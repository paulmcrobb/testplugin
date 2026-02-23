<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs;

defined('ABSPATH') || exit;

final class Files {

    /**
     * Gravity Forms file upload values can be:
     * - URL string
     * - JSON array string
     * - array (already)
     * Return first URL if multiple.
     */
    public static function normalizeGfFileValueToUrl($value): string {
        if (is_array($value)) {
            return (string)($value[0] ?? '');
        }

        $str = is_string($value) ? trim($value) : '';
        if ($str === '') { return ''; }

        // JSON array?
        if ($str[0] === '[') {
            $decoded = json_decode($str, true);
            if (is_array($decoded) && isset($decoded[0])) {
                return (string)$decoded[0];
            }
        }

        return $str;
    }

    /**
     * GF checkbox field values can arrive as a string like "A, B" or array.
     * Return array of selected values.
     *
     * @return string[]
     */
    public static function checkboxSelectedValues($value): array {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }
        $str = is_string($value) ? trim($value) : '';
        if ($str === '') { return []; }
        $parts = array_map('trim', explode(',', $str));
        return array_values(array_filter($parts, fn($v)=>$v!==''));
    }
}
