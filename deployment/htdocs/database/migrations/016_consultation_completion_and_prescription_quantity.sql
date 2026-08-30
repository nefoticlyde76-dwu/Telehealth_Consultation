-- ──────────────────────────────────────────────────────────────────────────────
-- 016_consultation_completion_and_prescription_quantity.sql
--
-- Week 7 Day 3 — Finalize consultation records and issue prescriptions.
-- Reuses consultation_records and prescriptions from 001.
-- ──────────────────────────────────────────────────────────────────────────────

SET @exist_requests := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultation_requests'
);
SET @exist_completed_at := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultation_requests' AND COLUMN_NAME = 'completed_at'
);
SET @sql_completed := IF(
    @exist_requests = 0,
    'SELECT ''[skip] consultation_requests missing'' AS msg',
    IF(
        @exist_completed_at = 0,
        'ALTER TABLE consultation_requests ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER status',
        'SELECT ''[skip] completed_at already exists'' AS msg'
    )
);
PREPARE stmt_completed FROM @sql_completed;
EXECUTE stmt_completed;
DEALLOCATE PREPARE stmt_completed;

SET @exist_records := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultation_records'
);
SET @exist_finalized_at := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultation_records' AND COLUMN_NAME = 'finalized_at'
);
SET @sql_finalized := IF(
    @exist_records = 0,
    'SELECT ''[skip] consultation_records missing'' AS msg',
    IF(
        @exist_finalized_at = 0,
        'ALTER TABLE consultation_records ADD COLUMN finalized_at TIMESTAMP NULL DEFAULT NULL AFTER record_status',
        'SELECT ''[skip] finalized_at already exists'' AS msg'
    )
);
PREPARE stmt_finalized FROM @sql_finalized;
EXECUTE stmt_finalized;
DEALLOCATE PREPARE stmt_finalized;

SET @exist_rx := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prescriptions'
);
SET @exist_qty := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prescriptions' AND COLUMN_NAME = 'quantity'
);
SET @sql_qty := IF(
    @exist_rx = 0,
    'SELECT ''[skip] prescriptions missing'' AS msg',
    IF(
        @exist_qty = 0,
        'ALTER TABLE prescriptions ADD COLUMN quantity VARCHAR(100) NULL DEFAULT NULL AFTER duration',
        'SELECT ''[skip] quantity already exists'' AS msg'
    )
);
PREPARE stmt_qty FROM @sql_qty;
EXECUTE stmt_qty;
DEALLOCATE PREPARE stmt_qty;
