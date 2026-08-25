<?php

/**
 * User model Google identity persistence checks.
 *
 * Usage: php bin/test_user_google_identity.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Models\User;

$root = dirname(__DIR__);
Environment::load($root . '/.env');

$failed = 0;
$passed = 0;

function expect_true(bool $condition, string $label): void
{
    global $failed, $passed;
    if ($condition) {
        $passed++;
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
}

$db = Database::getInstance();
$patientRoleId = User::findRoleIdByName('patient');
$suffix = bin2hex(random_bytes(6));

$existing = User::findById(1);
$existingGoogleSub = $existing?->google_sub;
$existingGoogleEmail = $existing?->google_email;
$existingAuthProvider = $existing?->auth_provider;
$existingEmail = $existing?->email;
$existingRoleId = $existing?->role_id;
$existingPasswordState = $existing !== null && is_string($existing->password) && $existing->password !== '' ? 'SET' : 'NULL';

expect_true($patientRoleId !== null && $patientRoleId > 0, 'Patient role id is available');
expect_true($existing !== null, 'An existing user row can be loaded');
expect_true($existingGoogleSub === null, 'Existing user google_sub is NULL');
expect_true($existingGoogleEmail === null, 'Existing user google_email is NULL');
expect_true($existingAuthProvider === User::AUTH_PROVIDER_LOCAL, 'Existing user auth_provider is local');

$db->beginTransaction();

try {
    $hydratedWithoutGoogle = User::fromArray([
        'id' => 0,
        'role_id' => $patientRoleId,
        'full_name' => 'Legacy Hydration',
        'email' => 'legacy@example.com',
        'password' => 'hash',
        'status' => 'active',
    ]);
    expect_true($hydratedWithoutGoogle->google_sub === null, 'Hydration without Google columns leaves google_sub null');
    expect_true($hydratedWithoutGoogle->google_email === null, 'Hydration without Google columns leaves google_email null');
    expect_true($hydratedWithoutGoogle->auth_provider === User::AUTH_PROVIDER_LOCAL, 'Hydration without Google columns defaults auth_provider to local');

    expect_true(User::findByGoogleSub('no-such-google-sub-' . $suffix) === null, 'findByGoogleSub returns null for an unknown sub');
    expect_true(User::findByGoogleSub('') === null, 'findByGoogleSub returns null for an empty sub');

    $googleSub = 'test-google-sub-' . $suffix;
    $googleUser = User::createGoogleUser([
        'role_id' => (int) $patientRoleId,
        'full_name' => 'Google Model Patient',
        'email' => 'Google.Model+' . $suffix . '@Example.COM',
        'google_sub' => $googleSub,
        'google_email' => 'Google.Model+' . $suffix . '@Example.COM',
        'status' => 'active',
    ]);

    expect_true($googleUser instanceof User && $googleUser->id !== null, 'Google user insert returns a User');
    expect_true($googleUser !== null && $googleUser->google_sub === $googleSub, 'Google user insert stores google_sub');
    expect_true($googleUser !== null && $googleUser->google_email === 'google.model+' . $suffix . '@example.com', 'Google user insert stores lowercased google_email');
    expect_true($googleUser !== null && $googleUser->email === 'google.model+' . $suffix . '@example.com', 'Google user insert stores lowercased users.email');
    expect_true($googleUser !== null && $googleUser->password === null, 'Google user insert stores password = NULL');
    expect_true($googleUser !== null && $googleUser->auth_provider === User::AUTH_PROVIDER_GOOGLE, 'Google user insert stores auth_provider = google');
    expect_true($googleUser !== null && $googleUser->status === 'active', 'Google user insert stores status = active');
    expect_true($googleUser !== null && (int) $googleUser->role_id === (int) $patientRoleId, 'Google user insert stores the supplied role_id');

    $foundBySub = User::findByGoogleSub($googleSub);
    expect_true($foundBySub !== null && (int) $foundBySub->id === (int) $googleUser->id, 'findByGoogleSub finds the matching Google user');
    expect_true($foundBySub !== null && $foundBySub->email === $googleUser->email, 'findByGoogleSub does not look up by email as its key');

    $passwordHash = password_hash('LocalPass!234', PASSWORD_DEFAULT);
    $local = new User();
    $local->role_id = (int) $patientRoleId;
    $local->full_name = 'Local Model Patient';
    $local->email = 'local.model+' . $suffix . '@example.com';
    $local->password = $passwordHash;
    $local->status = 'active';
    expect_true($local->save() && $local->id !== null, 'Existing save() still creates a local user');

    $localReloaded = User::findById((int) $local->id);
    expect_true($localReloaded !== null && $localReloaded->google_sub === null, 'Local insert leaves google_sub NULL');
    expect_true($localReloaded !== null && $localReloaded->google_email === null, 'Local insert leaves google_email NULL');
    expect_true($localReloaded !== null && $localReloaded->auth_provider === User::AUTH_PROVIDER_LOCAL, 'Local insert leaves auth_provider local');
    expect_true(
        $localReloaded !== null && is_string($localReloaded->password) && $localReloaded->password !== '',
        'Local insert stores a password hash'
    );

    $originalLocalEmail = (string) $localReloaded->email;
    $originalLocalRole = (int) $localReloaded->role_id;
    $originalLocalStatus = (string) $localReloaded->status;
    $originalLocalPassword = (string) $localReloaded->password;

    $linked = User::linkGoogleIdentity(
        (int) $local->id,
        'linked-google-sub-' . $suffix,
        'Linked.Email+' . $suffix . '@Example.COM'
    );
    expect_true($linked === true, 'linkGoogleIdentity returns true');

    $afterLink = User::findById((int) $local->id);
    expect_true($afterLink !== null && $afterLink->google_sub === 'linked-google-sub-' . $suffix, 'Linking stores google_sub');
    expect_true($afterLink !== null && $afterLink->google_email === 'linked.email+' . $suffix . '@example.com', 'Linking stores lowercased google_email');
    expect_true($afterLink !== null && $afterLink->auth_provider === User::AUTH_PROVIDER_BOTH, 'Linking sets auth_provider = both');
    expect_true($afterLink !== null && $afterLink->password === $originalLocalPassword, 'Linking does not change the password hash');
    expect_true($afterLink !== null && $afterLink->email === $originalLocalEmail, 'Linking does not change users.email');
    expect_true($afterLink !== null && (int) $afterLink->role_id === $originalLocalRole, 'Linking does not change role_id');
    expect_true($afterLink !== null && $afterLink->status === $originalLocalStatus, 'Linking does not change status');

    $relink = User::linkGoogleIdentity(
        (int) $local->id,
        'attacker-google-sub-' . $suffix,
        'attacker+' . $suffix . '@example.com'
    );
    expect_true($relink === false, 'linkGoogleIdentity does not overwrite an existing google_sub');
    $afterRelink = User::findById((int) $local->id);
    expect_true($afterRelink !== null && $afterRelink->google_sub === 'linked-google-sub-' . $suffix, 'Existing google_sub is unchanged after a rejected relink');
    expect_true($afterRelink !== null && $afterRelink->auth_provider === User::AUTH_PROVIDER_BOTH, 'Rejected relink does not change auth_provider');
    expect_true($afterRelink !== null && $afterRelink->password === $originalLocalPassword, 'Rejected relink does not change the password hash');

    $updatedEmail = User::updateGoogleEmail((int) $local->id, 'Updated.Google+' . $suffix . '@Example.COM');
    expect_true($updatedEmail === true, 'updateGoogleEmail returns true');
    $afterEmailUpdate = User::findById((int) $local->id);
    expect_true($afterEmailUpdate !== null && $afterEmailUpdate->google_email === 'updated.google+' . $suffix . '@example.com', 'updateGoogleEmail changes google_email');
    expect_true($afterEmailUpdate !== null && $afterEmailUpdate->email === $originalLocalEmail, 'updateGoogleEmail does not change users.email');
    expect_true($afterEmailUpdate !== null && $afterEmailUpdate->google_sub === 'linked-google-sub-' . $suffix, 'updateGoogleEmail does not change google_sub');
    expect_true($afterEmailUpdate !== null && $afterEmailUpdate->auth_provider === User::AUTH_PROVIDER_BOTH, 'updateGoogleEmail does not change auth_provider');
    expect_true($afterEmailUpdate !== null && $afterEmailUpdate->password === $originalLocalPassword, 'updateGoogleEmail does not change password');

    $googleBeforeSave = User::findById((int) $googleUser->id);
    $googleBeforeSave->full_name = 'Renamed Google Patient';
    expect_true($googleBeforeSave->save(), 'Existing save() still updates a Google user');
    $googleAfterSave = User::findById((int) $googleUser->id);
    expect_true($googleAfterSave !== null && $googleAfterSave->full_name === 'Renamed Google Patient', 'save() updates full_name');
    expect_true($googleAfterSave !== null && $googleAfterSave->google_sub === $googleSub, 'save() does not wipe google_sub');
    expect_true($googleAfterSave !== null && $googleAfterSave->google_email === 'google.model+' . $suffix . '@example.com', 'save() does not wipe google_email');
    expect_true($googleAfterSave !== null && $googleAfterSave->auth_provider === User::AUTH_PROVIDER_GOOGLE, 'save() does not reset auth_provider');
    expect_true($googleAfterSave !== null && $googleAfterSave->password === null, 'save() does not invent a password for a Google-only user');

    $linkedBeforeSave = User::findById((int) $local->id);
    $linkedBeforeSave->full_name = 'Renamed Linked Patient';
    expect_true($linkedBeforeSave->save(), 'Existing save() still updates a linked user');
    $linkedAfterSave = User::findById((int) $local->id);
    expect_true($linkedAfterSave !== null && $linkedAfterSave->full_name === 'Renamed Linked Patient', 'save() updates the linked user name');
    expect_true($linkedAfterSave !== null && $linkedAfterSave->google_sub === 'linked-google-sub-' . $suffix, 'save() does not wipe linked google_sub');
    expect_true($linkedAfterSave !== null && $linkedAfterSave->google_email === 'updated.google+' . $suffix . '@example.com', 'save() does not wipe linked google_email');
    expect_true($linkedAfterSave !== null && $linkedAfterSave->auth_provider === User::AUTH_PROVIDER_BOTH, 'save() does not reset linked auth_provider');
    expect_true($linkedAfterSave !== null && $linkedAfterSave->password === $originalLocalPassword, 'save() does not change the linked password hash');
    expect_true($linkedAfterSave !== null && $linkedAfterSave->email === $originalLocalEmail, 'save() does not change the linked users.email');

    $existingAfter = User::findById(1);
    expect_true($existingAfter !== null && $existingAfter->google_sub === $existingGoogleSub, 'Existing user google_sub remains unchanged');
    expect_true($existingAfter !== null && $existingAfter->google_email === $existingGoogleEmail, 'Existing user google_email remains unchanged');
    expect_true($existingAfter !== null && $existingAfter->auth_provider === $existingAuthProvider, 'Existing user auth_provider remains unchanged');
    expect_true($existingAfter !== null && $existingAfter->email === $existingEmail, 'Existing user email remains unchanged');
    expect_true($existingAfter !== null && (int) $existingAfter->role_id === (int) $existingRoleId, 'Existing user role remains unchanged');
    $existingPasswordAfter = $existingAfter !== null && is_string($existingAfter->password) && $existingAfter->password !== '' ? 'SET' : 'NULL';
    expect_true($existingPasswordAfter === $existingPasswordState, 'Existing user password presence remains unchanged');
} catch (Throwable $exception) {
    expect_true(false, 'Google identity tests completed without an unexpected exception: ' . $exception->getMessage());
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

echo PHP_EOL . "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
