<?php
declare(strict_types=1);

namespace Solas\Portal\Woo;

use WC_Order;

defined('ABSPATH') || exit;

/**
 * Thin compatibility layer around Woo Subscriptions (WCS).
 */
final class Subscriptions {

    public static function isActive(): bool {
        return function_exists('wcs_get_subscriptions_for_order')
            || function_exists('wcs_order_contains_renewal')
            || class_exists('WC_Subscriptions');
    }

    /**
     * @return array<int,object>
     */
    public static function getSubscriptionsForOrder($orderId): array {
        $orderId = (int) $orderId;
        if ($orderId <= 0 || !self::isActive() || !function_exists('wcs_get_subscriptions_for_order')) return [];
        $subs = wcs_get_subscriptions_for_order($orderId, ['order_type' => 'any']);
        return is_array($subs) ? $subs : [];
    }

    public static function isRenewalOrder($order): bool {
        if (!self::isActive() || !function_exists('wcs_order_contains_renewal')) return false;
        if ($order instanceof WC_Order) return (bool) wcs_order_contains_renewal($order);
        $orderId = (int) $order;
        if ($orderId > 0 && function_exists('wc_get_order')) {
            $o = wc_get_order($orderId);
            return $o instanceof WC_Order ? (bool) wcs_order_contains_renewal($o) : false;
        }
        return false;
    }
}
