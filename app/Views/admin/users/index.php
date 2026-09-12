<?php
use App\Helpers\Helper;
use App\Helpers\ListFilter;
use App\Helpers\Status;
use App\Services\AccountSecurityService;

require_once __DIR__ . '/../../partials/profile/_helpers.php';

$csrfToken = (string) ($csrfToken ?? '');
$filters = is_array($filters ?? null) ? $filters : [];
$status = (string) ($filters['status'] ?? '');
$users = is_array($users ?? null) ? $users : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$summary = is_array($summary ?? null) ? $summary : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$actorUserId = (int) ($actorUserId ?? 0);
$confirmationPhrase = AccountSecurityService::CONFIRMATION_PHRASE;

require __DIR__ . '/../../partials/shared/status_helper.php';

$filterTabs = array_merge(
    [['value' => '', 'label' => 'All']],
    array_map(static fn (string $opt): array => [
        'value' => $opt,
        'label' => Status::label($opt, Status::DOMAIN_USER),
    ], $statusOptions)
);
$filterTabCurrent = $status;
$filterTabAria = 'Filter users by status';
$filterTabUrl = static function (string $value) use ($filters): string {
    return ListFilter::url('/admin/users', $filters, ['status' => $value, 'page' => 1]);
};

$search = (string) ($filters['search'] ?? '');
$filterForm = [
    'id' => 'tf-users',
    'action' => Helper::url('/admin/users'),
    'clear_url' => Helper::url('/admin/users'),
    'active' => $search !== '' || $status !== '',
    'hidden' => $status !== '' ? ['status' => $status] : [],
    'search' => [
        'name' => 'search',
        'value' => $search,
        'placeholder' => 'Search users...',
        'label' => 'Search',
    ],
];
$tableFilterId = 'tf-users';
?>

<section class="mb-4">
  <?php
  $pageHeaderTitle = 'Users';
  $pageHeaderSubtitle = 'Manage system accounts and access. Clinical consultation content is not shown here.';
  $pageHeaderIcon = 'bi-people';
  $pageHeaderHeadingTag = 'h2';
  $pageHeaderBreadcrumbs = [
      ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
      ['label' => 'Users', 'active' => true],
  ];
  ob_start();
  ?>
  <a href="<?= Helper::url('/admin/doctors/create') ?>" class="btn btn-primary">
    <i class="bi bi-person-plus me-1"></i>
    Add Doctor
  </a>
  <?php
  $pageHeaderActions = ob_get_clean();
  require __DIR__ . '/../../partials/dashboard/page_header.php';
  ?>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <?php
  $summaryStats = [
      [
          'label' => 'Total',
          'value' => (int) ($summary['total_users'] ?? 0),
          'icon' => 'bi-people',
          'tone' => 'pending',
          'url' => '/admin/users',
      ],
      [
          'label' => 'Active',
          'value' => (int) ($summary['active_users'] ?? 0),
          'icon' => 'bi-person-check',
          'tone' => 'success',
          'url' => '/admin/users?status=' . rawurlencode(Status::USER_ACTIVE),
      ],
      [
          'label' => 'Suspended',
          'value' => (int) ($summary['suspended_users'] ?? 0),
          'icon' => 'bi-pause-circle',
          'tone' => 'warning',
          'url' => '/admin/users?status=' . rawurlencode(Status::USER_SUSPENDED),
      ],
      [
          'label' => 'Deactivated',
          'value' => (int) ($summary['inactive_users'] ?? 0),
          'icon' => 'bi-person',
          'tone' => 'info',
          'url' => '/admin/users?status=' . rawurlencode(Status::USER_INACTIVE),
      ],
  ];
  $summaryStatsCompact = true;
  require __DIR__ . '/../../partials/dashboard/summary_stats.php';
  ?>

  <div class="ux-card ux-data-card" data-table-filter="tf-users">
    <?php require __DIR__ . '/../../partials/shared/table_filter_form.php'; ?>
    <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex flex-wrap align-items-center gap-3 min-w-0">
        <h2 class="ux-data-card__title mb-0">
          <i class="bi bi-person me-1" aria-hidden="true"></i>
          User accounts
        </h2>
        <?php require __DIR__ . '/../../partials/shared/table_filter_tabs.php'; ?>
      </div>
      <?php require __DIR__ . '/../../partials/shared/table_toolbar.php'; ?>
    </div>
    <?php if ($users !== []): ?>
      <div class="user-bulk-bar">
        <label class="user-bulk-bar__select-all">
          <input type="checkbox" id="userSelectAll" aria-label="Select all users on this page">
          <span>Select page</span>
        </label>
        <span class="user-bulk-bar__count text-muted small" id="userBulkCount">0 selected</span>
        <button
          type="button"
          class="btn btn-outline-danger btn-sm btn-destroy-quiet"
          id="userBulkDeleteButton"
          data-bs-toggle="modal"
          data-bs-target="#permanentDeleteModal"
          data-bulk-delete="1"
          data-bulk-url="<?= Helper::escape(Helper::url('/admin/users/delete-selected')) ?>"
          disabled
        >Delete selected</button>
      </div>
    <?php endif; ?>
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
          <caption class="visually-hidden">User accounts with role, status, last login, and actions.</caption>
          <thead>
            <tr>
              <th scope="col" class="user-select-col">
                <span class="visually-hidden">Select</span>
              </th>
              <th scope="col">Name</th>
              <th scope="col">Email</th>
              <th scope="col">Role</th>
              <th scope="col">Status</th>
              <th scope="col">Last login</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($users === []): ?>
              <tr>
                <td colspan="7" class="ux-table__empty-state">
                  <?php
                  $emptyIcon = 'bi-people';
                  $emptyTitle = 'No users to display';
                  $emptyText = 'There are no user accounts in this view.';
                  $emptyActions = '<a href="' . Helper::url('/admin/users') . '" class="btn btn-outline-primary btn-sm">View all users</a>';
                  $emptyCompact = true;
                  require __DIR__ . '/../../partials/shared/empty_state.php';
                  ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $row): ?>
                <?php
                $userId = (int) ($row['id'] ?? 0);
                $userName = trim((string) ($row['full_name'] ?? 'Unknown'));
                $userEmail = trim((string) ($row['email'] ?? ''));
                $userRole = trim((string) ($row['role_name'] ?? ''));
                $userStatus = (string) ($row['status'] ?? '');
                $lastLogin = trim((string) ($row['last_login_at'] ?? ''));
                $actions = is_array($row['actions'] ?? null) ? $row['actions'] : [];
                $primary = null;
                $menu = [];
                foreach ($actions as $action) {
                    if (($action['key'] ?? '') === 'view') {
                        $primary = $action;
                        continue;
                    }
                    $menu[] = $action;
                }
                $canDelete = false;
                foreach ($actions as $action) {
                    if (($action['key'] ?? '') === 'delete') {
                        $canDelete = true;
                        break;
                    }
                }
                ?>
                <tr>
                  <td class="user-select-col">
                    <?php if ($canDelete): ?>
                      <input
                        type="checkbox"
                        class="form-check-input user-select-box"
                        name="user_ids[]"
                        value="<?= $userId ?>"
                        data-user-name="<?= Helper::escape($userName) ?>"
                        aria-label="Select <?= Helper::escape($userName) ?>"
                      >
                    <?php else: ?>
                      <input type="checkbox" class="form-check-input" disabled aria-label="This account cannot be selected for deletion">
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                    $personName = $userName;
                    $personPhoto = $row['profile_photo_path'] ?? null;
                    $personMeta = '';
                    $personSize = 'sm';
                    require __DIR__ . '/../../partials/shared/person_row.php';
                    ?>
                  </td>
                  <td><span class="text-muted"><?= Helper::escape($userEmail !== '' ? $userEmail : '—') ?></span></td>
                  <td>
                    <?php
                    $roleKey = strtolower($userRole);
                    $roleIcon = match ($roleKey) {
                        'doctor' => 'bi-heart-pulse',
                        'admin', 'administrator' => 'bi-shield-lock',
                        'patient' => 'bi-person',
                        default => 'bi-person',
                    };
                    ?>
                    <span class="ux-badge ux-badge--neutral ux-badge--role">
                      <i class="bi <?= Helper::escape($roleIcon) ?>" aria-hidden="true"></i>
                      <?= Helper::escape($userRole !== '' ? user_profile_role_label($userRole) : '—') ?>
                    </span>
                  </td>
                  <td><?= ux_status_badge($userStatus, Status::DOMAIN_USER) ?></td>
                  <td>
                    <?php if ($lastLogin !== ''): ?>
                      <div><?= Helper::escape(Helper::formatDate($lastLogin, 'd M Y', '—')) ?></div>
                      <div class="small text-muted"><?= Helper::escape(Helper::formatDate($lastLogin, 'g:i A', '')) ?></div>
                    <?php else: ?>
                      <span class="text-muted">Never</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <div class="ux-table__actions">
                      <?php if ($primary !== null): ?>
                        <a href="<?= Helper::url((string) $primary['url']) ?>" class="btn btn-outline-primary btn-sm">View</a>
                      <?php endif; ?>
                      <?php if ($menu !== [] && $userId > 0): ?>
                        <div class="dropdown d-inline-block">
                          <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions for <?= Helper::escape($userName) ?>">
                            <i class="bi bi-three-dots"></i>
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end">
                            <?php foreach ($menu as $action): ?>
                              <?php
                              $tone = (string) ($action['tone'] ?? '');
                              $itemClass = 'dropdown-item';
                              if ($tone === 'destroy') {
                                  $itemClass .= ' text-danger';
                              }
                              ?>
                              <li>
                                <?php if (($action['method'] ?? 'GET') === 'GET'): ?>
                                  <a class="<?= Helper::escape($itemClass) ?>" href="<?= Helper::url((string) $action['url']) ?>"><?= Helper::escape((string) $action['label']) ?></a>
                                <?php elseif (($action['key'] ?? '') === 'delete'): ?>
                                  <button
                                    type="button"
                                    class="<?= Helper::escape($itemClass) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#permanentDeleteModal"
                                    data-delete-url="<?= Helper::escape(Helper::url((string) $action['url'])) ?>"
                                    data-delete-name="<?= Helper::escape($userName) ?>"
                                  ><?= Helper::escape((string) $action['label']) ?></button>
                                <?php else: ?>
                                  <?php
                                  $confirmTitle = match ((string) ($action['key'] ?? '')) {
                                      'suspend' => 'Suspend this account?',
                                      'deactivate' => 'Deactivate this account?',
                                      'reactivate' => 'Reactivate this account?',
                                      default => '',
                                  };
                                  $confirmBody = match ((string) ($action['key'] ?? '')) {
                                      'suspend' => 'The user will be temporarily blocked from signing in. This is not permanent deletion.',
                                      'deactivate' => 'The account will be disabled and retained for operational history. This is not permanent deletion.',
                                      'reactivate' => 'This user will be able to sign in again.',
                                      default => '',
                                  };
                                  $confirmHint = match ((string) ($action['key'] ?? '')) {
                                      'reactivate' => 'You can change the status again later if needed.',
                                      default => 'You can reactivate the account later if this was a mistake.',
                                  };
                                  $confirmTone = ((string) ($action['key'] ?? '')) === 'reactivate' ? 'primary' : 'danger';
                                  ?>
                                  <form
                                    method="POST"
                                    action="<?= Helper::url((string) $action['url']) ?>"
                                    <?php if ($confirmTitle !== ''): ?>
                                      data-confirm-title="<?= Helper::escape($confirmTitle) ?>"
                                      data-confirm-body="<?= Helper::escape($confirmBody) ?>"
                                      data-confirm-hint="<?= Helper::escape($confirmHint) ?>"
                                      data-confirm-tone="<?= Helper::escape($confirmTone) ?>"
                                    <?php endif; ?>
                                  >
                                    <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
                                    <input type="hidden" name="return_to" value="<?= Helper::escape(Helper::currentRequestPath()) ?>">
                                    <button type="submit" class="<?= Helper::escape($itemClass) ?>"><?= Helper::escape((string) $action['label']) ?></button>
                                  </form>
                                <?php endif; ?>
                              </li>
                            <?php endforeach; ?>
                          </ul>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    $paginationPath = '/admin/users';
    $paginationFilters = $filters;
    $paginationLabel = 'users';
    $paginationAria = 'User pagination';
    $paginationShowCount = true;
    require __DIR__ . '/../../partials/shared/list_pagination.php';
    ?>
  </div>
</section>

<div class="modal fade" id="permanentDeleteModal" tabindex="-1" aria-labelledby="permanentDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-3">
      <form method="POST" action="#" id="permanentDeleteForm" novalidate data-bulk-url="<?= Helper::escape(Helper::url('/admin/users/delete-selected')) ?>">
        <div class="modal-header border-bottom">
          <h5 class="modal-title" id="permanentDeleteModalLabel">Permanently delete this user?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
          <div id="permanentDeleteIdFields"></div>
          <p class="mb-3">This is different from deactivation. The account cannot normally be restored. Protected clinical and audit records will not be casually deleted.</p>
          <p class="small text-muted mb-3">You are deleting <strong id="permanentDeleteName">this user</strong>.</p>
          <div class="mb-3">
            <label for="confirmation_phrase" class="form-label">Type <code><?= Helper::escape($confirmationPhrase) ?></code> to confirm</label>
            <input type="text" class="form-control" id="confirmation_phrase" name="confirmation_phrase" autocomplete="off" required data-confirm-phrase="<?= Helper::escape($confirmationPhrase) ?>">
          </div>
          <div>
            <label for="admin_password" class="form-label">Your administrator password</label>
            <input type="password" class="form-control" id="admin_password" name="admin_password" autocomplete="current-password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" id="permanentDeleteSubmit" disabled>Delete permanently</button>
        </div>
      </form>
    </div>
  </div>
</div>
