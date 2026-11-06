<?php
/**
 * Drawer form fragment (loaded by ajax into the right-hand drawer).
 * @var App\Core\Resource $r
 * @var array|null $row
 * @var bool $readonly
 */
$c = $r->c;
$isEdit = $row !== null;
$title = ($isEdit ? ($readonly ? '' : 'Edit ') : 'New ') . $c['singular'];
$value = static function (string $name, array $f) use ($row) {
    if ($row !== null) {
        return $row[$name] ?? null;
    }
    if (!empty($f['from_query']) && isset($_GET[$name]) && is_scalar($_GET[$name])) {
        return (string) $_GET[$name];
    }
    return $f['default'] ?? null;
};
?>
<form class="flex h-full flex-col" data-drawer-form action="<?= e($r->url('save')) ?>" method="post" novalidate data-enter-nav autocomplete="off">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $row[$c['pk']] ?>"><?php endif; ?>
  <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
    <div>
      <p class="text-xs font-semibold text-cyan-700"><?= e($c['title']) ?></p>
      <h2 id="drawer-title" class="text-lg font-bold text-slate-900"><?= e($title) ?><?php if ($isEdit && (isset($row['code']) || isset($row['sku']))): ?> <span class="font-mono text-sm text-slate-500"><?= e($row['code'] ?? $row['sku']) ?></span><?php endif; ?></h2>
    </div>
    <button type="button" class="btn btn-ghost btn-icon" data-drawer-close aria-label="Close"><?= icon('x', 'size-5') ?></button>
  </div>

  <div class="flex-1 overflow-y-auto px-5 py-4">
    <div class="mb-3 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>
    <?php if ($readonly): ?>
      <div class="mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"><?= icon('lock', 'size-4') ?>Read only — you do not have permission to change this record.</div>
    <?php endif; ?>
    <div class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
      <?php foreach ($c['fields'] as $name => $f):
          $type = $f['type'] ?? 'text';
          if ($isEdit && !empty($f['create_only']) && $type === 'section') { continue; }
          if ($isEdit && !empty($f['create_only']) && !empty($f['virtual'])) { continue; }
          if (!$isEdit && !empty($f['edit_only'])) { continue; }
          $id = 'f_' . $name;
          $val = $value($name, $f);
          $lockedOnEdit = $isEdit && !empty($f['create_only']);
          $disabled = $readonly || $lockedOnEdit;
          $span = ($f['col'] ?? 1) === 2 || in_array($type, ['textarea', 'multiselect'], true) ? 'sm:col-span-2' : '';
          $req = !empty($f['required']) && !is_callable($f['required']);
      ?>
        <?php if ($type === 'section'): ?>
          <div class="pt-3 sm:col-span-2"><div class="section-title border-b border-slate-100 pb-1.5"><?= e($f['label']) ?></div></div>
        <?php elseif ($type === 'checkbox'): ?>
          <div class="<?= $span ?> flex items-center pt-1">
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
              <input type="hidden" name="<?= e($name) ?>" value="0">
              <input type="checkbox" class="check" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1" <?= (int) $val === 1 ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
              <?= e($f['label']) ?>
            </label>
          </div>
        <?php else: ?>
          <div class="<?= $span ?>" data-field="<?= e($name) ?>">
            <label class="label" for="<?= e($id) ?>"><?= e($f['label']) ?><?= $req ? ' <span class="text-rose-500">*</span>' : '' ?></label>
            <?php if ($type === 'textarea'): ?>
              <textarea class="input" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="3" maxlength="<?= (int) ($f['max'] ?? 1000) ?>" <?= $disabled ? 'disabled' : '' ?>><?= e($val) ?></textarea>
            <?php elseif ($type === 'select' || $type === 'lookup'): $opts = $r->fieldOptions($f); ?>
              <select class="input" id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $disabled ? 'disabled' : '' ?> <?= $req ? 'required' : '' ?> <?= count($opts) > 12 ? 'data-searchable' : '' ?>>
                <?php if ($type === 'lookup' || !$req): ?><?php if (!array_key_exists('', $opts)): ?><option value="">— Select —</option><?php endif; ?><?php endif; ?>
                <?php foreach ($opts as $v => $l): ?><option value="<?= e($v) ?>" <?= (string) $v === (string) $val ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
              </select>
            <?php elseif ($type === 'multiselect'): $opts = $r->fieldOptions($f); $sel = array_map('strval', (array) ($val ?? [])); if (!$isEdit && !$sel) { $sel = [(string) auth()->companyId()]; } ?>
              <div class="grid gap-1.5 rounded-lg border border-slate-200 p-2 sm:grid-cols-2">
                <?php foreach ($opts as $v => $l): ?>
                  <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-slate-50">
                    <input type="checkbox" class="check" name="<?= e($name) ?>[]" value="<?= e($v) ?>" <?= in_array((string) $v, $sel, true) ? 'checked' : '' ?> <?= $disabled || (int) $v === auth()->companyId() ? 'disabled' : '' ?>>
                    <?php if ((int) $v === auth()->companyId()): ?><input type="hidden" name="<?= e($name) ?>[]" value="<?= e($v) ?>"><?php endif; ?>
                    <span class="truncate"><?= e($l) ?></span><?php if ((int) $v === auth()->companyId()): ?><span class="badge badge-cyan ml-auto">Current</span><?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php else:
                $htmlType = match ($type) { 'email' => 'email', 'number' => 'number', 'decimal' => 'text', 'date' => 'date', 'password' => 'password', default => 'text' };
                $display = $type === 'decimal' && $val !== null && $val !== '' ? rtrim(rtrim(number_format((float) $val, 4, '.', ''), '0'), '.') : $val;
            ?>
              <input class="input <?= in_array($type, ['decimal', 'number'], true) ? 'text-right tabular-nums' : '' ?> <?= !empty($f['uppercase']) ? 'uppercase' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= $htmlType ?>"
                     value="<?= $type === 'password' ? '' : e($display) ?>" <?= isset($f['max']) ? 'maxlength="' . (int) $f['max'] . '"' : '' ?>
                     <?= isset($f['placeholder']) ? 'placeholder="' . e($f['placeholder']) . '"' : '' ?> <?= $type === 'decimal' ? 'inputmode="decimal" data-decimal' : '' ?>
                     <?= $type === 'password' ? 'autocomplete="new-password"' : '' ?> <?= $disabled ? 'disabled' : '' ?> <?= $req ? 'required' : '' ?>>
            <?php endif; ?>
            <?php if (!empty($f['help'])): ?><p class="help"><?= e($f['help']) ?></p><?php endif; ?>
            <p class="field-error hidden" data-error-for="<?= e($name) ?>"></p>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php if ($isEdit && (isset($row['created_at']) || isset($row['updated_at']))): ?>
      <p class="mt-6 text-xs text-slate-400">Created <?= e(local_dt($row['created_at'] ?? null)) ?><?= !empty($row['updated_at']) ? ' · Updated ' . e(local_dt($row['updated_at'])) : '' ?></p>
    <?php endif; ?>
  </div>

  <div class="flex items-center gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
    <span class="hidden text-xs text-slate-500 sm:inline"><span class="kbd">Enter</span> next field · <span class="kbd">Ctrl S</span> save · <span class="kbd">Esc</span> close</span>
    <div class="ml-auto flex gap-2">
      <button type="button" class="btn" data-drawer-close>Cancel</button>
      <?php if (!$readonly): ?>
        <?php if (!$isEdit): ?><button type="submit" class="btn" name="_after" value="new" data-save-new>Save & New</button><?php endif; ?>
        <button type="submit" class="btn btn-primary" data-save-btn><?= icon('check', 'size-4') ?>Save</button>
      <?php endif; ?>
    </div>
  </div>
</form>
