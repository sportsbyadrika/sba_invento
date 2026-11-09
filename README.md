# SBA Invento — SaaS Accounting & Inventory

A multi-tenant, multi-company, multi-currency accounting and inventory application for small and mid-sized businesses (UAE VAT and India GST ready).

- **Stack:** PHP 8.2+ (no framework, PSR-4 autoload, PDO), MySQL 8 / MariaDB 10.6+, Tailwind CSS v4 (built with the Tailwind CLI), vanilla JavaScript, Lucide icons (self-hosted)
- **Clean URLs:** every page is served through `public/index.php`, so `.php` never appears in the address bar. `/login.php` is redirected to `/login`.
- **Design:** white base, top navigation bar, profile menu on the right with the company switcher and sign-out, a filter panel on the right like Tally, and keyboard-first data entry.

> The original prototype (`hallsons.htm`) is kept for reference. My review of it, the corrections and the recommendations are in [`docs/SUGGESTIONS.md`](docs/SUGGESTIONS.md).

---

## 1. Quick start (local)

```bash
# 1. dependencies for the CSS build (only needed when you change styles / icons)
npm install
npm run build            # builds public/assets/css/app.css and copies the used icons

# 2. configuration
cp config/config.example.php config/config.php   # then edit the db / app.url / mail settings

# 3. database (create an empty utf8mb4 database first)
mysql -uroot -e "CREATE DATABASE sba_invento CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php bin/install.php --super-user=superadmin --super-email=you@example.com --super-pass='Super@12345' --demo

# 4. run
php -S 127.0.0.1:8000 -t public public/index.php
```

Open http://127.0.0.1:8000 and sign in.

| Username     | Password         | Role                                                               |
|--------------|------------------|--------------------------------------------------------------------|
| `superadmin` | `Super@12345`    | Platform super admin (creates companies and their admins)          |
| `admin`      | `Admin@12345`    | Company admin of **Hallsons Trading LLC** (UAE) and **Hallsons India Pvt Ltd** |
| `accountant` | `Account@12345`  | Accountant (UAE company), viewer (India company)                   |
| `sales`      | `Sales@12345`    | Sales user (UAE company)                                           |

Change these passwords, or run the installer without `--demo`, before going live. `--fresh` drops all tables first and is for development only.

The compiled CSS (`public/assets/css/app.css`) and icons (`app/icons`) are committed, so a server only needs PHP and MySQL. Node is needed only to rebuild the styles.

## 2. Deploying

### Shared hosting without a terminal (cPanel)
1. Deploy with cPanel Git Version Control (`.cpanel.yml` copies the code to `/home/olympicd/federaerp.com`).
2. Create a MySQL database and user in **cPanel → MySQL Databases**, and give the user *All Privileges* on the database.
3. In File Manager, copy `config/config.example.php` to `config/config.php`. Fill in the `db` values, `app.url`, and a long random `app.install_key`.
4. Open `https://your-domain/install`, type the install key, and create the super admin. Tick *demo data* only for a test site. The installer creates every table and then locks itself (`storage/installed.lock`).
5. Set `install_key` back to `''` in `config/config.php`.

The root `.htaccess` routes everything into `/public`, blocks the source folders, and leaves `/.well-known/` open for AutoSSL. If cPanel's *MultiPHP Manager* adds a PHP handler block to `.htaccess`, copy that block into the repository's `.htaccess`, because each deploy overwrites the file.

### Other servers

**Apache:** point the document root at `/public`. `public/.htaccess` handles the clean URLs. If your shared host can't change the document root, the root `.htaccess` sends everything into `/public` and blocks the source folders.

**Nginx:**

```nginx
server {
    root /var/www/sba_invento/public;
    index index.php;
    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ {
        try_files /index.php =404;              # only the front controller is executable
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    }
    location ~* \.(css|js|svg|png|woff2)$ { expires 1y; add_header Cache-Control "public, immutable"; }
}
```

Production checklist: `app.debug=false`, `app.force_https=true`, a real `app.url` (used in password-reset e-mails), `mail.driver=smtp`, `security.trusted_proxies` when behind a load balancer or Cloudflare, and a daily database backup.

## 3. What is built (phase 1)

| Area | Screens |
|---|---|
| **Auth & security** | Username login, lock-out after 5 wrong passwords, forgot/reset password by e-mail (single-use, hashed, 60-min token, also unlocks the account), forced password change, IP throttling, login log (IP, geo-location, ISP, browser, OS, device, screen, device time zone; stored in UTC and shown in the viewer's local time), audit trail, CSRF on every POST, strict CSP, secure session cookies, idle timeout |
| **SaaS** | Super admin: companies (provisions COA, currencies, taxes, units, warehouse and number series, and creates or links the company admin), all users, platform login log. Company admin: users and roles, multi-company assignment, plan user limit |
| **Profile** | Profile details and time zone, default company, password and security, my companies (switch), my login history. The company switcher is also in the right-hand profile dropdown |
| **Accountant** | Chart of accounts (list and **tree** view with balances, groups and ledgers, opening balances, cycle protection), manual journals (keyboard voucher entry, multi-currency, draft/post/void, duplicate) |
| **Inventory** | Items (goods/services, HSN/SAC, tax, prices, margin insight, GL accounts), categories, units (GST UQC), warehouses |
| **Sales / Purchases** | Customers and vendors (TRN/GSTIN validation with the GSTIN checksum, tax treatment, place of supply, currency, credit limit, terms) |
| **Banking** | Bank, cash, card and wallet accounts, each with an auto-created ledger and a live balance |
| **Reports** | Trial balance, general ledger, profit & loss, balance sheet, day book. All have filters, print and CSV export |
| **Settings** | Company profile (tax regime, TRN/GSTIN, FY start, date format, time zone), currencies and exchange-rate history, tax rates, document numbering, login log, audit trail |

Planned screens (invoices, bills, payments, stock movements, VAT/GST returns …) already appear in the menu with a phase badge and open a roadmap page. See `config/menu.php`.

## 4. How a list screen works

Every list (chart of accounts, items, customers, users …) comes from one **resource definition** in `app/Resources/{module}/{name}.php`. The definition declares the columns, filters, overview stats, form fields, hooks and row actions. The generic `ResourceController` provides:

- **Overview cards** at the top (count, totals, exceptions), recalculated for the active filters
- **Search** (`/`), **sortable columns**, **AJAX pagination** with a page-size selector (10/25/50/100/250, default 25) and *go to page*
- **Filters button**: the filter panel on the right is **hidden by default** and toggles with the button or **Alt+F**. Active filters show as removable chips. Date presets are included (today, this month, quarter …)
- **Right-side drawer form** for add/edit. **Enter** moves to the next field, **Shift+Enter** to the previous one, **Ctrl+S** saves, **Esc** closes. There is also *Save & New*
- **Row actions** (post, void, unlock, send reset link …), delete with business-rule checks, and **CSV export** (Excel friendly: UTF-8 BOM, guarded against formula injection)
- The URL keeps the state (`?page=2&per_page=50&f[status][]=draft`), so the back button and shared links work

Keyboard shortcuts: `Ctrl+K`/`Alt+G` go-to palette · `Alt+N` new · `Alt+F` filters · `Alt+E` export · `↑ ↓ Enter` move between and open rows · `PgUp/PgDn` pages · `?` shortcut help. In a journal: `Alt+A` add line · `Alt+B` balance · `Alt+P` post · `Ctrl+Del` remove line. A blank account ends the entry, as in Tally.

To add a new master screen, add a resource file and a menu entry. No controller or view code is needed.

## 5. Project structure

```
public/            index.php (front controller), .htaccess, assets (css, js, img)
app/
  Core/            App (routes + middleware), Router, Database, Auth, Session, Csrf, Resource engine, View
  Controllers/     Auth, Dashboard, Profile, Resource (generic), Account, Journal, Report, CompanySettings, Planned
  Services/        JournalService (single posting engine), Ledger, CompanyProvisioner, TaxRules, NumberSeries,
                   LoginLogger, GeoIp, UserAgent, Mailer, PasswordReset, Audit
  Resources/       declarative list/form definitions per module
  icons/           the Lucide SVGs in use (generated by `npm run icons`)
views/             layouts, partials (list, form, overlays), pages, accountant, reports, profile, settings, auth, emails
config/            config.example.php, menu.php (top navigation), permissions.php (roles → module access)
database/          schema.sql
bin/install.php    installer / demo seeder
resources/css/     Tailwind v4 source (app.css)
docs/              SUGGESTIONS.md (review, corrections, roadmap, integration notes)
```

## 6. Conventions

- **Times:** all `DATETIME` columns are UTC (PHP and the MySQL session are forced to UTC). They are converted to the user's time zone only for display (`local_dt()`).
- **Money:** `DECIMAL(18,4)` amounts, `DECIMAL(18,8)` rates. Each currency has its own decimals (KWD/OMR/BHD use 3). Journal lines store both the transaction and the base-currency amount.
- **Tenancy:** every business table has `company_id`, and the resource engine scopes every query to the active company automatically. Lookup values posted from forms are checked against the company's own option lists, so another tenant's IDs are rejected.
- **One ledger:** every future module (invoices, bills, payments, stock) must post through `JournalService::save()` with `source_type` and `source_id`. Then all reports come from `journal_lines` and always reconcile.
