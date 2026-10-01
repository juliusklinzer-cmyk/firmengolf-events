<?php
/**
 * Partner contacts — the people for a partner beyond the portal-account holder.
 *
 * Model (confirmed 2026-06-08): only the main contact (manager) has a real WP
 * account. All further contacts (the 31 roles) get NO account — just name + email
 * + a permission flag + a magic-link token, so they can be informed or respond to
 * date proposals (Terminabstimmung) without logging in.
 *
 * Permission levels: 'notify' (info-/status-mails) | 'vote' (notify + can confirm
 * proposed dates via signed link). Each role has a sensible default, overridable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_CONTACTS_DB_VERSION = '1.0.0';

/** Fully-qualified table name. */
function fge_contacts_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'fge_partner_contacts';
}

/** Create/upgrade the contacts table (version-gated, safe to call on every init). */
function fge_contacts_install(): void {
	if ( get_option( 'fge_contacts_db_version' ) === FGE_CONTACTS_DB_VERSION ) {
		return;
	}
	global $wpdb;
	$table   = fge_contacts_table();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		partner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		name VARCHAR(190) NOT NULL DEFAULT '',
		email VARCHAR(190) NOT NULL DEFAULT '',
		role VARCHAR(80) NOT NULL DEFAULT '',
		permission VARCHAR(20) NOT NULL DEFAULT 'notify',
		token CHAR(64) NOT NULL DEFAULT '',
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		created_at DATETIME NULL DEFAULT NULL,
		updated_at DATETIME NULL DEFAULT NULL,
		PRIMARY KEY (id),
		KEY partner_id (partner_id),
		KEY email (email),
		KEY token (token)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'fge_contacts_db_version', FGE_CONTACTS_DB_VERSION );
}
add_action( 'init', 'fge_contacts_install' );

/** Valid permission levels. */
function fge_contact_permissions(): array {
	return [
		'notify' => 'Nur informieren',
		'vote'   => 'Terminabstimmung',
	];
}

/**
 * Default permission for a role. Operational/coordination roles default to 'vote'
 * (they decide whether a date works); governance/admin/info roles to 'notify'.
 */
function fge_contact_role_default_permission( string $role ): string {
	$vote_roles = [
		'Clubmanager', 'Geschäftsführer', 'Sekretariat', 'Rezeption', 'Eventmanager',
		'Gastronomiebetreiber', 'Restaurantleitung', 'Spielleitung', 'Turnierleitung',
		'Sportwart', 'Starter', 'Head Pro', 'Golfprofessional', 'Golflehrer', 'Golfschule',
		'Course Manager', 'Head Greenkeeper', 'Caddiemaster', 'Cart Verantwortlicher',
	];
	return in_array( $role, $vote_roles, true ) ? 'vote' : 'notify';
}

/** Normalise a permission value, falling back to the role default. */
function fge_contact_normalize_permission( string $permission, string $role = '' ): string {
	$permission = strtolower( trim( $permission ) );
	if ( isset( fge_contact_permissions()[ $permission ] ) ) {
		return $permission;
	}
	return fge_contact_role_default_permission( $role );
}

/** Generate a unique magic-link token. */
function fge_contact_generate_token(): string {
	return bin2hex( random_bytes( 24 ) ); // 48 hex chars
}

/** All contacts for a partner (active only unless $include_inactive). */
function fge_contacts_get( int $partner_id, bool $include_inactive = false ): array {
	global $wpdb;
	$table = fge_contacts_table();
	$where = $include_inactive ? '' : " AND status = 'active'";
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE partner_id = %d{$where} ORDER BY id ASC", $partner_id );
	return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
}

/**
 * Stellt sicher, dass der Golfplatz selbst (Hauptkontakt) als vollwertiger
 * Abstimmer in der Kontakt-Tabelle vorhanden ist, und liefert dessen id (0 ohne
 * Hauptkontakt-Mail). Idempotent: Die id wird in `_fge_owner_contact_id` gemerkt,
 * Name/E-Mail werden mit dem Hauptkontakt des Platzes synchron gehalten.
 *
 * Die Zeile bekommt `user_id` = Portal-Account und Rolle „Hauptkontakt", damit
 * sie aus den „weitere Ansprechpartner"-Listen (user_id === 0) herausfällt, bei
 * der Terminabstimmung (vote) aber immer mitzählt.
 */
function fge_partner_ensure_owner_contact( int $partner_id ): int {
	if ( $partner_id <= 0 ) {
		return 0;
	}
	$email = sanitize_email( (string) get_post_meta( $partner_id, '_fge_main_contact_email', true ) );
	$name  = (string) get_post_meta( $partner_id, '_fge_main_contact_name', true );
	if ( '' === $name ) {
		$name = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: 'Hauptkontakt';
	}

	$stored = (int) get_post_meta( $partner_id, '_fge_owner_contact_id', true );
	if ( $stored > 0 ) {
		$c = fge_contact_get( $stored );
		if ( $c && 'active' === ( $c['status'] ?? '' ) ) {
			// Mit dem Hauptkontakt des Platzes synchron halten.
			if ( '' !== $email && ( $c['email'] !== $email || $c['name'] !== $name ) ) {
				fge_contact_update( $stored, [ 'email' => $email, 'name' => $name ] );
			}
			return $stored;
		}
	}

	if ( '' === $email || ! is_email( $email ) ) {
		return 0; // Ohne Adresse kann der Platz nicht per Mail abstimmen.
	}

	global $wpdb;
	$now = current_time( 'mysql' );
	$ok  = $wpdb->insert(
		fge_contacts_table(),
		[
			'partner_id' => $partner_id,
			'user_id'    => (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true ),
			'name'       => $name,
			'email'      => $email,
			'role'       => 'Hauptkontakt',
			'permission' => 'vote',
			'token'      => fge_contact_generate_token(),
			'status'     => 'active',
			'created_at' => $now,
			'updated_at' => $now,
		],
		[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
	);
	if ( ! $ok ) {
		return 0;
	}
	$id = (int) $wpdb->insert_id;
	update_post_meta( $partner_id, '_fge_owner_contact_id', $id );
	return $id;
}

/** Single contact by id. */
function fge_contact_get( int $id ): ?array {
	global $wpdb;
	$table = fge_contacts_table();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

/** Single contact by magic-link token. */
function fge_contact_get_by_token( string $token ): ?array {
	if ( '' === $token ) {
		return null;
	}
	global $wpdb;
	$table = fge_contacts_table();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s AND status = 'active'", $token ), ARRAY_A );
	return $row ?: null;
}

/**
 * Insert a contact. $data: name, email, role, permission (optional), user_id (optional).
 * Returns the new id, or 0 on failure (e.g. missing email).
 */
function fge_contact_add( int $partner_id, array $data ): int {
	$email = sanitize_email( $data['email'] ?? '' );
	$name  = sanitize_text_field( $data['name'] ?? '' );
	if ( $partner_id <= 0 || '' === $email || ! is_email( $email ) ) {
		return 0;
	}
	$role = sanitize_text_field( $data['role'] ?? '' );
	if ( '' !== $role && ! in_array( $role, fge_catalog_contact_roles(), true ) ) {
		$role = 'Sonstige';
	}
	global $wpdb;
	$now = current_time( 'mysql' );
	$ok = $wpdb->insert(
		fge_contacts_table(),
		[
			'partner_id' => $partner_id,
			'user_id'    => absint( $data['user_id'] ?? 0 ),
			'name'       => $name,
			'email'      => $email,
			'role'       => $role,
			'permission' => fge_contact_normalize_permission( (string) ( $data['permission'] ?? '' ), $role ),
			'token'      => fge_contact_generate_token(),
			'status'     => 'active',
			'created_at' => $now,
			'updated_at' => $now,
		],
		[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
	);
	return $ok ? (int) $wpdb->insert_id : 0;
}

/** Update mutable fields of a contact. $data may contain name, email, role, permission, status. */
function fge_contact_update( int $id, array $data ): bool {
	$contact = fge_contact_get( $id );
	if ( ! $contact ) {
		return false;
	}
	$fields = [];
	$format = [];
	if ( array_key_exists( 'name', $data ) ) {
		$fields['name'] = sanitize_text_field( $data['name'] );
		$format[]       = '%s';
	}
	if ( array_key_exists( 'email', $data ) ) {
		$email = sanitize_email( $data['email'] );
		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}
		$fields['email'] = $email;
		$format[]        = '%s';
	}
	$role = $contact['role'];
	if ( array_key_exists( 'role', $data ) ) {
		$role = sanitize_text_field( $data['role'] );
		if ( '' !== $role && ! in_array( $role, fge_catalog_contact_roles(), true ) ) {
			$role = 'Sonstige';
		}
		$fields['role'] = $role;
		$format[]       = '%s';
	}
	if ( array_key_exists( 'permission', $data ) ) {
		$fields['permission'] = fge_contact_normalize_permission( (string) $data['permission'], $role );
		$format[]             = '%s';
	}
	if ( array_key_exists( 'status', $data ) ) {
		$fields['status'] = in_array( $data['status'], [ 'active', 'inactive' ], true ) ? $data['status'] : 'active';
		$format[]         = '%s';
	}
	if ( empty( $fields ) ) {
		return true;
	}
	$fields['updated_at'] = current_time( 'mysql' );
	$format[]             = '%s';
	global $wpdb;
	return false !== $wpdb->update( fge_contacts_table(), $fields, [ 'id' => $id ], $format, [ '%d' ] );
}

/** Soft-delete a contact (status=inactive); keeps it for audit/links. */
function fge_contact_delete( int $id, bool $hard = false ): bool {
	global $wpdb;
	if ( $hard ) {
		return false !== $wpdb->delete( fge_contacts_table(), [ 'id' => $id ], [ '%d' ] );
	}
	return fge_contact_update( $id, [ 'status' => 'inactive' ] );
}

// ── Einmal-Übernahme Kontakte Bestandspartner (01.10.2026) ────────────────────

/**
 * Die Golfclub-Partner aus der Website-Übernahme hatten im Control Center keine
 * Kontaktdaten („Partner ohne Kontaktdaten"). Quelle: Julius' Outlook-Korrespondenz
 * (Signaturen, Verträge) und HubSpot, Stand 01.10.2026. Füllt nur leere Felder und
 * legt den Ansprechpartner an, wenn er noch nicht als Kontakt existiert. Nur
 * veröffentlichte, echte Partner (keine Stammdaten, kein Muster-Platz).
 */
function fge_partner_contacts_fill_2026_10_data(): array {
	return [
		'Jersbek'         => [ 'Nadja Nissen', 'Sekretariat', 'n.nissen@golfclub-jersbek.de', '04532 20950' ],
		'OPEN.9'          => [ 'Lea Schneider', 'Eventmanager', 'lea.schneider@open9.de', '08123 989280' ],
		'Eurach'          => [ 'Andreas Röhrl', 'Clubmanager', 'a.roehrl@eurach.de', '+49 8801 915830' ],
		'Bayerwald'       => [ 'Albert Harz', 'Vorstand', 'sport@gc-bayerwald.de', '08581 1040' ],
		'Chieming'        => [ 'Jannik Heine', 'Clubmanager', 'j.heine@golfchieming.de', '08669 87330' ],
		'Erding'          => [ 'Marc Ober', 'Clubmanager', 'mo@golf-erding.de', '0160 94870549' ],
		'Escheburg'       => [ 'Nina Cockayne', 'Sekretariat', 'nina.cockayne@gc-escheburg.de', '04152 83204' ],
		'Grambek'         => [ 'Karina Czech', 'Sekretariat', 'k.czech@gcgrambek.de', '04542 841474' ],
		'Ahrensburg'      => [ 'Tobias Wilde', 'Clubmanager', 'clubmanager@golfclub-ahrensburg.de', '04102 51309' ],
		'Lutzhorn'        => [ 'Justin Eller-Hughes', 'Sonstige', 'ellerhughes97@gmail.com', '+49 178 6914426' ],
		'Mangfalltal'     => [ 'Markus Steinle', 'Geschäftsführer', 'm.steinle@gc-mangfalltal.de', '+49 8063 6300' ],
		'Maxlrain'        => [ 'Alexandra Sturm', 'Clubmanager', 'alexandra.sturm@golfclub-maxlrain.de', '+49 8061 1403' ],
		'Igling'          => [ 'Manuel Argentari', 'Präsident', 'praesident@golfclub-igling.de', '08248 1893' ],
		'Stiftland'       => [ 'Andreas Graf', 'Clubmanager', 'clubmanager@gc-stiftland.de', '09638 1271' ],
		'Wiggensbach'     => [ 'Ralf Schwarz', 'Vorstand', 'rs@nohcp.de', '+49 8370 93073' ],
		'Gut Kaden'       => [ 'Wolfgang Mych', 'Geschäftsführer', 'wolfgang.mych@gutkaden.de', '+49 4193 99290' ],
		'Holledau'        => [ 'Dietmar Strunz', 'Clubmanager', 'ds@golfclubholledau.de', '08756 96010' ],
		'Bergkramerhof'   => [ 'Sebastian Hochbaum', 'Sonstige', 'sh@leadgolf.de', '+49 157 72737843' ],
		'Schwäbisch Hall' => [ 'Ingo Bücher', 'Präsident', 'i.buecher@incomma.com', '07907 8190' ],
		'Weidenhof'       => [ 'Sandy Voß', 'Sonstige', 'info@golfpark-weidenhof.de', '04101 511830' ],
	];
}

add_action( 'init', static function (): void {
	if ( get_option( 'fge_partner_contacts_fill_2026_10' ) || ! function_exists( 'fge_contact_add' ) ) {
		return;
	}
	update_option( 'fge_partner_contacts_fill_2026_10', [ 'running' => time() ], false );
	global $wpdb;
	$ids = get_posts( [
		'post_type'      => 'firmengolf_partner',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );
	$log = [];
	foreach ( $ids as $pid ) {
		if ( ( function_exists( 'fge_partner_is_stammdaten' ) && fge_partner_is_stammdaten( $pid ) )
			|| ( function_exists( 'fge_is_demo_partner' ) && fge_is_demo_partner( $pid ) ) ) {
			continue;
		}
		$title = (string) get_the_title( $pid );
		foreach ( fge_partner_contacts_fill_2026_10_data() as $needle => [ $name, $role, $email, $phone ] ) {
			if ( false === mb_stripos( $title, $needle ) ) {
				continue;
			}
			$set = [];
			foreach ( [ '_fge_main_contact_name' => $name, '_fge_main_contact_email' => $email, '_fge_main_contact_phone' => $phone ] as $k => $v ) {
				if ( '' === trim( (string) get_post_meta( $pid, $k, true ) ) ) {
					update_post_meta( $pid, $k, $v );
					$set[] = $k;
				}
			}
			$exists = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . fge_contacts_table() . " WHERE partner_id = %d AND email = %s AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$pid,
				$email
			) );
			if ( 0 === $exists && fge_contact_add( $pid, [ 'name' => $name, 'email' => $email, 'role' => $role, 'permission' => 'vote' ] ) > 0 ) {
				$set[] = 'kontakt';
			}
			$log[] = $pid . ' ' . $title . ': ' . ( $set ? implode( ', ', $set ) : 'schon vollständig' );
			break;
		}
	}
	update_option( 'fge_partner_contacts_fill_2026_10', [ 'done' => time(), 'log' => $log ], false );
}, 45 );
