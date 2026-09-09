-- ──────────────────────────────────────────────────────────────────────────────
-- MBPHA TeleHealth PNG — FINAL SYSTEM SCHEMA
--
-- Authoritative CREATE script for the complete application database.
-- Import this file onto a FRESH MySQL 8 / MariaDB instance:
--
--   mysql -u root -p < database/schema.sql
--
-- It consolidates every table, column, index, unique key, and foreign key
-- used by the running application after migrations 001–034:
--
--   Identity        roles, users, patient, doctor, admin
--   Scheduling      doctor_availability, consultation_requests, complaint_images
--   Clinical        consultation_records, prescriptions
--   Video           consultation_rooms
--   Operations      audit_logs, notifications, notification_preferences
--   Security        user_sessions, doctor_password_setup_tokens,
--                   password_reset_tokens, login_attempts
--
-- Intentionally absent
--   consultation_ai_reviews          dropped by 017
--   consultation_requests.specialization / attachment_*
--     leftover local columns; the app joins doctor.specialization and stores
--     symptom photos on complaint_image_path plus complaint_images.photo_blob
--   doctor_availability status Cancelled
--     leftover local enum; slots use Available | Booked | Expired
--
-- Do not import database/migrations/001_initial_schema.sql onto production.
-- That file drops tables. Existing databases should keep applying numbered
-- migrations.
--
-- This file seeds roles and one local administrator:
--   Email     admin@telehealth.local
--   Password  admin123
-- Change that password before any production use. Additional administrators
-- can still be created with:
--   php bin/create_admin.php "Full Name" admin@example.com "StrongPassword"
--
-- Permanent deletion policy (application-enforced)
--   Do not DELETE FROM users. Clinical FKs cascade through patient/doctor
--   stubs. UserDeletionService anonymizes the users row, keeps role stubs,
--   and retains consultation records, prescriptions, and audit_logs.
--
-- Entity-relationship overview
--
--   roles 1──N users
--   users 1──0..1 patient | doctor | admin
--   doctor 1──N doctor_availability
--   patient 1──N consultation_requests N──1 doctor
--   consultation_requests 1──0..1 complaint_images
--   doctor_availability 1──0..N consultation_requests   (SET NULL if slot removed)
--   consultation_requests 1──0..1 consultation_rooms
--   consultation_requests 1──0..1 consultation_records
--   consultation_records 1──N prescriptions
--   users 1──N notifications
--   users 1──N user_sessions
--   users 1──0..1 notification_preferences
--   users 1──N doctor_password_setup_tokens
--   users 1──N password_reset_tokens
--   users 1──N audit_logs (actor_user_id, SET NULL)
-- ──────────────────────────────────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS telehealth_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE telehealth_db;

SET NAMES utf8mb4;

-- ══════════════════════════════════════════════════════════════════════════════
-- Identity and role-based access
-- ══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE COMMENT 'admin | doctor | patient',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='RBAC lookup. Seeded with admin, doctor, patient.';

CREATE TABLE IF NOT EXISTS users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE COMMENT 'Primary login identifier',
    password VARCHAR(255) NULL COMMENT 'password_hash(); NULL for Google-only or invited doctors',
    google_sub VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stable Google subject; UNIQUE allows multiple NULLs',
    google_email VARCHAR(255) NULL DEFAULT NULL COMMENT 'Informational Google email; not a login key',
    auth_provider ENUM('local', 'google', 'both') NOT NULL DEFAULT 'local',
    status ENUM('invitation_pending', 'active', 'suspended', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL DEFAULT NULL,
    force_password_reset TINYINT(1) NOT NULL DEFAULT 0,
    password_changed_at DATETIME NULL DEFAULT NULL,
    deleted_at DATETIME NULL DEFAULT NULL,
    anonymized_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE RESTRICT,
    UNIQUE INDEX uq_users_google_sub (google_sub),
    INDEX idx_users_role_id (role_id),
    INDEX idx_users_email (email),
    INDEX idx_users_status (status),
    INDEX idx_users_last_login_at (last_login_at),
    INDEX idx_users_deleted_at (deleted_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Shared login account. Role profile lives in patient, doctor, or admin.';

CREATE TABLE IF NOT EXISTS patient (
    user_id BIGINT PRIMARY KEY,
    phone VARCHAR(30) NULL,
    dob DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    address TEXT NULL,
    medical_history TEXT NULL,
    profile_photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_patient_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_patient_phone (phone)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Patient profile. PK is users.id.';

CREATE TABLE IF NOT EXISTS doctor (
    user_id BIGINT PRIMARY KEY,
    phone VARCHAR(30) NULL,
    gender ENUM('male', 'female', 'other') NULL,
    professional_title VARCHAR(150) NULL,
    specialization VARCHAR(255) NULL,
    employee_id VARCHAR(100) NULL UNIQUE,
    license_number VARCHAR(100) NULL UNIQUE,
    clinic_address TEXT NULL,
    consultation_fee DECIMAL(10,2) NULL,
    signature_path VARCHAR(255) NULL COMMENT 'Relative path used on PDF exports',
    signature_mime VARCHAR(32) NULL,
    signature_blob MEDIUMBLOB NULL COMMENT 'Persists the signature across ephemeral deploys',
    consent_doc_path VARCHAR(255) NULL,
    profile_photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctor_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_doctor_specialization (specialization)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Doctor profile. Created by administrator invitation.';

CREATE TABLE IF NOT EXISTS admin (
    user_id BIGINT PRIMARY KEY,
    employee_id VARCHAR(100) NULL UNIQUE,
    profile_photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Administrator profile. PK is users.id.';

CREATE TABLE IF NOT EXISTS profile_photos (
    user_id BIGINT PRIMARY KEY,
    mime VARCHAR(32) NOT NULL,
    photo_blob MEDIUMBLOB NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profile_photos_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Persists profile photos across ephemeral deploys';

-- ══════════════════════════════════════════════════════════════════════════════
-- Scheduling and booking
-- ══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS doctor_availability (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    doctor_id BIGINT NOT NULL,
    consultation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    notes TEXT NULL,
    status ENUM('Available', 'Booked', 'Expired') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctor_availability_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctor(user_id)
        ON DELETE CASCADE,
    INDEX idx_doctor_availability_doctor_id (doctor_id),
    INDEX idx_doctor_availability_date (consultation_date),
    INDEX idx_doctor_availability_status (status),
    INDEX idx_doctor_availability_status_date (status, consultation_date),
    INDEX idx_doctor_availability_doctor_date_status (doctor_id, consultation_date, status)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Doctor-owned consultation slots. Booked when a request is approved.';

CREATE TABLE IF NOT EXISTS consultation_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT NOT NULL,
    doctor_id BIGINT NOT NULL,
    availability_id BIGINT NULL COMMENT 'Linked slot; SET NULL if the slot row is removed',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT NULL,
    complaint_image_path VARCHAR(255) NULL DEFAULT NULL COMMENT 'Relative path under storage/; blob copy lives in complaint_images',
    status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed', 'No-Show') NOT NULL DEFAULT 'Pending',
    completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_consultation_requests_patient
        FOREIGN KEY (patient_id) REFERENCES patient(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_consultation_requests_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctor(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_consultation_requests_availability
        FOREIGN KEY (availability_id) REFERENCES doctor_availability(id)
        ON DELETE SET NULL,
    INDEX idx_consultation_requests_patient_id (patient_id),
    INDEX idx_consultation_requests_doctor_id (doctor_id),
    INDEX idx_consultation_requests_status (status),
    INDEX idx_consultation_requests_availability_id (availability_id),
    INDEX idx_consultation_requests_patient_status (patient_id, status),
    INDEX idx_consultation_requests_doctor_status (doctor_id, status)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Patient booking. Approved rows are the appointment of record.';

CREATE TABLE IF NOT EXISTS complaint_images (
    request_id BIGINT PRIMARY KEY,
    mime VARCHAR(32) NOT NULL,
    photo_blob MEDIUMBLOB NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaint_images_request
        FOREIGN KEY (request_id) REFERENCES consultation_requests(id)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Persists complaint images across ephemeral deploys';

-- ══════════════════════════════════════════════════════════════════════════════
-- Clinical documentation
-- ══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS consultation_records (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    consultation_request_id BIGINT NOT NULL,
    patient_id BIGINT NOT NULL,
    doctor_id BIGINT NOT NULL,
    chief_complaint TEXT NULL,
    symptoms TEXT NULL,
    clinical_findings TEXT NULL,
    diagnosis TEXT NULL,
    treatment_plan TEXT NULL,
    additional_notes TEXT NULL,
    record_status ENUM('Draft', 'Final') NOT NULL DEFAULT 'Draft',
    finalized_at TIMESTAMP NULL DEFAULT NULL,
    consultation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX uq_consultation_records_request_id (consultation_request_id),
    INDEX idx_consultation_records_patient_id (patient_id),
    INDEX idx_consultation_records_doctor_id (doctor_id),
    INDEX idx_consultation_records_status (record_status),
    CONSTRAINT fk_consultation_records_request
        FOREIGN KEY (consultation_request_id) REFERENCES consultation_requests(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_consultation_records_patient
        FOREIGN KEY (patient_id) REFERENCES patient(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_consultation_records_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctor(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='One clinical record per consultation. Draft until the doctor completes.';

CREATE TABLE IF NOT EXISTS prescriptions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    consultation_record_id BIGINT NOT NULL,
    doctor_id BIGINT NOT NULL,
    patient_id BIGINT NOT NULL,
    medication_name VARCHAR(255) NOT NULL,
    dosage VARCHAR(255) NOT NULL,
    frequency VARCHAR(255) NOT NULL,
    duration VARCHAR(255) NOT NULL,
    quantity VARCHAR(100) NULL DEFAULT NULL,
    additional_notes TEXT NULL,
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prescriptions_record_id (consultation_record_id),
    INDEX idx_prescriptions_doctor_id (doctor_id),
    INDEX idx_prescriptions_patient_id (patient_id),
    CONSTRAINT fk_prescriptions_record
        FOREIGN KEY (consultation_record_id) REFERENCES consultation_records(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_prescriptions_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctor(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_prescriptions_patient
        FOREIGN KEY (patient_id) REFERENCES patient(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Medication lines issued after a consultation is completed. One row per drug.';

-- ══════════════════════════════════════════════════════════════════════════════
-- Video consultation (Daily.co)
-- ══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS consultation_rooms (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    consultation_request_id BIGINT NOT NULL COMMENT 'Exactly one Daily room per approved request',
    daily_room_name VARCHAR(120) NOT NULL,
    daily_room_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Daily room expiry; join is refused after this',
    CONSTRAINT fk_consultation_rooms_request
        FOREIGN KEY (consultation_request_id) REFERENCES consultation_requests(id)
        ON DELETE CASCADE,
    CONSTRAINT uq_consultation_rooms_request_id UNIQUE (consultation_request_id),
    CONSTRAINT uq_consultation_rooms_room_name UNIQUE (daily_room_name),
    INDEX idx_consultation_rooms_request_expires (consultation_request_id, expires_at),
    INDEX idx_consultation_rooms_expires_at (expires_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Persistent Daily room metadata. Meeting tokens are never stored.';

-- ══════════════════════════════════════════════════════════════════════════════
-- Operations: audit trail and in-app notifications
-- ══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT NULL,
    actor_name VARCHAR(255) NOT NULL,
    actor_role VARCHAR(50) NULL,
    action VARCHAR(100) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_label VARCHAR(255) NOT NULL,
    event_category VARCHAR(100) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    subject_role VARCHAR(50) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT NULL,
    outcome VARCHAR(20) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_actor_user
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_audit_logs_actor_user_id (actor_user_id),
    INDEX idx_audit_logs_action (action),
    INDEX idx_audit_logs_created_at (created_at),
    INDEX idx_audit_logs_event_type (event_type),
    INDEX idx_audit_logs_actor_role (actor_role),
    INDEX idx_audit_logs_entity (entity_type, entity_id),
    INDEX idx_audit_logs_outcome (outcome)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Append-only activity log. Never deleted during account anonymization.';

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_entity_type VARCHAR(50) NOT NULL DEFAULT 'consultation_request',
    related_entity_id BIGINT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_notifications_user_created (user_id, created_at),
    INDEX idx_notifications_user_unread (user_id, is_read, created_at),
    UNIQUE INDEX uq_notifications_dedupe (user_id, notification_type, related_entity_type, related_entity_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='In-app notifications. Unique key prevents duplicate events per user.';

CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id BIGINT PRIMARY KEY,
    appointment_in_app TINYINT(1) NOT NULL DEFAULT 1,
    consultation_in_app TINYINT(1) NOT NULL DEFAULT 1,
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_preferences_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Per-user notification channel flags.';

-- ══════════════════════════════════════════════════════════════════════════════
-- Security: sessions and hashed tokens (raw tokens are never stored)
-- ══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    session_token_hash CHAR(64) NOT NULL COMMENT 'SHA-256 of the session identifier',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_seen_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_sessions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE INDEX uq_user_sessions_token_hash (session_token_hash),
    INDEX idx_user_sessions_user_seen (user_id, last_seen_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Registered sessions for logout-other-devices.';

CREATE TABLE IF NOT EXISTS doctor_password_setup_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    token_hash CHAR(64) NOT NULL COMMENT 'SHA-256 of the invitation token',
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    sent_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctor_password_setup_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE INDEX uq_doctor_password_setup_token_hash (token_hash),
    INDEX idx_doctor_password_setup_user (user_id),
    INDEX idx_doctor_password_setup_expires (expires_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Doctor invitation password-setup tokens. Hash only.';

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    token_hash CHAR(64) NOT NULL COMMENT 'SHA-256 of the reset bearer token',
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE INDEX uq_password_reset_token_hash (token_hash),
    INDEX idx_password_reset_tokens_user (user_id),
    INDEX idx_password_reset_tokens_expires (expires_at),
    INDEX idx_password_reset_tokens_user_usable (user_id, used_at, expires_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Self-service password-reset tokens. Hash only.';

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    last_failed_at DATETIME NOT NULL,
    locked_until DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX uq_login_attempts_email_ip (email, ip_address),
    INDEX idx_login_attempts_locked_until (locked_until)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Failed password-login counters keyed by email and IP.';

-- ══════════════════════════════════════════════════════════════════════════════
-- Seed data (roles + local administrator only)
-- ══════════════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO roles (name) VALUES
('admin'),
('doctor'),
('patient');

-- Local development administrator. Password is admin123.
-- Never leave this account unchanged in production.
INSERT IGNORE INTO users (role_id, full_name, email, password, status)
SELECT id, 'System Administrator', 'admin@telehealth.local',
       '$2y$10$iCQxLpuu12uiBBNZWkiZi.aMA9K/VcbZsJrTANxUaamMdF5eq9PzK', 'active'
  FROM roles
 WHERE name = 'admin'
 LIMIT 1;

INSERT IGNORE INTO admin (user_id, employee_id)
SELECT id, 'ADMIN-001'
  FROM users
 WHERE email = 'admin@telehealth.local'
 LIMIT 1;
