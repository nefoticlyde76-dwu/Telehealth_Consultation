<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Environment;
use App\Core\ErrorHandler;
use App\Core\Session;
use App\Core\Router;

Environment::load(__DIR__ . '/../.env');

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

require_once __DIR__ . '/../routes/web.php';

$router->dispatch();
