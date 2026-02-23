<?php
declare(strict_types=1);

namespace Solas\Portal\Admin;

use Solas\Portal\Woo\Memberships;
use Solas\Portal\Woo\Subscriptions;
use Solas\Portal\CPD\RecordFactory;
use Solas\Portal\CPD\DTO\RecordData;

defined('ABSPATH') || exit;

final class Health {

    public const MENU_SLUG_PARENT = 'solas-portal';
    public const MENU_SLUG = 'solas-portal-health';

    public static function register(): void {
        add_action('admin_menu', [self::class, 'registerMenu'], 20);
    }

    public static function registerMenu(): void {
        // If a parent menu doesn't exist yet, create a minimal one.
        if (!self::parentMenuExists(self::MENU_SLUG_PARENT)) {
            add_menu_page(
                'SOLAS Portal',
                'SOLAS Portal',
                'manage_options',
                self::MENU_SLUG_PARENT,
                [self::class, 'renderPage'],
                'dashicons-shield',
                56
            );
        }

        add_submenu_page(
            self::MENU_SLUG_PARENT,
            'Health',
            'Health',
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'renderPage']
        );
    }

    private static function parentMenuExists(string $slug): bool {
        global $menu;
        if (!is_array($menu)) return false;
        foreach ($menu as $item) {
            if (!empty($item[2]) && (string) $item[2] === $slug) return true;
        }
        return false;
    }

    public static function renderPage(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Sorry, you are not allowed to access this page.');
        }

        $results = null;
        $ran = false;

        // Toggle CPD RecordFactory debug logging
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solas_health_toggle_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['solas_health_toggle_nonce']));
            if (wp_verify_nonce($nonce, 'solas_portal_health_toggle_debug')) {
                $enable = isset($_POST['solas_cpd_recordfactory_debug']) && (string) $_POST['solas_cpd_recordfactory_debug'] === '1';
                update_option('solas_cpd_recordfactory_debug', $enable ? '1' : '0');
                echo '<div class="notice notice-success"><p><strong>RecordFactory debug logging ' . ($enable ? 'enabled' : 'disabled') . '.</strong></p></div>';
            }
        }


        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solas_health_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['solas_health_nonce']));
            if (wp_verify_nonce($nonce, 'solas_portal_health_check')) {
                $ran = true;
                $results = self::runChecks();
            }
        }

        $info = self::getEnvironmentInfo();

        echo '<div class="wrap">';
        echo '<h1>SOLAS Portal – Health</h1>';

        if ($ran) {
            if (!empty($results['errors'])) {
                echo '<div class="notice notice-error"><p><strong>Self-check completed with issues.</strong></p></div>';
            } else {
                echo '<div class="notice notice-success"><p><strong>Self-check completed. No blocking issues found.</strong></p></div>';
            }
        }

        echo '<h2>Environment</h2>';
        echo '<table class="widefat striped" style="max-width: 1100px">';
        foreach ($info as $label => $value) {
            echo '<tr><th style="width:280px">' . esc_html($label) . '</th><td>' . wp_kses_post($value) . '</td></tr>';
        }
        echo '</table>';

        if (is_array($results)) {
            echo '<h2>Self-check results</h2>';
            self::renderChecksTable($results);
        }

        $debugEnabled = (string) get_option('solas_cpd_recordfactory_debug', '0') === '1';

        echo '<h2>CPD RecordFactory debug</h2>';
        echo '<p>When enabled, RecordFactory writes minimal diagnostics to <code>error_log</code> (duplicates + inserts). Leave off in normal operation.</p>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="solas_health_toggle_nonce" value="' . esc_attr(wp_create_nonce('solas_portal_health_toggle_debug')) . '" />';
        echo '<label style="display:block; margin:8px 0 12px 0">';
        echo '<input type="checkbox" name="solas_cpd_recordfactory_debug" value="1" ' . checked(true, $debugEnabled, false) . ' /> '; 
        echo 'Enable RecordFactory debug logging';
        echo '</label>';
        submit_button('Save', 'secondary', 'submit', false);
        echo '</form>';

        echo '<h2>Run self-check</h2>';
        echo '<p>This runs a read-only diagnostic of integrations and key settings. It does not write to the database.</p>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="solas_health_nonce" value="' . esc_attr(wp_create_nonce('solas_portal_health_check')) . '" />';
        submit_button('Run self-check', 'primary', 'submit', false);
        echo '</form>';

        echo '</div>';
    }

    private static function renderChecksTable(array $results): void {
        $rows = $results['checks'] ?? [];
        echo '<table class="widefat striped" style="max-width: 1100px">';
        echo '<thead><tr><th style="width:220px">Check</th><th style="width:120px">Status</th><th>Details</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $status = strtoupper((string) ($row['status'] ?? 'info'));
            $statusClass = 'info';
            if ($status === 'OK') $statusClass = 'success';
            if ($status === 'WARN') $statusClass = 'warning';
            if ($status === 'ERROR') $statusClass = 'error';

            $badge = '<span class="solas-health-badge solas-health-' . esc_attr($statusClass) . '">' . esc_html($status) . '</span>';

            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['label'] ?? '')) . '</td>';
            echo '<td>' . $badge . '</td>';
            echo '<td>' . wp_kses_post((string) ($row['details'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        // Small inline badge styles (admin-safe).
        echo '<style>
            .solas-health-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-weight:600;font-size:12px;line-height:18px}
            .solas-health-success{background:#d1e7dd;color:#0f5132}
            .solas-health-warning{background:#fff3cd;color:#664d03}
            .solas-health-error{background:#f8d7da;color:#842029}
            .solas-health-info{background:#cff4fc;color:#055160}
        </style>';
    }

    private static function getEnvironmentInfo(): array {
        $tz = function_exists('wp_timezone') ? wp_timezone() : null;
        $now = $tz ? (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s T') : wp_date('Y-m-d H:i:s');

        $info = [];
        $info['Site URL'] = esc_html((string) get_site_url());
        $info['Current time (site timezone)'] = esc_html($now);
        $info['PHP'] = esc_html(PHP_VERSION);
        $info['WordPress'] = esc_html(get_bloginfo('version'));

        $woo = class_exists('WooCommerce') ? 'Yes' : 'No';
        $info['WooCommerce active'] = esc_html($woo);

        $memberships = Memberships::isActive() ? 'Yes' : 'No';
        $info['WooCommerce Memberships active'] = esc_html($memberships);

        $subs = Subscriptions::isActive() ? 'Yes' : 'No';
        $info['WooCommerce Subscriptions detected'] = esc_html($subs);

        $gf = class_exists('GFForms') ? 'Yes' : 'No';
        $info['Gravity Forms active'] = esc_html($gf);

        $as = function_exists('as_next_scheduled_action') || class_exists('ActionScheduler') ? 'Yes' : 'No';
        $info['Action Scheduler detected'] = esc_html($as);

        $info['SOLAS Portal version'] = defined('SOLAS_PORTAL_VERSION') ? esc_html((string) SOLAS_PORTAL_VERSION) : 'Unknown';

        return $info;
    }

    private static function runChecks(): array {
        $checks = [];
        $errors = [];

        // Woo
        $wooOk = class_exists('WooCommerce');
        $checks[] = [
            'label' => 'WooCommerce',
            'status' => $wooOk ? 'ok' : 'error',
            'details' => $wooOk ? 'WooCommerce is active.' : 'WooCommerce is not active. Many SOLAS Portal features will not work.'
        ];
        if (!$wooOk) $errors[] = 'WooCommerce missing';

        // Memberships
        $memOk = Memberships::isActive();
        $checks[] = [
            'label' => 'Woo Memberships',
            'status' => $memOk ? 'ok' : 'warn',
            'details' => $memOk ? 'WooCommerce Memberships detected.' : 'WooCommerce Memberships not detected. Membership-gated features may be unavailable.'
        ];

        // Subscriptions
        $subsOk = Subscriptions::isActive();
        $checks[] = [
            'label' => 'Woo Subscriptions',
            'status' => $subsOk ? 'ok' : 'warn',
            'details' => $subsOk ? 'WooCommerce Subscriptions detected.' : 'WooCommerce Subscriptions not detected. Subscription-based gifting / renewal fine may not run.'
        ];

        // Gravity Forms
        $gfOk = class_exists('GFForms');
        $checks[] = [
            'label' => 'Gravity Forms',
            'status' => $gfOk ? 'ok' : 'warn',
            'details' => $gfOk ? 'Gravity Forms detected.' : 'Gravity Forms not detected. Profile avatar and CPD submission forms may be unavailable.'
        ];

        // Renewal fine settings
        $fineEnabled = (bool) get_option('solas_renewal_fine_enabled', false);
        $fineProductId = (int) get_option('solas_renewal_fine_product_id', 0);
        $deadline = (string) get_option('solas_renewal_fine_deadline_mmdd', '11-30');

        $fineStatus = 'info';
        $fineDetails = 'Late renewal fine is disabled.';
        if ($fineEnabled) {
            if ($fineProductId > 0) {
                $fineStatus = 'ok';
                $fineDetails = 'Enabled. Fine product ID: ' . esc_html((string) $fineProductId) . '. Deadline (MM-DD): ' . esc_html($deadline) . '.';
            } else {
                $fineStatus = 'warn';
                $fineDetails = 'Enabled but product ID is not configured.';
            }
        }
        $checks[] = [
            'label' => 'Late renewal fine',
            'status' => $fineStatus,
            'details' => $fineDetails,
        ];

        // ProductPageFields config
        $courseCat = (string) get_option('solas_product_course_category_slug', 'evening-courses');
        $courseIds = get_option('solas_product_course_ids', []);
        $courseIdsText = is_array($courseIds) ? implode(', ', array_map('intval', $courseIds)) : (string) $courseIds;

        $checks[] = [
            'label' => 'Giftable course config',
            'status' => 'info',
            'details' => 'Course category slug: <code>' . esc_html($courseCat) . '</code>. Course IDs (fallback): <code>' . esc_html($courseIdsText) . '</code>.'
        ];


        // CPD DTO / RecordFactory (dry run)
        $dtoOk = class_exists(RecordData::class) && class_exists(RecordFactory::class);
        $checks[] = [
            'label' => 'CPD DTO / RecordFactory',
            'status' => $dtoOk ? 'ok' : 'error',
            'details' => $dtoOk
                ? 'RecordData DTO and RecordFactory are available.'
                : 'Missing CPD DTO/Factory classes. Ensure /src/CPD loads correctly.',
        ];
        if (!$dtoOk) $errors[] = 'CPD DTO/Factory missing';

        if ($dtoOk) {
            // Note: this is a dry-run payload only. It does not write to the database.
            $sample = new RecordData(
                1,
                (int) wp_date('Y'),
                'structured',
                'health_check',
                'pending',
                'publish',
                1.0,
                60.0,
                0.0,
                'Health check',
                '',
                'Health self-check record (dry run).',
                ['https://example.com/evidence.pdf'],
                'Health check record',
                'health:' . wp_date('YmdHis'),
                []
            );

            $valid = RecordFactory::validate($sample);
            $checks[] = [
                'label' => 'CPD RecordFactory validate',
                'status' => is_wp_error($valid) ? 'error' : 'ok',
                'details' => is_wp_error($valid)
                    ? 'Validation failed: ' . esc_html($valid->get_error_message())
                    : 'Validation OK for sample DTO.',
            ];
            if (is_wp_error($valid)) $errors[] = 'CPD RecordFactory validate failed';

            $dry = RecordFactory::dryRun($sample);
            $checks[] = [
                'label' => 'CPD RecordFactory dry-run',
                'status' => is_wp_error($dry) ? 'error' : 'ok',
                'details' => is_wp_error($dry)
                    ? 'Dry run failed: ' . esc_html($dry->get_error_message())
                    : 'Dry run OK (no database writes).',
            ];
            if (is_wp_error($dry)) $errors[] = 'CPD RecordFactory dry-run failed';
        }


        // CPD adapters (basic invocation, no writes)
        $adapterChecks = [
            'GF Submission Adapter' => \Solas\Portal\CPD\Adapters\GfSubmissionAdapter::class,
            'LearnDash Quiz Adapter' => \Solas\Portal\CPD\Adapters\LearnDashQuizAdapter::class,
            'Teams Import Adapter' => \Solas\Portal\CPD\Adapters\TeamsImportAdapter::class,
        ];
        foreach ($adapterChecks as $label => $class) {
            $exists = class_exists($class);
            $checks[] = [
                'label' => 'CPD ' . $label,
                'status' => $exists ? 'ok' : 'error',
                'details' => $exists ? 'Class available: <code>' . esc_html($class) . '</code>.' : 'Missing adapter class: <code>' . esc_html($class) . '</code>.',
            ];
            if (!$exists) $errors[] = 'Missing ' . $label;
        }

        // Optional: invoke adapters with safe sample payloads (will return WP_Error if helpers missing)
        if (class_exists(\Solas\Portal\CPD\Adapters\GfSubmissionAdapter::class) && $gfOk && function_exists('solas_cpd_normalize_cycle_year')) {
            $sampleEntry = [
                'id' => 999999,
                '1'  => '2025/26',
                '5'  => '60',
                '9'  => 'Health adapter check',
                '3'  => 'Health reflection',
                '4'  => '[]',
            ];
            $dto = \Solas\Portal\CPD\Adapters\GfSubmissionAdapter::toRecordData($sampleEntry, 1, 'structured', 1);
            $checks[] = [
                'label' => 'CPD GF adapter dry parse',
                'status' => is_wp_error($dto) ? 'warn' : 'ok',
                'details' => is_wp_error($dto)
                    ? 'Adapter returned: ' . esc_html($dto->get_error_message())
                    : 'Adapter parsed sample entry successfully.',
            ];
        }

        if (class_exists(\Solas\Portal\CPD\Adapters\LearnDashQuizAdapter::class)) {
            $user = get_user_by('id', 1);
            if ($user instanceof \WP_User) {
                $dto = \Solas\Portal\CPD\Adapters\LearnDashQuizAdapter::toRecordData([], $user, 123, 456, '2025/26', time(), 1.0, false);
                $checks[] = [
                    'label' => 'CPD LearnDash adapter dry parse',
                    'status' => is_wp_error($dto) ? 'warn' : 'ok',
                    'details' => is_wp_error($dto)
                        ? 'Adapter returned: ' . esc_html($dto->get_error_message())
                        : 'Adapter parsed sample payload successfully.',
                ];
            }
        }

        if (class_exists(\Solas\Portal\CPD\Adapters\TeamsImportAdapter::class)) {
            $row = [
                'user_id' => 1,
                'cycle_year' => (int) wp_date('Y'),
                'source_ref' => 'health:teams:' . wp_date('YmdHis'),
                'minutes' => 60,
                'category' => 'structured',
                'title' => 'Health Teams import',
                'description' => 'Dry parse only',
            ];
            $dto = \Solas\Portal\CPD\Adapters\TeamsImportAdapter::toRecordData($row);
            $checks[] = [
                'label' => 'CPD Teams adapter dry parse',
                'status' => is_wp_error($dto) ? 'warn' : 'ok',
                'details' => is_wp_error($dto)
                    ? 'Adapter returned: ' . esc_html($dto->get_error_message())
                    : 'Adapter parsed sample row successfully.',
            ];
        }


        return [
            'checks' => $checks,
            'errors' => $errors,
        ];
    }
}
