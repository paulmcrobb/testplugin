<?php
namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

use Solas\Portal\CPD\Certificates\Branding;

/**
 * CPD Tools: Certificates tab renderer (class-based).
 * Includes certificate branding settings (logo + 4 signatures).
 */
final class Certificates
{
    public static function render($cycle): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to access this page.', 'solas-portal'));
        }

        // Ensure media modal is available for logo/signature pickers
        wp_enqueue_media();

        // Normalise cycle: accept label "2025/26" or int start year
        $cycle_label = is_string($cycle) ? (string) $cycle : '';
        $cycle_start_year = absint($cycle);

        if ($cycle_start_year === 0 && preg_match('/^(\d{4})\s*\//', $cycle_label, $m)) {
            $cycle_start_year = absint($m[1]);
        }
        if ($cycle_start_year === 0 && class_exists('Solas\\Portal\\CPD\\Cycle')) {
            $cycle_start_year = (int) \Solas\Portal\CPD\Cycle::currentStartYear();
        }
        if ($cycle_label === '' && $cycle_start_year) {
            $cycle_label = sprintf('%d/%02d', $cycle_start_year, ($cycle_start_year + 1) % 100);
        }

        // Handle save settings POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solas_cpd_cert_settings_nonce'])) {
            if (wp_verify_nonce((string) $_POST['solas_cpd_cert_settings_nonce'], 'solas_cpd_cert_settings_save')) {
                $raw = [
                    'logo_id' => isset($_POST['solas_cpd_cert_logo_id']) ? absint($_POST['solas_cpd_cert_logo_id']) : 0,
                    'title' => isset($_POST['solas_cpd_cert_title']) ? sanitize_text_field((string) $_POST['solas_cpd_cert_title']) : '',
                    'body' => isset($_POST['solas_cpd_cert_body']) ? (string) wp_kses_post($_POST['solas_cpd_cert_body']) : '',
                    'watermark_preview' => !empty($_POST['solas_cpd_cert_watermark_preview']) ? 1 : 0,
                    'watermark_text' => isset($_POST['solas_cpd_cert_watermark_text']) ? sanitize_text_field((string) $_POST['solas_cpd_cert_watermark_text']) : 'PREVIEW',
                    'signatories' => [],
                ];
                for ($i=1; $i<=4; $i++) {
                    $raw['signatories'][] = [
                        'image_id' => isset($_POST["solas_cpd_cert_sig_{$i}_id"]) ? absint($_POST["solas_cpd_cert_sig_{$i}_id"]) : 0,
                        'name'     => isset($_POST["solas_cpd_cert_sig_{$i}_name"]) ? sanitize_text_field((string) $_POST["solas_cpd_cert_sig_{$i}_name"]) : '',
                        'role'     => isset($_POST["solas_cpd_cert_sig_{$i}_role"]) ? sanitize_text_field((string) $_POST["solas_cpd_cert_sig_{$i}_role"]) : '',
                    ];
                }
                update_option(Branding::OPTION_KEY, Branding::sanitize($raw));
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Certificate settings saved.', 'solas-portal') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed. Please try again.', 'solas-portal') . '</p></div>';
            }
        }

        $settings = Branding::get();

        $selected_user = isset($_GET['cert_user']) ? absint($_GET['cert_user']) : 0;

        echo '<div class="wrap">';
        echo '<h2>' . esc_html__('Certificates', 'solas-portal') . '</h2>';
        echo '<p>' . esc_html__('Generate preview/download links for a member certificate for the selected cycle.', 'solas-portal') . '</p>';

        // Link generator form (GET)
        echo '<form method="get" action="">';
        foreach (['post_type','page','tab','cycle'] as $k) {
            if (isset($_GET[$k])) {
                printf('<input type="hidden" name="%s" value="%s" />', esc_attr($k), esc_attr((string) $_GET[$k]));
            }
        }

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row"><label for="cert_user">' . esc_html__('Member', 'solas-portal') . '</label></th>';
        echo '<td>';
        wp_dropdown_users([
            'name'              => 'cert_user',
            'id'                => 'cert_user',
            'show_option_none'  => esc_html__('Select a member…', 'solas-portal'),
            'selected'          => $selected_user ?: 0,
            'orderby'           => 'display_name',
            'order'             => 'ASC',
        ]);
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Cycle', 'solas-portal') . '</th>';
        echo '<td><code>' . esc_html($cycle_label ?: (string) $cycle_start_year) . '</code></td>';
        echo '</tr>';

        echo '</tbody></table>';

        submit_button(esc_html__('Load certificate links', 'solas-portal'), 'secondary');
        echo '</form>';

        if ($selected_user) {
            $base = admin_url('admin-post.php');
            $args_preview = [
                'action'  => 'solas_cpd_certificate_preview',
                'user_id' => $selected_user,
                'cycle'   => $cycle_label,
                'preview' => 1,
            ];
            $args_pdf = $args_preview + ['pdf' => 1];

            $preview_url = wp_nonce_url(add_query_arg($args_preview, $base), 'solas_cpd_certificate_preview');
            $pdf_url     = wp_nonce_url(add_query_arg($args_pdf, $base), 'solas_cpd_certificate_preview');echo '<p>';
            echo '<a class="button button-secondary" target="_blank" href="' . esc_url($preview_url) . '">' . esc_html__('Preview (HTML)', 'solas-portal') . '</a> ';
            echo '<a class="button button-primary" target="_blank" href="' . esc_url($pdf_url) . '">' . esc_html__('Download (PDF if available)', 'solas-portal') . '</a>';
            echo '</p>';
        }

        echo '<hr />';
        echo '<h3>' . esc_html__('Certificate branding', 'solas-portal') . '</h3>';
        echo '<p>' . esc_html__('Set a logo and up to 4 signatories (signature image, name and role).', 'solas-portal') . '</p>';

        // Settings form (POST)
        echo '<form method="post" action="">';
        wp_nonce_field('solas_cpd_cert_settings_save', 'solas_cpd_cert_settings_nonce');

        $logo_id = absint($settings['logo_id'] ?? 0);
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Logo', 'solas-portal') . '</th><td>';
        echo '<input type="hidden" id="solas_cpd_cert_logo_id" name="solas_cpd_cert_logo_id" value="' . esc_attr($logo_id) . '"/>';
        echo '<div id="solas_cpd_cert_logo_preview" style="margin-bottom:10px;">';
        if ($logo_url) {
            echo '<img src="' . esc_url($logo_url) . '" style="max-width:220px;height:auto;border:1px solid #ddd;padding:6px;background:#fff;" />';
        }
        echo '</div>';
        echo '<button type="button" class="button" id="solas_cpd_cert_logo_pick">' . esc_html__('Select logo', 'solas-portal') . '</button> ';
        echo '<button type="button" class="button" id="solas_cpd_cert_logo_clear">' . esc_html__('Clear', 'solas-portal') . '</button>';
        echo '</td></tr>';

        $title = (string) ($settings['title'] ?? 'Certificate of CPD Completion');
        $body  = (string) ($settings['body'] ?? 'This certifies that {name} has completed Continuing Professional Development requirements for the {cycle} cycle.');
        $wm_enabled = !empty($settings['watermark_preview']);
        $wm_text    = (string) ($settings['watermark_text'] ?? 'PREVIEW');

        echo '<tr><th scope="row">' . esc_html__('Certificate title', 'solas-portal') . '</th><td>';
        echo '<input type="text" class="regular-text" name="solas_cpd_cert_title" value="' . esc_attr($title) . '" />';
        echo '<p class="description">' . esc_html__('You can use tokens like {name} and {cycle}.', 'solas-portal') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Certificate body text', 'solas-portal') . '</th><td>';
        echo '<textarea name="solas_cpd_cert_body" rows="5" class="large-text code">' . esc_textarea($body) . '</textarea>';
        echo '<p class="description">' . esc_html__('Available tokens: {name}, {cycle}, {date}, {structured_hours}, {unstructured_hours}, {solas_structured_hours}, {total_hours}.', 'solas-portal') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Preview watermark', 'solas-portal') . '</th><td>';
        echo '<label><input type="checkbox" name="solas_cpd_cert_watermark_preview" value="1" ' . checked($wm_enabled, true, false) . ' /> ' . esc_html__('Show watermark on preview links only', 'solas-portal') . '</label>';
        echo '<div style="margin-top:8px;max-width:520px;">';
        echo '<label style="display:block;margin:6px 0 2px;">' . esc_html__('Watermark text', 'solas-portal') . '</label>';
        echo '<input type="text" class="regular-text" name="solas_cpd_cert_watermark_text" value="' . esc_attr($wm_text) . '" />';
        echo '</div>';
        echo '</td></tr>';


        $sigs = is_array($settings['signatories'] ?? null) ? $settings['signatories'] : [];

        for ($i=1; $i<=4; $i++) {
            $sig = $sigs[$i-1] ?? ['image_id'=>0,'name'=>'','role'=>''];
            $sig_id = absint($sig['image_id'] ?? 0);
            $sig_url = $sig_id ? wp_get_attachment_image_url($sig_id, 'medium') : '';
            $sig_name = (string)($sig['name'] ?? '');
            $sig_role = (string)($sig['role'] ?? '');

            echo '<tr><th scope="row">' . sprintf(esc_html__('Signature %d', 'solas-portal'), $i) . '</th><td>';
            echo '<input type="hidden" id="solas_cpd_cert_sig_' . $i . '_id" name="solas_cpd_cert_sig_' . $i . '_id" value="' . esc_attr($sig_id) . '"/>';
            echo '<div id="solas_cpd_cert_sig_' . $i . '_preview" style="margin-bottom:10px;">';
            if ($sig_url) {
                echo '<img src="' . esc_url($sig_url) . '" style="max-width:220px;height:auto;border:1px solid #ddd;padding:6px;background:#fff;" />';
            }
            echo '</div>';
            echo '<button type="button" class="button solas_cpd_cert_sig_pick" data-sig="' . esc_attr($i) . '">' . esc_html__('Select signature image', 'solas-portal') . '</button> ';
            echo '<button type="button" class="button solas_cpd_cert_sig_clear" data-sig="' . esc_attr($i) . '">' . esc_html__('Clear', 'solas-portal') . '</button>';
            echo '<div style="margin-top:10px;max-width:520px;">';
            echo '<label style="display:block;margin:6px 0 2px;">' . esc_html__('Name', 'solas-portal') . '</label>';
            echo '<input type="text" class="regular-text" name="solas_cpd_cert_sig_' . $i . '_name" value="' . esc_attr($sig_name) . '" />';
            echo '<label style="display:block;margin:10px 0 2px;">' . esc_html__('Role', 'solas-portal') . '</label>';
            echo '<input type="text" class="regular-text" name="solas_cpd_cert_sig_' . $i . '_role" value="' . esc_attr($sig_role) . '" />';
            echo '</div>';
            echo '</td></tr>';
        }

        echo '</tbody></table>';

        submit_button(esc_html__('Save certificate settings', 'solas-portal'));
        echo '</form>';

        // Inline JS for media pickers (admin only; small and self-contained)
        echo '<script>
        (function(){
            function pickImage(onSelect){
                var frame = wp.media({ title: "Select image", button: { text: "Use this image" }, multiple: false });
                frame.on("select", function(){
                    var att = frame.state().get("selection").first().toJSON();
                    onSelect(att);
                });
                frame.open();
            }

            var logoPick = document.getElementById("solas_cpd_cert_logo_pick");
            var logoClear = document.getElementById("solas_cpd_cert_logo_clear");
            var logoId = document.getElementById("solas_cpd_cert_logo_id");
            var logoPrev = document.getElementById("solas_cpd_cert_logo_preview");

            if (logoPick) {
                logoPick.addEventListener("click", function(){
                    pickImage(function(att){
                        logoId.value = att.id;
                        logoPrev.innerHTML = "<img src=\"" + att.url + "\" style=\"max-width:220px;height:auto;border:1px solid #ddd;padding:6px;background:#fff;\" />";
                    });
                });
            }
            if (logoClear) {
                logoClear.addEventListener("click", function(){
                    logoId.value = "";
                    logoPrev.innerHTML = "";
                });
            }

            document.querySelectorAll(".solas_cpd_cert_sig_pick").forEach(function(btn){
                btn.addEventListener("click", function(){
                    var i = btn.getAttribute("data-sig");
                    pickImage(function(att){
                        var idEl = document.getElementById("solas_cpd_cert_sig_" + i + "_id");
                        var prevEl = document.getElementById("solas_cpd_cert_sig_" + i + "_preview");
                        if (idEl) idEl.value = att.id;
                        if (prevEl) prevEl.innerHTML = "<img src=\"" + att.url + "\" style=\"max-width:220px;height:auto;border:1px solid #ddd;padding:6px;background:#fff;\" />";
                    });
                });
            });

            document.querySelectorAll(".solas_cpd_cert_sig_clear").forEach(function(btn){
                btn.addEventListener("click", function(){
                    var i = btn.getAttribute("data-sig");
                    var idEl = document.getElementById("solas_cpd_cert_sig_" + i + "_id");
                    var prevEl = document.getElementById("solas_cpd_cert_sig_" + i + "_preview");
                    if (idEl) idEl.value = "";
                    if (prevEl) prevEl.innerHTML = "";
                });
            });
        })();
        </script>';

        echo '</div>';
    }
}
