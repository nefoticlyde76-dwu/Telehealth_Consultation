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

$router->get('/doctor/dashboard', [DoctorController::class, 'dashboard'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/admin/dashboard', [AdminController::class, 'dashboard'], [
    new RoleMiddleware(['admin']),
]);
