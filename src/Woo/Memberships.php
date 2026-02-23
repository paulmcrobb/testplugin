<?php
declare(strict_types=1);

namespace Solas\Portal\Woo;

defined('ABSPATH') || exit;

/**
 * Thin compatibility layer around WooCommerce Memberships.
 */
final class Memberships {

    public static function isActive(): bool {
        return function_exists('wc_memberships')
            || function_exists('wc_memberships_get_membership_plans')
            || function_exists('wc_memberships_is_user_active_member')
            || class_exists('WC_Memberships');
    }

    /**
     * @return array<int,object>
     */
    public static function getPlans(): array {
        if (function_exists('wc_memberships_get_membership_plans')) {
            $plans = wc_memberships_get_membership_plans();
            return is_array($plans) ? $plans : [];
        }
        return [];
    }

    public static function getPlanById(int $planId): ?object {
        if ($planId <= 0) return null;
        if (function_exists('wc_memberships_get_membership_plan')) {
            $plan = wc_memberships_get_membership_plan($planId);
            return is_object($plan) ? $plan : null;
        }
        foreach (self::getPlans() as $plan) {
            if (is_object($plan) && method_exists($plan, 'get_id') && (int) $plan->get_id() === $planId) {
                return $plan;
            }
        }
        return null;
    }

    /**
     * $plan can be an ID, slug or name.
     */
    public static function isUserActiveMember($userId, $plan): bool {
        $userId = (int) $userId;
        if ($userId <= 0 || empty($plan) || !self::isActive()) return false;

        if (is_numeric($plan) && function_exists('wc_memberships_is_user_active_member')) {
            return (bool) wc_memberships_is_user_active_member($userId, (int) $plan);
        }

        $activePlans = self::getUserActivePlanIds($userId);
        if (!$activePlans) return false;

        foreach ($activePlans as $planId) {
            $p = self::getPlanById((int) $planId);
            if ($p && self::planMatches($p, $plan)) return true;
        }

        return false;
    }

    /**
     * @return int[] plan IDs
     */
    public static function getUserActivePlanIds(int $userId): array {
        if ($userId <= 0 || !self::isActive()) return [];

        if (function_exists('wc_memberships_get_user_memberships')) {
            $memberships = wc_memberships_get_user_memberships([
                'user_id' => $userId,
                'status'  => ['active'],
                'limit'   => -1,
            ]);
            if (is_array($memberships)) {
                $planIds = [];
                foreach ($memberships as $m) {
                    if (is_object($m) && method_exists($m, 'get_plan_id')) {
                        $planIds[] = (int) $m->get_plan_id();
                    }
                }
                return array_values(array_unique(array_filter($planIds)));
            }
        }

        if (function_exists('wc_memberships_is_user_active_member')) {
            $planIds = [];
            foreach (self::getPlans() as $plan) {
                if (!is_object($plan) || !method_exists($plan, 'get_id')) continue;
                $pid = (int) $plan->get_id();
                if ($pid > 0 && wc_memberships_is_user_active_member($userId, $pid)) {
                    $planIds[] = $pid;
                }
            }
            return array_values(array_unique($planIds));
        }

        return [];
    }

    /**
     * @return int[] purchase-linked product IDs (best-effort)
     */
    public static function getPlanPurchaseProductIds(int $planId): array {
        $plan = self::getPlanById($planId);
        if (!$plan) return [];

        foreach (['get_product_ids', 'get_products', 'get_product_id'] as $method) {
            if (method_exists($plan, $method)) {
                $val = $plan->{$method}();
                if (is_array($val)) {
                    return array_values(array_unique(array_map('intval', $val)));
                }
                if (is_numeric($val)) {
                    return [(int) $val];
                }
            }
        }

        return [];
    }

    public static function planMatches(object $plan, $needle): bool {
        if (is_numeric($needle) && method_exists($plan, 'get_id')) {
            return (int) $plan->get_id() === (int) $needle;
        }

        $needleStr = is_string($needle) ? strtolower(trim($needle)) : '';
        if ($needleStr === '') return false;

        if (method_exists($plan, 'get_slug')) {
            $slug = strtolower((string) $plan->get_slug());
            if ($slug !== '' && $slug === $needleStr) return true;
        }

        if (method_exists($plan, 'get_name')) {
            $name = strtolower((string) $plan->get_name());
            if ($name !== '' && $name === $needleStr) return true;
        }

        return false;
    }
}
