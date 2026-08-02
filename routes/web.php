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

$router->get('/doctor/dashboard', [DoctorController::class, 'dashboard'], [
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
