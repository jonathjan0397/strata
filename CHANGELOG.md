# Changelog

All notable changes to Strata are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Planned (next priorities)
- Client billing history page (full invoice list with filters + PDF download)
- Authorize.net Accept.js Vue component (client-side card entry)

---

## [V1-Beta] — 2026-03-28 — Fresh Install Hardening & Release Pipeline

### Added
- **GitHub Actions release workflow** — tag-triggered CI builds `Strata-V1-Beta.zip` with `vendor/` and `public/build/` pre-compiled; attaches to GitHub Release with install instructions; auto-detects beta/alpha/rc tags for pre-release flag
- **Pre-install URL auto-detection** (`AppServiceProvider`) — when `APP_URL` is still `http://localhost`, detects real base URL from the incoming request and calls `URL::forceRootUrl()` so Ziggy and `route()` helpers generate correct URLs before the installer runs
- **Subdirectory install support** — all 22+ hardcoded absolute paths in Portal pages replaced with Ziggy `route()` calls; `ASSET_URL` written to `.env` by the installer; `CheckInstalled` redirect and `AppServiceProvider` URL detection both respect subdirectory prefixes via `getBaseUrl()`
- **Install README troubleshooting section** — documents 403 Forbidden (subdirectory, AllowOverride, mod_rewrite), missing storage directories, and root@localhost errors with specific CWP/cPanel guidance
- **Release notes rewrite** — Quick Install instructions now reflect the actual CWP/cPanel subdomain workflow; wizard auto-launches, no manual `/install` navigation needed

### Fixed
- **BF-026** — `HandleInertiaRequests::share()` flash closures called `$request->session()` without checking `hasSession()` first; threw `RuntimeException` on all install route requests where `StartSession` is stripped (fresh install 500)
- **BF-027** — `CheckInstalled` issued `redirect('/install')` — absolute path ignored subdirectory prefix; fixed with `getSchemeAndHttpHost() . getBaseUrl() . '/install'`
- **BF-028** — Laravel 12 default cache driver is `database`; before installer runs no `.env` exists so cache queries hit `root@localhost` with no password; fixed by switching cache to `array` driver pre-install in `AppServiceProvider`
- **BF-029** — Pre-install DB connection error logged as 500 (duplicate of BF-028 root cause, same fix)
- **BF-030** — `withoutMiddleware` on install routes listed `App\Http\Middleware\VerifyCsrfToken` which does not exist in Laravel 12; actual class is `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken`; exclusion was a no-op causing CSRF middleware to call `$request->session()` and throw on POST install routes
- **BF-031** — Release ZIP `--exclude "storage/framework/sessions/*"` wildcard also excluded `.gitkeep` files; ZIP dropped those directories entirely; fresh installs failed with "directory does not exist"; fixed by re-adding `.gitkeep` files after the main zip command
- **BF-032** — Sample data seeder had 9 column/enum mismatches against actual migrations (`uses` → `uses_count`, `valid_from` → `starts_at`, `valid_until` → `expires_at`, `active` → `is_active` on promo_codes; product type `hosting` → `shared`; announcement `content` → `body`, removed non-existent `pinned`; payment status `paid` → `completed`; quote status `pending` → `sent`)

### Changed
- **Rebranded** — official name updated to **Strata Service Billing and Support Platform** across `config/app.php`, `.env.example`, `PortalLayout.vue`, `README.md`, `README-INSTALL.md`, and release workflow
- **Copyright notice** — `© 2026 Jonathan R. Covington` added to portal footer and install documentation
- **`@vitejs/plugin-vue`** upgraded from `^5.1` to `^6.0` to resolve npm peer dependency conflict with Vite 8 in the release workflow
- **`package-lock.json`** regenerated after plugin-vue upgrade

---

## [2.0.0] — 2026-03-28 — Product Addons & Affiliate System

### Added

#### Product Addons (Gap 13)
- **Addons catalog** — admin creates global addon products: name, description, price, setup fee, billing cycle, active flag, sort order
- **Admin CRUD** — `Admin/Addons/Index.vue` + `Admin/Addons/Form.vue`; routes `admin.addons.*`
- **Attach addons to services** — admin service show page has add/remove addon panel; creates `ServiceAddon` record and an invoice covering price + setup fee
- **Client addon panel** — client service show page lists active addons and allows ordering new ones (creates invoice; status set to `pending` until invoice paid)
- **`service_addon_id` on invoice_items** — nullable FK; mirrors `service_id` for renewal tracking
- **Addon renewal invoices** — `billing:generate-renewals` now runs a second pass for `ServiceAddon` records whose `next_due_date` is within the window and have no outstanding invoice

#### Affiliate System (Gap 14)
- **`affiliates` table** — `code` (unique), `commission_type` (percent/fixed), `commission_value`, `balance`, `total_earned`, `payout_threshold`, `status` (pending/active/inactive)
- **`affiliate_referrals` table** — links affiliate → referred user → first order; tracks `amount`, `commission`, `status` (pending/approved/paid/rejected)
- **`affiliate_payouts` table** — payout requests: affiliate, amount, method, notes, status (pending/paid), processed_at
- **Referral cookie tracking** — `TrackAffiliateReferral` middleware reads `?ref=CODE` on any page visit and sets a 30-day `strata_ref` cookie
- **Registration hook** — on user registration, the `strata_ref` cookie is read; if an active affiliate is found, an `AffiliateReferral` record is created with `referred_user_id`
- **Commission on first order** — `OrderController::place()` finds the pending referral for the client and records `order_id`, `amount`, and `commission` (calculated via `Affiliate::calculateCommission()`)
- **Admin affiliate management** — `Admin/Affiliates/Index.vue` (paginated list) + `Admin/Affiliates/Show.vue` (stats, referral list, payout history, commission settings); approve / deactivate / reactivate affiliate; approve individual referrals (credits commission to balance); mark payouts as paid
- **Client affiliate dashboard** — `Client/Affiliate/Dashboard.vue` — apply to join, referral link with copy button, stats (balance, total earned, referral count), referral history, payout request form (enforces `payout_threshold`)
- **`User::affiliate()` relationship** — `HasOne` added to User model

---

## [1.9.0] — 2026-03-28 — Order Flow & Billing Management

### Added

#### Order Flow
- **Human-readable order numbers** — `ORD-YYYYMMDD-NNNN` format; generated atomically after insert; shown in admin order list and service show
- **Client notes on orders** — free-text field at checkout; shown in admin service detail and order card
- **Auto-provision trigger per product** (`autosetup` field): `on_order` (provision immediately), `on_payment` (provision when invoice paid), `manual` (admin approves), `never`
- **`OrderProvisioner` service** — centralised provisioning engine called by all paths: web request (`on_order`), payment webhooks (`on_payment`), admin manual approval, Artisan cron
- **Welcome email with credential variables** — `service.activated` template updated to include `{{username}}`, `{{password}}`, `{{server}}`, `{{nameserver1}}`, `{{nameserver2}}`
- **Approve & Provision button** on admin service show page; one-click provisioning for pending services

#### Promo Code Enhancements
- **Free setup fee type** (`free_setup`) — waives setup fee, shows "Setup fee waived" label at checkout
- **Date window** (`starts_at`, `expires_at`) — promo codes can be restricted to a date range
- **Recurring cycles** (`recurring_cycles`) — `null` = first invoice only, `n` = n cycles, `-1` = always applied
- **New clients only** (`new_clients_only`) — restricts code to clients with no active/suspended services
- **`PromoCode::isValid(?User)`** — model-level enforcement; `applies_once` and `new_clients_only` checks run regardless of code path
- **`PromoCode::calculateDiscount(float $price, float $setupFee)`** — signature separates price from setup fee to correctly support `free_setup` type

#### Cancellation Improvements
- **Cancellation type** — client chooses `immediate` or `end_of_period` when requesting cancellation
- **End-of-period cancellation** — sets `scheduled_cancel_at = next_due_date`; service stays `active`; renewal invoice generation and overdue suspension both skip scheduled-cancel services
- **`billing:process-cancellations`** command — daily 00:30; cancels services whose `scheduled_cancel_at` date has been reached
- Admin service show: amber banner for both pending and scheduled cancellations; cancellation type display

#### Trial Periods
- **`products.trial_days`** — integer; when set, service activates immediately regardless of `autosetup`
- **`services.trial_ends_at`** — trial expiry date; set to `now() + trial_days` on order placement
- Invoice due date set to `trial_ends_at` (not the standard `invoice_due_days` setting)
- `billing:generate-renewals` and `billing:suspend-overdue` both skip services in active trial
- Client service show: "Free Trial Active" indigo banner with expiry date
- Admin service show: trial end date displayed in service details card

#### Service Upgrade / Downgrade with Proration
- Client can change plan from service show page — products of same type, not hidden, not current
- Proration: remaining days × (new daily rate − old daily rate)
  - Net positive → prorated invoice created (due 7 days); notes reference old → new product
  - Net negative → difference added as account credit immediately
- Route `POST client/services/{service}/upgrade`
- Plan selector labels each option as **Upgrade** or **Downgrade** with price and billing cycle

#### Fraud Check (MaxMind minFraud Score)
- **`FraudChecker::evaluate()`** service — calls minFraud Score API before any DB writes on order placement
- Graceful fallback: API errors logged as warnings; order always proceeds if API is unreachable
- Two actions: `flag` (stores score on order, order proceeds) or `reject` (blocks order, returns error)
- `orders.fraud_score` and `orders.fraud_flags` columns; score visible to admin
- Settings → Integrations: account ID, license key, score threshold (0–100), and action selector

#### Quote System
- Admin creates quotes with freeform line items, optional tax rate, valid-until date, client message, internal notes
- **Quote numbers**: `QUO-YYYYMMDD-NNNN`
- **Status lifecycle**: `draft → sent → accepted / declined`
- Admin sends quote — transitions to `sent`; emails client with `quote.sent` template (seeded on migration)
- Client accepts or declines from `/client/quotes/{id}` — expired-quote notice shown if past `valid_until`
- Admin converts accepted quote to invoice (`POST admin/quotes/{id}/convert`) — `converted_invoice_id` links quote to invoice
- 5 new Vue pages: `Admin/Quotes/Index`, `Admin/Quotes/Form`, `Admin/Quotes/Show`, `Client/Quotes/Index`, `Client/Quotes/Show`

### Changed
- `ProvisionPendingServices` command rewritten — filters by `autosetup = on_payment`, delegates entirely to `OrderProvisioner::provision()`; removed duplicate credential logic and `Mail::queue()` calls
- `OrderController::place()` — service created before order item (fixes `service_id` always being null on `order_items`); promo validation moved into `PromoCode::isValid()`
- `Admin/ServiceController::approveCancellation()` — branches on `cancellation_type`; end-of-period sets `scheduled_cancel_at`, immediate marks `cancelled`
- `Client/ServiceController::requestCancellation()` — now validates and stores `cancellation_type`
- `Client/PromoController::validate()` — accepts `price` + `setup_fee` instead of `subtotal`; passes user to `isValid()`; returns 'Setup fee waived' label for `free_setup`
- All invoice-paid paths (admin mark-paid, Stripe webhook, PayPal capture, Authorize.net capture, client apply-credit) now call `OrderProvisioner::handleInvoicePaid()`
- Admin Products Form — added Auto Setup dropdown; Trial Period days field
- Client Services Show — cancellation type radio (end-of-period / immediate); upgrade/downgrade plan panel

### Database
- `products`: `autosetup` enum, `trial_days` smallint
- `orders`: `order_number` varchar, `client_notes` text, `fraud_score` decimal, `fraud_flags` json
- `services`: `cancellation_type` enum, `scheduled_cancel_at` date, `trial_ends_at` date
- `promo_codes`: `free_setup` added to type enum, `starts_at` timestamp, `recurring_cycles` smallint, `new_clients_only` boolean
- `quotes` table (new)
- `quote_items` table (new)
- `email_templates`: `service.active` updated with credential variables; `quote.sent` seeded

---

## [1.8.0] — 2026-03-28 — Knowledge Base Rich Text Editor

### Added

#### Tiptap Rich Text Editor (Knowledge Base)
- `resources/js/Components/TiptapEditor.vue` — full Tiptap v2 editor component
  - Toolbar: bold, italic, underline, strikethrough, H1/H2/H3, text align (left/center), bullet list, ordered list, blockquote, code block, link (prompt dialog), image upload, undo, redo
  - **Image upload** — file picker button triggers `POST /admin/kb/images`; drag-and-drop onto editor canvas; clipboard paste (Ctrl+V image) — all three paths upload and insert the image URL
  - Placeholder text support
  - Scoped CSS for editor content: headings, lists, blockquote, code/pre, image selection ring, link styling
- `Admin/Kb/Edit.vue` — replaced plain `<textarea>` with `<TiptapEditor v-model="form.body" />`
- `KbController::uploadImage()` — validates uploaded image (max 5 MB), stores to `public` disk under `kb-images/`, returns `{ url }` JSON
- Route `POST admin/kb/images` → `admin.kb.images.upload`
- `Client/Kb/Show.vue` — article body now rendered with `v-html` (was `{{ article.body }}` plain text); `whitespace-pre-wrap` removed
- `Portal/KB/Show.vue` — same `v-html` switch for public portal article view; `style="white-space: pre-wrap"` wrapper removed
- `@tailwindcss/typography` installed; `@plugin "@tailwindcss/typography"` added to `resources/css/app.css` — enables `prose` and `prose-invert` classes for rendered HTML content

### Dependencies added
- `@tiptap/vue-3`, `@tiptap/pm`, `@tiptap/starter-kit`, `@tiptap/extension-image`, `@tiptap/extension-link`, `@tiptap/extension-placeholder`, `@tiptap/extension-text-align`, `@tiptap/extension-underline`
- `@tailwindcss/typography`

---

## [1.7.0] — 2026-03-28 — Full Support System Feature Set + Payment Gateway Fixes

### Added

#### Support — File Attachments
- `ticket_attachments` table: `ticket_id`, `reply_id` (nullable), `user_id`, `filename`, `path`, `size`, `mime_type`
- `TicketAttachment` model with `humanSize()` helper and `downloadUrl()` accessor
- `TicketAttachmentController::download()` — role-aware access control (staff see all; client sees own tickets only); streams file from `storage/public` disk
- Attachments stored under `ticket-attachments/{ticket_id}/`
- File picker on `Client/Support/Create.vue` — dashed-border drop zone, removable pending files list, `forceFormData: true`
- File picker on `Client/Support/Show.vue` (reply form) — same UX
- File picker on `Admin/Support/Show.vue` (reply form) — same UX; `accept="*/*"`
- Reply thread in client and admin views shows attachment chips with file icon, filename, size, and download link
- Routes: `GET client/support/attachments/{attachment}/download` and `GET admin/support/attachments/{attachment}/download`

#### Support — Ticket Ratings
- `rating` (tinyint, nullable), `rating_note` (varchar 500, nullable) columns added to `support_tickets`
- `Client/Support/Show.vue` — closed-ticket state shows 5-star hover rating with optional comment textarea and submit button
- Already-rated state shows submitted stars + note
- `Client\SupportController::rate()` — validates 1–5 star; one-time only (aborts if already rated)
- Route: `POST client/support/{ticket}/rate`

#### Support — Bulk Admin Actions
- `Admin\SupportController::bulkAction()` — actions: `close`, `reopen`, `assign`, `delete`; accepts array of ticket IDs
- `Admin/Support/Index.vue` — checkbox column with select-all toggle; animated bulk action bar (Transition); assign dropdown populated from staff list
- Route: `POST admin/support/bulk`

#### Support — Department Transfer
- `Admin\SupportController::transferDepartment()` — PATCH; updates department; adds an internal note recording the change
- `Admin/Support/Show.vue` — inline department `<select>` in meta bar triggers PATCH on `@change`
- Route: `PATCH admin/support/{ticket}/department`

#### Support — Ticket Merge
- `Admin\SupportController::merge()` — moves all replies and attachments from source ticket to target; closes and saves merged note on source
- `Admin/Support/Show.vue` — collapsible merge panel with ticket ID input and Merge button; validation error display
- Route: `POST admin/support/{ticket}/merge`

#### Support — SLA Indicators
- SLA thresholds computed in Vue: urgent=4h, high=8h, medium=24h, low=72h
- `Admin/Support/Index.vue` — colored dot per ticket: red (overdue), amber (within 75% of threshold), gray (ok); row background tinted for overdue/warning
- SLA legend at bottom of ticket list

#### Support — Tracking Fields
- `first_replied_at` (timestamp, nullable) and `closed_at` (timestamp, nullable) added to `support_tickets`
- `Admin\SupportController::reply()` — sets `first_replied_at` on first staff reply; updates ticket status to `answered`
- `Admin\SupportController::close()` — sets `closed_at`
- `CloseInactiveTickets` command — sets `closed_at` on auto-closed tickets
- `Admin/Support/Show.vue` — first reply time displayed in meta bar (e.g. "1h 23m")

#### Support — Staff Assignment Emails
- `support.assigned` email template seeded: notifies assigned staff member with ticket link
- `Admin\SupportController::assign()` — fires `support.assigned` email to new assignee; detects assignee change

#### Support — Admin New-Ticket Notification
- `support.opened` email template seeded: `[{{priority}}] New Ticket #{{ticket_id}}: {{ticket_subject}}`; sent to admin email on ticket creation
- `Client\SupportController::store()` — fires `support.opened` email after ticket creation

#### Support — Auto-Close Notification
- `support.closed` email template seeded: notifies client when ticket is auto-closed
- `CloseInactiveTickets` command — fires `support.closed` email (silent catch)

#### Support — Client Search & Filter
- `Client\SupportController::index()` — `search` (subject LIKE) and `status` filter parameters; `withQueryString()` pagination
- `Client/Support/Index.vue` — search input + status dropdown; watch-driven `router.get` on change; priority dot column

#### Support — Admin Agent Filter
- `Admin/Support/Index.vue` — `assigned_to` filter: All / Me / Unassigned / individual agent; passed to backend query

#### Email Templates (new)
- Migration `2026_03_27_220002_seed_support_email_templates.php` seeds three templates via `EmailTemplate::updateOrCreate` (works via maintenance endpoint which only runs migrations, not seeders):
  - `support.opened` — admin notification on new ticket
  - `support.closed` — client notification on auto-close
  - `support.assigned` — staff notification on assignment

#### Payment Gateway Fixes
- `hasStripe` and `hasPayPal` boolean flags added to `Client\InvoiceController::show()` Inertia props
- `Client/Invoices/Show.vue` — Stripe pay button hidden via `v-if="hasStripe"`; PayPal button hidden via `v-if="hasPayPal"` — prevents 500 errors when gateway not configured
- `StripeWebhookController::handle()` — graceful fallback when no `STRIPE_WEBHOOK_SECRET` configured: skips `Webhook::constructEvent` signature check, constructs event from raw JSON payload; logs warning

### Fixed

#### Mail — No Queue Worker (All controllers)
All `Mail::to()->queue()` calls replaced with `Mail::to()->send()` wrapped in `try { } catch (\Throwable) {}` so mail failure never blocks the user action or causes a 500 error on CWP shared hosting (no queue worker):
- `RegisteredUserController` — welcome email
- `OrderController` — invoice created email
- `ServiceController` — service activated email
- `SupportController` (client) — support reply email
- `StripeWebhookController` — invoice paid email
- `AuthorizeNetPaymentController` — payment confirmation email
- `CloseInactiveTickets` — auto-close notification email

### Database Migrations (this release)
| File | Purpose |
|------|---------|
| `2026_03_27_220000_create_ticket_attachments_table.php` | `ticket_attachments` table |
| `2026_03_27_220001_add_fields_to_support_tickets.php` | `rating`, `rating_note`, `first_replied_at`, `closed_at` |
| `2026_03_27_220002_seed_support_email_templates.php` | Seeds `support.opened`, `support.closed`, `support.assigned` |

---

## [1.6.1] — 2026-03-27 — Installer WAF Fix

### Fixed
- Passwords (admin + db) now base64-encoded client-side in the installer wizard before POST, decoded server-side before use — prevents ModSecurity WAF rules from false-positive blocking on special characters in password fields (`&&`, `||`, `>>`, `>`, etc.)
- Applies to both `POST /install/test-database` (db password) and `POST /install/run` (db + admin passwords)

---

## [1.6.0] — 2026-03-27 — Client Notes, Domain Reminders, Dunning, Late Fees, Tax Rates, Client Groups, Authorize.Net, CI

### Added

#### Admin Client Notes
- `client_notes` table — `id`, `user_id`, `author_id` (→ users), `body`, `created_at`
- `ClientNote` model — `user()` and `author()` BelongsTo relationships
- `Admin\ClientController::storeNote()` / `destroyNote()` — create/delete notes; abort if note doesn't belong to client
- Client Show page: internal notes panel with textarea, amber-background note list, delete button
- Routes: `POST admin/clients/{client}/notes`, `DELETE admin/clients/{client}/notes/{note}`

#### Domain Renewal Reminders
- `SendDomainRenewalReminders` artisan command — checks 30, 14, and 7-day windows; queues `TemplateMailable('domain.expiring')`; fires `domain.expiring` workflow trigger
- Scheduled daily at 09:30 in `routes/console.php`

#### Service Welcome Email
- `WorkflowEngine::fire('service.active', $service)` wired into `Admin\ServiceController::approve()` — triggers automation workflows on service activation

#### Dunning Management
- Migration: `dunning_attempts` (tinyInteger, default 0) and `dunning_last_attempt_at` (timestamp, nullable) added to `invoices` table
- `Invoice` model: both columns added to `$fillable`; `dunning_last_attempt_at` cast to datetime
- `RetryFailedPayments` artisan command — reads `dunning_max_attempts` and `dunning_retry_days` from `Setting`; retries Stripe off-session charge on overdue invoices; tracks attempt count; fires `invoice.paid` workflow on success
- Scheduled daily at 11:00

#### Late Fee Automation
- `ApplyLateFees` artisan command — reads `late_fee_type` (fixed|percent), `late_fee_amount`, `late_fee_days` from `Setting`; finds overdue invoices past threshold with no existing Late Fee line item; adds line item and increments `total` + `amount_due`
- Scheduled daily at 02:00

#### Tax Rates
- Migration: `tax_rates` table (id, name, rate, country nullable, state nullable, is_default, active); `country`, `state`, `tax_exempt` columns added to users table
- `TaxRate` model — `resolveForUser(User $user): ?self` static method; priority: country+state match → country-only match → default rate; returns null if client is `tax_exempt`
- `Admin\TaxRateController` — full CRUD; `store()`/`update()` enforce single default (clears others when `is_default=true`)
- `Admin/TaxRates/Index.vue` — inline create/edit form with country/state fields; priority explanation note
- `Client\OrderController::place()` — resolves tax via `TaxRate::resolveForUser()`; adds tax line item to invoice; applies to taxable products only
- Routes: `GET/POST/PATCH/DELETE admin/tax-rates`
- Admin nav: Tax Rates link

#### Client Groups
- Migration: `client_groups` table (id, name, discount_type, discount_value); `client_group_id` FK nullable on users table
- `ClientGroup` model — `calculateDiscount(float $subtotal): float` returns discount amount based on type (none/percent/fixed)
- `User` model: `group()` BelongsTo relationship; `client_group_id` added to fillable
- `Admin\ClientGroupController` — index, store, update, destroy (unassigns clients before deleting), assignClient
- `Admin/ClientGroups/Index.vue` — inline create/edit; discount label helper; assign client from Client Show
- `Client\OrderController::place()` — applies group discount when no promo code is active; applied before tax
- Routes: `GET/POST/PATCH/DELETE admin/client-groups`, `POST admin/clients/{client}/assign-group`
- Admin nav: Client Groups link

#### Authorize.Net Payment Gateway
- `app/Contracts/PaymentGateway.php` — interface: `charge()`, `refund()`, `supportsTokens()`, `slug()`
- `AuthorizeNetGateway` — AIM API; supports Accept.js opaque data (`opaque_value`) or stored Customer Profile (`customer_profile_id` + `customer_payment_id`); BOM stripping on API response (`\xEF\xBB\xBF`)
- `GatewayService` factory — `driver(string $slug)`, `register(string $slug, string $class)` (plugin API), `available()`
- `config/services.php` — `authorizenet` block added
- `.env.example` — `AUTHORIZENET_API_LOGIN_ID`, `AUTHORIZENET_TRANSACTION_KEY`, `AUTHORIZENET_SANDBOX` added

#### GitHub Actions CI
- `.github/workflows/tests.yml` — Pest test suite on every push and PR; PHP 8.3, Node 22, SQLite in-memory DB; includes `npm run build`

### Changed
- `routes/console.php` — added three new schedule entries: domain reminders (09:30), late fees (02:00), retry payments (11:00)

---

## [1.5.0] — 2026-03-27 — Reports Dashboard, 2FA Enforcement, Email Log, Client Dashboard Improvements

### Added

#### Admin Reports Dashboard
- `Admin\ReportController::index()` — MRR (CASE-normalized billing cycles), ARR, 12-month revenue chart (zero-filled), this-month vs last-month revenue + growth %, unpaid/overdue totals, top 10 clients by lifetime revenue, 6-month new client chart, service status breakdown, support ticket stats
- `Admin/Reports/Index.vue` — KPI cards, bar charts (inline SVG-style with relative widths), top clients table
- Route: `GET admin/reports` → `admin.reports.index`

#### 2FA Enforcement
- `RequireTwoFactor` middleware — blocks admin/staff from accessing the admin panel until TOTP is confirmed; allows `profile.security` and `two-factor.*` routes through; redirects with `needs_2fa` flash message
- Applied to all admin routes: `middleware(['admin', 'require.2fa'])`

#### Email Log
- `LogSentEmail` listener on `MessageSent` event — captures every outbound `Mail::` call to `email_logs` table (to, subject, template slug, mailer, sent_at, headers)
- `Admin\EmailLogController` — paginated index with search (by recipient or subject); detail view
- `Admin/EmailLog/Index.vue` — search bar, status badges, paginated list
- `Admin/EmailLog/Show.vue` — full header/body detail view
- Routes: `GET admin/email-log`, `GET admin/email-log/{emailLog}`

#### Client Dashboard
- Recent Tickets panel added to client dashboard page

### Fixed
- `WorkflowEngine::actionCreateTicket` — class reference corrected from `Ticket` to `SupportTicket`
- Stripe.js now conditionally loaded in `app.blade.php` only when `STRIPE_KEY` is set
- `stripeKey` shared via `HandleInertiaRequests` middleware shared props

---

## [1.4.0] — 2026-03-27 — Staff Permissions, Audit Log, Stored Cards, Automation Workflows

### Added

#### Staff Permission Groups
- `EnsureAdminCan` middleware — passes super-admin/admin; checks named permission for staff role
- `admin.can` middleware alias registered in `bootstrap/app.php`
- Granular permissions seeded: `access.billing`, `access.support`, `access.technical`, `access.clients`, `access.reports`
- `Admin\StaffController` — list staff + per-staff permission checkbox editor
- `Admin/Staff/Index.vue` — table showing each staff member's current permission badges
- `Admin/Staff/Edit.vue` — labelled checkboxes with descriptions per permission
- Routes: `GET admin/staff`, `GET admin/staff/{staff}/edit`, `PATCH admin/staff/{staff}`

#### Audit Log
- `AuditLog` model — append-only (no `updated_at`); cast `details` as array
- `AuditLogger` service — static `log($action, $target, $details, $userId)` helper; auto-resolves actor from `Auth::id()` and IP from request
- `Admin\AuditLogController` — filterable paginated log (by action, actor, target_type, date range)
- `Admin/AuditLog/Index.vue` — filter bar + colour-coded action badges + pagination
- Audit calls added to: `invoice.created`, `invoice.paid`, `service.suspended`, `service.cancelled`, `client.registered`
- Route: `GET admin/audit-log`

#### Stored Payment Methods + Auto-Charge
- Migration: `stripe_customer_id` nullable column added to users table
- Migration: `payment_methods` table (stripe_payment_method_id unique, brand, last4, exp_month, exp_year, is_default)
- `PaymentMethod` model — `expiry` accessor (`MM/YYYY`); `belongsTo` User
- `User::paymentMethods()` relationship + `stripe_customer_id` added to fillable
- `Client\PaymentMethodController` — `setupIntent()` (returns Stripe SetupIntent client_secret), `store()` (attach + save), `setDefault()`, `destroy()` (detach from Stripe)
- `ensureStripeCustomer()` creates Stripe Customer on first card save
- `Client/PaymentMethods/Index.vue` — list saved cards with default badge; Stripe.js card element for adding new cards; set default / remove actions
- `GenerateRenewalInvoices` — `tryAutoCharge()` private method; charges default saved card via off-session PaymentIntent on renewal invoice creation; fires `invoice.paid` workflow on success
- Routes: `GET/POST client/payment-methods`, `GET client/payment-methods/setup-intent`, `POST .../default`, `DELETE .../{id}`
- Admin nav: Payment Methods link in client nav

#### Advanced Automation Workflows (Premium ⭐)
- Migrations: `workflows`, `workflow_conditions`, `workflow_actions`, `workflow_runs` tables
- `Workflow`, `WorkflowCondition`, `WorkflowAction`, `WorkflowRun` models
- `WorkflowEngine` service — `fire($trigger, $target)`: loads active workflows, evaluates ALL conditions (eq/neq/gt/lt/gte/lte/contains), executes actions in order, records run with log
- Supported actions: `send.email`, `create.ticket`, `suspend.service`, `add.credit`, `call.webhook`
- Delayed actions dispatched as `ExecuteWorkflowAction` queued jobs
- `Admin\WorkflowController` — full CRUD + `toggleActive`; syncs conditions/actions on save
- `Admin/Workflows/Index.vue` — list with live toggle switch, condition/action/run counts
- `Admin/Workflows/Edit.vue` — 3-step builder: name+trigger → conditions → actions; dynamic config fields per action type; delay field per action
- `WorkflowEngine::fire()` called on: `invoice.created`, `invoice.paid`, `service.suspended`, `service.cancelled`, `ticket.opened`, `client.registered`
- Routes: full CRUD under `admin/workflows` + `POST .../toggle`

### Routes Added
- `GET/PATCH admin/staff*` → `admin.staff.*`
- `GET admin/audit-log` → `admin.audit-log.index`
- `GET/POST/PATCH/DELETE admin/workflows*` + `POST .../toggle` → `admin.workflows.*`
- `GET/POST/DELETE client/payment-methods*` → `client.payment-methods.*`

---

## [1.3.0] — 2026-03-27 — HEXONET, Auto-Close Tickets, Credit Balance, Payment Reminders, Promo Codes

### Added

#### HEXONET / CentralNic Registrar (v1.9)
- `HexonetDriver` — HEXONET ISPAPI HTTP gateway; OTE sandbox + live; full lifecycle: availability, register, renew, transfer, nameservers, lock, privacy
- `parseResponse()` parses HEXONET plain-text response format (`[RESPONSE]…CODE=…PROPERTY[X][n]=…EOF`)
- Registered in `DomainRegistrarService` under `hexonet`
- `config/registrars.php` + `.env.example` updated with `HEXONET_SANDBOX`, `HEXONET_LOGIN`, `HEXONET_PASSWORD`

#### Auto-Close Inactive Tickets
- `support:close-inactive` artisan command — closes open/answered/customer_reply tickets with no activity for N days
- Threshold read from `Setting::get('ticket_auto_close_days', 14)`; overridable via `--days=` flag
- Sends `support.closed` email to client on auto-close
- Scheduled daily at 03:00 in `routes/console.php`

#### Client Credit Balance
- `ClientCredit` model — `client_credits` ledger table (positive = credit added, negative = credit applied to invoice)
- `User::credits()` relationship added
- **Admin top-up** — `+ Add` button on client profile; inline form with amount + description; `POST admin/clients/{client}/credit` route
- **Client apply credit** — "Apply Credit" banner on unpaid invoice page (shown when `credit_balance > 0`); `POST client/invoices/{invoice}/apply-credit`; applies up to full amount due; auto-marks invoice paid if fully covered; creates ledger entry
- `Client/Invoices/Show.vue` now accepts `creditBalance` prop

#### Payment Reminders
- `billing:send-reminders` artisan command — reads `Setting::get('reminder_days', '7,3,1')` (comma-separated days before due) and sends `invoice.reminder` email for each matching unpaid invoice
- Scheduled daily at 10:00

#### Promo Codes / Discounts
- Migration `2026_03_27_120000_create_promo_codes_table` — `promo_codes` table: code, type (percent/fixed), value, product_id (nullable), max_uses, uses_count, applies_once, is_active, expires_at
- `PromoCode` model — `isValid()`, `calculateDiscount()`, `scopeActive()`
- **Admin** — `Admin\PromoCodeController` CRUD; `Admin/PromoCodes/Index.vue` (table with edit/delete); `Admin/PromoCodes/Edit.vue` (create/edit form); admin nav "Promo Codes" link
- **Client checkout** — promo code field with AJAX `POST client/promo/validate` validation; discount shown in order summary; promo applied to invoice as negative line item; `uses_count` incremented on order
- `Order` model's existing `promo_code` + `discount` fields now populated

### Routes Added
- `POST admin/clients/{client}/credit` → `admin.clients.credit`
- `GET/POST admin/promo-codes` + CRUD → `admin.promo-codes.*`
- `POST client/invoices/{invoice}/apply-credit` → `client.invoices.apply-credit`
- `POST client/promo/validate` → `client.promo.validate`

---

## [1.2.0] — 2026-03-27 — Shared Hosting Compatibility

### Added

#### Root Shared-Hosting Shim
- `index.php` at project root — bootstrap shim that re-points `DOCUMENT_ROOT` to `public/` and delegates to the real front controller; allows web root = project root without any server reconfiguration
- `.htaccess` at project root — rewrites static file requests to `public/`, all other requests to the shim

#### StorageController (symlink fallback)
- `App\Http\Controllers\StorageController` — streams files from `storage/app/public/` when `public/storage` symlink is not available
- Route `GET storage/{path}` registered automatically in `web.php` only when the symlink is absent; zero overhead on VPS installs where symlink exists

#### Installer Enhancements
- **Install-type auto-detection** — presence of `vendor/` directory sets `installType = 'zip'` (shared hosting); absence = `dev` (VPS/clone); passed as Inertia prop to wizard
- **Expanded requirements checks** — added: `file_uploads`, `upload_max_filesize ≥ 10 MB`, `post_max_size ≥ 10 MB`, `max_execution_time ≥ 60s`, `memory_limit ≥ 128 MB`, `mod_rewrite`, `symlink()` availability; warn-only checks (yellow) vs hard-fail checks (red)
- **Queue mode selector** — new Environment step (step 4) in wizard; `Sync` option for shared hosting (default when ZIP detected), `Database Queue` for VPS; selected value written to `QUEUE_CONNECTION` in `.env`
- **`Artisan::call('storage:link')`** called during install; graceful fallback to controller mode if symlinks are blocked; `storage_mode` recorded in lock file
- **OPENSRS keys** added to `writeEnv()` heredoc (`OPENSRS_SANDBOX`, `OPENSRS_API_KEY`, `OPENSRS_RESELLER_USERNAME`)
- **Lock file version** read from `composer.json` version field instead of hardcoded string
- **Post-install screen** (step 8) — shows: cron command for `schedule:run`; queue worker command (database mode only); sync-mode info note; storage controller note if symlinks were blocked
- Install response now returns `{ queue, storage_mode, app_url }` to front-end for dynamic post-install instructions

#### GitHub Actions
- `.github/workflows/release.yml` — on `v*` tag push: installs deps, builds Vite assets, produces `strata-x.x.x.zip` (source, no vendor/) and `strata-x.x.x-shared.zip` (with vendor/ + public/build/), attaches both to GitHub Release
- `.github/workflows/ci.yml` — on every push/PR: spins up MySQL 8, runs `composer install`, runs migrations, executes Pest test suite in parallel

---

## [1.1.0] — 2026-03-27 — HestiaCP, Service Cancellations, Knowledge Base, Ticket Priority

### Added

#### HestiaCP Provisioner
- **`HestiaProvisioner`** — HestiaCP REST API; `v-add-user` + `v-add-web` for account creation; suspend/unsuspend/terminate via API; numeric `0` response = OK
- Registered in `ProvisionerService` under `hestia` type; default port 8083 in `ProvisionPendingServices`

#### Service Cancellation Requests
- `cancellation_reason` + `cancellation_requested_at` columns added to `services` table (migration `2026_03_27_032000`)
- `Service` model updated with new fillable + casts
- Client `ServiceController::requestCancellation()` — validates reason, sets `status = cancellation_requested`
- Admin `ServiceController::approveCancellation()` / `rejectCancellation()` — approve marks `cancelled`; reject restores `active` and clears cancellation fields
- `POST client/services/{service}/cancel` + `POST admin/services/{service}/approve-cancellation` + `POST admin/services/{service}/reject-cancellation` routes
- `Client/Services/Show.vue` — request form with reason textarea; pending notice; Nevermind button
- `Admin/Services/Show.vue` — amber alert with reason + approve/reject buttons when `cancellation_requested`
- `Admin/Services/Index.vue` — `cancellation_requested` added to status filter options
- `StatusBadge` — amber style for `cancellation_requested`

#### Ticket Priority Change (Admin)
- `PATCH admin/support/{ticket}/priority` route + `SupportController::setPriority()` action
- Admin `Support/Show.vue` — priority in meta bar now an inline `<select>` that auto-saves on change

#### Knowledge Base (v2.1)
- **`KbCategory` model** — `slug`, `active`, `sort_order`; `scopeActive()`, `publishedArticles()` relationship
- **`KbArticle` model** — full-text index on `title` + `body`; `scopePublished()`; `incrementViews()`; `SoftDeletes`
- **`Admin\KbController`** — full CRUD for categories + articles; slug auto-generated from title; `edit` + `store` + `update` + `destroy` + `categories` + `storeCategory` + `updateCategory` + `destroyCategory`
- **`Client\KbController`** — `index` (category browse + full-text search); `show` (view counter, related articles)
- 10 admin routes + 2 client routes
- **`Admin/KB/Categories.vue`** — inline edit-in-row category management
- **`Admin/KB/Index.vue`** — paginated article list with search/category/published filters
- **`Admin/KB/Edit.vue`** — textarea-based article editor with publish toggle
- **`Client/KB/Index.vue`** — search bar + category browse with article previews
- **`Client/KB/Show.vue`** — article view with breadcrumb, view count, related articles, support ticket CTA
- Admin nav: Knowledge Base link; Client nav: Help Center link

---

## [1.0.0] — 2026-03-26 — Multi-Provisioner, OpenSRS, Settings Wired

### Added

#### Multi-Provisioner Support
- **`ProvisionerDriver` contract** — `createAccount`, `suspendAccount`, `unsuspendAccount`, `terminateAccount`, `slug`
- **`PleskProvisioner`** — Plesk REST API v2; two-step webspace + client creation; suspend/unsuspend via `is_disabled`; terminate deletes webspace and client
- **`DirectAdminProvisioner`** — DirectAdmin HTTP API; Basic Auth; URL-encoded response parsing; CRUD via `/CMD_API_ACCOUNT_USER` and `/CMD_API_SELECT_USERS`
- **`CpanelProvisioner`** moved to `App\Services\Provisioners` namespace and made to implement `ProvisionerDriver`
- **`ProvisionerService`** factory — `forModule(Module)`, `findAvailableModule(?string)`, `supportedTypes()`; dispatches by `module.type` across cpanel, plesk, directadmin

#### OpenSRS Registrar Driver
- **`OpenSRSDriver`** — full XCP API implementation; HMAC-MD5 auth (`md5(md5(body+md5(key))+md5(key))`); sandbox-capable
- Registered in `DomainRegistrarService::$drivers` alongside Namecheap and Enom
- `config/registrars.php` — `opensrs` block with sandbox, api_key, reseller_username
- `.env.example` — `OPENSRS_SANDBOX`, `OPENSRS_API_KEY`, `OPENSRS_RESELLER_USERNAME` keys added

#### Logo Upload
- `SettingController::uploadLogo()` — validates image (PNG/JPG/WebP/SVG, max 2 MB), deletes old logo, stores to `storage/app/public/logos/`, saves `logo_path` setting
- `POST admin/settings/logo` route (`admin.settings.logo`)
- `Admin/Settings/Index.vue` — logo preview + file picker + Upload button in General tab
- `TemplateMailable::wrapHtml()` — renders logo `<img>` tag if `logo_path` is set; falls back to text company name
- PDF invoice blade — renders logo via `storage_path()` if available; falls back to branded text

#### Settings Wired Into App
- `app/helpers.php` registered in `composer.json` autoload.files — `setting()` global helper available app-wide
- `TemplateMailable::wrapHtml()` — company name from `setting('company_name')` instead of `config('app.name')`
- PDF invoice — `$companyName`, `$companyAddress`, `$currencySymbol`, `$logoPath` all pulled from settings
- `OrderController::place()` — invoice due date uses `Setting::get('invoice_due_days', 7)`
- `GenerateRenewalInvoices` — default days from `Setting::get('invoice_due_days', 14)` (CLI flag still overrides)
- `SuspendOverdueServices` — grace period from `Setting::get('grace_period_days', 3)` (CLI flag still overrides)
- `ProvisionPendingServices` — refactored to use `ProvisionerService`; supports all module types (cpanel, plesk, directadmin); selects correct port per type

---

## [0.9.0] — 2026-03-26 — System Settings & Complete Support Ticket System

### Added

#### System Settings
- **`Setting` model** — key/value store with `get()`, `set()`, `setMany()`, and `allKeyed()` static helpers; 1-hour cache with automatic bust on write
- **`SettingController`** — `index` returns all settings keyed; `update` validates and batch-upserts 17 configurable keys
- **`SettingsSeeder`** — seeds default values for General (company_name, timezone, date_format), Company (email, phone, address, city, state, zip, country), and Billing (currency, symbol, invoice_prefix, invoice_due_days, grace_period_days, tax_rate, tax_name)
- **`Admin/Settings/Index.vue`** — tabbed settings page (General / Company / Billing); inline save with `recentlySuccessful` confirmation; linked to Departments and Canned Responses sub-pages
- **Settings nav item** in admin sidebar with gear icon

#### Support Ticket Departments
- **`Department` model + migration** — departments table (name, description, email, sort_order, active); `scopeActive()` for ordered query; `department_id` FK added to `support_tickets`
- **`DepartmentController`** — full CRUD; validates unique name; returns to `Admin/Settings/Departments`
- **`Admin/Settings/Departments.vue`** — inline edit-in-row table with create form, activate toggle, delete with confirmation
- **4 default departments seeded**: General, Billing, Technical Support, Sales
- `SupportTicket` fillable updated to include `department_id`; `department()` relationship added
- Client `SupportController::create()` now passes departments from DB; `store()` sets both `department_id` and `department` string
- Admin `SupportController::index()` accepts `department` filter; passes `departments` to view
- Admin Support/Index: department filter dropdown column added

#### Canned Responses
- **`CannedResponse` model + migration** — canned_responses table (title, body, department_id nullable)
- **`CannedResponseController`** — full CRUD; validates title + body + optional department_id
- **`Admin/Settings/CannedResponses.vue`** — card list with inline edit; department scope label; linked from Settings
- Admin Support/Show: **canned response picker** dropdown — click "Insert canned response", pick a title, body is injected into reply textarea

#### Internal Staff Notes
- `internal` boolean added to `support_replies` (migration + model cast)
- Admin `SupportController::reply()` — when `internal=true`: creates reply without emailing client, without updating ticket status to "answered"; note gets amber dashed styling in thread
- Admin Support/Show: **internal note toggle** checkbox in reply form; textarea turns amber when checked; submit button reads "Add Note" vs "Send Reply"
- Client SupportController/show: filters `internal=true` replies from client-visible thread

#### Support Reopen
- `reopen` action on `SupportController` — sets status back to `open`
- Reopen button shown on closed tickets in admin show page and at bottom of closed ticket

Code Checked and Verified By: Claude

---

## [0.8.1] — 2026-03-26 — Documentation Update

### Changed
- **`README.md`** — full rewrite to reflect actual v0.8.0 feature set; accurate tech stack (Laravel 12, Inertia.js v2, Tailwind v4); complete installation guide with requirements, quick-start steps, scheduler and queue worker setup; configuration reference for Stripe, PayPal, and registrar env vars; project structure tree; roadmap summary table
- **`ROADMAP.md`** — all completed items checked off with ✅; partial items marked 🔄; planned items marked ⏳; milestones 0–3 reflect production-shipped state; milestones 4–6 updated to clearly separate done vs. planned work; backlog section updated to include Docker and Horizon

---

## [0.8.0] — 2026-03-26 — Domain Registration API

### Added
- **`RegistrarDriver` contract** (`app/Contracts/RegistrarDriver.php`) — interface defining `checkAvailability()`, `registerDomain()`, `renewDomain()`, `transferDomain()`, `getNameservers()`, `setNameservers()`, `getInfo()`, `setLock()`, `setPrivacy()`, `slug()`
- **`NamecheapDriver`** (`app/Services/Registrars/NamecheapDriver.php`) — Namecheap XML API v1 implementation with sandbox support; handles contact param mapping for all four contact types
- **`EnomDriver`** (`app/Services/Registrars/EnomDriver.php`) — Enom reseller XML API implementation with sandbox support
- **`DomainRegistrarService`** (`app/Services/DomainRegistrarService.php`) — driver factory; `driver(?string)`, `available()`, `checkAvailability(string)`
- **`config/registrars.php`** — `REGISTRAR_DRIVER`, `NAMECHEAP_*`, `ENOM_*` config keys
- **Admin `DomainController`** — `index` (paginated + filtered), `show`, `syncNameservers`, `setLock`, `setPrivacy`, `refresh` (pulls live info from registrar)
- **Client `DomainController`** — `index`, `show`, `setNameservers`, `toggleAutoRenew`, `checkAvailability` (JSON; used by checkout)
- **Admin Domains pages** — `Admin/Domains/Index.vue` (searchable, status filter, paginated), `Admin/Domains/Show.vue` (metadata, lock/privacy toggles, nameserver editor)
- **Client Domains pages** — `Client/Domains/Index.vue`, `Client/Domains/Show.vue` (auto-renew toggle, inline nameserver editor with up to 6 NS slots)
- **Checkout availability badge** — debounced 600ms live domain availability check in `Checkout.vue` for `domain`-type products; green "Available ✓" / red "Not available" inline indicator
- **Domain record on order** — `OrderController::place()` creates a `Domain` record (status: pending) when `product.type === 'domain'`
- **`domains:renew-expiring` command** — auto-renews active domains with `auto_renew=true` expiring within N days (default 30); scheduled daily at 09:00
- **Routes** — admin: `domains.index/show/nameservers/lock/privacy/refresh`; client: `domains.index/show/nameservers/auto-renew/check`
- **Nav** — Domains item added to both admin and client sidebars
- **`.env.example` + installer template** — `REGISTRAR_DRIVER`, `NAMECHEAP_*`, `ENOM_*` env vars

---

## [0.7.0] — 2026-03-26 — Mailables, cPanel Provisioning & Email Template Editor

### Added
- **`EmailTemplate` model** (`app/Models/EmailTemplate.php`) — `findBySlug()` + `render(field, vars)` method for `{{variable}}` placeholder replacement
- **`TemplateMailable`** (`app/Mail/TemplateMailable.php`) — single queued mailable that loads template by slug, renders HTML (wrapped in branded layout) and plain text, implements `ShouldQueue`
- **7 default email templates** (seeded via `EmailTemplatesSeeder`): `auth.welcome`, `invoice.created`, `invoice.paid`, `invoice.overdue`, `service.activated`, `service.suspended`, `support.reply`
- **Emails wired to triggers:**
  - Registration → `auth.welcome`
  - Order placed → `invoice.created`
  - Admin marks invoice paid → `invoice.paid`
  - Stripe webhook completes → `invoice.paid`
  - `billing:flag-overdue` → `invoice.overdue` per invoice
  - `billing:suspend-overdue` → `service.suspended` per service
  - Admin support reply → `support.reply`
  - Service provisioned → `service.activated`
- **Admin Email Templates UI** (`/admin/email-templates`) — index list with slug, name, subject, status; edit form with variable reference panel, HTML body textarea, plain text fallback, active toggle
- **`CpanelProvisioner`** (`app/Services/CpanelProvisioner.php`) — WHM JSON API v1 client: `createAccount()` (generates username + password, calls `createacct`), `suspendAccount()`, `unsuspendAccount()`, `terminateAccount()`; uses Bearer token auth; `findAvailableModule()` selects active cPanel module with capacity
- **`provisioning:run` command** — finds pending cPanel services whose invoices are paid, provisions via `CpanelProvisioner`, updates service (username, `password_enc`, `server_hostname`, `module_data`), increments module account count, sends `service.activated` email
- **Scheduler** — `provisioning:run` added at `everyFiveMinutes()` with `withoutOverlapping()`

---

## [0.6.0] — 2026-03-26 — Orders, PDF Export & Billing Automation

### Added
- **`barryvdh/laravel-dompdf ^3.1`** added to `composer.json`
- **PDF Invoice Export** — `GET /admin/invoices/{invoice}/download` and `GET /client/invoices/{invoice}/download` stream a styled A4 PDF; client route ownership-checked (403); admin and client invoice show pages both have Download PDF buttons
- **Invoice PDF template** (`resources/views/pdf/invoice.blade.php`) — branded header with app name/URL, bill-to/from parties, dates, line-items table, totals with tax and credit rows, payment history section, footer with contact email
- **Client Order Catalog** (`/client/order`) — product grid with type badge, price, billing cycle, setup fee, and Order Now link; added to client nav
- **Client Checkout** (`/client/order/checkout`) — order summary card + domain field (shown for hosting/domain/VPS product types) + place order button; validates product, billing cycle, optional domain
- **`Client/OrderController`** — `catalog()`, `checkout()` (GET with query params), `place()` (POST; wraps full flow in a DB transaction: creates Order → OrderItem → Service → Invoice → InvoiceItems); redirects to new invoice for immediate payment
- **Billing Automation Commands**
  - `billing:generate-invoices --days=14` — generates renewal invoices for active services due within N days, skipping services that already have an open unpaid invoice for the cycle
  - `billing:flag-overdue` — marks unpaid invoices past their due date as `overdue`
  - `billing:suspend-overdue --grace=3` — suspends active services with invoices overdue beyond the grace period
- **Scheduler** (`routes/console.php`) — all three billing commands registered: generate-invoices at 08:00, flag-overdue at 00:05, suspend-overdue at 01:00; all run `withoutOverlapping()->runInBackground()`

---

## [0.5.1] — 2026-03-26 — PayPal Payment Integration

### Added
- **`srmklive/paypal ^3.0`** added to `composer.json`
- **PayPal Checkout** — `POST /client/invoices/{invoice}/paypal` creates a PayPal Orders v2 order and returns the buyer approval URL; client is redirected to PayPal-hosted approval page
- **`Client/PayPalPaymentController`** — `checkout()` creates order with `CAPTURE` intent; `return()` captures payment on buyer return and marks invoice paid; `cancel()` marks pending payment failed and redirects with error message
- **PayPal return/cancel routes** — `GET /client/invoices/{invoice}/paypal/return` and `/cancel` handle post-approval flow; ownership-checked (403)
- **Invoice Show** — payment section now offers both Card (Stripe) and PayPal buttons side by side; both show loading spinners; only one can be active at a time; flash messages from PayPal redirect displayed at top
- **`config/services.php`** — `paypal` block with `client_id`, `client_secret`, `mode`, `currency`
- **`.env.example`** — `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_MODE`, `PAYPAL_CURRENCY`
- **Installer `.env` template** — payment gateway placeholders (Stripe + PayPal) written during web install

---

## [0.5.0] — 2026-03-26 — Stripe Payment Integration

### Added
- **`stripe/stripe-php ^20.0`** added to `composer.json`
- **Stripe Checkout** — `POST /client/invoices/{invoice}/checkout` creates a Stripe Checkout Session and returns a redirect URL; client is redirected to Stripe-hosted payment page; on return, `?paid=1` query param triggers a success banner
- **`Client/PaymentController`** — validates ownership (403), guards against double-payment, builds Checkout Session with line item from `invoice.amount_due`, stores a `pending` Payment record with the Stripe session ID for webhook reconciliation
- **Stripe Webhook** (`POST /stripe/webhook`) — `StripeWebhookController` handles `checkout.session.completed` (marks Payment completed, marks Invoice paid) and `checkout.session.expired` (marks Payment failed); signature verified via `STRIPE_WEBHOOK_SECRET`; route exempt from CSRF verification
- **`Client/Invoices/Show`** — Pay Now button triggers Stripe redirect with loading spinner; success banner on return from Stripe; credit applied row in totals; payment history list at bottom of invoice
- **`config/services.php`** — `stripe` block with `key`, `secret`, `webhook_secret`, `currency`
- **`.env.example`** — `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CURRENCY`, `VITE_STRIPE_KEY`

### Security
- Webhook endpoint verified with `Stripe\Webhook::constructEvent()` — rejects requests with invalid signatures (400)
- Invoice ownership checked before creating checkout session (403)
- Double-payment guarded: aborts 422 if invoice status is already `paid`

---

## [0.4.0] — 2026-03-26 — Services Detail & Announcements

### Added
- **Admin Services/Show** (`/admin/services/{service}`) — service detail page: service metadata (product, domain, billing cycle, amount, dates), client card with link to client show, context-sensitive action buttons (suspend/reactivate/terminate with confirmation), provisioning block (username, server hostname:port, shown only when populated), notes panel, full invoice history table with status badges
- **Services Index → Show links** — domain column in Services Index now links to the show page
- **Announcements admin CRUD** (`/admin/announcements`) — list with published/draft badges; create and edit form (title, body textarea, publish toggle); soft-delete via destroy
- **Client Announcements** (`/client/announcements`) — paginated list of published announcements sorted by publish date; clean article layout with date header
- **`AnnouncementController` (Admin)** — full CRUD; sets `published_at` to `now()` on first publish, clears it on unpublish
- **`AnnouncementController` (Client)** — invokable; returns only published announcements ordered by `published_at` desc
- **AppLayout nav** — Announcements added to both admin nav and client nav with megaphone icon

---

## [0.3.0] — 2026-03-26 — Browser-Based Installer

### Added
- **Web Installer** (`/install`) — complete 7-step browser wizard requiring no CLI or shell access; designed for hosting resellers who deploy via FTP/File Manager
  - **Step 1 — Welcome** — introduction and requirements overview
  - **Step 2 — Requirements Check** — live server check: PHP ≥8.3, PDO, PDO MySQL, mbstring, OpenSSL, Tokenizer, JSON, Ctype, BCMath; directory writability (storage/, bootstrap/cache/, .env); all checks displayed with pass/fail indicators
  - **Step 3 — Database** — host, port, name, username, password fields; "Test Connection" button performs raw PDO connect with 5-second timeout and returns MySQL version on success before allowing progression
  - **Step 4 — Site Configuration** — app name and app URL
  - **Step 5 — Admin Account** — admin name, email, password (min 8 chars), password confirmation with client-side validation
  - **Step 6 — Installing** — animated spinner while install runs; calls `/install/run` which executes the full pipeline server-side
  - **Step 7 — Complete** — success screen with direct link to login
- **`InstallerController`** (`app/Http/Controllers/Install/InstallerController.php`) — four endpoints:
  - `GET /install` — renders `Install/Welcome`
  - `GET /install/requirements` — returns JSON of all 12 server checks with pass/fail/detail
  - `POST /install/test-database` — validates input, opens raw PDO connection, returns MySQL version
  - `POST /install/run` — full install pipeline: writes `.env`, live-patches running DB config (`DB::purge` + `DB::reconnect`), runs `migrate --force`, seeds `RolesAndPermissionsSeeder`, creates super-admin user, caches config+routes, writes `storage/installed.lock`
- **`.env` generation** — `APP_KEY` generated with `base64_encode(random_bytes(32))`; file written with `chmod 0600`; `APP_ENV=production`, `APP_DEBUG=false`, `APP_INSTALLED=true` set automatically
- **`CheckInstalled` middleware** (`app/Http/Middleware/CheckInstalled.php`) — redirects to `/install` if `storage/installed.lock` is absent; returns 403 if lock exists and `/install` is accessed again; appended to the web middleware stack in `bootstrap/app.php`
- **Installer route group** — added at top of `routes/web.php` before all auth routes; no auth or verified middleware applied

### Security
- `.env` written with `chmod 0600` (owner-read-only)
- Installer locked permanently via `storage/installed.lock` after first successful run
- Direct PDO test with 5-second timeout prevents the installer from writing `.env` with invalid credentials

---

## [0.2.0] — 2026-03-26 — Auth & Multi-Role Access

### Added
- **Login / Logout** — rate-limited (5 attempts per email+IP), session regeneration
- **Client Registration** — self-service signup; new accounts are auto-assigned the `client` role
- **Password Reset** — forgot-password email flow with signed tokens
- **TOTP 2FA** — enable (QR code via BaconQrCode + Google2FA), confirm with first OTP, disable; login intercepted for users with confirmed 2FA and routed through challenge page
- **Email Verification** — enforced on dashboard and all profile/portal routes; resend throttled at 6/min; signed URL verification
- **Session Management** — list all active sessions (device/browser/IP/last active), revoke individual sessions, revoke-all-others (cross-user isolation enforced)
- **OAuth2** — Google and Microsoft sign-in via Laravel Socialite; find-or-create with `client` role assignment
- **Roles & Permissions** — spatie/laravel-permission; roles: `super-admin`, `admin`, `staff`, `client`; seeder creates all four roles + default `admin@strata.local` super-admin
- **User Model** — `HasRoles` trait, 2FA fillable/casts (`two_factor_secret`, `two_factor_enabled`, `two_factor_confirmed_at`), `credit_balance`, `isAdmin()` / `isClient()` helpers, all billing relationships
- **Profile — Security** (`/profile/security`) — 2FA management UI (QR scan + plain key fallback, confirm, disable, status banners)
- **Profile — Sessions** (`/profile/sessions`) — active session list with revoke buttons
- **GuestLayout.vue** — centered dark-card layout for all auth pages
- **Auth Vue Pages** — Login (with Google/Microsoft OAuth buttons), Register, ForgotPassword, ResetPassword, VerifyEmail, TwoFactorChallenge
- **Pest Tests** — authentication, password reset, TOTP 2FA lifecycle, email verification, session management (26 tests across 5 files)

### Changed
- `AuthenticatedSessionController::store` — intercepts login for 2FA users; stores `two_factor_login_id` in session before completing auth
- `HandleInertiaRequests` — shares `auth.user` (with roles loaded) and `flash` props globally
- `AppLayout` — logout button in user footer; role-aware navigation (admin nav vs client nav); Settings section with Security + Sessions links
- `routes/web.php` — restructured into guest / OAuth / 2FA-challenge / auth / auth+verified groups

### Migrations
- `add_two_factor_to_users_table` — `two_factor_secret`, `two_factor_enabled`, `two_factor_confirmed_at`
- `create_permission_tables` — spatie/laravel-permission tables

---

## [0.1.1] — 2026-03-26 — Complete Framework Scaffold

### Added
**Database (11 new migrations)**
- `products` — name, type (shared/reseller/vps/dedicated/domain/ssl/other), price, setup_fee, billing_cycle, module, module_config (JSON), stock, hidden, taxable, sort_order; soft deletes
- `services` — user_id, product_id, domain, status lifecycle (pending/active/suspended/cancelled/terminated), amount, billing_cycle, registration_date, next_due_date, termination_date, provisioning fields (username, password_enc, server_hostname, server_port, module_data); indexes on user_id+status and next_due_date; soft deletes
- `orders` + `order_items` — promo code, discount, per-item billing cycle
- `invoices` + `invoice_items` — tax_rate, credit_applied, amount_due, date, due_date, paid_at
- `payments` — gateway (stripe/paypal/bank_transfer/credit/manual), transaction_id (unique), gateway_response (JSON, hidden)
- `domains` — registrar, status, registered_at, expires_at, auto_renew, locked, privacy, 4× nameserver fields, registrar_data (JSON); indexes on user_id+status and expires_at
- `support_tickets` + `support_replies` — department, priority, assigned_to, last_reply_at; soft deletes
- `modules` — server type (cpanel/plesk/directadmin/vestacp/cyberpanel/generic), hostname, port, ssl, api_token_enc, password_enc (both hidden), max_accounts, current_accounts
- `client_credits` — append-only credit ledger; `credit_balance` column added to users
- `email_templates` — slug (unique), subject, body_html, body_plain, active
- `announcements` — published flag, published_at; soft deletes

**Models (12 new)**
`Product`, `Service`, `Order`, `OrderItem`, `Invoice`, `InvoiceItem`, `Payment`, `Domain`, `SupportTicket`, `SupportReply`, `Module`, `Announcement` — all with fillable, casts, relationships, soft deletes where applicable; encrypted fields hidden

**Admin Panel** (`/admin/*` — requires `auth + verified + admin` middleware)
- `DashboardController` — stats (total clients, active services, open/overdue invoices, open tickets, MRR); recent orders + tickets
- `ClientController` — index (search, paginated, service/invoice counts), show (with services/invoices/tickets/domains), create, store, update, suspend (bulk-suspends services)
- `ProductController` — full CRUD with validation
- `ServiceController` — index (search + status filter), show, suspend / unsuspend / terminate
- `InvoiceController` — index (search + status filter), show, create (line-item builder), store, markPaid, cancel
- `SupportController` — index (search + status + priority filter, priority-ordered), show, reply (sets status → answered), assign, close
- `ModuleController` — full CRUD; encrypts API token on store/update

**Client Portal** (`/client/*` — requires `auth + verified`)
- `DashboardController` — personalised stats + services due ≤30 days + unpaid invoices + recent tickets
- `ServiceController` — index, show (ownership enforced with 403)
- `InvoiceController` — index, show (ownership enforced with 403)
- `SupportController` — index, create, store (opens ticket + first reply), show, reply (sets status → customer_reply)

**Vue Pages (30 new)**
- Admin: `Dashboard`, `Clients/Index`, `Clients/Show`, `Clients/Create`, `Products/Index`, `Products/Form`, `Services/Index`, `Invoices/Index`, `Invoices/Show`, `Invoices/Create` (dynamic line-item builder), `Support/Index`, `Support/Show`, `Modules/Index`, `Modules/Form`
- Client: `Dashboard`, `Services/Index`, `Services/Show`, `Invoices/Index`, `Invoices/Show` (Pay Now stub), `Support/Index`, `Support/Create`, `Support/Show`
- Auth: `Register`
- Shared component: `StatusBadge` — unified status/priority colour mapping for all list views

**Infrastructure**
- `EnsureIsAdmin` middleware — 403 for non-admin roles; registered as `admin` alias in `bootstrap/app.php`
- `AppLayout` — role-aware navigation: admins see admin panel links; clients see portal links; computed from `auth.user.roles`
- `HandleInertiaRequests` — loads `user.roles` relation in shared `auth` prop
- `laravel/socialite ^5.0` added to `composer.json`

---

## [0.1.0] — 2026-03-25 — Project Foundation

### Added
- **Laravel 12** skeleton with `composer.json` configured for PHP 8.3+
- **Inertia.js v2** + Vue 3 + Vite 8 wiring (`app.js`, `app.blade.php`, `HandleInertiaRequests` middleware, `vite.config.js`)
- **Tailwind CSS v4** via `@tailwindcss/vite`
- **Ziggy** route helper (`@routes` directive + `ZiggyVue` plugin)
- **Laravel Horizon** (queue worker, `horizon` Docker service)
- **Laravel Scout** + Meilisearch service in Docker
- **Docker Compose** — 6 services: `app` (php-fpm), `nginx` (port 8080), `mysql` (healthcheck), `redis`, `horizon`, `meilisearch` (port 7700)
- `docker/nginx/default.conf` — FastCGI pass, gzip, static asset caching
- `docker/php/local.ini` — dev settings (`display_errors=On`, `memory_limit=256M`, `opcache.enable=0`)
- **GitHub Actions CI** (`.github/workflows/tests.yml`) — PHP 8.3, MySQL 8 + Redis 7 services; Pest + Pint jobs
- **AppLayout.vue** — dark sidebar, flash messages, mobile hamburger
- **Dashboard.vue** — 4-stat grid placeholder
- `routes/web.php` — initial dashboard route
- `README.md` — pre-release banner, feature tiers, status table, feature request email
- `LICENSE.md` — FSL-1.1-Apache-2.0 with premium modules addendum
- `.github/ISSUE_TEMPLATE/feature_request.md`

### Stack
| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.3 |
| Frontend | Vue 3, Inertia.js v2, Tailwind CSS v4 |
| Queue | Laravel Horizon + Redis |
| Search | Laravel Scout + Meilisearch |
| Testing | Pest + Pint |
| Infrastructure | Docker Compose |

---

*Strata is pre-release software. Feature requests: Jonathan.r.covington@gmail.com*
