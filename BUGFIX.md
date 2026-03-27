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
**Status:** OPEN
**Symptom:** No way to create a KB category from the article creation flow; "Add Article" fails without an existing category
**Fix needed:** Add inline category creation on the KB article create/edit form, or redirect to category management first.

---

## BF-013 — Audit Log does not separate admin vs customer actions
**Status:** OPEN
**Symptom:** All audit log entries are mixed together regardless of actor type
**Fix needed:** Add a filter/tab on the Audit Log page to show admin actions separately from client/customer actions. Requires an `actor_type` or `is_admin` column on the audit log table or derived from the actor's role.

---

## BF-014 — Logo upload may fail (same 403 as BF-011)
**Status:** OPEN (pending verification after BF-011 fix)
**Symptom:** Logo upload to `/admin/settings/logo` returns 403
**Root cause:** Likely same WAF block as BF-011 — POST with file upload may trigger a separate ModSecurity rule for multipart file uploads.
**Fix:** Verify after BF-011 deploy. If still failing, may need to disable WAF for `/admin/settings/logo` at the server level.

---

## BF-015 — Remove debug logging from InstallerController
**Status:** OPEN
**File:** `app/Http/Controllers/Install/InstallerController.php`
**Symptom:** `INSTALL_DB_TEST` debug entries with password hex dumps written to laravel.log in production
**Fix needed:** Remove or gate behind `APP_DEBUG` the `Log::debug('INSTALL_DB_TEST', ...)` block in `testDatabase()`.

---

*Last updated: 2026-03-27*
