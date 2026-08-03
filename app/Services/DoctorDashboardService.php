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

        return [
            'user' => $user,
            'doctor' => $doctor,
            'stats' => [
                [
                    'label' => 'Total Available Slots',
                    'value' => (string) ($availabilitySummary['available_slots'] ?? 0),
                    'icon' => 'bi-calendar2-check',
                    'description' => 'Open consultation slots that are ready for future patient booking workflows.',
                ],
                [
                    'label' => 'Total Booked Slots',
                    'value' => (string) ($availabilitySummary['booked_slots'] ?? 0),
                    'icon' => 'bi-journal-check',
                    'description' => 'Consultation slots currently marked as booked in the scheduling module.',
                ],
                [
                    'label' => 'Upcoming Consultations',
                    'value' => (string) ($availabilitySummary['upcoming_consultations'] ?? 0),
                    'icon' => 'bi-clock-history',
                    'description' => 'Future booked consultations scheduled from today onward.',
                ],
                [
                    'label' => 'Completed Consultations',
                    'value' => (string) ($availabilitySummary['completed_consultations'] ?? 0),
                    'icon' => 'bi-clipboard2-pulse',
                    'description' => 'Booked consultations with dates earlier than today.',
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
                [
                    'title' => 'Upload Clinical Assets',
                    'description' => 'Keep your profile photo and digital signature current for future consultation documentation.',
                    'icon' => $hasProfilePhoto && $hasSignature ? 'bi-check2-circle' : 'bi-cloud-arrow-up',
                    'status' => $hasProfilePhoto && $hasSignature ? 'Up to date' : 'Action recommended',
                    'url' => '/doctor/profile/edit',
                    'action_label' => 'Update Assets',
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
}
