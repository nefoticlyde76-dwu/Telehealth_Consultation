-- ──────────────────────────────────────────────────────────────────────────────
-- 028_add_expired_to_doctor_availability_status.sql
--
-- Unbooked slots that have passed their end datetime are marked Expired and
-- removed from active availability listings. Booked appointments are unchanged.
-- ──────────────────────────────────────────────────────────────────────────────

USE telehealth_db;

ALTER TABLE doctor_availability
    MODIFY COLUMN status ENUM('Available', 'Booked', 'Expired') NOT NULL DEFAULT 'Available';
