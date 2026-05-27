<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style(
		'firmengolf-child-style',
		get_stylesheet_uri(),
		[],
		wp_get_theme()->get( 'Version' )
	);
	wp_enqueue_style(
		'fge-frontend',
		plugins_url( 'assets/css/fge-frontend.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
		[],
		defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
	);
} );
