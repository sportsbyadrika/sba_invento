<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\DocumentService;
use App\Services\Shop;
use App\Services\ShopAuth;
use App\Services\TaxRules;

/** Public online shop: /store/{shop}/... */
final class StoreController
{
    private array $store;
    private int $cid;
    private ?array $customer = null;

    private function boot(string $shop): void
    {
        $store = Shop::store($shop) ?? throw new HttpException(404, 'This store does not exist or is closed.');
        $this->store = $store;
        $this->cid = (int) $store['company_id'];
        Auth::instance()->useCompany($this->cid);
        $this->customer = ShopAuth::customer($this->cid);
    }

    private function base(string $path = ''): string
    {
        return '/store/' . $this->store['slug'] . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    private function render(string $view, array $data = []): string
    {
        return View::render('store/' . $view, $data + [
            'store' => $this->store, 'customer' => $this->customer, 'sbase' => $this->base(),
            'cartCount' => array_sum(Shop::cart($this->cid)),
            'categories' => Database::all('SELECT c.id, c.name, c.parent_id, COUNT(i.id) n FROM item_categories c JOIN items i ON i.category_id = c.id AND i.publish_online = 1 AND i.is_active = 1 WHERE c.company_id = ? AND c.is_active = 1 GROUP BY c.id ORDER BY c.name', [$this->cid]),
        ], 'store');
    }

    private function requireCustomer(): array
    {
        if (!$this->customer) {
            Session::set('store_intended', $_SERVER['REQUEST_URI'] ?? '');
            Session::flash('info', 'Please sign in to continue.');
            redirect($this->base('login'));
        }
        return $this->customer;
    }

    /** Where to go after sign-in (only paths inside this store) */
    private function intended(): string
    {
        $to = (string) Session::get('store_intended', '');
        Session::forget('store_intended');
        $prefix = app_prefix();
        if ($to !== '' && str_starts_with($to, $prefix . $this->base())) {
            return substr($to, strlen($prefix));
        }
        return $this->base();
    }

    private function back(string $fallback, ValidationException $v): never
    {
        Session::flash('error', $v->getMessage());
        Session::set('_errors', $v->errors);
        Session::set('_old', array_diff_key($_POST, array_flip(['password', 'password_confirmation', '_token'])));
        redirect($fallback);
    }

    // ------------------------------------------------------------------ catalogue

    public function index(string $shop): string
    {
        $this->boot($shop);
        $all = Shop::catalogue($this->cid);
        $q = trim((string) ($_GET['q'] ?? ''));
        $cat = (int) ($_GET['category'] ?? 0);
        $brands = array_filter((array) ($_GET['brand'] ?? []), 'is_string');
        $min = is_numeric($_GET['min'] ?? null) ? (float) $_GET['min'] : null;
        $max = is_numeric($_GET['max'] ?? null) ? (float) $_GET['max'] : null;
        $rating = (int) ($_GET['rating'] ?? 0);
        $inStock = !empty($_GET['in_stock']);
        $offers = !empty($_GET['offers']);
        $attrSel = array_map(static fn ($v) => array_filter((array) $v, 'is_string'), (array) ($_GET['attr'] ?? []));
        $sort = (string) ($_GET['sort'] ?? 'relevance');

        // categories include their sub-categories
        $catIds = [];
        if ($cat) {
            $children = [];
            foreach (Database::all('SELECT id, parent_id FROM item_categories WHERE company_id = ?', [$this->cid]) as $c) {
                $children[(int) $c['parent_id']][] = (int) $c['id'];
            }
            $stack = [$cat];
            while ($stack) {
                $x = array_pop($stack);
                $catIds[] = $x;
                array_push($stack, ...($children[$x] ?? []));
            }
        }
        // $skip: 'brand' or an attribute name - facet counts ignore their own filter
        $match = static function (array $it, ?string $skip = null) use ($q, $catIds, $brands, $min, $max, $rating, $inStock, $offers, $attrSel) {
            if ($q !== '' && !str_contains(mb_strtolower($it['name'] . ' ' . $it['sku'] . ' ' . $it['brand'] . ' ' . $it['category'] . ' ' . $it['short_description']), mb_strtolower($q))) {
                return false;
            }
            if ($catIds && !in_array((int) $it['category_id'], $catIds, true)) {
                return false;
            }
            if ($skip !== 'brand' && $brands && !in_array((string) $it['brand'], $brands, true)) {
                return false;
            }
            if (($min !== null && $it['price'] < $min) || ($max !== null && $it['price'] > $max)) {
                return false;
            }
            if ($rating && (float) $it['rating'] < $rating) {
                return false;
            }
            if (($inStock && !$it['in_stock']) || ($offers && !$it['mrp'])) {
                return false;
            }
            foreach ($attrSel as $name => $vals) {
                if ($vals && $name !== $skip && !in_array($it['attrs'][$name] ?? null, $vals, true)) {
                    return false;
                }
            }
            return true;
        };
        $items = array_filter($all, static fn ($it) => $match($it));
        // facets (counts reflect the other active filters)
        $brandFacet = $attrFacet = [];
        foreach ($all as $it) {
            if ($it['brand'] && $match($it, 'brand')) {
                $brandFacet[$it['brand']] = ($brandFacet[$it['brand']] ?? 0) + 1;
            }
            foreach ($it['attrs'] as $n => $v) {
                if ($match($it, $n)) {
                    $attrFacet[$n][$v] = ($attrFacet[$n][$v] ?? 0) + 1;
                }
            }
        }
        ksort($brandFacet);
        ksort($attrFacet);
        $attrFacet = array_slice($attrFacet, 0, 6, true);
        usort($items, match ($sort) {
            'price_asc' => static fn ($a, $b) => $a['price'] <=> $b['price'],
            'price_desc' => static fn ($a, $b) => $b['price'] <=> $a['price'],
            'newest' => static fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']),
            'rating' => static fn ($a, $b) => (float) $b['rating'] <=> (float) $a['rating'],
            default => static fn ($a, $b) => [$b['in_stock'], $a['name']] <=> [$a['in_stock'], $b['name']],
        });
        $per = 24;
        $total = count($items);
        $page = max(1, min((int) ($_GET['page'] ?? 1), (int) max(1, ceil($total / $per))));
        $prices = array_column($all, 'price');
        return $this->render('index', [
            'title' => $this->store['store_name'],
            'items' => array_slice($items, ($page - 1) * $per, $per), 'total' => $total, 'page' => $page, 'pages' => (int) max(1, ceil($total / $per)),
            'f' => compact('q', 'cat', 'brands', 'min', 'max', 'rating', 'inStock', 'offers', 'attrSel', 'sort'),
            'brandFacet' => $brandFacet, 'attrFacet' => $attrFacet, 'priceRange' => $prices ? [floor(min($prices)), ceil(max($prices))] : [0, 0],
        ]);
    }

    public function product(string $shop, string $item): string
    {
        $this->boot($shop);
        $id = (int) $item;
        $it = Shop::catalogue($this->cid, [$id])[$id] ?? throw new HttpException(404, 'This product is not available.');
        $images = Database::all('SELECT * FROM item_images WHERE item_id = ? ORDER BY is_main DESC, sort_order, id', [$id]);
        $reviews = Database::all("SELECT r.*, c.name FROM shop_reviews r JOIN shop_customers c ON c.id = r.customer_id WHERE r.item_id = ? AND r.status = 'approved' ORDER BY r.created_at DESC LIMIT 50", [$id]);
        $dist = array_fill(1, 5, 0);
        foreach ($reviews as $r) {
            $dist[(int) $r['rating']]++;
        }
        $related = array_slice(array_filter(Shop::catalogue($this->cid), static fn ($x) => (int) $x['id'] !== $id && $x['category_id'] === $it['category_id']), 0, 4);
        return $this->render('product', ['title' => $it['name'], 'it' => $it, 'images' => $images, 'reviews' => $reviews, 'dist' => $dist, 'related' => $related]);
    }

    // ------------------------------------------------------------------ cart

    public function cartAdd(string $shop): never
    {
        $this->boot($shop);
        $id = (int) ($_POST['item_id'] ?? 0);
        $qty = max(0.0, (float) ($_POST['qty'] ?? 1));
        $it = Shop::catalogue($this->cid, [$id])[$id] ?? null;
        $back = $_POST['back'] ?? '';
        if (!$it) {
            Session::flash('error', 'This product is not available.');
            redirect($this->base());
        }
        $newQty = (float) (Shop::cart($this->cid)[$id] ?? 0) + max($qty, (float) $it['min_order_qty']);
        if ($it['max_order_qty'] !== null) {
            $newQty = min($newQty, (float) $it['max_order_qty']);
        }
        if ($it['available'] !== null && $newQty > $it['available']) {
            Session::flash('error', $it['available'] > 0 ? 'Only ' . (float) $it['available'] . ' available.' : $it['name'] . ' is out of stock.');
            redirect($back === 'cart' ? $this->base('cart') : $this->base('item/' . $it['url_slug']));
        }
        Shop::cartSet($this->cid, $id, $newQty);
        Session::flash('success', $it['name'] . ' added to your cart.');
        redirect(($_POST['buy_now'] ?? '') === '1' ? $this->base('checkout') : $this->base('cart'));
    }

    public function cart(string $shop): string
    {
        $this->boot($shop);
        return $this->render('cart', ['title' => 'Your cart', 'view' => Shop::cartView($this->cid, $this->store)]);
    }

    public function cartUpdate(string $shop): never
    {
        $this->boot($shop);
        foreach ((array) ($_POST['qty'] ?? []) as $id => $q) {
            Shop::cartSet($this->cid, (int) $id, max(0.0, (float) $q));
        }
        if (isset($_POST['remove'])) {
            Shop::cartSet($this->cid, (int) $_POST['remove'], 0);
        }
        redirect($this->base('cart'));
    }

    // ------------------------------------------------------------------ checkout

    public function checkout(string $shop): string
    {
        $this->boot($shop);
        $c = $this->requireCustomer();
        $view = Shop::cartView($this->cid, $this->store);
        if (!$view['lines']) {
            redirect($this->base('cart'));
        }
        return $this->render('checkout', [
            'title' => 'Checkout', 'view' => $view,
            'addresses' => Database::all('SELECT * FROM shop_addresses WHERE customer_id = ? ORDER BY is_default DESC, id', [$c['id']]),
            'regions' => TaxRules::places($this->store['tax_regime']),
        ]);
    }

    public function placeOrder(string $shop): never
    {
        $this->boot($shop);
        $c = $this->requireCustomer();
        try {
            $addrId = (int) ($_POST['address_id'] ?? 0);
            if ($addrId === 0) {
                $addrId = $this->saveAddress($c, $_POST);
            }
            $address = Database::one('SELECT * FROM shop_addresses WHERE id = ? AND customer_id = ?', [$addrId, $c['id']]) ?? throw new ValidationException(['address_id' => 'Select a delivery address.']);
            $id = Shop::placeOrder($this->store, $c, $address, (string) ($_POST['payment_method'] ?? ''), trim((string) ($_POST['note'] ?? '')) ?: null);
        } catch (ValidationException $v) {
            $this->back($this->base('checkout'), $v);
        }
        $number = Database::value('SELECT number FROM shop_orders WHERE id = ?', [$id]);
        $o = Shop::order($this->cid, $id);
        Shop::notify($o, 'placed');
        Session::flash('success', "Thank you! Your order $number has been placed. Pay in cash when it is delivered.");
        redirect($this->base('account/orders/' . $number));
    }

    // ------------------------------------------------------------------ account

    public function loginForm(string $shop): string
    {
        $this->boot($shop);
        if ($this->customer) {
            redirect($this->base('account'));
        }
        return $this->render('login', ['title' => 'Sign in']);
    }

    public function login(string $shop): never
    {
        $this->boot($shop);
        try {
            ShopAuth::attempt($this->cid, (string) ($_POST['login'] ?? ''), (string) ($_POST['password'] ?? ''));
        } catch (ValidationException $v) {
            usleep(250000);
            $this->back($this->base('login'), $v);
        }
        redirect($this->intended());
    }

    public function registerForm(string $shop): string
    {
        $this->boot($shop);
        return $this->render('register', ['title' => 'Create account']);
    }

    public function register(string $shop): never
    {
        $this->boot($shop);
        try {
            ShopAuth::register($this->cid, $_POST);
        } catch (ValidationException $v) {
            $this->back($this->base('register'), $v);
        }
        Session::flash('success', 'Welcome! Your account has been created.');
        redirect($this->intended());
    }

    public function logout(string $shop): never
    {
        $this->boot($shop);
        ShopAuth::logout($this->cid);
        Session::flash('success', 'You have been signed out.');
        redirect($this->base());
    }

    public function forgotForm(string $shop): string
    {
        $this->boot($shop);
        return $this->render('forgot', ['title' => 'Forgot password']);
    }

    public function forgot(string $shop): never
    {
        $this->boot($shop);
        ShopAuth::sendReset($this->cid, (string) ($_POST['email'] ?? ''), $this->store['slug'], $this->store['store_name']);
        Session::flash('success', 'If an account exists for that e-mail, a reset link has been sent.');
        redirect($this->base('login'));
    }

    public function resetForm(string $shop, string $token): string
    {
        $this->boot($shop);
        if (!ShopAuth::resetCustomer($this->cid, $token)) {
            Session::flash('error', 'This reset link is invalid or has expired.');
            redirect($this->base('forgot'));
        }
        return $this->render('reset', ['title' => 'New password', 'token' => $token]);
    }

    public function reset(string $shop, string $token): never
    {
        $this->boot($shop);
        $c = ShopAuth::resetCustomer($this->cid, $token);
        if (!$c) {
            Session::flash('error', 'This reset link is invalid or has expired.');
            redirect($this->base('forgot'));
        }
        try {
            ShopAuth::resetPassword($c, (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirmation'] ?? ''));
        } catch (ValidationException $v) {
            $this->back($this->base('reset/' . $token), $v);
        }
        Session::flash('success', 'Password changed. Please sign in.');
        redirect($this->base('login'));
    }

    public function account(string $shop): string
    {
        $this->boot($shop);
        $c = $this->requireCustomer();
        $orders = Database::all('SELECT o.*, (SELECT COUNT(*) FROM shop_order_lines l WHERE l.order_id = o.id) lines_count,
                                        (SELECT l.image FROM shop_order_lines l WHERE l.order_id = o.id ORDER BY l.id LIMIT 1) image
                                   FROM shop_orders o WHERE o.customer_id = ? ORDER BY o.id DESC', [$c['id']]);
        $bought = Database::all("SELECT l.item_id, l.name, l.sku, l.image, l.qty, l.unit_price, l.tax_amount, l.line_total, o.number, o.placed_at, o.status
                                   FROM shop_order_lines l JOIN shop_orders o ON o.id = l.order_id WHERE o.customer_id = ? AND o.status <> 'cancelled' ORDER BY o.id DESC, l.id LIMIT 100", [$c['id']]);
        return $this->render('account', ['title' => 'My orders', 'orders' => $orders, 'bought' => $bought, 'tab' => ($_GET['tab'] ?? '') === 'items' ? 'items' : 'orders']);
    }

    private function myOrder(string $number): array
    {
        $c = $this->requireCustomer();
        $id = (int) Database::value('SELECT id FROM shop_orders WHERE company_id = ? AND number = ? AND customer_id = ?', [$this->cid, $number, $c['id']]);
        return ($id ? Shop::order($this->cid, $id) : null) ?? throw new HttpException(404, 'Order not found.');
    }

    public function order(string $shop, string $number): string
    {
        $this->boot($shop);
        $o = $this->myOrder($number);
        $returnDays = [];
        foreach (Database::all('SELECT id, return_days FROM items WHERE id IN (' . implode(',', array_map(static fn ($l) => (int) $l['item_id'], $o['lines']) ?: [0]) . ')') as $r) {
            $returnDays[(int) $r['id']] = $r['return_days'];
        }
        return $this->render('order', [
            'title' => 'Order ' . $o['number'], 'o' => $o,
            'canCancel' => in_array($o['status'], ['placed', 'confirmed'], true),
            'canReturn' => Shop::returnWindowOpen($o, $this->store),
            'reviewable' => Shop::reviewable((int) $this->customer['id'], (int) $o['id']),
            'reasons' => Shop::RETURN_REASONS,
        ]);
    }

    public function cancel(string $shop, string $number): never
    {
        $this->boot($shop);
        $o = $this->myOrder($number);
        if (!in_array($o['status'], ['placed', 'confirmed'], true)) {
            Session::flash('error', 'This order is already being packed or shipped and can no longer be cancelled.');
            redirect($this->base('account/orders/' . $number));
        }
        Database::update('shop_orders', ['status' => 'cancelled', 'cancelled_at' => now_utc()], 'id = ?', [$o['id']]);
        Shop::event((int) $o['id'], 'cancelled', 'Cancelled by customer', 'customer', (int) $this->customer['id']);
        Session::flash('success', "Order $number has been cancelled.");
        redirect($this->base('account/orders/' . $number));
    }

    public function returnRequest(string $shop, string $number): never
    {
        $this->boot($shop);
        $o = $this->myOrder($number);
        try {
            Shop::requestReturn($this->store, $this->customer, $o, (array) ($_POST['qty'] ?? []), (string) ($_POST['reason'] ?? ''), trim((string) ($_POST['details'] ?? '')));
        } catch (ValidationException $v) {
            $this->back($this->base('account/orders/' . $number) . '#return', $v);
        }
        Session::flash('success', 'Your return request has been sent. We will contact you to arrange the pick-up.');
        redirect($this->base('account/orders/' . $number));
    }

    public function review(string $shop, string $number): never
    {
        $this->boot($shop);
        $o = $this->myOrder($number);
        $item = (int) ($_POST['item_id'] ?? 0);
        $rating = (int) ($_POST['rating'] ?? 0);
        if (!in_array($item, Shop::reviewable((int) $this->customer['id'], (int) $o['id']), true) || $rating < 1 || $rating > 5) {
            Session::flash('error', 'Choose a star rating for an item you received.');
            redirect($this->base('account/orders/' . $number));
        }
        Database::insert('shop_reviews', ['company_id' => $this->cid, 'item_id' => $item, 'customer_id' => $this->customer['id'], 'order_id' => $o['id'], 'rating' => $rating,
            'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 120) ?: null, 'body' => mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 2000) ?: null, 'created_at' => now_utc()]);
        Session::flash('success', 'Thank you for your review! It will appear after a quick check.');
        redirect($this->base('account/orders/' . $number));
    }

    public function invoice(string $shop, string $number): string
    {
        $this->boot($shop);
        $o = $this->myOrder($number);
        if (!$o['invoice_id']) {
            throw new HttpException(404, 'The invoice is generated when your order is shipped.');
        }
        $doc = DocumentService::find((int) $o['invoice_id'], 'invoice');
        return View::render('documents/print', ['title' => 'Invoice ' . $doc['number'], 'doc' => $doc, 'cfg' => DocumentService::TYPES['invoice'], 'taxSummary' => DocumentService::taxSummary($doc), 'company' => Auth::instance()->company()], null);
    }

    // ------------------------------------------------------------------ addresses

    public function addresses(string $shop): string
    {
        $this->boot($shop);
        $c = $this->requireCustomer();
        return $this->render('addresses', ['title' => 'My addresses', 'addresses' => Database::all('SELECT * FROM shop_addresses WHERE customer_id = ? ORDER BY is_default DESC, id', [$c['id']]), 'regions' => TaxRules::places($this->store['tax_regime'])]);
    }

    private function saveAddress(array $c, array $in, ?int $id = null): int
    {
        $e = [];
        $f = static fn ($k, $len) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $len);
        $d = ['label' => $f('label', 40) ?: 'Home', 'name' => $f('ship_name', 120), 'phone' => $f('ship_phone', 30), 'line1' => $f('line1', 200), 'line2' => $f('line2', 200) ?: null,
            'city' => $f('city', 80), 'region' => $f('region', 80) ?: null, 'postal_code' => $f('postal_code', 20) ?: null, 'country' => $this->store['country_code'] ?: 'AE'];
        foreach (['name' => 'Enter the receiver name.', 'phone' => 'Enter a contact number.', 'line1' => 'Enter the address.', 'city' => 'Enter the city.'] as $k => $msg) {
            if ($d[$k] === '') {
                $e[$k === 'name' ? 'ship_name' : ($k === 'phone' ? 'ship_phone' : $k)] = $msg;
            }
        }
        if ($e) {
            throw new ValidationException($e, 'Please complete the delivery address.');
        }
        $default = !empty($in['is_default']) || !Database::value('SELECT COUNT(*) FROM shop_addresses WHERE customer_id = ?', [$c['id']]);
        if ($default) {
            Database::query('UPDATE shop_addresses SET is_default = 0 WHERE customer_id = ?', [$c['id']]);
        }
        $d['is_default'] = $default ? 1 : 0;
        if ($id) {
            Database::update('shop_addresses', $d, 'id = ? AND customer_id = ?', [$id, $c['id']]);
            return $id;
        }
        return Database::insert('shop_addresses', $d + ['customer_id' => $c['id']]);
    }

    public function addressSave(string $shop): never
    {
        $this->boot($shop);
        $c = $this->requireCustomer();
        try {
            $this->saveAddress($c, $_POST, ((int) ($_POST['id'] ?? 0)) ?: null);
        } catch (ValidationException $v) {
            $this->back($this->base('account/addresses'), $v);
        }
        Session::flash('success', 'Address saved.');
        redirect($this->base('account/addresses'));
    }

    public function addressDelete(string $shop, string $id): never
    {
        $this->boot($shop);
        $c = $this->requireCustomer();
        Database::query('DELETE FROM shop_addresses WHERE id = ? AND customer_id = ?', [(int) $id, $c['id']]);
        Session::flash('success', 'Address removed.');
        redirect($this->base('account/addresses'));
    }

    public function page(string $shop, string $page): string
    {
        $this->boot($shop);
        $map = ['terms' => ['Terms & conditions', 'terms'], 'privacy' => ['Privacy policy', 'privacy'], 'returns' => ['Returns & refunds', 'returns_policy'], 'shipping' => ['Shipping & delivery', 'shipping_policy']];
        [$title, $field] = $map[$page];
        return $this->render('page', ['title' => $title, 'body' => (string) ($this->store[$field] ?? '')]);
    }
}
