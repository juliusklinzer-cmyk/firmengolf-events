<?php
/**
 * Control Center: Bausteine des Grundgerüsts (Design vom 07.10.2026).
 *
 * Globale Suche in der Seitenleiste und der Bestätigungsdialog. Beide lesen
 * nur vorhandene Daten: Anfragen, Partner, Events, Partnercodes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Globale Suche ────────────────────────────────────────────────────────────

add_action( 'wp_ajax_fge_cc_search', static function (): void {
	if ( ! fge_cc_can() || ! check_ajax_referer( 'fge_cc_search', 'nonce', false ) ) {
		wp_send_json_error( [], 403 );
	}
	$q = trim( sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ) );
	wp_send_json_success( mb_strlen( $q ) < 2 ? [] : fge_cc_search( $q ) );
} );

/**
 * Treffer über alle Bereiche, höchstens zwölf.
 *
 * @return array<int, array{kind:string,title:string,sub:string,url:string}>
 */
function fge_cc_search( string $q ): array {
	global $wpdb;
	$out  = [];
	$like = '%' . $wpdb->esc_like( $q ) . '%';

	// Anfragen: FG-Nummer steht im Titel, Firma, Kontakt und Mail in Metas.
	$req_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
		  AND m.meta_key IN ('_fge_company_name','_fge_contact_first_name','_fge_contact_last_name','_fge_contact_email')
		 WHERE p.post_type = 'firmengolf_request' AND p.post_status IN ('publish','draft','pending','private')
		  AND ( p.post_title LIKE %s OR m.meta_value LIKE %s )
		 ORDER BY p.post_date DESC LIMIT 6",
		$like,
		$like
	) );
	foreach ( $req_ids as $id ) {
		$id      = (int) $id;
		$company = (string) get_post_meta( $id, '_fge_company_name', true );
		$contact = trim( get_post_meta( $id, '_fge_contact_first_name', true ) . ' ' . get_post_meta( $id, '_fge_contact_last_name', true ) );
		$out[]   = [
			'kind'  => 'Anfrage',
			'title' => '' !== $company ? $company : ( '' !== $contact ? $contact : fge_request_number( $id ) ),
			'sub'   => fge_request_number( $id ) . ( '' !== $contact ? ' · ' . $contact : '' ),
			'url'   => fge_cc_request_url( $id ),
		];
	}

	// Partner (ohne Stammdaten-Verzeichnis, das hat seine eigene Suche).
	$partner_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('_fge_city','_fge_postal_code')
		 WHERE p.post_type = 'firmengolf_partner' AND p.post_status IN ('publish','draft','pending')
		  AND ( p.post_title LIKE %s OR m.meta_value LIKE %s )
		 ORDER BY p.post_title ASC LIMIT 20",
		$like,
		$like
	) );
	$n = 0;
	foreach ( $partner_ids as $id ) {
		$id = (int) $id;
		if ( function_exists( 'fge_partner_is_stammdaten' ) && fge_partner_is_stammdaten( $id ) ) {
			continue;
		}
		$out[] = [
			'kind'  => 'Partner',
			'title' => get_the_title( $id ),
			'sub'   => trim( get_post_meta( $id, '_fge_postal_code', true ) . ' ' . get_post_meta( $id, '_fge_city', true ) ),
			'url'   => fge_cc_url( 'verzeichnis', [ 's' => get_the_title( $id ) ] ),
		];
		if ( ++$n >= 4 ) {
			break;
		}
	}

	// Events: Titel.
	$event_ids = get_posts( [
		'post_type'      => 'firmengolf_event',
		'post_status'    => [ 'publish', 'draft', 'pending' ],
		's'              => $q,
		'posts_per_page' => 4,
		'fields'         => 'ids',
	] );
	foreach ( $event_ids as $id ) {
		$partner = (int) get_post_meta( $id, '_fge_assigned_partner_id', true );
		$out[]   = [
			'kind'  => 'Event',
			'title' => get_the_title( $id ),
			'sub'   => $partner > 0 ? get_the_title( $partner ) : '',
			'url'   => (string) get_edit_post_link( $id, 'raw' ),
		];
	}

	// Partnercodes: Code und Inhaber.
	if ( defined( 'FGE_PC_POST_TYPE' ) ) {
		$pc_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('_fge_pc_code','_fge_pc_contact_name')
			 WHERE p.post_type = %s AND p.post_status IN ('publish','draft')
			  AND ( p.post_title LIKE %s OR m.meta_value LIKE %s ) LIMIT 3",
			FGE_PC_POST_TYPE,
			$like,
			$like
		) );
		foreach ( $pc_ids as $id ) {
			$id    = (int) $id;
			$out[] = [
				'kind'  => 'Partnercode',
				'title' => (string) get_post_meta( $id, '_fge_pc_code', true ),
				'sub'   => get_the_title( $id ),
				'url'   => (string) get_edit_post_link( $id, 'raw' ),
			];
		}
	}

	return array_slice( $out, 0, 12 );
}

// ── Bestätigungsdialog ───────────────────────────────────────────────────────

/**
 * Leere Dialog-Hülle, gefüllt von fge-cc.js.
 *
 * Vor jedem Knopf mit Rückfrage zeigt der Dialog die Frage und, falls die
 * Aktion Mails auslöst, die Folgen-Vorschau mit echten Empfängern. Ohne
 * JavaScript greift weiter das einfache confirm() am Knopf.
 */
function fge_cc_dialog_shell(): void {
	echo '<dialog class="cc-dialog" data-cc-dialog aria-labelledby="cc-dialog-title">';
	echo '<form method="dialog" class="cc-dialog-in">';
	echo '<h2 id="cc-dialog-title" data-cc-dialog-title></h2>';
	echo '<div class="cc-dialog-body" data-cc-dialog-body></div>';
	echo '<div class="cc-dialog-foot"><button class="cc-btn cc-btn--text" value="cancel">Abbrechen</button>';
	echo '<button class="cc-btn cc-btn--primary" value="ok" data-cc-dialog-ok>Bestätigen</button></div>';
	echo '</form></dialog>';
}
