<?php

/**
 * Cross-doctor visibility of FINALIZED consultation records only.
 *
 * Doctor A (has treated the patient) can read doctor B's finalized record.
 * Doctor C (never treated the patient) is denied even on a direct service call.
 *
 * Usage: php bin/test_cross_doctor_records.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Core\Session;
use App\Models\ConsultationRecord;
use App\Models\ConsultationRequest;
use App\Models\Prescription;
use App\Services\AuditLogService;
use App\Services\PatientClinicalRecordService;

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
expect_true(isset($catalog['clinical_record_viewed_cross_doctor']), 'Audit catalog includes clinical_record_viewed_cross_doctor');
expect_true(($catalog['clinical_record_viewed_cross_doctor']['category'] ?? '') === 'clinical', 'Cross-doctor view event is catalogued as clinical');

$recordModel = (string) file_get_contents($root . '/app/Models/ConsultationRecord.php');
$requestModel = (string) file_get_contents($root . '/app/Models/ConsultationRequest.php');
$recordService = (string) file_get_contents($root . '/app/Services/PatientClinicalRecordService.php');
$doctorController = (string) file_get_contents($root . '/app/Controllers/DoctorController.php');
$doctorShow = (string) file_get_contents($root . '/app/Views/doctor/consultations/show.php');
$priorPartial = (string) file_get_contents($root . '/app/Views/partials/shared/_prior_consultations.php');

expect_true(str_contains($recordModel, 'function findFinalizedHistoryForPatient'), 'ConsultationRecord::findFinalizedHistoryForPatient exists');
expect_true(str_contains($recordModel, 'STATUS_FINAL'), 'Finalized history uses the existing Final status constant');
expect_true(str_contains($requestModel, 'function doctorHasRelationshipWithPatient'), 'ConsultationRequest relationship guard exists');
expect_true(str_contains($recordService, 'function getPriorFinalizedRecordsForDoctor'), 'Service-layer prior-record read path exists');
expect_true(str_contains($recordService, 'doctorHasRelationshipWithPatient'), 'Service re-checks the doctor-patient relationship');
expect_true(str_contains($recordService, 'clinical_record_viewed_cross_doctor'), 'Cross-doctor read path writes the dedicated audit event');
expect_true(str_contains($doctorController, 'getPriorFinalizedRecordsForDoctor'), 'Doctor consultation view loads prior finalized records');
expect_true(str_contains($doctorShow, '_prior_consultations.php'), 'Doctor patient view includes the prior consultations partial');
expect_true(str_contains($priorPartial, 'Prior consultations'), 'Prior consultations panel title is present');
expect_true(str_contains($priorPartial, '_prescription_document.php'), 'Prior consultations reuse the existing prescription document');
expect_true(
    str_contains($recordService, 'function getHistoricalRecordForDoctor')
    && str_contains($recordService, 'findByIdForDoctor')
    && str_contains($recordService, 'findByConsultationForDoctor'),
    'Doctor own-record historical path is still doctor_id scoped'
);

$db = Database::getInstance();
$patientRoleId = (int) $db->query("SELECT id FROM roles WHERE name = 'patient' LIMIT 1")->fetchColumn();
$doctorRoleId = (int) $db->query("SELECT id FROM roles WHERE name = 'doctor' LIMIT 1")->fetchColumn();
expect_true($patientRoleId > 0 && $doctorRoleId > 0, 'Patient and doctor roles exist');

$suffix = 'xdcr' . date('His') . bin2hex(random_bytes(2));
$createdUserIds = [];
$createdRequestIds = [];
$createdRecordIds = [];

$insertUser = static function (int $roleId, string $email, string $name) use ($db, &$createdUserIds): int {
    $stmt = $db->prepare(
        "INSERT INTO users (role_id, full_name, email, password, status)
         VALUES (:role_id, :full_name, :email, :password, 'active')"
    );
    $stmt->execute([
        ':role_id' => $roleId,
        ':full_name' => $name,
        ':email' => $email,
        ':password' => password_hash('TestPass123!', PASSWORD_DEFAULT),
    ]);
    $userId = (int) $db->lastInsertId();
    $createdUserIds[] = $userId;

    return $userId;
};

$insertRequest = static function (int $patientId, int $doctorId, string $reason) use ($db, &$createdRequestIds): int {
    $stmt = $db->prepare(
        "INSERT INTO consultation_requests (patient_id, doctor_id, reason, status)
         VALUES (:patient_id, :doctor_id, :reason, 'Approved')"
    );
    $stmt->execute([
        ':patient_id' => $patientId,
        ':doctor_id' => $doctorId,
        ':reason' => $reason,
    ]);
    $requestId = (int) $db->lastInsertId();
    $createdRequestIds[] = $requestId;

    return $requestId;
};

$doctorAId = 0;
$doctorBId = 0;
$doctorCId = 0;
$patientId = 0;
$requestAId = 0;
$requestBFinalId = 0;
$requestBDraftId = 0;
$recordAId = 0;
$recordBFinalId = 0;
$recordBDraftId = 0;

$cleanup = static function () use ($db, &$createdRequestIds, &$createdRecordIds, &$createdUserIds): void {
    foreach ($createdRecordIds as $recordId) {
        if ($recordId > 0) {
            $db->prepare('DELETE FROM prescriptions WHERE consultation_record_id = :id')->execute([':id' => $recordId]);
            $db->prepare("DELETE FROM audit_logs WHERE entity_type = 'consultation_record' AND entity_id = :id")->execute([':id' => $recordId]);
            $db->prepare('DELETE FROM consultation_records WHERE id = :id')->execute([':id' => $recordId]);
        }
    }
    foreach ($createdRequestIds as $requestId) {
        if ($requestId > 0) {
            $db->prepare('DELETE FROM notifications WHERE related_entity_id = :id')->execute([':id' => $requestId]);
            $db->prepare("DELETE FROM audit_logs WHERE entity_type = 'consultation_request' AND entity_id = :id")->execute([':id' => $requestId]);
            $db->prepare('DELETE FROM consultation_requests WHERE id = :id')->execute([':id' => $requestId]);
        }
    }
    foreach ($createdUserIds as $userId) {
        $db->prepare('DELETE FROM notifications WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM patient WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM doctor WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
    }
};

try {
    $doctorAId = $insertUser($doctorRoleId, 'xdcr.doctor.a+' . $suffix . '@telehealth.test', 'XDCR Doctor A');
    $doctorBId = $insertUser($doctorRoleId, 'xdcr.doctor.b+' . $suffix . '@telehealth.test', 'XDCR Doctor B');
    $doctorCId = $insertUser($doctorRoleId, 'xdcr.doctor.c+' . $suffix . '@telehealth.test', 'XDCR Doctor C');
    $patientId = $insertUser($patientRoleId, 'xdcr.patient+' . $suffix . '@telehealth.test', 'XDCR Patient');

    $insertDoctor = $db->prepare("INSERT INTO doctor (user_id, professional_title, specialization) VALUES (:id, 'Medical Officer', 'General Practice')");
    $insertDoctor->execute([':id' => $doctorAId]);
    $insertDoctor->execute([':id' => $doctorBId]);
    $insertDoctor->execute([':id' => $doctorCId]);
    $db->prepare('INSERT INTO patient (user_id) VALUES (:id)')->execute([':id' => $patientId]);

    $requestBFinalId = $insertRequest($patientId, $doctorBId, 'XDCR-TEST doctor B finalized visit');
    $requestBDraftId = $insertRequest($patientId, $doctorBId, 'XDCR-TEST doctor B draft visit');
    $requestAId = $insertRequest($patientId, $doctorAId, 'XDCR-TEST doctor A relationship visit');

    $now = date('Y-m-d H:i:s');
    $draftB = ConsultationRecord::ensureDraftForDoctor($requestBDraftId, $doctorBId, $patientId, $now, 'XDCR-B-DRAFT hidden complaint');
    expect_true(is_array($draftB), 'Doctor B can keep a draft record for the same patient');
    $recordBDraftId = (int) ($draftB['id'] ?? 0);
    if ($recordBDraftId > 0) {
        $createdRecordIds[] = $recordBDraftId;
    }
    expect_true((string) ($draftB['record_status'] ?? '') === ConsultationRecord::STATUS_DRAFT, 'Doctor B in-progress record remains Draft');

    $completeB = ConsultationRecord::completeConsultationForDoctor(
        $requestBFinalId,
        $doctorBId,
        $patientId,
        $now,
        [
            'chief_complaint' => 'Fever',
            'symptoms' => 'Three days of fever',
            'clinical_findings' => 'Febrile',
            'diagnosis' => 'XDCR-B-DX malaria',
            'treatment_plan' => 'XDCR-B-TX artemether-lumefantrine',
            'additional_notes' => 'Follow up if fever persists',
        ]
    );
    expect_true(($completeB['success'] ?? false) === true, 'Doctor B can finalize their own clinical record');
    $recordBFinalId = (int) (($completeB['record']['id'] ?? 0));
    if ($recordBFinalId > 0) {
        $createdRecordIds[] = $recordBFinalId;
    }

    $rxB = Prescription::createForCompletedConsultation($recordBFinalId, $doctorBId, $patientId, [[
        'medication_name' => 'Coartem',
        'dosage' => '80/480 mg',
        'frequency' => 'BD',
        'duration' => '3 days',
        'quantity' => '24',
    ]]);
    expect_true(($rxB['success'] ?? false) === true, 'Doctor B can issue a prescription on the finalized record');

    $completeA = ConsultationRecord::completeConsultationForDoctor(
        $requestAId,
        $doctorAId,
        $patientId,
        $now,
        [
            'chief_complaint' => 'Cough',
            'symptoms' => 'Dry cough',
            'clinical_findings' => 'Afebrile',
            'diagnosis' => 'XDCR-A-DX viral URTI',
            'treatment_plan' => 'XDCR-A-TX supportive care',
            'additional_notes' => '',
        ]
    );
    expect_true(($completeA['success'] ?? false) === true, 'Doctor A can finalize their own clinical record');
    $recordAId = (int) (($completeA['record']['id'] ?? 0));
    if ($recordAId > 0) {
        $createdRecordIds[] = $recordAId;
    }

    expect_true(ConsultationRequest::doctorHasRelationshipWithPatient($doctorAId, $patientId), 'Doctor A has a consultation_requests relationship with the patient');
    expect_true(ConsultationRequest::doctorHasRelationshipWithPatient($doctorBId, $patientId), 'Doctor B has a consultation_requests relationship with the patient');
    expect_true(!ConsultationRequest::doctorHasRelationshipWithPatient($doctorCId, $patientId), 'Doctor C has never treated the patient');

    $history = ConsultationRecord::findFinalizedHistoryForPatient($patientId);
    $historyIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $history);
    $historyStatuses = array_unique(array_map(static fn (array $row): string => (string) ($row['record_status'] ?? ''), $history));
    expect_true(in_array($recordBFinalId, $historyIds, true), 'Finalized history includes doctor B\'s Final record');
    expect_true(in_array($recordAId, $historyIds, true), 'Finalized history includes doctor A\'s own Final record');
    expect_true(!in_array($recordBDraftId, $historyIds, true), 'Finalized history never includes doctor B\'s Draft record');
    expect_true($historyStatuses === [ConsultationRecord::STATUS_FINAL], 'Finalized history only returns Final records');

    $ownA = PatientClinicalRecordService::getHistoricalRecordForDoctor($doctorAId, $requestAId);
    expect_true(is_array($ownA['record'] ?? null), 'Doctor A own-record path still loads their finalized record');
    expect_true(($ownA['record']['diagnosis'] ?? '') === 'XDCR-A-DX viral URTI', 'Doctor A own-record path is unchanged');
    expect_true(array_keys($ownA ?? []) === ['request', 'record', 'prescriptions'], 'Doctor A own-record payload shape is unchanged');
    expect_true(PatientClinicalRecordService::getHistoricalRecordForDoctor($doctorAId, $requestBFinalId) === null, 'Doctor A cannot open doctor B\'s consultation through the own-record path');
    expect_true(PatientClinicalRecordService::getHistoricalRecordForDoctor($doctorCId, $requestBFinalId) === null, 'Doctor C cannot open doctor B\'s consultation through the own-record path');
    expect_true(ConsultationRequest::findByIdForDoctor($requestBFinalId, $doctorCId) === null, 'Doctor C cannot load doctor B\'s consultation_requests row');
    expect_true(Prescription::findByRecordForDoctor($recordBFinalId, $doctorAId) === [], 'Doctor-scoped prescription lookup still hides doctor B\'s lines from doctor A');

    $auditBefore = (int) $db->query(
        "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'clinical_record_viewed_cross_doctor'"
    )->fetchColumn();

    $priorForA = PatientClinicalRecordService::getPriorFinalizedRecordsForDoctor($doctorAId, $patientId);
    expect_true(is_array($priorForA), 'Doctor A is allowed to read prior finalized records for this patient');
    expect_true(count($priorForA) === 1, 'Prior path returns only other doctors\' finalized records');
    $seenRecord = $priorForA[0]['record'] ?? [];
    expect_true((int) ($seenRecord['id'] ?? 0) === $recordBFinalId, 'Doctor A sees doctor B\'s finalized record');
    expect_true((int) ($seenRecord['doctor_id'] ?? 0) === $doctorBId, 'Prior record is attributed to doctor B');
    expect_true(($seenRecord['diagnosis'] ?? '') === 'XDCR-B-DX malaria', 'Doctor A can read doctor B\'s diagnosis');
    expect_true(($seenRecord['treatment_plan'] ?? '') === 'XDCR-B-TX artemether-lumefantrine', 'Doctor A can read doctor B\'s treatment plan');
    expect_true(($priorForA[0]['prescriptions'][0]['medication_name'] ?? '') === 'Coartem', 'Doctor A can read doctor B\'s prescription');
    $priorIds = array_map(static fn (array $item): int => (int) ($item['record']['id'] ?? 0), $priorForA);
    expect_true(!in_array($recordAId, $priorIds, true), 'Prior path does not mix in doctor A\'s own record');
    expect_true(!in_array($recordBDraftId, $priorIds, true), 'Prior path does not expose doctor B\'s draft');

    $auditAfterA = $db->prepare(
        "SELECT actor_name, actor_role, subject_name, subject_role, entity_type, entity_id, event_type
           FROM audit_logs
          WHERE event_type = 'clinical_record_viewed_cross_doctor'
            AND entity_id = :entity_id
          ORDER BY id DESC
          LIMIT 1"
    );
    $auditAfterA->execute([':entity_id' => $recordBFinalId]);
    $auditRow = $auditAfterA->fetch(PDO::FETCH_ASSOC) ?: null;
    expect_true(is_array($auditRow), 'Cross-doctor read writes an audit row');
    if (is_array($auditRow)) {
        expect_true((string) ($auditRow['event_type'] ?? '') === 'clinical_record_viewed_cross_doctor', 'Audit event type is clinical_record_viewed_cross_doctor');
        expect_true((string) ($auditRow['actor_name'] ?? '') === 'XDCR Doctor A', 'Audit actor is the viewing doctor');
        expect_true((string) ($auditRow['actor_role'] ?? '') === 'doctor', 'Audit actor role is doctor');
        expect_true((string) ($auditRow['subject_name'] ?? '') === 'XDCR Patient', 'Audit subject is the patient');
        expect_true((string) ($auditRow['subject_role'] ?? '') === 'patient', 'Audit subject role is patient');
        expect_true((string) ($auditRow['entity_type'] ?? '') === AuditLogService::ENTITY_CONSULTATION_RECORD, 'Audit entity is the clinical record');
        expect_true((int) ($auditRow['entity_id'] ?? 0) === $recordBFinalId, 'Audit entity id is the viewed record');
    }

    $deniedC = PatientClinicalRecordService::getPriorFinalizedRecordsForDoctor($doctorCId, $patientId);
    expect_true($deniedC === null, 'Doctor C is denied on a direct prior-record service call');
    expect_true(PatientClinicalRecordService::getPriorFinalizedRecordsForDoctor($doctorCId, $patientId) === null, 'Doctor C remains denied on a repeated direct request');

    $auditAfterC = (int) $db->query(
        "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'clinical_record_viewed_cross_doctor'"
    )->fetchColumn();
    expect_true($auditAfterC === $auditBefore + 1, 'Denied doctor C does not create a cross-doctor view audit event');
} catch (Throwable $exception) {
    expect_true(false, 'Cross-doctor record test setup failed: ' . $exception->getMessage());
} finally {
    try {
        $cleanup();
    } catch (Throwable $exception) {
        echo 'Cleanup warning: ' . $exception->getMessage() . "\n";
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
