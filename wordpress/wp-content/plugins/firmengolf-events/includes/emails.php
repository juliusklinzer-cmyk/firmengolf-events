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

	if ( $data['request_type'] === 'specific_event' && $data['partner_email'] !== '' ) {
		fge_send_partner_availability_email( $request_id, $data );
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
	];
}

function fge_send_customer_confirmation_email( int $request_id, array $data ): bool {
	$subject  = 'Deine Firmengolf Event Anfrage ist angekommen';
	$greeting = $data['first_name'] !== '' ? 'Hallo ' . esc_html( $data['first_name'] ) . ',' : 'Hallo,';

	$event_line = '';
	if ( $data['request_type'] === 'specific_event' && $data['event_title'] !== '' ) {
		$event_line = '<p>Deine Anfrage bezieht sich auf: <strong>' . esc_html( $data['event_title'] ) . '</strong></p>';
	}

	$content = '
		<p>' . $greeting . '</p>
		<p>Deine Event-Anfrage bei Firmengolf ist eingegangen. Wir prüfen die Details und melden uns schnell bei dir zurück.</p>
		' . $event_line . '
		<p><strong>Was als Nächstes passiert:</strong></p>
		<ul>
			<li>Wir prüfen passende Angebote und Verfügbarkeiten.</li>
			<li>Du erhältst innerhalb von 1–2 Werktagen eine persönliche Rückmeldung.</li>
		</ul>
		<p>Bei Fragen erreichst du uns jederzeit unter <a href="mailto:events@firmen.golf" style="color:#2a6e32;">events@firmen.golf</a>.</p>
	';

	$sent = wp_mail(
		$data['contact_email'],
		$subject,
		fge_email_wrap( $subject, $content ),
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);

	update_post_meta( $request_id, '_fge_customer_email_sent', $sent ? 1 : 0 );
	if ( $sent ) {
		update_post_meta( $request_id, '_fge_request_status', 'eingangsbestaetigung_gesendet' );
	}

	return $sent;
}

function fge_send_internal_request_email( int $request_id, array $data ): bool {
	$company = $data['company_name'] !== '' ? $data['company_name'] : 'Unbekannt';
	$subject = 'Neue Firmengolf Event Anfrage: ' . $company;
	$to      = apply_filters( 'fge_internal_email', 'events@firmen.golf' );

	$type_label = $data['request_type'] === 'specific_event' ? 'Konkretes Event' : 'Allgemeine Anfrage';

	$dates = array_filter( [ $data['date_1'], $data['date_2'], $data['date_3'] ] );
	$dates_text = $dates ? implode( ', ', $dates ) : ( $data['alt_period'] ?: '—' );

	$rows = [
		'Anfragetyp'  => esc_html( $type_label ),
		'Event'       => esc_html( $data['event_title'] ?: '—' ),
		'Golfplatz'   => esc_html( $data['partner_title'] ?: '—' ),
		'Unternehmen' => esc_html( $company ),
		'Kontakt'     => esc_html( trim( $data['first_name'] . ' ' . $data['last_name'] ) ?: '—' ),
		'E-Mail'      => '<a href="mailto:' . esc_attr( $data['contact_email'] ) . '" style="color:#2a6e32;">' . esc_html( $data['contact_email'] ) . '</a>',
		'Telefon'     => esc_html( $data['phone'] ?: '—' ),
		'Teilnehmer'  => esc_html( $data['participants'] ?: '—' ),
		'Budget'      => esc_html( $data['budget'] ?: '—' ),
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

	$content = '
		<p>Neue Event-Anfrage eingegangen:</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;">' . $table_rows . '</table>
		<p style="margin-top:28px;">
			<a href="' . esc_url( $admin_link ) . '" style="display:inline-block;background:#2a6e32;color:#ffffff;padding:10px 22px;text-decoration:none;border-radius:4px;font-size:14px;">Anfrage im Admin öffnen</a>
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
	$subject = 'Neue Verfügbarkeitsanfrage für ein Firmengolf Event';

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
		<strong>Teilnehmer:</strong> ' . esc_html( $data['participants'] ?: '—' ) . '</p>
		' . $dates_html . '
		<p>Bitte gebt uns kurz Bescheid, ob ihr an den genannten Terminen verfügbar seid. Wir kümmern uns um die weitere Abstimmung mit dem Kunden.</p>
		<p>Vielen Dank,<br>Euer Firmengolf Team</p>
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

function fge_send_onboarding_submitted_email( int $partner_id, string $temp_password = '' ): bool {
	$name       = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$email      = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true );
	$contact    = (string) get_post_meta( $partner_id, '_fge_main_contact_name', true );
	$portal_url = trailingslashit( home_url( '/partnerportal/' ) );
	$admin_url  = admin_url( 'post.php?post=' . $partner_id . '&action=edit' );

	if ( $email === '' ) {
		return false;
	}

	$subject  = 'Dein Golfplatz wurde zur Prüfung eingereicht';
	$greeting = $contact !== '' ? 'Hallo ' . esc_html( $contact ) . ',' : 'Hallo,';

	$pw_notice = '';
	if ( $temp_password !== '' ) {
		$pw_notice = '<p>Deine Zugangsdaten für das Firmengolf Partner-Portal:<br>
			<strong>E-Mail:</strong> ' . esc_html( $email ) . '<br>
			<strong>Passwort:</strong> ' . esc_html( $temp_password ) . '</p>
			<p>Bitte ändere dein Passwort nach dem ersten Login.</p>';
	}

	$content = '
		<p>' . $greeting . '</p>
		<p>Dein Golfplatz-Profil für <strong>' . esc_html( $name ) . '</strong> wurde erfolgreich zur Prüfung eingereicht.</p>
		<p>Firmengolf prüft deine Angaben und meldet sich bei dir, sobald das Profil freigeschaltet ist oder noch Informationen fehlen.</p>
		' . $pw_notice . '
		<p style="margin-top:28px;">
			<a href="' . esc_url( $portal_url ) . '" style="display:inline-block;background:#2a6e32;color:#ffffff;padding:10px 22px;text-decoration:none;border-radius:4px;font-size:14px;">Zum Partner-Portal</a>
		</p>
		<p style="font-size:13px;color:#888;">Bei Fragen erreichst du uns unter <a href="mailto:events@firmen.golf" style="color:#2a6e32;">events@firmen.golf</a>.</p>
	';

	$sent = wp_mail(
		$email,
		$subject,
		fge_email_wrap( $subject, $content ),
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);

	// Internal notification to Firmengolf team.
	$internal_to = apply_filters( 'fge_internal_email', 'events@firmen.golf' );
	$int_content = '
		<p>Neues Golfplatz-Profil eingereicht:</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;">
			<tr><td style="padding:6px 16px 6px 0;color:#555;width:130px;"><strong>Golfplatz</strong></td><td>' . esc_html( $name ) . '</td></tr>
			<tr><td style="padding:6px 16px 6px 0;color:#555;"><strong>Kontakt</strong></td><td>' . esc_html( $contact ) . '</td></tr>
			<tr><td style="padding:6px 16px 6px 0;color:#555;"><strong>E-Mail</strong></td><td><a href="mailto:' . esc_attr( $email ) . '" style="color:#2a6e32;">' . esc_html( $email ) . '</a></td></tr>
		</table>
		<p style="margin-top:28px;">
			<a href="' . esc_url( $admin_url ) . '" style="display:inline-block;background:#2a6e32;color:#ffffff;padding:10px 22px;text-decoration:none;border-radius:4px;font-size:14px;">Im Admin öffnen</a>
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

function fge_format_request_admin_link( int $request_id ): string {
	return admin_url( 'post.php?post=' . $request_id . '&action=edit' );
}

function fge_email_wrap( string $title, string $body_html ): string {
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
  <tr><td style="background:#1a3a1f;padding:20px 32px;">
    <span style="color:#ffffff;font-size:17px;font-weight:bold;letter-spacing:0.03em;">Firmengolf</span>
  </td></tr>
  <tr><td style="padding:32px;color:#1a1a1a;font-size:15px;line-height:1.65;">
    ' . $body_html . '
  </td></tr>
  <tr><td style="background:#f0f0ee;padding:16px 32px;font-size:12px;color:#888;border-top:1px solid #e4e4e0;">
    <p style="margin:0;">Firmengolf &nbsp;·&nbsp; <a href="mailto:events@firmen.golf" style="color:#2a6e32;text-decoration:none;">events@firmen.golf</a></p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
}
