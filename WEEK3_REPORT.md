# WEEK 3 REPORT

## Project Information

- Project Title: MBPHA TeleHealth Consultation System
- Week: Week 3
- Scope Covered in This Report: Day 1 Administrator User Management Foundation, Day 2 Doctor Account Management, Day 3 Patient Management with Administrator Profile Management, and Week 3 finalization of the Administrator Management module

## Week 3 Day 1 Objective

The objective of this implementation was to establish the administrator user management foundation without changing the existing authentication flow, dashboard shell, or broader application functionality.

The work completed today focused only on:

- Administrator dashboard enhancements
- User Management page
- View all users
- Search users
- Filter users by role
- Filter users by account status
- View user details
- Responsive Bootstrap table
- Dashboard statistics
- Pagination for user listing

## Week 3 Day 2 Objective

The objective of this implementation was to extend the existing administrator governance foundation with secure doctor account management while preserving the current MVC architecture, authentication flow, and security controls.

The work completed today focused only on:

- Create doctor account
- Edit doctor account
- Activate doctor account
- Deactivate doctor account
- Reset doctor password
- Extend the linked doctor profile with required Week 3 Day 2 clinician fields
- Maintain administrator-only provisioning for doctor accounts

## Week 3 Day 3 Objective

The objective of this implementation was to extend the existing Week 3 administration layer with patient account governance and administrator self-management while preserving the current MVC architecture, shared authentication flow, and existing security controls.

The work completed today focused only on:

- View patients
- Search patients
- Edit patient
- Activate patient
- Deactivate patient
- Edit administrator profile
- Change administrator password
- Update administrator profile information

## Week 3 Finalization Objective

The objective of the final Week 3 pass was to review the entire Administrator Management module without adding any new module, then harden the existing implementation for quality, security, usability, and presentation.

The work completed in this finalization pass focused on:

- validation consistency
- error handling improvements
- responsive design polish
- UI consistency
- accessibility improvements
- Bootstrap styling refinements
- duplicate code reduction
- lightweight performance-minded cleanup
- security review and verification

## Completed Work

### 1. Administrator Dashboard Enhancements

The administrator dashboard was upgraded from placeholder-only content to a Week 3 Day 1 foundation that now includes:

- Live user statistics sourced from the database
- Latest visible user snapshot
- User-management-oriented quick actions
- Updated administrator sidebar navigation
- Week 3 platform status messaging

### 2. Administrator User Management Foundation

An administrator-only user management module was added with:

- `/admin/users`
- `/admin/users/{id}`

This foundation supports:

- Viewing all users from the `users` table joined to `roles`
- Searching by full name or email
- Filtering by role
- Filtering by account status
- Viewing role-aware user details
- Responsive table presentation
- Pagination support

### 3. Security and Access Control

The implementation continues using the existing security foundation:

- Administrator-only route protection through `RoleMiddleware`
- Existing shared authentication flow
- Existing secure session handling
- PDO prepared statements
- Escaped output in views

No new authentication mechanism was introduced.

No business logic outside the administrator user visibility foundation was modified.

### 4. Doctor Account Management

An administrator-only doctor account management module was added with:

- `/admin/doctors`
- `/admin/doctors/create`
- `/admin/doctors/{id}/edit`
- `/admin/doctors/{id}/activate`
- `/admin/doctors/{id}/deactivate`
- `/admin/doctors/{id}/reset-password`

This module supports:

- secure doctor account creation by an administrator only
- automatic creation of the linked `users` and `doctor` records
- automatic assignment of `role = doctor`
- password hashing with `password_hash()`
- duplicate email prevention
- optional employee ID uniqueness validation
- clinician profile editing
- activate and deactivate account controls
- password reset workflow for existing doctor accounts
- responsive Bootstrap-based management screens

### 5. Administrator Dashboard Day 2 Enhancements

The administrator dashboard was extended to surface doctor account governance directly from the dashboard layer through:

- doctor account summary cards
- direct navigation to doctor management
- direct navigation to create doctor account
- updated governance messaging aligned with Week 3 Day 2 scope

### 6. Patient Management

An administrator-only patient management module was added with:

- `/admin/patients`
- `/admin/patients/{id}/edit`
- `/admin/patients/{id}/activate`
- `/admin/patients/{id}/deactivate`

This module supports:

- patient listing with pagination
- search by full name, email, or address
- filtering by account status
- editing linked `users` and `patient` data
- patient account activation and deactivation
- responsive Bootstrap-based management screens

### 7. Administrator Profile Management

An administrator profile management module was added with:

- `/admin/profile`

This module supports:

- editing administrator profile information
- updating administrator full name
- updating administrator email
- updating administrator employee ID
- changing the current administrator password
- strong password validation
- duplicate email and employee ID prevention
- continued use of CSRF verification and prepared statements

### 8. Week 3 Finalization Improvements

The Administrator Management module was reviewed end to end and improved in the following areas:

- shared password-strength validation logic centralized through the common helper layer
- improved role-protection feedback by redirecting unauthorized authenticated users back to their allowed dashboard with a professional message
- administrator dashboard quick actions updated to provide direct navigation links
- shared alert rendering improved with contextual status icons
- responsive administrator action groups improved for smaller screens
- table accessibility improved with hidden captions and clearer action labels
- topbar accessibility improved with better control labelling
- safer date formatting introduced for user-management displays
- administrator password changes now reject reuse of the current password
- invalid CSRF submissions verified to leave protected data unchanged

## Files Created

- `app/Services/AdminUserService.php`
- `app/Services/AdminDoctorService.php`
- `app/Services/AdminPatientService.php`
- `app/Services/AdminProfileService.php`
- `app/Views/admin/users/index.php`
- `app/Views/admin/users/show.php`
- `app/Views/admin/doctors/index.php`
- `app/Views/admin/doctors/create.php`
- `app/Views/admin/doctors/edit.php`
- `app/Views/admin/doctors/reset_password.php`
- `app/Views/admin/doctors/_form.php`
- `app/Views/admin/patients/index.php`
- `app/Views/admin/patients/edit.php`
- `app/Views/admin/patients/_form.php`
- `app/Views/admin/profile/edit.php`
- `database/migrations/003_add_doctor_account_management_fields.sql`
- `WEEK3_REPORT.md`

## Files Modified

- `app/Controllers/AdminController.php`
- `app/Core/Router.php`
- `app/Models/Doctor.php`
- `app/Models/Admin.php`
- `app/Models/Patient.php`
- `app/Models/User.php`
- `app/Views/admin/dashboard.php`
- `app/Views/partials/dashboard/overview.php`
- `app/Views/partials/dashboard/sidebar.php`
- `app/Views/partials/dashboard/topbar.php`
- `app/Views/partials/shared/alerts.php`
- `app/Helpers/Helper.php`
- `app/Middleware/RoleMiddleware.php`
- `app/Controllers/AuthController.php`
- `app/Controllers/DoctorController.php`
- `app/Controllers/PatientController.php`
- `public/css/style.css`
- `routes/web.php`
- `README.md`

## Architecture Notes

### Controller Layer

`AdminController` remains thin and now coordinates:

- administrator dashboard rendering
- user management listing
- user detail rendering

### Service Layer

`AdminUserService` was introduced to centralize:

- dashboard user statistics
- latest visible users
- user listing filters
- pagination preparation
- user detail retrieval

### Model Layer

`User` was extended with repository-style query methods to support:

- summary counts
- latest user lookup
- filtered user counts
- filtered paginated listing
- detailed user retrieval

### View Layer

New administrator views were added under `app/Views/admin/users/` and reuse the shared dashboard layout and design system.

### Day 2 Service Layer

`AdminDoctorService` was introduced to centralize:

- doctor listing filters
- pagination preparation
- doctor form normalization
- create and update validation
- linked user and doctor record persistence
- doctor status updates
- doctor password resets

### Day 2 Model Layer

The `Doctor` model was extended with management-focused repository methods and new Week 3 Day 2 profile fields:

- `phone`
- `gender`
- `professional_title`
- `employee_id`

The `User` model was extended to support:

- duplicate email exclusion checks during editing
- role lookup by role name
- direct status updates
- direct password hash updates

### Day 2 Database Layer

A new migration was added instead of rewriting the initial schema so the project could extend the existing doctor table safely:

- `database/migrations/003_add_doctor_account_management_fields.sql`

This migration adds:

- `phone`
- `gender`
- `professional_title`
- `employee_id`

### Day 3 Service Layer

`AdminPatientService` was introduced to centralize:

- patient listing filters
- pagination preparation
- patient form normalization
- patient account update validation
- linked user and patient record persistence
- patient status updates

`AdminProfileService` was introduced to centralize:

- administrator profile detail loading
- profile form normalization
- administrator profile validation
- profile updates across `users` and `admin`
- administrator password updates

### Day 3 Model Layer

The `Patient` model was extended with management-focused repository methods for:

- patient summaries
- patient listing
- patient detail retrieval
- patient management filtering

The `Admin` model was extended to support:

- profile detail retrieval
- employee ID uniqueness checks
- administrator detail persistence

### Week 3 Finalization Notes

The finalization pass did not introduce any new module. Instead, it reviewed and improved the existing Administrator Management module through targeted hardening across:

- controllers
- middleware
- shared helpers
- shared dashboard partials
- administrator management views
- validation and feedback behavior

## Testing Performed

The following was verified in the browser using the seeded administrator account:

- Administrator login succeeds
- Administrator dashboard loads correctly
- Administrator dashboard shows live user statistics
- `/admin/users` loads correctly
- Search by email works
- Role filter works
- Status filter works
- Empty-state handling works when no results match
- User detail page loads correctly
- Responsive table layout remains usable

The following doctor account management flows were also verified in code and through interface review:

- Doctor management page renders with summary cards, search, status filter, and action controls
- Create doctor form includes required fields and Bootstrap validation wiring
- Edit doctor form preserves the linked clinician profile structure
- Activate and deactivate actions submit through CSRF-protected POST requests
- Reset password form requires a strong password and matching confirmation
- Service layer uses PDO prepared statements and transactions for linked record creation

The following patient and administrator Day 3 flows were also verified in code and through interface review:

- Patient management page renders with summary cards, search, status filter, and action controls
- Patient edit form preserves existing registration data and updates linked `users` and `patient` records
- Patient activate and deactivate actions submit through CSRF-protected POST requests
- Administrator profile form updates identity details while preserving the authenticated admin account
- Administrator password change requires the current password and a strong replacement password

The following Week 3 finalization checks were additionally completed:

- PHP linting passed for all changed controller, service, helper, middleware, and view files
- Unauthenticated access to `/admin/dashboard` returned a redirect to `/login`
- Live administrator session checks returned `200 OK` for:
  - `/admin/dashboard`
  - `/admin/users`
  - `/admin/doctors`
  - `/admin/patients`
  - `/admin/profile`
- Invalid doctor create submission returned the expected professional validation error message
- Invalid administrator password change returned the expected current-password error
- Invalid patient status POST with a bad CSRF token left the patient account status unchanged in the database
- Database integrity checks confirmed:
  - no `users` without roles
  - no orphaned `doctor` records
  - no orphaned `patient` records
  - no orphaned `admin` records
- Stored administrator credentials remained password-hashed in the database

## Issues Encountered

### 1. Search Filter Query Error

Issue:

- Searching with filters initially triggered `SQLSTATE[HY093]: Invalid parameter number`

Cause:

- The same named PDO placeholder was reused twice in one search condition while native prepares were enabled

Resolution:

- Separate placeholders were used for the full-name and email search expressions

### 2. User Detail Route Dispatch Error

Issue:

- `/admin/users/{id}` initially produced a fatal dispatch error

Cause:

- Named route captures were being passed directly into `call_user_func_array()`

Resolution:

- Router dispatch was updated to pass ordered parameter values safely to controller methods

## Current Status

Week 3 Day 1, Day 2, Day 3, and finalization are now complete for the implemented Administrator Management scope.

The system currently supports:

- administrator user visibility
- search and filtering
- detail inspection
- administrator dashboard user statistics
- administrator-managed doctor account creation
- doctor account editing
- doctor activation and deactivation
- secure doctor password reset
- linked user and doctor profile persistence
- administrator-managed patient account review and editing
- patient activation and deactivation
- administrator profile editing
- administrator password changes
- finalized administrator management validation, security feedback, accessibility, and responsive UI polish

The following are intentionally not included in today’s scope:

- deletion workflows
- doctor availability management

## Preparation for Next Work

This foundation is now ready for future controlled administrator functions such as:

- user account editing
- role-specific management workflows
- expanded audit and governance features
- doctor availability scheduling in its own scoped module
- patient booking workflows in their own scoped module

## Git

- Recommended commit:
  - `feat(week3): finalize administrator management module`
