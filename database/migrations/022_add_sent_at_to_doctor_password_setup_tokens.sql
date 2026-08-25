-- ──────────────────────────────────────────────────────────────────────────────
-- 022_add_sent_at_to_doctor_password_setup_tokens.sql
--
-- Records a successful invitation send so resend cooldown can apply
-- without blocking an immediate retry after a failed send.
-- ──────────────────────────────────────────────────────────────────────────────

SET @need_sent_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'doctor_password_setup_tokens'
      AND COLUMN_NAME = 'sent_at'
);

SET @sql_sent_at := IF(
    @need_sent_at > 0,
    'SELECT ''[skip] sent_at already exists'' AS msg',
    'ALTER TABLE doctor_password_setup_tokens ADD COLUMN sent_at DATETIME NULL DEFAULT NULL AFTER used_at'
);

PREPARE stmt_sent_at FROM @sql_sent_at;
EXECUTE stmt_sent_at;
DEALLOCATE PREPARE stmt_sent_at;
