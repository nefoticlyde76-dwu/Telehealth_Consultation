<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Services\AuthService;

class PatientController extends Controller
{
    public function dashboard(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'patient') {
            \App\Helpers\Helper::redirect('/login');
            return;
        }

        $user = AuthService::getUser();

        $this->render('patient/dashboard', [
            'title' => 'Patient Dashboard | MBPHA TeleHealth Consultation System',
            'user' => $user,
            'dashboardRole' => 'patient',
            'dashboardRoleLabel' => 'Patient Dashboard',
            'dashboardTitle' => 'Patient Dashboard',
            'dashboardDescription' => 'Your secure home for upcoming digital care interactions.',
            'sidebarItems' => [
                ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
            ],
            'welcomeMessage' => 'Your account is active and ready for consultation booking, care updates, and future appointment history.',
            'focusTitle' => 'Patient access is ready',
            'focusDescription' => 'As booking and consultation modules come online, this workspace will display live healthcare activity.',
            'statusMessage' => Session::getFlash('status'),
            'stats' => [
                ['label' => 'Upcoming Consultations', 'value' => '0', 'icon' => 'bi-calendar2-check', 'description' => 'Live appointment data will appear here once booking is enabled.'],
                ['label' => 'Pending Requests', 'value' => '0', 'icon' => 'bi-hourglass-split', 'description' => 'Tracks consultation requests awaiting action.'],
                ['label' => 'Messages Requiring Review', 'value' => '0', 'icon' => 'bi-chat-dots', 'description' => 'Reserved for future communication updates.'],
                ['label' => 'Account Status', 'value' => 'Active', 'icon' => 'bi-shield-check', 'description' => 'Your patient account is authenticated and available.'],
            ],
            'quickActions' => [
                ['title' => 'Review Profile Access', 'description' => 'Confirm that your account and dashboard access are working correctly.', 'icon' => 'bi-person-badge', 'status' => 'Available now'],
                ['title' => 'Prepare For Booking', 'description' => 'This area is ready to connect to the booking workflow in the next module.', 'icon' => 'bi-journal-check', 'status' => 'Ready for integration'],
                ['title' => 'Monitor Consultation Updates', 'description' => 'Recent activity cards are structured for future appointment and consultation history.', 'icon' => 'bi-clipboard2-pulse', 'status' => 'Structured placeholder'],
                ['title' => 'Contact Support', 'description' => 'Use the public contact channel if you need onboarding assistance.', 'icon' => 'bi-life-preserver', 'status' => 'Available now'],
            ],
            'recentActivity' => [
                ['title' => 'Dashboard access confirmed', 'description' => 'Your authenticated patient dashboard is available and role protected.', 'meta' => 'Current session'],
                ['title' => 'Booking workflow pending', 'description' => 'Appointment booking data will populate once the patient booking module is implemented.', 'meta' => 'Prepared for Week 4'],
                ['title' => 'Consultation history placeholder', 'description' => 'Historical care records will appear here when consultation records become available.', 'meta' => 'Future module integration'],
            ],
            'emptyState' => [
                'icon' => 'bi-calendar-plus',
                'title' => 'No consultation activity yet',
                'description' => 'Once appointment booking and clinical workflows are enabled, this dashboard will display upcoming consultations and related records.',
            ],
        ], 'layouts/dashboard');
    }
}
