<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\DoctorAvailabilityService;
use App\Services\AuthService;
use App\Services\DoctorConsultationService;
use App\Services\DoctorDashboardService;
use App\Services\DoctorProfileService;

class DoctorController extends Controller
{
    public function dashboard(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $dashboardData = DoctorDashboardService::getDashboardData((int) $user->id);

        $this->render('doctor/dashboard', [
            'title' => 'Doctor Dashboard | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Doctor Dashboard',
            'dashboardDescription' => 'A professional workspace for secure clinician profile and dashboard visibility.',
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-vcard'],
            ],
            'welcomeMessage' => 'Your clinician workspace is ready for secure profile management, dashboard visibility, and future-ready clinical workflows.',
            'focusTitle' => 'Clinician profile readiness',
            'focusDescription' => 'Keep your phone number, specialization, profile photo, and signature up to date for future consultation records.',
            'statusMessage' => Session::getFlash('status'),
            'stats' => $dashboardData['stats'],
            'quickActions' => $dashboardData['quickActions'],
            'recentActivity' => $dashboardData['recentActivity'],
            'emptyState' => $dashboardData['emptyState'],
            'doctorProfile' => $dashboardData['doctor'],
            'todaySummary' => $dashboardData['todaySummary'],
            'upcomingSlots' => $dashboardData['upcomingSlots'],
            'weeklySchedule' => $dashboardData['weeklySchedule'],
            'availabilitySummary' => $dashboardData['availabilitySummary'],
            'assetReadiness' => $dashboardData['assetReadiness'],
            'upcomingApprovedAppointments' => $dashboardData['upcomingApprovedAppointments'] ?? [],
            'recentApprovedAppointmentCount' => $dashboardData['recentApprovedAppointmentCount'] ?? 0,
            'upcomingApprovedAppointmentCount' => $dashboardData['upcomingApprovedAppointmentCount'] ?? 0,
        ], 'layouts/dashboard');
    }

    public function profile(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $profile = DoctorProfileService::getProfileDetail((int) $user->id);

        if ($profile === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'Your doctor profile could not be loaded.',
            ]);
            Helper::redirect('/doctor/dashboard');
            return;
        }

        $this->render('doctor/profile/show', [
            'title' => 'Doctor Profile | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'My Profile',
            'dashboardDescription' => 'Review your clinician identity, specialization, and uploaded assets securely.',
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-vcard'],
                ['path' => '/doctor/profile/edit', 'label' => 'Edit Profile', 'icon' => 'bi-person-gear'],
            ],
            'statusMessage' => Session::getFlash('status'),
            'profile' => $profile,
        ], 'layouts/dashboard');
    }

    public function editProfile(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $profile = DoctorProfileService::getProfileDetail((int) $user->id);

        if ($profile === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'Your doctor profile could not be loaded.',
            ]);
            Helper::redirect('/doctor/dashboard');
            return;
        }

        $errors = [];
        $fieldErrors = [];
        $passwordErrors = [];
        $passwordFieldErrors = [];
        $formData = DoctorProfileService::getProfileFormData($profile);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['form_action'] ?? 'profile');

            if ($action === 'password') {
                $result = DoctorProfileService::updatePassword((int) $user->id, $_POST);

                if ($result['success'] ?? false) {
                    Session::regenerate();
                    Session::flash('status', [
                        'type' => 'success',
                        'message' => $result['message'] ?? 'Doctor password updated successfully.',
                    ]);
                    Helper::redirect('/doctor/profile/edit');
                    return;
                }

                $passwordErrors = $result['errors'] ?? [];
                $passwordFieldErrors = $result['fieldErrors'] ?? [];
            } else {
                $result = DoctorProfileService::updateProfile((int) $user->id, $_POST, $_FILES);

                if ($result['success'] ?? false) {
                    Session::flash('status', [
                        'type' => 'success',
                        'message' => $result['message'] ?? 'Doctor profile updated successfully.',
                    ]);
                    Helper::redirect('/doctor/profile');
                    return;
                }

                $errors = $result['errors'] ?? [];
                $fieldErrors = $result['fieldErrors'] ?? [];
                $formData = $result['formData'] ?? $formData;
                $profile = $result['profile'] ?? $profile;
            }
        }

        $this->render('doctor/profile/edit', [
            'title' => 'Edit Doctor Profile | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Edit Profile',
            'dashboardDescription' => 'Update your phone number, specialization, profile photo, signature, and password securely.',
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-vcard'],
                ['path' => '/doctor/profile/edit', 'label' => 'Edit Profile', 'icon' => 'bi-person-gear'],
            ],
            'profile' => $profile,
            'formData' => $formData,
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'passwordErrors' => $passwordErrors,
            'passwordFieldErrors' => $passwordFieldErrors,
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
        ], 'layouts/dashboard');
    }


    public function availability(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $pageData = DoctorAvailabilityService::getAvailabilityPageData((int) $user->id, $_GET);

        $this->render('doctor/availability/index', [
            'title' => 'Doctor Availability | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Availability Scheduling',
            'dashboardDescription' => 'Create, review, update, and remove consultation availability slots securely.',
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-vcard'],
            ],
            'filters' => $pageData['filters'],
            'availability' => $pageData['availability'],
            'summary' => $pageData['summary'],
            'pagination' => $pageData['pagination'],
            'statusOptions' => $pageData['statusOptions'],
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
        ], 'layouts/dashboard');
    }

    public function createAvailability(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $errors = [];
        $fieldErrors = [];
        $formData = DoctorAvailabilityService::getAvailabilityFormData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = DoctorAvailabilityService::createAvailability((int) $user->id, $_POST);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Availability slot created successfully.',
                ]);
                Helper::redirect('/doctor/availability');
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
            $formData = $result['formData'] ?? $formData;
        }

        $this->render('doctor/availability/create', [
            'title' => 'Create Availability | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Create Availability',
            'dashboardDescription' => 'Schedule a new consultation slot with secure validation and conflict protection.',
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-vcard'],
            ],
            'formData' => $formData,
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'statusOptions' => DoctorAvailabilityService::getStatusOptions(),
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
        ], 'layouts/dashboard');
    }

    public function editAvailability(string $id): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $availabilityId = (int) $id;
        $availability = DoctorAvailabilityService::getAvailabilityDetail((int) $user->id, $availabilityId);

        if ($availability === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested availability slot could not be found.',
            ]);
            Helper::redirect('/doctor/availability');
            return;
        }

        $errors = [];
        $fieldErrors = [];
        $formData = DoctorAvailabilityService::getAvailabilityFormData($availability);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = DoctorAvailabilityService::updateAvailability((int) $user->id, $availabilityId, $_POST);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Availability slot updated successfully.',
                ]);
                Helper::redirect('/doctor/availability');
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
            $formData = $result['formData'] ?? $formData;
        }

        $this->render('doctor/availability/edit', [
            'title' => 'Edit Availability | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Edit Availability',
            'dashboardDescription' => 'Update an existing consultation slot while preserving conflict-free scheduling.',
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-vcard'],
            ],
            'availability' => $availability,
            'formData' => $formData,
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'statusOptions' => DoctorAvailabilityService::getStatusOptions(),
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
        ], 'layouts/dashboard');
    }

    public function deleteAvailability(string $id): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $result = DoctorAvailabilityService::deleteAvailability((int) $user->id, (int) $id, (string) ($_POST['_token'] ?? ''));

        Session::flash('status', [
            'type' => $result['type'] ?? (($result['success'] ?? false) ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Availability slot request completed.',
        ]);

        Helper::redirect('/doctor/availability');
    }

    public function consultations(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $pageData = DoctorConsultationService::getConsultationPageData((int) $user->id, $_GET);

        $this->render('doctor/consultations/index', [
            'title' => 'Doctor Consultations | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Consultations',
            'dashboardDescription' => 'Review approved, upcoming, and completed consultations from your clinician workspace.',
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-vcard'],
            ],
            'filters' => $pageData['filters'] ?? [],
            'consultations' => $pageData['consultations'] ?? [],
            'summary' => $pageData['summary'] ?? [],
            'pagination' => $pageData['pagination'] ?? [],
            'statusOptions' => $pageData['statusOptions'] ?? [],
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
        ], 'layouts/dashboard');
    }

    public function completeConsultation(string $id): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $result = DoctorConsultationService::completeConsultation((int) $user->id, (int) $id, (string) ($_POST['_token'] ?? ''));

        Session::flash('status', [
            'type' => $result['type'] ?? (($result['success'] ?? false) ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Consultation status update completed.',
        ]);

        Helper::redirect('/doctor/consultations');
    }
}
