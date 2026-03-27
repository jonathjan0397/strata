# Strata

> **⚠ Pre-Release — Active Development**
> Strata is not yet production-ready. APIs, database schemas, and features may change without notice between versions. Use in production environments is not recommended until v1.0.

**Strata** is a self-hosted billing and automation platform built for web hosting providers. Handle client accounts, recurring invoices, service provisioning, domain management, and support tickets — with a clean modern UI and a full REST API.

Built as a modern, developer-friendly alternative to WHMCS and Blesta.

---

## Features

### Core (Free — FSL Licensed)
- Client & account management
- Automated recurring invoices, payment reminders, overdue handling
- Payment processing (Stripe, PayPal, and more)
- Service provisioning — cPanel, Plesk, DirectAdmin, HestiaCP
- Domain registration, renewal & transfer (Namecheap, OpenSRS, eNom, HEXONET)
- Support ticket system with departments, canned responses, and email piping
- Knowledge base
- Full REST API with OpenAPI spec
- Docker Compose — one-command install

### Premium (30-Day Free Trial, then Commercial License)
- Advanced automation workflows (visual builder, event triggers, multi-step)
- Usage-based / metered billing
- White-label reseller system
- Affiliate management with commission tiers
- Revenue analytics (MRR, ARR, churn, cohort analysis)
- WHMCS migration wizard
- Priority support

---

## Status

| Milestone | Status |
|-----------|--------|
| v0.x — Foundation (auth, DB, Docker) | 🔄 In progress |
| v0.4–v0.9 — Core Billing | ⏳ Planned |
| v1.x — Provisioning | ⏳ Planned |
| v1.5–v1.9 — Domains | ⏳ Planned |
| v2.x — Support System | ⏳ Planned |
| v2.2–v2.7 — Premium Features | ⏳ Planned |
| v3.x — PWA, Full API, Polish | ⏳ Planned |

See [ROADMAP.md](ROADMAP.md) for the full feature breakdown.

---

## Tech Stack

- **Backend:** Laravel 11 (PHP 8.3+)
- **Frontend:** Vue 3 + Inertia.js + Tailwind CSS
- **Database:** MySQL 8 / MariaDB (PostgreSQL support planned)
- **Queue:** Redis + Laravel Horizon
- **Search:** Meilisearch (via Laravel Scout)
- **Deployment:** Docker Compose

---

## Installation

> Full installation docs will be added at v0.3. Docker Compose setup is the target workflow.

```bash
git clone https://github.com/jonathjan0397/strata.git
cd strata
cp .env.example .env
docker compose up -d
```

---

## License

The **core platform** is licensed under the [Functional Source License 1.1, Apache 2.0 Future License (FSL-1.1-Apache-2.0)](LICENSE.md).

- You may use, modify, and deploy Strata for your own hosting business
- You may not use this codebase to build a competing billing platform (for 2 years per version)
- Each version converts to Apache 2.0 automatically after 2 years
- **Premium modules** are covered by a separate commercial license

---

## Feature Requests

Have an idea for Strata? We'd love to hear it.

**Email:** [Jonathan.r.covington@gmail.com](mailto:Jonathan.r.covington@gmail.com)

Please include:
- A clear description of the feature
- The problem it solves for you
- Any examples from other platforms you've seen it done well

You can also [open a GitHub issue](https://github.com/jonathjan0397/strata/issues/new?labels=feature-request&template=feature_request.md) with the `feature-request` label.

---

## Contributing

Contributions are welcome for the core platform. Please open an issue before submitting a PR for large changes so we can align on direction.

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
