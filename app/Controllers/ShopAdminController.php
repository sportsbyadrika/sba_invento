<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Audit;
use App\Services\ItemImages;
use App\Services\Shop;
use App\Services\Stock;

/** Staff screens for the online shop: order processing, returns, settings, product images. */
final class ShopAdminController
{
    private function cid(string $action = 'view', string $module = 'shop'): int
    {
        if (!Auth::instance()->can($module, $action)) {
            throw new HttpException(403);
        }
        return (int) Auth::instance()->companyId();
    }

    private function cashAccounts(int $cid): array
    {
        return Database::all("SELECT id, code, name, sub_type FROM accounts WHERE company_id = ? AND is_group = 0 AND is_active = 1 AND sub_type IN ('cash','bank') ORDER BY sub_type = 'cash' DESC, code", [$cid]);
    }

    public function order(string $id): string
    {
        $cid = $this->cid();
        $o = Shop::order($cid, (int) $id) ?? throw new HttpException(404, 'Order not found.');
        $ids = array_map(static fn ($l) => (int) $l['item_id'], $o['lines']);
        $o['invoice'] = $o['invoice_id'] ? Database::one('SELECT id, number, status, total, amount_paid FROM documents WHERE id = ?', [$o['invoice_id']]) : null;
        return View::render('shop/order', [
            'title' => 'Order ' . $o['number'], 'activeModule' => 'shop', 'o' => $o,
            'onHand' => Stock::onHand($cid, $ids), 'cashAccounts' => $this->cashAccounts($cid),
            'store' => Shop::settings($cid), 'canEdit' => Auth::instance()->can('shop', 'edit'),
        ]);
    }

    public function advance(string $id): never
    {
        $this->cid('edit');
        try {
            Shop::advance((int) $id, (string) ($_POST['to'] ?? ''), $_POST);
        } catch (ValidationException $v) {
            json_out(['ok' => false, 'message' => $v->getMessage(), 'errors' => $v->errors], 422);
        }
        $to = (string) ($_POST['to'] ?? '');
        json_out(['ok' => true, 'message' => 'Order updated: ' . (Shop::STAGES[$to][0] ?? $to) . ($to === 'shipped' ? ' · tax invoice posted and stock issued.' : '.'), 'redirect' => url('/shop/orders/' . (int) $id)]);
    }

    public function packingSlip(string $id): string
    {
        $cid = $this->cid();
        $o = Shop::order($cid, (int) $id) ?? throw new HttpException(404);
        return View::render('shop/packing-slip', ['title' => 'Packing slip ' . $o['number'], 'o' => $o, 'store' => Shop::settings($cid), 'company' => Auth::instance()->company()], null);
    }

    public function returnShow(string $id): string
    {
        $cid = $this->cid();
        $r = Database::one('SELECT r.*, o.number AS order_number, o.ship_name, o.ship_phone, o.ship_address, o.ship_city, o.payment_method, c.name AS customer_name, c.email, c.phone_code, c.phone
                              FROM shop_returns r JOIN shop_orders o ON o.id = r.order_id JOIN shop_customers c ON c.id = r.customer_id WHERE r.id = ? AND r.company_id = ?', [(int) $id, $cid])
            ?? throw new HttpException(404, 'Return not found.');
        $r['lines'] = Database::all('SELECT rl.*, ol.name, ol.sku, ol.image, ol.qty AS ordered FROM shop_return_lines rl JOIN shop_order_lines ol ON ol.id = rl.order_line_id WHERE rl.return_id = ?', [$r['id']]);
        $r['credit_note'] = $r['credit_note_id'] ? Database::one('SELECT id, number, total, amount_paid, status FROM documents WHERE id = ?', [$r['credit_note_id']]) : null;
        return View::render('shop/return', ['title' => 'Return ' . $r['number'], 'activeModule' => 'shop', 'r' => $r, 'cashAccounts' => $this->cashAccounts($cid), 'canEdit' => Auth::instance()->can('shop', 'edit')]);
    }

    public function returnProcess(string $id): never
    {
        $this->cid('edit');
        try {
            Shop::processReturn((int) $id, (string) ($_POST['op'] ?? ''), $_POST);
        } catch (ValidationException $v) {
            json_out(['ok' => false, 'message' => $v->getMessage(), 'errors' => $v->errors], 422);
        }
        json_out(['ok' => true, 'message' => 'Return updated.', 'redirect' => url('/shop/returns/' . (int) $id)]);
    }

    public function settings(): string
    {
        $cid = (int) Auth::instance()->companyId();
        return View::render('shop/settings', ['title' => 'Shop Settings', 'activeModule' => 'shop', 's' => Shop::settings($cid),
            'published' => (int) Database::value('SELECT COUNT(*) FROM items WHERE company_id = ? AND publish_online = 1 AND is_active = 1', [$cid])]);
    }

    public function saveSettings(): never
    {
        $cid = (int) Auth::instance()->companyId();
        $in = $_POST;
        $num = static fn ($k) => ($in[$k] ?? '') === '' ? null : (float) str_replace(',', '', (string) $in[$k]);
        $slug = strtolower(trim((string) ($in['slug'] ?? '')));
        $e = [];
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,59}$/', $slug)) {
            $e['slug'] = '2-60 lower-case letters, digits or dashes.';
        } elseif (Database::value('SELECT company_id FROM shop_settings WHERE slug = ? AND company_id <> ?', [$slug, $cid])) {
            $e['slug'] = 'This web address is already used by another store.';
        }
        if (trim((string) ($in['store_name'] ?? '')) === '') {
            $e['store_name'] = 'Enter the store name.';
        }
        foreach (['delivery_fee', 'free_delivery_min', 'cod_max', 'cod_fee'] as $k) {
            if (($in[$k] ?? '') !== '' && (!is_numeric(str_replace(',', '', (string) $in[$k])) || (float) $in[$k] < 0)) {
                $e[$k] = 'Enter a positive amount.';
            }
        }
        if ($e) {
            json_out(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $e], 422);
        }
        $data = [
            'enabled' => !empty($in['enabled']) ? 1 : 0, 'slug' => $slug, 'store_name' => mb_substr(trim((string) $in['store_name']), 0, 120),
            'tagline' => mb_substr(trim((string) ($in['tagline'] ?? '')), 0, 200) ?: null,
            'contact_email' => mb_substr(trim((string) ($in['contact_email'] ?? '')), 0, 150) ?: null, 'contact_phone' => mb_substr(trim((string) ($in['contact_phone'] ?? '')), 0, 40) ?: null,
            'delivery_fee' => $num('delivery_fee') ?? 0, 'free_delivery_min' => $num('free_delivery_min'),
            'cod_enabled' => !empty($in['cod_enabled']) ? 1 : 0, 'cod_max' => $num('cod_max'), 'cod_fee' => $num('cod_fee') ?? 0,
            'return_days' => max(0, min(365, (int) ($in['return_days'] ?? 7))), 'low_stock_badge' => max(0, min(1000, (int) ($in['low_stock_badge'] ?? 5))),
        ];
        foreach (['terms', 'privacy', 'returns_policy', 'shipping_policy'] as $k) {
            $data[$k] = mb_substr(trim((string) ($in[$k] ?? '')), 0, 20000) ?: null;
        }
        if (Database::value('SELECT company_id FROM shop_settings WHERE company_id = ?', [$cid])) {
            Database::update('shop_settings', $data, 'company_id = ?', [$cid]);
        } else {
            Database::insert('shop_settings', $data + ['company_id' => $cid]);
        }
        Audit::log('shop_settings', $cid, 'update', null, $data);
        json_out(['ok' => true, 'message' => 'Shop settings saved.' . ($data['enabled'] ? ' Your store is live at /store/' . $slug : ' The store is offline.')]);
    }

    // ------------------------------------------------------------------ item images

    private function item(int $id, string $action = 'view'): array
    {
        $cid = $this->cid($action, 'inventory');
        return Database::one('SELECT * FROM items WHERE id = ? AND company_id = ?', [$id, $cid]) ?? throw new HttpException(404, 'Item not found.');
    }

    public function images(string $id): string
    {
        $item = $this->item((int) $id);
        $cid = (int) Auth::instance()->companyId();
        $store = Shop::settings($cid);
        return View::render('shop/images', ['title' => 'Images · ' . $item['name'], 'activeModule' => 'inventory', 'item' => $item, 'store' => $store,
            'images' => Database::all('SELECT * FROM item_images WHERE item_id = ? ORDER BY is_main DESC, sort_order, id', [$item['id']]),
            'live' => Shop::catalogue($cid, [(int) $item['id']])[(int) $item['id']] ?? null]);
    }

    public function uploadImages(string $id): never
    {
        $item = $this->item((int) $id, 'edit');
        $files = $_FILES['images'] ?? null;
        $n = 0;
        try {
            if (!$files || !is_array($files['name'])) {
                throw new ValidationException([], 'Choose one or more images.');
            }
            if ((int) Database::value('SELECT COUNT(*) FROM item_images WHERE item_id = ?', [$item['id']]) + count($files['name']) > 12) {
                throw new ValidationException([], 'An item can have up to 12 images.');
            }
            foreach (array_keys($files['name']) as $i) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                ItemImages::upload((int) $item['company_id'], (int) $item['id'], ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]], (string) ($_POST['alt'] ?? $item['name']));
                $n++;
            }
        } catch (ValidationException $v) {
            Session::flash('error', $v->getMessage());
            redirect('/inventory/items/' . $item['id'] . '/images');
        }
        Session::flash('success', $n ? "$n image(s) uploaded." : 'No image was selected.');
        redirect('/inventory/items/' . $item['id'] . '/images');
    }

    public function imageOp(string $id, string $img, string $op): never
    {
        $item = $this->item((int) $id, 'edit');
        if ($op === 'delete') {
            ItemImages::delete((int) $item['company_id'], (int) $img);
            Session::flash('success', 'Image deleted.');
        } else {
            Database::query('UPDATE item_images SET is_main = (id = ?) WHERE item_id = ?', [(int) $img, $item['id']]);
            Session::flash('success', 'Main image changed.');
        }
        redirect('/inventory/items/' . $item['id'] . '/images');
    }
}
