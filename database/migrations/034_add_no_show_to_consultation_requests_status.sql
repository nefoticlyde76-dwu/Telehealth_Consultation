-- ──────────────────────────────────────────────────────────────────────────────
-- 034_add_no_show_to_consultation_requests_status.sql
--
-- Adds No-Show as a distinct terminal consultation status.
-- Existing Pending / Approved / Rejected / Cancelled / Completed rows are
-- unchanged. MODIFY ENUM only appends the new value.
-- ──────────────────────────────────────────────────────────────────────────────

USE telehealth_db;

SET @need_no_show := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consultation_requests'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE NOT LIKE '%No-Show%'
);

SET @sql_status := IF(
    @need_no_show = 0,
    'SELECT ''[skip] consultation_requests.status already includes No-Show'' AS msg',
    'ALTER TABLE consultation_requests MODIFY COLUMN status ENUM(''Pending'', ''Approved'', ''Rejected'', ''Cancelled'', ''Completed'', ''No-Show'') NOT NULL DEFAULT ''Pending'''
);

PREPARE stmt_status FROM @sql_status;
EXECUTE stmt_status;
DEALLOCATE PREPARE stmt_status;
