<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// SHARED HELPERS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Loads PRG error-state from a transient.
 * Reads $_GET[$err_key], fetches the transient, deletes it, returns its contents.
 * Returns ['errors' => [], 'data' => []] if no token present or transient expired.
 */
function fge_load_form_state( string $err_key ): array {
	$token = sanitize_text_field( wp_unslash( $_GET[ $err_key ] ?? '' ) );
	if ( $token === '' ) {
		return [ 'errors' => [], 'data' => [] ];
	}
	$transient = get_transient( 'fge_form_err_' . $token );
	if ( ! is_array( $transient ) ) {
		return [ 'errors' => [], 'data' => [] ];
	}
	delete_transient( 'fge_form_err_' . $token );
	return [
		'errors' => $transient['errors'] ?? [],
		'data'   => $transient['data']   ?? [],
	];
}

/**
 * Returns an array of closures for rendering form fields.
 * Usage: [ 'v' => $v, 'checked' => $checked, 'err_html' => $err_html, 'has_error' => $has_error ] = fge_form_helpers(...);
 */
function fge_form_helpers( array $saved_data, array $errors ): array {
	return [
		'v' => static function( string $key ) use ( $saved_data ): string {
			return esc_attr( sanitize_text_field( $saved_data[ $key ] ?? '' ) );
		},
		'checked' => static function( string $key ) use ( $saved_data ): bool {
			return ! empty( $saved_data[ $key ] );
		},
		'err_html' => static function( string $key ) use ( $errors ): string {
			if ( isset( $errors[ $key ] ) ) {
				return '<p class="fg-form-error" role="alert">' . esc_html( $errors[ $key ] ) . '</p>';
			}
			return '';
		},
		'has_error' => static function( string $key ) use ( $errors ): string {
			return isset( $errors[ $key ] ) ? ' fg-form-input--error' : '';
		},
	];
}

/**
 * Saves all 10 Leistungen checkboxes and additional_wishes textarea from $_POST.
 */
function fge_save_leistungen_meta( int $post_id ): void {
	$keys = [
		'wants_golf_teacher', 'wants_meeting_room', 'wants_breakfast', 'wants_lunch',
		'wants_dinner', 'wants_shuttle', 'wants_branding', 'wants_tournament_mode',
		'wants_bad_weather_alternative', 'wants_individual_customization',
	];
	foreach ( $keys as $key ) {
		update_post_meta( $post_id, '_fge_' . $key, isset( $_POST[ 'fge_' . $key ] ) ? 1 : 0 );
	}
	update_post_meta( $post_id, '_fge_additional_wishes', sanitize_textarea_field( wp_unslash( $_POST['fge_additional_wishes'] ?? '' ) ) );
}

/**
 * Renders the Leistungen block (Block 5) — shared between both forms.
 */
function fge_render_form_leistungen_block( callable $checked, string $additional_wishes = '' ): void {
	$labels = [
		'wants_golf_teacher'             => 'Golflehrer / Golfpro',
		'wants_meeting_room'             => 'Meetingraum',
		'wants_breakfast'                => 'Frühstück',
		'wants_lunch'                    => 'Mittagessen',
		'wants_dinner'                   => 'Abendessen / Dinner',
		'wants_shuttle'                  => 'Shuttle-Service',
		'wants_branding'                 => 'Branding & Firmenmotiv',
		'wants_tournament_mode'          => 'Turniermodus',
		'wants_bad_weather_alternative'  => 'Schlechtwetter-Alternative',
		'wants_individual_customization' => 'Individuelle Gestaltung',
	];
	?>
	<div class="fg-form-block">
		<p class="fg-form-block-title">Gewünschte Zusatzleistungen</p>
		<p class="fg-form-block-hint">Was soll das Event beinhalten? Mehrfachauswahl möglich.</p>
		<div class="fg-form-checkgrid">
			<?php foreach ( $labels as $key => $label ) : ?>
				<label class="fg-form-check">
					<input type="checkbox" name="fge_<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $checked( 'fge_' . $key ) ); ?>>
					<span><?php echo esc_html( $label ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<div class="fg-form-row" style="margin-top:16px;">
			<label class="fg-form-label" for="fge_additional_wishes">Weitere Wünsche</label>
			<div class="fg-form-field">
				<textarea class="fg-form-textarea" id="fge_additional_wishes" name="fge_additional_wishes" rows="3" placeholder="Sonstige Anforderungen oder besondere Wünsche"><?php echo esc_textarea( $additional_wishes ); ?></textarea>
			</div>
		</div>
	</div>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// AP8 — SPECIFIC EVENT FORM
// ══════════════════════════════════════════════════════════════════════════════

function fge_handle_anfrage_submit() {
	if ( ! isset( $_POST['fge_action'] ) || $_POST['fge_action'] !== 'anfrage_submit' ) {
		return;
	}

	if (
		! isset( $_POST['fge_anfrage_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_anfrage_nonce'] ) ), 'fge_anfrage' )
	) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}

	$event_id      = absint( $_POST['fge_event_id'] ?? 0 );
	$redirect_base = $event_id > 0 ? get_permalink( $event_id ) : home_url( '/' );

	if ( ! empty( $_POST['fge_hp_url'] ) ) {
		wp_redirect( esc_url_raw( $redirect_base . '?anfrage=danke' ), 303 );
		exit;
	}

	$errors       = [];
	$company_name = sanitize_text_field( wp_unslash( $_POST['fge_company_name'] ?? '' ) );
	$first_name   = sanitize_text_field( wp_unslash( $_POST['fge_contact_first_name'] ?? '' ) );
	$last_name    = sanitize_text_field( wp_unslash( $_POST['fge_contact_last_name'] ?? '' ) );
	$email        = sanitize_email( wp_unslash( $_POST['fge_contact_email'] ?? '' ) );
	$consent      = isset( $_POST['fge_privacy_consent'] );

	if ( $company_name === '' ) {
		$errors['fge_company_name'] = 'Bitte gib den Firmennamen an.';
	}
	if ( $first_name === '' ) {
		$errors['fge_contact_first_name'] = 'Bitte gib deinen Vornamen an.';
	}
	if ( $last_name === '' ) {
		$errors['fge_contact_last_name'] = 'Bitte gib deinen Nachnamen an.';
	}
	if ( $email === '' || ! is_email( $email ) ) {
		$errors['fge_contact_email'] = 'Bitte gib eine gültige E-Mail-Adresse an.';
	}
	if ( ! $consent ) {
		$errors['fge_privacy_consent'] = 'Bitte stimme der Datenschutzerklärung zu.';
	}

	if ( ! empty( $errors ) ) {
		$token = wp_generate_uuid4();
		set_transient( 'fge_form_err_' . $token, [ 'errors' => $errors, 'data' => wp_unslash( $_POST ) ], 300 );
		wp_redirect( esc_url_raw( $redirect_base . '?anfrage_err=' . rawurlencode( $token ) . '#event-anfrage' ), 303 );
		exit;
	}

	$request_id = wp_insert_post( [
		'post_type'   => 'firmengolf_request',
		'post_status' => 'publish',
		'post_title'  => 'Auto Draft',
	] );

	if ( is_wp_error( $request_id ) || $request_id === 0 ) {
		wp_redirect( esc_url_raw( $redirect_base . '?anfrage=fehler' ), 303 );
		exit;
	}

	$partner_id              = (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true );
	$allowed_contact_methods = [ 'phone', 'email', 'any' ];
	$allowed_event_goals     = [ 'teamevent', 'kundenevent', 'gesundheitstag', 'offsite', 'sommerfest', 'weihnachtsfeier', 'anderes_event' ];
	$allowed_preferred_times = [ 'morning', 'afternoon', 'after_work', 'full_day', 'open' ];

	$san_select = static function( string $key, array $allowed ): string {
		$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		return in_array( $val, $allowed, true ) ? $val : '';
	};

	update_post_meta( $request_id, '_fge_request_type',        'specific_event' );
	update_post_meta( $request_id, '_fge_request_status',      'neu' );
	update_post_meta( $request_id, '_fge_assigned_event_id',   $event_id );
	update_post_meta( $request_id, '_fge_assigned_partner_id', $partner_id );
	update_post_meta( $request_id, '_fge_source',              'event_page' );
	add_post_meta( $request_id, '_fge_request_date',      current_datetime()->format( 'Y-m-d H:i:s' ), true );
	add_post_meta( $request_id, '_fge_consent_timestamp', current_datetime()->format( 'Y-m-d H:i:s' ), true );

	update_post_meta( $request_id, '_fge_company_name', $company_name );
	update_post_meta( $request_id, '_fge_industry',     sanitize_text_field( wp_unslash( $_POST['fge_industry'] ?? '' ) ) );

	update_post_meta( $request_id, '_fge_contact_first_name',       $first_name );
	update_post_meta( $request_id, '_fge_contact_last_name',        $last_name );
	update_post_meta( $request_id, '_fge_contact_email',            $email );
	update_post_meta( $request_id, '_fge_contact_phone',            sanitize_text_field( wp_unslash( $_POST['fge_contact_phone'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_contact_role',             sanitize_text_field( wp_unslash( $_POST['fge_contact_role'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_preferred_contact_method', $san_select( 'fge_preferred_contact_method', $allowed_contact_methods ) );

	update_post_meta( $request_id, '_fge_expected_participants', absint( $_POST['fge_expected_participants'] ?? 0 ) );
	update_post_meta( $request_id, '_fge_event_goal',            $san_select( 'fge_event_goal', $allowed_event_goals ) );
	update_post_meta( $request_id, '_fge_budget_range',          sanitize_text_field( wp_unslash( $_POST['fge_budget_range'] ?? '' ) ) );

	update_post_meta( $request_id, '_fge_preferred_date_1',   sanitize_text_field( wp_unslash( $_POST['fge_preferred_date_1'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_preferred_date_2',   sanitize_text_field( wp_unslash( $_POST['fge_preferred_date_2'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_preferred_date_3',   sanitize_text_field( wp_unslash( $_POST['fge_preferred_date_3'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_alternative_period', sanitize_text_field( wp_unslash( $_POST['fge_alternative_period'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_preferred_time',     $san_select( 'fge_preferred_time', $allowed_preferred_times ) );

	fge_save_leistungen_meta( $request_id );

	update_post_meta( $request_id, '_fge_message', sanitize_textarea_field( wp_unslash( $_POST['fge_message'] ?? '' ) ) );

	$current_count = (int) get_post_meta( $event_id, '_fge_requests_count', true );
	update_post_meta( $event_id, '_fge_requests_count', $current_count + 1 );

	do_action( 'fge_request_created', $request_id );

	wp_redirect( esc_url_raw( $redirect_base . '?anfrage=danke#event-anfrage' ), 303 );
	exit;
}
add_action( 'init', 'fge_handle_anfrage_submit', 10 );

// ── AP8: Specific Event Form Renderer ─────────────────────────────────────────

function fge_render_anfrage_form( int $event_id ): void {

	if ( isset( $_GET['anfrage'] ) && sanitize_key( $_GET['anfrage'] ) === 'danke' ) {
		?>
		<div class="fg-anfrage-success">
			<div class="fg-anfrage-success-icon" aria-hidden="true">
				<?php echo fge_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
			<h2 class="fg-anfrage-success-title">Anfrage erhalten!</h2>
			<p class="fg-anfrage-success-body">Vielen Dank — wir prüfen die Verfügbarkeit und melden uns schnell bei dir zurück.</p>
		</div>
		<?php
		return;
	}

	[ 'errors' => $errors, 'data' => $saved_data ] = fge_load_form_state( 'anfrage_err' );
	[
		'v'         => $v,
		'checked'   => $checked,
		'err_html'  => $err_html,
		'has_error' => $has_error,
	] = fge_form_helpers( $saved_data, $errors );

	?>
	<h2 class="fg-anfrage-title">Event anfragen</h2>
	<p class="fg-anfrage-sub">Füll das Formular aus — wir prüfen die Verfügbarkeit und melden uns schnell zurück.</p>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="fg-form-errors-banner" role="alert">
			<strong>Bitte prüf deine Eingaben.</strong> Einige Pflichtfelder sind noch nicht ausgefüllt.
		</div>
	<?php endif; ?>

	<form class="fg-anfrage-form" method="post" action="<?php echo esc_url( get_permalink( $event_id ) ); ?>" novalidate>
		<input type="hidden" name="fge_action" value="anfrage_submit">
		<input type="hidden" name="fge_event_id" value="<?php echo esc_attr( $event_id ); ?>">
		<?php wp_nonce_field( 'fge_anfrage', 'fge_anfrage_nonce' ); ?>
		<input type="text" name="fge_hp_url" value="" style="display:none;position:absolute;left:-9999px;" tabindex="-1" autocomplete="off" aria-hidden="true">

		<?php /* Block 1 */ ?>
		<div class="fg-form-block">
			<p class="fg-form-block-title">Unternehmen</p>
			<div class="fg-form-row">
				<label class="fg-form-label" for="fge_company_name">Firmenname <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
				<div class="fg-form-field">
					<input class="fg-form-input<?php echo $has_error( 'fge_company_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="text" id="fge_company_name" name="fge_company_name" value="<?php echo $v( 'fge_company_name' ); ?>" autocomplete="organization" placeholder="z.B. Muster GmbH">
					<?php echo $err_html( 'fge_company_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
			<div class="fg-form-row">
				<label class="fg-form-label" for="fge_industry">Branche</label>
				<div class="fg-form-field">
					<input class="fg-form-input" type="text" id="fge_industry" name="fge_industry" value="<?php echo $v( 'fge_industry' ); ?>" placeholder="z.B. IT, Finance, Pharma">
				</div>
			</div>
		</div>

		<?php /* Block 2 */ ?>
		<div class="fg-form-block">
			<p class="fg-form-block-title">Ansprechpartner</p>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_contact_first_name">Vorname <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_contact_first_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="text" id="fge_contact_first_name" name="fge_contact_first_name" value="<?php echo $v( 'fge_contact_first_name' ); ?>" autocomplete="given-name">
						<?php echo $err_html( 'fge_contact_first_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_contact_last_name">Nachname <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_contact_last_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="text" id="fge_contact_last_name" name="fge_contact_last_name" value="<?php echo $v( 'fge_contact_last_name' ); ?>" autocomplete="family-name">
						<?php echo $err_html( 'fge_contact_last_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_contact_email">E-Mail <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_contact_email' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="email" id="fge_contact_email" name="fge_contact_email" value="<?php echo $v( 'fge_contact_email' ); ?>" autocomplete="email">
						<?php echo $err_html( 'fge_contact_email' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_contact_phone">Telefon</label>
					<div class="fg-form-field">
						<input class="fg-form-input" type="tel" id="fge_contact_phone" name="fge_contact_phone" value="<?php echo $v( 'fge_contact_phone' ); ?>" autocomplete="tel">
					</div>
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_contact_role">Funktion im Unternehmen</label>
					<div class="fg-form-field">
						<input class="fg-form-input" type="text" id="fge_contact_role" name="fge_contact_role" value="<?php echo $v( 'fge_contact_role' ); ?>" placeholder="z.B. HR Manager">
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_preferred_contact_method">Bevorzugter Kontaktweg</label>
					<div class="fg-form-field">
						<select class="fg-form-select" id="fge_preferred_contact_method" name="fge_preferred_contact_method">
							<option value="">— bitte wählen —</option>
							<option value="email" <?php selected( $saved_data['fge_preferred_contact_method'] ?? '', 'email' ); ?>>E-Mail</option>
							<option value="phone" <?php selected( $saved_data['fge_preferred_contact_method'] ?? '', 'phone' ); ?>>Telefon</option>
							<option value="any" <?php selected( $saved_data['fge_preferred_contact_method'] ?? '', 'any' ); ?>>Egal</option>
						</select>
					</div>
				</div>
			</div>
		</div>

		<?php /* Block 3 */ ?>
		<div class="fg-form-block">
			<p class="fg-form-block-title">Event Rahmen</p>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_expected_participants">Teilnehmeranzahl</label>
					<div class="fg-form-field">
						<input class="fg-form-input" type="number" id="fge_expected_participants" name="fge_expected_participants" value="<?php echo esc_attr( absint( $saved_data['fge_expected_participants'] ?? 0 ) ?: '' ); ?>" min="1" placeholder="z.B. 25">
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_event_goal">Anlass</label>
					<div class="fg-form-field">
						<select class="fg-form-select" id="fge_event_goal" name="fge_event_goal">
							<option value="">— bitte wählen —</option>
							<option value="teamevent" <?php selected( $saved_data['fge_event_goal'] ?? '', 'teamevent' ); ?>>Teamevent</option>
							<option value="kundenevent" <?php selected( $saved_data['fge_event_goal'] ?? '', 'kundenevent' ); ?>>Kundenevent</option>
							<option value="gesundheitstag" <?php selected( $saved_data['fge_event_goal'] ?? '', 'gesundheitstag' ); ?>>Gesundheitstag</option>
							<option value="offsite" <?php selected( $saved_data['fge_event_goal'] ?? '', 'offsite' ); ?>>Offsite / Retreat</option>
							<option value="sommerfest" <?php selected( $saved_data['fge_event_goal'] ?? '', 'sommerfest' ); ?>>Sommerfest</option>
							<option value="weihnachtsfeier" <?php selected( $saved_data['fge_event_goal'] ?? '', 'weihnachtsfeier' ); ?>>Weihnachtsfeier</option>
							<option value="anderes_event" <?php selected( $saved_data['fge_event_goal'] ?? '', 'anderes_event' ); ?>>Anderes</option>
						</select>
					</div>
				</div>
			</div>
			<div class="fg-form-row">
				<label class="fg-form-label" for="fge_budget_range">Budgetrahmen</label>
				<div class="fg-form-field">
					<input class="fg-form-input" type="text" id="fge_budget_range" name="fge_budget_range" value="<?php echo $v( 'fge_budget_range' ); ?>" placeholder="z.B. 2.000–4.000 €">
				</div>
			</div>
		</div>

		<?php /* Block 4 */ ?>
		<div class="fg-form-block">
			<p class="fg-form-block-title">Terminvorschläge</p>
			<div class="fg-form-row fg-form-row--3col">
				<div>
					<label class="fg-form-label" for="fge_preferred_date_1">Wunschtermin 1</label>
					<input class="fg-form-input" type="date" id="fge_preferred_date_1" name="fge_preferred_date_1" value="<?php echo $v( 'fge_preferred_date_1' ); ?>">
				</div>
				<div>
					<label class="fg-form-label" for="fge_preferred_date_2">Wunschtermin 2</label>
					<input class="fg-form-input" type="date" id="fge_preferred_date_2" name="fge_preferred_date_2" value="<?php echo $v( 'fge_preferred_date_2' ); ?>">
				</div>
				<div>
					<label class="fg-form-label" for="fge_preferred_date_3">Wunschtermin 3</label>
					<input class="fg-form-input" type="date" id="fge_preferred_date_3" name="fge_preferred_date_3" value="<?php echo $v( 'fge_preferred_date_3' ); ?>">
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_alternative_period">Alternativer Zeitraum</label>
					<input class="fg-form-input" type="text" id="fge_alternative_period" name="fge_alternative_period" value="<?php echo $v( 'fge_alternative_period' ); ?>" placeholder="z.B. September–Oktober 2026">
				</div>
				<div>
					<label class="fg-form-label" for="fge_preferred_time">Bevorzugte Uhrzeit</label>
					<select class="fg-form-select" id="fge_preferred_time" name="fge_preferred_time">
						<option value="">— bitte wählen —</option>
						<option value="morning" <?php selected( $saved_data['fge_preferred_time'] ?? '', 'morning' ); ?>>Vormittag</option>
						<option value="afternoon" <?php selected( $saved_data['fge_preferred_time'] ?? '', 'afternoon' ); ?>>Nachmittag</option>
						<option value="after_work" <?php selected( $saved_data['fge_preferred_time'] ?? '', 'after_work' ); ?>>After Work</option>
						<option value="full_day" <?php selected( $saved_data['fge_preferred_time'] ?? '', 'full_day' ); ?>>Ganztägig</option>
						<option value="open" <?php selected( $saved_data['fge_preferred_time'] ?? '', 'open' ); ?>>Offen</option>
					</select>
				</div>
			</div>
		</div>

		<?php fge_render_form_leistungen_block( $checked, $saved_data['fge_additional_wishes'] ?? '' ); ?>

		<?php /* Block 6 */ ?>
		<div class="fg-form-block">
			<p class="fg-form-block-title">Nachricht</p>
			<div class="fg-form-row">
				<label class="fg-form-label" for="fge_message">Nachricht an uns</label>
				<div class="fg-form-field">
					<textarea class="fg-form-textarea" id="fge_message" name="fge_message" rows="4" placeholder="Weitere Informationen oder Fragen"><?php echo esc_textarea( $saved_data['fge_message'] ?? '' ); ?></textarea>
				</div>
			</div>
			<div class="fg-form-consent">
				<label class="fg-form-check<?php echo isset( $errors['fge_privacy_consent'] ) ? ' fg-form-check--error' : ''; ?>">
					<input type="checkbox" name="fge_privacy_consent" value="1" <?php checked( $checked( 'fge_privacy_consent' ) ); ?>>
					<span>Ich stimme der <a href="#" class="fg-form-link">Datenschutzerklärung</a> zu und bin einverstanden, dass meine Daten zur Bearbeitung dieser Anfrage gespeichert werden. <span class="fg-form-required" aria-label="Pflichtfeld">*</span></span>
				</label>
				<?php echo $err_html( 'fge_privacy_consent' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		</div>

		<div class="fg-form-submit">
			<button type="submit" class="fg-btn fg-btn-brand fg-btn-lg">
				Anfrage absenden <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>
			<p class="fg-form-submit-note">Kein Spam. Keine Verpflichtung. Wir melden uns schnell zurück.</p>
		</div>

	</form>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// AP9 — GENERAL EVENT REQUEST FORM
// ══════════════════════════════════════════════════════════════════════════════

function fge_handle_general_anfrage_submit() {
	if ( ! isset( $_POST['fge_action'] ) || $_POST['fge_action'] !== 'general_anfrage_submit' ) {
		return;
	}

	if (
		! isset( $_POST['fge_general_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_general_nonce'] ) ), 'fge_general_anfrage' )
	) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}

	$return_url    = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['fge_return_url'] ?? '' ) ), home_url( '/' ) );
	$redirect_base = trailingslashit( $return_url );

	if ( ! empty( $_POST['fge_hp_url'] ) ) {
		wp_redirect( esc_url_raw( $redirect_base . '?anfrage=danke' ), 303 );
		exit;
	}

	$errors       = [];
	$company_name = sanitize_text_field( wp_unslash( $_POST['fge_company_name'] ?? '' ) );
	$company_city = sanitize_text_field( wp_unslash( $_POST['fge_company_city'] ?? '' ) );
	$company_region = sanitize_text_field( wp_unslash( $_POST['fge_company_region'] ?? '' ) );
	$first_name   = sanitize_text_field( wp_unslash( $_POST['fge_contact_first_name'] ?? '' ) );
	$last_name    = sanitize_text_field( wp_unslash( $_POST['fge_contact_last_name'] ?? '' ) );
	$email        = sanitize_email( wp_unslash( $_POST['fge_contact_email'] ?? '' ) );
	$phone        = sanitize_text_field( wp_unslash( $_POST['fge_contact_phone'] ?? '' ) );
	$participants = absint( $_POST['fge_expected_participants'] ?? 0 );
	$desired_region  = sanitize_text_field( wp_unslash( $_POST['fge_desired_region'] ?? '' ) );
	$alt_period   = sanitize_text_field( wp_unslash( $_POST['fge_alternative_period'] ?? '' ) );

	if ( $company_name === '' ) {
		$errors['fge_company_name'] = 'Bitte gib den Firmennamen an.';
	}
	if ( $company_city === '' ) {
		$errors['fge_company_city'] = 'Bitte gib den Unternehmensort an.';
	}
	if ( $company_region === '' ) {
		$errors['fge_company_region'] = 'Bitte gib das Bundesland oder die Region an.';
	}
	if ( $first_name === '' ) {
		$errors['fge_contact_first_name'] = 'Bitte gib deinen Vornamen an.';
	}
	if ( $last_name === '' ) {
		$errors['fge_contact_last_name'] = 'Bitte gib deinen Nachnamen an.';
	}
	if ( $email === '' || ! is_email( $email ) ) {
		$errors['fge_contact_email'] = 'Bitte gib eine gültige E-Mail-Adresse an.';
	}
	if ( $phone === '' ) {
		$errors['fge_contact_phone'] = 'Bitte gib deine Telefonnummer an.';
	}
	if ( $participants < 1 ) {
		$errors['fge_expected_participants'] = 'Bitte gib die erwartete Teilnehmerzahl an (mindestens 1).';
	}
	if ( $desired_region === '' ) {
		$errors['fge_desired_region'] = 'Bitte gib die gewünschte Region an.';
	}
	if ( $alt_period === '' ) {
		$errors['fge_alternative_period'] = 'Bitte gib einen Zeitraum oder Wunschtermin an.';
	}

	if ( ! empty( $errors ) ) {
		$token = wp_generate_uuid4();
		set_transient( 'fge_form_err_' . $token, [ 'errors' => $errors, 'data' => wp_unslash( $_POST ) ], 300 );
		wp_redirect( esc_url_raw( $redirect_base . '?general_err=' . rawurlencode( $token ) . '#general-anfrage' ), 303 );
		exit;
	}

	$request_id = wp_insert_post( [
		'post_type'   => 'firmengolf_request',
		'post_status' => 'publish',
		'post_title'  => 'Auto Draft',
	] );

	if ( is_wp_error( $request_id ) || $request_id === 0 ) {
		wp_redirect( esc_url_raw( $redirect_base . '?anfrage=fehler' ), 303 );
		exit;
	}

	$allowed_contact_methods = [ 'phone', 'email', 'any' ];
	$allowed_event_goals     = [ 'teamevent', 'kundenevent', 'gesundheitstag', 'offsite', 'sommerfest', 'weihnachtsfeier', 'anderes_event' ];
	$allowed_preferred_times = [ 'morning', 'afternoon', 'after_work', 'full_day', 'open' ];

	$san_select = static function( string $key, array $allowed ): string {
		$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		return in_array( $val, $allowed, true ) ? $val : '';
	};

	$now = current_datetime()->format( 'Y-m-d H:i:s' );

	update_post_meta( $request_id, '_fge_request_type',        'general_event_request' );
	update_post_meta( $request_id, '_fge_request_status',      'neu' );
	update_post_meta( $request_id, '_fge_assigned_event_id',   0 );
	update_post_meta( $request_id, '_fge_assigned_partner_id', 0 );
	update_post_meta( $request_id, '_fge_source',              'general_landingpage' );
	add_post_meta( $request_id, '_fge_request_date',      $now, true );
	update_post_meta( $request_id, '_fge_last_status_change', $now );
	add_post_meta( $request_id, '_fge_consent_timestamp', $now, true );

	update_post_meta( $request_id, '_fge_company_name',    $company_name );
	update_post_meta( $request_id, '_fge_company_city',    $company_city );
	update_post_meta( $request_id, '_fge_company_region',  $company_region );
	update_post_meta( $request_id, '_fge_industry',        sanitize_text_field( wp_unslash( $_POST['fge_industry'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_company_website', esc_url_raw( wp_unslash( $_POST['fge_company_website'] ?? '' ) ) );

	update_post_meta( $request_id, '_fge_contact_first_name',       $first_name );
	update_post_meta( $request_id, '_fge_contact_last_name',        $last_name );
	update_post_meta( $request_id, '_fge_contact_email',            $email );
	update_post_meta( $request_id, '_fge_contact_phone',            $phone );
	update_post_meta( $request_id, '_fge_contact_role',             sanitize_text_field( wp_unslash( $_POST['fge_contact_role'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_preferred_contact_method', $san_select( 'fge_preferred_contact_method', $allowed_contact_methods ) );

	update_post_meta( $request_id, '_fge_expected_participants', $participants );
	update_post_meta( $request_id, '_fge_participants_range',    sanitize_text_field( wp_unslash( $_POST['fge_participants_range'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_event_goal',            $san_select( 'fge_event_goal', $allowed_event_goals ) );
	update_post_meta( $request_id, '_fge_desired_region',        $desired_region );
	update_post_meta( $request_id, '_fge_budget_range',          sanitize_text_field( wp_unslash( $_POST['fge_budget_range'] ?? '' ) ) );

	update_post_meta( $request_id, '_fge_preferred_date_1',   sanitize_text_field( wp_unslash( $_POST['fge_preferred_date_1'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_preferred_date_2',   sanitize_text_field( wp_unslash( $_POST['fge_preferred_date_2'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_preferred_date_3',   sanitize_text_field( wp_unslash( $_POST['fge_preferred_date_3'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_alternative_period', $alt_period );
	update_post_meta( $request_id, '_fge_preferred_time',     $san_select( 'fge_preferred_time', $allowed_preferred_times ) );

	fge_save_leistungen_meta( $request_id );

	update_post_meta( $request_id, '_fge_message',    sanitize_textarea_field( wp_unslash( $_POST['fge_message'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_kit_opt_in', isset( $_POST['fge_kit_opt_in'] ) ? 1 : 0 );
	update_post_meta( $request_id, '_fge_kit_status',     'not_sent' );
	update_post_meta( $request_id, '_fge_hubspot_status', 'not_sent' );

	do_action( 'fge_request_created', $request_id );

	wp_redirect( esc_url_raw( $redirect_base . '?anfrage=danke#general-anfrage' ), 303 );
	exit;
}
add_action( 'init', 'fge_handle_general_anfrage_submit', 10 );

// ── AP9: General Event Form Renderer ─────────────────────────────────────────

function fge_render_general_anfrage_form(): void {

	if ( isset( $_GET['anfrage'] ) && sanitize_key( $_GET['anfrage'] ) === 'danke' ) {
		?>
		<div class="fg-anfrage-success">
			<div class="fg-anfrage-success-icon" aria-hidden="true">
				<?php echo fge_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
			<h2 class="fg-anfrage-success-title">Anfrage erhalten!</h2>
			<p class="fg-anfrage-success-body">Danke — wir prüfen passende Eventformate und melden uns persönlich bei dir.</p>
		</div>
		<?php
		return;
	}

	[ 'errors' => $errors, 'data' => $saved_data ] = fge_load_form_state( 'general_err' );
	[
		'v'         => $v,
		'checked'   => $checked,
		'err_html'  => $err_html,
		'has_error' => $has_error,
	] = fge_form_helpers( $saved_data, $errors );

	$page_url = get_permalink() ?: home_url( '/' );

	?>
	<?php if ( ! empty( $errors ) ) : ?>
		<div class="fg-form-errors-banner" role="alert">
			<strong>Bitte prüf deine Eingaben.</strong> Einige Pflichtfelder sind noch nicht ausgefüllt.
		</div>
	<?php endif; ?>

	<form class="fg-anfrage-form" method="post" action="<?php echo esc_url( $page_url ); ?>#general-anfrage" novalidate>
		<input type="hidden" name="fge_action" value="general_anfrage_submit">
		<input type="hidden" name="fge_return_url" value="<?php echo esc_attr( $page_url ); ?>">
		<?php wp_nonce_field( 'fge_general_anfrage', 'fge_general_nonce' ); ?>
		<input type="text" name="fge_hp_url" value="" style="display:none;position:absolute;left:-9999px;" tabindex="-1" autocomplete="off" aria-hidden="true">

		<?php /* Block 1: Unternehmen */ ?>
		<div class="fg-form-block">
			<p class="fg-form-block-title">Unternehmen</p>
			<div class="fg-form-row">
				<label class="fg-form-label" for="fge_company_name">Unternehmen <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
				<div class="fg-form-field">
					<input class="fg-form-input<?php echo $has_error( 'fge_company_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="text" id="fge_company_name" name="fge_company_name" value="<?php echo $v( 'fge_company_name' ); ?>" autocomplete="organization" placeholder="z.B. Muster GmbH">
					<?php echo $err_html( 'fge_company_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_company_city">Ort <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_company_city' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="text" id="fge_company_city" name="fge_company_city" value="<?php echo $v( 'fge_company_city' ); ?>" autocomplete="address-level2" placeholder="z.B. München">
						<?php echo $err_html( 'fge_company_city' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_company_region">Bundesland / Region <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_company_region' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="text" id="fge_company_region" name="fge_company_region" value="<?php echo $v( 'fge_company_region' ); ?>" placeholder="z.B. Bayern">
						<?php echo $err_html( 'fge_company_region' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_industry">Branche</label>
					<div class="fg-form-field">
						<input class="fg-form-input" type="text" id="fge_industry" name="fge_industry" value="<?php echo $v( 'fge_industry' ); ?>" placeholder="z.B. IT, Finance, Pharma">
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_company_website">Website</label>
					<div class="fg-form-field">
						<input class="fg-form-input" type="url" id="fge_company_website" name="fge_company_website" value="<?php echo $v( 'fge_company_website' ); ?>" autocomplete="url" placeholder="https://...">
					</div>
				</div>
			</div>
		</div>

		<?php /* Block 2: Ansprechpartner */ ?>
		<div class="fg-form-block">
			<p class="fg-form-block-title">Ansprechpartner</p>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_contact_first_name">Vorname <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_contact_first_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="text" id="fge_contact_first_name" name="fge_contact_first_name" value="<?php echo $v( 'fge_contact_first_name' ); ?>" autocomplete="given-name">
						<?php echo $err_html( 'fge_contact_first_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_contact_last_name">Nachname <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_contact_last_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="text" id="fge_contact_last_name" name="fge_contact_last_name" value="<?php echo $v( 'fge_contact_last_name' ); ?>" autocomplete="family-name">
						<?php echo $err_html( 'fge_contact_last_name' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_contact_email">E-Mail <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_contact_email' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="email" id="fge_contact_email" name="fge_contact_email" value="<?php echo $v( 'fge_contact_email' ); ?>" autocomplete="email">
						<?php echo $err_html( 'fge_contact_email' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_contact_phone">Telefon <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_contact_phone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="tel" id="fge_contact_phone" name="fge_contact_phone" value="<?php echo $v( 'fge_contact_phone' ); ?>" autocomplete="tel">
						<?php echo $err_html( 'fge_contact_phone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_contact_role">Rolle im Unternehmen</label>
					<div class="fg-form-field">
						<input class="fg-form-input" type="text" id="fge_contact_role" name="fge_contact_role" value="<?php echo $v( 'fge_contact_role' ); ?>" placeholder="z.B. HR Manager">
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_preferred_contact_method">Bevorzugte Kontaktart</label>
					<div class="fg-form-field">
						<select class="fg-form-select" id="fge_preferred_contact_method" name="fge_preferred_contact_method">
							<option value="any" <?php selected( $saved_data['fge_preferred_contact_method'] ?? 'any', 'any' ); ?>>Egal</option>
							<option value="email" <?php selected( $saved_data['fge_preferred_contact_method'] ?? '', 'email' ); ?>>E-Mail</option>
							<option value="phone" <?php selected( $saved_data['fge_preferred_contact_method'] ?? '', 'phone' ); ?>>Telefon</option>
						</select>
					</div>
				</div>
			</div>
		</div>

		<?php /* Block 3: Event Rahmen */ ?>
		<div class="fg-form-block">
			<p class="fg-form-block-title">Event Rahmen</p>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_expected_participants">Teilnehmerzahl erwartet <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_expected_participants' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="number" id="fge_expected_participants" name="fge_expected_participants" value="<?php echo esc_attr( absint( $saved_data['fge_expected_participants'] ?? 0 ) ?: '' ); ?>" min="1" placeholder="z.B. 25">
						<?php echo $err_html( 'fge_expected_participants' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_participants_range">Teilnehmerzahl Spanne</label>
					<div class="fg-form-field">
						<input class="fg-form-input" type="text" id="fge_participants_range" name="fge_participants_range" value="<?php echo $v( 'fge_participants_range' ); ?>" placeholder="z.B. 20–40 Personen">
					</div>
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_event_goal">Eventziel</label>
					<div class="fg-form-field">
						<select class="fg-form-select" id="fge_event_goal" name="fge_event_goal">
							<option value="">— bitte wählen —</option>
							<option value="teamevent" <?php selected( $saved_data['fge_event_goal'] ?? '', 'teamevent' ); ?>>Teamevent</option>
							<option value="kundenevent" <?php selected( $saved_data['fge_event_goal'] ?? '', 'kundenevent' ); ?>>Kundenevent</option>
							<option value="gesundheitstag" <?php selected( $saved_data['fge_event_goal'] ?? '', 'gesundheitstag' ); ?>>Gesundheitstag</option>
							<option value="offsite" <?php selected( $saved_data['fge_event_goal'] ?? '', 'offsite' ); ?>>Offsite / Retreat</option>
							<option value="sommerfest" <?php selected( $saved_data['fge_event_goal'] ?? '', 'sommerfest' ); ?>>Sommerfest</option>
							<option value="weihnachtsfeier" <?php selected( $saved_data['fge_event_goal'] ?? '', 'weihnachtsfeier' ); ?>>Weihnachtsfeier</option>
							<option value="anderes_event" <?php selected( $saved_data['fge_event_goal'] ?? '', 'anderes_event' ); ?>>Anderes</option>
						</select>
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_budget_range">Budgetrahmen</label>
					<div class="fg-form-field">
						<input class="fg-form-input" type="text" id="fge_budget_range" name="fge_budget_range" value="<?php echo $v( 'fge_budget_range' ); ?>" placeholder="z.B. 2.000–4.000 €">
					</div>
				</div>
			</div>
			<div class="fg-form-row">
				<label class="fg-form-label" for="fge_desired_region">Gewünschte Region <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
				<div class="fg-form-field">
					<input class="fg-form-input<?php echo $has_error( 'fge_desired_region' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="text" id="fge_desired_region" name="fge_desired_region" value="<?php echo $v( 'fge_desired_region' ); ?>" placeholder="z.B. Bayern, Rhein-Main, deutschlandweit">
					<?php echo $err_html( 'fge_desired_region' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
		</div>

		<?php /* Block 4: Termine */ ?>
		<div class="fg-form-block">
			<p class="fg-form-block-title">Termine und Zeitraum</p>
			<div class="fg-form-row fg-form-row--3col">
				<div>
					<label class="fg-form-label" for="fge_preferred_date_1">Wunschtermin 1</label>
					<input class="fg-form-input" type="date" id="fge_preferred_date_1" name="fge_preferred_date_1" value="<?php echo $v( 'fge_preferred_date_1' ); ?>">
				</div>
				<div>
					<label class="fg-form-label" for="fge_preferred_date_2">Wunschtermin 2</label>
					<input class="fg-form-input" type="date" id="fge_preferred_date_2" name="fge_preferred_date_2" value="<?php echo $v( 'fge_preferred_date_2' ); ?>">
				</div>
				<div>
					<label class="fg-form-label" for="fge_preferred_date_3">Wunschtermin 3</label>
					<input class="fg-form-input" type="date" id="fge_preferred_date_3" name="fge_preferred_date_3" value="<?php echo $v( 'fge_preferred_date_3' ); ?>">
				</div>
			</div>
			<div class="fg-form-row fg-form-row--2col">
				<div>
					<label class="fg-form-label" for="fge_alternative_period">Alternativer Zeitraum <span class="fg-form-required" aria-label="Pflichtfeld">*</span></label>
					<div class="fg-form-field">
						<input class="fg-form-input<?php echo $has_error( 'fge_alternative_period' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" type="text" id="fge_alternative_period" name="fge_alternative_period" value="<?php echo $v( 'fge_alternative_period' ); ?>" placeholder="z.B. Juni 2026, Freitag Nachmittag">
						<?php echo $err_html( 'fge_alternative_period' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
				<div>
					<label class="fg-form-label" for="fge_preferred_time">Uhrzeit Wunsch</label>
					<select class="fg-form-select" id="fge_preferred_time" name="fge_preferred_time">
						<option value="open" <?php selected( $saved_data['fge_preferred_time'] ?? 'open', 'open' ); ?>>Offen</option>
						<option value="morning" <?php selected( $saved_data['fge_preferred_time'] ?? '', 'morning' ); ?>>Vormittag</option>
						<option value="afternoon" <?php selected( $saved_data['fge_preferred_time'] ?? '', 'afternoon' ); ?>>Nachmittag</option>
						<option value="after_work" <?php selected( $saved_data['fge_preferred_time'] ?? '', 'after_work' ); ?>>After Work</option>
						<option value="full_day" <?php selected( $saved_data['fge_preferred_time'] ?? '', 'full_day' ); ?>>Ganztägig</option>
					</select>
				</div>
			</div>
		</div>

		<?php /* Block 5: Leistungen (shared) */ ?>
		<?php fge_render_form_leistungen_block( $checked, $saved_data['fge_additional_wishes'] ?? '' ); ?>

		<?php /* Block 6: Nachricht + Einwilligung */ ?>
		<div class="fg-form-block">
			<p class="fg-form-block-title">Nachricht</p>
			<div class="fg-form-row">
				<label class="fg-form-label" for="fge_message">Nachricht oder Sonderwünsche</label>
				<div class="fg-form-field">
					<textarea class="fg-form-textarea" id="fge_message" name="fge_message" rows="4" placeholder="Weitere Informationen oder besondere Anforderungen"><?php echo esc_textarea( $saved_data['fge_message'] ?? '' ); ?></textarea>
				</div>
			</div>
			<div class="fg-form-consent" style="margin-top:16px;">
				<label class="fg-form-check">
					<input type="checkbox" name="fge_kit_opt_in" value="1" <?php checked( $checked( 'fge_kit_opt_in' ) ); ?>>
					<span>Ich möchte weitere Informationen zu Firmengolf Events erhalten.</span>
				</label>
			</div>
			<p class="fg-form-privacy-note">Mit dem Absenden der Anfrage verarbeiten wir deine Angaben zur Bearbeitung der Anfrage. Weitere Informationen findest du in unserer <a href="#" class="fg-form-link">Datenschutzerklärung</a>.</p>
		</div>

		<div class="fg-form-submit">
			<button type="submit" class="fg-btn fg-btn-brand fg-btn-lg">
				Anfrage absenden <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>
			<p class="fg-form-submit-note">Kein Spam. Keine Verpflichtung. Wir melden uns persönlich zurück.</p>
		</div>

	</form>
	<?php
}

// ══════════════════════════════════════════════════════════════════════════════
// MODAL ANFRAGE — AJAX handler (logged-out + logged-in)
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_fge_modal_anfrage',        'fge_ajax_modal_anfrage' );
add_action( 'wp_ajax_nopriv_fge_modal_anfrage', 'fge_ajax_modal_anfrage' );

function fge_ajax_modal_anfrage(): void {
	check_ajax_referer( 'fge_modal_anfrage', 'nonce' );

	$event_id  = absint( $_POST['event_id'] ?? 0 );
	$date_pref = sanitize_text_field( wp_unslash( $_POST['date_pref'] ?? '' ) );
	$group     = sanitize_text_field( wp_unslash( $_POST['group_size'] ?? '' ) );
	$notes     = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
	$first     = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
	$last      = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$company   = sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) );
	$phone     = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );

	if ( ! $email || ! is_email( $email ) || ! $first || ! $last || ! $company ) {
		wp_send_json_error( [ 'message' => 'Pflichtfelder fehlen.' ], 422 );
	}

	$event_title = $event_id > 0 ? get_the_title( $event_id ) : '–';
	$ref         = 'FG-26-' . strtoupper( substr( wp_generate_uuid4(), 0, 6 ) );

	// Save as request post
	wp_insert_post( [
		'post_type'   => 'firmengolf_request',
		'post_status' => 'publish',
		'post_title'  => $ref . ' · ' . $first . ' ' . $last,
		'meta_input'  => [
			'_fge_ref'        => $ref,
			'_fge_event_id'   => $event_id,
			'_fge_first_name' => $first,
			'_fge_last_name'  => $last,
			'_fge_email'      => $email,
			'_fge_company'    => $company,
			'_fge_phone'      => $phone,
			'_fge_date_pref'  => $date_pref,
			'_fge_group_size' => $group,
			'_fge_notes'      => $notes,
		],
	] );

	// Notify admin
	wp_mail(
		get_option( 'admin_email' ),
		'Neue Event-Anfrage: ' . $ref . ' · ' . $event_title,
		"Ref: $ref\nEvent: $event_title\nDatum: $date_pref\nGruppe: $group\nNotizen: $notes\n\n$first $last\n$company\n$email\n$phone"
	);

	// Confirm to requester
	wp_mail(
		$email,
		'Deine Anfrage bei Firmengolf · ' . $ref,
		"Hallo $first,\n\ndeine Anfrage für „$event_title“ ist bei uns eingegangen.\nWir melden uns innerhalb von 24 Stunden zurück.\n\nReferenznummer: $ref\n\nBis gleich,\nDein Firmengolf-Team"
	);

	wp_send_json_success( [
		'ref'         => $ref,
		'event_title' => $event_title,
		'date_pref'   => $date_pref,
		'group_size'  => $group,
		'venue'       => get_post_meta( $event_id, '_fge_event_location', true ),
		'format'      => get_post_meta( $event_id, '_fge_event_type', true ),
	] );
}
