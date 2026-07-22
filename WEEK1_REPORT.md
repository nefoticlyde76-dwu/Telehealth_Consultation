
# Week 1 Development Report

## Project Information

- **Project Title**: MBPHA TeleHealth Consultation System
- **Client**: Milne Bay Provincial Health Authority
- **Developer**: Final Year Capstone Project Team
- **Week Number**: 1
- **Development Period**: July 21, 2026
- **Repository**: https://github.com/nefoticlyde76-dwu/Telehealth_Consultation

---

## Week 1 Objectives

The objective of Week 1 was to establish the complete foundation for the MBPHA TeleHealth Consultation System, including development environment setup, project configuration, MVC architecture, database schema, and core security and functionality components.

---

## Completed Tasks

### Task 1: Development Environment Verification
- **Objective**: Verify that all required tools (PHP, Composer, Git, XAMPP) are properly installed and configured.
- **Description**: Verified PHP version 8.2.12, Composer version 2.10.2, Git version 2.53.0, and XAMPP environment.
- **Files Created**: None (environment verification)
- **Files Modified**: None
- **Technologies Used**: PHP, Composer, Git, XAMPP
- **Outcome**: All tools verified and functioning correctly.

### Task 2: Composer Configuration
- **Objective**: Set up Composer for dependency management and PSR-4 autoloading.
- **Description**: Created composer.json with appropriate project metadata and PSR-4 autoload configuration pointing to the "app/" directory.
- **Files Created**: [composer.json](file:///c:/xampp/htdocs/Telehealth_Consultation_System/composer.json)
- **Files Modified**: None
- **Technologies Used**: Composer
- **Outcome**: Composer configured, autoloader set up.

### Task 3: Git Initialization & GitHub Repository Setup
- **Objective**: Initialize git repository and push code to GitHub.
- **Description**: Initialized git repository, created .gitignore file, added remote origin, and pushed initial commit to GitHub.
- **Files Created**: [.gitignore](file:///c:/xampp/htdocs/Telehealth_Consultation_System/.gitignore)
- **Files Modified**: None
- **Technologies Used**: Git, GitHub
- **Outcome**: Git initialized, code pushed to GitHub repository.

### Task 4: MVC Project Structure
- **Objective**: Establish a standard MVC folder structure.
- **Description**: Created directories for app/Controllers, app/Models, app/Views, app/Core, app/Config, app/Services, app/Middleware, app/Helpers, public/, database/migrations, logs/, tmp/, cache/.
- **Files Created**: Project directories (no files created here other than directory structure)
- **Files Modified**: None
- **Technologies Used**: File system operations
- **Outcome**: MVC structure established.

### Task 5: Environment Configuration
- **Objective**: Set up environment variable management.
- **Description**: Created .env and .env.example files with configuration for app, database, and session settings. Implemented Environment class to load and parse .env files.
- **Files Created**: [.env.example](file:///c:/xampp/htdocs/Telehealth_Consultation_System/.env.example), [app/Config/Environment.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Config/Environment.php), [app/Config/App.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Config/App.php), [app/Config/Database.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Config/Database.php)
- **Files Modified**: None
- **Technologies Used**: PHP
- **Outcome**: Environment variable system in place.

### Task 6: Database Creation & Schema
- **Objective**: Create database and initial schema with all tables, keys, constraints, and default admin user.
- **Description**: Created telehealth_db database, implemented tables for roles, users, patient, doctor, admin, doctor_availability, consultation_requests, consultation_records, prescriptions with primary keys, foreign keys, indexes, and constraints. Inserted default admin account.
- **Files Created**: [database/migrations/001_initial_schema.sql](file:///c:/xampp/htdocs/Telehealth_Consultation_System/database/migrations/001_initial_schema.sql)
- **Files Modified**: None
- **Technologies Used**: MySQL, SQL
- **Outcome**: Database and schema created, default admin account set up.

### Task 7: Database Connection
- **Objective**: Implement PDO-based database connection class.
- **Description**: Created Database singleton class using PDO for secure database access with prepared statements.
- **Files Created**: [app/Core/Database.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Core/Database.php)
- **Files Modified**: None
- **Technologies Used**: PHP, PDO
- **Outcome**: Database connection foundation implemented.

### Task 8: Routing Foundation
- **Objective**: Implement simple routing system.
- **Description**: Created Router class to handle GET/POST requests and map to controller actions, along with routes/web.php and public/index.php entry point.
- **Files Created**: [app/Core/Router.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Core/Router.php), [routes/web.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/routes/web.php), [public/index.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/public/index.php)
- **Files Modified**: None
- **Technologies Used**: PHP
- **Outcome**: Routing system implemented.

### Task 9: Core Framework Classes
- **Objective**: Implement base Controller, Model, and other core classes.
- **Description**: Created base Controller and Model classes.
- **Files Created**: [app/Core/Controller.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Core/Controller.php), [app/Core/Model.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Core/Model.php), [app/Controllers/HomeController.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Controllers/HomeController.php)
- **Files Modified**: None
- **Technologies Used**: PHP
- **Outcome**: Core framework classes in place.

### Task 10: Helper Classes
- **Objective**: Implement common helper functions.
- **Description**: Created Helper class with escape(), redirect(), and asset() functions.
- **Files Created**: [app/Helpers/Helper.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Helpers/Helper.php)
- **Files Modified**: None
- **Technologies Used**: PHP
- **Outcome**: Helper class created.

### Task 11: Authentication Foundation
- **Objective**: Implement basic authentication service foundation.
- **Description**: Created AuthService class with login(), logout(), isAuthenticated(), getUserId(), and getUserRole() functions.
- **Files Created**: [app/Services/AuthService.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Services/AuthService.php)
- **Files Modified**: None
- **Technologies Used**: PHP
- **Outcome**: Authentication foundation implemented.

### Task 12: Session Management Foundation
- **Objective**: Implement secure session management.
- **Description**: Created Session class with methods for session initialization, setting, getting, removing, destroying, and regenerating sessions with secure cookie settings (httponly, samesite=Strict).
- **Files Created**: [app/Core/Session.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Core/Session.php)
- **Files Modified**: None
- **Technologies Used**: PHP
- **Outcome**: Session management foundation implemented.

### Task 13: CSRF Protection Foundation
- **Objective**: Implement CSRF token generation and verification.
- **Description**: Created Csrf class with generate() and verify() methods using random_bytes() for secure token generation.
- **Files Created**: [app/Core/Csrf.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Core/Csrf.php)
- **Files Modified**: None
- **Technologies Used**: PHP
- **Outcome**: CSRF protection foundation implemented.

### Task 14: Role-Based Middleware Foundation
- **Objective**: Implement role-based access control middleware foundation.
- **Description**: Created Middleware interface and RoleMiddleware class to check user roles.
- **Files Created**: [app/Middleware/Middleware.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Middleware/Middleware.php), [app/Middleware/RoleMiddleware.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Middleware/RoleMiddleware.php)
- **Files Modified**: None
- **Technologies Used**: PHP
- **Outcome**: Role middleware foundation in place.

### Task 15: Global Error Handling Foundation
- **Objective**: Implement global error and exception handling.
- **Description**: Created ErrorHandler class that registers error, exception, and shutdown handlers, logs errors to logs/error.log, and displays debug info or user-friendly messages depending on APP_DEBUG setting.
- **Files Created**: [app/Core/ErrorHandler.php](file:///c:/xampp/htdocs/Telehealth_Consultation_System/app/Core/ErrorHandler.php)
- **Files Modified**: None
- **Technologies Used**: PHP
- **Outcome**: Global error handling foundation implemented.

---

## Project Structure

```
Telehealth_Consultation_System/
├── app/
│   ├── Config/          # Configuration classes (Environment, App, Database)
│   ├── Controllers/     # MVC Controllers
│   ├── Core/            # Core framework classes (Router, Database, Session, etc.)
│   ├── Helpers/         # Helper functions
│   ├── Middleware/      # Middleware interfaces and implementations
│   ├── Models/          # MVC Models (empty as of Week 1)
│   ├── Services/        # Business logic services (AuthService, etc.)
│   └── Views/           # MVC Views (empty as of Week 1)
├── database/
│   └── migrations/      # Database migration SQL files
├── public/              # Web server document root (contains index.php entry point)
│   ├── css/             # CSS files (empty as of Week 1)
│   ├── images/          # Image files (empty as of Week 1)
│   └── js/              # JavaScript files (empty as of Week 1)
├── routes/              # Route definitions
├── logs/                # Application log files
├── tmp/                 # Temporary files
├── cache/               # Cache files
├── vendor/              # Composer dependencies (ignored by git)
├── .env                 # Local environment configuration (ignored by git)
├── .env.example         # Example environment configuration
├── .gitignore           # Git ignore rules
└── composer.json        # Composer configuration
```

---

## Database Progress

- **Database Created**: telehealth_db
- **Tables Implemented**:
  - roles (user roles)
  - users (superclass for all system users)
  - patient (patient subclass)
  - doctor (doctor subclass)
  - admin (admin subclass)
  - doctor_availability (doctor availability slots)
  - consultation_requests (patient consultation requests)
  - consultation_records (completed consultation records)
  - prescriptions (prescriptions issued during consultations)
- **Relationships**:
  - users.role_id → roles.id (ON DELETE CASCADE)
  - patient.user_id → users.id (ON DELETE CASCADE)
  - doctor.user_id → users.id (ON DELETE CASCADE)
  - admin.user_id → users.id (ON DELETE CASCADE)
  - doctor_availability.doctor_id → doctor.user_id (ON DELETE CASCADE)
  - consultation_requests.patient_id → patient.user_id (ON DELETE CASCADE)
  - consultation_requests.doctor_id → doctor.user_id (ON DELETE CASCADE)
  - consultation_requests.availability_id → doctor_availability.id (ON DELETE SET NULL)
  - consultation_records.consultation_request_id → consultation_requests.id (ON DELETE CASCADE)
  - consultation_records.patient_id → patient.user_id (ON DELETE CASCADE)
  - consultation_records.doctor_id → doctor.user_id (ON DELETE CASCADE)
  - prescriptions.consultation_record_id → consultation_records.id (ON DELETE CASCADE)
  - prescriptions.doctor_id → doctor.user_id (ON DELETE CASCADE)
  - prescriptions.patient_id → patient.user_id (ON DELETE CASCADE)
- **Constraints**:
  - Primary keys on all tables (BIGINT AUTO_INCREMENT)
  - Unique constraints: roles.name, users.email, doctor.license_number, admin.employee_id
  - Foreign key constraints with appropriate ON DELETE rules
  - Indexes on foreign keys, status fields, email
- **Administrator Account**:
  - Email: admin@telehealth.local
  - Password: admin123
  - Role: admin

---

## Security Progress

- **PDO Prepared Statements**: Implemented in Database class to prevent SQL injection.
- **Environment Variables**: All credentials and configuration stored in .env file, not hardcoded.
- **Session Security**: Session class sets httponly and samesite=Strict cookies, provides session regeneration.
- **CSRF Protection**: Csrf class for token generation and verification.
- **Password Hashing**: Default admin account uses bcrypt hashed password (using PASSWORD_DEFAULT).
- **Output Escaping**: Helper::escape() function for escaping output to prevent XSS.

---

## Testing Performed

- **Development Environment Test**: Verified PHP, Composer, Git, and MySQL are functional.
- **Composer Autoload Test**: Ran composer install to generate autoloader.
- **Database Connection Test**: Executed migration script to create tables and default admin.
- **Git Configuration Test**: Initialized repo, committed, and pushed to GitHub.

---

## Challenges Encountered

### Challenge 1: Directory Case Sensitivity Issue
- **Problem**: The app/ directory was initially created as App/ (capitalized), which could cause issues on case-sensitive file systems (e.g., Linux) with PSR-4 autoloading.
- **Cause**: Accidental capitalization when creating the directory on Windows (case-insensitive).
- **Resolution**: Renamed directory via a temporary name (App → app-temp → app) to ensure proper casing.
- **Lessons Learned**: Always double-check directory casing, even on Windows, to maintain cross-platform compatibility.

---

## Git Summary

- **Number of Commits**: 1
- **Important Commit Messages**:
  - feat(week1): initialize MBPHA TeleHealth Consultation System foundation
- **Current Branch**: master

---

## Current Project Status

- **Week 1 Objectives**: All Week 1 objectives have been achieved.
- **Remaining Work for Week 1**: None.
- **Summary**: The project foundation is fully established with environment setup, MVC structure, database schema, core classes, and security components in place.

---

## Next Week Plan

Planned Week 2 implementation tasks include:
- Public Landing Page
- Authentication UI (Login/Register Views)
- Patient Registration
- Login Functionality
- Role-based Dashboards (Admin/Doctor/Patient)
- Navigation System

---

## Conclusion

Week 1 has been successfully completed, laying a solid, secure, and maintainable foundation for the MBPHA TeleHealth Consultation System. All planned tasks were executed as per the requirements, with particular attention to security, code quality, and adherence to best practices (SOLID, PSR-12, MVC). The project is ready to move forward with Week 2 implementation of user-facing features.
