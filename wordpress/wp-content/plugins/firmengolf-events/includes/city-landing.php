<?php
/**
 * SEO city landing pages: /golf-events/<stadt>/
 * Programmatic routing via rewrite rule → template-city.php in the child theme.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stadt-Konfiguration, keyed by URL-Slug. `match` enthält Standort-Begriffe, mit
 * denen die Stadt den Partnerplätzen über deren Ort/Region zugeordnet wird
 * (ersetzt das frühere Event-`_fge_region`). Jede Stadt bringt eigenen Inhalt
 * (Intro, Gründe, FAQ) für echten, nicht-dünnen programmatischen SEO-Content.
 */
function fge_get_cities(): array {
	$reason = static function ( $ic, $t, $b ) { return [ 'ic' => $ic, 't' => $t, 'b' => $b ]; };
	$faq    = static function ( $q, $a ) { return [ 'q' => $q, 'a' => $a ]; };
	$team   = $reason( 'users', 'Für jedes Team', 'Von kompletten Einsteigenden bis zu Stammspielern. Schläger werden gestellt, ein PGA-Pro führt an, jedes Format passend zusammengestellt.' );
	$local  = $reason( 'flag', 'Lokale Partnerplätze', 'Wir arbeiten direkt mit den Clubs vor Ort. Kurze Wege, verlässliche Termine, echte Ansprechpartner.' );
	$f_anf  = $faq( 'Müssen unsere Mitarbeitenden Golf spielen können?', 'Nein. Unsere Schnupperkurse und Teamevents sind genau für Teams ohne Vorerfahrung gedacht. Schläger werden gestellt, ein PGA-Pro führt euch an, der gemeinsame Tag steht im Vordergrund, nicht das Handicap.' );
	$f_size = $faq( 'Wie groß darf die Gruppe sein?', 'Vom Coaching für zwei Personen bis zum Firmenturnier mit rund 80 Teilnehmenden ist alles möglich. Sag uns einfach eure Gruppengröße in der Anfrage, dann wählen wir Platz und Format passend aus.' );
	$f_fast = static function ( $city ) use ( $faq ) { return $faq( 'Wie schnell bekomme ich eine Rückmeldung?', 'Nach eurer Anfrage meldet sich innerhalb eines Werktags ein persönlicher Ansprechpartner mit konkreten Vorschlägen für Platz, Format und Termin in ' . $city . '.' ); };

	return [
		'muenchen' => [
			'name' => 'München', 'region' => 'Süd & Oberbayern', 'match' => [ 'München', 'Oberbayern' ],
			'intro' => 'München ist Firmenstandort und Naherholung in einem, und kaum eine Stadt hat so viele Top-Plätze direkt vor der Tür. In rund 30 Minuten seid ihr von der Innenstadt im Grünen, zwischen Isar-Auen und Alpenpanorama. Ob Teamevent, Firmenturnier, Kundenevent oder Sommerfest: Wir kennen die passenden Plätze im Münchner Umland.',
			'reasons' => [ $reason( 'clock', '30 Min. ins Grüne', 'Die besten Plätze liegen stadtnah, Eichenried und Co. sind schnell erreichbar, auch mit der S-Bahn.' ), $team, $reason( 'mountain', 'Bergpanorama inklusive', 'An klaren Tagen spielt ihr mit Blick auf die Alpen, ein Erlebnis, das in Erinnerung bleibt.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze in München kann ich für ein Firmenevent buchen?', 'Rund um München arbeiten wir mit mehreren Partnerplätzen. Je nach Gruppengröße, Anlass und Wunschtermin schlagen wir euch die passenden vor, alle in rund 30 Minuten vom Stadtkern.' ), $f_anf, $f_size, $f_fast( 'München' ) ],
		],
		'hamburg' => [
			'name' => 'Hamburg', 'region' => 'Nord', 'match' => [ 'Hamburg' ],
			'intro' => 'Hamburg lebt vom Wasser und vom Wind, und genau das macht Golf hier besonders. Die Plätze im Hamburger Umland liegen zwischen Knicks, Wiesen und alten Alleen, viele nur eine kurze Fahrt vom Zentrum. Ob After-Work-Teamevent, Schnupperkurs für die ganze Abteilung oder mehrtägiges Offsite: Wir organisieren euer Event im Norden von A bis Z.',
			'reasons' => [ $reason( 'clock', 'Stadtnah & erreichbar', 'Die Plätze im Norden Hamburgs sind schnell erreichbar, ideal für ein Event nach Feierabend.' ), $team, $reason( 'castle', 'Offsite mit Übernachtung', 'Einige Anlagen verbinden Tagung, Golf und Hotel an einem Ort, perfekt für Strategie-Tage.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze in Hamburg kann ich für ein Firmenevent buchen?', 'Im Hamburger Raum arbeiten wir mit ausgewählten Partnerplätzen. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $faq( 'Geht auch ein mehrtägiges Offsite mit Übernachtung?', 'Ja. Mehrere Anlagen verbinden Tagungsräume, Golf und Hotel an einem Ort, ideal für ein Strategie-Offsite. Wir planen Ablauf, Verpflegung und Golfprogramm gemeinsam mit euch.' ), $f_fast( 'Hamburg' ) ],
		],
		'koeln' => [
			'name' => 'Köln', 'region' => 'West & Rheinland', 'match' => [ 'Köln' ],
			'intro' => 'Köln ist Messe- und Medienstadt und ein perfekter Ort, um Kunden und Teams einmal anders zusammenzubringen. Die Plätze im Kölner Süden und im Bergischen liegen nah an der Stadt und doch mitten im Grünen. Ob Firmenturnier mit Siegerehrung, Kundenevent mit Dinner oder Charity-Cup: Wir organisieren euer Event von der ersten Idee bis zur Rechnung.',
			'reasons' => [ $reason( 'clock', 'Nah an der Stadt', 'Die Partnerplätze liegen stadtnah, ideal für Events mit Kunden aus der Region.' ), $team, $reason( 'gift', 'Kunden & Charity', 'Vom Hospitality-Tag bis zum Charity-Cup mit Spendentopf, wir setzen euer Anliegen in Szene.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze in Köln kann ich für ein Firmenevent buchen?', 'Im Kölner Raum arbeiten wir mit Partnerplätzen im Süden und im Bergischen. Abhängig von Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $faq( 'Eignet sich Golf für ein Kundenevent?', 'Sehr gut. Ein paar entspannte Stunden auf dem Platz schaffen Gespräche, die im Konferenzraum nie entstehen. Wir kombinieren das gern mit Catering, Dinner oder einem kleinen Wettbewerb.' ), $f_anf, $f_fast( 'Köln' ) ],
		],
		'stuttgart' => [
			'name' => 'Stuttgart', 'region' => 'Baden-Württemberg', 'match' => [ 'Stuttgart' ],
			'intro' => 'Stuttgart ist Industrie- und Mittelstandsregion, und Golf ist hier ein etablierter Rahmen für Kunden und Teams. Die Plätze im Umland liegen in sanften Hügeln und Weinbergen, gut erreichbar aus dem Kessel. Vom Teamtag bis zum Firmenturnier richten wir euer Event auf den passenden Anlagen rund um Stuttgart aus.',
			'reasons' => [ $reason( 'clock', 'Gut erreichbar', 'Die Plätze im Stuttgarter Umland sind aus der Stadt und vom Flughafen schnell erreichbar.' ), $team, $reason( 'leaf', 'Hügel & Weinberge', 'Spielt in der typischen Landschaft Baden-Württembergs, ein ruhiger Kontrast zum Arbeitsalltag.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Stuttgart kann ich für ein Firmenevent buchen?', 'Im Großraum Stuttgart arbeiten wir mit Partnerplätzen im Umland. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Stuttgart' ) ],
		],
		'berlin' => [
			'name' => 'Berlin', 'region' => 'Berlin & Brandenburg', 'match' => [ 'Berlin' ],
			'intro' => 'Berlin und das Umland bieten weitläufige Anlagen in Wald- und Seenlandschaft, oft erstaunlich nah an der Stadt. Für Unternehmen in der Hauptstadt ist Golf ein entspannter Weg, Teams und Kunden zusammenzubringen, vom lockeren After-Work bis zum großen Firmenturnier. Wir organisieren euer Event auf den passenden Plätzen rund um Berlin.',
			'reasons' => [ $reason( 'clock', 'Stadtnah im Grünen', 'Mehrere Anlagen liegen im Berliner Speckgürtel, schnell erreichbar aus der Innenstadt.' ), $team, $reason( 'leaf', 'Wald & Seen', 'Weitläufige Plätze in typischer Brandenburger Landschaft, viel Platz auch für große Gruppen.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze in Berlin kann ich für ein Firmenevent buchen?', 'In Berlin und Brandenburg arbeiten wir mit Partnerplätzen im Umland. Je nach Gruppengröße, Anlass und Termin schlagen wir euch die passenden vor.' ), $f_anf, $f_size, $f_fast( 'Berlin' ) ],
		],
		'frankfurt' => [
			'name' => 'Frankfurt', 'region' => 'Rhein-Main', 'match' => [ 'Frankfurt', 'Rhein-Main' ],
			'intro' => 'Frankfurt ist Finanz- und Messeplatz, und kaum eine Region ist so dicht mit guten Golfplätzen besetzt wie das Rhein-Main-Gebiet. Für Kunden- und Teamevents heißt das kurze Wege und viel Auswahl, vom Taunusrand bis in die Wetterau. Wir richten euer Firmenevent auf den passenden Plätzen rund um Frankfurt aus.',
			'reasons' => [ $reason( 'clock', 'Kurze Wege', 'Im dicht besetzten Rhein-Main-Gebiet ist der passende Platz nie weit, ideal auch für internationale Gäste.' ), $team, $reason( 'gift', 'Stark für Kundenevents', 'Golf passt perfekt zu Hospitality und Kundenbindung, gern mit Dinner und Rahmenprogramm.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Frankfurt kann ich für ein Firmenevent buchen?', 'Im Rhein-Main-Gebiet arbeiten wir mit mehreren Partnerplätzen. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $faq( 'Eignet sich Golf für ein Kundenevent?', 'Sehr gut. Auf dem Platz entstehen Gespräche wie nirgends sonst. Wir kombinieren das gern mit Catering, Dinner oder einem kleinen Turnier.' ), $f_anf, $f_fast( 'Frankfurt' ) ],
		],
		'duesseldorf' => [
			'name' => 'Düsseldorf', 'region' => 'Niederrhein', 'match' => [ 'Düsseldorf' ],
			'intro' => 'Düsseldorf verbindet Wirtschaftskraft mit kurzen Wegen ins Grüne. Die Plätze am Niederrhein und im Bergischen liegen nah an der Stadt und bieten Anlagen für jeden Anlass. Ob Teamtag, Kundenturnier oder Sommerfest, wir organisieren euer Firmenevent auf den passenden Plätzen rund um Düsseldorf.',
			'reasons' => [ $reason( 'clock', 'Nah an der Stadt', 'Die Partnerplätze sind aus Düsseldorf schnell erreichbar, ideal für Events mit regionalen Kunden.' ), $team, $reason( 'gift', 'Repräsentativ', 'Gepflegte Anlagen, die zu einem Unternehmensauftritt passen, gern mit Hospitality.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze in Düsseldorf kann ich für ein Firmenevent buchen?', 'Im Düsseldorfer Raum arbeiten wir mit Partnerplätzen am Niederrhein und im Bergischen. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Düsseldorf' ) ],
		],
		'tegernsee' => [
			'name' => 'Tegernsee', 'region' => 'Oberbayern', 'match' => [ 'Tegernsee' ],
			'intro' => 'Der Tegernsee ist eine der schönsten Kulissen für ein Firmenevent in Deutschland. Golf zwischen Bergen und See, dazu erstklassige Hotellerie, das macht die Region ideal für Incentives, Strategie-Offsites und besondere Kundenevents. Wir organisieren euer Event auf den Plätzen rund um den Tegernsee.',
			'reasons' => [ $reason( 'mountain', 'Berg- und Seekulisse', 'Golf vor Alpenpanorama, ein Rahmen, der bei Kunden und Teams lange nachwirkt.' ), $team, $reason( 'castle', 'Incentive & Offsite', 'Beste Hotellerie vor Ort, perfekt für mehrtägige Incentives mit Übernachtung.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze am Tegernsee kann ich für ein Firmenevent buchen?', 'In der Region Tegernsee arbeiten wir mit Partnerplätzen mit besonderer Kulisse. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $faq( 'Geht auch ein mehrtägiges Incentive mit Übernachtung?', 'Ja, gerade dafür ist die Region ideal. Wir verbinden Golf, Hotel, Rahmenprogramm und Verpflegung zu einem runden Offsite.' ), $f_anf, $f_fast( 'die Region Tegernsee' ) ],
		],
	];
}

/**
 * Partner-IDs, die einer Stadt über den Platz-Standort (`_fge_city` / `_fge_free_region`)
 * zugeordnet sind. Nur öffentlich sichtbare Plätze (Status aktiv).
 *
 * @return int[]
 */
function fge_city_partner_ids( array $city ): array {
	$terms = array_map( 'mb_strtolower', (array) ( $city['match'] ?? [] ) );
	if ( empty( $terms ) ) {
		return [];
	}
	$partners = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => [ [ 'key' => '_fge_partner_status', 'value' => 'aktiv' ] ],
	] );
	$out = [];
	foreach ( $partners as $pid ) {
		$hay = mb_strtolower( (string) get_post_meta( $pid, '_fge_city', true ) . ' ' . (string) get_post_meta( $pid, '_fge_free_region', true ) );
		foreach ( $terms as $t ) {
			if ( '' !== $t && false !== mb_strpos( $hay, $t ) ) {
				$out[] = (int) $pid;
				break;
			}
		}
	}
	return $out;
}

/**
 * Öffentliche Events der Plätze einer Stadt (für die Stadt-Landingpage).
 *
 * @return WP_Post[]
 */
function fge_city_events( array $city, int $limit = 6 ): array {
	$partner_ids = fge_city_partner_ids( $city );
	$terms       = array_values( array_filter( (array) ( $city['match'] ?? [] ) ) );

	// Stadt-Zugehörigkeit: Partner-Standort passt ODER Event-Stadt/-Region passt
	// (Letzteres deckt die von Firmengolf organisierten Events ohne Partner ab).
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
	$statuses = function_exists( 'fge_public_event_statuses' ) ? fge_public_event_statuses() : [ 'freigegeben' ];
	$posts    = get_posts( [
		'post_type'      => 'firmengolf_event',
		'post_status'    => 'publish',
		'posts_per_page' => max( $limit * 3, 12 ),
		'meta_query'     => [
			'relation' => 'AND',
			[ 'key' => '_fge_event_status', 'value' => $statuses, 'compare' => 'IN' ],
			$loc_or,
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

add_action( 'init', static function () {
	add_rewrite_rule( '^golf-events/([^/]+)/?$', 'index.php?fge_city=$matches[1]', 'top' );
} );

add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_city';
	return $vars;
} );

add_filter( 'template_include', static function ( $template ) {
	$slug = get_query_var( 'fge_city' );
	if ( ! $slug ) {
		return $template;
	}
	if ( get_query_var( 'fge_format' ) ) {
		return $template; // Format×Stadt-Kombi → citformat-landing.php übernimmt.
	}
	$cities = fge_get_cities();
	if ( ! isset( $cities[ $slug ] ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return get_query_template( '404' );
	}
	$city_template = locate_template( 'template-city.php' );
	return $city_template ?: $template;
} );

/**
 * Ensure the rewrite rule exists (self-heals if rules weren't flushed on activation).
 */
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^golf-events/([^/]+)/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );
