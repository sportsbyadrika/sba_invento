<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;

/**
 * Product images: validated (real JPEG/PNG/WebP, max 8 MB), re-encoded with GD (strips EXIF and any
 * embedded payload) to a 1200 px image + 400 px thumbnail, stored in public/uploads/c{company}/items/.
 */
final class ItemImages
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    public static function dir(int $cid): string
    {
        return BASE_PATH . "/public/uploads/c$cid/items";
    }

    public static function upload(int $cid, int $itemId, array $file, string $alt = ''): int
    {
        if (in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new ValidationException([], 'The image is larger than the server allows (' . ini_get('upload_max_filesize') . '). Crop it, or raise upload_max_filesize in cPanel → MultiPHP INI Editor.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new ValidationException([], 'The image could not be uploaded (' . ($file['name'] ?? 'file') . ').');
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new ValidationException([], 'Images must be smaller than 8 MB.');
        }
        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? '';
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/png' => @imagecreatefrompng($file['tmp_name']),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
            default => false,
        };
        if (!$src) {
            throw new ValidationException([], 'Only JPEG, PNG or WebP images are allowed.');
        }
        $dir = self::dir($cid);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ValidationException([], 'The upload folder is not writable.');
        }
        $name = bin2hex(random_bytes(10));
        $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
        self::save($src, 1200, "$dir/$name.$ext", $ext);
        self::save($src, 400, "$dir/{$name}_t.$ext", $ext);
        imagedestroy($src);
        $first = !Database::value('SELECT COUNT(*) FROM item_images WHERE item_id = ?', [$itemId]);
        return Database::insert('item_images', [
            'company_id' => $cid, 'item_id' => $itemId, 'file' => "c$cid/items/$name.$ext", 'thumb' => "c$cid/items/{$name}_t.$ext",
            'alt' => mb_substr($alt, 0, 150) ?: null, 'sort_order' => (int) Database::value('SELECT COALESCE(MAX(sort_order),0)+1 FROM item_images WHERE item_id = ?', [$itemId]),
            'is_main' => $first ? 1 : 0,
        ]);
    }

    private static function save(\GdImage $src, int $max, string $path, string $ext): void
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // flatten transparency on white
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ext === 'webp' ? imagewebp($dst, $path, 82) : imagejpeg($dst, $path, 85);
        imagedestroy($dst);
    }

    public static function delete(int $cid, int $id): void
    {
        $img = Database::one('SELECT * FROM item_images WHERE id = ? AND company_id = ?', [$id, $cid]);
        if (!$img) {
            return;
        }
        foreach ([$img['file'], $img['thumb']] as $f) {
            $p = BASE_PATH . '/public/uploads/' . $f;
            if (is_file($p)) {
                @unlink($p);
            }
        }
        Database::query('DELETE FROM item_images WHERE id = ?', [$id]);
        if ($img['is_main']) {
            Database::query('UPDATE item_images SET is_main = 1 WHERE item_id = ? ORDER BY sort_order, id LIMIT 1', [$img['item_id']]);
        }
    }

    /** Placeholder product image generated with GD (used for demo data) */
    public static function placeholder(int $cid, int $itemId, string $label, array $rgb): void
    {
        $dir = self::dir($cid);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
        $name = bin2hex(random_bytes(10));
        foreach ([[900, ''], [400, '_t']] as [$size, $suffix]) {
            $im = imagecreatetruecolor($size, $size);
            imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb));
            $light = imagecolorallocatealpha($im, 255, 255, 255, 80);
            imagefilledellipse($im, (int) ($size * 0.5), (int) ($size * 0.46), (int) ($size * 0.62), (int) ($size * 0.62), $light);
            $white = imagecolorallocate($im, 255, 255, 255);
            $font = 5;
            $text = strtoupper(mb_substr($label, 0, 18));
            $scale = (int) max(1, floor($size / 220));
            $tw = imagefontwidth($font) * strlen($text);
            $th = imagefontheight($font);
            $tmp = imagecreatetruecolor($tw, $th);
            imagefill($tmp, 0, 0, imagecolorallocate($tmp, ...$rgb));
            imagestring($tmp, $font, 0, 0, $text, imagecolorallocate($tmp, 255, 255, 255));
            imagecopyresized($im, $tmp, (int) (($size - $tw * $scale) / 2), (int) ($size * 0.82), 0, 0, $tw * $scale, $th * $scale, $tw, $th);
            imagedestroy($tmp);
            unset($white);
            $path = "$dir/$name$suffix.$ext";
            $ext === 'webp' ? imagewebp($im, $path, 80) : imagejpeg($im, $path, 85);
            imagedestroy($im);
        }
        Database::insert('item_images', ['company_id' => $cid, 'item_id' => $itemId, 'file' => "c$cid/items/$name.$ext", 'thumb' => "c$cid/items/{$name}_t.$ext", 'alt' => $label, 'sort_order' => 1, 'is_main' => 1]);
    }
}
