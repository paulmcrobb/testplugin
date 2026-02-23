<?php
declare(strict_types=1);

namespace Solas\Portal\Adverts;

use DateTimeImmutable;
use Throwable;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

/**
 * Adverts Checkout Integration
 * - Programmatic add-to-cart from GF redirect
 * - Cart item meta + soft reservations
 * - Capacity validation
 * - Order meta mapping (line item + order level)
 *
 * Kept static/DI-free to remain activation-safe and consistent with current plugin style.
 */
final class Checkout {

    public static function register(): void {
        // Programmatic add-to-cart handler
        add_action('wp_loaded', [self::class, 'handleAddToCart']);

        // Cart item data & display
        add_filter('woocommerce_add_cart_item_data', [self::class, 'addCartItemData'], 10, 3);
        add_filter('woocommerce_get_item_data', [self::class, 'getItemData'], 10, 2);

        // Order meta mapping
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'addOrderLineItemMeta'], 10, 4);
        add_action('woocommerce_checkout_create_order', [self::class, 'addOrderMeta'], 10, 2);

        // Final validation before add-to-cart
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'validateAddToCart'], 10, 5);
    }

    /**
     * Handle the GF confirmation redirect: /cart/?solas_add_advert={postId}&_solas_add_advert={nonce}
     * Adds the correct variation to cart and redirects to clean cart URL.
     */
    public static function handleAddToCart(): void {
        if (!function_exists('WC') || !WC()->cart) return;
        if (!function_exists('wc_get_cart_url')) return;

        $postId = isset($_GET['solas_add_advert']) ? (int) $_GET['solas_add_advert'] : 0;
        if ($postId <= 0) return;

        $nonce = isset($_GET['_solas_add_advert']) ? (string) $_GET['_solas_add_advert'] : '';
        if (!wp_verify_nonce($nonce, 'solas_add_advert_' . $postId)) {
            return;
        }

        // Prevent duplicate adds on refresh: once handled, redirect to clean cart URL.
        if (isset($_GET['solas_added']) && (string) $_GET['solas_added'] === '1') {
            return;
        }

        $slot = (string) get_post_meta($postId, 'solas_slot', true);
        $slot = function_exists('solas_adverts_normalize_slot') ? solas_adverts_normalize_slot($slot) : $slot;

        $days = (int) get_post_meta($postId, 'solas_days_required', true);

        $variation_id = function_exists('solas_adverts_variation_for_slot')
            ? (int) solas_adverts_variation_for_slot($slot, $days)
            : 0;

        // If the selected slot+days combo has no variation configured, block add-to-cart.
        if (defined('SOLAS_ADVERTS_VARIATIONS')) {
            $slotMap = SOLAS_ADVERTS_VARIATIONS;
            if (is_array($slotMap) && isset($slotMap[$slot]) && $days > 0 && !isset($slotMap[$slot][$days])) {
                if (function_exists('wc_add_notice')) {
                    wc_add_notice('That advert duration is not available for the selected slot. Please choose a different duration.', 'error');
                }
                wp_safe_redirect(remove_query_arg(['solas_add_advert','_solas_add_advert'], wp_get_referer() ?: home_url('/')));
                exit;
            }
        }

        $variation = [];
        if (function_exists('wc_get_product') && $variation_id > 0) {
            $v = wc_get_product($variation_id);
            if ($v && method_exists($v, 'get_variation_attributes')) {
                $variation = (array) $v->get_variation_attributes();
            }
        }

        $productId = defined('SOLAS_ADVERTS_PRODUCT_ID') ? (int) SOLAS_ADVERTS_PRODUCT_ID : 0;

        WC()->cart->add_to_cart(
            $productId,
            1,
            $variation_id,
            $variation,
            ['solas_advert_post_id' => $postId]
        );

        wp_safe_redirect(add_query_arg('solas_added', '1', wc_get_cart_url()));
        exit;
    }

    public static function addCartItemData( $cart_item_data, $product_id, $variation_id): array {
        if (!function_exists('solas_adverts_is_advert_product') || !solas_adverts_is_advert_product($product_id, $variation_id)) {
            return $cart_item_data;
        }

        $adPostId = isset($_REQUEST['solas_advert_post_id']) ? (int) $_REQUEST['solas_advert_post_id'] : 0;
        if ($adPostId <= 0) return $cart_item_data;

        $slot     = (string) get_post_meta($adPostId, 'solas_slot', true);
        $startIso = (string) get_post_meta($adPostId, 'solas_start_date', true);
        $endIso   = (string) get_post_meta($adPostId, 'solas_end_date', true);

        $cart_item_data['solas_advert_post_id'] = $adPostId;

        if ($slot && $startIso && $endIso) {
            try {
                $start = new DateTimeImmutable($startIso, wp_timezone());
                $end   = new DateTimeImmutable($endIso, wp_timezone());

                $daysMeta = (int) get_post_meta($adPostId, 'solas_days_required', true);
                $days   = $daysMeta > 0
                    ? $daysMeta
                    : (function_exists('solas_adverts_duration_days') ? (int) solas_adverts_duration_days($start, $end) : 0);

                if ($days > 0) {
                    $cart_item_data['solas_advert_days'] = $days;
                }

                // Soft reservation (30 mins) to avoid race conditions.
                if (function_exists('solas_adverts_reservations_create')) {
                    $token = solas_adverts_reservations_create(
                        (string) (function_exists('solas_adverts_normalize_slot') ? solas_adverts_normalize_slot($slot) : $slot),
                        $startIso,
                        $endIso,
                        $adPostId,
                        get_current_user_id()
                    );
                    if ($token) {
                        $cart_item_data['solas_advert_reservation_token'] = $token;
                    }
                }

            } catch (Throwable $t) {
                // ignore
            }
        }

        // Prevent merging with other advert items
        $cart_item_data['unique_key'] = md5(microtime(true) . rand());

        return $cart_item_data;
    }

    public static function getItemData( $item_data, $cart_item): array {
        if (!empty($cart_item['solas_advert_days'])) {
            $days = (int) $cart_item['solas_advert_days'];
            $item_data[] = [
                'key'   => 'Advert duration',
                'value' => sprintf('%d days', $days),
            ];
        }
        return $item_data;
    }

    public static function addOrderLineItemMeta($item, $cart_item_key, $values, $order): void {
        if (!empty($values['solas_advert_post_id'])) {
            $item->add_meta_data('solas_advert_post_id', (int) $values['solas_advert_post_id'], true);
        }
        if (!empty($values['solas_advert_days'])) {
            $item->add_meta_data('advert_duration_days', (int) $values['solas_advert_days'], true);
        }
    }

    public static function addOrderMeta($order, $data): void {
        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        $ids = [];
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['solas_advert_post_id'])) {
                $ids[] = (int) $cart_item['solas_advert_post_id'];
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (!empty($ids)) {
            $order->update_meta_data('solas_advert_post_id', count($ids) === 1 ? $ids[0] : $ids);
        }
    }

    public static function validateAddToCart( $passed, $product_id, $quantity, $variation_id = 0, $variations = []): bool {
        if (!$passed) return false;

        if (!function_exists('solas_adverts_is_advert_product') || !solas_adverts_is_advert_product($product_id, $variation_id)) {
            return $passed;
        }

        $adPostId = isset($_REQUEST['solas_advert_post_id']) ? (int) $_REQUEST['solas_advert_post_id'] : 0;
        if ($adPostId <= 0) return $passed;

        $slot = (string) get_post_meta($adPostId, 'solas_slot', true);
        $slot = function_exists('solas_adverts_normalize_slot') ? solas_adverts_normalize_slot($slot) : $slot;

        $startIso = (string) get_post_meta($adPostId, 'solas_start_date', true);
        $endIso   = (string) get_post_meta($adPostId, 'solas_end_date', true);

        if ($slot === '' || $startIso === '' || $endIso === '') return $passed;

        try {
            $start = new DateTimeImmutable($startIso, wp_timezone());
            $end   = new DateTimeImmutable($endIso, wp_timezone());
        } catch (Throwable $t) {
            return $passed;
        }

        $capacity = function_exists('solas_adverts_capacity_for_slot') ? (int) solas_adverts_capacity_for_slot($slot) : 1;
        $conflicts_published = function_exists('solas_adverts_count_overlaps') ? (int) solas_adverts_count_overlaps($slot, $start, $end, $adPostId) : 0;
        $conflicts_reserved  = function_exists('solas_adverts_reservations_count_overlaps')
            ? (int) solas_adverts_reservations_count_overlaps($slot, $start, $end, $adPostId)
            : 0;

        $conflicts = $conflicts_published + $conflicts_reserved;

        if ($conflicts >= $capacity) {
            $next = function_exists('solas_adverts_find_next_available_start')
                ? solas_adverts_find_next_available_start($slot, $start, $end, $capacity, $adPostId, 365)
                : null;

            $msg = ($slot === 'footer_mpu')
                ? 'That MPU slot is already at capacity for the selected dates (max 4).'
                : 'That header slot is already booked for the selected dates (max 1).';

            if ($next instanceof DateTimeImmutable) {
                $msg .= ' Next available start date: ' . $next->format('d-m-Y') . '.';
            }
            $msg .= ' Note: dates are temporarily reserved for 30 minutes when added to cart, and confirmed once payment is received.';

            if (function_exists('wc_add_notice')) {
                wc_add_notice($msg, 'error');
            }
            return false;
        }

        return $passed;
    }
}
