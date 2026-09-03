<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\ConsultationRequest;
use App\Services\AuthService;
use App\Services\ConsultationRecordPdfService;
use App\Services\ComplaintImageService;
use App\Services\PatientClinicalRecordService;
use App\Services\PrescriptionPdfService;
use App\Services\PatientConsultationBookingService;
use App\Services\PatientDirectoryService;
use App\Services\PatientProfileService;
use App\Services\NotificationService;
use App\Services\VideoConsultationService;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

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
        $dashboardInsights = $user !== null && $user->id !== null
            ? PatientConsultationBookingService::getDashboardInsights((int) $user->id)
            : [
                'summary' => [
                    'pending_requests' => 0,
                    'approved_requests' => 0,
                    'upcoming_appointments' => 0,
                    'consultation_history' => 0,
                    'latest_status' => '',
                    'latest_status_display' => 'No requests yet',
                    'latest_request' => null,
                ],
                'nextAppointment' => null,
                'charts' => [],
            ];
        $bookingSummary = is_array($dashboardInsights['summary'] ?? null) ? $dashboardInsights['summary'] : [];
        $recentRequests = $user !== null && $user->id !== null
            ? PatientConsultationBookingService::getRecentRequests((int) $user->id, 5)
            : [];
        $nextAppointment = is_array($dashboardInsights['nextAppointment'] ?? null) ? $dashboardInsights['nextAppointment'] : null;
        $patientCharts = is_array($dashboardInsights['charts'] ?? null) ? $dashboardInsights['charts'] : [];
        $headerNotifications = $user !== null && $user->id !== null
            ? NotificationService::getHeaderData((int) $user->id, 'patient')
            : ['unread_count' => 0, 'total_count' => 0, 'recent' => []];

        $this->render('patient/dashboard', [
            'title' => 'Patient Dashboard | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Patient Dashboard',
            'dashboardDescription' => 'Your secure home for upcoming digital care interactions.',
            'topbarSearchPlaceholder' => 'Search doctors, slots, or consultations',
            'showRightbar' => true,
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/doctors', 'label' => 'Doctors', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Book Consultation', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/consultation-requests', 'label' => 'My Consultations', 'icon' => 'bi-clipboard2-check'],
                ['path' => '/patient/profile', 'label' => 'Profile', 'icon' => 'bi-person-circle'],
            ],
            'welcomeMessage' => 'Browse doctors, choose an available slot, and submit a consultation request.',
            'focusTitle' => 'Book a consultation slot',
            'focusDescription' => 'Choose a doctor, pick an available slot, and describe the reason for the visit.',
            'statusMessage' => Session::getFlash('status'),
            'stats' => [
                ['label' => 'Pending Requests', 'value' => (string) ($bookingSummary['pending_requests'] ?? 0), 'icon' => 'bi-hourglass-split', 'description' => 'Consultation booking requests waiting for review.'],
                ['label' => 'Upcoming Consultation', 'value' => (string) ($bookingSummary['upcoming_appointments'] ?? 0), 'icon' => 'bi-calendar2-check', 'description' => 'Approved consultations scheduled for today or later.'],
                ['label' => 'Consultation History', 'value' => (string) ($bookingSummary['consultation_history'] ?? 0), 'icon' => 'bi-clipboard2-data', 'description' => 'All consultation booking requests submitted from your patient account.'],
                ['label' => 'Latest Consultation Status', 'value' => (string) ($bookingSummary['latest_status_display'] ?? 'No requests yet'), 'icon' => 'bi-activity', 'description' => 'The most recent consultation workflow status currently visible on your account.'],
            ],
            'recentActivity' => [
                [
                    'title' => 'Latest consultation status',
                    'description' => 'Your most recent consultation request is currently ' . (string) ($bookingSummary['latest_status_display'] ?? 'none') . '.',
                    'meta' => !empty($bookingSummary['latest_request']['consultation_date']) ? 'Scheduled ' . \App\Helpers\Helper::formatDate((string) $bookingSummary['latest_request']['consultation_date'], 'd M Y', 'Not scheduled') : 'No consultation scheduled yet',
                ],
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
            'recentRequests' => $recentRequests,
            'nextAppointment' => $nextAppointment,
            'charts' => $patientCharts,
            'headerNotifications' => $headerNotifications,
            'recentNotifications' => array_slice($headerNotifications['recent'] ?? [], 0, 5),
            'rightbar' => [
                'calendarTitle' => 'Upcoming Appointments',
                'calendarEvents' => $user !== null && $user->id !== null
                    ? \App\Helpers\DashboardCalendar::eventsForDashboard('patient', (int) $user->id)
                    : [],
                'upcomingTitle' => 'Upcoming Consultation',
                'upcomingItems' => $nextAppointment !== null
                    ? [
                        [
                            'icon' => 'bi-calendar2-check',
                            'title' => (string) ($nextAppointment['doctor_name'] ?? 'Doctor'),
                            'meta' => \App\Helpers\Helper::formatDate((string) ($nextAppointment['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')
                                . (!empty($nextAppointment['start_time']) ? ' · ' . substr((string) ($nextAppointment['start_time'] ?? ''), 0, 5) : ''),
                            'badge' => (string) ($nextAppointment['status'] ?? 'Approved'),
                        ],
                    ]
                    : [],
                'quickActions' => [
                    ['label' => 'Browse Doctors', 'url' => '/patient/doctors', 'icon' => 'bi-person-badge'],
                    ['label' => 'Book Consultation', 'url' => '/patient/available-slots', 'icon' => 'bi-calendar2-plus'],
                    ['label' => 'My Consultations', 'url' => '/patient/consultation-requests', 'icon' => 'bi-clipboard2-check'],
                ],
                'summaryStats' => [
                    ['label' => 'Pending', 'value' => (string) ((int) ($bookingSummary['pending_requests'] ?? 0))],
                    ['label' => 'Upcoming', 'value' => (string) ((int) ($bookingSummary['upcoming_appointments'] ?? 0))],
                    ['label' => 'History', 'value' => (string) ((int) ($bookingSummary['consultation_history'] ?? 0))],
                ],
            ],
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
            'dashboardTitle' => 'Doctors',
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

        $sidebarItems = [
            ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
            ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
            ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
            ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
            ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
        ];

        if (strtolower(trim((string) ($_GET['view'] ?? ''))) === 'list') {
            $pageData = PatientDirectoryService::getAvailableSlotsPageData($_GET);

            $this->render('patient/slots/index', [
                'title' => 'Available Consultation Slots | MBPHA TeleHealth Consultation System',
                'user' => $user,
                'dashboardRole' => 'patient',
                'dashboardRoleLabel' => 'Patient Dashboard',
                'dashboardTitle' => 'Available Consultation Slots',
                'dashboardDescription' => 'Review available consultation slots and submit a booking request for a selected slot.',
                'sidebarItems' => $sidebarItems,
                'filters' => $pageData['filters'],
                'slots' => $pageData['slots'],
                'summary' => $pageData['summary'],
                'pagination' => $pageData['pagination'],
                'doctorOptions' => $pageData['doctorOptions'],
                'specializationOptions' => $pageData['specializationOptions'],
                'statusMessage' => Session::getFlash('status'),
            ], 'layouts/dashboard');
            return;
        }

        $weekData = PatientDirectoryService::getWeeklyBookingPageData($_GET);

        $this->render('patient/slots/timetable', [
            'title' => 'Available Consultation Slots | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Available Consultation Slots',
            'dashboardDescription' => 'Choose an open consultation time on the weekly schedule and book that visit.',
            'sidebarItems' => $sidebarItems,
            'filters' => $weekData['filters'],
            'weekStart' => $weekData['weekStart'],
            'weekEnd' => $weekData['weekEnd'],
            'weekLabel' => $weekData['weekLabel'],
            'isCurrentWeek' => $weekData['isCurrentWeek'],
            'prevWeek' => $weekData['prevWeek'],
            'nextWeek' => $weekData['nextWeek'],
            'thisWeek' => $weekData['thisWeek'],
            'days' => $weekData['days'],
            'intervals' => $weekData['intervals'],
            'blocks' => $weekData['blocks'],
            'gridStart' => $weekData['gridStart'],
            'gridEnd' => $weekData['gridEnd'],
            'openCells' => $weekData['openCells'],
            'weekSlotCount' => $weekData['weekSlotCount'],
            'summary' => $weekData['summary'],
            'doctorOptions' => $weekData['doctorOptions'],
            'specializationOptions' => $weekData['specializationOptions'],
            'timezoneLabel' => $weekData['timezoneLabel'],
            'statusMessage' => Session::getFlash('status'),
            'pageStyles' => '<link rel="stylesheet" href="' . Helper::asset('css/mbpha-schedule.css') . '">',
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
        $formData = [
            'dob' => (string) ($profile['dob'] ?? ''),
            'gender' => (string) ($profile['gender'] ?? ''),
            'address' => (string) ($profile['address'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''),
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['form_action'] ?? 'photo');
            $result = $action === 'details'
                ? PatientProfileService::updateAccountDetails((int) $user->id, $_POST)
                : PatientProfileService::updateProfilePhoto((int) $user->id, $_POST, $_FILES);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Patient profile updated successfully.',
                ]);
                Helper::redirect('/patient/profile');
                return;
            }

            $errors = $result['errors'] ?? [];
            $fieldErrors = $result['fieldErrors'] ?? [];
            $profile = $result['profile'] ?? $profile;
            if (isset($result['formData']) && is_array($result['formData'])) {
                $formData = array_merge($formData, $result['formData']);
            }
        }

        $this->render('patient/profile/edit', [
            'title' => 'Edit Patient Profile | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Edit Profile',
            'dashboardDescription' => 'Update permitted personal details and your profile picture.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
                ['path' => '/patient/profile/edit', 'label' => 'Edit Profile Photo', 'icon' => 'bi-camera'],
            ],
            'profile' => $profile,
            'formData' => $formData,
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
        $attachJoin = static function (array $row): array {
            $row['videoJoin'] = self::computePatientVideoJoinContext($row);
            return $row;
        };
        $requests = $pageData['requests'] ?? [];
        if (is_array($requests)) {
            $requests = array_map($attachJoin, $requests);
        } else {
            $requests = [];
        }

        $groups = is_array($pageData['groups'] ?? null) ? $pageData['groups'] : PatientConsultationBookingService::groupHistoryRows($requests);
        foreach (['active', 'completed', 'closed'] as $groupKey) {
            $groupRows = $groups[$groupKey] ?? [];
            $groups[$groupKey] = is_array($groupRows) ? array_map($attachJoin, $groupRows) : [];
        }

        $this->render('patient/consultation_requests/index', [
            'title' => 'Consultation History | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'My Consultations',
            'dashboardDescription' => 'View your bookings, completed consultations, clinical records, and prescriptions.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'requests' => $requests,
            'historyGroups' => $groups,
            'grouped' => (bool) ($pageData['grouped'] ?? true),
            'filterActive' => (bool) ($pageData['filterActive'] ?? false),
            'summary' => $pageData['summary'] ?? [],
            'pagination' => $pageData['pagination'] ?? [],
            'filters' => $pageData['filters'] ?? [],
            'statusOptions' => $pageData['statusOptions'] ?? [],
            'dateOptions' => $pageData['dateOptions'] ?? [],
            'sortOptions' => $pageData['sortOptions'] ?? [],
            'documentOptions' => $pageData['documentOptions'] ?? [],
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

        $videoJoin = self::computePatientVideoJoinContext($request);
        $clinical = PatientClinicalRecordService::getCompletedRecordForPatient((int) $user->id, $requestId);

        $this->render('patient/consultation_requests/show', [
            'title' => (((string) ($request['status'] ?? '')) === 'Completed' ? 'Consultation Record' : 'Consultation Details') . ' | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => ((string) ($request['status'] ?? '')) === 'Completed' ? 'Consultation Record' : 'Consultation Details',
            'dashboardDescription' => ((string) ($request['status'] ?? '')) === 'Completed'
                ? 'Review this completed historical consultation record and any issued prescription.'
                : 'Review this consultation and join the video room when the scheduled session is open.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'request' => $request,
            'videoJoin' => $videoJoin,
            'clinicalRecord' => is_array($clinical) ? ($clinical['record'] ?? null) : null,
            'prescriptions' => is_array($clinical) ? ($clinical['prescriptions'] ?? []) : [],
            'pageStyles' => '<link rel="stylesheet" href="' . Helper::asset('css/consultation-record.css') . '"><link rel="stylesheet" href="' . Helper::asset('css/prescription.css') . '">',
            'statusMessage' => Session::getFlash('status'),
        ], 'layouts/dashboard');
    }

    /**
     * Compute a deterministic, server-side "can the patient join NOW" hint
     * for the consultation details page.  The hint is a UX guide only — the
     * actual allow/deny decision remains in VideoConsultationService at the
     * time the user clicks "Join Consultation".
     *
     * @return array{
     *     canJoin: bool,
     *     status: non-empty-string,
     *     reason: non-empty-string,
     *     consultationId: int,
     *     joinUrl: string,
     * }
     */
    private static function computePatientVideoJoinContext(array $request): array
    {
        $status = strtolower(trim((string) ($request['status'] ?? '')));
        $consultationId = (int) ($request['id'] ?? 0);
        $fallback = [
            'canJoin' => false,
            'status'  => 'unavailable',
            'reason'  => 'Video consultation is not available for this request.',
            'consultationId' => $consultationId,
            'joinUrl' => '',
        ];

        if ($status !== 'approved' || $consultationId <= 0) {
            if ($status === 'rejected') {
                $fallback['reason'] = 'This consultation request was rejected by MBPHA administration.';
            } elseif ($status === 'cancelled') {
                $fallback['reason'] = 'This consultation has been cancelled.';
            } elseif ($status !== 'approved') {
                $fallback['reason'] = 'You can join once MBPHA administration approves the consultation.';
            }
            return $fallback;
        }

        $dateStr = trim((string) ($request['consultation_date'] ?? ''));
        $startStr = trim((string) ($request['start_time'] ?? ''));
        $endStr   = trim((string) ($request['end_time'] ?? ''));

        if ($dateStr === '' || $startStr === '' || $endStr === '') {
            $fallback['reason'] = 'The consultation schedule is not set. Please try again later.';
            return $fallback;
        }

        try {
            $tz = \App\Services\VideoConsultationService::resolveAppointmentTimezone();
            $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $startStr, $tz);
            $end   = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $endStr, $tz);
            if ($start === false || $end === false) {
                throw new RuntimeException('parse');
            }
            $windowStart = $start->modify(sprintf('-%d seconds', \App\Services\VideoConsultationService::JOIN_WINDOW_BEFORE_START_SECONDS));
            $windowEnd   = $end;
            $nowUtc      = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $wsUtc       = $windowStart->setTimezone(new DateTimeZone('UTC'));
            $weUtc       = $windowEnd->setTimezone(new DateTimeZone('UTC'));

            $result = [
                'canJoin'        => false,
                'status'         => 'pending',
                'reason'         => '',
                'consultationId' => $consultationId,
                'joinUrl'        => \App\Helpers\Helper::url('/patient/consultations/' . $consultationId . '/room'),
            ];

            if ($nowUtc < $wsUtc) {
                $result['status'] = 'early';
                $result['reason'] = sprintf(
                    'Consultation opens at %s (%s).',
                    $start->format('g:i A'),
                    \App\Helpers\Helper::appTimezoneLabel()
                );
                return $result;
            }
            if ($nowUtc > $weUtc) {
                $result['status'] = 'ended';
                $result['reason'] = 'This consultation has ended.';
                return $result;
            }

            $result['canJoin'] = true;
            $result['status']  = 'open';
            $result['reason']  = 'Your video consultation room is available now.';
            return $result;
        } catch (\Throwable) {
            $fallback['reason'] = 'Unable to determine consultation window. Please try again later.';
            return $fallback;
        }
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
            $result = PatientConsultationBookingService::submitBooking((int) $user->id, $availabilityId, $_POST, $_FILES);

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

    /**
     * JSON endpoint — authorize and mint a Daily meeting token for the
     * currently logged-in patient to join consultation {id}.
     *
     * Security:
     *   • User id + role are sourced from PHP session only (never GET/POST).
     *   • Consultation id comes from route parameter; we do NOT trust any
     *     user-supplied role / appointment ownership in the request.
     *   • Token is minted FRESH per call and NEVER stored in MySQL.
     *
     * @week 6 — Video Consultation Integration
     */
    public function joinConsultation(string $id): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'patient') {
            $this->jsonResponse([
                'ok'      => false,
                'code'    => 'unauthenticated',
                'message' => 'Please sign in to join this consultation.',
            ], 401);
            return;
        }

        if (!$this->requireJsonCsrf()) {
            return;
        }

        try {
            $result = VideoConsultationService::authorizeJoinForCurrentUser((int) $id);
        } catch (\Throwable $e) {
            error_log(sprintf('[PatientController::joinConsultation] Unexpected exception for id=%s: %s', var_export($id, true), $e->getMessage()));
            $this->jsonResponse([
                'ok'      => false,
                'code'    => 'server_error',
                'message' => 'The video consultation could not be started right now.',
            ], 500);
            return;
        }

        if (($result['ok'] ?? false) === true) {
            $this->jsonResponse([
                'ok'       => true,
                'room_url' => (string) ($result['room_url'] ?? ''),
                'token'    => (string) ($result['token'] ?? ''),
            ], 200);
            return;
        }

        $this->jsonResponse([
            'ok'      => false,
            'code'    => (string) ($result['code'] ?? 'error'),
            'message' => (string) ($result['message'] ?? 'Request could not be fulfilled.'),
        ], (int) ($result['http_code'] ?? 400));
    }

    /**
     * Show the MBPHA video consultation room (UI page) for patient {id}.
     *
     * SECURITY NOTE:
     *   This action only renders the PAGE SHELL.  It NEVER emits the Daily
     *   room URL or the short-lived meeting token into the HTML.  Those are
     *   only fetched via a subsequent fetch() call to joinConsultation()
     *   (above) at the moment the browser is ready to initialize Daily.
     *
     * @week 6 — Video Consultation Integration
     */
    public function showConsultationRoom(string $id): void
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

        $request = ConsultationRequest::findByIdForPatient((int) $id, (int) $user->id);

        if ($request === null) {
            Session::flash('status', [
                'type' => 'danger',
                'message' => 'Consultation not found.',
            ]);
            Helper::redirect('/patient/consultation-requests');
            return;
        }

        $requestStatus = (string) ($request['status'] ?? '');
        if ($requestStatus !== 'Approved') {
            if ($requestStatus === 'Completed') {
                Helper::redirect('/patient/consultation-requests/' . (int) $request['id']);
                return;
            }

            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The video consultation room is only available for approved consultations.',
            ]);
            Helper::redirect('/patient/consultation-requests');
            return;
        }

        $otherPartyName = (string) ($request['doctor_name'] ?? 'Your Doctor');
        $otherPartyTitle = (string) ($request['doctor_title'] ?? 'Medical Practitioner');
        $specialization = trim((string) ($request['specialization'] ?? ''));

        $context = [
            'consultation_id'          => (int) $request['id'],
            'viewer_role'              => 'patient',
            'other_party_name'         => $otherPartyName,
            'other_party_title'        => $otherPartyTitle,
            'other_party_photo'        => $request['doctor_photo_path'] ?? null,
            'other_party_meta'         => $specialization !== '' ? $specialization : null,
            'consultation_date'        => (string) ($request['consultation_date'] ?? ''),
            'consultation_start_time'  => (string) ($request['start_time'] ?? ''),
            'consultation_end_time'    => (string) ($request['end_time'] ?? ''),
            'consultation_reason'      => (string) ($request['reason'] ?? ''),
            'complaint_image_url'      => ComplaintImageService::existsOnRequest($request)
                ? ComplaintImageService::viewerUrl('patient', (int) $request['id'])
                : '',
            'consultation_status'      => (string) ($request['status'] ?? 'Pending'),
            'join_token_endpoint'      => Helper::url('/patient/consultations/' . (int) $request['id'] . '/join-token'),
            'csrf_token'               => Csrf::generate(),
            'return_path'              => Helper::url('/patient/consultation-requests/' . (int) $request['id']),
            'return_path_label'        => 'Back to Consultation Details',
        ];

        $this->render('patient/consultations/room', [
            'title' => 'Video Consultation | MBPHA TeleHealth Consultation System',
            'user'  => $user,
            'dashboardRole'      => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle'     => 'Video Consultation',
            'dashboardDescription' => sprintf('Live consultation with %s.', $otherPartyName),
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/patient/consultation-requests', 'label' => 'Consultation History', 'icon' => 'bi-clipboard2-check'],
                ['path' => '/patient/doctors', 'label' => 'Doctor Directory', 'icon' => 'bi-person-badge'],
                ['path' => '/patient/available-slots', 'label' => 'Available Slots', 'icon' => 'bi-calendar2-week'],
                ['path' => '/patient/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'pageStyles'  => '<link rel="stylesheet" href="' . Helper::asset('css/consultation-room.css') . '">',
            'pageScripts' => '',
            'context' => $context,
            'statusMessage' => Session::getFlash('status'),
        ], 'layouts/dashboard');
    }

    public function downloadConsultationRecord(string $id): void
    {
        $this->streamConsultationRecordPdf($id);
    }

    public function downloadPrescription(string $id): void
    {
        $this->streamPrescriptionPdf($id);
    }

    public function showComplaintImage(string $id): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'patient') {
            Helper::redirect('/login');
            return;
        }

        ComplaintImageService::streamForCurrentUser((int) $id);
    }

    /**
     * Stream a finalized consultation-record PDF for the signed-in patient.
     */
    private function streamConsultationRecordPdf(string $id): void
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
        $owned = PatientConsultationBookingService::getRequestDetail((int) $user->id, $requestId);
        if ($owned === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested consultation record could not be found.',
            ]);
            Helper::redirect('/patient/consultation-requests');
            return;
        }

        $page = PatientClinicalRecordService::getPrintableDocumentForPatient((int) $user->id, $requestId, 'record');
        $built = is_array($page) ? ConsultationRecordPdfService::buildFromAuthorizedPage($page) : null;
        if ($built === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The consultation record is not available to download yet.',
            ]);
            Helper::redirect('/patient/consultation-requests/' . $requestId);
            return;
        }

        ConsultationRecordPdfService::stream($built);
    }

    /**
     * Stream an issued prescription PDF for the signed-in patient.
     * Read-only: authorization uses the patient's own consultation, then
     * the stored prescription rows. No prescription is created or updated.
     */
    private function streamPrescriptionPdf(string $id): void
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
        $owned = PatientConsultationBookingService::getRequestDetail((int) $user->id, $requestId);
        if ($owned === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested prescription could not be found.',
            ]);
            Helper::redirect('/patient/consultation-requests');
            return;
        }

        $page = PatientClinicalRecordService::getPrintableDocumentForPatient((int) $user->id, $requestId, 'prescription');
        $built = is_array($page) ? PrescriptionPdfService::buildFromAuthorizedPage($page) : null;
        if ($built === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'No prescription is available to download for this consultation.',
            ]);
            Helper::redirect('/patient/consultation-requests/' . $requestId);
            return;
        }

        PrescriptionPdfService::stream($built);
    }
}
