# TeleHealth Consultation System

Production-quality TeleHealth Consultation System for the Milne Bay Provincial Health Authority (MBPHA), developed as a final year Bachelor of Information Systems capstone project.

## Current Status

- Current Week: Week 2
- Architecture: Custom MVC (PHP 8.x)
- Database: MySQL with PDO prepared statements
- Frontend: HTML5, CSS3, Bootstrap 5, Bootstrap Icons, Vanilla JavaScript
- Authentication: Implemented for patient registration, login, logout, session handling, CSRF, and role-based redirects
- Public Website: Implemented
- Dashboards: Initial patient, doctor, and administrator dashboards implemented

## Implemented Features

### Week 1 Foundation

- Composer PSR-4 autoloading
- Environment configuration loading
- MVC folder structure
- PDO database connection layer
- Initial database schema and migration
- Session foundation
- CSRF foundation
- Role middleware foundation
- Authentication service foundation
- Global error handling foundation

### Week 2 Features

- Premium public landing page
- Responsive public navigation bar
- About section
- Services section
- Features section
- How It Works section
- FAQ accordion
- Contact section with validated inquiry logging
- Patient registration page
- Login page for patient, doctor, and administrator access
- Authentication flow wiring
- Role-based dashboard redirects
- Initial patient dashboard
- Initial doctor dashboard
- Initial administrator dashboard
- Secure logout

## Project Structure

```text
Telehealth_Consultation_System/
├── app/
│   ├── Config/
│   ├── Controllers/
│   ├── Core/
│   ├── Helpers/
│   ├── Middleware/
│   ├── Models/
│   ├── Services/
│   └── Views/
│       ├── admin/
│       ├── auth/
│       ├── doctor/
│       ├── home/
│       ├── layouts/
│       ├── partials/
│       └── patient/
├── database/
│   └── migrations/
├── public/
│   ├── css/
│   ├── js/
│   └── index.php
├── routes/
├── tmp/
├── .env.example
├── .gitignore
├── README.md
├── WEEK1_REPORT.md
└── WEEK2_REPORT.md
```

## Installation

1. Clone the repository into your XAMPP `htdocs` directory.
2. Install Composer dependencies:

```bash
composer install
```

3. Create a local environment file:

```bash
copy .env.example .env
```

4. Update `.env` with your local database credentials and application URL.
5. Ensure Apache and MySQL are running in XAMPP.
6. Import the migration file:

```bash
mysql -u root -p < database/migrations/001_initial_schema.sql
```

7. Open the application using the configured `APP_URL`.

## Authentication Notes

- Public entry point is the landing page.
- Patients register through the public registration page.
- Patients, doctors, and administrators use the shared login page.
- Successful login redirects each user to the correct role dashboard.
- Logout is handled through a secure POST request with CSRF protection.

## Security Highlights

- Password hashing with `password_hash()`
- Password verification with `password_verify()`
- Session regeneration on login
- CSRF token verification for forms
- Role-protected dashboard routes
- PDO prepared statements
- Output escaping in views
- Request-aware base URL generation with `APP_URL` fallback

## Testing Summary

- PHP syntax validation across all project PHP files
- VS Code diagnostics checks on changed PHP files
- Browser smoke test of the landing page, login page, registration page, dashboard shell, and contact form flow
- Verified contact form validation and successful inquiry logging
- Verified graceful user-facing handling when database connectivity is unavailable

## Known Environment Requirement

- Full login and registration submission require an active MySQL service matching the local `.env` configuration.
- During the latest verification, the application code handled database unavailability gracefully, but end-to-end authentication remained dependent on the local MySQL service being online.

## Screenshots

- Landing page screenshot: pending capture
- Login page screenshot: pending capture
- Registration page screenshot: pending capture
- Patient dashboard screenshot: pending capture
- Doctor dashboard screenshot: pending capture
- Administrator dashboard screenshot: pending capture

## Roadmap

- Week 1: Foundation completed
- Week 2: Public website and authentication module completed in code
- Week 3: Doctor availability module
- Week 4: Patient booking module
- Week 5: Admin booking management
- Week 6: Video consultation
- Week 7: Consultation records and prescription module
- Week 8: Consultation history and PDF export
- Week 9: Testing, security review, bug fixing, and UI refinement
- Week 10: Deployment, documentation, and final testing

## Repository Notes

- Follow PSR-12 and SOLID principles
- Keep controllers thin and business logic in services/models
- Do not introduce features outside the approved week plan
- Preserve the established MVC structure, schema, and design language
