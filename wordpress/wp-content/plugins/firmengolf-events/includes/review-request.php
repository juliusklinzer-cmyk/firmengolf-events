<?php
/**
 * Bewertungsbitte nach dem Event (Julius, 15.09.2026, KI-Sichtbarkeit Phase 2).
 *
 * Sobald eine Anfrage im Admin auf „event_durchgefuehrt" gestellt wird, bekommt der
 * Kunde einmalig eine kurze Danke-Mail mit dem Link zum Google-Unternehmensprofil
 * (fge_company()['google_review_url']). Gate: _fge_review_mail_sent. Demo-Anfragen
 * lösen nie Mails aus. Wird der Status direkt auf „abgeschlossen" gesetzt, ohne
 * vorher „event_durchgefuehrt", geht die Mail ebenfalls raus.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'fge_request_status_changed', static function ( int $req, string $status, string $old ): void {
	if ( ! in_array( $status, [ 'event_durchgefuehrt', 'abgeschlossen' ], true ) ) {
		return;
	}
	fge_send_review_request_email( $req );
}, 10, 3 );

/** Danke-Mail mit Bewertungslink an den Kunden, einmal je Anfrage. */
function fge_send_review_request_email( int $request_id ): bool {
	if ( '1' === (string) get_post_meta( $request_id, '_fge_review_mail_sent', true ) ) {
		return false;
	}
	if ( function_exists( 'fge_is_demo_request' ) && fge_is_demo_request( $request_id ) ) {
		return false;
	}
	$c   = fge_company();
	$url = (string) ( $c['google_review_url'] ?? '' );
	if ( '' === $url || ! function_exists( 'fge_get_request_email_data' ) ) {
		return false;
	}
	$data = fge_get_request_email_data( $request_id );
	if ( '' === $data['contact_email'] ) {
		return false;
	}
	$greet   = $data['first_name'] !== '' ? 'Hallo ' . esc_html( $data['first_name'] ) . ',' : 'Hallo,';
	$what    = $data['event_title'] !== '' ? esc_html( $data['event_title'] ) : 'euer Event';
	$where   = $data['partner_title'] !== '' ? ' bei ' . esc_html( $data['partner_title'] ) : '';
	$subject = 'Danke für euer Event mit Firmengolf, zwei Minuten für uns?';
	$content = '
		<p style="margin:0 0 16px;">' . $greet . '</p>
		<p style="margin:0 0 16px;">danke, dass ihr ' . $what . $where . ' mit uns gemacht habt. Ich hoffe, der Tag hat eurem Team genauso viel Spaß gemacht wie uns die Planung.</p>
		<p style="margin:0 0 16px;">Eine Bitte habe ich: Wir sind noch jung, und jede ehrliche Bewertung hilft anderen Firmen bei der Entscheidung, ob ein Golf-Teamevent etwas für sie ist. Zwei Minuten reichen, ein Satz genügt.</p>
		<p style="margin:0 0 22px;">' . fge_email_button( $url, 'Bei Google bewerten' ) . '</p>
		<p style="margin:0 0 16px;">Und wenn etwas nicht gepasst hat, antworte bitte einfach auf diese Mail. Das lese ich persönlich, und es hilft uns mehr als jede Bewertung.</p>
		<p style="margin:24px 0 0;">Sportliche Grüße<br><strong>Julius Klinzer</strong><br><span style="color:#6C736E;font-size:13px;">Gründer Firmengolf Events</span></p>
	';
	$sent = (bool) wp_mail( $data['contact_email'], $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	if ( $sent ) {
		update_post_meta( $request_id, '_fge_review_mail_sent', '1' );
		update_post_meta( $request_id, '_fge_review_mail_sent_at', current_datetime()->format( 'Y-m-d H:i:s' ) );
	}
	return $sent;
}
