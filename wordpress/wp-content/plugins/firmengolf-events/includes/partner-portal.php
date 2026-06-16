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

/** PRG handler for the Anfragen tab — confirm a final wish date. Nonce + ownership. */
function fge_portal_handle_request(): void {
	$action = sanitize_key( $_POST['portal_action'] ?? '' );
	if ( 'confirm_date' !== $action ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_portal_nonce'] ?? '' ) ), 'fge_portal_request' ) ) {
		return;
	}
	$partner_id = fge_portal_get_partner_id();
	if ( $partner_id <= 0 ) {
		return;
	}
	$req = absint( $_POST['req_id'] ?? 0 );
	if ( $req <= 0 || (int) get_post_meta( $req, '_fge_assigned_partner_id', true ) !== $partner_id ) {
		return;
	}
	$idx = absint( $_POST['date_index'] ?? 0 );
	if ( $idx > 0 && function_exists( 'fge_rr_set_final' ) ) {
		fge_rr_set_final( $req, $idx );
		update_post_meta( $req, '_fge_request_status', 'bestaetigt' );
		do_action( 'fge_request_date_confirmed', $req, $idx );
	}
	wp_redirect( esc_url_raw( fge_portal_page_url() . '?tab=anfragen&req=' . $req . '&portal_success=date_confirmed' ), 303 );
	exit;
}

/** Derived pipeline status of a request → [ id, label ]. */
function fge_portal_request_status( int $req ): array {
	$manual = (string) get_post_meta( $req, '_fge_request_status', true );
	if ( in_array( $manual, [ 'abgeschlossen', 'gewonnen' ], true ) ) {
		return [ 'abgeschlossen', 'Abgeschlossen' ];
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
	if ( $cid > 0 ) {
		$c = fge_contact_get( $cid );
		if ( $c && (int) $c['partner_id'] === $partner_id && (int) $c['user_id'] === 0 ) {
			fge_contact_update( $cid, $data );
		}
	} else {
		fge_contact_add( $partner_id, $data );
	}
	wp_redirect( esc_url_raw( $base . '?tab=team&portal_success=contact_saved' ), 303 );
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
		set_transient( 'fge_portal_err_' . $token, [ 'errors' => $errors, 'data' => wp_unslash( $_POST ) ], 300 );
		wp_redirect( esc_url_raw( $base . '?tab=angebote&portal_action=new&portal_err=' . rawurlencode( $token ) ), 303 );
		exit;
	}

	$post_id = wp_insert_post( [
		'post_type'    => 'firmengolf_event',
		'post_status'  => 'publish',
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
		set_transient( 'fge_portal_err_' . $token, [ 'errors' => $errors, 'data' => wp_unslash( $_POST ) ], 300 );
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
	update_post_meta( $event_id, '_fge_event_status', $current_status === 'freigegeben' ? 'aenderung_in_pruefung' : 'zur_pruefung' );
	fge_portal_save_event_meta( $event_id );
	fge_event_save_images( $event_id, $partner_id, wp_unslash( $_POST ) );

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
			update_post_meta( $partner_id, '_fge_event_contact_name',  sanitize_text_field( $P['fge_event_contact_name'] ?? '' ) );
			update_post_meta( $partner_id, '_fge_event_contact_email', sanitize_email( $P['fge_event_contact_email'] ?? '' ) );
			update_post_meta( $partner_id, '_fge_event_contact_phone', sanitize_text_field( $P['fge_event_contact_phone'] ?? '' ) );
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

// ══════════════════════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_page_url(): string {
	$page = get_page_by_path( 'partnerportal' );
	return $page ? trailingslashit( get_permalink( $page->ID ) ) : trailingslashit( home_url( '/partnerportal/' ) );
}

function fge_portal_get_partner_id(): int {
	if ( current_user_can( 'manage_options' ) ) {
		return 0;
	}
	$posts = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'meta_key'    => '_fge_assigned_wp_user_id',
		'meta_value'  => get_current_user_id(),
		'numberposts' => 1,
		'post_status' => 'any',
	] );
	return $posts ? (int) $posts[0]->ID : -1;
}

function fge_portal_access_check(): void {
	if ( is_user_logged_in() ) {
		$user = wp_get_current_user();
		if ( ! current_user_can( 'manage_options' ) && ! in_array( 'firmengolf_partner', (array) $user->roles, true ) ) {
			wp_die(
				'<p>Du hast keine Berechtigung für diesen Bereich.</p><p><a href="' . esc_url( home_url( '/' ) ) . '">Zurück zur Startseite</a></p>',
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
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="Firmengolf" height="28">
			</a>
			<h1 class="fp-gate-title">Partner-Portal</h1>
			<p class="fp-gate-sub">Melde dich mit deinen Zugangsdaten an, um dein Golfplatz-Profil zu verwalten.</p>

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
				Jetzt Golfplatz registrieren
				<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</a>
		</div>
	</div>
	<?php
}

function fge_portal_get_active_tab(): string {
	$allowed = [ 'uebersicht', 'angebote', 'anfragen', 'kalender', 'platz', 'team', 'kennzahlen' ];
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
		'has_golf_teacher'      => [ 'pga', 'coaching', 'golflehrer', 'schnupperkurs' ],
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
	update_post_meta( $post_id, '_fge_price_amount', (float) str_replace( ',', '.', preg_replace( '/[^\d.,]/', '', (string) wp_unslash( $_POST['fge_price_amount'] ?? '' ) ) ) );
	update_post_meta( $post_id, '_fge_price_basis', in_array( $_POST['fge_price_basis'] ?? '', [ 'person', 'pauschal' ], true ) ? $_POST['fge_price_basis'] : 'person' );
	$pli = [];
	foreach ( preg_split( '/\r?\n/', (string) wp_unslash( $_POST['fge_line_items'] ?? '' ) ) as $line ) {
		$line = trim( $line );
		if ( $line === '' ) {
			continue;
		}
		$parts = explode( '|', $line, 2 );
		$lbl   = sanitize_text_field( trim( $parts[0] ?? '' ) );
		$cst   = (float) str_replace( ',', '.', preg_replace( '/[^\d.,]/', '', $parts[1] ?? '' ) );
		if ( $lbl !== '' ) {
			$pli[] = [ 'label' => $lbl, 'cost' => $cst ];
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

	if ( current_user_can( 'manage_options' ) ) {
		?>
		<div class="fg-portal-standalone">
			<p class="fg-portal-standalone-title">Du bist als Administrator angemeldet.</p>
			<p>Das Partnerportal ist für Golfplatz-Partner-Nutzer konzipiert. Alle Daten verwaltest du über die <a href="<?php echo esc_url( admin_url() ); ?>">WordPress-Administrationsoberfläche</a>.</p>
		</div>
		<?php
		return;
	}

	if ( $partner_id === -1 ) {
		?>
		<div class="fg-portal-standalone">
			<p class="fg-portal-standalone-title">Dein Partnerprofil ist noch nicht freigeschaltet.</p>
			<p>Bitte wende dich an das Firmengolf Team: <a href="mailto:events@visionpunch.de">events@visionpunch.de</a></p>
		</div>
		<?php
		return;
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
		'anfragen'   => [ 'Anfragen',        '<path d="M22 12h-5l-2 3h-6l-2-3H2"/><path d="M5 5h14l3 7v7H2v-7z"/>' ],
		'kalender'   => [ 'Kalender',        '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>' ],
		'platz'      => [ 'Platz',           '<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>' ],
		'team'       => [ 'Ansprechpartner', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>' ],
	];
	?>
	<div class="fgpp">
	<nav class="nav">
		<div class="nav-inner">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="Firmengolf">
				<span class="brand-divider"></span>
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
				<?php if ( $archive_url ) : ?><a href="<?php echo esc_url( $archive_url ); ?>" class="nav-link" target="_blank" rel="noopener">Vorschau</a><?php endif; ?>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="nav-link">Abmelden</a>
				<div class="nav-avatar" title="<?php echo esc_attr( $user->display_name ); ?>"><?php echo esc_html( $user_initials ?: 'P' ); ?></div>
			</div>
		</div>
	</nav>
	</div>

	<div class="fp-page-wrap">

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
					Termin bestätigt — Firmengolf kümmert sich um Angebot und Buchung.
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div id="fp-tab-uebersicht"  class="fp-section<?php echo $active_tab !== 'uebersicht'  ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_uebersicht( $partner_id ); ?></div>
		<div id="fp-tab-angebote"    class="fp-section<?php echo $active_tab !== 'angebote'    ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_angebote( $partner_id ); ?></div>
		<div id="fp-tab-anfragen"    class="fp-section<?php echo $active_tab !== 'anfragen'    ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_requests( $partner_id ); ?></div>
		<div id="fp-tab-kalender"    class="fp-section<?php echo $active_tab !== 'kalender'    ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_kalender( $partner_id ); ?></div>
		<div id="fp-tab-platz"       class="fp-section<?php echo $active_tab !== 'platz'       ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_platz( $partner_id ); ?></div>
		<div id="fp-tab-team"        class="fp-section<?php echo $active_tab !== 'team'        ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_team( $partner_id ); ?></div>
		<div id="fp-tab-kennzahlen"  class="fp-section<?php echo $active_tab !== 'kennzahlen'  ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_stats( $partner_id ); ?></div>

	</div>

	<script>
	(function () {
		var tabs     = document.querySelectorAll('.nav-tab');
		var sections = document.querySelectorAll('.fp-section');
		tabs.forEach(function (btn) {
			btn.addEventListener('click', function () {
				tabs.forEach(function (b) { b.classList.remove('active'); });
				sections.forEach(function (s) { s.classList.add('fp-section--hidden'); });
				btn.classList.add('active');
				var el = document.getElementById('fp-tab-' + btn.dataset.tab);
				if (el) { el.classList.remove('fp-section--hidden'); }
				var url = new URL(window.location.href);
				url.searchParams.set('tab', btn.dataset.tab);
				['portal_action', 'event_id', 'portal_success', 'portal_err', 'preset_type'].forEach(function (p) {
					url.searchParams.delete(p);
				});
				history.replaceState(null, '', url.toString());
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

function fge_portal_section_uebersicht( int $partner_id ): void {
	$base = fge_portal_page_url();

	fge_portal_render_hero( $partner_id );
	fge_portal_render_stats_row( $partner_id );
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
	<?php fge_portal_render_cat_grid( $partner_id, $base ); ?>

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
				Bewertungen kommen bald — Firmen können deine Events nach der Durchführung bewerten. Das Feature wird demnächst freigeschaltet.
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
	$archive_url    = get_post_type_archive_link( 'firmengolf_event' ) ?: home_url( '/firmenevents/' );
	$is_live        = in_array( $partner_status, [ 'aktiv', '' ], true );

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
		: fge_portal_format_partner_status( $partner_status );
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
					<a href="<?php echo esc_url( $archive_url ); ?>" class="fp-hero-btn" target="_blank" rel="noopener">
						<?php echo fge_icon_external(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						Öffentliches Profil
					</a>
					<a href="<?php echo esc_url( $base . '?tab=platz' ); ?>" class="fp-hero-btn solid">
						<?php echo fge_icon_edit_pencil(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						Profil bearbeiten
					</a>
				</div>
			</div>
			<div class="fp-hero-body">
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

	$published      = 0;
	$views          = 0;
	$requests_total = 0;
	$bookings       = 0;
	foreach ( $events as $eid ) {
		if ( get_post_meta( $eid, '_fge_event_status', true ) === 'freigegeben' ) {
			$published++;
		}
		$views          += (int) get_post_meta( $eid, '_fge_views_count', true );
		$requests_total += (int) get_post_meta( $eid, '_fge_requests_count', true );
		$bookings       += (int) get_post_meta( $eid, '_fge_bookings_count', true );
	}

	$base = fge_portal_page_url();
	?>
	<div class="fgpp"><div class="stats">
		<div class="stat">
			<div class="stat-lbl">Profilaufrufe</div>
			<div class="stat-val"><?php echo esc_html( number_format( $views, 0, ',', '.' ) ); ?></div>
			<div class="stat-foot">gesamt</div>
		</div>
		<div class="stat">
			<div class="stat-lbl">Anfragen</div>
			<div class="stat-val"><?php echo esc_html( $requests_total ); ?></div>
			<div class="stat-foot">gesamt</div>
		</div>
		<div class="stat">
			<div class="stat-lbl">Buchungen</div>
			<div class="stat-val"><?php echo esc_html( $bookings ); ?></div>
			<div class="stat-foot">gesamt</div>
		</div>
		<div class="stat">
			<div class="stat-lbl">Veröffentlicht</div>
			<div class="stat-val"><?php echo esc_html( $published ); ?></div>
			<div class="stat-foot"><a href="<?php echo esc_url( $base . '?tab=kennzahlen' ); ?>">Details →</a></div>
		</div>
	</div></div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// CATEGORY GRID
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_render_cat_grid( int $partner_id, string $base ): void {
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
		?>
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

	$group = ( $pmin > 0 && $pmax > 0 ) ? "{$pmin}–{$pmax}" : ( $pmax > 0 ? "bis {$pmax}" : ( $pmin > 0 ? "ab {$pmin}" : '' ) );
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
			<?php if ( $desc !== '' ) : ?><div class="cat-sub"><?php echo esc_html( wp_trim_words( $desc, 16, '…' ) ); ?></div><?php endif; ?>
			<div class="cat-stats">
				<?php if ( $duration !== '' ) : ?><span class="chip"><?php echo esc_html( $duration ); ?></span><?php endif; ?>
				<?php if ( $group !== '' ) : ?><span class="chip"><?php echo esc_html( $group ); ?> Pers.</span><?php endif; ?>
				<?php if ( $status === 'freigegeben' && $views > 0 ) : ?><span class="chip"><?php echo esc_html( number_format( $views, 0, ',', '.' ) ); ?> Aufrufe</span><?php endif; ?>
			</div>
			<div class="cat-foot">
				<div class="cat-price">
					<?php if ( $pr['gross'] > 0 ) : ?>
						<span class="from">ab</span>€<?php echo esc_html( number_format( $pr['gross'], 0, ',', '.' ) ); ?><span class="unit"><?php echo $pr['unit'] === 'pro Person' ? '/Pers.' : ''; ?></span>
					<?php else : ?>
						<span class="from">Preis</span>offen
					<?php endif; ?>
				</div>
				<div style="display:flex;gap:2px;align-items:center;">
					<?php if ( $lc ) : ?><a class="cat-edit" href="<?php echo esc_url( $lc['url'] ); ?>"><?php echo esc_html( $lc['label'] ); ?></a><?php endif; ?>
					<a class="cat-edit" href="<?php echo $edit_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>">Bearbeiten →</a>
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

	$status_labels = [
		'neu'                           => 'Neu',
		'eingangsbestaetigung_gesendet' => 'Bestätigt',
		'verfuegbarkeit_wird_geprueft'  => 'In Prüfung',
		'partner_angefragt'             => 'Angefragt',
		'vollstaendig_verfuegbar'       => 'Verfügbar',
		'nicht_verfuegbar'              => 'Nicht verfügbar',
		'angebot_versendet'             => 'Angebot versendet',
		'angebot_angenommen'            => 'Angenommen',
		'event_durchgefuehrt'           => 'Durchgeführt',
		'abgeschlossen'                 => 'Abgeschlossen',
		'verloren'                      => 'Verloren',
	];
	$st_label      = $status_labels[ $status ] ?? ( $status ?: 'Neu' );
	$is_new_status = $status === 'neu' || $status === '';
	?>
	<div class="fp-inbox-row">
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
			<span class="fp-pill<?php echo $is_new_status ? ' green' : ''; ?>">
				<?php if ( $is_new_status ) : ?><span class="dot"></span><?php endif; ?>
				<?php echo esc_html( $st_label ); ?>
			</span>
		</div>
	</div>
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
				<div class="panel" style="padding:0;">
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
		<?php fge_portal_render_cat_grid( $partner_id, $base ); ?>
	</div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: ANFRAGEN
// ══════════════════════════════════════════════════════════════════════════════

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
	return strtoupper( $ini ) ?: '–';
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
		[ $sid, $slabel ] = fge_portal_request_status( $r->ID );
		$rows[]           = [ 'post' => $r, 'sid' => $sid, 'slabel' => $slabel ];
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
						fge_portal_render_request_item( $row['post'], $row['sid'], $row['slabel'], $base );
					} ?>
				</div>
			<?php endif; ?>
		</section>
	</div></div>
	<?php
}

function fge_portal_render_request_item( WP_Post $r, string $sid, string $slabel, string $base ): void {
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
	<a class="req-item" href="<?php echo esc_url( $base . '?tab=anfragen&req=' . $id ); ?>">
		<span class="av green"><?php echo esc_html( fge_portal_initials( $company ) ); ?></span>
		<span class="ri-main">
			<span class="ri-co"><?php echo esc_html( $company ); ?></span>
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
	?>
	<div class="fgpp"><div class="page-wide">
		<p style="margin:24px 0 14px;"><a class="btn btn-ghost btn-sm" href="<?php echo esc_url( $base . '?tab=anfragen' ); ?>">← Alle Anfragen</a></p>
		<div class="req-detail">
			<div class="req-detail-head">
				<span class="req-no"><span class="req-no-hash">#<?php echo esc_html( $ref ); ?></span></span>
				<span class="spill s-<?php echo esc_attr( $sid ); ?>" style="margin-left:10px;"><?php echo esc_html( $slabel ); ?></span>
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
				<div class="req-facts">
					<div class="req-fact"><div class="l">Veranstaltungstyp</div><div class="v"><?php echo esc_html( $etype ); ?></div></div>
					<div class="req-fact"><div class="l">Teilnehmer</div><div class="v"><?php echo $pax ? esc_html( $pax . ' Personen' ) : '—'; ?></div></div>
					<div class="req-fact"><div class="l">Zeitfenster</div><div class="v"><?php echo esc_html( $slot ); ?></div></div>
					<div class="req-fact"><div class="l">Budget</div><div class="v"><?php echo esc_html( $budget ?: '—' ); ?></div></div>
				</div>
				<?php if ( '' !== $msg ) : ?>
					<div class="req-section-label">Nachricht</div>
					<div class="req-msg-block">„<?php echo esc_html( $msg ); ?>"</div>
				<?php endif; ?>

				<?php if ( ! empty( $wish ) && $total > 0 ) : ?>
					<div class="coord-head">
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
					<div class="req-section-label">Wunschtermine</div>
					<p style="font-size:13px;color:var(--ink-500);margin:-4px 0 12px;">Diese Anfrage gibst du selbst frei — bestätige den passenden Termin. (Du kannst im Tab <a href="<?php echo esc_url( $base . '?tab=team' ); ?>" style="color:var(--fairway-700);">Ansprechpartner</a> Personen mit „Terminabstimmung" hinterlegen, dann stimmen sie automatisch mit ab.)</p>
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
								<span class="btn btn-quiet btn-sm" title="Datum nicht eindeutig — kein Export" style="cursor:default;">kein .ics</span>
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
	$platz_edit = esc_url( add_query_arg( [ 'tab' => 'platz', 'edit' => '1' ], $base ) );

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
		return strtoupper( $ini ) ?: '–';
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
				<div class="panel" style="text-align:center;color:var(--ink-500);">Noch keine weiteren Ansprechpartner. Füge die erste Person hinzu — z. B. Gastronomie, Head Pro oder das Sekretariat.</div>
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
		$facts[] = [ 'Gruppengröße', trim( ( $cap['min'] ?? '?' ) . '–' . ( $cap['max'] ?? '?' ) . ' Personen' ) ];
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
						<div class="hero-status"><span class="dot"></span> <?php echo $status === 'pausiert' ? 'Pausiert — nicht öffentlich sichtbar' : 'Öffentlich sichtbar auf Firmengolf'; ?></div>
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
							$logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
							?>
							<?php if ( $logo_url !== '' ) : ?>
								<div class="hero-monogram hero-logo" style="background-image:url('<?php echo esc_url( $logo_url ); ?>')" role="img" aria-label="<?php echo esc_attr( $name ); ?> Logo"></div>
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

			<?php if ( $infra ) : ?>
			<section class="section">
				<div class="section-head">
					<div><div class="eyebrow">Ausstattung</div><h2>Was euch <em>erwartet</em></h2></div>
					<div class="actions"><a class="btn btn-ghost btn-sm" href="<?php echo $edit_sec( 'ausstattung' ); ?>">Bearbeiten</a></div>
				</div>
				<?php fge_render_amenities_grid( $partner_id ); ?>
				<?php $fge_extra_eq = $m( 'additional_equipment' ); ?>
				<?php if ( '' !== $fge_extra_eq ) : ?>
				<p class="fgpp-extra-equipment"><strong>Außerdem vor Ort:</strong> <?php echo esc_html( $fge_extra_eq ); ?></p>
				<?php endif; ?>
			</section>
			<?php endif; ?>

			<?php if ( $gallery ) : ?>
			<section class="section" id="galerie">
				<div class="section-head">
					<div><div class="eyebrow">Bildergalerie</div><h2>Fotos deines <em>Platzes</em></h2></div>
					<div class="actions"><a class="btn btn-brand btn-sm" href="<?php echo $edit_sec( 'medien' ); ?>">Fotos verwalten</a></div>
				</div>
				<div class="gallery-grid">
					<?php foreach ( $gallery as $gid ) :
						$gurl = (string) wp_get_attachment_image_url( $gid, 'large' );
						if ( ! $gurl ) { continue; } ?>
						<div class="gallery-item" style="background-image:url('<?php echo esc_url( $gurl ); ?>')"></div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<?php
			$plat = (float) get_post_meta( $partner_id, '_fge_latitude', true );
			$plng = (float) get_post_meta( $partner_id, '_fge_longitude', true );
			$paddr = trim( $m( 'street' ) . ' ' . $m( 'house_number' ) . ', ' . $m( 'postal_code' ) . ' ' . $m( 'city' ), ' ,' );
			$pmq  = ( $plat && $plng ) ? $plat . ',' . $plng : $paddr;
			if ( $pmq !== '' ) : ?>
			<section class="section" id="standort">
				<div class="section-head">
					<div><div class="eyebrow">Standort</div><h2>Wo ihr uns <em>findet</em></h2></div>
					<div class="actions"><a class="btn btn-ghost btn-sm" href="<?php echo $edit_sec( 'standort' ); ?>">Bearbeiten</a></div>
				</div>
				<div class="fgpp-map">
					<iframe src="https://www.google.com/maps?q=<?php echo rawurlencode( $pmq ); ?>&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Karte: <?php echo esc_attr( $name ); ?>"></iframe>
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
			</section>
			<?php endif; ?>

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
						<div class="panel-head"><h3 style="font-size:18px;">Ansprechpartner</h3></div>
						<div class="facts" style="background:var(--paper-200);">
							<div class="fact-row"><span class="lbl">Name</span><span class="val"><?php echo esc_html( $m( 'main_contact_name' ) ?: '—' ); ?></span></div>
							<div class="fact-row"><span class="lbl">E-Mail</span><span class="val"><?php echo esc_html( $m( 'main_contact_email' ) ?: '—' ); ?></span></div>
							<div class="fact-row"><span class="lbl">Telefon</span><span class="val"><?php echo esc_html( $m( 'main_contact_phone' ) ?: '—' ); ?></span></div>
						</div>
						<a class="btn btn-ghost btn-sm" style="margin-top:14px;" href="<?php echo $edit_sec( 'kontakt' ); ?>">Kontaktdaten bearbeiten</a>
					</div>
				</div>
			</section>

		</div>
	</div>
	<?php
}

function fge_portal_section_platz( int $partner_id ): void {
	$edit = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '1' === $edit ) { $edit = 'steckbrief'; } // back-compat with the old single edit form
	if ( in_array( $edit, [ 'steckbrief', 'ausstattung', 'standort', 'medien', 'kontakt' ], true ) ) {
		fge_portal_render_platz_edit_section( $partner_id, $edit );
		return;
	}
	fge_portal_render_platz_profile( $partner_id );
}

/** Focused per-section edit form for the portal "Platz". Saved by fge_portal_handle_profile_update(). */
function fge_portal_render_platz_edit_section( int $partner_id, string $section ): void {
	$m    = static fn( string $k ): string => (string) get_post_meta( $partner_id, '_fge_' . $k, true );
	$base = fge_portal_page_url();
	$back = esc_url( add_query_arg( [ 'tab' => 'platz' ], $base ) );
	$titles = [
		'steckbrief'  => 'Über den Platz',
		'ausstattung' => 'Ausstattung',
		'standort'    => 'Standort',
		'medien'      => 'Fotos & Logo',
		'kontakt'     => 'Ansprechpartner',
	];
	$title = $titles[ $section ] ?? 'Bearbeiten';
	?>
	<div style="padding-top:32px;">
		<a href="<?php echo $back; ?>" class="fg-btn fg-btn-outline" style="margin-bottom:18px;">← Zurück zum Platz</a>
		<div class="fp-section-head"><div><div class="fp-eyebrow">Platz bearbeiten</div><h2><?php echo esc_html( $title ); ?></h2></div></div>

		<form method="post" action="<?php echo esc_url( $base ); ?>" enctype="multipart/form-data" class="fp-platz-editform">
			<input type="hidden" name="fge_action" value="portal_profile_update">
			<input type="hidden" name="fge_platz_section" value="<?php echo esc_attr( $section ); ?>">
			<?php wp_nonce_field( 'fge_portal_profile_update', 'fge_portal_nonce' ); ?>

			<div class="fp-form-sec">
			<?php
			switch ( $section ) {
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
						<textarea class="fg-form-textarea" id="fge_public_short_description" name="fge_public_short_description" rows="4" placeholder="2–3 Sätze für dein öffentliches Profil"><?php echo esc_textarea( $m( 'public_short_description' ) ); ?></textarea>
					</div>
					<div class="fg-form-row">
						<label class="fg-form-label" for="fge_golf_type">Platztyp</label>
						<select class="fg-form-input" id="fge_golf_type" name="fge_golf_type">
							<option value="">— bitte wählen —</option>
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
					?>
					<p class="fp-help">Wird intern für Verfügbarkeitsanfragen genutzt.</p>
					<div class="fg-form-row fg-form-row--3col">
						<div><label class="fg-form-label" for="fge_event_contact_name">Name</label><input class="fg-form-input" type="text" id="fge_event_contact_name" name="fge_event_contact_name" value="<?php echo esc_attr( $m( 'event_contact_name' ) ); ?>"></div>
						<div><label class="fg-form-label" for="fge_event_contact_email">E-Mail</label><input class="fg-form-input" type="email" id="fge_event_contact_email" name="fge_event_contact_email" value="<?php echo esc_attr( $m( 'event_contact_email' ) ); ?>"></div>
						<div><label class="fg-form-label" for="fge_event_contact_phone">Telefon</label><input class="fg-form-input" type="tel" id="fge_event_contact_phone" name="fge_event_contact_phone" value="<?php echo esc_attr( $m( 'event_contact_phone' ) ); ?>"></div>
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

			<div style="margin-top:24px;">
				<button type="submit" class="fp-btn fp-btn-brand"><?php echo esc_html( $title ); ?> speichern <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</div>
		</form>
	</div>
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
						$ev_cr = $ev_v > 0 ? round( $ev_r / $ev_v * 100, 1 ) . '%' : '—';
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
						$pmode   = $event_id ? ( get_post_meta( $event_id, '_fge_price_mode', true ) ?: 'gesamt' ) : 'gesamt';
						$pamount = $event_id ? get_post_meta( $event_id, '_fge_price_amount', true ) : '';
						$pbasis  = $event_id ? ( get_post_meta( $event_id, '_fge_price_basis', true ) ?: 'person' ) : 'person';
						$pitems  = $event_id ? (array) get_post_meta( $event_id, '_fge_line_items', true ) : [];
						$markup  = defined( 'FGE_MARKUP_PERCENT' ) ? (int) FGE_MARKUP_PERCENT : 20;
						?>
						<p class="fp-help">Hinterlege deinen <strong>Netto</strong>-Preis. Die Vermittlung von Firmengolf (<?php echo (int) $markup; ?> %) kommt automatisch oben drauf.</p>

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
									<button type="button" class="x" data-fp-item-remove aria-label="Entfernen">×</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="fp-btn fp-btn-ghost fp-btn-sm" id="fp-price-additem">+ Kosten hinzufügen</button>
							<textarea name="fge_line_items" id="fge_line_items" hidden><?php echo esc_textarea( implode( "\n", array_map( static fn( $i ): string => ( $i['label'] ?? '' ) . ' | ' . ( $i['cost'] ?? '' ), $pitems ) ) ); ?></textarea>
						</div>

						<div class="fp-price-summary" id="fp-price-summary" data-markup="<?php echo (int) $markup; ?>">
							<div class="row"><span>Netto-Summe</span><span class="v" id="fp-sum-net">€0</span></div>
							<div class="row"><span>+ Vermittlung Firmengolf (<?php echo (int) $markup; ?> %)</span><span class="v" id="fp-sum-fee">€0</span></div>
							<div class="row total"><span>Gesamtpreis für das Unternehmen</span><span class="v" id="fp-sum-total">€0</span></div>
						</div>
						<div class="fg-form-row fg-form-row--3col">
							<div>
								<label class="fg-form-label" for="fge_duration">Dauer</label>
								<input class="fg-form-input" type="text" id="fge_duration" name="fge_duration" value="<?php echo $v( 'fge_duration' ); ?>" placeholder="z.B. 4 Stunden">
							</div>
							<div>
								<label class="fg-form-label" for="fge_participants_min">Min. Teilnehmer</label>
								<input class="fg-form-input" type="number" id="fge_participants_min" name="fge_participants_min" value="<?php echo esc_attr( $saved['fge_participants_min'] ?: '' ); ?>" min="1" placeholder="z.B. 8">
							</div>
							<div>
								<label class="fg-form-label" for="fge_participants_max">Max. Teilnehmer</label>
								<?php
								$platz_cap = (array) get_post_meta( $partner_id, '_fge_cap', true );
								$platz_max = (int) ( $platz_cap['max'] ?? 0 );
								// Neues Event: mit dem Platz-Maximum vorbelegen.
								$pmax_val = $saved['fge_participants_max'] ?: ( ( ! $is_edit && $platz_max > 0 ) ? (string) $platz_max : '' );
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
								'coach'           => 'PGA-Coaching',
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

						$cur_includes = $event_id ? array_map( 'strval', (array) get_post_meta( $event_id, '_fge_event_includes', true ) ) : [];
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
							<label class="fg-form-label" for="fge_event_dayflow">So läuft der Tag ab</label>
							<textarea class="fg-form-textarea" id="fge_event_dayflow" name="fge_event_dayflow" rows="9" placeholder="Wir holen euch um 9:00 Uhr direkt in eurer Firma ab.&#10;&#10;Treffpunkt ist der Pro-Shop. Dort begrüßen wir euch und stellen euch Platz und Anlage kurz vor.&#10;&#10;9:30 Uhr: Erster Teil des Schnupperkurses auf der Range (ca. 1 Stunde) mit unseren PGA-Pros.&#10;&#10;Mittags: gemeinsames Lunch auf der Terrasse.&#10;&#10;Am Nachmittag geht es aufs Grün. Zum Abschluss bringt euch unser Shuttle bequem zurück."><?php echo esc_textarea( $event_id ? (string) get_post_meta( $event_id, '_fge_event_dayflow', true ) : '' ); ?></textarea>
							<p class="fp-help">Beschreib den Ablauf Schritt für Schritt, von der Ankunft bis zur Heimfahrt. Dieser Text erscheint auf der Event-Seite.</p>
						</div>
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
						$fge_release  = $event_id ? ( (string) get_post_meta( $event_id, '_fge_release_mode', true ) ?: 'us' ) : 'us';
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
						<a href="<?php echo esc_url( $lc_url ); ?>" class="fp-btn fp-btn-ghost fp-btn-sm" style="margin-top:14px;display:inline-flex;"><?php echo esc_html( $lc_label ); ?></a>
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
						<article class="fg-event ev-card2 fp-preview-card">
							<div class="fg-event-photo" id="fp-pv-photo" style="<?php echo $pv_cover ? "background-image:url('" . esc_url( $pv_cover ) . "');" : 'background:var(--paper-300);'; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
								<div class="fg-event-chips"><span class="fg-photo-chip" id="fp-pv-chip"><?php echo esc_html( $event_type_label ?: 'Eventart' ); ?></span></div>
							</div>
							<div class="fg-event-body">
								<div class="ev-card2-top">
									<div class="fg-event-eyebrow" id="fp-pv-eyebrow"><?php echo esc_html( $event_type_label ?: 'Eventart' ); ?></div>
									<?php if ( $pv_rating ) : ?><div class="fg-event-rating">★ <span><?php echo esc_html( $pv_rating ); ?></span></div><?php endif; ?>
								</div>
								<h3 class="fg-event-title" id="fp-pv-title"><?php echo esc_html( $event_title ?: 'Titel deines Angebots' ); ?></h3>
								<div class="ev-card2-loc">
									<span><?php echo esc_html( $pv_name ); ?></span>
									<span class="dot">·</span><span id="fp-pv-guests">Teilnehmer</span>
									<span class="dot">·</span><span id="fp-pv-duration">Dauer</span>
								</div>
								<div class="fg-event-foot ev-card2-foot">
									<div class="fg-event-price" id="fp-pv-price">Preis</div>
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

				</div><!-- /rail -->

			</div><!-- .fp-edit-grid -->

			<div class="fp-edit-actionbar">
				<span style="font-size:13px;color:var(--ink-500);display:inline-flex;align-items:center;gap:8px;">
					<span class="fp-unsaved-dot" style="width:7px;height:7px;border-radius:50%;background:var(--warning);flex:none;display:inline-block;"></span>
					<?php echo $is_edit ? 'Änderungen noch nicht gespeichert' : 'Noch nicht eingereicht'; ?>
				</span>
				<button type="submit" class="fp-btn fp-btn-brand">
					<?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php echo $is_edit ? 'Änderungen speichern' : 'Event einreichen'; ?>
				</button>
			</div>

			<script>
			(function () {
				var byId = function (id) { return document.getElementById(id); };
				var fmt  = function (n) { return '€' + (Math.round(n * 100) / 100).toLocaleString('de-DE'); };

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

				function parseNum(v) { return parseFloat(String(v || '').replace(/[^\d.,]/g, '').replace(',', '.')) || 0; }
				function netSum() {
					if (modeIn && modeIn.value === 'einzel' && items) {
						var s = 0;
						items.querySelectorAll('[data-fp-item-cost]').forEach(function (i) { s += parseNum(i.value); });
						return s;
					}
					return amount ? parseNum(amount.value) : 0;
				}
				function syncItems() {
					if (!items || !itemsTa) { return; }
					var lines = [];
					items.querySelectorAll('.fp-price-item').forEach(function (row) {
						var l = row.querySelector('[data-fp-item-label]');
						var c = row.querySelector('[data-fp-item-cost]');
						if (l && l.value.trim() !== '') { lines.push(l.value.trim() + ' | ' + (c ? c.value.trim() : '')); }
					});
					itemsTa.value = lines.join('\n');
				}
				function recalc() {
					if (!summary) { return; }
					var net = netSum();
					var perPerson = modeIn && modeIn.value === 'gesamt' && basisIn && basisIn.value === 'person';
					byId('fp-sum-net').textContent   = fmt(net) + (perPerson ? ' pro Person' : '');
					byId('fp-sum-fee').textContent   = fmt(net * markup / 100);
					byId('fp-sum-total').textContent = fmt(net * (1 + markup / 100)) + (perPerson ? ' pro Person' : '');
					var pv = byId('fp-pv-price');
					if (pv) { pv.textContent = net > 0 ? 'ab ' + fmt(net * (1 + markup / 100)) + (perPerson ? ' /p.P.' : '') : 'Preis'; }
				}
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
				function addItemRow(label, cost) {
					var row = document.createElement('div');
					row.className = 'fp-price-item';
					row.innerHTML = '<input class="fg-form-input" type="text" placeholder="Bezeichnung (z. B. Golflehrer)" data-fp-item-label>' +
						'<input class="fg-form-input" type="text" inputmode="decimal" placeholder="80" data-fp-item-cost>' +
						'<button type="button" class="x" data-fp-item-remove aria-label="Entfernen">×</button>';
					row.querySelector('[data-fp-item-label]').value = label || '';
					row.querySelector('[data-fp-item-cost]').value  = cost || '';
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
				function openSvc()  { if (overlay) { overlay.hidden = false; document.body.style.overflow = 'hidden'; } }
				function closeSvc() { if (overlay) { overlay.hidden = true; document.body.style.overflow = ''; } }
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
					var pvE = byId('fp-pv-eyebrow');
					if (pvE) { pvE.textContent = typeLabel + ( dur && dur.value.trim() ? ' · ' + dur.value.trim() : '' ); }
					var pvD = byId('fp-pv-duration');
					if (pvD) { pvD.textContent = ( dur && dur.value.trim() ) ? dur.value.trim() : 'Dauer'; }
					var pvG = byId('fp-pv-guests');
					if (pvG) {
						pvG.textContent = ( minP && maxP && minP.value && maxP.value )
							? minP.value + '–' + maxP.value + ' Gäste'
							: ( maxP && maxP.value ? 'bis ' + maxP.value + ' Gäste' : 'Teilnehmer' );
					}
				}
				['fge_post_title', 'fge_duration', 'fge_participants_min', 'fge_participants_max'].forEach(function (id) {
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

function fge_portal_render_event_leistungen_block( callable $checked, string $additional = '' ): void {
	$labels = [
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
	<div class="fg-form-block">
		<p class="fg-form-block-title">Enthaltene Leistungen</p>
		<div class="fg-form-checkgrid">
			<?php foreach ( $labels as $key => $label ) : ?>
				<label class="fg-form-check">
					<input type="checkbox" name="fge_<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $checked( 'fge_' . $key ) ); ?>>
					<span><?php echo esc_html( $label ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<div class="fg-form-row" style="margin-top:16px;">
			<label class="fg-form-label" for="fge_additional_services">Weitere Leistungen</label>
			<div class="fg-form-field">
				<textarea class="fg-form-textarea" id="fge_additional_services" name="fge_additional_services" rows="2" placeholder="Sonstige enthaltene Leistungen"><?php echo esc_textarea( $additional ); ?></textarea>
			</div>
		</div>
	</div>
	<?php
}
