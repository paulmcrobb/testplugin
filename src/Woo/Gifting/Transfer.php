<?php
declare(strict_types=1);

namespace Solas\Portal\Woo\Gifting;

defined('ABSPATH') || exit;

final class Transfer {

    /**
     * Transfer a subscription to beneficiary (transfer model).
     */
    public static function transferSubscriptionToUser($subscription, int $beneficiaryUserId): bool {
        if (!$subscription || $beneficiaryUserId <= 0) return false;

        // Woo Subscriptions object should support set_customer_id
        if (method_exists($subscription, 'set_customer_id')) {
            $subscription->set_customer_id($beneficiaryUserId);
        } elseif (method_exists($subscription, 'set_customer_user')) {
            $subscription->set_customer_user($beneficiaryUserId);
        } else {
            return false;
        }

        // Store audit meta.
        if (method_exists($subscription, 'update_meta_data')) {
            $subscription->update_meta_data('_solas_gift_beneficiary_user_id', $beneficiaryUserId);
            $subscription->update_meta_data('_solas_gifting_transferred_at', wp_date('c'));
        }

        if (method_exists($subscription, 'save')) {
            $subscription->save();
        }

        /**
         * Allow other modules to react (e.g., grant memberships manually if required).
         */
        do_action('solas_gifting_subscription_transferred', $subscription, $beneficiaryUserId);

        return true;
    }

    /**
     * Transfer course access for a gifted course product.
     * We don't assume a mapping; we fire an action hook for LearnDash enrolment.
     */
    public static function transferCourseProductToUser(int $productId, int $beneficiaryUserId, int $orderId = 0, int $orderItemId = 0): void {
        if ($productId <= 0 || $beneficiaryUserId <= 0) return;

        /**
         * Hook point for LearnDash / course access logic.
         *
         * @param int $productId
         * @param int $beneficiaryUserId
         * @param int $orderId
         * @param int $orderItemId
         */
        do_action('solas_gifting_transfer_course_access', $productId, $beneficiaryUserId, $orderId, $orderItemId);
    }
}
