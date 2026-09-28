<?php
/**
 * Städte-Rollout 2026-08: Selbstplaner-Events für die 22 neuen City-Landingpages.
 *
 * Klont die 7 München-Master (Teamevent, Schnupperkurs, Firmenturnier, Platzreife,
 * Kundenevent, Workshop, After-Work) zur Laufzeit in jede neue Stadt, wie beim
 * ersten Regional-Rollout: Inhalte 1:1, nur Titel, Slug, Stadt/Region/Ort und
 * Geo-Koordinaten werden getauscht. Läuft option-gegated nach dem Deploy
 * (Live-System nur per FTPS, kein WP-CLI) und ist idempotent: existiert der
 * Ziel-Slug schon, wird nichts angelegt.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Neue Städte des Rollouts 2026-08: slug => [Anzeigename, Ortsangabe, lat, lng]. */
function fge_cityseed26_cities(): array {
	return [
		'augsburg'               => [ 'Augsburg', 'Raum Augsburg', '48.371', '10.898' ],
		'bonn'                   => [ 'Bonn', 'Raum Bonn', '50.737', '7.098' ],
		'bremen'                 => [ 'Bremen', 'Raum Bremen', '53.079', '8.802' ],
		'dortmund'               => [ 'Dortmund', 'Raum Dortmund', '51.514', '7.465' ],
		'dresden'                => [ 'Dresden', 'Raum Dresden', '51.050', '13.737' ],
		'erlangen'               => [ 'Erlangen', 'Raum Erlangen', '49.590', '11.012' ],
		'essen'                  => [ 'Essen', 'Raum Essen', '51.456', '7.012' ],
		'garmisch-partenkirchen' => [ 'Garmisch-Partenkirchen', 'Raum Garmisch-Partenkirchen', '47.492', '11.096' ],
		'ingolstadt'             => [ 'Ingolstadt', 'Raum Ingolstadt', '48.766', '11.426' ],
		'itzehoe'                => [ 'Itzehoe', 'Raum Itzehoe', '53.925', '9.516' ],
		'karlsruhe'              => [ 'Karlsruhe', 'Raum Karlsruhe', '49.007', '8.404' ],
		'kiel'                   => [ 'Kiel', 'Raum Kiel', '54.323', '10.123' ],
		'landshut'               => [ 'Landshut', 'Raum Landshut', '48.537', '12.151' ],
		'leipzig'                => [ 'Leipzig', 'Raum Leipzig', '51.340', '12.373' ],
		'luebeck'                => [ 'Lübeck', 'Raum Lübeck', '53.866', '10.687' ],
		'lueneburg'              => [ 'Lüneburg', 'Raum Lüneburg', '53.246', '10.412' ],
		'mannheim'               => [ 'Mannheim', 'Raum Mannheim', '49.488', '8.466' ],
		'penzberg'               => [ 'Penzberg', 'Raum Penzberg', '47.752', '11.377' ],
		'regensburg'             => [ 'Regensburg', 'Raum Regensburg', '49.013', '12.102' ],
		'rosenheim'              => [ 'Rosenheim', 'Raum Rosenheim', '47.856', '12.129' ],
		'ulm'                    => [ 'Ulm', 'Raum Ulm', '48.401', '9.988' ],
		'wuerzburg'              => [ 'Würzburg', 'Raum Würzburg', '49.791', '9.953' ],
	];
}

/** Slugs der München-Master, die in jede neue Stadt geklont werden. */
function fge_cityseed26_master_slugs(): array {
	return [
		'golf-teamevent-in-muenchen',
		'golf-schnupperkurs-fuer-teams-in-muenchen',
		'firmen-golfturnier-in-muenchen',
		'platzreifekurs-in-muenchen',
		'kundenevent-auf-dem-golfplatz-in-muenchen',
		'workshop-auf-dem-golfplatz-in-muenchen',
		'after-work-golf-in-muenchen',
	];
}

/** Klont einen München-Master in eine Stadt (Inhalt 1:1, Ortsdaten getauscht). */
function fge_cityseed26_clone_master( WP_Post $master, string $slug, array $city ): void {
	list( $name, $venue, $lat, $lng ) = $city;
	$post_name = str_replace( '-in-muenchen', '-in-' . $slug, $master->post_name );
	if ( get_page_by_path( $post_name, OBJECT, 'firmengolf_event' ) ) {
		return; // schon angelegt
	}

	$id = wp_insert_post( [
		'post_type'    => 'firmengolf_event',
		'post_status'  => $master->post_status,
		'post_title'   => str_replace( 'München', $name, $master->post_title ),
		'post_name'    => $post_name,
		'post_content' => $master->post_content,
		'post_excerpt' => $master->post_excerpt,
	] );
	if ( ! $id || is_wp_error( $id ) ) {
		return;
	}

	$skip = [ '_fge_views_count', '_fge_requests_count', '_edit_lock', '_edit_last' ];
	foreach ( get_post_meta( $master->ID ) as $key => $values ) {
		if ( in_array( $key, $skip, true ) ) {
			continue;
		}
		update_post_meta( $id, $key, maybe_unserialize( $values[0] ) );
	}
	$overrides = [
		'_fge_city'           => $name,
		'_fge_region'         => $name,
		'_fge_event_location' => $venue,
		'_fge_geo_lat'        => $lat,
		'_fge_geo_lng'        => $lng,
	];
	foreach ( $overrides as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	// Der After-Work-Master hat seinen Netto-Betrag verloren (Stand 2026-08-20,
	// alle älteren Regional-Klone tragen 49.17): Loch beim Klonen wieder stopfen,
	// sonst zeigt der Klon "Auf Anfrage" statt des kalibrierten Preises.
	if ( 'after_work_golf' === get_post_meta( $id, '_fge_event_type', true ) && '' === (string) get_post_meta( $id, '_fge_price_amount', true ) ) {
		update_post_meta( $id, '_fge_price_amount', '49.17' );
	}
	if ( function_exists( 'fge_event_price_label' ) ) {
		$pr = fge_event_pricing( (int) $id );
		update_post_meta( $id, '_fge_sale_price_net', $pr['gross'] );
		update_post_meta( $id, '_fge_public_price_label', fge_event_price_label( (int) $id ) );
	}
}

add_action( 'init', static function () {
	if ( get_option( 'fge_city_seed_2026_08' ) ) {
		return;
	}
	update_option( 'fge_city_seed_2026_08', '1', true );

	$masters = [];
	foreach ( fge_cityseed26_master_slugs() as $master_slug ) {
		$master = get_page_by_path( $master_slug, OBJECT, 'firmengolf_event' );
		if ( $master instanceof WP_Post ) {
			$masters[] = $master;
		}
	}
	foreach ( fge_cityseed26_cities() as $slug => $city ) {
		foreach ( $masters as $master ) {
			fge_cityseed26_clone_master( $master, $slug, $city );
		}
	}
}, 21 );

/* ════════════════════════════════════════════════════════════════════════════
   Turnier-Rollout 2026-08-21 (T2): die drei Turnier-Varianten in allen Städten.

   Julius' Varianten-Trio (21.08.): 18-Loch-Firmenturnier (150 € p.P., bis 120),
   9-Loch-Firmenturnier ohne Half-Way (80 € p.P.) und Firmenturnier ohne
   Platzreife auf dem Kurzplatz (80 € p.P.). Die zwei neuen Master existieren
   bisher nur in der lokalen DB → dieser Seed legt sie aus dem Code an (Live
   hat kein WP-CLI, nur FTPS + Option-Gate), zieht die 18-Loch-Master-Preise
   nach und klont beide neue Master in JEDE Stadt, in der ein
   firmen-golfturnier-Klon existiert (deckt Rollout 1 UND 2 ab).
   ════════════════════════════════════════════════════════════════════════════ */

/** Volldaten der zwei neuen Turnier-Master (München), für die Live-Anlage. */
function fge_cityseed26_t2_masters(): array {
	$addons = "Live-Scoring\nMeetingraum (ab 2 Std.)\nAbholservice\nGebrandete Abschläge\nFotograf";
	return [
		[
			'post_title' => '9-Loch-Firmenturnier in München',
			'post_name'  => '9-loch-firmenturnier-in-muenchen',
			'meta'       => [
				'_fge_event_type'       => 'firmen_golfturnier',
				'_fge_event_status'     => 'freigegeben',
				'_fge_event_location'   => 'Raum München',
				'_fge_region'           => 'München',
				'_fge_city'             => 'München',
				'_fge_participants_min' => '12',
				'_fge_participants_max' => '80',
				'_fge_duration'         => 'Halbtag · ca. 4 bis 5 Std.',
				'_fge_card_description' => 'Das kompakte Firmenturnier: 9 Löcher im Turniermodus, zügig gespielt ohne Half-Way-Pause, danach Siegerehrung und Barbecue. Bei Buchung als Paket ist alles inklusive, anpassbar auf euer Turnier.',
				'_fge_price_mode'       => 'gesamt',
				'_fge_price_basis'      => 'person',
				'_fge_price_amount'     => '66.50',
				'_fge_event_dayflow'    => "Anmeldung & Scorekarten\nAnmeldung am Clubhaus, Ausgabe der Scorekarten und des Startgeschenks.\n\nEinweisung & Flight-Einteilung\nBegrüßung, Regeln und faire Flights, auch Scramble, damit alle Level mitspielen.\n\n9-Loch-Turnier\nEine kompakte Turnierrunde, zügig durchgespielt mit Betreuung am Platz; jeder Flight startet organisiert.\n\nSiegerehrung & Barbecue\nAuswertung, Preise und ein gemeinsames Barbecue zum Ausklang.",
				'_fge_event_includes'   => "Startunterlagen & Scorekarten\nStartgeschenk\nGreenfee & Platznutzung\nStartlisten & Flight-Einteilung\nSiegerehrung & Preise\nBarbecue\nOrganisation & ein Ansprechpartner",
				'_fge_event_addons'     => $addons,
				'_fge_geo_lat'          => '48.137',
				'_fge_geo_lng'          => '11.575',
			],
		],
		[
			'post_title' => 'Firmenturnier ohne Platzreife in München',
			'post_name'  => 'firmenturnier-ohne-platzreife-in-muenchen',
			'meta'       => [
				'_fge_event_type'       => 'firmen_golfturnier',
				'_fge_event_status'     => 'freigegeben',
				'_fge_event_location'   => 'Raum München',
				'_fge_region'           => 'München',
				'_fge_city'             => 'München',
				'_fge_participants_min' => '12',
				'_fge_participants_max' => '60',
				'_fge_duration'         => 'Halbtag · ca. 5 bis 6 Std.',
				'_fge_card_description' => 'Das Firmenturnier für Teams ohne Golferfahrung, ganz ohne Platzreife: erst eine Stunde Einführung mit dem Golf-Pro, dann Turnier auf dem Kurzplatz über 3 bis 6 Löcher, zum Ausklang Siegerehrung und Dinner. Bei Buchung als Paket ist alles inklusive, anpassbar auf euer Turnier.',
				'_fge_price_mode'       => 'gesamt',
				'_fge_price_basis'      => 'person',
				'_fge_price_amount'     => '66.50',
				'_fge_event_dayflow'    => "Anmeldung & Einführung mit dem Pro\nAnmeldung am Clubhaus, danach eine Stunde Einführung in den Golfsport mit einem Golf-Pro. Schläger und Bälle werden gestellt.\n\nEinweisung & Flight-Einteilung\nBegrüßung, Regeln und faire Flights, damit alle ohne Vorerfahrung mitspielen.\n\nKurzplatz-Turnier\nTurnier über 3 bis 6 Löcher auf dem Kurzplatz, ideal für Einsteigerinnen und Einsteiger, mit Betreuung am Platz.\n\nSiegerehrung & Dinner\nAuswertung, Preise und ein gemeinsames Abendessen zum Ausklang.",
				'_fge_event_includes'   => "Einführung mit Golf-Pro (1 Std.)\nLeihschläger & Bälle\nStartunterlagen & Scorekarten\nStartgeschenk\nPlatznutzung Kurzplatz\nStartlisten & Flight-Einteilung\nSiegerehrung & Preise\nDinner\nOrganisation & ein Ansprechpartner",
				'_fge_event_addons'     => $addons,
				'_fge_geo_lat'          => '48.137',
				'_fge_geo_lng'          => '11.575',
			],
		],
	];
}

/** Legt einen T2-Master an, falls er fehlt (idempotent per Slug). */
function fge_cityseed26_t2_ensure_master( array $def ): ?WP_Post {
	$existing = get_page_by_path( $def['post_name'], OBJECT, 'firmengolf_event' );
	if ( $existing instanceof WP_Post ) {
		return $existing;
	}
	$id = wp_insert_post( [
		'post_type'   => 'firmengolf_event',
		'post_status' => 'publish',
		'post_title'  => $def['post_title'],
		'post_name'   => $def['post_name'],
	] );
	if ( ! $id || is_wp_error( $id ) ) {
		return null;
	}
	foreach ( $def['meta'] as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	if ( function_exists( 'fge_event_price_label' ) ) {
		$pr = fge_event_pricing( (int) $id );
		update_post_meta( $id, '_fge_sale_price_net', $pr['gross'] );
		update_post_meta( $id, '_fge_public_price_label', fge_event_price_label( (int) $id ) );
	}
	return get_post( $id );
}

/**
 * Zieht einen Stadt-Klon inhaltlich auf den Master-Stand (alle _fge_-Metas
 * AUSSER Ortsdaten), Preis-Label wird neu berechnet. Für die 18-Loch-Klone
 * aus Rollout 1, die noch den alten Preisstand (157.50/80) tragen.
 */
function fge_cityseed26_t2_sync_clone( WP_Post $master, WP_Post $clone ): void {
	$keep = [ '_fge_city', '_fge_region', '_fge_event_location', '_fge_geo_lat', '_fge_geo_lng' ];
	$skip = [ '_fge_views_count', '_fge_requests_count', '_edit_lock', '_edit_last' ];
	foreach ( get_post_meta( $master->ID ) as $key => $values ) {
		if ( in_array( $key, $keep, true ) || in_array( $key, $skip, true ) ) {
			continue;
		}
		update_post_meta( $clone->ID, $key, maybe_unserialize( $values[0] ) );
	}
	if ( function_exists( 'fge_event_price_label' ) ) {
		$pr = fge_event_pricing( (int) $clone->ID );
		update_post_meta( $clone->ID, '_fge_sale_price_net', $pr['gross'] );
		update_post_meta( $clone->ID, '_fge_public_price_label', fge_event_price_label( (int) $clone->ID ) );
	}
}

add_action( 'init', static function () {
	if ( get_option( 'fge_city_seed_2026_08_t2' ) ) {
		return;
	}
	update_option( 'fge_city_seed_2026_08_t2', '1', true );

	// 1) 18-Loch-Master (München) auf den 21.08.-Stand: 125 netto (150 € p.P.),
	//    bis 120 Gäste, Paket-Satz in der Beschreibung. Lokal längst so, live neu.
	$m18 = get_page_by_path( 'firmen-golfturnier-in-muenchen', OBJECT, 'firmengolf_event' );
	if ( $m18 instanceof WP_Post ) {
		update_post_meta( $m18->ID, '_fge_price_amount', '125' );
		update_post_meta( $m18->ID, '_fge_participants_max', '120' );
		update_post_meta( $m18->ID, '_fge_card_description', 'Klassisches Firmenturnier mit Flights, Live-Scoring und Siegerehrung, auch mit Scramble für gemischte Level. Komplett organisiert, von der Startliste bis zum Dinner. Bei Buchung als Paket ist alles inklusive, anpassbar auf euer Turnier.' );
		if ( function_exists( 'fge_event_price_label' ) ) {
			$pr = fge_event_pricing( (int) $m18->ID );
			update_post_meta( $m18->ID, '_fge_sale_price_net', $pr['gross'] );
			update_post_meta( $m18->ID, '_fge_public_price_label', fge_event_price_label( (int) $m18->ID ) );
		}
	}

	// 2) After-Work München auf das kalibrierte 49-€-Label (Betrag bewusst leer,
	//    die 5-€-Rundung liesse 49 sonst nicht zu). Lokal längst so, live neu.
	$maw = get_page_by_path( 'after-work-golf-in-muenchen', OBJECT, 'firmengolf_event' );
	if ( $maw instanceof WP_Post ) {
		update_post_meta( $maw->ID, '_fge_price_amount', '' );
		update_post_meta( $maw->ID, '_fge_public_price_label', '49 € p.P.' );
	}

	// 3) Die zwei neuen Turnier-Master sicherstellen.
	$t2_masters = [];
	foreach ( fge_cityseed26_t2_masters() as $def ) {
		$m = fge_cityseed26_t2_ensure_master( $def );
		if ( $m instanceof WP_Post ) {
			$t2_masters[] = $m;
		}
	}

	// 4) Alle Städte über die vorhandenen 18-Loch-Klone finden (Rollout 1 + 2):
	//    Klon-Preise auf Master-Stand syncen und die neuen Turniere dazustellen.
	global $wpdb;
	$clone_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type = 'firmengolf_event' AND post_status = 'publish'
		   AND post_name LIKE %s AND post_name != %s",
		'firmen-golfturnier-in-%',
		'firmen-golfturnier-in-muenchen'
	) );
	foreach ( $clone_ids as $cid ) {
		$c18 = get_post( (int) $cid );
		if ( ! $c18 instanceof WP_Post || ! $m18 instanceof WP_Post ) {
			continue;
		}
		fge_cityseed26_t2_sync_clone( $m18, $c18 );

		$city_slug = substr( $c18->post_name, strlen( 'firmen-golfturnier-in-' ) );
		$city      = [
			(string) get_post_meta( $c18->ID, '_fge_city', true ),
			(string) get_post_meta( $c18->ID, '_fge_event_location', true ),
			(string) get_post_meta( $c18->ID, '_fge_geo_lat', true ),
			(string) get_post_meta( $c18->ID, '_fge_geo_lng', true ),
		];
		if ( '' === $city[0] || '' === $city[2] ) {
			continue; // ohne Ortsdaten kein sinnvoller Klon
		}
		foreach ( $t2_masters as $tm ) {
			fge_cityseed26_clone_master( $tm, $city_slug, $city );
		}
	}
}, 22 );

// ── Seed T3 (02.09.2026): Weihnachtsfeier in allen Städten ────────────────────
// Neuer Saison-Typ 'weihnachtsfeier' (1.9.205). Master München, geklont über
// dieselben Städte wie das Turnier-Trio. Recherche-Basis: Weihnachtsfeiern bei
// 15/100 Golfanlagen und 14/50 Simulatoren belegt. Preise = Platzhalter-Logik
// wie alle Seeds (99 netto → 119 € p.P. Kundenpreis).
/**
 * Weihnachtsfeier-Texte (v2, Julius 07.09.): jede Platzhalter-Feier verbindet
 * Bewegung, Eventlocation und Spielformate (Nearest to the Pin, Longest Drive,
 * Angry Birds, Putt-Bierpong, Team-Scramble). Zentral, damit Master und die
 * 30 Stadt-Klone denselben Stand haben (Migration unten).
 */
function fge_cityseed26_t3_texts(): array {
	return [
		'desc'     => 'Bewegung, Location und Feier an einem Abend: Golf-Challenge mit Spielformaten wie Nearest to the Pin, Angry Birds und Putt-Bierpong, warm an den Simulatoren oder im Clubhaus, danach das gemeinsame Weihnachtsessen. Als Paket alles inklusive, anpassbar auf eure Feier.',
		'dayflow'  => "Ankommen in der Location\nGlühwein oder Punsch zur Begrüßung in der Indoor-Lounge oder im Clubhaus, kurze Einweisung an den Boxen, die Teams werden gelost.\n\nWarm werden an der Box\nErste Schläge mit Betreuung, Schläger werden gestellt. Wer noch nie gespielt hat, trifft nach zehn Minuten den Screen.\n\nSpielformate im Team\nNearest to the Pin, Longest Drive, Angry Birds und Putt-Bierpong im Wechsel, mit Live-Leaderboard über alle Boxen.\n\nSiegerehrung\nAuswertung mit Preisen für die besten Teams, garantiert mit Geschichten für die Kaffeeküche.\n\nWeihnachtsessen\nGemeinsames Menü oder Buffet zum Ausklang, auf Wunsch mit Getränkepauschale und Bar bis zum Schluss.",
		'includes' => "Glühwein-Empfang\nBetreuung an den Boxen\nLeihschläger & Bälle\nSpielformate mit Live-Leaderboard\nSiegerehrung & Preise\nWeihnachtsmenü oder Buffet\nOrganisation & ein Ansprechpartner",
	];
}

// Migration (07.09.): bestehende Weihnachtsfeiern (Master + Klone) auf die v2-Texte heben.
add_action( 'init', static function () {
	if ( get_option( 'fge_city_seed_2026_09_t3_v2' ) ) {
		return;
	}
	update_option( 'fge_city_seed_2026_09_t3_v2', '1', true );
	$ids = get_posts( [ 'post_type' => 'firmengolf_event', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_fge_event_type', 'meta_value' => 'weihnachtsfeier' ] );
	$t   = fge_cityseed26_t3_texts();
	foreach ( $ids as $id ) {
		// Nur unsere Platzhalter (Seed-Slug, kein zugeordneter Partner): Partner-eigene
		// Weihnachtsfeiern behalten ihre Texte (Review 07.09.).
		$post = get_post( $id );
		if ( ! $post || 0 !== strpos( $post->post_name, 'weihnachtsfeier-mit-golf-in-' ) || (int) get_post_meta( $id, '_fge_assigned_partner_id', true ) > 0 ) {
			continue;
		}
		update_post_meta( $id, '_fge_card_description', $t['desc'] );
		update_post_meta( $id, '_fge_event_dayflow', $t['dayflow'] );
		update_post_meta( $id, '_fge_event_includes', $t['includes'] );
	}
}, 23 );

function fge_cityseed26_t3_master(): array {
	return [
		'post_title' => 'Weihnachtsfeier mit Golf in München',
		'post_name'  => 'weihnachtsfeier-mit-golf-in-muenchen',
		'meta'       => [
			'_fge_event_type'       => 'weihnachtsfeier',
			'_fge_event_status'     => 'freigegeben',
			'_fge_event_location'   => 'Raum München',
			'_fge_region'           => 'München',
			'_fge_city'             => 'München',
			'_fge_participants_min' => '10',
			'_fge_participants_max' => '60',
			'_fge_duration'         => 'Abend · ca. 4 Std.',
			'_fge_card_description' => fge_cityseed26_t3_texts()['desc'],
			'_fge_price_mode'       => 'gesamt',
			'_fge_price_basis'      => 'person',
			'_fge_price_amount'     => '99',
			'_fge_event_dayflow'    => fge_cityseed26_t3_texts()['dayflow'],
			'_fge_event_includes'   => fge_cityseed26_t3_texts()['includes'],
			'_fge_event_addons'     => "Getränkepauschale\nLive-Musik oder DJ\nFotograf\nGebrandete Preise\nShuttle-Service",
			'_fge_geo_lat'          => '48.137',
			'_fge_geo_lng'          => '11.575',
		],
	];
}

add_action( 'init', static function () {
	if ( get_option( 'fge_city_seed_2026_09_t3' ) ) {
		return;
	}
	update_option( 'fge_city_seed_2026_09_t3', '1', true );

	$master = fge_cityseed26_t2_ensure_master( fge_cityseed26_t3_master() );
	if ( ! $master instanceof WP_Post ) {
		return;
	}

	// Städte über die vorhandenen 18-Loch-Klone finden (wie Seed T2).
	global $wpdb;
	$clone_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type = 'firmengolf_event' AND post_status = 'publish'
		   AND post_name LIKE %s AND post_name != %s",
		'firmen-golfturnier-in-%',
		'firmen-golfturnier-in-muenchen'
	) );
	foreach ( $clone_ids as $cid ) {
		$c18 = get_post( (int) $cid );
		if ( ! $c18 instanceof WP_Post ) {
			continue;
		}
		$city_slug = substr( $c18->post_name, strlen( 'firmen-golfturnier-in-' ) );
		$city      = [
			(string) get_post_meta( $c18->ID, '_fge_city', true ),
			(string) get_post_meta( $c18->ID, '_fge_event_location', true ),
			(string) get_post_meta( $c18->ID, '_fge_geo_lat', true ),
			(string) get_post_meta( $c18->ID, '_fge_geo_lng', true ),
		];
		if ( '' === $city[0] || '' === $city[2] ) {
			continue;
		}
		fge_cityseed26_clone_master( $master, $city_slug, $city );
	}
}, 30 );
