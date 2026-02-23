<?php
declare(strict_types=1);

namespace Solas\Portal\Woo\Cart;

defined('ABSPATH') || exit;

/**
 * Centralised product mapping for SOLAS commerce rules.
 *
 * Defaults are hard-coded for now but can be overridden via options and filters.
 */
final class ProductCatalog {

    public static function newMemberApplicationId(): int {
        $id = (int) get_option('solas_product_new_member_application_id', 705);
        /** @var int $id */
        return (int) apply_filters('solas_product_new_member_application_id', $id);
    }

    public static function enrolmentFeeId(): int {
        $id = (int) get_option('solas_product_enrolment_fee_id', 746);
        /** @var int $id */
        return (int) apply_filters('solas_product_enrolment_fee_id', $id);
    }

    /**
     * Course products can be identified either via an explicit ID list, or via a category slug.
     *
     * @return int[]
     */
    public static function courseIds(): array {
        $ids = get_option('solas_product_course_ids', [269, 271, 272, 273]);
        if (!is_array($ids)) $ids = [269, 271, 272, 273];
        $ids = array_values(array_filter(array_map('intval', $ids)));
        /** @var int[] $ids */
        return (array) apply_filters('solas_product_course_ids', $ids);
    }

    public static function courseCategorySlug(): string {
        $slug = (string) get_option('solas_product_course_category_slug', 'evening-courses');
        $slug = sanitize_title($slug);
        /** @var string $slug */
        return (string) apply_filters('solas_product_course_category_slug', $slug);
    }

    /**
     * Giftable products: courses + memberships.
     *
     * Memberships are treated as subscription-type products by default.
     */
    public static function isGiftableProduct(int $productId): bool {
        $product = wc_get_product($productId);
        if (!$product) return false;

        $isCourse = ProductRules::isCourseProduct($productId);

        // "Membership" default heuristic: subscription product types.
        $type = $product->get_type();
        $isMembership = in_array($type, ['subscription', 'subscription_variation', 'variable-subscription', 'simple-subscription'], true);

        $giftable = ($isCourse || $isMembership);
        /** @var bool $giftable */
        return (bool) apply_filters('solas_is_giftable_product', $giftable, $productId, $product);
    }

public function isCourseProductId(int $productId): bool {
    if ($productId <= 0) return false;

    // Primary: explicit configured IDs.
    $ids = $this->courseIds();
    if (in_array($productId, $ids, true)) return true;

    // Secondary: category slug match (if Woo product can be loaded).
    if (function_exists('wc_get_product') && function_exists('has_term')) {
        $slug = $this->courseCategorySlug();
        if ($slug) {
            return has_term($slug, 'product_cat', $productId);
        }
    }
    return false;
}
}
