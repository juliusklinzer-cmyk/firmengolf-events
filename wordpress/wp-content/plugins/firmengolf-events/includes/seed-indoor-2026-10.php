<?php
/**
 * Platzhalter-Events Indoor (Julius, 06.10.2026): je Ort mit Golfsimulatoren in der Nähe
 * „Indoor Team Golf in {Ort}“ (Typ indoor-golf) und „Indoor Golf Weihnachtsfeier in {Ort}“
 * (Typ weihnachtsfeier). Von Firmengolf organisiert, ohne Partner.
 *
 * Preise so gerechnet, dass eine Nicht-Partner-Anlage in der Region beim Anfragen nah dran
 * liegt (Masterbestand 29.09., Blatt Simulatoren): Boxpreis je Stunde (abends, Gruppen) aus
 * den Preisangaben der Region, sonst der drei nächsten Anlagen, sonst Bundesmedian 40 €.
 *   Team Golf:       Preis pro Box und Stunde (Einkauf = regionaler Boxpreis), bis 6 Personen pro Box,
 *                    mind. 2 Std.; Einweisung Pro, Getränke und Essen als Zusatzleistungen (Julius, 06.10.).
 *   Weihnachtsfeier: p. P., Referenz 12 Personen, 4 je Box (3 Boxen):
 *                    (3 Boxen × 4 Std. × Boxpreis + 70 € Einweisung Pro) / 12 + 45 € Essen, Getränke, Glühwein
 * price_amount = Einkauf netto p. P., Kundenpreis = +20 % Aufschlag und Preisglättung wie überall.
 *
 * Orte, in denen es schon „Weihnachtsfeier mit Golf in …“ gibt (Seed T3), bekommen keine zweite
 * Weihnachtsfeier; dort wird nur der Preis des bestehenden Platzhalters auf den regionalen Wert gesetzt.
 * Einmalig (Option fge_seed_indoor_2026_10), angelegte IDs stehen in der Option (rücknehmbar).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<int,array<string,mixed>> */
function fge_seed_indoor_2026_10_rows(): array {
	return [
		[ 'ort' => 'Berlin', 'slug' => 'berlin', 'lat' => 52.5049, 'lng' => 13.385, 'team_net' => 35.5, 'xmas_net' => 86.33, 'existing_xmas' => 'Berlin', 'basis' => '35.5 €/Std. je Box, eigene Region (4 Preisangaben)' ],
		[ 'ort' => 'München', 'slug' => 'muenchen', 'lat' => 48.1437, 'lng' => 11.5598, 'team_net' => 42.5, 'xmas_net' => 93.33, 'existing_xmas' => 'München', 'basis' => '42.5 €/Std. je Box, eigene Region (2 Preisangaben)' ],
		[ 'ort' => 'Köln', 'slug' => 'koeln', 'lat' => 50.9404, 'lng' => 6.9606, 'team_net' => 35, 'xmas_net' => 85.83, 'existing_xmas' => 'Köln', 'basis' => '35 €/Std. je Box, eigene Region (3 Preisangaben)' ],
		[ 'ort' => 'Frankfurt am Main', 'slug' => 'frankfurt-am-main', 'lat' => 50.1194, 'lng' => 8.654, 'team_net' => 55, 'xmas_net' => 105.83, 'existing_xmas' => 'Frankfurt', 'basis' => '55 €/Std. je Box, eigene Region (1 Preisangaben)' ],
		[ 'ort' => 'Hamburg', 'slug' => 'hamburg', 'lat' => 53.573, 'lng' => 10.0185, 'team_net' => 47.5, 'xmas_net' => 98.33, 'existing_xmas' => 'Hamburg', 'basis' => '47.5 €/Std. je Box, eigene Region (6 Preisangaben)' ],
		[ 'ort' => 'Düsseldorf', 'slug' => 'duesseldorf', 'lat' => 51.2211, 'lng' => 6.7941, 'team_net' => 35.5, 'xmas_net' => 86.33, 'existing_xmas' => 'Düsseldorf', 'basis' => '35.5 €/Std. je Box, eigene Region (2 Preisangaben)' ],
		[ 'ort' => 'Essen', 'slug' => 'essen', 'lat' => 51.4503, 'lng' => 7.0151, 'team_net' => 36, 'xmas_net' => 86.83, 'existing_xmas' => 'Essen', 'basis' => '36 €/Std. je Box, Nachbarregion (Mülheim an der Ruhr, Wuppertal, Düsseldorf)' ],
		[ 'ort' => 'Leipzig', 'slug' => 'leipzig', 'lat' => 51.3545, 'lng' => 12.3692, 'team_net' => 39, 'xmas_net' => 89.83, 'existing_xmas' => 'Leipzig', 'basis' => '39 €/Std. je Box, Nachbarregion (Chemnitz, Berlin, Berlin)' ],
		[ 'ort' => 'Nürnberg', 'slug' => 'nuernberg', 'lat' => 49.444, 'lng' => 11.0785, 'team_net' => 35, 'xmas_net' => 85.83, 'existing_xmas' => '', 'basis' => '35 €/Std. je Box, eigene Region (1 Preisangaben)' ],
		[ 'ort' => 'Dortmund', 'slug' => 'dortmund', 'lat' => 51.5076, 'lng' => 7.4726, 'team_net' => 40, 'xmas_net' => 90.83, 'existing_xmas' => 'Dortmund', 'basis' => '40 €/Std. je Box, Nachbarregion (Wuppertal, Mülheim an der Ruhr, Münster)' ],
		[ 'ort' => 'Dresden', 'slug' => 'dresden', 'lat' => 51.0498, 'lng' => 13.7467, 'team_net' => 39, 'xmas_net' => 89.83, 'existing_xmas' => 'Dresden', 'basis' => '39 €/Std. je Box, Nachbarregion (Chemnitz, Berlin, Berlin)' ],
		[ 'ort' => 'Bielefeld', 'slug' => 'bielefeld', 'lat' => 52.0174, 'lng' => 8.5437, 'team_net' => 40, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Nachbarregion (Münster, Wuppertal, Mülheim an der Ruhr)' ],
		[ 'ort' => 'Bonn', 'slug' => 'bonn', 'lat' => 50.7198, 'lng' => 7.1123, 'team_net' => 35, 'xmas_net' => 85.83, 'existing_xmas' => 'Bonn', 'basis' => '35 €/Std. je Box, Nachbarregion (Bonn, Hürth, Wuppertal)' ],
		[ 'ort' => 'Stuttgart', 'slug' => 'stuttgart', 'lat' => 48.7702, 'lng' => 9.1895, 'team_net' => 40, 'xmas_net' => 90.83, 'existing_xmas' => 'Stuttgart', 'basis' => '40 €/Std. je Box, eigene Region (1 Preisangaben)' ],
		[ 'ort' => 'Saarbrücken', 'slug' => 'saarbruecken', 'lat' => 49.2354, 'lng' => 6.9932, 'team_net' => 55, 'xmas_net' => 105.83, 'existing_xmas' => '', 'basis' => '55 €/Std. je Box, Nachbarregion (Karlsdorf-Neuthard, Mannheim, Kronberg)' ],
		[ 'ort' => 'Chemnitz', 'slug' => 'chemnitz', 'lat' => 50.8207, 'lng' => 12.9105, 'team_net' => 40, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, eigene Region (1 Preisangaben)' ],
		[ 'ort' => 'Braunschweig', 'slug' => 'braunschweig', 'lat' => 52.2681, 'lng' => 10.5259, 'team_net' => 40.0, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Bundesmedian' ],
		[ 'ort' => 'Magdeburg', 'slug' => 'magdeburg', 'lat' => 52.127, 'lng' => 11.6177, 'team_net' => 40.0, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Bundesmedian' ],
		[ 'ort' => 'Karlsruhe', 'slug' => 'karlsruhe', 'lat' => 49.0061, 'lng' => 8.397, 'team_net' => 50, 'xmas_net' => 100.83, 'existing_xmas' => 'Karlsruhe', 'basis' => '50 €/Std. je Box, eigene Region (1 Preisangaben)' ],
		[ 'ort' => 'Mannheim', 'slug' => 'mannheim', 'lat' => 49.4963, 'lng' => 8.4812, 'team_net' => 55, 'xmas_net' => 105.83, 'existing_xmas' => 'Mannheim', 'basis' => '55 €/Std. je Box, eigene Region (1 Preisangaben)' ],
		[ 'ort' => 'Lübeck', 'slug' => 'luebeck', 'lat' => 53.871, 'lng' => 10.7033, 'team_net' => 49, 'xmas_net' => 99.83, 'existing_xmas' => 'Lübeck', 'basis' => '49 €/Std. je Box, Nachbarregion (Hamburg, Hamburg, Hamburg)' ],
		[ 'ort' => 'Erfurt', 'slug' => 'erfurt', 'lat' => 50.9881, 'lng' => 11.0275, 'team_net' => 40.0, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Bundesmedian' ],
		[ 'ort' => 'Freiburg im Breisgau', 'slug' => 'freiburg-im-breisgau', 'lat' => 47.9991, 'lng' => 7.8394, 'team_net' => 40.0, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Bundesmedian' ],
		[ 'ort' => 'Kassel', 'slug' => 'kassel', 'lat' => 51.3158, 'lng' => 9.4767, 'team_net' => 40.0, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Bundesmedian' ],
		[ 'ort' => 'Mainz', 'slug' => 'mainz', 'lat' => 49.9926, 'lng' => 8.2489, 'team_net' => 55, 'xmas_net' => 105.83, 'existing_xmas' => '', 'basis' => '55 €/Std. je Box, Nachbarregion (Kronberg, Mannheim, Karlsdorf-Neuthard)' ],
		[ 'ort' => 'Wetzlar', 'slug' => 'wetzlar', 'lat' => 50.5512, 'lng' => 8.503, 'team_net' => 55, 'xmas_net' => 105.83, 'existing_xmas' => '', 'basis' => '55 €/Std. je Box, Nachbarregion (Kronberg, Bonn, Mannheim)' ],
		[ 'ort' => 'Osnabrück', 'slug' => 'osnabrueck', 'lat' => 52.2706, 'lng' => 8.037, 'team_net' => 64, 'xmas_net' => 114.83, 'existing_xmas' => '', 'basis' => '64 €/Std. je Box, eigene Region (1 Preisangaben)' ],
		[ 'ort' => 'Rostock', 'slug' => 'rostock', 'lat' => 54.1104, 'lng' => 12.1161, 'team_net' => 40.0, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Bundesmedian' ],
		[ 'ort' => 'Heidelberg', 'slug' => 'heidelberg', 'lat' => 49.4074, 'lng' => 8.6913, 'team_net' => 55, 'xmas_net' => 105.83, 'existing_xmas' => '', 'basis' => '55 €/Std. je Box, Nachbarregion (Mannheim, Karlsdorf-Neuthard, Kronberg)' ],
		[ 'ort' => 'Göttingen', 'slug' => 'goettingen', 'lat' => 51.5373, 'lng' => 9.9319, 'team_net' => 40.0, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Bundesmedian' ],
		[ 'ort' => 'Heilbronn', 'slug' => 'heilbronn', 'lat' => 49.1419, 'lng' => 9.2052, 'team_net' => 50, 'xmas_net' => 100.83, 'existing_xmas' => '', 'basis' => '50 €/Std. je Box, Nachbarregion (Karlsdorf-Neuthard, Ebersbach an der Fils, Mannheim)' ],
		[ 'ort' => 'Wilhelmshaven', 'slug' => 'wilhelmshaven', 'lat' => 53.5442, 'lng' => 8.1134, 'team_net' => 46, 'xmas_net' => 96.83, 'existing_xmas' => '', 'basis' => '46 €/Std. je Box, Nachbarregion (Schenefeld, Hamburg, Hamburg)' ],
		[ 'ort' => 'Aalen', 'slug' => 'aalen', 'lat' => 48.8387, 'lng' => 10.0926, 'team_net' => 40, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Nachbarregion (Ebersbach an der Fils, Nürnberg, Karlsdorf-Neuthard)' ],
		[ 'ort' => 'Villingen-Schwenningen', 'slug' => 'villingen-schwenningen', 'lat' => 48.0667, 'lng' => 8.45, 'team_net' => 50, 'xmas_net' => 100.83, 'existing_xmas' => '', 'basis' => '50 €/Std. je Box, Nachbarregion (Ebersbach an der Fils, Karlsdorf-Neuthard, Mannheim)' ],
		[ 'ort' => 'Ingolstadt', 'slug' => 'ingolstadt', 'lat' => 48.7581, 'lng' => 11.4241, 'team_net' => 40, 'xmas_net' => 90.83, 'existing_xmas' => 'Ingolstadt', 'basis' => '40 €/Std. je Box, Nachbarregion (Nürnberg, Hohenbrunn, Brunnthal)' ],
		[ 'ort' => 'Dessau', 'slug' => 'dessau', 'lat' => 51.8262, 'lng' => 12.238, 'team_net' => 36, 'xmas_net' => 86.83, 'existing_xmas' => '', 'basis' => '36 €/Std. je Box, Nachbarregion (Berlin, Berlin, Berlin)' ],
		[ 'ort' => 'Lüdenscheid', 'slug' => 'luedenscheid', 'lat' => 51.2234, 'lng' => 7.6278, 'team_net' => 36, 'xmas_net' => 86.83, 'existing_xmas' => '', 'basis' => '36 €/Std. je Box, Nachbarregion (Wuppertal, Mülheim an der Ruhr, Düsseldorf)' ],
		[ 'ort' => 'Paderborn', 'slug' => 'paderborn', 'lat' => 51.7296, 'lng' => 8.7372, 'team_net' => 40, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Nachbarregion (Münster, Wuppertal, Mülheim an der Ruhr)' ],
		[ 'ort' => 'Ulm', 'slug' => 'ulm', 'lat' => 48.3981, 'lng' => 9.9701, 'team_net' => 40, 'xmas_net' => 90.83, 'existing_xmas' => 'Ulm', 'basis' => '40 €/Std. je Box, Nachbarregion (Ebersbach an der Fils, Brunnthal, Karlsdorf-Neuthard)' ],
		[ 'ort' => 'Bayreuth', 'slug' => 'bayreuth', 'lat' => 49.9462, 'lng' => 11.5783, 'team_net' => 35, 'xmas_net' => 85.83, 'existing_xmas' => '', 'basis' => '35 €/Std. je Box, eigene Region (1 Preisangaben)' ],
		[ 'ort' => 'Bamberg', 'slug' => 'bamberg', 'lat' => 49.8925, 'lng' => 10.897, 'team_net' => 35, 'xmas_net' => 85.83, 'existing_xmas' => '', 'basis' => '35 €/Std. je Box, Nachbarregion (Nürnberg, Neustadt an der Waldnaab, Ebersbach an der Fils)' ],
		[ 'ort' => 'Tübingen', 'slug' => 'tuebingen', 'lat' => 48.5272, 'lng' => 9.05, 'team_net' => 50, 'xmas_net' => 100.83, 'existing_xmas' => '', 'basis' => '50 €/Std. je Box, Nachbarregion (Ebersbach an der Fils, Karlsdorf-Neuthard, Mannheim)' ],
		[ 'ort' => 'Plauen', 'slug' => 'plauen', 'lat' => 50.4976, 'lng' => 12.1425, 'team_net' => 35, 'xmas_net' => 85.83, 'existing_xmas' => '', 'basis' => '35 €/Std. je Box, Nachbarregion (Chemnitz, Neustadt an der Waldnaab, Nürnberg)' ],
		[ 'ort' => 'Schweinfurt', 'slug' => 'schweinfurt', 'lat' => 50.0459, 'lng' => 10.2276, 'team_net' => 55, 'xmas_net' => 105.83, 'existing_xmas' => '', 'basis' => '55 €/Std. je Box, Nachbarregion (Nürnberg, Kronberg, Mannheim)' ],
		[ 'ort' => 'Konstanz', 'slug' => 'konstanz', 'lat' => 47.6687, 'lng' => 9.1767, 'team_net' => 40, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Nachbarregion (Ebersbach an der Fils, Karlsdorf-Neuthard, Brunnthal)' ],
		[ 'ort' => 'Fulda', 'slug' => 'fulda', 'lat' => 50.5541, 'lng' => 9.6753, 'team_net' => 55, 'xmas_net' => 105.83, 'existing_xmas' => '', 'basis' => '55 €/Std. je Box, Nachbarregion (Kronberg, Mannheim, Nürnberg)' ],
		[ 'ort' => 'Passau', 'slug' => 'passau', 'lat' => 48.5772, 'lng' => 13.42, 'team_net' => 29, 'xmas_net' => 79.83, 'existing_xmas' => '', 'basis' => '29 €/Std. je Box, eigene Region (1 Preisangaben)' ],
		[ 'ort' => 'Kempten', 'slug' => 'kempten', 'lat' => 47.7197, 'lng' => 10.3136, 'team_net' => 40, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Nachbarregion (Brunnthal, Hohenbrunn, Ebersbach an der Fils)' ],
		[ 'ort' => 'Emden', 'slug' => 'emden', 'lat' => 53.3632, 'lng' => 7.2127, 'team_net' => 40.0, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Bundesmedian' ],
		[ 'ort' => 'Greifswald', 'slug' => 'greifswald', 'lat' => 54.0871, 'lng' => 13.4133, 'team_net' => 40.0, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Bundesmedian' ],
		[ 'ort' => 'Rosenheim', 'slug' => 'rosenheim', 'lat' => 48.5703, 'lng' => 11.047, 'team_net' => 50, 'xmas_net' => 100.83, 'existing_xmas' => 'Rosenheim', 'basis' => '50 €/Std. je Box, eigene Region (1 Preisangaben)' ],
		[ 'ort' => 'Nordhausen', 'slug' => 'nordhausen', 'lat' => 51.5018, 'lng' => 10.7957, 'team_net' => 40.0, 'xmas_net' => 90.83, 'existing_xmas' => '', 'basis' => '40 €/Std. je Box, Bundesmedian' ],
	];
}

function fge_seed_indoor_2026_10_texts(): array {
	return [
		'team' => [
			'desc'     => 'Golf fürs ganze Team, wetterunabhängig am Simulator: eure eigenen Boxen in einer Indoor-Golf-Lounge, Spielformate wie Longest Drive und Nearest to the Pin mit Live-Leaderboard. Ohne Vorkenntnisse. Einweisung durch einen Pro, Getränke und Essen buchen wir auf Wunsch dazu. Die passende Lounge in der Region suchen wir für euch aus.',
			'dayflow'  => "Ankommen\nBegrüßung in der Indoor-Golf-Lounge, die Teams verteilen sich auf die Boxen.\n\nAufwärmen an der Box\nErste Schläge mit Leihschlägern, auf Wunsch mit kurzer Einweisung durch einen Pro.\n\nSpielformate im Team\nNearest to the Pin, Longest Drive und eine Runde auf einem berühmten Platz, mit Live-Leaderboard über alle Boxen.\n\nSiegerehrung\nAuswertung für die besten Teams, danach Zeit für Getränke und Snacks.",
			'includes' => "Eigene Simulatorbox, bis 6 Personen pro Box\nLeihschläger & Bälle\nSpielformate mit Live-Leaderboard\nOrganisation & ein Ansprechpartner",
			'addons'   => "Einweisung durch einen Pro\nGetränke & Snacks\nEssen oder Buffet\nTurniermodus mit Preisen\nFotograf\nShuttle-Service",
		],
		'xmas' => [
			'desc'     => 'Bewegung, Location und Feier an einem Abend: Golf-Challenge am Simulator mit Spielformaten wie Nearest to the Pin, Longest Drive und Putt-Bierpong, danach das gemeinsame Weihnachtsessen. Warm, wetterfest und ohne Vorkenntnisse. Die passende Indoor-Golf-Lounge in der Region suchen wir für euch aus.',
			'dayflow'  => "Ankommen in der Lounge\nGlühwein oder Punsch zur Begrüßung, kurze Einweisung an den Boxen, die Teams werden gelost.\n\nWarm werden an der Box\nErste Schläge mit Betreuung, Schläger werden gestellt. Wer noch nie gespielt hat, trifft nach zehn Minuten den Screen.\n\nSpielformate im Team\nNearest to the Pin, Longest Drive und Putt-Bierpong im Wechsel, mit Live-Leaderboard über alle Boxen.\n\nSiegerehrung\nAuswertung mit Preisen für die besten Teams.\n\nWeihnachtsessen\nBuffet oder Fingerfood zum Ausklang, auf Wunsch mit Getränkepauschale.",
			'includes' => "Glühwein-Empfang\nSimulatorboxen in einer Indoor-Golf-Lounge (ca. 4 Std.)\nEinweisung durch einen Pro\nLeihschläger & Bälle\nSpielformate mit Live-Leaderboard\nSiegerehrung & Preise\nWeihnachtsessen (Buffet oder Fingerfood)\nGetränke\nOrganisation & ein Ansprechpartner",
			'addons'   => "Getränkepauschale bis zum Schluss\nLive-Musik oder DJ\nFotograf\nGebrandete Preise\nShuttle-Service",
		],
	];
}

/** Ein Platzhalter-Event anlegen, falls der Slug noch frei ist. Liefert die neue ID oder 0. */
function fge_seed_indoor_2026_10_create( string $kind, array $row ): int {
	$t     = fge_seed_indoor_2026_10_texts()[ $kind ];
	$team  = 'team' === $kind;
	$name  = ( $team ? 'indoor-team-golf-in-' : 'indoor-golf-weihnachtsfeier-in-' ) . $row['slug'];
	if ( get_page_by_path( $name, OBJECT, 'firmengolf_event' ) ) {
		return 0;
	}
	$id = wp_insert_post( [
		'post_type'   => 'firmengolf_event',
		'post_status' => 'publish',
		'post_title'  => ( $team ? 'Indoor Team Golf in ' : 'Indoor Golf Weihnachtsfeier in ' ) . $row['ort'],
		'post_name'   => $name,
	] );
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	$meta = [
		'_fge_event_type'       => $team ? 'indoor-golf' : 'weihnachtsfeier',
		'_fge_event_status'     => 'freigegeben',
		'_fge_provider_type'    => 'firmengolf',
		'_fge_event_location'   => 'Indoor-Golf-Lounge im Raum ' . $row['ort'],
		'_fge_region'           => $row['ort'],
		'_fge_city'             => $row['ort'],
		'_fge_participants_min' => '6',
		'_fge_participants_max' => '40',
		'_fge_duration'         => $team ? 'ab 2 Std.' : 'Abend · ca. 4 Std.',
		'_fge_card_description' => $t['desc'],
		'_fge_price_mode'       => 'gesamt',
		'_fge_price_basis'      => $team ? 'box' : 'person',
		'_fge_box_persons'      => $team ? '6' : '',
		'_fge_box_hours'        => $team ? '2' : '',
		'_fge_price_amount'     => (string) ( $team ? $row['team_net'] : $row['xmas_net'] ),
		'_fge_event_dayflow'    => $t['dayflow'],
		'_fge_event_includes'   => $t['includes'],
		'_fge_event_addons'     => $t['addons'],
		'_fge_geo_lat'          => (string) $row['lat'],
		'_fge_geo_lng'          => (string) $row['lng'],
		'_fge_review_note'      => 'Platzhalter Indoor 06.10.2026, Preisbasis: ' . $row['basis'],
	];
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	if ( function_exists( 'fge_event_price_label' ) ) {
		$pr = fge_event_pricing( (int) $id );
		update_post_meta( $id, '_fge_sale_price_net', $pr['gross'] );
		update_post_meta( $id, '_fge_public_price_label', fge_event_price_label( (int) $id ) );
	}
	return (int) $id;
}

add_action( 'init', static function (): void {
	if ( get_option( 'fge_seed_indoor_2026_10' ) || ! function_exists( 'fge_event_pricing' ) ) {
		return;
	}
	update_option( 'fge_seed_indoor_2026_10', [ 'running' => time() ], false );
	$created = [];
	$repriced = [];
	foreach ( fge_seed_indoor_2026_10_rows() as $row ) {
		$id = fge_seed_indoor_2026_10_create( 'team', $row );
		if ( $id ) {
			$created[] = $id;
		}
		if ( '' === $row['existing_xmas'] ) {
			$id = fge_seed_indoor_2026_10_create( 'xmas', $row );
			if ( $id ) {
				$created[] = $id;
			}
			continue;
		}
		// Bestehender Platzhalter „Weihnachtsfeier mit Golf in …“ (ohne Partner): nur Preis anpassen.
		foreach ( get_posts( [ 'post_type' => 'firmengolf_event', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_fge_event_type', 'meta_value' => 'weihnachtsfeier' ] ) as $p ) {
			if ( 0 !== strpos( $p->post_name, 'weihnachtsfeier-mit-golf-in-' ) || (int) get_post_meta( $p->ID, '_fge_assigned_partner_id', true ) > 0 ) {
				continue;
			}
			if ( (string) get_post_meta( $p->ID, '_fge_city', true ) !== $row['existing_xmas'] ) {
				continue;
			}
			$repriced[ $p->ID ] = (string) get_post_meta( $p->ID, '_fge_price_amount', true );
			update_post_meta( $p->ID, '_fge_price_amount', (string) $row['xmas_net'] );
			$pr = fge_event_pricing( (int) $p->ID );
			update_post_meta( $p->ID, '_fge_sale_price_net', $pr['gross'] );
			update_post_meta( $p->ID, '_fge_public_price_label', fge_event_price_label( (int) $p->ID ) );
		}
	}
	update_option( 'fge_seed_indoor_2026_10', [ 'done' => time(), 'created' => $created, 'repriced_old' => $repriced ], false );
	if ( function_exists( 'delete_transient' ) ) {
		delete_transient( 'fge_formats_in_use' );
	}
}, 31 );
