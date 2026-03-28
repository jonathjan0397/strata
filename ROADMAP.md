# Strata — Product Roadmap

> Organized by phase. Each phase ships as a tagged release. Phases within a milestone can overlap; phases across milestones are sequential.
>
> **Core tier** = always free (FSL). **Premium** = commercial license, 30-day trial.
>
> ✅ = Complete | 🔄 = Partial / In Progress | ⏳ = Planned

---

## Distribution & Install Paths

Strata ships two installation tracks from the same codebase:

| Track | Target | How |
|-------|--------|-----|
| **Shared Hosting** | cPanel / Plesk / DirectAdmin shared accounts | Pre-built ZIP (`strata-x.x.x-shared.zip`): `vendor/` + compiled Vite assets bundled; no Composer or Node required on server; drop into `public_html/`, browse to `/install` |
| **VPS / Dedicated** | Full root-access servers | Standard ZIP (`strata-x.x.x.zip`) or `git clone`; run `composer install` + `npm run build` + `php artisan ...` via CLI |

Both tracks share the same browser-based installer wizard at `/install`. The wizard auto-detects the environment and adjusts defaults (queue driver, cron instructions, storage link method).

### v0.x — Shared Hosting Compatibility ✅
- [x] Root-level shared hosting bootstrap: `index.php` + `.htaccess` shim at project root so web root = project root (no document root change required)
- [x] `Artisan::call('storage:link')` added to installer; graceful fallback to `StorageController` file-serving route if symlinks are blocked by host
- [x] Queue mode selector in installer: **Sync** (shared hosting — jobs run inline, no worker needed) vs **Database** (VPS — jobs queued, worker runs via cron/supervisor)
- [x] Installer post-install screen: shows cron command (`* * * * * php /path/to/artisan schedule:run`) and, if queue=database, queue worker command
- [x] Installer requirements check: add `file_uploads`, `upload_max_filesize` ≥ 10 MB, `post_max_size`, `max_execution_time` ≥ 60, `memory_limit` ≥ 128 MB, `mod_rewrite` (Apache), `symlink()` availability
- [x] Installer detects install-type automatically: presence of `vendor/` = ZIP install (shared); absence = dev/VPS clone
- [x] `OPENSRS_*` keys added to installer `.env` template
- [x] Lock file version updated to current release on each install
- [x] GitHub Actions release workflow: on `v*` tag push → `composer install --no-dev` + `npm run build` → produce `strata-x.x.x-shared.zip` (with `vendor/` + `public/build/`) and `strata-x.x.x.zip` (source only) → attach to GitHub Release
- [x] GitHub Actions CI workflow: run Pest on every push/PR

---

## Milestone 0 — Foundation
*Goal: Installable skeleton with auth, DB schema, and dev environment*

### v0.1 — Project Skeleton ✅
- [x] Laravel 12 project with Inertia.js v2 + Vue 3 + Tailwind CSS v4 + Vite 8
- [x] `.env.example` with all required variables documented
- [x] Basic admin shell layout (sidebar navigation, responsive)
- [ ] Docker Compose: `app` (PHP-FPM), `nginx`, `mysql`, `redis`, `horizon`, `meilisearch` ⏳
- [ ] Pest test suite bootstrapped ⏳
- [x] GitHub Actions CI ✅ (see Distribution section above)
- [ ] OpenAPI 3.0 spec scaffolded ⏳

### v0.2 — Auth & Multi-Role Access ✅
- [x] Admin authentication (email/password)
- [x] Client authentication (email/password)
- [x] TOTP two-factor authentication (QR setup, challenge page, enable/disable)
- [x] OAuth2 login via Laravel Socialite (Google, GitHub, any provider)
- [x] Role system: `super-admin`, `admin`, `staff`, `client` (spatie/laravel-permission)
- [x] Session management (active sessions, individual + bulk revoke)
- [x] Password reset flow
- [x] Email verification on client registration
- [x] Staff permission groups (billing-only, support-only) ✅

### v0.3 — Database Schema, Browser Installer & Settings ✅
- [x] Full database schema migration for all core entities (clients, products, invoices, tickets, orders, domains, services, modules, announcements, email templates)
- [x] **Browser installer wizard** at `/install` — requirements check, DB test, env write, migrate + seed, lock file
- [x] Email template engine (7 system templates, `{{variable}}` substitution, admin-editable)
- [x] System settings panel (company info, logo, currency, timezone) — v0.9.0/v1.0.0
- [x] Audit log ✅

---

## Milestone 1 — Core Billing ✅
*Goal: End-to-end billing that can process real payments*

### v0.4 — Clients & Contacts ✅
- [x] Client account creation (admin and self-registration)
- [x] Client profile: name, email, company
- [x] Client notes (internal)
- [x] Client suspension
- [ ] Multiple contacts per client account ⏳
- [x] Client groups with group-level pricing ✅
- [x] Client credit balance ✅
- [ ] Client merge ⏳

### v0.5 — Products & Pricing ✅
- [x] Product catalog: name, description, type, billing cycle, price, setup fee
- [x] Billing cycles: monthly, quarterly, semi-annual, annual, biennial, triennial, one-time
- [x] Product type flags: shared, reseller, vps, dedicated, domain, other
- [x] Setup fees (one-time charge on first invoice)
- [x] Stock / capacity limit
- [x] Sort order for catalog display
- [ ] Configurable options / add-ons ⏳
- [x] Promotional pricing / promo codes ✅
- [ ] Free trial periods ⏳
- [x] Tax rules (VAT/GST) ✅

### v0.6 — Orders & Services ✅
- [x] Client-facing order catalog and checkout form
- [x] Admin order creation (manual invoice + service)
- [x] Service records: status (active/suspended/terminated/pending), next due date
- [x] Domain field captured at order time
- [x] Upgrade / downgrade (admin service edit) 🔄
- [x] Service cancellation requests ✅
- [ ] Bulk service actions ⏳

### v2.1 — Knowledge Base ⏳
- [ ] KB articles with rich-text editor
- [ ] Category organization + article search
- [ ] Suggested articles on ticket creation

### v0.7 — Invoices, PDF & Billing Automation ✅
- [x] Automated invoice generation (`billing:generate-invoices`)
- [x] Manual invoice creation with line items (admin)
- [x] Invoice PDF generation (DomPDF A4, branded, downloadable by admin and client)
- [x] Invoice statuses: unpaid, paid, overdue, cancelled
- [x] `billing:flag-overdue` — marks past-due invoices overdue, sends email
- [x] `billing:suspend-overdue` — suspends services past grace period, sends email
- [x] Transactions log per invoice (Payments table)
- [x] Payment reminders: configurable schedule ✅
- [ ] Credit notes / partial refunds ⏳
- [ ] Multi-currency invoicing ⏳
- [x] Late fee automation ✅

### v0.8 — Payment Gateways ✅
- [x] **Stripe Checkout** — hosted session, webhook reconciliation (`checkout.session.completed` / expired)
- [x] **PayPal Orders v2** — create order → approve → capture on return URL; cancel URL
- [x] Double-payment guard on paid invoices
- [x] Pending payment record on initiation (both gateways)
- [x] Authorize.net ✅
- [ ] Bank transfer / manual payment ⏳
- [x] Stored payment methods / auto-charge ✅
- [x] Dunning management ✅

---

## Milestone 2 — Provisioning ✅ (cPanel)
*Goal: Automated hosting account lifecycle tied to billing*

### v1.0 — Provisioning Module Interface ✅
- [x] Server (module) record management: hostname, API token, type, capacity, account count
- [x] Server capacity tracking (account count vs. limit)
- [x] `RegistrarDriver`-style contract pattern applied to provisioners
- [x] Provisioning queue via `provisioning:run` command (every 5 min)
- [ ] Server group load-balancing ⏳
- [ ] Provisioning log per service (visible in admin) 🔄
- [ ] Retry logic for unreachable servers ⏳

### v1.1 — cPanel / WHM ✅
- [x] cPanel account creation via WHM JSON API v1 (Bearer token)
- [x] Username generation (domain-derived, 6 chars + 2 random suffix)
- [x] cPanel account suspension / unsuspension / termination
- [x] Service updated with username, encrypted password, server hostname, `module_data`
- [ ] cPanel package assignment / sync ⏳
- [ ] cPanel auto-login SSO ⏳
- [ ] Bandwidth / disk usage sync ⏳
- [ ] WHM reseller account creation ⏳

### v1.2 — Plesk ✅
- [x] Plesk subscription (webspace) creation via Plesk REST API v2
- [x] Suspend / unsuspend / terminate
- [ ] Auto-login SSO ⏳

### v1.3 — DirectAdmin ✅
- [x] DirectAdmin user creation via DirectAdmin HTTP API
- [x] Suspend / unsuspend / terminate

### v1.4 — HestiaCP ✅
- [x] HestiaCP API provisioner — create, suspend, unsuspend, terminate
- [x] Registered in `ProvisionerService` under `hestia`

---

## Milestone 3 — Domain Management ✅ (Namecheap + Enom)
*Goal: Domain registration, renewal, and transfer fully automated*

### v1.5 — Domain Module Interface ✅
- [x] `RegistrarDriver` contract: `checkAvailability`, `registerDomain`, `renewDomain`, `transferDomain`, `getNameservers`, `setNameservers`, `getInfo`, `setLock`, `setPrivacy`, `slug`
- [x] `DomainRegistrarService` factory with driver selection
- [x] Domain record management: status, expiry, nameservers (1–4), auto_renew, locked, privacy, registrar_data JSON
- [x] Live domain availability check endpoint (JSON; used by checkout)
- [x] Domain auto-renewal scheduler (`domains:renew-expiring` — daily at 09:00)
- [x] Admin domain index (search, status filter, pagination) + show (NS editor, lock/privacy, refresh)
- [x] Client domain index + show (NS editor up to 6, auto-renew toggle)
- [ ] TLD pricing table ⏳
- [x] Domain expiry alerts to client ✅ (30/14/7-day reminder emails via `SendDomainRenewalReminders`)
- [ ] Bulk domain import ⏳
- [ ] EPP/auth code retrieval ⏳

### v1.6 — Registrar: Namecheap ✅
- [x] Registration, renewal, transfer via Namecheap XML API v1
- [x] Nameserver management
- [x] WHOIS privacy toggle
- [x] Lock / unlock
- [x] Sandbox mode
- [ ] TLD sync from pricing API ⏳

### v1.7 — Registrar: OpenSRS / Tucows ✅
- [x] Full lifecycle via OpenSRS XCP API (HMAC-MD5 auth, sandbox)
- [x] Nameserver management, WHOIS privacy, lock/unlock

### v1.8 — Registrar: eNom ✅
- [x] Registration, renewal, transfer via Enom reseller XML API
- [x] Nameserver management
- [x] Lock / unlock
- [x] Sandbox mode

### v1.9 — Registrar: HEXONET / CentralNic ✅
- [x] HEXONET ISPAPI HTTP API integration (OTE sandbox + live)

---

## Milestone 4 — Support System 🔄
*Goal: Full-featured ticket system integrated with billing context*

### v2.0 — Ticket System 🔄
- [x] Ticket creation (client portal + admin view)
- [x] Staff reply from admin panel
- [x] `support.reply` email notification on admin reply
- [x] Ticket status: open / closed / reopen
- [x] Admin assign ticket to staff
- [x] Departments (billing, technical, sales — configurable) — v0.9.0
- [x] Internal notes (staff-only, amber styling, hidden from client) — v0.9.0
- [x] Canned responses (department-scoped) — v0.9.0
- [x] Priority levels ✅ (admin inline dropdown, auto-saves via PATCH)
- [ ] Email piping (reply-by-email updates ticket) ⏳
- [ ] HTML reply editor + file attachments ⏳
- [x] Auto-close inactive tickets ✅
- [ ] Client satisfaction rating ⏳
- [ ] Ticket search ⏳

### v2.1 — Knowledge Base ✅
- [x] KB articles with rich-text editor + publish toggle
- [x] Category organization + full-text article search + view counter
- [x] Admin: category CRUD, article list with filters, editor
- [x] Client: browse by category, keyword search, article view with related articles + support CTA
- [ ] Suggested articles on ticket creation ⏳

---

## Milestone 5 — Premium Features ⭐
*All features in this milestone require a commercial license after the 30-day trial*

### v2.2 — Advanced Automation Workflows ⭐ ✅
- [x] Visual workflow builder (trigger → conditions → actions)
- [x] Triggers: invoice.created, invoice.paid, invoice.overdue, service.created, service.suspended, service.cancelled, ticket.opened, ticket.closed, client.registered
- [x] Conditions: field operator value (eq/neq/gt/lt/gte/lte/contains), ALL must pass
- [x] Actions: send.email, create.ticket, suspend.service, add.credit, call.webhook
- [x] Multi-step workflows with configurable delay per action (dispatched as queued jobs)
- [x] Workflow run log with per-action log entries and status (completed/failed/skipped)

### v2.3 — Usage-Based / Metered Billing ⭐ ⏳
- [ ] Usage metric types: bandwidth, CPU hours, storage, API calls, seats
- [ ] Usage ingestion API
- [ ] Tiered pricing
- [ ] Usage threshold alerts + overage invoicing

### v2.4 — White-Label Reseller System ⭐ ⏳
- [ ] Reseller accounts with branded client portal
- [ ] Reseller product catalog with markup pricing
- [ ] Reseller credit system

### v2.5 — Affiliate Management ⭐ ⏳
- [ ] Referral links + commission tiers
- [ ] Payout management

### v2.6 — Revenue Analytics & Reporting ⭐ 🔄
- [x] MRR / ARR dashboard — 12-month revenue chart, growth %, unpaid/overdue totals, top 10 clients, service status breakdown, support stats
- [ ] Churn rate, cohort analysis, projected revenue ⏳
- [ ] Exportable reports (CSV, PDF) ⏳

### v2.7 — Migration Import Wizard ⭐ ⏳
- [ ] Import clients, services, products, invoices, and tickets from an external billing platform DB dump
- [ ] Dry-run mode + conflict resolution
- [ ] Post-import validation report

---

## Milestone 6 — Polish & Scale

### v3.0 — PWA Client Portal ⏳
- [ ] Progressive Web App manifest + service worker
- [ ] Push notifications (payment due, ticket reply)

### v3.1 — Full REST API + SDK ⏳
- [ ] 100% REST API coverage with OpenAPI 3.0 spec
- [ ] Auto-generated PHP + TypeScript SDKs
- [ ] API key management (scoped permissions)
- [ ] Webhook management UI

### v3.2 — Security & Compliance ⏳
- [ ] IP allowlisting for admin area
- [ ] GDPR data export + deletion
- [ ] EU VAT MOSS reporting
- [ ] SSL certificate provisioning (Let's Encrypt)

### v3.3 — Multi-Language & Multi-Currency ⏳
- [ ] Full i18n
- [ ] Per-client language preference
- [ ] Exchange rate auto-sync

### v3.4 — Additional Provisioning Modules ⏳
- [ ] Pterodactyl (game servers)
- [ ] Proxmox VE (VPS)
- [ ] Cloudflare DNS
- [ ] Generic SSH module

---

## Backlog / Future Consideration

- Docker Compose one-command install
- Redis + Laravel Horizon queue backend
- Native iOS + Android app
- Multi-tenant SaaS mode
- Live chat integration (Tawk.to, Crisp)
- Quote / proposal system
- OpenStack / VMware cloud billing
- SOC 2 audit prep tooling

---

## Milestones at a Glance

| Milestone | Version Range | Status |
|-----------|--------------|--------|
| 0 — Foundation | v0.1–v0.3 | ✅ Complete |
| 1 — Core Billing | v0.4–v0.8 | ✅ Complete (incl. tax rates, client groups, dunning, late fees, Authorize.net) |
| 2 — Provisioning | v1.0–v1.4 | ✅ cPanel, Plesk, DirectAdmin, HestiaCP complete |
| 3 — Domains | v1.5–v1.9 | ✅ Namecheap + Enom + OpenSRS + HEXONET + renewal reminders complete |
| 4 — Support | v2.0–v2.1 | ✅ Tickets, departments, canned responses, priority, KB, auto-close complete |
| 5 — Premium ⭐ | v2.2–v2.7 | 🔄 Workflows + reports dashboard done; usage billing, reseller, affiliate planned |
| 6 — Polish | v3.0–v3.4 | ⏳ Planned |

*Last updated: 2026-03-27*
