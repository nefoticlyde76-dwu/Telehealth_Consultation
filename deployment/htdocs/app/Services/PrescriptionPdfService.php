<?php

namespace App\Services;

use App\Config\Paths;
use App\Helpers\Helper;
use Dompdf\Dompdf;
use Dompdf\Options;

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

        $html = self::renderDocumentHtml($page);
        $publicRoot = realpath(Paths::publicRoot());

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('dpi', 96);
        if (is_string($publicRoot) && $publicRoot !== '') {
            $options->setChroot($publicRoot);
        }

        $dompdf = new Dompdf($options);
        if (is_string($publicRoot) && $publicRoot !== '') {
            $dompdf->setBasePath($publicRoot);
        }

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        self::paintFooter($dompdf);

        return [
            'binary' => (string) $dompdf->output(),
            'filename' => self::filename($page),
        ];
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
        $logoSrc = self::logoSrc();
        $signature = self::embedSignatureImage(self::signatureSrc($page));
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

        $relative = str_replace('\\', '/', trim((string) ($request['doctor_signature_path'] ?? '')));
        $expectedPrefix = 'uploads/doctors/' . $doctorId . '/';
        if ($relative === ''
            || str_contains($relative, '..')
            || str_contains($relative, ':')
            || !str_starts_with($relative, $expectedPrefix)
        ) {
            return '';
        }

        $publicRoot = realpath(Paths::publicRoot());
        if (!is_string($publicRoot) || $publicRoot === '') {
            return '';
        }

        $absolute = realpath($publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($absolute === false || !is_file($absolute) || !str_starts_with($absolute, $publicRoot . DIRECTORY_SEPARATOR)) {
            return '';
        }

        $size = filesize($absolute);
        if ($size === false || $size <= 0 || $size > 2000000) {
            return '';
        }

        $extension = strtolower((string) pathinfo($absolute, PATHINFO_EXTENSION));
        if ($extension === 'png' && !extension_loaded('gd')) {
            return '';
        }

        return $relative;
    }

    /**
     * Flatten and scale the stored signature so DomPDF can paint it inside
     * the A4 table cell. Native multi-megapixel PNGs with alpha are embedded
     * at full size and disappear off the page.
     *
     * @return array{src:string,width:int,height:int}
     */
    public static function embedSignatureImage(string $relative): array
    {
        $empty = ['src' => '', 'width' => 0, 'height' => 0];
        $relative = str_replace('\\', '/', trim($relative));
        if ($relative === '' || !extension_loaded('gd')) {
            return $empty;
        }

        $publicRoot = realpath(Paths::publicRoot());
        if (!is_string($publicRoot) || $publicRoot === '') {
            return $empty;
        }

        $absolute = realpath($publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($absolute === false || !is_file($absolute) || !is_readable($absolute)) {
            return $empty;
        }
        if (!str_starts_with($absolute, $publicRoot . DIRECTORY_SEPARATOR)) {
            return $empty;
        }

        $bytes = file_get_contents($absolute);
        if (!is_string($bytes) || $bytes === '') {
            return $empty;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return $empty;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($source);
            return $empty;
        }

        $maxW = 200;
        $maxH = 140;
        $scale = min($maxW / $srcW, $maxH / $srcH, 1.0);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        $canvas = imagecreatetruecolor($dstW, $dstH);
        if ($canvas === false) {
            imagedestroy($source);
            return $empty;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $dstW, $dstH, $white);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($source);

        // JPEG avoids DomPDF's PNG alpha-mask path, which can paint a blank box.
        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        if ($jpeg === '') {
            return $empty;
        }

        return [
            'src' => 'data:image/jpeg;base64,' . base64_encode($jpeg),
            'width' => $dstW,
            'height' => $dstH,
        ];
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

    private static function paintFooter(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $width = $canvas->get_width();
        $height = $canvas->get_height();
        $y = $height - 24;
        $color = [0.42, 0.45, 0.48];

        $canvas->page_text(48, $y, 'MBPHA TeleHealth  ·  Confidential prescription', $font, 8, $color);
        $canvas->page_text($width - 118, $y, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, $color);
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

    private static function logoSrc(): string
    {
        $path = Paths::publicRoot() . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'LOGOS.png';
        if (!is_file($path)) {
            return '';
        }

        $size = filesize($path);
        if ($size === false || $size > 400000) {
            return '';
        }

        if (!extension_loaded('gd')) {
            return '';
        }

        return 'images/LOGOS.png';
    }
}
