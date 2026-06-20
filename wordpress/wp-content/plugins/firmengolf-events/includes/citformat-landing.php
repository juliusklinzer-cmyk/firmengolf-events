<?php
/**
 * SEO Format×Stadt landing pages: /golf-events/<stadt>/<format>/
 *
 * Dritte Ebene über City- (city-landing.php) und Format-Hubs (format-landing.php):
 * fängt die lokalen Long-Tail-Keywords mit Format- UND Stadtbezug ab
 * (z. B. „Teamevent München", „Golf-Schnupperkurs Hamburg").
 *
 * Scharf geschaltet (eigener Intro-Text + Sitemap) nur für die Städte aus
 * fge_citformat_enabled_cities(). Andere Stadt×Format-Kombis liefern 404,
 * damit kein dünner programmatischer Content entsteht.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Städte, für die Format×Stadt-Seiten live sind (eigener Inhalt + Sitemap).
 *
 * @return string[] City-Slugs (müssen in fge_get_cities() existieren).
 */
function fge_citformat_enabled_cities(): array {
	return [ 'muenchen', 'hamburg' ];
}

/**
 * Format-Ebene: H1-/Title-/Description-Muster je Format (Stadtname via %s).
 * Einmal definiert, für alle Städte wiederverwendet → DRY, aber pro Seite unique
 * durch den eingesetzten Stadtnamen + den handgeschriebenen Intro (siehe unten).
 */
function fge_citformat_format_meta(): array {
	return [
		'teamevent' => [
			'h1'    => 'Golf-Teamevents für Firmen in %s',
			'eyeb'  => 'Teamevent · %s',
			'title' => 'Teamevent in %s auf dem Golfplatz — Firmengolf',
			'desc'  => 'Golf-Teamevent für euer Team in %s: PGA-Pro, Leihschläger, ein Tag draußen statt Stuhlkreis. Eine Anfrage, ein Ansprechpartner, eine Rechnung.',
		],
		'golfturnier' => [
			'h1'    => 'Firmen-Golfturniere in %s',
			'eyeb'  => 'Firmenturnier · %s',
			'title' => 'Firmenturnier in %s organisieren — Firmengolf',
			'desc'  => 'Firmen-Golfturnier in %s: Startlisten, Scramble für gemischte Level, Siegerehrung und Branding. Komplett organisiert von Firmengolf.',
		],
		'schnupperkurs' => [
			'h1'    => 'Golf-Schnupperkurse für Teams in %s',
			'eyeb'  => 'Schnupperkurs · %s',
			'title' => 'Golf-Schnupperkurs in %s für Firmen — Firmengolf',
			'desc'  => 'Golf-Schnupperkurs für euer Team in %s: ohne Vorkenntnisse, PGA-Coach, Material inklusive. Der lockere Einstieg ins Golf.',
		],
		'kundenevent' => [
			'h1'    => 'Kundenevents auf dem Golfplatz in %s',
			'eyeb'  => 'Kundenevent · %s',
			'title' => 'Kundenevent in %s auf dem Golfplatz — Firmengolf',
			'desc'  => 'Kundenevent auf dem Golfplatz in %s: Hospitality, Turnier oder Schnupperteil mit Dinner. Zeit für eure Gäste, organisiert von Firmengolf.',
		],
		'incentive' => [
			'h1'    => 'Golf-Incentives in %s & Umland',
			'eyeb'  => 'Incentive · %s',
			'title' => 'Golf-Incentive in %s für Unternehmen — Firmengolf',
			'desc'  => 'Golf-Incentive in %s: Golf, Hotellerie und Rahmenprogramm zu einem Paket. Leistung belohnen mit einem Erlebnis statt einem Bonus.',
		],
		'after-work-golf' => [
			'h1'    => 'After-Work Golf für Teams in %s',
			'eyeb'  => 'After-Work Golf · %s',
			'title' => 'After-Work Golf in %s für Firmen — Firmengolf',
			'desc'  => 'After-Work Golf in %s: nach Feierabend auf Range und Kurzplatz, locker angeleitet. Der kompakte Teamabend im Grünen.',
		],
	];
}

/**
 * Handgeschriebene, einzigartige Intro-Absätze je Stadt×Format (Anti-Thin-Content).
 * Schlüssel: [city_slug][format_slug].
 */
function fge_citformat_intros(): array {
	return [
		'muenchen' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um München bringt euer Team in rund 30 Minuten von der Innenstadt ins Grüne, zwischen Isar-Auen und Alpenpanorama. Statt Stuhlkreis und Flipchart entsteht hier ganz nebenbei Nähe: gemeinsam üben, lachen, anfeuern. Ein PGA-Pro führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, und wir stellen Platz, Ablauf und Verpflegung passend zu eurer Gruppe zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier in München ist der Klassiker für Kundenbindung und Teamwettbewerb, und kaum eine Region hat so viele Top-Plätze direkt vor der Tür wie das Münchner Umland. Wir richten es komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine stimmungsvolle Siegerehrung, auf Wunsch mit Sponsoren-Branding und Goodie-Bags.',
			'schnupperkurs'   => 'Ein Golf-Schnupperkurs ist der lockere Einstieg, wenn euer Team in München Golf noch nie ausprobiert hat. Auf einer stadtnahen Anlage, oft in rund 30 Minuten erreichbar, erklärt ein PGA-Pro die Grundlagen vom Putten bis zum ersten vollen Schwung, mit viel Humor und ohne Leistungsdruck. Alles Material wird gestellt, am Ende steht ein gemeinsames Erfolgserlebnis.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch in München Zeit und Atmosphäre, um Kunden einmal anders zu erleben, mit Blick aufs Bergpanorama und kurzen Wegen aus der Stadt. Ob Hospitality-Tag, Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive belohnt Leistung mit einem Erlebnis statt einem Bonus auf dem Konto, und rund um München liegen dafür einige der reizvollsten Anlagen Deutschlands, vom stadtnahen Platz bis zur Bergkulisse am Tegernsee. Wir verbinden Golf mit Hotellerie, Rahmenprogramm und Verpflegung zu einem runden Paket, ein- oder mehrtägig, von der Idee bis zur Abrechnung.',
			'after-work-golf' => 'After-Work Golf ist das niedrigschwellige Format für zwischendurch, und in München seid ihr nach Feierabend schnell auf einer stadtnahen Range oder dem Kurzplatz. Ein Pro leitet locker an, danach ein entspannter Ausklang, kein ganzer Tag und kein großer Aufwand. Ideal als regelmäßiger Teamabend oder lockerer Einstieg ins Thema Golf.',
		],
		'hamburg' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Hamburg holt euer Team raus zwischen Knicks, Wiesen und alte Alleen, viele Plätze nur eine kurze Fahrt vom Zentrum. Anders als beim klassischen Teambuilding entsteht hier ganz nebenbei Nähe: gemeinsam üben, lachen, anfeuern. Ein PGA-Pro führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, und wir stellen Platz, Ablauf und Verpflegung passend zu eurer Gruppe zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier in Hamburg verbindet Wettbewerb mit der besonderen Atmosphäre des Nordens, auf weitläufigen Plätzen, wo Wind und Weite das Spiel zur Herausforderung machen. Wir richten es komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine stimmungsvolle Siegerehrung, auf Wunsch mit Sponsoren-Branding und Goodie-Bags.',
			'schnupperkurs'   => 'Ein Golf-Schnupperkurs ist der ideale Einstieg, wenn euer Team in Hamburg Golf noch nie ausprobiert hat. Auf einer Anlage im Hamburger Umland erklärt ein PGA-Pro die Grundlagen vom Putten bis zum ersten vollen Schwung, locker und ohne Leistungsdruck. Alles Material wird gestellt, am Ende steht ein gemeinsames Erfolgserlebnis, das verbindet.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch in Hamburg Zeit und Atmosphäre, um Kunden einmal anders zu erleben, im Grünen und doch nah an der Stadt. Ob Hospitality-Tag, Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive belohnt Leistung mit einem Erlebnis statt einem Bonus auf dem Konto. Rund um Hamburg verbinden mehrere Anlagen Golf, Tagung und Hotel an einem Ort, ideal für ein mehrtägiges Offsite im Norden. Wir verbinden Golf mit Hotellerie, Rahmenprogramm und Verpflegung zu einem runden Paket, von der Idee bis zur Abrechnung.',
			'after-work-golf' => 'After-Work Golf ist das niedrigschwellige Format für zwischendurch, und in Hamburg seid ihr nach Feierabend schnell auf einer stadtnahen Range oder dem Kurzplatz. Ein Pro leitet locker an, danach ein entspannter Ausklang, kein ganzer Tag und kein großer Aufwand. Ideal als regelmäßiger Teamabend oder lockerer Einstieg ins Thema Golf.',
		],
	];
}

/**
 * Prüft, ob eine Stadt×Format-Kombination live ist (Stadt scharf, Format existiert).
 */
function fge_citformat_is_valid( string $city_slug, string $format_slug ): bool {
	if ( ! in_array( $city_slug, fge_citformat_enabled_cities(), true ) ) {
		return false;
	}
	$cities  = function_exists( 'fge_get_cities' ) ? fge_get_cities() : [];
	$formats = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];
	return isset( $cities[ $city_slug ], $formats[ $format_slug ] );
}

/**
 * Events am Schnittpunkt Stadt (Partnerplatz-Standort) UND Format (event_type).
 *
 * @return WP_Post[]
 */
function fge_citformat_events( array $city, array $format, int $limit = 6 ): array {
	$partner_ids = function_exists( 'fge_city_partner_ids' ) ? fge_city_partner_ids( $city ) : [];
	$types       = (array) ( $format['types'] ?? [] );
	$terms       = array_values( array_filter( (array) ( $city['match'] ?? [] ) ) );
	if ( empty( $types ) ) {
		return [];
	}
	// Stadt-Zugehörigkeit: Partner-Standort ODER Event-Stadt/-Region (Self-Events ohne Partner).
	$loc_or = [ 'relation' => 'OR' ];
	if ( ! empty( $partner_ids ) ) {
		$loc_or[] = [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_ids, 'compare' => 'IN' ];
	}
	if ( ! empty( $terms ) ) {
		$loc_or[] = [ 'key' => '_fge_city', 'value' => $terms, 'compare' => 'IN' ];
		$loc_or[] = [ 'key' => '_fge_region', 'value' => $terms, 'compare' => 'IN' ];
	}
	if ( count( $loc_or ) <= 1 ) {
		return [];
	}
	$posts = get_posts( [
		'post_type'      => 'firmengolf_event',
		'post_status'    => 'publish',
		'posts_per_page' => max( $limit * 3, 12 ),
		'meta_query'     => [
			'relation' => 'AND',
			[ 'key' => '_fge_event_status', 'value' => fge_public_event_statuses(), 'compare' => 'IN' ],
			$loc_or,
			[ 'key' => '_fge_event_type', 'value' => $types, 'compare' => 'IN' ],
		],
	] );
	$out = [];
	foreach ( $posts as $p ) {
		if ( ! function_exists( 'fge_event_is_public' ) || fge_event_is_public( $p->ID ) ) {
			$out[] = $p;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
	}
	return $out;
}

/* ── Routing ──────────────────────────────────────────────────────────────── */

add_action( 'init', static function () {
	// Zwei Segmente: kollidiert nicht mit den 1-Segment-Regeln ([^/]+ matcht keine Slashes).
	add_rewrite_rule( '^golf-events/([^/]+)/([^/]+)/?$', 'index.php?fge_city=$matches[1]&fge_format=$matches[2]', 'top' );
} );
// query_vars fge_city / fge_format sind bereits in city-landing.php / format-landing.php registriert.

add_filter( 'template_include', static function ( $template ) {
	$city_slug   = (string) get_query_var( 'fge_city' );
	$format_slug = (string) get_query_var( 'fge_format' );
	if ( $city_slug === '' || $format_slug === '' ) {
		return $template; // Keine Kombi → City-/Format-Filter übernehmen.
	}
	if ( ! fge_citformat_is_valid( $city_slug, $format_slug ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return get_query_template( '404' );
	}
	$citformat_template = locate_template( 'template-citformat.php' );
	return $citformat_template ?: $template;
}, 11 ); // Priorität 11: läuft nach den City-/Format-Filtern (10), gewinnt für Kombi-URLs.

/**
 * Self-heal: Rewrite-Regel anlegen, falls beim Aktivieren nicht geflusht wurde.
 */
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^golf-events/([^/]+)/([^/]+)/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );
