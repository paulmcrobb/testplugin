<?php
declare(strict_types=1);

namespace Solas\Portal\CPD;

defined('ABSPATH') || exit;

/**
 * Cycle status labelling in one place.
 */
final class Status {

    public const ACTIVE     = 'active';
    public const GRACE      = 'grace';
    public const LOCKED     = 'locked';
    public const NOT_ACTIVE = 'not_active';

    /**
     * Compute status for a cycle (start year).
     */
    public static function forCycle(int $cycleStartYear, int $userId = 0): string {
        if ($cycleStartYear <= 0) return self::NOT_ACTIVE;

        $current = Cycle::currentStartYear();
        if ($cycleStartYear > $current) return self::NOT_ACTIVE;
        if ($cycleStartYear === $current) return self::ACTIVE;

        // Past cycle.
        if ($userId > 0 && Overrides::isUnlocked($userId, $cycleStartYear)) {
            return self::GRACE;
        }

        if (Cycle::isNowInGraceWindowForCycle($cycleStartYear)) {
            return self::GRACE;
        }

return LockManager::isCycleLocked($cycleStartYear, $userId) ? self::LOCKED : self::GRACE;
    }

    public static function label(string $status): string {
        switch ($status) {
            case self::ACTIVE:
                return 'Current period';
            case self::GRACE:
                return 'Grace period';
            case self::LOCKED:
                return 'Locked';
            default:
                return 'Not active';
        }
    }
}
