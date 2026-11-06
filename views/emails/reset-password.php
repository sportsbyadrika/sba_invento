<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:auto;color:#0f172a">
  <h2 style="margin:0 0 12px"><?= e(config('app.name')) ?> password reset</h2>
  <p>Hello <?= e($user['name']) ?>,</p>
  <p>We received a request to reset the password for username <strong><?= e($user['username']) ?></strong>. Click the button below to choose a new password. This also unlocks your account if it was locked.</p>
  <p style="margin:24px 0"><a href="<?= e($link) ?>" style="background:#0e7490;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:bold">Reset password</a></p>
  <p style="font-size:13px;color:#475569">The link expires in <?= (int) $minutes ?> minutes and can be used once. If you did not request this, you can ignore this e-mail — your password will not change.</p>
  <p style="font-size:12px;color:#94a3b8;word-break:break-all"><?= e($link) ?></p>
</div>
