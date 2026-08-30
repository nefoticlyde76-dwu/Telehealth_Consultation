<?php

$siblingAutoload = __DIR__ . '/vendor/autoload.php';
$parentAutoload = __DIR__ . '/../vendor/autoload.php';

if (is_file($siblingAutoload)) {
    $projectRoot = __DIR__;
} elseif (is_file($parentAutoload)) {
    $projectRoot = dirname(__DIR__);
} else {
    throw new RuntimeException(
        'Composer autoload could not be found. Checked '
        . $siblingAutoload . ' and ' . $parentAutoload
        . '. Run composer install on the server.'
    );
}

$autoload = $projectRoot . '/vendor/autoload.php';
require_once $autoload;

use App\Config\Environment;
use App\Core\ErrorHandler;
use App\Core\Session;
use App\Core\Router;

Environment::load($projectRoot . '/.env');

/**
 * Application-wide default timezone.
 *
 * The MBPHA TeleHealth Consultation System operates in Papua New Guinea,
 * so we pin the runtime default timezone to Pacific/Port_Moresby (UTC+10)
 * for all date(), strtotime(), new DateTime(), Helper::formatDate(),
 * dashboard "today" computations, and availability-window comparisons.
 *
 * This also affects the two resolveAppointmentTimezone() helpers in
 * VideoConsultationService and ConsultationRoom, because they prefer
 * ini_get('date.timezone') first — now guaranteed to be PNG local time.
 *
 * We set this AFTER Environment::load() so a future APP_TIMEZONE env
 * override could be slotted in here without touching the rest of the
 * codebase.  Until then, PNG is the authoritative default.
 */
$appDefaultTimezone = trim((string) (Environment::get('APP_TIMEZONE') ?? ''));
if ($appDefaultTimezone === '') {
    $appDefaultTimezone = 'Pacific/Port_Moresby';
}
if (!@date_default_timezone_set($appDefaultTimezone)) {
    date_default_timezone_set('Pacific/Port_Moresby');
}

ErrorHandler::register();
Session::start();

$router = new Router();

require_once $projectRoot . '/routes/web.php';

$router->dispatch();
