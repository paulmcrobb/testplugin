<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

final class Settings {
    public static function render(): void {
        if (!current_user_can('manage_options')) return;

            if (isset($_POST['solas_cpd_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['solas_cpd_settings_nonce'])), 'solas_cpd_save_settings')) {
                $email = isset($_POST['solas_cpd_admin_email']) ? sanitize_text_field(wp_unslash($_POST['solas_cpd_admin_email'])) : '';
                update_option('solas_cpd_admin_email', $email);

                $selected = isset($_POST['solas_cpd_member_types']) ? (array)$_POST['solas_cpd_member_types'] : [];
                $selected = array_values(array_filter(array_map(function($v){
                    return sanitize_text_field(wp_unslash($v));
                }, $selected)));

                $custom = isset($_POST['solas_cpd_member_types_custom']) ? sanitize_text_field(wp_unslash($_POST['solas_cpd_member_types_custom'])) : '';
                if ($custom) {
                    $more = array_map('trim', explode(',', $custom));
                    $more = array_values(array_filter(array_map('sanitize_text_field', $more)));
                    $selected = array_values(array_unique(array_merge($selected, $more)));
                }

                if (empty($selected)) {
                    $selected = ['full-member', 'associate-member'];
                }
                update_option('solas_cpd_allowed_member_types', $selected);

                $courses_url = isset($_POST['solas_cpd_courses_url']) ? esc_url_raw(wp_unslash($_POST['solas_cpd_courses_url'])) : '';
                if ($courses_url === '') { $courses_url = site_url('/courses/'); }
                update_option('solas_cpd_courses_url', $courses_url);

                $events_url = isset($_POST['solas_cpd_events_url']) ? esc_url_raw(wp_unslash($_POST['solas_cpd_events_url'])) : '';
                if ($events_url === '') { $events_url = site_url('/events/'); }
                update_option('solas_cpd_events_url', $events_url);

                $corrections_url = isset($_POST['solas_cpd_corrections_url']) ? esc_url_raw(wp_unslash($_POST['solas_cpd_corrections_url'])) : '';
                if ($corrections_url === '') { $corrections_url = site_url('/cpd-corrections/'); }
                update_option('solas_cpd_corrections_url', $corrections_url);


                $rem_enabled = isset($_POST['solas_cpd_reminders_enabled']) ? 1 : 0;
                update_option('solas_cpd_reminders_enabled', $rem_enabled);
                $rem_level = isset($_POST['solas_cpd_reminders_level']) ? sanitize_text_field(wp_unslash($_POST['solas_cpd_reminders_level'])) : 'standard';
                update_option('solas_cpd_reminders_level', $rem_level);
                $cc_admin = isset($_POST['solas_cpd_reminders_cc_admin']) ? 1 : 0;
                update_option('solas_cpd_reminders_cc_admin', $cc_admin);

                // Reminder batching (Action Scheduler)
                $batch_size = isset($_POST['solas_cpd_reminders_batch_size']) ? intval($_POST['solas_cpd_reminders_batch_size']) : 50;
                if ($batch_size < 10) $batch_size = 10;
                if ($batch_size > 250) $batch_size = 250;
                update_option('solas_cpd_reminders_batch_size', $batch_size);

                $batch_interval = isset($_POST['solas_cpd_reminders_batch_interval']) ? intval($_POST['solas_cpd_reminders_batch_interval']) : 120;
                if ($batch_interval < 30) $batch_interval = 30;
                if ($batch_interval > 1800) $batch_interval = 1800;
                update_option('solas_cpd_reminders_batch_interval', $batch_interval);

                // Cycle locking
                $lock_enabled = isset($_POST['solas_cpd_lock_enabled']) ? 'yes' : 'no';
                update_option('solas_cpd_lock_enabled', $lock_enabled);
                $cutoff = isset($_POST['solas_cpd_lock_grace_cutoff']) ? sanitize_text_field(wp_unslash($_POST['solas_cpd_lock_grace_cutoff'])) : '11-30';
                if (!preg_match('/^\d{2}-\d{2}$/', $cutoff)) $cutoff = '11-30';
                update_option('solas_cpd_lock_grace_cutoff', $cutoff);

                $cert_start = isset($_POST['solas_cpd_certificates_start_year']) ? absint($_POST['solas_cpd_certificates_start_year']) : 0;
                update_option('solas_cpd_certificates_start_year', $cert_start);

                // Member scope policy toggles
                update_option('solas_cpd_scope_reminders_include_honorary', isset($_POST['solas_cpd_scope_reminders_include_honorary']) ? 1 : 0);
                update_option('solas_cpd_scope_reminders_include_expired', isset($_POST['solas_cpd_scope_reminders_include_expired']) ? 1 : 0);
                update_option('solas_cpd_scope_reporting_include_honorary', isset($_POST['solas_cpd_scope_reporting_include_honorary']) ? 1 : 0);
                update_option('solas_cpd_scope_ui_include_honorary', isset($_POST['solas_cpd_scope_ui_include_honorary']) ? 1 : 0);
                update_option('solas_cpd_scope_enforcement_include_honorary', isset($_POST['solas_cpd_scope_enforcement_include_honorary']) ? 1 : 0);



                echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
            }

            $email = (string)get_option('solas_cpd_admin_email', '');
            $allowed = solas_cpd_admin_get_allowed_member_types();

            $common = [
                'full-member' => 'Full member',
                'associate-member' => 'Associate member',
                'honorary-member' => 'Honorary member',
                'student-member' => 'Student member',
            ];

            echo '<h3>Settings</h3>';
            echo '<form method="post">';
            echo '<input type="hidden" name="solas_cpd_settings_nonce" value="' . esc_attr(wp_create_nonce('solas_cpd_save_settings')) . '"/>';

            echo '<table class="form-table"><tbody>';

            echo '<tr><th scope="row"><label for="solas_cpd_admin_email">Admin email</label></th>';
            echo '<td><input type="text" class="regular-text" id="solas_cpd_admin_email" name="solas_cpd_admin_email" value="' . esc_attr($email) . '" placeholder="' . esc_attr(get_option('admin_email')) . '" />';
            echo '<p class="description">Leave blank to use the site admin email. If an invalid email is entered, the site admin email will be used.</p></td></tr>';


            $courses_url = (string) get_option('solas_cpd_courses_url', site_url('/courses/'));
            $events_url  = (string) get_option('solas_cpd_events_url', site_url('/events/'));
            $corrections_url = (string) get_option('solas_cpd_corrections_url', site_url('/cpd-corrections/'));

            echo '<tr><th scope="row"><label for="solas_cpd_courses_url">Courses page URL</label></th>';
            echo '<td><input type="url" class="regular-text" id="solas_cpd_courses_url" name="solas_cpd_courses_url" value="' . esc_attr($courses_url) . '" placeholder="' . esc_attr(site_url('/courses/')) . '" />';
            echo '<p class="description">Used for CPD progress nudges (SOLAS structured shortfall).</p></td></tr>';

            echo '<tr><th scope="row"><label for="solas_cpd_events_url">Events page URL</label></th>';
            echo '<td><input type="url" class="regular-text" id="solas_cpd_events_url" name="solas_cpd_events_url" value="' . esc_attr($events_url) . '" placeholder="' . esc_attr(site_url('/events/')) . '" />';
            echo '<p class="description">Optional: used for progress nudges and member guidance.</p></td></tr>';


            echo '<tr><th scope="row"><label for="solas_cpd_corrections_url">CPD corrections page URL</label></th>';
            echo '<td><input type="url" class="regular-text" id="solas_cpd_corrections_url" name="solas_cpd_corrections_url" value="' . esc_attr($corrections_url) . '" placeholder="' . esc_attr(site_url('/cpd-corrections/')) . '" />';
            echo '<p class="description">Used for the “Request correction” buttons on the CPD dashboard.</p></td></tr>';


            $lock_enabled = (get_option('solas_cpd_lock_enabled', 'yes') !== 'no');
            // Canonical grace cutoff is 11-30. If an old default (12-31) is still stored, auto-correct it.
            $stored_cutoff = get_option('solas_cpd_lock_grace_cutoff', '');
            if ($stored_cutoff === '12-31') { update_option('solas_cpd_lock_grace_cutoff', '11-30'); $stored_cutoff = '11-30'; }
            if ($stored_cutoff !== '') {
                $lock_cutoff = (string) $stored_cutoff;
            } else {
                $lock_cutoff = (string) get_option('solas_cpd_lock_grace_cutoff', '11-30');
            }

            echo '<tr><th scope="row">Cycle locking</th><td>';
            echo '<label><input type="checkbox" name="solas_cpd_lock_enabled" value="1" ' . checked($lock_enabled, true, false) . ' /> Lock previous cycles after grace cutoff</label>';
            echo '<p style="margin-top:8px;"><label for="solas_cpd_lock_grace_cutoff">Grace cutoff (MM-DD):</label> ';
            echo '<input type="text" id="solas_cpd_lock_grace_cutoff" name="solas_cpd_lock_grace_cutoff" value="' . esc_attr($lock_cutoff) . '" class="small-text" pattern="\\d{2}-\\d{2}" />';
            echo '</p>';
            echo '<p class="description">Default is <code>11-30</code> (30 Nov of the cycle end year). Current cycle is never locked. Admins can override by editing records directly.</p>';
            echo '</td></tr>';

            echo '<tr><th scope="row">Member scope policies</th><td>';
            $r_h = (int)get_option('solas_cpd_scope_reminders_include_honorary', 0);
            $r_e = (int)get_option('solas_cpd_scope_reminders_include_expired', 0);
            $rep_h = (int)get_option('solas_cpd_scope_reporting_include_honorary', 1);
            $ui_h = (int)get_option('solas_cpd_scope_ui_include_honorary', 1);
            $enf_h = (int)get_option('solas_cpd_scope_enforcement_include_honorary', 0);
            echo '<p><label><input type="checkbox" name="solas_cpd_scope_reminders_include_honorary" value="1" ' . checked($r_h, 1, false) . ' /> Include <code>honorary-member</code> in reminders</label></p>';
            echo '<p><label><input type="checkbox" name="solas_cpd_scope_reminders_include_expired" value="1" ' . checked($r_e, 1, false) . ' /> Include expired/cancelled memberships in reminders</label></p>';
            echo '<p><label><input type="checkbox" name="solas_cpd_scope_reporting_include_honorary" value="1" ' . checked($rep_h, 1, false) . ' /> Include <code>honorary-member</code> in reporting</label></p>';
            echo '<p><label><input type="checkbox" name="solas_cpd_scope_ui_include_honorary" value="1" ' . checked($ui_h, 1, false) . ' /> Allow honorary members to view My CPD tab</label></p>';
            echo '<p><label><input type="checkbox" name="solas_cpd_scope_enforcement_include_honorary" value="1" ' . checked($enf_h, 1, false) . ' /> Enforce CPD on honorary members</label></p>';
            echo '<p class="description">These toggles control per-feature scope. Member detection comes from Woo Memberships plan slugs, not WP roles.</p>';
            echo '</td></tr>';




            echo '<tr><th scope="row">Member types included in “Members Not Completed”</th><td>';

            echo '<p><button type="button" class="button" onclick="solas_cpd_select_all_types(true)">Select all</button> ';
            echo '<button type="button" class="button" onclick="solas_cpd_select_all_types(false)">Select none</button></p>';
            // Use double-quotes in the selector to avoid breaking the PHP single-quoted string.
            echo '<script>\n'
                . 'function solas_cpd_select_all_types(state){\n'
                . '  document.querySelectorAll("input[name=\\"solas_cpd_member_types[]\\"]").forEach(function(cb){\n'
                . '    cb.checked = state;\n'
                . '  });\n'
                . '}\n'
                . '</script>';
        foreach ($common as $k => $label) {
                echo '<label style="display:block;margin:4px 0;"><input type="checkbox" name="solas_cpd_member_types[]" value="' . esc_attr($k) . '" ' . checked(in_array($k, $allowed, true), true, false) . ' /> ' . esc_html($label) . ' <code>' . esc_html($k) . '</code></label>';
            }
            echo '<p class="description">You can also add other types as a comma-separated list.</p>';
        $detected = [];
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $du) {
            $t = function_exists('solas_cpd_get_member_type') ? solas_cpd_get_member_type((int) $du) : '';
            if ($t) $detected[$t] = true;
        }
        if (!empty($detected)) {
            echo '<p><strong>Detected membership types in user base:</strong><br/>';
            foreach (array_keys($detected) as $dt) {
                echo '<code>' . esc_html($dt) . '</code> ';
            }
            echo '</p>';
        }
            echo '<input type="text" class="regular-text" name="solas_cpd_member_types_custom" value="" placeholder="e.g. retired-member, temporary-member" />';
            echo '</td></tr>';

    
            echo '<tr><th scope="row">Reminder emails</th><td>';
            $rem_enabled = (int)get_option('solas_cpd_reminders_enabled', 1);
            $rem_level = (string)get_option('solas_cpd_reminders_level', 'standard');
            $cc_admin = (int)get_option('solas_cpd_reminders_cc_admin', 1);
            $batch_size = (int)get_option('solas_cpd_reminders_batch_size', 50);
            $batch_interval = (int)get_option('solas_cpd_reminders_batch_interval', 120);
            echo '<label><input type="checkbox" name="solas_cpd_reminders_enabled" value="1" ' . checked($rem_enabled, 1, false) . ' /> Enable reminders</label>';
            echo '<p class="description">Sends reminder emails to members who have not yet completed the CPD requirement for the current cycle.</p>';
            echo '<p><label>Schedule: <select name="solas_cpd_reminders_level">';
            $levels = ['standard' => 'Standard (Aug–Nov)', 'october' => 'Extra in October', 'november' => 'November only'];
            foreach ($levels as $k => $label) {
                echo '<option value="' . esc_attr($k) . '" ' . selected($rem_level, $k, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></label></p>';
            echo '<label><input type="checkbox" name="solas_cpd_reminders_cc_admin" value="1" ' . checked($cc_admin, 1, false) . ' /> Send daily summary to admin email</label>';
            echo '<p style="margin-top:8px;"><label for="solas_cpd_reminders_batch_size">Batch size:</label> '
                . '<input type="number" min="10" max="250" class="small-text" id="solas_cpd_reminders_batch_size" name="solas_cpd_reminders_batch_size" value="' . esc_attr($batch_size) . '" />'
                . ' <span class="description">How many emails to send per Action Scheduler run.</span></p>';
            echo '<p><label for="solas_cpd_reminders_batch_interval">Batch interval (seconds):</label> '
                . '<input type="number" min="30" max="1800" class="small-text" id="solas_cpd_reminders_batch_interval" name="solas_cpd_reminders_batch_interval" value="' . esc_attr($batch_interval) . '" />'
                . ' <span class="description">Delay before the next batch is processed.</span></p>';
            echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Certificates start cycle', 'solas-portal') . '</th><td>';
        $start_year = absint(get_option('solas_cpd_certificates_start_year', 0));
        echo '<input type="number" min="2000" max="2100" name="solas_cpd_certificates_start_year" value="' . esc_attr($start_year) . '" class="small-text" />';
        echo '<p class="description">' . esc_html__('My Certificates will not show cycles before this start year (e.g. 2024 shows 2024/25 onwards).', 'solas-portal') . '</p>';
        echo '</td></tr>';

        echo '</tbody></table>';
            submit_button('Save settings');

        echo '<hr/><h3>Test reminder</h3>';
        echo '<p><a href="' . esc_url(admin_url('admin-post.php?action=solas_cpd_send_test_reminder&_wpnonce=' . wp_create_nonce('solas_cpd_test_reminder'))) . '" class="button button-secondary">Send test reminder to admin email</a></p>';
            echo '</form>';
    }
}