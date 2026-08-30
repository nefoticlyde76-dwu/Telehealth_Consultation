-- ──────────────────────────────────────────────────────────────────────────────
-- 021_create_doctor_password_setup_tokens_table.sql
--
-- One-time doctor password-setup invitations.
-- Stores only the SHA-256 hash of a cryptographically random token.
-- The raw token is never persisted.
-- ──────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS doctor_password_setup_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctor_password_setup_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE INDEX uq_doctor_password_setup_token_hash (token_hash),
    INDEX idx_doctor_password_setup_user (user_id),
    INDEX idx_doctor_password_setup_expires (expires_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
