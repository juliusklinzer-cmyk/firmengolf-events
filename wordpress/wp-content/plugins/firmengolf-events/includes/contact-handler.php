<?php
/**
 * Kontaktseite — Formular- & Rückruf-Verarbeitung.
 *
 * Jede Einsendung wird zu einem `firmengolf_request`-Post (wie jede andere
 * Anfrage) und löst die interne Admin-Mail mit Link zur Anfrage aus.
 * Muster (Nonce, Honeypot, Sanitizing, PRG-Redirect) analog form-handler.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** URL der Kontaktseite (für Redirects). */
function fge_kontakt_url(): string {
	$p = get_page_by_path( 'kontakt' );
	return $p ? (string) get_permalink( $p->ID ) : home_url( '/kontakt/' );
}

/** Setzt den Post-Titel einer Anfrage, ohne den Auto-Titel-Hook anzustoßen. */
function fge_kontakt_set_title( int $request_id, string $title ): void {
	remove_action( 'save_post', 'fge_auto_title_request', 20 );
	wp_update_post( [
		'ID'         => $request_id,
		'post_title' => $title,
		'post_name'  => sanitize_title( $title . '-' . $request_id ),
	] );
	add_action( 'save_post', 'fge_auto_title_request', 20 );
}

// ── Kontaktformular ───────────────────────────────────────────────────────────

function fge_handle_kontakt_submit() {
	if ( ! isset( $_POST['fge_action'] ) || $_POST['fge_action'] !== 'kontakt_submit' ) {
		return;
	}

	$base = fge_kontakt_url();

	if (
		! isset( $_POST['fge_kontakt_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_kontakt_nonce'] ) ), 'fge_kontakt' )
	) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}

	// Honeypot — Bots füllen das versteckte Feld; still als Erfolg abtun.
	if ( ! empty( $_POST['fge_hp_url'] ) ) {
		wp_redirect( esc_url_raw( $base . '?kontakt=danke#kontaktformular' ), 303 );
		exit;
	}

	$topics = [
		'event'   => 'Event anfragen',
		'individ' => 'Individuelles Event',
		'partner' => 'Partnerplatz werden',
		'benefit' => 'Benefit-Programm',
		'press'   => 'Presse',
		'other'   => 'Etwas anderes',
	];
	$prefs = [ 'Egal', 'E-Mail', 'Telefon', 'WhatsApp' ];

	$topic_id = sanitize_text_field( wp_unslash( $_POST['fge_kontakt_topic'] ?? 'event' ) );
	$topic_id = isset( $topics[ $topic_id ] ) ? $topic_id : 'event';
	$pref     = sanitize_text_field( wp_unslash( $_POST['fge_kontakt_pref'] ?? 'Egal' ) );
	$pref     = in_array( $pref, $prefs, true ) ? $pref : 'Egal';

	$name    = sanitize_text_field( wp_unslash( $_POST['fge_kontakt_name'] ?? '' ) );
	$company = sanitize_text_field( wp_unslash( $_POST['fge_kontakt_company'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['fge_kontakt_email'] ?? '' ) );
	$phone   = sanitize_text_field( wp_unslash( $_POST['fge_kontakt_phone'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['fge_kontakt_message'] ?? '' ) );
	$consent = isset( $_POST['fge_kontakt_consent'] );

	$errors = [];
	if ( $name === '' ) {
		$errors['name'] = 'Bitte gib deinen Namen an.';
	}
	if ( $email === '' || ! is_email( $email ) ) {
		$errors['email'] = 'Bitte gib eine gültige E-Mail-Adresse an.';
	}
	if ( $message === '' ) {
		$errors['message'] = 'Bitte schreib uns kurz, worum es geht.';
	}
	if ( ! $consent ) {
		$errors['consent'] = 'Bitte stimme der Datenschutzerklärung zu.';
	}

	if ( ! empty( $errors ) ) {
		$token = wp_generate_uuid4();
		set_transient( 'fge_kontakt_err_' . $token, [
			'errors' => $errors,
			'data'   => compact( 'topic_id', 'pref', 'name', 'company', 'email', 'phone', 'message' ),
		], 300 );
		wp_redirect( esc_url_raw( $base . '?kontakt_err=' . rawurlencode( $token ) . '#kontaktformular' ), 303 );
		exit;
	}

	$request_id = wp_insert_post( [
		'post_type'   => 'firmengolf_request',
		'post_status' => 'publish',
		'post_title'  => 'Auto Draft',
	] );

	if ( is_wp_error( $request_id ) || $request_id === 0 ) {
		wp_redirect( esc_url_raw( $base . '?kontakt=fehler#kontaktformular' ), 303 );
		exit;
	}

	// Name in Vor-/Nachname aufteilen (für Admin-Anzeige/Mail).
	$parts      = preg_split( '/\s+/', trim( $name ), 2 );
	$first_name = $parts[0] ?? '';
	$last_name  = $parts[1] ?? '';

	$pref_method_map = [ 'Egal' => 'any', 'E-Mail' => 'email', 'Telefon' => 'phone', 'WhatsApp' => 'phone' ];

	$full_message = sprintf(
		"[Kontaktformular] Thema: %s\nGewünschter Kontaktweg: %s\n\n%s",
		$topics[ $topic_id ],
		$pref,
		$message
	);

	update_post_meta( $request_id, '_fge_request_type',            'general_event_request' );
	update_post_meta( $request_id, '_fge_request_status',          'neu' );
	update_post_meta( $request_id, '_fge_source',                  'contact_page' );
	update_post_meta( $request_id, '_fge_contact_topic',           $topics[ $topic_id ] );
	add_post_meta( $request_id, '_fge_request_date',      current_datetime()->format( 'Y-m-d H:i:s' ), true );
	add_post_meta( $request_id, '_fge_consent_timestamp', current_datetime()->format( 'Y-m-d H:i:s' ), true );

	update_post_meta( $request_id, '_fge_company_name',            $company );
	update_post_meta( $request_id, '_fge_contact_first_name',      $first_name );
	update_post_meta( $request_id, '_fge_contact_last_name',       $last_name );
	update_post_meta( $request_id, '_fge_contact_email',           $email );
	update_post_meta( $request_id, '_fge_contact_phone',           $phone );
	update_post_meta( $request_id, '_fge_preferred_contact_method', $pref_method_map[ $pref ] );
	update_post_meta( $request_id, '_fge_message',                 $full_message );

	$date  = current_datetime()->format( 'Y-m-d' );
	$label = $company !== '' ? $company : $name;
	fge_kontakt_set_title( $request_id, 'Kontakt: ' . $label . ' · ' . $date );

	// Kundenbestätigung + Admin-Mail (kein Partner-Mail, da kein specific_event).
	do_action( 'fge_request_created', $request_id );

	$ref = function_exists( 'fge_generate_request_ref' ) ? fge_generate_request_ref() : sprintf( 'FG-%06d', $request_id );
	update_post_meta( $request_id, '_fge_ref', $ref );
	wp_redirect( esc_url_raw( $base . '?kontakt=danke&fgref=' . rawurlencode( $ref ) . '#kontaktformular' ), 303 );
	exit;
}
add_action( 'init', 'fge_handle_kontakt_submit', 10 );

// ── Rückruf-Widget ──────────────────────────────────────────────────────────────

function fge_handle_rueckruf_submit() {
	if ( ! isset( $_POST['fge_action'] ) || $_POST['fge_action'] !== 'rueckruf_submit' ) {
		return;
	}

	$base = fge_kontakt_url();

	if (
		! isset( $_POST['fge_rueckruf_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_rueckruf_nonce'] ) ), 'fge_rueckruf' )
	) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}

	if ( ! empty( $_POST['fge_hp_url'] ) ) {
		wp_redirect( esc_url_raw( $base . '?rueckruf=danke#callback' ), 303 );
		exit;
	}

	$whens = [ 'Egal', 'Vormittags', 'Nachmittags', 'Früher Abend' ];
	$phone = sanitize_text_field( wp_unslash( $_POST['fge_rueckruf_phone'] ?? '' ) );
	$when  = sanitize_text_field( wp_unslash( $_POST['fge_rueckruf_when'] ?? 'Egal' ) );
	$when  = in_array( $when, $whens, true ) ? $when : 'Egal';

	if ( $phone === '' ) {
		wp_redirect( esc_url_raw( $base . '?rueckruf_err=1#callback' ), 303 );
		exit;
	}

	$request_id = wp_insert_post( [
		'post_type'   => 'firmengolf_request',
		'post_status' => 'publish',
		'post_title'  => 'Auto Draft',
	] );

	if ( is_wp_error( $request_id ) || $request_id === 0 ) {
		wp_redirect( esc_url_raw( $base . '?rueckruf=fehler#callback' ), 303 );
		exit;
	}

	update_post_meta( $request_id, '_fge_request_type',   'general_event_request' );
	update_post_meta( $request_id, '_fge_request_status', 'neu' );
	update_post_meta( $request_id, '_fge_source',         'callback_widget' );
	update_post_meta( $request_id, '_fge_contact_phone',  $phone );
	update_post_meta( $request_id, '_fge_preferred_contact_method', 'phone' );
	update_post_meta( $request_id, '_fge_message', sprintf( "[Rückruf gewünscht] Bevorzugte Zeit: %s\nTelefon: %s", $when, $phone ) );
	add_post_meta( $request_id, '_fge_request_date', current_datetime()->format( 'Y-m-d H:i:s' ), true );

	$date = current_datetime()->format( 'Y-m-d' );
	fge_kontakt_set_title( $request_id, 'Rückruf: ' . $phone . ' · ' . $date );

	// Kein Kunden-Mail (keine E-Mail vorhanden) — Admin direkt benachrichtigen.
	if ( function_exists( 'fge_send_internal_request_email' ) ) {
		fge_send_internal_request_email( $request_id, fge_get_request_email_data( $request_id ) );
	}

	wp_redirect( esc_url_raw( $base . '?rueckruf=danke#callback' ), 303 );
	exit;
}
add_action( 'init', 'fge_handle_rueckruf_submit', 10 );
