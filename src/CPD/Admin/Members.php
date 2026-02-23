<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

/**
 * Stage 46: Members report tab extracted from includes/cpd-admin-tools.php
 * Keeps rendering logic isolated and safer.
 */
final class Members {

    public static function render(string $cycle_label): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Prefer existing legacy renderer if present (back-compat).
        if (function_exists('solas_cpd_admin_tools_members_report')) {
            solas_cpd_admin_tools_members_report($cycle_label);
            return;
        }

        echo '<p>Members report renderer not available.</p>';
    }
}
