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
