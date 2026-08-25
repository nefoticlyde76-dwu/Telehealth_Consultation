-- ──────────────────────────────────────────────────────────────────────────────
-- TeleHealth PNG — complete production schema
--
-- Use this file for a FRESH production database.
-- Do not run database/migrations/001_initial_schema.sql in production:
-- that file drops tables and seeds a local administrator password.
--
-- This file creates the current application schema (tables, columns, indexes,
-- primary keys, foreign keys, and constraints). It seeds roles only.
-- Create the first administrator with: php bin/create_admin.php
-- ──────────────────────────────────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS telehealth_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE telehealth_db;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NULL,
    google_sub VARCHAR(255) NULL DEFAULT NULL,
    google_email VARCHAR(255) NULL DEFAULT NULL,
    auth_provider ENUM('local', 'google', 'both') NOT NULL DEFAULT 'local',
    status ENUM('invitation_pending', 'active', 'suspended', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL DEFAULT NULL,
    force_password_reset TINYINT(1) NOT NULL DEFAULT 0,
    password_changed_at DATETIME NULL DEFAULT NULL,
    deleted_at DATETIME NULL DEFAULT NULL,
    anonymized_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE INDEX uq_users_google_sub (google_sub),
    INDEX idx_users_role_id (role_id),
    INDEX idx_users_email (email),
    INDEX idx_users_status (status),
    INDEX idx_users_last_login_at (last_login_at),
    INDEX idx_users_deleted_at (deleted_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patient (
    user_id BIGINT PRIMARY KEY,
    phone VARCHAR(30) NULL,
    dob DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    medical_history TEXT,
    profile_photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_patient_phone (phone)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS doctor (
    user_id BIGINT PRIMARY KEY,
    phone VARCHAR(30) NULL,
    gender ENUM('male', 'female', 'other') NULL,
    professional_title VARCHAR(150) NULL,
    specialization VARCHAR(255),
    employee_id VARCHAR(100) NULL UNIQUE,
    license_number VARCHAR(100) UNIQUE,
    clinic_address TEXT,
    consultation_fee DECIMAL(10,2),
    signature_path VARCHAR(255),
    consent_doc_path VARCHAR(255),
    profile_photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doctor_specialization (specialization)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin (
    user_id BIGINT PRIMARY KEY,
    employee_id VARCHAR(100) UNIQUE,
    profile_photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS doctor_availability (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    doctor_id BIGINT NOT NULL,
    consultation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    notes TEXT NULL,
    status ENUM('Available', 'Booked') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctor(user_id) ON DELETE CASCADE,
    INDEX idx_doctor_availability_doctor_id (doctor_id),
    INDEX idx_doctor_availability_date (consultation_date),
    INDEX idx_doctor_availability_status (status)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consultation_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT NOT NULL,
    doctor_id BIGINT NOT NULL,
    availability_id BIGINT,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed') DEFAULT 'Pending',
    completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patient(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctor(user_id) ON DELETE CASCADE,
    FOREIGN KEY (availability_id) REFERENCES doctor_availability(id) ON DELETE SET NULL,
    INDEX idx_consultation_requests_patient_id (patient_id),
    INDEX idx_consultation_requests_doctor_id (doctor_id),
    INDEX idx_consultation_requests_status (status),
    INDEX idx_consultation_requests_availability_id (availability_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consultation_records (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    consultation_request_id BIGINT NOT NULL,
    patient_id BIGINT NOT NULL,
    doctor_id BIGINT NOT NULL,
    chief_complaint TEXT NULL,
    symptoms TEXT,
    clinical_findings TEXT NULL,
    diagnosis TEXT,
    treatment_plan TEXT,
    additional_notes TEXT NULL,
    record_status ENUM('Draft', 'Final') NOT NULL DEFAULT 'Draft',
    finalized_at TIMESTAMP NULL DEFAULT NULL,
    consultation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX uq_consultation_records_request_id (consultation_request_id),
    INDEX idx_consultation_records_patient_id (patient_id),
    INDEX idx_consultation_records_doctor_id (doctor_id),
    FOREIGN KEY (consultation_request_id) REFERENCES consultation_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patient(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctor(user_id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

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
    additional_notes TEXT,
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prescriptions_record_id (consultation_record_id),
    INDEX idx_prescriptions_doctor_id (doctor_id),
    INDEX idx_prescriptions_patient_id (patient_id),
    FOREIGN KEY (consultation_record_id) REFERENCES consultation_records(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctor(user_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patient(user_id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consultation_rooms (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    consultation_request_id BIGINT NOT NULL,
    daily_room_name VARCHAR(120) NOT NULL,
    daily_room_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (consultation_request_id) REFERENCES consultation_requests(id) ON DELETE CASCADE,
    CONSTRAINT uq_consultation_rooms_request_id UNIQUE (consultation_request_id),
    CONSTRAINT uq_consultation_rooms_room_name UNIQUE (daily_room_name),
    INDEX idx_consultation_rooms_request_expires (consultation_request_id, expires_at),
    INDEX idx_consultation_rooms_expires_at (expires_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

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
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

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
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    session_token_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_seen_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_sessions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE INDEX uq_user_sessions_token_hash (session_token_hash),
    INDEX idx_user_sessions_user_seen (user_id, last_seen_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

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
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS doctor_password_setup_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    token_hash CHAR(64) NOT NULL,
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
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (name) VALUES
('admin'),
('doctor'),
('patient');
