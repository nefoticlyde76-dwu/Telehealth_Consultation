-- ──────────────────────────────────────────────────────────────────────────────
-- 020_allow_null_user_password_for_invitation.sql
--
-- Doctor accounts are created by an administrator without a password.
-- NULL users.password means password setup is not complete.
-- A stored hash means the account owner has set their own password.
-- users.status remains the only active/inactive account-control field.
-- ──────────────────────────────────────────────────────────────────────────────

SET @need_nullable_password := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'password'
      AND IS_NULLABLE = 'NO'
);

SET @sql_nullable_password := IF(
    @need_nullable_password = 0,
    'SELECT ''[skip] users.password already nullable'' AS msg',
    'ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL'
);

PREPARE stmt_nullable_password FROM @sql_nullable_password;
EXECUTE stmt_nullable_password;
DEALLOCATE PREPARE stmt_nullable_password;
