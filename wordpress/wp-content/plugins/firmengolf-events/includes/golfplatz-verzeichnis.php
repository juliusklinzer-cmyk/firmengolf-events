<?php
/**
 * Golfplatz-Verzeichnis: alle deutschen Golfplätze (DGV-Faktendaten) für
 * City-Seiten-Listen + Karte. Eigene Tabelle statt CPT — reine Verzeichnisdaten,
 * bewusst getrennt vom Partner-System. Verknüpfung zu Partnern über partner_id.
 *
 * Rechtlicher Rahmen (2026-07): NUR Fakten (Name, Adresse, Koordinaten, Löcher,
 * Kontakt) — keine Fotos/Logos/Beschreibungstexte aus der DGV-Quelle übernehmen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fge_verzeichnis_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'fge_golfplaetze';
}

/** Tabelle idempotent anlegen (Import-Skript + Fallback). */
function fge_verzeichnis_create_table(): void {
	global $wpdb;
	$table   = fge_verzeichnis_table();
	$charset = $wpdb->get_charset_collate();
	$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		dgv_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		name VARCHAR(190) NOT NULL,
		plz VARCHAR(10) NOT NULL DEFAULT '',
		ort VARCHAR(120) NOT NULL DEFAULT '',
		strasse VARCHAR(190) NOT NULL DEFAULT '',
		bundesland VARCHAR(60) NOT NULL DEFAULT '',
		lat DECIMAL(9,6) NOT NULL DEFAULT 0,
		lng DECIMAL(9,6) NOT NULL DEFAULT 0,
		loecher VARCHAR(20) NOT NULL DEFAULT '',
		website VARCHAR(190) NOT NULL DEFAULT '',
		telefon VARCHAR(60) NOT NULL DEFAULT '',
		schnupperkurse TINYINT(1) NOT NULL DEFAULT 0,
		platzreifekurse TINYINT(1) NOT NULL DEFAULT 0,
		driving_range TINYINT(1) NOT NULL DEFAULT 0,
		restaurant TINYINT(1) NOT NULL DEFAULT 0,
		partner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY dgv_id (dgv_id),
		KEY latlng (lat, lng),
		KEY partner_id (partner_id)
	) {$charset}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/** Koordinaten der City-Landingpages (Stadtzentren) für die Umkreis-Liste. */
function fge_city_coords(): array {
	return [
		'muenchen'    => [ 48.1372, 11.5756 ],
		'hamburg'     => [ 53.5511, 9.9937 ],
		'koeln'       => [ 50.9375, 6.9603 ],
		'stuttgart'   => [ 48.7758, 9.1829 ],
		'berlin'      => [ 52.5200, 13.4050 ],
		'frankfurt'   => [ 50.1109, 8.6821 ],
		'duesseldorf' => [ 51.2277, 6.7735 ],
		'tegernsee'   => [ 47.7124, 11.7580 ],
		'augsburg'               => [ 48.3705, 10.8978 ],
		'bonn'                   => [ 50.7374, 7.0982 ],
		'bremen'                 => [ 53.0793, 8.8017 ],
		'dortmund'               => [ 51.5136, 7.4653 ],
		'dresden'                => [ 51.0504, 13.7373 ],
		'erlangen'               => [ 49.5897, 11.0120 ],
		'essen'                  => [ 51.4556, 7.0116 ],
		'garmisch-partenkirchen' => [ 47.4917, 11.0955 ],
		'ingolstadt'             => [ 48.7665, 11.4258 ],
		'itzehoe'                => [ 53.9252, 9.5163 ],
		'karlsruhe'              => [ 49.0069, 8.4037 ],
		'kiel'                   => [ 54.3233, 10.1228 ],
		'landshut'               => [ 48.5370, 12.1508 ],
		'leipzig'                => [ 51.3397, 12.3731 ],
		'luebeck'                => [ 53.8655, 10.6866 ],
		'lueneburg'              => [ 53.2464, 10.4115 ],
		'mannheim'               => [ 49.4875, 8.4660 ],
		'penzberg'               => [ 47.7522, 11.3772 ],
		'regensburg'             => [ 49.0134, 12.1016 ],
		'rosenheim'              => [ 47.8561, 12.1289 ],
		'ulm'                    => [ 48.4011, 9.9876 ],
		'wuerzburg'              => [ 49.7913, 9.9534 ],
	];
}

/**
 * Golfplätze im Umkreis, nach Entfernung sortiert.
 *
 * @return array<int,object> Zeilen mit ->dist (km) angereichert.
 */
function fge_verzeichnis_nearby( float $lat, float $lng, int $radius_km = 60, int $limit = 25 ): array {
	global $wpdb;
	$table = fge_verzeichnis_table();
	// Bounding-Box vorab (nutzt den latlng-Index), exakte Distanz danach in PHP.
	$dlat = $radius_km / 111.0;
	$dlng = $radius_km / ( 111.0 * max( 0.2, cos( deg2rad( $lat ) ) ) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f AND lat != 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$lat - $dlat,
		$lat + $dlat,
		$lng - $dlng,
		$lng + $dlng
	) );
	if ( ! $rows ) {
		return [];
	}
	$out = [];
	foreach ( $rows as $r ) {
		$dist = function_exists( 'fge_geo_distance' )
			? fge_geo_distance( $lat, $lng, (float) $r->lat, (float) $r->lng )
			: 111.0 * sqrt( pow( $lat - (float) $r->lat, 2 ) + pow( ( $lng - (float) $r->lng ) * cos( deg2rad( $lat ) ), 2 ) );
		if ( $dist <= $radius_km ) {
			$r->dist = $dist;
			$out[]   = $r;
		}
	}
	usort( $out, static fn( $a, $b ) => $a->dist <=> $b->dist );
	return array_slice( $out, 0, $limit );
}

/**
 * City-Seiten: Klaro-gegatete Google-Karte mit den Plätzen der Region.
 * Nutzt dasselbe Gating wie die Onboarding-Karte (script_loader_tag am Handle
 * 'google-maps' in der Theme-functions.php) — lädt erst nach Einwilligung.
 */
add_action( 'wp_enqueue_scripts', function (): void {
	if ( ! function_exists( 'fge_gmaps_api_key' ) ) {
		return;
	}
	$key = fge_gmaps_api_key();
	if ( '' === $key ) {
		return;
	}

	$coords = null;
	$radius = 60;

	// Fall 1: City-Landingpage (nicht die Format×Stadt-Kombi).
	$city = (string) get_query_var( 'fge_city' );
	if ( '' !== $city && ! get_query_var( 'fge_format' ) ) {
		$coords = fge_city_coords()[ $city ] ?? null;
	}

	// Fall 2: Platzhalter-Event ohne zugeordneten Golfplatz → Karte der
	// infrage kommenden Plätze rund um die Event-Region (Julius, 2026-07-03).
	if ( ! $coords && is_singular( 'firmengolf_event' ) ) {
		$eid = get_the_ID();
		$pid = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
		$lat = (float) get_post_meta( $eid, '_fge_geo_lat', true );
		$lng = (float) get_post_meta( $eid, '_fge_geo_lng', true );
		if ( 0 === $pid && $lat && $lng ) {
			$coords = [ $lat, $lng ];
			$radius = 45;
		}
	}

	// Fall 2b: Event MIT zugeordnetem Golfplatz → ein einzelner blauer Fahnen-Pin auf
	// den Partnerplatz statt des roten Standard-Pins der iframe-Karte (Julius, 2026-07-06).
	$self_place = null;
	if ( ! $coords && is_singular( 'firmengolf_event' ) ) {
		$eid = get_the_ID();
		$pid = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
		if ( $pid > 0 ) {
			$plat = (float) get_post_meta( $pid, '_fge_latitude', true );
			$plng = (float) get_post_meta( $pid, '_fge_longitude', true );
			if ( ! ( $plat && $plng ) ) {
				// Bestands-Partner ohne Meta-Koordinaten: das Verzeichnis kennt sie (wie Fall 3).
				global $wpdb;
				$row = $wpdb->get_row( $wpdb->prepare(
					'SELECT lat, lng FROM ' . fge_verzeichnis_table() . ' WHERE partner_id = %d LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$pid
				) );
				if ( $row ) {
					$plat = (float) $row->lat;
					$plng = (float) $row->lng;
				}
			}
			if ( ! ( $plat && $plng ) ) {
				$plat = (float) get_post_meta( $eid, '_fge_geo_lat', true );
				$plng = (float) get_post_meta( $eid, '_fge_geo_lng', true );
			}
			if ( $plat && $plng ) {
				$coords     = [ $plat, $plng ];
				$self_place = [
					'id'      => 0,
					'name'    => (string) get_post_meta( $pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $pid ),
					'lat'     => $plat,
					'lng'     => $plng,
					'partner' => true,
					'meta'    => (string) get_post_meta( $pid, '_fge_city', true ),
				];
			}
		}
	}

	// Fall 3: öffentliche Golfplatz-Seite → nur der eigene Pin (Partner-Pin, blau mit Fahne).
	if ( ! $coords && is_singular( 'firmengolf_partner' ) ) {
		$pid  = get_the_ID();
		$plat = (float) get_post_meta( $pid, '_fge_latitude', true );
		$plng = (float) get_post_meta( $pid, '_fge_longitude', true );
		if ( ! ( $plat && $plng ) ) {
			// Die 20 Bestands-Partner haben keine Meta-Koordinaten — das DGV-Verzeichnis
			// (verlinkt über partner_id) kennt sie aber.
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare(
				'SELECT lat, lng FROM ' . fge_verzeichnis_table() . ' WHERE partner_id = %d LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$pid
			) );
			if ( $row ) {
				$plat = (float) $row->lat;
				$plng = (float) $row->lng;
			}
		}
		if ( $plat && $plng ) {
			$coords     = [ $plat, $plng ];
			$self_place = [
				'id'      => 0,
				'name'    => (string) get_post_meta( $pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $pid ),
				'lat'     => $plat,
				'lng'     => $plng,
				'partner' => true,
				'meta'    => (string) get_post_meta( $pid, '_fge_city', true ),
			];
		}
	}

	if ( ! $coords ) {
		return;
	}
	$places = [];
	if ( $self_place ) {
		$places[] = $self_place;
	} else {
	foreach ( fge_verzeichnis_nearby( $coords[0], $coords[1], $radius, 80 ) as $gp ) {
		// Partner-Foto fürs Auswahl-Panel der City-Seiten: Hero-Bild des verknüpften
		// Partner-Profils (nur Partner haben Fotos, das stellt sie sichtbar nach vorn).
		$gp_photo = '';
		if ( 1 === (int) $gp->ist_partner && (int) $gp->partner_id > 0 ) {
			$gp_aid = (int) get_post_meta( (int) $gp->partner_id, '_fge_hero_image_attachment_id', true );
			if ( $gp_aid ) {
				$gp_photo = (string) wp_get_attachment_image_url( $gp_aid, 'medium_large' );
			}
		}
		$places[] = [
			'id'      => (int) $gp->id,
			'name'    => $gp->name,
			'lat'     => (float) $gp->lat,
			'lng'     => (float) $gp->lng,
			// Quelle der Wahrheit für „Partner": das Vertrags-Flag aus Julius' Liste,
			// nicht der CPT-Link (dort können Alt-Einträge liegen, s. Maxlrain 2026-07).
			'partner' => 1 === (int) $gp->ist_partner,
			'meta'    => $gp->ort . ' · ' . round( $gp->dist ) . ' km',
			'holes'   => (string) $gp->loecher,
			'photo'   => $gp_photo,
		];
	}
	}
	if ( ! $places ) {
		return;
	}
	$src = plugins_url( 'assets/js/fge-city-map.js', FGE_DIR . 'firmengolf-events.php' );
	wp_enqueue_script( 'fge-city-map', $src, [], FGE_VERSION, true );
	wp_localize_script( 'fge-city-map', 'FGE_CITY_MAP', [
		'lat'    => $coords[0],
		'lng'    => $coords[1],
		'places' => $places,
	] );
	$maps_url = add_query_arg(
		[ 'key' => rawurlencode( $key ), 'callback' => 'fgeCityMapInit', 'loading' => 'async', 'language' => 'de', 'region' => 'DE' ],
		'https://maps.googleapis.com/maps/api/js'
	);
	wp_enqueue_script( 'google-maps', $maps_url, [ 'fge-city-map' ], null, true );
} );

/** Gesamtzahl der Verzeichnis-Einträge (für „730 Golfplätze"-Claims). */
function fge_verzeichnis_count(): int {
	global $wpdb;
	$table = fge_verzeichnis_table();
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Hält das Verzeichnis mit dem Partner-System synchron: Wird ein Partner
 * freigeschaltet (Status aktiv), bekommt sein Platz im Verzeichnis das
 * Partner-Flag (blauer Pin auf allen Karten + „Partner"-Badge in den Listen).
 * Match per bereits verknüpfter partner_id, sonst PLZ + Namensähnlichkeit;
 * ohne Treffer wird eine neue Zeile angelegt (synthetische dgv_id, kein DGV-Import).
 * Pausiert/abgelehnt nimmt das Flag wieder weg, die Zeile bleibt.
 */
function fge_verzeichnis_sync_partner( int $partner_id, string $status ): void {
	global $wpdb;
	if ( $partner_id <= 0 ) {
		return;
	}
	$table = fge_verzeichnis_table();
	$is    = ( 'aktiv' === $status ) ? 1 : 0;

	$row_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE partner_id = %d LIMIT 1", $partner_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $row_id > 0 ) {
		$wpdb->update( $table, [ 'ist_partner' => $is ], [ 'id' => $row_id ], [ '%d' ], [ '%d' ] );
		return;
	}
	if ( 1 !== $is ) {
		return; // nie verknüpft und nicht aktiv → nichts zu tun
	}

	$m    = static fn( string $k ): string => (string) get_post_meta( $partner_id, '_fge_' . $k, true );
	$name = $m( 'public_golfclub_name' ) ?: get_the_title( $partner_id );
	$plz  = $m( 'postal_code' );
	$ort  = $m( 'city' );

	$norm = static function ( string $s ): string {
		$s = mb_strtolower( $s );
		$s = str_replace( [ 'ä', 'ö', 'ü', 'ß' ], [ 'ae', 'oe', 'ue', 'ss' ], $s );
		$s = (string) preg_replace( '/\b(golfclub|golf club|golfpark|golfanlage|golfplatz|golf|club|gc|e v|ev)\b/', '', $s );
		return trim( (string) preg_replace( '/[^a-z0-9]+/', '', $s ) );
	};

	// Kandidaten mit gleicher PLZ (oder gleichem Ort) auf Namensähnlichkeit prüfen.
	$cands = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, name FROM {$table} WHERE partner_id = 0 AND ( plz = %s OR ort = %s )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$plz,
		$ort
	) );
	$needle = $norm( $name );
	foreach ( $cands as $c ) {
		$hay = $norm( (string) $c->name );
		if ( '' !== $needle && '' !== $hay && ( str_contains( $hay, $needle ) || str_contains( $needle, $hay ) ) ) {
			$wpdb->update( $table, [ 'partner_id' => $partner_id, 'ist_partner' => 1 ], [ 'id' => (int) $c->id ], [ '%d', '%d' ], [ '%d' ] );
			return;
		}
	}

	// Kein DGV-Treffer → eigene Zeile anlegen (synthetische dgv_id, kollisionsfrei > Import-Bereich).
	$wpdb->insert( $table, [
		'dgv_id'      => 900000 + $partner_id,
		'name'        => $name,
		'plz'         => $plz,
		'ort'         => $ort,
		'strasse'     => trim( $m( 'street' ) . ' ' . $m( 'house_number' ) ),
		'bundesland'  => $m( 'federal_state' ),
		'lat'         => (float) $m( 'latitude' ),
		'lng'         => (float) $m( 'longitude' ),
		'website'     => $m( 'website_url' ),
		'partner_id'  => $partner_id,
		'ist_partner' => 1,
	], [ '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%d' ] );
}
