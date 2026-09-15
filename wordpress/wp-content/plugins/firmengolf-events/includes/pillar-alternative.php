<?php
/**
 * Pillar-Seite /teamevent-alternative/ (KI-Sichtbarkeit, Phase 1, 15.09.2026).
 *
 * Besetzt die Suchintention „Alternative zum klassischen Teamevent“, bei der
 * Firmengolf Events bisher weder in Suchmaschinen noch in KI-Antworten auftaucht.
 * Aufbau: Definition in den ersten Sätzen, Vergleichstabelle mit konkreten Zahlen,
 * Ablauf, FAQ mit FAQPage-Schema. Golf-Preise kommen live aus den buchbaren Events
 * (fge_format_price_range), die Richtwerte der anderen Formate stehen hier im Code.
 *
 * Routing wie die Format-Landingpages: Rewrite → Query-Var → Child-Theme-Template.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_PILLAR_ALT_SLUG = 'teamevent-alternative';

/** URL der Pillar-Seite. */
function fge_alternative_url(): string {
	return home_url( '/' . FGE_PILLAR_ALT_SLUG . '/' );
}

/**
 * Inhalt der Seite. Zahlen bewusst konkret: KI-Antworten und Snippets zitieren
 * Seiten mit Zahlen, nicht mit Adjektiven. Richtwerte anderer Formate sind
 * öffentliche Anbieterpreise (Stand September 2026) und als Richtwert markiert.
 *
 * @return array<string,mixed>
 */
function fge_alternative_page_data(): array {
	$formats = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];
	$types   = $formats['teamevent']['types'] ?? [ 'teamevent', 'schnupperkurs' ];
	$range   = function_exists( 'fge_format_price_range' ) ? fge_format_price_range( $types ) : null;
	$eur     = static fn( float $v ): string => function_exists( 'fge_price_eur' ) ? fge_price_eur( $v ) : number_format( $v, 0, ',', '.' ) . ' €';

	$golf_price = $range
		? ( $range['max'] > $range['min'] ? $eur( $range['min'] ) . ' bis ' . $eur( $range['max'] ) : 'ab ' . $eur( $range['min'] ) )
		: 'auf Anfrage';
	$golf_from  = $range ? 'ab ' . $eur( $range['min'] ) . ' pro Person' : 'mit Preis auf Anfrage';
	$partner_tx = 'über 20 Partnerplätzen'; // Stand 15.09.2026: 22 aktive Plätze (docs/partner-uebersicht.csv)

	$definition = sprintf(
		'Ein Golf-Teamevent ist eine Alternative zu Escape Room, Kochkurs und Floßbau: ein Firmenevent auf dem Golfplatz für Teams ohne Golferfahrung. Ein Golflehrer führt an, Schläger und Bälle werden gestellt, danach spielt das Team eine kleine Challenge gegeneinander. Dauer drei bis sechs Stunden, 6 bis 80 Personen, %s pro Person netto, je nach Verpflegung und Programm. Buchbar deutschlandweit auf %s, im Winter auch indoor am Simulator.',
		$golf_price,
		$partner_tx
	);

	$compare = [
		'cols' => [ 'Format', 'Dauer', 'Gruppengröße', 'Vorkenntnisse', 'Ort und Wetter', 'Preis pro Person', 'Was bleibt' ],
		'rows' => [
			[ 'Golf-Teamevent', '3 bis 6 Std.', '6 bis 80', 'keine', 'draußen, im Winter indoor am Simulator', $golf_price . ' netto', 'ein Sport, der bleibt: Nach dem Schnupperkurs buchen viele einen Platzreifekurs nach', true ],
			[ 'Escape Room', '60 bis 90 Min.', '2 bis 8 pro Raum, große Gruppen parallel', 'keine', 'indoor', 'ca. 25 bis 40 €', 'kurz und intensiv, fast jedes Team kennt es schon', false ],
			[ 'Kochkurs', '3 bis 4 Std.', '8 bis 30', 'keine', 'indoor', 'ca. 90 bis 150 €', 'gemeinsames Essen, wenig Bewegung', false ],
			[ 'Floßbau', 'halber Tag', '10 bis 100', 'keine', 'draußen am See, stark wetterabhängig', 'ca. 60 bis 120 €', 'einmaliges Erlebnis, im Winter nicht möglich', false ],
			[ 'Bowling', '2 Std.', '4 bis 40', 'keine', 'indoor', 'ca. 20 bis 35 €', 'unverbindlich, wenig Gesprächsstoff danach', false ],
			[ 'Klettergarten', '3 bis 4 Std.', '8 bis 60', 'keine, Höhenangst ist ein Ausschluss', 'draußen, wetterabhängig', 'ca. 30 bis 60 €', 'sportlich, nicht für jedes Team passend', false ],
		],
		'note' => 'Richtwerte aus öffentlichen Anbieterpreisen, Stand September 2026, netto. Die Golf-Preise kommen live aus unseren buchbaren Events und ändern sich mit dem Angebot.',
	];

	$when = [
		[ 'ic' => 'users', 't' => 'Gemischte Teams', 'b' => 'Sportliche und unsportliche, jung und alt, alle starten bei null. Niemand hat einen Vorteil, niemand muss sich blamieren.' ],
		[ 'ic' => 'handshake', 't' => 'Kunden und Partner dabei', 'b' => 'Ein Golfplatz trägt auch ein Kundenevent: Clubhaus, Terrasse, Gastronomie, Parkplätze. Escape Room und Bowling wirken dafür zu klein.' ],
		[ 'ic' => 'sun', 't' => 'Sommer draußen, Winter indoor', 'b' => 'Von April bis Oktober auf dem Platz, danach am Simulator mit Bar. Das Format fällt nicht aus, wenn das Wetter kippt.' ],
		[ 'ic' => 'gift', 't' => 'Ein Preis, eine Rechnung', 'b' => 'Platz, Golflehrer, Leihschläger, Verpflegung und Challenge in einem Angebot. Ihr bekommt eine Sammelrechnung von Firmengolf Events.' ],
	];

	$flow = $formats['teamevent']['flow'] ?? [];

	$faqs = [
		[ 'q' => 'Was ist eine gute Alternative zum Escape Room für Firmen?', 'a' => 'Ein Golf-Teamevent: draußen, ohne Vorkenntnisse, drei bis sechs Stunden, für 6 bis 80 Personen. Anders als im Escape Room spielt das ganze Team zusammen statt in Achtergruppen, und es gibt einen echten Ort dafür, Clubhaus und Terrasse inklusive.' ],
		[ 'q' => 'Was kostet ein Golf-Teamevent im Vergleich zu anderen Teamevents?', 'a' => sprintf( 'Unsere buchbaren Golf-Teamevents liegen aktuell bei %s pro Person netto. Zum Vergleich: Escape Room etwa 25 bis 40 Euro, Kochkurs 90 bis 150 Euro, Floßbau 60 bis 120 Euro. Der reine Grundlagenkurs ist die günstigste Golf-Variante, Verpflegung und Turnier kommen nach Wunsch dazu.', $golf_price ) ],
		[ 'q' => 'Muss jemand aus dem Team Golf spielen können?', 'a' => 'Nein. Das Format ist für Teams ohne Vorerfahrung gebaut. Ein Golflehrer erklärt die Grundlagen, Schläger und Bälle werden gestellt. Wer schon spielt, bekommt in der Challenge keine Vorteile, weil wir Formate wie Longest Drive und Putt-Turnier nutzen.' ],
		[ 'q' => 'Wie viele Personen passen zu einem Golf-Teamevent?', 'a' => 'Von 6 bis rund 80 Personen. Kleine Teams bekommen eine Gruppe mit einem Golflehrer, große Teams werden auf mehrere Stationen verteilt. Für Gruppen über 80 planen wir das Event individuell.' ],
		[ 'q' => 'Was passiert bei Regen?', 'a' => 'Bei Regen wird trotzdem Golf gespielt, Golf ist kein Schönwettersport. Mit Regenkleidung wird der Nachmittag eher zum Abenteuer, und die meisten Plätze haben überdachte Abschläge auf der Driving Range, dort läuft der Grundlagenkurs weiter. Kurze Schauer wartet die Gruppe im Clubhaus ab. Nur bei Gewitter oder Platzsperre wird ein neuer Termin gesucht oder der Betrag erlassen.', 'link' => [ 'url' => home_url( '/golfevent-bei-schlechtem-wetter/' ), 'label' => 'Mehr zum Golfevent bei schlechtem Wetter' ] ],
		[ 'q' => 'Wie schnell bekommen wir ein Angebot?', 'a' => 'Innerhalb eines Werktags meldet sich ein persönlicher Ansprechpartner mit Vorschlägen für Platz, Format und Termin. Ihr fragt unverbindlich an und entscheidet mit dem Angebot in der Hand.' ],
	];

	return [
		'slug'       => FGE_PILLAR_ALT_SLUG,
		'eyebrow'    => 'Teamevent-Ideen',
		'h1'         => 'Teamevent-Alternative: Golf statt Escape Room, Kochkurs und Floßbau',
		'title'      => 'Teamevent-Alternative: Golf statt Escape Room und Kochkurs | Firmengolf Events',
		'desc'       => sprintf( 'Alternative zum klassischen Teamevent: Golf-Teamevent ohne Vorkenntnisse, 6 bis 80 Personen, %s. Vergleich mit Escape Room, Kochkurs, Floßbau und Bowling, mit Preisen.', $golf_from ),
		'lead'       => sprintf( 'Draußen, ohne Vorkenntnisse, für 6 bis 80 Personen, %s. Hier steht, wie Golf im Vergleich zu den üblichen Teamevents abschneidet.', $golf_from ),
		'definition' => $definition,
		'compare'    => $compare,
		'when'       => $when,
		'flow'       => $flow,
		'faqs'       => $faqs,
		'golf_from'  => $golf_from,
		'range'      => $range,
		'hero_img'   => 'pool/afterwork-grundlagenkurs-mit-dem-team.jpg',
	];
}

add_action( 'init', static function () {
	add_rewrite_rule( '^' . FGE_PILLAR_ALT_SLUG . '/?$', 'index.php?fge_pillar=' . FGE_PILLAR_ALT_SLUG, 'top' );
} );

add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_pillar';
	return $vars;
} );

add_filter( 'template_include', static function ( $template ) {
	if ( FGE_PILLAR_ALT_SLUG !== get_query_var( 'fge_pillar' ) ) {
		return $template;
	}
	$t = locate_template( 'template-alternative.php' );
	return $t ?: $template;
} );

/** Self-heal: Rewrite-Regel anlegen, falls beim Deploy nicht geflusht wurde. */
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules[ '^' . FGE_PILLAR_ALT_SLUG . '/?$' ] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );
