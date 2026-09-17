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
 * Hat der Partner Indoor-Golf in der Ausstattung? (indoor, trackman oder
 * toptracer in _fge_infra). Steuert den Indoor-Detailblock im Onboarding
 * und den Indoor-Reiter im Portal (docs/onboarding-golflehrer-indoor.md, Abschnitt 3).
 */
function fge_partner_has_indoor( int $partner_id ): bool {
	// Indoor-Partner (Formular B) haben ihren eigenen Wizard und schreiben KEIN
	// _fge_infra=indoor; sie werden über den Partnertyp erkannt (Audit 28.08.,
	// sonst fehlt ihnen der Indoor-Reiter im Portal komplett). Golfplätze mit
	// Indoor in der Ausstattung weiterhin über _fge_infra.
	if ( 'indoor' === fge_partner_type( $partner_id ) ) {
		return true;
	}
	$infra = get_post_meta( $partner_id, '_fge_infra', true );
	return is_array( $infra ) && [] !== array_intersect( [ 'indoor', 'trackman', 'toptracer' ], $infra );
}

/**
 * Umsatzsteuer-Status des Partners (Julius, 02.09.): 'regular' (Regelbesteuerung,
 * 19 %) oder 'small' (Kleinunternehmer §19 UStG, keine USt). Default 'regular'.
 * Migration: altes Kleinunternehmer-Kennzeichen aus der (entfernten) Billing-Slide.
 */
function fge_partner_tax_mode( int $partner_id ): string {
	$mode = (string) get_post_meta( $partner_id, '_fge_tax_mode', true );
	if ( 'regular' === $mode || 'small' === $mode ) {
		return $mode;
	}
	// Migration aus dem alten Billing-Feld, falls dort mal gesetzt.
	return '1' === (string) get_post_meta( $partner_id, '_fge_billing_small_business', true ) ? 'small' : 'regular';
}

/** Kleinunternehmer nach §19 UStG (keine Umsatzsteuer)? */
function fge_partner_is_small_business( int $partner_id ): bool {
	return 'small' === fge_partner_tax_mode( $partner_id );
}

/**
 * Divisor, um vom Brutto-Endkundenpreis des Partners auf sein Netto zu kommen:
 * 1.19 bei Regelbesteuerung, 1.0 beim Kleinunternehmer. Golfpläte/Indoor sind
 * praktisch immer regelbesteuert, Golflehrer können Kleinunternehmer sein.
 */
function fge_partner_tax_divisor( int $partner_id ): float {
	$vat = defined( 'FGE_VAT_PERCENT' ) ? FGE_VAT_PERCENT : 19;
	return fge_partner_is_small_business( $partner_id ) ? 1.0 : ( 1 + $vat / 100 );
}

/**
 * Brutto-Endkundenpreis des Partners → sein Netto (Speicherwert). Der interne
 * Speicherwert (`_fge_price_amount`, line-item cost) bleibt Netto, damit der
 * gesamte Downstream-Pfad (event-pricing, Karten, Anzeige) unverändert bleibt.
 */
function fge_gross_to_net( float $gross, int $partner_id ): float {
	$d = fge_partner_tax_divisor( $partner_id );
	return $d > 0 ? round( $gross / $d, 2 ) : $gross;
}

/** Netto (Speicherwert) → Brutto für die Anzeige im Angebotseditor. */
function fge_net_to_gross( float $net, int $partner_id ): float {
	return round( $net * fge_partner_tax_divisor( $partner_id ), 2 );
}

// ── Golflehrer ↔ Golfplatz-Verknüpfung (Julius, 02.09.) ──────────────────────
// Existiert der Unterrichts-Golfplatz schon als Partner, wird verknüpft statt
// doppelt angelegt (`_fge_coach_venue_partner_id`); sonst bleibt der Coach der
// Ersteller der Grundlagen und kann das Clubmanagement per Mail einladen.
// Erster Baustein der Standort-Verknüpfungs-Vision (jeder Akteur ein Account,
// am selben Standort auto-verlinkt).

/** Verknüpfter Golfplatz-Partner eines Golflehrers, validiert (0 = keiner). */
function fge_coach_linked_venue_id( int $coach_id ): int {
	$vid = (int) get_post_meta( $coach_id, '_fge_coach_venue_partner_id', true );
	if ( $vid <= 0 || 'firmengolf_partner' !== get_post_type( $vid ) || 'course' !== fge_partner_type( $vid ) ) {
		return 0;
	}
	return $vid;
}

/**
 * Anzeigename + Ort des Unterrichts-Golfplatzes: bei Verknüpfung live vom
 * Platz-Partner (bleibt automatisch aktuell), sonst die eigenen Coach-Metas.
 */
function fge_coach_venue_display( int $coach_id ): array {
	$vid = fge_coach_linked_venue_id( $coach_id );
	if ( $vid > 0 ) {
		$name = (string) get_post_meta( $vid, '_fge_public_golfclub_name', true ) ?: get_the_title( $vid );
		return [ 'partner_id' => $vid, 'name' => $name, 'city' => (string) get_post_meta( $vid, '_fge_city', true ) ];
	}
	return [ 'partner_id' => 0, 'name' => (string) get_post_meta( $coach_id, '_fge_coach_venue_name', true ), 'city' => (string) get_post_meta( $coach_id, '_fge_city', true ) ];
}

/**
 * Snapshot bei Verknüpfung: Adresse, Geo und Anlagen-Name vom Golfplatz auf den
 * Coach kopieren. So stimmen alle bestehenden Verbraucher (_fge_city für die
 * Stadt-Zuordnung, Karten-Pin, Umkreissuche) ohne Umbau, der Pro gibt nichts an.
 */
function fge_coach_apply_venue_link( int $coach_id ): void {
	$vid = fge_coach_linked_venue_id( $coach_id );
	if ( $vid <= 0 ) {
		return;
	}
	foreach ( [ 'street', 'house_number', 'postal_code', 'city', 'federal_state', 'latitude', 'longitude', 'google_place_id' ] as $k ) {
		$val = get_post_meta( $vid, '_fge_' . $k, true );
		if ( '' !== (string) $val ) {
			update_post_meta( $coach_id, '_fge_' . $k, $val );
		}
	}
	$vname = (string) get_post_meta( $vid, '_fge_public_golfclub_name', true ) ?: get_the_title( $vid );
	if ( '' !== $vname ) {
		update_post_meta( $coach_id, '_fge_coach_venue_name', $vname );
	}
}

/**
 * Bekannte Golfplatz-Partner als kleines Datenpaket für den Verknüpfungs-
 * Vorschlag im Coach-Standort-Formular (JS-Matching auf dem Namensfeld).
 */
function fge_course_partner_choices(): array {
	$ids = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => 300,
		'fields'      => 'ids',
	] );
	$out = [];
	foreach ( $ids as $pid ) {
		if ( 'course' !== fge_partner_type( (int) $pid ) ) {
			continue;
		}
		$name = (string) get_post_meta( $pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $pid );
		$city = (string) get_post_meta( $pid, '_fge_city', true );
		// Ohne Namen oder Ort ist es ein leerer Onboarding-Entwurf, kein
		// vorschlagbarer Platz (sonst matchen Karteileichen wie „Neuer
		// Golfplatz Partner (Onboarding)").
		if ( '' === trim( $name ) || '' === trim( $city ) ) {
			continue;
		}
		$out[] = [ 'id' => (int) $pid, 'name' => $name, 'city' => $city ];
	}
	return $out;
}

/**
 * Golfplatz-Partner als Entwurf aus den Coach-Grundlagen anlegen (Name +
 * Adresse/Pin vom Coach) und den Coach damit verknüpfen. Basis für die
 * Clubmanagement-Einladung, wenn der Platz noch kein Partner ist.
 */
function fge_coach_create_venue_draft( int $coach_id ): int {
	$name = (string) get_post_meta( $coach_id, '_fge_coach_venue_name', true );
	if ( '' === trim( $name ) ) {
		return 0;
	}
	$vid = wp_insert_post( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => 'draft',
		'post_title'  => $name,
	] );
	if ( ! $vid || is_wp_error( $vid ) ) {
		return 0;
	}
	update_post_meta( $vid, '_fge_partner_type', 'course' );
	update_post_meta( $vid, '_fge_public_golfclub_name', $name );
	foreach ( [ 'street', 'house_number', 'postal_code', 'city', 'federal_state', 'latitude', 'longitude', 'google_place_id' ] as $k ) {
		$val = get_post_meta( $coach_id, '_fge_' . $k, true );
		if ( '' !== (string) $val ) {
			update_post_meta( $vid, '_fge_' . $k, $val );
		}
	}
	update_post_meta( $vid, '_fge_created_via_coach', $coach_id );
	update_post_meta( $coach_id, '_fge_coach_venue_partner_id', (int) $vid );
	return (int) $vid;
}

/** Golfplatz-/Anlagen-Namen fürs Matching normalisieren (Zwilling der JS-Norm). */
function fge_venue_name_norm( string $name ): string {
	$n = mb_strtolower( $name );
	$n = (string) preg_replace( '/golfclub|golf-club|golf club|golfplatz|golfanlage|golfresort|golf resort|land- und golfclub|land-und golfclub|g\.?c\.?|e\.?\s?v\.?/u', ' ', $n );
	$n = (string) preg_replace( '/[^a-zäöüß0-9]+/u', ' ', $n );
	return trim( $n );
}

/** Golflehrer, die mit diesem Golfplatz-Partner verknüpft sind (Heimatplatz). */
function fge_course_linked_coach_ids( int $course_id ): array {
	$ids = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => 50,
		'fields'      => 'ids',
		'meta_query'  => [ [ 'key' => '_fge_coach_venue_partner_id', 'value' => $course_id, 'type' => 'NUMERIC' ] ],
	] );
	return array_values( array_filter( array_map( 'intval', $ids ), static fn( $pid ) => 'coach' === fge_partner_type( $pid ) ) );
}

/**
 * Noch NICHT verknüpfte Golflehrer, deren eingetragener Heimatplatz-Name zu
 * diesem Golfplatz passt („wenn es die schon gibt, linken sie sich").
 */
function fge_course_coach_suggestions( int $course_id ): array {
	$cnorm = fge_venue_name_norm( (string) get_post_meta( $course_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $course_id ) );
	if ( mb_strlen( $cnorm ) < 4 ) {
		return [];
	}
	$ids = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => 300,
		'fields'      => 'ids',
	] );
	$out = [];
	foreach ( $ids as $pid ) {
		$pid = (int) $pid;
		if ( 'coach' !== fge_partner_type( $pid ) || fge_coach_linked_venue_id( $pid ) > 0 ) {
			continue;
		}
		$vnorm = fge_venue_name_norm( (string) get_post_meta( $pid, '_fge_coach_venue_name', true ) );
		if ( mb_strlen( $vnorm ) < 4 || ( false === mb_strpos( $vnorm, $cnorm ) && false === mb_strpos( $cnorm, $vnorm ) ) ) {
			continue;
		}
		$cname = trim( (string) get_post_meta( $pid, '_fge_coach_first', true ) . ' ' . (string) get_post_meta( $pid, '_fge_coach_last', true ) ) ?: get_the_title( $pid );
		$out[] = [ 'id' => $pid, 'name' => $cname, 'venue' => (string) get_post_meta( $pid, '_fge_coach_venue_name', true ) ];
	}
	return $out;
}

/**
 * Golflehrer-Entwurf vom Club aus anlegen: Coach-Partner als Draft, verknüpft
 * mit dem Platz, Adresse/Ort per Snapshot vom Platz. Rückgabe = Coach-ID.
 */
function fge_course_create_coach_draft( int $course_id, string $first, string $last ): int {
	$full = trim( $first . ' ' . $last );
	if ( '' === $full ) {
		return 0;
	}
	$cid = wp_insert_post( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => 'draft',
		'post_title'  => $full,
	] );
	if ( ! $cid || is_wp_error( $cid ) ) {
		return 0;
	}
	update_post_meta( $cid, '_fge_partner_type', 'coach' );
	update_post_meta( $cid, '_fge_coach_first', $first );
	update_post_meta( $cid, '_fge_coach_last', $last );
	update_post_meta( $cid, '_fge_coach_venue_partner_id', $course_id );
	update_post_meta( $cid, '_fge_created_via_course', $course_id );
	fge_coach_apply_venue_link( (int) $cid );
	return (int) $cid;
}

// ── Coach-Locations v2 (Julius, 02.09.) ──────────────────────────────────────
// Strukturierte Liste WEITERER Unterrichts-Locations neben dem Heimatplatz:
// je Zeile Name, Bild, optionale Verknüpfung mit einem Golfplatz-Partner
// („Golfplatz 2", „Simulator München", „Indoor …"). Quelle der Wahrheit ist
// `_fge_coach_locations`; `_fge_coach_more_venues` (alte Namens-Strings) bleibt
// als abgeleiteter Spiegel für Wizard und Altanzeigen erhalten.

/** Zusätzliche Locations eines Golflehrers, validiert; migriert Alt-Strings. */
function fge_coach_locations( int $coach_id ): array {
	$raw = get_post_meta( $coach_id, '_fge_coach_locations', true );
	if ( ! is_array( $raw ) || ! $raw ) {
		// Migration on read: alte „Weitere Golfplätze"-Strings als Zeilen ohne Bild.
		$raw = array_map(
			static fn( $n ) => [ 'name' => (string) $n, 'image_id' => 0, 'partner_id' => 0 ],
			array_filter( array_map( 'strval', (array) get_post_meta( $coach_id, '_fge_coach_more_venues', true ) ) )
		);
	}
	$out = [];
	foreach ( $raw as $row ) {
		$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
		if ( '' === trim( $name ) ) {
			continue;
		}
		$img = absint( $row['image_id'] ?? 0 );
		$vid = absint( $row['partner_id'] ?? 0 );
		if ( $vid > 0 && ( 'firmengolf_partner' !== get_post_type( $vid ) || 'course' !== fge_partner_type( $vid ) ) ) {
			$vid = 0;
		}
		$out[] = [ 'name' => $name, 'image_id' => $img, 'partner_id' => $vid ];
	}
	return $out;
}

/** Locations speichern + Namens-Spiegel (`_fge_coach_more_venues`) nachziehen. */
function fge_coach_save_locations( int $coach_id, array $rows ): void {
	$clean = [];
	foreach ( $rows as $row ) {
		$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
		if ( '' === trim( $name ) ) {
			continue;
		}
		$vid = absint( $row['partner_id'] ?? 0 );
		if ( $vid > 0 && ( 'firmengolf_partner' !== get_post_type( $vid ) || 'course' !== fge_partner_type( $vid ) ) ) {
			$vid = 0;
		}
		$clean[] = [ 'name' => $name, 'image_id' => absint( $row['image_id'] ?? 0 ), 'partner_id' => $vid ];
	}
	update_post_meta( $coach_id, '_fge_coach_locations', $clean );
	update_post_meta( $coach_id, '_fge_coach_more_venues', array_column( $clean, 'name' ) );
}

/**
 * Sync aus dem Wizard (der nur Namen kennt): bestehende Zeilen samt Bild und
 * Verknüpfung behalten, solange der Name bleibt; neue Namen ergänzen, entfernte
 * Namen streichen. So zerstört der schlanke Wizard keine Portal-Pflege.
 */
function fge_coach_sync_locations_from_names( int $coach_id, array $names ): void {
	$names    = array_values( array_filter( array_map( static fn( $n ) => sanitize_text_field( (string) $n ), $names ), static fn( $n ) => '' !== trim( $n ) ) );
	$existing = fge_coach_locations( $coach_id );
	$by_name  = [];
	foreach ( $existing as $row ) {
		$by_name[ mb_strtolower( trim( $row['name'] ) ) ] = $row;
	}
	$rows = [];
	foreach ( $names as $n ) {
		$rows[] = $by_name[ mb_strtolower( trim( $n ) ) ] ?? [ 'name' => $n, 'image_id' => 0, 'partner_id' => 0 ];
	}
	fge_coach_save_locations( $coach_id, $rows );
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

/**
 * Admin-Aktions-Button für Metaboxen im Beitrags-Editor (Julius, 17.09.2026).
 *
 * Hintergrund: Metaboxen liegen im WordPress-Formular #post. Ein eigenes <form>
 * darin wird vom Browser verworfen, die versteckten Felder (action, request_id,
 * Nonce) landen im Hauptformular und überschreiben dessen action=editpost. Folge:
 * „Aktualisieren" leitet auf die Beitragsliste um und speichert nichts, die
 * Aktions-Buttons selbst greifen ebenfalls nicht. Dieser Button baut beim Klick
 * per JS ein eigenes Formular außerhalb von #post und schickt es an admin-post.php.
 *
 * @param string $action  admin_post_-Aktion.
 * @param array  $fields  Versteckte Felder (inkl. request_id); Nonce wird ergänzt.
 * @param string $label   Button-Text.
 * @param array  $opts    class (Button-Klassen), confirm (Sicherheitsfrage),
 *                        radio (Name einer Radio-Gruppe, deren Wert mitgeschickt wird).
 */
function fge_admin_post_button( string $action, array $fields, string $label, array $opts = [] ): void {
	static $script_done = false;
	$fields['action']   = $action;
	$fields['_wpnonce'] = wp_create_nonce( (string) ( $opts['nonce_action'] ?? $action ) );
	echo '<button type="button" class="' . esc_attr( (string) ( $opts['class'] ?? 'button' ) ) . '"'
		. ' data-fge-post-action="' . esc_attr( wp_json_encode( $fields ) ) . '"'
		. ( ! empty( $opts['radio'] ) ? ' data-fge-radio="' . esc_attr( (string) $opts['radio'] ) . '"' : '' )
		. ( ! empty( $opts['confirm'] ) ? ' data-fge-confirm="' . esc_attr( (string) $opts['confirm'] ) . '"' : '' )
		. '>' . esc_html( $label ) . '</button>';
	if ( $script_done ) {
		return;
	}
	$script_done = true;
	?>
	<script>
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('[data-fge-post-action]') : null;
		if (!b) { return; }
		e.preventDefault();
		if (b.dataset.fgeConfirm && !window.confirm(b.dataset.fgeConfirm)) { return; }
		var fields = {};
		try { fields = JSON.parse(b.dataset.fgePostAction || '{}'); } catch (err) { return; }
		if (b.dataset.fgeRadio) {
			var r = document.querySelector('input[name="' + b.dataset.fgeRadio + '"]:checked');
			if (r) { fields[b.dataset.fgeRadio] = r.value; }
		}
		var f = document.createElement('form');
		f.method = 'post';
		f.action = <?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>;
		Object.keys(fields).forEach(function (k) {
			var i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = fields[k]; f.appendChild(i);
		});
		document.body.appendChild(f);
		b.disabled = true;
		f.submit();
	});
	</script>
	<?php
}
