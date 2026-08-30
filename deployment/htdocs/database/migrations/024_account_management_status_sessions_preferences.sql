-- ──────────────────────────────────────────────────────────────────────────────
-- 024_account_management_status_sessions_preferences.sql
--
-- Account status expansion, last-login / forced password reset, session
-- registry (logout-other-devices), and notification preferences.
--
-- PERMANENT USER DELETION POLICY (application-enforced)
-- Do NOT blindly DELETE FROM users. Existing foreign keys cascade from
-- users → patient/doctor → consultation_requests → consultation_records
-- and prescriptions. Permanent deletion therefore anonymizes the users row,
-- keeps patient/doctor stubs for clinical FKs, and never removes submitted
-- consultation records, prescriptions, or audit_logs.
-- ──────────────────────────────────────────────────────────────────────────────

USE telehealth_db;

ALTER TABLE users
    MODIFY COLUMN status ENUM('active', 'suspended', 'inactive', 'deleted') NOT NULL DEFAULT 'active';

ALTER TABLE users
    ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL AFTER status,
    ADD COLUMN force_password_reset TINYINT(1) NOT NULL DEFAULT 0 AFTER last_login_at,
    ADD COLUMN password_changed_at DATETIME NULL DEFAULT NULL AFTER force_password_reset,
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER password_changed_at,
    ADD COLUMN anonymized_at DATETIME NULL DEFAULT NULL AFTER deleted_at;

CREATE INDEX idx_users_last_login_at ON users (last_login_at);
CREATE INDEX idx_users_deleted_at ON users (deleted_at);

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
