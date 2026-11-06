<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\View;

final class PasswordReset
{
    /** Create a single-use token (only its SHA-256 hash is stored) and e-mail the link. */
    public static function send(array $user): bool
    {
        $token = bin2hex(random_bytes(32));
        Database::query('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now_utc(), $user['id']]);
        Database::insert('password_resets', [
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 60 * (int) config('security.reset_token_minutes', 60)),
            'ip_address' => client_ip(),
            'created_at' => now_utc(),
        ]);
        $link = rtrim((string) config('app.url'), '/') . url('/reset-password/' . $token);
        $html = View::partial('emails/reset-password', ['user' => $user, 'link' => $link, 'minutes' => (int) config('security.reset_token_minutes', 60)]);
        return Mailer::send($user['email'], 'Reset your ' . config('app.name') . ' password', $html);
    }
}
