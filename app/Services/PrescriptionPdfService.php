<?php

namespace App\Services;

use App\Helpers\Helper;
use Dompdf\Dompdf;

class PrescriptionPdfService
{
    /**
     * Build a downloadable A4 portrait PDF from an already-authorized
     * prescription page. Returns null when no issued prescription exists.
     * This method does not insert or update prescription rows.
     *
     * @param array{
     *   request:array<string,mixed>,
     *   record:?array<string,mixed>,
     *   prescriptions:list<array<string,mixed>>
     * } $page
     * @return array{binary:string,filename:string}|null
     */
    public static function buildFromAuthorizedPage(array $page): ?array
    {
        if (!PatientClinicalRecordService::isDownloadableDocument($page, 'prescription')) {
            return null;
        }

        if (!self::prescriptionMatchesConsultation($page)) {
            return null;
        }

        try {
            $html = self::renderDocumentHtml($page);
            $publicRoot = PdfDocumentSupport::publicRoot();
            $options = PdfDocumentSupport::createOptions();

            $dompdf = new Dompdf($options);
            if ($publicRoot !== null) {
                $dompdf->setBasePath($publicRoot);
            }

            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            PdfDocumentSupport::paintFooter($dompdf, 'MBPHA TeleHealth  ·  Confidential prescription');

            return [
                'binary' => (string) $dompdf->output(),
                'filename' => self::filename($page),
            ];
        } catch (\Throwable $exception) {
            error_log('Prescription PDF render failed: ' . $exception->getMessage());
            return null;
        }
    }

    /**
     * HTML document used by DomPDF. Public so tests can assert labels
     * without parsing compressed PDF streams.
     *
     * @param array{
     *   request:array<string,mixed>,
     *   record:?array<string,mixed>,
     *   prescriptions?:list<array<string,mixed>>
     * } $page
     */
    public static function renderDocumentHtml(array $page): string
    {
        $request = is_array($page['request'] ?? null) ? $page['request'] : [];
        $prescriptions = is_array($page['prescriptions'] ?? null) ? $page['prescriptions'] : [];
        $logoSrc = PdfDocumentSupport::embedLetterheadLogo();
        $signature = self::embedSignatureImage(
            (string) ($request['doctor_signature_path'] ?? ''),
            (int) ($request['doctor_id'] ?? 0)
        );
        $signatureSrc = $signature['src'];
        $signatureWidth = $signature['width'];
        $signatureHeight = $signature['height'];

        ob_start();
        require dirname(__DIR__) . '/Views/documents/prescription_pdf.php';
        return (string) ob_get_clean();
    }

    /**
     * Resolve the prescribing doctor's stored signature for PDF embedding.
     * The path is taken from the authorized doctor profile joined to the
     * consultation. Query/body values are never read.
     *
     * @param array{
     *   request?:array<string,mixed>,
     *   prescriptions?:list<array<string,mixed>>
     * } $page
     */
    public static function signatureSrc(array $page): string
    {
        $request = is_array($page['request'] ?? null) ? $page['request'] : [];
        $doctorId = (int) ($request['doctor_id'] ?? 0);
        if ($doctorId <= 0 || !self::prescriptionMatchesConsultation($page)) {
            return '';
        }

        $relative = DoctorSignatureService::normalizeRelativePath((string) ($request['doctor_signature_path'] ?? ''));
        if (!DoctorSignatureService::isSafeDoctorPath($doctorId, $relative)) {
            return '';
        }

        if (DoctorSignatureService::resolveAbsolute($doctorId, $relative) !== null) {
            return $relative;
        }

        $embedded = DoctorSignatureService::embed($doctorId, $relative);
        if (($embedded['src'] ?? '') !== '') {
            return $relative;
        }

        return '';
    }

    /**
     * Flatten and scale the stored signature so DomPDF can paint it inside
     * the A4 table cell. Native multi-megapixel PNGs with alpha are embedded
     * at full size and disappear off the page.
     *
     * @return array{src:string,width:int,height:int}
     */
    public static function embedSignatureImage(string $relative, int $doctorId = 0): array
    {
        return DoctorSignatureService::embed($doctorId, $relative);
    }

    /**
     * @param array{binary:string,filename:string} $built
     */
    public static function stream(array $built): void
    {
        $filename = self::safeDownloadName((string) ($built['filename'] ?? 'MBPHA-Prescription.pdf'));
        $binary = (string) ($built['binary'] ?? '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($binary));
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: public');
        echo $binary;
        exit;
    }

    /**
     * @param array{
     *   request?:array<string,mixed>,
     *   prescriptions?:list<array<string,mixed>>
     * } $page
     */
    public static function prescriptionMatchesConsultation(array $page): bool
    {
        $request = is_array($page['request'] ?? null) ? $page['request'] : [];
        $prescriptions = is_array($page['prescriptions'] ?? null) ? $page['prescriptions'] : [];
        $doctorId = (int) ($request['doctor_id'] ?? 0);
        $patientId = (int) ($request['patient_id'] ?? 0);

        if ($doctorId <= 0 || $patientId <= 0 || $prescriptions === []) {
            return false;
        }

        foreach ($prescriptions as $line) {
            if (!is_array($line)) {
                return false;
            }
            if ((int) ($line['doctor_id'] ?? 0) !== $doctorId) {
                return false;
            }
            if ((int) ($line['patient_id'] ?? 0) !== $patientId) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{
     *   request?:array<string,mixed>,
     *   prescriptions?:list<array<string,mixed>>
     * } $page
     */
    public static function filename(array $page): string
    {
        $request = is_array($page['request'] ?? null) ? $page['request'] : [];
        $prescriptions = is_array($page['prescriptions'] ?? null) ? $page['prescriptions'] : [];
        $issued = '';
        if ($prescriptions !== [] && is_array($prescriptions[0])) {
            $issued = (string) ($prescriptions[0]['issued_date'] ?? '');
        }

        $namePart = self::filenamePart((string) ($request['patient_name'] ?? ''));
        $datePart = Helper::formatDate($issued !== '' ? $issued : (string) ($request['consultation_date'] ?? ''), 'd-M-Y', date('d-M-Y'));
        $base = $namePart !== ''
            ? 'MBPHA-Prescription-' . $namePart . '-' . $datePart
            : 'MBPHA-Prescription-' . $datePart;

        return $base . '.pdf';
    }

    private static function filenamePart(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', trim($value)) ?? '';
        return trim($slug, '-');
    }

    private static function safeDownloadName(string $filename): string
    {
        $filename = str_replace(['"', "\r", "\n", '/', '\\'], '', $filename);
        if ($filename === '' || !str_ends_with(strtolower($filename), '.pdf')) {
            return 'MBPHA-Prescription.pdf';
        }

        return $filename;
    }

}
