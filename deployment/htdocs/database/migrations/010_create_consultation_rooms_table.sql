-- ──────────────────────────────────────────────────────────────────────────────
-- 010_create_consultation_rooms_table.sql
--
-- Week 6  —  Daily Video Consultation Integration
--
-- PURPOSE
-- Associates exactly ONE Daily.co private room with ONE approved consultation
-- request.  This table stores only persistent room metadata needed to locate
-- the correct Daily room for an appointment at join time.
--
-- -----------------------------------------------------------------------------
-- DESIGN RATIONALE
-- -----------------------------------------------------------------------------
-- 1. LINKED EXISTING TABLE
--    The authoritative record for an approved appointment/consultation is
--    `consultation_requests` (created by 001_initial_schema.sql line 101,
--    status extended by 007_…booking.sql to include the Approved state).
--    - Primary key     : consultation_requests.id  (BIGINT AUTO_INCREMENT)
--    - Approved flag   : consultation_requests.status = 'Approved'
--    - Schedule source : consultation_requests.availability_id  → links to
--                        doctor_availability.consultation_date / start_time /
--                        end_time.
--    We therefore do NOT need a separate "appointments" table — the existing
--    consultation_requests row IS the approved appointment once its status
--    transitions to Approved.
--
-- 2. ONE ROOM PER CONSULTATION
--    A UNIQUE constraint on `consultation_request_id` guarantees that a single
--    consultation can never accidentally receive multiple Daily rooms (for
--    example if the approval controller is re-entered or a user double-clicks
--    the Approve button).  The application layer will additionally use
--    INSERT … ON DUPLICATE KEY UPDATE semantics so that retries are
--    idempotent.
--
-- 3. MEETING TOKENS ARE NOT STORED
--    Daily meeting tokens are short-lived JWT-style artefacts that MUST be
--    generated dynamically at the moment the doctor or patient clicks “Join
--    Consultation”.  Storing them would:
--      • expose the system to replay / token-theft risk,
--      • bind tokens to a concrete room instead of re-issuing with correct
--        is_owner / user_name / exp for each actor,
--      • force us to implement token-expiry cleanup in the DB (redundant with
--        Daily's server-side exp claim).
--    The PHP service layer therefore issues tokens via
--    DailyService::createMeetingToken() *only* when the Join route is
--    visited — after the current authenticated user + role have been fully
--    verified against the consultation.
--
-- 4. INNOCUOUS FOR WEEK 1–5 WORK
--    This migration adds ONE new table only.  No existing columns, indices or
--    FKs on any previously-defined table are modified.
--
-- 5. ON-DELETE CASCADE
--    If (for whatever admin/data-cleanup reason) a consultation_requests row
--    is removed, the attached consultation_rooms row evaporates
--    automatically via ON DELETE CASCADE — no orphan Daily-room metadata.
-- ──────────────────────────────────────────────────────────────────────────────

CREATE TABLE consultation_rooms (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    -- The approved consultation/appointment that owns this Daily room.
    -- Matches consultation_requests.id  (BIGINT PK, 001_initial_schema.sql:102)
    consultation_request_id BIGINT NOT NULL,

    -- Daily room name as returned from POST /v1/rooms.
    -- Example: "mbpha-consultation-12345-a1b2c3d4"
    daily_room_name VARCHAR(120) NOT NULL,

    -- Fully-qualified Daily room URL returned by the same API call.
    -- Example: "https://mbphatelehealth.daily.co/…"
    daily_room_url  VARCHAR(255) NOT NULL,

    -- When this room was successfully created via the Daily REST API.
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- When Daily will automatically expire / destroy the room (nbf + duration
    -- + cleanup buffer).  Allows the application to:
    --   • refuse join attempts after the room has lapsed,
    --   • schedule periodic cleanup jobs that delete stale rows.
    expires_at TIMESTAMP NULL DEFAULT NULL,

    -- ─── Relationships & data integrity ────────────────────────────────────
    FOREIGN KEY (consultation_request_id)
        REFERENCES consultation_requests(id)
        ON DELETE CASCADE,

    -- 1 consultation → 0 or 1 room.  NEVER 2+ rooms for the same consultation.
    CONSTRAINT uq_consultation_rooms_request_id
        UNIQUE (consultation_request_id),

    -- Daily room names are globally unique within a Daily account.  Adding
    -- a DB-level UNIQUE prevents two distinct consultation rows from ever
    -- pointing at the same Daily room URL (defense-in-depth against a
    -- duplicate-room bug in the application layer).
    CONSTRAINT uq_consultation_rooms_room_name
        UNIQUE (daily_room_name)
)
    ENGINE=InnoDB
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Look-up index for the join-time query path:
--   "given consultation_request_id, load its room metadata + expires check".
-- (The UNIQUE constraint above already creates a secondary index; this extra
-- covering index on (consultation_request_id, expires_at) keeps the most
-- frequent runtime query purely index-driven.)
CREATE INDEX idx_consultation_rooms_request_expires
    ON consultation_rooms (consultation_request_id, expires_at);

-- Index to support cron/admin-style "expire or delete all rooms past expires_at"
CREATE INDEX idx_consultation_rooms_expires_at
    ON consultation_rooms (expires_at);
