# MBPHA TeleHealth Consultation System

## Project Progress Presentation: Weeks 6–10

**Student:** Clyde  
**Student ID:** 230176  
**Programme:** Bachelor of Information Systems  
**Presentation Date:** [Presentation Date]

---

# 1. Introduction

**Project title:** MBPHA TeleHealth Consultation System

**Project purpose:** Deliver a secure, web-based teleconsultation system for the Milne Bay Provincial Health Authority (MBPHA) so that a patient, assigned doctor, and administrator can complete the approved consultation pathway: booking and approval, video consultation, clinical documentation, prescription issuance, and PDF export.

**Target users:** Patients, Doctors, and Administrators

**Reporting period:** Weeks 6–10

---

# 2. Work Achievements: Weeks 6–10

| Week | Work Completed | Deliverable / Output | Evidence |
| ---- | -------------- | -------------------- | -------- |
| Week 6 | Video Consultation Module: Daily.co Prebuilt integration, server-side room lifecycle, idempotent consultation–room mapping, role-aware join-token issuance, shared patient/doctor room UI, and Pacific/Port Moresby join-window enforcement | `DailyService`, `VideoConsultationService`, `ConsultationRoom`, shared room page, migrations `010` and `011` | Git commit `Complete Week 6 Task` (`fcdb799`); repository `https://github.com/nefoticlyde76-dwu/Telehealth_Consultation.git`; PHP syntax validation; Daily room/token/idempotency checks |
| Week 7 | Consultation records and prescription module: live Draft clinical documentation during the Daily call, doctor-only completion, explicit prescription issuance after completion, dedicated historical record views, and administrator manual Approve / Reject | `ConsultationRecord`, `Prescription`, clinical and prescription services, historical record views, migrations `015`–`017` | `test_week7_day3.php` **36 passed, 0 failed**; `test_week7_day4.php` **33 passed, 0 failed**; `test_week7_record_view.php` **48 passed, 0 failed**; committed on `master` to the same GitHub repository |
| Week 8 | Consultation history refinement, authorized consultation-record PDF export, A4 portrait prescription PDF export, download authorization, and supporting public/dashboard UI consistency | `ConsultationRecordPdfService`, `PrescriptionPdfService`, authorized download routes, shared history tables | Week 8 QA pass **329 passed, 0 failed**; Day 4 live document fixture; committed on `master` to the same GitHub repository |
| Week 9 | Full end-to-end HTTP integration testing, functional bug review, access-control and security testing, and targeted UI refinement (no redesign) | `bin/test_week9_integration.php`; required-field indicators on key forms; phone-size spacing pass in `dashboard-ui.css` | Integration suite **152/152** on two consecutive runs; Week 7 + Week 8 + Week 9 integration **488 passed, 0 failed**; supporting suites **183 passed, 0 failed**; Apache served the application at `http://localhost/Telehealth_Consultation_System/public` |
| Week 10 | Production deployment, production environment and document-root configuration, final database schema and administrator bootstrap, Apache / upload protection, production error handling, public-site documentation and SEO, project documentation, and final testing | Live site **https://mbphatelehealth.com**; `database/schema.sql`; `bin/create_admin.php`; `README.md`; `robots.txt`; `sitemap.xml` | Live public pages returned **200**; unauthenticated `/admin/dashboard` returned **302**; `bin/test_seo.php` **69 passed, 0 failed**; **WEEK 10 COMPLETE — DEPLOYED AT https://mbphatelehealth.com** |

---

# 3. Evidence of Completed Activities

Strongest documented evidence from Weeks 6–10:

**Implemented modules**

- Week 6 completed the video consultation module: credential-safe Daily.co room provisioning, one Daily room per approved consultation, role-scoped join tokens, and join CTAs only inside the appointment window.
- Week 7 completed consultation records and prescriptions: live Draft notes beside the Daily room, doctor-only Final completion, explicit prescription issuance, and read-only historical records.
- Week 8 completed authorized PDF export: Consultation History → View Record → download consultation-record PDF → download A4 portrait prescription PDF.
- Week 9 completed the testing, security, and refinement quality gate. No controller, service, model, route, or schema change was required.
- Week 10 completed deployment, documentation, and final testing on the production domain.

**Testing results (as recorded in the weekly reports)**

- Week 6: PHP syntax validation on 25 touched PHP files; Daily room create / token / delete checks; idempotent upsert (`created` then `reused`); patient and doctor endpoints returned the same `room_url`.
- Week 7: **117 passed, 0 failed** across the three Week 7 CLI scripts recorded in that report.
- Week 8: **329 passed, 0 failed** in the Week 8 QA pass, including the Day 4 live fixture (Draft refused; Final record PDF generated; prescription PDF refused until issued; A4 portrait prescription PDF generated; Patient B / Doctor B blocked).
- Week 9: official demonstration path executed twice as real HTTP traffic; both runs **152/152**. Combined Week 7 + Week 8 + Week 9 integration: **488 passed, 0 failed**.
- Week 10: Week 7–9 product suites kept as the official gate; Week 10 SEO suite **69 passed, 0 failed**. Live checks on `https://mbphatelehealth.com` confirmed homepage, About, How It Works, Contact, Login, `robots.txt`, and `sitemap.xml`.

**Repository and deployment evidence**

- Weeks 6–8: implementation committed on branch `master` and pushed to `https://github.com/nefoticlyde76-dwu/Telehealth_Consultation.git`.
- Week 6 commit recorded as `Complete Week 6 Task` (`fcdb799`); 57 files changed; temporary diagnostic harness files deleted before staging.
- Week 9: local application served at `http://localhost/Telehealth_Consultation_System/public`.
- Week 10: production origin **https://mbphatelehealth.com**; Daily video continues on `mbphatelehealth.daily.co`.

**Access-control and security evidence (Week 9)**

- Cross-user ID guessing blocked for consultations, records, prescriptions, and PDFs.
- Role isolation confirmed for patient, doctor, administrator, and guest routes.
- CSRF required on login, register, booking, availability, approve / reject / cancel, join-token, clinical draft, complete, and prescription.
- SQL-injection payloads rejected; XSS escaped; secrets and SQLSTATE not exposed on user-facing pages.

---

# 4. Completed Features / Deliverables

Major Week 6–10 deliverables, grouped by outcome:

**Video consultation (Week 6)**

- Server-side Daily.co room creation, meeting-token minting, and room deletion
- Idempotent consultation ↔ Daily room mapping
- Authorized patient and doctor join, with join-window enforcement
- Shared consultation-room page with Daily Prebuilt iframe bootstrap

**Clinical records and prescriptions (Week 7)**

- Live Draft clinical documentation during an Approved Daily consultation
- Doctor-only completion after required clinical fields
- Explicit prescription issuance after completion (not auto-created)
- Read-only historical records for patient and doctor
- Administrator manual Approve / Reject workspace

**Consultation history and PDF export (Week 8)**

- History actions: **Join Consultation** (Approved, in window) and **View Record** (Completed)
- Downloadable consultation-record PDF from finalized records
- Downloadable A4 portrait prescription PDF from issued prescriptions
- Patient-own and doctor-assigned download authorization; administrators have no PDF download routes

**Stabilization (Week 9)**

- End-to-end integration testing of the official demonstration path
- Server-side access-control and security testing
- Targeted UI refinement: required-field indicators and phone-size table/button spacing
- Recorded outcome: **WEEK 9 COMPLETE — STABLE AND READY FOR WEEK 10**

**Deployment and close-out (Week 10)**

- Live deployment at **https://mbphatelehealth.com**
- Production environment, `public/` document root, Apache / upload protection, and generic production error handling
- Final schema (`database/schema.sql`) and CLI administrator bootstrap
- Installation and environment documentation in `README.md` and `.env.example`
- Public-site SEO limited to Home, About, How It Works, and Contact

---

# 5. Next Plan of Action

Week 10 closes the approved ten-week plan. The Week 10 report does not schedule further implementation weeks.

Items documented as remaining **outside the MVP**:

| Week | Planned Task | Expected Output |
| ---- | ------------ | --------------- |
| Outside MVP | Electronic medical records beyond this consultation’s record | Not in approved scope |
| Outside MVP | Laboratory, billing, payments, pharmacy inventory, or messaging | Not in approved scope |
| Outside MVP | AI diagnosis and analytics dashboards | Not in approved scope |
| Outside MVP | Multi-hospital tenancy | Not in approved scope |

Documented remaining checks from the Week 10 report (not scheduled as new weeks):

- Local XAMPP MySQL was not running during the Week 10 report session, so the database-backed Week 7–9 CLI suites were not re-executed in that session.
- Production must keep `APP_ENV=production` and `APP_DEBUG=false`.
- Physical printer output and a live two-sided Daily camera/microphone session were not re-checked in that session.

---

# 6. Current Project Status

**Current status:** On schedule

**Reason:** All approved Week 6–10 modules were completed in their designated weeks. Week 6 delivered video consultation; Week 7 delivered records and prescriptions; Week 8 delivered PDF export; Week 9 completed testing, security, and UI refinement; Week 10 completed deployment, documentation, and final testing. Week 10 states that the three official close-out requirements are satisfied and that Weeks 1–9 business rules remain intact.

**Evidence:**

- Week 6 Git commit `fcdb799` and Weeks 6–8 GitHub pushes to `https://github.com/nefoticlyde76-dwu/Telehealth_Consultation.git`
- Week 7–9 quality gate: **488 passed, 0 failed**
- Week 9 decision: **STABLE AND READY FOR WEEK 10**
- Week 10 live site **https://mbphatelehealth.com** (public pages **200**; unauthenticated admin dashboard **302**)
- Week 10 SEO suite: **69 passed, 0 failed**
- Week 10 decision: **WEEK 10 COMPLETE — DEPLOYED AT https://mbphatelehealth.com**

**Main priority for the next stage:** Keep the production configuration documented in Week 10 (`APP_ENV=production`, `APP_DEBUG=false`) and complete the remaining checks recorded in that report: re-run the database-backed Week 7–9 suites when local MySQL is available, and re-check physical printer output and a live two-sided Daily camera/microphone session.

---

# 7. Summary and Questions

**Most important achievement from Weeks 6–10:** The approved ten-week MVP is complete and deployed. A patient, assigned doctor, and administrator can complete the official path on **https://mbphatelehealth.com**: register/login → find doctor → book → admin approve → join video → complete → final record → prescription → download both PDFs.

**Strongest evidence of progress:** Live production HTTP checks on **https://mbphatelehealth.com**, the Week 7–9 quality gate (**488 passed, 0 failed**), and the Week 10 SEO suite (**69 passed, 0 failed**).

**Next major deliverable:** No further implementation week is scheduled. Remaining work documented in Week 10 is outside the MVP, plus the outstanding production/local verification checks listed above.

**Thank you**

**Questions and feedback**
