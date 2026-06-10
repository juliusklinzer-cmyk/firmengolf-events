<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// AP14 — PARTNER ONBOARDING
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'init', 'fge_onboarding_handle_step', 5 );

// ── Slide manifest ────────────────────────────────────────────────────────────
// The wizard mirrors _design_neu/partner-onboarding (STEPS array): 3 chapters,
// each opened by an intro slide. Navigation is by 1-based ordinal; field logic
// (render/save/validate) is dispatched by slide id, so order changes stay safe.

function fge_onboarding_manifest(): array {
	return [
		1  => [ 'id' => 'intro-1',  'chapter' => 1, 'kind' => 'intro' ],
		2  => [ 'id' => 'golftype', 'chapter' => 1, 'kind' => 'form', 'wide' => true ],
		3  => [ 'id' => 'basics',   'chapter' => 1, 'kind' => 'form' ],
		4  => [ 'id' => 'location', 'chapter' => 1, 'kind' => 'form' ],
		5  => [ 'id' => 'arrival',  'chapter' => 1, 'kind' => 'form' ],
		6  => [ 'id' => 'main',     'chapter' => 1, 'kind' => 'form' ],
		7  => [ 'id' => 'contacts', 'chapter' => 1, 'kind' => 'form', 'skippable' => true ],
		8  => [ 'id' => 'intro-2',  'chapter' => 2, 'kind' => 'intro' ],
		9  => [ 'id' => 'infra',    'chapter' => 2, 'kind' => 'form', 'wide' => true ],
		10 => [ 'id' => 'gastro',   'chapter' => 2, 'kind' => 'form', 'wide' => true ],
		11 => [ 'id' => 'capacity', 'chapter' => 2, 'kind' => 'form' ],
		12 => [ 'id' => 'formats',  'chapter' => 2, 'kind' => 'form', 'wide' => true ],
		13 => [ 'id' => 'intro-3',  'chapter' => 3, 'kind' => 'intro' ],
		14 => [ 'id' => 'avail',    'chapter' => 3, 'kind' => 'form' ],
		15 => [ 'id' => 'pricing',  'chapter' => 3, 'kind' => 'form' ],
		16 => [ 'id' => 'media',    'chapter' => 3, 'kind' => 'form' ],
		17 => [ 'id' => 'review',   'chapter' => 3, 'kind' => 'review' ],
	];
}

function fge_onboarding_total_slides(): int {
	return count( fge_onboarding_manifest() );
}

function fge_onboarding_slide( int $ordinal ): array {
	$m = fge_onboarding_manifest();
	return $m[ $ordinal ] ?? [];
}

function fge_onboarding_ordinal_of( string $id ): int {
	foreach ( fge_onboarding_manifest() as $ord => $slide ) {
		if ( $slide['id'] === $id ) {
			return $ord;
		}
	}
	return 0;
}

/** Ordinal at which the WP account is created (the main-contact slide). */
function fge_onboarding_account_ordinal(): int {
	return fge_onboarding_ordinal_of( 'main' );
}

/** Slides before account creation are addressed by URL token (no login yet). */
function fge_onboarding_uses_token( int $ordinal ): bool {
	return $ordinal < fge_onboarding_account_ordinal();
}

// ── Google Maps (location picker) ─────────────────────────────────────────────

/**
 * Google Maps JavaScript API key. Define FGE_GMAPS_API_KEY in wp-config.php
 * (or filter fge_gmaps_api_key). Empty = map disabled, plain fields remain.
 */
function fge_gmaps_api_key(): string {
	$key = defined( 'FGE_GMAPS_API_KEY' ) ? (string) FGE_GMAPS_API_KEY : '';
	return (string) apply_filters( 'fge_gmaps_api_key', $key );
}

/** Loads the Maps API + picker init only on the onboarding location slide. */
add_action( 'wp_enqueue_scripts', 'fge_onboarding_enqueue_map' );
function fge_onboarding_enqueue_map(): void {
	if ( ! is_page( 'partner-onboarding' ) ) {
		return;
	}
	$key = fge_gmaps_api_key();
	if ( '' === $key ) {
		return;
	}
	$step = max( 1, absint( $_GET['ob_step'] ?? 1 ) );
	if ( ( fge_onboarding_slide( $step )['id'] ?? '' ) !== 'location' ) {
		return;
	}

	$src = plugins_url( 'assets/js/fge-onboarding-map.js', FGE_DIR . 'firmengolf-events.php' );
	wp_enqueue_script( 'fge-ob-map', $src, [], FGE_VERSION, true );

	$pid = fge_onboarding_get_current_partner_id();
	wp_localize_script( 'fge-ob-map', 'FGE_OB_MAP', [
		'lat' => $pid > 0 ? (float) get_post_meta( $pid, '_fge_latitude', true ) : 0,
		'lng' => $pid > 0 ? (float) get_post_meta( $pid, '_fge_longitude', true ) : 0,
	] );

	$maps_url = add_query_arg(
		[ 'key' => rawurlencode( $key ), 'libraries' => 'places', 'callback' => 'fgeObMapInit', 'loading' => 'async', 'language' => 'de', 'region' => 'DE' ],
		'https://maps.googleapis.com/maps/api/js'
	);
	wp_enqueue_script( 'google-maps', $maps_url, [ 'fge-ob-map' ], null, true );
}

/**
 * Allowed formats + size/count limits for the onboarding media uploads.
 * Single source of truth — used by server validation, the JS preview/checks and the hint texts.
 */
function fge_onboarding_media_limits(): array {
	return [
		'mimes'           => [ 'image/jpeg', 'image/png', 'image/webp' ],
		'exts'            => 'JPG, PNG oder WebP',
		'logo'            => 1 * 1024 * 1024, // 1 MB
		'cover'           => 5 * 1024 * 1024, // 5 MB
		'gallery'         => 5 * 1024 * 1024, // 5 MB pro Foto
		'gallery_max'     => 6,
		'cover_min_width' => 1600,
	];
}

/** Loads the async Airbnb-style gallery widget only on the onboarding media slide. */
add_action( 'wp_enqueue_scripts', 'fge_onboarding_enqueue_media' );
function fge_onboarding_enqueue_media(): void {
	if ( ! is_page( 'partner-onboarding' ) ) {
		return;
	}
	$step = max( 1, absint( $_GET['ob_step'] ?? 1 ) );
	if ( ( fge_onboarding_slide( $step )['id'] ?? '' ) !== 'media' ) {
		return;
	}
	$pid = fge_onboarding_get_current_partner_id();
	if ( $pid > 0 ) {
		fge_media_enqueue( $pid );
	}
}

// ── URL / Routing helpers ─────────────────────────────────────────────────────

function fge_onboarding_page_url(): string {
	$page = get_page_by_path( 'partner-onboarding' );
	return $page ? trailingslashit( get_permalink( $page->ID ) ) : trailingslashit( home_url( '/partner-onboarding/' ) );
}

function fge_onboarding_step_url( int $step, string $token = '' ): string {
	$base = fge_onboarding_page_url();
	$args = [ 'ob_step' => $step ];
	if ( $token !== '' ) {
		$args['ob_token'] = $token;
	}
	return add_query_arg( $args, $base );
}

function fge_onboarding_get_token(): string {
	$t = sanitize_text_field( wp_unslash( $_GET['ob_token'] ?? $_POST['ob_token'] ?? '' ) );
	return preg_replace( '/[^a-f0-9]/', '', $t );
}

// ── Partner lookup ────────────────────────────────────────────────────────────

function fge_onboarding_get_partner_id_by_token( string $token ): int {
	if ( $token === '' ) {
		return 0;
	}
	$posts = get_posts( [
		'post_type'      => 'firmengolf_partner',
		'post_status'    => [ 'draft', 'publish', 'pending' ],
		'numberposts'    => 1,
		'meta_key'       => '_fge_onboarding_token',
		'meta_value'     => $token,
		'no_found_rows'  => true,
	] );
	return $posts ? (int) $posts[0]->ID : 0;
}

function fge_onboarding_get_current_partner_id(): int {
	// After step 4: user is logged in, find by user ID.
	if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
		$posts = get_posts( [
			'post_type'     => 'firmengolf_partner',
			'post_status'   => [ 'draft', 'publish', 'pending' ],
			'numberposts'   => 1,
			'meta_key'      => '_fge_assigned_wp_user_id',
			'meta_value'    => get_current_user_id(),
			'no_found_rows' => true,
		] );
		if ( $posts ) {
			return (int) $posts[0]->ID;
		}
	}
	// Before step 4 (or admin): use URL token.
	$token = fge_onboarding_get_token();
	if ( $token !== '' ) {
		return fge_onboarding_get_partner_id_by_token( $token );
	}
	return 0;
}

// ── Draft creation ────────────────────────────────────────────────────────────

function fge_onboarding_create_draft_partner(): int {
	$post_id = wp_insert_post( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => 'draft',
		'post_title'  => 'Neuer Golfplatz Partner (Onboarding)',
	] );
	if ( is_wp_error( $post_id ) ) {
		return 0;
	}
	$token = bin2hex( random_bytes( 16 ) );
	update_post_meta( $post_id, '_fge_onboarding_token', $token );
	update_post_meta( $post_id, '_fge_onboarding_step', 0 );
	update_post_meta( $post_id, '_fge_individual_availability_check', 1 );
	update_post_meta( $post_id, '_fge_default_markup_percent', 20.0 );
	return $post_id;
}

// ── Validation ────────────────────────────────────────────────────────────────

function fge_onboarding_validate( array $data, array $required ): array {
	$errors = [];
	foreach ( $required as $field => $label ) {
		$val = trim( $data[ $field ] ?? '' );
		if ( $val === '' ) {
			$errors[ $field ] = $label . ' ist ein Pflichtfeld.';
		}
	}
	return $errors;
}

// ── Progress helpers ──────────────────────────────────────────────────────────

function fge_onboarding_get_progress( int $partner_id ): int {
	return (int) get_post_meta( $partner_id, '_fge_onboarding_step', true );
}

function fge_onboarding_is_submittable( int $partner_id ): bool {
	$m = static function( string $key ) use ( $partner_id ): string {
		return (string) get_post_meta( $partner_id, '_fge_' . $key, true );
	};

	if ( $m( 'public_golfclub_name' ) === '' )     { return false; }
	if ( $m( 'legal_operator_name' ) === '' )      { return false; }
	if ( ! in_array( $m( 'golf_type' ), array_keys( fge_catalog_golf_types() ), true ) ) { return false; }
	if ( $m( 'main_contact_name' ) === '' )        { return false; }
	if ( ! is_email( $m( 'main_contact_email' ) ) ) { return false; }
	if ( $m( 'street' ) === '' )                   { return false; }
	if ( $m( 'postal_code' ) === '' )              { return false; }
	if ( $m( 'city' ) === '' )                     { return false; }
	if ( $m( 'federal_state' ) === '' )            { return false; }

	// Capacities (new model: _fge_cap array).
	$cap = get_post_meta( $partner_id, '_fge_cap', true );
	$cap = is_array( $cap ) ? $cap : [];
	$cap_min = (int) ( $cap['min'] ?? 0 );
	$cap_max = (int) ( $cap['max'] ?? 0 );
	if ( $cap_min <= 0 )            { return false; }
	if ( $cap_max < $cap_min )      { return false; }

	if ( $m( 'image_rights_confirmed' ) !== '1' ) { return false; }

	// Infrastructure (new model: _fge_infra array, ≥1 from catalog).
	$infra = get_post_meta( $partner_id, '_fge_infra', true );
	$infra = is_array( $infra ) ? array_intersect( $infra, fge_catalog_infra_ids() ) : [];
	return ! empty( $infra );
}

// ── Save step data ────────────────────────────────────────────────────────────

function fge_onboarding_save_slide( int $partner_id, string $id, array $post ): void {
	$s  = static function( string $k ) use ( $post ): string {
		return sanitize_text_field( wp_unslash( $post[ $k ] ?? '' ) );
	};
	$su = static function( string $k ) use ( $post ): string {
		return esc_url_raw( wp_unslash( $post[ $k ] ?? '' ) );
	};
	$sa = static function( string $k ) use ( $post ): string {
		return sanitize_textarea_field( wp_unslash( $post[ $k ] ?? '' ) );
	};
	$allowed_states  = [
		'baden_wuerttemberg', 'bayern', 'berlin', 'brandenburg', 'bremen', 'hamburg',
		'hessen', 'mecklenburg_vorpommern', 'niedersachsen', 'nordrhein_westfalen',
		'rheinland_pfalz', 'saarland', 'sachsen', 'sachsen_anhalt',
		'schleswig_holstein', 'thueringen',
	];
	$allowed_days    = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
	$allowed_seasons = [ 'year_round', 'march_to_october', 'april_to_october', 'on_request' ];
	$allowed_billing = [ 'manual_clarification', 'invoice_from_partner_to_firmengolf', 'credit_note', 'other' ];
	$allowed_formats = array_keys( fge_get_event_format_options() );
	$allowed_infra   = array_keys( fge_get_infrastructure_options() );

	$san_group = static function( string $key, array $allowed ) use ( $post ): array {
		$raw = is_array( $post[ $key ] ?? null ) ? $post[ $key ] : [];
		return array_values( array_intersect( array_map( 'sanitize_text_field', $raw ), $allowed ) );
	};
	$san_select = static function( string $key, array $allowed ) use ( $post ): string {
		$val = sanitize_text_field( wp_unslash( $post[ $key ] ?? '' ) );
		return in_array( $val, $allowed, true ) ? $val : '';
	};

	// Infrastructure / Gastronomy share one `_fge_infra` array; each slide only
	// owns its group's ids, so saving one must not wipe the other's selections.
	$gastro_ids    = array_keys( fge_catalog_infra_groups()['Gastronomie'] ?? [] );
	$all_infra_ids = fge_catalog_infra_ids();
	$nongastro_ids = array_values( array_diff( $all_infra_ids, $gastro_ids ) );

	switch ( $id ) {
		case 'golftype':
			update_post_meta( $partner_id, '_fge_golf_type', $san_select( 'fge_golf_type', array_keys( fge_catalog_golf_types() ) ) );
			break;

		case 'basics':
			update_post_meta( $partner_id, '_fge_public_golfclub_name',    $s( 'fge_public_golfclub_name' ) );
			update_post_meta( $partner_id, '_fge_legal_operator_name',     $s( 'fge_legal_operator_name' ) );
			update_post_meta( $partner_id, '_fge_website_url',             $su( 'fge_website_url' ) );
			update_post_meta( $partner_id, '_fge_public_short_description', $sa( 'fge_public_short_description' ) );
			update_post_meta( $partner_id, '_fge_internal_note',           $sa( 'fge_internal_note' ) );
			// Sync post title to public name.
			wp_update_post( [ 'ID' => $partner_id, 'post_title' => $s( 'fge_public_golfclub_name' ) ] );
			break;

		case 'location':
			update_post_meta( $partner_id, '_fge_street',        $s( 'fge_street' ) );
			update_post_meta( $partner_id, '_fge_house_number',  $s( 'fge_house_number' ) );
			update_post_meta( $partner_id, '_fge_postal_code',   $s( 'fge_postal_code' ) );
			update_post_meta( $partner_id, '_fge_city',          $s( 'fge_city' ) );
			update_post_meta( $partner_id, '_fge_federal_state', $san_select( 'fge_federal_state', $allowed_states ) );
			// Map pin coordinates (set by the Google Maps picker).
			if ( isset( $post['fge_latitude'], $post['fge_longitude'] ) ) {
				$lat = (float) $post['fge_latitude'];
				$lng = (float) $post['fge_longitude'];
				update_post_meta( $partner_id, '_fge_latitude',  ( $lat >= -90 && $lat <= 90 && 0.0 !== $lat ) ? $lat : '' );
				update_post_meta( $partner_id, '_fge_longitude', ( $lng >= -180 && $lng <= 180 && 0.0 !== $lng ) ? $lng : '' );
			}
			// Manual link only used as fallback when the map is disabled.
			if ( isset( $post['fge_google_maps_url'] ) ) {
				update_post_meta( $partner_id, '_fge_google_maps_url', $su( 'fge_google_maps_url' ) );
			}
			break;

		case 'arrival':
			// Same keys as admin meta box / Platz view.
			update_post_meta( $partner_id, '_fge_poi_car',         $s( 'fge_poi_car' ) );
			update_post_meta( $partner_id, '_fge_poi_parking',     $s( 'fge_poi_parking' ) );
			update_post_meta( $partner_id, '_fge_poi_train',       $s( 'fge_poi_train' ) );
			update_post_meta( $partner_id, '_fge_poi_shuttle',     $s( 'fge_poi_shuttle' ) );
			update_post_meta( $partner_id, '_fge_arrival_estation', ( ( $post['fge_arrival_estation'] ?? '' ) === '1' ) ? 1 : 0 );
			break;

		case 'main':
			$first = $s( 'fge_contact_first_name' );
			$last  = $s( 'fge_contact_last_name' );
			update_post_meta( $partner_id, '_fge_main_contact_name',  trim( $first . ' ' . $last ) );
			update_post_meta( $partner_id, '_fge_main_contact_email', sanitize_email( wp_unslash( $post['fge_contact_email'] ?? '' ) ) );
			update_post_meta( $partner_id, '_fge_main_contact_phone', $s( 'fge_contact_phone' ) );
			update_post_meta( $partner_id, '_fge_main_contact_role',  $s( 'fge_contact_role' ) );
			break;

		case 'contacts':
			// Rebuild the partner's no-account contacts (fge_partner_contacts) from the submitted arrays.
			$c_names = is_array( $post['fge_contact_name'] ?? null ) ? $post['fge_contact_name'] : [];
			$c_mails = is_array( $post['fge_contact_email'] ?? null ) ? $post['fge_contact_email'] : [];
			$c_roles = is_array( $post['fge_contact_role'] ?? null ) ? $post['fge_contact_role'] : [];
			$c_perms = is_array( $post['fge_contact_permission'] ?? null ) ? $post['fge_contact_permission'] : [];
			foreach ( fge_contacts_get( $partner_id, true ) as $existing ) {
				if ( (int) $existing['user_id'] === 0 ) {
					fge_contact_delete( (int) $existing['id'], true );
				}
			}
			foreach ( $c_names as $i => $cn ) {
				$cm = sanitize_email( wp_unslash( $c_mails[ $i ] ?? '' ) );
				if ( '' === $cm || ! is_email( $cm ) ) {
					continue;
				}
				fge_contact_add( $partner_id, [
					'name'       => sanitize_text_field( wp_unslash( $cn ) ),
					'email'      => $cm,
					'role'       => sanitize_text_field( wp_unslash( $c_roles[ $i ] ?? '' ) ),
					'permission' => sanitize_text_field( wp_unslash( $c_perms[ $i ] ?? '' ) ),
				] );
			}
			break;

		case 'infra':
			// Replace only the non-gastro ids; keep any Gastronomie selections.
			$existing = (array) get_post_meta( $partner_id, '_fge_infra', true );
			$kept     = array_intersect( $existing, $gastro_ids );
			$submit   = array_intersect( $san_group( 'fge_infra', $all_infra_ids ), $nongastro_ids );
			update_post_meta( $partner_id, '_fge_infra', array_values( array_unique( array_merge( $kept, $submit ) ) ) );
			update_post_meta( $partner_id, '_fge_additional_equipment', $sa( 'fge_additional_equipment' ) );
			break;

		case 'gastro':
			// Replace only the Gastronomie ids; keep all other infrastructure.
			$existing = (array) get_post_meta( $partner_id, '_fge_infra', true );
			$kept     = array_intersect( $existing, $nongastro_ids );
			$submit   = array_intersect( $san_group( 'fge_infra', $all_infra_ids ), $gastro_ids );
			update_post_meta( $partner_id, '_fge_infra', array_values( array_unique( array_merge( $kept, $submit ) ) ) );
			break;

		case 'capacity':
			// New catalog model: single `_fge_cap` array keyed by cap_keys (min, max + conditional rows).
			$cap_in  = is_array( $post['fge_cap'] ?? null ) ? $post['fge_cap'] : [];
			$cap_out = [];
			foreach ( fge_catalog_cap_keys() as $ck ) {
				$cap_out[ $ck ] = absint( $cap_in[ $ck ] ?? 0 );
			}
			update_post_meta( $partner_id, '_fge_cap', $cap_out );
			break;

		case 'formats':
			update_post_meta( $partner_id, '_fge_event_formats', $san_group( 'fge_event_formats', $allowed_formats ) );
			break;

		case 'avail':
			update_post_meta( $partner_id, '_fge_preferred_event_days',         $san_group( 'fge_preferred_event_days', $allowed_days ) );
			update_post_meta( $partner_id, '_fge_evening_events_possible',       ( ( $post['fge_evening_events_possible'] ?? '' ) === '1' ) ? 1 : 0 );
			update_post_meta( $partner_id, '_fge_min_lead_time_days',            absint( $post['fge_min_lead_time_days'] ?? 14 ) );
			update_post_meta( $partner_id, '_fge_season',                        $san_select( 'fge_season', $allowed_seasons ) );
			update_post_meta( $partner_id, '_fge_individual_availability_check', ( ( $post['fge_individual_availability_check'] ?? '1' ) !== '0' ) ? 1 : 0 );
			break;

		case 'pricing':
			// Info-only (net stays net; markup added by Firmengolf; per-event net
			// prices are set in the portal). Nothing to persist.
			break;

		case 'media':
			// Photos + logo are uploaded asynchronously via the firmengolf/v1 REST routes
			// (fge-media-gallery.js). Here we only persist the rights confirmation + note.
			update_post_meta( $partner_id, '_fge_image_rights_confirmed', isset( $post['fge_image_rights_confirmed'] ) ? 1 : 0 );
			update_post_meta( $partner_id, '_fge_image_rights_note',      $sa( 'fge_image_rights_note' ) );
			break;
	}
}

// ── User creation / assignment ────────────────────────────────────────────────

function fge_onboarding_create_or_assign_user( string $email, string $first, string $last, string $role_label, string $phone, int $partner_id ): int {
	$existing = get_user_by( 'email', $email );
	if ( $existing ) {
		$user_id = $existing->ID;
	} else {
		$temp_pass = wp_generate_password( 12, false );
		$user_id   = wp_create_user( $email, $temp_pass, $email );
		if ( is_wp_error( $user_id ) ) {
			return 0;
		}
		wp_update_user( [
			'ID'         => $user_id,
			'first_name' => $first,
			'last_name'  => $last,
			'role'       => 'firmengolf_partner',
		] );
		// Notify new user with generated password.
		wp_new_user_notification( $user_id, null, 'user' );
	}

	// Link to partner post.
	update_post_meta( $partner_id, '_fge_assigned_wp_user_id', $user_id );
	update_post_meta( $partner_id, '_fge_main_contact_name',   trim( $first . ' ' . $last ) );
	update_post_meta( $partner_id, '_fge_main_contact_email',  sanitize_email( $email ) );
	update_post_meta( $partner_id, '_fge_main_contact_phone',  sanitize_text_field( $phone ) );
	update_post_meta( $partner_id, '_fge_main_contact_role',   sanitize_text_field( $role_label ) );
	update_post_meta( $partner_id, '_fge_partner_portal_enabled', 1 );

	return $user_id;
}

// ── Submit ────────────────────────────────────────────────────────────────────

function fge_onboarding_submit( int $partner_id ): void {
	update_post_meta( $partner_id, '_fge_partner_status', 'in_pruefung' );
	wp_update_post( [ 'ID' => $partner_id, 'post_status' => 'publish' ] );

	// Tracking defaults.
	foreach ( [ '_fge_published_events_count', '_fge_event_views_total', '_fge_requests_total', '_fge_bookings_total' ] as $key ) {
		if ( get_post_meta( $partner_id, $key, true ) === '' ) {
			add_post_meta( $partner_id, $key, 0, true );
		}
	}

	// Email.
	fge_send_onboarding_submitted_email( $partner_id );
}

// ── POST Handler ──────────────────────────────────────────────────────────────

function fge_onboarding_handle_step(): void {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		return;
	}
	if ( ( sanitize_key( $_POST['fge_ob_action'] ?? '' ) ) !== 'step_submit' ) {
		return;
	}

	$total = fge_onboarding_total_slides();
	$step  = absint( $_POST['ob_step'] ?? 0 );
	if ( $step < 1 || $step > $total ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_ob_nonce'] ?? '' ) ), 'fge_onboarding_step_' . $step ) ) {
		wp_die( 'Sicherheitsüberprüfung fehlgeschlagen.', '', [ 'response' => 403 ] );
	}

	$slide = fge_onboarding_slide( $step );
	$id    = $slide['id'] ?? '';
	$kind  = $slide['kind'] ?? 'form';
	// Whether the *next* URL still needs the token depends on the CURRENT slide:
	// the token carries forward until the account exists (main-contact submit).
	$next  = static function ( int $cur ) use ( $total ) {
		return [ min( $cur + 1, $total ), fge_onboarding_uses_token( $cur ) ];
	};

	// First intro slide: create the draft partner, then advance.
	if ( 'intro-1' === $id ) {
		$partner_id = fge_onboarding_create_draft_partner();
		if ( $partner_id <= 0 ) {
			wp_die( 'Fehler beim Erstellen des Profils.', '', [ 'response' => 500 ] );
		}
		$token = (string) get_post_meta( $partner_id, '_fge_onboarding_token', true );
		[ $n ] = $next( $step );
		wp_redirect( fge_onboarding_step_url( $n, $token ) );
		exit;
	}

	// All other slides: find existing partner.
	$partner_id = fge_onboarding_get_current_partner_id();
	if ( $partner_id <= 0 ) {
		wp_redirect( fge_onboarding_page_url() );
		exit;
	}

	$token = (string) get_post_meta( $partner_id, '_fge_onboarding_token', true );

	// Chapter intro slides (intro-2/3): nothing to save, just advance.
	if ( 'intro' === $kind ) {
		update_post_meta( $partner_id, '_fge_onboarding_step', max( fge_onboarding_get_progress( $partner_id ), $step ) );
		[ $n, $ntok ] = $next( $step );
		wp_redirect( fge_onboarding_step_url( $n, $ntok ? $token : '' ) );
		exit;
	}

	// Validate slide-specific required fields.
	$errors = fge_onboarding_validate_slide( $id, $_POST );
	if ( ! empty( $errors ) ) {
		$trans_key = 'fge_ob_err_' . $partner_id . '_' . $step;
		set_transient( $trans_key, $errors, 120 );
		wp_redirect( add_query_arg( 'ob_err', '1', fge_onboarding_step_url( $step, $token ) ) );
		exit;
	}

	// Main-contact slide: create/assign the WP account.
	if ( 'main' === $id ) {
		$email = sanitize_email( wp_unslash( $_POST['fge_contact_email'] ?? '' ) );
		$first = sanitize_text_field( wp_unslash( $_POST['fge_contact_first_name'] ?? '' ) );
		$last  = sanitize_text_field( wp_unslash( $_POST['fge_contact_last_name'] ?? '' ) );
		$role  = sanitize_text_field( wp_unslash( $_POST['fge_contact_role'] ?? '' ) );
		$phone = sanitize_text_field( wp_unslash( $_POST['fge_contact_phone'] ?? '' ) );

		if ( ! current_user_can( 'manage_options' ) ) {
			$user_id = fge_onboarding_create_or_assign_user( $email, $first, $last, $role, $phone, $partner_id );
			if ( $user_id > 0 ) {
				wp_set_auth_cookie( $user_id );
			}
		} else {
			fge_onboarding_save_slide( $partner_id, 'main', $_POST );
		}
		update_post_meta( $partner_id, '_fge_onboarding_step', max( fge_onboarding_get_progress( $partner_id ), $step ) );
		[ $n ] = $next( $step );
		wp_redirect( fge_onboarding_step_url( $n ) );
		exit;
	}

	// Review slide: submit.
	if ( 'review' === $id ) {
		if ( ! fge_onboarding_is_submittable( $partner_id ) ) {
			wp_redirect( add_query_arg( 'ob_missing', '1', fge_onboarding_step_url( $step ) ) );
			exit;
		}
		fge_onboarding_submit( $partner_id );
		wp_redirect( add_query_arg( 'ob_submitted', '1', fge_onboarding_page_url() ) );
		exit;
	}

	// All other form slides: save data.
	fge_onboarding_save_slide( $partner_id, $id, $_POST );
	update_post_meta( $partner_id, '_fge_onboarding_step', max( fge_onboarding_get_progress( $partner_id ), $step ) );

	// "Speichern & beenden".
	if ( isset( $_POST['fge_ob_save_exit'] ) ) {
		if ( ! fge_onboarding_uses_token( $step ) ) {
			wp_redirect( trailingslashit( home_url( '/partnerportal/' ) ) );
		} else {
			wp_redirect( add_query_arg(
				[ 'ob_saved' => '1', 'ob_step' => $step, 'ob_token' => $token ],
				fge_onboarding_page_url()
			) );
		}
		exit;
	}

	// Advance to next slide.
	[ $n, $ntok ] = $next( $step );
	wp_redirect( fge_onboarding_step_url( $n, $ntok ? $token : '' ) );
	exit;
}

// ── Per-step validation ───────────────────────────────────────────────────────

function fge_onboarding_validate_slide( string $id, array $post ): array {
	$s = static function( string $k ) use ( $post ): string {
		return trim( sanitize_text_field( wp_unslash( $post[ $k ] ?? '' ) ) );
	};

	switch ( $id ) {
		case 'golftype':
			return in_array( $s( 'fge_golf_type' ), array_keys( fge_catalog_golf_types() ), true )
				? []
				: [ 'fge_golf_type' => 'Bitte wähle aus, was dein Golfangebot am besten beschreibt.' ];

		case 'basics':
			return fge_onboarding_validate(
				[ 'fge_public_golfclub_name' => $s( 'fge_public_golfclub_name' ), 'fge_legal_operator_name' => $s( 'fge_legal_operator_name' ) ],
				[ 'fge_public_golfclub_name' => 'Golfplatz Name', 'fge_legal_operator_name' => 'Rechtlicher Betreibername' ]
			);

		case 'location':
			return fge_onboarding_validate(
				[
					'fge_street'       => $s( 'fge_street' ),
					'fge_postal_code'  => $s( 'fge_postal_code' ),
					'fge_city'         => $s( 'fge_city' ),
					'fge_federal_state' => $s( 'fge_federal_state' ),
				],
				[
					'fge_street'       => 'Straße',
					'fge_postal_code'  => 'PLZ',
					'fge_city'         => 'Ort',
					'fge_federal_state' => 'Bundesland',
				]
			);

		case 'main':
			$errors = fge_onboarding_validate(
				[
					'fge_contact_first_name' => $s( 'fge_contact_first_name' ),
					'fge_contact_last_name'  => $s( 'fge_contact_last_name' ),
					'fge_contact_email'      => sanitize_email( wp_unslash( $post['fge_contact_email'] ?? '' ) ),
				],
				[ 'fge_contact_first_name' => 'Vorname', 'fge_contact_last_name' => 'Nachname', 'fge_contact_email' => 'E-Mail' ]
			);
			if ( empty( $errors['fge_contact_email'] ) && ! is_email( sanitize_email( wp_unslash( $post['fge_contact_email'] ?? '' ) ) ) ) {
				$errors['fge_contact_email'] = 'Bitte gib eine gültige E-Mail-Adresse an.';
			}
			return $errors;

		case 'capacity':
			$cap = is_array( $post['fge_cap'] ?? null ) ? $post['fge_cap'] : [];
			$min = absint( $cap['min'] ?? 0 );
			$max = absint( $cap['max'] ?? 0 );
			$errors = [];
			if ( $min <= 0 ) { $errors['fge_cap_min'] = 'Teilnehmer-Minimum muss > 0 sein.'; }
			if ( $max <= 0 ) { $errors['fge_cap_max'] = 'Teilnehmer-Maximum muss > 0 sein.'; }
			if ( $max > 0 && $min > 0 && $max < $min ) { $errors['fge_cap_max'] = 'Maximum muss ≥ Minimum sein.'; }
			return $errors;

		case 'infra':
			$nongastro = array_values( array_diff( fge_catalog_infra_ids(), array_keys( fge_catalog_infra_groups()['Gastronomie'] ?? [] ) ) );
			$chosen    = is_array( $post['fge_infra'] ?? null ) ? array_intersect( array_map( 'sanitize_text_field', $post['fge_infra'] ), $nongastro ) : [];
			return empty( $chosen )
				? [ 'fge_infra' => 'Bitte wähle mindestens eine Ausstattung aus.' ]
				: [];

		default:
			return [];
	}
}

// ── Main renderer ─────────────────────────────────────────────────────────────

function fge_onboarding_render(): void {
	// Submitted confirmation page.
	if ( isset( $_GET['ob_submitted'] ) ) {
		fge_onboarding_render_confirmation();
		return;
	}

	// Saved & exit (before step 4): show resume URL.
	if ( isset( $_GET['ob_saved'] ) ) {
		$token = fge_onboarding_get_token();
		fge_onboarding_render_saved_notice( (int) ( $_GET['ob_step'] ?? 1 ), $token );
		return;
	}

	$total = fge_onboarding_total_slides();
	$step  = max( 1, absint( $_GET['ob_step'] ?? 1 ) );
	if ( $step > $total ) { $step = $total; }

	$slide = fge_onboarding_slide( $step );
	$id    = $slide['id'] ?? 'intro-1';
	$kind  = $slide['kind'] ?? 'form';
	$wide  = ! empty( $slide['wide'] );

	$partner_id = fge_onboarding_get_current_partner_id();
	$token      = $partner_id > 0 ? (string) get_post_meta( $partner_id, '_fge_onboarding_token', true ) : '';

	// Load saved values.
	$vals = fge_onboarding_get_saved_vals( $partner_id );

	// Load transient errors.
	$errors = [];
	if ( isset( $_GET['ob_err'] ) && $partner_id > 0 ) {
		$trans_key = 'fge_ob_err_' . $partner_id . '_' . $step;
		$errors    = (array) get_transient( $trans_key );
		delete_transient( $trans_key );
	}

	$save_exit_url = ( fge_onboarding_uses_token( $step ) && $token !== '' )
		? add_query_arg( [ 'ob_saved' => '1', 'ob_step' => $step, 'ob_token' => $token ], fge_onboarding_page_url() )
		: trailingslashit( home_url( '/partnerportal/' ) );
	?>
	<div class="ob-shell">
		<?php fge_onboarding_render_topbar( $save_exit_url, $step ); ?>
		<?php if ( 'intro' === $kind ) : ?>
		<main class="ob-stage is-intro">
			<?php fge_onboarding_render_intro( $id ); ?>
		</main>
		<?php fge_onboarding_render_footer( $step, $token ); ?>
		<?php else : ?>
		<main class="ob-stage">
			<div class="ob-step<?php echo $wide ? ' ob-step-wide' : ''; ?>">
			<?php fge_onboarding_render_slide_form( $id, $step, $partner_id, $token, $vals, $errors ); ?>
			</div>
		</main>
		<?php fge_onboarding_render_footer( $step, $token ); ?>
		<?php endif; ?>
		<?php fge_onboarding_render_help(); ?>
	</div>
	<?php
}

/** Dispatches a form/review slide to its renderer by id. */
function fge_onboarding_render_slide_form( string $id, int $step, int $partner_id, string $token, array $vals, array $errors ): void {
	switch ( $id ) {
		case 'golftype': fge_onboarding_render_golftype( $step, $partner_id, $token, $vals, $errors ); break;
		case 'basics':   fge_onboarding_render_basics( $step, $partner_id, $token, $vals, $errors ); break;
		case 'location': fge_onboarding_render_location( $step, $partner_id, $token, $vals, $errors ); break;
		case 'arrival':  fge_onboarding_render_arrival( $step, $partner_id, $token, $vals, $errors ); break;
		case 'main':     fge_onboarding_render_step_4( $step, $partner_id, $token, $vals, $errors ); break;
		case 'contacts': fge_onboarding_render_step_5( $step, $partner_id, $token, $vals, $errors ); break;
		case 'infra':    fge_onboarding_render_infra( $step, $partner_id, $token, $vals, $errors ); break;
		case 'gastro':   fge_onboarding_render_gastro( $step, $partner_id, $token, $vals, $errors ); break;
		case 'capacity': fge_onboarding_render_step_7( $step, $partner_id, $token, $vals, $errors ); break;
		case 'formats':  fge_onboarding_render_step_8( $step, $partner_id, $token, $vals, $errors ); break;
		case 'avail':    fge_onboarding_render_step_9( $step, $partner_id, $token, $vals, $errors ); break;
		case 'pricing':  fge_onboarding_render_step_10( $step, $partner_id, $token, $vals, $errors ); break;
		case 'media':    fge_onboarding_render_step_11( $step, $partner_id, $token, $vals, $errors ); break;
		case 'review':   fge_onboarding_render_step_12( $step, $partner_id, $token, $vals ); break;
	}
}

// ── Saved vals loader ─────────────────────────────────────────────────────────

function fge_onboarding_get_saved_vals( int $partner_id ): array {
	if ( $partner_id <= 0 ) {
		return [];
	}
	$m = static function( string $key ) use ( $partner_id ) {
		return get_post_meta( $partner_id, '_fge_' . $key, true );
	};
	return [
		'public_golfclub_name'          => (string) $m( 'public_golfclub_name' ),
		'legal_operator_name'           => (string) $m( 'legal_operator_name' ),
		'website_url'                   => (string) $m( 'website_url' ),
		'public_short_description'      => (string) $m( 'public_short_description' ),
		'internal_note'                 => (string) $m( 'internal_note' ),
		'street'                        => (string) $m( 'street' ),
		'house_number'                  => (string) $m( 'house_number' ),
		'postal_code'                   => (string) $m( 'postal_code' ),
		'city'                          => (string) $m( 'city' ),
		'federal_state'                 => (string) $m( 'federal_state' ),
		'google_maps_url'               => (string) $m( 'google_maps_url' ),
		'latitude'                      => (string) $m( 'latitude' ),
		'longitude'                     => (string) $m( 'longitude' ),
		'main_contact_name'             => (string) $m( 'main_contact_name' ),
		'main_contact_email'            => (string) $m( 'main_contact_email' ),
		'main_contact_phone'            => (string) $m( 'main_contact_phone' ),
		'main_contact_role'             => (string) $m( 'main_contact_role' ),
		'event_contact_name'            => (string) $m( 'event_contact_name' ),
		'event_contact_email'           => (string) $m( 'event_contact_email' ),
		'event_contact_phone'           => (string) $m( 'event_contact_phone' ),
		'gastro_contact_name'           => (string) $m( 'gastro_contact_name' ),
		'gastro_contact_email'          => (string) $m( 'gastro_contact_email' ),
		'gastro_contact_phone'          => (string) $m( 'gastro_contact_phone' ),
		'golf_school_contact_name'      => (string) $m( 'golf_school_contact_name' ),
		'golf_school_contact_email'     => (string) $m( 'golf_school_contact_email' ),
		'golf_school_contact_phone'     => (string) $m( 'golf_school_contact_phone' ),
		'billing_contact_name'          => (string) $m( 'billing_contact_name' ),
		'billing_contact_email'         => (string) $m( 'billing_contact_email' ),
		'billing_contact_phone'         => (string) $m( 'billing_contact_phone' ),
		'participants_min_general'      => (string) $m( 'participants_min_general' ),
		'participants_max_general'      => (string) $m( 'participants_max_general' ),
		'range_group_capacity'          => (string) $m( 'range_group_capacity' ),
		'putting_green_capacity'        => (string) $m( 'putting_green_capacity' ),
		'short_game_capacity'           => (string) $m( 'short_game_capacity' ),
		'meeting_room_capacity'         => (string) $m( 'meeting_room_capacity' ),
		'gastro_capacity'               => (string) $m( 'gastro_capacity' ),
		'gastro_outdoor_capacity'       => (string) $m( 'gastro_outdoor_capacity' ),
		'golf_teacher_capacity'         => (string) $m( 'golf_teacher_capacity' ),
		'parking_count'                 => (string) $m( 'parking_count' ),
		'event_formats'                 => (array) $m( 'event_formats' ),
		'preferred_event_days'          => (array) $m( 'preferred_event_days' ),
		'evening_events_possible'       => (string) $m( 'evening_events_possible' ),
		'min_lead_time_days'            => (string) $m( 'min_lead_time_days' ),
		'season'                        => (string) $m( 'season' ),
		'individual_availability_check' => $m( 'individual_availability_check' ) !== '0' ? '1' : '0',
		'default_markup_percent'        => (string) $m( 'default_markup_percent' ),
		'vat_required'                  => (string) $m( 'vat_required' ),
		'billing_method_internal'       => (string) $m( 'billing_method_internal' ),
		'bank_details_available'        => (string) $m( 'bank_details_available' ),
		'internal_billing_note'         => (string) $m( 'internal_billing_note' ),
		'logo_attachment_id'            => (int) $m( 'logo_attachment_id' ),
		'hero_image_attachment_id'      => (int) $m( 'hero_image_attachment_id' ),
		'image_rights_confirmed'        => (string) $m( 'image_rights_confirmed' ),
		'image_rights_note'             => (string) $m( 'image_rights_note' ),
		'infra'                         => is_array( $m( 'infra' ) ) ? $m( 'infra' ) : [],
		'additional_equipment'          => (string) $m( 'additional_equipment' ),
		'cap'                           => is_array( $m( 'cap' ) ) ? $m( 'cap' ) : [],
		'golf_type'                     => (string) $m( 'golf_type' ),
		'poi_car'                       => (string) $m( 'poi_car' ),
		'poi_parking'                   => (string) $m( 'poi_parking' ),
		'poi_train'                     => (string) $m( 'poi_train' ),
		'poi_shuttle'                   => (string) $m( 'poi_shuttle' ),
		'arrival_estation'              => (string) $m( 'arrival_estation' ),
	];
}

// ── UI helpers ────────────────────────────────────────────────────────────────

function fge_onboarding_render_topbar( string $save_exit_url, int $step ): void {
	$kind = fge_onboarding_slide( $step )['kind'] ?? 'form';
	?>
<header class="ob-topbar">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ob-brand" aria-label="Firmengolf Startseite">
		<img src="<?php echo esc_url( fge_get_logo_url() ); ?>" alt="Firmengolf" width="120" height="24">
	</a>
	<div class="ob-top-actions">
		<button type="button" class="ob-top-pill" data-ob-help>Noch Fragen?</button>
		<?php if ( 'form' === $kind ) : ?>
		<?php // Saves the current slide (via the content form) and exits — handler redirects. ?>
		<button type="submit" form="ob-step-form" name="fge_ob_save_exit" value="1" class="ob-top-pill">Speichern &amp; beenden</button>
		<?php elseif ( 'review' === $kind ) : ?>
		<a class="ob-top-pill" href="<?php echo esc_url( $save_exit_url ); ?>">Speichern &amp; beenden</a>
		<?php endif; ?>
	</div>
</header>
<?php }

/**
 * Footer with the 3-chapter progress (mirrors the Event-Anfrage stepper) + Zurück.
 * The per-step "Weiter" submit stays inside each step's <form> (server-rendered PRG).
 */
function fge_onboarding_render_footer( int $step, string $token ): void {
	$chapters = [
		1 => [ 'label' => 'Dein Platz',             'steps' => [ 2, 3, 4, 5, 6, 7 ] ],
		2 => [ 'label' => 'Dein Angebot',           'steps' => [ 9, 10, 11, 12 ] ],
		3 => [ 'label' => 'Verfügbarkeit & Preis',  'steps' => [ 14, 15, 16, 17 ] ],
	];
	$segments    = [];
	$active_label = '';
	foreach ( $chapters as $c ) {
		$total  = count( $c['steps'] );
		$done   = 0;
		$active = false;
		foreach ( $c['steps'] as $s ) {
			if ( $s < $step ) { $done++; }
			elseif ( $s === $step ) { $active = true; }
		}
		$ratio = $total ? ( $active ? ( $done + 0.5 ) / $total : $done / $total ) : 0;
		$ratio = max( 0, min( 1, $ratio ) );
		$segments[] = [ 'label' => $c['label'], 'ratio' => $ratio, 'done' => $ratio >= 1, 'active' => $active ];
		if ( $active ) { $active_label = $c['label']; }
	}
	if ( '' === $active_label ) {
		foreach ( array_reverse( $segments ) as $seg ) { if ( $seg['done'] ) { $active_label = $seg['label']; break; } }
	}

	$kind      = fge_onboarding_slide( $step )['kind'] ?? 'form';
	$back_step = $step - 1;
	$back_url  = $back_step >= 1 ? fge_onboarding_step_url( $back_step, fge_onboarding_uses_token( $back_step ) ? $token : '' ) : '';

	// Same button everywhere; only the wording changes by slide kind.
	$primary_label = ( 'intro' === $kind ) ? 'Start' : ( ( 'review' === $kind ) ? 'Zur Prüfung einreichen' : 'Weiter' );

	// The review slide can only be submitted once all required data is present.
	$review_ready = true;
	if ( 'review' === $kind ) {
		$pid          = fge_onboarding_get_current_partner_id();
		$review_ready = $pid > 0 && fge_onboarding_is_submittable( $pid );
	}
	?>
<footer class="ob-footer">
	<div class="ob-foot-inner">
		<div class="ob-prog" aria-label="Fortschritt">
			<?php foreach ( $segments as $seg ) : ?>
			<div class="ob-prog-seg<?php echo $seg['done'] ? ' done' : ''; echo $seg['active'] ? ' on' : ''; ?>">
				<span class="ob-prog-bar"><span class="ob-prog-fill" style="width:<?php echo esc_attr( number_format( $seg['ratio'] * 100, 1, '.', '' ) ); ?>%"></span></span>
				<span class="ob-prog-label"><?php echo esc_html( $seg['label'] ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
		<?php if ( '' !== $active_label ) : ?>
		<div class="ob-prog-count"><?php echo esc_html( $active_label ); ?></div>
		<?php endif; ?>
		<div class="ob-nav">
			<?php if ( '' !== $back_url ) : ?>
			<a class="ob-btn-text" href="<?php echo esc_url( $back_url ); ?>">Zurück</a>
			<?php else : ?>
			<span class="ob-btn-text" aria-disabled="true" style="visibility:hidden">Zurück</span>
			<?php endif; ?>
			<div class="ob-nav-right">
				<?php if ( 'intro' === $kind ) : ?>
				<form method="post" class="ob-foot-form">
					<?php wp_nonce_field( 'fge_onboarding_step_' . $step, 'fge_ob_nonce' ); ?>
					<input type="hidden" name="fge_ob_action" value="step_submit">
					<input type="hidden" name="ob_step" value="<?php echo esc_attr( (string) $step ); ?>">
					<?php if ( '' !== $token ) : ?><input type="hidden" name="ob_token" value="<?php echo esc_attr( $token ); ?>"><?php endif; ?>
					<button type="submit" class="ob-btn-primary"><?php echo esc_html( $primary_label ); ?></button>
				</form>
				<?php elseif ( 'review' === $kind && ! $review_ready ) : ?>
				<button type="button" class="ob-btn-primary" disabled><?php echo esc_html( $primary_label ); ?></button>
				<?php else : ?>
				<?php // Steps 2–12 submit their content form (id="ob-step-form") via the form attribute. ?>
				<button type="submit" form="ob-step-form" class="ob-btn-primary"><?php echo esc_html( $primary_label ); ?></button>
				<?php endif; ?>
			</div>
		</div>
	</div>
</footer>
<?php }

/**
 * Help drawer (toggled via [data-ob-help]). Contact data comes from fge_company()
 * — Partner-Team-Mail + Telefon, NEVER the design's placeholder firmengolf.de.
 */
function fge_onboarding_render_help(): void {
	$co        = function_exists( 'fge_company' ) ? fge_company() : [];
	$email     = $co['email_partner'] ?? 'partner@visionpunch.de';
	$phone     = $co['phone_display'] ?? '+49 (0) 89 1225 1010';
	$phone_tel = $co['phone_tel'] ?? '+498912251010';
	?>
<div class="ob-help-scrim" id="ob-help" hidden>
	<aside class="ob-help" role="dialog" aria-modal="true" aria-label="Hilfe">
		<button type="button" class="ob-help-close" data-ob-help-close aria-label="Schließen">×</button>
		<div class="ob-help-eyebrow">Hilfe</div>
		<h2 class="ob-help-h">Wir sind erreichbar.</h2>
		<p class="ob-help-p">Wenn du an einer Stelle hängen bleibst — schreib uns kurz oder ruf an. Wir helfen dir durch den Prozess und beantworten alle Fragen zur Partnerschaft.</p>
		<div class="ob-help-channels">
			<a href="mailto:<?php echo esc_attr( $email ); ?>" class="ob-help-channel">
				<span class="ob-help-l">Partner-Team</span>
				<span class="ob-help-v"><?php echo esc_html( $email ); ?></span>
			</a>
			<a href="tel:<?php echo esc_attr( $phone_tel ); ?>" class="ob-help-channel">
				<span class="ob-help-l">Telefon</span>
				<span class="ob-help-v"><?php echo esc_html( $phone ); ?></span>
			</a>
		</div>
		<div class="ob-help-foot">
			<span class="ob-help-l">Antwortzeit</span>
			<span class="ob-help-v-sm">Innerhalb eines Werktags · Mo–Fr 09–18 Uhr</span>
		</div>
	</aside>
</div>
<script>
(function(){
	var h = document.getElementById('ob-help');
	if (!h) return;
	function open(){ h.hidden = false; }
	function close(){ h.hidden = true; }
	document.querySelectorAll('[data-ob-help]').forEach(function(b){ b.addEventListener('click', open); });
	h.addEventListener('click', function(e){ if (e.target === h || e.target.closest('[data-ob-help-close]')) close(); });
	document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
})();
</script>
<?php }

function fge_onboarding_render_step_header( int $step, string $title, string $subtitle = '' ): void { ?>
<header class="ob-step-head">
	<h1 class="ob-step-title"><?php echo esc_html( $title ); ?></h1>
	<?php if ( $subtitle !== '' ) : ?>
		<p class="ob-step-lead"><?php echo esc_html( $subtitle ); ?></p>
	<?php endif; ?>
</header>
<?php }

/**
 * Per-step primary/save buttons now live in the sticky footer (and the topbar
 * "Speichern & beenden"), submitting the content form via the form attribute.
 * Kept as a no-op so existing step renderers don't need to drop their calls.
 */
function fge_onboarding_next_btn( string $label = 'Weiter', string $extra_name = '' ): void {}

function fge_onboarding_form_open( int $step, int $partner_id, string $token ): void {
	$enc = ( $step === 11 ) ? ' enctype="multipart/form-data"' : '';
	printf(
		'<form method="post"%s id="ob-step-form" class="ob-form">',
		$enc // phpcs:ignore WordPress.Security.EscapeOutput
	);
	wp_nonce_field( 'fge_onboarding_step_' . $step, 'fge_ob_nonce' );
	printf( '<input type="hidden" name="fge_ob_action" value="step_submit">' );
	printf( '<input type="hidden" name="ob_step" value="%d">', $step );
	if ( $token !== '' ) {
		printf( '<input type="hidden" name="ob_token" value="%s">', esc_attr( $token ) );
	}
}

function fge_onboarding_error( array $errors, string $field ): void {
	if ( isset( $errors[ $field ] ) ) {
		printf( '<p class="ob-field-error">%s</p>', esc_html( $errors[ $field ] ) );
	}
}

function fge_onboarding_input( string $id, string $name, string $label, string $val, string $type = 'text', bool $required = false, string $placeholder = '', array $errors = [] ): void {
	$err_class = isset( $errors[ $name ] ) ? ' ob-input--error' : '';
	?>
	<div class="ob-field<?php echo $err_class ? ' ob-field--error' : ''; ?>">
		<label class="ob-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?><?php if ( $required ) : ?> <span class="ob-required">*</span><?php endif; ?></label>
		<input class="ob-input<?php echo $err_class; ?>" type="<?php echo esc_attr( $type ); ?>"
		       id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
		       value="<?php echo esc_attr( $val ); ?>"
		       <?php echo $placeholder !== '' ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
		       <?php echo $required ? 'required' : ''; ?>>
		<?php fge_onboarding_error( $errors, $name ); ?>
	</div>
	<?php
}

function fge_onboarding_textarea( string $id, string $name, string $label, string $val, string $placeholder = '' ): void { ?>
<div class="ob-field">
	<label class="ob-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
	<textarea class="ob-input ob-textarea" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
	          rows="4" <?php echo $placeholder !== '' ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>><?php echo esc_textarea( $val ); ?></textarea>
</div>
<?php }

function fge_onboarding_select( string $id, string $name, string $label, string $selected, array $options, bool $required = false, array $errors = [] ): void {
	$err_class = isset( $errors[ $name ] ) ? ' ob-input--error' : '';
	?>
	<div class="ob-field<?php echo $err_class ? ' ob-field--error' : ''; ?>">
		<label class="ob-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?><?php if ( $required ) : ?> <span class="ob-required">*</span><?php endif; ?></label>
		<select class="ob-input ob-select<?php echo $err_class; ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo $required ? 'required' : ''; ?>>
			<option value="">— bitte wählen —</option>
			<?php foreach ( $options as $val => $lbl ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $selected, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php fge_onboarding_error( $errors, $name ); ?>
	</div>
	<?php
}

// ── Selectable icon cards ─────────────────────────────────────────────────────

/**
 * Inline SVG icon (Lucide-style line glyphs, ported 1:1 from the design's Icon
 * component in _design_neu/partner-onboarding/Steps.jsx). stroke=currentColor so
 * the card's CSS colour (ink → fairway-green when selected) drives it.
 */
function fge_onboarding_card_icon( string $name ): string {
	$p = [
		'driving-range'   => '<path d="M3 21h18"/><path d="M9 21V8l5-5 5 5v13"/><path d="M5 21V13l4-2"/>',
		'putting'         => '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="9"/>',
		'short-game'      => '<path d="M4 20h16"/><path d="M8 20l4-12 4 12"/><circle cx="12" cy="8" r="1.5"/>',
		'short-course'    => '<path d="M3 12c4-6 14-6 18 0"/><path d="M3 20h18"/><circle cx="6" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
		'course-9'        => '<path d="M4 18h16"/><path d="M8 18V8l4-4 4 4v10"/>',
		'course-18'       => '<path d="M3 18h18"/><path d="M7 18V9l3-3 3 3v9"/><path d="M14 18v-6l3-3"/>',
		'course-27'       => '<path d="M2 19h20"/><path d="M5 19v-5l2.5-2.5 2.5 2.5v5"/><path d="M12 19v-7l2.5-2.5 2.5 2.5v7"/><path d="M19 19v-4l1-1"/>',
		'leading-course'  => '<path d="M7 21V4l8 2.6L7 9.2"/><path d="M4 21h7"/><path d="M17.4 3.2l.7 1.5 1.6.2-1.2 1.1.3 1.6-1.4-.8-1.4.8.3-1.6-1.2-1.1 1.6-.2z"/>',
		'links-course'    => '<path d="M3 20h18"/><path d="M3 16c2.5-2.2 5-2.2 7.5 0s5 2.2 7.5 0"/><path d="M14 16V7l4 1.4L14 9.8"/>',
		'pitch-putt'      => '<path d="M3 20h18"/><path d="M16 20V8l4 1.4L16 11"/><path d="M4 17c2-5.5 6-6.5 9-4" stroke-dasharray="0.5 2.4"/><circle cx="4" cy="17" r="1.3"/>',
		'mini-golf'       => '<path d="M12 21V10"/><path d="M9 21h6"/><circle cx="12" cy="8.6" r="1.2"/><path d="M12 8.6l4.6-2.6M12 8.6l-4.6 2.6M12 8.6l2.6 4.6M12 8.6L9.4 4"/><circle cx="6" cy="20" r="1"/>',
		'clubs'           => '<path d="M6 21l6-14"/><path d="M14 21l4-10"/><circle cx="12" cy="5" r="2"/><circle cx="18" cy="9" r="1.6"/>',
		'balls'           => '<circle cx="8" cy="14" r="3"/><circle cx="16" cy="14" r="3"/><circle cx="12" cy="9" r="3"/>',
		'coach'           => '<circle cx="12" cy="7" r="3"/><path d="M5 21c0-4 3-6 7-6s7 2 7 6"/>',
		'meeting'         => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M8 18v2M16 18v2"/>',
		'restaurant'      => '<path d="M7 3v6c0 1 1 2 2 2v10"/><path d="M5 3h4M15 3v18M17 3c0 4-2 6-2 8"/>',
		'terrace'         => '<path d="M3 18h18"/><path d="M5 18V8h14v10"/><path d="M9 18V12h6v6"/>',
		'parking'         => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V8h4a3 3 0 1 1 0 6H9"/>',
		'shuttle'         => '<rect x="3" y="6" width="18" height="10" rx="2"/><path d="M3 12h18"/><circle cx="8" cy="18" r="1.5"/><circle cx="16" cy="18" r="1.5"/>',
		'indoor'          => '<rect x="3" y="4" width="18" height="14" rx="1"/><path d="M3 18v2M21 18v2M9 8l6 6M15 8l-6 6"/>',
		'weather'         => '<path d="M8 16a4 4 0 1 1 0-8 5 5 0 0 1 10 1 3 3 0 0 1 0 6H8z"/><path d="M9 19l-1 2M12 19l-1 2M15 19l-1 2"/>',
		'branding'        => '<path d="M4 21V7a2 2 0 0 1 2-2h8l4 4v12"/><path d="M14 5v4h4"/>',
		'tournament'      => '<path d="M7 4h10v4a5 5 0 0 1-10 0V4z"/><path d="M12 13v4M9 21h6"/>',
		'shower'          => '<path d="M12 4v3"/><circle cx="12" cy="9" r="2"/><path d="M8 13l-1 4M12 13l-1 4M16 13l-1 4"/>',
		'cart'            => '<path d="M3 17h11l3-5h4"/><path d="M3 7h6v5"/><circle cx="7" cy="19" r="2"/><circle cx="17" cy="19" r="2"/>',
		'trolley'         => '<path d="M6 21V5a2 2 0 0 1 4 0v16"/><path d="M10 8l8 1.5L10 12"/><circle cx="6" cy="21" r="1.4"/>',
		'wifi'            => '<path d="M4.5 12.5a10 10 0 0 1 15 0"/><path d="M8 16a5 5 0 0 1 8 0"/><circle cx="12" cy="19" r="1"/>',
		'pro-shop'        => '<path d="M4 9h16l-1 11H5z"/><path d="M8 9a4 4 0 0 1 8 0"/>',
		'coffee'          => '<path d="M5 8h11v4a5 5 0 0 1-10 0z"/><path d="M16 9h2a2 2 0 0 1 0 4h-2"/><path d="M5 21h12"/>',
		'drinks'          => '<path d="M5 4h14l-7 8z"/><path d="M12 12v6"/><path d="M8 21h8"/>',
		'grill'           => '<circle cx="12" cy="9" r="6"/><path d="M8.5 15l-2 5M15.5 15l2 5M12 15v5"/>',
		'accessible'      => '<circle cx="12" cy="4" r="1.6"/><path d="M7 8h7"/><path d="M11 8v5h3l3 6"/><path d="M14 13a4.5 4.5 0 1 1-5-4.3"/>',
		'beamer'          => '<rect x="3" y="8" width="14" height="9" rx="2"/><circle cx="9" cy="12.5" r="2.5"/><path d="M17 11l4-2v6l-4-2"/>',
		'screen'          => '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v4M8 20h8"/>',
		'mic'             => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/>',
		'flipchart'       => '<path d="M5 3h14M6 4v12M18 4v12M5 16h14"/><path d="M12 16v5"/>',
		'plate'           => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>',
		'wine'            => '<path d="M8 3h8l-1 6a3 3 0 0 1-6 0z"/><path d="M12 12v6M9 21h6"/>',
		'intro-golf'      => '<circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/>',
		'team-challenge'  => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="10" r="2.5"/><path d="M3 21c0-3 3-5 6-5s6 2 6 5"/><path d="M13 21c0-2 2-3 4-3s4 1 4 3"/>',
		'putting-challenge' => '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path d="M12 4v2M12 18v2M4 12h2M18 12h2"/>',
		'range-training'  => '<path d="M3 18h18"/><path d="M7 18v-6M11 18V8M15 18v-4M19 18v-9"/>',
		'short-challenge' => '<path d="M5 18l5-8 4 4 5-6"/><circle cx="19" cy="8" r="1.5"/>',
		'turnier-9'       => '<path d="M9 3h6v4a3 3 0 0 1-6 0z"/><path d="M12 11v6M8 19h8"/>',
		'turnier-18'      => '<path d="M8 3h8v4a4 4 0 0 1-8 0z"/><path d="M12 11v6M7 19h10"/>',
		'offsite'         => '<rect x="3" y="9" width="18" height="11" rx="1"/><path d="M9 9V5h6v4"/><path d="M8 14h8"/>',
		'sommerfest'      => '<circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.5 5.5l2 2M16.5 16.5l2 2M5.5 18.5l2-2M16.5 7.5l2-2"/>',
		'kunden'          => '<circle cx="12" cy="8" r="3"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/><path d="M16 4h4v4"/>',
		'health'          => '<path d="M12 21s-7-4-7-10a4 4 0 0 1 7-3 4 4 0 0 1 7 3c0 6-7 10-7 10z"/>',
		'azubi'           => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 12v4c0 2 3 4 6 4s6-2 6-4v-4"/>',
		'custom'          => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
		'networking'      => '<circle cx="7" cy="8" r="3"/><circle cx="17" cy="8" r="3"/><path d="M2.5 20c0-2.8 2-4.5 4.5-4.5s4.5 1.7 4.5 4.5"/><path d="M12.5 20c0-2.8 2-4.5 4.5-4.5s4.5 1.7 4.5 4.5"/>',
		'afterwork'       => '<path d="M3 18h18"/><path d="M7 18a5 5 0 0 1 10 0"/><path d="M12 4v3M5.2 7.2l1.6 1.6M18.8 7.2l-1.6 1.6M3 12h2M19 12h2"/>',
		'charity'         => '<path d="M12 9.2c-1.1-2.1-4.2-1.5-4.2 1 0 2.1 4.2 4.3 4.2 4.3s4.2-2.2 4.2-4.3c0-2.5-3.1-3.1-4.2-1z"/><path d="M3 20c2-1.8 4.6-1.8 6.5-.8l2.5.9 6-2.3"/>',
		'nacht-event'     => '<path d="M20 13.5A8 8 0 1 1 10.5 4a6.2 6.2 0 0 0 9.5 9.5z"/><path d="M18 3.5l.6 1.4 1.4.6-1.4.6-.6 1.4-.6-1.4-1.4-.6 1.4-.6z"/>',
	];
	$inner = $p[ $name ] ?? '<circle cx="12" cy="12" r="9"/>';
	return '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/** SVG icon for a catalog id (golf type / infrastructure / gastronomy / format) — uses the onboarding icon set. */
function fge_infra_icon( string $id ): string {
	static $map = null;
	if ( null === $map ) {
		$map = fge_onboarding_icon_map();
	}
	return fge_onboarding_card_icon( $map[ $id ] ?? '' );
}

/**
 * Maps catalog ids (golf types, infrastructure incl. gastronomy) to icon glyph
 * names — taken verbatim from the design's item lists.
 */
function fge_onboarding_icon_map(): array {
	return [
		// Golf offering types
		'course-18' => 'course-18', 'course-27' => 'course-27', 'leading' => 'leading-course',
		'links' => 'links-course', 'course-9' => 'course-9', 'indoor-sim' => 'indoor',
		'range' => 'driving-range', 'short' => 'short-course', 'pitch-putt' => 'pitch-putt',
		'mini-golf' => 'mini-golf',
		// Infrastructure
		'abc-platz' => 'course-27', 'short-course' => 'short-course', 'driving-range' => 'driving-range',
		'range-covered' => 'driving-range', 'range-heated' => 'driving-range', 'range-flood' => 'nacht-event',
		'trackman' => 'indoor', 'toptracer' => 'indoor', 'short-game' => 'short-game', 'indoor' => 'indoor',
		'barrierefrei' => 'accessible', 'meeting-room' => 'meeting', 'seminar' => 'meeting',
		'conference' => 'meeting', 'workshop' => 'meeting', 'eventroom' => 'meeting', 'golf-shop' => 'pro-shop',
		'shower' => 'shower', 'beamer' => 'beamer', 'screen' => 'screen', 'mic' => 'mic', 'wifi' => 'wifi',
		'flipchart' => 'flipchart', 'whiteboard' => 'flipchart', 'moderation' => 'branding',
		'catering-area' => 'plate', 'coach' => 'coach', 'trial-course' => 'intro-golf',
		'platzreife' => 'intro-golf', 'company-course' => 'team-challenge', 'advanced-course' => 'range-training',
		'rental-clubs' => 'clubs', 'range-balls' => 'balls',
		// Gastronomy
		'restaurant' => 'restaurant', 'club-restaurant' => 'restaurant', 'bistro' => 'coffee', 'cafe' => 'coffee',
		'bar' => 'drinks', 'halfway' => 'restaurant', 'terrace' => 'terrace', 'outdoor' => 'terrace',
		'lounge' => 'drinks', 'catering' => 'restaurant', 'breakfast' => 'coffee', 'lunch' => 'restaurant',
		'dinner' => 'restaurant', 'bbq' => 'grill', 'drinks-flat' => 'drinks', 'coffee-break' => 'coffee',
		// Event formats (canonical ids from event-formats.php)
		'teamevent' => 'team-challenge', 'after_work_golf' => 'afterwork', 'schnupperkurs' => 'intro-golf',
		'kundenevent' => 'kunden', 'gesundheitstag' => 'health', 'offsite' => 'offsite',
		'networking' => 'networking', 'firmen_golfturnier' => 'turnier-18', 'nacht_event' => 'nacht-event',
		'andere' => 'custom', 'sommerfest' => 'sommerfest', 'tagung' => 'meeting',
		'firmenjubilaeum' => 'sommerfest', 'kickoff' => 'offsite', 'incentive' => 'offsite',
		'charity_csr' => 'charity', 'sponsoring' => 'branding',
	];
}

/**
 * Renders one selectable icon card (design `.ob-card`). The hidden input keeps
 * the choice for the server POST and drives the no-JS `:has(:checked)` styling;
 * the design icon turns fairway-green and the box keeps its colour when selected.
 */
function fge_onboarding_card( string $type, string $name, string $id, string $label, bool $checked ): void {
	static $map = null;
	if ( null === $map ) {
		$map = fge_onboarding_icon_map();
	}
	$icon = fge_onboarding_card_icon( $map[ $id ] ?? '' );
	?>
	<label class="ob-card">
		<input type="<?php echo esc_attr( $type ); ?>" class="ob-card-input" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $id ); ?>" <?php checked( $checked ); ?>>
		<span class="ob-card-ico" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput — static, trusted SVG ?></span>
		<span class="ob-card-l"><?php echo esc_html( $label ); ?></span>
	</label>
	<?php
}

// ── Step renderers ────────────────────────────────────────────────────────────

/**
 * Chapter intro slides (intro-1/2/3) — the design's split intro layout
 * (eyebrow, big serif title, lead, bullet list, photo). Content per chapter.
 */
function fge_onboarding_render_intro( string $id ): void {
	$img = static function ( string $file ): string {
		return function_exists( 'fge_get_placeholder_image_url' ) ? fge_get_placeholder_image_url( $file ) : '';
	};
	$intros = [
		'intro-1' => [
			'eyebrow' => 'Schritt 1 · Erzähl uns von deinem Platz',
			'title'   => 'Richte deinen Golfplatz als <span class="ob-italic">Eventlocation</span> ein.',
			'lead'    => 'In ein paar Schritten erfassen wir die wichtigsten Informationen, damit Unternehmen deinen Golfplatz für Firmenevents anfragen können. Du kannst zwischendurch jederzeit speichern und später weitermachen.',
			'list'    => [ 'Basisdaten und Standort', 'Hauptkontakt + dein Login fürs Partnerportal', 'Was ihr für Firmenevents anbieten könnt', 'Verfügbarkeit, Preis &amp; Medien' ],
			'meta'    => 'Dauer ungefähr 10 Minuten · keine Verpflichtung',
			'photo'   => $img( 'onboarding-abschlag.jpg' ),
		],
		'intro-2' => [
			'eyebrow' => 'Schritt 2 · Was du anbieten kannst',
			'title'   => 'Was macht euren Platz zur <span class="ob-italic">Eventlocation</span>?',
			'lead'    => 'Wir möchten Unternehmen genau passende Vorschläge machen. Dazu erfassen wir, welche Infrastruktur ihr habt, wie groß die Gruppen sein können und welche Veranstaltungstypen ihr abdeckt.',
			'list'    => [ 'Infrastruktur (Range, Greens, Clubhaus, Räume)', 'Kapazitäten pro Bereich', 'Veranstaltungstypen, die ihr unterstützt' ],
			'meta'    => '',
			'photo'   => $img( 'onboarding-chapter-2.jpg' ),
		],
		'intro-3' => [
			'eyebrow' => 'Schritt 3 · Verfügbarkeit, Preis &amp; Medien',
			'title'   => 'Fast geschafft — jetzt die <span class="ob-italic">Rahmenbedingungen</span>.',
			'lead'    => 'Verfügbarkeit, Aufschlag und Bilder — danach prüfst du alles und reichst dein Profil bei uns ein. Wir melden uns innerhalb eines Werktags zurück.',
			'list'    => [ 'Verfügbarkeit &amp; Vorlauf', 'Preis-Aufschlag &amp; Abrechnung', 'Logo, Titelbild &amp; Galerie', 'Zusammenfassung &amp; Einreichung' ],
			'meta'    => '',
			'photo'   => $img( 'onboarding-chapter-3.jpg' ),
		],
	];
	$d = $intros[ $id ] ?? $intros['intro-1'];
	?>
<div class="ob-intro">
	<div class="ob-intro-text">
		<div class="ob-eyebrow"><?php echo wp_kses_post( $d['eyebrow'] ); ?></div>
		<h1 class="ob-step-title big"><?php echo wp_kses_post( $d['title'] ); ?></h1>
		<p class="ob-intro-lead"><?php echo esc_html( $d['lead'] ); ?></p>
		<ul class="ob-intro-list">
			<?php foreach ( $d['list'] as $item ) : ?>
			<li><span class="ob-intro-dot"></span> <?php echo wp_kses_post( $item ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( '' !== $d['meta'] ) : ?>
		<div class="ob-intro-meta"><?php echo esc_html( $d['meta'] ); ?></div>
		<?php endif; ?>
	</div>
	<div class="ob-intro-art" aria-hidden="true">
		<?php if ( '' !== $d['photo'] ) : ?>
		<div class="ob-art-photo">
			<img src="<?php echo esc_url( $d['photo'] ); ?>" alt="">
		</div>
		<?php endif; ?>
	</div>
</div>
<?php }

function fge_onboarding_render_golftype( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Was beschreibt dein Golfangebot am besten?', 'Wähl die Art, die euren Platz am besten beschreibt. Anlagen und Veranstaltungstypen erfassen wir gleich noch im Detail.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	$golf_types = fge_catalog_golf_types();
	$gt_current = (string) ( $v['golf_type'] ?? '' );
	?>
	<div class="ob-cards">
		<?php foreach ( $golf_types as $id => $label ) :
			fge_onboarding_card( 'radio', 'fge_golf_type', (string) $id, (string) $label, ( $gt_current === $id ) );
		endforeach; ?>
	</div>
	<?php fge_onboarding_error( $errors, 'fge_golf_type' );
	echo '</form>';
}

function fge_onboarding_render_basics( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Wie heißt euer Golfplatz?', 'Diese Angaben erscheinen später öffentlich auf deinem Partnerprofil. Du kannst alles jederzeit ändern.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	fge_onboarding_input( 'fge_public_golfclub_name', 'fge_public_golfclub_name', 'Öffentlicher Golfplatzname', $v['public_golfclub_name'] ?? '', 'text', true, 'z.B. Golfclub Königsfeld', $errors );
	fge_onboarding_input( 'fge_legal_operator_name', 'fge_legal_operator_name', 'Rechtlicher Betreibername', $v['legal_operator_name'] ?? '', 'text', true, 'z.B. GC Königsfeld GmbH & Co. KG', $errors );
	fge_onboarding_input( 'fge_website_url', 'fge_website_url', 'Website (optional)', $v['website_url'] ?? '', 'url', false, 'https://' );
	fge_onboarding_textarea( 'fge_public_short_description', 'fge_public_short_description', 'Kurzbeschreibung (optional)', $v['public_short_description'] ?? '', 'Was macht deinen Golfplatz besonders für Firmenevents?' );
	fge_onboarding_textarea( 'fge_internal_note', 'fge_internal_note', 'Interne Notiz für Firmengolf (optional)', $v['internal_note'] ?? '' );
	echo '</form>';
}

function fge_onboarding_render_location( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Wo befindet sich dein Platz?', 'Wir nutzen die Adresse für die Karte und die Anfahrtsbeschreibung. Genaue Anfahrt geht erst nach Bestätigung an Kunden raus.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	$states = [
		'baden_wuerttemberg'   => 'Baden-Württemberg',
		'bayern'               => 'Bayern',
		'berlin'               => 'Berlin',
		'brandenburg'          => 'Brandenburg',
		'bremen'               => 'Bremen',
		'hamburg'              => 'Hamburg',
		'hessen'               => 'Hessen',
		'mecklenburg_vorpommern' => 'Mecklenburg-Vorpommern',
		'niedersachsen'        => 'Niedersachsen',
		'nordrhein_westfalen'  => 'Nordrhein-Westfalen',
		'rheinland_pfalz'      => 'Rheinland-Pfalz',
		'saarland'             => 'Saarland',
		'sachsen'              => 'Sachsen',
		'sachsen_anhalt'       => 'Sachsen-Anhalt',
		'schleswig_holstein'   => 'Schleswig-Holstein',
		'thueringen'           => 'Thüringen',
	];

	?>
	<div class="ob-field-row">
		<?php fge_onboarding_input( 'fge_street', 'fge_street', 'Straße', $v['street'] ?? '', 'text', true, 'Musterstraße', $errors ); ?>
		<?php fge_onboarding_input( 'fge_house_number', 'fge_house_number', 'Hausnr.', $v['house_number'] ?? '', 'text', false, '1' ); ?>
	</div>
	<div class="ob-field-row">
		<?php fge_onboarding_input( 'fge_postal_code', 'fge_postal_code', 'PLZ', $v['postal_code'] ?? '', 'text', true, '12345', $errors ); ?>
		<?php fge_onboarding_input( 'fge_city', 'fge_city', 'Ort', $v['city'] ?? '', 'text', true, 'Musterstadt', $errors ); ?>
	</div>
	<?php
	fge_onboarding_select( 'fge_federal_state', 'fge_federal_state', 'Bundesland', $v['federal_state'] ?? '', $states, true, $errors );

	if ( fge_gmaps_api_key() !== '' ) :
		$lat = (string) ( $v['latitude'] ?? '' );
		$lng = (string) ( $v['longitude'] ?? '' );
		?>
		<div class="ob-field full">
			<label class="ob-field-label" for="fge_map_search">Standort auf der Karte</label>
			<span class="ob-field-hint">Such deine Adresse und zieh den Pin genau auf euren Eingang oder Parkplatz.</span>
			<input type="text" id="fge_map_search" class="ob-input" placeholder="Adresse oder Platzname suchen…" autocomplete="off">
			<div id="fge_map" class="ob-map"></div>
		</div>
		<input type="hidden" id="fge_latitude"  name="fge_latitude"  value="<?php echo esc_attr( $lat ); ?>">
		<input type="hidden" id="fge_longitude" name="fge_longitude" value="<?php echo esc_attr( $lng ); ?>">
		<?php
	else :
		// No API key configured — keep the manual link as a fallback.
		fge_onboarding_input( 'fge_google_maps_url', 'fge_google_maps_url', 'Google Maps Link (optional)', $v['google_maps_url'] ?? '', 'url', false, 'https://maps.google.com/...' );
	endif;
	echo '</form>';
}

function fge_onboarding_render_arrival( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Anfahrt & Location', 'Wie kommen Gäste zu euch? Diese Angaben helfen Firmen bei der Planung — du kannst sie jederzeit anpassen.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	// Maps to _fge_poi_* / _fge_arrival_estation (same keys as admin/Platz view).
	$estation = ( (string) ( $v['arrival_estation'] ?? '' ) === '1' );
	?>
	<div class="ob-field full">
		<label class="ob-field-label" for="fge_poi_car">Mit dem Auto</label>
		<span class="ob-field-hint">Fahrzeit ab Stadtzentrum oder Autobahn.</span>
		<input type="text" class="ob-input" id="fge_poi_car" name="fge_poi_car" value="<?php echo esc_attr( (string) ( $v['poi_car'] ?? '' ) ); ?>" placeholder="z. B. 15 Min ab Stadtzentrum">
	</div>
	<div class="ob-field full">
		<label class="ob-field-label" for="fge_poi_parking">Parken</label>
		<span class="ob-field-hint">Anzahl und Art der Parkplätze.</span>
		<input type="text" class="ob-input" id="fge_poi_parking" name="fge_poi_parking" value="<?php echo esc_attr( (string) ( $v['poi_parking'] ?? '' ) ); ?>" placeholder="z. B. 100 kostenfreie Parkplätze">
	</div>
	<div class="ob-field full">
		<label class="ob-field-label">Ladestation für E-Autos vorhanden?</label>
		<span class="ob-field-hint">Wird Gästen mit E-Auto als Hinweis angezeigt.</span>
		<div class="ob-radio-row">
			<label class="ob-radio">
				<input type="radio" name="fge_arrival_estation" value="1" <?php checked( $estation ); ?> style="position:absolute;opacity:0">
				<span class="ob-radio-dot" aria-hidden="true"></span> Ja
			</label>
			<label class="ob-radio">
				<input type="radio" name="fge_arrival_estation" value="0" <?php checked( ! $estation ); ?> style="position:absolute;opacity:0">
				<span class="ob-radio-dot" aria-hidden="true"></span> Nein
			</label>
		</div>
	</div>
	<div class="ob-field full">
		<label class="ob-field-label" for="fge_poi_train">Mit der Bahn</label>
		<span class="ob-field-hint">Nächste Station und Gehweg.</span>
		<input type="text" class="ob-input" id="fge_poi_train" name="fge_poi_train" value="<?php echo esc_attr( (string) ( $v['poi_train'] ?? '' ) ); ?>" placeholder="z. B. S2 Riem, 10 Gehminuten">
	</div>
	<div class="ob-field full">
		<label class="ob-field-label" for="fge_poi_shuttle">Shuttle-Service</label>
		<span class="ob-field-hint">Falls ihr einen Transfer anbietet.</span>
		<input type="text" class="ob-input" id="fge_poi_shuttle" name="fge_poi_shuttle" value="<?php echo esc_attr( (string) ( $v['poi_shuttle'] ?? '' ) ); ?>" placeholder="z. B. Abholung nach Absprache">
	</div>
	<?php fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_4( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	$name_parts = explode( ' ', $v['main_contact_name'] ?? '', 2 );
	$saved_first = $name_parts[0] ?? '';
	$saved_last  = $name_parts[1] ?? '';

	fge_onboarding_render_step_header( $step, 'Wer ist der Hauptkontakt?', 'Diese Person wird mit dem Platz verknüpft und bekommt einen Login fürs Partnerportal. Wenn die E-Mail-Adresse bereits existiert, verbinden wir das bestehende Konto.' );
	fge_onboarding_form_open( $step, $partner_id, $token );
	?>
	<div class="ob-field-row">
		<?php fge_onboarding_input( 'fge_contact_first_name', 'fge_contact_first_name', 'Vorname', $saved_first, 'text', true, 'Max', $errors ); ?>
		<?php fge_onboarding_input( 'fge_contact_last_name', 'fge_contact_last_name', 'Nachname', $saved_last, 'text', true, 'Mustermann', $errors ); ?>
	</div>
	<?php
	fge_onboarding_input( 'fge_contact_role', 'fge_contact_role', 'Rolle / Position (optional)', $v['main_contact_role'] ?? '', 'text', false, 'z.B. Geschäftsführer, Marketing-Leitung' );
	fge_onboarding_input( 'fge_contact_email', 'fge_contact_email', 'E-Mail-Adresse', $v['main_contact_email'] ?? '', 'email', true, 'max@golfclub.de', $errors );
	fge_onboarding_input( 'fge_contact_phone', 'fge_contact_phone', 'Telefon (optional)', $v['main_contact_phone'] ?? '', 'tel', false, '+49 89 …' );
	?>
	<p class="ob-hint">Wenn diese E-Mail bereits registriert ist, wird der bestehende Account verknüpft. Ansonsten erstellen wir automatisch einen neuen Login.</p>
	<?php
	fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_5( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Möchtest du weitere Ansprechpartner hinzufügen?', 'Du kannst diesen Schritt überspringen und später ergänzen. Diese Personen kannst du später bei der Termin-Freigabe für ein Event einbinden.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	$existing = $partner_id > 0 ? fge_contacts_get( $partner_id ) : [];
	// Only the no-account contacts (user_id 0) are managed here.
	$existing = array_values( array_filter( $existing, static function ( $c ) {
		return (int) $c['user_id'] === 0;
	} ) );
	?>
	<div class="ob-contacts-list" id="ob-contacts-list">
		<?php foreach ( $existing as $c ) {
			fge_onboarding_contact_card( $c );
		} ?>
	</div>

	<button type="button" class="ob-add-contact" id="ob-add-contact">
		<span class="ob-add-contact-ic">+</span> Ansprechpartner hinzufügen
	</button>

	<template id="ob-contact-template"><?php fge_onboarding_contact_card( null ); ?></template>

	<script>
	(function(){
		var list = document.getElementById('ob-contacts-list');
		var tpl  = document.getElementById('ob-contact-template');
		var add  = document.getElementById('ob-add-contact');
		if (!list || !tpl || !add) return;
		function wire(card){
			var rm = card.querySelector('[data-ob-remove]');
			if (rm) rm.addEventListener('click', function(){ card.remove(); });
			var role = card.querySelector('[data-ob-role]');
			var tag  = card.querySelector('.ob-contact-tag');
			if (role && tag) role.addEventListener('change', function(){ tag.textContent = role.value || 'Ansprechpartner'; });
		}
		list.querySelectorAll('.ob-contact-card').forEach(wire);
		add.addEventListener('click', function(){
			var node = tpl.content.firstElementChild.cloneNode(true);
			list.appendChild(node); wire(node);
			var n = node.querySelector('input'); if (n) n.focus();
		});
	})();
	</script>
	<?php fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

/** One contact card (array-named inputs) for the onboarding contacts step. $c null = empty template. */
function fge_onboarding_contact_card( ?array $c ): void {
	$name = $c['name'] ?? '';
	$role = $c['role'] ?? '';
	$mail = $c['email'] ?? '';
	$perm = $c['permission'] ?? '';
	?>
	<div class="ob-contact-card">
		<div class="ob-contact-head">
			<span class="ob-contact-tag"><?php echo esc_html( '' !== $role ? $role : 'Ansprechpartner' ); ?></span>
			<button type="button" class="ob-contact-remove" data-ob-remove>Entfernen</button>
		</div>
		<div class="ob-field full">
			<label class="ob-field-label">Name</label>
			<input type="text" class="ob-input" name="fge_contact_name[]" value="<?php echo esc_attr( $name ); ?>" placeholder="Vor- und Nachname">
		</div>
		<div class="ob-row">
			<div class="ob-field">
				<label class="ob-field-label">Rolle</label>
				<select class="ob-input" name="fge_contact_role[]" data-ob-role>
					<option value="">Rolle wählen …</option>
					<?php foreach ( fge_catalog_contact_roles() as $r ) : ?>
					<option value="<?php echo esc_attr( $r ); ?>" <?php selected( $role, $r ); ?>><?php echo esc_html( $r ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="ob-field">
				<label class="ob-field-label">E-Mail</label>
				<input type="email" class="ob-input" name="fge_contact_email[]" value="<?php echo esc_attr( $mail ); ?>" placeholder="name@golfclub.de">
			</div>
		</div>
		<div class="ob-field full">
			<label class="ob-field-label">Berechtigung</label>
			<span class="ob-field-hint">„Nur informieren" bekommt Status-Mails. „Terminabstimmung" kann zusätzlich Wunschtermine per Link bestätigen.</span>
			<select class="ob-input" name="fge_contact_permission[]">
				<option value="" <?php selected( $perm, '' ); ?>>Standard nach Rolle</option>
				<option value="notify" <?php selected( $perm, 'notify' ); ?>>Nur informieren</option>
				<option value="vote" <?php selected( $perm, 'vote' ); ?>>Terminabstimmung</option>
			</select>
		</div>
	</div>
	<?php
}

function fge_onboarding_render_infra( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Was ist auf eurem Platz alles vorhanden und möglich?', 'Wähl alles aus, was vor Ort verfügbar ist. Du kannst die Auswahl später jederzeit anpassen.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	$groups   = fge_catalog_infra_groups();
	unset( $groups['Gastronomie'] ); // Gastronomie has its own slide.
	$selected = is_array( $v['infra'] ?? null ) ? $v['infra'] : [];
	fge_onboarding_error( $errors, 'fge_infra' );
	?>
	<div class="ob-form">
		<?php foreach ( $groups as $group_label => $items ) : ?>
		<div class="ob-cat">
			<div class="ob-cat-h"><?php echo esc_html( $group_label ); ?></div>
			<div class="ob-cards">
				<?php foreach ( $items as $id => $label ) :
					fge_onboarding_card( 'checkbox', 'fge_infra[]', (string) $id, (string) $label, in_array( $id, $selected, true ) );
				endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
		<div class="ob-field full">
			<label class="ob-field-label" for="fge_additional_equipment">Weitere Ausstattung (optional)</label>
			<span class="ob-field-hint">Etwas, das oben fehlt? Trag es hier frei ein.</span>
			<textarea class="ob-input" id="fge_additional_equipment" name="fge_additional_equipment" placeholder="z. B. E-Trolleys, Cart-Flotte, Wellness-Bereich …"><?php echo esc_textarea( (string) ( $v['additional_equipment'] ?? '' ) ); ?></textarea>
		</div>
	</div>
	<?php
	echo '</form>';
}

function fge_onboarding_render_gastro( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Was bietet ihr gastronomisch an?', 'Wähl alles aus, was ihr für Firmenevents bereitstellen könnt. Du kannst die Auswahl später jederzeit anpassen.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	$gastro   = fge_catalog_infra_groups()['Gastronomie'] ?? [];
	$selected = is_array( $v['infra'] ?? null ) ? $v['infra'] : [];
	?>
	<div class="ob-cards">
		<?php foreach ( $gastro as $id => $label ) :
			fge_onboarding_card( 'checkbox', 'fge_infra[]', (string) $id, (string) $label, in_array( $id, $selected, true ) );
		endforeach; ?>
	</div>
	<?php
	echo '</form>';
}

function fge_onboarding_render_step_7( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Wie viele Gäste passen wo?', 'Wie groß sind eure Bereiche? Das hilft uns, bei individuellen Anfragen sofort zu erkennen, ob ihr dafür in Frage kommt. Mindest- und Maximal-Teilnehmerzahl sind Pflicht — für eure ausgewählten Bereiche fragen wir die Kapazität ab.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	$cap   = is_array( $v['cap'] ?? null ) ? $v['cap'] : [];
	$infra = is_array( $v['infra'] ?? null ) ? $v['infra'] : [];
	$rows  = array_filter( fge_catalog_cap_rows(), static function ( $r ) use ( $infra ) {
		return in_array( $r['infra'], $infra, true );
	} );
	?>
	<div class="ob-cap-list">
		<?php
		fge_onboarding_cap_stepper( 'min', 'Teilnehmer-Minimum', 'Ab wie vielen Gästen lohnt sich ein Event?', $cap['min'] ?? '', true );
		fge_onboarding_error( $errors, 'fge_cap_min' );
		fge_onboarding_cap_stepper( 'max', 'Teilnehmer-Maximum', 'Größte Gruppe, die ihr realistisch betreuen könnt.', $cap['max'] ?? '', true );
		fge_onboarding_error( $errors, 'fge_cap_max' );
		?>

		<?php if ( ! empty( $rows ) ) : ?>
			<div class="ob-cap-divider">Kapazitäten deiner Bereiche</div>
			<?php foreach ( $rows as $r ) {
				fge_onboarding_cap_stepper( $r['key'], $r['label'], $r['hint'], $cap[ $r['key'] ] ?? '', false );
			} ?>
		<?php endif; ?>
	</div>

	<?php if ( empty( $rows ) ) : ?>
		<p class="ob-cap-note">Du hast in Schritt 6 noch keine Bereiche mit eigener Kapazität gewählt (z. B. Driving Range, Meetingraum, Restaurant). Geh einen Schritt zurück, falls du welche ergänzen möchtest.</p>
	<?php endif; ?>

	<script>
	(function(){
		document.querySelectorAll('.ob-stepper-btn[data-step]').forEach(function(b){
			b.addEventListener('click', function(){
				var inp = b.parentNode.querySelector('.ob-stepper-input');
				if (!inp) return;
				var n = parseInt(inp.value || '0', 10); if (isNaN(n)) n = 0;
				n = Math.max(0, n + parseInt(b.getAttribute('data-step'), 10));
				inp.value = n;
			});
		});
	})();
	</script>
	<?php fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

/** Ja/Nein radio field in the design's `.ob-radio-row` look (value 1/0). */
function fge_onboarding_yesno( string $name, string $label, string $hint, bool $is_yes ): void {
	?>
	<div class="ob-field full">
		<label class="ob-field-label"><?php echo esc_html( $label ); ?></label>
		<?php if ( '' !== $hint ) : ?><span class="ob-field-hint"><?php echo esc_html( $hint ); ?></span><?php endif; ?>
		<div class="ob-radio-row">
			<label class="ob-radio">
				<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $is_yes ); ?> style="position:absolute;opacity:0">
				<span class="ob-radio-dot" aria-hidden="true"></span> Ja
			</label>
			<label class="ob-radio">
				<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="0" <?php checked( ! $is_yes ); ?> style="position:absolute;opacity:0">
				<span class="ob-radio-dot" aria-hidden="true"></span> Nein
			</label>
		</div>
	</div>
	<?php
}

/** Single capacity stepper row in the design's `.ob-cap-row` look. */
function fge_onboarding_cap_stepper( string $key, string $label, string $hint, $value, bool $required ): void {
	$val = ( '' === (string) $value ) ? '' : (string) absint( $value );
	?>
	<div class="ob-cap-row">
		<div class="ob-cap-text">
			<span class="ob-cap-l"><?php echo esc_html( $label ); ?><?php echo $required ? ' <span class="ob-required">*</span>' : ''; ?></span>
			<span class="ob-cap-h"><?php echo esc_html( $hint ); ?></span>
		</div>
		<div class="ob-stepper">
			<button type="button" class="ob-stepper-btn" data-step="-1" aria-label="weniger">−</button>
			<input type="number" inputmode="numeric" min="0" class="ob-stepper-input" name="fge_cap[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>" placeholder="0"<?php echo $required ? ' required' : ''; ?>>
			<button type="button" class="ob-stepper-btn" data-step="1" aria-label="mehr">+</button>
		</div>
	</div>
	<?php
}

function fge_onboarding_render_step_8( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Welche Veranstaltungstypen könnt ihr abdecken?', 'Wähle alles, was ihr regelmäßig oder bei Bedarf anbieten könnt. Mehr Veranstaltungstypen = mehr passende Anfragen.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	$formats       = fge_get_event_format_options();
	$saved_formats = (array) ( $v['event_formats'] ?? [] );
	?>
	<div class="ob-cards">
		<?php foreach ( $formats as $key => $label ) :
			fge_onboarding_card( 'checkbox', 'fge_event_formats[]', (string) $key, (string) $label, in_array( $key, $saved_formats, true ) );
		endforeach; ?>
	</div>
	<?php fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_9( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Wann sind Events bei euch möglich?', 'Die exakte Verfügbarkeit prüfen wir später bei jeder Anfrage einzeln. Hier reichen Grundlagen, damit wir Anfragen vorfiltern können.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	$weekdays = [
		'monday' => 'Mo', 'tuesday' => 'Di', 'wednesday' => 'Mi',
		'thursday' => 'Do', 'friday' => 'Fr', 'saturday' => 'Sa', 'sunday' => 'So',
	];
	$saved_days = (array) ( $v['preferred_event_days'] ?? [] );
	$seasons = [
		''                 => 'Bitte wählen …',
		'year_round'       => 'Ganzjährig',
		'march_to_october' => 'März – Oktober',
		'april_to_october' => 'April – Oktober',
		'on_request'       => 'Auf Anfrage',
	];
	$lead  = ( '' !== (string) ( $v['min_lead_time_days'] ?? '' ) ) ? $v['min_lead_time_days'] : '14';
	$indiv = ( (string) ( $v['individual_availability_check'] ?? '1' ) !== '0' );
	?>
	<div class="ob-form">
		<div class="ob-field full">
			<span class="ob-field-label">Bevorzugte Event-Wochentage</span>
			<div class="ob-day-row">
				<?php foreach ( $weekdays as $val => $label ) :
					$on = in_array( $val, $saved_days, true ); ?>
				<label class="ob-day<?php echo $on ? ' on' : ''; ?>">
					<input type="checkbox" name="fge_preferred_event_days[]" value="<?php echo esc_attr( $val ); ?>" <?php checked( $on ); ?>>
					<?php echo esc_html( $label ); ?>
				</label>
				<?php endforeach; ?>
			</div>
			<span class="ob-field-hint">Mehrfachauswahl. Du kannst jederzeit weitere Tage freischalten.</span>
		</div>

		<?php
		fge_onboarding_yesno( 'fge_evening_events_possible', 'Abend-Events möglich?', 'Z. B. Flutlicht-Putting oder Tasting-Abend.', ( (string) ( $v['evening_events_possible'] ?? '' ) === '1' ) );
		?>

		<div class="ob-row">
			<div class="ob-field">
				<label class="ob-field-label" for="fge_min_lead_time_days">Mindest-Vorlauf in Tagen</label>
				<span class="ob-field-hint">So weit im Voraus müssen Anfragen mindestens kommen.</span>
				<input type="number" inputmode="numeric" min="0" class="ob-input" id="fge_min_lead_time_days" name="fge_min_lead_time_days" value="<?php echo esc_attr( $lead ); ?>" placeholder="14">
			</div>
			<div class="ob-field">
				<label class="ob-field-label" for="fge_season">Saison / Verfügbarkeitszeitraum</label>
				<span class="ob-field-hint">Grober Zeitraum für Events bei euch.</span>
				<select class="ob-input" id="fge_season" name="fge_season">
					<?php foreach ( $seasons as $sk => $sl ) : ?>
					<option value="<?php echo esc_attr( $sk ); ?>" <?php selected( (string) ( $v['season'] ?? '' ), $sk ); ?>><?php echo esc_html( $sl ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<?php fge_onboarding_yesno( 'fge_individual_availability_check', 'Individuelle Verfügbarkeitsprüfung erforderlich?', 'Standardmäßig ja. Wir fragen jede Anfrage individuell bei euch ab — kein Auto-Booking.', $indiv ); ?>
	</div>
	<?php
	fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_10( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Preis und Abrechnung', 'Du hinterlegst deine Netto-Preise für das Event. Auf dieser Basis berechnet Firmengolf den Verkaufspreis für das Unternehmen.' );
	fge_onboarding_form_open( $step, $partner_id, $token );
	$portal_url = trailingslashit( home_url( '/partnerportal/' ) );
	?>
	<div class="ob-form">
		<div class="ob-fixed full">
			<div class="ob-fixed-head">
				<span class="ob-fixed-l">Dein Netto-Preis bleibt dein Netto-Preis</span>
			</div>
			<p class="ob-fixed-body">Dein hinterlegter Netto-Preis ist der Betrag, den du nach Durchführung des Events an Firmengolf abrechnest. Der Firmengolf-Aufschlag wird zusätzlich kalkuliert und <strong>nicht</strong> von deinem Anteil abgezogen.</p>
		</div>
		<div class="ob-fixed full">
			<div class="ob-fixed-head">
				<span class="ob-fixed-l">So läuft die Abrechnung</span>
			</div>
			<p class="ob-fixed-body">Nach dem Event stellst du deine Leistung direkt an Firmengolf in Rechnung. Das Unternehmen erhält die Gesamtrechnung von Firmengolf.</p>
		</div>
		<div class="ob-portal-note full">
			<span class="ob-portal-note-ic" aria-hidden="true">⛳</span>
			<div>
				<div class="ob-portal-note-h">Unsere Rechnungsdaten findest du im Portal</div>
				<p>Sobald dein Profil bestätigt ist, findest du im Partner-Portal alle Rechnungsdaten von Firmengolf.</p>
			</div>
			<a class="ob-portal-note-btn" href="<?php echo esc_url( $portal_url ); ?>">Im Portal ansehen</a>
		</div>
		<div class="ob-fixed full">
			<div class="ob-fixed-head">
				<span class="ob-fixed-l">Bitte immer angeben</span>
				<span class="ob-fixed-badge">Pflicht</span>
			</div>
			<p class="ob-fixed-body">Gib auf jeder Rechnung die jeweilige <strong>Anfragenummer</strong> an, zum Beispiel FG-26-001. So können wir deine Rechnung eindeutig dem richtigen Event zuordnen.</p>
		</div>
	</div>
	<?php fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_11( int $step, int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( $step, 'Bilder, die euren Platz zeigen.', 'Logo und Titelbild sind wichtig, aber du kannst auch ohne weitermachen — wir erinnern dich daran, sobald wir dein Profil prüfen. Die Bildrechte-Bestätigung ist Pflicht.' );
	fge_onboarding_form_open( $step, $partner_id, $token );

	$rights_on = ( (string) ( $v['image_rights_confirmed'] ?? '' ) === '1' );
	fge_media_gallery_render( [ 'show_logo' => true ] );
	?>

	<label class="ob-consent">
		<input type="checkbox" name="fge_image_rights_confirmed" value="1" <?php checked( $rights_on ); ?> required>
		<span>Ich bestätige, dass ich das Recht habe, die hochgeladenen Bilder zu verwenden und auf Firmengolf zu veröffentlichen.</span>
	</label>
	<?php fge_onboarding_error( $errors, 'fge_image_rights_confirmed' );
	fge_onboarding_textarea( 'fge_image_rights_note', 'fge_image_rights_note', 'Hinweis zu Bildrechten (optional)', $v['image_rights_note'] ?? '' );
	fge_onboarding_next_btn( 'Weiter zur Zusammenfassung', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_12( int $step, int $partner_id, string $token, array $v ): void {
	$can_submit = fge_onboarding_is_submittable( $partner_id );
	$missing    = isset( $_GET['ob_missing'] );

	fge_onboarding_render_step_header( $step, 'Prüf deine Angaben — dann reichen wir ein.', 'Sieh nochmal über alles drüber, was du eingegeben hast. Korrigieren kannst du jeden Bereich mit „Zurück".' );

	if ( $missing ) { ?>
		<div class="ob-notice ob-notice--warn">Einige Pflichtangaben fehlen noch. Bitte gehe zurück und ergänze die markierten Felder.</div>
	<?php } ?>

	<div class="ob-summary">

		<?php fge_onboarding_summary_section( 'Golfplatz', [
			'Name'              => $v['public_golfclub_name'],
			'Betreiber'         => $v['legal_operator_name'],
			'Website'           => $v['website_url'],
			'Kurzbeschreibung'  => $v['public_short_description'],
		] ); ?>

		<?php fge_onboarding_summary_section( 'Standort', [
			'Adresse'   => trim( $v['street'] . ' ' . $v['house_number'] . ', ' . $v['postal_code'] . ' ' . $v['city'] ),
			'Bundesland' => $v['federal_state'],
		] ); ?>

		<?php fge_onboarding_summary_section( 'Hauptkontakt', [
			'Name'    => $v['main_contact_name'],
			'E-Mail'  => $v['main_contact_email'],
			'Telefon' => $v['main_contact_phone'],
			'Rolle'   => $v['main_contact_role'],
		] ); ?>

		<?php
		$further = fge_contacts_get( $partner_id );
		$further = array_values( array_filter( $further, static function ( $c ) { return (int) $c['user_id'] === 0; } ) );
		if ( ! empty( $further ) ) {
			$perm_labels = fge_contact_permissions();
			$rows = [];
			foreach ( $further as $c ) {
				$label = trim( ( $c['name'] ?: $c['email'] ) . ( $c['role'] ? ' · ' . $c['role'] : '' ) );
				$rows[ $label ] = ( $perm_labels[ $c['permission'] ] ?? $c['permission'] ) . ' · ' . $c['email'];
			}
			fge_onboarding_summary_section( 'Weitere Ansprechpartner', $rows );
		}
		?>

		<?php
		$infra_labels = fge_get_infrastructure_options();
		$active_infra = [];
		foreach ( $infra_labels as $key => $label ) {
			if ( ( $v[ $key ] ?? '' ) == '1' ) {
				$active_infra[] = $label;
			}
		}
		fge_onboarding_summary_section( 'Infrastruktur', [ 'Ausstattung' => implode( ', ', $active_infra ) ?: '—' ] );
		?>

		<?php fge_onboarding_summary_section( 'Kapazitäten', [
			'Teilnehmer Min/Max' => $v['participants_min_general'] . ' – ' . $v['participants_max_general'],
		] ); ?>

		<?php
		$fmt_labels  = fge_get_event_format_options();
		$active_fmts = [];
		foreach ( (array) $v['event_formats'] as $key ) {
			if ( isset( $fmt_labels[ $key ] ) ) {
				$active_fmts[] = $fmt_labels[ $key ];
			}
		}
		fge_onboarding_summary_section( 'Eventformate', [ 'Formate' => implode( ', ', $active_fmts ) ?: '—' ] );
		?>

		<?php fge_onboarding_summary_section( 'Verfügbarkeit', [
			'Saison'            => $v['season'],
			'Mindestvorlauf'    => $v['min_lead_time_days'] . ' Tage',
			'Individuelle Prüfung' => $v['individual_availability_check'] === '1' ? 'Ja' : 'Nein',
		] ); ?>

		<?php fge_onboarding_summary_section( 'Preis & Abrechnung', [
			'Modell'      => 'Netto-Preise pro Event im Portal',
			'Aufschlag'   => 'Firmengolf-Aufschlag wird zusätzlich kalkuliert',
			'Abrechnung'  => 'Rechnung an Firmengolf mit Anfragenummer',
		] ); ?>

		<?php fge_onboarding_summary_section( 'Medien', [
			'Logo'           => $v['logo_attachment_id'] > 0 ? 'Hochgeladen' : 'Nicht hochgeladen',
			'Titelbild'      => $v['hero_image_attachment_id'] > 0 ? 'Hochgeladen' : 'Nicht hochgeladen',
			'Bildrechte'     => $v['image_rights_confirmed'] === '1' ? 'Bestätigt ✓' : 'Nicht bestätigt',
		] ); ?>

	</div>

	<?php if ( $can_submit ) : ?>
	<?php // Submit button lives in the footer ("Zur Prüfung einreichen") and targets this form. ?>
	<form method="post" id="ob-step-form" class="ob-form">
		<?php wp_nonce_field( 'fge_onboarding_step_' . $step, 'fge_ob_nonce' ); ?>
		<input type="hidden" name="fge_ob_action" value="step_submit">
		<input type="hidden" name="ob_step" value="<?php echo esc_attr( (string) $step ); ?>">
	</form>
	<?php else : ?>
	<div class="ob-notice ob-notice--warn">Bitte ergänze die fehlenden Pflichtangaben, bevor du das Profil einreichst. Geh mit „Zurück" durch die Schritte und fülle die markierten Felder aus.</div>
	<?php endif; ?>
	<?php
}

function fge_onboarding_summary_section( string $title, array $rows ): void { ?>
<div class="ob-summary-section">
	<h3 class="ob-summary-title"><?php echo esc_html( $title ); ?></h3>
	<dl class="ob-summary-dl">
		<?php foreach ( $rows as $label => $value ) :
			if ( (string) $value === '' || $value === null ) { continue; } ?>
			<dt><?php echo esc_html( $label ); ?></dt>
			<dd><?php echo esc_html( (string) $value ); ?></dd>
		<?php endforeach; ?>
	</dl>
</div>
<?php }

function fge_onboarding_render_confirmation(): void { ?>
<div class="ob-wizard">
	<div class="ob-body ob-body--center">
		<div class="ob-confirm-icon">✓</div>
		<h1 class="ob-title">Danke — dein Profil ist eingereicht!</h1>
		<p class="ob-subtitle">Firmengolf prüft deine Angaben und meldet sich, wenn dein Golfplatz freigeschaltet ist oder noch Informationen fehlen.</p>
		<p class="ob-subtitle">Du erhältst in Kürze eine Bestätigung per E-Mail.</p>
		<div class="ob-actions ob-actions--center">
			<a href="<?php echo esc_url( trailingslashit( home_url( '/partnerportal/' ) ) ); ?>" class="ob-btn ob-btn-primary">Zum Partner-Portal →</a>
		</div>
	</div>
</div>
<?php }

function fge_onboarding_render_saved_notice( int $step, string $token ): void {
	$resume_url = fge_onboarding_step_url( $step, $token );
	?>
<div class="ob-wizard">
	<div class="ob-body ob-body--center">
		<h1 class="ob-title">Fortschritt gespeichert</h1>
		<p class="ob-subtitle">Kopiere diese URL, um später weiterzumachen:</p>
		<div class="ob-resume-url">
			<input type="text" value="<?php echo esc_attr( $resume_url ); ?>" readonly class="ob-input ob-input--mono" id="ob-resume-input">
			<button onclick="navigator.clipboard.writeText(document.getElementById('ob-resume-input').value)" class="ob-btn ob-btn-ghost" type="button">Kopieren</button>
		</div>
		<div class="ob-actions ob-actions--center">
			<a href="<?php echo esc_url( $resume_url ); ?>" class="ob-btn ob-btn-primary">Jetzt weitermachen →</a>
		</div>
	</div>
</div>
<?php }
