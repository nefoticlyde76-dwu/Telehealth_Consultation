<?php

/**
 * Optional scheduled cleanup for expired unbooked availability slots.
 *
 * The web application also runs this sweep whenever availability or booking
 * pages are accessed. This script is an extra safety net for cron / Task Scheduler.
 *
 * Usage: php bin/expire_past_slots.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Helpers\Helper;
use App\Services\SlotExpirationService;

Environment::load(dirname(__DIR__) . '/.env');

$appDefaultTimezone = trim((string) (Environment::get('APP_TIMEZONE') ?? ''));
if ($appDefaultTimezone === '') {
    $appDefaultTimezone = 'Pacific/Port_Moresby';
}
if (!@date_default_timezone_set($appDefaultTimezone)) {
    date_default_timezone_set('Pacific/Port_Moresby');
}

SlotExpirationService::resetRequestGuard();
$expired = SlotExpirationService::sweep();

echo 'Expired unbooked slots processed: ' . $expired . "\n";
echo 'Server time: ' . SlotExpirationService::nowDatetime() . ' (' . Helper::appTimezoneLabel() . ")\n";
