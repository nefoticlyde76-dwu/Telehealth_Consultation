<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AuthService;

class AdminController extends Controller
{
    public function dashboard(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'admin') {
            \App\Helpers\Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        $this->render('admin/dashboard', [
            'title' => 'Admin Dashboard | TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'admin',
            'dashboardRoleLabel' => 'Administrator Dashboard',
            'dashboardTitle' => 'Administrator Dashboard',
            'dashboardDescription' => 'Operational visibility for secure platform administration.',
            'sidebarItems' => [
                ['path' => '/admin/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
            ],
            'welcomeMessage' => 'This administrator workspace is ready to support oversight, account governance, and future operational analytics.',
            'focusTitle' => 'Administrative control point',
            'focusDescription' => 'Booking supervision and broader operational tooling will extend from this foundation in later weeks.',
            'stats' => [
                ['label' => 'Active Users Visible', 'value' => 'Ready', 'icon' => 'bi-people', 'description' => 'Prepared for future user monitoring and management visibility.'],
                ['label' => 'Booking Oversight', 'value' => '0', 'icon' => 'bi-kanban', 'description' => 'Reserved for appointment and scheduling review metrics.'],
                ['label' => 'System Alerts', 'value' => '0', 'icon' => 'bi-bell', 'description' => 'Notification placeholders are available for later system events.'],
                ['label' => 'Admin Session', 'value' => 'Secure', 'icon' => 'bi-shield-lock', 'description' => 'Administrative access is protected by the authentication layer.'],
            ],
            'quickActions' => [
                ['title' => 'Review Platform Access', 'description' => 'Confirm that role-based routing and administrator login are functioning correctly.', 'icon' => 'bi-person-lock', 'status' => 'Available now'],
                ['title' => 'Prepare Booking Oversight', 'description' => 'The dashboard structure is ready for booking management cards and tables.', 'icon' => 'bi-table', 'status' => 'Week 5 ready'],
                ['title' => 'Monitor Operational Health', 'description' => 'Top navigation placeholders are prepared for notifications and profile controls.', 'icon' => 'bi-activity', 'status' => 'Structured placeholder'],
                ['title' => 'Use Secure Logout', 'description' => 'Administrative sessions can be ended safely from the profile dropdown.', 'icon' => 'bi-box-arrow-right', 'status' => 'Available now'],
            ],
            'recentActivity' => [
                ['title' => 'Administrator dashboard enabled', 'description' => 'Authenticated admins are redirected correctly into the shared dashboard shell.', 'meta' => 'Current session'],
                ['title' => 'Operational widgets pending', 'description' => 'Administrative booking and system management data will populate in later weeks.', 'meta' => 'Future sprint'],
                ['title' => 'Design system aligned', 'description' => 'The administrator workspace uses the same premium layout system as other roles.', 'meta' => 'Consistent UI'],
            ],
            'emptyState' => [
                'icon' => 'bi-clipboard-data',
                'title' => 'Operational data will appear here later',
                'description' => 'Administrative insights, booking oversight, and broader workflow analytics will populate this area as management modules are implemented.',
            ],
        ], 'layouts/dashboard');
    }
}
