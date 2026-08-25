<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Core\Session;
use App\Models\AuditLog;
use App\Services\AuditLogService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');
date_default_timezone_set('Pacific/Port_Moresby');
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

$catalog = AuditLogService::catalog();
expect_true(isset($catalog['login_success']), 'Audit catalog includes login_success');
expect_true(isset($catalog['consultation_approved']), 'Audit catalog includes consultation_approved');
expect_true(isset($catalog['prescription_created']), 'Audit catalog includes prescription_created');
expect_true(isset($catalog['doctor_password_setup_completed']), 'Audit catalog includes doctor_password_setup_completed');

$filters = AuditLogService::normalizeFilters([
    'sort' => 'drop table',
    'date' => 'custom',
    'date_from' => '2026-08-01',
    'date_to' => '2026-08-31',
    'per_page' => 100,
]);
expect_true($filters['sort'] === 'newest', 'Sort whitelist rejects unsafe values');
expect_true((int) $filters['per_page'] === 100, 'Per-page supports 100 rows');

$db = Database::getInstance();
$maxBefore = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();

AuditLogService::record(
    'availability_created',
    'Test audit event creation.',
    AuditLogService::ENTITY_AVAILABILITY,
    999001,
    'success',
    [
        'actor_name' => 'Test Runner',
        'actor_role' => 'admin',
        'subject_name' => 'Test Subject',
        'subject_role' => 'system',
    ]
);

$created = $db->prepare('SELECT * FROM audit_logs WHERE id > :max_before ORDER BY id DESC LIMIT 1');
$created->bindValue(':max_before', $maxBefore, PDO::PARAM_INT);
$created->execute();
$row = $created->fetch(PDO::FETCH_ASSOC) ?: null;

expect_true(is_array($row), 'Audit record created');
if (is_array($row)) {
    $hasExpandedColumns = array_key_exists('event_type', $row);
    if ($hasExpandedColumns) {
        expect_true((string) ($row['event_type'] ?? '') === 'availability_created', 'event_type stored correctly');
        expect_true((string) ($row['entity_type'] ?? '') === AuditLogService::ENTITY_AVAILABILITY, 'entity_type stored correctly');
        expect_true((int) ($row['entity_id'] ?? 0) === 999001, 'entity_id stored correctly');
    } else {
        expect_true((string) ($row['action'] ?? '') === 'availability_created', 'Legacy action stores the event type');
    }
}

$list = AuditLog::findForAdmin(['search' => 'availability_created', 'sort' => 'newest'], 10, 0);
expect_true(is_array($list), 'Admin audit listing query returns rows');

$count = AuditLog::countForAdmin(['action' => 'availability_created']);
expect_true($count >= 1, 'Admin audit count supports action filtering');

$page = AuditLogService::getPageData(['search' => 'availability_created']);
expect_true(isset($page['pagination']['total_items']), 'Page data includes pagination');
expect_true(is_array($page['logs'] ?? null), 'Page data includes logs list');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
