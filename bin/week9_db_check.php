<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;

Environment::load(dirname(__DIR__) . '/.env');

try {
    $pdo = Database::getInstance();
    echo 'DB OK: ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;

    $tables = [
        'users',
        'roles',
        'doctor',
        'patient',
        'consultation_requests',
        'consultation_records',
        'prescriptions',
        'doctor_availability',
        'notifications',
        'audit_logs',
    ];

    foreach ($tables as $table) {
        try {
            $count = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
            echo $table . ': ' . $count . PHP_EOL;
        } catch (Throwable $e) {
            echo $table . ': MISSING OR ERROR — ' . $e->getMessage() . PHP_EOL;
        }
    }

    echo PHP_EOL . 'Roles:' . PHP_EOL;
    foreach ($pdo->query('SELECT id, name FROM roles')->fetchAll(PDO::FETCH_ASSOC) as $role) {
        echo '  ' . $role['id'] . ' ' . $role['name'] . PHP_EOL;
    }

    echo PHP_EOL . 'Users by role/status:' . PHP_EOL;
    $sql = "SELECT r.name AS role_name, u.status, COUNT(*) AS total
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            GROUP BY r.name, u.status
            ORDER BY r.name, u.status";
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo '  ' . $row['role_name'] . ' / ' . $row['status'] . ': ' . $row['total'] . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'DB FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
