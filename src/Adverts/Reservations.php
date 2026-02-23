<?php
declare(strict_types=1);

namespace Solas\Portal\Adverts;

use DateTimeImmutable;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Soft reservations to prevent checkout race conditions.
 * Stored in a single option so we can query overlaps without scanning transients.
 *
 * Shape:
 *  option solas_adverts_reservations = [
 *    token => [slot,start,end,advert_id,user_id,expires_at,created_at]
 *  ]
 */
final class Reservations {

    public const OPTION_KEY = 'solas_adverts_reservations';
    public const TTL_SECONDS = 30 * MINUTE_IN_SECONDS;

    /** @return array<string,array<string,mixed>> */
    public static function getAll(): array {
        $all = get_option(self::OPTION_KEY, []);
        return is_array($all) ? $all : [];
    }

    /** @param array<string,array<string,mixed>> $all */
    public static function putAll(array $all): void {
        update_option(self::OPTION_KEY, $all, false);
    }

    public static function makeToken(): string {
        return wp_generate_uuid4();
    }

    /**
     * Create a reservation.
     * @return string token
     */
    public static function create(string $slot, string $startIso, string $endIso, int $advertId, int $userId): string {
        $slot = sanitize_key($slot);
        $token = self::makeToken();

        $now = time();
        $all = self::getAll();

        // prune expired
        foreach ($all as $t => $row) {
            $exp = isset($row['expires_at']) ? (int) $row['expires_at'] : 0;
            if ($exp > 0 && $exp <= $now) {
                unset($all[$t]);
            }
        }

        $all[$token] = [
            'slot' => $slot,
            'start' => $startIso,
            'end' => $endIso,
            'advert_id' => (int) $advertId,
            'user_id' => (int) $userId,
            'expires_at' => $now + self::TTL_SECONDS,
            'created_at' => $now,
        ];

        self::putAll($all);
        return $token;
    }

    public static function releaseToken(string $token): void {
        $token = sanitize_text_field($token);
        if ($token === '') return;

        $all = self::getAll();
        if (isset($all[$token])) {
            unset($all[$token]);
            self::putAll($all);
        }
    }

    public static function releaseForAdvert(int $advertId): void {
        $advertId = (int) $advertId;
        if ($advertId <= 0) return;

        $all = self::getAll();
        $changed = false;
        foreach ($all as $token => $row) {
            if ((int)($row['advert_id'] ?? 0) === $advertId) {
                unset($all[$token]);
                $changed = true;
            }
        }
        if ($changed) self::putAll($all);
    }

    public static function countOverlaps(string $slot, string $startIso, string $endIso): int {
        $slot = sanitize_key($slot);
        $all = self::getAll();
        $now = time();

        $count = 0;
        foreach ($all as $token => $row) {
            $exp = (int)($row['expires_at'] ?? 0);
            if ($exp > 0 && $exp <= $now) continue;

            if (($row['slot'] ?? '') !== $slot) continue;

            $s = (string)($row['start'] ?? '');
            $e = (string)($row['end'] ?? '');
            if ($s === '' || $e === '') continue;

            if (self::rangesOverlap($s, $e, $startIso, $endIso)) {
                $count++;
            }
        }

        return $count;
    }

    public static function releaseCart(): void {
        if (!function_exists('WC')) return;
        $cart = WC()->cart;
        if (!$cart) return;

        foreach ($cart->get_cart() as $item) {
            $token = (string)($item['solas_reservation_token'] ?? '');
            if ($token !== '') {
                self::releaseToken($token);
            }
        }
    }

    private static function rangesOverlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool {
        // Treat ISO dates as comparable; fall back to timestamps.
        try {
            $aS = new DateTimeImmutable($aStart, wp_timezone());
            $aE = new DateTimeImmutable($aEnd, wp_timezone());
            $bS = new DateTimeImmutable($bStart, wp_timezone());
            $bE = new DateTimeImmutable($bEnd, wp_timezone());
            return $aS <= $bE && $bS <= $aE;
        } catch (Throwable $t) {
            return ($aStart <= $bEnd) && ($bStart <= $aEnd);
        }
    }
}
