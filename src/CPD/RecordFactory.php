<?php
declare(strict_types=1);

namespace Solas\Portal\CPD;

use Solas\Portal\CPD\DTO\RecordData;

defined('ABSPATH') || exit;

/**
 * CPD RecordFactory
 *
 * Centralises creation of solas_cpd_record CPT entries from multiple sources:
 * - Gravity Forms
 * - LearnDash
 * - Imports (e.g. Teams CSV)
 *
 * Adapters should build a RecordData DTO and pass it here.
 * Includes wrappers may still call createFromArray() for legacy compatibility.
 */
final class RecordFactory {
    /**
     * Validate a CPD record DTO. Returns true on success or WP_Error on failure.
     *
     * @return true|\WP_Error
     */
    public static function validate(RecordData $data) {
        if ($data->userId() <= 0) {
            return new \WP_Error('solas_cpd_record_invalid_user', 'Invalid CPD user ID.');
        }
        if ($data->cycleYear() <= 0) {
            return new \WP_Error('solas_cpd_record_invalid_cycle', 'Invalid CPD cycle year.');
        }

        if ($data->hours() < 0 || $data->minutes() < 0 || $data->points() < 0) {
            return new \WP_Error('solas_cpd_record_negative_values', 'CPD hours/minutes/points cannot be negative.');
        }

        $urls = $data->evidenceUrls();
        if (!empty($urls)) {
            if (!is_array($urls)) {
                return new \WP_Error('solas_cpd_record_invalid_evidence', 'Evidence URLs must be an array.');
            }
            foreach ($urls as $u) {
                if (!is_string($u) || $u === '') {
                    return new \WP_Error('solas_cpd_record_invalid_evidence', 'Evidence URLs must be non-empty strings.');
                }
            }
        }

        return true;
    }

    /**
     * Prepare (but do not insert) the post/meta payload for a CPD record.
     * Useful for health checks and dry runs.
     *
     * @return array{post: array<string,mixed>, meta: array<string,mixed>}|\WP_Error
     */
    public static function dryRun(RecordData $data) {
        $valid = self::validate($data);
        if (is_wp_error($valid)) return $valid;

        $category = $data->category();
        $subject  = $data->subject();
        $title = $data->title();
        if ($title === '') {
            $label = $category !== '' ? $category : 'CPD';
            if ($subject !== '') $label .= ' – ' . wp_strip_all_tags($subject);
            $title = 'CPD – ' . $label . ' – ' . wp_date('Y-m-d');
        }

        $meta = [
            'solas_user_id'     => $data->userId(),
            'solas_cycle_year'  => (string) $data->cycleYear(),
            'solas_minutes'     => $data->minutes(),
            'solas_hours'       => $data->hours(),
            'solas_points'      => $data->points(),
            'solas_created_at'  => wp_date('c'),
        ];

        if ($category !== '') $meta['solas_cpd_category'] = $category;
        if ($data->origin() !== '') $meta['solas_origin'] = $data->origin();
        if ($data->status() !== '') $meta['solas_cpd_status'] = $data->status();
        if ($data->subject() !== '') $meta['solas_cpd_subject'] = $data->subject();
        if ($data->subjectDetail() !== '') $meta['solas_cpd_subject_detail'] = $data->subjectDetail();
        if ($data->reflection() !== '') $meta['solas_reflection'] = $data->reflection();

        $urls = $data->evidenceUrls();
        if (!empty($urls)) {
            $meta['solas_evidence_urls'] = $urls;
            $meta['solas_evidence_url']  = $urls[0];
        }

        if ($data->sourceRef() !== '') $meta['solas_source_ref'] = $data->sourceRef();

        $extraMeta = $data->meta();
        if (!empty($extraMeta)) {
            foreach ($extraMeta as $k => $v) {
                if (!is_string($k) || $k === '') continue;
                $meta[$k] = $v;
            }
        }

        return [
            'post' => [
                'post_type'   => 'solas_cpd_record',
                'post_status' => $data->postStatus(),
                'post_title'  => $title,
            ],
            'meta' => $meta,
        ];
    }

    private static function log(string $message): void {
        $enabled = (bool) get_option('solas_cpd_recordfactory_debug', false);
        if (!$enabled) return;
        if (function_exists('error_log')) {
            error_log('[SOLAS CPD RecordFactory] ' . $message);
        }
    }


    /**
     * Backwards-compatible entry point for legacy array payloads.
     *
     * @param array<string,mixed> $data
     * @return int|\WP_Error Post ID on success.
     */
    public static function createFromArray(array $data) {
        $dto = RecordData::fromArray($data);
        return self::create($dto);
    }

    /**
     * Create a CPD record from a DTO.
     *
     * @return int|\WP_Error Post ID on success.
     */
    public static function create(RecordData $data) {

        $valid = self::validate($data);
        if (is_wp_error($valid)) return $valid;
        $userId = $data->userId();
        $cycleYearInt = $data->cycleYear();

        if ($userId <= 0 || $cycleYearInt <= 0) {
            return new \WP_Error('solas_cpd_record_missing_fields', 'Missing required CPD record fields.');
        }

        $category = $data->category();
        $hours    = $data->hours();
        $minutes  = $data->minutes();
        $points   = $data->points();

        $origin = $data->origin();
        $status = $data->status();

        $subject = $data->subject();
        $subjectDetail = $data->subjectDetail();
        $reflection = $data->reflection();

        $evidenceUrls = $data->evidenceUrls();

        $sourceRef = $data->sourceRef();

        // Idempotency: if source_ref is provided and already exists, return existing post ID.
        if ($sourceRef !== '') {
            $existing = self::findBySourceRef($sourceRef, $userId);
            if ($existing) {
                self::log('Idempotent hit: sourceRef=' . $sourceRef . ' userId=' . $userId . ' -> postId=' . $existing);
                return $existing;
            }
        }

        $postStatus = $data->postStatus();

        $title = $data->title();
        if ($title === '') {
            $label = $category !== '' ? $category : 'CPD';
            if ($subject !== '') $label .= ' – ' . wp_strip_all_tags($subject);
            $title = 'CPD – ' . $label . ' – ' . wp_date('Y-m-d');
        }

        $postId = wp_insert_post([
            'post_type'   => 'solas_cpd_record',
            'post_status' => $postStatus,
            'post_title'  => $title,
        ], true);

        if (is_wp_error($postId) || !$postId) return $postId;

        self::log('Created CPD record postId=' . (string) $postId . ' userId=' . $userId . ' cycle=' . (string) $cycleYearInt . ' origin=' . $origin);

        update_post_meta($postId, 'solas_user_id', $userId);
        update_post_meta($postId, 'solas_cycle_year', (string) $cycleYearInt);
        update_post_meta($postId, 'solas_minutes', $minutes);
        update_post_meta($postId, 'solas_hours', $hours);
        update_post_meta($postId, 'solas_points', $points);

        if ($category !== '') update_post_meta($postId, 'solas_cpd_category', $category);
        if ($origin !== '') update_post_meta($postId, 'solas_origin', $origin);
        if ($status !== '') update_post_meta($postId, 'solas_cpd_status', $status);

        if ($subject !== '') update_post_meta($postId, 'solas_cpd_subject', $subject);
        if ($subjectDetail !== '') update_post_meta($postId, 'solas_cpd_subject_detail', $subjectDetail);

        if ($reflection !== '') update_post_meta($postId, 'solas_reflection', $reflection);

        if (!empty($evidenceUrls)) {
            update_post_meta($postId, 'solas_evidence_urls', $evidenceUrls);
            update_post_meta($postId, 'solas_evidence_url', $evidenceUrls[0]);
        }

        if ($sourceRef !== '') update_post_meta($postId, 'solas_source_ref', $sourceRef);

        update_post_meta($postId, 'solas_created_at', wp_date('c'));

        $extraMeta = $data->meta();
        if (!empty($extraMeta)) {
            foreach ($extraMeta as $k => $v) {
                if (!is_string($k) || $k === '') continue;
                update_post_meta($postId, $k, $v);
            }
        }

        return (int) $postId;
    }

    private static function findBySourceRef(string $sourceRef, int $userId = 0): int {
        $args = [
            'post_type'      => 'solas_cpd_record',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'solas_source_ref',
                    'value' => $sourceRef,
                ],
            ],
        ];
        if ($userId > 0) {
            $args['meta_query'][] = [
                'key'   => 'solas_user_id',
                'value' => (string) $userId,
            ];
        }
        $q = new \WP_Query($args);
        if (!empty($q->posts[0])) return (int) $q->posts[0];
        return 0;
    }
}
