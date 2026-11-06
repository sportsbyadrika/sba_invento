<?php
$cfg = (require __DIR__ . '/../_login-logs.php')('me');
$cfg['module'] = 'dashboard'; // every signed-in user may see their own history
return $cfg;
