<?php

if (!defined('ABSPATH')) {
    exit;
}

// Title-Tag-Support: ohne das gibt WordPress in den klassischen Templates keinen
// <title> aus. Damit greifen unsere Seitentitel (pre_get_document_title) erst.
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
} );

// SEO der Startseite: keyword-orientierter Title + Meta + OpenGraph.
add_filter( 'pre_get_document_title', function ( $title ) {
	if ( is_front_page() ) {
		return 'Firmenevents auf dem Golfplatz | Teamevents, Turniere & Incentives | Firmengolf';
	}
	return $title;
} );
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$desc = 'Firmenevents auf Deutschlands schönsten Golfplätzen: Teamevents, Firmenturniere, Schnupperkurse und Incentives. Passenden Platz finden und schnell anfragen.';
	echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="Firmenevents auf dem Golfplatz | Firmengolf">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( home_url( '/' ) ) . '">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}, 1 );

// Marken-Entität: Organization auf allen Seiten, WebSite auf der Startseite (für Google Knowledge + KI).
add_action( 'wp_head', function () {
	$org = [
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'name'        => 'Firmengolf',
		'url'         => home_url( '/' ),
		'description' => 'Firmenevents auf Golfplätzen in ganz Deutschland: Teamevents, Firmenturniere, Schnupperkurse und Incentives. Eine Anfrage, ein Ansprechpartner, eine Rechnung.',
	];
	$logo = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 512 ) : '';
	if ( $logo ) { $org['logo'] = $logo; }
	echo '<script type="application/ld+json">' . wp_json_encode( $org ) . '</script>' . "\n";

	if ( is_front_page() ) {
		$site = [
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => 'Firmengolf',
			'url'      => home_url( '/' ),
		];
		echo '<script type="application/ld+json">' . wp_json_encode( $site ) . '</script>' . "\n";
	}
}, 2 );

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

	if ( get_query_var( 'fge_termin' ) || get_query_var( 'fge_angebot' ) ) {
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
		// Reuses the portal "Platz" design (.fgpp) so the public page matches it 1:1.
		wp_enqueue_style(
			'fge-portal',
			plugins_url( 'assets/css/fge-portal.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-frontend' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
		wp_enqueue_style(
			'fge-golfplatz',
			plugins_url( 'assets/css/fge-golfplatz.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-portal' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
		wp_enqueue_script(
			'fge-golfplatz',
			plugins_url( 'assets/js/fge-golfplatz.js', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1',
			true
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
