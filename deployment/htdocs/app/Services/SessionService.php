<?php

namespace App\Services;

use App\Core\Session;
use App\Helpers\Helper;
use App\Models\UserSession;

/**
 * File-based PHP sessions plus a database registry so users can review
 * devices and revoke other sessions without storing session secrets.
 */
class SessionService
{
    public static function currentHash(): string
    {
        $id = session_id();
        if (!is_string($id) || $id === '') {
            return '';
        }

        return hash('sha256', $id);
    }

    public static function registerCurrent(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $hash = self::currentHash();
        if ($hash === '') {
            return;
        }

        try {
            UserSession::upsert([
                'user_id' => $userId,
                'session_token_hash' => $hash,
                'ip_address' => Helper::clientIp(),
                'user_agent' => Helper::userAgent(),
            ]);
        } catch (\Throwable $exception) {
            error_log('[SessionService::registerCurrent] ' . $exception->getMessage());
        }
    }

    /**
     * Ensure this PHP session is listed for the user. Existing sessions
     * created before the registry existed are enrolled on first request.
     */
    public static function ensureCurrent(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $hash = self::currentHash();
        if ($hash === '') {
            return false;
        }

        try {
            if (UserSession::existsForUser($userId, $hash)) {
                UserSession::touch($userId, $hash);
                return true;
            }

            // Enrol only when this account has no registered devices yet
            // (sessions created before the registry existed). After a
            // revocation, other PHP sessions must not re-enrol themselves.
            if (UserSession::countForUser($userId) === 0) {
                self::registerCurrent($userId);

                return UserSession::existsForUser($userId, $hash);
            }

            return false;
        } catch (\Throwable $exception) {
            error_log('[SessionService::ensureCurrent] ' . $exception->getMessage());

            return true;
        }
    }

    public static function revokeOthers(int $userId): int
    {
        $hash = self::currentHash();
        if ($userId <= 0 || $hash === '') {
            return 0;
        }

        try {
            return UserSession::deleteOthersForUser($userId, $hash);
        } catch (\Throwable $exception) {
            error_log('[SessionService::revokeOthers] ' . $exception->getMessage());

            return 0;
        }
    }

    public static function revokeAll(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            return UserSession::deleteAllForUser($userId);
        } catch (\Throwable $exception) {
            error_log('[SessionService::revokeAll] ' . $exception->getMessage());

            return 0;
        }
    }

    public static function destroyCurrent(int $userId): void
    {
        $hash = self::currentHash();
        if ($userId > 0 && $hash !== '') {
            try {
                UserSession::deleteCurrent($userId, $hash);
            } catch (\Throwable $exception) {
                error_log('[SessionService::destroyCurrent] ' . $exception->getMessage());
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForUser(int $userId): array
    {
        $current = self::currentHash();
        $rows = [];

        try {
            $rows = UserSession::findForUser($userId);
        } catch (\Throwable $exception) {
            error_log('[SessionService::listForUser] ' . $exception->getMessage());
            return [];
        }

        $presented = [];
        foreach ($rows as $row) {
            $hash = (string) ($row['session_token_hash'] ?? '');
            $agent = (string) ($row['user_agent'] ?? '');
            $presented[] = [
                'id' => (int) ($row['id'] ?? 0),
                'is_current' => $hash !== '' && hash_equals($current, $hash),
                'ip_address' => (string) ($row['ip_address'] ?? ''),
                'device_label' => self::deviceLabel($agent),
                'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $presented;
    }

    public static function deviceLabel(string $userAgent): string
    {
        $ua = trim($userAgent);
        if ($ua === '') {
            return 'Unknown device';
        }

        $browser = 'Browser';
        if (stripos($ua, 'Edg/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'Chrome/') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'Firefox/') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($ua, 'Safari/') !== false) {
            $browser = 'Safari';
        }

        $os = 'Unknown OS';
        if (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        return $browser . ' on ' . $os;
    }
}
