<?php
namespace Solas\Portal\CPD\Certificates;

defined('ABSPATH') || exit;

/**
 * Certificate branding/settings accessor.
 *
 * Storage: option 'solas_cpd_certificate_settings' (array)
 * Back-compat: attempts to read legacy option keys if the new option is empty.
 */
final class Branding
{
    public const OPTION_KEY = 'solas_cpd_certificate_settings';

    /**
     * Returns full settings array with defaults applied.
     *
     * @return array{
     *   logo_id:int,
     *   title:string,
     *   body:string,
     *   watermark_preview:int,
     *   watermark_text:string,
     *   signatories:array<int, array{image_id:int,name:string,role:string}>
     * }
     */
    public static function get(): array
    {
        $defaults = [
            'logo_id' => 0,
            'title' => 'Certificate of CPD Completion',
            'body'  => 'This certifies that {name} has completed Continuing Professional Development requirements for the {cycle} cycle.',
            'watermark_preview' => 1,
            'watermark_text' => 'PREVIEW',
            'signatories' => [
                ['image_id' => 0, 'name' => '', 'role' => ''],
                ['image_id' => 0, 'name' => '', 'role' => ''],
                ['image_id' => 0, 'name' => '', 'role' => ''],
                ['image_id' => 0, 'name' => '', 'role' => ''],
            ],
        ];

        $opt = get_option(self::OPTION_KEY);
        if (is_array($opt)) {
            $settings = self::sanitize($opt);
            $settings = array_merge($defaults, $settings);
            $settings['signatories'] = self::normalizeSignatories($settings['signatories'] ?? []);
            return $settings;
        }

        // Legacy fallback (logo/signatures only)
        $legacy = self::readLegacy();
        if ($legacy) {
            $settings = array_merge($defaults, $legacy);
            $settings['signatories'] = self::normalizeSignatories($settings['signatories'] ?? []);
            return self::sanitize($settings);
        }

        return $defaults;
    }

    public static function logoUrl(): string
    {
        $s = self::get();
        $id = absint($s['logo_id'] ?? 0);
        return $id ? (string) wp_get_attachment_url($id) : '';
    }

    /**
     * @return array<int, array{image_url:string,name:string,role:string}>
     */
    public static function signatories(): array
    {
        $sigs = self::get()['signatories'] ?? [];
        $out = [];
        foreach ($sigs as $sig) {
            $img_id = absint($sig['image_id'] ?? 0);
            $out[] = [
                'image_url' => $img_id ? (string) wp_get_attachment_url($img_id) : '',
                'name'      => (string) ($sig['name'] ?? ''),
                'role'      => (string) ($sig['role'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Sanitize persisted settings.
     */
    public static function sanitize($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $logo_id = absint($raw['logo_id'] ?? 0);
        $title   = isset($raw['title']) ? sanitize_text_field((string) $raw['title']) : '';
        $body    = isset($raw['body']) ? wp_kses_post((string) $raw['body']) : '';
        $watermark_preview = !empty($raw['watermark_preview']) ? 1 : 0;
        $watermark_text    = isset($raw['watermark_text']) ? sanitize_text_field((string) $raw['watermark_text']) : 'PREVIEW';

        $signatories = self::normalizeSignatories($raw['signatories'] ?? []);

        return [
            'logo_id' => $logo_id,
            'title' => $title,
            'body' => $body,
            'watermark_preview' => $watermark_preview,
            'watermark_text' => $watermark_text,
            'signatories' => $signatories,
        ];
    }

    /**
     * Ensure exactly 4 signatories with keys image_id/name/role.
     *
     * @param mixed $sigs
     * @return array<int, array{image_id:int,name:string,role:string}>
     */
    private static function normalizeSignatories($sigs): array
    {
        $out = [];
        if (is_array($sigs)) {
            foreach ($sigs as $sig) {
                if (!is_array($sig)) continue;
                $out[] = [
                    'image_id' => absint($sig['image_id'] ?? 0),
                    'name' => (string) ($sig['name'] ?? ''),
                    'role' => (string) ($sig['role'] ?? ''),
                ];
            }
        }
        while (count($out) < 4) {
            $out[] = ['image_id' => 0, 'name' => '', 'role' => ''];
        }
        return array_slice($out, 0, 4);
    }

    /**
     * Attempts to read legacy option keys. Best-effort.
     *
     * @return array|null
     */
    private static function readLegacy(): ?array
    {
        $logoCandidates = [
            'solas_cpd_certificate_logo_id',
            'solas_cpd_cert_logo_id',
            'solas_cpd_cert_logo_attachment_id',
            'solas_cpd_cert_logo',
        ];

        $logo_id = 0;
        foreach ($logoCandidates as $k) {
            $v = get_option($k);
            if ($v) {
                $logo_id = absint($v);
                if ($logo_id) break;
            }
        }

        if (!$logo_id) {
            foreach (['solas_cpd_certificate_logo_url', 'solas_cpd_cert_logo_url'] as $k) {
                $u = get_option($k);
                if (is_string($u) && $u) {
                    $id = attachment_url_to_postid($u);
                    if ($id) { $logo_id = (int) $id; break; }
                }
            }
        }

        $signatories = null;
        foreach (['solas_cpd_certificate_signatories', 'solas_cpd_cert_signatories', 'solas_cpd_cert_signatures'] as $k) {
            $v = get_option($k);
            if (is_array($v)) { $signatories = $v; break; }
        }

        if ($signatories === null) {
            $sigs = [];
            for ($i=1; $i<=4; $i++) {
                $id = 0;
                foreach ([
                    "solas_cpd_cert_sig_{$i}_id",
                    "solas_cpd_cert_signature_{$i}_id",
                    "solas_cpd_certificate_sig_{$i}_id",
                ] as $k) {
                    $v = get_option($k);
                    $id = absint($v);
                    if ($id) break;
                }

                if (!$id) {
                    foreach ([
                        "solas_cpd_cert_sig_{$i}_url",
                        "solas_cpd_cert_signature_{$i}_url",
                    ] as $k) {
                        $u = get_option($k);
                        if (is_string($u) && $u) {
                            $aid = attachment_url_to_postid($u);
                            if ($aid) { $id = (int) $aid; break; }
                        }
                    }
                }

                $name = '';
                foreach ([
                    "solas_cpd_cert_sig_{$i}_name",
                    "solas_cpd_cert_signature_{$i}_name",
                    "solas_cpd_certificate_sig_{$i}_name",
                ] as $k) {
                    $v = get_option($k);
                    if (is_string($v) && $v !== '') { $name = $v; break; }
                }

                $role = '';
                foreach ([
                    "solas_cpd_cert_sig_{$i}_role",
                    "solas_cpd_cert_signature_{$i}_role",
                    "solas_cpd_certificate_sig_{$i}_role",
                    "solas_cpd_cert_sig_{$i}_title",
                ] as $k) {
                    $v = get_option($k);
                    if (is_string($v) && $v !== '') { $role = $v; break; }
                }

                $sigs[] = ['image_id' => $id, 'name' => (string)$name, 'role' => (string)$role];
            }
            $signatories = $sigs;
        }

        $hasAny = $logo_id || (is_array($signatories) && array_filter($signatories, fn($s)=>absint($s['image_id']??0) || ($s['name']??'') || ($s['role']??'')));

        if (!$hasAny) return null;

        return [
            'logo_id' => $logo_id,
            'signatories' => $signatories,
        ];
    }
}
