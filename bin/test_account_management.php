<?php

/**
 * Account management, permanent deletion, notifications, and RBAC checks.
 *
 * Usage: php bin/test_account_management.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Helpers\Status;
use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\User;
use App\Models\UserSession;
use App\Services\AccountSecurityService;
use App\Services\AdminUserService;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\SessionService;
use App\Services\UserDeletionService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');
date_default_timezone_set('Pacific/Port_Moresby');
Session::start();

$failed = 0;
$passed = 0;
$skipped = 0;

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

function skip(string $label): void
{
    global $skipped;
    $skipped++;
    echo "SKIP  {$label}\n";
}

function acct_has_column(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function acct_upsert_user(string $role, string $email, string $name, string $password): array
{
    $roleId = User::findRoleIdByName($role);
    if ($roleId === null) {
        throw new RuntimeException('Missing role: ' . $role);
    }

    $user = User::findByEmail($email);
    if ($user === null) {
        $user = new User();
        $user->role_id = $roleId;
        $user->full_name = $name;
        $user->email = $email;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->status = 'active';
        if (!$user->save() || $user->id === null) {
            throw new RuntimeException('Unable to create user ' . $email);
        }
    } else {
        $user->full_name = $name;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->status = 'active';
        $user->role_id = $roleId;
        $user->save();
    }

    $userId = (int) $user->id;

    if ($role === 'admin' && Admin::findByUserId($userId) === null) {
        $admin = new Admin();
        $admin->user_id = $userId;
        $admin->employee_id = 'AM-ADM-' . $userId;
        $admin->save();
    }

    if ($role === 'doctor' && Doctor::findByUserId($userId) === null) {
        $doctor = new Doctor();
        $doctor->user_id = $userId;
        $doctor->professional_title = 'Medical Officer';
        $doctor->specialization = 'General Practice';
        $doctor->license_number = 'AM-LIC-' . $userId;
        $doctor->clinic_address = 'Alotau Provincial Hospital';
        $doctor->save();
    }

    if ($role === 'patient' && Patient::findByUserId($userId) === null) {
        $patient = new Patient();
        $patient->user_id = $userId;
        $patient->dob = '1990-03-12';
        $patient->gender = 'female';
        $patient->address = '123 Test Street, Alotau';
        $patient->phone = '67570000001';
        $patient->medical_history = 'AM_TEST_HISTORY';
        $patient->save();
    }

    return [
        'id' => $userId,
        'email' => $email,
        'password' => $password,
        'name' => $name,
        'role' => $role,
    ];
}

final class AcctHttp
{
    public string $baseUrl;
    public string $cookieFile;
    public int $status = 0;
    public string $url = '';
    public string $body = '';
    public string $headers = '';

    public function __construct(string $baseUrl, string $cookieFile)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = $cookieFile;
        $dir = dirname($cookieFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (is_file($cookieFile)) {
            unlink($cookieFile);
        }
    }

    public function get(string $path, bool $follow = true): self
    {
        return $this->request('GET', $path, [], $follow);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function post(string $path, array $fields, bool $follow = true): self
    {
        return $this->request('POST', $path, $fields, $follow);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function request(string $method, string $path, array $fields, bool $follow): self
    {
        $url = str_starts_with($path, 'http') ? $path : $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        $headers = ['Expect:'];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => 'MBPHA-AccountManagement/1.0',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $raw = (string) curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->url = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($errno !== 0) {
            $this->headers = '';
            $this->body = 'CURL_ERROR: ' . $error;
            return $this;
        }

        $this->headers = substr($raw, 0, $headerSize);
        $this->body = substr($raw, $headerSize);

        return $this;
    }

    public function csrf(): string
    {
        if (preg_match('/name="_token"\s+value="([^"]+)"/', $this->body, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
        }

        return '';
    }

    public function reachable(): bool
    {
        return $this->status > 0 && !str_starts_with($this->body, 'CURL_ERROR:');
    }

    public function pathContains(string $needle): bool
    {
        return str_contains($this->url, $needle);
    }
}

$db = Database::getInstance();
$routes = (string) file_get_contents($root . '/routes/web.php');

echo "=== ACCOUNT MANAGEMENT ===\n";

$thrown = false;
try {
    User::deleteById(1);
} catch (LogicException) {
    $thrown = true;
}
expect_true($thrown, 'User::deleteById refuses blind cascade deletion');

expect_true(
    !preg_match('/consultation[-_]record[s]?.+delete|delete.+consultation[-_]record/i', $routes),
    'No consultation-record delete route is registered'
);
expect_true(
    str_contains($routes, "/admin/users/{id}/delete"),
    'Administrator permanent-delete route is registered'
);
expect_true(
    str_contains($routes, "/admin/users/delete-selected"),
    'Administrator bulk-delete route is registered'
);
expect_true(
    str_contains($routes, "/notifications/{id}/delete"),
    'Notification delete route is registered'
);

$adminPassword = 'AcctMgmt#Admin9';
$doctorPassword = 'AcctMgmt#Doc9aa';
$patientPassword = 'AcctMgmt#Pat9aa';
$stamp = date('YmdHis');

$admin = acct_upsert_user('admin', 'acctmgmt.admin@mbpha.test', 'Account Management Admin', $adminPassword);
$doctor = acct_upsert_user('doctor', 'acctmgmt.doctor.' . $stamp . '@mbpha.test', 'Account Management Doctor', $doctorPassword);
$patient = acct_upsert_user('patient', 'acctmgmt.patient.' . $stamp . '@mbpha.test', 'Account Management Patient', $patientPassword);
$patientB = acct_upsert_user('patient', 'acctmgmt.patientb.' . $stamp . '@mbpha.test', 'Account Management Patient B', $patientPassword);

expect_true(AuthService::authenticate($admin['email'], $admin['password']) !== null, 'Administrator fixture can authenticate');
expect_true(AuthService::authenticate($doctor['email'], $doctor['password']) !== null, 'Doctor fixture can authenticate');
expect_true(AuthService::authenticate($patient['email'], $patient['password']) !== null, 'Patient fixture can authenticate');

$activeActions = array_column(AdminUserService::availableActions([
    'id' => $patient['id'],
    'status' => 'active',
    'role_name' => 'patient',
], $admin['id']), 'key');
expect_true(in_array('suspend', $activeActions, true), 'Active accounts expose Suspend');
expect_true(in_array('deactivate', $activeActions, true), 'Active accounts expose Deactivate');
expect_true(!in_array('reactivate', $activeActions, true), 'Active accounts do not expose Reactivate');
expect_true(in_array('delete', $activeActions, true), 'Active accounts expose permanent delete');

$suspendedActions = array_column(AdminUserService::availableActions([
    'id' => $patient['id'],
    'status' => 'suspended',
    'role_name' => 'patient',
], $admin['id']), 'key');
expect_true(in_array('reactivate', $suspendedActions, true), 'Suspended accounts expose Reactivate');
expect_true(!in_array('suspend', $suspendedActions, true), 'Suspended accounts do not expose Suspend');

$deletedActions = array_column(AdminUserService::availableActions([
    'id' => $patient['id'],
    'status' => 'deleted',
    'role_name' => 'patient',
], $admin['id']), 'key');
expect_true(!in_array('delete', $deletedActions, true), 'Deleted accounts do not expose permanent delete');
expect_true(!in_array('edit', $deletedActions, true), 'Deleted accounts do not expose Edit');

$selfActions = array_column(AdminUserService::availableActions([
    'id' => $admin['id'],
    'status' => 'active',
    'role_name' => 'admin',
], $admin['id']), 'key');
expect_true(!in_array('delete', $selfActions, true), 'Administrators cannot permanently delete themselves from the action menu');
expect_true(!in_array('suspend', $selfActions, true), 'Administrators cannot suspend themselves from the action menu');

$csrf = Csrf::generate();
$doctorStatusAttempt = AdminUserService::changeAccountStatus(
    $patientB['id'],
    $doctor['id'],
    Status::USER_SUSPENDED,
    $csrf
);
expect_true(($doctorStatusAttempt['success'] ?? true) === false, 'Doctors cannot change another account status through the admin service');

$patientDetail = AdminUserService::getUserDetail($patient['id'], $admin['id']);
$detailJson = json_encode($patientDetail, JSON_UNESCAPED_UNICODE) ?: '';
expect_true($patientDetail !== null, 'Administrator can load non-clinical user detail');
expect_true(!str_contains($detailJson, 'AM_TEST_HISTORY'), 'User-management detail does not expose medical history');
expect_true(!str_contains($detailJson, 'PROTECTED_DIAGNOSIS'), 'User-management detail does not include clinical diagnosis text');
expect_true(isset($patientDetail['protected_counts']), 'User detail includes protected-record counts only');

$notifyA = Notification::createOnce([
    'user_id' => $patient['id'],
    'notification_type' => NotificationService::TYPE_APPROVED,
    'title' => 'Appointment Approved',
    'message' => 'Your consultation has been approved.',
    'related_entity_type' => Notification::ENTITY_CONSULTATION_REQUEST,
    'related_entity_id' => 910000 + (int) $stamp % 100000,
]);
$notifyB = Notification::createOnce([
    'user_id' => $patientB['id'],
    'notification_type' => NotificationService::TYPE_APPROVED,
    'title' => 'Appointment Approved',
    'message' => 'Your consultation has been approved.',
    'related_entity_type' => Notification::ENTITY_CONSULTATION_REQUEST,
    'related_entity_id' => 920000 + (int) $stamp % 100000,
]);
expect_true($notifyA > 0 && $notifyB > 0, 'Notification fixtures can be created');
expect_true(NotificationService::belongsToUser($patient['id'], $notifyA), 'Owner is recognised for their notification');
expect_true(!NotificationService::belongsToUser($patient['id'], $notifyB), 'IDOR: patient cannot own another user notification');
expect_true(Notification::deleteForUser($notifyB, $patient['id']) === false, 'IDOR: patient cannot delete another user notification');
expect_true(Notification::findByIdForUser($notifyB, $patientB['id']) !== null, 'Foreign notification remains after IDOR delete');
expect_true(Notification::markUnreadForUser($notifyA, $patientB['id']) === false, 'IDOR: mark unread is owner-scoped');

$currentHash = SessionService::currentHash();
if ($currentHash !== '') {
    UserSession::upsert([
        'user_id' => $patient['id'],
        'session_token_hash' => $currentHash,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit-CLI',
    ]);
    UserSession::upsert([
        'user_id' => $patient['id'],
        'session_token_hash' => hash('sha256', 'other-device-' . $stamp),
        'ip_address' => '127.0.0.2',
        'user_agent' => 'Other Device',
    ]);
    $revoked = SessionService::revokeOthers($patient['id']);
    expect_true($revoked >= 1, 'Logout-other-devices removes other session registry rows');
    expect_true(UserSession::existsForUser($patient['id'], $currentHash), 'Current session remains registered');
    UserSession::deleteCurrent($patient['id'], $currentHash);
    UserSession::upsert([
        'user_id' => $patient['id'],
        'session_token_hash' => hash('sha256', 'kept-other-' . $stamp),
        'ip_address' => '127.0.0.3',
        'user_agent' => 'Kept Device',
    ]);
    expect_true(SessionService::ensureCurrent($patient['id']) === false, 'Revoked PHP sessions cannot re-enrol while other devices remain');
} else {
    skip('Session hash unavailable in CLI for logout-other-devices check');
}

$diagnosis = 'PROTECTED_DIAGNOSIS_DO_NOT_DELETE';
$requestId = 0;
$recordId = 0;
$prescriptionId = 0;

try {
    $requestSql = 'INSERT INTO consultation_requests (patient_id, doctor_id, reason, status) VALUES (:patient_id, :doctor_id, :reason, :status)';
    $request = $db->prepare($requestSql);
    $request->execute([
        ':patient_id' => $patient['id'],
        ':doctor_id' => $doctor['id'],
        ':reason' => 'Account-management deletion fixture',
        ':status' => 'Completed',
    ]);
    $requestId = (int) $db->lastInsertId();

    $columns = ['consultation_request_id', 'patient_id', 'doctor_id', 'diagnosis'];
    $values = [':consultation_request_id', ':patient_id', ':doctor_id', ':diagnosis'];
    $params = [
        ':consultation_request_id' => $requestId,
        ':patient_id' => $patient['id'],
        ':doctor_id' => $doctor['id'],
        ':diagnosis' => $diagnosis,
    ];
    if (acct_has_column($db, 'consultation_records', 'record_status')) {
        $columns[] = 'record_status';
        $values[] = ':record_status';
        $params[':record_status'] = 'Final';
    }
    if (acct_has_column($db, 'consultation_records', 'finalized_at')) {
        $columns[] = 'finalized_at';
        $values[] = 'NOW()';
    }
    $record = $db->prepare(
        'INSERT INTO consultation_records (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')'
    );
    $record->execute($params);
    $recordId = (int) $db->lastInsertId();

    $rxColumns = ['consultation_record_id', 'doctor_id', 'patient_id', 'medication_name', 'dosage', 'frequency', 'duration'];
    $rxValues = [':consultation_record_id', ':doctor_id', ':patient_id', ':medication_name', ':dosage', ':frequency', ':duration'];
    $rxParams = [
        ':consultation_record_id' => $recordId,
        ':doctor_id' => $doctor['id'],
        ':patient_id' => $patient['id'],
        ':medication_name' => 'Paracetamol',
        ':dosage' => '500mg',
        ':frequency' => 'TDS',
        ':duration' => '5 days',
    ];
    if (acct_has_column($db, 'prescriptions', 'quantity')) {
        $rxColumns[] = 'quantity';
        $rxValues[] = ':quantity';
        $rxParams[':quantity'] = '15';
    }
    $rx = $db->prepare(
        'INSERT INTO prescriptions (' . implode(', ', $rxColumns) . ') VALUES (' . implode(', ', $rxValues) . ')'
    );
    $rx->execute($rxParams);
    $prescriptionId = (int) $db->lastInsertId();
} catch (Throwable $exception) {
    echo 'NOTE  Clinical fixture insert: ' . $exception->getMessage() . "\n";
}

expect_true($requestId > 0 && $recordId > 0 && $prescriptionId > 0, 'Clinical history fixture was created for deletion testing');

$auditBefore = (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$wrongPhrase = UserDeletionService::permanentlyDelete(
    $patient['id'],
    $admin['id'],
    $csrf,
    'delete user',
    $adminPassword
);
expect_true(($wrongPhrase['success'] ?? true) === false, 'Permanent deletion requires the exact DELETE USER phrase');
$stillActive = User::findById($patient['id']);
expect_true($stillActive !== null && (string) $stillActive->status === 'active', 'Failed confirmation leaves the account unchanged');

$badCsrf = UserDeletionService::permanentlyDelete(
    $patient['id'],
    $admin['id'],
    'not-a-real-csrf-token',
    AccountSecurityService::CONFIRMATION_PHRASE,
    $adminPassword
);
expect_true(($badCsrf['success'] ?? true) === false, 'Permanent deletion requires a valid CSRF token');

$doctorDelete = UserDeletionService::permanentlyDelete(
    $patient['id'],
    $doctor['id'],
    $csrf,
    AccountSecurityService::CONFIRMATION_PHRASE,
    $doctorPassword
);
expect_true(($doctorDelete['success'] ?? true) === false, 'Doctors cannot permanently delete users');

$result = UserDeletionService::permanentlyDelete(
    $patient['id'],
    $admin['id'],
    $csrf,
    AccountSecurityService::CONFIRMATION_PHRASE,
    $adminPassword
);
expect_true(($result['success'] ?? false) === true, 'Administrator can permanently delete a user with confirmation and password');

$deletedUser = User::findById($patient['id']);
expect_true($deletedUser !== null, 'Users row is retained after permanent deletion');
expect_true(Status::isDeletedUserStatus((string) ($deletedUser->status ?? '')), 'Deleted account status is deleted');
expect_true((string) ($deletedUser->full_name ?? '') === 'Deleted User', 'Account display name is anonymized');
expect_true(str_contains((string) ($deletedUser->email ?? ''), '@anonymized.invalid'), 'Account email is anonymized');
expect_true(!is_string($deletedUser->password) || $deletedUser->password === '', 'Deleted account password hash is removed');
expect_true(AuthService::authenticate($patient['email'], $patientPassword) === null, 'Anonymized original email can no longer authenticate');
expect_true(Patient::findByUserId($patient['id']) !== null, 'Patient extension row is retained for clinical foreign keys');

$patientRow = $db->prepare('SELECT dob, gender, address, phone, medical_history FROM patient WHERE user_id = :id');
$patientRow->execute([':id' => $patient['id']]);
$pii = $patientRow->fetch(PDO::FETCH_ASSOC) ?: [];
expect_true(($pii['dob'] ?? 'x') === null || ($pii['dob'] ?? '') === '', 'Patient date of birth is cleared');
expect_true(($pii['address'] ?? 'x') === null || ($pii['address'] ?? '') === '', 'Patient address is cleared');
expect_true(($pii['medical_history'] ?? 'x') === null || ($pii['medical_history'] ?? '') === '', 'Patient medical history is cleared from the account profile');

if ($recordId > 0) {
    $kept = $db->prepare('SELECT id, patient_id, doctor_id, diagnosis FROM consultation_records WHERE id = :id');
    $kept->execute([':id' => $recordId]);
    $record = $kept->fetch(PDO::FETCH_ASSOC) ?: [];
    expect_true((int) ($record['id'] ?? 0) === $recordId, 'Consultation record survives user deletion');
    expect_true((int) ($record['patient_id'] ?? 0) === $patient['id'], 'Consultation record retains the patient foreign key');
    expect_true((int) ($record['doctor_id'] ?? 0) === $doctor['id'], 'Consultation record retains the doctor foreign key');
    expect_true((string) ($record['diagnosis'] ?? '') === $diagnosis, 'Clinical diagnosis content is not deleted');
} else {
    skip('Consultation record preservation (fixture was not created)');
}

if ($prescriptionId > 0) {
    $rxKept = $db->prepare('SELECT id, patient_id FROM prescriptions WHERE id = :id');
    $rxKept->execute([':id' => $prescriptionId]);
    $rxRow = $rxKept->fetch(PDO::FETCH_ASSOC) ?: [];
    expect_true((int) ($rxRow['id'] ?? 0) === $prescriptionId, 'Prescription survives user deletion');
    expect_true((int) ($rxRow['patient_id'] ?? 0) === $patient['id'], 'Prescription retains the patient foreign key');
} else {
    skip('Prescription preservation (fixture was not created)');
}

if ($requestId > 0) {
    $reqKept = $db->prepare('SELECT id, status FROM consultation_requests WHERE id = :id');
    $reqKept->execute([':id' => $requestId]);
    $reqRow = $reqKept->fetch(PDO::FETCH_ASSOC) ?: [];
    expect_true((int) ($reqRow['id'] ?? 0) === $requestId, 'Completed consultation request survives user deletion');
}

$auditAfter = (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
expect_true($auditAfter > $auditBefore, 'Permanent deletion writes an audit event');
$auditStmt = $db->prepare(
    "SELECT event_type, action, description, actor_user_id
     FROM audit_logs
     WHERE actor_user_id = :actor
     ORDER BY id DESC
     LIMIT 1"
);
$auditStmt->execute([':actor' => $admin['id']]);
$audit = $auditStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$auditType = (string) ($audit['event_type'] ?? $audit['action'] ?? '');
expect_true($auditType === 'user_deleted', 'Deletion audit event type is user_deleted');
expect_true(!str_contains((string) ($audit['description'] ?? ''), $diagnosis), 'Deletion audit does not contain clinical diagnosis text');
expect_true(!str_contains((string) ($audit['description'] ?? ''), $adminPassword), 'Deletion audit does not contain passwords');
expect_true(Notification::countForUser($patient['id']) === 0, 'User-facing notifications for the deleted account are removed');

$csrf2 = Csrf::generate();
$repeat = UserDeletionService::permanentlyDelete(
    $patient['id'],
    $admin['id'],
    $csrf2,
    AccountSecurityService::CONFIRMATION_PHRASE,
    $adminPassword
);
expect_true(($repeat['success'] ?? true) === false, 'A already-deleted account cannot be deleted again');

$anonymizedEmail = (string) ($deletedUser->email ?? '');
$listedIds = array_map(
    static fn (array $row): int => (int) ($row['id'] ?? 0),
    User::findForManagement(['search' => $anonymizedEmail], 20, 0)
);
expect_true(!in_array($patient['id'], $listedIds, true), 'Permanently deleted users no longer appear on the users list');
$forcedDeletedFilter = array_map(
    static fn (array $row): int => (int) ($row['id'] ?? 0),
    User::findForManagement(['status' => 'deleted', 'search' => $anonymizedEmail], 20, 0)
);
expect_true(!in_array($patient['id'], $forcedDeletedFilter, true), 'Deleted status cannot be used to bring deleted users back onto the list');
expect_true(AdminUserService::getUserDetail($patient['id'], $admin['id']) === null, 'Deleted users cannot be opened from user management');
expect_true(!in_array('deleted', AdminUserService::getStatusOptions(), true), 'User-management status filters do not include Deleted');

$listedPatientIds = array_map(
    static fn (array $row): int => (int) ($row['id'] ?? 0),
    Patient::findForManagement(['search' => $anonymizedEmail], 20, 0)
);
expect_true(!in_array($patient['id'], $listedPatientIds, true), 'Permanently deleted patients no longer appear on the patients list');

$bulkA = acct_upsert_user('patient', 'acctmgmt.bulk.a.' . $stamp . '@mbpha.test', 'Bulk Delete Patient A', $patientPassword);
$bulkB = acct_upsert_user('patient', 'acctmgmt.bulk.b.' . $stamp . '@mbpha.test', 'Bulk Delete Patient B', $patientPassword);
$csrfBulk = Csrf::generate();
$emptyBulk = UserDeletionService::permanentlyDeleteMany(
    [],
    $admin['id'],
    $csrfBulk,
    AccountSecurityService::CONFIRMATION_PHRASE,
    $adminPassword
);
expect_true(($emptyBulk['success'] ?? true) === false, 'Bulk delete requires at least one selected user');
expect_true(AuthService::authenticate($bulkA['email'], $bulkA['password']) !== null, 'Bulk fixture A remains after an empty selection');

$badBulkCsrf = UserDeletionService::permanentlyDeleteMany(
    [$bulkA['id'], $bulkB['id']],
    $admin['id'],
    'invalid-csrf',
    AccountSecurityService::CONFIRMATION_PHRASE,
    $adminPassword
);
expect_true(($badBulkCsrf['success'] ?? true) === false, 'Bulk delete requires a valid CSRF token');
expect_true(AuthService::authenticate($bulkA['email'], $bulkA['password']) !== null, 'Invalid CSRF does not delete selected users');

$bulkResult = UserDeletionService::permanentlyDeleteMany(
    [$bulkA['id'], $bulkB['id'], $admin['id']],
    $admin['id'],
    $csrfBulk,
    AccountSecurityService::CONFIRMATION_PHRASE,
    $adminPassword
);
expect_true(($bulkResult['success'] ?? false) === true, 'Administrator can permanently delete selected users');
expect_true((int) ($bulkResult['deleted'] ?? 0) === 2, 'Bulk delete removes the selected patient accounts');
expect_true((int) ($bulkResult['skipped'] ?? 0) >= 1, 'Bulk delete skips the signed-in administrator');
expect_true(AdminUserService::getUserDetail($bulkA['id'], $admin['id']) === null, 'Bulk-deleted user A is removed from the users list');
expect_true(AdminUserService::getUserDetail($bulkB['id'], $admin['id']) === null, 'Bulk-deleted user B is removed from the users list');
expect_true(AuthService::authenticate($admin['email'], $admin['password']) !== null, 'Administrator account remains after a bulk delete that included their own id');

$lockStmt = $db->prepare('UPDATE consultation_records SET diagnosis = :diagnosis WHERE id = :id');
if ($recordId > 0) {
    $lockStmt->execute([':diagnosis' => $diagnosis, ':id' => $recordId]);
}

$baseUrl = rtrim((string) Environment::get('APP_URL', 'http://localhost/Telehealth_Consultation_System/public'), '/');
$tmp = $root . '/tmp/account-management';
$probe = new AcctHttp($baseUrl, $tmp . '/probe.txt');
$probe->get('/login', false);

if (!$probe->reachable()) {
    skip('HTTP negatives (APP_URL was not reachable: ' . $baseUrl . ')');
} else {
    $httpPatient = new AcctHttp($baseUrl, $tmp . '/patient.txt');
    $httpPatient->get('/login');
    $loginToken = $httpPatient->csrf();
    $httpPatient->post('/login', [
        '_token' => $loginToken,
        'email' => $patientB['email'],
        'password' => $patientB['password'],
    ]);
    expect_true($httpPatient->pathContains('/patient/dashboard') || $httpPatient->status === 200, 'Patient HTTP login succeeds');

    $httpPatient->get('/notifications');
    $patientToken = $httpPatient->csrf();
    $victimBefore = User::findById($doctor['id']);
    $httpPatient->post('/admin/users/' . $doctor['id'] . '/delete', [
        '_token' => $patientToken,
        'confirmation_phrase' => AccountSecurityService::CONFIRMATION_PHRASE,
        'admin_password' => $patientB['password'],
    ]);
    $victimAfter = User::findById($doctor['id']);
    expect_true(
        $victimAfter !== null && (string) ($victimAfter->status ?? '') === (string) ($victimBefore->status ?? 'active'),
        'Patient HTTP POST to admin user delete does not delete the target'
    );
    $httpPatient->post('/admin/users/delete-selected', [
        '_token' => $patientToken,
        'confirmation_phrase' => AccountSecurityService::CONFIRMATION_PHRASE,
        'admin_password' => $patientB['password'],
        'user_ids' => [$doctor['id']],
    ]);
    $victimAfterBulk = User::findById($doctor['id']);
    expect_true(
        $victimAfterBulk !== null && (string) ($victimAfterBulk->status ?? '') === 'active',
        'Patient HTTP POST to bulk user delete does not delete the target'
    );
    expect_true(
        !$httpPatient->pathContains('/admin/users'),
        'Patient is not admitted to administrator user management after the delete attempt'
    );

    if ($notifyB > 0) {
        $httpDoctor = new AcctHttp($baseUrl, $tmp . '/doctor.txt');
        $httpDoctor->get('/login');
        $docToken = $httpDoctor->csrf();
        $httpDoctor->post('/login', [
            '_token' => $docToken,
            'email' => $doctor['email'],
            'password' => $doctor['password'],
        ]);
        $httpDoctor->get('/notifications');
        $docCsrf = $httpDoctor->csrf();
        $httpDoctor->post('/admin/users/' . $patientB['id'] . '/delete', [
            '_token' => $docCsrf,
            'confirmation_phrase' => AccountSecurityService::CONFIRMATION_PHRASE,
            'admin_password' => $doctorPassword,
        ]);
        $patientBAfter = User::findById($patientB['id']);
        expect_true(
            $patientBAfter !== null && (string) ($patientBAfter->status ?? '') === 'active',
            'Doctor HTTP POST to admin user delete does not delete the target'
        );

        $httpDoctor->post('/notifications/' . $notifyB . '/delete', [
            '_token' => $docCsrf,
        ]);
        expect_true(
            Notification::findByIdForUser($notifyB, $patientB['id']) !== null,
            'Doctor cannot delete a patient notification by changing the id'
        );
    }

    $httpDoctorRecord = new AcctHttp($baseUrl, $tmp . '/doctor-record.txt');
    $httpDoctorRecord->get('/login');
    $docLogin = $httpDoctorRecord->csrf();
    $httpDoctorRecord->post('/login', [
        '_token' => $docLogin,
        'email' => $doctor['email'],
        'password' => $doctor['password'],
    ]);
    if ($recordId > 0) {
        $httpDoctorRecord->post('/doctor/consultations/' . $recordId . '/delete', [
            '_token' => $httpDoctorRecord->csrf() !== '' ? $httpDoctorRecord->csrf() : 'x',
        ], false);
        $stillThere = $db->prepare('SELECT id FROM consultation_records WHERE id = :id');
        $stillThere->execute([':id' => $recordId]);
        expect_true((int) $stillThere->fetchColumn() === $recordId, 'POST to a consultation-record delete URL does not remove the record');
    }
}

echo "\n{$passed} passed, {$failed} failed, {$skipped} skipped\n";
exit($failed > 0 ? 1 : 0);
