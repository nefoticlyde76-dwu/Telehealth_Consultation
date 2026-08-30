-- ──────────────────────────────────────────────────────────────────────────────
-- 027_create_password_reset_tokens_table.sql
--
-- Self-service forgot-password / reset-password tokens.
-- Stores only the SHA-256 hash of a cryptographically random token.
-- The raw token is never persisted.
-- Does not alter users, doctor_password_setup_tokens, or other auth tables.
-- ──────────────────────────────────────────────────────────────────────────────

USE telehealth_db;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    token_hash CHAR(64) NOT NULL,
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
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
