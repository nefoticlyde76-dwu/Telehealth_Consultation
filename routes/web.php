<?php

use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\DoctorController;
use App\Controllers\DoctorPasswordSetupController;
use App\Controllers\GoogleAuthController;
use App\Controllers\HomeController;
use App\Controllers\NotificationController;
use App\Controllers\PatientController;
use App\Middleware\RoleMiddleware;

$router->get('/', [HomeController::class, 'index']);
$router->get('/about', [HomeController::class, 'about']);
$router->get('/how-it-works', [HomeController::class, 'howItWorks']);
$router->get('/contact', [HomeController::class, 'showContact']);
$router->post('/contact', [HomeController::class, 'contact']);

$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'register']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password', [AuthController::class, 'resetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->post('/auth/google', [GoogleAuthController::class, 'authenticate']);
$router->get('/auth/google', [GoogleAuthController::class, 'methodNotAllowed']);
$router->add('PUT', '/auth/google', [GoogleAuthController::class, 'methodNotAllowed']);
$router->add('PATCH', '/auth/google', [GoogleAuthController::class, 'methodNotAllowed']);
$router->add('DELETE', '/auth/google', [GoogleAuthController::class, 'methodNotAllowed']);

$router->get('/doctor/setup-password', [DoctorPasswordSetupController::class, 'show']);
$router->post('/doctor/setup-password', [DoctorPasswordSetupController::class, 'store']);

$authenticatedRoles = [new RoleMiddleware(['admin', 'doctor', 'patient'])];
$router->get('/notifications', [NotificationController::class, 'index'], $authenticatedRoles);
$router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'], $authenticatedRoles);
$router->post('/notifications/delete-selected', [NotificationController::class, 'deleteSelected'], $authenticatedRoles);
$router->post('/notifications/clear-all', [NotificationController::class, 'clearAll'], $authenticatedRoles);
$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], $authenticatedRoles);
$router->post('/notifications/{id}/unread', [NotificationController::class, 'markUnread'], $authenticatedRoles);
$router->post('/notifications/{id}/delete', [NotificationController::class, 'delete'], $authenticatedRoles);
$router->get('/notifications/{id}', [NotificationController::class, 'open'], $authenticatedRoles);

$router->get('/account/security', [AccountController::class, 'security'], $authenticatedRoles);
$router->post('/account/password', [AccountController::class, 'changePassword'], $authenticatedRoles);
$router->post('/account/sessions/logout-others', [AccountController::class, 'logoutOtherSessions'], $authenticatedRoles);
$router->get('/account/notifications/preferences', [AccountController::class, 'notificationPreferences'], $authenticatedRoles);
$router->post('/account/notifications/preferences', [AccountController::class, 'notificationPreferences'], $authenticatedRoles);

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

$router->get('/patient/consultation-requests/{id}/download-record', [PatientController::class, 'downloadConsultationRecord'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/consultation-requests/{id}/download-prescription', [PatientController::class, 'downloadPrescription'], [
    new RoleMiddleware(['patient']),
]);

$router->get('/patient/consultation-requests/{id}/complaint-image', [PatientController::class, 'showComplaintImage'], [
    new RoleMiddleware(['patient']),
]);

$router->post('/patient/consultations/{id}/join-token', [PatientController::class, 'joinConsultation'], [
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

$router->get('/doctor/consultations/{id}/prescription', [DoctorController::class, 'showPrescription'], [
    new RoleMiddleware(['doctor']),
]);

$router->post('/doctor/consultations/{id}/prescription', [DoctorController::class, 'savePrescription'], [
    new RoleMiddleware(['doctor']),
]);

$router->post('/doctor/consultations/{id}/join-token', [DoctorController::class, 'joinConsultation'], [
    new RoleMiddleware(['doctor']),
]);

$router->post('/doctor/consultations/{id}/clinical-record', [DoctorController::class, 'saveClinicalRecordDraft'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/consultations/{id}/room', [DoctorController::class, 'showConsultationRoom'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/consultations/{id}', [DoctorController::class, 'showConsultationDetails'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/consultations/{id}/download-record', [DoctorController::class, 'downloadConsultationRecord'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/consultations/{id}/download-prescription', [DoctorController::class, 'downloadPrescription'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/consultations/{id}/complaint-image', [DoctorController::class, 'showComplaintImage'], [
    new RoleMiddleware(['doctor']),
]);

$router->get('/doctor/availability', [DoctorController::class, 'availability'], [
    new RoleMiddleware(['doctor']),
]);

$router->post('/doctor/availability/week', [DoctorController::class, 'saveWeeklyAvailability'], [
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

$router->post('/admin/users/delete-selected', [AdminController::class, 'deleteSelectedUsers'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/users/{id}', [AdminController::class, 'showUser'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/users/{id}/suspend', [AdminController::class, 'suspendUser'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/users/{id}/deactivate', [AdminController::class, 'deactivateUser'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/users/{id}/reactivate', [AdminController::class, 'reactivateUser'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/users/{id}/reset-password', [AdminController::class, 'resetUserPassword'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/users/{id}/reset-password', [AdminController::class, 'resetUserPassword'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/users/{id}/force-password-reset', [AdminController::class, 'forceUserPasswordReset'], [
    new RoleMiddleware(['admin']),
]);

$router->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/audit-logs', [AdminController::class, 'auditLogs'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/audit-logs/{id}', [AdminController::class, 'showAuditLog'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/consultation-requests', [AdminController::class, 'consultationRequests'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/consultation-requests/{id}', [AdminController::class, 'showConsultationRequest'], [
    new RoleMiddleware(['admin']),
]);

$router->get('/admin/consultation-requests/{id}/complaint-image', [AdminController::class, 'showComplaintImage'], [
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

$router->post('/admin/doctors/{id}/resend-invitation', [AdminController::class, 'resendDoctorInvitation'], [
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
