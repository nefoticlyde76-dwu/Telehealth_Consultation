<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\ConsultationRequest;
use App\Models\DoctorAvailability;
use App\Models\User;

class DoctorDashboardService
{
    public static function getDashboardData(int $userId): array
    {
        $user = User::findById($userId);
        $doctor = Doctor::findProfileDetailByUserId($userId);
        $availabilitySummary = DoctorAvailability::getSummaryForDoctor($userId);
        $todaySummary = DoctorAvailability::getTodaySummaryForDoctor($userId);
        $upcomingSlots = DoctorAvailability::getUpcomingForDoctor($userId, 4);
        $weeklySchedule = DoctorAvailability::getDailyScheduleCountsForDoctor($userId, 7);
        $recentApprovedAppointments = ConsultationRequest::countApprovedRecentlyForDoctor($userId, 7);
        $upcomingApprovedCount = ConsultationRequest::countUpcomingApprovedForDoctor($userId);
        $upcomingApprovedAppointments = ConsultationRequest::findUpcomingApprovedForDoctor($userId, 5);
        $consultationSummary = ConsultationRequest::getDoctorStatusSummary($userId);

        $hasProfilePhoto = !empty($doctor['profile_photo_path'] ?? '');
        $hasSignature = !empty($doctor['signature_path'] ?? '');

        return [
            'user' => $user,
            'doctor' => $doctor,
            'stats' => [
                [
                    'label' => "Today's Schedule",
                    'value' => (string) ($todaySummary['total_today_slots'] ?? 0),
                    'icon' => 'bi-calendar-date',
                    'description' => 'Consultation slots currently scheduled on your calendar for today.',
                ],
                [
                    'label' => 'Approved Appointments',
                    'value' => (string) ($consultationSummary['approved_appointments'] ?? 0),
                    'icon' => 'bi-check2-circle',
                    'description' => 'Appointments approved by administration and assigned to your consultation workflow.',
                ],
                [
                    'label' => 'Upcoming Consultations',
                    'value' => (string) ($consultationSummary['upcoming_consultations'] ?? 0),
                    'icon' => 'bi-clock-history',
                    'description' => 'Approved consultations scheduled from today onward.',
                ],
                [
                    'label' => 'New Approved Appointments',
                    'value' => (string) $recentApprovedAppointments,
                    'icon' => 'bi-bell',
                    'description' => 'Recently approved consultation requests assigned to your clinician schedule.',
                ],
            ],
            'quickActions' => [
                [
                    'title' => 'View Consultations',
                    'description' => 'Review upcoming, approved, and completed consultations in one clinician view.',
                    'icon' => 'bi-clipboard2-pulse',
                    'status' => 'Week 5',
                    'url' => '/doctor/consultations',
                    'action_label' => 'Open Consultations',
                ],
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
                    'title' => 'Approved appointments visible',
                    'description' => 'Approved consultations now flow through to your clinician dashboard and consultation workspace.',
                    'meta' => $recentApprovedAppointments . ' new approvals',
                ],
                [
                    'title' => $hasProfilePhoto && $hasSignature ? 'Clinical identity assets complete' : 'Clinical identity assets need review',
                    'description' => $hasProfilePhoto && $hasSignature
                        ? 'Your profile photo and signature are ready for future clinical documentation workflows.'
                        : 'Complete your profile photo and signature to keep your clinician workspace fully prepared.',
                    'meta' => $hasProfilePhoto && $hasSignature ? 'Profile readiness confirmed' : 'Profile update recommended',
                ],
            ],
            'emptyState' => [
                'icon' => 'bi-clipboard2-pulse',
                'title' => 'No clinical activity to show yet',
                'description' => 'Your availability calendar is now active, and future consultation workflows will build on these scheduled slots.',
            ],
            'todaySummary' => $todaySummary,
            'upcomingSlots' => $upcomingSlots,
            'weeklySchedule' => $weeklySchedule,
            'availabilitySummary' => $availabilitySummary,
            'upcomingApprovedAppointments' => $upcomingApprovedAppointments,
            'recentApprovedAppointmentCount' => $recentApprovedAppointments,
            'upcomingApprovedAppointmentCount' => $upcomingApprovedCount,
            'consultationSummary' => $consultationSummary,
            'assetReadiness' => [
                'has_profile_photo' => $hasProfilePhoto,
                'has_signature' => $hasSignature,
            ],
        ];
    }
}
