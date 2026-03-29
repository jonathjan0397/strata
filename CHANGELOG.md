# Changelog

All notable changes to Strata are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Planned (next priorities)
- Client billing history page — dedicated full invoice list with filters and PDF download
- Authorize.Net Accept.js Vue component for client-side card entry without redirect
- Bank transfer / manual payment gateway
- Installer upgrade wizard — browser-based; replace files and run `artisan migrate` without CLI
- Ticket search — admin-side full-text search across ticket subjects and messages
- Client satisfaction ratings analytics — aggregate view and per-staff performance stats
- HTML reply editor on tickets — rich text composer + file attachments on ticket replies
- Suggested KB articles on ticket creation — surface related articles before client submits

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

*Last updated: 2026-03-29*
