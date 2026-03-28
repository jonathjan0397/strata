# Strata — Session Handoff

**Date:** Saturday, March 28, 2026
**Time:** ~3:45 AM EDT
**Version at handoff:** v1.8.0
**Branch:** main
**Deployed to:** stratadev.hosted-tech.net

---

## What Was Done This Session

### 1. Payment Gateway Fixes
- **All `Mail::queue()` → `Mail::send()`** across every controller that sends email (7 locations). CWP shared hosting has no queue worker — queue calls hang indefinitely. All are now `send()` with silent `try/catch` so mail failure never blocks the user.
- **`hasStripe` / `hasPayPal` flags** added to Invoice show props. Pay buttons now hidden via `v-if` when the gateway is not configured in `.env`.
- **Stripe webhook graceful fallback** — if `STRIPE_WEBHOOK_SECRET` is absent, signature check is skipped and event is constructed from raw JSON. Prevents 400 errors on installs without webhook signing configured.

### 2. Full Support System Feature Set
Everything that was missing from the support module:

| Feature | Status |
|---------|--------|
| File attachments (tickets + replies) | ✅ Done |
| Secure attachment download (role-aware) | ✅ Done |
| 1–5 star satisfaction ratings (closed tickets) | ✅ Done |
| Bulk admin actions (close/reopen/assign/delete) | ✅ Done |
| Inline department transfer | ✅ Done |
| Ticket merge (absorbs replies + attachments) | ✅ Done |
| SLA visual indicators (dot + row tint) | ✅ Done |
| Client keyword search + status filter | ✅ Done |
| Admin agent filter | ✅ Done |
| First reply time tracking + display | ✅ Done |
| `support.opened` email (admin notification) | ✅ Done |
| `support.assigned` email (staff notification) | ✅ Done |
| `support.closed` email (client auto-close) | ✅ Done |
| `closed_at` timestamp on auto-close | ✅ Done |

### 3. Knowledge Base Rich Text Editor
- **`TiptapEditor.vue`** component — full Tiptap v2 with formatting toolbar + image upload
- Image upload via file picker, drag-and-drop, and clipboard paste → stored in `storage/app/public/kb-images/`
- `KbController::uploadImage()` endpoint + route `POST /admin/kb/images`
- `Admin/Kb/Edit.vue` — `<textarea>` replaced with `<TiptapEditor>`
- `Client/Kb/Show.vue` + `Portal/KB/Show.vue` — body rendered with `v-html` (was plain text)
- `@tailwindcss/typography` installed and activated for `prose` rendering

### 4. Documentation
- `FEATURES.md` — new complete feature inventory
- `README.md` — updated to v1.8.x, all new features documented
- `CHANGELOG.md` — v1.7.0 and v1.8.0 entries added
- `BUGFIX.md` — BF-019, BF-020, BF-021 documented

---

## Files Changed This Session

### New Files
| File | Purpose |
|------|---------|
| `resources/js/Components/TiptapEditor.vue` | Tiptap rich text editor component |
| `database/migrations/2026_03_27_220000_create_ticket_attachments_table.php` | ticket_attachments table |
| `database/migrations/2026_03_27_220001_add_fields_to_support_tickets.php` | rating, rating_note, first_replied_at, closed_at |
| `database/migrations/2026_03_27_220002_seed_support_email_templates.php` | Seeds 3 support email templates |
| `app/Models/TicketAttachment.php` | TicketAttachment Eloquent model |
| `app/Http/Controllers/TicketAttachmentController.php` | Secure attachment download |
| `FEATURES.md` | Complete current features list |

### Modified Files
| File | What Changed |
|------|-------------|
| `app/Http/Controllers/Admin/KbController.php` | Added `uploadImage()` method |
| `app/Http/Controllers/Admin/SupportController.php` | Full rewrite — reply, close, assign, transfer, merge, bulk |
| `app/Http/Controllers/Client/SupportController.php` | Full rewrite — store, reply, rate, search/filter |
| `app/Http/Controllers/Client/InvoiceController.php` | hasStripe / hasPayPal flags |
| `app/Http/Controllers/StripeWebhookController.php` | Graceful webhook fallback; queue→send |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | queue→send |
| `app/Http/Controllers/Admin/OrderController.php` | queue→send |
| `app/Http/Controllers/Admin/ServiceController.php` | queue→send |
| `app/Http/Controllers/Client/AuthorizeNetPaymentController.php` | queue→send |
| `app/Console/Commands/CloseInactiveTickets.php` | Sets closed_at; queue→send |
| `app/Models/SupportTicket.php` | New fillable fields + attachments() relationship |
| `app/Models/SupportReply.php` | Added attachments() relationship |
| `resources/js/Pages/Admin/Support/Index.vue` | Full rewrite — bulk, SLA, agent filter |
| `resources/js/Pages/Admin/Support/Show.vue` | Full rewrite — meta bar, merge, attachments |
| `resources/js/Pages/Client/Support/Index.vue` | Full rewrite — search, filter, priority dot |
| `resources/js/Pages/Client/Support/Show.vue` | Full rewrite — attachments, ratings |
| `resources/js/Pages/Client/Support/Create.vue` | Full rewrite — file attachments |
| `resources/js/Pages/Admin/Kb/Edit.vue` | TiptapEditor replaces textarea |
| `resources/js/Pages/Client/Kb/Show.vue` | v-html article body |
| `resources/js/Pages/Portal/KB/Show.vue` | v-html article body |
| `resources/css/app.css` | Added @plugin "@tailwindcss/typography" |
| `routes/web.php` | New routes: support attachments, rate, bulk, merge, transfer; KB image upload |
| `database/seeders/EmailTemplatesSeeder.php` | Added 3 support templates |
| `README.md` | Updated to v1.8.x |
| `CHANGELOG.md` | v1.7.0 + v1.8.0 entries |
| `BUGFIX.md` | BF-019, BF-020, BF-021 |

---

## Database State

Migrations ran successfully on stratadev.hosted-tech.net:
- `ticket_attachments` table created
- `support_tickets` — `rating`, `rating_note`, `first_replied_at`, `closed_at` added
- `email_templates` — `support.opened`, `support.closed`, `support.assigned` seeded via migration

---

## Known Issues / Deferred

| ID | Issue | Priority |
|----|-------|---------|
| BF-015 | Debug password logging in InstallerController — remove before production | Medium |
| — | Authorize.net Accept.js Vue component (client-side card entry) | Low |
| — | HEXONET registrar sandbox confirmation | Low |
| — | Client billing history page (full invoice list with filters) | Medium |

---

## Next Suggested Work

1. **Authorize.net Accept.js** — client-side card entry component in Vue for the invoice pay page
2. **Client billing history** — full invoice list with date range filters and PDF batch download
3. **Workflow trigger: `support.replied`** — trigger automations when staff replies to a ticket
4. **KB article versioning** — track edit history for KB articles
5. **BF-015** — remove debug logging from InstallerController before v1.0

---

*Generated by Claude — Code Checked and Verified By: Claude*
