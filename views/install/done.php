<h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Installation complete</h1>
<div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-800">The tables and the super admin <span class="font-mono font-semibold"><?= e($username) ?></span> were created. The installer is now locked.</div>
<?php if ($demo): ?>
  <p class="mt-4 text-sm text-slate-600">Demo users: <span class="font-mono">admin / Admin@12345</span>, <span class="font-mono">accountant / Account@12345</span>, <span class="font-mono">sales / Sales@12345</span>. Change or disable them before real use.</p>
<?php endif; ?>
<div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800">Now remove the <span class="font-mono">install_key</span> value from <span class="font-mono">config/config.php</span>.</div>
<a href="<?= e(url('/login')) ?>" class="btn btn-primary mt-6 w-full py-2"><?= icon('log-in', 'size-4') ?>Go to sign in</a>
