<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs\Portal;

defined('ABSPATH') || exit;

final class ApplyClicks
{
    public static function register(): void
    {
        add_action('admin_post_nopriv_solas_job_apply_click', [self::class, 'handle']);
        add_action('admin_post_solas_job_apply_click', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        $jobId = isset($_GET['job_id']) ? absint($_GET['job_id']) : 0;
        if (!$jobId) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        // Increment click count
        $clicks = (int) get_post_meta($jobId, '_solas_job_apply_clicks', true);
        update_post_meta($jobId, '_solas_job_apply_clicks', $clicks + 1);

        // Determine destination
        $dest = get_permalink($jobId);

        $url = (string) get_post_meta($jobId, '_solas_job_apply_url', true);
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $dest = $url;
        } elseif (function_exists('solas_jobs_portal_apply_page_url')) {
            $maybe = (string) solas_jobs_portal_apply_page_url($jobId);
            if ($maybe !== '') {
                $dest = $maybe;
            }
        }

        wp_safe_redirect($dest);
        exit;
    }
}
