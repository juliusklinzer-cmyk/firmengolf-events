<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// FORM HANDLERS
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'init', 'fge_portal_handle_new_event', 10 );
add_action( 'init', 'fge_portal_handle_edit_event', 10 );

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
		wp_redirect( esc_url_raw( $base . '?tab=neues-event&portal_err=' . rawurlencode( $token ) ), 303 );
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
		wp_redirect( esc_url_raw( $base . '?tab=neues-event&portal_err=save_failed' ), 303 );
		exit;
	}

	update_post_meta( $post_id, '_fge_event_status',        'zur_pruefung' );
	update_post_meta( $post_id, '_fge_provider_type',       'golfplatz_partner' );
	update_post_meta( $post_id, '_fge_assigned_partner_id', $partner_id );
	fge_portal_save_event_meta( $post_id );

	wp_redirect( esc_url_raw( $base . '?tab=events&portal_success=event_saved' ), 303 );
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
		wp_redirect( esc_url_raw( $base . '?tab=events&portal_action=edit&event_id=' . $event_id . '&portal_err=' . rawurlencode( $token ) ), 303 );
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

	wp_redirect( esc_url_raw( $base . '?tab=events&portal_success=event_updated' ), 303 );
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
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	$user = wp_get_current_user();
	if ( ! current_user_can( 'manage_options' ) && ! in_array( 'firmengolf_partner', (array) $user->roles, true ) ) {
		wp_die(
			'<p>Du hast keine Berechtigung für diesen Bereich.</p><p><a href="' . esc_url( home_url( '/' ) ) . '">Zurück zur Startseite</a></p>',
			'Kein Zugriff',
			[ 'response' => 403 ]
		);
	}
}

function fge_portal_get_active_tab(): string {
	$allowed = [ 'dashboard', 'profil', 'events', 'neues-event', 'anfragen', 'kennzahlen' ];
	$tab     = sanitize_key( $_GET['tab'] ?? 'dashboard' );
	return in_array( $tab, $allowed, true ) ? $tab : 'dashboard';
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
	$allowed_types    = [ 'teamevent', 'kundenevent', 'gesundheitstag', 'offsite', 'firmenturnier', 'anderes_event' ];
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

	$raw_days = array_map( 'sanitize_text_field', (array) ( $_POST['fge_available_weekdays'] ?? [] ) );
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
	return [
		'teamevent'      => 'Teamevent',
		'kundenevent'    => 'Kundenevent',
		'gesundheitstag' => 'Gesundheitstag',
		'offsite'        => 'Offsite',
		'firmenturnier'  => 'Firmenturnier',
		'anderes_event'  => 'Anderes Event',
	][ $type ] ?? '';
}

// ══════════════════════════════════════════════════════════════════════════════
// MAIN RENDER
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_render(): void {
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
			<p>Bitte wende dich an das Firmengolf Team: <a href="mailto:events@firmen.golf">events@firmen.golf</a></p>
		</div>
		<?php
		return;
	}

	$active_tab   = fge_portal_get_active_tab();
	$partner_name = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$user         = wp_get_current_user();
	$success      = sanitize_key( $_GET['portal_success'] ?? '' );

	$tabs = [
		'dashboard'   => 'Dashboard',
		'profil'      => 'Mein Profil',
		'events'      => 'Meine Events',
		'neues-event' => 'Neues Event',
		'anfragen'    => 'Anfragen',
		'kennzahlen'  => 'Kennzahlen',
	];
	?>
	<div class="fg-portal">

		<div class="fg-portal-header">
			<div class="fg-portal-header-inner">
				<div>
					<p class="fg-portal-header-label">Partnerportal</p>
					<h1 class="fg-portal-header-title"><?php echo esc_html( $partner_name ); ?></h1>
				</div>
				<div class="fg-portal-header-user">
					<span><?php echo esc_html( $user->display_name ); ?></span>
					<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="fg-portal-logout">Abmelden</a>
				</div>
			</div>
		</div>

		<?php if ( $success !== '' ) : ?>
			<div class="fg-portal-global-notice fg-portal-global-notice--success" role="status">
				<?php if ( $success === 'event_saved' ) : ?>
					Dein Eventangebot wurde eingereicht und wird von Firmengolf geprüft.
				<?php elseif ( $success === 'event_updated' ) : ?>
					Das Event wurde aktualisiert und wird erneut geprüft.
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<nav class="fg-portal-nav" aria-label="Portalbereiche">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<button
					class="fg-portal-tab<?php echo $active_tab === $key ? ' fg-portal-tab--active' : ''; ?>"
					data-tab="<?php echo esc_attr( $key ); ?>"
					type="button"
				><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
		</nav>

		<div class="fg-portal-content">
			<div id="fg-tab-dashboard"   class="fg-portal-section<?php echo $active_tab !== 'dashboard'   ? ' fg-portal-section--hidden' : ''; ?>"><?php fge_portal_section_dashboard( $partner_id ); ?></div>
			<div id="fg-tab-profil"      class="fg-portal-section<?php echo $active_tab !== 'profil'      ? ' fg-portal-section--hidden' : ''; ?>"><?php fge_portal_section_profile( $partner_id ); ?></div>
			<div id="fg-tab-events"      class="fg-portal-section<?php echo $active_tab !== 'events'      ? ' fg-portal-section--hidden' : ''; ?>"><?php fge_portal_section_events( $partner_id ); ?></div>
			<div id="fg-tab-neues-event" class="fg-portal-section<?php echo $active_tab !== 'neues-event' ? ' fg-portal-section--hidden' : ''; ?>"><?php fge_portal_section_new_event(); ?></div>
			<div id="fg-tab-anfragen"    class="fg-portal-section<?php echo $active_tab !== 'anfragen'    ? ' fg-portal-section--hidden' : ''; ?>"><?php fge_portal_section_requests( $partner_id ); ?></div>
			<div id="fg-tab-kennzahlen"  class="fg-portal-section<?php echo $active_tab !== 'kennzahlen'  ? ' fg-portal-section--hidden' : ''; ?>"><?php fge_portal_section_stats( $partner_id ); ?></div>
		</div>

	</div>
	<script>
	(function () {
		var tabs     = document.querySelectorAll('.fg-portal-tab');
		var sections = document.querySelectorAll('.fg-portal-section');
		tabs.forEach(function (btn) {
			btn.addEventListener('click', function () {
				tabs.forEach(function (b) { b.classList.remove('fg-portal-tab--active'); });
				sections.forEach(function (s) { s.classList.add('fg-portal-section--hidden'); });
				btn.classList.add('fg-portal-tab--active');
				var el = document.getElementById('fg-tab-' + btn.dataset.tab);
				if (el) { el.classList.remove('fg-portal-section--hidden'); }
				var url = new URL(window.location.href);
				url.searchParams.set('tab', btn.dataset.tab);
				['portal_action', 'event_id', 'portal_success', 'portal_err'].forEach(function (p) { url.searchParams.delete(p); });
				history.replaceState(null, '', url.toString());
			});
		});
	})();
	</script>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: DASHBOARD
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_section_dashboard( int $partner_id ): void {
	$partner_name   = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$partner_status = (string) get_post_meta( $partner_id, '_fge_partner_status', true );
	$city           = (string) get_post_meta( $partner_id, '_fge_city', true );

	$events = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );

	$total = count( $events );
	$published = $in_review = $views = $requests = $bookings = 0;

	foreach ( $events as $ev ) {
		$st = (string) get_post_meta( $ev->ID, '_fge_event_status', true );
		if ( $st === 'freigegeben' ) $published++;
		if ( in_array( $st, [ 'zur_pruefung', 'aenderung_in_pruefung' ], true ) ) $in_review++;
		$views    += (int) get_post_meta( $ev->ID, '_fge_views_count', true );
		$requests += (int) get_post_meta( $ev->ID, '_fge_requests_count', true );
		$bookings += (int) get_post_meta( $ev->ID, '_fge_bookings_count', true );
	}
	?>
	<h2 class="fg-portal-section-title">Übersicht</h2>

	<div class="fg-portal-profile-card">
		<div class="fg-portal-profile-card-body">
			<div class="fg-portal-profile-card-name"><?php echo esc_html( $partner_name ); ?></div>
			<?php if ( $city !== '' ) : ?><div class="fg-portal-profile-card-sub"><?php echo esc_html( $city ); ?></div><?php endif; ?>
		</div>
		<span class="fg-portal-status fg-portal-status--<?php echo esc_attr( fge_portal_status_class( $partner_status ) ); ?>">
			<?php echo esc_html( fge_portal_format_partner_status( $partner_status ) ); ?>
		</span>
	</div>

	<div class="fg-portal-stats">
		<?php
		$stats = [
			[ $total,     'Events gesamt' ],
			[ $published, 'Freigegeben' ],
			[ $in_review, 'In Prüfung' ],
			[ $views,     'Aufrufe' ],
			[ $requests,  'Anfragen' ],
			[ $bookings,  'Buchungen' ],
		];
		foreach ( $stats as [ $val, $label ] ) :
		?>
			<div class="fg-portal-stat">
				<div class="fg-portal-stat-value"><?php echo esc_html( $val ); ?></div>
				<div class="fg-portal-stat-label"><?php echo esc_html( $label ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: PROFIL
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_section_profile( int $partner_id ): void {
	$m = static function( string $key ) use ( $partner_id ): string {
		return (string) get_post_meta( $partner_id, '_fge_' . $key, true );
	};

	$ausstattung = [
		'has_driving_range' => 'Driving Range', 'has_short_game_area' => 'Kurzspielbereich',
		'has_putting_green' => 'Putting Green',  'has_rental_clubs' => 'Leihschläger',
		'has_range_balls' => 'Rangebälle',        'has_golf_teacher' => 'Golflehrer',
		'has_meeting_room' => 'Meetingraum',       'has_gastronomy' => 'Gastronomie',
		'has_breakfast' => 'Frühstück',            'has_lunch' => 'Lunch',
		'has_dinner' => 'Abendessen',              'has_terrace' => 'Terrasse',
		'has_parking' => 'Parkplätze',             'has_shuttle_access' => 'Shuttle',
		'has_indoor_or_simulator' => 'Indoor/Simulator', 'has_branding_options' => 'Branding',
		'has_tournament_organization' => 'Turniermodus', 'has_bad_weather_alternative' => 'Schlechtwetter-Alternative',
	];
	$active = [];
	foreach ( $ausstattung as $key => $label ) {
		if ( $m( $key ) === '1' ) $active[] = $label;
	}
	?>
	<h2 class="fg-portal-section-title">Mein Golfplatz</h2>
	<p class="fg-portal-section-sub">Lesansicht — Profiländerungen bitte direkt bei Firmengolf anfragen.</p>

	<div class="fg-portal-profile-groups">

		<div class="fg-portal-profile-group">
			<div class="fg-portal-profile-group-title">Golfplatz</div>
			<?php
			fge_portal_profile_row( 'Name öffentlich', $m('public_golfclub_name') );
			fge_portal_profile_row( 'Betreiber', $m('legal_operator_name') );
			fge_portal_profile_row( 'Ort', trim( $m('city') . ( $m('federal_state') ? ', ' . $m('federal_state') : '' ) ) );
			fge_portal_profile_row( 'Region', $m('free_region') );
			if ( $m('website_url') !== '' ) {
				echo '<div class="fg-portal-profile-row"><span class="fg-portal-profile-key">Website</span><span class="fg-portal-profile-val"><a href="' . esc_url( $m('website_url') ) . '" target="_blank" rel="noopener">' . esc_html( $m('website_url') ) . '</a></span></div>';
			}
			?>
		</div>

		<div class="fg-portal-profile-group">
			<div class="fg-portal-profile-group-title">Hauptansprechpartner</div>
			<?php
			fge_portal_profile_row( 'Name', $m('main_contact_name') );
			if ( $m('main_contact_email') !== '' ) {
				echo '<div class="fg-portal-profile-row"><span class="fg-portal-profile-key">E-Mail</span><span class="fg-portal-profile-val"><a href="mailto:' . esc_attr( $m('main_contact_email') ) . '">' . esc_html( $m('main_contact_email') ) . '</a></span></div>';
			}
			fge_portal_profile_row( 'Telefon', $m('main_contact_phone') );
			?>
		</div>

		<div class="fg-portal-profile-group">
			<div class="fg-portal-profile-group-title">Event Ansprechpartner</div>
			<?php
			fge_portal_profile_row( 'Name', $m('event_contact_name') );
			if ( $m('event_contact_email') !== '' ) {
				echo '<div class="fg-portal-profile-row"><span class="fg-portal-profile-key">E-Mail</span><span class="fg-portal-profile-val"><a href="mailto:' . esc_attr( $m('event_contact_email') ) . '">' . esc_html( $m('event_contact_email') ) . '</a></span></div>';
			}
			fge_portal_profile_row( 'Telefon', $m('event_contact_phone') );
			?>
		</div>

		<?php if ( ! empty( $active ) ) : ?>
		<div class="fg-portal-profile-group">
			<div class="fg-portal-profile-group-title">Ausstattung</div>
			<div class="fg-portal-tags">
				<?php foreach ( $active as $label ) : ?>
					<span class="fg-portal-tag"><?php echo esc_html( $label ); ?></span>
				<?php endforeach; ?>
			</div>
			<?php if ( $m('additional_equipment') !== '' ) : ?>
				<p class="fg-portal-profile-extra"><?php echo esc_html( $m('additional_equipment') ); ?></p>
			<?php endif; ?>
		</div>
		<?php endif; ?>

	</div>
	<?php
}

function fge_portal_profile_row( string $key, string $val ): void {
	if ( $val === '' ) return;
	echo '<div class="fg-portal-profile-row"><span class="fg-portal-profile-key">' . esc_html( $key ) . '</span><span class="fg-portal-profile-val">' . esc_html( $val ) . '</span></div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: MEINE EVENTS
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_section_events( int $partner_id ): void {
	$portal_action = sanitize_key( $_GET['portal_action'] ?? '' );
	$event_id      = absint( $_GET['event_id'] ?? 0 );

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

		$base = fge_portal_page_url();
		?>
		<a href="<?php echo esc_url( $base . '?tab=events' ); ?>" class="fg-portal-back">
			<?php echo fge_icon_arrow_left(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			Zurück zu Meine Events
		</a>
		<h2 class="fg-portal-section-title">Event bearbeiten</h2>
		<?php fge_portal_render_event_form( $partner_id, $saved, $errors, $event_id );
		return;
	}

	$events = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );

	$base = fge_portal_page_url();
	?>
	<h2 class="fg-portal-section-title">Meine Eventangebote</h2>

	<?php if ( empty( $events ) ) : ?>
		<p class="fg-portal-empty-text">Noch keine Eventangebote vorhanden. <a href="<?php echo esc_url( $base . '?tab=neues-event' ); ?>">Erstes Event einreichen →</a></p>
	<?php else : ?>
		<div class="fg-portal-table-wrap">
			<table class="fg-portal-table">
				<thead><tr>
					<th>Event</th><th>Eventart</th><th>Status</th>
					<th>Preislabel</th><th>Aufrufe</th><th>Anfragen</th><th>Buchungen</th><th></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $events as $ev ) :
						$status   = (string) get_post_meta( $ev->ID, '_fge_event_status', true );
						$etype    = (string) get_post_meta( $ev->ID, '_fge_event_type', true );
						$plabel   = (string) get_post_meta( $ev->ID, '_fge_public_price_label', true );
						$views    = (int)    get_post_meta( $ev->ID, '_fge_views_count', true );
						$reqs     = (int)    get_post_meta( $ev->ID, '_fge_requests_count', true );
						$books    = (int)    get_post_meta( $ev->ID, '_fge_bookings_count', true );
					?>
					<tr>
						<td><strong><?php echo esc_html( $ev->post_title ); ?></strong></td>
						<td><?php echo esc_html( fge_portal_format_event_type( $etype ) ); ?></td>
						<td><span class="fg-portal-status fg-portal-status--<?php echo esc_attr( fge_portal_status_class( $status ) ); ?>"><?php echo esc_html( fge_portal_format_event_status( $status ) ); ?></span></td>
						<td><?php echo esc_html( $plabel ?: '—' ); ?></td>
						<td><?php echo esc_html( $views ); ?></td>
						<td><?php echo esc_html( $reqs ); ?></td>
						<td><?php echo esc_html( $books ); ?></td>
						<td><a href="<?php echo esc_url( $base . '?tab=events&portal_action=edit&event_id=' . $ev->ID ); ?>" class="fg-portal-edit-link">Bearbeiten</a></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION: NEUES EVENT
// ══════════════════════════════════════════════════════════════════════════════

function fge_portal_section_new_event(): void {
	[ 'errors' => $errors, 'data' => $saved ] = fge_load_form_state( 'portal_err' );
	?>
	<h2 class="fg-portal-section-title">Neues Eventangebot einreichen</h2>
	<p class="fg-portal-section-sub">Dein Event wird nach dem Einreichen von Firmengolf geprüft und dann freigegeben.</p>
	<?php fge_portal_render_event_form( 0, $saved, $errors, 0 );
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

	$request_status_labels = [
		'neu'                           => 'Neu',
		'eingangsbestaetigung_gesendet' => 'Bestätigt',
		'verfuegbarkeit_wird_geprueft'  => 'Prüfung',
		'partner_angefragt'             => 'Angefragt',
		'vollstaendig_verfuegbar'       => 'Verfügbar',
		'nicht_verfuegbar'              => 'Nicht verfügbar',
		'angebot_versendet'             => 'Angebot versendet',
		'angebot_angenommen'            => 'Angenommen',
		'event_durchgefuehrt'           => 'Durchgeführt',
		'abgeschlossen'                 => 'Abgeschlossen',
		'verloren'                      => 'Verloren',
	];
	?>
	<h2 class="fg-portal-section-title">Meine Anfragen</h2>

	<?php if ( empty( $requests ) ) : ?>
		<p class="fg-portal-empty-text">Noch keine Anfragen vorhanden.</p>
	<?php else : ?>
		<div class="fg-portal-table-wrap">
			<table class="fg-portal-table">
				<thead><tr>
					<th>Status</th><th>Unternehmen</th><th>Kontakt</th><th>Event</th>
					<th>Teilnehmer</th><th>Wunschtermin</th><th>Quelle</th><th>Datum</th>
				</tr></thead>
				<tbody>
					<?php foreach ( $requests as $req ) :
						$status    = (string) get_post_meta( $req->ID, '_fge_request_status', true );
						$company   = (string) get_post_meta( $req->ID, '_fge_company_name', true );
						$first     = (string) get_post_meta( $req->ID, '_fge_contact_first_name', true );
						$last      = (string) get_post_meta( $req->ID, '_fge_contact_last_name', true );
						$ev_id     = (int)    get_post_meta( $req->ID, '_fge_assigned_event_id', true );
						$parts     = (string) get_post_meta( $req->ID, '_fge_expected_participants', true );
						$date_1    = (string) get_post_meta( $req->ID, '_fge_preferred_date_1', true );
						$source    = (string) get_post_meta( $req->ID, '_fge_source', true );
						$req_date  = (string) get_post_meta( $req->ID, '_fge_request_date', true );
						$ev_title  = $ev_id > 0 ? get_the_title( $ev_id ) : '—';
						$st_label  = $request_status_labels[ $status ] ?? $status;
					?>
					<tr>
						<td><span class="fg-portal-status fg-portal-status--<?php echo esc_attr( fge_portal_status_class( $status ) ); ?>"><?php echo esc_html( $st_label ); ?></span></td>
						<td><?php echo esc_html( $company ?: '—' ); ?></td>
						<td><?php echo esc_html( trim( $first . ' ' . $last ) ?: '—' ); ?></td>
						<td><?php echo esc_html( $ev_title ); ?></td>
						<td><?php echo esc_html( $parts ?: '—' ); ?></td>
						<td><?php echo esc_html( $date_1 ?: '—' ); ?></td>
						<td><?php echo esc_html( $source ?: '—' ); ?></td>
						<td><?php echo esc_html( $req_date ? substr( $req_date, 0, 10 ) : '—' ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
	<?php
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
		if ( get_post_meta( $ev->ID, '_fge_event_status', true ) === 'freigegeben' ) $published++;
		$views    += (int) get_post_meta( $ev->ID, '_fge_views_count', true );
		$requests += (int) get_post_meta( $ev->ID, '_fge_requests_count', true );
		$bookings += (int) get_post_meta( $ev->ID, '_fge_bookings_count', true );
	}

	$cr_vr = $views    > 0 ? round( $requests / $views * 100, 1 ) : 0;
	$cr_rb = $requests > 0 ? round( $bookings / $requests * 100, 1 ) : 0;
	?>
	<h2 class="fg-portal-section-title">Kennzahlen</h2>

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
	<h3 class="fg-portal-subsection-title">Events im Detail</h3>
	<div class="fg-portal-table-wrap">
		<table class="fg-portal-table">
			<thead><tr><th>Event</th><th>Status</th><th>Aufrufe</th><th>Anfragen</th><th>Buchungen</th><th>CR Aufruf→Anfrage</th></tr></thead>
			<tbody>
				<?php foreach ( $events as $ev ) :
					$st  = (string) get_post_meta( $ev->ID, '_fge_event_status', true );
					$ev_v = (int) get_post_meta( $ev->ID, '_fge_views_count', true );
					$ev_r = (int) get_post_meta( $ev->ID, '_fge_requests_count', true );
					$ev_b = (int) get_post_meta( $ev->ID, '_fge_bookings_count', true );
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

	$event_types = [
		'teamevent' => 'Teamevent', 'kundenevent' => 'Kundenevent',
		'gesundheitstag' => 'Gesundheitstag', 'offsite' => 'Offsite',
		'firmenturnier' => 'Firmenturnier', 'anderes_event' => 'Anderes Event',
	];
	$weekdays = [ 'monday' => 'Mo', 'tuesday' => 'Di', 'wednesday' => 'Mi', 'thursday' => 'Do', 'friday' => 'Fr', 'saturday' => 'Sa', 'sunday' => 'So' ];
	$saved_weekdays = (array) ( $saved['fge_available_weekdays'] ?? [] );

	if ( ! empty( $errors ) ) : ?>
		<div class="fg-form-errors-banner" role="alert">
			<strong>Bitte prüf deine Eingaben.</strong> Einige Felder sind noch nicht korrekt ausgefüllt.
		</div>
	<?php endif; ?>

	<form class="fg-anfrage-form" method="post" action="<?php echo esc_url( $base ); ?>" novalidate>
		<input type="hidden" name="fge_action" value="<?php echo $is_edit ? 'portal_edit_event' : 'portal_new_event'; ?>">
		<?php if ( $is_edit ) : ?><input type="hidden" name="fge_event_id" value="<?php echo esc_attr( $event_id ); ?>"><?php endif; ?>
		<?php wp_nonce_field( $is_edit ? 'fge_portal_edit_event' : 'fge_portal_new_event', 'fge_portal_nonce' ); ?>

		<div class="fg-form-block">
			<p class="fg-form-block-title">Basisdaten</p>
			<div class="fg-form-row">
				<label class="fg-form-label" for="fge_post_title">Eventtitel <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
				<div class="fg-form-field">
					<input class="fg-form-input<?php echo $has_err( 'fge_post_title' ); // phpcs:ignore ?>" type="text" id="fge_post_title" name="fge_post_title" value="<?php echo $v( 'fge_post_title' ); ?>" placeholder="z.B. Golfschnupperkurs für Firmenkunden">
					<?php echo $err_html( 'fge_post_title' ); // phpcs:ignore ?>
				</div>
			</div>
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
					<div class="fg-form-field">
						<input class="fg-form-input" type="text" id="fge_event_location" name="fge_event_location" value="<?php echo $v( 'fge_event_location' ); ?>" placeholder="z.B. Golfclub Muster, München">
					</div>
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

		<div class="fg-form-block">
			<p class="fg-form-block-title">Event Rahmen</p>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_participants_min">Teilnehmer Min.</label>
					<input class="fg-form-input" type="number" id="fge_participants_min" name="fge_participants_min" value="<?php echo esc_attr( $saved['fge_participants_min'] ?: '' ); ?>" min="1" placeholder="z.B. 8">
				</div>
				<div>
					<label class="fg-form-label" for="fge_participants_max">Teilnehmer Max.</label>
					<input class="fg-form-input" type="number" id="fge_participants_max" name="fge_participants_max" value="<?php echo esc_attr( $saved['fge_participants_max'] ?: '' ); ?>" min="1" placeholder="z.B. 40">
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_duration">Dauer</label>
					<input class="fg-form-input" type="text" id="fge_duration" name="fge_duration" value="<?php echo $v( 'fge_duration' ); ?>" placeholder="z.B. halber Tag, 4 Stunden">
				</div>
				<div>
					<label class="fg-form-label" for="fge_season">Saison / Zeitraum</label>
					<input class="fg-form-input" type="text" id="fge_season" name="fge_season" value="<?php echo $v( 'fge_season' ); ?>" placeholder="z.B. April bis Oktober">
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_region">Region</label>
					<input class="fg-form-input" type="text" id="fge_region" name="fge_region" value="<?php echo $v( 'fge_region' ); ?>" placeholder="z.B. München, Bayern">
				</div>
				<div>
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
			</div>
		</div>

		<?php fge_portal_render_event_leistungen_block( $checked, $saved['fge_additional_services'] ?? '' ); ?>

		<div class="fg-form-block">
			<p class="fg-form-block-title">Preis</p>
			<p class="fg-form-block-hint">Firmengolf berechnet den Verkaufspreis intern. Gib hier das öffentliche Preislabel an.</p>
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
		</div>

		<div class="fg-form-block">
			<p class="fg-form-block-title">Ansprechpartner für Verfügbarkeit</p>
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

		<div class="fg-form-submit">
			<button type="submit" class="fg-btn fg-btn-brand fg-btn-lg">
				<?php echo $is_edit ? 'Änderungen speichern' : 'Event einreichen'; ?> <?php echo fge_icon_arrow_right(); // phpcs:ignore ?>
			</button>
			<p class="fg-form-submit-note">
				<?php echo $is_edit ? 'Das Event geht nach dem Speichern erneut in Prüfung.' : 'Dein Event wird von Firmengolf geprüft und dann freigegeben.'; ?>
			</p>
		</div>

	</form>
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
