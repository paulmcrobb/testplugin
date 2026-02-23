<?php
declare(strict_types=1);

namespace Solas\Portal\Woo\Cart;

defined('ABSPATH') || exit;

final class ProductRules {

    /**
     * True if cart contains any of the product IDs (matching product_id or variation_id).
     *
     * @param int[] $productIds
     */
    public static function cartHasAny(array $productIds): bool {
        if (!function_exists('WC') || !WC()->cart) return false;
        $ids = array_values(array_filter(array_map('intval', $productIds)));
        if (empty($ids)) return false;

        foreach (WC()->cart->get_cart() as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $vid = (int) ($item['variation_id'] ?? 0);
            if (in_array($pid, $ids, true) || ($vid && in_array($vid, $ids, true))) return true;
        }
        return false;
    }

    public static function cartHasProduct(int $productId): bool {
        return self::cartHasAny([$productId]);
    }

    public static function cartHasCourseProducts(): bool {
        // Prefer category logic if configured and exists.
        $slug = ProductCatalog::courseCategorySlug();
        if ($slug !== '') {
            foreach (self::cartProductIdsAndVariationIds() as $pid) {
                if (self::productHasCategorySlug($pid, $slug)) return true;
            }
        }
        return self::cartHasAny(ProductCatalog::courseIds());
    }

    public static function cartHasNewMemberApplication(): bool {
        return self::cartHasProduct(ProductCatalog::newMemberApplicationId());
    }

    public static function cartHasEnrolmentFee(): bool {
        return self::cartHasProduct(ProductCatalog::enrolmentFeeId());
    }

    public static function isCourseProduct(int $productId): bool {
        $slug = ProductCatalog::courseCategorySlug();
        if ($slug !== '' && self::productHasCategorySlug($productId, $slug)) return true;
        return in_array((int)$productId, ProductCatalog::courseIds(), true);
    }

    private static function productHasCategorySlug(int $productId, string $slug): bool {
        $slug = sanitize_title($slug);
        if ($slug === '') return false;
        $terms = get_the_terms($productId, 'product_cat');
        if (!is_array($terms) || empty($terms)) return false;
        foreach ($terms as $t) {
            if (!empty($t->slug) && $t->slug === $slug) return true;
        }
        return false;
    }

    /**
     * @return int[]
     */
    private static function cartProductIdsAndVariationIds(): array {
        if (!function_exists('WC') || !WC()->cart) return [];
        $out = [];
        foreach (WC()->cart->get_cart() as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $vid = (int) ($item['variation_id'] ?? 0);
            if ($pid) $out[] = $pid;
            if ($vid) $out[] = $vid;
        }
        return array_values(array_unique($out));
    }
}
