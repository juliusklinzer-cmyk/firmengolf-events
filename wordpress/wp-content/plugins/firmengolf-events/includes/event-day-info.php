<?php
/**
 * Vortags-Info (Julius, 17.09.2026): Am Tag vor einem gebuchten Event bekommen alle
 * Beteiligten einmalig eine Info-Mail.
 *
 *  - Kunde: „Morgen ist es soweit", Start, Treffpunkt, Ort, Ansprechpartner vor Ort,
 *    Golflehrer, Ablauf, gebuchte Leistungen, Kontakt für den Tag.
 *  - Golfplatz: „Morgen kommt das Team von …", Teilnehmer, Start, Treffpunkt, bei wem
 *    sich die Gruppe meldet, Kundenkontakt, gebuchte und bestätigte Leistungen.
 *  - Firmengolf (events@): dieselbe Zusammenfassung als Kontrolle, inkl. Hinweis,
 *    welche Mails rausgingen.
 *
 * Datenquelle: Angebots-Snapshot (Termin, Ort, Ablauf, Leistungen) plus die Box
 * „Schritt 4: Event-Tag" (Treffpunkt, Startzeit, Ansprechpartner vor Ort, Golflehrer,
 * Hinweise). Auslöser: täglicher Cron (07:00 Site-Zeit) für angenommene Angebote,
 * deren Termin morgen ist (Nachholer: heute). Manuell über den Button in der Box.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Felder ───────────────────────────────────────────────────────────────────

/** Feldliste der Box (Meta-Key ohne Präfix => Label, Typ, Platzhalter). */
function fge_day_fields(): array {
	return [
		'day_start_time'   => [ 'Startzeit', 'text', 'z. B. 12:00 Uhr' ],
		'day_meeting_point' => [ 'Treffpunkt', 'text', 'z. B. Clubhaus, Empfang Golfpark Weidenhof' ],
		'day_onsite_name'  => [ 'Ansprechpartner vor Ort', 'text', 'Name' ],
		'day_onsite_phone' => [ 'Telefon vor Ort', 'text', 'Mobilnummer für den Tag' ],
		'day_pro'          => [ 'Golflehrer / Pro', 'text', 'Name' ],
		'day_bring_along'  => [ 'Mitbringen', 'text', 'z. B. nichts, Schläger und Bälle gibt es vor Ort' ],
		'day_notes'        => [ 'Hinweise', 'textarea', 'z. B. Kleidung, Parken, Regenplan' ],
	];
}

/** Werte der Box, leere Felder mit Vorbelegung aus dem Partnerprofil (Event-Ansprechpartner, Golfschule). */
function fge_day_values( int $req ): array {
	$v = [];
	foreach ( array_keys( fge_day_fields() ) as $k ) {
		$v[ $k ] = trim( (string) get_post_meta( $req, '_fge_' . $k, true ) );
	}
	$partner_id = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
	if ( $partner_id > 0 ) {
		$pm = static fn( string $k ): string => trim( (string) get_post_meta( $partner_id, $k, true ) );
		if ( '' === $v['day_onsite_name'] ) {
			$v['day_onsite_name'] = $pm( '_fge_event_contact_name' ) ?: $pm( '_fge_main_contact_name' );
		}
		if ( '' === $v['day_onsite_phone'] ) {
			$v['day_onsite_phone'] = $pm( '_fge_event_contact_phone' ) ?: $pm( '_fge_main_contact_phone' );
		}
		if ( '' === $v['day_pro'] ) {
			$v['day_pro'] = $pm( '_fge_golf_school_contact_name' );
		}
	}
	if ( '' === $v['day_start_time'] ) {
		$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
		if ( preg_match( '/\b(\d{1,2}[:.]\d{2})\s*Uhr/u', (string) ( $snap['schedule'] ?? '' ), $m ) ) {
			$v['day_start_time'] = str_replace( '.', ':', $m[1] ) . ' Uhr';
		}
	}
	return $v;
}

// ── Metabox ──────────────────────────────────────────────────────────────────

add_action( 'add_meta_boxes', static function () {
	add_meta_box( 'fge_rmb_eventtag', 'Schritt 4: Event-Tag (Vortags-Info)', 'fge_render_rmb_eventtag', 'firmengolf_request', 'normal', 'default' );
}, 30 );

function fge_render_rmb_eventtag( WP_Post $post ): void {
	$req    = $post->ID;
	$v      = fge_day_values( $req );
	$sent   = (string) get_post_meta( $req, '_fge_day_info_sent', true );
	$status = (string) get_post_meta( $req, '_fge_offer_status', true );
	$date   = fge_day_event_date( $req );
	$partner_id = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );

	echo '<p class="description" style="margin:0 0 10px;">Am Tag vor dem Event geht automatisch eine Info an Kunde, Golfplatz und events@ raus (07:00 Uhr). Treffpunkt und Ansprechpartner vor Ort stehen nur hier, bitte nach der Buchung ausfüllen. Vorbelegt aus dem Partnerprofil, wo vorhanden.</p>';

	if ( '' !== $sent ) {
		echo '<p style="margin:0 0 10px;padding:8px 12px;background:#D9F6EC;border-radius:6px;"><strong>Vortags-Info gesendet</strong> am ' . esc_html( wp_date( 'd.m.Y H:i', (int) $sent ) ) . ' Uhr.</p>';
	} elseif ( 'accepted' !== $status ) {
		echo '<p style="margin:0 0 10px;padding:8px 12px;background:#F0F4FA;border-radius:6px;">Wird aktiv, sobald das Angebot angenommen ist.</p>';
	} elseif ( null === $date ) {
		echo '<p style="margin:0 0 10px;padding:8px 12px;background:#FBEFD6;border-radius:6px;">Termin im Angebot nicht als Datum lesbar, die Info kann nur manuell gesendet werden.</p>';
	} else {
		echo '<p style="margin:0 0 10px;padding:8px 12px;background:#F0F4FA;border-radius:6px;">Geplant für <strong>' . esc_html( wp_date( 'd.m.Y', strtotime( $date . ' -1 day' ) ) ) . '</strong> (Vortag von ' . esc_html( wp_date( 'd.m.Y', strtotime( $date ) ) ) . ').' . ( $partner_id <= 0 ? ' <strong>Kein Golfplatz zugeordnet</strong>, die Platz-Mail entfällt.' : '' ) . '</p>';
	}

	echo '<table class="form-table" style="margin:0;">';
	foreach ( fge_day_fields() as $k => [ $label, $type, $ph ] ) {
		// Gespeicherter Wert im Feld, Vorbelegung aus dem Partnerprofil nur als Platzhalter (gilt beim Versand, wenn leer).
		$raw  = trim( (string) get_post_meta( $req, '_fge_' . $k, true ) );
		$hint = ( '' === $raw && '' !== $v[ $k ] ) ? 'Vorbelegt: ' . $v[ $k ] : $ph;
		echo '<tr><th scope="row" style="padding:6px 10px 6px 0;width:190px;"><label for="fge-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td style="padding:6px 0;">';
		if ( 'textarea' === $type ) {
			echo '<textarea id="fge-' . esc_attr( $k ) . '" name="fge_' . esc_attr( $k ) . '" rows="3" class="large-text" placeholder="' . esc_attr( $hint ) . '">' . esc_textarea( $raw ) . '</textarea>';
		} else {
			echo '<input type="text" id="fge-' . esc_attr( $k ) . '" name="fge_' . esc_attr( $k ) . '" value="' . esc_attr( $raw ) . '" class="regular-text" placeholder="' . esc_attr( $hint ) . '">';
		}
		echo '</td></tr>';
	}
	echo '</table>';

	if ( 'accepted' === $status ) {
		// Ablauf-Info: schließt die Lücke zwischen Buchung und Vortag. Geht beim
		// Speichern von allein raus, sobald Startzeit und Treffpunkt stehen.
		$plan_sent = (string) get_post_meta( $req, '_fge_day_plan_sent', true );
		echo '<p style="margin:12px 0 0;">';
		if ( '' !== $plan_sent ) {
			echo '<span style="display:inline-block;margin-right:10px;color:#2C7A3D;">✓ Ablauf-Info an den Kunden am ' . esc_html( wp_date( 'd.m.Y H:i', (int) $plan_sent ) ) . ' Uhr.</span>';
		}
		fge_admin_post_button( 'fge_send_day_plan', [ 'request_id' => $req ], '' !== $plan_sent ? 'Ablauf-Info erneut senden' : 'Ablauf-Info jetzt senden', [
			'class'        => 'button',
			'confirm'      => 'Ablauf-Info mit Treffpunkt und Ansprechpartner jetzt an den Kunden senden? Vorher speichern.',
			'nonce_action' => 'fge_send_day_plan_' . $req,
		] );
		echo '<span class="description" style="margin-left:8px;">Geht an den Kunden, ohne Golflehrer und Ablauf.</span></p>';

		echo '<p style="margin:12px 0 0;">';
		fge_admin_post_button( 'fge_send_day_info', [ 'request_id' => $req ], '' !== $sent ? 'Vortags-Info erneut senden' : 'Vortags-Info jetzt senden', [
			'class'        => 'button' . ( '' === $sent ? ' button-primary' : '' ),
			'confirm'      => 'Vortags-Info jetzt an Kunde, Golfplatz und events@ senden? Vorher speichern, sonst gehen ungespeicherte Felder nicht mit.',
			'nonce_action' => 'fge_send_day_info_' . $req,
		] );
		echo '<span class="description" style="margin-left:8px;">Erst „Aktualisieren", dann senden.</span></p>';
	}
}

add_action( 'save_post', static function ( int $post_id ) {
	if ( ! isset( $_POST['fge_request_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_request_nonce'] ) ), 'fge_request_fields' )
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		|| wp_is_post_revision( $post_id )
		|| ! current_user_can( 'edit_post', $post_id )
		|| get_post_type( $post_id ) !== 'firmengolf_request'
		|| ! isset( $_POST['fge_day_meeting_point'] ) ) {
		return;
	}
	foreach ( fge_day_fields() as $k => [ , $type ] ) {
		$raw = wp_unslash( $_POST[ 'fge_' . $k ] ?? '' );
		$val = 'textarea' === $type ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		if ( '' !== trim( $val ) ) {
			update_post_meta( $post_id, '_fge_' . $k, trim( $val ) );
		} else {
			delete_post_meta( $post_id, '_fge_' . $k );
		}
	}
} );

// ── Termin lesen ─────────────────────────────────────────────────────────────

/** Eventdatum (Y-m-d) aus dem Angebots-Snapshot bzw. dem bestätigten Wunschtermin, oder null. */
function fge_day_event_date( int $req ): ?string {
	$snap  = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$label = (string) ( $snap['date'] ?? '' );
	if ( '' === $label ) {
		$idx   = (int) get_post_meta( $req, '_fge_final_date_index', true );
		$label = $idx > 0 ? (string) get_post_meta( $req, '_fge_preferred_date_' . $idx, true ) : '';
	}
	if ( '' === $label || ! function_exists( 'fge_parse_german_date' ) ) {
		return null;
	}
	$ymd = fge_parse_german_date( $label );
	return $ymd ? substr( $ymd, 0, 4 ) . '-' . substr( $ymd, 4, 2 ) . '-' . substr( $ymd, 6, 2 ) : null;
}

// ── Cron ─────────────────────────────────────────────────────────────────────

add_action( 'init', static function () {
	if ( ! wp_next_scheduled( 'fge_event_day_info_cron' ) ) {
		$tz    = wp_timezone();
		$first = new DateTime( 'tomorrow 07:00', $tz );
		wp_schedule_event( $first->getTimestamp(), 'daily', 'fge_event_day_info_cron' );
	}
} );
add_action( 'fge_event_day_info_cron', 'fge_event_day_info_run' );
register_deactivation_hook( FGE_DIR . 'firmengolf-events.php', static function () {
	$ts = wp_next_scheduled( 'fge_event_day_info_cron' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'fge_event_day_info_cron' );
	}
} );

/** Alle angenommenen Angebote mit Termin morgen (Nachholer: heute) einmalig informieren. */
function fge_event_day_info_run(): array {
	$stats    = [ 'sent' => 0 ];
	$today    = wp_date( 'Y-m-d' );
	$tomorrow = wp_date( 'Y-m-d', strtotime( '+1 day', current_datetime()->getTimestamp() ) );
	$reqs     = get_posts( [
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
		if ( '' !== (string) get_post_meta( $req, '_fge_day_info_sent', true ) ) {
			continue;
		}
		if ( in_array( (string) get_post_meta( $req, '_fge_request_status', true ), [ 'verloren', 'abgeschlossen', 'event_durchgefuehrt', 'angebot_abgelehnt' ], true ) ) {
			continue;
		}
		$date = fge_day_event_date( $req );
		if ( null === $date || ( $date !== $tomorrow && $date !== $today ) ) {
			continue;
		}
		if ( fge_send_day_info( $req, $date === $today ) ) {
			$stats['sent']++;
		}
	}
	return $stats;
}

// ── Manueller Versand ────────────────────────────────────────────────────────

add_action( 'admin_post_fge_send_day_info', static function () {
	$req = absint( $_POST['request_id'] ?? 0 );
	if ( $req <= 0 || ! current_user_can( 'edit_post', $req ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_send_day_info_' . $req );
	$date = fge_day_event_date( $req );
	$ok   = fge_send_day_info( $req, null !== $date && $date === wp_date( 'Y-m-d' ) );
	wp_safe_redirect( add_query_arg( [ 'fge_day_info' => $ok ? 1 : 0 ], get_edit_post_link( $req, 'raw' ) ) );
	exit;
} );
add_action( 'admin_notices', static function () {
	if ( ! isset( $_GET['fge_day_info'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( '1' === (string) $_GET['fge_day_info'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>Vortags-Info gesendet (Kunde, Golfplatz sofern zugeordnet, events@).</p></div>';
	} else {
		echo '<div class="notice notice-error is-dismissible"><p>Vortags-Info konnte nicht gesendet werden (keine Kunden-Mailadresse oder kein Angebot).</p></div>';
	}
} );

// ── Mails ────────────────────────────────────────────────────────────────────

/**
 * Schickt die drei Vortags-Mails. $is_today: Termin ist heute (Nachholer), Wortwahl „heute".
 */
function fge_send_day_info( int $req, bool $is_today = false ): bool {
	$data = fge_get_request_email_data( $req );
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	if ( '' === $data['contact_email'] || empty( $snap ) ) {
		return false;
	}
	$co    = function_exists( 'fge_company' ) ? fge_company() : [];
	$v     = fge_day_values( $req );
	$ref   = fge_request_number( $req );
	$when  = $is_today ? 'heute' : 'morgen';
	$Whend = $is_today ? 'Heute' : 'Morgen';
	$date  = (string) ( $snap['date'] ?? '' );
	$title = (string) ( $snap['event_title'] ?? 'euer Event' );
	$pax   = (int) ( $snap['participants'] ?? 0 );
	$loc   = (string) ( $snap['location'] ?? '' );
	$partner_id = (int) $data['partner_id'];

	// Adresse des Platzes aus dem Partnerprofil (nur wenn zugeordnet).
	$addr = '';
	if ( $partner_id > 0 ) {
		$pm   = static fn( string $k ): string => trim( (string) get_post_meta( $partner_id, $k, true ) );
		$addr = trim( $pm( '_fge_street' ) . ', ' . trim( $pm( '_fge_postal_code' ) . ' ' . $pm( '_fge_city' ) ), ', ' );
	}

	// Gebuchte Leistungen: Leistungsliste plus gewählte Zusatzleistungen.
	$booked = array_values( array_filter( array_map( 'strval', (array) ( $snap['includes'] ?? [] ) ) ) );
	$sel    = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $req ) : [];
	foreach ( (array) ( $snap['extras'] ?? [] ) as $x ) {
		if ( in_array( (int) ( $x['src'] ?? -1 ), $sel, true ) ) {
			$booked[] = (string) ( $x['label'] ?? '' ) . ( 'verbrauch' === (string) ( $x['basis'] ?? '' ) ? ' (nach Verbrauch)' : '' );
		}
	}
	$booked_html = '';
	foreach ( $booked as $b ) {
		$booked_html .= '<li style="margin-bottom:3px;">' . esc_html( $b ) . '</li>';
	}
	// Der Platz bekommt nur, was er selbst erbringt; extern Organisiertes (Fotograf,
	// Gravur) als Hinweis, damit er weiß, wer noch auf der Anlage auftaucht (Audit 28.09.).
	$booked_partner_html = '';
	$extern_labels       = [];
	foreach ( array_values( array_filter( array_map( 'strval', (array) ( $snap['includes'] ?? [] ) ) ) ) as $b ) {
		$booked_partner_html .= '<li style="margin-bottom:3px;">' . esc_html( $b ) . '</li>';
	}
	foreach ( (array) ( $snap['extras'] ?? [] ) as $x ) {
		if ( ! in_array( (int) ( $x['src'] ?? -1 ), $sel, true ) ) {
			continue;
		}
		$lbl = (string) ( $x['label'] ?? '' ) . ( 'verbrauch' === (string) ( $x['basis'] ?? '' ) ? ' (nach Verbrauch)' : '' );
		if ( 'extern' === (string) ( $x['organizer'] ?? 'platz' ) ) {
			$extern_labels[] = (string) ( $x['label'] ?? '' );
		} else {
			$booked_partner_html .= '<li style="margin-bottom:3px;">' . esc_html( $lbl ) . '</li>';
		}
	}
	if ( $extern_labels ) {
		$booked_partner_html .= '<li style="margin-bottom:3px;color:#6C736E;">Zusätzlich über Firmengolf organisiert, nicht durch euch: ' . esc_html( implode( ', ', $extern_labels ) ) . '</li>';
	}

	$row = static function ( string $k, string $v ): string {
		return '' === trim( wp_strip_all_tags( $v ) ) ? '' : '<tr><td style="padding:5px 16px 5px 0;color:#555;white-space:nowrap;vertical-align:top;"><strong>' . esc_html( $k ) . '</strong></td><td style="padding:5px 0;color:#1a1a1a;">' . $v . '</td></tr>';
	};
	$onsite = trim( $v['day_onsite_name'] . ( '' !== $v['day_onsite_phone'] ? ', ' . $v['day_onsite_phone'] : '' ), ', ' );
	$cust   = trim( $data['first_name'] . ' ' . $data['last_name'] );
	$cust_c = trim( $cust . ( '' !== $data['phone'] ? ', ' . $data['phone'] : '' ) . ( '' !== $data['contact_email'] ? ', ' . $data['contact_email'] : '' ), ', ' );
	$fg_contact = (string) ( $co['managing_director'] ?? 'Firmengolf' ) . ( ! empty( $co['whatsapp_display'] ) ? ', mobil ' . $co['whatsapp_display'] : ( ! empty( $co['phone_display'] ) ? ', ' . $co['phone_display'] : '' ) );
	$notes  = '' !== $v['day_notes'] ? nl2br( esc_html( $v['day_notes'] ) ) : '';
	$sched  = '' !== (string) ( $snap['schedule'] ?? '' ) ? nl2br( esc_html( (string) $snap['schedule'] ) ) : '';

	$results = [];
	// Nachholer: Platz- und interne Mail gingen schon raus, nur die Kundenmail ist offen (Audit 18.09.).
	$others_done = '1' === (string) get_post_meta( $req, '_fge_day_info_others_sent', true );

	// 1) Kunde
	$rows = $row( 'Event', esc_html( $title ) )
		. $row( 'Termin', esc_html( $date ) )
		. $row( 'Start', esc_html( $v['day_start_time'] ) )
		. $row( 'Treffpunkt', esc_html( $v['day_meeting_point'] ) )
		. $row( 'Ort', fge_day_location_line( $loc, $addr ) )
		. $row( 'Ansprechpartner vor Ort', esc_html( $onsite ) )
		. $row( 'Golflehrer', esc_html( $v['day_pro'] ) )
		. $row( 'Mitbringen', esc_html( $v['day_bring_along'] ) )
		. $row( 'Ablauf', $sched )
		. $row( 'Teilnehmer', $pax > 0 ? $pax . ' Personen' : '' )
		. $row( 'Hinweise', $notes );
	$subject_c = $Whend . ' ist es soweit: ' . $title . ' am ' . $date;
	$content_c = '
		<p style="margin:0 0 16px;">Hallo ' . esc_html( $data['first_name'] ?: '' ) . ',</p>
		<p style="margin:0 0 16px;">' . $when . ' ist es soweit, wir freuen uns auf euch! Hier noch einmal alles Wichtige für den Tag:</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows . '</table>
		' . ( '' !== $booked_html ? '<p style="margin:0 0 4px;font-weight:600;">Gebucht und bestätigt</p><ul style="margin:0 0 16px;padding-left:20px;">' . $booked_html . '</ul>' : '' ) . '
		<p style="margin:0 0 16px;">Wenn ' . $when . ' etwas dazwischenkommt oder ihr Fragen habt: ' . esc_html( $fg_contact ) . '.</p>
		<p style="margin:0 0 16px;">Bis ' . $when . ' und viel Spaß!</p>
		' . ( function_exists( 'fge_offer_link' ) ? '<p style="margin:0;color:#6C736E;font-size:13px;">Eure Buchung im Überblick: <a href="' . esc_url( fge_offer_link( $req ) ) . '" style="color:#4279D1;">Angebot ' . esc_html( $ref ) . '</a></p>' : '' ) . '
	';
	$results['kunde'] = (bool) wp_mail( $data['contact_email'], $subject_c, fge_email_wrap( $subject_c, $content_c ), [ 'Content-Type: text/html; charset=UTF-8' ] );

	// 2) Golfplatz
	$results['platz'] = null;
	if ( '' !== $data['partner_email'] && ! $others_done ) {
		$company = $data['company_name'] ?: 'unserem Kunden';
		$rows_p  = $row( 'Event', esc_html( $title ) )
			. $row( 'Termin', esc_html( $date ) )
			. $row( 'Start', esc_html( $v['day_start_time'] ) )
			. $row( 'Teilnehmer', $pax > 0 ? $pax . ' Personen' : '' )
			. $row( 'Treffpunkt', esc_html( $v['day_meeting_point'] ) )
			. $row( 'Die Gruppe meldet sich bei', esc_html( $onsite ) )
			. $row( 'Golflehrer', esc_html( $v['day_pro'] ) )
			. $row( 'Bringt die Gruppe mit', esc_html( $v['day_bring_along'] ) )
			. $row( 'Ablauf', $sched )
			. $row( 'Kontakt beim Kunden', esc_html( $cust_c ) )
			. $row( 'Hinweise', $notes );
		$subject_p = $Whend . ': Firmengolf-Event ' . ( $data['company_name'] ?: $ref ) . ' am ' . $date;
		$content_p = '
			<p style="margin:0 0 16px;">Guten Tag,</p>
			<p style="margin:0 0 16px;">' . $when . ' ist das Team von <strong>' . esc_html( $company ) . '</strong> bei Ihnen zu Gast. Hier die Eckdaten:</p>
			<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows_p . '</table>
			' . ( '' !== $booked_partner_html ? '<p style="margin:0 0 4px;font-weight:600;">Gebucht und bestätigt</p><ul style="margin:0 0 16px;padding-left:20px;">' . $booked_partner_html . '</ul>' : '' ) . '
			<p style="margin:0 0 16px;">Falls sich kurzfristig etwas ändert, erreichen Sie uns unter ' . esc_html( $fg_contact ) . '. Vielen Dank und einen schönen Tag!</p>
			<p style="margin:0;color:#6C736E;font-size:13px;">Buchung ' . esc_html( $ref ) . ' · Firmengolf</p>
		';
		$results['platz'] = (bool) wp_mail( $data['partner_email'], $subject_p, fge_email_wrap( $subject_p, $content_p ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	}

	// 3) Firmengolf intern
	$missing = [];
	foreach ( [ 'day_meeting_point' => 'Treffpunkt', 'day_onsite_name' => 'Ansprechpartner vor Ort', 'day_start_time' => 'Startzeit' ] as $k => $label ) {
		if ( '' === $v[ $k ] ) {
			$missing[] = $label;
		}
	}
	$rows_i = $row( 'Anfrage', esc_html( $ref ) )
		. $row( 'Unternehmen', esc_html( $data['company_name'] ) )
		. $row( 'Kontakt', esc_html( $cust_c ) )
		. $row( 'Event', esc_html( $title ) )
		. $row( 'Termin', esc_html( $date ) )
		. $row( 'Start', esc_html( $v['day_start_time'] ) )
		. $row( 'Ort', fge_day_location_line( $loc, $addr ) )
		. $row( 'Platz', esc_html( $data['partner_title'] ) )
		. $row( 'Treffpunkt', esc_html( $v['day_meeting_point'] ) )
		. $row( 'Vor Ort', esc_html( $onsite ) )
		. $row( 'Golflehrer', esc_html( $v['day_pro'] ) )
		. $row( 'Teilnehmer', $pax > 0 ? $pax . ' Personen' : '' );
	$sent_txt  = 'Kunde: ' . ( $results['kunde'] ? 'gesendet' : 'FEHLER' ) . ' · Golfplatz: ' . ( null === $results['platz'] ? 'nicht gesendet (kein Platz mit Mailadresse zugeordnet)' : ( $results['platz'] ? 'gesendet' : 'FEHLER' ) );
	$subject_i = 'Vortags-Info verschickt: ' . $ref . ( $data['company_name'] ? ' (' . $data['company_name'] . ')' : '' );
	$content_i = '
		<p style="margin:0 0 16px;">Die Vortags-Info für <strong>' . esc_html( $ref ) . '</strong> ist raus. ' . esc_html( $sent_txt ) . '.</p>
		' . ( ! empty( $missing ) ? '<p style="margin:0 0 16px;color:#B4332B;"><strong>Fehlende Angaben:</strong> ' . esc_html( implode( ', ', $missing ) ) . '. Die Mails sind ohne diese Zeilen rausgegangen, bitte dem Kunden nachreichen.</p>' : '' ) . '
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows_i . '</table>
		' . ( '' !== $booked_html ? '<p style="margin:0 0 4px;font-weight:600;">Gebucht und bestätigt</p><ul style="margin:0 0 16px;padding-left:20px;">' . $booked_html . '</ul>' : '' ) . '
		<p style="margin:0;">' . fge_email_button( fge_format_request_admin_link( $req ), 'Anfrage im Admin öffnen' ) . '</p>
	';
	if ( ! $others_done ) {
		wp_mail( apply_filters( 'fge_internal_email', fge_company_internal_email() ), $subject_i, fge_email_wrap( $subject_i, $content_i ), [ 'Content-Type: text/html; charset=UTF-8' ] );
		update_post_meta( $req, '_fge_day_info_others_sent', 1 );
	}

	if ( $results['kunde'] ) {
		update_post_meta( $req, '_fge_day_info_sent', time() );
		delete_post_meta( $req, '_fge_day_info_customer_failed' );
	} else {
		// Gate bleibt offen, der Cron versucht die Kundenmail am nächsten Lauf erneut (nur diese).
		update_post_meta( $req, '_fge_day_info_customer_failed', 1 );
	}
	return $results['kunde'];
}

// ── Ablauf-Info direkt nach der Buchung ──────────────────────────────────────
/**
 * Zwischen Buchungsbestätigung und Vortags-Info lagen bisher bis zu acht Tage
 * ohne jeden Kontakt zum Kunden (Befund 22.09.2026), obwohl Treffpunkt und
 * Ansprechpartner oft schon am Tag der Buchung feststehen. Diese Mail schließt
 * die Lücke: sie geht raus, sobald Startzeit und Treffpunkt erstmals gefüllt
 * sind, und kündigt an, dass Golflehrer und Ablauf am Vortag folgen.
 *
 * Vorlage ist die von Hand getippte Mail an die Kundin von FG-26-165.
 */

/**
 * Ort plus Adresse, ohne Dopplung.
 *
 * Der Veranstaltungsort aus dem Angebot enthält oft schon die vollständige
 * Anschrift („Golfpark Weidenhof, Mühlenstraße 140, 25421 Pinneberg"). Die
 * Adresse aus dem Partnerprofil darunter zu wiederholen, sah nach Fehler aus.
 */
function fge_day_location_line( string $loc, string $addr ): string {
	$loc  = trim( $loc );
	$addr = trim( $addr, " \t\n\r\0\x0B," );
	if ( '' === $addr ) {
		return esc_html( $loc );
	}
	if ( '' === $loc ) {
		return esc_html( $addr );
	}
	// Steht die Postleitzahl oder die Straße schon im Ort, reicht der Ort.
	$haystack = mb_strtolower( $loc );
	foreach ( preg_split( '/[,\s]+/', mb_strtolower( $addr ) ) as $part ) {
		if ( mb_strlen( $part ) >= 4 && false !== mb_strpos( $haystack, $part ) ) {
			return esc_html( $loc );
		}
	}
	return esc_html( $loc ) . '<br>' . esc_html( $addr );
}

/** Stehen die Eckdaten für die Ablauf-Info? */
function fge_day_plan_ready( int $req ): bool {
	if ( 'accepted' !== (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
		return false;
	}
	$v = fge_day_values( $req );
	return '' !== $v['day_start_time'] && '' !== $v['day_meeting_point'];
}

/** Ablauf-Info an den Kunden. Gibt false zurück, wenn nichts versendet wurde. */
function fge_send_day_plan( int $req ): bool {
	$data = fge_get_request_email_data( $req );
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	if ( '' === $data['contact_email'] || empty( $snap ) ) {
		return false;
	}
	$co    = function_exists( 'fge_company' ) ? fge_company() : [];
	$v     = fge_day_values( $req );
	$ref   = fge_request_number( $req );
	$title = (string) ( $snap['event_title'] ?? 'euer Event' );
	$date  = (string) ( $snap['date'] ?? '' );
	$loc   = (string) ( $snap['location'] ?? '' );

	$partner_id = (int) $data['partner_id'];
	$addr       = '';
	if ( $partner_id > 0 ) {
		$pm   = static fn( string $k ): string => trim( (string) get_post_meta( $partner_id, $k, true ) );
		$addr = trim( $pm( '_fge_street' ) . ', ' . trim( $pm( '_fge_postal_code' ) . ' ' . $pm( '_fge_city' ) ), ', ' );
	}

	$row = static function ( string $k, string $val ): string {
		return '' === trim( wp_strip_all_tags( $val ) ) ? '' : '<tr><td style="padding:5px 16px 5px 0;color:#555;white-space:nowrap;vertical-align:top;"><strong>' . esc_html( $k ) . '</strong></td><td style="padding:5px 0;color:#1a1a1a;">' . $val . '</td></tr>';
	};
	$onsite = trim( $v['day_onsite_name'] . ( '' !== $v['day_onsite_phone'] ? ', ' . $v['day_onsite_phone'] : '' ), ', ' );
	$when   = trim( $date . ( '' !== $v['day_start_time'] ? ', ' . $v['day_start_time'] : '' ), ', ' );
	$mobile = (string) ( $co['whatsapp_display'] ?? $co['phone_display'] ?? '' );

	$rows = $row( 'Wann', esc_html( $when ) )
		. $row( 'Wo', fge_day_location_line( $loc, $addr ) )
		. $row( 'Treffpunkt', esc_html( $v['day_meeting_point'] ) )
		. $row( 'Ansprechpartner vor Ort', esc_html( $onsite ) )
		. $row( 'Mitbringen', esc_html( $v['day_bring_along'] ) )
		. $row( 'Golflehrer', esc_html( $v['day_pro'] ) );

	// Was am Vortag noch nachkommt, wird ausdrücklich angekündigt statt verschwiegen.
	$open = [];
	if ( '' === $v['day_pro'] ) {
		$open[] = 'wer euer Golflehrer ist';
	}
	if ( '' === trim( (string) ( $snap['schedule'] ?? '' ) ) ) {
		$open[] = 'wie der Ablauf genau aussieht';
	}

	$subject = 'Der Ablauf für euren Eventtag: ' . $title . ' am ' . $date;
	$content = '
		<p style="margin:0 0 16px;">Hallo ' . esc_html( $data['first_name'] ?: '' ) . ',</p>
		<p style="margin:0 0 16px;">der Termin ist fix. Hier alles, was ihr für den Tag braucht:</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows . '</table>
		' . ( $open ? '<p style="margin:0 0 16px;">' . esc_html( ucfirst( implode( ' und ', $open ) ) ) . ', schicken wir euch einen Tag vor der Veranstaltung.</p>' : '' ) . '
		' . ( '' !== $mobile ? '<p style="margin:0 0 16px;">Falls am Tag selbst etwas ist, erreicht ihr uns unter ' . esc_html( $mobile ) . '.</p>' : '' ) . '
		<p style="margin:0 0 16px;">Viel Spaß bei eurem Teamevent!</p>
		' . ( function_exists( 'fge_offer_link' ) ? '<p style="margin:0;color:#6C736E;font-size:13px;">Eure Buchung im Überblick: <a href="' . esc_url( fge_offer_link( $req ) ) . '" style="color:#4279D1;">Buchung ' . esc_html( $ref ) . '</a></p>' : '' ) . '
	';

	if ( function_exists( 'fge_mail_log_context' ) ) {
		fge_mail_log_context( $req, 'day_plan_customer' );
	}
	$ok = (bool) wp_mail( $data['contact_email'], $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	if ( function_exists( 'fge_mail_log_context_clear' ) ) {
		fge_mail_log_context_clear();
	}

	if ( $ok ) {
		update_post_meta( $req, '_fge_day_plan_sent', time() );
		if ( function_exists( 'fge_activity_add' ) ) {
			fge_activity_add( $req, 'system', 'Ablauf-Info an den Kunden gesendet' );
		}
	}
	return $ok;
}

// Automatik: sobald Startzeit und Treffpunkt erstmals stehen, einmalig.
add_action( 'save_post', static function ( int $post_id ) {
	if ( ! isset( $_POST['fge_day_meeting_point'] )
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		|| wp_is_post_revision( $post_id )
		|| get_post_type( $post_id ) !== 'firmengolf_request'
		|| ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( '' !== (string) get_post_meta( $post_id, '_fge_day_plan_sent', true ) ) {
		return; // Gate: nur einmal von allein.
	}
	if ( function_exists( 'fge_is_demo_request' ) && fge_is_demo_request( $post_id ) ) {
		return;
	}
	if ( fge_day_plan_ready( $post_id ) ) {
		fge_send_day_plan( $post_id );
	}
}, 30 );

add_action( 'admin_post_fge_send_day_plan', static function () {
	$req = absint( $_POST['request_id'] ?? 0 );
	if ( $req <= 0 || ! current_user_can( 'edit_post', $req ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_send_day_plan_' . $req );
	$ok = fge_send_day_plan( $req );
	wp_safe_redirect( add_query_arg( [ 'fge_day_plan' => $ok ? 1 : 0 ], get_edit_post_link( $req, 'raw' ) ) );
	exit;
} );

add_action( 'admin_notices', static function () {
	if ( ! isset( $_GET['fge_day_plan'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( '1' === (string) $_GET['fge_day_plan'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>Ablauf-Info an den Kunden gesendet.</p></div>';
	} else {
		echo '<div class="notice notice-error is-dismissible"><p>Ablauf-Info konnte nicht gesendet werden (keine Kunden-Mailadresse oder kein Angebot).</p></div>';
	}
} );
