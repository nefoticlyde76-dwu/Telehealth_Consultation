<?php

/**
 * Centralized status system checks.
 *
 * Usage: php bin/test_status_system.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\Status;
use App\Models\ConsultationRequest;
use App\Services\AdminConsultationService;
use App\Services\DoctorConsultationService;
use App\Services\PatientConsultationBookingService;

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

$canonical = Status::consultationKeys();
expect_true(
    $canonical === ['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'],
    'Consultation status keys stay Pending, Approved, Rejected, Completed, Cancelled'
);

expect_true(Status::label('Pending') === 'Pending', 'Pending label is Pending');
expect_true(Status::label('Approved') === 'Approved', 'Approved label is Approved');
expect_true(Status::label('Rejected') === 'Rejected', 'Rejected label is Rejected');
expect_true(Status::label('Completed') === 'Completed', 'Completed label is Completed');
expect_true(Status::label('Cancelled') === 'Cancelled', 'Cancelled label is Cancelled');
expect_true(Status::label('canceled') === 'Cancelled', 'Canceled alias maps to Cancelled');

expect_true(
    Status::badgeClass('Approved') !== Status::badgeClass('Completed'),
    'Approved and Completed use distinct badge classes'
);
expect_true(
    Status::badgeClass('Rejected') !== Status::badgeClass('Cancelled'),
    'Rejected and Cancelled use distinct badge classes'
);
expect_true(Status::badgeClass('Pending') === 'ux-badge--pending', 'Pending uses the pending badge class');
expect_true(Status::badgeClass('Approved') === 'ux-badge--approved', 'Approved uses the approved badge class');
expect_true(Status::badgeClass('Completed') === 'ux-badge--completed', 'Completed uses the completed badge class');
expect_true(Status::badgeClass('Rejected') === 'ux-badge--rejected', 'Rejected uses the rejected badge class');
expect_true(Status::badgeClass('Cancelled') === 'ux-badge--cancelled', 'Cancelled uses the cancelled badge class');

$unknown = Status::resolve('TotallyInvented');
expect_true($unknown['known'] === false, 'Unknown statuses are marked unknown');
expect_true($unknown['label'] === 'Unknown Status', 'Unknown statuses display Unknown Status');
expect_true($unknown['category'] !== 'completed' && $unknown['category'] !== 'approved', 'Unknown statuses are not silently treated as Completed or Approved');
expect_true(!str_contains($unknown['label'], 'TotallyInvented'), 'Unknown badges do not expose the raw database value');

expect_true(Status::label('Draft', Status::DOMAIN_RECORD) === 'Draft', 'Clinical record Draft stays Draft');
expect_true(Status::label('Final', Status::DOMAIN_RECORD) === 'Final', 'Clinical record Final stays Final');
expect_true(
    Status::badgeClass('Draft', Status::DOMAIN_RECORD) !== Status::badgeClass('Completed'),
    'Clinical record Draft is visually distinct from consultation Completed'
);

expect_true(Status::label('active', Status::DOMAIN_USER) === 'Active', 'User active displays as Active');
expect_true(Status::label('inactive', Status::DOMAIN_USER) === 'Deactivated', 'User inactive displays as Deactivated');
expect_true(Status::label('suspended', Status::DOMAIN_USER) === 'Suspended', 'User suspended displays as Suspended');
expect_true(Status::label('deleted', Status::DOMAIN_USER) === 'Deleted', 'User deleted displays as Deleted');
expect_true(Status::label('deactivated', Status::DOMAIN_USER) === 'Deactivated', 'Deactivated alias maps to inactive');
expect_true(Status::label('invitation_pending', Status::DOMAIN_USER) === 'Invitation pending', 'User invitation_pending displays as Invitation pending');

expect_true(
    Status::userKeys() === [
        Status::USER_INVITATION_PENDING,
        Status::USER_ACTIVE,
        Status::USER_SUSPENDED,
        Status::USER_INACTIVE,
        Status::USER_DELETED,
    ],
    'userKeys includes invitation_pending then the existing user statuses'
);
expect_true(
    Status::normalizeKey(Status::DOMAIN_USER, 'invitation_pending') === Status::USER_INVITATION_PENDING,
    'normalizeKey recognizes invitation_pending'
);
expect_true(
    Status::isKnown('invitation_pending', Status::DOMAIN_USER),
    'invitation_pending is a known user status'
);
expect_true(
    Status::canAuthenticateUserStatus(Status::USER_INVITATION_PENDING) === false,
    'invitation_pending cannot authenticate'
);
expect_true(
    !in_array(Status::USER_INVITATION_PENDING, Status::assignableUserKeys(), true),
    'invitation_pending is not an administrator-assignable status'
);
expect_true(
    Status::canAssignUserStatus(Status::USER_INVITATION_PENDING) === false,
    'canAssignUserStatus rejects invitation_pending'
);
expect_true(
    Status::isInvitationPendingUserStatus(Status::USER_INVITATION_PENDING),
    'isInvitationPendingUserStatus recognizes invitation_pending'
);
expect_true(
    Status::isInvitationPendingUserStatus(Status::USER_ACTIVE) === false,
    'isInvitationPendingUserStatus rejects active'
);
expect_true(
    in_array(Status::USER_INVITATION_PENDING, Status::filterableUserKeys(), true),
    'filterableUserKeys includes invitation_pending'
);
expect_true(
    !in_array(Status::USER_DELETED, Status::filterableUserKeys(), true),
    'filterableUserKeys excludes deleted'
);
expect_true(
    Status::filterableUserKeys() !== Status::assignableUserKeys(),
    'filterable user keys are not the same as assignable keys'
);
expect_true(
    Status::iconClass('invitation_pending', Status::DOMAIN_USER) === 'bi-envelope',
    'invitation_pending uses the envelope icon'
);
expect_true(
    Status::badgeClass('invitation_pending', Status::DOMAIN_USER) === 'ux-badge--pending',
    'invitation_pending uses the pending badge class'
);
expect_true(
    Status::description('invitation_pending', Status::DOMAIN_USER) !== '',
    'invitation_pending has a description'
);

expect_true(Status::canAuthenticateUserStatus(Status::USER_ACTIVE) === true, 'active can authenticate');
expect_true(in_array(Status::USER_ACTIVE, Status::assignableUserKeys(), true), 'active is administrator-assignable');
expect_true(Status::canAuthenticateUserStatus(Status::USER_SUSPENDED) === false, 'suspended cannot authenticate');
expect_true(in_array(Status::USER_SUSPENDED, Status::assignableUserKeys(), true), 'suspended is administrator-assignable');
expect_true(Status::canAuthenticateUserStatus(Status::USER_INACTIVE) === false, 'inactive cannot authenticate');
expect_true(in_array(Status::USER_INACTIVE, Status::assignableUserKeys(), true), 'inactive is administrator-assignable');
expect_true(Status::canAuthenticateUserStatus(Status::USER_DELETED) === false, 'deleted cannot authenticate');
expect_true(!in_array(Status::USER_DELETED, Status::assignableUserKeys(), true), 'deleted is not administrator-assignable');
expect_true(Status::isDeletedUserStatus(Status::USER_DELETED) === true, 'deleted keeps deletion semantics');
expect_true(Status::isDeletedUserStatus(Status::USER_INVITATION_PENDING) === false, 'invitation_pending is not a deleted status');
expect_true(Status::label('Available', Status::DOMAIN_SLOT) === 'Available', 'Slot Available stays Available');
expect_true(Status::label('Booked', Status::DOMAIN_SLOT) === 'Booked', 'Slot Booked stays Booked');

$patientOptions = PatientConsultationBookingService::getStatusOptions();
expect_true(in_array('Rejected', $patientOptions, true), 'Patient filters include Rejected');
expect_true($patientOptions === Status::consultationKeys(), 'Patient filter values match the central consultation keys');
expect_true(AdminConsultationService::getStatusOptions() === Status::consultationKeys(), 'Admin filter values match the central consultation keys');
expect_true(DoctorConsultationService::getStatusOptions() === Status::consultationKeys(), 'Doctor filter values match the central consultation keys');

$filterLabels = array_column(Status::filterOptions(Status::DOMAIN_CONSULTATION), 'label');
expect_true($filterLabels === ['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'], 'Filter labels match badge labels');

expect_true(Status::canAdminTransition('Pending', 'Approved'), 'Admin may approve Pending');
expect_true(Status::canAdminTransition('Pending', 'Rejected'), 'Admin may reject Pending');
expect_true(Status::canAdminTransition('Approved', 'Cancelled'), 'Admin may cancel Approved');
expect_true(!Status::canAdminTransition('Pending', 'Completed'), 'Admin may not complete a request');
expect_true(!Status::canAdminTransition('Approved', 'Completed'), 'Admin may not mark Approved as Completed');
expect_true(!Status::canAdminTransition('Rejected', 'Approved'), 'Rejected cannot return to Approved');
expect_true(!Status::canAdminTransition('Completed', 'Approved'), 'Completed cannot return to Approved');
expect_true(!in_array('Completed', Status::adminActionableStatuses(), true), 'Completed is not an admin-postable status');

$pendingActions = Status::consultationUiActions('Pending', ['role' => 'admin']);
expect_true($pendingActions['approve'] && $pendingActions['reject'] && !$pendingActions['join'], 'Pending admin actions are review-only');

$approvedPatient = Status::consultationUiActions('Approved', [
    'role' => 'patient',
    'join_status' => 'open',
    'can_join' => true,
    'join_url' => '/patient/consultations/1/room',
]);
expect_true($approvedPatient['join'] && $approvedPatient['join_enabled'] && !$approvedPatient['view_record'], 'Approved patients can join and cannot view a completed record');
expect_true(!$approvedPatient['cancel'] && !$approvedPatient['reschedule'], 'Patients cannot cancel or reschedule without eligibility flags');

$pendingPatientChange = Status::consultationUiActions('Pending', [
    'role' => 'patient',
    'can_cancel' => true,
    'can_reschedule' => true,
]);
expect_true($pendingPatientChange['cancel'] && $pendingPatientChange['reschedule'], 'Patients can cancel or reschedule when the cutoff allows it');

$completedPatient = Status::consultationUiActions('Completed', [
    'role' => 'patient',
    'has_final_record' => true,
    'has_prescription' => true,
    'join_status' => 'open',
    'can_join' => true,
    'join_url' => '/patient/consultations/1/room',
]);
expect_true(
    $completedPatient['view_record']
    && $completedPatient['view_prescription']
    && $completedPatient['download_record']
    && !$completedPatient['join'],
    'Completed patients see record actions and cannot join'
);

$rejectedPatient = Status::consultationUiActions('Rejected', [
    'role' => 'patient',
    'join_status' => 'open',
    'can_join' => true,
    'join_url' => '/patient/consultations/1/room',
]);
expect_true(!$rejectedPatient['join'] && !$rejectedPatient['view_record'], 'Rejected patients cannot join or open a completed record');

$cancelledDoctor = Status::consultationUiActions('Cancelled', [
    'role' => 'doctor',
    'join_status' => 'open',
    'join_url' => '/doctor/consultations/1/room',
    'can_join' => true,
]);
expect_true(!$cancelledDoctor['join'] && !$cancelledDoctor['review_complete'], 'Cancelled doctors cannot join or complete');

$badgeHtml = Status::badgeHtml('Approved');
expect_true(str_contains($badgeHtml, 'Approved') && str_contains($badgeHtml, 'ux-badge--approved'), 'Badge HTML includes the label and class');
expect_true(str_contains($badgeHtml, 'bi-check-circle-fill'), 'Badge HTML includes an icon in addition to the text label');

$chart = Status::consultationDistributionChart(['Pending' => 2, 'Approved' => 1]);
expect_true($chart['data']['labels'] === ['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'], 'Chart labels use the same status names as badges');

expect_true(
    Status::filteredListUrl('/admin/consultation-requests', 'Pending') === '/admin/consultation-requests?status=Pending',
    'Dashboard status summaries link to the matching filtered list'
);

$reflection = new ReflectionClass(ConsultationRequest::class);
$source = file_get_contents($reflection->getFileName() ?: '');
expect_true(
    is_string($source) && str_contains($source, 'Status::adminConsultationTransitions()'),
    'ConsultationRequest uses the centralized admin transition map'
);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
