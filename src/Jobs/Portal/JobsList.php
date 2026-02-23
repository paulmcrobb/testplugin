<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs\Portal;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

final class JobsList {

    public static function render(): string {
        $now = new \DateTimeImmutable('now', wp_timezone());

            // --------------------------------------------------------
            // Filters (GET)
            // --------------------------------------------------------
            $q_search   = isset($_GET['q']) ? sanitize_text_field((string) $_GET['q']) : '';
            $q_location = isset($_GET['location']) ? sanitize_text_field((string) $_GET['location']) : '';
            $q_contract = isset($_GET['contract']) ? sanitize_text_field((string) $_GET['contract']) : '';
            $q_remote   = isset($_GET['remote']) ? sanitize_text_field((string) $_GET['remote']) : '';

            $per_page = (int) get_option('solas_jobs_per_page', 10);
            $sort     = (string) get_option('solas_jobs_default_sort', 'newest');

            $orderby = 'date';
            $order   = 'DESC';
            $metaKey = '';

            if ($sort === 'closing_soon') {
                $orderby = 'meta_value';
                $order   = 'ASC';
                $metaKey = 'solas_job_expires_at';
            }

            $meta_query = [
                ['key' => 'solas_job_status', 'value' => 'active'],
            ];

            if ($q_location !== '') {
                $meta_query[] = ['key' => 'solas_location', 'value' => $q_location, 'compare' => 'LIKE'];
            }
            if ($q_contract !== '') {
                $meta_query[] = ['key' => 'solas_contract_type', 'value' => $q_contract, 'compare' => 'LIKE'];
            }
            if ($q_remote !== '' && $q_remote !== 'any') {
                $meta_query[] = ['key' => 'solas_remote', 'value' => $q_remote, 'compare' => 'LIKE'];
            }

            $args = [
                'post_type'      => 'solas_job',
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'orderby'        => $orderby,
                'order'          => $order,
                'meta_query'     => $meta_query,
                // keyword search
                's'              => $q_search ?: '',
            ];

            if ($metaKey) {
                $args['meta_key'] = $metaKey;
            }

            $q = new \WP_Query($args);

            // --------------------------------------------------------
            // Filter UI
            // --------------------------------------------------------
            ob_start();

            echo '<form method="get" class="solas-jobs-filters" style="border:1px solid #eee;padding:12px;border-radius:10px;background:#fff;margin:0 0 14px 0;">';
            echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">';

            echo '<div style="flex:1 1 220px;min-width:180px;">';
            echo '<label style="display:block;font-size:13px;opacity:.8;margin-bottom:4px;">Search</label>';
            echo '<input type="text" name="q" value="' . esc_attr($q_search) . '" placeholder="Keyword…" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;">';
            echo '</div>';

            echo '<div style="flex:1 1 180px;min-width:160px;">';
            echo '<label style="display:block;font-size:13px;opacity:.8;margin-bottom:4px;">Location</label>';
            echo '<input type="text" name="location" value="' . esc_attr($q_location) . '" placeholder="e.g. Glasgow" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;">';
            echo '</div>';

            echo '<div style="flex:1 1 180px;min-width:160px;">';
            echo '<label style="display:block;font-size:13px;opacity:.8;margin-bottom:4px;">Contract</label>';
            echo '<select name="contract" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;">';
            $contract_opts = [
                '' => 'Any',
                'Full-time' => 'Full-time',
                'Part-time' => 'Part-time',
                'Permanent' => 'Permanent',
                'Fixed-term' => 'Fixed-term',
                'Temporary' => 'Temporary',
                'Contract' => 'Contract',
                'Freelance' => 'Freelance',
            ];
            foreach ($contract_opts as $val => $label) {
                echo '<option value="' . esc_attr($val) . '" ' . selected($q_contract, $val, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';

            echo '<div style="flex:0 0 160px;min-width:140px;">';
            echo '<label style="display:block;font-size:13px;opacity:.8;margin-bottom:4px;">Remote</label>';
            echo '<select name="remote" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;">';
            $remote_opts = ['any' => 'Any', 'Yes' => 'Yes', 'No' => 'No', 'Hybrid' => 'Hybrid'];
            $cur_remote = $q_remote === '' ? 'any' : $q_remote;
            foreach ($remote_opts as $val => $label) {
                echo '<option value="' . esc_attr($val) . '" ' . selected($cur_remote, $val, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';

            echo '<div style="flex:0 0 auto;">';
            echo '<button class="' . esc_attr(solas_wc_button_classes('primary')) . '" type="submit">Filter</button> ';
            echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url(get_permalink()) . '">Reset</a>';
            echo '</div>';

            echo '</div>';
            echo '</form>';

            if (!$q->have_posts()) {
                echo '<p>No jobs available at the moment.</p>';
                return (string) ob_get_clean();
            }

            echo '<div class="solas-jobs-list">';

            $require_login_apply = (bool) get_option('solas_jobs_require_login_apply', false);
            $apply_requires_login_and_logged_out = $require_login_apply && !is_user_logged_in();

            while ($q->have_posts()) {
                $q->the_post();
                $job_id = (int) get_the_ID();

                $expires = (string) get_post_meta($job_id, 'solas_job_expires_at', true);
                if ($expires) {
                    $exp_ts = strtotime($expires);
                    if ($exp_ts && $exp_ts < $now->getTimestamp()) {
                        continue;
                    }
                }

                $company  = (string) get_post_meta($job_id, 'solas_company_name', true);
                $location = (string) get_post_meta($job_id, 'solas_location', true);
                $remote   = (string) get_post_meta($job_id, 'solas_remote', true);
                $salary   = (string) get_post_meta($job_id, 'solas_salary', true);
                $contract = (string) get_post_meta($job_id, 'solas_contract_type', true);
                $hours    = (string) get_post_meta($job_id, 'solas_hours', true);

                $apply_url = solas_jobs_portal_apply_click_url($job_id);

            $appMethod = (string) get_post_meta($job_id, 'solas_application_method', true);
            $appUrl    = (string) get_post_meta($job_id, 'solas_application_url', true);
            $label     = ($appUrl && stripos($appMethod, 'url') !== false) ? 'Apply on employer site' : 'Apply for job';
                if ($apply_requires_login_and_logged_out) {
                    $apply_url = wp_login_url($apply_url);
                }

                $logo_url  = solas_jobs_portal_job_logo_url($job_id);
                $shortdesc = solas_jobs_portal_job_short_description($job_id, (int) get_option('solas_jobs_card_truncate_words', 60));

                echo '<article class="solas-job-card" style="border:1px solid #e5e5e5;padding:14px;border-radius:10px;margin:0 0 14px 0;background:#fff;">';

                echo '<div style="display:flex;gap:12px;align-items:flex-start;">';
                if ($logo_url) {
                    echo '<div style="flex:0 0 auto;">'
                        . '<img src="' . esc_url($logo_url) . '" alt="" style="width:64px;height:64px;object-fit:contain;border:1px solid #eee;border-radius:10px;background:#fff;padding:6px;">'
                        . '</div>';
                }
                echo '<div style="flex:1 1 auto;min-width:0;">';
                echo '<h3 style="margin:0 0 6px 0;">' . esc_html(get_the_title()) . '</h3>';

                echo '<div style="font-size:13px;opacity:.8;margin-bottom:6px;">Posted: ' . esc_html(get_the_date('d/m/Y', $job_id)) . '</div>';

                if ($company)  echo '<div><strong>' . esc_html($company) . '</strong></div>';
                if ($location) echo '<div>Location: ' . esc_html($location) . '</div>';
                if ($remote)   echo '<div>Remote: ' . esc_html($remote) . '</div>';
                if ($contract) echo '<div>Contract: ' . esc_html($contract) . '</div>';
                if ($hours)    echo '<div>Hours: ' . esc_html($hours) . '</div>';
                if ($salary)   echo '<div>Salary: ' . esc_html($salary) . '</div>';

                if ($shortdesc) {
                    echo '<div style="margin-top:8px;opacity:.95;">' . esc_html($shortdesc) . '</div>';
                }

                if ($apply_requires_login_and_logged_out) {
                    echo '<div style="margin-top:8px;font-size:13px;opacity:.8;">Log in to apply.</div>';
                }

                echo '</div>'; // text col
                echo '</div>'; // header flex

                echo '<div class="solas-job-actions" style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
                echo '<a href="' . esc_url(get_permalink($job_id)) . '" class="' . esc_attr(solas_wc_button_classes('default')) . '">View job</a>';
                echo '<a href="' . esc_url($apply_url) . '" class="' . esc_attr(solas_wc_button_classes('primary')) . '">' . ($apply_requires_login_and_logged_out ? 'Log in to apply' : ($apply_label ?? 'Apply for job')) . '</a>';
                echo '</div>';

                echo '</article>';
            }
            wp_reset_postdata();

            echo '</div>';

            return (string) ob_get_clean();
    }

}
