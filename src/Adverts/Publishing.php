<?php
declare(strict_types=1);

namespace Solas\Portal\Adverts;

use WC_Order;
use Throwable;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

/**
 * Adverts Publishing lifecycle:
 * - Publish advert CPT when Woo order is completed
 * - Scheduled expiry safety net hook
 * - Manual publish transition safety net
 */
final class Publishing {

    public static function register(): void {
        add_action('woocommerce_order_status_completed', [self::class, 'publishFromOrder'], 10, 1);
        add_action('solas_advert_expire', [self::class, 'handleScheduledExpire'], 10, 1);
        add_action('transition_post_status', [self::class, 'handleManualPublish'], 10, 3);
    }

    public static function publishFromOrder( $order_id): void {
        if (!function_exists('wc_get_order')) return;

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) return;

        // Avoid duplicate publishes
        $done = (string) $order->get_meta('_solas_adverts_publish_done', true);
        if ($done === 'yes') return;

        $ids = $order->get_meta('solas_advert_post_id', true);
        if (empty($ids)) return;

        $postIds = is_array($ids) ? $ids : [$ids];
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if (empty($postIds)) return;

        $attempted = 0;
        $succeeded = 0;

        foreach ($postIds as $postId) {
            $attempted++;

            // Only activate if not already active
            $current = (string) get_post_meta($postId, 'solas_advert_status', true);
            if ($current === 'active') {
                $succeeded++;
                continue;
            }

            // Ensure advert post exists
            if (get_post_type($postId) !== 'solas_advert') continue;

            // Link order to advert
            update_post_meta($postId, 'solas_wc_order_id', $order_id);
            update_post_meta($postId, 'solas_paid_at', wp_date('c'));

            if (function_exists('solas_adverts_activate_advert')) {
                solas_adverts_activate_advert($postId, [
                    'method'            => 'order_completed',
                    'order_id'          => $order_id,
                    'actor_id'          => 0,
                    'set_paid_override' => false,
                ]);
            }

            // Release any soft reservation for this advert now that it's confirmed.
            if (function_exists('solas_adverts_reservations_release_for_advert')) {
                solas_adverts_reservations_release_for_advert($postId);
            }

            // Hard expiry + renewal reminders.
            if (function_exists('solas_adverts_expiry_schedule')) {
                solas_adverts_expiry_schedule($postId);
            }
            if (function_exists('solas_adverts_renewals_schedule')) {
                solas_adverts_renewals_schedule($postId);
            }

            $succeeded++;
        }

        if ($attempted > 0 && $attempted === $succeeded) {
            $order->update_meta_data('_solas_adverts_publish_done', 'yes');
            $order->save();
        }
    }

    public static function handleScheduledExpire($args): void {
        $adId = is_array($args) && isset($args['advert_id']) ? (int) $args['advert_id'] : 0;
        if ($adId <= 0) return;

        $end = (string) get_post_meta($adId, 'solas_end_date', true);
        if ($end && strtotime($end) > time()) return;

        if (function_exists('solas_adverts_expire_advert')) {
            solas_adverts_expire_advert($adId, [
                'method'   => 'scheduled_expiry',
                'actor_id' => 0,
                'note'     => 'Auto-expired by Action Scheduler.',
            ]);
        }
    }

    public static function handleManualPublish( $new_status, $old_status, $post): void {
        if (empty($post) || !isset($post->post_type) || $post->post_type !== 'solas_advert') {
            return;
        }

        if ($new_status === 'publish' && $old_status !== 'publish') {
            $current = (string) get_post_meta((int) $post->ID, 'solas_advert_status', true);
            if ($current !== 'active' && function_exists('solas_adverts_activate_advert')) {
                solas_adverts_activate_advert((int) $post->ID, [
                    'method'            => 'manual_publish',
                    'order_id'          => (int) get_post_meta((int) $post->ID, 'solas_wc_order_id', true),
                    'actor_id'          => get_current_user_id(),
                    'set_paid_override' => true,
                ]);
            }
        }
    }
}
