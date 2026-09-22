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
