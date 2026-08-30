<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Helpers\Status;
use App\Models\ConsultationRequest;
use App\Services\AccountSecurityService;
use App\Services\AdminConsultationService;
use App\Services\AdminDoctorService;
use App\Services\AdminPatientService;
use App\Services\AdminProfileService;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\AdminUserService;
use App\Services\NotificationService;

class AdminController extends Controller
{
    public function dashboard(): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $dashboardData = AdminUserService::getDashboardData();
        $summary = $dashboardData['summary'];
        $doctorSummary = AdminDoctorService::getDashboardSummary();
        $patientSummary = AdminPatientService::getDashboardSummary();
        $headerNotifications = NotificationService::getHeaderData((int) $user->id, 'admin');
        $consultationDashboard = AdminConsultationService::getDashboardSummary(
            (int) ($headerNotifications['unread_count'] ?? 0)
        );
        $recentAudit = AuditLogService::getDashboardRecent(5);

        $this->render('admin/dashboard', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Administrator Dashboard | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Administrator Dashboard',
                'dashboardDescription' => 'Operational visibility for secure platform administration.',
                'topbarSearchPlaceholder' => 'Search users, consultations, or doctors',
                'showRightbar' => true,
            ]),
            [
                'welcomeMessage' => 'Review consultation requests, manage doctor and patient accounts, and keep the service operating.',
                'focusTitle' => 'What needs attention',
                'focusDescription' => 'Start with pending consultation requests, then review doctor and patient accounts as needed.',
                'stats' => $consultationDashboard['stats'] ?? $dashboardData['stats'],
                'quickActions' => $dashboardData['quickActions'],
                'recentActivity' => $consultationDashboard['recentActivity'] ?? $dashboardData['recentActivity'],
                'statusMessage' => Session::getFlash('status'),
                'emptyState' => [
                    'icon' => 'bi-people',
                    'title' => 'Administration tools are ready',
                    'description' => 'Use Users, Doctors, Patients, and Consultation Requests to manage the service.',
                ],
                'userSummary' => $summary,
                'doctorSummary' => $doctorSummary,
                'patientSummary' => $patientSummary,
                'consultationSummary' => $consultationDashboard['summary'] ?? [],
                'recentConsultationRequests' => $consultationDashboard['recentRequests'] ?? [],
                'latestUsers' => $dashboardData['latestUsers'],
                'charts' => $consultationDashboard['charts'] ?? [],
                'headerNotifications' => $headerNotifications,
                'recentNotifications' => array_slice($headerNotifications['recent'] ?? [], 0, 5),
                'recentAudit' => $recentAudit,
                'rightbar' => [
                    'calendarTitle' => 'Upcoming Appointments',
                    'calendarEvents' => \App\Helpers\DashboardCalendar::eventsForDashboard('admin', (int) $user->id),
                    'showUpcomingList' => true,
                    'upcomingTitle' => 'Recent Audit Activity',
                    'upcomingItems' => array_map(static function (array $activity): array {
                        return [
                            'icon' => $activity['icon'] ?? 'bi-journal-text',
                            'title' => (string) ($activity['title'] ?? ''),
                            'meta' => (string) ($activity['meta'] ?? ''),
                        ];
                    }, $recentAudit),
                    'quickActions' => [
                        ['label' => 'Review Pending Requests', 'url' => \App\Helpers\Status::filteredListUrl('/admin/consultation-requests', \App\Helpers\Status::PENDING), 'icon' => 'bi-clipboard2-check'],
                        ['label' => 'Consultation Queue', 'url' => '/admin/consultation-requests', 'icon' => 'bi-list-check'],
                        ['label' => 'View Audit Activity', 'url' => '/admin/audit-logs', 'icon' => 'bi-journal-text'],
                    ],
                    'summaryStats' => [
                        ['label' => 'Pending', 'value' => (string) ((int) ($consultationDashboard['summary']['pending_requests'] ?? 0))],
                        ['label' => 'Approved', 'value' => (string) ((int) ($consultationDashboard['summary']['approved_requests'] ?? 0))],
                        ['label' => 'Completed', 'value' => (string) ((int) ($consultationDashboard['summary']['completed_requests'] ?? 0))],
                    ],
                ],
            ]
        ), 'layouts/dashboard');
    }

    public function users(): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $pageData = AdminUserService::getUserManagementPageData($_GET);

        $this->render('admin/users/index', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'User Management | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Users',
                'dashboardDescription' => 'Review, search, and filter registered platform accounts securely.',
            ]),
            [
                'filters' => $pageData['filters'],
                'users' => $pageData['users'],
                'summary' => $pageData['summary'],
                'pagination' => $pageData['pagination'],
                'roleOptions' => $pageData['roleOptions'],
                'statusOptions' => $pageData['statusOptions'],
                'sortOptions' => $pageData['sortOptions'] ?? [],
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
                'actorUserId' => (int) $user->id,
            ]
        ), 'layouts/dashboard');
    }

    public function auditLogs(): void
    {
        $user = $this->requireAdminUser();
        if ($user === null) {
            return;
        }

        $pageData = AuditLogService::getPageData($_GET);
        $this->render('admin/audit_logs/index', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Activity & Audit Logs | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Activity & Audit Logs',
                'dashboardDescription' => 'Centralized activity records for accountability and troubleshooting.',
            ]),
            [
                'filters' => $pageData['filters'] ?? [],
                'logs' => $pageData['logs'] ?? [],
                'pagination' => $pageData['pagination'] ?? [],
                'filterActive' => (bool) ($pageData['filterActive'] ?? false),
                'actionOptions' => $pageData['actionOptions'] ?? [],
                'roleOptions' => $pageData['roleOptions'] ?? [],
                'dateOptions' => $pageData['dateOptions'] ?? [],
                'sortOptions' => $pageData['sortOptions'] ?? [],
                'userOptions' => $pageData['userOptions'] ?? [],
                'statusMessage' => Session::getFlash('status'),
            ]
        ), 'layouts/dashboard');
    }

    public function showAuditLog(string $id): void
    {
        $user = $this->requireAdminUser();
        if ($user === null) {
            return;
        }

        $auditId = (int) $id;
        $audit = AuditLogService::findDetailForAdmin($auditId);
        if ($audit === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested audit event could not be found.',
            ]);
            Helper::redirect('/admin/audit-logs');
            return;
        }

        $this->render('admin/audit_logs/show', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Audit Event Details | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Audit Event Details',
                'dashboardDescription' => 'Review event metadata and accountability information.',
            ]),
            [
                'audit' => $audit,
            ]
        ), 'layouts/dashboard');
    }

    public function doctors(): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $pageData = AdminDoctorService::getDoctorManagementPageData($_GET);

        $this->render('admin/doctors/index', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Doctor Account Management | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Doctors',
                'dashboardDescription' => 'Create and manage secure clinician accounts for the platform.',
            ]),
            [
                'filters' => $pageData['filters'],
                'doctors' => $pageData['doctors'],
                'summary' => $pageData['summary'],
                'pagination' => $pageData['pagination'],
                'statusOptions' => $pageData['statusOptions'],
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function patients(): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $pageData = AdminPatientService::getPatientManagementPageData($_GET);

        $this->render('admin/patients/index', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Patient Management | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Patients',
                'dashboardDescription' => 'Review, search, update, and manage secure patient accounts.',
            ]),
            [
                'filters' => $pageData['filters'],
                'patients' => $pageData['patients'],
                'summary' => $pageData['summary'],
                'pagination' => $pageData['pagination'],
                'statusOptions' => $pageData['statusOptions'],
                'genderOptions' => $pageData['genderOptions'],
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function editPatient(string $id): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $userId = (int) $id;
        $patient = AdminPatientService::getPatientDetail($userId);

        if ($patient === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested patient account could not be found.',
            ]);
            Helper::redirect('/admin/patients');
            return;
        }

        $errors = [];
        $fieldErrors = [];
        $formData = AdminPatientService::getPatientFormData($patient);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = AdminPatientService::updatePatientAccount($userId, $_POST);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Patient account updated successfully.',
                ]);
                Helper::redirect('/admin/patients/' . $userId . '/edit');
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
            $formData = $result['formData'] ?? $formData;
        }

        $this->render('admin/patients/edit', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Edit Patient Account | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Edit Patient Account',
                'dashboardDescription' => 'Update patient identity, profile information, and access status securely.',
            ]),
            [
                'patient' => $patient,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
                'genderOptions' => AdminPatientService::getGenderOptions(),
                'statusOptions' => AdminPatientService::getStatusOptions(),
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function activatePatient(string $id): void
    {
        $this->handlePatientStatusUpdate((int) $id, 'active');
    }

    public function deactivatePatient(string $id): void
    {
        $this->handlePatientStatusUpdate((int) $id, 'inactive');
    }

    public function profile(): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $profile = AdminProfileService::getProfileDetail((int) $user->id);

        if ($profile === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The administrator profile could not be loaded.',
            ]);
            Helper::redirect('/admin/dashboard');
            return;
        }

        $errors = [];
        $fieldErrors = [];
        $passwordErrors = [];
        $passwordFieldErrors = [];
        $formData = AdminProfileService::getProfileFormData($profile);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['form_action'] ?? 'profile');

            if ($action === 'password') {
                $result = AdminProfileService::updatePassword((int) $user->id, $_POST);

                if ($result['success'] ?? false) {
                    Session::regenerate();
                    Session::flash('status', [
                        'type' => 'success',
                        'message' => $result['message'] ?? 'Administrator password updated successfully.',
                    ]);
                    Helper::redirect('/admin/profile');
                    return;
                }

                $passwordErrors = $result['errors'] ?? [];
                $passwordFieldErrors = $result['fieldErrors'] ?? [];
            } else {
                $result = AdminProfileService::updateProfile((int) $user->id, $_POST, $_FILES);

                if ($result['success'] ?? false) {
                    Session::flash('status', [
                        'type' => 'success',
                        'message' => $result['message'] ?? 'Administrator profile updated successfully.',
                    ]);
                    Helper::redirect('/admin/profile');
                    return;
                }

                $errors = $result['errors'] ?? [];
                $fieldErrors = $result['fieldErrors'] ?? [];
                $formData = $result['formData'] ?? $formData;
            }
        }

        $this->render('admin/profile/edit', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Administrator Profile | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Administrator Profile',
                'dashboardDescription' => 'Maintain your administrator identity, employee details, and password securely.',
            ]),
            [
                'profile' => $profile,
                'formData' => $formData,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'passwordErrors' => $passwordErrors,
                'passwordFieldErrors' => $passwordFieldErrors,
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function createDoctor(): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $errors = [];
        $fieldErrors = [];
        $formData = AdminDoctorService::getDoctorFormData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = AdminDoctorService::createDoctorAccount($_POST);

            if (($result['accountCreated'] ?? false) === true || ($result['success'] ?? false) === true) {
                $invitationSent = (bool) ($result['invitationSent'] ?? false);
                Session::flash('status', [
                    'type' => $invitationSent ? 'success' : 'warning',
                    'message' => $result['message'] ?? (
                        $invitationSent
                            ? 'Doctor account created successfully. A password setup invitation has been sent to the doctor\'s email.'
                            : 'Doctor account was created, but the invitation email could not be sent. Please use Resend Invitation.'
                    ),
                ]);
                Helper::redirect('/admin/doctors');
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
            $formData = $result['formData'] ?? $formData;
        }

        $this->render('admin/doctors/create', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Create Doctor Account | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Create Doctor Account',
                'dashboardDescription' => 'Register a new clinician profile. The doctor will receive an email invitation to create their own password.',
            ]),
            [
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
                'genderOptions' => AdminDoctorService::getGenderOptions(),
                'statusOptions' => AdminDoctorService::getStatusOptions(),
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function editDoctor(string $id): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $userId = (int) $id;
        $doctor = AdminDoctorService::getDoctorDetail($userId);

        if ($doctor === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested doctor account could not be found.',
            ]);
            Helper::redirect('/admin/doctors');
            return;
        }

        $errors = [];
        $fieldErrors = [];
        $formData = AdminDoctorService::getDoctorFormData($doctor);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = AdminDoctorService::updateDoctorAccount($userId, $_POST);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Doctor account updated successfully.',
                ]);
                Helper::redirect('/admin/doctors/' . $userId . '/edit');
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
            $formData = $result['formData'] ?? $formData;
        }

        $this->render('admin/doctors/edit', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Edit Doctor Account | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Edit Doctor Account',
                'dashboardDescription' => 'Update clinician account identity, profile, and access status securely.',
            ]),
            [
                'doctor' => $doctor,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
                'genderOptions' => AdminDoctorService::getGenderOptions(),
                'statusOptions' => AdminDoctorService::getStatusOptions(),
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function activateDoctor(string $id): void
    {
        $this->handleDoctorStatusUpdate((int) $id, 'active');
    }

    public function deactivateDoctor(string $id): void
    {
        $this->handleDoctorStatusUpdate((int) $id, 'inactive');
    }

    public function resendDoctorInvitation(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/admin/doctors');
            return;
        }

        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $result = AdminDoctorService::resendDoctorInvitation((int) $id, (string) ($_POST['_token'] ?? ''));

        Session::flash('status', [
            'type' => $result['type'] ?? (($result['success'] ?? false) ? 'success' : 'warning'),
            'message' => $result['message'] ?? 'The invitation could not be resent. Please verify that the doctor is still pending.',
        ]);

        Helper::redirect('/admin/doctors');
    }

    public function resetDoctorPassword(string $id): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $userId = (int) $id;
        $doctor = AdminDoctorService::getDoctorDetail($userId);

        if ($doctor === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested doctor account could not be found.',
            ]);
            Helper::redirect('/admin/doctors');
            return;
        }

        if (Status::isInvitationPendingUserStatus((string) ($doctor['status'] ?? ''))) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'A password cannot be reset for a doctor whose invitation is still pending.',
            ]);
            Helper::redirect('/admin/doctors');
            return;
        }

        $errors = [];
        $fieldErrors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = AdminDoctorService::resetDoctorPassword($userId, $_POST);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Doctor password reset successfully.',
                ]);
                Helper::redirect('/admin/doctors');
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
            $doctor = $result['doctor'] ?? $doctor;
        }

        $this->render('admin/doctors/reset_password', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Reset Doctor Password | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Reset Doctor Password',
                'dashboardDescription' => 'Issue a secure new password for a clinician account.',
            ]),
            [
                'doctor' => $doctor,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function showUser(string $id): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $userId = (int) $id;
        $managedUser = AdminUserService::getUserDetail($userId, (int) $user->id);

        if ($managedUser === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested user record could not be found.',
            ]);
            Helper::redirect('/admin/users');
            return;
        }

        $this->render('admin/users/show', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'User Details | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'User Details',
                'dashboardDescription' => 'Review role, status, and non-clinical account details.',
            ]),
            [
                'managedUser' => $managedUser,
                'csrfToken' => Csrf::generate(),
                'actorUserId' => (int) $user->id,
                'statusMessage' => Session::getFlash('status'),
                'confirmationPhrase' => AccountSecurityService::CONFIRMATION_PHRASE,
            ]
        ), 'layouts/dashboard');
    }

    public function suspendUser(string $id): void
    {
        $this->handleUserStatusChange((int) $id, \App\Helpers\Status::USER_SUSPENDED);
    }

    public function deactivateUser(string $id): void
    {
        $this->handleUserStatusChange((int) $id, \App\Helpers\Status::USER_INACTIVE);
    }

    public function reactivateUser(string $id): void
    {
        $this->handleUserStatusChange((int) $id, \App\Helpers\Status::USER_ACTIVE);
    }

    public function forceUserPasswordReset(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/admin/users');
            return;
        }

        $user = $this->requireAdminUser();
        if ($user === null || $user->id === null) {
            return;
        }

        $result = AdminUserService::forcePasswordReset(
            (int) $id,
            (int) $user->id,
            (string) ($_POST['_token'] ?? '')
        );

        Session::flash('status', [
            'type' => $result['type'] ?? 'danger',
            'message' => $result['message'] ?? 'Unable to require a password reset.',
        ]);
        Helper::redirect('/admin/users/' . (int) $id);
    }

    public function resetUserPassword(string $id): void
    {
        $user = $this->requireAdminUser();
        if ($user === null || $user->id === null) {
            return;
        }

        $userId = (int) $id;
        $managedUser = AdminUserService::getUserDetail($userId, (int) $user->id);
        if ($managedUser === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested user record could not be found.',
            ]);
            Helper::redirect('/admin/users');
            return;
        }

        $errors = [];
        $fieldErrors = [];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $result = AdminUserService::resetUserPassword($userId, (int) $user->id, $_POST);
            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Password reset successfully.',
                ]);
                Helper::redirect('/admin/users/' . $userId);
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
        }

        $this->render('admin/users/reset_password', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Reset User Password | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Reset Password',
                'dashboardDescription' => 'Issue a secure replacement password for this account.',
            ]),
            [
                'managedUser' => $managedUser,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function deleteUser(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/admin/users');
            return;
        }

        $user = $this->requireAdminUser();

        if ($user === null || $user->id === null) {
            return;
        }

        $userId = (int) $id;
        $result = AdminUserService::deleteUserAccount(
            $userId,
            (int) $user->id,
            (string) ($_POST['_token'] ?? ''),
            (string) ($_POST['confirmation_phrase'] ?? ''),
            (string) ($_POST['admin_password'] ?? '')
        );

        Session::flash('status', [
            'type' => $result['type'] ?? ($result['success'] ?? false ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'User account deletion completed.',
        ]);

        Helper::redirect('/admin/users');
    }

    public function deleteSelectedUsers(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/admin/users');
            return;
        }

        $user = $this->requireAdminUser();
        if ($user === null || $user->id === null) {
            return;
        }

        $ids = $_POST['user_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        $result = AdminUserService::deleteSelectedUserAccounts(
            $ids,
            (int) $user->id,
            (string) ($_POST['_token'] ?? ''),
            (string) ($_POST['confirmation_phrase'] ?? ''),
            (string) ($_POST['admin_password'] ?? '')
        );

        Session::flash('status', [
            'type' => $result['type'] ?? ($result['success'] ?? false ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Selected user accounts could not be deleted.',
        ]);

        Helper::redirect('/admin/users');
    }

    private function handleUserStatusChange(int $userId, string $status): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/admin/users');
            return;
        }

        $user = $this->requireAdminUser();
        if ($user === null || $user->id === null) {
            return;
        }

        $result = AdminUserService::changeAccountStatus(
            $userId,
            (int) $user->id,
            $status,
            (string) ($_POST['_token'] ?? '')
        );

        Session::flash('status', [
            'type' => $result['type'] ?? 'danger',
            'message' => $result['message'] ?? 'Account status could not be updated.',
        ]);

        $returnTo = Helper::safeInternalPath((string) ($_POST['return_to'] ?? ''), '/admin/users/' . $userId);
        Helper::redirect($returnTo);
    }

    private function requireAdminUser(): ?\App\Models\User
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'admin') {
            Helper::redirect('/login');
            return null;
        }

        $user = AuthService::getUser();

        if ($user === null) {
            Helper::redirect('/login');
            return null;
        }

        return $user;
    }

    private function getAdminViewData(\App\Models\User $user, array $overrides = []): array
    {
        return array_merge([
            'user' => $user,
            'dashboardRole' => 'admin',
            'dashboardRoleLabel' => 'Administrator',
            'sidebarItems' => [
                ['path' => '/admin/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/admin/users', 'label' => 'Users', 'icon' => 'bi-people-fill'],
                ['path' => '/admin/doctors', 'label' => 'Doctors', 'icon' => 'bi-person-badge-fill'],
                ['path' => '/admin/patients', 'label' => 'Patients', 'icon' => 'bi-people'],
                ['path' => '/admin/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-check'],
                ['path' => '/admin/audit-logs', 'label' => 'Audit Logs', 'icon' => 'bi-journal-text'],
                ['path' => '/admin/profile', 'label' => 'Settings', 'icon' => 'bi-gear'],
            ],
            'sidebarStatusTitle' => 'Consultation oversight',
            'sidebarStatusDescription' => 'Approve, reject, or cancel consultation booking requests.',
        ], $overrides);
    }

    public function consultationRequests(): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $pageData = AdminConsultationService::getManagementPageData($_GET);
        $selectedRequest = $pageData['selectedRequest'] ?? null;
        $selectedId = (int) ($pageData['selectedId'] ?? 0);

        $this->render('admin/consultation_requests/index', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Consultation Requests | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Consultation Requests',
                'dashboardDescription' => 'Review pending consultation requests and decide in order.',
            ]),
            [
                'filters' => $pageData['filters'] ?? [],
                'requests' => $pageData['requests'] ?? [],
                'selectedId' => $selectedId,
                'selectedRequest' => $selectedRequest,
                'queuePosition' => (int) ($pageData['queuePosition'] ?? 0),
                'summary' => $pageData['summary'] ?? [],
                'pagination' => $pageData['pagination'] ?? [],
                'statusOptions' => $pageData['statusOptions'] ?? [],
                'doctorOptions' => $pageData['doctorOptions'] ?? [],
                'dateOptions' => $pageData['dateOptions'] ?? [],
                'sortOptions' => $pageData['sortOptions'] ?? [],
                'filterActive' => (bool) ($pageData['filterActive'] ?? false),
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function showConsultationRequest(string $id): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $requestId = (int) $id;
        $request = AdminConsultationService::getConsultationDetail($requestId);

        if ($request === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested consultation request could not be found.',
            ]);
            Helper::redirect('/admin/consultation-requests');
            return;
        }

        $this->render('admin/consultation_requests/show', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Consultation Request Details | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Consultation Request Details',
                'dashboardDescription' => 'Review patient, doctor, slot details, and approve or reject securely.',
            ]),
            [
                'request'           => $request,
                'statusMessage'     => Session::getFlash('status'),
                'csrfToken'         => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function approveConsultationRequest(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/admin/consultation-requests');
            return;
        }

        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $requestId = (int) $id;
        $workspace = ((string) ($_POST['workspace'] ?? '')) === '1';
        $advance = ((string) ($_POST['advance'] ?? '')) === '1';
        $filters = $workspace ? AdminConsultationService::normalizeFilters($_POST) : [];
        $queueIds = $workspace ? ConsultationRequest::findQueueIdsForAdmin($filters) : [];

        $result = AdminConsultationService::approveRequest($requestId, (string) ($_POST['_token'] ?? ''));

        Session::flash('status', [
            'type' => $result['type'] ?? ($result['success'] ?? false ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Consultation request approval completed.',
        ]);

        $this->redirectAfterConsultationDecision($requestId, $result, $workspace, $advance, $filters, $queueIds);
    }

    public function rejectConsultationRequest(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/admin/consultation-requests');
            return;
        }

        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $requestId = (int) $id;
        $workspace = ((string) ($_POST['workspace'] ?? '')) === '1';
        $advance = ((string) ($_POST['advance'] ?? '')) === '1';
        $filters = $workspace ? AdminConsultationService::normalizeFilters($_POST) : [];
        $queueIds = $workspace ? ConsultationRequest::findQueueIdsForAdmin($filters) : [];

        $result = AdminConsultationService::rejectRequest($requestId, (string) ($_POST['_token'] ?? ''));

        Session::flash('status', [
            'type' => $result['type'] ?? ($result['success'] ?? false ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Consultation request rejection completed.',
        ]);

        $this->redirectAfterConsultationDecision($requestId, $result, $workspace, $advance, $filters, $queueIds);
    }

    public function cancelConsultationRequest(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/admin/consultation-requests');
            return;
        }

        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $requestId = (int) $id;
        $result = AdminConsultationService::cancelRequest($requestId, (string) ($_POST['_token'] ?? ''));

        Session::flash('status', [
            'type' => $result['type'] ?? ($result['success'] ?? false ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Consultation request cancellation completed.',
        ]);

        Helper::redirect('/admin/consultation-requests/' . $requestId);
    }

    /**
     * After approve/reject, stay on the request detail page unless the
     * decision was made from the review workspace.
     *
     * @param array{success?:bool,type?:string,message?:string} $result
     * @param array<string, mixed> $filters
     * @param list<int> $queueIds
     */
    private function redirectAfterConsultationDecision(
        int $requestId,
        array $result,
        bool $workspace,
        bool $advance,
        array $filters,
        array $queueIds
    ): void {
        if (!$workspace) {
            Helper::redirect('/admin/consultation-requests/' . $requestId);
            return;
        }

        $selectedId = $requestId;
        $page = max(1, (int) ($_POST['page'] ?? 1));
        if (($result['success'] ?? false) === true && $advance) {
            $selectedId = (int) (AdminConsultationService::nextQueueRequestId($queueIds, $requestId) ?? 0);
            $page = 1;
        }

        Helper::redirect(AdminConsultationService::workspacePath(
            $filters,
            $selectedId > 0 ? $selectedId : null,
            $page
        ));
    }

    private function handleDoctorStatusUpdate(int $userId, string $status): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/admin/doctors');
            return;
        }

        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $result = AdminDoctorService::updateDoctorStatus($userId, $status, (string) ($_POST['_token'] ?? ''));

        Session::flash('status', [
            'type' => $result['type'] ?? ($result['success'] ?? false ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Doctor account status update completed.',
        ]);

        Helper::redirect('/admin/doctors');
    }

    private function handlePatientStatusUpdate(int $userId, string $status): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/admin/patients');
            return;
        }

        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $result = AdminPatientService::updatePatientStatus($userId, $status, (string) ($_POST['_token'] ?? ''));

        Session::flash('status', [
            'type' => $result['type'] ?? ($result['success'] ?? false ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Patient account status update completed.',
        ]);

        Helper::redirect('/admin/patients');
    }
}
