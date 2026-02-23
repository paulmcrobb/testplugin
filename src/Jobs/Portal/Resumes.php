<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs\Portal;

defined('ABSPATH') || exit;

final class Resumes {

    public static function render(): string {
        if (!is_user_logged_in()) {
                return '<p>Please log in to manage your resumes.</p>';
            }

            $user_id = (int) get_current_user_id();
            $limit   = solas_resume_limit_for_user($user_id);
            $resume_ids = solas_jobs_portal_user_resumes($user_id);
            $count   = count($resume_ids);

            $out  = '<div style="border:1px solid #e5e5e5;padding:14px;border-radius:10px;background:#fff;">';
            $out .= '<h3 style="margin-top:0;">My Resumes</h3>';
            $out .= '<p>Stored: ' . esc_html((string)$count) . ' / ' . esc_html((string)$limit) . '</p>';

            if (!$resume_ids) {
                $out .= '<p>You have not saved any resumes yet.</p>';
                $out .= '<p><a class="' . esc_attr(solas_wc_button_classes('primary')) . '" href="' . esc_url(site_url('/create-my-resume/')) . '">Create a resume</a></p>';
                $out .= '</div>';
                return $out;
            }

            $out .= '<p><a class="' . esc_attr(solas_wc_button_classes('primary')) . '" href="' . esc_url(site_url('/create-my-resume/')) . '">Create a new resume</a></p>';

            $out .= '<table style="width:100%;border-collapse:collapse;">';
            $out .= '<thead><tr>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Name</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Default</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">CV</th>'
                . '<th style="text-align:left;border-bottom:1px solid #eee;padding:8px;">Actions</th>'
                . '</tr></thead><tbody>';

            foreach ($resume_ids as $rid) {
                $title = get_the_title($rid);
                $is_default = solas_jobs_portal_resume_is_default($rid);
                $file_url = (string) get_post_meta($rid, 'solas_resume_file_url', true);

                $set_default_url = wp_nonce_url(
                    admin_url('admin-post.php?action=solas_resume_set_default&resume_id=' . $rid),
                    'solas_resume_manage'
                );
                $delete_url = wp_nonce_url(
                    admin_url('admin-post.php?action=solas_resume_delete&resume_id=' . $rid),
                    'solas_resume_manage'
                );

                // ✅ Edit link to GF Form 8 page
                $edit_url = site_url('/create-my-resume/?resume_id=' . $rid);

                $out .= '<tr>';
                $out .= '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . esc_html($title ?: ('Resume #' . $rid)) . '</td>';
                $out .= '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">' . ($is_default ? '<strong>Yes</strong>' : 'No') . '</td>';
                $out .= '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">';
                $out .= $file_url ? '<a href="' . esc_url($file_url) . '" target="_blank" rel="noopener">Download</a>' : '—';
                $out .= '</td>';

                $out .= '<td style="border-bottom:1px solid #f2f2f2;padding:8px;">'
                    . '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url($edit_url) . '">Edit</a> '
                    . '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url($set_default_url) . '">Set default</a> '
                    . '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Delete this resume?\')">Delete</a>'
                    . '</td>';
                $out .= '</tr>';
            }

            $out .= '</tbody></table></div>';
            return $out;
    }

}
