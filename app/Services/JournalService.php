<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;

/**
 * Creates / updates / posts journals.  This is the single entry point other
 * modules (invoices, bills, payments) will use to post into the ledger.
 */
final class JournalService
{
    /**
     * @param array $h header: id?, journal_date, reference, narration, currency_code, exchange_rate
     * @param array $lines [account_id, description, debit, credit]
     */
    public static function save(array $h, array $lines, bool $post, string $sourceType = 'manual', ?int $sourceId = null): int
    {
        $auth = Auth::instance();
        $cid = (int) $auth->companyId();
        $company = $auth->company();
        $errors = [];

        $date = (string) ($h['journal_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            $errors['journal_date'] = 'Enter a valid date.';
        }
        $currency = strtoupper((string) ($h['currency_code'] ?? $company['base_currency']));
        $cur = Database::one('SELECT * FROM currencies WHERE company_id = ? AND code = ? AND is_active = 1', [$cid, $currency]);
        if (!$cur) {
            $errors['currency_code'] = 'Select an active currency.';
        }
        $rate = $currency === $company['base_currency'] ? 1.0 : (float) str_replace(',', '', (string) ($h['exchange_rate'] ?? 0));
        if ($rate <= 0) {
            $errors['exchange_rate'] = 'Exchange rate must be greater than zero.';
        }
        $dec = (int) ($cur['decimal_places'] ?? 2);
        $baseDec = (int) ($company['_decimals'] ?? 2);

        $clean = [];
        $accountIds = [];
        foreach ($lines as $i => $l) {
            $acc = (int) ($l['account_id'] ?? 0);
            $dr = round((float) str_replace(',', '', (string) ($l['debit'] ?? 0)), $dec);
            $cr = round((float) str_replace(',', '', (string) ($l['credit'] ?? 0)), $dec);
            if (!$acc && $dr == 0 && $cr == 0) {
                continue; // empty row
            }
            if (!$acc) {
                $errors["lines.$i.account_id"] = 'Select an account.';
            }
            if ($dr < 0 || $cr < 0) {
                $errors["lines.$i.debit"] = 'Amounts cannot be negative.';
            }
            if ($dr > 0 && $cr > 0) {
                $errors["lines.$i.debit"] = 'A line can have either a debit or a credit, not both.';
            }
            if ($dr == 0 && $cr == 0) {
                $errors["lines.$i.debit"] = 'Enter a debit or credit amount.';
            }
            $accountIds[] = $acc;
            $clean[] = ['account_id' => $acc, 'description' => mb_substr(trim((string) ($l['description'] ?? '')), 0, 255) ?: null, 'debit' => $dr, 'credit' => $cr, 'row' => $i];
        }
        if (count($clean) < 2) {
            $errors['lines'] = 'A journal needs at least two lines.';
        }
        if ($accountIds) {
            $ids = array_values(array_unique(array_filter($accountIds)));
            $valid = $ids ? array_map('intval', array_column(Database::all(
                'SELECT id FROM accounts WHERE company_id = ? AND is_group = 0 AND is_active = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                [$cid, ...$ids]
            ), 'id')) : [];
            foreach ($clean as $l) {
                if ($l['account_id'] && !in_array($l['account_id'], $valid, true)) {
                    $errors["lines.{$l['row']}.account_id"] = 'Account is not a valid active ledger.';
                }
            }
        }
        $totalDr = round(array_sum(array_column($clean, 'debit')), $dec);
        $totalCr = round(array_sum(array_column($clean, 'credit')), $dec);
        if ($post && abs($totalDr - $totalCr) > 0.00001) {
            $errors['lines'] = sprintf('Debits (%s) and credits (%s) must be equal before posting. Difference: %s',
                number_format($totalDr, $dec), number_format($totalCr, $dec), number_format(abs($totalDr - $totalCr), $dec));
        }
        if ($errors) {
            throw new ValidationException($errors, $errors['lines'] ?? 'Please correct the highlighted fields.');
        }

        // Base currency amounts; any rounding difference goes to the largest line so base stays balanced.
        foreach ($clean as &$l) {
            $l['debit_base'] = round($l['debit'] * $rate, $baseDec);
            $l['credit_base'] = round($l['credit'] * $rate, $baseDec);
        }
        unset($l);
        $diff = round(array_sum(array_column($clean, 'debit_base')) - array_sum(array_column($clean, 'credit_base')), $baseDec);
        if ($totalDr == $totalCr && $diff != 0.0) {
            $idx = 0;
            foreach ($clean as $k => $l) {
                if (max($l['debit_base'], $l['credit_base']) > max($clean[$idx]['debit_base'], $clean[$idx]['credit_base'])) {
                    $idx = $k;
                }
            }
            if ($clean[$idx]['debit_base'] > 0) {
                $clean[$idx]['debit_base'] -= $diff;
            } else {
                $clean[$idx]['credit_base'] += $diff;
            }
        }

        return Database::transaction(static function () use ($h, $clean, $post, $cid, $currency, $rate, $date, $totalDr, $baseDec, $auth, $sourceType, $sourceId) {
            $id = (int) ($h['id'] ?? 0);
            $header = [
                'journal_date' => $date,
                'reference' => mb_substr(trim((string) ($h['reference'] ?? '')), 0, 60) ?: null,
                'narration' => mb_substr(trim((string) ($h['narration'] ?? '')), 0, 500) ?: null,
                'currency_code' => $currency,
                'exchange_rate' => $rate,
                'total_amount' => $totalDr,
                'total_base' => round(array_sum(array_column($clean, 'debit_base')), $baseDec),
            ];
            if ($post) {
                $header += ['status' => 'posted', 'posted_by' => $auth->id(), 'posted_at' => now_utc()];
            }
            if ($id) {
                $existing = Database::one('SELECT * FROM journals WHERE id = ? AND company_id = ? FOR UPDATE', [$id, $cid]);
                if (!$existing) {
                    throw new ValidationException([], 'Journal not found.');
                }
                if ($existing['status'] !== 'draft') {
                    throw new ValidationException([], 'Only draft journals can be edited. Create a reversing entry instead.');
                }
                Database::update('journals', $header, 'id = ? AND company_id = ?', [$id, $cid]);
                Database::query('DELETE FROM journal_lines WHERE journal_id = ?', [$id]);
                Audit::log('journal', $id, $post ? 'update+post' : 'update', $existing, $header);
            } else {
                $header += [
                    'company_id' => $cid,
                    'number' => NumberSeries::next($cid, 'journal'),
                    'status' => $post ? 'posted' : 'draft',
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'created_by' => $auth->id(),
                ];
                $id = Database::insert('journals', $header);
                Audit::log('journal', $id, $post ? 'create+post' : 'create', null, $header);
            }
            foreach ($clean as $n => $l) {
                Database::insert('journal_lines', [
                    'journal_id' => $id, 'company_id' => $cid, 'line_no' => $n + 1, 'account_id' => $l['account_id'],
                    'description' => $l['description'], 'debit' => $l['debit'], 'credit' => $l['credit'],
                    'debit_base' => $l['debit_base'], 'credit_base' => $l['credit_base'],
                ]);
            }
            return $id;
        });
    }

    public static function post(int $id): void
    {
        $cid = (int) Auth::instance()->companyId();
        $j = Database::one('SELECT * FROM journals WHERE id = ? AND company_id = ? FOR UPDATE', [$id, $cid]);
        if (!$j || $j['status'] !== 'draft') {
            throw new ValidationException([], 'Only draft journals can be posted.');
        }
        $t = Database::one('SELECT COUNT(*) n, SUM(debit) dr, SUM(credit) cr, SUM(debit_base) bdr, SUM(credit_base) bcr FROM journal_lines WHERE journal_id = ?', [$id]);
        if ((int) $t['n'] < 2 || abs((float) $t['dr'] - (float) $t['cr']) > 0.00001 || abs((float) $t['bdr'] - (float) $t['bcr']) > 0.00001) {
            throw new ValidationException([], 'The journal is not balanced. Open it and correct the lines before posting.');
        }
        Database::update('journals', ['status' => 'posted', 'posted_by' => Auth::instance()->id(), 'posted_at' => now_utc()], 'id = ?', [$id]);
    }
}
