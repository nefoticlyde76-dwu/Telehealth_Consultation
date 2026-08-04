<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\AuthService;
use App\Services\PatientConsultationBookingService;
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
        $bookingSummary = $user !== null && $user->id !== null
            ? PatientConsultationBookingService::getDashboardSummary((int) $user->id)
            : [
                'pending_requests' => 0,
                'approved_requests' => 0,
                'upcoming_appointments' => 0,
                'consultation_history' => 0,
                'latest_status' => '',
                'latest_status_display' => 'No requests yet',
                'latest_request' => null,
            ];

        $this->render('patient/dashboard', [
            'title' => 'Patient Dashboard | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Patient Dashboard',
            'dashboardDescription' => 'Your secure home for upcoming digital care interactions.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'welcomeMessage' => 'Browse available doctors, select a consultation slot, and submit a secure booking request from one patient workspace.',
            'focusTitle' => 'Book a consultation slot',
            'focusDescription' => 'Week 5 introduces patient consultation booking with slot selection and a brief chief complaint submission.',
            'statusMessage' => Session::getFlash('status'),
            'stats' => [
                ['label' => 'Pending Requests', 'value' => (string) ($bookingSummary['pending_requests'] ?? 0), 'icon' => 'bi-hourglass-split', 'description' => 'Consultation booking requests waiting for review.'],
                ['label' => 'Upcoming Consultation', 'value' => (string) ($bookingSummary['upcoming_appointments'] ?? 0), 'icon' => 'bi-calendar2-check', 'description' => 'Approved consultations scheduled for today or later.'],
                ['label' => 'Consultation History', 'value' => (string) ($bookingSummary['consultation_history'] ?? 0), 'icon' => 'bi-clipboard2-data', 'description' => 'All consultation booking requests submitted from your patient account.'],
                ['label' => 'Latest Consultation Status', 'value' => (string) ($bookingSummary['latest_status_display'] ?? 'No requests yet'), 'icon' => 'bi-activity', 'description' => 'The most recent consultation workflow status currently visible on your account.'],
            ],
            'quickActions' => [
                ['title' => 'Browse Doctors', 'description' => 'Review clinician photos, titles, specializations, and the next available consultation slots.', 'icon' => 'bi-person-badge', 'status' => 'Booking live', 'url' => '/patient/doctors', 'action_label' => 'Open Directory'],
                ['title' => 'View Available Slots', 'description' => 'See all available consultation slots and book directly from the slot viewer.', 'icon' => 'bi-calendar2-week', 'status' => 'Booking live', 'url' => '/patient/available-slots', 'action_label' => 'Browse Slots'],
                ['title' => 'Consultation History', 'description' => 'View submitted consultation requests and track their current status updates.', 'icon' => 'bi-clipboard2-check', 'status' => 'Week 5', 'url' => '/patient/consultation-requests', 'action_label' => 'View Requests'],
                ['title' => 'View My Profile', 'description' => 'Review your patient account identity and profile photo in one place.', 'icon' => 'bi-person-circle', 'status' => 'Available now', 'url' => '/patient/profile', 'action_label' => 'Open Profile'],
                ['title' => 'Change Profile Picture', 'description' => 'Upload a profile photo that updates your dashboard avatar after saving.', 'icon' => 'bi-camera', 'status' => 'Available now', 'url' => '/patient/profile/edit', 'action_label' => 'Edit Photo'],
            ],
            'recentActivity' => [
                ['title' => 'Dashboard access confirmed', 'description' => 'Your authenticated patient dashboard remains role protected and session aware.', 'meta' => 'Current session'],
                [
                    'title' => 'Latest consultation status',
                    'description' => 'Your most recent consultation request is currently ' . (string) ($bookingSummary['latest_status_display'] ?? 'No requests yet') . '.',
                    'meta' => !empty($bookingSummary['latest_request']['consultation_date']) ? 'Scheduled ' . \App\Helpers\Helper::formatDate((string) $bookingSummary['latest_request']['consultation_date'], 'd M Y', 'Not scheduled') : 'No consultation scheduled yet',
                ],
                ['title' => 'Doctor directory enabled', 'description' => 'Patients can browse clinicians with future available consultation schedules.', 'meta' => 'Week 4 completion'],
                ['title' => 'Consultation workflow active', 'description' => 'Patients can now book, review history, and track consultation request status updates.', 'meta' => 'Week 5 refinement'],
            ],
            'emptyState' => [
                'icon' => 'bi-search',
                'title' => 'Start by booking an available slot',
                'description' => 'Browse doctors with available consultation slots and submit a consultation booking request with a brief chief complaint.',
            ],
            'featuredDoctors' => $featuredDoctors,
            'slotPreview' => $slotPreview,
            'browseSummary' => $browseSummary,
            'bookingSummary' => $bookingSummary,
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
            'dashboardDescription' => 'Browse available doctors and book a consultation slot with a brief chief complaint.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
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
            'dashboardDescription' => 'Review available consultation slots and submit a booking request for a selected slot.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
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
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
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
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
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

    public function consultationHistory(): void
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

        $pageData = PatientConsultationBookingService::getHistoryPageData((int) $user->id, $_GET);

        $this->render('patient/consultation_requests/index', [
            'title' => 'Consultation History | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Consultation History',
            'dashboardDescription' => 'View submitted consultation requests, appointment details, and request statuses.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'requests' => $pageData['requests'] ?? [],
            'summary' => $pageData['summary'] ?? [],
            'pagination' => $pageData['pagination'] ?? [],
            'statusMessage' => Session::getFlash('status'),
        ], 'layouts/dashboard');
    }

    public function showConsultationRequest(string $id): void
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

        $requestId = (int) $id;
        $request = PatientConsultationBookingService::getRequestDetail((int) $user->id, $requestId);

        if ($request === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested consultation record could not be found.',
            ]);
            Helper::redirect('/patient/consultation-requests');
            return;
        }

        $this->render('patient/consultation_requests/show', [
            'title' => 'Consultation Details | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Consultation Details',
            'dashboardDescription' => 'Review consultation booking details, doctor information, and request status.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'request' => $request,
            'statusMessage' => Session::getFlash('status'),
        ], 'layouts/dashboard');
    }

    public function bookConsultation(string $id): void
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

        $availabilityId = (int) $id;
        $errors = [];
        $fieldErrors = [];
        $formData = ['reason' => ''];
        $slot = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = PatientConsultationBookingService::submitBooking((int) $user->id, $availabilityId, $_POST);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Consultation booking submitted successfully.',
                ]);
                Helper::redirect('/patient/consultation-requests/' . (string) ((int) ($result['requestId'] ?? 0)));
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
            $formData = $result['formData'] ?? $formData;
            $slot = $result['slot'] ?? $slot;
        } else {
            $pageData = PatientConsultationBookingService::getBookingPageData($availabilityId);
            $slot = $pageData['slot'] ?? null;
            $formData = $pageData['formData'] ?? $formData;
        }

        if (!is_array($slot) || $slot === []) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The selected consultation slot is no longer available.',
            ]);
            Helper::redirect('/patient/available-slots');
            return;
        }

        $this->render('patient/consultation_requests/book', [
            'title' => 'Book Consultation | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Book Consultation',
            'dashboardDescription' => 'Confirm the selected consultation slot and submit a brief reason for consultation.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'slot' => $slot,
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'formData' => $formData,
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
        ], 'layouts/dashboard');
    }
}
