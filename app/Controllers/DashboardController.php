<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

final class DashboardController
{
    public function home(): never
    {
        redirect(Auth::instance()->check() ? '/dashboard' : '/login');
    }

    public function index(): string
    {
        $auth = Auth::instance();
        $cid = $auth->companyId();
        $kpi = [];
        $recent = [];
        $monthly = [];
        if ($cid) {
            $balances = Database::all(
                "SELECT a.account_type, a.sub_type,
                        SUM(CASE WHEN a.opening_type='Dr' THEN a.opening_balance ELSE -a.opening_balance END) AS opening
                   FROM accounts a WHERE a.company_id = ? AND a.is_group = 0 GROUP BY a.account_type, a.sub_type",
                [$cid]
            );
            $movement = Database::all(
                "SELECT a.account_type, a.sub_type, SUM(l.debit_base - l.credit_base) AS net
                   FROM journal_lines l JOIN journals j ON j.id = l.journal_id AND j.status = 'posted'
                   JOIN accounts a ON a.id = l.account_id
                  WHERE l.company_id = ? GROUP BY a.account_type, a.sub_type",
                [$cid]
            );
            $sum = static function (array $rows, string $field, callable $match): float {
                $t = 0.0;
                foreach ($rows as $r) {
                    if ($match($r)) {
                        $t += (float) $r[$field];
                    }
                }
                return $t;
            };
            $isCash = static fn ($r) => in_array($r['sub_type'], ['cash', 'bank'], true);
            $isAR = static fn ($r) => $r['sub_type'] === 'receivable';
            $isAP = static fn ($r) => $r['sub_type'] === 'payable';
            $yearStart = self::fiscalYearStart($auth->company());
            $pl = Database::one(
                "SELECT SUM(CASE WHEN a.account_type='income' THEN l.credit_base - l.debit_base ELSE 0 END) AS income,
                        SUM(CASE WHEN a.account_type='expense' THEN l.debit_base - l.credit_base ELSE 0 END) AS expense
                   FROM journal_lines l JOIN journals j ON j.id = l.journal_id AND j.status='posted'
                   JOIN accounts a ON a.id = l.account_id
                  WHERE l.company_id = ? AND j.journal_date >= ?",
                [$cid, $yearStart]
            ) ?? [];
            $kpi = [
                ['label' => 'Cash & Bank', 'value' => $sum($balances, 'opening', $isCash) + $sum($movement, 'net', $isCash), 'icon' => 'landmark', 'tone' => 'cyan'],
                ['label' => 'Receivables', 'value' => $sum($balances, 'opening', $isAR) + $sum($movement, 'net', $isAR), 'icon' => 'hand-coins', 'tone' => 'emerald'],
                ['label' => 'Payables', 'value' => -($sum($balances, 'opening', $isAP) + $sum($movement, 'net', $isAP)), 'icon' => 'banknote', 'tone' => 'amber'],
                ['label' => 'Net Profit (FY)', 'value' => (float) ($pl['income'] ?? 0) - (float) ($pl['expense'] ?? 0), 'icon' => 'trending-up', 'tone' => 'indigo'],
            ];
            $recent = Database::all(
                "SELECT id, number, journal_date, narration, total_base, status FROM journals WHERE company_id = ? ORDER BY id DESC LIMIT 6",
                [$cid]
            );
            $monthly = Database::all(
                "SELECT DATE_FORMAT(j.journal_date, '%Y-%m') ym,
                        SUM(CASE WHEN a.account_type='income' THEN l.credit_base - l.debit_base ELSE 0 END) income,
                        SUM(CASE WHEN a.account_type='expense' THEN l.debit_base - l.credit_base ELSE 0 END) expense
                   FROM journal_lines l JOIN journals j ON j.id = l.journal_id AND j.status='posted'
                   JOIN accounts a ON a.id = l.account_id
                  WHERE l.company_id = ? AND j.journal_date >= ?
               GROUP BY ym ORDER BY ym",
                [$cid, date('Y-m-01', strtotime('-5 months'))]
            );
        }
        $counts = $cid ? Database::one(
            "SELECT (SELECT COUNT(*) FROM accounts WHERE company_id = ?) accounts,
                    (SELECT COUNT(*) FROM journals WHERE company_id = ? AND status='draft') drafts,
                    (SELECT COUNT(*) FROM items WHERE company_id = ? AND is_active=1) items,
                    (SELECT COUNT(*) FROM contacts WHERE company_id = ? AND contact_type='customer') customers,
                    (SELECT COUNT(*) FROM contacts WHERE company_id = ? AND contact_type='vendor') vendors",
            [$cid, $cid, $cid, $cid, $cid]
        ) : null;
        return View::render('pages/dashboard', [
            'title' => 'Dashboard', 'activeModule' => 'dashboard',
            'kpi' => $kpi, 'recent' => $recent, 'monthly' => $monthly, 'counts' => $counts,
        ]);
    }

    public static function fiscalYearStart(?array $company, ?string $asOf = null): string
    {
        $m = (int) ($company['fiscal_year_start'] ?? 1);
        $ts = $asOf ? strtotime($asOf) : time();
        $y = (int) date('Y', $ts);
        if ((int) date('n', $ts) < $m) {
            $y--;
        }
        return sprintf('%04d-%02d-01', $y, $m);
    }
}
