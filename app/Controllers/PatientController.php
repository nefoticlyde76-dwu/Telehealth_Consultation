<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\AuthService;
use App\Services\PatientProfileService;

class PatientController extends Controller
{
    public function dashboard(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'patient') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        $this->render('patient/dashboard', [
            'title' => 'Patient Dashboard | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Patient Dashboard',
            'dashboardDescription' => 'Your secure home for upcoming digital care interactions.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'welcomeMessage' => 'Your account is active and ready for consultation booking, care updates, profile maintenance, and future appointment history.',
            'focusTitle' => 'Profile readiness',
            'focusDescription' => 'Keep your patient profile photo up to date so your account identity stays consistent across the MBPHA TeleHealth experience.',
            'statusMessage' => Session::getFlash('status'),
            'stats' => [
                ['label' => 'Upcoming Consultations', 'value' => '0', 'icon' => 'bi-calendar2-check', 'description' => 'Live appointment data will appear here once booking is enabled.'],
                ['label' => 'Pending Requests', 'value' => '0', 'icon' => 'bi-hourglass-split', 'description' => 'Tracks consultation requests awaiting action.'],
                ['label' => 'Messages Requiring Review', 'value' => '0', 'icon' => 'bi-chat-dots', 'description' => 'Reserved for future communication updates.'],
                ['label' => 'Account Status', 'value' => 'Active', 'icon' => 'bi-shield-check', 'description' => 'Your patient account is authenticated and available.'],
            ],
            'quickActions' => [
                ['title' => 'View My Profile', 'description' => 'Review your patient account identity and profile photo in one place.', 'icon' => 'bi-person-badge', 'status' => 'Available now', 'url' => '/patient/profile', 'action_label' => 'Open Profile'],
                ['title' => 'Change Profile Picture', 'description' => 'Crop and upload a square profile photo without leaving your dashboard workspace.', 'icon' => 'bi-camera', 'status' => 'Available now', 'url' => '/patient/profile/edit', 'action_label' => 'Edit Photo'],
                ['title' => 'Prepare For Booking', 'description' => 'This area is ready to connect to the booking workflow in the next module.', 'icon' => 'bi-journal-check', 'status' => 'Ready for integration'],
                ['title' => 'Monitor Consultation Updates', 'description' => 'Recent activity cards are structured for future appointment and consultation history.', 'icon' => 'bi-clipboard2-pulse', 'status' => 'Structured placeholder'],
            ],
            'recentActivity' => [
                ['title' => 'Dashboard access confirmed', 'description' => 'Your authenticated patient dashboard is available and role protected.', 'meta' => 'Current session'],
                ['title' => 'Profile photo workspace ready', 'description' => 'You can now crop and save a profile picture that updates your dashboard avatar after save.', 'meta' => 'Week 4 enhancement'],
                ['title' => 'Booking workflow pending', 'description' => 'Appointment booking data will populate once the patient booking module is implemented.', 'meta' => 'Prepared for Week 4'],
                ['title' => 'Consultation history placeholder', 'description' => 'Historical care records will appear here when consultation records become available.', 'meta' => 'Future module integration'],
            ],
            'emptyState' => [
                'icon' => 'bi-calendar-plus',
                'title' => 'No consultation activity yet',
                'description' => 'Once appointment booking and clinical workflows are enabled, this dashboard will display upcoming consultations and related records.',
            ],
        ], 'layouts/dashboard');
    }

    public function profile(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'patient') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $profile = PatientProfileService::getProfileDetail((int) $user->id);

        if ($profile === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'Your patient profile could not be loaded.',
            ]);
            Helper::redirect('/patient/dashboard');
            return;
        }

        $this->render('patient/profile/show', [
            'title' => 'Patient Profile | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'My Profile',
            'dashboardDescription' => 'Review your patient account identity and profile photo securely.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
                ['path' => '/patient/profile/edit', 'label' => 'Edit Profile Photo', 'icon' => 'bi-camera'],
            ],
            'statusMessage' => Session::getFlash('status'),
            'profile' => $profile,
        ], 'layouts/dashboard');
    }

    public function editProfile(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'patient') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $profile = PatientProfileService::getProfileDetail((int) $user->id);

        if ($profile === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'Your patient profile could not be loaded.',
            ]);
            Helper::redirect('/patient/dashboard');
            return;
        }

        $errors = [];
        $fieldErrors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = PatientProfileService::updateProfilePhoto((int) $user->id, $_POST, $_FILES);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Patient profile photo updated successfully.',
                ]);
                Helper::redirect('/patient/profile');
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
            $profile = $result['profile'] ?? $profile;
        }

        $this->render('patient/profile/edit', [
            'title' => 'Edit Patient Profile | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Edit Profile Photo',
            'dashboardDescription' => 'Crop and upload a professional patient profile picture securely.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
                ['path' => '/patient/profile/edit', 'label' => 'Edit Profile Photo', 'icon' => 'bi-camera'],
            ],
            'profile' => $profile,
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
            'enableImageCropper' => true,
        ], 'layouts/dashboard');
    }
}
