# Changelog

All notable changes to Strata are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Planned (next priorities)
- OpenSRS / HEXONET registrar drivers
- Plesk and DirectAdmin provisioning modules
- Logo upload for settings
- Email piping for support departments

---

## [0.9.0] — 2026-03-26 — System Settings & Complete Support Ticket System

### Added

#### System Settings
- **`Setting` model** — key/value store with `get()`, `set()`, `setMany()`, and `allKeyed()` static helpers; 1-hour cache with automatic bust on write
- **`SettingController`** — `index` returns all settings keyed; `update` validates and batch-upserts 17 configurable keys
- **`SettingsSeeder`** — seeds default values for General (company_name, timezone, date_format), Company (email, phone, address, city, state, zip, country), and Billing (currency, symbol, invoice_prefix, invoice_due_days, grace_period_days, tax_rate, tax_name)
- **`Admin/Settings/Index.vue`** — tabbed settings page (General / Company / Billing); inline save with `recentlySuccessful` confirmation; linked to Departments and Canned Responses sub-pages
- **Settings nav item** in admin sidebar with gear icon

#### Support Ticket Departments
- **`Department` model + migration** — departments table (name, description, email, sort_order, active); `scopeActive()` for ordered query; `department_id` FK added to `support_tickets`
- **`DepartmentController`** — full CRUD; validates unique name; returns to `Admin/Settings/Departments`
- **`Admin/Settings/Departments.vue`** — inline edit-in-row table with create form, activate toggle, delete with confirmation
- **4 default departments seeded**: General, Billing, Technical Support, Sales
- `SupportTicket` fillable updated to include `department_id`; `department()` relationship added
- Client `SupportController::create()` now passes departments from DB; `store()` sets both `department_id` and `department` string
- Admin `SupportController::index()` accepts `department` filter; passes `departments` to view
- Admin Support/Index: department filter dropdown column added

#### Canned Responses
- **`CannedResponse` model + migration** — canned_responses table (title, body, department_id nullable)
- **`CannedResponseController`** — full CRUD; validates title + body + optional department_id
- **`Admin/Settings/CannedResponses.vue`** — card list with inline edit; department scope label; linked from Settings
- Admin Support/Show: **canned response picker** dropdown — click "Insert canned response", pick a title, body is injected into reply textarea

#### Internal Staff Notes
- `internal` boolean added to `support_replies` (migration + model cast)
- Admin `SupportController::reply()` — when `internal=true`: creates reply without emailing client, without updating ticket status to "answered"; note gets amber dashed styling in thread
- Admin Support/Show: **internal note toggle** checkbox in reply form; textarea turns amber when checked; submit button reads "Add Note" vs "Send Reply"
- Client SupportController/show: filters `internal=true` replies from client-visible thread

#### Support Reopen
- `reopen` action on `SupportController` — sets status back to `open`
- Reopen button shown on closed tickets in admin show page and at bottom of closed ticket

Code Checked and Verified By: Claude

---

## [0.8.1] — 2026-03-26 — Documentation Update

### Changed
- **`README.md`** — full rewrite to reflect actual v0.8.0 feature set; accurate tech stack (Laravel 12, Inertia.js v2, Tailwind v4); complete installation guide with requirements, quick-start steps, scheduler and queue worker setup; configuration reference for Stripe, PayPal, and registrar env vars; project structure tree; roadmap summary table
- **`ROADMAP.md`** — all completed items checked off with ✅; partial items marked 🔄; planned items marked ⏳; milestones 0–3 reflect production-shipped state; milestones 4–6 updated to clearly separate done vs. planned work; backlog section updated to include Docker and Horizon

---

## [0.8.0] — 2026-03-26 — Domain Registration API

### Added
- **`RegistrarDriver` contract** (`app/Contracts/RegistrarDriver.php`) — interface defining `checkAvailability()`, `registerDomain()`, `renewDomain()`, `transferDomain()`, `getNameservers()`, `setNameservers()`, `getInfo()`, `setLock()`, `setPrivacy()`, `slug()`
- **`NamecheapDriver`** (`app/Services/Registrars/NamecheapDriver.php`) — Namecheap XML API v1 implementation with sandbox support; handles contact param mapping for all four contact types
- **`EnomDriver`** (`app/Services/Registrars/EnomDriver.php`) — Enom reseller XML API implementation with sandbox support
- **`DomainRegistrarService`** (`app/Services/DomainRegistrarService.php`) — driver factory; `driver(?string)`, `available()`, `checkAvailability(string)`
- **`config/registrars.php`** — `REGISTRAR_DRIVER`, `NAMECHEAP_*`, `ENOM_*` config keys
- **Admin `DomainController`** — `index` (paginated + filtered), `show`, `syncNameservers`, `setLock`, `setPrivacy`, `refresh` (pulls live info from registrar)
- **Client `DomainController`** — `index`, `show`, `setNameservers`, `toggleAutoRenew`, `checkAvailability` (JSON; used by checkout)
- **Admin Domains pages** — `Admin/Domains/Index.vue` (searchable, status filter, paginated), `Admin/Domains/Show.vue` (metadata, lock/privacy toggles, nameserver editor)
- **Client Domains pages** — `Client/Domains/Index.vue`, `Client/Domains/Show.vue` (auto-renew toggle, inline nameserver editor with up to 6 NS slots)
- **Checkout availability badge** — debounced 600ms live domain availability check in `Checkout.vue` for `domain`-type products; green "Available ✓" / red "Not available" inline indicator
- **Domain record on order** — `OrderController::place()` creates a `Domain` record (status: pending) when `product.type === 'domain'`
- **`domains:renew-expiring` command** — auto-renews active domains with `auto_renew=true` expiring within N days (default 30); scheduled daily at 09:00
- **Routes** — admin: `domains.index/show/nameservers/lock/privacy/refresh`; client: `domains.index/show/nameservers/auto-renew/check`
- **Nav** — Domains item added to both admin and client sidebars
- **`.env.example` + installer template** — `REGISTRAR_DRIVER`, `NAMECHEAP_*`, `ENOM_*` env vars

---

## [0.7.0] — 2026-03-26 — Mailables, cPanel Provisioning & Email Template Editor

### Added
- **`EmailTemplate` model** (`app/Models/EmailTemplate.php`) — `findBySlug()` + `render(field, vars)` method for `{{variable}}` placeholder replacement
- **`TemplateMailable`** (`app/Mail/TemplateMailable.php`) — single queued mailable that loads template by slug, renders HTML (wrapped in branded layout) and plain text, implements `ShouldQueue`
- **7 default email templates** (seeded via `EmailTemplatesSeeder`): `auth.welcome`, `invoice.created`, `invoice.paid`, `invoice.overdue`, `service.activated`, `service.suspended`, `support.reply`
- **Emails wired to triggers:**
  - Registration → `auth.welcome`
  - Order placed → `invoice.created`
  - Admin marks invoice paid → `invoice.paid`
  - Stripe webhook completes → `invoice.paid`
  - `billing:flag-overdue` → `invoice.overdue` per invoice
  - `billing:suspend-overdue` → `service.suspended` per service
  - Admin support reply → `support.reply`
  - Service provisioned → `service.activated`
- **Admin Email Templates UI** (`/admin/email-templates`) — index list with slug, name, subject, status; edit form with variable reference panel, HTML body textarea, plain text fallback, active toggle
- **`CpanelProvisioner`** (`app/Services/CpanelProvisioner.php`) — WHM JSON API v1 client: `createAccount()` (generates username + password, calls `createacct`), `suspendAccount()`, `unsuspendAccount()`, `terminateAccount()`; uses Bearer token auth; `findAvailableModule()` selects active cPanel module with capacity
- **`provisioning:run` command** — finds pending cPanel services whose invoices are paid, provisions via `CpanelProvisioner`, updates service (username, `password_enc`, `server_hostname`, `module_data`), increments module account count, sends `service.activated` email
- **Scheduler** — `provisioning:run` added at `everyFiveMinutes()` with `withoutOverlapping()`

---

## [0.6.0] — 2026-03-26 — Orders, PDF Export & Billing Automation

### Added
- **`barryvdh/laravel-dompdf ^3.1`** added to `composer.json`
- **PDF Invoice Export** — `GET /admin/invoices/{invoice}/download` and `GET /client/invoices/{invoice}/download` stream a styled A4 PDF; client route ownership-checked (403); admin and client invoice show pages both have Download PDF buttons
- **Invoice PDF template** (`resources/views/pdf/invoice.blade.php`) — branded header with app name/URL, bill-to/from parties, dates, line-items table, totals with tax and credit rows, payment history section, footer with contact email
- **Client Order Catalog** (`/client/order`) — product grid with type badge, price, billing cycle, setup fee, and Order Now link; added to client nav
- **Client Checkout** (`/client/order/checkout`) — order summary card + domain field (shown for hosting/domain/VPS product types) + place order button; validates product, billing cycle, optional domain
- **`Client/OrderController`** — `catalog()`, `checkout()` (GET with query params), `place()` (POST; wraps full flow in a DB transaction: creates Order → OrderItem → Service → Invoice → InvoiceItems); redirects to new invoice for immediate payment
- **Billing Automation Commands**
  - `billing:generate-invoices --days=14` — generates renewal invoices for active services due within N days, skipping services that already have an open unpaid invoice for the cycle
  - `billing:flag-overdue` — marks unpaid invoices past their due date as `overdue`
  - `billing:suspend-overdue --grace=3` — suspends active services with invoices overdue beyond the grace period
- **Scheduler** (`routes/console.php`) — all three billing commands registered: generate-invoices at 08:00, flag-overdue at 00:05, suspend-overdue at 01:00; all run `withoutOverlapping()->runInBackground()`

---

## [0.5.1] — 2026-03-26 — PayPal Payment Integration

### Added
- **`srmklive/paypal ^3.0`** added to `composer.json`
- **PayPal Checkout** — `POST /client/invoices/{invoice}/paypal` creates a PayPal Orders v2 order and returns the buyer approval URL; client is redirected to PayPal-hosted approval page
- **`Client/PayPalPaymentController`** — `checkout()` creates order with `CAPTURE` intent; `return()` captures payment on buyer return and marks invoice paid; `cancel()` marks pending payment failed and redirects with error message
- **PayPal return/cancel routes** — `GET /client/invoices/{invoice}/paypal/return` and `/cancel` handle post-approval flow; ownership-checked (403)
- **Invoice Show** — payment section now offers both Card (Stripe) and PayPal buttons side by side; both show loading spinners; only one can be active at a time; flash messages from PayPal redirect displayed at top
- **`config/services.php`** — `paypal` block with `client_id`, `client_secret`, `mode`, `currency`
- **`.env.example`** — `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_MODE`, `PAYPAL_CURRENCY`
- **Installer `.env` template** — payment gateway placeholders (Stripe + PayPal) written during web install

---

## [0.5.0] — 2026-03-26 — Stripe Payment Integration

### Added
- **`stripe/stripe-php ^20.0`** added to `composer.json`
- **Stripe Checkout** — `POST /client/invoices/{invoice}/checkout` creates a Stripe Checkout Session and returns a redirect URL; client is redirected to Stripe-hosted payment page; on return, `?paid=1` query param triggers a success banner
- **`Client/PaymentController`** — validates ownership (403), guards against double-payment, builds Checkout Session with line item from `invoice.amount_due`, stores a `pending` Payment record with the Stripe session ID for webhook reconciliation
- **Stripe Webhook** (`POST /stripe/webhook`) — `StripeWebhookController` handles `checkout.session.completed` (marks Payment completed, marks Invoice paid) and `checkout.session.expired` (marks Payment failed); signature verified via `STRIPE_WEBHOOK_SECRET`; route exempt from CSRF verification
- **`Client/Invoices/Show`** — Pay Now button triggers Stripe redirect with loading spinner; success banner on return from Stripe; credit applied row in totals; payment history list at bottom of invoice
- **`config/services.php`** — `stripe` block with `key`, `secret`, `webhook_secret`, `currency`
- **`.env.example`** — `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CURRENCY`, `VITE_STRIPE_KEY`

### Security
- Webhook endpoint verified with `Stripe\Webhook::constructEvent()` — rejects requests with invalid signatures (400)
- Invoice ownership checked before creating checkout session (403)
- Double-payment guarded: aborts 422 if invoice status is already `paid`

---

## [0.4.0] — 2026-03-26 — Services Detail & Announcements

### Added
- **Admin Services/Show** (`/admin/services/{service}`) — service detail page: service metadata (product, domain, billing cycle, amount, dates), client card with link to client show, context-sensitive action buttons (suspend/reactivate/terminate with confirmation), provisioning block (username, server hostname:port, shown only when populated), notes panel, full invoice history table with status badges
- **Services Index → Show links** — domain column in Services Index now links to the show page
- **Announcements admin CRUD** (`/admin/announcements`) — list with published/draft badges; create and edit form (title, body textarea, publish toggle); soft-delete via destroy
- **Client Announcements** (`/client/announcements`) — paginated list of published announcements sorted by publish date; clean article layout with date header
- **`AnnouncementController` (Admin)** — full CRUD; sets `published_at` to `now()` on first publish, clears it on unpublish
- **`AnnouncementController` (Client)** — invokable; returns only published announcements ordered by `published_at` desc
- **AppLayout nav** — Announcements added to both admin nav and client nav with megaphone icon

---

## [0.3.0] — 2026-03-26 — Browser-Based Installer

### Added
- **Web Installer** (`/install`) — complete 7-step browser wizard requiring no CLI or shell access; designed for hosting resellers who deploy via FTP/File Manager
  - **Step 1 — Welcome** — introduction and requirements overview
  - **Step 2 — Requirements Check** — live server check: PHP ≥8.3, PDO, PDO MySQL, mbstring, OpenSSL, Tokenizer, JSON, Ctype, BCMath; directory writability (storage/, bootstrap/cache/, .env); all checks displayed with pass/fail indicators
  - **Step 3 — Database** — host, port, name, username, password fields; "Test Connection" button performs raw PDO connect with 5-second timeout and returns MySQL version on success before allowing progression
  - **Step 4 — Site Configuration** — app name and app URL
  - **Step 5 — Admin Account** — admin name, email, password (min 8 chars), password confirmation with client-side validation
  - **Step 6 — Installing** — animated spinner while install runs; calls `/install/run` which executes the full pipeline server-side
  - **Step 7 — Complete** — success screen with direct link to login
- **`InstallerController`** (`app/Http/Controllers/Install/InstallerController.php`) — four endpoints:
  - `GET /install` — renders `Install/Welcome`
  - `GET /install/requirements` — returns JSON of all 12 server checks with pass/fail/detail
  - `POST /install/test-database` — validates input, opens raw PDO connection, returns MySQL version
  - `POST /install/run` — full install pipeline: writes `.env`, live-patches running DB config (`DB::purge` + `DB::reconnect`), runs `migrate --force`, seeds `RolesAndPermissionsSeeder`, creates super-admin user, caches config+routes, writes `storage/installed.lock`
- **`.env` generation** — `APP_KEY` generated with `base64_encode(random_bytes(32))`; file written with `chmod 0600`; `APP_ENV=production`, `APP_DEBUG=false`, `APP_INSTALLED=true` set automatically
- **`CheckInstalled` middleware** (`app/Http/Middleware/CheckInstalled.php`) — redirects to `/install` if `storage/installed.lock` is absent; returns 403 if lock exists and `/install` is accessed again; appended to the web middleware stack in `bootstrap/app.php`
- **Installer route group** — added at top of `routes/web.php` before all auth routes; no auth or verified middleware applied

### Security
- `.env` written with `chmod 0600` (owner-read-only)
- Installer locked permanently via `storage/installed.lock` after first successful run
- Direct PDO test with 5-second timeout prevents the installer from writing `.env` with invalid credentials

---

## [0.2.0] — 2026-03-26 — Auth & Multi-Role Access

### Added
- **Login / Logout** — rate-limited (5 attempts per email+IP), session regeneration
- **Client Registration** — self-service signup; new accounts are auto-assigned the `client` role
- **Password Reset** — forgot-password email flow with signed tokens
- **TOTP 2FA** — enable (QR code via BaconQrCode + Google2FA), confirm with first OTP, disable; login intercepted for users with confirmed 2FA and routed through challenge page
- **Email Verification** — enforced on dashboard and all profile/portal routes; resend throttled at 6/min; signed URL verification
- **Session Management** — list all active sessions (device/browser/IP/last active), revoke individual sessions, revoke-all-others (cross-user isolation enforced)
- **OAuth2** — Google and Microsoft sign-in via Laravel Socialite; find-or-create with `client` role assignment
- **Roles & Permissions** — spatie/laravel-permission; roles: `super-admin`, `admin`, `staff`, `client`; seeder creates all four roles + default `admin@strata.local` super-admin
- **User Model** — `HasRoles` trait, 2FA fillable/casts (`two_factor_secret`, `two_factor_enabled`, `two_factor_confirmed_at`), `credit_balance`, `isAdmin()` / `isClient()` helpers, all billing relationships
- **Profile — Security** (`/profile/security`) — 2FA management UI (QR scan + plain key fallback, confirm, disable, status banners)
- **Profile — Sessions** (`/profile/sessions`) — active session list with revoke buttons
- **GuestLayout.vue** — centered dark-card layout for all auth pages
- **Auth Vue Pages** — Login (with Google/Microsoft OAuth buttons), Register, ForgotPassword, ResetPassword, VerifyEmail, TwoFactorChallenge
- **Pest Tests** — authentication, password reset, TOTP 2FA lifecycle, email verification, session management (26 tests across 5 files)

### Changed
- `AuthenticatedSessionController::store` — intercepts login for 2FA users; stores `two_factor_login_id` in session before completing auth
- `HandleInertiaRequests` — shares `auth.user` (with roles loaded) and `flash` props globally
- `AppLayout` — logout button in user footer; role-aware navigation (admin nav vs client nav); Settings section with Security + Sessions links
- `routes/web.php` — restructured into guest / OAuth / 2FA-challenge / auth / auth+verified groups

### Migrations
- `add_two_factor_to_users_table` — `two_factor_secret`, `two_factor_enabled`, `two_factor_confirmed_at`
- `create_permission_tables` — spatie/laravel-permission tables

---

## [0.1.1] — 2026-03-26 — Complete Framework Scaffold

### Added
**Database (11 new migrations)**
- `products` — name, type (shared/reseller/vps/dedicated/domain/ssl/other), price, setup_fee, billing_cycle, module, module_config (JSON), stock, hidden, taxable, sort_order; soft deletes
- `services` — user_id, product_id, domain, status lifecycle (pending/active/suspended/cancelled/terminated), amount, billing_cycle, registration_date, next_due_date, termination_date, provisioning fields (username, password_enc, server_hostname, server_port, module_data); indexes on user_id+status and next_due_date; soft deletes
- `orders` + `order_items` — promo code, discount, per-item billing cycle
- `invoices` + `invoice_items` — tax_rate, credit_applied, amount_due, date, due_date, paid_at
- `payments` — gateway (stripe/paypal/bank_transfer/credit/manual), transaction_id (unique), gateway_response (JSON, hidden)
- `domains` — registrar, status, registered_at, expires_at, auto_renew, locked, privacy, 4× nameserver fields, registrar_data (JSON); indexes on user_id+status and expires_at
- `support_tickets` + `support_replies` — department, priority, assigned_to, last_reply_at; soft deletes
- `modules` — server type (cpanel/plesk/directadmin/vestacp/cyberpanel/generic), hostname, port, ssl, api_token_enc, password_enc (both hidden), max_accounts, current_accounts
- `client_credits` — append-only credit ledger; `credit_balance` column added to users
- `email_templates` — slug (unique), subject, body_html, body_plain, active
- `announcements` — published flag, published_at; soft deletes

**Models (12 new)**
`Product`, `Service`, `Order`, `OrderItem`, `Invoice`, `InvoiceItem`, `Payment`, `Domain`, `SupportTicket`, `SupportReply`, `Module`, `Announcement` — all with fillable, casts, relationships, soft deletes where applicable; encrypted fields hidden

**Admin Panel** (`/admin/*` — requires `auth + verified + admin` middleware)
- `DashboardController` — stats (total clients, active services, open/overdue invoices, open tickets, MRR); recent orders + tickets
- `ClientController` — index (search, paginated, service/invoice counts), show (with services/invoices/tickets/domains), create, store, update, suspend (bulk-suspends services)
- `ProductController` — full CRUD with validation
- `ServiceController` — index (search + status filter), show, suspend / unsuspend / terminate
- `InvoiceController` — index (search + status filter), show, create (line-item builder), store, markPaid, cancel
- `SupportController` — index (search + status + priority filter, priority-ordered), show, reply (sets status → answered), assign, close
- `ModuleController` — full CRUD; encrypts API token on store/update

**Client Portal** (`/client/*` — requires `auth + verified`)
- `DashboardController` — personalised stats + services due ≤30 days + unpaid invoices + recent tickets
- `ServiceController` — index, show (ownership enforced with 403)
- `InvoiceController` — index, show (ownership enforced with 403)
- `SupportController` — index, create, store (opens ticket + first reply), show, reply (sets status → customer_reply)

**Vue Pages (30 new)**
- Admin: `Dashboard`, `Clients/Index`, `Clients/Show`, `Clients/Create`, `Products/Index`, `Products/Form`, `Services/Index`, `Invoices/Index`, `Invoices/Show`, `Invoices/Create` (dynamic line-item builder), `Support/Index`, `Support/Show`, `Modules/Index`, `Modules/Form`
- Client: `Dashboard`, `Services/Index`, `Services/Show`, `Invoices/Index`, `Invoices/Show` (Pay Now stub), `Support/Index`, `Support/Create`, `Support/Show`
- Auth: `Register`
- Shared component: `StatusBadge` — unified status/priority colour mapping for all list views

**Infrastructure**
- `EnsureIsAdmin` middleware — 403 for non-admin roles; registered as `admin` alias in `bootstrap/app.php`
- `AppLayout` — role-aware navigation: admins see admin panel links; clients see portal links; computed from `auth.user.roles`
- `HandleInertiaRequests` — loads `user.roles` relation in shared `auth` prop
- `laravel/socialite ^5.0` added to `composer.json`

---

## [0.1.0] — 2026-03-25 — Project Foundation

### Added
- **Laravel 12** skeleton with `composer.json` configured for PHP 8.3+
- **Inertia.js v2** + Vue 3 + Vite 8 wiring (`app.js`, `app.blade.php`, `HandleInertiaRequests` middleware, `vite.config.js`)
- **Tailwind CSS v4** via `@tailwindcss/vite`
- **Ziggy** route helper (`@routes` directive + `ZiggyVue` plugin)
- **Laravel Horizon** (queue worker, `horizon` Docker service)
- **Laravel Scout** + Meilisearch service in Docker
- **Docker Compose** — 6 services: `app` (php-fpm), `nginx` (port 8080), `mysql` (healthcheck), `redis`, `horizon`, `meilisearch` (port 7700)
- `docker/nginx/default.conf` — FastCGI pass, gzip, static asset caching
- `docker/php/local.ini` — dev settings (`display_errors=On`, `memory_limit=256M`, `opcache.enable=0`)
- **GitHub Actions CI** (`.github/workflows/tests.yml`) — PHP 8.3, MySQL 8 + Redis 7 services; Pest + Pint jobs
- **AppLayout.vue** — dark sidebar, flash messages, mobile hamburger
- **Dashboard.vue** — 4-stat grid placeholder
- `routes/web.php` — initial dashboard route
- `README.md` — pre-release banner, feature tiers, status table, feature request email
- `LICENSE.md` — FSL-1.1-Apache-2.0 with premium modules addendum
- `.github/ISSUE_TEMPLATE/feature_request.md`

### Stack
| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.3 |
| Frontend | Vue 3, Inertia.js v2, Tailwind CSS v4 |
| Queue | Laravel Horizon + Redis |
| Search | Laravel Scout + Meilisearch |
| Testing | Pest + Pint |
| Infrastructure | Docker Compose |

---

*Strata is pre-release software. Feature requests: Jonathan.r.covington@gmail.com*
