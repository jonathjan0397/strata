# Strata

> **⚠ Pre-Release — Active Development**
> Strata is not yet production-ready. APIs, database schemas, and features may change between versions. v1.0 is the first recommended production release.

**Strata** is a self-hosted billing and client management platform built for web hosting providers. Manage client accounts, automate recurring invoices, process payments, provision cPanel hosting accounts, register domains, and handle support tickets — all through a clean, modern browser-based interface.

Built as a developer-friendly, self-hostable alternative to WHMCS and Blesta.

---

## What's Built (v0.8.0)

### Authentication & Access Control
- Email + password login and self-registration
- **TOTP two-factor authentication** (QR code setup, challenge page)
- **OAuth2 social login** via Laravel Socialite (Google, GitHub, and any configured provider)
- Email verification on registration
- Password reset via email
- **Active session management** — view and revoke sessions per device
- Role system: `super-admin`, `admin`, `staff`, `client` (via spatie/laravel-permission)

### Browser Installer
- Multi-step wizard at `/install` — no CLI required
- **PHP extension requirements check** with pass/fail indicators
- **Database connection test** before committing
- Writes a scoped `.env` file (mode `0600`) and runs migrations + seeders in-browser
- `storage/installed.lock` prevents re-running the installer

### Admin Panel
| Section | Capabilities |
|---------|-------------|
| **Dashboard** | Summary stats (clients, active services, open tickets, revenue) |
| **Clients** | List, create, edit, suspend; client detail with services + invoice history |
| **Products** | Full CRUD; type, billing cycle, price, setup fee, stock, sort order |
| **Services** | List and detail view; suspend, unsuspend, terminate; provisioning info |
| **Invoices** | List, create, view, download PDF; mark paid, cancel; line items |
| **Support** | Ticket queue; view thread; reply, assign to staff, close |
| **Servers** | cPanel/WHM server CRUD (hostname, API token, capacity, module config) |
| **Domains** | List (search + status filter); detail with NS editor, lock/privacy toggles, refresh from registrar |
| **Announcements** | Create, edit, delete; publish/draft toggle |
| **Email Templates** | Edit all 7 system templates inline; variable reference panel; active toggle |

### Client Portal
| Section | Capabilities |
|---------|-------------|
| **Dashboard** | Active services, unpaid invoices, recent tickets at a glance |
| **Order** | Product catalog with type badges and pricing; checkout with domain availability check |
| **Services** | List and detail view |
| **Invoices** | List and detail; pay via Stripe or PayPal; download PDF |
| **Support** | Open ticket, view thread, reply |
| **Domains** | List all domains; manage nameservers (up to 6); toggle auto-renew |
| **Announcements** | Paginated published announcements |
| **Security** | Enable/disable 2FA; generate QR code and confirm |
| **Sessions** | View active sessions; revoke individual sessions or all others |

### Payments
- **Stripe Checkout** — hosted checkout session; webhook (`checkout.session.completed` / `checkout.session.expired`) reconciles payment records and marks invoices paid
- **PayPal Orders v2** — create order → redirect to PayPal → capture on return; cancel on cancel URL
- Both gateways create a pending `Payment` record on initiation; double-payment guard on paid invoices

### Invoicing & PDF Export
- Automated invoice generation (`billing:generate-invoices --days=N`)
- Invoice status flow: `unpaid` → `overdue` → paid/cancelled
- `barryvdh/laravel-dompdf` A4 PDF with branded header, line items, tax/credit rows, payment history, footer
- Admin and client can both download invoices

### Billing Automation (Scheduler)
| Command | Schedule | What it does |
|---------|----------|-------------|
| `billing:generate-invoices` | Daily 08:00 | Creates renewal invoices for services due within 14 days |
| `billing:flag-overdue` | Daily 00:05 | Marks past-due unpaid invoices overdue; sends `invoice.overdue` email |
| `billing:suspend-overdue` | Daily 01:00 | Suspends services overdue past 3-day grace; sends `service.suspended` email |
| `provisioning:run` | Every 5 min | Provisions paid pending cPanel services |
| `domains:renew-expiring` | Daily 09:00 | Auto-renews active domains expiring within 30 days |

### cPanel / WHM Provisioning
- WHM JSON API v1 via Bearer token authentication
- `createAccount()` — generates unique username (domain-derived, 6 chars + 2 random), random password, calls `createacct`
- `suspendAccount()`, `unsuspendAccount()`, `terminateAccount()`
- Server selection: picks active module with remaining capacity
- Updates service with username, encrypted password, hostname, `module_data`

### Domain Registration
- **`RegistrarDriver` contract** — clean interface for `checkAvailability`, `registerDomain`, `renewDomain`, `transferDomain`, `getNameservers`, `setNameservers`, `getInfo`, `setLock`, `setPrivacy`
- **Namecheap driver** — Namecheap XML API v1 with sandbox mode
- **Enom driver** — Enom reseller XML API with sandbox mode
- `DomainRegistrarService` factory — `driver(?string)`, `available()`, `checkAvailability(string)`
- Live availability check in checkout (debounced 600ms, green/red inline badge)
- Auto-renew scheduler + admin refresh-from-registrar action

### Email Templates
Seven built-in templates, all editable by admins with `{{variable}}` placeholder substitution:

| Slug | Trigger |
|------|---------|
| `auth.welcome` | Client registration |
| `invoice.created` | Order placed |
| `invoice.paid` | Invoice marked paid (admin or Stripe webhook) |
| `invoice.overdue` | `billing:flag-overdue` command |
| `service.activated` | `provisioning:run` command |
| `service.suspended` | `billing:suspend-overdue` command |
| `support.reply` | Admin replies to support ticket |

All emails are queued (`ShouldQueue`), wrapped in a branded HTML layout (indigo header, 600px max-width, `.btn` class for CTA links).

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | **Laravel 12** (PHP 8.3+) |
| Frontend | **Vue 3** + **Inertia.js v2** + **Tailwind CSS v4** |
| Build | Vite 8 |
| Database | MySQL / MariaDB (SQLite for dev) |
| Queue | Laravel database queue (Redis/Horizon planned) |
| Auth | Laravel Breeze–style + spatie/laravel-permission |
| Payments | Stripe PHP SDK v20, srmklive/paypal v3 |
| PDF | barryvdh/laravel-dompdf v3 |
| OAuth | Laravel Socialite |
| HTTP | Laravel Http facade (for registrar + WHM API calls) |

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

### Queue Worker
```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

---

## Configuration

Key `.env` variables beyond the standard Laravel set:

```dotenv
# Payments
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_CURRENCY=usd

PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...
PAYPAL_MODE=live          # sandbox | live
PAYPAL_CURRENCY=USD

# Domain Registrars
REGISTRAR_DRIVER=namecheap   # namecheap | enom

NAMECHEAP_SANDBOX=false
NAMECHEAP_API_USER=
NAMECHEAP_API_KEY=
NAMECHEAP_CLIENT_IP=         # Your server's outbound IP (whitelisted in Namecheap)

ENOM_SANDBOX=false
ENOM_UID=
ENOM_PW=
```

---

## Project Structure

```
app/
├── Console/Commands/       # Artisan commands (billing automation, provisioning, domains)
├── Contracts/              # Interfaces (RegistrarDriver)
├── Http/Controllers/
│   ├── Admin/              # Admin panel controllers
│   ├── Auth/               # Authentication controllers
│   ├── Client/             # Client portal controllers
│   ├── Install/            # Browser installer
│   └── Profile/            # Session management
├── Mail/                   # TemplateMailable
├── Models/                 # Eloquent models
├── Services/
│   ├── CpanelProvisioner.php
│   ├── DomainRegistrarService.php
│   └── Registrars/         # NamecheapDriver, EnomDriver
resources/
├── js/
│   ├── Layouts/            # AppLayout.vue
│   └── Pages/
│       ├── Admin/          # Admin panel Vue pages
│       ├── Auth/           # Login, register, 2FA, etc.
│       ├── Client/         # Client portal Vue pages
│       └── Profile/        # Security, Sessions
└── views/
    └── pdf/invoice.blade.php
routes/
├── web.php                 # All web routes
└── console.php             # Scheduler
```

---

## Roadmap Summary

| Milestone | Version | Status |
|-----------|---------|--------|
| Foundation — installer, auth, schema | v0.1–v0.3 | ✅ Complete |
| Core Billing — clients, products, invoices, orders | v0.4–v0.6 | ✅ Complete |
| Payments — Stripe, PayPal, PDF, automation | v0.7–v0.8 | ✅ Complete |
| Domain Registrars — Namecheap, Enom | v0.8 | ✅ Complete |
| Email & Provisioning — cPanel/WHM | v0.7 | ✅ Complete |
| Support + Knowledge Base | v2.0–v2.1 | ⏳ Planned |
| Premium Features ⭐ | v2.2–v2.7 | ⏳ Planned |
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

Please include a clear description of the feature, the problem it solves, and any examples from other platforms.

You can also [open a GitHub issue](https://github.com/jonathjan0397/strata/issues/new?labels=feature-request&template=feature_request.md) with the `feature-request` label.

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
