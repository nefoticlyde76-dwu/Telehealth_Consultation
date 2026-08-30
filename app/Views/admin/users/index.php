<?php
use App\Helpers\Helper;
use App\Helpers\Status;
use App\Services\AccountSecurityService;

$csrfToken = (string) ($csrfToken ?? '');
$filters = is_array($filters ?? null) ? $filters : [];
$search = (string) ($filters['search'] ?? '');
$role = (string) ($filters['role'] ?? '');
$status = (string) ($filters['status'] ?? '');
$sort = (string) ($filters['sort'] ?? 'newest');
$users = is_array($users ?? null) ? $users : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$summary = is_array($summary ?? null) ? $summary : [];
$roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$sortOptions = is_array($sortOptions ?? null) ? $sortOptions : [];
$actorUserId = (int) ($actorUserId ?? 0);
$confirmationPhrase = AccountSecurityService::CONFIRMATION_PHRASE;

require __DIR__ . '/../../partials/shared/status_helper.php';

$filterForm = [
    'action' => Helper::url('/admin/users'),
    'title' => 'Filter users',
    'search' => [
        'name' => 'search',
        'value' => $search,
        'placeholder' => 'Search by name or email',
        'label' => 'Search users',
    ],
    'clear_url' => Helper::url('/admin/users'),
    'fields' => [
        [
            'type' => 'select',
            'name' => 'role',
            'id' => 'role',
            'label' => 'Role',
            'value' => $role,
            'empty_label' => 'All roles',
            'options' => array_map(static fn (string $opt): array => [
                'value' => $opt,
                'label' => ucfirst($opt),
            ], $roleOptions),
        ],
        [
            'type' => 'select',
            'name' => 'status',
            'id' => 'status',
            'label' => 'Status',
            'value' => $status,
            'empty_label' => 'All statuses',
            'options' => array_map(static fn (string $opt): array => [
                'value' => $opt,
                'label' => Status::label($opt, Status::DOMAIN_USER),
            ], $statusOptions),
        ],
        [
            'type' => 'select',
            'name' => 'sort',
            'id' => 'sort',
            'label' => 'Sort',
            'value' => $sort,
            'include_empty' => false,
            'options' => $sortOptions,
        ],
    ],
];
?>

<section class="mb-4">
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li class="active">Users</li>
      </ol>
      <h2 class="ux-page-header__title">Users</h2>
      <p class="ux-page-header__subtitle">Manage system accounts and access. Clinical consultation content is not shown here.</p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2">
      <a href="<?= Helper::url('/admin/doctors/create') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i>
        Add doctor
      </a>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <?php require __DIR__ . '/../../partials/shared/list_filter.php'; ?>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy"><i class="bi bi-people-fill"></i></div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['total_users'] ?? 0) ?></div>
          <div class="ux-stat__label">Total</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success"><i class="bi bi-person-check-fill"></i></div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['active_users'] ?? 0) ?></div>
          <div class="ux-stat__label">Active</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--cyan"><i class="bi bi-pause-circle-fill"></i></div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['suspended_users'] ?? 0) ?></div>
          <div class="ux-stat__label">Suspended</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--slate"><i class="bi bi-person-dash-fill"></i></div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['inactive_users'] ?? 0) ?></div>
          <div class="ux-stat__label">Deactivated</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card ux-data-card">
    <div class="ux-card__header">
      <h2 class="ux-data-card__title">User accounts</h2>
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
                  $emptyIcon = 'bi-search';
                  $emptyTitle = 'No users match your filters';
                  $emptyText = 'Try another name, email, role, or status.';
                  $emptyActions = '<a href="' . Helper::url('/admin/users') . '" class="btn btn-outline-primary btn-sm">Reset filters</a>';
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
                  <td><span class="ux-badge ux-badge--neutral"><?= Helper::escape($userRole !== '' ? ucfirst($userRole) : '—') ?></span></td>
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
