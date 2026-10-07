<?php
/**
 * Control Center: die Knöpfe.
 *
 * Jede Aktion läuft über admin_post mit Nonce und Rechteprüfung und kehrt
 * danach ins Control Center zurück, nicht ins WordPress-Backend. Die Logik
 * selbst wird wiederverwendet, nicht nachgebaut: Angebotsversand liegt in
 * scheduling.php, die Mails in emails.php und event-day-info.php.
 *
 * Leitplanke aus dem Plan: keine Aktion ohne sichtbare Folge. Was ein Knopf
 * auslöst, steht vorher darunter (fge_cc_action_preview) und hinterher im
 * Postausgang und in der Zeitleiste.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Meldungen nach einer Aktion. */
function fge_cc_messages(): array {
	return [
		'offer_sent'   => [ 'ok', 'Angebot ist raus. Der Kunde hat Mail und PDF, du das BCC.' ],
		'day_plan'     => [ 'ok', 'Ablauf-Info an den Kunden gesendet.' ],
		'day_info'     => [ 'ok', 'Vortags-Info gesendet.' ],
		'status'       => [ 'ok', 'Status geändert.' ],
		'note'         => [ 'ok', 'Notiz gespeichert.' ],
		'saved'        => [ 'ok', 'Gespeichert.' ],
		'snoozed'      => [ 'ok', 'Vorgang schlummert und taucht später wieder auf.' ],
		'woken'        => [ 'ok', 'Vorgang schlummert nicht mehr.' ],
		'no_date'      => [ 'err', 'Es ist noch kein Termin bestätigt.' ],
		'already_sent' => [ 'err', 'Für diese Anfrage wurde bereits ein Angebot versendet.' ],
		'no_price'     => [ 'err', 'Ohne Preis kein Angebot. Erst in Schritt 2 bepreisen.' ],
		'no_event'     => [ 'err', 'Das Angebot konnte nicht erstellt werden, bitte im WordPress-Backend nachsehen.' ],
		'mail_failed'  => [ 'err', 'Die Mail konnte nicht verschickt werden. Adresse prüfen.' ],
		'nothing'      => [ 'err', 'Nichts zu tun.' ],
		'venue_added'    => [ 'ok', 'Platz in die Liste aufgenommen.' ],
		'venue_asked'    => [ 'ok', 'Platz ist angefragt.' ],
		'venue_reply'    => [ 'ok', 'Antwort des Platzes festgehalten.' ],
		'venue_reply_sent' => [ 'ok', 'Antwort festgehalten. Der Platz hat die Bestätigung mit Terminen, Preisen und Reservierungsbitte bekommen.' ],
		'venue_chosen'   => [ 'ok', 'Platz gewählt und der Anfrage zugeordnet.' ],
		'venue_declined' => [ 'ok', 'Absage ist raus.' ],
		'venue_summary'  => [ 'ok', 'Bestätigung mit Reservierungsbitte ist beim Platz.' ],
		'venue_removed'  => [ 'ok', 'Platz aus der Liste entfernt.' ],
		'venue_dupe'     => [ 'err', 'Dieser Platz steht schon in der Liste.' ],
		'venue_nomail'   => [ 'err', 'Dieser Platz hat keine Kontaktmail. Bitte telefonisch und hier festhalten.' ],
		'relaunched'     => [ 'ok', 'Neues Angebot ist raus. Die alte Fassung liegt im Archiv.' ],
		'catalog_sent'   => [ 'ok', 'Vorschlag ist beim Platz. Er kann direkt aus der Mail antworten.' ],
		'catalog_failed' => [ 'err', 'Der Vorschlag konnte nicht verschickt werden.' ],
		'date_offer'     => [ 'ok', 'Termin bestätigt, das Angebot ist beim Kunden.' ],
		'date_hold'      => [ 'ok', 'Termin bestätigt. Das Angebot wird zurückgehalten, bis die Positionen bepreist sind. Der Kunde hat eine Termin-Bestätigung bekommen.' ],
		'already_final'  => [ 'err', 'Für diese Anfrage ist bereits ein Termin bestätigt.' ],
		'task_added'     => [ 'ok', 'Aufgabe notiert.' ],
		'task_done'      => [ 'ok', 'Aufgabe abgehakt.' ],
		'relaunch_failed' => [ 'err', 'Das Angebot konnte nicht neu aufgelegt werden.' ],
		'venue_exists'    => [ 'err', 'Dieser Platz steht schon in der Liste.' ],
		'geo_saved'       => [ 'ok', 'Kundenstandort gesetzt, die Plätze in der Nähe sind aktualisiert.' ],
		'contact_saved'   => [ 'ok', 'Kontakt beim Platz ergänzt.' ],
		'contact_invalid' => [ 'err', 'Die Mailadresse sieht nicht gültig aus, Kontakt nicht gespeichert.' ],
		'calc_saved'      => [ 'ok', 'Kalkulation gespeichert.' ],
	];
}

/** Gemeinsamer Rückweg. */
function fge_cc_redirect( int $req, string $msg ): void {
	wp_safe_redirect( fge_cc_url( 'anfragen', [ 'req' => $req, 'msg' => $msg ] ) );
	exit;
}

/** Rechte und Nonce für jede Aktion, liefert die Anfrage-ID. */
function fge_cc_guard( string $action ): int {
	$req = absint( $_POST['request_id'] ?? 0 );
	if ( $req <= 0 || ! fge_cc_can() || ! current_user_can( 'edit_post', $req ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( $action . '_' . $req );
	return $req;
}

// ── Angebot senden ───────────────────────────────────────────────────────────

add_action( 'admin_post_fge_cc_offer_send', static function (): void {
	$req = fge_cc_guard( 'fge_cc_offer_send' );
	$idx = function_exists( 'fge_rr_final_index' ) ? fge_rr_final_index( $req ) : 0;

	if ( $idx < 1 ) {
		fge_cc_redirect( $req, 'no_date' );
	}
	if ( '1' === (string) get_post_meta( $req, '_fge_offer_sent', true ) ) {
		fge_cc_redirect( $req, 'already_sent' );
	}
	if ( function_exists( 'fge_offer_is_priced' ) && ! fge_offer_is_priced( $req ) ) {
		fge_cc_redirect( $req, 'no_price' );
	}

	update_post_meta( $req, '_fge_offer_review_done', 1 );
	// Die Kennungen fürs Protokoll setzen die Mailfunktionen selbst, damit die
	// Angebotsmail überall gleich heißt, egal von wo sie ausgelöst wird.
	if ( function_exists( 'fge_offer_on_date_confirmed' ) ) {
		fge_offer_on_date_confirmed( $req, $idx );
	}
	if ( '1' !== (string) get_post_meta( $req, '_fge_offer_sent', true ) ) {
		fge_cc_redirect( $req, 'no_event' );
	}
	fge_activity_add( $req, 'offer', 'Angebot aus dem Control Center gesendet' );
	fge_cc_redirect( $req, 'offer_sent' );
} );

// ── Termin bestätigen ────────────────────────────────────────────────────────

/**
 * Freier Wunschtermin-Platz, 0 wenn alle drei belegt sind.
 * Julius verhandelt Termine oft telefonisch, dann passt keiner der drei.
 */
function fge_cc_free_date_slot( int $req ): int {
	for ( $i = 1; $i <= 3; $i++ ) {
		if ( '' === trim( (string) get_post_meta( $req, '_fge_preferred_date_' . $i, true ) ) ) {
			return $i;
		}
	}
	return 0;
}

add_action( 'admin_post_fge_cc_confirm_date', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_confirm_date' );
	$idx  = absint( $_POST['date_index'] ?? 0 );
	$free = sanitize_text_field( wp_unslash( $_POST['free_date'] ?? '' ) );

	// Anderer Termin: in einen freien Wunschtermin-Platz schreiben, damit
	// Angebot, Snapshot und Vortags-Info denselben Weg gehen wie sonst.
	if ( '' !== trim( $free ) ) {
		$slot = fge_cc_free_date_slot( $req );
		if ( $slot < 1 ) {
			set_transient( 'fge_cc_err_' . $req, 'Alle drei Wunschtermin-Felder sind belegt. Einen davon im WordPress-Backend überschreiben.', 60 );
			fge_cc_redirect( $req, 'no_date' );
		}
		update_post_meta( $req, '_fge_preferred_date_' . $slot, trim( $free ) );
		$idx = $slot;
	}

	if ( $idx < 1 || '' === trim( (string) get_post_meta( $req, '_fge_preferred_date_' . $idx, true ) ) ) {
		fge_cc_redirect( $req, 'no_date' );
	}
	if ( '1' === (string) get_post_meta( $req, '_fge_offer_sent', true ) ) {
		fge_cc_redirect( $req, 'already_sent' );
	}
	// Zweiter Klick darf den Termin nicht neu setzen und die Bestätigungen
	// nicht erneut schicken (gleiche Sperre wie im WordPress-Backend).
	if ( ( function_exists( 'fge_rr_final_index' ) && fge_rr_final_index( $req ) > 0 )
		|| '1' === (string) get_post_meta( $req, '_fge_offer_hold', true ) ) {
		fge_cc_redirect( $req, 'already_final' );
	}

	if ( function_exists( 'fge_rr_set_final' ) ) {
		fge_rr_set_final( $req, $idx );
	}
	do_action( 'fge_request_date_confirmed', $req, $idx );

	$sent = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	fge_activity_add( $req, 'offer', sprintf(
		'Termin bestätigt: %s%s',
		(string) get_post_meta( $req, '_fge_preferred_date_' . $idx, true ),
		$sent ? ', Angebot ist raus' : ', Angebot zurückgehalten'
	) );
	fge_cc_redirect( $req, $sent ? 'date_offer' : 'date_hold' );
} );

// ── Katalog-Vorschlag an den Platz ───────────────────────────────────────────

add_action( 'admin_post_fge_cc_catalog', static function (): void {
	$req = fge_cc_guard( 'fge_cc_catalog' );
	$err = fge_catalog_send_proposal( $req );
	if ( '' !== $err ) {
		set_transient( 'fge_cc_err_' . $req, $err, 60 );
		fge_cc_redirect( $req, 'catalog_failed' );
	}
	fge_cc_redirect( $req, 'catalog_sent' );
} );

// ── Angebot neu auflegen ─────────────────────────────────────────────────────

add_action( 'admin_post_fge_cc_offer_relaunch', static function (): void {
	$req = fge_cc_guard( 'fge_cc_offer_relaunch' );
	$err = fge_offer_relaunch( $req );
	if ( '' !== $err ) {
		set_transient( 'fge_cc_err_' . $req, $err, 60 );
		fge_cc_redirect( $req, 'relaunch_failed' );
	}
	fge_cc_redirect( $req, 'relaunched' );
} );

// ── Mails rund um den Eventtag ───────────────────────────────────────────────

add_action( 'admin_post_fge_cc_day_plan', static function (): void {
	$req = fge_cc_guard( 'fge_cc_day_plan' );
	fge_cc_redirect( $req, fge_send_day_plan( $req ) ? 'day_plan' : 'mail_failed' );
} );

add_action( 'admin_post_fge_cc_day_info', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_day_info' );
	$date = function_exists( 'fge_day_event_date' ) ? fge_day_event_date( $req ) : null;
	$ok   = fge_send_day_info( $req, null !== $date && $date === wp_date( 'Y-m-d' ) );
	fge_cc_redirect( $req, $ok ? 'day_info' : 'mail_failed' );
} );

// ── Eventtag-Felder direkt im Cockpit ────────────────────────────────────────

add_action( 'admin_post_fge_cc_day_save', static function (): void {
	$req = fge_cc_guard( 'fge_cc_day_save' );
	foreach ( fge_day_fields() as $k => [ , $type ] ) {
		$raw = wp_unslash( $_POST[ 'fge_' . $k ] ?? '' );
		$val = 'textarea' === $type ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		if ( '' !== trim( $val ) ) {
			update_post_meta( $req, '_fge_' . $k, trim( $val ) );
		} else {
			delete_post_meta( $req, '_fge_' . $k );
		}
	}
	// Gleiche Automatik wie beim Speichern im WordPress-Backend: sobald Startzeit
	// und Treffpunkt erstmals stehen, geht die Ablauf-Info einmalig raus.
	if ( '' === (string) get_post_meta( $req, '_fge_day_plan_sent', true )
		&& fge_day_plan_ready( $req )
		&& ! ( function_exists( 'fge_is_demo_request' ) && fge_is_demo_request( $req ) ) ) {
		fge_send_day_plan( $req );
		fge_cc_redirect( $req, 'day_plan' );
	}
	fge_activity_add( $req, 'system', 'Eventtag-Felder aktualisiert' );
	fge_cc_redirect( $req, 'saved' );
} );

// ── Status ───────────────────────────────────────────────────────────────────

/** Statuswechsel, die im Control Center angeboten werden. */
function fge_cc_status_actions(): array {
	return [
		'event_durchgefuehrt' => 'Event durchgeführt',
		'abgeschlossen'       => 'Abgeschlossen',
		'verloren'            => 'Verloren',
	];
}

add_action( 'admin_post_fge_cc_status', static function (): void {
	$req    = fge_cc_guard( 'fge_cc_status' );
	$status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
	if ( ! isset( fge_cc_status_actions()[ $status ] ) ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	fge_request_set_status( $req, $status );
	fge_cc_redirect( $req, 'status' );
} );

// ── Notiz und Telefonnotiz ───────────────────────────────────────────────────

add_action( 'admin_post_fge_cc_note', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_note' );
	$text = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
	$type = 'call' === sanitize_key( wp_unslash( $_POST['note_type'] ?? '' ) ) ? 'call' : 'note';
	if ( '' === trim( $text ) ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	fge_activity_add( $req, $type, $text );
	fge_cc_redirect( $req, 'note' );
} );

// ── Eigene Aufgaben ──────────────────────────────────────────────────────────

add_action( 'admin_post_fge_cc_task_add', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_task_add' );
	$text = sanitize_text_field( wp_unslash( $_POST['task'] ?? '' ) );
	if ( '' === trim( $text ) ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	fge_cc_own_task_add( $req, $text, isset( $_POST['urgent'] ) );
	fge_cc_redirect( $req, 'task_added' );
} );

add_action( 'admin_post_fge_cc_task_done', static function (): void {
	$req = fge_cc_guard( 'fge_cc_task_done' );
	$id  = sanitize_text_field( wp_unslash( $_POST['task_id'] ?? '' ) );
	if ( '' === $id ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	fge_cc_own_task_remove( $req, $id );
	fge_cc_redirect( $req, 'task_done' );
} );

// ── Schlummern ───────────────────────────────────────────────────────────────

add_action( 'admin_post_fge_cc_snooze', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_snooze' );
	$days = absint( $_POST['days'] ?? 0 );
	if ( $days <= 0 ) {
		delete_post_meta( $req, '_fge_cc_snooze' );
		fge_cc_redirect( $req, 'woken' );
	}
	$until = time() + $days * DAY_IN_SECONDS;
	update_post_meta( $req, '_fge_cc_snooze', $until );
	fge_activity_add( $req, 'system', sprintf( 'Schlummert bis %s', wp_date( 'd.m.Y', $until ) ) );
	fge_cc_redirect( $req, 'snoozed' );
} );

// ── Formularbausteine ────────────────────────────────────────────────────────

/**
 * Ein Aktionsknopf als eigenständiges Formular.
 *
 * Bewusst ein echtes <form> je Knopf statt JavaScript: das Control Center soll
 * auch dann funktionieren, wenn unterwegs etwas nicht lädt.
 */
function fge_cc_button( string $action, int $req, string $label, array $opts = [] ): void {
	$class   = (string) ( $opts['class'] ?? 'cc-btn' );
	$confirm = (string) ( $opts['confirm'] ?? '' );
	$fields  = (array) ( $opts['fields'] ?? [] );

	echo '<form class="cc-actform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( $action . '_' . $req );
	foreach ( $fields as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $v ) . '">';
	}
	echo '<button type="submit" class="' . esc_attr( $class ) . '"'
		. ( '' !== $confirm ? ' onclick="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : '' )
		. '>' . esc_html( $label ) . '</button>';
	echo '</form>';
}

/**
 * Folgen-Vorschau: wer bekommt gleich welche Mail.
 *
 * Zeigt die echten Namen und Adressen dieser Anfrage, nicht Platzhalter, und
 * warnt vorher in Rot, wenn ein Empfänger fehlt. Genau der Weidenhof-Fall,
 * bei dem eine Mail still nicht rausging.
 */
function fge_cc_action_preview( string $action, int $req ): void {
	$mails = function_exists( 'fge_mail_action_preview' ) ? fge_mail_action_preview( $action, $req ) : [];
	if ( ! $mails ) {
		return;
	}
	echo '<div class="cc-preview"><p class="cc-preview-head">Das löst aus:</p>';
	foreach ( $mails as $m ) {
		echo '<div class="cc-preview-mail' . ( $m['has_gap'] ? ' is-gap' : '' ) . '">';
		echo '<p class="cc-preview-title">' . esc_html( $m['label'] ) . '</p>';
		foreach ( $m['recipients'] as $r ) {
			echo '<p class="cc-preview-to">';
			if ( ! empty( $r['missing'] ) ) {
				echo '<span class="cc-preview-warn">geht nicht raus</span> ';
				echo esc_html( $r['name'] );
				if ( '' !== (string) $r['note'] ) {
					echo ' <span class="cc-muted">' . esc_html( (string) $r['note'] ) . '</span>';
				}
			} else {
				echo esc_html( $r['name'] ) . ' <span class="cc-muted">' . esc_html( (string) $r['email'] ) . '</span>';
				if ( '' !== (string) $r['note'] ) {
					echo ' <span class="cc-muted">· ' . esc_html( (string) $r['note'] ) . '</span>';
				}
			}
			echo '</p>';
		}
		if ( ! empty( $m['content'] ) ) {
			echo '<p class="cc-preview-content">' . esc_html( implode( ' · ', $m['content'] ) ) . '</p>';
		}
		echo '</div>';
	}
	echo '</div>';
}

// ── Platz-Pipeline ───────────────────────────────────────────────────────────

/** Die Pipeline-Zeile aus dem POST, geprüft gegen die Anfrage. */
function fge_cc_venue_from_post( int $req ): array {
	$venue = fge_venue_get( absint( $_POST['venue_id'] ?? 0 ) );
	if ( ! $venue || (int) $venue['request_id'] !== $req ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	return $venue;
}

add_action( 'admin_post_fge_cc_venue_add', static function (): void {
	$req     = fge_cc_guard( 'fge_cc_venue_add' );
	$partner = absint( $_POST['partner_id'] ?? 0 );
	if ( $partner <= 0 || 'firmengolf_partner' !== get_post_type( $partner ) ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	fge_cc_redirect( $req, fge_venue_add( $req, $partner ) > 0 ? 'venue_added' : 'venue_dupe' );
} );

add_action( 'admin_post_fge_cc_venue_ask', static function (): void {
	$req   = fge_cc_guard( 'fge_cc_venue_ask' );
	$venue = fge_cc_venue_from_post( $req );
	$how   = sanitize_key( wp_unslash( $_POST['how'] ?? 'mail' ) );

	if ( 'telefon' === $how ) {
		// Telefonisch angefragt: kein Mailversand, aber festhalten, damit das
		// Nachfassen nicht im Kopf hängen bleibt.
		fge_venue_update( (int) $venue['id'], [
			'status'   => 'angefragt',
			'channel'  => 'telefon',
			'asked_at' => current_time( 'mysql' ),
		] );
		fge_activity_add( $req, 'venue', sprintf( '%s telefonisch angefragt', get_the_title( (int) $venue['partner_id'] ) ) );
		fge_cc_redirect( $req, 'venue_asked' );
	}

	fge_cc_redirect( $req, fge_venue_send_request( $req, (int) $venue['id'] ) ? 'venue_asked' : 'venue_nomail' );
} );

add_action( 'admin_post_fge_cc_venue_reply', static function (): void {
	$req   = fge_cc_guard( 'fge_cc_venue_reply' );
	$venue = fge_cc_venue_from_post( $req );
	$yes   = 'zusagt' === sanitize_key( wp_unslash( $_POST['answer'] ?? '' ) );

	// Ein gewählter Platz bleibt gewählt, wenn nur Termine oder Preise nachgetragen werden.
	$data = [
		'status'     => $yes ? ( 'gewaehlt' === (string) $venue['status'] ? 'gewaehlt' : 'zugesagt' ) : 'abgesagt',
		'replied_at' => current_time( 'mysql' ),
		'reason'     => sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ),
		'note'       => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
	];
	if ( $yes ) {
		$data['price']       = fge_xs_parse_num( sanitize_text_field( wp_unslash( $_POST['price'] ?? '' ) ) );
		$data['price_basis'] = 'pauschal' === sanitize_key( wp_unslash( $_POST['price_basis'] ?? '' ) ) ? 'pauschal' : 'person';
		$data['price_gross'] = '0' === (string) ( $_POST['price_gross'] ?? '1' ) ? 0 : 1;
	}
	fge_venue_update( (int) $venue['id'], $data );

	// Preise je Position mitschreiben, damit die Angebote vergleichbar werden.
	foreach ( (array) ( $_POST['item_price'] ?? [] ) as $key => $raw ) {
		$key       = sanitize_key( (string) $key );
		$available = isset( $_POST['item_available'][ $key ] ) ? 1 : 0;
		// Was der Platz nicht anbietet, hat auch keinen Preis; ein getippter Wert bliebe sonst stehen.
		fge_venue_item_set( (int) $venue['id'], $key, [
			'price'       => $available ? fge_xs_parse_num( sanitize_text_field( wp_unslash( (string) $raw ) ) ) : 0.0,
			'available'   => $available,
			'price_basis' => 'pauschal' === sanitize_key( wp_unslash( $_POST['item_basis'][ $key ] ?? '' ) ) ? 'pauschal' : 'person',
			'price_gross' => '0' === (string) ( $_POST['item_gross'][ $key ] ?? '1' ) ? 0 : 1,
		] );
	}

	// Verfügbarkeit je Wunschtermin: 1 geht, 0 geht nicht, leer bleibt offen.
	// Der Alternativvorschlag hängt als Notiz am ersten gesendeten Termin-Index.
	$avail = [];
	foreach ( (array) ( $_POST['date_avail'] ?? [] ) as $idx => $raw ) {
		$idx = (int) $idx;
		if ( $idx >= 1 && $idx <= 3 ) {
			$avail[ $idx ] = (string) $raw;
		}
	}
	$dates_note = '';
	if ( $avail && function_exists( 'fge_venue_date_set' ) ) {
		$alt   = sanitize_text_field( wp_unslash( $_POST['date_alt'] ?? '' ) );
		$first = (int) min( array_keys( $avail ) );
		foreach ( $avail as $idx => $raw ) {
			fge_venue_date_set( (int) $venue['id'], $idx, '' === $raw ? null : ( '1' === $raw ? 1 : 0 ), $idx === $first ? $alt : '' );
		}
		if ( $yes ) {
			$free       = function_exists( 'fge_venue_free_dates' ) ? fge_venue_free_dates( (int) $venue['id'] ) : [];
			$dates_note = $free ? ' (Termin ' . implode( ', ', $free ) . ' frei)' : ' (kein Wunschtermin frei)';
		}
	}

	fge_activity_add( $req, 'venue', sprintf(
		'%s hat %s%s%s',
		get_the_title( (int) $venue['partner_id'] ),
		$yes ? 'zugesagt' : 'abgesagt',
		$dates_note,
		'' !== $data['reason'] ? ': ' . $data['reason'] : ''
	) );
	// Erste Zusage erfasst: dem Platz bestätigen, was wir notiert haben, mit Reservierungsbitte.
	// Nur beim Wechsel von „angefragt" zu „zugesagt". Spätere Korrekturen lösen keine Mail
	// aus (Julius, 28.09.: GolfKultur ist fix und soll nur die Auftragsbestätigung bekommen),
	// dafür gibt es den Knopf „Bestätigung erneut senden".
	$first_reply = in_array( (string) $venue['status'], [ 'idee', 'angefragt' ], true ) && '' === (string) ( $venue['summary_at'] ?? '' ) && '' === (string) ( $venue['reservation_at'] ?? '' );
	if ( $yes && $first_reply && function_exists( 'fge_venue_send_summary' ) && '' !== fge_venue_partner_email( (int) $venue['partner_id'] )
		&& ! ( function_exists( 'fge_request_is_booked' ) && fge_request_is_booked( $req ) ) ) {
		fge_cc_redirect( $req, fge_venue_send_summary( $req, (int) $venue['id'], 'absprache' ) ? 'venue_reply_sent' : 'venue_reply' );
	}
	fge_cc_redirect( $req, 'venue_reply' );
} );

/**
 * Kalkulation je Platz: Aufschlag, Verkaufspreis des Grundpreises, je Position
 * Verkaufspreis und Organisator. Leere Felder übernehmen den Vorschlag aus
 * Einkauf plus Aufschlag.
 */
add_action( 'admin_post_fge_cc_venue_calc', static function (): void {
	$req   = fge_cc_guard( 'fge_cc_venue_calc' );
	$venue = fge_cc_venue_from_post( $req );
	$vid   = (int) $venue['id'];

	$markup_raw = trim( sanitize_text_field( wp_unslash( $_POST['markup'] ?? '' ) ) );
	$markup     = '' !== $markup_raw ? fge_xs_parse_num( $markup_raw ) : (float) ( $venue['markup_percent'] ?? 20 );
	$markup     = min( 500.0, $markup );

	$base_raw = trim( sanitize_text_field( wp_unslash( $_POST['sale_base'] ?? '' ) ) );
	$sale     = '' !== $base_raw
		? fge_xs_parse_num( $base_raw )
		: fge_venue_sale_from_cost( (float) $venue['price'], (bool) (int) $venue['price_gross'], $markup );

	fge_venue_update( $vid, [
		'markup_percent' => $markup,
		'sale_price'     => $sale,
	] );

	// Über die gespeicherten Positionen laufen, nicht über den POST: so sind die
	// Keys garantiert echt, und Positionen ohne Eingabe bekommen den Vorschlag.
	$sale_items = (array) ( $_POST['sale_item'] ?? [] );
	$organizers = (array) ( $_POST['organizer'] ?? [] );
	foreach ( fge_venue_items_get( $vid ) as $it ) {
		$key = (string) $it['wish_key'];
		if ( 'green_fee' === $key || ! (int) $it['available'] || (float) $it['price'] <= 0 ) {
			continue;
		}
		$raw = isset( $sale_items[ $key ] ) ? trim( sanitize_text_field( wp_unslash( (string) $sale_items[ $key ] ) ) ) : '';
		$org = sanitize_key( wp_unslash( (string) ( $organizers[ $key ] ?? 'platz' ) ) );
		fge_venue_item_set( $vid, $key, [
			'sale_price' => '' !== $raw
				? fge_xs_parse_num( $raw )
				: fge_venue_sale_from_cost( (float) $it['price'], (bool) (int) $it['price_gross'], $markup ),
			'organizer'  => 'extern' === $org ? 'extern' : 'platz',
		] );
	}

	fge_activity_add( $req, 'venue', sprintf( 'Kalkulation für %s gespeichert', get_the_title( (int) $venue['partner_id'] ) ) );
	fge_cc_redirect( $req, 'calc_saved' );
} );

add_action( 'admin_post_fge_cc_venue_choose', static function (): void {
	$req   = fge_cc_guard( 'fge_cc_venue_choose' );
	$venue = fge_cc_venue_from_post( $req );
	fge_cc_redirect( $req, fge_venue_choose( $req, (int) $venue['id'] ) ? 'venue_chosen' : 'nothing' );
} );

add_action( 'admin_post_fge_cc_venue_decline', static function (): void {
	$req   = fge_cc_guard( 'fge_cc_venue_decline' );
	$venue = fge_cc_venue_from_post( $req );
	$reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
	fge_cc_redirect( $req, fge_venue_send_decline( $req, (int) $venue['id'], $reason ) ? 'venue_declined' : 'venue_nomail' );
} );

add_action( 'admin_post_fge_cc_venue_summary', static function (): void {
	$req   = fge_cc_guard( 'fge_cc_venue_summary' );
	$venue = fge_cc_venue_from_post( $req );
	fge_cc_redirect( $req, fge_venue_send_summary( $req, (int) $venue['id'], 'absprache' ) ? 'venue_summary' : 'venue_nomail' );
} );

add_action( 'admin_post_fge_cc_venue_release', static function (): void {
	$req   = fge_cc_guard( 'fge_cc_venue_release' );
	$venue = fge_cc_venue_from_post( $req );
	fge_cc_redirect( $req, fge_venue_send_release( $req, (int) $venue['id'] ) ? 'venue_declined' : 'venue_nomail' );
} );

add_action( 'admin_post_fge_cc_venue_decline_all', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_venue_decline_all' );
	$sent = 0;
	foreach ( fge_venues_to_decline( $req ) as $v ) {
		if ( fge_venue_send_decline( $req, (int) $v['id'], '' ) ) {
			$sent++;
		}
	}
	fge_cc_redirect( $req, $sent > 0 ? 'venue_declined' : 'venue_nomail' );
} );

add_action( 'admin_post_fge_cc_venue_remove', static function (): void {
	$req   = fge_cc_guard( 'fge_cc_venue_remove' );
	$venue = fge_cc_venue_from_post( $req );
	fge_venue_delete( (int) $venue['id'] );
	fge_cc_redirect( $req, 'venue_removed' );
} );

// ── Standort, Stammdaten-Plätze, Kontakt (28.09.2026) ────────────────────────

add_action( 'admin_post_fge_cc_geo_anchor', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_geo_anchor' );
	$text = sanitize_text_field( wp_unslash( $_POST['geo_anchor'] ?? '' ) );
	if ( '' === $text ) {
		delete_post_meta( $req, '_fge_geo_anchor' );
		fge_activity_add( $req, 'note', 'Kundenstandort zurückgesetzt' );
	} else {
		update_post_meta( $req, '_fge_geo_anchor', $text );
		fge_activity_add( $req, 'note', sprintf( 'Kundenstandort auf %s gesetzt', $text ) );
	}
	fge_cc_redirect( $req, 'geo_saved' );
} );

/**
 * Platz aus dem DGV-Verzeichnis oder der Simulatoren-Liste aufnehmen.
 * Legt bei Bedarf den Stammdaten-Partner an (stammdaten-import.php); fehlt
 * die Datei, passiert nichts und die Seite bleibt heil.
 */
add_action( 'admin_post_fge_cc_venue_add_place', static function (): void {
	global $wpdb;
	$req = fge_cc_guard( 'fge_cc_venue_add_place' );
	$vid = absint( $_POST['vid'] ?? 0 );
	$sim = sanitize_text_field( wp_unslash( $_POST['sim'] ?? '' ) );
	$pid = 0;

	if ( $vid > 0 ) {
		if ( ! function_exists( 'fge_stammdaten_ensure_from_verzeichnis_row' ) || ! function_exists( 'fge_verzeichnis_table' ) ) {
			fge_cc_redirect( $req, 'nothing' );
		}
		$t = fge_verzeichnis_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $vid ), ARRAY_A );
		if ( $row ) {
			$pid = (int) fge_stammdaten_ensure_from_verzeichnis_row( $row );
		}
	} elseif ( '' !== $sim ) {
		if ( ! function_exists( 'fge_stammdaten_ensure_from_simulator' ) || ! function_exists( 'fge_stammdaten_sim_key' ) || ! function_exists( 'fge_simulatoren' ) ) {
			fge_cc_redirect( $req, 'nothing' );
		}
		foreach ( fge_simulatoren() as $s ) {
			if ( fge_stammdaten_sim_key( (string) $s['name'], (string) ( $s['plz'] ?? '' ), (string) ( $s['ort'] ?? '' ) ) === $sim ) {
				$pid = (int) fge_stammdaten_ensure_from_simulator( $s );
				break;
			}
		}
	}

	if ( $pid <= 0 || 'firmengolf_partner' !== get_post_type( $pid ) ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	fge_cc_redirect( $req, fge_venue_add( $req, $pid ) > 0 ? 'venue_added' : 'venue_exists' );
} );

/**
 * Hauptkontakt eines Platzes ergänzen, aus dem Cockpit oder dem Verzeichnis.
 * Eigene Prüfung, weil der Nonce am Platz hängt, nicht an der Anfrage.
 */
add_action( 'admin_post_fge_cc_partner_contact', static function (): void {
	$pid = absint( $_POST['partner_id'] ?? 0 );
	$req = absint( $_POST['request_id'] ?? 0 );
	if ( $pid <= 0 || ! fge_cc_can() || 'firmengolf_partner' !== get_post_type( $pid ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_cc_partner_contact_' . $pid );

	$back = static function ( string $msg ) use ( $req ): void {
		if ( $req > 0 ) {
			fge_cc_redirect( $req, $msg );
		}
		wp_safe_redirect( fge_cc_url( 'verzeichnis', [ 'msg' => $msg ] ) );
		exit;
	};

	$name  = sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) );
	$phone = sanitize_text_field( wp_unslash( $_POST['contact_phone'] ?? '' ) );
	$email = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
	$raw   = trim( (string) wp_unslash( $_POST['contact_email'] ?? '' ) );

	update_post_meta( $pid, '_fge_main_contact_name', $name );
	update_post_meta( $pid, '_fge_main_contact_phone', $phone );
	if ( '' !== $raw ) {
		if ( ! is_email( $email ) ) {
			$back( 'contact_invalid' );
		}
		update_post_meta( $pid, '_fge_main_contact_email', $email );
	}
	if ( function_exists( 'fge_partner_ensure_owner_contact' ) ) {
		fge_partner_ensure_owner_contact( $pid );
	}
	if ( $req > 0 && function_exists( 'fge_activity_add' ) ) {
		fge_activity_add( $req, 'venue', sprintf( 'Kontakt bei %s ergänzt', get_the_title( $pid ) ) );
	}
	$back( 'contact_saved' );
} );
