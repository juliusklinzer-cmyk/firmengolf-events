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

// ── Angebots-Followups: Kunden-Erinnerung + Eskalation an Firmengolf ──────────
add_action( 'fge_request_followups_cron', 'fge_offer_run_followups' );
function fge_offer_run_followups(): array {
	$stats = [ 'offer_reminded' => 0, 'offer_escalated' => 0 ];

	$reqs = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_fge_offer_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] );

	$reminder_days = (int) apply_filters( 'fge_offer_reminder_days', 3 );

	foreach ( $reqs as $req ) {
		if ( 'pending' !== (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
			continue;
		}
		$sent_at = strtotime( (string) get_post_meta( $req, '_fge_last_status_change', true ) ?: get_post_field( 'post_date', $req ) ?: 'now' );

		// Einmalige Erinnerung an den Kunden.
		if ( time() >= $sent_at + $reminder_days * DAY_IN_SECONDS && '1' !== (string) get_post_meta( $req, '_fge_offer_reminded', true ) ) {
			if ( function_exists( 'fge_send_offer_reminder' ) && fge_send_offer_reminder( $req ) ) {
				update_post_meta( $req, '_fge_offer_reminded', 1 );
				$stats['offer_reminded']++;
			}
		}

		// Einmalige Eskalation an Firmengolf nach Ablauf der Angebotsfrist.
		$deadline = (int) get_post_meta( $req, '_fge_offer_deadline', true );
		if ( $deadline && time() > $deadline && '1' !== (string) get_post_meta( $req, '_fge_offer_escalated', true ) ) {
			$ref = function_exists( 'fge_request_number' ) ? fge_request_number( $req ) : 'FG-' . $req;
			$to  = apply_filters( 'fge_internal_email', fge_company_internal_email() );
			$admin = function_exists( 'fge_format_request_admin_link' ) ? fge_format_request_admin_link( $req ) : admin_url();
			$content = '<p style="margin:0 0 16px;">Das Angebot <strong>' . esc_html( $ref ) . '</strong> ist seit der Frist offen, der Kunde hat noch nicht zugesagt. Bitte nachfassen.</p>'
				. '<p style="margin:0;"><a href="' . esc_url( $admin ) . '">Anfrage im Admin öffnen</a></p>';
			if ( function_exists( 'fge_email_wrap' ) ) {
				wp_mail( $to, 'Angebot überfällig: ' . $ref, fge_email_wrap( 'Angebot überfällig: ' . $ref, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
			}
			update_post_meta( $req, '_fge_offer_escalated', 1 );
			$stats['offer_escalated']++;
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
