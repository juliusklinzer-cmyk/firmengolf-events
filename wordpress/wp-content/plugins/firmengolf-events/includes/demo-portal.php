<?php
/**
 * Musterumgebung fürs Partnerportal (Julius, 2026-07-22).
 *
 * Admins sehen unter /partnerportal/ nicht mehr den "du bist Admin"-Block,
 * sondern das volle Portal eines Muster-Golfplatzes mit befüllten Daten
 * (Profil, Events, Anfragen in allen Stadien, KPIs). Zum Vorführen in
 * Partner-Meetings, ohne sich als echter Platz anmelden zu müssen.
 *
 * Sicherheits-Prinzip: ALLE Demo-Events und -Anfragen sind Entwürfe (draft).
 * Das Portal zeigt drafts an, sämtliche öffentlichen Abfragen (Eventliste,
 * Startseite, Stadtseiten, Sitemap) holen nur publish. Die Muster-Platzseite
 * wird nie öffentlich, weil dafür mindestens ein öffentliches Event nötig ist.
 * Nachfass-Crons überspringen den Muster-Partner (kein Mailversand auf Demo-Daten).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Erkennung ─────────────────────────────────────────────────────────────────

/** Post-ID des Muster-Partners, 0 wenn (noch) keiner existiert. */
function fge_demo_partner_id(): int {
	$pid = (int) get_option( 'fge_demo_partner_id', 0 );
	return ( $pid > 0 && get_post_type( $pid ) === 'firmengolf_partner' ) ? $pid : 0;
}

function fge_is_demo_partner( int $partner_id ): bool {
	return $partner_id > 0 && $partner_id === fge_demo_partner_id();
}

/** Gehört diese Anfrage zur Musterumgebung? (Crons müssen sie überspringen.) */
function fge_is_demo_request( int $request_id ): bool {
	return fge_is_demo_partner( (int) get_post_meta( $request_id, '_fge_assigned_partner_id', true ) );
}

// ── Seeder / Reset ────────────────────────────────────────────────────────────

/** Bis zu $n Bild-IDs aus der Mediathek (ID-agnostisch, läuft lokal wie live). */
function fge_demo_media_ids( int $n ): array {
	return array_map( 'intval', get_posts( [
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'numberposts'    => $n,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
	] ) );
}

/**
 * Musterumgebung anlegen bzw. auf den Ausgangszustand zurücksetzen.
 * Löscht alle Demo-Events/-Anfragen und seedet frisch; der Partner-Post
 * selbst bleibt (gleiche ID), sein Profil wird überschrieben.
 */
function fge_demo_seed(): int {
	$pid = fge_demo_partner_id();

	// ── Partner anlegen/zurücksetzen ──
	$partner_args = [
		'post_type'    => 'firmengolf_partner',
		'post_status'  => 'publish',
		'post_title'   => 'Golfclub Sonnenhöhe (Muster)',
		'post_content' => 'Der Golfclub Sonnenhöhe liegt eingebettet in sanfte Hügel, 25 Minuten vom Stadtzentrum. '
			. 'Die 18-Loch-Anlage bietet eine großzügige Driving Range, ein modernes Clubhaus mit Seminarraum '
			. 'für bis zu 40 Personen und eine Sonnenterrasse mit Blick über das 18. Grün. Unser Küchenteam '
			. 'begleitet Firmenevents vom Empfangskaffee bis zum Abend-Grill.',
	];
	if ( $pid > 0 ) {
		$partner_args['ID'] = $pid;
		wp_update_post( $partner_args );
	} else {
		$pid = (int) wp_insert_post( $partner_args );
		if ( $pid <= 0 ) {
			return 0;
		}
		update_option( 'fge_demo_partner_id', $pid, false );
	}

	$imgs     = fge_demo_media_ids( 7 );
	$internal = function_exists( 'fge_company_internal_email' ) ? fge_company_internal_email() : get_option( 'admin_email' );

	$partner_meta = [
		'_fge_partner_status'           => 'aktiv',
		'_fge_partner_portal_enabled'   => 1,
		'_fge_public_golfclub_name'     => 'Golfclub Sonnenhöhe',
		'_fge_public_short_description' => 'Ihr Platz für Firmenevents: 18 Loch, Seminarraum, Sonnenterrasse und ein eingespieltes Eventteam.',
		'_fge_city'                     => 'Musterstadt',
		'_fge_federal_state'            => 'Bayern',
		'_fge_postal_code'              => '80000',
		'_fge_directions_text'          => 'Großzügige Anlage, Parkplätze direkt am Clubhaus, barrierearmer Zugang. S-Bahn Musterstadt plus 5 Minuten Taxi.',
		'_fge_main_contact_name'        => 'Max Mustermann',
		'_fge_main_contact_role'        => 'Clubmanager',
		'_fge_main_contact_email'       => $internal,
		'_fge_views_count'              => 418,
		'_fge_infra'                    => [ 'range', 'short', 'indoor' ],
		'_fge_partner_since'            => date_i18n( 'Y' ),
	];
	if ( ! empty( $imgs ) ) {
		$partner_meta['_fge_hero_image_attachment_id'] = $imgs[0];
		$partner_meta['_fge_gallery_attachment_ids']   = implode( ',', array_slice( $imgs, 0, 6 ) );
	}
	foreach ( $partner_meta as $k => $v ) {
		update_post_meta( $pid, $k, $v );
	}

	// ── Alte Demo-Events/-Anfragen restlos entfernen ──
	foreach ( [ 'firmengolf_event', 'firmengolf_request' ] as $pt ) {
		$old = get_posts( [
			'post_type'   => $pt,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $pid, 'type' => 'NUMERIC' ] ],
		] );
		foreach ( $old as $oid ) {
			wp_delete_post( (int) $oid, true );
		}
	}

	// ── Demo-Events (drafts: Portal zeigt sie, öffentlich unsichtbar) ──
	$events = [
		[ 'Golf-Teamevent Schnuppertag', 'teamevent', 'Halbtags (4 Std.)', 'Pro zeigt die ersten Schläge, danach Team-Wettbewerb auf der Range und Putt-Turnier. Kein Vorwissen nötig.', 'ab 89 € p.P.', 8, 40, 176 ],
		[ 'Platzreife-Intensivkurs für Teams', 'platzreife', '2 Tage', 'In zwei Tagen zur Platzreife: kompaktes Training mit unseren Pros, Prüfung inklusive. Schweißt Teams zusammen.', 'ab 349 € p.P.', 6, 16, 98 ],
		[ 'Firmenturnier mit Abendprogramm', 'turnier', 'Ganztags', 'Ihr eigenes Turnier: Kanonenstart, Halfway-Verpflegung, Siegerehrung auf der Terrasse und BBQ zum Ausklang.', 'ab 139 € p.P.', 20, 80, 87 ],
		[ 'Afterwork-Golf mit Grillabend', 'afterwork', 'Abends (3 Std.)', 'Feierabend auf der Range: lockeres Training mit Pro, danach Grill und Getränke mit Blick aufs Grün.', 'ab 59 € p.P.', 8, 30, 57 ],
	];
	foreach ( $events as $e ) {
		$eid = (int) wp_insert_post( [
			'post_type'   => 'firmengolf_event',
			'post_status' => 'draft',
			'post_title'  => $e[0],
		] );
		if ( $eid <= 0 ) {
			continue;
		}
		update_post_meta( $eid, '_fge_assigned_partner_id', $pid );
		update_post_meta( $eid, '_fge_event_status', 'freigegeben' );
		update_post_meta( $eid, '_fge_event_type', $e[1] );
		update_post_meta( $eid, '_fge_duration', $e[2] );
		update_post_meta( $eid, '_fge_card_description', $e[3] );
		update_post_meta( $eid, '_fge_public_price_label', $e[4] );
		update_post_meta( $eid, '_fge_participants_min', $e[5] );
		update_post_meta( $eid, '_fge_participants_max', $e[6] );
		update_post_meta( $eid, '_fge_views_count', $e[7] );
	}

	// ── Demo-Anfragen in allen Stadien (drafts) ──
	// Termine relativ zu heute, damit die Demo nie veraltet.
	$fmt = static function ( string $mod ): string {
		return date_i18n( 'd.m.Y', strtotime( $mod ) );
	};
	$requests = [
		// [Firma, Vorname, Nachname, pax, Typ, Status, post_date, Wunschtermine, final_idx, offer_status, accepted_mod, price_total, Nachricht]
		[ 'Muster GmbH', 'Anna', 'Beispiel', 18, 'Teamevent', 'neu', '-1 day', [ $fmt( '+21 days' ), $fmt( '+28 days' ) ], 0, '', '', 0, 'Wir suchen einen Teamtag für unsere Abteilung, überwiegend Anfänger. Gerne mit Verpflegung.' ],
		[ 'Beispiel & Partner Steuerberatung', 'Jonas', 'Muster', 12, 'Afterwork', 'neu', '-3 hours', [ $fmt( '+14 days' ) ], 0, '', '', 0, 'Afterwork für unser Team, Donnerstag bevorzugt. Leihschläger wären wichtig.' ],
		[ 'TechWerk Software AG', 'Lena', 'Probst', 24, 'Teamevent', 'verfuegbarkeit_wird_geprueft', '-2 days', [ $fmt( '+35 days' ), $fmt( '+42 days' ) ], 0, '', '', 0, 'Sommer-Teamevent, gerne mit Putting-Turnier und BBQ im Anschluss.' ],
		[ 'Kanzlei Sonnfeld', 'David', 'Berger', 16, 'Firmenturnier', 'bestaetigt', '-6 days', [ $fmt( '+20 days' ) ], 1, '', '', 0, 'Kleines Mandantenturnier mit anschließendem Abendessen.' ],
		[ 'Stadtwerke Musterstadt', 'Sarah', 'Klein', 30, 'Firmenturnier', 'angebot_versendet', '-4 days', [ $fmt( '+30 days' ) ], 1, 'pending', '', 4980, 'Sommerfest als Golfturnier, inkl. Halfway und Siegerehrung.' ],
		[ 'Nordlicht Consulting', 'Tim', 'Weber', 14, 'Teamevent', 'angebot_angenommen', '-12 days', [ $fmt( '+9 days' ) ], 1, 'accepted', '-2 days', 2140, 'Teamtag mit Schnupperkurs, vegetarische Optionen beim Lunch bitte.' ],
		[ 'Praxisgruppe Vital', 'Miriam', 'Hoffmann', 10, 'Platzreife', 'angebot_angenommen', '-6 weeks', [ $fmt( '-10 days' ) ], 1, 'accepted', '-5 weeks', 3490, 'Platzreife-Kurs für unser Ärzteteam über zwei Tage.' ],
		[ 'Baugruppe Hansen', 'Felix', 'Hansen', 22, 'Teamevent', 'abgeschlossen', '-3 months', [ $fmt( '-2 months' ) ], 1, 'accepted', '-2 months', 3120, 'Betriebsausflug mit Golf und Grillabend.' ],
		[ 'Logistik Brand KG', 'Julia', 'Brand', 15, 'Afterwork', 'abgeschlossen', '-4 months', [ $fmt( '-3 months' ) ], 1, 'accepted', '-3 months', 1180, 'Afterwork-Reihe, erster Termin als Test.' ],
		[ 'MedienHaus 7', 'Chris', 'Neumann', 20, 'Firmenturnier', 'verloren', '-2 months', [ $fmt( '-1 month' ) ], 0, '', '', 0, 'Turnier-Anfrage, Termin hat leider nicht gepasst.' ],
	];
	foreach ( $requests as $r ) {
		$rid = (int) wp_insert_post( [
			'post_type'   => 'firmengolf_request',
			'post_status' => 'draft',
			'post_title'  => 'Anfrage ' . $r[0],
			'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( $r[6] ) ),
		] );
		if ( $rid <= 0 ) {
			continue;
		}
		update_post_meta( $rid, '_fge_assigned_partner_id', $pid );
		update_post_meta( $rid, '_fge_company_name', $r[0] );
		update_post_meta( $rid, '_fge_contact_first_name', $r[1] );
		update_post_meta( $rid, '_fge_contact_last_name', $r[2] );
		update_post_meta( $rid, '_fge_contact_email', $internal );
		update_post_meta( $rid, '_fge_contact_phone', '089 000000' );
		update_post_meta( $rid, '_fge_expected_participants', $r[3] );
		update_post_meta( $rid, '_fge_request_type', $r[4] );
		update_post_meta( $rid, '_fge_request_status', $r[5] );
		update_post_meta( $rid, '_fge_message', $r[12] );
		update_post_meta( $rid, '_fge_group_experience', 'Überwiegend Anfänger' );
		foreach ( $r[7] as $i => $label ) {
			update_post_meta( $rid, '_fge_preferred_date_' . ( $i + 1 ), $label );
		}
		if ( $r[8] > 0 ) {
			update_post_meta( $rid, '_fge_final_date_index', $r[8] );
		}
		if ( '' !== $r[9] ) {
			update_post_meta( $rid, '_fge_offer_status', $r[9] );
			update_post_meta( $rid, '_fge_offer_sent', 1 );
			update_post_meta( $rid, '_fge_offer_sent_at', strtotime( $r[6] ) + DAY_IN_SECONDS );
			update_post_meta( $rid, '_fge_offer_snapshot', [
				'price_total' => (float) $r[11],
				'company'     => $r[0],
				'demo'        => 1,
			] );
		}
		if ( '' !== $r[10] ) {
			update_post_meta( $rid, '_fge_offer_accepted_at', gmdate( 'Y-m-d H:i:s', strtotime( $r[10] ) ) );
		}
	}

	return $pid;
}

// ── Admin-Aktion: anlegen / zurücksetzen ──────────────────────────────────────

add_action( 'admin_post_fge_demo_seed', static function (): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_demo_seed' );
	fge_demo_seed();
	$portal = function_exists( 'fge_portal_page_url' ) ? fge_portal_page_url() : home_url( '/partnerportal/' );
	wp_safe_redirect( add_query_arg( 'demo_reset', '1', $portal ) );
	exit;
} );

/** Nonce-URL für den Anlegen/Zurücksetzen-Button. */
function fge_demo_seed_url(): string {
	return wp_nonce_url( admin_url( 'admin-post.php?action=fge_demo_seed' ), 'fge_demo_seed' );
}
