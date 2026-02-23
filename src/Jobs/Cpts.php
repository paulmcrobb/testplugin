<?php
declare(strict_types=1);

namespace Solas\Portal\Jobs;

defined('ABSPATH') || exit;

final class Cpts {
    public static function register(): void {
        register_post_type('solas_job', [
        'label'           => 'Jobs',
        'public'          => true,
        'has_archive'     => true,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'show_in_rest'    => false,
        'supports'        => ['title', 'editor', 'author', 'revisions', 'thumbnail'],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ]);

    register_post_type('solas_job_app', [
        'label'           => 'Job Applications',
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => 'edit.php?post_type=solas_job',
        'show_in_rest'    => false,
        'supports'        => ['title', 'editor', 'author', 'revisions'],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ]);

    register_post_type('solas_candidate', [
        'label'           => 'Candidate Profiles',
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => 'edit.php?post_type=solas_job',
        'show_in_rest'    => false,
        'supports'        => ['title', 'editor', 'author', 'revisions', 'thumbnail'],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ]);
    }
}
