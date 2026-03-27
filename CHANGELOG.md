# Changelog

All notable changes to Strata are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Planned (next priorities)
- Stripe payment gateway integration
- Client-facing order / checkout flow
- Service auto-provisioning (cPanel/WHM API)
- PDF invoice export (`barryvdh/laravel-dompdf`)
- Billing automation cron jobs (invoice generation, overdue detection, suspension)
- Domain registration API (Enom / OpenSRS / Namecheap)
- Admin Services/Show page
- Mailable classes wired to EmailTemplates
- Announcements admin UI + client display

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
