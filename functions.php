
<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Facility Owners management (WordPress Admin → Facility Owners).
 */
require_once get_stylesheet_directory() . '/inc/facility-owners/bootstrap.php';

/**
 * Load child theme CSS and JavaScript.
 */
add_action('wp_enqueue_scripts', function () {

    $theme_version = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'my-child-style',
        get_stylesheet_uri(),
        [],
        $theme_version
    );

    // Custom CSS
    wp_enqueue_style(
        'my-custom-css',
        get_stylesheet_directory_uri() . '/assets/css/custom.css',
        ['my-child-style'],
        $theme_version
    );

    // Custom JavaScript
    wp_enqueue_script(
        'my-custom-js',
        get_stylesheet_directory_uri() . '/assets/js/custom.js',
        [],
        $theme_version,
        true
    );
});
