# Strata — Current Features

[![Buy me a coffee](https://img.shields.io/badge/Buy%20me%20a%20coffee-☕-yellow?style=flat-square)](https://buymeacoffee.com/jonathan0397)

> Complete feature inventory as of v1.0.16 (2026-04-02).
> Updated after each release. See [CHANGELOG.md](CHANGELOG.md) for history.

---

## Authentication & Access Control

| Feature | Detail |
|---------|--------|
| Email + password login | Standard Laravel auth with hashed passwords |
| Self-registration | Client account creation with email verification |
| TOTP two-factor authentication | QR code setup, challenge page, confirmation flow |
| 2FA admin-configurable | Enable/disable enforcement, session lifetime, keep-alive toggle via Settings → General |
| 2FA optional enforcement | Persistent amber banner nudge for admin/staff; non-blocking |
| OAuth2 social login | Laravel Socialite — Google and Microsoft |
| Email verification | Verified on registration; resend available |
| Password reset | Signed email link; standard Laravel flow |
| Active session management | View and revoke sessions per device/IP |
| Role system | `super-admin`, `admin`, `staff`, `client` via spatie/laravel-permission |
| Staff permission groups | Granular scopes: billing, support, technical, clients, reports |

---

## Browser Installer

| Feature | Detail |
|---------|--------|
| No-CLI setup wizard | Multi-step wizard at `/install` — works on shared hosting (cPanel, CWP, Plesk, DirectAdmin) |
| PHP requirements check | Pass/fail indicators for all required extensions and settings |
| Database connection test | Live test before committing credentials |
| Scoped `.env` write | Written with mode `0600`; safe handling of special characters (base64 transport) |
| Auto-run migrations + seeders | Runs in-browser via `Artisan::call` |
| Queue mode selector | Sync (shared hosting) or Database (VPS); installer writes `QUEUE_CONNECTION` to `.env` |
| Optional sample data | 5 demo clients, products, services, invoices, tickets, quotes, domains, affiliate, promo codes, and credit note |
| Lock file | `storage/installed.lock` prevents re-running |
| Pre-install URL detection | Detects real base URL from HTTP request when `APP_URL` is `http://localhost`; supports subdirectory installs |
| Pre-install cache fix | Switches cache driver to `array` before `.env` exists to prevent database connection errors |

---

## Admin Panel

### Clients
- Paginated list with search and status filter
- Create / edit / suspend clients
- Client detail: services, invoice history, support tickets, internal notes
- Assign client to group; apply group discount
- `country`, `state`, `tax_exempt` fields for automatic tax resolution
- Internal notes (admin-only, timestamped, deletable)
- **Verify email button** — marks client email address as verified without requiring the client to click the verification link

### Products
- Full CRUD — name, type, billing cycle, price, setup fee, stock, sort order
- Product types: shared, domain, VPS, dedicated, email, SSL, other
- Billing cycles: monthly, quarterly, semi-annual, annual, biennial, triennial, one-time
- **Auto-setup trigger** — `on_order`, `on_payment`, `manual`, `never`
- **Trial period** — `trial_days` field; service activates immediately; invoice due at trial end
- **Account options** — per-product provisioning flags: reseller account (cPanel/Plesk/DirectAdmin/CWP), SSL/Let's Encrypt on creation (with DNS-must-point warning), PHP version override

### Services
- List and detail view
- Admin actions: suspend, unsuspend, terminate, approve & provision
- Approve / reject cancellation requests
- **Cancellation type** — immediate or end-of-period; end-of-period sets `scheduled_cancel_at`
- **Panel API on lifecycle events** — suspend, unsuspend, and terminate all call the configured panel API; gracefully skips if no module is set (manual provisioning)
- **Customer notifications on lifecycle events** — suspend, reactivation, and termination each send the corresponding email template to the client
- **Client upgrade/downgrade** — change plan within same product type; prorated invoice or credit applied automatically
- **Trial badge** — "Free Trial Active" notice with expiry date on client service detail
- Provisioning info (module, credentials) stored on service record

### Addons
- Global addons catalog — name, description, price, setup fee, billing cycle, active flag, sort order
- Attach addons to services from admin service detail page; auto-generates invoice
- Clients can add addons from service detail page
- Addons tracked in `service_addons` join table with own billing cycle and next due date
- Addon renewal invoices generated automatically by `billing:generate-renewals` cron

### Quotes
- Admin creates freeform quotes: line items, tax rate, valid-until date, client message, internal notes
- Quote numbers: `QUO-YYYYMMDD-NNNN`
- Status lifecycle: `draft → sent → accepted / declined`
- Admin sends quote — emails client with `quote.sent` template
- Client accepts or declines from client portal; expired-quote notice shown past `valid_until`
- Admin converts accepted quote to invoice with one click

### Invoices
- List and detail view
- Create invoices manually with line items
- Mark paid, cancel, apply credit
- Download A4 PDF (branded header, line items, tax, payment history)
- **Credit notes** — flat amount, reason, disposition (account balance or invoice deduction); numbered `CN-YYYYMMDD-NNNN`; voidable with full reversal
- Automated generation, overdue flagging, late fee application

### Support Tickets
- Ticket queue with status filter and agent filter
- Inline priority and department editing
- Assign to staff agent
- Department transfer (with internal note)
- Ticket merge (absorbs replies and attachments, closes source)
- Reply with file attachments (up to 5 × 10 MB)
- Internal notes (not visible to client)
- Canned response insertion
- Close and reopen
- SLA visual indicators — dot color and row tint based on age vs priority threshold
- Bulk actions: close, reopen, assign, delete (with select-all toggle)
- First reply time tracking and display
- Client satisfaction ratings view (stars + note)
- **Staff can manually create tickets** — new Create Ticket form in admin; staff select a client, department, priority, and compose the initial message on behalf of the client

### Knowledge Base
- Category management (CRUD, sort order, active toggle)
- Article management — Tiptap rich text editor with:
  - Bold, italic, underline, strikethrough
  - Heading levels H1/H2/H3
  - Text alignment (left, center)
  - Bullet and ordered lists
  - Blockquote and code block
  - Link insertion
  - Image upload — file picker, drag-and-drop, clipboard paste → stored in `kb-images/`
  - Undo / redo
- Publish / draft toggle
- Sort order control
- Admin: full-text article search

### Announcements
- Create, edit, delete
- Publish / draft toggle
- **Rich text editor (Tiptap)** — full formatting toolbar with inline image uploads; stored as HTML; rendered in client portal and public portal with `prose` classes

### Servers
- cPanel/WHM, Plesk, DirectAdmin, HestiaCP, CWP server CRUD
- Credentials and API endpoint stored per server
- **Local/internal server access** — optional hostname and port fields for same-machine installs; uses local API address instead of public hostname when set

### Domains
- List with search and status filter
- Detail: nameserver editor (up to 6), lock/privacy toggles, refresh from registrar
- Auto-renew toggle per domain

### Active Sessions
- Admin view of all currently logged-in users (clients, staff, admins)
- Summary tiles: total online, admin count, staff count, client count
- Role-filtered tabs: All / Admins / Staff / Clients
- Per-row: device icon (desktop or mobile), name/email, role badge, browser + device, IP address, last active (relative time)
- "You" badge on the current admin's own session (revoke buttons hidden)
- Revoke individual session or all sessions for a user (with confirmation)
- Backed by single SQL query with role-priority sub-select (no N+1)

### Staff
- List staff members with permission badges
- Per-staff permission checkbox editor (billing, support, technical, clients, reports)

### Client Groups
- Group-level pricing: percent or fixed discount
- Assign clients to groups from the Client detail page

### Tax Rates
- Country/state-based rules
- Priority: country+state > country-only > default
- `tax_exempt` flag per client
- Applied automatically at checkout

### Email Templates
- Inline editor for all system templates
- `{{variable}}` placeholder substitution with reference panel
- Active / inactive toggle per template

**Built-in templates:**

| Slug | Trigger |
|------|---------|
| `auth.welcome` | Client registration |
| `invoice.created` | Order placed |
| `invoice.paid` | Invoice marked paid |
| `invoice.overdue` | Overdue billing command |
| `service.activated` | Provisioning runner (includes credential variables) |
| `service.suspended` | Suspension (admin action or billing:suspend-overdue) |
| `service.reactivated` | Unsuspend admin action |
| `service.terminated` | Terminate admin action or end-of-period cancellation |
| `support.reply` | Admin/staff reply on ticket |
| `support.opened` | New ticket submitted (admin notification) |
| `support.closed` | Ticket auto-closed (client notification) |
| `support.assigned` | Ticket assigned to staff agent |
| `quote.sent` | Admin sends quote to client |

### Email Log
- Full outbound email history
- Search by recipient or subject
- Detail view with headers and full body

### Audit Log
- Append-only action log: actor, actor type (admin/client/system), IP, target, detail
- Filterable by action, actor, target type, date range
- Tabs: All / Admin Actions / Client Actions

### Reports
- MRR / ARR calculation
- 12-month revenue bar chart
- Month-over-month growth percentage
- Unpaid and overdue invoice totals
- Top 10 clients by lifetime revenue
- 6-month new client chart
- Service status breakdown
- Support ticket statistics

### Automation Workflows
- Trigger-based builder: event → conditions → actions → optional delay
- Supported triggers: invoice.created, invoice.paid, service.active, domain.expiring, support.opened
- Actions: send email, create ticket, suspend service, add note
- Run history log

### Settings

#### General
- Company name, logo upload, portal tagline
- **Portal Color Theme** — 4 options: Ocean Blue, Ruby Red, Forest Green, Sky Blue (admin-selectable; applied across public portal and auth pages)
- **Domain Search TLDs** — comma-separated list of TLDs to check on domain availability searches
- Timezone and date format
- **2FA Settings** — enable/disable 2FA enforcement; session lifetime (minutes); keep-alive ping toggle

#### Company
- Company address, phone number, email

#### Billing
- Currency, invoice prefix, grace period, dunning config, late fee config, payment reminder days

#### Email
- Mailer driver (SMTP / sendmail / log)
- From address and display name
- SMTP credentials and host/port settings
- Sendmail path and flags
- Send Test button with inline result

#### Integrations (4 collapsible categories)

**Payment Gateways**
- Stripe — API key, secret, webhook secret, currency
- PayPal — client ID, client secret, mode (sandbox/live), currency
- Authorize.Net — API login ID, transaction key, sandbox toggle

**Domain Registrars**
- Driver selector (Namecheap / eNom / OpenSRS / HEXONET)
- Namecheap — API user, API key, client IP, sandbox toggle
- eNom — UID, password, sandbox toggle
- OpenSRS — API key, reseller username, sandbox toggle
- HEXONET — login, password, sandbox toggle

**Fraud Prevention**
- MaxMind minFraud — account ID, license key, score threshold, action (flag or reject)

**OAuth / Social Login**
- Google — client ID and client secret
- Microsoft — client ID and client secret

### Maintenance
- In-browser migration runner — runs `artisan migrate --force`
- **Force Schema Repair** — verifies and creates 5 critical tables (`settings`, `modules`, `servers`, `mailbox_pipes`, `tld_pricing`) without running full migrations; safe to run on a live site
- **Clear Bootstrap Cache** — removes compiled `config.php`, `routes.php`, and `services.php` from `bootstrap/cache`

---

## Client Portal

### Dashboard
- Personalised welcome banner with user name and company name
- Outstanding balance alert (amber strip) when unpaid invoices exist — shows total due with direct Pay Now link
- Stat cards: active services, unpaid invoices, open tickets, account credit — each links to the relevant section; cards highlight when non-zero
- Services list with colour-coded due-date indicator (red ≤7 days, amber ≤14 days) and billing cycle suffix
- Empty state with "Browse Plans" CTA when no services exist
- Unpaid invoices and support tickets side-by-side; billing history full-width below

### Order / Checkout
- Product catalog with type badges and billing cycle pricing
- Domain availability check (live, debounced 600ms)
- Group discount + tax applied automatically at checkout
- **Promo codes** — percent, fixed, or free-setup-fee; date window; recurring cycle limit; new-clients-only flag
- **Client notes** — optional free-text field for special instructions
- **Order numbers** — `ORD-YYYYMMDD-NNNN` displayed on confirmation
- **Fraud scoring** — orders silently scored via MaxMind minFraud before placement (if configured)

### Services
- List and detail view
- Submit cancellation request — choose immediate or end-of-period
- **Plan change** — upgrade or downgrade to any product of the same type; prorated invoice or credit applied
- **Addon management** — view active addons, add new addons from available catalog, invoice generated automatically

### Quotes
- View quotes sent by the admin team
- Accept or decline a quote
- View the invoice created from an accepted quote

### Invoices
- List and detail view
- Pay via Stripe, PayPal, or Authorize.Net (gateways shown only if configured)
- Apply credit balance toward invoice
- Download PDF

### Payment Methods
- Save cards via Stripe SetupIntent
- Set default card
- Remove saved cards
- Auto-charged on renewal (Stripe off-session)

### Support Tickets
- Create ticket: subject, department, priority, message, file attachments
- View ticket thread (client replies + staff replies + status)
- Reply with file attachments
- Download reply attachments
- Search and filter tickets by keyword and status
- Submit 1–5 star satisfaction rating on closed tickets (with optional comment)

### Domains
- List all domains with status
- Edit nameservers (up to 6)
- Toggle auto-renew

### Knowledge Base
- Browse articles by category
- Keyword search
- Article view with HTML-rendered rich content (images, headings, lists, code blocks)
- Related articles sidebar
- "Open a support ticket" nudge at bottom

### Announcements
- Paginated list of published announcements

### Affiliate Program
- Apply to join from client portal
- Unique referral code + shareable link
- Balance, total earned, and referral count dashboard
- Referral history with commission and status
- Payout request form with method field; enforces minimum threshold

### Security
- Enable / disable TOTP two-factor authentication
- QR code display and confirmation flow

### Sessions (admin/staff only)
- View all active sessions with device/IP info
- Revoke individual sessions
- Revoke all other sessions
- Not shown in client-facing navigation

---

## Public Portal

- Glassmorphism UI at the portal root
- Home page: hero, product catalog teaser, announcements teaser, KB teaser, CTA
- Full product/services catalog page
- Knowledge Base browse and article view (HTML content with `prose-invert` styling)
- Announcements listing
- **4 color themes** (admin-selectable from Settings → General): Ocean Blue, Ruby Red, Forest Green, Sky Blue; theme token applied globally
- **Company logo display** — shows uploaded logo in navigation and auth pages; falls back to a gradient letter icon if no logo is configured
- **"Powered by Strata Service Billing and Support Platform"** displayed on login and register pages
- **Domain search bar** — live availability check when a registrar is configured; shows available/taken status + price per TLD + register button
- Responsive navigation: Sign In and Get Started buttons; mobile hamburger menu

---

## Embeddable Widget (`strata-widget.js`)

Embed on any external website via `<div data-strata-widget="[type]"></div>`.

| Widget Type | Description |
|-------------|-------------|
| `catalog` | Product grid with pricing and order links |
| `announcements` | Latest published announcements |
| `kb` | Knowledge Base article listing |
| `support` | Support CTA with link to open a ticket |
| `domain-search` | Live domain availability search form |

- Themes: `glass` (dark, glassmorphism) and `light`
- CORS-open API endpoints at `/api/widget/*`

---

## Payments

| Gateway | Method |
|---------|--------|
| **Stripe** | Checkout session; off-session auto-charge; stored cards via SetupIntent; webhook reconciliation |
| **PayPal** | Orders v2 — create → redirect → capture |
| **Authorize.Net** | AIM API |

- Graceful webhook fallback — Stripe webhooks work without `STRIPE_WEBHOOK_SECRET` (skips signature verification, logs warning)
- `PaymentGateway` contract + `GatewayService` factory — extensible driver pattern
- Both Stripe and PayPal create a pending `Payment` record on initiation — double-payment guard on paid invoices
- Payment gateway buttons hidden from UI when not configured

---

## Dunning & Billing Automation

| Command | Schedule | What it does |
|---------|----------|-------------|
| `billing:generate-renewals` | Daily 08:00 | Creates renewal invoices for services due within 14 days (skips trial-active and end-of-period cancel services); also generates addon renewal invoices |
| `billing:flag-overdue` | Daily 00:05 | Marks past-due unpaid invoices overdue; sends `invoice.overdue` email |
| `billing:suspend-overdue` | Daily 01:00 | Suspends services overdue past 3-day grace; skips trial-active services; calls panel API + sends `service.suspended` email |
| `billing:process-cancellations` | Daily 00:30 | Terminates services whose end-of-period `scheduled_cancel_at` date has been reached; calls panel API + fires `service.cancelled` workflow event |
| `billing:send-reminders` | Daily 10:00 | Sends payment reminder emails per configurable day schedule |
| `billing:apply-late-fees` | Daily 02:00 | Applies fixed/percent late fee to overdue invoices past threshold |
| `billing:retry-payments` | Daily 11:00 | Retries failed Stripe auto-charges (dunning); tracks attempt count |
| `provisioning:run` | Every 5 min | Provisions paid pending hosting accounts |
| `domains:renew-expiring` | Daily 09:00 | Auto-renews active domains expiring within 30 days |
| `domains:send-reminders` | Daily 09:30 | Emails clients at 30, 14, 7 days before domain expiry |
| `support:close-inactive` | Daily 03:00 | Auto-closes tickets with no activity past configurable threshold; sets `closed_at` |
| `mail:fetch` | Every 5 min | Fetches new messages from IMAP mailbox pipes and creates support tickets; uses `withoutOverlapping` to prevent concurrent runs |

---

## Provisioning

| Panel | API |
|-------|-----|
| cPanel / WHM | WHM JSON API v1 — create, suspend, unsuspend, terminate, list packages |
| Plesk | REST API v2 — subscription lifecycle, service plans |
| DirectAdmin | HTTP API — full lifecycle, package management |
| HestiaCP | HestiaCP API — full lifecycle, package management |
| CWP (Control Web Panel) | CWP REST API v1 — full lifecycle, package management |

- `ProvisionerDriver` contract + `ProvisionerService` factory; extensible driver pattern
- Server selection by capacity
- Service updated with credentials and `module_data` on provision
- **Package sync** — admin can list all packages on any connected server, annotated with matching Strata products; import selected packages as hidden/$0 Strata products for use in the product catalog
- **Auto-create packages** — product configuration exposes disk/bandwidth/auto-create fields; at provisioning time, if the package doesn't exist on the server it is created automatically before account setup
- **Package push** — admin can create a new package directly on any panel from the Package Sync wizard

---

## Domain Registration

| Registrar | API |
|-----------|-----|
| Namecheap | XML API v1, sandbox mode |
| Enom | Reseller XML API, sandbox mode |
| OpenSRS | XCP API, HMAC-MD5 auth, sandbox mode |
| HEXONET | ISPAPI HTTP gateway, OTE sandbox + live |

- `RegistrarDriver` contract — `checkAvailability`, `registerDomain`, `renewDomain`, `transferDomain`, `getNameservers`, `setNameservers`, `getInfo`, `setLock`, `setPrivacy`
- `DomainRegistrarService` factory — `driver(?string)`, `available()`, `checkAvailability(string)`
- Live availability check in checkout (debounced 600ms)
- Active driver and credentials configurable from Settings → Integrations → Domain Registrars

---

## Shared-Hosting Compatibility

All of the following work on CWP/shared hosting without a queue worker:

- All emails sent via `Mail::send()` with silent `try/catch` — mail failure never blocks user actions
- No queue worker required — all mail and billing actions run inline
- PATCH/PUT/DELETE method-spoofed via POST (`_method` field) — compatible with restrictive ModSecurity WAF
- **ModSecurity CRS admin exemption** — `public/.htaccess` disables WAF engine for all `/admin` routes to prevent OWASP CRS anomaly scoring from blocking rich-text POST bodies (Tiptap HTML, email templates, KB articles, support tickets); admin routes are auth-protected at the app layer
- Installer bypasses CSRF and session middleware to avoid WAF false positives
- `sendmail` default flags `-t -i` (pipe mode) — compatible with CWP sendmail
- Pre-install cache driver auto-switched to `array`; base URL auto-detected from HTTP request

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 (PHP 8.3+) |
| Frontend | Vue 3 + Inertia.js v2 + Tailwind CSS v4 |
| Rich Text Editor | Tiptap v2 (`@tiptap/vue-3`) |
| Typography | `@tailwindcss/typography` — `prose` classes for HTML content |
| Build | Vite 8 |
| Database | MySQL / MariaDB 8.0+ |
| Auth / Permissions | spatie/laravel-permission |
| OAuth | Laravel Socialite |
| Payments | Stripe PHP SDK v20, srmklive/paypal v3, authorize.net SDK |
| PDF | barryvdh/laravel-dompdf v3 |
| HTTP | Laravel Http facade |

---

*Last updated: 2026-04-02 — v1.0.16*
