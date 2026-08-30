ALTER TABLE doctor
    ADD COLUMN phone VARCHAR(30) NULL AFTER user_id,
    ADD COLUMN gender ENUM('male', 'female', 'other') NULL AFTER phone,
    ADD COLUMN professional_title VARCHAR(150) NULL AFTER gender,
    ADD COLUMN employee_id VARCHAR(100) NULL UNIQUE AFTER specialization;
