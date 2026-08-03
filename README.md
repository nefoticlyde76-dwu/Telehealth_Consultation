# MBPHA TeleHealth Consultation System

Production-quality MBPHA TeleHealth Consultation System for the Milne Bay Provincial Health Authority (MBPHA), developed as a final year Bachelor of Information Systems capstone project.

## Current Status

- Current Week: Week 4
- Architecture: Custom MVC (PHP 8.x)
- Database: MySQL with PDO prepared statements
- Frontend: HTML5, CSS3, Bootstrap 5, Bootstrap Icons, Vanilla JavaScript
- Authentication: Implemented for patient registration, login, logout, session handling, CSRF, and role-based redirects
- Public Website: Implemented
- Dashboards: Initial patient, doctor, and administrator dashboards implemented
- Administration: Week 3 Day 1 user management foundation implemented for administrators
- Doctor Accounts: Week 3 Day 2 doctor account management implemented for administrator-controlled clinician onboarding
- Patient Management: Week 3 Day 3 patient management implemented for administrator oversight and account maintenance
- Administrator Profile: Week 3 Day 3 profile editing and password management implemented for administrators
- Week 3 Finalization: Administrator Management module reviewed and hardened for validation, security, accessibility, responsive behavior, and UI consistency
- Doctor Dashboard: Week 4 Day 1 professional doctor dashboard and profile management implemented for clinicians
- Doctor Availability: Week 4 Day 2 availability scheduling implemented for doctor-managed consultation slots
- Profile Pictures: Direct profile picture uploads with live avatar updates are implemented for administrator, doctor, and patient profiles
- Design System: Official MBPHA TeleHealth Design System and colour palette applied through a shared theme layer
- Branding: Official MBPHA TeleHealth logo applied across shared layouts, public pages, and dashboards

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

### Week 3 Features

- Administrator dashboard enhancements with real user statistics
- Administrator user management foundation
- View all users
- Search users by name or email
- Filter users by role
- Filter users by account status
- View user details
- Responsive administrator user table
- Pagination support for user listing
- Doctor account management
- Create doctor account
- Edit doctor account
- Activate doctor account
- Deactivate doctor account
- Reset doctor password
- Linked `users` and `doctor` record creation with transaction support
- Doctor profile fields for full name, email, phone, gender, professional title, specialization, employee ID, and status
- Patient management
- View patients
- Search patients
- Edit patient accounts
- Activate and deactivate patient accounts
- Linked `users` and `patient` record updates for administrator patient management
- Administrator profile management
- Edit administrator profile
- Update administrator profile information
- Change administrator password
- Finalized administrator management module
- Administrator dashboard quick actions now link directly to management pages
- Improved role-protection feedback for unauthorized access attempts
- Improved shared validation helpers for consistent password policy checks
- Accessibility improvements for tables, form controls, action labels, and dashboard controls
- Responsive improvements for admin action groups, preview cards, and dashboard cards
- Hardened CSRF handling verification for administrator status and profile flows
- Final Week 3 review for validation, error handling, security, and UI consistency

### Week 4 Day 1 Features

- Professional doctor dashboard experience using the shared dashboard layout and MBPHA design system
- Doctor dashboard statistics cards for total available slots, total booked slots, upcoming consultations, and completed consultations
- Doctor dashboard quick action cards for profile viewing, profile editing, asset updates, and availability management
- Doctor profile viewer (`/doctor/profile`)
- Doctor profile editing (`/doctor/profile/edit`)
- Update doctor phone number and specialization
- Upload/change doctor profile picture (JPG/PNG/WEBP, max 5MB)
- Upload/update doctor digital signature (PNG/JPG, max 2MB)
- Doctor password change requiring the current password, strong password validation, and session regeneration

### Week 4 Day 2 Features

- Doctor availability listing at `/doctor/availability`
- Create doctor availability at `/doctor/availability/create`
- Edit doctor availability at `/doctor/availability/{id}/edit`
- Delete doctor availability via secure POST action
- Availability fields:
  - consultation date
  - start time
  - end time
  - optional notes
  - status (`Available` by default; `Booked` is reserved for Week 5 booking workflows)
- Validation rules:
  - prevent overlapping slots
  - prevent duplicate slots
  - prevent past dates
  - require end time to be greater than start time
- Management rules:
  - only unbooked (`Available`) slots can be edited or deleted
  - booked slots are displayed as locked to preserve schedule integrity
- Responsive Bootstrap table with search, date filter, status filter, and pagination
- Doctor dashboard quick action updated to link directly to availability management

### Profile Enhancement

- Direct profile picture uploads without a cropping step
- Shared live avatar rendering in the dashboard sidebar, top navigation, dashboard welcome section, and profile screens
- Administrator profile photo updates
- Doctor profile photo uploads integrated into existing doctor profile management
- Patient profile view/edit screens for profile photo management
- Secure server validation for JPG, JPEG, PNG, and WEBP uploads up to 5 MB
- Unique filename generation and secure storage under role-specific upload directories

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
├── WEEK2_REPORT.md
└── WEEK3_REPORT.md
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
   - For typical XAMPP local setups, use `DB_HOST=localhost`.
5. Ensure Apache and MySQL are running in XAMPP.
6. Import the migration files:

```bash
mysql -u root -p < database/migrations/001_initial_schema.sql
mysql -u root -p < database/migrations/003_add_doctor_account_management_fields.sql
mysql -u root -p < database/migrations/004_add_doctor_profile_assets.sql
mysql -u root -p < database/migrations/005_add_profile_photo_fields_for_admin_and_patient.sql
mysql -u root -p < database/migrations/006_add_notes_to_doctor_availability.sql
```

7. Open the application using the configured `APP_URL`.

## Authentication Notes

- Public entry point is the landing page.
- Patients register through the public registration page.
- Patients, doctors, and administrators use the shared login page.
- Successful login redirects each user to the correct role dashboard.
- Logout is handled through a secure POST request with CSRF protection.
- Default seeded administrator account after running the migration:
  - Email: `admin@telehealth.local`
  - Password: `admin123`
- Administrator-only user management is currently available at `/admin/users` after successful administrator login.
- Administrator-only doctor account management is currently available at `/admin/doctors` after successful administrator login.
- Administrator-only patient management is currently available at `/admin/patients` after successful administrator login.
- Administrator profile management is currently available at `/admin/profile` after successful administrator login.

## Security Highlights

- Password hashing with `password_hash()`
- Password verification with `password_verify()`
- Session regeneration on login
- CSRF token verification for forms
- Role-protected dashboard routes
- PDO prepared statements
- Output escaping in views
- Request-aware base URL generation with `APP_URL` fallback
- Clinician profile uploads validated by size and MIME type and stored under `public/uploads/` (gitignored)
- Profile picture uploads validated by MIME type and size before the database path is updated

## Design System

- The application now uses the official MBPHA TeleHealth Design System across public pages, forms, navigation, footer, and dashboards
- Core palette is centralized in [theme.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/theme.css)
- Shared UI refinements are applied through [style.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/style.css)
- Colours are managed through CSS variables instead of page-level hardcoded values

## Testing Summary

- PHP syntax validation across all project PHP files
- VS Code diagnostics checks on changed PHP files
- Browser smoke test of the landing page, login page, registration page, dashboard shell, and contact form flow
- Verified contact form validation and successful inquiry logging
- Verified graceful user-facing handling when database connectivity is unavailable
- Verified administrator login and redirect after correcting the default admin seed password
- Verified administrator doctor-management flows in code for create, edit, activate, deactivate, and password reset handling with CSRF validation and prepared statements
- Verified administrator patient-management flows in code for search, edit, activate, and deactivate handling with CSRF validation and prepared statements
- Verified administrator profile update and password change handling in code with duplicate checks and password strength validation
- Verified unauthenticated access to `/admin/dashboard` redirects to `/login`
- Verified administrator login through a live HTTP session and confirmed `200 OK` for `/admin/dashboard`, `/admin/users`, `/admin/doctors`, `/admin/patients`, and `/admin/profile`
- Verified invalid patient status POST with a bad CSRF token leaves the database status unchanged
- Verified invalid doctor create submission returns the expected professional validation message
- Verified invalid administrator password change returns the expected current-password error message
- Verified database integrity checks for orphaned `admin`, `doctor`, and `patient` records returned zero issues
- Verified stored administrator password remains hashed in the database
- Verified doctor login through a live HTTP session and confirmed `200 OK` for `/doctor/dashboard`, `/doctor/profile`, and `/doctor/profile/edit`
- Verified doctor profile updates persist phone number and specialization using CSRF-protected POST handling
- Verified doctor profile photo and signature uploads pass MIME/size checks, store files under `public/uploads/doctors/{userId}/`, and update the linked doctor record paths
- Verified replacing a doctor upload removes the previous file within the expected clinician upload directory
- Verified invalid CSRF submissions for doctor profile updates leave protected fields unchanged in the database
- Verified doctor password change requires the current password, rejects reuse of the same password, and regenerates the session cookie
- Verified administrator, doctor, and patient profile photo uploads persist image paths in the correct role tables
- Verified administrator, doctor, and patient dashboard HTML immediately renders the updated avatar path in shared sidebar, topbar, and welcome components after save
- Verified replacing an existing patient avatar removes the previous stored file and keeps only the latest processed image
- Verified invalid profile photo submissions reject invalid file types and oversized uploads with professional validation messages
- Verified doctor-only access to `/doctor/availability`, `/doctor/availability/create`, and `/doctor/availability/{id}/edit`
- Verified doctor availability creation persists a valid slot in `doctor_availability`
- Verified duplicate slots are rejected
- Verified overlapping slots are rejected
- Verified past dates are rejected
- Verified invalid time ranges where end time is not greater than start time are rejected
- Verified doctor availability search and date/status filters return the expected slot rows
- Verified invalid CSRF availability deletion leaves the selected slot unchanged in the database
- Verified doctor availability edits persist updated time, notes, and status values
- Verified doctor availability deletion removes the selected slot from the database
- Verified availability pagination renders the second page correctly when more than 10 slots exist

## Known Environment Requirement

- Full login and registration submission require an active MySQL service matching the local `.env` configuration.
- For common XAMPP local environments, `DB_HOST=localhost` is the recommended database host value.

## Roadmap

- Week 1: Foundation completed
- Week 2: Public website and authentication module completed in code
- Week 3: Administrator management module completed
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
