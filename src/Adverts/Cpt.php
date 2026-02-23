<?php
declare(strict_types=1);

namespace Solas\Portal\Adverts;

defined('ABSPATH') || exit;

final class Cpt {
    public static function register(): void {
        register_post_type('solas_advert', [
            'label' => 'Adverts',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'supports' => ['title', 'custom-fields', 'author', 'revisions'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }
}
