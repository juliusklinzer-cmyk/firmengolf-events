<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fge_register_post_types() {

	register_post_type( 'firmengolf_event', [
		'labels'       => [
			'name'          => __( 'Firmenevents', 'firmengolf-events' ),
			'singular_name' => __( 'Firmenevent', 'firmengolf-events' ),
		],
		'public'        => true,
		'has_archive'   => true,
		'rewrite'       => [ 'slug' => 'firmenevents' ],
		'supports'      => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
		'show_in_rest'  => true,
		'menu_icon'     => 'dashicons-calendar-alt',
		'menu_position' => 20,
	] );

	register_post_type( 'firmengolf_partner', [
		'labels'       => [
			'name'          => __( 'Golfplatz Partner', 'firmengolf-events' ),
			'singular_name' => __( 'Golfplatz Partner', 'firmengolf-events' ),
		],
		'public'             => false,
		'publicly_queryable' => true,                          // public single page (gated in fge_block_non_public_partners)
		'exclude_from_search' => true,
		'show_ui'            => true,
		'has_archive'        => false,
		'rewrite'            => [ 'slug' => 'golfplatz', 'with_front' => false ],
		'supports'           => [ 'title', 'editor', 'thumbnail' ],
		// false: die öffentliche v2-REST-Liste würde sonst ALLE Partner (auch nicht
		// freigegebene/pausierte) enumerierbar machen — der Frontend-Gate greift dort nicht.
		// Portal/Frontend nutzen die wp/v2-Route nicht; Single-Page bleibt via publicly_queryable.
		'show_in_rest'       => false,
		'menu_icon'          => 'dashicons-location-alt',
		'menu_position'      => 21,
	] );

	register_post_type( 'firmengolf_request', [
		'labels'       => [
			'name'          => __( 'Event Anfragen', 'firmengolf-events' ),
			'singular_name' => __( 'Event Anfrage', 'firmengolf-events' ),
		],
		'public'        => false,
		'show_ui'       => true,
		'has_archive'   => false,
		'supports'      => [ 'editor' ],
		// show_in_rest absichtlich false: Event-Anfragen enthalten Kundendaten (PII).
		// Mit true lieferte /wp-json/wp/v2/firmengolf_request diese unauthentifiziert aus.
		'show_in_rest'  => false,
		'menu_icon'     => 'dashicons-email-alt',
		'menu_position' => 22,
	] );
}

add_action( 'init', 'fge_register_post_types' );

// ── Eigener URL-Stamm je Partner-Typ (Julius, 28.08.) ─────────────────────────
// Golflehrer-Visitenkarten laufen unter /golflehrer/<name>/ statt /golfplatz/…,
// der CPT-Rewrite bleibt für Plätze (und vorerst Indoor) unverändert.

add_action( 'init', static function () {
	add_rewrite_rule( '^golflehrer/([^/]+)/?$', 'index.php?firmengolf_partner=$matches[1]', 'top' );
}, 10 );

/** Permalinks von Coach-Partnern auf den golflehrer-Stamm umschreiben. */
add_filter( 'post_type_link', static function ( string $link, WP_Post $post ): string {
	if ( 'firmengolf_partner' === $post->post_type
		&& function_exists( 'fge_partner_type' )
		&& 'coach' === fge_partner_type( (int) $post->ID ) ) {
		return str_replace( '/golfplatz/', '/golflehrer/', $link );
	}
	return $link;
}, 10, 2 );

// Kanonisch: falscher Stamm (alter Link, geratene URL) leitet 301 auf den
// richtigen um — /golfplatz/<coach>/ → /golflehrer/<coach>/ und umgekehrt.
add_action( 'template_redirect', static function () {
	if ( ! is_singular( 'firmengolf_partner' ) ) {
		return;
	}
	$path      = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$canonical = get_permalink();
	$want_base = function_exists( 'fge_partner_type' ) && 'coach' === fge_partner_type( (int) get_the_ID() ) ? '/golflehrer/' : '/golfplatz/';
	if ( $canonical && '' !== $path && ! str_starts_with( $path, $want_base ) ) {
		wp_safe_redirect( $canonical, 301 );
		exit;
	}
}, 2 );

// Einmaliger Flush, sobald die golflehrer-Regel fehlt (Muster aus partner-invite.php).
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^golflehrer/([^/]+)/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );
