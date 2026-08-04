<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\AuthService;
use App\Services\PatientConsultationRequestService;
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
        $requestSummary = $user !== null && $user->id !== null
            ? PatientConsultationRequestService::getDashboardSummary((int) $user->id)
            : [
                'total_requests' => 0,
                'pending_requests' => 0,
                'approved_requests' => 0,
                'completed_consultations' => 0,
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
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'welcomeMessage' => 'Submit secure consultation requests, track request statuses, and explore available clinicians from one patient workspace.',
            'focusTitle' => 'Submit a consultation request',
            'focusDescription' => 'Week 5 introduces patient consultation request submission with specialization matching and secure attachment uploads.',
            'statusMessage' => Session::getFlash('status'),
            'stats' => [
                ['label' => 'Total Requests', 'value' => (string) ($requestSummary['total_requests'] ?? 0), 'icon' => 'bi-clipboard2-check', 'description' => 'All consultation requests you have submitted so far.'],
                ['label' => 'Pending Requests', 'value' => (string) ($requestSummary['pending_requests'] ?? 0), 'icon' => 'bi-hourglass-split', 'description' => 'Requests waiting for clinician review and approval.'],
                ['label' => 'Approved Requests', 'value' => (string) ($requestSummary['approved_requests'] ?? 0), 'icon' => 'bi-check2-circle', 'description' => 'Requests approved and awaiting appointment scheduling workflow.'],
                ['label' => 'Completed Consultations', 'value' => (string) ($requestSummary['completed_consultations'] ?? 0), 'icon' => 'bi-file-medical', 'description' => 'Consultations that have been completed and recorded in your history.'],
            ],
            'quickActions' => [
                ['title' => 'New Consultation Request', 'description' => 'Submit a new consultation request with a chief complaint and optional supporting attachment.', 'icon' => 'bi-clipboard2-plus', 'status' => 'Week 5 live', 'url' => '/patient/consultation-requests/create', 'action_label' => 'Submit Request'],
                ['title' => 'View My Requests', 'description' => 'Review submitted consultation requests, view details, and track your current request statuses.', 'icon' => 'bi-clipboard2-data', 'status' => 'Week 5 live', 'url' => '/patient/consultation-requests', 'action_label' => 'Open Requests'],
                ['title' => 'Browse Doctors', 'description' => 'Review clinician photos, titles, specializations, and previewed consultation availability.', 'icon' => 'bi-person-badge', 'status' => 'Available now', 'url' => '/patient/doctors', 'action_label' => 'Open Directory'],
                ['title' => 'View Available Slots', 'description' => 'Filter future consultation slots by doctor, specialization, and consultation date.', 'icon' => 'bi-calendar2-week', 'status' => 'Available now', 'url' => '/patient/available-slots', 'action_label' => 'Browse Slots'],
                ['title' => 'View My Profile', 'description' => 'Review your patient account identity and profile photo in one place.', 'icon' => 'bi-person-circle', 'status' => 'Available now', 'url' => '/patient/profile', 'action_label' => 'Open Profile'],
                ['title' => 'Change Profile Picture', 'description' => 'Upload a profile photo that updates your dashboard avatar after saving.', 'icon' => 'bi-camera', 'status' => 'Available now', 'url' => '/patient/profile/edit', 'action_label' => 'Edit Photo'],
            ],
            'recentActivity' => [
                ['title' => 'Dashboard access confirmed', 'description' => 'Your authenticated patient dashboard remains role protected and session aware.', 'meta' => 'Current session'],
                ['title' => 'Consultation request submission enabled', 'description' => 'Patients can now submit consultation requests with specialization matching and secure attachment uploads.', 'meta' => 'Week 5 Day 1'],
                ['title' => 'Doctor directory enabled', 'description' => 'Patients can now browse clinicians with future available consultation schedules.', 'meta' => 'Week 4 completion'],
                ['title' => 'Available slot viewer enabled', 'description' => 'Future slots can now be filtered by doctor, specialization, and consultation date.', 'meta' => 'Week 4 completion'],
                ['title' => 'Booking workflow pending', 'description' => 'Consultation booking and appointment scheduling remain reserved for later Week 5 modules.', 'meta' => 'Next module'],
            ],
            'emptyState' => [
                'icon' => 'bi-clipboard2-pulse',
                'title' => 'Submit your first consultation request',
                'description' => 'Start by creating a consultation request and selecting the medical specialization that best matches your symptoms.',
            ],
            'browseSummary' => $browseSummary,
            'requestSummary' => $requestSummary,
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
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-pulse'],
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
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-pulse'],
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
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-pulse'],
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
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-pulse'],
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

    public function consultationRequests(): void
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

        $pageData = PatientConsultationRequestService::getRequestListPageData((int) $user->id, $_GET);

        $this->render('patient/consultation_requests/index', [
            'title' => 'Consultation Requests | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Consultation Requests',
            'dashboardDescription' => 'Review consultation requests you have submitted and track their approval status.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-pulse'],
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

    public function createConsultationRequest(): void
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

        $errors = [];
        $fieldErrors = [];
        $formData = PatientConsultationRequestService::getRequestFormData();
        $specializationOptions = PatientConsultationRequestService::getSpecializationOptions();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = PatientConsultationRequestService::submitRequest((int) $user->id, $_POST, $_FILES);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Consultation request submitted successfully.',
                ]);
                Helper::redirect('/patient/consultation-requests/' . (string) ($result['requestId'] ?? ''));
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
            $formData = $result['formData'] ?? $formData;
            $specializationOptions = $result['specializationOptions'] ?? $specializationOptions;
        }

        $this->render('patient/consultation_requests/create', [
            'title' => 'New Consultation Request | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'New Consultation Request',
            'dashboardDescription' => 'Submit a chief complaint and select the medical specialization required for your consultation.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'formData' => $formData,
            'specializationOptions' => $specializationOptions,
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
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
        $request = PatientConsultationRequestService::getRequestDetail((int) $user->id, $requestId);

        if ($request === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested consultation request could not be found.',
            ]);
            Helper::redirect('/patient/consultation-requests');
            return;
        }

        $this->render('patient/consultation_requests/show', [
            'title' => 'Consultation Request Details | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Consultation Request Details',
            'dashboardDescription' => 'View your submitted consultation request details and current status.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'request' => $request,
            'statusMessage' => Session::getFlash('status'),
        ], 'layouts/dashboard');
    }

    public function downloadConsultationRequestAttachment(string $id): void
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
        $request = PatientConsultationRequestService::getRequestDetail((int) $user->id, $requestId);
        $attachmentPath = is_array($request) ? (string) ($request['attachment_path'] ?? '') : '';

        if ($request === null || $attachmentPath === '') {
            http_response_code(404);
            echo 'Attachment not found.';
            return;
        }

        $expectedPrefix = 'uploads/consultation_requests/' . (int) $user->id . '/';
        $attachmentPath = str_replace(['\\', "\0"], ['/', ''], ltrim($attachmentPath, '/'));

        if (strpos($attachmentPath, $expectedPrefix) !== 0) {
            http_response_code(404);
            echo 'Attachment not found.';
            return;
        }

        $absolutePath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $attachmentPath);

        if (!is_file($absolutePath)) {
            http_response_code(404);
            echo 'Attachment not found.';
            return;
        }

        $mime = (string) ($request['attachment_mime'] ?? 'application/octet-stream');
        $originalName = (string) ($request['attachment_original_name'] ?? basename($absolutePath));
        $originalName = preg_replace('/[\\x00-\\x1F\\x7F\"\\\\\\/]+/', '_', $originalName) ?: 'attachment';

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $originalName . '"');
        header('Content-Length: ' . (string) filesize($absolutePath));
        readfile($absolutePath);
        exit;
    }
}
