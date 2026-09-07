<?php
/**
 * SEO Format×Stadt landing pages: /golf-events/<stadt>/<format>/
 *
 * Dritte Ebene über City- (city-landing.php) und Format-Hubs (format-landing.php):
 * fängt die lokalen Long-Tail-Keywords mit Format- UND Stadtbezug ab
 * (z. B. „Teamevent München", „Platzreife Hamburg").
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
	return [
		'muenchen',
		'hamburg',
		'augsburg',
		'bonn',
		'bremen',
		'dortmund',
		'dresden',
		'erlangen',
		'essen',
		'garmisch-partenkirchen',
		'ingolstadt',
		'itzehoe',
		'karlsruhe',
		'kiel',
		'landshut',
		'leipzig',
		'luebeck',
		'lueneburg',
		'mannheim',
		'penzberg',
		'regensburg',
		'rosenheim',
		'ulm',
		'wuerzburg',
	];
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
			'title' => 'Teamevent in %s auf dem Golfplatz, Firmengolf',
			'desc'  => 'Golf-Teamevent für euer Team in %s: Golflehrer, Leihschläger, ein Tag draußen statt Stuhlkreis. Eine Anfrage, ein Ansprechpartner, eine Rechnung.',
		],
		'golfturnier' => [
			'h1'    => 'Firmen-Golfturniere in %s',
			'eyeb'  => 'Firmenturnier · %s',
			'title' => 'Firmenturnier in %s organisieren, Firmengolf',
			'desc'  => 'Firmen-Golfturnier in %s: Startlisten, Scramble für gemischte Level, Siegerehrung und Branding. Komplett organisiert von Firmengolf.',
		],
		'platzreife' => [
			'h1'    => 'Platzreife für Firmen & Teams in %s',
			'eyeb'  => 'Platzreife · %s',
			'title' => 'Platzreife in %s für Firmen, Firmengolf',
			'desc'  => 'Platzreifekurs für euer Team in %s: PGA-Golflehrer, Regeln, Prüfung. Der offizielle Einstieg in den Golfsport als Firmenprogramm.',
		],
		'workshop' => [
			'h1'    => 'Workshops auf dem Golfplatz in %s',
			'eyeb'  => 'Workshop · %s',
			'title' => 'Workshop in %s auf dem Golfplatz, Firmengolf',
			'desc'  => 'Workshop-Location in %s: Konferenzraum im Clubhaus, Mittagsbuffet auf der Clubterrasse, Golf-Grundlagenkurs zum Ausklang, ein Tag mit einem Ansprechpartner.',
		],
		'kundenevent' => [
			'h1'    => 'Kundenevents auf dem Golfplatz in %s',
			'eyeb'  => 'Kundenevent · %s',
			'title' => 'Kundenevent in %s auf dem Golfplatz, Firmengolf',
			'desc'  => 'Kundenevent auf dem Golfplatz in %s: Hospitality, Turnier oder Schnupperteil mit Dinner. Zeit für eure Gäste, organisiert von Firmengolf.',
		],
		'incentive' => [
			'h1'    => 'Golf-Incentives in %s & Umland',
			'eyeb'  => 'Incentive · %s',
			'title' => 'Golf-Incentive in %s für Unternehmen, Firmengolf',
			'desc'  => 'Golf-Incentive in %s: Golf, Hotellerie und Rahmenprogramm zu einem Paket. Leistung belohnen mit einem Erlebnis statt einem Bonus.',
		],
		'weihnachtsfeier' => [
			'h1'    => 'Weihnachtsfeier mit Indoor‑Golf in %s',
			'eyeb'  => 'Weihnachtsfeier · %s',
			'title' => 'Weihnachtsfeier mit Indoor-Golf in %s | Firmengolf',
			// Kurzer Hero-Sub (Julius, 03.09.): der erste Intro-Satz war zu lang.
			'hero_sub' => 'Glühwein, Golf-Challenge und Weihnachtsmenü, warm und wetterfest im Simulator.',
			'desc'  => 'Weihnachtsfeier in %s, die im Gedächtnis bleibt: Glühwein, Golf-Challenge mit Betreuung und gemeinsames Weihnachtsessen, indoor am Simulator oder im Clubhaus. Beliebte Dezember-Termine früh anfragen.',
		],
		'after-work-golf' => [
			'h1'    => 'After-Work Golf für Teams in %s',
			'eyeb'  => 'After-Work Golf · %s',
			'title' => 'After-Work Golf in %s für Firmen, Firmengolf',
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
			'teamevent'       => 'Ein Golf-Teamevent rund um München bringt euer Team in rund 30 Minuten von der Innenstadt ins Grüne, zwischen Isar-Auen und Alpenpanorama. Statt Stuhlkreis und Flipchart entsteht hier ganz nebenbei Nähe: gemeinsam üben, lachen, anfeuern. Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, und wir stellen Platz, Ablauf und Verpflegung passend zu eurer Gruppe zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier in München ist der Klassiker für Kundenbindung und Teamwettbewerb, und kaum eine Region hat so viele Top-Plätze direkt vor der Tür wie das Münchner Umland. Wir richten es komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine stimmungsvolle Siegerehrung, auf Wunsch mit Sponsoren-Branding und Goodie-Bags.',
			'platzreife'      => 'Die Platzreife rund um München ist der offizielle Einstieg in den Golfsport, auf stadtnahen Anlagen, oft in rund 30 Minuten erreichbar. Ein PGA-Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Als Firmenprogramm oder Benefit gedacht: Wer sie besteht, kann danach eigenständig auf Plätzen im Münchner Umland spielen. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz bringt euer Team rund um München raus aus dem Büro und rein in ein Clubhaus mit Konferenzraum, Tageslicht und Blick ins Grüne. Vormittags wird konzentriert gearbeitet, mittags gibt es Buffet auf der Clubterrasse, und zum Ausklang bringt ein Golf-Grundlagenkurs mit Golflehrer alle in Bewegung, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch in München Zeit und Atmosphäre, um Kunden einmal anders zu erleben, mit Blick aufs Bergpanorama und kurzen Wegen aus der Stadt. Ob Hospitality-Tag, Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive belohnt Leistung mit einem Erlebnis statt einem Bonus auf dem Konto, und rund um München liegen dafür einige der reizvollsten Anlagen Deutschlands, vom stadtnahen Platz bis zur Bergkulisse am Tegernsee. Wir verbinden Golf mit Hotellerie, Rahmenprogramm und Verpflegung zu einem runden Paket, ein- oder mehrtägig, von der Idee bis zur Abrechnung.',
			'after-work-golf' => 'After-Work Golf ist das niedrigschwellige Format für zwischendurch, und in München seid ihr nach Feierabend schnell auf einer stadtnahen Range oder dem Kurzplatz. Ein Pro leitet locker an, danach ein entspannter Ausklang, kein ganzer Tag und kein großer Aufwand. Ideal als regelmäßiger Teamabend oder lockerer Einstieg ins Thema Golf.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf macht in München auch im Dezember Sinn: Die Indoor-Simulatoren der Stadt holen den Sport in die warme Lounge, mit Glühwein an der Bar und Live-Leaderboard über alle Boxen. Wer es klassischer mag, feiert im Clubhaus einer Anlage im Umland, mit Blick Richtung Alpen und Menü nach der Siegerehrung. Wir stellen Location, Challenge und Essen passend zu eurer Gruppe zusammen. Die beliebten Termine ab Ende November sind früh vergeben, fragt am besten bis Oktober an.',
		],
		'hamburg' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Hamburg holt euer Team raus zwischen Knicks, Wiesen und alte Alleen, viele Plätze nur eine kurze Fahrt vom Zentrum. Anders als beim klassischen Teambuilding entsteht hier ganz nebenbei Nähe: gemeinsam üben, lachen, anfeuern. Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, und wir stellen Platz, Ablauf und Verpflegung passend zu eurer Gruppe zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier in Hamburg verbindet Wettbewerb mit der besonderen Atmosphäre des Nordens, auf weitläufigen Plätzen, wo Wind und Weite das Spiel zur Herausforderung machen. Wir richten es komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine stimmungsvolle Siegerehrung, auf Wunsch mit Sponsoren-Branding und Goodie-Bags.',
			'platzreife'      => 'Die Platzreife in Hamburg ist der offizielle Einstieg in den Golfsport, auf weitläufigen Anlagen im Hamburger Umland. Ein PGA-Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Als Firmenprogramm oder Benefit gedacht: Wer sie besteht, kann danach eigenständig auf Plätzen im Norden spielen. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Team rund um Hamburg das, was kein Tagungshotel bietet: Konferenzraum mit Blick ins Grüne, Mittagsbuffet auf der Clubterrasse und norddeutsche Luft in der Pause. Zum Ausklang bringt ein Golf-Grundlagenkurs mit Golflehrer alle in Bewegung, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch in Hamburg Zeit und Atmosphäre, um Kunden einmal anders zu erleben, im Grünen und doch nah an der Stadt. Ob Hospitality-Tag, Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive belohnt Leistung mit einem Erlebnis statt einem Bonus auf dem Konto. Rund um Hamburg verbinden mehrere Anlagen Golf, Tagung und Hotel an einem Ort, ideal für ein mehrtägiges Teamerlebnis im Norden. Wir verbinden Golf mit Hotellerie, Rahmenprogramm und Verpflegung zu einem runden Paket, von der Idee bis zur Abrechnung.',
			'after-work-golf' => 'After-Work Golf ist das niedrigschwellige Format für zwischendurch, und in Hamburg seid ihr nach Feierabend schnell auf einer stadtnahen Range oder dem Kurzplatz. Ein Pro leitet locker an, danach ein entspannter Ausklang, kein ganzer Tag und kein großer Aufwand. Ideal als regelmäßiger Teamabend oder lockerer Einstieg ins Thema Golf.',
			'weihnachtsfeier' => 'Wenn draußen an der Alster das Schmuddelwetter regiert, wird die Weihnachtsfeier mit Golf in Hamburg zum Indoor-Erlebnis: Simulator-Boxen, Turniermodus und Glühwein statt Stuhlreihen und Buffetschlange. Alternativ öffnen Clubhäuser im Hamburger Umland ihre Gastronomie für eure Feier, hanseatisch unaufgeregt und mit richtig gutem Essen. Challenge, Siegerehrung und Menü organisieren wir komplett. Dezember-Termine früh sichern, am besten bis Oktober.',
		],
		'augsburg' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Augsburg bringt euer Team in kurzer Zeit aus der Stadt in die Park- und Flusslandschaft Bayerisch-Schwabens. Zwischen Lechauen und Stauden wird gemeinsam geübt, gelacht und angefeuert, ganz ohne Vorkenntnisse. Ein Golflehrer führt alle sicher an, Schläger und Bälle werden gestellt, und wir planen Platz, Ablauf und Verpflegung passend zu eurer Gruppe.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Augsburg bietet euren Gästen einen Tag zwischen Lech und Stauden, gut erreichbar aus der Stadt und über die A8 auch aus München. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung, die den Tag rund macht.',
			'platzreife'      => 'Die Platzreife rund um Augsburg legt den Grundstein für alle, die im Unternehmen dauerhaft Golf spielen wollen. Auf den ruhigen Anlagen Bayerisch-Schwabens bringt ein Golflehrer eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Danach steht dem eigenständigen Spiel auf den Plätzen der Region nichts mehr im Weg. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz bringt euer Augsburger Team raus aus dem Büro und rein in ein Clubhaus im Grünen: Konferenzraum mit Tageslicht, Mittagessen auf der Terrasse, dazwischen frische Luft statt Flurfunk. Zum Ausklang bringt ein Golf-Grundlagenkurs mit Golflehrer alle in Bewegung, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch bei Augsburg Zeit und Ruhe für echte Gespräche, in der Flusslandschaft von Lech und Wertach statt im Besprechungsraum. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit gemeinsamem Essen, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive rund um Augsburg belohnt euer Team mit einem Erlebnis statt einem Bonus auf dem Konto. Die Anlagen Bayerisch-Schwabens liegen ruhig im Grünen, und mit der Nähe zu München und zum Allgäu lässt sich das Programm beliebig erweitern. Wir verbinden Golf, Verpflegung und Rahmenprogramm zu einem runden Paket, ein- oder mehrtägig.',
			'after-work-golf' => 'After-Work Golf bei Augsburg macht aus einem normalen Dienstagabend einen Teamtermin, auf den sich alle freuen. Nach Feierabend geht es auf Range und Kurzplatz, ein Pro leitet locker an, danach gibt es einen entspannten Ausklang. Kein ganzer Tag, kein großer Aufwand, dafür ein regelmäßiger Anlass, zusammen rauszukommen.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf gibt eurem Team in Augsburg einen Abend, über den noch im Januar geredet wird: Nearest to the Pin im warmen Simulator oder eine Feier im Clubhaus einer Anlage zwischen Lech und Stauden. Glühwein zur Begrüßung, Team-Challenge mit Betreuung, danach das gemeinsame Weihnachtsessen. Wir kümmern uns um Location, Ablauf und Menü. Für Termine ab Ende November empfiehlt sich eine Anfrage bis Oktober.',
		],
		'bonn' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Bonn holt euer Team raus ins Rheintal, zwischen Vorgebirge, Kottenforst und Siebengebirge. Statt Meetingraum und Agenda gibt es gemeinsames Üben, Lachen und Anfeuern, angeleitet von einem Golflehrer, der auch komplette Einsteigende sicher führt. Schläger und Material werden gestellt, Platz, Ablauf und Verpflegung stellen wir passend zu eurer Gruppe zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier in Bonn spielt sich vor der Kulisse von Rheintal und Siebengebirge, auf Anlagen, die aus der Stadt und aus Köln schnell erreichbar sind. Wir richten euer Turnier komplett aus, von Startlisten über faire Zählformate wie Scramble bis zur Siegerehrung, auf Wunsch mit Branding und Goodie-Bags für eure Gäste.',
			'platzreife'      => 'Die Platzreife rund um Bonn ist der offizielle Einstieg in den Golfsport, auf den Anlagen zwischen Rheintal und Vorgebirge. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung, danach spielt ihr eigenständig auf Plätzen im Rheinland. Als Firmenprogramm oder Mitarbeiter-Benefit gleichermaßen geeignet, Material wird gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Bonner Team einen Tagungsort, der nach Feierabend nicht einfach zuklappt: Konferenzraum mit Blick ins Grüne, Mittagessen auf der Clubterrasse, und zum Ausklang ein Golf-Grundlagenkurs, der alle in Bewegung bringt. Vorkenntnisse braucht niemand, wir organisieren den kompletten Tag mit einem Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz hat in Bonn einen besonderen Vorteil: Rheintal und Siebengebirge liefern die Kulisse, wir liefern den Rahmen. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr Zeit für die Gespräche habt, für die das Event gedacht ist.',
			'incentive'       => 'Ein Golf-Incentive rund um Bonn verbindet die Anlagen des Rheinlands mit einem Rahmenprogramm zwischen Rhein, Drachenfels und Altstadt. Als Belohnung für euer Team oder als Dankeschön an Partner wirkt so ein Tag länger als jede Prämie. Wir stellen Golf, Verpflegung, Hotellerie und Programm zu einem runden Paket zusammen, ein- oder mehrtägig.',
			'after-work-golf' => 'After-Work Golf in Bonn heißt: raus aus dem Büro, in kurzer Zeit auf der Range, zwei bis drei Stunden Golf mit lockerer Anleitung und danach ein Ausklang mit dem Team. Kein ganzer Tag, kein großer Aufwand, dafür ein Termin, der aus Kolleginnen und Kollegen eine Runde macht. Ideal als regelmäßiges Teamformat.',
			'weihnachtsfeier' => 'Statt Kegelbahn oder Hotel-Buffet: Eine Weihnachtsfeier mit Golf bringt euer Bonner Team an einem Abend zusammen, im Simulator mitten in der Stadt oder im Clubhaus einer Anlage zwischen Rheintal und Siebengebirge. Ein Golfprofi leitet die Challenge an, auch komplette Einsteiger treffen den Ball, und nach der Siegerehrung wartet das Weihnachtsmenü. Wir organisieren alles mit einem Ansprechpartner. Dezember füllt sich schnell, fragt früh an.',
		],
		'bremen' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Bremen bringt euer Team raus in die weite norddeutsche Landschaft, auf flache, gut bespielbare Plätze zwischen Wiesen und Wasserzügen. Genau das macht den Einstieg leicht: Ein Golflehrer führt alle an, Schläger und Bälle werden gestellt, und nebenbei entsteht das, was kein Seminarraum schafft, echtes Miteinander. Ablauf und Verpflegung planen wir mit euch.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Bremen hat norddeutschen Charakter: weite Fairways, frische Luft und eine Brise Wind, die das Spiel fair durchmischt. Wir übernehmen die komplette Organisation, von Startlisten und Zählformaten wie Scramble bis zu Bewirtung und Siegerehrung, auf Wunsch mit Branding für euer Unternehmen oder eure Sponsoren.',
			'platzreife'      => 'Die Platzreife rund um Bremen ist der anerkannte Einstieg in den Golfsport, absolviert auf den ruhigen Anlagen zwischen Weser und Wümme. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die Prüfung, danach wird eigenständig gespielt. Ob als Firmenprogramm oder Benefit, Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Bremer Team Abstand vom Tagesgeschäft: vormittags konzentriertes Arbeiten im Konferenzraum mit Blick ins Grüne, mittags Buffet auf der Clubterrasse, nachmittags bringt ein Golf-Grundlagenkurs alle in Bewegung. Vorkenntnisse braucht niemand, und ihr habt für den ganzen Tag einen Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch rund um Bremen Zeit für Gespräche, die im Terminkalender sonst untergehen. Hanseatisch unaufgeregt, im Grünen und doch stadtnah. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, ihr kümmert euch um eure Gäste.',
			'incentive'       => 'Ein Golf-Incentive rund um Bremen belohnt euer Team mit einem Tag oder Wochenende in der weiten Landschaft des Nordwestens, auf Wunsch kombiniert mit maritimem Rahmenprogramm zwischen Schlachte und Nordsee. Wir stellen Golf, Verpflegung, Hotellerie und Programm zu einem Paket zusammen, das länger wirkt als jede Prämie.',
			'after-work-golf' => 'After-Work Golf bei Bremen ist der einfachste Weg, Golf ins Team zu holen: nach Feierabend auf die Range, zwei bis drei Stunden mit lockerer Anleitung vom Pro, danach ein Ausklang im Clubhaus. Kein großer Aufwand, keine Vorkenntnisse, dafür ein Termin, der schnell zur festen Runde wird.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf hat in Bremen hanseatischen Charme: erst eine angeleitete Golf-Challenge, bei der alle mitmachen können, dann Glühwein und ein gemeinsames Essen in gemütlicher Runde. Im Winter geht das indoor am Simulator, alternativ im Clubhaus einer der Anlagen zwischen Weser und Wümme. Wir stellen Location, Ablauf und Menü für eure Gruppe zusammen. Die guten Dezember-Termine sind erfahrungsgemäß bis Oktober vergeben.',
		],
		'dortmund' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Dortmund zeigt die grüne Seite des Ruhrgebiets: Die Plätze im Süden der Stadt und Richtung Sauerland liegen überraschend nah und mitten im Grünen. Ein Golflehrer führt euer Team an, Schläger und Material werden gestellt, und beim gemeinsamen Üben und Anfeuern entsteht das, wofür ihr das Event bucht, echtes Teamgefühl.',
			'golfturnier'     => 'Ein Firmen-Golfturnier in Dortmund profitiert von der dichten Golflandschaft Nordrhein-Westfalens, die Auswahl an Anlagen rund um die Stadt ist groß. Wir richten euer Turnier komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung mit Atmosphäre, auf Wunsch mit Sponsoren-Branding.',
			'platzreife'      => 'Die Platzreife rund um Dortmund ist der offizielle Einstieg in den Golfsport, absolviert auf den Anlagen im grünen Dortmunder Süden. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Wer besteht, spielt danach eigenständig auf Plätzen in ganz Nordrhein-Westfalen. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Dortmunder Team Abstand vom Alltag, ohne lange Anreise: Konferenzraum mit Blick ins Grüne, Mittagessen auf der Clubterrasse, und zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, der alle in Bewegung bringt. Vorkenntnisse braucht niemand, den kompletten Tag organisieren wir mit einem Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz überrascht in Dortmund gleich doppelt: mit der grünen Seite des Reviers und mit Zeit für Gespräche, die auf Messen und in Meetings nie entstehen. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, ihr euch um eure Gäste.',
			'incentive'       => 'Ein Golf-Incentive rund um Dortmund belohnt euer Team mit einem Tag zwischen Ardeygebirge und Sauerland, auf Wunsch mehrtägig mit Hotel und Rahmenprogramm. Ein Erlebnis wirkt länger als ein Bonus auf dem Konto, und wir bauen es komplett für euch: Golf, Verpflegung, Programm und Organisation aus einer Hand.',
			'after-work-golf' => 'After-Work Golf in Dortmund passt in jeden Feierabend: kurze Anfahrt in den grünen Süden der Stadt, zwei bis drei Stunden auf Range und Kurzplatz mit lockerer Anleitung vom Pro, danach gemeinsamer Ausklang. Kein großer Aufwand, keine Ausrüstung nötig, dafür ein Teamtermin mit Wiederholungspotenzial.',
			'weihnachtsfeier' => 'Die Weihnachtsfeier mit Golf zeigt eurem Dortmunder Team die andere Seite des Reviers: Indoor-Golf mit Live-Leaderboard und Glühwein oder eine Feier im Clubhaus einer Anlage Richtung Sauerland. Die Challenge funktioniert ohne jede Vorerfahrung, ein Betreuer leitet an, und nach der Siegerehrung gibt es das gemeinsame Weihnachtsessen. Eine Anfrage, ein Ansprechpartner, eine Rechnung. Für Dezember-Termine früh anfragen.',
		],
		'dresden' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Dresden bringt euer Team raus aus der Stadt und rein in die ruhige Landschaft zwischen Elbtal und sächsischem Hügelland. Gemeinsam üben, lachen, anfeuern: Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, und wir stellen Platz, Ablauf und Verpflegung passend zu eurer Gruppe zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Dresden verbindet sportlichen Wettbewerb mit einer Region, die Gäste ohnehin begeistert, vom Elbtal bis zu den Weinhängen. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble, Bewirtung am Platz und eine Siegerehrung, die den Tag würdig abschließt, auf Wunsch mit Branding.',
			'platzreife'      => 'Die Platzreife rund um Dresden ist der offizielle Einstieg in den Golfsport, absolviert auf den ruhigen Anlagen im Dresdner Umland. Ein Golflehrer vermittelt eurem Team Technik, Regeln und Etikette, am Ende steht die anerkannte Prüfung. Wer besteht, spielt danach eigenständig, in Sachsen und überall sonst. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Dresdner Team einen Ort, an dem konzentriertes Arbeiten und gemeinsames Erleben zusammenpassen: Konferenzraum mit Tageslicht, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer. Vorkenntnisse braucht niemand, und ihr habt für alles einen Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz nutzt in Dresden eine Kulisse, die für sich spricht: Elbtal, Weinhänge und barocke Stadt in Reichweite. Auf dem Platz entsteht die Ruhe für echte Gespräche. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm.',
			'incentive'       => 'Ein Golf-Incentive rund um Dresden verbindet Golf mit einer der schönsten Städtekulissen Deutschlands: Elbtal, Altstadt und Sächsische Schweiz liegen in Reichweite für das Rahmenprogramm. Als Belohnung für Team oder Partner wirkt so ein Erlebnis länger als jede Prämie. Wir bauen das Paket komplett, ein- oder mehrtägig, mit Hotel und Verpflegung.',
			'after-work-golf' => 'After-Work Golf bei Dresden holt euer Team nach Feierabend an die frische Luft: zwei bis drei Stunden auf Range und Kurzplatz, locker angeleitet von einem Pro, danach ein Ausklang mit Blick ins Grüne. Kein ganzer Tag, kein großer Aufwand, ideal als regelmäßiger Teamabend oder lockerer Einstieg ins Thema Golf.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf macht aus dem Jahresabschluss in Dresden ein Erlebnis: Team-Challenge am Simulator mit Glühwein in der Hand oder eine Feier im Clubhaus zwischen Elbtal und sächsischem Hügelland. Alle spielen mit, niemand braucht Vorkenntnisse, und das Weihnachtsmenü rundet den Abend ab. Location, Ablauf und Verpflegung organisieren wir komplett. Beliebte Termine ab Ende November am besten bis Oktober anfragen.',
		],
		'erlangen' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Erlangen bringt euer Team raus aus Labor, Büro und Besprechungsraum und rein in die fränkische Landschaft zwischen Regnitzgrund und Fränkischer Schweiz. Ein Golflehrer führt alle sicher an, auch ohne jede Vorerfahrung, Schläger und Material werden gestellt. Ablauf, Gruppengröße und Verpflegung stellen wir passend zu eurem Anlass zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Erlangen erreicht Gäste aus der ganzen Metropolregion Nürnberg auf kurzen Wegen. Wir richten es komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine stimmungsvolle Siegerehrung, auf Wunsch mit Branding und Goodie-Bags für Kunden und Partner.',
			'platzreife'      => 'Die Platzreife rund um Erlangen ist der offizielle Einstieg in den Golfsport, absolviert auf den fränkischen Anlagen der Metropolregion. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Gerade als Benefit für Fach- und Führungskräfte ein Programm mit Langzeitwirkung. Material wird gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Erlanger Team Denkraum außerhalb des Campus: Konferenzraum mit Tageslicht und Blick ins Grüne, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner, wir organisieren alles.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch rund um Erlangen Zeit und Atmosphäre für Gespräche mit Kunden und Partnern aus der Metropolregion. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit gemeinsamem Abendessen, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive rund um Erlangen belohnt Leistung mit einem Erlebnis: Golf in der fränkischen Landschaft, dazu ein Rahmenprogramm zwischen Fränkischer Schweiz, Nürnberg und Bierkeller-Kultur. Wir verbinden Golf, Verpflegung, Hotellerie und Programm zu einem runden Paket, ein- oder mehrtägig, von der Idee bis zur Abrechnung.',
			'after-work-golf' => 'After-Work Golf bei Erlangen macht den Feierabend zum Teamtermin: kurze Anfahrt, zwei bis drei Stunden auf Range und Kurzplatz mit lockerer Anleitung vom Pro, danach ein entspannter Ausklang. Keine Ausrüstung, keine Vorkenntnisse nötig. Ideal als regelmäßiges Format für Abteilungen, die mehr wollen als den Stammtisch.',
			'weihnachtsfeier' => 'Für Teams aus Forschung, Technik und Verwaltung ist die Weihnachtsfeier mit Golf in Erlangen die Abwechslung zum immergleichen Jahresabschluss: eine angeleitete Challenge, bei der der Ehrgeiz schnell größer ist als gedacht, Glühwein, Siegerehrung und ein gemeinsames Essen. Im Winter indoor am Simulator, sonst im Clubhaus einer Anlage im Regnitzgrund. Wir planen den Abend komplett mit euch. Dezember-Termine früh sichern.',
		],
		'essen' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Essen nutzt die grünste Seite des Ruhrgebiets: Die Anlagen an Ruhr und Baldeneysee liegen mitten im Revier und fühlen sich doch wie Urlaub an. Ein Golflehrer führt euer Team an, Schläger und Material werden gestellt, und beim gemeinsamen Üben und Anfeuern wächst das Team ganz von selbst zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier in Essen spielt sich im grünen Ruhrtal, und die dichte Golflandschaft Nordrhein-Westfalens sorgt für viel Auswahl bei Platz und Termin. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble, Bewirtung am Platz und eine Siegerehrung mit Atmosphäre, auf Wunsch mit Sponsoren-Branding.',
			'platzreife'      => 'Die Platzreife rund um Essen führt euer Team Schritt für Schritt zum anerkannten Golfabschluss, mitten im grünen Ruhrtal. Über mehrere Kurstage vermittelt ein Golflehrer Schwung, Kurzspiel, Regeln und Etikette, den Abschluss bildet die Prüfung. Danach darf jeder im Team eigenständig auf Golfanlagen spielen, im Revier und darüber hinaus. Leihschläger sind während des Kurses inklusive.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Essener Team Abstand, ohne weit zu fahren: Konferenzraum mit Blick ins Grüne, Mittagessen auf der Clubterrasse über dem Ruhrtal, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner, den Rest organisieren wir.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz zeigt euren Gästen in Essen ein Ruhrgebiet, das viele so nicht kennen: grün, ruhig und überraschend schön an Ruhr und Baldeneysee. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, ihr euch um eure Gäste.',
			'incentive'       => 'Ein Golf-Incentive rund um Essen belohnt euer Team mit einem Tag im grünen Ruhrtal, auf Wunsch erweitert um Zeche Zollverein, Baldeneysee oder ein Dinner mit Aussicht. Ein Erlebnis, das länger trägt als jede Prämie. Wir stellen Golf, Verpflegung, Hotellerie und Programm zu einem runden Paket zusammen, ein- oder mehrtägig.',
			'after-work-golf' => 'After-Work Golf in Essen heißt: in wenigen Minuten vom Schreibtisch an die Range im Ruhrtal, zwei bis drei Stunden Golf mit lockerer Anleitung vom Pro, danach ein Ausklang mit dem Team. Kein großer Aufwand, keine Vorkenntnisse, dafür ein Feierabendtermin, der schnell Tradition wird.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf bringt euer Essener Team an einem Abend zusammen, ganz ohne Golf-Vorkenntnisse: Challenge mit Betreuung, Glühwein, Siegerehrung und Weihnachtsmenü. Im Winter indoor am Simulator, alternativ im Clubhaus einer der Anlagen an Ruhr und Baldeneysee, grün und doch mitten im Revier. Wir übernehmen Location, Ablauf und Catering. Die beliebten Termine ab Ende November früh anfragen.',
		],
		'garmisch-partenkirchen' => [
			'teamevent'       => 'Ein Golf-Teamevent in Garmisch-Partenkirchen spielt sich vor einer Kulisse, die jedes Teamfoto besonders macht: Wettersteinwand, Zugspitze und grüne Wiesen des Werdenfelser Landes. Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, und wir planen Ablauf und Verpflegung passend zu eurer Gruppe.',
			'golfturnier'     => 'Ein Firmen-Golfturnier in Garmisch-Partenkirchen ist mehr als ein Wettbewerb, es ist ein Tag in den Bergen, den eure Gäste so schnell nicht vergessen. Wir richten alles aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung mit Alpenblick, auf Wunsch mit Branding und Goodie-Bags.',
			'platzreife'      => 'Die Platzreife in Garmisch-Partenkirchen verbindet den anerkannten Einstieg in den Golfsport mit Kurstagen vor Bergpanorama. Ein Golflehrer vermittelt Technik, Regeln und Etikette, am Ende steht die Prüfung, danach spielt euer Team eigenständig, weltweit. Gerade mehrtägig ein Programm mit Erlebnischarakter. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop in Garmisch-Partenkirchen kombiniert konzentriertes Arbeiten mit einem Ort, der Kopf und Blick freimacht: Tagungsraum mit Bergblick, Mittagessen auf der Terrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer. Ideal für Strategie-Tage und Offsites, die im Gedächtnis bleiben sollen. Wir organisieren den kompletten Tag.',
			'kundenevent'     => 'Ein Kundenevent in Garmisch-Partenkirchen spielt die stärkste Karte der Region aus: die Berge. Golf vor Zugspitz-Kulisse, danach ein Abendessen mit Blick auf die Gipfel, das ist Hospitality, die haften bleibt. Wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz euren Gästen widmen könnt.',
			'incentive'       => 'Ein Golf-Incentive in Garmisch-Partenkirchen ist die Königsdisziplin unter den Teamerlebnissen: Golf im Werdenfelser Land, Hotels mit Bergblick, dazu Rahmenprogramm von Hüttenabend bis Zugspitzfahrt. Wir verbinden alles zu einem runden Paket, ein- oder mehrtägig, von der Idee bis zur Abrechnung. Eine Belohnung, über die noch lange gesprochen wird.',
			'after-work-golf' => 'After-Work Golf in Garmisch-Partenkirchen macht aus einem Feierabend einen kleinen Bergurlaub: zwei bis drei Stunden auf Range und Platz, vor euch die Wettersteinwand, danach ein Ausklang auf der Terrasse. Ein Pro leitet locker an, Vorkenntnisse braucht niemand. Für Teams aus dem Oberland der wohl schönste Wochentermin.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier in Garmisch-Partenkirchen hat die Kulisse schon eingebaut: verschneite Berge vor dem Fenster, drinnen Glühwein, Golf-Challenge und ein gemeinsames Menü. Ob im Clubhaus am Fuß von Wetterstein und Zugspitze oder indoor am Simulator, der Abend verbindet Team und Jahresausklang auf die entspannte Art. Wir organisieren Location, Ablauf und Essen. Im Advent ist das Werdenfelser Land gefragt, fragt früh an.',
		],
		'ingolstadt' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Ingolstadt bringt euer Team in kurzer Zeit aus dem Werk oder Büro in die ruhigen Donauauen. Gemeinsam üben, lachen, anfeuern: Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt. Gruppengröße, Ablauf und Verpflegung stellen wir passend zu eurem Anlass zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Ingolstadt liegt verkehrsgünstig zwischen München und Nürnberg, ideal, wenn eure Gäste aus mehreren Richtungen anreisen. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung, die den Tag rund macht.',
			'platzreife'      => 'Die Platzreife rund um Ingolstadt ist der offizielle Einstieg in den Golfsport, absolviert auf den ruhigen Anlagen zwischen Donau und Hügelland. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Ob als Firmenprogramm oder Benefit, danach spielt euer Team eigenständig. Material wird gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Ingolstädter Team Denkraum abseits von Taktzeiten und Terminen: Konferenzraum mit Tageslicht, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner, wir kümmern uns um den Rest.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch rund um Ingolstadt Zeit und Ruhe für echte Gespräche mit Kunden und Partnern, in den Donauauen statt am Messestand. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, ihr euch um eure Gäste.',
			'incentive'       => 'Ein Golf-Incentive rund um Ingolstadt belohnt euer Team mit einem Erlebnis in der Flusslandschaft der Donau, auf Wunsch erweitert um Altmühltal, Therme oder ein Abendprogramm in der Altstadt. Wir verbinden Golf, Verpflegung, Hotellerie und Rahmenprogramm zu einem runden Paket, ein- oder mehrtägig, aus einer Hand.',
			'after-work-golf' => 'After-Work Golf bei Ingolstadt passt in jeden Schichtplan: kurze Anfahrt, zwei bis drei Stunden auf Range und Kurzplatz mit lockerer Anleitung vom Pro, danach ein entspannter Ausklang. Keine Ausrüstung, keine Vorkenntnisse nötig, dafür ein Teamtermin, auf den sich alle freuen. Ideal als regelmäßiges Format.',
			'weihnachtsfeier' => 'Die Weihnachtsfeier mit Golf holt euer Ingolstädter Team aus Werk und Büro in einen Abend mit Wettbewerb und Genuss: angeleitete Golf-Challenge, Glühwein, Siegerehrung und Weihnachtsessen. Im Winter indoor am Simulator, sonst im Clubhaus einer Anlage an den Donauauen. Alles läuft über einen Ansprechpartner, von der Location bis zur Rechnung. Dezember-Termine sind schnell weg, am besten bis Oktober anfragen.',
		],
		'itzehoe' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Itzehoe bringt euer Team raus in die Knick- und Marschlandschaft Schleswig-Holsteins, ruhig, grün und herrlich unaufgeregt. Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, und wir stellen Platz, Ablauf und Verpflegung passend zu eurer Gruppe zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Itzehoe verbindet norddeutsche Weite mit kurzer Anreise, auch aus Hamburg über die A23. Wir richten euer Turnier komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung im Clubhaus, auf Wunsch mit Branding für euer Unternehmen.',
			'platzreife'      => 'Die Platzreife rund um Itzehoe ist der offizielle Einstieg in den Golfsport, absolviert in der ruhigen Landschaft zwischen Marsch und Geest. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung, danach spielt ihr eigenständig auf Plätzen im ganzen Norden. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Team rund um Itzehoe genau die Ruhe, die gute Entscheidungen brauchen: Tagungsraum mit Blick ins Grüne, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer. Vorkenntnisse braucht niemand, wir organisieren den kompletten Tag mit einem Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch rund um Itzehoe Zeit für Gespräche in entspannter norddeutscher Atmosphäre, weit weg vom Messelärm. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit gemeinsamem Essen, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive rund um Itzehoe belohnt euer Team mit Ruhe und Weite: Golf zwischen Knicks und Marsch, auf Wunsch kombiniert mit Nordsee, Elbe oder einem Abend in Hamburg. Wir verbinden Golf, Verpflegung, Hotellerie und Rahmenprogramm zu einem runden Paket, ein- oder mehrtägig, von der Idee bis zur Abrechnung.',
			'after-work-golf' => 'After-Work Golf bei Itzehoe ist Feierabend, wie er sein sollte: kurze Wege, frische Luft, zwei bis drei Stunden auf Range und Kurzplatz mit lockerer Anleitung vom Pro, danach ein Ausklang im Clubhaus. Kein großer Aufwand, keine Vorkenntnisse, ideal als regelmäßiger Teamtermin für Firmen aus der Region.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf bringt euer Team rund um Itzehoe in der dunklen Jahreszeit zusammen: drinnen wird an der Golf-Challenge gekämpft, draußen ruht die Marsch. Glühwein zur Begrüßung, Betreuung für alle Level, danach Siegerehrung und ein gemeinsames Essen im Clubhaus. Wir stellen den Abend komplett zusammen, passend zu eurer Gruppengröße. Für Termine im Advent empfiehlt sich eine frühe Anfrage.',
		],
		'karlsruhe' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Karlsruhe profitiert vom badischen Klima: Die Saison ist hier länger als fast überall in Deutschland, und die Plätze zwischen Rheinebene und Schwarzwaldrand liegen nah an der Stadt. Ein Golflehrer führt euer Team an, Schläger und Material werden gestellt, Ablauf und Verpflegung planen wir passend zu eurer Gruppe.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Karlsruhe lässt sich dank des milden badischen Klimas vom Frühjahr bis weit in den Herbst planen. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung mit Atmosphäre, auf Wunsch mit Sponsoren-Branding und Goodie-Bags.',
			'platzreife'      => 'Die Platzreife rund um Karlsruhe ist der offizielle Einstieg in den Golfsport, absolviert auf den Anlagen zwischen Rheinebene, Kraichgau und Nordschwarzwald. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Die lange badische Saison macht auch Herbsttermine gut planbar. Material wird gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Karlsruher Team Denkraum im Grünen: Konferenzraum mit Tageslicht, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ideal für Strategie-Tage der Technologieregion, ein Tag, ein Ort, ein Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch rund um Karlsruhe Zeit und Atmosphäre für Gespräche mit Kunden aus der Technologieregion und darüber hinaus. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive rund um Karlsruhe verbindet Golf mit einer Region voller Möglichkeiten: Schwarzwald, Pfalz und Elsass liegen für das Rahmenprogramm direkt vor der Tür. Wir stellen Golf, Verpflegung, Hotellerie und Programm zu einem runden Paket zusammen, ein- oder mehrtägig. Eine Belohnung, die länger wirkt als jede Prämie.',
			'after-work-golf' => 'After-Work Golf bei Karlsruhe nutzt die langen badischen Abende: nach Feierabend auf die Range, zwei bis drei Stunden Golf mit lockerer Anleitung vom Pro, danach ein Ausklang auf der Terrasse. Kein großer Aufwand, keine Vorkenntnisse, dafür ein Teamtermin, der sich schnell etabliert.',
			'weihnachtsfeier' => 'In Karlsruhe spielt das badische Klima der Weihnachtsfeier mit Golf in die Karten: Selbst im Dezember sind milde Tage möglich, und wenn nicht, übernehmen Simulator und Clubhaus. Golf-Challenge mit Anleitung, Glühwein, Siegerehrung und Weihnachtsmenü, alles an einem Ort zwischen Rheinebene und Kraichgau oder indoor in der Stadt. Wir organisieren den Abend von A bis Z. Beliebte Termine früh anfragen.',
		],
		'kiel' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Kiel hat immer eine Brise Ostsee dabei: Die Plätze zwischen Förde, Knicks und Hügelland liegen nur eine kurze Fahrt von der Stadt entfernt. Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, und wir planen Ablauf und Verpflegung passend zu eurer Gruppe.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Kiel spielt sich in der frischen Luft der Ostseeküste, auf Plätzen, wo der Wind das Spiel fair durchmischt. Wir richten euer Turnier komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung im Clubhaus, auf Wunsch mit Branding und Goodie-Bags.',
			'platzreife'      => 'Die Platzreife rund um Kiel ist der offizielle Einstieg in den Golfsport, absolviert auf den Anlagen zwischen Förde und Hügelland. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung, danach spielt ihr eigenständig, an der Küste und überall sonst. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Kieler Team klare Gedanken bei klarer Luft: Tagungsraum mit Blick ins Grüne, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner, den kompletten Ablauf organisieren wir.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz verbindet in Kiel maritimes Flair mit Zeit für echte Gespräche. Golf am Vormittag, danach ein Essen mit Blick Richtung Förde, das bleibt bei Gästen hängen. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm.',
			'incentive'       => 'Ein Golf-Incentive rund um Kiel kombiniert Golf mit dem, was die Landeshauptstadt besonders macht: Ostsee, Segeln und maritimes Rahmenprogramm. Als Belohnung für euer Team oder Dankeschön an Partner wirkt so ein Erlebnis länger als jede Prämie. Wir bauen das Paket komplett, ein- oder mehrtägig, mit Hotel und Verpflegung.',
			'after-work-golf' => 'After-Work Golf in Kiel heißt Feierabend mit Seeluft: zwei bis drei Stunden auf Range und Kurzplatz, locker angeleitet von einem Pro, danach ein Ausklang im Clubhaus. Kein ganzer Tag, kein großer Aufwand, keine Vorkenntnisse. Für Teams aus Kiel und Umgebung ein Termin mit hohem Wiederholungsfaktor.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf an der Förde: drinnen warm, draußen weht die Ostseebrise. Euer Kieler Team tritt in einer angeleiteten Golf-Challenge an, mit Glühwein in der Hand und Live-Wertung, danach gibt es Siegerehrung und Weihnachtsessen. Im Simulator oder im Clubhaus einer Anlage zwischen Förde und Hügelland. Wir kümmern uns um Location, Ablauf und Menü. Advent-Termine früh sichern.',
		],
		'landshut' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Landshut bringt euer Team raus ins niederbayerische Hügelland, zwischen Isartal und Hopfenland, ruhig und schnell erreichbar. Gemeinsam üben, lachen, anfeuern: Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, Ablauf und Verpflegung planen wir mit euch.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Landshut liegt günstig für Gäste aus Niederbayern, München und vom Flughafen, kurze Wege inklusive. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung, die den Tag würdig abschließt.',
			'platzreife'      => 'Die Platzreife rund um Landshut ist der offizielle Einstieg in den Golfsport, absolviert auf den ruhigen Anlagen des niederbayerischen Hügellands. Ein Golflehrer vermittelt eurem Team Technik, Regeln und Etikette, am Ende steht die anerkannte Prüfung, danach wird eigenständig gespielt. Als Firmenprogramm oder Benefit geeignet, Material wird gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Landshuter Team einen Tag mit Struktur und Weitblick: vormittags konzentriertes Arbeiten im Tagungsraum, mittags Buffet auf der Clubterrasse, nachmittags bringt ein Golf-Grundlagenkurs mit Golflehrer alle in Bewegung. Vorkenntnisse braucht niemand, wir organisieren alles mit einem Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch rund um Landshut Ruhe und Zeit für Gespräche mit Kunden und Partnern, im Grünen statt im Besprechungsraum. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit gemeinsamem Essen, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, ihr euch um eure Gäste.',
			'incentive'       => 'Ein Golf-Incentive rund um Landshut verbindet Golf im Isartal mit einem Rahmenprogramm zwischen Altstadt, Burg Trausnitz und niederbayerischer Genusskultur. Als Belohnung für euer Team wirkt so ein Tag länger als jede Prämie. Wir stellen Golf, Verpflegung, Hotellerie und Programm zu einem runden Paket zusammen, ein- oder mehrtägig.',
			'after-work-golf' => 'After-Work Golf bei Landshut macht den Feierabend zum Höhepunkt der Woche: kurze Anfahrt, zwei bis drei Stunden auf Range und Kurzplatz mit lockerer Anleitung vom Pro, danach ein Ausklang mit Blick ins Hügelland. Keine Ausrüstung, keine Vorkenntnisse nötig, ideal als regelmäßiger Teamtermin.',
			'weihnachtsfeier' => 'Die Weihnachtsfeier mit Golf gibt eurem Team in Landshut einen Jahresabschluss mit Erlebnis-Faktor: Challenge mit Betreuung, Glühwein, Siegerehrung und ein gemeinsames Menü. Im Winter indoor am Simulator, sonst im Clubhaus einer Anlage im niederbayerischen Hügelland zwischen Isartal und Hopfenland. Eine Anfrage genügt, wir stellen alles zusammen. Dezember-Termine am besten bis Oktober anfragen.',
		],
		'leipzig' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Leipzig bringt euer Team raus ins Neuseenland, wo aus Tagebauten eine weite Freizeitlandschaft geworden ist. Golf passt perfekt dazu: gemeinsam üben, lachen, anfeuern, angeleitet von einem Golflehrer, der auch komplette Einsteigende sicher führt. Schläger und Material werden gestellt, Ablauf und Verpflegung planen wir mit euch.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Leipzig verbindet sportlichen Wettbewerb mit der jungen, weiten Landschaft des Neuseenlands. Wir richten euer Turnier komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung mit Atmosphäre, auf Wunsch mit Branding für Sponsoren und Partner.',
			'platzreife'      => 'Die Platzreife rund um Leipzig ist der offizielle Einstieg in den Golfsport, absolviert auf den Anlagen zwischen Neuseenland und sächsischem Flachland. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Wer besteht, spielt danach eigenständig, in Sachsen und weltweit. Material wird gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Leipziger Team frischen Blick abseits von Spinnerei und Coworking: Tagungsraum mit Tageslicht, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner, wir kümmern uns um den Rest.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch rund um Leipzig Zeit und Atmosphäre für echte Gespräche, am Wasser des Neuseenlands statt im Konferenzsaal. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner am See, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive rund um Leipzig belohnt euer Team mit einem Erlebnis in einer Region im Aufbruch: Golf im Neuseenland, danach Bootstour, Strandbar oder ein Abend in der Leipziger Innenstadt. Wir verbinden Golf, Verpflegung, Hotellerie und Rahmenprogramm zu einem runden Paket, ein- oder mehrtägig, von der Idee bis zur Abrechnung.',
			'after-work-golf' => 'After-Work Golf bei Leipzig holt euer Team nach Feierabend raus ans Grün: zwei bis drei Stunden auf Range und Kurzplatz, locker angeleitet von einem Pro, danach ein Ausklang, gern mit Seeblick. Kein ganzer Tag, kein großer Aufwand, keine Vorkenntnisse. Ideal als regelmäßiger Teamabend.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf macht den Jahresausklang in Leipzig besonders: erst die angeleitete Golf-Challenge, bei der wirklich alle mitspielen, dann Glühwein, Siegerehrung und Weihnachtsessen. Im Winter indoor am Simulator, alternativ im Clubhaus einer Anlage im Neuseenland. Wir planen Location, Ablauf und Verpflegung passend zu eurem Team. Die beliebten Advent-Termine sind früh vergeben.',
		],
		'luebeck' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Lübeck bringt euer Team an die Ostseekante: Die Plätze zwischen Trave, Küste und Holsteinischer Schweiz liegen nur eine kurze Fahrt von der Altstadt entfernt. Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, Ablauf und Verpflegung planen wir passend zu eurer Gruppe.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Lübeck verbindet Wettbewerb mit Ostseeluft und hanseatischem Rahmen. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung, die den Tag rund macht, auf Wunsch mit Branding für euer Unternehmen.',
			'platzreife'      => 'Die Platzreife rund um Lübeck ist der offizielle Einstieg in den Golfsport, absolviert zwischen Küste und Holsteinischer Schweiz. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung, danach spielt ihr eigenständig, an der Ostsee und überall sonst. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Lübecker Team Seeluft für klare Entscheidungen: Tagungsraum mit Blick ins Grüne, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner, den Rest organisieren wir.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz spielt in Lübeck mit einer doppelten Kulisse: Golf im Grünen und die Hansestadt mit Holstentor und Altstadt für den Ausklang. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, ihr euch um eure Gäste.',
			'incentive'       => 'Ein Golf-Incentive rund um Lübeck verbindet Golf mit Ostsee-Erlebnis: Strandspaziergang in Travemünde, Abendessen in der Altstadt, Übernachtung an der Küste. Als Belohnung für euer Team wirkt so ein Wochenende länger als jede Prämie. Wir bauen das Paket komplett, ein- oder mehrtägig, mit Hotel, Verpflegung und Programm.',
			'after-work-golf' => 'After-Work Golf bei Lübeck heißt: nach Feierabend raus an die frische Luft, zwei bis drei Stunden auf Range und Kurzplatz mit lockerer Anleitung vom Pro, danach ein Ausklang im Clubhaus. Kein großer Aufwand, keine Vorkenntnisse, dafür ein Teamtermin mit Küstenklima, der schnell zur festen Runde wird.',
			'weihnachtsfeier' => 'Marzipanstadt trifft Golfschwung: Die Weihnachtsfeier mit Golf bringt euer Lübecker Team in Adventsstimmung zusammen, mit Glühwein, Challenge und gemeinsamem Menü. Im Winter indoor am Simulator, sonst im Clubhaus einer Anlage zwischen Trave, Küste und Holsteinischer Schweiz. Betreuung inklusive, Vorkenntnisse braucht niemand. Wir organisieren den kompletten Abend. Für Dezember früh anfragen.',
		],
		'lueneburg' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Lüneburg bringt euer Team in die ruhige Landschaft am Rand der Heide, zwischen Ilmenau, Wäldern und weiten Flächen. Genau der richtige Rahmen, um gemeinsam Neues auszuprobieren: Ein Golflehrer führt alle sicher an, Schläger und Material werden gestellt, Ablauf und Verpflegung planen wir mit euch.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Lüneburg verbindet Wettbewerb mit der Ruhe der Heidelandschaft, gut erreichbar auch für Gäste aus Hamburg. Wir richten euer Turnier komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung im Clubhaus, auf Wunsch mit Branding.',
			'platzreife'      => 'Die Platzreife rund um Lüneburg ist der offizielle Einstieg in den Golfsport, absolviert in der ruhigen Landschaft der Heide. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung, danach spielt ihr eigenständig, in Niedersachsen und überall sonst. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Lüneburger Team die Ruhe der Heide für konzentriertes Arbeiten: Tagungsraum mit Tageslicht, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner, wir organisieren den kompletten Ablauf.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch rund um Lüneburg entschleunigte Stunden für Gespräche, die im Alltag untergehen, mit der historischen Salzstadt als Kulisse für den Ausklang. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm.',
			'incentive'       => 'Ein Golf-Incentive rund um Lüneburg belohnt euer Team mit Heide, Ruhe und Zeit: Golf am Vormittag, danach Altstadt, Sauna oder Kanutour auf der Ilmenau. Ein Erlebnis, das länger trägt als jede Prämie. Wir verbinden Golf, Verpflegung, Hotellerie und Rahmenprogramm zu einem runden Paket, ein- oder mehrtägig.',
			'after-work-golf' => 'After-Work Golf bei Lüneburg macht aus dem Feierabend eine kleine Auszeit: zwei bis drei Stunden auf Range und Kurzplatz in der Heidelandschaft, locker angeleitet von einem Pro, danach ein Ausklang im Clubhaus. Kein großer Aufwand, keine Vorkenntnisse, ideal als regelmäßiger Teamtermin.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf gibt eurem Team rund um Lüneburg einen Jahresabschluss mit Charakter: angeleitete Golf-Challenge, Glühwein, Siegerehrung und Weihnachtsessen in gemütlicher Runde. Im Winter indoor am Simulator, alternativ im Clubhaus einer Anlage am Rand der Heide. Wir stellen Location, Ablauf und Menü zusammen, ihr bringt nur euer Team mit. Advent-Termine am besten bis Oktober sichern.',
		],
		'mannheim' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Mannheim profitiert von der Lage der Quadratestadt: Rheinebene, Bergstraße und Pfalz bieten Plätze in jede Richtung, alle auf kurzen Wegen. Ein Golflehrer führt euer Team an, auch ohne jede Vorerfahrung, Schläger und Material werden gestellt. Gruppengröße, Ablauf und Verpflegung stellen wir passend zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Mannheim erreicht Gäste aus der ganzen Rhein-Neckar-Region schnell, auch mit der Bahn. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung mit Atmosphäre, auf Wunsch mit Sponsoren-Branding und Goodie-Bags.',
			'platzreife'      => 'Die Platzreife rund um Mannheim ist der offizielle Einstieg in den Golfsport, absolviert auf den Anlagen zwischen Rheinebene, Bergstraße und Pfalz. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Als Firmenprogramm oder Benefit mit Langzeitwirkung. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Mannheimer Team Denkraum außerhalb der Quadrate: Konferenzraum mit Blick ins Grüne, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner, wir kümmern uns um alles.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch in der Rhein-Neckar-Region Zeit und Atmosphäre, um Kunden einmal anders zu erleben, zwischen Reben, Ebene und Odenwaldrand. Ob Hospitality-Tag, Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive rund um Mannheim verbindet Golf mit den Genussregionen vor der Tür: Pfalz, Bergstraße und Odenwald liefern Weinprobe, Ausblick und Abendprogramm. Als Belohnung für euer Team wirkt so ein Tag länger als jede Prämie. Wir stellen Golf, Verpflegung, Hotellerie und Programm zu einem runden Paket zusammen.',
			'after-work-golf' => 'After-Work Golf bei Mannheim passt zwischen Projektabschluss und Feierabendbier: kurze Anfahrt, zwei bis drei Stunden auf Range und Kurzplatz mit lockerer Anleitung vom Pro, danach ein Ausklang auf der Terrasse. Keine Ausrüstung, keine Vorkenntnisse nötig, ideal als regelmäßiger Teamtermin in der Rhein-Neckar-Region.',
			'weihnachtsfeier' => 'Von den Quadraten in die Weihnachtsfeier mit Schwung: In Mannheim feiert euer Team mit Golf-Challenge, Glühwein und gemeinsamem Menü, indoor am Simulator oder im Clubhaus einer Anlage zwischen Rheinebene, Bergstraße und Pfalz. Die Challenge funktioniert für alle Level, ein Betreuer leitet an, die Siegerehrung liefert die Geschichten fürs neue Jahr. Wir organisieren alles. Dezember-Termine früh anfragen.',
		],
		'penzberg' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Penzberg liegt für Teams aus dem Oberland direkt vor der Tür: Die Anlagen zwischen Starnberger See, Osterseen und Alpenrand sind in wenigen Minuten erreichbar, Bergblick inklusive. Ein Golflehrer führt alle sicher an, Schläger und Material werden gestellt, Ablauf und Verpflegung planen wir mit euch.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Penzberg spielt sich vor Alpenpanorama, und genau das macht die Siegerehrung auf der Terrasse so besonders. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und auf Wunsch Branding für euer Unternehmen oder eure Sponsoren.',
			'platzreife'      => 'Die Platzreife rund um Penzberg ist der offizielle Einstieg in den Golfsport, absolviert auf den Anlagen des Oberlands mit Blick auf die Berge. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung, danach wird eigenständig gespielt. Gerade als Benefit im Oberland beliebt. Material wird gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Penzberger Team Bergluft für gute Entscheidungen: Tagungsraum mit Tageslicht, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ideal für Strategie-Tage, die raus aus dem Gebäude sollen. Wir organisieren den kompletten Tag.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch im Oberland eine Kulisse, die Eindruck hinterlässt: Golf mit Alpenblick, danach ein Essen auf der Terrasse. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr Zeit für eure Gäste habt.',
			'incentive'       => 'Ein Golf-Incentive rund um Penzberg nutzt das Beste des Oberlands: Golf vor Bergkulisse, dazu Seen, Klöster und Almen für das Rahmenprogramm, auf Wunsch mit Übernachtung Richtung Alpen. Wir verbinden Golf, Verpflegung, Hotellerie und Programm zu einem runden Paket, ein- oder mehrtägig, von der Idee bis zur Abrechnung.',
			'after-work-golf' => 'After-Work Golf bei Penzberg ist der Feierabend, für den man im Oberland wohnt: in wenigen Minuten auf der Range, zwei bis drei Stunden Golf mit lockerer Anleitung vom Pro, die Berge im Blick, danach ein Ausklang auf der Terrasse. Kein großer Aufwand, keine Vorkenntnisse, ideal als fester Teamtermin.',
			'weihnachtsfeier' => 'Im Oberland hat die Weihnachtsfeier mit Golf einen besonderen Rahmen: Anlagen zwischen Starnberger See, Osterseen und Alpenrand, im Advent oft schon verschneit, dazu Clubhaus-Wärme, Glühwein und ein gemeinsames Menü. Die Golf-Challenge läuft angeleitet und indoor-tauglich, alle machen mit. Wir stellen den Abend für euer Penzberger Team komplett zusammen. Beliebte Termine ab Ende November früh sichern.',
		],
		'regensburg' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Regensburg bringt euer Team raus in die ostbayerische Landschaft zwischen Donautal, Jura und Vorwald. Gemeinsam üben, lachen, anfeuern: Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, und wir stellen Platz, Ablauf und Verpflegung passend zu eurer Gruppe zusammen.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Regensburg verbindet sportlichen Wettbewerb mit ostbayerischer Gelassenheit, und für den Ausklang wartet eine Welterbe-Altstadt. Wir richten euer Turnier komplett aus: Startlisten, faire Zählformate wie Scramble, Bewirtung am Platz und eine Siegerehrung mit Atmosphäre, auf Wunsch mit Sponsoren-Branding.',
			'platzreife'      => 'Die Platzreife rund um Regensburg ist der offizielle Einstieg in den Golfsport, absolviert auf den ruhigen Anlagen Ostbayerns. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Wer besteht, spielt danach eigenständig, an der Donau und weltweit. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Regensburger Team Denkraum abseits des Tagesgeschäfts: Konferenzraum mit Tageslicht, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner, den kompletten Ablauf organisieren wir.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz hat in Regensburg einen unschlagbaren zweiten Akt: Nach dem Golf wartet die Welterbe-Altstadt mit Steinerner Brücke und Domblick für Dinner und Ausklang. Wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, damit ihr euch ganz auf eure Gäste konzentrieren könnt.',
			'incentive'       => 'Ein Golf-Incentive rund um Regensburg verbindet Golf in Ostbayern mit einem Rahmenprogramm, das von Donauschifffahrt bis Altstadtabend reicht. Als Belohnung für euer Team wirkt so ein Erlebnis länger als jede Prämie. Wir stellen Golf, Verpflegung, Hotellerie und Programm zu einem runden Paket zusammen, ein- oder mehrtägig.',
			'after-work-golf' => 'After-Work Golf bei Regensburg holt euer Team nach Feierabend an die frische Luft: zwei bis drei Stunden auf Range und Kurzplatz, locker angeleitet von einem Pro, danach ein Ausklang im Clubhaus oder in der Altstadt. Kein großer Aufwand, keine Vorkenntnisse, ideal als regelmäßiger Teamabend.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf verbindet in Regensburg Welterbe-Romantik mit Team-Wettbewerb: erst die angeleitete Challenge, indoor am Simulator oder im Clubhaus zwischen Donautal und Jura, dann Glühwein, Siegerehrung und Weihnachtsessen. Vorkenntnisse braucht niemand, der Ehrgeiz kommt von selbst. Location, Ablauf und Menü organisieren wir mit einem Ansprechpartner. Advent-Termine früh anfragen.',
		],
		'rosenheim' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Rosenheim spielt zwischen Inntal, Chiemgau und Mangfalltal, an klaren Tagen mit Alpenpanorama. Der perfekte Rahmen, um als Team etwas Neues auszuprobieren: Ein Golflehrer führt alle sicher an, Schläger und Material werden gestellt, Ablauf und Verpflegung planen wir passend zu eurer Gruppe.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Rosenheim verbindet Wettbewerb mit Voralpenkulisse, gut erreichbar über A8 und A93 auch für Gäste aus München und Salzburg. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble, Bewirtung am Platz und eine Siegerehrung mit Bergblick, auf Wunsch mit Branding.',
			'platzreife'      => 'Die Platzreife rund um Rosenheim ist der offizielle Einstieg in den Golfsport, absolviert auf den Anlagen zwischen Inntal und Chiemgau. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung, danach spielt ihr eigenständig, im Alpenvorland und weltweit. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Rosenheimer Team Bergblick statt Bürowand: Tagungsraum mit Tageslicht, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ideal für Strategie-Tage im Alpenvorland. Wir organisieren den kompletten Tag mit einem Ansprechpartner.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch rund um Rosenheim eine Kulisse, die Gäste begeistert: Golf im Alpenvorland, danach ein Ausklang Richtung Chiemsee oder in der Altstadt. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm.',
			'incentive'       => 'Ein Golf-Incentive rund um Rosenheim schöpft aus dem Vollen: Golf vor Bergkulisse, Chiemsee und Berge für das Rahmenprogramm, auf Wunsch mit Hüttenabend oder Schifffahrt. Als Belohnung wirkt so ein Erlebnis länger als jede Prämie. Wir bauen das Paket komplett, ein- oder mehrtägig, mit Hotel, Verpflegung und Programm.',
			'after-work-golf' => 'After-Work Golf bei Rosenheim macht den Feierabend alpin: zwei bis drei Stunden auf Range und Kurzplatz, an klaren Tagen mit Blick auf die Berge, locker angeleitet von einem Pro. Danach ein Ausklang auf der Terrasse. Kein großer Aufwand, keine Vorkenntnisse, ideal als fester Teamtermin im Alpenvorland.',
			'weihnachtsfeier' => 'Zwischen Inntal und Chiemgau feiert es sich besonders: Die Weihnachtsfeier mit Golf bringt euer Rosenheimer Team zu Glühwein, Challenge und gemeinsamem Menü zusammen, an klaren Wintertagen mit Alpenblick vom Clubhaus, sonst indoor am Simulator. Ein Betreuer leitet die Challenge an, die Siegerehrung macht den Abend rund. Wir übernehmen die komplette Organisation. Dezember-Termine am besten bis Oktober sichern.',
		],
		'ulm' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Ulm bringt euer Team wahlweise auf die Höhen der Schwäbischen Alb oder ins sanfte Oberschwaben, beides nur eine kurze Fahrt von der Doppelstadt entfernt. Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, Ablauf und Verpflegung planen wir mit euch.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Ulm liegt verkehrsgünstig an der A8 zwischen Stuttgart und München, ideal für Gäste aus mehreren Regionen. Wir übernehmen die komplette Ausrichtung: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung, die den Tag rund macht, auf Wunsch mit Branding.',
			'platzreife'      => 'Die Platzreife rund um Ulm bringt euer Team in mehreren Kurstagen vom ersten Schwung bis zur bestandenen Prüfung. Gespielt und gelernt wird auf den Anlagen zwischen Schwäbischer Alb und Oberschwaben, unter Anleitung eines Golflehrers: Technik, Regeln, Etikette. Mit dem Abschluss in der Tasche steht dem eigenständigen Spiel nichts mehr im Weg, hier und weltweit. Leihmaterial ist während des Kurses inklusive.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz gibt eurem Ulmer Team Weitblick, wie ihn sonst nur das Münster bietet: Tagungsraum mit Tageslicht, Mittagessen auf der Clubterrasse, zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, ganz ohne Vorkenntnisse. Ein Tag, ein Ort, ein Ansprechpartner, wir kümmern uns um den Rest.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz gibt euch rund um Ulm Zeit und Ruhe für Gespräche mit Kunden aus der Region zwischen Stuttgart und München. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit gemeinsamem Abendessen, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm, ihr euch um eure Gäste.',
			'incentive'       => 'Ein Golf-Incentive rund um Ulm verbindet Golf auf Alb oder in Oberschwaben mit einem Rahmenprogramm zwischen Donau, Münster und Legoland für den lockeren Teil. Als Belohnung für euer Team wirkt so ein Tag länger als jede Prämie. Wir stellen Golf, Verpflegung, Hotellerie und Programm zu einem runden Paket zusammen.',
			'after-work-golf' => 'After-Work Golf bei Ulm passt in jeden Feierabend: kurze Anfahrt aus Ulm oder Neu-Ulm, zwei bis drei Stunden auf Range und Kurzplatz mit lockerer Anleitung vom Pro, danach ein Ausklang im Clubhaus. Keine Ausrüstung, keine Vorkenntnisse nötig, ideal als regelmäßiger Teamtermin.',
			'weihnachtsfeier' => 'Eine Weihnachtsfeier mit Golf gibt eurem Ulmer Team einen Jahresabschluss zwischen Alb und Oberschwaben: Golf-Challenge mit Anleitung, Glühwein, Siegerehrung und Weihnachtsmenü. Im Winter indoor am Simulator, alternativ im warmen Clubhaus einer Anlage der Region. Alle spielen mit, niemand braucht Vorkenntnisse. Wir stellen Location, Ablauf und Essen zusammen. Die beliebten Advent-Termine früh anfragen.',
		],
		'wuerzburg' => [
			'teamevent'       => 'Ein Golf-Teamevent rund um Würzburg bringt euer Team raus nach Mainfranken, zwischen Maintal, Weinberge und fränkisches Hügelland. Gemeinsam üben, lachen, anfeuern: Ein Golflehrer führt auch komplette Einsteigende sicher an, Schläger und Material werden gestellt, und wir planen Platz, Ablauf und Verpflegung passend zu eurer Gruppe.',
			'golfturnier'     => 'Ein Firmen-Golfturnier bei Würzburg liegt zentral am Kreuz von A3 und A7, ideal, wenn eure Gäste aus mehreren Regionen anreisen. Wir richten euer Turnier komplett aus: Startlisten, faire Zählformate wie Scramble für gemischte Level, Bewirtung am Platz und eine Siegerehrung, auf Wunsch mit fränkischem Wein statt Sekt.',
			'platzreife'      => 'Die Platzreife rund um Würzburg ist der offizielle Einstieg in den Golfsport, absolviert auf den ruhigen Anlagen Mainfrankens. Ein Golflehrer bringt eurem Team Technik, Regeln und Etikette bei, am Ende steht die anerkannte Prüfung. Wer besteht, spielt danach eigenständig, in Franken und weltweit. Material wird während des Kurses gestellt.',
			'workshop'        => 'Ein Workshop auf dem Golfplatz verlegt euren Strategie-Tag von Würzburg raus ins Grüne von Mainfranken. Vormittags wird konzentriert im Tagungsraum gearbeitet, mittags gibt es Buffet auf der Clubterrasse, nachmittags bringt ein Golf-Grundlagenkurs mit Golflehrer Bewegung in die Gruppe, ganz ohne Vorkenntnisse. Den Ausklang übernimmt die Region, gern mit einem Schoppen mit Blick auf die Reben.',
			'kundenevent'     => 'Ein Kundenevent auf dem Golfplatz hat in Würzburg einen Ausklang, den Gäste lieben: Nach dem Golf geht es in die Weinberge oder auf die Alte Mainbrücke, Schoppen inklusive. Ob Hospitality-Tag, kleines Turnier oder Schnupperteil mit Dinner, wir kümmern uns um Platz, Ablauf, Catering und Rahmenprogramm.',
			'incentive'       => 'Ein Golf-Incentive rund um Würzburg verbindet Golf in Mainfranken mit Weinprobe, Residenz und einem Abend über den Dächern der Stadt. Als Belohnung für euer Team oder Dankeschön an Partner wirkt so ein Erlebnis länger als jede Prämie. Wir bauen das Paket komplett, ein- oder mehrtägig, mit Hotel, Verpflegung und Programm.',
			'after-work-golf' => 'After-Work Golf bei Würzburg macht aus dem Feierabend einen kleinen Frankenurlaub: zwei bis drei Stunden auf Range und Kurzplatz, locker angeleitet von einem Pro, danach ein Ausklang mit Blick auf Reben oder Maintal. Kein großer Aufwand, keine Vorkenntnisse, ideal als regelmäßiger Teamtermin.',
			'weihnachtsfeier' => 'In Mainfranken passt die Weihnachtsfeier mit Golf perfekt zwischen Weinberge und Adventszeit: Challenge mit Betreuung, Glühwein oder ein Glas Frankenwein, Siegerehrung und gemeinsames Menü. Im Winter indoor am Simulator, sonst im Clubhaus einer Anlage im Maintal. Euer Würzburger Team braucht keine Vorkenntnisse, nur gute Laune. Wir organisieren den Abend komplett. Dezember füllt sich früh, fragt rechtzeitig an.',
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
	// Nur Formate mit eigenem Stadt-Text (fge_citformat_format_meta): sonst entstünden
	// dünne Seiten mit Stadt-Titel und leerer Description (Review 07.09., Sommerfest).
	$metas = fge_citformat_format_meta();
	return isset( $cities[ $city_slug ], $formats[ $format_slug ], $metas[ $format_slug ] );
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
