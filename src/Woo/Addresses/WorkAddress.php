<?php
declare(strict_types=1);

namespace Solas\Portal\Woo\Addresses;

use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

final class WorkAddress {

    public const ENDPOINT = 'work-address';
    public const NONCE_ACTION = 'solas_save_work_address';

    /** User meta prefix for work address fields. */
    public const PREFIX = 'work_';

    public static function register(): void {
        add_action('init', [__CLASS__, 'addEndpoint']);

        // Add "Work address" card to the Addresses page.
        add_filter('woocommerce_my_account_get_addresses', [__CLASS__, 'addToAddressBook'], 20, 2);

        // Provide formatted address data for the Addresses page.
        add_filter('woocommerce_my_account_my_address_formatted_address', [__CLASS__, 'formattedAddress'], 20, 3);

        // Make the "Edit" link for work address point to our endpoint.
        add_filter('woocommerce_get_endpoint_url', [__CLASS__, 'endpointUrl'], 20, 4);

        // Render the custom endpoint.
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [__CLASS__, 'renderEndpoint']);

        // Ensure query var is recognised.
        add_filter('query_vars', [__CLASS__, 'addQueryVar']);
    }

    public static function endpointUrl($url, $endpoint, $value, $permalink): string {
        $url = (string) $url;
        $endpoint = (string) $endpoint;
        $permalink = (string) $permalink;
        if ($endpoint === 'edit-address' && (string) $value === 'work') {
            return wc_get_endpoint_url(self::ENDPOINT, '', $permalink);
        }
        return $url;
    }

    public static function formattedAddress($address, $customerId, $name): array {
        $address = is_array($address) ? $address : [];
        $customerId = (int) $customerId;
        $name = (string) $name;
        if ($name !== 'work') {
            return $address;
        }
        return self::getWorkAddressArray($customerId);
    }

    public static function addEndpoint(): void {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public static function addQueryVar(array $vars): array {
        $vars[] = self::ENDPOINT;
        return $vars;
    }

    /**
     * Adds work address as a third address in My Account → Addresses.
     */
    public static function addToAddressBook($addresses, $customerId): array {
        $addresses = is_array($addresses) ? $addresses : [];
        $addresses['work'] = __('Work address', 'solas-portal-new');
        return $addresses;
    }

    /**
     * Woo uses `woocommerce_my_account_my_address_formatted_address` filter
     * to build display text. For our custom type, it calls `get_user_meta`.
     */
    public static function getWorkAddressArray(int $userId): array {
        $get = static function (string $key) use ($userId): string {
            $raw = Meta::getUserMetaString($userId, self::PREFIX . $key, '');

            // Defensive: user meta can be stored as arrays by accident (e.g. plugin conflicts / copy tools).
            if (is_array($raw)) {
                $raw = reset($raw);
            }

            return is_scalar($raw) ? (string) $raw : '';
        };

        return [
            'first_name' => $get('first_name'),
            'last_name'  => $get('last_name'),
            'company'    => $get('company'),
            'address_1'  => $get('address_1'),
            'address_2'  => $get('address_2'),
            'city'       => $get('city'),
            'state'      => $get('state'),
            'postcode'   => $get('postcode'),
            'country'    => $get('country'),
        ];
    }

    

private static function sanitizeAddressArray(array $address): array {
    foreach ($address as $k => $v) {
        if (is_array($v)) {
            $v = reset($v);
        }
        $address[$k] = is_scalar($v) ? (string) $v : '';
    }
    return $address;
}

public static function renderEndpoint(): void {
        if (!is_user_logged_in()) {
            echo '<p>' . esc_html__('Please log in to manage your address.', 'solas-portal-new') . '</p>';
            return;
        }

        $userId = get_current_user_id();

        // Handle save.
        if (!empty($_POST['solas_work_address_save'])) {
            self::handleSave($userId);
        }

        $values = self::getWorkAddressArray($userId);
        $countries = \WC()->countries;
        $allowedCountries = $countries ? $countries->get_allowed_countries() : [];

        echo '<h3>' . esc_html__('Work address', 'solas-portal-new') . '</h3>';
        echo '<form method="post" class="solas-work-address-form">';

        wp_nonce_field(self::NONCE_ACTION, '_solas_work_nonce');

        echo '<p style="margin:0 0 12px;">';
        echo '<button type="button" class="button" id="solas-copy-from-billing">' . esc_html__('Copy from billing', 'solas-portal-new') . '</button> ';
        echo '<button type="button" class="button" id="solas-copy-from-shipping">' . esc_html__('Copy from shipping', 'solas-portal-new') . '</button>';
        echo '</p>';

        $fields = self::getFields();
        foreach ($fields as $key => $field) {
            $value = $values[$key] ?? '';
            woocommerce_form_field(
                'solas_work_' . $key,
                $field,
                $value
            );
        }

        echo '<p><button type="submit" name="solas_work_address_save" value="1" class="button button-primary">' . esc_html__('Save work address', 'solas-portal-new') . '</button></p>';
        echo '</form>';

        // Copy helpers.
        self::renderCopyScript($userId);
    }

    private static function getFields(): array {
        // Minimal set aligning with Woo address fields.
        return [
            'company'    => [
                'type'     => 'text',
                'label'    => __('Company name', 'solas-portal-new'),
                'required' => false,
                'class'    => ['form-row-wide'],
            ],
            'first_name' => [
                'type'     => 'text',
                'label'    => __('First name', 'solas-portal-new'),
                'required' => false,
                'class'    => ['form-row-first'],
            ],
            'last_name' => [
                'type'     => 'text',
                'label'    => __('Last name', 'solas-portal-new'),
                'required' => false,
                'class'    => ['form-row-last'],
            ],
            'address_1' => [
                'type'     => 'text',
                'label'    => __('Address line 1', 'solas-portal-new'),
                'required' => false,
                'class'    => ['form-row-wide'],
            ],
            'address_2' => [
                'type'     => 'text',
                'label'    => __('Address line 2', 'solas-portal-new'),
                'required' => false,
                'class'    => ['form-row-wide'],
            ],
            'city' => [
                'type'     => 'text',
                'label'    => __('Town / City', 'solas-portal-new'),
                'required' => false,
                'class'    => ['form-row-wide'],
            ],
            'postcode' => [
                'type'     => 'text',
                'label'    => __('Postcode', 'solas-portal-new'),
                'required' => false,
                'class'    => ['form-row-wide'],
            ],
            'country' => [
                'type'     => 'country',
                'label'    => __('Country / Region', 'solas-portal-new'),
                'required' => false,
                'class'    => ['form-row-wide'],
            ],
            'state' => [
                'type'     => 'state',
                'label'    => __('State / County', 'solas-portal-new'),
                'required' => false,
                'class'    => ['form-row-wide'],
            ],
        ];
    }

    private static function handleSave(int $userId): void {
        if (!isset($_POST['_solas_work_nonce']) || !wp_verify_nonce((string) $_POST['_solas_work_nonce'], self::NONCE_ACTION)) {
            wc_add_notice(__('Security check failed. Please try again.', 'solas-portal-new'), 'error');
            return;
        }

        if (!current_user_can('read')) {
            wc_add_notice(__('You do not have permission to edit this address.', 'solas-portal-new'), 'error');
            return;
        }

        $fields = array_keys(self::getFields());
        foreach ($fields as $key) {
            $postedKey = 'solas_work_' . $key;
            $raw = $_POST[$postedKey] ?? '';
            if (is_array($raw)) {
                $raw = reset($raw);
            }
            $val = is_scalar($raw) ? (string) wp_unslash($raw) : '';
            $val = sanitize_text_field($val);
            update_user_meta($userId, self::PREFIX . $key, $val);
        }

        wc_add_notice(__('Work address updated.', 'solas-portal-new'), 'success');
    }

    private static function renderCopyScript(int $userId): void {
        // Copy from saved billing/shipping addresses for this user.
        $customer = class_exists('WC_Customer') ? new \WC_Customer($userId) : null;
        $billing = [];
        $shipping = [];

        if ($customer) {
            $billing = [
                'company'    => (string) $customer->get_billing_company(),
                'first_name' => (string) $customer->get_billing_first_name(),
                'last_name'  => (string) $customer->get_billing_last_name(),
                'address_1'  => (string) $customer->get_billing_address_1(),
                'address_2'  => (string) $customer->get_billing_address_2(),
                'city'       => (string) $customer->get_billing_city(),
                'postcode'   => (string) $customer->get_billing_postcode(),
                'country'    => (string) $customer->get_billing_country(),
                'state'      => (string) $customer->get_billing_state(),
            ];

            $shipping = [
                'company'    => (string) $customer->get_shipping_company(),
                'first_name' => (string) $customer->get_shipping_first_name(),
                'last_name'  => (string) $customer->get_shipping_last_name(),
                'address_1'  => (string) $customer->get_shipping_address_1(),
                'address_2'  => (string) $customer->get_shipping_address_2(),
                'city'       => (string) $customer->get_shipping_city(),
                'postcode'   => (string) $customer->get_shipping_postcode(),
                'country'    => (string) $customer->get_shipping_country(),
                'state'      => (string) $customer->get_shipping_state(),
            ];
        }

        $payload = wp_json_encode([
            'billing'  => $billing,
            'shipping' => $shipping,
        ]);

        echo '<script>(function(){' .
            'var data=' . $payload . ';' .
            'function fill(src){' .
            ' if(!src) return;' .
            ' ["company","first_name","last_name","address_1","address_2","city","postcode","country","state"].forEach(function(field){' .
            '  var w=document.querySelector("input[name=\\"solas_work_"+field+"\\"], select[name=\\"solas_work_"+field+"\\"]");' .
            '  if(w){ w.value=(src[field]||""); w.dispatchEvent(new Event("change")); }' .
            ' });' .
            '}' .
            'var b=document.getElementById("solas-copy-from-billing");' .
            'if(b){ b.addEventListener("click", function(){ fill(data.billing); }); }' .
            'var s=document.getElementById("solas-copy-from-shipping");' .
            'if(s){ s.addEventListener("click", function(){ fill(data.shipping); }); }' .
            '})();</script>';
    }
}
