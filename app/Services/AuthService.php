
<?php

namespace App\Services;

use App\Core\Session;

class AuthService
{
    public static function isAuthenticated(): bool
    {
        return Session::has('user_id');
    }

    public static function getUserId(): ?int
    {
        return Session::get('user_id');
    }

    public static function getUserRole(): ?string
    {
        return Session::get('user_role');
    }

    public static function login(int $userId, string $role): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('user_role', $role);
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}

