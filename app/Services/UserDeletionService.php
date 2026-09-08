<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\Helper;
use App\Helpers\Status;
use App\Models\AuditLog;
use App\Models\User;
use PDO;

/**
 * Permanent account removal without cascade-deleting clinical or audit history.
 *
 * Strategy: keep the users row (and patient/doctor stubs) so consultation
 * records, prescriptions, and appointments retain valid foreign keys.
 * Personally identifiable account fields are anonymized. Open bookings are
 * cancelled. User-facing notifications and sessions are removed. Audit logs
 * are written and never deleted.
 */
class UserDeletionService
{
    public const MAX_BULK = 20;

    /**
     * @return array{success:bool,message:string,type:string}
     */
    public static function permanentlyDelete(
        int $targetUserId,
        int $actorUserId,
        string $csrfToken,
        string $confirmationPhrase,
        string $actorPassword
    ): array {
        $authorized = self::authorizeAdministratorDeletion($actorUserId, $csrfToken, $confirmationPhrase, $actorPassword);
        if (!($authorized['ok'] ?? false)) {
            return self::fail((string) ($authorized['message'] ?? 'Deletion was not authorized.'), (string) ($authorized['type'] ?? 'danger'));
        }

        return self::deleteVerifiedAccount($targetUserId, $authorized['actor']);
    }

    /**
     * @param list<mixed> $targetUserIds
     * @return array{success:bool,message:string,type:string,deleted:int,skipped:int}
     */
    public static function permanentlyDeleteMany(
        array $targetUserIds,
        int $actorUserId,
        string $csrfToken,
        string $confirmationPhrase,
        string $actorPassword
    ): array {
        $authorized = self::authorizeAdministratorDeletion($actorUserId, $csrfToken, $confirmationPhrase, $actorPassword);
        if (!($authorized['ok'] ?? false)) {
            return [
                'success' => false,
                'message' => (string) ($authorized['message'] ?? 'Deletion was not authorized.'),
                'type' => (string) ($authorized['type'] ?? 'danger'),
                'deleted' => 0,
                'skipped' => 0,
            ];
        }

        $ids = [];
        foreach ($targetUserIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_slice(array_values($ids), 0, self::MAX_BULK);

        if ($ids === []) {
            return [
                'success' => false,
                'message' => 'Select at least one user to delete permanently.',
                'type' => 'warning',
                'deleted' => 0,
                'skipped' => 0,
            ];
        }

        $deleted = 0;
        $skipped = [];
        foreach ($ids as $id) {
            $result = self::deleteVerifiedAccount($id, $authorized['actor']);
            if ($result['success'] ?? false) {
                $deleted++;
                continue;
            }
            $skipped[] = $result['message'] ?? 'One selected account could not be deleted.';
        }

        if ($deleted === 0) {
            return [
                'success' => false,
                'message' => $skipped[0] ?? 'None of the selected accounts could be deleted.',
                'type' => 'warning',
                'deleted' => 0,
                'skipped' => count($skipped),
            ];
        }

        $message = $deleted === 1
            ? '1 user account was permanently deleted. Protected clinical and audit records were retained.'
            : $deleted . ' user accounts were permanently deleted. Protected clinical and audit records were retained.';
        if ($skipped !== []) {
            $message .= ' ' . count($skipped) . ' selected account(s) were skipped.';
        }

        return [
            'success' => true,
            'message' => $message,
            'type' => $skipped === [] ? 'success' : 'warning',
            'deleted' => $deleted,
            'skipped' => count($skipped),
        ];
    }

    /**
     * @return array{ok:true,actor:\App\Models\User}|array{ok:false,message:string,type:string}
     */
    private static function authorizeAdministratorDeletion(
        int $actorUserId,
        string $csrfToken,
        string $confirmationPhrase,
        string $actorPassword
    ): array {
        if (!\App\Core\Csrf::verify($csrfToken)) {
            return ['ok' => false, 'message' => 'Unable to verify the request. Please refresh the page and try again.', 'type' => 'danger'];
        }

        if (!AccountSecurityService::confirmationPhraseIsValid($confirmationPhrase)) {
            return ['ok' => false, 'message' => 'Type DELETE USER exactly to confirm permanent deletion.', 'type' => 'warning'];
        }

        if (!AccountSecurityService::verifyCurrentPassword($actorUserId, $actorPassword)) {
            return ['ok' => false, 'message' => 'Administrator password confirmation failed. The account was not deleted.', 'type' => 'danger'];
        }

        $actorUser = User::findById($actorUserId);
        if ($actorUser === null || $actorUser->getRole() !== 'admin') {
            return ['ok' => false, 'message' => 'The administrator account performing this action could not be verified.', 'type' => 'danger'];
        }

        return ['ok' => true, 'actor' => $actorUser];
    }

    /**
     * @return array{success:bool,message:string,type:string}
     */
    private static function deleteVerifiedAccount(int $targetUserId, User $actorUser): array
    {
        $actorUserId = (int) $actorUser->id;
        $targetUser = User::findDeletionContextById($targetUserId);

        if ($targetUser === null) {
            return self::fail('The selected user account could not be found.', 'warning');
        }

        if ($targetUserId === $actorUserId) {
            return self::fail('Administrators cannot permanently delete their own account.', 'warning');
        }

        $targetRole = (string) ($targetUser['role_name'] ?? '');
        $targetStatus = Status::normalizeKey(Status::DOMAIN_USER, (string) ($targetUser['status'] ?? ''));

        if ($targetStatus === Status::USER_DELETED) {
            return self::fail('This account has already been permanently deleted.', 'info');
        }

        if ($targetRole === 'admin' && User::countActiveAdministrators() <= 1 && $targetStatus === Status::USER_ACTIVE) {
            return self::fail('The final active administrator account cannot be deleted.', 'warning');
        }

        if ($targetRole === 'admin' && User::countAdministrators() <= 1) {
            return self::fail('The final administrator account cannot be deleted.', 'warning');
        }

        $db = Database::getInstance();
        $assetPaths = self::collectAssetPaths($targetUser);
        $originalName = (string) ($targetUser['full_name'] ?? 'Unknown User');
        $protection = self::countProtectedRecords($db, $targetUserId, $targetRole);

        try {
            $db->beginTransaction();

            self::cancelOpenConsultations($db, $targetUserId, $targetRole);
            self::releaseUnusedDoctorSlots($db, $targetUserId, $targetRole);
            self::deleteUserFacingRecords($db, $targetUserId);
            self::anonymizeRoleProfile($db, $targetUserId, $targetRole);
            self::anonymizeUserRow($db, $targetUserId);

            $description = sprintf(
                '%s permanently deleted %s account #%d. Open bookings were cancelled. Clinical records preserved: %d consultation record(s), %d prescription(s), %d completed request(s). Audit history retained.',
                (string) ($actorUser->full_name ?? 'Administrator'),
                $targetRole !== '' ? $targetRole : 'user',
                $targetUserId,
                $protection['consultation_records'],
                $protection['prescriptions'],
                $protection['completed_requests']
            );

            $ip = Helper::clientIp();
            if ($ip !== '') {
                $description .= ' Request IP: ' . $ip . '.';
            }

            $meta = AuditLogService::catalog()['user_deleted'] ?? [
                'label' => 'User Account Deleted',
                'category' => 'administration',
                'severity' => 'warning',
            ];

            AuditLog::create([
                'actor_user_id' => $actorUserId,
                'actor_name' => (string) ($actorUser->full_name ?? 'Administrator'),
                'actor_role' => 'admin',
                'action' => 'user_deleted',
                'event_type' => 'user_deleted',
                'event_label' => $meta['label'],
                'event_category' => $meta['category'],
                'severity' => $meta['severity'],
                'subject_name' => $originalName,
                'subject_role' => $targetRole,
                'entity_type' => AuditLogService::ENTITY_USER,
                'entity_id' => $targetUserId,
                'description' => $description,
                'outcome' => 'success',
            ]);

            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('[UserDeletionService] ' . $exception->getMessage());

            return self::fail('The user account could not be deleted right now. No changes were saved.', 'danger');
        }

        SessionService::revokeAll($targetUserId);
        self::deleteStoredAssets($assetPaths);

        return [
            'success' => true,
            'message' => ucfirst($targetRole) . ' account for ' . $originalName . ' was permanently deleted. Protected clinical and audit records were retained.',
            'type' => 'success',
        ];
    }

    /**
     * @return array{consultation_records:int,prescriptions:int,completed_requests:int,open_requests:int}
     */
    public static function countProtectedRecords(PDO $db, int $userId, string $role): array
    {
        $counts = [
            'consultation_records' => 0,
            'prescriptions' => 0,
            'completed_requests' => 0,
            'open_requests' => 0,
        ];

        if ($role === 'patient' || $role === 'doctor') {
            $column = $role === 'patient' ? 'patient_id' : 'doctor_id';
            $counts['consultation_records'] = self::countWhere($db, 'consultation_records', $column, $userId);
            $counts['prescriptions'] = self::countWhere($db, 'prescriptions', $column, $userId);
            $counts['completed_requests'] = self::countWhereStatus($db, 'consultation_requests', $column, $userId, Status::COMPLETED);
            $counts['open_requests'] = self::countOpenRequests($db, $column, $userId);
        }

        return $counts;
    }

    /**
     * @return array{success:bool,message:string,type:string}
     */
    private static function fail(string $message, string $type): array
    {
        return [
            'success' => false,
            'message' => $message,
            'type' => $type,
        ];
    }

    /**
     * @param array<string, mixed> $targetUser
     * @return list<string>
     */
    private static function collectAssetPaths(array $targetUser): array
    {
        return array_values(array_filter([
            (string) ($targetUser['admin_profile_photo_path'] ?? ''),
            (string) ($targetUser['doctor_profile_photo_path'] ?? ''),
            (string) ($targetUser['signature_path'] ?? ''),
            (string) ($targetUser['patient_profile_photo_path'] ?? ''),
        ], static fn (string $path): bool => trim($path) !== ''));
    }

    /**
     * @param list<string> $assetPaths
     */
    private static function deleteStoredAssets(array $assetPaths): void
    {
        foreach ($assetPaths as $assetPath) {
            ProfilePhotoService::deleteStoredPath((string) $assetPath);
        }
    }

    private static function cancelOpenConsultations(PDO $db, int $userId, string $role): void
    {
        if (!in_array($role, ['patient', 'doctor'], true)) {
            return;
        }

        $column = $role === 'patient' ? 'patient_id' : 'doctor_id';
        $select = $db->prepare(
            "SELECT id, status, availability_id
            FROM consultation_requests
            WHERE {$column} = :user_id
              AND status IN ('Pending', 'Approved')
            FOR UPDATE"
        );
        $select->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $select->execute();
        $rows = $select->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $availabilityId = (int) ($row['availability_id'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            if ($availabilityId > 0 && $status === Status::APPROVED) {
                $release = $db->prepare(
                    "UPDATE doctor_availability
                    SET status = 'Available'
                    WHERE id = :id
                      AND status = 'Booked'"
                );
                $release->bindValue(':id', $availabilityId, PDO::PARAM_INT);
                $release->execute();
            }

            $update = $db->prepare(
                "UPDATE consultation_requests
                SET status = 'Cancelled'
                WHERE id = :id
                  AND status IN ('Pending', 'Approved')"
            );
            $update->bindValue(':id', (int) $row['id'], PDO::PARAM_INT);
            $update->execute();
        }
    }

    private static function releaseUnusedDoctorSlots(PDO $db, int $userId, string $role): void
    {
        if ($role !== 'doctor') {
            return;
        }

        $stmt = $db->prepare(
            "DELETE FROM doctor_availability
            WHERE doctor_id = :doctor_id
              AND status = 'Available'"
        );
        $stmt->bindValue(':doctor_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private static function deleteUserFacingRecords(PDO $db, int $userId): void
    {
        $notifications = $db->prepare('DELETE FROM notifications WHERE user_id = :user_id');
        $notifications->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $notifications->execute();

        if (self::tableExists($db, 'notification_preferences')) {
            $prefs = $db->prepare('DELETE FROM notification_preferences WHERE user_id = :user_id');
            $prefs->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $prefs->execute();
        }

        if (self::tableExists($db, 'user_sessions')) {
            $sessions = $db->prepare('DELETE FROM user_sessions WHERE user_id = :user_id');
            $sessions->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $sessions->execute();
        }

        if (self::tableExists($db, 'doctor_password_setup_tokens')) {
            $tokens = $db->prepare('DELETE FROM doctor_password_setup_tokens WHERE user_id = :user_id');
            $tokens->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $tokens->execute();
        }
    }

    private static function anonymizeRoleProfile(PDO $db, int $userId, string $role): void
    {
        if ($role === 'patient') {
            $stmt = $db->prepare(
                "UPDATE patient
                SET dob = NULL,
                    gender = NULL,
                    address = NULL,
                    medical_history = NULL,
                    phone = NULL,
                    profile_photo_path = NULL
                WHERE user_id = :user_id"
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return;
        }

        if ($role === 'doctor') {
            $stmt = $db->prepare(
                "UPDATE doctor
                SET phone = NULL,
                    clinic_address = NULL,
                    profile_photo_path = NULL,
                    signature_path = NULL,
                    consent_doc_path = NULL,
                    employee_id = NULL
                WHERE user_id = :user_id"
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            DoctorSignatureService::clear($userId);
            return;
        }

        if ($role === 'admin') {
            $stmt = $db->prepare(
                "UPDATE admin
                SET profile_photo_path = NULL
                WHERE user_id = :user_id"
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    private static function anonymizeUserRow(PDO $db, int $userId): void
    {
        $stmt = $db->prepare(
            "UPDATE users
            SET full_name = :full_name,
                email = :email,
                password = NULL,
                google_sub = NULL,
                google_email = NULL,
                auth_provider = 'local',
                status = 'deleted',
                force_password_reset = 0,
                deleted_at = NOW(),
                anonymized_at = NOW()
            WHERE id = :id
              AND status <> 'deleted'"
        );
        $stmt->bindValue(':full_name', 'Deleted User');
        $stmt->bindValue(':email', 'deleted+' . $userId . '@anonymized.invalid');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException('The user account could not be anonymized.');
        }
    }

    private static function countWhere(PDO $db, string $table, string $column, int $userId): int
    {
        $allowed = [
            'consultation_records' => ['patient_id', 'doctor_id'],
            'prescriptions' => ['patient_id', 'doctor_id'],
        ];
        if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
            return 0;
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :user_id");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private static function countWhereStatus(PDO $db, string $table, string $column, int $userId, string $status): int
    {
        if ($table !== 'consultation_requests' || !in_array($column, ['patient_id', 'doctor_id'], true)) {
            return 0;
        }

        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM consultation_requests
            WHERE {$column} = :user_id
              AND status = :status"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private static function countOpenRequests(PDO $db, string $column, int $userId): int
    {
        if (!in_array($column, ['patient_id', 'doctor_id'], true)) {
            return 0;
        }

        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM consultation_requests
            WHERE {$column} = :user_id
              AND status IN ('Pending', 'Approved')"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table'
        );
        $stmt->execute([':table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
