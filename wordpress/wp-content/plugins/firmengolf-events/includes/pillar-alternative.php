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
		'Ein Golf-Teamevent ist eine Alternative zu Escape Room, Kochkurs und Floßbau: ein Firmenevent auf dem Golfplatz für Teams ohne Golferfahrung. Ein Golflehrer führt an, Schläger und Bälle werden gestellt, danach spielt das Team eine kleine Challenge gegeneinander. Dauer drei bis sechs Stunden, ideal für 6 bis 12 Personen, mit mehreren Golflehrern bis etwa 80, %s pro Person netto, je nach Verpflegung und Programm. Buchbar auf fast jedem Golfplatz in Deutschland, oft mit dem Nahverkehr erreichbar, im Winter auch indoor am Simulator.',
		$golf_price
	);

	// Musterplanung, bewusst als Beispiel gekennzeichnet (keine erfundene Firma, keine
	// erfundenen Zitate). Zahlen: Live-Preis des günstigsten Teamevents plus Ablauf.
	$example = [
		'title' => 'So sieht ein Golf-Teamevent für 10 Personen aus',
		'intro' => 'Eine Musterplanung, wie wir sie für ein Team aus München anlegen würden. Der Platz liegt am Stadtrand, mit der S-Bahn erreichbar, mit Driving Range, Kurzplatz und Terrasse.',
		'rows'  => [
			[ 'Teilnehmer', '10 Personen aus einer Abteilung, zwei davon haben schon einmal Golf gespielt.' ],
			[ 'Ablauf', 'Ankunft 15 Uhr, Leihschläger, zwei Stunden Grundlagenkurs mit einem Golflehrer auf der Range, danach Longest Drive und Putt-Turnier, ab 18 Uhr Barbecue auf der Clubterrasse.' ],
			[ 'Kosten', sprintf( 'Grundlagenkurs %s aus dem aktuellen Angebot, Verpflegung als Fixpreis mit dem Gastronomen des Platzes, Getränke wahlweise als Pauschale oder auf eigene Rechnung.', $golf_from ) ],
			[ 'Vorlauf', 'Fünf Tage reichen, wenn Golflehrer und Platz in der Region frei sind. Für Verpflegung und größere Gruppen planen wir zwei bis vier Wochen.' ],
			[ 'Buchung', 'Eine Anfrage, ein Ansprechpartner, eine Sammelrechnung von Firmengolf Events.' ],
		],
	];

	$compare = [
		'cols' => [ 'Format', 'Dauer', 'Gruppengröße', 'Vorkenntnisse', 'Ort und Wetter', 'Preis pro Person', 'Was bleibt' ],
		'rows' => [
			[ 'Golf-Teamevent', '3 bis 6 Std.', 'ideal 6 bis 12, mit mehreren Golflehrern bis 80', 'keine', 'draußen, auch bei Regen; im Winter indoor am Simulator', $golf_price . ' netto', 'ein Sport, der bleibt: Nach dem Schnupperkurs buchen viele einen Platzreifekurs nach', true ],
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
		[ 'ic' => 'sun', 't' => 'Sommer draußen, Winter indoor', 'b' => 'Von April bis Oktober auf dem Platz, auch bei Regen. Von November bis März am Simulator mit Bar, das Format kennt keine Pause.' ],
		[ 'ic' => 'gift', 't' => 'Ein Preis, eine Rechnung', 'b' => 'Platz, Golflehrer, Leihschläger, Verpflegung und Challenge in einem Angebot. Ihr bekommt eine Sammelrechnung von Firmengolf Events.' ],
	];

	$flow = $formats['teamevent']['flow'] ?? [];

	$faqs = [
		[ 'q' => 'Was ist eine gute Alternative zum Escape Room für Firmen?', 'a' => 'Ein Golf-Teamevent: draußen, ohne Vorkenntnisse, drei bis sechs Stunden, für 6 bis 80 Personen. Anders als im Escape Room spielt das ganze Team zusammen statt in Achtergruppen, und es gibt einen echten Ort dafür, Clubhaus und Terrasse inklusive.' ],
		[ 'q' => 'Was kostet ein Golf-Teamevent im Vergleich zu anderen Teamevents?', 'a' => sprintf( 'Unsere buchbaren Golf-Teamevents liegen aktuell bei %s pro Person netto. Zum Vergleich: Escape Room etwa 25 bis 40 Euro, Kochkurs 90 bis 150 Euro, Floßbau 60 bis 120 Euro. Der reine Grundlagenkurs ist die günstigste Golf-Variante, Verpflegung und Turnier kommen nach Wunsch dazu.', $golf_price ) ],
		[ 'q' => 'Muss jemand aus dem Team Golf spielen können?', 'a' => 'Nein. Das Format ist für Teams ohne Vorerfahrung gebaut. Ein Golflehrer holt die Gruppe ab, stattet sie mit Leihschlägern aus und erklärt die Grundlagen auf der Übungsanlage. Bei jeder Eventseite steht, ob ein Golflehrer dabei ist und wie der Tag abläuft.' ],
		[ 'q' => 'Was ist, wenn im Team Golfer und Nichtgolfer gemischt sind?', 'a' => 'Das ist der Normalfall und funktioniert gut. Im Grundlagenkurs erklärt der Golflehrer der Einsteigergruppe die Basics, während erfahrene Spieler an ihrem Schwung arbeiten. Bei größeren Gruppen teilen wir: Die Golfer spielen eine Runde auf dem 9-Loch-Platz, die anderen haben zwei Stunden Grundlagenkurs. Oder beide spielen ein Firmenturnier, die Einsteiger nach kurzer Einführung auf dem Kurzplatz, die Golfer auf der großen Anlage, am Ende wird gemeinsam ausgewertet. Und oft zeigt sich: Es kommt auf Technik an, nicht auf Kraft.' ],
		[ 'q' => 'Wie viele Personen passen zu einem Golf-Teamevent?', 'a' => 'Ideal sind 6 bis 12 Personen, das deckt ein Golflehrer ab, bis 20 sind es zwei. Auf Anlagen mit mehreren Golflehrern gehen auch 40 bis 80 Personen, dann wird in Gruppen rotiert: Grundlagenkurs, Kurzplatz, Putting-Challenge.' ],
		[ 'q' => 'Wie kurzfristig kann eine Firma buchen?', 'a' => 'Ein Schnupperkurs oder Grundlagenkurs steht innerhalb von fünf Tagen, wenn Golflehrer und Platz in der Region frei sind. Mit Verpflegung, Turnier und größerer Gruppe planen wir zwei bis vier Wochen Vorlauf. Passende Plätze gibt es fast überall in Deutschland, in München zum Beispiel am Stadtrand mit S-Bahn-Anschluss.' ],
		[ 'q' => 'Ist Golf nicht elitär?', 'a' => 'Das Bild ist veraltet. Jede Region hat ihre exklusiven Clubs, aber Golf ist in Deutschland längst ein Breitensport, den Menschen aus jeder Branche spielen. Für ein Teamevent braucht niemand Ausrüstung, Mitgliedschaft oder Vorkenntnisse. Und es ist günstiger, als die meisten denken: Der Grundlagenkurs kostet weniger als ein Kochkurs.' ],
		[ 'q' => 'Wie laufen Essen und Getränke?', 'a' => 'Jedes Event wird individuell geplant, Essen und Getränke buchen wir beim Gastronomen des Platzes dazu. Meistens vereinbaren wir einen Fixpreis für die Verpflegung, etwa Barbecue oder Menü auf der Terrasse. Alkoholische Getränke laufen als Pauschale oder auf eigene Rechnung der Teilnehmer, je nachdem, was die Firma möchte.' ],
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
		'example'    => $example,
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
