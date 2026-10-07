<?php
/**
 * Control Center: eigene Route, eigener Shell, eigenes Stylesheet.
 *
 * Bewusst NICHT im WordPress-Admin. Design vom 07.10.2026 (Claude Design):
 * helle Seitenleiste mit 17 Bereichen, Geist, ruhige Karten, eine Spalte am
 * Telefon. DESIGN.md gilt für die Kundenstrecken, hier gilt
 * assets/css/fge-cc.css. Inhalte kommen nur aus vorhandenen Daten des
 * WP-Admins, neue Felder nur nach Julius' Einzel-OK.
 *
 * Route: /control/ und /control/<seite>/ (Fallback ?fge_cc=<seite>, falls keine
 * hübschen Permalinks aktiv sind). Zugriff nur mit manage_options, sonst 404,
 * damit die Existenz der Seite nach außen nicht sichtbar ist.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_CC_REWRITE_VERSION = '1.2.0';

/**
 * Seiten des Control Centers: slug => [Label, Gruppe].
 *
 * Reihenfolge und Gruppen nach dem finalen Design (07.10.2026). Seiten, deren
 * Inhalt noch im WordPress-Admin liegt, zeigen bis zu ihrer Etappe einen
 * Hinweis mit Direktlink dorthin (fge_cc_admin_links()).
 */
function fge_cc_pages(): array {
	return [
		'dashboard'     => [ 'Dashboard', 'arbeit' ],
		'anfragen'      => [ 'Anfragen', 'arbeit' ],
		'angebote'      => [ 'Angebote', 'arbeit' ],
		'kalender'      => [ 'Kalender', 'arbeit' ],
		'aufgaben'      => [ 'Aufgaben', 'arbeit' ],
		'partner'       => [ 'Partner', 'partner' ],
		'events'        => [ 'Events', 'partner' ],
		'verzeichnis'   => [ 'Verzeichnis', 'partner' ],
		'kunden'        => [ 'Kunden', 'kunden' ],
		'leads'         => [ 'Leads', 'kunden' ],
		'partnercodes'  => [ 'Partnercodes', 'kunden' ],
		'finanzen'      => [ 'Finanzen', 'geld' ],
		'rechnungen'    => [ 'Rechnungen', 'geld' ],
		'dienstleister' => [ 'Dienstleister', 'stamm' ],
		'postausgang'   => [ 'Postausgang', 'stamm' ],
		'nutzer'        => [ 'Nutzer', 'system' ],
		'einstellungen' => [ 'Einstellungen', 'system' ],
	];
}

/** Gruppen der Seitenleiste. */
function fge_cc_groups(): array {
	return [
		'arbeit'  => 'Arbeit',
		'partner' => 'Partner',
		'kunden'  => 'Kunden',
		'geld'    => 'Geld',
		'stamm'   => 'Stamm',
		'system'  => 'System',
	];
}

/** Alte Adressen vor dem Umbau, damit Lesezeichen und Mail-Links weiter gehen. */
function fge_cc_legacy_pages(): array {
	return [ 'plaetze' => 'verzeichnis', 'geld' => 'finanzen' ];
}

/** Darf der aktuelle Nutzer ins Control Center? */
function fge_cc_can(): bool {
	return current_user_can( 'manage_options' );
}

/** Link auf eine Seite des Control Centers. */
function fge_cc_url( string $page = 'dashboard', array $args = [] ): string {
	$url = get_option( 'permalink_structure' )
		? home_url( '/control/' . ( 'dashboard' === $page ? '' : $page . '/' ) )
		: add_query_arg( 'fge_cc', $page, home_url( '/' ) );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/** Link auf eine einzelne Anfrage im Control Center. */
function fge_cc_request_url( int $req ): string {
	return fge_cc_url( 'anfragen', [ 'req' => $req ] );
}

// ── Routing ──────────────────────────────────────────────────────────────────

add_action( 'init', static function (): void {
	add_rewrite_rule( '^control/?$', 'index.php?fge_cc=dashboard', 'top' );
	add_rewrite_rule( '^control/([a-z0-9-]+)/?$', 'index.php?fge_cc=$matches[1]', 'top' );

	// Regeln nur bei Versionswechsel neu schreiben, nicht bei jedem Aufruf.
	if ( get_option( 'fge_cc_rewrite_version' ) !== FGE_CC_REWRITE_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'fge_cc_rewrite_version', FGE_CC_REWRITE_VERSION );
	}
}, 11 );

add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_cc';
	return $vars;
} );

add_action( 'template_redirect', static function (): void {
	$page = (string) get_query_var( 'fge_cc' );
	if ( '' === $page ) {
		return;
	}
	$legacy = fge_cc_legacy_pages();
	if ( isset( $legacy[ $page ] ) && fge_cc_can() ) {
		$args = array_map( 'sanitize_text_field', wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $args['fge_cc'] );
		wp_safe_redirect( fge_cc_url( $legacy[ $page ], $args ), 301 );
		exit;
	}
	if ( ! isset( fge_cc_pages()[ $page ] ) ) {
		$page = 'dashboard';
	}
	if ( ! fge_cc_can() ) {
		// Nicht angemeldet: zum Login. Angemeldet ohne Recht: 404, die Seite
		// soll für fremde Konten gar nicht existieren.
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( fge_cc_url( $page ) ) );
			exit;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return;
	}
	fge_cc_render( $page );
	exit;
}, 1 );

// ── Shell ────────────────────────────────────────────────────────────────────

function fge_cc_render( string $page ): void {
	nocache_headers();
	$pages  = fge_cc_pages();
	$title  = $pages[ $page ][0];
	$base   = plugins_url( '', FGE_DIR . 'firmengolf-events.php' );
	$req    = 'anfragen' === $page ? absint( $_GET['req'] ?? 0 ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$routes = [
		'dashboard'     => 'fge_cc_page_dashboard',
		'angebote'      => 'fge_cc_page_offers',
		'kalender'      => 'fge_cc_page_calendar',
		'aufgaben'      => 'fge_cc_page_tasks',
		'verzeichnis'   => 'fge_cc_page_partners',
		'kunden'        => 'fge_cc_page_customers',
		'finanzen'      => 'fge_cc_page_money',
		'dienstleister' => 'fge_cc_page_providers',
		'postausgang'   => 'fge_cc_page_outbox',
	];
	?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title ); ?> · Control Center</title>
	<link rel="preload" href="<?php echo esc_url( $base . '/assets/fonts/Geist-Variable.woff2' ); ?>" as="font" type="font/woff2" crossorigin>
	<link rel="stylesheet" href="<?php echo esc_url( $base . '/assets/css/fge-cc.css?v=' . FGE_VERSION ); ?>">
	<script src="<?php echo esc_url( $base . '/assets/js/fge-cc.js?v=' . FGE_VERSION ); ?>" defer></script>
</head>
<body class="cc">
<a class="cc-skip" href="#cc-main">Zum Inhalt</a>
<div class="cc-app">
	<?php fge_cc_sidebar( $page ); ?>
	<main class="cc-main" id="cc-main">
		<?php
		echo '<div class="cc-body' . ( $req > 0 ? ' cc-body--wide' : '' ) . '">';
		if ( $req > 0 ) {
			fge_cc_page_request( $req );
		} else {
			fge_cc_topbar( $title, $page );
			if ( 'anfragen' === $page ) {
				fge_cc_page_requests();
			} elseif ( isset( $routes[ $page ] ) && function_exists( $routes[ $page ] ) ) {
				call_user_func( $routes[ $page ] );
			} else {
				fge_cc_page_stub( $page );
			}
		}
		echo '</div>';
		?>
	</main>
</div>
<?php fge_cc_dialog_shell(); ?>
</body>
</html>
	<?php
}

/** Zähler in der Seitenleiste, nur aus vorhandenen Daten. */
function fge_cc_nav_badges(): array {
	$badges = [ 'dashboard' => fge_cc_badge_count() ];

	$count_meta = static function ( string $type, string $key, $value ): int {
		$q = new WP_Query( [
			'post_type'      => $type,
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => [ [ 'key' => $key, 'value' => $value, 'compare' => is_array( $value ) ? 'IN' : '=' ] ],
		] );
		return (int) $q->found_posts;
	};
	$badges['partner'] = $count_meta( 'firmengolf_partner', '_fge_partner_status', 'in_pruefung' );
	$badges['events']  = $count_meta( 'firmengolf_event', '_fge_event_status', [ 'zur_pruefung', 'aenderung_in_pruefung' ] );

	$failed = function_exists( 'fge_mail_log_recent' ) ? fge_mail_log_recent( 50, 'failed' ) : [];
	$since  = time() - 7 * DAY_IN_SECONDS;
	$badges['postausgang'] = count( array_filter( $failed, static fn( $f ) => strtotime( (string) ( $f['sent_at'] ?? '' ) ) >= $since ) );
	return $badges;
}

function fge_cc_sidebar( string $current ): void {
	$badges = fge_cc_nav_badges();
	$user   = wp_get_current_user();
	$name   = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
	$logo   = plugins_url( 'assets/logo/firmengolf-logo.svg', FGE_DIR . 'firmengolf-events.php' );

	echo '<aside class="cc-side">';
	echo '<a class="cc-brand" href="' . esc_url( fge_cc_url() ) . '"><img src="' . esc_url( $logo ) . '" alt="Firmengolf" width="140" height="28"><span>Control Center</span></a>';

	echo '<div class="cc-gsearch" data-cc-gsearch data-endpoint="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'fge_cc_search' ) ) . '">';
	echo '<input type="search" placeholder="Suchen" aria-label="Alles durchsuchen" autocomplete="off">';
	echo '<div class="cc-gsearch-list" hidden></div>';
	echo '</div>';

	echo '<button class="cc-navtoggle" type="button" aria-expanded="false" data-cc-navtoggle>Menü</button>';
	echo '<nav class="cc-nav" aria-label="Control Center">';
	foreach ( fge_cc_groups() as $g => $label ) {
		echo '<div class="cc-nav-group"><p class="cc-nav-head">' . esc_html( $label ) . '</p>';
		foreach ( fge_cc_pages() as $slug => [ $title, $group ] ) {
			if ( $group !== $g ) {
				continue;
			}
			$badge = (int) ( $badges[ $slug ] ?? 0 );
			printf(
				'<a class="cc-nav-item%s" href="%s"%s><span>%s</span>%s</a>',
				$slug === $current ? ' is-on' : '',
				esc_url( fge_cc_url( $slug ) ),
				$slug === $current ? ' aria-current="page"' : '',
				esc_html( $title ),
				$badge > 0 ? '<span class="cc-badge">' . $badge . '</span>' : ''
			);
		}
		echo '</div>';
	}
	echo '</nav>';

	echo '<div class="cc-side-foot">';
	echo '<p class="cc-side-user"><strong>' . esc_html( $name ) . '</strong><span>Administrator</span></p>';
	echo '<p class="cc-side-links"><a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">Zur Website</a>';
	echo '<a href="' . esc_url( admin_url() ) . '">WordPress</a>';
	echo '<a class="cc-side-out" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Abmelden</a></p>';
	echo '</div>';
	echo '</aside>';
}

/**
 * Seitenkopf: Titel, Unterzeile, Suche der Seite.
 *
 * Die Suche bleibt auf der Seite, die eine eigene hat. Alles andere findet die
 * globale Suche in der Seitenleiste.
 */
function fge_cc_topbar( string $title, string $page = 'anfragen' ): void {
	$targets = [
		'anfragen'    => 'Firma oder FG-Nummer',
		'verzeichnis' => 'Platz, Ort oder PLZ',
		'kunden'      => 'Firma oder Mailadresse',
	];
	$sub = '';
	if ( 'dashboard' === $page ) {
		$user  = wp_get_current_user();
		$first = $user->first_name ?: $user->display_name;
		$hour  = (int) wp_date( 'G' );
		$title = ( $hour < 11 ? 'Guten Morgen' : ( $hour < 18 ? 'Hallo' : 'Guten Abend' ) ) . ', ' . $first;
		$n     = fge_cc_badge_count();
		$sub   = wp_date( 'l, j. F Y' ) . ' · ' . ( $n > 0 ? $n . ( 1 === $n ? ' Vorgang wartet' : ' Vorgänge warten' ) . ' auf dich' : 'nichts wartet auf dich' );
	}

	echo '<header class="cc-top">';
	echo '<div class="cc-top-text"><h1>' . esc_html( $title ) . '</h1>';
	if ( '' !== $sub ) {
		echo '<p class="cc-top-sub">' . esc_html( $sub ) . '</p>';
	}
	echo '</div>';
	if ( isset( $targets[ $page ] ) ) {
		echo '<form class="cc-search" method="get" action="' . esc_url( fge_cc_url( $page ) ) . '" role="search">';
		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<input type="hidden" name="fge_cc" value="' . esc_attr( $page ) . '">';
		}
		echo '<input type="search" name="s" placeholder="' . esc_attr( $targets[ $page ] ) . '" value="' . esc_attr( wp_unslash( $_GET['s'] ?? '' ) ) . '" aria-label="Suche">'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '</form>';
	}
	echo '</header>';
}

// ── Bausteine ────────────────────────────────────────────────────────────────

/** Anzahl der Vorgänge, die auf Julius warten (Zahl in der Seitenleiste). */
function fge_cc_badge_count(): int {
	$n = 0;
	foreach ( fge_cc_worklist() as $row ) {
		if ( $row['mine'] && ! $row['snoozed'] && ! $row['cold'] ) {
			$n++;
		}
	}
	return $n;
}

/** Status-Pille. */
function fge_cc_pill( string $text, string $tone = 'neutral' ): string {
	return '<span class="cc-pill cc-pill--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
}

/** Tonfall einer Phase für die Pille. */
function fge_cc_phase_tone( string $phase ): string {
	$map = [
		'Eingang'       => 'info',
		'Termin'        => 'info',
		'Angebot'       => 'warn',
		'Angebot läuft' => 'warn',
		'Vorbereitung'  => 'good',
		'Eventtag'      => 'good',
		'Nachlauf'      => 'neutral',
		'Abgelehnt'     => 'bad',
		'Verloren'      => 'bad',
		'Nicht verfügbar' => 'bad',
	];
	return $map[ $phase ] ?? 'neutral';
}

/** Datum lesbar, leer wenn keins. */
function fge_cc_date( int $ts ): string {
	return $ts > 0 ? wp_date( 'D, d.m.Y', $ts ) : '';
}

/** Leerzustand. */
function fge_cc_empty( string $text ): void {
	echo '<p class="cc-empty">' . esc_html( $text ) . '</p>';
}

/**
 * Wo der Inhalt einer noch nicht umgezogenen Seite heute liegt.
 * Etappe nach dem Plan vom 07.10.2026.
 */
function fge_cc_admin_links(): array {
	$pc = defined( 'FGE_PC_POST_TYPE' ) ? FGE_PC_POST_TYPE : 'fge_partnercode';
	return [
		'partner'       => [ 3, 'Partner-Anmeldungen freischalten, Rückfragen und Pausieren, Profile je Partnertyp.', [
			'Partner im WordPress-Admin' => admin_url( 'edit.php?post_type=firmengolf_partner' ),
		] ],
		'events'        => [ 3, 'Events aller Partner prüfen, freigeben und bearbeiten.', [
			'Events im WordPress-Admin' => admin_url( 'edit.php?post_type=firmengolf_event' ),
		] ],
		'leads'         => [ 3, 'Leads aus dem Budget-Rechner und die Preise des Rechners.', [
			'Anfragen im WordPress-Admin' => admin_url( 'edit.php?post_type=firmengolf_request' ),
			'Budget-Rechner'              => admin_url( 'edit.php?post_type=firmengolf_event&page=fge-budget-calc' ),
		] ],
		'partnercodes'  => [ 3, 'Partnercodes, Rabatt, Provision und Abrechnung.', [
			'Partnercodes im WordPress-Admin' => admin_url( 'edit.php?post_type=' . $pc ),
		] ],
		'rechnungen'    => [ 5, 'Rechnungen laufen manuell über Lexoffice. Nummern und Status stehen je Anfrage im Feld „Lexoffice (intern, manuell)".', [
			'Anfragen im WordPress-Admin' => admin_url( 'edit.php?post_type=firmengolf_request' ),
		] ],
		'nutzer'        => [ 4, 'Nutzer, Passwort zurücksetzen, Mailadresse ändern, Admins anlegen.', [
			'Benutzer im WordPress-Admin' => admin_url( 'users.php' ),
		] ],
		'einstellungen' => [ 4, 'Firmendaten, Aufschlag und Absender sind derzeit fest im Plugin hinterlegt.', [
			'Allgemeine Einstellungen' => admin_url( 'options-general.php' ),
		] ],
	];
}

function fge_cc_page_stub( string $page ): void {
	$info = fge_cc_admin_links()[ $page ] ?? null;
	echo '<section class="cc-card cc-stub">';
	if ( ! $info ) {
		echo '<p class="cc-muted">Dieser Bereich ist noch nicht gebaut.</p></section>';
		return;
	}
	[ $stage, $text, $links ] = $info;
	echo '<p class="cc-kicker">Kommt in Etappe ' . (int) $stage . '</p>';
	echo '<p>' . esc_html( $text ) . '</p>';
	echo '<p class="cc-muted">Bis dahin liegt das im WordPress-Admin:</p><div class="cc-btnrow">';
	foreach ( $links as $label => $url ) {
		echo '<a class="cc-btn" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</div></section>';
}

// ── Dashboard ────────────────────────────────────────────────────────────────

function fge_cc_page_dashboard(): void {
	$rows = fge_cc_worklist();

	if ( ! empty( $GLOBALS['fge_cc_worklist_truncated'] ) ) {
		echo '<p class="cc-msg cc-msg--err">Es sind mehr offene Vorgänge da, als hier passen. '
			. 'Schließe Erledigtes ab oder setze Liegengebliebenes auf „verloren", damit die Liste die Wahrheit sagt.</p>';
	}

	fge_cc_month_kpis_panel();

	$mine = $others = $soon = $cold = [];
	foreach ( $rows as $r ) {
		if ( $r['snoozed'] ) {
			continue;
		}
		$d = $r['date'];
		if ( $d > 0 && $d >= fge_cc_today() && $d <= ( fge_cc_today() + 2 * DAY_IN_SECONDS - 1 ) ) {
			$soon[] = $r;
		}
		if ( $r['cold'] ) {
			$cold[] = $r;
		} elseif ( $r['mine'] ) {
			$mine[] = $r;
		} else {
			$others[] = $r;
		}
	}

	echo '<section class="cc-section">';
	echo '<div class="cc-section-head"><h2>Anfragen<span class="cc-count">' . (int) ( count( $mine ) + count( $others ) ) . '</span></h2></div>';
	echo '<div class="cc-tabs" role="tablist">';
	fge_cc_tab( 'mine', 'Wartet auf mich', count( $mine ), true );
	fge_cc_tab( 'others', 'Wartet auf andere', count( $others ), false );
	fge_cc_tab( 'soon', 'Heute und morgen', count( $soon ), false );
	fge_cc_tab( 'cold', 'Verstaubt', count( $cold ), false );
	echo '</div>';
	fge_cc_worklist_panel( 'mine', $mine, 'Nichts offen. Alles, was zu tun war, ist getan.' );
	fge_cc_worklist_panel( 'others', $others, 'Es wartet gerade nichts auf andere.' );
	fge_cc_worklist_panel( 'soon', $soon, 'Heute und morgen steht kein Event an.' );
	fge_cc_worklist_panel( 'cold', $cold, 'Nichts ist liegen geblieben.', 'Seit mehr als ' . (int) fge_cc_cold_days() . ' Tagen ohne Fortschritt und ohne Termin. Nachfassen oder auf „verloren" setzen, damit die Liste ehrlich bleibt.' );
	echo '</section>';

	echo '<div class="cc-cols cc-cols--even">';
	fge_cc_platform_panel();
	fge_cc_recent_panel();
	echo '</div>';

	fge_cc_upcoming_panel( $rows );
	fge_cc_failed_mails_panel();

	fge_cc_tabs_script();
}

/**
 * Kennzahlen des laufenden Monats, nur aus vorhandenen Daten.
 *
 * Anfragen und Angebote zählen nach Eingang im Monat (wie bisher der
 * Trichter), Buchungen, Umsatz und Marge nach Annahme im Monat (wie „Finanzen").
 */
function fge_cc_month_kpis(): array {
	$since = fge_cc_local_ts( wp_date( 'Y-m-01' ) );
	$ids   = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => 400,
		'fields'      => 'ids',
		'date_query'  => [ [ 'after' => wp_date( 'Y-m-d', $since - 1 ) ] ],
	] );
	$k = [ 'reqs' => count( $ids ), 'offers' => 0, 'book' => 0, 'net' => 0.0, 'margin' => 0.0, 'margin_base' => 0.0 ];
	foreach ( $ids as $id ) {
		if ( '1' === (string) get_post_meta( $id, '_fge_offer_sent', true ) ) {
			$k['offers']++;
		}
	}
	$acc = fge_cc_query_requests( [ 'meta_query' => [ [ 'key' => '_fge_offer_status', 'value' => 'accepted' ] ] ], 400 );
	foreach ( $acc['ids'] as $req ) {
		if ( (int) strtotime( (string) get_post_meta( $req, '_fge_offer_accepted_at', true ) ) < $since ) {
			continue;
		}
		$net = function_exists( 'fge_cc_offer_net' ) ? fge_cc_offer_net( $req ) : 0.0;
		$k['book']++;
		$k['net'] += $net;
		$cost = function_exists( 'fge_cc_partner_cost_net' ) ? fge_cc_partner_cost_net( $req ) + fge_cc_extras_cost_net( $req ) : 0.0;
		if ( $cost > 0 ) {
			$k['margin']      += $net - $cost;
			$k['margin_base'] += $net;
		}
	}
	return $k;
}

function fge_cc_month_kpis_panel(): void {
	$k   = fge_cc_month_kpis();
	$pct = static fn( int $n ) => $k['reqs'] > 0 ? round( $n / $k['reqs'] * 100 ) . ' % der Anfragen' : 'noch keine Anfragen';
	$items = [
		[ 'Anfragen', (string) $k['reqs'], 'eingegangen seit dem 1.', fge_cc_url( 'anfragen' ) ],
		[ 'Angebote', (string) $k['offers'], $pct( $k['offers'] ), fge_cc_url( 'angebote' ) ],
		[ 'Buchungen', (string) $k['book'], 'angenommen im Monat', '' ],
		[ 'Gebucht netto', number_format_i18n( $k['net'], 0 ) . ' €', 'Summe der Annahmen', fge_cc_url( 'finanzen' ) ],
		[
			'Marge',
			$k['margin_base'] > 0 ? number_format_i18n( $k['margin'], 0 ) . ' €' : 'unbekannt',
			$k['margin_base'] > 0 ? round( $k['margin'] / $k['margin_base'] * 100 ) . ' % vom Umsatz' : 'kein Einkauf hinterlegt',
			fge_cc_url( 'finanzen' ),
		],
	];
	echo '<section class="cc-section"><div class="cc-section-head"><h2>Kennzahlen im ' . esc_html( wp_date( 'F' ) ) . '</h2></div>';
	echo '<div class="cc-kpis">';
	foreach ( $items as [ $label, $value, $sub, $url ] ) {
		$tag = '' !== $url ? 'a href="' . esc_url( $url ) . '"' : 'div';
		echo '<' . $tag . ' class="cc-kpi"><span class="cc-kpi-label">' . esc_html( $label ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<span class="cc-kpi-value">' . esc_html( $value ) . '</span>';
		echo '<span class="cc-kpi-sub">' . esc_html( $sub ) . '</span></' . ( '' !== $url ? 'a' : 'div' ) . '>';
	}
	echo '</div></section>';
}

function fge_cc_tab( string $id, string $label, int $count, bool $on ): void {
	printf(
		'<button class="cc-tab%s" data-cc-tab="%s" role="tab" aria-selected="%s" type="button"><span class="cc-tab-n">%d</span>%s</button>',
		$on ? ' is-on' : '',
		esc_attr( $id ),
		$on ? 'true' : 'false',
		$count,
		esc_html( $label )
	);
}

function fge_cc_worklist_panel( string $id, array $rows, string $empty, string $note = '' ): void {
	echo '<div class="cc-panel" data-cc-pane="' . esc_attr( $id ) . '"' . ( 'mine' === $id ? '' : ' hidden' ) . '>';
	if ( '' !== $note && $rows ) {
		echo '<p class="cc-section-sub cc-panel-note">' . esc_html( $note ) . '</p>';
	}
	echo '<div class="cc-list cc-wl">';
	if ( ! $rows ) {
		fge_cc_empty( $empty );
	} else {
		echo '<div class="cc-wl-head" aria-hidden="true"><span>Firma</span><span>Nächste Aufgabe</span><span>Dringlichkeit</span><span>Eventdatum</span><span>Alter</span></div>';
		foreach ( $rows as $r ) {
			fge_cc_worklist_row( $r );
		}
	}
	echo '</div></div>';
}

function fge_cc_worklist_row( array $r ): void {
	$tasks = array_values( array_filter( $r['tasks'], static fn( $t ) => 'me' === $t['who'] ) );
	if ( ! $tasks ) {
		$tasks = $r['tasks'];
	}
	$lead    = $tasks[0] ?? null;
	$req     = (int) $r['req'];
	$contact = trim( get_post_meta( $req, '_fge_contact_first_name', true ) . ' ' . get_post_meta( $req, '_fge_contact_last_name', true ) );
	$urg     = [ 'now' => [ 'jetzt', 'bad' ], 'soon' => [ 'bald', 'warn' ], 'wait' => [ 'warten', 'neutral' ] ][ $r['urgency'] ?? 'wait' ] ?? [ 'warten', 'neutral' ];

	echo '<a class="cc-row cc-wl-row" href="' . esc_url( fge_cc_request_url( $req ) ) . '">';
	echo '<span class="cc-row-main"><span class="cc-row-top"><span class="cc-company">' . esc_html( $r['company'] ?: 'ohne Firma' ) . '</span></span>';
	echo '<span class="cc-more"><span class="cc-ref">' . esc_html( $r['ref'] ) . '</span>' . ( '' !== $contact ? ' · ' . esc_html( $contact ) : '' ) . ' · ' . esc_html( $r['phase'] ) . '</span></span>';
	echo '<span class="cc-row-main">';
	if ( $lead ) {
		echo '<span class="cc-next">' . esc_html( $lead['text'] ) . '</span>';
	}
	if ( count( $tasks ) > 1 ) {
		echo '<span class="cc-more">und ' . (int) ( count( $tasks ) - 1 ) . ' weitere</span>';
	}
	echo '</span>';
	echo '<span>' . fge_cc_pill( $urg[0], $urg[1] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="cc-when">' . esc_html( $r['date'] > 0 ? wp_date( 'D, d.m.', (int) $r['date'] ) : 'offen' ) . '</span>';
	echo '<span class="cc-age">' . (int) $r['age'] . ( 1 === (int) $r['age'] ? ' Tag' : ' Tage' ) . '</span>';
	echo '</a>';
}

/** Was außer Anfragen noch wartet: nur Zähler aus vorhandenen Daten. */
function fge_cc_platform_panel(): void {
	$badges = fge_cc_nav_badges();
	$todo   = [];

	if ( $badges['partner'] > 0 ) {
		$todo[] = [ 'Neue Partner-Anmeldungen', $badges['partner'], 'Status In Prüfung', admin_url( 'edit.php?post_type=firmengolf_partner' ) ];
	}
	if ( $badges['events'] > 0 ) {
		$todo[] = [ 'Events zur Prüfung', $badges['events'], 'neu eingereicht oder geändert', admin_url( 'edit.php?post_type=firmengolf_event' ) ];
	}
	$expiring = 0;
	$pending  = fge_cc_query_requests( [ 'meta_query' => [ [ 'key' => '_fge_offer_status', 'value' => 'pending' ] ] ], 300 );
	foreach ( $pending['ids'] as $req ) {
		$ts = (int) get_post_meta( $req, '_fge_offer_deadline', true );
		if ( $ts >= time() && $ts <= time() + 2 * DAY_IN_SECONDS ) {
			$expiring++;
		}
	}
	if ( $expiring > 0 ) {
		$todo[] = [ 'Angebote, deren Frist bald abläuft', $expiring, 'in den nächsten 2 Tagen', fge_cc_url( 'angebote' ) ];
	}
	if ( $badges['postausgang'] > 0 ) {
		$todo[] = [ 'Fehlgeschlagene Mails', $badges['postausgang'], 'nicht zugestellt, letzte 7 Tage', fge_cc_url( 'postausgang' ) ];
	}
	$no_mail = 0;
	foreach ( get_posts( [ 'post_type' => 'firmengolf_partner', 'post_status' => [ 'publish', 'draft', 'pending' ], 'numberposts' => -1, 'fields' => 'ids' ] ) as $pid ) {
		if ( function_exists( 'fge_partner_is_stammdaten' ) && fge_partner_is_stammdaten( (int) $pid ) ) {
			continue;
		}
		if ( function_exists( 'fge_cc_partner_email' ) && '' === fge_cc_partner_email( (int) $pid ) ) {
			$no_mail++;
		}
	}
	if ( $no_mail > 0 ) {
		$todo[] = [ 'Partner ohne Kontaktmail', $no_mail, 'Anfragen gehen nicht automatisch raus', fge_cc_url( 'verzeichnis', [ 'filter' => 'ohne_mail' ] ) ];
	}

	echo '<section class="cc-section"><div class="cc-section-head"><h2>Plattform</h2></div>';
	echo '<div class="cc-list">';
	if ( ! $todo ) {
		fge_cc_empty( 'Bei Partnern, Events, Angeboten und Mails ist alles erledigt.' );
	}
	foreach ( $todo as [ $label, $n, $sub, $url ] ) {
		echo '<a class="cc-row" href="' . esc_url( $url ) . '"><span class="cc-row-main"><span class="cc-company">' . esc_html( $label ) . '</span>';
		echo '<span class="cc-more">' . esc_html( $sub ) . '</span></span>';
		echo '<span class="cc-row-meta"><strong class="cc-todo-n">' . (int) $n . '</strong><span class="cc-btn">Öffnen</span></span></a>';
	}
	echo '</div></section>';
}

/** Was seit gestern passiert ist. */
function fge_cc_recent_panel(): void {
	$acts = function_exists( 'fge_activity_recent' ) ? fge_activity_recent( 12 ) : [];
	echo '<section class="cc-section"><div class="cc-section-head"><h2>Seit gestern<span class="cc-count">' . (int) count( $acts ) . '</span></h2></div>';
	echo '<div class="cc-card">';
	if ( ! $acts ) {
		fge_cc_empty( 'Noch keine Ereignisse aufgezeichnet.' );
	}
	foreach ( $acts as $a ) {
		$req = (int) $a['request_id'];
		echo '<p class="cc-feed-item">';
		if ( $req > 0 ) {
			echo '<a href="' . esc_url( fge_cc_request_url( $req ) ) . '">' . esc_html( fge_request_number( $req ) ) . '</a> ';
		}
		echo esc_html( mb_substr( (string) $a['text'], 0, 90 ) );
		echo '<br><span class="cc-muted">' . esc_html( fge_cc_ago( (string) $a['created_at'] ) ) . '</span></p>';
	}
	echo '</div></section>';
}

/** Gebuchte Events der nächsten sieben Tage, sonst die nächsten drei. */
function fge_cc_upcoming_panel( array $rows ): void {
	$today  = fge_cc_today();
	$booked = [];
	$acc    = fge_cc_query_requests( [ 'meta_query' => [ [ 'key' => '_fge_offer_status', 'value' => 'accepted' ] ] ], 300 );
	foreach ( $acc['ids'] as $req ) {
		$d = fge_cc_event_date( $req );
		if ( $d >= $today ) {
			$booked[] = [ $req, $d ];
		}
	}
	usort( $booked, static fn( $a, $b ) => $a[1] <=> $b[1] );
	$week = array_values( array_filter( $booked, static fn( $x ) => $x[1] <= $today + 7 * DAY_IN_SECONDS ) );
	$list = $week ?: array_slice( $booked, 0, 3 );

	echo '<section class="cc-section"><div class="cc-section-head"><h2>' . ( $week ? 'Nächste Events, 7 Tage' : 'Nächste Events' ) . '</h2></div>';
	if ( ! $week && $list ) {
		echo '<p class="cc-section-sub">In den nächsten 7 Tagen steht nichts an. Danach:</p>';
	}
	if ( ! $list ) {
		echo '<div class="cc-list">';
		fge_cc_empty( 'Kein gebuchtes Event in Sicht.' );
		echo '</div></section>';
		return;
	}
	echo '<div class="cc-tablewrap"><table class="cc-table"><thead><tr><th>Datum</th><th>Uhrzeit</th><th>Firma</th><th>Partner</th><th class="cc-num">Personen</th><th>Vortags-Info</th><th>Es fehlt noch</th></tr></thead><tbody>';
	foreach ( $list as [ $req, $d ] ) {
		$days    = (int) round( ( $d - $today ) / DAY_IN_SECONDS );
		$start   = trim( (string) get_post_meta( $req, '_fge_day_start_time', true ) );
		$missing = [];
		foreach ( [ 'day_start_time' => 'Startzeit', 'day_meeting_point' => 'Treffpunkt', 'day_onsite_name' => 'Ansprechpartner' ] as $k => $l ) {
			if ( '' === trim( (string) get_post_meta( $req, '_fge_' . $k, true ) ) ) {
				$missing[] = $l;
			}
		}
		$partner = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
		$pax     = function_exists( 'fge_request_pax_current' ) ? fge_request_pax_current( $req ) : (int) get_post_meta( $req, '_fge_expected_participants', true );
		$info    = '' !== (string) get_post_meta( $req, '_fge_day_info_sent', true );
		echo '<tr onclick="location.href=\'' . esc_js( fge_cc_request_url( $req ) ) . '\'">';
		echo '<td><strong>' . esc_html( wp_date( 'D, d.m.', $d ) ) . '</strong><span class="cc-td-sub">' . esc_html( 0 === $days ? 'heute' : ( 1 === $days ? 'morgen' : 'in ' . $days . ' Tagen' ) ) . '</span></td>';
		echo '<td>' . esc_html( '' !== $start ? $start : 'offen' ) . '</td>';
		echo '<td><a href="' . esc_url( fge_cc_request_url( $req ) ) . '">' . esc_html( (string) get_post_meta( $req, '_fge_company_name', true ) ) . '</a></td>';
		echo '<td>' . esc_html( $partner > 0 ? get_the_title( $partner ) : '' ) . '</td>';
		echo '<td class="cc-num">' . (int) $pax . '</td>';
		echo '<td>' . fge_cc_pill( $info ? 'gesendet' : 'noch nicht', $info ? 'good' : 'neutral' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td' . ( $missing ? ' class="cc-td-warn"' : '' ) . '>' . esc_html( $missing ? implode( ', ', $missing ) : 'alles da' ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div></section>';
}

/** Nicht zugestellte Mails, eingeklappt wenn alles ankam. */
function fge_cc_failed_mails_panel(): void {
	$failed = function_exists( 'fge_mail_log_recent' ) ? fge_mail_log_recent( 5, 'failed' ) : [];
	echo '<section class="cc-section"><details class="cc-fold"' . ( $failed ? ' open' : '' ) . '><summary class="cc-section-head"><h2>Mails</h2><span class="cc-muted">' . esc_html( $failed ? count( $failed ) . ' nicht zugestellt' : 'alle angekommen' ) . '</span></summary>';
	if ( $failed ) {
		echo '<div class="cc-list">';
		foreach ( $failed as $f ) {
			$req = (int) ( $f['request_id'] ?? 0 );
			$url = $req > 0 ? fge_cc_request_url( $req ) : fge_cc_url( 'postausgang' );
			echo '<a class="cc-row" href="' . esc_url( $url ) . '"><span class="cc-row-main"><span class="cc-company">' . esc_html( mb_substr( (string) $f['subject'], 0, 80 ) ) . '</span>';
			echo '<span class="cc-more">An ' . esc_html( (string) $f['recipient'] ) . ' · ' . esc_html( fge_cc_ago( (string) $f['sent_at'] ) ) . '</span></span>';
			echo '<span class="cc-row-meta"><span class="cc-btn">Ansehen</span></span></a>';
		}
		echo '</div>';
	}
	echo '</details></section>';
}

/** „vor 3 Stunden" statt Zeitstempel. */
function fge_cc_ago( string $mysql ): string {
	$ts = (int) strtotime( $mysql );
	if ( $ts <= 0 ) {
		return '';
	}
	$diff = time() - $ts;
	if ( $diff < 3600 ) {
		return 'vor ' . max( 1, (int) round( $diff / 60 ) ) . ' Min';
	}
	if ( $diff < DAY_IN_SECONDS ) {
		return 'vor ' . (int) round( $diff / 3600 ) . ' Std';
	}
	return wp_date( 'd.m.Y H:i', $ts );
}

function fge_cc_tabs_script(): void {
	?>
	<script>
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('[data-cc-tab]') : null;
		if (!b) { return; }
		var id = b.dataset.ccTab;
		document.querySelectorAll('[data-cc-tab]').forEach(function (t) {
			var on = t === b;
			t.classList.toggle('is-on', on);
			t.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		document.querySelectorAll('[data-cc-pane]').forEach(function (p) {
			p.hidden = p.dataset.ccPane !== id;
		});
	});
	</script>
	<?php
}

// ── Anfragenliste ────────────────────────────────────────────────────────────

function fge_cc_page_requests(): void {
	$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$phase  = sanitize_text_field( wp_unslash( $_GET['phase'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$result = fge_cc_query_requests( [], 300 );
	$groups = [
		'mine'   => [ 'Du bist dran', [] ],
		'others' => [ 'Wartet auf Plätze oder Kunden', [] ],
		'booked' => [ 'Gebucht, Event und Abrechnung', [] ],
		'closed' => [ 'Abgeschlossen oder verloren', [] ],
	];
	$terminal = fge_cc_terminal_statuses();

	foreach ( $result['ids'] as $id ) {
		$ref     = fge_request_number( $id );
		$company = (string) get_post_meta( $id, '_fge_company_name', true );
		if ( '' !== $search && false === mb_stripos( $ref . ' ' . $company, $search ) ) {
			continue;
		}
		[ $num, $phase_name ] = fge_cc_phase( $id );
		if ( '' !== $phase && $phase_name !== $phase ) {
			continue;
		}
		$status = (string) get_post_meta( $id, '_fge_request_status', true );
		$tasks  = fge_cc_tasks( $id );
		$mine   = array_values( array_filter( $tasks, static fn( $t ) => 'me' === $t['who'] ) );
		$row    = [
			'req'     => $id,
			'ref'     => $ref,
			'company' => $company,
			'num'     => (int) $num,
			'phase'   => $phase_name,
			'next'    => $mine[0]['text'] ?? ( $tasks[0]['text'] ?? '' ),
			'date'    => fge_cc_event_date( $id ),
		];
		if ( in_array( $status, $terminal, true ) ) {
			$groups['closed'][1][] = $row;
		} elseif ( 'accepted' === (string) get_post_meta( $id, '_fge_offer_status', true ) ) {
			$groups['booked'][1][] = $row;
		} elseif ( $mine ) {
			$groups['mine'][1][] = $row;
		} else {
			$groups['others'][1][] = $row;
		}
	}

	fge_cc_truncation_note( $result, 'Anfragen' );

	$summary = [];
	foreach ( [ 'mine' => 'du bist dran', 'others' => 'wartet auf Plätze oder Kunden', 'booked' => 'gebucht', 'closed' => 'abgeschlossen oder verloren' ] as $k => $l ) {
		$summary[] = count( $groups[ $k ][1] ) . ' ' . $l;
	}
	echo '<p class="cc-top-sub cc-sub-under">' . esc_html( implode( ' · ', $summary ) ) . '</p>';

	echo '<div class="cc-filters">';
	$phases = [ 'Eingang', 'Termin', 'Angebot', 'Angebot läuft', 'Vorbereitung', 'Eventtag', 'Nachlauf', 'Abgelehnt', 'Verloren' ];
	echo '<a class="cc-chip' . ( '' === $phase ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'anfragen', $search ? [ 's' => $search ] : [] ) ) . '">Alle Phasen</a>';
	foreach ( $phases as $p ) {
		$args = [ 'phase' => $p ] + ( $search ? [ 's' => $search ] : [] );
		echo '<a class="cc-chip' . ( $phase === $p ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'anfragen', $args ) ) . '">' . esc_html( $p ) . '</a>';
	}
	echo '</div>';

	if ( '' !== $search ) {
		$hits = array_sum( array_map( static fn( $g ) => count( $g[1] ), $groups ) );
		echo '<p class="cc-muted">' . (int) $hits . ' Treffer für „' . esc_html( $search ) . '"</p>';
	}

	$any = false;
	foreach ( $groups as $key => [ $label, $rows ] ) {
		if ( ! $rows ) {
			continue;
		}
		$any = true;
		echo '<section class="cc-section"><div class="cc-section-head"><h2>' . esc_html( $label ) . '<span class="cc-count">' . (int) count( $rows ) . '</span></h2></div>';
		echo '<div class="cc-list">';
		foreach ( $rows as $r ) {
			fge_cc_request_list_row( $r, 'closed' === $key );
		}
		echo '</div></section>';
	}
	if ( ! $any ) {
		echo '<div class="cc-list">';
		fge_cc_empty( 'Keine Anfrage gefunden.' );
		echo '</div>';
	}
}

/** Eine Zeile der Anfragenliste: wer, was als Nächstes, wie weit. */
function fge_cc_request_list_row( array $r, bool $closed ): void {
	$req   = (int) $r['req'];
	$pax   = function_exists( 'fge_request_pax_current' ) ? fge_request_pax_current( $req ) : (int) get_post_meta( $req, '_fge_expected_participants', true );
	$place = (string) get_post_meta( $req, '_fge_desired_region', true );
	if ( '' === $place ) {
		$place = (string) get_post_meta( $req, '_fge_company_city', true );
	}
	$dates = $r['date'] > 0
		? [ wp_date( 'D, d.m.', (int) $r['date'] ) ]
		: array_values( function_exists( 'fge_rr_wish_dates' ) ? fge_rr_wish_dates( $req ) : [] );
	$meta = array_filter( [ $pax > 0 ? $pax . ' Personen' : '', $place, implode( ', ', $dates ) ] );

	echo '<a class="cc-row cc-rl-row" href="' . esc_url( fge_cc_request_url( $req ) ) . '">';
	echo '<span class="cc-row-main"><span class="cc-row-top"><span class="cc-ref">' . esc_html( $r['ref'] ) . '</span><span class="cc-company">' . esc_html( $r['company'] ?: 'ohne Firma' ) . '</span></span>';
	echo '<span class="cc-more">' . esc_html( implode( ' · ', $meta ) ) . '</span></span>';
	echo '<span class="cc-row-main"><span class="cc-next">' . esc_html( $closed ? $r['phase'] : ( $r['next'] ?: $r['phase'] ) ) . '</span>';
	echo fge_cc_progress( $closed ? 8 : $r['num'], ! $closed && in_array( $r['phase'], [ 'Abgelehnt', 'Verloren', 'Nicht verfügbar' ], true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</span>';
	echo '<span class="cc-rl-step">' . esc_html( $closed ? $r['phase'] : 'Schritt ' . $r['num'] . ' von 7' ) . '</span>';
	echo '</a>';
}

/** Sieben Segmente: erledigt grün, aktuell blau. $num 8 = alles erledigt. */
function fge_cc_progress( int $num, bool $stopped = false ): string {
	$out = '<span class="cc-progress" aria-hidden="true">';
	for ( $i = 1; $i <= 7; $i++ ) {
		$cls  = $i < $num ? 'is-done' : ( $i === $num ? ( $stopped ? 'is-stop' : 'is-on' ) : '' );
		$out .= '<span class="' . $cls . '"></span>';
	}
	return $out . '</span>';
}

// ── Einzelne Anfrage (Platzhalter bis Stufe 3) ───────────────────────────────

function fge_cc_page_request( int $req ): void {
	if ( 'firmengolf_request' !== get_post_type( $req ) ) {
		fge_cc_empty( 'Diese Anfrage gibt es nicht.' );
		return;
	}
	echo '<p class="cc-back"><a href="' . esc_url( fge_cc_url( 'anfragen' ) ) . '">Alle Anfragen</a></p>';
	fge_cc_message( $req );
	fge_cc_request_header( $req );

	$mails = function_exists( 'fge_mail_log_for_request' ) ? count( fge_mail_log_for_request( $req, 50 ) ) : 0;
	$tabs  = [
		'prozess' => 'Prozess',
		'daten'   => 'Daten',
		'angebot' => 'Angebot und Eventtag',
		'lex'     => 'Lexoffice und Tracking',
		'mails'   => 'Mails' . ( $mails ? ' ' . $mails : '' ),
	];
	echo '<div class="cc-tabs" role="tablist" data-cc-rtabs="' . (int) $req . '">';
	foreach ( $tabs as $id => $label ) {
		echo '<button type="button" class="cc-tab' . ( 'prozess' === $id ? ' is-on' : '' ) . '" role="tab" data-cc-rtab="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</button>';
	}
	echo '</div>';

	// Prozess: Phase mit ihren Knöpfen, Platz-Pipeline, Aufgaben und Verlauf.
	echo '<div class="cc-rpane" data-cc-rpane="prozess">';
	echo '<section class="cc-card cc-processcard">';
	fge_cc_request_steps( $req );
	fge_cc_request_phase_panel( $req );
	echo '</section>';
	echo '<div class="cc-cols">';
	echo '<div class="cc-col-main">';
	if ( function_exists( 'fge_cc_venues_panel' )
		&& ( '1' !== (string) get_post_meta( $req, '_fge_offer_sent', true ) || fge_venues_get( $req ) ) ) {
		fge_cc_venues_panel( $req );
	}
	echo '</div><div class="cc-col-side">';
	fge_cc_request_tasks( $req );
	fge_cc_request_timeline( $req );
	fge_cc_request_snooze( $req );
	echo '</div></div>';
	echo '</div>';

	// Daten: Kontakte und alles, was angefragt wurde.
	echo '<div class="cc-rpane" data-cc-rpane="daten" hidden>';
	fge_cc_request_contacts( $req );
	fge_cc_request_facts( $req );
	echo '<p class="cc-muted">Bearbeiten der Anfragedaten: <a href="' . esc_url( get_edit_post_link( $req, 'raw' ) ) . '">im WordPress-Admin öffnen</a>.</p>';
	echo '</div>';

	// Angebot und Eventtag: Positionen, Angebotsstand, Eventtag-Stand.
	echo '<div class="cc-rpane" data-cc-rpane="angebot" hidden>';
	fge_cc_request_offer_tab( $req );
	echo '</div>';

	echo '<div class="cc-rpane" data-cc-rpane="lex" hidden>';
	fge_cc_request_lex_tab( $req );
	echo '</div>';

	echo '<div class="cc-rpane" data-cc-rpane="mails" hidden>';
	fge_cc_request_mails( $req );
	echo '</div>';
}

/** Reiter „Angebot und Eventtag": nur vorhandene Daten und Formulare. */
function fge_cc_request_offer_tab( int $req ): void {
	$offer = (string) get_post_meta( $req, '_fge_offer_status', true );
	$sent  = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );

	if ( 'accepted' !== $offer && function_exists( 'fge_cc_positions_panel' ) ) {
		echo '<section class="cc-card">';
		fge_cc_positions_panel( $req );
		if ( function_exists( 'fge_cc_provider_datalist' ) ) {
			fge_cc_provider_datalist();
		}
		echo '</section>';
	}

	$net      = function_exists( 'fge_cc_offer_net' ) ? fge_cc_offer_net( $req ) : 0.0;
	$deadline = (int) get_post_meta( $req, '_fge_offer_deadline', true );
	$labels   = [ 'pending' => 'beim Kunden', 'accepted' => 'angenommen', 'declined' => 'abgelehnt' ];
	echo '<section class="cc-card"><h2>Angebot</h2><dl class="cc-facts">';
	fge_cc_fact( 'Stand', $sent ? ( $labels[ $offer ] ?? 'gesendet' ) : 'noch nicht gesendet' );
	if ( $sent ) {
		$at = (string) get_post_meta( $req, '_fge_offer_sent_at', true );
		fge_cc_fact( 'Gesendet am', '' !== $at ? wp_date( 'd.m.Y H:i', is_numeric( $at ) ? (int) $at : (int) strtotime( $at ) ) : '' );
		fge_cc_fact( 'Frist', $deadline > 0 ? wp_date( 'd.m.Y', $deadline ) : '' );
		fge_cc_fact( 'Fassung', (string) get_post_meta( $req, '_fge_offer_version', true ) );
	}
	fge_cc_fact( 'Summe netto', $net > 0 ? number_format_i18n( $net, 2 ) . ' €' : '' );
	if ( 'accepted' === $offer ) {
		$acc_at = (int) strtotime( (string) get_post_meta( $req, '_fge_offer_accepted_at', true ) );
		fge_cc_fact( 'Angenommen am', $acc_at > 0 ? wp_date( 'd.m.Y H:i', $acc_at ) : '' );
		if ( function_exists( 'fge_booked_extras' ) ) {
			$labels_x = array_map( static fn( $x ) => (string) $x['label'], fge_booked_extras( $req ) );
			fge_cc_fact( 'Gebuchte Leistungen', implode( ', ', array_filter( $labels_x ) ) );
		}
		if ( function_exists( 'fge_request_pax_current' ) ) {
			fge_cc_fact( 'Personen aktuell', (string) fge_request_pax_current( $req ) );
		}
	}
	fge_cc_fact( 'Empfehlung', (string) get_post_meta( $req, '_fge_offer_recommendation', true ) );
	echo '</dl>';
	if ( $sent && function_exists( 'fge_offer_link' ) ) {
		echo '<p><a class="cc-btn" href="' . esc_url( fge_offer_link( $req ) ) . '" target="_blank" rel="noopener">Angebotsseite ansehen</a></p>';
	}
	echo '</section>';

	if ( 'accepted' === $offer && function_exists( 'fge_day_fields' ) ) {
		$filled = 0;
		foreach ( array_keys( fge_day_fields() ) as $k ) {
			if ( '' !== trim( (string) get_post_meta( $req, '_fge_' . $k, true ) ) ) {
				$filled++;
			}
		}
		$checks = [
			[ 'Eventtag-Felder ausgefüllt', $filled . ' von ' . count( fge_day_fields() ), $filled >= 2 ],
			[ 'Ablauf an den Kunden gesendet', (string) get_post_meta( $req, '_fge_day_plan_sent', true ), '' !== (string) get_post_meta( $req, '_fge_day_plan_sent', true ) ],
			[ 'Vortags-Info gesendet', (string) get_post_meta( $req, '_fge_day_info_sent', true ), '' !== (string) get_post_meta( $req, '_fge_day_info_sent', true ) ],
		];
		echo '<section class="cc-card"><h2>Eventtag</h2><ul class="cc-checklist">';
		foreach ( $checks as [ $label, $val, $ok ] ) {
			$note = is_numeric( $val ) && (int) $val > 100000 ? wp_date( 'd.m.Y H:i', (int) $val ) : $val;
			echo '<li class="' . ( $ok ? 'is-ok' : '' ) . '"><span class="cc-check-dot"></span><span>' . esc_html( $label ) . '</span><span class="cc-muted">' . esc_html( $ok || false !== strpos( $label, 'Felder' ) ? (string) $note : 'noch nicht' ) . '</span></li>';
		}
		echo '</ul><p class="cc-muted">Die Eventtag-Felder und die Knöpfe zum Senden stehen im Reiter Prozess.</p></section>';
	}
}

/** Reiter „Lexoffice und Tracking": Lesesicht der Felder aus dem WP-Admin. */
function fge_cc_request_lex_tab( int $req ): void {
	$m   = static fn( string $k ) => (string) get_post_meta( $req, '_fge_' . $k, true );
	$yes = static fn( string $v ) => '1' === $v ? 'ja' : 'nein';
	echo '<div class="cc-cols cc-cols--even">';
	echo '<section class="cc-card"><h2>Lexoffice</h2><dl class="cc-facts">';
	fge_cc_fact( 'Angebot erstellt', $yes( $m( 'lexoffice_offer_created' ) ) );
	fge_cc_fact( 'Angebotsnummer', $m( 'lexoffice_offer_number' ) );
	fge_cc_fact( 'Rechnung erstellt', $yes( $m( 'lexoffice_invoice_created' ) ) );
	fge_cc_fact( 'Rechnungsnummer', $m( 'lexoffice_invoice_number' ) );
	fge_cc_fact( 'Notiz', $m( 'lexoffice_note' ) );
	echo '</dl></section>';

	$sources = function_exists( 'fge_request_source_options' ) ? fge_request_source_options() : [];
	echo '<section class="cc-card"><h2>Quelle und Tracking</h2><dl class="cc-facts">';
	if ( function_exists( 'fge_pc_request_summary' ) ) {
		$pc = fge_pc_request_summary( $req );
		fge_cc_fact( 'Partnercode', '' !== $pc['code'] ? $pc['code'] . ' · ' . $pc['holder'] . ( $pc['percent'] > 0 ? ', ' . $pc['percent'] . ' % Rabatt' : '' ) : '' );
		fge_cc_fact( 'Provision', '' !== $pc['commission_status'] ? number_format_i18n( (float) $pc['commission_amount'], 2 ) . ' € (' . $pc['commission_status'] . ')' : '' );
	}
	fge_cc_fact( 'Quelle', $sources[ $m( 'source' ) ] ?? $m( 'source' ) );
	fge_cc_fact( 'UTM Source', $m( 'utm_source' ) );
	fge_cc_fact( 'UTM Medium', $m( 'utm_medium' ) );
	fge_cc_fact( 'UTM Campaign', $m( 'utm_campaign' ) );
	fge_cc_fact( 'Anfrage-Datum', $m( 'request_date' ) );
	fge_cc_fact( 'Letzte Statusänderung', $m( 'last_status_change' ) );
	fge_cc_fact( 'Kit', trim( $m( 'kit_status' ) . ' ' . $m( 'kit_tags' ) ) );
	fge_cc_fact( 'HubSpot', trim( $m( 'hubspot_status' ) . ' ' . $m( 'hubspot_contact_id' ) ) );
	echo '</dl></section>';
	echo '</div>';
	echo '<p class="cc-muted">Diese Felder werden derzeit im WordPress-Admin gepflegt: <a href="' . esc_url( get_edit_post_link( $req, 'raw' ) ) . '">Anfrage im WordPress-Admin öffnen</a>.</p>';
}

/**
 * Meldung nach einer Aktion.
 *
 * Aktionen, die einen genauen Grund kennen (Angebot neu auflegen, Katalog-
 * Vorschlag), legen ihn zusätzlich als Transient ab. Ohne diese Ausgabe wäre
 * er geschrieben worden und nie jemandem begegnet.
 */
function fge_cc_message( int $req = 0 ): void {
	$key = sanitize_key( wp_unslash( $_GET['msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$all = function_exists( 'fge_cc_messages' ) ? fge_cc_messages() : [];
	if ( '' === $key || ! isset( $all[ $key ] ) ) {
		return;
	}
	[ $tone, $text ] = $all[ $key ];

	$detail = $req > 0 ? (string) get_transient( 'fge_cc_err_' . $req ) : '';
	if ( '' !== $detail ) {
		delete_transient( 'fge_cc_err_' . $req );
		$text .= ' ' . $detail;
	}
	echo '<p class="cc-msg cc-msg--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</p>';
}

/** Die sieben Phasen als Leiste, die aktuelle hervorgehoben. */
function fge_cc_request_steps( int $req ): void {
	[ $num ] = fge_cc_phase( $req );
	$steps = [ 1 => 'Eingang', 2 => 'Termin', 3 => 'Angebot', 4 => 'Entscheidung', 5 => 'Vorbereitung', 6 => 'Eventtag', 7 => 'Nachlauf' ];
	echo '<ol class="cc-steps">';
	foreach ( $steps as $i => $label ) {
		$state = $i < $num ? 'done' : ( $i === $num ? 'on' : 'todo' );
		echo '<li class="cc-step is-' . esc_attr( $state ) . '"><span class="cc-step-n">' . (int) $i . '</span>' . esc_html( $label ) . '</li>';
	}
	echo '</ol>';
}

/**
 * Die aktuelle Phase mit genau den Feldern und Knöpfen, die jetzt zählen.
 * Alles andere bleibt eingeklappt oder im WordPress-Backend.
 */
function fge_cc_request_phase_panel( int $req ): void {
	[ $num, $label ] = fge_cc_phase( $req );
	$sent  = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	$offer = (string) get_post_meta( $req, '_fge_offer_status', true );

	// Positionen stehen seit dem Design vom 07.10.2026 im Reiter „Angebot und
	// Eventtag" und bleiben dort bis zur Buchung erreichbar.
	echo '<div class="cc-phase"><p class="cc-phase-kicker">Schritt ' . (int) $num . ' von 7</p><h2>' . esc_html( $label ) . '</h2>';

	if ( 'accepted' === $offer ) {
		fge_cc_phase_booked( $req );
	} elseif ( 'declined' === $offer ) {
		fge_cc_phase_offer_declined( $req );
	} elseif ( $sent ) {
		fge_cc_phase_offer_running( $req );
	} elseif ( function_exists( 'fge_rr_final_index' ) && fge_rr_final_index( $req ) > 0 ) {
		fge_cc_phase_offer_ready( $req );
	} else {
		fge_cc_phase_early( $req );
	}
	echo '</div>';
}

/** Eingang und Termin: was fehlt, um ein Angebot senden zu können. */
function fge_cc_phase_early( int $req ): void {
	$partner_id = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
	$wishes     = function_exists( 'fge_rr_wish_dates' ) ? fge_rr_wish_dates( $req ) : [];

	// Die Fakten stehen seit 28.09.2026 im Block „Angefragt" über der Pipeline.
	$has_pipeline = function_exists( 'fge_venues_get' ) && fge_venues_get( $req );
	if ( $partner_id <= 0 && ! $has_pipeline ) {
		echo '<p class="cc-hint">Es ist noch kein Platz in der Liste. Nimm oben Plätze auf (Nähe oder Suche), frag sie an und stelle aus den Zusagen das Angebot zusammen.</p>';
	}
	if ( function_exists( 'fge_cc_offer_options_panel' ) ) {
		fge_cc_offer_options_panel( $req );
	}
	// Der alte Weg „Termin bestätigen, Angebot geht raus" bleibt für Sonderfälle
	// (Partner-Event mit Portal-Abstimmung, telefonisch fixierter Einzeltermin).
	echo '<details class="cc-details"><summary>Sonderfall: Termin direkt bestätigen (ohne Optionen)</summary>';
	fge_cc_confirm_date_form( $req, $wishes );
	echo '</details>';
}

/**
 * Termin bestätigen, ohne den Umweg über das WordPress-Backend.
 *
 * Dieselben Sperren wie dort: nur einmal, nicht nach dem Angebotsversand.
 * Zusätzlich das Feld für einen telefonisch vereinbarten Termin, weil in der
 * Praxis oft keiner der drei Wunschtermine passt.
 */
function fge_cc_confirm_date_form( int $req, array $wishes ): void {
	$responses = function_exists( 'fge_rr_matrix' ) ? fge_rr_matrix( $req ) : [];
	$free_slot = function_exists( 'fge_cc_free_date_slot' ) ? fge_cc_free_date_slot( $req ) : 0;

	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_confirm_date">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_confirm_date_' . $req );

	echo '<p class="cc-kicker">Termin bestätigen</p>';
	if ( $wishes ) {
		echo '<div class="cc-datelist">';
		$first = true;
		foreach ( $wishes as $i => $label ) {
			echo '<label class="cc-inline"><input type="radio" name="date_index" value="' . (int) $i . '"' . ( $first ? ' checked' : '' ) . '> '
				. esc_html( (string) $label ) . '</label>';
			$first = false;
		}
		echo '</div>';
	} else {
		echo '<p class="cc-muted">Es sind keine Wunschtermine hinterlegt.</p>';
	}

	if ( $free_slot > 0 ) {
		echo '<label class="cc-field cc-field--wide"><span>Oder ein telefonisch vereinbarter Termin</span>';
		echo '<input type="text" name="free_date" placeholder="z. B. Mi, 30.09.2026"></label>';
	} else {
		echo '<p class="cc-muted">Alle drei Wunschtermin-Felder sind belegt. Ein abweichender Termin muss im WordPress-Backend eingetragen werden.</p>';
	}

	echo '<p><button type="submit" class="cc-btn cc-btn--primary" '
		. 'onclick="return confirm(\'Termin bestätigen? Der Kunde bekommt daraufhin das Angebot oder eine Termin-Bestätigung.\')">Termin bestätigen</button></p>';
	echo '</form>';

	fge_cc_action_preview( 'date_confirm', $req );

	if ( $responses ) {
		echo '<p class="cc-muted">Die Rückmeldungen der Platz-Kontakte stehen weiterhin im WordPress-Backend: '
			. '<a href="' . esc_url( get_edit_post_link( $req, 'raw' ) ) . '">Abstimmungsmatrix öffnen</a></p>';
	}
}

/** Termin steht, Angebot kann raus. */
function fge_cc_phase_offer_ready( int $req ): void {
	$idx    = fge_rr_final_index( $req );
	$wishes = function_exists( 'fge_rr_wish_dates' ) ? fge_rr_wish_dates( $req ) : [];
	$priced = ! function_exists( 'fge_offer_is_priced' ) || fge_offer_is_priced( $req );

	echo '<dl class="cc-facts">';
	fge_cc_fact( 'Bestätigter Termin', (string) ( $wishes[ $idx ] ?? 'steht fest' ) );
	fge_cc_fact( 'Preis hinterlegt', $priced ? 'ja' : 'nein' );
	echo '</dl>';

	if ( ! $priced ) {
		echo '<p class="cc-hint cc-hint--warn">Ohne Preis kein Angebot. Erst im Reiter „Angebot und Eventtag“ bepreisen, sonst ginge „Auf Anfrage" verbindlich buchbar raus.</p>';
		echo '<p><a class="cc-btn" href="' . esc_url( fge_cc_request_url( $req ) . '#angebot' ) . '">Positionen bepreisen</a></p>';
		return;
	}
	fge_cc_button( 'fge_cc_offer_send', $req, 'Angebot jetzt senden', [
		'class'   => 'cc-btn cc-btn--primary',
		'confirm' => 'Angebot mit PDF an den Kunden senden?',
	] );
	fge_cc_action_preview( 'offer_send', $req );
}

/**
 * Der Kunde hat abgesagt: Platz freigeben, neu auflegen oder als verloren ablegen.
 * Bis 28.09.2026 stand hier weiter „Angebot läuft" und der gewählte Platz erfuhr nichts.
 */
function fge_cc_phase_offer_declined( int $req ): void {
	$query = (string) get_post_meta( $req, '_fge_offer_query', true );
	echo '<dl class="cc-facts">';
	fge_cc_fact( 'Kunde', 'hat das Angebot abgelehnt' );
	fge_cc_fact( 'Nachricht des Kunden', $query );
	echo '</dl>';
	echo '<p class="cc-hint">Drei Wege: Positionen anpassen und als neue Fassung senden, den Platz über die Absage informieren, oder den Vorgang als verloren ablegen.</p>';
	echo '<div class="cc-venue-actions">';
	if ( function_exists( 'fge_offer_relaunch_blocker' ) && '' === fge_offer_relaunch_blocker( $req ) ) {
		fge_cc_button( 'fge_cc_offer_relaunch', $req, 'Zurückziehen und neu auflegen', [
			'confirm' => 'Der Kunde bekommt eine neue Fassung mit den aktuellen Positionen. Fortfahren?',
		] );
	}
	$chosen = null;
	if ( function_exists( 'fge_venues_get' ) ) {
		foreach ( fge_venues_get( $req ) as $v ) {
			if ( 'gewaehlt' === (string) $v['status'] ) {
				$chosen = $v;
			}
		}
	}
	if ( '1' === (string) get_post_meta( $req, '_fge_venue_released', true ) ) {
		echo '<p class="cc-done">Platz informiert, Termin freigegeben.</p>';
	} elseif ( $chosen ) {
		fge_cc_button( 'fge_cc_venue_release', $req, 'Platz informieren: Termin wird frei', [
			'fields'  => [ 'venue_id' => (int) $chosen['id'] ],
			'confirm' => get_the_title( (int) $chosen['partner_id'] ) . ' per Mail informieren, dass der Kunde abgesagt hat?',
		] );
		fge_cc_action_preview( 'venue_release', $req );
	}
	fge_cc_button( 'fge_cc_status', $req, 'Als verloren ablegen', [
		'fields'  => [ 'status' => 'verloren' ],
		'confirm' => 'Vorgang als verloren ablegen?',
	] );
	echo '</div>';
}

/** Angebot läuft, der Kunde entscheidet. */
function fge_cc_phase_offer_running( int $req ): void {
	$deadline = (int) get_post_meta( $req, '_fge_offer_deadline', true );
	$query    = (string) get_post_meta( $req, '_fge_offer_query', true );

	echo '<dl class="cc-facts">';
	fge_cc_fact( 'Frist', $deadline > 0 ? wp_date( 'd.m.Y', $deadline ) . ( time() > $deadline ? ' (abgelaufen)' : '' ) : 'ohne Frist' );
	fge_cc_fact( 'Rückfrage des Kunden', $query );
	echo '</dl>';
	echo '<p class="cc-hint">Der Ball liegt beim Kunden. Angenommen oder abgelehnt wird auf der Angebotsseite, das löst alle weiteren Mails aus.</p>';
	echo '<div class="cc-venue-actions">';
	if ( function_exists( 'fge_offer_link' ) ) {
		echo '<a class="cc-btn" href="' . esc_url( fge_offer_link( $req ) ) . '" target="_blank" rel="noopener">Angebotsseite ansehen</a>';
	}
	// Positionen ändern und neu senden, ohne eine neue Anfrage anzulegen.
	if ( function_exists( 'fge_offer_relaunch_blocker' ) && '' === fge_offer_relaunch_blocker( $req ) ) {
		fge_cc_button( 'fge_cc_offer_relaunch', $req, 'Zurückziehen und neu auflegen', [
			'confirm' => 'Das laufende Angebot wird ungültig und der Kunde bekommt eine neue Fassung mit den aktuellen Positionen. Fortfahren?',
		] );
	}
	echo '</div>';
	echo '<p class="cc-muted">Neu auflegen nimmt die Positionen, wie sie jetzt in Schritt 2 stehen. Die alte Fassung bleibt im Archiv, die Vorgangsnummer bleibt gleich.</p>';

	// Frühere Fassungen, falls es welche gibt.
	if ( function_exists( 'fge_offer_archive' ) ) {
		$old = fge_offer_archive( $req );
		if ( $old ) {
			echo '<details class="cc-details"><summary>' . (int) count( $old ) . ' frühere '
				. ( 1 === count( $old ) ? 'Fassung' : 'Fassungen' ) . '</summary>';
			foreach ( $old as $o ) {
				$snap = (array) ( $o['snapshot'] ?? [] );
				$tot  = function_exists( 'fge_offer_totals' ) ? fge_offer_totals( $snap, null ) : [ 'net' => 0 ];
				echo '<p class="cc-contact-line">Fassung ' . (int) ( $o['version'] ?? 0 ) . ': '
					. esc_html( number_format_i18n( (float) ( $tot['net'] ?? 0 ), 2 ) ) . ' € netto, gesendet '
					. esc_html( (int) ( $o['sent_at'] ?? 0 ) > 0 ? wp_date( 'd.m.Y', (int) $o['sent_at'] ) : 'unbekannt' )
					. ', zurückgezogen ' . esc_html( wp_date( 'd.m.Y', (int) strtotime( (string) ( $o['retired'] ?? '' ) ) ) ) . '</p>';
			}
			echo '</details>';
		}
	}

	fge_cc_action_preview( 'offer_accept', $req );
}

/** Gebucht: Eventtag ausfüllen und die beiden Info-Mails. */
function fge_cc_phase_booked( int $req ): void {
	$v         = fge_day_values( $req );
	$plan_sent = (string) get_post_meta( $req, '_fge_day_plan_sent', true );
	$info_sent = (string) get_post_meta( $req, '_fge_day_info_sent', true );

	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_day_save">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_day_save_' . $req );
	echo '<div class="cc-fields">';
	foreach ( fge_day_fields() as $k => [ $flabel, $type, $ph ] ) {
		$raw = trim( (string) get_post_meta( $req, '_fge_' . $k, true ) );
		$hint = ( '' === $raw && '' !== $v[ $k ] ) ? 'Vorbelegt: ' . $v[ $k ] : $ph;
		echo '<label class="cc-field' . ( 'textarea' === $type ? ' cc-field--wide' : '' ) . '">';
		echo '<span>' . esc_html( $flabel ) . '</span>';
		if ( 'textarea' === $type ) {
			echo '<textarea name="fge_' . esc_attr( $k ) . '" rows="2" placeholder="' . esc_attr( $hint ) . '">' . esc_textarea( $raw ) . '</textarea>';
		} else {
			echo '<input type="text" name="fge_' . esc_attr( $k ) . '" value="' . esc_attr( $raw ) . '" placeholder="' . esc_attr( $hint ) . '">';
		}
		echo '</label>';
	}
	echo '</div>';
	echo '<p><button type="submit" class="cc-btn cc-btn--primary">Speichern</button>';
	echo '<span class="cc-muted"> Sobald Startzeit und Treffpunkt stehen, geht die Ablauf-Info einmalig an den Kunden.</span></p>';
	echo '</form>';

	echo '<div class="cc-actions">';
	echo '<div class="cc-action">';
	if ( '' !== $plan_sent ) {
		echo '<p class="cc-done">Ablauf-Info gesendet am ' . esc_html( wp_date( 'd.m.Y H:i', (int) $plan_sent ) ) . '</p>';
	}
	fge_cc_button( 'fge_cc_day_plan', $req, '' !== $plan_sent ? 'Ablauf-Info erneut senden' : 'Ablauf-Info senden', [
		'confirm' => 'Ablauf-Info jetzt an den Kunden senden?',
	] );
	fge_cc_action_preview( 'day_plan', $req );
	echo '</div>';

	echo '<div class="cc-action">';
	if ( '' !== $info_sent ) {
		echo '<p class="cc-done">Vortags-Info gesendet am ' . esc_html( wp_date( 'd.m.Y H:i', (int) $info_sent ) ) . '</p>';
	}
	fge_cc_button( 'fge_cc_day_info', $req, '' !== $info_sent ? 'Vortags-Info erneut senden' : 'Vortags-Info senden', [
		'confirm' => 'Vortags-Info an Kunde, Platz und events@ senden?',
	] );
	fge_cc_action_preview( 'day_info', $req );
	echo '</div>';
	echo '</div>';

	// Nachlauf-Knöpfe, sobald der Termin vorbei ist.
	$date = fge_cc_event_date( $req );
	if ( $date > 0 && $date < fge_cc_today() ) {
		echo '<div class="cc-actions cc-actions--end">';
		foreach ( fge_cc_status_actions() as $slug => $slabel ) {
			fge_cc_button( 'fge_cc_status', $req, $slabel, [
				'fields'  => [ 'status' => $slug ],
				'confirm' => 'Status auf „' . $slabel . '" setzen?',
			] );
		}
		echo '</div>';
	}

	fge_cc_catalog_block( $req );
}

/**
 * Aus diesem Event ein Katalog-Event machen.
 *
 * Der Hebel, aus dem eine einmalige Handarbeit ein wiederverkäufliches Produkt
 * wird. Erscheint, sobald gebucht ist, und zeigt danach die Antwort des Platzes.
 */
function fge_cc_catalog_block( int $req ): void {
	if ( ! function_exists( 'fge_catalog_blocker' ) ) {
		return;
	}
	$answer  = fge_catalog_answer( $req );
	$asked   = (string) get_post_meta( $req, '_fge_catalog_asked_at', true );
	$blocker = fge_catalog_blocker( $req );

	echo '<div class="cc-catalog">';
	echo '<p class="cc-kicker">Aus diesem Event ein Angebot machen</p>';

	if ( 'ja' === $answer ) {
		$eid = (int) get_post_meta( $req, '_fge_catalog_event_id', true );
		echo '<p class="cc-done">Der Platz möchte das Event dauerhaft anbieten.</p>';
		if ( $eid > 0 ) {
			echo '<p><a class="cc-btn" href="' . esc_url( get_edit_post_link( $eid, 'raw' ) ) . '">Entwurf prüfen und freigeben</a></p>';
		}
	} elseif ( 'nein' === $answer ) {
		$note = (string) get_post_meta( $req, '_fge_catalog_note', true );
		echo '<p class="cc-muted">Der Platz möchte das nicht dauerhaft anbieten.' . ( '' !== $note ? ' „' . esc_html( $note ) . '"' : '' ) . '</p>';
	} elseif ( '' !== $blocker ) {
		echo '<p class="cc-muted">' . esc_html( $blocker ) . '</p>';
	} else {
		echo '<p class="cc-muted">Fragt den Platz, ob er genau dieses Paket dauerhaft auf firmengolf.app anbieten will. Bei Ja entsteht ein Entwurf in seinem Portal, den du freigibst. Die nächste Anfrage darauf läuft dann ohne Telefonat.</p>';
		if ( '' !== $asked ) {
			echo '<p class="cc-muted">Gefragt am ' . esc_html( wp_date( 'd.m.Y', (int) strtotime( $asked ) ) ) . ', noch keine Antwort.</p>';
		}
		fge_cc_button( 'fge_cc_catalog', $req, '' !== $asked ? 'Erneut fragen' : 'Platz fragen', [
			'class'   => 'cc-btn',
			'confirm' => 'Dem Platz vorschlagen, dieses Event dauerhaft anzubieten?',
		] );
	}
	echo '</div>';
}

/** Ein Fakt in der Definitionsliste, leere Werte fallen weg. */
function fge_cc_fact( string $label, string $value ): void {
	if ( '' === trim( $value ) ) {
		return;
	}
	echo '<div class="cc-fact"><dt>' . esc_html( $label ) . '</dt><dd>' . nl2br( esc_html( $value ) ) . '</dd></div>';
}

/** Zeitleiste plus Eingabe für Telefonnotizen. */
function fge_cc_request_timeline( int $req ): void {
	echo '<section class="cc-card"><h2>Verlauf</h2>';

	echo '<form class="cc-form cc-noteform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_note">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_note_' . $req );
	echo '<textarea name="note" rows="2" placeholder="Was wurde besprochen? Telefonnotiz oder Vermerk"></textarea>';
	echo '<div class="cc-noteform-foot">';
	echo '<label class="cc-inline"><input type="radio" name="note_type" value="call" checked> Telefonat</label>';
	echo '<label class="cc-inline"><input type="radio" name="note_type" value="note"> Notiz</label>';
	echo '<button type="submit" class="cc-btn">Festhalten</button>';
	echo '</div></form>';

	$items = function_exists( 'fge_activity_for_request' ) ? fge_activity_for_request( $req, 40 ) : [];
	if ( ! $items ) {
		fge_cc_empty( 'Noch nichts festgehalten.' );
		echo '</section>';
		return;
	}
	$labels = function_exists( 'fge_activity_types' ) ? fge_activity_types() : [];
	echo '<ul class="cc-timeline">';
	foreach ( $items as $i => $a ) {
		if ( 10 === $i ) {
			// Ältere Einträge eingeklappt, sonst wird die Seitenspalte endlos.
			echo '</ul><details class="cc-details cc-tl-more"><summary>Älteren Verlauf zeigen (' . (int) ( count( $items ) - 10 ) . ')</summary><ul class="cc-timeline">';
		}
		echo '<li class="cc-tl cc-tl--' . esc_attr( (string) $a['type'] ) . '">';
		echo '<span class="cc-tl-type">' . esc_html( $labels[ $a['type'] ] ?? (string) $a['type'] ) . '</span>';
		echo '<span class="cc-tl-text">' . nl2br( esc_html( (string) $a['text'] ) ) . '</span>';
		echo '<span class="cc-tl-time">' . esc_html( fge_cc_ago( (string) $a['created_at'] ) ) . '</span>';
		echo '</li>';
	}
	echo '</ul>' . ( count( $items ) > 10 ? '</details>' : '' ) . '</section>';
}

/** Vorgang für eine Weile aus der Arbeitsliste nehmen. */
function fge_cc_request_snooze( int $req ): void {
	$until = (int) get_post_meta( $req, '_fge_cc_snooze', true );
	echo '<section class="cc-card"><h2>Schlummern</h2>';
	if ( $until > time() ) {
		echo '<p class="cc-muted">Taucht am ' . esc_html( wp_date( 'd.m.Y', $until ) ) . ' wieder auf.</p>';
		fge_cc_button( 'fge_cc_snooze', $req, 'Jetzt wieder zeigen', [ 'fields' => [ 'days' => 0 ] ] );
	} else {
		echo '<p class="cc-muted">Aus der Arbeitsliste nehmen, ohne den Vorgang zu schließen.</p>';
		echo '<div class="cc-actions">';
		foreach ( [ 3 => '3 Tage', 7 => '1 Woche', 30 => '1 Monat' ] as $d => $l ) {
			fge_cc_button( 'fge_cc_snooze', $req, $l, [ 'fields' => [ 'days' => $d ] ] );
		}
		echo '</div>';
	}
	echo '</section>';
}

function fge_cc_request_header( int $req ): void {
	[ , $phase ] = fge_cc_phase( $req );
	$net  = function_exists( 'fge_cc_offer_net' ) ? fge_cc_offer_net( $req ) : 0.0;
	$data = fge_get_request_email_data( $req );
	$name = trim( (string) $data['first_name'] . ' ' . (string) $data['last_name'] );
	$f    = fge_cc_request_fact_data( $req );

	echo '<header class="cc-head">';
	echo '<div class="cc-head-text">';
	echo '<span class="cc-head-kicker">' . esc_html( fge_request_number( $req ) . ' · eingegangen ' . get_the_date( 'd.m.Y', $req ) ) . '</span>';
	echo '<h1>' . esc_html( (string) get_post_meta( $req, '_fge_company_name', true ) ?: ( $name ?: 'Ohne Firma' ) ) . '</h1>';
	echo '<div class="cc-head-meta">';
	echo fge_cc_pill( $phase, fge_cc_phase_tone( $phase ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	$date = fge_cc_event_date( $req );
	if ( $date > 0 ) {
		echo '<span class="cc-head-date">' . esc_html( fge_cc_date( $date ) ) . '</span>';
	}
	if ( $net > 0 ) {
		echo '<span class="cc-head-sum">' . esc_html( number_format_i18n( $net, 2 ) ) . ' € netto</span>';
	}
	echo '<a class="cc-btn cc-btn--text" href="' . esc_url( get_edit_post_link( $req, 'raw' ) ) . '">Im WordPress öffnen</a>';
	echo '</div></div>';

	echo '<div class="cc-head-contact"><span class="cc-head-contact-name"><strong>' . esc_html( $name ?: 'ohne Namen' ) . '</strong><span>Kunde</span></span>';
	if ( '' !== trim( (string) $data['phone'] ) ) {
		echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', (string) $data['phone'] ) ) . '">' . esc_html( (string) $data['phone'] ) . '</a>';
	}
	if ( '' !== trim( (string) $data['contact_email'] ) ) {
		echo '<a href="mailto:' . esc_attr( (string) $data['contact_email'] ) . '">' . esc_html( (string) $data['contact_email'] ) . '</a>';
	}
	echo '</div>';
	echo '</header>';

	$chips = [
		'Event'         => $f['event'],
		'Wunschtermine' => $f['dates'],
		'Gruppe'        => trim( ( $f['pax'] > 0 ? $f['pax'] . ' Personen' : '' ) . ( '' !== $f['level'] ? ', ' . $f['level'] : '' ), ', ' ),
		'Start'         => $f['start'],
		'Wünsche'       => implode( ', ', array_merge( $f['platz'], $f['fg'] ) ),
		'Eventseite'    => $f['price_short'],
	];
	echo '<div class="cc-chips">';
	foreach ( $chips as $k => $v ) {
		if ( '' !== trim( (string) $v ) ) {
			echo '<span class="cc-factchip"><span>' . esc_html( $k ) . '</span><strong>' . esc_html( (string) $v ) . '</strong></span>';
		}
	}
	echo '</div>';
}

/** Was angefragt wurde, einmal berechnet für Kopf und Daten-Reiter. */
function fge_cc_request_fact_data( int $req ): array {
	static $cache = [];
	if ( isset( $cache[ $req ] ) ) {
		return $cache[ $req ];
	}
	$event_id = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
	$pax      = (int) get_post_meta( $req, '_fge_expected_participants', true );
	$dates    = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$d_txt    = [];
	foreach ( $dates as $i => $label ) {
		$d_txt[] = $i . '. ' . $label;
	}

	// Leistungen: Wunschliste aus dem Event-Dialog plus Häkchen aus dem Wizard.
	$g     = function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $req ) : [ 'platz' => [], 'firmengolf' => [] ];
	$wants = fge_catalog_wish_labels();
	$platz = array_values( array_filter( array_map( 'strval', (array) ( $g['platz'] ?? [] ) ) ) );
	$fg    = array_values( array_filter( array_map( 'strval', (array) ( $g['firmengolf'] ?? [] ) ) ) );
	foreach ( $wants as $k => $l ) {
		if ( '1' === (string) get_post_meta( $req, '_fge_' . $k, true ) && ! in_array( $l, $platz, true ) && ! in_array( $l, $fg, true ) ) {
			$platz[] = $l;
		}
	}

	// Platzhalter-Preis zur Orientierung: was der Kunde auf der Eventseite gesehen hat.
	$price_txt   = '';
	$price_short = '';
	if ( $event_id > 0 && 'firmengolf_event' === get_post_type( $event_id ) && function_exists( 'fge_event_pricing_for_pax' ) ) {
		$p     = fge_event_pricing_for_pax( $event_id, $pax );
		$gross = (float) ( $p['gross'] ?? 0 );
		if ( $gross > 0 ) {
			$is_pp       = 'pro Person' === (string) ( $p['unit'] ?? '' );
			$price_short = number_format_i18n( $gross, 0 ) . ' € ' . ( $is_pp ? 'p.P.' : 'pauschal' );
			$price_txt   = number_format_i18n( $gross, 2 ) . ' € netto ' . ( $is_pp ? 'p.P.' : 'pauschal' )
				. ( ! empty( $p['box_count'] ) ? ' (' . (int) $p['box_count'] . ' Box' . ( (int) $p['box_count'] > 1 ? 'en' : '' ) . ' × ' . rtrim( rtrim( number_format( (float) $p['box_hours'], 1, ',', '' ), '0' ), ',' ) . ' Std. à ' . number_format_i18n( (float) $p['box_gross'], 0 ) . ' €)' : '' )
				. ( $is_pp && $pax > 0 ? ', ' . number_format_i18n( $gross * $pax, 2 ) . ' € bei ' . $pax . ' Personen' : '' )
				. ( ! function_exists( 'fge_offer_event_is_placeholder' ) || fge_offer_event_is_placeholder( $req ) ? ' (Platzhalter, nur Orientierung)' : '' );
		}
	}

	$cache[ $req ] = [
		'event'       => $event_id > 0 ? get_the_title( $event_id ) : (string) get_post_meta( $req, '_fge_event_type', true ),
		'dates'       => implode( ' · ', $d_txt ),
		'pax'         => $pax,
		'level'       => (string) get_post_meta( $req, '_fge_group_experience', true ),
		'start'       => trim( (string) get_post_meta( $req, '_fge_start_time', true ) ) ?: trim( (string) get_post_meta( $req, '_fge_preferred_time', true ) ),
		'platz'       => $platz,
		'fg'          => $fg,
		'extra'       => trim( (string) get_post_meta( $req, '_fge_additional_wishes', true ) ),
		'price'       => $price_txt,
		'price_short' => $price_short,
	];
	return $cache[ $req ];
}

/**
 * Was wurde angefragt: in jeder Phase sichtbar, damit Preisanfrage, Kalkulation und
 * Angebot immer gegen die Wünsche des Kunden geprüft werden (Julius, 28.09.2026).
 */
function fge_cc_request_facts( int $req ): void {
	$f = fge_cc_request_fact_data( $req );
	echo '<section class="cc-card cc-requested"><h2>Angefragt</h2><dl class="cc-facts">';
	fge_cc_fact( 'Event', $f['event'] );
	fge_cc_fact( 'Wunschtermine', '' !== $f['dates'] ? $f['dates'] : 'keine angegeben' );
	fge_cc_fact( 'Teilnehmer', $f['pax'] > 0 ? (string) $f['pax'] : '' );
	fge_cc_fact( 'Niveau', $f['level'] );
	fge_cc_fact( 'Startzeit', $f['start'] );
	fge_cc_fact( 'Leistungen am Platz', implode( ', ', $f['platz'] ) );
	fge_cc_fact( 'Über Firmengolf', implode( ', ', $f['fg'] ) );
	fge_cc_fact( 'Weitere Wünsche', $f['extra'] );
	fge_cc_fact( 'Preis auf der Eventseite', $f['price'] );
	fge_cc_fact( 'Budget', (string) get_post_meta( $req, '_fge_budget_range', true ) );
	fge_cc_fact( 'Nachricht', (string) get_post_meta( $req, '_fge_message', true ) );
	echo '</dl></section>';
}

/** Die beiden Kontaktkarten: Kunde und Platz, Telefon zum Antippen. */
function fge_cc_request_contacts( int $req ): void {
	$data = fge_get_request_email_data( $req );
	echo '<div class="cc-contacts">';

	// Kunde
	$name = trim( (string) $data['first_name'] . ' ' . (string) $data['last_name'] );
	echo '<section class="cc-card cc-contact">';
	echo '<p class="cc-kicker">Kunde</p>';
	echo '<p class="cc-contact-name">' . esc_html( $name ?: ( $data['company_name'] ?: 'ohne Namen' ) ) . '</p>';
	echo '<p class="cc-muted">' . esc_html( (string) $data['company_name'] ) . '</p>';
	fge_cc_contact_links( (string) $data['phone'], (string) $data['contact_email'] );
	echo '</section>';

	// Platz
	$pid = (int) $data['partner_id'];
	echo '<section class="cc-card cc-contact">';
	echo '<p class="cc-kicker">Golfplatz</p>';
	if ( $pid <= 0 ) {
		echo '<p class="cc-contact-name">Kein Platz zugeordnet</p>';
		echo '<p class="cc-warn">Erst zuordnen, dann laufen Buchungs- und Vortagsmails.</p>';
	} else {
		echo '<p class="cc-contact-name">' . esc_html( (string) $data['partner_title'] ) . '</p>';
		$cname = (string) get_post_meta( $pid, '_fge_event_contact_name', true ) ?: (string) get_post_meta( $pid, '_fge_main_contact_name', true );
		$cphon = (string) get_post_meta( $pid, '_fge_event_contact_phone', true ) ?: (string) get_post_meta( $pid, '_fge_main_contact_phone', true );
		if ( '' !== $cname ) {
			echo '<p class="cc-muted">' . esc_html( $cname ) . '</p>';
		}
		fge_cc_contact_links( $cphon, (string) $data['partner_email'] );
		if ( '' === (string) $data['partner_email'] ) {
			echo '<p class="cc-warn">Keine Kontaktmail hinterlegt, Buchungs- und Vortagsinfo gehen nicht raus.</p>';
		}
		// Weitere Ansprechpartner des Platzes
		$more = function_exists( 'fge_contacts_get' ) ? fge_contacts_get( $pid ) : [];
		if ( count( $more ) > 1 ) {
			echo '<details class="cc-details"><summary>Alle Ansprechpartner (' . (int) count( $more ) . ')</summary>';
			foreach ( $more as $c ) {
				echo '<p class="cc-contact-line"><strong>' . esc_html( (string) $c['name'] ) . '</strong> '
					. '<span class="cc-muted">' . esc_html( (string) $c['role'] ) . '</span><br>'
					. esc_html( (string) $c['email'] ) . '</p>';
			}
			echo '</details>';
		}
	}
	echo '</section>';
	echo '</div>';
}

function fge_cc_contact_links( string $phone, string $email ): void {
	echo '<p class="cc-contact-links">';
	if ( '' !== trim( $phone ) ) {
		echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
	}
	if ( '' !== trim( $email ) ) {
		echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
	}
	echo '</p>';
}

function fge_cc_request_tasks( int $req ): void {
	$tasks = fge_cc_tasks( $req );
	$own   = function_exists( 'fge_cc_own_tasks' ) ? fge_cc_own_tasks( $req ) : [];
	$own_ids = array_column( $own, 'id' );

	echo '<section class="cc-card"><h2>Offen</h2>';
	if ( ! $tasks ) {
		fge_cc_empty( 'Nichts offen.' );
	} else {
		echo '<ul class="cc-tasks">';
		foreach ( $tasks as $t ) {
			// Eigene Aufgaben bekommen einen Haken, abgeleitete verschwinden von
			// selbst, sobald der Zustand stimmt.
			$id = str_starts_with( (string) $t['key'], 'own_' ) ? substr( (string) $t['key'], 4 ) : '';
			echo '<li class="cc-task cc-task--' . esc_attr( $t['urgency'] ) . '">'
				. '<span class="cc-task-who">' . ( 'me' === $t['who'] ? 'ich' : 'andere' ) . '</span>'
				. '<span>' . esc_html( $t['text'] ) . '</span>';
			if ( '' !== $id && in_array( $id, $own_ids, true ) ) {
				fge_cc_button( 'fge_cc_task_done', $req, 'Erledigt', [
					'class'  => 'cc-btn cc-btn--tiny',
					'fields' => [ 'task_id' => $id ],
				] );
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	// Eigene Aufgabe notieren.
	echo '<form class="cc-form cc-taskform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_task_add">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_task_add_' . $req );
	echo '<input type="text" name="task" placeholder="Eigene Aufgabe, z. B. Pro anrufen" aria-label="Eigene Aufgabe">';
	echo '<label class="cc-inline"><input type="checkbox" name="urgent" value="1"> dringend</label>';
	echo '<button type="submit" class="cc-btn">Notieren</button>';
	echo '</form>';
	echo '</section>';
}

function fge_cc_request_mails( int $req ): void {
	$log = function_exists( 'fge_mail_log_for_request' ) ? fge_mail_log_for_request( $req, 20 ) : [];
	echo '<section class="cc-card"><h2>Mails zu dieser Anfrage</h2>';
	if ( ! $log ) {
		fge_cc_empty( 'Für diese Anfrage ist noch nichts protokolliert.' );
		echo '</section>';
		return;
	}
	foreach ( $log as $m ) {
		$bad = 'sent' !== $m['status'];
		echo '<p class="cc-feed-item' . ( $bad ? ' cc-feed-item--bad' : '' ) . '">';
		echo '<strong>' . esc_html( function_exists( 'fge_mail_label' ) && $m['mail_key'] ? fge_mail_label( (string) $m['mail_key'] ) : (string) $m['subject'] ) . '</strong><br>';
		echo '<span class="cc-muted">' . esc_html( (string) $m['recipient'] ) . ' · ' . esc_html( fge_cc_ago( (string) $m['sent_at'] ) );
		echo $bad ? ' · nicht zugestellt' : '';
		echo '</span></p>';
	}
	echo '</section>';
}
