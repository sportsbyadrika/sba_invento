<?php
return [
    'title' => 'Document Numbering',
    'singular' => 'Number Series',
    'icon' => 'hash',
    'admin_only' => true,
    'description' => 'Prefix and next number for each document type.',
    'table' => 'number_series',
    'can_create' => false,
    'can_delete' => false,
    'drawer_width' => 'max-w-lg',
    'columns' => [
        'doc_type' => ['label' => 'Document', 'sql' => 't.doc_type', 'render' => static fn ($r) => e(ucfirst($r['doc_type']))],
        'prefix' => ['label' => 'Prefix', 'sql' => 't.prefix', 'format' => 'code'],
        'next_number' => ['label' => 'Next Number', 'sql' => 't.next_number', 'align' => 'right'],
        'padding' => ['label' => 'Digits', 'sql' => 't.padding', 'align' => 'right'],
        'preview' => ['label' => 'Preview', 'sql' => "CONCAT(t.prefix, LPAD(t.next_number, t.padding, '0'))", 'format' => 'code'],
    ],
    'default_sort' => ['doc_type', 'asc'],
    'fields' => [
        'prefix' => ['label' => 'Prefix', 'required' => true, 'max' => 20, 'pattern' => '/^[A-Za-z0-9\/\-_]*$/', 'pattern_message' => 'Letters, digits, / - _ only.'],
        'next_number' => ['label' => 'Next Number', 'type' => 'number', 'required' => true, 'min' => 1],
        'padding' => ['label' => 'Digits (zero padding)', 'type' => 'number', 'required' => true, 'min' => 1],
    ],
];
