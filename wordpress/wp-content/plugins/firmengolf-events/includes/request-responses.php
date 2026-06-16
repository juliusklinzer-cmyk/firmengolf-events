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

/**
 * Generate the next sequential request reference: FG-<YY>-<NNN> (per-year counter).
 * e.g. FG-26-001, FG-26-002 … Resets each calendar year.
 */
function fge_generate_request_ref(): string {
	$yy  = (int) current_time( 'y' );
	$opt = 'fge_request_seq_' . $yy;
	$seq = (int) get_option( $opt, 0 ) + 1;
	update_option( $opt, $seq, false );
	return sprintf( 'FG-%02d-%03d', $yy, $seq );
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

/**
 * Die Abstimmenden einer Anfrage. Der Golfplatz selbst (Hauptkontakt) stimmt
 * immer mit ab. Modus des Events: `us` → nur der Platz; `approve` → zusätzlich
 * die ausgewählten Ansprechpartner (oder, ohne Auswahl, alle vote-Kontakte).
 */
function fge_rr_responders( int $request_id ): array {
	$partner_id = (int) get_post_meta( $request_id, '_fge_assigned_partner_id', true );
	if ( $partner_id <= 0 || ! function_exists( 'fge_contacts_get' ) ) {
		return [];
	}
	$event_id = (int) get_post_meta( $request_id, '_fge_assigned_event_id', true );
	$mode     = $event_id > 0 ? ( (string) get_post_meta( $event_id, '_fge_release_mode', true ) ?: 'us' ) : 'us';

	// Hauptkontakt (Platz) sicherstellen, dann alle Kontakte laden.
	$owner_id = function_exists( 'fge_partner_ensure_owner_contact' ) ? fge_partner_ensure_owner_contact( $partner_id ) : 0;
	$by_id    = [];
	foreach ( fge_contacts_get( $partner_id ) as $c ) {
		$by_id[ (int) $c['id'] ] = $c;
	}

	$result = [];
	// Der Platz stimmt immer mit ab (erste Stimme).
	if ( $owner_id > 0 && isset( $by_id[ $owner_id ] ) ) {
		$result[ $owner_id ] = $by_id[ $owner_id ];
	}

	if ( 'approve' === $mode ) {
		$selected = $event_id > 0 ? array_map( 'absint', (array) get_post_meta( $event_id, '_fge_event_responder_ids', true ) ) : [];
		if ( $selected ) {
			foreach ( $selected as $sid ) {
				if ( isset( $by_id[ $sid ] ) ) {
					$result[ $sid ] = $by_id[ $sid ];
				}
			}
		} else {
			// Ohne explizite Auswahl: alle vote-Kontakte (außer dem Hauptkontakt, schon drin).
			foreach ( $by_id as $cid => $c ) {
				if ( $cid !== $owner_id && ( $c['permission'] ?? '' ) === 'vote' ) {
					$result[ $cid ] = $c;
				}
			}
		}
	}

	return array_values( $result );
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

/** Has a contact answered at least one wish date? (= "hat reagiert" für Anzeige/Erinnerung) */
function fge_rr_contact_answered_any( int $request_id, int $contact_id ): bool {
	foreach ( fge_rr_get( $request_id ) as $r ) {
		if ( (int) $r['contact_id'] === $contact_id ) {
			return true;
		}
	}
	return false;
}

/** Has a contact answered every wish date of a request? (= vollständig, für „buchbar") */
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

/**
 * Eignungs-Matching für (Individual-)Anfragen: passende Plätze nach Kapazität,
 * Region und angebotenen Formaten. Schließt klare Nicht-Treffer (Teilnehmer außerhalb
 * der Kapazität) aus, sortiert nach Score. Rückgabe: Liste [id,name,city,cap,score,why,fits].
 */
function fge_match_partners_for_request( int $req, int $limit = 8 ): array {
	$pax    = (int) get_post_meta( $req, '_fge_expected_participants', true );
	$region = (string) get_post_meta( $req, '_fge_desired_region', true );
	if ( '' === $region ) { $region = (string) get_post_meta( $req, '_fge_company_city', true ); }
	$fmt    = (string) get_post_meta( $req, '_fge_event_type', true );
	if ( '' === $fmt ) { $fmt = (string) get_post_meta( $req, '_fge_event_goal', true ); }

	// Wunschtermine der Anfrage (Timestamps) für den Verfügbarkeits-Score.
	$wish_ts = [];
	foreach ( [ 1, 2, 3 ] as $di ) {
		$d = (string) get_post_meta( $req, '_fge_preferred_date_' . $di, true );
		$t = '' !== $d ? strtotime( $d ) : false;
		if ( false !== $t ) {
			$wish_ts[] = $t;
		}
	}

	$partners = get_posts( [ 'post_type' => 'firmengolf_partner', 'post_status' => 'publish', 'numberposts' => -1 ] );
	$out = [];
	foreach ( $partners as $p ) {
		if ( 'pausiert' === (string) get_post_meta( $p->ID, '_fge_partner_status', true ) ) {
			continue;
		}
		$cap = get_post_meta( $p->ID, '_fge_cap', true );
		$cap = is_array( $cap ) ? $cap : [];
		$min = (int) ( $cap['min'] ?? 0 );
		$max = (int) ( $cap['max'] ?? 0 );
		$fits = ( $pax > 0 && $min > 0 && $max > 0 ) ? ( $pax >= $min && $pax <= $max ) : null;
		if ( false === $fits ) {
			continue; // Teilnehmerzahl liegt klar außerhalb → raus.
		}
		$score = 0;
		$why   = [];
		if ( true === $fits ) { $score += 3; $why[] = "Kapazität {$min}–{$max} passt"; }
		$pcity   = (string) get_post_meta( $p->ID, '_fge_city', true );
		$pregion = (string) get_post_meta( $p->ID, '_fge_free_region', true );
		if ( '' !== $region && ( false !== stripos( $pcity, $region ) || false !== stripos( $pregion, $region ) || false !== stripos( $region, $pcity ) ) ) {
			$score += 2; $why[] = 'Region passt';
		}
		$pf = (array) get_post_meta( $p->ID, '_fge_event_formats', true );
		if ( '' !== $fmt && in_array( $fmt, $pf, true ) ) {
			$score += 2; $why[] = 'bietet dieses Format';
		}

		// ── Verfügbarkeit: Wunschtermine vs. Saison, Wochentage und Vorlauf des Platzes ──
		if ( $wish_ts ) {
			$sf = (int) get_post_meta( $p->ID, '_fge_season_from', true );
			$st = (int) get_post_meta( $p->ID, '_fge_season_to', true );
			if ( $sf >= 1 && $st >= 1 ) {
				$in_season = 0;
				foreach ( $wish_ts as $t ) {
					$mo = (int) gmdate( 'n', $t );
					// Saison kann übers Jahresende gehen (z. B. Oktober–März).
					$ok = $sf <= $st ? ( $mo >= $sf && $mo <= $st ) : ( $mo >= $sf || $mo <= $st );
					if ( $ok ) { $in_season++; }
				}
				if ( $in_season === count( $wish_ts ) ) {
					$score += 2; $why[] = 'Saison passt';
				} elseif ( 0 === $in_season ) {
					$score -= 3; $why[] = 'Wunschtermine außerhalb der Saison';
				} else {
					$score += 1; $why[] = 'Saison passt teilweise';
				}
			}

			$pref_days = (array) get_post_meta( $p->ID, '_fge_preferred_event_days', true );
			if ( $pref_days ) {
				$day_keys = [ 1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday' ];
				foreach ( $wish_ts as $t ) {
					if ( in_array( $day_keys[ (int) gmdate( 'N', $t ) ], $pref_days, true ) ) {
						$score += 1; $why[] = 'Wunschtag passt';
						break;
					}
				}
			}

			$lead_days = (int) get_post_meta( $p->ID, '_fge_min_lead_time_days', true );
			if ( $lead_days > 0 ) {
				$lead_ok = false;
				foreach ( $wish_ts as $t ) {
					if ( $t - time() >= $lead_days * DAY_IN_SECONDS ) {
						$lead_ok = true;
						break;
					}
				}
				if ( ! $lead_ok ) {
					$score -= 2; $why[] = "Vorlauf zu kurz (braucht {$lead_days} Tage)";
				}
			}
		}

		$out[] = [ 'id' => $p->ID, 'name' => get_the_title( $p->ID ), 'city' => $pcity, 'cap' => ( $min || $max ) ? "{$min}–{$max}" : '—', 'score' => $score, 'why' => $why, 'fits' => $fits ];
	}
	usort( $out, static function ( $a, $b ) { return $b['score'] <=> $a['score']; } );
	return array_slice( $out, 0, $limit );
}

// Admin-Metabox: passende Plätze (v. a. für individuelle Anfragen ohne festen Platz).
add_action( 'add_meta_boxes', static function () {
	add_meta_box( 'fge_mb_match', 'Passende Plätze (Eignung)', 'fge_render_mb_match', 'firmengolf_request', 'side', 'default' );
} );

function fge_render_mb_match( WP_Post $post ): void {
	$pax = (int) get_post_meta( $post->ID, '_fge_expected_participants', true );
	$matches = fge_match_partners_for_request( $post->ID );
	echo '<p style="margin:0 0 8px;color:#646970;font-size:12px;">Teilnehmer: ' . ( $pax ?: '—' ) . ' · Vorschläge nach Kapazität, Region &amp; Format.</p>';
	if ( empty( $matches ) ) {
		echo '<p style="margin:0;">Keine passenden Plätze gefunden.</p>';
		return;
	}
	echo '<ul style="margin:0;list-style:none;padding:0;">';
	foreach ( $matches as $mm ) {
		$edit = get_edit_post_link( $mm['id'] );
		echo '<li style="padding:8px 0;border-bottom:1px solid #f0f0f1;">';
		echo '<a href="' . esc_url( $edit ) . '" style="font-weight:600;">' . esc_html( $mm['name'] ) . '</a>';
		if ( $mm['city'] ) { echo ' <span style="color:#646970;">· ' . esc_html( $mm['city'] ) . '</span>'; }
		echo '<br><span style="font-size:12px;color:#646970;">Kap. ' . esc_html( $mm['cap'] ) . ( $mm['why'] ? ' · ' . esc_html( implode( ', ', $mm['why'] ) ) : '' ) . '</span>';
		echo '</li>';
	}
	echo '</ul>';
}

/** Parse a German free-text date ("Do, 18. Juni 2026") → "Ymd" or null if not parseable. */
function fge_parse_german_date( string $s ): ?string {
	$months = [ 'januar' => 1, 'februar' => 2, 'märz' => 3, 'maerz' => 3, 'april' => 4, 'mai' => 5, 'juni' => 6, 'juli' => 7, 'august' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'dezember' => 12 ];
	if ( preg_match( '/(\d{1,2})\.\s*([A-Za-zäöüÄÖÜ]+)\.?\s*(\d{4})/u', $s, $m ) ) {
		$mon = $months[ mb_strtolower( $m[2] ) ] ?? 0;
		if ( $mon ) {
			return sprintf( '%04d%02d%02d', (int) $m[3], $mon, (int) $m[1] );
		}
	}
	return null;
}

/** Build an .ics (single all-day VEVENT) for a request's confirmed date, or null. */
function fge_request_ics( int $request_id ): ?string {
	$idx = fge_rr_final_index( $request_id );
	if ( $idx <= 0 ) {
		return null;
	}
	$ymd = fge_parse_german_date( (string) get_post_meta( $request_id, '_fge_preferred_date_' . $idx, true ) );
	if ( ! $ymd ) {
		return null;
	}
	$next    = gmdate( 'Ymd', strtotime( $ymd . ' +1 day' ) );
	$company = (string) get_post_meta( $request_id, '_fge_company_name', true );
	$eid     = (int) get_post_meta( $request_id, '_fge_assigned_event_id', true );
	$etype   = $eid ? get_the_title( $eid ) : (string) get_post_meta( $request_id, '_fge_event_type', true );
	$esc     = static function ( string $t ): string {
		return str_replace( [ '\\', ';', ',', "\n" ], [ '\\\\', '\\;', '\\,', '\\n' ], $t );
	};
	$summary = $esc( 'Firmenevent: ' . $company . ( $etype ? ' (' . $etype . ')' : '' ) );
	$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$lines   = [
		'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Firmengolf//Termin//DE', 'CALSCALE:GREGORIAN',
		'BEGIN:VEVENT',
		'UID:fge-' . $request_id . '@' . $host,
		'DTSTART;VALUE=DATE:' . $ymd,
		'DTEND;VALUE=DATE:' . $next,
		'SUMMARY:' . $summary,
		'DESCRIPTION:' . $esc( 'Anfrage ' . fge_request_number( $request_id ) ),
		'END:VEVENT', 'END:VCALENDAR',
	];
	return implode( "\r\n", $lines ) . "\r\n";
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
