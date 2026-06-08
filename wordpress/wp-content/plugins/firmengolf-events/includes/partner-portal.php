<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// FORM HANDLERS
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'init', 'fge_portal_handle_new_event', 10 );
add_action( 'init', 'fge_portal_handle_edit_event', 10 );
add_action( 'init', 'fge_portal_handle_profile_update', 10 );

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

	if ( ! empty( $_FILES['fge_event_cover']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$cover_id = media_handle_upload( 'fge_event_cover', $post_id );
		if ( ! is_wp_error( $cover_id ) ) {
			update_post_meta( $post_id, '_fge_cover_attachment_id', $cover_id );
		}
	}

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

	wp_update_post( [
		'ID'           => $event_id,
		'post_title'   => sanitize_text_field( wp_unslash( $_POST['fge_post_title'] ?? '' ) ),
		'post_content' => wp_kses_post( wp_unslash( $_POST['fge_post_content'] ?? '' ) ),
	] );

	$current_status = (string) get_post_meta( $event_id, '_fge_event_status', true );
	update_post_meta( $event_id, '_fge_event_status', $current_status === 'freigegeben' ? 'aenderung_in_pruefung' : 'zur_pruefung' );
	fge_portal_save_event_meta( $event_id );

	if ( ! empty( $_FILES['fge_event_cover']['name'] ) ) {
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$cover_id = media_handle_upload( 'fge_event_cover', $event_id );
		if ( ! is_wp_error( $cover_id ) ) {
			update_post_meta( $event_id, '_fge_cover_attachment_id', $cover_id );
		}
	}

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

	// Text fields.
	update_post_meta( $partner_id, '_fge_public_golfclub_name',    sanitize_text_field( wp_unslash( $_POST['fge_public_golfclub_name'] ?? '' ) ) );
	update_post_meta( $partner_id, '_fge_city',                    sanitize_text_field( wp_unslash( $_POST['fge_city'] ?? '' ) ) );
	update_post_meta( $partner_id, '_fge_federal_state',           sanitize_text_field( wp_unslash( $_POST['fge_federal_state'] ?? '' ) ) );
	update_post_meta( $partner_id, '_fge_website_url',             esc_url_raw( wp_unslash( $_POST['fge_website_url'] ?? '' ) ) );
	update_post_meta( $partner_id, '_fge_free_region',             sanitize_text_field( wp_unslash( $_POST['fge_free_region'] ?? '' ) ) );
	update_post_meta( $partner_id, '_fge_public_short_description', sanitize_textarea_field( wp_unslash( $_POST['fge_public_short_description'] ?? '' ) ) );
	update_post_meta( $partner_id, '_fge_event_contact_name',      sanitize_text_field( wp_unslash( $_POST['fge_event_contact_name'] ?? '' ) ) );
	update_post_meta( $partner_id, '_fge_event_contact_email',     sanitize_email( wp_unslash( $_POST['fge_event_contact_email'] ?? '' ) ) );
	update_post_meta( $partner_id, '_fge_event_contact_phone',     sanitize_text_field( wp_unslash( $_POST['fge_event_contact_phone'] ?? '' ) ) );

	// Media uploads.
	if ( ! empty( $_FILES['fge_partner_logo']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$logo_id = media_handle_upload( 'fge_partner_logo', $partner_id );
		if ( ! is_wp_error( $logo_id ) ) {
			update_post_meta( $partner_id, '_fge_logo_attachment_id', $logo_id );
		}
	}
	if ( ! empty( $_FILES['fge_partner_cover']['name'] ) ) {
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$cover_id = media_handle_upload( 'fge_partner_cover', $partner_id );
		if ( ! is_wp_error( $cover_id ) ) {
			update_post_meta( $partner_id, '_fge_hero_image_attachment_id', $cover_id );
		}
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
	update_post_meta( $post_id, '_fge_season',           sanitize_text_field( wp_unslash( $_POST['fge_season'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_region',           sanitize_text_field( wp_unslash( $_POST['fge_region'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_event_location',   sanitize_text_field( wp_unslash( $_POST['fge_event_location'] ?? '' ) ) );

	$raw_days   = array_map( 'sanitize_text_field', (array) ( $_POST['fge_available_weekdays'] ?? [] ) );
	$clean_days = array_values( array_filter( $raw_days, static function( $v ) use ( $allowed_weekdays ) {
		return in_array( $v, $allowed_weekdays, true );
	} ) );
	update_post_meta( $post_id, '_fge_available_weekdays', $clean_days );

	$leistungen = [
		'has_golf_teacher', 'has_range_usage', 'has_rental_clubs', 'has_range_balls',
		'has_putting_shortgame', 'has_meeting_room', 'has_breakfast', 'has_lunch',
		'has_dinner', 'has_shuttle', 'has_branding',
	];
	foreach ( $leistungen as $key ) {
		update_post_meta( $post_id, '_fge_' . $key, isset( $_POST[ 'fge_' . $key ] ) ? '1' : '0' );
	}

	update_post_meta( $post_id, '_fge_additional_services',         sanitize_textarea_field( wp_unslash( $_POST['fge_additional_services'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_public_price_label',          sanitize_text_field( wp_unslash( $_POST['fge_public_price_label'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_price_note',                  sanitize_textarea_field( wp_unslash( $_POST['fge_price_note'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_availability_contact_name',   sanitize_text_field( wp_unslash( $_POST['fge_availability_contact_name'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_availability_contact_email',  sanitize_email( wp_unslash( $_POST['fge_availability_contact_email'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_availability_contact_phone',  sanitize_text_field( wp_unslash( $_POST['fge_availability_contact_phone'] ?? '' ) ) );
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
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div id="fp-tab-uebersicht"  class="fp-section<?php echo $active_tab !== 'uebersicht'  ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_uebersicht( $partner_id ); ?></div>
		<div id="fp-tab-angebote"    class="fp-section<?php echo $active_tab !== 'angebote'    ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_angebote( $partner_id ); ?></div>
		<div id="fp-tab-anfragen"    class="fp-section<?php echo $active_tab !== 'anfragen'    ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_requests( $partner_id ); ?></div>
		<div id="fp-tab-kalender"    class="fp-section<?php echo $active_tab !== 'kalender'    ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_kalender(); ?></div>
		<div id="fp-tab-platz"       class="fp-section<?php echo $active_tab !== 'platz'       ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_platz( $partner_id ); ?></div>
		<div id="fp-tab-team"        class="fp-section<?php echo $active_tab !== 'team'        ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_team( $partner_id ); ?></div>
		<div id="fp-tab-kennzahlen"  class="fp-section<?php echo $active_tab !== 'kennzahlen'  ? ' fp-section--hidden' : ''; ?>"><?php fge_portal_section_stats( $partner_id ); ?></div>

		<div class="fgpp"><footer class="foot">
			<div>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Firmengolf · Partner-Portal</div>
			<div class="links"><a href="mailto:<?php echo esc_attr( fge_company()['email_partner'] ); ?>">Support</a></div>
		</footer></div>

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

	<div class="fp-section-block">
		<div class="fp-section-head">
			<div>
				<div class="fp-eyebrow">Pro Eventformat ein Angebot</div>
				<h2>Deine <em>Event-Angebote</em></h2>
				<p>Jede Kategorie bekommt eigene Konditionen und eigene Beschreibung.</p>
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

	<?php fge_portal_render_anfragen_preview( $partner_id ); ?>

	<div class="fp-section-block">
		<div class="fp-section-head">
			<div>
				<div class="fp-eyebrow">Was Firmen sagen</div>
				<h2>Bewertungen</h2>
			</div>
		</div>
		<div class="fp-placeholder">
			<div class="fp-placeholder-icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
			</div>
			<div class="fp-placeholder-title">Bewertungen kommen bald</div>
			<div class="fp-placeholder-sub">Firmen können deine Events nach dem Event bewerten. Das Feature wird demnächst freigeschaltet.</div>
		</div>
	</div>
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
		: fge_get_placeholder_image_url( 'hero-fairway-wide.jpg' );

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
	<div class="fp-stats">
		<div class="fp-stat">
			<div class="fp-stat-lbl">Profilaufrufe</div>
			<div class="fp-stat-val"><?php echo esc_html( number_format( $views, 0, ',', '.' ) ); ?></div>
			<div class="fp-stat-foot">gesamt</div>
		</div>
		<div class="fp-stat">
			<div class="fp-stat-lbl">Anfragen</div>
			<div class="fp-stat-val"><?php echo esc_html( $requests_total ); ?></div>
			<div class="fp-stat-foot">gesamt</div>
		</div>
		<div class="fp-stat">
			<div class="fp-stat-lbl">Buchungen</div>
			<div class="fp-stat-val"><?php echo esc_html( $bookings ); ?></div>
			<div class="fp-stat-foot">gesamt</div>
		</div>
		<div class="fp-stat">
			<div class="fp-stat-lbl">Veröffentlicht</div>
			<div class="fp-stat-val"><?php echo esc_html( $published ); ?></div>
			<div class="fp-stat-foot"><a href="<?php echo esc_url( $base . '?tab=kennzahlen' ); ?>">Details →</a></div>
		</div>
	</div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// CATEGORY GRID
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_render_cat_grid( int $partner_id, string $base ): void {
	$types = fge_get_event_formats()['standard'];
	?>
	<div class="fp-cat-grid">
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
				<a href="<?php echo $new_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>" class="fp-cat is-empty">
					<div class="fp-empty-icon">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14m-7-7h14"/></svg>
					</div>
					<span class="fp-cat-chip"><?php echo esc_html( $type_label ); ?></span>
					<div class="fp-cat-title">Noch kein Angebot</div>
					<div class="fp-cat-sub">Lade ein Angebot hoch, damit Firmen dich in dieser Kategorie finden.</div>
					<span class="fp-btn fp-btn-brand fp-btn-sm">+ Angebot erstellen</span>
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
	</div>
	<?php
}

function fge_portal_render_cat_card( WP_Post $event, string $type_label, string $base, int $idx = 0 ): void {
	$event_id = $event->ID;
	$status   = (string) get_post_meta( $event_id, '_fge_event_status', true );
	$desc     = (string) get_post_meta( $event_id, '_fge_card_description', true );
	$duration = (string) get_post_meta( $event_id, '_fge_duration', true );
	$pmin     = (int)    get_post_meta( $event_id, '_fge_participants_min', true );
	$pmax     = (int)    get_post_meta( $event_id, '_fge_participants_max', true );
	$price    = (string) get_post_meta( $event_id, '_fge_public_price_label', true );
	$views    = (int)    get_post_meta( $event_id, '_fge_views_count', true );

	$cover_id    = (int) get_post_meta( $event_id, '_fge_cover_attachment_id', true );
	$placeholder = $cover_id > 0
		? (string) wp_get_attachment_image_url( $cover_id, 'large' )
		: fge_get_placeholder_image_url( 'hero-fairway-wide.jpg' );
	$edit_url    = esc_url( $base . '?tab=angebote&portal_action=edit&event_id=' . $event_id );

	$ampel_map = [
		'freigegeben'           => [ 'class' => 'published', 'label' => 'Veröffentlicht' ],
		'zur_pruefung'          => [ 'class' => 'draft',     'label' => 'In Prüfung' ],
		'aenderung_in_pruefung' => [ 'class' => 'draft',     'label' => 'In Prüfung' ],
		'entwurf'               => [ 'class' => 'draft',     'label' => 'Entwurf' ],
		'pausiert'              => [ 'class' => 'paused',    'label' => 'Pausiert' ],
		'abgelehnt'             => [ 'class' => 'rejected',  'label' => 'Abgelehnt' ],
	];
	$ampel = $ampel_map[ $status ] ?? [ 'class' => 'draft', 'label' => 'Entwurf' ];

	$group = '';
	if ( $pmin > 0 && $pmax > 0 ) {
		$group = "{$pmin}–{$pmax}";
	} elseif ( $pmax > 0 ) {
		$group = "bis {$pmax}";
	} elseif ( $pmin > 0 ) {
		$group = "ab {$pmin}";
	}
	?>
	<a href="<?php echo $edit_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>" class="fp-cat">
		<div class="fp-cat-photo" style="background-image: url('<?php echo esc_url( $placeholder ); ?>')">
			<span class="fp-cat-chip"><?php echo esc_html( $type_label ); ?></span>
			<span class="fp-cat-status <?php echo esc_attr( $ampel['class'] ); ?>">
				<span class="dot"></span>
				<?php echo esc_html( $ampel['label'] ); ?>
			</span>
		</div>
		<div class="fp-cat-body">
			<div class="fp-cat-title"><?php echo esc_html( $event->post_title ); ?></div>
			<?php if ( $desc !== '' ) : ?>
				<div class="fp-cat-sub"><?php echo esc_html( wp_trim_words( $desc, 15, '…' ) ); ?></div>
			<?php endif; ?>
			<div class="fp-cat-stats">
				<?php if ( $duration !== '' ) : ?>
					<span class="fp-chip"><?php echo fge_icon_clock(); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $duration ); ?></span>
				<?php endif; ?>
				<?php if ( $group !== '' ) : ?>
					<span class="fp-chip"><?php echo fge_icon_users(); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $group ); ?> Pers.</span>
				<?php endif; ?>
				<?php if ( $status === 'freigegeben' && $views > 0 ) : ?>
					<span class="fp-chip"><?php echo fge_icon_eye(); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( number_format( $views, 0, ',', '.' ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="fp-cat-foot">
				<div class="fp-cat-price">
					<?php if ( $price !== '' ) : ?>
						<?php echo esc_html( $price ); ?>
					<?php else : ?>
						<span class="fp-cat-price-empty">Kein Preislabel</span>
					<?php endif; ?>
				</div>
				<span class="fp-cat-edit">Bearbeiten <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</div>
		</div>
	</a>
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
			<?php if ( function_exists( 'fge_sched_state' ) && get_post_meta( $req->ID, '_fge_sched_token_platz', true ) ) :
				$sched   = fge_sched_state( $req->ID );
				$plabels = fge_sched_parties();
				$pcolors = [ 'pending' => '#C58A1D', 'zugesagt' => '#2F6E45', 'abgesagt' => '#B4332B', 'alternative' => '#3F6E8A' ];
			?>
				<div class="fp-inbox-sched" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">
					<?php foreach ( $sched['by_party'] as $p => $ps ) : ?>
						<span style="font-size:11px;padding:2px 8px;border-radius:10px;color:#fff;background:<?php echo esc_attr( $pcolors[ $ps ] ?? '#666' ); ?>;"><?php echo esc_html( $plabels[ $p ] . ': ' . $ps ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
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
	<div class="fp-section-block">
		<div class="fp-section-head">
			<div>
				<div class="fp-eyebrow">Letzte Aktivität</div>
				<h2>Inbox & <em>Anfragen</em></h2>
			</div>
			<div class="fp-actions">
				<a href="<?php echo esc_url( $base . '?tab=anfragen' ); ?>" class="fp-btn fp-btn-ghost">
					Alle ansehen <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
				</a>
			</div>
		</div>

		<?php if ( empty( $requests ) ) : ?>
			<div class="fp-panel" style="color: var(--ink-500); font-size: 14px; padding: 28px 26px;">
				Noch keine Anfragen eingegangen.
			</div>
		<?php else : ?>
			<div class="fp-panel">
				<div class="fp-inbox-list">
					<?php foreach ( $requests as $i => $req ) : ?>
						<?php fge_portal_render_inbox_row( $req, $i ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
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
	$requests = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'orderby'     => 'date',
		'order'       => 'DESC',
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );
	?>
	<div style="padding-top:32px;">
		<div class="fp-section-head">
			<div>
				<div class="fp-eyebrow">Eingegangene Anfragen</div>
				<h2>Meine <em>Anfragen</em></h2>
			</div>
		</div>

		<?php if ( empty( $requests ) ) : ?>
			<div class="fp-placeholder">
				<div class="fp-placeholder-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
				</div>
				<div class="fp-placeholder-title">Noch keine Anfragen</div>
				<div class="fp-placeholder-sub">Sobald Firmen eine Anfrage stellen, erscheint sie hier.</div>
			</div>
		<?php else : ?>
			<div class="fp-panel">
				<div class="fp-inbox-list">
					<?php foreach ( $requests as $i => $req ) : ?>
						<?php fge_portal_render_inbox_row( $req, $i ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: KALENDER (PLACEHOLDER)
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_section_kalender(): void {
	?>
	<div style="padding-top:32px;">
		<div class="fp-section-head">
			<div>
				<div class="fp-eyebrow">Buchungen und Termine</div>
				<h2>Kalender</h2>
			</div>
		</div>
		<div class="fp-placeholder">
			<div class="fp-placeholder-icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
			</div>
			<div class="fp-placeholder-title">Kalender kommt bald</div>
			<div class="fp-placeholder-sub">Du wirst deine Buchungen und Verfügbarkeiten direkt im Portal verwalten können.</div>
		</div>
	</div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: MEIN PLATZ (umbenannt von Profil)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Ansprechpartner/Team — Platzhalter (eigene Etappe: fge_partner_contacts-Tabelle).
 */
function fge_portal_section_team( int $partner_id ): void {
	$edit = esc_url( add_query_arg( [ 'tab' => 'platz', 'edit' => '1' ], fge_portal_page_url() ) );
	?>
	<div class="fgpp"><div class="page-wide">
		<section class="section">
			<div class="section-head">
				<div>
					<div class="eyebrow">Team</div>
					<h2>Eure <em>Ansprechpartner</em></h2>
					<p>Mehrere Kontakte je Platz (z.B. Geschäftsführung, Head Pro, Gastronomie) — sie speisen später die Termin-Freigabe bei Anfragen.</p>
				</div>
			</div>
			<div class="panel">
				<p style="font-size:15px;color:var(--ink-500);line-height:1.6;">Die Ansprechpartner-Verwaltung wird gerade gebaut. Bis dahin pflegst du den Hauptkontakt im Tab <a href="<?php echo $edit; ?>" style="color:var(--fairway-700);">Platz → Bearbeiten</a>.</p>
			</div>
		</section>
	</div></div>
	<?php
}

/**
 * Platz-Profil — neue Anzeige-View (Design rev. 2, gekapselt unter .fgpp).
 * Liest die echten Partner-Daten inkl. Katalog-Modell (golf_type, infra, cap).
 */
function fge_portal_render_platz_profile( int $partner_id ): void {
	$m    = static fn( string $k ): string => (string) get_post_meta( $partner_id, '_fge_' . $k, true );
	$base = fge_portal_page_url();
	$edit = esc_url( add_query_arg( [ 'tab' => 'platz', 'edit' => '1' ], $base ) );

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
	$cover      = $cover_id > 0 ? (string) wp_get_attachment_image_url( $cover_id, 'large' ) : fge_get_placeholder_image_url( 'hero-fairway-wide.jpg' );
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
							<a class="hero-btn solid" href="<?php echo $edit; ?>">Profil bearbeiten</a>
						</div>
					</div>
					<div class="hero-body">
						<div class="hero-id">
							<div class="hero-monogram"><?php echo esc_html( $mono ); ?></div>
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
					<div class="actions"><a class="btn btn-ghost btn-sm" href="<?php echo $edit; ?>">Bearbeiten</a></div>
				</div>
				<div class="amenities">
					<?php foreach ( $infra as $id ) :
						$it = $infra_index[ $id ] ?? null;
						if ( ! $it ) { continue; } ?>
						<div class="amenity">
							<span class="ic-wrap">✓</span>
							<div>
								<div class="l"><?php echo esc_html( $it['label'] ); ?></div>
								<div class="s"><?php echo esc_html( $it['group'] ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $gallery ) : ?>
			<section class="section" id="galerie">
				<div class="section-head">
					<div><div class="eyebrow">Bildergalerie</div><h2>Fotos deines <em>Platzes</em></h2></div>
					<div class="actions"><a class="btn btn-brand btn-sm" href="<?php echo $edit; ?>">Fotos verwalten</a></div>
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
						<a class="btn btn-ghost btn-sm" style="margin-top:14px;" href="<?php echo $edit; ?>">Kontaktdaten bearbeiten</a>
					</div>
				</div>
			</section>

		</div>
	</div>
	<?php
}

function fge_portal_section_platz( int $partner_id ): void {
	// Standard: neue Profil-Anzeige. Mit ?edit=1: bestehendes Bearbeiten-Formular.
	if ( ! isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		fge_portal_render_platz_profile( $partner_id );
		return;
	}
	$m    = static function( string $key ) use ( $partner_id ): string {
		return (string) get_post_meta( $partner_id, '_fge_' . $key, true );
	};
	$base = fge_portal_page_url();

	$logo_id  = (int) get_post_meta( $partner_id, '_fge_logo_attachment_id', true );
	$cover_id = (int) get_post_meta( $partner_id, '_fge_hero_image_attachment_id', true );
	$logo_url = $logo_id  > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
	$cov_url  = $cover_id > 0 ? (string) wp_get_attachment_image_url( $cover_id, 'large' ) : '';
	?>
	<div style="padding-top:32px;">
		<div class="fp-section-head">
			<div>
				<div class="fp-eyebrow">Dein Profil</div>
				<h2>Mein <em>Platz</em></h2>
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( $base ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="fge_action" value="portal_profile_update">
			<?php wp_nonce_field( 'fge_portal_profile_update', 'fge_portal_nonce' ); ?>

			<div class="fp-profile-grid">

				<!-- ─ Left: text fields ─ -->
				<div>
					<div class="fp-form-sec">
						<h3>Golfplatz</h3>
						<p class="fp-help">Diese Informationen erscheinen auf deinem öffentlichen Firmengolf-Profil.</p>

						<div class="fg-form-row">
							<label class="fg-form-label" for="fge_public_golfclub_name">Öffentlicher Name</label>
							<input class="fg-form-input" type="text" id="fge_public_golfclub_name" name="fge_public_golfclub_name" value="<?php echo esc_attr( $m('public_golfclub_name') ); ?>">
						</div>
						<div class="fg-form-row fg-form-row--2col">
							<div>
								<label class="fg-form-label" for="fge_city">Stadt</label>
								<input class="fg-form-input" type="text" id="fge_city" name="fge_city" value="<?php echo esc_attr( $m('city') ); ?>">
							</div>
							<div>
								<label class="fg-form-label" for="fge_federal_state">Bundesland</label>
								<input class="fg-form-input" type="text" id="fge_federal_state" name="fge_federal_state" value="<?php echo esc_attr( $m('federal_state') ); ?>">
							</div>
						</div>
						<div class="fg-form-row fg-form-row--2col">
							<div>
								<label class="fg-form-label" for="fge_website_url">Website</label>
								<input class="fg-form-input" type="url" id="fge_website_url" name="fge_website_url" value="<?php echo esc_attr( $m('website_url') ); ?>" placeholder="https://...">
							</div>
							<div>
								<label class="fg-form-label" for="fge_free_region">Region</label>
								<input class="fg-form-input" type="text" id="fge_free_region" name="fge_free_region" value="<?php echo esc_attr( $m('free_region') ); ?>" placeholder="z.B. München, Bayern">
							</div>
						</div>
						<div class="fg-form-row">
							<label class="fg-form-label" for="fge_public_short_description">Kurzbeschreibung</label>
							<textarea class="fg-form-textarea" id="fge_public_short_description" name="fge_public_short_description" rows="3" placeholder="2–3 Sätze für dein öffentliches Profil"><?php echo esc_textarea( $m('public_short_description') ); ?></textarea>
						</div>
					</div>

					<div class="fp-form-sec">
						<h3>Event-Ansprechpartner</h3>
						<p class="fp-help">Wird intern für Verfügbarkeitsanfragen genutzt.</p>

						<div class="fg-form-row fg-form-row--3col">
							<div>
								<label class="fg-form-label" for="fge_event_contact_name">Name</label>
								<input class="fg-form-input" type="text" id="fge_event_contact_name" name="fge_event_contact_name" value="<?php echo esc_attr( $m('event_contact_name') ); ?>">
							</div>
							<div>
								<label class="fg-form-label" for="fge_event_contact_email">E-Mail</label>
								<input class="fg-form-input" type="email" id="fge_event_contact_email" name="fge_event_contact_email" value="<?php echo esc_attr( $m('event_contact_email') ); ?>">
							</div>
							<div>
								<label class="fg-form-label" for="fge_event_contact_phone">Telefon</label>
								<input class="fg-form-input" type="tel" id="fge_event_contact_phone" name="fge_event_contact_phone" value="<?php echo esc_attr( $m('event_contact_phone') ); ?>">
							</div>
						</div>
					</div>
				</div>

				<!-- ─ Right: media uploads ─ -->
				<div class="fp-profile-media">
					<div>
						<div class="fp-profile-media-lbl">Logo</div>
						<label class="fp-logo-upload" title="Logo hochladen">
							<?php if ( $logo_url !== '' ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo">
							<?php else : ?>
								<?php echo fge_icon_upload(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span style="font-size:12px;">Hochladen</span>
							<?php endif; ?>
							<input type="file" name="fge_partner_logo" accept="image/*" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
						</label>
						<p style="font-size:12px;color:var(--ink-400);margin-top:6px;">PNG · max. 4 MB</p>
					</div>

					<div>
						<div class="fp-profile-media-lbl">Titelbild</div>
						<label class="fp-cover-upload-profile" style="<?php echo $cov_url !== '' ? 'background-image:url(' . esc_url( $cov_url ) . ');background-size:cover;background-position:center;border-style:solid;' : ''; ?>" title="Titelbild hochladen">
							<?php if ( $cov_url === '' ) : ?>
								<?php echo fge_icon_upload(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span style="font-size:12px;">16:9 · mind. 1600 px breit</span>
							<?php else : ?>
								<span class="fp-cover-replace-hint">
									<?php echo fge_icon_upload(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<span style="font-size:12px;">Bild tauschen</span>
								</span>
							<?php endif; ?>
							<input type="file" name="fge_partner_cover" accept="image/*" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
						</label>
						<p style="font-size:12px;color:var(--ink-400);margin-top:6px;">JPG / PNG · max. 8 MB</p>
					</div>
				</div>

			</div><!-- .fp-profile-grid -->

			<div style="margin-top:28px;">
				<button type="submit" class="fp-btn fp-btn-brand">
					Profil speichern <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
				<p style="font-size:13px;color:var(--ink-500);margin-top:10px;">
					Name, Betreiber und Adresse können nur vom Firmengolf-Team geändert werden. Schreib uns unter <a href="mailto:events@visionpunch.de" style="color:var(--fairway-700);">events@visionpunch.de</a>.
				</p>
			</div>
		</form>
	</div>
	<?php
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
	$cover_url = $cover_id > 0 ? (string) wp_get_attachment_image_url( $cover_id, 'large' ) : '';

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
			</div>
		</div>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="fg-form-errors-banner" role="alert">
				<strong>Bitte prüf deine Eingaben.</strong> Einige Felder sind noch nicht korrekt ausgefüllt.
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $base ); ?>" enctype="multipart/form-data" novalidate>
			<input type="hidden" name="fge_action" value="<?php echo $is_edit ? 'portal_edit_event' : 'portal_new_event'; ?>">
			<?php if ( $is_edit ) : ?><input type="hidden" name="fge_event_id" value="<?php echo esc_attr( $event_id ); ?>"><?php endif; ?>
			<?php wp_nonce_field( $is_edit ? 'fge_portal_edit_event' : 'fge_portal_new_event', 'fge_portal_nonce' ); ?>

			<div class="fp-edit-grid">

				<!-- ── LEFT: form sections ── -->
				<div>

					<!-- Cover -->
					<div class="fp-form-sec">
						<h3>Coverbild</h3>
						<p class="fp-help">Quer-Format 16:9 oder 16:10, mind. 1600 px breit. Dieses Bild erscheint auf der Angebotskarte.</p>
						<label class="fp-cover-upload<?php echo $cover_url !== '' ? ' has-image' : ''; ?>"
						       style="<?php echo $cover_url !== '' ? 'background-image:url(' . esc_url( $cover_url ) . ');background-size:cover;background-position:center;' : ''; ?>"
						       title="Coverbild hochladen">
							<?php if ( $cover_url === '' ) : ?>
								<div class="fp-cover-empty">
									<?php echo fge_icon_upload(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<span style="font-weight:500;color:var(--ink-900);">Foto hochladen oder hierher ziehen</span>
									<span class="fp-cover-hint">JPG / PNG · max. 8 MB</span>
								</div>
							<?php else : ?>
								<span class="fp-cover-replace-hint">
									<?php echo fge_icon_upload(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<span style="font-size:12px;">Foto tauschen</span>
								</span>
							<?php endif; ?>
							<input type="file" name="fge_event_cover" accept="image/*" class="fp-cover-label">
						</label>
					</div>

					<!-- Titel & Beschreibung -->
					<div class="fp-form-sec">
						<h3>Titel & Beschreibung</h3>
						<p class="fp-help">Wir empfehlen einen Titel, der ein Gefühl verspricht — nicht nur ein Format beschreibt.</p>

						<div class="fg-form-row">
							<label class="fg-form-label" for="fge_post_title">Eventtitel <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
							<div class="fg-form-field">
								<input class="fg-form-input<?php echo $has_err( 'fge_post_title' ); // phpcs:ignore ?>" type="text" id="fge_post_title" name="fge_post_title" value="<?php echo $v( 'fge_post_title' ); ?>" placeholder="z.B. Golfschnupperkurs für Firmenkunden">
								<?php echo $err_html( 'fge_post_title' ); // phpcs:ignore ?>
							</div>
						</div>
						<div class="fg-form-row">
							<label class="fg-form-label" for="fge_card_description">Kurzbeschreibung</label>
							<div class="fg-form-field">
								<textarea class="fg-form-textarea" id="fge_card_description" name="fge_card_description" rows="2" placeholder="Kurzer Teaser (1–2 Sätze)"><?php echo esc_textarea( $saved['fge_card_description'] ?? '' ); ?></textarea>
							</div>
						</div>
						<div class="fg-form-row">
							<label class="fg-form-label" for="fge_post_content">Ausführliche Beschreibung</label>
							<div class="fg-form-field">
								<textarea class="fg-form-textarea" id="fge_post_content" name="fge_post_content" rows="6" placeholder="Detaillierter Ablauf, Besonderheiten, Programm …"><?php echo esc_textarea( $saved['fge_post_content'] ?? '' ); ?></textarea>
							</div>
						</div>
					</div>

					<!-- Preis, Dauer & Teilnehmer -->
					<div class="fp-form-sec">
						<h3>Preis, Dauer & Teilnehmer</h3>
						<p class="fp-help">Diese Eckdaten erscheinen auf der Angebotskarte.</p>

						<div class="fg-form-row fg-form-row--2col">
							<div>
								<label class="fg-form-label" for="fge_public_price_label">Preislabel öffentlich</label>
								<input class="fg-form-input" type="text" id="fge_public_price_label" name="fge_public_price_label" value="<?php echo $v( 'fge_public_price_label' ); ?>" placeholder="z.B. ab 1.200 € netto">
							</div>
							<div>
								<label class="fg-form-label" for="fge_price_note">Preis Hinweis</label>
								<input class="fg-form-input" type="text" id="fge_price_note" name="fge_price_note" value="<?php echo $v( 'fge_price_note' ); ?>" placeholder="z.B. zzgl. Getränke">
							</div>
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
								<input class="fg-form-input" type="number" id="fge_participants_max" name="fge_participants_max" value="<?php echo esc_attr( $saved['fge_participants_max'] ?: '' ); ?>" min="1" placeholder="z.B. 40">
							</div>
						</div>
					</div>

					<!-- Enthaltene Leistungen -->
					<div class="fp-form-sec">
						<h3>Enthaltene Leistungen</h3>
						<p class="fp-help">Was im Preis enthalten ist — Firmen filtern nach diesen Punkten.</p>
						<div class="fg-form-checkgrid">
							<?php foreach ( $leistungen_labels as $key => $label ) : ?>
								<label class="fg-form-check">
									<input type="checkbox" name="fge_<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $checked( 'fge_' . $key ) ); ?>>
									<span><?php echo esc_html( $label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<div class="fg-form-row" style="margin-top:16px;">
							<label class="fg-form-label" for="fge_additional_services">Weitere Leistungen</label>
							<textarea class="fg-form-textarea" id="fge_additional_services" name="fge_additional_services" rows="2" placeholder="Sonstige enthaltene Leistungen"><?php echo esc_textarea( $saved['fge_additional_services'] ?? '' ); ?></textarea>
						</div>
					</div>

					<!-- Verfügbarkeit & Kontakt -->
					<div class="fp-form-sec">
						<h3>Verfügbarkeit & Kontakt</h3>

						<div class="fg-form-row fg-form-row--2col">
							<div>
								<label class="fg-form-label" for="fge_event_type">Eventart <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
								<div class="fg-form-field">
									<select class="fg-form-select<?php echo $has_err( 'fge_event_type' ); // phpcs:ignore ?>" id="fge_event_type" name="fge_event_type">
										<option value="">— bitte wählen —</option>
										<?php foreach ( $event_types as $val => $label ) : ?>
											<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $saved['fge_event_type'] ?? '', $val ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<?php echo $err_html( 'fge_event_type' ); // phpcs:ignore ?>
								</div>
							</div>
							<div>
								<label class="fg-form-label" for="fge_event_location">Ort</label>
								<input class="fg-form-input" type="text" id="fge_event_location" name="fge_event_location" value="<?php echo $v( 'fge_event_location' ); ?>" placeholder="z.B. GC München, Mainburg">
							</div>
						</div>

						<div class="fg-form-row">
							<label class="fg-form-label">Verfügbare Wochentage</label>
							<div class="fg-portal-weekdays">
								<?php foreach ( $weekdays as $val => $label ) : ?>
									<label class="fg-portal-weekday-check">
										<input type="checkbox" name="fge_available_weekdays[]" value="<?php echo esc_attr( $val ); ?>" <?php checked( in_array( $val, $saved_weekdays, true ) ); ?>>
										<span><?php echo esc_html( $label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="fg-form-row fg-form-row--2col">
							<div>
								<label class="fg-form-label" for="fge_region">Region</label>
								<input class="fg-form-input" type="text" id="fge_region" name="fge_region" value="<?php echo $v( 'fge_region' ); ?>" placeholder="z.B. München, Bayern">
							</div>
							<div>
								<label class="fg-form-label" for="fge_season">Saison / Zeitraum</label>
								<input class="fg-form-input" type="text" id="fge_season" name="fge_season" value="<?php echo $v( 'fge_season' ); ?>" placeholder="z.B. April bis Oktober">
							</div>
						</div>

						<div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--ink-200);">
							<p class="fg-form-label" style="margin-bottom:12px;font-weight:500;">Ansprechpartner für Verfügbarkeit</p>
							<div class="fg-form-row fg-form-row--3col">
								<div>
									<label class="fg-form-label" for="fge_availability_contact_name">Name</label>
									<input class="fg-form-input" type="text" id="fge_availability_contact_name" name="fge_availability_contact_name" value="<?php echo $v( 'fge_availability_contact_name' ); ?>" placeholder="Ansprechpartner">
								</div>
								<div>
									<label class="fg-form-label" for="fge_availability_contact_email">E-Mail</label>
									<input class="fg-form-input" type="email" id="fge_availability_contact_email" name="fge_availability_contact_email" value="<?php echo $v( 'fge_availability_contact_email' ); ?>">
								</div>
								<div>
									<label class="fg-form-label" for="fge_availability_contact_phone">Telefon</label>
									<input class="fg-form-input" type="tel" id="fge_availability_contact_phone" name="fge_availability_contact_phone" value="<?php echo $v( 'fge_availability_contact_phone' ); ?>">
								</div>
							</div>
						</div>
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
					<span style="width:7px;height:7px;border-radius:50%;background:var(--warning);flex:none;display:inline-block;"></span>
					<?php echo $is_edit ? 'Änderungen noch nicht gespeichert' : 'Noch nicht eingereicht'; ?>
				</span>
				<button type="submit" class="fp-btn fp-btn-brand">
					<?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php echo $is_edit ? 'Änderungen speichern' : 'Event einreichen'; ?>
				</button>
			</div>

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
