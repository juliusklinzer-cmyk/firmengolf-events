<?php
/**
 * SEO city landing pages: /golf-events/<stadt>/
 * Programmatic routing via rewrite rule → template-city.php in the child theme.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * City config. Keyed by URL slug. Region maps to event _fge_region for listings.
 */
function fge_get_cities(): array {
	return [
		'muenchen' => [
			'name'   => 'München',
			'region' => 'Süd',
			'intro'  => 'München ist Golf-Land: von den Anlagen am Stadtrand bis zu den Plätzen Richtung Voralpen. Für Firmenevents heißt das kurze Wege, viel Auswahl und Kulisse — ideal für Team-Tage, Kundenturniere und Offsites mit Bergblick.',
		],
		'hamburg' => [
			'name'   => 'Hamburg',
			'region' => 'Nord',
			'intro'  => 'Rund um Hamburg liegen einige der schönsten Parklandschafts-Plätze Norddeutschlands — gut erreichbar und perfekt für Firmenturniere, After-Work-Runden und Sommerfeste im Grünen.',
		],
		'koeln' => [
			'name'   => 'Köln',
			'region' => 'West',
			'intro'  => 'Im Rheinland trifft kurze Anfahrt auf abwechslungsreiche Plätze. Für Kölner Unternehmen sind Golf-Events ein entspannter Weg, Teams und Kunden zusammenzubringen — vom Schnupperkurs bis zum Firmenturnier.',
		],
	];
}

add_action( 'init', static function () {
	add_rewrite_rule( '^golf-events/([^/]+)/?$', 'index.php?fge_city=$matches[1]', 'top' );
} );

add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_city';
	return $vars;
} );

add_filter( 'template_include', static function ( $template ) {
	$slug = get_query_var( 'fge_city' );
	if ( ! $slug ) {
		return $template;
	}
	$cities = fge_get_cities();
	if ( ! isset( $cities[ $slug ] ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return get_query_template( '404' );
	}
	$city_template = locate_template( 'template-city.php' );
	return $city_template ?: $template;
} );

/**
 * Ensure the rewrite rule exists (self-heals if rules weren't flushed on activation).
 */
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^golf-events/([^/]+)/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );
