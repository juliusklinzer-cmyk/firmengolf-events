<?php
/**
 * Mails der Platz-Pipeline: Anfrage an einen Platz und Absage an die
 * nicht gewählten.
 *
 * Beide bewusst im Ton einer Mail unter Kollegen, nicht als Systemmeldung.
 * Der Platz soll eine kurze Antwort schreiben können, ohne sich irgendwo
 * anzumelden.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Eckdaten der Anfrage für beide Mails. */
function fge_venue_mail_facts( int $req ): array {
	$data   = fge_get_request_email_data( $req );
	$wishes = function_exists( 'fge_rr_wish_dates' ) ? fge_rr_wish_dates( $req ) : [];
	$snap   = (array) get_post_meta( $req, '_fge_offer_snapshot', true );

	return [
		'ref'     => fge_request_number( $req ),
		'company' => (string) $data['company_name'],
		'city'    => (string) get_post_meta( $req, '_fge_company_city', true ),
		'pax'     => (int) ( $snap['participants'] ?? get_post_meta( $req, '_fge_expected_participants', true ) ),
		'level'   => (string) get_post_meta( $req, '_fge_group_experience', true ),
		'dates'   => array_values( array_map( 'strval', $wishes ) ),
		'event'   => (string) $data['event_title'],
	];
}

/** Kontaktadresse eines Platzes für die Pipeline. */
function fge_venue_partner_email( int $partner_id ): string {
	return function_exists( 'fge_cc_partner_email' ) ? fge_cc_partner_email( $partner_id ) : '';
}

/** Anrede aus dem Kontaktnamen, sonst neutral. */
function fge_venue_greeting( int $partner_id ): string {
	$name = trim( (string) get_post_meta( $partner_id, '_fge_event_contact_name', true ) );
	if ( '' === $name ) {
		$name = trim( (string) get_post_meta( $partner_id, '_fge_main_contact_name', true ) );
	}
	if ( '' === $name ) {
		return 'Hallo,';
	}
	// Nur den Vornamen ansprechen, wie Julius es auch am Telefon tut.
	$first = explode( ' ', $name )[0];
	return 'Hallo ' . $first . ',';
}

/**
 * Anfrage an einen Platz: passt der Termin, und was kostet es.
 *
 * Fragt ausdrücklich alle Positionen ab, die der Kunde gewünscht hat, damit
 * die Angebote mehrerer Plätze vergleichbar werden und nicht nur beim Green Fee.
 */
function fge_venue_send_request( int $req, int $venue_id ): bool {
	$row = fge_venue_get( $venue_id );
	if ( ! $row ) {
		return false;
	}
	$partner_id = (int) $row['partner_id'];
	$to         = fge_venue_partner_email( $partner_id );
	if ( '' === $to ) {
		return false;
	}

	$f  = fge_venue_mail_facts( $req );
	$co = function_exists( 'fge_company' ) ? fge_company() : [];

	$rowfn = static function ( string $k, string $v ): string {
		return '' === trim( wp_strip_all_tags( $v ) ) ? '' : '<tr><td style="padding:5px 16px 5px 0;color:#555;white-space:nowrap;vertical-align:top;"><strong>' . esc_html( $k ) . '</strong></td><td style="padding:5px 0;color:#1a1a1a;">' . $v . '</td></tr>';
	};

	$group = trim( $f['company'] . ( '' !== $f['city'] ? ' aus ' . $f['city'] : '' ) )
		. ( $f['pax'] > 0 ? ', ' . $f['pax'] . ' Personen' : '' )
		. ( '' !== $f['level'] ? ', ' . $f['level'] : '' );

	$rows = $rowfn( 'Wunschtermine', $f['dates'] ? esc_html( implode( ' oder ', $f['dates'] ) ) : 'flexibel' )
		. $rowfn( 'Gruppe', esc_html( $group ) )
		. $rowfn( 'Anlass', esc_html( $f['event'] ) );

	// Positionsliste: genau die Punkte, zu denen wir einen Preis brauchen.
	$items = fge_venue_items_get( $venue_id );
	$list  = '';
	foreach ( $items as $it ) {
		$hint  = fge_venue_price_hint( $partner_id, (string) $it['wish_key'] );
		$list .= '<li style="margin-bottom:4px;">' . esc_html( (string) $it['label'] )
			. ( '' !== $hint ? ' <span style="color:#6C736E;">(bei uns notiert: ' . esc_html( $hint ) . ')</span>' : '' )
			. '</li>';
	}

	$subject = 'Passt das bei euch? Firmenanfrage ' . ( $f['dates'] ? 'für ' . $f['dates'][0] : '' ) . ' (' . $f['ref'] . ')';
	$content = '
		<p style="margin:0 0 16px;">' . esc_html( fge_venue_greeting( $partner_id ) ) . '</p>
		<p style="margin:0 0 16px;">wir haben eine Firmenanfrage und schauen gerade, welcher Platz am besten passt. Hier die Eckdaten:</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows . '</table>
		' . ( '' !== $list ? '<p style="margin:0 0 4px;font-weight:600;">Dazu bräuchten wir je einen Preis</p><ul style="margin:0 0 16px;padding-left:20px;">' . $list . '</ul>' : '' ) . '
		<p style="margin:0 0 16px;">Eine kurze Antwort auf diese Mail genügt: geht der Termin, und was kostet es bei euch? Wenn eine der Positionen bei euch nicht geht, schreib es einfach dazu.</p>
		<p style="margin:0;">Danke dir und sportliche Grüße<br>' . esc_html( (string) ( $co['managing_director'] ?? 'Firmengolf' ) ) . '</p>
	';

	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
	if ( ! empty( $co['email_owner'] ) && is_email( $co['email_owner'] ) ) {
		$headers[] = 'Bcc: ' . $co['email_owner'];
	}

	fge_mail_log_context( $req, 'venue_request' );
	$ok = (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), $headers );
	fge_mail_log_context_clear();

	if ( $ok ) {
		fge_venue_update( $venue_id, [
			'status'   => 'angefragt',
			'channel'  => 'mail',
			'asked_at' => current_time( 'mysql' ),
		] );
		fge_activity_add( $req, 'venue', sprintf( 'Anfrage an %s gesendet', get_the_title( $partner_id ) ) );
	}
	return $ok;
}

/**
 * Absage an einen nicht gewählten Platz.
 *
 * Bewusst persönlich und mit dem ehrlichen Grund, weil dieselben Plätze bei der
 * nächsten Anfrage wieder gebraucht werden.
 */
function fge_venue_send_decline( int $req, int $venue_id, string $reason = '' ): bool {
	$row = fge_venue_get( $venue_id );
	if ( ! $row ) {
		return false;
	}
	$partner_id = (int) $row['partner_id'];
	$to         = fge_venue_partner_email( $partner_id );
	if ( '' === $to ) {
		return false;
	}

	$f      = fge_venue_mail_facts( $req );
	$co     = function_exists( 'fge_company' ) ? fge_company() : [];
	$reason = '' !== trim( $reason ) ? trim( $reason ) : (string) $row['reason'];
	$when   = $f['dates'] ? ' am ' . $f['dates'][0] : '';

	$subject = 'Doch woanders: Firmenanfrage' . $when . ' (' . $f['ref'] . ')';
	$content = '
		<p style="margin:0 0 16px;">' . esc_html( fge_venue_greeting( $partner_id ) ) . '</p>
		<p style="margin:0 0 16px;">danke, dass ihr euch die Anfrage angeschaut habt. Die Gruppe geht diesmal leider zu einem anderen Platz.</p>
		' . ( '' !== $reason ? '<p style="margin:0 0 16px;">' . esc_html( $reason ) . '</p>' : '' ) . '
		<p style="margin:0 0 16px;">Das war kein Nein zu euch: wir fragen gern wieder an, sobald etwas in eurer Ecke passt. Wenn ihr für solche Gruppen feste Preise habt, schickt sie mir gern, dann habe ich sie beim nächsten Mal direkt zur Hand.</p>
		<p style="margin:0;">Sportliche Grüße<br>' . esc_html( (string) ( $co['managing_director'] ?? 'Firmengolf' ) ) . '</p>
	';

	fge_mail_log_context( $req, 'venue_decline' );
	$ok = (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	fge_mail_log_context_clear();

	if ( $ok ) {
		fge_venue_update( $venue_id, [
			'status'     => 'abgesagt',
			'replied_at' => current_time( 'mysql' ),
			'reason'     => '' !== $reason ? $reason : 'Anderer Platz gewählt',
		] );
		fge_activity_add( $req, 'venue', sprintf( 'Absage an %s gesendet', get_the_title( $partner_id ) ) );
	}
	return $ok;
}
