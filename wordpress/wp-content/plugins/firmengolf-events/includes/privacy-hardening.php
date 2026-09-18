<?php
/**
 * Härtungen aus dem Sicherheits-Audit vom 18.09.2026.
 *
 *  - Token-Seiten (Angebot, Termin, Einladung, Onboarding-Token) ohne Cache und
 *    ohne Tracker (das Theme fragt fge_is_private_token_page()).
 *  - WP-Nutzerliste (/wp/v2/users, Autorenarchive) nicht öffentlich: Partner-Konten
 *    waren mit Klarnamen und aus der Login-Mail gebildetem Nicename enumerierbar.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Seite mit Magic-Link oder Token in der URL? */
function fge_is_private_token_page(): bool {
	if ( get_query_var( 'fge_angebot' ) || get_query_var( 'fge_termin' ) || get_query_var( 'fge_einladung' ) ) {
		return true;
	}
	return isset( $_GET['ob_token'] ) || isset( $_GET['ob_resume'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

// Kein Browser-/Proxy-Cache für Seiten mit Kundendaten hinter einem Token.
add_action( 'template_redirect', static function () {
	if ( fge_is_private_token_page() ) {
		nocache_headers();
	}
}, 1 );

// REST-Nutzerliste nur für eingeloggte Nutzer.
add_filter( 'rest_endpoints', static function ( array $endpoints ): array {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}
	foreach ( [ '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)', '/wp/v2/users/me' ] as $route ) {
		unset( $endpoints[ $route ] );
	}
	return $endpoints;
} );

// Autorenarchive nur für Administratoren (Blog-Autoren sind Post-Meta, keine WP-User).
add_action( 'template_redirect', static function () {
	if ( ! is_author() ) {
		return;
	}
	$user = get_queried_object();
	if ( $user instanceof WP_User && user_can( $user, 'manage_options' ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}, 2 );

// ?author=1 → keine Weiterleitung auf den Autoren-Slug (Enumeration über die Redirect-URL).
add_filter( 'redirect_canonical', static function ( $redirect ) {
	if ( is_author() && isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return false;
	}
	return $redirect;
} );
