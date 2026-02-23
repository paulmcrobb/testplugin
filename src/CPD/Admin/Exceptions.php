<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

final class Exceptions {
    public static function render(string $cycle_label): void {
        $start_year = function_exists('solas_cpd_cycle_start_year_from_label') ? solas_cpd_cycle_start_year_from_label($cycle_label) : intval(wp_date('Y'));

            $nonce = wp_create_nonce('solas_cpd_export_exceptions_' . $cycle_label);
            $export_url = admin_url('admin-post.php?action=solas_cpd_export_exceptions_csv&cycle=' . urlencode($cycle_label) . '&_wpnonce=' . urlencode($nonce));

            echo '<h2>Exceptions</h2>';
            echo '<p>These are data integrity issues that can affect compliance reporting and exports.</p>';
            echo '<p><a class="button" href="' . esc_url($export_url) . '">Download Exceptions (CSV)</a></p>';

            // Quick summary counts (fast-ish)
            $q = new \WP_Query([
                'post_type' => 'solas_cpd_record',
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => 500,
                'orderby' => 'ID',
                'order' => 'DESC',
                'meta_query' => [
                    [
                        'key' => 'solas_cycle_year',
                        'value' => (string)$start_year,
                        'compare' => '='
                    ],
                ],
            ]);

            $rows = [];
            foreach ($q->posts as $rid) {
                $issues = solas_cpd_admin_tools_get_record_exceptions((int)$rid);
                if (!$issues) continue;
                $uid = intval(get_post_meta($rid, 'solas_user_id', true));
                $user = $uid ? get_user_by('id', $uid) : null;
                $rows[] = [
                    'record_id' => (int)$rid,
                    'user_id' => $uid,
                    'user' => $user ? $user->display_name : '',
                    'issues' => $issues,
                    'edit_record' => get_edit_post_link($rid, ''),
                    'edit_user' => $uid ? get_edit_user_link($uid) : '',
                ];
                if (count($rows) >= 50) break;
            }
            wp_reset_postdata();

            if (!$rows) {
                echo '<p><strong>No issues found</strong> in the most recent 500 records for this cycle.</p>';
                return;
            }

            echo '<table class="widefat striped"><thead><tr>'
               . '<th>Record</th><th>User</th><th>Issue(s)</th><th>Quick links</th>'
               . '</tr></thead><tbody>';

            foreach ($rows as $r) {
                $issue_lines = [];
                foreach ($r['issues'] as $iss) {
                    $issue_lines[] = esc_html(($iss['code'] ?? '') . ': ' . ($iss['message'] ?? ''));
                }
                echo '<tr>';
                echo '<td>#' . esc_html((string)$r['record_id']) . '</td>';
                echo '<td>' . esc_html($r['user'] ?: ('User #' . (string)$r['user_id'])) . '</td>';
                echo '<td>' . implode('<br>', $issue_lines) . '</td>';
                echo '<td>';
                if (!empty($r['edit_record'])) {
                    echo '<a class="button button-small" href="' . esc_url($r['edit_record']) . '">Edit record</a> ';
                }
                if (!empty($r['edit_user'])) {
                    echo '<a class="button button-small" href="' . esc_url($r['edit_user']) . '">Edit user</a>';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<p style="margin-top:10px;">Showing up to 50 issues (sample). Use the CSV for the full list.</p>';


            // Repairs (safe, batched)
            $pending = [
                'status_missing' => 0,
                'negative_time'  => 0,
                'evidence_json'  => 0,
            ];

            // Re-scan the same queried IDs for quick pending counts (cheap).
            foreach ($q->posts as $rid2) {
                $rid2 = (int)$rid2;
                $status = (string)get_post_meta($rid2, 'solas_cpd_status', true);
                if ($status === '') $pending['status_missing']++;

                $minutes = get_post_meta($rid2, 'solas_minutes', true);
                $hours = get_post_meta($rid2, 'solas_hours', true);
                if (($minutes !== '' && is_numeric($minutes) && (float)$minutes < 0) || ($hours !== '' && is_numeric($hours) && (float)$hours < 0)) {
                    $pending['negative_time']++;
                }

                $ev = get_post_meta($rid2, 'solas_evidence_urls', true);
                if (is_string($ev)) {
                    $trim = trim($ev);
                    if ($trim !== '' && ($trim[0] === '[' || $trim[0] === '{') && is_array(json_decode($trim, true))) {
                        $pending['evidence_json']++;
                    }
                }
            }

            echo '<hr style="margin:18px 0;">';
            echo '<h3>Safe repairs (batched)</h3>';
            echo '<p>These repairs only normalise data. No deletions. No financial changes. Each run processes up to 300 records.</p>';

            if (isset($_GET['repaired'], $_GET['fixed'])) {
                echo '<div class="notice notice-success"><p><strong>Repair complete:</strong> '
                    . esc_html((string)$_GET['repaired'])
                    . ' — fixed '
                    . esc_html((string)$_GET['fixed'])
                    . ' record(s).</p></div>';
            }

            $repairs = [
                'status_missing' => 'Backfill missing status → pending',
                'negative_time'  => 'Clamp negative minutes/hours → 0',
                'evidence_json'  => 'Normalise evidence JSON string → URL array',
            ];

            echo '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
            foreach ($repairs as $key => $label) {
                $nonce = wp_create_nonce('solas_cpd_repair_exceptions_' . $cycle_label . '_' . $key);
                $url = admin_url('admin-post.php?action=solas_cpd_repair_exceptions&cycle=' . urlencode($cycle_label) . '&repair=' . urlencode($key) . '&_wpnonce=' . urlencode($nonce));
                $count = (int)($pending[$key] ?? 0);
                $disabled = $count <= 0 ? ' disabled="disabled"' : '';
                echo '<a class="button"' . $disabled . ' href="' . esc_url($url) . '">'
                    . esc_html($label) . ' (' . esc_html((string)$count) . ' pending)'
                    . '</a>';
            }
            echo '</div>';
    }
}
