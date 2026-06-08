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
	$add_wish  = sanitize_textarea_field( wp_unslash( $_POST['add_wishes'] ?? '' ) );
	$first     = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
	$last      = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$company   = sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) );
	$phone     = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$city      = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );

	if ( ! $email || ! is_email( $email ) || ! $first || ! $last || ! $company ) {
		wp_send_json_error( [ 'message' => 'Pflichtfelder fehlen.' ], 422 );
	}

	$partner_id  = (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true );
	$event_title = $event_id > 0 ? get_the_title( $event_id ) : '–';
	$ref = fge_generate_request_ref();

	$request_id = wp_insert_post( [
		'post_type'   => 'firmengolf_request',
		'post_status' => 'publish',
		'post_title'  => $ref . ' · ' . $first . ' ' . $last,
	] );
	if ( is_wp_error( $request_id ) || ! $request_id ) {
		wp_send_json_error( [ 'message' => 'Anfrage konnte nicht gespeichert werden.' ], 500 );
	}

	// Core / routing (canonical request fields — same as the PRG handler)
	update_post_meta( $request_id, '_fge_request_type',        'specific_event' );
	update_post_meta( $request_id, '_fge_request_status',      'neu' );
	update_post_meta( $request_id, '_fge_assigned_event_id',   $event_id );
	update_post_meta( $request_id, '_fge_assigned_partner_id', $partner_id );
	update_post_meta( $request_id, '_fge_source',              'event_page' );
	update_post_meta( $request_id, '_fge_ref',                 $ref );
	add_post_meta( $request_id, '_fge_request_date',      current_datetime()->format( 'Y-m-d H:i:s' ), true );
	add_post_meta( $request_id, '_fge_consent_timestamp', current_datetime()->format( 'Y-m-d H:i:s' ), true );

	// Company + contact
	update_post_meta( $request_id, '_fge_company_name',       $company );
	update_post_meta( $request_id, '_fge_company_city',       $city );
	update_post_meta( $request_id, '_fge_contact_first_name', $first );
	update_post_meta( $request_id, '_fge_contact_last_name',  $last );
	update_post_meta( $request_id, '_fge_contact_email',      $email );
	update_post_meta( $request_id, '_fge_contact_phone',      $phone );

	// Event framework
	update_post_meta( $request_id, '_fge_expected_participants', absint( preg_replace( '/\D/', '', $group ) ) );
	update_post_meta( $request_id, '_fge_alternative_period',    $date_pref );
	// Wunschtermine (1–3) → speisen die Termin-Abstimmung (fge_request_responses / scheduling).
	update_post_meta( $request_id, '_fge_preferred_date_1', sanitize_text_field( wp_unslash( $_POST['date1'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_preferred_date_2', sanitize_text_field( wp_unslash( $_POST['date2'] ?? '' ) ) );
	update_post_meta( $request_id, '_fge_preferred_date_3', sanitize_text_field( wp_unslash( $_POST['date3'] ?? '' ) ) );
	$message = trim( $notes . ( $add_wish !== '' ? "\n\nWeitere Wünsche: " . $add_wish : '' ) );
	update_post_meta( $request_id, '_fge_message', $message );

	// Finetuning (Step 1) → canonical wants_* fields
	$allowed_wants = [ 'golf_teacher', 'meeting_room', 'breakfast', 'lunch', 'dinner', 'shuttle', 'branding', 'tournament_mode', 'bad_weather_alternative', 'individual_customization' ];
	$selected      = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['wants'] ?? '' ) ) ) ) );
	// Im Event inkludierte Leistungen serverseitig ergänzen (vertrauenswürdig aus event_id abgeleitet, da disabled Checkboxen nicht gesendet werden).
	$included      = function_exists( 'fge_event_included_wants' ) ? fge_event_included_wants( $event_id ) : [];
	$wants_all     = array_unique( array_merge( $selected, $included ) );
	foreach ( $allowed_wants as $w ) {
		update_post_meta( $request_id, '_fge_wants_' . $w, in_array( $w, $wants_all, true ) ? 1 : 0 );
	}

	// Tracking + downstream (emails, status) via the shared hook
	$current_count = (int) get_post_meta( $event_id, '_fge_requests_count', true );
	update_post_meta( $event_id, '_fge_requests_count', $current_count + 1 );

	do_action( 'fge_request_created', $request_id );

	wp_send_json_success( [
		'ref'         => $ref,
		'event_title' => $event_title,
		'date_pref'   => $date_pref,
		'group_size'  => $group,
	] );
}

// ── RequestWizard (Individuelle Events): allgemeine Anfrage per AJAX ─────────
add_action( 'wp_ajax_fge_general_request',        'fge_ajax_general_request' );
add_action( 'wp_ajax_nopriv_fge_general_request', 'fge_ajax_general_request' );

function fge_ajax_general_request(): void {
	check_ajax_referer( 'fge_general_request', 'nonce' );

	$t = static fn( $k ) => sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );

	$occasion = $t( 'occasion' );
	$first    = $t( 'first_name' );
	$last     = $t( 'last_name' );
	$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$company  = $t( 'company' );

	// Quick-Mode kommt mit „Vor- und Nachname" in einem Feld → splitten.
	if ( $last === '' && strpos( $first, ' ' ) !== false ) {
		$parts = preg_split( '/\s+/', $first, 2 );
		$first = $parts[0];
		$last  = $parts[1] ?? '';
	}

	if ( ! $email || ! is_email( $email ) || $first === '' || $occasion === '' ) {
		wp_send_json_error( [ 'message' => 'Bitte Anlass, Name und gültige E-Mail angeben.' ], 422 );
	}

	$goal      = $t( 'goal' );
	$size      = absint( preg_replace( '/\D/', '', (string) ( $_POST['size'] ?? '' ) ) );
	$region    = $t( 'region' );
	$place     = $t( 'place' );
	$budget    = $t( 'budget' );
	$when      = $t( 'when' );
	$flex      = $t( 'flex' );
	$duration  = $t( 'duration' );
	$role      = $t( 'role' );
	$phone     = $t( 'phone' );
	$city      = $t( 'city' );
	$pref      = $t( 'contact_pref' );
	$notes     = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

	$dates = array_filter( array_map( $t, [ 'date1', 'date2', 'date3' ] ) );

	// Services kommen als kommaseparierte Wizard-Labels.
	$services = array_filter( array_map( 'trim', explode( '||', (string) wp_unslash( $_POST['services'] ?? '' ) ) ) );
	$services = array_map( 'sanitize_text_field', $services );

	$ref = fge_generate_request_ref();

	$request_id = wp_insert_post( [
		'post_type'   => 'firmengolf_request',
		'post_status' => 'publish',
		'post_title'  => $ref . ' · ' . trim( $first . ' ' . $last ),
	] );
	if ( is_wp_error( $request_id ) || ! $request_id ) {
		wp_send_json_error( [ 'message' => 'Anfrage konnte nicht gespeichert werden.' ], 500 );
	}

	// Core / routing
	update_post_meta( $request_id, '_fge_request_type',   'general_event_request' );
	update_post_meta( $request_id, '_fge_request_status', 'neu' );
	$allowed_sources = [ 'general_landingpage', 'general_anfrage_page' ];
	$req_source      = $t( 'source' );
	update_post_meta( $request_id, '_fge_source', in_array( $req_source, $allowed_sources, true ) ? $req_source : 'general_landingpage' );
	update_post_meta( $request_id, '_fge_ref',            $ref );
	add_post_meta( $request_id, '_fge_request_date',      current_datetime()->format( 'Y-m-d H:i:s' ), true );
	add_post_meta( $request_id, '_fge_consent_timestamp', current_datetime()->format( 'Y-m-d H:i:s' ), true );

	// Company + contact
	update_post_meta( $request_id, '_fge_company_name',       $company );
	update_post_meta( $request_id, '_fge_company_city',       $city );
	update_post_meta( $request_id, '_fge_contact_first_name', $first );
	update_post_meta( $request_id, '_fge_contact_last_name',  $last );
	update_post_meta( $request_id, '_fge_contact_email',      $email );
	update_post_meta( $request_id, '_fge_contact_phone',      $phone );
	update_post_meta( $request_id, '_fge_contact_role',       $role );
	$pref_method = [ 'E-Mail' => 'email', 'Telefon' => 'phone', 'Egal' => 'any' ][ $pref ] ?? 'any';
	update_post_meta( $request_id, '_fge_preferred_contact_method', $pref_method );

	// Event framework
	update_post_meta( $request_id, '_fge_expected_participants', $size );
	update_post_meta( $request_id, '_fge_desired_region',        $region );
	update_post_meta( $request_id, '_fge_place_wish',            $place );
	update_post_meta( $request_id, '_fge_budget_range',          $budget );
	$period = trim( $when . ( $flex !== '' ? ' · ' . $flex : '' ), ' ·' );
	if ( $dates ) {
		$period = trim( $period . ' · Wunschtermine: ' . implode( ', ', $dates ), ' ·' );
	}
	update_post_meta( $request_id, '_fge_alternative_period', $period );
	update_post_meta( $request_id, '_fge_preferred_date_1', $t( 'date1' ) );
	update_post_meta( $request_id, '_fge_preferred_date_2', $t( 'date2' ) );
	update_post_meta( $request_id, '_fge_preferred_date_3', $t( 'date3' ) );

	// Services → kanonische wants_* Flags; unbekannte fließen in individual_customization.
	$svc_map = [
		'Golflehrer / Coaching'    => 'golf_teacher',
		'Schnupperkurs'            => 'golf_teacher',
		'Firmenturnier'            => 'tournament_mode',
		'Putting-Challenge'        => 'tournament_mode',
		'Frühstück'                => 'breakfast',
		'Lunch'                    => 'lunch',
		'Abendessen'               => 'dinner',
		'Bar & Drinks'             => 'dinner',
		'Meetingraum'              => 'meeting_room',
		'Shuttle / Transport'      => 'shuttle',
		'Branding & Banner'        => 'branding',
		'Schlechtwetter-Alternative' => 'bad_weather_alternative',
	];
	$all_wants = [ 'golf_teacher', 'meeting_room', 'breakfast', 'lunch', 'dinner', 'shuttle', 'branding', 'tournament_mode', 'bad_weather_alternative', 'individual_customization' ];
	$set_wants = [];
	$has_other = false;
	foreach ( $services as $label ) {
		if ( isset( $svc_map[ $label ] ) ) {
			$set_wants[ $svc_map[ $label ] ] = true;
		} else {
			$has_other = true;
		}
	}
	if ( $has_other ) {
		$set_wants['individual_customization'] = true;
	}
	foreach ( $all_wants as $w ) {
		update_post_meta( $request_id, '_fge_wants_' . $w, isset( $set_wants[ $w ] ) ? 1 : 0 );
	}

	update_post_meta( $request_id, '_fge_additional_wishes', $services ? implode( ', ', $services ) : '' );

	$message = 'Anlass: ' . $occasion
		. ( $goal !== ''     ? "\nZiel: " . $goal : '' )
		. ( $duration !== '' ? "\nDauer: " . $duration : '' )
		. ( $region !== ''   ? "\nWunsch-Ort: " . $region : '' )
		. ( $place !== ''    ? "\nKonkreter Platz: " . $place : '' )
		. ( $pref !== ''     ? "\nKontakt bevorzugt: " . $pref : '' )
		. ( $services        ? "\nGewünschte Leistungen: " . implode( ', ', $services ) : '' )
		. ( $notes !== ''    ? "\n\n" . $notes : '' );
	update_post_meta( $request_id, '_fge_message', trim( $message ) );

	// Downstream: Mails + Status über den gemeinsamen Hook.
	do_action( 'fge_request_created', $request_id );

	wp_send_json_success( [
		'ref'      => $ref,
		'occasion' => $occasion,
		'size'     => $size,
		'company'  => $company,
		'email'    => $email,
	] );
}
