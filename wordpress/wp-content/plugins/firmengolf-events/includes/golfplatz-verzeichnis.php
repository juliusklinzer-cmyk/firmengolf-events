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

/** Gesamtzahl der Verzeichnis-Einträge (für „730 Golfplätze"-Claims). */
function fge_verzeichnis_count(): int {
	global $wpdb;
	$table = fge_verzeichnis_table();
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
