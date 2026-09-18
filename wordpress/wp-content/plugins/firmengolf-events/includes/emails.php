<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// AP10 — E-MAIL PROZESS
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'fge_request_created', 'fge_send_request_emails', 10 );

function fge_send_request_emails( int $request_id ): void {
	$data = fge_get_request_email_data( $request_id );

	if ( $data['contact_email'] === '' ) {
		return;
	}

	fge_send_customer_confirmation_email( $request_id, $data );
	fge_send_internal_request_email( $request_id, $data );

	// Event-Anfrage (specific_event): Der Platz selbst und alle ausgewählten
	// Ansprechpartner bekommen ihren persönlichen Termin-Link zur Abstimmung.
	// Individuelles Event → nur Firmengolf (oben) + Kundenbestätigung.
	if ( $data['request_type'] === 'specific_event' && $data['partner_id'] > 0 ) {
		$sent = fge_send_contact_termin_emails( $request_id, $data );
		// Fallback: keine Abstimmenden (z. B. kein Hauptkontakt mit E-Mail) →
		// wenigstens die einfache Verfügbarkeits-Notiz an den Platz.
		if ( 0 === $sent && $data['partner_email'] !== '' ) {
			fge_send_partner_availability_email( $request_id, $data );
		}
	}
}

function fge_get_request_email_data( int $request_id ): array {
	$m = static function( string $key ) use ( $request_id ): string {
		return (string) get_post_meta( $request_id, '_fge_' . $key, true );
	};

	$request_type = $m( 'request_type' );
	$event_id     = (int) get_post_meta( $request_id, '_fge_assigned_event_id', true );
	$partner_id   = (int) get_post_meta( $request_id, '_fge_assigned_partner_id', true );

	$partner_email = '';
	if ( $partner_id > 0 ) {
		$partner_email = (string) get_post_meta( $partner_id, '_fge_event_contact_email', true );
		if ( $partner_email === '' ) {
			$partner_email = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true );
		}
	}

	return [
		'request_type'  => $request_type,
		'contact_email' => $m( 'contact_email' ),
		'first_name'    => $m( 'contact_first_name' ),
		'last_name'     => $m( 'contact_last_name' ),
		'company_name'  => $m( 'company_name' ),
		'phone'         => $m( 'contact_phone' ),
		'event_id'      => $event_id,
		'event_title'   => $event_id > 0 ? (string) get_the_title( $event_id ) : '',
		'partner_id'    => $partner_id,
		'partner_title' => $partner_id > 0 ? (string) get_the_title( $partner_id ) : '',
		'partner_email' => $partner_email,
		'date_1'        => $m( 'preferred_date_1' ),
		'date_2'        => $m( 'preferred_date_2' ),
		'date_3'        => $m( 'preferred_date_3' ),
		'alt_period'    => $m( 'alternative_period' ),
		'participants'  => $m( 'expected_participants' ),
		'budget'        => $m( 'budget_range' ),
		'message'       => $m( 'message' ),
		'source'        => $m( 'source' ),
		'request_date'  => $m( 'request_date' ),
		// Kontaktwunsch aus dem Formular (E-Mail / Telefon / Egal), fehlte bisher in
		// der internen Mail (Julius, 15.09.2026).
		'contact_pref'  => fge_preferred_contact_label( $m( 'preferred_contact_method' ) ),
	];
}

/** Lesbares Label für den gespeicherten Kontaktwunsch (email / phone / any). */
function fge_preferred_contact_label( string $method ): string {
	$labels = [ 'email' => 'E-Mail', 'phone' => 'Telefon', 'any' => 'Egal' ];
	return $labels[ $method ] ?? '';
}

function fge_send_customer_confirmation_email( int $request_id, array $data ): bool {
	$subject  = 'Deine Anfrage bei Firmengolf ist eingegangen';
	$greeting = $data['first_name'] !== '' ? 'Hallo ' . esc_html( $data['first_name'] ) . ',' : 'Hallo,';
	$events_email = fge_company()['email_events'];

	$event_line = '';
	if ( $data['request_type'] === 'specific_event' && $data['event_title'] !== '' ) {
		$event_line = '<p style="margin:0 0 16px;">Deine Anfrage bezieht sich auf <strong>' . esc_html( $data['event_title'] ) . '</strong>.</p>';
	}

	$ref_line = '';
	$ref      = (string) get_post_meta( $request_id, '_fge_ref', true );
	if ( '' !== $ref ) {
		$ref_line = '<p style="margin:0 0 16px;color:#6C736E;font-size:13px;">Deine Vorgangsnummer: <strong>' . esc_html( $ref ) . '</strong></p>';
	}
	$content = '
		<p style="margin:0 0 16px;">' . $greeting . '</p>
		<p style="margin:0 0 16px;">vielen Dank für deine Anfrage, sie ist bei uns eingegangen und liegt bereits beim richtigen Ansprechpartner.</p>
		' . $event_line . $ref_line . '
		<p style="margin:0 0 8px;"><strong>Wie es jetzt weitergeht</strong></p>
		<ul style="margin:0 0 16px;padding-left:20px;">
			<li style="margin-bottom:6px;">Wir sehen uns deine Angaben in Ruhe an und prüfen passende Optionen.</li>
			<li style="margin-bottom:6px;">Innerhalb eines Werktags meldet sich ein echter Ansprechpartner persönlich bei dir.</li>
		</ul>
		<p style="margin:0 0 16px;">Du musst nichts weiter tun. Fällt dir in der Zwischenzeit noch etwas ein, antworte einfach auf diese E-Mail oder schreib uns an <a href="mailto:' . esc_attr( $events_email ) . '" style="color:#4279D1;">' . esc_html( $events_email ) . '</a>.</p>
		<p style="margin:0 0 22px;">' . fge_email_button( fge_offer_link( $request_id ), 'Status meiner Anfrage ansehen' ) . '</p>
		<p style="margin:24px 0 0;">Sportliche Grüße<br><strong>Dein Firmengolf-Team</strong></p>
	';

	$sent = wp_mail(
		$data['contact_email'],
		$subject,
		fge_email_wrap( $subject, $content ),
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);

	update_post_meta( $request_id, '_fge_customer_email_sent', $sent ? 1 : 0 );
	if ( $sent ) {
		if ( function_exists( 'fge_request_set_status' ) ) { fge_request_set_status( $request_id, 'eingangsbestaetigung_gesendet' ); } else { update_post_meta( $request_id, '_fge_request_status', 'eingangsbestaetigung_gesendet' ); }
	}

	return $sent;
}

/**
 * Wunsch-Leistungen einer Anfrage als E-Mail-HTML, getrennt nach Quelle.
 * Leer, wenn nichts gewählt wurde.
 */
function fge_wishes_email_html( int $request_id ): string {
	if ( ! function_exists( 'fge_request_wish_groups' ) ) {
		return '';
	}
	$g    = fge_request_wish_groups( $request_id );
	$out  = '';
	$pill = static function ( array $items ): string {
		$html = '';
		foreach ( $items as $it ) {
			$html .= '<span style="display:inline-block;background:#EDF3FB;border:1px solid #DCE7F7;border-radius:999px;padding:3px 11px;margin:0 6px 6px 0;font-size:13px;color:#4279D1;">' . esc_html( $it ) . '</span>';
		}
		return $html;
	};
	if ( ! empty( $g['platz'] ) ) {
		$out .= '<p style="margin:0 0 4px;font-weight:600;">Gewünscht am Platz</p><p style="margin:0 0 14px;">' . $pill( $g['platz'] ) . '</p>';
	}
	if ( ! empty( $g['firmengolf'] ) ) {
		$out .= '<p style="margin:0 0 4px;font-weight:600;">Über Firmengolf gewünscht</p><p style="margin:0 0 14px;">' . $pill( $g['firmengolf'] ) . '</p>';
	}
	return $out;
}

function fge_send_internal_request_email( int $request_id, array $data ): bool {
	$company = $data['company_name'] !== '' ? $data['company_name'] : 'Unbekannt';
	$subject = 'Neue Event-Anfrage: ' . $company;
	// Platz ohne Abstimmungskontakt und ohne Mailadresse: niemand beim Platz erfährt
	// von der Anfrage (Audit 18.09.), deshalb deutlicher Hinweis in der internen Mail.
	$no_partner_contact = 'specific_event' === $data['request_type'] && (int) $data['partner_id'] > 0 && '' === $data['partner_email']
		&& function_exists( 'fge_rr_responders' ) && empty( fge_rr_responders( $request_id ) );
	$to      = apply_filters( 'fge_internal_email', fge_company_internal_email() );

	$type_label = $data['request_type'] === 'specific_event' ? 'Konkretes Event' : 'Allgemeine Anfrage';

	$dates = array_filter( [ $data['date_1'], $data['date_2'], $data['date_3'] ] );
	$dates_text = $dates ? implode( ', ', $dates ) : ( $data['alt_period'] ?: 'k. A.' );

	$rows = [
		'Vorgang'     => esc_html( (string) get_post_meta( $request_id, '_fge_ref', true ) ?: 'k. A.' ),
		'Anfragetyp'  => esc_html( $type_label ),
		'Event'       => esc_html( $data['event_title'] ?: 'k. A.' ),
		'Golfplatz'   => esc_html( $data['partner_title'] ?: 'k. A.' ) . ( $no_partner_contact ? ' <strong style="color:#B4332B;">Kein Ansprechpartner mit E-Mail hinterlegt, der Platz wurde nicht informiert. Bitte manuell übernehmen.</strong>' : '' ),
		'Unternehmen' => esc_html( $company ),
		'Kontakt'     => esc_html( trim( $data['first_name'] . ' ' . $data['last_name'] ) ?: 'k. A.' ),
		'E-Mail'      => '<a href="mailto:' . esc_attr( $data['contact_email'] ) . '" style="color:#4279D1;">' . esc_html( $data['contact_email'] ) . '</a>',
		'Telefon'     => esc_html( $data['phone'] ?: 'k. A.' ),
		'Kontaktwunsch' => esc_html( $data['contact_pref'] ?: 'k. A.' ),
		'Teilnehmer'  => esc_html( $data['participants'] ?: 'k. A.' ),
		'Budget'      => esc_html( $data['budget'] ?: 'k. A.' ),
		'Termine'     => esc_html( $dates_text ),
		'Quelle'      => esc_html( $data['source'] ),
		'Eingegangen' => esc_html( $data['request_date'] ),
	];

	$table_rows = '';
	foreach ( $rows as $label => $value ) {
		$table_rows .= '<tr>
			<td style="padding:6px 16px 6px 0;vertical-align:top;width:130px;color:#555;white-space:nowrap;"><strong>' . esc_html( $label ) . '</strong></td>
			<td style="padding:6px 0;color:#1a1a1a;">' . $value . '</td>
		</tr>';
	}

	if ( $data['message'] !== '' ) {
		$table_rows .= '<tr>
			<td style="padding:6px 16px 6px 0;vertical-align:top;color:#555;"><strong>Nachricht</strong></td>
			<td style="padding:6px 0;color:#1a1a1a;">' . nl2br( esc_html( $data['message'] ) ) . '</td>
		</tr>';
	}

	$admin_link = fge_format_request_admin_link( $request_id );
	$wishes_html = fge_wishes_email_html( $request_id );

	$content = '
		<p>Neue Event-Anfrage eingegangen:</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;">' . $table_rows . '</table>
		' . ( $wishes_html !== '' ? '<div style="margin-top:18px;">' . $wishes_html . '</div>' : '' ) . '
		<p style="margin-top:28px;">
			' . fge_email_button( $admin_link, 'Anfrage im Admin öffnen' ) . '
		</p>
	';

	$sent = wp_mail(
		$to,
		$subject,
		fge_email_wrap( $subject, $content ),
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);

	update_post_meta( $request_id, '_fge_internal_email_sent', $sent ? 1 : 0 );

	return $sent;
}

function fge_send_partner_availability_email( int $request_id, array $data ): bool {
	$subject = 'Neue Verfügbarkeitsanfrage: ' . ( $data['company_name'] ?: 'Firmenevent' );

	$dates = array_filter( [ $data['date_1'], $data['date_2'], $data['date_3'] ] );
	$dates_html = '';
	if ( $dates ) {
		$li_items = '';
		foreach ( $dates as $d ) {
			$li_items .= '<li>' . esc_html( $d ) . '</li>';
		}
		$dates_html = '<p><strong>Terminwünsche:</strong></p><ul>' . $li_items . '</ul>';
	} elseif ( $data['alt_period'] !== '' ) {
		$dates_html = '<p><strong>Gewünschter Zeitraum:</strong> ' . esc_html( $data['alt_period'] ) . '</p>';
	}

	$content = '
		<p>Hallo,</p>
		<p>für euer Angebot <strong>' . esc_html( $data['event_title'] ) . '</strong> ist eine neue Anfrage eingegangen.</p>
		<p><strong>Unternehmen:</strong> ' . esc_html( $data['company_name'] ) . '<br>
		<strong>Teilnehmer:</strong> ' . esc_html( $data['participants'] ?: 'k. A.' ) . '</p>
		' . $dates_html . '
		' . ( fge_wishes_email_html( $request_id ) !== '' ? '<div style="margin:0 0 12px;">' . fge_wishes_email_html( $request_id ) . '</div>' : '' ) . '
		<p>Bitte gebt uns kurz Bescheid, ob ihr an den genannten Terminen verfügbar seid. Wir kümmern uns um die weitere Abstimmung mit dem Kunden.</p>
		<p>Vielen Dank,<br>dein Firmengolf-Team</p>
	';

	$sent = wp_mail(
		$data['partner_email'],
		$subject,
		fge_email_wrap( $subject, $content ),
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);

	update_post_meta( $request_id, '_fge_partner_email_sent', $sent ? 1 : 0 );

	return $sent;
}

/**
 * Event-Anfrage: jede vote-Person des Platzes bekommt eine Mail mit ihrem
 * persönlichen Termin-Link (ohne Login). Returns Anzahl gesendeter Mails.
 */
function fge_send_contact_termin_emails( int $request_id, array $data ): int {
	if ( ! function_exists( 'fge_rr_responders' ) || ! function_exists( 'fge_termin_contact_link' ) ) {
		return 0;
	}
	$responders = fge_rr_responders( $request_id );
	if ( empty( $responders ) ) {
		return 0;
	}
	$ref        = fge_request_number( $request_id );
	$dates      = array_filter( [ $data['date_1'], $data['date_2'], $data['date_3'] ] );
	$dates_html = '';
	foreach ( $dates as $d ) {
		$dates_html .= '<li style="margin-bottom:4px;">' . esc_html( $d ) . '</li>';
	}
	$venue       = $data['event_title'] ?: ( $data['partner_title'] ?: 'euer Angebot' );
	$wishes_html = fge_wishes_email_html( $request_id );
	$sent        = 0;
	foreach ( $responders as $c ) {
		if ( '' === (string) ( $c['email'] ?? '' ) ) {
			continue;
		}
		$first   = trim( explode( ' ', (string) $c['name'] )[0] );
		$link    = fge_termin_contact_link( $request_id, $c );
		$subject = 'Eine Firmenanfrage wartet auf deine Rückmeldung (' . $ref . ')';
		$content = '
			<p style="margin:0 0 16px;">Hallo ' . esc_html( $first ) . ',</p>
			<p style="margin:0 0 16px;">für <strong>' . esc_html( $venue ) . '</strong> gibt es eine neue Anfrage von <strong>' . esc_html( $data['company_name'] ?: 'einem Unternehmen' ) . '</strong>' . ( $data['participants'] ? ' (ca. ' . esc_html( $data['participants'] ) . ' Personen)' : '' ) . '.</p>
			<p style="margin:0 0 8px;"><strong>Mögliche Termine:</strong></p>
			<ul style="margin:0 0 18px;padding-left:20px;">' . ( $dates_html ?: '<li>Nach Absprache</li>' ) . '</ul>
			' . ( $wishes_html !== '' ? '<div style="margin:0 0 18px;">' . $wishes_html . '</div>' : '' ) . '
			<p style="margin:0 0 22px;">Sag uns mit einem Klick, welche Termine bei dir gehen, kein Login nötig, der Link ist persönlich für dich.</p>
			<p style="margin:0 0 22px;">' . fge_email_button( $link, 'Jetzt Termine bestätigen' ) . '</p>
			<p style="margin:0;color:#6C736E;font-size:13px;">Anfragenummer ' . esc_html( $ref ) . '</p>
		';
		if ( wp_mail( $c['email'], $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] ) ) {
			$sent++;
		}
	}
	update_post_meta( $request_id, '_fge_contact_emails_sent', $sent );
	return $sent;
}

/** Erinnerung an eine einzelne vote-Person, die noch nicht reagiert hat. */
function fge_send_contact_reminder( int $request_id, array $contact ): bool {
	if ( '' === (string) ( $contact['email'] ?? '' ) || ! function_exists( 'fge_termin_contact_link' ) ) {
		return false;
	}
	$data    = fge_get_request_email_data( $request_id );
	$ref     = fge_request_number( $request_id );
	$first   = trim( explode( ' ', (string) $contact['name'] )[0] );
	$link    = fge_termin_contact_link( $request_id, $contact );
	$venue   = $data['event_title'] ?: ( $data['partner_title'] ?: 'euer Angebot' );
	$subject = 'Erinnerung: kurze Rückmeldung zu einer Firmenanfrage (' . $ref . ')';
	$content = '
		<p style="margin:0 0 16px;">Hallo ' . esc_html( $first ) . ',</p>
		<p style="margin:0 0 16px;">die Anfrage von <strong>' . esc_html( $data['company_name'] ?: 'einem Unternehmen' ) . '</strong> für <strong>' . esc_html( $venue ) . '</strong> wartet noch auf deine Rückmeldung. Es dauert nur einen Moment.</p>
		<p style="margin:0 0 22px;">' . fge_email_button( $link, 'Jetzt Termine bestätigen' ) . '</p>
		<p style="margin:0;color:#6C736E;font-size:13px;">Anfragenummer ' . esc_html( $ref ) . '</p>
	';
	return (bool) wp_mail( $contact['email'], $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Eskalation: Anfrage überfällig → Firmengolf soll übernehmen. */
function fge_notify_overdue( int $request_id ): void {
	$data    = fge_get_request_email_data( $request_id );
	$ref     = fge_request_number( $request_id );
	$to      = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$admin   = function_exists( 'fge_format_request_admin_link' ) ? fge_format_request_admin_link( $request_id ) : admin_url();
	$subject = 'Überfällig: Anfrage ' . $ref . ' braucht Aufmerksamkeit';
	$content = '
		<p style="margin:0 0 16px;">Die Anfrage <strong>' . esc_html( $ref ) . '</strong> (' . esc_html( $data['company_name'] ?: 'k. A.' ) . ' · ' . esc_html( $data['partner_title'] ?: 'k. A.' ) . ') hat die Reaktionsfrist überschritten, noch nicht alle Beteiligten haben reagiert.</p>
		<p style="margin:0 0 16px;">Bitte nachfassen oder die Koordination übernehmen (direkt mit der Firma einen Termin festlegen).</p>
		<p style="margin:0;">' . fge_email_button( $admin, 'Anfrage im Admin öffnen' ) . '</p>
	';
	wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Alle Beteiligten haben reagiert → Platz-Manager soll im Portal den Termin bestätigen. */
add_action( 'fge_request_all_responded', 'fge_notify_all_responded', 10 );
function fge_notify_all_responded( int $request_id ): void {
	// „Alle abgesagt" war eine stille Sackgasse (Audit B1): kein Termin bestätigbar →
	// nicht den Platz zum Bestätigen auffordern, sondern Firmengolf eskalieren.
	if ( function_exists( 'fge_rr_matrix' ) ) {
		$m = fge_rr_matrix( $request_id );
		if ( 'nicht_verfuegbar' === ( $m['overall'] ?? '' ) ) {
			if ( function_exists( 'fge_request_set_status' ) ) {
				fge_request_set_status( $request_id, 'nicht_verfuegbar' );
			}
			$ref     = fge_request_number( $request_id );
			$to      = apply_filters( 'fge_internal_email', fge_company_internal_email() );
			$admin   = function_exists( 'fge_format_request_admin_link' ) ? fge_format_request_admin_link( $request_id ) : admin_url();
			$subject = 'Kein Termin möglich: ' . $ref;
			$content = '<p style="margin:0 0 16px;">Für die Anfrage <strong>' . esc_html( $ref ) . '</strong> haben alle Ansprechpartner alle Wunschtermine abgesagt, es gibt keinen bestätigbaren Termin. Bitte mit dem Kunden Alternativen klären (Alternativvorschläge stehen ggf. in der Anfrage).</p>'
				. '<p style="margin:0;">' . fge_email_button( $admin, 'Anfrage im Admin öffnen' ) . '</p>';
			wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
			return;
		}
	}

	$partner_id = (int) get_post_meta( $request_id, '_fge_assigned_partner_id', true );
	$to         = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true )
		?: (string) get_post_meta( $partner_id, '_fge_event_contact_email', true ); // Fallback (Kern-Audit M10)
	if ( '' === $to ) {
		return;
	}
	$ref    = fge_request_number( $request_id );
	$portal = trailingslashit( home_url( '/partnerportal/' ) ) . '?tab=anfragen&req=' . $request_id;
	$subject = 'Alle Rückmeldungen da, Termin bestätigen (' . $ref . ')';
	$content = '
		<p style="margin:0 0 16px;">Hallo,</p>
		<p style="margin:0 0 16px;">für die Anfrage <strong>' . esc_html( $ref ) . '</strong> haben alle Ansprechpartner reagiert. Du kannst jetzt im Portal den passenden Termin bestätigen.</p>
		<p style="margin:0 0 22px;">' . fge_email_button( $portal, 'Im Portal öffnen' ) . '</p>
	';
	wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Ein Termin wurde bestätigt → Firmengolf benachrichtigen (Angebot & Buchung). */
add_action( 'fge_request_date_confirmed', 'fge_notify_date_confirmed', 10, 2 );
function fge_notify_date_confirmed( int $request_id, int $date_index ): void {
	$data = fge_get_request_email_data( $request_id );
	$ref  = fge_request_number( $request_id );
	$date = (string) get_post_meta( $request_id, '_fge_preferred_date_' . $date_index, true );
	$to   = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$subject = 'Termin bestätigt: ' . $ref . ( $data['partner_title'] ? ' (' . $data['partner_title'] . ')' : '' );

	// Mit unbepreisten Zusatzleistungen geht das Angebot NICHT automatisch raus — Firmengolf muss handeln.
	$needs_review = function_exists( 'fge_offer_needs_review' ) && fge_offer_needs_review( $request_id );
	if ( $needs_review ) {
		// Nur die noch unbepreisten Wünsche nennen (Audit 18.09.), nicht alle.
		$g      = function_exists( 'fge_xs_uncovered_wishes' ) ? fge_xs_uncovered_wishes( $request_id )
			: ( function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $request_id ) : [ 'platz' => [], 'firmengolf' => [] ] );
		$wishes = implode( ', ', array_merge( (array) $g['platz'], (array) $g['firmengolf'] ) );
		$reason = '' !== $wishes
			? 'Der Kunde hat Zusatzleistungen angefragt (' . esc_html( $wishes ) . '), die noch keinen Preis haben.'
			: 'Der Anfrage ist kein bepreistes Event zugeordnet, ein Auto-Angebot wäre „Auf Anfrage" und trotzdem verbindlich buchbar.';
		$next   = '<p style="margin:0 0 16px;"><strong>⚠️ Angebot zurückgehalten:</strong> ' . $reason . ' Bitte die Feinheiten mit dem Kunden klären und danach in der Anfrage auf <strong>„Angebot jetzt senden"</strong> klicken. Der Kunde hat eine Termin-Bestätigung erhalten und wartet auf das Angebot.</p>';
	} else {
		$next = '<p style="margin:0 0 16px;">Das Angebot geht automatisch an den Kunden. Sobald er annimmt, steht der Auftrag und ihr werdet informiert.</p>';
	}

	// „Alle Beteiligten haben zugestimmt" nur, wenn es eine Abstimmung mit Zusagen gab (Audit 18.09.).
	$m_conf   = function_exists( 'fge_rr_matrix' ) ? fge_rr_matrix( $request_id ) : [];
	$all_yes  = ! empty( $m_conf['responders'] ) && ! empty( $m_conf['all_responded'] );
	$content = '
		<p style="margin:0 0 16px;">Für die Anfrage <strong>' . esc_html( $ref ) . '</strong> wurde ein Termin bestätigt' . ( $all_yes ? ', alle Beteiligten haben zugestimmt.' : ( '' !== $data['partner_title'] ? '.' : ' (Selbstplaner-Event ohne Platz-Abstimmung).' ) ) . '</p>
		<p style="margin:0 0 16px;"><strong>Termin:</strong> ' . esc_html( $date ?: 'k. A.' ) . '<br>
		<strong>Platz:</strong> ' . esc_html( $data['partner_title'] ?: 'k. A.' ) . '<br>
		<strong>Unternehmen:</strong> ' . esc_html( $data['company_name'] ?: 'k. A.' ) . '<br>
		<strong>Event:</strong> ' . esc_html( $data['event_title'] ?: 'k. A.' ) . '</p>
		' . $next . '
		<p style="margin:0;">' . fge_email_button( fge_format_request_admin_link( $request_id ), 'Anfrage im Admin öffnen' ) . '</p>
	';
	wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/**
 * Termin-Bestätigung an den Kunden, wenn das Angebot noch NICHT rausgeht
 * (Zusatzleistungen in Feinplanung): der Termin ist fix, das Angebot folgt.
 */
function fge_send_date_confirmation_email( int $request_id, int $date_index ): bool {
	$data = fge_get_request_email_data( $request_id );
	if ( $data['contact_email'] === '' ) {
		return false;
	}
	$ref   = fge_request_number( $request_id );
	$date  = (string) get_post_meta( $request_id, '_fge_preferred_date_' . $date_index, true );
	$greet = $data['first_name'] !== '' ? 'Hallo ' . esc_html( $data['first_name'] ) . ',' : 'Hallo,';
	$g     = function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $request_id ) : [ 'platz' => [], 'firmengolf' => [] ];
	$wish  = '';
	foreach ( array_merge( (array) $g['platz'], (array) $g['firmengolf'] ) as $i ) {
		$wish .= '<li style="margin-bottom:3px;">' . esc_html( $i ) . '</li>';
	}
	$subject = 'Euer Termin steht: ' . ( $data['event_title'] ?: 'Firmen-Event' ) . ' am ' . $date;
	$content = '
		<p style="margin:0 0 16px;">' . $greet . '</p>
		<p style="margin:0 0 16px;">gute Nachrichten: euer Wunschtermin <strong>' . esc_html( $date ) . '</strong> ist ' . ( '' !== $data['partner_title'] ? 'beim Platz ' : '' ) . 'bestätigt, wir halten ihn für euch fest.</p>
		' . ( $wish !== '' ? '<p style="margin:0 0 6px;">Ihr habt Zusatzleistungen angefragt:</p><ul style="margin:0 0 16px;padding-left:20px;">' . $wish . '</ul>' : '' ) . '
		<p style="margin:0 0 16px;">Wir stellen gerade euer komplettes Angebot mit allen Leistungen und Preisen zusammen und melden uns kurzfristig. Ihr müsst nichts weiter tun.</p>
		<p style="margin:0;color:#6C736E;font-size:13px;">Anfragenummer ' . esc_html( $ref ) . '. Bei Fragen einfach auf diese Mail antworten.</p>
	';
	return (bool) wp_mail( $data['contact_email'], $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

// ── Angebots-Mail an den Kunden ───────────────────────────────────────────────

/** Schickt dem Kunden das automatisch erzeugte Angebot mit Annehmen/Ablehnen-Link. */
function fge_send_offer_email( int $request_id ): bool {
	$data = fge_get_request_email_data( $request_id );
	if ( $data['contact_email'] === '' ) {
		return false;
	}
	$snap     = (array) get_post_meta( $request_id, '_fge_offer_snapshot', true );
	$ref      = fge_request_number( $request_id );
	$link     = function_exists( 'fge_offer_link' ) ? fge_offer_link( $request_id ) : home_url();
	$greet    = $data['first_name'] !== '' ? 'Hallo ' . esc_html( $data['first_name'] ) . ',' : 'Hallo,';
	$deadline = (int) get_post_meta( $request_id, '_fge_offer_deadline', true );

	// Angebot als Positionstabelle wie im Dokument (Julius, 17.09.2026): exakte
	// Beträge, USt. nur einmal im Summenblock, das PDF hängt an der Mail.
	$table  = function_exists( 'fge_offer_mail_table_html' ) ? fge_offer_mail_table_html( $request_id ) : '';
	$extras = array_values( (array) ( $snap['extras'] ?? [] ) );
	$pdf    = function_exists( 'fge_offer_pdf_tempfile' ) ? fge_offer_pdf_tempfile( $request_id ) : '';

	$cname         = (string) ( $snap['contact_name'] ?? '' );
	$cphone        = (string) ( $snap['contact_phone'] ?? '' );
	$cmail         = (string) ( $snap['contact_email'] ?? '' );
	$contact_block = ( '' !== $cname )
		? '<p style="margin:0 0 16px;color:#1a1a1a;font-size:13px;">Euer Ansprechpartner: <strong>' . esc_html( $cname ) . '</strong>'
			. ( '' !== $cphone ? ' &nbsp;·&nbsp; ' . esc_html( $cphone ) : '' )
			. ( '' !== $cmail ? ' &nbsp;·&nbsp; <a href="mailto:' . esc_attr( $cmail ) . '" style="color:#4279D1;">' . esc_html( $cmail ) . '</a>' : '' )
			. '</p>'
		: '';

	$wishes = array_merge( (array) ( $snap['wishes_platz'] ?? [] ), (array) ( $snap['wishes_firmengolf'] ?? [] ) );

	$subject = 'Euer Angebot ' . $ref . ': ' . ( (string) ( $snap['event_title'] ?? 'euer Event' ) );
	$content = '
		<p style="margin:0 0 16px;">' . $greet . '</p>
		<p style="margin:0 0 18px;">der Termin steht. Hier ist euer Angebot <strong>' . esc_html( $ref ) . '</strong>, ihr könnt es online mit einem Klick annehmen.' . ( '' !== $pdf ? ' Als PDF findet ihr es auch im Anhang.' : '' ) . '</p>
		' . $table . '
		' . ( ! empty( $extras ) ? '<p style="margin:0 0 6px;color:#555;font-size:12px;">Zusatzleistungen könnt ihr auf der Angebotsseite einzeln abwählen, die Summe passt sich an.</p>' : '' ) . '
		' . ( ! empty( $wishes ) ? '<p style="margin:0 0 6px;color:#555;font-size:12px;">Auf Wunsch zusätzlich organisierbar, wird separat angeboten: ' . esc_html( implode( ', ', array_map( 'strval', $wishes ) ) ) . '.</p>' : '' ) . '
		<p style="margin:0 0 18px;color:#555;font-size:12px;">' . ( $deadline > 0 ? 'Das Angebot ist gültig bis ' . esc_html( wp_date( 'd.m.Y', $deadline ) ) . ', bis dahin halten wir den Termin für euch. ' : '' ) . 'Es gelten unsere <a href="' . esc_url( home_url( '/agb/' ) ) . '" style="color:#4279D1;">AGB</a> inkl. der dort genannten Storno- und Zahlungsbedingungen.</p>
		<p style="margin:0 0 22px;">' . fge_email_button( $link, 'Angebot ansehen & annehmen' ) . '</p>
		' . $contact_block . '
		<p style="margin:0;color:#6C736E;font-size:13px;">Angebotsnummer ' . esc_html( $ref ) . '. Bei Fragen einfach auf diese Mail antworten.</p>
	';
	$sent = wp_mail( $data['contact_email'], $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ], '' !== $pdf ? [ $pdf ] : [] );
	if ( function_exists( 'fge_offer_pdf_cleanup' ) ) {
		fge_offer_pdf_cleanup( $pdf );
	}
	update_post_meta( $request_id, '_fge_offer_email_sent', $sent ? 1 : 0 );
	if ( ! $sent ) {
		// Angebot gilt als versendet, kam aber nicht raus: intern warnen (Audit 18.09.).
		$to_i = apply_filters( 'fge_internal_email', fge_company_internal_email() );
		$ci   = '<p style="margin:0 0 16px;">Die Angebotsmail zu <strong>' . esc_html( $ref ) . '</strong> an ' . esc_html( $data['contact_email'] ) . ' konnte nicht versendet werden. Bitte den Kunden manuell informieren, der Angebots-Link steht in der Anfrage.</p>'
			. '<p style="margin:0;">' . fge_email_button( fge_format_request_admin_link( $request_id ), 'Anfrage im Admin öffnen' ) . '</p>';
		wp_mail( $to_i, 'Angebot nicht zugestellt: ' . $ref, fge_email_wrap( 'Angebot nicht zugestellt: ' . $ref, $ci ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	}
	return (bool) $sent;
}

// ── Rückfrage des Kunden zum Angebot → intern informieren ─────────────────────

add_action( 'fge_offer_query', 'fge_notify_offer_query', 10, 2 );
function fge_notify_offer_query( int $request_id, string $message = '' ): void {
	$ref     = fge_request_number( $request_id );
	$data    = fge_get_request_email_data( $request_id );
	$to      = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$name    = trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
	$subject = 'Rückfrage zum Angebot: ' . $ref;
	$content = '
		<p style="margin:0 0 16px;">Der Kunde hat zum Angebot <strong>' . esc_html( $ref ) . '</strong> eine Rückfrage bzw. einen Änderungswunsch gestellt. Das Angebot bleibt offen, bitte meldet euch mit einer Antwort oder einem angepassten Angebot.</p>
		<p style="margin:0 0 16px;"><strong>Unternehmen:</strong> ' . esc_html( $data['company_name'] ?: 'k. A.' ) . '<br>
		<strong>Kontakt:</strong> ' . esc_html( $name ?: 'k. A.' ) . ' &nbsp;·&nbsp; ' . esc_html( $data['contact_email'] ?: 'k. A.' ) . '</p>
		' . ( '' !== trim( $message ) ? '<p style="margin:0 0 16px;"><strong>Nachricht:</strong><br>' . nl2br( esc_html( $message ) ) . '</p>' : '<p style="margin:0 0 16px;color:#6C736E;">(Keine Nachricht hinterlegt.)</p>' ) . '
		<p style="margin:0;">' . fge_email_button( fge_format_request_admin_link( $request_id ), 'Anfrage im Admin öffnen' ) . '</p>
	';
	wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

// ── Angebot angenommen / abgelehnt → Beteiligte informieren ───────────────────

add_action( 'fge_offer_accepted', 'fge_notify_offer_accepted' );
function fge_notify_offer_accepted( int $request_id ): void {
	$data = fge_get_request_email_data( $request_id );
	$ref  = fge_request_number( $request_id );
	$snap = (array) get_post_meta( $request_id, '_fge_offer_snapshot', true );
	$date = (string) ( $snap['date'] ?? '' );

	// Abrechnungsübersicht der Zusatzleistungen (nur intern: Einkauf und Marge
	// stehen bewusst NUR hier, nie in Kunden- oder Dienstleister-Mails).
	$xs_block = '';
	if ( function_exists( 'fge_xs_priced' ) ) {
		$priced = fge_xs_priced( $request_id );
		if ( ! empty( $priced ) ) {
			$sel = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $request_id ) : [];
			$th  = static function ( $t ) { return '<td style="padding:4px 10px 4px 0;color:#6C736E;font-size:12px;">' . $t . '</td>'; };
			$td  = static function ( $t ) { return '<td style="padding:4px 10px 4px 0;">' . $t . '</td>'; };
			$trs = '<tr>' . $th( 'Leistung' ) . $th( 'Status' ) . $th( 'Kunde zahlt (netto)' ) . $th( 'Einkauf (netto)' ) . $th( 'Dienstleister' ) . '</tr>';
			foreach ( $priced as $src => $it ) {
				$on    = in_array( (int) $src, $sel, true );
				$per   = 'person' === $it['basis'] ? ' p.P.' : ' pauschal';
				$prov  = trim( $it['provider_name'] . ' ' . $it['provider_email'] );
				$trs  .= '<tr>'
					. $td( esc_html( $it['label'] ) )
					. $td( $on ? '<strong style="color:#2C7A3D;">gebucht</strong>' : '<span style="color:#B4332B;">abgewählt</span>' )
					. $td( esc_html( number_format_i18n( fge_xs_sale_price( $it ), 2 ) . ' €' . $per ) )
					. $td( esc_html( number_format_i18n( (float) $it['cost'], 2 ) . ' €' . $per . ' zzgl. ' . number_format_i18n( (float) $it['margin'], 0 ) . ' % Marge' ) )
					. $td( '' !== $prov ? esc_html( $prov ) : '<span style="color:#6C736E;">ohne, freie Position</span>' )
					. '</tr>';
			}
			$tot      = function_exists( 'fge_offer_totals' ) ? fge_offer_totals( $snap, $sel ) : [ 'net' => 0.0, 'ca' => false ];
			$xs_block = '<p style="margin:0 0 6px;font-weight:600;">Abrechnungsübersicht Zusatzleistungen (intern)</p>'
				. '<table style="width:100%;border-collapse:collapse;font-size:13px;line-height:1.5;margin:0 0 8px;">' . $trs . '</table>'
				. ( (float) $tot['net'] > 0 ? '<p style="margin:0 0 6px;"><strong>Rechnungsbetrag an den Kunden (netto, inkl. Event und gebuchter Zusatzleistungen):</strong> ' . esc_html( number_format_i18n( (float) $tot['net'], 2 ) . ' €' . ( $tot['ca'] ? ' bei ' . (int) ( $snap['participants'] ?? 0 ) . ' gebuchten Teilnehmern' : '' ) ) . '</p>' : '' )
				. '<p style="margin:0 0 16px;color:#6C736E;font-size:13px;">Dienstleister mit hinterlegter Mail wurden automatisch beauftragt bzw. bei Abwahl abgesagt.</p>';
		}
	}

	$to = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$ic = '
		<p style="margin:0 0 16px;">Der Kunde hat das Angebot <strong>' . esc_html( $ref ) . '</strong> angenommen. Der Auftrag steht.</p>
		<p style="margin:0 0 16px;"><strong>Termin:</strong> ' . esc_html( $date ?: 'k. A.' ) . '<br>
		<strong>Unternehmen:</strong> ' . esc_html( $data['company_name'] ?: 'k. A.' ) . '<br>
		<strong>Event:</strong> ' . esc_html( $data['event_title'] ?: 'k. A.' ) . '<br>
		<strong>Platz:</strong> ' . esc_html( $data['partner_title'] ?: 'k. A.' ) . '</p>
		' . $xs_block . '
		<p style="margin:0 0 16px;">Bitte Buchung finalisieren und Rechnung anstoßen. Für die automatische Vortags-Info in der Anfrage unter „Schritt 4: Event-Tag" Startzeit, Treffpunkt und Ansprechpartner vor Ort eintragen.</p>
		<p style="margin:0;">' . fge_email_button( fge_format_request_admin_link( $request_id ), 'Anfrage im Admin öffnen' ) . '</p>
	';
	wp_mail( $to, 'Auftrag steht: ' . $ref, fge_email_wrap( 'Auftrag steht: ' . $ref, $ic ), [ 'Content-Type: text/html; charset=UTF-8' ] );

	if ( $data['partner_email'] !== '' ) {
		$pc = '
			<p style="margin:0 0 16px;">Gute Nachricht: der Kunde hat das Event <strong>' . esc_html( $data['event_title'] ?: '' ) . '</strong> am <strong>' . esc_html( $date ?: 'bestätigten Termin' ) . '</strong> verbindlich gebucht.</p>
			<p style="margin:0;">Firmengolf meldet sich für die Detailabstimmung. Bitte den Termin fest einplanen.</p>
		';
		wp_mail( $data['partner_email'], 'Event gebucht: ' . $ref, fge_email_wrap( 'Event gebucht: ' . $ref, $pc ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	}

	if ( $data['contact_email'] !== '' ) {
		$pdf_c = function_exists( 'fge_offer_pdf_tempfile' ) ? fge_offer_pdf_tempfile( $request_id ) : '';
		$cc    = '
			<p style="margin:0 0 16px;">Hallo ' . esc_html( $data['first_name'] ?: '' ) . ', danke für die Zusage.</p>
			<p style="margin:0 0 16px;">Euer Event <strong>' . esc_html( $data['event_title'] ?: '' ) . '</strong> am <strong>' . esc_html( $date ?: '' ) . '</strong> ist gebucht. Wir kümmern uns um die letzten Details und melden uns.</p>
			' . ( $pdf_c !== '' ? '<p style="margin:0 0 16px;">Die Buchungsbestätigung mit allen Leistungen und Preisen findet ihr als PDF im Anhang.</p>' : '' ) . '
			<p style="margin:0;">' . ( function_exists( 'fge_offer_link' ) ? fge_email_button( fge_offer_link( $request_id ), 'Buchung ansehen' ) : '' ) . '</p>
		';
		// Beleg für den Kunden: Link plus PDF (Audit 18.09.: vorher ohne Beleg im Postfach).
		wp_mail( $data['contact_email'], 'Buchung bestätigt: ' . $ref, fge_email_wrap( 'Buchung bestätigt: ' . $ref, $cc ), [ 'Content-Type: text/html; charset=UTF-8' ], '' !== $pdf_c ? [ $pdf_c ] : [] );
		if ( function_exists( 'fge_offer_pdf_cleanup' ) ) {
			fge_offer_pdf_cleanup( $pdf_c );
		}
	}
}

add_action( 'fge_offer_declined', 'fge_notify_offer_declined' );
function fge_notify_offer_declined( int $request_id ): void {
	$data = fge_get_request_email_data( $request_id );
	$ref  = fge_request_number( $request_id );
	$to   = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$content = '
		<p style="margin:0 0 16px;">Der Kunde hat das Angebot <strong>' . esc_html( $ref ) . '</strong> (' . esc_html( $data['company_name'] ?: 'k. A.' ) . ') abgelehnt.</p>
		<p style="margin:0 0 16px;">Bitte nachfassen oder eine Alternative anbieten.</p>
		<p style="margin:0;">' . fge_email_button( fge_format_request_admin_link( $request_id ), 'Anfrage im Admin öffnen' ) . '</p>
	';
	wp_mail( $to, 'Angebot abgelehnt: ' . $ref, fge_email_wrap( 'Angebot abgelehnt: ' . $ref, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

// ── Dienstleister-Mails für Zusatzleistungen (Shuttle, Fotograf …) ────────────
// Positionen mit hinterlegter Dienstleister-Mail lösen bei Kundenannahme
// automatisch Auftrag bzw. Absage aus. Der Dienstleister sieht NUR den mit ihm
// vereinbarten Einkaufspreis, nie Verkaufspreis oder Marge.

/** Eckdaten fürs Dienstleister-Mailing aus dem Angebots-Snapshot. */
function fge_xs_mail_facts( int $req ): array {
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	return [
		'ref'      => fge_request_number( $req ),
		'event'    => (string) ( $snap['event_title'] ?? '' ),
		'date'     => (string) ( $snap['date'] ?? '' ),
		'location' => (string) ( $snap['location'] ?? '' ),
		'pax'      => (int) ( $snap['participants'] ?? 0 ),
	];
}

/** Vereinbarter Einkaufspreis als Text für den Dienstleister. */
function fge_xs_cost_text( array $item, int $pax ): string {
	$s = number_format_i18n( (float) $item['cost'], 2 ) . ' € netto ' . ( 'person' === $item['basis'] ? 'p.P.' : 'pauschal' );
	if ( 'person' === $item['basis'] && $pax > 0 ) {
		$s .= ' bei ' . $pax . ' Personen';
	}
	return $s;
}

/** Auftragsbestätigung an den Dienstleister (Kunde hat die Position mitgebucht). */
function fge_xs_provider_confirmation( int $req, array $item ): bool {
	$f     = fge_xs_mail_facts( $req );
	$co    = function_exists( 'fge_company' ) ? fge_company() : [];
	$greet = '' !== trim( $item['provider_name'] ) ? 'Hallo ' . esc_html( trim( $item['provider_name'] ) ) . ',' : 'Hallo,';
	$row   = static function ( $k, $v ) { return '<tr><td style="padding:5px 16px 5px 0;color:#555;white-space:nowrap;"><strong>' . esc_html( $k ) . '</strong></td><td style="padding:5px 0;color:#1a1a1a;">' . $v . '</td></tr>'; };
	$rows  = $row( 'Leistung', esc_html( $item['label'] ) )
		. ( '' !== $f['date'] ? $row( 'Termin', esc_html( $f['date'] ) ) : '' )
		. ( '' !== $f['location'] ? $row( 'Ort', esc_html( $f['location'] ) ) : '' )
		. ( $f['pax'] > 0 ? $row( 'Teilnehmer', (int) $f['pax'] . ' Personen' ) : '' )
		. $row( 'Vereinbarter Preis', esc_html( fge_xs_cost_text( $item, $f['pax'] ) ) )
		. $row( 'Referenz', esc_html( $f['ref'] ) );

	$subject = 'Auftragsbestätigung Firmengolf: ' . $item['label'] . ( '' !== $f['date'] ? ' am ' . $f['date'] : '' ) . ' (' . $f['ref'] . ')';
	$content = '
		<p style="margin:0 0 16px;">' . $greet . '</p>
		<p style="margin:0 0 16px;">unser Kunde hat verbindlich gebucht. Hiermit beauftragen wir wie besprochen:</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows . '</table>
		<p style="margin:0 0 16px;">Auftraggeber und Rechnungsempfänger ist ' . esc_html( (string) ( $co['legal_name'] ?? 'Firmengolf' ) ) . ', ' . esc_html( (string) ( $co['hq_street'] ?? '' ) ) . ', ' . esc_html( trim( ( $co['hq_zip'] ?? '' ) . ' ' . ( $co['hq_city'] ?? '' ) ) ) . '. Die Rechnung bitte unter Angabe der Referenz ' . esc_html( $f['ref'] ) . ' an <a href="mailto:' . esc_attr( (string) ( $co['email_events'] ?? '' ) ) . '" style="color:#4279D1;">' . esc_html( (string) ( $co['email_events'] ?? '' ) ) . '</a>.</p>
		<p style="margin:0;">Für die Detailabstimmung melden wir uns rechtzeitig. Bei Fragen einfach auf diese Mail antworten.</p>
	';
	return (bool) wp_mail( $item['provider_email'], $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Absage an den Dienstleister (Position abgewählt oder Event nicht zustande gekommen). */
function fge_xs_provider_cancellation( int $req, array $item, string $reason ): bool {
	$f     = fge_xs_mail_facts( $req );
	$greet = '' !== trim( $item['provider_name'] ) ? 'Hallo ' . esc_html( trim( $item['provider_name'] ) ) . ',' : 'Hallo,';
	$subject = 'Absage Firmengolf: ' . $item['label'] . ( '' !== $f['date'] ? ' am ' . $f['date'] : '' ) . ' (' . $f['ref'] . ')';
	$content = '
		<p style="margin:0 0 16px;">' . $greet . '</p>
		<p style="margin:0 0 16px;">danke für euer Angebot zu <strong>' . esc_html( $item['label'] ) . '</strong>' . ( '' !== $f['date'] ? ' am <strong>' . esc_html( $f['date'] ) . '</strong>' : '' ) . '. ' . esc_html( $reason ) . ' Es entsteht kein Auftrag.</p>
		<p style="margin:0;">Wir kommen gern beim nächsten Event wieder auf euch zu.</p>
	';
	return (bool) wp_mail( $item['provider_email'], $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Kunde hat angenommen: gewählte Positionen beauftragen, abgewählte absagen (einmalig). */
add_action( 'fge_offer_accepted', 'fge_xs_notify_providers_on_accept', 20 );
function fge_xs_notify_providers_on_accept( int $req ): void {
	// add_post_meta mit unique=true als atomarer Einmal-Guard (wie _fge_offer_sent).
	if ( ! function_exists( 'fge_xs_priced' ) || ! add_post_meta( $req, '_fge_xs_providers_notified', 1, true ) ) {
		return;
	}
	$sel = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $req ) : [];
	foreach ( fge_xs_priced( $req ) as $src => $item ) {
		if ( ! is_email( $item['provider_email'] ) ) {
			continue;
		}
		if ( in_array( (int) $src, $sel, true ) ) {
			fge_xs_provider_confirmation( $req, $item );
		} else {
			fge_xs_provider_cancellation( $req, $item, 'Der Kunde hat diese Zusatzleistung abgewählt, das übrige Event findet statt.' );
		}
	}
}

/** Angebot abgelehnt oder Anfrage terminal geschlossen: alle Dienstleister absagen (einmalig). */
add_action( 'fge_offer_declined', 'fge_xs_cancel_providers', 20 );
function fge_xs_cancel_providers( int $req ): void {
	if ( ! function_exists( 'fge_xs_priced' ) || ! add_post_meta( $req, '_fge_xs_providers_notified', 1, true ) ) {
		return;
	}
	foreach ( fge_xs_priced( $req ) as $item ) {
		if ( ! is_email( $item['provider_email'] ) ) {
			continue;
		}
		fge_xs_provider_cancellation( $req, $item, 'Das Event kommt leider nicht zustande.' );
	}
}

/** Erinnerung an den Kunden, wenn das Angebot noch offen ist. */
function fge_send_offer_reminder( int $request_id ): bool {
	$data = fge_get_request_email_data( $request_id );
	if ( $data['contact_email'] === '' ) {
		return false;
	}
	$ref  = fge_request_number( $request_id );
	$link = function_exists( 'fge_offer_link' ) ? fge_offer_link( $request_id ) : home_url();
	$snap = (array) get_post_meta( $request_id, '_fge_offer_snapshot', true );
	$content = '
		<p style="margin:0 0 16px;">Hallo ' . esc_html( $data['first_name'] ?: '' ) . ',</p>
		<p style="margin:0 0 16px;">euer Angebot für <strong>' . esc_html( (string) ( $snap['event_title'] ?? 'euer Event' ) ) . '</strong> am <strong>' . esc_html( (string) ( $snap['date'] ?? '' ) ) . '</strong> wartet noch auf eure Rückmeldung.</p>
		<p style="margin:0 0 22px;">' . fge_email_button( $link, 'Angebot ansehen & bestätigen' ) . '</p>
		<p style="margin:0;color:#6C736E;font-size:13px;">Anfragenummer ' . esc_html( $ref ) . '</p>
	';
	return (bool) wp_mail( $data['contact_email'], 'Erinnerung: euer Angebot (' . $ref . ')', fge_email_wrap( 'Erinnerung: euer Angebot (' . $ref . ')', $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

function fge_send_onboarding_submitted_email( int $partner_id, string $temp_password = '' ): bool {
	$name       = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$email      = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true );
	$contact    = (string) get_post_meta( $partner_id, '_fge_main_contact_name', true );
	$portal_url = trailingslashit( home_url( '/partnerportal/' ) );
	$admin_url  = admin_url( 'post.php?post=' . $partner_id . '&action=edit' );

	if ( $email === '' ) {
		return false;
	}

	// Typspezifische Begriffe (Audit 28.08.: vorher überall „Golfplatz").
	$ptype     = function_exists( 'fge_partner_type' ) ? fge_partner_type( $partner_id ) : 'course';
	$term_map  = [ 'course' => 'Golfplatz', 'indoor' => 'Indoor Golf', 'coach' => 'Profil' ];
	$term      = $term_map[ $ptype ] ?? 'Profil';
	$term_prof = 'coach' === $ptype ? 'Profil' : $term . '-Profil';

	$subject  = 'coach' === $ptype ? 'Dein Profil wurde zur Prüfung eingereicht' : ( 'indoor' === $ptype ? 'Euer Indoor Golf wurde zur Prüfung eingereicht' : 'Dein Golfplatz wurde zur Prüfung eingereicht' );
	$greeting = $contact !== '' ? 'Hallo ' . esc_html( $contact ) . ',' : 'Hallo,';

	$pw_notice = '';
	if ( $temp_password !== '' ) {
		$pw_notice = '<p>Deine Zugangsdaten für das Firmengolf Partner-Portal:<br>
			<strong>E-Mail:</strong> ' . esc_html( $email ) . '<br>
			<strong>Passwort:</strong> ' . esc_html( $temp_password ) . '</p>
			<p>Bitte ändere dein Passwort nach dem ersten Login.</p>';
	}

	$ref = function_exists( 'fge_partner_number' ) ? fge_partner_number( $partner_id ) : '';

	$content = '
		<p>' . $greeting . '</p>
		<p>Dein ' . esc_html( $term_prof ) . ' für <strong>' . esc_html( $name ) . '</strong> wurde erfolgreich zur Prüfung eingereicht.</p>
		' . ( $ref !== '' ? '<p style="color:#6C736E;font-size:13px;">Deine Vorgangsnummer: <strong>' . esc_html( $ref ) . '</strong></p>' : '' ) . '
		<p>Firmengolf prüft deine Angaben und meldet sich bei dir, sobald das Profil freigeschaltet ist oder noch Informationen fehlen.</p>
		' . $pw_notice . '
		<p style="margin-top:28px;">
			' . fge_email_button( $portal_url, 'Zum Partner-Portal' ) . '
		</p>
		<p style="font-size:13px;color:#888;">Bei Fragen erreichst du uns unter <a href="mailto:' . esc_attr( fge_company()['email_events'] ) . '" style="color:#4279D1;">' . esc_html( fge_company()['email_events'] ) . '</a>.</p>
	';

	$sent = wp_mail(
		$email,
		$subject,
		fge_email_wrap( $subject, $content ),
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);

	// Internal notification to Firmengolf team.
	$internal_to = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$final_note  = (string) get_post_meta( $partner_id, '_fge_onboarding_final_note', true );
	$int_content = '
		<p>Neues Partner-Profil eingereicht (' . esc_html( $term ) . '):</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;">
			<tr><td style="padding:6px 16px 6px 0;color:#555;width:130px;"><strong>' . esc_html( $term ) . '</strong></td><td>' . esc_html( $name ) . '</td></tr>
			' . ( $ref !== '' ? '<tr><td style="padding:6px 16px 6px 0;color:#555;"><strong>Vorgangs-Nr.</strong></td><td>' . esc_html( $ref ) . '</td></tr>' : '' ) . '
			<tr><td style="padding:6px 16px 6px 0;color:#555;"><strong>Kontakt</strong></td><td>' . esc_html( $contact ) . '</td></tr>
			<tr><td style="padding:6px 16px 6px 0;color:#555;"><strong>E-Mail</strong></td><td><a href="mailto:' . esc_attr( $email ) . '" style="color:#4279D1;">' . esc_html( $email ) . '</a></td></tr>
			' . ( $final_note !== '' ? '<tr><td style="padding:6px 16px 6px 0;color:#555;vertical-align:top;"><strong>Hinweis</strong></td><td>' . nl2br( esc_html( $final_note ) ) . '</td></tr>' : '' ) . '
			' . ( '' !== (string) get_post_meta( $partner_id, '_fge_image_rights_note', true ) ? '<tr><td style="padding:6px 16px 6px 0;color:#555;vertical-align:top;"><strong>Bildrechte</strong></td><td>' . nl2br( esc_html( (string) get_post_meta( $partner_id, '_fge_image_rights_note', true ) ) ) . '</td></tr>' : '' ) . '
			' . ( '' !== (string) get_post_meta( $partner_id, '_fge_coach_gastro_involve', true ) ? '<tr><td style="padding:6px 16px 6px 0;color:#555;"><strong>Gastro einbinden</strong></td><td>' . esc_html( [ 'ja' => 'Ja, über die Anlagen-Gastronomie', 'nein' => 'Nein', 'offen' => 'Später klären' ][ (string) get_post_meta( $partner_id, '_fge_coach_gastro_involve', true ) ] ?? '' ) . '</td></tr>' : '' ) . '
		</table>
		<p style="margin-top:28px;">
			' . fge_email_button( $admin_url, 'Im Admin öffnen' ) . '
		</p>
	';
	wp_mail(
		$internal_to,
		'Neuer Partner eingereicht: ' . $name,
		fge_email_wrap( 'Neuer Partner eingereicht', $int_content ),
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);

	return $sent;
}

/**
 * Branded welcome email for a freshly created partner account.
 * Replaces wp_new_user_notification(): password-set link + portal context.
 */
function fge_send_partner_welcome_email( int $user_id, int $partner_id ): bool {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	$key = get_password_reset_key( $user );
	if ( is_wp_error( $key ) ) {
		return false;
	}
	$set_url    = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
	$name       = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$first      = $user->first_name !== '' ? $user->first_name : $user->display_name;
	$greeting   = $first !== '' ? 'Hallo ' . esc_html( $first ) . ',' : 'Hallo,';

	$subject = 'Willkommen bei Firmengolf: Dein Zugang zum Partner-Portal';
	$content = '
		<p>' . $greeting . '</p>
		<p>schön, dass <strong>' . esc_html( $name ) . '</strong> dabei ist! Dein persönlicher Zugang zum Firmengolf Partner-Portal wurde erstellt.</p>
		<p><strong>Deine Anmelde-E-Mail:</strong> ' . esc_html( $user->user_email ) . '</p>
		<p style="margin-top:28px;">
			' . fge_email_button( $set_url, 'Passwort festlegen' ) . '
		</p>
		<p style="font-size:13px;color:#888;">Der Link ist aus Sicherheitsgründen 24 Stunden gültig. Danach kannst du jederzeit über „Passwort vergessen" auf der Anmeldeseite einen neuen Link anfordern.</p>
		<p>Im Partner-Portal verwaltest du euer Platzprofil, Fotos und alle Event-Anfragen.</p>
		<p style="font-size:13px;color:#888;">Bei Fragen erreichst du uns unter <a href="mailto:' . esc_attr( fge_company()['email_events'] ) . '" style="color:#4279D1;">' . esc_html( fge_company()['email_events'] ) . '</a>.</p>
	';

	return (bool) wp_mail(
		$user->user_email,
		$subject,
		fge_email_wrap( $subject, $content ),
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);
}

/** Notice to an existing account that it has just been linked to a partner (golf course). */
function fge_send_partner_account_linked_email( int $user_id, int $partner_id ): bool {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	$name       = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$portal_url = trailingslashit( home_url( '/partnerportal/' ) );
	$first      = $user->first_name !== '' ? $user->first_name : $user->display_name;
	$greeting   = $first !== '' ? 'Hallo ' . esc_html( $first ) . ',' : 'Hallo,';

	$subject = 'Dein Firmengolf-Konto wurde mit ' . $name . ' verknüpft';
	$content = '
		<p>' . $greeting . '</p>
		<p>dein bestehendes Firmengolf-Konto (<strong>' . esc_html( $user->user_email ) . '</strong>) ist jetzt mit dem Golfplatz <strong>' . esc_html( $name ) . '</strong> verknüpft.</p>
		<p>Du kannst das Profil ab sofort mit deinem gewohnten Login im Partner-Portal verwalten.</p>
		<p style="margin-top:28px;">
			' . fge_email_button( $portal_url, 'Zum Partner-Portal' ) . '
		</p>
		<p style="font-size:13px;color:#888;">Du hast diese Verknüpfung nicht angestoßen? Dann antworte bitte kurz auf diese E-Mail oder kontaktiere uns unter <a href="mailto:' . esc_attr( fge_company()['email_events'] ) . '" style="color:#4279D1;">' . esc_html( fge_company()['email_events'] ) . '</a>.</p>
	';

	return (bool) wp_mail(
		$user->user_email,
		$subject,
		fge_email_wrap( $subject, $content ),
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);
}

// ── Einladungspfad: Start-Mail, Nachfass, Zugangs-Bestätigung ─────────────────

/**
 * Versionierte Einladungs-Mail an den Club (warmer Akquisepfad).
 * Ersetzt die bisher handgeschriebene Start-Mail: Nutzen, Vertrauen und CTA
 * sind damit standardisiert; Versand über das Admin-Modal (partner-invite.php).
 *
 * @param string $to Abweichende Empfängeradresse (Default: Haupt-Kontakt des Partners).
 */
function fge_send_partner_invite_email( int $partner_id, string $to = '', string $asp_name = '' ): bool {
	[ $name, $contact_email, $greeting ] = fge_partner_mail_basics( $partner_id );
	$to = '' !== $to ? $to : $contact_email;
	if ( ! is_email( $to ) || ! function_exists( 'fge_invite_create' ) ) {
		return false;
	}
	// Anrede: eingegebener ASP-Name > Hauptkontakt (nur wenn die Mail wirklich an ihn
	// geht) > neutral. Vorher bekam jeder weitere ASP die Anrede des Hauptkontakts
	// (Audit 2026-07-23, Punkt 4).
	$asp_name = trim( $asp_name );
	if ( '' !== $asp_name ) {
		$greeting = 'Hallo ' . esc_html( $asp_name ) . ',';
	} elseif ( strtolower( trim( $to ) ) !== strtolower( trim( $contact_email ) ) ) {
		$greeting = 'Hallo,';
	}
	// Dedupe (Audit-Punkt 3): offene Einladung derselben Adresse wiederverwenden,
	// sonst je Versand eine eigene Einladung mit eigenem Token (mehrere ASP).
	$reused = function_exists( 'fge_invite_find_open' ) ? fge_invite_find_open( $partner_id, $to ) : '';
	$token  = '' !== $reused ? $reused : fge_invite_create( $partner_id, $to, $asp_name );
	$signup = fge_invite_url_for( $partner_id, $token );
	$c       = fge_company();
	$days    = fge_days_live();
	// Link auf die öffentliche Platzseite nur, wenn sie wirklich schon sichtbar ist
	// (vorbereitete Plätze ohne Event sind noch nicht öffentlich, sonst 410).
	$public_line = ( function_exists( 'fge_partner_is_public' ) && fge_partner_is_public( $partner_id ) )
		? '<p style="font-size:14px;"><a href="' . esc_url( get_permalink( $partner_id ) ) . '" style="color:#4279D1;">Eure öffentliche Platzseite ansehen</a></p>'
		: '';
	// Golflehrer-Einladung (z. B. vom Club aus dem Portal angelegt): eigene
	// Formulierung, die Platz-Texte passen dort nicht (Julius, 02.09.).
	$is_coach_invite = function_exists( 'fge_partner_type' ) && 'coach' === fge_partner_type( $partner_id );
	if ( $is_coach_invite ) {
		$subject = 'Dein Zugang zu Firmengolf: dein Golflehrer-Profil aktivieren';
		$content = '
			<p>' . $greeting . '</p>
			<p>für dich wurde auf Firmengolf ein Golflehrer-Profil vorbereitet (' . esc_html( $name ) . '). Wir sind seit ' . (int) $days . ' Tagen live, Firmen suchen bei uns Golflehrer für Grundlagenkurse, Platzreife-Kurse und Teamevents.</p>
			<p><strong>Dein Profil ist erst sichtbar, sobald du dich anmeldest und es vervollständigst.</strong> Das dauert nur ein paar Minuten.</p>
			<p style="margin-top:28px;">
				' . fge_email_button( $signup, 'Jetzt anmelden und Profil aktivieren' ) . '
			</p>
			<p><strong>Kurz zum System:</strong> Anfragen laufen gebündelt über dein Portal. Für dich ist das komplett kostenlos. Du bekommst genau den Preis, den du angibst. Die Vermittlungsprovision zahlt der Kunde obendrauf, sie geht nie zu deinen Lasten.</p>
			<p style="font-size:13px;color:#888;">Dein Link ist persönlich und einmalig gültig. Fragen? Ruf mich an: <a href="tel:' . esc_attr( $c['phone_tel'] ) . '" style="color:#4279D1;">' . esc_html( $c['phone_display'] ) . '</a> oder antworte einfach auf diese Mail.</p>
		';
	} else {
	$subject = 'Dein Zugang zu Firmengolf: ' . $name . ' aktivieren';
	$content = '
		<p>' . $greeting . '</p>
		<p>wie angekündigt findest du hier deinen persönlichen Zugang zu Firmengolf. Wir sind seit ' . (int) $days . ' Tagen live und die ersten Firmenanfragen kommen bereits rein. Je mehr Plätze dabei sind, desto mehr werden es.</p>
		<p><strong>Dein Platz ist erst sichtbar, sobald du dich anmeldest, ihn aktivierst und dein erstes Event anlegst.</strong> Warte also nicht zu lange.</p>
		<p style="margin-top:28px;">
			' . fge_email_button( $signup, 'Jetzt anmelden und Platz aktivieren' ) . '
		</p>
		' . $public_line . '
		<p><strong>Kurz zum System:</strong> Firmen suchen bei uns Plätze für Teamevents, Turniere und Afterwork. Anfragen laufen gebündelt über dein Portal. Für den Platz ist das komplett kostenlos. Du bekommst genau den Preis, den du angibst. Die Vermittlungsprovision zahlt der Kunde obendrauf, sie geht nie zu deinen Lasten.</p>
		<p style="font-size:13px;color:#888;">Dein Link ist persönlich und einmalig gültig. Fragen? Ruf mich an: <a href="tel:' . esc_attr( $c['phone_tel'] ) . '" style="color:#4279D1;">' . esc_html( $c['phone_display'] ) . '</a> oder antworte einfach auf diese Mail.</p>
	';
	}
	$sent = (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	if ( $sent ) {
		// Erneutes Senden setzt die Nachfass-Uhr bewusst zurück (frische Serie).
		// Name nur überschreiben, wenn diesmal einer angegeben wurde.
		$patch = [ 'sent_at' => time(), 'reminders' => 0, 'status' => 'sent' ];
		if ( '' !== $asp_name ) {
			$patch['name'] = $asp_name;
		}
		fge_invite_update( $partner_id, $token, $patch );
		update_post_meta( $partner_id, '_fge_invite_sent_at', time() );
		update_post_meta( $partner_id, '_fge_invite_sent_to', $to );
	} elseif ( '' === $reused ) {
		// Fehlversand: frisch angelegten Invitee samt Token-Zeile zurückrollen,
		// sonst bleibt ein "versendet"-Geist in der Tabelle (Audit-Punkt 5).
		$list = fge_invitees( $partner_id );
		unset( $list[ $token ] );
		fge_invitees_save( $partner_id, $list );
		delete_post_meta( $partner_id, '_fge_invite_token', $token );
	}
	return $sent;
}

/** Tage seit Go-Live (dynamisch für die Einladungs-Mails). */
function fge_days_live(): int {
	$launch = (int) apply_filters( 'fge_launch_timestamp', mktime( 0, 0, 0, 6, 21, 2026 ) );
	return max( 1, (int) floor( ( time() - $launch ) / DAY_IN_SECONDS ) );
}

/** Beste Kontaktadresse eines Platzes: primärer Portal-User, sonst hinterlegter Hauptkontakt. */
function fge_partner_primary_email( int $partner_id ): string {
	$uid = (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true );
	if ( $uid > 0 ) {
		$u = get_userdata( $uid );
		if ( $u && is_email( $u->user_email ) ) {
			return $u->user_email;
		}
	}
	$mail = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true );
	return is_email( $mail ) ? $mail : '';
}

/**
 * Nachfass an einen einzelnen ASP (Strecke A, Tag 5/10/15). Ton eskaliert leicht
 * über drei Stufen, bleibt freundlich. Empfänger und Link aus dem Invitee-Record.
 */
function fge_send_partner_invite_reminder_email( int $partner_id, string $token = '', int $stage = 1 ): bool {
	[ $name, $contact_email, $greeting ] = fge_partner_mail_basics( $partner_id );
	$invitees = function_exists( 'fge_invitees' ) ? fge_invitees( $partner_id ) : [];
	if ( '' !== $token && isset( $invitees[ $token ] ) ) {
		$to  = (string) $invitees[ $token ]['email'];
		$url = fge_invite_url_for( $partner_id, $token );
		// Anrede wie in der Einladung: ASP-Name > Hauptkontakt (nur bei dessen
		// Adresse) > neutral (Audit-Punkt 4).
		$inv_name = trim( (string) ( $invitees[ $token ]['name'] ?? '' ) );
		if ( '' !== $inv_name ) {
			$greeting = 'Hallo ' . esc_html( $inv_name ) . ',';
		} elseif ( strtolower( trim( $to ) ) !== strtolower( trim( $contact_email ) ) ) {
			$greeting = 'Hallo,';
		}
	} else {
		$to  = (string) get_post_meta( $partner_id, '_fge_invite_sent_to', true ) ?: $contact_email;
		$url = function_exists( 'fge_invite_url' ) ? fge_invite_url( $partner_id ) : home_url( '/' );
	}
	if ( ! is_email( $to ) ) {
		return false;
	}
	$c        = fge_company();
	$stage    = max( 1, min( 3, $stage ) );
	$subjects = [
		1 => 'Kurze Erinnerung: dein Zugang zu Firmengolf wartet',
		2 => 'Dein Platz ' . $name . ' ist noch nicht sichtbar',
		3 => 'Letzte Erinnerung zu deinem Firmengolf-Zugang',
	];
	$intros = [
		1 => 'vor ein paar Tagen habe ich dir deinen persönlichen Zugang zu Firmengolf geschickt. Dein vorbereiteter Platz <strong>' . esc_html( $name ) . '</strong> wartet noch auf die Aktivierung.',
		2 => 'die ersten Firmenanfragen laufen bereits, aber <strong>' . esc_html( $name ) . '</strong> ist noch nicht sichtbar, weil der Zugang noch nicht aktiviert ist. Schade um jede Anfrage, die so vorbeigeht.',
		3 => 'das ist meine letzte Erinnerung zu deinem Firmengolf-Zugang, danach lasse ich dich in Ruhe. Dein vorbereiteter Platz <strong>' . esc_html( $name ) . '</strong> steht weiter bereit, falls du später einsteigen willst.',
	];
	$content = '
		<p>' . $greeting . '</p>
		<p>' . $intros[ $stage ] . '</p>
		<p>Der Einstieg dauert eine Minute: Zugangsdaten festlegen, Platz aktivieren, erstes Event anlegen. Für den Platz komplett kostenlos, die Vermittlungsprovision zahlt der Kunde.</p>
		<p style="margin-top:28px;">
			' . fge_email_button( $url, 'Jetzt anmelden und Platz aktivieren' ) . '
		</p>
		<p>Lieber erst sprechen? Ruf mich an: <a href="tel:' . esc_attr( $c['phone_tel'] ) . '" style="color:#4279D1;">' . esc_html( $c['phone_display'] ) . '</a>.</p>
	';
	return (bool) wp_mail( $to, $subjects[ $stage ], fge_email_wrap( $subjects[ $stage ], $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Aktivierungs-Nachfass (Strecke B, Tag 3): angemeldet, aber noch kein Event. */
function fge_send_partner_activation_nudge_email( int $partner_id ): bool {
	[ $name, , $greeting ] = fge_partner_mail_basics( $partner_id );
	$to = fge_partner_primary_email( $partner_id );
	if ( ! is_email( $to ) ) {
		return false;
	}
	$portal  = function_exists( 'fge_portal_page_url' ) ? fge_portal_page_url() : home_url( '/partnerportal/' );
	$subject = 'Schön, dass du dabei bist: jetzt dein erstes Angebot';
	$content = '
		<p>' . $greeting . '</p>
		<p>schön, dass du dabei bist, dein Zugang für <strong>' . esc_html( $name ) . '</strong> steht. Jetzt fehlt nur dein erstes Angebot, damit dein Platz sichtbar wird und Anfragen bekommt.</p>
		<p>Wir starten gerade richtig durch und die ersten Firmenanfragen kommen rein. Sei von Anfang an dabei, es dauert nur ein paar Minuten.</p>
		<p style="margin-top:28px;">
			' . fge_email_button( $portal, 'Erstes Event anlegen' ) . '
		</p>
	';
	return (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Embed-Promo (Strecke C, ~Tag 12 nach erstem Event): Events auf eigener Webseite zeigen. */
function fge_send_partner_embed_promo_email( int $partner_id ): bool {
	[ $name, , $greeting ] = fge_partner_mail_basics( $partner_id );
	$to = fge_partner_primary_email( $partner_id );
	if ( ! is_email( $to ) ) {
		return false;
	}
	$portal  = function_exists( 'fge_portal_page_url' ) ? fge_portal_page_url() : home_url( '/partnerportal/' );
	$subject = 'Neu: zeig deine Events auf deiner eigenen Webseite';
	$content = '
		<p>' . $greeting . '</p>
		<p>schön, dass <strong>' . esc_html( $name ) . '</strong> jetzt live ist. Neu für dich: Du kannst deine Events von Firmengolf direkt auf deiner eigenen Webseite zeigen.</p>
		<p>Ein kurzer Codeschnipsel genügt und deine buchbaren Formate erscheinen live auf eurer Seite, immer aktuell. Den Schnipsel findest du im Portal unten bei deinen Angeboten.</p>
		<p style="margin-top:28px;">
			' . fge_email_button( $portal, 'Snippet holen' ) . '
		</p>
	';
	return (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/**
 * Zugangs-Bestätigung an den Club nach Einlösung der Einladung.
 * Der Partner hat sein Passwort selbst gesetzt — die Mail ist die schriftliche
 * Spur „dein Zugang existiert, so kommst du wieder rein" (Funnel-Audit 2026-07-12).
 */
function fge_send_invite_access_email( int $user_id, int $partner_id ): bool {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	$name       = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$portal_url = function_exists( 'fge_portal_page_url' ) ? fge_portal_page_url() : trailingslashit( home_url( '/partnerportal/' ) );
	$first      = $user->first_name !== '' ? $user->first_name : $user->display_name;
	$greeting   = $first !== '' ? 'Hallo ' . esc_html( $first ) . ',' : 'Hallo,';

	$subject = 'Dein Zugang zum Firmengolf Partner-Portal ist aktiv';
	$content = '
		<p>' . $greeting . '</p>
		<p>willkommen an Bord! Dein Zugang für <strong>' . esc_html( $name ) . '</strong> ist angelegt und das vorbereitete Profil gehört jetzt euch.</p>
		<p><strong>Deine Anmelde-E-Mail:</strong> ' . esc_html( $user->user_email ) . '<br>
		Das Passwort hast du gerade selbst festgelegt. Solltest du es vergessen, hilft „Passwort vergessen" auf der Anmeldeseite.</p>
		<p style="margin-top:28px;">
			' . fge_email_button( $portal_url, 'Zum Partner-Portal' ) . '
		</p>
		<p>Die nächsten Schritte zeigt dir das Portal direkt an: Profil prüfen, Fotos ansehen, erstes Event-Angebot freischalten. Erst damit geht eure Seite öffentlich live.</p>
		<p style="font-size:13px;color:#888;">Fragen? Antworte einfach auf diese E-Mail oder schreib an <a href="mailto:' . esc_attr( fge_company()['email_partner'] ) . '" style="color:#4279D1;">' . esc_html( fge_company()['email_partner'] ) . '</a>.</p>
	';
	return (bool) wp_mail( $user->user_email, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

// ── Partner-Status-Mails (Freischaltung, Rückfragen, Ablehnung) ──────────────

/** Versendet die passende Mail bei einem Statuswechsel. Kein Wechsel = keine Mail. */
// ── Event-Workflow: Einreichung + Prüfergebnis (Audit A3: lief vorher im Blindflug) ──

/** Interne Mail an Firmengolf, wenn ein Partner ein Event einreicht (neu oder Änderung). */
add_action( 'fge_event_submitted', 'fge_notify_event_submitted', 10, 3 );
function fge_notify_event_submitted( int $event_id, int $partner_id, bool $is_new ): void {
	$to      = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$partner = get_the_title( $partner_id ) ?: ( 'Partner #' . $partner_id );
	$title   = get_the_title( $event_id ) ?: ( 'Event #' . $event_id );
	$subject = ( $is_new ? 'Neues Event-Angebot zur Prüfung: ' : 'Event-Änderung zur Prüfung: ' ) . $title;
	$edit    = admin_url( 'post.php?post=' . $event_id . '&action=edit' );
	$content = '
		<p style="margin:0 0 16px;"><strong>' . esc_html( $partner ) . '</strong> hat ' . ( $is_new ? 'ein neues Event-Angebot eingereicht' : 'ein Event-Angebot überarbeitet' ) . ': <strong>' . esc_html( $title ) . '</strong>.</p>
		<p style="margin:0 0 16px;">Es ist erst öffentlich, wenn ihr es freigebt.</p>
		<p style="margin:0;">' . fge_email_button( $edit, 'Event prüfen und freigeben' ) . '</p>
	';
	wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );

	// Eingangsbestätigung an den Partner (Julius, 2026-07-06): er soll sofort wissen,
	// dass die Einreichung angekommen ist — nicht erst bei Freigabe/Ablehnung.
	$partner_to = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true )
		?: (string) get_post_meta( $partner_id, '_fge_event_contact_email', true );
	if ( '' !== $partner_to && is_email( $partner_to ) ) {
		$portal     = function_exists( 'fge_portal_page_url' ) ? fge_portal_page_url() . '?tab=angebote' : home_url( '/partnerportal/' );
		$p_subject  = ( $is_new ? 'Eingegangen: ' : 'Änderung eingegangen: ' ) . $title;
		$p_content  = '
			<p style="margin:0 0 16px;">' . ( $is_new ? 'Dein Event-Angebot' : 'Deine Änderung am Event-Angebot' ) . ' <strong>' . esc_html( $title ) . '</strong> ist bei uns eingegangen.</p>
			<p style="margin:0 0 16px;">Wir prüfen es kurz und geben es dann frei, du bekommst eine E-Mail, sobald es öffentlich ist. In der Regel dauert das nicht lange.</p>
			<p style="margin:0;">' . fge_email_button( $portal, 'Zum Partnerportal' ) . '</p>
		';
		wp_mail( $partner_to, $p_subject, fge_email_wrap( $p_subject, $p_content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	}
}

/** Mail an den Partner, wenn Firmengolf sein Event freigibt oder ablehnt. */
add_action( 'fge_event_reviewed', 'fge_notify_event_reviewed', 10, 3 );
function fge_notify_event_reviewed( int $event_id, string $status, bool $was_paused = false ): void {
	if ( ! in_array( $status, [ 'freigegeben', 'abgelehnt' ], true ) ) {
		return;
	}
	$partner_id = (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true );
	if ( $partner_id <= 0 ) {
		return; // Selbstplaner-Events von Firmengolf: niemand zu informieren
	}
	$to = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true )
		?: (string) get_post_meta( $partner_id, '_fge_event_contact_email', true );
	if ( '' === $to ) {
		return;
	}
	$title  = get_the_title( $event_id ) ?: 'Dein Event-Angebot';
	$portal = function_exists( 'fge_portal_page_url' ) ? fge_portal_page_url() . '?tab=angebote' : home_url( '/partnerportal/' );
	if ( 'freigegeben' === $status ) {
		$subject = 'Freigegeben: ' . $title;
		$content = '
			<p style="margin:0 0 16px;">Gute Nachricht: dein Angebot <strong>' . esc_html( $title ) . '</strong> ist geprüft und freigegeben' . ( $was_paused ? ', es bleibt aber pausiert, weil du es vor der Bearbeitung pausiert hattest. Du kannst es jederzeit im Portal reaktivieren.' : ' und ab sofort öffentlich auf Firmengolf sichtbar.' ) . '</p>
			<p style="margin:0;">' . fge_email_button( $portal, 'Zum Partnerportal' ) . '</p>
		';
	} else {
		$subject = 'Rückmeldung zu deinem Angebot: ' . $title;
		$content = '
			<p style="margin:0 0 16px;">Wir konnten dein Angebot <strong>' . esc_html( $title ) . '</strong> so noch nicht freigeben. Meist fehlen nur Kleinigkeiten, wir melden uns dazu bei dir, oder du überarbeitest es direkt im Portal und reichst es neu ein.</p>
			<p style="margin:0 0 16px;">' . fge_email_button( $portal, 'Angebot im Portal überarbeiten' ) . '</p>
			<p style="margin:0;color:#6C736E;font-size:13px;">Fragen? Antworte einfach auf diese Mail.</p>
		';
	}
	wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

function fge_notify_partner_status_change( int $partner_id, string $old, string $new ): void {
	if ( $old === $new ) {
		return;
	}
	switch ( $new ) {
		case 'aktiv':
			fge_send_partner_approved_email( $partner_id );
			break;
		case 'rueckfragen':
			fge_send_partner_inquiry_email( $partner_id );
			break;
		case 'abgelehnt':
			fge_send_partner_rejected_email( $partner_id );
			break;
	}
}

/** Name, Empfänger-Mail und Anrede eines Partners für Status-Mails. */
function fge_partner_mail_basics( int $partner_id ): array {
	$name    = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$email   = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true );
	$contact = trim( (string) get_post_meta( $partner_id, '_fge_main_contact_name', true ) );
	return [ $name, $email, $contact !== '' ? 'Hallo ' . esc_html( $contact ) . ',' : 'Hallo,' ];
}

/** „Dein Platz ist live": nach der Freischaltung, mit CTA fürs erste Event. */
function fge_send_partner_approved_email( int $partner_id ): bool {
	[ $name, $email, $greeting ] = fge_partner_mail_basics( $partner_id );
	if ( ! is_email( $email ) ) {
		return false;
	}
	$portal     = trailingslashit( home_url( '/partnerportal/' ) );
	$public_url = (string) get_permalink( $partner_id );
	$new_event  = $portal . '?tab=angebote&portal_action=new&preset_type=teamevent';

	$subject = 'Dein Golfplatz ist jetzt live auf Firmengolf';
	$content = '
		<p>' . $greeting . '</p>
		<p>gute Nachrichten: <strong>' . esc_html( $name ) . '</strong> ist geprüft, freigeschaltet und ab sofort für Unternehmen sichtbar.</p>
		<p style="margin-top:28px;">
			' . fge_email_button( $new_event, 'Erstes Event erstellen' ) . '
		</p>
		<p>Tipp für den Start: Das meistgebuchte Format ist das <strong>Teamevent</strong>. Leg eins an und du bist für Anfragen aus deiner Region sofort buchbar.</p>
		' . ( $public_url !== '' ? '<p style="font-size:13px;color:#888;">Dein öffentliches Profil: <a href="' . esc_url( $public_url ) . '" style="color:#4279D1;">' . esc_html( $public_url ) . '</a></p>' : '' ) . '
		<p style="font-size:13px;color:#888;">Fragen? Antworte einfach auf diese E-Mail oder schreib an <a href="mailto:' . esc_attr( fge_company()['email_partner'] ) . '" style="color:#4279D1;">' . esc_html( fge_company()['email_partner'] ) . '</a>.</p>
	';
	return (bool) wp_mail( $email, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Rückfragen bei der Prüfung: Profil bleibt in der Schwebe, wir melden uns. */
function fge_send_partner_inquiry_email( int $partner_id ): bool {
	[ $name, $email, $greeting ] = fge_partner_mail_basics( $partner_id );
	if ( ! is_email( $email ) ) {
		return false;
	}
	$portal  = trailingslashit( home_url( '/partnerportal/' ) );
	$subject = 'Kurze Rückfragen zu deinem Golfplatz-Profil';
	$content = '
		<p>' . $greeting . '</p>
		<p>bei der Prüfung von <strong>' . esc_html( $name ) . '</strong> sind ein paar Fragen aufgekommen. Wir melden uns dazu in Kürze per E-Mail oder Telefon bei dir.</p>
		<p>Du kannst dein Profil in der Zwischenzeit jederzeit im Partner-Portal anpassen und ergänzen.</p>
		<p style="margin-top:28px;">
			' . fge_email_button( $portal, 'Zum Partner-Portal' ) . '
		</p>
		<p style="font-size:13px;color:#888;">Du erreichst uns direkt unter <a href="mailto:' . esc_attr( fge_company()['email_partner'] ) . '" style="color:#4279D1;">' . esc_html( fge_company()['email_partner'] ) . '</a>. Antworte gern auch einfach auf diese E-Mail.</p>
	';
	return (bool) wp_mail( $email, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Neutrale Ablehnungs-Mail mit Gesprächsangebot. */
function fge_send_partner_rejected_email( int $partner_id ): bool {
	[ $name, $email, $greeting ] = fge_partner_mail_basics( $partner_id );
	if ( ! is_email( $email ) ) {
		return false;
	}
	$subject = 'Dein Golfplatz-Profil bei Firmengolf';
	$content = '
		<p>' . $greeting . '</p>
		<p>danke für dein Interesse an Firmengolf. Nach der Prüfung können wir <strong>' . esc_html( $name ) . '</strong> aktuell leider nicht freischalten.</p>
		<p>Wenn du die Gründe besprechen möchtest oder sich bei euch etwas ändert, melde dich jederzeit. Oft lässt sich gemeinsam ein Weg finden.</p>
		<p style="font-size:13px;color:#888;">Antworte einfach auf diese E-Mail oder schreib an <a href="mailto:' . esc_attr( fge_company()['email_partner'] ) . '" style="color:#4279D1;">' . esc_html( fge_company()['email_partner'] ) . '</a>.</p>
	';
	return (bool) wp_mail( $email, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** Resume-Link fürs unterbrochene Onboarding (Token-Phase, noch kein Account). */
function fge_send_onboarding_resume_email( string $to, string $resume_url, string $name = '' ): bool {
	if ( ! is_email( $to ) ) {
		return false;
	}
	$subject = 'Dein Firmengolf-Onboarding: Hier geht es weiter';
	$content = '
		<p>Hallo,</p>
		<p>dein Stand' . ( $name !== '' ? ' für <strong>' . esc_html( $name ) . '</strong>' : '' ) . ' ist gespeichert. Mit diesem Link machst du genau dort weiter, wo du aufgehört hast:</p>
		<p style="margin-top:28px;">
			' . fge_email_button( $resume_url, 'Onboarding fortsetzen' ) . '
		</p>
		<p style="font-size:13px;color:#888;">Behandle den Link bitte vertraulich, er führt direkt zu deinen Eingaben. Wenn du das Onboarding nicht gestartet hast, ignoriere diese E-Mail einfach.</p>
	';
	return (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

function fge_format_request_admin_link( int $request_id ): string {
	return admin_url( 'post.php?post=' . $request_id . '&action=edit' );
}

/**
 * Einheitlicher CTA-Button für alle Plugin-Mails.
 * Bewusst kompakt (Julius-Feedback: alte Buttons zu groß bzw. nackte Links) —
 * Größenänderungen nur hier, nie per Inline-Style an einzelnen Mails.
 */
function fge_email_button( string $url, string $label ): string {
	// Tabellen-Bauweise, weil Outlook Padding auf <a> ignoriert (sah dort wie ein
	// nackter blauer Kasten aus). Dunklere Unterkante = „drückbarer" 3D-Look.
	return '<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse:separate;display:inline-table;"><tr>'
		. '<td bgcolor="#4279D1" style="border-radius:8px;border-bottom:3px solid #2C55A0;">'
		. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 26px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">' . esc_html( $label ) . '</a>'
		. '</td></tr></table>';
}

function fge_email_wrap( string $title, string $body_html ): string {
	$co       = function_exists( 'fge_company' ) ? fge_company() : [];
	$imp_addr = trim( ( $co['office_street'] ?? '' ) . ', ' . ( $co['office_zip'] ?? '' ) . ' ' . ( $co['office_city'] ?? '' ), ', ' );
	$imprint  = esc_html( (string) ( $co['legal_name'] ?? 'Visionpunch UG (haftungsbeschränkt)' ) )
		. ( '' !== $imp_addr ? ' &nbsp;·&nbsp; ' . esc_html( $imp_addr ) : '' )
		. ( ! empty( $co['managing_director'] ) ? '<br>Geschäftsführer: ' . esc_html( (string) $co['managing_director'] ) : '' )
		. ( ! empty( $co['register_court'] ) ? ' &nbsp;·&nbsp; ' . esc_html( (string) $co['register_court'] . ' ' . ( $co['register_no'] ?? '' ) ) : '' )
		. ( ! empty( $co['ust_id'] ) ? ' &nbsp;·&nbsp; USt-IdNr. ' . esc_html( (string) $co['ust_id'] ) : '' );
	$email_events = (string) ( $co['email_events'] ?? 'events@firmengolf-events.de' );
	return '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . esc_html( $title ) . '</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f2;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f2;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">
  <tr><td style="background:#20294D;padding:20px 32px;">
    <span style="color:#ffffff;font-size:17px;font-weight:bold;letter-spacing:0.03em;">Firmengolf</span>
  </td></tr>
  <tr><td style="padding:32px;color:#1a1a1a;font-size:15px;line-height:1.65;">
    ' . $body_html . '
  </td></tr>
  <tr><td style="background:#f0f0ee;padding:16px 32px;font-size:12px;color:#888;border-top:1px solid #e4e4e0;line-height:1.6;">
    <p style="margin:0 0 6px;">Firmengolf &nbsp;·&nbsp; <a href="mailto:' . $email_events . '" style="color:#4279D1;text-decoration:none;">' . $email_events . '</a></p>
    <p style="margin:0;color:#a0a098;">' . $imprint . '</p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
}
