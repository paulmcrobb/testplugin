<?php
declare(strict_types=1);

namespace Solas\Portal\Adverts;

use DateTimeImmutable;
use Throwable;
use WP_Query;
use Solas\Portal\Woo\Meta;

defined('ABSPATH') || exit;

final class Shortcodes {

    public static function getActiveIds(string $slot, int $limit = 4): array {
        $slot = trim($slot);
        $limit = max(1, (int) $limit);

        $q = new WP_Query([
            'post_type'      => 'solas_advert',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => 50,
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'solas_slot', 'value' => $slot],
                [
                    'relation' => 'OR',
                    ['key' => 'solas_advert_status', 'value' => 'active'],
                    ['key' => 'solas_status', 'value' => 'active'],
                ],
            ],
        ]);

        $now = new DateTimeImmutable('now', wp_timezone());
        $out = [];

        foreach ($q->posts as $id) {
            $id = (int) $id;

            $s = (string) get_post_meta($id, 'solas_start_date', true);
            $e = (string) get_post_meta($id, 'solas_end_date', true);

            if ($s === '' && $e === '') {
                $s = (string) get_post_meta($id, 'solas_start', true);
                $e = (string) get_post_meta($id, 'solas_end', true);
            }

            if ($s === '' || $e === '') continue;

            try {
                $start = new DateTimeImmutable($s, wp_timezone());
                $end   = new DateTimeImmutable($e, wp_timezone());
            } catch (Throwable $t) {
                continue;
            }

            if ($start <= $now && $end >= $now) {
                $out[] = $id;
                if (count($out) >= $limit) break;
            }
        }

        return $out;
    }

    public static function getPlaceholder(string $slot): array {
        $slot = sanitize_key($slot);

        if ($slot === 'header' || $slot === 'header_banner') {
            $slot = (string) get_option('solas_adverts_placeholder_header_default_slot', 'header_leaderboard');
            $slot = sanitize_key($slot);
        }

        $att = (int) get_option('solas_adverts_placeholder_' . $slot . '_attachment_id', 0);
        $url = (string) get_option('solas_adverts_placeholder_' . $slot . '_url', '');
        $alt = (string) get_option('solas_adverts_placeholder_' . $slot . '_alt', '');
        $hed = (string) get_option('solas_adverts_placeholder_' . $slot . '_heading', '');

        if ($att <= 0 && $url === '' && $hed === '') {
            return [];
        }

        return [
            'attachment_id' => $att,
            'url' => $url,
            'alt' => $alt,
            'heading' => $hed,
            'slot' => $slot,
        ];
    }

    /**
     * Return up to 4 MPU placeholders (footer_mpu_1..4); falls back to legacy footer_mpu.
     */
    public static function getMpuPlaceholders(): array {
        $out = [];
        for ($i = 1; $i <= 4; $i++) {
            $slot = 'footer_mpu_' . $i;
            $ph = self::getPlaceholder($slot);
            if (!empty($ph)) $out[] = $ph;
        }
        if (empty($out)) {
            $legacy = self::getPlaceholder('footer_mpu');
            if (!empty($legacy)) $out[] = $legacy;
        }
        return $out;
    }

    public static function renderPlaceholder(array $ph, string $context): string {
        $att = (int) ($ph['attachment_id'] ?? 0);
        $url = (string) ($ph['url'] ?? '');
        $alt = (string) ($ph['alt'] ?? '');
        $hed = (string) ($ph['heading'] ?? '');

        $img = '';
        if ($att > 0) {
            $sizes = ($context === 'banner') ? '(max-width: 980px) 100vw, 980px' : '250px';
            $attrs = [
                'alt' => $alt,
                'decoding' => 'async',
                'loading' => ($context === 'banner') ? 'eager' : 'lazy',
            ];
            if ($context === 'banner') {
                $attrs['fetchpriority'] = 'high';
            }
            $img = wp_get_attachment_image($att, 'full', false, $attrs + ['sizes' => $sizes]);
        }

        if ($img === '' && !empty($ph['image_url'])) {
            $img = '<img src="' . esc_url((string)$ph['image_url']) . '" alt="' . esc_attr($alt) . '" loading="' . (($context === 'banner') ? 'eager' : 'lazy') . '" decoding="async" />';
        }

        $body = $img ?: '<div class="solas-adverts-fallback">' . esc_html($hed ?: 'Advert') . '</div>';

        if ($url !== '') {
            return '<a class="solas-adverts-item solas-adverts-item--placeholder" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . $body . '</a>';
        }
        return '<div class="solas-adverts-item solas-adverts-item--placeholder">' . $body . '</div>';
    }

    public static function renderItem(int $postId, string $variant = 'banner'): string {
        $url  = (string) get_post_meta($postId, 'solas_destination_url', true);
        $alt  = (string) get_post_meta($postId, 'solas_image_alt', true);
        $img  = (string) get_post_meta($postId, 'solas_image_url', true);
        $att  = (int) get_post_meta($postId, 'solas_image_attachment_id', true);
        $head = (string) get_post_meta($postId, 'solas_heading', true);

        if ($url === '') return '';

        $variant = $variant === 'mpu' ? 'mpu' : 'banner';

        $imgHtml = '';
        $computedAlt = $alt ?: wp_strip_all_tags($head);

        $isBanner = ($variant === 'banner');
        $loading = $isBanner ? 'eager' : 'lazy';
        $fetchpriority = $isBanner ? 'high' : 'auto';

        if ($att > 0) {
            $sizes = ($variant === 'mpu')
                ? '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 320px'
                : '(max-width: 640px) 100vw, 1200px';

            $imgHtml = wp_get_attachment_image(
                $att,
                'large',
                false,
                [
                    'class' => 'solas-advert__img',
                    'loading' => $loading,
                    'decoding' => 'async',
                    'fetchpriority' => $fetchpriority,
                    'sizes' => $sizes,
                    'alt' => $computedAlt,
                ]
            );
        } elseif ($img !== '') {
            $imgHtml = '<img class="solas-advert__img" loading="' . esc_attr($loading) . '" decoding="async" fetchpriority="' . esc_attr($fetchpriority) . '" src="' . esc_url($img) . '" alt="' . esc_attr($computedAlt) . '">';
        }

        if ($imgHtml === '') return '';

        $html  = '<div class="solas-advert solas-advert--' . esc_attr($variant) . '" data-solas-advert-id="' . (int) $postId . '">';
        $html .= '<a class="solas-advert__link" href="' . esc_url(Analytics::clickUrl($postId)) . '" target="_blank" rel="noopener nofollow">';
        $html .= $imgHtml;
        $html .= '</a>';
        if ($head !== '' && $variant === 'mpu') {
            $html .= '<div class="solas-advert__heading">' . esc_html($head) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    public static function shortcodeBanner($atts): string {
        $atts = shortcode_atts([
            'slot'  => 'header_leaderboard',
            'count' => 1,
            'class' => '',
        ], $atts, 'solas_adverts_banner');

        Assets::enqueueAssets();

        $slot  = sanitize_key((string) $atts['slot']);
        $count = max(1, (int) $atts['count']);

        if ($slot === 'header' || $slot === 'header_banner') {
            $slot = sanitize_key((string) get_option('solas_adverts_placeholder_header_default_slot', 'header_leaderboard'));
        }

        $ids = self::getActiveIds($slot, $count);

        if (empty($ids)) {
            $ph = self::getPlaceholder($slot);
            if (!empty($ph)) {
                return '<div class="' . esc_attr(trim('solas-adverts-banner ' . (string) $atts['class'])) . '">' . self::renderPlaceholder($ph, 'banner') . '</div>';
            }
            if (current_user_can('manage_options')) {
                return '<!-- SOLAS adverts: no active adverts found for slot ' . esc_html($slot) . ' -->';
            }
            return '';
        }

        Assets::enqueueFrontendAssets();

        $class = trim('solas-adverts-banner ' . (string) $atts['class']);
        $out = '<div class="' . esc_attr($class) . '">';
        foreach ($ids as $id) {
            $out .= self::renderItem((int) $id, 'banner');
        }
        $out .= '</div>';
        return $out;
    }

    public static function shortcodeMpus($atts): string {
        $atts = shortcode_atts([
            'slot'  => 'footer_mpu',
            'count' => 4,
            'class' => '',
        ], $atts, 'solas_adverts_mpus');

        Assets::enqueueAssets();

        $slot  = (string) $atts['slot'];
        $count = max(1, (int) $atts['count']);

        $ids = self::getActiveIds($slot, $count);
        $placeholders = self::getMpuPlaceholders();
        $ph = !empty($placeholders) ? $placeholders[0] : [];
        if (empty($ids) && empty($ph)) return '';

        Assets::enqueueFrontendAssets();

        $class = trim('solas-adverts-mpu-grid ' . (string) $atts['class']);
        if (defined('SOLAS_PORTAL_URL') && defined('SOLAS_PORTAL_VERSION')) {
            wp_enqueue_style('solas-adverts-mpus', SOLAS_PORTAL_URL . 'assets/css/solas-adverts-mpus.css', [], SOLAS_PORTAL_VERSION);
        }

        $out = '<div class="' . esc_attr($class) . '">';
        foreach ($ids as $id) {
            $out .= self::renderItem((int) $id, 'mpu');
        }
        $remaining = $count - count($ids);
        if ($remaining > 0 && !empty($placeholders)) {
            $max = min($remaining, count($placeholders));
            for ($i = 0; $i < $max; $i++) {
                $out .= self::renderPlaceholder($placeholders[$i], 'mpu');
            }
        }
        $out .= '</div>';
        return $out;
    }

    public static function registerShortcodes(): void {
        add_shortcode('solas_adverts_banner', [self::class, 'shortcodeBanner']);
        add_shortcode('solas_adverts_mpus', [self::class, 'shortcodeMpus']);
    }
}
