<?php
/**
 * Dienstleister: Pros, Shuttle, Catering, Fotografen.
 *
 * Sie existierten bisher nur als Position in einer einzelnen Anfrage, mit Mail
 * und Einkaufspreis, aber ohne Verzeichnis. Für jedes Event musste man neu
 * suchen. Hier entsteht ein Pool je Region mit Preisen.
 *
 * Als Post-Type statt eigener Tabelle, weil Liste, Suche und Metafelder von
 * WordPress geschenkt kommen und der Notausgang ins WordPress-Backend erhalten
 * bleibt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', static function (): void {
	register_post_type( 'firmengolf_provider', [
		'labels'       => [
			'name'          => 'Dienstleister',
			'singular_name' => 'Dienstleister',
			'add_new_item'  => 'Dienstleister anlegen',
			'edit_item'     => 'Dienstleister bearbeiten',
		],
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-groups',
		'supports'     => [ 'title' ],
		'capability_type' => 'post',
	] );
}, 9 );

/** Arten von Dienstleistern. */
function fge_provider_types(): array {
	return [
		'pro'      => 'Golflehrer / Pro',
		'shuttle'  => 'Shuttle',
		'catering' => 'Catering',
		'foto'     => 'Fotograf',
		'technik'  => 'Technik',
		'sonstige' => 'Sonstiges',
	];
}

/** Felder eines Dienstleisters: Meta-Key ohne Präfix => [Label, Typ]. */
function fge_provider_fields(): array {
	return [
		'provider_type'    => [ 'Art', 'select' ],
		'provider_region'  => [ 'Region', 'text' ],
		'provider_contact' => [ 'Ansprechpartner', 'text' ],
		'provider_email'   => [ 'E-Mail', 'email' ],
		'provider_phone'   => [ 'Telefon', 'text' ],
		'provider_price'   => [ 'Standardpreis', 'text' ],
		'provider_basis'   => [ 'Basis', 'basis' ],
		'provider_gross'   => [ 'brutto oder netto', 'gross' ],
		'provider_note'    => [ 'Notiz', 'textarea' ],
	];
}

/** Alle Werte eines Dienstleisters. */
function fge_provider_values( int $id ): array {
	$v = [];
	foreach ( array_keys( fge_provider_fields() ) as $k ) {
		$v[ $k ] = (string) get_post_meta( $id, '_fge_' . $k, true );
	}
	return $v;
}

/** Preis eines Dienstleisters als Satz, leer wenn keiner hinterlegt ist. */
function fge_provider_price_text( int $id ): string {
	$p = (float) get_post_meta( $id, '_fge_provider_price', true );
	if ( $p <= 0 ) {
		return '';
	}
	return number_format_i18n( $p, 2 ) . ' € '
		. ( '0' === (string) get_post_meta( $id, '_fge_provider_gross', true ) ? 'netto' : 'brutto' ) . ' '
		. ( 'pauschal' === (string) get_post_meta( $id, '_fge_provider_basis', true ) ? 'pauschal' : 'p.P.' );
}

/** Alle Dienstleister, nach Art und Name. */
function fge_providers_all(): array {
	$ids = get_posts( [
		'post_type'      => 'firmengolf_provider',
		'post_status'    => [ 'publish', 'draft' ],
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	] );
	if ( $ids ) {
		_prime_post_caches( $ids, false, true );
	}
	return $ids;
}

/** Mailadressen aller Dienstleister, für die Vervollständigung in Schritt 2. */
function fge_provider_emails(): array {
	$out = [];
	foreach ( fge_providers_all() as $id ) {
		$mail = (string) get_post_meta( $id, '_fge_provider_email', true );
		if ( is_email( $mail ) ) {
			$out[ $mail ] = get_the_title( $id ) . ( fge_provider_price_text( (int) $id ) ? ', ' . fge_provider_price_text( (int) $id ) : '' );
		}
	}
	return $out;
}

// ── Speichern ────────────────────────────────────────────────────────────────

add_action( 'admin_post_fge_cc_provider_save', static function (): void {
	if ( ! fge_cc_can() ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	$id = absint( $_POST['provider_id'] ?? 0 );
	check_admin_referer( 'fge_cc_provider_save_' . $id );

	$name = sanitize_text_field( wp_unslash( $_POST['provider_name'] ?? '' ) );
	if ( '' === trim( $name ) ) {
		wp_safe_redirect( fge_cc_url( 'dienstleister', [ 'msg' => 'noname' ] ) );
		exit;
	}

	if ( $id > 0 && 'firmengolf_provider' === get_post_type( $id ) ) {
		wp_update_post( [ 'ID' => $id, 'post_title' => $name ] );
	} else {
		$id = (int) wp_insert_post( [
			'post_type'   => 'firmengolf_provider',
			'post_status' => 'publish',
			'post_title'  => $name,
		] );
	}
	if ( $id <= 0 ) {
		wp_safe_redirect( fge_cc_url( 'dienstleister', [ 'msg' => 'failed' ] ) );
		exit;
	}

	foreach ( fge_provider_fields() as $key => [ , $type ] ) {
		$raw = wp_unslash( $_POST[ $key ] ?? '' );
		switch ( $type ) {
			case 'textarea':
				$val = sanitize_textarea_field( $raw );
				break;
			case 'email':
				$val = sanitize_email( $raw );
				break;
			case 'text':
				$val = sanitize_text_field( $raw );
				break;
			default:
				$val = sanitize_key( $raw );
		}
		if ( 'provider_price' === $key ) {
			$val = function_exists( 'fge_xs_parse_num' ) ? (string) fge_xs_parse_num( sanitize_text_field( $raw ) ) : sanitize_text_field( $raw );
		}
		if ( '' !== trim( (string) $val ) ) {
			update_post_meta( $id, '_fge_' . $key, $val );
		} else {
			delete_post_meta( $id, '_fge_' . $key );
		}
	}

	wp_safe_redirect( fge_cc_url( 'dienstleister', [ 'msg' => 'saved' ] ) );
	exit;
} );

add_action( 'admin_post_fge_cc_provider_delete', static function (): void {
	if ( ! fge_cc_can() ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	$id = absint( $_POST['provider_id'] ?? 0 );
	check_admin_referer( 'fge_cc_provider_delete_' . $id );
	if ( $id > 0 && 'firmengolf_provider' === get_post_type( $id ) ) {
		wp_trash_post( $id );
	}
	wp_safe_redirect( fge_cc_url( 'dienstleister', [ 'msg' => 'deleted' ] ) );
	exit;
} );

// ── Seite ────────────────────────────────────────────────────────────────────

function fge_cc_page_providers(): void {
	$msgs = [
		'saved'   => [ 'ok', 'Dienstleister gespeichert.' ],
		'deleted' => [ 'ok', 'Dienstleister entfernt.' ],
		'noname'  => [ 'err', 'Ohne Namen geht es nicht.' ],
		'failed'  => [ 'err', 'Konnte nicht gespeichert werden.' ],
	];
	$msg = sanitize_key( wp_unslash( $_GET['msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $msgs[ $msg ] ) ) {
		echo '<p class="cc-msg cc-msg--' . esc_attr( $msgs[ $msg ][0] ) . '">' . esc_html( $msgs[ $msg ][1] ) . '</p>';
	}

	$edit = absint( $_GET['edit'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$ids  = fge_providers_all();

	if ( ! $ids && 0 === $edit ) {
		echo '<p class="cc-muted">Noch kein Dienstleister hinterlegt. Trag die Pros, Shuttle-Anbieter, Caterer und Fotografen ein, mit denen du arbeitest, dann stehen sie bei der nächsten Anfrage mit Preis bereit.</p>';
	}

	// Gruppiert nach Art, damit man beim Suchen nicht scrollen muss.
	$by_type = [];
	foreach ( $ids as $id ) {
		$type = (string) get_post_meta( $id, '_fge_provider_type', true ) ?: 'sonstige';
		$by_type[ $type ][] = (int) $id;
	}

	foreach ( fge_provider_types() as $type => $label ) {
		if ( empty( $by_type[ $type ] ) ) {
			continue;
		}
		echo '<section class="cc-card"><h2>' . esc_html( $label ) . ' <span class="cc-muted">' . (int) count( $by_type[ $type ] ) . '</span></h2>';
		foreach ( $by_type[ $type ] as $id ) {
			fge_cc_provider_row( $id, $edit === $id );
		}
		echo '</section>';
	}

	// Anlegen
	echo '<section class="cc-card"><h2>Dienstleister anlegen</h2>';
	fge_cc_provider_form( 0 );
	echo '</section>';
}

function fge_cc_provider_row( int $id, bool $editing ): void {
	if ( $editing ) {
		fge_cc_provider_form( $id );
		return;
	}
	$v     = fge_provider_values( $id );
	$price = fge_provider_price_text( $id );

	echo '<div class="cc-dir-row">';
	echo '<div class="cc-dir-head">';
	echo '<span class="cc-dir-name">' . esc_html( get_the_title( $id ) ) . '</span>';
	if ( '' !== $v['provider_region'] ) {
		echo '<span class="cc-muted">' . esc_html( $v['provider_region'] ) . '</span>';
	}
	if ( '' !== $price ) {
		echo '<span class="cc-dir-price">' . esc_html( $price ) . '</span>';
	}
	echo '</div>';

	echo '<p class="cc-contact-links">';
	if ( '' !== $v['provider_contact'] ) {
		echo '<span class="cc-muted">' . esc_html( $v['provider_contact'] ) . '</span>';
	}
	if ( '' !== $v['provider_phone'] ) {
		echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $v['provider_phone'] ) ) . '">' . esc_html( $v['provider_phone'] ) . '</a>';
	}
	if ( is_email( $v['provider_email'] ) ) {
		echo '<a href="mailto:' . esc_attr( $v['provider_email'] ) . '">' . esc_html( $v['provider_email'] ) . '</a>';
	} else {
		echo '<span class="cc-warn">Keine Mailadresse, dieser Dienstleister bekommt keine automatische Auftragsbestätigung.</span>';
	}
	echo '</p>';

	if ( '' !== $v['provider_note'] ) {
		echo '<p class="cc-muted">' . esc_html( $v['provider_note'] ) . '</p>';
	}
	echo '<p class="cc-dir-links"><a href="' . esc_url( fge_cc_url( 'dienstleister', [ 'edit' => $id ] ) ) . '">Bearbeiten</a></p>';
	echo '</div>';
}

function fge_cc_provider_form( int $id ): void {
	$v    = $id > 0 ? fge_provider_values( $id ) : array_fill_keys( array_keys( fge_provider_fields() ), '' );
	$name = $id > 0 ? get_the_title( $id ) : '';

	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_provider_save">';
	echo '<input type="hidden" name="provider_id" value="' . (int) $id . '">';
	wp_nonce_field( 'fge_cc_provider_save_' . $id );

	echo '<div class="cc-fields">';
	echo '<label class="cc-field"><span>Name</span><input type="text" name="provider_name" value="' . esc_attr( $name ) . '" placeholder="z. B. Golfschule Weidenhof" required></label>';

	foreach ( fge_provider_fields() as $key => [ $label, $type ] ) {
		echo '<label class="cc-field' . ( 'textarea' === $type ? ' cc-field--wide' : '' ) . '"><span>' . esc_html( $label ) . '</span>';
		switch ( $type ) {
			case 'select':
				echo '<select name="' . esc_attr( $key ) . '">';
				foreach ( fge_provider_types() as $tk => $tl ) {
					echo '<option value="' . esc_attr( $tk ) . '"' . selected( $v[ $key ], $tk, false ) . '>' . esc_html( $tl ) . '</option>';
				}
				echo '</select>';
				break;
			case 'basis':
				echo '<select name="' . esc_attr( $key ) . '">';
				echo '<option value="person"' . selected( $v[ $key ], 'person', false ) . '>pro Person</option>';
				echo '<option value="pauschal"' . selected( $v[ $key ], 'pauschal', false ) . '>pauschal</option>';
				echo '</select>';
				break;
			case 'gross':
				echo '<select name="' . esc_attr( $key ) . '">';
				echo '<option value="1"' . selected( $v[ $key ], '1', false ) . '>brutto</option>';
				echo '<option value="0"' . selected( $v[ $key ], '0', false ) . '>netto</option>';
				echo '</select>';
				break;
			case 'textarea':
				echo '<textarea name="' . esc_attr( $key ) . '" rows="2">' . esc_textarea( $v[ $key ] ) . '</textarea>';
				break;
			default:
				$ph = 'provider_price' === $key ? 'z. B. 120,00' : '';
				$val = 'provider_price' === $key && '' !== $v[ $key ]
					? number_format( (float) $v[ $key ], 2, ',', '.' )
					: $v[ $key ];
				echo '<input type="' . ( 'email' === $type ? 'email' : 'text' ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $ph ) . '">';
		}
		echo '</label>';
	}
	echo '</div>';

	echo '<p><button type="submit" class="cc-btn cc-btn--primary">Speichern</button>';
	if ( $id > 0 ) {
		echo ' <a class="cc-btn" href="' . esc_url( fge_cc_url( 'dienstleister' ) ) . '">Abbrechen</a>';
	}
	echo '</p></form>';

	if ( $id > 0 ) {
		echo '<form class="cc-actform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="fge_cc_provider_delete">';
		echo '<input type="hidden" name="provider_id" value="' . (int) $id . '">';
		wp_nonce_field( 'fge_cc_provider_delete_' . $id );
		echo '<button type="submit" class="cc-btn" onclick="return confirm(\'Diesen Dienstleister entfernen?\')">Entfernen</button>';
		echo '</form>';
	}
}
