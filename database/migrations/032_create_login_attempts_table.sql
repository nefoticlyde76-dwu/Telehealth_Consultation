-- ──────────────────────────────────────────────────────────────────────────────
-- 032_create_login_attempts_table.sql
--
-- Brute-force protection for password login.
-- Tracks failed attempts per email + IP, including unknown emails, so lockout
-- messages cannot be used to enumerate accounts.
-- Does not alter users, sessions, CSRF, or password columns.
-- ──────────────────────────────────────────────────────────────────────────────

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
