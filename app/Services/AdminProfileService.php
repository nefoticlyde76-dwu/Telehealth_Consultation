<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Helpers\Helper;
use App\Models\Admin;
use App\Models\User;

class AdminProfileService
{
    public static function getProfileDetail(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return Admin::findProfileDetailByUserId($userId);
    }

    public static function getProfileFormData(?array $profile = null): array
    {
        return [
            '_token' => '',
            'full_name' => (string) ($profile['full_name'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'employee_id' => (string) ($profile['employee_id'] ?? ''),
        ];
    }

    public static function updateProfile(int $userId, array $input): array
    {
        $profile = self::getProfileDetail($userId);

        if ($profile === null) {
            return [
                'success' => false,
                'errors' => ['The administrator profile could not be found.'],
                'fieldErrors' => [],
                'formData' => self::normalizeProfileFormData($input),
            ];
        }

        $formData = self::normalizeProfileFormData($input);
        [$errors, $fieldErrors] = self::validateProfileForm($formData, $userId);

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
            $admin = Admin::findByUserId($userId);

            if ($user === null || $admin === null || $user->getRole() !== 'admin') {
                throw new \RuntimeException('The administrator profile could not be loaded for editing.');
            }

            $user->full_name = $formData['full_name'];
            $user->email = $formData['email'];

            if (!$user->save()) {
                throw new \RuntimeException('The administrator user record could not be updated.');
            }

            $admin->employee_id = $formData['employee_id'];

            if (!$admin->save()) {
                throw new \RuntimeException('The administrator profile details could not be updated.');
            }

            $db->commit();

            return [
                'success' => true,
                'message' => 'Administrator profile updated successfully.',
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Administrator profile update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Administrator profile updates are temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'formData' => $formData,
            ];
        }
    }

    public static function updatePassword(int $userId, array $input): array
    {
        $profile = self::getProfileDetail($userId);

        if ($profile === null) {
            return [
                'success' => false,
                'errors' => ['The administrator profile could not be found.'],
                'fieldErrors' => [],
            ];
        }

        $currentPassword = (string) ($input['current_password'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');
        $csrfToken = (string) ($input['_token'] ?? '');
        $errors = [];
        $fieldErrors = [];

        if (!Csrf::verify($csrfToken)) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        $user = User::findById($userId);

        if ($user === null || $user->getRole() !== 'admin') {
            $errors[] = 'The administrator profile could not be loaded.';

            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
            ];
        }

        if ($currentPassword === '') {
            $fieldErrors['current_password'] = 'Current password is required.';
        } elseif (!password_verify($currentPassword, (string) $user->password)) {
            $fieldErrors['current_password'] = 'Current password is incorrect.';
        }

        if ($password === '') {
            $fieldErrors['password'] = 'New password is required.';
        } elseif (!Helper::isStrongPassword($password)) {
            $fieldErrors['password'] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
        } elseif (password_verify($password, (string) $user->password)) {
            $fieldErrors['password'] = 'Please choose a new password that is different from the current password.';
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

            $errors[] = 'Please correct the highlighted password fields.';

            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
            ];
        }

        try {
            if (!User::updatePasswordHash($userId, password_hash($password, PASSWORD_DEFAULT))) {
                throw new \RuntimeException('The administrator password update did not persist.');
            }

            return [
                'success' => true,
                'message' => 'Administrator password updated successfully.',
            ];
        } catch (\Throwable $exception) {
            error_log('Administrator password update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Administrator password updates are temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
            ];
        }
    }

    private static function normalizeProfileFormData(array $input): array
    {
        return [
            '_token' => (string) ($input['_token'] ?? ''),
            'full_name' => trim((string) ($input['full_name'] ?? '')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'employee_id' => trim((string) ($input['employee_id'] ?? '')),
        ];
    }

    private static function validateProfileForm(array $formData, int $excludeUserId): array
    {
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
        } elseif (User::findByEmailExcludingId($formData['email'], $excludeUserId) !== null) {
            $fieldErrors['email'] = 'A user account with this email address already exists.';
        }

        if ($formData['employee_id'] === '') {
            $fieldErrors['employee_id'] = 'Employee ID is required.';
        } elseif (mb_strlen($formData['employee_id']) > 100) {
            $fieldErrors['employee_id'] = 'Employee ID must be 100 characters or fewer.';
        } elseif (Admin::findByEmployeeId($formData['employee_id'], $excludeUserId) !== null) {
            $fieldErrors['employee_id'] = 'This employee ID is already assigned to another administrator.';
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }

            $errors[] = 'Please correct the highlighted administrator profile fields.';
        }

        return [$errors, $fieldErrors];
    }
}
