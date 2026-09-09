<?php

/**
 * PHPUnit bootstrap.
 *
 * Fixtures follow the existing bin/test_*.php pattern: isolated rows in the
 * configured MySQL database (usually telehealth_db) with unique
 * @telehealth.test emails, then teardown deletes those rows. A separate
 * test schema is not required.
 *
 * Optional: copy .env to .env.testing only if you need a dedicated database;
 * values already present in the process environment are left unchanged.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Session;

Environment::load($root . '/.env.testing');
Environment::load($root . '/.env');

$timezone = trim((string) (Environment::get('APP_TIMEZONE') ?? ''));
if ($timezone === '' || !@date_default_timezone_set($timezone)) {
    date_default_timezone_set('Pacific/Port_Moresby');
}

$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'off';

if (session_status() === PHP_SESSION_NONE) {
    Session::start();
}
