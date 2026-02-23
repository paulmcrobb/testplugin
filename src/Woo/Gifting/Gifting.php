<?php
declare(strict_types=1);

namespace Solas\Portal\Woo\Gifting;

use Solas\Portal\Woo\Cart\ProductCatalog;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

final class Gifting {

    // Item meta labels written by ProductPageFields.
    private const META_GIFT_FLAG = Meta::ITEM_GIFT_FLAG;
    private const META_BENEFICIARY_EMAIL = Meta::ITEM_BENEFICIARY_EMAIL;
    private const META_BENEFICIARY_NAME  = Meta::ITEM_BENEFICIARY_NAME;

    public static function register(): void {
        // Subscription creation (best place for subscription-owner transfer).
        if (function_exists('wcs_get_subscriptions_for_order')) {
            add_action('woocommerce_checkout_subscription_created', [__CLASS__, 'onCheckoutSubscriptionCreated'], 20, 2);
        }

        // Fallback for gateways / edge cases: run on order status changes.
        add_action('woocommerce_order_status_processing', [__CLASS__, 'onOrderProcess'], 30, 1);
        add_action('woocommerce_order_status_completed',  [__CLASS__, 'onOrderProcess'], 30, 1);
    }

    /**
     * Transfer subscription to beneficiary when subscription is created at checkout.
     *
     * @param \WC_Subscription $subscription
     * @param \WC_Order $order
     */
    public static function onCheckoutSubscriptionCreated($subscription, $order): void {
        if (!$subscription || !$order || !method_exists($order, 'get_id')) return;

        $subId = method_exists($subscription, 'get_id') ? (int) $subscription->get_id() : 0;
        if ($subId > 0 && $subscription->get_meta('_solas_gifting_processed') === 'yes') {
            return;
        }

        $gift = self::extractGiftFromLineItems($subscription);
        if (!$gift) {
            // As a fallback, look at parent order items if only one beneficiary exists.
            $gift = self::extractGiftFromOrder((int) $order->get_id());
        }

        if (!$gift) return;

        $beneficiaryUserId = BeneficiaryResolver::resolveOrCreate($gift['email'], $gift['name'] ?? '', (int) $order->get_id());
        if ($beneficiaryUserId <= 0) return;

        Transfer::transferSubscriptionToUser($subscription, $beneficiaryUserId);

        if ($subId > 0) {
            $subscription->update_meta_data('_solas_gifting_processed', 'yes');
            $subscription->save();
        }
    }

    /**
     * Process order for course gifts (and subscription fallback if needed).
     */
    public static function onOrderProcess( $orderId): void {
        if ($orderId <= 0) return;

        $order = wc_get_order($orderId);
        if (!$order) return;

        if ($order->get_meta('_solas_gifting_order_processed') === 'yes') {
            return;
        }

        $catalog = new ProductCatalog();

        $beneficiariesIndex = []; // email => userId
        foreach ($order->get_items() as $itemId => $item) {
            $gift = self::extractGiftFromItem($item);
            if (!$gift) continue;

            $email = $gift['email'];
            $name  = $gift['name'] ?? '';

            $userId = $beneficiariesIndex[$email] ?? 0;
            if ($userId <= 0) {
                $userId = BeneficiaryResolver::resolveOrCreate($email, $name, $orderId);
                if ($userId <= 0) continue;
                $beneficiariesIndex[$email] = $userId;
            }

            $productId = (int) $item->get_product_id();

            // Course transfer (LearnDash hook).
            if ($catalog->isCourseProductId($productId)) {
                Transfer::transferCourseProductToUser($productId, $userId, $orderId, (int) $itemId);
            }
        }

        // Subscription fallback: if order has subscription(s) but checkout hook didn’t run.
        if (function_exists('wcs_get_subscriptions_for_order')) {
            $subs = wcs_get_subscriptions_for_order($orderId, ['order_type' => 'parent']);
            foreach ($subs as $sub) {
                if (!$sub || $sub->get_meta('_solas_gifting_processed') === 'yes') continue;
                $gift = self::extractGiftFromLineItems($sub) ?: self::extractGiftFromOrder($orderId);
                if (!$gift) continue;

                $userId = $beneficiariesIndex[$gift['email']] ?? 0;
                if ($userId <= 0) {
                    $userId = BeneficiaryResolver::resolveOrCreate($gift['email'], $gift['name'] ?? '', $orderId);
                    if ($userId <= 0) continue;
                    $beneficiariesIndex[$gift['email']] = $userId;
                }

                Transfer::transferSubscriptionToUser($sub, $userId);
                $sub->update_meta_data('_solas_gifting_processed', 'yes');
                $sub->save();
            }
        }

        $order->update_meta_data('_solas_gifting_order_processed', 'yes');
        $order->save();
    }

    private static function extractGiftFromItem($item): ?array {
        if (!$item || !method_exists($item, 'get_meta')) return null;

        $flag = strtolower((string) $item->get_meta(self::META_GIFT_FLAG));
        if ($flag !== 'yes' && $flag !== '1' && $flag !== 'true') return null;

        $email = sanitize_email((string) $item->get_meta(self::META_BENEFICIARY_EMAIL));
        if (!$email || !is_email($email)) return null;

        $name = sanitize_text_field((string) $item->get_meta(self::META_BENEFICIARY_NAME));

        return ['email' => $email, 'name' => $name];
    }

    private static function extractGiftFromLineItems($orderLike): ?array {
        if (!$orderLike || !method_exists($orderLike, 'get_items')) return null;

        foreach ($orderLike->get_items() as $item) {
            $gift = self::extractGiftFromItem($item);
            if ($gift) return $gift;
        }
        return null;
    }

    private static function extractGiftFromOrder( $orderId): ?array {
        $order = wc_get_order($orderId);
        if (!$order) return null;

        // Prefer aggregate order meta if present.
        $benef = $order->get_meta('_solas_gift_beneficiaries');
        if (is_array($benef) && !empty($benef)) {
            $first = $benef[0] ?? null;
            if (is_array($first)) {
                $email = sanitize_email((string) ($first['email'] ?? ''));
                $name  = sanitize_text_field((string) ($first['name'] ?? ''));
                if ($email && is_email($email)) {
                    return ['email' => $email, 'name' => $name];
                }
            }
        }

        // Otherwise scan items.
        foreach ($order->get_items() as $item) {
            $gift = self::extractGiftFromItem($item);
            if ($gift) return $gift;
        }

        return null;
    }
}
