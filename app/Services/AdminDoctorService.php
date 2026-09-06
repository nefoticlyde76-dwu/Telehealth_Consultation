<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Helpers\Helper;
use App\Helpers\Status;
use App\Models\Doctor;
use App\Models\User;

class AdminDoctorService
{
    public const PER_PAGE = 10;

    /**
     * Test-only hook used to prove user+doctor creation rolls back together.
     * Production callers must leave this false.
     */
    public static bool $testFailDoctorProfileInsert = false;

    public static function resetTestState(): void
    {
        self::$testFailDoctorProfileInsert = false;
    }

    public static function getDashboardSummary(): array
    {
        return Doctor::getManagementSummary();
    }

    public static function getDoctorManagementPageData(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $filters = self::normalizeFilters($query);
        $totalDoctors = Doctor::countForManagement($filters);
        $totalPages = max(1, (int) ceil($totalDoctors / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'filters' => $filters,
            'doctors' => Doctor::findForManagement($filters, self::PER_PAGE, $offset),
            'summary' => Doctor::getManagementSummary(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total_items' => $totalDoctors,
                'total_pages' => $totalPages,
            ],
            'statusOptions' => Status::filterableUserKeys(),
        ];
    }

    public static function getDoctorDetail(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $doctor = Doctor::findManagementDetailByUserId($userId);
        if ($doctor === null || Status::isDeletedUserStatus((string) ($doctor['status'] ?? ''))) {
            return null;
        }

        return $doctor;
    }

    /**
     * Presentation actions for the doctor management table.
     * Backend endpoints still enforce the same pending-doctor restrictions.
     *
     * @param array<string, mixed> $doctor
     * @return array{edit:bool,reset_password:bool,resend_invitation:bool,activate:bool,deactivate:bool}
     */
    public static function managementActions(array $doctor): array
    {
        $status = Status::normalizeKey(Status::DOMAIN_USER, (string) ($doctor['status'] ?? ''));
        $isDeleted = Status::isDeletedUserStatus($status);
        $isPending = Status::isInvitationPendingUserStatus($status);
        $isActive = $status === Status::USER_ACTIVE;

        return [
            'edit' => !$isDeleted,
            'reset_password' => !$isDeleted && !$isPending,
            'resend_invitation' => !$isDeleted && $isPending,
            'activate' => !$isDeleted && !$isPending && !$isActive,
            'deactivate' => !$isDeleted && !$isPending && $isActive,
        ];
    }

    public static function getDoctorFormData(?array $doctor = null): array
    {
        return [
            'full_name' => (string) ($doctor['full_name'] ?? ''),
            'email' => (string) ($doctor['email'] ?? ''),
            'phone' => (string) ($doctor['phone'] ?? ''),
            'gender' => (string) ($doctor['gender'] ?? ''),
            'professional_title' => (string) ($doctor['professional_title'] ?? ''),
            'specialization' => (string) ($doctor['specialization'] ?? ''),
            'employee_id' => (string) ($doctor['employee_id'] ?? ''),
            'status' => (string) ($doctor['status'] ?? 'active'),
        ];
    }

    public static function createDoctorAccount(array $input): array
    {
        $formData = self::normalizeFormData($input, true);
        [$errors, $fieldErrors] = self::validateDoctorForm($formData, '', '', true);

        if ($errors !== []) {
            return [
                'success' => false,
                'accountCreated' => false,
                'invitationSent' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
            ];
        }

        $roleId = User::findRoleIdByName('doctor');

        if ($roleId === null) {
            return [
                'success' => false,
                'accountCreated' => false,
                'invitationSent' => false,
                'errors' => ['The doctor role is not configured in the database.'],
                'fieldErrors' => [],
                'formData' => $formData,
            ];
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $user = User::createInvitedDoctorUser([
                'role_id' => $roleId,
                'full_name' => $formData['full_name'],
                'email' => $formData['email'],
            ]);

            if ($user === null || $user->id === null) {
                throw new \RuntimeException('Unable to create the doctor user account.');
            }

            $doctor = new Doctor();
            $doctor->user_id = $user->id;
            $doctor->phone = $formData['phone'];
            $doctor->gender = $formData['gender'];
            $doctor->professional_title = $formData['professional_title'];
            $doctor->specialization = $formData['specialization'];
            $doctor->employee_id = $formData['employee_id'] !== '' ? $formData['employee_id'] : null;

            if (self::$testFailDoctorProfileInsert || !$doctor->save()) {
                throw new \RuntimeException('Unable to create the linked doctor profile.');
            }

            $db->commit();
            AuditLogService::record(
                'doctor_account_created',
                'Administrator created a doctor account.',
                AuditLogService::ENTITY_USER,
                (int) $user->id,
                'success',
                ['subject_name' => $formData['full_name'], 'subject_role' => 'doctor']
            );

            $invitationSent = false;
            $issued = DoctorInvitationService::issueToken((int) $user->id);
            if (is_array($issued)) {
                $sendResult = DoctorInvitationService::sendIssuedInvitation($issued);
                $invitationSent = (bool) ($sendResult['success'] ?? false)
                    && (bool) ($sendResult['smtp_accepted'] ?? false);
            }

            $failureMessage = 'Doctor account was created, but the invitation email could not be sent. Please use Resend Invitation.';
            if (!$invitationSent) {
                $failureMessage .= MailService::adminFailureHint();
            }

            return [
                'success' => true,
                'accountCreated' => true,
                'invitationSent' => $invitationSent,
                'doctorId' => $user->id,
                'message' => $invitationSent
                    ? 'Doctor account created successfully. A password setup invitation has been sent to the doctor\'s email.'
                    : $failureMessage,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Doctor account creation failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'accountCreated' => false,
                'invitationSent' => false,
                'errors' => ['Doctor account creation is temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'formData' => $formData,
            ];
        }
    }

    public static function updateDoctorAccount(int $userId, array $input): array
    {
        $existingDoctor = self::getDoctorDetail($userId);

        if ($existingDoctor === null) {
            return [
                'success' => false,
                'errors' => ['The requested doctor account could not be found.'],
                'fieldErrors' => [],
                'formData' => self::normalizeFormData($input),
            ];
        }

        $preserveInvitationPending = Status::isInvitationPendingUserStatus((string) ($existingDoctor['status'] ?? ''));
        $formData = self::normalizeFormData($input, false, $preserveInvitationPending);
        [$errors, $fieldErrors] = self::validateDoctorForm($formData, '', '', false, $userId, $preserveInvitationPending);

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
            ];
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $user = User::findById($userId);
            $doctor = Doctor::findByUserId($userId);

            if ($user === null || $doctor === null || $user->getRole() !== 'doctor') {
                throw new \RuntimeException('The doctor account could not be loaded for editing.');
            }

            if ($preserveInvitationPending) {
                if (!User::updateIdentity($userId, $formData['full_name'], $formData['email'])) {
                    throw new \RuntimeException('The doctor user account could not be updated.');
                }
            } else {
                $user->full_name = $formData['full_name'];
                $user->email = $formData['email'];
                $user->status = $formData['status'];

                if (!$user->save()) {
                    throw new \RuntimeException('The doctor user account could not be updated.');
                }
            }

            $doctor->phone = $formData['phone'];
            $doctor->gender = $formData['gender'];
            $doctor->professional_title = $formData['professional_title'];
            $doctor->specialization = $formData['specialization'];
            $doctor->employee_id = $formData['employee_id'] !== '' ? $formData['employee_id'] : null;

            if (!$doctor->update()) {
                throw new \RuntimeException('The doctor profile could not be updated.');
            }

            $db->commit();
            AuditLogService::record(
                'doctor_account_updated',
                'Administrator updated a doctor account.',
                AuditLogService::ENTITY_USER,
                $userId,
                'success',
                ['subject_name' => $formData['full_name'], 'subject_role' => 'doctor']
            );

            return [
                'success' => true,
                'doctorId' => $userId,
                'message' => 'Doctor account updated successfully.',
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Doctor account update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Doctor account updates are temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'formData' => $formData,
            ];
        }
    }

    public static function updateDoctorStatus(int $userId, string $targetStatus, string $csrfToken): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        if (!in_array($targetStatus, self::getStatusOptions(), true)) {
            return [
                'success' => false,
                'message' => 'The requested doctor status is invalid.',
                'type' => 'danger',
            ];
        }

        $doctor = self::getDoctorDetail($userId);

        if ($doctor === null) {
            return [
                'success' => false,
                'message' => 'The requested doctor account could not be found.',
                'type' => 'warning',
            ];
        }

        if (Status::isDeletedUserStatus((string) ($doctor['status'] ?? ''))) {
            return [
                'success' => false,
                'message' => 'A permanently deleted doctor account cannot be updated.',
                'type' => 'warning',
            ];
        }

        if (Status::isInvitationPendingUserStatus((string) ($doctor['status'] ?? ''))) {
            return [
                'success' => false,
                'message' => 'This doctor account cannot be activated or deactivated until password setup is complete.',
                'type' => 'warning',
            ];
        }

        if (($doctor['status'] ?? '') === $targetStatus) {
            return [
                'success' => true,
                'message' => 'The doctor account is already marked as ' . $targetStatus . '.',
                'type' => 'info',
            ];
        }

        try {
            if (!User::updateStatus($userId, $targetStatus)) {
                throw new \RuntimeException('The doctor account status update did not persist.');
            }
            if ($targetStatus !== Status::USER_ACTIVE) {
                SessionService::revokeAll($userId);
            }
            AuditLogService::record(
                'doctor_status_updated',
                'Administrator updated doctor account status to ' . $targetStatus . '.',
                AuditLogService::ENTITY_USER,
                $userId,
                'success',
                ['subject_name' => (string) ($doctor['full_name'] ?? ''), 'subject_role' => 'doctor']
            );

            return [
                'success' => true,
                'message' => 'Doctor account status updated to ' . ucfirst($targetStatus) . '.',
                'type' => 'success',
            ];
        } catch (\Throwable $exception) {
            error_log('Doctor account status update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Doctor account status could not be updated right now.',
                'type' => 'danger',
            ];
        }
    }

    public static function resetDoctorPassword(int $userId, array $input): array
    {
        $doctor = self::getDoctorDetail($userId);

        if ($doctor === null) {
            return [
                'success' => false,
                'errors' => ['The requested doctor account could not be found.'],
                'fieldErrors' => [],
            ];
        }

        if (Status::isInvitationPendingUserStatus((string) ($doctor['status'] ?? ''))) {
            return [
                'success' => false,
                'errors' => ['A password cannot be reset for a doctor whose invitation is still pending.'],
                'fieldErrors' => [],
                'doctor' => $doctor,
            ];
        }

        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');
        $csrfToken = (string) ($input['_token'] ?? '');
        $errors = [];
        $fieldErrors = [];

        if (!Csrf::verify($csrfToken)) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        if ($password === '') {
            $fieldErrors['password'] = 'New password is required.';
        } elseif (!Helper::isStrongPassword($password)) {
            $fieldErrors['password'] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
        }

        if ($confirmPassword === '') {
            $fieldErrors['confirm_password'] = 'Please confirm the new password.';
        } elseif ($confirmPassword !== $password) {
            $fieldErrors['confirm_password'] = 'Passwords do not match.';
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }

            $errors[] = 'Please correct the highlighted password reset fields.';

            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'doctor' => $doctor,
            ];
        }

        try {
            if (!User::updatePasswordHash($userId, password_hash($password, PASSWORD_DEFAULT))) {
                throw new \RuntimeException('The doctor password reset did not persist.');
            }
            AuditLogService::record(
                'doctor_password_reset',
                'Administrator reset a doctor account password.',
                AuditLogService::ENTITY_USER,
                $userId,
                'success',
                ['subject_name' => (string) ($doctor['full_name'] ?? ''), 'subject_role' => 'doctor']
            );

            return [
                'success' => true,
                'message' => 'Doctor password reset successfully.',
            ];
        } catch (\Throwable $exception) {
            error_log('Doctor password reset failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Doctor password reset is temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'doctor' => $doctor,
            ];
        }
    }

    /**
     * @return array{success:bool,invitationSent?:bool,type:string,message:string}
     */
    public static function resendDoctorInvitation(int $userId, string $csrfToken): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'invitationSent' => false,
                'type' => 'danger',
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
            ];
        }

        $doctor = self::getDoctorDetail($userId);
        if (
            $doctor === null
            || !Status::isInvitationPendingUserStatus((string) ($doctor['status'] ?? ''))
        ) {
            return [
                'success' => false,
                'invitationSent' => false,
                'type' => 'warning',
                'message' => 'The invitation could not be resent. Please verify that the doctor is still pending.',
            ];
        }

        return DoctorInvitationService::resendInvitation($userId);
    }

    public static function getGenderOptions(): array
    {
        return ['male', 'female', 'other'];
    }

    public static function getStatusOptions(): array
    {
        return Status::assignableUserKeys();
    }

    private static function normalizeFilters(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $status = Status::normalizeKey(Status::DOMAIN_USER, (string) ($query['status'] ?? ''));

        if (!in_array($status, Status::filterableUserKeys(), true)) {
            $status = '';
        }

        return [
            'search' => $search,
            'status' => $status,
        ];
    }

    private static function normalizeFormData(array $input, bool $isCreate = false, bool $preserveInvitationPending = false): array
    {
        $status = Status::USER_INVITATION_PENDING;

        if (!$isCreate && !$preserveInvitationPending) {
            $status = strtolower(trim((string) ($input['status'] ?? 'active')));

            if (!in_array($status, self::getStatusOptions(), true)) {
                $status = 'active';
            }
        }

        return [
            '_token' => (string) ($input['_token'] ?? ''),
            'full_name' => trim((string) ($input['full_name'] ?? '')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'gender' => strtolower(trim((string) ($input['gender'] ?? ''))),
            'professional_title' => trim((string) ($input['professional_title'] ?? '')),
            'specialization' => trim((string) ($input['specialization'] ?? '')),
            'employee_id' => trim((string) ($input['employee_id'] ?? '')),
            'status' => $status,
        ];
    }

    private static function validateDoctorForm(
        array $formData,
        string $password,
        string $confirmPassword,
        bool $isCreate,
        ?int $excludeUserId = null,
        bool $preserveInvitationPending = false
    ): array {
        $errors = [];
        $fieldErrors = [];

        if (!Csrf::verify((string) ($formData['_token'] ?? ''))) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        if ($formData['full_name'] === '') {
            $fieldErrors['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($formData['full_name']) > 255) {
            $fieldErrors['full_name'] = 'Full name must be 255 characters or fewer.';
        }

        if ($formData['email'] === '') {
            $fieldErrors['email'] = 'Email address is required.';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $fieldErrors['email'] = 'Please provide a valid email address.';
        } elseif (
            ($excludeUserId === null && User::findByEmail($formData['email']) !== null)
            || ($excludeUserId !== null && User::findByEmailExcludingId($formData['email'], $excludeUserId) !== null)
        ) {
            $fieldErrors['email'] = 'A user account with this email address already exists.';
        }

        if ($formData['phone'] === '') {
            $fieldErrors['phone'] = 'Phone number is required.';
        } elseif (mb_strlen($formData['phone']) > 30) {
            $fieldErrors['phone'] = 'Phone number must be 30 characters or fewer.';
        } elseif (!preg_match('/^[0-9+()\-\s]{7,30}$/', $formData['phone'])) {
            $fieldErrors['phone'] = 'Please provide a valid phone number.';
        }

        if (!in_array($formData['gender'], self::getGenderOptions(), true)) {
            $fieldErrors['gender'] = 'Please select a valid gender option.';
        }

        if ($formData['professional_title'] === '') {
            $fieldErrors['professional_title'] = 'Professional title is required.';
        } elseif (mb_strlen($formData['professional_title']) > 150) {
            $fieldErrors['professional_title'] = 'Professional title must be 150 characters or fewer.';
        }

        if ($formData['specialization'] === '') {
            $fieldErrors['specialization'] = 'Specialization is required.';
        } elseif (mb_strlen($formData['specialization']) > 255) {
            $fieldErrors['specialization'] = 'Specialization must be 255 characters or fewer.';
        }

        if ($formData['employee_id'] !== '') {
            if (mb_strlen($formData['employee_id']) > 100) {
                $fieldErrors['employee_id'] = 'Employee ID must be 100 characters or fewer.';
            } elseif (Doctor::findByEmployeeId($formData['employee_id'], $excludeUserId) !== null) {
                $fieldErrors['employee_id'] = 'This employee ID is already assigned to another doctor account.';
            }
        }

        if (
            !$isCreate
            && !$preserveInvitationPending
            && !in_array($formData['status'], self::getStatusOptions(), true)
        ) {
            $fieldErrors['status'] = 'Please select a valid account status.';
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }

            $errors[] = 'Please correct the highlighted doctor account fields.';
        }

        return [$errors, $fieldErrors];
    }
}
