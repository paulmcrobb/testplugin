<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs\Portal;

defined('ABSPATH') || exit;

final class Applications {

    public static function render(): string {
        if (!is_user_logged_in()) {
                return '<p>Please log in to view your applications.</p>';
            }

            $user_id = (int) get_current_user_id();

            $q = new \WP_Query([
                'post_type'      => 'solas_job_app',
                'post_status'    => ['publish', 'private', 'draft'],
                'posts_per_page' => 50,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'author'         => $user_id,
                'fields'         => 'ids',
            ]);

            if (!$q->posts) {
                return '<p>You have not made any job applications yet.</p>';
            }

            ob_start();
            echo '<div style="border:1px solid #e5e5e5;padding:14px;border-radius:10px;background:#fff;">';
            echo '<h3 style="margin-top:0;">My Job Applications</h3>';

            echo '<table style="width:100%;border-collapse:collapse;">';
            echo '<thead><tr>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Date</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Job</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Status</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">CV</th>'
                . '</tr></thead><tbody>';

            foreach ($q->posts as $app_id) {
                $job_id = (int) get_post_meta($app_id, 'solas_job_id', true);
                $status = (string) get_post_meta($app_id, 'solas_application_status', true);
                $cv_url = (string) get_post_meta($app_id, 'solas_cv_url', true);

                $date = get_the_date('d/m/Y H:i', $app_id);
                $job_title = $job_id ? get_the_title($job_id) : '—';
                $job_link  = $job_id ? get_permalink($job_id) : '';

                echo '<tr>';
                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html($date) . '</td>';
                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . ($job_link ? '<a href="' . esc_url($job_link) . '">' . esc_html($job_title) . '</a>' : esc_html($job_title)) . '</td>';
                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html($status ?: 'submitted') . '</td>';
                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . ($cv_url ? '<a href="' . esc_url($cv_url) . '" target="_blank" rel="noopener">Download</a>' : '—') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';

            return (string) ob_get_clean();
    }

}
