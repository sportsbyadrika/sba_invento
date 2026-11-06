<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

/** Placeholder screen for menu items that are scheduled for a later phase. */
final class PlannedController
{
    public const PHASES = [
        1 => 'Foundation & Accountant',
        2 => 'Accountant advanced',
        3 => 'Inventory transactions',
        4 => 'Sales cycle',
        5 => 'Purchase cycle',
        6 => 'Banking',
        7 => 'Statutory & analytics reports',
    ];

    public static function find(string $path): ?array
    {
        foreach (require BASE_PATH . '/config/menu.php' as $group) {
            foreach ($group['children'] ?? [] as $child) {
                if ($child['url'] === $path && !empty($child['planned'])) {
                    return $child + ['group' => $group['label'], 'module' => $group['module']];
                }
            }
        }
        return null;
    }

    public function show(array $item): string
    {
        return View::render('pages/planned', [
            'title' => $item['label'],
            'item' => $item,
            'activeModule' => $item['module'],
            'phaseName' => self::PHASES[$item['planned']] ?? '',
        ]);
    }
}
