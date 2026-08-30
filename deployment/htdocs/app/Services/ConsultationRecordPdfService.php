<?php

namespace App\Services;

use App\Config\Paths;
use App\Helpers\Helper;
use Dompdf\Dompdf;
use Dompdf\Options;

class ConsultationRecordPdfService
{
    /**
     * Build a downloadable PDF from an already-authorized historical record page.
     * Returns null when the page is not a completed consultation with a Final record.
     *
     * @param array{
     *   request:array<string,mixed>,
     *   record:?array<string,mixed>,
     *   prescriptions?:list<array<string,mixed>>
     * } $page
     * @return array{binary:string,filename:string}|null
     */
    public static function buildFromAuthorizedPage(array $page): ?array
    {
        if (!PatientClinicalRecordService::isDownloadableDocument($page, 'record')) {
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
     * HTML document used by DomPDF. Public so tests can assert field labels
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
        $clinicalRecord = is_array($page['record'] ?? null) ? $page['record'] : [];
        $logoSrc = self::logoSrc();

        ob_start();
        require dirname(__DIR__) . '/Views/documents/consultation_record_pdf.php';
        return (string) ob_get_clean();
    }

    /**
     * @param array{binary:string,filename:string} $built
     */
    public static function stream(array $built): void
    {
        $filename = self::safeDownloadName((string) ($built['filename'] ?? 'MBPHA-Consultation-Record.pdf'));
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

    private static function paintFooter(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $width = $canvas->get_width();
        $height = $canvas->get_height();
        $y = $height - 24;
        $color = [0.42, 0.45, 0.48];

        $canvas->page_text(48, $y, 'MBPHA TeleHealth  ·  Confidential clinical record', $font, 8, $color);
        $canvas->page_text($width - 118, $y, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, $color);
    }

    /**
     * @param array{request?:array<string,mixed>} $page
     */
    public static function filename(array $page): string
    {
        $request = is_array($page['request'] ?? null) ? $page['request'] : [];
        $namePart = self::filenamePart((string) ($request['patient_name'] ?? ''));
        $datePart = Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd-M-Y', date('d-M-Y'));
        $base = $namePart !== ''
            ? 'MBPHA-Consultation-Record-' . $namePart . '-' . $datePart
            : 'MBPHA-Consultation-Record-' . $datePart;

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
            return 'MBPHA-Consultation-Record.pdf';
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

        return 'images/LOGOS.png';
    }
}
