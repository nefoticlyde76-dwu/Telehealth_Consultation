<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AuthService;

class DoctorController extends Controller
{
    public function dashboard(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'doctor') {
            \App\Helpers\Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        $this->render('doctor/dashboard', [
            'title' => 'Doctor Dashboard | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'doctor',
            'dashboardRoleLabel' => 'Doctor Dashboard',
            'dashboardTitle' => 'Doctor Dashboard',
            'dashboardDescription' => 'A professional workspace for clinical availability and care visibility.',
            'sidebarItems' => [
                ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
            ],
            'welcomeMessage' => 'Your clinician workspace is ready to support availability management, appointment review, and consultation delivery as modules are activated.',
            'focusTitle' => 'Clinical workspace ready',
            'focusDescription' => 'Doctor availability, bookings, and records will connect to this dashboard in upcoming sprints.',
            'stats' => [
                ['label' => 'Availability Slots', 'value' => '0', 'icon' => 'bi-clock-history', 'description' => 'Prepared for doctor availability management in the next module.'],
                ['label' => 'Pending Consultations', 'value' => '0', 'icon' => 'bi-clipboard-check', 'description' => 'Tracks future consultation requests requiring review.'],
                ['label' => 'Patient Notes Ready', 'value' => '0', 'icon' => 'bi-file-medical', 'description' => 'Reserved for consultation record summaries.'],
                ['label' => 'Account Status', 'value' => 'Active', 'icon' => 'bi-person-check', 'description' => 'Role-based access is active for this clinician account.'],
            ],
            'quickActions' => [
                ['title' => 'Review Dashboard Access', 'description' => 'Confirm secure login and role-based routing into the doctor workspace.', 'icon' => 'bi-shield-lock', 'status' => 'Available now'],
                ['title' => 'Prepare Availability Management', 'description' => 'The dashboard is structured for scheduling visibility and time-slot controls.', 'icon' => 'bi-calendar-week', 'status' => 'Week 3 ready'],
                ['title' => 'Track Patient Requests', 'description' => 'Pending consultation widgets will plug into this layout in future milestones.', 'icon' => 'bi-inboxes', 'status' => 'Awaiting data source'],
                ['title' => 'Access Profile Controls', 'description' => 'The profile menu is available from the top navigation area.', 'icon' => 'bi-person-circle', 'status' => 'Available now'],
            ],
            'recentActivity' => [
                ['title' => 'Doctor session authenticated', 'description' => 'Role protection and dashboard rendering are functioning for doctor users.', 'meta' => 'Current session'],
                ['title' => 'Availability integration pending', 'description' => 'Availability summaries will appear here after the Week 3 module is completed.', 'meta' => 'Upcoming sprint'],
                ['title' => 'Clinical workload panel prepared', 'description' => 'The current layout reserves space for consultation and records monitoring.', 'meta' => 'Layout ready'],
            ],
            'emptyState' => [
                'icon' => 'bi-calendar-heart',
                'title' => 'No clinical activity to show yet',
                'description' => 'Availability slots, pending requests, and consultation updates will appear here once doctor scheduling functionality is active.',
            ],
        ], 'layouts/dashboard');
    }
}
