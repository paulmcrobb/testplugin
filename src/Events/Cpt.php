<?php
declare(strict_types=1);

namespace Solas\Portal\Events;

defined('ABSPATH') || exit;

final class Cpt
{
    public static function register(): void
    {


    register_post_type(SOLAS_EVENT_CPT, [
        'label' => 'Events',
        'public' => true,

        // You use a PAGE at /events/ for the list/shortcode, so avoid conflicts:
        'has_archive' => false,
        'rewrite'     => ['slug' => 'event', 'with_front' => false],

        'show_in_rest' => true,
        'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'author'],
        'menu_icon'    => 'dashicons-calendar-alt',
    ]);

    register_taxonomy(SOLAS_EVENT_TAX_TYPE, SOLAS_EVENT_CPT, [
        'label'        => 'Event Types',
        'public'       => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite'      => ['slug' => 'event-type', 'with_front' => false],
    ]);
    }
}
