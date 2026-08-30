<?php

/**
 * One-time repair: release Google identity keys from already-deleted users.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Models\User;

Environment::load(dirname(__DIR__) . '/.env');
$db = Database::getInstance();

$ids = $db->query(
    "SELECT id FROM users
     WHERE status = 'deleted'
       AND google_sub IS NOT NULL
       AND google_sub <> ''"
)->fetchAll(PDO::FETCH_COLUMN);

$released = 0;
foreach ($ids as $id) {
    if (User::releaseGoogleIdentityIfDeleted((int) $id)) {
        $released++;
    }
}

echo 'deleted_google_identities_released=' . $released . PHP_EOL;
