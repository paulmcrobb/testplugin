<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

final class Diagnostics {
    public static function render( $cycle_label): void {
        if (!current_user_can('manage_options')) return;

            $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
            $now = new \DateTimeImmutable('now', $tz);

            $selected_user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;

            // Normalise selected cycle.
            $cycle_start_year = function_exists('solas_cpd_normalize_cycle_year')
                ? (int) solas_cpd_normalize_cycle_year($cycle_label)
                : (int) preg_replace('/[^0-9]/', '', substr((string)$cycle_label, 0, 4));

            $current_start = \Solas\Portal\CPD\Cycle::currentStartYear();
            $previous_start = $current_start - 1;

            $current_label = \Solas\Portal\CPD\Cycle::label($current_start);
            $previous_label = \Solas\Portal\CPD\Cycle::label($previous_start);

            $cycle_range = \Solas\Portal\CPD\Cycle::range($cycle_start_year);
    
            // Cycle::range() returns numeric array [start,end] in newer builds; normalise to named keys for legacy diagnostics UI.
            if (is_array($cycle_range) && !isset($cycle_range['start'])) {
                $cycle_range = [
                    'start' => $cycle_range[0] ?? null,
                    'end'   => $cycle_range[1] ?? null,
                ];
            }
        $grace_range = \Solas\Portal\CPD\Cycle::graceRange($cycle_start_year);

    
            if (is_array($grace_range) && !isset($grace_range['start'])) {
                $grace_range = [
                    'start' => $grace_range[0] ?? null,
                    'end'   => $grace_range[1] ?? null,
                ];
            }

            $fmt = static function ($dt): string {
                return ($dt instanceof \DateTimeInterface) ? $dt->format('Y-m-d') : '—';
            };
        echo '<p>This panel is for quick troubleshooting of CPD cycle detection, grace window, and lock/override behaviour.</p>';

            echo '<form method="get" style="margin:12px 0 16px 0; padding:12px; border:1px solid #ddd; background:#fff; max-width: 980px;">';
            echo '<input type="hidden" name="post_type" value="solas_cpd_record">';
            echo '<input type="hidden" name="page" value="solas-cpd-tools">';
            echo '<input type="hidden" name="tab" value="diagnostics">';
            echo '<input type="hidden" name="cycle" value="' . esc_attr($cycle_label) . '">';

            echo '<div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">';
            echo '<div>';
            echo '<label for="solas_diag_user"><strong>User</strong></label><br>';
            wp_dropdown_users([
                'name' => 'user_id',
                'id' => 'solas_diag_user',
                'selected' => $selected_user_id,
                'show' => 'display_name_with_login',
                'show_option_none' => '— Select a user —',
                'option_none_value' => '0',
                'orderby' => 'display_name',
                'order' => 'ASC',
            ]);
            echo '</div>';
            echo '<div>';
            submit_button('Run diagnostics', 'primary', '', false);
            echo '</div>';
            echo '</div>';

            echo '</form>';

            echo '<table class="widefat striped" style="max-width: 980px;">';
            echo '<tbody>';
            echo '<tr><th style="width:280px;">Site timezone</th><td>' . esc_html(function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC') . '</td></tr>';
            echo '<tr><th>Now</th><td>' . esc_html($now->format('Y-m-d H:i:s')) . '</td></tr>';
            echo '<tr><th>Current cycle (detected)</th><td><strong>' . esc_html($current_label) . '</strong> (start year ' . esc_html((string)$current_start) . ')</td></tr>';
            echo '<tr><th>Previous cycle</th><td><strong>' . esc_html($previous_label) . '</strong> (start year ' . esc_html((string)$previous_start) . ')</td></tr>';
            echo '<tr><th>Grace window (global)</th><td>' . (\Solas\Portal\CPD\Cycle::isNowInGraceWindow() ? '<span style="color:#b8860b;"><strong>YES</strong></span>' : '<span style="color:#666;">No</span>') . '</td></tr>';
            echo '<tr><th>Selected cycle</th><td><strong>' . esc_html($cycle_label) . '</strong> (start year ' . esc_html((string)$cycle_start_year) . ')</td></tr>';
            echo '<tr><th>Selected cycle range</th><td>' . esc_html($fmt($cycle_range['start'])) . ' → ' . esc_html($fmt($cycle_range['end'])) . '</td></tr>';
            echo '<tr><th>Selected cycle grace range</th><td>' . esc_html($fmt($grace_range['start'])) . ' → ' . esc_html($fmt($grace_range['end'])) . '</td></tr>';

            if ($selected_user_id > 0) {
                $u = get_userdata($selected_user_id);
                $status = \Solas\Portal\CPD\Status::forCycle($cycle_start_year, $selected_user_id);
                $status_label = \Solas\Portal\CPD\Status::label($status);
                $locked = \Solas\Portal\CPD\LockManager::isCycleLocked($cycle_start_year, $selected_user_id);
                $unlocked_cycles = \Solas\Portal\CPD\Overrides::getUnlockedCycles($selected_user_id);

                echo '<tr><th>User</th><td>' . esc_html($u ? $u->display_name : ('User #' . $selected_user_id)) . ' (ID ' . esc_html((string)$selected_user_id) . ')</td></tr>';
                echo '<tr><th>Overrides (unlocked cycles)</th><td>' . (!empty($unlocked_cycles) ? esc_html(implode(', ', array_map('intval', $unlocked_cycles))) : '<span style="color:#666;">None</span>') . '</td></tr>';
                echo '<tr><th>Computed status</th><td><strong>' . esc_html($status_label) . '</strong></td></tr>';
                echo '<tr><th>Locked?</th><td>' . ($locked ? '<span style="color:#b32d2e;"><strong>YES</strong></span>' : '<span style="color:#2e7d32;"><strong>No</strong></span>') . '</td></tr>';
            } else {
                echo '<tr><th>User-specific checks</th><td><span style="color:#666;">Select a user above to see override + per-user status/lock evaluation.</span></td></tr>';
            }

            echo '</tbody>';
            echo '</table>';
    }
}
