<?php
/**
 * SEO format landing pages: /firmenevent/<format>/
 * Programmatic routing via rewrite rule → template-format.php im Child-Theme.
 *
 * Fängt die format-bezogenen Head-Keywords ohne Stadtbezug ab (z. B. „Golf-Teamevent
 * für Firmen", „Firmen-Golfturnier"). Spiegelt die City-Landing-Architektur.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format-Konfiguration, keyed by URL-Slug. `types` listet die event_type-Meta-Werte
 * (inkl. Legacy-Keys), die diesem Hub zugeordnet werden. Jeder Hub bringt eigenen
 * Inhalt (Intro, Gründe, FAQ) für nicht-dünnen programmatischen SEO-Content.
 */
function fge_get_event_format_pages(): array {
	$reason = static function ( $ic, $t, $b ) { return [ 'ic' => $ic, 't' => $t, 'b' => $b ]; };
	$faq    = static function ( $q, $a ) { return [ 'q' => $q, 'a' => $a ]; };

	$r_team = $reason( 'users', 'Für jedes Level', 'Von kompletten Einsteigenden bis zu Stammspielern. Schläger werden gestellt, ein Golflehrer führt an.' );
	$r_one  = $reason( 'flag', 'Eine Anfrage, ein Kontakt', 'Platzwahl, Format, Catering und Abrechnung über einen einzigen Ansprechpartner.' );
	$f_anf  = $faq( 'Müssen unsere Mitarbeitenden Golf spielen können?', 'Nein. Das Format ist auch für Teams ohne Vorerfahrung geeignet. Schläger werden gestellt, ein Golflehrer führt euch an, der gemeinsame Tag steht im Vordergrund.' );
	$f_fast = $faq( 'Wie schnell bekomme ich eine Rückmeldung?', 'Nach eurer Anfrage meldet sich innerhalb eines Werktags ein persönlicher Ansprechpartner mit konkreten Vorschlägen für Platz, Format und Termin.' );
	$f_bill = $faq( 'Wie wird abgerechnet?', 'Ihr bekommt eine Sammelrechnung von Firmengolf mit allen Posten, einfach für HR und Buchhaltung.' );

	return [
		'teamevent' => [
			'name'    => 'Golf-Teamevent',
			'eyebrow' => 'Format · Teamevent',
			'h1'      => 'Golf-Teamevents für Firmen',
			'lead'    => 'Ein gemeinsamer Tag auf dem Platz, der euer Team wirklich zusammenbringt — auch ohne Golferfahrung.',
			'intro'   => 'Ein Golf-Teamevent holt euer Team raus aus dem Büro und rein ins Grüne. Anders als beim klassischen Teambuilding entsteht hier ganz nebenbei Nähe: gemeinsam üben, lachen, anfeuern. Ein Schnupper- und Grundlagenkurs ist immer dabei: Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt. Wir stellen Platz, Ablauf und Verpflegung passend zu eurer Gruppe zusammen, deutschlandweit.',
			'reasons' => [ $r_team, $reason( 'leaf', 'Wirkt nach', 'Bewegung, frische Luft und gemeinsame Erlebnisse statt Stuhlkreis — bleibt länger in Erinnerung als das nächste Meeting.' ), $reason( 'clock', 'Halber oder ganzer Tag', 'Vom kompakten Nachmittag bis zum vollen Eventtag, passend zu Kalender und Budget.' ), $r_one ],
			'faqs'    => [ $f_anf, $faq( 'Wie groß darf das Team sein?', 'Von der kleinen Abteilung bis zu rund 80 Teilnehmenden ist alles möglich. Sag uns eure Gruppengröße, dann wählen wir Platz und Format passend aus.' ), $f_fast, $f_bill ],
			'types'   => [ 'teamevent', 'team-building', 'team_challenge', 'azubi_event', 'schnupperkurs', 'schnuppergolf' ],
		],
		'golfturnier' => [
			'name'    => 'Firmen-Golfturnier',
			'eyebrow' => 'Format · Turnier',
			'h1'      => 'Firmen-Golfturniere organisieren',
			'lead'    => 'Vom 9-Loch-Schnupperturnier bis zum großen 18-Loch-Firmencup mit Siegerehrung.',
			'intro'   => 'Ein Firmen-Golfturnier ist der Klassiker für Kundenbindung und Teamwettbewerb. Wir richten es komplett aus: Startlisten, Zählweise (auch Scramble für gemischte Level), Bewirtung am Platz und eine stimmungsvolle Siegerehrung. Auf Wunsch mit Sponsoren-Branding, Goodie-Bags und Rahmenprogramm. So wird aus einem Spieltag ein Firmenevent, über das man noch lange spricht.',
			'reasons' => [ $reason( 'trophy', 'Echter Wettbewerb', 'Faire Zählformate für gemischte Level, inkl. Scramble — alle spielen mit, vom Anfänger bis zum Single-Handicap.' ), $reason( 'gift', 'Siegerehrung & Branding', 'Pokale, Preise, Sponsorenlogos und Goodie-Bags, auf Wunsch in eurem Corporate Design.' ), $reason( 'users', 'Auch für große Felder', 'Vom 20er-Flight bis zum großen Firmencup planen wir Startzeiten und Logistik.' ), $r_one ],
			'faqs'    => [ $faq( 'Können auch Anfänger an einem Firmenturnier teilnehmen?', 'Ja. Mit dem Scramble-Format spielen gemischte Teams gemeinsam — Erfahrene tragen das Feld, Einsteigende sind voll dabei. So wird niemand vorgeführt.' ), $faq( 'Übernehmt ihr die komplette Organisation?', 'Ja, von Startliste und Zählweise über Bewirtung bis zur Siegerehrung. Ihr müsst nur erscheinen und spielen.' ), $f_fast, $f_bill ],
			'types'   => [ 'firmen_golfturnier', 'firmenturnier', '9hole_turnier', '18hole_turnier' ],
		],
		'platzreife' => [
			'name'    => 'Platzreife',
			'eyebrow' => 'Format · Platzreife',
			'h1'      => 'Platzreife für Firmen & Teams',
			'lead'    => 'Der offizielle Einstieg in den Golfsport: kompakter Kurs mit Prüfung, als Firmenprogramm oder Benefit mit bleibendem Wert.',
			'intro'   => 'Die Platzreife ist die Eintrittskarte auf den Golfplatz. In einem kompakten Kurs lernt euer Team Technik, Regeln und Etikette, angeleitet von einem PGA-Golflehrer, und legt am Ende die offizielle Platzreifeprüfung ab. Als Firmenprogramm ist das mehr als ein Event: Mitarbeitende können danach eigenständig golfen und bleiben dem Sport verbunden. Schläger und Material werden während des Kurses gestellt, wir organisieren Platz, Pro und Ablauf.',
			'reasons' => [ $reason( 'trophy', 'Offizieller Abschluss', 'Am Ende steht die anerkannte Platzreife, die Eintrittskarte für Golfplätze in ganz Deutschland.' ), $reason( 'clock', 'Kompakt planbar', 'Als Intensivkurs an zwei bis drei Tagen oder verteilt über mehrere Termine, passend zum Arbeitskalender.' ), $reason( 'gift', 'Benefit mit Substanz', 'Ein Programm, das bleibt: kein einmaliges Event, sondern der Start in einen Sport fürs Leben.' ), $r_one ],
			'faqs'    => [ $f_anf, $faq( 'Was gehört zum Platzreifekurs?', 'Training mit PGA-Golflehrer, Regel- und Etikettekunde, Leihschläger, Range-Bälle und die Prüfung. Auf Wunsch ergänzen wir Verpflegung und ein gemeinsames Abschlussspiel.' ), $faq( 'Wie lange dauert die Platzreife?', 'Üblich sind zwei bis drei Tage. Wir planen den Kurs am Stück oder verteilt über mehrere Wochen, ganz wie es in euren Kalender passt.' ), $f_bill ],
			'types'   => [ 'platzreife' ],
		],
		'kundenevent' => [
			'name'    => 'Kundenevent Golf',
			'eyebrow' => 'Format · Kunden',
			'h1'      => 'Kundenevents auf dem Golfplatz',
			'lead'    => 'Ein paar entspannte Stunden auf dem Platz schaffen Gespräche, die im Konferenzraum nie entstehen.',
			'intro'   => 'Golf ist seit jeher der Rahmen für gute Geschäftsbeziehungen. Ein Kundenevent auf dem Golfplatz gibt euch Zeit und Atmosphäre, um Kunden einmal anders zu erleben — ob beim Schnupperkurs, beim Turnier oder beim Hospitality-Tag mit Dinner. Wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz auf eure Gäste konzentrieren könnt.',
			'reasons' => [ $reason( 'handshake', 'Beziehungen vertiefen', 'Mehrere Stunden gemeinsam draußen schaffen echte Gespräche und bleiben in Erinnerung.' ), $reason( 'gift', 'Hospitality & Dinner', 'Von der Begrüßung am Tee bis zum Abendessen mit Siegerehrung — alles aus einer Hand.' ), $r_team, $r_one ],
			'faqs'    => [ $faq( 'Eignet sich Golf für ein Kundenevent?', 'Sehr gut. Die ungezwungene Atmosphäre auf dem Platz öffnet Gespräche wie kaum ein anderes Format. Auch Kunden ohne Golferfahrung sind über einen Schnupperteil schnell dabei.' ), $faq( 'Lässt sich ein Rahmenprogramm ergänzen?', 'Ja, gern — Catering, Dinner, ein kleines Turnier oder Hospitality-Elemente kombinieren wir passend zu euren Gästen.' ), $f_fast, $f_bill ],
			'types'   => [ 'kundenevent' ],
		],
		'incentive' => [
			'name'    => 'Golf-Incentive',
			'eyebrow' => 'Format · Incentive',
			'h1'      => 'Golf-Incentives für Unternehmen',
			'lead'    => 'Belohnen und verbinden: ein- oder mehrtägige Incentives an besonderen Orten.',
			'intro'   => 'Ein Golf-Incentive belohnt Leistung und stärkt die Bindung — mit einem Erlebnis statt einem Bonus auf dem Konto. Wir verbinden Golf an reizvollen Anlagen mit Hotellerie, Rahmenprogramm und Verpflegung zu einem runden Paket, ein- oder mehrtägig. Besonders Regionen wie der Tegernsee bieten dafür eine Kulisse, die lange nachwirkt. Von der Idee bis zur Abrechnung organisieren wir alles.',
			'reasons' => [ $reason( 'mountain', 'Besondere Kulissen', 'Anlagen mit Berg- und Seeblick, die ein Incentive zum echten Erlebnis machen.' ), $reason( 'castle', 'Mehrtägig mit Hotel', 'Golf, Übernachtung, Dinner und Rahmenprogramm an einem Ort — ideal als Offsite-Belohnung.' ), $r_team, $r_one ],
			'faqs'    => [ $faq( 'Geht auch ein mehrtägiges Incentive mit Übernachtung?', 'Ja. Wir verbinden Golf, Hotel, Verpflegung und Rahmenprogramm zu einem runden, mehrtägigen Paket.' ), $f_anf, $f_fast, $f_bill ],
			'types'   => [ 'incentive' ],
		],
		'after-work-golf' => [
			'name'    => 'After-Work Golf',
			'eyebrow' => 'Format · After-Work',
			'h1'      => 'After-Work Golf für Teams',
			'lead'    => 'Ein paar Stunden nach Feierabend auf der Range — der unkomplizierte Teamabend im Grünen.',
			'intro'   => 'After-Work Golf ist das niedrigschwellige Format für zwischendurch: nach Feierabend gemeinsam auf die Driving Range oder den Kurzplatz, mit lockerer Anleitung durch einen Pro und entspanntem Ausklang. Kein ganzer Tag, kein großer Aufwand — und trotzdem ein echtes gemeinsames Erlebnis. Ideal als regelmäßiger Teamabend oder lockerer Einstieg ins Thema Golf.',
			'reasons' => [ $reason( 'clock', 'Nach Feierabend', 'Kompakt am Abend, ohne den Arbeitstag zu sprengen.' ), $reason( 'sun', 'Locker & ungezwungen', 'Range, Kurzspiel und ein entspannter Ausklang — Spaß statt Leistungsdruck.' ), $r_team, $r_one ],
			'faqs'    => [ $f_anf, $faq( 'Eignet sich After-Work Golf als regelmäßiges Format?', 'Ja, gerade dafür. Viele Teams machen daraus einen wiederkehrenden Termin — wir richten Platz und Pro passend zu eurem Rhythmus ein.' ), $f_fast, $f_bill ],
			'types'   => [ 'after_work_golf', 'coaching', 'putting_challenge', 'kurzspiel_challenge' ],
		],
	];
}

/**
 * Öffentliche Events, die einem Format-Hub zugeordnet sind.
 *
 * @return WP_Post[]
 */
function fge_format_events( array $format, int $limit = 6 ): array {
	$types = (array) ( $format['types'] ?? [] );
	if ( empty( $types ) ) {
		return [];
	}
	$posts = get_posts( [
		'post_type'      => 'firmengolf_event',
		'post_status'    => 'publish',
		'posts_per_page' => max( $limit * 3, 12 ),
		'meta_query'     => [
			'relation' => 'AND',
			[ 'key' => '_fge_event_status', 'value' => fge_public_event_statuses(), 'compare' => 'IN' ],
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

add_action( 'init', static function () {
	add_rewrite_rule( '^firmenevent/([^/]+)/?$', 'index.php?fge_format=$matches[1]', 'top' );
} );

add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_format';
	return $vars;
} );

/**
 * 301: alte Schnupperkurs-Landing-URLs (seit Launch indexiert!) auf das
 * Teamevent-Pendant — der Schnupper-/Grundlagenteil lebt dort weiter.
 * Gilt für /firmenevent/schnupperkurs/ UND /golf-events/<stadt>/schnupperkurs/.
 */
add_action( 'template_redirect', static function () {
	if ( get_query_var( 'fge_format' ) !== 'schnupperkurs' ) {
		return;
	}
	$city = (string) get_query_var( 'fge_city' );
	$url  = $city !== ''
		? home_url( '/golf-events/' . rawurlencode( $city ) . '/teamevent/' )
		: home_url( '/firmenevent/teamevent/' );
	wp_safe_redirect( $url, 301 );
	exit;
}, 1 );

add_filter( 'template_include', static function ( $template ) {
	$slug = get_query_var( 'fge_format' );
	if ( ! $slug ) {
		return $template;
	}
	if ( get_query_var( 'fge_city' ) ) {
		return $template; // Format×Stadt-Kombi → citformat-landing.php übernimmt.
	}
	$formats = fge_get_event_format_pages();
	if ( ! isset( $formats[ $slug ] ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return get_query_template( '404' );
	}
	$format_template = locate_template( 'template-format.php' );
	return $format_template ?: $template;
} );

/**
 * Self-heal: Rewrite-Regel anlegen, falls beim Aktivieren nicht geflusht wurde.
 */
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^firmenevent/([^/]+)/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );
