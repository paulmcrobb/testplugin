<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

final class Rebuild {
    public static function render(): void {
        echo '<h3>Rebuild tools</h3>';
            echo '<p>Rebuilds derived meta (hours/points) and populates <code>solas_period_year</code> from cycle labels.</p>';
            $nonce = wp_create_nonce('solas_cpd_rebuild_meta');
            $url = admin_url('admin-post.php?action=solas_cpd_rebuild_meta&_wpnonce=' . urlencode($nonce));
            echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Run rebuild now</a></p>';
    }
}
