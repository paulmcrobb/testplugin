<?php
declare(strict_types=1);

namespace Solas\Portal\Events;

defined('ABSPATH') || exit;

final class Gate {

    public static function allowedMemberRoles(): array {
        return ['full-member', 'associate-member', 'honorary-member', 'student-member'];
    }

    public static function userHasAllowedRole(int $userId): bool {
        $user = get_user_by('id', $userId);
        if (!$user) return false;
        foreach (self::allowedMemberRoles() as $role) {
            if (in_array($role, (array) $user->roles, true)) return true;
        }
        return false;
    }

    /**
     * True if user has ANY active WooCommerce Memberships membership.
     * Fail-closed if the plugin is not active.
     */
    public static function userHasActiveWcMembership(int $userId): bool {
        if (!function_exists('wc_memberships_get_user_active_memberships')) return false;
        $memberships = wc_memberships_get_user_active_memberships($userId);
        return !empty($memberships);
    }
}
