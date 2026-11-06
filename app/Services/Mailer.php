<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Minimal mailer: 'log' (writes .eml files to storage/mail - for development),
 * 'mail' (PHP mail()) or 'smtp' (AUTH LOGIN + STARTTLS/SSL).
 * For high volume use a transactional provider (SES, Postmark, SendGrid) via its API.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html, ?string $text = null): bool
    {
        $fromEmail = (string) config('mail.from_email');
        $fromName = (string) config('mail.from_name');
        $text ??= trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));
        $boundary = 'b' . bin2hex(random_bytes(8));
        $headers = [
            'From' => self::encode($fromName) . " <$fromEmail>",
            'To' => $to,
            'Subject' => self::encode($subject),
            'Date' => gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost') . '>',
            'MIME-Version' => '1.0',
            'Content-Type' => "multipart/alternative; boundary=\"$boundary\"",
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--\r\n";

        try {
            return match (config('mail.driver', 'log')) {
                'smtp' => self::smtp($fromEmail, $to, $headers, $body),
                'mail' => mail($to, $headers['Subject'], $body, self::headerString(array_diff_key($headers, ['To' => 1, 'Subject' => 1]))),
                default => self::log($headers, $body),
            };
        } catch (\Throwable $e) {
            error_log('Mail failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function encode(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function headerString(array $headers): string
    {
        $out = '';
        foreach ($headers as $k => $v) {
            $out .= "$k: $v\r\n";
        }
        return $out;
    }

    private static function log(array $headers, string $body): bool
    {
        $file = BASE_PATH . '/storage/mail/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml';
        return file_put_contents($file, self::headerString($headers) . "\r\n" . $body) !== false;
    }

    private static function smtp(string $from, string $to, array $headers, string $body): bool
    {
        $c = config('mail.smtp');
        $host = ($c['encryption'] === 'ssl' ? 'ssl://' : '') . $c['host'];
        $fp = stream_socket_client("$host:{$c['port']}", $errno, $errstr, (int) $c['timeout']);
        if (!$fp) {
            throw new \RuntimeException("SMTP connect failed: $errstr");
        }
        stream_set_timeout($fp, (int) $c['timeout']);
        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = static function (string $command, array $expect) use ($fp, $read): string {
            fwrite($fp, $command . "\r\n");
            $resp = $read();
            if (!in_array((int) substr($resp, 0, 3), $expect, true)) {
                throw new \RuntimeException('SMTP error: ' . trim($resp));
            }
            return $resp;
        };
        $read();
        $ehloHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $cmd("EHLO $ehloHost", [250]);
        if ($c['encryption'] === 'tls') {
            $cmd('STARTTLS', [220]);
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            $cmd("EHLO $ehloHost", [250]);
        }
        if ($c['username'] !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($c['username']), [334]);
            $cmd(base64_encode($c['password']), [235]);
        }
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $data = self::headerString($headers) . "\r\n" . preg_replace('/^\./m', '..', $body);
        $cmd($data . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }
}
