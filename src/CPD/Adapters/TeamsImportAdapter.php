<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Adapters;

use Solas\Portal\CPD\DTO\RecordData;

defined('ABSPATH') || exit;

/**
 * Teams CSV import row -> CPD record adapter.
 */
final class TeamsImportAdapter {

    /**
     * @param array $row Normalised ready_row from preview stage
     * @return array|\WP_Error RecordFactory payload
     */
    public static function toRecordData(array $row) {
        $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
        $cycleYear = isset($row['cycle_year']) ? (int) $row['cycle_year'] : 0;
        $sourceRef = isset($row['source_ref']) ? (string) $row['source_ref'] : '';
        if ($userId <= 0 || $cycleYear <= 0 || $sourceRef === '') {
            return new \WP_Error('solas_cpd_teams_missing_fields', 'Missing Teams import fields.');
        }

        $title = isset($row['title']) ? (string) $row['title'] : '';
        $date  = isset($row['date']) ? (string) $row['date'] : wp_date('Y-m-d');
        $minutes = isset($row['minutes']) ? (float) $row['minutes'] : 0.0;
        $hours   = isset($row['hours']) ? (float) $row['hours'] : 0.0;
        $category = isset($row['category']) ? (string) $row['category'] : 'structured';
        $subject = isset($row['subject']) ? (string) $row['subject'] : '';
        $subjectDetail = isset($row['subject_detail']) ? (string) $row['subject_detail'] : '';

        return new RecordData(
            $userId,
            (int) $cycleYear,
            (string) $category,
            'teams_import',
            'approved',
            'publish',
            (float) $hours,
            (float) $minutes,
            (float) $hours,
            (string) $subject,
            (string) $subjectDetail,
            '',
            [],
            'Teams CPD — ' . $title,
            (string) $sourceRef,
            [
                'solas_title'       => $title,
                'solas_date'        => $date,
                'solas_import_type' => 'teams',
            ]
        );
}
}
