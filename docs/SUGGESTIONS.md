# Review, corrections and recommendations

This document covers point 8 of the brief. It has four parts:

- Issues found in the `hallsons.htm` prototype and how this build corrects them
- Comments on the requirements (where I'd change or clarify something)
- Recommendations and the roadmap
- Decisions I need from you

---

## 1. Issues in `hallsons.htm` and what was changed

| # | Issue in the prototype | Correction in this build |
|---|---|---|
| 1 | The ERP menu is a dark left sidebar | Moved to a **white top navbar** with dropdown menus. Profile, the company switcher and sign-out are in a **right-side profile dropdown**, as requested |
| 2 | The company switcher sits in the top bar as a separate `<select>`, and choosing a company changes the "industry" labels (trading / manufacturing / services) | A company is a real tenant record. The switcher lists only the companies **linked to the user** (with role and base currency) and lives in the profile menu and on the *My Companies* profile tab |
| 3 | The login form asks for an **e-mail** and an "Organization Category". The password is pre-filled. Login is client-side only (`enterErpApp()` just un-hides a div) | Server-side **username** login with password hashing (Argon2id), CSRF protection, lock-out, IP throttling and a login log. The company comes from the user's links, so the user never picks one at login |
| 4 | "Launch ERP Live Demo" opens the app without authentication | Removed. Demo access uses the seeded demo users |
| 5 | Uses the **Tailwind Play CDN** (`cdn.tailwindcss.com`, which the Tailwind team says is not for production) and `lucide@latest` (unpinned, so it can break without warning, and a few hundred KB of JavaScript) | Tailwind **v4.3.4** compiled and minified to one CSS file (~74 KB, ~12 KB gzipped). Only the ~100 Lucide icons in use are inlined as SVG. There are no runtime CDN scripts, which allows a strict Content-Security-Policy |
| 6 | Hard-coded `₹` in labels ("Amount (₹)", "Opening Balance (₹)") and `FY 2026-27` in the sidebar, even though the system is multi-currency and the demo company is in the UAE | Currency labels come from the company **base currency** or the document currency. The financial year is computed from the company's *FY start month* |
| 7 | Mixed branding: "Kinetix Prime", "Formerly Hallsons", "Hallsons Prime", "SaaS v4.2" | One product name, set in config (`app.name`). Confirm the final brand (see section 4) |
| 8 | The apps grid shows POS, CRM, Manufacturing and HR & Payroll, which are not in scope | Removed. The menu shows only the modules in the roadmap, and the ones not built yet are marked **Phase N** |
| 9 | Navigation uses `<button onclick>` everywhere (78 inline handlers), so there are no real URLs: no deep links, no back button, no open-in-new-tab | Every screen has a real clean URL (`/accountant/journals/15`). List state lives in the query string. No inline JavaScript, which is also required for the CSP |
| 10 | The chart of accounts tree and tables are static HTML with fake balances | Data comes from the database. Balances are computed from the ledger (opening + posted journals) |
| 11 | Complex forms open in centred modals. The "New Account" modal shows a fixed list of parent groups | Forms open in a **right-side drawer**, so the list stays visible. Parent options are the company's own groups, validated on the server |
| 12 | Mostly 10–11 px text, low-contrast slate-on-slate, a dark header | 13–14 px base size, AA contrast, white base design, visible focus rings, keyboard access, skip link, aria attributes |
| 13 | "API Connected" status is a static fake | Removed. The status bar shows real context: company, FY, base currency, tax registration |
| 14 | Inconsistent labels: "Currency Revaluations" opens "Base Currency Adjustments"; "Stock Adjustments" vs "Inventory Adjustments"; "Delivery Challans" (India-only term) | Consistent naming: *Currency Revaluation*, *Stock Adjustments*, *Delivery Notes* (a common term for both UAE and India) |
| 15 | The marketing site (home, pricing, contact) and the application are in one HTML file | The application is separate. The marketing site should be its own static site (a CMS or static HTML on a CDN) so it can be cached and SEO-optimised independently |

## 2. Comments on the requirements

1. **Username uniqueness.** Login uses only a username, with no company code, so usernames are unique **across the whole platform**. If two client companies want the same username (for example `accounts`), you need either a company code on the login screen or `username@companycode` logins. *Built:* global uniqueness, lower-case, 3–50 characters `[a-z0-9._-]`.

2. **Lock after 5 wrong attempts. Please reconsider "permanent until reset".** An attacker who knows usernames can lock out your whole customer base on purpose (a denial-of-service). Common practice is a temporary lock of 15–30 minutes that grows with repeated locks, plus IP throttling and a CAPTCHA after 3 failures. *Built as you asked* (locked until a password reset or admin unlock), with IP throttling (20 failures per 15 minutes per IP). A timed auto-unlock is a small change. I recommend it.

3. **"Machine details".** Browsers do not expose the MAC address, hostname or hardware IDs, and trying to collect them is a privacy risk. *Built:* browser and version, OS, device type, platform, screen resolution, language and device time zone, plus the IP address with city, region, country and ISP. Geo-IP data is approximate. For production use a local MaxMind GeoLite2 database instead of a public API, so IP addresses don't leave your servers.

4. **Privacy laws.** The login log is personal data under UAE PDPL (Federal Decree-Law 45/2021) and India's DPDP Act 2023. Add a privacy notice, a retention period (for example 12 months for login logs) and a purge job. Audit logs of financial records follow the tax-record rules instead (see point 9).

5. **Local time.** As requested, the system stores UTC and shows local time. The device time zone is also stored with each login, so you can see *where* the user was as well as *when*.

6. **Multi-currency.**
   - The base currency is fixed after company creation. Changing it later would invalidate every base amount.
   - Exchange rates are dated (`exchange_rates`), so documents use the rate of their date.
   - Next steps: automatic daily rates (CBUAE for AED, the RBI/FBIL reference rate for INR), **realised** FX gain/loss on payments and **unrealised** revaluation at period end (the planned *Currency Revaluation* screen).

7. **SaaS hierarchy.** "A super admin creates a company and its admin" works. Most clients will own **several companies**, though, so you may want a *Tenant / Subscription* layer above companies. Billing, user limits and "group admins" belong to that layer. Today the user limit is per company. I recommend adding the tenant layer before the first paying customer, because adding it later means a data migration.

8. **India GST.**
   - Rates follow **GST 2.0 (from 22 Sep 2025): 0 / 5 / 18 / 40%**, plus special 3% and 0.25%. Rates must be *effective-dated* because they change.
   - CGST+SGST or IGST is decided **per document** from the place of supply, not per tax master.
   - The GSTIN is validated including its checksum and state code.
   - Still to build:
     - HSN length rules by turnover (4 or 6 digits)
     - e-Invoice (IRN/QR through an IRP, required above the AATO threshold; currently ₹5 crore)
     - e-Way bill
     - GSTR-1 / GSTR-3B exports
     - 2B reconciliation

9. **UAE VAT and Corporate Tax.**
   - VAT 5%, zero-rated, exempt, reverse charge and out-of-scope are seeded. The TRN is validated as 15 digits starting with 100.
   - Keep VAT records for at least **5 years**. This is why posted journals can only be **voided** and never deleted or edited.
   - Also plan for UAE Corporate Tax (9% above AED 375,000).
   - Plan for the **UAE e-invoicing mandate** (PINT-AE over Peppol through an accredited service provider). It is being phased in from 2026–27, starting with large businesses. Check the Ministry of Finance timeline when you plan phase 7.

10. **"Avoid Zoho/Odoo's unique design, but adopt their UX."** The layout here is original: white top bar, right drawer, Tally-style right filter panel, status bar. The UX patterns are generic and widely used: list → filter → drawer, keyboard navigation, overview cards.
    - **Do not** use their names, icons, colour schemes, screenshots or wording in the product or marketing.
    - Tally's familiar F-key shortcuts can't be copied one-to-one, because browsers reserve F1, F3, F5, F11 and F12. I used **Alt-based** shortcuts and kept Tally habits: Enter to move to the next field, a blank ledger ends the voucher, the balancing amount is suggested automatically, and an "Accept?" step focuses Save at the end.

11. **ERP / API integration.** Plan a versioned REST API (`/api/v1`) with:
    - per-company API keys or OAuth2 client credentials
    - rate limits
    - **idempotency keys** on POST, so retries don't create duplicate invoices
    - webhooks (invoice.created, payment.received …)
    - an `external_refs` table that maps other systems' IDs

    The single posting engine (`JournalService`) is already the integration point: an imported invoice posts exactly like a manual one.

## 3. Recommendations

### Security (next steps)
- **2-factor authentication** (TOTP / authenticator app), at least for admins
- **Active sessions** list with "sign out other devices". Store sessions in the DB or Redis, not on local disk, so you can run several web servers
- Password history (no reuse of the last 5) and a breached-password check (HIBP k-anonymity API)
- CAPTCHA (Cloudflare Turnstile or hCaptcha) after 3 failed logins or reset requests
- Encrypt sensitive fields at rest (bank account numbers, IBAN) with libsodium. Mask them in lists (already masked)
- Automated tests that prove tenant isolation: user A can never read company B's IDs
- Daily encrypted off-site backups with a tested restore. Separate DB credentials for the app and for migrations

### Accounting
- **Period lock / year-end close**: lock dates, transfer the closing P&L to retained earnings, and carry forward. Reports currently treat profit to date as "unappropriated"
- **Maker–checker approval** for journals above a limit. Edit drafts only; fix posted entries with reversing entries (built: void, duplicate)
- **Cost centres / projects** (analytic tags on journal lines) are cheap to add now and hard to add later
- **Inventory valuation method**: decide between weighted average (simpler; common in Tally) and FIFO before phase 3. It drives the stock-ledger design
- Attachments on vouchers (bills, receipts) in object storage (S3-compatible)
- Opening-balance difference check. The trial balance already flags an imbalance

### Performance and network
- The list pages send only JSON rows (~5–15 KB per page) and cancel superseded requests. Turn on gzip/brotli and HTTP/2 on the server; static assets are cache-busted and cached for a year
- Add composite indexes as data grows (most list filters are already indexed by `company_id`). Move to cursor pagination once a list passes about 100k rows
- Use Redis for sessions and rate limits once you run more than one web server

### Engineering
- Add Composer with PHPUnit (unit tests for `JournalService`, `Ledger`, `TaxRules`) and a migration tool such as Phinx. Wire Playwright end-to-end tests, like the flows I used to verify this build, into CI
- Add a static analyser (PHPStan level 6+) and a code style check (PHP-CS-Fixer)

### Roadmap (shown in the app on every planned screen)

| Phase | Scope |
|---|---|
| **1 ✅** | Foundation: SaaS, auth & security, profile & company switch, settings, currencies, taxes, **chart of accounts, manual journals**, item / customer / vendor / bank masters, core financial reports |
| 2 | Accountant advanced: opening-balance wizard, currency revaluation, recurring journals, period lock & year-end close, cost centres |
| 3 | Inventory: stock ledger, adjustments, transfers, price lists, valuation (WAC/FIFO), re-order report |
| 4 | Sales: quotes → orders → delivery notes → invoices → receipts → credit notes, with VAT/GST per line and posting through `JournalService` |
| 5 | Purchases: PO → GRN → bill (3-way match) → payments → debit notes, reverse charge |
| 6 | Banking: fund transfers, statement import (CSV / MT940 / CAMT.053), reconciliation with matching rules |
| 7 | Statutory & analytics: UAE VAT 201, India GSTR-1/3B, e-invoice (IRN, PINT-AE), aging, cash-flow, dashboards, REST API & webhooks |

## 4. Decisions I need from you

1. **Product name / brand.** The prototype uses "Kinetix Prime" and "Hallsons". This build uses `SBA Invento` (one setting in `config/config.php`).
2. Lock-out policy: keep **permanent until reset**, or switch to a **timed lock** (recommended)?
3. Should usernames stay globally unique, or do you want a company code on the login screen?
4. Do you want a **tenant / subscription layer** above companies, for billing and group admins?
5. Inventory valuation method: **weighted average** or **FIFO**?
6. Which document should Phase 4 start with: **Sales invoice** or **Quote**?
7. Will UAE companies need Arabic (RTL) invoices and UI? It affects the layout early on (Tailwind supports RTL with logical properties).
