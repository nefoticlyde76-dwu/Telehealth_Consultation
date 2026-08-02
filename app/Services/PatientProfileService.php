<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Models\Patient;
use App\Models\User;

class PatientProfileService
{
    public static function getProfileDetail(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return Patient::findProfileDetailByUserId($userId);
    }

    public static function updateProfilePhoto(int $userId, array $input, array $files): array
    {
        $profile = self::getProfileDetail($userId);

        if ($profile === null) {
            return [
                'success' => false,
                'errors' => ['The patient profile could not be found.'],
                'fieldErrors' => [],
            ];
        }

        $fieldErrors = [];
        $errors = [];

        if (!Csrf::verify((string) ($input['_token'] ?? ''))) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        if (
            !isset($files['profile_photo'])
            || !is_array($files['profile_photo'])
            || ($files['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            $fieldErrors['profile_photo'] = 'Please choose a profile photo to crop and upload.';
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }

            $errors[] = 'Please correct the highlighted profile photo fields.';

            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'profile' => $profile,
            ];
        }

        $patient = Patient::findByUserId($userId);
        $user = User::findById($userId);

        if ($patient === null || $user === null || $user->getRole() !== 'patient') {
            return [
                'success' => false,
                'errors' => ['The patient profile could not be loaded for editing.'],
                'fieldErrors' => [],
                'profile' => $profile,
            ];
        }

        $db = Database::getInstance();
        $uploadedProfilePhotoPath = null;

        try {
            $db->beginTransaction();

            $upload = ProfilePhotoService::storeCroppedProfilePhoto(
                $userId,
                'patients',
                $files['profile_photo'],
                $patient->profile_photo_path
            );

            $patient->profile_photo_path = (string) ($upload['path'] ?? $patient->profile_photo_path);
            $uploadedProfilePhotoPath = $patient->profile_photo_path;

            if (!$patient->save()) {
                throw new \RuntimeException('The patient profile photo could not be updated.');
            }

            $db->commit();

            return [
                'success' => true,
                'message' => 'Patient profile photo updated successfully.',
                'profile' => self::getProfileDetail($userId),
            ];
        } catch (\RuntimeException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            ProfilePhotoService::deleteStoredPath($uploadedProfilePhotoPath);

            return [
                'success' => false,
                'errors' => [$exception->getMessage()],
                'fieldErrors' => ['profile_photo' => $exception->getMessage()],
                'profile' => $profile,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            ProfilePhotoService::deleteStoredPath($uploadedProfilePhotoPath);

            error_log('Patient profile photo update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Patient profile photo updates are temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'profile' => $profile,
            ];
        }
    }
}
