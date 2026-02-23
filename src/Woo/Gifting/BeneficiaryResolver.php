<?php
declare(strict_types=1);

namespace Solas\Portal\Woo\Gifting;

defined('ABSPATH') || exit;

final class BeneficiaryResolver {

    /**
     * Find an existing user by email; if not found, create one.
     * Returns WP user ID.
     */
    public static function resolveOrCreate(string $email, string $name = '', int $orderId = 0): int {
        $email = sanitize_email($email);
        if (!$email || !is_email($email)) {
            return 0;
        }

        $existing = get_user_by('email', $email);
        if ($existing && $existing->ID) {
            return (int) $existing->ID;
        }

        $altId = self::findByAlternateEmail($email);
        if ($altId > 0) {
            return $altId;
        }

        // Build a username from the email local part.
        $base = strtolower(preg_replace('/[^a-z0-9._-]/i', '', (string) strtok($email, '@')));
        $base = $base ?: 'solasuser';
        $login = $base;

        // Ensure uniqueness.
        $i = 1;
        while (username_exists($login)) {
            $i++;
            $login = $base . $i;
        }

        $pass = wp_generate_password(20, true, true);

        $userData = [
            'user_login'   => $login,
            'user_pass'    => $pass,
            'user_email'   => $email,
            'display_name' => $name ? sanitize_text_field($name) : $login,
            'role'         => 'subscriber',
        ];

        $userId = wp_insert_user($userData);
        if (is_wp_error($userId)) {
            return 0;
        }

        $userId = (int) $userId;

        // Audit meta.
        update_user_meta($userId, 'solas_created_via_gifting', 'yes');
        if ($orderId > 0) {
            update_user_meta($userId, 'solas_created_via_gifting_order_id', $orderId);
        }

        // Trigger standard WP new user email (set-password flow depends on WP version/config).
        // Avoid emailing the raw password.
        if (function_exists('wp_new_user_notification')) {
            @wp_new_user_notification($userId, null, 'user');
        }

        return $userId;
    }


    /**
     * Look up a user by alternate email meta keys (e.g. secondary/third emails).
     */
    private static function findByAlternateEmail(string $email): int {
        $keys = apply_filters('solas_beneficiaryresolver_alt_email_keys', [
            'solas_secondary_email',
            'solas_third_email',
        ]);

        if (!is_array($keys) || empty($keys)) {
            return 0;
        }

        $metaQuery = ['relation' => 'OR'];
        foreach ($keys as $key) {
            $key = is_string($key) ? trim($key) : '';
            if ($key === '') {
                continue;
            }
            $metaQuery[] = [
                'key'     => $key,
                'value'   => $email,
                'compare' => '=',
            ];
        }

        if (count($metaQuery) <= 1) {
            return 0;
        }

        $q = new \WP_User_Query([
            'number'     => 1,
            'fields'     => 'ID',
            'meta_query' => $metaQuery,
        ]);

        $ids = $q->get_results();
        if (is_array($ids) && !empty($ids)) {
            return (int) $ids[0];
        }

        return 0;
    }

}
