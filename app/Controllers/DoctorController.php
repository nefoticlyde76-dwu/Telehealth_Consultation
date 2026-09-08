<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\ConsultationRequest;
use App\Services\AuthService;
use App\Services\ComplaintImageService;
use App\Services\ConsultationRecordPdfService;
use App\Services\DoctorAvailabilityService;
use App\Services\DoctorClinicalDocumentationService;
use App\Services\DoctorConsultationService;
use App\Services\DoctorDashboardService;
use App\Services\DoctorPrescriptionService;
use App\Services\DoctorProfileService;
use App\Services\NotificationService;
use App\Services\PatientClinicalRecordService;
use App\Services\PrescriptionPdfService;
use App\Services\VideoConsultationService;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

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
        $headerNotifications = NotificationService::getHeaderData((int) $user->id, 'doctor');
        $unreadCount = (int) ($headerNotifications['unread_count'] ?? 0);
        $stats = $dashboardData['stats'];
        $stats[] = [
            'label' => 'Unread Notifications',
            'value' => (string) $unreadCount,
            'icon' => 'bi-bell',
            'description' => 'Consultation updates that still need your attention.',
            'tone' => 'info',
            'url' => '/notifications?read_state=unread',
        ];

        $this->render('doctor/dashboard', [
            'title' => 'Doctor Dashboard | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Doctor Dashboard',
            'dashboardDescription' => 'Your consultations, availability, and profile.',
            'topbarSearchPlaceholder' => 'Search patients, appointments, or consultations',
            'showRightbar' => true,
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/doctor/profile', 'label' => 'Profile', 'icon' => 'bi-person-vcard'],
            ],
            'welcomeMessage' => "Here's your day at a glance. Stay on top of your consultations and keep making a difference.",
            'focusTitle' => 'Clinician profile readiness',
            'focusDescription' => 'Keep your phone number, specialization, profile photo, and signature up to date for future consultation records.',
            'statusMessage' => Session::getFlash('status'),
            'stats' => $stats,
            'recentActivity' => $dashboardData['recentActivity'],
            'emptyState' => $dashboardData['emptyState'],
            'doctorProfile' => $dashboardData['doctor'],
            'todaySummary' => $dashboardData['todaySummary'],
            'upcomingSlots' => $dashboardData['upcomingSlots'],
            'weeklySchedule' => $dashboardData['weeklySchedule'],
            'availabilitySummary' => $dashboardData['availabilitySummary'],
            'assetReadiness' => $dashboardData['assetReadiness'],
            'upcomingApprovedAppointments' => $dashboardData['upcomingApprovedAppointments'] ?? [],
            'recentCompletedConsultations' => $dashboardData['recentCompletedConsultations'] ?? [],
            'recentApprovedAppointmentCount' => $dashboardData['recentApprovedAppointmentCount'] ?? 0,
            'upcomingApprovedAppointmentCount' => $dashboardData['upcomingApprovedAppointmentCount'] ?? 0,
            'charts' => $dashboardData['charts'] ?? [],
            'headerNotifications' => $headerNotifications,
            'recentNotifications' => array_slice($headerNotifications['recent'] ?? [], 0, 5),
            'rightbar' => [
                'calendarTitle' => 'Upcoming Appointments',
                'calendarEvents' => \App\Helpers\DashboardCalendar::eventsForDashboard('doctor', (int) $user->id),
                'upcomingTitle' => 'Upcoming Consultations',
                'upcomingItems' => array_map(static function (array $appointment): array {
                    $date = \App\Helpers\Helper::formatDate((string) ($appointment['consultation_date'] ?? ''), 'd M Y', 'Not scheduled');
                    $time = substr((string) ($appointment['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($appointment['end_time'] ?? ''), 0, 5);

                    return [
                        'icon' => 'bi-calendar2-check',
                        'title' => (string) ($appointment['patient_name'] ?? 'Patient'),
                        'meta' => $date . ' · ' . $time,
                        'badge' => (string) ($appointment['status'] ?? 'Approved'),
                    ];
                }, $dashboardData['upcomingApprovedAppointments'] ?? []),
                'quickActions' => [
                    ['label' => 'View Consultations', 'url' => '/doctor/consultations', 'icon' => 'bi-clipboard2-pulse'],
                    ['label' => 'Create Slot', 'url' => '/doctor/availability/create', 'icon' => 'bi-plus-circle'],
                    ['label' => 'Manage Availability', 'url' => '/doctor/availability', 'icon' => 'bi-calendar-week'],
                ],
                'summaryStats' => [
                    ['label' => "Today's", 'value' => (string) ((int) ($dashboardData['todaySummary']['total_today_slots'] ?? 0))],
                    ['label' => 'Approved', 'value' => (string) ((int) ($dashboardData['consultationSummary']['approved_appointments'] ?? 0))],
                    ['label' => 'Upcoming', 'value' => (string) ((int) ($dashboardData['consultationSummary']['upcoming_consultations'] ?? 0))],
                ],
            ],
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

        $sidebarItems = [
            ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
            ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
            ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
            ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-vcard'],
        ];

        if (strtolower(trim((string) ($_GET['view'] ?? ''))) === 'list') {
            $pageData = DoctorAvailabilityService::getAvailabilityPageData((int) $user->id, $_GET);

            $this->render('doctor/availability/index', [
                'title' => 'Doctor Availability | MBPHA TeleHealth Consultation System',
                'user' => $user,
                'dashboardRole' => 'doctor',
                'dashboardRoleLabel' => 'Doctor Dashboard',
                'dashboardTitle' => 'Availability',
                'dashboardDescription' => 'Create, review, update, and remove consultation availability slots securely.',
                'sidebarItems' => $sidebarItems,
                'filters' => $pageData['filters'],
                'availability' => $pageData['availability'],
                'summary' => $pageData['summary'],
                'filterActive' => (bool) ($pageData['filterActive'] ?? false),
                'pagination' => $pageData['pagination'],
                'statusOptions' => $pageData['statusOptions'],
                'dateOptions' => $pageData['dateOptions'] ?? [],
                'sortOptions' => $pageData['sortOptions'] ?? [],
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
            ], 'layouts/dashboard');
            return;
        }

        $weekData = DoctorAvailabilityService::getWeeklySchedulePageData((int) $user->id, $_GET);

        $this->render('doctor/availability/timetable', [
            'title' => 'Doctor Availability | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Availability',
            'dashboardDescription' => 'Set any consultation hours patients can book this week.',
            'sidebarItems' => $sidebarItems,
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
            'todayDate' => $weekData['todayDate'],
            'nowHm' => $weekData['nowHm'],
            'counts' => $weekData['counts'],
            'summary' => $weekData['summary'],
            'timezoneLabel' => $weekData['timezoneLabel'],
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
            'pageStyles' => '<link rel="stylesheet" href="' . Helper::asset('css/mbpha-schedule.css') . '">',
            'pageScripts' => '<script src="' . Helper::asset('js/mbpha-schedule.js') . '"></script>',
        ], 'layouts/dashboard');
    }

    public function saveWeeklyAvailability(): void
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

        $result = DoctorAvailabilityService::saveWeeklySchedule((int) $user->id, $_POST);
        $weekStart = (string) ($result['weekStart'] ?? '');

        Session::flash('status', [
            'type' => $result['type'] ?? (($result['success'] ?? false) ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Availability request completed.',
        ]);

        Helper::redirect(DoctorAvailabilityService::weekQueryUrl($weekStart));
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
        $formData = DoctorAvailabilityService::getAvailabilityFormData(null, $_GET);
        $returnWeek = trim((string) ($_POST['return_week'] ?? $_GET['week'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = DoctorAvailabilityService::createAvailability((int) $user->id, $_POST);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Availability slot created successfully.',
                ]);
                Helper::redirect(DoctorAvailabilityService::weekQueryUrl($returnWeek));
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
            'returnWeek' => $returnWeek,
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
        $returnWeek = trim((string) ($_POST['return_week'] ?? $_GET['week'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = DoctorAvailabilityService::updateAvailability((int) $user->id, $availabilityId, $_POST);

            if ($result['success'] ?? false) {
                Session::flash('status', [
                    'type' => 'success',
                    'message' => $result['message'] ?? 'Availability slot updated successfully.',
                ]);
                Helper::redirect(DoctorAvailabilityService::weekQueryUrl($returnWeek));
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
            'returnWeek' => $returnWeek,
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

        Helper::redirect(DoctorAvailabilityService::weekQueryUrl((string) ($_POST['return_week'] ?? '')));
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

        $attachJoin = static fn (array $row): array => $row + self::computeDoctorVideoJoinContext($row);

        $consultations = $pageData['consultations'] ?? [];
        if (is_array($consultations)) {
            $consultations = array_map($attachJoin, $consultations);
        } else {
            $consultations = [];
        }

        $groups = is_array($pageData['groups'] ?? null) ? $pageData['groups'] : [];
        foreach (['active', 'completed', 'closed'] as $groupKey) {
            $groupRows = $groups[$groupKey] ?? [];
            $groups[$groupKey] = is_array($groupRows) ? array_map($attachJoin, $groupRows) : [];
        }

        $this->render('doctor/consultations/index', [
            'title' => 'Doctor Consultations | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Consultations',
            'dashboardDescription' => 'Review upcoming, approved, and completed consultations.',
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-vcard'],
            ],
            'filters' => $pageData['filters'] ?? [],
            'consultations' => $consultations,
            'grouped' => (bool) ($pageData['grouped'] ?? false),
            'historyGroups' => $groups,
            'filterActive' => (bool) ($pageData['filterActive'] ?? false),
            'summary' => $pageData['summary'] ?? [],
            'pagination' => $pageData['pagination'] ?? [],
            'statusOptions' => $pageData['statusOptions'] ?? [],
            'dateOptions' => $pageData['dateOptions'] ?? [],
            'sortOptions' => $pageData['sortOptions'] ?? [],
            'statusMessage' => Session::getFlash('status'),
            'csrfToken' => Csrf::generate(),
        ], 'layouts/dashboard');
    }

    /**
     * Compute the doctor-side "can join now" UX hint for a single consultation
     * row.  Actual allow/deny is still enforced server-side on room load.
     *
     * @return array{videoJoin: array{
     *     canJoin: bool,
     *     status: non-empty-string,
     *     reason: non-empty-string,
     *     consultationId: int,
     *     joinUrl: string,
     * }}
     */
    private static function computeDoctorVideoJoinContext(array $row): array
    {
        $consultationId = (int) ($row['id'] ?? 0);
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $fallback = [
            'videoJoin' => [
                'canJoin' => false,
                'status'  => 'unavailable',
                'reason'  => 'Video consultation is not available for this request.',
                'consultationId' => $consultationId,
                'joinUrl' => '',
            ],
        ];

        if ($status !== 'approved' || $consultationId <= 0) {
            if ($status === 'rejected') {
                $fallback['videoJoin']['reason'] = 'This consultation request was rejected by MBPHA administration.';
            } elseif ($status === 'cancelled') {
                $fallback['videoJoin']['reason'] = 'This consultation has been cancelled.';
            } elseif ($status !== 'approved') {
                $fallback['videoJoin']['reason'] = 'You can join once MBPHA administration approves the consultation.';
            }
            return $fallback;
        }

        $dateStr = trim((string) ($row['consultation_date'] ?? ''));
        $startStr = trim((string) ($row['start_time'] ?? ''));
        $endStr   = trim((string) ($row['end_time'] ?? ''));
        if ($dateStr === '' || $startStr === '' || $endStr === '') {
            $fallback['videoJoin']['reason'] = 'Consultation schedule is not available.';
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

            $hint = [
                'canJoin' => false,
                'status'  => 'pending',
                'reason'  => '',
                'consultationId' => $consultationId,
                'joinUrl'        => \App\Helpers\Helper::url('/doctor/consultations/' . $consultationId . '/room'),
            ];

            if ($nowUtc < $wsUtc) {
                $hint['status'] = 'early';
                $hint['reason'] = sprintf(
                    'Room opens at %s (%s).',
                    $start->format('g:i A'),
                    \App\Helpers\Helper::appTimezoneLabel()
                );
            } elseif ($nowUtc > $weUtc) {
                $hint['status'] = 'ended';
                $hint['reason'] = 'This consultation has ended.';
            } else {
                $hint['canJoin'] = true;
                $hint['status']  = 'open';
                $hint['reason']  = 'Room is ready.';
            }

            return ['videoJoin' => $hint];
        } catch (\Throwable) {
            $fallback['videoJoin']['reason'] = 'Unable to determine consultation window.';
            return $fallback;
        }
    }

    public function completeConsultation(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/doctor/consultations/' . (int) $id . '/room');
            return;
        }

        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $result = DoctorClinicalDocumentationService::completeConsultation(
            (int) $user->id,
            (int) $id,
            (string) ($_POST['_token'] ?? ''),
            $_POST,
            ((string) ($_POST['confirm'] ?? '')) === '1'
        );

        Session::flash('status', [
            'type' => $result['type'] ?? (($result['success'] ?? false) ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Consultation status update completed.',
        ]);

        if (($result['success'] ?? false) === true) {
            Helper::redirect('/doctor/consultations/' . (int) $id . '/prescription');
            return;
        }

        Helper::redirect('/doctor/consultations/' . (int) $id . '/room');
    }

    /**
     * JSON endpoint — authorize and mint a Daily meeting token for the
     * currently logged-in doctor to host consultation {id}.
     *
     * Security mirrors PatientController::joinConsultation():
     *   • User id + role come from PHP session only (never from request).
     *   • The actual doctor_id FK on the consultation is compared to the
     *     session user id server-side; payload-supplied overrides are not
     *     possible.
     *   • Doctor tokens receive Daily is_owner host privileges.
     *   • Token is minted FRESH per call and NEVER stored in MySQL.
     *
     * @week 6 — Video Consultation Integration
     */
    public function joinConsultation(string $id): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
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
            error_log(sprintf('[DoctorController::joinConsultation] Unexpected exception for id=%s: %s', var_export($id, true), $e->getMessage()));
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
     * Show the MBPHA video consultation room (UI page) for doctor {id}.
     *
     * SECURITY NOTE — mirrors PatientController::showConsultationRoom:
     *   Page shell only renders metadata (patient name, date, reason, etc.).
     *   Daily room URL + ephemeral meeting token are NEVER written into the
     *   HTML by PHP.  Both are fetched AFTER page load via fetch() to the
     *   existing joinConsultation() JSON endpoint.
     *
     * @week 6 — Video Consultation Integration
     */
    public function showConsultationRoom(string $id): void
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

        $request = ConsultationRequest::findByIdForDoctor((int) $id, (int) $user->id);
        if ($request === null) {
            Session::flash('status', [
                'type' => 'danger',
                'message' => 'Consultation not found.',
            ]);
            Helper::redirect('/doctor/consultations');
            return;
        }

        $requestStatus = (string) ($request['status'] ?? '');
        if ($requestStatus !== 'Approved') {
            if ($requestStatus === 'Completed') {
                Helper::redirect('/doctor/consultations/' . (int) $request['id']);
                return;
            }

            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The video consultation room is only available for approved consultations.',
            ]);
            Helper::redirect('/doctor/consultations');
            return;
        }

        $otherPartyName  = (string) ($request['patient_name'] ?? 'Your Patient');
        $patientPhone    = trim((string) ($request['patient_phone'] ?? ''));
        $patientGender   = trim((string) ($request['patient_gender'] ?? ''));
        $otherPartyMeta  = trim(implode(' · ', array_filter([$patientGender, $patientPhone], static fn($v) => $v !== '')));
        $clinicalRecord  = DoctorClinicalDocumentationService::ensureDraftForRoom($request, (int) $user->id);
        $canEditClinical = (string) ($request['status'] ?? '') === 'Approved';

        $context = [
            'consultation_id'          => (int) $request['id'],
            'viewer_role'              => 'doctor',
            'other_party_name'         => $otherPartyName,
            'other_party_title'        => 'Patient',
            'other_party_photo'        => $request['patient_photo_path'] ?? null,
            'other_party_meta'         => $otherPartyMeta !== '' ? $otherPartyMeta : null,
            'consultation_date'        => (string) ($request['consultation_date'] ?? ''),
            'consultation_start_time'  => (string) ($request['start_time'] ?? ''),
            'consultation_end_time'    => (string) ($request['end_time'] ?? ''),
            'consultation_reason'      => (string) ($request['reason'] ?? ''),
            'complaint_image_url'      => ComplaintImageService::existsOnRequest($request)
                ? ComplaintImageService::viewerUrl('doctor', (int) $request['id'])
                : '',
            'consultation_status'      => (string) ($request['status'] ?? 'Pending'),
            'join_token_endpoint'      => Helper::url('/doctor/consultations/' . (int) $request['id'] . '/join-token'),
            'clinical_save_endpoint'   => Helper::url('/doctor/consultations/' . (int) $request['id'] . '/clinical-record'),
            'complete_endpoint'        => Helper::url('/doctor/consultations/' . (int) $request['id'] . '/complete'),
            'prescription_path'        => Helper::url('/doctor/consultations/' . (int) $request['id'] . '/prescription'),
            'clinical_record'          => $clinicalRecord,
            'clinical_can_edit'        => $canEditClinical,
            'csrf_token'               => Csrf::generate(),
            'return_path'              => Helper::url('/doctor/consultations'),
            'return_path_label'        => 'Back to Consultations',
        ];

        $this->render('doctor/consultations/room', [
            'title' => 'Video Consultation | MBPHA TeleHealth Consultation System',
            'user'  => $user,
            'dashboardRole'      => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle'     => 'Video Consultation',
            'dashboardDescription' => sprintf('Live consultation with %s.', $otherPartyName),
            'sidebarItems' => [
                ['path' => '/doctor/dashboard',  'label' => 'Dashboard',          'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations',   'icon' => 'bi-calendar2-check-fill'],
                ['path' => '/doctor/availability', 'label' => 'Availability',     'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile',      'label' => 'My Profile',       'icon' => 'bi-person-circle'],
            ],
            'pageStyles'  => '<link rel="stylesheet" href="' . Helper::asset('css/consultation-room.css') . '">',
            'pageScripts' => '',
            'context' => $context,
            'statusMessage' => Session::getFlash('status'),
        ], 'layouts/dashboard');
    }

    /**
     * JSON endpoint — save a Draft clinical record while the assigned
     * doctor is in (or preparing) the live consultation room.
     *
     * Ownership, patient id, and doctor id are taken from the session and
     * the consultation_requests row. Client-supplied identity fields are
     * ignored. The record remains Draft; this action does not complete
     * the consultation or create a prescription.
     */
    public function saveClinicalRecordDraft(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->jsonResponse([
                'ok' => false,
                'code' => 'method_not_allowed',
                'message' => 'This action must be submitted as a POST request.',
            ], 405);
            return;
        }

        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            $this->jsonResponse([
                'ok' => false,
                'code' => 'unauthenticated',
                'message' => 'Please sign in as the assigned doctor to save clinical notes.',
            ], 401);
            return;
        }

        $user = AuthService::getUser();
        if ($user === null || $user->id === null) {
            $this->jsonResponse([
                'ok' => false,
                'code' => 'unauthenticated',
                'message' => 'Please sign in as the assigned doctor to save clinical notes.',
            ], 401);
            return;
        }

        if (!$this->requireJsonCsrf()) {
            return;
        }

        $result = DoctorClinicalDocumentationService::saveDraft(
            (int) $user->id,
            (int) $id,
            (string) ($_POST['_token'] ?? ''),
            $_POST
        );

        $payload = [
            'ok' => (bool) ($result['ok'] ?? false),
            'code' => (string) ($result['code'] ?? 'error'),
            'message' => (string) ($result['message'] ?? 'The clinical draft could not be saved.'),
            'record_status' => 'Draft',
            'csrf_token' => Csrf::generate(),
        ];

        if (($result['ok'] ?? false) === true && is_array($result['record'] ?? null)) {
            $record = $result['record'];
            $payload['record_id'] = (int) ($record['id'] ?? 0);
            $payload['saved_at'] = (string) ($record['updated_at'] ?? '');
            $payload['record_status'] = (string) ($record['record_status'] ?? 'Draft');
        }

        $this->jsonResponse($payload, (int) ($result['http_code'] ?? 400));
    }

    public function showConsultationDetails(string $id): void
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

        $page = PatientClinicalRecordService::getHistoricalRecordForDoctor((int) $user->id, (int) $id);
        if ($page === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested consultation record could not be found.',
            ]);
            Helper::redirect('/doctor/consultations');
            return;
        }

        $request = $page['request'];
        $patientName = (string) ($request['patient_name'] ?? 'Patient');
        $isCompleted = (string) ($request['status'] ?? '') === 'Completed';
        $videoJoin = self::computeDoctorVideoJoinContext($request)['videoJoin'] ?? null;

        $this->render('doctor/consultations/show', [
            'title' => ($isCompleted ? 'Consultation Record' : 'Consultation Details') . ' | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => $isCompleted ? 'Consultation Record' : 'Consultation Details',
            'dashboardDescription' => $isCompleted
                ? sprintf('Completed clinical record for %s.', $patientName)
                : sprintf('Consultation details for %s.', $patientName),
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-clipboard2-pulse'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-vcard'],
            ],
            'pageStyles' => '<link rel="stylesheet" href="' . Helper::asset('css/consultation-record.css') . '"><link rel="stylesheet" href="' . Helper::asset('css/prescription.css') . '">',
            'request' => $request,
            'clinicalRecord' => $page['record'] ?? null,
            'prescriptions' => $page['prescriptions'] ?? [],
            'videoJoin' => is_array($videoJoin) ? $videoJoin : null,
            'viewerRole' => 'doctor',
            'statusMessage' => Session::getFlash('status'),
        ], 'layouts/dashboard');
    }

    public function showPrescription(string $id): void
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

        $page = DoctorPrescriptionService::getPrescriptionPageForDoctor((int) $user->id, (int) $id);
        if ($page === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'Consultation not found.',
            ]);
            Helper::redirect('/doctor/consultations');
            return;
        }

        $patientName = (string) ($page['request']['patient_name'] ?? 'Patient');
        $this->render('doctor/consultations/prescription', [
            'title' => 'Prescription | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Prescription',
            'dashboardDescription' => sprintf('Prescription for %s.', $patientName),
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-calendar2-check-fill'],
                ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                ['path' => '/doctor/profile', 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ],
            'pageStyles' => '<link rel="stylesheet" href="' . Helper::asset('css/prescription.css') . '">',
            'request' => $page['request'],
            'record' => $page['record'],
            'prescriptions' => $page['prescriptions'],
            'canCreate' => $page['can_create'],
            'hasSignature' => (bool) ($page['has_signature'] ?? false),
            'csrfToken' => Csrf::generate(),
            'pageScripts' => ((bool) ($page['can_create'] ?? false))
                ? '<script src="' . Helper::asset('js/consultation-prescription.js') . '" defer></script>'
                : '',
            'statusMessage' => Session::getFlash('status'),
        ], 'layouts/dashboard');
    }

    public function savePrescription(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/doctor/consultations/' . (int) $id . '/prescription');
            return;
        }

        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();
        if ($user === null || $user->id === null) {
            Helper::redirect('/login');
            return;
        }

        $result = DoctorPrescriptionService::createPrescription(
            (int) $user->id,
            (int) $id,
            (string) ($_POST['_token'] ?? ''),
            $_POST
        );

        Session::flash('status', [
            'type' => $result['type'] ?? (($result['success'] ?? false) ? 'success' : 'danger'),
            'message' => $result['message'] ?? 'Prescription request completed.',
        ]);

        Helper::redirect('/doctor/consultations/' . (int) $id . '/prescription');
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
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            Helper::redirect('/login');
            return;
        }

        ComplaintImageService::streamForCurrentUser((int) $id);
    }

    /**
     * Stream a finalized consultation-record PDF for the assigned doctor.
     */
    private function streamConsultationRecordPdf(string $id): void
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

        $requestId = (int) $id;
        $owned = ConsultationRequest::findByIdForDoctor($requestId, (int) $user->id);
        if ($owned === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested consultation record could not be found.',
            ]);
            Helper::redirect('/doctor/consultations');
            return;
        }

        $page = PatientClinicalRecordService::getPrintableDocumentForDoctor((int) $user->id, $requestId, 'record');
        $built = is_array($page) ? ConsultationRecordPdfService::buildFromAuthorizedPage($page) : null;
        if ($built === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The consultation record could not be downloaded. Please try again.',
            ]);
            Helper::redirect('/doctor/consultations/' . $requestId);
            return;
        }

        ConsultationRecordPdfService::stream($built);
    }

    /**
     * Stream an issued prescription PDF for the assigned doctor.
     * Read-only: authorization uses the doctor's own consultation, then
     * the stored prescription rows. No prescription is created or updated.
     */
    private function streamPrescriptionPdf(string $id): void
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

        $requestId = (int) $id;
        $owned = ConsultationRequest::findByIdForDoctor($requestId, (int) $user->id);
        if ($owned === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested prescription could not be found.',
            ]);
            Helper::redirect('/doctor/consultations');
            return;
        }

        $page = PatientClinicalRecordService::getPrintableDocumentForDoctor((int) $user->id, $requestId, 'prescription');
        $built = is_array($page) ? PrescriptionPdfService::buildFromAuthorizedPage($page) : null;
        if ($built === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The prescription could not be downloaded. Please try again.',
            ]);
            Helper::redirect('/doctor/consultations/' . $requestId . '/prescription');
            return;
        }

        PrescriptionPdfService::stream($built);
    }
}
