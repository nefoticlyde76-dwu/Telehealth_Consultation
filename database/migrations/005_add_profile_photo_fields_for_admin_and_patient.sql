ALTER TABLE admin
    ADD COLUMN profile_photo_path VARCHAR(255) NULL AFTER employee_id;

ALTER TABLE patient
    ADD COLUMN profile_photo_path VARCHAR(255) NULL AFTER medical_history;
