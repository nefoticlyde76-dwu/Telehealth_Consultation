# MBPHA TeleHealth Consultation System

Production-quality MBPHA TeleHealth Consultation System for the Milne Bay Provincial Health Authority (MBPHA), developed as a final year Bachelor of Information Systems capstone project.

## Current Status

- Current Week: Week 8
- Architecture: Custom MVC (PHP 8.x)
- Database: MySQL with PDO prepared statements
- Frontend: HTML5, CSS3, Bootstrap 5, Bootstrap Icons, Vanilla JavaScript
- Authentication: Implemented for patient registration, login, logout, session handling, CSRF, and role-based redirects
- Public Website: Implemented, including dedicated About, How It Works, and Contact pages
- Dashboards: Initial patient, doctor, and administrator dashboards implemented
- Administration: Week 3 Day 1 user management foundation implemented for administrators
- Doctor Accounts: Week 3 Day 2 doctor account management implemented for administrator-controlled clinician onboarding
- Patient Management: Week 3 Day 3 patient management implemented for administrator oversight and account maintenance
- Administrator Profile: Week 3 Day 3 profile editing and password management implemented for administrators
- Week 3 Finalization: Administrator Management module reviewed and hardened for validation, security, accessibility, responsive behavior, and UI consistency
- Doctor Dashboard: Week 4 Day 1 professional doctor dashboard and profile management implemented for clinicians
- Doctor Availability: Week 4 Day 2 availability scheduling implemented for doctor-managed consultation slots
- Patient Browsing: Week 4 patient-facing doctor directory and consultation slot viewer implemented for patient review workflows
- Dashboard UI: Administrator, doctor, and patient dashboards refined into a unified premium telemedicine workspace experience
- Profile Pictures: Direct profile picture uploads with live avatar updates are implemented for administrator, doctor, and patient profiles
- Consultation Workflow: Week 5 consultation request and appointment management workflow implemented across patient, administrator, and doctor dashboards
- Video Consultation Module: Week 6 end-to-end Daily.co Prebuilt video consultation integration (secure server-side creds, idempotent rooms, join-window, role tokens) implemented for approved appointments
- Consultation Records and Prescriptions: Week 7 live clinical documentation, doctor-controlled completion, explicit prescription issuance, and read-only historical record views implemented
- Consultation History and PDF Export: Week 8 authorized consultation-record and A4 portrait prescription PDF downloads, refined history actions, and supporting public/dashboard UI consistency implemented
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
- Permanent administrator-managed deletion of patient and doctor user accounts
- Deletion safeguards to block self-deletion and prevent removal of the final administrator account
- Audit logging for permanent user account deletions
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

### Week 4 Finalisation Features

- Patient doctor directory at `/patient/doctors`
- Patient consultation slot viewer at `/patient/available-slots`
- Patient doctor directory displays:
  - doctor profile photo
  - full name
  - professional title
  - specialization
  - available consultation days
  - available consultation times
- Patient consultation slot viewer displays only future slots with status `Available`
- Patient slot viewer filters:
  - doctor
  - specialization
  - consultation date
- Patient dashboard updated with Week 4 browsing statistics and quick actions
- Week 4 role access, database integrity, and patient-visible availability rules verified
- Shared dashboard shell upgraded with a more premium healthcare SaaS layout inspired by modern telehealth UX patterns
- Administrator, doctor, and patient dashboards now use improved information hierarchy, spotlight panels, refined stat cards, and richer role-specific widgets
- Sidebar, topbar, quick actions, activity panels, and dashboard cards were visually unified to feel like one platform
- Dashboard layouts were further redesigned with telemedicine-inspired hierarchy including weekly schedule widgets, consultation overview panels, user distribution blocks, notifications areas, and cleaner role-specific information grouping

### Week 5 Day 1 Features

- Doctor directory continues to display only doctors with at least one future slot marked `Available`
- Patients can book a consultation by selecting a specific available slot from:
  - Doctor directory booking buttons
  - Available slot viewer booking actions
- Booking form displays:
  - doctor information
  - selected consultation slot date and time
  - a single required chief complaint field (no extra forms, uploads, or attachments)
- Consultation request processing:
  - consultation request saved in `consultation_requests`
  - links patient, doctor, and selected `doctor_availability` slot
  - default status set to `Pending`
  - selected availability slot is marked as `Booked` after successful submission
- Patient consultation history:
  - list all submitted consultation requests
  - view consultation details and status badge
- Patient dashboard updated to display:
  - pending consultation requests
  - approved consultations
  - upcoming appointments
  - consultation history count

### Week 5 Day 2 Features

- Administrator consultation request management at `/admin/consultation-requests`
  - view all consultation requests
  - search by patient/doctor name, request id, or chief complaint text
  - filter by status, patient, doctor, and consultation date
- Administrator consultation request detail view at `/admin/consultation-requests/{id}`
  - view patient, doctor, consultation slot, chief complaint, status, and submission timestamp
- Appointment approval workflow:
  - approve pending requests (status changes to `Approved`, slot remains reserved)
  - reject pending requests (status changes to `Rejected`, slot is returned to `Available`)
  - invalid transitions are blocked (only `Pending` requests can be approved or rejected)
- Administrator dashboard updates:
  - pending, approved, rejected consultation request counters
  - recent consultation requests feed
- Doctor dashboard updates:
  - new approved appointments counter
  - upcoming approved consultations list

### Week 5 Workflow Completion

- Consultation status lifecycle standardized across the active Week 5 workflow:
  - `Pending`
  - `Approved`
  - `Rejected`
  - `Cancelled`
  - `Completed`
- Administrator workflow refined to support:
  - pending request review
  - approval and rejection decisions
  - cancellation of eligible requests
  - recent consultation activity visibility on the dashboard
- Doctor workflow refined to support:
  - consultation workspace at `/doctor/consultations`
  - upcoming, approved, and completed consultation views
  - same-day completion of approved consultations
- Patient workflow refined to support:
  - consultation history with assigned doctor, date, time, chief complaint, and current status
  - latest consultation status visibility on the patient dashboard
  - upcoming consultation and consultation history summary metrics
- Slot reservation logic verified so that:
  - approved consultations keep the selected slot reserved
  - rejected and cancelled consultations release the slot back to `Available`
  - duplicate active bookings for the same patient and slot are prevented
- Dashboard metrics aligned with the completed Week 5 workflow:
  - Administrator: pending requests, approved appointments, rejected requests, recent activity
  - Doctor: today's schedule, approved appointments, upcoming consultations
  - Patient: pending requests, upcoming consultation, consultation history, latest consultation status

### Week 5 Dashboard Redesign (Premium Telehealth Interface)

- Dashboards upgraded to a WellEase-inspired three-column layout while keeping official MBPHA TeleHealth branding, palette, and existing workflows intact
- Redesigned fixed sidebar navigation with active link highlighting, refined hover transitions, and an offcanvas sidebar for smaller screens
- Redesigned header/topbar with:
  - global search UI (visual control)
  - notifications placeholder
  - user profile dropdown with role badge
  - live date/time label
  - role-aware quick action shortcut
- Added a role-aware right sidebar for quick visibility (desktop) plus an offcanvas overview panel (tablet/mobile):
  - mini calendar
  - upcoming activity feed
  - quick stats counters
  - quick actions shortcuts
- Replaced dashboard chart placeholders with real analytics powered by Chart.js:
  - Administrator: weekly consultation requests trend, status distribution
  - Doctor: availability coverage summary, weekly consultation requests trend, status distribution
  - Patient: status distribution, monthly request volume trend
- Added dashboard interaction layer via `public/js/dashboard.js`:
  - Chart.js initialization using server-provided chart configs
  - AOS scroll-reveal animations
  - chart skeleton loaders (loading shimmer until rendered)
  - mini calendar rendering
  - date/time auto-refresh

### Week 6 Video Consultation Module

Daily.co Prebuilt WebRTC integration wired to the approved consultation request lifecycle so patients and doctors can join the same real-time telehealth room from their dashboards.

#### Architecture

- Presentation shell: shared room view used for both patient and doctor:
  - Patient: `/patient/consultations/{id}/room`
  - Doctor:  `/doctor/consultations/{id}/room`
- JSON credential endpoints protected by role middleware and CSRF:
  - Patient: `POST /patient/consultations/{id}/join-token`
  - Doctor:  `POST /doctor/consultations/{id}/join-token`
  - These endpoints return `{ ok, room_url, token }` — no secrets are embedded in HTML
- Media provider: Daily.co Prebuilt iframe (`@daily-co/daily-js@0.67.0` via jsdelivr CDN, pinned for PNG health-sector network reachability)

#### Server-side services (server-side-only Daily keys)

- [DailyService.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Services/DailyService.php) — REST client for the hosted Daily.co backend
  - `createRoom($roomName, $startAt, $endAt)` — creates a `privacy: private` room with `enable_prejoin_ui`, `enable_screenshare`, `enable_people_ui`, room-level `exp` / `nbf` bounds matching the appointment window
  - `createMeetingToken($roomName, $userId, $displayName, $role, $ttlSecs = 1800)` — short-lived JWT bound to the room name; doctor is minted as `is_owner:true`, patient as `is_owner:false`; tokens are never persisted to the database
  - `deleteRoom($roomName)` — cleanup helper
  - Credentials are read exclusively from `.env` (`DAILY_API_KEY`, `DAILY_DOMAIN`) via `Environment::get()`. They never leave the PHP tier
- [ConsultationRoom.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Models/ConsultationRoom.php) — idempotent 1:1 room ↔ consultation_request mapping
  - `upsertForApprovedConsultation($requestId, $patientId, $doctorId)` — SELECT-first, then INSERT only if missing
  - DB-level UNIQUE key on `consultation_request_id` acts as a race-condition safety net so repeated Join clicks never create duplicate rooms for the same appointment
  - Returns the exact same stored `daily_room_name` + `daily_room_url` on every call
- [VideoConsultationService.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Services/VideoConsultationService.php) — single policy authority for joining
  - Verifies the caller's role matches the stored patient/doctor on the consultation request
  - Status guard: only `Approved` (or `Completed` for historical review) consultations are allowed
  - Join-window enforcement using the application timezone `Pacific/Port_Moresby` — earliest join is `appointment_start − 10 min`, latest join is `appointment_end + 10 min`
  - Lazy room creation: if the consultation is Approved but the `consultation_rooms` row does not yet exist, it is created on the first join via `upsertForApprovedConsultation`
  - Outputs the uniform shape `{ ok: true, room_url, token }` used identically by the patient and doctor controllers

#### Patient & Doctor presentation layer

- Shared room view: [patient/consultations/room.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/patient/consultations/room.php) (doctor view simply requires the same file)
  - Appointment summary strip (doctor / patient / date / time / status pill)
  - Pre-call guidance (media permission tips, HTTPS / secure-context warning, troubleshooting)
  - Primary CTA "Join Consultation"
  - `#vc-alert-region` with `aria-live="polite"` for Bootstrap-flavored permission / network / room errors
  - `#vc-daily-frame-wrapper` container used for Daily Prebuilt
- Custom stylesheet: [consultation-room.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/consultation-room.css) — dark navy frame container, belt-and-suspenders guarantee that the Daily iframe fills its wrapper (CSS `width:100%!important; height:100%!important` with `min-height: clamp(540px, 78vh, calc(100vh − 130px))`). Uses the TeleHealth design tokens `--ux-primary:#26658C`, `--ux-secondary:#2A9D8F`, `--ux-accent:#3CB371`
- Daily.js CDN bootstrap pinned to jsdelivr for network reliability:
  - `<script crossorigin="anonymous" src="https://cdn.jsdelivr.net/npm/@daily-co/daily-js@0.67.0/dist/daily-iframe.min.js" onerror=...>`
  - Custom `waitForDailyFactory(6000)` 80 ms poller resolves the factory through `window.DailyIframe` / `window.Daily` / `window.DailyJs` and early-aborts on `window.__dailyJsLoadFailed`
- Runtime logic: [consultation-room.js](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/js/consultation-room.js)
  - Triple duplicate-prevention guard: `isBootstrapping`, `callFrame !== null`, and `hasJoined` at the top of `bootstrapRoom()` prevent duplicate iframes from double-clicks or network re-delivery
  - Canonical two-step createFrame → join: `DailyIframe.createFrame(wrapper, { iframeStyle:{width:100%,height:100%,minHeight:540px}, showLeaveButton: true, showFullscreenButton: true, showParticipantsBar: true })` → wire events BEFORE joining → explicit `await callFrame.join({ url, token })`
  - Event wiring (before join): `loaded`, `joining-meeting`, `joined-meeting`, `left-meeting`, `participant-joined`, `participant-left`, `error`, `camera-error`, `microphone-error`, `network-connection`, `recording-stopped`, `nonfatal-error`, `available-devices-updated`
  - Actionable permission error pattern matching — camera/mic blocked prompts user to site settings, 404 → room not yet available, "Missing payment method" → Daily dashboard billing provisioning (documented externally, code unchanged)
  - Leave/destroy lifecycle when the user navigates away via window `beforeunload`

#### Dashboard integration & status helper

- A new reusable status badge helper [status_helper.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Views/partials/shared/status_helper.php) renders standardized pills for Pending/Approved/Rejected/Cancelled/Completed/Active/Expired. Used on dashboards, consultation listings, and the consultation-room summary strip
- Dashboard cards, "Upcoming Appointments" panels, and consultation request detail pages now surface a teal "Join Consultation" CTA only when the request is `Approved` AND the current Pacific/Port_Moresby time is inside the `start − 10 min … end + 10 min` join window
- Admin dashboard + booking detail pages reflect the consultation-room status (Active / Room Created) once a room has been created

#### Administration approval transaction

- `ConsultationRequest::updateStatusForAdmin()` wraps the approval chain in a PDO transaction:
  1. transition status to `Approved`
  2. keep the doctor_availability slot locked as `Booked`
  3. create the consultation_rooms row via `upsertForApprovedConsultation` (this in turn makes the signed Daily REST create-room call)
  - Any failure in step 3 rolls back steps 1 and 2 so no orphaned approved-but-unroomed requests can exist

### Week 7 Consultation Records and Prescriptions

Clinical documentation and prescriptions are layered onto the existing Approved Daily consultation. The administrator still approves or rejects bookings manually. Completing a consultation does not create a prescription automatically.

#### Live clinical documentation (Draft)

- Assigned doctor documents beside the Daily room at `/doctor/consultations/{id}/room`
- Opening an Approved consultation creates a Draft record if none exists; existing notes are not overwritten
- Autosave / Save persist Draft fields only (`POST /doctor/consultations/{id}/clinical-record`)
- Saving a draft does not complete the consultation and does not issue a prescription
- Required fields before completion: chief complaint, history/symptoms, clinical findings, diagnosis, treatment plan

#### Completion (doctor only)

- `POST /doctor/consultations/{id}/complete` requires CSRF and an explicit confirmation checkbox
- Sets `consultation_requests.status = Completed`, stores `completed_at`, and marks the clinical record `Final` with `finalized_at`
- A Final record cannot be edited
- Patients have no complete or clinical-record write routes

#### Prescription (explicit, after completion)

- `GET/POST /doctor/consultations/{id}/prescription`
- Allowed only when the consultation is Completed, the record is Final, and the doctor signature is on file
- Medication lines: name, dosage, frequency, duration, quantity
- Empty or incomplete prescriptions are rejected; a second prescription for the same consultation is blocked
- Patient name, address, doctor name, and signature are loaded from existing profile rows

#### Historical record views

- Doctor historical record: `/doctor/consultations/{id}` (not the Daily room)
- Patient completed details: `/patient/consultation-requests/{id}`
- Shared read-only partial `_consultation_record_details.php` (no textareas)
- History lists: Approved → Join Consultation; Completed → View Record
- Completed Daily room visits redirect to the record page

#### Administrator review workspace

- `/admin/consultation-requests` queue: review pending request details, then Approve or Reject
- No automated approval, rejection, or AI review

### Week 8 Consultation History and PDF Export

Authorized, read-only PDF downloads sit on top of the Week 7 finalized record and issued prescription. Generating a PDF does not create or change clinical notes or prescriptions.

#### History actions

- Patient history: `/patient/consultation-requests`
- Doctor history: `/doctor/consultations`
- Approved sessions inside the join window keep **Join Consultation**
- Completed consultations use **View Record** (not the Daily room)
- **Download Consultation Record** appears only for Completed consultations with a Final record
- **View Prescription** / **Download Prescription** appear only when a prescription has been issued

#### Consultation-record PDF

- Patient: `GET /patient/consultation-requests/{id}/download-record`
- Doctor: `GET /doctor/consultations/{id}/download-record`
- Built by `ConsultationRecordPdfService` from saved `consultation_records` data
- Includes patient, consultation, and doctor information plus finalized clinical fields
- A4 portrait, DomPDF, DejaVu Sans; remote URL fetching disabled

#### Prescription PDF

- Patient: `GET /patient/consultation-requests/{id}/download-prescription`
- Doctor: `GET /doctor/consultations/{id}/download-prescription`
- Built by `PrescriptionPdfService` from saved `prescriptions` rows
- Includes patient name and address, issue date, medication lines, doctor information, and the stored doctor signature
- Paper size is A4 portrait (`setPaper('A4', 'portrait')` and `@page { size: A4 portrait; }`)
- Signature images are taken only from `uploads/doctors/{doctorId}/...`

#### Authorization

- Role middleware on every download route; controllers re-check the signed-in role
- Patients download only their own documents; doctors download only assigned consultations
- Administrators have no PDF download routes
- Draft records and missing prescriptions cannot be downloaded
- Cross-user ID guessing fails closed

#### Supporting public pages

- Dedicated `/about`, `/how-it-works`, and `/contact` pages
- Shared public intro, hero, workflow, and CTA partials
- Login and registration stylesheets split for the same MBPHA visual language

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
│   │   ├── ConsultationRoom.php
│   │   ├── ConsultationRecord.php
│   │   └── Prescription.php
│   ├── Services/
│   │   ├── DailyService.php
│   │   ├── VideoConsultationService.php
│   │   ├── DoctorClinicalDocumentationService.php
│   │   ├── DoctorPrescriptionService.php
│   │   ├── PatientClinicalRecordService.php
│   │   ├── ConsultationRecordPdfService.php
│   │   └── PrescriptionPdfService.php
│   └── Views/
│       ├── admin/
│       │   └── consultation_requests/
│       ├── auth/
│       ├── documents/
│       │   ├── consultation_record_pdf.php
│       │   └── prescription_pdf.php
│       ├── doctor/
│       │   └── consultations/
│       │       ├── room.php
│       │       ├── show.php
│       │       ├── prescription.php
│       │       └── _history_table.php
│       ├── home/
│       │   ├── about.php
│       │   ├── contact.php
│       │   └── how_it_works.php
│       ├── layouts/
│       ├── partials/
│       │   └── shared/
│       └── patient/
│           ├── consultations/room.php
│           └── consultation_requests/_history_table.php
├── database/
│   └── migrations/
│       ├── 001_initial_schema.sql
│       ├── 003_add_doctor_account_management_fields.sql
│       ├── 004_add_doctor_profile_assets.sql
│       ├── 005_add_profile_photo_fields_for_admin_and_patient.sql
│       ├── 006_add_notes_to_doctor_availability.sql
│       ├── 007_update_consultation_requests_for_booking.sql
│       ├── 008_remove_unique_index_from_consultation_requests.sql
│       ├── 009_create_audit_logs_table.sql
│       ├── 010_create_consultation_rooms_table.sql
│       ├── 011_add_phone_to_patient_table.sql
│       ├── 015_alter_consultation_records_live_draft.sql
│       ├── 016_consultation_completion_and_prescription_quantity.sql
│       └── 017_drop_consultation_ai_reviews_table.sql
├── public/
│   ├── css/
│   │   ├── consultation-room.css
│   │   ├── consultation-record.css
│   │   ├── prescription.css
│   │   ├── design-system.css
│   │   ├── dashboard-ui.css
│   │   ├── home.css
│   │   ├── auth-login.css
│   │   └── auth-register.css
│   ├── js/
│   │   ├── consultation-room.js
│   │   ├── consultation-clinical-record.js
│   │   └── consultation-prescription.js
│   └── index.php
├── routes/
├── bin/
├── tmp/
├── .env.example
├── .gitignore
├── README.md
├── WEEK1_REPORT.md
├── WEEK2_REPORT.md
├── WEEK3_REPORT.md
├── WEEK4_REPORT.md
├── WEEK5_REPORT.md
├── WEEK6_REPORT.md
├── WEEK7_REPORT.md
└── WEEK8_REPORT.md
```

## Installation

1. Clone the repository into your XAMPP `htdocs` directory.
2. Install Composer dependencies (includes DomPDF for Week 8 PDF export):

```bash
composer install
```

   Enable PHP GD in the XAMPP `php.ini` (`extension=gd`) so consultation-record and prescription PDFs can embed the MBPHA logo and doctor signature. Restart Apache after enabling it.

3. Create a local environment file:

```bash
copy .env.example .env
```

4. Update `.env` with your local database credentials and application URL.
   - For typical XAMPP local setups, use `DB_HOST=localhost`.
5. **Configure Daily.co for the Week 6 video module (optional for Weeks 1-5):**
   - Create an account at `https://dashboard.daily.co/` and create a subdomain
   - Paste the API key + domain into `.env`:
     - `DAILY_API_KEY=` (your Daily.co API key, starts with `a7f59…` or similar)
     - `DAILY_DOMAIN=` (your subdomain, e.g. `mbphatelehealth.daily.co` — protocol and trailing slashes are stripped by `DailyService`)
   - Note: Daily free tier may require a billing method on file at `dashboard.daily.co → Billing` before real camera/mic SFU sessions will connect. REST API (rooms, tokens) works immediately without a payment method.
6. Ensure Apache and MySQL are running in XAMPP.
7. Import the migration files:

```bash
mysql -u root -p < database/migrations/001_initial_schema.sql
mysql -u root -p < database/migrations/003_add_doctor_account_management_fields.sql
mysql -u root -p < database/migrations/004_add_doctor_profile_assets.sql
mysql -u root -p < database/migrations/005_add_profile_photo_fields_for_admin_and_patient.sql
mysql -u root -p < database/migrations/006_add_notes_to_doctor_availability.sql
mysql -u root -p < database/migrations/007_update_consultation_requests_for_booking.sql
mysql -u root -p < database/migrations/008_remove_unique_index_from_consultation_requests.sql
mysql -u root -p < database/migrations/009_create_audit_logs_table.sql
mysql -u root -p < database/migrations/010_create_consultation_rooms_table.sql
mysql -u root -p < database/migrations/011_add_phone_to_patient_table.sql
mysql -u root -p < database/migrations/015_alter_consultation_records_live_draft.sql
mysql -u root -p < database/migrations/016_consultation_completion_and_prescription_quantity.sql
mysql -u root -p < database/migrations/017_drop_consultation_ai_reviews_table.sql
```

8. Open the application using the configured `APP_URL`.

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
- Administrator-only user management supports permanent deletion of patient and doctor accounts with confirmation, safeguards, and audit logging.
- Administrator-only doctor account management is currently available at `/admin/doctors` after successful administrator login.
- Administrator-only patient management is currently available at `/admin/patients` after successful administrator login.
- Administrator profile management is currently available at `/admin/profile` after successful administrator login.
- Week 6 video consultation shell and endpoints are available to authenticated roles only:
  - Patient: `/patient/consultations/{id}/room`
  - Doctor:  `/doctor/consultations/{id}/room`
  - Join-token JSON endpoints return `{ ok, room_url, token }` and are role-protected
- Week 7 clinical documentation and prescription endpoints are doctor-only:
  - `POST /doctor/consultations/{id}/clinical-record`
  - `POST /doctor/consultations/{id}/complete`
  - `GET/POST /doctor/consultations/{id}/prescription`
  - Historical doctor record: `/doctor/consultations/{id}`
  - Patients view completed records at `/patient/consultation-requests/{id}` (read-only)
- Week 8 PDF download endpoints are role-protected and ownership-checked:
  - Patient record: `/patient/consultation-requests/{id}/download-record`
  - Patient prescription: `/patient/consultation-requests/{id}/download-prescription`
  - Doctor record: `/doctor/consultations/{id}/download-record`
  - Doctor prescription: `/doctor/consultations/{id}/download-prescription`
  - Administrators have no PDF download routes

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
- **Daily.co credentials are server-side only** — `DAILY_API_KEY` and `DAILY_DOMAIN` are read via `Environment::get()` inside [DailyService.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Services/DailyService.php) only. They are never serialized to HTML, JS, JSON endpoints, views, or logs
- **Consultation-room idempotency**: the `consultation_rooms` UNIQUE key on `consultation_request_id` + `upsertForApprovedConsultation()` SELECT-first policy guarantee exactly one Daily room per approved request, even if the user clicks "Join Consultation" dozens of times or refreshes the page
- **Join-window enforcement**: video endpoints reject access outside `appointment_start − 10 min` through `appointment_end + 10 min` (timezone pinned to `Pacific/Port_Moresby`), preventing premature or late room access
- **Role-aware tokens**: meeting tokens are minted fresh per join, never stored, and encode `is_owner:true` only for the doctor assigned to the appointment
- **Authorization before media**: the patient/doctor role on the signed-in user must match the stored patient/doctor on the consultation request before a join response is issued
- **Admin-approval transactional consistency**: approval + slot lock + room creation run in one PDO transaction so partially-approved requests cannot exist if Daily.co REST or the DB write fails
- **Clinical documentation authorization**: only the assigned doctor can save a Draft, complete a consultation, or issue a prescription; patients have no write routes for records or prescriptions
- **Completion vs prescription separation**: marking a consultation Completed finalizes the clinical record only; a prescription is created only by an explicit doctor save after completion
- **Historical record vs Daily room**: completed consultations open a read-only record page; the Daily room remains available only for Approved sessions inside the join window
- **PDF download authorization**: download routes require the matching patient or assigned doctor; Draft records and missing prescriptions cannot be downloaded; cross-user ID guessing fails closed
- **Read-only PDF generation**: DomPDF builds files from saved rows only — no INSERT or UPDATE — and remote URL fetching is disabled
- **Signature path confinement**: prescription PDF signatures are loaded only from `uploads/doctors/{consultation doctor id}/`; request-supplied paths are ignored

## Design System

- The application now uses the official MBPHA TeleHealth Design System across public pages, forms, navigation, footer, and dashboards
- Core palette is centralized in [theme.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/theme.css)
- Shared UI refinements are applied through [style.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/style.css)
- Colours are managed through CSS variables instead of page-level hardcoded values
- Week 6 consultation-room visual language is defined in [consultation-room.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/consultation-room.css) and shares the tokens `--ux-primary:#26658C`, `--ux-secondary:#2A9D8F`, `--ux-accent:#3CB371`
- Week 7 consultation-record and prescription screens use [consultation-record.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/consultation-record.css) and [prescription.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/prescription.css) with the same MBPHA palette
- Shared dashboard workspace tokens for the administrator review queue live in [design-system.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/design-system.css)
- Week 8 public, auth, and dashboard consistency styles live in [home.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/home.css), [auth-login.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/auth-login.css), [auth-register.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/auth-register.css), and [dashboard-ui.css](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/css/dashboard-ui.css)
- Week 8 PDF templates use inline A4 portrait styles and the same MBPHA navy (`#26658C`) document header language

## Testing Summary

- PHP syntax validation across all project PHP files
- VS Code diagnostics checks on changed PHP files
- Browser smoke test of the landing page, login page, registration page, dashboard shell, and contact form flow
- Verified contact form validation and successful inquiry logging
- Verified graceful user-facing handling when database connectivity is unavailable
- Verified administrator login and redirect after correcting the default admin seed password
- Verified administrator doctor-management flows in code for create, edit, activate, deactivate, and password reset handling with CSRF validation and prepared statements
- Verified administrator patient-management flows in code for search, edit, activate, and deactivate handling with CSRF validation and prepared statements
- Verified administrator user-management deletion flow in code for patient and doctor records with CSRF validation, transaction rollback support, and audit logging
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
- Verified unauthenticated access to `/patient/doctors` and `/patient/available-slots` redirects away from protected pages
- Verified authenticated patient access renders the doctor directory and consultation slot viewer successfully
- Verified patient doctor directory shows only active doctors with future available slots
- Verified patient slot viewer excludes booked slots and past slots from the visible patient listing
- Verified Week 4 doctor availability relationships have no orphaned `doctor_availability` or `doctor` records
- Verified the full Week 5 workflow end to end with live HTTP requests:
  - patient registration and booking submission
  - administrator approval and rejection handling
  - doctor password reset, login, and consultation completion
  - patient history and dashboard status updates after workflow changes
- Verified the completed consultation lifecycle persists expected final states:
  - approved same-day request transitions to `Completed`
  - rejected request remains `Rejected`
  - approved slot remains `Booked`
  - rejected slot returns to `Available`
- Verified the reusable Week 5 PowerShell test script uses dynamic slots and authenticated page assertions for repeatable local validation
- Verified the Week 6 video module end to end:
  - PHP `php -l` lint passes on all 25 touched PHP files (controllers/services/models/views/routes/index)
  - `DailyService::createRoom()` returns a `FILTER_VALIDATE_URL` Daily room URL; tokens mint successfully for both doctor (owner) and patient (participant) with 30-minute TTL
  - `ConsultationRoom::upsertForApprovedConsultation()` returns the same stored `daily_room_url` on a second invocation (idempotency — `action: reused`)
  - `VideoConsultationService::authorizeAndIssueJoinToken()` rejects: wrong user/role, not-yet-Approved status, and access outside the `−10 min … +10 min` join window
  - Patient `POST /patient/consultations/{id}/join-token` and Doctor `POST /doctor/consultations/{id}/join-token` resolve the **identical** stored `daily_room_url` for the same consultation_request_id (same-room guarantee — byte-match on the URL string)
  - Presentation shell renders correctly with appointment summary, pre-call guidance, aria-live alert region, and properly-sized frame container
  - `consultation-room.js` triple guard (`isBootstrapping`, `callFrame !== null`, `hasJoined`) prevents duplicate iframe creation even when the CTA is double-clicked or bootstrapped twice
  - Daily factory resolution with `waitForDailyFactory(6000)` + jsdelivr CDN (`daily-js@0.67.0`) resolves `DailyIframe.createFrame` correctly; canonical two-step `createFrame()` → wire events → explicit `callFrame.join({ url, token })` initiates the session; `iframeStyle:{width:100%,height:100%,minHeight:540px}` combines with CSS clamp to guarantee consistent frame sizing
  - External note — real camera/mic SFU sessions require a Daily.co account with a billing method on file at `dashboard.daily.co → Billing`; without it, the native Daily page shows "Missing payment method". REST API (room + token creation) and the iframe join handshake complete without this; live media is the only gated step.
- `010_create_consultation_rooms_table.sql` and `011_add_phone_to_patient_table.sql` applied to `telehealth_db` with all foreign keys, UNIQUE keys, and indices intact; no orphaned `consultation_rooms` rows; `information_schema.STATISTICS` confirms the UNIQUE key on `consultation_request_id`
- Verified the Week 7 consultation-record and prescription module:
  - `php bin/test_week7_day3.php` — 36 passed (schema, required fields, completion, no auto-prescription, Final lock, access control)
  - `php bin/test_week7_day4.php` — 33 passed (no patient write routes, draft autosave, cross-user ID guessing fails closed, Daily join-token still registered)
  - `php bin/test_week7_record_view.php` — 64 passed after Week 8 download-route checks (dedicated record route vs Daily room, View Record vs Join Consultation, completed room redirects, read-only record partial)
  - Patient booking still saves as `Pending` for administrator review; administrator Approve/Reject remain manual
  - Daily room routes and `consultation_rooms` data remain intact after clinical documentation work
- Verified the Week 8 consultation-history and PDF export module:
  - `php bin/test_week8_day2.php` — 40 passed (consultation-record PDF generation, Final-only export, A4 portrait)
  - `php bin/test_week8_day3.php` — 58 passed (A4 portrait prescription PDF, medication lines, signature path confinement, no write on generate)
  - `php bin/test_week8_day4.php` — 98 passed (authorization, draft/final gates, cross-user PDF rejection, history download labels)
  - Combined Week 7 + Week 8 QA pass: 329 passed, 0 failed
  - PDF generation does not insert or update `consultation_records` or `prescriptions`

## Known Environment Requirements

- Full login and registration submission require an active MySQL service matching the local `.env` configuration.
- For common XAMPP local environments, `DB_HOST=localhost` is the recommended database host value.
- Weekly video consultations need `OpenSSL` enabled in PHP for outbound HTTPS calls from `DailyService` to Daily.co REST endpoints. Ensure `extension=openssl` is uncommented in `php.ini` and `cacert.pem` is configured on restricted networks.
- Daily Prebuilt requires the page to load over a **secure context** (HTTPS or `localhost`) to request camera and microphone permissions. If you access the app via a LAN IP or custom host that is not `localhost`, set `APP_URL=https://…` and terminate TLS locally.
- Daily.js CDN is pinned to `https://cdn.jsdelivr.net/npm/@daily-co/daily-js@0.67.0/dist/daily-iframe.min.js`. If your network filters this CDN, a browser console `net::ERR_FAILED` appears and `waitForDailyFactory()` will surface a Bootstrap alert with a troubleshooting hint.
- Week 8 PDF logo and signature embedding require PHP GD (`extension=gd` in `php.ini`). Restart Apache after enabling it. Without GD, downloads still succeed but PNG images may be omitted.

## Roadmap

- Week 1: Foundation completed
- Week 2: Public website and authentication module completed in code
- Week 3: Administrator management module completed
- Week 4: Doctor availability management and patient browsing completed
- Week 5: Admin booking management completed
- Week 6: Video consultation (Daily.co Prebuilt integration, idempotent rooms, join-window enforcement, role tokens, consultation-room UI, admin approval transaction consistency) — completed
- Week 7: Consultation records and prescription module — completed
- Week 8: Consultation history and PDF export — completed
- Week 9: Testing, security review, bug fixing, and UI refinement
- Week 10: Deployment, documentation, and final testing

## Repository Notes

- Follow PSR-12 and SOLID principles
- Keep controllers thin and business logic in services/models
- Do not introduce features outside the approved week plan
- Preserve the established MVC structure, schema, and design language
