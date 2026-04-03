# Strata Service Billing and Support Platform

[![Buy me a coffee](https://img.shields.io/badge/Buy%20me%20a%20coffee-☕-yellow?style=flat-square)](https://buymeacoffee.com/jonathan0397)

A self-hosted billing, client management, and support platform built for web hosting providers.

---

> ## Release Notice — 1.0.16
>
> Strata is at **v1.0.16** (stable release track). The platform is production-ready for hosting businesses.
>
> **Found a bug?** [Open an issue on GitHub](https://github.com/jonathjan0397/strata/issues). See the issue tracker before filing a duplicate.
>
> - Back up your database before upgrading between versions.
> - Review `CHANGELOG.md` for migration notes before each upgrade.

---

## What is Strata?

Strata is a self-hosted billing and client management platform built for web hosting providers. Manage client accounts, automate recurring invoices, process payments through Stripe, PayPal, or Authorize.Net, provision cPanel and Plesk hosting accounts, register domains through major registrars, and handle support tickets — all through a clean, modern browser-based interface.

Strata ships as a pre-built ZIP on [GitHub Releases](https://github.com/jonathjan0397/strata/releases) and installs entirely through a browser wizard at `/install`. No CLI, Composer, or Node.js is required on the target server.

---

## Feature Highlights

### Authentication & Access Control

- Email + password login and self-registration with email verification
- **TOTP two-factor authentication** — QR code setup, challenge page, confirmation flow; admin-configurable via Settings (enable/disable, session lifetime, keep-alive toggle)
- **2FA optional** — persistent amber banner nudge for admin/staff; non-blocking
- **OAuth2 social login** via Laravel Socialite — Google and Microsoft
- Password reset via signed email link
- **Password change** — change password from Profile page; requires current password; all roles
- **Active session management** — view and revoke sessions per device/IP (admin/staff only)
- Role system: `super-admin`, `admin`, `staff`, `client` (via spatie/laravel-permission)
- **Staff permission groups** — granular scopes: billing, support, technical, clients, reports

### Browser Installer

- Multi-step wizard at `/install` — no CLI required; works on cPanel/CWP shared hosting
- PHP extension requirements check with pass/fail indicators
- Database connection test before committing credentials
- Special-character-safe `.env` write (mode `0600`; base64 transport; WAF-compatible)
- Runs migrations + seeders in-browser via `Artisan::call`
- **Queue mode selector** — Sync (shared hosting) or Database (VPS/dedicated)
- **Optional sample data** — 5 demo clients, products, services, invoices, tickets, quotes, domains, credit note, promo codes, and affiliate for immediate hands-on testing
- `storage/installed.lock` prevents re-running the installer
- `storage/installed.lock` version updated on each install

### Admin Panel

| Section | Capabilities |
|---------|-------------|
| **Dashboard** | Summary stats: clients, active services, open tickets, MRR |
| **Clients** | List, create, edit, suspend; detail with services, invoices, tickets, internal notes, group assignment; country/state/tax_exempt fields; **verify email button** (marks verified without requiring client email click) |
| **Products** | Full CRUD; type, billing cycle, price, setup fee, stock, sort order; **auto-setup trigger** (on_order/on_payment/manual/never); **trial period** (days) |
| **Services** | List and detail; suspend, unsuspend, terminate; **Approve & Provision** button; approve/reject cancellation requests; cancellation type (immediate/end-of-period) |
| **Addons** | Global addon catalog (name, price, setup fee, billing cycle); attach addons to services from admin or client portal; auto-renewal invoices via cron |
| **Quotes** | Create freeform quotes with line items, tax, valid-until date; send to client; convert accepted quote to invoice; QUO-YYYYMMDD-NNNN numbers |
| **Orders** | Searchable/filterable list with client, status, total, and date; ORD-YYYYMMDD-NNNN numbers |
| **Invoices** | List, create, detail; mark paid, cancel; line items; download A4 PDF; **credit notes** (CN-YYYYMMDD-NNNN; amount, reason, apply to invoice or balance; voidable) |
| **Support** | Full ticket queue; reply, assign, close, reopen; department transfer; merge tickets; bulk actions; SLA indicators; canned responses; internal notes; file attachments (5 × 10 MB); first-reply tracking; satisfaction rating view; **staff can manually create tickets and assign to any client** |
| **Knowledge Base** | Categories + articles; **Tiptap rich text editor** (formatting + image upload); publish toggle; full-text search |
| **Announcements** | Create, edit, delete; publish/draft toggle; **Tiptap rich text editor with inline image uploads** |
| **Servers** | cPanel/WHM, Plesk, DirectAdmin, HestiaCP server CRUD |
| **Domains** | List; detail with NS editor (up to 6), lock/privacy toggles, refresh from registrar |
| **Client Groups** | Group-level pricing (percent/fixed discount); assign clients to groups |
| **Tax Rates** | Country/state-based rules; priority resolution; `tax_exempt` per client; applied at checkout |
| **Email Templates** | Inline editor for all 11 system templates; `{{variable}}` reference panel; active/inactive toggle |
| **Email Log** | Full outbound history; search by recipient/subject; detail view with headers and body |
| **Audit Log** | Append-only action log (actor, actor type, IP, target, detail); filterable; All/Admin Actions/Client Actions tabs |
| **Reports** | MRR/ARR, 12-month revenue chart, growth %, top 10 clients, service status, support stats |
| **Workflows** | Trigger-based automation builder (conditions + actions + delay); run history log |
| **Affiliates** | Approve/deactivate affiliates; commission type and payout threshold; approve referrals to credit balance; mark payouts paid |
| **Staff** | Per-staff permission checkbox editor |
| **Settings** | General (company name, logo, tagline, portal color theme, domain search TLDs, timezone, date format, 2FA settings); Company (address, phone, email); Billing (currency, invoice prefix, grace period, late fees, dunning, payment reminders); Email (SMTP/sendmail/log, from address, credentials, test send); **Integrations** (4 collapsible categories: Payment Gateways, Domain Registrars, Fraud Prevention, OAuth/Social Login) |

### Client Portal

| Section | Capabilities |
|---------|-------------|
| **Dashboard** | Welcome banner with user name; outstanding balance alert; active services, unpaid invoices, open tickets, account credit stat cards; colour-coded service due dates; billing history |
| **Order / Checkout** | Product catalog; live domain availability check; promo codes (percent/fixed/free-setup-fee); group discount + tax; client notes; fraud scoring |
| **Services** | List and detail; submit cancellation request (immediate or end-of-period); **upgrade/downgrade plan** with prorated invoice or credit; **addons** (view active, order new) |
| **Quotes** | View quotes sent by admin; accept or decline; link to converted invoice |
| **Invoices** | List and detail; pay via Stripe, PayPal, or Authorize.Net; apply credit balance; download PDF |
| **Payment Methods** | Save/remove Stripe cards; set default; auto-charged on renewal |
| **Support** | Create tickets with file attachments; view thread; reply; search/filter; 1–5 star satisfaction rating on closed tickets |
| **Domains** | List; manage nameservers (up to 6); toggle auto-renew |
| **Knowledge Base** | Browse by category; keyword search; HTML-rendered articles with images |
| **Announcements** | Paginated published announcements |
| **Affiliate** | Apply to join; referral link; stats (balance, total earned, referral count); payout requests |
| **Security** | Enable/disable TOTP 2FA; QR code and confirmation |
| **Sessions** | View active sessions; revoke individual or all others (admin/staff only — not shown in client nav) |

### Public Portal (Glassmorphism UI)

- Home page with hero, product catalog teaser, announcements teaser, KB teaser, and CTA
- Full product/services catalog page
- Knowledge Base browse and article view
- Announcements listing
- **Domain search** — live availability check against configured registrar; shows available/taken status + price per TLD + register button; displayed when a registrar is configured
- **4 color themes** (admin-selectable): Ocean Blue, Ruby Red, Forest Green, Sky Blue
- **Branding** — company logo (if uploaded) or gradient letter icon; "Powered by Strata Service Billing and Support Platform" on login/register pages
- Responsive navigation with Sign In and Get Started buttons; mobile hamburger menu

### Embeddable Widget (`strata-widget.js`)

Embed on any external website via a `<div data-strata-widget="[type]"></div>` element. Five widget types:

| Type | Description |
|------|-------------|
| `catalog` | Product grid with pricing and order links |
| `announcements` | Latest published announcements |
| `kb` | Knowledge Base article listing |
| `support` | Support CTA with ticket link |
| `domain-search` | Live domain availability search form |

Two themes: `glass` (dark, glassmorphism) and `light`. CORS-open API endpoints at `/api/widget/*`.

### Order Management

- **Order numbers** — `ORD-YYYYMMDD-NNNN`; client notes captured at checkout
- **Auto-provisioning** — per-product trigger: `on_order`, `on_payment`, `manual`, `never`; `OrderProvisioner` service handles all paths
- **Trial periods** — `trial_days` on products; service activates immediately; invoice due at trial end; billing automation skips active trials
- **Promo codes** — percent, fixed, or free-setup-fee; date window; recurring cycle limit; new-clients-only flag; model-level enforcement
- **Cancellations** — immediate or end-of-period; scheduled cancellations processed nightly
- **Plan upgrades/downgrades** — client self-service; prorated invoice or account credit applied automatically
- **Fraud scoring** — optional MaxMind minFraud Score integration; configurable threshold + flag/reject action
- **Product addons** — optional extras attachable to services with their own billing cycle; auto-renewal invoices generated by cron
- **Affiliate program** — 30-day referral cookie; commission on first order; percent or fixed commission types

### Payments

- **Stripe Checkout** — hosted session; webhook reconciliation; stored cards via SetupIntent; off-session auto-charge on renewal
- **PayPal Orders v2** — create → redirect → capture; cancel URL support
- **Authorize.Net** — AIM API
- `PaymentGateway` contract + `GatewayService` factory — extensible driver pattern
- **Gateway flags** — payment buttons hidden when gateway is not configured (`hasStripe`, `hasPayPal`, `hasAuthorizeNet`)
- **Double-payment guard** on paid invoices; pending payment record on initiation
- **Dunning management** — configurable retry schedule; tracks attempt count

### Full Support System

- **File attachments** on tickets and replies (up to 5 × 10 MB); secure download with access control
- **Department transfer** inline from admin meta bar
- **Ticket merge** — absorbs replies and attachments from source ticket; closes source
- **SLA indicators** — color-coded dots (red = overdue, amber = within 75% of threshold); row tinting; legend
- **Bulk actions** — close, reopen, assign, delete selected tickets with select-all toggle
- **Staff-created tickets** — admin staff can create tickets on behalf of any client
- **First reply tracking** — `first_replied_at` timestamp displayed in admin meta bar
- **Satisfaction ratings** — 1–5 star rating with optional comment; displayed in admin ticket view
- Email notifications: `support.opened` (admin), `support.reply` (client), `support.assigned` (staff), `support.closed` (client auto-close)

### Knowledge Base

- **Tiptap v2** rich text editor with full formatting toolbar: bold, italic, underline, strike, H1–H3, alignment, lists, blockquote, code block, link, image upload, undo/redo
- Image upload via file picker, drag-and-drop, or clipboard paste — stored in `storage/app/public/kb-images/`
- Articles stored as HTML; rendered with `@tailwindcss/typography` `prose` classes in client and public portals
- Category CRUD with sort order; publish/draft toggle; full-text search

### Billing Automation (Scheduler)

Add the cron entry shown at the end of the installer:

```cron
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

| Command | Schedule | What it does |
|---------|----------|-------------|
| `billing:generate-renewals` | Daily 08:00 | Renewal invoices for services due within 14 days; skips trials and end-of-period cancel services; also generates addon renewal invoices |
| `billing:flag-overdue` | Daily 00:05 | Marks past-due invoices overdue; sends `invoice.overdue` email |
| `billing:suspend-overdue` | Daily 01:00 | Suspends services past 3-day grace period; skips trial-active services; sends `service.suspended` email |
| `billing:process-cancellations` | Daily 00:30 | Cancels services whose end-of-period `scheduled_cancel_at` date has been reached |
| `billing:send-reminders` | Daily 10:00 | Payment reminder emails per configurable day schedule |
| `billing:apply-late-fees` | Daily 02:00 | Fixed/percent late fee on overdue invoices past threshold |
| `billing:retry-payments` | Daily 11:00 | Retries failed Stripe auto-charges (dunning); tracks attempt count |
| `provisioning:run` | Every 5 min | Provisions paid pending hosting accounts |
| `domains:renew-expiring` | Daily 09:00 | Auto-renews active domains expiring within 30 days |
| `domains:send-reminders` | Daily 09:30 | Emails clients at 30/14/7 days before domain expiry |
| `support:close-inactive` | Daily 03:00 | Auto-closes tickets past configurable inactivity threshold |

### Provisioning

- `ProvisionerDriver` contract + `ProvisionerService` factory; extensible driver pattern
- **cPanel/WHM** — WHM JSON API v1; create, suspend, unsuspend, terminate
- **Plesk** — REST API v2; subscription lifecycle
- **DirectAdmin** — HTTP API; full lifecycle
- **HestiaCP** — HestiaCP API; full lifecycle
- Server selection by capacity; service updated with credentials and `module_data` on provision

### Domain Registration

- `RegistrarDriver` contract — `checkAvailability`, `registerDomain`, `renewDomain`, `transferDomain`, `getNameservers`, `setNameservers`, `getInfo`, `setLock`, `setPrivacy`
- **Namecheap** — XML API v1, sandbox mode
- **Enom** — reseller XML API, sandbox mode
- **OpenSRS** — XCP API, HMAC-MD5 auth, sandbox mode
- **HEXONET** — ISPAPI HTTP gateway, OTE sandbox + live
- Live availability check (debounced 600ms); auto-renew scheduler; expiry reminder emails (30/14/7 days)

### Shared-Hosting Compatibility

All features work on CWP/shared hosting without a queue worker:

- All emails use `Mail::send()` with silent `try/catch` — mail failure never blocks user-facing actions
- PATCH/PUT/DELETE method-spoofed via POST (`_method` field) — compatible with restrictive ModSecurity WAF
- `sendmail` defaults to `-t -i` pipe mode — compatible with CWP sendmail
- Installer strips session and CSRF middleware (ModSecurity compatible)
- Pre-install cache driver auto-switched to `array`; URL auto-detected from HTTP request

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | **Laravel 12** (PHP 8.3+) |
| Frontend | **Vue 3** + **Inertia.js v2** + **Tailwind CSS v4** |
| Rich Text Editor | **Tiptap v2** (`@tiptap/vue-3`) |
| Build | **Vite 8** |
| Database | MySQL / MariaDB 8.0+ |
| Auth / Permissions | spatie/laravel-permission + Laravel Socialite |
| Payments | Stripe PHP SDK v20, srmklive/paypal v3, authorize.net SDK |
| PDF | barryvdh/laravel-dompdf v3 |
| HTTP | Laravel Http facade |

---

## Quick Install (Shared Hosting — No CLI Required)

1. Download the latest **pre-built ZIP** from [GitHub Releases](https://github.com/jonathjan0397/strata/releases)
2. Extract and upload all files to your server **above** `public_html`
3. Point your domain's document root to the `public/` subdirectory
4. Visit `https://yourdomain.com/install` — the browser wizard handles everything
5. Add the cron job shown at the end of the wizard

Full step-by-step instructions, screenshots, and troubleshooting guidance are in **`README-INSTALL.md`** (included in the ZIP).

---

## Development Install

**Requirements:** PHP 8.3+, MySQL 8+ / MariaDB 10.5+, Composer 2, Node 18+

```bash
git clone https://github.com/jonathjan0397/strata.git
cd strata
composer install
npm ci && npm run build
# Point web server document root at public/ and navigate to /install
```

For local development, select **Sync** queue mode during installation unless you are running a queue worker.

---

## License

The Functional Source License 1.1, Apache 2.0 Future License (**FSL-1.1-Apache-2.0**).

- You may use, modify, and deploy Strata for your own hosting business
- You may not use this codebase to build a competing billing platform (for 2 years per version)
- Each version converts to Apache 2.0 automatically after 2 years

**© 2026 Jonathan R. Covington**

Full license text in [LICENSE.md](LICENSE.md).

---

## Bug Reports & Feedback

- **Bug reports:** [GitHub Issues](https://github.com/jonathjan0397/strata/issues)
- **Feature requests:** open an issue with the `feature-request` label
- **Email:** [Jonathan.r.covington@gmail.com](mailto:Jonathan.r.covington@gmail.com)

---

*Strata v1.0.16 — [Report issues on GitHub.](https://github.com/jonathjan0397/strata/issues)*
