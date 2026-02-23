<?php
declare(strict_types=1);

namespace Solas\Portal\Woo;

defined('ABSPATH') || exit;

/**
 * Canonical membership plan gates for SOLAS Portal.
 *
 * Uses admin-configured plan IDs (option: solas_plan_gate_map) where available.
 * Falls back to filter-provided identifiers for flexibility.
 */
final class PlanGates {

    public const OPTION_MAP = 'solas_plan_gate_map';

    /**
     * Returns configured plan IDs map: ['full'=>int,'associate'=>int,'honorary'=>int,'student'=>int]
     */
    public static function getConfiguredPlanMap(): array {
        $map = get_option(self::OPTION_MAP, []);
        if (!is_array($map)) $map = [];
        $out = [];
        foreach (['full','associate','honorary','student'] as $k) {
            $out[$k] = isset($map[$k]) ? (int) $map[$k] : 0;
        }
        return $out;
    }

    /**
     * Default identifiers used when no plan IDs are configured.
     * Values can be plan IDs, slugs, or names.
     */
    public static function getDefaultIdentifiers(): array {
        $defaults = [
            'full'      => ['full-member', 'full member', 'full'],
            'associate' => ['associate-member', 'associate member', 'associate'],
            'honorary'  => ['honorary-member', 'honorary member', 'honorary'],
            'student'   => ['student-member', 'student member', 'student'],
        ];

        /**
         * Filter: solas_membership_plan_identifiers
         * Return array with keys full/associate/honorary/student; each value may be scalar or array.
         */
        $filtered = apply_filters('solas_membership_plan_identifiers', $defaults);
        return is_array($filtered) ? $filtered : $defaults;
    }

    private static function isInAnyPlan(int $userId, $identifiers): bool {
        if ($userId <= 0) return false;
        if (!Memberships::isActive()) return false;

        if (is_array($identifiers)) {
            foreach ($identifiers as $id) {
                if ($id === null || $id === '') continue;
                if (Memberships::isUserActiveMember($userId, $id)) return true;
            }
            return false;
        }

        return Memberships::isUserActiveMember($userId, $identifiers);
    }

    private static function isInConfiguredOrDefault(int $userId, string $key): bool {
        $map = self::getConfiguredPlanMap();
        if (!empty($map[$key])) {
            return Memberships::isUserActiveMember($userId, (int) $map[$key]);
        }
        $ids = self::getDefaultIdentifiers();
        return self::isInAnyPlan($userId, $ids[$key] ?? []);
    }

    public static function isFullMember($userId): bool {
        return self::isInConfiguredOrDefault((int) $userId, 'full');
    }

    public static function isAssociateMember($userId): bool {
        return self::isInConfiguredOrDefault((int) $userId, 'associate');
    }

    public static function isHonoraryMember($userId): bool {
        return self::isInConfiguredOrDefault((int) $userId, 'honorary');
    }

    public static function isStudentMember($userId): bool {
        return self::isInConfiguredOrDefault((int) $userId, 'student');
    }

    /**
     * CPD access rule:
     * - admins always allowed
     * - student members: no CPD access
     * - full/associate/honorary: allowed
     * - if Memberships plugin not active: allow (fail-open for dev/staging)
     */
    public static function canAccessCpd($userId): bool {
        $userId = (int) $userId;
        if ($userId <= 0) return false;
        if (user_can($userId, 'manage_options')) return true;

        if (!Memberships::isActive()) return true;

        if (self::isStudentMember($userId)) return false;

        return self::isFullMember($userId) || self::isAssociateMember($userId) || self::isHonoraryMember($userId);
    }

    /**
     * Optional employer jobs tools access rule.
     * Defaults to allow unless a list of plan identifiers is provided via filter 'solas_jobs_employer_plan_identifiers'.
     */
    public static function canAccessEmployerJobsTools($userId): bool {
        $userId = (int) $userId;
        if ($userId <= 0) return false;
        if (user_can($userId, 'manage_options')) return true;

        // If Memberships isn't active, fail-open.
        if (!Memberships::isActive()) return true;

        $idents = apply_filters('solas_jobs_employer_plan_identifiers', []);
        // If nothing configured, allow.
        if (empty($idents)) return true;

        // Accept scalar or array; if array of groups, flatten.
        if (is_array($idents)) {
            return self::isInAnyPlan($userId, $idents);
        }
        return Memberships::isUserActiveMember($userId, $idents);
    }
}