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
	// An der Top-50-Recherche ausgerichtet (Julius, 02.09.): TrackMan dominiert
	// mit ~44/50, dazu vereinzelt Garmin R50, Uneekor, Foresight, TruGolf.
	// Golfzon und Full Swing (0/50 Treffer) sind gestrichen; 'other' öffnet
	// weiterhin das Freitextfeld (fängt Bestand und Exoten ab).
	return [
		'trackman'   => 'TrackMan',
		'garmin-r50' => 'Garmin R50',
		'uneekor'    => 'Uneekor',
		'foresight'  => 'Foresight',
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
	// Nur die eventfähigsten Features (Julius, 28.08.).
	return [
		'closest-pin'   => 'Nearest to the Pin',
		'longest-drive' => 'Longest Drive',
		'mini-games'    => 'Minigames',
		'turniermodus'  => 'Turniermodus',
		'famous'        => 'Berühmte Plätze spielen',
		'leaderboard'   => 'Ergebnisanzeige (Leaderboard)',
	];
}

/**
 * Anlagentyp Indoor (Formular B, Slide B2). 'club-indoor' ist der Sonderweg
 * aus Entscheidung 4: Indoor-Bereich eines bestehenden Golfclubs läuft über
 * dessen Partner-Profil, nicht als eigener Datensatz.
 * @return array<string,string> id => Label
 */
function fge_catalog_indoor_kinds(): array {
	// Auf die zwei wesentlichen Pole gebrochen (Julius, 01.09.): professioneller
	// Ganzjahresbetrieb oder Simulator auf der Golfanlage. Entertainment-Anlagen
	// zählen zum eigenständigen Betrieb (lounge).
	return [
		'lounge' => 'Indoor Golf Lounge oder Studio',
		'club'   => 'Simulator auf einem Golfplatz',
	];
}

/** Erklärzeilen zu den Anlagentypen (Kachel-Untertitel). @return array<string,string> */
function fge_catalog_indoor_kind_subs(): array {
	return [
		'lounge' => 'Eigenständiger, ganzjährig geöffneter Indoor-Golf-Betrieb, oft mit Bar, Gastronomie oder weiteren Aktivitäten.',
		'club'   => 'Ein bis zwei Simulatoren auf der Golfanlage, vor allem von Mitgliedern genutzt.',
	];
}

/**
 * Räume, Flächen und Nebenaktivitäten einer Indoor-Anlage (Formular B, Slide B9).
 * @return array<string,array<string,string>> Gruppenname => [ id => Label ]
 */
function fge_catalog_indoor_infra_groups(): array {
	// Struktur Julius, 28.08.: Spielmöglichkeiten (inkl. Minigolf), Ausstattung
	// (Leihschläger lebt HIER, nicht bei der Technik), Tagen und Arbeiten,
	// weitere Aktivitäten. Gastronomie hat ihre EIGENE Slide (keine Doppelung).
	return [
		'Spielmöglichkeiten' => [
			'sim-boxes'    => 'Simulatorboxen',
			'sim-putting'  => 'Indoor Putting-Grün',
			'sim-chipping' => 'Kurzspielbereich',
			'sim-bunker'   => 'Übungsbunker Indoor',
			'fun-minigolf' => 'Minigolf',
		],
		'Ausstattung' => [
			'rental-clubs' => 'Leihschläger',
			'lockers'      => 'Umkleide und Schließfächer',
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
	// Bewusst kurz (Julius, 28.08.): nur Platzhalter-Formate, alles Weitere
	// legt der Partner später selbst im Portal an.
	return [
		'platzreife-indoor'    => 'Indoor Platzreife',
		'sim-turnier'          => 'Simulator Firmenturnier',
		'afterwork-indoor'     => 'After Work Indoor Golf',
		'grundlagen-indoor'    => 'Grundlagenkurs Indoor',
		'workshop-indoor'      => 'Tagung und Workshop',
		'weihnachtsfeier-indoor' => 'Weihnachtsfeier Indoor',
	];
}

/**
 * Gastronomie-Kacheln fuer Indoor-Locations (Top-50-Recherche, Julius 02.09.):
 * die Golfplatz-Liste (Clubrestaurant, Halfway, BBQ) passte nicht zu
 * Simulatoren. Realitaet dort: Bar 21/50, Getraenke 27, Snacks 12, warme
 * Speisen 12, Kaffee 5, Catering-Partner 4, oft Mitbringen erlaubt.
 * @return array<string,string> id => Label
 */
function fge_catalog_indoor_gastro(): array {
	return [
		'bar'              => 'Bar',
		'drinks'           => 'Getränkeauswahl',
		'snacks'           => 'Snacks',
		'hot-food'         => 'Warme Speisen',
		'coffee'           => 'Kaffee',
		'catering-partner' => 'Catering über Partner möglich',
		'byo'              => 'Eigene Speisen und Getränke erlaubt',
	];
}

/**
 * Betreuung waehrend der Buchung (Top-50: 18 betreut, ~9 Self-Service/Code).
 * Fuer Firmenevents entscheidend, deshalb Pflichtfeld bei der Technik.
 * @return array<string,string> id => Label
 */
function fge_catalog_indoor_staffing(): array {
	return [
		'betreut' => 'Betreut, Team vor Ort',
		'self'    => 'Self-Service mit Code-Zugang',
		'mixed'   => 'Gemischt, je nach Uhrzeit',
	];
}

/**
 * Wer meldet sich als Golflehrer an (Labels Julius, 28.08.2026).
 * Relevanz vor allem für die Rechnungsstellung: bei 'employed' ist der
 * Rechnungssteller der Golfclub, nicht der Pro selbst (Backend fragt den
 * Rechnungssteller dann als Golfplatz ab — noch offen).
 * @return array<string,string> id => Label
 */
function fge_catalog_coach_kinds(): array {
	// Auf zwei reduziert (Julius, 01.09.): steuert die Gruppengrößen-Logik und
	// später das Team (eine Schule darf mehrere Lehrer einladen, ein Einzel-Pro nicht).
	return [
		'solo'   => 'Einzelner Golflehrer',
		'school' => 'Golfschule mit mehreren Lehrern',
	];
}

/**
 * Ausbildung des Golflehrers (Liste Julius, 28.08.2026; Mehrfachauswahl).
 * Abfrage ohne Gate, ohne Lizenzprüfung (Entscheidung 6); 'other' öffnet Freitext.
 * @return array<string,string> id => Label
 */
function fge_catalog_coach_quali(): array {
	return [
		'pga-pro'       => 'PGA Golfprofessional',
		'pga-assistant' => 'PGA Assistant',
		'dosb-a'        => 'DOSB A-Trainer Golf',
		'dosb-b'        => 'DOSB B-Trainer Golf',
		'dosb-c'        => 'DOSB C-Trainer Golf',
		'pga-intl'      => 'Internationale PGA-Qualifikation',
		'other'         => 'Sonstige Qualifikation',
	];
}

/**
 * Was die Anlage dem Golflehrer für Kurse und größere Eventmodule bietet
 * (Julius, 28.08.: inkl. Kurzplatz, ids = Infra-/Golftyp-ids für die globalen
 * Icons in fge_onboarding_icon_map()).
 * @return array<string,string> id => Label
 */
function fge_catalog_coach_venue_use(): array {
	return [
		'driving-range'   => 'Driving Range',
		'short-game'      => 'Kurzspielbereich',
		'practice-bunker' => 'Übungsbunker',
		'short-course'    => 'Kurzplatz',
		'course-9'        => '9-Loch-Platz',
		'course-18'       => '18-Loch-Platz',
		'indoor'          => 'Indoor-Simulator',
		'seminar'         => 'Seminarraum',
		'restaurant'      => 'Gastronomie',
	];
}

/**
 * Anbietbare Formate für Golflehrer (Formular A, Slide A8). Die Voraussetzung
 * steht im Label-Kontext des Wizards, nicht als Gate im Katalog.
 * @return array<string,string> id => Label
 */
function fge_catalog_coach_formats(): array {
	// Aus echten Golflehrer-Angeboten abgeleitet (Recherche 50 Anlagen, 02.09.):
	// die tatsächlich verbreiteten Formate statt erfundener Sonderfälle. Keys der
	// Bestandsformate bleiben (gespeicherte Auswahlen). Wording „Grundlagenkurs"
	// statt „Schnupperkurs", „Golflehrer/Lehrer" statt „Pro".
	return [
		'schnupper-team'    => 'Grundlagenkurs für Teams',
		'platzreife-kompakt' => 'Platzreife',
		'gruppentraining'   => 'Gruppen- und Kleingruppentraining',
		'platztraining'     => 'Platztraining und Course Management',
		'teambuilding-golf' => 'Firmenevent und Teambuilding',
		'kurzplatz'         => 'Kurzplatz-Runde',
		'trackman-range'    => 'Training an der Trackman Range',
		'nacht-event'       => 'Flutlicht- und Nacht-Event',
		'schlaegerbau'      => 'Schlägerbau und Fitting',
		// Einzeltraining bleibt als Dienstleistung wählbar, ist aber kein
		// Firmen-Event (Julius, 02.09.).
		'einzeltraining'    => 'Einzeltraining und Coaching',
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
		'Course Manager', 'Gastronomiebetreiber', 'Gastronom', 'Restaurantleitung', 'Eventmanager',
		'Shuttleservice', 'Shuttle-Unternehmen', 'Technik', 'Golfplatz',
		'Pro Shop Mitarbeiter', 'Caddiemaster', 'Cart Verantwortlicher', 'Jugendwart',
		'Captain', 'Mannschaftsführer', 'Sonstige',
	];
}
