<?php
declare(strict_types=1);

namespace Solas\Portal\Adverts;

use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

final class Availability {

    /**
     * Returns an array keyed by date (Y-m-d) with count of active adverts for that day.
     *
     * @return array<string,int>
     */
    public static function buildDayCounts(string $slot, string $startYmd, string $endYmd): array {
        $start = new \DateTimeImmutable($startYmd, wp_timezone());
        $end   = new \DateTimeImmutable($endYmd, wp_timezone());

        $dates = self::daysBetweenInclusive($start, $end);

        $counts = [];
        foreach ($dates as $ymd) {
            $counts[$ymd] = 0;
        }

        $q = new \WP_Query([
            'post_type'      => 'solas_advert',
            'post_status'    => ['publish'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                ['key' => 'solas_slot', 'value' => $slot],
                // Overlap: advert_start <= end AND advert_end >= start
                ['key' => 'solas_start_date_ymd', 'value' => $endYmd, 'compare' => '<=', 'type' => 'CHAR'],
                ['key' => 'solas_end_date_ymd',   'value' => $startYmd, 'compare' => '>=', 'type' => 'CHAR'],
            ],
        ]);

        foreach ($q->posts as $postId) {
            $aStart = (string) get_post_meta($postId, 'solas_start_date_ymd', true);
            $aEnd   = (string) get_post_meta($postId, 'solas_end_date_ymd', true);
            if (!$aStart || !$aEnd) { continue; }

            foreach ($dates as $ymd) {
                if ($ymd >= $aStart && $ymd <= $aEnd) {
                    $counts[$ymd] = ($counts[$ymd] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    /**
     * @return string[] list of dates Y-m-d inclusive
     */
    public static function daysBetweenInclusive(\DateTimeImmutable $start, \DateTimeImmutable $end): array {
        if ($end < $start) { return []; }

        $out = [];
        $cur = $start;
        while ($cur <= $end) {
            $out[] = $cur->format('Y-m-d');
            $cur = $cur->modify('+1 day');
        }
        return $out;
    }

    public static function restCheckRange(WP_REST_Request $req): WP_REST_Response {
        $slot  = sanitize_key((string) $req->get_param('slot'));
        $start = (string) $req->get_param('start');
        $end   = (string) $req->get_param('end');

        if (!$slot || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Invalid parameters'], 400);
        }

        // capacity rules: header variants = 1, MPU = 4 (as per current spec)
        $capacity = (str_contains($slot, 'mpu')) ? 4 : 1;

        $counts = self::buildDayCounts($slot, $start, $end);

        $max = 0;
        foreach ($counts as $c) { $max = max($max, (int) $c); }

        return new WP_REST_Response([
            'ok' => true,
            'slot' => $slot,
            'start' => $start,
            'end' => $end,
            'capacity' => $capacity,
            'maxBookedOnAnyDay' => $max,
            'available' => ($max < $capacity),
        ]);
    }

    public static function restAvailability(WP_REST_Request $req): WP_REST_Response {
        $slot  = sanitize_key((string) $req->get_param('slot'));
        $start = (string) $req->get_param('start');
        $end   = (string) $req->get_param('end');

        if (!$slot || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Invalid parameters'], 400);
        }

        $capacity = (str_contains($slot, 'mpu')) ? 4 : 1;

        $counts = self::buildDayCounts($slot, $start, $end);

        $fullDays = [];
        foreach ($counts as $ymd => $count) {
            if ((int)$count >= $capacity) {
                $fullDays[] = $ymd;
            }
        }

        return new WP_REST_Response([
            'ok' => true,
            'slot' => $slot,
            'start' => $start,
            'end' => $end,
            'capacity' => $capacity,
            'counts' => $counts,
            'fullDays' => $fullDays,
        ]);
    }
}
