<?php
declare(strict_types=1);

namespace Solas\Portal\Adverts;

defined('ABSPATH') || exit;

final class Assets {

    public static function enqueueFrontendAssets(): void {
        if (!defined('SOLAS_PORTAL_URL')) return;

        $handle = 'solas-adverts';
        if (!wp_style_is($handle, 'registered')) {
            wp_register_style(
                $handle,
                trailingslashit(SOLAS_PORTAL_URL) . 'assets/css/solas-adverts.css',
                [],
                defined('SOLAS_PORTAL_VERSION') ? SOLAS_PORTAL_VERSION : null
            );
        }
        wp_enqueue_style($handle);
    }

    /**
     * Enqueue adverts CSS + JS once and localise nonces.
     */
    public static function enqueueAssets(): void {
        static $done = false;
        if ($done) return;
        $done = true;

        if (!defined('SOLAS_PORTAL_URL')) return;

        wp_enqueue_style(
            'solas-adverts',
            SOLAS_PORTAL_URL . 'assets/css/solas-adverts.css',
            [],
            defined('SOLAS_PORTAL_VERSION') ? SOLAS_PORTAL_VERSION : null
        );

        wp_enqueue_script(
            'solas-adverts',
            SOLAS_PORTAL_URL . 'assets/js/solas-adverts.js',
            [],
            defined('SOLAS_PORTAL_VERSION') ? SOLAS_PORTAL_VERSION : null,
            true
        );

        $nonceAction = defined('SOLAS_ADVERTS_IMPRESSION_NONCE_ACTION')
            ? SOLAS_ADVERTS_IMPRESSION_NONCE_ACTION
            : 'solas_adverts_impression';

        wp_localize_script('solas-adverts', 'SolasAdverts', [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce($nonceAction),
            'defaultDays'  => defined('SOLAS_ADVERTS_DEFAULT_DAYS') ? (int) SOLAS_ADVERTS_DEFAULT_DAYS : 30,
        ]);

        // GF helper: auto-populate End Date based on Start Date + Days Required.
        wp_enqueue_script(
            'solas-adverts-form',
            SOLAS_PORTAL_URL . 'assets/js/solas-adverts-form.js',
            [],
            defined('SOLAS_PORTAL_VERSION') ? SOLAS_PORTAL_VERSION : null,
            true
        );

        wp_localize_script('solas-adverts-form', 'SolasAdvertsAvailability', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('solas_adverts_availability'),
            'horizon' => 365,
        ]);

        wp_localize_script('solas-adverts-form', 'SolasAdvertsForm', [
            'formId'      => defined('SOLAS_ADVERTS_FORM_ID') ? (int) SOLAS_ADVERTS_FORM_ID : 7,
            'startFieldId'=> defined('SOLAS_ADVERTS_FIELD_START') ? (int) SOLAS_ADVERTS_FIELD_START : 3,
            'endFieldId'  => defined('SOLAS_ADVERTS_FIELD_END') ? (int) SOLAS_ADVERTS_FIELD_END : 4,
            'daysFieldId' => defined('SOLAS_ADVERTS_FIELD_DAYS') ? (int) SOLAS_ADVERTS_FIELD_DAYS : 14,
            'slotFieldId' => defined('SOLAS_ADVERTS_FIELD_SLOT') ? (int) SOLAS_ADVERTS_FIELD_SLOT : 1,
            'defaultDays' => defined('SOLAS_ADVERTS_DEFAULT_DAYS') ? (int) SOLAS_ADVERTS_DEFAULT_DAYS : 30,
        ]);
    }
}
