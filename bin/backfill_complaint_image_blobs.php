<?php

/**
 * Copy existing storage/complaint_images/ files into complaint_images.photo_blob.
 *
 * Does not delete disk files. Run after migration 033.
 *
 * Usage: php bin/backfill_complaint_image_blobs.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Services\ComplaintImageService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');

if (!ComplaintImageService::ensureStorage()) {
    fwrite(STDERR, "complaint_images table is not available.\n");
    exit(1);
}

$db = Database::getInstance();
$stmt = $db->query(
    "SELECT id, complaint_image_path
     FROM consultation_requests
     WHERE complaint_image_path IS NOT NULL
       AND TRIM(complaint_image_path) <> ''
     ORDER BY id ASC"
);
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

$migrated = 0;
$skippedMissing = 0;
$skippedExisting = 0;
$skippedInvalid = 0;
$failed = 0;

foreach ($rows as $row) {
    $requestId = (int) ($row['id'] ?? 0);
    $relativePath = (string) ($row['complaint_image_path'] ?? '');
    $result = ComplaintImageService::backfillFromStoredPath($requestId, $relativePath);

    switch ($result) {
        case 'migrated':
            $migrated++;
            echo "MIGRATED  request {$requestId}\n";
            break;
        case 'skipped_missing':
            $skippedMissing++;
            echo "SKIPPED   request {$requestId} (missing file on disk)\n";
            break;
        case 'skipped_existing':
            $skippedExisting++;
            break;
        case 'skipped_invalid':
            $skippedInvalid++;
            echo "SKIPPED   request {$requestId} (invalid path)\n";
            break;
        default:
            $failed++;
            echo "FAILED    request {$requestId}\n";
            break;
    }
}

$total = count($rows);
$skipped = $skippedMissing + $skippedExisting + $skippedInvalid;

echo "\nComplaint image blob backfill\n";
echo "  scanned:           {$total}\n";
echo "  migrated:          {$migrated}\n";
echo "  skipped (missing): {$skippedMissing}\n";
echo "  skipped (existing): {$skippedExisting}\n";
echo "  skipped (invalid): {$skippedInvalid}\n";
echo "  skipped (total):   {$skipped}\n";
echo "  failed:            {$failed}\n";

exit($failed > 0 ? 1 : 0);
