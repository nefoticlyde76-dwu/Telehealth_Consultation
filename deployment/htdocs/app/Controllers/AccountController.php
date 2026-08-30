<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\DashboardNav;
use App\Helpers\Helper;
use App\Helpers\Status;
use App\Services\AccountSecurityService;
use App\Services\AuthService;

class AccountController extends Controller
{
    public function security(): void
    {
        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        [$user, $role] = $context;
        $pageData = AccountSecurityService::getSecurityPageData((int) $user->id);

        $this->render('account/security', array_merge(
            $this->dashboardViewData($user, $role),
            $pageData,
            [
                'title' => 'Security | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Security',
                'dashboardDescription' => 'Change your password and manage signed-in devices.',
                'statusMessage' => Session::getFlash('status'),
                'errors' => Session::getFlash('security_errors') ?? [],
                'fieldErrors' => Session::getFlash('security_field_errors') ?? [],
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
    }

    public function changePassword(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/account/security');
            return;
        }

        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        [$user] = $context;
        $result = AccountSecurityService::changeOwnPassword((int) $user->id, $_POST);

        if ($result['success'] ?? false) {
            Session::flash('status', [
                'type' => 'success',
                'message' => $result['message'] ?? 'Password updated successfully.',
            ]);
            Helper::redirect('/account/security');
            return;
        }

        Session::flash('security_errors', $result['errors'] ?? ['Unable to update the password.']);
        Session::flash('security_field_errors', $result['fieldErrors'] ?? []);
        Helper::redirect('/account/security');
    }

    public function logoutOtherSessions(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Helper::redirect('/account/security');
            return;
        }

        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        [$user] = $context;
        $result = AccountSecurityService::logoutOtherSessions(
            (int) $user->id,
            (string) ($_POST['_token'] ?? '')
        );

        Session::flash('status', [
            'type' => $result['type'] ?? 'danger',
            'message' => $result['message'] ?? 'Unable to sign out other devices.',
        ]);
        Helper::redirect('/account/security');
    }

    public function notificationPreferences(): void
    {
        $context = $this->requireAuthenticatedUser();
        if ($context === null) {
            return;
        }

        [$user, $role] = $context;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $result = AccountSecurityService::updatePreferences((int) $user->id, $_POST);
            Session::flash('status', [
                'type' => $result['type'] ?? 'danger',
                'message' => $result['message'] ?? 'Unable to save notification preferences.',
            ]);
            Helper::redirect('/account/notifications/preferences');
            return;
        }

        $pageData = AccountSecurityService::getPreferencePageData((int) $user->id);

        $this->render('account/notification_preferences', array_merge(
            $this->dashboardViewData($user, $role),
            $pageData,
            [
                'title' => 'Notification Preferences | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Notification Preferences',
                'dashboardDescription' => 'Choose which in-app notices you receive. Security notices cannot be turned off.',
                'statusMessage' => Session::getFlash('status'),
                'csrfToken' => Csrf::generate(),
            ]
        ), 'layouts/dashboard');
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

        if (!Status::canAuthenticateUserStatus((string) $user->status)) {
            AuthService::logout();
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
        $nav = DashboardNav::forRole($role);

        return [
            'user' => $user,
            'dashboardRole' => $role,
            'dashboardRoleLabel' => (string) ($nav['roleLabel'] ?? 'Account'),
        ];
    }
}
