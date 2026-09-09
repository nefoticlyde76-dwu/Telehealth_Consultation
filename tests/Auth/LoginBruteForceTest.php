<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Controllers\AuthController;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\LoginAttemptService;
use Tests\Support\DatabaseTestCase;

/**
 * Converted from bin/test_login_bruteforce.php
 */
final class LoginBruteForceTest extends DatabaseTestCase
{
    private string $email = '';
    private string $unknownEmail = '';

    protected function setUp(): void
    {
        parent::setUp();

        LoginAttemptService::$testWindowSeconds = 30;
        LoginAttemptService::$testMaxAttempts = LoginAttemptService::MAX_ATTEMPTS;
        $this->ensureLoginAttemptsTable();
        $_SERVER['REQUEST_URI'] = '/login';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.77';
        $_SERVER['HTTP_HOST'] = 'localhost';

        $this->email = $this->uniqueEmail('bruteforce.patient');
        $this->unknownEmail = $this->uniqueEmail('bruteforce.unknown');
        $this->createUser('patient', 'Brute Force Test Patient', $this->email);
        LoginAttemptService::clear($this->email);
        LoginAttemptService::clear($this->unknownEmail);
    }

    public function testAuditCatalogIncludesLockout(): void
    {
        $catalog = AuditLogService::catalog();
        $this->assertArrayHasKey('login_lockout', $catalog);
        $this->assertSame('authentication', $catalog['login_lockout']['category'] ?? null);
    }

    public function testBackoffSchedule(): void
    {
        $this->assertSame(0, LoginAttemptService::delaySecondsForFailures(0));
        $this->assertSame(1, LoginAttemptService::delaySecondsForFailures(1));
        $this->assertSame(2, LoginAttemptService::delaySecondsForFailures(2));
        $this->assertSame(4, LoginAttemptService::delaySecondsForFailures(3));
        $this->assertSame(8, LoginAttemptService::delaySecondsForFailures(4));
        $this->assertSame(8, LoginAttemptService::delaySecondsForFailures(9));
    }

    public function testRepeatedFailuresLockEmailAndIp(): void
    {
        $maxAttempts = LoginAttemptService::maxAttempts();
        $lastHtml = '';
        for ($i = 1; $i <= $maxAttempts; $i++) {
            $lastHtml = $this->postLogin($this->email, 'WrongPass1!');
            $state = LoginAttemptService::inspect($this->email);
            if ($i < $maxAttempts) {
                $this->assertStringContainsString('Invalid email, password, or account status.', $lastHtml);
                $this->assertFalse($state['locked']);
                $this->assertSame($i, $state['failed_count']);
            }
        }

        $locked = LoginAttemptService::inspect($this->email);
        $this->assertTrue($locked['locked']);
        $this->assertStringContainsString(LoginAttemptService::LOCKOUT_MESSAGE, $lastHtml);
        $this->assertStringNotContainsString('Invalid email, password, or account status.', $lastHtml);

        $correctWhileLocked = $this->postLogin($this->email, self::TEST_PASSWORD);
        $this->assertStringContainsString(LoginAttemptService::LOCKOUT_MESSAGE, $correctWhileLocked);
        $this->assertNull(Session::get('user_id'));
        $this->assertFalse(AuthService::isAuthenticated());
        $this->assertInstanceOf(User::class, AuthService::authenticate($this->email, self::TEST_PASSWORD));

        $otherIp = LoginAttemptService::inspect($this->email, '198.51.100.80');
        $this->assertFalse($otherIp['locked']);
    }

    public function testLockoutExpiresAndUnknownEmailsUseTheSameMessage(): void
    {
        $maxAttempts = LoginAttemptService::maxAttempts();
        for ($i = 1; $i <= $maxAttempts; $i++) {
            $this->postLogin($this->email, 'WrongPass1!');
        }
        $this->assertTrue(LoginAttemptService::inspect($this->email)['locked']);

        LoginAttemptService::$testNow = Helper::now()->modify('+' . (LoginAttemptService::windowSeconds() + 1) . ' seconds');
        $expired = LoginAttemptService::inspect($this->email);
        $this->assertFalse($expired['locked']);
        $this->assertSame(0, $expired['failed_count']);

        $afterExpiry = $this->postLogin($this->email, 'WrongPass1!');
        $this->assertStringContainsString('Invalid email, password, or account status.', $afterExpiry);
        $this->assertStringNotContainsString(LoginAttemptService::LOCKOUT_MESSAGE, $afterExpiry);

        $unknownHtml = '';
        for ($i = 1; $i <= $maxAttempts; $i++) {
            $unknownHtml = $this->postLogin($this->unknownEmail, 'WrongPass1!');
        }
        $this->assertStringContainsString(LoginAttemptService::LOCKOUT_MESSAGE, $unknownHtml);
        $this->assertStringNotContainsString('does not exist', strtolower($unknownHtml));
        $this->assertStringNotContainsString('no account', strtolower($unknownHtml));
    }

    private function ensureLoginAttemptsTable(): void
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table'
        );
        $stmt->execute([':table' => 'login_attempts']);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/032_create_login_attempts_table.sql');
        $this->assertIsString($sql);
        $this->db()->exec($sql);
    }

    private function postLogin(string $email, string $password): string
    {
        $this->forgetLogin();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'email' => $email,
            'password' => $password,
            '_token' => Csrf::generate(),
        ];

        return $this->captureOutput(static function (): void {
            (new AuthController())->login();
        });
    }
}
