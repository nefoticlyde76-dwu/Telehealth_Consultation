<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\AdminDoctorService;
use App\Services\AdminPatientService;
use App\Services\AdminProfileService;
use App\Services\AuthService;
use App\Services\AdminUserService;

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

        $this->render('admin/dashboard', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Administrator Dashboard | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Administrator Dashboard',
                'dashboardDescription' => 'Operational visibility for secure platform administration.',
            ]),
            [
                'welcomeMessage' => 'This administrator workspace now supports patient oversight, doctor account provisioning, and administrator profile management.',
                'focusTitle' => 'Account governance controls',
                'focusDescription' => 'Administrators can now manage patient accounts, doctor onboarding, password controls, and profile maintenance from one coordinated workspace.',
                'stats' => $dashboardData['stats'],
                'quickActions' => $dashboardData['quickActions'],
                'recentActivity' => $dashboardData['recentActivity'],
                'statusMessage' => Session::getFlash('status'),
                'emptyState' => [
                    'icon' => 'bi-people',
                    'title' => 'Administrative governance tools are now available',
                    'description' => 'Use the patient management, doctor account, user management, and profile pages to apply role-aware governance securely.',
                ],
                'userSummary' => $summary,
                'doctorSummary' => $doctorSummary,
                'patientSummary' => $patientSummary,
                'latestUsers' => $dashboardData['latestUsers'],
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
                'dashboardTitle' => 'User Management',
                'dashboardDescription' => 'Review, search, and filter registered platform accounts securely.',
            ]),
            [
                'filters' => $pageData['filters'],
                'users' => $pageData['users'],
                'summary' => $pageData['summary'],
                'pagination' => $pageData['pagination'],
                'roleOptions' => $pageData['roleOptions'],
                'statusOptions' => $pageData['statusOptions'],
                'statusMessage' => Session::getFlash('status'),
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
                'dashboardTitle' => 'Doctor Account Management',
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
                'dashboardTitle' => 'Patient Management',
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

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Doctor account created successfully.',
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
                'dashboardDescription' => 'Register a new clinician account with secure access managed by the administrator.',
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
        $managedUser = AdminUserService::getUserDetail($userId);

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
                'dashboardDescription' => 'Review role, status, and account profile data for a specific user.',
            ]),
            [
                'managedUser' => $managedUser,
            ]
        ), 'layouts/dashboard');
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
            'dashboardRoleLabel' => 'Administrator Dashboard',
            'sidebarItems' => [
                ['path' => '/admin/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/admin/patients', 'label' => 'Patient Management', 'icon' => 'bi-people-fill'],
                ['path' => '/admin/doctors', 'label' => 'Doctor Accounts', 'icon' => 'bi-person-badge-fill'],
                ['path' => '/admin/users', 'label' => 'User Management', 'icon' => 'bi-people-fill'],
                ['path' => '/admin/profile', 'label' => 'Profile Settings', 'icon' => 'bi-person-gear'],
            ],
            'sidebarStatusTitle' => 'Week 3 Account Governance',
            'sidebarStatusDescription' => 'Patient oversight, doctor onboarding, and administrator profile management are now active.',
        ], $overrides);
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
