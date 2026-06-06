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

	// Individuelle-Events-Seite: Budget-Rechner + Anfrage-Wizard (JS-Insel).
	if ( is_page( 'individuelle-events' ) && function_exists( 'fge_bc_config' ) ) {
		wp_enqueue_script(
			'fge-individual',
			plugins_url( 'assets/js/fge-individual.js', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1',
			true
		);
		wp_localize_script( 'fge-individual', 'FGE_IND', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fge_general_request' ),
			'bc'      => fge_bc_config(),
		] );
	}
} );
