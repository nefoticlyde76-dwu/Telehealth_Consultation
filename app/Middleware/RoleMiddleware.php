<?php

namespace App\Middleware;

use App\Core\Session;
use App\Helpers\Helper;
use App\Helpers\Status;
use App\Services\AuthService;
use App\Services\SessionService;

class RoleMiddleware implements Middleware
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle(): void
    {
        $userRole = Session::get('user_role');

        if (!$userRole) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'Please sign in to continue.',
            ]);
            Helper::redirect('/login');
        }

        $user = AuthService::getUser();
        if ($user === null || !Status::canAuthenticateUserStatus((string) ($user->status ?? ''))) {
            AuthService::logout();
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'This account is no longer active. Please contact MBPHA TeleHealth administration if you need access restored.',
            ]);
            Helper::redirect('/login');
        }

        if (!SessionService::ensureCurrent((int) $user->id)) {
            AuthService::logout();
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'Your session is no longer valid. Please sign in again.',
            ]);
            Helper::redirect('/login');
        }

        $currentPath = Helper::currentPath();
        if ((int) ($user->force_password_reset ?? 0) === 1 && !self::allowsForcedPasswordPath($currentPath)) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'Please choose a new password before continuing.',
            ]);
            Helper::redirect('/account/security');
        }

        if (!in_array($userRole, $this->allowedRoles, true)) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'You do not have permission to access that page with the current account.',
            ]);

            $redirectUrl = AuthService::getRoleRedirectUrl((string) $userRole);
            Helper::redirect($redirectUrl !== '/' ? $redirectUrl : '/');
        }
    }

    private static function allowsForcedPasswordPath(string $path): bool
    {
        $allowed = [
            '/account/security',
            '/account/password',
            '/account/sessions/logout-others',
            '/logout',
        ];

        return in_array($path, $allowed, true);
    }
}
