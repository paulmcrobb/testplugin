<?php
declare(strict_types=1);

namespace Solas\Portal\CPD;

defined('ABSPATH') || exit;

/**
 * Stores exceptional per-user unlock overrides for historic CPD cycles.
 */
final class Overrides {

    public const META_KEY = 'solas_cpd_unlocked_cycles';

    /**
     * @return int[] list of cycle start years unlocked
     */
    public static function getUnlockedCycles(int $userId): array {
        if ($userId <= 0) return [];
        $raw = get_user_meta($userId, self::META_KEY, true);

        if (is_array($raw)) {
            $years = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            // tolerate CSV for backwards compatibility
            $years = array_map('trim', explode(',', $raw));
        } else {
            $years = [];
        }

        $out = [];
        foreach ($years as $y) {
            $i = (int) $y;
            if ($i > 0) $out[] = $i;
        }

        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    public static function isUnlocked(int $userId, int $cycleStartYear): bool {
        if ($userId <= 0 || $cycleStartYear <= 0) return false;
        $years = self::getUnlockedCycles($userId);
        return in_array($cycleStartYear, $years, true);
    }

    public static function unlock(int $userId, int $cycleStartYear): void {
        if ($userId <= 0 || $cycleStartYear <= 0) return;
        $years = self::getUnlockedCycles($userId);
        $years[] = $cycleStartYear;
        $years = array_values(array_unique($years));
        sort($years);
        update_user_meta($userId, self::META_KEY, $years);
    }

    public static function relock(int $userId, int $cycleStartYear): void {
        if ($userId <= 0 || $cycleStartYear <= 0) return;
        $years = array_values(array_filter(
            self::getUnlockedCycles($userId),
            static fn(int $y): bool => $y !== $cycleStartYear
        ));
        sort($years);
        update_user_meta($userId, self::META_KEY, $years);
    }
}
