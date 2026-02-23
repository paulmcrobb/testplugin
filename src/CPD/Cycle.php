<?php
declare(strict_types=1);

namespace Solas\Portal\CPD;

use DateTimeImmutable;

defined('ABSPATH') || exit;

/**
 * SOLAS CPD cycle helper.
 *
 * Canonical rules (site timezone):
 * - Cycle starts: 1 November (cycle start year)
 * - Cycle ends:   30 October (following year)
 * - Grace:        30 October -> 30 November (following year)
 *
 * Naming:
 * - "cycleStartYear" refers to the first year in the label, e.g. 2025 for 2025/26.
 */
final class Cycle {

    public const START_MONTH = 11;
    public const START_DAY   = 1;

    public const END_MONTH   = 10;
    public const END_DAY     = 30;

    public const GRACE_START_MONTH = 10;
    public const GRACE_START_DAY   = 30;

    public const GRACE_END_MONTH = 11;
    public const GRACE_END_DAY   = 30;

    /**
     * Determine the cycle start year for a given date.
     */
    public static function startYearForDate(DateTimeImmutable $date): int {
        $year  = (int) $date->format('Y');
        $month = (int) $date->format('n');

        // November and December belong to the cycle that starts that same year.
        // January–October belong to the cycle that started the previous November.
        return ($month >= self::START_MONTH) ? $year : ($year - 1);
    }

    /**
     * Current cycle start year in site timezone.
     */
    public static function currentStartYear(): int {
        return self::startYearForDate(new DateTimeImmutable('now', wp_timezone()));
    }

    /**
     * Human label e.g. 2025/26.
     */
    public static function label(int $cycleStartYear): string {
        if ($cycleStartYear <= 0) return '';
        $end = $cycleStartYear + 1;
        return sprintf('%d/%02d', $cycleStartYear, ((int) substr((string) $end, -2)));
    }

    /**
     * Cycle range: Nov 1 (start year) -> Oct 30 (end year) inclusive.
     *
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    public static function range(int $cycleStartYear): array {
        $tz = wp_timezone();
        $start = new DateTimeImmutable(
            sprintf('%d-%02d-%02d 00:00:00', $cycleStartYear, self::START_MONTH, self::START_DAY),
            $tz
        );
        $endYear = $cycleStartYear + 1;
        $end = new DateTimeImmutable(
            sprintf('%d-%02d-%02d 23:59:59', $endYear, self::END_MONTH, self::END_DAY),
            $tz
        );
        return [$start, $end];
    }

    /**
     * Grace window range: Oct 30 -> Nov 30 (end year) inclusive.
     *
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    public static function graceRange(int $cycleStartYear): array {
        $tz = wp_timezone();
        $endYear = $cycleStartYear + 1;

        $start = new DateTimeImmutable(
            sprintf('%d-%02d-%02d 00:00:00', $endYear, self::GRACE_START_MONTH, self::GRACE_START_DAY),
            $tz
        );
        $end = new DateTimeImmutable(
            sprintf('%d-%02d-%02d 23:59:59', $endYear, self::GRACE_END_MONTH, self::GRACE_END_DAY),
            $tz
        );

        return [$start, $end];
    }

    /**
     * Is today (site timezone) in the grace window for the current cycle.
     */
    public static function isNowInGraceWindow(): bool {
        $now = new DateTimeImmutable('now', wp_timezone());

        // Grace window is always Oct 30 -> Nov 30 (inclusive) in the *current calendar year*.
        $year = (int) $now->format('Y');
        $tz = wp_timezone();

        $gs = new DateTimeImmutable(
            sprintf('%d-%02d-%02d 00:00:00', $year, self::GRACE_START_MONTH, self::GRACE_START_DAY),
            $tz
        );
        $ge = new DateTimeImmutable(
            sprintf('%d-%02d-%02d 23:59:59', $year, self::GRACE_END_MONTH, self::GRACE_END_DAY),
            $tz
        );

        return ($now >= $gs && $now <= $ge);
    }

    /**
     * Is a given date inside the grace window for a specific cycle (start year)?
     */
    public static function isInGraceWindowForCycle(DateTimeImmutable $date, int $cycleStartYear): bool {
        [$gs, $ge] = self::graceRange($cycleStartYear);
        return ($date >= $gs && $date <= $ge);
    }

    /**
     * Is "now" inside the grace window for a specific cycle (start year)?
     */
    public static function isNowInGraceWindowForCycle(int $cycleStartYear): bool {
        return self::isInGraceWindowForCycle(new DateTimeImmutable('now', wp_timezone()), $cycleStartYear);
    }
}
