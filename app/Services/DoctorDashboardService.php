<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\DoctorAvailability;
use App\Models\User;

class DoctorDashboardService
{
    public static function getDashboardData(int $userId): array
    {
        $user = User::findById($userId);
        $doctor = Doctor::findProfileDetailByUserId($userId);
        $availabilitySummary = DoctorAvailability::getSummaryForDoctor($userId);

        $hasProfilePhoto = !empty($doctor['profile_photo_path'] ?? '');
        $hasSignature = !empty($doctor['signature_path'] ?? '');
        $profileCompleteness = self::calculateProfileCompleteness($doctor);

        return [
            'user' => $user,
            'doctor' => $doctor,
            'stats' => [
                [
                    'label' => 'Profile Completeness',
                    'value' => $profileCompleteness . '%',
                    'icon' => 'bi-clipboard-check',
                    'description' => 'Completeness of phone number, specialization, photo, and signature for clinical readiness.',
                ],
                [
                    'label' => 'Profile Photo',
                    'value' => $hasProfilePhoto ? 'Uploaded' : 'Not uploaded',
                    'icon' => $hasProfilePhoto ? 'bi-person-bounding-box' : 'bi-person',
                    'description' => 'A professional profile photo improves clinician trust and identity verification.',
                ],
                [
                    'label' => 'Availability Slots',
                    'value' => (string) ($availabilitySummary['upcoming_slots'] ?? 0),
                    'icon' => 'bi-calendar-week',
                    'description' => 'Upcoming consultation slots currently scheduled in your availability calendar.',
                ],
                [
                    'label' => 'Account Status',
                    'value' => ucfirst((string) ($user?->status ?? 'active')),
                    'icon' => ($user?->status ?? 'active') === 'active' ? 'bi-shield-check' : 'bi-shield-exclamation',
                    'description' => 'Account status determines whether secure login is permitted.',
                ],
            ],
            'quickActions' => [
                [
                    'title' => 'View My Profile',
                    'description' => 'Review your clinician identity, specialization, and uploaded assets.',
                    'icon' => 'bi-person-vcard',
                    'status' => 'Available now',
                    'url' => '/doctor/profile',
                    'action_label' => 'Open Profile',
                ],
                [
                    'title' => 'Edit Profile Settings',
                    'description' => 'Update phone number, specialization, profile photo, signature, and password.',
                    'icon' => 'bi-person-gear',
                    'status' => 'Available now',
                    'url' => '/doctor/profile/edit',
                    'action_label' => 'Edit Profile',
                ],
                [
                    'title' => 'Manage Availability',
                    'description' => 'Create, review, update, and delete consultation slots from your clinician workspace.',
                    'icon' => 'bi-calendar-week',
                    'status' => 'Available now',
                    'url' => '/doctor/availability',
                    'action_label' => 'Open Availability',
                ],
                [
                    'title' => 'Secure Session Controls',
                    'description' => 'Use the top navigation to manage session logout securely.',
                    'icon' => 'bi-shield-lock',
                    'status' => 'Active',
                ],
            ],
            'recentActivity' => [
                [
                    'title' => 'Doctor session authenticated',
                    'description' => 'Role protection and dashboard rendering are functioning for doctor users.',
                    'meta' => 'Current session',
                ],
                [
                    'title' => 'Availability scheduling ready',
                    'description' => 'Doctor availability management now supports creation, filtering, editing, and deletion with validation.',
                    'meta' => 'Week 4 Day 2',
                ],
            ],
            'emptyState' => [
                'icon' => 'bi-clipboard2-pulse',
                'title' => 'No clinical activity to show yet',
                'description' => 'Your availability calendar is now active, and future consultation workflows will build on these scheduled slots.',
            ],
        ];
    }

    private static function calculateProfileCompleteness(?array $doctor): int
    {
        if ($doctor === null) {
            return 0;
        }

        $checks = [
            !empty($doctor['phone'] ?? ''),
            !empty($doctor['specialization'] ?? ''),
            !empty($doctor['profile_photo_path'] ?? ''),
            !empty($doctor['signature_path'] ?? ''),
        ];

        $completed = 0;

        foreach ($checks as $check) {
            if ($check) {
                $completed++;
            }
        }

        return (int) round(($completed / count($checks)) * 100);
    }
}
