<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Certificates;

defined('ABSPATH') || exit;

final class CertificateService
{
    /**
     * Build certificate HTML.
     *
     * Backwards-compatible: older calls may pass ($userId, $cycleLabel, $totalsArray, $context)
     * or ($userId, $cycleLabel, $cycleYearInt, $context).
     */
    public static function html(int $userId, string $cycleLabel, $totalsOrCycleYear = null, array $context = []): string
    {
        $cycleYear = null;
        $totals = null;

        if (is_array($totalsOrCycleYear)) {
            $totals = $totalsOrCycleYear;
        } elseif (is_int($totalsOrCycleYear) && $totalsOrCycleYear > 0) {
            $cycleYear = $totalsOrCycleYear;
        }

        if ($cycleYear === null) {
            $cycleYear = self::cycleYearFromLabel($cycleLabel);
        }
        if ($totals === null) {
            $totals = self::getTotals($userId, (int) $cycleYear);
        }

        $user = get_userdata($userId);
        $name = $user ? (string) $user->display_name : ('User #' . $userId);

        $preview = !empty($context['preview']);

        // Map totals to expected data keys (support multiple shapes)
        $structured = (float) ($totals['structured_hours'] ?? $totals['structured'] ?? 0);
        $unstructured = (float) ($totals['unstructured_hours'] ?? $totals['unstructured'] ?? 0);
        $solas = (float) ($totals['solas_structured_hours'] ?? $totals['solas_structured'] ?? $totals['solas'] ?? 0);
        $total = (float) ($totals['total_hours'] ?? $totals['total'] ?? ($structured + $unstructured + $solas));

        $data = [
            'name' => $name,
            'cycle_label' => $cycleLabel ?: self::cycleLabelFromYear((int)$cycleYear),
            'structured_hours' => $structured,
            'unstructured_hours' => $unstructured,
            'solas_structured_hours' => $solas,
            'total_hours' => $total,
            'date' => (string) ($context['date'] ?? date_i18n('j F Y')),
            'preview' => $preview,
        ];

        return Renderer::html($data);
    }

    /**
     * Output a single certificate as PDF (if requested and possible) or HTML fallback.
     *
     * Accepts either the new signature:
     *   outputSingle($userId, $cycleLabel, $wantPdf, $preview)
     * or the older signature:
     *   outputSingle($userId, $cycleLabel, $cycleYear, $wantPdf, $context)
     */
    public static function outputSingle(...$args): void
    {
        $userId = isset($args[0]) ? (int) $args[0] : 0;
        $cycleLabel = isset($args[1]) ? (string) $args[1] : '';

        $cycleYear = null;
        $wantPdf = false;
        $context = [];

        // Determine calling pattern
        if (isset($args[2]) && is_bool($args[2])) {
            // new: userId, cycleLabel, wantPdf, previewBool
            $wantPdf = (bool) $args[2];
            $preview = !empty($args[3]);
            $context = ['preview' => $preview];
            $cycleYear = self::cycleYearFromLabel($cycleLabel);
        } else {
            // old: userId, cycleLabel, cycleYear, wantPdf, context
            $cycleYear = isset($args[2]) ? (int) $args[2] : self::cycleYearFromLabel($cycleLabel);
            $wantPdf = !empty($args[3]);
            $context = (isset($args[4]) && is_array($args[4])) ? $args[4] : [];
        }

        if (!$cycleYear) {
            $cycleYear = self::cycleYearFromLabel($cycleLabel);
        }
        if ($cycleLabel === '' && $cycleYear) {
            $cycleLabel = self::cycleLabelFromYear((int)$cycleYear);
        }

        $html = self::html($userId, $cycleLabel, (int) $cycleYear, $context);

        if ($wantPdf && PdfEngine::hasPdfEngine()) {
            $pdf = PdfEngine::htmlToPdf($html);
            if ($pdf !== null) {
                $filename = 'cpd-certificate-' . (int)$cycleYear . '-' . $userId . '.pdf';
                PdfEngine::streamPdf($pdf, $filename);
            }
        }

        // HTML fallback
        if (ob_get_length()) {
            @ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    private static function cycleYearFromLabel(string $label): int
    {
        if (preg_match('/^(\d{4})\s*\//', $label, $m)) {
            return (int) $m[1];
        }
        if (ctype_digit($label)) {
            return (int) $label;
        }
        // fall back to current cycle if available
        if (class_exists('Solas\\Portal\\CPD\\Cycle') && method_exists('Solas\\Portal\\CPD\\Cycle', 'currentStartYear')) {
            return (int) \Solas\Portal\CPD\Cycle::currentStartYear();
        }
        // final fallback: infer from date
        $y = (int) gmdate('Y');
        $mth = (int) gmdate('n');
        $day = (int) gmdate('j');
        return ($mth > 11 || ($mth === 11 && $day >= 1)) ? $y : ($y - 1);
    }

    private static function cycleLabelFromYear(int $startYear): string
    {
        return sprintf('%d/%02d', $startYear, ($startYear + 1) % 100);
    }

    /**
     * Get CPD totals for a user & cycle year.
     * This uses existing totals logic if present; otherwise returns zeros.
     */
    private static function getTotals(int $userId, int $cycleYear): array
    {
        // Prefer existing totals function if the procedural layer exists
        if (function_exists('solas_cpd_get_cycle_totals')) {
            $t = solas_cpd_get_cycle_totals($userId, $cycleYear);
            return is_array($t) ? $t : [];
        }

        // If you have a class totals provider in your CPD module, try it
        if (class_exists('Solas\\Portal\\CPD\\Totals') && method_exists('Solas\\Portal\\CPD\\Totals', 'forUserCycle')) {
            $t = \Solas\Portal\CPD\Totals::forUserCycle($userId, $cycleYear);
            return is_array($t) ? $t : [];
        }

        return [
            'structured_hours' => 0,
            'unstructured_hours' => 0,
            'solas_structured_hours' => 0,
            'total_hours' => 0,
        ];
    }
}
