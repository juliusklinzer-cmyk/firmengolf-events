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
		'teamevent'      => 'Teamevent',
		'firmenturnier'  => 'Firmenturnier',
		'kundenevent'    => 'Kundenevent',
		'networking'     => 'Networking',
		'afterwork'      => 'After-Work Golf',
		'sommerfest'     => 'Sommerfest',
		'offsite'        => 'Offsite & Incentive',
		'gesundheitstag' => 'Gesundheitstag',
		'charity'        => 'Charity-Event',
		'nacht-event'    => 'Nacht-Event',
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
