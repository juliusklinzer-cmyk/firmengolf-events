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
	if ( ! in_array( $m( 'golf_type' ), array_keys( fge_catalog_golf_types() ), true ) ) { return false; }
	if ( $m( 'main_contact_name' ) === '' )        { return false; }
	if ( ! is_email( $m( 'main_contact_email' ) ) ) { return false; }
	if ( $m( 'street' ) === '' )                   { return false; }
	if ( $m( 'postal_code' ) === '' )              { return false; }
	if ( $m( 'city' ) === '' )                     { return false; }
	if ( $m( 'federal_state' ) === '' )            { return false; }
	if ( $m( 'free_region' ) === '' )              { return false; }

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
			update_post_meta( $partner_id, '_fge_golf_type',               $san_select( 'fge_golf_type', array_keys( fge_catalog_golf_types() ) ) );
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
			// Anfahrt & Location (same keys as admin meta box / Platz view).
			update_post_meta( $partner_id, '_fge_poi_car',         $s( 'fge_poi_car' ) );
			update_post_meta( $partner_id, '_fge_poi_parking',     $s( 'fge_poi_parking' ) );
			update_post_meta( $partner_id, '_fge_poi_train',       $s( 'fge_poi_train' ) );
			update_post_meta( $partner_id, '_fge_poi_shuttle',     $s( 'fge_poi_shuttle' ) );
			update_post_meta( $partner_id, '_fge_arrival_estation', ( ( $post['fge_arrival_estation'] ?? '' ) === '1' ) ? 1 : 0 );
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

		case 6:
			// New catalog model: single `_fge_infra` array (52 ids incl. Gastronomie group).
			update_post_meta( $partner_id, '_fge_infra', $san_group( 'fge_infra', fge_catalog_infra_ids() ) );
			update_post_meta( $partner_id, '_fge_additional_equipment', $sa( 'fge_additional_equipment' ) );
			break;

		case 7:
			// New catalog model: single `_fge_cap` array keyed by cap_keys (min, max + conditional rows).
			$cap_in  = is_array( $post['fge_cap'] ?? null ) ? $post['fge_cap'] : [];
			$cap_out = [];
			foreach ( fge_catalog_cap_keys() as $ck ) {
				$cap_out[ $ck ] = absint( $cap_in[ $ck ] ?? 0 );
			}
			update_post_meta( $partner_id, '_fge_cap', $cap_out );
			break;

		case 8:
			update_post_meta( $partner_id, '_fge_event_formats', $san_group( 'fge_event_formats', $allowed_formats ) );
			break;

		case 9:
			update_post_meta( $partner_id, '_fge_preferred_event_days',         $san_group( 'fge_preferred_event_days', $allowed_days ) );
			update_post_meta( $partner_id, '_fge_weekend_events_possible',       ( ( $post['fge_weekend_events_possible'] ?? '' ) === '1' ) ? 1 : 0 );
			update_post_meta( $partner_id, '_fge_evening_events_possible',       ( ( $post['fge_evening_events_possible'] ?? '' ) === '1' ) ? 1 : 0 );
			update_post_meta( $partner_id, '_fge_min_lead_time_days',            absint( $post['fge_min_lead_time_days'] ?? 14 ) );
			update_post_meta( $partner_id, '_fge_season',                        $san_select( 'fge_season', $allowed_seasons ) );
			update_post_meta( $partner_id, '_fge_individual_availability_check', ( ( $post['fge_individual_availability_check'] ?? '1' ) !== '0' ) ? 1 : 0 );
			break;

		case 10:
			// Pricing step is info-only now (net stays net; markup added by Firmengolf;
			// actual net prices are set per event in the portal). Nothing to persist.
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
			$errors = fge_onboarding_validate(
				[ 'fge_public_golfclub_name' => $s( 'fge_public_golfclub_name' ), 'fge_legal_operator_name' => $s( 'fge_legal_operator_name' ) ],
				[ 'fge_public_golfclub_name' => 'Golfplatz Name', 'fge_legal_operator_name' => 'Rechtlicher Betreibername' ]
			);
			if ( ! in_array( $s( 'fge_golf_type' ), array_keys( fge_catalog_golf_types() ), true ) ) {
				$errors['fge_golf_type'] = 'Bitte wähle aus, was dein Golfangebot am besten beschreibt.';
			}
			return $errors;

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
			$cap = is_array( $post['fge_cap'] ?? null ) ? $post['fge_cap'] : [];
			$min = absint( $cap['min'] ?? 0 );
			$max = absint( $cap['max'] ?? 0 );
			$errors = [];
			if ( $min <= 0 ) { $errors['fge_cap_min'] = 'Teilnehmer-Minimum muss > 0 sein.'; }
			if ( $max <= 0 ) { $errors['fge_cap_max'] = 'Teilnehmer-Maximum muss > 0 sein.'; }
			if ( $max > 0 && $min > 0 && $max < $min ) { $errors['fge_cap_max'] = 'Maximum muss ≥ Minimum sein.'; }
			return $errors;

		case 6:
			$infra = is_array( $post['fge_infra'] ?? null ) ? array_intersect( array_map( 'sanitize_text_field', $post['fge_infra'] ), fge_catalog_infra_ids() ) : [];
			return empty( $infra )
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
	<div class="ob-shell">
		<?php fge_onboarding_render_topbar( $save_exit_url, $step ); ?>
		<main class="ob-stage<?php echo ( 1 === $step ) ? ' is-intro' : ''; ?>">
			<div class="ob-step<?php echo in_array( $step, [ 6, 8, 12 ], true ) ? ' ob-step-wide' : ''; ?>">
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
		</main>
		<?php if ( $step > 1 ) { fge_onboarding_render_footer( $step, $token ); } ?>
		<?php fge_onboarding_render_help(); ?>
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

function fge_onboarding_render_topbar( string $save_exit_url, int $step ): void { ?>
<header class="ob-topbar">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ob-brand" aria-label="Firmengolf Startseite">Firmengolf</a>
	<div class="ob-top-actions">
		<button type="button" class="ob-top-pill" data-ob-help>Noch Fragen?</button>
		<?php if ( $step > 1 ) : ?>
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
		1 => [ 'label' => 'Dein Platz',             'steps' => [ 2, 3, 4, 5 ] ],
		2 => [ 'label' => 'Dein Angebot',           'steps' => [ 6, 7, 8 ] ],
		3 => [ 'label' => 'Verfügbarkeit & Preis',  'steps' => [ 9, 10, 11 ] ],
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

	$back_step = $step - 1;
	$back_url  = $back_step >= 1 ? fge_onboarding_step_url( $back_step, ( $back_step < 4 ) ? $token : '' ) : '';
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
			<div class="ob-nav-right"></div>
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

	// Golfangebot (single-select) — maps to _fge_golf_type (catalog of 10).
	$golf_types = fge_catalog_golf_types();
	$gt_current = (string) ( $v['golf_type'] ?? '' );
	?>
	<div class="ob-field full">
		<label class="ob-field-label">Was beschreibt dein Golfangebot am besten? <span class="ob-required">*</span></label>
		<span class="ob-field-hint">Wähl die Art, die euren Platz am besten beschreibt.</span>
	</div>
	<div class="ob-cards">
		<?php foreach ( $golf_types as $id => $label ) :
			$on = ( $gt_current === $id ); ?>
		<label class="ob-card<?php echo $on ? ' on' : ''; ?>">
			<input type="radio" class="ob-card-input" name="fge_golf_type" value="<?php echo esc_attr( $id ); ?>" <?php checked( $on ); ?>>
			<span class="ob-card-check" aria-hidden="true">✓</span>
			<span class="ob-card-l"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php endforeach; ?>
	</div>
	<?php fge_onboarding_error( $errors, 'fge_golf_type' );

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

	// Anfahrt & Location — maps to _fge_poi_* / _fge_arrival_estation (same keys as admin/Platz view).
	$estation = ( (string) ( $v['arrival_estation'] ?? '' ) === '1' );
	?>
	<div class="ob-cat-h" style="margin-top:8px">Anfahrt &amp; Location</div>
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
			<label class="ob-radio<?php echo $estation ? ' on' : ''; ?>">
				<input type="radio" name="fge_arrival_estation" value="1" <?php checked( $estation ); ?> style="position:absolute;opacity:0">
				<span class="ob-radio-dot" aria-hidden="true"></span> Ja
			</label>
			<label class="ob-radio<?php echo $estation ? '' : ' on'; ?>">
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
	fge_onboarding_render_step_header( 5, 'Möchtest du weitere Ansprechpartner hinzufügen?', 'Optional — du kannst jederzeit später ergänzen. Diese Personen brauchen keinen eigenen Account; sie werden nur per E-Mail informiert oder können bei der Termin-Freigabe abstimmen.' );
	fge_onboarding_form_open( 5, $partner_id, $token );

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

function fge_onboarding_render_step_6( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 6, 'Was bietet dein Golfplatz?', 'Wähle alles aus, was bei dir verfügbar ist. Diese Ausstattung erscheint später auf deiner Platz-Seite.' );
	fge_onboarding_form_open( 6, $partner_id, $token );

	$groups   = fge_catalog_infra_groups();
	$selected = is_array( $v['infra'] ?? null ) ? $v['infra'] : [];
	fge_onboarding_error( $errors, 'fge_infra' );
	?>
	<div class="ob-form">
		<?php foreach ( $groups as $group_label => $items ) : ?>
		<div class="ob-cat">
			<div class="ob-cat-h"><?php echo esc_html( $group_label ); ?></div>
			<div class="ob-cards">
				<?php foreach ( $items as $id => $label ) :
					$on = in_array( $id, $selected, true ); ?>
				<label class="ob-card<?php echo $on ? ' on' : ''; ?>">
					<input type="checkbox" class="ob-card-input" name="fge_infra[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $on ); ?>>
					<span class="ob-card-check" aria-hidden="true">✓</span>
					<span class="ob-card-l"><?php echo esc_html( $label ); ?></span>
				</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
		<div class="ob-field full">
			<label class="ob-field-label" for="fge_additional_equipment">Weitere Ausstattung (optional)</label>
			<span class="ob-field-hint">Etwas, das oben fehlt? Trag es hier frei ein.</span>
			<textarea class="ob-input" id="fge_additional_equipment" name="fge_additional_equipment" placeholder="z. B. E-Trolleys, Cart-Flotte, Wellness-Bereich …"><?php echo esc_textarea( (string) ( $v['additional_equipment'] ?? '' ) ); ?></textarea>
		</div>
	</div>
	<?php fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
}

function fge_onboarding_render_step_7( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 7, 'Wie viele Gäste passen wo?', 'Mindest- und Maximal-Teilnehmerzahl sind Pflicht. Für die in Schritt 6 gewählten Bereiche fragen wir zusätzlich die Kapazität ab.' );
	fge_onboarding_form_open( 7, $partner_id, $token );

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
			<label class="ob-radio<?php echo $is_yes ? ' on' : ''; ?>">
				<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $is_yes ); ?> style="position:absolute;opacity:0">
				<span class="ob-radio-dot" aria-hidden="true"></span> Ja
			</label>
			<label class="ob-radio<?php echo $is_yes ? '' : ' on'; ?>">
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

function fge_onboarding_render_step_8( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 8, 'Welche Eventformate bietest du an?', 'Wähle alle passenden Formate. Dieser Schritt ist optional.' );
	fge_onboarding_form_open( 8, $partner_id, $token );

	$formats       = fge_get_event_format_options();
	$saved_formats = (array) ( $v['event_formats'] ?? [] );
	?>
	<div class="ob-cards">
		<?php foreach ( $formats as $key => $label ) :
			$on = in_array( $key, $saved_formats, true ); ?>
		<label class="ob-card<?php echo $on ? ' on' : ''; ?>">
			<input type="checkbox" class="ob-card-input" name="fge_event_formats[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $on ); ?>>
			<span class="ob-card-check" aria-hidden="true">✓</span>
			<span class="ob-card-l"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php endforeach; ?>
	</div>
	<?php fge_onboarding_next_btn( 'Weiter', 'fge_ob_save_exit' );
	echo '</form>';
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
		fge_onboarding_yesno( 'fge_weekend_events_possible', 'Wochenend-Events möglich?', 'Samstag und/oder Sonntag, ggf. mit Aufschlag.', ( (string) ( $v['weekend_events_possible'] ?? '' ) === '1' ) );
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

function fge_onboarding_render_step_10( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 10, 'Preis und Abrechnung', 'Du hinterlegst deine Netto-Preise später pro Event im Portal. Auf dieser Basis berechnet Firmengolf den Verkaufspreis für das Unternehmen.' );
	fge_onboarding_form_open( 10, $partner_id, $token );
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

function fge_onboarding_render_step_11( int $partner_id, string $token, array $v, array $errors ): void {
	fge_onboarding_render_step_header( 11, 'Logo und Bilder', 'Ein gutes Foto macht deinen Golfplatz sofort erkennbar.' );
	fge_onboarding_form_open( 11, $partner_id, $token );

	$logo_id   = (int) ( $v['logo_attachment_id'] ?? 0 );
	$logo_url  = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
	$cover_id  = (int) ( $v['hero_image_attachment_id'] ?? 0 );
	$cover_url = $cover_id > 0 ? (string) wp_get_attachment_image_url( $cover_id, 'large' ) : '';
	$gallery   = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $partner_id, '_fge_gallery_attachment_ids', true ) ) ) );
	$rights_on = ( (string) ( $v['image_rights_confirmed'] ?? '' ) === '1' );
	?>
	<div class="ob-uploads">
		<div class="ob-upload-row">
			<div class="ob-upload-text">
				<div class="ob-upload-l">Logo</div>
				<div class="ob-upload-h">PNG mit transparentem Hintergrund, ca. 400×400 px. Erscheint in der Trefferliste.</div>
			</div>
			<label class="ob-upload-dropzone a-logo<?php echo '' !== $logo_url ? ' filled' : ''; ?>">
				<?php if ( '' !== $logo_url ) : ?>
					<span class="ob-upload-preview" style="background-image:url('<?php echo esc_url( $logo_url ); ?>');background-size:contain;background-position:center;background-repeat:no-repeat;background-color:var(--paper-50);"></span>
					<span class="ob-upload-meta"><span class="ob-upload-name">Logo hochgeladen</span><span class="ob-upload-remove">Tauschen</span></span>
				<?php else : ?>
					<span class="ob-upload-cta">↑ Logo wählen</span>
				<?php endif; ?>
				<input type="file" name="fge_partner_logo" accept="image/*" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
			</label>
		</div>

		<div class="ob-upload-row">
			<div class="ob-upload-text">
				<div class="ob-upload-l">Titelbild</div>
				<div class="ob-upload-h">16:9, mind. 1600 px breit (JPG/PNG). Das große Bild oben auf deiner Platz-Seite.</div>
			</div>
			<label class="ob-upload-dropzone a-hero<?php echo '' !== $cover_url ? ' filled' : ''; ?>">
				<?php if ( '' !== $cover_url ) : ?>
					<span class="ob-upload-preview" style="background-image:url('<?php echo esc_url( $cover_url ); ?>');background-size:cover;background-position:center;"></span>
					<span class="ob-upload-meta"><span class="ob-upload-name">Titelbild hochgeladen</span><span class="ob-upload-remove">Tauschen</span></span>
				<?php else : ?>
					<span class="ob-upload-cta">↑ Titelbild wählen</span>
				<?php endif; ?>
				<input type="file" name="fge_partner_cover" accept="image/*" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
			</label>
		</div>

		<div class="ob-upload-row">
			<div class="ob-upload-text">
				<div class="ob-upload-l">Galerie</div>
				<div class="ob-upload-h">Optional, mehrere Fotos. Spielbahnen, Clubhaus, Eventflächen, Gastronomie.</div>
			</div>
			<div>
				<label class="ob-upload-dropzone a-gallery">
					<span class="ob-upload-cta">↑ Fotos hinzufügen</span>
					<input type="file" name="fge_gallery[]" accept="image/*" multiple style="position:absolute;inset:0;opacity:0;cursor:pointer;">
				</label>
				<?php if ( ! empty( $gallery ) ) : ?>
				<div class="ob-gallery-list">
					<?php foreach ( $gallery as $gid ) :
						$gurl = wp_get_attachment_image_url( $gid, 'thumbnail' ); ?>
					<div class="ob-gallery-item">
						<span class="ob-gallery-thumb" style="<?php echo $gurl ? 'background-image:url(' . esc_url( $gurl ) . ');background-size:cover;background-position:center;' : ''; ?>"></span>
						<span class="ob-gallery-name">Foto #<?php echo (int) $gid; ?></span>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<label class="ob-consent">
		<input type="checkbox" name="fge_image_rights_confirmed" value="1" <?php checked( $rights_on ); ?> required>
		<span>Ich bestätige, dass ich das Recht habe, die hochgeladenen Bilder zu verwenden und auf Firmengolf zu veröffentlichen.</span>
	</label>
	<?php fge_onboarding_error( $errors, 'fge_image_rights_confirmed' );
	fge_onboarding_textarea( 'fge_image_rights_note', 'fge_image_rights_note', 'Hinweis zu Bildrechten (optional)', $v['image_rights_note'] ?? '' );
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
