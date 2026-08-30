-- ──────────────────────────────────────────────────────────────────────────────
-- 025_add_google_identity_to_users.sql
--
-- Google Identity Services (GIS) patient sign-in support.
-- Adds Google identity columns to users only. Does not change patient,
-- doctor, admin, passwords, emails, roles, or status values.
--
-- google_sub is the permanent Google account identifier (nullable UNIQUE).
-- google_email is informational and is not a login key.
-- auth_provider records how the account can authenticate:
--   local  = password only (existing users; this is the DEFAULT)
--   google = Google only (password IS NULL)
--   both   = Google linked to an existing password account
-- ──────────────────────────────────────────────────────────────────────────────

USE telehealth_db;

-- google_sub: stable Google subject. Multiple NULLs are valid under MySQL UNIQUE.
SET @need_google_sub := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'google_sub'
);

SET @sql_google_sub := IF(
    @need_google_sub > 0,
    'SELECT ''[skip] users.google_sub already exists'' AS msg',
    'ALTER TABLE users ADD COLUMN google_sub VARCHAR(255) NULL DEFAULT NULL AFTER password'
);

PREPARE stmt_google_sub FROM @sql_google_sub;
EXECUTE stmt_google_sub;
DEALLOCATE PREPARE stmt_google_sub;

SET @need_uq_google_sub := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uq_users_google_sub'
);

SET @sql_uq_google_sub := IF(
    @need_uq_google_sub > 0,
    'SELECT ''[skip] uq_users_google_sub already exists'' AS msg',
    'CREATE UNIQUE INDEX uq_users_google_sub ON users (google_sub)'
);

PREPARE stmt_uq_google_sub FROM @sql_uq_google_sub;
EXECUTE stmt_uq_google_sub;
DEALLOCATE PREPARE stmt_uq_google_sub;

SET @need_google_email := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'google_email'
);

SET @sql_google_email := IF(
    @need_google_email > 0,
    'SELECT ''[skip] users.google_email already exists'' AS msg',
    'ALTER TABLE users ADD COLUMN google_email VARCHAR(255) NULL DEFAULT NULL AFTER google_sub'
);

PREPARE stmt_google_email FROM @sql_google_email;
EXECUTE stmt_google_email;
DEALLOCATE PREPARE stmt_google_email;

SET @need_auth_provider := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'auth_provider'
);

SET @sql_auth_provider := IF(
    @need_auth_provider > 0,
    'SELECT ''[skip] users.auth_provider already exists'' AS msg',
    'ALTER TABLE users ADD COLUMN auth_provider ENUM(''local'',''google'',''both'') NOT NULL DEFAULT ''local'' AFTER google_email'
);

PREPARE stmt_auth_provider FROM @sql_auth_provider;
EXECUTE stmt_auth_provider;
DEALLOCATE PREPARE stmt_auth_provider;
