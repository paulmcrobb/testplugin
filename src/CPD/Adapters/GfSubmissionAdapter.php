<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Adapters;

use Solas\Portal\CPD\DTO\RecordData;

defined('ABSPATH') || exit;

/**
 * Gravity Forms -> CPD record adapter.
 *
 * Turns a GF $entry into the canonical RecordFactory::create() payload.
 * Keeps all GF field mapping in one place so includes stay thin.
 */
final class GfSubmissionAdapter {

    /**
     * @param array $entry GF entry array
     * @param int $formId GF form ID
     * @param string $category structured|unstructured
     * @param int $userId user to attribute the record to
     * @return array|\WP_Error RecordFactory payload or WP_Error
     */
    public static function toRecordData(array $entry, int $formId, string $category, int $userId) {
        $entryId = isset($entry['id']) ? (int) $entry['id'] : 0;
        if ($userId <= 0 || $entryId <= 0) {
            return new \WP_Error('solas_cpd_gf_missing_fields', 'Missing user or entry id.');
        }

        // Field maps
        if ($formId === 1) {
            $cycle         = self::rgar($entry, '1');
            $subject       = self::rgar($entry, '9');
            $minutes       = self::rgar($entry, '5');
            $reflection    = self::rgar($entry, '6');
            $evidenceRaw   = self::rgar($entry, '7');
            $subjectDetail = self::rgar($entry, '8');
        } elseif ($formId === 2) {
            $cycle         = self::rgar($entry, '1');
            $subject       = self::rgar($entry, '8');
            $minutes       = self::rgar($entry, '4');
            $reflection    = self::rgar($entry, '5');
            $evidenceRaw   = self::rgar($entry, '6');
            $subjectDetail = self::rgar($entry, '7');
        } else {
            return new \WP_Error('solas_cpd_gf_unknown_form', 'Unknown CPD form id.');
        }

        $cycleYear = function_exists('solas_cpd_normalize_cycle_year')
            ? (int) solas_cpd_normalize_cycle_year($cycle)
            : (int) $cycle;
        if ($cycleYear <= 0) {
            return new \WP_Error('solas_cpd_gf_invalid_cycle', 'Invalid cycle year.');
        }

        // Enforce locking (no behavioural change: uses existing include function)
        if (function_exists('solas_cpd_enforce_cycle_not_locked')) {
            $ok = solas_cpd_enforce_cycle_not_locked($cycleYear);
            if (is_wp_error($ok)) return $ok;
        }

        $minutesF = (float) $minutes;
        $hours    = self::minutesToHours($minutesF);
        $evidence = self::normalizeUploadUrls($evidenceRaw);

        $sourceRef = 'gf:entry:' . $entryId;

        // Title consistent with previous implementation
        $title = ($category === 'structured') ? 'Structured CPD' : 'Unstructured CPD';
        if ($subject !== '') $title .= ' – ' . wp_strip_all_tags((string) $subject);

        return new RecordData(
            $userId,
            (int) $cycleYear,
            (string) $category,
            'gravity_forms',
            'approved',
            'publish',
            (float) $hours,
            (float) $minutesF,
            (float) $hours,
            (string) $subject,
            (string) $subjectDetail,
            (string) $reflection,
            $evidence,
            'CPD – ' . $title . ' – ' . wp_date('Y-m-d'),
            $sourceRef,
            []
        );
}

    private static function rgar(array $array, string $key, string $default = ''): string {
        return array_key_exists($key, $array) ? (string) $array[$key] : $default;
    }

    private static function minutesToHours(float $minutes): float {
        if ($minutes <= 0) return 0.0;
        return round($minutes / 60.0, 4);
    }

    /**
     * GF can store single URL, JSON array, or a PHP array.
     * @param mixed $raw
     * @return array<int,string>
     */
    private static function normalizeUploadUrls($raw): array {
        if (empty($raw)) return [];
        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', array_map('strval', $raw))));
        }
        $raw = trim((string) $raw);
        if ($raw === '') return [];

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('trim', array_map('strval', $decoded))));
            }
        }

        return [$raw];
    }
}
