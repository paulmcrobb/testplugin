<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs\Portal;

defined('ABSPATH') || exit;

final class CandidateDashboard {

    public static function render(): string {
        if (!is_user_logged_in()) {
                return '<p>Please log in to view your dashboard.</p>';
            }

            $user_id = (int) get_current_user_id();

            $resume_count = solas_jobs_portal_count_user_resumes($user_id);

            $apps_q = new \WP_Query([
                'post_type'      => 'solas_job_app',
                'post_status'    => ['publish', 'private', 'draft'],
                'posts_per_page' => 1,
                'author'         => $user_id,
                'fields'         => 'ids',
            ]);
            $app_count = (int) $apps_q->found_posts;

            $out  = '<div style="border:1px solid #e5e5e5;padding:14px;border-radius:10px;background:#fff;">';
            $out .= '<h3 style="margin-top:0;">Candidate Dashboard</h3>';
            $out .= '<p><strong>Resumes saved:</strong> ' . esc_html((string)$resume_count) . '</p>';
            $out .= '<p><strong>Applications submitted:</strong> ' . esc_html((string)$app_count) . '</p>';
            $out .= '<hr>';
            $out .= '<p><a class="' . esc_attr(solas_wc_button_classes('primary')) . '" href="' . esc_url(site_url('/current-jobs-available/')) . '">View current jobs</a></p>';
            $out .= '<p><a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url(site_url('/create-my-resume/')) . '">Create / edit resume</a></p>';
            $out .= '<p><a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url(site_url('/manage-my-resumes/')) . '">Manage my resumes</a></p>';
            $out .= '<p><a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url(site_url('/my-job-applications/')) . '">My job applications</a></p>';
            $out .= '</div>';

            return $out;
    }

}
