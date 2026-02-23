<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs\Portal;

defined('ABSPATH') || exit;

final class Shortcodes {

    public static function register(): void {
        add_shortcode('solas_employer_menu', [self::class, 'solas_employer_menu']);
        add_shortcode('solas_jobs_list', [self::class, 'solas_jobs_list']);
        add_shortcode('solas_resume_manage', [self::class, 'solas_resume_manage']);
        add_shortcode('solas_applications_list', [self::class, 'solas_applications_list']);
        add_shortcode('solas_candidate_dashboard', [self::class, 'solas_candidate_dashboard']);
        add_shortcode('solas_employer_dashboard', [self::class, 'solas_employer_dashboard']);
    }

    public static function solas_employer_menu(): string {
        return EmployerMenu::render();
    }

    public static function solas_jobs_list(): string {
        return JobsList::render();
    }

    public static function solas_resume_manage(): string {
        return Resumes::render();
    }

    public static function solas_applications_list(): string {
        return Applications::render();
    }

    public static function solas_candidate_dashboard(): string {
        return CandidateDashboard::render();
    }

    public static function solas_employer_dashboard(): string {
        return EmployerDashboard::render();
    }
}
