<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Certificates;

defined('ABSPATH') || exit;

/**
 * Normalises CPD totals + eligibility for certificate generation and UI.
 *
 * Important: historic builds have used BOTH of these for cycle storage:
 *  - solas_cycle_year meta stored as string label like "2025/26"
 *  - solas_cycle_year meta stored as int start year like 2025
 *
 * This class therefore checks both shapes (label + int) via existing helpers.
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

        $totals_label = null;
        $eligible_label = null;

        // 1) Prefer dashboard compliance (uses approved records and label-style meta)
        if (function_exists('solas_cpd_dashboard_get_compliance')) {
            $c = solas_cpd_dashboard_get_compliance($userId, $label);
            if (is_array($c)) {
                $totals_label = [
                    'structured'   => (float) ($c['structured'] ?? $c['structured_hours'] ?? 0),
                    'unstructured' => (float) ($c['unstructured'] ?? $c['unstructured_hours'] ?? 0),
                    'solas'        => (float) ($c['solas'] ?? $c['solas_structured_hours'] ?? 0),
                ];
                $totals_label['total'] = (float) ($c['total'] ?? ($totals_label['structured'] + $totals_label['unstructured'] + $totals_label['solas']));
                $eligible_label = !empty($c['compliant']) || !empty($c['eligible']);
            }
        }

        // 2) Int-style totals (uses cached query and int-style meta)
        $totals_int = self::totalsFromInt($userId, $cycleYear);

        // Choose whichever totals look non-zero (prefer label totals if they exist and have data)
        $use_totals = $totals_label;
        if (!is_array($use_totals) || (float)($use_totals['total'] ?? 0) <= 0) {
            $use_totals = $totals_int;
        }

        // Eligibility: prefer explicit function (int-style) if available, otherwise label compliance, otherwise fallback
        $eligible = null;

        if (function_exists('solas_cpd_is_complete_for_cycle')) {
            // Note: this function expects int cycle start year in this plugin build.
            $eligible = (bool) solas_cpd_is_complete_for_cycle($userId, $cycleYear);
        } elseif ($eligible_label !== null) {
            $eligible = (bool) $eligible_label;
        } else {
            $eligible = self::eligibleFallback($use_totals);
        }

        return [
            'cycle_label' => $label,
            'cycle_year'  => $cycleYear,
            'eligible'    => (bool) $eligible,
            'status'      => $eligible ? 'eligible' : 'in_progress',
            'totals'      => $use_totals ?? ['structured'=>0.0,'unstructured'=>0.0,'solas'=>0.0,'total'=>0.0],
        ];
    }

    private static function totalsFromInt(int $userId, int $cycleYear): array
    {
        if (function_exists('solas_cpd_get_user_cycle_totals')) {
            $t = solas_cpd_get_user_cycle_totals($userId, $cycleYear);
            if (is_array($t)) {
                return self::normaliseTotals($t);
            }
        }
        return ['structured'=>0.0,'unstructured'=>0.0,'solas'=>0.0,'total'=>0.0];
    }

    private static function eligibleFallback(array $totals): bool
    {
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
