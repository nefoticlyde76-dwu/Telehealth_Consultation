<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\AuthService;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    public function index(): void
    {
        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        [$user, $role] = $context;
        $userId = (int) $user->id;
        $pageData = NotificationService::getPageData($userId, $role, $_GET);

        $this->render('notifications/index', array_merge(
            $this->dashboardViewData($user, $role),
            $pageData,
            [
                'title' => 'Notifications | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Notifications',
                'dashboardDescription' => 'Stay informed about important consultation events.',
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function open(string $id): void
    {
        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        [$user, $role] = $context;
        $opened = NotificationService::openForUser((int) $user->id, $role, (int) $id);

        if ($opened === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'That notification could not be found.',
            ]);
            Helper::redirect('/notifications');
            return;
        }

        Helper::redirect((string) ($opened['target'] ?? '/notifications'));
    }

    public function markRead(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/notifications');
            return;
        }

        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        $returnPath = $this->returnPath();
        if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
            Session::flash('status', [
                'type' => 'danger',
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
            ]);
            Helper::redirect($returnPath);
            return;
        }

        [$user] = $context;
        $notificationId = (int) $id;
        if (!NotificationService::belongsToUser((int) $user->id, $notificationId)) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'That notification could not be found.',
            ]);
            Helper::redirect($returnPath);
            return;
        }

        NotificationService::markReadForUser((int) $user->id, $notificationId);
        Helper::redirect($returnPath);
    }

    public function markUnread(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/notifications');
            return;
        }

        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        $returnPath = $this->returnPath();
        if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
            Session::flash('status', [
                'type' => 'danger',
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
            ]);
            Helper::redirect($returnPath);
            return;
        }

        [$user] = $context;
        $notificationId = (int) $id;
        if (!NotificationService::belongsToUser((int) $user->id, $notificationId)) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'That notification could not be found.',
            ]);
            Helper::redirect($returnPath);
            return;
        }

        NotificationService::markUnreadForUser((int) $user->id, $notificationId);
        Helper::redirect($returnPath);
    }

    public function delete(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/notifications');
            return;
        }

        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        $returnPath = $this->returnPath();
        if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
            Session::flash('status', [
                'type' => 'danger',
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
            ]);
            Helper::redirect($returnPath);
            return;
        }

        [$user] = $context;
        $notificationId = (int) $id;
        if (!NotificationService::belongsToUser((int) $user->id, $notificationId)) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'That notification could not be found.',
            ]);
            Helper::redirect($returnPath);
            return;
        }

        NotificationService::deleteForUser((int) $user->id, $notificationId);
        Session::flash('status', [
            'type' => 'success',
            'message' => 'Notification deleted.',
        ]);
        Helper::redirect($returnPath);
    }

    public function deleteSelected(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/notifications');
            return;
        }

        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        $returnPath = $this->returnPath();
        if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
            Session::flash('status', [
                'type' => 'danger',
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
            ]);
            Helper::redirect($returnPath);
            return;
        }

        [$user] = $context;
        $ids = $_POST['notification_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $deleted = NotificationService::deleteSelectedForUser((int) $user->id, $ids);

        Session::flash('status', [
            'type' => $deleted > 0 ? 'success' : 'warning',
            'message' => $deleted > 0
                ? ($deleted === 1 ? '1 notification deleted.' : $deleted . ' notifications deleted.')
                : 'Select at least one of your notifications to delete.',
        ]);
        Helper::redirect($returnPath);
    }

    public function clearAll(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/notifications');
            return;
        }

        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
            Session::flash('status', [
                'type' => 'danger',
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
            ]);
            Helper::redirect('/notifications');
            return;
        }

        [$user] = $context;
        $deleted = NotificationService::clearAllForUser((int) $user->id);

        Session::flash('status', [
            'type' => 'success',
            'message' => $deleted > 0
                ? 'All of your notifications have been cleared.'
                : 'You have no notifications to clear.',
        ]);
        Helper::redirect('/notifications');
    }

    public function markAllRead(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/notifications');
            return;
        }

        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        $returnPath = $this->returnPath();
        if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
            Session::flash('status', [
                'type' => 'danger',
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
            ]);
            Helper::redirect($returnPath);
            return;
        }

        [$user] = $context;
        NotificationService::markAllReadForUser((int) $user->id);

        Session::flash('status', [
            'type' => 'success',
            'message' => 'All notifications have been marked as read.',
        ]);
        Helper::redirect($returnPath);
    }

    private function returnPath(): string
    {
        return Helper::safeInternalPath((string) ($_POST['return_to'] ?? ''), '/notifications');
    }

    /**
     * @return array{0:\App\Models\User,1:string}|null
     */
    private function requireAuthenticatedUser(): ?array
    {
        if (!AuthService::isAuthenticated()) {
            Helper::redirect('/login');
            return null;
        }

        $user = AuthService::getUser();
        $role = (string) (AuthService::getUserRole() ?? '');
        if ($user === null || $user->id === null || !in_array($role, ['admin', 'doctor', 'patient'], true)) {
            Helper::redirect('/login');
            return null;
        }

        return [$user, $role];
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardViewData(\App\Models\User $user, string $role): array
    {
        $roleLabel = match ($role) {
            'admin' => 'Administrator',
            'doctor' => 'Doctor',
            'patient' => 'Patient',
            default => 'Account',
        };

        return [
            'user' => $user,
            'dashboardRole' => $role,
            'dashboardRoleLabel' => $roleLabel,
        ];
    }
}
