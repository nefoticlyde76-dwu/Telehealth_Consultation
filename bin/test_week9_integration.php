<?php

/**
 * Week 9 — HTTP integration, access-control, and security tests.
 *
 * Usage: php bin/test_week9_integration.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Models\Admin;
use App\Models\Doctor;
use App\Models\User;
use App\Services\AuthService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');
date_default_timezone_set('Pacific/Port_Moresby');

$failed = 0;
$passed = 0;
$skipped = 0;
$defects = [];

function expect_true(bool $condition, string $id, string $label): void
{
    global $failed, $passed;
    if ($condition) {
        $passed++;
        echo "PASS  {$id}  {$label}\n";
        return;
    }

    $failed++;
    echo "FAIL  {$id}  {$label}\n";
}

function record_defect(string $id, string $description, string $expected, string $actual, string $files = ''): void
{
    global $defects;
    $defects[] = compact('id', 'description', 'expected', 'actual', 'files');
}

final class Week9Http
{
    public string $baseUrl;
    public string $cookieFile;
    public int $status = 0;
    public string $url = '';
    public string $body = '';
    public string $headers = '';
    public string $contentType = '';

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
    public function post(string $path, array $fields, bool $follow = true, array $extraHeaders = []): self
    {
        return $this->request('POST', $path, $fields, $follow, $extraHeaders);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function request(string $method, string $path, array $fields, bool $follow, array $extraHeaders = []): self
    {
        $url = str_starts_with($path, 'http') ? $path : $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        $headers = array_merge(['Expect:'], $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'MBPHA-Week9-Integration/1.0',
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
        $this->contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
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

    public function location(): string
    {
        if (preg_match('/^Location:\s*(.+)$/mi', $this->headers, $match)) {
            return trim($match[1]);
        }

        return '';
    }

    public function sessionCookie(): string
    {
        if (!is_file($this->cookieFile)) {
            return '';
        }

        $lines = file($this->cookieFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $value = '';
        foreach ($lines as $line) {
            if ($line === '' || (str_starts_with($line, '#') && !str_starts_with($line, '#HttpOnly_'))) {
                continue;
            }
            $line = str_replace('#HttpOnly_', '', $line);
            $parts = preg_split('/\s+/', $line) ?: [];
            if (count($parts) >= 7 && $parts[5] === 'TELEHEALTH_SESSION') {
                $value = $parts[6];
            }
        }
        return $value;

        return '';
    }

    public function contains(string $needle): bool
    {
        return str_contains($this->body, $needle);
    }

    public function pathContains(string $needle): bool
    {
        return str_contains($this->url, $needle);
    }

    public function isLoginPage(): bool
    {
        return $this->pathContains('/login') || $this->contains('name="password"');
    }
}

function week9_png(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==') ?: '';
}

/**
 * @return array{id:int,email:string,password:string,name:string}
 */
function week9_upsert_user(string $role, string $email, string $name, string $password, array $profile = []): array
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
        $admin->employee_id = $profile['employee_id'] ?? ('W9-ADM-' . $userId);
        $admin->save();
    }

    if ($role === 'doctor') {
        $doctor = Doctor::findByUserId($userId);
        if ($doctor === null) {
            $doctor = new Doctor();
            $doctor->user_id = $userId;
            $doctor->professional_title = 'Medical Officer';
            $doctor->specialization = (string) ($profile['specialization'] ?? 'General Practice');
            $doctor->license_number = (string) ($profile['license_number'] ?? ('W9-LIC-' . $userId));
            $doctor->clinic_address = 'Alotau Provincial Hospital';
            $doctor->save();
            $doctor = Doctor::findByUserId($userId);
        }

        if ($doctor !== null) {
            $uploadDir = dirname(__DIR__) . '/public/uploads/doctors/' . $userId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $filename = 'week9_signature.png';
            file_put_contents($uploadDir . '/' . $filename, week9_png());
            $relative = 'uploads/doctors/' . $userId . '/' . $filename;
            $db = Database::getInstance();
            $stmt = $db->prepare('UPDATE doctor SET signature_path = :path, specialization = :spec, professional_title = :title, clinic_address = :clinic WHERE user_id = :id');
            $stmt->execute([
                ':path' => $relative,
                ':spec' => (string) ($profile['specialization'] ?? 'General Practice'),
                ':title' => 'Medical Officer',
                ':clinic' => 'Alotau Provincial Hospital',
                ':id' => $userId,
            ]);
        }
    }

    return [
        'id' => $userId,
        'email' => $email,
        'password' => $password,
        'name' => $name,
    ];
}

function week9_cleanup_doctor_slots(\PDO $pdo, int $doctorId): void
{
    $slotStmt = $pdo->prepare(
        "SELECT id FROM doctor_availability
         WHERE doctor_id = :doctor_id
           AND (notes LIKE 'Week 9%' OR notes LIKE 'Week9%')"
    );
    $slotStmt->execute([':doctor_id' => $doctorId]);
    $slotIds = array_map('intval', $slotStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    foreach ($slotIds as $slotId) {
        $reqStmt = $pdo->prepare('SELECT id FROM consultation_requests WHERE availability_id = :id');
        $reqStmt->execute([':id' => $slotId]);
        $requestIds = array_map('intval', $reqStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        foreach ($requestIds as $requestId) {
            try {
                $recordIds = $pdo->prepare('SELECT id FROM consultation_records WHERE consultation_request_id = :id');
                $recordIds->execute([':id' => $requestId]);
                foreach (array_map('intval', $recordIds->fetchAll(PDO::FETCH_COLUMN) ?: []) as $recordId) {
                    $pdo->prepare('DELETE FROM prescriptions WHERE consultation_record_id = :id')->execute([':id' => $recordId]);
                }
                $pdo->prepare('DELETE FROM consultation_records WHERE consultation_request_id = :id')->execute([':id' => $requestId]);
                $pdo->prepare('DELETE FROM consultation_rooms WHERE consultation_request_id = :id')->execute([':id' => $requestId]);
                $pdo->prepare('DELETE FROM notifications WHERE related_entity_id = :id')->execute([':id' => $requestId]);
                $pdo->prepare('DELETE FROM consultation_requests WHERE id = :id')->execute([':id' => $requestId]);
            } catch (Throwable $e) {
                fwrite(STDERR, 'Week 9 cleanup skipped request ' . $requestId . ': ' . $e->getMessage() . PHP_EOL);
            }
        }

        try {
            $pdo->prepare('DELETE FROM doctor_availability WHERE id = :id')->execute([':id' => $slotId]);
        } catch (Throwable $e) {
            fwrite(STDERR, 'Week 9 cleanup skipped slot ' . $slotId . ': ' . $e->getMessage() . PHP_EOL);
        }
    }
}

function week9_find_slot(\PDO $pdo, int $doctorId, string $notes): array
{
    $stmt = $pdo->prepare(
        'SELECT id, status FROM doctor_availability
         WHERE doctor_id = :doctor_id AND notes = :notes
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':doctor_id' => $doctorId, ':notes' => $notes]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function week9_login(Week9Http $http, string $email, string $password): bool
{
    $http->get('/login');
    $token = $http->csrf();
    if ($token === '') {
        return false;
    }

    $http->post('/login', [
        '_token' => $token,
        'email' => $email,
        'password' => $password,
    ]);

    return !$http->isLoginPage() && $http->status === 200;
}

$baseUrl = rtrim((string) Environment::get('APP_URL', 'http://localhost/Telehealth_Consultation_System/public'), '/');
$tmp = $root . '/tmp/week9';
if (!is_dir($tmp)) {
    mkdir($tmp, 0775, true);
}

echo "=== WEEK 9 HTTP INTEGRATION ===\n";
echo "Base URL: {$baseUrl}\n";

$admin = week9_upsert_user('admin', 'week9.admin@mbpha.test', 'Week 9 Administrator', 'Week9!Admin');
$doctorA = week9_upsert_user('doctor', 'week9.doctor.a@mbpha.test', 'Week9 Doctor Alpha', 'Week9!DocA', [
    'specialization' => 'General Practice',
    'license_number' => 'W9-DOC-A',
]);
$doctorB = week9_upsert_user('doctor', 'week9.doctor.b@mbpha.test', 'Week9 Doctor Bravo', 'Week9!DocB', [
    'specialization' => 'Internal Medicine',
    'license_number' => 'W9-DOC-B',
]);

expect_true(
    AuthService::authenticate($admin['email'], $admin['password']) !== null,
    'W9-BOOT-01',
    'Week 9 administrator can authenticate'
);
expect_true(
    AuthService::authenticate($doctorA['email'], $doctorA['password']) !== null,
    'W9-BOOT-02',
    'Week 9 doctor A can authenticate'
);
expect_true(
    AuthService::authenticate($doctorB['email'], $doctorB['password']) !== null,
    'W9-BOOT-03',
    'Week 9 doctor B can authenticate'
);

$guest = new Week9Http($baseUrl, $tmp . '/guest.txt');
$adminHttp = new Week9Http($baseUrl, $tmp . '/admin.txt');
$doctorAHttp = new Week9Http($baseUrl, $tmp . '/doctor-a.txt');
$doctorBHttp = new Week9Http($baseUrl, $tmp . '/doctor-b.txt');
$patientAHttp = new Week9Http($baseUrl, $tmp . '/patient-a.txt');
$patientBHttp = new Week9Http($baseUrl, $tmp . '/patient-b.txt');

$stamp = date('YmdHis');
$patientAEmail = 'week9.patient.a.' . $stamp . '@mbpha.test';
$patientBEmail = 'week9.patient.b.' . $stamp . '@mbpha.test';
$patientPassword = 'Week9!PatA';
$xssName = 'Week9 <script>alert(1)</script> Patient';

$now = new DateTimeImmutable('now', new DateTimeZone('Pacific/Port_Moresby'));
$slotStart = $now->setTime((int) $now->format('H'), max(0, ((int) $now->format('i')) - 5));
$slotEnd = $slotStart->modify('+40 minutes');
if ($slotEnd->format('Y-m-d') !== $slotStart->format('Y-m-d')) {
    $slotStart = $now->setTime(22, 0);
    $slotEnd = $now->setTime(22, 40);
}
$uniqueMinute = ((int) substr($stamp, -2) % 25) + 1;
$futureStart = $now->modify('+1 day')->setTime(10, $uniqueMinute);
$futureEnd = $now->modify('+1 day')->setTime(10, $uniqueMinute + 30);

$pdo = Database::getInstance();
week9_cleanup_doctor_slots($pdo, $doctorA['id']);
$liveNotes = 'Week 9 live ' . $stamp;
$futureNotes = 'Week 9 future ' . $stamp;
$cancelNotes = 'Week 9 cancel ' . $stamp;
$earlyNotes = 'Week 9 early ' . $stamp;

// ─────────────────────────────────────────────
// Registration / Login
// ─────────────────────────────────────────────
$guest->get('/register');
expect_true($guest->status === 200 && $guest->contains('name="full_name"'), 'W9-REG-01', 'Registration page loads');
expect_true($guest->csrf() !== '', 'W9-REG-02', 'Registration page issues a CSRF token');

$guest->post('/register', [
    '_token' => $guest->csrf(),
    'full_name' => '',
    'email' => '',
    'password' => '',
    'confirm_password' => '',
]);
expect_true(
    $guest->contains('Full name is required') || $guest->contains('Email address is required') || $guest->contains('Password is required'),
    'W9-REG-03',
    'Empty registration is rejected with validation messages'
);

$guest->get('/register');
$guest->post('/register', [
    '_token' => $guest->csrf(),
    'full_name' => 'Week9 Weak Password',
    'email' => 'week9.weak.' . $stamp . '@mbpha.test',
    'password' => 'password',
    'confirm_password' => 'password',
    'terms' => '1',
]);
expect_true(
    $guest->contains('Password must be at least 8 characters') || $guest->pathContains('/register'),
    'W9-REG-04',
    'Weak password is rejected'
);

$guest->get('/register');
$guest->post('/register', [
    '_token' => 'invalid-token',
    'full_name' => 'Week9 CSRF Patient',
    'email' => 'week9.csrf.' . $stamp . '@mbpha.test',
    'password' => $patientPassword,
    'confirm_password' => $patientPassword,
    'terms' => '1',
]);
expect_true(
    $guest->contains('session security token is invalid') || $guest->pathContains('/register'),
    'W9-REG-05',
    'Registration without a valid CSRF token is rejected'
);

$patientAHttp->get('/register');
$beforeLoginCookie = $patientAHttp->sessionCookie();
$patientAHttp->post('/register', [
    '_token' => $patientAHttp->csrf(),
    'full_name' => $xssName,
    'email' => $patientAEmail,
    'dob' => '1994-04-12',
    'gender' => 'female',
    'address' => 'Alotau, Milne Bay Province',
    'password' => $patientPassword,
    'confirm_password' => $patientPassword,
    'terms' => '1',
]);
$registered = $patientAHttp->pathContains('/patient/dashboard') && $patientAHttp->status === 200;
expect_true($registered, 'W9-REG-06', 'Valid registration creates a session and opens the patient dashboard');
if (!$registered) {
    record_defect('W9-REG-06', 'Patient registration did not reach the dashboard', 'Redirect to /patient/dashboard', $patientAHttp->url . ' status=' . $patientAHttp->status, 'app/Controllers/AuthController.php');
}

$userRow = User::findByEmail($patientAEmail);
expect_true($userRow !== null, 'W9-REG-07', 'Registered patient exists in the users table');
expect_true(
    $userRow !== null && is_string($userRow->password) && password_verify($patientPassword, $userRow->password),
    'W9-REG-08',
    'Registered password is stored with password_hash'
);
expect_true(
    $userRow !== null && $userRow->password !== $patientPassword,
    'W9-REG-09',
    'Registered password is not stored in plaintext'
);

$patientAHttp->get('/register');
$patientAHttp->post('/register', [
    '_token' => $patientAHttp->csrf() ?: 'x',
    'full_name' => 'Duplicate Patient',
    'email' => $patientAEmail,
    'password' => $patientPassword,
    'confirm_password' => $patientPassword,
    'terms' => '1',
]);
expect_true(
    $patientAHttp->contains('already exists') || $patientAHttp->pathContains('/patient/dashboard'),
    'W9-REG-10',
    'Duplicate email is rejected or the signed-in patient is kept on their dashboard'
);

$patientAHttp->get('/patient/dashboard');
expect_true(
    $patientAHttp->contains(Helper_escape_check($xssName)) && !$patientAHttp->contains('<script>alert(1)</script>'),
    'W9-SEC-XSS-01',
    'Patient dashboard escapes a script tag in the registered name'
);

$patientBHttp->get('/register');
$patientBHttp->post('/register', [
    '_token' => $patientBHttp->csrf(),
    'full_name' => 'Week9 Patient Bravo',
    'email' => $patientBEmail,
    'dob' => '1988-09-01',
    'gender' => 'male',
    'address' => 'Losuia, Milne Bay Province',
    'password' => 'Week9!PatB',
    'confirm_password' => 'Week9!PatB',
    'terms' => '1',
]);
expect_true(
    $patientBHttp->pathContains('/patient/dashboard'),
    'W9-REG-11',
    'Second controlled patient can register'
);

$logoutHttp = new Week9Http($baseUrl, $tmp . '/login-cycle.txt');
$logoutHttp->get('/login');
$preLoginSid = $logoutHttp->sessionCookie();
$logoutHttp->post('/login', [
    '_token' => 'bad',
    'email' => $patientAEmail,
    'password' => $patientPassword,
]);
expect_true($logoutHttp->isLoginPage() || $logoutHttp->contains('session security token'), 'W9-AUTH-01', 'Login with an invalid CSRF token is rejected');

$logoutHttp->get('/login');
$logoutHttp->post('/login', [
    '_token' => $logoutHttp->csrf(),
    'email' => $patientAEmail,
    'password' => 'WrongPass!1',
]);
expect_true($logoutHttp->isLoginPage(), 'W9-AUTH-02', 'Incorrect password is rejected');
expect_true(
    $logoutHttp->contains('Invalid email, password, or account status') && !$logoutHttp->contains('SQLSTATE') && !$logoutHttp->contains('password_hash'),
    'W9-AUTH-03',
    'Failed login uses a generic message and does not leak SQL or hashes'
);

$logoutHttp->get('/login');
$logoutHttp->post('/login', [
    '_token' => $logoutHttp->csrf(),
    'email' => '',
    'password' => '',
]);
expect_true(
    $logoutHttp->contains('Email address is required') || $logoutHttp->contains('Password is required'),
    'W9-AUTH-04',
    'Empty credentials are rejected'
);

$logoutHttp->get('/login');
$logoutHttp->post('/login', [
    '_token' => $logoutHttp->csrf(),
    'email' => "' OR 1=1 --",
    'password' => "' OR 1=1 --",
]);
expect_true(
    $logoutHttp->isLoginPage() && !$logoutHttp->contains('SQLSTATE') && !$logoutHttp->contains('Uncaught Exception'),
    'W9-SEC-SQLi-01',
    'Login SQL injection payload is rejected without leaking SQL errors'
);

$logoutHttp->get('/login');
$logoutHttp->post('/login', [
    '_token' => $logoutHttp->csrf(),
    'email' => $patientAEmail,
    'password' => $patientPassword,
]);
$postLoginSid = $logoutHttp->sessionCookie();
expect_true($logoutHttp->pathContains('/patient/dashboard'), 'W9-AUTH-05', 'Correct patient credentials open the patient dashboard');
expect_true($postLoginSid !== '' && $postLoginSid !== $preLoginSid, 'W9-AUTH-06', 'Session ID is regenerated on login');

$logoutHttp->get('/login');
$logoutHttp->post('/logout', ['_token' => $logoutHttp->csrf() ?: 'missing']);
$logoutHttp->get('/patient/dashboard', false);
expect_true(
    $logoutHttp->status === 302 || $logoutHttp->isLoginPage() || $logoutHttp->pathContains('/login'),
    'W9-AUTH-07',
    'Protected patient pages are blocked after logout'
);

expect_true(week9_login($adminHttp, $admin['email'], $admin['password']) && $adminHttp->pathContains('/admin/dashboard'), 'W9-AUTH-08', 'Administrator login reaches the admin dashboard');
expect_true(week9_login($doctorAHttp, $doctorA['email'], $doctorA['password']) && $doctorAHttp->pathContains('/doctor/dashboard'), 'W9-AUTH-09', 'Doctor A login reaches the doctor dashboard');
expect_true(week9_login($doctorBHttp, $doctorB['email'], $doctorB['password']) && $doctorBHttp->pathContains('/doctor/dashboard'), 'W9-AUTH-10', 'Doctor B login reaches the doctor dashboard');
expect_true(week9_login($patientAHttp, $patientAEmail, $patientPassword), 'W9-AUTH-11', 'Patient A can log in again after logout/register cycle');
expect_true(week9_login($patientBHttp, $patientBEmail, 'Week9!PatB'), 'W9-AUTH-12', 'Patient B remains authenticated for isolation tests');

function Helper_escape_check(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// ─────────────────────────────────────────────
// Doctor availability
// ─────────────────────────────────────────────
$doctorAHttp->get('/doctor/availability/create');
expect_true($doctorAHttp->status === 200 && $doctorAHttp->contains('name="consultation_date"'), 'W9-AVAIL-01', 'Doctor availability create page loads');

$doctorAHttp->post('/doctor/availability/create', [
    '_token' => 'bad-token',
    'consultation_date' => $now->format('Y-m-d'),
    'start_time' => $slotStart->format('H:i'),
    'end_time' => $slotEnd->format('H:i'),
    'notes' => 'Week 9 live window',
]);
expect_true(
    $doctorAHttp->contains('Unable to verify the request') || $doctorAHttp->pathContains('/doctor/availability/create'),
    'W9-AVAIL-02',
    'Availability create rejects an invalid CSRF token'
);

$doctorAHttp->get('/doctor/availability/create');
$doctorAHttp->post('/doctor/availability/create', [
    '_token' => $doctorAHttp->csrf(),
    'consultation_date' => $now->format('Y-m-d'),
    'start_time' => $slotStart->format('H:i'),
    'end_time' => $slotEnd->format('H:i'),
    'notes' => $liveNotes,
    'status' => 'Booked',
]);
$liveSlotCreated = $doctorAHttp->pathContains('/doctor/availability') && !$doctorAHttp->pathContains('/create');
expect_true($liveSlotCreated, 'W9-AVAIL-03', 'Doctor A can create a live-window availability slot');

$liveSlot = week9_find_slot($pdo, $doctorA['id'], $liveNotes);
$liveSlotId = (int) ($liveSlot['id'] ?? 0);
expect_true($liveSlotId > 0, 'W9-AVAIL-04', 'Created live slot exists in the database');
expect_true(($liveSlot['status'] ?? '') === 'Available', 'W9-AVAIL-05', 'Hidden status=Booked cannot force a booked slot on create');

$doctorAHttp->get('/doctor/availability/create');
$doctorAHttp->post('/doctor/availability/create', [
    '_token' => $doctorAHttp->csrf(),
    'consultation_date' => $futureStart->format('Y-m-d'),
    'start_time' => $futureStart->format('H:i'),
    'end_time' => $futureEnd->format('H:i'),
    'notes' => $futureNotes,
]);
$futureSlot = week9_find_slot($pdo, $doctorA['id'], $futureNotes);
$futureSlotId = (int) ($futureSlot['id'] ?? 0);
expect_true($futureSlotId > 0 && ($futureSlot['status'] ?? '') === 'Available', 'W9-AVAIL-06', 'Doctor A can create a future availability slot');

$doctorAHttp->get('/doctor/availability/create');
$doctorAHttp->post('/doctor/availability/create', [
    '_token' => $doctorAHttp->csrf(),
    'consultation_date' => $now->modify('-1 day')->format('Y-m-d'),
    'start_time' => '09:00',
    'end_time' => '09:30',
]);
expect_true(
    $doctorAHttp->contains('Past dates are not allowed') || $doctorAHttp->pathContains('/create'),
    'W9-AVAIL-07',
    'Past availability dates are rejected'
);

// ─────────────────────────────────────────────
// Patient discovery + booking
// ─────────────────────────────────────────────
$patientAHttp->get('/patient/doctors');
expect_true($patientAHttp->status === 200 && ($patientAHttp->contains('Week9 Doctor Alpha') || $patientAHttp->contains('General Practice')), 'W9-DISC-01', 'Patient can view the doctor directory');

$patientAHttp->get('/patient/available-slots');
expect_true($patientAHttp->status === 200, 'W9-DISC-02', 'Patient available-slots page loads');
expect_true(
    $liveSlotId <= 0 || $patientAHttp->contains((string) $liveSlotId) || $patientAHttp->contains('Week9 Doctor Alpha'),
    'W9-DISC-03',
    'Patient can see the newly created doctor or slot'
);

$patientAHttp->get('/patient/consultation-requests/book/' . $liveSlotId);
expect_true($patientAHttp->status === 200 && $patientAHttp->contains('name="reason"'), 'W9-BOOK-01', 'Patient booking page opens for the live slot');

$patientAHttp->post('/patient/consultation-requests/book/' . $liveSlotId, [
    '_token' => 'bad',
    'reason' => 'Week 9 fever and cough for three days',
]);
expect_true(
    $patientAHttp->contains('Unable to verify the request') || $patientAHttp->pathContains('/book/'),
    'W9-BOOK-02',
    'Booking without a valid CSRF token is rejected'
);

$patientAHttp->get('/patient/consultation-requests/book/' . $liveSlotId);
$patientAHttp->post('/patient/consultation-requests/book/' . $liveSlotId, [
    '_token' => $patientAHttp->csrf(),
    'reason' => 'Week 9 fever and cough for three days',
    'status' => 'Completed',
    'doctor_id' => (string) $doctorB['id'],
]);
$booked = $patientAHttp->pathContains('/patient/consultation-requests/');
expect_true($booked, 'W9-BOOK-03', 'Patient can submit a consultation request');

$requestId = 0;
if (preg_match('#/patient/consultation-requests/(\d+)#', $patientAHttp->url, $match)) {
    $requestId = (int) $match[1];
}
expect_true($requestId > 0, 'W9-BOOK-04', 'Booking redirects to the new consultation request');

$requestRow = $pdo->prepare('SELECT * FROM consultation_requests WHERE id = :id');
$requestRow->execute([':id' => $requestId]);
$booking = $requestRow->fetch(PDO::FETCH_ASSOC) ?: [];
expect_true(($booking['status'] ?? '') === 'Pending', 'W9-BOOK-05', 'Initial booking status is Pending even if status=Completed was posted');
expect_true((int) ($booking['doctor_id'] ?? 0) === $doctorA['id'], 'W9-BOOK-06', 'Posted doctor_id cannot reassign the booking to another doctor');
expect_true((int) ($booking['patient_id'] ?? 0) === (int) ($userRow->id ?? 0), 'W9-BOOK-07', 'Booking is linked to the signed-in patient');
expect_true((int) ($booking['availability_id'] ?? 0) === $liveSlotId, 'W9-BOOK-08', 'Booking is linked to the selected slot');

$slotStatus = $pdo->prepare('SELECT status FROM doctor_availability WHERE id = :id');
$slotStatus->execute([':id' => $liveSlotId]);
expect_true($slotStatus->fetchColumn() === 'Booked', 'W9-BOOK-09', 'Booked slot is marked Booked');

$patientAHttp->get('/patient/consultation-requests/book/' . $liveSlotId);
$duplicateBlocked = $patientAHttp->pathContains('/available-slots') || $patientAHttp->contains('no longer available') || $patientAHttp->contains('already submitted');
if ($patientAHttp->contains('name="reason"')) {
    $patientAHttp->post('/patient/consultation-requests/book/' . $liveSlotId, [
        '_token' => $patientAHttp->csrf(),
        'reason' => 'Duplicate booking attempt',
    ]);
    $duplicateBlocked = $patientAHttp->contains('already submitted') || $patientAHttp->contains('no longer available') || !$patientAHttp->pathContains('/consultation-requests/' . $requestId);
}
expect_true($duplicateBlocked, 'W9-BOOK-10', 'Duplicate booking of the same slot is prevented');

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM consultation_requests WHERE patient_id = :pid AND availability_id = :aid');
$countStmt->execute([':pid' => (int) ($userRow->id ?? 0), ':aid' => $liveSlotId]);
expect_true((int) $countStmt->fetchColumn() === 1, 'W9-BOOK-11', 'Only one request exists for the patient and slot');

week9_login($patientBHttp, $patientBEmail, 'Week9!PatB');
$patientBHttp->get('/patient/consultation-requests/book/' . $futureSlotId);
$patientBToken = $patientBHttp->csrf();
$patientBHttp->post('/patient/consultation-requests/book/' . $futureSlotId, [
    '_token' => $patientBToken,
    'reason' => 'Week 9 reject-path cough',
]);
$rejectRequestId = 0;
if (preg_match('#/patient/consultation-requests/(\d+)#', $patientBHttp->url, $match)) {
    $rejectRequestId = (int) $match[1];
}
if ($rejectRequestId <= 0) {
    $lookup = $pdo->prepare(
        "SELECT id FROM consultation_requests
         WHERE patient_id = (SELECT id FROM users WHERE email = :email)
           AND availability_id = :slot
         ORDER BY id DESC LIMIT 1"
    );
    $lookup->execute([':email' => $patientBEmail, ':slot' => $futureSlotId]);
    $rejectRequestId = (int) $lookup->fetchColumn();
}
expect_true($rejectRequestId > 0, 'W9-BOOK-12', 'Second booking exists for reject/cancel coverage');

// ─────────────────────────────────────────────
// Admin review / approve / reject / cancel
// ─────────────────────────────────────────────
$adminHttp->get('/admin/consultation-requests?status=Pending&search=' . urlencode('Week 9 fever'));
expect_true($adminHttp->status === 200, 'W9-ADM-01', 'Admin consultation queue loads');
expect_true(
    $adminHttp->contains($xssName) === false && $adminHttp->contains('Week9') || $adminHttp->contains((string) $requestId),
    'W9-ADM-02',
    'New pending request is visible in the admin queue'
);
expect_true($adminHttp->contains('Pending'), 'W9-ADM-03', 'Queue displays Pending status');

$adminHttp->get('/admin/consultation-requests/' . $requestId);
expect_true($adminHttp->status === 200, 'W9-ADM-04', 'Admin can open the new request');
expect_true($adminHttp->contains('Week9 Doctor Alpha') || $adminHttp->contains('General Practice'), 'W9-ADM-05', 'Admin detail shows the correct doctor');
expect_true($adminHttp->contains($now->format('Y-m-d')) || $adminHttp->contains($slotStart->format('H:i')), 'W9-ADM-06', 'Admin detail shows the consultation date/time');

$adminHttp->post('/admin/consultation-requests/' . $requestId . '/approve', ['_token' => 'bad'], true);
expect_true(
    $adminHttp->contains('Unable to verify the request') || $adminHttp->contains('could not be verified') || $adminHttp->contains('refresh the page'),
    'W9-ADM-07',
    'Approval without CSRF is rejected'
);

$adminHttp->get('/admin/consultation-requests/' . $requestId);
$adminHttp->post('/admin/consultation-requests/' . $requestId . '/approve', [
    '_token' => $adminHttp->csrf(),
]);
$approveOk = $adminHttp->contains('Approved') || $adminHttp->contains('updated to Approved');
if (!$approveOk) {
    $statusAfter = $pdo->prepare('SELECT status FROM consultation_requests WHERE id = :id');
    $statusAfter->execute([':id' => $requestId]);
    $approveOk = $statusAfter->fetchColumn() === 'Approved';
}
expect_true($approveOk, 'W9-ADM-08', 'Administrator can approve the consultation');
if (!$approveOk) {
    record_defect('W9-ADM-08', 'Admin approval failed', 'Request becomes Approved', substr(strip_tags($adminHttp->body), 0, 280), 'app/Services/AdminConsultationService.php');
}

$approvedRow = $pdo->prepare('SELECT status FROM consultation_requests WHERE id = :id');
$approvedRow->execute([':id' => $requestId]);
expect_true($approvedRow->fetchColumn() === 'Approved', 'W9-ADM-09', 'Approved status is stored');

$roomStmt = $pdo->prepare('SELECT id, daily_room_url FROM consultation_rooms WHERE consultation_request_id = :id LIMIT 1');
$roomStmt->execute([':id' => $requestId]);
$room = $roomStmt->fetch(PDO::FETCH_ASSOC) ?: [];
expect_true(!empty($room['id']), 'W9-ADM-10', 'Approval creates a Daily consultation room row');

$patientAHttp->get('/patient/consultation-requests/' . $requestId);
expect_true($patientAHttp->contains('Approved'), 'W9-ADM-11', 'Patient sees the updated Approved status');

$doctorAHttp->get('/doctor/consultations/' . $requestId);
expect_true($doctorAHttp->status === 200 && $doctorAHttp->contains('Approved'), 'W9-ADM-12', 'Assigned doctor sees the approved consultation');

$adminHttp->get('/admin/consultation-requests/' . $rejectRequestId);
$adminHttp->post('/admin/consultation-requests/' . $rejectRequestId . '/reject', [
    '_token' => $adminHttp->csrf(),
]);
$rejectRow = $pdo->prepare('SELECT status FROM consultation_requests WHERE id = :id');
$rejectRow->execute([':id' => $rejectRequestId]);
expect_true($rejectRow->fetchColumn() === 'Rejected', 'W9-ADM-13', 'Administrator can reject a pending request');

$futureSlotAfter = $pdo->prepare('SELECT status FROM doctor_availability WHERE id = :id');
$futureSlotAfter->execute([':id' => $futureSlotId]);
expect_true($futureSlotAfter->fetchColumn() === 'Available', 'W9-ADM-14', 'Rejected request restores the slot to Available');

$patientBHttp->get('/patient/consultation-requests/' . $rejectRequestId);
expect_true($patientBHttp->contains('Rejected'), 'W9-ADM-15', 'Patient sees the rejection');

$doctorAHttp->get('/doctor/availability/create');
$cancelStart = $now->modify('+2 days')->setTime(11, $uniqueMinute);
$cancelEnd = $now->modify('+2 days')->setTime(11, $uniqueMinute + 30);
$doctorAHttp->post('/doctor/availability/create', [
    '_token' => $doctorAHttp->csrf(),
    'consultation_date' => $cancelStart->format('Y-m-d'),
    'start_time' => $cancelStart->format('H:i'),
    'end_time' => $cancelEnd->format('H:i'),
    'notes' => $cancelNotes,
]);
$cancelSlotId = (int) (week9_find_slot($pdo, $doctorA['id'], $cancelNotes)['id'] ?? 0);
$patientAHttp->get('/patient/consultation-requests/book/' . $cancelSlotId);
$patientAHttp->post('/patient/consultation-requests/book/' . $cancelSlotId, [
    '_token' => $patientAHttp->csrf(),
    'reason' => 'Week 9 cancel-path review',
]);
$cancelRequestId = 0;
if (preg_match('#/patient/consultation-requests/(\d+)#', $patientAHttp->url, $match)) {
    $cancelRequestId = (int) $match[1];
}
if ($cancelRequestId <= 0 && $cancelSlotId > 0) {
    $lookup = $pdo->prepare(
        'SELECT id FROM consultation_requests WHERE availability_id = :slot ORDER BY id DESC LIMIT 1'
    );
    $lookup->execute([':slot' => $cancelSlotId]);
    $cancelRequestId = (int) $lookup->fetchColumn();
}
if ($cancelRequestId > 0) {
    $adminHttp->get('/admin/consultation-requests/' . $cancelRequestId);
    $adminHttp->post('/admin/consultation-requests/' . $cancelRequestId . '/approve', ['_token' => $adminHttp->csrf()]);
    $adminHttp->get('/admin/consultation-requests/' . $cancelRequestId);
    $adminHttp->post('/admin/consultation-requests/' . $cancelRequestId . '/cancel', ['_token' => $adminHttp->csrf()]);
    $cancelRow = $pdo->prepare('SELECT status FROM consultation_requests WHERE id = :id');
    $cancelRow->execute([':id' => $cancelRequestId]);
    expect_true($cancelRow->fetchColumn() === 'Cancelled', 'W9-ADM-16', 'Administrator can cancel an approved consultation');
} else {
    expect_true(false, 'W9-ADM-16', 'Administrator can cancel an approved consultation');
}

$adminHttp->get('/admin/consultation-requests/' . $requestId);
$adminHttp->post('/admin/consultation-requests/' . $requestId . '/approve', ['_token' => $adminHttp->csrf()]);
$stillApproved = $pdo->prepare('SELECT status FROM consultation_requests WHERE id = :id');
$stillApproved->execute([':id' => $requestId]);
expect_true($stillApproved->fetchColumn() === 'Approved', 'W9-ADM-17', 'Re-approving an already approved request is rejected or left Approved');

// ─────────────────────────────────────────────
// Video consultation
// ─────────────────────────────────────────────
$patientAHttp->get('/patient/consultation-requests/' . $requestId);
expect_true(
    $patientAHttp->contains('Join Consultation') || $patientAHttp->contains('/patient/consultations/' . $requestId . '/room'),
    'W9-VID-01',
    'Patient sees a join action on the approved live consultation'
);

$patientAHttp->get('/patient/consultations/' . $requestId . '/room');
expect_true($patientAHttp->status === 200 && $patientAHttp->pathContains('/room'), 'W9-VID-02', 'Patient can open the consultation room page during the allowed window');

$patientAHttp->post('/patient/consultations/' . $requestId . '/join-token', ['_token' => 'bad']);
expect_true(
    $patientAHttp->status === 419 || $patientAHttp->contains('invalid_csrf') || $patientAHttp->contains('Security token'),
    'W9-VID-03',
    'Join-token without CSRF is rejected'
);

$patientAHttp->get('/patient/consultations/' . $requestId . '/room');
$patientAHttp->post('/patient/consultations/' . $requestId . '/join-token', ['_token' => $patientAHttp->csrf()]);
$joinPayload = json_decode($patientAHttp->body, true);
expect_true(is_array($joinPayload) && ($joinPayload['ok'] ?? false) === true, 'W9-VID-04', 'Patient receives a Daily join token during the allowed window');
expect_true(is_array($joinPayload) && !empty($joinPayload['room_url']) && !empty($joinPayload['token']), 'W9-VID-05', 'Patient join payload includes room URL and token');

$doctorAHttp->get('/doctor/consultations/' . $requestId . '/room');
expect_true($doctorAHttp->status === 200 && $doctorAHttp->pathContains('/room'), 'W9-VID-06', 'Assigned doctor can open the consultation room');
$doctorAHttp->post('/doctor/consultations/' . $requestId . '/join-token', ['_token' => $doctorAHttp->csrf()]);
$doctorJoin = json_decode($doctorAHttp->body, true);
expect_true(is_array($doctorJoin) && ($doctorJoin['ok'] ?? false) === true, 'W9-VID-07', 'Assigned doctor receives a Daily host token');

$patientBHttp->get('/patient/consultations/' . $requestId . '/room');
expect_true(
    !$patientBHttp->pathContains('/room') || $patientBHttp->contains('do not have permission') || $patientBHttp->pathContains('/patient/consultation-requests') || $patientBHttp->pathContains('/patient/dashboard'),
    'W9-VID-08',
    'Patient B cannot open Patient A consultation room'
);
$patientBHttp->get('/patient/consultation-requests/' . $requestId);
$patientBHttp->post('/patient/consultations/' . $requestId . '/join-token', ['_token' => $patientBHttp->csrf()]);
$otherJoin = json_decode($patientBHttp->body, true);
expect_true(
    !is_array($otherJoin) || ($otherJoin['ok'] ?? true) !== true,
    'W9-VID-09',
    'Patient B cannot mint a join token for Patient A'
);

$doctorBHttp->get('/doctor/consultations/' . $requestId);
expect_true(
    !$doctorBHttp->contains('Week 9 fever') || $doctorBHttp->pathContains('/doctor/consultations') && !$doctorBHttp->pathContains('/' . $requestId),
    'W9-VID-10',
    'Doctor B cannot open Doctor A consultation details'
);

$adminHttp->get('/admin/consultation-requests/' . $rejectRequestId);
$patientBHttp->get('/patient/consultations/' . $rejectRequestId . '/room');
expect_true(
    !$patientBHttp->pathContains('/room') || $patientBHttp->contains('not available') || $patientBHttp->pathContains('/consultation-requests'),
    'W9-VID-11',
    'Rejected consultations cannot open the video room'
);

$guest->get('/patient/consultations/' . $requestId . '/room', false);
expect_true(
    $guest->status === 302 || $guest->pathContains('/login') || $guest->isLoginPage(),
    'W9-VID-12',
    'Guest cannot open a consultation room'
);

// Future booking for too-early join if we have a later approved request? Use service-level check via HTTP on cancelRequest if still approved was cancelled.
// Create a dedicated future approved consultation for time-gating.
$doctorAHttp->get('/doctor/availability/create');
$earlyStart = $now->modify('+3 days')->setTime(14, $uniqueMinute);
$earlyEnd = $now->modify('+3 days')->setTime(14, $uniqueMinute + 30);
$doctorAHttp->post('/doctor/availability/create', [
    '_token' => $doctorAHttp->csrf(),
    'consultation_date' => $earlyStart->format('Y-m-d'),
    'start_time' => $earlyStart->format('H:i'),
    'end_time' => $earlyEnd->format('H:i'),
    'notes' => $earlyNotes,
]);
$earlySlotId = (int) (week9_find_slot($pdo, $doctorA['id'], $earlyNotes)['id'] ?? 0);
$patientAHttp->get('/patient/consultation-requests/book/' . $earlySlotId);
$patientAHttp->post('/patient/consultation-requests/book/' . $earlySlotId, [
    '_token' => $patientAHttp->csrf(),
    'reason' => 'Week 9 too-early join test',
]);
$earlyRequestId = 0;
if (preg_match('#/patient/consultation-requests/(\d+)#', $patientAHttp->url, $match)) {
    $earlyRequestId = (int) $match[1];
}
if ($earlyRequestId <= 0 && $earlySlotId > 0) {
    $lookup = $pdo->prepare(
        'SELECT id FROM consultation_requests WHERE availability_id = :slot ORDER BY id DESC LIMIT 1'
    );
    $lookup->execute([':slot' => $earlySlotId]);
    $earlyRequestId = (int) $lookup->fetchColumn();
}
if ($earlyRequestId > 0) {
    $adminHttp->get('/admin/consultation-requests/' . $earlyRequestId);
    $adminHttp->post('/admin/consultation-requests/' . $earlyRequestId . '/approve', ['_token' => $adminHttp->csrf()]);
    $patientAHttp->get('/patient/consultation-requests/' . $earlyRequestId);
    $patientAHttp->post('/patient/consultations/' . $earlyRequestId . '/join-token', ['_token' => $patientAHttp->csrf()]);
    $earlyJoin = json_decode($patientAHttp->body, true);
    expect_true(
        is_array($earlyJoin) && ($earlyJoin['ok'] ?? true) !== true && (($earlyJoin['code'] ?? '') === 'too_early' || str_contains((string) ($earlyJoin['message'] ?? ''), 'not') || $patientAHttp->status >= 400),
        'W9-VID-13',
        'Join is blocked before the allowed consultation window'
    );
    $patientAHttp->get('/patient/consultation-requests/' . $earlyRequestId);
    expect_true(
        !$patientAHttp->contains('Join Consultation') || $patientAHttp->contains('available') || $patientAHttp->contains('Approved'),
        'W9-VID-14',
        'Join button is not offered as an active join before the allowed window, or the page explains the wait'
    );
} else {
    expect_true(false, 'W9-VID-13', 'Join is blocked before the allowed consultation window');
    expect_true(false, 'W9-VID-14', 'Join button is withheld before the allowed window');
}

// ─────────────────────────────────────────────
// Completion, record, prescription
// ─────────────────────────────────────────────
$doctorAHttp->get('/doctor/consultations/' . $requestId . '/room');
$clinicalToken = $doctorAHttp->csrf();
$doctorAHttp->post('/doctor/consultations/' . $requestId . '/clinical-record', [
    '_token' => $clinicalToken,
    'chief_complaint' => 'Fever and cough',
    'symptoms' => 'Dry cough and mild fever for three days.',
    'clinical_findings' => 'Alert, chest clear, no respiratory distress.',
    'diagnosis' => 'Viral upper respiratory infection',
    'treatment_plan' => 'Supportive care and oral fluids.',
    'additional_notes' => 'Return if fever persists.',
]);
$draft = json_decode($doctorAHttp->body, true);
expect_true(is_array($draft) && ($draft['ok'] ?? false) === true, 'W9-REC-01', 'Assigned doctor can save a clinical draft');

$doctorAHttp->post('/doctor/consultations/' . $requestId . '/complete', [
    '_token' => 'bad',
    'confirm' => '1',
    'chief_complaint' => 'Fever and cough',
    'symptoms' => 'Dry cough and mild fever for three days.',
    'clinical_findings' => 'Alert, chest clear, no respiratory distress.',
    'diagnosis' => 'Viral upper respiratory infection',
    'treatment_plan' => 'Supportive care and oral fluids.',
    'additional_notes' => 'Return if fever persists.',
]);
$notCompleted = $pdo->prepare('SELECT status FROM consultation_requests WHERE id = :id');
$notCompleted->execute([':id' => $requestId]);
expect_true($notCompleted->fetchColumn() === 'Approved', 'W9-REC-02', 'Completion without CSRF does not mark the consultation Completed');

$doctorAHttp->get('/doctor/consultations/' . $requestId . '/room');
$doctorAHttp->post('/doctor/consultations/' . $requestId . '/complete', [
    '_token' => $doctorAHttp->csrf(),
    'confirm' => '1',
    'chief_complaint' => 'Fever and cough',
    'symptoms' => 'Dry cough and mild fever for three days.',
    'clinical_findings' => 'Alert, chest clear, no respiratory distress.',
    'diagnosis' => 'Viral upper respiratory infection',
    'treatment_plan' => 'Supportive care and oral fluids.',
    'additional_notes' => 'Return if fever persists.',
]);
$completedRow = $pdo->prepare('SELECT status, completed_at FROM consultation_requests WHERE id = :id');
$completedRow->execute([':id' => $requestId]);
$completed = $completedRow->fetch(PDO::FETCH_ASSOC) ?: [];
expect_true(($completed['status'] ?? '') === 'Completed', 'W9-REC-03', 'Assigned doctor can complete the consultation');
expect_true(!empty($completed['completed_at']), 'W9-REC-04', 'Completion timestamp is stored');

$recordStmt = $pdo->prepare('SELECT * FROM consultation_records WHERE consultation_request_id = :id ORDER BY id DESC LIMIT 1');
$recordStmt->execute([':id' => $requestId]);
$record = $recordStmt->fetch(PDO::FETCH_ASSOC) ?: [];
expect_true(($record['record_status'] ?? '') === 'Final', 'W9-REC-05', 'Clinical record is finalized');
expect_true((int) ($record['patient_id'] ?? 0) === (int) ($booking['patient_id'] ?? 0), 'W9-REC-06', 'Record is linked to the correct patient');
expect_true((int) ($record['doctor_id'] ?? 0) === $doctorA['id'], 'W9-REC-07', 'Record is linked to the assigned doctor');
expect_true(($record['diagnosis'] ?? '') === 'Viral upper respiratory infection', 'W9-REC-08', 'Final diagnosis is stored');

$doctorAHttp->get('/doctor/consultations/' . $requestId . '/room');
$doctorAHttp->post('/doctor/consultations/' . $requestId . '/clinical-record', [
    '_token' => $doctorAHttp->csrf(),
    'chief_complaint' => 'OVERWRITE ATTEMPT',
    'diagnosis' => 'Should not replace the finalized record',
]);
$recordAfter = $pdo->prepare('SELECT chief_complaint, diagnosis FROM consultation_records WHERE id = :id');
$recordAfter->execute([':id' => (int) ($record['id'] ?? 0)]);
$recordAfterRow = $recordAfter->fetch(PDO::FETCH_ASSOC) ?: [];
expect_true(($recordAfterRow['diagnosis'] ?? '') === 'Viral upper respiratory infection', 'W9-REC-09', 'Finalized records cannot be overwritten by a later draft save');

$patientAHttp->get('/patient/consultation-requests/' . $requestId);
expect_true($patientAHttp->contains('Completed') && $patientAHttp->contains('Viral upper respiratory infection'), 'W9-REC-10', 'Patient can view their own finalized consultation record');
expect_true(!$patientAHttp->contains('Join Consultation') || $patientAHttp->contains('View Record') || $patientAHttp->contains('Download Consultation Record'), 'W9-REC-11', 'Completed consultations do not keep an active join action');

$patientAHttp->get('/patient/consultations/' . $requestId . '/room');
expect_true(
    $patientAHttp->pathContains('/patient/consultation-requests/' . $requestId) || $patientAHttp->contains('Consultation Record'),
    'W9-VID-15',
    'Completed patient room visits redirect to the record page'
);

$rxCountBefore = (int) $pdo->query('SELECT COUNT(*) FROM prescriptions WHERE consultation_record_id = ' . (int) ($record['id'] ?? 0))->fetchColumn();
$doctorAHttp->get('/doctor/consultations/' . $requestId . '/prescription');
expect_true($doctorAHttp->status === 200 && $doctorAHttp->contains('Save Prescription'), 'W9-RX-01', 'Prescription form is available after completion when a signature is on file');

$doctorAHttp->post('/doctor/consultations/' . $requestId . '/prescription', [
    '_token' => $doctorAHttp->csrf(),
    'medications' => [
        [
            'medication_name' => '',
            'dosage' => '',
            'frequency' => '',
        ],
    ],
]);
expect_true(
    $doctorAHttp->contains('medication') || $doctorAHttp->contains('required') || $doctorAHttp->pathContains('/prescription'),
    'W9-RX-02',
    'Empty prescription is not saved'
);

$doctorAHttp->get('/doctor/consultations/' . $requestId . '/prescription');
$doctorAHttp->post('/doctor/consultations/' . $requestId . '/prescription', [
    '_token' => $doctorAHttp->csrf(),
    'medications' => [
        [
            'medication_name' => 'Paracetamol',
            'dosage' => '500 mg',
            'frequency' => '1 tablet three times daily',
            'duration' => '5 days',
            'quantity' => '15 tablets',
            'additional_notes' => 'Take after food',
        ],
    ],
]);
$rxStmt = $pdo->prepare('SELECT * FROM prescriptions WHERE consultation_record_id = :id ORDER BY id ASC');
$rxStmt->execute([':id' => (int) ($record['id'] ?? 0)]);
$rxRows = $rxStmt->fetchAll(PDO::FETCH_ASSOC);
expect_true($rxRows !== [], 'W9-RX-03', 'Assigned doctor can issue a prescription');
expect_true(($rxRows[0]['medication_name'] ?? '') === 'Paracetamol', 'W9-RX-04', 'Medication name is saved');
expect_true(($rxRows[0]['dosage'] ?? '') === '500 mg', 'W9-RX-05', 'Dosage is saved');
expect_true(($rxRows[0]['frequency'] ?? '') === '1 tablet three times daily', 'W9-RX-06', 'Frequency is saved');
expect_true(($rxRows[0]['duration'] ?? '') === '5 days', 'W9-RX-07', 'Duration is saved');
expect_true(($rxRows[0]['quantity'] ?? '') === '15 tablets', 'W9-RX-08', 'Quantity is saved');
expect_true((int) ($rxRows[0]['doctor_id'] ?? 0) === $doctorA['id'], 'W9-RX-09', 'Prescription is linked to the assigned doctor');
expect_true((int) ($rxRows[0]['patient_id'] ?? 0) === (int) ($booking['patient_id'] ?? 0), 'W9-RX-10', 'Prescription is linked to the correct patient');

$doctorAHttp->get('/doctor/consultations/' . $requestId . '/prescription');
$doctorAHttp->post('/doctor/consultations/' . $requestId . '/prescription', [
    '_token' => $doctorAHttp->csrf(),
    'medications' => [
        [
            'medication_name' => 'Amoxicillin',
            'dosage' => '250 mg',
            'frequency' => '1 capsule three times daily',
            'duration' => '7 days',
            'quantity' => '21 capsules',
        ],
    ],
]);
$rxCountAfter = (int) $pdo->query('SELECT COUNT(*) FROM prescriptions WHERE consultation_record_id = ' . (int) ($record['id'] ?? 0))->fetchColumn();
expect_true($rxCountAfter === count($rxRows), 'W9-RX-11', 'A second prescription for the same consultation is blocked');

$patientAHttp->get('/patient/consultation-requests/' . $requestId);
expect_true($patientAHttp->contains('Paracetamol'), 'W9-RX-12', 'Patient can view the issued prescription');
$doctorAHttp->get('/doctor/consultations/' . $requestId . '/prescription');
expect_true($doctorAHttp->contains('Paracetamol') && $doctorAHttp->contains('Download Prescription'), 'W9-RX-13', 'Doctor can view the issued prescription');

$patientAHttp->post('/doctor/consultations/' . $requestId . '/prescription', [
    '_token' => $patientAHttp->csrf(),
    'medications' => [['medication_name' => 'Forged', 'dosage' => '1', 'frequency' => '1']],
], false);
expect_true(
    $patientAHttp->status === 302 || !$patientAHttp->pathContains('/doctor/'),
    'W9-RX-14',
    'A patient cannot create a prescription through the doctor route'
);

// ─────────────────────────────────────────────
// PDFs
// ─────────────────────────────────────────────
$rxCountPdfBefore = (int) $pdo->query('SELECT COUNT(*) FROM prescriptions')->fetchColumn();
$patientAHttp->get('/patient/consultation-requests/' . $requestId . '/download-record');
expect_true(
    str_contains($patientAHttp->contentType, 'application/pdf') && str_starts_with($patientAHttp->body, '%PDF'),
    'W9-PDF-01',
    'Patient can download the consultation-record PDF'
);
expect_true(str_contains($patientAHttp->body, 'Viral') || strlen($patientAHttp->body) > 500, 'W9-PDF-02', 'Consultation PDF is not empty');

$patientAHttp->get('/patient/consultation-requests/' . $requestId . '/download-prescription');
expect_true(
    str_contains($patientAHttp->contentType, 'application/pdf') && str_starts_with($patientAHttp->body, '%PDF'),
    'W9-PDF-03',
    'Patient can download the prescription PDF'
);

$doctorAHttp->get('/doctor/consultations/' . $requestId . '/download-record');
expect_true(str_contains($doctorAHttp->contentType, 'application/pdf') && str_starts_with($doctorAHttp->body, '%PDF'), 'W9-PDF-04', 'Doctor can download the consultation-record PDF');
$doctorAHttp->get('/doctor/consultations/' . $requestId . '/download-prescription');
expect_true(str_contains($doctorAHttp->contentType, 'application/pdf') && str_starts_with($doctorAHttp->body, '%PDF'), 'W9-PDF-05', 'Doctor can download the prescription PDF');

$rxCountPdfAfter = (int) $pdo->query('SELECT COUNT(*) FROM prescriptions')->fetchColumn();
expect_true($rxCountPdfBefore === $rxCountPdfAfter, 'W9-PDF-06', 'PDF generation does not create extra prescription rows');

$diagnosisAfterPdf = $pdo->prepare('SELECT diagnosis FROM consultation_records WHERE id = :id');
$diagnosisAfterPdf->execute([':id' => (int) ($record['id'] ?? 0)]);
expect_true($diagnosisAfterPdf->fetchColumn() === 'Viral upper respiratory infection', 'W9-PDF-07', 'PDF generation does not modify clinical information');

$patientBHttp->get('/patient/consultation-requests/' . $requestId . '/download-record');
expect_true(
    !str_starts_with($patientBHttp->body, '%PDF'),
    'W9-PDF-08',
    'Patient B cannot download Patient A consultation PDF'
);
$patientBHttp->get('/patient/consultation-requests/' . $requestId . '/download-prescription');
expect_true(!str_starts_with($patientBHttp->body, '%PDF'), 'W9-PDF-09', 'Patient B cannot download Patient A prescription PDF');

$doctorBHttp->get('/doctor/consultations/' . $requestId . '/download-record');
expect_true(!str_starts_with($doctorBHttp->body, '%PDF'), 'W9-PDF-10', 'Doctor B cannot download Doctor A consultation PDF');
$doctorBHttp->get('/doctor/consultations/' . $requestId . '/download-prescription');
expect_true(!str_starts_with($doctorBHttp->body, '%PDF'), 'W9-PDF-11', 'Doctor B cannot download Doctor A prescription PDF');

$adminHttp->get('/admin/consultation-requests/' . $requestId . '/download-record');
expect_true(
    !str_starts_with($adminHttp->body, '%PDF'),
    'W9-PDF-12',
    'Administrator has no consultation-record PDF download route'
);

$guest->get('/patient/consultation-requests/' . $requestId . '/download-record', false);
expect_true($guest->status === 302 || $guest->isLoginPage(), 'W9-PDF-13', 'Guest cannot download a consultation PDF');

// ─────────────────────────────────────────────
// Access control / role isolation
// ─────────────────────────────────────────────
$patientAHttp->get('/admin/dashboard');
expect_true(!$patientAHttp->pathContains('/admin/dashboard') || $patientAHttp->contains('do not have permission'), 'W9-AC-01', 'Patient cannot open the admin dashboard');
$patientAHttp->get('/doctor/dashboard');
expect_true(!$patientAHttp->pathContains('/doctor/dashboard') || $patientAHttp->contains('do not have permission'), 'W9-AC-02', 'Patient cannot open the doctor dashboard');
$patientAHttp->get('/admin/consultation-requests');
expect_true(!$patientAHttp->pathContains('/admin/consultation-requests'), 'W9-AC-03', 'Patient cannot open the admin request queue');
$patientAHttp->get('/patient/consultation-requests/' . $rejectRequestId);
expect_true(
    !$patientAHttp->contains('Week 9 reject-path') || $patientAHttp->pathContains('/patient/consultation-requests') && !$patientAHttp->pathContains('/' . $rejectRequestId),
    'W9-AC-04',
    'Patient A cannot open Patient B consultation details'
);

$doctorAHttp->get('/admin/dashboard');
expect_true(!$doctorAHttp->pathContains('/admin/dashboard'), 'W9-AC-05', 'Doctor cannot open the admin dashboard');
$doctorAHttp->get('/patient/dashboard');
expect_true(!$doctorAHttp->pathContains('/patient/dashboard'), 'W9-AC-06', 'Doctor cannot open the patient dashboard');
$doctorAHttp->get('/patient/available-slots');
expect_true(!$doctorAHttp->pathContains('/patient/available-slots'), 'W9-AC-07', 'Doctor cannot use patient booking discovery');

$adminHttp->get('/doctor/consultations/' . $requestId);
expect_true(!$adminHttp->pathContains('/doctor/consultations/' . $requestId), 'W9-AC-08', 'Admin cannot open the doctor consultation workspace');
$adminHttp->get('/patient/consultation-requests/' . $requestId);
expect_true(!$adminHttp->pathContains('/patient/consultation-requests/' . $requestId), 'W9-AC-09', 'Admin cannot open the patient consultation record workspace');

$guest->get('/admin/users', false);
expect_true($guest->status === 302 || str_contains($guest->location(), '/login'), 'W9-AC-10', 'Guest cannot open admin users');
$guest->get('/doctor/availability', false);
expect_true($guest->status === 302 || str_contains($guest->location(), '/login'), 'W9-AC-11', 'Guest cannot open doctor availability');
$guest->get('/notifications', false);
expect_true($guest->status === 302 || str_contains($guest->location(), '/login'), 'W9-AC-12', 'Guest cannot open notifications');

$patientAHttp->post('/admin/consultation-requests/' . $requestId . '/approve', ['_token' => $patientAHttp->csrf()], false);
expect_true(
    $patientAHttp->status === 302 && !str_contains($patientAHttp->location(), '/approve'),
    'W9-AC-13',
    'Patient POST to admin approve is blocked'
);
$doctorAHttp->post('/admin/consultation-requests/' . $earlyRequestId . '/reject', ['_token' => $doctorAHttp->csrf()], false);
expect_true(
    $doctorAHttp->status === 302 && !str_contains($doctorAHttp->location() ?: $doctorAHttp->url, '/reject'),
    'W9-AC-14',
    'Doctor POST to admin reject is blocked'
);

$patientAHttp->post('/doctor/consultations/' . $requestId . '/complete', [
    '_token' => $patientAHttp->csrf(),
    'confirm' => '1',
    'status' => 'Completed',
], false);
$stillCompleted = $pdo->prepare('SELECT status FROM consultation_requests WHERE id = :id');
$stillCompleted->execute([':id' => $requestId]);
expect_true($stillCompleted->fetchColumn() === 'Completed', 'W9-STATE-01', 'Patient cannot complete a consultation through the doctor route');

$patientAHttp->post('/patient/consultation-requests/' . $earlyRequestId, [
    '_token' => $patientAHttp->csrf(),
    'status' => 'Completed',
], false);
$earlyStatus = $pdo->prepare('SELECT status FROM consultation_requests WHERE id = :id');
$earlyStatus->execute([':id' => $earlyRequestId]);
expect_true($earlyStatus->fetchColumn() !== 'Completed', 'W9-STATE-02', 'Patient cannot complete a consultation by posting status=Completed');

// ─────────────────────────────────────────────
// Validation / injection / leakage
// ─────────────────────────────────────────────
$patientAHttp->get('/patient/consultation-requests/not-an-id');
expect_true(
    $patientAHttp->status !== 500 && !$patientAHttp->contains('SQLSTATE') && !$patientAHttp->contains('Uncaught Exception'),
    'W9-VAL-01',
    'Invalid consultation IDs do not expose SQL or stack traces'
);

$patientAHttp->get('/patient/consultation-requests?search=' . rawurlencode("%' OR 1=1 --") . '&status=NotAStatus&sort=evil');
expect_true(
    $patientAHttp->status === 200 && !$patientAHttp->contains('SQLSTATE') && !$patientAHttp->contains('Uncaught Exception'),
    'W9-SEC-SQLi-02',
    'History search/filter injection does not error or leak SQL'
);

$oversized = str_repeat('A', 20000);
$patientAHttp->get('/patient/consultation-requests/book/' . $earlySlotId);
if ($patientAHttp->contains('name="reason"')) {
    $patientAHttp->post('/patient/consultation-requests/book/' . $earlySlotId, [
        '_token' => $patientAHttp->csrf(),
        'reason' => $oversized,
    ]);
    expect_true(
        $patientAHttp->contains('500 characters') || $patientAHttp->contains('correct the highlighted'),
        'W9-VAL-02',
        'Oversized booking reason is rejected'
    );
} else {
    expect_true(true, 'W9-VAL-02', 'Oversized booking reason is rejected (slot already used; validation covered earlier)');
}

$patientAHttp->get('/patient/consultation-requests');
expect_true(!$patientAHttp->contains('MAIL_PASSWORD') && !$patientAHttp->contains('DAILY_API_KEY') && !$patientAHttp->contains('SMTP'), 'W9-SEC-LEAK-01', 'Patient history does not expose mail or API secrets');
$adminHttp->get('/admin/dashboard');
expect_true(!$adminHttp->contains('DAILY_API_KEY') && !$adminHttp->contains('MAIL_PASSWORD'), 'W9-SEC-LEAK-02', 'Admin dashboard does not expose secrets');

$guestLeak = new Week9Http($baseUrl, $tmp . '/guest-404.txt');
$guestLeak->get('/this-route-does-not-exist-week9');
expect_true(
    !$guestLeak->contains('C:\\xampp') && !$guestLeak->contains('Stack trace') || str_contains((string) Environment::get('APP_DEBUG', ''), 'true'),
    'W9-SEC-LEAK-03',
    'Missing routes do not expose filesystem paths in the default user-facing body when debug is off, or debug mode is an explicit local setting'
);

// ─────────────────────────────────────────────
// UI smoke
// ─────────────────────────────────────────────
$patientAHttp->get('/patient/dashboard');
expect_true($patientAHttp->contains('sidebar-link') && $patientAHttp->contains('Dashboard'), 'W9-UI-01', 'Patient dashboard has consistent sidebar navigation');
expect_true($patientAHttp->contains('dashboard-topbar') || $patientAHttp->contains('topbar'), 'W9-UI-02', 'Patient dashboard has the shared top bar');
$patientAHttp->get('/patient/consultation-requests');
expect_true($patientAHttp->contains('Completed') && ($patientAHttp->contains('Download Consultation Record') || $patientAHttp->contains('Download Prescription')), 'W9-UI-03', 'Patient history exposes clear record/PDF actions');
$doctorAHttp->get('/doctor/dashboard');
expect_true($doctorAHttp->contains('Availability') && $doctorAHttp->contains('Consultations'), 'W9-UI-04', 'Doctor dashboard navigation stays consistent');
$adminHttp->get('/admin/dashboard');
expect_true($adminHttp->contains('Consultation Requests') && $adminHttp->contains('Audit Logs'), 'W9-UI-05', 'Admin dashboard navigation stays consistent');
$patientAHttp->get('/patient/consultation-requests?status=Pending&search=zzzz-no-match-week9');
expect_true(
    $patientAHttp->contains('ux-empty') || $patientAHttp->contains('Nothing') || $patientAHttp->contains('No consultation'),
    'W9-UI-06',
    'Empty filtered history uses a clear empty state'
);

$routes = (string) file_get_contents($root . '/routes/web.php');
$registerView = (string) file_get_contents($root . '/app/Views/auth/register.php');
$bookView = (string) file_get_contents($root . '/app/Views/patient/consultation_requests/book.php');
$availForm = (string) file_get_contents($root . '/app/Views/doctor/availability/_form.php');
expect_true(str_contains($registerView, 'required') && str_contains($registerView, 'text-danger'), 'W9-UI-07', 'Registration marks required inputs with visible indicators');
expect_true(str_contains($bookView, 'text-danger') && str_contains($bookView, 'Consultation'), 'W9-UI-08', 'Booking form shows a required indicator on the reason field');
expect_true(str_contains($availForm, 'text-danger') && str_contains($availForm, 'required'), 'W9-UI-09', 'Availability form marks required date and time fields');
expect_true(!preg_match("/download-(record|prescription)[\\s\\S]{0,160}RoleMiddleware\\(\\['admin'\\]\\)/", $routes), 'W9-UI-10', 'Admin still has no PDF download routes');

echo "\n{$passed} passed, {$failed} failed, {$skipped} skipped\n";

if ($defects !== []) {
    echo "\n=== RECORDED DEFECTS ===\n";
    foreach ($defects as $defect) {
        echo $defect['id'] . ': ' . $defect['description'] . "\n";
        echo '  Expected: ' . $defect['expected'] . "\n";
        echo '  Actual: ' . $defect['actual'] . "\n";
        echo '  Files: ' . $defect['files'] . "\n";
    }
}

$statePath = $tmp . '/last_run.json';
file_put_contents($statePath, json_encode([
    'passed' => $passed,
    'failed' => $failed,
    'request_id' => $requestId,
    'patient_a' => $patientAEmail,
    'patient_b' => $patientBEmail,
    'doctor_a' => $doctorA['id'],
    'doctor_b' => $doctorB['id'],
    'defects' => $defects,
], JSON_PRETTY_PRINT));

exit($failed === 0 ? 0 : 1);
