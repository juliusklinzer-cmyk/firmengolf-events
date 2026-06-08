<?php
/**
 * Termin-Follow-ups (Cron): Erinnerungen an Nicht-Responder + Eskalation überfälliger
 * Anfragen an Firmengolf. Läuft täglich; jede Aktion ist einmalig gegated.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Täglichen Cron sicherstellen.
add_action( 'init', static function () {
	if ( ! wp_next_scheduled( 'fge_request_followups_cron' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'fge_request_followups_cron' );
	}
} );
add_action( 'fge_request_followups_cron', 'fge_request_run_followups' );

/**
 * Verarbeitet alle offenen Event-Anfragen: schickt fällige Erinnerungen und
 * eskaliert überfällige. Rückgabe: [ 'reminded' => n, 'escalated' => n ].
 */
function fge_request_run_followups(): array {
	$stats = [ 'reminded' => 0, 'escalated' => 0 ];

	$requests = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => [ [ 'key' => '_fge_request_type', 'value' => 'specific_event' ] ],
	] );

	$reminder_days = (int) apply_filters( 'fge_request_reminder_days', 2 );

	foreach ( $requests as $req ) {
		// Erledigt / übernommen → nichts tun.
		if ( function_exists( 'fge_rr_final_index' ) && fge_rr_final_index( $req ) > 0 ) {
			continue;
		}
		if ( function_exists( 'fge_request_is_taken_over' ) && fge_request_is_taken_over( $req ) ) {
			continue;
		}
		if ( ! function_exists( 'fge_rr_matrix' ) ) {
			continue;
		}
		$m = fge_rr_matrix( $req );
		if ( empty( $m['responders'] ) || ! empty( $m['all_responded'] ) ) {
			continue;
		}

		$created     = strtotime( (string) get_post_field( 'post_date', $req ) ?: 'now' );
		$reminder_at = $created + $reminder_days * DAY_IN_SECONDS;

		// ── B6: Erinnerungen an Nicht-Responder (einmal je Person) ──
		if ( time() >= $reminder_at ) {
			$sent = (array) get_post_meta( $req, '_fge_reminders_sent', true );
			$sent = array_map( 'intval', $sent );
			foreach ( $m['responders'] as $c ) {
				$cid = (int) $c['id'];
				if ( in_array( $cid, $sent, true ) ) {
					continue;
				}
				if ( fge_rr_contact_answered_any( $req, $cid ) ) {
					continue;
				}
				if ( function_exists( 'fge_send_contact_reminder' ) && fge_send_contact_reminder( $req, $c ) ) {
					$sent[] = $cid;
					$stats['reminded']++;
				}
			}
			update_post_meta( $req, '_fge_reminders_sent', array_values( array_unique( $sent ) ) );
		}

		// ── B5: Eskalation bei Überfälligkeit (einmalig) ──
		if ( function_exists( 'fge_request_is_overdue' ) && fge_request_is_overdue( $req )
			&& '1' !== (string) get_post_meta( $req, '_fge_overdue_notified', true ) ) {
			if ( function_exists( 'fge_notify_overdue' ) ) {
				fge_notify_overdue( $req );
			}
			update_post_meta( $req, '_fge_overdue_notified', 1 );
			$stats['escalated']++;
		}
	}

	return $stats;
}

// Cron beim Deaktivieren entfernen.
register_deactivation_hook( FGE_DIR . 'firmengolf-events.php', static function () {
	$ts = wp_next_scheduled( 'fge_request_followups_cron' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'fge_request_followups_cron' );
	}
} );
