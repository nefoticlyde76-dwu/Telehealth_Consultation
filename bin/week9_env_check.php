<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;

Environment::load(dirname(__DIR__) . '/.env');

$appUrl = (string) Environment::get('APP_URL', '');
$debug = (string) Environment::get('APP_DEBUG', '');
$dailyKey = trim((string) Environment::get('DAILY_API_KEY', ''));
$dailyDomain = trim((string) Environment::get('DAILY_DOMAIN', ''));
$mailer = (string) Environment::get('MAIL_MAILER', '');

echo 'APP_URL=' . $appUrl . PHP_EOL;
echo 'APP_DEBUG=' . $debug . PHP_EOL;
echo 'DAILY_API_KEY=' . ($dailyKey !== '' ? 'SET(' . strlen($dailyKey) . ')' : 'EMPTY') . PHP_EOL;
echo 'DAILY_DOMAIN=' . ($dailyDomain !== '' ? $dailyDomain : 'EMPTY') . PHP_EOL;
echo 'MAIL_MAILER=' . $mailer . PHP_EOL;
echo 'GD=' . (extension_loaded('gd') ? 'yes' : 'no') . PHP_EOL;
echo 'CURL=' . (extension_loaded('curl') ? 'yes' : 'no') . PHP_EOL;
