<?php
declare(strict_types=1);

namespace Solas\Portal\Admin;

use Solas\Portal\Woo\Memberships;
use Solas\Portal\Woo\PlanGates;

defined('ABSPATH') || exit;

final class PlanSettings {

    public static function register(): void {
        add_action('admin_menu', [self::class, 'registerMenu'], 25);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    public static function registerMenu(): void {
        if (!current_user_can('manage_options')) return;

        // Add under SOLAS Portal top-level if present.
        add_submenu_page(
            'solas-portal',
            'Membership Plans',
            'Membership Plans',
            'manage_options',
            'solas-portal-membership-plans',
            [self::class, 'renderPage']
        );
    }

    public static function registerSettings(): void {
        register_setting('solas_portal_membership_plans', PlanGates::OPTION_MAP, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitizeMap'],
            'default' => [
                'full' => 0,
                'associate' => 0,
                'honorary' => 0,
                'student' => 0,
            ],
        ]);
    }

    public static function sanitizeMap($value): array {
        $out = [
            'full' => 0,
            'associate' => 0,
            'honorary' => 0,
            'student' => 0,
        ];
        if (!is_array($value)) return $out;

        foreach ($out as $k => $_) {
            $out[$k] = isset($value[$k]) ? (int) $value[$k] : 0;
            if ($out[$k] < 0) $out[$k] = 0;
        }
        return $out;
    }

    private static function getPlansForSelect(): array {
        $plans = [];
        foreach (Memberships::getPlans() as $p) {
            if (!is_object($p)) continue;
            $id = method_exists($p, 'get_id') ? (int) $p->get_id() : 0;
            if ($id <= 0) continue;
            $name = method_exists($p, 'get_name') ? (string) $p->get_name() : (method_exists($p, 'get_title') ? (string) $p->get_title() : ('Plan #' . $id));
            $plans[$id] = $name;
        }
        asort($plans, SORT_NATURAL | SORT_FLAG_CASE);
        return $plans;
    }

    public static function renderPage(): void {
        if (!current_user_can('manage_options')) return;

        $map = PlanGates::getConfiguredPlanMap();
        $plans = self::getPlansForSelect();

        echo '<div class="wrap">';
        echo '<h1>Membership Plans</h1>';
        echo '<p class="description">Map your WooCommerce Membership plans to SOLAS roles (Full, Associate, Honorary, Student). This lets the portal apply consistent access rules (CPD, employer tools, etc.).</p>';

        if (!Memberships::isActive()) {
            echo '<div class="notice notice-warning"><p><strong>WooCommerce Memberships is not detected.</strong> Activate it to populate the plan list. You can still save settings, but they won’t be used until Memberships is active.</p></div>';
        } elseif (!$plans) {
            echo '<div class="notice notice-warning"><p><strong>No membership plans found.</strong> Create plans in WooCommerce &rarr; Memberships &rarr; Membership Plans.</p></div>';
        }

        echo '<form method="post" action="options.php">';
        settings_fields('solas_portal_membership_plans');

        echo '<table class="form-table" role="presentation">';
        self::renderSelectRow('full', 'Full member plan', $map['full'], $plans);
        self::renderSelectRow('associate', 'Associate member plan', $map['associate'], $plans);
        self::renderSelectRow('honorary', 'Honorary member plan', $map['honorary'], $plans);
        self::renderSelectRow('student', 'Student member plan', $map['student'], $plans);
        echo '</table>';

        submit_button('Save plan mapping');
        echo '</form>';

        echo '<hr />';
        echo '<h2>Notes</h2>';
        echo '<ul style="list-style:disc;margin-left:18px;">';
        echo '<li>Leaving a role unmapped falls back to identifier matching (slug/name) via the <code>solas_membership_plan_identifiers</code> filter.</li>';
        echo '<li>Student members are blocked from CPD by default; Full/Associate/Honorary are allowed.</li>';
        echo '</ul>';

        echo '</div>';
    }

    private static function renderSelectRow(string $key, string $label, int $selected, array $plans): void {
        $fieldName = PlanGates::OPTION_MAP . '[' . $key . ']';
        echo '<tr>';
        echo '<th scope="row"><label for="solas_plan_' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td>';
        echo '<select id="solas_plan_' . esc_attr($key) . '" name="' . esc_attr($fieldName) . '">';
        echo '<option value="0">' . esc_html__('— Not set —', 'solas-portal') . '</option>';
        foreach ($plans as $id => $name) {
            printf(
                '<option value="%d"%s>%s</option>',
                (int) $id,
                selected((int) $selected, (int) $id, false),
                esc_html($name)
            );
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
    }
}
