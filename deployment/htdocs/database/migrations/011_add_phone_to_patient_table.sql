-- Add a telephone / mobile number column to the patient table (mirrors doctor.phone
-- from migration 003_add_doctor_account_management_fields.sql).
--
-- Email was already stored on the users superclass (users.email NOT NULL UNIQUE)
-- and is retrieved via the patient.user_id foreign key, so we intentionally do
-- NOT duplicate the patient's email here.
--
-- The patient table previously only contained dob, gender, address, medical_history,
-- profile_photo_path; this migration adds phone to complete the minimum patient
-- contact demographics used in the consultation-room sidebar.

ALTER TABLE patient
    ADD COLUMN phone VARCHAR(30) NULL AFTER user_id,
    ADD INDEX idx_patient_phone (phone);
