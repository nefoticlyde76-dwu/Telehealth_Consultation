# WEEK 4 REPORT

## Project Information

- Project Title: MBPHA TeleHealth Consultation System
- Week: Week 4
- Scope Covered in This Report: Day 1 Doctor Dashboard and Doctor Profile Management, plus Day 2 Doctor Availability Scheduling

## Week 4 Day 1 Objective

The objective of this implementation was to deliver a professional clinician (doctor) dashboard and profile management experience while preserving the existing authentication foundation, MVC architecture, and security controls.

The work completed today focused only on:

- Professional doctor dashboard
- Dashboard statistics cards
- Quick action cards
- View doctor profile
- Edit doctor profile
- Update phone number
- Update specialization
- Upload/change profile picture
- Upload/update digital signature
- Change password

The following were intentionally not implemented in today’s scope:

- doctor availability scheduling
- booking workflows
- consultation records

## Week 4 Day 2 Objective

The objective of this implementation was to add secure doctor availability scheduling on top of the completed doctor dashboard and profile module, while preserving the existing MVC architecture, authentication flow, CSRF handling, and doctor-only role protection.

The work completed today focused only on:

- create availability
- view availability
- edit availability
- delete availability
- search availability
- filter availability by date
- filter availability by status
- responsive listing with pagination
- conflict-prevention validation

The following remain intentionally out of scope for today:

- patient booking
- consultation request approval workflows
- consultation records

## Completed Work

### 1. Professional Doctor Dashboard

The doctor dashboard was upgraded from an initial shell to a professional clinician workspace that now includes:

- statistics cards aligned with availability scheduling:
  - total available slots
  - total booked slots
  - upcoming consultations (booked, future-dated)
  - completed consultations (booked, past-dated)
- quick actions linking to profile management and availability scheduling
- clinician profile snapshot section for identity and assets

### 2. Doctor Profile Viewer

A dedicated doctor profile viewer was added at:

- `/doctor/profile`

This screen provides:

- clinician identity summary (full name, email, status)
- specialization and phone number visibility
- uploaded profile photo preview
- uploaded signature preview
- direct navigation to edit profile or return to dashboard

### 3. Doctor Profile Editing

A professional doctor profile editing experience was added at:

- `/doctor/profile/edit`

This screen supports:

- updating phone number with validation
- updating specialization with validation
- uploading/changing profile picture (JPG/PNG/WEBP, max 5MB)
- uploading/updating digital signature (PNG/JPG, max 2MB)
- changing password with current-password verification and strong password validation

### 4. Security and Access Control

The implementation continues using the established security foundation:

- doctor-only route protection through `RoleMiddleware`
- existing shared authentication flow
- existing secure session handling
- CSRF token verification for profile and password POST requests
- PDO prepared statements for persistence
- output escaping in views

### 5. Upload Handling (Profile Photo + Signature)

Doctor profile asset uploads were implemented with:

- per-doctor storage under `public/uploads/doctors/{userId}/`
- strict size limits (5MB for profile photo, 2MB for signature)
- MIME validation using `finfo(FILEINFO_MIME_TYPE)`
- safe deletion of the previous uploaded file only when it is inside the expected clinician upload directory prefix

### 6. Doctor Availability Scheduling

A dedicated doctor scheduling module was added with the following routes:

- `/doctor/availability`
- `/doctor/availability/create`
- `/doctor/availability/{id}/edit`

The module provides:

- consultation slot creation
- slot listing in a responsive Bootstrap table
- search by date, time, and notes
- filtering by consultation date and status
- pagination for larger schedules
- secure editing and deletion for unbooked slots only

### 7. Availability Validation Rules

The scheduling workflow now prevents:

- overlapping consultation slots for the same doctor on the same date
- exact duplicate slots
- past dates
- invalid time ranges where end time is not greater than start time

### 8. Availability Status Support

The existing schema already provided a status field, so the module now supports:

- `Available` (default)
- `Booked`

No patient booking workflow was implemented in this task. Newly created slots default to `Available`, and `Booked` slots are locked from edits and deletions to preserve schedule integrity.

## Database Work

Instead of rewriting the initial schema, a minimal migration was added to extend the existing doctor table safely:

- `database/migrations/004_add_doctor_profile_assets.sql`

This migration adds:

- `doctor.profile_photo_path` for storing the public path to the clinician profile photo

Another minimal migration was added for availability scheduling:

- `database/migrations/006_add_notes_to_doctor_availability.sql`

This migration adds:

- `doctor_availability.notes` for optional slot-specific notes

## Files Created

- `app/Services/DoctorDashboardService.php`
- `app/Services/DoctorProfileService.php`
- `app/Models/DoctorAvailability.php`
- `app/Services/DoctorAvailabilityService.php`
- `app/Views/doctor/availability/_form.php`
- `app/Views/doctor/availability/index.php`
- `app/Views/doctor/availability/create.php`
- `app/Views/doctor/availability/edit.php`
- `app/Views/doctor/profile/show.php`
- `app/Views/doctor/profile/edit.php`
- `database/migrations/004_add_doctor_profile_assets.sql`
- `database/migrations/006_add_notes_to_doctor_availability.sql`
- `WEEK4_REPORT.md`

## Files Modified

- `routes/web.php`
- `app/Controllers/DoctorController.php`
- `app/Models/Doctor.php`
- `app/Views/doctor/dashboard.php`
- `app/Views/partials/dashboard/sidebar.php`
- `app/Views/partials/dashboard/topbar.php`
- `public/css/style.css`
- `.gitignore`
- `README.md`

## Architecture Notes

### Controller Layer

`DoctorController` remains thin and coordinates:

- dashboard rendering
- profile rendering
- profile update orchestration and redirects
- password change orchestration and session regeneration

### Service Layer

Two new service classes were introduced to keep business logic out of the controller:

- `DoctorDashboardService`
  - prepares dashboard statistics and quick actions without implementing availability or booking logic
- `DoctorProfileService`
  - normalizes and validates profile data
  - performs profile updates within a database transaction
  - validates and stores uploads
  - updates doctor password hashes with strong password validation

One additional service class now supports doctor scheduling:

- `DoctorAvailabilityService`
  - normalizes schedule form data
  - validates CSRF, date, time, duplicate, and overlap rules
  - prepares filtered/paginated listing data
  - coordinates create, update, and delete scheduling operations

### Model Layer

The `Doctor` model was extended to support the new asset path:

- `profile_photo_path`

It also includes a profile-detail query used by doctor profile views:

- `Doctor::findProfileDetailByUserId()`

A dedicated availability model was added:

- `DoctorAvailability`
  - handles filtered listing queries
  - provides doctor-specific summaries
  - checks duplicate and overlapping slots
  - persists schedule changes

## Testing Performed

The following was verified via live HTTP sessions and database checks:

- doctor-only access to `/doctor/dashboard`, `/doctor/profile`, and `/doctor/profile/edit`
- unauthenticated access redirects to `/login`
- doctor profile updates persist phone number and specialization
- profile photo upload persists `doctor.profile_photo_path` and stores the file under `public/uploads/doctors/{userId}/`
- signature upload persists `doctor.signature_path` and stores the file under `public/uploads/doctors/{userId}/`
- replacing an upload removes the prior file in the expected clinician upload directory
- invalid CSRF submissions do not update doctor profile fields or upload paths
- doctor password change:
  - requires current password
  - rejects reusing the current password
  - enforces strong password rules
  - regenerates the session cookie after success
- doctor-only access to `/doctor/availability`, `/doctor/availability/create`, and `/doctor/availability/{id}/edit`
- successful availability creation persisted a new doctor slot in `doctor_availability`
- duplicate slot creation was rejected
- overlapping slot creation was rejected
- past-date slot creation was rejected
- invalid time ranges were rejected
- search and filter combinations returned the expected availability rows
- invalid CSRF deletion requests left the selected availability slot unchanged
- availability edit persisted updated time, notes, and status values
- availability deletion removed the selected slot from the database
- pagination rendered a second page correctly when more than 10 availability slots existed

## Current Status

Week 4 currently includes completed doctor dashboard/profile management and doctor availability scheduling.

The system currently supports:

- professional doctor dashboard visibility
- secure clinician profile review
- secure clinician profile updates (phone, specialization)
- secure clinician asset uploads (profile photo, digital signature)
- secure clinician password changes
- doctor availability creation, listing, editing, and deletion
- doctor schedule filtering, validation, and pagination

The following remain intentionally out of scope for today:

- patient booking module work

## Git

- Recommended commit:
  - `feat(week4-day1): implement doctor dashboard and profile management`
