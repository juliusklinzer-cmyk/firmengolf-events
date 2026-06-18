<?php
/**
 * SEO: XML-Sitemap-Ergänzungen.
 *
 * WordPress-Core nimmt nur public Post-Types in die Sitemap auf. Unsere
 * SEO-/Money-Pages sind aber teils virtuell (City-Landingpages via Rewrite)
 * oder bewusst `public => false` (Golfplatz-Partner). Dieses Modul:
 *
 *  1. registriert einen eigenen Sitemap-Provider für City- und Partner-Seiten,
 *  2. beschränkt die Event-Sitemap auf öffentlich sichtbare Events,
 *  3. nimmt funktionale/gated Seiten (Portal, Onboarding) aus der Page-Sitemap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seiten-Slugs, die auf `noindex` stehen und nicht in die Sitemap gehören.
 * Single Source of Truth – auch vom Theme (functions.php) genutzt.
 */
function fge_noindex_page_slugs(): array {
	return [ 'partnerportal', 'partner-onboarding' ];
}

/**
 * Autoren-Sitemap (wp-sitemap-users-*.xml) entfernen — Autorenseiten sind dünn
 * und stehen auf noindex (siehe wp_robots-Filter im Theme).
 */
add_filter( 'wp_sitemaps_add_provider', static function ( $provider, $name ) {
	if ( 'users' === $name ) {
		return false;
	}
	return $provider;
}, 10, 2 );

/**
 * Event-Sitemap auf öffentlich sichtbare Events beschränken und gated Seiten
 * aus der Page-Sitemap entfernen.
 */
add_filter( 'wp_sitemaps_posts_query_args', function ( array $args, string $post_type ): array {
	if ( 'firmengolf_event' === $post_type ) {
		$args['meta_query'] = [
			[
				'key'     => '_fge_event_status',
				'value'   => function_exists( 'fge_public_event_statuses' ) ? fge_public_event_statuses() : [ 'freigegeben' ],
				'compare' => 'IN',
			],
		];
	}

	if ( 'page' === $post_type ) {
		$exclude = [];
		foreach ( fge_noindex_page_slugs() as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page ) {
				$exclude[] = (int) $page->ID;
			}
		}
		if ( $exclude ) {
			$args['post__not_in'] = array_merge( $args['post__not_in'] ?? [], $exclude );
		}
	}

	return $args;
}, 10, 2 );

/**
 * Eigener Sitemap-Provider für City-Landingpages (/golf-events/<stadt>/) und
 * öffentlich sichtbare Golfplatz-Partner (/golfplatz/<slug>/).
 *
 * Die Provider-Klasse wird erst innerhalb des init-Callbacks definiert, weil die
 * Basisklasse WP_Sitemaps_Provider zum Zeitpunkt des frühen require noch nicht
 * geladen ist (sonst Fatal Error).
 */
add_action( 'init', function () {
	if ( ! function_exists( 'wp_sitemaps_get_server' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
		return;
	}
	// Stellt sicher, dass die Sitemap-Basisklassen geladen sind.
	wp_sitemaps_get_server();
	if ( ! class_exists( 'WP_Sitemaps_Provider' ) ) {
		return;
	}

	if ( ! class_exists( 'FGE_Landing_Sitemap_Provider' ) ) {
		class FGE_Landing_Sitemap_Provider extends WP_Sitemaps_Provider {

			public function __construct() {
				// Name darf nur [a-z] enthalten – das Sitemap-Rewrite von WP-Core
				// matcht keine Bindestriche/Ziffern im Namenssegment.
				$this->name        = 'fgelandings';
				$this->object_type = 'fgelandings';
			}

			public function get_url_list( $page_num, $object_subtype = '' ) {
				$list = [];

				// City-Landingpages.
				if ( function_exists( 'fge_get_cities' ) ) {
					foreach ( array_keys( fge_get_cities() ) as $slug ) {
						$list[] = [ 'loc' => home_url( '/golf-events/' . $slug . '/' ) ];
					}
				}

				// Format-Landingpages.
				if ( function_exists( 'fge_get_event_format_pages' ) ) {
					foreach ( array_keys( fge_get_event_format_pages() ) as $slug ) {
						$list[] = [ 'loc' => home_url( '/firmenevent/' . $slug . '/' ) ];
					}
				}

				// Öffentlich sichtbare Golfplatz-Partner.
				$partners = get_posts( [
					'post_type'   => 'firmengolf_partner',
					'post_status' => 'publish',
					'numberposts' => -1,
					'fields'      => 'ids',
				] );
				foreach ( $partners as $pid ) {
					if ( function_exists( 'fge_partner_is_public' ) && fge_partner_is_public( (int) $pid ) ) {
						$list[] = [ 'loc' => get_permalink( (int) $pid ) ];
					}
				}

				return $list;
			}

			public function get_max_num_pages( $object_subtype = '' ) {
				return 1;
			}
		}
	}

	wp_register_sitemap_provider( 'fgelandings', new FGE_Landing_Sitemap_Provider() );
}, 99 );
