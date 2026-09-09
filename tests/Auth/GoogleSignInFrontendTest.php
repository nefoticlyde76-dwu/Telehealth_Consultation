<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Config\App;
use PHPUnit\Framework\TestCase;

/**
 * Converted from bin/test_google_login_gis.php
 */
final class GoogleSignInFrontendTest extends TestCase
{
    public function testLoginPageWiresOfficialGisWithoutClientSideJwtHandling(): void
    {
        $root = dirname(__DIR__, 2);
        $loginView = (string) file_get_contents($root . '/app/Views/auth/login.php');
        $registerView = (string) file_get_contents($root . '/app/Views/auth/register.php');
        $gisJs = (string) file_get_contents($root . '/public/js/google-auth.js');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $googleAuthService = (string) file_get_contents($root . '/app/Services/GoogleAuthService.php');
        $authService = (string) file_get_contents($root . '/app/Services/AuthService.php');

        $this->assertStringContainsString('https://accounts.google.com/gsi/client', $loginView);
        $this->assertStringContainsString('id="mbphaGoogleSignInButton"', $loginView);
        $this->assertStringContainsString('js/google-auth.js', $loginView);
        $this->assertStringContainsString('js/google-auth.js', $registerView);
        $this->assertStringContainsString("Helper::url('/auth/google')", $loginView);
        $this->assertStringContainsString('name="email"', $loginView);
        $this->assertStringContainsString('name="password"', $loginView);

        $this->assertStringContainsString('renderButton', $gisJs);
        $this->assertStringContainsString('ux_mode: "popup"', $gisJs);
        $this->assertStringContainsString('credential: credential', $gisJs);
        $this->assertStringContainsString('_token: csrf', $gisJs);
        $this->assertStringNotContainsString('localStorage', $gisJs);
        $this->assertStringNotContainsString('atob(', $gisJs);
        $this->assertStringNotContainsString('client_secret', $gisJs);
        $this->assertStringNotContainsString('platform.js', $gisJs);

        $this->assertStringContainsString("post('/auth/google', [GoogleAuthController::class, 'authenticate'])", $routes);
        $this->assertStringContainsString('AuthService::login', $googleAuthService);
        $this->assertStringContainsString('function authenticate(string $email, string $password)', $authService);
        $this->assertStringNotContainsString('$_SESSION', $googleAuthService);
    }

    public function testGoogleClientIdIsConfiguredWhenPresent(): void
    {
        $clientId = trim((string) (App::getConfig()['google']['client_id'] ?? ''));
        if ($clientId === '') {
            $this->markTestSkipped('GOOGLE_CLIENT_ID is not configured.');
        }

        $loginView = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/auth/login.php');
        $this->assertStringContainsString("App::getConfig()['google']['client_id']", $loginView);
        $this->assertStringNotContainsString('your-google-web-client-id.apps.googleusercontent.com', $loginView);
    }
}
