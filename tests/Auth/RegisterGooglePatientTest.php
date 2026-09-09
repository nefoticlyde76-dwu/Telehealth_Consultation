<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Core\Session;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuthService;
use Tests\Support\DatabaseTestCase;

/**
 * Converted from bin/test_register_google_patient.php
 */
final class RegisterGooglePatientTest extends DatabaseTestCase
{
    public function testInvalidAndUnverifiedIdentitiesAreRejected(): void
    {
        $this->assertNull(AuthService::registerGooglePatient([
            'sub' => '',
            'email' => 'not-an-email',
            'email_verified' => true,
            'name' => 'X',
        ]));

        $email = $this->uniqueEmail('unverified.google');
        $this->assertNull(AuthService::registerGooglePatient([
            'sub' => 'sub-unverified-' . $this->uniqueSuffix(),
            'email' => $email,
            'email_verified' => false,
            'name' => 'Unverified',
        ]));
        $this->assertNull(User::findByEmail($email));
    }

    public function testVerifiedGoogleIdentityCreatesAPatientWithoutLoggingIn(): void
    {
        $suffix = $this->uniqueSuffix();
        $googleEmail = 'google.reg+' . $suffix . '@example.com';
        $googleSub = 'google-reg-sub-' . $suffix;
        $sessionUserIdBefore = Session::get('user_id');
        $sessionRoleBefore = Session::get('user_role');

        $created = AuthService::registerGooglePatient([
            'sub' => $googleSub,
            'email' => 'Google.Reg+' . $suffix . '@Example.COM',
            'email_verified' => true,
            'name' => 'Google Registered Patient',
            'role' => 'admin',
            'password' => 'should-be-ignored',
            'status' => 'inactive',
        ]);

        $this->assertInstanceOf(User::class, $created);
        $this->assertNotNull($created->id);
        $this->trackUser((int) $created->id);
        $this->assertSame('patient', $created->getRole());
        $this->assertSame('active', $created->status);
        $this->assertNull($created->password);
        $this->assertSame($googleSub, $created->google_sub);
        $this->assertSame($googleEmail, $created->google_email);
        $this->assertSame($googleEmail, $created->email);
        $this->assertSame(User::AUTH_PROVIDER_GOOGLE, $created->auth_provider);

        $patient = Patient::findByUserId((int) $created->id);
        $this->assertNotNull($patient);
        $this->assertFalse(AuthService::isAuthenticated());
        $this->assertSame($sessionUserIdBefore, Session::get('user_id'));
        $this->assertSame($sessionRoleBefore, Session::get('user_role'));

        $duplicate = AuthService::registerGooglePatient([
            'sub' => $googleSub,
            'email' => 'other+' . $suffix . '@example.com',
            'email_verified' => true,
            'name' => 'Duplicate Sub',
        ]);
        $this->assertNull($duplicate);
        $this->assertNull(User::findByEmail('other+' . $suffix . '@example.com'));
    }

    public function testLocalRegistrationStillHashesPasswordsAndBlocksEmailCollision(): void
    {
        $localEmail = $this->uniqueEmail('local.reg');
        $localUser = AuthService::register([
            'full_name' => 'Local Regression Patient',
            'email' => $localEmail,
            'password' => 'LocalPass!234',
            'dob' => '1990-01-15',
            'gender' => 'female',
            'address' => 'Alotau',
        ]);

        $this->assertInstanceOf(User::class, $localUser);
        $this->assertNotNull($localUser->id);
        $this->trackUser((int) $localUser->id);
        $this->assertTrue(password_verify('LocalPass!234', (string) $localUser->password));
        $this->assertSame(User::AUTH_PROVIDER_LOCAL, $localUser->auth_provider);
        $this->assertSame('patient', $localUser->getRole());

        $collision = AuthService::registerGooglePatient([
            'sub' => 'google-email-collision-' . $this->uniqueSuffix(),
            'email' => $localEmail,
            'email_verified' => true,
            'name' => 'Should Not Link',
        ]);
        $this->assertNull($collision);
        $after = User::findByEmail($localEmail);
        $this->assertNotNull($after);
        $this->assertNull($after->google_sub);
        $this->assertSame(User::AUTH_PROVIDER_LOCAL, $after->auth_provider);
    }

    public function testPatientInsertFailureRollsBackTheUserRow(): void
    {
        $email = $this->uniqueEmail('rollback.google');
        $sub = 'rollback-sub-' . $this->uniqueSuffix();
        AuthService::$testFailGooglePatientInsert = true;
        $rolledBack = AuthService::registerGooglePatient([
            'sub' => $sub,
            'email' => $email,
            'email_verified' => true,
            'name' => 'Rollback Patient',
        ]);
        AuthService::$testFailGooglePatientInsert = false;

        $this->assertNull($rolledBack);
        $this->assertNull(User::findByEmail($email));
        $this->assertNull(User::findByGoogleSub($sub));
    }

    public function testBlankGoogleNameFallsBackToPatient(): void
    {
        $created = AuthService::registerGooglePatient([
            'sub' => 'blank-name-sub-' . $this->uniqueSuffix(),
            'email' => $this->uniqueEmail('blank.name'),
            'email_verified' => true,
            'name' => '   ',
        ]);

        $this->assertInstanceOf(User::class, $created);
        $this->assertSame('Patient', $created->full_name);
        $this->trackUser((int) $created->id);
    }
}
