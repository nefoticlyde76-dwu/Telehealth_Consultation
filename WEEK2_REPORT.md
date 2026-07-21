# Week 2 Development Report

## Project Information

- Project Title: TeleHealth Consultation System
- Client: Milne Bay Provincial Health Authority
- Week Number: 2
- Development Period: July 21, 2026

## Week Objectives

- Implement the public landing page as the application entry point
- Build the public navigation and informational marketing sections
- Implement patient registration
- Implement the shared login page
- Wire the authentication flow and role-based redirects
- Create initial patient, doctor, and administrator dashboards
- Implement secure logout
- Update project documentation to reflect actual Week 2 progress

## Completed Tasks

### 1. Week 1 Readiness Audit

- Reviewed the full project structure, routes, configuration, schema, middleware, helper layer, assets, and implemented classes
- Confirmed the Week 1 foundation existed but identified missing wiring required before Week 2 could function
- Identified and fixed required blockers before building the UI

### 2. Required Week 1 Fixes For Week 2

- Added missing auth, contact, logout, and dashboard routes
- Extended the router to support middleware execution
- Fixed `app/Core/Model.php` by importing `PDO`
- Improved helper URL generation and redirect behavior for subfolder and alternate local runtime handling
- Added project-local session storage to avoid PHP session permission failures in the local environment
- Strengthened patient registration with transaction-safe writes
- Added active-account checks during authentication
- Added graceful database outage handling in login and registration flows
- Added the missing public JavaScript asset

### 3. Public Landing Page

- Implemented a premium public home page in [index.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/home/index.php)
- Implemented:
  - Hero section
  - About section
  - Services section
  - Features section
  - How It Works section
  - FAQ section
  - Contact section
  - Footer
- Added a professional healthcare illustration using the approved generated image endpoint

### 4. Navigation Bar

- Implemented a responsive reusable public navigation partial in [navbar.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/public/navbar.php)
- Added role-aware action buttons for:
  - Login
  - Register
  - Dashboard
  - Logout

### 5. Contact Section

- Implemented a validated public contact form on the landing page
- Added server-side validation in [HomeController.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Controllers/HomeController.php)
- Implemented successful inquiry logging to `logs/contact.log` during runtime
- Verified the success flow during browser testing

### 6. Patient Registration

- Implemented a production-quality patient registration page in [register.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/auth/register.php)
- Added:
  - Server-side validation
  - Client-side validation
  - Password strength meter
  - Password confirmation matching
  - Duplicate email detection
  - Transactional persistence for `users` and `patient`

### 7. Login Implementation

- Implemented a production-quality login page in [login.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/auth/login.php)
- Added support for:
  - Patient login
  - Doctor login
  - Administrator login
- Kept the existing Week 1 authentication foundation and extended it safely

### 8. Authentication And Role Redirects

- Registered shared auth routes in [web.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/routes/web.php)
- Preserved the existing `AuthService` foundation and extended it with:
  - account status checks
  - transaction handling for registration
  - consistent role-based redirect usage
- Added secure logout via POST with CSRF validation

### 9. Dashboard Implementation

- Added a reusable dashboard layout in [dashboard.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/layouts/dashboard.php)
- Added reusable dashboard partials:
  - [sidebar.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/dashboard/sidebar.php)
  - [topbar.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/dashboard/topbar.php)
  - [overview.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/dashboard/overview.php)
- Implemented initial dashboards for:
  - [patient/dashboard.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/patient/dashboard.php)
  - [doctor/dashboard.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/doctor/dashboard.php)
  - [admin/dashboard.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/admin/dashboard.php)

## Files Created

- [README.md](file:///c:/xampp/htdocs/Telehealth_Consultation_System/README.md)
- [WEEK2_REPORT.md](file:///c:/xampp/htdocs/Telehealth_Consultation_System/WEEK2_REPORT.md)
- [app/Views/home/index.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/home/index.php)
- [app/Views/auth/login.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/auth/login.php)
- [app/Views/auth/register.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/auth/register.php)
- [app/Views/layouts/dashboard.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/layouts/dashboard.php)
- [app/Views/partials/shared/alerts.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/shared/alerts.php)
- [app/Views/partials/public/navbar.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/public/navbar.php)
- [app/Views/partials/public/footer.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/public/footer.php)
- [app/Views/partials/dashboard/sidebar.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/dashboard/sidebar.php)
- [app/Views/partials/dashboard/topbar.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/dashboard/topbar.php)
- [app/Views/partials/dashboard/overview.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/dashboard/overview.php)
- [app/Views/patient/dashboard.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/patient/dashboard.php)
- [app/Views/doctor/dashboard.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/doctor/dashboard.php)
- [app/Views/admin/dashboard.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/admin/dashboard.php)
- [public/js/app.js](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/js/app.js)

## Files Modified

- [routes/web.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/routes/web.php)
- [app/Core/Router.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Core/Router.php)
- [app/Core/Model.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Core/Model.php)
- [app/Core/Session.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Core/Session.php)
- [app/Helpers/Helper.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Helpers/Helper.php)
- [app/Services/AuthService.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Services/AuthService.php)
- [app/Controllers/AuthController.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Controllers/AuthController.php)
- [app/Controllers/HomeController.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Controllers/HomeController.php)
- [app/Controllers/PatientController.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Controllers/PatientController.php)
- [app/Controllers/DoctorController.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Controllers/DoctorController.php)
- [app/Controllers/AdminController.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Controllers/AdminController.php)
- [app/Views/layouts/app.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/layouts/app.php)
- [public/css/style.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/style.css)
- [.gitignore](file:///c:/xampp/htdocs/Telehealth_Consultation_System/.gitignore)

## Authentication Implemented

- Shared login page
- Shared auth controller routes
- Patient self-registration
- Role-based redirect mapping
- POST logout with CSRF protection
- Graceful handling when the database connection is unavailable

## Landing Page Implementation

- Public entry point is the home page
- All requested public sections were implemented
- Bootstrap 5 components used include:
  - cards
  - accordion
  - badges
  - alerts
  - dropdown
  - offcanvas
  - breadcrumbs
  - responsive navbar

## Registration Implementation

- Patient-only registration flow implemented in code
- Validation includes:
  - full name
  - email format
  - duplicate email
  - password strength
  - password confirmation
  - date validation
  - gender validation
  - address length
  - terms acceptance

## Login Implementation

- Shared secure login form implemented
- Login uses the existing Week 1 auth service
- Role redirects are wired for patient, doctor, and admin users

## Dashboard Implementation

- Shared dashboard layout system created
- Initial dashboards implemented for all three roles
- Included:
  - sidebar
  - top navigation
  - profile menu
  - welcome banner
  - quick actions
  - stats cards
  - recent activity placeholders
  - professional empty states

## Testing Performed

- Full code audit before implementation
- VS Code diagnostics on updated PHP files
- `php -l` syntax validation across project PHP files
- Browser smoke testing on:
  - `/`
  - `/login`
  - `/register`
- Contact form validation and successful submission test
- Verified contact logging
- Verified graceful login and registration behavior when MySQL is unavailable
- Verified that the local MySQL port `3306` was not reachable during the final smoke test

## Challenges Encountered

### 1. Session Storage Permission Failure

- Problem: PHP sessions could not start because the default temp path was not writable in the local runtime
- Solution: configured the application to use a project-local session directory under `tmp/sessions`

### 2. Base URL Mismatch During Smoke Tests

- Problem: URL generation pointed to the configured XAMPP `APP_URL`, which broke form submission when testing on `127.0.0.1:8080`
- Solution: made helper URL generation request-aware with `APP_URL` as fallback

### 3. Database Connectivity Unavailable In Local Runtime

- Problem: login and registration could not complete end-to-end because the active MySQL service was not reachable on the configured local port
- Solution: preserved the implementation, added graceful user-facing error handling, and documented the remaining environment dependency

## Solutions Summary

- Week 1 blockers required for Week 2 were resolved first
- Reusable view partials were introduced to avoid duplicated HTML
- The public and dashboard interfaces were aligned to a shared design system
- Authentication-related failures now degrade safely instead of exposing raw exceptions

## Git Summary

- Suggested Conventional Commit:
  - `feat(week2): implement public website and authentication module`
- Final branch name, commit hash, and push confirmation depend on repository commit/push execution after staging

## Current Project Status

- Week 2 UI and auth module have been implemented in code
- Public website is functional
- Contact form is functional and validated
- Login and registration flows are wired correctly
- Full database-backed authentication requires the local MySQL service to be running
- No Week 3 functionality was implemented

## Preparation For Week 3

- The doctor dashboard and routing foundation are ready to connect to the Doctor Availability Module
- The codebase now has reusable dashboard scaffolding for later modules
- Once the database service is available locally, Week 2 end-to-end auth testing can be completed against the live schema
