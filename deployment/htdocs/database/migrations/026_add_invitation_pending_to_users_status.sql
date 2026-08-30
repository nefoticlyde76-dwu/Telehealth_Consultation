-- ──────────────────────────────────────────────────────────────────────────────
-- 026_add_invitation_pending_to_users_status.sql
--
-- Doctor password-invitation support (Feature 2).
-- Adds invitation_pending to users.status only.
-- Does not change passwords, Google identity, roles, doctor/patient rows,
-- or doctor_password_setup_tokens.
--
-- Existing rows keep their current status. invitation_pending is unused
-- until the doctor-creation workflow starts issuing invitations.
-- ──────────────────────────────────────────────────────────────────────────────

USE telehealth_db;

SET @need_invitation_pending := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE NOT LIKE '%invitation_pending%'
);

SET @sql_status := IF(
    @need_invitation_pending = 0,
    'SELECT ''[skip] users.status already includes invitation_pending'' AS msg',
    'ALTER TABLE users MODIFY COLUMN status ENUM(''invitation_pending'', ''active'', ''suspended'', ''inactive'', ''deleted'') NOT NULL DEFAULT ''active'''
);

PREPARE stmt_status FROM @sql_status;
EXECUTE stmt_status;
DEALLOCATE PREPARE stmt_status;
