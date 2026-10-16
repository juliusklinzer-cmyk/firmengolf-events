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

const FGE_MAIL_LOG_DB_VERSION = '1.1.0'; // 1.1.0: payload für „Erneut senden“ (16.10.2026)

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
		payload LONGTEXT NULL,
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
function fge_mail_log_add( array $mail, string $status, string $error = '' ): int {
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
		// Nur fehlgeschlagene Mails behalten ihren Inhalt, damit „Erneut senden“ geht.
		'payload'    => 'failed' === $status ? wp_json_encode( [
			'to'          => $mail['to'] ?? '',
			'subject'     => (string) ( $mail['subject'] ?? '' ),
			'message'     => (string) ( $mail['message'] ?? '' ),
			'headers'     => $mail['headers'] ?? [],
			'attachments' => array_values( (array) ( $mail['attachments'] ?? [] ) ),
		] ) : null,
	] );
	return (int) $wpdb->insert_id;
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

// ── Erneut senden ────────────────────────────────────────────────────────────

/** Eine Zeile des Protokolls. */
function fge_mail_log_get( int $id ): array {
	global $wpdb;
	$table = fge_mail_log_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ) ?: [];
}

/**
 * Kann diese fehlgeschlagene Mail noch einmal raus? Leer = ja, sonst der Grund.
 * Ohne gespeicherten Inhalt (vor 1.9.315) oder ohne Anhang geht es nicht.
 */
function fge_mail_log_retry_blocker( array $row ): string {
	if ( 'failed' !== (string) ( $row['status'] ?? '' ) ) {
		return 'schon erledigt';
	}
	$p = json_decode( (string) ( $row['payload'] ?? '' ), true );
	if ( ! is_array( $p ) || '' === (string) ( $p['message'] ?? '' ) ) {
		return 'Inhalt nicht gespeichert, bitte aus der Anfrage neu auslösen';
	}
	foreach ( (array) ( $p['attachments'] ?? [] ) as $file ) {
		if ( ! is_string( $file ) || ! file_exists( $file ) ) {
			return 'Anhang fehlt, bitte aus der Anfrage neu auslösen';
		}
	}
	return '';
}

/** Fehlgeschlagene Mail neu senden; die alte Zeile wird „erneut gesendet“. */
function fge_mail_log_retry( int $id ): bool {
	$row = fge_mail_log_get( $id );
	if ( ! $row || '' !== fge_mail_log_retry_blocker( $row ) ) {
		return false;
	}
	$p = json_decode( (string) $row['payload'], true );
	fge_mail_log_context( (int) $row['request_id'], (string) $row['mail_key'] );
	$ok = (bool) wp_mail( $p['to'], (string) $p['subject'], (string) $p['message'], $p['headers'] ?? [], (array) ( $p['attachments'] ?? [] ) );
	fge_mail_log_context_clear();
	if ( $ok ) {
		global $wpdb;
		$wpdb->update( fge_mail_log_table(), [ 'status' => 'resent', 'payload' => null ], [ 'id' => $id ] );
	}
	return $ok;
}

add_action( 'admin_post_fge_cc_mail_retry', static function (): void {
	$id = absint( $_POST['mail_id'] ?? 0 );
	if ( ! function_exists( 'fge_cc_can' ) || ! fge_cc_can() ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_cc_mail_retry_' . $id );
	$ok   = fge_mail_log_retry( $id );
	$back = sanitize_key( wp_unslash( $_POST['back'] ?? '' ) );
	$args = [ 'msg' => $ok ? 'mail_resent' : 'mail_resend_fail' ];
	wp_safe_redirect( 'heute' === $back ? fge_cc_url( 'dashboard', $args ) : fge_cc_url( 'einstellungen', [ 'bereich' => 'postausgang' ] + $args ) );
	exit;
} );

/** Knopf „Erneut senden“ für eine Zeile, sonst der Grund, warum es nicht geht. */
function fge_mail_log_retry_button( array $row, string $back = '' ): void {
	$why = fge_mail_log_retry_blocker( $row );
	if ( '' !== $why ) {
		echo '<span class="cc-muted">' . esc_html( $why ) . '</span>';
		return;
	}
	$id = (int) $row['id'];
	echo '<form class="cc-actform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_mail_retry"><input type="hidden" name="mail_id" value="' . $id . '"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">';
	wp_nonce_field( 'fge_cc_mail_retry_' . $id );
	echo '<button type="submit" class="cc-btn" onclick="return confirm(' . esc_attr( wp_json_encode( 'Mail noch einmal an ' . $row['recipient'] . ' senden?' ) ) . ')">Erneut senden</button></form>';
}
