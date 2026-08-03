<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\AuthService;
use App\Services\PatientDirectoryService;
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
        $browseSummary = PatientDirectoryService::getBrowseSummary();
        $featuredDoctors = PatientDirectoryService::getFeaturedDoctors(3);
        $slotPreview = PatientDirectoryService::getUpcomingSlotPreview(4);

        $this->render('patient/dashboard', [
            'title' => 'Patient Dashboard | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Patient Dashboard',
            'dashboardDescription' => 'Your secure home for upcoming digital care interactions.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'welcomeMessage' => 'Browse active clinicians, review upcoming availability, and prepare for patient booking without leaving your secure dashboard.',
            'focusTitle' => 'Browse available care',
            'focusDescription' => 'Week 4 now lets patients review active doctors and available consultation slots before booking is introduced in Week 5.',
            'statusMessage' => Session::getFlash('status'),
            'stats' => [
                ['label' => 'Available Doctors', 'value' => (string) ($browseSummary['available_doctors'] ?? 0), 'icon' => 'bi-person-badge', 'description' => 'Doctors with at least one future consultation slot currently available.'],
                ['label' => 'Open Consultation Slots', 'value' => (string) ($browseSummary['available_slots'] ?? 0), 'icon' => 'bi-calendar2-check', 'description' => 'Patient-visible availability that can be reviewed before booking goes live.'],
                ['label' => 'Specializations Available', 'value' => (string) ($browseSummary['specializations'] ?? 0), 'icon' => 'bi-hospital', 'description' => 'Distinct clinician specialization options patients can browse today.'],
                ['label' => 'Account Status', 'value' => 'Active', 'icon' => 'bi-shield-check', 'description' => 'Your patient account is authenticated and available.'],
            ],
            'quickActions' => [
                ['title' => 'Browse Doctors', 'description' => 'Review clinician photos, titles, specializations, and previewed consultation availability.', 'icon' => 'bi-person-badge', 'status' => 'Available now', 'url' => '/patient/doctors', 'action_label' => 'Open Directory'],
                ['title' => 'View Available Slots', 'description' => 'Filter future consultation slots by doctor, specialization, and consultation date.', 'icon' => 'bi-calendar2-week', 'status' => 'Available now', 'url' => '/patient/available-slots', 'action_label' => 'Browse Slots'],
                ['title' => 'View My Profile', 'description' => 'Review your patient account identity and profile photo in one place.', 'icon' => 'bi-person-circle', 'status' => 'Available now', 'url' => '/patient/profile', 'action_label' => 'Open Profile'],
                ['title' => 'Change Profile Picture', 'description' => 'Upload a profile photo that updates your dashboard avatar after saving.', 'icon' => 'bi-camera', 'status' => 'Available now', 'url' => '/patient/profile/edit', 'action_label' => 'Edit Photo'],
            ],
            'recentActivity' => [
                ['title' => 'Dashboard access confirmed', 'description' => 'Your authenticated patient dashboard remains role protected and session aware.', 'meta' => 'Current session'],
                ['title' => 'Doctor directory enabled', 'description' => 'Patients can now browse clinicians with future available consultation schedules.', 'meta' => 'Week 4 completion'],
                ['title' => 'Available slot viewer enabled', 'description' => 'Future slots can now be filtered by doctor, specialization, and consultation date.', 'meta' => 'Week 4 completion'],
                ['title' => 'Booking workflow pending', 'description' => 'Consultation booking remains intentionally reserved for Week 5.', 'meta' => 'Next module'],
            ],
            'emptyState' => [
                'icon' => 'bi-search',
                'title' => 'Start by browsing available care',
                'description' => 'Use the doctor directory and available slot viewer to review future consultation options before patient booking is introduced.',
            ],
            'featuredDoctors' => $featuredDoctors,
            'slotPreview' => $slotPreview,
        ], 'layouts/dashboard');
    }

    public function doctors(): void
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

        $pageData = PatientDirectoryService::getDoctorDirectoryPageData($_GET);

        $this->render('patient/doctors/index', [
            'title' => 'Doctor Directory | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Doctor Directory',
            'dashboardDescription' => 'Browse active doctors with patient-visible consultation availability.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'doctors' => $pageData['doctors'],
            'summary' => $pageData['summary'],
            'pagination' => $pageData['pagination'],
            'statusMessage' => Session::getFlash('status'),
        ], 'layouts/dashboard');
    }

    public function availableSlots(): void
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

        $pageData = PatientDirectoryService::getAvailableSlotsPageData($_GET);

        $this->render('patient/slots/index', [
            'title' => 'Available Consultation Slots | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Available Consultation Slots',
            'dashboardDescription' => 'Review patient-visible consultation slots before booking is enabled in Week 5.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'filters' => $pageData['filters'],
            'slots' => $pageData['slots'],
            'summary' => $pageData['summary'],
            'pagination' => $pageData['pagination'],
            'doctorOptions' => $pageData['doctorOptions'],
            'specializationOptions' => $pageData['specializationOptions'],
            'statusMessage' => Session::getFlash('status'),
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
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
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
            'dashboardDescription' => 'Upload a professional patient profile picture securely.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
                ['path' => '/patient/profile/edit', 'label' => 'Edit Profile Photo', 'icon' => 'bi-camera'],
            ],
            'profile' => $profile,
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
        ], 'layouts/dashboard');
    }
}
