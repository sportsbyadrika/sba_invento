<?php
/**
 * Top navigation.  Order follows the delivery priority:
 * Accountant -> Inventory -> Sales -> Purchases -> Banking -> Reports.
 * 'planned' => phase number marks screens that are designed but not yet built.
 */
return [
    ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'module' => 'dashboard', 'url' => '/dashboard'],
    ['label' => 'Accountant', 'icon' => 'calculator', 'module' => 'accountant', 'children' => [
        ['label' => 'Chart of Accounts', 'url' => '/accountant/chart-of-accounts', 'icon' => 'git-fork', 'hint' => 'Groups & ledgers tree'],
        ['label' => 'Manual Journals', 'url' => '/accountant/journals', 'icon' => 'book-open', 'hint' => 'Double-entry vouchers'],
        ['label' => 'Currency Revaluation', 'url' => '/accountant/revaluation', 'icon' => 'refresh-cw', 'planned' => 2],
        ['label' => 'Opening Balances', 'url' => '/accountant/opening-balances', 'icon' => 'scale', 'planned' => 2],
    ]],
    ['label' => 'Inventory', 'icon' => 'package', 'module' => 'inventory', 'children' => [
        ['label' => 'Items', 'url' => '/inventory/items', 'icon' => 'package', 'hint' => 'Goods & services catalog'],
        ['label' => 'Item Categories', 'url' => '/inventory/categories', 'icon' => 'folder-tree'],
        ['label' => 'Units of Measure', 'url' => '/inventory/units', 'icon' => 'ruler'],
        ['label' => 'Warehouses', 'url' => '/inventory/warehouses', 'icon' => 'warehouse'],
        ['label' => 'Price Lists', 'url' => '/inventory/price-lists', 'icon' => 'tags', 'planned' => 3],
        ['label' => 'Stock Adjustments', 'url' => '/inventory/adjustments', 'icon' => 'sliders-horizontal', 'planned' => 3],
        ['label' => 'Stock Transfers', 'url' => '/inventory/transfers', 'icon' => 'arrow-left-right', 'planned' => 3],
    ]],
    ['label' => 'Sales', 'icon' => 'trending-up', 'module' => 'sales', 'children' => [
        ['label' => 'Customers', 'url' => '/sales/customers', 'icon' => 'users'],
        ['label' => 'Quotes', 'url' => '/sales/quotes', 'icon' => 'file-text', 'planned' => 4],
        ['label' => 'Sales Orders', 'url' => '/sales/orders', 'icon' => 'clipboard-list', 'planned' => 4],
        ['label' => 'Invoices', 'url' => '/sales/invoices', 'icon' => 'receipt', 'planned' => 4],
        ['label' => 'Recurring Invoices', 'url' => '/sales/recurring', 'icon' => 'repeat', 'planned' => 4],
        ['label' => 'Delivery Notes', 'url' => '/sales/delivery-notes', 'icon' => 'truck', 'planned' => 4],
        ['label' => 'Payments Received', 'url' => '/sales/receipts', 'icon' => 'hand-coins', 'planned' => 4],
        ['label' => 'Credit Notes', 'url' => '/sales/credit-notes', 'icon' => 'file-minus', 'planned' => 4],
    ]],
    ['label' => 'Purchases', 'icon' => 'shopping-bag', 'module' => 'purchases', 'children' => [
        ['label' => 'Vendors', 'url' => '/purchases/vendors', 'icon' => 'store'],
        ['label' => 'Purchase Orders', 'url' => '/purchases/orders', 'icon' => 'clipboard-list', 'planned' => 5],
        ['label' => 'Goods Receipt (GRN)', 'url' => '/purchases/grn', 'icon' => 'package-check', 'planned' => 5],
        ['label' => 'Bills', 'url' => '/purchases/bills', 'icon' => 'receipt', 'planned' => 5],
        ['label' => 'Payments Made', 'url' => '/purchases/payments', 'icon' => 'banknote', 'planned' => 5],
        ['label' => 'Debit Notes', 'url' => '/purchases/debit-notes', 'icon' => 'file-plus', 'planned' => 5],
    ]],
    ['label' => 'Banking', 'icon' => 'landmark', 'module' => 'banking', 'children' => [
        ['label' => 'Bank & Cash Accounts', 'url' => '/banking/accounts', 'icon' => 'landmark'],
        ['label' => 'Fund Transfers', 'url' => '/banking/transfers', 'icon' => 'arrow-left-right', 'planned' => 6],
        ['label' => 'Bank Reconciliation', 'url' => '/banking/reconciliation', 'icon' => 'check-check', 'planned' => 6],
        ['label' => 'Statement Import', 'url' => '/banking/import', 'icon' => 'upload', 'planned' => 6],
    ]],
    ['label' => 'Reports', 'icon' => 'chart-column', 'module' => 'reports', 'children' => [
        ['label' => 'Trial Balance', 'url' => '/reports/trial-balance', 'icon' => 'scale'],
        ['label' => 'General Ledger', 'url' => '/reports/ledger', 'icon' => 'book-open'],
        ['label' => 'Profit & Loss', 'url' => '/reports/profit-loss', 'icon' => 'trending-up'],
        ['label' => 'Balance Sheet', 'url' => '/reports/balance-sheet', 'icon' => 'landmark'],
        ['label' => 'Day Book', 'url' => '/reports/day-book', 'icon' => 'calendar-days'],
        ['label' => 'AR / AP Aging', 'url' => '/reports/aging', 'icon' => 'hourglass', 'planned' => 7],
        ['label' => 'VAT Return (UAE)', 'url' => '/reports/vat-return', 'icon' => 'file-check', 'planned' => 7],
        ['label' => 'GSTR-1 / GSTR-3B (India)', 'url' => '/reports/gst-returns', 'icon' => 'file-check', 'planned' => 7],
    ]],
];
