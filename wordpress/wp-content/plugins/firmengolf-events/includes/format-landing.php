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
 * Preis-FAQ aus den real buchbaren Angeboten (Audit 2026-08-12: die früher
 * hartkodierten 190 €/56 € widersprachen den eigenen Events). Gibt es gerade
 * kein Pro-Person-Angebot, nennt die Antwort bewusst gar keine Zahl.
 *
 * @param string   $question Fragetext.
 * @param string   $plural   Formatname im Plural, z. B. „Golf-Teamevents".
 * @param string[] $types    Event-Typ-Keys des Formats.
 * @param string   $tail     Optionaler Zusatzsatz am Ende.
 * @return array{q:string,a:string,link?:array{url:string,label:string}}
 */
function fge_format_price_faq( string $question, string $plural, array $types, string $tail = '' ): array {
	$range = function_exists( 'fge_format_price_range' ) ? fge_format_price_range( $types ) : null;

	if ( ! $range ) {
		$answer = 'Das hängt von Platz, Gruppengröße und Leistungen ab. Sag uns euren Rahmen, dann bekommt ihr innerhalb eines Werktags ein konkretes Angebot mit allen Posten.';
		return [ 'q' => $question, 'a' => trim( $answer . ' ' . $tail ) ];
	}

	$min = fge_price_eur( $range['min'] );
	if ( $range['max'] > $range['min'] ) {
		$answer = sprintf(
			'Das hängt von Platz, Leistungen und Verpflegung ab. Aktuell liegen unsere buchbaren %s zwischen %s und %s pro Person, netto. Am günstigsten ist gerade: %s.',
			$plural,
			$min,
			fge_price_eur( $range['max'] ),
			$range['min_title']
		);
	} else {
		$answer = sprintf(
			'Das hängt von Platz, Leistungen und Verpflegung ab. Aktuell buchbar: %s für %s pro Person, netto.',
			$range['min_title'],
			$min
		);
	}

	return [
		'q'    => $question,
		'a'    => trim( $answer . ' ' . $tail ),
		'link' => [ 'url' => $range['min_url'], 'label' => 'Günstigstes Angebot ansehen' ],
	];
}

/**
 * Format-Konfiguration, keyed by URL-Slug. `types` listet die event_type-Meta-Werte
 * (inkl. Legacy-Keys), die diesem Hub zugeordnet werden. Jeder Hub bringt eigenen
 * Inhalt (Intro, Gründe, FAQ) für nicht-dünnen programmatischen SEO-Content.
 */
function fge_get_event_format_pages(): array {
	$reason = static function ( $ic, $t, $b ) { return [ 'ic' => $ic, 't' => $t, 'b' => $b ]; };
	$faq    = static function ( $q, $a ) { return [ 'q' => $q, 'a' => $a ]; };

	// Event-Typen je Hub vorab, damit Preisrechnung und `types` dieselbe Quelle nutzen.
	$t_team     = [ 'teamevent', 'team-building', 'team_challenge', 'azubi_event', 'schnupperkurs', 'schnuppergolf' ];
	$t_workshop = [ 'workshop', 'offsite', 'offsite_mit_meeting' ];
	$t_turnier  = [ 'firmen_golfturnier', 'firmenturnier', '9hole_turnier', '18hole_turnier' ];
	$t_kunden   = [ 'kundenevent' ];
	// After-Work listet auch die Teamevents mit: jedes Teamevent ist als
	// After-Work-Termin buchbar (Julius, 2026-08-20). Kern-Typen stehen vorn,
	// die Reihenfolge steuert die Sortierung der Format-Seiten.
	$t_afterw   = array_merge( [ 'after_work_golf', 'coaching', 'putting_challenge', 'kurzspiel_challenge' ], $t_team );

	$from_team     = function_exists( 'fge_format_price_from_label' ) ? fge_format_price_from_label( $t_team ) : '';
	$from_workshop = function_exists( 'fge_format_price_from_label' ) ? fge_format_price_from_label( $t_workshop ) : '';

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
			'lead'    => 'Ein gemeinsamer Tag auf dem Platz, der euer Team wirklich zusammenbringt, auch ohne Golferfahrung.',
			'intro'   => 'Ein Golf-Teamevent holt euer Team raus aus dem Büro und rein ins Grüne. Anders als beim klassischen Teambuilding entsteht hier ganz nebenbei Nähe: gemeinsam üben, lachen, anfeuern. Ein Schnupper- und Grundlagenkurs ist immer dabei: Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt. Wir stellen Platz, Ablauf und Verpflegung passend zu eurer Gruppe zusammen, deutschlandweit.',
			// Keine 'reasons' mehr: Inhalte stecken in den Icon-Facts direkt unter dem Hero
			// (Feedback Julius 2026-08-10, eine Kachel-Reihe statt zwei Fakten-Blöcke).
			'faqs'    => [ $f_anf, $faq( 'Wie groß darf das Team sein?', 'Von der kleinen Abteilung bis zu rund 80 Teilnehmenden ist alles möglich. Sag uns eure Gruppengröße, dann wählen wir Platz und Format passend aus.' ), fge_format_price_faq( 'Was kostet ein Golf-Teamevent?', 'Golf-Teamevents', $t_team, 'Der reine Grundlagenkurs mit eigener Anreise ist immer die günstigste Variante, Verpflegung, Turnier und Rahmenprogramm kommen nach Wunsch dazu. Wenn dir ein Event gefällt, du aber nur den reinen Kurs möchtest, frag es einfach an und schreib das dazu.' ), $f_fast, $f_bill ],
			'types'   => $t_team,
			// Landingpage-Ausbau (Google-Ads-Kampagne 2026-08): Quick-Facts, Tagesablauf,
			// Level-Sektion, eigenes Hero-Bild. Formate ohne diese Keys rendern wie bisher.
			'hero_img' => 'firmenevent-afterwork-golf.jpg',
			'levels'  => true,
			// Icon-Kacheln direkt unter dem Hero (volle Container-Breite, ersetzt die
			// frühere Facts-Leiste UND die Gründe-Sektion weiter unten).
			'facts'   => [
				[ 'ic' => 'users', 't' => 'Für jedes Level', 'b' => 'Ohne Vorkenntnisse, von 6 bis 80 Personen. Schläger werden gestellt, ein Golflehrer führt an.' ],
				[ 'ic' => 'clock', 't' => 'Halber oder ganzer Tag', 'b' => 'Vom kompakten Nachmittag bis zum vollen Eventtag, passend zu Kalender und Budget.' ],
				[ 'ic' => 'gift', 't' => $from_team ?: 'Passend zum Budget', 'b' => 'Vom reinen Grundlagenkurs mit eigener Anreise bis zum Rundum-Teamevent mit Verpflegung.' ],
				[ 'ic' => 'flag', 't' => 'Eine Anfrage, ein Kontakt', 'b' => 'Platzwahl, Format, Catering und Abrechnung über einen einzigen Ansprechpartner.' ],
			],
			'flow'    => [
				[ 't' => 'Ankunft & Welcome', 'b' => 'Der Golfclub empfängt dich und du bekommst dein Leih-Equipment.' ],
				[ 't' => 'Golf-Grundlagenkurs im Team', 'b' => 'In kleinen Gruppen lernt ihr die Grundlagen des Golfsports, locker und ohne Druck.' ],
				[ 't' => 'Mittagessen auf der Clubterrasse', 'b' => 'Von Barbecue bis Pasta-Party ist alles möglich.' ],
				[ 't' => 'Longest Drive & Putt-Turnier', 'b' => 'Der Abschluss mit Wettbewerb: Wer schlägt am weitesten, wer puttet am sichersten?' ],
				[ 't' => 'Gemeinsamer Ausklang', 'b' => 'Entspannter Abschluss auf der Clubterrasse, danach individuelle Abreise.' ],
			],
		],
		'golfturnier' => [
			'name'    => 'Firmen-Golfturnier',
			'eyebrow' => 'Format · Turnier',
			'h1'      => 'Firmen-Golfturniere organisieren',
			'lead'    => 'Vom 9-Loch-Schnupperturnier bis zum großen 18-Loch-Firmencup mit Siegerehrung.',
			'intro'   => 'Ein Firmen-Golfturnier ist der Klassiker für Kundenbindung und Teamwettbewerb. Wir richten es komplett aus: Startlisten, Zählweise (auch Scramble für gemischte Level), Bewirtung am Platz und eine stimmungsvolle Siegerehrung. Auf Wunsch mit Sponsoren-Branding, Goodie-Bags und Rahmenprogramm. So wird aus einem Spieltag ein Firmenevent, über das man noch lange spricht.',
			'facts' => [ $reason( 'trophy', 'Echter Wettbewerb', 'Faire Zählformate für gemischte Level, inkl. Scramble, alle spielen mit, vom Anfänger bis zum Single-Handicap.' ), $reason( 'gift', 'Siegerehrung & Branding', 'Pokale, Preise, Sponsorenlogos und Goodie-Bags, auf Wunsch in eurem Corporate Design.' ), $reason( 'users', 'Auch für große Felder', 'Vom 20er-Flight bis zum großen Firmencup planen wir Startzeiten und Logistik.' ), $r_one ],
			'faqs'    => [ $faq( 'Können auch Anfänger an einem Firmenturnier teilnehmen?', 'Ja. Mit dem Scramble-Format spielen gemischte Teams gemeinsam, Erfahrene tragen das Feld, Einsteigende sind voll dabei. So wird niemand vorgeführt.' ), $faq( 'Übernehmt ihr die komplette Organisation?', 'Ja, von Startliste und Zählweise über Bewirtung bis zur Siegerehrung. Ihr müsst nur erscheinen und spielen.' ), $f_fast, $f_bill ],
			'hero_img' => 'pool/turnier-abschlag-eventszene.jpg',
			'flow_h'   => 'Vom Kanonenstart bis zur Siegerehrung.',
			'flow'     => [
				[ 't' => 'Empfang & Startlisten', 'b' => 'Ankommen am Clubhaus, Begrüßung, Flights und Zählformat werden erklärt.' ],
				[ 't' => 'Kanonenstart', 'b' => 'Alle Flights starten gleichzeitig, auch Einsteiger sind per Scramble voll dabei.' ],
				[ 't' => 'Verpflegung auf der Runde', 'b' => 'Halfway-Snacks und Getränke direkt am Platz, die Stimmung bleibt oben.' ],
				[ 't' => 'Siegerehrung & Ausklang', 'b' => 'Pokale, Preise und ein gemeinsames Essen mit den besten Geschichten des Tages.' ],
			],
			'types'   => $t_turnier,
		],
		// Saisonformat (Julius, 02.09.): eigener Event-Typ seit 1.9.205; die Top-100-
		// Recherche belegt Weihnachtsfeiern bei 15/100 Golfanlagen und 14/50
		// Simulatoren. Die Seite listet beide Welten (weihnachtsfeier + indoor-golf).
		'weihnachtsfeier' => [
			'name'    => 'Weihnachtsfeier',
			'eyebrow' => 'Format · Weihnachtsfeier',
			'h1'      => 'Weihnachtsfeier mit Golf',
			'lead'    => 'Die Firmenfeier, über die noch im Januar geredet wird: Glühwein, Golf-Challenge und gemeinsames Weihnachtsessen, indoor oder am Platz.',
			'intro'   => 'Eine Weihnachtsfeier mit Golf verbindet das gemeinsame Essen mit einem Erlebnis, das wirklich alle mitnimmt, auch ohne Golferfahrung. Im Winter geht es in die Indoor-Simulatoren: warme Lounge, Turniermodus mit Live-Leaderboard, Glühwein an der Bar. Golfanlagen mit eigener Gastronomie richten eure Feier auch im Clubhaus aus, vom Empfang bis zum Menü. Wir stellen Location, Ablauf und Verpflegung passend zu eurer Gruppe zusammen, deutschlandweit.',
			'faqs'    => [
				$f_anf,
				$faq( 'Wann sollten wir für Dezember anfragen?', 'Die beliebten Termine von Ende November bis Mitte Dezember sind früh vergeben. Frag am besten bis Oktober an, dann sichern wir euch Location und Wunschtermin.' ),
				fge_format_price_faq( 'Was kostet eine Weihnachtsfeier mit Golf?', 'Weihnachtsfeiern', [ 'weihnachtsfeier', 'indoor-golf' ], 'Im Paket stecken das Golf-Programm mit Betreuung, Turnier und Siegerehrung sowie das gemeinsame Essen. Menü, Getränkepauschale und Extras passen wir an eure Feier an.' ),
				$f_fast,
				$f_bill,
			],
			'types'   => [ 'weihnachtsfeier', 'indoor-golf' ],
			'hero_img'  => 'onboarding-indoor-lounge.jpg',
			// Saison-Seite (Julius, 07.09.): Kurz-Anfrage, Spielformate und Simulator-
			// Karte werden im Template nur für dieses Format gerendert.
			'xmas'      => true,
			'facts'     => [
				$reason( 'sun',       'Warm und wetterfest',      'Indoor an den Simulatoren oder im Clubhaus der Golfanlage: kein Frieren, kein Ausfall bei Schmuddelwetter.' ),
				$reason( 'users',     'Alle spielen mit',         'Betreuung an den Boxen, Schläger werden gestellt. Wer noch nie gespielt hat, trifft nach zehn Minuten den Screen.' ),
				$reason( 'gift',      'Menü und Getränke dabei',  'Glühwein-Empfang, Weihnachtsmenü oder Buffet und auf Wunsch die Getränkepauschale, alles aus einer Hand.' ),
				$reason( 'flag',      'In ganz Deutschland',      'Über 50 Simulator-Anlagen von Hamburg bis München plus Golfanlagen mit Clubhaus in 30 Städten.' ),
			],
			// Indoor-Formate im Homepage-Kachel-Look (Julius, 03.09.): im Winter
			// spielt die Feier drinnen. Bild-Slots in assets/imagery/tiles/,
			// Fallback = Lounge, bis die Stock-Bilder eingepflegt sind.
			'tiles_h2'  => 'Eure Feier, euer <em class="mk-italic">Indoor-Format</em>.',
			'tiles_sub' => 'Alles wetterfest an den Simulatoren: vom Turnier bis zur exklusiven Location.',
			'tiles'     => [
				[ 't' => 'Weihnachtsfeier komplett', 'sub' => 'Glühwein, Challenge & Menü',      'img' => 'tiles/indoor-weihnachtsfeier.jpg', 'url' => home_url( '/firmenevents/?format=weihnachtsfeier' ) ],
				[ 't' => 'Simulator-Turnier',        'sub' => 'Live-Leaderboard & Siegerehrung', 'img' => 'tiles/indoor-turnier.jpg',         'url' => home_url( '/firmenevents/?format=indoor-golf' ) ],
				[ 't' => 'Teamevent Indoor',         'sub' => 'Zusammenwachsen an den Boxen',    'img' => 'tiles/indoor-teamevent.jpg',       'url' => add_query_arg( [ 'anfrage' => 'quick', 'anlass' => 'Teamevent Indoor', 'intro' => '1' ], home_url( '/individuelle-events/' ) ) ],
				[ 't' => 'After-Work Indoor',        'sub' => 'Feierabend, warm & trocken',      'img' => 'tiles/indoor-afterwork.jpg',       'url' => add_query_arg( [ 'anfrage' => 'quick', 'anlass' => 'After-Work Indoor', 'intro' => '1' ], home_url( '/individuelle-events/' ) ) ],
				[ 't' => 'Grundlagenkurs Indoor',    'sub' => 'Einsteigen ohne Frieren',         'img' => 'tiles/indoor-grundlagen.jpg',      'url' => add_query_arg( [ 'anfrage' => 'quick', 'anlass' => 'Grundlagenkurs Indoor', 'intro' => '1' ], home_url( '/individuelle-events/' ) ) ],
				[ 't' => 'Tagung & Workshop',        'sub' => 'Meeting, dann an die Boxen',      'img' => 'tiles/indoor-workshop.jpg',        'url' => add_query_arg( [ 'anfrage' => 'quick', 'anlass' => 'Workshop Indoor', 'intro' => '1' ], home_url( '/individuelle-events/' ) ) ],
				[ 't' => 'Kundenevent Indoor',       'sub' => 'Gastgeber sein, drinnen',         'img' => 'tiles/indoor-kundenevent.jpg',     'url' => add_query_arg( [ 'anfrage' => 'quick', 'anlass' => 'Kundenevent Indoor', 'intro' => '1' ], home_url( '/individuelle-events/' ) ) ],
				[ 't' => 'Location exklusiv',        'sub' => 'Die ganze Anlage für euch',       'img' => 'tiles/indoor-exklusiv.jpg',        'url' => add_query_arg( [ 'anfrage' => 'quick', 'anlass' => 'Exklusivmiete Indoor', 'intro' => '1' ], home_url( '/individuelle-events/' ) ) ],
			],
		],
		'sommerfest' => [
			'name'    => 'Sommerfest',
			'eyebrow' => 'Format · Sommerfest',
			'h1'      => 'Sommerfest auf dem Golfplatz',
			'lead'    => 'Turnier für die Könner, Kurzplatz und Schnupperrunde für alle anderen, danach Barbecue auf der Clubterrasse. Ein Sommertag draußen, an dem wirklich alle mitspielen.',
			'intro'   => 'Ein Sommerfest auf dem Golfplatz bringt alle an einen Ort, an dem der Tag von selbst läuft: Die Golfer im Team spielen ihr Firmenturnier über 9 oder 18 Löcher, alle anderen starten mit dem Golflehrer auf Range und Kurzplatz. Am Nachmittag treffen sich beide Gruppen zur Putting-Challenge, am Abend grillt das Clubhaus auf der Terrasse, mit Siegerehrung, Sundowner und Blick über den Platz. Über 500 Anlagen in ganz Deutschland kommen dafür in Frage, wir wählen mit euch die passende aus.',
			'faqs'    => [
				$f_anf,
				$faq( 'Können wir schon für 2027 planen?', 'Ja, und das lohnt sich: Die Sommertermine von Juni bis September sind auf beliebten Anlagen früh vergeben. Fragt jetzt an, wir sichern euch Platz und Wunschtermin und feilen das Programm in Ruhe mit euch aus.' ),
				$faq( 'Was machen Kolleginnen und Kollegen ohne Golferfahrung?', 'Sie haben ihr eigenes Programm: Einführung mit dem Golflehrer, erste Schläge auf der Range, dann eine Runde auf dem Kurzplatz. Zur Putting-Challenge und zum Barbecue kommen alle wieder zusammen.' ),
				fge_format_price_faq( 'Was kostet ein Sommerfest auf dem Golfplatz?', 'Sommerfeste', [ 'sommerfest' ], 'Im Paket stecken Turnier und Einsteigerprogramm mit Betreuung, Leihschläger, Putting-Challenge und das Barbecue. Getränkepauschale, Musik und Extras passen wir an euer Fest an.' ),
				$f_fast,
				$f_bill,
			],
			'types'   => [ 'sommerfest', 'firmen_golfturnier' ],
			'hero_img' => 'pool/afterwork-anstossen.jpg',
			'levels'  => true,
			// Saison-Seite wie die Weihnachtsfeier (Julius, 07.09.): Vorplanung 2027,
			// Formate-Kacheln, Kurz-Anfrage, Golfplatz-Karte; Template-Zweig 'summer'.
			'summer'  => true,
		],
		'platzreife' => [
			'name'    => 'Platzreife',
			'eyebrow' => 'Format · Platzreife',
			'h1'      => 'Platzreife für Firmen & Teams',
			'lead'    => 'Der offizielle Einstieg in den Golfsport: kompakter Kurs mit Prüfung, als Firmenprogramm oder Benefit mit bleibendem Wert.',
			'intro'   => 'Die Platzreife ist die Eintrittskarte auf den Golfplatz. An vier Tagen lernt euer Team mit rund 12 Trainerstunden alles vom vollen Schwung über Kurzspiel, Putten und Bunkerschläge bis zu Regeln und Etikette, angeleitet von einem PGA-Golflehrer. Am Ende stehen ein Platzreife-Turnier und die Theorieprüfung: Mit dem Bestehen ist die DGV-Platzreife erworben, die weltweit zum Golfspielen berechtigt. Und noch etwas haben wir immer wieder erlebt: Mehrere gemeinsame Kurstage schweißen ein Team richtig zusammen. Daraus sind schon echte Freundschaften entstanden, die lange über den Kurs hinaus halten.',
			'facts' => [ $reason( 'trophy', 'Offizieller Abschluss', 'Am Ende stehen Platzreife-Turnier und Theorieprüfung: die anerkannte Eintrittskarte für Golfplätze in ganz Deutschland.' ), $reason( 'clock', 'Kompakt in 4 Tagen', 'Rund 12 Trainerstunden, am Stück oder verteilt über mehrere Termine, passend zum Arbeitskalender.' ), $reason( 'gift', 'Benefit mit Substanz', 'Ein Programm, das bleibt: kein einmaliges Event, sondern der Start in einen Sport fürs Leben.' ), $r_one ],
			'faqs'    => [ $f_anf, $faq( 'Was gehört zum Platzreifekurs?', 'Platzbegehung zum Auftakt, rund 12 Trainerstunden mit PGA-Golflehrer (voller Schwung, Kurzspiel, Putten, Bunker), Lernmaterial samt App-Zugang, Regel- und Etikettekunde, Leihschläger und Range-Bälle sowie die Prüfung. Auf Wunsch ergänzen wir Verpflegung.' ), $faq( 'Wie lange dauert die Platzreife und wie läuft die Prüfung?', 'Vier Tage mit rund 12 Trainerstunden, am Stück oder über mehrere Wochen verteilt. Am Ende steht ein Platzreife-Turnier über 9 Löcher nach Stableford plus eine kurze Theorieprüfung.' ), $faq( 'Darf ein Unternehmen Mitarbeitende zur Platzreife einladen?', 'Ja, grundsätzlich darf ein Unternehmen seine Mitarbeitenden als Teambuilding-Maßnahme zu einem Platzreifekurs einladen. Steuerlich gilt: Bei einer Betriebsveranstaltung bleiben in der Regel 110 Euro pro Person steuerfrei. Kostet der Kurs zum Beispiel 420 Euro pro Person, kann der Betrag darüber, also etwa 310 Euro, als geldwerter Vorteil gelten. In vielen Fällen kann der Arbeitgeber diesen Anteil pauschal versteuern, dann entsteht für die Mitarbeitenden meist kein zusätzlicher Aufwand. Ist die Platzreife beruflich sinnvoll, etwa für Kundenveranstaltungen oder Golf-Events, sollte das Unternehmen den geschäftlichen Zweck kurz dokumentieren. Kurz gesagt: Ja, das geht. Am besten vorher mit der Steuerberatung oder Lohnbuchhaltung abstimmen, wie der Betrag korrekt behandelt wird.' ), $f_bill ],
			'hero_img' => 'pool/platzreife-golflehrer-erklaert.jpg',
			'flow_h'   => 'Vom ersten Schwung bis zur Prüfung.',
			'flow'     => [
				[ 't' => 'Platzbegehung & Kennenlernen', 'b' => 'Auftakt mit eurem PGA-Golflehrer, ihr lernt Anlage und Ablauf kennen.' ],
				[ 't' => 'Training in kleinen Gruppen', 'b' => 'Rund 12 Trainerstunden: voller Schwung, Kurzspiel, Putten und Bunker.' ],
				[ 't' => 'Regeln & Etikette', 'b' => 'Kompakt und praxisnah, mit Lernmaterial und App-Zugang für zwischendurch.' ],
				[ 't' => 'Platzreife-Turnier', 'b' => '9 Löcher nach Stableford, gemeinsam statt gegeneinander.' ],
				[ 't' => 'Theorieprüfung & Feier', 'b' => 'Bestehen, anstoßen, und die DGV-Platzreife bleibt für immer.' ],
			],
			'types'   => [ 'platzreife' ],
			// Cross-Promo: Nach der Platzreife weiterspielen → Firmengolf-Benefit (Julius, 2026-07-03).
			'promo'   => [
				'eyebrow' => 'Und danach?',
				'title'   => 'Mit dem Firmengolf-Benefit bleibt euer Team am Ball.',
				'text'    => 'Nach der Platzreife geht es erst richtig los: Mit Firmengolf als Corporate Benefit spielen eure Mitarbeitenden regelmäßig weiter, mit Zugang zu Partnerplätzen und Coaching-Stunden zum Mitarbeiterpreis.',
				'cta'     => 'Firmengolf als Benefit entdecken ↗',
				'url'     => 'https://firmengolf.app',
				'external' => true,
			],
		],
		'workshop' => [
			'name'    => 'Workshop & Golf',
			'eyebrow' => 'Format · Workshop',
			'h1'      => 'Workshops auf dem Golfplatz',
			'lead'    => 'Vormittags konzentriert arbeiten, mittags auf der Clubterrasse essen, zum Ausklang gemeinsam Golf lernen.' . ( $from_workshop ? ' ' . $from_workshop . '.' : '' ),
			'intro'   => 'Ein Workshop auf dem Golfplatz verbindet konzentriertes Arbeiten mit einem Ausklang, der wirklich verbindet. Moderne Clubhäuser bieten Konferenzräume mit Tageslicht und eine Gastronomie, die vom Begrüßungskaffee bis zum Mittagsbuffet auf der Clubterrasse alles abdeckt. Nach dem offiziellen Teil geht es raus: Ein Golf-Grundlagenkurs mit einem unserer Golflehrer bringt das Team in Bewegung, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner.',
			'facts' => [ $reason( 'leaf', 'Frischer Kopf', 'Vormittags Workshop, mittags Clubterrasse, nachmittags Bewegung an der frischen Luft. So hält die Konzentration den ganzen Tag.' ), $reason( 'sun', 'Ausklang am Grün', 'Zum Abschluss ein Golf-Grundlagenkurs mit Golflehrer, ein gemeinsames Erlebnis statt letzter Folien.' ), $r_team, $r_one ],
			'faqs'    => [ fge_format_price_faq( 'Was kostet ein Workshop auf dem Golfplatz?', 'Workshoptage', $t_workshop, 'Im Workshoptag stecken Eventlocation, Begrüßung mit Kaffee und Kuchen, Mittagsbuffet inklusive Getränken auf der Clubterrasse und der Golf-Grundlagenkurs mit Golflehrer. Je nach Platz, Raum und Extras passen wir das Paket an.' ), $f_anf, $faq( 'Welche Technik steht im Konferenzraum bereit?', 'Beamer oder Screen, WLAN und Moderationsmaterial gehören auf den meisten Anlagen zur Ausstattung. Sag uns, was ihr braucht, wir klären das mit dem Platz vor der Buchung.' ), $f_fast, $f_bill ],
			'hero_img' => 'pool/workshop-clubhaus-aussen.jpg',
			'flow_h'   => 'Vom Kaffee bis zum ersten Abschlag.',
			'flow'     => [
				[ 't' => 'Ankunft & Begrüßung', 'b' => 'Der Golfclub empfängt euch, Kaffee und Kuchen am Buffet zum Ankommen.' ],
				[ 't' => 'Workshop Teil 1', 'b' => 'Der offizielle Teil im Konferenzraum, konzentriert und ungestört.' ],
				[ 't' => 'Mittagessen auf der Clubterrasse', 'b' => 'Buffet inklusive Getränke, mit Blick ins Grüne.' ],
				[ 't' => 'Workshop Teil 2', 'b' => 'Weiter geht es im Konferenzraum, mit frischem Kopf.' ],
				[ 't' => 'Ausklang: Golf-Grundlagenkurs', 'b' => 'Zum Abschluss bringt ein Golflehrer alle in Bewegung, ganz ohne Vorkenntnisse.' ],
			],
			'types'   => $t_workshop,
		],
		'kundenevent' => [
			'name'    => 'Kundenevent Golf',
			'eyebrow' => 'Format · Kunden',
			'h1'      => 'Kundenevents auf dem Golfplatz',
			'lead'    => 'Ein paar entspannte Stunden auf dem Platz schaffen Gespräche, die im Konferenzraum nie entstehen.',
			'intro'   => 'Golf ist seit jeher der Rahmen für gute Geschäftsbeziehungen. Ein Kundenevent auf dem Golfplatz gibt euch Zeit und Atmosphäre, um Kunden einmal anders zu erleben, ob beim Schnupperkurs, beim Turnier oder beim Hospitality-Tag mit Dinner. Wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz auf eure Gäste konzentrieren könnt.',
			'facts' => [ $reason( 'handshake', 'Beziehungen vertiefen', 'Mehrere Stunden gemeinsam draußen schaffen echte Gespräche und bleiben in Erinnerung.' ), $reason( 'gift', 'Hospitality & Dinner', 'Von der Begrüßung am Tee bis zum Abendessen mit Siegerehrung, alles aus einer Hand.' ), $r_team, $r_one ],
			'faqs'    => [ $faq( 'Eignet sich Golf für ein Kundenevent?', 'Sehr gut. Die ungezwungene Atmosphäre auf dem Platz öffnet Gespräche wie kaum ein anderes Format. Auch Kunden ohne Golferfahrung sind über einen Schnupperteil schnell dabei.' ), $faq( 'Lässt sich ein Rahmenprogramm ergänzen?', 'Ja, gern, Catering, Dinner, ein kleines Turnier oder Hospitality-Elemente kombinieren wir passend zu euren Gästen.' ), $f_fast, $f_bill ],
			'hero_img' => 'pool/kundenevent-handshake.jpg',
			'flow_h'   => 'Vom Empfang bis zum Dinner.',
			'flow'     => [
				[ 't' => 'Empfang eurer Gäste', 'b' => 'Persönliche Begrüßung am Clubhaus, Welcome-Drink und lockerer Auftakt.' ],
				[ 't' => 'Schnupperkurs oder Turnier', 'b' => 'Je nach Gästen: Grundlagen mit dem Pro oder ein kleines Turnier für Erfahrene.' ],
				[ 't' => 'Gemeinsame Runde & Gespräche', 'b' => 'Mehrere Stunden draußen, in denen echte Gespräche entstehen.' ],
				[ 't' => 'Dinner & Ausklang', 'b' => 'Gemeinsames Essen mit Siegerehrung, eure Gäste fahren mit einer Geschichte heim.' ],
			],
			'types'   => $t_kunden,
		],
		'incentive' => [
			'name'    => 'Golf-Incentive',
			'eyebrow' => 'Format · Incentive',
			'h1'      => 'Golf-Incentives für Unternehmen',
			'lead'    => 'Belohnen und verbinden: ein- oder mehrtägige Incentives an besonderen Orten.',
			'intro'   => 'Ein Golf-Incentive belohnt Leistung und stärkt die Bindung, mit einem Erlebnis statt einem Bonus auf dem Konto. Wir verbinden Golf an reizvollen Anlagen mit Hotellerie, Rahmenprogramm und Verpflegung zu einem runden Paket, ein- oder mehrtägig. Besonders Regionen wie der Tegernsee bieten dafür eine Kulisse, die lange nachwirkt. Von der Idee bis zur Abrechnung organisieren wir alles.',
			'facts' => [ $reason( 'mountain', 'Besondere Kulissen', 'Anlagen mit Berg- und Seeblick, die ein Incentive zum echten Erlebnis machen.' ), $reason( 'castle', 'Mehrtägig mit Hotel', 'Golf, Übernachtung, Dinner und Rahmenprogramm an einem Ort, ideal als Belohnung fürs Team.' ), $r_team, $r_one ],
			'faqs'    => [ $faq( 'Geht auch ein mehrtägiges Incentive mit Übernachtung?', 'Ja. Wir verbinden Golf, Hotel, Verpflegung und Rahmenprogramm zu einem runden, mehrtägigen Paket.' ), $f_anf, $f_fast, $f_bill ],
			'hero_img' => 'hero-golfer-alpen.jpg',
			'flow_h'   => 'Von der Anreise bis zum letzten Putt.',
			'flow'     => [
				[ 't' => 'Anreise & Check-in', 'b' => 'Ankommen an einer besonderen Anlage, Zimmerbezug und entspannter Welcome.' ],
				[ 't' => 'Golf mit Kulisse', 'b' => 'Eine Runde mit Berg- oder Seeblick, betreut vom Golflehrer.' ],
				[ 't' => 'Dinner & Abendprogramm', 'b' => 'Gemeinsames Abendessen und ein lockerer Ausklang.' ],
				[ 't' => 'Optionaler zweiter Tag', 'b' => 'Weitere Runde oder Rahmenprogramm, ganz nach Wunsch.' ],
			],
			'types'   => [ 'incentive' ],
		],
		'after-work-golf' => [
			'name'    => 'After-Work Golf',
			'eyebrow' => 'Format · After-Work',
			'h1'      => 'After-Work Golf für Teams',
			'lead'    => 'Ein paar Stunden nach Feierabend auf der Range, der unkomplizierte Teamabend im Grünen.',
			'intro'   => 'After-Work Golf ist das niedrigschwellige Format für zwischendurch: nach Feierabend gemeinsam auf die Driving Range oder den Kurzplatz, mit lockerer Anleitung durch einen Pro und entspanntem Ausklang. Kein ganzer Tag, kein großer Aufwand, und trotzdem ein echtes gemeinsames Erlebnis. Ideal als regelmäßiger Teamabend oder lockerer Einstieg ins Thema Golf.',
			'facts' => [ $reason( 'clock', 'Nach Feierabend', 'Kompakt am Abend, ohne den Arbeitstag zu sprengen.' ), $reason( 'sun', 'Locker & ungezwungen', 'Range, Kurzspiel und ein entspannter Ausklang, Spaß statt Leistungsdruck.' ), $r_team, $r_one ],
			'faqs'    => [ $f_anf, $faq( 'Eignet sich After-Work Golf als regelmäßiges Format?', 'Ja, gerade dafür. Viele Teams machen daraus einen wiederkehrenden Termin, wir richten Platz und Pro passend zu eurem Rhythmus ein.' ), $f_fast, $f_bill ],
			'hero_img' => 'pool/pool-hochformat-afterwork-laessiger-golfeinsteiger-macht-erste-schwuenge.jpg',
			'flow_h'   => 'Vom Feierabend bis zum letzten Ball.',
			'flow'     => [
				[ 't' => 'Ankommen nach Feierabend', 'b' => 'Direkt aus dem Büro auf die Anlage, Schläger und Bälle liegen bereit.' ],
				[ 't' => 'Lockere Range-Session', 'b' => 'Ein Pro leitet an, jeder schlägt in seinem Tempo, Spaß statt Leistungsdruck.' ],
				[ 't' => 'Putt- & Kurzspiel-Challenge', 'b' => 'Kleiner Wettbewerb auf dem Putting-Grün, hier wird gelacht.' ],
				[ 't' => 'Ausklang bei Drinks', 'b' => 'Gemeinsam sitzen bleiben, auf der Terrasse oder im Clubhaus.' ],
			],
			'types'   => $t_afterw,
		],
	];
}

/**
 * Anlass-Vorauswahl für den Anfrage-Wizard je Format-Slug (?anlass=…).
 * Platzreife/Incentive stehen nicht in der Standard-Anlassliste und erscheinen
 * im Wizard als zusätzliche, vorgewählte Kachel.
 */
/**
 * Possessiv je Format für Sätze wie „Bereit für euer Teamevent?" (Genus des
 * Formatnamens: das Teamevent, die Weihnachtsfeier, der Workshop). Review 07.09.
 */
function fge_format_possessive( string $slug ): string {
	$map = [ 'weihnachtsfeier' => 'eure', 'platzreife' => 'eure', 'workshop' => 'euren', 'incentive' => 'eure' ];
	return $map[ $slug ] ?? 'euer';
}

function fge_format_occasion( string $slug ): string {
	$map = [
		'teamevent'       => 'Teamevent',
		'golfturnier'     => 'Firmenturnier',
		'platzreife'      => 'Platzreife',
		'workshop'        => 'Workshop',
		'kundenevent'     => 'Kundenevent',
		'incentive'       => 'Incentive-Reise',
		'after-work-golf' => 'After-Work Golf',
		'weihnachtsfeier' => 'Indoor Weihnachtsfeier', // Wizard-Kachel heißt so (07.09.)
		'sommerfest'      => 'Sommerfest',
	];
	return $map[ $slug ] ?? '';
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
		}
	}
	// Kern-Typen des Formats zuerst (Reihenfolge der types-Liste), danach die
	// „auch buchbar als"-Events (z. B. Teamevents in der After-Work-Kategorie,
	// Julius 2026-08-20). Erst sortieren, dann kappen, sonst faellt das
	// namensgebende Event bei vielen Treffern aus der Auswahl.
	usort( $out, static function ( $a, $b ) use ( $types ) {
		$ia = array_search( get_post_meta( $a->ID, '_fge_event_type', true ), $types, true );
		$ib = array_search( get_post_meta( $b->ID, '_fge_event_type', true ), $types, true );
		return ( false === $ia ? PHP_INT_MAX : $ia ) <=> ( false === $ib ? PHP_INT_MAX : $ib );
	} );
	return array_slice( $out, 0, $limit );
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
	// Alias-Slugs → kanonische Format-Seite (naheliegende URL-Varianten, Julius 2026-08-11).
	$aliases = [
		'schnupperkurs' => 'teamevent',
		'after-work'    => 'after-work-golf',
		'afterwork'     => 'after-work-golf',
		'turnier'       => 'golfturnier',
		'offsite'       => 'workshop',
	];
	$slug = (string) get_query_var( 'fge_format' );
	if ( ! isset( $aliases[ $slug ] ) ) {
		return;
	}
	$target = $aliases[ $slug ];
	$city   = (string) get_query_var( 'fge_city' );
	$url    = $city !== ''
		? home_url( '/golf-events/' . rawurlencode( $city ) . '/' . $target . '/' )
		: home_url( '/firmenevent/' . $target . '/' );
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
