<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Certificates;

defined('ABSPATH') || exit;

/**
 * PDF engine wrapper. Uses Dompdf if available, otherwise TCPDF.
 * Falls back to HTML when no engine is present.
 */
final class PdfEngine
{
    public static function hasPdfEngine(): bool
    {
        return class_exists('\\Dompdf\\Dompdf') || class_exists('\\TCPDF');
    }

    /**
     * @return string|null PDF binary, or null if no engine available.
     */
    public static function htmlToPdf(string $html): ?string
    {
        if (class_exists('\\Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            return (string) $dompdf->output();
        }

        if (class_exists('\\TCPDF')) {
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('SOLAS Portal');
            $pdf->SetAuthor('SOLAS');
            $pdf->SetTitle('CPD Certificate');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(0, 0, 0, true);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');
            return (string) $pdf->Output('', 'S');
        }

        return null;
    }

    public static function streamPdf(string $pdfBinary, string $filename): void
    {
        if (ob_get_length()) {
            @ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBinary));
        echo $pdfBinary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
}
