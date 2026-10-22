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
		$team = trim( (string) get_the_title( $partner_id ) );
		return '' !== $team ? 'Hallo liebes Team von ' . $team . ',' : 'Hallo,';
	}
	// Nur den Vornamen ansprechen, wie Julius es auch am Telefon tut.
	$first = explode( ' ', $name )[0];
	return 'Hallo ' . $first . ',';
}

/**
 * Betreff und Mailtext der Platz-Anfrage, so wie sie ohne Änderung rausgehen.
 * Das Control Center zeigt beides vor dem Senden zum Bearbeiten (15.10.2026,
 * seit 22.10. der ganze Text statt nur der Einleitung). $venue_id 0 = Sammelanfrage.
 */
function fge_venue_request_defaults( int $req, bool $followup = false, int $venue_id = 0 ): array {
	$f = fge_venue_mail_facts( $req );
	if ( $followup ) {
		return [
			'subject' => 'Kurze Nachfrage: Firmenanfrage ' . ( $f['dates'] ? 'für ' . $f['dates'][0] . ' ' : '' ) . '(' . $f['ref'] . ')',
			'body'    => fge_venue_request_body( $req, $venue_id, true ),
		];
	}
	return [
		'subject' => 'Passt das bei euch? Firmenanfrage ' . ( $f['dates'] ? 'für ' . $f['dates'][0] : '' ) . ' (' . $f['ref'] . ')',
		'body'    => fge_venue_request_body( $req, $venue_id ),
	];
}

/**
 * Mailtext der Platz-Anfrage ohne Anrede, als schlichter Text wie eine selbst
 * geschriebene Mail (Julius, 22.10.2026: „nicht wie Werbung“). Reihenfolge:
 * Einleitung, Eckdaten (mit Dauer und Ablauf aus dem Event), Zusatzleistungen
 * des Kunden, Frage nach Termin und Kosten, Kontakt, Gruß.
 */
function fge_venue_request_body( int $req, int $venue_id = 0, bool $followup = false, string $intro = '' ): string {
	$f        = fge_venue_mail_facts( $req );
	$co       = function_exists( 'fge_company' ) ? fge_company() : [];
	$event_id = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
	$is_event = $event_id > 0 && 'firmengolf_event' === get_post_type( $event_id );
	$split    = static fn( string $t ): array => array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $t ) ) ) );

	if ( '' === $intro ) {
		$intro = $followup
			? 'ich wollte kurz nachhaken, ob unsere Anfrage bei euch angekommen ist. Hier noch einmal die Eckdaten:'
			: 'wir haben eine Firmenanfrage und suchen gerade nach einem Partner, der das abwickeln kann. Hier die Eckdaten:';
	}

	// Gruppe: vor der Buchung ohne Firmennamen (Paket C).
	$pax   = (int) $f['pax'];
	$level = trim( $f['level'] );
	$city  = trim( $f['city'] ) ?: trim( (string) get_post_meta( $req, '_fge_desired_region', true ) );
	$who   = fge_request_is_booked( $req ) && '' !== $f['company'] ? $f['company'] : 'ein Unternehmen';
	$group = implode( ', ', array_filter( [
		( $pax > 0 ? $pax . ' Personen' : 'Gruppe' ) . ' (' . $who . ( '' !== $city ? ' aus ' . $city : '' ) . ')',
		'' !== $level ? mb_strtolower( mb_substr( $level, 0, 1 ) ) . mb_substr( $level, 1 ) : '',
	] ) );

	// Anlass ist immer das gebuchte Event, ohne den Ort im Titel.
	$anlass = trim( $f['event'] );
	if ( '' !== $city ) {
		$anlass = (string) preg_replace( '/\s+in\s+' . preg_quote( $city, '/' ) . '$/iu', '', $anlass );
	}

	$start = trim( (string) get_post_meta( $req, '_fge_start_time', true ) );
	if ( 'After-Work' === $start ) {
		$start = 'After-Work, also ab ca. 16 Uhr';
	}
	$labels = array_values( function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [] );
	if ( ! $labels ) {
		$when = 'Termin: flexibel, ein Vorschlag von euch reicht' . ( '' !== $start ? ' (' . $start . ')' : '' );
	} else {
		$last = array_pop( $labels );
		$list = $labels ? implode( ', ', $labels ) . ' oder ' . $last : $last;
		$labels[] = $last;
		$when = ( 1 === count( $labels ) ? 'Wunschtermin: ' : 'Wunschtermine: ' ) . $list
			. ( '' !== $start ? ( 1 === count( $labels ) ? ', ' : ', jeweils ' ) . $start : '' );
	}

	$lines = [ 'Gruppe: ' . $group ];
	if ( '' !== $anlass ) {
		$lines[] = 'Anlass: ' . $anlass;
	}
	$lines[] = $when;
	if ( $is_event ) {
		$duration = trim( (string) get_post_meta( $event_id, '_fge_duration', true ) );
		if ( '' !== $duration ) {
			$lines[] = 'Dauer: ' . $duration;
		}
		$flow = function_exists( 'fge_offer_schedule_prefill' ) ? $split( fge_offer_schedule_prefill( $req ) ) : [];
		if ( $flow ) {
			// Platzhalter sind Orientierung, kein Pflichtenheft (Julius, 07.10.2026).
			$lines[] = 'Ablauf, grob gedacht: ' . implode( ', ', $flow ) . ' (darf bei euch gern anders laufen)';
		}
	}
	$food = trim( (string) get_post_meta( $req, '_fge_catering_notes', true ) );
	if ( '' !== $food ) {
		$lines[] = 'Essenswünsche: ' . $food;
	}

	// Zusatzleistungen: Positionen des Platzes, bei der Sammelanfrage die Wünsche des Kunden.
	$extras = [];
	if ( $venue_id > 0 ) {
		$row = fge_venue_get( $venue_id );
		foreach ( fge_venue_items_get( $venue_id ) as $it ) {
			if ( 'green_fee' === (string) $it['wish_key'] ) {
				continue;
			}
			$note     = fge_need_note( $req, (string) $it['label'] );
			$hint     = $row ? fge_venue_price_hint( (int) $row['partner_id'], (string) $it['wish_key'] ) : '';
			$extras[] = '- ' . $it['label'] . ( '' !== $note ? ': ' . $note : '' ) . ( '' !== $hint ? ' (bei uns notiert: ' . $hint . ')' : '' );
		}
	} else {
		foreach ( fge_venue_wishlist( $req ) as $key => $label ) {
			if ( 'green_fee' === $key ) {
				continue;
			}
			$note     = fge_need_note( $req, (string) $label );
			$extras[] = '- ' . $label . ( '' !== $note ? ': ' . $note : '' );
		}
	}

	$one  = 1 === count( $labels );
	$date = $labels ? ( $one ? 'ob der Termin bei euch passt' : 'ob einer der Termine bei euch passt' ) : 'wann es bei euch passt';
	$ask  = $extras
		? 'Sagt uns bitte, ' . $date . ', was ihr von den Zusatzleistungen anbieten könnt und welche Kosten uns dafür pro Person oder pauschal entstehen, damit wir das entsprechend an den Kunden weiterleiten können.'
		: 'Sagt uns bitte, ' . $date . ' und welche Kosten uns pro Person oder pauschal entstehen, damit wir das entsprechend an den Kunden weiterleiten können.';

	$contact = array_filter( [
		! empty( $co['whatsapp_display'] ) ? 'Mobil: ' . $co['whatsapp_display'] : '',
		! empty( $co['email_owner'] ) ? 'Mail: ' . $co['email_owner'] : '',
	] );

	$parts = [ $intro, implode( "\n", $lines ) ];
	if ( $extras ) {
		$parts[] = "Der Kunde hat außerdem Folgendes angefragt:\n" . implode( "\n", $extras );
	}
	$parts[] = $ask;
	if ( $contact ) {
		$parts[] = "Bei Fragen erreicht ihr mich direkt:\n" . implode( "\n", $contact );
	}
	$parts[] = "Sportliche Grüße\n" . (string) ( $co['managing_director'] ?? 'Firmengolf' ) . "\nFirmengolf Events";
	return implode( "\n\n", $parts );
}

/** Schlichter Mailtext als HTML: Absätze und Zeilenumbrüche, keine Box, kein Logo. */
function fge_plain_mail_html( string $text ): string {
	$paras = array_values( array_filter( array_map( 'trim', preg_split( '/\R{2,}/', trim( $text ) ) ) ) );
	$paras = array_map( static fn( $p ) => nl2br( esc_html( $p ) ), $paras );
	// Im Gruß wird „Firmengolf Events“ zum Link auf die Website (Julius, 22.10.2026).
	$last = count( $paras ) - 1;
	if ( $last >= 0 ) {
		$paras[ $last ] = (string) preg_replace( '/Firmengolf Events/', '<a href="' . esc_url( home_url( '/' ) ) . '" style="color:#1a1a1a;">Firmengolf Events</a>', $paras[ $last ], 1 );
	}
	$html = implode( '', array_map( static fn( $p ) => '<p style="margin:0 0 14px;">' . make_clickable( $p ) . '</p>', $paras ) );
	return '<!doctype html><html><body style="margin:0;padding:16px;"><div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;color:#1a1a1a;max-width:640px;">' . $html . '</div></body></html>';
}

/** Notiz zu einer Leistung aus dem Bedarf (z. B. „Grillbuffet“), leer ohne. */
function fge_need_note( int $req, string $label ): string {
	$notes = (array) get_post_meta( $req, '_fge_need_notes', true );
	return trim( (string) ( $notes[ mb_strtolower( trim( $label ) ) ] ?? '' ) );
}

/**
 * Anfrage an einen Platz: passt der Termin, und was kostet es.
 *
 * Fragt ausdrücklich alle Positionen ab, die der Kunde gewünscht hat, damit
 * die Angebote mehrerer Plätze vergleichbar werden und nicht nur beim Green Fee.
 *
 * $opts: subject, intro (aus dem Dialog im Control Center), followup (Nachfrage
 * an einen schon angefragten Platz: gleicher Inhalt, Stand bleibt „angefragt“).
 */
function fge_venue_send_request( int $req, int $venue_id, array $opts = [] ): bool {
	$row = fge_venue_get( $venue_id );
	if ( ! $row ) {
		return false;
	}
	// Dienstleister bekommen ihre eigene Mail (cc-request-providers.php).
	if ( 'dl' === (string) ( $row['kind'] ?? 'platz' ) ) {
		return function_exists( 'fge_provider_send_request' ) && fge_provider_send_request( $req, $venue_id, $opts );
	}
	$partner_id = (int) $row['partner_id'];
	$to         = fge_venue_partner_email( $partner_id );
	if ( '' === $to ) {
		return false;
	}

	$co       = function_exists( 'fge_company' ) ? fge_company() : [];
	$followup = ! empty( $opts['followup'] );
	$defaults = fge_venue_request_defaults( $req, $followup );
	$subject  = trim( (string) ( $opts['subject'] ?? '' ) ) ?: $defaults['subject'];
	// Eigener Text aus dem Dialog, sonst der Standardtext (mit eigener Einleitung, falls nur die kam).
	$body = trim( (string) ( $opts['body'] ?? '' ) );
	if ( '' === $body ) {
		$body = fge_venue_request_body( $req, $venue_id, $followup, trim( (string) ( $opts['intro'] ?? '' ) ) );
	}
	$content = fge_plain_mail_html( fge_venue_greeting( $partner_id ) . "\n\n" . $body );

	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
	if ( ! empty( $co['email_owner'] ) && is_email( $co['email_owner'] ) ) {
		$headers[] = 'Bcc: ' . $co['email_owner'];
	}

	fge_mail_log_context( $req, $followup ? 'venue_followup' : 'venue_request' );
	$ok = (bool) wp_mail( $to, $subject, $content, $headers );
	fge_mail_log_context_clear();

	if ( $ok && $followup ) {
		fge_venue_update( $venue_id, [ 'followup_at' => current_time( 'mysql' ) ] );
		fge_activity_add( $req, 'venue', sprintf( 'Nachfrage an %s gesendet', get_the_title( $partner_id ) ) );
	} elseif ( $ok ) {
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
/**
 * Der Kunde hat abgesagt: der gewählte Platz bekommt den Termin zurück.
 * Ohne diese Mail hielt der Platz den Termin weiter (Audit 28.09.2026).
 */
function fge_venue_send_release( int $req, int $venue_id ): bool {
	$row = fge_venue_get( $venue_id );
	if ( ! $row ) {
		return false;
	}
	$partner_id = (int) $row['partner_id'];
	$to         = fge_venue_partner_email( $partner_id );
	if ( '' === $to ) {
		return false;
	}
	$f    = fge_venue_mail_facts( $req );
	$co   = function_exists( 'fge_company' ) ? fge_company() : [];
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$when = '' !== (string) ( $snap['date'] ?? '' ) ? (string) $snap['date'] : ( $f['dates'][0] ?? '' );

	$subject = 'Termin wird frei: Firmenanfrage' . ( '' !== $when ? ' am ' . $when : '' ) . ' (' . $f['ref'] . ')';
	$content = '
		<p style="margin:0 0 16px;">' . esc_html( fge_venue_greeting( $partner_id ) ) . '</p>
		<p style="margin:0 0 16px;">kurze Rückmeldung zur Firmenanfrage ' . esc_html( $f['ref'] ) . ( '' !== $when ? ' am <strong>' . esc_html( $when ) . '</strong>' : '' ) . ': Die Gruppe hat unser Angebot leider nicht angenommen. Den Termin könnt ihr wieder freigeben, es entsteht kein Auftrag.</p>
		<p style="margin:0 0 16px;">Danke fürs Mitdenken und die schnelle Rückmeldung. Wir fragen gern wieder an, sobald die nächste Gruppe in eure Ecke passt.</p>
		<p style="margin:0;">Sportliche Grüße<br>' . esc_html( (string) ( $co['managing_director'] ?? 'Firmengolf' ) ) . '</p>
	';
	fge_mail_log_context( $req, 'venue_release' );
	$ok = (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	fge_mail_log_context_clear();
	if ( $ok ) {
		fge_venue_update( $venue_id, [ 'status' => 'abgesagt', 'replied_at' => current_time( 'mysql' ), 'reason' => 'Kunde hat abgesagt' ] );
		update_post_meta( $req, '_fge_venue_released', 1 );
		fge_activity_add( $req, 'venue', sprintf( '%s informiert: Kunde hat abgesagt, Termin frei', get_the_title( $partner_id ) ) );
	}
	return $ok;
}

/**
 * Bestätigung an den Platz nach seiner Antwort (Julius, 28.09.2026), einheitlich:
 * „Danke für die Rückmeldung, so haben wir es notiert, wir leiten das Angebot an den
 * Kunden weiter, bitte reserviert bis X, wir melden uns." Geht automatisch, sobald
 * Julius die Antwort erfasst hat, und noch einmal beim Angebotsversand, wenn die
 * Frist des Kunden später liegt als das bisher genannte Datum.
 * $mode 'absprache' = nach der erfassten Antwort, 'reservierung' = beim Versand.
 */
function fge_venue_send_summary( int $req, int $venue_id, string $mode = 'absprache' ): bool {
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
	$labels = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$dates  = function_exists( 'fge_venue_dates_get' ) ? fge_venue_dates_get( $venue_id ) : [];
	$free   = [];
	$alt    = '';
	foreach ( $dates as $idx => $d ) {
		if ( 1 === $d['available'] && isset( $labels[ $idx ] ) ) {
			$free[] = (string) $labels[ $idx ];
		}
		if ( '' !== (string) $d['note'] ) {
			$alt = (string) $d['note'];
		}
	}
	$money = static fn( float $x ): string => number_format_i18n( $x, 2 ) . ' €';
	$price = (float) $row['price'] > 0
		? $money( (float) $row['price'] ) . ' ' . ( (int) $row['price_gross'] ? 'brutto' : 'netto' ) . ' ' . ( 'pauschal' === (string) $row['price_basis'] ? 'pauschal' : 'p.P.' )
		: '';
	$items = '';
	$nope  = [];
	foreach ( fge_venue_items_get( $venue_id ) as $it ) {
		if ( 'green_fee' === (string) $it['wish_key'] ) {
			continue;
		}
		if ( ! (int) $it['available'] ) {
			$nope[] = (string) $it['label'];
			continue;
		}
		$p = (float) $it['price'] > 0
			? $money( (float) $it['price'] ) . ' ' . ( (int) $it['price_gross'] ? 'brutto' : 'netto' ) . ' ' . ( 'pauschal' === (string) $it['price_basis'] ? 'pauschal' : 'p.P.' )
			: 'Preis noch offen';
		$items .= '<li style="margin-bottom:3px;">' . esc_html( (string) $it['label'] ) . ': ' . esc_html( $p ) . ( '' !== (string) ( $it['note'] ?? '' ) ? ' <span style="color:#6C736E;">(' . esc_html( (string) $it['note'] ) . ')</span>' : '' ) . '</li>';
	}
	$rowfn = static function ( string $k, string $v ): string {
		return '' === trim( wp_strip_all_tags( $v ) ) ? '' : '<tr><td style="padding:5px 16px 5px 0;color:#555;white-space:nowrap;vertical-align:top;"><strong>' . esc_html( $k ) . '</strong></td><td style="padding:5px 0;color:#1a1a1a;">' . $v . '</td></tr>';
	};
	// Reservieren bis: Frist des Kunden, sobald das Angebot draußen ist, sonst ein
	// Standard-Halten (10 Tage), damit der Platz immer ein konkretes Datum hat.
	$deadline  = (int) get_post_meta( $req, '_fge_offer_deadline', true );
	$sent      = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	$hold_days = (int) apply_filters( 'fge_venue_hold_days', 10 );
	$until_ts  = $sent && $deadline > 0 ? $deadline : time() + $hold_days * DAY_IN_SECONDS;
	$until     = wp_date( 'd.m.Y', $until_ts );
	$plural    = count( $free ) > 1;

	$rows = $rowfn( 'Gruppe', esc_html( fge_request_group_label( $req ) ) )
		. $rowfn( 'Anlass', esc_html( $f['event'] ) )
		. $rowfn( $free ? ( $plural ? 'Termine, die bei euch gehen' : 'Termin, der bei euch geht' ) : 'Termine', $free ? esc_html( implode( ' oder ', $free ) ) : '<span style="color:#B4332B;">noch keiner bestätigt</span>' )
		. $rowfn( 'Alternativvorschlag', esc_html( $alt ) )
		. $rowfn( 'Event und Platznutzung', esc_html( $price ) )
		. $rowfn( 'Weitere Positionen', '' !== $items ? '<ul style="margin:0;padding-left:18px;">' . $items . '</ul>' : '' )
		. $rowfn( 'Bietet ihr nicht an', esc_html( implode( ', ', $nope ) ) )
		. $rowfn( 'Reserviert bis', esc_html( $until ) )
		. $rowfn( 'Referenz', esc_html( $f['ref'] ) );

	$subject = 'Danke für die Rückmeldung, bitte reservieren bis ' . $until . ' (' . $f['ref'] . ')';
	$intro   = 'danke für eure Rückmeldung' . ( 'telefon' === (string) $row['channel'] ? ' am Telefon' : '' ) . '. So haben wir es notiert, damit wir vom Gleichen sprechen:';
	$outro   = ( $sent
			? 'Wir haben das Angebot an den Kunden weitergeleitet, er entscheidet bis <strong>' . esc_html( $until ) . '</strong>.'
			: 'Wir stellen das Angebot jetzt zusammen und leiten es an den Kunden weiter.' )
		. ' Bitte reserviert ' . ( $plural ? 'die genannten Termine' : 'den genannten Termin' ) . ' bis zum <strong>' . esc_html( $until ) . '</strong>. Wir melden uns, sobald wir eine Rückmeldung vom Kunden haben, so oder so. Falls etwas nicht stimmt, antwortet bitte kurz auf diese Mail.';
	$content = '
		<p style="margin:0 0 16px;">' . esc_html( fge_venue_greeting( $partner_id ) ) . '</p>
		<p style="margin:0 0 16px;">' . $intro . '</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows . '</table>
		<p style="margin:0 0 16px;">' . $outro . '</p>
		<p style="margin:0;">Sportliche Grüße<br>' . esc_html( (string) ( $co['managing_director'] ?? 'Firmengolf' ) ) . '</p>
	';
	fge_mail_log_context( $req, 'reservierung' === $mode ? 'venue_reservation' : 'venue_summary' );
	$ok = (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	fge_mail_log_context_clear();
	if ( $ok ) {
		fge_venue_update( $venue_id, [ ( 'reservierung' === $mode ? 'reservation_at' : 'summary_at' ) => current_time( 'mysql' ), 'reserved_until' => wp_date( 'Y-m-d', $until_ts ) ] );
		fge_activity_add( $req, 'venue', sprintf( 'Bestätigung mit Reservierungsbitte an %s gesendet (reserviert bis %s)', get_the_title( $partner_id ), $until ) );
	}
	return $ok;
}

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
	// Nach der Terminbestätigung zählt der gebuchte Termin, nicht der erste Wunschtermin.
	$snap   = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$date   = '' !== (string) ( $snap['date'] ?? '' ) ? (string) $snap['date'] : (string) ( $f['dates'][0] ?? '' );
	$when   = '' !== $date ? ' am ' . $date : '';

	// Einladung, das Angebot dauerhaft anzubieten (cc-venue-catalog.php). Der
	// Platz hat gerade Preise genannt, das ist der beste Moment für den Katalog.
	$catalog = '';
	if ( function_exists( 'fge_venue_catalog_link' ) ) {
		$catalog = '
		<p style="margin:0 0 16px;">Wenn ihr wollt, könnt ihr dieses Angebot dauerhaft bei Firmengolf sichtbar machen, ein Klick genügt. Firmen finden euch dann direkt, wir kümmern uns um Anfrage, Angebot und Abrechnung.</p>
		<p style="margin:0 0 18px;">' . fge_email_button( fge_venue_catalog_link( $venue_id ), 'Angebot dauerhaft anbieten' ) . '</p>';
	}

	$subject = 'Doch woanders: Firmenanfrage' . $when . ' (' . $f['ref'] . ')';
	$content = '
		<p style="margin:0 0 16px;">' . esc_html( fge_venue_greeting( $partner_id ) ) . '</p>
		<p style="margin:0 0 16px;">danke, dass ihr euch die Anfrage angeschaut habt. Die Gruppe hat sich diesmal für einen anderen Platz entschieden.</p>
		' . ( '' !== $reason ? '<p style="margin:0 0 16px;">' . esc_html( $reason ) . '</p>' : '' ) . '
		<p style="margin:0 0 16px;">Das war kein Nein zu euch: wir fragen gern wieder an, sobald etwas in eurer Ecke passt. Wenn ihr für solche Gruppen feste Preise habt, schickt sie mir gern, dann habe ich sie beim nächsten Mal direkt zur Hand.</p>
		' . $catalog . '
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
