<?php
/**
 * Budget-Rechner — Preis-Konfiguration & Backend-Settings.
 *
 * Der Rechner auf der Individuelle-Events-Seite läuft client-seitig, bezieht
 * seine Preise aber aus einer WP-Option (fge_budget_calc), die hier im Backend
 * gepflegt wird. So bleibt der Rechner ohne Code-Änderung immer aktuell.
 *
 * Modell: Ein Event-Typ hat KEINEN Grundpreis, sondern nur eine Liste passender
 * Dienstleistungen. Jede Dienstleistung trägt den Preis (€/Person ODER Pauschale).
 * Platzkosten/Greenfee stecken in der jeweiligen Golf-Leistung (z. B. „18-Loch-
 * Turnier inkl. Greenfee"), es gibt keine separate Greenfee-Position.
 *
 * Editierbar im Admin: Typ-Labels, Service-Labels + Preisstaffeln (€/Person ODER
 * Pauschale, je Niveau €/€€/€€€), Rundung.
 * Fix (aus Defaults): IDs, Typ→Service-Zuordnung (services/default_on/required),
 * Kategorie-Zuordnung, Icons, Kategorie-Farben, Wizard-Mapping (wiz).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_BC_OPTION = 'fge_budget_calc';

/**
 * Werkseinstellungen — Quelle der Wahrheit für Struktur + Start-Preise.
 *
 * Preise seit 07.09.2026 je Preisniveau als Staffel [€, €€, €€€], Werte aus der
 * Marktrecherche (mind. 5 Angebote je Leistung, netto + 20 %, Quartile):
 * docs/budget-rechner-preisrecherche-2026-09.md. zero_label: Text in der
 * Aufschlüsselung, wenn eine Leistung ohne Preis gewählt ist (inklusive / auf Anfrage).
 */
function fge_bc_defaults(): array {
	// Service-Katalog — in grober Tagesablauf-Reihenfolge.
	// cat (Donut-Kategorie), icon, pp (€/Person je Niveau) ODER flat (Pauschale je Niveau), wiz (Anfrage-Mapping).
	$services = [
		// Anreise.
		[ 'id' => 'shuttle',       'label' => 'Transport & Shuttle (Bus, Hin- und Rückfahrt)',                    'cat' => 'transport','icon' => 'bus',  'pp' => [ 0, 0, 0 ],    'flat' => [ 450, 550, 800 ],          'wiz' => 'Shuttle / Transport' ],
		[ 'id' => 'vip_shuttle',   'label' => 'VIP-Shuttle (Limousine oder Sprinter, Halbtag)',                            'cat' => 'transport','icon' => 'star', 'pp' => [ 0, 0, 0 ],       'flat' => [ 350, 400, 750 ], 'wiz' => 'Shuttle / Transport' ],
		// Golf-Leistung (Platznutzung/Greenfee inkludiert).
		[ 'id' => 'golfkurs',      'label' => 'Golfkurs (inkl. Platz, Golflehrer & Leihschläger)',      'cat' => 'programm', 'icon' => 'coaching', 'pp' => [ 30, 40, 50 ],   'flat' => [ 0, 0, 0 ], 'wiz' => 'Grundlagenkurs' ],
		[ 'id' => 'platzreife',    'label' => 'Platzreifekurs (PGA-Pro, Regeln & Prüfung)',             'cat' => 'programm', 'icon' => 'coaching', 'pp' => [ 200, 250, 275 ],  'flat' => [ 0, 0, 0 ], 'wiz' => 'Platzreifekurs' ],
		// Turnierart: Entweder-oder-Gruppe (Julius, 07.09.), nur eine Art gleichzeitig wählbar.
		[ 'id' => 'turnier_kurz',  'label' => 'Kurzplatz-Turnier (für Nicht-Golfer)',      'cat' => 'venue', 'icon' => 'flag',   'pp' => [ 40, 50, 60 ],    'flat' => [ 0, 0, 0 ], 'wiz' => 'Kurzplatz-Turnier', 'group' => 'turnier' ],
		[ 'id' => 'turnier_9',     'label' => '9-Loch-Turnier',                          'cat' => 'venue', 'icon' => 'trophy', 'pp' => [ 40, 50, 70 ],   'flat' => [ 0, 0, 0 ], 'wiz' => '9-Loch-Turnier',    'group' => 'turnier' ],
		[ 'id' => 'turnier_18',    'label' => '18-Loch-Turnier',                         'cat' => 'venue', 'icon' => 'trophy', 'pp' => [ 70, 95, 100 ],  'flat' => [ 0, 0, 0 ], 'wiz' => '18-Loch-Turnier',   'group' => 'turnier' ],
		[ 'id' => 'turnier_kombi', 'label' => 'Kombi-Turnier (Golfer und Nicht-Golfer)', 'cat' => 'venue', 'icon' => 'trophy', 'pp' => [ 60, 70, 90 ],  'flat' => [ 0, 0, 0 ], 'wiz' => '18-Loch-Turnier',   'group' => 'turnier' ],
		[ 'id' => 'putting',       'label' => 'Putting-Turnier',                        'cat' => 'programm', 'icon' => 'target','pp' => [ 15, 25, 40 ],    'flat' => [ 0, 0, 0 ],          'wiz' => 'Putting-Challenge' ],
		[ 'id' => 'sonderwertung', 'label' => 'Sonderwertungen (Longest Drive, Nearest to Pin)', 'cat' => 'extras', 'icon' => 'target', 'pp' => [ 0, 0, 0 ], 'flat' => [ 0, 0, 0 ], 'wiz' => 'Long-Drive-Challenge', 'zero_label' => 'inklusive' ],
		[ 'id' => 'longest_drive', 'label' => 'Longest-Drive-Challenge (Radar-Modul mit Betreuung)',                'cat' => 'programm', 'icon' => 'target','pp' => [ 0, 0, 0 ],    'flat' => [ 700, 800, 950 ],          'wiz' => 'Long-Drive-Challenge' ],
		[ 'id' => 'nachtrunde',    'label' => 'Nacht-Runde (Kurzplatz / Range)',        'cat' => 'venue',    'icon' => 'flag', 'pp' => [ 30, 35, 40 ],   'flat' => [ 0, 0, 0 ],          'wiz' => 'Flutlicht und Nacht-Event' ],
		// Verpflegung (im Tagesverlauf).
		[ 'id' => 'startgeschenk', 'label' => 'Startgeschenk / Goodie-Bag',             'cat' => 'extras',   'icon' => 'gift', 'pp' => [ 10, 15, 25 ],    'flat' => [ 0, 0, 0 ],          'wiz' => 'Individuelle Artikel' ],
		[ 'id' => 'welcome_drink', 'label' => 'Welcome Drink',                          'cat' => 'catering', 'icon' => 'drink','pp' => [ 5, 10, 15 ],    'flat' => [ 0, 0, 0 ],          'wiz' => 'Bar & Drinks' ],
		[ 'id' => 'halfway',       'label' => 'Rundenverpflegung (Halfway)',            'cat' => 'catering', 'icon' => 'catering','pp' => [ 5, 10, 15 ], 'flat' => [ 0, 0, 0 ],          'wiz' => 'Mittagessen' ],
		[ 'id' => 'cominghome',    'label' => 'Coming Home (Imbiss nach der Runde)',    'cat' => 'catering', 'icon' => 'catering','pp' => [ 10, 15, 20 ], 'flat' => [ 0, 0, 0 ],          'wiz' => 'Mittagessen' ],
		[ 'id' => 'mittagessen',   'label' => 'Mittagessen',                            'cat' => 'catering', 'icon' => 'catering','pp' => [ 20, 25, 30 ], 'flat' => [ 0, 0, 0 ],          'wiz' => 'Mittagessen' ],
		[ 'id' => 'dinner',        'label' => 'Abendveranstaltung / Dinner',            'cat' => 'catering', 'icon' => 'catering','pp' => [ 35, 40, 50 ],'flat' => [ 0, 0, 0 ],          'wiz' => 'Abendessen' ],
		[ 'id' => 'getraenke',     'label' => 'Getränkepauschale',                      'cat' => 'catering', 'icon' => 'drink','pp' => [ 30, 35, 40 ],    'flat' => [ 0, 0, 0 ],          'wiz' => 'Bar & Drinks' ],
		[ 'id' => 'bar',           'label' => 'Bar & Drinks',                           'cat' => 'catering', 'icon' => 'drink','pp' => [ 30, 35, 45 ],    'flat' => [ 0, 0, 0 ],          'wiz' => 'Bar & Drinks' ],
		// Unterhaltung & Technik.
		[ 'id' => 'musik',         'label' => 'DJ oder Live-Band',                      'cat' => 'technik',  'icon' => 'music','pp' => [ 0, 0, 0 ],       'flat' => [ 800, 1100, 3000 ], 'wiz' => 'Musik und DJ' ],
		[ 'id' => 'technik',       'label' => 'Technik & Show (Ton, Licht, Bühne)',                         'cat' => 'technik',  'icon' => 'show', 'pp' => [ 0, 0, 0 ],  'flat' => [ 1000, 1500, 2200 ], 'wiz' => 'Bühne mit Licht und Ton' ],
		// Turnier-Extras.
		[ 'id' => 'siegerehrung',  'label' => 'Siegerehrung & Preise',                  'cat' => 'extras',   'icon' => 'trophy','pp' => [ 5, 20, 55 ],  'flat' => [ 0, 0, 0 ],   'wiz' => 'Pokale & Preise' ],
		[ 'id' => 'branding',      'label' => 'Branding (Abschläge, Banner, Merch)',    'cat' => 'extras',   'icon' => 'tag',  'pp' => [ 0, 0, 0 ],   'flat' => [ 600, 1400, 3000 ],   'wiz' => 'Branding & Banner' ],
		[ 'id' => 'turnierserie',  'label' => 'Turnier-Serie (mehrere Termine)',        'cat' => 'extras',   'icon' => 'calendar','pp' => [ 0, 0, 0 ],    'flat' => [ 0, 0, 0 ], 'wiz' => '9-Loch-Turnier', 'zero_label' => 'auf Anfrage' ],
		// Raum, Übernachtung, Content.
		[ 'id' => 'meetingraum',   'label' => 'Meetingraum (2 Stunden)',                'cat' => 'programm', 'icon' => 'room', 'pp' => [ 0, 0, 0 ],   'flat' => [ 50, 150, 300 ],          'wiz' => 'Meetingraum' ],
		// Golfreise / Offsite (Julius, 07.09., Vorbild Retreat beim Mitbewerber): Unterkunft je Nacht,
		// Golfkurs und Verpflegung je Tag, Verpflegung als Entweder-oder-Gruppe.
		[ 'id' => 'uebernachtung', 'label' => 'Unterkunft (Hotel, pro Nacht)',           'cat' => 'uebernachtung','icon' => 'bed','pp' => [ 70, 90, 115 ], 'flat' => [ 0, 0, 0 ],         'wiz' => 'Übernachtung', 'per' => 'night' ],
		[ 'id' => 'golfkurs_reise','label' => 'Golfkurs täglich (Pro, Platz, Leihschläger)', 'cat' => 'programm', 'icon' => 'coaching', 'pp' => [ 80, 105, 135 ], 'flat' => [ 0, 0, 0 ], 'wiz' => 'Grundlagenkurs', 'per' => 'day' ],
		[ 'id' => 'fruehstueck',   'label' => 'Frühstück',                              'cat' => 'catering', 'icon' => 'catering','pp' => [ 15, 20, 25 ],  'flat' => [ 0, 0, 0 ],          'wiz' => 'Frühstück',    'per' => 'day', 'group' => 'pension' ],
		[ 'id' => 'halbpension',   'label' => 'Halbpension',                            'cat' => 'catering', 'icon' => 'catering','pp' => [ 30, 35, 40 ],  'flat' => [ 0, 0, 0 ],          'wiz' => 'Abendessen',   'per' => 'day', 'group' => 'pension' ],
		[ 'id' => 'vollpension',   'label' => 'Vollpension',                            'cat' => 'catering', 'icon' => 'catering','pp' => [ 50, 55, 60 ], 'flat' => [ 0, 0, 0 ],          'wiz' => 'Mittagessen',  'per' => 'day', 'group' => 'pension' ],
		[ 'id' => 'transfer',      'label' => 'An- und Abreise (Bus-Transfer)',             'cat' => 'transport','icon' => 'bus',  'pp' => [ 0, 0, 0 ],    'flat' => [ 450, 550, 800 ],          'wiz' => 'Shuttle / Transport' ],
		[ 'id' => 'foto',          'label' => 'Fotograf und Videograf',                 'cat' => 'foto',     'icon' => 'cam',  'pp' => [ 0, 0, 0 ],       'flat' => [ 750, 1550, 3300 ], 'wiz' => 'Fotograf' ],
		// Nacht.
		[ 'id' => 'flutlicht',     'label' => 'Flutlicht / Einleuchten des Platzes',    'cat' => 'venue',    'icon' => 'bulb', 'pp' => [ 0, 0, 0 ],       'flat' => [ 850, 1400, 2750 ], 'wiz' => 'Flutlicht und Nacht-Event' ],
		[ 'id' => 'leuchtball',    'label' => 'Leucht-Equipment / Nacht-Bälle',         'cat' => 'programm', 'icon' => 'ball', 'pp' => [ 5, 10, 30 ],      'flat' => [ 0, 0, 0 ],          'wiz' => 'Flutlicht und Nacht-Event' ],
	];

	$all_ids = array_column( $services, 'id' );

	// Zusatzleistungen, die bei Teamevent und Platzreife anklickbar sind (Julius, 07.09.).
	$team_addons = [ 'shuttle', 'meetingraum', 'foto' ];
	// Firmenturnier und Kundenevent (Julius, 07.09.): Turnierart zuerst, dann die Turnier-Extras, exakt gleich.
	$turnier_set = [ 'turnier_kurz', 'turnier_9', 'turnier_18', 'turnier_kombi', 'halfway', 'cominghome', 'dinner', 'musik', 'technik', 'siegerehrung', 'sonderwertung', 'branding', 'turnierserie', 'foto' ];

	$types = [
		[
			'id' => 'teamevent', 'label' => 'Teamevent', 'wiz' => 'Golf-Teamevent',
			'services'   => array_merge( [ 'golfkurs', 'mittagessen', 'getraenke', 'putting', 'longest_drive' ], $team_addons ),
			'default_on' => [ 'golfkurs', 'mittagessen', 'getraenke', 'putting', 'longest_drive' ],
			'required'   => [],
		],
		[
			'id' => 'platzreife', 'label' => 'Platzreife', 'wiz' => 'Platzreife',
			'services'   => array_merge( [ 'platzreife', 'mittagessen', 'getraenke', 'putting', 'longest_drive' ], $team_addons ),
			'default_on' => [ 'platzreife' ],
			'required'   => [ 'platzreife' ],
		],
		[
			'id' => 'turnier', 'label' => 'Firmenturnier', 'wiz' => 'Firmen-Golfturnier',
			'services'   => $turnier_set,
			'default_on' => [ 'turnier_18' ],
			'required'   => [],
		],
		[
			'id' => 'kundenevent', 'label' => 'Kundenevent', 'wiz' => 'Golf-Kundenevent',
			'services'   => array_merge( [ 'vip_shuttle', 'golfkurs' ], $turnier_set ),
			'default_on' => [ 'turnier_18' ],
			'required'   => [],
		],
		[
			'id' => 'offsite', 'label' => 'Golfreise & Offsite', 'wiz' => 'Incentive-Reise', 'days' => true, 'start_days' => 3,
			'services'   => [ 'transfer', 'uebernachtung', 'golfkurs_reise', 'fruehstueck', 'halbpension', 'vollpension', 'meetingraum', 'turnier_18', 'foto' ],
			'default_on' => [ 'uebernachtung', 'golfkurs_reise', 'halbpension' ],
			'required'   => [],
		],
		[
			'id' => 'sommerfest', 'label' => 'Sommerfest', 'wiz' => 'Sommerfest',
			// Wie Firmenturnier (Turnierarten + Extras), Golfkurs als Option dazu (Julius, 07.09.).
			'services'   => array_merge( [ 'golfkurs' ], $turnier_set ),
			'default_on' => [ 'turnier_18' ],
			'required'   => [],
		],
		[
			'id' => 'nachtturnier', 'label' => 'Nachtturnier', 'wiz' => 'Firmen-Golfturnier',
			'services'   => [ 'flutlicht', 'shuttle', 'nachtrunde', 'leuchtball', 'welcome_drink', 'halfway', 'cominghome', 'dinner', 'siegerehrung', 'musik', 'bar', 'technik', 'foto' ],
			'default_on' => [ 'nachtrunde' ],
			'required'   => [ 'flutlicht' ],
		],
		[
			'id' => 'andere', 'label' => 'Andere', 'wiz' => 'Andere Events',
			'services'   => $all_ids,
			'default_on' => [],
			'required'   => [],
		],
	];

	return [
		'v'        => 2,
		'types'    => $types,
		'services' => $services,
		// Preisniveau: Index in die Staffeln pp/flat (kein Faktor mehr).
		'ranges'   => [
			[ 'id' => '€',   'idx' => 0, 'label' => 'Günstig' ],
			[ 'id' => '€€',  'idx' => 1, 'label' => 'Standard' ],
			[ 'id' => '€€€', 'idx' => 2, 'label' => 'Premium' ],
		],
		'cats' => [
			'venue'         => [ 'label' => 'Golfplatz & Greenfee', 'color' => '#4279D1' ],
			'programm'      => [ 'label' => 'Programm & Coaching',  'color' => '#00C896' ],
			'catering'      => [ 'label' => 'Catering',             'color' => '#C9B488' ],
			'uebernachtung' => [ 'label' => 'Übernachtung',         'color' => '#009E78' ],
			'transport'     => [ 'label' => 'Transport',            'color' => '#6C736E' ],
			'technik'       => [ 'label' => 'Technik & Show',       'color' => '#6E9BDD' ],
			'foto'          => [ 'label' => 'Foto & Content',       'color' => '#D8C9A6' ],
			'extras'        => [ 'label' => 'Extras & Branding',    'color' => '#C77D4A' ],
		],
		'round_to' => 50,
		'start'    => [ 'participants' => 30, 'type' => 'teamevent', 'range' => '€€' ],
	];
}

/** Drei Staffelwerte aus beliebiger Eingabe (Array oder Skalar) normalisieren. */
function fge_bc_tiers( $raw, array $fallback ): array {
	if ( ! is_array( $raw ) ) {
		return $fallback;
	}
	$out = $fallback;
	for ( $i = 0; $i < 3; $i++ ) {
		if ( isset( $raw[ $i ] ) && '' !== $raw[ $i ] && is_numeric( $raw[ $i ] ) ) {
			$out[ $i ] = max( 0, (float) $raw[ $i ] );
		}
	}
	return $out;
}

/**
 * Aktive Konfiguration: gespeicherte Preise auf die feste Default-Struktur
 * gelegt. Struktur (IDs, services/default_on/required, cat, icon, color, wiz)
 * stammt immer aus den Defaults, nur die editierbaren Felder (Labels, Staffeln,
 * Rundung) werden aus der Option übernommen. Alte Optionen (v1, Skalarpreis +
 * Faktor) werden ignoriert, damit die neuen Staffeln greifen.
 */
function fge_bc_config(): array {
	$def   = fge_bc_defaults();
	$saved = get_option( FGE_BC_OPTION, [] );
	if ( ! is_array( $saved ) || (int) ( $saved['v'] ?? 1 ) !== (int) $def['v'] ) {
		$saved = [];
	}

	$by_id = static function ( array $rows ) {
		$map = [];
		foreach ( $rows as $r ) {
			if ( isset( $r['id'] ) ) {
				$map[ $r['id'] ] = $r;
			}
		}
		return $map;
	};

	$saved_types = $by_id( $saved['types'] ?? [] );
	foreach ( $def['types'] as &$t ) {
		$s = $saved_types[ $t['id'] ] ?? [];
		if ( isset( $s['label'] ) && $s['label'] !== '' ) {
			$t['label'] = (string) $s['label'];
		}
	}
	unset( $t );

	$saved_svc = $by_id( $saved['services'] ?? [] );
	foreach ( $def['services'] as &$sv ) {
		$s = $saved_svc[ $sv['id'] ] ?? [];
		if ( isset( $s['label'] ) && $s['label'] !== '' ) {
			$sv['label'] = (string) $s['label'];
		}
		$sv['pp']   = fge_bc_tiers( $s['pp'] ?? null, $sv['pp'] );
		$sv['flat'] = fge_bc_tiers( $s['flat'] ?? null, $sv['flat'] );
	}
	unset( $sv );

	if ( isset( $saved['round_to'] ) ) {
		$def['round_to'] = max( 1, (int) $saved['round_to'] );
	}

	return $def;
}

// ── Admin-Menü ─────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'fge_bc_register_menu' );
function fge_bc_register_menu(): void {
	add_submenu_page(
		'edit.php?post_type=firmengolf_event',
		'Budget-Rechner',
		'Budget-Rechner',
		'manage_options',
		'fge-budget-calc',
		'fge_bc_render_settings_page'
	);
}

/** Speichern der geposteten Preise. */
function fge_bc_handle_save(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_POST['fge_bc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_bc_nonce'] ) ), 'fge_bc_save' ) ) {
		return;
	}
	$def = fge_bc_defaults();

	$types = [];
	foreach ( $def['types'] as $t ) {
		$types[] = [ 'id' => $t['id'], 'label' => sanitize_text_field( wp_unslash( $_POST['type_label'][ $t['id'] ] ?? $t['label'] ) ) ];
	}
	$services = [];
	foreach ( $def['services'] as $s ) {
		$id  = $s['id'];
		$pp  = [];
		$fl  = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$pp[ $i ] = max( 0, (float) ( $_POST['svc_pp'][ $id ][ $i ] ?? 0 ) );
			$fl[ $i ] = max( 0, (float) ( $_POST['svc_flat'][ $id ][ $i ] ?? 0 ) );
		}
		$services[] = [
			'id'    => $id,
			'label' => sanitize_text_field( wp_unslash( $_POST['svc_label'][ $id ] ?? $s['label'] ) ),
			'pp'    => $pp,
			'flat'  => $fl,
		];
	}
	update_option( FGE_BC_OPTION, [
		'v'        => $def['v'],
		'types'    => $types,
		'services' => $services,
		'round_to' => max( 1, (int) ( $_POST['round_to'] ?? 50 ) ),
	] );
	add_settings_error( 'fge_bc', 'saved', 'Preise gespeichert.', 'updated' );
}

function fge_bc_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		fge_bc_handle_save();
	}
	$cfg = fge_bc_config();

	// Service-Label-Lookup für die lesbare Zuordnungs-Übersicht.
	$svc_label = [];
	foreach ( $cfg['services'] as $s ) {
		$svc_label[ $s['id'] ] = $s['label'];
	}

	settings_errors( 'fge_bc' );
	?>
	<div class="wrap">
		<h1>Budget-Rechner, Preise</h1>
		<p class="description" style="max-width:680px;">
			Diese Preise speisen den Budget-Rechner auf der Seite <em>Individuelle Events</em>.
			Ein Event-Typ hat <strong>keinen Grundpreis</strong>, er bündelt nur die passenden
			Dienstleistungen. Jede Dienstleistung kostet entweder <strong>pro Person</strong>
			<em>oder</em> eine <strong>Pauschale</strong> (Pauschale &gt; 0 hat Vorrang), jeweils
			als Staffel für die drei Preisniveaus € / €€ / €€€. Die Platzkosten/Greenfee stecken in der
			jeweiligen Golf-Leistung (z.&nbsp;B. „Firmenturnier inkl. Greenfee").
			Welche Services bei welchem Typ erscheinen, ist im Code festgelegt (siehe Übersicht unten).
		</p>
		<form method="post">
			<?php wp_nonce_field( 'fge_bc_save', 'fge_bc_nonce' ); ?>

			<h2>Event-Typen, Anzeigename</h2>
			<table class="widefat striped" style="max-width:680px;">
				<thead><tr><th>Typ (Anzeige)</th></tr></thead>
				<tbody>
				<?php foreach ( $cfg['types'] as $t ) : ?>
					<tr>
						<td><input type="text" class="regular-text" name="type_label[<?php echo esc_attr( $t['id'] ); ?>]" value="<?php echo esc_attr( $t['label'] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px;">Services, Preise je Niveau</h2>
			<p class="description">Je Service drei Werte für € / €€ / €€€. Entweder <strong>pro Person</strong> <em>oder</em> eine <strong>Pauschale</strong>; ist eine Pauschale &gt; 0 gesetzt, gilt diese.</p>
			<table class="widefat striped" style="max-width:1100px;">
				<thead><tr><th>Service (Anzeige)</th><th style="width:100px;">€/P. bei €</th><th style="width:100px;">€/P. bei €€</th><th style="width:100px;">€/P. bei €€€</th><th style="width:110px;">Pauschale €</th><th style="width:110px;">Pauschale €€</th><th style="width:110px;">Pauschale €€€</th></tr></thead>
				<tbody>
				<?php foreach ( $cfg['services'] as $s ) : ?>
					<tr>
						<td><input type="text" class="regular-text" name="svc_label[<?php echo esc_attr( $s['id'] ); ?>]" value="<?php echo esc_attr( $s['label'] ); ?>"></td>
						<?php for ( $i = 0; $i < 3; $i++ ) : ?>
							<td><input type="number" step="0.01" min="0" style="width:90px;" name="svc_pp[<?php echo esc_attr( $s['id'] ); ?>][<?php echo (int) $i; ?>]" value="<?php echo esc_attr( (string) $s['pp'][ $i ] ); ?>"></td>
						<?php endfor; ?>
						<?php for ( $i = 0; $i < 3; $i++ ) : ?>
							<td><input type="number" step="0.01" min="0" style="width:100px;" name="svc_flat[<?php echo esc_attr( $s['id'] ); ?>][<?php echo (int) $i; ?>]" value="<?php echo esc_attr( (string) $s['flat'][ $i ] ); ?>"></td>
						<?php endfor; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px;">Rundung</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="round_to">Rundung auf</label></th>
					<td><input type="number" step="1" min="1" id="round_to" name="round_to" value="<?php echo esc_attr( (string) $cfg['round_to'] ); ?>"> <span class="description">€, Anzeigewerte werden hierauf gerundet</span></td>
				</tr>
			</table>

			<?php submit_button( 'Preise speichern' ); ?>
		</form>

		<h2 style="margin-top:28px;">Zuordnung Typ → Services <span class="description">(fix im Code)</span></h2>
		<table class="widefat striped" style="max-width:920px;">
			<thead><tr><th style="width:170px;">Event-Typ</th><th>Angezeigte Services (●&nbsp;=&nbsp;vorausgewählt, 🔒&nbsp;=&nbsp;fix enthalten)</th></tr></thead>
			<tbody>
			<?php foreach ( $cfg['types'] as $t ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $t['label'] ); ?></strong></td>
					<td><?php
						$labels = array_map(
							static function ( $sid ) use ( $svc_label, $t ) {
								$mark = in_array( $sid, $t['required'], true ) ? ' 🔒' : ( in_array( $sid, $t['default_on'], true ) ? ' ●' : '' );
								return ( $svc_label[ $sid ] ?? $sid ) . $mark;
							},
							$t['services']
						);
						echo esc_html( implode( ' · ', $labels ) );
					?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
