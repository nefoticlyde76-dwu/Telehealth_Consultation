<?php

/**
 * Create the first production administrator.
 *
 * Usage:
 *   php bin/create_admin.php "Full Name" admin@example.com "StrongPassword" [employee-id]
 *
 * The password is hashed with password_hash() and is never written to source files.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Helpers\Helper;
use App\Models\Admin;
use App\Models\User;

Environment::load(dirname(__DIR__) . '/.env');

$fullName = trim((string) ($argv[1] ?? ''));
$email = strtolower(trim((string) ($argv[2] ?? '')));
$password = (string) ($argv[3] ?? '');
$employeeId = trim((string) ($argv[4] ?? 'ADMIN-001'));

if ($fullName === '' || $email === '' || $password === '') {
    fwrite(STDERR, "Usage: php bin/create_admin.php \"Full Name\" admin@example.com \"StrongPassword\" [employee-id]\n");
    exit(1);
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "A valid administrator email is required.\n");
    exit(1);
}

if (!Helper::isStrongPassword($password)) {
    fwrite(STDERR, "Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.\n");
    exit(1);
}

if (User::findByEmail($email) !== null) {
    fwrite(STDERR, "An account with that email already exists.\n");
    exit(1);
}

if (Admin::findByEmployeeId($employeeId) !== null) {
    fwrite(STDERR, "That employee ID is already in use.\n");
    exit(1);
}

$db = Database::getInstance();
$roleStmt = $db->prepare("SELECT id FROM roles WHERE name = 'admin' LIMIT 1");
$roleStmt->execute();
$roleId = $roleStmt->fetchColumn();

if (!$roleId) {
    fwrite(STDERR, "The admin role is missing. Import database/schema.sql first.\n");
    exit(1);
}

try {
    $db->beginTransaction();

    $user = new User();
    $user->role_id = (int) $roleId;
    $user->full_name = $fullName;
    $user->email = $email;
    $user->password = password_hash($password, PASSWORD_DEFAULT);
    $user->status = 'active';

    if (!$user->save() || $user->id === null) {
        throw new RuntimeException('The administrator user row could not be created.');
    }

    $admin = new Admin();
    $admin->user_id = (int) $user->id;
    $admin->employee_id = $employeeId;

    if (!$admin->save()) {
        throw new RuntimeException('The administrator profile row could not be created.');
    }

    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(STDERR, "Administrator creation failed.\n");
    exit(1);
}

echo 'Administrator created for ' . $email . PHP_EOL;
exit(0);
