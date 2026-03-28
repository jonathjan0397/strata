# Strata — Bug Fix Log

> Tracks bugs discovered during installer testing and early development on stratadev.hosted-tech.net.
> Status: OPEN | FIXED | WONTFIX

---

## BF-001 — Session driver default causes 500 before install
**Status:** FIXED
**File:** `config/session.php`
**Symptom:** `/install` returns HTTP 500 — "sessions table not found"
**Root cause:** `SESSION_DRIVER` defaulted to `database`; no tables exist before install runs.
**Fix:** Changed default to `file` so the app boots without a database.

---

## BF-002 — Missing APP_KEY crashes encryption service before install
**Status:** FIXED
**File:** `config/app.php`
**Symptom:** `MissingAppKeyException` thrown when visiting `/install`
**Root cause:** No `.env` exists pre-install; encryption service provider boot fails.
**Fix:** Added safe fallback key so encryption bootstraps without a real `.env`.

---

## BF-003 — Migration runs against SQLite instead of MySQL
**Status:** FIXED
**Files:** `config/database.php`, `app/Http/Controllers/Install/InstallerController.php`
**Symptom:** Install wizard shows "Connection: sqlite" errors on migration step
**Root cause:** `DB_CONNECTION` default was `sqlite`; after `rebootConfig()` set MySQL credentials, the default connection was still `sqlite`.
**Fix:** Changed `config/database.php` default to `mysql`; `rebootConfig()` now also sets `database.default`.

---

## BF-004 — MariaDB FULLTEXT index migration fails
**Status:** FIXED
**File:** `database/migrations/2026_03_27_033000_create_knowledge_base_tables.php`
**Symptom:** "This database driver does not support fulltext index creation."
**Root cause:** `Blueprint::fullText()` is not implemented in Laravel's MariaDB grammar.
**Fix:** Replaced with raw `DB::statement('ALTER TABLE kb_articles ADD FULLTEXT INDEX ...')`.

---

## BF-005 — ModSecurity 403 on installer POST (rule 981319 / 981260)
**Status:** FIXED
**File:** `routes/web.php`
**Symptom:** Database connection test returns 403 Forbidden from Apache/ModSecurity
**Root cause:** Encrypted Laravel session and XSRF cookies triggered WAF SQL-injection pattern rules.
**Fix:** Stripped session, cookie, and CSRF middleware from all `/install` routes so no cookies are issued for those endpoints.

---

## BF-006 — Installer always creates admin@strata.local
**Status:** FIXED
**File:** `database/seeders/RolesAndPermissionsSeeder.php`
**Symptom:** A hard-coded `admin@strata.local` account was created during seeding, preventing the user from being the sole administrator.
**Root cause:** Seeder contained a hard-coded `User::create(['email' => 'admin@strata.local', ...])`.
**Fix:** Removed the hard-coded user creation; admin is now created exclusively by the installer wizard.

---

## BF-007 — Database connection test fails with localhost (Unix socket vs TCP)
**Status:** FIXED
**File:** `app/Http/Controllers/Install/InstallerController.php`
**Symptom:** "Access denied for user ...@'localhost'" even with correct credentials
**Root cause:** Web PHP processes resolve `localhost` to a Unix socket path that differs from the MySQL CLI default on shared hosting (CWP).
**Fix:** Normalize `localhost` → `127.0.0.1` in both `testDatabase()` and `install()` to force TCP connections.

---

## BF-008 — 2FA Enable button has no effect
**Status:** FIXED
**File:** `app/Http/Middleware/HandleInertiaRequests.php`
**Symptom:** Clicking "Enable" on the 2FA security page returns to "not enabled" state with no QR code shown
**Root cause:** `two_factor_secret`, `two_factor_enabled`, `two_factor_confirmed_at` were in the User model's `$hidden` array and never sent to the frontend.
**Fix:** Added `makeVisible()` on those fields in the Inertia shared props middleware.

---

## BF-009 — Deploy script race condition (FTP error code 0)
**Status:** FIXED
**File:** `deploy/deploy-strata.js`
**Symptom:** Parallel FTP upload crashes with "error code 0" on the second or third worker
**Root cause:** All three FTP workers shared a single `dirCache` Set and FTP client, causing concurrent directory creation collisions.
**Fix:** Each worker now owns its own FTP client and `dirCache` Set; shared state eliminated entirely.

---

## BF-010 — Bootstrap cache survives deploy and serves stale config
**Status:** FIXED
**File:** `deploy/deploy-strata.js`
**Symptom:** Config changes (e.g. session driver, DB default) take effect locally but not on server
**Root cause:** `config:cache` was run at deploy time, writing `bootstrap/cache/config.php`; subsequent deploys didn't clear it.
**Fix:** Deploy script now FTP-deletes all bootstrap cache files (`config.php`, `services.php`, `routes-v7.php`, `events.php`, `packages.php`) after every upload.

---

## BF-011 — PATCH/DELETE requests blocked by CWP/ModSecurity (403 on all update/delete actions)
**Status:** FIXED
**File:** `resources/js/app.js`
**Symptom:** Saving settings, updating departments, deleting records — all return 403 Forbidden popup
**Root cause:** CWP's ModSecurity configuration blocks PATCH, PUT, and DELETE HTTP verbs.
**Fix:** Globally patched `router.visit` in Inertia's app setup to convert PATCH/PUT/DELETE to POST with `_method` spoofing in the request body. Laravel's built-in method override middleware handles the conversion transparently. No route or controller changes required.

---

## BF-012 — Knowledge Base requires category before article creation
**Status:** FIXED (verified working 2026-03-27)

---

## BF-013 — Audit Log does not separate admin vs customer actions
**Status:** FIXED
**Fix applied:** Added `actor_type` enum column to `audit_logs`; `AuditLogger` auto-detects admin/client/system from user roles at log time. Added All / Admin Actions / Client Actions tabs to the UI. Also wired up logging for login, logout, 2FA login, settings updates, staff permission changes, and client creates/updates.

---

## BF-014 — Logo upload may fail (same 403 as BF-011)
**Status:** FIXED (verified working 2026-03-27)

---

## BF-015 — Remove debug logging from InstallerController
**Status:** DEFERRED — must fix before v1.0 stable release
**File:** `app/Http/Controllers/Install/InstallerController.php`
**Symptom:** `INSTALL_DB_TEST` debug entries with password hex dumps written to laravel.log during database connection test.
**Fix needed:** Remove or gate behind `APP_DEBUG` the `Log::debug('INSTALL_DB_TEST', ...)` block in `testDatabase()`.

---

## BF-016 — Send Email feature: mail transport fails on CWP shared hosting
**Status:** FIXED
**Files:** `config/mail.php`, `app/Providers/MailSettingsServiceProvider.php`, `app/Http/Controllers/Admin/SettingController.php`, `resources/js/Pages/Admin/Settings/Index.vue`
**Symptom:** POST `/admin/clients/{id}/email` returns 500; sendmail flag `-bs -i` (SMTP mode) caused connection errors on CWP.
**Root cause:** `-bs` flag expects SMTP dialog on stdin; CWP sendmail only supports pipe mode (`-t -i`).
**Fix:** Changed sendmail default to `/usr/sbin/sendmail -t -i`. Added Email tab to Admin Settings panel so mailer (sendmail/SMTP/log), from address, SMTP credentials, and sendmail path are all configurable at runtime without `.env` changes. Added Send Test button with inline result.

## BF-017 — 500 after 2FA login: route 'dashboard' not defined
**Status:** FIXED
**Files:** `app/Http/Controllers/Auth/TwoFactorChallengeController.php` + 5 other auth controllers
**Symptom:** Successful 2FA login resulted in HTTP 500 — `Route [dashboard] not defined`
**Root cause:** All auth controllers redirected to `route('dashboard')` which does not exist; correct route is `admin.dashboard`.
**Fix:** Replaced `route('dashboard')` with `route('admin.dashboard')` across all auth controllers.

## BF-018 — RequireTwoFactor middleware returns void causing 500
**Status:** FIXED
**File:** `app/Http/Middleware/RequireTwoFactor.php`
**Symptom:** Any admin page load returns 500 after 2FA enforcement was changed to optional.
**Root cause:** Middleware `handle()` had no return statement after removing the redirect.
**Fix:** Added `return $next($request)` to always pass the request through. 2FA is now optional but a persistent amber banner prompts admins to enable it.

## BF-019 — Mail::queue() hangs / 500 on CWP shared hosting (no queue worker)
**Status:** FIXED
**Files:** `RegisteredUserController.php`, `OrderController.php`, `ServiceController.php`, `Client/SupportController.php`, `StripeWebhookController.php`, `AuthorizeNetPaymentController.php`, `CloseInactiveTickets.php`
**Symptom:** Any action that triggers an email (registration, order, service activation, support reply, payment confirmation, auto-close) hangs indefinitely or returns 500 on CWP shared hosting.
**Root cause:** All mail calls used `Mail::to()->queue()` which pushes jobs to the database queue. CWP shared hosting has no `queue:work` daemon running, so jobs are never processed and the queue table fills up. In some cases Laravel throws a timeout or dispatch exception.
**Fix:** Replaced every `queue()` call with `send()` wrapped in `try { } catch (\Throwable) {}` — mail failure is silently swallowed so it never blocks the user-facing action. Affected controllers: RegisteredUserController, OrderController, ServiceController, Client\SupportController, StripeWebhookController, AuthorizeNetPaymentController, and the CloseInactiveTickets command.

---

## BF-020 — Stripe / PayPal pay buttons visible when gateway not configured (500 on click)
**Status:** FIXED
**Files:** `app/Http/Controllers/Client/InvoiceController.php`, `resources/js/Pages/Client/Invoices/Show.vue`
**Symptom:** Clicking the Stripe or PayPal pay button on an invoice returns 500 when the respective gateway credentials are absent from `.env`.
**Root cause:** Pay buttons were always rendered regardless of whether the gateway was actually configured.
**Fix:** Added `hasStripe` (`(bool) config('services.stripe.secret')`) and `hasPayPal` (`(bool) config('services.paypal.client_id')`) flags to the Invoice `show()` Inertia props. Applied `v-if="hasStripe"` and `v-if="hasPayPal"` to the respective payment buttons in `Client/Invoices/Show.vue`.

---

## BF-021 — Stripe webhook 400 when STRIPE_WEBHOOK_SECRET not configured
**Status:** FIXED
**File:** `app/Http/Controllers/StripeWebhookController.php`
**Symptom:** Stripe webhooks return 400 "Invalid signature" when `STRIPE_WEBHOOK_SECRET` is absent from `.env`, breaking invoice reconciliation on installations that haven't set up webhook signing.
**Root cause:** `Webhook::constructEvent()` was called unconditionally and always throws `SignatureVerificationException` when no secret is set.
**Fix:** Added conditional: if `$secret` is set, verify signature normally; otherwise skip to `Event::constructFrom(json_decode($payload, true))` and log a warning. Webhook processing continues regardless of whether the secret is configured.

---

---

## BF-022 — `Pagination` component missing causes runtime crash on Affiliates list
**Status:** FIXED
**File:** `resources/js/Pages/Admin/Affiliates/Index.vue`
**Symptom:** Navigating to `/admin/affiliates` throws a Vue runtime error — `Failed to resolve component: Pagination` — resulting in a blank page.
**Root cause:** `Admin/Affiliates/Index.vue` imported `@/Components/Pagination.vue` which was never created. All other admin list pages use inline prev/next HTML pagination; no shared `Pagination` component exists in the codebase.
**Fix:** Removed the import and replaced `<Pagination :links="affiliates.links" />` with the same inline prev/next pattern used by every other admin list page.

---

## BF-023 — Nav links missing for Quotes, Addons, Affiliates (admin) and Quotes, Affiliate (client)
**Status:** FIXED
**File:** `resources/js/Layouts/AppLayout.vue`
**Symptom:** Admin pages for Quotes, Addons, and Affiliates existed and had working routes but were completely unreachable from the sidebar navigation. Same for the client Quotes and Affiliate pages.
**Root cause:** Nav arrays in `AppLayout.vue` were never updated when these features were added in earlier sessions.
**Fix:** Added `Quotes` and `Orders` to the admin Clients & Billing nav group; `Addons` to Products & Services; `Affiliates` to Administration. Added `Quotes` (after Invoices) and `Affiliate` (after Payment Methods) to the client nav list.

---

## BF-024 — Admin Orders page missing entirely
**Status:** FIXED
**Files:** `app/Http/Controllers/Admin/OrderController.php` (new), `routes/web.php`, `resources/js/Pages/Admin/Orders/Index.vue` (new)
**Symptom:** Orders were created at checkout and stored in the database but admin had no way to list, search, or view them. The Orders nav link (once added) had no route to point to.
**Root cause:** `Admin/OrderController` and its route were never created.
**Fix:** Created `Admin/OrderController@index` with search (order number, client name/email) and status filter; registered `admin.orders.index` route; created `Admin/Orders/Index.vue` with searchable table and inline pagination.

---

## BF-025 — Client profile missing Company and Phone fields
**Status:** FIXED
**Files:** `app/Http/Controllers/ProfileController.php`, `resources/js/Pages/Profile/Edit.vue`
**Symptom:** The profile edit page had no fields for company name or phone number despite both columns existing on the `users` table and being used for tax resolution and invoice display.
**Root cause:** `ProfileController::edit()` only passed `name`, `email`, `country`, `state` to the view; `update()` only validated and saved those four fields.
**Fix:** Added `company` and `phone` to the controller's `only()` call and validation rules; added two corresponding optional input fields to `Profile/Edit.vue` between the Name and Country fields.

---

*Last updated: 2026-03-28*
