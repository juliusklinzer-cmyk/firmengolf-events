<?php
/**
 * Stammdaten-Plätze: alle DGV-Golfplätze (wp_fge_golfplaetze) und alle Simulatoren
 * (simulatoren-data.php) als Partner-Posts mit Status `stammdaten`.
 *
 * Damit kann die Platz-Pipeline des Control Centers jeden Platz anfragen, ohne dass
 * er Partner ist: kein Portal, nicht öffentlich, keine Termin-Einladungen, kein
 * Matching (Klausel fge_stammdaten_exclude_meta_clause in helpers.php).
 *
 * Der Import läuft auf Live ohne WP-CLI: option-gated, in Batches je Cron-Tick oder
 * je Aufruf des Control Centers, idempotent über `_fge_verzeichnis_id` bzw.
 * `_fge_simulator_key`. Julius, 28.09.2026 (Auslöser FG-26-166).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_STAMMDATEN_OPTION = 'fge_stammdaten_import';
const FGE_STAMMDATEN_BATCH  = 40;

// ── Schlüssel und Lookups ────────────────────────────────────────────────────

/** Stabiler Schlüssel eines Simulators (Name normalisiert plus PLZ, sonst Ort). */
function fge_stammdaten_sim_key( string $name, string $plz, string $ort ): string {
	$n = function_exists( 'fge_venue_name_norm' ) ? fge_venue_name_norm( $name ) : mb_strtolower( trim( $name ) );
	$p = trim( $plz );
	if ( '' === $p ) {
		$p = function_exists( 'fge_venue_name_norm' ) ? fge_venue_name_norm( $ort ) : mb_strtolower( trim( $ort ) );
	}
	return $n . '|' . $p;
}

/** Partner-Post zu einem Meta-Schlüssel (Verzeichnis-id, Simulator-Key, manueller Key). */
function fge_stammdaten_find_by_meta( string $meta_key, string $value ): int {
	if ( '' === $value ) {
		return 0;
	}
	$ids = get_posts( [
		'post_type'        => 'firmengolf_partner',
		'post_status'      => 'any',
		'numberposts'      => 1,
		'fields'           => 'ids',
		'meta_key'         => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'       => $value,    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'suppress_filters' => true,
	] );
	return $ids ? (int) $ids[0] : 0;
}

/** Partner-Post eines Simulators (leer, wenn noch nicht angelegt). */
function fge_stammdaten_sim_partner_id( string $key ): int {
	return fge_stammdaten_find_by_meta( '_fge_simulator_key', $key );
}

/** Verzeichniszeile per Name plus PLZ (Live-ids weichen von lokalen ab). */
function fge_verzeichnis_find_by_name_plz( string $name, string $plz ): ?array {
	global $wpdb;
	if ( ! function_exists( 'fge_verzeichnis_table' ) ) {
		return null;
	}
	$t    = fge_verzeichnis_table();
	$norm = function_exists( 'fge_venue_name_norm' ) ? 'fge_venue_name_norm' : 'mb_strtolower';
	$plz  = trim( $plz );
	$rows = $plz
		? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE plz = %s", $plz ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		: [];
	if ( ! $rows ) {
		$like = '%' . $wpdb->esc_like( mb_substr( trim( $name ), 0, 12 ) ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE name LIKE %s LIMIT 20", $like ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$want = $norm( $name );
	foreach ( (array) $rows as $r ) {
		$have = $norm( (string) $r['name'] );
		if ( '' !== $want && ( $have === $want || false !== mb_strpos( $have, $want ) || false !== mb_strpos( $want, $have ) ) ) {
			return $r;
		}
	}
	return null;
}

/**
 * Gibt es schon einen echten Partner (kein Stammdaten-Post) mit gleicher PLZ und
 * ähnlichem Namen? Dann wird nichts angelegt, sondern dieser verwendet.
 */
function fge_stammdaten_match_existing_partner( string $name, string $plz ): int {
	$plz = trim( $plz );
	if ( '' === $plz ) {
		return 0;
	}
	$ids = get_posts( [
		'post_type'        => 'firmengolf_partner',
		'post_status'      => [ 'publish', 'draft', 'pending' ],
		'numberposts'      => 20,
		'fields'           => 'ids',
		'meta_key'         => '_fge_postal_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'       => $plz,               // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'suppress_filters' => true,
	] );
	$norm = function_exists( 'fge_venue_name_norm' ) ? 'fge_venue_name_norm' : 'mb_strtolower';
	$want = $norm( $name );
	foreach ( $ids as $pid ) {
		$pid = (int) $pid;
		if ( fge_partner_is_stammdaten( $pid ) ) {
			continue;
		}
		$have = $norm( (string) get_post_meta( $pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $pid ) );
		if ( '' !== $want && mb_strlen( $want ) >= 4 && ( $have === $want || false !== mb_strpos( $have, $want ) || false !== mb_strpos( $want, $have ) ) ) {
			return $pid;
		}
	}
	return 0;
}

/** Bundesland-Klartext → Schlüssel der Partner-Metabox (partner-fields.php). */
function fge_stammdaten_state_key( string $state ): string {
	$s = mb_strtolower( trim( $state ) );
	$s = str_replace( [ 'ä', 'ö', 'ü', 'ß', '-', ' ' ], [ 'ae', 'oe', 'ue', 'ss', '_', '_' ], $s );
	return isset( fge_catalog_federal_states()[ $s ] ) ? $s : '';
}

// ── Anlegen ──────────────────────────────────────────────────────────────────

/**
 * Stammdaten-Post anlegen. Erwartet: name, type (course|indoor), city, plz, street,
 * state, lat, lng, website, phone, email, contact_name, source (dgv|simulator|manuell),
 * raw (Array), keys (zusätzliche Metas, z. B. Idempotenz-Schlüssel).
 */
function fge_stammdaten_create( array $d ): int {
	$name = trim( (string) ( $d['name'] ?? '' ) );
	if ( '' === $name ) {
		return 0;
	}
	$plz = trim( (string) ( $d['plz'] ?? '' ) );
	$pid = wp_insert_post( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => 'publish',
		'post_title'  => $name,
		// Eigener Slug-Raum: echte Partner behalten den schönen /golfplatz/<name>/.
		'post_name'   => 'sd-' . sanitize_title( $name ) . ( '' !== $plz ? '-' . $plz : '' ),
	], true );
	if ( is_wp_error( $pid ) || ! $pid ) {
		return 0;
	}
	$pid  = (int) $pid;
	$meta = [
		'_fge_partner_status'         => 'stammdaten',
		'_fge_partner_type'           => 'indoor' === ( $d['type'] ?? '' ) ? 'indoor' : 'course',
		'_fge_public_golfclub_name'   => $name,
		'_fge_city'                   => trim( (string) ( $d['city'] ?? '' ) ),
		'_fge_postal_code'            => $plz,
		'_fge_street'                 => trim( (string) ( $d['street'] ?? '' ) ),
		'_fge_federal_state'          => fge_stammdaten_state_key( (string) ( $d['state'] ?? '' ) ),
		'_fge_latitude'               => (float) ( $d['lat'] ?? 0 ) ?: '',
		'_fge_longitude'              => (float) ( $d['lng'] ?? 0 ) ?: '',
		'_fge_website_url'            => trim( (string) ( $d['website'] ?? '' ) ),
		'_fge_main_contact_phone'     => trim( (string) ( $d['phone'] ?? '' ) ),
		'_fge_main_contact_email'     => is_email( (string) ( $d['email'] ?? '' ) ) ? sanitize_email( (string) $d['email'] ) : '',
		'_fge_main_contact_name'      => trim( (string) ( $d['contact_name'] ?? '' ) ),
		'_fge_stammdaten_source'      => (string) ( $d['source'] ?? 'manuell' ),
		'_fge_stammdaten_raw'         => (array) ( $d['raw'] ?? [] ),
		'_fge_stammdaten_created_at'  => current_time( 'mysql' ),
		'_fge_default_markup_percent' => 20,
	];
	foreach ( (array) ( $d['keys'] ?? [] ) as $k => $v ) {
		$meta[ $k ] = $v;
	}
	foreach ( $meta as $k => $v ) {
		if ( '' === $v || [] === $v ) {
			continue;
		}
		update_post_meta( $pid, $k, $v );
	}
	return $pid;
}

/**
 * Zeile aus wp_fge_golfplaetze → Partner-Post (vorhandenen zurückgeben, sonst anlegen)
 * und partner_id in die Zeile schreiben. Nie fge_verzeichnis_sync_partner aufrufen,
 * das würde Dubletten mit dgv_id 900000+ erzeugen.
 */
function fge_stammdaten_ensure_from_verzeichnis_row( array $row, array $extra = [] ): int {
	global $wpdb;
	$vid = (int) ( $row['id'] ?? 0 );
	if ( $vid <= 0 ) {
		return 0;
	}
	$pid = (int) ( $row['partner_id'] ?? 0 );
	if ( $pid > 0 && 'firmengolf_partner' === get_post_type( $pid ) ) {
		return $pid;
	}
	$pid = fge_stammdaten_find_by_meta( '_fge_verzeichnis_id', (string) $vid );
	if ( $pid <= 0 ) {
		$pid = fge_stammdaten_match_existing_partner( (string) $row['name'], (string) ( $row['plz'] ?? '' ) );
	}
	if ( $pid <= 0 ) {
		$mail = fge_stammdaten_mail_lookup_course( $row );
		$pid  = fge_stammdaten_create( [
			'name'    => (string) $row['name'],
			'type'    => 'course',
			'city'    => (string) ( $row['ort'] ?? '' ),
			'plz'     => (string) ( $row['plz'] ?? '' ),
			'street'  => (string) ( $row['strasse'] ?? '' ),
			'state'   => (string) ( $row['bundesland'] ?? '' ),
			'lat'     => (float) ( $row['lat'] ?? 0 ),
			'lng'     => (float) ( $row['lng'] ?? 0 ),
			'website' => (string) ( $row['website'] ?? '' ),
			'phone'   => (string) ( $row['telefon'] ?? '' ),
			'email'   => (string) ( $extra['email'] ?? $mail ),
			'source'  => 'dgv',
			'raw'     => [
				'loecher'       => (string) ( $row['loecher'] ?? '' ),
				'restaurant'    => (int) ( $row['restaurant'] ?? 0 ),
				'driving_range' => (int) ( $row['driving_range'] ?? 0 ),
				'schnupperkurse' => (int) ( $row['schnupperkurse'] ?? 0 ),
				'platzreifekurse' => (int) ( $row['platzreifekurse'] ?? 0 ),
			],
			'keys'    => [ '_fge_verzeichnis_id' => $vid, '_fge_dgv_id' => (string) ( $row['dgv_id'] ?? '' ) ],
		] + $extra );
	}
	if ( $pid > 0 && function_exists( 'fge_verzeichnis_table' ) ) {
		$wpdb->update( fge_verzeichnis_table(), [ 'partner_id' => $pid ], [ 'id' => $vid ], [ '%d' ], [ '%d' ] );
		if ( ! get_post_meta( $pid, '_fge_verzeichnis_id', true ) ) {
			update_post_meta( $pid, '_fge_verzeichnis_id', $vid );
		}
	}
	return $pid;
}

/** Eintrag aus fge_simulatoren() → Partner-Post (Typ indoor). */
function fge_stammdaten_ensure_from_simulator( array $sim ): int {
	$name = trim( (string) ( $sim['name'] ?? '' ) );
	if ( '' === $name ) {
		return 0;
	}
	$key = fge_stammdaten_sim_key( $name, (string) ( $sim['plz'] ?? '' ), (string) ( $sim['ort'] ?? '' ) );
	$pid = fge_stammdaten_find_by_meta( '_fge_simulator_key', $key );
	if ( $pid > 0 ) {
		return $pid;
	}
	$pid = fge_stammdaten_match_existing_partner( $name, (string) ( $sim['plz'] ?? '' ) );
	if ( $pid > 0 ) {
		update_post_meta( $pid, '_fge_simulator_key', $key );
		return $pid;
	}
	$c      = fge_stammdaten_mail_lookup_sim( $key, $name );
	$street = trim( (string) strtok( (string) ( $sim['adresse'] ?? '' ), ',' ) );
	if ( $street === trim( (string) ( $sim['adresse'] ?? '' ) ) && false === strpos( $street, ' ' ) ) {
		$street = '';
	}
	return fge_stammdaten_create( [
		'name'    => $name,
		'type'    => 'indoor',
		'city'    => (string) ( $sim['ort'] ?? '' ),
		'plz'     => (string) ( $sim['plz'] ?? '' ),
		'street'  => $street,
		'state'   => (string) ( $sim['bundesland'] ?? '' ),
		'lat'     => (float) ( $sim['lat'] ?? 0 ),
		'lng'     => (float) ( $sim['lng'] ?? 0 ),
		'website' => (string) ( $sim['website'] ?? $c['website'] ?? '' ),
		'phone'   => (string) ( $c['phone'] ?? '' ),
		'email'   => (string) ( $c['email'] ?? '' ),
		'source'  => 'simulator',
		'raw'     => [
			'bays'          => (string) ( $sim['bays'] ?? '' ),
			'system'        => (string) ( $sim['system'] ?? '' ),
			'eventlocation' => (string) ( $sim['eventlocation'] ?? '' ),
			'bar'           => (string) ( $sim['bar'] ?? '' ),
		],
		'keys'    => [ '_fge_simulator_key' => $key ],
	] );
}

/** Mail zu einer Verzeichniszeile: id-Treffer nur mit Namensprüfung, sonst Name plus PLZ. */
function fge_stammdaten_mail_lookup_course( array $row ): string {
	if ( ! function_exists( 'fge_stammdaten_mails_courses' ) ) {
		return '';
	}
	$norm = function_exists( 'fge_venue_name_norm' ) ? 'fge_venue_name_norm' : 'mb_strtolower';
	$want = $norm( (string) ( $row['name'] ?? '' ) );
	$all  = fge_stammdaten_mails_courses();
	$hit  = $all[ (int) ( $row['id'] ?? 0 ) ] ?? null;
	if ( $hit && $norm( $hit['name'] ) === $want ) {
		return (string) $hit['email'];
	}
	foreach ( $all as $c ) {
		if ( $norm( $c['name'] ) === $want && ( '' === $c['plz'] || '' === (string) ( $row['plz'] ?? '' ) || $c['plz'] === (string) $row['plz'] ) ) {
			return (string) $c['email'];
		}
	}
	return '';
}

/** Kontakt zu einem Simulator: Schlüssel-Treffer, sonst normalisierter Name. */
function fge_stammdaten_mail_lookup_sim( string $key, string $name ): array {
	if ( ! function_exists( 'fge_stammdaten_mails_simulators' ) ) {
		return [];
	}
	$all = fge_stammdaten_mails_simulators();
	if ( isset( $all[ $key ] ) ) {
		return $all[ $key ];
	}
	$norm = function_exists( 'fge_venue_name_norm' ) ? 'fge_venue_name_norm' : 'mb_strtolower';
	$want = $norm( $name );
	foreach ( $all as $k => $c ) {
		if ( '' !== $want && strtok( $k, '|' ) === $want ) {
			return $c;
		}
	}
	return [];
}

/**
 * Stammdaten-Platz wird Partner-Kandidat (Antwort „Ja" auf die Katalog-Einladung).
 * Status in_pruefung, Slug neu aus dem Titel. Kein Mailversand, das macht der Aufrufer.
 */
function fge_partner_promote_from_stammdaten( int $pid ): bool {
	if ( ! fge_partner_is_stammdaten( $pid ) ) {
		return false;
	}
	update_post_meta( $pid, '_fge_partner_status', 'in_pruefung' );
	update_post_meta( $pid, '_fge_stammdaten_promoted_at', current_time( 'mysql' ) );
	wp_update_post( [ 'ID' => $pid, 'post_name' => '' ] );
	return true;
}

// ── Import in Batches (Live ohne WP-CLI) ─────────────────────────────────────

function fge_stammdaten_import_state(): array {
	$s = get_option( FGE_STAMMDATEN_OPTION );
	return is_array( $s ) ? $s : [];
}

function fge_stammdaten_import_total(): int {
	$courses = function_exists( 'fge_verzeichnis_count' ) ? fge_verzeichnis_count() : 0;
	$sims    = function_exists( 'fge_simulatoren' ) ? count( fge_simulatoren() ) : 0;
	return $courses + $sims;
}

/** Ein Batch: erst Verzeichnis (nach id), dann Simulatoren. Liefert den neuen Stand. */
function fge_stammdaten_import_run_batch( int $limit = FGE_STAMMDATEN_BATCH ): array {
	global $wpdb;
	$s = fge_stammdaten_import_state();
	if ( ! $s ) {
		$s = [ 'phase' => 'courses', 'cursor' => 0, 'done' => 0, 'skipped' => 0, 'created' => 0, 'total' => fge_stammdaten_import_total(), 'started' => time(), 'finished' => 0, 'errors' => [] ];
	}
	if ( 'done' === $s['phase'] ) {
		return $s;
	}
	$n = 0;
	if ( 'courses' === $s['phase'] ) {
		$rows = function_exists( 'fge_verzeichnis_table' )
			? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . fge_verzeichnis_table() . ' WHERE id > %d ORDER BY id ASC LIMIT %d', (int) $s['cursor'], $limit ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			: [];
		if ( ! $rows ) {
			$s['phase']  = 'simulators';
			$s['cursor'] = 0;
		}
		foreach ( (array) $rows as $row ) {
			$s['cursor'] = (int) $row['id'];
			$n++;
			if ( (int) $row['partner_id'] > 0 && 'firmengolf_partner' === get_post_type( (int) $row['partner_id'] ) ) {
				$s['skipped']++;
				$s['done']++;
				continue;
			}
			$before = fge_stammdaten_find_by_meta( '_fge_verzeichnis_id', (string) $row['id'] );
			$pid    = fge_stammdaten_ensure_from_verzeichnis_row( $row );
			if ( $pid <= 0 ) {
				$s['errors'][] = 'Verzeichnis ' . (int) $row['id'] . ' ' . (string) $row['name'];
			} elseif ( $before <= 0 ) {
				$s['created']++;
			}
			$s['done']++;
		}
	} elseif ( 'simulators' === $s['phase'] ) {
		$all   = function_exists( 'fge_simulatoren' ) ? array_values( fge_simulatoren() ) : [];
		$slice = array_slice( $all, (int) $s['cursor'], $limit );
		if ( ! $slice ) {
			$s['phase']    = 'done';
			$s['finished'] = time();
		}
		foreach ( $slice as $sim ) {
			$s['cursor']++;
			$n++;
			$key    = fge_stammdaten_sim_key( (string) ( $sim['name'] ?? '' ), (string) ( $sim['plz'] ?? '' ), (string) ( $sim['ort'] ?? '' ) );
			$before = fge_stammdaten_find_by_meta( '_fge_simulator_key', $key );
			$pid    = fge_stammdaten_ensure_from_simulator( $sim );
			if ( $pid <= 0 ) {
				$s['errors'][] = 'Simulator ' . (string) ( $sim['name'] ?? '' );
			} elseif ( $before <= 0 ) {
				$s['created']++;
			} else {
				$s['skipped']++;
			}
			$s['done']++;
		}
	}
	$s['errors'] = array_slice( (array) $s['errors'], -50 );
	update_option( FGE_STAMMDATEN_OPTION, $s, true );
	if ( 'done' !== $s['phase'] && ! wp_next_scheduled( 'fge_stammdaten_import_tick' ) ) {
		wp_schedule_single_event( time() + 60, 'fge_stammdaten_import_tick' );
	}
	return $s;
}
add_action( 'fge_stammdaten_import_tick', static function (): void {
	fge_stammdaten_import_run_batch();
} );

/**
 * Start und Fortschritt ohne Cron-Verlass: beim ersten Aufruf des Control Centers
 * wird das Gate gesetzt und ein Batch gefahren, danach je Aufruf einer (mit Sperre),
 * bis alles durch ist. WP-Cron zieht dazwischen nach.
 */
add_action( 'init', static function (): void {
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! function_exists( 'fge_verzeichnis_count' ) || fge_verzeichnis_count() <= 0 ) {
		return; // Ohne Verzeichnis (frische lokale Installation) nichts anlegen.
	}
	$s = fge_stammdaten_import_state();
	if ( $s && 'done' === $s['phase'] ) {
		return;
	}
	if ( get_transient( 'fge_stammdaten_import_lock' ) ) {
		return;
	}
	set_transient( 'fge_stammdaten_import_lock', 1, 55 );
	fge_stammdaten_import_run_batch();
}, 30 );

/** Knopf „Weiter importieren" (Fallback, wenn Cron und Seitenaufrufe nicht reichen). */
add_action( 'admin_post_fge_cc_stammdaten_tick', static function (): void {
	if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'fge_cc_stammdaten_tick' ) ) {
		wp_die( 'Kein Zugriff.', '', [ 'response' => 403 ] );
	}
	for ( $i = 0; $i < 5; $i++ ) {
		$s = fge_stammdaten_import_run_batch();
		if ( 'done' === $s['phase'] ) {
			break;
		}
	}
	wp_safe_redirect( function_exists( 'fge_cc_url' ) ? fge_cc_url( 'verzeichnis' ) : admin_url() );
	exit;
} );

/** Statuszeile fürs Plätze-Verzeichnis im Control Center. */
function fge_stammdaten_import_status_line(): void {
	$s = fge_stammdaten_import_state();
	if ( ! $s ) {
		return;
	}
	$total = (int) ( $s['total'] ?? 0 );
	$done  = (int) ( $s['done'] ?? 0 );
	if ( 'done' === $s['phase'] ) {
		echo '<p class="cc-muted">Stammdaten-Import abgeschlossen am ' . esc_html( wp_date( 'd.m.Y H:i', (int) $s['finished'] ) ) . ': '
			. (int) $s['created'] . ' Plätze angelegt, ' . (int) $s['skipped'] . ' übersprungen (schon Partner oder vorhanden)'
			. ( ! empty( $s['errors'] ) ? ', ' . count( (array) $s['errors'] ) . ' Fehler' : '' ) . '.</p>';
		return;
	}
	echo '<div class="cc-msg"><strong>Stammdaten-Import läuft:</strong> ' . $done . ' / ' . $total
		. ' (' . esc_html( 'courses' === $s['phase'] ? 'Golfplätze' : 'Simulatoren' ) . '). Läuft im Hintergrund weiter, jeder Aufruf dieser Seite zieht einen Schritt nach. ';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
	echo '<input type="hidden" name="action" value="fge_cc_stammdaten_tick">';
	wp_nonce_field( 'fge_cc_stammdaten_tick' );
	echo '<button type="submit" class="cc-btn">Weiter importieren</button></form></div>';
}

// ── Abgleich Simulatoren-Datei ↔ Stammdaten-Plätze ───────────────────────────

/**
 * Hält die Stammdaten-Simulatoren auf dem Stand von simulatoren-data.php und der
 * Mail-Liste: neue Einträge werden angelegt, Einträge, die aus der Datei entfernt wurden
 * (z. B. geschlossene Anlagen), wandern in den Papierkorb, geänderte Mail/Website wird übernommen. Nur Posts mit
 * Status `stammdaten` und Quelle `simulator`, echte Partner bleiben unberührt.
 * Läuft einmal je Datenstand (Hash), angestoßen beim Admin-Aufruf. Julius, 29.09.2026.
 */
function fge_stammdaten_sync_simulators(): array {
	$keys = [];
	foreach ( function_exists( 'fge_simulatoren' ) ? fge_simulatoren() : [] as $sim ) {
		$keys[ fge_stammdaten_sim_key( (string) ( $sim['name'] ?? '' ), (string) ( $sim['plz'] ?? '' ), (string) ( $sim['ort'] ?? '' ) ) ] = (string) ( $sim['name'] ?? '' );
	}
	$ids = get_posts( [
		'post_type'      => 'firmengolf_partner',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [
			[ 'key' => '_fge_partner_status', 'value' => 'stammdaten' ],
			[ 'key' => '_fge_stammdaten_source', 'value' => 'simulator' ],
		],
	] );
	$out = [ 'trashed' => 0, 'updated' => 0, 'created' => 0 ];
	foreach ( function_exists( 'fge_simulatoren' ) ? fge_simulatoren() : [] as $sim ) {
		$key = fge_stammdaten_sim_key( (string) ( $sim['name'] ?? '' ), (string) ( $sim['plz'] ?? '' ), (string) ( $sim['ort'] ?? '' ) );
		if ( fge_stammdaten_find_by_meta( '_fge_simulator_key', $key ) <= 0 && fge_stammdaten_ensure_from_simulator( $sim ) > 0 ) {
			$out['created']++;
		}
	}
	foreach ( $ids as $pid ) {
		$key = (string) get_post_meta( $pid, '_fge_simulator_key', true );
		if ( '' === $key ) {
			continue;
		}
		if ( ! isset( $keys[ $key ] ) ) {
			wp_trash_post( $pid );
			$out['trashed']++;
			continue;
		}
		$c     = fge_stammdaten_mail_lookup_sim( $key, $keys[ $key ] );
		$email = is_email( (string) ( $c['email'] ?? '' ) ) ? sanitize_email( (string) $c['email'] ) : '';
		// Leere Mail bei vorhandenem Listeneintrag = Opt-out (z. B. „Löschen Sie meine E-Mail“), dann auch am Platz leeren.
		if ( ( '' !== $email || isset( $c['email'] ) ) && get_post_meta( $pid, '_fge_main_contact_email', true ) !== $email ) {
			update_post_meta( $pid, '_fge_main_contact_email', $email );
			$out['updated']++;
		}
		$web = trim( (string) ( $c['website'] ?? '' ) );
		if ( '' !== $web && get_post_meta( $pid, '_fge_website_url', true ) !== $web ) {
			update_post_meta( $pid, '_fge_website_url', $web );
		}
	}
	return $out;
}

add_action( 'admin_init', static function (): void {
	if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'fge_simulatoren' ) || ! function_exists( 'fge_stammdaten_mails_simulators' ) ) {
		return;
	}
	$s = fge_stammdaten_import_state();
	if ( ! $s || 'done' !== $s['phase'] ) {
		return; // Erst nach abgeschlossenem Import abgleichen.
	}
	$hash = md5( wp_json_encode( [ fge_simulatoren(), fge_stammdaten_mails_simulators() ] ) );
	if ( get_option( 'fge_stammdaten_sim_sync' ) === $hash ) {
		return;
	}
	update_option( 'fge_stammdaten_sim_sync', $hash, false );
	fge_stammdaten_sync_simulators();
} );
