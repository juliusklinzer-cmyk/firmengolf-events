<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// AP14 — PARTNER ONBOARDING
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'init', 'fge_onboarding_handle_step', 5 );

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
	if ( $m( 'main_contact_name' ) === '' )        { return false; }
	if ( ! is_email( $m( 'main_contact_email' ) ) ) { return false; }
	if ( $m( 'street' ) === '' )                   { return false; }
	if ( $m( 'postal_code' ) === '' )              { return false; }
	if ( $m( 'city' ) === '' )                     { return false; }
	if ( $m( 'federal_state' ) === '' )            { return false; }
	if ( $m( 'free_region' ) === '' )              { return false; }
	if ( (int) $m( 'participants_min_general' ) <= 0 ) { return false; }
	if ( (int) $m( 'participants_max_general' ) < (int) $m( 'participants_min_general' ) ) { return false; }
	if ( $m( 'image_rights_confirmed' ) !== '1' ) { return false; }

	$infra_keys = array_keys( fge_get_infrastructure_options() );
	foreach ( $infra_keys as $key ) {
		if ( $m( $key ) === '1' ) { return true; } // at least one infra item
	}
	return false;
}

// ── Save step data ────────────────────────────────────────────────────────────

function fge_onboarding_save_step( int $partner_id, int $step, array $post ): void {
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

	switch ( $step ) {
		case 2:
			update_post_meta( $partner_id, '_fge_public_golfclub_name',    $s( 'fge_public_golfclub_name' ) );
			update_post_meta( $partner_id, '_fge_legal_operator_name',     $s( 'fge_legal_operator_name' ) );
			update_post_meta( $partner_id, '_fge_website_url',             $su( 'fge_website_url' ) );
			update_post_meta( $partner_id, '_fge_public_short_description', $sa( 'fge_public_short_description' ) );
			update_post_meta( $partner_id, '_fge_internal_note',           $sa( 'fge_internal_note' ) );
			// Sync post title to public name.
			wp_update_post( [ 'ID' => $partner_id, 'post_title' => $s( 'fge_public_golfclub_name' ) ] );
			break;

		case 3:
			update_post_meta( $partner_id, '_fge_street',        $s( 'fge_street' ) );
			update_post_meta( $partner_id, '_fge_house_number',  $s( 'fge_house_number' ) );
			update_post_meta( $partner_id, '_fge_postal_code',   $s( 'fge_postal_code' ) );
			update_post_meta( $partner_id, '_fge_city',          $s( 'fge_city' ) );
			update_post_meta( $partner_id, '_fge_federal_state', $san_select( 'fge_federal_state', $allowed_states ) );
			update_post_meta( $partner_id, '_fge_free_region',   $s( 'fge_free_region' ) );
			update_post_meta( $partner_id, '_fge_google_maps_url', $su( 'fge_google_maps_url' ) );
			break;

		case 4:
			$first = $s( 'fge_contact_first_name' );
			$last  = $s( 'fge_contact_last_name' );
			update_post_meta( $partner_id, '_fge_main_contact_name',  trim( $first . ' ' . $last ) );
			update_post_meta( $partner_id, '_fge_main_contact_email', sanitize_email( wp_unslash( $post['fge_contact_email'] ?? '' ) ) );
			update_post_meta( $partner_id, '_fge_main_contact_phone', $s( 'fge_contact_phone' ) );
			update_post_meta( $partner_id, '_fge_main_contact_role',  $s( 'fge_contact_role' ) );
			break;

		case 5:
			foreach ( [ 'event', 'gastro', 'golf_school', 'billing' ] as $prefix ) {
				update_post_meta( $partner_id, '_fge_' . $prefix . '_contact_name',  $s( 'fge_' . $prefix . '_contact_name' ) );
				update_post_meta( $partner_id, '_fge_' . $prefix . '_contact_email', sanitize_email( wp_unslash( $post[ 'fge_' . $prefix . '_contact_email' ] ?? '' ) ) );
				update_post_meta( $partner_id, '_fge_' . $prefix . '_contact_phone', $s( 'fge_' . $prefix . '_contact_phone' ) );
			}
			break;

		case 6:
			foreach ( $allowed_infra as $key ) {
				update_post_meta( $partner_id, '_fge_' . $key, isset( $post[ 'fge_' . $key ] ) ? 1 : 0 );
			}
			update_post_meta( $partner_id, '_fge_additional_equipment', $sa( 'fge_additional_equipment' ) );
			break;

		case 7:
			update_post_meta( $partner_id, '_fge_participants_min_general', absint( $post['fge_participants_min_general'] ?? 0 ) );
			update_post_meta( $partner_id, '_fge_participants_max_general', absint( $post['fge_participants_max_general'] ?? 0 ) );
			update_post_meta( $partner_id, '_fge_range_group_capacity',     absint( $post['fge_range_group_capacity'] ?? 0 ) );
			update_post_meta( $partner_id, '_fge_putting_green_capacity',   absint( $post['fge_putting_green_capacity'] ?? 0 ) );
			update_post_meta( $partner_id, '_fge_short_game_capacity',      absint( $post['fge_short_game_capacity'] ?? 0 ) );
			update_post_meta( $partner_id, '_fge_meeting_room_capacity',    absint( $post['fge_meeting_room_capacity'] ?? 0 ) );
			update_post_meta( $partner_id, '_fge_gastro_capacity',          absint( $post['fge_gastro_capacity'] ?? 0 ) );
			update_post_meta( $partner_id, '_fge_gastro_outdoor_capacity',  absint( $post['fge_gastro_outdoor_capacity'] ?? 0 ) );
			update_post_meta( $partner_id, '_fge_golf_teacher_capacity',    absint( $post['fge_golf_teacher_capacity'] ?? 0 ) );
			update_post_meta( $partner_id, '_fge_parking_count',            absint( $post['fge_parking_count'] ?? 0 ) );
			break;

		case 8:
			update_post_meta( $partner_id, '_fge_event_formats', $san_group( 'fge_event_formats', $allowed_formats ) );
			break;

		case 9:
			update_post_meta( $partner_id, '_fge_preferred_event_days',         $san_group( 'fge_preferred_event_days', $allowed_days ) );
			update_post_meta( $partner_id, '_fge_weekend_events_possible',       isset( $post['fge_weekend_events_possible'] ) ? 1 : 0 );
			update_post_meta( $partner_id, '_fge_evening_events_possible',       isset( $post['fge_evening_events_possible'] ) ? 1 : 0 );
			update_post_meta( $partner_id, '_fge_min_lead_time_days',            absint( $post['fge_min_lead_time_days'] ?? 14 ) );
			update_post_meta( $partner_id, '_fge_season',                        $san_select( 'fge_season', $allowed_seasons ) );
			update_post_meta( $partner_id, '_fge_individual_availability_check', isset( $post['fge_individual_availability_check'] ) ? 1 : 0 );
			break;

		case 10:
			$markup_raw = trim( wp_unslash( $post['fge_default_markup_percent'] ?? '' ) );
			$markup     = ( $markup_raw !== '' ) ? (float) str_replace( ',', '.', $markup_raw ) : 20.0;
			update_post_meta( $partner_id, '_fge_default_markup_percent',  $markup );
			update_post_meta( $partner_id, '_fge_vat_required',            isset( $post['fge_vat_required'] ) ? 1 : 0 );
			update_post_meta( $partner_id, '_fge_billing_method_internal', $san_select( 'fge_billing_method_internal', $allowed_billing ) );
			update_post_meta( $partner_id, '_fge_bank_details_available',  isset( $post['fge_bank_details_available'] ) ? 1 : 0 );
			update_post_meta( $partner_id, '_fge_internal_billing_note',   $sa( 'fge_internal_billing_note' ) );
			break;

		case 11:
			update_post_meta( $partner_id, '_fge_image_rights_confirmed', isset( $post['fge_image_rights_confirmed'] ) ? 1 : 0 );
			update_post_meta( $partner_id, '_fge_image_rights_note',      $sa( 'fge_image_rights_note' ) );

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
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				$hero_id = media_handle_upload( 'fge_partner_cover', $partner_id );
				if ( ! is_wp_error( $hero_id ) ) {
					update_post_meta( $partner_id, '_fge_hero_image_attachment_id', $hero_id );
				}
			}
			if ( ! empty( $_FILES['fge_gallery']['name'][0] ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				$existing = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $partner_id, '_fge_gallery_attachment_ids', true ) ) ) );
				foreach ( array_keys( $_FILES['fge_gallery']['name'] ) as $idx ) {
					if ( $_FILES['fge_gallery']['error'][ $idx ] !== UPLOAD_ERR_OK ) {
						continue;
					}
					$_FILES['fge_gallery_item'] = [
						'name'     => $_FILES['fge_gallery']['name'][ $idx ],
						'type'     => $_FILES['fge_gallery']['type'][ $idx ],
						'tmp_name' => $_FILES['fge_gallery']['tmp_name'][ $idx ],
						'error'    => $_FILES['fge_gallery']['error'][ $idx ],
						'size'     => $_FILES['fge_gallery']['size'][ $idx ],
					];
					$gid = media_handle_upload( 'fge_gallery_item', $partner_id );
					if ( ! is_wp_error( $gid ) ) {
						$existing[] = $gid;
					}
				}
				update_post_meta( $partner_id, '_fge_gallery_attachment_ids', implode( ',', $existing ) );
			}
			break;
	}

	update_post_meta( $partner_id, '_fge_onboarding_step', max( fge_onboarding_get_progress( $partner_id ), $step ) );
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

	$step = absint( $_POST['ob_step'] ?? 0 );
	if ( $step < 1 || $step > 12 ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_ob_nonce'] ?? '' ) ), 'fge_onboarding_step_' . $step ) ) {
		wp_die( 'Sicherheitsüberprüfung fehlgeschlagen.', '', [ 'response' => 403 ] );
	}

	// Step 1: create draft partner.
	if ( $step === 1 ) {
		$partner_id = fge_onboarding_create_draft_partner();
		if ( $partner_id <= 0 ) {
			wp_die( 'Fehler beim Erstellen des Profils.', '', [ 'response' => 500 ] );
		}
		$token = (string) get_post_meta( $partner_id, '_fge_onboarding_token', true );
		wp_redirect( fge_onboarding_step_url( 2, $token ) );
		exit;
	}

	// All other steps: find existing partner.
	$partner_id = fge_onboarding_get_current_partner_id();
	if ( $partner_id <= 0 ) {
		wp_redirect( fge_onboarding_page_url() );
		exit;
	}

	$token = (string) get_post_meta( $partner_id, '_fge_onboarding_token', true );

	// Validate step-specific required fields.
	$errors = fge_onboarding_validate_step( $step, $_POST );
	if ( ! empty( $errors ) ) {
		// Re-render with errors via GET redirect with error transient.
		$trans_key = 'fge_ob_err_' . $partner_id . '_' . $step;
		set_transient( $trans_key, $errors, 120 );
		wp_redirect( add_query_arg( 'ob_err', '1', fge_onboarding_step_url( $step, $token ) ) );
		exit;
	}

	// Step 4: create/assign user.
	if ( $step === 4 ) {
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
			// Admin flow: just save contact data.
			fge_onboarding_save_step( $partner_id, 4, $_POST );
		}
		update_post_meta( $partner_id, '_fge_onboarding_step', max( fge_onboarding_get_progress( $partner_id ), 4 ) );
		wp_redirect( fge_onboarding_step_url( 5 ) );
		exit;
	}

	// Step 12: submit.
	if ( $step === 12 ) {
		if ( ! fge_onboarding_is_submittable( $partner_id ) ) {
			wp_redirect( add_query_arg( 'ob_missing', '1', fge_onboarding_step_url( 12 ) ) );
			exit;
		}
		fge_onboarding_submit( $partner_id );
		wp_redirect( add_query_arg( 'ob_submitted', '1', fge_onboarding_page_url() ) );
		exit;
	}

	// All other steps: save data.
	fge_onboarding_save_step( $partner_id, $step, $_POST );

	// "Save & exit" button.
	if ( isset( $_POST['fge_ob_save_exit'] ) ) {
		if ( $step >= 4 ) {
			wp_redirect( trailingslashit( home_url( '/partnerportal/' ) ) );
		} else {
			wp_redirect( add_query_arg(
				[ 'ob_saved' => '1', 'ob_step' => $step, 'ob_token' => $token ],
				fge_onboarding_page_url()
			) );
		}
		exit;
	}

	// Advance to next step.
	$next_token = ( $step < 4 ) ? $token : '';
	wp_redirect( fge_onboarding_step_url( $step + 1, $next_token ) );
	exit;
}

// ── Per-step validation ───────────────────────────────────────────────────────

function fge_onboarding_validate_step( int $step, array $post ): array {
	$s = static function( string $k ) use ( $post ): string {
		return trim( sanitize_text_field( wp_unslash( $post[ $k ] ?? '' ) ) );
	};

	switch ( $step ) {
		case 2:
			return fge_onboarding_validate(
				[ 'fge_public_golfclub_name' => $s( 'fge_public_golfclub_name' ), 'fge_legal_operator_name' => $s( 'fge_legal_operator_name' ) ],
				[ 'fge_public_golfclub_name' => 'Golfplatz Name', 'fge_legal_operator_name' => 'Rechtlicher Betreibername' ]
			);

		case 3:
			return fge_onboarding_validate(
				[
					'fge_street'       => $s( 'fge_street' ),
					'fge_postal_code'  => $s( 'fge_postal_code' ),
					'fge_city'         => $s( 'fge_city' ),
					'fge_federal_state' => $s( 'fge_federal_state' ),
					'fge_free_region'  => $s( 'fge_free_region' ),
				],
				[
					'fge_street'       => 'Straße',
					'fge_postal_code'  => 'PLZ',
					'fge_city'         => 'Ort',
					'fge_federal_state' => 'Bundesland',
					'fge_free_region'  => 'Region',
				]
			);

		case 4:
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

		case 7:
			$min = absint( $post['fge_participants_min_general'] ?? 0 );
			$max = absint( $post['fge_participants_max_general'] ?? 0 );
			$errors = [];
			if ( $min <= 0 ) { $errors['fge_participants_min_general'] = 'Teilnehmer Minimum muss > 0 sein.'; }
			if ( $max <= 0 ) { $errors['fge_participants_max_general'] = 'Teilnehmer Maximum muss > 0 sein.'; }
			if ( $max > 0 && $min > 0 && $max < $min ) { $errors['fge_participants_max_general'] = 'Maximum muss ≥ Minimum sein.'; }
			return $errors;

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

	$step = max( 1, absint( $_GET['ob_step'] ?? 1 ) );
	if ( $step > 12 ) { $step = 12; }

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

	$save_exit_url = ( $step < 4 && $token !== '' )
		? add_query_arg( [ 'ob_saved' => '1', 'ob_step' => $step, 'ob_token' => $token ], fge_onboarding_page_url() )
		: trailingslashit( home_url( '/partnerportal/' ) );
	?>
	<div class="ob-wizard">
		<?php fge_onboarding_render_header( $save_exit_url ); ?>
		<div class="ob-body">
			<?php
			switch ( $step ) {
				case 1:  fge_onboarding_render_step_1( $token ); break;
				case 2:  fge_onboarding_render_step_2( $partner_id, $token, $vals, $errors ); break;
				case 3:  fge_onboarding_render_step_3( $partner_id, $token, $vals, $errors ); break;
				case 4:  fge_onboarding_render_step_4( $partner_id, $token, $vals, $errors ); break;
				case 5:  fge_onboarding_render_step_5( $partner_id, $token, $vals, $errors ); break;
				case 6:  fge_onboarding_render_step_6( $partner_id, $token, $vals, $errors ); break;
				case 7:  fge_onboarding_render_step_7( $partner_id, $token, $vals, $errors ); break;
				case 8:  fge_onboarding_render_step_8( $partner_id, $token, $vals, $errors ); break;
				case 9:  fge_onboarding_render_step_9( $partner_id, $token, $vals, $errors ); break;
				case 10: fge_onboarding_render_step_10( $partner_id, $token, $vals, $errors ); break;
				case 11: fge_onboarding_render_step_11( $partner_id, $token, $vals, $errors ); break;
				case 12: fge_onboarding_render_step_12( $partner_id, $token, $vals ); break;
			}
			?>
		</div>
		<?php if ( $step > 1 ) : ?>
		<?php fge_onboarding_render_footer( $step, $token ); ?>
		<?php endif; ?>
	</div>
	<?php
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
		'free_region'                   => (string) $m( 'free_region' ),
		'google_maps_url'               => (string) $m( 'google_maps_url' ),
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
		'weekend_events_possible'       => (string) $m( 'weekend_events_possible' ),
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
	];
}

// ── UI helpers ────────────────────────────────────────────────────────────────

function fge_onboarding_render_header( string $save_exit_url ): void { ?>
<header class="ob-header">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ob-header-logo">
		<span class="ob-logo-mark">⛳</span> Firmengolf
	</a>
	<a href="<?php echo esc_url( $save_exit_url ); ?>" class="ob-save-exit">Speichern &amp; beenden</a>
</header>
<?php }

function fge_onboarding_render_footer( int $step, string $token ): void {
	$total    = 12;
	$progress = round( ( $step - 1 ) / ( $total - 1 ) * 100 );
	$back_step = $step - 1;
	$back_url = $back_step >= 1 ? fge_onboarding_step_url( $back_step, ( $back_step < 4 ) ? $token : '' ) : '';
	?>
<footer class="ob-footer">
	<div class="ob-footer-back">
		<?php if ( $back_url !== '' ) : ?>
			<a href="<?php echo esc_url( $back_url ); ?>" class="ob-back-link">← Zurück</a>
		<?php endif; ?>
	</div>
	<div class="ob-progress-wrap">
		<div class="ob-progress-bar">
			<div class="ob-progress-fill" style="width:<?php echo esc_attr( $progress ); ?>%"></div>
		</div>
		<div class="ob-progress-label"><?php echo esc_html( $step ); ?> von <?php echo esc_html( $total ); ?></div>
	</div>
	<div class="ob-footer-spacer"></div>
</footer>
<?php }

function fge_onboarding_render_step_header( int $step, string $title, string $subtitle = '' ): void { ?>
<div class="ob-step-header">
	<div class="ob-step-chip">Schritt <?php echo esc_html( $step ); ?> von 12</div>
	<h1 class="ob-title"><?php echo esc_html( $title ); ?></h1>
	<?php if ( $subtitle !== '' ) : ?>
		<p class="ob-subtitle"><?php echo esc_html( $subtitle ); ?></p>
	<?php endif; ?>
</div>
<?php }

function fge_onboarding_next_btn( string $label = 'Weiter', string $extra_name = '' ): void { ?>
<div class="ob-actions">
	<?php if ( $extra_name !== '' ) : ?>
		<button type="submit" name="<?php echo esc_attr( $extra_name ); ?>" value="1" class="ob-btn ob-btn-ghost">Speichern &amp; beenden</button>
	<?php endif; ?>
	<button type="submit" class="ob-btn ob-btn-primary"><?php echo esc_html( $label ); ?> →</button>
</div>
<?php }

function fge_onboarding_form_open( int $step, int $partner_id, string $token ): void {
	$enc = ( $step === 11 ) ? ' enctype="multipart/form-data"' : '';
	printf(
		'<form method="post"%s class="ob-form">',
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

// ── Step renderers ────────────────────────────────────────────────────────────

function fge_onboarding_render_step_1( string $token ): void { ?>
<div class="ob-welcome">
	<div class="ob-welcome-icon">⛳</div>
	<h1 class="ob-title">Richte deinen Golfplatz als Eventlocation ein</h1>
	<p class="ob-subtitle">In wenigen Schritten erfassen wir die wichtigsten Informationen, damit Unternehmen deinen Golfplatz für Firmenevents anfragen können.</p>
	<ul class="ob-welcome-list">
		<li>✔ Standort und Kontaktdaten</li>
		<li>✔ Event-Infrastruktur und Kapazitäten</li>
		<li>✔ Verfügbarkeit und Preise</li>
		<li>✔ Logo und Bilder</li>
	</ul>
	<form method="post" class="ob-form">
		<?php wp_nonce_field( 'fge_onboarding_step_1', 'fge_ob_nonce' ); ?>
		<input type="hidden" name="fge_ob_action" value="step_submit">
		<input type="hidden" name="ob_step" value="1">
		<div class="ob-actions ob-actions--center">
			<button type="submit" class="ob-btn ob-btn-primary ob-btn-lg">Jetzt einrichten →</button>
		</div>
	</form>
</div>
<?php }

function fge_onboarding_render_step_2( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 2, 'Erzähl uns von deinem Golfplatz', 'Diese Informationen sind öffentlich auf Firmengolf sichtbar.' );
	fge_onboarding_form_open( 2, $partner_id, $token );
	fge_onboarding_input( 'fge_public_golfclub_name', 'fge_public_golfclub_name', 'Öffentlicher Golfplatzname', $v['public_golfclub_name'] ?? '', 'text', true, 'z.B. Golfclub Königsfeld', $errors );
	fge_onboarding_input( 'fge_legal_operator_name', 'fge_legal_operator_name', 'Rechtlicher Betreibername', $v['legal_operator_name'] ?? '', 'text', true, 'z.B. GC Königsfeld GmbH & Co. KG', $errors );
	fge_onboarding_input( 'fge_website_url', 'fge_website_url', 'Website (optional)', $v['website_url'] ?? '', 'url', false, 'https://' );
	fge_onboarding_textarea( 'fge_public_short_description', 'fge_public_short_description', 'Kurzbeschreibung (optional)', $v['public_short_description'] ?? '', 'Was macht deinen Golfplatz besonders für Firmenevents?' );
	fge_onboarding_textarea( 'fge_internal_note', 'fge_internal_note', 'Interne Notiz für Firmengolf (optional)', $v['internal_note'] ?? '' );
	fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_3( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 3, 'Wo liegt dein Golfplatz?', 'Wir zeigen deinen Standort in der Suche und auf der Profilseite.' );
	fge_onboarding_form_open( 3, $partner_id, $token );

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
	$regions = [
		'nord'      => 'Nord (Hamburg, Bremen, Schleswig-Holstein)',
		'nordost'   => 'Nordost (Berlin, Brandenburg, MV)',
		'ost'       => 'Ost (Sachsen, Sachsen-Anhalt, Thüringen)',
		'mitte'     => 'Mitte (Hessen, NRW, Rheinland-Pfalz)',
		'sued'      => 'Süd (Bayern, Baden-Württemberg)',
		'suedwest'  => 'Südwest (Saarland, Rheinland-Pfalz)',
		'west'      => 'West (NRW)',
		'sonstige'  => 'Sonstige',
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
	fge_onboarding_select( 'fge_free_region', 'fge_free_region', 'Region', $v['free_region'] ?? '', $regions, true, $errors );
	fge_onboarding_input( 'fge_google_maps_url', 'fge_google_maps_url', 'Google Maps Link (optional)', $v['google_maps_url'] ?? '', 'url', false, 'https://maps.google.com/...' );
	fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_4( int $partner_id, string $token, array $v, array $errors ): void {
	$name_parts = explode( ' ', $v['main_contact_name'] ?? '', 2 );
	$saved_first = $name_parts[0] ?? '';
	$saved_last  = $name_parts[1] ?? '';

	fge_onboarding_render_step_header( 4, 'Wer ist der Hauptansprechpartner?', 'Diese Person erhält die Login-Zugangsdaten für das Partner-Portal.' );
	fge_onboarding_form_open( 4, $partner_id, $token );
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

function fge_onboarding_render_step_5( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 5, 'Weitere Ansprechpartner (optional)', 'Hast du separate Ansprechpartner für Events, Gastronomie, Golfschule oder Abrechnung? Dieser Schritt ist optional.' );
	fge_onboarding_form_open( 5, $partner_id, $token );

	$contacts = [
		'event'       => 'Event-Ansprechpartner',
		'gastro'      => 'Gastronomie-Ansprechpartner',
		'golf_school' => 'Golflehrer / Golfschule',
		'billing'     => 'Abrechnung',
	];

	foreach ( $contacts as $prefix => $label ) : ?>
	<div class="ob-contact-group">
		<h3 class="ob-group-title"><?php echo esc_html( $label ); ?></h3>
		<?php fge_onboarding_input( 'fge_' . $prefix . '_contact_name', 'fge_' . $prefix . '_contact_name', 'Name', $v[ $prefix . '_contact_name' ] ?? '', 'text', false, 'Vor- und Nachname' ); ?>
		<div class="ob-field-row">
			<?php fge_onboarding_input( 'fge_' . $prefix . '_contact_email', 'fge_' . $prefix . '_contact_email', 'E-Mail', $v[ $prefix . '_contact_email' ] ?? '', 'email' ); ?>
			<?php fge_onboarding_input( 'fge_' . $prefix . '_contact_phone', 'fge_' . $prefix . '_contact_phone', 'Telefon', $v[ $prefix . '_contact_phone' ] ?? '', 'tel' ); ?>
		</div>
	</div>
	<?php endforeach;

	?>
	<div class="ob-actions">
		<button type="submit" name="fge_ob_save_exit" value="1" class="ob-btn ob-btn-ghost">Speichern &amp; beenden</button>
		<a href="<?php echo esc_url( fge_onboarding_step_url( 6 ) ); ?>" class="ob-btn ob-btn-ghost">Überspringen</a>
		<button type="submit" class="ob-btn ob-btn-primary">Weiter →</button>
	</div>
	<?php
	echo '</form>';
}

function fge_onboarding_render_step_6( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 6, 'Was bietet dein Golfplatz?', 'Wähle alles aus, was bei dir verfügbar ist.' );
	fge_onboarding_form_open( 6, $partner_id, $token );

	$options = fge_get_infrastructure_options();
	?>
	<div class="ob-card-grid">
		<?php foreach ( $options as $key => $label ) :
			$checked = ( ( $v[ $key ] ?? '' ) === '1' || ( $v[ $key ] ?? '' ) == 1 ); ?>
			<label class="ob-card<?php echo $checked ? ' is-selected' : ''; ?>">
				<input type="checkbox" name="fge_<?php echo esc_attr( $key ); ?>" value="1"
				       <?php checked( $checked ); ?> class="ob-card-input">
				<span class="ob-card-label"><?php echo esc_html( $label ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
	<?php fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_7( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 7, 'Wie viele Teilnehmer können kommen?', 'Diese Angaben helfen Unternehmen, passende Events zu finden.' );
	fge_onboarding_form_open( 7, $partner_id, $token );
	?>
	<div class="ob-field-row">
		<?php
		fge_onboarding_input( 'fge_participants_min_general', 'fge_participants_min_general', 'Minimum Teilnehmer', $v['participants_min_general'] ?? '', 'number', true, '10', $errors );
		fge_onboarding_input( 'fge_participants_max_general', 'fge_participants_max_general', 'Maximum Teilnehmer', $v['participants_max_general'] ?? '', 'number', true, '100', $errors );
		?>
	</div>
	<div class="ob-field-row">
		<?php
		fge_onboarding_input( 'fge_range_group_capacity', 'fge_range_group_capacity', 'Driving Range Kapazität', $v['range_group_capacity'] ?? '', 'number', false, '0' );
		fge_onboarding_input( 'fge_putting_green_capacity', 'fge_putting_green_capacity', 'Putting Green Kapazität', $v['putting_green_capacity'] ?? '', 'number', false, '0' );
		?>
	</div>
	<div class="ob-field-row">
		<?php
		fge_onboarding_input( 'fge_short_game_capacity', 'fge_short_game_capacity', 'Kurzspielbereich Kapazität', $v['short_game_capacity'] ?? '', 'number', false, '0' );
		fge_onboarding_input( 'fge_meeting_room_capacity', 'fge_meeting_room_capacity', 'Meetingraum Kapazität', $v['meeting_room_capacity'] ?? '', 'number', false, '0' );
		?>
	</div>
	<div class="ob-field-row">
		<?php
		fge_onboarding_input( 'fge_gastro_capacity', 'fge_gastro_capacity', 'Restaurant innen Kapazität', $v['gastro_capacity'] ?? '', 'number', false, '0' );
		fge_onboarding_input( 'fge_gastro_outdoor_capacity', 'fge_gastro_outdoor_capacity', 'Restaurant außen Kapazität', $v['gastro_outdoor_capacity'] ?? '', 'number', false, '0' );
		?>
	</div>
	<div class="ob-field-row">
		<?php
		fge_onboarding_input( 'fge_golf_teacher_capacity', 'fge_golf_teacher_capacity', 'Anzahl Golflehrer', $v['golf_teacher_capacity'] ?? '', 'number', false, '0' );
		fge_onboarding_input( 'fge_parking_count', 'fge_parking_count', 'Anzahl Parkplätze (optional)', $v['parking_count'] ?? '', 'number', false, '0' );
		?>
	</div>
	<?php fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_8( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 8, 'Welche Eventformate bietest du an?', 'Wähle alle passenden Formate. Dieser Schritt ist optional.' );
	fge_onboarding_form_open( 8, $partner_id, $token );

	$formats      = fge_get_event_format_options();
	$saved_formats = (array) ( $v['event_formats'] ?? [] );
	?>
	<div class="ob-card-grid">
		<?php foreach ( $formats as $key => $label ) :
			$checked = in_array( $key, $saved_formats, true ); ?>
			<label class="ob-card<?php echo $checked ? ' is-selected' : ''; ?>">
				<input type="checkbox" name="fge_event_formats[]" value="<?php echo esc_attr( $key ); ?>"
				       <?php checked( $checked ); ?> class="ob-card-input">
				<span class="ob-card-label"><?php echo esc_html( $label ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
	<div class="ob-actions">
		<button type="submit" name="fge_ob_save_exit" value="1" class="ob-btn ob-btn-ghost">Speichern &amp; beenden</button>
		<a href="<?php echo esc_url( fge_onboarding_step_url( 9 ) ); ?>" class="ob-btn ob-btn-ghost">Überspringen</a>
		<button type="submit" class="ob-btn ob-btn-primary">Weiter →</button>
	</div>
	<?php echo '</form>';
}

function fge_onboarding_render_step_9( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 9, 'Wann sind Events bei dir möglich?', 'Diese Angaben helfen uns bei der Suche und Empfehlung.' );
	fge_onboarding_form_open( 9, $partner_id, $token );

	$weekdays = [
		'monday' => 'Mo', 'tuesday' => 'Di', 'wednesday' => 'Mi',
		'thursday' => 'Do', 'friday' => 'Fr', 'saturday' => 'Sa', 'sunday' => 'So',
	];
	$saved_days = (array) ( $v['preferred_event_days'] ?? [] );
	$seasons = [
		'year_round'       => 'Ganzjährig',
		'march_to_october' => 'März – Oktober',
		'april_to_october' => 'April – Oktober',
		'on_request'       => 'Auf Anfrage',
	];
	?>
	<div class="ob-field">
		<label class="ob-label">Bevorzugte Wochentage</label>
		<div class="ob-weekday-row">
			<?php foreach ( $weekdays as $val => $label ) : ?>
				<label class="ob-weekday<?php echo in_array( $val, $saved_days, true ) ? ' is-selected' : ''; ?>">
					<input type="checkbox" name="fge_preferred_event_days[]" value="<?php echo esc_attr( $val ); ?>"
					       <?php checked( in_array( $val, $saved_days, true ) ); ?> class="ob-card-input">
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="ob-field-row">
		<div class="ob-field">
			<label class="ob-label">Wochenend-Events möglich</label>
			<label class="ob-toggle">
				<input type="checkbox" name="fge_weekend_events_possible" value="1"
				       <?php checked( $v['weekend_events_possible'] ?? '', '1' ); ?>>
				<span class="ob-toggle-track"><span class="ob-toggle-thumb"></span></span>
				<span>Ja</span>
			</label>
		</div>
		<div class="ob-field">
			<label class="ob-label">Abend-Events möglich</label>
			<label class="ob-toggle">
				<input type="checkbox" name="fge_evening_events_possible" value="1"
				       <?php checked( $v['evening_events_possible'] ?? '', '1' ); ?>>
				<span class="ob-toggle-track"><span class="ob-toggle-thumb"></span></span>
				<span>Ja</span>
			</label>
		</div>
	</div>
	<?php
	fge_onboarding_input( 'fge_min_lead_time_days', 'fge_min_lead_time_days', 'Mindestvorlauf in Tagen', $v['min_lead_time_days'] !== '' ? $v['min_lead_time_days'] : '14', 'number', false, '14' );
	fge_onboarding_select( 'fge_season', 'fge_season', 'Saison / Verfügbarkeitszeitraum', $v['season'] ?? '', $seasons );
	?>
	<div class="ob-field">
		<label class="ob-label">Individuelle Verfügbarkeitsprüfung erforderlich</label>
		<label class="ob-toggle">
			<input type="checkbox" name="fge_individual_availability_check" value="1"
			       <?php checked( $v['individual_availability_check'] ?? '1', '1' ); ?>>
			<span class="ob-toggle-track"><span class="ob-toggle-thumb"></span></span>
			<span>Ja (empfohlen)</span>
		</label>
		<p class="ob-hint">Im MVP ist die individuelle Prüfung standardmäßig aktiv.</p>
	</div>
	<?php
	fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_10( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 10, 'Preise und Abrechnung', 'Diese Daten sind nicht öffentlich und nur für Firmengolf sichtbar.' );
	fge_onboarding_form_open( 10, $partner_id, $token );

	$billing_methods = [
		'manual_clarification'                  => 'Manuelle Klärung',
		'invoice_from_partner_to_firmengolf'    => 'Rechnung vom Partner an Firmengolf',
		'credit_note'                           => 'Gutschrift',
		'other'                                 => 'Sonstiges',
	];
	$markup = $v['default_markup_percent'] !== '' ? $v['default_markup_percent'] : '20';
	fge_onboarding_input( 'fge_default_markup_percent', 'fge_default_markup_percent', 'Standard Firmengolf Aufschlag (%)', $markup, 'number', false, '20' );
	?>
	<div class="ob-field-row">
		<div class="ob-field">
			<label class="ob-label">Umsatzsteuerpflichtig</label>
			<label class="ob-toggle">
				<input type="checkbox" name="fge_vat_required" value="1"
				       <?php checked( $v['vat_required'] ?? '', '1' ); ?>>
				<span class="ob-toggle-track"><span class="ob-toggle-thumb"></span></span>
				<span>Ja</span>
			</label>
		</div>
		<div class="ob-field">
			<label class="ob-label">Bankdaten vorhanden</label>
			<label class="ob-toggle">
				<input type="checkbox" name="fge_bank_details_available" value="1"
				       <?php checked( $v['bank_details_available'] ?? '', '1' ); ?>>
				<span class="ob-toggle-track"><span class="ob-toggle-thumb"></span></span>
				<span>Ja</span>
			</label>
		</div>
	</div>
	<?php
	fge_onboarding_select( 'fge_billing_method_internal', 'fge_billing_method_internal', 'Abrechnungsmethode', $v['billing_method_internal'] ?? '', $billing_methods );
	fge_onboarding_textarea( 'fge_internal_billing_note', 'fge_internal_billing_note', 'Interne Abrechnungsnotiz (optional)', $v['internal_billing_note'] ?? '' );
	fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_11( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 11, 'Logo und Bilder', 'Ein gutes Foto macht deinen Golfplatz sofort erkennbar.' );
	fge_onboarding_form_open( 11, $partner_id, $token );

	$logo_id   = (int) ( $v['logo_attachment_id'] ?? 0 );
	$logo_url  = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
	$cover_id  = (int) ( $v['hero_image_attachment_id'] ?? 0 );
	$cover_url = $cover_id > 0 ? (string) wp_get_attachment_image_url( $cover_id, 'large' ) : '';
	?>
	<div class="ob-media-row">
		<div class="ob-media-block">
			<div class="ob-media-lbl">Logo</div>
			<label class="ob-logo-upload<?php echo $logo_url !== '' ? ' has-image' : ''; ?>" title="Logo hochladen">
				<?php if ( $logo_url !== '' ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo">
				<?php else : ?>
					<span class="ob-upload-icon">↑</span>
					<span class="ob-upload-hint">PNG · 400×400 empfohlen</span>
				<?php endif; ?>
				<input type="file" name="fge_partner_logo" accept="image/*" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
			</label>
		</div>
		<div class="ob-media-block ob-media-block--wide">
			<div class="ob-media-lbl">Titelbild</div>
			<label class="ob-cover-upload<?php echo $cover_url !== '' ? ' has-image' : ''; ?>"
			       style="<?php echo $cover_url !== '' ? 'background-image:url(' . esc_url( $cover_url ) . ');background-size:cover;background-position:center;' : ''; ?>"
			       title="Titelbild hochladen">
				<?php if ( $cover_url === '' ) : ?>
					<span class="ob-upload-icon">↑</span>
					<span class="ob-upload-hint">16:9 · mind. 1600 px · JPG/PNG</span>
				<?php else : ?>
					<span class="ob-cover-replace">Bild tauschen</span>
				<?php endif; ?>
				<input type="file" name="fge_partner_cover" accept="image/*" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
			</label>
		</div>
	</div>
	<div class="ob-media-block ob-media-block--gallery">
		<div class="ob-media-lbl">Galerie (optional, mehrere Fotos möglich)</div>
		<input type="file" name="fge_gallery[]" accept="image/*" multiple class="ob-gallery-input">
	</div>

	<div class="ob-field ob-field--rights">
		<label class="ob-checkbox-label">
			<input type="checkbox" name="fge_image_rights_confirmed" value="1"
			       <?php checked( $v['image_rights_confirmed'] ?? '', '1' ); ?> required>
			<span>Ich bestätige, dass ich das Recht habe, die hochgeladenen Bilder zu verwenden und auf Firmengolf zu veröffentlichen.</span>
		</label>
		<?php fge_onboarding_error( $errors, 'fge_image_rights_confirmed' ); ?>
	</div>
	<?php fge_onboarding_textarea( 'fge_image_rights_note', 'fge_image_rights_note', 'Hinweis zu Bildrechten (optional)', $v['image_rights_note'] ?? '' );
	fge_onboarding_next_btn( 'Weiter zur Zusammenfassung', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_12( int $partner_id, string $token, array $v ): void {
	$can_submit = fge_onboarding_is_submittable( $partner_id );
	$missing    = isset( $_GET['ob_missing'] );

	fge_onboarding_render_step_header( 12, 'Alles bereit?', 'Prüfe deine Angaben und reiche das Profil zur Überprüfung ein.' );

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
			'Region'    => $v['free_region'],
		] ); ?>

		<?php fge_onboarding_summary_section( 'Hauptkontakt', [
			'Name'    => $v['main_contact_name'],
			'E-Mail'  => $v['main_contact_email'],
			'Telefon' => $v['main_contact_phone'],
			'Rolle'   => $v['main_contact_role'],
		] ); ?>

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

		<?php fge_onboarding_summary_section( 'Abrechnung', [
			'Aufschlag'         => $v['default_markup_percent'] . ' %',
			'USt-pflichtig'     => $v['vat_required'] === '1' ? 'Ja' : 'Nein',
			'Abrechnungsmethode' => $v['billing_method_internal'],
		] ); ?>

		<?php fge_onboarding_summary_section( 'Medien', [
			'Logo'           => $v['logo_attachment_id'] > 0 ? 'Hochgeladen' : 'Nicht hochgeladen',
			'Titelbild'      => $v['hero_image_attachment_id'] > 0 ? 'Hochgeladen' : 'Nicht hochgeladen',
			'Bildrechte'     => $v['image_rights_confirmed'] === '1' ? 'Bestätigt ✓' : 'Nicht bestätigt',
		] ); ?>

	</div>

	<?php if ( $can_submit ) : ?>
	<form method="post" class="ob-form">
		<?php wp_nonce_field( 'fge_onboarding_step_12', 'fge_ob_nonce' ); ?>
		<input type="hidden" name="fge_ob_action" value="step_submit">
		<input type="hidden" name="ob_step" value="12">
		<div class="ob-actions">
			<a href="<?php echo esc_url( fge_onboarding_step_url( 2 ) ); ?>" class="ob-btn ob-btn-ghost">Zurück zum Bearbeiten</a>
			<button type="submit" class="ob-btn ob-btn-primary">Jetzt zur Prüfung einreichen →</button>
		</div>
	</form>
	<?php else : ?>
	<div class="ob-notice ob-notice--warn">Bitte ergänze die fehlenden Pflichtangaben, bevor du das Profil einreichst.</div>
	<div class="ob-actions">
		<a href="<?php echo esc_url( fge_onboarding_step_url( 2 ) ); ?>" class="ob-btn ob-btn-primary">Fehlende Daten ergänzen</a>
	</div>
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
