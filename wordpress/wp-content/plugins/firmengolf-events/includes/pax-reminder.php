<?php
/**
 * Teilnehmerzahl-Erinnerung (Julius, 05.10.2026).
 *
 * § 4 Abs. 2 AGB: Die endgültige Teilnehmerzahl muss 14 Tage vor dem Termin stehen,
 * danach wird die gemeldete Zahl berechnet (Plätze haben ähnliche Fristen, sonst
 * bleibt Firmengolf auf Teilabsagen sitzen). Damit das für den Kunden fair bleibt,
 * fragt diese Mail 16 Tage vorher einmal nach, ob die Zahl noch passt.
 *
 * Cron täglich 08:00, alle angenommenen Angebote mit Termin in 15 bis 16 Tagen,
 * einmal je Anfrage (Gate _fge_pax_reminder_sent). Demo-Anfragen nie.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', static function (): void {
	if ( ! wp_next_scheduled( 'fge_pax_reminder_cron' ) ) {
		$first = new DateTime( 'tomorrow 08:00', wp_timezone() );
		wp_schedule_event( $first->getTimestamp(), 'daily', 'fge_pax_reminder_cron' );
	}
} );
add_action( 'fge_pax_reminder_cron', 'fge_pax_reminder_run' );
register_deactivation_hook( FGE_DIR . 'firmengolf-events.php', static function (): void {
	$ts = wp_next_scheduled( 'fge_pax_reminder_cron' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'fge_pax_reminder_cron' );
	}
} );

/** Alle passenden Buchungen einmalig erinnern. */
function fge_pax_reminder_run(): array {
	$stats = [ 'sent' => 0 ];
	if ( ! function_exists( 'fge_day_event_date' ) ) {
		return $stats;
	}
	$today = new DateTimeImmutable( wp_date( 'Y-m-d' ), wp_timezone() );
	$reqs  = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_fge_offer_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => 'accepted', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] );
	foreach ( $reqs as $req ) {
		if ( function_exists( 'fge_is_demo_request' ) && fge_is_demo_request( $req ) ) {
			continue;
		}
		if ( '' !== (string) get_post_meta( $req, '_fge_pax_reminder_sent', true ) ) {
			continue;
		}
		if ( in_array( (string) get_post_meta( $req, '_fge_request_status', true ), [ 'verloren', 'abgeschlossen', 'event_durchgefuehrt', 'angebot_abgelehnt', 'nicht_verfuegbar' ], true ) ) {
			continue;
		}
		$date = fge_day_event_date( $req );
		if ( null === $date ) {
			continue;
		}
		$days = (int) $today->diff( new DateTimeImmutable( $date, wp_timezone() ) )->format( '%r%a' );
		// Fenster 15 bis 16 Tage: holt einen verpassten Cron-Lauf nach, und der Kunde hat
		// immer mindestens einen Tag bis zum Stichtag. Kurzfristige Buchungen bekommen nichts.
		if ( $days < 15 || $days > 16 ) {
			continue;
		}
		if ( fge_send_pax_reminder( $req, $date ) ) {
			$stats['sent']++;
		}
	}
	return $stats;
}

/** Erinnerung an den Kunden: passt die Teilnehmerzahl noch? */
function fge_send_pax_reminder( int $req, string $date ): bool {
	$data = fge_get_request_email_data( $req );
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$pax  = (int) ( $snap['participants'] ?? 0 );
	if ( $pax <= 0 ) {
		$pax = (int) get_post_meta( $req, '_fge_expected_participants', true );
	}
	if ( '' === $data['contact_email'] || $pax <= 0 ) {
		return false;
	}
	$event_ts    = strtotime( $date . ' 12:00:00' );
	$event_label = wp_date( 'd.m.Y', $event_ts );
	$deadline    = wp_date( 'd.m.Y', strtotime( '-14 days', $event_ts ) );
	$ref         = function_exists( 'fge_request_number' ) ? fge_request_number( $req ) : '';
	$greet       = $data['first_name'] !== '' ? 'Hallo ' . esc_html( $data['first_name'] ) . ',' : 'Hallo,';
	$subject     = 'Passt die Teilnehmerzahl noch?' . ( '' !== $ref ? ' (' . $ref . ')' : '' );
	$content     = '
		<p style="margin:0 0 16px;">' . $greet . '</p>
		<p style="margin:0 0 16px;">euer Teamevent am ' . esc_html( $event_label ) . ' rückt näher. Aktuell planen wir mit <strong>' . $pax . ' Teilnehmern</strong>.</p>
		<p style="margin:0 0 16px;">Falls sich daran etwas ändert, antworte bitte bis zum <strong>' . esc_html( $deadline ) . '</strong> kurz auf diese Mail. Danach geben wir die Zahl verbindlich an den Platz weiter, und berechnet wird die zuletzt gemeldete Teilnehmerzahl.</p>
		<p style="margin:0 0 16px;">Wenn alles passt, musst du nichts tun.</p>
		<p style="margin:24px 0 0;">Sportliche Grüße<br><strong>Julius Klinzer</strong><br><span style="color:#6C736E;font-size:13px;">Gründer Firmengolf Events</span></p>
	';
	if ( function_exists( 'fge_mail_log_context' ) ) {
		fge_mail_log_context( $req, 'pax_reminder_customer' );
	}
	$sent = (bool) wp_mail( $data['contact_email'], $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	if ( function_exists( 'fge_mail_log_context_clear' ) ) {
		fge_mail_log_context_clear();
	}
	if ( $sent ) {
		update_post_meta( $req, '_fge_pax_reminder_sent', current_datetime()->format( 'Y-m-d H:i:s' ) );
	}
	return $sent;
}
