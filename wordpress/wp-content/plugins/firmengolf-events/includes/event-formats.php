<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fge_get_event_formats(): array {
	return [
		'standard' => [
			'teamevent'          => 'Teamevent',
			'after_work_golf'    => 'After-Work Golf',
			'schnupperkurs'      => 'Schnupperkurs',
			'kundenevent'        => 'Kundenevent',
			'gesundheitstag'     => 'Gesundheitstag',
			'offsite'            => 'Offsite',
			'networking'         => 'Networking',
			'firmen_golfturnier' => 'Firmen-Golfturnier',
			'nacht_event'        => 'Nacht-Event',
			'andere'             => 'Andere',
		],
		'on_request' => [
			'sommerfest'      => 'Sommerfest',
			'tagung'          => 'Tagung',
			'firmenjubilaeum' => 'Firmenjubiläum',
			'kickoff'         => 'Kick-off-Veranstaltung',
			'incentive'       => 'Incentive',
			'charity_csr'     => 'Charity- / CSR-Event',
			'sponsoring'      => 'Sponsoring-Event',
		],
	];
}

function fge_get_event_formats_flat( bool $include_on_request = true ): array {
	$tiers = fge_get_event_formats();
	return $include_on_request
		? $tiers['standard'] + $tiers['on_request']
		: $tiers['standard'];
}

/**
 * Format-Slug→Label nur für Formate, die mindestens ein öffentlich sichtbares
 * Event haben. Reihenfolge folgt dem Katalog (inkl. on_request, daher z. B. Incentive).
 * Verhindert tote Filter-Chips und nimmt neue Typen automatisch auf.
 */
function fge_event_formats_in_use(): array {
	$cached = get_transient( 'fge_formats_in_use' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = 'firmengolf_event' AND p.post_status = 'publish'"
	);
	if ( $ids ) {
		update_meta_cache( 'post', $ids ); // sonst N+1 in der Schleife unten
	}
	$catalog = fge_get_event_formats_flat( true );
	$legacy  = fge_get_event_format_legacy_map();
	$present = [];
	foreach ( $ids as $id ) {
		if ( function_exists( 'fge_event_is_public' ) && ! fge_event_is_public( (int) $id ) ) {
			continue;
		}
		$type = (string) get_post_meta( (int) $id, '_fge_event_type', true );
		$key  = isset( $catalog[ $type ] ) ? $type : ( $legacy[ $type ] ?? '' );
		if ( '' !== $key && isset( $catalog[ $key ] ) ) {
			$present[ $key ] = true;
		}
	}
	$out = [];
	foreach ( $catalog as $slug => $label ) {
		if ( isset( $present[ $slug ] ) ) {
			$out[ $slug ] = $label;
		}
	}
	set_transient( 'fge_formats_in_use', $out, DAY_IN_SECONDS );
	return $out;
}

/** Cache der „benutzten Formate" leeren, wenn sich Events oder ihr Status ändern. */
function fge_flush_formats_in_use(): void {
	delete_transient( 'fge_formats_in_use' );
}
add_action( 'save_post_firmengolf_event', 'fge_flush_formats_in_use' );
add_action( 'deleted_post', static function ( $pid ) {
	if ( get_post_type( (int) $pid ) === 'firmengolf_event' ) {
		fge_flush_formats_in_use();
	}
} );
add_action( 'updated_post_meta', static function ( $mid, $pid, $key ) {
	if ( '_fge_event_status' === $key ) {
		fge_flush_formats_in_use();
	}
}, 10, 3 );

function fge_format_event_type( string $key ): string {
	if ( $key === '' ) {
		return '';
	}
	$all = fge_get_event_formats_flat();
	if ( isset( $all[ $key ] ) ) {
		return $all[ $key ];
	}
	$legacy = fge_get_event_format_legacy_map();
	if ( isset( $legacy[ $key ], $all[ $legacy[ $key ] ] ) ) {
		return $all[ $legacy[ $key ] ];
	}
	return '';
}

function fge_is_on_request_format( string $key ): bool {
	$tiers = fge_get_event_formats();
	return isset( $tiers['on_request'][ $key ] );
}

function fge_get_event_format_legacy_map(): array {
	return [
		'firmenturnier'       => 'firmen_golfturnier',
		'team-building'       => 'teamevent',
		'schnupperkurs'       => 'teamevent',
		'coaching'            => 'after_work_golf',
		'anderes_event'       => 'andere',
		'weihnachtsfeier'     => 'andere',
		'schnuppergolf'       => 'after_work_golf',
		'team_challenge'      => 'teamevent',
		'putting_challenge'   => 'after_work_golf',
		'range_training'      => 'after_work_golf',
		'kurzspiel_challenge' => 'after_work_golf',
		'9hole_turnier'       => 'firmen_golfturnier',
		'18hole_turnier'      => 'firmen_golfturnier',
		'offsite_mit_meeting' => 'offsite',
		'azubi_event'         => 'andere',
		'individuelles_event' => 'andere',
	];
}
