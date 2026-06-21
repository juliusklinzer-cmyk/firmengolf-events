<?php
/**
 * Geo / Umkreissuche für die Events-Seite.
 * PLZ-Zentroide aus geo-data.php (GeoNames, CC-BY). Alles offline/serverseitig.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Lazy-load the PLZ dataset: [ '80331' => [lat, lng, 'Ort'], ... ]. */
function fge_geo_data(): array {
	static $data = null;
	if ( $data === null ) {
		$file = FGE_DIR . 'includes/geo-data.php';
		$data = is_readable( $file ) ? require $file : [];
		if ( ! is_array( $data ) ) {
			$data = [];
		}
	}
	return $data;
}

/** PLZ → [lat, lng, ort] or null. */
function fge_geo_lookup_plz( string $plz ): ?array {
	$plz  = preg_replace( '/\D/', '', $plz );
	$data = fge_geo_data();
	return ( $plz !== '' && isset( $data[ $plz ] ) ) ? $data[ $plz ] : null;
}

/** Luftlinie (Haversine) in km zwischen zwei Koordinaten. */
function fge_geo_distance( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
	$r    = 6371.0;
	$dlat = deg2rad( $lat2 - $lat1 );
	$dlng = deg2rad( $lng2 - $lng1 );
	$a    = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) ** 2;
	return $r * 2 * asin( min( 1.0, sqrt( $a ) ) );
}

/** Stadt-/Regionsname → repräsentative Koordinaten (für Selbstplaner-Events ohne Partner-PLZ). */
function fge_geo_city_coords( string $place ): ?array {
	static $map = [
		'münchen'     => [ 48.137, 11.575 ],
		'muenchen'    => [ 48.137, 11.575 ],
		'hamburg'     => [ 53.551, 9.993 ],
		'köln'        => [ 50.938, 6.960 ],
		'koeln'       => [ 50.938, 6.960 ],
		'stuttgart'   => [ 48.776, 9.182 ],
		'berlin'      => [ 52.520, 13.405 ],
		'frankfurt'   => [ 50.110, 8.682 ],
		'düsseldorf'  => [ 51.225, 6.776 ],
		'duesseldorf' => [ 51.225, 6.776 ],
		'tegernsee'   => [ 47.713, 11.757 ],
	];
	$p = mb_strtolower( trim( $place ) );
	if ( '' === $p ) {
		return null;
	}
	if ( isset( $map[ $p ] ) ) {
		return $map[ $p ];
	}
	foreach ( $map as $key => $coords ) {
		if ( mb_strpos( $p, $key ) !== false ) {
			return $coords;
		}
	}
	return null;
}

/** Koordinaten eines Events: 1) eigene Geo-Meta, 2) Partner-PLZ, 3) Stadt/Region — gecacht. */
function fge_geo_event_coords( int $event_id ): ?array {
	$lat = get_post_meta( $event_id, '_fge_geo_lat', true );
	$lng = get_post_meta( $event_id, '_fge_geo_lng', true );
	if ( $lat !== '' && $lng !== '' ) {
		return [ (float) $lat, (float) $lng ];
	}
	// 1) Über zugeordneten Partner (PLZ).
	$partner_id = (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true );
	if ( $partner_id > 0 ) {
		$plz = (string) get_post_meta( $partner_id, '_fge_postal_code', true );
		$geo = $plz !== '' ? fge_geo_lookup_plz( $plz ) : null;
		if ( $geo ) {
			update_post_meta( $event_id, '_fge_geo_lat', $geo[0] );
			update_post_meta( $event_id, '_fge_geo_lng', $geo[1] );
			return [ $geo[0], $geo[1] ];
		}
	}
	// 2) Fallback: Stadt/Region des Events (Selbstplaner-Events „von Firmengolf organisiert").
	foreach ( [ '_fge_city', '_fge_region', '_fge_event_location' ] as $key ) {
		$coords = fge_geo_city_coords( (string) get_post_meta( $event_id, $key, true ) );
		if ( $coords ) {
			update_post_meta( $event_id, '_fge_geo_lat', $coords[0] );
			update_post_meta( $event_id, '_fge_geo_lng', $coords[1] );
			return $coords;
		}
	}
	return null;
}

/** Invalidate cached event coords when a partner address changes. */
add_action( 'updated_post_meta', static function ( $mid, $post_id, $meta_key ) {
	if ( $meta_key !== '_fge_postal_code' || get_post_type( $post_id ) !== 'firmengolf_partner' ) {
		return;
	}
	$events = get_posts( [
		'post_type'      => 'firmengolf_event',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [ [ 'key' => '_fge_assigned_partner_id', 'value' => (int) $post_id ] ],
	] );
	foreach ( $events as $eid ) {
		delete_post_meta( $eid, '_fge_geo_lat' );
		delete_post_meta( $eid, '_fge_geo_lng' );
	}
}, 10, 3 );

/** Autocomplete suggestions for a query (PLZ prefix or Ort substring). */
function fge_geo_suggest( string $q, int $limit = 8 ): array {
	$q = trim( $q );
	if ( mb_strlen( $q ) < 2 ) {
		return [];
	}
	$data = fge_geo_data();
	$out  = [];

	if ( ctype_digit( $q ) ) {
		foreach ( $data as $plz => $row ) {
			if ( strpos( $plz, $q ) === 0 ) {
				$out[] = [ 'plz' => $plz, 'ort' => $row[2], 'lat' => $row[0], 'lng' => $row[1], 'label' => $plz . ' ' . $row[2] ];
				if ( count( $out ) >= $limit ) {
					break;
				}
			}
		}
		return $out;
	}

	$seen = [];
	foreach ( $data as $plz => $row ) {
		$ort = $row[2];
		if ( mb_stripos( $ort, $q ) === false ) {
			continue;
		}
		$key = mb_strtolower( $ort );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$out[]        = [ 'plz' => $plz, 'ort' => $ort, 'lat' => $row[0], 'lng' => $row[1], 'label' => $ort ];
	}
	// Rank: prefix match first, then bigger places (more PLZ), then alphabetical.
	$weight = fge_geo_ort_weight();
	usort( $out, static function ( $a, $b ) use ( $q, $weight ) {
		$ap = ( mb_stripos( $a['ort'], $q ) === 0 ) ? 0 : 1;
		$bp = ( mb_stripos( $b['ort'], $q ) === 0 ) ? 0 : 1;
		if ( $ap !== $bp ) {
			return $ap - $bp;
		}
		$aw = $weight[ mb_strtolower( $a['ort'] ) ] ?? 0;
		$bw = $weight[ mb_strtolower( $b['ort'] ) ] ?? 0;
		if ( $aw !== $bw ) {
			return $bw - $aw;
		}
		return strcmp( $a['ort'], $b['ort'] );
	} );
	return array_slice( $out, 0, $limit );
}

/** Weight per Ort name = number of PLZ it spans (proxy for city size). */
function fge_geo_ort_weight(): array {
	static $weight = null;
	if ( $weight === null ) {
		$weight = [];
		foreach ( fge_geo_data() as $row ) {
			$k            = mb_strtolower( $row[2] );
			$weight[ $k ] = ( $weight[ $k ] ?? 0 ) + 1;
		}
	}
	return $weight;
}

// AJAX: location autocomplete.
add_action( 'wp_ajax_fge_geo_suggest',        'fge_ajax_geo_suggest' );
add_action( 'wp_ajax_nopriv_fge_geo_suggest', 'fge_ajax_geo_suggest' );
function fge_ajax_geo_suggest(): void {
	$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	wp_send_json_success( fge_geo_suggest( $q ) );
}
