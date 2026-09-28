<?php
/**
 * Platz-Pipeline: mehrere Golfplätze je Anfrage anfragen und verfolgen.
 *
 * Das Datenmodell kannte bis 1.9.267 genau einen Platz je Anfrage
 * (_fge_assigned_partner_id). „Ich habe drei Plätze angefragt, zwei haben mit
 * Grund abgesagt, einer nennt 49 Euro" ließ sich nirgends festhalten. Genau das
 * ist aber der Alltag bei Platzhalter-Events.
 *
 * Zwei Tabellen:
 *   fge_request_venues       je Anfrage ein angefragter Platz
 *   fge_request_venue_items  je Platz die einzelnen Positionen (Abendessen,
 *                            Getränkepauschale …) mit eigenem Preis
 *
 * Der gewählte Platz setzt weiterhin _fge_assigned_partner_id. Die gesamte
 * bestehende Mail- und Portalmechanik bleibt damit unverändert gültig; die
 * Pipeline ergänzt sie, sie ersetzt sie nicht.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_VENUES_DB_VERSION = '1.4.0';

function fge_venues_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'fge_request_venues';
}

function fge_venue_items_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'fge_request_venue_items';
}

function fge_venue_dates_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'fge_request_venue_dates';
}

function fge_venues_install(): void {
	if ( get_option( 'fge_venues_db_version' ) === FGE_VENUES_DB_VERSION ) {
		return;
	}
	global $wpdb;
	$charset = $wpdb->get_charset_collate();
	$v       = fge_venues_table();
	$i       = fge_venue_items_table();

	$sql = "CREATE TABLE {$v} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		request_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		partner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		contact_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		status VARCHAR(20) NOT NULL DEFAULT 'idee',
		channel VARCHAR(20) NOT NULL DEFAULT '',
		asked_at DATETIME NULL DEFAULT NULL,
		replied_at DATETIME NULL DEFAULT NULL,
		reason TEXT NULL,
		price DECIMAL(10,2) NOT NULL DEFAULT 0,
		price_basis VARCHAR(10) NOT NULL DEFAULT 'person',
		price_gross TINYINT(1) NOT NULL DEFAULT 1,
		note TEXT NULL,
		created_at DATETIME NULL DEFAULT NULL,
		catalog_token VARCHAR(40) NOT NULL DEFAULT '',
		catalog_answer VARCHAR(10) NOT NULL DEFAULT '',
		catalog_answered_at DATETIME NULL DEFAULT NULL,
		catalog_event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		catalog_note TEXT NULL,
		markup_percent DECIMAL(5,2) NOT NULL DEFAULT 20.00,
		sale_price DECIMAL(10,2) NOT NULL DEFAULT 0,
		in_offer TINYINT(1) NOT NULL DEFAULT 0,
		option_text TEXT NULL,
		detail_token VARCHAR(40) NOT NULL DEFAULT '',
		details_at DATETIME NULL DEFAULT NULL,
		summary_at DATETIME NULL DEFAULT NULL,
		reservation_at DATETIME NULL DEFAULT NULL,
		reserved_until DATE NULL DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY req_partner (request_id, partner_id),
		KEY request_id (request_id),
		KEY partner_id (partner_id)
	) {$charset};";

	$sql2 = "CREATE TABLE {$i} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		venue_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		wish_key VARCHAR(60) NOT NULL DEFAULT '',
		label VARCHAR(190) NOT NULL DEFAULT '',
		available TINYINT(1) NOT NULL DEFAULT 1,
		price DECIMAL(10,2) NOT NULL DEFAULT 0,
		price_basis VARCHAR(10) NOT NULL DEFAULT 'person',
		price_gross TINYINT(1) NOT NULL DEFAULT 1,
		note TEXT NULL,
		sale_price DECIMAL(10,2) NOT NULL DEFAULT 0,
		organizer VARCHAR(10) NOT NULL DEFAULT 'platz',
		PRIMARY KEY (id),
		UNIQUE KEY venue_wish (venue_id, wish_key),
		KEY venue_id (venue_id)
	) {$charset};";

	// Verfügbarkeit je Wunschtermin und Platz (28.09.2026): available 1 = geht,
	// 0 = geht nicht, NULL = noch keine Antwort; note = Alternativvorschlag.
	$d    = fge_venue_dates_table();
	$sql3 = "CREATE TABLE {$d} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		venue_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		date_index TINYINT UNSIGNED NOT NULL DEFAULT 0,
		available TINYINT(1) NULL DEFAULT NULL,
		note TEXT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY venue_date (venue_id, date_index)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	dbDelta( $sql2 );
	dbDelta( $sql3 );
	update_option( 'fge_venues_db_version', FGE_VENUES_DB_VERSION );
}
add_action( 'init', 'fge_venues_install' );

// ── Vokabular ────────────────────────────────────────────────────────────────

/** Zustände eines angefragten Platzes. */
function fge_venue_statuses(): array {
	return [
		'idee'      => 'Idee',
		'angefragt' => 'Angefragt',
		'zugesagt'  => 'Zugesagt',
		'abgesagt'  => 'Abgesagt',
		'gewaehlt'  => 'Gewählt',
	];
}

/** Über welchen Weg der Platz angefragt wurde. */
function fge_venue_channels(): array {
	return [
		'mail'    => 'per Mail aus dem System',
		'telefon' => 'telefonisch',
		'portal'  => 'über das Partnerportal',
	];
}

/** Häufige Absagegründe, frei ergänzbar. Basis für die spätere Auswertung. */
function fge_venue_reasons(): array {
	return [
		'Termin belegt',
		'Keine Kapazität für Gruppen',
		'Kein Pro verfügbar',
		'Preis passt nicht',
		'Keine Rückmeldung',
	];
}

// ── Lesen ────────────────────────────────────────────────────────────────────

/** Alle angefragten Plätze einer Anfrage, gewählter zuerst. */
function fge_venues_get( int $req ): array {
	global $wpdb;
	$t = fge_venues_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$t} WHERE request_id = %d ORDER BY FIELD(status,'gewaehlt','zugesagt','angefragt','idee','abgesagt'), id ASC",
		$req
	), ARRAY_A ) ?: [];

	foreach ( $rows as &$r ) {
		$r['title'] = (int) $r['partner_id'] > 0 ? (string) get_the_title( (int) $r['partner_id'] ) : 'Unbekannter Platz';
		$r['items'] = fge_venue_items_get( (int) $r['id'] );
	}
	return $rows;
}

/** Eine Zeile der Pipeline. */
function fge_venue_get( int $id ): ?array {
	global $wpdb;
	$t = fge_venues_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

/** Positionen eines angefragten Platzes. */
function fge_venue_items_get( int $venue_id ): array {
	global $wpdb;
	$t = fge_venue_items_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$t} WHERE venue_id = %d ORDER BY id ASC",
		$venue_id
	), ARRAY_A ) ?: [];
}

// ── Schreiben ────────────────────────────────────────────────────────────────

/** Platz in die Pipeline aufnehmen. Gibt die Zeilen-ID zurück, 0 bei Dublette. */
function fge_venue_add( int $req, int $partner_id ): int {
	global $wpdb;
	if ( $req <= 0 || $partner_id <= 0 ) {
		return 0;
	}
	$wpdb->query( $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'INSERT IGNORE INTO ' . fge_venues_table() . ' (request_id, partner_id, status, created_at) VALUES (%d, %d, %s, %s)',
		$req,
		$partner_id,
		'idee',
		current_time( 'mysql' )
	) );
	$id = (int) $wpdb->insert_id;
	if ( $id > 0 ) {
		// Die Wünsche des Kunden als Positionszeilen anlegen, damit jeder Platz
		// zu denselben Punkten einen Preis nennen kann.
		foreach ( fge_venue_wishlist( $req ) as $key => $label ) {
			fge_venue_item_set( $id, $key, [ 'label' => $label ] );
		}
		if ( function_exists( 'fge_activity_add' ) ) {
			fge_activity_add( $req, 'venue', sprintf( '%s in die Platz-Liste aufgenommen', get_the_title( $partner_id ) ) );
		}
	}
	return $id;
}

/** Felder einer Pipeline-Zeile ändern. */
function fge_venue_update( int $id, array $data ): bool {
	global $wpdb;
	$allowed = [ 'status', 'channel', 'asked_at', 'replied_at', 'reason', 'price', 'price_basis', 'price_gross', 'note', 'contact_id', 'catalog_token', 'catalog_answer', 'catalog_answered_at', 'catalog_event_id', 'catalog_note', 'markup_percent', 'sale_price', 'in_offer', 'option_text', 'detail_token', 'details_at', 'summary_at', 'reservation_at', 'reserved_until' ];
	$set     = array_intersect_key( $data, array_flip( $allowed ) );
	if ( ! $set || $id <= 0 ) {
		return false;
	}
	return false !== $wpdb->update( fge_venues_table(), $set, [ 'id' => $id ] );
}

/** Platz aus der Pipeline entfernen, samt Positionen. */
function fge_venue_delete( int $id ): void {
	global $wpdb;
	$wpdb->delete( fge_venue_items_table(), [ 'venue_id' => $id ] );
	$wpdb->delete( fge_venues_table(), [ 'id' => $id ] );
}

/** Position anlegen oder ändern. */
function fge_venue_item_set( int $venue_id, string $wish_key, array $data ): void {
	global $wpdb;
	$t   = fge_venue_items_table();
	$key = mb_substr( $wish_key, 0, 60 );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE venue_id = %d AND wish_key = %s", $venue_id, $key ) );

	$row = array_intersect_key( $data, array_flip( [ 'label', 'available', 'price', 'price_basis', 'price_gross', 'note', 'sale_price', 'organizer' ] ) );
	if ( $id > 0 ) {
		if ( $row ) {
			$wpdb->update( $t, $row, [ 'id' => $id ] );
		}
		return;
	}
	$wpdb->insert( $t, $row + [ 'venue_id' => $venue_id, 'wish_key' => $key ] );
}

// ── Wünsche des Kunden als Positionsliste ────────────────────────────────────

/**
 * Was der Kunde über das Event hinaus angefragt hat, als key => Label.
 * Diese Liste bekommt jeder Platz vorgelegt, damit die Angebote vergleichbar
 * werden statt nur beim Green Fee.
 */
function fge_venue_wishlist( int $req ): array {
	$labels = fge_catalog_wish_labels();

	$out = [ 'green_fee' => 'Event und Platznutzung' ];
	foreach ( $labels as $key => $label ) {
		if ( '1' === (string) get_post_meta( $req, '_fge_' . $key, true ) ) {
			$out[ $key ] = $label;
		}
	}
	// Wünsche aus dem Event-Dialog (Abendessen, Getränkepauschale …) liegen als
	// Labels in _fge_wishes_platz/_firmengolf, nicht als wants_*-Häkchen. Ohne sie
	// fragte die Pipeline den Platz nie danach (FG-26-166, 28.09.2026).
	$have = array_map( static fn( $l ) => mb_strtolower( trim( (string) $l ) ), $out );
	$n    = 0;
	foreach ( [ '_fge_wishes_platz', '_fge_wishes_firmengolf' ] as $mk ) {
		foreach ( (array) get_post_meta( $req, $mk, true ) as $w ) {
			$w = trim( (string) $w );
			if ( '' === $w || in_array( mb_strtolower( $w ), $have, true ) ) {
				continue;
			}
			$out[ 'wunsch_' . $n++ ] = mb_substr( $w, 0, 120 );
			$have[]                  = mb_strtolower( $w );
		}
	}
	// Freitextwünsche aus dem Formular ergänzen.
	$extra = (string) get_post_meta( $req, '_fge_additional_wishes', true );
	foreach ( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n|,/', $extra ) ) ) as $n => $w ) {
		$out[ 'frei_' . $n ] = mb_substr( $w, 0, 120 );
	}
	return $out;
}

// ── Preisgedächtnis ──────────────────────────────────────────────────────────

/**
 * Was dieser Platz früher für dieselbe Position genannt hat.
 *
 * Damit füllt sich eine Preisliste von selbst: jeder einmal telefonisch geholte
 * Preis steht bei der nächsten Anfrage als Vorschlag da. Das ist der eigentliche
 * Hebel, wenn aus drei Anfragen zwanzig am Tag werden.
 */
function fge_venue_price_history( int $partner_id, string $wish_key = 'green_fee', int $limit = 5 ): array {
	global $wpdb;
	$v = fge_venues_table();
	$i = fge_venue_items_table();

	if ( 'green_fee' === $wish_key ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT price, price_basis, price_gross, replied_at, request_id
			 FROM {$v}
			 WHERE partner_id = %d AND price > 0
			 ORDER BY COALESCE(replied_at, created_at) DESC LIMIT %d",
			$partner_id,
			$limit
		), ARRAY_A ) ?: [];
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT it.price, it.price_basis, it.price_gross, v.replied_at, v.request_id
		 FROM {$i} it
		 INNER JOIN {$v} v ON v.id = it.venue_id
		 WHERE v.partner_id = %d AND it.wish_key = %s AND it.price > 0
		 ORDER BY COALESCE(v.replied_at, v.created_at) DESC LIMIT %d",
		$partner_id,
		$wish_key,
		$limit
	), ARRAY_A ) ?: [];
}

/**
 * Letzte Green-Fee-Preise für viele Plätze in einer Abfrage.
 *
 * Das Verzeichnis fragte sonst je Platz einzeln nach, bei zweihundert Plätzen
 * also zweihundertmal.
 */
function fge_venue_price_hints_many( array $partner_ids ): array {
	$partner_ids = array_values( array_unique( array_map( 'intval', $partner_ids ) ) );
	if ( ! $partner_ids ) {
		return [];
	}
	global $wpdb;
	$t  = fge_venues_table();
	$in = implode( ',', array_fill( 0, count( $partner_ids ), '%d' ) );

	// Je Platz die jüngste Zeile mit Preis.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT v.partner_id, v.price, v.price_basis, v.price_gross
		 FROM {$t} v
		 INNER JOIN (
			SELECT partner_id, MAX(COALESCE(replied_at, created_at)) AS ts
			FROM {$t} WHERE price > 0 AND partner_id IN ({$in}) GROUP BY partner_id
		 ) last ON last.partner_id = v.partner_id
			AND COALESCE(v.replied_at, v.created_at) = last.ts
		 WHERE v.price > 0",
		...$partner_ids
	), ARRAY_A ) ?: [];

	$out = [];
	foreach ( $rows as $r ) {
		$out[ (int) $r['partner_id'] ] = sprintf(
			'zuletzt %s € %s %s',
			number_format_i18n( (float) $r['price'], 2 ),
			(int) $r['price_gross'] ? 'brutto' : 'netto',
			'person' === $r['price_basis'] ? 'p.P.' : 'pauschal'
		);
	}
	return $out;
}

/** Letzter bekannter Preis als Satz, leer wenn es keinen gibt. */
function fge_venue_price_hint( int $partner_id, string $wish_key = 'green_fee' ): string {
	$rows = fge_venue_price_history( $partner_id, $wish_key, 1 );
	if ( ! $rows ) {
		return '';
	}
	$r = $rows[0];
	return sprintf(
		'zuletzt %s € %s %s',
		number_format_i18n( (float) $r['price'], 2 ),
		(int) $r['price_gross'] ? 'brutto' : 'netto',
		'person' === $r['price_basis'] ? 'p.P.' : 'pauschal'
	);
}

// ── Auswahl ──────────────────────────────────────────────────────────────────

/**
 * Einen Platz nehmen: setzt _fge_assigned_partner_id und damit die bestehende
 * Mechanik in Gang. Die übrigen Plätze bleiben stehen, damit man ihnen absagen
 * kann und der Absagegrund auswertbar bleibt.
 */
function fge_venue_choose( int $req, int $venue_id ): bool {
	global $wpdb;
	$row = fge_venue_get( $venue_id );
	if ( ! $row || (int) $row['request_id'] !== $req ) {
		return false;
	}
	$t = fge_venues_table();
	// Ein zuvor gewählter Platz fällt auf „zugesagt" zurück.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$t} SET status = 'zugesagt' WHERE request_id = %d AND status = 'gewaehlt'",
		$req
	) );
	fge_venue_update( $venue_id, [ 'status' => 'gewaehlt' ] );

	update_post_meta( $req, '_fge_assigned_partner_id', (int) $row['partner_id'] );

	// Den vereinbarten Einkaufspreis in die Anfrage übernehmen, damit die
	// Auftragsbestätigung an den Platz einen Betrag nennen kann.
	if ( (float) $row['price'] > 0 ) {
		update_post_meta( $req, '_fge_partner_cost', (float) $row['price'] );
		update_post_meta( $req, '_fge_partner_cost_basis', (string) $row['price_basis'] );
		update_post_meta( $req, '_fge_partner_cost_gross', (int) $row['price_gross'] );
	}

	if ( function_exists( 'fge_activity_add' ) ) {
		fge_activity_add( $req, 'venue', sprintf( '%s als Platz gewählt', get_the_title( (int) $row['partner_id'] ) ) );
	}

	// Was der Platz genannt hat, direkt ins Angebot: Positionen, Ort, Grundpreis.
	$had_base = (float) get_post_meta( $req, '_fge_offer_base_override', true ) > 0;
	$taken    = fge_venue_apply_to_offer( $req, $row );
	$got_base = ! $had_base && (float) get_post_meta( $req, '_fge_offer_base_override', true ) > 0;
	if ( ( $taken > 0 || $got_base ) && function_exists( 'fge_activity_add' ) ) {
		fge_activity_add( $req, 'venue', 'Preise und Positionen aus der Pipeline übernommen, bitte im Positionen-Panel prüfen' );
	}
	return true;
}

/**
 * Pipeline-Antwort des gewählten Platzes ins Angebot übernehmen.
 *
 * Die Positionen des Platzes wandern in _fge_extra_services (Match über den
 * Wunsch bzw. das Label), der Ort wird vorbelegt und bei Platzhalter-Events ohne
 * eigenen Preis wird der Green-Fee-Einkauf zum Grundpreis-Override. Handeingaben
 * von Julius bleiben stehen, die Pipeline füllt nur Lücken.
 *
 * @return int Anzahl der übernommenen Positionen.
 */
function fge_venue_apply_to_offer( int $req, array $row ): int {
	$partner_id = (int) ( $row['partner_id'] ?? 0 );
	if ( $req <= 0 || $partner_id <= 0 ) {
		return 0;
	}

	$pname = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: (string) get_the_title( $partner_id );
	$pmail = sanitize_email( (string) get_post_meta( $partner_id, '_fge_main_contact_email', true ) );
	if ( '' === $pmail && function_exists( 'fge_cc_partner_email' ) ) {
		$pmail = fge_cc_partner_email( $partner_id );
	}
	$margin = function_exists( 'fge_xs_default_margin' ) ? fge_xs_default_margin() : 20.0;
	$norm   = static fn( $s ) => mb_strtolower( trim( (string) $s ) );

	// a) Positionen mergen.
	$raw   = get_post_meta( $req, '_fge_extra_services', true );
	$items = is_array( $raw ) ? array_values( array_filter( $raw, 'is_array' ) ) : [];
	$max   = 0;
	foreach ( $items as $it ) {
		$max = max( $max, (int) ( $it['id'] ?? 0 ) );
	}

	$taken = 0;
	$fg_wishes = array_map( $norm, (array) get_post_meta( $req, '_fge_wishes_firmengolf', true ) );
	foreach ( fge_venue_items_get( (int) ( $row['id'] ?? 0 ) ) as $vi ) {
		$key   = (string) $vi['wish_key'];
		$label = trim( (string) $vi['label'] );
		// Das Green Fee ist kein Zusatzposten, es wird unten zum Grundpreis.
		if ( 'green_fee' === $key || '' === $label ) {
			continue;
		}
		$available = (int) $vi['available'] > 0;
		$price     = (float) $vi['price'];
		$basis     = 'pauschal' === (string) $vi['price_basis'] ? 'pauschal' : 'person';
		$gross     = (int) $vi['price_gross'] ? 1 : 0;
		$note      = trim( (string) ( $vi['note'] ?? '' ) );

		$idx = null;
		foreach ( $items as $i => $it ) {
			$w = $norm( $it['wish'] ?? '' );
			$l = $norm( $it['label'] ?? '' );
			if ( in_array( $norm( $label ), [ $w, $l ], true ) || in_array( $norm( $key ), [ $w, $l ], true ) ) {
				$idx = $i;
				break;
			}
		}

		if ( null === $idx ) {
			$items[] = [
				'id'             => ++$max,
				'label'          => $label,
				'wish'           => $label,
				'cost'           => 0.0,
				'cost_gross'     => 1,
				'basis'          => $basis,
				'margin'         => $margin,
				'organizer'      => 'platz',
				'provider_name'  => '',
				'provider_email' => '',
				'partner_id'     => 0,
				'note'           => '',
				'guide'          => '',
				'status'         => 'angeboten',
			];
			$idx = array_key_last( $items );
		}
		$it       = $items[ $idx ];
		$has_cost = (float) ( $it['cost'] ?? 0 ) > 0;

		if ( ! $available ) {
			// Extern gelöst und schon bepreist: Julius hat das anders geregelt.
			if ( $has_cost && 'extern' === (string) ( $it['organizer'] ?? '' ) ) {
				continue;
			}
			if ( in_array( $norm( $label ), $fg_wishes, true ) ) {
				// „Über Firmengolf gewünscht" (Fotograf): sagt der Platz Nein, bleibt es
				// eine externe Position, die Julius bepreist, kein „nicht möglich" beim Kunden.
				$it['organizer']  = 'extern';
				$it['partner_id'] = 0;
				$it['status']     = 'angeboten';
				$it['cost']       = 0.0;
			} else {
				$it['organizer']  = 'platz';
				$it['partner_id'] = $partner_id;
				$it['status']     = 'nicht_moeglich';
				$it['cost']       = 0.0;
			}
		} else {
			$it['organizer']      = 'platz';
			$it['partner_id']     = $partner_id;
			$it['provider_name']  = $pname;
			$it['provider_email'] = $pmail;
			$it['status']         = 'angeboten';
			if ( $price > 0 && ! $has_cost ) {
				$it['cost']       = $price;
				$it['cost_gross'] = $gross;
				$it['basis']      = $basis;
			}
			if ( '' !== $note && '' === trim( (string) ( $it['note'] ?? '' ) ) ) {
				$it['note'] = $note;
			}
		}
		if ( (int) ( $it['id'] ?? 0 ) <= 0 ) {
			$it['id'] = ++$max;
		}
		if ( '' === trim( (string) ( $it['wish'] ?? '' ) ) ) {
			$it['wish'] = $label;
		}
		$items[ $idx ] = $it;
		$taken++;
	}
	if ( $taken > 0 ) {
		update_post_meta( $req, '_fge_extra_services', $items );
	}

	// b) Veranstaltungsort vorbelegen.
	if ( '' === trim( (string) get_post_meta( $req, '_fge_offer_location', true ) ) ) {
		$city = trim( (string) get_post_meta( $partner_id, '_fge_city', true ) );
		update_post_meta( $req, '_fge_offer_location', '' !== $city ? $pname . ', ' . $city : $pname );
	}

	// c) Grundpreis-Override, wenn das Event selbst keinen Preis liefert.
	$price = (float) ( $row['price'] ?? 0 );
	if ( $price > 0 && (float) get_post_meta( $req, '_fge_offer_base_override', true ) <= 0 ) {
		$event_id    = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
		$event_gross = ( $event_id > 0 && function_exists( 'fge_event_pricing' ) )
			? (float) ( fge_event_pricing( $event_id )['gross'] ?? 0 )
			: 0.0;
		if ( $event_gross <= 0 ) {
			$vat    = defined( 'FGE_VAT_PERCENT' ) ? (float) FGE_VAT_PERCENT : 19.0;
			$markup = defined( 'FGE_MARKUP_PERCENT' ) ? (float) FGE_MARKUP_PERCENT : 20.0;
			$net    = (int) ( $row['price_gross'] ?? 1 ) ? $price / ( 1 + $vat / 100 ) : $price;
			update_post_meta( $req, '_fge_offer_base_override', round( $net * ( 1 + $markup / 100 ), 2 ) );
			update_post_meta( $req, '_fge_offer_base_override_unit', 'pauschal' === (string) ( $row['price_basis'] ?? '' ) ? 'pauschal' : 'person' );
		}
	}

	return $taken;
}

/**
 * Wie viele Plätze je Anfrage liegen noch als Idee herum.
 *
 * In einer Abfrage für alle Anfragen, weil die Arbeitsliste sonst je Vorgang
 * eine eigene stellen würde. Innerhalb eines Aufrufs gecacht.
 */
function fge_venues_idle_counts(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	global $wpdb;
	$t = fge_venues_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( "SELECT request_id, COUNT(*) AS n FROM {$t} WHERE status = 'idee' GROUP BY request_id", ARRAY_A ) ?: [];

	$cache = [];
	foreach ( $rows as $r ) {
		$cache[ (int) $r['request_id'] ] = (int) $r['n'];
	}
	return $cache;
}

/** Plätze, denen nach der Wahl noch abgesagt werden müsste. */
function fge_venues_to_decline( int $req ): array {
	return array_values( array_filter(
		fge_venues_get( $req ),
		static fn( $v ) => in_array( $v['status'], [ 'angefragt', 'zugesagt' ], true )
	) );
}

// ── Suche und Standort (Cockpit, 28.09.2026) ─────────────────────────────────

/**
 * Partner-Posts per Text finden: Titel, Ort oder PLZ.
 * Echte Partner stehen vor Stammdaten-Plätzen, innerhalb alphabetisch.
 *
 * @return array<int,array{id:int,title:string,city:string,plz:string,status:string}>
 */
function fge_venue_search_partners( string $term, array $exclude_ids = [], int $limit = 25 ): array {
	global $wpdb;
	$term = trim( $term );
	if ( '' === $term ) {
		return [];
	}
	$like    = '%' . $wpdb->esc_like( $term ) . '%';
	$exclude = array_values( array_filter( array_map( 'intval', $exclude_ids ), static fn( $i ) => $i > 0 ) );
	$not_in  = $exclude ? ' AND p.ID NOT IN (' . implode( ',', $exclude ) . ')' : '';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.ID AS id, p.post_title AS title,
			COALESCE(c.meta_value, '') AS city,
			COALESCE(z.meta_value, '') AS plz,
			COALESCE(s.meta_value, '') AS status
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_fge_city'
		LEFT JOIN {$wpdb->postmeta} z ON z.post_id = p.ID AND z.meta_key = '_fge_postal_code'
		LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_fge_partner_status'
		WHERE p.post_type = 'firmengolf_partner' AND p.post_status = 'publish'
			AND (p.post_title LIKE %s OR c.meta_value LIKE %s OR z.meta_value LIKE %s){$not_in}
		ORDER BY (COALESCE(s.meta_value, '') = 'stammdaten') ASC, p.post_title ASC
		LIMIT %d",
		$like,
		$like,
		$like,
		max( 1, $limit )
	), ARRAY_A ) ?: [];

	return array_map( static fn( $r ) => [
		'id'     => (int) $r['id'],
		'title'  => (string) $r['title'],
		'city'   => (string) $r['city'],
		'plz'    => (string) $r['plz'],
		'status' => (string) $r['status'],
	], $rows );
}

/**
 * Einen Freitext (PLZ oder Ort) in Koordinaten auflösen.
 *
 * @return array{lat:float,lng:float,label:string,candidates:array}|null
 */
function fge_request_geo_resolve_text( string $text ): ?array {
	$text = trim( $text );
	if ( '' === $text ) {
		return null;
	}
	$digits = preg_replace( '/\D/', '', $text );
	if ( 5 === strlen( $digits ) && function_exists( 'fge_geo_lookup_plz' ) ) {
		$hit = fge_geo_lookup_plz( $digits );
		if ( $hit ) {
			return [ 'lat' => (float) $hit[0], 'lng' => (float) $hit[1], 'label' => $digits . ' ' . (string) $hit[2], 'candidates' => [] ];
		}
	}
	if ( function_exists( 'fge_geo_suggest' ) ) {
		$hits = fge_geo_suggest( $text, 8 );
		if ( $hits ) {
			$exact = null;
			foreach ( $hits as $h ) {
				if ( 0 === strcasecmp( (string) $h['ort'], $text ) ) {
					$exact = $h;
					break;
				}
			}
			$pick       = $exact ?? $hits[0];
			$candidates = [];
			if ( ! $exact && count( $hits ) > 1 ) {
				foreach ( $hits as $h ) {
					$candidates[] = [ 'plz' => (string) $h['plz'], 'ort' => (string) $h['ort'], 'label' => (string) $h['plz'] . ' ' . (string) $h['ort'] ];
				}
			}
			return [
				'lat'        => (float) $pick['lat'],
				'lng'        => (float) $pick['lng'],
				'label'      => (string) $pick['plz'] . ' ' . (string) $pick['ort'],
				'candidates' => $candidates,
			];
		}
	}
	if ( function_exists( 'fge_geo_city_coords' ) ) {
		$c = fge_geo_city_coords( $text );
		if ( $c ) {
			return [ 'lat' => (float) $c[0], 'lng' => (float) $c[1], 'label' => $text, 'candidates' => [] ];
		}
	}
	return null;
}

/**
 * Standort-Anker einer Anfrage: gesetzter Kundenstandort, sonst PLZ, Ort, Region.
 *
 * @return array{lat:float,lng:float,label:string,source:string,candidates:array}
 */
function fge_request_geo_anchor( int $req ): array {
	$none = [ 'lat' => 0.0, 'lng' => 0.0, 'label' => '', 'source' => 'none', 'candidates' => [] ];
	if ( $req <= 0 ) {
		return $none;
	}
	$chain = [
		'anchor' => (string) get_post_meta( $req, '_fge_geo_anchor', true ),
		'plz'    => (string) get_post_meta( $req, '_fge_company_postal_code', true ),
		'city'   => (string) get_post_meta( $req, '_fge_company_city', true ),
		'region' => (string) get_post_meta( $req, '_fge_desired_region', true ),
	];
	foreach ( $chain as $source => $text ) {
		if ( '' === trim( $text ) ) {
			continue;
		}
		$hit = fge_request_geo_resolve_text( $text );
		if ( $hit ) {
			return $hit + [ 'source' => 'region' === $source ? 'city' : $source ];
		}
	}
	return $none;
}

/**
 * Simulatoren nach Entfernung zu einem Punkt, nur Einträge mit Koordinaten.
 *
 * @return array<int,array> Simulator-Einträge mit 'dist' (km) und 'key'.
 */
function fge_simulatoren_nearby( float $lat, float $lng, int $limit = 3 ): array {
	if ( ! function_exists( 'fge_simulatoren' ) || ! function_exists( 'fge_geo_distance' ) ) {
		return [];
	}
	$out = [];
	foreach ( fge_simulatoren() as $sim ) {
		$slat = (float) ( $sim['lat'] ?? 0 );
		$slng = (float) ( $sim['lng'] ?? 0 );
		if ( 0.0 === $slat || 0.0 === $slng ) {
			continue;
		}
		$sim['dist'] = fge_geo_distance( $lat, $lng, $slat, $slng );
		$sim['key']  = function_exists( 'fge_stammdaten_sim_key' )
			? fge_stammdaten_sim_key( (string) $sim['name'], (string) ( $sim['plz'] ?? '' ), (string) ( $sim['ort'] ?? '' ) )
			: '';
		$out[]       = $sim;
	}
	usort( $out, static fn( $a, $b ) => $a['dist'] <=> $b['dist'] );
	return array_slice( $out, 0, max( 1, $limit ) );
}

// ── Verfügbarkeit je Wunschtermin (28.09.2026) ───────────────────────────────

/** Antworten eines Platzes je Wunschtermin: [ date_index => [ 'available' => 1|0|null, 'note' => string ] ]. */
function fge_venue_dates_get( int $venue_id ): array {
	global $wpdb;
	$t   = fge_venue_dates_table();
	$out = [];
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT date_index, available, note FROM {$t} WHERE venue_id = %d ORDER BY date_index ASC", $venue_id ), ARRAY_A ) as $r ) {
		$out[ (int) $r['date_index'] ] = [
			'available' => null === $r['available'] ? null : (int) $r['available'],
			'note'      => (string) ( $r['note'] ?? '' ),
		];
	}
	return $out;
}

/** Antwort eines Platzes zu einem Wunschtermin setzen (null = noch offen). */
function fge_venue_date_set( int $venue_id, int $date_index, ?int $available, string $note = '' ): void {
	global $wpdb;
	if ( $venue_id <= 0 || $date_index < 1 || $date_index > 3 ) {
		return;
	}
	$t  = fge_venue_dates_table();
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE venue_id = %d AND date_index = %d", $venue_id, $date_index ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = [ 'available' => $available, 'note' => $note ];
	if ( $id > 0 ) {
		$wpdb->update( $t, $row, [ 'id' => $id ] );
	} else {
		$wpdb->insert( $t, $row + [ 'venue_id' => $venue_id, 'date_index' => $date_index ] );
	}
}

/** Indizes der Wunschtermine, die dieser Platz frei gemeldet hat. */
function fge_venue_free_dates( int $venue_id ): array {
	$out = [];
	foreach ( fge_venue_dates_get( $venue_id ) as $idx => $d ) {
		if ( 1 === $d['available'] ) {
			$out[] = (int) $idx;
		}
	}
	return $out;
}

/** Wunschtermine der Anfrage als [ index => Label ] (nur gefüllte). */
function fge_request_wish_date_labels( int $req ): array {
	$out = [];
	for ( $i = 1; $i <= 3; $i++ ) {
		$d = trim( (string) get_post_meta( $req, '_fge_preferred_date_' . $i, true ) );
		if ( '' !== $d ) {
			$out[ $i ] = $d;
		}
	}
	return $out;
}

/**
 * Verkaufspreis aus Einkauf: netto (brutto wird umgerechnet), plus Aufschlag,
 * auf volle Euro aufgerundet (Julius, 28.09.2026).
 */
function fge_venue_sale_from_cost( float $cost, bool $gross, float $markup_percent ): float {
	if ( $cost <= 0 ) {
		return 0.0;
	}
	$vat = defined( 'FGE_VAT_PERCENT' ) ? (float) FGE_VAT_PERCENT : 19.0;
	$net = $gross ? $cost / ( 1 + $vat / 100 ) : $cost;
	return (float) ceil( $net * ( 1 + $markup_percent / 100 ) - 0.00001 );
}
