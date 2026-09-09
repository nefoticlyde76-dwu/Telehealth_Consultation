<?php

declare(strict_types=1);

namespace Tests\Authorization;

use App\Controllers\DoctorController;
use App\Controllers\PatientController;
use App\Helpers\Helper;
use App\Models\ConsultationRecord;
use App\Models\ConsultationRequest;
use App\Models\Prescription;
use App\Services\DoctorClinicalDocumentationService;
use App\Services\DoctorPrescriptionService;
use App\Services\PatientClinicalRecordService;
use App\Services\PatientConsultationBookingService;
use Tests\Support\DatabaseTestCase;

/**
 * Direct service/controller authorization boundaries for clinical data.
 */
final class AuthorizationBoundaryTest extends DatabaseTestCase
{
    private int $doctorA;
    private int $doctorB;
    private int $doctorC;
    private int $patientA;
    private int $patientB;
    private int $requestBFinal;
    private int $requestBDraft;
    private int $requestA;
    private int $requestPatientB;
    private int $recordBFinal;
    private int $recordBDraft;
    private int $recordPatientB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctorA = $this->createDoctor('Authz Doctor A');
        $this->doctorB = $this->createDoctor('Authz Doctor B');
        $this->doctorC = $this->createDoctor('Authz Doctor C');
        $this->patientA = $this->createPatient('Authz Patient A');
        $this->patientB = $this->createPatient('Authz Patient B');

        $this->requestBFinal = $this->createApprovedRequest($this->patientA, $this->doctorB, 'AUTHZ doctor B finalized');
        $this->requestBDraft = $this->createApprovedRequest($this->patientA, $this->doctorB, 'AUTHZ doctor B draft');
        $this->requestA = $this->createApprovedRequest($this->patientA, $this->doctorA, 'AUTHZ doctor A relationship');
        $this->requestPatientB = $this->createApprovedRequest($this->patientB, $this->doctorB, 'AUTHZ patient B visit');

        $draft = $this->ensureDraft($this->requestBDraft, $this->doctorB, $this->patientA, 'AUTHZ hidden draft complaint');
        $this->assertIsArray($draft);
        $this->assertSame(ConsultationRecord::STATUS_DRAFT, (string) ($draft['record_status'] ?? ''));
        $this->recordBDraft = (int) ($draft['id'] ?? 0);

        $finalB = $this->finalizeConsultation($this->requestBFinal, $this->doctorB, $this->patientA, [
            'chief_complaint' => 'Fever',
            'symptoms' => 'Three days of fever',
            'clinical_findings' => 'Febrile',
            'diagnosis' => 'AUTHZ-B-DX malaria',
            'treatment_plan' => 'AUTHZ-B-TX AL',
            'additional_notes' => '',
        ]);
        $this->assertTrue(($finalB['success'] ?? false) === true);
        $this->recordBFinal = (int) ($finalB['record']['id'] ?? 0);
        $this->issuePrescription($this->recordBFinal, $this->doctorB, $this->patientA, [[
            'medication_name' => 'Coartem',
            'dosage' => '80/480 mg',
            'frequency' => 'BD',
            'duration' => '3 days',
            'quantity' => '24',
        ]]);

        $this->finalizeConsultation($this->requestA, $this->doctorA, $this->patientA, [
            'chief_complaint' => 'Cough',
            'symptoms' => 'Dry cough',
            'clinical_findings' => 'Afebrile',
            'diagnosis' => 'AUTHZ-A-DX URTI',
            'treatment_plan' => 'Supportive care',
            'additional_notes' => '',
        ]);

        $finalPatientB = $this->finalizeConsultation($this->requestPatientB, $this->doctorB, $this->patientB, [
            'chief_complaint' => 'Headache',
            'symptoms' => 'Frontal headache',
            'clinical_findings' => 'Alert',
            'diagnosis' => 'AUTHZ-PB-DX tension headache',
            'treatment_plan' => 'Paracetamol',
            'additional_notes' => '',
        ]);
        $this->recordPatientB = (int) ($finalPatientB['record']['id'] ?? 0);
        $this->issuePrescription($this->recordPatientB, $this->doctorB, $this->patientB, [[
            'medication_name' => 'Paracetamol',
            'dosage' => '500 mg',
            'frequency' => 'TDS',
            'duration' => '3 days',
            'quantity' => '9',
        ]]);
    }

    public function testDoctorACannotReadDoctorBConsultationRequestById(): void
    {
        $this->assertNull(ConsultationRequest::findByIdForDoctor($this->requestBFinal, $this->doctorA));
        $this->assertNull(PatientConsultationBookingService::getRequestDetail($this->doctorA, $this->requestBFinal));
    }

    public function testDoctorACannotReadDoctorBClinicalRecordById(): void
    {
        $this->assertNull(ConsultationRecord::findByConsultationForDoctor($this->requestBFinal, $this->doctorA));
        $this->assertNull(PatientClinicalRecordService::getHistoricalRecordForDoctor($this->doctorA, $this->requestBFinal));
        $this->assertNull(PatientClinicalRecordService::getPrintableDocumentForDoctor($this->doctorA, $this->requestBFinal, 'record'));
    }

    public function testDoctorACannotReadDoctorBPrescriptionsById(): void
    {
        $this->assertSame([], Prescription::findByRecordForDoctor($this->recordBFinal, $this->doctorA));
        $this->assertNull(DoctorPrescriptionService::getPrescriptionPageForDoctor($this->doctorA, $this->requestBFinal));
        $this->assertNull(PatientClinicalRecordService::getPrintableDocumentForDoctor($this->doctorA, $this->requestBFinal, 'prescription'));
    }

    public function testDoctorACannotSaveDraftOnDoctorBConsultation(): void
    {
        $result = DoctorClinicalDocumentationService::saveDraft(
            $this->doctorA,
            $this->requestBFinal,
            $this->csrfToken(),
            ['chief_complaint' => 'should not save']
        );

        $this->assertFalse($result['ok'] ?? true);
        $this->assertSame(404, (int) ($result['http_code'] ?? 0));
    }

    public function testDoctorAControllerRedirectsAwayFromDoctorBConsultation(): void
    {
        $this->loginAs($this->doctorA, 'doctor');
        $html = $this->captureOutput(function (): void {
            (new DoctorController())->showConsultationDetails((string) $this->requestBFinal);
        });

        $this->assertSame('', $html);
        $this->assertNotNull(Helper::$lastRedirect);
        $this->assertStringContainsString('/doctor/consultations', (string) Helper::$lastRedirect);
        $this->assertStringNotContainsString('AUTHZ-B-DX', $html);
    }

    public function testPatientACannotReadPatientBConsultationRequest(): void
    {
        $this->assertNull(ConsultationRequest::findByIdForPatient($this->requestPatientB, $this->patientA));
        $this->assertNull(PatientConsultationBookingService::getRequestDetail($this->patientA, $this->requestPatientB));
    }

    public function testPatientACannotReadPatientBClinicalRecordOrPrescription(): void
    {
        $this->assertNull(ConsultationRecord::findByConsultationForPatient($this->requestPatientB, $this->patientA));
        $this->assertNull(PatientClinicalRecordService::getCompletedRecordForPatient($this->patientA, $this->requestPatientB));
        $this->assertSame([], Prescription::findByRecordForPatient($this->recordPatientB, $this->patientA));
        $this->assertNull(PatientClinicalRecordService::getPrintableDocumentForPatient($this->patientA, $this->requestPatientB, 'record'));
        $this->assertNull(PatientClinicalRecordService::getPrintableDocumentForPatient($this->patientA, $this->requestPatientB, 'prescription'));
    }

    public function testPatientAControllerRedirectsAwayFromPatientBConsultation(): void
    {
        $this->loginAs($this->patientA, 'patient');
        $html = $this->captureOutput(function (): void {
            (new PatientController())->showConsultationRequest((string) $this->requestPatientB);
        });

        $this->assertSame('', $html);
        $this->assertNotNull(Helper::$lastRedirect);
        $this->assertStringContainsString('/patient/consultation-requests', (string) Helper::$lastRedirect);
        $this->assertStringNotContainsString('AUTHZ-PB-DX', $html);
    }

    public function testDoctorWhoNeverTreatedPatientIsDeniedCrossDoctorPath(): void
    {
        $this->assertFalse(ConsultationRequest::doctorHasRelationshipWithPatient($this->doctorC, $this->patientA));
        $this->assertNull(PatientClinicalRecordService::getPriorFinalizedRecordsForDoctor($this->doctorC, $this->patientA));
        $this->assertNull(PatientClinicalRecordService::getHistoricalRecordForDoctor($this->doctorC, $this->requestBFinal));
        $this->assertNull(ConsultationRequest::findByIdForDoctor($this->requestBFinal, $this->doctorC));
    }

    public function testTreatingDoctorCanReadOtherDoctorsFinalizedRecordOnly(): void
    {
        $this->assertTrue(ConsultationRequest::doctorHasRelationshipWithPatient($this->doctorA, $this->patientA));

        $prior = PatientClinicalRecordService::getPriorFinalizedRecordsForDoctor($this->doctorA, $this->patientA);
        $this->assertIsArray($prior);
        $this->assertCount(1, $prior);
        $this->assertSame($this->recordBFinal, (int) ($prior[0]['record']['id'] ?? 0));
        $this->assertSame('AUTHZ-B-DX malaria', (string) ($prior[0]['record']['diagnosis'] ?? ''));
        $this->assertSame('Coartem', (string) ($prior[0]['prescriptions'][0]['medication_name'] ?? ''));
    }

    public function testDraftRecordsAreInvisibleToDoctorsOtherThanTheAuthor(): void
    {
        $this->assertIsArray(ConsultationRecord::findByConsultationForDoctor($this->requestBDraft, $this->doctorB));
        $this->assertSame(
            ConsultationRecord::STATUS_DRAFT,
            (string) (ConsultationRecord::findByConsultationForDoctor($this->requestBDraft, $this->doctorB)['record_status'] ?? '')
        );

        $this->assertNull(ConsultationRecord::findByConsultationForDoctor($this->requestBDraft, $this->doctorA));
        $this->assertNull(ConsultationRecord::findByConsultationForDoctor($this->requestBDraft, $this->doctorC));
        $this->assertNull(PatientClinicalRecordService::getHistoricalRecordForDoctor($this->doctorA, $this->requestBDraft));

        $authorHistorical = PatientClinicalRecordService::getHistoricalRecordForDoctor($this->doctorB, $this->requestBDraft);
        $this->assertIsArray($authorHistorical);
        $this->assertNull($authorHistorical['record'] ?? null);
    }

    public function testDraftRecordsNeverAppearOnTheCrossDoctorPath(): void
    {
        $history = ConsultationRecord::findFinalizedHistoryForPatient($this->patientA);
        $historyIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $history);
        $this->assertNotContains($this->recordBDraft, $historyIds);
        foreach ($history as $row) {
            $this->assertSame(ConsultationRecord::STATUS_FINAL, (string) ($row['record_status'] ?? ''));
        }

        $priorA = PatientClinicalRecordService::getPriorFinalizedRecordsForDoctor($this->doctorA, $this->patientA);
        $this->assertIsArray($priorA);
        $priorIds = array_map(static fn (array $item): int => (int) ($item['record']['id'] ?? 0), $priorA);
        $this->assertNotContains($this->recordBDraft, $priorIds);

        $priorC = PatientClinicalRecordService::getPriorFinalizedRecordsForDoctor($this->doctorC, $this->patientA);
        $this->assertNull($priorC);
    }
}
