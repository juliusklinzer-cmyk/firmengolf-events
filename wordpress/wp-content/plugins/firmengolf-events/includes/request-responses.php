<?php
/**
 * Termin-Abstimmung — Antworten je Wunschtermin × Person (Hybrid-Modell).
 *
 * Die Anfrage bleibt der firmengolf_request-CPT (Wunschtermine = _fge_preferred_date_1..3).
 * Nur die relationale Abstimmung lebt hier in EINER Tabelle: pro (Anfrage, Termin-Index,
 * Kontakt) eine Antwort confirmed/declined. "pending" = es existiert keine Zeile.
 *
 * Responder werden LIVE abgeleitet: die `vote`-Kontakte (fge_partner_contacts) des der
 * Anfrage zugewiesenen Partners. Jeder Kontakt hat bereits einen Magic-Link-Token.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_RR_DB_VERSION = '1.0.0';

function fge_rr_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'fge_request_responses';
}

/** Create/upgrade the responses table (version-gated). */
function fge_rr_install(): void {
	if ( get_option( 'fge_rr_db_version' ) === FGE_RR_DB_VERSION ) {
		return;
	}
	global $wpdb;
	$table   = fge_rr_table();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		request_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		date_index TINYINT UNSIGNED NOT NULL DEFAULT 0,
		contact_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		response VARCHAR(20) NOT NULL DEFAULT 'pending',
		alt_date VARCHAR(190) NOT NULL DEFAULT '',
		note TEXT NULL,
		responded_at DATETIME NULL DEFAULT NULL,
		created_at DATETIME NULL DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY rr_uniq (request_id, date_index, contact_id),
		KEY request_id (request_id)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'fge_rr_db_version', FGE_RR_DB_VERSION );
}
add_action( 'init', 'fge_rr_install' );

/** Human-readable request number (FG-26-…). Falls back to FG-<id> if none stored. */
function fge_request_number( int $request_id ): string {
	$ref = (string) get_post_meta( $request_id, '_fge_ref', true );
	return '' !== $ref ? $ref : sprintf( 'FG-%06d', $request_id );
}

/** Wish dates of a request as [index => label] (1..3, non-empty only). */
function fge_rr_wish_dates( int $request_id ): array {
	$out = [];
	for ( $i = 1; $i <= 3; $i++ ) {
		$label = trim( (string) get_post_meta( $request_id, '_fge_preferred_date_' . $i, true ) );
		if ( '' !== $label ) {
			$out[ $i ] = $label;
		}
	}
	return $out;
}

/** The responders for a request: the assigned partner's vote-permission contacts. */
function fge_rr_responders( int $request_id ): array {
	$partner_id = (int) get_post_meta( $request_id, '_fge_assigned_partner_id', true );
	if ( $partner_id <= 0 || ! function_exists( 'fge_contacts_get' ) ) {
		return [];
	}
	return array_values( array_filter( fge_contacts_get( $partner_id ), static function ( $c ) {
		return ( $c['permission'] ?? '' ) === 'vote';
	} ) );
}

/** Upsert one response. Returns true on success. */
function fge_rr_set( int $request_id, int $date_index, int $contact_id, string $response, string $alt = '', string $note = '' ): bool {
	if ( ! in_array( $response, [ 'confirmed', 'declined' ], true ) ) {
		return false;
	}
	if ( $request_id <= 0 || $contact_id <= 0 || $date_index < 1 ) {
		return false;
	}
	global $wpdb;
	$t   = fge_rr_table();
	$now = current_time( 'mysql' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$sql = $wpdb->prepare(
		"INSERT INTO {$t} (request_id, date_index, contact_id, response, alt_date, note, responded_at, created_at)
		 VALUES (%d, %d, %d, %s, %s, %s, %s, %s)
		 ON DUPLICATE KEY UPDATE response = VALUES(response), alt_date = VALUES(alt_date), note = VALUES(note), responded_at = VALUES(responded_at)",
		$request_id, $date_index, $contact_id, $response, sanitize_text_field( $alt ), sanitize_textarea_field( $note ), $now, $now
	);
	return false !== $wpdb->query( $sql );
}

/** All response rows for a request, keyed "dateIndex:contactId". */
function fge_rr_get( int $request_id ): array {
	global $wpdb;
	$t    = fge_rr_table();
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE request_id = %d", $request_id ), ARRAY_A ) ?: [];
	$out  = [];
	foreach ( $rows as $r ) {
		$out[ $r['date_index'] . ':' . $r['contact_id'] ] = $r;
	}
	return $out;
}

/** Has a contact answered every wish date of a request? */
function fge_rr_contact_done( int $request_id, int $contact_id ): bool {
	$dates = fge_rr_wish_dates( $request_id );
	if ( empty( $dates ) ) {
		return false;
	}
	$rows = fge_rr_get( $request_id );
	foreach ( array_keys( $dates ) as $idx ) {
		if ( ! isset( $rows[ $idx . ':' . $contact_id ] ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Derived voting matrix: per date the responders + their response (pending if no row),
 * per-date counts, overall state, and a fully-confirmed date candidate (final_index).
 */
function fge_rr_matrix( int $request_id ): array {
	$dates      = fge_rr_wish_dates( $request_id );
	$responders = fge_rr_responders( $request_id );
	$rows       = fge_rr_get( $request_id );
	$total      = count( $responders );

	$out_dates = [];
	foreach ( $dates as $idx => $label ) {
		$entry = [ 'label' => $label, 'responders' => [], 'confirmed' => 0, 'declined' => 0, 'pending' => 0, 'total' => $total ];
		foreach ( $responders as $c ) {
			$row  = $rows[ $idx . ':' . $c['id'] ] ?? null;
			$resp = $row['response'] ?? 'pending';
			$entry['responders'][ (int) $c['id'] ] = [
				'contact'  => $c,
				'response' => $resp,
				'alt'      => $row['alt_date'] ?? '',
				'note'     => $row['note'] ?? '',
			];
			$entry[ $resp ] = ( $entry[ $resp ] ?? 0 ) + 1;
		}
		$out_dates[ $idx ] = $entry;
	}

	// All responded = every responder answered every wish date.
	$all_responded = ! empty( $responders ) && ! empty( $dates );
	if ( $all_responded ) {
		foreach ( $responders as $c ) {
			if ( ! fge_rr_contact_done( $request_id, (int) $c['id'] ) ) {
				$all_responded = false;
				break;
			}
		}
	}

	// A date everyone confirmed → bookable candidate.
	$final_index = null;
	foreach ( $out_dates as $idx => $e ) {
		if ( $e['total'] > 0 && $e['confirmed'] === $e['total'] ) {
			$final_index = $idx;
			break;
		}
	}

	if ( empty( $responders ) || empty( $dates ) || ! $all_responded ) {
		$overall = 'offen';
	} elseif ( null !== $final_index ) {
		$overall = 'buchbar';
	} else {
		$any_confirmed = false;
		foreach ( $out_dates as $e ) {
			if ( $e['confirmed'] > 0 ) {
				$any_confirmed = true;
				break;
			}
		}
		$overall = $any_confirmed ? 'teilweise' : 'nicht_verfuegbar';
	}

	return [
		'dates'         => $out_dates,
		'responders'    => $responders,
		'all_responded' => $all_responded,
		'overall'       => $overall,
		'final_index'   => $final_index,
	];
}

/** Store / read the confirmed (final) wish-date index for a request. */
function fge_rr_set_final( int $request_id, int $date_index ): void {
	update_post_meta( $request_id, '_fge_final_date_index', $date_index );
}
function fge_rr_final_index( int $request_id ): int {
	return (int) get_post_meta( $request_id, '_fge_final_date_index', true );
}

/** A contact's existing alternative-date / note (denormalised across their rows). */
function fge_rr_contact_alt( array $rows, int $contact_id ): string {
	foreach ( $rows as $r ) {
		if ( (int) $r['contact_id'] === $contact_id && '' !== (string) $r['alt_date'] ) {
			return (string) $r['alt_date'];
		}
	}
	return '';
}
function fge_rr_contact_note( array $rows, int $contact_id ): string {
	foreach ( $rows as $r ) {
		if ( (int) $r['contact_id'] === $contact_id && '' !== (string) ( $r['note'] ?? '' ) ) {
			return (string) $r['note'];
		}
	}
	return '';
}

/** Personal magic-link for a contact to respond to a request's wish dates. */
function fge_termin_contact_link( int $request_id, array $contact ): string {
	return home_url( '/termin/' . rawurlencode( (string) $contact['token'] ) . '/?req=' . $request_id );
}

/**
 * Validate a landing request: contact token + request id. Returns
 * [ 'contact' => row, 'request_id' => int ] when the contact is a vote-responder
 * of the request's assigned partner, else null.
 */
function fge_rr_resolve_landing( string $token, int $request_id ): ?array {
	if ( '' === $token || $request_id <= 0 ) {
		return null;
	}
	$contact = function_exists( 'fge_contact_get_by_token' ) ? fge_contact_get_by_token( $token ) : null;
	if ( ! $contact || ( $contact['permission'] ?? '' ) !== 'vote' ) {
		return null;
	}
	if ( get_post_type( $request_id ) !== 'firmengolf_request' ) {
		return null;
	}
	$partner = (int) get_post_meta( $request_id, '_fge_assigned_partner_id', true );
	if ( (int) $contact['partner_id'] !== $partner ) {
		return null;
	}
	return [ 'contact' => $contact, 'request_id' => $request_id ];
}

// ── PRG handler for the Termin landing form (per-date votes) ──────────────────
add_action( 'init', 'fge_rr_handle_landing_post', 5 );
function fge_rr_handle_landing_post(): void {
	if ( ( $_POST['fge_termin_action'] ?? '' ) !== 'respond_dates' ) {
		return;
	}
	$token = sanitize_text_field( wp_unslash( $_POST['fge_termin_token'] ?? '' ) );
	$req   = absint( $_POST['fge_req'] ?? 0 );
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_termin_nonce'] ?? '' ) ), 'fge_termin_' . $token ) ) {
		wp_die( 'Ungültige Sicherheitsprüfung.', '', [ 'response' => 403 ] );
	}
	$resolved = fge_rr_resolve_landing( $token, $req );
	if ( ! $resolved ) {
		wp_die( 'Link ungültig oder abgelaufen.', '', [ 'response' => 404 ] );
	}
	$contact = $resolved['contact'];
	$alt     = sanitize_text_field( wp_unslash( $_POST['fge_alt_date'] ?? '' ) );
	$note    = sanitize_textarea_field( wp_unslash( $_POST['fge_note'] ?? '' ) );
	$votes   = is_array( $_POST['vote'] ?? null ) ? $_POST['vote'] : [];

	foreach ( array_keys( fge_rr_wish_dates( $req ) ) as $idx ) {
		$v = sanitize_key( $votes[ $idx ] ?? '' );
		if ( in_array( $v, [ 'confirmed', 'declined' ], true ) ) {
			fge_rr_set( $req, $idx, (int) $contact['id'], $v, $alt, $note );
		}
	}

	// Once everyone has fully responded, notify the partner manager (once).
	$matrix = fge_rr_matrix( $req );
	if ( ! empty( $matrix['all_responded'] ) && '1' !== (string) get_post_meta( $req, '_fge_allresponded_notified', true ) ) {
		update_post_meta( $req, '_fge_allresponded_notified', 1 );
		do_action( 'fge_request_all_responded', $req );
	}

	wp_safe_redirect( fge_termin_contact_link( $req, $contact ) . '&done=1' );
	exit;
}
