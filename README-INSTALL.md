# Strata Service Billing and Support Platform — Installation Guide

> **Beta-1** — Test in a non-production environment.
> Report bugs and issues at: https://github.com/jonathjan0397/strata/issues

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.3 or higher |
| MySQL / MariaDB | 8.0 / 10.4 or higher |
| Web server | Apache (mod_rewrite) or Nginx |
| PHP extensions | PDO, PDO_MySQL, mbstring, OpenSSL, tokenizer, JSON, ctype, BCMath, fileinfo, xml |
| Writable paths | `storage/`, `bootstrap/cache/` |

---

## Installation Steps

### Step 1 — Upload the files

Upload the entire contents of this ZIP to your server. The recommended layout is:

```
/home/youraccount/
├── strata/          ← all Laravel files go here (above public_html)
│   ├── app/
│   ├── config/
│   ├── public/      ← this becomes your web root
│   └── ...
```

**cPanel / Shared Hosting:**
- Extract the ZIP into a directory **above** `public_html` (e.g. `/home/user/strata/`).
- In cPanel → **Domains** → set the document root for your domain to `strata/public`.
- Alternatively, move the *contents* of the `public/` folder into `public_html/`, then edit `public_html/index.php` and update the path on line 14 to point to your `strata` directory.

**VPS / Dedicated:**
- Point your virtual host `DocumentRoot` or Nginx `root` to `/path/to/strata/public`.

---

### Step 2 — Set file permissions

```bash
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

On shared hosting, use your file manager to set `storage/` and `bootstrap/cache/` to **755**.

---

### Step 3 — Run the installer

Open your browser and navigate to:

```
https://yourdomain.com/install
```

The setup wizard will guide you through:

1. **Server requirements check** — verifies all PHP extensions are present
2. **Database connection** — enter your MySQL host, database name, username, and password
3. **Environment type** — choose Sync (shared hosting) or Database queue (VPS)
4. **Site configuration** — site name and URL
5. **Admin account** — creates your first administrator login
6. **Sample data** *(optional)* — installs demo clients, invoices, tickets, and quotes for testing

Click **Install Strata** to complete setup. This typically takes 15–45 seconds.

---

### Step 4 — Set up the cron job

Strata requires a cron job for automated invoice generation, overdue checks, and domain renewal reminders.

**cPanel Cron Jobs** — add the following (replace the path with your actual `artisan` path):

```
* * * * * php /home/youraccount/strata/artisan schedule:run >> /dev/null 2>&1
```

**Linux crontab** (`crontab -e`):

```
* * * * * /usr/bin/php /path/to/strata/artisan schedule:run >> /dev/null 2>&1
```

---

### Step 5 — Queue worker (VPS / Database queue only)

If you selected **Database queue** during installation, start a persistent worker:

```bash
php artisan queue:work --sleep=3 --tries=3
```

Use **Supervisor** to keep it running. Shared hosting users on **Sync** mode do not need this step.

---

## Post-Installation

- Log in at `https://yourdomain.com/login` with the admin credentials you created.
- Go to **Settings** to configure your company name, logo, currency, and email.
- Add your Stripe, PayPal, or Authorize.Net credentials in **Settings → Payments**.
- Create your first **Product** under Products & Services before clients can place orders.

---

## Upgrading

1. Back up your database before upgrading.
2. Replace all files **except** `.env` and the `storage/` directory.
3. Run `php artisan migrate --force` to apply any new database migrations.

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank page / 500 error after upload | Check `storage/logs/laravel.log` for the error message |
| Installer not accessible | Verify `.htaccess` is uploaded and `mod_rewrite` is enabled |
| "Access denied for user root" in logs | The `storage/framework/sessions/` or other `storage/` subdirectories are missing — create them manually and set to chmod 755 |
| "storage is not writable" | Set `storage/` and `bootstrap/cache/` to chmod 755 |
| Emails not sending | Configure SMTP in **Settings → Email** after installation |
| Cron not running | Verify the full path to `php` and `artisan` in your cron command |

### 403 Forbidden on the installer

If you see **"You don't have permission to access this resource"** when visiting `/install`, the cause depends on how Strata is installed.

**Scenario A — Strata is in a subdirectory (e.g. `public_html/billing/`)**

Apache processes `.htaccess` files top-down, starting from the domain root. If your hosting does not grant `AllowOverride` access to subdirectories, the `billing/.htaccess` that routes requests through Laravel is silently ignored, and Apache returns 403.

The recommended fix is to **use a subdomain instead**:

- In **CWP**: Domains → Subdomains → create `billing.yourdomain.com` with document root set to `public_html/billing/public`
- In **cPanel**: Domains → Subdomains → same approach

Then visit `https://billing.yourdomain.com/install`.

If you must keep a subdirectory URL, add the following to your **main** `public_html/.htaccess` (not the one inside `billing/`):

```apache
RewriteEngine On

# Route /billing/* through Strata's front controller
RewriteCond %{REQUEST_URI} ^/billing
RewriteCond %{DOCUMENT_ROOT}/billing/public/%{REQUEST_URI} !-f
RewriteRule ^billing(/.*)?$ /billing/public/index.php [L,QSA]
```

Replace `billing` with your actual subdirectory name. Be careful not to break any existing rules if your main domain already has a `.htaccess`.

**Scenario B — Strata is the only app at the domain root**

If files are uploaded to `public_html/` (or equivalent) but you did **not** point the document root to `public/`, Apache may deny access to PHP files or return 403 on directory requests.

Fix: in your control panel, set the document root for the domain to `public_html/public` (or wherever the `public/` subdirectory lives).

**Scenario C — `mod_rewrite` is disabled**

Without `mod_rewrite`, the `.htaccess` rewrite rules are ignored. Contact your host to confirm `mod_rewrite` is enabled, or check **CWP → Apache Manager** / **cPanel → MultiPHP Manager**.

---

## Default Demo Credentials (Sample Data only)

If you installed sample data, the following demo client accounts are available:

| Name | Email | Password |
|------|-------|----------|
| Alice Johnson | alice@demo.strata | demo1234 |
| Bob Smith | bob@demo.strata | demo1234 |
| Carol White | carol@demo.strata | demo1234 |
| David Lee | david@demo.strata | demo1234 |
| Emma Brown | emma@demo.strata | demo1234 |

> Remove these accounts before going live.

---

## Support & Bug Reports

- **GitHub Issues:** https://github.com/jonathjan0397/strata/issues
- **Documentation:** See `README.md` for full feature documentation
- **Changelog:** See `CHANGELOG.md`

---

*Strata Service Billing and Support Platform is licensed under FSL-1.1-Apache-2.0. See `LICENSE.md` for details.*
*&copy; 2026 Jonathan R. Covington. All rights reserved.*
