<?php
/**
 * Plugin Name:       SOLAS Portal Includes
 * Description:       SOLAS portal: adverts, jobs, CPD, Woo customisations.
 * Version: 0.1.0
 * Author:            SOLAS
 */

defined('ABSPATH') || exit;

define('SOLAS_PORTAL_FILE', __FILE__);
define('SOLAS_PORTAL_PATH', plugin_dir_path(__FILE__));
define('SOLAS_PORTAL_URL', plugin_dir_url(__FILE__));
define('SOLAS_PORTAL_VERSION', '6.10.7');

// PSR-4 style autoloader for namespaced classes (dependency-free)
require_once SOLAS_PORTAL_PATH . 'src/autoloader.php';

// Load simplified “one file per area”
$inc = SOLAS_PORTAL_PATH . 'includes/';

// CPD core
require_once $inc . 'shortcode-sanitizer.php';
require_once $inc . 'cpd-cpt.php';
require_once $inc . 'cpd-counting.php';
require_once $inc . 'cpd-cycle-lock.php';
require_once $inc . 'cpd-cache.php';
require_once $inc . 'cpd-membership.php';
require_once $inc . 'cpd-admin-lock-ui.php';
require_once $inc . 'cpd-audit.php';
require_once $inc . 'cpd-corrections.php';
require_once $inc . 'cpd-certificate.php';
require_once $inc . 'cpd-teams-import.php';
require_once $inc . 'cpd-submissions.php';
require_once $inc . 'cpd-gf-validation.php';
require_once $inc . 'cpd-dashboard.php';
require_once $inc . 'cpd-learndash.php';
require_once $inc . 'cpd-learndash-admin.php';
require_once $inc . 'cpd-admin-tools.php';
require_once $inc . 'cpd-reminders.php';
require_once $inc . 'cpd-myaccount.php';
require_once $inc . 'cpd-my-certificates.php';

// Woo glue + other modules
require_once $inc . 'woo-customisations.php';
require_once $inc . 'woo/productpagefields.php';
require_once $inc . 'woo/gifting.php';
require_once $inc . 'woo/renewalfine.php';
require_once $inc . 'woo/work-address.php';
require_once $inc . 'woo/additional-address.php';
require_once $inc . 'woo/checkout-address-source.php';
require_once $inc . 'profile-avatar.php';
require_once $inc . 'jobs.php';
require_once $inc . 'adverts-reservations.php';
require_once $inc . 'adverts-expiry.php';
require_once $inc . 'adverts-renewals.php';
require_once $inc . 'adverts-analytics.php';
require_once $inc . 'adverts.php';
require_once $inc . 'adverts-myaccount.php';
require_once $inc . 'adverts-stats.php';
require_once $inc . 'adverts-availability.php';
require_once $inc . 'jobs-portal.php';
require_once $inc . 'jobs-gf-application.php';
require_once $inc . 'jobs-gf-resume.php';
require_once $inc . 'events.php';
require_once $inc . 'events-calendar.php';
require_once $inc . 'events-admin.php';

// Admin area (starter dashboard + settings)
if (is_admin()) {
    require_once $inc . 'admin/portal-admin.php';
    require_once $inc . 'admin/health.php';
    require_once $inc . 'admin/plan-settings.php';
require_once $inc . 'admin/integrity.php';
}

register_activation_hook(__FILE__, function () {
    // Prevent any accidental output breaking activation
    $solas_ob_started = false;
    if (ob_get_level() === 0) { ob_start(); $solas_ob_started = true; }

    if (function_exists('solas_adverts_register_cpt')) solas_adverts_register_cpt();
    if (function_exists('solas_jobs_register_cpts')) solas_jobs_register_cpts();
    if (function_exists('solas_cpd_register_cpt')) solas_cpd_register_cpt();

    flush_rewrite_rules();

    if ($solas_ob_started) { ob_end_clean(); }
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});