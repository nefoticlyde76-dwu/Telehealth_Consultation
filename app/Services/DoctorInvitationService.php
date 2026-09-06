<?php

namespace App\Services;

use App\Config\App;
use App\Core\Database;
use App\Helpers\Status;
use App\Models\Doctor;
use App\Models\DoctorPasswordSetupToken;
use App\Models\User;
use PDO;

/**
 * Doctor password-setup invitation tokens and invitation email delivery.
 * Issues hash-only tokens. Sends mail through MailService. Does not
 * create doctor accounts or complete password setup.
 */
class DoctorInvitationService
{
    /**
     * Test-only hook: expire previous tokens, then fail before insert
     * so callers can prove the transaction rolls back. Production must
     * leave this false.
     */
    public static bool $testFailInsert = false;

    public static function resetTestState(): void
    {
        self::$testFailInsert = false;
    }

    /**
     * Expire prior usable tokens and insert a new hash-only invitation.
     * The raw token is returned in memory only for the later email step.
     *
     * @return array{
     *   token_id:int,
     *   user_id:int,
     *   raw_token:string,
     *   token_hash:string,
     *   expires_at:string,
     *   ttl_hours:int
     * }|null
     */
    public static function issueToken(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        if (User::findById($userId) === null) {
            return null;
        }

        $ttlHours = (int) (App::getConfig()['doctor_invitation']['token_ttl_hours'] ?? 0);
        if ($ttlHours < 1) {
            return null;
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $db = Database::getInstance();
        $ownsTransaction = !$db->inTransaction();
        $usedSavepoint = false;

        if ($ownsTransaction) {
            $db->beginTransaction();
        } else {
            $db->exec('SAVEPOINT doctor_invite_issue_token');
            $usedSavepoint = true;
        }

        try {
            $lock = $db->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
            $lock->bindValue(':id', $userId, PDO::PARAM_INT);
            $lock->execute();
            if ($lock->fetch(PDO::FETCH_ASSOC) === false) {
                throw new \RuntimeException('Doctor invitation token insert failed.');
            }

            DoctorPasswordSetupToken::expireUsableForUser($userId);

            if (self::$testFailInsert) {
                throw new \RuntimeException('Doctor invitation token insert failed.');
            }

            $row = DoctorPasswordSetupToken::insert($userId, $tokenHash, $ttlHours);
            if ($row === null || (int) ($row['id'] ?? 0) <= 0) {
                throw new \RuntimeException('Doctor invitation token insert failed.');
            }

            if ($ownsTransaction) {
                $db->commit();
            } elseif ($usedSavepoint) {
                $db->exec('RELEASE SAVEPOINT doctor_invite_issue_token');
            }

            return [
                'token_id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'raw_token' => $rawToken,
                'token_hash' => (string) $row['token_hash'],
                'expires_at' => (string) $row['expires_at'],
                'ttl_hours' => $ttlHours,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            } elseif ($usedSavepoint) {
                $db->exec('ROLLBACK TO SAVEPOINT doctor_invite_issue_token');
            }

            error_log('[DoctorInvitationService::issueToken] Token issuance failed.');

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByTokenHash(string $tokenHash): ?array
    {
        return DoctorPasswordSetupToken::findByTokenHash($tokenHash);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $tokenId): ?array
    {
        return DoctorPasswordSetupToken::findById($tokenId);
    }

    public static function markUsed(int $tokenId): bool
    {
        return DoctorPasswordSetupToken::markUsed($tokenId);
    }

    public static function markSent(int $tokenId): bool
    {
        return DoctorPasswordSetupToken::markSent($tokenId);
    }

    /**
     * Send the password-setup invitation for an already-issued token.
     * Recipient and name come from the trusted users row, not the browser.
     * The raw token is used only to build the setup URL and is not returned.
     *
     * @param array{
     *   token_id?:mixed,
     *   user_id?:mixed,
     *   raw_token?:mixed,
     *   ttl_hours?:mixed
     * } $issued
     * @return array{success:bool,token_id:int,smtp_accepted:bool,error_code?:string}
     */
    public static function sendIssuedInvitation(array $issued): array
    {
        $tokenId = (int) ($issued['token_id'] ?? 0);
        $userId = (int) ($issued['user_id'] ?? 0);
        $rawToken = (string) ($issued['raw_token'] ?? '');
        $ttlHours = (int) ($issued['ttl_hours'] ?? 0);

        $fail = static function (string $code, int $id = 0): array {
            return [
                'success' => false,
                'token_id' => $id,
                'smtp_accepted' => false,
                'error_code' => $code,
            ];
        };

        if ($tokenId <= 0 || $userId <= 0 || $rawToken === '' || $ttlHours < 1) {
            return $fail('incomplete_invitation', $tokenId);
        }

        $user = User::findById($userId);
        if ($user === null || $user->email === null) {
            return $fail('user_not_found', $tokenId);
        }

        $email = strtolower(trim((string) $user->email));
        $fullName = trim((string) ($user->full_name ?? ''));
        if ($fullName === '') {
            $fullName = 'Clinician';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $fail('invalid_recipient', $tokenId);
        }

        $setupUrl = \App\Helpers\Helper::applicationUrl('/doctor/setup-password?token=' . $rawToken);
        $contactUrl = \App\Helpers\Helper::applicationUrl('/contact');
        $ttlLabel = $ttlHours === 1 ? '1 hour' : $ttlHours . ' hours';
        $subject = 'MBPHA TeleHealth – Set Up Your Doctor Account';

        $html = self::renderEmailView('doctor_invitation', [
            'fullName' => $fullName,
            'setupUrl' => $setupUrl,
            'contactUrl' => $contactUrl,
            'ttlLabel' => $ttlLabel,
        ]);
        $text = self::renderEmailView('doctor_invitation_text', [
            'fullName' => $fullName,
            'setupUrl' => $setupUrl,
            'contactUrl' => $contactUrl,
            'ttlLabel' => $ttlLabel,
        ]);

        $rawToken = '';
        unset($issued['raw_token']);

        if ($html === '' || $text === '') {
            return $fail('incomplete_invitation', $tokenId);
        }

        try {
            $accepted = MailService::send($email, $subject, $html, $text);
        } catch (\Throwable $exception) {
            error_log('[DoctorInvitationService::sendIssuedInvitation] Mail delivery failed.');
            return $fail('mail_rejected', $tokenId);
        }

        if (!$accepted) {
            return $fail('mail_rejected', $tokenId);
        }

        self::markSent($tokenId);

        return [
            'success' => true,
            'token_id' => $tokenId,
            'smtp_accepted' => true,
        ];
    }

    /**
     * Replace any outstanding invitation and email a new setup link.
     * Recipient data is loaded from the trusted users row.
     *
     * @return array{
     *   success:bool,
     *   invitationSent:bool,
     *   type:string,
     *   message:string
     * }
     */
    public static function resendInvitation(int $userId): array
    {
        $invalid = [
            'success' => false,
            'invitationSent' => false,
            'type' => 'warning',
            'message' => 'The invitation could not be resent. Please verify that the doctor is still pending.',
        ];

        if ($userId <= 0 || !self::isEligiblePendingDoctor($userId)) {
            return $invalid;
        }

        $cooldownMinutes = (int) (App::getConfig()['doctor_invitation']['resend_cooldown_minutes'] ?? 5);
        $maxSendsPerDay = (int) (App::getConfig()['doctor_invitation']['max_sends_per_day'] ?? 8);

        if (DoctorPasswordSetupToken::hasRecentSuccessfulSend($userId, $cooldownMinutes)) {
            return [
                'success' => false,
                'invitationSent' => false,
                'type' => 'warning',
                'message' => 'The invitation could not be resent. Please wait before sending another invitation.',
            ];
        }

        if (DoctorPasswordSetupToken::countSentTodayForUser($userId) >= $maxSendsPerDay) {
            return [
                'success' => false,
                'invitationSent' => false,
                'type' => 'warning',
                'message' => 'The invitation could not be resent. Please try again later.',
            ];
        }

        $issued = self::issueToken($userId);
        if (!is_array($issued) || !self::isEligiblePendingDoctor($userId)) {
            return $invalid;
        }

        $sendResult = self::sendIssuedInvitation($issued);
        $issued['raw_token'] = '';
        unset($issued['raw_token']);

        if (!($sendResult['success'] ?? false) || !($sendResult['smtp_accepted'] ?? false)) {
            return [
                'success' => false,
                'invitationSent' => false,
                'type' => 'warning',
                'message' => 'The doctor\'s account is still pending, but the invitation email could not be sent. Please try Resend Invitation again.'
                    . MailService::adminFailureHint(),
            ];
        }

        $user = User::findById($userId);
        AuditLogService::record(
            'doctor_invitation_resent',
            'Administrator resent a doctor password setup invitation.',
            AuditLogService::ENTITY_USER,
            $userId,
            'success',
            [
                'subject_name' => trim((string) ($user?->full_name ?? '')),
                'subject_role' => 'doctor',
            ]
        );

        return [
            'success' => true,
            'invitationSent' => true,
            'type' => 'success',
            'message' => 'A new password setup invitation has been sent to the doctor\'s email.',
        ];
    }

    private static function isEligiblePendingDoctor(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = User::findById($userId);
        if ($user === null || $user->id === null) {
            return false;
        }

        if ($user->getRole() !== 'doctor') {
            return false;
        }

        if (Status::isDeletedUserStatus((string) ($user->status ?? ''))) {
            return false;
        }

        if (!Status::isInvitationPendingUserStatus((string) ($user->status ?? ''))) {
            return false;
        }

        $db = Database::getInstance();
        $passwordNull = $db->prepare('SELECT password IS NULL FROM users WHERE id = :id LIMIT 1');
        $passwordNull->bindValue(':id', $userId, PDO::PARAM_INT);
        $passwordNull->execute();
        if ((int) $passwordNull->fetchColumn() !== 1) {
            return false;
        }

        if (Doctor::findByUserId($userId) === null) {
            return false;
        }

        $email = strtolower(trim((string) ($user->email ?? '')));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function renderEmailView(string $view, array $data): string
    {
        $viewPath = dirname(__DIR__) . '/Views/emails/' . $view . '.php';
        if (!is_file($viewPath)) {
            return '';
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewPath;

        return trim((string) ob_get_clean());
    }
}
