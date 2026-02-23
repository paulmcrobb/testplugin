<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs\Portal;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

final class EmployerDashboard {

    public static function render(): string {
        if (!is_user_logged_in()) {
                return '<p>Please log in to view your employer dashboard.</p>';
            }

            $user_id = (int) get_current_user_id();

            if (!solas_is_employer_user($user_id)) {
                return '<p>This area is available to employers only.</p>';
            }

            // Optional membership gating for employer tools (defaults to allow unless configured).
            if (class_exists('Solas\\Portal\\Woo\\PlanGates') && !\Solas\Portal\Woo\PlanGates::canAccessEmployerJobsTools($user_id)) {
                return '<p>Your membership plan does not currently allow access to employer job tools.</p>';
            }

            // If a job_id is present, show applications for that job
            $job_id = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
            if ($job_id > 0) {
                if (!solas_jobs_portal_can_manage_job($job_id, $user_id)) {
                    return '<p>Not allowed.</p>';
                }

                $job_title = get_the_title($job_id);
                $back_url  = solas_jobs_portal_employer_dashboard_url();

                ob_start();
                echo '<div style="border:1px solid #e5e5e5;padding:14px;border-radius:10px;background:#fff;">';
                echo '<p><a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url($back_url) . '">← Back to jobs</a></p>';
                echo '<h3 style="margin-top:0;">Applications for: ' . esc_html($job_title ?: ('Job #' . $job_id)) . '</h3>';

                // Stats (job-specific)
                $stats = solas_employer_application_stats($user_id, $job_id);
                solas_render_stats_cards($stats);

            // Apply clicks (total across your jobs)
            $jobs_for_clicks = new \WP_Query(['post_type'=>'solas_job','post_status'=>['publish','draft','private'],'posts_per_page'=>500,'author'=>$user_id,'fields'=>'ids']);
            $click_total = 0;
            foreach ((array)$jobs_for_clicks->posts as $jid2) { $click_total += (int) get_post_meta((int)$jid2, 'solas_apply_clicks', true); }
            echo '<p style="margin:0 0 12px 0;opacity:.8;"><strong>Apply clicks (total):</strong> ' . esc_html((string)$click_total) . '</p>';


                $apps = new \WP_Query([
                    'post_type'      => 'solas_job_app',
                    'post_status'    => ['publish', 'private', 'draft'],
                    'posts_per_page' => 200,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'meta_query'     => [
                        ['key' => 'solas_job_id', 'value' => $job_id, 'compare' => '='],
                    ],
                    'fields' => 'ids',
                ]);

                if (!$apps->posts) {
                    echo '<p>No applications yet.</p>';
                    echo '</div>';
                    return (string) ob_get_clean();
                }

                $nonce = wp_create_nonce('solas_employer_app_actions');

                $status_labels = [
                    'submitted'      => 'Submitted',
                    'shortlisted'    => 'Shortlisted',
                    'interviewed'    => 'Interviewed',
                    'offer_extended' => 'Offer extended',
                    'hired'          => 'Hired',
                    'rejected'       => 'Rejected',
                    'archived'       => 'Archived',
                    'accepted'       => 'Accepted', // legacy
                ];

                echo '<table style="width:100%;border-collapse:collapse;">';
                echo '<thead><tr>'
                    . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Date</th>'
                    . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Applicant</th>'
                    . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Status</th>'
                    . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">CV</th>'
                    . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Cover</th>'
                    . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Actions</th>'
                    . '</tr></thead><tbody>';

                foreach ($apps->posts as $app_id) {
                    $applied_at = (string) get_post_meta($app_id, 'solas_applied_at', true);
                    $date = $applied_at ? wp_date('d/m/Y H:i', strtotime($applied_at)) : get_the_date('d/m/Y H:i', $app_id);

                    $status = (string) get_post_meta($app_id, 'solas_application_status', true);
                    if ($status === '') $status = 'submitted';
                    if ($status === 'accepted') $status = 'hired';

                    $email = (string) get_post_meta($app_id, 'solas_applicant_email', true);
                    if ($email === '') {
                        $cand_user = (int) get_post_meta($app_id, 'solas_candidate_user_id', true);
                        if ($cand_user > 0) {
                            $u = get_userdata($cand_user);
                            if ($u && !empty($u->user_email)) $email = (string) $u->user_email;
                        }
                    }

                    $cv_url = (string) get_post_meta($app_id, 'solas_cv_url', true);
                    $cover_url = (string) get_post_meta($app_id, 'solas_cover_upload_url', true);

                    $set_status_url = function ($new_status) use ($app_id, $job_id, $nonce) {
                        return esc_url(add_query_arg([
                            'action'    => 'solas_employer_application_set_status',
                            'app_id'    => $app_id,
                            'job_id'    => $job_id,
                            'status'    => $new_status,
                            '_wpnonce'  => $nonce,
                        ], admin_url('admin-post.php')));
                    };

                    echo '<tr>';
                    echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html($date) . '</td>';
                    echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html($email ?: '—') . '</td>';
                    echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;"><strong>' . esc_html($status_labels[$status] ?? $status) . '</strong></td>';

                    echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">'
                        . ($cv_url ? '<a href="' . esc_url($cv_url) . '" target="_blank" rel="noopener">Download</a>' : '—')
                        . '</td>';

                    echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">'
                        . ($cover_url ? '<a href="' . esc_url($cover_url) . '" target="_blank" rel="noopener">Download</a>' : '—')
                        . '</td>';

                    echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;display:flex;gap:6px;flex-wrap:wrap;">';
                    echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . $set_status_url('shortlisted') . '">Shortlist</a>';
                    echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . $set_status_url('interviewed') . '">Interviewed</a>';
                    echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . $set_status_url('offer_extended') . '">Offer</a>';
                    echo '<a class="' . esc_attr(solas_wc_button_classes('primary')) . '" href="' . $set_status_url('hired') . '" onclick="return confirm(\'Mark as hired?\')">Hired</a>';
                    echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . $set_status_url('rejected') . '" onclick="return confirm(\'Reject this application?\')">Reject</a>';
                    echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . $set_status_url('archived') . '">Archive</a>';
                    echo '</td>';

                    echo '</tr>';
                }

                echo '</tbody></table>';
                echo '</div>';

                return (string) ob_get_clean();
            }

            // Otherwise: list employer jobs with stats + archive filter
            $view = isset($_GET['view']) ? sanitize_key((string) $_GET['view']) : 'active';
            $wanted_statuses = match ($view) {
                'filled'   => ['filled'],
                'archived' => ['archived'],
                default    => ['active', 'pending_payment'],
            };

            $q = new \WP_Query([
                'post_type'      => 'solas_job',
                'post_status'    => ['publish', 'draft', 'private'],
                'posts_per_page' => 200,
                'author'         => $user_id,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'     => 'solas_job_status',
                        'value'   => $wanted_statuses,
                        'compare' => 'IN',
                    ],
                ],
            ]);

            ob_start();
            echo '<div style="border:1px solid #e5e5e5;padding:14px;border-radius:10px;background:#fff;">';
            echo '<h3 style="margin-top:0;">Employer Dashboard</h3>';

            // Stats (overall)
            $stats = solas_employer_application_stats($user_id);
            solas_render_stats_cards($stats);

            // Filter toggles
            echo '<p style="margin:0 0 12px 0;display:flex;gap:10px;flex-wrap:wrap;">'
                . '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url(site_url('/employer-dashboard/?view=active')) . '">Active</a>'
                . '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url(site_url('/employer-dashboard/?view=filled')) . '">Filled</a>'
                . '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url(site_url('/employer-dashboard/?view=archived')) . '">Archived</a>'
                . '</p>';

            if (!$q->posts) {
                echo '<p>No jobs found for this view.</p>';
                echo '</div>';
                return (string) ob_get_clean();
            }

            $nonce = wp_create_nonce('solas_employer_job_actions');

            echo '<table style="width:100%;border-collapse:collapse;">';
            echo '<thead><tr>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Job</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Status</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Expiry</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Views</th><th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Apply clicks</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Applications</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Actions</th>'
                . '</tr></thead><tbody>';

            foreach ($q->posts as $jid) {
                $status = (string) get_post_meta($jid, 'solas_job_status', true);
                if ($status === '') $status = 'active';

                $expiry = (string) get_post_meta($jid, 'solas_job_expires_at', true);
                $views  = (int) get_post_meta($jid, 'solas_views', true);
                $apps   = (int) get_post_meta($jid, 'solas_applications', true);

                $renew_url = function_exists('wc_get_cart_url')
                    ? add_query_arg(['add-to-cart' => SOLAS_JOBS_PRODUCT_ID, 'solas_job_post_id' => $jid], wc_get_cart_url())
                    : '#';

                $dup_url = esc_url(add_query_arg([
                    'action'  => 'solas_employer_job_duplicate',
                    'job_id'  => $jid,
                    '_wpnonce'=> $nonce,
                ], admin_url('admin-post.php')));

                $filled_url = esc_url(add_query_arg([
                    'action'  => 'solas_employer_job_mark_filled',
                    'job_id'  => $jid,
                    '_wpnonce'=> $nonce,
                ], admin_url('admin-post.php')));

                $archive_url = esc_url(add_query_arg([
                    'action'  => 'solas_employer_job_archive',
                    'job_id'  => $jid,
                    '_wpnonce'=> $nonce,
                ], admin_url('admin-post.php')));

                $unarchive_url = esc_url(add_query_arg([
                    'action'  => 'solas_employer_job_unarchive',
                    'job_id'  => $jid,
                    '_wpnonce'=> $nonce,
                ], admin_url('admin-post.php')));

                $apps_url = solas_jobs_portal_employer_dashboard_url(['job_id' => $jid]);

                echo '<tr>';
                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html(get_the_title($jid)) . '</td>';
                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html($status) . '</td>';
                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html($expiry ? wp_date('d/m/Y H:i', strtotime($expiry)) : '—') . '</td>';
                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html((string)$views) . '</td>';
                $clicks = (int) get_post_meta($jid, 'solas_apply_clicks', true);
                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html((string)$clicks) . '</td>';
                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html((string)$apps) . '</td>';

                echo '<td style="border-bottom:1px solid #f2f2f2;padding:8px;display:flex;gap:6px;flex-wrap:wrap;">';
                echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url(get_permalink($jid)) . '">View</a>';
                echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url($apps_url) . '">Applications</a>';
                echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url($renew_url) . '">Renew</a>';
                echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . $dup_url . '">Duplicate</a>';
                echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . $filled_url . '" onclick="return confirm(\'Mark this job as filled?\')">Mark filled</a>';
                if ($status === 'filled') {
                    $reopen_url = esc_url(add_query_arg([
                        'action'  => 'solas_employer_job_reopen',
                        'job_id'  => $jid,
                        '_wpnonce'=> $nonce,
                    ], admin_url('admin-post.php')));
                    echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . $reopen_url . '" onclick="return confirm(\'Re-open this job and make it active again?\')">Re-open</a>';
                }

                if ($status === 'filled') {
                    echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . $archive_url . '">Archive</a>';
                }
                if ($status === 'archived') {
                    echo '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . $unarchive_url . '">Unarchive</a>';
                }

                echo '</td>';

                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';

            return (string) ob_get_clean();
    }

}
