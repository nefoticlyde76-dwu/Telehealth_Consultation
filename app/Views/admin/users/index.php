<?php
$csrfToken = $csrfToken ?? '';
$errors = $errors ?? [];
$search = $search ?? '';
$role = $role ?? '';
$status = $status ?? '';
$pageTitle = (string) ($pageTitle ?? 'Users');
$pageSubtitle = (string) ($pageSubtitle ?? 'Manage administrator, doctor, and patient accounts across the platform.');
$summary = $summary ?? [];
$users = $users ?? [];
$pagination = $pagination ?? null;
$roles = $roles ?? [];
$statuses = $statuses ?? [];
$roleList = is_array($roles) ? $roles : [];
$statusList = is_array($statuses) ? $statuses : [];

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalUsers = (int) ($summary['total_users'] ?? 0);
$activeUsers = (int) ($summary['active_users'] ?? 0);
$doctorUsers = (int) ($summary['doctor_users'] ?? 0);
$patientUsers = (int) ($summary['patient_users'] ?? 0);

$statusFilterOptions = [];
foreach ($statusList as $opt) {
    if (is_array($opt)) {
        $statusFilterOptions[] = ['value' => (string) ($opt['value'] ?? ''), 'label' => (string) ($opt['label'] ?? '')];
    } elseif (is_string($opt) && $opt !== '') {
        $statusFilterOptions[] = ['value' => $opt, 'label' => ucfirst($opt)];
    }
}

$roleFilterOptions = [];
foreach ($roleList as $opt) {
    if (is_array($opt)) {
        $roleFilterOptions[] = ['value' => (string) ($opt['value'] ?? ''), 'label' => (string) ($opt['label'] ?? '')];
    } elseif (is_string($opt) && $opt !== '') {
        $roleFilterOptions[] = ['value' => $opt, 'label' => ucfirst($opt)];
    }
}
?>

<section class="mb-4">
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li class="active"><?= \App\Helpers\Helper::escape($pageTitle) ?></li>
      </ol>
      <h2 class="ux-page-header__title"><?= \App\Helpers\Helper::escape($pageTitle) ?></h2>
      <p class="ux-page-header__subtitle"><?= \App\Helpers\Helper::escape($pageSubtitle) ?></p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-people-fill"></i>
        <span>Total users: <?= $totalUsers ?></span>
      </span>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-card ux-filter">
    <div class="card-header">
      <h3 class="h6">
        <i class="bi bi-funnel-fill ux-filter__header-icon"></i>
        Filter users
      </h3>
    </div>
    <form method="get" action="<?= \App\Helpers\Helper::url('/admin/users') ?>" novalidate>
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
      <div class="row g-3 align-items-end">
        <div class="col-md-4 col-lg">
          <label for="search" class="form-label">Search users</label>
          <input
            id="search"
            type="search"
            name="search"
            class="form-control"
            placeholder="Name, email, or phone"
            value="<?= \App\Helpers\Helper::escape($search) ?>"
          >
        </div>
        <div class="col-md-4 col-lg">
          <label for="role" class="form-label">Role</label>
          <select id="role" name="role" class="form-select" aria-label="Filter by role">
            <option value="">All roles</option>
            <?php foreach ($roleFilterOptions as $opt): ?>
              <?php $optValue = (string) $opt['value']; ?>
              <option value="<?= \App\Helpers\Helper::escape($optValue) ?>" <?= $role === $optValue ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) $opt['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 col-lg">
          <label for="status" class="form-label">Status</label>
          <select id="status" name="status" class="form-select" aria-label="Filter by status">
            <option value="">All statuses</option>
            <?php foreach ($statusFilterOptions as $opt): ?>
              <?php $optValue = (string) $opt['value']; ?>
              <option value="<?= \App\Helpers\Helper::escape($optValue) ?>" <?= $status === $optValue ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) $opt['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <div class="ux-filter__actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-funnel-fill me-1"></i>
              Apply Filters
            </button>
            <a href="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="btn btn-outline-primary btn-sm">
              Reset
            </a>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-people-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= $totalUsers ?></div>
          <div class="ux-stat__label">Total Users</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= $activeUsers ?></div>
          <div class="ux-stat__label">Active</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--cyan">
          <i class="bi bi-person-badge-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= $doctorUsers ?></div>
          <div class="ux-stat__label">Doctors</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--purple">
          <i class="bi bi-person-heart"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= $patientUsers ?></div>
          <div class="ux-stat__label">Patients</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card">
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
          <thead>
            <tr>
              <th scope="col">User</th>
              <th scope="col">Role</th>
              <th scope="col">Status</th>
              <th scope="col">Created</th>
              <th scope="col" class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="5" class="ux-table__empty-state">
                  <div class="ux-empty">
                    <div class="ux-empty__icon">
                      <i class="bi bi-search"></i>
                    </div>
                    <h4 class="ux-empty__title">No users match your filters</h4>
                    <p class="ux-empty__text">Try adjusting the search criteria, or create a new user to begin onboarding.</p>
                    <div class="ux-empty__action">
                      <a href="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="btn btn-outline-primary btn-sm">
                        Reset filters
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $user): ?>
                <?php
                $userId = (int) ($user['id'] ?? 0);
                $userName = trim((string) ($user['full_name'] ?? $user['name'] ?? 'Unknown'));
                $userEmail = trim((string) ($user['email'] ?? ''));
                $userRole = trim((string) ($user['role'] ?? ''));
                $userStatus = (string) ($user['status'] ?? 'inactive');
                $userCreated = trim((string) ($user['created_at'] ?? ''));
                $viewAriaLabel = 'View ' . ($userName ?: 'this user') . ' account';
                $statusBadge = ux_active_badge_class($userStatus);
                $statusLabel = $userStatus === '' ? 'Unknown' : ucfirst($userStatus);
                ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-3">
                      <div class="ux-stat__icon ux-stat__icon--surface flex-shrink-0">
                        <i class="bi bi-person-fill"></i>
                      </div>
                      <div>
                        <strong class="d-block"><?= \App\Helpers\Helper::escape($userName ?: 'Unnamed user') ?></strong>
                        <span class="small text-muted d-block"><?= \App\Helpers\Helper::escape($userEmail) ?></span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="ux-badge ux-badge--neutral">
                      <?= \App\Helpers\Helper::escape($userRole === '' ? '—' : ucfirst($userRole)) ?>
                    </span>
                  </td>
                  <td>
                    <span class="<?= $statusBadge ?> ux-badge">
                      <?= \App\Helpers\Helper::escape($statusLabel) ?>
                    </span>
                  </td>
                  <td>
                    <div><?= \App\Helpers\Helper::formatDate($userCreated, 'd M Y', '—') ?></div>
                    <div class="small text-muted"><?= $userCreated !== '' ? \App\Helpers\Helper::formatDate($userCreated, 'g:i A', '') : '' ?></div>
                  </td>
                  <td class="text-end">
                    <div class="ux-table__actions">
                      <?php if ($userId > 0): ?>
                        <a
                          href="<?= \App\Helpers\Helper::url('/admin/users/' . $userId) ?>"
                          class="btn btn-outline-primary btn-sm"
                          aria-label="<?= \App\Helpers\Helper::escape($viewAriaLabel) ?>"
                        >
                          <i class="bi bi-eye-fill me-1"></i>
                          View
                        </a>
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
    <?php if (is_array($pagination) && ($pagination['total_pages'] ?? 0) > 1): ?>
      <?php
      $page = (int) ($pagination['current_page'] ?? 1);
      $totalPages = (int) ($pagination['total_pages'] ?? 1);
      $baseUrl = \App\Helpers\Helper::url('/admin/users');
      $queryKeep = ['search' => $search, 'role' => $role, 'status' => $status, 'per_page' => (string) ($pagination['per_page'] ?? '')];
      $buildPageUrl = static function (int $p) use ($baseUrl, $queryKeep): string {
          $queryKeep['page'] = (string) $p;
          $cleaned = array_filter($queryKeep, static fn($v) => $v !== '' && $v !== null);
          return $cleaned === [] ? $baseUrl : $baseUrl . '?' . http_build_query($cleaned);
      };
      ?>
      <div class="card-footer border-0 bg-transparent pt-4 pb-0">
        <nav class="d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Users pagination">
          <?php if ($page > 1): ?>
            <a href="<?= $buildPageUrl($page - 1) ?>" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-chevron-left me-1"></i>
              Previous
            </a>
          <?php endif; ?>
          <span class="small text-muted">
            Page <?= $page ?> of <?= $totalPages ?>
          </span>
          <?php if ($page < $totalPages): ?>
            <a href="<?= $buildPageUrl($page + 1) ?>" class="btn btn-outline-primary btn-sm">
              Next
              <i class="bi bi-chevron-right ms-1"></i>
            </a>
          <?php endif; ?>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($users)): ?>
  <?php foreach ($users as $user): ?>
    <?php
    $userId = (int) ($user['id'] ?? 0);
    $userName = trim((string) ($user['full_name'] ?? $user['name'] ?? 'this user'));
    if ($userId <= 0) {
        continue;
    }
    ?>
    <div
      class="modal fade"
      id="adminUserConfirmDeactivate<?= $userId ?>"
      tabindex="-1"
      aria-labelledby="adminUserConfirmDeactivate<?= $userId ?>Label"
      aria-hidden="true"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3">
          <form
            method="post"
            action="<?= \App\Helpers\Helper::url('/admin/users/' . $userId . '/toggle-status') ?>"
            novalidate
          >
            <div class="modal-header border-bottom">
              <h5 class="modal-title" id="adminUserConfirmDeactivate<?= $userId ?>Label">
                Update status for <?= \App\Helpers\Helper::escape($userName) ?>
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
              <p class="mb-0">
                Confirm this action in order to update the user account status.
              </p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger">
                Confirm
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
