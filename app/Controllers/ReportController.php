<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\View;
use App\Services\Ledger;

/**
 * Financial reports. The page renders filters (right panel) and the report body
 * is fetched by ajax from /reports/{name}/data (HTML fragment or CSV).
 */
final class ReportController
{
    private const TITLES = [
        'trial-balance' => 'Trial Balance',
        'ledger' => 'General Ledger',
        'profit-loss' => 'Profit & Loss',
        'balance-sheet' => 'Balance Sheet',
        'day-book' => 'Day Book',
    ];

    private function guard(): void
    {
        if (!Auth::instance()->can('reports', 'view') || !Auth::instance()->companyId()) {
            throw new HttpException(403);
        }
    }

    private function range(): array
    {
        $company = Auth::instance()->company();
        $from = (string) ($_GET['from'] ?? '');
        $to = (string) ($_GET['to'] ?? '');
        $valid = static fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
        if (!$valid($from)) {
            $from = DashboardController::fiscalYearStart($company);
        }
        if (!$valid($to)) {
            $to = date('Y-m-d');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        return [$from, $to];
    }

    public function show(string $report): string
    {
        $this->guard();
        [$from, $to] = $this->range();
        $accounts = $report === 'ledger'
            ? Database::all('SELECT id, code, name FROM accounts WHERE company_id = ? AND is_group = 0 ORDER BY code', [Auth::instance()->companyId()])
            : [];
        return View::render('reports/show', [
            'title' => self::TITLES[$report],
            'report' => $report,
            'activeModule' => 'reports',
            'from' => $from,
            'to' => $to,
            'accounts' => $accounts,
        ]);
    }

    public function data(string $report): string
    {
        $this->guard();
        [$from, $to] = $this->range();
        $cid = (int) Auth::instance()->companyId();
        $opts = [
            'zero' => ($_GET['zero'] ?? '0') === '1',
            'groups' => ($_GET['groups'] ?? '1') === '1',
        ];
        $data = match ($report) {
            'trial-balance' => $this->trialBalance($cid, $from, $to, $opts),
            'profit-loss' => $this->profitLoss($cid, $from, $to, $opts),
            'balance-sheet' => $this->balanceSheet($cid, $to, $opts),
            'ledger' => $this->ledger($cid, $from, $to, (int) ($_GET['account_id'] ?? 0)),
            'day-book' => $this->dayBook($cid, $from, $to),
        };
        if (($_GET['format'] ?? '') === 'csv') {
            $this->csv($report, $data['csv'] ?? []);
        }
        return View::partial('reports/' . $report, $data + ['from' => $from, 'to' => $to, 'opts' => $opts]);
    }

    private function filterRows(array $map, array $ids, array $opts): array
    {
        return array_values(array_filter($ids, static function ($id) use ($map, $opts) {
            $r = $map[$id];
            if (!$opts['groups'] && $r['is_group']) {
                return false;
            }
            if (!$opts['zero'] && abs($r['ob']) < 0.005 && abs($r['dr']) < 0.005 && abs($r['cr']) < 0.005) {
                return false;
            }
            return true;
        }));
    }

    private function trialBalance(int $cid, string $from, string $to, array $opts): array
    {
        $map = Ledger::balances($cid, $from, $to);
        $ids = $this->filterRows($map, Ledger::ordered($map), $opts);
        $tot = ['ob_dr' => 0, 'ob_cr' => 0, 'dr' => 0, 'cr' => 0, 'cb_dr' => 0, 'cb_cr' => 0];
        $csv = [['Code', 'Account', 'Type', 'Opening Dr', 'Opening Cr', 'Debit', 'Credit', 'Closing Dr', 'Closing Cr']];
        foreach ($map as $r) {
            if ($r['is_group']) {
                continue;
            }
            $tot['ob_dr'] += max($r['ob'], 0);
            $tot['ob_cr'] += max(-$r['ob'], 0);
            $tot['dr'] += $r['dr'];
            $tot['cr'] += $r['cr'];
            $tot['cb_dr'] += max($r['cb'], 0);
            $tot['cb_cr'] += max(-$r['cb'], 0);
        }
        foreach ($ids as $id) {
            $r = $map[$id];
            $csv[] = [$r['code'], str_repeat('  ', $r['depth']) . $r['name'], $r['account_type'], round(max($r['ob'], 0), 4), round(max(-$r['ob'], 0), 4), round($r['dr'], 4), round($r['cr'], 4), round(max($r['cb'], 0), 4), round(max(-$r['cb'], 0), 4)];
        }
        return ['map' => $map, 'ids' => $ids, 'tot' => $tot, 'csv' => $csv];
    }

    private function profitLoss(int $cid, string $from, string $to, array $opts): array
    {
        $map = Ledger::balances($cid, $from, $to, ['income', 'expense']);
        $ids = $this->filterRows($map, Ledger::ordered($map), ['zero' => $opts['zero'], 'groups' => $opts['groups']]);
        $income = $expense = 0.0;
        foreach ($map as $r) {
            if (!$r['is_group']) {
                $move = $r['dr'] - $r['cr'];
                $r['account_type'] === 'income' ? $income -= $move : $expense += $move;
            }
        }
        $csv = [['Code', 'Account', 'Amount']];
        foreach ($ids as $id) {
            $r = $map[$id];
            $amt = $r['account_type'] === 'income' ? $r['cr'] - $r['dr'] : $r['dr'] - $r['cr'];
            $csv[] = [$r['code'], str_repeat('  ', $r['depth']) . $r['name'], round($amt, 4)];
        }
        $csv[] = ['', 'Net Profit / (Loss)', round($income - $expense, 4)];
        return ['map' => $map, 'ids' => $ids, 'income' => $income, 'expense' => $expense, 'csv' => $csv];
    }

    private function balanceSheet(int $cid, string $to, array $opts): array
    {
        $map = Ledger::balances($cid, '1900-01-01', $to);
        $ids = $this->filterRows($map, Ledger::ordered($map), ['zero' => $opts['zero'], 'groups' => $opts['groups']]);
        $sum = ['asset' => 0.0, 'liability' => 0.0, 'equity' => 0.0, 'income' => 0.0, 'expense' => 0.0];
        foreach ($map as $r) {
            if (!$r['is_group']) {
                $sum[$r['account_type']] += $r['cb'];
            }
        }
        $profit = -($sum['income'] + $sum['expense']); // credit balance of P&L accounts
        $csv = [['Code', 'Account', 'Type', 'Balance']];
        foreach ($ids as $id) {
            $r = $map[$id];
            if (in_array($r['account_type'], ['income', 'expense'], true)) {
                continue;
            }
            $csv[] = [$r['code'], str_repeat('  ', $r['depth']) . $r['name'], $r['account_type'], round($r['account_type'] === 'asset' ? $r['cb'] : -$r['cb'], 4)];
        }
        return [
            'map' => $map, 'ids' => $ids, 'profit' => $profit,
            'assets' => $sum['asset'], 'liabilities' => -$sum['liability'], 'equity' => -$sum['equity'], 'csv' => $csv,
        ];
    }

    private function ledger(int $cid, string $from, string $to, int $accountId): array
    {
        $account = $accountId ? Database::one('SELECT * FROM accounts WHERE id = ? AND company_id = ?', [$accountId, $cid]) : null;
        if (!$account) {
            return ['account' => null, 'opening' => 0, 'lines' => [], 'csv' => []];
        }
        $opening = ($account['opening_type'] === 'Dr' ? 1 : -1) * (float) $account['opening_balance']
            + (float) Database::value(
                "SELECT COALESCE(SUM(l.debit_base - l.credit_base),0) FROM journal_lines l JOIN journals j ON j.id = l.journal_id
                  WHERE l.account_id = ? AND j.status = 'posted' AND j.journal_date < ?",
                [$accountId, $from]
            );
        $lines = Database::all(
            "SELECT j.id AS journal_id, j.number, j.journal_date, j.reference, j.narration, j.currency_code, l.description,
                    l.debit, l.credit, l.debit_base, l.credit_base,
                    (SELECT GROUP_CONCAT(DISTINCT a2.name ORDER BY l2.line_no SEPARATOR ', ')
                       FROM journal_lines l2 JOIN accounts a2 ON a2.id = l2.account_id
                      WHERE l2.journal_id = j.id AND l2.account_id <> l.account_id) AS contra
               FROM journal_lines l JOIN journals j ON j.id = l.journal_id
              WHERE l.account_id = ? AND j.status = 'posted' AND j.journal_date BETWEEN ? AND ?
           ORDER BY j.journal_date, j.id, l.line_no LIMIT 5000",
            [$accountId, $from, $to]
        );
        $csv = [['Date', 'Voucher', 'Particulars', 'Narration', 'Debit', 'Credit', 'Balance']];
        $bal = $opening;
        $csv[] = [$from, '', 'Opening Balance', '', '', '', round($bal, 4)];
        foreach ($lines as $l) {
            $bal += (float) $l['debit_base'] - (float) $l['credit_base'];
            $csv[] = [$l['journal_date'], $l['number'], $l['contra'], $l['description'] ?: $l['narration'], (float) $l['debit_base'], (float) $l['credit_base'], round($bal, 4)];
        }
        return ['account' => $account, 'opening' => $opening, 'lines' => $lines, 'csv' => $csv];
    }

    private function dayBook(int $cid, string $from, string $to): array
    {
        $rows = Database::all(
            "SELECT j.id, j.number, j.journal_date, j.narration, j.status, j.source_type, l.debit_base, l.credit_base, a.code, a.name
               FROM journals j JOIN journal_lines l ON l.journal_id = j.id JOIN accounts a ON a.id = l.account_id
              WHERE j.company_id = ? AND j.journal_date BETWEEN ? AND ? AND j.status <> 'void'
           ORDER BY j.journal_date, j.id, l.line_no LIMIT 10000",
            [$cid, $from, $to]
        );
        $vouchers = [];
        $csv = [['Date', 'Voucher', 'Status', 'Account', 'Debit', 'Credit', 'Narration']];
        foreach ($rows as $r) {
            $vouchers[$r['id']]['head'] = $r;
            $vouchers[$r['id']]['lines'][] = $r;
            $csv[] = [$r['journal_date'], $r['number'], $r['status'], $r['code'] . ' ' . $r['name'], (float) $r['debit_base'], (float) $r['credit_base'], $r['narration']];
        }
        return ['vouchers' => $vouchers, 'csv' => $csv];
    }

    private function csv(string $report, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $report . '-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($out, array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) && !is_numeric($v) ? "'" . $v : $v, $r), ',', '"', '\\');
        }
        exit;
    }
}
