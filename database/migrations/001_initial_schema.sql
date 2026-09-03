
-- PRODUCTION WARNING
-- Do not import this file onto a production database.
-- It DROPS existing tables and seeds a local administrator password.
-- Use database/schema.sql for a fresh production install.
--
-- Create database
CREATE DATABASE IF NOT EXISTS telehealth_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE telehealth_db;

-- Disable foreign key checks temporarily to drop existing tables in order
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables if they exist (for idempotency)
DROP TABLE IF EXISTS prescriptions;
DROP TABLE IF EXISTS consultation_records;
DROP TABLE IF EXISTS consultation_requests;
DROP TABLE IF EXISTS doctor_availability;
DROP TABLE IF EXISTS admin;
DROP TABLE IF EXISTS doctor;
DROP TABLE IF EXISTS patient;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Roles table
CREATE TABLE roles (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Users table (Superclass)
CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    INDEX idx_users_role_id (role_id),
    INDEX idx_users_email (email),
    INDEX idx_users_status (status)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Patient table (Subclass of User)
CREATE TABLE patient (
    user_id BIGINT PRIMARY KEY,
    dob DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    medical_history TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Doctor table (Subclass of User)
CREATE TABLE doctor (
    user_id BIGINT PRIMARY KEY,
    specialization VARCHAR(255),
    license_number VARCHAR(100) UNIQUE,
    clinic_address TEXT,
    consultation_fee DECIMAL(10,2),
    signature_path VARCHAR(255),
    consent_doc_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doctor_specialization (specialization)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Admin table (Subclass of User)
CREATE TABLE admin (
    user_id BIGINT PRIMARY KEY,
    employee_id VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Doctor Availability table
CREATE TABLE doctor_availability (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    doctor_id BIGINT NOT NULL,
    consultation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('Available', 'Booked', 'Expired') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctor(user_id) ON DELETE CASCADE,
    INDEX idx_doctor_availability_doctor_id (doctor_id),
    INDEX idx_doctor_availability_date (consultation_date),
    INDEX idx_doctor_availability_status (status)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Consultation Requests table
CREATE TABLE consultation_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT NOT NULL,
    doctor_id BIGINT NOT NULL,
    availability_id BIGINT,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patient(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctor(user_id) ON DELETE CASCADE,
    FOREIGN KEY (availability_id) REFERENCES doctor_availability(id) ON DELETE SET NULL,
    INDEX idx_consultation_requests_patient_id (patient_id),
    INDEX idx_consultation_requests_doctor_id (doctor_id),
    INDEX idx_consultation_requests_status (status)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Consultation Records table
CREATE TABLE consultation_records (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    consultation_request_id BIGINT NOT NULL,
    patient_id BIGINT NOT NULL,
    doctor_id BIGINT NOT NULL,
    symptoms TEXT,
    diagnosis TEXT,
    treatment_plan TEXT,
    consultation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (consultation_request_id) REFERENCES consultation_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patient(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctor(user_id) ON DELETE CASCADE,
    INDEX idx_consultation_records_request_id (consultation_request_id),
    INDEX idx_consultation_records_patient_id (patient_id),
    INDEX idx_consultation_records_doctor_id (doctor_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Prescriptions table
CREATE TABLE prescriptions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    consultation_record_id BIGINT NOT NULL,
    doctor_id BIGINT NOT NULL,
    patient_id BIGINT NOT NULL,
    medication_name VARCHAR(255) NOT NULL,
    dosage VARCHAR(255) NOT NULL,
    frequency VARCHAR(255) NOT NULL,
    duration VARCHAR(255) NOT NULL,
    additional_notes TEXT,
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (consultation_record_id) REFERENCES consultation_records(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctor(user_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patient(user_id) ON DELETE CASCADE,
    INDEX idx_prescriptions_record_id (consultation_record_id),
    INDEX idx_prescriptions_doctor_id (doctor_id),
    INDEX idx_prescriptions_patient_id (patient_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO roles (name) VALUES
('admin'),
('doctor'),
('patient');

-- Insert default admin user for LOCAL development only.
-- Password is admin123. Never use this account unchanged in production.
INSERT INTO users (role_id, full_name, email, password, status) VALUES
(1, 'System Administrator', 'admin@telehealth.local', '$2y$10$iCQxLpuu12uiBBNZWkiZi.aMA9K/VcbZsJrTANxUaamMdF5eq9PzK', 'active');

-- Insert admin subclass record
INSERT INTO admin (user_id, employee_id) VALUES
(LAST_INSERT_ID(), 'ADMIN-001');

