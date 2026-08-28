<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// FORM HANDLERS
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'init', 'fge_portal_handle_new_event', 10 );
add_action( 'init', 'fge_portal_handle_edit_event', 10 );

/** Load the async media widgets on the portal: gallery on "Platz" edit, picker on the event form. */
add_action( 'wp_enqueue_scripts', 'fge_portal_enqueue_media' );
function fge_portal_enqueue_media(): void {
	if ( ! is_page( 'partnerportal' ) ) {
		return;
	}
	$tab    = $_GET['tab'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification
	$action = $_GET['portal_action'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification
	$pid    = fge_portal_get_partner_id();
	if ( $pid <= 0 ) {
		return;
	}

	if ( 'platz' === $tab && isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		fge_media_enqueue( $pid );
	} elseif ( 'angebote' === $tab && in_array( $action, [ 'new', 'edit' ], true ) ) {
		fge_event_picker_enqueue( $pid );
	}
}
add_action( 'init', 'fge_portal_handle_profile_update', 10 );
add_action( 'init', 'fge_portal_handle_indoor_update', 10 );
add_action( 'init', 'fge_portal_handle_lifecycle', 10 );
add_action( 'init', 'fge_portal_handle_contacts', 10 );
add_action( 'init', 'fge_portal_handle_request', 10 );
add_action( 'init', 'fge_portal_handle_ics', 9 );

/** .ics-Download eines bestätigten Termins (GET ?fge_ics=<req> + Nonce + Ownership). */
function fge_portal_handle_ics(): void {
	if ( ! isset( $_GET['fge_ics'] ) ) {
		return;
	}
	$req = absint( $_GET['fge_ics'] );
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fge_ics_' . $req ) ) {
		return;
	}
	$partner_id = fge_portal_get_partner_id();
	if ( $partner_id <= 0 || (int) get_post_meta( $req, '_fge_assigned_partner_id', true ) !== $partner_id ) {
		return;
	}
	$ics = function_exists( 'fge_request_ics' ) ? fge_request_ics( $req ) : null;
	if ( ! $ics ) {
		wp_die( 'Für diesen Termin ist kein Kalender-Export möglich (Datum nicht eindeutig).', '', [ 'response' => 404 ] );
	}
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="firmengolf-' . sanitize_file_name( fge_request_number( $req ) ) . '.ics"' );
	echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}

/** Willkommens-Panel ausblenden (einmaliger Erste-Schritte-Kasten). */
function fge_portal_handle_dismiss_welcome(): void {
	if ( 'dismiss_welcome' !== sanitize_key( $_GET['portal_action'] ?? '' ) || ! is_user_logged_in() ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'fge_dismiss_welcome' ) ) {
		return;
	}
	delete_user_meta( get_current_user_id(), 'fge_welcome_pending' );
	wp_safe_redirect( fge_portal_page_url() );
	exit;
}
add_action( 'template_redirect', 'fge_portal_handle_dismiss_welcome', 4 );

/** PRG handler for the Anfragen tab — confirm a final wish date. Nonce + ownership. */
function fge_portal_handle_request(): void {
	$action = sanitize_key( $_POST['portal_action'] ?? '' );
	if ( 'confirm_date' !== $action ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_portal_nonce'] ?? '' ) ), 'fge_portal_request' ) ) {
		// Abgelaufene Sitzung: nicht still schlucken, sondern Hinweis zeigen (Audit C7).
		wp_redirect( esc_url_raw( fge_portal_page_url() . '?tab=anfragen&portal_err_notice=expired' ), 303 );
		exit;
	}
	$partner_id = fge_portal_get_partner_id();
	if ( $partner_id <= 0 ) {
		return;
	}
	$req = absint( $_POST['req_id'] ?? 0 );
	if ( $req <= 0 || (int) get_post_meta( $req, '_fge_assigned_partner_id', true ) !== $partner_id ) {
		return;
	}

	// Idempotenz: bereits final/Angebot raus → Termin darf nicht mehr umgestoßen werden (Audit A4).
	$already_final = ( function_exists( 'fge_rr_final_index' ) && fge_rr_final_index( $req ) > 0 )
		|| '1' === (string) get_post_meta( $req, '_fge_offer_sent', true )
		|| '1' === (string) get_post_meta( $req, '_fge_offer_hold', true );
	if ( $already_final ) {
		wp_redirect( esc_url_raw( fge_portal_page_url() . '?tab=anfragen&req=' . $req . '&portal_err_notice=already_final' ), 303 );
		exit;
	}

	// Index muss auf einen echten Wunschtermin zeigen.
	$idx   = absint( $_POST['date_index'] ?? 0 );
	$label = $idx > 0 ? trim( (string) get_post_meta( $req, '_fge_preferred_date_' . $idx, true ) ) : '';
	if ( $idx > 0 && '' !== $label && function_exists( 'fge_rr_set_final' ) ) {
		fge_rr_set_final( $req, $idx );
		fge_request_set_status( $req, 'bestaetigt' );
		do_action( 'fge_request_date_confirmed', $req, $idx );
		wp_redirect( esc_url_raw( fge_portal_page_url() . '?tab=anfragen&req=' . $req . '&portal_success=date_confirmed' ), 303 );
		exit;
	}
	wp_redirect( esc_url_raw( fge_portal_page_url() . '?tab=anfragen&req=' . $req . '&portal_err_notice=invalid_date' ), 303 );
	exit;
}

/** Derived pipeline status of a request → [ id, label ]. */
function fge_portal_request_status( int $req ): array {
	$manual = (string) get_post_meta( $req, '_fge_request_status', true );
	// Explizite Lebenszyklus-Status sind führend (eine Statusquelle).
	if ( in_array( $manual, [ 'abgeschlossen', 'angebot_angenommen', 'event_durchgefuehrt', 'rechnung_in_lexoffice_erstellt' ], true ) ) {
		return [ 'abgeschlossen', 'angebot_angenommen' === $manual ? 'Gebucht' : 'Abgeschlossen' ];
	}
	if ( in_array( $manual, [ 'angebot_abgelehnt', 'verloren', 'nicht_verfuegbar' ], true ) ) {
		return [ 'abgelehnt', 'Abgelehnt' ];
	}
	if ( 'angebot_versendet' === $manual ) {
		return [ 'bestaetigt', 'Angebot versendet' ];
	}
	if ( function_exists( 'fge_rr_final_index' ) && fge_rr_final_index( $req ) > 0 ) {
		return [ 'bestaetigt', 'Bestätigt' ];
	}
	if ( ! function_exists( 'fge_rr_matrix' ) ) {
		return [ 'neu', 'Neu' ];
	}
	$m = fge_rr_matrix( $req );
	if ( 'nicht_verfuegbar' === $m['overall'] ) {
		return [ 'abgelehnt', 'Abgelehnt' ];
	}
	$has_resp = false;
	foreach ( $m['dates'] as $d ) {
		if ( $d['confirmed'] > 0 || $d['declined'] > 0 ) {
			$has_resp = true;
			break;
		}
	}
	return ( $has_resp || $m['all_responded'] ) ? [ 'bearbeitung', 'In Abstimmung' ] : [ 'neu', 'Neu' ];
}

/**
 * Partner-Sicht auf eine Anfrage: wer ist am Zug und was passiert als Nächstes.
 * Übersetzt die internen Pipeline-Status in eine einfache Sprache für Golfplatz-
 * Nutzer (keine Techies): 'who' = du | wir | fertig | zu, plus Schritt 0–3 für
 * die Timeline (Eingegangen → Verfügbarkeit → Termin fix → Angebot & Buchung).
 */
function fge_portal_partner_phase( int $req ): array {
	[ $sid, $slabel ] = fge_portal_request_status( $req );
	$m = function_exists( 'fge_rr_matrix' ) ? fge_rr_matrix( $req ) : [ 'dates' => [], 'responders' => [], 'all_responded' => false, 'overall' => 'offen' ];
	$final = function_exists( 'fge_rr_final_index' ) ? fge_rr_final_index( $req ) : 0;
	$total = count( $m['responders'] );

	if ( 'abgeschlossen' === $sid ) {
		return [
			'who'   => 'fertig',
			'pill'  => 'Gebucht' === $slabel ? 'Gebucht' : 'Abgeschlossen',
			'title' => 'Alles erledigt.',
			'text'  => 'Gebucht' === $slabel
				? 'Das Event ist gebucht. Alle Details stimmen wir rechtzeitig vor dem Termin mit dir ab.'
				: 'Diese Anfrage ist abgeschlossen. Du musst nichts weiter tun.',
			'cta'   => '',
			'step'  => 3,
		];
	}
	if ( 'abgelehnt' === $sid ) {
		return [
			'who'   => 'zu',
			'pill'  => 'Abgesagt',
			'title' => 'Diese Anfrage kam nicht zustande.',
			'text'  => 'Kein passender Termin oder das Unternehmen hat abgesagt. Du musst nichts weiter tun.',
			'cta'   => '',
			'step'  => 1,
		];
	}
	if ( 'bestaetigt' === $sid || $final > 0 ) {
		return [
			'who'   => 'wir',
			'pill'  => 'Termin fix, Firmengolf übernimmt',
			'title' => 'Firmengolf ist am Zug.',
			'text'  => 'Der Termin steht. Wir erstellen jetzt das Angebot für das Unternehmen und kümmern uns um die Buchung. Du musst nichts tun, wir melden uns, sobald es fix ist.',
			'cta'   => '',
			'step'  => 3,
		];
	}

	// Verfügbarkeit wird noch geklärt.
	$avail = 0;
	foreach ( $m['dates'] as $d ) {
		$avail = max( $avail, (int) ( $d['confirmed'] ?? 0 ) );
	}
	if ( $total > 0 && ! $m['all_responded'] ) {
		$done = 0;
		foreach ( $m['responders'] as $c ) {
			if ( fge_rr_contact_answered_any( $req, (int) $c['id'] ) ) {
				$done++;
			}
		}
		return [
			'who'   => 'du',
			'pill'  => 'Verfügbarkeit klären',
			'title' => 'Dein Team ist am Zug.',
			'text'  => sprintf( '%d von %d Ansprechpartnern haben zu den Wunschterminen reagiert. Erinnere die übrigen, den Abstimmungs-Link kannst du unten bei jeder Person kopieren.', $done, $total ),
			'cta'   => 'Zu den Wunschterminen',
			'step'  => 1,
		];
	}
	if ( $total > 0 && $m['all_responded'] && $avail > 0 ) {
		return [
			'who'   => 'du',
			'pill'  => 'Termin bestätigen',
			'title' => 'Du bist am Zug: Bestätige den finalen Termin.',
			'text'  => 'Alle haben reagiert. Wähle unten den Termin aus, der bei euch passt, danach übernimmt Firmengolf.',
			'cta'   => 'Termin auswählen',
			'step'  => 2,
		];
	}
	return [
		'who'   => 'du',
		'pill'  => 'Neu, Verfügbarkeit prüfen',
		'title' => 'Du bist am Zug: Prüfe die Wunschtermine.',
		'text'  => $total > 0
			? 'Eine neue Anfrage ist da. Deine Ansprechpartner wurden zu den Wunschterminen befragt, unten siehst du den Stand.'
			: 'Eine neue Anfrage ist da. Schau dir die Wunschtermine an und bestätige, was bei euch möglich ist.',
		'cta'   => 'Zu den Wunschterminen',
		'step'  => 1,
	];
}

/**
 * PRG handler for the Ansprechpartner (contacts) tab — add / edit / remove
 * no-account contacts in fge_partner_contacts. Nonce + partner-ownership checked.
 */
function fge_portal_handle_contacts(): void {
	$action = sanitize_key( $_POST['portal_action'] ?? '' );
	if ( ! in_array( $action, [ 'contact_save', 'contact_delete' ], true ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_portal_nonce'] ?? '' ) ), 'fge_portal_contact' ) ) {
		return;
	}
	$partner_id = fge_portal_get_partner_id();
	if ( $partner_id <= 0 ) {
		return;
	}
	$base = fge_portal_page_url();

	if ( 'contact_delete' === $action ) {
		$cid = absint( $_POST['contact_id'] ?? 0 );
		$c   = $cid > 0 ? fge_contact_get( $cid ) : null;
		if ( $c && (int) $c['partner_id'] === $partner_id && (int) $c['user_id'] === 0 ) {
			fge_contact_delete( $cid );
		}
		wp_redirect( esc_url_raw( $base . '?tab=team&portal_success=contact_removed' ), 303 );
		exit;
	}

	// contact_save (add or edit).
	$cid  = absint( $_POST['contact_id'] ?? 0 );
	$data = [
		'name'       => sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ),
		'email'      => sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ),
		'role'       => sanitize_text_field( wp_unslash( $_POST['contact_role'] ?? '' ) ),
		'permission' => sanitize_text_field( wp_unslash( $_POST['contact_permission'] ?? '' ) ),
	];

	// Server-Validierung: sonst meldet der Redirect „gespeichert", obwohl nichts passierte (Audit C8).
	if ( '' === $data['name'] || ! is_email( $data['email'] ) ) {
		wp_redirect( esc_url_raw( $base . '?tab=team&portal_err_notice=contact_invalid' ), 303 );
		exit;
	}

	$saved_ok = false;
	if ( $cid > 0 ) {
		$c = fge_contact_get( $cid );
		if ( $c && (int) $c['partner_id'] === $partner_id && (int) $c['user_id'] === 0 ) {
			$saved_ok = false !== fge_contact_update( $cid, $data );
		}
	} else {
		$saved_ok = (int) fge_contact_add( $partner_id, $data ) > 0;
	}
	wp_redirect( esc_url_raw( $base . ( $saved_ok ? '?tab=team&portal_success=contact_saved' : '?tab=team&portal_err_notice=contact_invalid' ) ), 303 );
	exit;
}

/**
 * Lifecycle-Aktion des Platzes: eigenes Angebot pausieren / reaktivieren (GET + Nonce).
 * freigegeben → pausiert (Pausieren) · pausiert → freigegeben (Reaktivieren).
 */
function fge_portal_handle_lifecycle(): void {
	$action = sanitize_key( $_GET['portal_action'] ?? '' );
	if ( ! in_array( $action, [ 'pause', 'reactivate' ], true ) ) {
		return;
	}
	$event_id = absint( $_GET['event_id'] ?? 0 );
	if ( ! $event_id ) {
		return;
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fge_portal_lifecycle_' . $event_id ) ) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}
	$partner_id = fge_portal_get_partner_id();
	if ( ! $partner_id || (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true ) !== $partner_id ) {
		wp_die( 'Kein Zugriff auf dieses Angebot.', '', [ 'response' => 403 ] );
	}
	$current = (string) get_post_meta( $event_id, '_fge_event_status', true );
	$base    = fge_portal_page_url();
	if ( $action === 'pause' && $current === 'freigegeben' ) {
		update_post_meta( $event_id, '_fge_event_status', 'pausiert' );
		wp_redirect( esc_url_raw( $base . '?tab=angebote&portal_success=event_paused' ), 303 );
		exit;
	}
	if ( $action === 'reactivate' && $current === 'pausiert' ) {
		update_post_meta( $event_id, '_fge_event_status', 'freigegeben' );
		wp_redirect( esc_url_raw( $base . '?tab=angebote&portal_success=event_reactivated' ), 303 );
		exit;
	}
	wp_redirect( esc_url_raw( $base . '?tab=angebote' ), 303 );
	exit;
}

function fge_portal_handle_new_event(): void {
	if ( ( $_POST['fge_action'] ?? '' ) !== 'portal_new_event' ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		wp_die( 'Nicht autorisiert.', '', [ 'response' => 403 ] );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_portal_nonce'] ?? '' ) ), 'fge_portal_new_event' ) ) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}
	$partner_id = fge_portal_get_partner_id();
	if ( $partner_id <= 0 ) {
		wp_die( 'Kein gültiges Partnerprofil.', '', [ 'response' => 403 ] );
	}

	$base   = fge_portal_page_url();
	$errors = fge_portal_validate_event_fields();

	if ( ! empty( $errors ) ) {
		$token = wp_generate_uuid4();
		set_transient( 'fge_form_err_' . $token, [ 'errors' => $errors, 'data' => wp_unslash( $_POST ) ], 300 );
		wp_redirect( esc_url_raw( $base . '?tab=angebote&portal_action=new&portal_err=' . rawurlencode( $token ) ), 303 );
		exit;
	}

	// In der Musterumgebung entstehen Events als Entwürfe: im Demo-Portal sichtbar,
	// aber nie öffentlich und nie in der echten Freigabe-Queue.
	$is_demo = function_exists( 'fge_is_demo_partner' ) && fge_is_demo_partner( $partner_id );
	$post_id = wp_insert_post( [
		'post_type'    => 'firmengolf_event',
		'post_status'  => $is_demo ? 'draft' : 'publish',
		'post_title'   => sanitize_text_field( wp_unslash( $_POST['fge_post_title'] ?? '' ) ),
		'post_content' => wp_kses_post( wp_unslash( $_POST['fge_post_content'] ?? '' ) ),
		'post_author'  => get_current_user_id(),
	] );

	if ( is_wp_error( $post_id ) || $post_id === 0 ) {
		wp_redirect( esc_url_raw( $base . '?tab=angebote&portal_action=new&portal_err=save_failed' ), 303 );
		exit;
	}

	update_post_meta( $post_id, '_fge_event_status',        'zur_pruefung' );
	update_post_meta( $post_id, '_fge_provider_type',       'golfplatz_partner' );
	update_post_meta( $post_id, '_fge_assigned_partner_id', $partner_id );
	fge_portal_save_event_meta( $post_id );
	fge_event_save_images( $post_id, $partner_id, wp_unslash( $_POST ) );

	do_action( 'fge_event_submitted', $post_id, $partner_id, true );

	wp_redirect( esc_url_raw( $base . '?tab=angebote&portal_success=event_saved' ), 303 );
	exit;
}

function fge_portal_handle_edit_event(): void {
	if ( ( $_POST['fge_action'] ?? '' ) !== 'portal_edit_event' ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		wp_die( 'Nicht autorisiert.', '', [ 'response' => 403 ] );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_portal_nonce'] ?? '' ) ), 'fge_portal_edit_event' ) ) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}
	$partner_id = fge_portal_get_partner_id();
	if ( $partner_id <= 0 ) {
		wp_die( 'Kein gültiges Partnerprofil.', '', [ 'response' => 403 ] );
	}

	$event_id = absint( $_POST['fge_event_id'] ?? 0 );
	if ( $event_id <= 0 || get_post_type( $event_id ) !== 'firmengolf_event' ) {
		wp_die( 'Ungültige Event-ID.', '', [ 'response' => 400 ] );
	}
	if ( (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true ) !== $partner_id ) {
		wp_die( 'Zugriff verweigert.', '', [ 'response' => 403 ] );
	}

	$base   = fge_portal_page_url();
	$errors = fge_portal_validate_event_fields();

	if ( ! empty( $errors ) ) {
		$token = wp_generate_uuid4();
		set_transient( 'fge_form_err_' . $token, [ 'errors' => $errors, 'data' => wp_unslash( $_POST ) ], 300 );
		wp_redirect( esc_url_raw( $base . '?tab=angebote&portal_action=edit&event_id=' . $event_id . '&portal_err=' . rawurlencode( $token ) ), 303 );
		exit;
	}

	$upd = [
		'ID'         => $event_id,
		'post_title' => sanitize_text_field( wp_unslash( $_POST['fge_post_title'] ?? '' ) ),
	];
	// Lange Beschreibung gibt es im Formular nicht mehr; Bestand nicht überschreiben.
	if ( isset( $_POST['fge_post_content'] ) ) {
		$upd['post_content'] = wp_kses_post( wp_unslash( $_POST['fge_post_content'] ) );
	}
	wp_update_post( $upd );

	$current_status = (string) get_post_meta( $event_id, '_fge_event_status', true );
	if ( 'pausiert' === $current_status ) {
		// Merken: nach der Freigabe soll das Event pausiert bleiben, nicht ungewollt live gehen.
		update_post_meta( $event_id, '_fge_was_paused', 1 );
	}
	update_post_meta( $event_id, '_fge_event_status', $current_status === 'freigegeben' ? 'aenderung_in_pruefung' : 'zur_pruefung' );
	fge_portal_save_event_meta( $event_id );
	fge_event_save_images( $event_id, $partner_id, wp_unslash( $_POST ) );

	do_action( 'fge_event_submitted', $event_id, $partner_id, false );

	wp_redirect( esc_url_raw( $base . '?tab=angebote&portal_success=event_updated' ), 303 );
	exit;
}

function fge_portal_handle_profile_update(): void {
	if ( ( $_POST['fge_action'] ?? '' ) !== 'portal_profile_update' ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		wp_die( 'Nicht autorisiert.', '', [ 'response' => 403 ] );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_portal_nonce'] ?? '' ) ), 'fge_portal_profile_update' ) ) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}
	$partner_id = fge_portal_get_partner_id();
	if ( $partner_id <= 0 ) {
		wp_die( 'Kein gültiges Partnerprofil.', '', [ 'response' => 403 ] );
	}

	// Per-section save — only the submitted section's fields are written.
	$section = sanitize_key( $_POST['fge_platz_section'] ?? 'steckbrief' );
	$P       = wp_unslash( $_POST );

	switch ( $section ) {
		// Coach-Sektionen: Persistenz über die Onboarding-Slide-Logik (gleiche
		// Feldnamen, gleiche Sanitisierung) — EIN Datenmodell für beide Wege.
		case 'profil':
			// Der Portal-Schritt bündelt beide Wizard-Slides (Über dich + Geschichte).
			fge_onboarding_save_slide( $partner_id, 'coach-profile', $_POST );
			fge_onboarding_save_slide( $partner_id, 'coach-story', $_POST );
			break;

		case 'standorte':
			fge_onboarding_save_slide( $partner_id, 'coach-venue', $_POST );
			// Adresse der Anlage (setzt Karte, Umkreis und Stadt-Zuordnung).
			foreach ( [ 'street', 'postal_code', 'city' ] as $k ) {
				if ( isset( $P[ 'fge_' . $k ] ) ) {
					update_post_meta( $partner_id, '_fge_' . $k, sanitize_text_field( $P[ 'fge_' . $k ] ) );
				}
			}
			break;

		case 'steckbrief':
			update_post_meta( $partner_id, '_fge_public_golfclub_name',    sanitize_text_field( $P['fge_public_golfclub_name'] ?? '' ) );
			update_post_meta( $partner_id, '_fge_public_short_description', sanitize_textarea_field( $P['fge_public_short_description'] ?? '' ) );
			update_post_meta( $partner_id, '_fge_website_url',             esc_url_raw( $P['fge_website_url'] ?? '' ) );
			$gt = sanitize_text_field( $P['fge_golf_type'] ?? '' );
			update_post_meta( $partner_id, '_fge_golf_type', array_key_exists( $gt, fge_catalog_golf_types() ) ? $gt : '' );
			// Nur min/max aktualisieren — die Bereichs-Kapazitäten aus dem Onboarding erhalten.
			$cap_in       = is_array( $P['fge_cap'] ?? null ) ? $P['fge_cap'] : [];
			$cap_existing = (array) get_post_meta( $partner_id, '_fge_cap', true );
			$cap_existing['min'] = absint( $cap_in['min'] ?? 0 );
			$cap_existing['max'] = absint( $cap_in['max'] ?? 0 );
			update_post_meta( $partner_id, '_fge_cap', $cap_existing );
			$fmt_keys = array_keys( fge_get_event_formats_flat( false ) );
			$fmts     = array_values( array_intersect( array_map( 'sanitize_text_field', (array) ( $P['fge_event_formats'] ?? [] ) ), $fmt_keys ) );
			update_post_meta( $partner_id, '_fge_event_formats', $fmts );
			break;

		case 'ausstattung':
			$valid = fge_catalog_infra_ids();
			$infra = array_values( array_intersect( array_map( 'sanitize_text_field', (array) ( $P['fge_infra'] ?? [] ) ), $valid ) );
			update_post_meta( $partner_id, '_fge_infra', $infra );
			update_post_meta( $partner_id, '_fge_additional_equipment', sanitize_textarea_field( $P['fge_additional_equipment'] ?? '' ) );
			break;

		case 'standort':
			foreach ( [ 'street', 'house_number', 'postal_code', 'city', 'federal_state', 'free_region' ] as $k ) {
				update_post_meta( $partner_id, '_fge_' . $k, sanitize_text_field( $P[ 'fge_' . $k ] ?? '' ) );
			}
			update_post_meta( $partner_id, '_fge_latitude',  '' === ( $P['fge_latitude'] ?? '' )  ? '' : (string) (float) $P['fge_latitude'] );
			update_post_meta( $partner_id, '_fge_longitude', '' === ( $P['fge_longitude'] ?? '' ) ? '' : (string) (float) $P['fge_longitude'] );
			foreach ( [ 'poi_car', 'poi_parking', 'poi_train', 'poi_shuttle' ] as $k ) {
				update_post_meta( $partner_id, '_fge_' . $k, sanitize_text_field( $P[ 'fge_' . $k ] ?? '' ) );
			}
			// '' = keine Angabe (gleiche Drei-Zustands-Logik wie im Onboarding).
			$est = (string) ( $P['fge_arrival_estation'] ?? '' );
			update_post_meta( $partner_id, '_fge_arrival_estation', '' === $est ? '' : ( '1' === $est ? 1 : 0 ) );
			break;

		case 'kontakt':
			// Alle Felder AUSSER der Hauptkontakt-E-Mail sofort speichern.
			update_post_meta( $partner_id, '_fge_main_contact_name',   sanitize_text_field( $P['fge_main_contact_name'] ?? '' ) );
			update_post_meta( $partner_id, '_fge_main_contact_role',   sanitize_text_field( $P['fge_main_contact_role'] ?? '' ) );
			update_post_meta( $partner_id, '_fge_main_contact_phone',  sanitize_text_field( $P['fge_main_contact_phone'] ?? '' ) );
			update_post_meta( $partner_id, '_fge_event_contact_name',  sanitize_text_field( $P['fge_event_contact_name'] ?? '' ) );
			update_post_meta( $partner_id, '_fge_event_contact_email', sanitize_email( $P['fge_event_contact_email'] ?? '' ) );
			update_post_meta( $partner_id, '_fge_event_contact_phone', sanitize_text_field( $P['fge_event_contact_phone'] ?? '' ) );

			// Hauptkontakt-E-Mail: Änderung nur nach Code-Bestätigung an der NEUEN Adresse
			// übernehmen (Julius, 2026-07-06), sonst kann ein Tippfehler den Login
			// unbemerkt umleiten. Die alte Adresse wird über den Wechsel informiert.
			// Leere/ungültige Eingabe löscht NICHT die gespeicherte Adresse (Kern-Audit H3).
			$new_email = sanitize_email( $P['fge_main_contact_email'] ?? '' );
			$cur_email = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true );
			if ( $new_email !== $cur_email && is_email( $new_email ) ) {
				$ev_ctx = 'portalmail_' . $partner_id;
				if ( ! fge_ev_is_verified( $new_email, $ev_ctx ) ) {
					set_transient( 'fge_portalmail_' . $partner_id, $new_email, 15 * MINUTE_IN_SECONDS );
					$code = sanitize_text_field( $P['fge_mail_code'] ?? '' );
					if ( ! empty( $P['fge_mail_resend'] ) || '' === $code ) {
						$send = fge_ev_send_code( $new_email, $ev_ctx );
						wp_redirect( esc_url_raw( fge_portal_page_url() . '?tab=platz&edit=kontakt&mailverify=1' . ( is_wp_error( $send ) ? '&mailerr=' . $send->get_error_code() : '' ) ), 303 );
						exit;
					}
					$chk = fge_ev_check_code( $new_email, $ev_ctx, $code );
					if ( is_wp_error( $chk ) ) {
						wp_redirect( esc_url_raw( fge_portal_page_url() . '?tab=platz&edit=kontakt&mailverify=1&mailerr=' . $chk->get_error_code() ), 303 );
						exit;
					}
				}
				// Verifiziert: Info an die alte Adresse, dann übernehmen.
				if ( is_email( $cur_email ) ) {
					fge_portal_notify_contact_email_change( $partner_id, $cur_email, $new_email );
				}
				fge_ev_forget( $new_email, $ev_ctx );
				delete_transient( 'fge_portalmail_' . $partner_id );
				update_post_meta( $partner_id, '_fge_main_contact_email', $new_email );
			}
			break;

		case 'medien':
		default:
			// Logo + photos are managed asynchronously via the firmengolf/v1 REST routes.
			break;
	}

	$base = fge_portal_page_url();
	wp_redirect( esc_url_raw( $base . '?tab=platz&portal_success=profile_saved' ), 303 );
	exit;
}

/**
 * Info an die BISHERIGE Kontaktadresse, wenn die Hauptkontakt-E-Mail gewechselt
 * wird — damit ein unbefugter Wechsel auffällt (Julius, 2026-07-06).
 */
/**
 * Speichert die Indoor-Detaildaten aus dem Portal-Reiter. Validierung und
 * Persistenz laufen über die Onboarding-Slide-Logik (gleiche Feldnamen,
 * gleicher Meta-Schlüssel _fge_indoor_sim) — EIN Datenmodell für beide Wege.
 */
function fge_portal_handle_indoor_update(): void {
	if ( ( $_POST['fge_action'] ?? '' ) !== 'portal_indoor_update' ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		wp_die( 'Nicht autorisiert.', '', [ 'response' => 403 ] );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_portal_nonce'] ?? '' ) ), 'fge_portal_indoor_update' ) ) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}
	$partner_id = fge_portal_get_partner_id();
	if ( $partner_id <= 0 || ! fge_partner_has_indoor( $partner_id ) ) {
		wp_die( 'Kein gültiges Partnerprofil.', '', [ 'response' => 403 ] );
	}

	$base   = fge_portal_page_url();
	$errors = fge_onboarding_validate_slide( 'indoor-detail', $_POST );
	if ( ! empty( $errors ) ) {
		$token = wp_generate_uuid4();
		set_transient( 'fge_form_err_' . $token, [ 'errors' => $errors, 'data' => wp_unslash( $_POST ) ], 300 );
		wp_redirect( esc_url_raw( $base . '?tab=indoor&portal_err=' . rawurlencode( $token ) ), 303 );
		exit;
	}
	fge_onboarding_save_slide( $partner_id, 'indoor-detail', $_POST );
	wp_redirect( esc_url_raw( $base . '?tab=indoor&portal_success=indoor_saved' ), 303 );
	exit;
}

function fge_portal_notify_contact_email_change( int $partner_id, string $old_email, string $new_email ): void {
	if ( ! function_exists( 'fge_email_wrap' ) ) {
		return;
	}
	$name    = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$portal  = fge_portal_page_url();
	$subject = 'Kontakt-E-Mail eures Firmengolf-Portals geändert';
	$content = '
		<p style="margin:0 0 16px;">Die Kontakt-E-Mail-Adresse für <strong>' . esc_html( $name ) . '</strong> wurde soeben geändert:</p>
		<p style="margin:0 0 16px;">von <strong>' . esc_html( $old_email ) . '</strong><br>zu <strong>' . esc_html( $new_email ) . '</strong></p>
		<p style="margin:0 0 16px;">Warst du das nicht, antworte einfach auf diese Mail oder melde dich bei uns, wir prüfen das sofort.</p>
		<p style="margin:0;">' . ( function_exists( 'fge_email_button' ) ? fge_email_button( $portal, 'Zum Partnerportal' ) : '' ) . '</p>
	';
	wp_mail( $old_email, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

// ══════════════════════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_page_url(): string {
	$page = get_page_by_path( 'partnerportal' );
	return $page ? trailingslashit( get_permalink( $page->ID ) ) : trailingslashit( home_url( '/partnerportal/' ) );
}

function fge_portal_get_partner_id(): int {
	if ( current_user_can( 'manage_options' ) ) {
		// Musterumgebung: Admins agieren im Portal auf dem Muster-Platz — dadurch
		// funktionieren auch alle Aktionen (Event anlegen, Profil speichern, Termin
		// bestätigen) in der Demo. Ohne angelegte Demo bleibt es beim alten 0-Verhalten.
		return function_exists( 'fge_demo_partner_id' ) ? fge_demo_partner_id() : 0;
	}
	$pid = fge_partner_for_user( get_current_user_id() );
	return $pid > 0 ? $pid : -1;
}

/**
 * Welcher Platz gehört diesem User? Unterstützt mehrere Logins pro Platz
 * (2026-07-21): jeder eingeladene ASP bekommt beim Annehmen den Reverse-Link
 * _fge_managed_partner_id am eigenen User. Bestandspartner (ein primärer User
 * am Platz via _fge_assigned_wp_user_id) bleiben über den Fallback kompatibel.
 */
function fge_partner_for_user( int $user_id = 0 ): int {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	if ( $user_id <= 0 ) {
		return 0;
	}
	$pid = (int) get_user_meta( $user_id, '_fge_managed_partner_id', true );
	if ( $pid > 0 && get_post_type( $pid ) === 'firmengolf_partner' ) {
		return $pid;
	}
	$posts = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'meta_key'    => '_fge_assigned_wp_user_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => $user_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'numberposts' => 1,
		'fields'      => 'ids',
		'post_status' => 'any',
	] );
	return $posts ? (int) $posts[0] : 0;
}

/** Darf dieser User diesen Platz verwalten? (Admin, primärer oder zusätzlicher ASP.) */
function fge_user_can_manage_partner( int $partner_id, int $user_id = 0 ): bool {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	if ( $partner_id <= 0 || $user_id <= 0 ) {
		return false;
	}
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}
	if ( (int) get_user_meta( $user_id, '_fge_managed_partner_id', true ) === $partner_id ) {
		return true;
	}
	return (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true ) === $user_id;
}

function fge_portal_access_check(): void {
	if ( is_user_logged_in() ) {
		$user = wp_get_current_user();
		if ( ! current_user_can( 'manage_options' ) && ! in_array( 'firmengolf_partner', (array) $user->roles, true ) ) {
			wp_die(
				'<p>Du hast keine Berechtigung für diesen Bereich.</p>'
				. '<p><a href="' . esc_url( wp_logout_url( fge_portal_page_url() ) ) . '">Abmelden und mit anderem Konto anmelden</a></p>'
				. '<p><a href="' . esc_url( home_url( '/' ) ) . '">Zurück zur Startseite</a></p>',
				'Kein Zugriff',
				[ 'response' => 403 ]
			);
		}
	}
}

function fge_portal_render_gate(): void {
	$login_url     = wp_login_url( fge_portal_page_url() );
	$onboarding_url = trailingslashit( home_url( '/partner-onboarding/' ) );
	$logo_url      = fge_get_logo_url();
	?>
	<div class="fp-gate">
		<div class="fp-gate-card">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="fp-gate-logo">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="Firmengolf" width="122" height="28">
			</a>
			<h1 class="fp-gate-title">Partner-Portal</h1>
			<p class="fp-gate-sub">Melde dich mit deinen Zugangsdaten an, um dein Partner-Profil zu verwalten.</p>

			<?php
			wp_login_form( [
				'redirect'       => fge_portal_page_url(),
				'label_username' => 'E-Mail-Adresse',
				'label_password' => 'Passwort',
				'label_log_in'   => 'Anmelden',
				'id_username'    => 'fp_gate_user',
				'id_password'    => 'fp_gate_pass',
				'id_submit'      => 'fp_gate_submit',
				'remember'       => true,
				'label_remember' => 'Angemeldet bleiben',
				'value_remember' => true,
			] );
			?>

			<div class="fp-gate-divider"><span>Noch kein Konto?</span></div>

			<a href="<?php echo esc_url( $onboarding_url ); ?>" class="fp-gate-cta">
				Jetzt Partner werden
				<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</a>

			<?php // Einstieg für Interessenten, die erst hier landen (Funnel-Audit Paket A): erst verstehen, dann registrieren. ?>
			<p class="fp-gate-info">Neu bei Firmengolf? <a href="<?php echo esc_url( home_url( '/golfplatz-partner/' ) ); ?>">So funktioniert die Partnerschaft →</a></p>
		</div>
	</div>
	<?php
}

function fge_portal_get_active_tab(): string {
	$allowed = [ 'uebersicht', 'angebote', 'indoor', 'anfragen', 'kalender', 'platz', 'team', 'kennzahlen', 'sichtbarkeit' ];
	$tab     = sanitize_key( $_GET['tab'] ?? 'uebersicht' );
	return in_array( $tab, $allowed, true ) ? $tab : 'uebersicht';
}

function fge_portal_validate_event_fields(): array {
	$errors = [];
	if ( sanitize_text_field( wp_unslash( $_POST['fge_post_title'] ?? '' ) ) === '' ) {
		$errors['fge_post_title'] = 'Bitte gib einen Eventtitel an.';
	}
	if ( sanitize_text_field( wp_unslash( $_POST['fge_event_type'] ?? '' ) ) === '' ) {
		$errors['fge_event_type'] = 'Bitte wähle eine Eventart aus.';
	}
	// Gemischte Einzelposten (pro Person + pauschal): ohne max. Teilnehmerzahl kann
	// kein ehrlicher „ab"-Preis pro Person berechnet werden (Pauschale wird umgelegt).
	if ( sanitize_text_field( wp_unslash( $_POST['fge_price_mode'] ?? '' ) ) === 'einzel'
		&& absint( $_POST['fge_participants_max'] ?? 0 ) < 1 ) {
		$has_pp = $has_flat = false;
		foreach ( preg_split( '/\r?\n/', (string) wp_unslash( $_POST['fge_line_items'] ?? '' ) ) as $vline ) {
			$vparts = explode( '|', $vline, 3 );
			if ( '' === trim( $vparts[0] ?? '' ) ) {
				continue;
			}
			if ( trim( $vparts[2] ?? '' ) === 'person' ) {
				$has_pp = true;
			} else {
				$has_flat = true;
			}
		}
		if ( $has_pp && $has_flat ) {
			$errors['fge_participants_max'] = 'Bitte gib die maximale Teilnehmerzahl an, bei Posten „pro Person" plus Pauschalen berechnen wir daraus den „ab"-Preis pro Person.';
		}
	}
	return $errors;
}

function fge_portal_save_event_meta( int $post_id ): void {
	$allowed_types    = array_keys( fge_get_event_formats()['standard'] );
	$allowed_weekdays = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];

	$san = static function( string $key, array $allowed ): string {
		$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		return in_array( $val, $allowed, true ) ? $val : '';
	};

	update_post_meta( $post_id, '_fge_event_type',      $san( 'fge_event_type', $allowed_types ) );
	update_post_meta( $post_id, '_fge_card_description', sanitize_textarea_field( wp_unslash( $_POST['fge_card_description'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_participants_min', absint( $_POST['fge_participants_min'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_participants_max', absint( $_POST['fge_participants_max'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_duration',         sanitize_text_field( wp_unslash( $_POST['fge_duration'] ?? '' ) ) );
	// Saison nur übernehmen, wenn das Feld im Formular existiert (Portal-Form hat es nicht mehr).
	if ( isset( $_POST['fge_season'] ) ) {
		update_post_meta( $post_id, '_fge_season', sanitize_text_field( wp_unslash( $_POST['fge_season'] ) ) );
	}
	// Standort kommt vom Platz: Suche läuft über PLZ/Entfernung, nicht über Region-Freitext.
	$loc_partner = (int) get_post_meta( $post_id, '_fge_assigned_partner_id', true );
	if ( $loc_partner <= 0 ) {
		$loc_partner = fge_portal_get_partner_id();
	}
	if ( $loc_partner > 0 ) {
		$lp_name = (string) get_post_meta( $loc_partner, '_fge_public_golfclub_name', true ) ?: get_the_title( $loc_partner );
		$lp_city = (string) get_post_meta( $loc_partner, '_fge_city', true );
		update_post_meta( $post_id, '_fge_event_location', trim( $lp_name . ( '' !== $lp_city ? ', ' . $lp_city : '' ) ) );
		update_post_meta( $post_id, '_fge_region', $lp_city );
	}

	$raw_days   = array_map( 'sanitize_text_field', (array) ( $_POST['fge_available_weekdays'] ?? [] ) );
	$clean_days = array_values( array_filter( $raw_days, static function( $v ) use ( $allowed_weekdays ) {
		return in_array( $v, $allowed_weekdays, true );
	} ) );
	update_post_meta( $post_id, '_fge_available_weekdays', $clean_days );

	// has_*-Flags aus den inkludierten Leistungen ableiten, damit Filter und
	// Badges („Indoor-Backup" etc.) ohne eigenes Checkbox-Grid gültig bleiben.
	$inc_raw      = mb_strtolower( (string) wp_unslash( $_POST['fge_event_includes'] ?? '' ) );
	$flag_needles = [
		'has_golf_teacher'      => [ 'pga', 'coaching', 'golflehrer', 'schnupperkurs', 'platzreife' ],
		'has_range_usage'       => [ 'range' ],
		'has_rental_clubs'      => [ 'leihschläger' ],
		'has_range_balls'       => [ 'bälle' ],
		'has_putting_shortgame' => [ 'putting', 'kurzspiel' ],
		'has_meeting_room'      => [ 'meetingraum', 'seminarraum', 'konferenz' ],
		'has_breakfast'         => [ 'frühstück' ],
		'has_lunch'             => [ 'lunch', 'mittagessen' ],
		'has_dinner'            => [ 'abendessen', 'dinner' ],
		'has_shuttle'           => [ 'shuttle' ],
		'has_branding'          => [ 'branding' ],
	];
	foreach ( $flag_needles as $flag => $needles ) {
		$on = '0';
		foreach ( $needles as $needle ) {
			if ( false !== mb_strpos( $inc_raw, $needle ) ) {
				$on = '1';
				break;
			}
		}
		update_post_meta( $post_id, '_fge_' . $flag, $on );
	}

	// ── Termin-Freigabe: Modus + Event-Ansprechpartner (inkl. Quick-Add neuer Kontakte) ──
	update_post_meta( $post_id, '_fge_release_mode', in_array( $_POST['fge_release_mode'] ?? '', [ 'us', 'approve' ], true ) ? $_POST['fge_release_mode'] : 'us' );
	$responders = array_values( array_filter( array_map( 'absint', (array) ( $_POST['fge_event_responders'] ?? [] ) ) ) );
	$rp_partner = fge_portal_get_partner_id();
	if ( $rp_partner > 0 && function_exists( 'fge_contact_add' ) ) {
		$n_names = (array) ( $_POST['fge_new_responder_name'] ?? [] );
		$n_roles = (array) ( $_POST['fge_new_responder_role'] ?? [] );
		$n_mails = (array) ( $_POST['fge_new_responder_email'] ?? [] );
		$n_perms = (array) ( $_POST['fge_new_responder_perm'] ?? [] );
		foreach ( $n_names as $i => $nn ) {
			$nm = sanitize_text_field( wp_unslash( $nn ) );
			$em = sanitize_email( wp_unslash( $n_mails[ $i ] ?? '' ) );
			if ( '' === $nm || ! is_email( $em ) ) {
				continue;
			}
			$n_role = sanitize_text_field( wp_unslash( $n_roles[ $i ] ?? '' ) );
			$n_perm = strtolower( sanitize_text_field( wp_unslash( $n_perms[ $i ] ?? '' ) ) );
			if ( ! in_array( $n_perm, [ 'vote', 'notify' ], true ) ) {
				$n_perm = ''; // Standard nach Rolle.
			}
			$cid = fge_contact_add( $rp_partner, [
				'name'       => $nm,
				'email'      => $em,
				'role'       => $n_role,
				'permission' => $n_perm,
			] );
			// Nur Abstimmende werden fürs Event vorgemerkt; „Nur informieren" stimmt nicht ab.
			$n_effective = function_exists( 'fge_contact_normalize_permission' ) ? fge_contact_normalize_permission( $n_perm, $n_role ) : $n_perm;
			if ( $cid > 0 && 'notify' !== $n_effective ) {
				$responders[] = $cid;
			}
		}
	}
	update_post_meta( $post_id, '_fge_event_responder_ids', array_values( array_unique( $responders ) ) );

	// Beide Felder existieren im Portal-Formular nicht mehr; Admin-Werte erhalten.
	if ( isset( $_POST['fge_additional_services'] ) ) {
		update_post_meta( $post_id, '_fge_additional_services', sanitize_textarea_field( wp_unslash( $_POST['fge_additional_services'] ) ) );
	}
	if ( isset( $_POST['fge_price_note'] ) ) {
		update_post_meta( $post_id, '_fge_price_note', sanitize_textarea_field( wp_unslash( $_POST['fge_price_note'] ) ) );
	}

	// ── Preismodell + Inhalt (rev. 2) ──
	$price_mode = in_array( $_POST['fge_price_mode'] ?? '', [ 'gesamt', 'einzel' ], true ) ? $_POST['fge_price_mode'] : 'gesamt';
	update_post_meta( $post_id, '_fge_price_mode', $price_mode );
	update_post_meta( $post_id, '_fge_price_amount', fge_parse_de_amount( wp_unslash( $_POST['fge_price_amount'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_price_basis', in_array( $_POST['fge_price_basis'] ?? '', [ 'person', 'pauschal' ], true ) ? $_POST['fge_price_basis'] : 'person' );
	$pli = [];
	foreach ( preg_split( '/\r?\n/', (string) wp_unslash( $_POST['fge_line_items'] ?? '' ) ) as $line ) {
		$line = trim( $line );
		if ( $line === '' ) {
			continue;
		}
		// 3 Spalten: label | cost | basis — Limit 2 hat die Basis verworfen und
		// JEDEN p.P.-Posten als Pauschale gerechnet (Kern-Audit K1, 2026-07-08).
		$parts = explode( '|', $line, 3 );
		$lbl   = sanitize_text_field( trim( $parts[0] ?? '' ) );
		$cst   = fge_parse_de_amount( $parts[1] ?? '' );
		$bas   = 'person' === trim( $parts[2] ?? '' ) ? 'person' : 'pauschal';
		if ( $lbl !== '' ) {
			$pli[] = [ 'label' => $lbl, 'cost' => $cst, 'basis' => $bas ];
		}
	}
	update_post_meta( $post_id, '_fge_line_items', $pli );
	$pinc = array_values( array_filter( array_map(
		static fn( $l ): string => sanitize_text_field( trim( $l ) ),
		preg_split( '/\r?\n/', (string) wp_unslash( $_POST['fge_event_includes'] ?? '' ) )
	) ) );
	update_post_meta( $post_id, '_fge_event_includes', $pinc );
	update_post_meta( $post_id, '_fge_event_dayflow', sanitize_textarea_field( wp_unslash( $_POST['fge_event_dayflow'] ?? '' ) ) );

	// Öffentlichen Preis aus dem Modell spiegeln (eine Quelle: event-pricing.php).
	$pr = fge_event_pricing( $post_id );
	if ( $pr['gross'] > 0 ) {
		update_post_meta( $post_id, '_fge_sale_price_net', $pr['gross'] );
		update_post_meta( $post_id, '_fge_public_price_label', fge_event_price_label( $post_id ) );
	} else {
		// Preis auf 0/leer gesetzt → alten öffentlichen Preis nicht stehen lassen (Audit C9).
		delete_post_meta( $post_id, '_fge_sale_price_net' );
		delete_post_meta( $post_id, '_fge_public_price_label' );
	}
	// Verfügbarkeits-Kontakt: Felder existieren im Portal-Formular nicht mehr,
	// vorhandene Werte (z. B. aus dem Admin) bleiben erhalten.
	if ( isset( $_POST['fge_availability_contact_name'] ) ) {
		update_post_meta( $post_id, '_fge_availability_contact_name',  sanitize_text_field( wp_unslash( $_POST['fge_availability_contact_name'] ) ) );
		update_post_meta( $post_id, '_fge_availability_contact_email', sanitize_email( wp_unslash( $_POST['fge_availability_contact_email'] ?? '' ) ) );
		update_post_meta( $post_id, '_fge_availability_contact_phone', sanitize_text_field( wp_unslash( $_POST['fge_availability_contact_phone'] ?? '' ) ) );
	}
}

function fge_portal_format_event_status( string $status ): string {
	return [
		'entwurf'               => 'Entwurf',
		'zur_pruefung'          => 'In Prüfung',
		'freigegeben'           => 'Freigegeben',
		'aenderung_in_pruefung' => 'Änderung in Prüfung',
		'pausiert'              => 'Pausiert',
		'abgelehnt'             => 'Abgelehnt',
	][ $status ] ?? $status;
}

function fge_portal_format_partner_status( string $status ): string {
	return [
		'in_pruefung' => 'In Prüfung',
		'aktiv'       => 'Aktiv',
		'pausiert'    => 'Pausiert',
		'abgelehnt'   => 'Abgelehnt',
	][ $status ] ?? $status;
}

function fge_portal_status_class( string $status ): string {
	return [
		'freigegeben'           => 'green',
		'aktiv'                 => 'green',
		'zur_pruefung'          => 'orange',
		'aenderung_in_pruefung' => 'orange',
		'in_pruefung'           => 'orange',
		'entwurf'               => 'gray',
		'pausiert'              => 'gray',
		'abgelehnt'             => 'red',
		'abgeschlossen'         => 'gray',
		'verloren'              => 'red',
	][ $status ] ?? 'gray';
}

function fge_portal_format_event_type( string $type ): string {
	return fge_format_event_type( $type );
}

// ── New Helpers ───────────────────────────────────────────────────────────────

function fge_portal_make_monogram( string $title ): string {
	$cleaned  = preg_replace( '/^\s*(Golfclub|Golf-Club|Golf Club|GC)\s+/i', '', trim( $title ) ) ?? $title;
	$words    = preg_split( '/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
	$initials = '';
	foreach ( $words as $word ) {
		$letter = strtoupper( mb_substr( $word, 0, 1 ) );
		if ( $letter !== '' && ctype_alpha( $letter ) ) {
			$initials .= $letter;
			if ( mb_strlen( $initials ) >= 2 ) {
				break;
			}
		}
	}
	if ( mb_strlen( $initials ) < 2 ) {
		$initials = strtoupper( mb_substr( $title, 0, 2 ) );
	}
	return $initials;
}

function fge_portal_relative_time( string $datetime ): string {
	$diff = time() - (int) strtotime( $datetime );
	if ( $diff < 3600 ) {
		$mins = max( 1, (int) round( $diff / 60 ) );
		return "vor {$mins} Min.";
	}
	if ( $diff < 86400 ) {
		$hours = (int) round( $diff / 3600 );
		return "vor {$hours} Std.";
	}
	$days = (int) round( $diff / 86400 );
	if ( $days === 1 ) {
		return 'gestern';
	}
	return "vor {$days} Tagen";
}

function fge_portal_count_new_requests( int $partner_id ): int {
	if ( $partner_id <= 0 ) {
		return 0;
	}
	$cutoff   = gmdate( 'Y-m-d H:i:s', time() - 172800 );
	$requests = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'date_query'  => [ [ 'after' => $cutoff, 'inclusive' => true ] ],
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
		'fields'      => 'ids',
	] );
	return count( $requests );
}

function fge_portal_avatar_color( int $index ): string {
	return [ 'sand', 'green', 'clay' ][ $index % 3 ];
}

function fge_portal_name_initials( string $name ): string {
	$words    = preg_split( '/\s+/', trim( $name ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
	$initials = '';
	foreach ( $words as $word ) {
		$l = strtoupper( mb_substr( $word, 0, 1 ) );
		if ( $l !== '' ) {
			$initials .= $l;
			if ( mb_strlen( $initials ) >= 2 ) {
				break;
			}
		}
	}
	return $initials ?: strtoupper( mb_substr( $name, 0, 2 ) );
}

// ══════════════════════════════════════════════════════════════════════════════
// MAIN RENDER
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_render(): void {
	if ( ! is_user_logged_in() ) {
		fge_portal_render_gate();
		return;
	}

	fge_portal_access_check();

	$partner_id = fge_portal_get_partner_id();

	// Ausweg aus allen Sackgassen-Screens: abmelden und mit anderem Konto rein.
	$switch_url = wp_logout_url( fge_portal_page_url() );

	if ( current_user_can( 'manage_options' ) ) {
		// Musterumgebung (2026-07-22): Admins sehen das Portal des Muster-Platzes
		// mit befüllten Demo-Daten, zum Vorführen in Partner-Meetings.
		$demo_id = function_exists( 'fge_demo_partner_id' ) ? fge_demo_partner_id() : 0;
		if ( $demo_id > 0 ) {
			$partner_id = $demo_id;
			?>
			<div style="background:#20294D;color:#FBFAF6;font-size:13px;padding:9px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
				<span><strong>Musterumgebung.</strong> Alle Daten sind Beispiele, nichts davon ist öffentlich sichtbar.<?php if ( ! empty( $_GET['demo_reset'] ) ) : ?> <span style="color:#00C896;">Frisch zurückgesetzt ✓</span><?php endif; ?></span>
				<span style="display:inline-flex;gap:14px;align-items:center;">
					<a href="<?php echo esc_url( fge_demo_seed_url() ); ?>" style="color:#C2D4F2;">Demo zurücksetzen</a>
					<a href="<?php echo esc_url( admin_url() ); ?>" style="color:#C2D4F2;">Zur WP-Verwaltung</a>
				</span>
			</div>
			<?php
		} else {
			?>
			<div class="fg-portal-standalone">
				<p class="fg-portal-standalone-title">Du bist als Administrator angemeldet.</p>
				<p>Das Partnerportal ist für Golfplatz-Partner-Nutzer konzipiert. Alle Daten verwaltest du über die <a href="<?php echo esc_url( admin_url() ); ?>">WordPress-Administrationsoberfläche</a>.</p>
				<?php if ( function_exists( 'fge_demo_seed_url' ) ) : ?>
					<p><a class="fg-btn-brand" href="<?php echo esc_url( fge_demo_seed_url() ); ?>" style="display:inline-block;margin-top:4px;">Musterumgebung anlegen (Demo-Portal)</a></p>
				<?php endif; ?>
				<p><a href="<?php echo esc_url( $switch_url ); ?>">Abmelden und mit anderem Konto anmelden →</a></p>
			</div>
			<?php
			return;
		}
	}

	if ( $partner_id === -1 ) {
		?>
		<div class="fg-portal-standalone">
			<p class="fg-portal-standalone-title">Mit diesem Konto ist kein Partnerprofil verknüpft.</p>
			<p>Vielleicht bist du mit dem falschen Konto angemeldet? Du kannst dich abmelden und mit einem anderen Konto anmelden.</p>
			<p><a class="fg-btn-brand" href="<?php echo esc_url( $switch_url ); ?>" style="display:inline-block;margin-top:4px;">Abmelden &amp; Konto wechseln</a></p>
			<p style="margin-top:16px;">Du kommst trotzdem nicht rein? Wende dich an das Firmengolf-Team: <a href="mailto:<?php echo esc_attr( fge_company()['email_partner'] ?? fge_company()['email_events'] ); ?>"><?php echo esc_html( fge_company()['email_partner'] ?? fge_company()['email_events'] ); ?></a></p>
		</div>
		<?php
		return;
	}

	// Frisch eingereichte Partner (noch in Prüfung) kommen voll ins Portal (Julius:
	// sollen direkt Events erstellen können) — sie sehen nur ein Status-Banner oben.
	$p_pending       = 'in_pruefung' === (string) get_post_meta( $partner_id, '_fge_partner_status', true );
	$p_number        = '';
	$p_submitted_fmt = '';
	if ( $p_pending ) {
		$p_number    = function_exists( 'fge_partner_number' ) ? fge_partner_number( $partner_id ) : '';
		$p_submitted = (string) get_post_meta( $partner_id, '_fge_submitted_at', true );
		if ( '' === $p_submitted ) {
			$p_submitted = (string) get_post_field( 'post_modified', $partner_id );
		}
		$p_submitted_fmt = $p_submitted ? date_i18n( 'j. F Y', strtotime( $p_submitted ) ) : '';
	}

	$active_tab    = fge_portal_get_active_tab();
	$partner_name  = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$success       = sanitize_key( $_GET['portal_success'] ?? '' );
	$new_requests  = fge_portal_count_new_requests( $partner_id );
	$user          = wp_get_current_user();
	$user_initials = strtoupper( mb_substr( $user->first_name ?: $user->display_name, 0, 1 ) )
	               . strtoupper( mb_substr( $user->last_name ?: '', 0, 1 ) );
	$logo_url      = fge_get_logo_url();
	$archive_url   = get_post_type_archive_link( 'firmengolf_event' );

	$svg = static function ( string $p ): string {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
	};
	$tabs = [
		'uebersicht' => [ 'Übersicht',       '<path d="M3 3v18h18"/><path d="M19 9l-5 5-4-4-3 3"/>' ],
		'angebote'   => [ 'Angebote',        '<path d="M5 22V4M5 4l13 3-13 3"/>' ],
		'indoor'     => [ 'Indoor-Golf',     '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>' ],
		'anfragen'   => [ 'Anfragen',        '<path d="M22 12h-5l-2 3h-6l-2-3H2"/><path d="M5 5h14l3 7v7H2v-7z"/>' ],
		'kalender'   => [ 'Kalender',        '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>' ],
		'platz'      => [ 'Platz',           '<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>' ],
		'team'       => [ 'Ansprechpartner', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>' ],
	];
	// Indoor-Reiter nur für Partner mit Indoor in der Ausstattung (Abschnitt 3b).
	if ( ! fge_partner_has_indoor( $partner_id ) ) {
		unset( $tabs['indoor'] );
	}
	// Ansicht je Partner-Typ (Julius, 28.08.): Der Coach verwaltet eine PERSON,
	// die Indoor-Anlage eine Location, kein „Platz".
	$portal_ptype = function_exists( 'fge_partner_type' ) ? fge_partner_type( $partner_id ) : 'course';
	if ( 'coach' === $portal_ptype ) {
		$tabs['platz'][0] = 'Profil';
		$tabs['platz'][1] = '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/>';
	} elseif ( 'indoor' === $portal_ptype ) {
		$tabs['platz'][0] = 'Anlage';
	}
	?>
	<div class="fgpp">
	<nav class="nav">
		<div class="nav-inner">
			<a href="<?php echo esc_url( fge_portal_page_url() ); ?>" class="brand">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="Firmengolf">
				<span class="brand-context">Partner-Portal <span class="pill">Live</span></span>
			</a>

			<div class="nav-tabs">
				<?php foreach ( $tabs as $key => $t ) : ?>
					<button class="nav-tab<?php echo $active_tab === $key ? ' active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>" type="button">
						<?php echo $svg( $t[1] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php echo esc_html( $t[0] ); ?>
						<?php if ( $key === 'anfragen' && $new_requests > 0 ) : ?><span class="badge"><?php echo (int) $new_requests; ?></span><?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="nav-end">
				<?php // Vorschau = die EIGENE Golfplatz-Seite (verknüpfter Nutzer darf sie auch vor Freischaltung sehen), nicht das Event-Archiv. ?>
				<a href="<?php echo esc_url( get_permalink( $partner_id ) ); ?>" class="nav-link" target="_blank" rel="noopener">Vorschau</a>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="nav-link">Abmelden</a>
				<div class="nav-avatar" title="<?php echo esc_attr( $user->display_name ); ?>"><?php echo esc_html( $user_initials ?: 'P' ); ?></div>
			</div>
		</div>
	</nav>
	</div>

	<div class="fp-page-wrap">

		<?php if ( $p_pending ) : ?>
			<div class="fg-portal-global-notice fg-portal-global-notice--pending" role="status">
				<span class="fg-portal-status-pill"><span class="fg-portal-status-dot"></span>In Prüfung</span>
				<span>
					Dein Partnerprofil ist eingereicht<?php if ( $p_submitted_fmt !== '' ) : ?> (am <?php echo esc_html( $p_submitted_fmt ); ?><?php echo $p_number !== '' ? ', Vorgang ' . esc_html( $p_number ) : ''; ?>)<?php endif; ?> und wird von Firmengolf geprüft, in der Regel innerhalb von zwei Werktagen.
					Du kannst schon jetzt alles einrichten und Events erstellen. Sobald dein Profil freigeschaltet ist, geht dein Platz öffentlich online.
				</span>
			</div>
		<?php endif; ?>

		<?php
		// Willkommens-Panel beim ersten Login nach Einladung/Onboarding (einmalig, wegklickbar).
		if ( '1' === (string) get_user_meta( get_current_user_id(), 'fge_welcome_pending', true ) ) :
			$wp_dismiss = wp_nonce_url( add_query_arg( [ 'portal_action' => 'dismiss_welcome' ], fge_portal_page_url() ), 'fge_dismiss_welcome' );
			$wp_phone   = fge_company()['phone_display'] ?? '';
			?>
			<div class="fp-welcome">
				<div class="fp-welcome-head">
					<h3>Willkommen, <?php echo esc_html( $partner_name ); ?>! 👋</h3>
					<a href="<?php echo esc_url( $wp_dismiss ); ?>" class="fp-welcome-close" aria-label="Ausblenden">✕</a>
				</div>
				<p>Wir haben dein Profil schon komplett eingerichtet, du musst nichts von vorn anlegen. Drei Schritte zum Start:</p>
				<ol>
					<li><a href="<?php echo esc_url( fge_portal_page_url() . '?tab=platz' ); ?>">Profil prüfen</a> und Feinheiten anpassen, Beschreibung, Ausstattung, Kontakt</li>
					<li><a href="<?php echo esc_url( fge_portal_page_url() . '?tab=platz&edit=medien' ); ?>">Fotos ansehen</a>, gern eigene Bilder hochladen, die verkaufen am besten</li>
					<li><a href="<?php echo esc_url( fge_portal_page_url() . '?tab=angebote&portal_action=new' ); ?>">Erstes Event-Angebot anlegen</a>, erst damit geht deine Seite öffentlich live</li>
				</ol>
				<p class="fp-welcome-foot">
					<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( get_permalink( $partner_id ) ); ?>" target="_blank" rel="noopener">Vorschau deiner Seite ↗</a>
					<?php if ( '' !== $wp_phone ) : ?><span>Fragen? Ruf uns an: <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $wp_phone ) ); ?>"><?php echo esc_html( $wp_phone ); ?></a></span><?php endif; ?>
				</p>
			</div>
		<?php endif; ?>

		<?php
		$err_notice = sanitize_key( $_GET['portal_err_notice'] ?? '' );
		$err_texts  = [
			'expired'         => 'Deine Sitzung war abgelaufen, die Aktion wurde nicht ausgeführt. Bitte versuch es noch einmal.',
			'already_final'   => 'Für diese Anfrage ist der Termin bereits fix, er kann nicht mehr geändert werden. Melde dich bei uns, falls sich etwas geändert hat.',
			'invalid_date'    => 'Dieser Termin konnte nicht bestätigt werden. Bitte lade die Seite neu und versuch es erneut.',
			'contact_invalid' => 'Der Ansprechpartner konnte nicht gespeichert werden, bitte gib mindestens Name und eine gültige E-Mail-Adresse an.',
		];
		if ( isset( $err_texts[ $err_notice ] ) ) : ?>
			<div class="fg-portal-global-notice fg-portal-global-notice--error" role="alert">
				<?php echo esc_html( $err_texts[ $err_notice ] ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $success !== '' ) : ?>
			<div class="fg-portal-global-notice fg-portal-global-notice--success" role="status">
				<?php if ( $success === 'event_saved' ) : ?>
					Dein Eventangebot wurde eingereicht und wird von Firmengolf geprüft.
				<?php elseif ( $success === 'event_updated' ) : ?>
					Das Event wurde aktualisiert und wird erneut geprüft.
				<?php elseif ( $success === 'profile_saved' ) : ?>
					Dein Profil wurde gespeichert.
				<?php elseif ( $success === 'event_paused' ) : ?>
					Das Angebot wurde pausiert und ist nicht mehr öffentlich sichtbar.
				<?php elseif ( $success === 'event_reactivated' ) : ?>
					Das Angebot wurde reaktiviert und ist wieder öffentlich sichtbar.
				<?php elseif ( $success === 'contact_saved' ) : ?>
					Ansprechpartner gespeichert.
				<?php elseif ( $success === 'contact_removed' ) : ?>
					Ansprechpartner entfernt.
				<?php elseif ( $success === 'date_confirmed' ) : ?>
					Termin bestätigt, Firmengolf kümmert sich um Angebot und Buchung.
				<?php elseif ( $success === 'indoor_saved' ) : ?>
					Eure Indoor-Daten sind gespeichert.
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php
		// Nur die aktive Sektion rendern (Audit C11): vorher liefen bei jedem Aufruf
		// alle 7 Sektionen inkl. sämtlicher Queries. Tab-Wechsel navigiert jetzt per URL.
		$sections_map = [
			'uebersicht' => 'fge_portal_section_uebersicht',
			'angebote'   => 'fge_portal_section_angebote',
			'indoor'     => 'fge_portal_section_indoor',
			'anfragen'   => 'fge_portal_section_requests',
			'kalender'   => 'fge_portal_section_kalender',
			'platz'      => 'fge_portal_section_platz',
			'team'       => 'fge_portal_section_team',
			'kennzahlen' => 'fge_portal_section_stats',
			'sichtbarkeit' => 'fge_portal_section_sichtbarkeit',
		];
		$section_fn = $sections_map[ $active_tab ] ?? 'fge_portal_section_uebersicht';
		?>
		<div id="fp-tab-<?php echo esc_attr( $active_tab ); ?>" class="fp-section"><?php $section_fn( $partner_id ); ?></div>

	</div>

	<script>
	(function () {
		document.querySelectorAll('.nav-tab').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var url = new URL(window.location.href);
				url.searchParams.set('tab', btn.dataset.tab);
				['portal_action', 'event_id', 'portal_success', 'portal_err', 'portal_err_notice', 'preset_type', 'req', 'edit', 'filter'].forEach(function (p) {
					url.searchParams.delete(p);
				});
				window.location.href = url.toString();
			});
		});
	})();

	// Image upload previews
	(function () {
		// Logo: show image inside .fp-logo-upload, hide placeholder children
		var logoInput = document.querySelector('input[name="fge_partner_logo"]');
		if (logoInput) {
			logoInput.addEventListener('change', function () {
				if (!this.files[0]) return;
				var label = this.closest('.fp-logo-upload');
				var reader = new FileReader();
				reader.onload = function (e) {
					var img = label.querySelector('img');
					if (!img) {
						img = document.createElement('img');
						label.insertBefore(img, label.firstChild);
					}
					img.src = e.target.result;
					Array.from(label.children).forEach(function (el) {
						if (el !== img && el !== logoInput) el.style.display = 'none';
					});
				};
				reader.readAsDataURL(this.files[0]);
			});
		}

		// Partner cover: set background-image on .fp-cover-upload-profile
		var coverInput = document.querySelector('input[name="fge_partner_cover"]');
		if (coverInput) {
			coverInput.addEventListener('change', function () {
				if (!this.files[0]) return;
				var label = this.closest('.fp-cover-upload-profile');
				var reader = new FileReader();
				reader.onload = function (e) {
					label.style.backgroundImage = 'url(' + e.target.result + ')';
					label.style.backgroundSize = 'cover';
					label.style.backgroundPosition = 'center';
					label.style.borderStyle = 'solid';
					Array.from(label.children).forEach(function (el) {
						if (el !== coverInput) el.style.display = 'none';
					});
					var hint = document.createElement('span');
					hint.className = 'fp-cover-replace-hint';
					hint.innerHTML = '<span style="font-size:12px;">Bild tauschen</span>';
					hint.style.display = 'flex';
					label.insertBefore(hint, coverInput);
				};
				reader.readAsDataURL(this.files[0]);
			});
		}

		// Event cover: set background-image on .fp-cover-upload
		var eventCoverInput = document.querySelector('input[name="fge_event_cover"]');
		if (eventCoverInput) {
			eventCoverInput.addEventListener('change', function () {
				if (!this.files[0]) return;
				var label = this.closest('.fp-cover-upload');
				var reader = new FileReader();
				reader.onload = function (e) {
					label.style.backgroundImage = 'url(' + e.target.result + ')';
					label.style.backgroundSize = 'cover';
					label.style.backgroundPosition = 'center';
					label.classList.add('has-image');
					var empty = label.querySelector('.fp-cover-empty');
					if (empty) empty.style.display = 'none';
					var hint = label.querySelector('.fp-cover-replace-hint');
					if (!hint) {
						hint = document.createElement('span');
						hint.className = 'fp-cover-replace-hint';
						hint.innerHTML = '<span style="font-size:12px;">Foto tauschen</span>';
						label.insertBefore(hint, eventCoverInput);
					}
					hint.style.display = 'flex';
				};
				reader.readAsDataURL(this.files[0]);
			});
		}
	})();
	</script>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: ÜBERSICHT
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Profil-Vollständigkeit als Checkliste: jeder Punkt zahlt messbar auf die
 * Sichtbarkeit ein (vollständige Profile wirken besser und konvertieren besser).
 */
function fge_portal_visibility_checklist( int $partner_id ): array {
	$m       = static fn( string $k ): string => (string) get_post_meta( $partner_id, '_fge_' . $k, true );
	$base    = fge_portal_page_url();
	$gallery = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $partner_id, '_fge_gallery_attachment_ids', true ) ) ) );
	$infra   = (array) get_post_meta( $partner_id, '_fge_infra', true );
	$events  = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );
	$types = [];
	foreach ( $events as $eid ) {
		$types[ (string) get_post_meta( $eid, '_fge_event_type', true ) ] = true;
	}
	$pois = function_exists( 'fge_partner_arrival_pois' ) ? fge_partner_arrival_pois( $partner_id ) : [];

	return [
		[ 'done' => '' !== trim( $m( 'public_short_description' ) ), 'label' => 'Beschreibung deines Platzes', 'hint' => '2 bis 3 Sätze, was euch für Firmen besonders macht', 'url' => $base . '?tab=platz&edit=steckbrief' ],
		[ 'done' => (int) $m( 'logo_attachment_id' ) > 0, 'label' => 'Euer Logo', 'hint' => 'erscheint auf Profil und Event-Karten', 'url' => $base . '?tab=platz&edit=medien' ],
		[ 'done' => count( $gallery ) >= 3, 'label' => 'Mindestens 3 eigene Fotos', 'hint' => 'Angebote mit echten Fotos bekommen deutlich mehr Anfragen', 'url' => $base . '?tab=platz&edit=medien' ],
		[ 'done' => count( array_filter( $infra ) ) >= 5, 'label' => 'Ausstattung angehakt', 'hint' => 'Firmen filtern nach Gastro, Räumen & Co.', 'url' => $base . '?tab=platz&edit=ausstattung' ],
		[ 'done' => ! empty( $pois ), 'label' => 'Anfahrt & Parken beschrieben', 'hint' => 'nimmt Planern die häufigsten Fragen ab', 'url' => $base . '?tab=platz&edit=standort' ],
		[ 'done' => count( $types ) >= 2, 'label' => 'Mindestens 2 Event-Kategorien mit Angebot', 'hint' => 'jede Kategorie ist ein eigener Sucheinstieg', 'url' => $base . '?tab=angebote' ],
	];
}

/** Sichtbarkeit steigern — eigener Bereich, verlinkt aus der Übersicht (kein Nav-Tab). */
function fge_portal_section_sichtbarkeit( int $partner_id ): void {
	$base      = fge_portal_page_url();
	$items     = fge_portal_visibility_checklist( $partner_id );
	$done      = count( array_filter( array_column( $items, 'done' ) ) );
	$pct       = (int) round( $done / max( 1, count( $items ) ) * 100 );
	$is_public = function_exists( 'fge_partner_is_public' ) && fge_partner_is_public( $partner_id );
	$slug      = get_post_field( 'post_name', $partner_id );
	$badge     = '<a href="' . esc_url( home_url( '/golfplatz/' . $slug . '/' ) . '?utm_source=partner-badge' ) . '" target="_blank" rel="noopener">' . "\n"
		. '  <img src="' . esc_url( function_exists( 'fge_get_logo_url' ) ? fge_get_logo_url() : '' ) . '" alt="Offizieller Partner von Firmengolf" height="32" style="height:32px">' . "\n" . '</a>';
	?>
	<div class="fgpp"><div class="page-wide">
		<section class="section">
			<div class="section-head">
				<div>
					<div class="eyebrow">Mehr Anfragen bekommen</div>
					<h2>Sichtbarkeit <em>steigern</em></h2>
					<p>Je vollständiger und aktiver dein Auftritt, desto öfter wirst du gefunden, auf Firmengolf und über eure eigene Website. Alles hier dauert nur Minuten.</p>
				</div>
				<div class="actions"><a class="btn btn-ghost btn-sm" href="<?php echo esc_url( $base ); ?>">← Zur Übersicht</a></div>
			</div>

			<div class="panel" style="margin-bottom:18px;">
				<div class="panel-head"><h3>1 · Profil vervollständigen</h3><span class="todo-pill<?php echo 100 === $pct ? ' done' : ''; ?>"><?php echo (int) $pct; ?> %</span></div>
				<div class="vis-list">
					<?php foreach ( $items as $it ) : ?>
					<a class="vis-item<?php echo $it['done'] ? ' is-done' : ''; ?>" href="<?php echo esc_url( $it['url'] ); ?>">
						<span class="vis-check"><?php echo $it['done'] ? '✓' : ''; ?></span>
						<span class="vis-main"><b><?php echo esc_html( $it['label'] ); ?></b><span><?php echo esc_html( $it['hint'] ); ?></span></span>
						<?php if ( ! $it['done'] ) : ?><span class="todo-arrow">›</span><?php endif; ?>
					</a>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="panel" style="margin-bottom:18px;">
				<div class="panel-head"><h3>2 · Eure Website arbeiten lassen</h3></div>
				<p style="font-size:14.5px;color:var(--ink-600);margin:0 0 14px;line-height:1.55;">Zeigt eure Firmengolf-Events direkt auf eurer Club-Website (immer aktuell, ohne Pflege) und verlinkt uns mit dem Partner-Logo, das stärkt euer Profil bei Google und bringt Firmen direkt zu euren Angeboten.</p>
				<?php if ( $is_public ) : ?>
					<div style="display:flex;gap:10px;flex-wrap:wrap;">
						<a class="btn btn-brand btn-sm" href="<?php echo esc_url( $base . '?tab=angebote#embed' ); ?>">Event-Widget einbauen →</a>
					</div>
					<div style="margin-top:18px;">
						<div style="font-size:13px;font-weight:600;color:var(--ink-800);margin-bottom:8px;">Partner-Logo mit Link (für Footer oder „Partner"-Seite eurer Website):</div>
						<textarea id="fge-badge-snippet" readonly rows="3" style="width:100%;font:12.5px/1.5 ui-monospace,Consolas,monospace;color:var(--ink-800);background:var(--paper-200);border:1px solid var(--ink-200);border-radius:8px;padding:12px 14px;resize:none;box-sizing:border-box;" onclick="this.select()"><?php echo esc_textarea( $badge ); ?></textarea>
						<button type="button" class="btn btn-ghost btn-sm" style="margin-top:10px;" onclick="var t=document.getElementById('fge-badge-snippet');t.select();document.execCommand('copy');this.textContent='Kopiert ✓';">Logo-Snippet kopieren</button>
					</div>
				<?php else : ?>
					<div class="pe-empty" style="border:1px dashed var(--ink-200);border-radius:10px;padding:16px 18px;"><p style="margin:0;">Widget und Partner-Logo bekommst du hier, sobald dein Platz öffentlich ist (erstes Event freigegeben).</p></div>
				<?php endif; ?>
			</div>

			<div class="panel">
				<div class="panel-head"><h3>3 · Aktiv bleiben</h3></div>
				<div class="vis-list">
					<div class="vis-item is-tip"><span class="vis-check">→</span><span class="vis-main"><b>Schnell auf Terminanfragen reagieren</b><span>Plätze, die binnen 24 Stunden zusagen, gewinnen die meisten Buchungen, Firmen fragen oft mehrere Termine parallel an.</span></span></div>
					<div class="vis-item is-tip"><span class="vis-check">→</span><span class="vis-main"><b>Eure Event-Seite teilen</b><span>Link zu euren Angeboten in Newsletter, Social Media und ins Google-Unternehmensprofil („Neuigkeiten") stellen.</span></span></div>
					<div class="vis-item is-tip"><span class="vis-check">→</span><span class="vis-main"><b>Mehr Kategorien anbieten</b><span>After-Work, Platzreife, Turnier: jede Kategorie erscheint in einer eigenen Suche und auf eigenen Themen-Seiten.</span></span></div>
					<div class="vis-item is-tip"><span class="vis-check">→</span><span class="vis-main"><b>Bald: Bewertungen</b><span>Nach durchgeführten Events können Firmen euch bewerten, gute Bewertungen werden prominent angezeigt.</span></span></div>
				</div>
			</div>
		</section>
	</div></div>
	<?php
}

function fge_portal_section_uebersicht( int $partner_id ): void {
	$base = fge_portal_page_url();

	fge_portal_render_hero( $partner_id );
	fge_portal_render_stats_row( $partner_id );
	fge_portal_render_todo_row( $partner_id );
	?>

	<div class="fgpp"><div class="page-wide">
		<section class="section">
			<div class="section-head page-head-row">
				<div>
					<div class="eyebrow">Pro Eventformat ein Angebot</div>
					<h2>Deine <em>Event-Angebote</em></h2>
					<p>Jede Kategorie bekommt eigene Konditionen und eigene Beschreibung.</p>
				</div>
				<a href="<?php echo esc_url( $base . '?tab=angebote&portal_action=new' ); ?>" class="btn btn-brand">+ Neues Angebot</a>
			</div>
		</section>
	</div></div>
	<?php fge_portal_render_cat_grid( $partner_id, $base, true ); ?>

	<?php
	$vis_items = fge_portal_visibility_checklist( $partner_id );
	$vis_done  = count( array_filter( array_column( $vis_items, 'done' ) ) );
	$vis_pct   = (int) round( $vis_done / max( 1, count( $vis_items ) ) * 100 );
	?>
	<div class="fgpp"><div class="page-wide">
		<a class="vis-teaser" href="<?php echo esc_url( $base . '?tab=sichtbarkeit' ); ?>">
			<div class="vis-teaser-main">
				<div class="vis-teaser-title">Sichtbarkeit steigern</div>
				<p>Vollständiges Profil, Events auf eurer eigenen Website, Partner-Logo mit Link: kleine Handgriffe, die messbar mehr Anfragen bringen. Dein Profil ist zu <b><?php echo (int) $vis_pct; ?> %</b> vollständig.</p>
			</div>
			<span class="vis-teaser-bar"><span style="width:<?php echo (int) $vis_pct; ?>%"></span></span>
			<span class="btn btn-ghost btn-sm">Zum Leitfaden →</span>
		</a>
	</div></div>

	<?php fge_portal_render_anfragen_preview( $partner_id ); ?>

	<div class="fgpp"><div class="page-wide">
		<section class="section">
			<div class="section-head">
				<div>
					<div class="eyebrow">Was Firmen sagen</div>
					<h2><em>Bewertungen</em></h2>
				</div>
			</div>
			<div class="panel" style="text-align:center;color:var(--ink-500);line-height:1.6;">
				Bewertungen kommen bald, Firmen können deine Events nach der Durchführung bewerten. Das Feature wird demnächst freigeschaltet.
			</div>
		</section>
	</div></div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// HERO + STATS
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_render_hero( int $partner_id ): void {
	$partner_name   = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$city           = (string) get_post_meta( $partner_id, '_fge_city', true );
	$partner_status = (string) get_post_meta( $partner_id, '_fge_partner_status', true );
	$monogram       = fge_portal_make_monogram( $partner_name );
	$base           = fge_portal_page_url();
	$is_public      = function_exists( 'fge_partner_is_public' ) && fge_partner_is_public( $partner_id );
	$is_live        = 'aktiv' === $partner_status; // '' zählt NICHT als live (Audit C1)

	$hero_img_id  = (int) get_post_meta( $partner_id, '_fge_hero_image_attachment_id', true );
	$hero_img     = $hero_img_id > 0
		? (string) wp_get_attachment_image_url( $hero_img_id, 'full' )
		: fge_get_placeholder_image_url( 'hero-fairway-wide.jpg', $partner_id );

	$logo_id  = (int) get_post_meta( $partner_id, '_fge_logo_attachment_id', true );
	$logo_img = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';

	$post_date  = get_post_field( 'post_date', $partner_id );
	$live_since = $post_date ? date_i18n( 'F Y', (int) strtotime( $post_date ) ) : '';
	$status_label = $is_live
		? ( $live_since ? 'Live auf Firmengolf · seit ' . $live_since : 'Live auf Firmengolf' )
		: ( '' !== $partner_status ? fge_portal_format_partner_status( $partner_status ) : 'Noch nicht eingereicht' );
	?>
	<section class="fp-hero">
		<div class="fp-hero-photo" style="background-image: url('<?php echo esc_url( $hero_img ); ?>')">
			<div class="fp-hero-scrim"></div>
			<div class="fp-hero-top">
				<div class="fp-hero-status">
					<span class="dot"></span>
					<?php echo esc_html( $status_label ); ?>
				</div>
				<div class="fp-hero-actions">
					<?php if ( $is_public ) : // Button führte vorher aufs Event-Archiv statt zur Platzseite (Audit C2) ?>
					<a href="<?php echo esc_url( get_permalink( $partner_id ) ); ?>" class="fp-hero-btn" target="_blank" rel="noopener">
						<?php echo fge_icon_external(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						Öffentliches Profil
					</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $base . '?tab=platz' ); ?>" class="fp-hero-btn solid">
						<?php echo fge_icon_edit_pencil(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						Profil bearbeiten
					</a>
				</div>
			</div>
			<div class="fp-hero-body">
				<?php $next = fge_portal_next_booking( $partner_id ); ?>
				<?php if ( $next ) : ?>
				<a class="fp-hero-next" href="<?php echo esc_url( $base . '?tab=anfragen&req=' . (int) $next['req'] ); ?>">
					<span class="fp-hero-next-lbl"><?php echo $next['booked'] ? 'Nächste Buchung' : 'Nächster fixierter Termin'; ?></span>
					<?php // "k. A." war Sweep-Altschaden (wie a7d2ef7), gemeint ist der ·-Trenner. ?>
					<span class="fp-hero-next-val"><?php echo esc_html( $next['label'] ); ?> · <?php echo esc_html( $next['company'] ); ?></span>
					<span class="fp-hero-next-cta">Zur Anfrage ›</span>
				</a>
				<?php endif; ?>
				<div class="fp-hero-id">
					<div class="fp-hero-monogram">
						<?php if ( $logo_img !== '' ) : ?>
							<img src="<?php echo esc_url( $logo_img ); ?>" alt="<?php echo esc_attr( $partner_name ); ?>">
						<?php else : ?>
							<?php echo esc_html( $monogram ); ?>
						<?php endif; ?>
					</div>
					<div class="fp-hero-text">
						<div class="fp-hero-eyebrow">Dein Platz auf Firmengolf</div>
						<h1 class="fp-hero-name"><?php echo esc_html( $partner_name ); ?></h1>
						<?php if ( $city !== '' ) : ?>
							<div class="fp-hero-meta">
								<span><?php echo fge_icon_map_pin(); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $city ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</section>
	<?php
}

function fge_portal_render_stats_row( int $partner_id ): void {
	$events = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
		'fields'      => 'ids',
	] );
	$views = 0;
	foreach ( $events as $eid ) {
		$views += (int) get_post_meta( $eid, '_fge_views_count', true );
	}

	// Anfragen: echte Monatsreihe aus post_date (letzte 6 Monate).
	$requests = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );
	$months = [];
	for ( $i = 5; $i >= 0; $i-- ) {
		$months[ date_i18n( 'Y-m', strtotime( "-{$i} months" ) ) ] = 0;
	}
	$book_months = $months;
	$year        = (int) current_time( 'Y' );
	$bookings_y  = 0;
	$revenue_y   = 0.0;
	foreach ( $requests as $r ) {
		$mk = substr( (string) $r->post_date, 0, 7 );
		if ( isset( $months[ $mk ] ) ) {
			$months[ $mk ]++;
		}
		// Buchung = angenommenes Angebot; Zeitpunkt: _fge_offer_accepted_at, sonst letzter Statuswechsel.
		if ( 'accepted' === (string) get_post_meta( $r->ID, '_fge_offer_status', true ) ) {
			$acc = (string) get_post_meta( $r->ID, '_fge_offer_accepted_at', true )
				?: (string) get_post_meta( $r->ID, '_fge_last_status_change', true );
			if ( '' !== $acc && (int) substr( $acc, 0, 4 ) === $year ) {
				$bookings_y++;
				$snap       = (array) get_post_meta( $r->ID, '_fge_offer_snapshot', true );
				$revenue_y += (float) ( $snap['price_total'] ?? 0 );
				$bmk        = substr( $acc, 0, 7 );
				if ( isset( $book_months[ $bmk ] ) ) {
					$book_months[ $bmk ]++;
				}
			}
		}
	}
	$series      = array_values( $months );
	$this_month  = (int) end( $series );
	$prev_month  = (int) $series[ count( $series ) - 2 ];
	$delta_pct   = $prev_month > 0 ? (int) round( ( $this_month - $prev_month ) / $prev_month * 100 ) : null;
	$rev_label   = $revenue_y >= 1000
		? number_format_i18n( $revenue_y / 1000, 1 ) . '<span class="kpi-unit">T€</span>'
		: '<span class="kpi-unit">€</span>' . number_format_i18n( $revenue_y, 0 );
	?>
	<div class="fgpp"><div class="page-wide"><div class="kpi-row">
		<div class="kpi">
			<div class="kpi-head"><span class="kpi-lbl">Profilaufrufe</span></div>
			<div class="kpi-val"><?php echo esc_html( number_format( $views, 0, ',', '.' ) ); ?></div>
			<div class="kpi-foot">über alle Angebote</div>
		</div>
		<div class="kpi">
			<div class="kpi-head"><span class="kpi-lbl">Anfragen / Monat</span><?php echo fge_portal_sparkline( $series ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<div class="kpi-val"><?php echo esc_html( (string) $this_month ); ?></div>
			<div class="kpi-foot">
				<?php if ( null !== $delta_pct ) : ?>
					<span class="kpi-delta <?php echo $delta_pct >= 0 ? 'up' : 'down'; ?>"><?php echo ( $delta_pct >= 0 ? '↑ +' : '↓ ' ) . esc_html( (string) $delta_pct ); ?> %</span> vs. Vormonat
				<?php else : ?>
					<?php echo esc_html( $prev_month . ' im Vormonat' ); ?>
				<?php endif; ?>
			</div>
		</div>
		<div class="kpi">
			<div class="kpi-head"><span class="kpi-lbl">Buchungen <?php echo esc_html( (string) $year ); ?></span><?php echo fge_portal_sparkline( array_values( $book_months ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<div class="kpi-val"><?php echo esc_html( (string) $bookings_y ); ?></div>
			<div class="kpi-foot">seit Jahresbeginn</div>
		</div>
		<div class="kpi">
			<div class="kpi-head"><span class="kpi-lbl">Umsatz <?php echo esc_html( (string) $year ); ?></span></div>
			<div class="kpi-val"><?php echo $rev_label; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<div class="kpi-foot"><?php echo $bookings_y > 0 ? 'aus ' . (int) $bookings_y . ' Buchung' . ( 1 === $bookings_y ? '' : 'en' ) . ' (netto laut Angebot)' : 'noch keine Buchung dieses Jahr'; ?></div>
		</div>
	</div></div></div>
	<?php
}

/** Mini-Sparkline (Inline-SVG) aus einer Zahlenreihe; leer wenn alles 0. */
function fge_portal_sparkline( array $series ): string {
	$max = max( $series );
	if ( $max <= 0 || count( $series ) < 2 ) {
		return '';
	}
	$w   = 72;
	$h   = 24;
	$n   = count( $series ) - 1;
	$pts = [];
	foreach ( $series as $i => $v ) {
		$x     = round( $i / $n * $w, 1 );
		$y     = round( $h - 3 - ( $v / $max ) * ( $h - 6 ), 1 );
		$pts[] = $x . ',' . $y;
	}
	return '<svg class="kpi-spark" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" aria-hidden="true"><polyline fill="none" stroke="#2F6E45" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="' . esc_attr( implode( ' ', $pts ) ) . '"/></svg>';
}

/** Nächste gebuchte/fixierte Buchung des Partners (Datum in der Zukunft), oder null. */
function fge_portal_next_booking( int $partner_id ): ?array {
	$reqs = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => [
			'relation' => 'AND',
			[ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ],
			[ 'key' => '_fge_final_date_index', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ],
		],
	] );
	$best = null;
	foreach ( $reqs as $rid ) {
		$idx   = (int) get_post_meta( $rid, '_fge_final_date_index', true );
		$label = (string) get_post_meta( $rid, '_fge_preferred_date_' . $idx, true );
		$ymd   = function_exists( 'fge_parse_german_date' ) ? fge_parse_german_date( $label ) : null; // Format Ymd
		if ( null === $ymd || $ymd < current_time( 'Ymd' ) ) {
			continue;
		}
		if ( null === $best || $ymd < $best['ymd'] ) {
			$best = [
				'ymd'     => $ymd,
				'label'   => $label,
				'company' => (string) get_post_meta( $rid, '_fge_company_name', true ) ?: 'Unternehmen',
				'req'     => $rid,
				'booked'  => 'accepted' === (string) get_post_meta( $rid, '_fge_offer_status', true ),
			];
		}
	}
	return $best;
}

/** „Zu erledigen"-Zeile: echte offene Aufgaben des Platzes mit Ein-Klick-Zielen. */
function fge_portal_render_todo_row( int $partner_id ): void {
	$base = fge_portal_page_url();

	// Anfragen, bei denen der Platz am Zug ist.
	$reqs = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );
	$neu = 0;
	$fix = 0;
	foreach ( $reqs as $rid ) {
		$ph = fge_portal_partner_phase( $rid );
		if ( 'du' !== $ph['who'] ) {
			continue;
		}
		if ( 2 === (int) $ph['step'] ) {
			$fix++;
		} else {
			$neu++;
		}
	}

	// Events: abgelehnt/Entwurf = überarbeiten; in Prüfung = Info; leere Kategorien = anlegen.
	$events = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );
	$rework    = 0;
	$pruefung  = 0;
	$has_type  = [];
	foreach ( $events as $ev ) {
		$st = (string) get_post_meta( $ev->ID, '_fge_event_status', true );
		$has_type[ (string) get_post_meta( $ev->ID, '_fge_event_type', true ) ] = true;
		if ( in_array( $st, [ 'abgelehnt', '' ], true ) ) {
			$rework++;
		} elseif ( in_array( $st, [ 'zur_pruefung', 'aenderung_in_pruefung' ], true ) ) {
			$pruefung++;
		}
	}
	$empty_types = 0;
	foreach ( array_keys( fge_get_event_formats()['standard'] ) as $tk ) {
		if ( empty( $has_type[ $tk ] ) && ! in_array( $tk, fge_portal_hidden_empty_types(), true ) ) {
			$empty_types++;
		}
	}

	$open = $neu + $fix + $rework;
	$ico  = static function ( string $path ): string {
		return '<span class="todo-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg></span>';
	};
	?>
	<div class="fgpp"><div class="page-wide">
		<div class="todo-head">
			<span class="todo-title">Zu erledigen</span>
			<?php if ( $open > 0 ) : ?>
				<span class="todo-pill"><?php echo (int) $open; ?> offen</span>
			<?php elseif ( 0 === $pruefung + $empty_types ) : ?>
				<span class="todo-pill done">alles erledigt ✓</span>
			<?php endif; ?>
		</div>
		<?php if ( $open + $pruefung + $empty_types > 0 ) : ?>
		<div class="todo-row">
			<?php if ( $neu > 0 ) : ?>
			<a class="todo" href="<?php echo esc_url( $base . '?tab=anfragen' ); ?>">
				<?php echo $ico( '<path d="M22 12h-5l-2 3h-6l-2-3H2"/><path d="M5 5h14l3 7v7H2v-7z"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="todo-main"><b><?php echo (int) $neu; ?> <?php echo 1 === $neu ? 'Neue Anfrage' : 'Neue Anfragen'; ?></b><span>Warten auf deine Reaktion</span></span>
				<span class="todo-arrow">›</span>
			</a>
			<?php endif; ?>
			<?php if ( $fix > 0 ) : ?>
			<a class="todo" href="<?php echo esc_url( $base . '?tab=anfragen' ); ?>">
				<?php echo $ico( '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="todo-main"><b><?php echo (int) $fix; ?> <?php echo 1 === $fix ? 'Terminfreigabe' : 'Terminfreigaben'; ?></b><span>Wunschtermine brauchen deine Freigabe</span></span>
				<span class="todo-arrow">›</span>
			</a>
			<?php endif; ?>
			<?php if ( $rework > 0 ) : ?>
			<a class="todo" href="<?php echo esc_url( $base . '?tab=angebote' ); ?>">
				<?php echo $ico( '<path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="todo-main"><b><?php echo (int) $rework; ?> <?php echo 1 === $rework ? 'Angebot überarbeiten' : 'Angebote überarbeiten'; ?></b><span>Noch nicht freigegeben, bitte anpassen</span></span>
				<span class="todo-arrow">›</span>
			</a>
			<?php endif; ?>
			<?php if ( $empty_types > 0 ) : ?>
			<a class="todo" href="<?php echo esc_url( $base . '?tab=angebote' ); ?>">
				<?php echo $ico( '<path d="M12 5v14m-7-7h14"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="todo-main"><b><?php echo (int) $empty_types; ?> <?php echo 1 === $empty_types ? 'Kategorie ohne Angebot' : 'Kategorien ohne Angebot'; ?></b><span>Mehr Angebote = mehr Anfragen</span></span>
				<span class="todo-arrow">›</span>
			</a>
			<?php endif; ?>
			<?php if ( $pruefung > 0 ) : ?>
			<div class="todo is-wait">
				<?php echo $ico( '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="todo-main"><b><?php echo (int) $pruefung; ?> in Prüfung bei Firmengolf</b><span>Du musst nichts tun, wir melden uns</span></span>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div></div>
	<?php
}


// ══════════════════════════════════════════════════════════════════════════════
// CATEGORY GRID
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Nischen-Kategorien, die NICHT als leere Kacheln beworben werden (wirkt sonst
 * nach viel Arbeit). Anlegen bleibt möglich: im Formular unter „Eventart" bzw.
 * über die „Andere"-Kachel — als Inspiration statt Pflichtprogramm.
 */
function fge_portal_hidden_empty_types(): array {
	// indoor-golf: leere Kachel nie im allgemeinen Grid — Anlegen läuft über den
	// Indoor-Reiter, den nur Partner mit Indoor-Ausstattung sehen.
	return [ 'gesundheitstag', 'networking', 'nacht_event', 'indoor-golf' ];
}

function fge_portal_render_cat_grid( int $partner_id, string $base, bool $compact = false ): void {
	$types = fge_get_event_formats()['standard'];
	?>
	<div class="fgpp"><div class="cat-grid">
		<?php
		$idx = 0;
		foreach ( $types as $type_key => $type_label ) :
			$events = get_posts( [
				'post_type'   => 'firmengolf_event',
				'post_status' => [ 'publish', 'draft' ],
				'numberposts' => -1,
				'meta_query'  => [
					'relation' => 'AND',
					[ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ],
					[ 'key' => '_fge_event_type', 'value' => $type_key ],
				],
			] );

			if ( empty( $events ) ) :
				if ( $compact || in_array( $type_key, fge_portal_hidden_empty_types(), true ) ) {
					continue; // Übersicht bzw. Nischen-Typ: keine leere Kachel, Anlegen geht über „Andere"/Formular
				}
				$new_url = esc_url( $base . '?tab=angebote&portal_action=new&preset_type=' . $type_key );
				?>
				<a href="<?php echo $new_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>" class="cat is-empty">
					<div class="empty-icon">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14m-7-7h14"/></svg>
					</div>
					<span class="cat-cat-chip"><?php echo esc_html( $type_label ); ?></span>
					<div class="cat-title">Noch kein Angebot</div>
					<div class="cat-sub">Lade ein Angebot hoch, damit Firmen dich in dieser Kategorie finden.</div>
					<span class="btn btn-brand btn-sm">+ Angebot erstellen</span>
				</a>
				<?php
			else :
				foreach ( $events as $event ) {
					fge_portal_render_cat_card( $event, $type_label, $base, $idx );
					$idx++;
				}
			endif;
		endforeach;

		if ( $compact ) : ?>
			<a href="<?php echo esc_url( $base . '?tab=angebote&portal_action=new' ); ?>" class="cat is-empty">
				<div class="empty-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14m-7-7h14"/></svg>
				</div>
				<div class="cat-title">Neues Angebot anlegen</div>
				<div class="cat-sub"><?php echo 0 === $idx ? 'Leg dein erstes Event-Angebot an, Firmen finden dich pro Kategorie.' : 'Mehr Kategorien = mehr Anfragen. Alle Kategorien findest du unter „Angebote".'; ?></div>
				<span class="btn btn-brand btn-sm">+ Angebot erstellen</span>
			</a>
		<?php endif; ?>
	</div></div>
	<?php
}

function fge_portal_render_cat_card( WP_Post $event, string $type_label, string $base, int $idx = 0 ): void {
	$event_id = $event->ID;
	$status   = (string) get_post_meta( $event_id, '_fge_event_status', true );
	$desc     = (string) get_post_meta( $event_id, '_fge_card_description', true );
	$duration = (string) get_post_meta( $event_id, '_fge_duration', true );
	$pmin     = (int) get_post_meta( $event_id, '_fge_participants_min', true );
	$pmax     = (int) get_post_meta( $event_id, '_fge_participants_max', true );
	$views    = (int) get_post_meta( $event_id, '_fge_views_count', true );
	$cover    = function_exists( 'fge_event_cover_url' ) ? fge_event_cover_url( $event_id, 'large' ) : fge_get_placeholder_image_url( 'hero-fairway-wide.jpg' );
	$edit_url = esc_url( $base . '?tab=angebote&portal_action=edit&event_id=' . $event_id );

	// Einheitliches Status-Tag (gleiche Labels/Farben wie überall sonst).
	$st_label = fge_portal_format_event_status( $status ) ?: 'Entwurf';
	$st_class = fge_portal_status_class( $status );

	$group = ( $pmin > 0 && $pmax > 0 ) ? "{$pmin} bis {$pmax}" : ( $pmax > 0 ? "bis {$pmax}" : ( $pmin > 0 ? "ab {$pmin}" : '' ) );
	$pr    = function_exists( 'fge_event_pricing' ) ? fge_event_pricing( $event_id ) : [ 'gross' => 0.0, 'unit' => '' ];

	$lc = null;
	if ( $status === 'freigegeben' || $status === 'pausiert' ) {
		$lc = [
			'label' => $status === 'freigegeben' ? 'Pausieren' : 'Reaktivieren',
			'url'   => wp_nonce_url( add_query_arg( [ 'tab' => 'angebote', 'portal_action' => ( $status === 'freigegeben' ? 'pause' : 'reactivate' ), 'event_id' => $event_id ], $base ), 'fge_portal_lifecycle_' . $event_id ),
		];
	}
	?>
	<div class="cat">
		<div class="cat-photo" style="background-image:url('<?php echo esc_url( $cover ); ?>')">
			<span class="cat-cat-chip"><?php echo esc_html( $type_label ); ?></span>
			<span class="fp-pill cat-status-pos <?php echo esc_attr( $st_class ); ?>"><span class="dot"></span><?php echo esc_html( $st_label ); ?></span>
		</div>
		<div class="cat-body">
			<div class="cat-title"><?php echo esc_html( $event->post_title ); ?></div>
			<?php if ( 'abgelehnt' === $status ) : // Sackgasse auflösen: erklären + Weg zurück (Audit C5) ?>
				<div class="cat-sub" style="color:#8C3B2F;">Dieses Angebot wurde so noch nicht freigegeben, meist fehlen nur Kleinigkeiten. Überarbeite es und reiche es neu ein, oder frag uns kurz per Mail.</div>
			<?php elseif ( $desc !== '' ) : ?><div class="cat-sub"><?php echo esc_html( wp_trim_words( $desc, 16, '…' ) ); ?></div><?php endif; ?>
			<div class="cat-stats">
				<?php if ( $duration !== '' ) : ?><span class="chip"><?php echo esc_html( $duration ); ?></span><?php endif; ?>
				<?php if ( $group !== '' ) : ?><span class="chip"><?php echo esc_html( $group ); ?> Pers.</span><?php endif; ?>
				<?php if ( $status === 'freigegeben' && $views > 0 ) : ?><span class="chip"><?php echo esc_html( number_format( $views, 0, ',', '.' ) ); ?> Aufrufe</span><?php endif; ?>
			</div>
			<div class="cat-foot">
				<div class="cat-price">
					<?php if ( $pr['gross'] > 0 ) : ?>
						<span class="from">ab</span><?php echo esc_html( number_format( $pr['gross'], 0, ',', '.' ) ); ?> €<span class="unit"><?php echo $pr['unit'] === 'pro Person' ? '/Pers.' : ''; ?></span>
					<?php else : ?>
						<span class="from">Preis</span>offen
					<?php endif; ?>
				</div>
				<div style="display:flex;gap:2px;align-items:center;">
					<?php if ( $lc ) : ?><a class="cat-edit" href="<?php echo esc_url( $lc['url'] ); ?>" onclick="return confirm('<?php echo 'Pausieren' === $lc['label'] ? 'Dieses Angebot pausieren? Es ist dann nicht mehr öffentlich sichtbar.' : 'Dieses Angebot reaktivieren? Es ist dann wieder öffentlich sichtbar.'; ?>');"><?php echo esc_html( $lc['label'] ); ?></a><?php endif; ?>
					<a class="cat-edit" href="<?php echo $edit_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>"><?php echo 'abgelehnt' === $status ? 'Überarbeiten & neu einreichen →' : 'Bearbeiten →'; ?></a>
				</div>
			</div>
		</div>
	</div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// INBOX ROW + ANFRAGEN PREVIEW
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_render_inbox_row( WP_Post $req, int $idx = 0 ): void {
	$status  = (string) get_post_meta( $req->ID, '_fge_request_status', true );
	$company = (string) get_post_meta( $req->ID, '_fge_company_name', true );
	$first   = (string) get_post_meta( $req->ID, '_fge_contact_first_name', true );
	$last    = (string) get_post_meta( $req->ID, '_fge_contact_last_name', true );
	$ev_id   = (int)    get_post_meta( $req->ID, '_fge_assigned_event_id', true );
	$ev_type = $ev_id > 0 ? (string) get_post_meta( $ev_id, '_fge_event_type', true ) : '';
	$desc    = wp_trim_words( wp_strip_all_tags( $req->post_content ?: (string) get_post_meta( $req->ID, '_fge_message', true ) ), 12, '…' );

	$name     = $company ?: trim( $first . ' ' . $last ) ?: 'Unbekannte Anfrage';
	$initials = fge_portal_name_initials( $name );
	$color    = fge_portal_avatar_color( $idx );
	$time     = fge_portal_relative_time( $req->post_date );
	$is_new   = ( time() - (int) strtotime( $req->post_date ) ) < 172800;

	// Partner-Sprache statt interner Pipeline-Status (Julius: „jeder muss wissen, was als Nächstes passiert").
	$phase      = fge_portal_partner_phase( $req->ID );
	$st_label   = $phase['pill'];
	$is_du      = 'du' === $phase['who'];
	$is_new     = $is_new && $is_du; // erledigte Anfragen nicht mehr als 'Neu' markieren (Audit D13)
	$detail_url = fge_portal_page_url() . '?tab=anfragen&req=' . $req->ID;
	?>
	<a class="fp-inbox-row" href="<?php echo esc_url( $detail_url ); ?>">
		<div class="fp-inbox-avatar <?php echo esc_attr( $color ); ?>"><?php echo esc_html( $initials ); ?></div>
		<div class="fp-inbox-body">
			<div class="fp-inbox-top">
				<span><?php echo esc_html( $name ); ?></span>
				<?php if ( $is_new ) : ?><span class="fp-new">Neu</span><?php endif; ?>
				<?php if ( $ev_type !== '' ) : ?>
					<span class="fp-inbox-dot">·</span>
					<span class="fp-inbox-etype"><?php echo esc_html( fge_portal_format_event_type( $ev_type ) ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $desc !== '' ) : ?>
				<div class="fp-inbox-sub"><?php echo esc_html( $desc ); ?></div>
			<?php endif; ?>
			<?php if ( function_exists( 'fge_rr_responders' ) ) :
				$rr_resp = fge_rr_responders( $req->ID );
				if ( ! empty( $rr_resp ) ) :
					$rr_done = 0;
					foreach ( $rr_resp as $rr_c ) {
						if ( fge_rr_contact_answered_any( $req->ID, (int) $rr_c['id'] ) ) {
							$rr_done++;
						}
					}
					?>
					<div class="fp-inbox-sub" style="margin-top:6px;color:var(--fairway-700);font-weight:600;"><?php echo (int) $rr_done . ' von ' . count( $rr_resp ) . ' haben reagiert'; ?></div>
				<?php endif; endif; ?>
		</div>
		<div class="fp-inbox-meta">
			<span><?php echo esc_html( $time ); ?></span>
			<span class="fp-pill<?php echo $is_du ? ' green' : ''; ?>">
				<?php if ( $is_du ) : ?><span class="dot"></span><?php endif; ?>
				<?php echo esc_html( $st_label ); ?>
			</span>
		</div>
	</a>
	<?php
}

function fge_portal_render_anfragen_preview( int $partner_id ): void {
	$requests = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => 4,
		'orderby'     => 'date',
		'order'       => 'DESC',
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );

	$base = fge_portal_page_url();
	?>
	<div class="fgpp"><div class="page-wide">
		<section class="section">
			<div class="section-head page-head-row">
				<div>
					<div class="eyebrow">Letzte Aktivität</div>
					<h2>Inbox &amp; <em>Anfragen</em></h2>
				</div>
				<a href="<?php echo esc_url( $base . '?tab=anfragen' ); ?>" class="btn btn-ghost">Alle ansehen →</a>
			</div>

			<?php if ( empty( $requests ) ) : ?>
				<div class="panel" style="color: var(--ink-500); font-size: 14px; padding: 28px 26px;">
					Noch keine Anfragen eingegangen.
				</div>
			<?php else : ?>
				<div class="panel">
					<div class="fp-inbox-list">
						<?php foreach ( $requests as $i => $req ) : ?>
							<?php fge_portal_render_inbox_row( $req, $i ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</section>
	</div></div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: ANGEBOTE
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_section_angebote( int $partner_id ): void {
	$portal_action = sanitize_key( $_GET['portal_action'] ?? '' );
	$event_id      = absint( $_GET['event_id'] ?? 0 );
	$preset_type   = sanitize_key( $_GET['preset_type'] ?? '' );
	$base          = fge_portal_page_url();

	if ( $portal_action === 'edit' && $event_id > 0 ) {
		if ( (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true ) !== $partner_id ) {
			echo '<p class="fg-portal-error-text">Zugriff verweigert.</p>';
			return;
		}

		[ 'errors' => $errors, 'data' => $err_data ] = fge_load_form_state( 'portal_err' );

		if ( ! empty( $errors ) ) {
			$saved = $err_data;
		} else {
			$post  = get_post( $event_id );
			$m     = static function( string $key ) use ( $event_id ): string {
				return (string) get_post_meta( $event_id, '_fge_' . $key, true );
			};
			$saved = [
				'fge_post_title'                 => $post ? $post->post_title : '',
				'fge_post_content'               => $post ? $post->post_content : '',
				'fge_event_type'                 => $m( 'event_type' ),
				'fge_card_description'           => $m( 'card_description' ),
				'fge_participants_min'            => $m( 'participants_min' ),
				'fge_participants_max'            => $m( 'participants_max' ),
				'fge_duration'                   => $m( 'duration' ),
				'fge_season'                     => $m( 'season' ),
				'fge_region'                     => $m( 'region' ),
				'fge_event_location'             => $m( 'event_location' ),
				'fge_public_price_label'         => $m( 'public_price_label' ),
				'fge_price_note'                 => $m( 'price_note' ),
				'fge_availability_contact_name'  => $m( 'availability_contact_name' ),
				'fge_availability_contact_email' => $m( 'availability_contact_email' ),
				'fge_availability_contact_phone' => $m( 'availability_contact_phone' ),
				'fge_additional_services'        => $m( 'additional_services' ),
				'fge_available_weekdays'         => get_post_meta( $event_id, '_fge_available_weekdays', true ) ?: [],
			];
			foreach ( [ 'has_golf_teacher', 'has_range_usage', 'has_rental_clubs', 'has_range_balls', 'has_putting_shortgame', 'has_meeting_room', 'has_breakfast', 'has_lunch', 'has_dinner', 'has_shuttle', 'has_branding' ] as $key ) {
				$saved[ 'fge_' . $key ] = $m( $key );
			}
		}

		fge_portal_render_event_form( $partner_id, $saved, $errors, $event_id );
		return;
	}

	if ( $portal_action === 'new' ) {
		[ 'errors' => $errors, 'data' => $saved ] = fge_load_form_state( 'portal_err' );

		if ( $preset_type !== '' && empty( $saved['fge_event_type'] ) ) {
			$saved['fge_event_type'] = $preset_type;
		}

		fge_portal_render_event_form( $partner_id, $saved, $errors, 0 );
		return;
	}

	?>
	<div style="padding-top:32px;">
		<div class="fp-section-head">
			<div>
				<div class="fp-eyebrow">Pro Eventformat ein Angebot</div>
				<h2>Deine <em>Event-Angebote</em></h2>
				<p>Jede Kategorie eigene Konditionen, eigenes Foto, eigene Beschreibung.</p>
			</div>
			<div class="fp-actions">
				<a href="<?php echo esc_url( $base . '?tab=angebote&portal_action=new' ); ?>" class="fp-btn fp-btn-brand">
					<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex:none"><path d="M12 5v14m-7-7h14"/></svg>
					Neues Angebot
				</a>
			</div>
		</div>

		<div class="fgpp"><div class="ang-info">
			<p><b>So funktioniert's:</b> Du legst dein Angebot an, Firmen aus deiner Region sehen es und fragen ein Datum bei dir an. Den Umfang stimmt ihr danach gemeinsam ab, nichts ist in Stein gemeißelt.</p>
			<p>Brauchst du Orientierung? <a href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ?: home_url( '/firmenevents/' ) ); ?>" target="_blank" rel="noopener">Schau dir die Angebote der anderen Plätze an</a>. Ansonsten sind deiner Kreativität keine Grenzen gesetzt, probieren wir aus, was bei euch am besten funktioniert. Mach deinen Golfplatz zur Event-Location für die Unternehmen deiner Region.</p>
		</div></div>

		<?php fge_portal_render_cat_grid( $partner_id, $base ); ?>

		<?php
		// Embed-Promo (2026-07-22, Julius): vom Platz-Tab hierher gezogen und auffälliger,
		// bringt Traffic von den Club-Websites. In der Musterumgebung immer mit Snippet.
		$embed_ready = ( function_exists( 'fge_partner_is_public' ) && fge_partner_is_public( $partner_id ) )
			|| ( function_exists( 'fge_is_demo_partner' ) && fge_is_demo_partner( $partner_id ) );
		?>
		<div class="fgpp"><section id="embed" style="margin-top:34px;background:linear-gradient(135deg,var(--fairway-900,#20294D),var(--fairway-800,#283A6E));border-radius:20px;padding:30px 32px;color:#FBFAF6;">
			<div style="display:flex;gap:14px;align-items:flex-start;">
				<span aria-hidden="true" style="flex:none;width:44px;height:44px;border-radius:12px;background:rgba(0,200,150,.16);display:inline-flex;align-items:center;justify-content:center;color:#00C896;">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
				</span>
				<div style="min-width:0;">
					<div style="font-size:11.5px;font-weight:600;letter-spacing:.09em;text-transform:uppercase;color:#00C896;margin-bottom:6px;">Mehr Reichweite für eure Angebote</div>
					<h2 style="margin:0 0 8px;color:#FBFAF6;">Zeigt eure Events auch auf eurer eigenen Website</h2>
					<p style="margin:0;color:rgba(251,250,246,.78);max-width:640px;">Zwei Zeilen Code für euren Webmaster und eure Firmengolf-Angebote erscheinen automatisch auf eurer Club-Website. Immer aktuell, ohne Pflege. Das bringt Besucher eurer Seite direkt zu euren Events und euch zusätzliche Anfragen.</p>
				</div>
			</div>
			<?php if ( $embed_ready && function_exists( 'fge_embed_snippet' ) ) : ?>
				<textarea id="fge-embed-snippet" readonly rows="3" style="width:100%;margin-top:18px;font:12.5px/1.5 ui-monospace,Consolas,monospace;color:#EDF3FB;background:rgba(14,19,16,.35);border:1px solid rgba(251,250,246,.22);border-radius:10px;padding:12px 14px;resize:none;box-sizing:border-box;" onclick="this.select()"><?php echo esc_textarea( fge_embed_snippet( $partner_id ) ); ?></textarea>
				<div style="display:flex;gap:12px;align-items:center;margin-top:14px;flex-wrap:wrap;">
					<button type="button" style="background:#00C896;color:#0E1310;border:0;border-radius:999px;padding:10px 20px;font-size:13.5px;font-weight:600;cursor:pointer;" onclick="var t=document.getElementById('fge-embed-snippet');t.select();document.execCommand('copy');this.textContent='Kopiert ✓';">Snippet kopieren</button>
					<a style="color:#C2D4F2;font-size:13.5px;" href="<?php echo esc_url( home_url( '/embed/platz/' . get_post_field( 'post_name', $partner_id ) . '/' ) ); ?>" target="_blank" rel="noopener">Vorschau ansehen ↗</a>
				</div>
			<?php else : ?>
				<p style="margin:16px 0 0;padding:12px 16px;border:1px dashed rgba(251,250,246,.3);border-radius:10px;color:rgba(251,250,246,.78);font-size:14px;">Sobald dein Platz öffentlich ist (mindestens ein freigegebenes Event), erscheint hier der fertige Einbau-Code für eure Website.</p>
			<?php endif; ?>
		</section></div>
	</div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: ANFRAGEN
// ══════════════════════════════════════════════════════════════════════════════

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: INDOOR-GOLF (nur Partner mit Indoor in der Ausstattung, Abschnitt 3b)
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_section_indoor( int $partner_id ): void {
	if ( ! fge_partner_has_indoor( $partner_id ) ) {
		echo '<p class="fg-portal-error-text">Für dieses Profil ist kein Indoor-Golf in der Ausstattung hinterlegt. Ergänze es im Tab „Platz" unter Ausstattung.</p>';
		return;
	}
	$base = fge_portal_page_url();
	[ 'errors' => $errors, 'data' => $err_data ] = fge_load_form_state( 'portal_err' );

	$sim = get_post_meta( $partner_id, '_fge_indoor_sim', true );
	$sim = is_array( $sim ) ? $sim : [];
	// Nach einem Validierungsfehler die frische Eingabe zeigen, nicht den alten Stand.
	$val = static function( string $flat, string $key, $default = '' ) use ( $errors, $err_data, $sim ) {
		if ( ! empty( $errors ) ) {
			return $err_data[ $flat ] ?? $default;
		}
		return $sim[ $key ] ?? $default;
	};
	$num = static function( $v ): string {
		$v = absint( $v );
		return $v > 0 ? (string) $v : '';
	};
	$err_html = static function( string $key ) use ( $errors ): string {
		return isset( $errors[ $key ] ) ? '<p class="fg-form-error" role="alert">' . esc_html( $errors[ $key ] ) . '</p>' : '';
	};
	$sel_systems  = array_map( 'strval', (array) $val( 'fge_indoor_systems', 'systems', [] ) );
	$sel_features = array_map( 'strval', (array) $val( 'fge_indoor_features', 'features', [] ) );

	$indoor_events = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'meta_query'  => [
			'relation' => 'AND',
			[ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ],
			[ 'key' => '_fge_event_type', 'value' => 'indoor-golf' ],
		],
	] );
	$new_url  = esc_url( $base . '?tab=angebote&portal_action=new&preset_type=indoor-golf' );
	$has_data = ! empty( $sim ) && absint( $sim['boxes'] ?? 0 ) > 0;
	?>
	<div class="fgpp"><div class="page-wide">
		<div class="section-head" style="margin-bottom:22px;">
			<div>
				<div class="eyebrow">Indoor-Golf</div>
				<h2>Euer Indoor-Bereich</h2>
				<p>Indoor ist euer Winter- und Ganzjahresangebot: Firmenkunden kommen unter der Woche und tagsüber, genau dann, wenn Boxen sonst frei sind. Mit vollständigen Daten können wir euch für Indoor-Events vorschlagen.</p>
			</div>
			<a href="<?php echo $new_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>" class="btn btn-brand">Indoor-Angebot anlegen</a>
		</div>

		<?php if ( ! $has_data && empty( $errors ) ) : ?>
			<div class="fg-portal-global-notice" role="status">Eure Indoor-Details fehlen noch. Einmal ausgefüllt, tauchen sie in eurem Profil auf und wir können Indoor-Anfragen passend zuordnen.</div>
		<?php endif; ?>

		<?php if ( ! empty( $indoor_events ) ) : ?>
			<h3 style="margin:8px 0 12px;">Eure Indoor-Angebote</h3>
			<div class="cat-grid" style="margin-bottom:28px;">
				<?php foreach ( $indoor_events as $iidx => $iev ) {
					fge_portal_render_cat_card( $iev, 'Indoor Golf', $base, $iidx );
				} ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $base ); ?>">
			<input type="hidden" name="fge_action" value="portal_indoor_update">
			<?php wp_nonce_field( 'fge_portal_indoor_update', 'fge_portal_nonce' ); ?>
			<div class="panel" style="padding:24px;">
				<h3 style="margin-top:0;">Simulatoren und Technik</h3>
				<div class="fg-form-row fg-form-row--2col">
					<div>
						<label class="fg-form-label" for="fge_indoor_boxes">Anzahl Simulator-Boxen *</label>
						<input class="fg-form-input" type="number" min="1" max="99" id="fge_indoor_boxes" name="fge_indoor_boxes" value="<?php echo esc_attr( $num( $val( 'fge_indoor_boxes', 'boxes' ) ) ); ?>">
						<?php echo $err_html( 'fge_indoor_boxes' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
					<div>
						<label class="fg-form-label" for="fge_indoor_max_persons">Maximal Personen im Indoor-Bereich *</label>
						<input class="fg-form-input" type="number" min="1" max="999" id="fge_indoor_max_persons" name="fge_indoor_max_persons" value="<?php echo esc_attr( $num( $val( 'fge_indoor_max_persons', 'max_persons' ) ) ); ?>">
						<?php echo $err_html( 'fge_indoor_max_persons' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
				<div class="fg-form-row">
					<label class="fg-form-label">System und Hersteller *</label>
					<div class="fp-check-grid">
						<?php foreach ( fge_catalog_indoor_systems() as $sid => $slabel ) : ?>
							<label class="fp-check"><input type="checkbox" name="fge_indoor_systems[]" value="<?php echo esc_attr( $sid ); ?>" <?php checked( in_array( (string) $sid, $sel_systems, true ) ); ?>> <?php echo esc_html( $slabel ); ?></label>
						<?php endforeach; ?>
					</div>
					<?php echo $err_html( 'fge_indoor_systems' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="fg-form-row">
					<label class="fg-form-label" for="fge_indoor_systems_other">Anderes System (falls oben angehakt)</label>
					<input class="fg-form-input" type="text" id="fge_indoor_systems_other" name="fge_indoor_systems_other" value="<?php echo esc_attr( (string) $val( 'fge_indoor_systems_other', 'systems_other' ) ); ?>" placeholder="Herstellername">
				</div>
				<div class="fg-form-row">
					<label class="fg-form-label">Event-Features der Software</label>
					<div class="fp-check-grid">
						<?php foreach ( fge_catalog_indoor_features() as $fid => $flabel ) : ?>
							<label class="fp-check"><input type="checkbox" name="fge_indoor_features[]" value="<?php echo esc_attr( $fid ); ?>" <?php checked( in_array( (string) $fid, $sel_features, true ) ); ?>> <?php echo esc_html( $flabel ); ?></label>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="fg-form-row fg-form-row--2col">
					<div>
						<label class="fg-form-label" for="fge_indoor_box_comfort">Personen pro Box, komfortabel *</label>
						<input class="fg-form-input" type="number" min="1" max="20" id="fge_indoor_box_comfort" name="fge_indoor_box_comfort" value="<?php echo esc_attr( $num( $val( 'fge_indoor_box_comfort', 'box_comfort' ) ) ); ?>">
						<?php echo $err_html( 'fge_indoor_box_comfort' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
					<div>
						<label class="fg-form-label" for="fge_indoor_box_max">Personen pro Box, maximal *</label>
						<input class="fg-form-input" type="number" min="1" max="20" id="fge_indoor_box_max" name="fge_indoor_box_max" value="<?php echo esc_attr( $num( $val( 'fge_indoor_box_max', 'box_max' ) ) ); ?>">
						<?php echo $err_html( 'fge_indoor_box_max' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>

				<h3>Betreuung und Buchung</h3>
				<div class="fg-form-row fg-form-row--2col">
					<div>
						<label class="fg-form-label" for="fge_indoor_lefthand">Können Linkshänder spielen? *</label>
						<select class="fg-form-input" id="fge_indoor_lefthand" name="fge_indoor_lefthand">
							<option value="">bitte wählen …</option>
							<?php foreach ( [ 'all' => 'Ja, in allen Boxen', 'some' => 'In einzelnen Boxen', 'no' => 'Nein' ] as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $val( 'fge_indoor_lefthand', 'lefthand' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php echo $err_html( 'fge_indoor_lefthand' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
					<div>
						<label class="fg-form-label" for="fge_indoor_rental_clubs">Leihschläger vorhanden? *</label>
						<select class="fg-form-input" id="fge_indoor_rental_clubs" name="fge_indoor_rental_clubs">
							<option value="">bitte wählen …</option>
							<option value="1" <?php selected( (string) $val( 'fge_indoor_rental_clubs', 'rental_clubs' ), '1' ); ?>>Ja</option>
							<option value="0" <?php selected( (string) $val( 'fge_indoor_rental_clubs', 'rental_clubs' ), '0' ); ?>>Nein</option>
						</select>
						<?php echo $err_html( 'fge_indoor_rental_clubs' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
				<div class="fg-form-row fg-form-row--2col">
					<div>
						<label class="fg-form-label" for="fge_indoor_support">Betreuung bei Firmenevents *</label>
						<select class="fg-form-input" id="fge_indoor_support" name="fge_indoor_support">
							<option value="">bitte wählen …</option>
							<?php foreach ( [ 'inklusive' => 'Inklusive', 'aufpreis' => 'Gegen Aufpreis', 'nein' => 'Nicht möglich' ] as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $val( 'fge_indoor_support', 'support' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php echo $err_html( 'fge_indoor_support' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
					<div>
						<label class="fg-form-label" for="fge_indoor_exclusive">Exklusivbuchung möglich? *</label>
						<select class="fg-form-input" id="fge_indoor_exclusive" name="fge_indoor_exclusive">
							<option value="">bitte wählen …</option>
							<?php foreach ( [ 'ja' => 'Ja', 'ab' => 'Ja, ab einer Mindestpersonenzahl', 'nein' => 'Nein' ] as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $val( 'fge_indoor_exclusive', 'exclusive' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php echo $err_html( 'fge_indoor_exclusive' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
				<div class="fg-form-row fg-form-row--2col">
					<div>
						<label class="fg-form-label" for="fge_indoor_exclusive_from">Exklusiv ab wie vielen Personen?</label>
						<input class="fg-form-input" type="number" min="1" max="999" id="fge_indoor_exclusive_from" name="fge_indoor_exclusive_from" value="<?php echo esc_attr( $num( $val( 'fge_indoor_exclusive_from', 'exclusive_from' ) ) ); ?>">
						<?php echo $err_html( 'fge_indoor_exclusive_from' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
					<div>
						<label class="fg-form-label" for="fge_indoor_offseason">Auch außerhalb der Golfsaison nutzbar?</label>
						<select class="fg-form-input" id="fge_indoor_offseason" name="fge_indoor_offseason">
							<option value="">bitte wählen …</option>
							<option value="1" <?php selected( (string) $val( 'fge_indoor_offseason', 'offseason' ), '1' ); ?>>Ja, ganzjährig</option>
							<option value="0" <?php selected( (string) $val( 'fge_indoor_offseason', 'offseason' ), '0' ); ?>>Nein, nur während der Saison</option>
						</select>
					</div>
				</div>
				<div class="fg-form-row">
					<label class="fg-form-label" for="fge_indoor_winter_hours">Öffnungszeiten im Winter (optional)</label>
					<input class="fg-form-input" type="text" id="fge_indoor_winter_hours" name="fge_indoor_winter_hours" value="<?php echo esc_attr( (string) $val( 'fge_indoor_winter_hours', 'winter_hours' ) ); ?>" placeholder="z. B. Montag bis Sonntag 9 bis 22 Uhr">
				</div>
				<div style="margin-top:18px;">
					<button type="submit" class="btn btn-brand">Indoor-Daten speichern</button>
				</div>
			</div>
		</form>
	</div></div>
	<?php
}

function fge_portal_section_requests( int $partner_id ): void {
	$base      = fge_portal_page_url();
	$detail_id = isset( $_GET['req'] ) ? absint( $_GET['req'] ) : 0;
	if ( $detail_id > 0 && (int) get_post_meta( $detail_id, '_fge_assigned_partner_id', true ) === $partner_id ) {
		fge_portal_render_request_detail( $detail_id, $base );
		return;
	}
	fge_portal_render_request_list( $partner_id, $base );
}

/** Initials from a name/company (max 2). */
function fge_portal_initials( string $s ): string {
	$parts = preg_split( '/\s+/', trim( $s ) );
	$ini   = '';
	foreach ( array_slice( $parts, 0, 2 ) as $p ) {
		$ini .= function_exists( 'mb_substr' ) ? mb_substr( $p, 0, 1 ) : substr( $p, 0, 1 );
	}
	return strtoupper( $ini ) ?: 'k. A.';
}

function fge_portal_render_request_list( int $partner_id, string $base ): void {
	$requests = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'orderby'     => 'date',
		'order'       => 'DESC',
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );
	$filters = [ 'alle' => 'Alle', 'neu' => 'Neu', 'bearbeitung' => 'In Abstimmung', 'bestaetigt' => 'Bestätigt', 'abgelehnt' => 'Abgelehnt', 'abgeschlossen' => 'Abgeschlossen' ];
	$active  = sanitize_key( $_GET['filter'] ?? 'alle' );
	if ( ! isset( $filters[ $active ] ) ) {
		$active = 'alle';
	}
	$rows   = [];
	$counts = array_fill_keys( array_keys( $filters ), 0 );
	foreach ( $requests as $r ) {
		[ $sid ] = fge_portal_request_status( $r->ID );
		$phase   = fge_portal_partner_phase( $r->ID );
		$rows[]  = [ 'post' => $r, 'sid' => $sid, 'slabel' => $phase['pill'], 'du' => 'du' === $phase['who'] ];
		$counts['alle']++;
		$counts[ $sid ] = ( $counts[ $sid ] ?? 0 ) + 1;
	}
	$shown = array_filter( $rows, static function ( $row ) use ( $active ) {
		return 'alle' === $active || $row['sid'] === $active;
	} );
	?>
	<div class="fgpp"><div class="page-wide">
		<section class="section">
			<div class="section-head">
				<div><div class="eyebrow">Eingegangene Anfragen</div><h2>Meine <em>Anfragen</em></h2></div>
			</div>
			<div class="req-filters" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
				<?php foreach ( $filters as $fid => $flabel ) : ?>
				<a class="btn btn-sm <?php echo $fid === $active ? 'btn-brand' : 'btn-ghost'; ?>" href="<?php echo esc_url( $base . '?tab=anfragen&filter=' . $fid ); ?>"><?php echo esc_html( $flabel ); ?> <?php echo (int) ( $counts[ $fid ] ?? 0 ); ?></a>
				<?php endforeach; ?>
			</div>
			<?php if ( empty( $shown ) ) : ?>
				<div class="panel" style="text-align:center;color:var(--ink-500);"><?php echo empty( $rows ) ? 'Noch keine Anfragen. Sobald Firmen anfragen, erscheinen sie hier.' : 'Keine Anfragen in diesem Filter.'; ?></div>
			<?php else : ?>
				<div class="req-list">
					<?php foreach ( $shown as $row ) {
						fge_portal_render_request_item( $row['post'], $row['sid'], $row['slabel'], $base, ! empty( $row['du'] ) );
					} ?>
				</div>
			<?php endif; ?>
		</section>
	</div></div>
	<?php
}

function fge_portal_render_request_item( WP_Post $r, string $sid, string $slabel, string $base, bool $is_du = false ): void {
	$id      = $r->ID;
	$company = (string) get_post_meta( $id, '_fge_company_name', true ) ?: 'Unternehmen';
	$pax     = (int) get_post_meta( $id, '_fge_expected_participants', true );
	$etype   = (string) get_post_meta( $id, '_fge_event_type', true );
	$eid     = (int) get_post_meta( $id, '_fge_assigned_event_id', true );
	$etype   = $eid ? get_the_title( $eid ) : ( $etype ?: 'Firmen-Event' );
	$msg     = (string) get_post_meta( $id, '_fge_message', true );
	$wish    = function_exists( 'fge_rr_wish_dates' ) ? fge_rr_wish_dates( $id ) : [];
	$when    = get_the_date( 'd.m.Y', $id );
	$meta    = $etype . ( $pax ? ' · ' . $pax . ' Pers.' : '' ) . ( count( $wish ) > 1 ? ' · ' . count( $wish ) . ' Wunschtermine' : '' );
	?>
	<a class="req-item<?php echo $is_du ? ' is-du' : ''; ?>" href="<?php echo esc_url( $base . '?tab=anfragen&req=' . $id ); ?>">
		<span class="av green"><?php echo esc_html( fge_portal_initials( $company ) ); ?></span>
		<span class="ri-main">
			<span class="ri-co"><?php echo esc_html( $company ); ?><?php if ( $is_du ) : ?> <span class="ri-du">Du bist dran</span><?php endif; ?></span>
			<span class="ri-meta"><?php echo esc_html( $meta ); ?></span>
			<?php if ( '' !== $msg ) : ?><span class="ri-msg"><?php echo esc_html( $msg ); ?></span><?php endif; ?>
		</span>
		<span class="ri-right">
			<span class="ri-when"><?php echo esc_html( $when ); ?></span>
			<span class="spill s-<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $slabel ); ?></span>
		</span>
	</a>
	<?php
}

function fge_portal_render_request_detail( int $req, string $base ): void {
	[ $sid, $slabel ] = fge_portal_request_status( $req );
	$company  = (string) get_post_meta( $req, '_fge_company_name', true ) ?: 'Unternehmen';
	$contact  = trim( (string) get_post_meta( $req, '_fge_contact_first_name', true ) . ' ' . (string) get_post_meta( $req, '_fge_contact_last_name', true ) );
	$role     = (string) get_post_meta( $req, '_fge_contact_role', true );
	$email    = (string) get_post_meta( $req, '_fge_contact_email', true );
	$phone    = (string) get_post_meta( $req, '_fge_contact_phone', true );
	$pax      = (int) get_post_meta( $req, '_fge_expected_participants', true );
	$budget   = (string) get_post_meta( $req, '_fge_budget_range', true );
	$eid      = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
	$etype    = $eid ? get_the_title( $eid ) : ( (string) get_post_meta( $req, '_fge_event_type', true ) ?: 'Firmen-Event' );
	$slot     = (string) get_post_meta( $req, '_fge_preferred_time', true ) ?: 'Nach Absprache';
	$msg      = (string) get_post_meta( $req, '_fge_message', true );
	$ref      = function_exists( 'fge_request_number' ) ? fge_request_number( $req ) : 'FG-' . $req;
	$wish     = function_exists( 'fge_rr_wish_dates' ) ? fge_rr_wish_dates( $req ) : [];
	$m        = function_exists( 'fge_rr_matrix' ) ? fge_rr_matrix( $req ) : [ 'dates' => [], 'responders' => [], 'all_responded' => false, 'overall' => 'offen', 'final_index' => null ];
	$resp     = $m['responders'];
	$total    = count( $resp );
	$final    = function_exists( 'fge_rr_final_index' ) ? fge_rr_final_index( $req ) : 0;
	$done_cnt = 0;
	foreach ( $resp as $c ) {
		if ( fge_rr_contact_answered_any( $req, (int) $c['id'] ) ) {
			$done_cnt++;
		}
	}
	$nonce = wp_create_nonce( 'fge_portal_request' );
	$phase = fge_portal_partner_phase( $req );
	$steps = [ 'Anfrage eingegangen', 'Verfügbarkeit klären', 'Termin bestätigen', 'Angebot & Buchung' ];
	?>
	<div class="fgpp"><div class="page-wide">
		<p style="margin:24px 0 14px;"><a class="btn btn-ghost btn-sm" href="<?php echo esc_url( $base . '?tab=anfragen' ); ?>">← Alle Anfragen</a></p>

		<div class="req-next req-next--<?php echo esc_attr( $phase['who'] ); ?>">
			<div class="req-next-main">
				<div class="req-next-who"><?php echo 'du' === $phase['who'] ? 'Jetzt bist du dran' : ( 'wir' === $phase['who'] ? 'Firmengolf kümmert sich' : 'Kein Handlungsbedarf' ); ?></div>
				<div class="req-next-title"><?php echo esc_html( $phase['title'] ); ?></div>
				<p class="req-next-text"><?php echo esc_html( $phase['text'] ); ?></p>
			</div>
			<?php if ( '' !== $phase['cta'] ) : ?>
				<a class="btn btn-brand" href="#wunschtermine"><?php echo esc_html( $phase['cta'] ); ?> ↓</a>
			<?php endif; ?>
		</div>

		<div class="req-steps" aria-label="Wo steht diese Anfrage?">
			<?php foreach ( $steps as $i => $step_label ) :
				$cls = $i < $phase['step'] ? 'done' : ( $i === $phase['step'] && 'zu' !== $phase['who'] ? 'now' : 'todo' ); ?>
				<div class="req-step <?php echo esc_attr( $cls ); ?>">
					<span class="req-step-dot"><?php echo 'done' === $cls ? '✓' : (int) ( $i + 1 ); ?></span>
					<span class="req-step-label"><?php echo esc_html( $step_label ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="req-detail">
			<div class="req-detail-head">
				<span class="req-no"><span class="req-no-hash">#<?php echo esc_html( $ref ); ?></span></span>
				<span class="spill s-<?php echo esc_attr( $sid ); ?>" style="margin-left:10px;"><?php echo esc_html( $phase['pill'] ); ?></span>
				<div class="req-detail-top" style="margin-top:14px;">
					<span class="av green"><?php echo esc_html( fge_portal_initials( $company ) ); ?></span>
					<div>
						<div class="req-detail-co"><?php echo esc_html( $company ); ?></div>
						<div class="req-detail-sub"><?php echo esc_html( trim( ( $contact ?: 'Ansprechpartner' ) . ( $role ? ' · ' . $role : '' ) ) ); ?></div>
					</div>
				</div>
				<div class="req-contact-row">
					<?php if ( '' !== $email ) : ?><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><?php endif; ?>
					<?php if ( '' !== $phone ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a><?php endif; ?>
				</div>
			</div>
			<div class="req-detail-body">
				<?php
				// Ohne Kundenbudget den Preis des angefragten Angebots zeigen (Julius: „Fixpreis wird nicht angezeigt").
				$price_label = ( $eid && function_exists( 'fge_event_price_label' ) ) ? (string) fge_event_price_label( $eid ) : '';
				?>
				<div class="req-facts">
					<div class="req-fact"><div class="l">Veranstaltungstyp</div><div class="v"><?php echo esc_html( $etype ); ?></div></div>
					<div class="req-fact"><div class="l">Teilnehmer</div><div class="v"><?php echo $pax ? esc_html( $pax . ' Personen' ) : 'k. A.'; ?></div></div>
					<div class="req-fact"><div class="l">Zeitfenster</div><div class="v"><?php echo esc_html( $slot ); ?></div></div>
					<?php if ( '' !== $budget ) : ?>
						<div class="req-fact"><div class="l">Budget</div><div class="v"><?php echo esc_html( $budget ); ?></div></div>
					<?php else : ?>
						<div class="req-fact"><div class="l">Preis laut Angebot</div><div class="v"><?php echo esc_html( $price_label ?: 'k. A.' ); ?></div></div>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $msg ) : ?>
					<div class="req-section-label">Nachricht</div>
					<div class="req-msg-block">„<?php echo esc_html( $msg ); ?>"</div>
				<?php endif; ?>

				<?php
				$wish_groups = function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $req ) : [ 'platz' => [], 'firmengolf' => [] ];
				if ( ! empty( $wish_groups['platz'] ) || ! empty( $wish_groups['firmengolf'] ) ) : ?>
					<div class="req-section-label">Gewünschte Leistungen</div>
					<?php if ( ! empty( $wish_groups['platz'] ) ) : ?>
						<div class="req-wish-sub">Bei euch am Platz</div>
						<div class="req-wish-chips">
							<?php foreach ( $wish_groups['platz'] as $w ) : ?><span class="req-wish-chip"><?php echo esc_html( $w ); ?></span><?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $wish_groups['firmengolf'] ) ) : ?>
						<div class="req-wish-sub">Über Firmengolf <span class="req-wish-note">organisieren wir</span></div>
						<div class="req-wish-chips">
							<?php foreach ( $wish_groups['firmengolf'] as $w ) : ?><span class="req-wish-chip is-fg"><?php echo esc_html( $w ); ?></span><?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( ! empty( $wish ) && $total > 0 ) : ?>
					<div class="coord-head" id="wunschtermine">
						<div class="req-section-label" style="margin:0;">Wunschtermine</div>
						<div class="coord-prog"><b><?php echo (int) $done_cnt; ?></b> von <?php echo (int) $total; ?> haben reagiert</div>
					</div>
					<div class="coord-bar"><div class="coord-bar-fill" style="width:<?php echo $total ? esc_attr( round( $done_cnt / $total * 100 ) ) : 0; ?>%;"></div></div>

					<div class="wishdates">
						<?php foreach ( $wish as $idx => $label ) :
							$d       = $m['dates'][ $idx ] ?? [ 'confirmed' => 0, 'declined' => 0, 'responders' => [] ];
							$avail   = (int) $d['confirmed'];
							$is_final = ( (int) $final === (int) $idx );
							$cls     = $avail === $total ? 'ok' : ( 0 === $avail ? 'no' : 'mixed' ); ?>
						<div class="wishdate<?php echo $is_final ? ' final' : ''; ?>">
							<div class="wishdate-top">
								<div>
									<div class="wishdate-date"><?php echo $is_final ? '✓ ' : ''; ?><?php echo esc_html( $label ); ?></div>
								</div>
								<div class="wishdate-avail <?php echo esc_attr( $cls ); ?>"><?php echo (int) $avail; ?>/<?php echo (int) $total; ?> verfügbar</div>
							</div>
							<div class="vote-row">
								<?php foreach ( $resp as $c ) :
									$st = $d['responders'][ (int) $c['id'] ]['response'] ?? 'pending'; ?>
								<span class="vote <?php echo esc_attr( $st ); ?>"><span class="va green"><?php echo esc_html( fge_portal_initials( $c['name'] ?: $c['email'] ) ); ?></span><?php echo $st === 'confirmed' ? '✓' : ( $st === 'declined' ? '✕' : '·' ); ?></span>
								<?php endforeach; ?>
							</div>
							<?php if ( ! $final && $m['all_responded'] && $avail > 0 ) : ?>
							<div class="wishdate-you">
								<form method="post" action="<?php echo esc_url( $base ); ?>" style="margin:0;">
									<input type="hidden" name="fge_portal_nonce" value="<?php echo esc_attr( $nonce ); ?>">
									<input type="hidden" name="portal_action" value="confirm_date">
									<input type="hidden" name="req_id" value="<?php echo (int) $req; ?>">
									<input type="hidden" name="date_index" value="<?php echo (int) $idx; ?>">
									<button type="submit" class="minibtn confirm">✓ Diesen Termin bestätigen</button>
								</form>
							</div>
							<?php elseif ( $is_final ) : ?>
							<div class="wishdate-you"><span class="wishdate-final-tag">✓ Bestätigter Termin</span></div>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
					</div>

					<?php
					// Alternatives proposed by responders.
					$alts = [];
					foreach ( fge_rr_get( $req ) as $r ) {
						$a = (string) ( $r['alt_date'] ?? '' );
						if ( '' !== $a ) {
							$alts[ (int) $r['contact_id'] ] = [ 'alt' => $a, 'note' => (string) ( $r['note'] ?? '' ), 'cid' => (int) $r['contact_id'] ];
						}
					}
					foreach ( $alts as $a ) :
						$cname = '';
						foreach ( $resp as $c ) {
							if ( (int) $c['id'] === $a['cid'] ) {
								$cname = $c['name'];
							}
						}
						?>
						<div class="alt-card">
							<div class="alt-by"><span class="va green" style="width:22px;height:22px;"><?php echo esc_html( fge_portal_initials( $cname ) ); ?></span> <?php echo esc_html( $cname ?: 'Ansprechpartner' ); ?> schlägt einen Alternativtermin vor</div>
							<div class="alt-date"><?php echo esc_html( $a['alt'] ); ?></div>
							<?php if ( '' !== $a['note'] ) : ?><div class="alt-note">„<?php echo esc_html( $a['note'] ); ?>"</div><?php endif; ?>
						</div>
					<?php endforeach; ?>

					<div class="req-section-label">Beteiligte Ansprechpartner</div>
					<div class="team-list">
						<?php foreach ( $resp as $c ) :
							$responded = fge_rr_contact_answered_any( $req, (int) $c['id'] );
							$link      = fge_termin_contact_link( $req, $c ); ?>
						<div class="team-row">
							<span class="av green"><?php echo esc_html( fge_portal_initials( $c['name'] ?: $c['email'] ) ); ?></span>
							<div>
								<div class="team-name"><?php echo esc_html( $c['name'] ?: $c['email'] ); ?></div>
								<div class="team-role"><?php echo esc_html( $c['role'] ?: 'Ansprechpartner' ); ?></div>
							</div>
							<div class="team-right">
								<span class="spill <?php echo $responded ? 's-bestaetigt' : 's-angefragt'; ?>"><?php echo $responded ? 'Hat reagiert' : 'Ausstehend'; ?></span>
								<button type="button" class="team-link" data-copy="<?php echo esc_attr( $link ); ?>">Link kopieren</button>
								<a class="team-link" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener">Öffnen</a>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				<?php elseif ( ! empty( $wish ) ) : ?>
					<div class="req-section-label" id="wunschtermine">Wunschtermine</div>
					<p style="font-size:13px;color:var(--ink-500);margin:-4px 0 12px;">Diese Anfrage gibst du selbst frei, bestätige den passenden Termin. (Du kannst im Tab <a href="<?php echo esc_url( $base . '?tab=team' ); ?>" style="color:var(--fairway-700);">Ansprechpartner</a> Personen mit „Terminabstimmung" hinterlegen, dann stimmen sie automatisch mit ab.)</p>
					<div class="wishdates">
						<?php foreach ( $wish as $idx => $label ) :
							$is_final = ( (int) $final === (int) $idx ); ?>
						<div class="wishdate<?php echo $is_final ? ' final' : ''; ?>">
							<div class="wishdate-top">
								<div><div class="wishdate-date"><?php echo $is_final ? '✓ ' : ''; ?><?php echo esc_html( $label ); ?></div></div>
								<?php if ( ! $final ) : ?>
								<form method="post" action="<?php echo esc_url( $base ); ?>" style="margin:0;">
									<input type="hidden" name="fge_portal_nonce" value="<?php echo esc_attr( $nonce ); ?>">
									<input type="hidden" name="portal_action" value="confirm_date">
									<input type="hidden" name="req_id" value="<?php echo (int) $req; ?>">
									<input type="hidden" name="date_index" value="<?php echo (int) $idx; ?>">
									<button type="submit" class="minibtn confirm">✓ Diesen Termin bestätigen</button>
								</form>
								<?php else : ?><span class="wishdate-final-tag">✓ Bestätigt</span><?php endif; ?>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div></div>
	<script>
	(function(){
		document.querySelectorAll('[data-copy]').forEach(function(b){
			b.addEventListener('click', function(){
				var t = b.getAttribute('data-copy');
				if (navigator.clipboard) navigator.clipboard.writeText(t).then(function(){ var o=b.textContent; b.textContent='Kopiert ✓'; setTimeout(function(){b.textContent=o;},1500); });
			});
		});
	})();
	</script>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: KALENDER (PLACEHOLDER)
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_section_kalender( int $partner_id ): void {
	$base     = fge_portal_page_url();
	$bookings = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'meta_query'  => [
			'relation' => 'AND',
			[ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ],
			[ 'key' => '_fge_final_date_index', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ],
		],
	] );
	?>
	<div class="fgpp"><div class="page-wide">
		<section class="section">
			<div class="section-head">
				<div><div class="eyebrow">Buchungen &amp; Termine</div><h2>Dein <em>Kalender</em></h2><p>Bestätigte Firmenevents. Lade dir einen Termin als Kalendereintrag (.ics) herunter.</p></div>
			</div>
			<?php if ( empty( $bookings ) ) : ?>
				<div class="panel" style="text-align:center;color:var(--ink-500);">Noch keine bestätigten Termine. Sobald du in einer Anfrage einen Termin bestätigst, erscheint er hier.</div>
			<?php else : ?>
				<div class="team-mgmt">
					<?php foreach ( $bookings as $b ) :
						$idx   = (int) get_post_meta( $b->ID, '_fge_final_date_index', true );
						$label = (string) get_post_meta( $b->ID, '_fge_preferred_date_' . $idx, true );
						$comp  = (string) get_post_meta( $b->ID, '_fge_company_name', true ) ?: 'Unternehmen';
						$eid   = (int) get_post_meta( $b->ID, '_fge_assigned_event_id', true );
						$etype = $eid ? get_the_title( $eid ) : (string) get_post_meta( $b->ID, '_fge_event_type', true );
						$pax   = (int) get_post_meta( $b->ID, '_fge_expected_participants', true );
						$ics_ok = function_exists( 'fge_parse_german_date' ) && null !== fge_parse_german_date( $label );
						$ics_url = wp_nonce_url( add_query_arg( [ 'tab' => 'kalender', 'fge_ics' => $b->ID ], $base ), 'fge_ics_' . $b->ID ); ?>
					<div class="tm-row">
						<span class="tm-av"><?php echo esc_html( fge_portal_initials( $comp ) ); ?></span>
						<div class="tm-main">
							<div class="tm-name"><?php echo esc_html( $label ?: 'Termin' ); ?></div>
							<div class="tm-sub"><?php echo esc_html( trim( $comp . ( $etype ? ' · ' . $etype : '' ) . ( $pax ? ' · ' . $pax . ' Pers.' : '' ) ) ); ?></div>
						</div>
						<div class="tm-actions">
							<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( $base . '?tab=anfragen&req=' . $b->ID ); ?>">Anfrage</a>
							<?php if ( $ics_ok ) : ?>
								<a class="btn btn-brand btn-sm" href="<?php echo esc_url( $ics_url ); ?>">.ics</a>
							<?php else : ?>
								<span class="btn btn-quiet btn-sm" title="Datum nicht eindeutig, kein Export" style="cursor:default;">kein .ics</span>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	</div></div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: MEIN PLATZ (umbenannt von Profil)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Ansprechpartner/Team — Platzhalter (eigene Etappe: fge_partner_contacts-Tabelle).
 */
function fge_portal_section_team( int $partner_id ): void {
	$base       = fge_portal_page_url();
	$platz_edit = esc_url( add_query_arg( [ 'tab' => 'platz', 'edit' => 'kontakt' ], $base ) );

	$owner_name  = (string) get_post_meta( $partner_id, '_fge_main_contact_name', true );
	$owner_email = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true );
	$owner_role  = (string) get_post_meta( $partner_id, '_fge_main_contact_role', true );
	$contacts    = $partner_id > 0 ? fge_contacts_get( $partner_id ) : [];
	$contacts    = array_values( array_filter( $contacts, static function ( $c ) {
		return (int) $c['user_id'] === 0;
	} ) );
	$perm_labels = fge_contact_permissions();

	$initials = static function ( string $name ): string {
		$parts = preg_split( '/\s+/', trim( $name ) );
		$ini   = '';
		foreach ( array_slice( $parts, 0, 2 ) as $p ) {
			$ini .= function_exists( 'mb_substr' ) ? mb_substr( $p, 0, 1 ) : substr( $p, 0, 1 );
		}
		return strtoupper( $ini ) ?: 'k. A.';
	};
	?>
	<div class="fgpp"><div class="page-wide">
		<section class="section">
			<div class="section-head page-head-row">
				<div>
					<div class="eyebrow">Konto</div>
					<h2>Deine <em>Ansprechpartner</em></h2>
					<p>Diese Personen kannst du bei der Terminabstimmung für ein Angebot einbinden. Sie werden bei Anfragen über die gewünschten Termine benachrichtigt und brauchen keinen eigenen Account.</p>
				</div>
				<button type="button" class="btn btn-brand" data-fgc-open="new">+ Person hinzufügen</button>
			</div>

			<div class="team-mgmt">
				<?php if ( '' !== $owner_name || '' !== $owner_email ) : ?>
				<div class="tm-row">
					<span class="tm-av"><?php echo esc_html( $initials( $owner_name ?: $owner_email ) ); ?></span>
					<div class="tm-main">
						<div class="tm-name"><?php echo esc_html( $owner_name ?: $owner_email ); ?><span class="tm-owner">Kontoinhaber</span></div>
						<div class="tm-sub"><?php echo esc_html( trim( ( $owner_role ?: 'Hauptkontakt' ) . ' · ' . $owner_email ) ); ?></div>
					</div>
					<div class="tm-actions">
						<a class="btn btn-ghost btn-sm" href="<?php echo $platz_edit; ?>">Bearbeiten</a>
					</div>
				</div>
				<?php endif; ?>

				<?php foreach ( $contacts as $c ) :
					$perm_label = $perm_labels[ $c['permission'] ] ?? $c['permission']; ?>
				<div class="tm-row">
					<span class="tm-av"><?php echo esc_html( $initials( $c['name'] ?: $c['email'] ) ); ?></span>
					<div class="tm-main">
						<div class="tm-name"><?php echo esc_html( $c['name'] ?: $c['email'] ); ?></div>
						<div class="tm-sub"><?php echo esc_html( trim( ( $c['role'] ?: 'Ansprechpartner' ) . ' · ' . $perm_label . ' · ' . $c['email'] ) ); ?></div>
					</div>
					<div class="tm-actions">
						<button type="button" class="btn btn-ghost btn-sm"
							data-fgc-open="<?php echo (int) $c['id']; ?>"
							data-name="<?php echo esc_attr( $c['name'] ); ?>"
							data-email="<?php echo esc_attr( $c['email'] ); ?>"
							data-role="<?php echo esc_attr( $c['role'] ); ?>"
							data-perm="<?php echo esc_attr( $c['permission'] ); ?>">Bearbeiten</button>
						<form method="post" action="<?php echo esc_url( $base ); ?>" class="fgc-inline" onsubmit="return confirm('Diesen Ansprechpartner entfernen?');">
							<?php wp_nonce_field( 'fge_portal_contact', 'fge_portal_nonce' ); ?>
							<input type="hidden" name="portal_action" value="contact_delete">
							<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>">
							<button type="submit" class="btn btn-quiet btn-sm">Entfernen</button>
						</form>
					</div>
				</div>
				<?php endforeach; ?>

				<?php if ( empty( $contacts ) ) : ?>
				<div class="panel" style="text-align:center;color:var(--ink-500);">Noch keine weiteren Ansprechpartner. Füge die erste Person hinzu, z. B. Gastronomie, Head Pro oder das Sekretariat.</div>
				<?php endif; ?>
			</div>

			<p class="tm-legal">Personenbezogene Daten werden ausschließlich zur Bearbeitung von Firmenanfragen verwendet und nicht an Dritte weitergegeben (Art. 6 Abs. 1 lit. b/f DSGVO).</p>
		</section>

		<?php $co = fge_company(); ?>
		<section class="section" id="rechnungsdaten">
			<div class="section-head">
				<div>
					<div class="eyebrow">Abrechnung</div>
					<h2>Unsere <em>Rechnungsdaten</em></h2>
					<p>Nach einem Event stellst du deine Leistung direkt an Firmengolf in Rechnung. Hier findest du alle Angaben dafür.</p>
				</div>
			</div>
			<div class="panel">
				<?php
				fge_portal_profile_row( 'Rechnungsempfänger', $co['legal_name'] );
				fge_portal_profile_row( 'Anschrift', $co['hq_street'] . ', ' . $co['hq_zip'] . ' ' . $co['hq_city'] );
				fge_portal_profile_row( 'USt-ID', $co['ust_id'] );
				fge_portal_profile_row( 'Rechnung per E-Mail an', $co['email_partner'] );
				?>
				<p style="font-size:13px;color:var(--ink-500);margin-top:14px;line-height:1.5;">Bitte gib auf jeder Rechnung die <strong>Anfragenummer</strong> an (z. B. FG-26-001), damit wir sie eindeutig dem Event zuordnen können.</p>
			</div>
		</section>

		<div class="fgc-scrim" id="fgc-modal" hidden>
			<form class="fgc-sheet" method="post" action="<?php echo esc_url( $base ); ?>">
				<div class="fgc-bar">
					<span class="t" id="fgc-title">Person hinzufügen</span>
					<button type="button" class="fgc-close" data-fgc-close aria-label="Schließen">×</button>
				</div>
				<div class="fgc-body">
					<?php wp_nonce_field( 'fge_portal_contact', 'fge_portal_nonce' ); ?>
					<input type="hidden" name="portal_action" value="contact_save">
					<input type="hidden" name="contact_id" id="fgc-id" value="0">
					<label class="fgc-field"><span>Name</span>
						<input class="fgc-input" name="contact_name" id="fgc-name" placeholder="Vor- und Nachname" required></label>
					<label class="fgc-field"><span>E-Mail</span>
						<input class="fgc-input" type="email" name="contact_email" id="fgc-email" placeholder="name@golfclub.de" required></label>
					<label class="fgc-field"><span>Rolle</span>
						<select class="fgc-input" name="contact_role" id="fgc-role">
							<option value="">Rolle wählen …</option>
							<?php foreach ( fge_catalog_contact_roles() as $r ) : ?>
							<option value="<?php echo esc_attr( $r ); ?>"><?php echo esc_html( $r ); ?></option>
							<?php endforeach; ?>
						</select></label>
					<label class="fgc-field"><span>Berechtigung</span>
						<select class="fgc-input" name="contact_permission" id="fgc-perm">
							<option value="">Standard nach Rolle</option>
							<option value="notify">Nur informieren</option>
							<option value="vote">Terminabstimmung</option>
						</select></label>
				</div>
				<div class="fgc-foot">
					<button type="button" class="btn btn-ghost" data-fgc-close>Abbrechen</button>
					<button type="submit" class="btn btn-brand">Speichern</button>
				</div>
			</form>
		</div>

		<script>
		(function(){
			var modal = document.getElementById('fgc-modal');
			if (!modal) return;
			var f = {
				id: document.getElementById('fgc-id'), name: document.getElementById('fgc-name'),
				email: document.getElementById('fgc-email'), role: document.getElementById('fgc-role'),
				perm: document.getElementById('fgc-perm'), title: document.getElementById('fgc-title')
			};
			function open(){ modal.hidden = false; }
			function close(){ modal.hidden = true; }
			document.querySelectorAll('[data-fgc-open]').forEach(function(b){
				b.addEventListener('click', function(){
					var id = b.getAttribute('data-fgc-open');
					if (id === 'new') {
						f.id.value = '0'; f.name.value = ''; f.email.value = '';
						f.role.value = ''; f.perm.value = ''; f.title.textContent = 'Person hinzufügen';
					} else {
						f.id.value = id;
						f.name.value = b.getAttribute('data-name') || '';
						f.email.value = b.getAttribute('data-email') || '';
						f.role.value = b.getAttribute('data-role') || '';
						f.perm.value = b.getAttribute('data-perm') || '';
						f.title.textContent = 'Person bearbeiten';
					}
					open();
					f.name.focus();
				});
			});
			modal.addEventListener('click', function(e){ if (e.target === modal || e.target.closest('[data-fgc-close]')) close(); });
			document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
		})();
		</script>
	</div></div>
	<?php
}

/**
 * Platz-Profil — neue Anzeige-View (Design rev. 2, gekapselt unter .fgpp).
 * Liest die echten Partner-Daten inkl. Katalog-Modell (golf_type, infra, cap).
 */
function fge_portal_render_platz_profile( int $partner_id ): void {
	$m    = static fn( string $k ): string => (string) get_post_meta( $partner_id, '_fge_' . $k, true );
	$base     = fge_portal_page_url();
	$edit_sec = static fn( string $s ): string => esc_url( add_query_arg( [ 'tab' => 'platz', 'edit' => $s ], $base ) );
	$edit     = $edit_sec( 'steckbrief' );

	$name       = $m( 'public_golfclub_name' ) ?: get_the_title( $partner_id );
	$city       = $m( 'city' );
	$region     = $m( 'free_region' ) ?: $m( 'federal_state' );
	$loc        = trim( $city . ( ( $region && $region !== $city ) ? ' · ' . $region : '' ) );
	$golf_label = ( $gt = $m( 'golf_type' ) ) ? ( fge_catalog_golf_types()[ $gt ] ?? $gt ) : '';
	$since      = $m( 'partner_since' );
	$rating     = (float) $m( 'rating' );
	$status     = $m( 'partner_status' );
	$desc       = $m( 'public_short_description' );
	$infra      = (array) get_post_meta( $partner_id, '_fge_infra', true );
	$cap        = (array) get_post_meta( $partner_id, '_fge_cap', true );
	$formats    = (array) get_post_meta( $partner_id, '_fge_event_formats', true );
	$gallery    = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $partner_id, '_fge_gallery_attachment_ids', true ) ) ) );
	$cover_id   = (int) get_post_meta( $partner_id, '_fge_hero_image_attachment_id', true );
	$cover      = $cover_id > 0 ? (string) wp_get_attachment_image_url( $cover_id, '2048x2048' ) : fge_get_placeholder_image_url( 'hero-fairway-wide.jpg', $partner_id );
	$mono       = function_exists( 'fge_portal_make_monogram' ) ? fge_portal_make_monogram( $name ) : strtoupper( mb_substr( $name, 0, 2 ) );

	$infra_index = [];
	foreach ( fge_catalog_infra_groups() as $group => $items ) {
		foreach ( $items as $id => $label ) {
			$infra_index[ $id ] = [ 'label' => $label, 'group' => $group ];
		}
	}

	$facts = [];
	if ( $golf_label ) { $facts[] = [ 'Platztyp', $golf_label ]; }
	if ( $loc )        { $facts[] = [ 'Standort', $loc ]; }
	if ( ! empty( $cap['min'] ) || ! empty( $cap['max'] ) ) {
		$facts[] = [ 'Gruppengröße', trim( ( $cap['min'] ?? '?' ) . ' bis ' . ( $cap['max'] ?? '?' ) . ' Personen' ) ];
	}
	if ( $formats ) { $facts[] = [ 'Veranstaltungstypen', (string) count( $formats ) ]; }
	if ( $since )   { $facts[] = [ 'Mitglied seit', $since ]; }
	?>
	<div class="fgpp">
		<div class="page-wide">

			<section class="hero">
				<div class="hero-photo" style="background-image:url('<?php echo esc_url( $cover ); ?>')">
					<div class="hero-scrim"></div>
					<div class="hero-top">
						<?php
					// Ehrlicher Sichtbarkeits-Status statt pauschal „Öffentlich sichtbar" (Audit C1).
					$vis_public = function_exists( 'fge_partner_is_public' ) && fge_partner_is_public( $partner_id );
					if ( 'aktiv' === $status ) {
						$vis_label = $vis_public ? 'Öffentlich sichtbar auf Firmengolf' : 'Freigeschaltet, öffentlich, sobald ein Event freigegeben ist';
					} elseif ( 'pausiert' === $status ) {
						$vis_label = 'Pausiert, nicht öffentlich sichtbar';
					} elseif ( 'in_pruefung' === $status ) {
						$vis_label = 'In Prüfung, noch nicht öffentlich';
					} elseif ( 'abgelehnt' === $status ) {
						$vis_label = 'Nicht freigeschaltet';
					} else {
						$vis_label = 'Noch nicht eingereicht, nicht öffentlich';
					}
					?>
					<div class="hero-status"><span class="dot"></span> <?php echo esc_html( $vis_label ); ?></div>
						<div class="hero-actions">
							<?php if ( function_exists( 'fge_partner_is_public' ) && fge_partner_is_public( $partner_id ) ) : ?>
								<a class="hero-btn" href="<?php echo esc_url( get_permalink( $partner_id ) ); ?>" target="_blank" rel="noopener">Öffentliches Profil ansehen&nbsp;↗</a>
							<?php endif; ?>
							<a class="hero-btn solid" href="<?php echo $edit; ?>">Profil bearbeiten</a>
						</div>
					</div>
					<div class="hero-body">
						<div class="hero-id">
							<?php
							$logo_id  = (int) $m( 'logo_attachment_id' );
							// 'thumbnail' (quadratischer Zuschnitt) + <img> wie im Übersichts-Hero — füllt die Kachel satt.
							$logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
							?>
							<?php if ( $logo_url !== '' ) : ?>
								<div class="hero-monogram hero-logo"><img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $name ); ?> Logo"></div>
							<?php else : ?>
								<div class="hero-monogram"><?php echo esc_html( $mono ); ?></div>
							<?php endif; ?>
							<div class="hero-text">
								<div class="hero-eyebrow">Dein Platz auf Firmengolf</div>
								<h1 class="hero-name"><?php echo esc_html( $name ); ?></h1>
								<div class="hero-meta">
									<?php if ( $loc ) : ?><span><?php echo esc_html( $loc ); ?></span><?php endif; ?>
									<?php if ( $golf_label ) : ?><span class="dot">·</span><span><?php echo esc_html( $golf_label ); ?></span><?php endif; ?>
									<?php if ( $since ) : ?><span class="dot">·</span><span>Mitglied seit <?php echo esc_html( $since ); ?></span><?php endif; ?>
								</div>
							</div>
						</div>
						<?php if ( $rating > 0 ) : ?>
							<div class="hero-cta-card">
								<div class="lbl">Bewertung</div>
								<div class="val"><?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?> ★</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="section-head">
					<div>
						<div class="eyebrow">So sehen dich Firmen</div>
						<h2>Über deinen <em>Platz</em></h2>
						<p>Beschreibung und Eckdaten erscheinen auf deinem öffentlichen Firmengolf-Profil.</p>
					</div>
					<div class="actions"><a class="btn btn-ghost btn-sm" href="<?php echo $edit; ?>">Bearbeiten</a></div>
				</div>
				<div class="about">
					<div class="about-main">
						<?php if ( $desc !== '' ) : ?>
							<?php foreach ( preg_split( '/\n\s*\n/', trim( $desc ) ) as $para ) : ?>
								<p><?php echo esc_html( trim( $para ) ); ?></p>
							<?php endforeach; ?>
						<?php else : ?>
							<p style="color:var(--ink-500);">Noch keine Beschreibung hinterlegt. <a href="<?php echo $edit; ?>">Jetzt ergänzen →</a></p>
						<?php endif; ?>
					</div>
					<?php if ( $facts ) : ?>
						<div class="facts">
							<h4>Eckdaten</h4>
							<?php foreach ( $facts as $f ) : ?>
								<div class="fact-row"><span class="lbl"><?php echo esc_html( $f[0] ); ?></span><span class="val"><?php echo esc_html( $f[1] ); ?></span></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<section class="section">
				<div class="section-head">
					<div><div class="eyebrow">Ausstattung</div><h2>Was euch <em>erwartet</em></h2></div>
					<div class="actions"><a class="btn btn-ghost btn-sm" href="<?php echo $edit_sec( 'ausstattung' ); ?>">Bearbeiten</a></div>
				</div>
				<?php if ( $infra ) : ?>
					<?php fge_render_amenities_grid( $partner_id ); ?>
					<?php $fge_extra_eq = $m( 'additional_equipment' ); ?>
					<?php if ( '' !== $fge_extra_eq ) : ?>
					<p class="fgpp-extra-equipment"><strong>Außerdem vor Ort:</strong> <?php echo esc_html( $fge_extra_eq ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<div class="panel pe-empty">
						<p>Noch keine Ausstattung angegeben. Hake einfach an, was es bei euch gibt, Firmen filtern danach.</p>
						<a class="btn btn-brand btn-sm" href="<?php echo $edit_sec( 'ausstattung' ); ?>">Ausstattung angeben</a>
					</div>
				<?php endif; ?>
			</section>

			<section class="section" id="galerie">
				<div class="section-head">
					<div><div class="eyebrow">Bildergalerie</div><h2>Fotos deines <em>Platzes</em></h2></div>
					<div class="actions"><a class="btn btn-brand btn-sm" href="<?php echo $edit_sec( 'medien' ); ?>">Fotos verwalten</a></div>
				</div>
				<?php if ( $gallery ) : ?>
				<div class="gallery-grid">
					<?php foreach ( $gallery as $gid ) :
						$gurl = (string) wp_get_attachment_image_url( $gid, 'large' );
						if ( ! $gurl ) { continue; } ?>
						<div class="gallery-item" style="background-image:url('<?php echo esc_url( $gurl ); ?>')"></div>
					<?php endforeach; ?>
				</div>
				<?php else : ?>
					<div class="panel pe-empty">
						<p>Noch keine Fotos hochgeladen. Gute Bilder sind das Erste, was Firmen sehen, Platz, Clubhaus, Terrasse.</p>
						<a class="btn btn-brand btn-sm" href="<?php echo $edit_sec( 'medien' ); ?>">Fotos hochladen</a>
					</div>
				<?php endif; ?>
			</section>

			<?php
			$plat = (float) get_post_meta( $partner_id, '_fge_latitude', true );
			$plng = (float) get_post_meta( $partner_id, '_fge_longitude', true );
			$paddr = trim( $m( 'street' ) . ' ' . $m( 'house_number' ) . ', ' . $m( 'postal_code' ) . ' ' . $m( 'city' ), ' ,' );
			$pmq  = ( $plat && $plng ) ? $plat . ',' . $plng : $paddr;
			?>
			<section class="section" id="standort">
				<div class="section-head">
					<div><div class="eyebrow">Standort</div><h2>Wo ihr uns <em>findet</em></h2></div>
					<div class="actions"><a class="btn btn-ghost btn-sm" href="<?php echo $edit_sec( 'standort' ); ?>">Bearbeiten</a></div>
				</div>
				<?php if ( $pmq === '' ) : ?>
					<div class="panel pe-empty">
						<p>Noch keine Adresse hinterlegt. Mit der Adresse zeigen wir Firmen die Karte und die Anfahrt.</p>
						<a class="btn btn-brand btn-sm" href="<?php echo $edit_sec( 'standort' ); ?>">Adresse eintragen</a>
					</div>
				<?php else : ?>
				<div class="fgpp-map">
					<iframe data-name="googlemaps" data-src="https://www.google.com/maps?q=<?php echo rawurlencode( $pmq ); ?>&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Karte: <?php echo esc_attr( $name ); ?>"></iframe>
					<p class="fgpp-map-consent" style="font-size:13px;color:var(--ink-500);margin:10px 0 0;">Karte leer? Sie lädt erst nach deiner Einwilligung in „Google Maps". <button type="button" onclick="window.klaro&amp;&amp;window.klaro.show()" style="border:0;background:none;color:var(--fairway-700);font:inherit;font-weight:600;cursor:pointer;padding:0;text-decoration:underline;">Cookie-Einstellungen öffnen</button></p>
				</div>
				<?php if ( $paddr !== '' ) : ?><p class="fgpp-map-addr"><?php echo fge_icon_map_pin(); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $paddr ); ?></p><?php endif; ?>
				<?php $apois = fge_partner_arrival_pois( $partner_id ); ?>
				<?php if ( $apois ) : ?>
				<div class="fgpp-poi-grid">
					<?php foreach ( $apois as $pl => $pv ) : ?>
						<div class="fgpp-poi"><div class="fgpp-poi-l"><?php echo esc_html( $pl ); ?></div><div class="fgpp-poi-v"><?php echo esc_html( $pv ); ?></div></div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<?php endif; ?>
			</section>

			<section class="section" id="bewertungen">
				<div class="two-col">
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Was Firmen sagen</h3></div>
						<?php if ( $m( 'review_quote' ) !== '' ) : ?>
							<div class="review">
								<div class="review-head"><span class="review-company"><?php echo esc_html( $m( 'review_author' ) ?: 'Kunde' ); ?></span></div>
								<p class="review-quote">„<?php echo esc_html( $m( 'review_quote' ) ); ?>"</p>
								<?php if ( $m( 'review_role' ) !== '' ) : ?><div class="review-foot"><span><?php echo esc_html( $m( 'review_role' ) ); ?></span></div><?php endif; ?>
							</div>
						<?php else : ?>
							<p style="font-size:14px;color:var(--ink-500);">Noch keine Bewertung hinterlegt.</p>
						<?php endif; ?>
					</div>
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Dein Hauptkontakt</h3></div>
						<div class="facts" style="background:var(--paper-200);">
							<div class="fact-row"><span class="lbl">Name</span><span class="val"><?php echo esc_html( $m( 'main_contact_name' ) ?: 'k. A.' ); ?></span></div>
							<div class="fact-row"><span class="lbl">E-Mail</span><span class="val"><?php echo esc_html( $m( 'main_contact_email' ) ?: 'k. A.' ); ?></span></div>
							<div class="fact-row"><span class="lbl">Telefon</span><span class="val"><?php echo esc_html( $m( 'main_contact_phone' ) ?: 'k. A.' ); ?></span></div>
						</div>
						<a class="btn btn-ghost btn-sm" style="margin-top:14px;" href="<?php echo $edit_sec( 'kontakt' ); ?>">Kontaktdaten bearbeiten</a>
					</div>
				</div>
			</section>

		</div>
	</div>
	<?php
}

/** Portal-Ansicht für Golflehrer: stellt die PERSON vor, nicht einen Platz (Julius, 28.08.). */
function fge_portal_render_coach_profile( int $partner_id ): void {
	$m        = static fn( string $k ): string => (string) get_post_meta( $partner_id, '_fge_' . $k, true );
	$base     = fge_portal_page_url();
	$edit_sec = static fn( string $s ): string => esc_url( add_query_arg( [ 'tab' => 'platz', 'edit' => $s ], $base ) );

	$first      = $m( 'coach_first' );
	$coach_name = trim( $first . ' ' . $m( 'coach_last' ) ) ?: get_the_title( $partner_id );
	$status     = $m( 'partner_status' );
	$cover_id   = (int) $m( 'hero_image_attachment_id' );
	$cover      = $cover_id > 0 ? (string) wp_get_attachment_image_url( $cover_id, '2048x2048' ) : fge_get_placeholder_image_url( 'hero-fairway-wide.jpg', $partner_id );
	$portrait   = $cover_id > 0 ? (string) wp_get_attachment_image_url( $cover_id, 'thumbnail' ) : '';
	$mono       = strtoupper( mb_substr( $first ?: $coach_name, 0, 1 ) . mb_substr( $m( 'coach_last' ) ?: '', 0, 1 ) );

	$vis_public = function_exists( 'fge_partner_is_public' ) && fge_partner_is_public( $partner_id );
	if ( 'aktiv' === $status ) {
		$vis_label = $vis_public ? 'Öffentlich sichtbar auf Firmengolf' : 'Freigeschaltet, öffentlich, sobald ein Event freigegeben ist';
	} elseif ( 'pausiert' === $status ) {
		$vis_label = 'Pausiert, nicht öffentlich sichtbar';
	} elseif ( 'in_pruefung' === $status ) {
		$vis_label = 'In Prüfung, noch nicht öffentlich';
	} elseif ( 'abgelehnt' === $status ) {
		$vis_label = 'Nicht freigeschaltet';
	} else {
		$vis_label = 'Noch nicht eingereicht, nicht öffentlich';
	}

	$quali_all   = fge_catalog_coach_quali();
	$quali_raw   = get_post_meta( $partner_id, '_fge_coach_quali', true );
	$quali_ids   = is_array( $quali_raw ) ? array_map( 'strval', $quali_raw ) : array_filter( [ (string) $quali_raw ] );
	$quali_names = [];
	foreach ( $quali_ids as $qid ) {
		if ( 'other' === $qid && '' !== $m( 'coach_quali_other' ) ) {
			$quali_names[] = $m( 'coach_quali_other' );
		} elseif ( isset( $quali_all[ $qid ] ) ) {
			$quali_names[] = $quali_all[ $qid ];
		}
	}
	$lang_all   = [ 'de' => 'Deutsch', 'en' => 'Englisch', 'fr' => 'Französisch', 'it' => 'Italienisch', 'es' => 'Spanisch' ];
	$lang_names = [];
	foreach ( (array) get_post_meta( $partner_id, '_fge_coach_langs', true ) as $lid ) {
		if ( isset( $lang_all[ $lid ] ) ) { $lang_names[] = $lang_all[ $lid ]; }
	}
	$venue_name = $m( 'coach_venue_name' );
	$cf_all   = fge_catalog_coach_formats();
	$cf_names = [];
	foreach ( (array) get_post_meta( $partner_id, '_fge_coach_formats', true ) as $fid ) {
		if ( isset( $cf_all[ $fid ] ) ) { $cf_names[] = $cf_all[ $fid ]; }
	}
	$years_l = [ 'u3' => 'Unter 3 Jahre', '3-5' => '3 bis 5 Jahre', '6-10' => '6 bis 10 Jahre', '10plus' => 'Über 10 Jahre' ];

	$facts = [];
	if ( $m( 'public_golfclub_name' ) ) { $facts[] = [ 'Titel', $m( 'public_golfclub_name' ) ]; }
	if ( $quali_names )                 { $facts[] = [ 'Ausbildung', implode( ', ', $quali_names ) ]; }
	if ( isset( $years_l[ $m( 'coach_years' ) ] ) ) { $facts[] = [ 'Als Golflehrer tätig', $years_l[ $m( 'coach_years' ) ] ]; }
	if ( $lang_names )                  { $facts[] = [ 'Sprachen', implode( ', ', $lang_names ) ]; }
	if ( $venue_name )                  { $facts[] = [ 'Hauptstandort', $venue_name ]; }
	?>
	<div class="fgpp">
		<div class="page-wide">

			<section class="hero">
				<div class="hero-photo" style="background-image:url('<?php echo esc_url( $cover ); ?>')">
					<div class="hero-scrim"></div>
					<div class="hero-top">
						<div class="hero-status"><span class="dot"></span> <?php echo esc_html( $vis_label ); ?></div>
						<div class="hero-actions">
							<?php if ( $vis_public ) : ?>
								<a class="hero-btn" href="<?php echo esc_url( get_permalink( $partner_id ) ); ?>" target="_blank" rel="noopener">Öffentliche Visitenkarte ansehen&nbsp;↗</a>
							<?php endif; ?>
							<a class="hero-btn solid" href="<?php echo $edit_sec( 'profil' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>">Profil bearbeiten</a>
						</div>
					</div>
					<div class="hero-body">
						<div class="hero-id">
							<?php if ( $portrait !== '' ) : ?>
								<div class="hero-monogram hero-logo"><img src="<?php echo esc_url( $portrait ); ?>" alt="Portrait <?php echo esc_attr( $coach_name ); ?>"></div>
							<?php else : ?>
								<div class="hero-monogram"><?php echo esc_html( $mono ); ?></div>
							<?php endif; ?>
							<div class="hero-text">
								<div class="hero-eyebrow">Golflehrer auf Firmengolf</div>
								<h1 class="hero-name"><?php echo esc_html( $coach_name ); ?></h1>
								<div class="hero-meta">
									<?php if ( $m( 'public_golfclub_name' ) ) : ?><span><?php echo esc_html( $m( 'public_golfclub_name' ) ); ?></span><?php endif; ?>
									<?php if ( $m( 'city' ) ) : ?><span class="dot">·</span><span><?php echo esc_html( $m( 'city' ) ); ?></span><?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="two-col">
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Über dich</h3><a class="btn btn-ghost btn-sm" href="<?php echo $edit_sec( 'profil' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>">Bearbeiten</a></div>
						<?php if ( $m( 'public_short_description' ) !== '' ) : ?>
							<p style="font-size:14.5px;line-height:1.6;color:var(--ink-700);"><?php echo esc_html( $m( 'public_short_description' ) ); ?></p>
						<?php endif; ?>
						<?php if ( $m( 'coach_about' ) !== '' ) : ?>
							<p style="font-size:14px;line-height:1.6;color:var(--ink-600);"><?php echo esc_html( $m( 'coach_about' ) ); ?></p>
						<?php endif; ?>
						<?php if ( $m( 'public_short_description' ) === '' && $m( 'coach_about' ) === '' ) : ?>
							<p style="font-size:14px;color:var(--ink-500);">Noch keine Beschreibung. Dein Kurzprofil und deine Geschichte sind das Herz deiner Visitenkarte.</p>
						<?php endif; ?>
					</div>
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Auf einen Blick</h3></div>
						<?php foreach ( $facts as $f ) : ?>
							<div class="fact-row"><span class="lbl"><?php echo esc_html( $f[0] ); ?></span><span class="val"><?php echo esc_html( $f[1] ); ?></span></div>
						<?php endforeach; ?>
						<?php if ( empty( $facts ) ) : ?>
							<p style="font-size:14px;color:var(--ink-500);">Noch keine Angaben.</p>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="two-col">
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Deine Formate</h3></div>
						<?php if ( $cf_names ) : ?>
							<p style="font-size:14px;line-height:1.9;color:var(--ink-700);"><?php echo esc_html( implode( ' · ', $cf_names ) ); ?></p>
						<?php else : ?>
							<p style="font-size:14px;color:var(--ink-500);">Noch keine Formate gewählt.</p>
						<?php endif; ?>
					</div>
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Wo du unterrichtest</h3><a class="btn btn-ghost btn-sm" href="<?php echo $edit_sec( 'standorte' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>">Bearbeiten</a></div>
						<p style="font-size:14px;color:var(--ink-700);margin:0 0 6px;"><strong><?php echo esc_html( $venue_name ?: 'Noch kein Hauptstandort' ); ?></strong></p>
						<?php $pv_more = array_filter( array_map( 'strval', (array) get_post_meta( $partner_id, '_fge_coach_more_venues', true ) ) ); ?>
						<?php if ( $pv_more ) : ?>
							<p style="font-size:13.5px;color:var(--ink-500);margin:6px 0 0;">Außerdem: <?php echo esc_html( implode( ', ', $pv_more ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="two-col">
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Fotos</h3><a class="btn btn-ghost btn-sm" href="<?php echo $edit_sec( 'medien' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>">Bearbeiten</a></div>
						<p style="font-size:14px;color:var(--ink-600);margin:0;">Dein Portraitfoto ist das erste Bild (Titelfoto), es ist der stärkste Vertrauensfaktor deiner Visitenkarte. Dazu 3 bis 8 Bilder aus deinen Kursen.</p>
					</div>
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Kontaktdaten</h3><a class="btn btn-ghost btn-sm" href="<?php echo $edit_sec( 'kontakt' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>">Bearbeiten</a></div>
						<p style="font-size:14px;color:var(--ink-700);margin:0;"><?php echo esc_html( $m( 'main_contact_name' ) ?: 'Noch kein Kontakt hinterlegt' ); ?><?php echo $m( 'main_contact_email' ) ? ' · ' . esc_html( $m( 'main_contact_email' ) ) : ''; ?></p>
					</div>
				</div>
			</section>

		</div>
	</div>
	<?php
}

function fge_portal_section_platz( int $partner_id ): void {
	$is_coach = function_exists( 'fge_partner_type' ) && 'coach' === fge_partner_type( $partner_id );
	$edit     = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '1' === $edit ) { $edit = $is_coach ? 'profil' : 'steckbrief'; } // back-compat with the old single edit form
	$sections = $is_coach
		? [ 'profil', 'standorte', 'medien', 'kontakt' ]
		: [ 'steckbrief', 'ausstattung', 'standort', 'medien', 'kontakt' ];
	if ( in_array( $edit, $sections, true ) ) {
		fge_portal_render_platz_edit_section( $partner_id, $edit );
		return;
	}
	if ( $is_coach ) {
		fge_portal_render_coach_profile( $partner_id );
		return;
	}
	fge_portal_render_platz_profile( $partner_id );
}

/** Focused per-section edit form for the portal "Platz". Saved by fge_portal_handle_profile_update(). */
function fge_portal_render_platz_edit_section( int $partner_id, string $section ): void {
	$m    = static fn( string $k ): string => (string) get_post_meta( $partner_id, '_fge_' . $k, true );
	$base = fge_portal_page_url();
	$back = esc_url( add_query_arg( [ 'tab' => 'platz' ], $base ) );
	$is_coach = function_exists( 'fge_partner_type' ) && 'coach' === fge_partner_type( $partner_id );
	$titles = $is_coach ? [
		'profil'    => 'Dein Profil',
		'standorte' => 'Wo du unterrichtest',
		'medien'    => 'Fotos',
		'kontakt'   => 'Kontaktdaten',
	] : [
		'steckbrief'  => 'Über den Platz',
		'ausstattung' => 'Ausstattung',
		'standort'    => 'Standort & Anfahrt',
		'medien'      => 'Fotos & Logo',
		'kontakt'     => 'Kontaktdaten',
	];
	$intros = [
		'profil'    => 'Name, Titel, Qualifikation und deine Geschichte, das Herz deiner öffentlichen Visitenkarte.',
		'standorte' => 'Dein Hauptstandort und dein mobiles Angebot, daraus entstehen Karte und Zuordnung deiner Events.',
	] + [
		'steckbrief'  => 'Name, Beschreibung und Eckdaten, der erste Eindruck deines Platzes für Firmen.',
		'ausstattung' => 'Hake einfach an, was es bei euch gibt. Mehr Häkchen = mehr Treffer bei Firmen.',
		'standort'    => 'Adresse und Anfahrt, damit Firmen wissen, wie sie zu euch kommen.',
		'medien'      => 'Gute Fotos verkaufen deinen Platz. Das erste Foto ist dein Titelbild.',
		'kontakt'     => 'Wen erreichen wir bei euch, und wer bekommt Terminanfragen?',
	];
	$helps = [
		'profil'    => [ 'Alles hier erscheint auf deiner öffentlichen Visitenkarte.', 'Die Qualifikation ist keine Voraussetzung, sie steht als kleine Zeile unter deinem Namen.', 'Das Kurzprofil sind 2 bis 3 Sätze; deine Geschichte erzählst du unter „Über dich und deinen Unterricht".' ],
		'standorte' => [ 'Wählst du einen Firmengolf-Partnerplatz, übernehmen wir Stadt und Karten-Pin automatisch.', 'Das mobile Angebot erscheint auf deiner Visitenkarte („Kommt auch zu euch").' ],
	] + [
		'steckbrief'  => [ 'Alles hier erscheint auf deiner öffentlichen Platzseite.', 'Beschreibung: 2 bis 3 Sätze reichen. Was macht euren Platz für Firmen besonders, Lage, Gastronomie, Atmosphäre?', 'Die Veranstaltungstypen entscheiden, für welche Anfragen Firmen dich finden.' ],
		'ausstattung' => [ 'Die Ausstattung erscheint als Icon-Liste auf deiner öffentlichen Platzseite.', 'Fehlt etwas in der Liste? Trag es unten bei „Weitere Ausstattung" ein.' ],
		'standort'    => [ 'Die Adresse setzt den Karten-Pin auf deiner Platzseite und bei deinen Events.', 'Die Anfahrts-Felder (Auto, Bahn, Parken, Shuttle) helfen Firmen bei der Planung, kurz und konkret, z. B. „100 kostenfreie Parkplätze".', 'Breiten-/Längengrad nur ändern, wenn der Pin falsch sitzt.' ],
		'medien'      => [ 'Empfehlung: mindestens 5 Fotos im Querformat, Platz, Clubhaus, Terrasse, Gastronomie.', 'Das Titelbild ist das große Bild auf deiner Platzseite und deinen Event-Karten.', 'Fotos werden sofort hochgeladen, „Speichern" bestätigt nur die Reihenfolge.' ],
		'kontakt'     => [ 'Der Hauptkontakt ist unsere erste Anlaufstelle und steht nur intern im Portal, nicht öffentlich.', 'Terminanfragen gehen an den Verfügbarkeits-Kontakt. Leer lassen = Hauptkontakt bekommt sie.', 'Mehrere Personen (Gastro, Head Pro, Sekretariat) für die Terminabstimmung verwaltest du im Tab „Ansprechpartner".' ],
	];
	$title = $titles[ $section ] ?? 'Bearbeiten';
	?>
	<div class="fgpp"><div class="page-wide">
		<div class="pe-top">
			<a href="<?php echo $back; ?>" class="btn btn-ghost btn-sm">← Zurück zum Platz</a>
			<nav class="pe-chips" aria-label="Bereiche">
				<?php foreach ( $titles as $sec_id => $sec_label ) : ?>
					<a class="pe-chip<?php echo $sec_id === $section ? ' active' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'platz', 'edit' => $sec_id ], $base ) ); ?>"><?php echo esc_html( $sec_label ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
		<div class="section-head" style="margin-bottom:22px;">
			<div>
				<div class="eyebrow">Platz bearbeiten</div>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $intros[ $section ] ?? '' ); ?></p>
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( $base ); ?>" enctype="multipart/form-data" class="fp-platz-editform">
			<input type="hidden" name="fge_action" value="portal_profile_update">
			<input type="hidden" name="fge_platz_section" value="<?php echo esc_attr( $section ); ?>">
			<?php wp_nonce_field( 'fge_portal_profile_update', 'fge_portal_nonce' ); ?>

			<div class="pe-grid">
			<div class="panel pe-main">
			<?php
			switch ( $section ) {
				case 'profil':
					$quali_raw = get_post_meta( $partner_id, '_fge_coach_quali', true );
					$sel_quali = is_array( $quali_raw ) ? array_map( 'strval', $quali_raw ) : array_filter( [ (string) $quali_raw ] );
					$sel_langs = array_map( 'strval', (array) get_post_meta( $partner_id, '_fge_coach_langs', true ) );
					?>
					<div class="fg-form-row fg-form-row--2col">
						<div>
							<label class="fg-form-label" for="fge_coach_first">Vorname</label>
							<input class="fg-form-input" type="text" id="fge_coach_first" name="fge_coach_first" value="<?php echo esc_attr( $m( 'coach_first' ) ); ?>">
						</div>
						<div>
							<label class="fg-form-label" for="fge_coach_last">Nachname</label>
							<input class="fg-form-input" type="text" id="fge_coach_last" name="fge_coach_last" value="<?php echo esc_attr( $m( 'coach_last' ) ); ?>">
						</div>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_public_golfclub_name">Öffentlicher Titel</label>
						<input class="fg-form-input" type="text" id="fge_public_golfclub_name" name="fge_public_golfclub_name" value="<?php echo esc_attr( $m( 'public_golfclub_name' ) ); ?>" placeholder="z. B. PGA Golf Professional">
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label">Ausbildung</label>
						<div class="fp-check-grid">
							<?php foreach ( fge_catalog_coach_quali() as $qid => $ql ) : ?>
								<label class="fp-check"><input type="checkbox" name="fge_coach_quali[]" value="<?php echo esc_attr( $qid ); ?>" <?php checked( in_array( (string) $qid, $sel_quali, true ) ); ?>> <?php echo esc_html( $ql ); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="fg-form-row fg-form-row--2col">
						<div>
							<label class="fg-form-label" for="fge_coach_quali_other">Sonstige Qualifikation</label>
							<input class="fg-form-input" type="text" id="fge_coach_quali_other" name="fge_coach_quali_other" value="<?php echo esc_attr( $m( 'coach_quali_other' ) ); ?>">
						</div>
						<div>
							<label class="fg-form-label" for="fge_coach_years">Wie viele Jahre arbeitest du schon als Golflehrer?</label>
							<select class="fg-form-input" id="fge_coach_years" name="fge_coach_years">
								<option value="">bitte wählen …</option>
								<?php foreach ( [ 'u3' => 'Unter 3 Jahre', '3-5' => '3 bis 5 Jahre', '6-10' => '6 bis 10 Jahre', '10plus' => 'Über 10 Jahre' ] as $yk => $yl ) : ?>
									<option value="<?php echo esc_attr( $yk ); ?>" <?php selected( $m( 'coach_years' ), $yk ); ?>><?php echo esc_html( $yl ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label">Sprachen</label>
						<div class="fp-check-grid">
							<?php foreach ( [ 'de' => 'Deutsch', 'en' => 'Englisch', 'fr' => 'Französisch', 'it' => 'Italienisch', 'es' => 'Spanisch', 'other' => 'Weitere' ] as $lid => $ll ) : ?>
								<label class="fp-check"><input type="checkbox" name="fge_coach_langs[]" value="<?php echo esc_attr( $lid ); ?>" <?php checked( in_array( $lid, $sel_langs, true ) ); ?>> <?php echo esc_html( $ll ); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_coach_langs_other">Weitere Sprachen</label>
						<input class="fg-form-input" type="text" id="fge_coach_langs_other" name="fge_coach_langs_other" value="<?php echo esc_attr( $m( 'coach_langs_other' ) ); ?>">
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_public_short_description">Kurzprofil</label>
						<textarea class="fg-form-textarea" id="fge_public_short_description" name="fge_public_short_description" rows="3" placeholder="2 bis 3 Sätze über dich"><?php echo esc_textarea( $m( 'public_short_description' ) ); ?></textarea>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_coach_about">Über dich und deinen Unterricht</label>
						<textarea class="fg-form-textarea" id="fge_coach_about" name="fge_coach_about" rows="5" placeholder="Wie unterrichtest du, was macht deine Events besonders?"><?php echo esc_textarea( $m( 'coach_about' ) ); ?></textarea>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_website_url">Website oder Social</label>
						<input class="fg-form-input" type="url" id="fge_website_url" name="fge_website_url" value="<?php echo esc_attr( $m( 'website_url' ) ); ?>" placeholder="https://...">
					</div>
					<?php
					break;

				case 'standorte':
					?>
					<p style="font-size:13.5px;color:var(--ink-600);margin:0 0 16px;line-height:1.55;">Du bist der Organisator: Größere Eventmodule stimmst du selbst mit deiner Anlage ab, die Anfragen laufen über dich. Die Adresse setzt Karten-Pin, Umkreissuche und Stadt-Zuordnung deiner Events.</p>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_coach_venue_name">Name der Anlage</label>
						<input class="fg-form-input" type="text" id="fge_coach_venue_name" name="fge_coach_venue_name" value="<?php echo esc_attr( $m( 'coach_venue_name' ) ); ?>" placeholder="z. B. GC Beispielstadt">
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_street">Straße und Hausnummer</label>
						<input class="fg-form-input" type="text" id="fge_street" name="fge_street" value="<?php echo esc_attr( trim( $m( 'street' ) . ' ' . $m( 'house_number' ) ) ); ?>">
					</div>
					<div class="fg-form-row fg-form-row--2col">
						<div>
							<label class="fg-form-label" for="fge_postal_code">PLZ</label>
							<input class="fg-form-input" type="text" id="fge_postal_code" name="fge_postal_code" value="<?php echo esc_attr( $m( 'postal_code' ) ); ?>">
						</div>
						<div>
							<label class="fg-form-label" for="fge_city">Ort</label>
							<input class="fg-form-input" type="text" id="fge_city" name="fge_city" value="<?php echo esc_attr( $m( 'city' ) ); ?>">
						</div>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label">Was bietet die Anlage für deine Kurse und größere Eventmodule?</label>
						<?php $sel_use = array_map( 'strval', (array) get_post_meta( $partner_id, '_fge_coach_venue_use', true ) ); ?>
						<div class="fp-check-grid">
							<?php foreach ( fge_catalog_coach_venue_use() as $uid => $ul ) : ?>
								<label class="fp-check"><input type="checkbox" name="fge_coach_venue_use[]" value="<?php echo esc_attr( $uid ); ?>" <?php checked( in_array( (string) $uid, $sel_use, true ) ); ?>> <?php echo esc_html( $ul ); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label">Weitere Golfplätze für deinen Unterricht?</label>
						<?php $pv_more = array_filter( array_map( 'strval', (array) get_post_meta( $partner_id, '_fge_coach_more_venues', true ) ) ); ?>
						<div id="fge-more-venues">
							<?php foreach ( $pv_more as $mv_name ) : ?>
								<input class="fg-form-input" name="fge_coach_more_venues[]" value="<?php echo esc_attr( $mv_name ); ?>" placeholder="Name des Golfplatzes" style="margin-bottom:8px;">
							<?php endforeach; ?>
						</div>
						<button type="button" class="btn btn-ghost btn-sm" id="fge-more-venues-add">+ Golfplatz hinzufügen</button>
						<script>
						(function () {
							var b = document.getElementById('fge-more-venues-add'), l = document.getElementById('fge-more-venues');
							if (!b || !l) { return; }
							b.addEventListener('click', function () {
								var i = document.createElement('input');
								i.className = 'fg-form-input'; i.name = 'fge_coach_more_venues[]';
								i.placeholder = 'Name des Golfplatzes'; i.style.marginBottom = '8px';
								l.appendChild(i); i.focus();
							});
						})();
						</script>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_coach_gastro_involve">Gastronomie der Anlage einbinden?</label>
						<select class="fg-form-input" id="fge_coach_gastro_involve" name="fge_coach_gastro_involve">
							<option value="">bitte wählen …</option>
							<?php foreach ( [ 'ja' => 'Ja, Anfragen direkt über die Gastronomie', 'nein' => 'Nein', 'offen' => 'Später klären' ] as $gk => $gl ) : ?>
								<option value="<?php echo esc_attr( $gk ); ?>" <?php selected( $m( 'coach_gastro_involve' ), $gk ); ?>><?php echo esc_html( $gl ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php
					break;

				case 'steckbrief':
					?>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_public_golfclub_name">Öffentlicher Anzeigename</label>
						<input class="fg-form-input" type="text" id="fge_public_golfclub_name" name="fge_public_golfclub_name" value="<?php echo esc_attr( $m( 'public_golfclub_name' ) ); ?>">
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_website_url">Website</label>
						<input class="fg-form-input" type="url" id="fge_website_url" name="fge_website_url" value="<?php echo esc_attr( $m( 'website_url' ) ); ?>" placeholder="https://...">
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_public_short_description">Öffentliche Kurzbeschreibung</label>
						<textarea class="fg-form-textarea" id="fge_public_short_description" name="fge_public_short_description" rows="4" placeholder="2 bis 3 Sätze für dein öffentliches Profil"><?php echo esc_textarea( $m( 'public_short_description' ) ); ?></textarea>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_golf_type">Platztyp</label>
						<select class="fg-form-input" id="fge_golf_type" name="fge_golf_type">
							<option value="">bitte wählen …</option>
							<?php $gt = $m( 'golf_type' ); foreach ( fge_catalog_golf_types() as $id => $label ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $gt, $id ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php $cap = (array) get_post_meta( $partner_id, '_fge_cap', true ); ?>
					<div class="fg-form-row fg-form-row--2col">
						<div>
							<label class="fg-form-label" for="fge_cap_min">Gruppengröße min.</label>
							<input class="fg-form-input" type="number" min="0" id="fge_cap_min" name="fge_cap[min]" value="<?php echo esc_attr( (string) ( $cap['min'] ?? '' ) ); ?>">
						</div>
						<div>
							<label class="fg-form-label" for="fge_cap_max">Gruppengröße max.</label>
							<input class="fg-form-input" type="number" min="0" id="fge_cap_max" name="fge_cap[max]" value="<?php echo esc_attr( (string) ( $cap['max'] ?? '' ) ); ?>">
						</div>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label">Veranstaltungstypen</label>
						<?php $sel = array_map( 'strval', (array) get_post_meta( $partner_id, '_fge_event_formats', true ) ); ?>
						<div class="fp-check-grid">
							<?php foreach ( fge_get_event_formats_flat( false ) as $id => $label ) : ?>
								<label class="fp-check"><input type="checkbox" name="fge_event_formats[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( (string) $id, $sel, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<?php
					break;

				case 'ausstattung':
					$sel = array_map( 'strval', (array) get_post_meta( $partner_id, '_fge_infra', true ) );
					foreach ( fge_catalog_infra_groups() as $group => $items ) : ?>
						<div class="fp-check-group">
							<div class="fp-check-group-h"><?php echo esc_html( $group ); ?></div>
							<div class="fp-check-grid">
								<?php foreach ( $items as $id => $label ) : ?>
									<label class="fp-check"><input type="checkbox" name="fge_infra[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( (string) $id, $sel, true ) ); ?>> <span class="fp-check-ico"><?php echo function_exists( 'fge_infra_icon' ) ? fge_infra_icon( (string) $id ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
					<div class="fg-form-row" style="margin-top:16px;">
						<label class="fg-form-label" for="fge_additional_equipment">Weitere Ausstattung</label>
						<textarea class="fg-form-textarea" id="fge_additional_equipment" name="fge_additional_equipment" rows="2" placeholder="z. B. E-Trolleys, Cart-Flotte, Wellness-Bereich …"><?php echo esc_textarea( $m( 'additional_equipment' ) ); ?></textarea>
					</div>
					<?php
					break;

				case 'standort':
					?>
					<div class="fg-form-row fg-form-row--2col">
						<div><label class="fg-form-label" for="fge_street">Straße</label><input class="fg-form-input" type="text" id="fge_street" name="fge_street" value="<?php echo esc_attr( $m( 'street' ) ); ?>"></div>
						<div><label class="fg-form-label" for="fge_house_number">Hausnummer</label><input class="fg-form-input" type="text" id="fge_house_number" name="fge_house_number" value="<?php echo esc_attr( $m( 'house_number' ) ); ?>"></div>
					</div>
					<div class="fg-form-row fg-form-row--2col">
						<div><label class="fg-form-label" for="fge_postal_code">PLZ</label><input class="fg-form-input" type="text" id="fge_postal_code" name="fge_postal_code" value="<?php echo esc_attr( $m( 'postal_code' ) ); ?>"></div>
						<div><label class="fg-form-label" for="fge_city">Ort</label><input class="fg-form-input" type="text" id="fge_city" name="fge_city" value="<?php echo esc_attr( $m( 'city' ) ); ?>"></div>
					</div>
					<div class="fg-form-row fg-form-row--2col">
						<div><label class="fg-form-label" for="fge_federal_state">Bundesland</label><input class="fg-form-input" type="text" id="fge_federal_state" name="fge_federal_state" value="<?php echo esc_attr( $m( 'federal_state' ) ); ?>"></div>
						<div><label class="fg-form-label" for="fge_free_region">Region</label><input class="fg-form-input" type="text" id="fge_free_region" name="fge_free_region" value="<?php echo esc_attr( $m( 'free_region' ) ); ?>" placeholder="z.B. München und Umgebung"></div>
					</div>
					<div class="fg-form-row fg-form-row--2col">
						<div><label class="fg-form-label" for="fge_latitude">Breitengrad (lat)</label><input class="fg-form-input" type="text" id="fge_latitude" name="fge_latitude" value="<?php echo esc_attr( $m( 'latitude' ) ); ?>" placeholder="48.1372"></div>
						<div><label class="fg-form-label" for="fge_longitude">Längengrad (lng)</label><input class="fg-form-input" type="text" id="fge_longitude" name="fge_longitude" value="<?php echo esc_attr( $m( 'longitude' ) ); ?>" placeholder="11.5755"></div>
					</div>
					<p class="fp-help">Die Koordinaten setzen den Pin auf der Karte (öffentliche Platzseite + Event-Detail).</p>
					<div class="fg-form-row fg-form-row--2col">
						<div><label class="fg-form-label" for="fge_poi_car">Anfahrt mit dem Auto</label><input class="fg-form-input" type="text" id="fge_poi_car" name="fge_poi_car" value="<?php echo esc_attr( $m( 'poi_car' ) ); ?>" placeholder="z. B. 15 Min ab Stadtzentrum"></div>
						<div><label class="fg-form-label" for="fge_poi_parking">Parken</label><input class="fg-form-input" type="text" id="fge_poi_parking" name="fge_poi_parking" value="<?php echo esc_attr( $m( 'poi_parking' ) ); ?>" placeholder="z. B. 100 kostenfreie Parkplätze"></div>
					</div>
					<div class="fg-form-row fg-form-row--2col">
						<div><label class="fg-form-label" for="fge_poi_train">Mit der Bahn</label><input class="fg-form-input" type="text" id="fge_poi_train" name="fge_poi_train" value="<?php echo esc_attr( $m( 'poi_train' ) ); ?>" placeholder="z. B. S2 Riem, 10 Gehminuten"></div>
						<div><label class="fg-form-label" for="fge_poi_shuttle">Shuttle-Service</label><input class="fg-form-input" type="text" id="fge_poi_shuttle" name="fge_poi_shuttle" value="<?php echo esc_attr( $m( 'poi_shuttle' ) ); ?>" placeholder="z. B. Abholung nach Absprache"></div>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_arrival_estation">Ladestation für E-Autos</label>
						<select class="fg-form-input" id="fge_arrival_estation" name="fge_arrival_estation">
							<option value="" <?php selected( $m( 'arrival_estation' ), '' ); ?>>Keine Angabe</option>
							<option value="1" <?php selected( $m( 'arrival_estation' ), '1' ); ?>>Ja</option>
							<option value="0" <?php selected( $m( 'arrival_estation' ), '0' ); ?>>Nein</option>
						</select>
					</div>
					<?php
					break;

				case 'kontakt':
					// Läuft gerade eine E-Mail-Bestätigung? Dann die noch nicht übernommene
					// (neue) Adresse im Feld zeigen und das Code-Feld einblenden.
					$mail_pending = ( isset( $_GET['mailverify'] ) && $partner_id > 0 ) ? (string) get_transient( 'fge_portalmail_' . $partner_id ) : '';
					$mail_val     = '' !== $mail_pending ? $mail_pending : $m( 'main_contact_email' );
					$mail_err     = sanitize_key( wp_unslash( $_GET['mailerr'] ?? '' ) );
					$mail_err_txt = [
						'wrong'    => 'Der Code stimmt nicht, schau nochmal in die Mail.',
						'expired'  => 'Der Code ist abgelaufen, fordere einen neuen an.',
						'toomany'  => 'Zu viele Versuche. Bitte fordere einen neuen Code an.',
						'cooldown' => 'Wir haben dir gerade erst einen Code geschickt.',
						'nomail'   => 'Der Code konnte nicht verschickt werden. Bitte prüfe die Adresse.',
					][ $mail_err ] ?? '';
					?>
					<div class="pe-subhead">Hauptkontakt</div>
					<p class="fp-help">Deine erste Anlaufstelle für Firmengolf, z. B. Clubmanagement oder Sekretariat.</p>
					<div class="fg-form-row fg-form-row--3col">
						<div><label class="fg-form-label" for="fge_main_contact_name">Name</label><input class="fg-form-input" type="text" id="fge_main_contact_name" name="fge_main_contact_name" value="<?php echo esc_attr( $m( 'main_contact_name' ) ); ?>"></div>
						<div><label class="fg-form-label" for="fge_main_contact_email">E-Mail</label><input class="fg-form-input" type="email" id="fge_main_contact_email" name="fge_main_contact_email" value="<?php echo esc_attr( $mail_val ); ?>"></div>
						<div><label class="fg-form-label" for="fge_main_contact_phone">Telefon</label><input class="fg-form-input" type="tel" id="fge_main_contact_phone" name="fge_main_contact_phone" value="<?php echo esc_attr( $m( 'main_contact_phone' ) ); ?>"></div>
					</div>
					<?php if ( '' !== $mail_pending ) : ?>
					<div class="fp-mailverify">
						<?php if ( '' !== $mail_err_txt ) : ?><div class="fp-mailverify-err"><?php echo esc_html( $mail_err_txt ); ?></div><?php endif; ?>
						<label class="fg-form-label" for="fge_mail_code">Bestätigungscode für die neue E-Mail</label>
						<input class="fg-form-input" type="text" id="fge_mail_code" name="fge_mail_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="6-stelliger Code" style="max-width:220px;">
						<p class="fp-help" style="margin-top:8px;">Wir haben einen Code an <strong><?php echo esc_html( $mail_pending ); ?></strong> geschickt, erst nach Bestätigung wird die Adresse übernommen. <button type="submit" name="fge_mail_resend" value="1" class="fp-linkbtn" formnovalidate>Code erneut senden</button></p>
					</div>
					<?php endif; ?>
					<div class="fg-form-row" style="max-width:320px;">
						<label class="fg-form-label" for="fge_main_contact_role">Rolle / Funktion</label>
						<input class="fg-form-input" type="text" id="fge_main_contact_role" name="fge_main_contact_role" value="<?php echo esc_attr( $m( 'main_contact_role' ) ); ?>" placeholder="z. B. Clubmanagerin">
					</div>

					<div class="pe-subhead" style="margin-top:26px;">Kontakt für Terminanfragen</div>
					<p class="fp-help">Diese Person fragen wir an, wenn ein Unternehmen Wunschtermine nennt. Leer lassen, wenn das der Hauptkontakt übernehmen soll.</p>
					<div class="fg-form-row fg-form-row--3col">
						<div><label class="fg-form-label" for="fge_event_contact_name">Name</label><input class="fg-form-input" type="text" id="fge_event_contact_name" name="fge_event_contact_name" value="<?php echo esc_attr( $m( 'event_contact_name' ) ); ?>"></div>
						<div><label class="fg-form-label" for="fge_event_contact_email">E-Mail</label><input class="fg-form-input" type="email" id="fge_event_contact_email" name="fge_event_contact_email" value="<?php echo esc_attr( $m( 'event_contact_email' ) ); ?>"></div>
						<div><label class="fg-form-label" for="fge_event_contact_phone">Telefon</label><input class="fg-form-input" type="tel" id="fge_event_contact_phone" name="fge_event_contact_phone" value="<?php echo esc_attr( $m( 'event_contact_phone' ) ); ?>"></div>
					</div>

					<div class="pe-hintbox">
						Mehrere Personen sollen bei Terminen mitentscheiden (z. B. Gastronomie, Head Pro)?
						<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'team' ], $base ) ); ?>">Ansprechpartner verwalten →</a>
					</div>
					<?php
					break;

				case 'medien':
					?>
					<p class="fp-help">Das erste Foto ist dein Titelbild. Ziehe per Drag &amp; Drop, um die Reihenfolge zu ändern.</p>
					<?php fge_media_gallery_render( [ 'show_logo' => true ] ); ?>
					<?php
					break;
			}
			?>
			</div>

			<aside class="pe-aside">
				<div class="pe-help">
					<h4>Gut zu wissen</h4>
					<ul>
						<?php foreach ( $helps[ $section ] ?? [] as $h ) : ?>
							<li><?php echo esc_html( $h ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</aside>
			</div>

			<div class="pe-savebar">
				<?php if ( 'medien' === $section ) : // Fotos speichern sofort per Upload, ein „Speichern" wäre ein No-op (Audit D9) ?>
					<a href="<?php echo $back; ?>" class="btn btn-brand">Fertig <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<?php else : ?>
					<a href="<?php echo $back; ?>" class="btn btn-ghost">Abbrechen</a>
					<button type="submit" class="btn btn-brand">Speichern <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				<?php endif; ?>
			</div>
		</form>
	</div></div>
	<?php
}

/** Anfahrt-POIs eines Partners als Label => Wert (nur ausgefüllte; E-Ladestation nur bei „Ja"). */
function fge_partner_arrival_pois( int $partner_id ): array {
	$m    = static fn( string $k ): string => (string) get_post_meta( $partner_id, '_fge_' . $k, true );
	$pois = array_filter( [
		'Auto'    => $m( 'poi_car' ),
		'Bahn'    => $m( 'poi_train' ),
		'Parken'  => $m( 'poi_parking' ),
		'Shuttle' => $m( 'poi_shuttle' ),
	] );
	if ( '1' === $m( 'arrival_estation' ) ) {
		$pois['E-Ladestation'] = 'Vorhanden';
	}
	return $pois;
}

function fge_portal_profile_row( string $key, string $val ): void {
	if ( $val === '' ) {
		return;
	}
	echo '<div class="fg-portal-profile-row"><span class="fg-portal-profile-key">' . esc_html( $key ) . '</span><span class="fg-portal-profile-val">' . esc_html( $val ) . '</span></div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: KENNZAHLEN
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_section_stats( int $partner_id ): void {
	$events = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );

	$published = $views = $requests = $bookings = 0;
	foreach ( $events as $ev ) {
		if ( get_post_meta( $ev->ID, '_fge_event_status', true ) === 'freigegeben' ) {
			$published++;
		}
		$views    += (int) get_post_meta( $ev->ID, '_fge_views_count', true );
		$requests += (int) get_post_meta( $ev->ID, '_fge_requests_count', true );
		$bookings += (int) get_post_meta( $ev->ID, '_fge_bookings_count', true );
	}

	$cr_vr = $views    > 0 ? round( $requests / $views * 100, 1 ) : 0;
	$cr_rb = $requests > 0 ? round( $bookings / $requests * 100, 1 ) : 0;
	?>
	<div style="padding-top:32px;">
		<div class="fp-section-head">
			<div>
				<div class="fp-eyebrow">Detaillierte Auswertung</div>
				<h2>Kennzahlen</h2>
			</div>
		</div>

		<div class="fg-portal-stats">
			<?php
			foreach ( [
				[ $published, 'Veröffentlichte Events' ],
				[ $views,     'Aufrufe gesamt' ],
				[ $requests,  'Anfragen gesamt' ],
				[ $bookings,  'Buchungen gesamt' ],
				[ $cr_vr . '%', 'Aufruf → Anfrage' ],
				[ $cr_rb . '%', 'Anfrage → Buchung' ],
			] as [ $val, $label ] ) :
			?>
				<div class="fg-portal-stat">
					<div class="fg-portal-stat-value"><?php echo esc_html( $val ); ?></div>
					<div class="fg-portal-stat-label"><?php echo esc_html( $label ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( ! empty( $events ) ) : ?>
		<h3 class="fg-portal-subsection-title" style="margin-top:40px;">Events im Detail</h3>
		<div class="fg-portal-table-wrap">
			<table class="fg-portal-table">
				<thead><tr><th>Event</th><th>Status</th><th>Aufrufe</th><th>Anfragen</th><th>Buchungen</th><th>CR Aufruf→Anfrage</th></tr></thead>
				<tbody>
					<?php foreach ( $events as $ev ) :
						$st   = (string) get_post_meta( $ev->ID, '_fge_event_status', true );
						$ev_v = (int)    get_post_meta( $ev->ID, '_fge_views_count', true );
						$ev_r = (int)    get_post_meta( $ev->ID, '_fge_requests_count', true );
						$ev_b = (int)    get_post_meta( $ev->ID, '_fge_bookings_count', true );
						$ev_cr = $ev_v > 0 ? round( $ev_r / $ev_v * 100, 1 ) . '%' : 'k. A.';
					?>
					<tr>
						<td><?php echo esc_html( $ev->post_title ); ?></td>
						<td><span class="fg-portal-status fg-portal-status--<?php echo esc_attr( fge_portal_status_class( $st ) ); ?>"><?php echo esc_html( fge_portal_format_event_status( $st ) ); ?></span></td>
						<td><?php echo esc_html( $ev_v ); ?></td>
						<td><?php echo esc_html( $ev_r ); ?></td>
						<td><?php echo esc_html( $ev_b ); ?></td>
						<td><?php echo esc_html( $ev_cr ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// EVENT FORM (new + edit)
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_render_event_form( int $partner_id, array $saved = [], array $errors = [], int $event_id = 0 ): void {
	$is_edit = $event_id > 0;
	$base    = fge_portal_page_url();

	$v = static function( string $key ) use ( $saved ): string {
		return esc_attr( $saved[ $key ] ?? '' );
	};
	$has_err = static function( string $key ) use ( $errors ): string {
		return isset( $errors[ $key ] ) ? ' fg-form-input--error' : '';
	};
	$err_html = static function( string $key ) use ( $errors ): string {
		return isset( $errors[ $key ] ) ? '<p class="fg-form-error" role="alert">' . esc_html( $errors[ $key ] ) . '</p>' : '';
	};
	$checked = static function( string $key ) use ( $saved ): bool {
		return ! empty( $saved[ $key ] );
	};

	$event_types = fge_get_event_formats()['standard'];
	// Indoor Golf nur anbieten, wenn der Partner Indoor in der Ausstattung hat
	// (oder das Event den Typ schon trägt, damit Bestand editierbar bleibt).
	if ( ! fge_partner_has_indoor( $partner_id ) && 'indoor-golf' !== (string) ( $saved['fge_event_type'] ?? '' ) ) {
		unset( $event_types['indoor-golf'] );
	}
	$weekdays       = [ 'monday' => 'Mo', 'tuesday' => 'Di', 'wednesday' => 'Mi', 'thursday' => 'Do', 'friday' => 'Fr', 'saturday' => 'Sa', 'sunday' => 'So' ];
	$saved_weekdays = (array) ( $saved['fge_available_weekdays'] ?? [] );

	$event_type       = $saved['fge_event_type'] ?? '';
	$event_type_label = $event_types[ $event_type ] ?? '';
	$event_title      = $saved['fge_post_title'] ?? '';

	$cover_id  = $is_edit ? (int) get_post_meta( $event_id, '_fge_cover_attachment_id', true ) : 0;

	$event_status = $is_edit ? (string) get_post_meta( $event_id, '_fge_event_status', true ) : 'entwurf';
	$status_label = fge_portal_format_event_status( $event_status );
	$status_class = fge_portal_status_class( $event_status );

	$leistungen_labels = [
		'has_golf_teacher'      => 'Golflehrer',
		'has_range_usage'       => 'Range Nutzung inklusive',
		'has_rental_clubs'      => 'Leihschläger inklusive',
		'has_range_balls'       => 'Rangebälle inklusive',
		'has_putting_shortgame' => 'Putting / Kurzspiel inklusive',
		'has_meeting_room'      => 'Meetingraum',
		'has_breakfast'         => 'Frühstück',
		'has_lunch'             => 'Lunch',
		'has_dinner'            => 'Abendessen',
		'has_shuttle'           => 'Shuttle möglich',
		'has_branding'          => 'Branding / individuelle Anpassung möglich',
	];
	?>
	<div class="fp-edit-shell">

		<div class="fp-edit-head">
			<div class="fp-edit-head-left">
				<span class="fp-edit-cat-chip">
					<?php echo esc_html( $event_type_label ?: ( $is_edit ? 'Event' : 'Neues Angebot' ) ); ?>
				</span>
				<h1 class="fp-edit-title">
					<?php echo esc_html( $event_title ?: ( $is_edit ? 'Event bearbeiten' : 'Neues Eventangebot' ) ); ?>
				</h1>
				<div class="fp-edit-status-row">
					<span class="fp-pill <?php echo esc_attr( $status_class ); ?>">
						<span class="dot"></span>
						<?php echo esc_html( $status_label ); ?>
					</span>
				</div>
			</div>
			<div class="fp-edit-actions">
				<a href="<?php echo esc_url( $base . '?tab=angebote' ); ?>" class="fp-btn fp-btn-ghost">
					<?php echo fge_icon_arrow_left(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					Zurück
				</a>
				<button type="submit" form="fp-event-form" class="fp-btn fp-btn-brand">
					<?php echo $is_edit ? 'Änderungen speichern' : 'Event einreichen'; ?>
				</button>
			</div>
		</div>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="fg-form-errors-banner" role="alert">
				<strong>Bitte prüf deine Eingaben.</strong> Einige Felder sind noch nicht korrekt ausgefüllt.
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $base ); ?>" enctype="multipart/form-data" novalidate id="fp-event-form">
			<input type="hidden" name="fge_action" value="<?php echo $is_edit ? 'portal_edit_event' : 'portal_new_event'; ?>">
			<?php if ( $is_edit ) : ?><input type="hidden" name="fge_event_id" value="<?php echo esc_attr( $event_id ); ?>"><?php endif; ?>
			<?php wp_nonce_field( $is_edit ? 'fge_portal_edit_event' : 'fge_portal_new_event', 'fge_portal_nonce' ); ?>

			<div class="fp-edit-grid">

				<!-- ── LEFT: form sections ── -->
				<div>

					<!-- Eventart als Kachel-Auswahl -->
					<div class="fp-form-sec">
						<h3>Eventart <span class="fg-form-required" aria-label="Pflichtfeld">*</span></h3>
						<p class="fp-help">Welches Format legst du an? Bestimmt Kategorie und Filter in der Suche.</p>
						<?php
						$cur_type   = (string) ( $saved['fge_event_type'] ?? '' );
						$type_icons = function_exists( 'fge_onboarding_icon_map' ) ? fge_onboarding_icon_map() : [];
						?>
						<div class="fp-type-grid" id="fp-type-grid">
							<?php foreach ( $event_types as $tval => $tlabel ) : ?>
							<label class="fp-svc-card fp-type-card">
								<input type="radio" name="fge_event_type" value="<?php echo esc_attr( $tval ); ?>" data-label="<?php echo esc_attr( $tlabel ); ?>" <?php checked( $cur_type, $tval ); ?>>
								<span class="fp-svc-ico" aria-hidden="true"><?php echo function_exists( 'fge_onboarding_card_icon' ) ? fge_onboarding_card_icon( $type_icons[ $tval ] ?? '' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
								<span class="fp-svc-l"><?php echo esc_html( $tlabel ); ?></span>
								<span class="fp-svc-check" aria-hidden="true">✓</span>
							</label>
							<?php endforeach; ?>
						</div>
						<?php echo $err_html( 'fge_event_type' ); // phpcs:ignore ?>
					</div>

					<!-- Eventbilder: Auswahl aus der Platz-Galerie -->
					<div class="fp-form-sec">
						<h3>Eventbilder</h3>
						<p class="fp-help">Wähle Bilder aus deiner Platz-Galerie. Das Titelbild erscheint auf der Angebotskarte; weitere Fotos auf der Detailseite.</p>
						<?php
						$rest_ids     = $is_edit ? array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $event_id, '_fge_event_gallery_ids', true ) ) ) ) : [];
						$selected_ids = $cover_id > 0 ? array_merge( [ $cover_id ], $rest_ids ) : $rest_ids;
						fge_event_picker_render( $cover_id, implode( ',', $selected_ids ) );
						?>
					</div>

					<!-- Titel & Beschreibung -->
					<div class="fp-form-sec">
						<h3>Titel & Beschreibung</h3>
						<p class="fp-help">Wir empfehlen einen Titel, der ein Gefühl verspricht, nicht nur ein Format beschreibt.</p>

						<div class="fg-form-row">
							<label class="fg-form-label" for="fge_post_title">Eventtitel <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
							<div class="fg-form-field">
								<input class="fg-form-input<?php echo $has_err( 'fge_post_title' ); // phpcs:ignore ?>" type="text" id="fge_post_title" name="fge_post_title" value="<?php echo $v( 'fge_post_title' ); ?>" maxlength="80" placeholder="z. B. Erster Schwung: Schnupperkurs für Teams">
								<?php echo $err_html( 'fge_post_title' ); // phpcs:ignore ?>
								<p class="fp-help">Maximal 60 Zeichen empfohlen. Keine GROSSBUCHSTABEN.</p>
							</div>
						</div>
						<div class="fg-form-row">
							<label class="fg-form-label" for="fge_card_description">Kurzbeschreibung</label>
							<div class="fg-form-field">
								<textarea class="fg-form-textarea" id="fge_card_description" name="fge_card_description" rows="2" maxlength="200" placeholder="Was erwartet die Gäste in einem Satz?"><?php echo esc_textarea( $saved['fge_card_description'] ?? '' ); ?></textarea>
								<p class="fp-help">Maximal 160 Zeichen empfohlen. Erscheint als Vorschau in den Suchergebnissen.</p>
							</div>
						</div>
					</div>

					<!-- Preis, Dauer & Teilnehmer -->
					<div class="fp-form-sec">
						<h3>Preis, Dauer & Teilnehmer</h3>
						<p class="fp-help">Diese Eckdaten erscheinen auf der Angebotskarte.</p>

						<?php
						// Nach Validierungsfehlern POST-Daten ($saved) bevorzugen, sonst DB-Stand (Audit C6).
						$pmode   = (string) ( $saved['fge_price_mode'] ?? '' ) ?: ( $event_id ? ( get_post_meta( $event_id, '_fge_price_mode', true ) ?: 'gesamt' ) : 'gesamt' );
						$pamount = $saved['fge_price_amount'] ?? ( $event_id ? get_post_meta( $event_id, '_fge_price_amount', true ) : '' );
						$pbasis  = (string) ( $saved['fge_price_basis'] ?? '' ) ?: ( $event_id ? ( get_post_meta( $event_id, '_fge_price_basis', true ) ?: 'person' ) : 'person' );
						if ( isset( $saved['fge_line_items'] ) ) {
							$pitems = [];
							foreach ( preg_split( '/\r?\n/', (string) $saved['fge_line_items'] ) as $pi_line ) {
								$pi_parts = explode( '|', $pi_line, 3 );
								if ( '' !== trim( $pi_parts[0] ?? '' ) ) {
									$pitems[] = [ 'label' => trim( $pi_parts[0] ), 'cost' => trim( $pi_parts[1] ?? '' ), 'basis' => 'person' === trim( $pi_parts[2] ?? '' ) ? 'person' : 'pauschal' ];
								}
							}
						} else {
							$pitems = $event_id ? (array) get_post_meta( $event_id, '_fge_line_items', true ) : [];
						}
						$markup  = defined( 'FGE_MARKUP_PERCENT' ) ? (int) FGE_MARKUP_PERCENT : 20;
						?>
						<p class="fp-help">Hinterlege deinen <strong>Netto</strong>-Preis. Die Vermittlung von Firmengolf (<?php echo (int) $markup; ?> %) kommt automatisch oben drauf, der Kundenpreis wird dabei auf glatte Beträge aufgerundet. Du bekommst immer exakt deinen Preis.</p>

						<input type="hidden" name="fge_price_mode" id="fge_price_mode" value="<?php echo esc_attr( $pmode ); ?>">
						<div class="fp-price-modes" role="tablist">
							<button type="button" class="fp-price-mode<?php echo 'gesamt' === $pmode ? ' on' : ''; ?>" data-fp-mode="gesamt">Gesamtpreis</button>
							<button type="button" class="fp-price-mode<?php echo 'einzel' === $pmode ? ' on' : ''; ?>" data-fp-mode="einzel">Einzelauflistung</button>
						</div>

						<div id="fp-price-gesamt" style="<?php echo 'gesamt' === $pmode ? '' : 'display:none;'; ?>">
							<div class="fg-form-row fg-form-row--2col">
								<div>
									<label class="fg-form-label" for="fge_price_amount">Gesamtpreis für die Veranstaltung (netto, €)</label>
									<input class="fg-form-input" type="text" inputmode="decimal" id="fge_price_amount" name="fge_price_amount" value="<?php echo esc_attr( $pamount ); ?>" placeholder="2400">
								</div>
								<div>
									<label class="fg-form-label">Basis</label>
									<input type="hidden" name="fge_price_basis" id="fge_price_basis" value="<?php echo esc_attr( $pbasis ); ?>">
									<div class="fp-price-modes" style="margin-bottom:0;">
										<button type="button" class="fp-price-mode<?php echo 'person' === $pbasis ? ' on' : ''; ?>" data-fp-basis="person">pro Person</button>
										<button type="button" class="fp-price-mode<?php echo 'pauschal' === $pbasis ? ' on' : ''; ?>" data-fp-basis="pauschal">Pauschal</button>
									</div>
								</div>
							</div>
						</div>

						<div id="fp-price-einzel" style="<?php echo 'einzel' === $pmode ? '' : 'display:none;'; ?>">
							<div id="fp-price-items">
								<?php foreach ( $pitems as $pit ) : ?>
								<div class="fp-price-item">
									<input class="fg-form-input" type="text" value="<?php echo esc_attr( (string) ( $pit['label'] ?? '' ) ); ?>" placeholder="Bezeichnung (z. B. Golflehrer)" data-fp-item-label>
									<input class="fg-form-input" type="text" inputmode="decimal" value="<?php echo esc_attr( (string) ( $pit['cost'] ?? '' ) ); ?>" placeholder="80" data-fp-item-cost>
									<select class="fg-form-input fp-item-basis" data-fp-item-basis aria-label="Preisbasis">
										<option value="pauschal" <?php selected( ( $pit['basis'] ?? 'pauschal' ), 'pauschal' ); ?>>pauschal</option>
										<option value="person" <?php selected( ( $pit['basis'] ?? 'pauschal' ), 'person' ); ?>>pro Person</option>
									</select>
									<button type="button" class="x" data-fp-item-remove aria-label="Entfernen">×</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="fp-btn fp-btn-ghost fp-btn-sm" id="fp-price-additem">+ Kosten hinzufügen</button>
							<textarea name="fge_line_items" id="fge_line_items" hidden><?php echo esc_textarea( implode( "\n", array_map( static fn( $i ): string => ( $i['label'] ?? '' ) . ' | ' . ( $i['cost'] ?? '' ) . ' | ' . ( ( $i['basis'] ?? 'pauschal' ) === 'person' ? 'person' : 'pauschal' ), $pitems ) ) ); ?></textarea>
						</div>

						<div class="fp-price-summary" id="fp-price-summary" data-markup="<?php echo (int) $markup; ?>">
							<div class="row"><span>Netto-Summe</span><span class="v" id="fp-sum-net">€0</span></div>
							<div class="row"><span>+ Vermittlung Firmengolf (<?php echo (int) $markup; ?> %, aufgerundet)</span><span class="v" id="fp-sum-fee">€0</span></div>
							<div class="row total"><span>Gesamtpreis für das Unternehmen</span><span class="v" id="fp-sum-total">€0</span></div>
							<div class="row" style="font-size:12px;color:var(--ink-500);border:0;padding-top:6px;"><span>Alle Beträge netto, die gesetzliche MwSt. kommt auf der Rechnung oben drauf.</span><span></span></div>
						</div>
						<div class="fg-form-row fg-form-row--3col">
							<div>
								<label class="fg-form-label" for="fge_duration">Dauer</label>
								<input class="fg-form-input" type="text" id="fge_duration" name="fge_duration" value="<?php echo $v( 'fge_duration' ); ?>" placeholder="z.B. 4 Stunden">
							</div>
							<div>
								<label class="fg-form-label" for="fge_participants_min">Min. Teilnehmer</label>
								<input class="fg-form-input" type="number" id="fge_participants_min" name="fge_participants_min" value="<?php echo esc_attr( ( $saved['fge_participants_min'] ?? '' ) ?: '' ); ?>" min="1" placeholder="z.B. 8">
							</div>
							<div>
								<label class="fg-form-label" for="fge_participants_max">Max. Teilnehmer</label>
								<?php
								$platz_cap = (array) get_post_meta( $partner_id, '_fge_cap', true );
								$platz_max = (int) ( $platz_cap['max'] ?? 0 );
								// Neues Event: mit dem Platz-Maximum vorbelegen.
								$pmax_val = ( $saved['fge_participants_max'] ?? '' ) ?: ( ( ! $is_edit && $platz_max > 0 ) ? (string) $platz_max : '' );
								?>
								<input class="fg-form-input" type="number" id="fge_participants_max" name="fge_participants_max" value="<?php echo esc_attr( $pmax_val ); ?>" min="1" placeholder="z.B. 40">
								<?php if ( $platz_max > 0 ) : ?>
								<p class="fp-help" id="fge-pmax-warn" style="display:none;color:#9a6b00;margin-top:6px;">Liegt über eurem Platz-Maximum von <?php echo (int) $platz_max; ?> Personen. Prüf kurz, ob das wirklich passt.</p>
								<script>
								(function () {
									var inp  = document.getElementById('fge_participants_max');
									var warn = document.getElementById('fge-pmax-warn');
									if (!inp || !warn) { return; }
									var platzMax = <?php echo (int) $platz_max; ?>;
									var upd = function () {
										warn.style.display = parseInt(inp.value || '0', 10) > platzMax ? '' : 'none';
									};
									inp.addEventListener('input', upd);
									upd();
								})();
								</script>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- Inkludierte Leistungen + Tagesablauf -->
					<div class="fp-form-sec">
						<h3>Inkludierte Leistungen</h3>
						<p class="fp-help">Wähl aus, was in diesem Angebot enthalten ist. Die Liste erscheint als „Im Preis enthalten" auf der Event-Seite und Firmen filtern danach.</p>

						<?php
						// Anlage ≠ Leistung: Die Onboarding-Infrastruktur wird in buchbare
						// Leistungs-Formulierungen übersetzt (der 18-Loch-Platz ist vor Ort,
						// die Leistung ist die Runde darauf). Reine Fakten wie WLAN oder
						// Duschen tauchen hier bewusst nicht auf.
						$p_infra_sel = array_map( 'strval', (array) get_post_meta( $partner_id, '_fge_infra', true ) );

						$infra_to_service = [
							'Golf & Training' => [
								'trial-course'    => 'Schnupperkurs',
								'platzreife'      => 'Platzreifekurs',
								'company-course'  => 'Firmenkurs',
								'advanced-course' => 'Fortgeschrittenenkurs',
								'coach'           => 'Golftraining',
								'driving-range'   => 'Range-Nutzung inkl. Bälle',
								'trackman'        => 'TrackMan-Session',
								'toptracer'       => 'Toptracer-Session',
								'indoor'          => 'Indoor-Simulator-Session',
								'course-18'       => '18-Loch-Runde (Greenfee)',
								'course-9'        => '9-Loch-Runde (Greenfee)',
								'short-course'    => 'Kurzplatz-Runde',
								'short-game'      => 'Putting- & Kurzspiel-Challenge',
								'rental-clubs'    => 'Leihschläger',
								'range-balls'     => 'Range-Bälle',
							],
							'Räume & Tagung' => [
								'meeting-room' => 'Meetingraum-Nutzung',
								'seminar'      => 'Seminarraum-Nutzung',
								'conference'   => 'Konferenzraum-Nutzung',
								'workshop'     => 'Workshopraum-Nutzung',
								'eventroom'    => 'Eventraum-Nutzung',
							],
							'Verpflegung' => [
								'breakfast'    => 'Frühstück',
								'lunch'        => 'Lunch',
								'dinner'       => 'Abendessen',
								'bbq'          => 'BBQ',
								'catering'     => 'Catering',
								'coffee-break' => 'Kaffeepause',
								'drinks-flat'  => 'Getränkepauschale',
								'halfway'      => 'Halfway-Verpflegung',
							],
						];

						$svc_groups = [];
						foreach ( $infra_to_service as $svc_group => $svc_map ) {
							foreach ( $svc_map as $svc_id => $svc_label ) {
								if ( in_array( $svc_id, $p_infra_sel, true ) ) {
									$svc_groups[ $svc_group ][] = $svc_label;
								}
							}
						}
						// Tagungstechnik gesammelt als eine Leistung anbieten.
						if ( array_intersect( [ 'beamer', 'screen', 'mic', 'flipchart', 'whiteboard', 'moderation' ], $p_infra_sel ) ) {
							$svc_groups['Räume & Tagung'][] = 'Tagungstechnik (Beamer, Leinwand & Co.)';
						}
						// Extras, die keine Anlage voraussetzen.
						$svc_groups['Extras'] = [
							'Shuttle-Service', 'Begrüßungsgetränk', 'Urkunde & Foto-Erinnerung',
							'Turnierorganisation', 'Übernachtung',
						];

						$cur_includes = isset( $saved['fge_event_includes'] )
							? array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $saved['fge_event_includes'] ) ) ) )
							: ( $event_id ? array_map( 'strval', (array) get_post_meta( $event_id, '_fge_event_includes', true ) ) : [] );
						?>
						<div class="fp-inc-chips" id="fp-inc-chips">
							<?php foreach ( $cur_includes as $inc ) : ?>
							<span class="fp-inc-chip" data-fp-chip="<?php echo esc_attr( $inc ); ?>">
								<span class="fp-inc-chip-ic" aria-hidden="true"><?php echo function_exists( 'fge_include_icon' ) ? fge_include_icon( $inc ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
								<span class="fp-inc-chip-l"><?php echo esc_html( $inc ); ?></span>
								<button type="button" data-fp-chip-remove aria-label="Entfernen">×</button>
							</span>
							<?php endforeach; ?>
							<span class="fp-inc-add-wrap">
								<button type="button" class="fp-inc-add" id="fp-inc-addbtn"><span class="fp-inc-add-plus">+</span>Leistung hinzufügen</button>
							</span>
						</div>
						<textarea name="fge_event_includes" id="fge_event_includes" hidden><?php echo esc_textarea( implode( "\n", $cur_includes ) ); ?></textarea>

						<!-- Auswahl-Fenster: Leistungs-Kacheln im Onboarding-Look -->
						<div class="fp-svc-overlay" id="fp-svc-overlay" hidden>
							<div class="fp-svc-sheet" role="dialog" aria-modal="true" aria-label="Leistungen auswählen">
								<div class="fp-svc-bar">
									<span class="t">Leistungen auswählen</span>
									<button type="button" class="fp-svc-close" id="fp-svc-close" aria-label="Schließen">×</button>
								</div>
								<div class="fp-svc-body">
									<?php foreach ( $svc_groups as $svc_group => $svc_list ) : ?>
										<?php if ( empty( $svc_list ) ) { continue; } ?>
									<div class="fp-svc-group">
										<div class="fp-svc-group-h"><?php echo esc_html( $svc_group ); ?></div>
										<div class="fp-svc-grid">
											<?php foreach ( $svc_list as $svc ) : ?>
											<label class="fp-svc-card">
												<input type="checkbox" data-fp-svc="<?php echo esc_attr( $svc ); ?>" <?php checked( in_array( $svc, $cur_includes, true ) ); ?>>
												<span class="fp-svc-ico" aria-hidden="true"><?php echo function_exists( 'fge_include_icon' ) ? fge_include_icon( $svc ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
												<span class="fp-svc-l"><?php echo esc_html( $svc ); ?></span>
												<span class="fp-svc-check" aria-hidden="true">✓</span>
											</label>
											<?php endforeach; ?>
										</div>
									</div>
									<?php endforeach; ?>
									<div class="fp-svc-custom">
										<input class="fg-form-input" type="text" id="fp-inc-custom-input" placeholder="Eigene Leistung, z. B. Feuerwerk …">
										<button type="button" class="fp-btn fp-btn-ghost fp-btn-sm" id="fp-inc-custom-add">Hinzufügen</button>
									</div>
								</div>
								<div class="fp-svc-foot">
									<span class="fp-svc-count" id="fp-svc-count"></span>
									<button type="button" class="fp-btn fp-btn-brand" id="fp-svc-done">Fertig</button>
								</div>
							</div>
						</div>

						<div class="fg-form-row" style="margin-top:24px;">
							<label class="fg-form-label">So läuft der Tag ab</label>
							<p class="fp-help" style="margin-bottom:12px;">Baue den Ablauf in Schritten, von der Ankunft bis zur Heimfahrt, so erscheint er als Timeline auf deiner Event-Seite.</p>
							<div id="fp-dayflow-builder" hidden>
								<div id="fp-dayflow-steps"></div>
								<button type="button" class="fp-btn fp-btn-ghost fp-btn-sm" id="fp-dayflow-add">+ Schritt hinzufügen</button>
							</div>
							<textarea class="fg-form-textarea" id="fge_event_dayflow" name="fge_event_dayflow" rows="9" placeholder="Ankunft &amp; Begrüßung&#10;Treffpunkt ist der Pro-Shop. Wir begrüßen euch und stellen Platz und Anlage kurz vor.&#10;&#10;Schnupperkurs auf der Range&#10;Ca. 1 Stunde mit unseren Golflehrern: Abschlag, Grundtechnik und die ersten Erfolgserlebnisse."><?php echo esc_textarea( (string) ( $saved['fge_event_dayflow'] ?? ( $event_id ? get_post_meta( $event_id, '_fge_event_dayflow', true ) : '' ) ) ); ?></textarea>
						</div>
						<script>
						(function(){
							var src = document.getElementById('fge_event_dayflow');
							var builder = document.getElementById('fp-dayflow-builder');
							var list = document.getElementById('fp-dayflow-steps');
							if (!src || !builder || !list) return;

							function sync(){
								var blocks = [];
								list.querySelectorAll('.fp-dfs').forEach(function(row){
									var h = row.querySelector('.fp-dfs-h').value.trim();
									var t = row.querySelector('.fp-dfs-t').value.trim();
									if (h === '' && t === '') return;
									blocks.push(h + (t ? '\n' + t : ''));
								});
								src.value = blocks.join('\n\n');
								list.querySelectorAll('.fp-dfs-num').forEach(function(n, i){ n.textContent = i + 1; });
							}

							function addStep(heading, text){
								var row = document.createElement('div');
								row.className = 'fp-dfs';
								row.innerHTML = '<span class="fp-dfs-num"></span>'
									+ '<div class="fp-dfs-fields">'
									+ '<input type="text" class="fg-form-input fp-dfs-h" placeholder="Überschrift, z. B. Ankunft &amp; Begrüßung" maxlength="80">'
									+ '<textarea class="fg-form-textarea fp-dfs-t" rows="2" placeholder="Kurze Beschreibung dieses Schritts"></textarea>'
									+ '</div>'
									+ '<button type="button" class="fp-dfs-del" title="Schritt entfernen" aria-label="Schritt entfernen">×</button>';
								row.querySelector('.fp-dfs-h').value = heading || '';
								row.querySelector('.fp-dfs-t').value = text || '';
								row.querySelector('.fp-dfs-del').addEventListener('click', function(){ row.remove(); sync(); });
								row.addEventListener('input', sync);
								list.appendChild(row);
								sync();
								return row;
							}

							// Bestehenden Text in Schritte parsen (Block = Leerzeile-getrennt, Zeile 1 = Überschrift).
							var val = src.value.trim();
							if (val !== '') {
								val.split(/\n\s*\n/).forEach(function(block){
									var lines = block.split('\n');
									addStep(lines.shift().trim(), lines.join('\n').trim());
								});
							} else {
								addStep('', '');
							}

							document.getElementById('fp-dayflow-add').addEventListener('click', function(){
								var row = addStep('', '');
								row.querySelector('.fp-dfs-h').focus();
							});

							// Builder an, Roh-Textfeld aus (bleibt das submit-Feld; ohne JS bleibt es sichtbar).
							builder.hidden = false;
							src.style.display = 'none';
						})();
						</script>
					</div>

					<!-- Verfügbarkeit -->
					<div class="fp-form-sec">
						<h3>Verfügbarkeit</h3>
						<p class="fp-help">An welchen Wochentagen kann dieses Event angefragt werden? Standort und Saison kommen automatisch von deinem Platz.</p>

						<div class="fg-form-row">
							<label class="fg-form-label">Verfügbare Wochentage</label>
							<div class="fp-day-row">
								<?php foreach ( $weekdays as $val => $label ) : ?>
									<label class="fp-day">
										<input type="checkbox" name="fge_available_weekdays[]" value="<?php echo esc_attr( $val ); ?>" <?php checked( in_array( $val, $saved_weekdays, true ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<!-- Terminabstimmung -->
					<div class="fp-form-sec">
						<h3>Terminabstimmung</h3>
						<p class="fp-help">Wenn ein Unternehmen anfragt, schickt es bis zu drei Wunschtermine mit. Hier legst du fest, wer diese Termine für dieses Event bestätigt.</p>

						<div class="fp-rel-info">
							<strong>So funktioniert die Abstimmung im Team:</strong> Alle ausgewählten Personen bekommen die Wunschtermine automatisch per E-Mail und antworten mit einem Klick „passt" oder „passt nicht", ganz ohne eigenes Konto. Du siehst alle Rückmeldungen gesammelt und niemand muss hinterhertelefonieren. So werden Events deutlich schneller verbindlich.
						</div>

						<?php
						$fge_release  = (string) ( $saved['fge_release_mode'] ?? '' ) ?: ( $event_id ? ( (string) get_post_meta( $event_id, '_fge_release_mode', true ) ?: 'us' ) : 'us' );
						$fge_resp_sel = $event_id ? array_map( 'absint', (array) get_post_meta( $event_id, '_fge_event_responder_ids', true ) ) : [];
						// Hauptkontakt (Platz) stimmt immer mit ab → nicht als abwählbares Team-Mitglied zeigen.
						$fge_owner_id = function_exists( 'fge_partner_ensure_owner_contact' ) ? fge_partner_ensure_owner_contact( $partner_id ) : 0;
						$fge_contacts = function_exists( 'fge_contacts_get' ) ? fge_contacts_get( $partner_id ) : [];
						$fge_contacts = array_values( array_filter( $fge_contacts, static function ( $c ) use ( $fge_owner_id ) {
							return (int) $c['id'] !== $fge_owner_id && (int) ( $c['user_id'] ?? 0 ) === 0;
						} ) );
						?>
						<div class="fp-rel-opts" id="fp-rel-opts">
							<label class="fp-rel-opt">
								<input type="radio" name="fge_release_mode" value="us" <?php checked( $fge_release, 'us' ); ?>>
								<span class="fp-rel-radio" aria-hidden="true"></span>
								<span>
									<span class="fp-rel-t">Nur wir am Platz</span>
									<span class="fp-rel-s">Ihr bekommt die Wunschtermine per Mail-Link und stimmt sie allein ab.</span>
								</span>
							</label>
							<label class="fp-rel-opt">
								<input type="radio" name="fge_release_mode" value="approve" <?php checked( $fge_release, 'approve' ); ?>>
								<span class="fp-rel-radio" aria-hidden="true"></span>
								<span>
									<span class="fp-rel-t">Team stimmt mit ab <span class="fp-rel-badge">Empfohlen</span></span>
									<span class="fp-rel-s">Zusätzlich zu euch bestätigen Gastronomie, Pro oder Sekretariat ihre Verfügbarkeit selbst per Mail-Link. Ideal, wenn mehrere Bereiche am Event beteiligt sind.</span>
								</span>
							</label>
						</div>

						<div id="fge-responders" style="margin-top:14px;<?php echo 'approve' === $fge_release ? '' : 'display:none;'; ?>">
							<p class="fg-form-label" style="margin-bottom:10px;font-weight:600;">Wer stimmt für dieses Event ab?</p>
							<?php if ( $fge_contacts ) : ?>
							<div class="fg-form-checkgrid" style="margin-bottom:12px;">
								<?php foreach ( $fge_contacts as $fc ) :
									$fc_perm = function_exists( 'fge_contact_normalize_permission' )
										? fge_contact_normalize_permission( (string) ( $fc['permission'] ?? '' ), (string) ( $fc['role'] ?? '' ) )
										: (string) ( $fc['permission'] ?? '' );
									$fc_perm_lbl = fge_contact_permissions()[ $fc_perm ] ?? $fc_perm;
									$fc_checked  = $fge_resp_sel ? in_array( (int) $fc['id'], $fge_resp_sel, true ) : ( 'vote' === $fc_perm );
								?>
								<label class="fg-form-check">
									<input type="checkbox" name="fge_event_responders[]" value="<?php echo esc_attr( (string) $fc['id'] ); ?>" <?php checked( $fc_checked ); ?>>
									<span><?php echo esc_html( $fc['name'] . ( '' !== (string) ( $fc['role'] ?? '' ) ? ' · ' . $fc['role'] : '' ) ); ?>
										<span class="fp-perm-tag <?php echo 'vote' === $fc_perm ? 'is-vote' : 'is-notify'; ?>"><?php echo esc_html( $fc_perm_lbl ); ?></span>
									</span>
								</label>
								<?php endforeach; ?>
							</div>
							<?php endif; ?>
							<div id="fge-newresp-list"></div>
							<button type="button" class="fp-btn fp-btn-ghost fp-btn-sm" id="fge-newresp-add">+ Neue Person hinzufügen</button>
							<p class="fp-help" style="margin-top:8px;">Neue Personen werden deinen Ansprechpartnern hinzugefügt und direkt für dieses Event ausgewählt.</p>
							<template id="fge-newresp-tpl">
								<div class="fp-newresp" style="margin-top:12px;padding:14px;border:1px solid var(--ink-200);border-radius:12px;">
									<div class="fg-form-row fg-form-row--3col">
										<div><input class="fg-form-input" type="text" name="fge_new_responder_name[]" placeholder="Vor- und Nachname"></div>
										<div>
											<select class="fg-form-input" name="fge_new_responder_role[]">
												<option value="">Rolle wählen …</option>
												<?php foreach ( fge_catalog_contact_roles() as $r ) : ?>
												<option value="<?php echo esc_attr( $r ); ?>"><?php echo esc_html( $r ); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div><input class="fg-form-input" type="email" name="fge_new_responder_email[]" placeholder="name@golfclub.de"></div>
									</div>
									<div class="fg-form-row" style="margin-top:8px;">
										<select class="fg-form-input" name="fge_new_responder_perm[]">
											<option value="">Standard nach Rolle</option>
											<option value="vote">Terminabstimmung: stimmt Wunschterminen per Link zu</option>
											<option value="notify">Nur informieren: bekommt Status-Mails, stimmt nicht ab</option>
										</select>
									</div>
								</div>
							</template>
						</div>
						<script>
						(function () {
							var opts = document.getElementById('fp-rel-opts');
							var box  = document.getElementById('fge-responders');
							var add  = document.getElementById('fge-newresp-add');
							var list = document.getElementById('fge-newresp-list');
							var tpl  = document.getElementById('fge-newresp-tpl');
							if (opts && box) {
								opts.addEventListener('change', function () {
									var sel = opts.querySelector('input[name="fge_release_mode"]:checked');
									box.style.display = ( sel && sel.value === 'approve' ) ? '' : 'none';
								});
							}
							if (add && list && tpl) {
								add.addEventListener('click', function () {
									list.appendChild(tpl.content.firstElementChild.cloneNode(true));
								});
							}
						})();
						</script>
					</div>

				</div><!-- /left -->

				<!-- ── RIGHT: sticky rail ── -->
				<div class="fp-edit-rail">

					<div class="fp-rail-card">
						<h4>Status</h4>
						<span class="fp-pill <?php echo esc_attr( $status_class ); ?>">
							<span class="dot"></span>
							<?php echo esc_html( $status_label ); ?>
						</span>
						<p style="font-size:13px;color:var(--ink-500);margin-top:12px;line-height:1.5;">
							<?php echo $is_edit
								? 'Gespeicherte Änderungen gehen erneut in Prüfung.'
								: 'Nach dem Einreichen wird dein Angebot von Firmengolf geprüft und dann freigegeben.'; ?>
						</p>
						<?php
						$lc_status = $event_id ? (string) get_post_meta( $event_id, '_fge_event_status', true ) : '';
						if ( $is_edit && in_array( $lc_status, [ 'freigegeben', 'pausiert' ], true ) ) :
							$lc_action = $lc_status === 'freigegeben' ? 'pause' : 'reactivate';
							$lc_label  = $lc_status === 'freigegeben' ? 'Angebot pausieren' : 'Angebot reaktivieren';
							$lc_url    = wp_nonce_url( add_query_arg( [ 'tab' => 'angebote', 'portal_action' => $lc_action, 'event_id' => $event_id ], fge_portal_page_url() ), 'fge_portal_lifecycle_' . $event_id );
						?>
						<a href="<?php echo esc_url( $lc_url ); ?>" class="fp-btn fp-btn-ghost fp-btn-sm" style="margin-top:14px;display:inline-flex;" onclick="return confirm('<?php echo 'pause' === $lc_action ? 'Dieses Angebot pausieren? Es ist dann nicht mehr öffentlich sichtbar.' : 'Dieses Angebot reaktivieren? Es ist dann wieder öffentlich sichtbar.'; ?>');"><?php echo esc_html( $lc_label ); ?></a>
						<?php endif; ?>
					</div>

					<?php
					$pv_name   = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
					$pv_rating = (string) get_post_meta( $partner_id, '_fge_rating', true );
					$pv_cover  = $cover_id > 0 ? (string) wp_get_attachment_image_url( $cover_id, 'large' ) : '';
					?>
					<div class="fp-rail-card">
						<h4>Live-Vorschau</h4>
						<p style="font-size:12px;color:var(--ink-500);margin:0 0 10px;">So erscheint dein Angebot in der Suche. Aktualisiert sich beim Tippen.</p>
						<?php
						// Option E · Live-Vorschau. IDs werden vom JS (updPreview) beim Tippen gefüllt.
						$pv_pin  = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
						$pv_usr  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
						$pv_clk  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
						$pv_desc0 = (string) ( $saved['fge_card_description'] ?? '' );
						?>
						<article class="fg-event evE-card fp-preview-card">
							<div class="evE-photo">
								<div class="evE-img" id="fp-pv-photo" style="<?php echo $pv_cover ? "background-image:url('" . esc_url( $pv_cover ) . "');" : 'background:var(--paper-300);'; // phpcs:ignore WordPress.Security.EscapeOutput ?>"></div>
								<span class="evE-chip" id="fp-pv-chip"><?php echo esc_html( $event_type_label ?: 'Eventart' ); ?></span>
							</div>
							<div class="evE-body">
								<span class="evE-venue"><?php echo $pv_pin; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $pv_name ?: 'k. A.' ); ?></span>
								<h3 class="evE-title" id="fp-pv-title"><?php echo esc_html( $event_title ?: 'Titel deines Angebots' ); ?></h3>
								<p class="evE-desc" id="fp-pv-desc"><?php echo esc_html( $pv_desc0 ); ?></p>
								<div class="evE-meta">
									<span class="m"><?php echo $pv_usr; // phpcs:ignore WordPress.Security.EscapeOutput ?><span id="fp-pv-guests">Teilnehmer</span></span>
									<span class="m"><?php echo $pv_clk; // phpcs:ignore WordPress.Security.EscapeOutput ?><span id="fp-pv-duration">Dauer</span></span>
									<?php if ( $pv_rating ) : ?><span class="m evE-rate"><svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><b><?php echo esc_html( $pv_rating ); ?></b></span><?php endif; ?>
								</div>
								<div class="evE-foot">
									<div class="evE-price" id="fp-pv-price">Preis</div>
									<span class="evE-cta">Ansehen</span>
								</div>
							</div>
						</article>
					</div>

					<div class="fp-rail-card fp-rail-card--tip">
						<h4>Tipp vom Team</h4>
						<p style="font-size:14px;line-height:1.5;color:rgba(251,250,246,0.85);margin-bottom:0;">
							Angebote mit eigenem Foto vom Platz erhalten <strong style="color:var(--paper-100);">3× mehr Anfragen</strong> als solche ohne Foto.
						</p>
					</div>

					<?php $help_co = fge_company(); ?>
					<div class="fp-rail-card">
						<h4>Du brauchst Hilfe?</h4>
						<p style="font-size:14px;line-height:1.5;color:var(--ink-600);margin:0 0 12px;">
							Dann ruf uns einfach an, wir richten dein Angebot gern gemeinsam mit dir ein.
						</p>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) ( $help_co['phone_display'] ?? '' ) ) ); ?>" class="fp-btn fp-btn-ghost fp-btn-sm" style="display:inline-flex;">
							<?php echo esc_html( $help_co['phone_display'] ?? '' ); ?>
						</a>
					</div>

				</div><!-- /rail -->

			</div><!-- .fp-edit-grid -->

			<div class="fp-edit-actionbar">
				<span id="fp-unsaved-hint" style="font-size:13px;color:var(--ink-500);display:inline-flex;align-items:center;gap:8px;<?php echo $is_edit ? 'visibility:hidden;' : ''; ?>">
					<span class="fp-unsaved-dot" style="width:7px;height:7px;border-radius:50%;background:var(--warning);flex:none;display:inline-block;"></span>
					<?php echo $is_edit ? 'Änderungen noch nicht gespeichert' : 'Noch nicht eingereicht'; ?>
				</span>
				<script>
				// Hinweis erst zeigen, wenn wirklich etwas geändert wurde (Audit D8).
				(function () {
					var hint = document.getElementById('fp-unsaved-hint');
					var form = document.getElementById('fp-event-form');
					if (hint && form && hint.style.visibility === 'hidden') {
						form.addEventListener('input', function () { hint.style.visibility = 'visible'; }, { once: true });
						form.addEventListener('change', function () { hint.style.visibility = 'visible'; }, { once: true });
					}
				})();
				</script>
				<button type="submit" class="fp-btn fp-btn-brand">
					<?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php echo $is_edit ? 'Änderungen speichern' : 'Event einreichen'; ?>
				</button>
			</div>

			<script>
			(function () {
				var byId = function (id) { return document.getElementById(id); };
				var fmt  = function (n) { return (Math.round(n * 100) / 100).toLocaleString('de-DE') + ' €'; };

				// ── Preis: Modus/Basis-Pills, Posten-Zeilen, Live-Rechnung ──
				var modeIn  = byId('fge_price_mode');
				var basisIn = byId('fge_price_basis');
				var amount  = byId('fge_price_amount');
				var boxG    = byId('fp-price-gesamt');
				var boxE    = byId('fp-price-einzel');
				var items   = byId('fp-price-items');
				var itemsTa = byId('fge_line_items');
				var summary = byId('fp-price-summary');
				var markup  = summary ? parseInt(summary.dataset.markup || '20', 10) : 20;

				function parseNum(v) {
				var s = String(v || '').replace(/[^\d.,]/g, '');
				if (s.indexOf(',') !== -1) { s = s.replace(/\./g, ''); var i = s.lastIndexOf(','); s = s.slice(0, i).replace(/,/g, '') + '.' + s.slice(i + 1); }
				else if ((s.match(/\./g) || []).length > 1 || /\.\d{3}$/.test(s)) { s = s.replace(/\./g, ''); }
				return parseFloat(s) || 0;
				}
				function itemSums() {
					var pp = 0, flat = 0;
					if (items) {
						items.querySelectorAll('.fp-price-item').forEach(function (row) {
							var c = row.querySelector('[data-fp-item-cost]');
							var b = row.querySelector('[data-fp-item-basis]');
							var v = c ? parseNum(c.value) : 0;
							if (b && b.value === 'person') { pp += v; } else { flat += v; }
						});
					}
					return { pp: pp, flat: flat };
				}
				function syncItems() {
					if (!items || !itemsTa) { return; }
					var lines = [];
					items.querySelectorAll('.fp-price-item').forEach(function (row) {
						var l = row.querySelector('[data-fp-item-label]');
						var c = row.querySelector('[data-fp-item-cost]');
						var b = row.querySelector('[data-fp-item-basis]');
						if (l && l.value.trim() !== '') { lines.push(l.value.trim() + ' | ' + (c ? c.value.trim() : '') + ' | ' + (b ? b.value : 'pauschal')); }
					});
					itemsTa.value = lines.join('\n');
				}
				function recalc() {
					if (!summary) { return; }
					var paxIn  = byId('fge_participants_max');
					var paxMax = paxIn ? parseInt(paxIn.value, 10) || 0 : 0;
					var einzel = modeIn && modeIn.value === 'einzel';
					var net, perPerson, netLabel;
					if (einzel) {
						var su = itemSums();
						perPerson = su.pp > 0;
						// „ab"-Preis p.P.: Pauschalen auf die maximale Gruppengröße umgelegt
						net = perPerson ? su.pp + (paxMax > 0 ? su.flat / paxMax : 0) : su.flat;
						netLabel = perPerson && su.flat > 0
							? fmt(su.pp) + ' p.P. + ' + fmt(su.flat) + ' pauschal'
							: fmt(net) + (perPerson ? ' pro Person' : '');
						if (perPerson && su.flat > 0 && !paxMax) { netLabel += ', bitte max. Teilnehmer angeben'; }
					} else {
						perPerson = basisIn && basisIn.value === 'person';
						net = amount ? parseNum(amount.value) : 0;
						netLabel = fmt(net) + (perPerson ? ' pro Person' : '');
					}
					// Kundenpreis geglättet wie in PHP (fge_price_smooth, event-pricing.php):
					// aufrunden auf Endziffer 4 oder 9 (5er-Raster p.P., 50er-Raster Gesamt,
					// um eins nach unten versetzt). Zwilling identisch zur PHP-Funktion halten!
					var step  = perPerson ? 5 : 50;
					var total = net > 0 ? Math.ceil((net * (1 + markup / 100) + 1) / step) * step - 1 : 0;
					byId('fp-sum-net').textContent   = netLabel;
					byId('fp-sum-fee').textContent   = fmt(total - net) + (perPerson ? ' pro Person' : '');
					byId('fp-sum-total').textContent = (einzel && perPerson ? 'ab ' : '') + fmt(total) + (perPerson ? ' pro Person' : '') + (einzel && perPerson && paxMax ? ' (bei ' + paxMax + ' Personen)' : '');
					var pv = byId('fp-pv-price');
					if (pv) { pv.textContent = net > 0 ? 'ab ' + fmt(total) + (perPerson ? ' /p.P.' : '') : 'Preis'; }
				}
				var paxMaxIn = byId('fge_participants_max');
				if (paxMaxIn) { paxMaxIn.addEventListener('input', recalc); }
				document.querySelectorAll('[data-fp-mode]').forEach(function (b) {
					b.addEventListener('click', function () {
						if (modeIn) { modeIn.value = b.dataset.fpMode; }
						document.querySelectorAll('[data-fp-mode]').forEach(function (x) { x.classList.toggle('on', x === b); });
						if (boxG) { boxG.style.display = b.dataset.fpMode === 'gesamt' ? '' : 'none'; }
						if (boxE) { boxE.style.display = b.dataset.fpMode === 'einzel' ? '' : 'none'; }
						recalc();
					});
				});
				document.querySelectorAll('[data-fp-basis]').forEach(function (b) {
					b.addEventListener('click', function () {
						if (basisIn) { basisIn.value = b.dataset.fpBasis; }
						document.querySelectorAll('[data-fp-basis]').forEach(function (x) { x.classList.toggle('on', x === b); });
						recalc();
					});
				});
				function addItemRow(label, cost, basis) {
					var row = document.createElement('div');
					row.className = 'fp-price-item';
					row.innerHTML = '<input class="fg-form-input" type="text" placeholder="Bezeichnung (z. B. Golflehrer)" data-fp-item-label>' +
						'<input class="fg-form-input" type="text" inputmode="decimal" placeholder="80" data-fp-item-cost>' +
						'<select class="fg-form-input fp-item-basis" data-fp-item-basis aria-label="Preisbasis"><option value="pauschal">pauschal</option><option value="person">pro Person</option></select>' +
						'<button type="button" class="x" data-fp-item-remove aria-label="Entfernen">×</button>';
					row.querySelector('[data-fp-item-label]').value = label || '';
					row.querySelector('[data-fp-item-cost]').value  = cost || '';
					row.querySelector('[data-fp-item-basis]').value = basis === 'person' ? 'person' : 'pauschal';
					items.appendChild(row);
				}
				var addBtn = byId('fp-price-additem');
				if (addBtn) { addBtn.addEventListener('click', function () { addItemRow('', ''); }); }
				if (items) {
					items.addEventListener('input', function () { syncItems(); recalc(); });
					items.addEventListener('click', function (e) {
						var rm = e.target.closest('[data-fp-item-remove]');
						if (rm) { rm.closest('.fp-price-item').remove(); syncItems(); recalc(); }
					});
				}
				if (amount) { amount.addEventListener('input', recalc); }
				syncItems();
				recalc();

				// ── Inkludierte Leistungen: Chips + Kachel-Auswahlfenster ──
				var chips   = byId('fp-inc-chips');
				var incTa   = byId('fge_event_includes');
				var overlay = byId('fp-svc-overlay');
				function syncChips() {
					if (!chips || !incTa) { return; }
					var vals = [];
					chips.querySelectorAll('[data-fp-chip]').forEach(function (c) { vals.push(c.dataset.fpChip); });
					incTa.value = vals.join('\n');
					var count = byId('fp-svc-count');
					if (count) { count.textContent = vals.length > 0 ? vals.length + ' ausgewählt' : ''; }
				}
				function hasChip(v) {
					var found = false;
					chips.querySelectorAll('[data-fp-chip]').forEach(function (c) { if (c.dataset.fpChip === v) { found = true; } });
					return found;
				}
				// Icon für eigene Leistungen (Zusatzleistung: Plus im Kreis).
				var ZUSATZ_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>';
				function addChip(v, iconHtml) {
					v = (v || '').trim();
					if (v === '' || hasChip(v)) { return; }
					var chip = document.createElement('span');
					chip.className = 'fp-inc-chip';
					chip.dataset.fpChip = v;
					var ic = document.createElement('span');
					ic.className = 'fp-inc-chip-ic';
					ic.setAttribute('aria-hidden', 'true');
					ic.innerHTML = iconHtml || ZUSATZ_ICON;
					chip.appendChild(ic);
					var lbl = document.createElement('span');
					lbl.className = 'fp-inc-chip-l';
					lbl.textContent = v;
					chip.appendChild(lbl);
					var x = document.createElement('button');
					x.type = 'button';
					x.setAttribute('data-fp-chip-remove', '');
					x.setAttribute('aria-label', 'Entfernen');
					x.textContent = '×';
					chip.appendChild(x);
					chips.insertBefore(chip, chips.querySelector('.fp-inc-add-wrap'));
					syncChips();
				}
				function removeChip(v) {
					chips.querySelectorAll('[data-fp-chip]').forEach(function (c) {
						if (c.dataset.fpChip === v) { c.remove(); }
					});
					syncChips();
				}
				function setCard(v, on) {
					if (!overlay) { return; }
					overlay.querySelectorAll('[data-fp-svc]').forEach(function (cb) {
						if (cb.dataset.fpSvc === v) { cb.checked = on; }
					});
				}
				if (chips) {
					chips.addEventListener('click', function (e) {
						var rm = e.target.closest('[data-fp-chip-remove]');
						if (rm) {
							var chip = rm.closest('[data-fp-chip]');
							setCard(chip.dataset.fpChip, false);
							chip.remove();
							syncChips();
						}
					});
				}
				function openSvc()  { if (overlay) { overlay.hidden = false; document.documentElement.classList.add('fg-drawer-lock'); } }
				function closeSvc() { if (overlay) { overlay.hidden = true; document.documentElement.classList.remove('fg-drawer-lock'); } }
				var addBtnSvc = byId('fp-inc-addbtn');
				if (addBtnSvc) { addBtnSvc.addEventListener('click', openSvc); }
				var closeBtnSvc = byId('fp-svc-close');
				var doneBtnSvc  = byId('fp-svc-done');
				if (closeBtnSvc) { closeBtnSvc.addEventListener('click', closeSvc); }
				if (doneBtnSvc)  { doneBtnSvc.addEventListener('click', closeSvc); }
				if (overlay) {
					overlay.addEventListener('click', function (e) { if (e.target === overlay) { closeSvc(); } });
					document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !overlay.hidden) { closeSvc(); } });
					overlay.addEventListener('change', function (e) {
						var cb = e.target.closest('[data-fp-svc]');
						if (!cb) { return; }
						var card = cb.closest('.fp-svc-card');
						if (cb.checked) {
							var ico = card ? card.querySelector('.fp-svc-ico') : null;
							addChip(cb.dataset.fpSvc, ico ? ico.innerHTML : '');
							if (card) {
								card.classList.remove('fp-svc-pop');
								void card.offsetWidth;
								card.classList.add('fp-svc-pop');
							}
						} else {
							removeChip(cb.dataset.fpSvc);
						}
					});
				}
				var customIn  = byId('fp-inc-custom-input');
				var customAdd = byId('fp-inc-custom-add');
				function addCustom() { addChip(customIn.value); customIn.value = ''; }
				if (customIn && customAdd) {
					customAdd.addEventListener('click', addCustom);
					customIn.addEventListener('keydown', function (e) {
						if (e.key === 'Enter') { e.preventDefault(); addCustom(); }
					});
				}
				syncChips();

				// ── Live-Vorschau ──
				function bindPreview(srcId, fn) {
					var el = byId(srcId);
					if (el) { el.addEventListener('input', fn); el.addEventListener('change', fn); }
				}
				function updPreview() {
					var title = byId('fge_post_title');
					var dur   = byId('fge_duration');
					var minP  = byId('fge_participants_min');
					var maxP  = byId('fge_participants_max');
					var typeRadio = document.querySelector('#fp-type-grid input[name="fge_event_type"]:checked');
					var typeLabel = typeRadio ? (typeRadio.dataset.label || 'Eventart') : 'Eventart';
					var pvT = byId('fp-pv-title');
					if (pvT) { pvT.textContent = ( title && title.value.trim() ) ? title.value.trim() : 'Titel deines Angebots'; }
					var pvC = byId('fp-pv-chip');
					if (pvC) { pvC.textContent = typeLabel; }
					var desc = byId('fge_card_description');
					var pvDesc = byId('fp-pv-desc');
					if (pvDesc) { pvDesc.textContent = ( desc && desc.value.trim() ) ? desc.value.trim() : ''; }
					var pvD = byId('fp-pv-duration');
					if (pvD) { pvD.textContent = ( dur && dur.value.trim() ) ? dur.value.trim() : 'Dauer'; }
					var pvG = byId('fp-pv-guests');
					if (pvG) {
						pvG.textContent = ( minP && maxP && minP.value && maxP.value )
							? minP.value + ' bis ' + maxP.value
							: ( maxP && maxP.value ? 'bis ' + maxP.value : 'Teilnehmer' );
					}
				}
				['fge_post_title', 'fge_duration', 'fge_participants_min', 'fge_participants_max', 'fge_card_description'].forEach(function (id) {
					bindPreview(id, updPreview);
				});
				var typeGrid = byId('fp-type-grid');
				if (typeGrid) {
					typeGrid.addEventListener('change', function (e) {
						var card = e.target.closest('.fp-type-card');
						if (card) {
							card.classList.remove('fp-svc-pop');
							void card.offsetWidth;
							card.classList.add('fp-svc-pop');
						}
						updPreview();
					});
				}
				updPreview();

				// Cover-Auswahl im Picker → Vorschau-Foto (URL aus der Galerie-Config).
				var coverIn = document.querySelector('[data-fge-picker-cover]');
				var pvPhoto = byId('fp-pv-photo');
				function updCover() {
					if (!coverIn || !pvPhoto || !window.FGE_MEDIA) { return; }
					var id = parseInt(coverIn.value || '0', 10);
					var hit = (window.FGE_MEDIA.gallery || []).filter(function (p) { return p.id === id; })[0];
					if (hit) { pvPhoto.style.background = ''; pvPhoto.style.backgroundImage = "url('" + (hit.large || hit.thumb) + "')"; }
				}
				var pickerHost = document.querySelector('[data-fge-picker]');
				if (pickerHost) { pickerHost.addEventListener('click', function () { setTimeout(updCover, 50); }); }
				updCover();
			})();
			</script>

		</form>
	</div><!-- .fp-edit-shell -->
	<?php
}

