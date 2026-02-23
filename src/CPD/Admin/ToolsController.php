<?php
declare(strict_types=1);

namespace Solas\Portal\CPD\Admin;

defined('ABSPATH') || exit;

/**
 * CPD Tools Controller
 *
 * Purpose:
 * - Keep admin menu + routing out of the global function space
 * - Provide a single safe render boundary (no white-screening the admin)
 * - Delegate to legacy tab renderers still living in includes/cpd-admin-tools.php
 *
 * This is a Stage 43 refactor step: behaviour unchanged.
 */
final class ToolsController
{
    public const MENU_SLUG = 'solas-cpd-tools';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenu']);
    }

    public static function addMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=solas_cpd_record',
            'CPD Tools',
            'CPD Tools',
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'renderPage']
        );
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        try {
            // Call the legacy renderer (still provides all tabs/features).
            if (function_exists('solas_cpd_admin_tools_page_legacy')) {
                solas_cpd_admin_tools_page_legacy();
                return;
            }

            // Fallback (should never happen unless the include file is missing).
            echo '<div class="wrap"><h1>CPD Tools</h1><div class="notice notice-error"><p>CPD Tools renderer not found.</p></div></div>';
        } catch (\Throwable $e) {
            // Never white-screen the admin: show the error in-page.
            echo '<div class="wrap"><h1>CPD Tools</h1>';
            echo '<div class="notice notice-error"><p><strong>CPD Tools error:</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '<p><code>' . esc_html($e->getFile()) . ':' . esc_html((string)$e->getLine()) . '</code></p></div>';
            echo '</div>';

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SOLAS CPD Tools] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }
    }
}
