<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Models\Patient;
use App\Models\User;

class AdminPatientService
{
    public const PER_PAGE = 10;

    public static function getDashboardSummary(): array
    {
        return Patient::getManagementSummary();
    }

    public static function getPatientManagementPageData(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $filters = self::normalizeFilters($query);
        $totalPatients = Patient::countForManagement($filters);
        $totalPages = max(1, (int) ceil($totalPatients / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'filters' => $filters,
            'patients' => Patient::findForManagement($filters, self::PER_PAGE, $offset),
            'summary' => Patient::getManagementSummary(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total_items' => $totalPatients,
                'total_pages' => $totalPages,
            ],
            'statusOptions' => self::getStatusOptions(),
            'genderOptions' => self::getGenderOptions(),
        ];
    }

    public static function getPatientDetail(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return Patient::findManagementDetailByUserId($userId);
    }

    public static function getPatientFormData(?array $patient = null): array
    {
        return [
            '_token' => '',
            'full_name' => (string) ($patient['full_name'] ?? ''),
            'email' => (string) ($patient['email'] ?? ''),
            'dob' => (string) ($patient['dob'] ?? ''),
            'gender' => (string) ($patient['gender'] ?? ''),
            'address' => (string) ($patient['address'] ?? ''),
            'status' => (string) ($patient['status'] ?? 'active'),
        ];
    }

    public static function updatePatientAccount(int $userId, array $input): array
    {
        $existingPatient = self::getPatientDetail($userId);

        if ($existingPatient === null) {
            return [
                'success' => false,
                'errors' => ['The requested patient account could not be found.'],
                'fieldErrors' => [],
                'formData' => self::normalizeFormData($input),
            ];
        }

        $formData = self::normalizeFormData($input);
        [$errors, $fieldErrors] = self::validatePatientForm($formData, $userId);

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
            $patient = Patient::findByUserId($userId);

            if ($user === null || $patient === null || $user->getRole() !== 'patient') {
                throw new \RuntimeException('The patient account could not be loaded for editing.');
            }

            $user->full_name = $formData['full_name'];
            $user->email = $formData['email'];
            $user->status = $formData['status'];

            if (!$user->save()) {
                throw new \RuntimeException('The patient user account could not be updated.');
            }

            $patient->dob = $formData['dob'] !== '' ? $formData['dob'] : null;
            $patient->gender = $formData['gender'] !== '' ? $formData['gender'] : null;
            $patient->address = $formData['address'] !== '' ? $formData['address'] : null;

            if (!$patient->save()) {
                throw new \RuntimeException('The patient profile could not be updated.');
            }

            $db->commit();

            return [
                'success' => true,
                'patientId' => $userId,
                'message' => 'Patient account updated successfully.',
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Patient account update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Patient account updates are temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'formData' => $formData,
            ];
        }
    }

    public static function updatePatientStatus(int $userId, string $targetStatus, string $csrfToken): array
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
                'message' => 'The requested patient status is invalid.',
                'type' => 'danger',
            ];
        }

        $patient = self::getPatientDetail($userId);

        if ($patient === null) {
            return [
                'success' => false,
                'message' => 'The requested patient account could not be found.',
                'type' => 'warning',
            ];
        }

        if (($patient['status'] ?? '') === $targetStatus) {
            return [
                'success' => true,
                'message' => 'The patient account is already marked as ' . $targetStatus . '.',
                'type' => 'info',
            ];
        }

        try {
            if (!User::updateStatus($userId, $targetStatus)) {
                throw new \RuntimeException('The patient account status update did not persist.');
            }

            return [
                'success' => true,
                'message' => 'Patient account status updated to ' . ucfirst($targetStatus) . '.',
                'type' => 'success',
            ];
        } catch (\Throwable $exception) {
            error_log('Patient account status update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Patient account status could not be updated right now.',
                'type' => 'danger',
            ];
        }
    }

    public static function getGenderOptions(): array
    {
        return ['male', 'female', 'other'];
    }

    public static function getStatusOptions(): array
    {
        return ['active', 'inactive'];
    }

    private static function normalizeFilters(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $status = strtolower(trim((string) ($query['status'] ?? '')));

        if (!in_array($status, self::getStatusOptions(), true)) {
            $status = '';
        }

        return [
            'search' => $search,
            'status' => $status,
        ];
    }

    private static function normalizeFormData(array $input): array
    {
        $status = strtolower(trim((string) ($input['status'] ?? 'active')));
        $gender = strtolower(trim((string) ($input['gender'] ?? '')));

        if (!in_array($status, self::getStatusOptions(), true)) {
            $status = 'active';
        }

        if ($gender !== '' && !in_array($gender, self::getGenderOptions(), true)) {
            $gender = '';
        }

        return [
            '_token' => (string) ($input['_token'] ?? ''),
            'full_name' => trim((string) ($input['full_name'] ?? '')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'dob' => trim((string) ($input['dob'] ?? '')),
            'gender' => $gender,
            'address' => trim((string) ($input['address'] ?? '')),
            'status' => $status,
        ];
    }

    private static function validatePatientForm(array $formData, int $excludeUserId): array
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

        if ($formData['dob'] !== '') {
            $dob = \DateTime::createFromFormat('Y-m-d', $formData['dob']);
            $isValidDate = $dob && $dob->format('Y-m-d') === $formData['dob'];

            if (!$isValidDate) {
                $fieldErrors['dob'] = 'Date of birth must be a valid date.';
            } elseif ($dob > new \DateTime('today')) {
                $fieldErrors['dob'] = 'Date of birth cannot be in the future.';
            }
        }

        if ($formData['gender'] !== '' && !in_array($formData['gender'], self::getGenderOptions(), true)) {
            $fieldErrors['gender'] = 'Please select a valid gender option.';
        }

        if (mb_strlen($formData['address']) > 1000) {
            $fieldErrors['address'] = 'Address must be 1000 characters or fewer.';
        }

        if (!in_array($formData['status'], self::getStatusOptions(), true)) {
            $fieldErrors['status'] = 'Please select a valid account status.';
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }

            $errors[] = 'Please correct the highlighted patient account fields.';
        }

        return [$errors, $fieldErrors];
    }
}
