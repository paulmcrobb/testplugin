<?php
declare(strict_types=1);

namespace Solas\Portal\Adverts;

use DateTimeImmutable;
use Throwable;

defined('ABSPATH') || exit;

final class Analytics {

    public static function clickUrl( $postId): string {
        return add_query_arg('solas_ad_click', (string) $postId, home_url('/'));
    }

    public static function increment( $postId, $metaKey): void {
        $postId = (int) $postId;
        if ($postId <= 0) return;
        $current = (int) get_post_meta($postId, $metaKey, true);
        update_post_meta($postId, $metaKey, $current + 1);
    }

    /**
     * Daily analytics stored in post meta 'solas_advert_daily' as:
     *  [ 'YYYY-MM-DD' => [ 'i' => impressions, 'c' => clicks ] ]
     */
    public static function incDaily( $postId, $type): void {
        // Prefer legacy analytics module if present (stores in solas_daily_impressions/clicks).
        if (function_exists("\\solas_adverts_analytics_inc_daily")) {
            \solas_adverts_analytics_inc_daily($postId, $type);
            return;
        }

        $postId = (int) $postId;
        if ($postId <= 0) return;

        $day = wp_date('Y-m-d');
        $data = get_post_meta($postId, 'solas_advert_daily', true);
        if (!is_array($data)) $data = [];
        if (!isset($data[$day]) || !is_array($data[$day])) {
            $data[$day] = ['i' => 0, 'c' => 0];
        } 

        if ($type === 'impression') {
            $data[$day]['i'] = (int) ($data[$day]['i'] ?? 0) + 1;
        } elseif ($type === 'click') {
            $data[$day]['c'] = (int) ($data[$day]['c'] ?? 0) + 1;
        } else {
            return;
        }

        // Keep 2 years.
        try {
            $cutoff = (new DateTimeImmutable(wp_date('Y-m-d')))->modify('-730 days')->format('Y-m-d');
            foreach ($data as $k => $v) {
                if (is_string($k) && $k < $cutoff) unset($data[$k]);
            }
        } catch (Throwable $t) {
            // ignore
        }

        update_post_meta($postId, 'solas_advert_daily', $data);
    }

    public static function handleClickRedirect(): void {
        if (empty($_GET['solas_ad_click'])) return;

        $id = (int) $_GET['solas_ad_click'];
        if ($id <= 0) {
            status_header(404);
            exit;
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== 'solas_advert' || $post->post_status !== 'publish') {
            status_header(404);
            exit;
        }

        $url = (string) get_post_meta($id, 'solas_destination_url', true);
        if ($url === '') {
            status_header(404);
            exit;
        }

        self::increment($id, 'solas_clicks');
        self::incDaily($id, 'click');

        wp_redirect(esc_url_raw($url), 302);
        exit;
    }

    public static function ajaxImpression(): void {
        $nonceAction = defined('SOLAS_ADVERTS_IMPRESSION_NONCE_ACTION')
            ? SOLAS_ADVERTS_IMPRESSION_NONCE_ACTION
            : 'solas_adverts_impression';

        $nonce = isset($_POST['nonce']) ? (string) wp_unslash($_POST['nonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, $nonceAction)) {
            wp_send_json_error(['message' => 'Bad nonce'], 403);
        }

        $id = isset($_POST['advert_id']) ? (int) $_POST['advert_id'] : 0;
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Invalid advert_id'], 400);
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== 'solas_advert' || $post->post_status !== 'publish') {
            wp_send_json_error(['message' => 'Advert not found'], 404);
        }

        self::increment($id, 'solas_impressions');
        self::incDaily($id, 'impression');

        wp_send_json_success(['ok' => true]);
    }

    public static function registerHooks(): void {
        add_action('template_redirect', [self::class, 'handleClickRedirect'], 1);
        add_action('wp_ajax_solas_advert_impression', [self::class, 'ajaxImpression']);
        add_action('wp_ajax_nopriv_solas_advert_impression', [self::class, 'ajaxImpression']);
    }
}
