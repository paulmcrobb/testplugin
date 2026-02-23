<?php
namespace Solas\Portal\CPD\Certificates;

defined('ABSPATH') || exit;

final class Renderer
{
    /**
     * Back-compat alias for older CertificateService implementations.
     */
    public static function renderHtml(array $data): string
    {
        return self::html($data);
    }

    /**
     * Build certificate HTML (print + PDF friendly).
     */
    public static function html(array $data): string
    {
        $settings = Branding::get();

        $logo_url = Branding::logoUrl();
        $signatories = Branding::signatories();

        $name = (string)($data['name'] ?? '');
        $cycle = (string)($data['cycle_label'] ?? '');
        $date = (string)($data['date'] ?? date_i18n('j F Y'));
        $preview = !empty($data['preview']);

        $structured = (float)($data['structured_hours'] ?? 0);
        $unstructured = (float)($data['unstructured_hours'] ?? 0);
        $solas = (float)($data['solas_structured_hours'] ?? 0);
        $total = (float)($data['total_hours'] ?? ($structured + $unstructured + $solas));

        $tokens = [
            '{name}' => $name,
            '{cycle}' => $cycle,
            '{date}' => $date,
            '{structured_hours}' => number_format_i18n($structured, 2),
            '{unstructured_hours}' => number_format_i18n($unstructured, 2),
            '{solas_structured_hours}' => number_format_i18n($solas, 2),
            '{total_hours}' => number_format_i18n($total, 2),
        ];

        $title_tpl = (string)($settings['title'] ?? 'Certificate of CPD Completion');
        $body_tpl  = (string)($settings['body'] ?? 'This certifies that {name} has completed Continuing Professional Development requirements for the {cycle} cycle.');

        $title = strtr($title_tpl, $tokens);
        $body  = strtr($body_tpl, $tokens);

        $css = '
            body { font-family: DejaVu Sans, Arial, sans-serif; color: #111; }
            .solas-cert { width: 100%; max-width: 900px; margin: 0 auto; padding: 36px 42px; border: 2px solid #111; }
            .solas-cert-header { display:flex; align-items:center; justify-content:space-between; gap: 20px; margin-bottom: 30px; }
            .solas-cert-logo img { max-height: 90px; width:auto; }
            .solas-cert-title { text-align:right; }
            .solas-cert-title h1 { margin:0; font-size: 28px; letter-spacing: 0.3px; }
            .solas-cert-title .cycle { margin-top: 6px; font-size: 14px; }
            .solas-cert-body { margin-top: 20px; font-size: 16px; line-height: 1.5; }
            .solas-cert-body p { margin: 0 0 10px; }
            .solas-cert-metrics { margin-top: 22px; font-size: 14px; }
            .solas-cert-metrics table { width: 100%; border-collapse: collapse; }
            .solas-cert-metrics td { padding: 6px 0; }
            .solas-cert-footer { margin-top: 46px; display: grid; grid-template-columns: 1fr 1fr; gap: 26px; }
            .sig { text-align: center; }
            .sig img { max-height: 60px; width:auto; display:block; margin: 0 auto 8px; }
            .sig .name { font-weight: 600; }
            .sig .role { font-size: 12px; color: #333; }
            .watermark { position: fixed; top: 45%; left: 0; right:0; text-align:center; font-size: 72px; opacity: 0.08; transform: rotate(-20deg); }
        ';

        $watermark = '';
        $wm_enabled = !empty($settings['watermark_preview']);
        if ($preview && $wm_enabled) {
            $wm_text = (string)($settings['watermark_text'] ?? 'PREVIEW');
            $watermark = '<div class="watermark">' . esc_html($wm_text ?: 'PREVIEW') . '</div>';
        }

        $logoHtml = $logo_url ? '<div class="solas-cert-logo"><img src="' . esc_url($logo_url) . '" alt="Logo" /></div>' : '<div></div>';

        $sigsHtml = '';
        foreach ($signatories as $sig) {
            $img = $sig['image_url'] ? '<img src="' . esc_url($sig['image_url']) . '" alt="Signature" />' : '';
            $nm = $sig['name'] ? esc_html($sig['name']) : '';
            $rl = $sig['role'] ? esc_html($sig['role']) : '';
            $sigsHtml .= '<div class="sig">' . $img . '<div class="name">' . $nm . '</div><div class="role">' . $rl . '</div></div>';
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>';
        $html .= $watermark;
        $html .= '<div class="solas-cert">';
        $html .= '<div class="solas-cert-header">' . $logoHtml;
        $html .= '<div class="solas-cert-title"><h1>' . esc_html($title) . '</h1><div class="cycle">' . esc_html($cycle) . '</div></div></div>';
        $html .= '<div class="solas-cert-body">' . wp_kses_post(wpautop($body)) . '</div>';

        $html .= '<div class="solas-cert-metrics"><table><tbody>';
        $html .= '<tr><td>Structured hours</td><td style="text-align:right;">' . esc_html(number_format_i18n($structured, 2)) . '</td></tr>';
        $html .= '<tr><td>Unstructured hours</td><td style="text-align:right;">' . esc_html(number_format_i18n($unstructured, 2)) . '</td></tr>';
        $html .= '<tr><td>SOLAS structured hours</td><td style="text-align:right;">' . esc_html(number_format_i18n($solas, 2)) . '</td></tr>';
        $html .= '<tr><td><strong>Total</strong></td><td style="text-align:right;"><strong>' . esc_html(number_format_i18n($total, 2)) . '</strong></td></tr>';
        $html .= '<tr><td>Date issued</td><td style="text-align:right;">' . esc_html($date) . '</td></tr>';
        $html .= '</tbody></table></div>';

        $html .= '<div class="solas-cert-footer">' . $sigsHtml . '</div>';
        $html .= '</div></body></html>';

        return $html;
    }
}
