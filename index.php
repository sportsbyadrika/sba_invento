<?php
/*
 * This file only runs when the root .htaccess is NOT active (missing file, or rewrites disabled).
 * With a working .htaccess every request is routed into /public and this file is never reached.
 */
http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><meta charset="utf-8"><title>Setup incomplete</title>
<div style="font-family:system-ui,sans-serif;max-width:640px;margin:10vh auto;padding:0 16px;color:#0f172a">
<h1>Setup incomplete</h1>
<p>The web server is not using the <code>.htaccess</code> file in the site's root folder, so the application cannot start.</p>
<ol>
<li>In cPanel File Manager, open this site's folder, click <b>Settings</b> and tick <b>Show Hidden Files (dotfiles)</b>.</li>
<li>Make sure a file named <code>.htaccess</code> exists next to the <code>app</code> and <code>public</code> folders. If it is missing, deploy again or create it from the repository.</li>
<li>Or set this domain's <b>Document Root</b> to the <code>public</code> folder (cPanel &rarr; Domains).</li>
</ol>
</div>
