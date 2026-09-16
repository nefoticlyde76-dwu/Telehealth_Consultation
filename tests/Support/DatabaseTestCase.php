<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\ConsultationRecord;
use App\Models\DoctorAvailability;
use App\Models\Prescription;
use App\Models\User;
use App\Services\AuthService;
use App\Services\GoogleAuthService;
use App\Services\LoginAttemptService;
use App\Services\MailService;
use App\Services\NotificationService;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Shared MySQL fixture helper used by authorization, auth, and booking tests.
 * Creates uniquely named users and deletes them after each test.
 */
abstract class DatabaseTestCase extends TestCase
{
    public const TEST_PASSWORD = 'TestPass123!';

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $requestIds = [];

    /** @var list<int> */
    private array $recordIds = [];

    /** @var list<int> */
    private array $slotIds = [];

    /** @var list<string> */
    private array $emails = [];

    private string $suffix = '';

    private int $emailSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suffix = 'phpunit' . date('His') . bin2hex(random_bytes(3));
        $this->emailSeq = 0;
        $this->userIds = [];
        $this->requestIds = [];
        $this->recordIds = [];
        $this->slotIds = [];
        $this->emails = [];

        Helper::$exitOnRedirect = false;
        Helper::$lastRedirect = null;
        LoginAttemptService::resetTestState();
        NotificationService::resetTestState();
        MailService::resetTestState();
        LoginAttemptService::$testSkipDelay = true;
        GoogleAuthService::$testJwksOverride = null;
        AuthService::$testFailGooglePatientInsert = false;

        $this->forgetLogin();
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/';
    }

    protected function tearDown(): void
    {
        $this->forgetLogin();
        $this->cleanupFixtures();

        Helper::$exitOnRedirect = true;
        Helper::$lastRedirect = null;
        LoginAttemptService::resetTestState();
        GoogleAuthService::$testJwksOverride = null;
        AuthService::$testFailGooglePatientInsert = false;
        $_POST = [];
        $_FILES = [];

        parent::tearDown();
    }

    protected function db(): PDO
    {
        return Database::getInstance();
    }

    protected function uniqueSuffix(): string
    {
        return $this->suffix;
    }

    protected function uniqueEmail(string $label): string
    {
        $this->emailSeq++;
        $email = $label . $this->emailSeq . '+' . $this->suffix . '@telehealth.test';
        $this->emails[] = $email;

        return $email;
    }

    protected function roleId(string $role): int
    {
        $id = User::findRoleIdByName($role);
        $this->assertNotNull($id, "Role {$role} must exist");

        return (int) $id;
    }

    protected function trackUser(int $userId): void
    {
        if ($userId > 0) {
            $this->userIds[] = $userId;
        }
    }

    protected function trackRequest(int $requestId): void
    {
        if ($requestId > 0) {
            $this->requestIds[] = $requestId;
        }
    }

    protected function trackRecord(int $recordId): void
    {
        if ($recordId > 0) {
            $this->recordIds[] = $recordId;
        }
    }

    protected function createUser(string $role, string $name, ?string $email = null): int
    {
        $email ??= $this->uniqueEmail($role);
        $this->emails[] = $email;
        $stmt = $this->db()->prepare(
            "INSERT INTO users (role_id, full_name, email, password, status)
             VALUES (:role_id, :full_name, :email, :password, 'active')"
        );
        $stmt->execute([
            ':role_id' => $this->roleId($role),
            ':full_name' => $name,
            ':email' => $email,
            ':password' => password_hash(self::TEST_PASSWORD, PASSWORD_DEFAULT),
        ]);
        $userId = (int) $this->db()->lastInsertId();
        $this->trackUser($userId);

        return $userId;
    }

    protected function createDoctor(string $name = 'PHPUnit Doctor', string $specialization = 'General Practice'): int
    {
        $userId = $this->createUser('doctor', $name);
        $this->db()->prepare(
            "INSERT INTO doctor (user_id, professional_title, specialization)
             VALUES (:id, 'Medical Officer', :specialization)"
        )->execute([
            ':id' => $userId,
            ':specialization' => $specialization,
        ]);

        return $userId;
    }

    protected function createPatient(string $name = 'PHPUnit Patient'): int
    {
        $userId = $this->createUser('patient', $name);
        $this->db()->prepare('INSERT INTO patient (user_id) VALUES (:id)')->execute([':id' => $userId]);

        return $userId;
    }

    protected function createApprovedRequest(int $patientId, int $doctorId, string $reason): int
    {
        $stmt = $this->db()->prepare(
            "INSERT INTO consultation_requests (patient_id, doctor_id, reason, status)
             VALUES (:patient_id, :doctor_id, :reason, 'Approved')"
        );
        $stmt->execute([
            ':patient_id' => $patientId,
            ':doctor_id' => $doctorId,
            ':reason' => $reason,
        ]);
        $requestId = (int) $this->db()->lastInsertId();
        $this->trackRequest($requestId);

        return $requestId;
    }

    protected function createAvailability(int $doctorId, string $start = '09:00:00', string $end = '09:30:00', ?string $date = null): int
    {
        $slot = new DoctorAvailability();
        $slot->doctor_id = $doctorId;
        $slot->consultation_date = $date ?? (new DateTimeImmutable('+5 days'))->format('Y-m-d');
        $slot->start_time = $start;
        $slot->end_time = $end;
        $slot->notes = 'PHPUnit booking slot';
        $slot->status = 'Available';
        $slot->save();
        $id = (int) ($slot->id ?? 0);
        $this->slotIds[] = $id;

        return $id;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    protected function finalizeConsultation(int $requestId, int $doctorId, int $patientId, array $fields): array
    {
        $result = ConsultationRecord::completeConsultationForDoctor(
            $requestId,
            $doctorId,
            $patientId,
            date('Y-m-d H:i:s'),
            $fields
        );
        $recordId = (int) ($result['record']['id'] ?? 0);
        $this->trackRecord($recordId);

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function ensureDraft(int $requestId, int $doctorId, int $patientId, string $chiefComplaint): ?array
    {
        $draft = ConsultationRecord::ensureDraftForDoctor(
            $requestId,
            $doctorId,
            $patientId,
            date('Y-m-d H:i:s'),
            $chiefComplaint
        );
        if (is_array($draft)) {
            $this->trackRecord((int) ($draft['id'] ?? 0));
        }

        return $draft;
    }

    /**
     * @param list<array<string, string>> $medications
     */
    protected function issuePrescription(int $recordId, int $doctorId, int $patientId, array $medications): void
    {
        $result = Prescription::createForCompletedConsultation($recordId, $doctorId, $patientId, $medications);
        $this->assertTrue(($result['success'] ?? false) === true, 'Test fixture prescription should save');
    }

    protected function loginAs(int $userId, string $role): void
    {
        Session::set('user_id', $userId);
        Session::set('user_role', $role);
    }

    protected function forgetLogin(): void
    {
        Session::remove('user_id');
        Session::remove('user_role');
    }

    protected function csrfToken(): string
    {
        return Csrf::generate();
    }

    /**
     * @param callable():void $callback
     */
    protected function captureOutput(callable $callback): string
    {
        Helper::$lastRedirect = null;
        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    private function cleanupFixtures(): void
    {
        $db = $this->db();

        foreach (array_unique($this->recordIds) as $recordId) {
            if ($recordId <= 0) {
                continue;
            }
            $db->prepare('DELETE FROM prescriptions WHERE consultation_record_id = :id')->execute([':id' => $recordId]);
            $db->prepare("DELETE FROM audit_logs WHERE entity_type = 'consultation_record' AND entity_id = :id")->execute([':id' => $recordId]);
            $db->prepare('DELETE FROM consultation_records WHERE id = :id')->execute([':id' => $recordId]);
        }

        foreach (array_unique($this->requestIds) as $requestId) {
            if ($requestId <= 0) {
                continue;
            }
            $db->prepare('DELETE FROM complaint_images WHERE request_id = :id')->execute([':id' => $requestId]);
            try {
                $db->prepare('DELETE FROM consultation_rooms WHERE consultation_request_id = :id')->execute([':id' => $requestId]);
            } catch (\Throwable) {
            }
            $db->prepare('DELETE FROM notifications WHERE related_entity_id = :id')->execute([':id' => $requestId]);
            $db->prepare("DELETE FROM audit_logs WHERE entity_type = 'consultation_request' AND entity_id = :id")->execute([':id' => $requestId]);
            $db->prepare('DELETE FROM consultation_requests WHERE id = :id')->execute([':id' => $requestId]);
        }

        foreach (array_unique($this->slotIds) as $slotId) {
            if ($slotId > 0) {
                $db->prepare('DELETE FROM doctor_availability WHERE id = :id')->execute([':id' => $slotId]);
            }
        }

        foreach (array_unique($this->emails) as $email) {
            $db->prepare('DELETE FROM login_attempts WHERE email = :email')->execute([':email' => strtolower($email)]);
        }

        foreach (array_unique($this->userIds) as $userId) {
            $db->prepare('DELETE FROM user_sessions WHERE user_id = :id')->execute([':id' => $userId]);
            $db->prepare('DELETE FROM notifications WHERE user_id = :id')->execute([':id' => $userId]);
            $db->prepare('DELETE FROM notification_preferences WHERE user_id = :id')->execute([':id' => $userId]);
            $db->prepare('DELETE FROM login_attempts WHERE email IN (SELECT email FROM users WHERE id = :id)')->execute([':id' => $userId]);
            $db->prepare('DELETE FROM patient WHERE user_id = :id')->execute([':id' => $userId]);
            $db->prepare('DELETE FROM doctor WHERE user_id = :id')->execute([':id' => $userId]);
            $db->prepare('DELETE FROM admin WHERE user_id = :id')->execute([':id' => $userId]);
            $db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
        }
    }
}
