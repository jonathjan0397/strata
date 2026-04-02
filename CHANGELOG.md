# Changelog

All notable changes to Strata are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.0.15] — 2026-04-02

### Added
- **Missing email templates** — three previously unseeded templates added: `domain.expiring` (domain expiry reminder with domain name, expiry date, days remaining, renewal link), `invoice.reminder` (payment due reminder with invoice ID, amount, due date, days until due, pay link), and `ticket.auto_reply` (support ticket auto-confirmation with ticket ID, subject, auto-reply body, and ticket link); all seeded via `EmailTemplatesSeeder::updateOrCreate` so existing customisations are preserved.

### Fixed
- **Email log not updating** — `TemplateMailable` implemented `ShouldQueue`, meaning emails dispatched via `Mail::send()` were queued and `MessageSent` never fired on shared hosting without a running queue worker; `LogSentEmail` therefore never logged them; removed `ShouldQueue` from `TemplateMailable` to make all email sends synchronous and log-safe; commands that use `Mail::queue()` explicitly (`SendDomainRenewalReminders`, `SendPaymentReminders`) now also fire `MessageSent` synchronously when `QUEUE_CONNECTION=sync`.
- **OAuth redirect URL not visible to admins** — Settings → Integrations → OAuth sections now display the exact callback URLs to whitelist in Google Cloud Console and Azure App Registrations (e.g. `https://your-domain/auth/google/callback`); previously admins had to guess the correct URL.

---

## [1.0.10] — 2026-04-02

### Added
- **Portal feature highlights editor** — admin can edit, toggle, add, or remove the three feature cards shown on the public portal home page (icon, title, description, enabled toggle); stored as JSON in settings; section hides automatically when all cards are disabled; grid columns adjust to match the number of enabled cards (1, 2, or 3).
- **Portal stats row editor** — admin can edit, toggle, add, or remove the stats displayed below the hero buttons (e.g. 99.9% Uptime, 24/7 Support, SSL Included); each stat has a value, label, and enabled toggle; row hides when all stats are disabled.
- **Reports & Analytics date range selection** — period selector bar with quick presets (This Month, Last Month, Last 12 Months, Year to Date, Last Year, All Time) and specific year/month pickers; period-aware revenue chart switches between daily bars (month views) and monthly bars (year/multi-month views); period KPI banner shows total revenue and invoice count for the selected range.
- **Reports CSV export** — two export formats available for any period: *CSV — Invoices* (detailed per-invoice rows with client name, email, subtotal, tax, total, payment method; compatible with QuickBooks, Xero, Wave, FreshBooks) and *CSV — Summary* (monthly aggregated totals with a TOTALS row; useful for accountant reconciliation); `GET /admin/reports/export` route with `type` and period params.
- **Active Sessions** — `sessions` database table migration with `Schema::hasTable` guard; `config/session.php` default driver set to `database`; admin Active Sessions page now shows all connected users.
- **Bulk suspend/unsuspend rate-limit protection** — 1-second delay injected between panel API calls in `SuspendOverdueServices` and `ProcessScheduledCancellations` when services have a panel configured; prevents CWP/cPanel from dropping rapid sequential requests.

### Fixed
- **`OrderProvisioner::callPanel()` silent return** — when a service had `module_id` set but the module record had been deleted, `callPanel()` silently returned without calling the panel API; changed to throw `RuntimeException` with a clear message directing the admin to re-link the service; added `Log::debug` for the legitimate no-panel early-return case.
- **Active Sessions always blank** — server `.env` had `SESSION_DRIVER=file` overriding the config default; sessions were never written to the database table; fixed by updating the server `.env` to `SESSION_DRIVER=database` and running `config:clear`.

---

## [1.0.0] — 2026-04-01 — Stable Release

### Added
- **Local / internal server access** — modules now store optional `local_hostname` and `local_port`; when set, all provisioner API calls use the local address and SSL certificate verification is skipped automatically; migration `2026_04_01_000012_add_local_access_to_modules`.
- **CWP package endpoint fix** — CWP REST API uses `/v1/packages` (plural) for all package operations; corrected from `/v1/package`; updated field names to match CWP v1 API (`package_name`, `disk_quota`, `ftp_accounts`, `email_accounts`, `databases`, `sub_domains`, `parked_domains`, `addons_domains`, `hourly_emails`).
- **Maintenance page** — admin `Maintenance` page with three action cards: Run Migrations (runs `artisan migrate --force`), Force Schema Repair (directly applies known schema changes via `ALTER TABLE` / `CREATE TABLE IF NOT EXISTS`, bypassing migration tracking), and Clear Cache (flushes config, route, view, and compiled class caches); each card shows output inline.
- **Force Schema Repair endpoint** — idempotent SQL repair covering: `modules.type` enum (hestia + cwp), `modules.local_hostname` / `local_port` columns, `mailbox_pipes` IMAP columns, and `tld_pricing` table creation.
- **Product provisioning account options** — product form now exposes account-level options: reseller account checkbox (visible only for cPanel, Plesk, DirectAdmin, CWP), SSL/Let's Encrypt checkbox with amber DNS warning, and PHP version field; stored in `module_config`.
- **Service lifecycle panel integration** — `OrderProvisioner::suspend()`, `unsuspend()`, and `terminate()` call the hosting panel API (suspend/unsuspend/terminate account), update the DB, fire audit log and workflow events, and send the client a notification email with the reason; `ServiceController` uses these methods and accepts an optional `reason` request field; `SuspendOverdueServices` and `ProcessScheduledCancellations` also route through `OrderProvisioner` for consistent panel + notification handling.
- **Service lifecycle email notifications** — clients are emailed on admin-initiated suspend (`service.suspended` with reason), reactivation (`service.reactivated`), and termination (`service.terminated` with reason); billing-triggered suspensions use reason "Payment overdue"; scheduled cancellations use "Service cancelled at end of billing period".
- **`TldPrice` model explicit table name** — `protected $table = 'tld_pricing'` added; prevents Laravel auto-pluralisation resolving to the wrong `tld_prices` table name.
- **`mail:fetch` scheduler entry** — IMAP mailbox polling command registered in `console.php` on a 5-minute schedule with `withoutOverlapping()` and `runInBackground()`.

### Fixed
- **Outgoing mail flagged as spam — missing SPF/DKIM/DMARC** — emails sent from the portal had no SPF record on the sending domain, no DKIM signature, and DMARC set to QUARANTINE with both auth checks failing; fixed by adding in-app DNS health check tool (Settings → Email → Check DNS Records) that runs live `dns_get_record()` lookups for SPF, DKIM, and DMARC and shows exact DNS records to add; documented full setup in BUGFIX.md BF-034.
- **Missing `service.reactivated` and `service.terminated` email templates** — `OrderProvisioner` was sending these template slugs but they were never seeded; emails arrived with the raw slug as the subject and "No template found" as the body; added both templates to `EmailTemplatesSeeder`; also updated `service.suspended` template to use `{{reason}}` variable instead of hardcoded "unpaid invoice" language, since it is now sent for both billing-triggered and admin-triggered suspensions.
- **Intermittent 403 in admin panel (ModSecurity CRS anomaly scoring)** — OWASP CRS in anomaly-scoring mode accumulated risk scores across multiple rules fired on innocent POST body content (Tiptap HTML, `{{variable}}` email template syntax, SQL-like words in support tickets, `<script>` in KB code blocks); fixed by adding `SecRuleEngine Off` for `/admin` paths in `public/.htaccess`; admin routes are protected by application auth, making WAF body scanning redundant; full troubleshooting path documented in `BUGFIX.md BF-033` for hosts where `.htaccess` overrides are restricted.
- **IMAP double-close crash** — `FetchMailboxes` was calling `imap_close()` explicitly before an early `return`, then the `finally` block called it again; PHP 8.1+ throws `ValueError: IMAP\Connection is already closed` on the second call; removed the redundant close and added a `try/catch` guard in `finally`.
- **CWP API 400 on all list operations** — CWP REST API v1 requires POST for all endpoints including list; `listAccounts` and `listPackages` were issuing GET requests.
- **`modules.type` enum missing CWP and HestiaCP** — enum was defined without these values; any attempt to save a CWP or HestiaCP server record threw `SQLSTATE[01000]: Data truncated for column 'type'`; fixed via Force Schema Repair and migration `2026_04_01_000011`.
- **`local_port` empty string on SMALLINT column** — form initialized `local_port` as `''`; MySQL strict mode rejects empty string for `SMALLINT UNSIGNED`; form now initializes as `null` and controller normalizes empty strings to `null`.

### Install telemetry — `app/Services/StrataLicense.php` static service; `app/Console/Commands/StrataSync.php` daily cron (`strata:sync` at 04:15); `config/strata.php` (`STRATA_LICENSE_SERVER_URL`, `STRATA_LICENSE_SECRET`); `install_token` UUID written to `installed.lock` at install time (backfilled on upgrade); HMAC-SHA256 response verification; 25-hour cache with graceful all-pass degradation on any failure.
- **CWP (Control Web Panel) provisioner** — full `CwpProvisioner` driver for the CWP REST API (`/v1/`); implements create/suspend/unsuspend/terminate/listAccounts/listPackages; API key sent as `key` param in every request; added to `ProvisionerService` driver map, `ModuleController` validation, and module type enum migration `2026_04_01_000011`.
- **HestiaCP and CWP added to modules type enum** — migration `2026_04_01_000011_add_hestia_cwp_to_modules_type_enum.php` adds `hestia` and `cwp` to the `modules.type` enum (both existed as provisioner drivers but were missing from the DB constraint).
- **Server account import wizard** — `ServerImportController` with preview (fetches accounts+packages from panel, auto-matches plans to products, flags already-imported) and store (creates clients, services, and placeholder products); multi-step `Import.vue` wizard with package mapping table and per-account checkboxes; "Import Accounts" link on Modules index.
- **Product provisioning configuration** — product form now selects panel type, specific server (with live account counts), and package/plan (loaded live from the server API); `OrderProvisioner` respects a pinned `module_id` in `module_config` and throws clearly if the pinned server is full/removed rather than silently falling back.
- **Package sync** — `PackageSyncController` with `show` (lists all panel packages, annotates each with matching Strata product) and `store` (imports selected packages as hidden/$0 products); "Sync Packages" link on Modules index; `PackageSync.vue` page with inline disk/bandwidth editing and "Create on Panel" capability.
- **Auto-create packages at provisioning time** — `ProvisionerDriver` interface extended with `packageExists()` and `createPackage()`; all five provisioners (cPanel, DirectAdmin, Plesk, HestiaCP, CWP) implement both; `OrderProvisioner` calls `createPackage()` before `createAccount()` when `module_config.auto_create_package = true` and the package doesn't already exist on the server; product form exposes disk/bandwidth/auto-create fields under the package selector.
- **IMAP polling for mail pipes** — `FetchMailboxes` artisan command (`mail:fetch`) polls active IMAP-configured mail pipes every 5 minutes via the scheduler; connection string built from `imap_host`, `imap_port`, `imap_encryption`; fetches UNSEEN messages via `imap_search()`, processes each with `EmailPipeProcessor`, marks Seen, updates `imap_last_checked_at`; IMAP fields added to `MailboxPipeController::update()` validation; migration `2026_04_01_000010_add_imap_fields_to_mailbox_pipes`.
- **Buy Me a Coffee links** — inconspicuous ☕ links added to admin layout footer, portal layout footer, strata-license app blade layout, and mailframe sidebar; shields.io badge added to the top of all public documentation files (README, ROADMAP, FEATURES, install guide, mailframe docs, strata-panel docs).

### Added
- **Active Sessions admin page** — `Admin/ActiveSessions` shows all currently logged-in users (clients, staff, admins) with role badges, device/browser detection, IP, last-active time, and per-session or per-user revoke capability; "You" badge on current admin's own session; counts bar with total, admin, staff, client tiles; tabs to filter by role group; backed by `ActiveSessionsController` with a single role-priority SQL sub-select query.

### Changed
- **Admin nav groups collapsed by default** — all administration nav groups now start collapsed for a cleaner first-look presentation; the active group (matching current URL) auto-expands as before.
- **Dark mode readability** — comprehensive `app.css` overrides rewritten: `text-gray-400` corrected from slate-600 (invisible on dark) to slate-500; `text-gray-500` lifted to slate-400; full `slate-*` text/bg/border coverage added (admin pages use both `gray-*` and `slate-*`); semi-transparent card backgrounds (`bg-white/70`, `bg-gray-50/80`); hover states for all table rows, list items, and sidebar links; action-bar overrides for indigo, amber, green, red, blue patterns; placeholder color and input focus ring in dark.
- **Buy Me a Coffee link visibility** — admin layout footer and portal layout footer link bumped from `text-xs opacity-20` to `text-sm opacity-60`; now readable in light mode without being obtrusive.

### Fixed
- **cPanel auth header** — all WHM JSON API calls were using `Authorization: Bearer <token>` (Laravel `withToken()`) instead of the documented `Authorization: whm <username>:<token>` format; would have caused 401 on every cPanel operation.
- **HestiaCP command parameter format** — all CRUD operations were passing named key-value params (`user=`, `password=`, etc.) instead of positional `arg1=`, `arg2=`, ... as the HestiaCP REST API requires; affected createAccount, suspendAccount, unsuspendAccount, terminateAccount.
- **HestiaCP wrong command** — `v-add-web` does not exist; correct command is `v-add-web-domain`; web domain was silently never added after user creation.
- **cPanel QUOTA units** — `listPackages` was multiplying QUOTA (already in MB) by 1024, producing KB values; removed incorrect multiplier.
- **CWP package units** — `listPackages` was multiplying diskspace/bandwidth (already in MB) by 1024; removed incorrect multiplier.
- **HestiaCP package units** — `listPackages` was multiplying DISK/BANDWIDTH (already in MB) by 1024; removed incorrect multiplier.
- **Plesk auth header** — was sending `Authorization: Basic base64(admin:<key>)` instead of the documented `X-API-Key: <key>` header for Plesk REST API v2.
- **Plesk `ownerLogin` field** — `listAccounts` and `findSubscriptionByUsername` referenced flat `ownerLogin` field; correct Plesk REST API v2 structure is nested `ownerClient.login`.
- **Plesk dead `$payload` variable** — `createAccount` built a `$payload` array then discarded it, sending a separate hardcoded array to the webspaces endpoint; consolidated into a single correct payload that respects the `$plan` parameter.

---

## [1.0-RC1] — 2026-03-29 — Release Candidate 1

### Added
- **BF-015** — Removed `Log::debug('INSTALL_DB_TEST', ...)` from `InstallerController::testDatabase()` — was writing DB credentials and password hex dumps to laravel.log
- **Ticket search extended** — admin search now also scans reply message bodies (previously subject + client name only)
- **Client billing history** — invoice index now shows summary cards (total billed, total paid, outstanding) and date range filter (from/to) in addition to existing status tabs
- **Suggested KB articles on ticket create** — as client types a subject, matching published KB articles are fetched and displayed as a "before you submit" panel with links
- **Admin dark mode** — sun/moon toggle in top bar; persisted via localStorage; `html.dark` class drives Tailwind `dark:` variants on the layout chrome plus global CSS overrides for card/table/input patterns across all admin pages
- **HTML reply editor on tickets** — Tiptap rich-text composer replaces plain textarea in both admin and client ticket reply forms; bold/italic/underline/lists/links toolbar; reply thread renders with `prose` classes
- **Client satisfaction ratings analytics** — Reports page now shows overall average rating with star graphic, 1–5 star distribution bar chart (rated count per star), and per-staff breakdown table (avg rating + ticket count); backed by new queries in `ReportController`
- **Bank transfer / manual payment** — admin configures payment instructions in Settings → Billing; client invoice page shows "Bank Transfer" button (only when configured) that reveals the formatted instructions panel; admin marks paid manually via existing mark-paid flow
- **Browser-based upgrade wizard** — `/upgrade` route guides resellers through upgrading without CLI; detects installed vs code version from `installed.lock` + `composer.json`; accepts ZIP upload (PHP ext-zip path) or "files already uploaded via FTP" path; re-extracts files while preserving `.env`, uploaded files, and sessions; runs `artisan migrate --force`; clears and rebuilds caches; updates `installed.lock` version; credential-verified via super-admin email + password without requiring a session

### Changed
- `composer.json` version field added: `1.0-RC1`; installer fallback version corrected to match

---

## [1.0-Beta.2] — 2026-03-29 — Portal Branding, Themes & Integration Polish

### Added
- **Portal color themes** — 4 admin-selectable themes in Settings → General: Ocean Blue, Ruby Red, Forest Green, Sky Blue; theme propagated globally via Inertia middleware
- **Portal branding** — company logo displayed on login and register pages; gradient letter-icon fallback when no logo is uploaded; "Powered by Strata Service Billing and Support Platform" tagline shown on auth pages
- **Portal tagline setting** — configurable in Settings → General; displayed on public portal home
- **Domain search TLDs setting** — admin configures which TLDs to check in Settings → General; used by public portal domain search and checkout availability check
- **Domain search on public portal home** — live availability check bar appears when a domain registrar is configured; shows available/taken status + price per TLD + register button
- **Domain-search embeddable widget type** — fifth widget type added to `strata-widget.js`; embed a live domain search form on any external site
- **Staff can manually create tickets** — new Create Ticket route and form in admin support; staff select a client, department, priority, and compose the initial message on behalf of the client
- **Verify email button on client detail** — admin can mark a client's email address as verified without requiring the client to click the verification link
- **Domain registrar integrations in Settings** — Namecheap, eNom, OpenSRS, and HEXONET credentials configurable from Settings → Integrations (no `.env` edit required after initial setup)
- **2FA settings in Settings → General** — admin can enable/disable 2FA enforcement, set session lifetime, and toggle the keep-alive ping
- **`siteName`, `logoUrl`, and `portalTheme` shared globally** via `HandleInertiaRequests` middleware; available on every page without per-controller passing

### Changed
- **Announcements editor upgraded to Tiptap** — rich text editor with inline image uploads replaces plain textarea; stored as HTML; rendered in client portal and public portal with `prose` classes
- **Settings → Integrations reorganized** — tab split into 4 collapsible categories: Payment Gateways (Stripe, PayPal, Authorize.Net), Domain Registrars (Namecheap, eNom, OpenSRS, HEXONET), Fraud Prevention (MaxMind minFraud), OAuth/Social Login (Google, Microsoft)
- **OAuth providers corrected** — social login updated to Google and Microsoft (GitHub removed; not a supported provider)
- **Dead config entries removed** — Postmark, Resend, SES, Slack, and unused mailer transport definitions removed from `config/mail.php` and `config/services.php` to reduce noise

---

## [1.0-Beta] — 2026-03-28 — Initial Beta Release

### Added
- **GitHub Actions release workflow** — tag-triggered CI builds `Strata-{TAG}.zip` with `vendor/` and `public/build/` pre-compiled; attaches to GitHub Release with install instructions; tags containing `beta`, `alpha`, or `rc` automatically marked as pre-releases
- **Pre-install URL auto-detection** (`AppServiceProvider`) — when `APP_URL` is `http://localhost`, detects real base URL from the incoming request and calls `URL::forceRootUrl()` so Ziggy and `route()` helpers generate correct URLs before the installer runs
- **Subdirectory install support** — hardcoded absolute paths in Portal pages replaced with Ziggy `route()` calls; `CheckInstalled` redirect and URL detection both respect subdirectory prefixes via `getBaseUrl()`
- **Install README** (`README-INSTALL.md`) — end-user installation guide included in the release ZIP with 403 Forbidden troubleshooting section covering subdirectory installs, AllowOverride, mod_rewrite, and root@localhost errors with CWP/cPanel-specific guidance
- **Rebranded** — official name updated to **Strata Service Billing and Support Platform** across `config/app.php`, `.env.example`, portal layout, README, and release workflow
- **Copyright** — `© 2026 Jonathan R. Covington` added to portal footer and install documentation

### Fixed
- **BF-026** — `HandleInertiaRequests::share()` flash closures called `$request->session()` without `hasSession()` guard; threw `RuntimeException` on install routes where `StartSession` is stripped
- **BF-027** — `CheckInstalled` used `redirect('/install')` absolute path; ignored subdirectory prefix; fixed with full URL construction from request
- **BF-028** — Pre-install Laravel 12 cache driver defaults to `database`; before `.env` exists cache queries hit `root@localhost` with no password; fixed by switching to `array` driver when `installed.lock` is absent
- **BF-029** — Pre-install database cache query caused 500 before credentials were configured (same root cause as BF-028; resolved by same fix)
- **BF-030** — `withoutMiddleware` on install routes listed `App\Http\Middleware\VerifyCsrfToken` which does not exist in Laravel 12; correct class is `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken`; exclusion was a no-op
- **BF-031** — Release ZIP `--exclude` wildcard for `storage/framework/sessions/*` also excluded `.gitkeep` files; storage subdirectories vanished from the ZIP; fresh installs failed with "directory does not exist" errors; fixed by re-adding `.gitkeep` files after the main zip command
- **BF-032** — Sample data seeder had 9 column/enum mismatches against actual migrations (`uses` → `uses_count`, `valid_from` → `starts_at`, `valid_until` → `expires_at`, `active` → `is_active` on promo_codes; product type `hosting` → `shared`; announcement `content` → `body`; removed non-existent `pinned`; payment status `paid` → `completed`; quote status `pending` → `sent`)

### Changed
- **`@vitejs/plugin-vue`** upgraded from `^5.1` to `^6.0` to resolve npm peer dependency conflict with Vite 8 in the release workflow

---

## [1.0-Beta-RC] — 2026-03-27 — Pre-Beta Feature Completion

### Added

This release represents the completion of all core and advanced features built during the initial concentrated development cycle. The full feature set built to reach this point includes:

#### Admin Panel
Full admin panel across all sections: dashboard summary stats; client CRUD with suspension, internal notes, group assignment, country/state/tax_exempt; product CRUD with auto-setup triggers and trial periods; service lifecycle (suspend/unsuspend/terminate, Approve & Provision, cancellation management); global addons catalog with service attachment and auto-renewal; quote system (freeform line items, tax, valid-until, send, convert to invoice, QUO-YYYYMMDD-NNNN numbers); order list (ORD-YYYYMMDD-NNNN numbers); invoice management with PDF download, credit notes, and line items; full support ticket queue with reply, assign, close/reopen, department transfer, merge, bulk actions, SLA indicators, canned responses, internal notes, file attachments, first-reply tracking, and satisfaction rating view; Knowledge Base with Tiptap rich text editor including image upload; server CRUD for cPanel/WHM, Plesk, DirectAdmin, and HestiaCP; domain management with NS editor, lock/privacy, refresh; client groups; tax rates; email templates (11 built-in); email log; audit log; reports; automation workflows engine; affiliate program; staff permission editor; settings (general, company, billing, email, payments, fraud prevention).

#### Client Portal
Full client portal: dashboard; order/checkout with live domain availability check, promo codes, group discount, tax, and fraud scoring; service management with cancellation requests, upgrade/downgrade (prorated), and addons; quote view/accept/decline; invoice list and detail with Stripe/PayPal/Authorize.Net payment, credit apply, and PDF download; Stripe saved cards with auto-charge on renewal; support tickets with file attachments, satisfaction ratings, and search/filter; domain management with NS editor and auto-renew toggle; Knowledge Base browse and search; announcements; affiliate program dashboard; TOTP 2FA setup; active session management.

#### Public Portal
Glassmorphism public portal: home page with hero, product catalog teaser, announcements, and KB teaser; full services/product catalog page; Knowledge Base browse and article view; announcements listing. Navigation with Sign In and Get Started buttons and mobile hamburger menu.

#### Embeddable Widget (`strata-widget.js`)
Embed on any external website via `data-strata-widget` attribute. Four widget types: `catalog` (product grid), `announcements`, `kb` (knowledge base), `support` (CTA). Two themes: `glass` (dark) and `light`. CORS-open API endpoints at `/api/widget/*`.

#### Provisioning
`ProvisionerDriver` contract and `ProvisionerService` factory with four modules: cPanel/WHM (WHM JSON API v1), Plesk (REST API v2), DirectAdmin (HTTP API), HestiaCP. `provisioning:run` cron every 5 minutes.

#### Domain Registrars
`RegistrarDriver` contract and `DomainRegistrarService` factory with four integrations: Namecheap (XML API v1), Enom (reseller XML API), OpenSRS (XCP API with HMAC-MD5 auth), HEXONET (ISPAPI). All support sandbox mode, live availability check, auto-renew, and expiry reminder emails.

#### Payment Gateways
Stripe Checkout with webhook reconciliation, stored cards, and off-session auto-charge. PayPal Orders v2 (create → redirect → capture). Authorize.Net AIM API. `PaymentGateway` contract and `GatewayService` factory; double-payment guard; pending payment record on initiation; gateway buttons hidden when not configured.

#### Billing Automation
Full scheduler suite: `billing:generate-renewals` (daily 08:00), `billing:flag-overdue` (daily 00:05), `billing:suspend-overdue` (daily 01:00), `billing:process-cancellations` (daily 00:30), `billing:send-reminders` (daily 10:00), `billing:apply-late-fees` (daily 02:00), `billing:retry-payments` (daily 11:00), `provisioning:run` (every 5 min), `domains:renew-expiring` (daily 09:00), `domains:send-reminders` (daily 09:30), `support:close-inactive` (daily 03:00).

#### Advanced Order Features
Trial periods, plan upgrades/downgrades with proration, promo codes (percent/fixed/free-setup-fee with date windows, recurring cycle limits, new-clients-only flag), fraud scoring via MaxMind minFraud, order numbers (ORD-YYYYMMDD-NNNN), client notes at checkout.

#### Automation Workflows
Trigger-based workflow engine: event → conditions → actions → delay. Run history log.

#### Affiliate Program
30-day referral cookie; commission on first order; percent or fixed commission types; admin-managed approval and payout flow; client affiliate dashboard with referral link, stats, and payout requests.

#### Shared-Hosting Compatibility
All emails use `Mail::send()` with silent `try/catch`. PATCH/PUT/DELETE method-spoofed via POST (`_method`). `sendmail` defaults to `-t -i` pipe mode. Installer strips session and CSRF middleware (ModSecurity compatible). Pre-install cache driver auto-switched to `array`. URL auto-detected from request.

---

*Last updated: 2026-04-02 — 1.0.10 tagged*
