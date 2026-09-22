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
		'venue_chosen'   => [ 'ok', 'Platz gewählt und der Anfrage zugeordnet.' ],
		'venue_declined' => [ 'ok', 'Absage ist raus.' ],
		'venue_removed'  => [ 'ok', 'Platz aus der Liste entfernt.' ],
		'venue_dupe'     => [ 'err', 'Dieser Platz steht schon in der Liste.' ],
		'venue_nomail'   => [ 'err', 'Dieser Platz hat keine Kontaktmail. Bitte telefonisch und hier festhalten.' ],
		'relaunched'     => [ 'ok', 'Neues Angebot ist raus. Die alte Fassung liegt im Archiv.' ],
		'catalog_sent'   => [ 'ok', 'Vorschlag ist beim Platz. Er kann direkt aus der Mail antworten.' ],
		'catalog_failed' => [ 'err', 'Der Vorschlag konnte nicht verschickt werden.' ],
		'task_added'     => [ 'ok', 'Aufgabe notiert.' ],
		'task_done'      => [ 'ok', 'Aufgabe abgehakt.' ],
		'relaunch_failed' => [ 'err', 'Das Angebot konnte nicht neu aufgelegt werden.' ],
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
	if ( function_exists( 'fge_offer_on_date_confirmed' ) ) {
		fge_mail_log_context( $req, 'offer_customer' );
		fge_offer_on_date_confirmed( $req, $idx );
		fge_mail_log_context_clear();
	}
	if ( '1' !== (string) get_post_meta( $req, '_fge_offer_sent', true ) ) {
		fge_cc_redirect( $req, 'no_event' );
	}
	fge_activity_add( $req, 'offer', 'Angebot aus dem Control Center gesendet' );
	fge_cc_redirect( $req, 'offer_sent' );
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

	$data = [
		'status'     => $yes ? 'zugesagt' : 'abgesagt',
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
		$key = sanitize_key( (string) $key );
		fge_venue_item_set( (int) $venue['id'], $key, [
			'price'       => fge_xs_parse_num( sanitize_text_field( wp_unslash( (string) $raw ) ) ),
			'available'   => isset( $_POST['item_available'][ $key ] ) ? 1 : 0,
			'price_basis' => 'pauschal' === sanitize_key( wp_unslash( $_POST['item_basis'][ $key ] ?? '' ) ) ? 'pauschal' : 'person',
			'price_gross' => '0' === (string) ( $_POST['item_gross'][ $key ] ?? '1' ) ? 0 : 1,
		] );
	}

	fge_activity_add( $req, 'venue', sprintf(
		'%s hat %s%s',
		get_the_title( (int) $venue['partner_id'] ),
		$yes ? 'zugesagt' : 'abgesagt',
		'' !== $data['reason'] ? ': ' . $data['reason'] : ''
	) );
	fge_cc_redirect( $req, 'venue_reply' );
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

add_action( 'admin_post_fge_cc_venue_decline_all', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_venue_decline_all' );
	$sent = 0;
	foreach ( fge_venues_to_decline( $req ) as $v ) {
		if ( fge_venue_send_decline( $req, (int) $v['id'], 'Der Termin passte diesmal bei einem anderen Platz besser.' ) ) {
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
