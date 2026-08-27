<?php
/**
 * Zentrale Kataloge (Single Source of Truth) für das Partner-/Event-Modell.
 *
 * Abgeleitet aus dem Design-Handoff rev. 2 (partner-onboarding/Steps.jsx).
 * Genutzt von: Onboarding, Partner-Portal, Admin-Metaboxen, öffentliche Eignungsprüfung.
 * Alle id-Werte sind stabil (Speicherwerte); Labels sind frei änderbar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Partner-Typ (drei Zielgruppen seit 28.08.2026, docs/onboarding-golflehrer-indoor.md).
 * Gespeichert als _fge_partner_type; Bestand ohne Meta gilt als 'course'
 * (fge_partner_type() liefert den Default, Migration: wp firmengolf migrate-partner-type).
 * @return array<string,string> id => Label
 */
function fge_catalog_partner_types(): array {
	return [
		'course' => 'Golfplatz',
		'coach'  => 'Golflehrer',
		'indoor' => 'Indoor-Anlage',
	];
}

/**
 * Golf-Angebot / Platztyp (Single-Select, erste Onboarding-Frage).
 * @return array<string,string> id => Label
 */
function fge_catalog_golf_types(): array {
	return [
		'course-18'  => '18-Loch-Platz',
		'course-27'  => '27-Loch-Platz',
		'leading'    => 'Leading Course',
		'links'      => 'Links-Platz',
		'course-9'   => '9-Loch-Platz',
		'indoor-sim' => 'Indoor-Simulator',
		'range'      => 'Driving-Range',
		'short'      => 'Kurzplatz',
		'pitch-putt' => 'Pitch & Putt',
		'mini-golf'  => 'Mini-Golf',
	];
}

/**
 * Infrastruktur-Katalog — Gruppen mit Mehrfachauswahl (inkl. Gastronomie als eigene Gruppe).
 * @return array<string,array<string,string>> Gruppenname => [ id => Label ]
 */
function fge_catalog_infra_groups(): array {
	return [
		'Auf dem Platz' => [
			'course-18'       => '18-Loch-Platz',
			'course-9'        => '9-Loch-Platz',
			'abc-platz'       => 'A-B-C Platz',
			'short-course'    => 'Kurzplatz',
			'driving-range'   => 'Driving Range',
			'range-covered'   => 'Überdachte Driving Range',
			'range-heated'    => 'Beheizte Abschlagplätze',
			'range-flood'     => 'Flutlicht Range',
			'trackman'        => 'TrackMan Range',
			'toptracer'       => 'Toptracer Range',
			'short-game'      => 'Kurzspielbereich',
			'practice-bunker' => 'Übungsbunker',
			'indoor'          => 'Indoor Simulator',
			'barrierefrei'    => 'Barrierearme Anlage',
		],
		'Im Clubhaus' => [
			'meeting-room' => 'Meetingraum',
			'seminar'      => 'Seminarraum',
			'conference'   => 'Konferenzraum',
			'workshop'     => 'Workshopraum',
			'eventroom'    => 'Eventraum',
			'golf-shop'    => 'Golf-Shop',
			'shower'       => 'Duschen & Umkleiden',
		],
		'Tagungstechnik' => [
			'beamer'        => 'Beamer',
			'screen'        => 'Bildschirm',
			'mic'           => 'Mikrofonanlage',
			'wifi'          => 'WLAN',
			'flipchart'     => 'Flipchart',
			'whiteboard'    => 'Whiteboard',
			'moderation'    => 'Moderationsmaterial',
			'catering-area' => 'Cateringfläche',
		],
		'Golfschule' => [
			'coach'           => 'Golflehrer',
			'trial-course'    => 'Schnupperkurs',
			'platzreife'      => 'Platzreifekurs',
			'company-course'  => 'Firmenkurs',
			'advanced-course' => 'Fortgeschrittenenkurs',
			'rental-clubs'    => 'Leihschläger',
			'range-balls'     => 'Range-Bälle',
		],
		'Gastronomie' => [
			'restaurant'      => 'Restaurant',
			'club-restaurant' => 'Clubrestaurant',
			'bistro'          => 'Bistro',
			'cafe'            => 'Café',
			'bar'             => 'Bar',
			'halfway'         => 'Halfway-Verpflegung',
			'terrace'         => 'Terrasse',
			'outdoor'         => 'Außenbereich',
			'lounge'          => 'Lounge Bereich',
			'catering'        => 'Catering',
			'breakfast'       => 'Frühstück',
			'lunch'           => 'Lunch',
			'dinner'          => 'Abendessen',
			'bbq'             => 'BBQ',
			'drinks-flat'     => 'Getränkepauschale',
			'coffee-break'    => 'Kaffeepause',
		],
	];
}

/**
 * Flache Liste aller gültigen Infrastruktur-ids (zum Validieren/Sanitizen).
 * @return string[]
 */
function fge_catalog_infra_ids(): array {
	$ids = [];
	foreach ( fge_catalog_infra_groups() as $items ) {
		$ids = array_merge( $ids, array_keys( $items ) );
	}
	return array_values( array_unique( $ids ) );
}

/** Label zu einer Infrastruktur-id (oder die id selbst, falls unbekannt). */
function fge_catalog_infra_label( string $id ): string {
	foreach ( fge_catalog_infra_groups() as $items ) {
		if ( isset( $items[ $id ] ) ) {
			return $items[ $id ];
		}
	}
	return $id;
}

/**
 * Bedingte Kapazitäts-Zeilen: nur abgefragt, wenn die zugehörige Infrastruktur gewählt ist.
 * min/max werden immer abgefragt (nicht hier gelistet).
 * @return array<int,array{key:string,infra:string,label:string,hint:string}>
 */
function fge_catalog_cap_rows(): array {
	return [
		[ 'key' => 'range',      'infra' => 'driving-range',  'label' => 'Kapazität Driving Range',          'hint' => 'Wie viele Abschlagplätze?' ],
		[ 'key' => 'indoor',     'infra' => 'indoor',         'label' => 'Kapazität Indoor Simulator',       'hint' => 'Personen gleichzeitig.' ],
		[ 'key' => 'meeting',    'infra' => 'meeting-room',   'label' => 'Kapazität Meetingraum',            'hint' => 'Sitzplätze.' ],
		[ 'key' => 'seminar',    'infra' => 'seminar',        'label' => 'Kapazität Seminarraum',            'hint' => 'Sitzplätze.' ],
		[ 'key' => 'conference', 'infra' => 'conference',     'label' => 'Kapazität Konferenzraum',          'hint' => 'Sitzplätze.' ],
		[ 'key' => 'workshop',   'infra' => 'workshop',       'label' => 'Kapazität Workshopraum',           'hint' => 'Sitzplätze.' ],
		[ 'key' => 'eventroom',  'infra' => 'eventroom',      'label' => 'Kapazität Eventraum',              'hint' => 'Personen.' ],
		[ 'key' => 'restaurant', 'infra' => 'restaurant',     'label' => 'Kapazität Restaurant',             'hint' => 'Sitzplätze.' ],
		[ 'key' => 'terrace',    'infra' => 'terrace',        'label' => 'Kapazität Terrasse',               'hint' => 'Sitzplätze draußen.' ],
		[ 'key' => 'outdoor',    'infra' => 'outdoor',        'label' => 'Kapazität Außenbereich',           'hint' => 'Personen.' ],
		[ 'key' => 'lounge',     'infra' => 'lounge',         'label' => 'Kapazität Lounge Bereich',         'hint' => 'Personen.' ],
		[ 'key' => 'trial',      'infra' => 'trial-course',   'label' => 'Max. Teilnehmer Schnupperkurs',    'hint' => 'Pro Kurs.' ],
		[ 'key' => 'platzreife', 'infra' => 'platzreife',     'label' => 'Max. Teilnehmer Platzreifekurs',   'hint' => 'Pro Kurs.' ],
		[ 'key' => 'company',    'infra' => 'company-course', 'label' => 'Max. Teilnehmer Firmenkurs',       'hint' => 'Pro Kurs.' ],
	];
}

/** Alle gültigen Kapazitäts-Schlüssel inkl. min/max. @return string[] */
function fge_catalog_cap_keys(): array {
	$keys = [ 'min', 'max' ];
	foreach ( fge_catalog_cap_rows() as $row ) {
		$keys[] = $row['key'];
	}
	return $keys;
}

/**
 * Anbietbare Veranstaltungstypen (Partner-Auswahl im Onboarding).
 * @return array<string,string> id => Label
 */
function fge_catalog_partner_formats(): array {
	return [
		'schnupperkurs'  => 'Schnupperkurs',
		'platzreife'     => 'Platzreife',
		'teamevent'      => 'Teamevent',
		'firmenturnier'  => 'Firmenturnier',
		'kundenevent'    => 'Kundenevent',
		'networking'     => 'Networking',
		'afterwork'      => 'After-Work Golf',
		'sommerfest'     => 'Sommerfest',
		// Key bleibt 'offsite' (gespeicherte Partner-Auswahlen!), Label folgt der
		// Workshop-Umstellung 2026-08.
		'offsite'        => 'Workshop & Incentive',
		'gesundheitstag' => 'Gesundheitstag',
		'charity'        => 'Charity-Event',
		'nacht-event'    => 'Nacht-Event',
	];
}

/**
 * Simulator-Systeme / Hersteller (Indoor-Detailblock + Formular B,
 * docs/onboarding-golflehrer-indoor.md B8). 'other' erlaubt Freitext.
 * @return array<string,string> id => Label
 */
function fge_catalog_indoor_systems(): array {
	return [
		'trackman'   => 'Trackman',
		'foresight'  => 'Foresight (GCQuad, GCHawk)',
		'uneekor'    => 'Uneekor',
		'fullswing'  => 'Full Swing',
		'skytrak'    => 'SkyTrak',
		'garmin'     => 'Garmin Approach',
		'toptracer'  => 'Toptracer',
		'xgolf'      => 'X-Golf',
		'golfzon'    => 'Golfzon',
		'trugolf'    => 'TruGolf',
		'other'      => 'Anderes System',
	];
}

/**
 * Event-relevante Software-Features der Simulatoren (gekürzter Katalog für den
 * Indoor-Detailblock bei Bestandspartnern; Formular B nutzt denselben).
 * Das Live-Leaderboard ist das zentrale Firmenevent-Feature.
 * @return array<string,string> id => Label
 */
function fge_catalog_indoor_features(): array {
	return [
		'turniermodus'  => 'Turniermodus (Scramble, Texas)',
		'leaderboard'   => 'Live-Leaderboard über alle Boxen',
		'longest-drive' => 'Longest Drive',
		'closest-pin'   => 'Closest to Pin',
		'zielschiessen' => 'Zielschießen',
		'mini-games'    => 'Mini-Games (Fußballgolf, Darts, Bowling)',
		'famous'        => 'Berühmte Plätze spielen',
		'multiplayer'   => 'Multiplayer je Box',
	];
}

/**
 * Anlagentyp Indoor (Formular B, Slide B2). 'club-indoor' ist der Sonderweg
 * aus Entscheidung 4: Indoor-Bereich eines bestehenden Golfclubs läuft über
 * dessen Partner-Profil, nicht als eigener Datensatz.
 * @return array<string,string> id => Label
 */
function fge_catalog_indoor_kinds(): array {
	return [
		'lounge'      => 'Indoor-Golfclub oder Simulator-Lounge',
		'club-indoor' => 'Indoor-Bereich eines bestehenden Golfclubs',
		'range'       => 'Indoor-Range mit Ballflug-Tracking',
		'bar'         => 'Golf-Bar oder Entertainment-Location',
		'mobile'      => 'Event-Location mit mobilem Simulator',
		'academy'     => 'Trainingszentrum oder Golfschule mit Simulator',
	];
}

/**
 * Räume, Flächen und Nebenaktivitäten einer Indoor-Anlage (Formular B, Slide B9).
 * @return array<string,array<string,string>> Gruppenname => [ id => Label ]
 */
function fge_catalog_indoor_infra_groups(): array {
	return [
		'Simulatorbereich' => [
			'sim-putting'   => 'Indoor-Puttinggrün',
			'sim-chipping'  => 'Chipping-Bereich',
			'sim-bunker'    => 'Übungsbunker indoor',
			'sim-lounge'    => 'Lounge am Simulator',
			'sim-screen'    => 'Präsentations-Screen',
		],
		'Aufenthalt' => [
			'stay-lounge'   => 'Loungebereich',
			'stay-bar'      => 'Bar',
			'stay-seats'    => 'Sitzbereich',
			'stay-terrace'  => 'Terrasse',
			'stay-smoking'  => 'Raucherbereich',
		],
		'Tagen und Arbeiten' => [
			'work-meeting'    => 'Meetingraum',
			'work-seminar'    => 'Seminarraum',
			'work-workshop'   => 'Workshopraum',
			'work-wifi'       => 'WLAN',
			'work-beamer'     => 'Beamer',
			'work-screen'     => 'Bildschirm',
			'work-flipchart'  => 'Flipchart',
			'work-whiteboard' => 'Whiteboard',
			'work-mic'        => 'Mikrofonanlage',
			'work-moderation' => 'Moderationsmaterial',
		],
		'Weitere Aktivitäten' => [
			'fun-dart'         => 'Dart',
			'fun-billard'      => 'Billard',
			'fun-shuffleboard' => 'Shuffleboard',
			'fun-kicker'       => 'Kicker',
			'fun-bowling'      => 'Bowling',
			'fun-kegeln'       => 'Kegeln',
			'fun-simracing'    => 'Sim-Racing',
			'fun-tischtennis'  => 'Tischtennis',
		],
		'Sonstiges' => [
			'misc-music'    => 'Musikanlage',
			'misc-playlist' => 'Eigene Playlist erlaubt',
			'misc-deko'     => 'Deko erlaubt',
		],
	];
}

/** Flache Liste aller Indoor-Raum-ids (zum Validieren). @return string[] */
function fge_catalog_indoor_infra_ids(): array {
	$ids = [];
	foreach ( fge_catalog_indoor_infra_groups() as $items ) {
		$ids = array_merge( $ids, array_keys( $items ) );
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Anbietbare Indoor-Formate (Formular B, Slide B13). Format
 * 'schlechtwetter-ersatz' existiert bewusst NICHT (Entscheidung 5).
 * @return array<string,string> id => Label
 */
function fge_catalog_indoor_formats(): array {
	return [
		'indoor-teamevent'    => 'Indoor-Teamevent',
		'weihnachtsfeier'     => 'Weihnachtsfeier und Jahresabschluss',
		'longest-drive'       => 'Longest Drive oder Closest to Pin Turnier',
		'sim-turnier'         => 'Simulator-Firmenturnier',
		'afterwork-indoor'    => 'After-Work Indoor Golf',
		'schnupper-indoor'    => 'Schnupperkurs Indoor',
		'platzreife-theorie'  => 'Platzreife-Theorie mit Indoor-Praxis',
		'kundenevent-indoor'  => 'Kundenevent und Netzwerkabend',
		'kickoff-workshop'    => 'Kick-off oder Workshop mit Golfteil',
		'gesundheitstag-indoor' => 'Gesundheitstag und Bewegungspause',
		'wintertraining'      => 'Wintertraining für Golfer im Team',
	];
}

/**
 * Wer meldet sich als Golflehrer an (Formular A, Slide A2).
 * @return array<string,string> id => Label
 */
function fge_catalog_coach_kinds(): array {
	return [
		'solo'     => 'Einzelner Golf-Pro oder Trainer',
		'school'   => 'Golfschule oder Pro-Team',
		'employed' => 'Trainer fest angestellt bei einem Club',
	];
}

/**
 * Qualifikationen für Golflehrer (Formular A, Slide A3). Abfrage ohne Gate,
 * ohne Lizenzprüfung (Entscheidung 6); 'other' erlaubt Freitext.
 * @return array<string,string> id => Label
 */
function fge_catalog_coach_quali(): array {
	return [
		'pga-a'    => 'PGA of Germany, Level A',
		'pga-b'    => 'PGA of Germany, Level B',
		'pga-c'    => 'PGA of Germany, Level C',
		'dgv-b'    => 'DGV-B-Trainer',
		'dgv-c'    => 'DGV-C-Trainer',
		'dosb'     => 'DOSB-Lizenz',
		'kids'     => 'Kindertrainer-Lizenz',
		'other'    => 'Sonstige Qualifikation',
	];
}

/**
 * Anbietbare Formate für Golflehrer (Formular A, Slide A8). Die Voraussetzung
 * steht im Label-Kontext des Wizards, nicht als Gate im Katalog.
 * @return array<string,string> id => Label
 */
function fge_catalog_coach_formats(): array {
	return [
		'schnupper-team'            => 'Schnupperkurs für Teams',
		'platzreife-kompakt'        => 'Platzreife kompakt (1 bis 2 Tage)',
		'platzreife-serie'          => 'Platzreife über mehrere Termine',
		'firmenkurs-fortgeschritten' => 'Firmenkurs für Fortgeschrittene',
		'einzeltraining'            => 'Einzeltraining und Coaching-Gutscheine',
		'turnierbegleitung'         => 'Turnierbegleitung, Pro am Abschlag',
		'station-longest-drive'     => 'Longest Drive oder Closest to Pin betreuen',
		'station-kurzspiel'         => 'Putting- und Kurzspiel-Station',
		'indoor-training'           => 'Training am Simulator',
		'theorie-regeln'            => 'Regel- und Etikette-Theorie',
		'golf-fitness'              => 'Golf-Fitness und Mobility-Einheit',
		'teambuilding-golf'         => 'Teambuilding-Format mit Golfanteil',
		'inhouse-golf'              => 'In-House Golf beim Unternehmen',
		'familientag'               => 'Kinder- und Familientag',
	];
}

/**
 * Rollen-Liste für Ansprechpartner (Handoff §1.2.1).
 * @return string[]
 */
function fge_catalog_contact_roles(): array {
	return [
		'Clubmanager', 'Geschäftsführer', 'Vorstand', 'Präsident', 'Schatzmeister',
		'Sekretariat', 'Rezeption', 'Mitgliederverwaltung', 'Buchhaltung', 'Head Pro',
		'Golfprofessional', 'Golflehrer', 'Golfschule', 'Sportwart', 'Spielleitung',
		'Turnierleitung', 'Marshal', 'Starter', 'Head Greenkeeper', 'Greenkeeper',
		'Course Manager', 'Gastronomiebetreiber', 'Restaurantleitung', 'Eventmanager',
		'Pro Shop Mitarbeiter', 'Caddiemaster', 'Cart Verantwortlicher', 'Jugendwart',
		'Captain', 'Mannschaftsführer', 'Sonstige',
	];
}
