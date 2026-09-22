<?php
/**
 * Zeitleiste je Anfrage: Statuswechsel, Telefonnotizen, Notizen, Ereignisse.
 *
 * Ergänzt das Mail-Protokoll (mail-log.php) um alles, was keine Mail ist.
 * Telefonate waren bisher nirgends festgehalten, obwohl der halbe Vertrieb
 * darüber läuft.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_ACTIVITY_DB_VERSION = '1.0.0';

/** Voll qualifizierter Tabellenname. */
function fge_activity_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'fge_activity';
}

/** Tabelle anlegen/aktualisieren (versionsgesteuert). */
function fge_activity_install(): void {
	if ( get_option( 'fge_activity_db_version' ) === FGE_ACTIVITY_DB_VERSION ) {
		return;
	}
	global $wpdb;
	$table   = fge_activity_table();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		request_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		type VARCHAR(20) NOT NULL DEFAULT 'note',
		text TEXT NULL,
		user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NULL DEFAULT NULL,
		PRIMARY KEY (id),
		KEY request_id (request_id),
		KEY created_at (created_at)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'fge_activity_db_version', FGE_ACTIVITY_DB_VERSION );
}
add_action( 'init', 'fge_activity_install' );

/** Erlaubte Arten mit Anzeigelabel. */
function fge_activity_types(): array {
	return [
		'status' => 'Status',
		'call'   => 'Telefonat',
		'note'   => 'Notiz',
		'venue'  => 'Platz',
		'offer'  => 'Angebot',
		'system' => 'System',
	];
}

/**
 * Einen Eintrag schreiben.
 *
 * @param int    $request_id Anfrage.
 * @param string $type       Art, siehe fge_activity_types().
 * @param string $text       Klartext, wird als solcher angezeigt.
 */
function fge_activity_add( int $request_id, string $type, string $text ): void {
	if ( $request_id <= 0 || '' === trim( $text ) ) {
		return;
	}
	global $wpdb;
	if ( ! isset( fge_activity_types()[ $type ] ) ) {
		$type = 'note';
	}
	$wpdb->insert( fge_activity_table(), [
		'request_id' => $request_id,
		'type'       => $type,
		'text'       => mb_substr( trim( $text ), 0, 4000 ),
		'user_id'    => get_current_user_id(),
		'created_at' => current_time( 'mysql' ),
	] );
}

/** Einträge einer Anfrage, neueste zuerst. */
function fge_activity_for_request( int $request_id, int $limit = 100 ): array {
	global $wpdb;
	$table = fge_activity_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE request_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
		$request_id,
		$limit
	), ARRAY_A ) ?: [];
}

/** Letzte Einträge über alle Anfragen (für „was ist seit gestern passiert"). */
function fge_activity_recent( int $limit = 50 ): array {
	global $wpdb;
	$table = fge_activity_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d",
		$limit
	), ARRAY_A ) ?: [];
}

// ── Automatische Einträge ────────────────────────────────────────────────────

add_action( 'fge_request_status_changed', 'fge_activity_log_status', 10, 3 );
function fge_activity_log_status( int $req, string $status, string $old ): void {
	$label = function_exists( 'fge_cc_status_label' ) ? 'fge_cc_status_label' : null;
	$to    = $label ? $label( $status ) : $status;
	$from  = '' !== $old ? ( $label ? $label( $old ) : $old ) : 'neu angelegt';
	fge_activity_add( $req, 'status', sprintf( 'Status: %s → %s', $from, $to ) );
}
