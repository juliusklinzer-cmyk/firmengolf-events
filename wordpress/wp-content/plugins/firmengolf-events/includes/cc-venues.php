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

const FGE_VENUES_DB_VERSION = '1.0.0';

function fge_venues_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'fge_request_venues';
}

function fge_venue_items_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'fge_request_venue_items';
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
		PRIMARY KEY (id),
		UNIQUE KEY venue_wish (venue_id, wish_key),
		KEY venue_id (venue_id)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	dbDelta( $sql2 );
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
	$allowed = [ 'status', 'channel', 'asked_at', 'replied_at', 'reason', 'price', 'price_basis', 'price_gross', 'note', 'contact_id' ];
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

	$row = array_intersect_key( $data, array_flip( [ 'label', 'available', 'price', 'price_basis', 'price_gross', 'note' ] ) );
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
	$labels = [
		'wants_golf_teacher'             => 'Golflehrer',
		'wants_meeting_room'             => 'Meetingraum',
		'wants_breakfast'                => 'Frühstück',
		'wants_lunch'                    => 'Lunch',
		'wants_dinner'                   => 'Abendessen',
		'wants_shuttle'                  => 'Shuttle',
		'wants_branding'                 => 'Branding',
		'wants_tournament_mode'          => 'Turniermodus',
		'wants_bad_weather_alternative'  => 'Schlechtwetter-Alternative',
		'wants_individual_customization' => 'Individuelle Anpassung',
	];

	$out = [ 'green_fee' => 'Event und Platznutzung' ];
	foreach ( $labels as $key => $label ) {
		if ( '1' === (string) get_post_meta( $req, '_fge_' . $key, true ) ) {
			$out[ $key ] = $label;
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
	return true;
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
