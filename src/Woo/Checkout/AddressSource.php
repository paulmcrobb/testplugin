<?php
declare(strict_types=1);

namespace Solas\Portal\Woo\Checkout;

use Solas\Portal\Woo\Addresses\WorkAddress;
use Solas\Portal\Woo\Addresses\AdditionalAddress;

defined('ABSPATH') || exit;

/**
 * Blocks checkout helper: allow customer to choose which saved address
 * should be used as the shipping address (Shipping / Billing / Work).
 */
final class AddressSource {

    public const FIELD_KEY = 'solas/address_source';

    public static function register(): void {
        // Blocks checkout additional field.
        if (function_exists('woocommerce_register_additional_checkout_field')) {
            add_action('init', [__CLASS__, 'registerBlocksField']);
            add_action('woocommerce_store_api_checkout_update_order_from_request', [__CLASS__, 'applyToOrderFromRequest'], 20, 2);
        }

        // Classic checkout fallback.
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'applyToOrderClassic'], 20, 2);
    }

    public static function registerBlocksField(): void {
        woocommerce_register_additional_checkout_field([
            'id'       => self::FIELD_KEY,
            'label'    => __('Use shipping address from', 'solas-portal-new'),
            'location' => 'order',
            'type'     => 'select',
            'required' => false,
            'options'  => [
                [ 'value' => 'shipping', 'label' => __('Shipping address', 'solas-portal-new') ],
                [ 'value' => 'billing',  'label' => __('Billing address', 'solas-portal-new') ],
                [ 'value' => 'work',     'label' => __('Work address', 'solas-portal-new') ],
                [ 'value' => 'additional','label' => __('Additional address', 'solas-portal-new') ],
            ],
        ]);
    }

    /**
     * Store API (Blocks) path.
     */
    public static function applyToOrderFromRequest($order, $request): void {
        if (!$order || !is_user_logged_in()) return;

        // Read additional field from request.
        $source = '';
        if (is_object($request) && method_exists($request, 'get_param')) {
            $extensions = $request->get_param('extensions');
            if (is_array($extensions) && isset($extensions['solas']) && is_array($extensions['solas'])) {
                $source = (string) ($extensions['solas']['address_source'] ?? '');
            }
        }

        $source = sanitize_key($source);
        if ($source === '' && method_exists($order, 'get_meta')) {
            $source = sanitize_key((string) $order->get_meta(self::FIELD_KEY, true));
        }
        if ($source === '') return;

        self::applySourceToOrder($order, $source, get_current_user_id());
    }

    /**
     * Classic checkout fallback.
     */
    public static function applyToOrderClassic($order, $data): void {
        if (!$order || !is_user_logged_in()) return;
        $source = isset($_POST['solas_address_source']) ? sanitize_key((string) wp_unslash($_POST['solas_address_source'])) : '';
        if ($source === '') return;
        self::applySourceToOrder($order, $source, get_current_user_id());
    }

    private static function applySourceToOrder($order, string $source, int $userId): void {
        if (!method_exists($order, 'set_shipping_address_1')) return;

        if ($source === 'billing') {
            $order->set_shipping_first_name($order->get_billing_first_name());
            $order->set_shipping_last_name($order->get_billing_last_name());
            $order->set_shipping_company($order->get_billing_company());
            $order->set_shipping_address_1($order->get_billing_address_1());
            $order->set_shipping_address_2($order->get_billing_address_2());
            $order->set_shipping_city($order->get_billing_city());
            $order->set_shipping_state($order->get_billing_state());
            $order->set_shipping_postcode($order->get_billing_postcode());
            $order->set_shipping_country($order->get_billing_country());
            return;
        }

        if ($source === 'work') {
            $a = WorkAddress::getWorkAddressArray($userId);
            $order->set_shipping_first_name($a['first_name'] ?? '');
            $order->set_shipping_last_name($a['last_name'] ?? '');
            $order->set_shipping_company($a['company'] ?? '');
            $order->set_shipping_address_1($a['address_1'] ?? '');
            $order->set_shipping_address_2($a['address_2'] ?? '');
            $order->set_shipping_city($a['city'] ?? '');
            $order->set_shipping_state($a['state'] ?? '');
            $order->set_shipping_postcode($a['postcode'] ?? '');
            $order->set_shipping_country($a['country'] ?? '');
            return;
        }

        if ($source === 'additional') {
            $a = AdditionalAddress::getAdditionalAddressArray($userId);
            $order->set_shipping_first_name($a['first_name'] ?? '');
            $order->set_shipping_last_name($a['last_name'] ?? '');
            $order->set_shipping_company($a['company'] ?? '');
            $order->set_shipping_address_1($a['address_1'] ?? '');
            $order->set_shipping_address_2($a['address_2'] ?? '');
            $order->set_shipping_city($a['city'] ?? '');
            $order->set_shipping_state($a['state'] ?? '');
            $order->set_shipping_postcode($a['postcode'] ?? '');
            $order->set_shipping_country($a['country'] ?? '');
            return;
        }
    }
}
