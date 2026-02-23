<?php
declare(strict_types=1);

namespace Solas\Portal\Woo\Cart;

defined('ABSPATH') || exit;

/**
 * Product-page fields for gifting + new member application.
 *
 * - Allows guest checkout.
 * - Supports multiple gifted applications by forcing unique cart line items.
 * - Auto-adds enrolment fee when application added; fee quantity forced to 1.
 */
final class ProductPageFields {

    public static function register(): void {
        // Render fields on product page.
        add_action('woocommerce_before_add_to_cart_button', [self::class, 'renderGiftFields'], 15);
        add_action('woocommerce_before_add_to_cart_button', [self::class, 'renderNewMemberApplicationFields'], 25);

        // Validate + persist.
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'validateAddToCart'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [self::class, 'addCartItemData'], 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'addOrderItemMeta'], 10, 4);

        // Enrolment fee rules.
        add_action('woocommerce_add_to_cart', [self::class, 'ensureEnrolmentFeeInCart'], 10, 6);
        add_action('woocommerce_before_calculate_totals', [self::class, 'forceEnrolmentFeeQtyToOne'], 10, 1);

        // Store aggregated beneficiary list on the order for reporting.
        add_action('woocommerce_checkout_create_order', [self::class, 'addOrderMeta'], 10, 2);
    }

    public static function renderGiftFields(): void {
        global $product;
        if (!$product instanceof \WC_Product) return;

        $pid = (int) $product->get_id();
        if (!ProductCatalog::isGiftableProduct($pid)) return;

        $checked = !empty($_REQUEST['solas_is_gift_item']);
        $benName = isset($_REQUEST['solas_beneficiary_name_item']) ? sanitize_text_field((string) $_REQUEST['solas_beneficiary_name_item']) : '';
        $benEmail = isset($_REQUEST['solas_beneficiary_email_item']) ? sanitize_email((string) $_REQUEST['solas_beneficiary_email_item']) : '';

        echo '<fieldset class="solas-gift-fields" style="margin:16px 0;padding:12px;border:1px solid #ddd;border-radius:8px;">';
        echo '<legend style="font-weight:600;">Gifting</legend>';

        echo '<p style="margin:0 0 10px;">';
        echo '<label><input type="checkbox" name="solas_is_gift_item" value="1" ' . checked($checked, true, false) . '> This is for someone else</label>';
        echo '</p>';

        echo '<p style="margin:0 0 10px;">';
        echo '<label style="display:block;font-weight:600;">Beneficiary name</label>';
        echo '<input type="text" name="solas_beneficiary_name_item" value="' . esc_attr($benName) . '" style="width:100%;max-width:420px;">';
        echo '</p>';

        echo '<p style="margin:0;">';
        echo '<label style="display:block;font-weight:600;">Beneficiary email address</label>';
        echo '<input type="email" name="solas_beneficiary_email_item" value="' . esc_attr($benEmail) . '" style="width:100%;max-width:420px;">';
        echo '</p>';

        echo '</fieldset>';
    }

    public static function renderNewMemberApplicationFields(): void {
        global $product;
        if (!$product instanceof \WC_Product) return;

        $pid = (int) $product->get_id();
        if ($pid !== ProductCatalog::newMemberApplicationId()) return;

        $passed = !empty($_REQUEST['solas_passed_modules']);
        $employment = isset($_REQUEST['solas_employment_details']) ? sanitize_textarea_field((string) $_REQUEST['solas_employment_details']) : '';
        $recommended = isset($_REQUEST['solas_recommended_by']) ? sanitize_text_field((string) $_REQUEST['solas_recommended_by']) : '';

        echo '<fieldset class="solas-new-member-application" style="margin:16px 0;padding:12px;border:1px solid #ddd;border-radius:8px;">';
        echo '<legend style="font-weight:600;">New Member Application</legend>';

        echo '<p style="margin:0 0 10px;">';
        echo '<label><input type="checkbox" name="solas_passed_modules" value="1" ' . checked($passed, true, false) . '> I confirm I have passed the required modules</label>';
        echo '</p>';

        echo '<p style="margin:0 0 10px;">';
        echo '<label style="display:block;font-weight:600;">Employment details</label>';
        echo '<textarea name="solas_employment_details" rows="4" style="width:100%;max-width:620px;">' . esc_textarea($employment) . '</textarea>';
        echo '</p>';

        echo '<p style="margin:0;">';
        echo '<label style="display:block;font-weight:600;">Recommended by (optional)</label>';
        echo '<input type="text" name="solas_recommended_by" value="' . esc_attr($recommended) . '" style="width:100%;max-width:420px;">';
        echo '</p>';

        echo '</fieldset>';
    }

    public static function validateAddToCart( $passed, $productId, $quantity): bool {
        if (!$passed) return false;

        $productId = (int) $productId;

        // Gift validation: applies to giftable products only.
        if (ProductCatalog::isGiftableProduct($productId) && !empty($_POST['solas_is_gift_item'])) {
            $name = sanitize_text_field((string)($_POST['solas_beneficiary_name_item'] ?? ''));
            $email = sanitize_email((string)($_POST['solas_beneficiary_email_item'] ?? ''));

            if ($name === '') {
                wc_add_notice('Please enter the beneficiary name.', 'error');
                return false;
            }
            if (!$email || !is_email($email)) {
                wc_add_notice('Please enter a valid beneficiary email address.', 'error');
                return false;
            }
        }

        // Application validation.
        if ($productId === ProductCatalog::newMemberApplicationId()) {
            $passedModules = !empty($_POST['solas_passed_modules']);
            $employment = sanitize_textarea_field((string)($_POST['solas_employment_details'] ?? ''));

            if (!$passedModules) {
                wc_add_notice('Please confirm you have passed the required modules.', 'error');
                return false;
            }
            if (trim($employment) === '') {
                wc_add_notice('Please provide employment details.', 'error');
                return false;
            }
        }

        return true;
    }

    public static function addCartItemData( $cartItemData, $productId, $variationId): array {
        $productId = (int) $productId;

        // Gift capture.
        if (ProductCatalog::isGiftableProduct($productId) && !empty($_POST['solas_is_gift_item'])) {
            $cartItemData['solas_is_gift'] = 'yes';
            $cartItemData['solas_beneficiary_name'] = sanitize_text_field((string)($_POST['solas_beneficiary_name_item'] ?? ''));
            $cartItemData['solas_beneficiary_email'] = sanitize_email((string)($_POST['solas_beneficiary_email_item'] ?? ''));
            // Force unique line item so multiple gifts of the same product can coexist.
            $cartItemData['solas_unique'] = wp_generate_uuid4();
        }

        // New member application capture.
        if ($productId === ProductCatalog::newMemberApplicationId()) {
            $cartItemData['solas_new_member_application'] = [
                'passed_modules' => !empty($_POST['solas_passed_modules']) ? 'yes' : 'no',
                'employment_details' => sanitize_textarea_field((string)($_POST['solas_employment_details'] ?? '')),
                'recommended_by' => sanitize_text_field((string)($_POST['solas_recommended_by'] ?? '')),
            ];
            // Force unique line item so multiple applications can be added separately.
            $cartItemData['solas_unique'] = wp_generate_uuid4();
        }

        return $cartItemData;
    }

    public static function addOrderItemMeta(\WC_Order_Item_Product $item, $cartItemKey, $values, \WC_Order $order): void {
        // Gift meta.
        if (!empty($values['solas_is_gift']) && $values['solas_is_gift'] === 'yes') {
            $item->add_meta_data(\Solas\Portal\Woo\Meta::ITEM_GIFT_FLAG, 'yes', true);
            $item->add_meta_data(\Solas\Portal\Woo\Meta::ITEM_BENEFICIARY_NAME, sanitize_text_field((string)($values['solas_beneficiary_name'] ?? '')), true);
            $item->add_meta_data(\Solas\Portal\Woo\Meta::ITEM_BENEFICIARY_EMAIL, sanitize_email((string)($values['solas_beneficiary_email'] ?? '')), true);
        }

        // Application meta.
        if (!empty($values['solas_new_member_application']) && is_array($values['solas_new_member_application'])) {
            $a = $values['solas_new_member_application'];
            $item->add_meta_data('SOLAS - Passed modules confirmed', ((string)($a['passed_modules'] ?? 'no')) === 'yes' ? 'yes' : 'no', true);
            $item->add_meta_data('SOLAS - Employment details', sanitize_textarea_field((string)($a['employment_details'] ?? '')), true);
            $rb = sanitize_text_field((string)($a['recommended_by'] ?? ''));
            if ($rb !== '') {
                $item->add_meta_data('SOLAS - Recommended by', $rb, true);
            }
        }
    }

    public static function addOrderMeta(\WC_Order $order, $data): void {
        // Aggregate beneficiaries across items for reporting.
        $beneficiaries = [];
        foreach ($order->get_items('line_item') as $item) {
            $email = sanitize_email((string) $item->get_meta(\Solas\Portal\Woo\Meta::ITEM_BENEFICIARY_EMAIL));
            $name  = sanitize_text_field((string) $item->get_meta(\Solas\Portal\Woo\Meta::ITEM_BENEFICIARY_NAME));
            if ($email && is_email($email)) {
                $beneficiaries[] = ['name' => $name, 'email' => strtolower($email)];
            }
        }
        if (!empty($beneficiaries)) {
            // De-dupe by email.
            $seen = [];
            $unique = [];
            foreach ($beneficiaries as $b) {
                $e = $b['email'];
                if (isset($seen[$e])) continue;
                $seen[$e] = true;
                $unique[] = $b;
            }
            $order->update_meta_data(\Solas\Portal\Woo\Meta::ORDER_GIFT_BENEFICIARIES, $unique);
        }
    }

    public static function ensureEnrolmentFeeInCart( $cartItemKey, $productId, $quantity, $variationId, $variation, $cartItemData): void {
        $productId = (int) $productId;
        if ($productId !== ProductCatalog::newMemberApplicationId()) return;

        // Auto-add enrolment fee product if missing.
        $feeId = ProductCatalog::enrolmentFeeId();
        if ($feeId <= 0) return;

        if (!ProductRules::cartHasEnrolmentFee()) {
            // Carry beneficiary info onto fee item if gifting application.
            $feeData = [];
            if (!empty($cartItemData['solas_is_gift']) && $cartItemData['solas_is_gift'] === 'yes') {
                $feeData['solas_is_gift'] = 'yes';
                $feeData['solas_beneficiary_name'] = sanitize_text_field((string)($cartItemData['solas_beneficiary_name'] ?? ''));
                $feeData['solas_beneficiary_email'] = sanitize_email((string)($cartItemData['solas_beneficiary_email'] ?? ''));
                $feeData['solas_unique'] = wp_generate_uuid4();
            }
            WC()->cart->add_to_cart($feeId, 1, 0, [], $feeData);
        }
    }

    public static function forceEnrolmentFeeQtyToOne($cart): void {
        if (!($cart instanceof \WC_Cart)) return;
        $feeId = ProductCatalog::enrolmentFeeId();
        if ($feeId <= 0) return;

        foreach ($cart->get_cart() as $key => $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            if ($pid === $feeId && (int) ($item['quantity'] ?? 1) !== 1) {
                $cart->set_quantity($key, 1, false);
            }
        }
    }
}
