<?php

/**
 * Complaint image upload: validation, storage, authorization, and booking wiring.
 *
 * Usage: php bin/test_complaint_image.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Config\Paths;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Models\DoctorAvailability;
use App\Services\ComplaintImageService;
use App\Services\PatientConsultationBookingService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');
Session::start();

$failed = 0;
$passed = 0;

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

function complaint_test_image(string $format): string
{
    $image = imagecreatetruecolor(24, 24);
    $fill = imagecolorallocate($image, 15, 76, 129);
    imagefilledrectangle($image, 0, 0, 23, 23, $fill);
    ob_start();
    if ($format === 'png') {
        imagepng($image);
    } else {
        imagejpeg($image, null, 90);
    }
    $binary = (string) ob_get_clean();
    imagedestroy($image);

    return $binary;
}

function complaint_test_file(string $contents, string $name, string $mime): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'cmpimg');
    if ($tmp === false) {
        throw new RuntimeException('Unable to create a temporary upload file.');
    }
    file_put_contents($tmp, $contents);

    return [
        ComplaintImageService::FIELD_NAME => [
            'name' => $name,
            'type' => $mime,
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($contents),
        ],
        '_tmp' => $tmp,
    ];
}

echo "Complaint image feature checks\n";

$db = Database::getInstance();
$columnStmt = $db->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'consultation_requests'
       AND COLUMN_NAME = 'complaint_image_path'"
);
$columnStmt->execute();
expect_true((int) $columnStmt->fetchColumn() === 1, 'consultation_requests.complaint_image_path exists');

$routes = (string) file_get_contents($root . '/routes/web.php');
$bookView = (string) file_get_contents($root . '/app/Views/patient/consultation_requests/book.php');
$details = (string) file_get_contents($root . '/app/Views/partials/shared/_consultation_record_details.php');
$doctorShow = (string) file_get_contents($root . '/app/Views/doctor/consultations/show.php');
$service = (string) file_get_contents($root . '/app/Services/ComplaintImageService.php');
$bookingService = (string) file_get_contents($root . '/app/Services/PatientConsultationBookingService.php');

expect_true(str_contains($bookView, 'Upload Complaint Image (Optional)'), 'Booking form includes the optional complaint image field');
expect_true(str_contains($bookView, 'enctype="multipart/form-data"'), 'Booking form posts multipart data');
expect_true(str_contains($bookView, 'name="complaint_image"'), 'Booking form posts complaint_image');
expect_true(str_contains($details, 'Complaint Image'), 'Consultation details include a Complaint Image section');
expect_true(str_contains($doctorShow, '_consultation_record_details.php'), 'Doctor consultation details reuse the shared record partial');
expect_true(str_contains($routes, "/patient/consultation-requests/{id}/complaint-image"), 'Patient complaint-image route is registered');
expect_true(str_contains($routes, "/doctor/consultations/{id}/complaint-image"), 'Doctor complaint-image route is registered');
expect_true(str_contains($routes, "/admin/consultation-requests/{id}/complaint-image"), 'Admin complaint-image route is registered');
expect_true((bool) preg_match("/complaint-image'[\\s\\S]{0,160}RoleMiddleware\\(\\['patient'\\]\\)/", $routes), 'Patient complaint-image route is role-protected');
expect_true((bool) preg_match("/complaint-image'[\\s\\S]{0,160}RoleMiddleware\\(\\['doctor'\\]\\)/", $routes), 'Doctor complaint-image route is role-protected');
expect_true((bool) preg_match("/complaint-image'[\\s\\S]{0,160}RoleMiddleware\\(\\['admin'\\]\\)/", $routes), 'Admin complaint-image route is role-protected');
expect_true(str_contains($service, 'Paths::storageRoot()'), 'Complaint images are stored outside the public web root');
expect_true(!str_contains($service, 'public/uploads'), 'Complaint images are not written to public/uploads');
expect_true(str_contains($bookingService, 'ComplaintImageService::storeForPatient'), 'Booking submission stores an uploaded complaint image');
expect_true(str_contains($bookingService, "array \$files = []"), 'Booking still works when no files array is supplied');

expect_true(ComplaintImageService::userMayAccess('patient', 11, ['patient_id' => 11, 'doctor_id' => 22]), 'Patient can access their own complaint image');
expect_true(!ComplaintImageService::userMayAccess('patient', 12, ['patient_id' => 11, 'doctor_id' => 22]), 'Patient cannot access another patient complaint image');
expect_true(ComplaintImageService::userMayAccess('doctor', 22, ['patient_id' => 11, 'doctor_id' => 22]), 'Assigned doctor can access the complaint image');
expect_true(!ComplaintImageService::userMayAccess('doctor', 23, ['patient_id' => 11, 'doctor_id' => 22]), 'Unassigned doctor cannot access the complaint image');
expect_true(ComplaintImageService::userMayAccess('admin', 99, ['patient_id' => 11, 'doctor_id' => 22]), 'Administrator can access a complaint image for review');
expect_true(!ComplaintImageService::userMayAccess('patient', 0, ['patient_id' => 11, 'doctor_id' => 22]), 'Anonymous user cannot access a complaint image');

expect_true(!ComplaintImageService::hasUpload([]), 'Booking without an image is treated as no upload');
expect_true(!ComplaintImageService::hasUpload([
    ComplaintImageService::FIELD_NAME => [
        'name' => '',
        'type' => '',
        'tmp_name' => '',
        'error' => UPLOAD_ERR_NO_FILE,
        'size' => 0,
    ],
]), 'Empty file input is treated as no upload');

$storedPaths = [];
$tmpFiles = [];

$jpegPack = complaint_test_file(complaint_test_image('jpeg'), 'rash.JPG', 'image/gif');
$tmpFiles[] = $jpegPack['_tmp'];
try {
    $stored = ComplaintImageService::storeForPatient(9001, $jpegPack);
    $storedPaths[] = (string) ($stored['path'] ?? '');
    expect_true(is_array($stored) && str_ends_with((string) ($stored['path'] ?? ''), '.jpg'), 'Valid JPEG is stored with a generated .jpg name');
    expect_true(!str_contains((string) ($stored['path'] ?? ''), 'rash'), 'Stored filename does not use the browser-supplied name');
    $absolute = Paths::storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($stored['path'] ?? ''));
    expect_true(is_file($absolute), 'Stored JPEG exists under private storage');
    expect_true(!str_starts_with(str_replace('\\', '/', $absolute), str_replace('\\', '/', Paths::publicRoot())), 'Stored JPEG is not inside the public root');
} catch (Throwable $exception) {
    expect_true(false, 'Valid JPEG store failed: ' . $exception->getMessage());
}

$pngPack = complaint_test_file(complaint_test_image('png'), 'swelling.png', 'application/octet-stream');
$tmpFiles[] = $pngPack['_tmp'];
try {
    $stored = ComplaintImageService::storeForPatient(9001, $pngPack);
    $storedPaths[] = (string) ($stored['path'] ?? '');
    expect_true(is_array($stored) && str_ends_with((string) ($stored['path'] ?? ''), '.png'), 'Valid PNG is stored with a generated .png name');
} catch (Throwable $exception) {
    expect_true(false, 'Valid PNG store failed: ' . $exception->getMessage());
}

$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') ?: 'GIF89a';
$gifPack = complaint_test_file($gif, 'not-allowed.gif', 'image/gif');
$tmpFiles[] = $gifPack['_tmp'];
$gifRejected = false;
try {
    ComplaintImageService::storeForPatient(9001, $gifPack);
} catch (RuntimeException $exception) {
    $gifRejected = str_contains($exception->getMessage(), 'JPG, JPEG, or PNG');
}
expect_true($gifRejected, 'GIF uploads are rejected');

$phpPack = complaint_test_file("<?php echo 'pwn';", 'payload.php.jpg', 'image/jpeg');
$tmpFiles[] = $phpPack['_tmp'];
$phpRejected = false;
try {
    ComplaintImageService::storeForPatient(9001, $phpPack);
} catch (RuntimeException $exception) {
    $phpRejected = true;
}
expect_true($phpRejected, 'Non-image executable payload is rejected even with a .jpg name');

$oversize = str_repeat('A', ComplaintImageService::MAX_BYTES + 1);
$oversizePack = complaint_test_file($oversize, 'huge.jpg', 'image/jpeg');
$tmpFiles[] = $oversizePack['_tmp'];
$oversizeRejected = false;
try {
    ComplaintImageService::storeForPatient(9001, $oversizePack);
} catch (RuntimeException $exception) {
    $oversizeRejected = str_contains($exception->getMessage(), '5 MB');
}
expect_true($oversizeRejected, 'Oversized uploads are rejected');

foreach ($storedPaths as $path) {
    ComplaintImageService::deleteStoredPath($path);
    $absolute = Paths::storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    expect_true($path === '' || !is_file($absolute), 'Stored test complaint image was removed after the check');
}

foreach ($tmpFiles as $tmp) {
    if (is_string($tmp) && is_file($tmp)) {
        @unlink($tmp);
    }
}

$csrfToken = Csrf::generate();

$suffix = 'cimg' . date('His') . bin2hex(random_bytes(2));
$createdUserIds = [];
$createdSlotIds = [];
$createdRequestIds = [];
$createdImagePaths = [];

$patientRoleId = (int) $db->query("SELECT id FROM roles WHERE name = 'patient' LIMIT 1")->fetchColumn();
$doctorRoleId = (int) $db->query("SELECT id FROM roles WHERE name = 'doctor' LIMIT 1")->fetchColumn();
expect_true($patientRoleId > 0 && $doctorRoleId > 0, 'Patient and doctor roles exist for booking tests');

$insertUser = static function (int $roleId, string $email, string $name) use ($db, &$createdUserIds): int {
    $stmt = $db->prepare(
        "INSERT INTO users (role_id, full_name, email, password, status)
         VALUES (:role_id, :full_name, :email, :password, 'active')"
    );
    $stmt->execute([
        ':role_id' => $roleId,
        ':full_name' => $name,
        ':email' => $email,
        ':password' => password_hash('TestPass123!', PASSWORD_DEFAULT),
    ]);
    $userId = (int) $db->lastInsertId();
    $createdUserIds[] = $userId;

    return $userId;
};

$doctorId = $insertUser($doctorRoleId, 'complaint.image.doctor+' . $suffix . '@telehealth.test', 'Complaint Image Doctor');
$patientId = $insertUser($patientRoleId, 'complaint.image.patient+' . $suffix . '@telehealth.test', 'Complaint Image Patient');
$db->prepare("INSERT INTO doctor (user_id, professional_title, specialization) VALUES (:id, 'Medical Officer', 'General Practice')")
    ->execute([':id' => $doctorId]);
$db->prepare('INSERT INTO patient (user_id) VALUES (:id)')->execute([':id' => $patientId]);

$futureDate = (new DateTimeImmutable('+3 days'))->format('Y-m-d');
$createSlot = static function (string $start, string $end) use ($db, $doctorId, $futureDate, &$createdSlotIds): int {
    $slot = new DoctorAvailability();
    $slot->doctor_id = $doctorId;
    $slot->consultation_date = $futureDate;
    $slot->start_time = $start;
    $slot->end_time = $end;
    $slot->notes = 'Complaint image booking test';
    $slot->status = 'Available';
    $slot->save();
    $id = (int) ($slot->id ?? 0);
    $createdSlotIds[] = $id;

    return $id;
};

$slotWithoutImage = $createSlot('09:00:00', '09:30:00');
$slotWithImage = $createSlot('10:00:00', '10:30:00');
expect_true($slotWithoutImage > 0 && $slotWithImage > 0, 'Test consultation slots were created');

$withoutImage = PatientConsultationBookingService::submitBooking($patientId, $slotWithoutImage, [
    '_token' => $csrfToken,
    'reason' => 'CIMG-TEST booking without an attached image',
]);
expect_true(($withoutImage['success'] ?? false) === true, 'Booking without an image still succeeds');
$withoutId = (int) ($withoutImage['requestId'] ?? 0);
$createdRequestIds[] = $withoutId;
$pathStmt = $db->prepare('SELECT complaint_image_path, reason FROM consultation_requests WHERE id = :id');
$pathStmt->execute([':id' => $withoutId]);
$withoutRow = $pathStmt->fetch(PDO::FETCH_ASSOC) ?: [];
expect_true(($withoutRow['complaint_image_path'] ?? null) === null || ($withoutRow['complaint_image_path'] ?? '') === '', 'Booking without an image stores a null path');

$jpegPack = complaint_test_file(complaint_test_image('jpeg'), 'lesion.jpg', 'image/jpeg');
$tmpFiles[] = $jpegPack['_tmp'];
$withImage = PatientConsultationBookingService::submitBooking(
    $patientId,
    $slotWithImage,
    [
        '_token' => $csrfToken,
        'reason' => 'CIMG-TEST booking with a complaint photo',
    ],
    [ComplaintImageService::FIELD_NAME => $jpegPack[ComplaintImageService::FIELD_NAME]]
);
expect_true(($withImage['success'] ?? false) === true, 'Booking with a valid JPEG succeeds');
$withId = (int) ($withImage['requestId'] ?? 0);
$createdRequestIds[] = $withId;
$pathStmt->execute([':id' => $withId]);
$withRow = $pathStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$storedBookingPath = trim((string) ($withRow['complaint_image_path'] ?? ''));
$createdImagePaths[] = $storedBookingPath;
expect_true($storedBookingPath !== '' && str_starts_with($storedBookingPath, 'complaint_images/' . $patientId . '/'), 'Uploaded JPEG is associated with the consultation request');
$storedAbsolute = Paths::storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storedBookingPath);
expect_true(is_file($storedAbsolute), 'Associated complaint image file exists in private storage');

$owned = \App\Models\ConsultationRequest::findByIdForDoctor($withId, $doctorId);
$foreign = \App\Models\ConsultationRequest::findByIdForDoctor($withId, $patientId);
expect_true(is_array($owned) && ComplaintImageService::existsOnRequest($owned), 'Assigned doctor can load the consultation that includes the complaint image');
expect_true($foreign === null, 'A non-assigned user id cannot load the doctor-scoped consultation');

$cleanup = static function () use ($db, &$createdRequestIds, &$createdSlotIds, &$createdUserIds, &$createdImagePaths): void {
    foreach ($createdImagePaths as $path) {
        ComplaintImageService::deleteStoredPath($path);
    }
    foreach ($createdRequestIds as $requestId) {
        if ($requestId > 0) {
            $db->prepare('DELETE FROM notifications WHERE related_entity_id = :id')->execute([':id' => $requestId]);
            $db->prepare("DELETE FROM audit_logs WHERE entity_type = 'consultation_request' AND entity_id = :id")->execute([':id' => $requestId]);
            $db->prepare('DELETE FROM consultation_requests WHERE id = :id')->execute([':id' => $requestId]);
        }
    }
    foreach ($createdSlotIds as $slotId) {
        if ($slotId > 0) {
            $db->prepare('DELETE FROM doctor_availability WHERE id = :id')->execute([':id' => $slotId]);
        }
    }
    foreach ($createdUserIds as $userId) {
        $db->prepare('DELETE FROM notifications WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM patient WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM doctor WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
    }
};

try {
    $cleanup();
} catch (Throwable $exception) {
    echo 'Cleanup warning: ' . $exception->getMessage() . "\n";
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
