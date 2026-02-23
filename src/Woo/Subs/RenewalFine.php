<?php
declare(strict_types=1);

namespace Solas\Portal\Woo\Subs;

use Solas\Portal\Woo\Memberships;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

/**
 * Late Renewal Fine automation (Woo Subscriptions renewal orders).
 *
 * Applies configured fine product to renewal orders created after a configured deadline
 * (default: after 30 Nov, i.e. from 1 Dec).
 *
 * Applicability is controlled via selected Woo Membership plans (preferred) or fallback
 * subscription product IDs.
 */
final class RenewalFine {

    public const OPTION_ENABLED = 'solas_renewal_fine_enabled';
    public const OPTION_FINE_PRODUCT_ID = 'solas_renewal_fine_product_id';
    public const OPTION_DEADLINE_MMDD = 'solas_renewal_fine_deadline_mmdd'; // e.g. '11-30'
    public const OPTION_PLAN_IDS = 'solas_renewal_fine_plan_ids'; // array of WC Membership plan IDs
    public const OPTION_SUBSCRIPTION_PRODUCT_IDS = 'solas_renewal_fine_subscription_product_ids'; // fallback

    public const ORDER_META_APPLIED = Meta::ORDER_RENEWAL_FINE_APPLIED;

    public static function register(): void {
        // Admin UI
        add_action('admin_menu', [self::class, 'registerAdminMenu']);
        add_action('admin_init', [self::class, 'registerSettings']);

        // Apply fine on renewal order creation (WCS)
        add_action('wcs_new_order_created', [self::class, 'maybeApplyFineToRenewalOrder'], 20, 3);
    }

    public static function isEnabled(): bool {
        return (bool) get_option(self::OPTION_ENABLED, true);
    }

    public static function fineProductId(): int {
        return (int) get_option(self::OPTION_FINE_PRODUCT_ID, 912);
    }

    public static function deadlineMmdd(): string {
        $val = (string) get_option(self::OPTION_DEADLINE_MMDD, '11-30');
        return preg_match('/^\d{2}-\d{2}$/', $val) ? $val : '11-30';
    }

    /** Deadline is inclusive (11-30). Fine applies from next day (12-01). */
    public static function isAfterDeadline(\DateTimeImmutable $now): bool {
        $mmdd = self::deadlineMmdd();
        [$mm, $dd] = array_map('intval', explode('-', $mmdd));

        $tz = $now->getTimezone();
        $deadline = (new \DateTimeImmutable($now->format('Y') . '-' . sprintf('%02d-%02d', $mm, $dd), $tz))
            ->setTime(23, 59, 59);

        return $now > $deadline;
    }

    public static function registerAdminMenu(): void {
        if (!current_user_can('manage_options')) return;

        add_submenu_page(
            'woocommerce',
            'Late Renewal Fine',
            'Late Renewal Fine',
            'manage_options',
            'solas-renewal-fine',
            [self::class, 'renderAdminPage']
        );
    }

    public static function registerSettings(): void {
        register_setting('solas_renewal_fine', self::OPTION_ENABLED);
        register_setting('solas_renewal_fine', self::OPTION_FINE_PRODUCT_ID);
        register_setting('solas_renewal_fine', self::OPTION_DEADLINE_MMDD);
        register_setting('solas_renewal_fine', self::OPTION_PLAN_IDS);
        register_setting('solas_renewal_fine', self::OPTION_SUBSCRIPTION_PRODUCT_IDS);

        // Defaults (only set if missing)
        if (get_option(self::OPTION_FINE_PRODUCT_ID, null) === null) {
            update_option(self::OPTION_FINE_PRODUCT_ID, 912);
        }
        if (get_option(self::OPTION_DEADLINE_MMDD, null) === null) {
            update_option(self::OPTION_DEADLINE_MMDD, '11-30');
        }
        if (get_option(self::OPTION_ENABLED, null) === null) {
            update_option(self::OPTION_ENABLED, true);
        }
    }

    public static function renderAdminPage(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $enabled = self::isEnabled();
        $fineProductId = self::fineProductId();
        $deadline = self::deadlineMmdd();

        $selectedPlanIds = get_option(self::OPTION_PLAN_IDS, []);
        if (!is_array($selectedPlanIds)) $selectedPlanIds = [];

        $fallbackProductIds = get_option(self::OPTION_SUBSCRIPTION_PRODUCT_IDS, []);
        if (!is_array($fallbackProductIds)) $fallbackProductIds = [];

        $plans = Memberships::getPlans();

        ?>
        <div class="wrap">
            <h1>Late Renewal Fine</h1>
            <p>Automatically adds the fine product to Woo Subscriptions <strong>renewal orders</strong> created after the deadline (default: from 1 Dec).</p>

            <form method="post" action="options.php">
                <?php settings_fields('solas_renewal_fine'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="solas_renewal_fine_enabled">Enabled</label></th>
                        <td>
                            <input type="checkbox" id="solas_renewal_fine_enabled" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="1" <?php checked($enabled); ?> />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="solas_renewal_fine_product_id">Fine product ID</label></th>
                        <td>
                            <input type="number" id="solas_renewal_fine_product_id" name="<?php echo esc_attr(self::OPTION_FINE_PRODUCT_ID); ?>" value="<?php echo esc_attr((string) $fineProductId); ?>" />
                            <p class="description">Product added to renewal orders when late. Default: 912.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="solas_renewal_fine_deadline_mmdd">Deadline (MM-DD)</label></th>
                        <td>
                            <input type="text" id="solas_renewal_fine_deadline_mmdd" name="<?php echo esc_attr(self::OPTION_DEADLINE_MMDD); ?>" value="<?php echo esc_attr($deadline); ?>" placeholder="11-30" />
                            <p class="description">Fine applies from the next day (e.g. deadline 11-30 means fine from 12-01).</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Applies to membership plans</th>
                        <td>
                            <?php if (!empty($plans)) : ?>
                                <?php foreach ($plans as $plan) :
                                    $planId = is_object($plan) && method_exists($plan, 'get_id') ? (int) $plan->get_id() : 0;
                                    $planName = is_object($plan) && method_exists($plan, 'get_name') ? (string) $plan->get_name() : 'Plan';
                                    if (!$planId) continue;
                                    ?>
                                    <label style="display:block;margin:2px 0;">
                                        <input type="checkbox"
                                               name="<?php echo esc_attr(self::OPTION_PLAN_IDS); ?>[]"
                                               value="<?php echo esc_attr((string) $planId); ?>"
                                            <?php checked(in_array($planId, $selectedPlanIds, true)); ?>
                                        />
                                        <?php echo esc_html($planName); ?> (ID: <?php echo esc_html((string) $planId); ?>)
                                    </label>
                                <?php endforeach; ?>
                                <p class="description">Select plans to which the late renewal fine should apply (e.g., Associate Member, Full Member, Honorary Member).</p>
                            <?php else : ?>
                                <p><em>Woo Memberships plans not detected.</em></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Fallback: subscription product IDs</th>
                        <td>
                            <input type="text"
                                   name="<?php echo esc_attr(self::OPTION_SUBSCRIPTION_PRODUCT_IDS); ?>_csv"
                                   value="<?php echo esc_attr(implode(',', array_map('intval', $fallbackProductIds))); ?>"
                                   placeholder="123,456"
                                   style="width: 320px;"
                            />
                            <p class="description">If plan-based detection cannot be resolved, these product IDs will be used as a fallback check.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * WCS hook: wcs_new_order_created( $order, $subscription, $type ).
     *
     * @param \WC_Order $order
     * @param \WC_Subscription $subscription
     * @param string $type
     */
    public static function maybeApplyFineToRenewalOrder($order, $subscription, $type): void {
        if (!self::isEnabled()) return;

        if (!is_object($order) || !method_exists($order, 'get_id')) return;
        if (!is_object($subscription) || !method_exists($subscription, 'get_id')) return;

        $type = (string) $type;
        if ($type !== 'renewal_order') return;

        $orderId = (int) $order->get_id();
        if ($order->get_meta(self::ORDER_META_APPLIED) === 'yes') return;

        // Parse fallback CSV if present (admin UI convenience)
        $csvKey = self::OPTION_SUBSCRIPTION_PRODUCT_IDS . '_csv';
        if (isset($_POST[$csvKey]) && is_string($_POST[$csvKey])) {
            // handled in sanitize callback? We'll also handle in admin_init below via option update hook.
        }

        $now = new \DateTimeImmutable('now', wp_timezone());
        if (!self::isAfterDeadline($now)) return;

        if (!self::subscriptionMatchesApplicability($subscription)) return;

        $fineProductId = self::fineProductId();
        if ($fineProductId <= 0) return;

        if (self::orderAlreadyHasProduct($order, $fineProductId)) {
            $order->update_meta_data(self::ORDER_META_APPLIED, 'yes');
            $order->save();
            return;
        }

        $product = wc_get_product($fineProductId);
        if (!$product) return;

        $item = new \WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity(1);
        $item->set_subtotal((float) $product->get_price());
        $item->set_total((float) $product->get_price());
        $item->add_meta_data('_solas_added_by', 'renewalfine', true);

        $order->add_item($item);

        $order->update_meta_data(self::ORDER_META_APPLIED, 'yes');
        $order->calculate_totals(true);
        $order->save();
    }

    private static function subscriptionMatchesApplicability($subscription): bool {
        // Prefer plan-based applicability via Woo Memberships.
        $selectedPlanIds = get_option(self::OPTION_PLAN_IDS, []);
        if (!is_array($selectedPlanIds)) $selectedPlanIds = [];
        $selectedPlanIds = array_values(array_filter(array_map('intval', $selectedPlanIds)));

        $subProductIds = self::subscriptionProductIds($subscription);

        if (!empty($selectedPlanIds) && Memberships::isActive()) {
            foreach ($selectedPlanIds as $planId) {
                $plan = Memberships::getPlanById((int) $planId);
                if (!$plan || !is_object($plan)) continue;

                foreach ($subProductIds as $pid) {
                    if (self::planGrantsAccessFromProduct($plan, $pid)) {
                        return true;
                    }
                }
            }
        }

        // Fallback: configured subscription product IDs
        $fallback = get_option(self::OPTION_SUBSCRIPTION_PRODUCT_IDS, []);
        if (!is_array($fallback)) $fallback = [];
        $fallback = array_values(array_filter(array_map('intval', $fallback)));

        if (empty($fallback)) {
            // If no selection made, be conservative: do NOT apply.
            return false;
        }

        foreach ($subProductIds as $pid) {
            if (in_array($pid, $fallback, true)) return true;
        }

        return false;
    }

    private static function planGrantsAccessFromProduct($plan, $productId): bool {
        // Different WC Memberships versions expose different APIs; try a few.
        if (method_exists($plan, 'has_product')) {
            try {
                return (bool) $plan->has_product($productId);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (method_exists($plan, 'has_product_id')) {
            try {
                return (bool) $plan->has_product_id($productId);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // As a last resort, try plan rules access object.
        if (method_exists($plan, 'get_access_method')) {
            // can't reliably infer without rules API; return false.
        }

        // Best-effort: if the plan exposes purchase product IDs, use them.
        if (is_object($plan) && method_exists($plan, 'get_id')) {
            $purchaseIds = Memberships::getPlanPurchaseProductIds((int) $plan->get_id());
            if (!empty($purchaseIds) && in_array((int) $productId, $purchaseIds, true)) {
                return true;
            }
        }

        return false;
    }

    private static function subscriptionProductIds($subscription): array {
        $ids = [];
        if (is_object($subscription) && method_exists($subscription, 'get_items')) {
            foreach ($subscription->get_items() as $item) {
                if (!is_object($item) || !method_exists($item, 'get_product_id')) continue;
                $pid = (int) $item->get_product_id();
                if ($pid) $ids[] = $pid;
            }
        }
        return array_values(array_unique($ids));
    }

    private static function orderAlreadyHasProduct($order, $productId): bool {
        if (!is_object($order) || !method_exists($order, 'get_items')) return false;
        foreach ($order->get_items('line_item') as $item) {
            if (!is_object($item) || !method_exists($item, 'get_product_id')) continue;
            if ((int) $item->get_product_id() === $productId) return true;
        }
        return false;
    }
}
