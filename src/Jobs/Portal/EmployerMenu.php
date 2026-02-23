<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs\Portal;

defined('ABSPATH') || exit;

final class EmployerMenu {

    public static function render(): string {
        if (!is_user_logged_in()) return '';
            $uid = (int) get_current_user_id();
            if (!solas_is_employer_user($uid)) return '';

            if (class_exists('Solas\\Portal\\Woo\\PlanGates') && !\Solas\Portal\Woo\PlanGates::canAccessEmployerJobsTools($uid)) {
                return '';
            }

            $out  = '<div class="solas-job-actions" style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px 0;">';
            $out .= '<a class="' . esc_attr(solas_wc_button_classes('primary')) . '" href="' . esc_url(site_url('/employer-dashboard/')) . '">Employer Dashboard</a>';
            $out .= '<a class="' . esc_attr(solas_wc_button_classes('default')) . '" href="' . esc_url(site_url('/employer-post-a-job/')) . '">Post a Job</a>';
            $out .= '</div>';
            return $out;
    }

}
