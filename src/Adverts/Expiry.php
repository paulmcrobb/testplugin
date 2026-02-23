<?php
declare(strict_types=1);

namespace Solas\Portal\Adverts;

use DateTimeImmutable;
use Throwable;
use WP_Post;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

/**
 * Hard expiry via Action Scheduler (preferred) or WP Cron fallback.
 */
final class Expiry {

    public const HOOK = 'solas_adverts_expire_advert';
    public const GROUP = 'solas-portal';

    public static function register(): void {
        add_action(self::HOOK, [self::class, 'handleExpireAction']);
        add_action('save_post_solas_advert', [self::class, 'handleSavePost'], 10, 3);
    }

    public static function schedule( $advertId): void {
        $advertId = (int) $advertId;
        if ($advertId <= 0) return;

        $endIso = (string) get_post_meta($advertId, 'solas_end_date', true);
        if ($endIso === '') return;

        try {
            $end = new DateTimeImmutable($endIso, wp_timezone());
        } catch (Throwable $t) {
            return;
        }

        $ts = $end->getTimestamp();
        if ($ts <= time()) return;

        $args = ['advert_id' => $advertId];

        // Clear any previously scheduled expiry for this advert.
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::HOOK, $args, self::GROUP);
        }

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action($ts + 1, self::HOOK, $args, self::GROUP);
            return;
        }

        // WP Cron fallback (best-effort)
        if (function_exists('wp_next_scheduled')) {
            $next = wp_next_scheduled(self::HOOK, [$advertId]);
            if ($next) {
                wp_unschedule_event($next, self::HOOK, [$advertId]);
            }
            wp_schedule_single_event($ts + 1, self::HOOK, [$advertId]);
        }
    }

    public static function run( $advertId): void {
        $advertId = (int) $advertId;
        if ($advertId <= 0) return;

        $post = get_post($advertId);
        if (!$post || $post->post_type !== 'solas_advert') return;

        // If already not published, still mark expired meta for clarity.
        update_post_meta($advertId, 'solas_advert_status', 'expired');

        if ($post->post_status === 'publish') {
            wp_update_post([
                'ID' => $advertId,
                'post_status' => 'draft',
            ]);
        }
    }

    /** @param mixed $argsOrId */
    public static function handleExpireAction($argsOrId = null): void {
        $advertId = 0;
        if (is_array($argsOrId) && isset($argsOrId['advert_id'])) {
            $advertId = (int) $argsOrId['advert_id'];
        } elseif (is_numeric($argsOrId)) {
            $advertId = (int) $argsOrId;
        }
        self::run($advertId);
    }

    public static function handleSavePost( $postId, WP_Post $post, $update): void {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) return;

        $status = (string) get_post_meta($postId, 'solas_advert_status', true);
        if ($post->post_status === 'publish' && $status === 'active') {
            self::schedule($postId);
        }
    }
}
