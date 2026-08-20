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
	$team   = $reason( 'users', 'Für jedes Team', 'Ob 10 oder 80 Gäste: Gruppengröße, Ablauf und Catering stellen wir passend zu eurem Anlass zusammen.' );
	$local  = $reason( 'flag', 'Lokale Partnerplätze', 'Wir arbeiten direkt mit den Clubs vor Ort. Kurze Wege, verlässliche Termine, echte Ansprechpartner.' );
	$f_anf  = $faq( 'Müssen unsere Mitarbeitenden Golf spielen können?', 'Nein. Unsere Teamevents starten immer mit einem Schnupper- und Grundlagenteil und sind genau für Teams ohne Vorerfahrung gedacht. Schläger werden gestellt, ein Golflehrer führt euch an, der gemeinsame Tag steht im Vordergrund, nicht das Handicap.' );
	$f_size = $faq( 'Wie groß darf die Gruppe sein?', 'Vom Coaching für zwei Personen bis zum Firmenturnier mit rund 80 Teilnehmenden ist alles möglich. Sag uns einfach eure Gruppengröße in der Anfrage, dann wählen wir Platz und Format passend aus.' );
	$f_fast = static function ( $city ) use ( $faq ) { return $faq( 'Wie schnell bekomme ich eine Rückmeldung?', 'Nach eurer Anfrage meldet sich innerhalb eines Werktags ein persönlicher Ansprechpartner mit konkreten Vorschlägen für Platz, Format und Termin in ' . $city . '.' ); };

	// Preise kommen aus den real buchbaren Angeboten, nicht aus dem Text (Audit 2026-08-12).
	$price_summary = function_exists( 'fge_price_from_summary' ) ? fge_price_from_summary( [
		'Teamevents'      => [ 'teamevent', 'team-building', 'team_challenge', 'azubi_event', 'schnupperkurs', 'schnuppergolf' ],
		'After-Work Golf' => [ 'after_work_golf', 'coaching', 'putting_challenge', 'kurzspiel_challenge' ],
		'Workshoptage'    => [ 'workshop', 'offsite', 'offsite_mit_meeting' ],
		'die Platzreife'  => [ 'platzreife' ],
	] ) : '';

	return [
		'muenchen' => [
			'name' => 'München', 'region' => 'Süd & Oberbayern', 'match' => [ 'München', 'Oberbayern' ],
			// Großraum fürs Zahlenband (Julius, 2026-08-20): breiter als die Karten-Liste,
			// zählt auch Tegernsee & Co. mit, alles in Event-Reichweite ab München.
			'stat_match' => [ 'München', 'Oberbayern', 'Tegernsee', 'Starnberg', 'Erding', 'Eichenried', 'Garmisch' ],
			// Bild der Story-Sektion („Raus aus dem Büro"): St. Eurach mit Alpenkette
			// (Bild von Julius, 2026-08-20; U-Bahn- und Garmisch-Motiv verworfen).
			'story_img'     => 'golfclub-st-eurach.jpg',
			'story_img_alt' => 'Fairway des Golfclubs St. Eurach mit Blick auf die verschneiten Alpen',
			'story_img_tag' => 'Golfclub St. Eurach',
			'intro' => 'München ist Firmenstandort und Naherholung in einem, und kaum eine Stadt hat so viele Top-Plätze direkt vor der Tür. In rund 30 Minuten seid ihr von der Innenstadt im Grünen, zwischen Isar-Auen und Alpenpanorama. Ob Teamevent, Firmenturnier, Kundenevent oder Sommerfest: Wir kennen die passenden Plätze im Münchner Umland.',
			'reasons' => [ $reason( 'clock', '30 Min. ins Grüne', 'Die besten Plätze liegen stadtnah, Eichenried und Co. sind schnell erreichbar, auch mit der S-Bahn.' ), $team, $reason( 'mountain', 'Bergpanorama inklusive', 'An klaren Tagen spielt ihr mit Blick auf die Alpen, ein Erlebnis, das in Erinnerung bleibt.' ), $local ],
			'faqs' => [
				$faq( 'Welche Golfplätze rund um München eignen sich für Firmenevents?', 'In Bayern gibt es über 160 Golfanlagen, viele davon in rund 30 Minuten vom Münchner Stadtkern erreichbar, etwa Richtung Eichenried, Erding oder ins Oberland. Wir schlagen euch je nach Gruppengröße, Anlass und Termin die passenden Plätze vor.' ),
				$faq( 'Was kostet ein Firmenevent auf dem Golfplatz in München?', trim( 'Der Preis hängt von Platz, Gruppengröße und Verpflegung ab. ' . ( $price_summary ? 'Aktuell buchbar: ' . $price_summary . ' pro Person, netto. ' : '' ) . 'Ihr bekommt vorab ein transparentes Angebot mit allen Posten.' ) ),
				$f_anf,
				$faq( 'Erreichen wir die Plätze auch ohne Auto?', 'Ja. Einige Anlagen im Münchner Umland sind mit S-Bahn oder U-Bahn plus kurzem Fußweg erreichbar. Für Gruppen organisieren wir auf Wunsch einen Shuttle direkt ab eurem Büro.' ),
				$faq( 'Wie weit im Voraus sollten wir buchen?', 'Beliebte Termine zwischen Mai und September sind meist 4 bis 6 Wochen im Voraus vergeben. Im Frühjahr und Herbst geht es oft auch kurzfristiger. Schickt uns einfach euren Wunschzeitraum, wir prüfen sofort, was möglich ist.' ),
				$faq( 'Geht ein Golfevent auch nach Feierabend?', 'Sehr gut sogar. After-Work-Golf dauert zwei bis drei Stunden auf Range und Kurzplatz, locker angeleitet und mit entspanntem Ausklang. Viele Münchner Teams machen daraus einen festen Termin.' ),
			],
		],
		'hamburg' => [
			'name' => 'Hamburg', 'region' => 'Nord', 'match' => [ 'Hamburg' ],
			'intro' => 'Hamburg lebt vom Wasser und vom Wind, und genau das macht Golf hier besonders. Die Plätze im Hamburger Umland liegen zwischen Knicks, Wiesen und alten Alleen, viele nur eine kurze Fahrt vom Zentrum. Ob After-Work-Teamevent, Platzreife für die ganze Abteilung oder Workshop mit Golf-Ausklang: Wir organisieren euer Event im Norden von A bis Z.',
			'reasons' => [ $reason( 'clock', 'Stadtnah & erreichbar', 'Die Plätze im Norden Hamburgs sind schnell erreichbar, ideal für ein Event nach Feierabend.' ), $team, $reason( 'castle', 'Workshop & Tagung', 'Einige Anlagen verbinden Konferenzraum, Clubterrasse und Golf an einem Ort, perfekt für Workshops und Strategie-Tage.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze in Hamburg kann ich für ein Firmenevent buchen?', 'Im Hamburger Raum arbeiten wir mit ausgewählten Partnerplätzen. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $faq( 'Geht auch ein Workshop oder eine mehrtägige Tagung mit Übernachtung?', 'Ja. Mehrere Anlagen verbinden Tagungsräume, Golf und Hotel an einem Ort. Wir planen Ablauf, Verpflegung und Golfprogramm gemeinsam mit euch, mehrtägig als individuelles Event.' ), $f_fast( 'Hamburg' ) ],
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
			'intro' => 'Der Tegernsee ist eine der schönsten Kulissen für ein Firmenevent in Deutschland. Golf zwischen Bergen und See, dazu erstklassige Hotellerie, das macht die Region ideal für Incentives, Strategie-Tage und besondere Kundenevents. Wir organisieren euer Event auf den Plätzen rund um den Tegernsee.',
			'reasons' => [ $reason( 'mountain', 'Berg- und Seekulisse', 'Golf vor Alpenpanorama, ein Rahmen, der bei Kunden und Teams lange nachwirkt.' ), $team, $reason( 'castle', 'Hotellerie vor Ort', 'Erstklassige Häuser direkt am See, ideal, wenn euer Event über einen Tag hinausgeht.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze am Tegernsee kann ich für ein Firmenevent buchen?', 'In der Region Tegernsee arbeiten wir mit Partnerplätzen mit besonderer Kulisse. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $faq( 'Geht auch ein mehrtägiges Incentive mit Übernachtung?', 'Ja, gerade dafür ist die Region ideal. Wir verbinden Golf, Hotel, Rahmenprogramm und Verpflegung zu einem runden Erlebnis.' ), $f_anf, $f_fast( 'die Region Tegernsee' ) ],
		],
		'augsburg' => [
			'name' => 'Augsburg', 'region' => 'Bayerisch-Schwaben', 'match' => [ 'Augsburg' ],
			'intro' => 'Augsburg liegt zwischen Lech und Wertach, und die Golfplätze in Bayerisch-Schwaben sind aus der Stadt in kurzer Zeit erreichbar. Die Anlagen im Umland liegen in ruhiger Park- und Flusslandschaft, ideal für einen Tag raus aus dem Büro. Ob Teamevent, Firmenturnier oder Kundenevent: Wir organisieren euer Event auf den passenden Plätzen rund um Augsburg.',
			'reasons' => [ $reason( 'clock', 'Schnell im Grünen', 'Die Plätze rund um Augsburg liegen nah an Stadt und A8, auch aus München gut erreichbar.' ), $team, $reason( 'leaf', 'Fluss- und Parklandschaft', 'Golf zwischen Lechauen und Stauden, ein ruhiger Rahmen für Teams und Kunden.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Augsburg eignen sich für ein Firmenevent?', 'Rund um Augsburg liegen mehrere Golfanlagen in Bayerisch-Schwaben, von der Übungsanlage bis zum 18-Loch-Platz. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Augsburg' ) ],
		],
		'bonn' => [
			'name' => 'Bonn', 'region' => 'Rheinland', 'match' => [ 'Bonn' ],
			'intro' => 'Bonn verbindet Rheintal, Siebengebirge und Kottenforst, und genau in dieser Landschaft liegen die Golfplätze der Region. Für Unternehmen, Verbände und Organisationen in der Stadt ist Golf ein entspannter Rahmen, um Teams und Gäste zusammenzubringen. Vom Teamtag bis zum Kundenevent mit Blick auf den Rhein organisieren wir euer Event rund um Bonn.',
			'reasons' => [ $reason( 'clock', 'Nah an Stadt und Rhein', 'Die Anlagen rund um Bonn sind aus dem Zentrum und aus Köln schnell erreichbar.' ), $team, $reason( 'leaf', 'Rheintal & Siebengebirge', 'Golf mit Blick ins Rheintal, ein Rahmen, der bei Gästen in Erinnerung bleibt.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze in Bonn kann ich für ein Firmenevent buchen?', 'Im Raum Bonn liegen mehrere Anlagen zwischen Rheintal, Vorgebirge und Bergischem Land. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $faq( 'Eignet sich Golf für ein Kundenevent?', 'Sehr gut. Ein paar Stunden auf dem Platz schaffen Gespräche, die im Besprechungsraum nie entstehen. Wir kombinieren das gern mit Catering, Dinner oder einem kleinen Wettbewerb.' ), $f_anf, $f_fast( 'Bonn' ) ],
		],
		'bremen' => [
			'name' => 'Bremen', 'region' => 'Nordwest', 'match' => [ 'Bremen' ],
			'intro' => 'Bremen ist Hansestadt mit kurzen Wegen, und das gilt auch für Golf. Die Plätze im Umland liegen flach in der norddeutschen Wiesen- und Marschlandschaft, gut erreichbar aus der Stadt und aus dem Speckgürtel. Ob After-Work-Runde, Teamevent oder Firmenturnier: Wir organisieren euer Event im Nordwesten von A bis Z.',
			'reasons' => [ $reason( 'clock', 'Kurze Wege', 'Die Anlagen rund um Bremen sind aus der Innenstadt und aus dem Umland schnell erreichbar.' ), $team, $reason( 'leaf', 'Weites Land', 'Flache, weitläufige Plätze zwischen Wiesen und Wasserzügen, typisch Norddeutschland.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Bremen kann ich für ein Firmenevent buchen?', 'Im Bremer Umland liegen mehrere Anlagen zwischen Weser und Wümme. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Bremen' ) ],
		],
		'dortmund' => [
			'name' => 'Dortmund', 'region' => 'Ruhrgebiet & Westfalen', 'match' => [ 'Dortmund' ],
			'intro' => 'Dortmund ist längst grüner, als viele denken, und im Süden der Stadt beginnt mit dem Ardeygebirge und dem Sauerland eine echte Golflandschaft. Für Unternehmen im östlichen Ruhrgebiet heißt das kurze Wege zu abwechslungsreichen Plätzen. Vom Teamevent bis zum Firmenturnier organisieren wir euer Event rund um Dortmund.',
			'reasons' => [ $reason( 'clock', 'Ab ins Grüne', 'Die Plätze im Dortmunder Süden und Richtung Sauerland sind in kurzer Fahrzeit erreichbar.' ), $team, $reason( 'flag', 'Dichte Platzauswahl', 'Nordrhein-Westfalen hat eine der dichtesten Golflandschaften Deutschlands, entsprechend groß ist die Auswahl.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze in Dortmund kann ich für ein Firmenevent buchen?', 'Rund um Dortmund liegen mehrere Anlagen, vor allem im grünen Süden der Stadt und Richtung Sauerland. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Dortmund' ) ],
		],
		'dresden' => [
			'name' => 'Dresden', 'region' => 'Sachsen', 'match' => [ 'Dresden' ],
			'intro' => 'Dresden bietet mit Elbtal, Weinhängen und der Nähe zur Sächsischen Schweiz eine Kulisse, die auch ein Firmenevent besonders macht. Die Golfplätze der Region liegen ruhig im Umland und sind aus der Stadt gut erreichbar. Ob Teamtag, Kundenevent oder Turnier: Wir organisieren euer Event rund um Dresden.',
			'reasons' => [ $reason( 'clock', 'Raus aus der Stadt', 'Die Anlagen im Dresdner Umland sind in kurzer Fahrzeit erreichbar, auch für Gäste von außerhalb.' ), $team, $reason( 'castle', 'Elbtal-Kulisse', 'Barockstadt, Weinhänge und Elbtal, ein Rahmen, der bei Kunden nachwirkt.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Dresden kann ich für ein Firmenevent buchen?', 'Im Dresdner Umland liegen mehrere Anlagen zwischen Elbtal und sächsischem Hügelland. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Dresden' ) ],
		],
		'erlangen' => [
			'name' => 'Erlangen', 'region' => 'Franken', 'match' => [ 'Erlangen' ],
			'intro' => 'Erlangen gehört mit Nürnberg und Fürth zu einer der stärksten Wirtschaftsregionen Bayerns, und die fränkischen Golfplätze liegen direkt vor der Tür. Zwischen Regnitzgrund und Fränkischer Schweiz findet ihr Anlagen für jeden Anlass. Vom Teamevent für die Abteilung bis zum Kundenturnier organisieren wir euer Event rund um Erlangen.',
			'reasons' => [ $reason( 'clock', 'Mitten in der Metropolregion', 'Die Plätze in Franken sind aus Erlangen, Nürnberg und Fürth schnell erreichbar.' ), $team, $reason( 'leaf', 'Fränkische Landschaft', 'Golf zwischen Regnitzgrund, Wäldern und der Fränkischen Schweiz, ruhig und abwechslungsreich.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Erlangen kann ich für ein Firmenevent buchen?', 'In der Metropolregion Nürnberg liegen mehrere Anlagen in kurzer Fahrzeit. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Erlangen' ) ],
		],
		'essen' => [
			'name' => 'Essen', 'region' => 'Ruhrgebiet', 'match' => [ 'Essen' ],
			'intro' => 'Essen liegt mitten im Ruhrgebiet und ist zugleich eine der grünsten Großstädte Deutschlands. Rund um Baldeneysee und Ruhrtal liegen Golfanlagen, die man so mitten im Revier nicht erwartet. Für Teams und Kunden aus der Region organisieren wir euer Firmenevent auf den passenden Plätzen rund um Essen.',
			'reasons' => [ $reason( 'clock', 'Mitten im Revier', 'Die Anlagen an Ruhr und Baldeneysee sind aus Essen und den Nachbarstädten schnell erreichbar.' ), $team, $reason( 'leaf', 'Grünes Ruhrgebiet', 'Golf im Ruhrtal zeigt das Revier von seiner grünen Seite, ein Kontrast, der überrascht.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze in Essen kann ich für ein Firmenevent buchen?', 'Rund um Essen liegen mehrere Anlagen im Ruhrtal und in den Nachbarstädten. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Essen' ) ],
		],
		'garmisch-partenkirchen' => [
			'name' => 'Garmisch-Partenkirchen', 'region' => 'Oberbayern', 'match' => [ 'Garmisch-Partenkirchen', 'Garmisch' ],
			'intro' => 'Garmisch-Partenkirchen liegt am Fuß der Zugspitze, und Golf im Werdenfelser Land gehört zu den eindrucksvollsten Erlebnissen, die ihr einem Team oder Kunden bieten könnt. Die Region ist ideal für Incentives, Strategie-Tage und mehrtägige Events mit Hotel. Wir organisieren euer Firmenevent zwischen Bergen und Wiesen rund um Garmisch.',
			'reasons' => [ $reason( 'mountain', 'Zugspitz-Kulisse', 'Golf direkt vor Deutschlands höchstem Berg, ein Rahmen, den niemand vergisst.' ), $team, $reason( 'castle', 'Ideal für Incentives', 'Hotels, Hütten und Rahmenprogramm vor Ort, perfekt für ein- oder mehrtägige Events.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze in Garmisch-Partenkirchen kann ich für ein Firmenevent buchen?', 'Im Werdenfelser Land und im Oberland liegen Anlagen mit besonderer Bergkulisse. Je nach Anlass, Gruppe und Termin schlagen wir euch die passende vor.' ), $faq( 'Geht auch ein mehrtägiges Incentive mit Übernachtung?', 'Ja, gerade dafür ist die Region ideal. Wir verbinden Golf, Hotel, Rahmenprogramm und Verpflegung zu einem runden Erlebnis.' ), $f_anf, $f_fast( 'Garmisch-Partenkirchen' ) ],
		],
		'ingolstadt' => [
			'name' => 'Ingolstadt', 'region' => 'Bayern', 'match' => [ 'Ingolstadt' ],
			'intro' => 'Ingolstadt liegt an der Donau auf halbem Weg zwischen München und Nürnberg, und die Golfplätze der Region sind aus der Stadt in kurzer Zeit erreichbar. Zwischen Donauauen und Hügelland findet ihr ruhige Anlagen für Teams und Kunden. Vom Teamevent bis zum Firmenturnier organisieren wir euer Event rund um Ingolstadt.',
			'reasons' => [ $reason( 'clock', 'Zentral in Bayern', 'Die Plätze rund um Ingolstadt liegen nah an A9 und Donau, gut erreichbar auch für Gäste.' ), $team, $reason( 'leaf', 'Donauauen & Hügelland', 'Golf in ruhiger Flusslandschaft, ein entspannter Kontrast zum Werksalltag.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Ingolstadt kann ich für ein Firmenevent buchen?', 'Rund um Ingolstadt liegen mehrere Anlagen zwischen Donau, Altmühltal und Hügelland. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Ingolstadt' ) ],
		],
		'itzehoe' => [
			'name' => 'Itzehoe', 'region' => 'Schleswig-Holstein', 'match' => [ 'Itzehoe' ],
			'intro' => 'Itzehoe liegt an der Stör mitten in Schleswig-Holstein, zwischen Knicks, Marsch und Geest. Golf gehört hier fest zur Region, und die Anlagen im Umland sind auch aus Hamburg schnell erreichbar. Ob Teamevent, After-Work-Runde oder Firmenturnier: Wir organisieren euer Event im echten Norden.',
			'reasons' => [ $reason( 'clock', 'Nah an Hamburg', 'Die Region ist über die A23 schnell erreichbar, auch für Teams aus dem Hamburger Raum.' ), $team, $reason( 'leaf', 'Knicks & Marsch', 'Golf in der typischen Landschaft Schleswig-Holsteins, ruhig, grün und weit.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Itzehoe kann ich für ein Firmenevent buchen?', 'Rund um Itzehoe liegen mehrere Anlagen zwischen Marsch und Geest. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Itzehoe' ) ],
		],
		'karlsruhe' => [
			'name' => 'Karlsruhe', 'region' => 'Baden', 'match' => [ 'Karlsruhe' ],
			'intro' => 'Karlsruhe liegt in einer der wärmsten Regionen Deutschlands, und das verlängert die Golfsaison spürbar. Zwischen Rheinebene, Kraichgau und Schwarzwaldrand findet ihr Anlagen für jeden Anlass, gut erreichbar aus der Technologieregion. Vom Teamtag bis zum Kundenturnier organisieren wir euer Firmenevent rund um Karlsruhe.',
			'reasons' => [ $reason( 'clock', 'Lange Saison', 'Das milde badische Klima macht Events vom Frühjahr bis weit in den Herbst planbar.' ), $team, $reason( 'leaf', 'Rheinebene & Schwarzwaldrand', 'Golf zwischen Reben, Wald und Ebene, viel Abwechslung auf kurzer Distanz.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Karlsruhe kann ich für ein Firmenevent buchen?', 'In der Region zwischen Rheinebene, Kraichgau und Nordschwarzwald liegen mehrere Anlagen. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Karlsruhe' ) ],
		],
		'kiel' => [
			'name' => 'Kiel', 'region' => 'Schleswig-Holstein', 'match' => [ 'Kiel' ],
			'intro' => 'Kiel liegt an der Förde, und Golf hat hier immer eine Brise Ostsee dabei. Die Anlagen rund um die Landeshauptstadt liegen zwischen Küste, Knicks und Hügelland, viele nur eine kurze Fahrt vom Wasser entfernt. Ob Teamevent, Platzreife oder Firmenturnier: Wir organisieren euer Event im Norden von A bis Z.',
			'reasons' => [ $reason( 'flag', 'Golf an der Küste', 'Seeluft und weite Plätze machen jedes Event besonders, typisch für den Kieler Raum.' ), $team, $reason( 'clock', 'Kurze Wege', 'Die Anlagen rund um Kiel sind aus der Stadt und von der Förde schnell erreichbar.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Kiel kann ich für ein Firmenevent buchen?', 'Rund um Kiel und entlang der Ostseeküste liegen mehrere Anlagen. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Kiel' ) ],
		],
		'landshut' => [
			'name' => 'Landshut', 'region' => 'Niederbayern', 'match' => [ 'Landshut' ],
			'intro' => 'Landshut verbindet niederbayerisches Hügelland mit der Nähe zu München und zum Flughafen. Die Golfplätze der Region liegen ruhig zwischen Isartal und Hopfenland und sind aus der Stadt schnell erreichbar. Vom Teamevent bis zum Kundenevent organisieren wir euer Firmenevent rund um Landshut.',
			'reasons' => [ $reason( 'clock', 'Nah an München & Flughafen', 'Die Region ist auch für Gäste von außerhalb schnell erreichbar, ideal für gemischte Runden.' ), $team, $reason( 'leaf', 'Isartal & Hügelland', 'Golf in ruhiger niederbayerischer Landschaft, weit weg vom Alltagslärm.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Landshut kann ich für ein Firmenevent buchen?', 'Rund um Landshut liegen mehrere Anlagen im niederbayerischen Hügelland. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Landshut' ) ],
		],
		'leipzig' => [
			'name' => 'Leipzig', 'region' => 'Sachsen', 'match' => [ 'Leipzig' ],
			'intro' => 'Leipzig wächst wie kaum eine andere Stadt im Osten, und mit dem Neuseenland ist direkt vor der Tür eine ganze Freizeitlandschaft entstanden. Auch Golf gehört dazu: Die Anlagen der Region liegen zwischen Seen, Auenwald und offenem Land. Vom Teamevent bis zum Firmenturnier organisieren wir euer Event rund um Leipzig.',
			'reasons' => [ $reason( 'clock', 'Schnell am See', 'Die Plätze im Leipziger Umland und im Neuseenland sind in kurzer Fahrzeit erreichbar.' ), $team, $reason( 'leaf', 'Neuseenland & Auenwald', 'Golf in junger, weiter Landschaft, perfekt kombinierbar mit einem Ausklang am Wasser.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Leipzig kann ich für ein Firmenevent buchen?', 'Im Leipziger Umland liegen mehrere Anlagen zwischen Neuseenland und sächsischem Flachland. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Leipzig' ) ],
		],
		'luebeck' => [
			'name' => 'Lübeck', 'region' => 'Schleswig-Holstein', 'match' => [ 'Lübeck' ],
			'intro' => 'Lübeck verbindet Hansestadt-Flair mit der Nähe zur Ostsee, und die Golfplätze der Region liegen zwischen Küste, Trave und Holsteinischer Schweiz. Für Teams und Kunden ist das eine Kulisse, die aus einem Golftag ein Erlebnis macht. Ob Teamevent, Kundenevent oder Turnier: Wir organisieren euer Event rund um Lübeck.',
			'reasons' => [ $reason( 'flag', 'Ostsee vor der Tür', 'Golf mit Seeluft, danach ein Ausklang Richtung Küste, das bleibt in Erinnerung.' ), $team, $reason( 'castle', 'Hansestadt-Kulisse', 'Die Lübecker Altstadt und die Küste geben eurem Event einen besonderen Rahmen.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Lübeck kann ich für ein Firmenevent buchen?', 'Rund um Lübeck und Richtung Ostsee liegen mehrere Anlagen. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Lübeck' ) ],
		],
		'lueneburg' => [
			'name' => 'Lüneburg', 'region' => 'Niedersachsen', 'match' => [ 'Lüneburg' ],
			'intro' => 'Lüneburg liegt am Rand der Heide und ist von Hamburg aus schnell erreichbar. Die Golfplätze der Region liegen ruhig zwischen Ilmenau, Wäldern und Heideflächen, ideal für einen Tag mit Abstand zum Alltag. Vom Teamevent bis zum Firmenturnier organisieren wir euer Event rund um Lüneburg.',
			'reasons' => [ $reason( 'leaf', 'Heide & Wälder', 'Golf in der ruhigen Landschaft der Lüneburger Heide, entschleunigend und grün.' ), $team, $reason( 'clock', 'Nah an Hamburg', 'Die Region ist aus Hamburg und dem südlichen Umland schnell erreichbar.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Lüneburg kann ich für ein Firmenevent buchen?', 'Rund um Lüneburg und in der Heide liegen mehrere Anlagen. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Lüneburg' ) ],
		],
		'mannheim' => [
			'name' => 'Mannheim', 'region' => 'Rhein-Neckar', 'match' => [ 'Mannheim' ],
			'intro' => 'Mannheim ist das Zentrum der Metropolregion Rhein-Neckar, und die Golfplätze liegen hier gleich in mehreren Richtungen: in der Rheinebene, an der Bergstraße und Richtung Pfalz. Für Kunden- und Teamevents heißt das viel Auswahl bei kurzen Wegen. Wir richten euer Firmenevent auf den passenden Plätzen rund um Mannheim aus.',
			'reasons' => [ $reason( 'clock', 'Kurze Wege', 'In der Metropolregion Rhein-Neckar ist der passende Platz nie weit, auch für Gäste per Bahn.' ), $team, $reason( 'gift', 'Stark für Kundenevents', 'Golf passt perfekt zu Hospitality und Kundenbindung, gern mit Dinner und Rahmenprogramm.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Mannheim kann ich für ein Firmenevent buchen?', 'In der Rhein-Neckar-Region liegen mehrere Anlagen zwischen Rheinebene, Bergstraße und Pfalz. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Mannheim' ) ],
		],
		'penzberg' => [
			'name' => 'Penzberg', 'region' => 'Oberbayern', 'match' => [ 'Penzberg' ],
			'intro' => 'Penzberg liegt im Oberland zwischen Starnberger See, Osterseen und den Bergen, und viele Unternehmen der Region suchen genau hier den Ausgleich zum dichten Arbeitsalltag. Die Golfanlagen des Oberlands liegen nur wenige Minuten entfernt, mit Blick auf die Alpen. Vom Teamevent bis zum Incentive organisieren wir euer Event rund um Penzberg.',
			'reasons' => [ $reason( 'mountain', 'Alpenblick inklusive', 'Die Plätze im Oberland spielen sich vor Bergpanorama, ein Rahmen, der motiviert.' ), $team, $reason( 'clock', 'Direkt vor der Tür', 'Für Teams aus dem Oberland und dem Münchner Süden sind die Anlagen schnell erreichbar.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Penzberg kann ich für ein Firmenevent buchen?', 'Im Oberland zwischen Starnberger See und Alpenrand liegen mehrere Anlagen. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Penzberg' ) ],
		],
		'regensburg' => [
			'name' => 'Regensburg', 'region' => 'Ostbayern', 'match' => [ 'Regensburg' ],
			'intro' => 'Regensburg verbindet Welterbe-Altstadt mit einer der dynamischsten Wirtschaftsregionen Bayerns. Die Golfplätze Ostbayerns liegen zwischen Donautal, Jura und Vorwald, ruhig und gut erreichbar. Ob Teamevent, Kundenevent an der Donau oder Firmenturnier: Wir organisieren euer Event rund um Regensburg.',
			'reasons' => [ $reason( 'clock', 'Schnell im Grünen', 'Die Anlagen rund um Regensburg sind aus der Stadt und über die A3 gut erreichbar.' ), $team, $reason( 'castle', 'Welterbe-Kulisse', 'Altstadt und Donau geben einem Kundenevent einen Rahmen, der hängen bleibt.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Regensburg kann ich für ein Firmenevent buchen?', 'In Ostbayern liegen mehrere Anlagen zwischen Donautal, Jura und Bayerischem Wald. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Regensburg' ) ],
		],
		'rosenheim' => [
			'name' => 'Rosenheim', 'region' => 'Oberbayern', 'match' => [ 'Rosenheim' ],
			'intro' => 'Rosenheim liegt im Inntal zwischen München, Chiemsee und den Bergen, und die Golfplätze der Region spielen sich vor echter Alpenkulisse. Für Teams und Kunden aus dem südostbayerischen Raum sind die Anlagen schnell erreichbar. Vom Teamevent bis zum Incentive mit Bergblick organisieren wir euer Event rund um Rosenheim.',
			'reasons' => [ $reason( 'mountain', 'Berge & Chiemsee', 'Golf zwischen Inntal und Chiemgau, an klaren Tagen mit Alpenpanorama.' ), $team, $reason( 'clock', 'Gut angebunden', 'Über A8 und A93 sind die Plätze auch aus München und Salzburg schnell erreichbar.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Rosenheim kann ich für ein Firmenevent buchen?', 'Zwischen Inntal, Chiemgau und Mangfalltal liegen mehrere Anlagen. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Rosenheim' ) ],
		],
		'ulm' => [
			'name' => 'Ulm', 'region' => 'Schwaben', 'match' => [ 'Ulm' ],
			'intro' => 'Ulm liegt an der Donau zwischen Schwäbischer Alb und Oberschwaben, und beide Richtungen bieten Golfplätze in ruhiger Lage. Für Unternehmen aus der Doppelstadt Ulm und Neu-Ulm heißt das kurze Wege zu abwechslungsreichen Anlagen. Vom Teamtag bis zum Firmenturnier organisieren wir euer Event rund um Ulm.',
			'reasons' => [ $reason( 'clock', 'Zwischen Alb und Donau', 'Die Plätze rund um Ulm sind aus der Stadt und über die A8 schnell erreichbar.' ), $team, $reason( 'leaf', 'Alb & Oberschwaben', 'Golf auf der Höhe der Alb oder im sanften Oberschwaben, beides nur eine kurze Fahrt entfernt.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Ulm kann ich für ein Firmenevent buchen?', 'Rund um Ulm liegen mehrere Anlagen auf der Schwäbischen Alb und in Oberschwaben. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Ulm' ) ],
		],
		'wuerzburg' => [
			'name' => 'Würzburg', 'region' => 'Franken', 'match' => [ 'Würzburg' ],
			'intro' => 'Würzburg liegt am Main zwischen Weinbergen, und diese Landschaft macht auch ein Firmenevent besonders. Die Golfplätze Mainfrankens liegen ruhig im Umland und sind aus der Stadt schnell erreichbar. Ob Teamevent, Kundenevent mit Weinprobe zum Ausklang oder Firmenturnier: Wir organisieren euer Event rund um Würzburg.',
			'reasons' => [ $reason( 'leaf', 'Main & Weinberge', 'Golf in Mainfranken, danach ein Ausklang mit Blick auf die Reben, das bleibt in Erinnerung.' ), $team, $reason( 'clock', 'Zentral gelegen', 'Würzburg liegt am Kreuz von A3 und A7, gut erreichbar auch für Gäste aus mehreren Regionen.' ), $local ],
			'faqs' => [ $faq( 'Welche Golfplätze bei Würzburg kann ich für ein Firmenevent buchen?', 'In Mainfranken liegen mehrere Anlagen zwischen Maintal und fränkischem Hügelland. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden vor.' ), $f_anf, $f_size, $f_fast( 'Würzburg' ) ],
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

/**
 * Zahl fürs Stadt-Zahlenband: öffentliche Events im GROSSRAUM der Stadt.
 * Nutzt die erweiterten Begriffe aus `stat_match` (z. B. München inkl. Tegernsee
 * und Erding), sonst `match`. Bewusst breiter als die Karten-Liste, die Zahl
 * beantwortet „wie viel Auswahl habe ich hier", nicht „was zeigt das Grid".
 */
function fge_city_stat_events_count( array $city ): int {
	$stat_city = $city;
	if ( ! empty( $city['stat_match'] ) ) {
		$stat_city['match'] = $city['stat_match'];
	}
	return count( fge_city_events( $stat_city, 999 ) );
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
