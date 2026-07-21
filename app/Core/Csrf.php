<?php

namespace App\Core;

class Csrf
{
    public const TOKEN_KEY = '_csrf_token';

    public static function generate(): string
    {
        if (!Session::has(self::TOKEN_KEY)) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::TOKEN_KEY, $token);
        }

        return Session::get(self::TOKEN_KEY);
    }

    public static function verify(string $token): bool
    {
        if (!Session::has(self::TOKEN_KEY)) {
            return false;
        }

        $storedToken = Session::get(self::TOKEN_KEY);

        return hash_equals($storedToken, $token);
    }
}
