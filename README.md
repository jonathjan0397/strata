# Strata

> **Pre-Release — Active Development**
> Strata is not yet production-ready. APIs, database schemas, and features may change between versions. v1.0 is the first recommended production release.

**Strata** is a self-hosted billing and client management platform built for web hosting providers. Manage client accounts, automate recurring invoices, process payments, provision cPanel hosting accounts, register domains, and handle support tickets — all through a clean, modern browser-based interface.

A developer-friendly, self-hostable hosting billing and client management platform.

---

## What's Built (v1.8.x)

### Authentication & Access Control
- Email + password login and self-registration
- **TOTP two-factor authentication** (QR code setup, challenge page)
- **2FA optional** with persistent amber warning banner for admin/staff
- **OAuth2 social login** via Laravel Socialite (Google, GitHub, any provider)
- Email verification on registration; password reset via email
- **Active session management** — view and revoke sessions per device
- Role system: `super-admin`, `admin`, `staff`, `client` (via spatie/laravel-permission)
- **Staff permission groups** — granular scopes: billing, support, technical, clients, reports

### Browser Installer
- Multi-step wizard at `/install` — no CLI required; works on CWP/shared hosting
- PHP extension requirements check with pass/fail indicators
- Database connection test before committing
- Special-character-safe password handling (base64 transport, WAF-compatible)
- Writes a scoped `.env` file (mode `0600`) and runs migrations + seeders in-browser
- `storage/installed.lock` prevents re-running the installer

### Admin Panel

| Section | Capabilities |
|---------|-------------|
| **Dashboard** | Summary stats: clients, active services, open tickets, MRR |
| **Clients** | List, create, edit, suspend; detail with services, invoices, tickets, internal notes, group assignment |
| **Products** | Full CRUD; type, billing cycle, price, setup fee, stock, sort order |
| **Services** | List and detail; suspend, unsuspend, terminate; approve/reject cancellation requests |
| **Invoices** | List, create, view, download PDF; mark paid, cancel; line items |
| **Support** | Full ticket queue; reply, assign, close, reopen; department transfer; merge tickets; bulk actions; SLA indicators; canned responses; internal notes; file attachments; first-reply tracking; satisfaction rating view |
| **Servers** | cPanel/WHM/Plesk/DirectAdmin/HestiaCP server CRUD |
| **Domains** | List; detail with NS editor, lock/privacy toggles, refresh from registrar |
| **Client Groups** | Group-level pricing (percent/fixed discount); assign clients to groups |
| **Tax Rates** | Country/state-based tax rules; priority resolution; applied at checkout |
| **Announcements** | Create, edit, delete; publish/draft toggle |
| **Email Templates** | Inline editor for all 10 system templates; `{{variable}}` reference panel; active toggle |
| **Email Log** | Full outbound history; search by recipient/subject; detail view |
| **Audit Log** | Append-only action log (actor, IP, target); filterable; admin/client/system tabs |
| **Reports** | MRR/ARR, 12-month revenue chart, growth %, top clients, service status, support stats |
| **Workflows** | Trigger-based automation (conditions + actions + delay); run history |
| **Knowledge Base** | Categories + articles; **Tiptap rich text editor** (formatting + images); publish toggle |
| **Staff** | Per-staff permission editor |
| **Settings** | App, email (SMTP/sendmail/test), billing, payments, maintenance |

### Client Portal

| Section | Capabilities |
|---------|-------------|
| **Dashboard** | Active services, unpaid invoices, recent tickets |
| **Order** | Product catalog; live domain availability check; group discount + tax at checkout |
| **Services** | List and detail; submit cancellation request |
| **Invoices** | List and detail; pay via Stripe, PayPal, or Authorize.net; apply credit; download PDF |
| **Payment Methods** | Save/remove Stripe cards; set default; auto-charged on renewal |
| **Support** | Create tickets with file attachments; view thread; reply with attachments; download files; search/filter tickets; 1–5 star satisfaction rating on closed tickets |
| **Domains** | List; manage nameservers (up to 6); toggle auto-renew |
| **Knowledge Base** | Browse by category; search; **HTML-rendered articles with images** |
| **Announcements** | Paginated published announcements |
| **Security** | Enable/disable TOTP 2FA; QR code and confirmation |
| **Sessions** | View active sessions; revoke individual or all others |

### Payments
- **Stripe Checkout** — hosted checkout; webhook reconciliation; stored cards with SetupIntent; off-session auto-charge on renewal
- **Stripe webhook** — works with or without `STRIPE_WEBHOOK_SECRET` configured (graceful fallback)
- **PayPal Orders v2** — create → redirect → capture; cancel URL support
- **Authorize.net** — AIM API; Accept.js opaque data or stored Customer Payment Profile
- `PaymentGateway` contract + `GatewayService` factory — extensible driver pattern
- **Gateway flags** — `hasStripe` / `hasPayPal` — payment buttons hidden when gateway is not configured
- **Dunning management** — configurable retry schedule; tracks attempt count; fires workflow on recovery
- Double-payment guard on paid invoices

### Full Support System
- **File attachments** on tickets and replies (up to 5 × 10 MB); secure download with access control
- **Department transfer** inline (admin meta bar)
- **Ticket merge** — absorbs replies + attachments from source, closes source ticket
- **SLA indicators** — color-coded dots (red = overdue, amber = within 75% of threshold); row tinting; legend
- **Bulk actions** — close, reopen, assign, delete selected tickets
- **Staff assignment emails** (`support.assigned`) — notifies agent on ticket assignment
- **Admin new-ticket notification** (`support.opened`) — fires when client submits ticket
- **Auto-close notification** (`support.closed`) — sent to client when ticket auto-closes
- **First reply tracking** — `first_replied_at` timestamp + display in admin meta bar
- **Satisfaction ratings** — 1–5 star hover rating with optional comment; displayed in admin ticket view
- **Client search/filter** — live keyword search + status dropdown filter

### Knowledge Base — Rich Text Editor
- **Tiptap v2** editor with full formatting toolbar: bold, italic, underline, strike, H1–H3, text align, lists, blockquote, code block, link, undo/redo
- **Image support** — file picker upload, drag-and-drop, clipboard paste → stored in `storage/app/public/kb-images/`
- **HTML storage** — articles stored as HTML; rendered with `v-html` + `@tailwindcss/typography` `prose` classes in client portal and public portal
- `POST /admin/kb/images` upload endpoint (validates image, max 5 MB)

### Invoicing & PDF Export
- Automated invoice generation (`billing:generate-invoices --days=N`)
- Invoice status flow: `unpaid` → `overdue` → paid/cancelled
- `barryvdh/laravel-dompdf` A4 PDF with branded header, line items, tax/credit rows, payment history, footer

### Billing Automation (Scheduler)

| Command | Schedule | What it does |
|---------|----------|-------------|
| `billing:generate-invoices` | Daily 08:00 | Renewal invoices for services due within 14 days |
| `billing:flag-overdue` | Daily 00:05 | Marks past-due invoices overdue; sends email |
| `billing:suspend-overdue` | Daily 01:00 | Suspends services past 3-day grace; sends email |
| `billing:send-reminders` | Daily 10:00 | Payment reminder emails per configurable schedule |
| `billing:apply-late-fees` | Daily 02:00 | Fixed/percent late fee on overdue invoices past threshold |
| `billing:retry-payments` | Daily 11:00 | Retries failed Stripe auto-charges (dunning) |
| `provisioning:run` | Every 5 min | Provisions paid pending hosting accounts |
| `domains:renew-expiring` | Daily 09:00 | Auto-renews active domains expiring within 30 days |
| `domains:send-reminders` | Daily 09:30 | Emails clients at 30/14/7 days before domain expiry |
| `support:close-inactive` | Daily 03:00 | Auto-closes tickets past configurable inactivity threshold |

### Provisioning
- `ProvisionerDriver` contract + `ProvisionerService` factory
- **cPanel/WHM** — WHM JSON API v1; create/suspend/unsuspend/terminate
- **Plesk** — REST API v2; subscription lifecycle
- **DirectAdmin** — HTTP API; full lifecycle
- **HestiaCP** — HestiaCP API; full lifecycle
- Server selection by capacity; service updated with credentials and `module_data`

### Domain Registration
- `RegistrarDriver` contract — `checkAvailability`, `registerDomain`, `renewDomain`, `transferDomain`, `getNameservers`, `setNameservers`, `getInfo`, `setLock`, `setPrivacy`
- **Namecheap** — XML API v1, sandbox mode
- **Enom** — reseller XML API, sandbox mode
- **OpenSRS** — XCP API, HMAC-MD5 auth, sandbox mode
- **HEXONET** — ISPAPI HTTP gateway, OTE sandbox + live
- Live availability check in checkout (debounced 600ms); auto-renew scheduler; expiry reminder emails

### Shared-Hosting Compatibility
All features work on CWP/shared hosting without a queue worker:
- All emails use `Mail::send()` with silent `try/catch` — mail failure never blocks user actions
- PATCH/PUT/DELETE method-spoofed via POST (`_method` field) — WAF compatible
- `sendmail` defaults to `-t -i` (pipe mode)
- Bootstrap cache cleared on every deploy

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | **Laravel 12** (PHP 8.3+) |
| Frontend | **Vue 3** + **Inertia.js v2** + **Tailwind CSS v4** |
| Rich Text Editor | **Tiptap v2** (`@tiptap/vue-3`) |
| Build | Vite 8 |
| Database | MySQL / MariaDB (SQLite for dev) |
| Auth | spatie/laravel-permission |
| Payments | Stripe PHP SDK v20, srmklive/paypal v3 |
| PDF | barryvdh/laravel-dompdf v3 |
| OAuth | Laravel Socialite |
| HTTP | Laravel Http facade |

---

## Installation

### Requirements
- PHP 8.3+ with: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`
- MySQL 8+ or MariaDB 10.5+
- Composer 2
- Node.js 18+ + npm (for building frontend assets)
- A web server (Apache or Nginx) pointed at the `public/` directory

### Quick Start

```bash
# 1. Clone
git clone https://github.com/jonathjan0397/strata.git
cd strata

# 2. Install PHP dependencies
composer install --no-dev --optimize-autoloader

# 3. Build frontend assets
npm ci && npm run build

# 4. Copy env template
cp .env.example .env
php artisan key:generate

# 5. Point your web server at public/ and navigate to /install
```

The browser installer will guide you through:
- PHP requirements check
- Database connection configuration + test
- Application name and URL
- Initial admin account creation
- Running migrations and seeders

### Scheduler
Add one cron entry to run Laravel's scheduler:
```cron
* * * * * cd /path/to/strata && php artisan schedule:run >> /dev/null 2>&1
```

> **No queue worker required.** All background tasks run through the scheduler on shared hosting.

---

## Configuration

Key `.env` variables beyond the standard Laravel set:

```dotenv
# Payments
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...   # optional — skipped if not set
STRIPE_CURRENCY=usd

PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...
PAYPAL_MODE=live          # sandbox | live
PAYPAL_CURRENCY=USD

AUTHORIZENET_API_LOGIN_ID=
AUTHORIZENET_TRANSACTION_KEY=
AUTHORIZENET_SANDBOX=true

# Domain Registrars
REGISTRAR_DRIVER=namecheap   # namecheap | enom | opensrs | hexonet

NAMECHEAP_SANDBOX=false
NAMECHEAP_API_USER=
NAMECHEAP_API_KEY=
NAMECHEAP_CLIENT_IP=

ENOM_SANDBOX=false
ENOM_UID=
ENOM_PW=

OPENSRS_SANDBOX=true
OPENSRS_API_KEY=
OPENSRS_RESELLER_USERNAME=

HEXONET_SANDBOX=true
HEXONET_LOGIN=
HEXONET_PASSWORD=
```

Mail and billing settings are configurable at runtime through **Admin → Settings** — no `.env` changes needed after initial setup.

---

## Project Structure

```
app/
├── Console/Commands/       # Artisan commands (billing, provisioning, domains, support)
├── Contracts/              # Interfaces (PaymentGateway, RegistrarDriver, ProvisionerDriver)
├── Http/Controllers/
│   ├── Admin/              # Admin panel controllers
│   ├── Auth/               # Authentication controllers
│   ├── Client/             # Client portal controllers
│   ├── Install/            # Browser installer
│   └── Profile/            # Session management
├── Mail/                   # TemplateMailable
├── Models/                 # Eloquent models (incl. SupportTicket, TicketAttachment, KbArticle)
└── Services/               # GatewayService, ProvisionerService, DomainRegistrarService, etc.
resources/
├── js/
│   ├── Components/         # Shared Vue components (StatusBadge, TiptapEditor)
│   ├── Layouts/            # AppLayout, PortalLayout, GuestLayout
│   └── Pages/
│       ├── Admin/          # Admin panel Vue pages
│       ├── Auth/           # Login, register, 2FA, etc.
│       ├── Client/         # Client portal Vue pages
│       ├── Portal/         # Public portal pages
│       └── Profile/        # Security, Sessions
└── views/
    └── pdf/invoice.blade.php
routes/
├── web.php                 # All web routes
└── console.php             # Scheduler
database/
├── migrations/             # All schema migrations
└── seeders/                # Roles, email templates
```

---

## Roadmap Summary

| Milestone | Version | Status |
|-----------|---------|--------|
| Foundation — installer, auth, schema | v0.1–v0.3 | ✅ Complete |
| Core Billing — clients, products, invoices, payments, tax, dunning | v0.4–v0.8 | ✅ Complete |
| Provisioning — cPanel, Plesk, DirectAdmin, HestiaCP | v1.0–v1.4 | ✅ Complete |
| Domain Registrars — Namecheap, Enom, OpenSRS, HEXONET | v1.5–v1.9 | ✅ Complete |
| Full Support System — attachments, ratings, SLA, bulk, merge, email templates | v1.7.0 | ✅ Complete |
| Knowledge Base — Tiptap rich text editor with image upload | v1.8.0 | ✅ Complete |
| Premium — Workflows + Reports Dashboard | v2.0–v2.2 | 🔄 Partial |
| Usage Billing, Reseller, Affiliate | v2.3–v2.5 | ⏳ Planned |
| PWA, Full API, Compliance | v3.x | ⏳ Planned |

See [ROADMAP.md](ROADMAP.md) for the full breakdown.

---

## License

The **core platform** is licensed under the [Functional Source License 1.1, Apache 2.0 Future License (FSL-1.1-Apache-2.0)](LICENSE.md).

- You may use, modify, and deploy Strata for your own hosting business
- You may not use this codebase to build a competing billing platform (for 2 years per version)
- Each version converts to Apache 2.0 automatically after 2 years
- **Premium modules** are covered by a separate commercial license

---

## Feature Requests & Feedback

**Email:** [Jonathan.r.covington@gmail.com](mailto:Jonathan.r.covington@gmail.com)

[Open a GitHub issue](https://github.com/jonathjan0397/strata/issues/new?labels=feature-request&template=feature_request.md) with the `feature-request` label.

---

## Contributing

Contributions are welcome for the core platform. Please open an issue before submitting a PR for large changes.

- Fork the repo
- Create a feature branch (`git checkout -b feature/my-feature`)
- Commit your changes
- Open a pull request against `main`

---

## Support

- **Feature requests & feedback:** [Jonathan.r.covington@gmail.com](mailto:Jonathan.r.covington@gmail.com)
- **Bug reports:** [GitHub Issues](https://github.com/jonathjan0397/strata/issues)
- **Premium support:** Available to commercial license holders

---

*Strata is pre-release software. Star the repo to follow progress.*
