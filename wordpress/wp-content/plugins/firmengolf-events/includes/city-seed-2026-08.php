<?php
/**
 * Städte-Rollout 2026-08: Selbstplaner-Events für die 22 neuen City-Landingpages.
 *
 * Klont die 7 München-Master (Teamevent, Schnupperkurs, Firmenturnier, Platzreife,
 * Kundenevent, Workshop, After-Work) zur Laufzeit in jede neue Stadt, wie beim
 * ersten Regional-Rollout: Inhalte 1:1, nur Titel, Slug, Stadt/Region/Ort und
 * Geo-Koordinaten werden getauscht. Läuft option-gegated nach dem Deploy
 * (Live-System nur per FTPS, kein WP-CLI) und ist idempotent: existiert der
 * Ziel-Slug schon, wird nichts angelegt.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Neue Städte des Rollouts 2026-08: slug => [Anzeigename, Ortsangabe, lat, lng]. */
function fge_cityseed26_cities(): array {
	return [
		'augsburg'               => [ 'Augsburg', 'Raum Augsburg', '48.371', '10.898' ],
		'bonn'                   => [ 'Bonn', 'Raum Bonn', '50.737', '7.098' ],
		'bremen'                 => [ 'Bremen', 'Raum Bremen', '53.079', '8.802' ],
		'dortmund'               => [ 'Dortmund', 'Raum Dortmund', '51.514', '7.465' ],
		'dresden'                => [ 'Dresden', 'Raum Dresden', '51.050', '13.737' ],
		'erlangen'               => [ 'Erlangen', 'Raum Erlangen', '49.590', '11.012' ],
		'essen'                  => [ 'Essen', 'Raum Essen', '51.456', '7.012' ],
		'garmisch-partenkirchen' => [ 'Garmisch-Partenkirchen', 'Raum Garmisch-Partenkirchen', '47.492', '11.096' ],
		'ingolstadt'             => [ 'Ingolstadt', 'Raum Ingolstadt', '48.766', '11.426' ],
		'itzehoe'                => [ 'Itzehoe', 'Raum Itzehoe', '53.925', '9.516' ],
		'karlsruhe'              => [ 'Karlsruhe', 'Raum Karlsruhe', '49.007', '8.404' ],
		'kiel'                   => [ 'Kiel', 'Raum Kiel', '54.323', '10.123' ],
		'landshut'               => [ 'Landshut', 'Raum Landshut', '48.537', '12.151' ],
		'leipzig'                => [ 'Leipzig', 'Raum Leipzig', '51.340', '12.373' ],
		'luebeck'                => [ 'Lübeck', 'Raum Lübeck', '53.866', '10.687' ],
		'lueneburg'              => [ 'Lüneburg', 'Raum Lüneburg', '53.246', '10.412' ],
		'mannheim'               => [ 'Mannheim', 'Raum Mannheim', '49.488', '8.466' ],
		'penzberg'               => [ 'Penzberg', 'Raum Penzberg', '47.752', '11.377' ],
		'regensburg'             => [ 'Regensburg', 'Raum Regensburg', '49.013', '12.102' ],
		'rosenheim'              => [ 'Rosenheim', 'Raum Rosenheim', '47.856', '12.129' ],
		'ulm'                    => [ 'Ulm', 'Raum Ulm', '48.401', '9.988' ],
		'wuerzburg'              => [ 'Würzburg', 'Raum Würzburg', '49.791', '9.953' ],
	];
}

/** Slugs der München-Master, die in jede neue Stadt geklont werden. */
function fge_cityseed26_master_slugs(): array {
	return [
		'golf-teamevent-in-muenchen',
		'golf-schnupperkurs-fuer-teams-in-muenchen',
		'firmen-golfturnier-in-muenchen',
		'platzreifekurs-in-muenchen',
		'kundenevent-auf-dem-golfplatz-in-muenchen',
		'workshop-auf-dem-golfplatz-in-muenchen',
		'after-work-golf-in-muenchen',
	];
}

/** Klont einen München-Master in eine Stadt (Inhalt 1:1, Ortsdaten getauscht). */
function fge_cityseed26_clone_master( WP_Post $master, string $slug, array $city ): void {
	list( $name, $venue, $lat, $lng ) = $city;
	$post_name = str_replace( '-in-muenchen', '-in-' . $slug, $master->post_name );
	if ( get_page_by_path( $post_name, OBJECT, 'firmengolf_event' ) ) {
		return; // schon angelegt
	}

	$id = wp_insert_post( [
		'post_type'    => 'firmengolf_event',
		'post_status'  => $master->post_status,
		'post_title'   => str_replace( 'München', $name, $master->post_title ),
		'post_name'    => $post_name,
		'post_content' => $master->post_content,
		'post_excerpt' => $master->post_excerpt,
	] );
	if ( ! $id || is_wp_error( $id ) ) {
		return;
	}

	$skip = [ '_fge_views_count', '_fge_requests_count', '_edit_lock', '_edit_last' ];
	foreach ( get_post_meta( $master->ID ) as $key => $values ) {
		if ( in_array( $key, $skip, true ) ) {
			continue;
		}
		update_post_meta( $id, $key, maybe_unserialize( $values[0] ) );
	}
	$overrides = [
		'_fge_city'           => $name,
		'_fge_region'         => $name,
		'_fge_event_location' => $venue,
		'_fge_geo_lat'        => $lat,
		'_fge_geo_lng'        => $lng,
	];
	foreach ( $overrides as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	// Der After-Work-Master hat seinen Netto-Betrag verloren (Stand 2026-08-20,
	// alle älteren Regional-Klone tragen 49.17): Loch beim Klonen wieder stopfen,
	// sonst zeigt der Klon "Auf Anfrage" statt des kalibrierten Preises.
	if ( 'after_work_golf' === get_post_meta( $id, '_fge_event_type', true ) && '' === (string) get_post_meta( $id, '_fge_price_amount', true ) ) {
		update_post_meta( $id, '_fge_price_amount', '49.17' );
	}
	if ( function_exists( 'fge_event_price_label' ) ) {
		$pr = fge_event_pricing( (int) $id );
		update_post_meta( $id, '_fge_sale_price_net', $pr['gross'] );
		update_post_meta( $id, '_fge_public_price_label', fge_event_price_label( (int) $id ) );
	}
}

add_action( 'init', static function () {
	if ( get_option( 'fge_city_seed_2026_08' ) ) {
		return;
	}
	update_option( 'fge_city_seed_2026_08', '1', true );

	$masters = [];
	foreach ( fge_cityseed26_master_slugs() as $master_slug ) {
		$master = get_page_by_path( $master_slug, OBJECT, 'firmengolf_event' );
		if ( $master instanceof WP_Post ) {
			$masters[] = $master;
		}
	}
	foreach ( fge_cityseed26_cities() as $slug => $city ) {
		foreach ( $masters as $master ) {
			fge_cityseed26_clone_master( $master, $slug, $city );
		}
	}
}, 21 );
