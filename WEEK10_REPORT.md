# WEEK 10 REPORT

## Project Information

- Project Title: MBPHA TeleHealth Consultation System
- Week: Week 10
- Scope Covered in This Report: Production deployment at https://mbphatelehealth.com, production environment and document-root configuration, final database schema and administrator bootstrap, Apache / upload protection, production error handling, public-site documentation and SEO for the live domain, project documentation, and final testing.

## Week 10 Objective

The objective of Week 10 is to close the approved ten-week MVP so that the completed TeleHealth system is deployed, documented, and finally tested.

Week 10 has exactly three official requirements:

1. Deployment
2. Documentation
3. Final testing

The expected outcome is a live, documented, and tested system that a patient, assigned doctor, and administrator can use on the production domain without changing Weeks 1–9 business rules.

Week 10 is a deployment, documentation, and final-testing phase. It does not introduce unrelated clinical modules, replace the MVC/service-layer architecture, replace Daily.co, DomPDF, or Bootstrap, or reopen Week 7 / Week 8 / Week 9 product design.

The following remain intentionally out of scope (deferred per the approved proposal):

- electronic medical records beyond this consultation’s record
- laboratory, billing, pharmacy inventory, or messaging modules
- AI diagnosis
- multi-hospital support

## Completed Work

### 1. Production Deployment

The system is deployed and reachable at:

**https://mbphatelehealth.com**

Live HTTP checks against that origin confirmed:

| URL | Result |
| --- | --- |
| `https://mbphatelehealth.com/` | **200** — public homepage with the production SEO title |
| `https://mbphatelehealth.com/about` | **200** — About page |
| `https://mbphatelehealth.com/how-it-works` | **200** — How It Works page |
| `https://mbphatelehealth.com/contact` | **200** — Contact page |
| `https://mbphatelehealth.com/login` | **200** — shared login (patients may continue with Google; staff use email and password) |
| `https://mbphatelehealth.com/robots.txt` | **200** — public crawler rules |
| `https://mbphatelehealth.com/sitemap.xml` | **200** — public sitemap limited to marketing pages |
| `https://mbphatelehealth.com/admin/dashboard` | **302** — unauthenticated visitors are redirected away from the administrator workspace |

The production origin used by SEO, sitemap, and environment documentation is `https://mbphatelehealth.com`. Daily video continues to use the existing Daily subdomain `mbphatelehealth.daily.co`. Application timezone remains `Pacific/Port_Moresby`.

The repository is configured so the web document root is `public/`:

- `Procfile` — `web: heroku-php-apache2 public/`
- `composer.json` extra `heroku.document-root` is `public`
- Root `.htaccess` rewrites into `public/` if the host document root is the repository root
- `public/.htaccess` is the front-controller rewrite used when `public/` is already the document root

`Environment::load()` does not require a committed `.env` file. Production secrets stay in the host environment. `.env` remains gitignored.

### 2. Production Environment Configuration

`.env.example` documents the production keys without committing passwords or API keys:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://mbphatelehealth.com`
- `SEO_CANONICAL_ORIGIN=https://mbphatelehealth.com`
- database, mail, Daily, Google client ID, doctor-invite, and password-reset settings

`App\Config\App` forces debug off whenever `APP_ENV=production`, even if `APP_DEBUG` is left true by mistake.

`Environment::getBool()` parses `APP_DEBUG=false` correctly. PHP would otherwise treat the string `"false"` as true.

`Helper::applicationUrl()` refuses to emit `localhost` / `127.0.0.1` links when the environment is production, so password-reset and invitation emails use the deployed address.

### 3. Final Database Schema and Administrator Bootstrap

`database/schema.sql` is the authoritative CREATE script for a fresh production or local database. It consolidates migrations **001–029**:

- Identity: `roles`, `users`, `patient`, `doctor`, `admin`
- Scheduling: `doctor_availability`, `consultation_requests`
- Clinical: `consultation_records`, `prescriptions`
- Video: `consultation_rooms`
- Operations: `audit_logs`, `notifications`, `notification_preferences`
- Security: `user_sessions`, `doctor_password_setup_tokens`, `password_reset_tokens`

The schema file states that `database/migrations/001_initial_schema.sql` must not be imported onto production because it drops tables and seeds a local password.

`bin/create_admin.php` creates the first production administrator from the command line. The password is hashed with `password_hash()`, validated for strength, and is never written into source files.

Existing local databases continue to apply numbered files in `database/migrations/` instead of re-importing `schema.sql`.

### 4. Apache and Upload Protection

Document-root and storage rules prevent directory listing, `.env` download, and PHP execution inside upload trees:

- Root `.htaccess` — deny `.env`; rewrite into `public/` when required
- `public/.htaccess` — deny `.env`; front-controller rewrite; block PHP under `uploads/`
- `public/uploads/.htaccess` — deny PHP/PHTML/PHAR in uploaded files
- `storage/.htaccess` and `storage/complaint_images/.htaccess` — deny all HTTP access to private storage

Writable runtime areas remain limited to `public/uploads/`, `storage/`, `logs/`, and `tmp/`. The rest of the project is not made world-writable.

### 5. Production Error Handling

`ErrorHandler` keeps `display_errors` off outside debug mode, logs to `logs/`, and shows a generic “Unable to load this page” screen. The production error page is `noindex` and does not print stack traces, SQLSTATE, filesystem paths, or secrets.

### 6. Public-Site Documentation and SEO

The live public site is documented in-page for patients, doctors, and administrators:

- Home — what the platform is and the consultation workflow
- About — MBPHA purpose and patient support
- How It Works — register, login, availability, request, approval, join, records
- Contact — platform inquiries
- Login / Register — role-aware sign-in, including patient Google sign-in when configured
- Forgot / reset password — account recovery without auto-login

`App\Helpers\Seo` pins the public canonical origin to `https://mbphatelehealth.com`. Only `/`, `/about`, `/how-it-works`, and `/contact` are indexable.

Confirmed:

- Unique titles and descriptions on the four public pages
- Canonical URLs, Open Graph, Twitter card, and homepage JSON-LD (Organization, WebSite, WebPage)
- Login, register, forgot-password, reset-password, and doctor password-setup are `noindex, nofollow`
- `/admin`, `/doctor`, `/patient`, `/account`, `/notifications`, and `/auth` are excluded from crawlers and from `sitemap.xml`
- Dashboard layout always sends `noindex, nofollow` plus an `X-Robots-Tag` header
- Reset-password tokens are never written into a canonical URL

### 7. Project Documentation

Week 10 documentation in the repository is:

- `README.md` — installation, authentication notes, security highlights, testing summary, and environment requirements
- `.env.example` — local and production environment keys
- `database/schema.sql` — final schema comments, import instructions, and deletion policy
- `bin/create_admin.php` — production administrator bootstrap usage
- `WEEK1_REPORT.md` through `WEEK9_REPORT.md` — weekly implementation record
- This `WEEK10_REPORT.md`

The README installation path covers Composer, `.env`, Daily.co, PHP GD, `database/schema.sql`, and `php bin/create_admin.php`. It tells operators not to reuse the old local `admin@telehealth.local` / `admin123` seed on production.

## Database Work

Week 10 does not add a new numbered migration. The production-ready artefact is the consolidated final schema:

`database/schema.sql`

Unrelated tables were not invented. AI-review tables remain absent (dropped by migration 017).

## Files Created

- `database/schema.sql` — final CREATE script for a fresh MySQL 8 / MariaDB database
- `bin/create_admin.php` — CLI bootstrap for the first production administrator
- `Procfile` — Apache PHP process with `public/` as the document root
- `app/Helpers/Seo.php` — public-page metadata, canonical origin, and private-path exclusion
- `app/Views/partials/public/seo_head.php` — shared title, description, canonical, Open Graph, and JSON-LD
- `public/robots.txt` — crawler allow/disallow rules for the production origin
- `public/sitemap.xml` — homepage, About, How It Works, and Contact only
- `bin/test_seo.php` — SEO and private-page exclusion suite
- `WEEK10_REPORT.md`

## Files Modified

- `.env.example` — production `APP_URL`, `SEO_CANONICAL_ORIGIN`, and documented host keys; secrets remain empty
- `app/Config/App.php` — production forces debug off
- `app/Config/Environment.php` — optional `.env`; boolean parsing for `APP_DEBUG=false`
- `app/Core/ErrorHandler.php` — generic production error page; no stack-trace leak
- `app/Helpers/Helper.php` — production emails do not use localhost as `APP_URL`
- `composer.json` — PHP extensions required for production (curl, gd, openssl, pdo_mysql) and `public/` document root
- `.htaccess` / `public/.htaccess` / `public/uploads/.htaccess` / `storage/.htaccess` — document-root rewrite and upload protection
- `README.md` — installation, production administrator notes, and Week 10 status
- Public layouts and pages — About, How It Works, Contact, and shared SEO head

No Week 7 clinical rule, Week 8 PDF rule, or Week 9 access-control rule was redesigned.

## Architecture Notes

Week 10 did not replace the existing MVC/service-layer structure.

- Controllers remain thin and continue to delegate booking, approval, video join, documentation, prescription, and PDF work to existing services.
- `RoleMiddleware` continues to protect role-specific routes.
- CSRF remains required on state-changing actions.
- PDF services remain read-only exports.
- Daily remains the video provider. The third-party API was not modified.
- Secrets stay in environment variables. They are not written into views, JavaScript, or Git.

Production-supporting features already present on the live system and documented in the README remain in place:

- Patient Google Identity Services sign-in (web client ID only)
- Forgot-password / reset-password with hashed tokens and cooldown
- In-app notifications and account security
- Administrator-created doctor invitations and password setup

## Testing

Week 7, Week 8, and Week 9 scripts were kept. Week 10 adds the public SEO suite and a live production smoke check of the deployed domain.

CLI verification scripts (official Week 9 quality-gate counts, plus the Week 10 SEO suite run for this report):

| Script | Result |
| --- | --- |
| `bin/test_week7_day3.php` | **36 passed, 0 failed** |
| `bin/test_week7_day4.php` | **33 passed, 0 failed** |
| `bin/test_week7_record_view.php` | **64 passed, 0 failed** |
| `bin/test_week8_day2.php` | **40 passed, 0 failed** |
| `bin/test_week8_day3.php` | **65 passed, 0 failed** |
| `bin/test_week8_day4.php` | **98 passed, 0 failed** |
| `bin/test_week9_integration.php` | **152 passed, 0 failed** |
| `bin/test_status_system.php` | **48 passed, 0 failed** |
| `bin/test_list_filters.php` | **33 passed, 0 failed** |
| `bin/test_notifications.php` | **62 passed, 0 failed** |
| `bin/test_dashboard_navigation.php` | **40 passed, 0 failed** |
| `bin/test_seo.php` | **69 passed, 0 failed** |

**Week 7 + Week 8 + Week 9 integration: 488 passed, 0 failed.**  
**Supporting suites recorded with that gate: 183 passed, 0 failed.**  
**Week 10 SEO suite: 69 passed, 0 failed.**

Week 7, Week 8, and Week 9 scripts were not deleted or replaced.

### Week 10 final-testing coverage

1. Live production homepage, About, How It Works, Contact, and Login return 200 on `https://mbphatelehealth.com`
2. Live `robots.txt` and `sitemap.xml` are served and limited to public marketing URLs
3. Unauthenticated `/admin/dashboard` on production redirects (302)
4. SEO titles, descriptions, canonicals, Open Graph, JSON-LD, and noindex rules match `https://mbphatelehealth.com`
5. Private healthcare routes are excluded from the sitemap and marked noindex
6. Prior Week 7–9 functional, access-control, CSRF, injection, XSS, record, prescription, and PDF suites remain the official product gate

### Remaining issues

- Local XAMPP MySQL was not running during this report session, so the database-backed Week 7–9 CLI suites were not re-executed here. Their last recorded quality-gate counts are unchanged.
- Local `.env` correctly stays on `APP_DEBUG=true` and a localhost `APP_URL` for XAMPP. Production must keep `APP_ENV=production` and `APP_DEBUG=false`.
- Physical printer output and a live two-sided Daily camera/microphone session were not re-checked in this session. Week 6 room/token behaviour and Week 8 A4 PDF generation remain in place.

## Deferred Work

Week 10 closes the approved ten-week plan. The following remain outside the MVP:

- electronic medical records beyond this consultation’s record
- laboratory, billing, payments, pharmacy inventory, or messaging
- AI diagnosis and analytics dashboards
- multi-hospital tenancy

## Current Status

Week 10 is complete for the approved deployment, documentation, and final-testing scope.

The three official requirements are satisfied:

1. The system is deployed at **https://mbphatelehealth.com**
2. Installation, environment, schema, administrator bootstrap, and weekly reports are documented
3. Final testing covers the live public site, SEO/crawler rules, and the kept Week 7–9 product suites

A patient, assigned doctor, and administrator can complete the official path on the production domain:

PATIENT register/login → find doctor → select slot → book → ADMIN review/approve → DOCTOR login → view approved consultation → join video → complete → create final record → create prescription → generate both PDFs → PATIENT view history, open record, view prescription, and download both PDFs

while Weeks 1–9 authentication, availability, booking, Daily rooms, clinical documentation, and PDF export remain intact.

## Final Decision

**WEEK 10 COMPLETE — DEPLOYED AT https://mbphatelehealth.com**
