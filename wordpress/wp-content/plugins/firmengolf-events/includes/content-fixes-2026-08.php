<?php
/**
 * Inhaltskorrekturen aus dem Komplett-Audit (2026-08-12).
 *
 * Läuft wie workshop-migration.php option-gegated nach dem Deploy, weil das
 * Live-System nur per FTPS erreichbar ist (kein WP-CLI, kein SSH). Alles hier
 * ist idempotent: Events werden über ihren Slug gesucht, Texte per str_replace
 * ersetzt (zweiter Lauf findet nichts mehr).
 *
 * Schritte:
 *  1. Einstiegs-Teamevent je Stadt: reiner Golf-Schnupperkurs mit Leihschlägern
 *     und Bällen. Partner-Netto 15,97 € (= 19 € brutto Einkauf) ergibt über den
 *     20-%-Aufschlag und die 5er-Glättung 20 € p. P. netto als Kundenpreis.
 *  2. Sie-Anrede in den Partner-Eventtexten auf Du umgestellt.
 *  3. Kleinkram in Eventdaten: „Cafe" → „Kaffee", „1.5" → „1,5", Grammatik.
 *  4. Gedankenstriche aus Blog- und Eventtexten (harte Markenregel).
 *  5. Blog-Kostenartikel: Preisspanne und 110-Euro-Beispiel an die eigenen
 *     Angebote und an die Brutto-Betrachtung angeglichen.
 *  6. Betreuungsschlüssel im Blog vereinheitlicht (8 vs. 10 pro Coach).
 *  7. Weihnachtsfeier: ganzjährig buchbar, Preis pro Person (Julius, 2026-08-12).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Städte des Seed-Bestands: slug => [Anzeigename, Ortsangabe, lat, lng]. */
function fge_cf26_cities(): array {
	return [
		'muenchen'    => [ 'München', 'Raum München', '48.137', '11.575' ],
		'hamburg'     => [ 'Hamburg', 'Raum Hamburg', '53.551', '9.993' ],
		'koeln'       => [ 'Köln', 'Raum Köln', '50.938', '6.96' ],
		'stuttgart'   => [ 'Stuttgart', 'Raum Stuttgart', '48.776', '9.182' ],
		'berlin'      => [ 'Berlin', 'Raum Berlin', '52.52', '13.405' ],
		'frankfurt'   => [ 'Frankfurt', 'Raum Frankfurt', '50.11', '8.682' ],
		'duesseldorf' => [ 'Düsseldorf', 'Raum Düsseldorf', '51.225', '6.776' ],
		'tegernsee'   => [ 'Tegernsee', 'Region Tegernsee', '47.713', '11.757' ],
	];
}

/**
 * Schritt 1: das günstigste Einstiegsformat, damit das „ab"-Versprechen auf den
 * Landingpages ein echtes Angebot hinter sich hat (vorher: 20 € versprochen,
 * günstigstes Teamevent 70 €).
 */
function fge_cf26_create_starter_events(): void {
	$dayflow = "Ankunft & Begrüßung\nEmpfang am Clubhaus, kurze Einführung ins Programm, Leihschläger werden ausgegeben.\n\n"
		. "Aufwärmen auf der Range\nDie ersten Schwünge unter Anleitung, ganz ohne Vorkenntnisse.\n\n"
		. "Golf-Schnupperkurs mit Golflehrer\nIn kleinen Gruppen lernt ihr Putten, Chippen und den vollen Schwung, locker und mit viel Humor.\n\n"
		. "Putt-Challenge zum Abschluss\nKleiner Wettbewerb auf dem Putting-Grün, danach individuelle Abreise.";
	$includes = "Golf-Schnupperkurs mit Golflehrer\nLeihschläger & Range-Bälle\nPlatznutzung (Range & Putting-Grün)\nOrganisation & ein Ansprechpartner";
	$addons   = "Verpflegung im Clubhaus\nTeam-Putt-Turnier\nMeetingraum (ab 2 Std.)\nAbholservice";
	$card     = 'Der einfache Einstieg: zwei Stunden Golf-Schnupperkurs mit Golflehrer, Leihschläger und Bälle sind dabei. Ohne Verpflegung und mit eigener Anreise, dafür zum kleinsten Preis.';
	$body     = '<p>Ihr wollt Golf einmal ausprobieren, ohne gleich einen ganzen Eventtag zu buchen? Genau dafür ist dieses Format gedacht. In rund zwei Stunden bringt euch ein Golflehrer die Grundlagen bei: Putten, Chippen und der volle Schwung, in kleinen Gruppen und ohne Leistungsdruck.</p>'
		. '<p>Schläger und Range-Bälle werden gestellt, Vorkenntnisse braucht ihr keine. Anreise und Verpflegung organisiert ihr selbst, deshalb ist das hier unser günstigstes Teamformat. Wenn ihr Essen, ein Team-Putt-Turnier oder einen Meetingraum dazu haben wollt, schreibt es einfach in die Anfrage, wir bauen euch das Paket zusammen.</p>';

	foreach ( fge_cf26_cities() as $slug => $c ) {
		list( $name, $venue, $lat, $lng ) = $c;
		$post_name = 'golf-schnupperkurs-fuer-teams-in-' . $slug;
		if ( get_page_by_path( $post_name, OBJECT, 'firmengolf_event' ) ) {
			continue; // schon angelegt
		}

		$id = wp_insert_post( [
			'post_type'    => 'firmengolf_event',
			'post_status'  => 'publish',
			'post_title'   => 'Golf-Schnupperkurs für Teams in ' . $name,
			'post_name'    => $post_name,
			'post_content' => $body,
		] );
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}

		$meta = [
			'_fge_event_type'        => 'teamevent',
			'_fge_event_status'      => 'freigegeben',
			'_fge_participants_min'  => '6',
			'_fge_participants_max'  => '30',
			'_fge_duration'          => 'Kompakt · ca. 2 Std.',
			'_fge_card_description'  => $card,
			'_fge_event_dayflow'     => $dayflow,
			'_fge_event_includes'    => $includes,
			'_fge_event_addons'      => $addons,
			'_fge_event_location'    => $venue,
			'_fge_region'            => $name,
			'_fge_city'              => $name,
			'_fge_geo_lat'           => $lat,
			'_fge_geo_lng'           => $lng,
			// Partner-Netto: 19 € brutto Einkauf / 1,19. Über den 20-%-Aufschlag
			// und die 5er-Glättung wird daraus ein Kundenpreis von 20 € p. P.
			'_fge_price_mode'        => 'gesamt',
			'_fge_price_basis'       => 'person',
			'_fge_price_amount'      => '15.97',
		];
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		if ( function_exists( 'fge_event_price_label' ) ) {
			$pr = fge_event_pricing( (int) $id );
			update_post_meta( $id, '_fge_sale_price_net', $pr['gross'] );
			update_post_meta( $id, '_fge_public_price_label', fge_event_price_label( (int) $id ) );
		}
	}
}

/**
 * Ersetzt Textbausteine in post_content und in den Text-Metas eines Events.
 *
 * @param array<string,string> $pairs Suchtext => Ersatztext.
 */
function fge_cf26_replace_in_event( int $post_id, array $pairs ): void {
	$keys = [ '_fge_card_description', '_fge_event_dayflow', '_fge_event_includes', '_fge_event_addons', '_fge_duration' ];
	foreach ( $keys as $key ) {
		$val = (string) get_post_meta( $post_id, $key, true );
		if ( '' === $val ) {
			continue;
		}
		$new = strtr( $val, $pairs );
		if ( $new !== $val ) {
			update_post_meta( $post_id, $key, $new );
		}
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}
	$content = strtr( (string) $post->post_content, $pairs );
	$excerpt = strtr( (string) $post->post_excerpt, $pairs );
	if ( $content !== $post->post_content || $excerpt !== $post->post_excerpt ) {
		wp_update_post( [ 'ID' => $post_id, 'post_content' => $content, 'post_excerpt' => $excerpt ] );
	}
}

/** Schritte 2 und 4: Sie-Anrede und Schreibfehler in den Partner-Eventtexten. */
function fge_cf26_fix_event_copy(): void {
	$pairs = [
		// Sie-Anrede auf Du (harte Markenregel, Audit-Fund).
		'stärken Sie Ihr Team auf dem Golfplatz'   => 'stärkt euer Team auf dem Golfplatz',
		'Erleben Sie einen abwechslungsreichen Tag' => 'Erlebt einen abwechslungsreichen Tag',
		'erleben Sie die Faszination Golf'          => 'erlebt ihr die Faszination Golf',
		'erleben Sie echtes Golf'                   => 'erlebt ihr echtes Golf',
		'üben Sie Abschläge'                        => 'übt ihr Abschläge',
		'Nach einer Einführung durch einen erfahrenen Trainer üben Sie' => 'Nach einer Einführung durch einen erfahrenen Trainer übt ihr',
		// Schreibfehler und Zahlenformate.
		'Cafe + Kuchen'                             => 'Kaffee & Kuchen',
		'1.5 Stunden'                               => '1,5 Stunden',
		'12 Stunden an 3-4 Tage'                    => '12 Stunden an 3 bis 4 Tagen',
		// Halbgeviertstrich in Eventtexten (Markenregel: keine Gedankenstriche).
		'Erfolgserlebnisse – stärken'               => 'Erfolgserlebnisse, stärken',
		'Dein Weg zur Platzreife – flexibel'        => 'Dein Weg zur Platzreife, flexibel',
	];

	$events = get_posts( [
		'post_type'      => 'firmengolf_event',
		'post_status'    => [ 'publish', 'draft', 'pending' ],
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );
	foreach ( $events as $id ) {
		fge_cf26_replace_in_event( (int) $id, $pairs );
		fge_cf26_unwrap_dash_list( (int) $id );
	}
}

/**
 * Aufzählungen, die als ein Fließtext mit eingeklebten Bindestrichen gespeichert
 * wurden („-Leihschläger -Schnupperkurs -Mittagessen"), in echte Zeilen brechen.
 * Greift nur ab drei Punkten, damit normale Bindestriche unangetastet bleiben.
 */
function fge_cf26_unwrap_dash_list( int $post_id ): void {
	$keys = [ '_fge_event_dayflow', '_fge_event_includes', '_fge_event_addons' ];
	foreach ( $keys as $key ) {
		$val = (string) get_post_meta( $post_id, $key, true );
		if ( '' === $val || preg_match_all( '/\s-(?=[A-ZÄÖÜ0-9])/u', $val ) < 3 ) {
			continue;
		}
		$new = trim( preg_replace( '/\s-(?=[A-ZÄÖÜ0-9])/u', "\n", $val ) );
		if ( $new !== $val ) {
			update_post_meta( $post_id, $key, $new );
		}
	}
}

/**
 * Weihnachtsfeier: laut Julius (2026-08-12) ganzjährig buchbar, auch außerhalb
 * der Platzsaison, und der hinterlegte Preis ist pro Person, nicht pauschal.
 * Die Karte zeigte deshalb „150 € Gesamt netto" für bis zu 50 Gäste inklusive
 * Dinner und daneben „Saison März bis Oktober" bei einem Glühwein-Event.
 */
function fge_cf26_fix_weihnachtsfeier(): void {
	$post = get_page_by_path( 'weihnachtsfeier-fuer-dein-team', OBJECT, 'firmengolf_event' );
	if ( ! $post ) {
		return;
	}
	update_post_meta( $post->ID, '_fge_season_exempt', '1' );
	if ( 'person' !== (string) get_post_meta( $post->ID, '_fge_price_basis', true ) ) {
		update_post_meta( $post->ID, '_fge_price_basis', 'person' );
		if ( function_exists( 'fge_event_pricing' ) && function_exists( 'fge_event_price_label' ) ) {
			$pr = fge_event_pricing( (int) $post->ID );
			update_post_meta( $post->ID, '_fge_sale_price_net', $pr['gross'] );
			update_post_meta( $post->ID, '_fge_public_price_label', fge_event_price_label( (int) $post->ID ) );
		}
	}
}

/** Schritte 5 bis 7: Blogtexte. */
function fge_cf26_fix_blog(): void {
	$posts = get_posts( [ 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ] );
	foreach ( $posts as $id ) {
		$post    = get_post( $id );
		$content = (string) $post->post_content;
		// Gedankenstriche: in den Zitat-Attributionen (<footer>/<cite>) und im
		// Fließtext. Markenregel erlaubt sie nirgends.
		$content = strtr( $content, [
			'<footer>— ' => '<footer>',
			'<footer>– ' => '<footer>',
			'<cite>— '   => '<cite>',
			'<cite>– '   => '<cite>',
			' — '        => ', ',
			' – '        => ', ',
		] );
		if ( $content !== $post->post_content ) {
			wp_update_post( [ 'ID' => $id, 'post_content' => $content ] );
		}
	}

	// Kostenartikel: Spanne an die eigenen Angebote angeglichen und das
	// 110-Euro-Beispiel auf Brutto umgestellt (der Freibetrag rechnet brutto,
	// der Footer weist alle Preise netto aus, das kollidierte).
	$kosten = get_page_by_path( 'was-kostet-ein-firmen-golfevent', OBJECT, 'post' );
	if ( $kosten ) {
		fge_cf26_replace_in_post( (int) $kosten->ID, [
			'ca. 80 bis 160 €' => 'ca. 20 bis 160 €',
			'landet bei einer Orientierung von rund 100 € pro Person bei etwa 2.000 € gesamt. Damit bleibt ihr pro Kopf unter der 110-Euro-Grenze und somit oft im steuerfreien Rahmen.'
				=> 'landet bei einer Orientierung von rund 90 € netto pro Person bei etwa 1.800 € gesamt. Brutto sind das rund 107 € pro Kopf, ihr bleibt also unter der 110-Euro-Grenze, die mit den Bruttokosten rechnet.',
		] );
	}

	// Betreuungsschlüssel: eine Zahl sitewide (Audit: 8 vs. 10 pro Coach).
	$einsteiger = get_page_by_path( 'einsteiger-mythos', OBJECT, 'post' );
	if ( $einsteiger ) {
		fge_cf26_replace_in_post( (int) $einsteiger->ID, [
			'die Gruppengröße pro Coach: max. 8 Personen' => 'die Gruppengröße pro Coach: rund 10 Personen',
		] );
	}

	// Sprachschnitzer.
	$teambuilding = get_page_by_path( 'golf-teambuilding-warum-es-funktioniert', OBJECT, 'post' );
	if ( $teambuilding ) {
		fge_cf26_replace_in_post( (int) $teambuilding->ID, [
			'Es wirft dich in einen Bann' => 'Es zieht dich in seinen Bann',
			'Lausch einfach den Gesprächen' => 'Lauscht einfach den Gesprächen',
		] );
	}
	$sommerfest = get_page_by_path( 'sommerfest-2026', OBJECT, 'post' );
	if ( $sommerfest ) {
		fge_cf26_replace_in_post( (int) $sommerfest->ID, [
			'jede:r kann kommen und gehen wann er will' => 'alle können kommen und gehen, wann sie wollen',
		] );
	}
}

/** str_replace auf post_content eines einzelnen Beitrags. */
function fge_cf26_replace_in_post( int $post_id, array $pairs ): void {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}
	$content = strtr( (string) $post->post_content, $pairs );
	if ( $content !== $post->post_content ) {
		wp_update_post( [ 'ID' => $post_id, 'post_content' => $content ] );
	}
}

add_action( 'init', static function () {
	if ( get_option( 'fge_content_fixes_2026_08' ) ) {
		return;
	}
	update_option( 'fge_content_fixes_2026_08', '1', true );

	fge_cf26_create_starter_events();
	fge_cf26_fix_event_copy();
	fge_cf26_fix_weihnachtsfeier();
	fge_cf26_fix_blog();

	if ( function_exists( 'fge_flush_person_price_index' ) ) {
		fge_flush_person_price_index();
	}
	if ( function_exists( 'fge_flush_formats_in_use' ) ) {
		fge_flush_formats_in_use();
	}
}, 21 );

/**
 * Nachlauf. Beim ersten Durchgang standen im selben strtr()-Aufruf zwei Regeln,
 * die sich überlappten: der Halbgeviertstrich-Ersatz („Erfolgserlebnisse – stärken"
 * → „Erfolgserlebnisse, stärken") hat das Wort „stärken" konsumiert, bevor die
 * Du-Regel „stärken Sie Ihr Team" greifen konnte. strtr() scannt nur einmal von
 * links, ersetzter Text wird nicht erneut geprüft. Diese Stelle deshalb separat,
 * mit einem Suchbegriff, der sich mit nichts anderem überschneidet.
 */
add_action( 'init', static function () {
	if ( get_option( 'fge_content_fixes_2026_08b' ) ) {
		return;
	}
	update_option( 'fge_content_fixes_2026_08b', '1', true );

	$pairs  = [ 'stärken Sie Ihr Team' => 'stärkt euer Team' ];
	$events = get_posts( [
		'post_type'      => 'firmengolf_event',
		'post_status'    => [ 'publish', 'draft', 'pending' ],
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );
	foreach ( $events as $id ) {
		fge_cf26_replace_in_event( (int) $id, $pairs );
	}
}, 22 );

// ── Seed: Typ-Landingpages für Golflehrer und Indoor (28.08.2026) ─────────────
// Legt die WP-Seiten /golflehrer-partner/ und /indoor-partner/ an (die Templates
// page-golflehrer-partner.php / page-indoor-partner.php greifen über den Slug).
// Option-gegated + idempotent, weil live nur FTPS erreichbar ist.
add_action( 'init', static function () {
	if ( get_option( 'fge_partner_type_pages_2026_08' ) ) {
		return;
	}
	foreach ( [
		'golflehrer-partner' => 'Golflehrer-Partner werden',
		'indoor-partner'     => 'Indoor-Partner werden',
	] as $seed_slug => $seed_title ) {
		if ( get_page_by_path( $seed_slug ) ) {
			continue;
		}
		wp_insert_post( [
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => $seed_slug,
			'post_title'  => $seed_title,
		] );
	}
	update_option( 'fge_partner_type_pages_2026_08', 1, false );
}, 20 );
