<?php
declare(strict_types=1);

namespace Solas\Portal\Profile;

defined('ABSPATH') || exit;

final class Avatar {

    public static function resolveUserId(): int {
        if (is_user_logged_in()) {
            return (int) get_current_user_id();
        }
        return 0;
    }

    public static function isMyProfileEndpoint(): bool {
        if (!function_exists('is_account_page') || !is_account_page()) { return false; }
        global $wp;
        $endpoint = $wp->query_vars ? array_key_first(array_filter($wp->query_vars)) : '';
        return $endpoint === 'my-profile' || (isset($wp->request) && str_contains((string)$wp->request, 'my-profile'));
    }

    public static function supportsHeic(): bool {
        if (!extension_loaded('imagick')) { return false; }
        try {
            $formats = \Imagick::queryFormats();
            $formats = array_map('strtoupper', $formats);
            return in_array('HEIC', $formats, true) || in_array('HEIF', $formats, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Sanitise SVG and overwrite file. Returns true if ok.
     */
    public static function sanitizeSvgFile(string $filePath): bool {
        if (!is_readable($filePath)) { return false; }
        $raw = file_get_contents($filePath);
        if ($raw === false) { return false; }

        // Very strict sanitiser: allow only safe tags/attrs (mirrors existing implementation).
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        libxml_use_internal_errors(true);
        $ok = $dom->loadXML($raw, LIBXML_NONET | LIBXML_NOENT | LIBXML_COMPACT);
        libxml_clear_errors();
        if (!$ok) { return false; }

        $allowedTags = [
            'svg','g','path','rect','circle','ellipse','line','polyline','polygon',
            'defs','linearGradient','radialGradient','stop','clipPath','mask','title','desc'
        ];
        $allowedAttrs = [
            'xmlns','viewBox','width','height','x','y','cx','cy','r','rx','ry','d','fill','stroke','stroke-width',
            'opacity','transform','points','x1','x2','y1','y2','id','class','clip-path','mask','gradientUnits',
            'gradientTransform','offset','stop-color','stop-opacity','preserveAspectRatio'
        ];

        $xpath = new \DOMXPath($dom);

        // Remove forbidden elements
        foreach ($xpath->query('//*') as $node) {
            $tag = $node->nodeName;
            if (!in_array($tag, $allowedTags, true)) {
                $node->parentNode?->removeChild($node);
                continue;
            }

            // strip attributes
            if ($node->hasAttributes()) {
                $toRemove = [];
                foreach ($node->attributes as $attr) {
                    $name = $attr->nodeName;
                    $val  = (string)$attr->nodeValue;

                    $lname = strtolower($name);
                    if (str_starts_with($lname, 'on')) { $toRemove[] = $name; continue; } // onload etc

                    if (!in_array($name, $allowedAttrs, true)) { $toRemove[] = $name; continue; }

                    // disallow any external refs / scripts / url()
                    if (preg_match('/url\s*\(/i', $val)) { $toRemove[] = $name; continue; }
                    if (preg_match('/javascript:/i', $val)) { $toRemove[] = $name; continue; }
                }
                foreach ($toRemove as $rm) {
                    $node->removeAttribute($rm);
                }
            }
        }

        // Remove potentially dangerous nodes explicitly
        foreach (['script','foreignObject','style'] as $bad) {
            foreach ($xpath->query('//' . $bad) as $n) {
                $n->parentNode?->removeChild($n);
            }
        }

        $out = $dom->saveXML($dom->documentElement);
        if (!$out) { return false; }
        return (bool) file_put_contents($filePath, $out);
    }

    public static function deleteUploadByUrl(string $url): void {
        if (!$url) { return; }
        $uploads = wp_upload_dir();
        if (!empty($uploads['baseurl']) && str_starts_with($url, $uploads['baseurl'])) {
            $rel = ltrim(str_replace($uploads['baseurl'], '', $url), '/');
            $path = trailingslashit($uploads['basedir']) . $rel;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    // Error storage (per request)
    private static ?string $lastError = null;

    public static function setLastError(string $msg): void { self::$lastError = $msg; }
    public static function getLastError(): ?string { return self::$lastError; }
    public static function clearLastError(): void { self::$lastError = null; }

    public static function gf11ResetUploadedFiles($form = 11): void {
        $formId = is_array($form) && isset($form['id']) ? (int) $form['id'] : (int) $form;
        if ($formId <= 0) { $formId = 11; }
                if (!class_exists('\\GFFormsModel')) {
            return;
        }
if (!class_exists('\GFFormsModel')) { return; }
        if (!isset(\GFFormsModel::$uploaded_files[$formId])) { return; }
        \GFFormsModel::$uploaded_files[$formId] = [];
    }

    public static function attachUrlToMediaLibrary(string $url, int $userId = 0): int {
        if (!$url) { return 0; }
        $existing = attachment_url_to_postid($url);
        if ($existing) { return (int)$existing; }

        $uploads = wp_upload_dir();
        if (empty($uploads['baseurl']) || empty($uploads['basedir'])) { return 0; }
        if (!str_starts_with($url, $uploads['baseurl'])) { return 0; }

        $rel = ltrim(str_replace($uploads['baseurl'], '', $url), '/');
        $filePath = trailingslashit($uploads['basedir']) . $rel;
        if (!file_exists($filePath)) { return 0; }

        $filetype = wp_check_filetype(basename($filePath), null);
        $attachment = [
            'guid'           => $url,
            'post_mime_type' => $filetype['type'] ?? '',
            'post_title'     => sanitize_file_name(pathinfo($filePath, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachId = wp_insert_attachment($attachment, $filePath, 0);
        if (is_wp_error($attachId) || !$attachId) { return 0; }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $meta = wp_generate_attachment_metadata($attachId, $filePath);
        wp_update_attachment_metadata($attachId, $meta);

        if ($userId) {
            update_user_meta($userId, 'solas_avatar_attachment_id', (int)$attachId);
        }

        return (int)$attachId;
    }
}