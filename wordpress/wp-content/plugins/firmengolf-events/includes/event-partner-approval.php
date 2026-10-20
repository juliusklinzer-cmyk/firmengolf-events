<?php
/**
 * Partner-Freigabe nach Bearbeitung durch Firmengolf (Julius, 20.10.2026).
 *
 * Hat Firmengolf ein Partner-Event im Control Center überarbeitet, kann es den
 * Partner um Freigabe bitten. Der Partner bekommt eine Mail mit Link ohne Login
 * auf die Eventseite, sieht dort das Event wie online und gibt mit einem Klick
 * frei, oder antwortet auf die Mail. Nach drei Werktagen ohne Antwort geht eine
 * Erinnerung raus und die Anfrage steht unter „Heute“.
 *
 * Kein eigener Status: ein neues Event bleibt in Prüfung (nicht öffentlich),
 * ein Live-Event bleibt live. Offen ist die Freigabe, solange _fge_pa_token
 * gesetzt und _fge_pa_done_at leer ist.
 *   _fge_pa_token       Schlüssel im Link
 *   _fge_pa_sent_at     gesendet (Zeitstempel)
 *   _fge_pa_reminded_at Erinnerung gesendet (Zeitstempel)
 *   _fge_pa_done_at     vom Partner freigegeben (Zeitstempel)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Wartet das Event auf die Freigabe des Partners? */
function fge_pa_pending( int $eid ): bool {
	return '' !== (string) get_post_meta( $eid, '_fge_pa_token', true ) && '' === (string) get_post_meta( $eid, '_fge_pa_done_at', true );
}

/** Link für den Partner: Eventseite mit Freigabe-Leiste. */
function fge_pa_link( int $eid ): string {
	return add_query_arg( 'freigabe', (string) get_post_meta( $eid, '_fge_pa_token', true ), (string) get_permalink( $eid ) );
}

/** Gültiger Schlüssel aus der Adresse für dieses Event? */
function fge_pa_token_ok( int $eid ): bool {
	$t = sanitize_text_field( wp_unslash( $_GET['freigabe'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$k = (string) get_post_meta( $eid, '_fge_pa_token', true );
	return '' !== $t && '' !== $k && hash_equals( $k, $t );
}

/** Zeitpunkt plus n Werktage (Mo bis Fr). */
function fge_pa_add_workdays( int $ts, int $n ): int {
	while ( $n > 0 ) {
		$ts += DAY_IN_SECONDS;
		if ( (int) wp_date( 'N', $ts ) <= 5 ) {
			$n--;
		}
	}
	return $ts;
}

/** Überfällig: drei Werktage ohne Freigabe. */
function fge_pa_overdue( int $eid ): bool {
	$sent = (int) get_post_meta( $eid, '_fge_pa_sent_at', true );
	return fge_pa_pending( $eid ) && $sent > 0 && time() >= fge_pa_add_workdays( $sent, 3 );
}

/** Freigabe anfragen: Schlüssel setzen und Mail an den Partner. */
function fge_pa_request( int $eid ): bool {
	$pid = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
	if ( $pid <= 0 ) {
		return false;
	}
	update_post_meta( $eid, '_fge_pa_token', wp_generate_password( 32, false ) );
	update_post_meta( $eid, '_fge_pa_sent_at', time() );
	delete_post_meta( $eid, '_fge_pa_reminded_at' );
	delete_post_meta( $eid, '_fge_pa_done_at' );
	return fge_pa_send_mail( $eid, false );
}

/** Mail an den Partner, erstmalig oder als Erinnerung. */
function fge_pa_send_mail( int $eid, bool $reminder ): bool {
	$pid = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
	$to  = (string) get_post_meta( $pid, '_fge_main_contact_email', true ) ?: (string) get_post_meta( $pid, '_fge_event_contact_email', true );
	if ( $pid <= 0 || ! is_email( $to ) ) {
		return false;
	}
	[ , , $greeting ] = fge_partner_mail_basics( $pid );
	$title   = get_the_title( $eid );
	$subject = ( $reminder ? 'Erinnerung: ' : '' ) . 'Dein Event „' . $title . '“ ist fast online';
	$intro   = $reminder
		? '<p style="margin:0 0 16px;">kurze Erinnerung: Wir haben dein Event <strong>' . esc_html( $title ) . '</strong> überarbeitet und warten noch auf deine Freigabe.</p>'
		: '<p style="margin:0 0 16px;">danke für deine Einreichung <strong>' . esc_html( $title ) . '</strong>. Wir haben noch etwas an der Präsentation gearbeitet, damit das Event online möglichst gut ankommt.</p>';
	$content = '
		<p style="margin:0 0 16px;">' . $greeting . '</p>
		' . $intro . '
		<p style="margin:0 0 22px;">Schau es dir bitte noch einmal an. Wenn alles passt, gib das Event mit einem Klick frei:</p>
		<p style="margin:0 0 22px;">' . fge_email_button( fge_pa_link( $eid ), 'Event ansehen und freigeben' ) . '</p>
		<p style="margin:0 0 16px;">Wenn du etwas anders haben möchtest, antworte einfach auf diese Mail.</p>
		<p style="margin:24px 0 0;">Sportliche Grüße<br><strong>Julius Klinzer</strong><br><span style="color:#6C736E;font-size:13px;">Gründer Firmengolf Events</span></p>
	';
	return (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

// Nicht öffentliches Event mit gültigem Schlüssel zeigen (sonst 410).
add_action( 'template_redirect', static function (): void {
	if ( is_singular( 'firmengolf_event' ) && fge_pa_token_ok( (int) get_queried_object_id() ) ) {
		remove_action( 'template_redirect', 'fge_block_non_approved_events', 1 );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
	}
}, 0 );

// Freigabe-Leiste oben auf der Eventseite.
add_action( 'wp_body_open', static function (): void {
	if ( ! is_singular( 'firmengolf_event' ) ) {
		return;
	}
	$eid = (int) get_queried_object_id();
	if ( ! fge_pa_token_ok( $eid ) ) {
		return;
	}
	$done = '' !== (string) get_post_meta( $eid, '_fge_pa_done_at', true );
	$edit = ( function_exists( 'fge_portal_page_url' ) ? fge_portal_page_url() : home_url( '/partnerportal/' ) ) . '?tab=angebote&portal_action=edit&event_id=' . $eid;
	echo '<div class="fg-pa-bar" role="region" aria-label="Freigabe"><div class="fg-pa-in">';
	if ( $done ) {
		echo '<p class="fg-pa-t"><strong>Danke, das Event ist freigegeben.</strong> Es ist jetzt auf firmengolf-events.de sichtbar.</p>';
	} else {
		echo '<p class="fg-pa-t"><strong>Firmengolf hat dein Event überarbeitet.</strong> So erscheint es online. Passt alles?</p>';
		echo '<div class="fg-pa-act"><a class="fg-pa-alt" href="' . esc_url( $edit ) . '">Ich möchte noch etwas ändern</a>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fge_pa_approve">';
		echo '<input type="hidden" name="event" value="' . (int) $eid . '"><input type="hidden" name="freigabe" value="' . esc_attr( (string) get_post_meta( $eid, '_fge_pa_token', true ) ) . '">';
		echo '<button type="submit" class="fg-btn-brand">Passt, Event freigeben</button></form></div>';
	}
	echo '</div></div>';
} );

/** Freigabe durch den Partner: neues Event geht live, Live-Event bleibt live. */
function fge_pa_handle_approve(): void {
	$eid = absint( $_POST['event'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Schlüssel im Link ersetzt die Nonce
	$t   = sanitize_text_field( wp_unslash( $_POST['freigabe'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$k   = (string) get_post_meta( $eid, '_fge_pa_token', true );
	if ( 'firmengolf_event' !== get_post_type( $eid ) || '' === $k || ! hash_equals( $k, $t ) ) {
		wp_die( 'Dieser Freigabe-Link ist nicht mehr gültig.', '', [ 'response' => 403 ] );
	}
	if ( fge_pa_pending( $eid ) ) {
		$st = (string) get_post_meta( $eid, '_fge_event_status', true );
		if ( in_array( $st, [ 'zur_pruefung', 'aenderung_in_pruefung' ], true ) ) {
			// Wie die Freigabe im CC, aber ohne die Freigabe-Mail: der Partner hat es ja selbst gemacht.
			$paused = '1' === (string) get_post_meta( $eid, '_fge_was_paused', true );
			update_post_meta( $eid, '_fge_event_status', $paused ? 'pausiert' : 'freigegeben' );
			delete_post_meta( $eid, '_fge_was_paused' );
			delete_post_meta( $eid, '_fge_review_message' );
			delete_post_meta( $eid, '_fge_review_message_at' );
		}
		update_post_meta( $eid, '_fge_pa_done_at', time() );
		$co  = fge_company();
		$to  = (string) ( $co['email_partner'] ?? $co['email_events'] ?? '' );
		$pid = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
		if ( is_email( $to ) ) {
			$subject = 'Partner hat freigegeben: ' . get_the_title( $eid );
			$body    = '<p style="margin:0 0 16px;">' . esc_html( $pid > 0 ? fge_cc_partner_name( $pid ) : 'Der Partner' ) . ' hat das überarbeitete Event <strong>' . esc_html( get_the_title( $eid ) ) . '</strong> freigegeben. Es ist jetzt live.</p>'
				. '<p style="margin:0;">' . fge_email_button( (string) get_permalink( $eid ), 'Eventseite ansehen' ) . '</p>';
			wp_mail( $to, $subject, fge_email_wrap( $subject, $body ), [ 'Content-Type: text/html; charset=UTF-8' ] );
		}
	}
	wp_safe_redirect( fge_pa_link( $eid ) );
	exit;
}
add_action( 'admin_post_fge_pa_approve', 'fge_pa_handle_approve' );
add_action( 'admin_post_nopriv_fge_pa_approve', 'fge_pa_handle_approve' );

// Reicht der Partner selbst neu ein, ist die Freigabe-Anfrage erledigt.
add_action( 'fge_event_submitted', static function ( $event ): void {
	$eid = is_object( $event ) ? (int) $event->ID : (int) $event;
	if ( $eid > 0 && fge_pa_pending( $eid ) ) {
		delete_post_meta( $eid, '_fge_pa_token' );
		delete_post_meta( $eid, '_fge_pa_sent_at' );
		delete_post_meta( $eid, '_fge_pa_reminded_at' );
	}
}, 6 );

/** Offene Freigaben (Event-IDs). */
function fge_pa_pending_events(): array {
	$ids = get_posts( [ 'post_type' => 'firmengolf_event', 'post_status' => 'any', 'numberposts' => 200, 'fields' => 'ids', 'meta_key' => '_fge_pa_token', 'meta_compare' => 'EXISTS' ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return array_values( array_filter( array_map( 'intval', $ids ), 'fge_pa_pending' ) );
}

// Täglich: nach drei Werktagen einmal erinnern.
add_action( 'fge_cc_daily_digest', static function (): void {
	foreach ( fge_pa_pending_events() as $eid ) {
		if ( fge_pa_overdue( $eid ) && '' === (string) get_post_meta( $eid, '_fge_pa_reminded_at', true ) && fge_pa_send_mail( $eid, true ) ) {
			update_post_meta( $eid, '_fge_pa_reminded_at', time() );
		}
	}
}, 8 );
