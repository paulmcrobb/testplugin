<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

final class Duplicates
{
    public static function render($cycle): void
    {
        $cycle = absint($cycle);

        if (function_exists('solas_cpd_admin_tools_dupes_report')) {
            // Legacy renderer (kept for backward compatibility).
            solas_cpd_admin_tools_dupes_report();
            return;
        }

        echo '<p>Duplicates report module not loaded.</p>';
    }
}
