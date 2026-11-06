<?php
declare(strict_types=1);

namespace App\Core;

/**
 * A declarative resource (list + filters + stats + form) loaded from
 * app/Resources/{module}/{name}.php.  The query builder only ever
 * interpolates developer-defined SQL fragments from the resource file;
 * every user supplied value is bound as a parameter and every sort key
 * is white-listed against the column definitions.
 */
final class Resource
{
    public array $c;
    public string $key;

    private function __construct(string $key, array $config)
    {
        $this->key = $key;
        $this->c = $config + [
            'pk' => 'id', 'tenant' => 't.company_id', 'search' => [], 'filters' => [], 'stats' => [],
            'fields' => [], 'row_actions' => [], 'hooks' => [], 'readonly' => false, 'can_create' => true,
            'can_delete' => true, 'export' => true, 'scope' => [], 'creator' => false, 'admin_only' => false,
            'super_only' => false, 'description' => '', 'icon' => 'list', 'views' => [], 'drawer_width' => 'max-w-2xl',
        ];
    }

    public static function load(string $module, string $name): ?self
    {
        if (!preg_match('/^[a-z]+$/', $module) || !preg_match('/^[a-z][a-z\-]*$/', $name)) {
            return null;
        }
        $file = BASE_PATH . "/app/Resources/$module/$name.php";
        if (!is_file($file)) {
            return null;
        }
        $config = require $file;
        $config['module'] ??= $module;
        return new self("$module/$name", $config);
    }

    /** Column holding the company id on the main table (null when tenancy comes from a join) */
    public function tenantColumn(): ?string
    {
        if (!$this->c['tenant']) {
            return null;
        }
        return array_key_exists('tenant_column', $this->c) ? $this->c['tenant_column'] : 'company_id';
    }

    public function url(string $suffix = ''): string
    {
        return url('/' . $this->key . ($suffix !== '' ? '/' . ltrim($suffix, '/') : ''));
    }

    public function authorize(string $action): void
    {
        $auth = Auth::instance();
        if ($this->c['super_only'] && !$auth->isSuper()) {
            throw new HttpException(403);
        }
        if ($this->c['admin_only'] && !$auth->isAdmin()) {
            throw new HttpException(403);
        }
        if (!$this->c['super_only'] && !$this->c['admin_only'] && !$auth->can($this->c['module'], $action)) {
            throw new HttpException(403);
        }
        if ($this->c['tenant'] && !$auth->companyId()) {
            throw new HttpException(403, 'Please select a company first.');
        }
    }

    public function allowed(string $action): bool
    {
        try {
            $this->authorize($action);
        } catch (HttpException) {
            return false;
        }
        return match ($action) {
            'create' => !$this->c['readonly'] && $this->c['can_create'],
            'edit' => !$this->c['readonly'],
            'delete' => !$this->c['readonly'] && $this->c['can_delete'],
            default => true,
        };
    }

    /** @return array{0:string,1:array} WHERE clause and its bound parameters */
    public function where(array $in, bool $withFilters = true): array
    {
        $w = ['1=1'];
        $p = [];
        if ($this->c['tenant']) {
            $w[] = $this->c['tenant'] . ' = ?';
            $p[] = Auth::instance()->companyId();
        }
        foreach ($this->c['scope'] as $col => $val) {
            $w[] = "t.`$col` = ?";
            $p[] = $val;
        }
        if (isset($this->c['where']) && is_callable($this->c['where'])) {
            [$sql, $params] = ($this->c['where'])();
            $w[] = "($sql)";
            array_push($p, ...$params);
        }
        if (!$withFilters) {
            return [implode(' AND ', $w), $p];
        }
        $q = trim((string) ($in['q'] ?? ''));
        if ($q !== '' && $this->c['search']) {
            $parts = [];
            foreach ($this->c['search'] as $expr) {
                $parts[] = "$expr LIKE ?";
                $p[] = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
            }
            $w[] = '(' . implode(' OR ', $parts) . ')';
        }
        $f = is_array($in['f'] ?? null) ? $in['f'] : [];
        foreach ($this->c['filters'] as $key => $flt) {
            $sql = $flt['sql'];
            switch ($flt['type']) {
                case 'daterange':
                case 'numrange':
                    $from = trim((string) ($f[$key . '_from'] ?? ''));
                    $to = trim((string) ($f[$key . '_to'] ?? ''));
                    $isDate = $flt['type'] === 'daterange';
                    if ($from !== '' && ($isDate ? self::isDate($from) : is_numeric($from))) {
                        $w[] = $isDate ? "DATE($sql) >= ?" : "$sql >= ?";
                        $p[] = $from;
                    }
                    if ($to !== '' && ($isDate ? self::isDate($to) : is_numeric($to))) {
                        $w[] = $isDate ? "DATE($sql) <= ?" : "$sql <= ?";
                        $p[] = $to;
                    }
                    break;
                case 'text':
                    $v = trim((string) ($f[$key] ?? ''));
                    if ($v !== '') {
                        $w[] = "$sql LIKE ?";
                        $p[] = '%' . addcslashes(mb_substr($v, 0, 100), '%_\\') . '%';
                    }
                    break;
                default: // select / lookup (single or multiple values)
                    $v = $f[$key] ?? '';
                    $values = array_values(array_filter((array) $v, static fn ($x) => $x !== '' && $x !== null));
                    if ($values) {
                        $allowed = array_map('strval', array_keys($this->filterOptions($key)));
                        $values = array_values(array_filter($values, static fn ($x) => in_array((string) $x, $allowed, true)));
                        if ($values) {
                            $in = implode(',', array_fill(0, count($values), '?'));
                            $w[] = isset($flt['in_template']) ? str_replace('{in}', $in, $flt['in_template']) : "$sql IN ($in)";
                            array_push($p, ...$values);
                        }
                    }
            }
        }
        return [implode(' AND ', $w), $p];
    }

    public function filterOptions(string $key): array
    {
        $flt = $this->c['filters'][$key] ?? [];
        $o = $flt['options'] ?? [];
        return is_callable($o) ? $o() : $o;
    }

    public function fieldOptions(array $field): array
    {
        $o = $field['options'] ?? [];
        return is_callable($o) ? $o() : $o;
    }

    private static function isDate(string $v): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
    }

    public function selectList(): string
    {
        $sel = ['t.' . $this->c['pk'] . ' AS __id'];
        foreach ($this->c['columns'] as $key => $col) {
            $sel[] = ($col['sql'] ?? "t.`$key`") . " AS `$key`";
        }
        foreach ($this->c['select_extra'] ?? [] as $alias => $expr) {
            $sel[] = "$expr AS `$alias`";
        }
        return implode(', ', $sel);
    }

    public function orderBy(array $in): string
    {
        [$defKey, $defDir] = $this->c['default_sort'] ?? [array_key_first($this->c['columns']), 'asc'];
        $key = (string) ($in['sort'] ?? $defKey);
        $dir = strtolower((string) ($in['dir'] ?? $defDir)) === 'desc' ? 'DESC' : 'ASC';
        $col = $this->c['columns'][$key] ?? null;
        if (!$col || ($col['sortable'] ?? true) === false) {
            $key = $defKey;
            $col = $this->c['columns'][$key];
            $dir = strtolower($defDir) === 'desc' ? 'DESC' : 'ASC';
        }
        $expr = $col['sort_sql'] ?? $col['sql'] ?? "t.`$key`";
        return "$expr $dir, t." . $this->c['pk'] . " $dir";
    }

    /** @return array{rows:array,total:int} */
    public function fetch(array $in, ?int $limit, int $offset = 0): array
    {
        [$where, $params] = $this->where($in);
        $from = $this->c['from'] ?? ('`' . $this->c['table'] . '` t');
        $group = isset($this->c['group_by']) ? ' GROUP BY ' . $this->c['group_by'] : '';
        $countSql = $group
            ? "SELECT COUNT(*) FROM (SELECT 1 FROM $from WHERE $where$group) x"
            : "SELECT COUNT(*) FROM $from WHERE $where";
        $total = (int) Database::value($countSql, $params);
        $sql = 'SELECT ' . $this->selectList() . " FROM $from WHERE $where$group ORDER BY " . $this->orderBy($in);
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        return ['rows' => Database::all($sql, $params), 'total' => $total];
    }

    public function stats(array $in): array
    {
        if (!$this->c['stats']) {
            return [];
        }
        [$where, $params] = $this->where($in);
        $base = $this->c['stats_from'] ?? $this->c['from'] ?? ('`' . $this->c['table'] . '` t');
        $sel = [];
        foreach ($this->c['stats'] as $i => $s) {
            $sel[] = $s['sql'] . " AS s$i";
        }
        $row = Database::one('SELECT ' . implode(', ', $sel) . " FROM $base WHERE $where", $params) ?? [];
        $out = [];
        foreach ($this->c['stats'] as $i => $s) {
            $v = $row["s$i"] ?? 0;
            $out[] = [
                'label' => $s['label'],
                'value' => match ($s['format'] ?? 'int') {
                    'money' => money($v),
                    'text' => (string) $v,
                    default => number_format((float) $v),
                },
                'svg'   => icon($s['icon'] ?? 'hash', 'size-[18px]'),
                'tone'  => $s['tone'] ?? 'slate',
                'hint'  => $s['hint'] ?? '',
            ];
        }
        return $out;
    }

    public function find(int $id): ?array
    {
        [$where, $params] = $this->where([], false);
        $from = $this->c['form_from'] ?? $this->c['from'] ?? ('`' . $this->c['table'] . '` t');
        $sel = $this->c['form_select'] ?? 't.*';
        return Database::one("SELECT $sel FROM $from WHERE $where AND t." . $this->c['pk'] . ' = ? LIMIT 1', [...$params, $id]);
    }

    /** Format one cell to safe HTML */
    public function cell(string $key, array $row): string
    {
        $col = $this->c['columns'][$key];
        $v = $row[$key] ?? null;
        if (isset($col['render']) && is_callable($col['render'])) {
            return (string) $col['render']($row);
        }
        return match ($col['format'] ?? 'text') {
            'money' => $v === null ? '' : '<span class="tabular-nums' . ((float) $v < 0 ? ' text-rose-600' : '') . '">' . money($v, $col['decimals'] ?? null) . '</span>',
            'number' => $v === null ? '' : '<span class="tabular-nums">' . e(rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.')) . '</span>',
            'date' => e(fmt_date($v)),
            'datetime' => e(local_dt($v)),
            'code' => '<span class="font-mono text-xs font-semibold text-slate-700">' . e($v) . '</span>',
            'status' => (int) $v === 1 ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-slate">Inactive</span>',
            'bool' => (int) $v === 1 ? '<span class="badge badge-blue">Yes</span>' : '<span class="text-slate-400">—</span>',
            'badge' => self::badge($col['map'] ?? [], $v),
            default => e($v),
        };
    }

    public static function badge(array $map, mixed $v): string
    {
        $m = $map[(string) $v] ?? [ucfirst((string) $v), 'slate'];
        [$label, $tone] = is_array($m) ? $m : [$m, 'slate'];
        return '<span class="badge badge-' . e($tone) . '">' . e($label) . '</span>';
    }

    /** Plain text value for CSV export */
    public function plain(string $key, array $row): string
    {
        $col = $this->c['columns'][$key];
        $v = $row[$key] ?? '';
        return match ($col['format'] ?? 'text') {
            'badge' => (string) (is_array($col['map'][(string) $v] ?? null) ? $col['map'][(string) $v][0] : ($col['map'][(string) $v] ?? $v)),
            'status' => (int) $v === 1 ? 'Active' : 'Inactive',
            'bool' => (int) $v === 1 ? 'Yes' : 'No',
            'datetime' => local_dt($v),
            default => isset($col['export']) && is_callable($col['export']) ? (string) $col['export']($row) : (string) $v,
        };
    }

    /**
     * Validate + cast posted input against the field definitions.
     * @return array<string,mixed> columns => values
     */
    public function validate(array $input, ?array $existing): array
    {
        $errors = [];
        $data = [];
        foreach ($this->c['fields'] as $name => $f) {
            $type = $f['type'] ?? 'text';
            if ($type === 'section') {
                continue;
            }
            if ($existing && !empty($f['create_only'])) {
                continue;
            }
            if (!$existing && !empty($f['edit_only'])) {
                continue;
            }
            if (isset($f['visible']) && is_callable($f['visible']) && !$f['visible']($existing)) {
                continue;
            }
            $label = $f['label'] ?? $name;
            $raw = $input[$name] ?? null;
            if ($type === 'checkbox') {
                $data[$name] = !empty($raw) ? 1 : 0;
                continue;
            }
            if ($type === 'multiselect') {
                $vals = array_map('strval', (array) ($raw ?? []));
                $allowed = array_map('strval', array_keys($this->fieldOptions($f)));
                $vals = array_values(array_intersect($vals, $allowed));
                if (!empty($f['required']) && !$vals) {
                    $errors[$name] = "$label is required.";
                }
                $data[$name] = $vals;
                continue;
            }
            $v = is_string($raw) ? trim($raw) : (is_scalar($raw) ? (string) $raw : '');
            if (!empty($f['lowercase'])) {
                $v = mb_strtolower($v);
            }
            if ($type === 'password' && $v === '' && $existing) {
                continue; // keep the existing password
            }
            if ($v === '') {
                $required = $f['required'] ?? false;
                if (is_callable($required)) {
                    $required = $required($input, $existing);
                }
                if ($required) {
                    $errors[$name] = "$label is required.";
                }
                $data[$name] = in_array($type, ['decimal', 'number'], true) && empty($f['nullable']) ? 0 : null;
                continue;
            }
            if (isset($f['max']) && mb_strlen($v) > $f['max']) {
                $errors[$name] = "$label must be at most {$f['max']} characters.";
                continue;
            }
            switch ($type) {
                case 'email':
                    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
                        $errors[$name] = 'Enter a valid e-mail address.';
                    }
                    break;
                case 'number':
                    if (!preg_match('/^-?\d+$/', $v)) {
                        $errors[$name] = "$label must be a whole number.";
                    }
                    $v = (int) $v;
                    break;
                case 'decimal':
                    $v = str_replace(',', '', $v);
                    if (!is_numeric($v)) {
                        $errors[$name] = "$label must be a number.";
                    }
                    break;
                case 'date':
                    if (!self::isDate($v)) {
                        $errors[$name] = 'Enter a valid date.';
                    }
                    break;
                case 'select':
                case 'lookup':
                    // Only values offered in the (tenant scoped) option list are accepted.
                    if (!array_key_exists($v, $this->fieldOptions($f))) {
                        $errors[$name] = "Select a valid $label.";
                    }
                    break;
            }
            if (isset($f['pattern']) && !isset($errors[$name]) && !preg_match($f['pattern'], (string) $v)) {
                $errors[$name] = $f['pattern_message'] ?? "$label format is invalid.";
            }
            if (isset($f['min']) && is_numeric($v) && $v < $f['min']) {
                $errors[$name] = "$label must be at least {$f['min']}.";
            }
            if (!empty($f['uppercase'])) {
                $v = strtoupper((string) $v);
            }
            $data[$name] = $v;
        }
        // Uniqueness checks (within the company / scope)
        foreach ($this->c['fields'] as $name => $f) {
            if (empty($f['unique']) || isset($errors[$name]) || !isset($data[$name]) || $data[$name] === null || $data[$name] === '') {
                continue;
            }
            $sql = 'SELECT COUNT(*) FROM `' . $this->c['table'] . "` WHERE `$name` = ?";
            $p = [$data[$name]];
            if (($tc = $this->tenantColumn()) && empty($f['unique_global'])) {
                $sql .= " AND `$tc` = ?";
                $p[] = Auth::instance()->companyId();
            }
            foreach ($this->c['scope'] as $col => $val) {
                $sql .= " AND `$col` = ?";
                $p[] = $val;
            }
            if ($existing) {
                $sql .= ' AND ' . $this->c['pk'] . ' <> ?';
                $p[] = $existing[$this->c['pk']];
            }
            if ((int) Database::value($sql, $p) > 0) {
                $errors[$name] = ($f['label'] ?? $name) . ' already exists.';
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $data;
    }
}
