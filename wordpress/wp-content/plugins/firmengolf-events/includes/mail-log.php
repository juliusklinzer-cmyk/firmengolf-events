<?php
/**
 * Postausgang: Protokoll jeder transaktionalen Mail.
 *
 * Bis 1.9.266 war nicht feststellbar, ob eine Mail den Empfänger erreicht hat
 * (Befund 22.09.2026: beim Golfpark Weidenhof blieb offen, ob die Buchungsmail
 * rausging, weil der Platz keine Adresse hinterlegt hatte).
 *
 * Gefüttert wird ausschließlich über die WordPress-Hooks `wp_mail_succeeded`
 * und `wp_mail_failed`. Kein einziger wp_mail()-Aufruf muss dafür umgebaut
 * werden. Die Zuordnung zur Anfrage läuft über einen gesetzten Kontext, sonst
 * über die Vorgangsnummer im Betreff.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_MAIL_LOG_DB_VERSION = '1.0.0';

/** Voll qualifizierter Tabellenname. */
function fge_mail_log_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'fge_mail_log';
}

/** Tabelle anlegen/aktualisieren (versionsgesteuert, bei jedem init aufrufbar). */
function fge_mail_log_install(): void {
	if ( get_option( 'fge_mail_log_db_version' ) === FGE_MAIL_LOG_DB_VERSION ) {
		return;
	}
	global $wpdb;
	$table   = fge_mail_log_table();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		request_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		mail_key VARCHAR(60) NOT NULL DEFAULT '',
		recipient VARCHAR(190) NOT NULL DEFAULT '',
		subject VARCHAR(255) NOT NULL DEFAULT '',
		status VARCHAR(10) NOT NULL DEFAULT 'sent',
		error TEXT NULL,
		sent_at DATETIME NULL DEFAULT NULL,
		PRIMARY KEY (id),
		KEY request_id (request_id),
		KEY sent_at (sent_at),
		KEY mail_key (mail_key)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'fge_mail_log_db_version', FGE_MAIL_LOG_DB_VERSION );
}
add_action( 'init', 'fge_mail_log_install' );

// ── Kontext ──────────────────────────────────────────────────────────────────
// Wer eine Mail auslöst, kann Anfrage und Registry-Schlüssel vorher setzen.
// Ohne Kontext greift die Erkennung über die Vorgangsnummer im Betreff.

/**
 * Kontext für die nächsten wp_mail()-Aufrufe setzen.
 *
 * @param int    $request_id Anfrage, zu der die Mails gehören (0 = unbekannt).
 * @param string $mail_key   Schlüssel aus der Mail-Registry.
 */
function fge_mail_log_context( int $request_id, string $mail_key = '' ): void {
	$GLOBALS['fge_mail_log_ctx'] = [ 'req' => $request_id, 'key' => $mail_key ];
}

/** Kontext wieder aufheben. Immer nach dem Versand aufrufen. */
function fge_mail_log_context_clear(): void {
	unset( $GLOBALS['fge_mail_log_ctx'] );
}

/** Aktueller Kontext oder leere Werte. */
function fge_mail_log_current_context(): array {
	$ctx = $GLOBALS['fge_mail_log_ctx'] ?? [];
	return [
		'req' => (int) ( $ctx['req'] ?? 0 ),
		'key' => (string) ( $ctx['key'] ?? '' ),
	];
}

// ── Zuordnung ────────────────────────────────────────────────────────────────

/** Anfrage-ID zu einer Vorgangsnummer (FG-26-165), 0 wenn unbekannt. */
function fge_request_by_ref( string $ref ): int {
	$ref = trim( $ref );
	if ( '' === $ref ) {
		return 0;
	}
	$found = get_posts( [
		'post_type'        => 'firmengolf_request',
		'post_status'      => 'any',
		'numberposts'      => 1,
		'fields'           => 'ids',
		'meta_key'         => '_fge_ref',
		'meta_value'       => $ref,
		'suppress_filters' => true,
	] );
	return $found ? (int) $found[0] : 0;
}

/** Vorgangsnummer aus einem Betreff ziehen, sonst leer. */
function fge_mail_log_ref_from_subject( string $subject ): string {
	return preg_match( '/\bFG-\d{2}-\d{3}\b/', $subject, $m ) ? $m[0] : '';
}

// ── Schreiben ────────────────────────────────────────────────────────────────

/** Empfängerliste eines wp_mail-Datensatzes als lesbare Zeile. */
function fge_mail_log_recipients( $to ): string {
	$list = is_array( $to ) ? $to : preg_split( '/[,;]\s*/', (string) $to );
	$list = array_filter( array_map( 'trim', (array) $list ) );
	return mb_substr( implode( ', ', $list ), 0, 190 );
}

/** Eine Zeile schreiben. */
function fge_mail_log_add( array $mail, string $status, string $error = '' ): void {
	global $wpdb;
	$subject = mb_substr( (string) ( $mail['subject'] ?? '' ), 0, 255 );
	$ctx     = fge_mail_log_current_context();
	$req     = $ctx['req'];
	if ( $req <= 0 ) {
		$ref = fge_mail_log_ref_from_subject( $subject );
		$req = '' !== $ref ? fge_request_by_ref( $ref ) : 0;
	}
	$wpdb->insert( fge_mail_log_table(), [
		'request_id' => $req,
		'mail_key'   => mb_substr( $ctx['key'], 0, 60 ),
		'recipient'  => fge_mail_log_recipients( $mail['to'] ?? '' ),
		'subject'    => $subject,
		'status'     => $status,
		'error'      => '' !== $error ? mb_substr( $error, 0, 2000 ) : null,
		'sent_at'    => current_time( 'mysql' ),
	] );
}

add_action( 'wp_mail_succeeded', 'fge_mail_log_on_success' );
function fge_mail_log_on_success( $mail_data ): void {
	if ( is_array( $mail_data ) ) {
		fge_mail_log_add( $mail_data, 'sent' );
	}
}

add_action( 'wp_mail_failed', 'fge_mail_log_on_failure' );
function fge_mail_log_on_failure( $error ): void {
	if ( ! is_wp_error( $error ) ) {
		return;
	}
	$data = $error->get_error_data();
	fge_mail_log_add( is_array( $data ) ? $data : [], 'failed', $error->get_error_message() );
}

// ── Lesen ────────────────────────────────────────────────────────────────────

/** Protokoll einer Anfrage, neueste zuerst. */
function fge_mail_log_for_request( int $request_id, int $limit = 50 ): array {
	global $wpdb;
	$table = fge_mail_log_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE request_id = %d ORDER BY sent_at DESC, id DESC LIMIT %d",
		$request_id,
		$limit
	), ARRAY_A ) ?: [];
}

/** Letzte Mails über alle Anfragen, neueste zuerst. */
function fge_mail_log_recent( int $limit = 100, string $status = '' ): array {
	global $wpdb;
	$table = fge_mail_log_table();
	$where = '' !== $status ? $wpdb->prepare( ' WHERE status = %s', $status ) : '';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table}{$where} ORDER BY sent_at DESC, id DESC LIMIT %d",
		$limit
	), ARRAY_A ) ?: [];
}

/**
 * Ist eine bestimmte Mail für diese Anfrage schon erfolgreich rausgegangen?
 * Gedacht für Anzeigen („Buchungsmail an den Platz: zugestellt"), nicht als Guard.
 */
function fge_mail_log_has( int $request_id, string $mail_key ): bool {
	global $wpdb;
	$table = fge_mail_log_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$table} WHERE request_id = %d AND mail_key = %s AND status = 'sent' LIMIT 1",
		$request_id,
		$mail_key
	) );
}
