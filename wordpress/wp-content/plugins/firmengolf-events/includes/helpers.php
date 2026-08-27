<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon (SVG) für eine inkludierte Event-Leistung: mappt das Label per Stichwort
 * auf die Onboarding-Icons; unbekannte Leistungen bekommen ein Häkchen.
 */
function fge_include_icon( string $label ): string {
	$l   = mb_strtolower( $label );
	$map = [
		'meeting'       => [ 'meetingraum', 'seminar', 'konferenz', 'workshop', 'raum' ],
		'restaurant'    => [ 'lunch', 'mittag', 'dinner', 'abendessen', 'menü', 'buffet', 'verpflegung', 'catering' ],
		'coffee'        => [ 'kaffee', 'kuchen', 'frühstück', 'pause' ],
		'drinks'        => [ 'getränk', 'begrüßung', 'sekt', 'bar' ],
		'grill'         => [ 'bbq', 'grill' ],
		'coach'         => [ 'coaching', 'pga', 'golflehrer', 'schnupperkurs', 'platzreife', 'kurs', 'training' ],
		'clubs'         => [ 'leihschläger', 'schläger' ],
		'balls'         => [ 'bälle', 'ball' ],
		'driving-range' => [ 'range', 'übungsanlage', 'greenfee' ],
		'course-18'     => [ 'loch', 'runde', 'turnier' ],
		'putting'       => [ 'putting' ],
		'branding'      => [ 'urkunde', 'foto', 'branding' ],
	];
	if ( function_exists( 'fge_onboarding_card_icon' ) ) {
		foreach ( $map as $icon => $needles ) {
			foreach ( $needles as $needle ) {
				if ( false !== mb_strpos( $l, $needle ) ) {
					$svg = fge_onboarding_card_icon( $icon );
					if ( '' !== $svg ) {
						return $svg;
					}
				}
			}
		}
	}
	// Fallback: „Zusatzleistung"-Icon (Plus im Kreis) für eigene Leistungen.
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>';
}

/**
 * Partner-Vorgangsnummer (FG-P-26-001): jährliche Sequenz wie bei den Anfragen,
 * wird beim ersten Zugriff vergeben und am Partner gespeichert.
 */
function fge_partner_number( int $partner_id ): string {
	$ref = (string) get_post_meta( $partner_id, '_fge_partner_ref', true );
	if ( '' !== $ref ) {
		return $ref;
	}
	$yy  = (int) current_time( 'y' );
	$opt = 'fge_partner_seq_' . $yy;
	// Atomar hochzählen — doppelte Vorgangsnummern bei Gleichzeitigkeit ausgeschlossen.
	$seq = function_exists( 'fge_atomic_sequence' ) ? fge_atomic_sequence( $opt ) : (int) get_option( $opt, 0 ) + 1;
	if ( ! function_exists( 'fge_atomic_sequence' ) ) {
		update_option( $opt, $seq, false );
	}
	$ref = sprintf( 'FG-P-%02d-%03d', $yy, $seq );
	update_post_meta( $partner_id, '_fge_partner_ref', $ref );
	return $ref;
}

/**
 * Partner-Typ (course/coach/indoor, Katalog fge_catalog_partner_types()).
 * Bestandspartner ohne Meta und unbekannte Werte gelten als 'course', damit
 * Live-Daten auch vor der Migration korrekt einsortiert werden.
 */
function fge_partner_type( int $partner_id ): string {
	$type = (string) get_post_meta( $partner_id, '_fge_partner_type', true );
	return isset( fge_catalog_partner_types()[ $type ] ) ? $type : 'course';
}

/**
 * IBAN normalisieren: Leerzeichen raus, Großbuchstaben. Speicherformat.
 */
function fge_normalize_iban( string $iban ): string {
	return strtoupper( (string) preg_replace( '/\s+/', '', $iban ) );
}

/**
 * IBAN-Formatprüfung (ISO 13616): Grundmuster plus Prüfsumme mod 97.
 * Genutzt vom billing-Slide im Onboarding; toleriert Leerzeichen in der Eingabe.
 */
function fge_is_valid_iban( string $iban ): bool {
	$iban = fge_normalize_iban( $iban );
	if ( ! preg_match( '/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban ) ) {
		return false;
	}
	$numeric = '';
	foreach ( str_split( substr( $iban, 4 ) . substr( $iban, 0, 4 ) ) as $ch ) {
		$numeric .= ctype_alpha( $ch ) ? (string) ( ord( $ch ) - 55 ) : $ch;
	}
	// Die Zahl sprengt jeden int, deshalb mod 97 stückweise.
	$rem = 0;
	foreach ( str_split( $numeric, 7 ) as $chunk ) {
		$rem = (int) ( ( $rem . $chunk ) % 97 );
	}
	return 1 === $rem;
}

/**
 * Deutschen Geldbetrag robust parsen: „2.400" → 2400, „1.200,50" → 1200.50,
 * „67,5" → 67.5, „12.34" → 12.34. Punkt vor genau 3 Ziffern = Tausendertrenner.
 */
function fge_parse_de_amount( $raw ): float {
	$s = (string) preg_replace( '/[^\d,.]/', '', (string) $raw );
	if ( '' === $s ) {
		return 0.0;
	}
	if ( str_contains( $s, ',' ) ) {
		$s   = str_replace( '.', '', $s ); // Punkte sind Tausendertrenner
		$pos = strrpos( $s, ',' );
		$s   = str_replace( ',', '', substr( $s, 0, $pos ) ) . '.' . substr( $s, $pos + 1 );
	} elseif ( substr_count( $s, '.' ) > 1 || preg_match( '/\.\d{3}$/', $s ) ) {
		$s = str_replace( '.', '', $s ); // 1.200.000 / 2.400
	}
	return (float) $s;
}

/** Monatsnamen 1–12 (für Saison von/bis). */

function fge_month_names(): array {
	return [
		1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
		5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
		9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
	];
}

/** Lesbares Saison-Label aus von/bis-Monat (1–12). Januar–Dezember = „Ganzjährig". */
function fge_season_range_label( int $from, int $to ): string {
	if ( 1 === $from && 12 === $to ) {
		return 'Ganzjährig';
	}
	$m = fge_month_names();
	$fr = (string) ( $m[ $from ] ?? '' );
	$to_n = (string) ( $m[ $to ] ?? '' );
	return ( $fr !== '' && $to_n !== '' ) ? $fr . ' bis ' . $to_n : trim( $fr . $to_n );
}

/**
 * True, wenn der aktuelle Monat außerhalb der Saison des Partners liegt.
 * Dynamisch abgeleitet (kein gespeicherter Status, kein Cron) — endet automatisch
 * mit Saisonbeginn. Ohne gepflegte Saison (von/bis) nie offseason.
 */
function fge_partner_is_offseason( int $partner_id ): bool {
	$sf = (int) get_post_meta( $partner_id, '_fge_season_from', true );
	$st = (int) get_post_meta( $partner_id, '_fge_season_to', true );
	if ( $sf < 1 || $st < 1 ) {
		return false;
	}
	$mo = (int) current_time( 'n' );
	// Saison kann übers Jahresende gehen (z. B. Oktober–März).
	$in = $sf <= $st ? ( $mo >= $sf && $mo <= $st ) : ( $mo >= $sf || $mo <= $st );
	return ! $in;
}

/** „Saison startet im April wieder" — leer, wenn der Partner nicht offseason ist. */
function fge_partner_offseason_note( int $partner_id ): string {
	if ( ! fge_partner_is_offseason( $partner_id ) ) {
		return '';
	}
	$sf = (int) get_post_meta( $partner_id, '_fge_season_from', true );
	$m  = fge_month_names();
	return isset( $m[ $sf ] ) ? 'Saison startet im ' . $m[ $sf ] . ' wieder' : 'Aktuell außerhalb der Saison';
}

/** Saison-Anzeige: mappt Legacy-Keys (year_round …) auf Labels, reicht Klartext durch. */
function fge_season_label( string $value ): string {
	$legacy = [
		'year_round'       => 'Ganzjährig',
		'march_to_october' => 'März bis Oktober',
		'april_to_october' => 'April bis Oktober',
		'on_request'       => 'Auf Anfrage',
	];
	return $legacy[ $value ] ?? $value;
}

/**
 * Calculates the net sale price based on purchase prices and the Firmengolf markup.
 *
 * @param array $meta Associative array of field values (keys without _fge_ prefix).
 * @return float Rounded net sale price.
 */
function fge_calculate_sale_price_net( array $meta ): float {
	$mode   = $meta['pricing_mode'] ?? 'package';
	$markup = isset( $meta['firmengolf_markup_percent'] ) && $meta['firmengolf_markup_percent'] !== ''
		? (float) $meta['firmengolf_markup_percent']
		: 20.0;

	if ( $mode === 'package' ) {
		$basis = (float) ( $meta['purchase_price_package_net'] ?? 0 );
	} else {
		$individual_keys = [
			'purchase_price_meeting_room_hour_net',
			'purchase_price_range_net',
			'purchase_price_trainer_hour_net',
			'purchase_price_breakfast_net',
			'purchase_price_lunch_net',
			'purchase_price_dinner_net',
			'purchase_price_shuttle_net',
			'purchase_price_other_net',
		];
		$basis = 0.0;
		foreach ( $individual_keys as $key ) {
			$basis += (float) ( $meta[ $key ] ?? 0 );
		}
	}

	return round( $basis * ( 1 + $markup / 100 ), 2 );
}

/**
 * Returns an array of post options for a given post type, keyed by post ID.
 *
 * @param string $post_type The post type to query.
 * @return array<int, string> [ post_ID => 'Post Titel (#post_ID)' ]
 */
function fge_get_posts_select_options( string $post_type ): array {
	$posts = get_posts( [
		'post_type'   => $post_type,
		'post_status' => [ 'publish', 'draft', 'pending' ],
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
	] );

	$options = [];
	foreach ( $posts as $p ) {
		$options[ $p->ID ] = $p->post_title . ' (#' . $p->ID . ')';
	}
	return $options;
}
