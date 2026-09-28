<?php
/**
 * Einmal-Seed für die Live-Anfrage FG-26-166 (solutio GmbH & Co. KG, Golf-Teamevent
 * Stuttgart, 08.10.2026 bei GolfKultur Stuttgart).
 *
 * Julius hat die Anfrage im September außerhalb des Systems bearbeitet (Outlook,
 * Telefon). Dieser Seed trägt den Stand nach: drei Plätze in der Pipeline mit
 * Antworten und Preisen, GolfKultur gewählt, Positionen, Ort, Ablauf, Leistungen,
 * Chronik. Er sendet keine Mail, bestätigt keinen Termin und ändert keinen Status.
 * Danach klickt Julius im Cockpit „Termin bestätigen" (Angebot geht automatisch mit
 * PDF an den Kunden) und sagt den übrigen Plätzen per Knopf ab.
 *
 * Läuft option-gated beim ersten Request nach dem Deploy, nur wenn die Anfrage
 * existiert (lokal also nicht). Lokal: wp firmengolf seed-fg-26-166 --request=<ID>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', static function (): void {
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || get_option( 'fge_seed_fg_26_166' ) || ! function_exists( 'fge_request_by_ref' ) ) {
		return;
	}
	$req = fge_request_by_ref( 'FG-26-166' );
	if ( $req <= 0 ) {
		return; // Gate nicht verbrennen, solange die Anfrage fehlt.
	}
	update_option( 'fge_seed_fg_26_166', '1', true ); // Gate zuerst, dann arbeiten.
	fge_seed_fg166_run( $req );
}, 25 );

/** Stammdaten-Platz für den Seed: per Verzeichnis (Name plus PLZ) oder manuell. */
function fge_seed_fg166_partner( array $d ): int {
	$pid = fge_stammdaten_find_by_meta( '_fge_stammdaten_manual_key', (string) $d['key'] );
	if ( $pid <= 0 && function_exists( 'fge_verzeichnis_find_by_name_plz' ) ) {
		$row = fge_verzeichnis_find_by_name_plz( (string) $d['name'], (string) $d['plz'] );
		if ( $row ) {
			$pid = fge_stammdaten_ensure_from_verzeichnis_row( $row, [ 'email' => (string) $d['email'] ] );
		}
	}
	if ( $pid <= 0 ) {
		$pid = fge_stammdaten_match_existing_partner( (string) $d['name'], (string) $d['plz'] );
	}
	if ( $pid <= 0 ) {
		$pid = fge_stammdaten_create( [
			'name'    => (string) $d['name'],
			'type'    => 'course',
			'city'    => (string) $d['city'],
			'plz'     => (string) $d['plz'],
			'street'  => (string) $d['street'],
			'state'   => 'Baden-Württemberg',
			'website' => (string) $d['website'],
			'phone'   => (string) $d['phone'],
			'email'   => (string) $d['email'],
			'source'  => 'manuell',
			'keys'    => [ '_fge_stammdaten_manual_key' => (string) $d['key'] ],
		] );
	}
	if ( $pid <= 0 ) {
		return 0;
	}
	update_post_meta( $pid, '_fge_stammdaten_manual_key', (string) $d['key'] );
	// Kontaktdaten nur ergänzen, nie überschreiben, falls der Platz schon Partner ist.
	foreach ( [
		'_fge_main_contact_email' => (string) $d['email'],
		'_fge_main_contact_phone' => (string) $d['phone'],
		'_fge_main_contact_name'  => (string) ( $d['contact'] ?? '' ),
		'_fge_website_url'        => (string) $d['website'],
		'_fge_street'             => (string) $d['street'],
		'_fge_postal_code'        => (string) $d['plz'],
		'_fge_city'               => (string) $d['city'],
	] as $k => $v ) {
		if ( '' !== $v && '' === trim( (string) get_post_meta( $pid, $k, true ) ) ) {
			update_post_meta( $pid, $k, $v );
		}
	}
	if ( ! empty( $d['operator'] ) && '' === (string) get_post_meta( $pid, '_fge_legal_operator_name', true ) ) {
		update_post_meta( $pid, '_fge_legal_operator_name', (string) $d['operator'] );
	}
	return $pid;
}

/** Pipeline-Zeile einer Anfrage zu einem Partner, ggf. neu anlegen. */
function fge_seed_fg166_venue( int $req, int $pid ): int {
	$vid = fge_venue_add( $req, $pid );
	if ( $vid > 0 ) {
		return $vid;
	}
	foreach ( fge_venues_get( $req ) as $v ) {
		if ( (int) $v['partner_id'] === $pid ) {
			return (int) $v['id'];
		}
	}
	return 0;
}

/** Item einer Pipeline-Zeile per Label setzen. */
function fge_seed_fg166_item( int $vid, string $label, array $data ): void {
	foreach ( fge_venue_items_get( $vid ) as $it ) {
		if ( mb_strtolower( trim( (string) $it['label'] ) ) === mb_strtolower( $label ) ) {
			fge_venue_item_set( $vid, (string) $it['wish_key'], $data );
			return;
		}
	}
}

/** Chronik-Eintrag mit echtem Datum. */
function fge_seed_fg166_note( int $req, string $type, string $text, string $when ): void {
	global $wpdb;
	if ( ! function_exists( 'fge_activity_add' ) || ! function_exists( 'fge_activity_table' ) ) {
		return;
	}
	fge_activity_add( $req, $type, $text );
	$id = (int) $wpdb->insert_id;
	if ( $id > 0 ) {
		$wpdb->update( fge_activity_table(), [ 'created_at' => $when ], [ 'id' => $id ] );
	}
}

function fge_seed_fg166_run( int $req ): array {
	$out = [ 'request' => $req ];
	if ( ! function_exists( 'fge_venue_add' ) || ! function_exists( 'fge_stammdaten_create' ) ) {
		$out['error'] = 'Pipeline oder Stammdaten-Modul fehlt.';
		return $out;
	}

	// 1) Voraussetzungen der Anfrage (nur füllen, wenn leer).
	$wishes = (array) get_post_meta( $req, '_fge_wishes_platz', true );
	if ( ! array_filter( $wishes ) ) {
		update_post_meta( $req, '_fge_wishes_platz', [ 'Abendessen', 'Getränkepauschale' ] );
	}
	if ( (int) get_post_meta( $req, '_fge_expected_participants', true ) <= 0 ) {
		update_post_meta( $req, '_fge_expected_participants', 7 );
	}
	if ( '' === trim( (string) get_post_meta( $req, '_fge_geo_anchor', true ) ) ) {
		update_post_meta( $req, '_fge_geo_anchor', '71093 Weil im Schönbuch' ); // Breitenstein liegt im Schönbuch, nicht in Sachsen-Anhalt.
	}

	// 2) Plätze.
	$golfkultur = fge_seed_fg166_partner( [
		'key' => 'golfkultur-stuttgart|70329', 'name' => 'GolfKultur Stuttgart', 'city' => 'Stuttgart', 'plz' => '70329',
		'street' => 'Steinprügel 2', 'website' => 'https://www.golfkultur-stuttgart.de', 'phone' => '0711 90110847',
		'email' => 'info@golfkultur-stuttgart.de', 'contact' => 'Lola Forner', 'operator' => 'SportKultur Stuttgart e.V.',
	] );
	$schoenbuch = fge_seed_fg166_partner( [
		'key' => 'gc-schoenbuch|71088', 'name' => 'Golfclub Schönbuch', 'city' => 'Holzgerlingen', 'plz' => '71088',
		'street' => 'Schaichhof', 'website' => 'https://www.gc-schoenbuch.de', 'phone' => '07157 67966',
		'email' => 'info@gc-schoenbuch.de', 'contact' => '',
	] );
	$hammetweil = fge_seed_fg166_partner( [
		'key' => 'gc-hammetweil|72654', 'name' => 'Golf Club Hammetweil', 'city' => 'Neckartenzlingen', 'plz' => '72654',
		'street' => 'Hammetweil 10', 'website' => 'https://www.gc-hammetweil.de', 'phone' => '07127 97430',
		'email' => 'info@gc-hammetweil.de', 'contact' => 'Petra Will',
	] );
	$out['partners'] = compact( 'golfkultur', 'schoenbuch', 'hammetweil' );
	if ( $golfkultur <= 0 ) {
		$out['error'] = 'GolfKultur konnte nicht angelegt werden.';
		return $out;
	}

	// 3) Pipeline.
	$v_gk = fge_seed_fg166_venue( $req, $golfkultur );
	$v_sb = $schoenbuch > 0 ? fge_seed_fg166_venue( $req, $schoenbuch ) : 0;
	$v_hw = $hammetweil > 0 ? fge_seed_fg166_venue( $req, $hammetweil ) : 0;
	$out['venues'] = [ $v_gk, $v_sb, $v_hw ];

	fge_venue_update( $v_gk, [
		'status'      => 'zugesagt',
		'channel'     => 'telefon',
		'asked_at'    => '2026-09-22 10:00:00',
		'replied_at'  => '2026-09-25 11:00:00',
		'price'       => 33.00,
		'price_basis' => 'person',
		'price_gross' => 1,
		'note'        => 'Do 08.10.2026 telefonisch fixiert (Kollege von Lola Forner, Golfbüro). Grundlagenkurs mit Pro ca. 2 Std. inkl. Leihschläger und Bälle, kleiner Abschlusswettbewerb bestätigt. Grillabend mit Grillsemmeln (Bratwurst, Grillkäse, Schweinesteak), Getränke nach Verbrauch, Abrechnung im Nachgang. Restaurant hat an dem Tag geschlossen. Tel. 0711 90110847.',
	] );
	fge_seed_fg166_item( $v_gk, 'Abendessen', [ 'available' => 1, 'price' => 0, 'price_basis' => 'person', 'price_gross' => 1, 'note' => 'Grillabend nach Verbrauch, Richtwert 15 bis 25 € p.P.' ] );
	fge_seed_fg166_item( $v_gk, 'Getränkepauschale', [ 'available' => 0, 'price' => 0, 'note' => 'Keine Pauschale, Getränke nach Verbrauch' ] );

	if ( $v_sb > 0 ) {
		fge_venue_update( $v_sb, [
			'status'     => 'zugesagt',
			'channel'    => 'mail',
			'asked_at'   => '2026-09-19 16:00:00',
			'replied_at' => '2026-09-23 12:00:00',
			'price'      => 0,
			'note'       => 'Per Mail angefragt (info@gc-schoenbuch.de, Frau Holzkämper) für 30.09. und 08.10., Varianten Kurs sowie Kurs plus 9 Loch. Abendessen über Restaurant Buca19 (Antonio Scollo, Holzgerlingen). Angebot liegt in Outlook, Preise hier noch nicht übernommen.',
		] );
	}
	if ( $v_hw > 0 ) {
		fge_venue_update( $v_hw, [
			'status'   => 'angefragt',
			'channel'  => 'mail',
			'asked_at' => '2026-09-19 16:30:00',
			'note'     => 'Per Mail angefragt (info@gc-hammetweil.de, Clubmanagerin Petra Will). Nicht weiterverfolgt, bekommt die Absage aus dem System.',
		] );
	}

	// 4) Wahl: setzt Platz, Einkauf, Ort und Positionen aus der Pipeline.
	fge_venue_choose( $req, $v_gk );

	// 5) Angebotstexte und Positionen exakt so, wie sie zum Kunden sollen.
	update_post_meta( $req, '_fge_offer_base_override', 35.00 );
	update_post_meta( $req, '_fge_offer_base_override_unit', 'person' );
	update_post_meta( $req, '_fge_partner_cost', 33.00 );
	update_post_meta( $req, '_fge_partner_cost_basis', 'person' );
	update_post_meta( $req, '_fge_partner_cost_gross', 1 );
	update_post_meta( $req, '_fge_offer_location', 'GolfKultur Stuttgart, Hedelfingen' );
	update_post_meta( $req, '_fge_offer_schedule', "16:00 Uhr Ankommen und Begrüßung\n16:15 Uhr Grundlagenkurs mit dem Pro, ca. 2 Stunden\nca. 18:15 Uhr kleiner Abschlusswettbewerb\nab ca. 18:45 Uhr Grillabend auf der Anlage" );
	update_post_meta( $req, '_fge_offer_includes', "Grundlagenkurs Golf mit Pro, ca. 2 Stunden\nLeihschläger und Bälle\nKleiner Abschlusswettbewerb" );
	$margin = function_exists( 'fge_xs_default_margin' ) ? fge_xs_default_margin() : 20.0;
	update_post_meta( $req, '_fge_extra_services', [
		[
			'id' => 1, 'label' => 'Lockerer Grillabend auf der Anlage', 'cost' => 0.0, 'cost_gross' => 1, 'basis' => 'verbrauch', 'margin' => $margin,
			'organizer' => 'platz', 'provider_name' => 'GolfKultur Stuttgart', 'provider_email' => 'info@golfkultur-stuttgart.de', 'partner_id' => $golfkultur,
			'wish' => 'Abendessen',
			'note' => "Grillsemmeln (Bratwurst, Grillkäse, Schweinesteak) und Getränke.\nDas Restaurant auf der Anlage hat an dem Tag geschlossen, deshalb kein gedeckter Tisch, sondern ein lockerer Abend im Stehen.",
			'guide' => '15 bis 25 € p.P.', 'status' => 'angeboten',
		],
		[
			'id' => 2, 'label' => 'Getränkepauschale', 'cost' => 0.0, 'cost_gross' => 1, 'basis' => 'person', 'margin' => $margin,
			'organizer' => 'platz', 'provider_name' => 'GolfKultur Stuttgart', 'provider_email' => 'info@golfkultur-stuttgart.de', 'partner_id' => $golfkultur,
			'wish' => 'Getränkepauschale', 'note' => 'Getränke werden nach Verbrauch abgerechnet', 'guide' => '', 'status' => 'nicht_moeglich',
		],
	] );

	// 6) Cron-Schutz: keine Termin-Erinnerung an GolfKultur, es gab nie eine Einladung.
	if ( function_exists( 'fge_partner_ensure_owner_contact' ) ) {
		$owner = fge_partner_ensure_owner_contact( $golfkultur );
		if ( $owner > 0 ) {
			update_post_meta( $req, '_fge_reminders_sent', [ $owner ] );
		}
	}
	update_post_meta( $req, '_fge_termin_invited_at', time() );

	// 7) Buca19 als Dienstleister (Catering), ohne Mail: soll keine Auftragsmail bekommen.
	$buca = get_posts( [ 'post_type' => 'firmengolf_provider', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'title' => 'Restaurant Buca19', 'suppress_filters' => true ] );
	if ( ! $buca ) {
		$bid = wp_insert_post( [ 'post_type' => 'firmengolf_provider', 'post_status' => 'publish', 'post_title' => 'Restaurant Buca19' ] );
		if ( $bid && ! is_wp_error( $bid ) ) {
			update_post_meta( $bid, '_fge_provider_type', 'catering' );
			update_post_meta( $bid, '_fge_provider_contact', 'Antonio Scollo' );
			update_post_meta( $bid, '_fge_provider_region', 'Holzgerlingen' );
			update_post_meta( $bid, '_fge_provider_note', 'Abendessen-Option für Gruppen am GC Schönbuch (FG-26-166). Kontakt über Julius, keine Mail hinterlegt.' );
			$out['buca19'] = (int) $bid;
		}
	}

	// 8) Chronik aus Outlook und Telefon.
	fge_seed_fg166_note( $req, 'note', 'Anfrage per Mail an GC Schönbuch (Frau Holzkämper) und Golf Club Hammetweil: Kurs ab 16 Uhr, Varianten nur Kurs oder Kurs plus 9 Loch, Abendessen mit Getränkepauschale, Termine 30.09. oder 08.10.', '2026-09-19 16:30:00' );
	fge_seed_fg166_note( $req, 'call', 'GolfKultur Stuttgart (Lola Forner) telefonisch angefragt: beide Termine frei, Grundlagenkurs 33 € p.P., Grillbuffet und Getränke nach Verbrauch.', '2026-09-22 10:00:00' );
	fge_seed_fg166_note( $req, 'note', 'GC Schönbuch hat per Mail geantwortet, Abendessen über Restaurant Buca19 (Antonio Scollo). Bleibt als Alternative in der Hinterhand.', '2026-09-23 12:00:00' );
	fge_seed_fg166_note( $req, 'call', 'GolfKultur: Kollege von Lola hat Do 08.10. fest reserviert. 33 € brutto p.P. Grillbuffet sind Grillsemmeln (Bratwurst, Grillkäse, Steak) und Getränke, keine Salate, Restaurant an dem Tag geschlossen. Abschlusswettbewerb bestätigt.', '2026-09-25 11:00:00' );
	fge_seed_fg166_note( $req, 'system', 'Stand aus Outlook und Telefon ins System übernommen (Seed 28.09.). Nächster Schritt: Do 08.10. bestätigen, dann geht das Angebot an Mark Thomas. Danach Schönbuch und Hammetweil per Knopf absagen.', '2026-09-28 18:00:00' );

	$out['ok'] = true;
	return $out;
}
