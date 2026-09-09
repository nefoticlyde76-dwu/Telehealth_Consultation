<?php

namespace App\Services;

use App\Config\Paths;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Shared DomPDF setup for clinical downloads.
 * Letterhead images must stay small: the public brand PNG (LOGOS.png) is
 * sized for the navbar (~960x401) which still exceeds this decoder budget,
 * so PDFs use images/pdf-logo.png (or favicon.png) instead.
 */
class PdfDocumentSupport
{
    private const MAX_SOURCE_BYTES = 400000;
    private const MAX_SOURCE_PIXELS = 262144;
    private const LOGO_MAX_EDGE = 160;

    public static function createOptions(): Options
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('dpi', 96);

        $publicRoot = self::publicRoot();
        if ($publicRoot !== null) {
            $options->setChroot($publicRoot);
        }

        $cacheDir = Paths::storageRoot() . DIRECTORY_SEPARATOR . 'dompdf';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $options->set('fontCache', $cacheDir);
        }

        return $options;
    }

    public static function publicRoot(): ?string
    {
        $publicRoot = realpath(Paths::publicRoot());

        return is_string($publicRoot) && $publicRoot !== '' ? $publicRoot : null;
    }

    public static function embedLetterheadLogo(): string
    {
        if (!extension_loaded('gd')) {
            return '';
        }

        $absolute = self::letterheadFile();
        if ($absolute === null) {
            return '';
        }

        $bytes = @file_get_contents($absolute);
        if (!is_string($bytes) || $bytes === '') {
            return '';
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return '';
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW < 1 || $srcH < 1 || ($srcW * $srcH) > self::MAX_SOURCE_PIXELS) {
            imagedestroy($source);
            return '';
        }

        $scale = min(self::LOGO_MAX_EDGE / $srcW, self::LOGO_MAX_EDGE / $srcH, 1.0);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        $canvas = imagecreatetruecolor($dstW, $dstH);
        if ($canvas === false) {
            imagedestroy($source);
            return '';
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $dstW, $dstH, $white);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 88);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        if ($jpeg === '') {
            return '';
        }

        return 'data:image/jpeg;base64,' . base64_encode($jpeg);
    }

    public static function paintFooter(Dompdf $dompdf, string $label): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        if ($canvas === null || $font === null) {
            return;
        }

        $width = $canvas->get_width();
        $height = $canvas->get_height();
        $y = $height - 24;
        $color = [0.42, 0.45, 0.48];

        $canvas->page_text(48, $y, $label, $font, 8, $color);
        $canvas->page_text($width - 118, $y, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, $color);
    }

    private static function letterheadFile(): ?string
    {
        $publicRoot = self::publicRoot();
        if ($publicRoot === null) {
            return null;
        }

        foreach (['images/pdf-logo.png', 'images/pdf-logo.jpg', 'favicon.png'] as $relative) {
            $absolute = realpath($publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
            if ($absolute === false || !is_file($absolute) || !is_readable($absolute)) {
                continue;
            }
            if (!str_starts_with($absolute, $publicRoot . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $size = filesize($absolute);
            if ($size === false || $size <= 0 || $size > self::MAX_SOURCE_BYTES) {
                continue;
            }

            $info = @getimagesize($absolute);
            if (!is_array($info)) {
                continue;
            }

            $width = (int) ($info[0] ?? 0);
            $height = (int) ($info[1] ?? 0);
            if ($width < 1 || $height < 1 || ($width * $height) > self::MAX_SOURCE_PIXELS) {
                continue;
            }

            return $absolute;
        }

        return null;
    }
}
