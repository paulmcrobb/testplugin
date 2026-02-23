<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Adapters;

use Solas\Portal\CPD\DTO\RecordData;

defined('ABSPATH') || exit;

/**
 * LearnDash quiz completion -> CPD record adapter.
 */
final class LearnDashQuizAdapter {

    /**
     * @return array|\WP_Error
     */
    public static function toRecordData(
        array $quizData,
        \WP_User $user,
        int $quizId,
        int $courseId,
        string $courseCycleLabel,
        int $completionTs,
        float $points,
        bool $isInGrace
    ) {
        $userId = (int) $user->ID;
        if ($userId <= 0 || $quizId <= 0 || $courseId <= 0 || $courseCycleLabel === '') {
            return new \WP_Error('solas_cpd_ld_missing_fields', 'Missing LearnDash CPD fields.');
        }

        $cycleYear = function_exists('solas_cpd_cycle_start_year_from_label')
            ? (int) solas_cpd_cycle_start_year_from_label($courseCycleLabel)
            : 0;
        if ($cycleYear <= 0) {
            return new \WP_Error('solas_cpd_ld_invalid_cycle', 'Invalid LearnDash course cycle label.');
        }

        $sourceRef = 'ld:quiz:' . $quizId . ':user:' . $userId;

        $quizTitle = get_the_title($quizId);
        $courseTitle = get_the_title($courseId);

        return new RecordData(
            $userId,
            (int) $cycleYear,
            'structured',
            'learndash',
            'approved',
            'publish',
            (float) $points,
            0.0,
            (float) $points,
            '',
            '',
            '',
            [],
            'CPD – ' . $user->display_name . ' – ' . wp_date('Y-m-d', $completionTs) . ' – ' . $points . 'h (LD)',
            $sourceRef,
            [
                'solas_cycle_label'  => (string) $courseCycleLabel,
                'solas_period_year'  => (int) $cycleYear,
                'solas_title'        => 'SOLAS CPD Quiz: ' . $quizTitle,
                'solas_description'  => 'Course: ' . $courseTitle,
                'solas_course_id'    => (int) $courseId,
                'solas_quiz_id'      => (int) $quizId,
                'solas_is_grace'     => ($isInGrace ? 1 : 0),
                'solas_completed_at' => wp_date('c', $completionTs),
                'solas_completed_ts' => (int) $completionTs,
            ]
        );
}
}
