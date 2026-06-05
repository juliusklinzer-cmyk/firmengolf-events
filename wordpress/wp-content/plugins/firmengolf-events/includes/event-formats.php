<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fge_get_event_formats(): array {
	return [
		'standard' => [
			'teamevent'          => 'Teamevent',
			'after_work_golf'    => 'After-Work Golf',
			'kundenevent'        => 'Kundenevent',
			'gesundheitstag'     => 'Gesundheitstag',
			'offsite'            => 'Offsite',
			'networking'         => 'Networking',
			'firmen_golfturnier' => 'Firmen-Golfturnier',
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
