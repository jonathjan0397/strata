# Strata — Product Roadmap

> Organized by phase. Each phase ships as a tagged release. Phases within a milestone can overlap; phases across milestones are sequential.
>
> **Core tier** = always free (FSL). **Premium** = commercial license, 30-day trial.

---

## Milestone 0 — Foundation
*Goal: Installable skeleton with auth, DB schema, and dev environment*

### v0.1 — Project Skeleton
- [ ] Laravel 11 project init with Inertia.js + Vue 3 + Tailwind CSS
- [ ] Docker Compose: `app` (PHP-FPM), `nginx`, `mysql`, `redis`, `horizon`, `meilisearch`
- [ ] `.env.example` with all required variables documented
- [ ] Pest test suite bootstrapped (unit + feature)
- [ ] GitHub Actions CI: test + lint on push
- [ ] OpenAPI 3.0 spec scaffolded (auto-generated from routes)
- [ ] Basic admin shell layout (sidebar navigation, responsive)

### v0.2 — Auth & Multi-Role Access
- [ ] Admin authentication (email/password + TOTP 2FA)
- [ ] Client authentication (email/password + TOTP 2FA)
- [ ] OAuth2 login: Google, Microsoft (client portal)
- [ ] Role system: `super-admin`, `admin`, `staff`, `client`
- [ ] Staff permission groups (e.g., billing-only, support-only)
- [ ] Session management (active sessions, revoke)
- [ ] Password reset flow
- [ ] Email verification on client registration

### v0.3 — Database Schema & Settings
- [ ] Full database schema migration for all v1 entities (clients, products, invoices, tickets, orders, domains, services)
- [ ] System settings panel (company info, logo, currency, timezone, date format)
- [ ] Email settings (SMTP config, test send)
- [ ] Email template engine (per-event templates with variable substitution)
- [ ] Audit log (all admin actions recorded with actor, IP, timestamp)
- [ ] Activity log visible in admin and client areas

---

## Milestone 1 — Core Billing
*Goal: End-to-end billing that can process real payments*

### v0.4 — Clients & Contacts
- [ ] Client account creation (admin and self-registration)
- [ ] Client profile: contact info, company, address, tax ID, custom fields
- [ ] Multiple contacts per client account
- [ ] Client groups (e.g., Reseller, Wholesale, VIP) with group-level pricing
- [ ] Client notes (internal, not visible to client)
- [ ] Client credit balance (add credit, apply to invoices)
- [ ] Client merge (combine duplicate accounts)

### v0.5 — Products & Pricing
- [ ] Product catalog: name, description, billing cycle options, pricing
- [ ] Billing cycles: monthly, quarterly, semi-annual, annual, biennial, one-time
- [ ] Configurable options (e.g., disk: 10GB/20GB/50GB, each at different price)
- [ ] Add-ons (optional line items added to a service)
- [ ] Product groups and categories
- [ ] Promotional pricing / discounts (percent or fixed, expiry date)
- [ ] Promo codes at checkout
- [ ] Free trial periods (X days before first billing)
- [ ] Setup fees (one-time charge on first invoice)
- [ ] Tax rules (VAT/GST by country, tax-exempt clients, tax class per product)

### v0.6 — Orders & Services
- [ ] Client-facing order form (configurable, embeddable)
- [ ] Admin order creation on behalf of client
- [ ] Order review + approval workflow (auto-approve or manual review)
- [ ] Service records: status (Active/Suspended/Terminated/Pending), next due date, pricing
- [ ] Upgrade / downgrade services (pro-rated billing)
- [ ] Service cancellation requests with reason and effective date
- [ ] Service notes (internal)
- [ ] Bulk service actions (suspend all, terminate all for a client)

### v0.7 — Invoices & Payments
- [ ] Automated invoice generation on billing cycle due dates
- [ ] Manual invoice creation with line items
- [ ] Invoice PDF generation (downloadable by client and admin)
- [ ] Invoice statuses: Draft, Unpaid, Paid, Overdue, Cancelled
- [ ] Payment reminders: configurable schedule (e.g., 7 days before, 3 days after due)
- [ ] Overdue notices: configurable escalation sequence
- [ ] Credit notes / partial refunds
- [ ] Transactions log per invoice
- [ ] Multi-currency invoicing with exchange rate management
- [ ] Late fee automation (fixed or percentage after X days overdue)

### v0.8 — Payment Gateways
- [ ] **Stripe** (cards, SEPA, ACH, 3D Secure) — primary gateway
- [ ] **PayPal** (standard + subscriptions)
- [ ] **Authorize.net**
- [ ] Bank transfer / manual payment (admin confirms manually)
- [ ] Gateway module interface (defined contract for adding new gateways)
- [ ] Stored payment methods (card on file, auto-charge)
- [ ] Dunning management: retry failed payments on schedule
- [ ] Chargebacks / disputes tracking

### v0.9 — Automation Engine (Core)
- [ ] Automation queue via Laravel Horizon (Redis)
- [ ] Core automation rules:
  - Suspend service N days after invoice overdue
  - Unsuspend service on payment received
  - Terminate service N days after suspension
  - Send email on service state change
- [ ] Automation log (every triggered action recorded)
- [ ] Manual override: skip automation for specific services
- [ ] Scheduled tasks dashboard (Horizon queue visibility in admin)

---

## Milestone 2 — Provisioning
*Goal: Automated hosting account lifecycle tied to billing*

### v1.0 — Provisioning Module Interface
- [ ] Server record management (hostname, IP, API credentials, type)
- [ ] Server group assignment (load-balance new accounts across group)
- [ ] Server capacity tracking (account count vs. limit)
- [ ] Module interface contract: `create`, `suspend`, `unsuspend`, `terminate`, `changePackage`, `changePassword`, `getUsage`
- [ ] Provisioning queue with retry logic (if server unreachable, retry after delay)
- [ ] Provisioning log per service

### v1.1 — cPanel / WHM
- [ ] cPanel account creation via WHM API2 / UAPI
- [ ] Package assignment (map Strata product to cPanel package)
- [ ] cPanel account suspension / unsuspension / termination
- [ ] cPanel password change
- [ ] cPanel auto-login (SSO link in client portal)
- [ ] Bandwidth / disk usage sync (update Strata usage records)
- [ ] IP assignment from WHM IP pool
- [ ] WHM reseller account creation

### v1.2 — Plesk
- [ ] Plesk subscription creation via Plesk API
- [ ] Service plan mapping
- [ ] Suspend / unsuspend / terminate
- [ ] Password change
- [ ] Auto-login SSO
- [ ] Disk / traffic usage sync

### v1.3 — DirectAdmin
- [ ] DirectAdmin user creation via API
- [ ] Package mapping
- [ ] Suspend / unsuspend / terminate
- [ ] Password change
- [ ] Auto-login SSO

### v1.4 — HestiaCP + VestaCP
- [ ] HestiaCP user creation via API
- [ ] Package mapping
- [ ] Suspend / unsuspend / terminate
- [ ] Auto-login SSO

---

## Milestone 3 — Domain Management
*Goal: Domain registration, renewal, and transfer fully automated*

### v1.5 — Domain Module Interface
- [ ] Domain record management (status, expiry, nameservers, WHOIS privacy)
- [ ] Registrar module contract: `register`, `transfer`, `renew`, `getInfo`, `updateNameservers`, `enablePrivacy`, `getEPP`
- [ ] Domain search / availability check (multi-TLD)
- [ ] TLD pricing table (per registrar, per TLD, per action type)
- [ ] Domain auto-renewal (invoice generated 30/14/7 days before expiry)
- [ ] Domain expiry alerts (to client and admin)
- [ ] Bulk domain import (for existing domains)

### v1.6 — Registrar: Namecheap
- [ ] Registration, transfer, renewal via Namecheap API
- [ ] Nameserver management
- [ ] WHOIS privacy toggle
- [ ] EPP/auth code retrieval
- [ ] TLD sync from Namecheap pricing API

### v1.7 — Registrar: OpenSRS / Tucows
- [ ] Full lifecycle via OpenSRS XCP API
- [ ] Nameserver + DNS management

### v1.8 — Registrar: eNom / Enom
- [ ] Full lifecycle via eNom API

### v1.9 — Registrar: HEXONET / CentralNic
- [ ] HEXONET EPP API integration

---

## Milestone 4 — Support System
*Goal: Full-featured ticket system integrated with billing context*

### v2.0 — Ticket System
- [ ] Ticket creation (client portal + admin)
- [ ] Departments (billing, technical, sales — configurable)
- [ ] Staff assignment per department
- [ ] Priority levels (Low / Medium / High / Critical)
- [ ] Email piping (reply-by-email updates ticket)
- [ ] HTML reply editor + file attachments
- [ ] Internal notes (staff-only, not visible to client)
- [ ] Canned responses (pre-written reply templates)
- [ ] Ticket status: Open / Awaiting Reply / On Hold / Closed
- [ ] Auto-close inactive tickets after N days
- [ ] Client satisfaction rating on ticket close
- [ ] Linked services (attach a service to a ticket for context)
- [ ] Ticket search (full-text via Scout/Meilisearch)

### v2.1 — Knowledge Base
- [ ] KB articles with rich-text editor
- [ ] Category organization
- [ ] Article search
- [ ] "Suggested articles" shown on ticket creation form
- [ ] Public KB (no login required) + staff-only articles
- [ ] Article view count, helpfulness rating

---

## Milestone 5 — Premium Features ⭐
*All features in this milestone require a commercial license after the 30-day trial*

### v2.2 — Advanced Automation Workflows ⭐
- [ ] Visual workflow builder (trigger → conditions → actions)
- [ ] Triggers: invoice created, payment received, service created, ticket opened, client registered, custom webhook event, date/time
- [ ] Conditions: client group, product, invoice amount, client country, custom field value
- [ ] Actions: send email, create ticket, suspend service, add credit, call webhook, move client group, apply discount
- [ ] Multi-step workflows with delay between steps
- [ ] Workflow run log with per-step status
- [ ] Workflow templates (pre-built common scenarios)

### v2.3 — Usage-Based / Metered Billing ⭐
- [ ] Usage metric types: bandwidth, CPU hours, storage, API calls, seats
- [ ] Usage ingestion API (push or pull from servers)
- [ ] Usage aggregation per billing period
- [ ] Tiered pricing (first 100GB free, $0.01/GB after)
- [ ] Usage threshold alerts (email client at X% of included allowance)
- [ ] Usage overage invoicing at end of period
- [ ] Usage reports (client-visible + admin dashboard)

### v2.4 — White-Label Reseller System ⭐
- [ ] Reseller accounts with their own branded client portal
- [ ] Reseller creates their own product catalog with markup pricing
- [ ] Reseller billing: wholesale price to reseller, reseller charges their clients
- [ ] Reseller credit system (fund account, deduct on provisioning)
- [ ] Reseller can brand their portal (logo, colors, domain)
- [ ] Admin view: see all resellers and their clients
- [ ] Reseller reporting: revenue, active services, outstanding

### v2.5 — Affiliate Management ⭐
- [ ] Affiliate account creation + approval workflow
- [ ] Unique referral links + coupon codes
- [ ] Commission types: one-time (on signup), recurring (% of each invoice)
- [ ] Commission tiers (tier up based on referral count or revenue)
- [ ] Affiliate dashboard (clicks, signups, earnings)
- [ ] Payout management (request payout, admin approves, pay via gateway)
- [ ] Fraud detection: flag suspicious self-referrals

### v2.6 — Revenue Analytics & Reporting ⭐
- [ ] MRR / ARR dashboard (with historical chart)
- [ ] Churn rate (voluntary cancellations + payment failures)
- [ ] Cohort analysis (retention by signup month)
- [ ] Revenue by product, by country, by gateway
- [ ] Projected revenue (upcoming renewals)
- [ ] Overdue aging report (0–30, 30–60, 60–90+ days)
- [ ] Client lifetime value
- [ ] Exportable reports (CSV, PDF)

### v2.7 — WHMCS Migration Wizard ⭐
- [ ] Import clients from WHMCS database dump (direct DB connection or SQL file)
- [ ] Import services, products, invoices, tickets, custom fields
- [ ] Mapping UI: map WHMCS server groups → Strata servers, WHMCS gateways → Strata gateways
- [ ] Dry-run mode (show what would be imported without committing)
- [ ] Conflict resolution UI (duplicate emails, etc.)
- [ ] Post-import validation report

---

## Milestone 6 — Polish & Scale
*Goal: Production-hardened, mobile-ready, enterprise-capable*

### v3.0 — PWA Client Portal
- [ ] Progressive Web App manifest + service worker
- [ ] Installable on iOS / Android home screen
- [ ] Offline invoice viewing (cached)
- [ ] Push notifications (payment due, ticket reply)
- [ ] Mobile-optimized admin dashboard (read-only on mobile)

### v3.1 — Full REST API + SDK
- [ ] 100% REST API coverage (every UI action is API-accessible)
- [ ] OpenAPI 3.0 spec published
- [ ] Auto-generated PHP SDK (from spec)
- [ ] Auto-generated JavaScript/TypeScript SDK
- [ ] API key management (per-client, per-staff, scoped permissions)
- [ ] Webhook management UI (subscribe to events, view delivery log, retry)

### v3.2 — Security & Compliance
- [ ] IP allowlisting for admin area
- [ ] Brute-force protection (rate limiting on login, lockout)
- [ ] GDPR data export (client requests their data as ZIP)
- [ ] GDPR data deletion (right to erasure, with billing record retention option)
- [ ] EU VAT MOSS reporting
- [ ] PCI DSS scope notes (Stripe handles cardholder data; Strata stays out of scope)
- [ ] SSL certificate provisioning module (Let's Encrypt for client domains)

### v3.3 — Multi-Language & Multi-Currency
- [ ] Full i18n (all UI strings translatable)
- [ ] Community translation contributions via Crowdin or similar
- [ ] Per-client language preference
- [ ] GeoIP-based currency suggestion on order form
- [ ] Exchange rate auto-sync (fixer.io / OpenExchangeRates)
- [ ] Per-country tax rate database (auto-populated, manually overridable)

### v3.4 — Additional Provisioning Modules
- [ ] Pterodactyl (game server billing)
- [ ] Proxmox VE (VPS billing)
- [ ] Virtualizor
- [ ] Cloudflare (DNS management)
- [ ] Generic SSH module (run custom commands on provision/terminate)

---

## Backlog / Future Consideration

- Native iOS + Android app (post-v3)
- Multi-tenant SaaS mode (Strata serves multiple independent hosting brands)
- Live chat integration (Tawk.to, Crisp embed)
- Custom domain for client portal (white-label hosting)
- Quote / proposal system
- Contract management
- Network device management (for data center operators)
- OpenStack / VMware cloud billing
- SOC 2 audit prep tooling

---

## Release milestones at a glance

| Milestone | Version range | What ships |
|-----------|--------------|------------|
| 0 — Foundation | v0.1–v0.3 | Auth, DB, Docker, settings |
| 1 — Core Billing | v0.4–v0.9 | Clients, products, invoices, payments, core automation |
| 2 — Provisioning | v1.0–v1.4 | cPanel, Plesk, DirectAdmin, HestiaCP |
| 3 — Domains | v1.5–v1.9 | Domain lifecycle, 4 registrars |
| 4 — Support | v2.0–v2.1 | Tickets, KB |
| 5 — Premium ⭐ | v2.2–v2.7 | Automation workflows, metered billing, reseller, affiliates, analytics, migration wizard |
| 6 — Polish | v3.0–v3.4 | PWA, full API, compliance, i18n, more modules |

*Last updated: 2026-03-26*
