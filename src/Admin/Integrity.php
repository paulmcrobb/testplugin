<?php
declare(strict_types=1);

namespace Solas\Portal\Admin;

use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

final class Integrity {

    private const PAGE_SLUG = 'solas-portal-integrity';

    public static function register(): void {
        add_action('admin_menu', [self::class, 'menu'], 30);
        add_action('admin_post_solas_portal_integrity_flatten_user_meta', [self::class, 'handleFlattenUserMeta']);
        add_action('admin_post_solas_portal_integrity_flatten_user_meta_billing_shipping', [self::class, 'handleFlattenUserMetaBillingShipping']);
    }

    public static function menu(): void {
        add_submenu_page(
            'solas-portal',
            'Integrity',
            'Integrity',
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $counts = self::dryRunCounts();
        ?>
        <div class="wrap">
            <h1>SOLAS Portal – Integrity</h1>

            <p>Read-only diagnostics + safe repair actions (no line item/totals changes).</p>

            <h2>Meta flattening (arrays → strings)</h2>
            <p>If some user address/email meta values were accidentally saved as arrays, WordPress/Woo can emit “Array to string conversion” warnings. This tool safely flattens those values to strings.</p>

            <ul>
                <li><strong>Billing/Shipping address fields:</strong> <?php echo esc_html((string) $counts['flatten_user_meta_billing_shipping']); ?></li>
                <li><strong>Work/Additional + extra emails:</strong> <?php echo esc_html((string) $counts['flatten_user_meta_custom']); ?></li>
            </ul>

            <p><strong>Total pending fixes:</strong> <?php echo esc_html((string) $counts['flatten_user_meta_total']); ?></p>

            <h3>Repair actions</h3>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 16px 0;">
                <?php wp_nonce_field('solas_portal_integrity_flatten_user_meta_billing_shipping'); ?>
                <input type="hidden" name="action" value="solas_portal_integrity_flatten_user_meta_billing_shipping">
                <p>
                    <button class="button" type="submit" <?php disabled($counts['flatten_user_meta_billing_shipping'] < 1); ?>>
                        Repair: flatten billing/shipping only (<?php echo esc_html((string) $counts['flatten_user_meta_billing_shipping']); ?> pending)
                    </button>
                </p>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('solas_portal_integrity_flatten_user_meta'); ?>
                <input type="hidden" name="action" value="solas_portal_integrity_flatten_user_meta">
                <p>
                    <button class="button button-primary" type="submit" <?php disabled($counts['flatten_user_meta_total'] < 1); ?>>
                        Repair: flatten user meta (<?php echo esc_html((string) $counts['flatten_user_meta_total']); ?> pending)
                    </button>
                </p>
            </form>

        </div>
        <?php
    }

    private static function dryRunCounts(): array {
        return [
            'flatten_user_meta_billing_shipping' => self::countFlattenUserMetaBillingShipping(),
            'flatten_user_meta_custom'           => self::countFlattenUserMetaCustom(),
            'flatten_user_meta_total'            => self::countFlattenUserMetaBillingShipping() + self::countFlattenUserMetaCustom(),
        ];
    }

    private static function countFlattenUserMetaBillingShipping(): int {
        global $wpdb;

        $keys = self::getFlattenUserMetaKeysBillingShipping();
        if (empty($keys)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '%s'));
        $sql = "SELECT COUNT(*) FROM {$wpdb->usermeta}
                WHERE meta_key IN ($placeholders)
                AND (meta_value LIKE 'a:%' OR meta_value LIKE 'O:%')";

        $params = $keys;
        $count = (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        return max(0, $count);
    }

    private static function countFlattenUserMetaCustom(): int {
        global $wpdb;

        $keys = self::getFlattenUserMetaKeysCustom();

        $likeWork = $wpdb->esc_like(Meta::WORK_PREFIX) . '%';
        $likeAdd  = $wpdb->esc_like(Meta::ADDITIONAL_PREFIX) . '%';

        $clauses = [];
        $params  = [];
        if (!empty($keys)) {
            $placeholders = implode(',', array_fill(0, count($keys), '%s'));
            $clauses[] = "meta_key IN ($placeholders)";
            $params = array_merge($params, $keys);
        }

        $clauses[] = 'meta_key LIKE %s';
        $clauses[] = 'meta_key LIKE %s';
        $params[] = $likeWork;
        $params[] = $likeAdd;

        $sql = "SELECT COUNT(*) FROM {$wpdb->usermeta}
                WHERE (" . implode(' OR ', $clauses) . ")
                AND (meta_value LIKE 'a:%' OR meta_value LIKE 'O:%')";

        $count = (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        return max(0, $count);
    }

    private static function getFlattenUserMetaKeysBillingShipping(): array {
        return [
            'billing_first_name','billing_last_name','billing_company','billing_address_1','billing_address_2','billing_city','billing_state','billing_postcode','billing_country','billing_phone',
            'shipping_first_name','shipping_last_name','shipping_company','shipping_address_1','shipping_address_2','shipping_city','shipping_state','shipping_postcode','shipping_country',
        ];
    }

    private static function getFlattenUserMetaKeysCustom(): array {
        return [
            Meta::USER_SECONDARY_EMAIL,
            Meta::USER_THIRD_EMAIL,
        ];
    }

    public static function handleFlattenUserMeta(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }
        check_admin_referer('solas_portal_integrity_flatten_user_meta');

        $fixed = self::flattenUserMeta();

        wp_safe_redirect(add_query_arg([
            'page'  => self::PAGE_SLUG,
            'fixed' => $fixed,
        ], admin_url('admin.php')));
        exit;
    }

    public static function handleFlattenUserMetaBillingShipping(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }
        check_admin_referer('solas_portal_integrity_flatten_user_meta_billing_shipping');

        $fixed = self::flattenUserMetaBillingShipping();

        wp_safe_redirect(add_query_arg([
            'page'     => self::PAGE_SLUG,
            'fixed_bs' => $fixed,
        ], admin_url('admin.php')));
        exit;
    }

    private static function flattenUserMeta(): int {
        global $wpdb;

        $keys = array_merge(self::getFlattenUserMetaKeysBillingShipping(), self::getFlattenUserMetaKeysCustom());
        $likeWork = $wpdb->esc_like(Meta::WORK_PREFIX) . '%';
        $likeAdd  = $wpdb->esc_like(Meta::ADDITIONAL_PREFIX) . '%';

        $placeholders = implode(',', array_fill(0, count($keys), '%s'));

        $sql = "SELECT umeta_id, user_id, meta_key, meta_value
                FROM {$wpdb->usermeta}
                WHERE (
                    meta_key IN ($placeholders)
                    OR meta_key LIKE %s
                    OR meta_key LIKE %s
                )
                AND (meta_value LIKE 'a:%' OR meta_value LIKE 'O:%')
                LIMIT 500";

        $params = array_merge($keys, [$likeWork, $likeAdd]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        if (empty($rows)) {
            return 0;
        }

        $fixed = 0;
        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            $key    = (string) $row['meta_key'];
            $raw    = $row['meta_value'];

            $val = maybe_unserialize($raw);
            if (!is_array($val)) {
                continue;
            }
            $flat = Meta::flattenToString($val, '');
            // Write a real string value (update_user_meta will store scalar as-is).
            update_user_meta($userId, $key, $flat);
            $fixed++;
        }

        return $fixed;
    }

    private static function flattenUserMetaBillingShipping(): int {
        global $wpdb;

        $keys = self::getFlattenUserMetaKeysBillingShipping();
        if (empty($keys)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '%s'));

        $sql = "SELECT umeta_id, user_id, meta_key, meta_value
                FROM {$wpdb->usermeta}
                WHERE meta_key IN ($placeholders)
                AND (meta_value LIKE 'a:%' OR meta_value LIKE 'O:%')
                LIMIT 500";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $keys), ARRAY_A);
        if (empty($rows)) {
            return 0;
        }

        $fixed = 0;
        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            $key    = (string) $row['meta_key'];
            $raw    = $row['meta_value'];

            $val = maybe_unserialize($raw);
            if (!is_array($val)) {
                continue;
            }

            $flat = Meta::flattenToString($val, '');
            update_user_meta($userId, $key, $flat);
            $fixed++;
        }

        return $fixed;
    }
}
