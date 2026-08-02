<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\AuthService;
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
            'enableImageCropper' => true,
        ], 'layouts/dashboard');
    }
}
