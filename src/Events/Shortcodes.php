<?php
declare(strict_types=1);

namespace Solas\Portal\Events;

use WP_Query;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

final class Shortcodes {

    public static function register(): void {
        add_shortcode('solas_featured_events', [self::class, 'featuredEvents']);
        // Legacy shortcodes [solas_events] and [solas_event_single] are still handled in includes/events.php
    }

    public static function featuredEvents($atts): string {
        $atts = shortcode_atts([
            'count' => 5,
        ], (array) $atts, 'solas_featured_events');

        $count = max(1, (int) $atts['count']);

        $sticky = get_option('sticky_posts');
        if (empty($sticky) || !is_array($sticky)) return '';

        $args = [
            'post_type'           => defined('SOLAS_EVENT_CPT') ? SOLAS_EVENT_CPT : 'solas_event',
            'post_status'         => 'publish',
            'posts_per_page'      => $count,
            'post__in'            => array_map('intval', $sticky),
            'ignore_sticky_posts' => 1,
            'orderby'             => 'rand',
        ];

        $q = new WP_Query($args);

        if (!$q->have_posts()) {
            wp_reset_postdata();
            return '';
        }

        ob_start();
        echo '<div class="solas-featured-events">';
        while ($q->have_posts()) {
            $q->the_post();
            $id = get_the_ID();

            $start = (string) get_post_meta($id, 'solas_event_start', true);
            $startFmt = $start ? date_i18n('D j M, H:i', strtotime($start)) : '';

            echo '<div class="solas-featured-events__item">';
            echo '<a href="' . esc_url(get_permalink($id)) . '" style="font-weight:600;">' . esc_html(get_the_title($id)) . '</a>';
            if ($startFmt) {
                echo '<div style="opacity:.8; font-size:0.95em; margin-top:2px;">' . esc_html($startFmt) . '</div>';
            }
            echo '</div>';
        }
        echo '</div>';

        wp_reset_postdata();
        return (string) ob_get_clean();
    }
}
