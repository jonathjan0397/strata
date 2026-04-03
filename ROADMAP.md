# Strata — Product Roadmap

[![Buy me a coffee](https://img.shields.io/badge/Buy%20me%20a%20coffee-☕-yellow?style=flat-square)](https://buymeacoffee.com/jonathan0397)

**Strata Service Billing and Support Platform** is a self-hosted billing, client management, and support platform built for web hosting providers. It ships as a pre-built ZIP on [GitHub Releases](https://github.com/jonathjan0397/strata/releases) and installs entirely through a browser wizard — no CLI, Composer, or Node required on the target server.

**Release naming convention:** Releases are named by milestone (`1.0-Beta`, `1.0-Beta.2`, `1.0`, `1.1`, etc.). There were no public v0.x or v2.x releases; those were internal session tracking numbers. The product shipped publicly as `1.0-Beta` in March 2026.

See [CHANGELOG.md](CHANGELOG.md) for a detailed history of what changed in each release.

---

## Current Release: v1.0.16 (Stable)

The stable release track began at **1.0.0** (2026-04-01) and is now at **v1.0.16** (2026-04-03). The full feature set is production-ready: admin panel, client portal, public glassmorphism portal, embeddable widget, five provisioning modules (cPanel, Plesk, DirectAdmin, HestiaCP, CWP), four domain registrars, three payment gateways, full billing automation, automation workflows, affiliate program, quote system, and advanced order features.

Patch releases since 1.0.0 resolved: Active Sessions (session driver config), OAuth button visibility (settings key prefix fix), activation email delivery (sync send, resend button), portal branding consolidation, rich-text announcements, invoice list SQL GROUP BY error (BF-036), service cancellation enum gap (BF-037), client dashboard redesign (BF-038), dashboard Ziggy route crash (BF-039), and password change for all user roles.

---

## 1.1 — Client Experience

Focused on filling in the remaining gaps in the end-client and support-staff experience.

- **Client billing history** — dedicated invoice list page with date/status filters and bulk PDF download
- **Ticket search (admin)** — full-text search across ticket subjects and message bodies in the admin support queue
- **Client satisfaction ratings analytics** — aggregate rating view, per-staff averages, and rating trend over time
- **HTML reply editor on tickets** — Tiptap composer on ticket reply forms for both admin and client; file attachments on replies
- **Suggested KB articles on ticket creation** — surface related Knowledge Base articles when a client is writing a new ticket subject

---

## 1.2 — Extensibility

Developer and integrator-facing features that allow Strata to connect with external systems.

- **REST API with OpenAPI 3.0 spec** — full API coverage for all core resources (clients, invoices, services, tickets, products) with API key management and scoped permissions
- **Webhook management UI** — configure outbound webhooks per event type from the admin panel; delivery log with retry
- **Multiple contacts per client account** — additional contact records per client; each contact can receive specific notification types
- **Bulk service actions** — select multiple services in the admin list and apply suspend/unsuspend/terminate/assign-server actions

---

## 2.0 — Scale & Compliance

Features required for larger deployments and regulated markets.

- **Usage-based / metered billing** — define usage metric types (bandwidth, API calls, seats); ingest usage via API; apply tiered pricing; generate overage invoices automatically
- **Multi-currency invoicing** — per-client currency setting; exchange rate sync; invoices stored and displayed in client currency
- **Multi-language (i18n)** — full internationalization with per-client language preference; community-contributed translation files
- **GDPR data export + deletion** — client-initiated data export and account deletion with admin approval workflow
- **IP allowlisting for admin area** — restrict `/admin` access to a configurable list of IP addresses or CIDR ranges
- **Exportable reports** — download MRR/ARR, client, and service reports as CSV or PDF

---

## Future / Backlog

Items under consideration for post-2.0 releases. No committed timeline.

- **White-label reseller system** — reseller accounts with branded client portals, reseller product catalog with markup pricing, reseller credit system
- **Migration import wizard** — import clients, services, products, invoices, and tickets from WHMCS or other billing platform database dumps; dry-run mode with conflict resolution
- **PWA client portal** — Progressive Web App manifest and service worker; push notifications for payment due and ticket replies
- **Additional provisioning modules** — Pterodactyl (game servers), Proxmox VE (VPS), Cloudflare DNS
- **Multi-tenant SaaS mode** — run Strata as a hosted platform serving multiple independent billing instances from one codebase

---

## Distribution

Strata ships on two tracks. Both use the same browser installer at `/install`.

| Track | Target | How to install |
|-------|--------|---------------|
| **Shared Hosting ZIP** | cPanel / Plesk / DirectAdmin shared accounts | Download pre-built `Strata-{TAG}.zip` from GitHub Releases; `vendor/` and `public/build/` are pre-compiled; upload above `public_html`; point document root to `public/`; browse to `/install` |
| **VPS / Developer** | Root-access servers, local dev | `git clone` + `composer install` + `npm run build`; browse to `/install` |

**GitHub Actions release workflow** — pushing a `v*` or `V*` tag automatically runs `composer install --no-dev`, `npm run build`, packages the ZIP (excluding dev artifacts), and publishes a GitHub Release with the ZIP attached and install instructions in the release body. Tags containing `beta`, `alpha`, or `rc` are automatically marked as pre-releases.

---

## Milestones at a Glance

| Milestone | Focus | Status |
|-----------|-------|--------|
| **1.0-Beta** | Initial release — full feature set, browser installer, all integrations | Released 2026-03-28 |
| **1.0-Beta.2** | Portal branding, themes, domain search, integrations settings UI polish | Released 2026-03-29 |
| **1.0 Stable** | BF-015 fix, Authorize.Net Accept.js, bank transfer, upgrade wizard, doc review | Released 2026-04-01 |
| **1.0.x patches** | OAuth, email delivery, sessions, SQL errors, cancellation enum, dashboard redesign, password change | Released 2026-04-03 (latest: v1.0.16) |
| **1.1** | Client billing history, ticket search, satisfaction analytics, HTML reply editor | Planned |
| **1.2** | REST API + OpenAPI, webhook UI, multiple contacts, bulk service actions | Planned |
| **2.0** | Metered billing, multi-currency, i18n, GDPR, IP allowlist, exportable reports | Planned |
| **Future** | White-label reseller, migration wizard, PWA, Pterodactyl/Proxmox/Cloudflare, multi-tenant | Backlog |

*Last updated: 2026-04-03*
