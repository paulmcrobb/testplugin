<?php
declare(strict_types=1);

namespace Solas\Portal\CPD;

use DateTimeImmutable;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * CPD cycle locking rules.
 */
final class LockManager {

    public static function isEnabled(): bool {
        return get_option('solas_cpd_lock_enabled', 'yes') !== 'no';
    }

    /**
     * End of grace window (inclusive) is controlled by option MM-DD.
     * Default: 11-30.
     */
    public static function graceCutoffMd(): string {
        $md = (string) get_option('solas_cpd_lock_grace_cutoff', '11-30');
        return preg_match('/^\d{2}-\d{2}$/', $md) ? $md : '11-30';
    }

    /**
     * Accept formats: 2025, "2025", "2025/26", "2025/2026".
     */
    public static function normalizeCycleStartYear($cycle): int {
        if (is_int($cycle)) return $cycle;
        $s = trim((string) $cycle);
        if ($s === '') return 0;

        if (preg_match('/^(\d{4})\s*\/\s*(\d{2,4})$/', $s, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^\d{4}$/', $s)) return (int) $s;

        return 0;
    }

    /**
     * For cycle start year 2025 (2025/26), lock cutoff is 2026-11-30 23:59:59 by default.
     */
    public static function cutoffTimestamp(int $cycleStartYear): int {
        $md = self::graceCutoffMd();
        $endYear = $cycleStartYear + 1;
        $dt = new DateTimeImmutable($endYear . '-' . $md . ' 23:59:59', wp_timezone());
        return (int) $dt->format('U');
    }

    public static function userCanOverride(?int $userId = null): bool {
        // Keep strict: admins only.
        return current_user_can('manage_options');
    }

    /**
     * Lock rules:
     * - Locking can be disabled globally.
     * - Current and future cycles are never locked.
     * - Past cycles lock after grace cutoff, unless per-user override exists.
     */
    public static function isCycleLocked($cycleStartYear, int $userId = 0): bool {
        if (!self::isEnabled()) return false;

        $cycleStartYear = self::normalizeCycleStartYear($cycleStartYear);
        if ($cycleStartYear <= 0) return false;

        $userId = (int) $userId;
        if ($userId > 0 && Overrides::isUnlocked($userId, $cycleStartYear)) {
            return false;
        }

        $current = Cycle::currentStartYear();
        if ($current && $cycleStartYear >= $current) {
            return false;
        }

        return time() > self::cutoffTimestamp($cycleStartYear);
    }

    /**
     * Enforce lock, returning WP_Error when blocked.
     *
     * @return true|WP_Error
     */
    public static function enforceNotLocked($cycleStartYear) {
        $cycleStartYear = self::normalizeCycleStartYear($cycleStartYear);
        if ($cycleStartYear <= 0) return true;

        if (self::isCycleLocked($cycleStartYear) && !self::userCanOverride()) {
            $md = self::graceCutoffMd();
            $cutoff = ($cycleStartYear + 1) . '-' . $md;
            return new WP_Error(
                'solas_cpd_cycle_locked',
                'This CPD cycle is locked (cut-off was ' . esc_html($cutoff) . '). Please contact SOLAS if you need to amend historic CPD.'
            );
        }

        return true;
    }
}
