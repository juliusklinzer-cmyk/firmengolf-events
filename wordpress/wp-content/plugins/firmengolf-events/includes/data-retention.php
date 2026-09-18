<?php
/**
 * Automatische Löschung alter Anfragen (Audit 2026-08-12: die Datenschutz-
 * erklärung versprach Löschung nach Zweckerfüllung, im Code gab es dafür keine
 * Routine — löschen ging nur von Hand).
 *
 * Regel: Anfragen ohne Auftrag werden 24 Monate nach der letzten Änderung
 * vollständig gelöscht. Anfragen, aus denen ein Auftrag wurde, bleiben wegen
 * der handels- und steuerrechtlichen Aufbewahrungsfristen unberührt und werden
 * bewusst NICHT automatisch entfernt.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Aufbewahrung nicht-geschäftsrelevanter Anfragen in Tagen (24 Monate). */
const FGE_REQUEST_RETENTION_DAYS = 730;

/**
 * Status, die einen geschäftsrelevanten Vorgang markieren. Diese Anfragen fallen
 * unter gesetzliche Aufbewahrungsfristen und werden nicht automatisch gelöscht.
 */
function fge_retention_kept_statuses(): array {
	return [
		'angebot_angenommen',
		'event_durchgefuehrt',
		'rechnung_in_lexoffice_erstellt',
		'abgeschlossen',
	];
}

/**
 * Löscht fällige Anfragen. Läuft täglich per Cron, arbeitet in Blöcken, damit
 * ein großer Rückstand den Request nicht sprengt.
 *
 * @return int Anzahl gelöschter Anfragen.
 */
function fge_retention_purge_requests( int $batch = 200 ): int {
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - FGE_REQUEST_RETENTION_DAYS * DAY_IN_SECONDS );

	$ids = get_posts( [
		'post_type'        => 'firmengolf_request',
		'post_status'      => 'any',
		'posts_per_page'   => $batch,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => true,
		'date_query'       => [
			[ 'column' => 'post_modified_gmt', 'before' => $cutoff ],
		],
		'meta_query'       => [
			'relation' => 'OR',
			[ 'key' => '_fge_request_status', 'value' => fge_retention_kept_statuses(), 'compare' => 'NOT IN' ],
			[ 'key' => '_fge_request_status', 'compare' => 'NOT EXISTS' ],
		],
	] );

	$deleted = 0;
	foreach ( $ids as $id ) {
		// force_delete: kein Papierkorb, sonst bleiben die Kontaktdaten liegen.
		if ( wp_delete_post( (int) $id, true ) ) {
			$deleted++;
		}
	}
	return $deleted;
}

add_action( 'fge_daily_retention', static function (): void {
	fge_retention_purge_requests();
} );

add_action( 'init', static function (): void {
	if ( ! wp_next_scheduled( 'fge_daily_retention' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'fge_daily_retention' );
	}
} );

// Cron beim Deaktivieren entfernen (Audit 18.09.2026).
register_deactivation_hook( FGE_DIR . 'firmengolf-events.php', static function (): void {
	$ts = wp_next_scheduled( 'fge_daily_retention' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'fge_daily_retention' );
	}
} );
