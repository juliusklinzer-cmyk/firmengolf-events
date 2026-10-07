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

/**
 * Was genau angefragt ist, aus dem Event (meist ein Platzhalter): Beschreibung,
 * Dauer, Paketinhalt und Ablauf. Ohne das weiß der Platz nicht, was ein
 * „Teamevent" bei uns heißt (Julius, 07.10.2026). Ablauf und Paketinhalt nehmen
 * zuerst, was im Angebot angepasst wurde, sonst das Event.
 */
function fge_venue_program_html( int $req ): string {
	$event_id = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
	if ( $event_id <= 0 || 'firmengolf_event' !== get_post_type( $event_id ) ) {
		return '';
	}
	$split = static fn( string $t ): array => array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $t ) ) ) );

	$desc     = trim( (string) get_post_meta( $event_id, '_fge_card_description', true ) );
	$duration = trim( (string) get_post_meta( $event_id, '_fge_duration', true ) );
	$includes = $split( (string) get_post_meta( $req, '_fge_offer_includes', true ) );
	if ( ! $includes ) {
		$raw      = get_post_meta( $event_id, '_fge_event_includes', true );
		$includes = is_array( $raw ) ? array_values( array_filter( array_map( 'trim', array_map( 'strval', $raw ) ) ) ) : $split( (string) $raw );
	}
	// Unsere eigene Leistung ist für den Platz kein Programmpunkt.
	$includes = array_values( array_filter( $includes, static fn( $i ) => false === mb_stripos( $i, 'Organisation' ) ) );
	$flow     = function_exists( 'fge_offer_schedule_prefill' ) ? $split( fge_offer_schedule_prefill( $req ) ) : [];

	$html = '<p style="margin:0 0 4px;font-weight:600;">Angefragt ist: ' . esc_html( get_the_title( $event_id ) ) . '</p>';
	if ( '' !== $desc ) {
		$html .= '<p style="margin:0 0 8px;">' . esc_html( $desc ) . '</p>';
	}
	if ( '' !== $duration ) {
		$html .= '<p style="margin:0 0 8px;"><strong>Dauer:</strong> ' . esc_html( $duration ) . '</p>';
	}
	if ( $includes ) {
		$html .= '<p style="margin:0 0 4px;"><strong>Im Paket enthalten</strong></p><ul style="margin:0 0 8px;padding-left:20px;">';
		foreach ( $includes as $i ) {
			$html .= '<li style="margin-bottom:2px;">' . esc_html( $i ) . '</li>';
		}
		$html .= '</ul>';
	}
	if ( $flow ) {
		$html .= '<p style="margin:0 0 4px;"><strong>Ablauf</strong></p><ol style="margin:0 0 8px;padding-left:20px;">';
		foreach ( $flow as $step ) {
			$html .= '<li style="margin-bottom:2px;">' . esc_html( $step ) . '</li>';
		}
		$html .= '</ol>';
	}
	// Platzhalter sind Orientierung, kein Pflichtenheft (Julius, 07.10.2026).
	$html .= '<p style="margin:8px 0 0;color:#4B5054;">Das ist unsere Orientierung, wie so ein Tag aussehen kann. Es muss bei euch nicht genau so laufen: Sagt uns einfach, was ihr davon anbietet und was ihr anders machen würdet.</p>';
	return '<div style="margin:0 0 16px;padding:12px 14px;background:#F5F6F8;border-radius:10px;">' . $html . '</div>';
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

	// Vor der Buchung bleibt das Unternehmen anonym (Paket C).
	$group = fge_request_group_label( $req );

	// Anlass mit Niveau und Startzeitwunsch, damit der Platz Pro und Slot
	// gleich mitdenken kann: „Golf-Teamevent, überwiegend Anfänger, Start After-Work".
	$start  = trim( (string) get_post_meta( $req, '_fge_start_time', true ) );
	$anlass = array_filter( [
		trim( $f['event'] ),
		// Niveau steht schon in der Gruppen-Zeile, hier nur, wenn es dort fehlt.
		false === mb_stripos( $group, trim( $f['level'] ) ) ? trim( $f['level'] ) : '',
		'' !== $start ? 'Start ' . $start : '',
	], static fn( $s ) => '' !== $s );

	$rows = $rowfn( 'Gruppe', esc_html( $group ) )
		. $rowfn( 'Anlass', esc_html( implode( ', ', $anlass ) ) )
		. $rowfn( 'Essenswünsche', esc_html( trim( (string) get_post_meta( $req, '_fge_catering_notes', true ) ) ) );

	$program_html = fge_venue_program_html( $req );

	// Wunschtermine nummeriert, damit der Platz je Termin antworten kann und
	// die Antwort in der Pipeline dem richtigen Index zugeordnet wird.
	$labels = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$dates_html = '';
	if ( $labels ) {
		if ( 1 === count( $labels ) ) {
			// Nur ein Wunschtermin: keine Liste, kein „je Termin" (Julius, 07.10.2026).
			$dates_html = '<p style="margin:0 0 4px;font-weight:600;">Wunschtermin</p><p style="margin:0 0 8px;">' . esc_html( (string) reset( $labels ) ) . '</p>'
				. '<p style="margin:0 0 16px;">Sagt uns bitte, ob der Termin bei euch geht, und nennt gern einen Alternativtermin, falls nicht.</p>';
		} else {
			$dates_html = '<p style="margin:0 0 4px;font-weight:600;">Wunschtermine</p><ol style="margin:0 0 8px;padding-left:20px;">';
			foreach ( $labels as $idx => $label ) {
				$dates_html .= '<li value="' . (int) $idx . '" style="margin-bottom:4px;">' . esc_html( (string) $label ) . '</li>';
			}
			$dates_html .= '</ol><p style="margin:0 0 16px;">Sagt uns bitte je Termin, ob er bei euch geht, und nennt gern einen Alternativtermin, falls keiner passt.</p>';
		}
	} else {
		$dates_html = '<p style="margin:0 0 16px;">Terminlich ist der Kunde flexibel, ein Vorschlag von euch reicht.</p>';
	}

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
		<p style="margin:0 0 16px;">wir haben eine Firmenanfrage und schauen gerade, welcher Platz am besten passt.</p>
		' . $program_html . '
		<p style="margin:0 0 4px;font-weight:600;">Eckdaten</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows . '</table>
		' . $dates_html . '
		' . ( '' !== $list ? '<p style="margin:0 0 4px;font-weight:600;">Dazu bräuchten wir je einen Preis</p><ul style="margin:0 0 16px;padding-left:20px;">' . $list . '</ul>' : '' ) . '
		<p style="margin:0 0 16px;">Eine kurze Antwort auf diese Mail genügt: ' . ( count( $labels ) === 1 ? 'geht der Termin' : 'welche Termine gehen' ) . ', und was kostet es bei euch? Wenn eine der Positionen bei euch nicht geht, schreib es einfach dazu.</p>
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
