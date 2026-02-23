<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

final class TeamsImport
{
    public static function render($cycle): void
    {
        $cycle = absint($cycle);

        if (function_exists('solas_cpd_tools_render_teams_import')) {
            solas_cpd_tools_render_teams_import($cycle);
            return;
        }

        echo '<p>Teams import module not loaded.</p>';
    }
}
