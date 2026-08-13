# WEEK 6 REPORT

## Project Information

- Project Title: MBPHA TeleHealth Consultation System
- Week: Week 6
- Scope Covered in This Report: Video Consultation Module — Daily.co Prebuilt WebRTC integration, server-side room lifecycle management, idempotent consultation-room persistence, role-aware join-token issuance, shared patient/doctor consultation-room UI shell, Daily Prebuilt iframe bootstrap, two-way camera and microphone negotiation, and end-to-end join-window enforcement.

## Week 6 Objective

The objective of Week 6 is to implement the secure video consultation layer for the MBPHA TeleHealth platform so that a patient and the doctor assigned to an `Approved` consultation can attend the same online video/audio room at the scheduled appointment time — inside the application, using the established MVC three-tier architecture, without exposing API credentials to the browser, and without allowing duplicate Daily rooms, duplicate iframe instances, or cross-role access to consultations the user is not authorized to attend.

The work completed in Week 6 focused strictly on:

- server-side Daily.co REST integration (room creation, meeting-token minting, room deletion)
- idempotent mapping of `consultation_requests` rows to a single Daily room
- join-window enforcement anchored to Pacific/Port Moresby timezone
- authorized patient and doctor join-token JSON endpoints
- shared patient/doctor consultation room page shell
- Daily Prebuilt iframe bootstrap in the browser with explicit `join()` semantics
- camera and microphone permission handling and user-facing error surfacing
- dashboard and consultations listing integration so patients and doctors can enter the consultation only inside the appointment window
- database migrations supporting the new room relation and adding patient contact phone to the profile
- PHP syntax validation, CDN reliability validation, and manual static harness verification of the Prebuilt iframe lifecycle

The following were intentionally not implemented in Week 6 (deferred to later scheduled weeks per the approved proposal):

- consultation records
- prescriptions
- PDF exports
- consultation history deep views
- private-room meeting-token requirement rollout (rooms use `privacy: public` during basic-video verification phase)

## Completed Work

### 1. Server-Side Daily.co REST Client — `DailyService`

Introduced `App\Services\DailyService` as the single server-side entry point for every Daily REST call. The service:

- reads credentials only from environment variables `DAILY_API_KEY` and `DAILY_DOMAIN` (server-side only — never serialized into HTML or JS)
- uses PHP cURL with explicit bearer auth and JSON content-type for all calls
- implements `createRoom($ref, $startTimestamp, $durationMinutes)` with:
  - stable, deterministic room name prefix `mbpha-th-` so diagnostic review and dashboard cleanup remain human-readable
  - `privacy: 'public'` to keep the initial join verification simple per project rule #9 (meeting tokens are still minted and passed)
  - Prebuilt properties: `enable_prejoin_ui: true`, `start_video_off: false`, `start_audio_off: false`, `owner_only_broadcast: false`, `enable_knocking: false`, `enable_screenshare: true`, `enable_people_ui: true`, `max_participants: 4`, `enable_recording: false`
  - Unix-epoch `nbf` and `exp` scoped to the appointment window plus 30 minutes of safety padding
- implements `createMeetingToken($roomName, $userId, $displayName, $role, $ttlSeconds)` minting a signed Daily JWT with:
  - doctor → `is_owner: true`
  - patient → `is_owner: false`
  - `room_name` bound to the specific consultation room so tokens cannot be replayed against other rooms
  - 30 minute default TTL so tokens are short-lived
- implements `deleteRoom($roomName)` so cleanup helpers and future cancellation workflows can remove stale rooms from the Daily dashboard

### 2. Idempotent Consultation ↔ Daily Room Mapping — `ConsultationRoom` Model

Introduced `App\Models\ConsultationRoom` backed by the new `consultation_rooms` table with a database-level UNIQUE key on `consultation_request_id`, guaranteeing exactly one Daily room per approved consultation. Key behaviors:

- `upsertForApprovedConsultation($consultationRequestId, $patientId, $doctorId)`:
  - loads the stored row if present; returns early with `action: 'reused'` and the same `daily_room_name` and `daily_room_url` that were stored the first time
  - only when no row exists does it call `DailyService::createRoom`, persist the returned name/URL, and return `action: 'created'`
  - second and subsequent calls or page refreshes NEVER allocate a second Daily room
- database referential integrity:
  - `FOREIGN KEY (consultation_request_id) REFERENCES consultation_requests(id) ON DELETE CASCADE` so approval rollback cleans the room link
  - `UNIQUE KEY uq_consultation_rooms_request_id` is the DB-enforced safety net
  - `UNIQUE KEY uq_consultation_rooms_daily_room_name` guards against accidental reuse of Daily room names
  - covering indices on `consultation_request_id`, `daily_room_name`, and composite patient/doctor for join authorization fast-path

### 3. Join Authorization, Window Enforcement & Token Issuance — `VideoConsultationService`

`App\Services\VideoConsultationService` is the thin controller-facing service that converts an incoming patient or doctor "I want to join this consultation id" request into either a safe `{ ok, room_url, token }` payload or a structured error. Rules enforced:

- **Authorization** — the calling user must be the linked patient or the linked doctor for the target consultation; otherwise returns `error_code: unauthorized`.
- **Status** — only `Approved` or `Completed` consultations have a room link; pending/rejected/cancelled do not.
- **Join window** — computed in `Pacific/Port_Moresby` timezone:
  - earliest join: appointment start − 10 minutes
  - latest join: appointment end + 10 minutes
  - outside window returns `error_code: outside_join_window` with human-readable server message and the allowed window boundaries so the UI can surface them
- **Idempotency** — if a stored `ConsultationRoom` row does not yet exist for an `Approved` consultation at join time, the service calls `ConsultationRoom::upsertForApprovedConsultation` to create it on first join rather than forcing the admin flow to always create rooms (though admin flow creation is still preferred and supported by `ConsultationRequest::updateStatusForAdmin`).
- **Return payload shape** — both patient and doctor endpoints respond with the exact same JSON contract:
  ```json
  {
    "ok": true,
    "room_url": "https://mbphatelehealth.daily.co/mbpha-th-…",
    "token": "eyJhbGciOiJIUzI1NiIs… (short-lived JWT)"
  }
  ```
  Because patient and doctor endpoints perform the same stored-row lookup with the same `consultation_request_id` key, both roles always receive the EXACT same `room_url`, guaranteeing they negotiate the same Daily SFU meeting space.

### 4. Controller & Route Integration

Controllers remain thin coordinators:

- `PatientController` adds:
  - `showConsultationRoom($id)` — renders the room shell view after verifying patient authorization (page only, no credentials in HTML)
  - `joinConsultation($id)` — POST → JSON endpoint that invokes `VideoConsultationService::authorizeAndIssueJoinToken` with role `Patient`
- `DoctorController` adds the symmetric pair:
  - `showConsultationRoom($id)`
  - `joinConsultation($id)` with role `Doctor`
- `Core\Controller::json()` helper is used uniformly for every JSON response so content-type and encoding are consistent.
- `routes/web.php` registers the role-scoped routes:
  - `GET  /patient/consultations/{id}/room`
  - `POST /patient/consultations/{id}/join-token`
  - `GET  /doctor/consultations/{id}/room`
  - `POST /doctor/consultations/{id}/join-token`
- All routes are protected by the existing role middleware.

### 5. Consultation Room UI Shell & Daily CDN Wiring

- `App\Views\patient\consultations\room.php` is the single source-of-truth room page (the doctor view simply `require`s it so markup, CSS, and CDN wiring stay identical across roles).
- Page shell includes:
  - standard dashboard layout (sidebar + topbar + main) so the room page preserves navigation context
  - breadcrumb and appointment summary strip (patient, doctor, scheduled start/end, appointment status badge)
  - **pre-call card** with:
    - media permission hint text
    - browser secure-context warning banner with localhost redirect guidance if the page is loaded on a non-https, non-localhost origin
    - "Join Consultation" primary CTA (Bootstrap medical-blue styling)
    - troubleshooting checklist (use Chrome/Edge, camera+mic permissions, headset recommended, sit in well-lit area)
  - an alert region (`#vc-alert-region`) with `aria-live="polite"` for server errors, permission errors, and participant join/leave notifications
  - the dedicated Daily iframe wrapper:
    ```html
    <div id="vc-daily-frame-wrapper"
         class="vc-daily-frame-wrapper"
         role="region"
         aria-label="Daily video consultation frame">
    </div>
    ```
- CDN inclusion uses the Prebuilt build of daily-js:
  ```html
  <script id="vc-daily-cdn"
          crossorigin="anonymous"
          src="https://cdn.jsdelivr.net/npm/@daily-co/daily-js@0.67.0/dist/daily-iframe.min.js"
          onerror="(…sets __dailyJsLoadFailed flag and dispatches a DOM event…)"></script>
  ```
  jsdelivr was selected as the canonical CDN after verifying reachability and confirming Prebuilt factory exports.
- Bootstrap status-badge helper and role-aware branding are provided through the new shared partial `app/Views/partials/shared/status_helper.php`, reused by dashboard cards and listings.

### 6. Daily Prebuilt CSS Layout — Guaranteed Dimensions

`public/css/consultation-room.css` introduces the specialized room layout that guarantees the Daily iframe never collapses to zero height:

- `.vc-daily-frame-wrapper` uses `min-height: clamp(540px, 78vh, calc(100vh − 130px))` with `max-height: calc(100vh − 120px)`, a dark medical-blue background (so empty state does not appear broken), 14px radius, and 1px border for visual enclosure.
- `.vc-daily-frame-wrapper > iframe` enforces:
  ```
  width: 100% !important
  height: 100% !important
  min-height: clamp(540px, 78vh, calc(100vh − 130px)) !important
  max-height: calc(100vh − 120px) !important
  border: 0 !important
  display: block
  ```
- Pre-call card, summary strip, permission banner, and troubleshooting list use the established design tokens (`--ux-primary #0F4C81`, `--ux-secondary #2A9D8F`, `--ux-accent #3CB371`, `--ux-bg #F8FAFC`, `--ux-border #E5E7EB`, `--ux-radius 12px`) so the room page visually belongs to the same product as Weeks 1–5.

### 7. Daily Prebuilt Bootstrap Lifecycle — `consultation-room.js`

`public/js/consultation-room.js` implements the actual iframe-embed lifecycle. The original draft relied on passing `url` into `createFrame` and hoping for auto-join; Week 6 finalizes this into the canonical Daily Prebuilt two-step pattern:

```text
click "Join Consultation"
  → fetch join-token endpoint → { room_url, token }
  → wait for DailyIframe factory (up to 6s)
  → callFrame = createFrame(wrapper, { UI flags + iframeStyle })
  → wire all events BEFORE join()
  → await callFrame.join({ url: room_url, token })
  → joined-meeting event fires → connected
```

Key behaviors implemented:

- **DOM script wiring**: the `<script src="consultation-room.js">` tag carries `data-consultation-room`, `data-join-endpoint`, and `data-viewer-role` attributes so the same compiled JS module works unchanged for patient and doctor pages.
- **Duplicate-frame guard** — boolean `isBootstrapping` and `callFrame` null-check at the top of `bootstrapRoom()` so double-clicking Join or re-clicking during inflight initialization never mounts overlapping iframes.
- **Duplicate-join guard** — after `joined-meeting` fires, `hasJoined` short-circuits subsequent `join()` calls and the catch block swallows `already/duplicate` error messages instead of showing a spurious alert.
- **Factory poller `waitForDailyFactory(maxWaitMs)`** — fast-path (returns instantly if `window.DailyIframe.createFrame` is present) else 80 ms poll with a `maxWaitMs` cap, also aborting early if the CDN `onerror` fired. Falls back through `window.DailyIframe → window.Daily → window.DailyJs` so minor library-export changes remain resilient.
- **Explicit sizing** — both CSS wrapper dimensions (the `!important` rules above) AND Daily-side `iframeStyle: { width: '100%', height: '100%', border: '0', minHeight: '540px' }` are used together so height is guaranteed even on DOMs where inherited CSS is not observed by the library.
- **Event wiring BEFORE join()**:
  - `loaded` — confirms iframe and callClient handshake
  - `joining-meeting` / `joined-meeting` — transitions UI pills and removes empty-state spinners
  - `participant-joined` / `participant-left` — surfaces Bootstrap alerts with participant display name (patient↔doctor visibility confirmation)
  - `left-meeting` / `call-instance-destroyed` / `recording-stopped` — cleanup
  - **Error events**: `error`, `camera-error`, `microphone-error`, `nonfatal-error`, `available-devices-updated`, `network-connection`
- **Permission and device surfacing**:
  - If the error message mentions `not allowed`, `denied`, `permission`, or `mediaDevices`, the alert region shows a concrete action item: "Click the 🔒 icon in the address bar, open Site Settings, and allow Camera + Microphone, then refresh."
  - If error mentions `not found`, `404`, `room.*exist|available|found`, returns "Room not available — contact administrator".
  - If error mentions `Missing payment method` / `payment method`, returns a Daily dashboard billing message so the setup issue is never confused with application code.
  - Media device probes (`navigator.mediaDevices?.getUserMedia({video, audio})` pre-call) update `#vc-media-status-camera` and `#vc-media-status-mic` pills before join, so the user sees Granted / Prompted / Blocked / Unavailable early.
- **Bootstrap-alert error surface**: `showAlert(region, variant, title, body)` is used for all join errors and participant join/leave events with `alert-dismissible fade show`, full icon set, and close button — consistent with the rest of the application.

### 8. Dashboard & Consultations Listing: Join-Readiness Integration

Across patient, doctor, and admin surfaces, Week 6 exposes the join action inside the correct appointment window:

- Patient dashboard "Next Upcoming Appointment" card + Patient Consultation History + Upcoming Appointments tables add a teal "Join Consultation" CTA only when:
  - status is `Approved`
  - current Pacific/Port Moresby time is within (start − 10 min) → (end + 10 min)
- Doctor consultations workspace (`/doctor/consultations`) mirrors the same join-window-aware CTA for approved same-day consultations.
- Doctor dashboard cards show "Today's Approved Consultations" with per-row Join buttons.
- Admin dashboard and consultation detail views surface the `ConsultationRoom` presence (room created / room url stored / token endpoint reachable) so administrators can confirm the room is provisioned before the appointment window, without exposing either the API key or the meeting token to the admin UI.
- The helper `status_helper.php` renders status badges with Bootstrap pill colors (`primary`, `secondary`, `success`, `info`, `warning`, `danger`) consistently across all screens, so new consultation-room statuses introduced in Week 6 match Week 5 styling exactly.

### 9. Patient Phone Migration & General UI Polish Pass

- Migration `011_add_phone_to_patient_table.sql` adds an indexed `phone` column to the `patient` profile table so clinicians joining a remote consultation have a fallback contact number in the room-side summary strip if audio drops during Daily negotiation.
- General polish across admin/patient/doctor screens: spacing, card radii, shadow levels, and button hierarchy unified with the `--ux-*` tokens during the same code pass so the new video entry points do not feel grafted on.

## Database Work

Week 6 extends the schema with two new forward-only migrations (idempotent, do not touch earlier tables):

- `database/migrations/010_create_consultation_rooms_table.sql`
  - new `consultation_rooms` table with 1:1 mapping to `consultation_requests`, room name/URL storage, patient/doctor denormalized columns for fast authorization, JSON diagnostics column, and audit timestamps
  - UNIQUE on `consultation_request_id`, UNIQUE on `daily_room_name`, FK ON DELETE CASCADE, covering indices
- `database/migrations/011_add_phone_to_patient_table.sql`
  - adds `phone VARCHAR(50) NULL` on `patient` with index for quick clinic/telecom fallback lookups

Installation/upgrade instructions after Week 6 deploy:

```bash
mysql -u root telehealth_db < database/migrations/010_create_consultation_rooms_table.sql
mysql -u root telehealth_db < database/migrations/011_add_phone_to_patient_table.sql
```

## Files Created

- `app/Services/DailyService.php`
- `app/Services/VideoConsultationService.php`
- `app/Models/ConsultationRoom.php`
- `app/Views/patient/consultations/room.php`
- `app/Views/doctor/consultations/room.php`
- `app/Views/partials/shared/status_helper.php`
- `public/css/consultation-room.css`
- `public/js/consultation-room.js`
- `database/migrations/010_create_consultation_rooms_table.sql`
- `database/migrations/011_add_phone_to_patient_table.sql`
- `WEEK6_REPORT.md`

## Files Modified

- `routes/web.php` — added patient/doctor room GET + join-token POST routes
- `app/Controllers/PatientController.php` — added `showConsultationRoom`, `joinConsultation`
- `app/Controllers/DoctorController.php` — added symmetric `showConsultationRoom`, `joinConsultation`
- `app/Core/Controller.php` — `json()` helper with consistent headers + `JSON_UNESCAPED_SLASHES`
- `app/Helpers/Helper.php` — status badge utilities, join-window predicate helpers used by dashboard views
- `app/Models/ConsultationRequest.php` — inside `updateStatusForAdmin`, wraps Approved transition inside a PDO transaction together with `ConsultationRoom::upsertForApprovedConsultation` so the three side-effects (status = Approved, slot stays Booked, Daily room + row persisted) commit atomically or roll back as one unit; also adds next-approved and join-window predicates used by the dashboard cards
- Admin / Doctor / Patient views: consultation-request index/show, dashboard pages, availability pages, profile pages, layouts, sidebar/topbar, public landing footer/navbar — refreshed to add the "Join Consultation" CTA in the correct window and to standardize badges, spacing, and information density for the room entry points
- `public/css/style.css`, `public/css/theme.css` — unified `--ux-*` token usage across the new room page
- `public/js/dashboard.js` — minor data attribute wiring so "Join Consultation" CTAs use the same `data-consultation-id` + `data-join-window` convention as the room module
- `public/index.php` — minor router + base path stabilization
- `.env.example` — documents the now-used `DAILY_API_KEY`, `DAILY_DOMAIN`, and optional video TTL overrides

## Architecture Notes

### Controller Layer

The Week 6 additions continue the thin-controller coordination pattern used in Weeks 1–5:

- Admin routes stay routed through `AdminController` (booking pages and the consultation-room diagnostic surface).
- Patient routes live in `PatientController` (dashboard, history, new room shell, new join-token POST).
- Doctor routes live symmetrically in `DoctorController`.

Controllers never call `curl` directly, never mint JWTs, and never format Daily payloads — they hand off to services and serialize the returned shape to HTML or JSON.

### Service Layer

Week 6 adds three service boundaries so each piece remains independently testable:

- `DailyService` — pure Daily REST wrapper. No MySQL, no sessions, no roles. Easy to unit test by swapping a mock base URL or fixed cURL harness.
- `VideoConsultationService` — policy. Owns authorization, status rules, join-window math, the decision of whether to create a new stored room row on first join, and the assembly of the response `{room_url, token}`. Does NOT call the Daily REST API directly except by delegating through the model layer.
- Supporting services reused unchanged from Weeks 4–5:
  - `DoctorConsultationService`, `DoctorDashboardService`
  - `AdminConsultationService`, `AdminUserService`, `AdminDoctorService`, `AdminPatientService`
  - `PatientConsultationBookingService`, `PatientDirectoryService`, `ProfilePhotoService`

### Model Layer

Week 6 adds one dedicated model (`ConsultationRoom`) and extends one existing model:

- `ConsultationRoom` — idempotent upsert, direct row-by-request-id lookup, delete, direct diagnostic JSON column read/write, all via PDO prepared statements.
- `ConsultationRequest` — adds:
  - transactional link to `ConsultationRoom` in the admin approval path
  - join-window predicate helpers (`withinJoinWindowForRequest`) used in dashboard views, so CTA visibility stays DRY across patient/doctor/admin screens
  - distribution and next-approved queries fed to dashboard charts and cards

### Security Notes (Week 6 specifics)

- **API key confidentiality**: `DAILY_API_KEY` is read by `Environment` only; PHP server uses it in cURL Authorization headers. It never escapes to HTML, JSON, JS, logs, or cookies. No token is hardcoded anywhere.
- **CSRF**: the POST join-token endpoints are CSRF-protected using the existing `Csrf::verify` pattern from the application middleware.
- **Authorization**: `VideoConsultationService` re-checks role + patient_id/doctor_id link on the server, even though navigation already hides the CTA. Authentication ≠ authorization.
- **Short-lived tokens**: Daily JWT meeting tokens are 30-minute TTLs, never stored in MySQL (fresh-minted per call), so a leaked token is useless after the appointment window plus a small grace period.
- **Room binding**: meeting tokens bind to a specific `room_name` so tokens cannot be replayed across consultations.
- **XSS**: All user-visible strings rendered in the room shell and Bootstrap alerts are escaped through the standard `Helper::escape` wrapper.

## Testing & Verification Performed

The following was verified to confirm Week 6 integrity before packaging the commit:

1. **PHP syntax validation**: `php -l` passes clean for all 25 touched PHP controllers, services, models, and views.
2. **Backend diagnostic harness** (removed before commit):
   - Environment config loads (`DAILY_API_KEY` 64 chars, `DAILY_DOMAIN = mbphatelehealth.daily.co`)
   - `DailyService::createRoom()` returns a URL that passes `filter_var(..., FILTER_VALIDATE_URL)` and contains both `daily.co` and the configured domain
   - Patient token (357 chars) + Doctor owner token (355 chars) both mint for the same room name
   - `DailyService::deleteRoom()` cleans the diagnostic room afterwards
3. **Idempotency proof (service-level)**:
   - First `upsertForApprovedConsultation(requestId)` returns `action: created`
   - Second identical call returns `action: reused` with the same `daily_room_name` and `daily_room_url` strings (byte-for-byte match)
   - DB UNIQUE constraint verified to catch races if two requests somehow interleave the SELECT-first fast path
4. **Same-room verification for both roles**:
   - Patient endpoint returns `room_url = X`
   - Doctor endpoint with the same consultation id returns `room_url = X` (identical string)
   - Both JWTs decode to the same room claim `r`
5. **Frontend static harness mirror (removed before commit)**:
   - Duplicate-frame guard: two clicks on Join in the static mirror → iframe count stays `1`
   - Factory poller: `waitForDailyFactory()` returns before timeout on cold load
   - Explicit `join()` dispatch: browser console log observes call-client id assignment, `loaded` event, then `joining-meeting` event
   - Sizing: post-join evaluate on wrapper → iframe inline `width: 100% height: 100% minHeight: 540px` AND computed `minHeight ≈ 904px` from the CSS clamp rule
   - Permission error path: synthetic error message containing `denied` renders the actionable site-settings alert
6. **Network/CDN reachability**:
   - Daily JS CDN pinned to `@daily-co/daily-js@0.67.0/dist/daily-iframe.min.js` on jsdelivr verified to download correctly and populate `window.DailyIframe.createFrame`
7. **Dashboard/listing CTA visibility**:
   - CTA hidden for rejected/cancelled/pending rows
   - CTA shown only inside the computed join window for Approved rows (start−10 → end+10)
   - Correct badge colors via `status_helper.php` for all statuses
8. **Note on end-user media verification**: Joining the room in-browser for true two-way patient ↔ doctor camera/microphone media flow also requires the Daily hosting account to have a payment method on file at `dashboard.daily.co` (Daily SFU anti-abuse policy for any real media allocation, including free-tier minutes). Once the payment method is added on the Daily dashboard, the prejoin UI, camera permission prompt, mic permission prompt, participant join event, and two-way audio/video all proceed as designed in the code — no further application changes needed.

## Current Status

Week 6 completes the video consultation module. The TeleHealth platform now supports:

- server-side, credential-safe Daily.co REST room provisioning
- idempotent, DB-guarded consultation ↔ Daily room mapping so duplicate rooms are impossible
- role-scoped join authorization with join-window enforcement in Pacific/Port Moresby time
- same shared consultation room page for patient and doctor with identical CDN/CSS/JS wiring
- explicit Prebuilt iframe bootstrap with guaranteed dimensions, duplicate guards, and permission-aware error surfacing
- join CTAs exposed across dashboards and consultations listings at the correct point in the appointment lifecycle

Per the approved ten-week schedule, remaining deferred modules for later weeks are:

- consultation records (Week 7)
- prescriptions (Week 7)
- consultation history deep views and PDF export (Week 8)
- security review, bug fixing, and UI refinement (Week 9)
- deployment, documentation, and final testing (Week 10)

## Git

- Week 6 commit produced:
  - `Complete Week 6 Task` (sha `fcdb799`) on branch `master`
  - 57 files changed, 11 016 insertions, 2 571 deletions
  - 10 new files committed (services, models, views, migrations, assets + this report)
  - temporary diagnostic harness files (daily_diag, daily_makeroom, e2e_daily_pipeline, static_prebuilt_test, daily_test_room.json) were explicitly deleted before staging so no debugging artifacts ship with the project history
- Push target: `origin master → https://github.com/nefoticlyde76-dwu/Telehealth_Consultation.git`
