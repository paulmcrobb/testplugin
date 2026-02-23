<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs;

defined('ABSPATH') || exit;

final class Settings {

    public static function register(): void {
        add_action('admin_menu', [self::class, 'registerMenu'], 20);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    public static function registerMenu(): void {
        // Submenu under Jobs CPT
    add_submenu_page(
        'edit.php?post_type=solas_job',
        'Jobs Settings',
        'Settings',
        'manage_options',
        'solas-jobs-settings',
        'solas_jobs_render_settings_page'
    );
    }

    public static function registerSettings(): void {
        // -----------------------------
    // Core setting(s)
    // -----------------------------
    register_setting('solas_jobs_settings', 'solas_jobs_single_template', [
        'type' => 'string',
        'sanitize_callback' => function ($v) {
            $v = is_string($v) ? $v : '';
            return in_array($v, ['theme', 'solas'], true) ? $v : 'theme';
        },
        'default' => 'theme',
    ]);

    // Listings / cards
    register_setting('solas_jobs_settings', 'solas_jobs_card_use_short_desc', [
        'type' => 'boolean',
        'sanitize_callback' => function ($v) { return (bool) $v; },
        'default' => true,
    ]);

    register_setting('solas_jobs_settings', 'solas_jobs_card_truncate_words', [
        'type' => 'integer',
        'sanitize_callback' => function ($v) {
            $n = (int) $v;
            if ($n < 10) $n = 10;
            if ($n > 200) $n = 200;
            return $n;
        },
        'default' => 60,
    ]);

    register_setting('solas_jobs_settings', 'solas_jobs_per_page', [
        'type' => 'integer',
        'sanitize_callback' => function ($v) {
            $n = (int) $v;
            if ($n < 1) $n = 1;
            if ($n > 100) $n = 100;
            return $n;
        },
        'default' => 10,
    ]);

    register_setting('solas_jobs_settings', 'solas_jobs_default_sort', [
        'type' => 'string',
        'sanitize_callback' => function ($v) {
            $v = is_string($v) ? $v : '';
            return in_array($v, ['newest', 'closing_soon'], true) ? $v : 'newest';
        },
        'default' => 'newest',
    ]);

    // Submission / moderation
    register_setting('solas_jobs_settings', 'solas_jobs_submission_status', [
        'type' => 'string',
        'sanitize_callback' => function ($v) {
            $v = is_string($v) ? $v : '';
            return in_array($v, ['draft', 'pending', 'publish'], true) ? $v : 'draft';
        },
        'default' => 'draft',
    ]);

    // Applications
    register_setting('solas_jobs_settings', 'solas_jobs_require_login_apply', [
        'type' => 'boolean',
        'sanitize_callback' => function ($v) { return (bool) $v; },
        'default' => false,
    ]);

    // -----------------------------
    // Sections
    // -----------------------------
    add_settings_section(
        'solas_jobs_display_section',
        'Display',
        '__return_false',
        'solas-jobs-settings'
    );

    add_settings_section(
        'solas_jobs_listings_section',
        'Listings',
        '__return_false',
        'solas-jobs-settings'
    );

    add_settings_section(
        'solas_jobs_moderation_section',
        'Moderation',
        '__return_false',
        'solas-jobs-settings'
    );

    add_settings_section(
        'solas_jobs_applications_section',
        'Applications',
        '__return_false',
        'solas-jobs-settings'
    );

    // -----------------------------
    // Fields: Display
    // -----------------------------
    add_settings_field(
        'solas_jobs_single_template',
        'Single job template',
        function () {
            $mode = (string) get_option('solas_jobs_single_template', 'theme');
            ?>
            <fieldset>
                <label style="display:block;margin:0 0 8px;">
                    <input type="radio" name="solas_jobs_single_template" value="theme" <?php checked($mode, 'theme'); ?> />
                    Theme default (recommended)
                </label>
                <label style="display:block;margin:0 0 8px;">
                    <input type="radio" name="solas_jobs_single_template" value="solas" <?php checked($mode, 'solas'); ?> />
                    SOLAS template (constrained featured image, consistent layout)
                </label>
                <p class="description">Only affects the <code>solas_job</code> single view.</p>
            </fieldset>
            <?php
        },
        'solas-jobs-settings',
        'solas_jobs_display_section'
    );

    // -----------------------------
    // Fields: Listings
    // -----------------------------
    add_settings_field(
        'solas_jobs_card_use_short_desc',
        'Use short description on job cards',
        function () {
            $v = (bool) get_option('solas_jobs_card_use_short_desc', true);
            ?>
            <label>
                <input type="checkbox" name="solas_jobs_card_use_short_desc" value="1" <?php checked($v, true); ?> />
                Prefer “Short Job Description” on listings (fallback to trimmed full description).
            </label>
            <?php
        },
        'solas-jobs-settings',
        'solas_jobs_listings_section'
    );

    add_settings_field(
        'solas_jobs_card_truncate_words',
        'Fallback truncate length (words)',
        function () {
            $n = (int) get_option('solas_jobs_card_truncate_words', 60);
            ?>
            <input type="number" min="10" max="200" step="1" name="solas_jobs_card_truncate_words" value="<?php echo esc_attr($n); ?>" class="small-text" />
            <p class="description">Used when short description is empty.</p>
            <?php
        },
        'solas-jobs-settings',
        'solas_jobs_listings_section'
    );

    add_settings_field(
        'solas_jobs_per_page',
        'Jobs per page',
        function () {
            $n = (int) get_option('solas_jobs_per_page', 10);
            ?>
            <input type="number" min="1" max="100" step="1" name="solas_jobs_per_page" value="<?php echo esc_attr($n); ?>" class="small-text" />
            <?php
        },
        'solas-jobs-settings',
        'solas_jobs_listings_section'
    );

    add_settings_field(
        'solas_jobs_default_sort',
        'Default sorting',
        function () {
            $v = (string) get_option('solas_jobs_default_sort', 'newest');
            ?>
            <select name="solas_jobs_default_sort">
                <option value="newest" <?php selected($v, 'newest'); ?>>Newest first</option>
                <option value="closing_soon" <?php selected($v, 'closing_soon'); ?>>Closing soon</option>
            </select>
            <?php
        },
        'solas-jobs-settings',
        'solas_jobs_listings_section'
    );

    // -----------------------------
    // Fields: Moderation
    // -----------------------------
    add_settings_field(
        'solas_jobs_submission_status',
        'New job submission status',
        function () {
            $v = (string) get_option('solas_jobs_submission_status', 'draft');
            ?>
            <select name="solas_jobs_submission_status">
                <option value="draft" <?php selected($v, 'draft'); ?>>Draft (admin review)</option>
                <option value="pending" <?php selected($v, 'pending'); ?>>Pending review</option>
                <option value="publish" <?php selected($v, 'publish'); ?>>Publish immediately</option>
            </select>
            <p class="description">Controls the post status when a job is submitted via Gravity Forms.</p>
            <?php
        },
        'solas-jobs-settings',
        'solas_jobs_moderation_section'
    );

    // -----------------------------
    // Fields: Applications
    // -----------------------------
    add_settings_field(
        'solas_jobs_require_login_apply',
        'Require login to apply',
        function () {
            $v = (bool) get_option('solas_jobs_require_login_apply', false);
            ?>
            <label>
                <input type="checkbox" name="solas_jobs_require_login_apply" value="1" <?php checked($v, true); ?> />
                Users must be logged in to submit an application.
            </label>
            <?php
        },
        'solas-jobs-settings',
        'solas_jobs_applications_section'
    );
    }

    public static function renderPage(): void {
        if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1>Jobs Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('solas_jobs_settings');
            do_settings_sections('solas-jobs-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
    }
}
