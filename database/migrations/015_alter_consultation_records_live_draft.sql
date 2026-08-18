-- ──────────────────────────────────────────────────────────────────────────────
-- 015_alter_consultation_records_live_draft.sql
--
-- Week 7 Day 1 — Live clinical documentation during video consultation.
--
-- Extends the existing consultation_records table (created in 001) so a
-- doctor can save an in-progress DRAFT while the Daily call is running.
-- The record is NOT finalized by this migration or by draft autosave.
-- ──────────────────────────────────────────────────────────────────────────────

SET @exist_table := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'consultation_records'
);

-- (1) Chief complaint / presenting problem
SET @exist_chief := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'consultation_records'
      AND COLUMN_NAME  = 'chief_complaint'
);
SET @sql_chief := IF(
    @exist_table = 0,
    'SELECT ''[skip] consultation_records does not exist'' AS msg',
    IF(
        @exist_chief = 0,
        'ALTER TABLE consultation_records ADD COLUMN chief_complaint TEXT NULL AFTER doctor_id',
        'SELECT ''[skip] chief_complaint already exists'' AS msg'
    )
);
PREPARE stmt_chief FROM @sql_chief;
EXECUTE stmt_chief;
DEALLOCATE PREPARE stmt_chief;

-- (2) Clinical findings / assessment
SET @exist_findings := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'consultation_records'
      AND COLUMN_NAME  = 'clinical_findings'
);
SET @sql_findings := IF(
    @exist_table = 0,
    'SELECT ''[skip] consultation_records does not exist'' AS msg',
    IF(
        @exist_findings = 0,
        'ALTER TABLE consultation_records ADD COLUMN clinical_findings TEXT NULL AFTER symptoms',
        'SELECT ''[skip] clinical_findings already exists'' AS msg'
    )
);
PREPARE stmt_findings FROM @sql_findings;
EXECUTE stmt_findings;
DEALLOCATE PREPARE stmt_findings;

-- (3) Additional clinical notes
SET @exist_notes := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'consultation_records'
      AND COLUMN_NAME  = 'additional_notes'
);
SET @sql_notes := IF(
    @exist_table = 0,
    'SELECT ''[skip] consultation_records does not exist'' AS msg',
    IF(
        @exist_notes = 0,
        'ALTER TABLE consultation_records ADD COLUMN additional_notes TEXT NULL AFTER treatment_plan',
        'SELECT ''[skip] additional_notes already exists'' AS msg'
    )
);
PREPARE stmt_notes FROM @sql_notes;
EXECUTE stmt_notes;
DEALLOCATE PREPARE stmt_notes;

-- (4) Draft / Final status — default Draft so live notes are never auto-finalized
SET @exist_status := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'consultation_records'
      AND COLUMN_NAME  = 'record_status'
);
SET @sql_status := IF(
    @exist_table = 0,
    'SELECT ''[skip] consultation_records does not exist'' AS msg',
    IF(
        @exist_status = 0,
        'ALTER TABLE consultation_records
            ADD COLUMN record_status ENUM(''Draft'', ''Final'') NOT NULL DEFAULT ''Draft'' AFTER additional_notes',
        'SELECT ''[skip] record_status already exists'' AS msg'
    )
);
PREPARE stmt_status FROM @sql_status;
EXECUTE stmt_status;
DEALLOCATE PREPARE stmt_status;

-- (5) One clinical record per consultation request
SET @exist_uq := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'consultation_records'
      AND INDEX_NAME   = 'uq_consultation_records_request_id'
);
SET @sql_uq := IF(
    @exist_table = 0,
    'SELECT ''[skip] consultation_records does not exist'' AS msg',
    IF(
        @exist_uq = 0,
        'ALTER TABLE consultation_records
            ADD UNIQUE INDEX uq_consultation_records_request_id (consultation_request_id)',
        'SELECT ''[skip] unique request index already exists'' AS msg'
    )
);
PREPARE stmt_uq FROM @sql_uq;
EXECUTE stmt_uq;
DEALLOCATE PREPARE stmt_uq;
