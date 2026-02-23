<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

/**
 * CPD Tools - Exports + Audit Pack UI (extracted from includes/cpd-admin-tools.php)
 * Stage 45: move heavy export/audit rendering behind a class boundary.
 */
final class Exports {

    public static function renderExportTab(string $cycle): void {
        // Nonce URLs are built exactly as before to preserve behaviour
        $nonce = wp_create_nonce('solas_cpd_export_' . $cycle);
        $url = admin_url('admin-post.php?action=solas_cpd_export_csv&cycle=' . urlencode($cycle) . '&_wpnonce=' . urlencode($nonce));

        $nonce2 = wp_create_nonce('solas_cpd_export_compliance_' . $cycle);
        $url2 = admin_url('admin-post.php?action=solas_cpd_export_compliance_csv&cycle=' . urlencode($cycle) . '&_wpnonce=' . urlencode($nonce2));

        $nonce3 = wp_create_nonce('solas_cpd_export_evidence_' . $cycle);
        $url3 = admin_url('admin-post.php?action=solas_cpd_export_evidence_csv&cycle=' . urlencode($cycle) . '&_wpnonce=' . urlencode($nonce3));

        $nonce4 = wp_create_nonce('solas_cpd_export_exceptions_' . $cycle);
        $url4 = admin_url('admin-post.php?action=solas_cpd_export_exceptions_csv&cycle=' . urlencode($cycle) . '&_wpnonce=' . urlencode($nonce4));

        echo '<h3>CSV Export</h3>';
        echo '<p>Export CPD records and summaries for the selected cycle.</p>';

        echo '<p>';
        echo '<a class="button button-primary" href="' . esc_url($url) . '">All CPD Records (CSV)</a> ';
        echo '<a class="button" href="' . esc_url($url2) . '">Member Compliance Summary (CSV)</a> ';
        echo '<a class="button" href="' . esc_url($url3) . '">Evidence Links (CSV)</a> ';
        echo '<a class="button" href="' . esc_url($url4) . '">Exceptions (CSV)</a>';
        echo '</p>';
    }

    public static function renderAuditTab(string $cycle): void {
        echo '<h3>Audit Pack</h3>';
        echo '<p>Generate a folder in uploads containing compliance, evidence, exceptions and a summary for the selected cycle.</p>';

        $nonce = wp_create_nonce('solas_cpd_generate_cycle_audit_pack_' . $cycle);
        $url = admin_url('admin-post.php?action=solas_cpd_generate_cycle_audit_pack&cycle=' . urlencode($cycle) . '&_wpnonce=' . urlencode($nonce));

        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Generate Cycle Audit Pack</a></p>';

        // If a pack id was generated, show links to it (handled in includes via query arg)
        if (isset($_GET['solas_audit_pack'])) {
            $packId = sanitize_text_field(wp_unslash($_GET['solas_audit_pack']));
            $uploads = wp_upload_dir();
            if (!empty($uploads['baseurl'])) {
                $base = trailingslashit($uploads['baseurl']) . 'solas-exports/cpd-audit-pack/' . rawurlencode($packId) . '/';
                echo '<p><strong>Latest pack:</strong> ' . esc_html($packId) . '</p>';
                echo '<ul>';
                echo '<li><a href="' . esc_url($base . 'compliance.csv') . '" target="_blank" rel="noopener">compliance.csv</a></li>';
                echo '<li><a href="' . esc_url($base . 'evidence.csv') . '" target="_blank" rel="noopener">evidence.csv</a></li>';
                echo '<li><a href="' . esc_url($base . 'exceptions.csv') . '" target="_blank" rel="noopener">exceptions.csv</a></li>';
                echo '<li><a href="' . esc_url($base . 'summary.txt') . '" target="_blank" rel="noopener">summary.txt</a></li>';
                echo '<li><a href="' . esc_url($base . 'summary.json') . '" target="_blank" rel="noopener">summary.json</a></li>';
                echo '</ul>';
            }
        }
    }
}
