<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Certificates;

defined('ABSPATH') || exit;

/**
 * Normalises CPD totals + eligibility for certificate generation and UI.
 *
 * Uses existing plugin compliance logic when available.
 */
final class Eligibility
{
    /**
     * @return array{
     *   cycle_label:string,
     *   cycle_year:int,
     *   eligible:bool,
     *   status:string,
     *   totals:array{structured:float,unstructured:float,solas:float,total:float}
     * }
     */
    public static function forUserCycle(int $userId, int $cycleYear): array
    {
        $label = sprintf('%d/%02d', $cycleYear, ($cycleYear + 1) % 100);

        // 1) Prefer existing dashboard compliance function (most accurate vs admin UI)
        if (function_exists('solas_cpd_dashboard_get_compliance')) {
            $c = solas_cpd_dashboard_get_compliance($userId, $label);
            if (is_array($c)) {
                $structured = (float) ($c['structured'] ?? $c['structured_hours'] ?? 0);
                $unstructured = (float) ($c['unstructured'] ?? $c['unstructured_hours'] ?? 0);
                $solas = (float) ($c['solas'] ?? $c['solas_structured_hours'] ?? 0);
                $total = (float) ($c['total'] ?? ($structured + $unstructured + $solas));
                $eligible = !empty($c['compliant']) || !empty($c['eligible']);
                return [
                    'cycle_label' => $label,
                    'cycle_year'  => $cycleYear,
                    'eligible'    => (bool) $eligible,
                    'status'      => $eligible ? 'eligible' : 'in_progress',
                    'totals'      => [
                        'structured'   => $structured,
                        'unstructured' => $unstructured,
                        'solas'        => $solas,
                        'total'        => $total,
                    ],
                ];
            }
        }

        // 2) Fallback to totals provider and basic threshold if available
        $totals = self::totalsFallback($userId, $cycleYear);
        $eligible = self::eligibleFallback($userId, $cycleYear, $totals);

        return [
            'cycle_label' => $label,
            'cycle_year'  => $cycleYear,
            'eligible'    => $eligible,
            'status'      => $eligible ? 'eligible' : 'in_progress',
            'totals'      => $totals,
        ];
    }

    private static function totalsFallback(int $userId, int $cycleYear): array
    {
        // Existing caching helper in some builds
        if (function_exists('solas_cpd_get_user_cycle_totals')) {
            $t = solas_cpd_get_user_cycle_totals($userId, $cycleYear);
            if (is_array($t)) {
                return self::normaliseTotals($t);
            }
        }

        // From CertificateService fallback hook
        if (function_exists('solas_cpd_get_cycle_totals')) {
            $t = solas_cpd_get_cycle_totals($userId, $cycleYear);
            if (is_array($t)) {
                return self::normaliseTotals($t);
            }
        }

        return ['structured'=>0.0,'unstructured'=>0.0,'solas'=>0.0,'total'=>0.0];
    }

    private static function eligibleFallback(int $userId, int $cycleYear, array $totals): bool
    {
        // If plugin already has explicit check
        if (function_exists('solas_cpd_is_complete_for_cycle')) {
            $label = sprintf('%d/%02d', $cycleYear, ($cycleYear + 1) % 100);
            return (bool) solas_cpd_is_complete_for_cycle($userId, $label);
        }

        // Minimal fallback: require total >= threshold if known (default 20)
        $required = (float) get_option('solas_cpd_required_hours_total', 20);
        return (float) ($totals['total'] ?? 0) >= $required;
    }

    private static function normaliseTotals(array $t): array
    {
        $structured = (float) ($t['structured'] ?? $t['structured_hours'] ?? 0);
        $unstructured = (float) ($t['unstructured'] ?? $t['unstructured_hours'] ?? 0);
        $solas = (float) ($t['solas'] ?? $t['solas_structured'] ?? $t['solas_structured_hours'] ?? $t['solas_delivered_hours'] ?? 0);
        $total = (float) ($t['total'] ?? $t['total_hours'] ?? ($structured + $unstructured + $solas));
        return [
            'structured' => $structured,
            'unstructured' => $unstructured,
            'solas' => $solas,
            'total' => $total,
        ];
    }
}
