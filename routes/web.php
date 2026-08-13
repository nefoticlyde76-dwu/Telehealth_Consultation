<?php

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\DoctorController;
use App\Controllers\HomeController;
use App\Controllers\PatientController;
use App\Middleware\RoleMiddleware;

$router->get('/', [HomeController::class, 'index']);
$router->post('/contact', [HomeController::class, 'contact']);

$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'register']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/patient/dashboard', [PatientController::class, 'dashboard'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/profile', [PatientController::class, 'profile'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/profile/edit', [PatientController::class, 'editProfile'], [
    new RoleMiddleware(['patient']),
]);

$router->post('/patient/profile/edit', [PatientController::class, 'editProfile'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/doctors', [PatientController::class, 'doctors'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/available-slots', [PatientController::class, 'availableSlots'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/consultation-requests', [PatientController::class, 'consultationHistory'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/consultation-requests/{id}', [PatientController::class, 'showConsultationRequest'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/consultations/{id}/join-token', [PatientController::class, 'joinConsultation'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/consultations/{id}/room', [PatientController::class, 'showConsultationRoom'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/consultation-requests/book/{id}', [PatientController::class, 'bookConsultation'], [
    new RoleMiddleware(['patient']),
]);

$router->post('/patient/consultation-requests/book/{id}', [PatientController::class, 'bookConsultation'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/doctor/dashboard', [DoctorController::class, 'dashboard'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/consultations', [DoctorController::class, 'consultations'], [
    new RoleMiddleware(['doctor']),
]);

$router->post('/doctor/consultations/{id}/complete', [DoctorController::class, 'completeConsultation'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/consultations/{id}/join-token', [DoctorController::class, 'joinConsultation'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/consultations/{id}/room', [DoctorController::class, 'showConsultationRoom'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/availability', [DoctorController::class, 'availability'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/availability/create', [DoctorController::class, 'createAvailability'], [
    new RoleMiddleware(['doctor']),
]);

$router->post('/doctor/availability/create', [DoctorController::class, 'createAvailability'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/availability/{id}/edit', [DoctorController::class, 'editAvailability'], [
    new RoleMiddleware(['doctor']),
]);

$router->post('/doctor/availability/{id}/edit', [DoctorController::class, 'editAvailability'], [
    new RoleMiddleware(['doctor']),
]);

$router->post('/doctor/availability/{id}/delete', [DoctorController::class, 'deleteAvailability'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/profile', [DoctorController::class, 'profile'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/profile/edit', [DoctorController::class, 'editProfile'], [
    new RoleMiddleware(['doctor']),
]);

$router->post('/doctor/profile/edit', [DoctorController::class, 'editProfile'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/admin/dashboard', [AdminController::class, 'dashboard'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/users', [AdminController::class, 'users'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/users/{id}', [AdminController::class, 'showUser'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/consultation-requests', [AdminController::class, 'consultationRequests'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/consultation-requests/{id}', [AdminController::class, 'showConsultationRequest'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/consultation-requests/{id}/approve', [AdminController::class, 'approveConsultationRequest'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/consultation-requests/{id}/reject', [AdminController::class, 'rejectConsultationRequest'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/consultation-requests/{id}/cancel', [AdminController::class, 'cancelConsultationRequest'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/patients', [AdminController::class, 'patients'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/patients/{id}/edit', [AdminController::class, 'editPatient'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/patients/{id}/edit', [AdminController::class, 'editPatient'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/patients/{id}/activate', [AdminController::class, 'activatePatient'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/patients/{id}/deactivate', [AdminController::class, 'deactivatePatient'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/doctors', [AdminController::class, 'doctors'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/doctors/create', [AdminController::class, 'createDoctor'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/doctors/create', [AdminController::class, 'createDoctor'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/doctors/{id}/edit', [AdminController::class, 'editDoctor'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/doctors/{id}/edit', [AdminController::class, 'editDoctor'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/doctors/{id}/activate', [AdminController::class, 'activateDoctor'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/doctors/{id}/deactivate', [AdminController::class, 'deactivateDoctor'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/doctors/{id}/reset-password', [AdminController::class, 'resetDoctorPassword'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/doctors/{id}/reset-password', [AdminController::class, 'resetDoctorPassword'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/profile', [AdminController::class, 'profile'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/profile', [AdminController::class, 'profile'], [
    new RoleMiddleware(['admin']),
]);
