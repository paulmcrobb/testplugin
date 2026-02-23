<?php
declare(strict_types=1);

namespace Solas\Portal\Woo\Addresses;

use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

/**
 * Additional address (third saved address besides billing/shipping).
 * Stored in user meta with prefix additional_.
 */
final class AdditionalAddress {

    public const ENDPOINT = 'additional-address';
    public const NONCE_ACTION = 'solas_save_additional_address';
    public const PREFIX = 'additional_';

    public static function register(): void {
        add_action('init', [__CLASS__, 'addEndpoint']);

        // Add card to My Account → Addresses.
        add_filter('woocommerce_my_account_get_addresses', [__CLASS__, 'addToAddressBook'], 30, 2);

        // Provide formatted address.
        add_filter('woocommerce_my_account_my_address_formatted_address', [__CLASS__, 'formattedAddress'], 30, 3);

        // Point edit link to our endpoint.
        add_filter('woocommerce_get_endpoint_url', [__CLASS__, 'endpointUrl'], 30, 4);

        // Render endpoint.
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [__CLASS__, 'renderEndpoint']);

        add_filter('query_vars', [__CLASS__, 'addQueryVar']);
    }

    public static function addEndpoint(): void {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public static function addQueryVar(array $vars): array {
        $vars[] = self::ENDPOINT;
        return $vars;
    }

    public static function addToAddressBook($addresses, $customerId): array {
        $addresses = is_array($addresses) ? $addresses : [];
        $addresses['additional'] = __('Additional address', 'solas-portal-new');
        return $addresses;
    }

    public static function endpointUrl($url, $endpoint, $value, $permalink): string {
        $url = (string) $url;
        $endpoint = (string) $endpoint;
        $permalink = (string) $permalink;
        if ($endpoint === 'edit-address' && (string) $value === 'additional') {
            return wc_get_endpoint_url(self::ENDPOINT, '', $permalink);
        }
        return $url;
    }

    public static function formattedAddress($address, $customerId, $name): array {
        $address = is_array($address) ? $address : [];
        $customerId = (int) $customerId;
        $name = (string) $name;
        if ($name !== 'additional') {
            return $address;
        }
        return self::getAdditionalAddressArray($customerId);
    }

    public static function getAdditionalAddressArray(int $userId): array {
        $get = static function (string $key) use ($userId): string {
            $raw = Meta::getUserMetaString($userId, self::PREFIX . $key, '');
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

    public static function renderEndpoint(): void {
        if (!is_user_logged_in()) {
            echo '<p>' . esc_html__('Please log in to manage your address.', 'solas-portal-new') . '</p>';
            return;
        }

        $userId = get_current_user_id();

        if (!empty($_POST['solas_additional_address_save'])) {
            self::handleSave($userId);
        }

        $values = self::getAdditionalAddressArray($userId);

        echo '<h3>' . esc_html__('Additional address', 'solas-portal-new') . '</h3>';
        echo '<form method="post" class="solas-additional-address-form">';

        wp_nonce_field(self::NONCE_ACTION, '_solas_additional_nonce');

        echo '<p style="margin:0 0 12px;">';
        echo '<button type="button" class="button" id="solas-additional-copy-from-shipping">' . esc_html__('Copy from shipping', 'solas-portal-new') . '</button>';
        echo '</p>';

        foreach (self::getFields() as $key => $field) {
            $value = $values[$key] ?? '';
            woocommerce_form_field('solas_additional_' . $key, $field, $value);
        }

        echo '<p><button type="submit" name="solas_additional_address_save" value="1" class="button button-primary">' . esc_html__('Save additional address', 'solas-portal-new') . '</button></p>';
        echo '</form>';

        self::renderCopyScript($userId);
    }

    private static function getFields(): array {
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
        if (!isset($_POST['_solas_additional_nonce']) || !wp_verify_nonce((string) $_POST['_solas_additional_nonce'], self::NONCE_ACTION)) {
            wc_add_notice(__('Security check failed. Please try again.', 'solas-portal-new'), 'error');
            return;
        }

        if (!current_user_can('read')) {
            wc_add_notice(__('You do not have permission to edit this address.', 'solas-portal-new'), 'error');
            return;
        }

        $fields = array_keys(self::getFields());
        foreach ($fields as $key) {
            $postedKey = 'solas_additional_' . $key;
            $raw = $_POST[$postedKey] ?? '';
            if (is_array($raw)) {
                $raw = reset($raw);
            }
            $val = is_scalar($raw) ? (string) wp_unslash($raw) : '';
            $val = sanitize_text_field($val);
            update_user_meta($userId, self::PREFIX . $key, $val);
        }

        wc_add_notice(__('Additional address updated.', 'solas-portal-new'), 'success');
    }

    private static function renderCopyScript(int $userId): void {
        $customer = class_exists('WC_Customer') ? new \WC_Customer($userId) : null;
        $shipping = [];
        if ($customer) {
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
        $payload = wp_json_encode(['shipping' => $shipping]);

        echo '<script>(function(){' .
            'var data=' . $payload . ';' .
            'function fill(src){ if(!src) return; ["company","first_name","last_name","address_1","address_2","city","postcode","country","state"].forEach(function(field){' .
            ' var a=document.querySelector("input[name=\\"solas_additional_"+field+"\\"], select[name=\\"solas_additional_"+field+"\\"]");' .
            ' if(a){ a.value=(src[field]||""); a.dispatchEvent(new Event("change")); }' .
            '}); }' .
            'var s=document.getElementById("solas-additional-copy-from-shipping");' .
            'if(s){ s.addEventListener("click", function(){ fill(data.shipping); }); }' .
            '})();</script>';
    }
}
