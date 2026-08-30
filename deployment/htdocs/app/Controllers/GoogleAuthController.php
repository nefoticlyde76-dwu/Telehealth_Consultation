<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Helpers\Helper;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\GoogleAuthService;

/**
 * HTTP entry point for Google Identity Services ID-token sign-in.
 *
 * This controller only validates the request and formats the response.
 * Account resolution and session creation belong to GoogleAuthService
 * and the existing authentication service.
 */
class GoogleAuthController extends Controller
{
    private const MAX_BODY_BYTES = 16384;
    private const MAX_CREDENTIAL_BYTES = 8192;

    /**
     * Test-only raw JSON body used by bin/test_google_auth_controller.php.
     * Production callers must leave this null so php://input is read.
     */
    public static ?string $testRawBody = null;

    public function authenticate(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            $this->methodNotAllowed();
            return;
        }

        if (AuthService::isAuthenticated()) {
            $this->jsonResponse([
                'success' => true,
                'redirect' => self::approvedRedirectPath((string) AuthService::getUserRole()),
            ], 200);
            return;
        }

        $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '')));
        if (!str_starts_with($contentType, 'application/json')) {
            $this->invalidRequest();
            return;
        }

        $rawBody = $this->readRawBody();
        if ($rawBody === null) {
            $this->invalidRequest();
            return;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            $this->invalidRequest();
            return;
        }

        $csrfToken = '';
        if (isset($payload['_token']) && is_string($payload['_token'])) {
            $csrfToken = $payload['_token'];
        } elseif (isset($_POST['_token']) && is_string($_POST['_token'])) {
            $csrfToken = $_POST['_token'];
        }

        if (!Csrf::verify($csrfToken)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Your session security token is invalid. Please refresh the page and try again.',
            ], 419);
            return;
        }

        if (!array_key_exists('credential', $payload) || !is_string($payload['credential'])) {
            $this->invalidRequest();
            return;
        }

        $credential = trim($payload['credential']);
        if ($credential === '' || strlen($credential) > self::MAX_CREDENTIAL_BYTES) {
            $this->invalidRequest();
            return;
        }

        try {
            $user = GoogleAuthService::authenticate($credential);
        } catch (\Throwable) {
            error_log('[GoogleAuthController] Unexpected Google authentication error.');
            $this->jsonResponse([
                'success' => false,
                'message' => 'Google sign-in is temporarily unavailable. Please try again later.',
            ], 500);
            return;
        }

        $credential = '';

        if ($user === null || $user->id === null) {
            AuditLogService::record(
                'login_failed',
                'Google authentication failed.',
                AuditLogService::ENTITY_AUTH,
                null,
                'failed',
                [
                    'actor_name' => 'Guest',
                    'actor_role' => 'guest',
                ]
            );
            $this->jsonResponse([
                'success' => false,
                'message' => self::patientFacingFailureMessage(GoogleAuthService::lastFailureReason()),
            ], 401);
            return;
        }

        $role = $user->getRole();
        AuditLogService::record(
            'login_success',
            'User authenticated successfully.',
            AuditLogService::ENTITY_AUTH,
            (int) $user->id
        );

        $this->jsonResponse([
            'success' => true,
            'redirect' => self::approvedRedirectPath((string) $role),
        ], 200);
    }

    /**
     * Safe copy for the login/register Google button. Never includes reason
     * codes or whether the matching account is a doctor or administrator.
     */
    private static function patientFacingFailureMessage(?string $reason): string
    {
        if (
            $reason === GoogleAuthService::REASON_EMAIL_COLLISION
            || $reason === GoogleAuthService::REASON_GOOGLE_USER_NOT_ALLOWED
        ) {
            return 'Google sign-in is for patients only. Please sign in with your email and password.';
        }

        return 'Google sign-in could not be completed.';
    }

    /**
     * Role dashboard path including the application base (XAMPP subdirectory).
     * Never derived from Google or browser input.
     */
    private static function approvedRedirectPath(string $role): string
    {
        return Helper::browserPath(AuthService::getRoleRedirectUrl($role));
    }

    public function methodNotAllowed(): void
    {
        if (!headers_sent()) {
            header('Allow: POST');
        }

        $this->jsonResponse([
            'success' => false,
            'message' => 'This method is not allowed.',
        ], 405);
    }

    private function invalidRequest(): void
    {
        $this->jsonResponse([
            'success' => false,
            'message' => 'Google sign-in request is invalid.',
        ], 400);
    }

    private function readRawBody(): ?string
    {
        $raw = self::$testRawBody;
        if ($raw === null) {
            $declaredLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($declaredLength > self::MAX_BODY_BYTES) {
                return null;
            }

            $read = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
            $raw = is_string($read) ? $read : '';
        }

        if (strlen($raw) > self::MAX_BODY_BYTES) {
            return null;
        }

        return $raw;
    }
}
