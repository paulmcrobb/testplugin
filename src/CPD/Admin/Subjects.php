<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

/**
 * Stage 46: Subjects report tab extracted from includes/cpd-admin-tools.php
 */
final class Subjects {

    public static function render(string $cycle_label): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (function_exists('solas_cpd_admin_tools_subject_report')) {
            solas_cpd_admin_tools_subject_report($cycle_label);
            return;
        }

        // If older installs only have totals, try that.
        if (function_exists('solas_cpd_admin_tools_subject_totals')) {
            $rows = solas_cpd_admin_tools_subject_totals($cycle_label);
            if (is_array($rows)) {
                echo '<h2>Grouped by Subject</h2>';
                echo '<table class="widefat striped"><thead><tr><th>Subject</th><th>Total Hours</th></tr></thead><tbody>';
                foreach ($rows as $subject => $hours) {
                    echo '<tr><td>' . esc_html((string)$subject) . '</td><td>' . esc_html((string)$hours) . '</td></tr>';
                }
                echo '</tbody></table>';
                return;
            }
        }

        echo '<p>Subjects report renderer not available.</p>';
    }
}
