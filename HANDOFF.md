# Strata — Session Handoff

**Date:** Friday, March 27, 2026
**Time:** 7:19 PM EDT
**Branch:** `main` — fully committed and pushed to GitHub
**Deploy target:** `http://stratadev.hosted-tech.net`

---

## What Was Done This Session

### BF-011 — PATCH/DELETE 403 on Shared Hosting ✅ FIXED
- CWP/ModSecurity blocks PATCH, PUT, DELETE HTTP verbs
- Global patch in `resources/js/app.js` converts all Inertia PATCH/PUT/DELETE to POST + `_method` spoofing
- Single fix covers all 30+ Vue components — no individual changes needed

### BF-012 — Knowledge Base Category Issue ✅ FIXED
- Verified working

### BF-013 — Audit Log Blank / No Admin-Client Separation ✅ FIXED
- Added `actor_type` ENUM column to `audit_logs` via migration (applied to server)
- `AuditLogger` auto-detects admin/client/system from user roles
- Added All / Admin Actions / Client Actions tabs to Audit Log UI
- Wired up logging: login, logout, 2FA login, settings updates, staff permissions, client create/update, email sent

### BF-014 — Logo Upload 403 ✅ FIXED
- Same fix as BF-011 (method spoofing)

### BF-015 — Debug Logging in InstallerController ⏸ DEFERRED
- `Log::debug('INSTALL_DB_TEST', ...)` logs DB password hex to laravel.log
- Remove before production release

### BF-016 — Send Email Transport Failure 🔴 OPEN
- `ClientEmail` mailable built and wired to client profile page
- Sendmail transport fails: `Connection to "process /usr/sbin/sendmail -bs -i" has been closed unexpectedly`
- Switched to `MAIL_MAILER=smtp MAIL_HOST=localhost MAIL_PORT=25` — also failing
- **Fix needed:** Get correct outbound SMTP credentials from CWP hosting panel → update `PROD_ENV` in `deploy/deploy-strata.js`

### Public Portal + Embeddable Widget System ✅ COMPLETE
- New glass-theme public portal (no auth required):
  - `/` — Home with hero, featured products, announcements, KB teaser, CTA
  - `/services` — Full product catalog with type filter
  - `/kb` — Searchable knowledge base
  - `/kb/{slug}` — Article reader
  - `/announcements` — Paginated news feed
- `PortalLayout.vue` — light blue glassmorphism, gradient background, blurred orbs, sticky header
- `PortalController.php` — serves public pages; redirects authenticated users to their dashboard
- `WidgetController.php` — JSON API at `/api/widget/{products,announcements,kb}` (CORS-open, rate-limited, read-only)
- `/strata-widget.js` — embeddable vanilla JS widget, zero dependencies

**Widget embed syntax:**
```html
<div data-strata-widget="catalog"
     data-strata-url="https://stratadev.hosted-tech.net"
     data-strata-limit="6"
     data-strata-theme="glass"></div>
<script src="https://stratadev.hosted-tech.net/strata-widget.js" async></script>
```
Supported widget types: `catalog`, `announcements`, `kb`, `support`
Supported themes: `glass` (dark backgrounds), `light` (light site backgrounds)

---

## Current Git State

```
cf436fd  Add public-facing portal with glassmorphism UI and embeddable widget system
ff48b95  Add BF-016: Send Email mail transport failure on CWP shared hosting
2669d97  Fix ClientEmail: use Setting::get() directly instead of setting() helper
e5b5ad3  Fix Send Email button — only disable while processing, not on empty fields
7e1bdfd  Replace email modal with inline collapsible form (same pattern as credit form)
```

---

## Open Issues / Next Steps

| Priority | Item |
|----------|------|
| 🔴 High  | BF-016: Fix outbound mail — get SMTP credentials from CWP panel |
| 🟡 Medium | BF-015: Remove `Log::debug` from InstallerController before release |
| 🟡 Medium | Payment gateways: Stripe and PayPal keys empty in `.env` |
| 🟡 Medium | Provisioning: cPanel/Plesk modules need real server credentials to test |
| 🟢 Low   | Domain registrar: Namecheap/eNom currently in sandbox mode |
| 🟢 Low   | Portal: Add domain availability checker to public `/services` page |
| 🟢 Low   | Portal: Add public order flow (allow ordering without pre-registering) |

---

## Server Access

| Item | Value |
|------|-------|
| Site | http://stratadev.hosted-tech.net |
| Admin panel | http://stratadev.hosted-tech.net/admin |
| FTP deploy | `node deploy/deploy-strata.js --skip-vendor` |
| SSH (plink) | `plink hostedte@hosted-tech.net` |
| DB | `hostedte_stratad` / user `hostedte_strata` |

---

## Key Files Reference

| File | Purpose |
|------|---------|
| `resources/js/app.js` | Global method-spoofing patch (PATCH/PUT/DELETE → POST) |
| `resources/js/Layouts/PortalLayout.vue` | Public glass-theme layout |
| `resources/js/Pages/Portal/` | All public portal pages |
| `app/Http/Controllers/Portal/PortalController.php` | Public page controller |
| `app/Http/Controllers/Portal/WidgetController.php` | Widget JS + JSON API |
| `app/Http/Controllers/Admin/ClientController.php` | Admin client management + sendEmail |
| `app/Mail/ClientEmail.php` | Branded HTML mailable |
| `app/Services/AuditLogger.php` | Centralized audit logging with actor_type |
| `deploy/deploy-strata.js` | FTP deploy script (--skip-vendor, --env-only flags) |
| `BUGFIX.md` | Bug tracking log (BF-001 through BF-016) |
