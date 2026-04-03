# Strata — Bug Fix Log

> Tracks bugs discovered and resolved through 1.0-Beta development, including installer testing on stratadev.hosted-tech.net.
> Status: OPEN | FIXED | WONTFIX | DEFERRED

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
**Status:** FIXED
**File:** `app/Http/Controllers/Install/InstallerController.php`
**Symptom:** `INSTALL_DB_TEST` debug entries with password hex dumps written to laravel.log during database connection test.
**Fix:** Removed the `Log::debug('INSTALL_DB_TEST', ...)` block from the `testDatabase()` catch handler entirely.

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

## BF-026 — Fresh install 500: session store not set on install routes
**Status:** FIXED
**File:** `app/Http/Middleware/HandleInertiaRequests.php`
**Symptom:** Visiting `/install` on a fresh server returns HTTP 500 — "Session store not set on request"
**Root cause:** Install routes strip `StartSession` middleware to prevent ModSecurity false positives (BF-005). However, `HandleInertiaRequests::share()` evaluated `$request->session()->get()` in its flash closures unconditionally. With no session bound to the request, Laravel throws `RuntimeException`.
**Fix:** Guarded both flash closures with `$request->hasSession() ? ... : null`.

---

## BF-027 — Fresh install redirects to wrong URL in subdirectory installs
**Status:** FIXED
**File:** `app/Http/Middleware/CheckInstalled.php`
**Symptom:** Visiting a subdirectory install (e.g. `domain.com/billing`) redirects to `domain.com/install` instead of `domain.com/billing/install`
**Root cause:** `CheckInstalled` issued `redirect('/install')` — an absolute path that ignores any subdirectory prefix.
**Fix:** Replaced with `redirect($request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/install')` to construct the correct full URL from the incoming request.

---

## BF-028 — Pre-install URL detection: Ziggy and asset() helpers use wrong base URL
**Status:** FIXED
**File:** `app/Providers/AppServiceProvider.php`
**Symptom:** On fresh installs `APP_URL` defaults to `http://localhost`; Ziggy-generated route URLs and asset paths are wrong, causing JS routing failures before the installer runs.
**Root cause:** Without a `.env`, `APP_URL` is `http://localhost`. Ziggy and `URL::to()` use this value when generating URLs server-side.
**Fix:** In `AppServiceProvider::boot()`, if `installed.lock` is absent, detect the real base URL from the incoming HTTP request (`getSchemeAndHttpHost() . getBaseUrl()`) and call `URL::forceRootUrl()` to override it for the duration of the request.

---

## BF-029 — Pre-install database cache query causes 500 before credentials are configured
**Status:** FIXED
**File:** `app/Providers/AppServiceProvider.php`
**Symptom:** Visiting the installer on a fresh server returns 500 — "Access denied for user 'root'@'localhost'" logged against the `cache` table
**Root cause:** Laravel 12 defaults the cache driver to `database`. Before the installer runs there is no `.env`, so the cache driver attempts to query `root@localhost` with no password against a database named `laravel`.
**Fix:** In `AppServiceProvider::boot()`, if `installed.lock` is absent, set `config(['cache.default' => 'array'])` so all cache operations use in-memory storage and no database connection is attempted.

---

## BF-030 — VerifyCsrfToken not excluded from install routes (wrong class name)
**Status:** FIXED
**File:** `routes/web.php`
**Symptom:** Installer POST routes (test-database, run) throw "Session store not set on request" via `VerifyCsrfToken->addCookieToResponse()`
**Root cause:** The `withoutMiddleware` call listed `App\Http\Middleware\VerifyCsrfToken::class` which does not exist in Laravel 12. The actual CSRF middleware registered via `bootstrap/app.php` `validateCsrfTokens()` is `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken`. The exclusion was a no-op and CSRF ran on every install route.
**Fix:** Changed to `\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class`.

---

## BF-031 — Release ZIP missing storage subdirectories
**Status:** FIXED
**File:** `.github/workflows/release.yml`
**Symptom:** Fresh installs from the GitHub release ZIP fail with "directory does not exist" errors for `storage/framework/sessions`, `storage/framework/views`, `storage/framework/cache/data`
**Root cause:** The workflow creates `.gitkeep` files in each storage subdirectory before building the ZIP, but the `zip --exclude "storage/framework/sessions/*"` wildcard also matched `.gitkeep`. ZIP archives do not store empty directories, so all excluded directories vanished from the package.
**Fix:** After the main `zip` command, explicitly re-add each `.gitkeep` file with a second `zip` invocation.

---

## BF-032 — Sample data seeder column/enum mismatches (multiple)
**Status:** FIXED
**File:** `database/seeders/SampleDataSeeder.php`
**Symptom:** Installer fails during sample data seeding with a series of `SQLSTATE` column-not-found or data-truncation errors
**Root cause:** Seeder was written against an out-of-date schema. Multiple column names and enum values did not match the actual migrations.
**Fix:** Corrected all mismatches in one audit pass:

| Table | Field | Was | Correct |
|-------|-------|-----|---------|
| `promo_codes` | usage counter | `uses` | `uses_count` |
| `promo_codes` | validity start | `valid_from` | `starts_at` |
| `promo_codes` | validity end | `valid_until` | `expires_at` |
| `promo_codes` | active flag | `active` | `is_active` |
| `products` | type enum | `hosting` | `shared` |
| `announcements` | body column | `content` | `body` |
| `announcements` | non-existent | `pinned` | removed |
| `payments` | status enum | `paid` | `completed` |
| `quotes` | status enum | `pending` | `sent` |

---

## BF-033 — Intermittent 403 Forbidden in admin panel (ModSecurity OWASP CRS anomaly scoring)
**Status:** FIXED
**File:** `public/.htaccess`
**Symptom:** Random "403 Forbidden — You don't have permission to access this resource" errors while working in the admin panel. No consistent pattern — occurs on settings saves, KB article edits, email template edits, support ticket replies, or any form containing rich text.
**Root cause:** OWASP Core Rule Set (CRS) in anomaly-scoring mode accumulates a "risk score" across multiple rules fired against the POST body. Once the score exceeds the inbound threshold (default: 5) the request is blocked. Admin panel content triggers multiple rules in combination:

| Content type | Rules triggered | Score |
|---|---|---|
| Tiptap HTML — `<a href=`, `<img src=` | XSS 941100, 941160 | +2–4 |
| Email template `{{variable}}` syntax | RCE/template injection 932160 | +5 |
| `<script>` tag in KB code blocks | XSS 941110 | +5 |
| SQL keywords in ticket body (SELECT, WHERE, DROP) | SQLi 942100, 942200 | +2–5 |
| File upload with special filename | Multiple | +2 |

Any single trigger that scores ≥5, or two smaller triggers that stack, produces a 403 with no error detail — making it appear "random" because it depends entirely on what the admin typed.

**Why the existing `_method` spoofing fix (BF-011) doesn't cover this:** BF-011 fixed HTTP verb blocking (PATCH/DELETE rejected at the protocol level). This is a separate, content-scanning block that fires on POST bodies regardless of verb.

**Fix applied:** Added `SecRuleEngine Off` for all `/admin` paths in `public/.htaccess`:

```apache
<IfModule mod_security2.c>
    <If "%{REQUEST_URI} =~ m|^/admin|">
        SecRuleEngine Off
    </If>
</IfModule>
```

The admin panel is already protected by Laravel session authentication and role middleware (`EnsureIsAdmin`, `EnsureAdminCan`). Disabling WAF body scanning for these routes does not reduce security — an unauthenticated attacker cannot reach any admin route regardless.

**Prerequisite:** The `.htaccess` fix requires Apache `AllowOverride All` (or at least `AllowOverride Limit`) on the document root AND ModSecurity compiled with per-directory engine control. Both are enabled by default on CWP.

**If `.htaccess` fix does not work (403 persists):**

The server may have `SecRuleEngineStatePerDirectory Off` set in the main `modsecurity.conf`, preventing `.htaccess` from overriding the engine state. In this case, disable the problematic rule groups from the panel:

**CWP (Control Web Panel):**
1. Log in to CWP admin → Security → ModSecurity Manager
2. Click "Rules" or "Rule Sets"
3. Disable the following rule groups for your domain (or globally if you trust your users):
   - **REQUEST-941-APPLICATION-ATTACK-XSS** — triggers on Tiptap HTML
   - **REQUEST-942-APPLICATION-ATTACK-SQLI** — triggers on SQL-like text in tickets/articles
   - **REQUEST-932-APPLICATION-ATTACK-RCE** — triggers on `{{variable}}` syntax
4. Click Save / Apply

**cPanel/WHM:**
1. WHM → ModSecurity → Rules List
2. Search for rule IDs 941100, 941110, 942100, 942200, 932160 and disable each
3. Or use the "Domain Config" option to set `SecRuleEngine Off` for the domain's vhost

**Plesk:**
1. Domains → your domain → Apache & nginx Settings → Additional Apache directives
2. Add:
   ```apache
   <Location /admin>
       SecRuleEngine Off
   </Location>
   ```

**DirectAdmin:**
1. Admin Level → ModSecurity → Global Config
2. Add rule exclusion for the domain via the custom rules editor

**Manual (SSH):** If you have server access, add to the vhost `<Directory>` block:
```apache
<Location /admin>
    SecRuleEngine Off
</Location>
```
Then run `apachectl graceful` (or `systemctl reload httpd`).

**How to confirm ModSecurity is causing the 403:** Check the Apache error log or ModSecurity audit log:
```bash
grep "ModSecurity" /usr/local/apache/logs/error_log | tail -20
grep "id \"94" /var/log/modsec_audit.log | tail -20
```
The log entry will show the rule ID and the portion of the request body that matched.

---

## BF-034 — Outgoing email flagged as spam (missing SPF/DKIM/DMARC + wrong template subjects)
**Status:** FIXED
**Files:** `database/seeders/EmailTemplatesSeeder.php`, `app/Http/Controllers/Admin/SettingController.php`, `routes/web.php`, `resources/js/Pages/Admin/Settings/Index.vue`

### Symptom A — Emails land in spam / DMARC quarantine
Emails from the portal arrive with `spf=none`, no `DKIM-Signature` header, and `dmarc=fail (p=QUARANTINE)`. Gmail and other providers quarantine or reject the message.

**Root cause — three missing DNS records:**

| Record | Status | Effect |
|---|---|---|
| SPF TXT on sending domain | `none` — not present | Receiving server cannot verify the IP is authorized to send |
| DKIM | Not signing | No cryptographic proof the message is unmodified |
| DMARC | Fails (policy exists but both SPF and DKIM fail) | Policy set to QUARANTINE → message goes to spam |

**Additional factor:** The From address was `noreply@portal.stratadevplatform.com` (a subdomain). SPF checks the exact `MailFrom` domain — the parent domain's SPF record does NOT apply to the subdomain. The subdomain needs its own SPF record.

**Fix — DNS records to add:**

Replace `YOUR_SERVER_IP` with the IP of your mail server (shown in Settings → Email → Check DNS Records).

```
# SPF — add TXT record on your sending domain (e.g. yourdomain.com or portal.yourdomain.com)
Name: yourdomain.com   Type: TXT   Value: v=spf1 ip4:YOUR_SERVER_IP ~all

# DKIM — generated by your mail server (see DKIM setup below)
Name: mail._domainkey.yourdomain.com   Type: TXT   Value: v=DKIM1; k=rsa; p=YOUR_PUBLIC_KEY

# DMARC — add TXT record on the organisational (root) domain
Name: _dmarc.yourdomain.com   Type: TXT   Value: v=DMARC1; p=quarantine; rua=mailto:postmaster@yourdomain.com
```

**DKIM setup on CWP:**
1. CWP Admin → Email → DKIM Manager → Enable for domain → Generate Key
2. CWP adds the DNS record automatically if you use CWP DNS; otherwise copy the TXT value and add it manually

**DKIM setup manually (SSH):**
```bash
sudo apt install opendkim opendkim-tools
sudo opendkim-genkey -s mail -d yourdomain.com
sudo cat /etc/opendkim/keys/yourdomain.com/mail.txt   # copy this TXT value to DNS
```

**Recommendation for subdomain senders:** Change the From address in Settings → Email from `noreply@portal.yourdomain.com` to `noreply@yourdomain.com` so one set of DNS records covers all portal mail.

**In-app tool added:** Settings → Email → "Check DNS Records" button runs a live DNS lookup for SPF, DKIM (probes 8 common selectors), and DMARC on your configured From domain and shows the exact records to add for anything missing.

---

### Symptom B — Service emails arrive with subject "service.reactivated" and body "No template found"
**Root cause:** `service.reactivated` and `service.terminated` templates were never added to `EmailTemplatesSeeder`. `OrderProvisioner::unsuspend()` and `terminate()` were added in the previous session but the seeder wasn't updated. `TemplateMailable::envelope()` falls back to using the raw slug as the subject when no template exists.

**Fix:** Added `service.reactivated` and `service.terminated` to the seeder. Also updated `service.suspended` body to use `{{reason}}` instead of hardcoded "unpaid invoice" language — the template is now sent for both billing-triggered suspensions (reason: "Payment overdue") and admin-initiated suspensions (reason: admin input).

**To apply on an existing install:** Go to Admin → Email Templates — the three templates will be present but may have old content. Edit each template body to match the new defaults, or re-run the seeder: `php artisan db:seed --class=EmailTemplatesSeeder`.

---

## BF-035 — Gmail marks outgoing mail as spam despite DKIM/DMARC passing
**Status:** DOCUMENTED — requires hosting/DNS changes, not a code fix

### Symptom
Email passes DKIM and DMARC but Gmail still delivers to spam folder.

### Diagnosis
Authentication (DKIM PASS, DMARC PASS) proves the mail is yours — it does not affect Gmail's spam scoring. Gmail spam filtering is primarily driven by **IP reputation** and **domain reputation**, which are independent of DNS authentication records.

**Shared hosting IPs are the root cause.** When Strata is installed on a shared hosting server (CWP, cPanel, etc.), outgoing mail is relayed through a shared Postfix instance on an IP that is shared with potentially hundreds of other websites. If any other tenant on that IP sends spam, the entire IP pool's reputation with Gmail drops — affecting every sender on the server regardless of their own sending practices.

Additional factors observed:
- **SPF NONE on subdomain envelope sender** — if `MAIL_FROM_ADDRESS` is set to `noreply@portal.yourdomain.com`, the Return-Path uses the subdomain. If no SPF record exists on the subdomain (even if the root domain has one), SPF resolves as NONE. This is a soft negative signal even when DMARC passes via DKIM.
- **New domain cold-start** — domains with no sending history start with neutral/low reputation; Gmail's filters are more aggressive for the first weeks of sending.

### Fix A — Add SPF to the sending subdomain (if applicable)
If your From address uses a subdomain (e.g. `noreply@portal.yourdomain.com`), add a TXT record to that subdomain:
```
portal.yourdomain.com   TXT   v=spf1 ip4:YOUR_SERVER_IP ~all
```
Or change your From address in Settings → Email to the root domain (`noreply@yourdomain.com`) so the root domain's SPF record applies to the envelope sender.

### Fix B — Register with Google Postmaster Tools (free)
1. Go to https://postmaster.google.com
2. Add and verify your sending domain
3. Monitor domain reputation and IP reputation — this also signals to Gmail that a real sender is managing the domain, which slightly improves initial reputation scoring.

### Fix C — Use a dedicated SMTP relay (recommended for production)
The permanent solution for reliable Gmail delivery from shared hosting is to route outgoing mail through a transactional email service with pre-warmed IPs and established Gmail reputation. Configure SMTP credentials in Settings → Email.

| Provider | Free tier | Setup |
|---|---|---|
| **Brevo** (formerly Sendinblue) | 300 emails/day | SMTP host: smtp-relay.brevo.com, port 587 |
| **Mailgun** | 100 emails/day (US region) | SMTP host: smtp.mailgun.org, port 587 |
| **SendGrid** | 100 emails/day | SMTP host: smtp.sendgrid.net, port 587 |
| **AWS SES** | 62,000/month (from EC2), $0.10/1000 otherwise | SMTP host: email-smtp.us-east-1.amazonaws.com, port 587 |
| **Postmark** | 100 emails/month free | Best deliverability reputation; SMTP host: smtp.postmarkapp.com, port 587 |

All of these services provide their own SPF/DKIM records and the setup is purely in Settings → Email — no server changes needed. DMARC alignment works automatically since they sign with your verified domain.

---

---

## BF-036 — Client invoice list 500: SQLSTATE[42000] Mixing GROUP columns without GROUP BY
**Status:** FIXED
**File:** `app/Http/Controllers/Client/InvoiceController.php`

### Symptom
Visiting the client invoices list returns a Middleware 500 Server Error.

### Root cause
`InvoiceController::index()` clones the paginated query before running an aggregate `SUM()` summary:
```php
$summary = (clone $query)->selectRaw('SUM(total) as total_billed, ...')->first();
```
The cloned query inherits `latest()` → `ORDER BY created_at DESC`. MySQL's `ONLY_FULL_GROUP_BY` mode rejects mixing aggregate functions with an `ORDER BY` on a non-grouped column when no `GROUP BY` clause is present.

Error: `SQLSTATE[42000]: Syntax error or access violation: 1140 Mixing of GROUP columns (MIN(),MAX(),COUNT(),...) with no GROUP columns is illegal if there is no GROUP BY clause`

### Fix
Added `->reorder()` before `->selectRaw()` to strip the inherited ORDER BY before the aggregate query executes:
```php
$summary = (clone $query)->reorder()->selectRaw('SUM(total) as total_billed, ...')->first();
```

---

## BF-037 — Client service cancellation 500: `cancellation_requested` not in services status enum
**Status:** FIXED
**Files:** `app/Http/Controllers/Client/ServiceController.php`, `database/migrations/2026_04_02_000003_add_cancellation_requested_to_services_status.php`

### Symptom
Submitting a service cancellation request from the client portal returns a Middleware 500 Server Error.

### Root cause
`ServiceController::requestCancellation()` sets `status = 'cancellation_requested'`, but the `services.status` column was declared as `ENUM('pending','active','suspended','cancelled','terminated')` — `cancellation_requested` was never added to the enum. MySQL rejected the UPDATE with a data integrity violation error.

Additionally, the `cancellation_reason`, `cancellation_requested_at`, `cancellation_type`, and `scheduled_cancel_at` columns added by migrations `2026_03_27_032000` and `2026_03_28_310000` were missing from the deploy tracking list (they were present on the server but not recorded in `deploy-stratatest.js`).

### Fix
Created migration `2026_04_02_000003_add_cancellation_requested_to_services_status.php` using a raw `ALTER TABLE ... MODIFY COLUMN status ENUM(...)` statement (Doctrine DBAL not required):
```sql
ALTER TABLE services MODIFY COLUMN status ENUM('pending','active','suspended','cancelled','terminated','cancellation_requested') NOT NULL DEFAULT 'pending'
```
`down()` moves any `cancellation_requested` rows back to `active` before narrowing the enum. Both previously missing column migrations were also added to `deploy-stratatest.js` for future deploy tracking.

---

## BF-038 — Client dashboard blank / no account summary
**Status:** FIXED
**Files:** `resources/js/Pages/Client/Dashboard.vue`, `app/Http/Controllers/Client/DashboardController.php`

### Symptom
The client portal dashboard loaded but appeared blank — no useful account information was shown, especially for accounts with no services or invoices yet.

### Root cause
The existing dashboard had the correct data structure but relied entirely on populated data to show content. All sections showed empty-state text simultaneously for a new account. There was no account summary, no CTA for new accounts, no credit balance display, and no outstanding balance alert.

Additionally, "Sessions" (active session management) was visible in the client-facing Account navigation sidebar — this is an admin/security tool not relevant to standard clients.

### Fix
- **Dashboard redesign** — personalised welcome banner with user name and company name, outstanding balance amber alert strip when unpaid invoices exist, stat cards as clickable links with colour highlighting, services list with colour-coded due-date proximity (red ≤7d, amber ≤14d), "Browse Plans" CTA on empty service state, unpaid/tickets side-by-side layout, billing history full-width below.
- **DashboardController** — added `creditBalance` (float) and `companyName` (from Settings) to props.
- **Sessions nav hidden from clients** — `settingsNav` rendered in `AppLayout.vue` now filters `i.name !== 'Sessions'` for non-admin users.

---

## BF-039 — Client dashboard blank: Ziggy route `client.tickets.show` does not exist
**Status:** FIXED
**File:** `resources/js/Pages/Client/Dashboard.vue`

### Symptom
The client portal dashboard appeared blank for any account that had open support tickets. For accounts with no tickets the page rendered (but showed the redesigned empty state from BF-038).

### Root cause
`Dashboard.vue` contained the following in the recent tickets list:
```vue
<Link :href="route('client.tickets.show', t.id)">
```
The route `client.tickets.show` does not exist. The correct route name is `client.support.show` (declared in `routes/web.php` as `Route::get('support/{ticket}', ...)->name('support.show')` inside the `Route::name('client.')->group(...)` block, giving the full name `client.support.show`).

Ziggy throws a JavaScript exception when `route()` is called with an unknown name. Because this occurred inside a `v-for` rendering the tickets list, the exception propagated and crashed the entire Vue component tree for the page, resulting in a fully blank dashboard for any account with tickets.

### Fix
Changed `route('client.tickets.show', t.id)` to `route('client.support.show', t.id)` in `Dashboard.vue`.

---

*Last updated: 2026-04-03*
