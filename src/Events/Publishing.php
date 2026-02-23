<?php
declare(strict_types=1);

namespace Solas\Portal\Events;

use WC_Order;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

final class Publishing {

    public static function register(): void {
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'persistOrderItemMeta'], 10, 4);
        add_action('woocommerce_order_status_processing', [self::class, 'publishCommercialEventsFromOrder'], 20);
        add_action('woocommerce_order_status_completed',  [self::class, 'publishCommercialEventsFromOrder'], 20);
    }

    public static function persistOrderItemMeta($item, $cartItemKey, $values, $order): void {
        if (!empty($values['solas_event_post_id'])) {
            $item->add_meta_data('solas_event_post_id', (int) $values['solas_event_post_id'], true);
        }
        if (!empty($values['solas_event_listing'])) {
            $item->add_meta_data('solas_event_listing', sanitize_text_field((string) $values['solas_event_listing']), true);
        }
        if (isset($values['solas_event_featured'])) {
            $val = ((string) $values['solas_event_featured'] === '1') ? '1' : '0';
            $item->add_meta_data('solas_event_featured', $val, true);
        }
    }

    public static function publishCommercialEventsFromOrder( $orderId): void {
        if (!function_exists('wc_get_order')) return;

        $order = wc_get_order($orderId);
        if (!$order) return;

        foreach ($order->get_items() as $item) {
            $eventPostId = (int) $item->get_meta('solas_event_post_id', true);
            $listingType = (string) $item->get_meta('solas_event_listing', true);

            if ($eventPostId <= 0 || $listingType !== 'commercial') continue;

            $post = get_post($eventPostId);
            if (!$post || $post->post_type !== (defined('SOLAS_EVENT_CPT') ? SOLAS_EVENT_CPT : 'solas_event')) continue;

            if (in_array($post->post_status, ['publish', 'private'], true)) continue;

            $featuredSelected = (string) $item->get_meta('solas_event_featured', true);
            $isFeatured = ($featuredSelected === '1' || $featuredSelected === 'true');

            wp_update_post([
                'ID'          => $eventPostId,
                'post_status' => 'publish',
            ]);

            if ($isFeatured) {
                stick_post($eventPostId);
                update_post_meta($eventPostId, 'is_sticky', 1);
            } else {
                unstick_post($eventPostId);
                update_post_meta($eventPostId, 'is_sticky', 0);
            }

            update_post_meta($eventPostId, 'solas_event_paid_order_id', $orderId);
            update_post_meta($eventPostId, 'solas_event_published_at', current_time('mysql'));
            update_post_meta($eventPostId, 'solas_event_published_by', (int) $order->get_user_id());
        }
    }
}
