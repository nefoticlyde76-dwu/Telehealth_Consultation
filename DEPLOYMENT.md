# TeleHealth PNG production deployment

This guide is for the first production deployment of the MBPHA TeleHealth Consultation System. It does not change application behaviour. Keep secrets in `.env` only. Never commit `.env`.

The public web root is `public/`. PHP files outside `public/` must not be reachable over HTTP.

## 1. Required PHP version

PHP **8.0 or later** (8.1+ recommended).

## 2. Required PHP extensions

Enable these extensions on the production PHP build:

| Extension | Used for |
|-----------|----------|
| `pdo` | Database access |
| `pdo_mysql` | MySQL/MariaDB driver |
| `json` | JSON request and Daily/Google responses |
| `mbstring` | Names, Google identity, session user-agent trimming |
| `openssl` | TLS for SMTP, Daily, and Google JWKS |
| `curl` | Daily.co API and Google JWKS fetch |
| `fileinfo` | Upload MIME checks |
| `gd` | PDF logo and signature embedding (DomPDF) |

Confirm with `php -m` on the server.

## 3. Required MySQL / MariaDB version

- MySQL **5.7+** or **8.0+**, or MariaDB **10.4+**
- Character set `utf8mb4`, collation `utf8mb4_unicode_ci`
- InnoDB
- The application connects through `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS`. It does not assume `localhost` or `127.0.0.1`.
- If the host provides a Unix socket instead of TCP, set `DB_SOCKET` and leave TCP unused.

The database server may be on a different machine from PHP. Allow the PHP host in MySQL user grants (`user@php-server-ip`) and open port 3306 only to that host.

## 4. Required database tables / schema

For a **new** production database import the complete current schema:

```bash
mysql -u USER -p -h DB_HOST telehealth_db < database/schema.sql
```

`database/schema.sql` creates all tables required by the current application:

- `roles`, `users`, `patient`, `doctor`, `admin`
- `doctor_availability`, `consultation_requests`, `consultation_records`, `prescriptions`
- `consultation_rooms`
- `audit_logs`, `notifications`, `notification_preferences`
- `user_sessions`
- `doctor_password_setup_tokens`

It seeds `admin`, `doctor`, and `patient` roles only. It does **not** create a default administrator password.

Do **not** import `database/migrations/001_initial_schema.sql` on production. That file drops tables and seeds the local account `admin@telehealth.local` / `admin123`.

Create the first administrator from the project root after `.env` is in place:

```bash
php bin/create_admin.php "System Administrator" your-admin@example.com "YourStrongPassword"
```

The password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.

If this database was copied from a local/XAMPP environment, change or disable `admin@telehealth.local` immediately.

## 5. Required environment variables

Copy `.env.example` to `.env` on the server and set production values. `.env` must sit in the **project root** (one level above `public/`), never inside `public/`.

### Application

| Variable | Production value |
|----------|------------------|
| `APP_NAME` | Display name, for example `MBPHA TeleHealth Consultation System` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (forced off when `APP_ENV=production`) |
| `APP_URL` | Full public origin **including** the path to `public` if the app is not at domain root. Example: `https://telehealth.example.gov.pg` or `https://example.gov.pg/telehealth/public` |
| `APP_TIMEZONE` | `Pacific/Port_Moresby` |

`APP_URL` is required. Invitation emails and routing use it. Do not leave the local XAMPP URL.

### Database

| Variable | Notes |
|----------|--------|
| `DB_HOST` | Remote MySQL hostname or IP. Not assumed to be localhost. |
| `DB_PORT` | Usually `3306` |
| `DB_NAME` | Database name |
| `DB_USER` | Application user with rights on `DB_NAME` only |
| `DB_PASS` | Database password |
| `DB_SOCKET` | Optional. Unix socket path. When set, `DB_HOST` is not used. |

### Sessions

| Variable | Notes |
|----------|--------|
| `SESSION_NAME` | Cookie name, default `TELEHEALTH_SESSION` |
| `SESSION_LIFETIME` | Seconds, default `7200` |

Set `APP_URL` to an `https://` address so the session cookie is marked Secure.

### Mail / SMTP

| Variable | Notes |
|----------|--------|
| `MAIL_MAILER` | `smtp` in production (`log` only writes to `tmp/mail`) |
| `MAIL_HOST` | SMTP host, for Gmail `smtp.gmail.com` |
| `MAIL_PORT` | `587` (TLS) or `465` (SSL) |
| `MAIL_USERNAME` | SMTP username, currently `telehealth76@gmail.com` |
| `MAIL_PASSWORD` | App password or SMTP password. **Never put this in PHP/JS/HTML/SQL.** |
| `MAIL_ENCRYPTION` | `tls` or `ssl` |
| `MAIL_FROM_ADDRESS` | Sender address, currently `telehealth76@gmail.com` |
| `MAIL_FROM_NAME` | Sender display name |

Gmail requires an App Password when 2-step verification is on. Do not use the Google account password in `.env` if Google has disabled that.

### Daily.co video

| Variable | Notes |
|----------|--------|
| `DAILY_API_KEY` | Restricted Daily API key |
| `DAILY_DOMAIN` | Daily subdomain only, for example `your-team.daily.co` |

Without these, booking approval cannot create video rooms.

### Google Identity Services

| Variable | Notes |
|----------|--------|
| `GOOGLE_CLIENT_ID` | Google Cloud **Web client ID** |

This application uses Google Identity Services ID tokens. It does **not** use `GOOGLE_CLIENT_SECRET` and does not use an OAuth authorization-code redirect. The client ID is public in the login/register page by design.

In Google Cloud Console, for the Web client:

1. Add the production origin under **Authorized JavaScript origins** (scheme + host + optional port, no path). Example: `https://telehealth.example.gov.pg`
2. If the console requires a redirect URI, add the production origin. The app posts the credential to `/auth/google` on the same origin; there is no `/auth/google/callback` route.

### Doctor invitations

| Variable | Default |
|----------|---------|
| `DOCTOR_INVITE_TOKEN_TTL_HOURS` | `24` |
| `DOCTOR_INVITE_RESEND_COOLDOWN_MINUTES` | `5` |
| `DOCTOR_INVITE_MAX_SENDS_PER_DAY` | `8` |

These are operational limits, not secrets.

### OpenRouter / AI

Not used. The OpenRouter consultation-review feature was removed. There is no `OPENROUTER_API_KEY` to configure. Do not add an automatic AI review step.

## 6. Writable directories

Make **only** these directories writable by the PHP/Apache user (for example `www-data`). Do not make the whole project writable.

| Directory | Why |
|-----------|-----|
| `logs/` | `logs/error.log` |
| `tmp/` | Session files in `tmp/sessions/`, optional mail log in `tmp/mail/`, Google JWKS cache `tmp/google_jwks.json` |
| `public/uploads/` | Profile photos and doctor signatures (`public/uploads/patients/`, `public/uploads/doctors/`, `public/uploads/admin/`) |

Suggested permissions after deploy (adjust the user to match the host):

```bash
mkdir -p logs tmp/sessions tmp/mail public/uploads
chown -R www-data:www-data logs tmp public/uploads
chmod 775 logs tmp tmp/sessions tmp/mail public/uploads
```

Keep `app/`, `routes/`, `database/`, `bin/`, `vendor/`, `public/css/`, and `public/js/` read-only at runtime.

## 7. Required Apache / `.htaccess` configuration

### Document root

Point the virtual host **DocumentRoot** at `public/`:

```apache
<VirtualHost *:443>
    ServerName telehealth.example.gov.pg
    DocumentRoot /var/www/telehealth/public

    <Directory /var/www/telehealth/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    SSLEngine on
    # SSLCertificateFile and SSLCertificateKeyFile as provided by the host
</VirtualHost>
```

`AllowOverride All` is required so `public/.htaccess` can rewrite routes to `index.php`.

`mod_rewrite` must be enabled:

```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### What the shipped `.htaccess` files do

| File | Purpose |
|------|---------|
| `public/.htaccess` | Front-controller rewrite, no directory listing, block `.env`, block PHP under `uploads/` |
| `public/uploads/.htaccess` | Deny PHP execution in uploaded files |
| `app/.htaccess`, `bin/.htaccess`, `database/.htaccess` | Deny HTTP access if the document root is mis-set |
| `logs/.htaccess`, `tmp/.htaccess` | Deny HTTP access to logs and sessions |
| `/.htaccess` | Safety net: rewrite into `public/` and deny `.env` if the vhost root is the project folder |

If the host cannot change DocumentRoot, the project-root `.htaccess` rewrites into `public/`. Prefer setting DocumentRoot to `public/` when the host allows it.

### PHP settings the application enforces

When `APP_ENV=production`, the application sets `display_errors=0` and logs to `logs/error.log`. Users see a generic error page. Do not set `display_errors=On` in the server `php.ini` for this vhost.

## 8. Required SMTP settings

Production doctor invitations will not leave the server unless:

- `MAIL_MAILER=smtp`
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` are set
- `MAIL_FROM_ADDRESS` is a mailbox the SMTP account is allowed to send as

Current operational sender: `telehealth76@gmail.com`. Store the Gmail App Password only in `.env` as `MAIL_PASSWORD`.

## 9. Google OAuth / GIS settings

1. Keep the existing Google button on `/login` and `/register`.
2. Put the Web client ID in `GOOGLE_CLIENT_ID`.
3. Update Authorized JavaScript origins to the production HTTPS origin.
4. Do not add a client secret to the application. The current flow does not use one.

## 10. OpenRouter settings

None. AI-assisted consultation review is not part of this deployment.

## 11. Production URL values that must be changed

Replace every local XAMPP value before going live:

| Item | Local example | Production |
|------|---------------|------------|
| `APP_URL` | `http://localhost/Telehealth_Consultation_System/public` | Public HTTPS URL of `public/` |
| `APP_ENV` | `development` | `production` |
| `APP_DEBUG` | `true` | `false` |
| `DB_HOST` | `localhost` | Hosted MySQL hostname or IP |
| `MAIL_MAILER` | `log` | `smtp` |
| `MAIL_FROM_ADDRESS` | `noreply@telehealth.local` | `telehealth76@gmail.com` (or the live sender) |
| Google JS origins | `http://localhost` | Production origin |
| Daily domain | local Daily subdomain | The live Daily subdomain |

Video consultations require a browser **secure context**. Serve the site over HTTPS. Camera and microphone will not work on a public `http://` hostname.

Do not change third-party API URLs (`https://api.daily.co/v1`, `https://www.googleapis.com/oauth2/v3/certs`). Those are external.

## 12. Post-deployment testing checklist

Install dependencies on the server from the project root:

```bash
composer install --no-dev --optimize-autoloader
```

Then verify:

1. `https://YOUR-APP-URL/` loads the public home page.
2. `https://YOUR-APP-URL/.env` is **not** downloadable (404 or 403).
3. `https://YOUR-APP-URL/../.env` is **not** downloadable.
4. Directory listing is off for `/`, `/uploads`, and `/css`.
5. Patient registration and email/password login work.
6. Administrator login works with the account created by `bin/create_admin.php`.
7. A patient cannot open `/admin/dashboard` or `/doctor/dashboard`.
8. A doctor cannot open `/admin/dashboard`.
9. Google sign-in still works for patients after Console origins are updated.
10. Administrator can create a doctor and the invitation email arrives.
11. The invitation link uses the production `APP_URL`, not localhost.
12. Patient booking, administrator approve/reject, and doctor availability still work.
13. Video room join works on HTTPS (camera/microphone prompt appears).
14. Doctor can save a consultation record and prescription.
15. Patient can download consultation record and prescription PDFs.
16. Profile photo and doctor signature upload succeed; a `.php` file is rejected.
17. Logout ends the session; the next protected URL redirects to login.
18. A forced error is **not** shown to the browser (check `logs/error.log` instead).
19. SMTP credentials, Daily key, and database password never appear in HTML source or error pages.

## InfinityFree (mbpha-telehealth.wuaze.com)

InfinityFree cannot change the document root. Upload the **entire project** into `htdocs/` (including `public/`, `app/`, `vendor/`, `.htaccess`, and `.env`). Do not upload only the contents of `public/`. Do not place files above `htdocs`.

1. In the control panel, set PHP to **8.1 or later**.
2. On your computer run `composer install --no-dev --optimize-autoloader`, then upload `vendor/` with the project. InfinityFree has no SSH/Composer.
3. Copy `.env.example` to `.env` inside `htdocs` via FTP. Fill `DB_PASS`, `MAIL_*` SMTP fields, `DAILY_*`, and `GOOGLE_CLIENT_ID`. Leave those secrets out of Git.
4. `APP_URL` must be `https://mbpha-telehealth.wuaze.com` (no `/public` suffix).
5. `DB_HOST` must be `sql212.infinityfree.com`. InfinityFree MySQL is not reachable from your home PC; import `database/schema.sql` with the InfinityFree phpMyAdmin.
6. Create the first admin in phpMyAdmin or run `php bin/create_admin.php` locally against a dump — the CLI script cannot reach InfinityFree MySQL from home.
7. Make `logs/`, `tmp/`, `tmp/sessions/`, `tmp/mail/`, and `public/uploads/` writable (chmod 775).
8. In Google Cloud Console add `https://mbpha-telehealth.wuaze.com` as an Authorized JavaScript origin.
9. Gmail SMTP on port 587 with TLS is allowed; PHP `mail()` is not. Put the App Password only in server `.env`.

The root `htdocs/.htaccess` rewrites traffic into `/public`. Invitation emails use `APP_URL`, so they become `https://mbpha-telehealth.wuaze.com/doctor/setup-password?token=...`.

## Composer lock file

After a local `composer install`, commit `composer.lock` so production installs the same DomPDF version. The lock file is no longer gitignored.
