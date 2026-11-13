<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;

/**
 * Online shop logo and browser-tab icon. Images are validated and re-encoded with GD as PNG
 * (transparency kept). Without a separate icon, the icon is made from the logo (fitted in a square).
 */
final class ShopBranding
{
    private static function dir(int $cid): string
    {
        $d = BASE_PATH . "/public/uploads/c$cid/shop";
        if (!is_dir($d) && !mkdir($d, 0755, true) && !is_dir($d)) {
            throw new ValidationException([], 'The upload folder is not writable.');
        }
        return $d;
    }

    private static function load(array $file): \GdImage
    {
        if (in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new ValidationException([], 'The image is larger than the server allows (' . ini_get('upload_max_filesize') . ').');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || $file['size'] > 8 * 1024 * 1024) {
            throw new ValidationException([], 'The image could not be uploaded (max 8 MB).');
        }
        $mime = (@getimagesize($file['tmp_name']))['mime'] ?? '';
        $im = match ($mime) {
            'image/png' => @imagecreatefrompng($file['tmp_name']),
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
            default => false,
        };
        if (!$im) {
            throw new ValidationException([], 'Only PNG, JPEG or WebP images are allowed.');
        }
        imagepalettetotruecolor($im);
        imagesavealpha($im, true);
        return $im;
    }

    private static function canvas(int $w, int $h): \GdImage
    {
        $c = imagecreatetruecolor($w, $h);
        imagealphablending($c, false);
        imagefill($c, 0, 0, imagecolorallocatealpha($c, 0, 0, 0, 127));
        imagesavealpha($c, true);
        imagealphablending($c, true);
        return $c;
    }

    /** Fit inside max w x h (never enlarged), or into a centred square of $square px */
    private static function resized(\GdImage $src, int $maxW, int $maxH, ?int $square = null): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $box = $square ?? 0;
        $scale = $square ? min($square / $w, $square / $h) : min(1, $maxW / $w, $maxH / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = self::canvas($square ? $box : $nw, $square ? $box : $nh);
        imagecopyresampled($dst, $src, $square ? (int) (($box - $nw) / 2) : 0, $square ? (int) (($box - $nh) / 2) : 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    private static function write(\GdImage $im, int $cid, string $name): string
    {
        $file = $name . '-' . bin2hex(random_bytes(4)) . '.png'; // new name each time: browsers re-fetch the new icon
        imagepng($im, self::dir($cid) . '/' . $file, 6);
        imagedestroy($im);
        return "c$cid/shop/$file";
    }

    private static function unlinkFiles(?array $paths): void
    {
        foreach (array_filter($paths ?? []) as $p) {
            $f = BASE_PATH . '/public/uploads/' . $p;
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    private static function icons(\GdImage $src, int $cid): array
    {
        return ['icon' => self::write(self::resized($src, 0, 0, 192), $cid, 'icon192'), 'icon_small' => self::write(self::resized($src, 0, 0, 32), $cid, 'icon32')];
    }

    public static function upload(int $cid, string $kind, array $file): void
    {
        $s = Shop::settings($cid);
        if (!Database::value('SELECT company_id FROM shop_settings WHERE company_id = ?', [$cid])) {
            throw new ValidationException([], 'Save the shop settings once before adding a logo.');
        }
        $src = self::load($file);
        if ($kind === 'logo') {
            $data = ['logo' => self::write(self::resized($src, 800, 240), $cid, 'logo')];
            $old = [$s['logo'] ?? null];
            if (empty($s['icon_custom'])) { // icon follows the logo until a separate icon is uploaded
                $data += self::icons($src, $cid);
                $old[] = $s['icon'] ?? null;
                $old[] = $s['icon_small'] ?? null;
            }
        } else {
            $data = self::icons($src, $cid) + ['icon_custom' => 1];
            $old = [$s['icon'] ?? null, $s['icon_small'] ?? null];
        }
        imagedestroy($src);
        Database::update('shop_settings', $data, 'company_id = ?', [$cid]);
        self::unlinkFiles($old);
    }

    public static function remove(int $cid, string $kind): void
    {
        $s = Shop::settings($cid);
        if ($kind === 'logo') {
            $data = ['logo' => null];
            $old = [$s['logo'] ?? null];
            if (empty($s['icon_custom'])) {
                $data += ['icon' => null, 'icon_small' => null];
                $old[] = $s['icon'] ?? null;
                $old[] = $s['icon_small'] ?? null;
            }
        } else {
            $data = ['icon' => null, 'icon_small' => null, 'icon_custom' => 0];
            $old = [$s['icon'] ?? null, $s['icon_small'] ?? null];
            if (!empty($s['logo']) && is_file(BASE_PATH . '/public/uploads/' . $s['logo'])) { // fall back to an icon made from the logo
                $src = imagecreatefrompng(BASE_PATH . '/public/uploads/' . $s['logo']);
                imagesavealpha($src, true);
                $data = self::icons($src, $cid) + ['icon_custom' => 0];
                imagedestroy($src);
            }
        }
        Database::update('shop_settings', $data, 'company_id = ?', [$cid]);
        self::unlinkFiles($old);
    }
}
