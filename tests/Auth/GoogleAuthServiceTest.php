<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Config\App;
use App\Core\Session;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuthService;
use App\Services\GoogleAuthService;
use Tests\Support\DatabaseTestCase;
use Tests\Support\GoogleJwtFactory;

/**
 * Converted from bin/test_google_auth_service.php
 */
final class GoogleAuthServiceTest extends DatabaseTestCase
{
    /** @var array{private: \OpenSSLAsymmetricKey, jwks: array{keys: list<array<string, string>>}, kid: string} */
    private array $rsa;

    private string $clientId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientId = trim((string) (App::getConfig()['google']['client_id'] ?? ''));
        if ($this->clientId === '') {
            $this->markTestSkipped('GOOGLE_CLIENT_ID is not configured.');
        }

        GoogleJwtFactory::configureOpenSsl();
        $this->rsa = GoogleJwtFactory::rsaJwks();
        GoogleAuthService::$testJwksOverride = $this->rsa['jwks'];
        $this->forgetLogin();
    }

    public function testInvalidTokenDoesNotCreateASessionOrUser(): void
    {
        $usersBefore = (int) $this->db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $token = 'not-a-jwt-' . $this->uniqueSuffix();
        $this->assertNull(GoogleAuthService::authenticate($token));
        $this->assertSame(GoogleAuthService::REASON_INVALID_GOOGLE_IDENTITY, GoogleAuthService::lastFailureReason());
        $this->assertSame($usersBefore, (int) $this->db()->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->assertFalse(AuthService::isAuthenticated());
    }

    public function testNewVerifiedGoogleIdentityCreatesAPatientAndLogsIn(): void
    {
        $suffix = $this->uniqueSuffix();
        $email = 'new.google+' . $suffix . '@example.com';
        $sub = 'new-google-sub-' . $suffix;
        $token = GoogleJwtFactory::idToken($this->rsa, $this->clientId, [
            'sub' => $sub,
            'email' => 'New.Google+' . $suffix . '@Example.COM',
            'name' => 'Brand New Google Patient',
            'role' => 'admin',
        ]);

        $created = GoogleAuthService::authenticate($token);
        $this->assertInstanceOf(User::class, $created);
        $this->assertNotNull($created->id);
        $this->trackUser((int) $created->id);
        $this->assertSame('patient', $created->getRole());
        $this->assertNull($created->password);
        $this->assertSame($sub, $created->google_sub);
        $this->assertSame($email, $created->email);
        $this->assertSame(User::AUTH_PROVIDER_GOOGLE, $created->auth_provider);
        $this->assertNotNull(Patient::findByUserId((int) $created->id));
        $this->assertTrue(AuthService::isAuthenticated());
        $this->assertSame((int) $created->id, (int) Session::get('user_id'));

        $again = GoogleAuthService::authenticate($token);
        $this->assertInstanceOf(User::class, $again);
        $this->assertSame((int) $created->id, (int) $again->id);
    }

    public function testExistingDoctorGoogleSubIsRejected(): void
    {
        $suffix = $this->uniqueSuffix();
        $sub = 'doctor-google-sub-' . $suffix;
        $email = 'google.doctor+' . $suffix . '@example.com';
        $doctor = User::createGoogleUser([
            'role_id' => $this->roleId('doctor'),
            'full_name' => 'Google Doctor Fixture',
            'email' => $email,
            'google_sub' => $sub,
            'google_email' => $email,
            'status' => 'active',
        ]);
        $this->assertInstanceOf(User::class, $doctor);
        $this->trackUser((int) $doctor->id);
        $profile = new Doctor();
        $profile->user_id = $doctor->id;
        $profile->professional_title = 'Medical Officer';
        $this->assertTrue($profile->save());

        $token = GoogleJwtFactory::idToken($this->rsa, $this->clientId, [
            'sub' => $sub,
            'email' => $email,
            'name' => 'Google Doctor Fixture',
        ]);
        $this->assertNull(GoogleAuthService::authenticate($token));
        $this->assertSame(GoogleAuthService::REASON_GOOGLE_USER_NOT_ALLOWED, GoogleAuthService::lastFailureReason());
        $this->assertFalse(AuthService::isAuthenticated());
        $after = User::findByGoogleSub($sub);
        $this->assertNotNull($after);
        $this->assertSame('doctor', $after->getRole());
    }

    public function testMatchingLocalPatientEmailIsLinkedWithoutChangingPassword(): void
    {
        $email = $this->uniqueEmail('local.link');
        $userId = $this->createUser('patient', 'Local Link Patient', $email);
        $patient = new Patient();
        $patient->user_id = $userId;
        $this->assertTrue($patient->save());
        $original = User::findById($userId);
        $this->assertNotNull($original);
        $originalPassword = (string) $original->password;

        $sub = 'link-google-sub-' . $this->uniqueSuffix();
        $token = GoogleJwtFactory::idToken($this->rsa, $this->clientId, [
            'sub' => $sub,
            'email' => $email,
            'name' => 'Local Link Patient',
        ]);
        $linked = GoogleAuthService::authenticate($token);
        $this->assertInstanceOf(User::class, $linked);
        $this->assertSame($userId, (int) $linked->id);
        $this->assertSame(User::AUTH_PROVIDER_BOTH, $linked->auth_provider);
        $this->assertSame($sub, $linked->google_sub);
        $this->assertSame($originalPassword, $linked->password);
        $this->assertTrue(password_verify(self::TEST_PASSWORD, (string) $linked->password));
        $this->assertTrue(AuthService::isAuthenticated());
    }

    public function testDoctorEmailCollisionDoesNotAutoLink(): void
    {
        $email = $this->uniqueEmail('doctor.email');
        $doctorId = $this->createDoctor('Email Collision Doctor');
        $this->db()->prepare('UPDATE users SET email = :email WHERE id = :id')->execute([
            ':email' => $email,
            ':id' => $doctorId,
        ]);

        $token = GoogleJwtFactory::idToken($this->rsa, $this->clientId, [
            'sub' => 'doctor-email-sub-' . $this->uniqueSuffix(),
            'email' => $email,
            'name' => 'Email Collision Doctor',
        ]);
        $this->assertNull(GoogleAuthService::authenticate($token));
        $this->assertSame(GoogleAuthService::REASON_EMAIL_COLLISION, GoogleAuthService::lastFailureReason());
        $after = User::findByEmail($email);
        $this->assertNotNull($after);
        $this->assertNull($after->google_sub);
        $this->assertSame('doctor', $after->getRole());
        $this->assertFalse(AuthService::isAuthenticated());
    }

    public function testLocalPasswordLoginRemainsAvailable(): void
    {
        $email = $this->uniqueEmail('local.password');
        $userId = $this->createPatient('Local Password Patient');
        $this->db()->prepare('UPDATE users SET email = :email WHERE id = :id')->execute([
            ':email' => $email,
            ':id' => $userId,
        ]);

        $authenticated = AuthService::authenticate($email, self::TEST_PASSWORD);
        $this->assertInstanceOf(User::class, $authenticated);
        $this->assertSame($userId, (int) $authenticated->id);
        $this->assertSame('patient', $authenticated->getRole());
    }
}
