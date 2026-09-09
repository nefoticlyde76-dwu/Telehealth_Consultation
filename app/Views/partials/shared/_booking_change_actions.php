<?php

use App\Helpers\Helper;
use App\Helpers\Status;
use App\Services\PatientConsultationBookingService;

$request = is_array($request ?? null) ? $request : [];
$requestId = (int) ($request['id'] ?? 0);
$csrfToken = (string) ($csrfToken ?? '');
$variant = (string) ($bookingChangeVariant ?? 'header');
$bookingChange = is_array($request['bookingChange'] ?? null)
    ? $request['bookingChange']
    : (is_array($bookingChange ?? null) ? $bookingChange : PatientConsultationBookingService::changeEligibility($request));
$actions = Status::consultationUiActions((string) ($request['status'] ?? ''), [
    'role' => 'patient',
    'can_cancel' => !empty($bookingChange['can_cancel']),
    'can_reschedule' => !empty($bookingChange['can_reschedule']),
]);
$blockedReason = trim((string) ($bookingChange['blocked_reason'] ?? ''));
$isTable = $variant === 'table';
$cancelClass = $isTable ? 'btn btn-outline-danger btn-sm' : 'cr-btn';
$rescheduleClass = $isTable ? 'btn btn-outline-primary btn-sm' : 'cr-btn';

if ($requestId <= 0 || $csrfToken === '') {
    return;
}

if ($actions['reschedule']): ?>
  <a href="<?= Helper::url('/patient/consultation-requests/' . $requestId . '/reschedule') ?>"
     class="<?= Helper::escape($rescheduleClass) ?>"
     aria-label="Reschedule this consultation">
    <i class="bi bi-calendar2-week me-1"></i>
    Reschedule
  </a>
<?php endif; ?>
<?php if ($actions['cancel']): ?>
  <form method="POST"
        action="<?= Helper::url('/patient/consultation-requests/' . $requestId . '/cancel') ?>"
        class="<?= $isTable ? 'd-inline' : '' ?>"
        data-confirm-title="Cancel this consultation?"
        data-confirm-body="The booked slot will be released. This cannot be undone from this page."
        data-confirm-hint="You can book a new slot later if you still need a consultation."
        data-confirm-tone="danger"
        data-confirm-action="Cancel booking">
    <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
    <button type="submit" class="<?= Helper::escape($cancelClass) ?>" aria-label="Cancel this consultation">
      <i class="bi bi-slash-circle me-1"></i>
      Cancel
    </button>
  </form>
<?php elseif (!$isTable && in_array((string) ($request['status'] ?? ''), [Status::PENDING, Status::APPROVED], true) && $blockedReason !== ''): ?>
  <p class="text-muted small mb-0 align-self-center"><?= Helper::escape($blockedReason) ?></p>
<?php endif; ?>
