<?php

/**
 * Search, filter, sort, and pagination checks.
 *
 * Usage: php bin/test_list_filters.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Helpers\ListFilter;
use App\Models\ConsultationRequest;
use App\Models\DoctorAvailability;
use App\Services\AdminConsultationService;
use App\Services\DoctorAvailabilityService;
use App\Services\DoctorConsultationService;
use App\Services\PatientConsultationBookingService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');
date_default_timezone_set('Pacific/Port_Moresby');

$failed = 0;
$passed = 0;

function expect_true(bool $condition, string $label): void
{
    global $failed, $passed;
    if ($condition) {
        $passed++;
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$tomorrow = (new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');

$search = ListFilter::normalizeSearch("  Jane   Doe  ");
expect_true($search === 'Jane Doe', 'Search collapses extra spaces and trims');

$like = ListFilter::likeContains('%_test');
expect_true(str_contains($like, '\\%') && str_contains($like, '\\_'), 'LIKE wildcards in search text are escaped');

$sort = ListFilter::allowedValue('id;DROP TABLE users', ['newest', 'oldest', 'date_asc'], 'date_asc');
expect_true($sort === 'date_asc', 'Untrusted sort values fall back to the whitelist default');

$perPage = ListFilter::allowedPerPage('999', 10);
expect_true($perPage === 10, 'Untrusted page size falls back to the default');
expect_true(ListFilter::allowedPerPage(25, 10) === 25, 'Allowed page size 25 is accepted');

$todayRange = ListFilter::resolveDateRange('today');
expect_true($todayRange['preset'] === 'today' && $todayRange['from'] === $today && $todayRange['to'] === $today, 'Today preset resolves to the current local date');

$tomorrowRange = ListFilter::resolveDateRange('tomorrow');
expect_true($tomorrowRange['from'] === $tomorrow && $tomorrowRange['to'] === $tomorrow, 'Tomorrow preset resolves to the next local date');

$bogusRange = ListFilter::resolveDateRange('next_year');
expect_true($bogusRange['preset'] === '' && $bogusRange['from'] === '', 'Unknown date presets are ignored');

$customRange = ListFilter::resolveDateRange('custom', '2026-08-20', '2026-08-10');
expect_true($customRange['from'] === '2026-08-10' && $customRange['to'] === '2026-08-20', 'Custom date range is ordered from earliest to latest');

$legacyRange = ListFilter::resolveDateRange('', '', '', '2026-08-19');
expect_true($legacyRange['preset'] === 'custom' && $legacyRange['from'] === '2026-08-19', 'Legacy exact dates map to a custom one-day range');

$pagination = ListFilter::paginate(99, 47, 10);
expect_true(
    $pagination['current_page'] === 5
    && $pagination['total_pages'] === 5
    && $pagination['from'] === 41
    && $pagination['to'] === 47,
    'Pagination clamps the page and reports the visible range'
);

$emptyPagination = ListFilter::paginate(1, 0, 10);
expect_true($emptyPagination['from'] === 0 && $emptyPagination['to'] === 0 && $emptyPagination['total_pages'] === 1, 'Empty lists report zero visible rows');

$pages = ListFilter::pageNumbers(5, 20);
expect_true($pages[0] === 1 && in_array(0, $pages, true) && end($pages) === 20, 'Page windows include first, last, and ellipsis markers');

$adminFilters = AdminConsultationService::normalizeFilters([
    'search' => '  jane  ',
    'status' => 'Pending',
    'doctor_id' => '12',
    'date' => 'today',
    'sort' => 'newest',
]);
expect_true($adminFilters['search'] === 'jane', 'Admin search is normalized');
expect_true($adminFilters['status'] === 'Pending', 'Admin status whitelist accepts Pending');
expect_true((int) $adminFilters['doctor_id'] === 12, 'Admin doctor filter uses a numeric id');
expect_true($adminFilters['date'] === 'today' && $adminFilters['date_range']['from'] === $today, 'Admin today filter is resolved server-side');
expect_true($adminFilters['sort'] === 'newest', 'Admin sort whitelist accepts newest');

$adminDefault = AdminConsultationService::normalizeFilters([]);
expect_true($adminDefault['status'] === 'Pending' && $adminDefault['sort'] === 'date_asc', 'Admin default queue stays Pending with soonest consultation first');

$adminAll = AdminConsultationService::normalizeFilters(['status' => '', 'sort' => 'created_at']);
expect_true($adminAll['status'] === '' && $adminAll['sort'] === 'date_asc', 'Admin all-status is preserved and a bad sort is rejected');

$workspace = AdminConsultationService::workspacePath($adminFilters, 44, 2);
expect_true(
    str_contains($workspace, 'status=Pending')
    && str_contains($workspace, 'doctor_id=12')
    && str_contains($workspace, 'date=today')
    && str_contains($workspace, 'sort=newest')
    && str_contains($workspace, 'selected=44')
    && str_contains($workspace, 'page=2'),
    'Admin workspace URLs preserve non-sensitive filter state'
);

$nextId = AdminConsultationService::nextQueueRequestId([10, 20, 30], 20);
expect_true($nextId === 30, 'Approve & Next stays inside the current filtered id list');

$doctorFilters = (new ReflectionClass(DoctorConsultationService::class))
    ->getMethod('normalizeFilters');
$doctorFilters->setAccessible(true);
$normalizedDoctor = $doctorFilters->invoke(null, [
    'search' => ' Jane ',
    'status' => 'Completed',
    'date' => 'this_month',
    'sort' => 'date_desc',
    'per_page' => '25',
]);
expect_true(
    $normalizedDoctor['search'] === 'Jane'
    && $normalizedDoctor['status'] === 'Completed'
    && $normalizedDoctor['date'] === 'this_month'
    && $normalizedDoctor['sort'] === 'date_desc'
    && (int) $normalizedDoctor['per_page'] === 25,
    'Doctor consultation filters are normalized with a sort whitelist'
);

$patientFilters = (new ReflectionClass(PatientConsultationBookingService::class))
    ->getMethod('normalizeFilters');
$patientFilters->setAccessible(true);
$normalizedPatient = $patientFilters->invoke(null, [
    'search' => ' paracetamol ',
    'status' => 'Completed',
    'date' => 'upcoming',
    'documents' => 'prescription',
    'sort' => 'newest',
    'patient_id' => '999999',
]);
expect_true(
    $normalizedPatient['search'] === 'paracetamol'
    && $normalizedPatient['status'] === 'Completed'
    && $normalizedPatient['documents'] === 'prescription'
    && !isset($normalizedPatient['patient_id']),
    'Patient filters ignore identity parameters from the query string'
);

$normalizedRejected = $patientFilters->invoke(null, ['status' => 'Rejected']);
expect_true($normalizedRejected['status'] === 'Rejected', 'Patient filters accept Rejected');
expect_true(
    PatientConsultationBookingService::getStatusOptions() === ['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled', 'No-Show'],
    'Patient status options match the canonical consultation labels'
);

$availabilityFilters = (new ReflectionClass(DoctorAvailabilityService::class))
    ->getMethod('normalizeFilters');
$availabilityFilters->setAccessible(true);
$normalizedAvailability = $availabilityFilters->invoke(null, [
    'status' => 'Booked',
    'date' => 'future',
    'sort' => 'latest',
]);
expect_true(
    $normalizedAvailability['status'] === 'Booked'
    && $normalizedAvailability['date'] === 'future'
    && $normalizedAvailability['sort'] === 'latest'
    && $normalizedAvailability['date_range']['from'] === $today,
    'Doctor availability future/latest filters resolve without trusting raw SQL'
);

try {
    $db = Database::getInstance();
} catch (Throwable $exception) {
    $db = null;
    echo "SKIP  Database checks (" . $exception->getMessage() . ")\n";
}

if ($db instanceof PDO) {
    $patientId = (int) $db->query(
        "SELECT users.id
           FROM users
           INNER JOIN roles ON roles.id = users.role_id
          WHERE roles.name = 'patient'
          ORDER BY users.id ASC
          LIMIT 1"
    )->fetchColumn();
    $otherPatientId = (int) $db->query(
        "SELECT users.id
           FROM users
           INNER JOIN roles ON roles.id = users.role_id
          WHERE roles.name = 'patient'
            AND users.id <> {$patientId}
          ORDER BY users.id ASC
          LIMIT 1"
    )->fetchColumn();
    $doctorId = (int) $db->query(
        "SELECT users.id
           FROM users
           INNER JOIN roles ON roles.id = users.role_id
          WHERE roles.name = 'doctor'
          ORDER BY users.id ASC
          LIMIT 1"
    )->fetchColumn();
    $otherDoctorId = (int) $db->query(
        "SELECT users.id
           FROM users
           INNER JOIN roles ON roles.id = users.role_id
          WHERE roles.name = 'doctor'
            AND users.id <> {$doctorId}
          ORDER BY users.id ASC
          LIMIT 1"
    )->fetchColumn();

    expect_true($patientId > 0 && $doctorId > 0, 'Test patient and doctor accounts exist');

    if ($patientId > 0 && $otherPatientId > 0) {
        $ownedIds = [];
        $ownedStmt = $db->prepare('SELECT id FROM consultation_requests WHERE patient_id = :patient_id');
        $ownedStmt->execute([':patient_id' => $patientId]);
        foreach ($ownedStmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [] as $ownedId) {
            $ownedIds[(int) $ownedId] = true;
        }

        $leaked = false;
        foreach (ConsultationRequest::findForPatient($otherPatientId, 50, 0, ['search' => 'a']) as $row) {
            if (isset($ownedIds[(int) ($row['id'] ?? 0)])) {
                $leaked = true;
                break;
            }
        }
        $patientPage = PatientConsultationBookingService::getHistoryPageData($patientId, [
            'status' => 'Completed',
            'sort' => 'newest',
        ]);
        foreach ($patientPage['requests'] ?? [] as $row) {
            if ((int) ($row['id'] ?? 0) > 0 && (string) ($row['status'] ?? '') !== 'Completed') {
                $leaked = true;
            }
        }
        expect_true(!$leaked, 'Patient history cannot be filtered into another patient or a non-matching status');
    } else {
        echo "SKIP  Second patient account not available for isolation check\n";
    }

    if ($doctorId > 0 && $otherDoctorId > 0) {
        $leaked = false;
        $otherDoctorRows = ConsultationRequest::findForDoctor($otherDoctorId, ['search' => 'a', 'sort' => 'newest'], 50, 0);
        foreach ($otherDoctorRows as $row) {
            if ((int) ($row['id'] ?? 0) > 0) {
                $assigned = $db->prepare('SELECT doctor_id FROM consultation_requests WHERE id = :id');
                $assigned->execute([':id' => (int) $row['id']]);
                if ((int) $assigned->fetchColumn() === $doctorId) {
                    $leaked = true;
                    break;
                }
            }
        }
        expect_true(!$leaked, 'Doctor consultation filters cannot return another doctor\'s consultations');

        $slotLeak = false;
        foreach (DoctorAvailability::findForDoctor($otherDoctorId, ['date' => 'future', 'sort' => 'latest'], 50, 0) as $slot) {
            if ((int) ($slot['doctor_id'] ?? 0) === $doctorId) {
                $slotLeak = true;
                break;
            }
        }
        expect_true(!$slotLeak, 'Doctor availability filters stay scoped to the signed-in doctor');
    } else {
        echo "SKIP  Second doctor account not available for isolation check\n";
    }

    $adminPage = AdminConsultationService::getManagementPageData([
        'status' => 'Pending',
        'date' => 'today',
        'sort' => 'date_asc',
    ]);
    $adminOk = true;
    foreach ($adminPage['requests'] ?? [] as $row) {
        if ((string) ($row['status'] ?? '') !== 'Pending') {
            $adminOk = false;
            break;
        }
    }
    expect_true($adminOk, 'Admin combined Pending + Today filter only returns pending requests');
    expect_true(isset($adminPage['pagination']['total_items']), 'Admin queue reports a result count');
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
