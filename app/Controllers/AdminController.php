<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\AuthService;
use App\Services\AdminUserService;

class AdminController extends Controller
{
    public function dashboard(): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $dashboardData = AdminUserService::getDashboardData();
        $summary = $dashboardData['summary'];

        $this->render('admin/dashboard', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'Administrator Dashboard | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'Administrator Dashboard',
                'dashboardDescription' => 'Operational visibility for secure platform administration.',
            ]),
            [
                'welcomeMessage' => 'This administrator workspace now includes the first user management foundation for secure account oversight.',
                'focusTitle' => 'User governance foundation',
                'focusDescription' => 'Search, filtering, detail review, and user visibility are now available while broader administration features continue in later weeks.',
                'stats' => $dashboardData['stats'],
                'quickActions' => $dashboardData['quickActions'],
                'recentActivity' => $dashboardData['recentActivity'],
                'emptyState' => [
                    'icon' => 'bi-people',
                    'title' => 'Administrative user management is now available',
                    'description' => 'Use the user management page to review registered accounts, filter by role or status, and inspect individual account details securely.',
                ],
                'userSummary' => $summary,
                'latestUsers' => $dashboardData['latestUsers'],
            ]
        ), 'layouts/dashboard');
    }

    public function users(): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $pageData = AdminUserService::getUserManagementPageData($_GET);

        $this->render('admin/users/index', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'User Management | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'User Management',
                'dashboardDescription' => 'Review, search, and filter registered platform accounts securely.',
            ]),
            [
                'filters' => $pageData['filters'],
                'users' => $pageData['users'],
                'summary' => $pageData['summary'],
                'pagination' => $pageData['pagination'],
                'roleOptions' => $pageData['roleOptions'],
                'statusOptions' => $pageData['statusOptions'],
                'statusMessage' => Session::getFlash('status'),
            ]
        ), 'layouts/dashboard');
    }

    public function showUser(string $id): void
    {
        $user = $this->requireAdminUser();

        if ($user === null) {
            return;
        }

        $userId = (int) $id;
        $managedUser = AdminUserService::getUserDetail($userId);

        if ($managedUser === null) {
            Session::flash('status', [
                'type' => 'warning',
                'message' => 'The requested user record could not be found.',
            ]);
            Helper::redirect('/admin/users');
        }

        $this->render('admin/users/show', array_merge(
            $this->getAdminViewData($user, [
                'title' => 'User Details | MBPHA TeleHealth Consultation System',
                'dashboardTitle' => 'User Details',
                'dashboardDescription' => 'Review role, status, and account profile data for a specific user.',
            ]),
            [
                'managedUser' => $managedUser,
            ]
        ), 'layouts/dashboard');
    }

    private function requireAdminUser(): ?\App\Models\User
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'admin') {
            Helper::redirect('/login');
            return null;
        }

        $user = AuthService::getUser();

        if ($user === null) {
            Helper::redirect('/login');
            return null;
        }

        return $user;
    }

    private function getAdminViewData(\App\Models\User $user, array $overrides = []): array
    {
        return array_merge([
            'user' => $user,
            'dashboardRole' => 'admin',
            'dashboardRoleLabel' => 'Administrator Dashboard',
            'sidebarItems' => [
                ['path' => '/admin/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
                ['path' => '/admin/users', 'label' => 'User Management', 'icon' => 'bi-people-fill'],
            ],
            'sidebarStatusTitle' => 'Week 3 User Governance',
            'sidebarStatusDescription' => 'Administrator user visibility, filtering, and detail review are now active.',
        ], $overrides);
    }
}
