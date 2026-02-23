<?php
declare(strict_types=1);

namespace Solas\Portal\Events;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

/**
 * SOLAS Events Admin (Back-end creation/editing)
 *
 * Adds an "Event Details" metabox to the solas_event CPT.
 * Ticket/Booking metabox callback is expected to exist (legacy) in includes/events.php.
 */
final class Admin
{
    public static function register(): void
    {
        add_filter('use_block_editor_for_post_type', [self::class, 'forceBlockEditor'], 20, 2);
        add_action('add_meta_boxes', [self::class, 'registerMetaBoxes']);
        add_action('save_post', [self::class, 'saveMeta'], 10, 2);
    }

    public static function forceBlockEditor($use_block_editor, $post_type)
    {
        if (defined('SOLAS_EVENT_CPT') && $post_type === SOLAS_EVENT_CPT) {
            return true;
        }
        return $use_block_editor;
    }

    public static function registerMetaBoxes(): void
    {
        if (!defined('SOLAS_EVENT_CPT')) return;

        add_meta_box(
            'solas_event_details',
            'Event Details',
            [self::class, 'renderDetailsMetabox'],
            SOLAS_EVENT_CPT,
            'normal',
            'high'
        );

        // Ticket/Booking metabox: use existing function if present (kept in includes/events.php)
        if (function_exists('solas_event_ticket_metabox_html')) {
            add_meta_box(
                'solas_event_ticket',
                'Ticket / Booking',
                'solas_event_ticket_metabox_html',
                SOLAS_EVENT_CPT,
                'side',
                'default'
            );
        }
    }

    public static function renderDetailsMetabox($post): void
    {
        $pid = (int) $post->ID;

        wp_nonce_field('solas_event_admin_save', 'solas_event_admin_nonce');

        $start_date = (string) get_post_meta($pid, 'solas_event_start_date', true);
        $end_date   = (string) get_post_meta($pid, 'solas_event_end_date', true);
        $start_time = (string) get_post_meta($pid, 'solas_event_start_time', true);
        $end_time   = (string) get_post_meta($pid, 'solas_event_end_time', true);
        $format     = (string) get_post_meta($pid, 'solas_event_format', true);
        $location   = (string) get_post_meta($pid, 'solas_event_location', true);

        echo '<p><label>Start date<br><input type="date" name="solas_event_start_date" value="' . esc_attr($start_date) . '"></label></p>';
        echo '<p><label>End date<br><input type="date" name="solas_event_end_date" value="' . esc_attr($end_date) . '"></label></p>';
        echo '<p><label>Start time<br><input type="time" name="solas_event_start_time" value="' . esc_attr($start_time) . '"></label></p>';
        echo '<p><label>End time<br><input type="time" name="solas_event_end_time" value="' . esc_attr($end_time) . '"></label></p>';

        echo '<p><label>Format<br><select name="solas_event_format">';
        $opts = ['Online','In person','Hybrid',''];
        foreach ($opts as $opt) {
            if ($opt === '') continue;
            echo '<option value="' . esc_attr($opt) . '"' . selected($format, $opt, false) . '>' . esc_html($opt) . '</option>';
        }
        echo '</select></label></p>';

        echo '<p><label>Location<br><input type="text" name="solas_event_location" value="' . esc_attr($location) . '" class="widefat"></label></p>';
        echo '<p style="opacity:.8">More event fields can still be edited in the block editor content.</p>';
    }

    public static function saveMeta( $post_id, $post): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
        if (!defined('SOLAS_EVENT_CPT') || $post->post_type !== SOLAS_EVENT_CPT) return;

        if (!isset($_POST['solas_event_admin_nonce']) || !wp_verify_nonce((string) $_POST['solas_event_admin_nonce'], 'solas_event_admin_save')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) return;

        $map = [
            'solas_event_start_date' => 'solas_event_start_date',
            'solas_event_end_date'   => 'solas_event_end_date',
            'solas_event_start_time' => 'solas_event_start_time',
            'solas_event_end_time'   => 'solas_event_end_time',
            'solas_event_format'     => 'solas_event_format',
            'solas_event_location'   => 'solas_event_location',
        ];

        foreach ($map as $field => $meta_key) {
            if (isset($_POST[$field])) {
                $val = sanitize_text_field((string) $_POST[$field]);
                update_post_meta($post_id, $meta_key, $val);
            }
        }
    }
}
