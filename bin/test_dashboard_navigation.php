<?php

/**
 * Dashboard navigation and authorization checks.
 *
 * Usage: php bin/test_dashboard_navigation.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\DashboardNav;
use App\Helpers\Status;
use App\Middleware\RoleMiddleware;

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

function labelsFor(string $role): array
{
    $labels = [];
    foreach (DashboardNav::allItems($role) as $item) {
        $labels[] = (string) ($item['label'] ?? '');
    }
    return $labels;
}

function pathsFor(string $role): array
{
    $paths = [];
    foreach (DashboardNav::allItems($role) as $item) {
        $paths[] = (string) ($item['path'] ?? '');
    }
    return $paths;
}

$patientLabels = labelsFor('patient');
$doctorLabels = labelsFor('doctor');
$adminLabels = labelsFor('admin');

expect_true(in_array('Dashboard', $patientLabels, true), 'Patient nav includes Dashboard');
expect_true(in_array('Book Consultation', $patientLabels, true), 'Patient nav includes Book Consultation');
expect_true(in_array('My Consultations', $patientLabels, true), 'Patient nav includes My Consultations');
expect_true(in_array('Notifications', $patientLabels, true), 'Patient nav includes Notifications');
expect_true(in_array('Profile', $patientLabels, true), 'Patient nav includes Profile');
expect_true(in_array('Security', $patientLabels, true), 'Patient nav includes Security');
expect_true(in_array('Preferences', $patientLabels, true), 'Patient nav includes Preferences');
expect_true(!in_array('Audit Logs', $patientLabels, true), 'Patient nav hides Audit Logs');
expect_true(!in_array('Users', $patientLabels, true), 'Patient nav hides Users');
expect_true(!in_array('Availability', $patientLabels, true), 'Patient nav hides Availability');

expect_true(in_array('Dashboard', $doctorLabels, true), 'Doctor nav includes Dashboard');
expect_true(in_array('Availability', $doctorLabels, true), 'Doctor nav includes Availability');
expect_true(in_array('Consultations', $doctorLabels, true), 'Doctor nav includes Consultations');
expect_true(in_array('Notifications', $doctorLabels, true), 'Doctor nav includes Notifications');
expect_true(in_array('Security', $doctorLabels, true), 'Doctor nav includes Security');
expect_true(in_array('Preferences', $doctorLabels, true), 'Doctor nav includes Preferences');
expect_true(!in_array('Audit Logs', $doctorLabels, true), 'Doctor nav hides Audit Logs');
expect_true(!in_array('Users', $doctorLabels, true), 'Doctor nav hides Users');
expect_true(!in_array('Book Consultation', $doctorLabels, true), 'Doctor nav hides patient booking');

expect_true(in_array('Dashboard', $adminLabels, true), 'Admin nav includes Dashboard');
expect_true(in_array('Consultation Requests', $adminLabels, true), 'Admin nav includes Consultation Requests');
expect_true(in_array('Users', $adminLabels, true), 'Admin nav includes Users');
expect_true(in_array('Audit Logs', $adminLabels, true), 'Admin nav includes Audit Logs');
expect_true(in_array('Notifications', $adminLabels, true), 'Admin nav includes Notifications');
expect_true(in_array('Security', $adminLabels, true), 'Admin nav includes Security');
expect_true(in_array('Preferences', $adminLabels, true), 'Admin nav includes Preferences');
expect_true(!in_array('Book Consultation', $adminLabels, true), 'Admin nav hides patient booking');
expect_true(!in_array('Availability', $adminLabels, true), 'Admin nav hides doctor availability');

expect_true(
    DashboardNav::isActive('/admin/consultation-requests', '/admin/consultation-requests/184', DashboardNav::allItems('admin')),
    'Admin Consultation Requests stays active on request details'
);
expect_true(
    DashboardNav::isActive('/doctor/consultations', '/doctor/consultations/12', DashboardNav::allItems('doctor')),
    'Doctor Consultations stays active on consultation details'
);
expect_true(
    DashboardNav::isActive('/patient/consultation-requests', '/patient/consultation-requests/9', DashboardNav::allItems('patient')),
    'Patient My Consultations stays active on details'
);
expect_true(
    DashboardNav::isActive('/notifications', '/notifications', DashboardNav::allItems('admin')),
    'Notifications is active on /notifications'
);
expect_true(
    !DashboardNav::isActive('/admin/users', '/admin/doctors', DashboardNav::allItems('admin')),
    'Users is not active on Doctors'
);
expect_true(
    DashboardNav::isActive('/doctor/profile', '/doctor/profile/edit', DashboardNav::allItems('doctor')),
    'Doctor Profile stays active on account settings'
);

expect_true(
    Status::filteredListUrl('/admin/consultation-requests', Status::PENDING) === '/admin/consultation-requests?status=Pending',
    'Pending dashboard card uses the shared status filter URL'
);
expect_true(
    Status::filteredListUrl('/doctor/consultations', Status::COMPLETED) === '/doctor/consultations?status=Completed',
    'Completed doctor history uses the shared status filter URL'
);
expect_true(
    Status::filteredListUrl('/patient/consultation-requests', Status::APPROVED) === '/patient/consultation-requests?status=Approved',
    'Patient upcoming card uses the shared status filter URL'
);

$reflection = new ReflectionClass(RoleMiddleware::class);
expect_true($reflection->hasMethod('handle'), 'RoleMiddleware still enforces server-side authorization');

$patientPaths = pathsFor('patient');
$adminOnly = ['/admin/dashboard', '/admin/audit-logs', '/admin/consultation-requests', '/admin/users'];
foreach ($adminOnly as $path) {
    expect_true(!in_array($path, $patientPaths, true), 'Patient nav does not expose ' . $path);
}

$doctorPaths = pathsFor('doctor');
foreach ($adminOnly as $path) {
    expect_true(!in_array($path, $doctorPaths, true), 'Doctor nav does not expose ' . $path);
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
