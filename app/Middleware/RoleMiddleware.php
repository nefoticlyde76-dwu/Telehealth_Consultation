<?php

namespace App\Middleware;

use App\Core\Session;
use App\Helpers\Helper;
use App\Services\AuthService;

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
        if ($user === null || strtolower(trim((string) ($user->status ?? ''))) !== 'active') {
            AuthService::logout();
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'This account is no longer active. Please contact MBPHA TeleHealth administration if you need access restored.',
            ]);
            Helper::redirect('/login');
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
}
