-- ──────────────────────────────────────────────────────────────────────────────
-- 023_ensure_consultation_records_and_prescriptions.sql
--
-- Week 8 completion audit found that some environments received later
-- migrations (rooms, notifications, invitation tokens) without the Week 1
-- consultation_records and prescriptions tables from 001. History pages and
-- PDF export query those tables, so they must exist.
--
-- This file is idempotent. It creates the missing tables with the final
-- Week 7 column set (001 + 015 + 016) and does not change existing rows.
-- ──────────────────────────────────────────────────────────────────────────────

SET @exist_records := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consultation_records'
);

SET @sql_records := IF(
    @exist_records > 0,
    'SELECT ''[skip] consultation_records already exists'' AS msg',
    'CREATE TABLE consultation_records (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        consultation_request_id BIGINT NOT NULL,
        patient_id BIGINT NOT NULL,
        doctor_id BIGINT NOT NULL,
        chief_complaint TEXT NULL,
        symptoms TEXT,
        clinical_findings TEXT NULL,
        diagnosis TEXT,
        treatment_plan TEXT,
        additional_notes TEXT NULL,
        record_status ENUM(''Draft'', ''Final'') NOT NULL DEFAULT ''Draft'',
        finalized_at TIMESTAMP NULL DEFAULT NULL,
        consultation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE INDEX uq_consultation_records_request_id (consultation_request_id),
        INDEX idx_consultation_records_patient_id (patient_id),
        INDEX idx_consultation_records_doctor_id (doctor_id),
        FOREIGN KEY (consultation_request_id) REFERENCES consultation_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (patient_id) REFERENCES patient(user_id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctor(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
);

PREPARE stmt_records FROM @sql_records;
EXECUTE stmt_records;
DEALLOCATE PREPARE stmt_records;

SET @exist_rx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'prescriptions'
);

SET @sql_rx := IF(
    @exist_rx > 0,
    'SELECT ''[skip] prescriptions already exists'' AS msg',
    'CREATE TABLE prescriptions (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        consultation_record_id BIGINT NOT NULL,
        doctor_id BIGINT NOT NULL,
        patient_id BIGINT NOT NULL,
        medication_name VARCHAR(255) NOT NULL,
        dosage VARCHAR(255) NOT NULL,
        frequency VARCHAR(255) NOT NULL,
        duration VARCHAR(255) NOT NULL,
        quantity VARCHAR(100) NULL DEFAULT NULL,
        additional_notes TEXT,
        issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_prescriptions_record_id (consultation_record_id),
        INDEX idx_prescriptions_doctor_id (doctor_id),
        INDEX idx_prescriptions_patient_id (patient_id),
        FOREIGN KEY (consultation_record_id) REFERENCES consultation_records(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctor(user_id) ON DELETE CASCADE,
        FOREIGN KEY (patient_id) REFERENCES patient(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
);

PREPARE stmt_rx FROM @sql_rx;
EXECUTE stmt_rx;
DEALLOCATE PREPARE stmt_rx;
