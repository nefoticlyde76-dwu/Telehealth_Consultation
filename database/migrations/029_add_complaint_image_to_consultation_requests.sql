-- ──────────────────────────────────────────────────────────────────────────────
-- 029_add_complaint_image_to_consultation_requests.sql
--
-- Optional patient complaint/symptom photo attached when booking.
-- The file is stored outside the public web root; this column holds the
-- relative path under storage/.
-- ──────────────────────────────────────────────────────────────────────────────

USE telehealth_db;

SET @exist_requests := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultation_requests'
);
SET @exist_complaint_image := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consultation_requests'
      AND COLUMN_NAME = 'complaint_image_path'
);
SET @sql_complaint_image := IF(
    @exist_requests = 0,
    'SELECT ''[skip] consultation_requests missing'' AS msg',
    IF(
        @exist_complaint_image = 0,
        'ALTER TABLE consultation_requests ADD COLUMN complaint_image_path VARCHAR(255) NULL DEFAULT NULL AFTER reason',
        'SELECT ''[skip] complaint_image_path already exists'' AS msg'
    )
);
PREPARE stmt_complaint_image FROM @sql_complaint_image;
EXECUTE stmt_complaint_image;
DEALLOCATE PREPARE stmt_complaint_image;
