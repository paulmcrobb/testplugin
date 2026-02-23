<?php
declare(strict_types=1);

namespace Solas\Portal\Events;

use WP_REST_Request;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

/**
 * SOLAS Events Calendar (CPT-driven)
 * Shortcode: [solas_events_calendar view="listYear"]
 * REST: /wp-json/solas/v1/events?start=YYYY-MM-DD&end=YYYY-MM-DD&type=slug&format=Online
 */
final class Calendar
{
    public static function register(): void
    {
        // Clean JSON shield (if PHP warnings pollute REST responses)
        add_action('rest_api_init', [self::class, 'startBuffer'], 1);
        add_filter('rest_post_dispatch', [self::class, 'cleanBufferForSolasRoutes'], 10, 3);

        add_shortcode('solas_events_calendar', [self::class, 'shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets']);

        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function startBuffer(): void
    {
        if (!ob_get_level()) {
            ob_start();
        }
    }

    public static function cleanBufferForSolasRoutes($result, $server, $request)
    {
        if (method_exists($request, 'get_route') && strpos((string) $request->get_route(), '/solas/v1/') !== false) {
            while (ob_get_level()) {
                ob_end_clean();
            }
        }
        return $result;
    }

    public static function shortcode($atts): string
    {
        $atts = shortcode_atts([
            'view'   => 'listYear',
            'type'   => '',
            'format' => '',
            'height' => 'auto',
        ], (array) $atts, 'solas_events_calendar');

        self::enqueueAssets();

        $id = 'solas-events-calendar-' . wp_generate_uuid4();
        $data = [
            'id'     => $id,
            'view'   => (string) $atts['view'],
            'type'   => (string) $atts['type'],
            'format' => (string) $atts['format'],
            'height' => (string) $atts['height'],
            'rest'   => esc_url_raw(rest_url('solas/v1/events')),
        ];

        return '<div class="solas-events-calendar" id="' . esc_attr($id) . '" data-solas-events-calendar="' . esc_attr(wp_json_encode($data)) . '"></div>';
    }

    public static function registerAssets(): void
    {
        // Only register; enqueue happens in enqueueAssets()
        $useLocal = defined('SOLAS_EVENTS_CALENDAR_USE_LOCAL_FULLCALENDAR') ? (bool) SOLAS_EVENTS_CALENDAR_USE_LOCAL_FULLCALENDAR : false;

        if ($useLocal) {
            wp_register_script('fullcalendar-global', SOLAS_PORTAL_URL . 'assets/vendor/fullcalendar/index.global.min.js', [], SOLAS_PORTAL_VERSION, true);
            wp_register_style('fullcalendar-global', SOLAS_PORTAL_URL . 'assets/vendor/fullcalendar/index.global.min.css', [], SOLAS_PORTAL_VERSION);
        } else {
            // CDN fallback
            wp_register_script('fullcalendar-global', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', [], null, true);
            wp_register_style('fullcalendar-global', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css', [], null);
        }

        wp_register_script('solas-events-calendar', SOLAS_PORTAL_URL . 'assets/js/events-calendar.js', ['fullcalendar-global'], SOLAS_PORTAL_VERSION, true);
        wp_register_style('solas-events-calendar', SOLAS_PORTAL_URL . 'assets/css/events-calendar.css', ['fullcalendar-global'], SOLAS_PORTAL_VERSION);
    }

    public static function enqueueAssets(): void
    {
        wp_enqueue_script('solas-events-calendar');
        wp_enqueue_style('solas-events-calendar');
    }

    public static function registerRoutes(): void
    {
        register_rest_route('solas/v1', '/events', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'restEvents'],
            'permission_callback' => '__return_true',
            'args'                => [
                'start'  => ['required' => true],
                'end'    => ['required' => true],
                'type'   => ['required' => false],
                'format' => ['required' => false],
            ],
        ]);
    }

    public static function restEvents(WP_REST_Request $request)
    {
        $startRaw = sanitize_text_field((string) $request->get_param('start'));
        $endRaw   = sanitize_text_field((string) $request->get_param('end'));
        $type     = sanitize_text_field((string) $request->get_param('type'));
        $format   = sanitize_text_field((string) $request->get_param('format'));

        // FullCalendar often sends ISO timestamps; normalise to YYYY-MM-DD for date-range comparisons.
        $start = substr($startRaw, 0, 10);
        $end   = substr($endRaw, 0, 10);

        $args = [
            'post_type'      => defined('SOLAS_EVENT_CPT') ? SOLAS_EVENT_CPT : 'solas_event',
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_key'       => 'solas_event_start',
            'meta_query'     => [
                // Start <= end-of-window
                [
                    'key'     => 'solas_event_start',
                    'value'   => $end . ' 23:59:59',
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ],
                // End >= start-of-window
                [
                    'key'     => 'solas_event_end',
                    'value'   => $start . ' 00:00:00',
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ],
            ],
        ];

        if ($type) {
            $args['tax_query'] = [[
                'taxonomy' => defined('SOLAS_EVENT_TAX_TYPE') ? SOLAS_EVENT_TAX_TYPE : 'solas_event_type',
                'field'    => 'slug',
                'terms'    => [$type],
            ]];
        }

        if ($format) {
            // Stored as array/serialized in some flows, so LIKE is safest.
            $args['meta_query'][] = [
                'key'     => 'solas_event_format',
                'value'   => $format,
                'compare' => 'LIKE',
            ];
        }

        $q = new \WP_Query($args);
        $out = [];

        foreach ($q->posts as $post) {
            $pid = (int) $post->ID;

            $startDt = (string) get_post_meta($pid, 'solas_event_start', true);
            $endDt   = (string) get_post_meta($pid, 'solas_event_end', true);

            // Convert MySQL datetime (YYYY-MM-DD HH:MM:SS) to ISO for FullCalendar.
            $startIso = $startDt ? str_replace(' ', 'T', $startDt) : '';
            $endIso   = $endDt ? str_replace(' ', 'T', $endDt) : '';

            $isSticky     = is_sticky($pid);
            $isCommercial = (string) get_post_meta($pid, 'solas_event_commercial', true) === '1' || (string) get_post_meta($pid, 'solas_event_submission_type', true) === 'commercial';

            $title   = get_the_title($pid);
            $excerpt = has_excerpt($pid) ? get_the_excerpt($pid) : wp_trim_words(wp_strip_all_tags((string) $post->post_content), 24);

            $labelBits = [];
            if ($isCommercial) $labelBits[] = 'Commercial';
            if ($isSticky) $labelBits[] = 'Featured';
            $badge = $labelBits ? implode(' • ', $labelBits) : '';

            $out[] = [
                'id'          => $pid,
                'title'       => $title,
                'start'       => $startIso,
                'end'         => $endIso,
                'url'         => get_permalink($pid),
                'description' => $excerpt,
                'badge'       => $badge,
                'sticky'      => $isSticky,
                'commercial'  => $isCommercial,
            ];
        }

        return rest_ensure_response($out);
    }
}
