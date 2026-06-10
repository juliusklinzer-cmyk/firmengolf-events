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

	// Partner-Portal (neu): gekapseltes Stylesheet, nur auf der Portalseite.
	if ( is_page( 'partnerportal' ) ) {
		wp_enqueue_style(
			'fge-portal',
			plugins_url( 'assets/css/fge-portal.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-frontend' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
	}

	if ( get_query_var( 'fge_termin' ) ) {
		wp_enqueue_style(
			'fge-termin',
			plugins_url( 'assets/css/fge-termin.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-frontend' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
	}

	if ( is_page( 'partner-onboarding' ) ) {
		wp_enqueue_style(
			'fge-onboarding',
			plugins_url( 'assets/css/fge-onboarding.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-frontend' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
	}

	if ( is_singular( 'firmengolf_partner' ) ) {
		wp_enqueue_style(
			'fge-golfplatz',
			plugins_url( 'assets/css/fge-golfplatz.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-frontend' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
	}

	// Anfrage-Wizard (JS-Insel) — auf Individuelle-Events (inkl. Budget-Rechner) und der allgemeinen Anfrage-Seite.
	if ( is_page( [ 'individuelle-events', 'event-anfrage' ] ) ) {
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
			// Budget-Rechner-Config nur auf der Individuelle-Events-Seite; sonst null (Wizard läuft trotzdem).
			'bc'      => ( is_page( 'individuelle-events' ) && function_exists( 'fge_bc_config' ) ) ? fge_bc_config() : null,
			// Golfplatz-Namen für den optionalen „Konkreter Platz"-Dropdown.
			'places'  => function_exists( 'fge_get_public_place_names' ) ? fge_get_public_place_names() : [],
		] );
	}
} );
