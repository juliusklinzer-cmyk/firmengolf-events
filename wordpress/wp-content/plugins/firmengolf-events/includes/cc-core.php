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
 * Seiten des Control Centers: slug => [Label, Menüpunkt].
 *
 * Menü nach „Control Center Redesign“ (Julius, 16.10.2026): sieben flache
 * Punkte ohne Gruppen. Events und Verzeichnis sind Unterseiten von „Partner
 * und Events“, Angebote laufen in den Anfragen, Leads unter Kunden,
 * Rechnungen unter Finanzen, Nutzer und Postausgang unter Einstellungen.
 */
function fge_cc_pages(): array {
	return [
		'dashboard'     => [ 'Heute', 'dashboard' ],
		'anfragen'      => [ 'Anfragen', 'anfragen' ],
		'partner'       => [ 'Partner und Events', 'partner' ],
		'kunden'        => [ 'Kunden', 'kunden' ],
		'partnercodes'  => [ 'Partnercodes', 'partnercodes' ],
		'finanzen'      => [ 'Finanzen', 'finanzen' ],
		'einstellungen' => [ 'Einstellungen', 'einstellungen' ],
		// Unterseiten, nicht im Menü.
		'events'        => [ 'Events', 'partner' ],
		'verzeichnis'   => [ 'Verzeichnis', 'partner' ],
	];
}

/** Menüpunkt, unter dem eine Seite leuchtet. */
function fge_cc_nav_parent( string $page ): string {
	return fge_cc_pages()[ $page ][1] ?? 'dashboard';
}

/**
 * Alte Adressen, damit Lesezeichen und Mail-Links weiter gehen:
 * slug => [Ziel, feste Parameter].
 */
function fge_cc_legacy_pages(): array {
	return [
		'plaetze'       => [ 'verzeichnis', [] ],
		'geld'          => [ 'finanzen', [] ],
		'rechnungen'    => [ 'finanzen', [] ],
		'angebote'      => [ 'anfragen', [ 'tab' => 'kunde' ] ],
		'leads'         => [ 'kunden', [ 'ansicht' => 'leads' ] ],
		'nutzer'        => [ 'einstellungen', [ 'bereich' => 'nutzer' ] ],
		'postausgang'   => [ 'einstellungen', [ 'bereich' => 'postausgang' ] ],
		'dienstleister' => [ 'partner', [ 'art' => 'dl' ] ],
		'kalender'      => [ 'einstellungen', [ 'bereich' => 'verbindungen' ] ],
		'aufgaben'      => [ 'dashboard', [] ],
	];
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
		[ $to, $fixed ] = $legacy[ $page ];
		$args = array_map( 'sanitize_text_field', wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $args['fge_cc'] );
		if ( 'dienstleister' === $page && ! empty( $args['edit'] ) ) {
			$fixed = [ 'dl' => absint( $args['edit'] ) ]; // Bearbeiten eines Dienstleisters = seine Detailseite
			unset( $args['edit'] );
		}
		if ( 'rechnungen' === $page || 'angebote' === $page ) {
			unset( $args['tab'], $args['filter'] ); // alte Tabs gibt es dort nicht mehr
		}
		wp_safe_redirect( fge_cc_url( $to, $fixed + $args ), 302 );
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
	send_frame_options_header(); // nur im eigenen Fenster, kein Einbetten von außen
	$pages  = fge_cc_pages();
	$title  = $pages[ $page ][0];
	$base   = plugins_url( '', FGE_DIR . 'firmengolf-events.php' );
	$req    = 'anfragen' === $page ? absint( $_GET['req'] ?? 0 ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$routes = [
		'dashboard'     => 'fge_cc_page_dashboard',
		'partner'       => 'fge_cc_page_partner_area',
		'events'        => 'fge_cc_page_events',
		'verzeichnis'   => 'fge_cc_page_partners',
		'kunden'        => 'fge_cc_page_customers',
		'partnercodes'  => 'fge_cc_page_partnercodes',
		'finanzen'      => 'fge_cc_page_money',
		'einstellungen' => 'fge_cc_page_settings',
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
		$wide = $req > 0 || ( 'partner' === $page && ( isset( $_GET['pid'] ) || isset( $_GET['dl'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="cc-body' . ( $wide ? ' cc-body--wide' : '' ) . '">';
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
	if ( function_exists( 'fge_cc_invoices_overdue' ) ) {
		$badges['finanzen'] = count( fge_cc_invoices_overdue() );
	}
	if ( function_exists( 'fge_cc_bucket_counts' ) ) {
		$badges['anfragen'] = fge_cc_bucket_counts()['offen'];
	}

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
	$badges['events']  = $count_meta( 'firmengolf_event', '_fge_event_status', [ 'zur_pruefung', 'aenderung_in_pruefung' ] );
	// „Partner und Events“ zählt alle offenen Freigaben, wie im Entwurf.
	$badges['partner'] = $count_meta( 'firmengolf_partner', '_fge_partner_status', 'in_pruefung' ) + $badges['events'];

	$failed = function_exists( 'fge_mail_log_recent' ) ? fge_mail_log_recent( 50, 'failed' ) : [];
	$since  = time() - 7 * DAY_IN_SECONDS;
	$badges['postausgang'] = count( array_filter( $failed, static fn( $f ) => strtotime( (string) ( $f['sent_at'] ?? '' ) ) >= $since ) );
	return $badges;
}

function fge_cc_sidebar( string $current ): void {
	$badges = fge_cc_nav_badges();
	$user   = wp_get_current_user();
	$name   = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
	$ini    = mb_strtoupper( implode( '', array_map( static fn( $w ) => mb_substr( $w, 0, 1 ), array_slice( preg_split( '/\s+/', trim( $name ) ) ?: [], 0, 2 ) ) ) );
	$logo   = plugins_url( 'assets/logo/firmengolf-logo.svg', FGE_DIR . 'firmengolf-events.php' );
	$on     = fge_cc_nav_parent( $current );

	echo '<aside class="cc-side">';
	echo '<a class="cc-brand" href="' . esc_url( fge_cc_url() ) . '"><img src="' . esc_url( $logo ) . '" alt="Firmengolf" width="130" height="26"><span>Control Center</span></a>';

	echo '<div class="cc-gsearch" data-cc-gsearch data-endpoint="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'fge_cc_search' ) ) . '">';
	echo '<input type="search" placeholder="Firma, Nummer, Partner" aria-label="Alles durchsuchen" autocomplete="off">';
	echo '<div class="cc-gsearch-list" hidden></div>';
	echo '</div>';

	echo '<button class="cc-navtoggle" type="button" aria-expanded="false" data-cc-navtoggle>Menü</button>';
	echo '<nav class="cc-nav" aria-label="Control Center">';
	foreach ( fge_cc_pages() as $slug => [ $title, $parent ] ) {
		if ( $parent !== $slug ) {
			continue; // Unterseite
		}
		$badge = (int) ( $badges[ $slug ] ?? 0 );
		printf(
			'<a class="cc-nav-item%s" href="%s"%s><span>%s</span>%s</a>',
			$slug === $on ? ' is-on' : '',
			esc_url( fge_cc_url( $slug ) ),
			$slug === $current ? ' aria-current="page"' : '',
			esc_html( $title ),
			$badge > 0 ? '<span class="cc-badge">' . $badge . '</span>' : ''
		);
	}
	echo '</nav>';

	echo '<div class="cc-side-foot">';
	echo '<div class="cc-side-user"><span class="cc-avatar" aria-hidden="true">' . esc_html( $ini ) . '</span><span class="cc-side-who"><strong>' . esc_html( $name ) . '</strong><span>Admin</span></span></div>';
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
		'partner'     => 'Name, Ort oder Ansprechpartner',
		'events'      => 'Event oder Partner',
		'verzeichnis' => 'Platz, Ort oder PLZ',
		'kunden'      => 'Firma oder Mailadresse',
		'partnercodes' => 'Suchen',
	];
	// Knöpfe rechts im Kopf, wie im Entwurf.
	$buttons = [
		'partnercodes' => '<button type="button" class="cc-btn" data-cc-drawer-open="cc-pc-new">Code anlegen</button>',
	];
	$sub    = '';
	$kicker = '';
	if ( 'dashboard' === $page ) {
		$user   = wp_get_current_user();
		$first  = $user->first_name ?: $user->display_name;
		$hour   = (int) wp_date( 'G' );
		$title  = ( $hour < 11 ? 'Guten Morgen' : ( $hour < 18 ? 'Guten Tag' : 'Guten Abend' ) ) . ', ' . $first;
		$n      = fge_cc_badge_count();
		$kicker = wp_date( 'l, j. F Y' );
		$sub    = $n > 0 ? $n . ( 1 === $n ? ' Sache wartet auf dich.' : ' Dinge warten auf dich.' ) . ' Das Wichtigste steht oben.' : 'Alles erledigt. Schönen Tag.';
	}

	echo '<header class="cc-top">';
	echo '<div class="cc-top-text">' . ( '' !== $kicker ? '<span class="cc-top-kicker">' . esc_html( $kicker ) . '</span>' : '' ) . '<h1>' . esc_html( $title ) . '</h1>';
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
		if ( 'kunden' === $page && 'leads' === ( $_GET['ansicht'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<input type="hidden" name="ansicht" value="leads">';
		}
		echo '</form>';
	}
	if ( isset( $buttons[ $page ] ) ) {
		echo $buttons[ $page ]; // phpcs:ignore WordPress.Security.EscapeOutput -- feste Knöpfe oben
	}
	echo '</header>';
}

// ── Bausteine ────────────────────────────────────────────────────────────────

/** Wie viel unter „Heute“ wartet (Zahl in der Seitenleiste). */
function fge_cc_badge_count(): int {
	return function_exists( 'fge_cc_today_total' ) ? fge_cc_today_total() : 0;
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
	return []; // Seit 1.9.310 hat jede Seite ihren Inhalt im Control Center.
}

function fge_cc_page_stub( string $page ): void {
	echo '<section class="cc-card cc-stub"><p class="cc-muted">Diese Seite gibt es nicht.</p></section>';
}

/** „vor 3 Stunden“ statt Zeitstempel. */
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

// ── Einzelne Anfrage ─────────────────────────────────────────────────────────
// Liste und Detail liegen seit 15.10.2026 in cc-request-flow.php.

/** Reiter „Lexoffice und Tracking“: Lesesicht der Felder aus dem WP-Admin. */
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
	echo '<p class="cc-muted">Die Rechnungsnummer trägst du oben bei „Rechnung gestellt“ ein. Angebotsnummer und Tracking stehen im <a href="' . esc_url( get_edit_post_link( $req, 'raw' ) ) . '">WordPress</a>.</p>';
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
		echo '<p class="cc-hint cc-hint--warn">Ohne Preis kein Angebot. Erst im Reiter „Angebot und Eventtag“ bepreisen, sonst ginge „Auf Anfrage“ verbindlich buchbar raus.</p>';
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
 * Bis 28.09.2026 stand hier weiter „Angebot läuft“ und der gewählte Platz erfuhr nichts.
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

/** Gebucht: Eventtag ausfüllen, die Vortags-Info geht am Tag vor dem Event. */
function fge_cc_phase_booked( int $req ): void {
	$v         = fge_day_values( $req );
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
	$date = fge_day_event_date( $req );
	echo '<span class="cc-muted"> Die Felder gehen mit der Vortags-Info an Kunde und Platz' . ( null !== $date ? ', am ' . esc_html( gmdate( 'd.m.Y', strtotime( $date . ' -1 day' ) ) ) . ' um 7:00 Uhr' : ', am Tag vor dem Event um 7:00 Uhr' ) . '.</span></p>';
	echo '</form>';

	echo '<div class="cc-actions">';
	echo '<div class="cc-action">';
	if ( '' !== $info_sent ) {
		echo '<p class="cc-done">Vortags-Info gesendet am ' . esc_html( wp_date( 'd.m.Y H:i', (int) $info_sent ) ) . '</p>';
	}
	if ( fge_day_info_open( $req ) ) {
		fge_cc_button( 'fge_cc_day_info', $req, '' !== $info_sent ? 'Vortags-Info erneut senden' : 'Vortags-Info senden', [
			'confirm' => 'Vortags-Info an Kunde, Platz und ' . fge_company_internal_email() . ' senden?',
		] );
	} else {
		// Fragt der Kunde vorher nach dem Ablauf: dieselbe Mail von Hand, nur an ihn.
		fge_cc_button( 'fge_cc_day_info', $req, 'Infos zum Eventtag jetzt an den Kunden', [
			'confirm' => 'Infos zum Eventtag jetzt an den Kunden senden? Die Vortags-Info am Vortag geht trotzdem automatisch.',
		] );
	}
	fge_cc_action_preview( 'day_info', $req );
	echo '</div>';
	echo '</div>';

	// Nachlauf-Knöpfe (durchgeführt, abgeschlossen, verloren) stehen seit
	// 15.10.2026 im Schritt Abrechnung.

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
		echo '<p class="cc-muted">Der Platz möchte das nicht dauerhaft anbieten.' . ( '' !== $note ? ' „' . esc_html( $note ) . '“' : '' ) . '</p>';
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
	echo '<textarea name="note" rows="2" aria-label="Notiz" placeholder="Was wurde besprochen? Telefonnotiz oder Vermerk"></textarea>';
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
		// Im Bedarf angepasster Name geht vor (Julius, 15.10.2026: der Bedarf ist die Anfrage).
		'event'       => trim( (string) get_post_meta( $req, '_fge_need_event', true ) ) ?: ( $event_id > 0 ? get_the_title( $event_id ) : (string) get_post_meta( $req, '_fge_event_type', true ) ),
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
