<?php
/**
 * Control Center: eigene Route, eigener Shell, eigenes Stylesheet.
 *
 * Bewusst NICHT im WordPress-Admin und bewusst ohne Firmengolf-Markendesign.
 * Das Backend ist ein internes Werkzeug: dunkle Seitenleiste, helle Arbeits-
 * fläche, dichte Tabellen am Schreibtisch, eine Spalte am Telefon. DESIGN.md
 * gilt für die Kundenstrecken, hier gilt assets/css/fge-cc.css.
 *
 * Route: /control/ und /control/<seite>/ (Fallback ?fge_cc=<seite>, falls keine
 * hübschen Permalinks aktiv sind). Zugriff nur mit manage_options, sonst 404,
 * damit die Existenz der Seite nach außen nicht sichtbar ist.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_CC_REWRITE_VERSION = '1.0.0';

/** Seiten des Control Centers: slug => [Label, Gruppe]. */
function fge_cc_pages(): array {
	return [
		'dashboard'     => [ 'Dashboard', 'arbeit' ],
		'anfragen'      => [ 'Anfragen', 'arbeit' ],
		'angebote'      => [ 'Angebote', 'arbeit' ],
		'kalender'      => [ 'Kalender', 'arbeit' ],
		'aufgaben'      => [ 'Aufgaben', 'arbeit' ],
		'plaetze'       => [ 'Plätze', 'stamm' ],
		'kunden'        => [ 'Kunden', 'stamm' ],
		'dienstleister' => [ 'Dienstleister', 'stamm' ],
		'geld'          => [ 'Geld', 'stamm' ],
		'postausgang'   => [ 'Postausgang', 'stamm' ],
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
	$pages = fge_cc_pages();
	$title = $pages[ $page ][0];
	?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title ); ?> · Control Center</title>
	<link rel="stylesheet" href="<?php echo esc_url( plugins_url( 'assets/css/fge-cc.css', FGE_DIR . 'firmengolf-events.php' ) . '?v=' . FGE_VERSION ); ?>">
</head>
<body class="cc">
<a class="cc-skip" href="#cc-main">Zum Inhalt</a>
<div class="cc-app">
	<?php fge_cc_sidebar( $page ); ?>
	<main class="cc-main" id="cc-main">
		<?php
		fge_cc_topbar( $title );
		echo '<div class="cc-body">';
		switch ( $page ) {
			case 'anfragen':
				$req = absint( $_GET['req'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( $req > 0 ) {
					fge_cc_page_request( $req );
				} else {
					fge_cc_page_requests();
				}
				break;
			case 'dashboard':
				fge_cc_page_dashboard();
				break;
			default:
				fge_cc_page_stub( $title );
		}
		echo '</div>';
		?>
	</main>
</div>
</body>
</html>
	<?php
}

function fge_cc_sidebar( string $current ): void {
	$groups = [ 'arbeit' => 'Arbeit', 'stamm' => 'Stammdaten' ];
	echo '<aside class="cc-side">';
	echo '<div class="cc-brand"><span class="cc-mark">FG</span><span>Control Center</span></div>';
	echo '<nav class="cc-nav">';
	foreach ( $groups as $g => $label ) {
		echo '<p class="cc-nav-head">' . esc_html( $label ) . '</p>';
		foreach ( fge_cc_pages() as $slug => [ $name, $group ] ) {
			if ( $group !== $g ) {
				continue;
			}
			$badge = 'dashboard' === $slug ? fge_cc_badge_count() : 0;
			printf(
				'<a class="cc-nav-item%s" href="%s">%s%s</a>',
				$slug === $current ? ' is-on' : '',
				esc_url( fge_cc_url( $slug ) ),
				esc_html( $name ),
				$badge > 0 ? '<span class="cc-badge">' . (int) $badge . '</span>' : ''
			);
		}
	}
	echo '</nav>';
	echo '<div class="cc-side-foot"><a href="' . esc_url( admin_url() ) . '">WordPress-Backend</a></div>';
	echo '</aside>';
}

function fge_cc_topbar( string $title ): void {
	echo '<header class="cc-top">';
	echo '<h1>' . esc_html( $title ) . '</h1>';
	echo '<form class="cc-search" method="get" action="' . esc_url( fge_cc_url( 'anfragen' ) ) . '" role="search">';
	if ( ! get_option( 'permalink_structure' ) ) {
		echo '<input type="hidden" name="fge_cc" value="anfragen">';
	}
	echo '<input type="search" name="s" placeholder="Firma oder FG-Nummer" value="' . esc_attr( wp_unslash( $_GET['s'] ?? '' ) ) . '" aria-label="Suche">'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	echo '</form>';
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

function fge_cc_page_stub( string $title ): void {
	echo '<div class="cc-card">';
	echo '<h2>' . esc_html( $title ) . '</h2>';
	echo '<p class="cc-muted">Dieser Bereich ist geplant und noch nicht gebaut. Bis dahin führt der Weg über das WordPress-Backend.</p>';
	echo '</div>';
}

// ── Dashboard ────────────────────────────────────────────────────────────────

function fge_cc_page_dashboard(): void {
	$rows = fge_cc_worklist();

	$mine = $others = $soon = $cold = [];
	foreach ( $rows as $r ) {
		if ( $r['snoozed'] ) {
			continue;
		}
		$d = $r['date'];
		if ( $d > 0 && $d >= strtotime( 'today' ) && $d <= strtotime( 'tomorrow 23:59' ) ) {
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

	echo '<div class="cc-tabs" role="tablist">';
	fge_cc_tab( 'mine', 'Wartet auf mich', count( $mine ), true );
	fge_cc_tab( 'others', 'Wartet auf andere', count( $others ), false );
	fge_cc_tab( 'soon', 'Heute und morgen', count( $soon ), false );
	if ( $cold ) {
		fge_cc_tab( 'cold', 'Verstaubt', count( $cold ), false );
	}
	echo '</div>';

	echo '<div class="cc-cols">';
	echo '<div class="cc-col-main">';
	fge_cc_worklist_panel( 'mine', $mine, 'Nichts offen. Alles, was zu tun war, ist getan.' );
	fge_cc_worklist_panel( 'others', $others, 'Es wartet gerade nichts auf andere.' );
	fge_cc_worklist_panel( 'soon', $soon, 'Heute und morgen steht kein Event an.' );
	if ( $cold ) {
		echo '<section class="cc-panel" data-cc-pane="cold" hidden>';
		echo '<p class="cc-muted">Seit mehr als ' . (int) fge_cc_cold_days() . ' Tagen ohne Fortschritt und ohne Termin. Nachfassen oder auf „verloren" setzen, damit die Liste ehrlich bleibt.</p>';
		echo '<div class="cc-list">';
		foreach ( $cold as $r ) {
			fge_cc_worklist_row( $r );
		}
		echo '</div></section>';
	}
	echo '</div>';

	echo '<div class="cc-col-side">';
	fge_cc_recent_panel();
	fge_cc_funnel_panel();
	echo '</div>';
	echo '</div>';

	fge_cc_tabs_script();
}

function fge_cc_tab( string $id, string $label, int $count, bool $on ): void {
	printf(
		'<button class="cc-tab%s" data-cc-tab="%s" role="tab" aria-selected="%s"><span class="cc-tab-n">%d</span>%s</button>',
		$on ? ' is-on' : '',
		esc_attr( $id ),
		$on ? 'true' : 'false',
		$count,
		esc_html( $label )
	);
}

function fge_cc_worklist_panel( string $id, array $rows, string $empty ): void {
	echo '<section class="cc-panel" data-cc-pane="' . esc_attr( $id ) . '"' . ( 'mine' === $id ? '' : ' hidden' ) . '>';
	if ( ! $rows ) {
		fge_cc_empty( $empty );
		echo '</section>';
		return;
	}
	echo '<div class="cc-list">';
	foreach ( $rows as $r ) {
		fge_cc_worklist_row( $r );
	}
	echo '</div></section>';
}

function fge_cc_worklist_row( array $r ): void {
	$tasks = array_values( array_filter( $r['tasks'], static fn( $t ) => 'me' === $t['who'] ) );
	if ( ! $tasks ) {
		$tasks = $r['tasks'];
	}
	$lead = $tasks[0] ?? null;

	echo '<a class="cc-row" href="' . esc_url( fge_cc_request_url( (int) $r['req'] ) ) . '">';
	echo '<span class="cc-row-main">';
	echo '<span class="cc-row-top"><span class="cc-ref">' . esc_html( $r['ref'] ) . '</span>';
	echo '<span class="cc-company">' . esc_html( $r['company'] ?: 'ohne Firma' ) . '</span>';
	echo fge_cc_pill( $r['phase'], fge_cc_phase_tone( $r['phase'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</span>';
	if ( $lead ) {
		echo '<span class="cc-next cc-next--' . esc_attr( $lead['urgency'] ) . '">' . esc_html( $lead['text'] ) . '</span>';
	}
	if ( count( $tasks ) > 1 ) {
		echo '<span class="cc-more">und ' . (int) ( count( $tasks ) - 1 ) . ' weitere</span>';
	}
	echo '</span>';
	echo '<span class="cc-row-meta">';
	if ( $r['date'] > 0 ) {
		echo '<span class="cc-when">' . esc_html( fge_cc_date( (int) $r['date'] ) ) . '</span>';
	}
	echo '<span class="cc-age">' . (int) $r['age'] . ' T</span>';
	echo '</span>';
	echo '</a>';
}

/** Was seit gestern passiert ist: Zeitleiste plus fehlgeschlagene Mails. */
function fge_cc_recent_panel(): void {
	echo '<section class="cc-card"><h2>Seit gestern</h2>';
	$acts   = function_exists( 'fge_activity_recent' ) ? fge_activity_recent( 12 ) : [];
	$failed = function_exists( 'fge_mail_log_recent' ) ? fge_mail_log_recent( 5, 'failed' ) : [];

	if ( $failed ) {
		echo '<p class="cc-alert">' . (int) count( $failed ) . ' Mail' . ( count( $failed ) > 1 ? 's' : '' ) . ' nicht zugestellt</p>';
		foreach ( $failed as $f ) {
			echo '<p class="cc-feed-item cc-feed-item--bad">' . esc_html( $f['recipient'] ) . '<br><span class="cc-muted">' . esc_html( mb_substr( (string) $f['subject'], 0, 60 ) ) . '</span></p>';
		}
	}
	if ( ! $acts ) {
		fge_cc_empty( 'Noch keine Ereignisse aufgezeichnet.' );
		echo '</section>';
		return;
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
	echo '</section>';
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

/** Trichter: Anfragen, Angebote, Buchungen im laufenden Monat. */
function fge_cc_funnel_panel(): void {
	$since = strtotime( 'first day of this month 00:00' );
	$ids   = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => 400,
		'fields'      => 'ids',
		'date_query'  => [ [ 'after' => wp_date( 'Y-m-d', $since ) ] ],
	] );

	$anfragen = count( $ids );
	$angebote = $buchungen = 0;
	$umsatz   = 0.0;
	foreach ( $ids as $id ) {
		if ( '1' === (string) get_post_meta( $id, '_fge_offer_sent', true ) ) {
			$angebote++;
		}
		if ( 'accepted' === (string) get_post_meta( $id, '_fge_offer_status', true ) ) {
			$buchungen++;
			$snap = (array) get_post_meta( $id, '_fge_offer_snapshot', true );
			$sel  = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $id ) : [];
			$tot  = function_exists( 'fge_offer_totals' ) ? fge_offer_totals( $snap, $sel ) : [ 'net' => 0 ];
			$umsatz += (float) ( $tot['net'] ?? 0 );
		}
	}

	echo '<section class="cc-card"><h2>' . esc_html( wp_date( 'F' ) ) . '</h2>';
	echo '<div class="cc-funnel">';
	foreach ( [ 'Anfragen' => $anfragen, 'Angebote' => $angebote, 'Buchungen' => $buchungen ] as $label => $n ) {
		$pct = $anfragen > 0 ? (int) round( $n / $anfragen * 100 ) : 0;
		echo '<div class="cc-funnel-row"><span class="cc-funnel-label">' . esc_html( $label ) . '</span>';
		echo '<span class="cc-funnel-bar"><span style="width:' . (int) $pct . '%"></span></span>';
		echo '<span class="cc-funnel-n">' . (int) $n . '</span></div>';
	}
	echo '</div>';
	if ( $umsatz > 0 ) {
		echo '<p class="cc-stat"><span class="cc-stat-n">' . esc_html( number_format_i18n( $umsatz, 0 ) ) . ' €</span><span class="cc-muted">gebucht, netto</span></p>';
	}
	echo '</section>';
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

	$ids = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => 200,
		'fields'      => 'ids',
		'orderby'     => 'date',
		'order'       => 'DESC',
	] );

	$rows = [];
	foreach ( $ids as $id ) {
		$ref     = fge_request_number( $id );
		$company = (string) get_post_meta( $id, '_fge_company_name', true );
		if ( '' !== $search
			&& false === mb_stripos( $ref . ' ' . $company, $search ) ) {
			continue;
		}
		[ , $phase_name ] = fge_cc_phase( $id );
		if ( '' !== $phase && $phase_name !== $phase ) {
			continue;
		}
		$tasks  = fge_cc_tasks( $id );
		$mine   = array_values( array_filter( $tasks, static fn( $t ) => 'me' === $t['who'] ) );
		$rows[] = [
			'req'     => $id,
			'ref'     => $ref,
			'company' => $company,
			'phase'   => $phase_name,
			'status'  => fge_cc_status_label( (string) get_post_meta( $id, '_fge_request_status', true ) ),
			'next'    => $mine[0]['text'] ?? ( $tasks[0]['text'] ?? '' ),
			'date'    => fge_cc_event_date( $id ),
			'age'     => fge_cc_age_days( $id ),
			'partner' => (int) get_post_meta( $id, '_fge_assigned_partner_id', true ),
		];
	}

	// Filterleiste
	echo '<div class="cc-filters">';
	$phases = [ 'Eingang', 'Termin', 'Angebot', 'Angebot läuft', 'Vorbereitung', 'Eventtag', 'Nachlauf' ];
	echo '<a class="cc-chip' . ( '' === $phase ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'anfragen', $search ? [ 's' => $search ] : [] ) ) . '">Alle</a>';
	foreach ( $phases as $p ) {
		$args = [ 'phase' => $p ] + ( $search ? [ 's' => $search ] : [] );
		echo '<a class="cc-chip' . ( $phase === $p ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'anfragen', $args ) ) . '">' . esc_html( $p ) . '</a>';
	}
	echo '</div>';

	if ( '' !== $search ) {
		echo '<p class="cc-muted">' . (int) count( $rows ) . ' Treffer für „' . esc_html( $search ) . '"</p>';
	}

	if ( ! $rows ) {
		fge_cc_empty( 'Keine Anfrage gefunden.' );
		return;
	}

	echo '<div class="cc-tablewrap"><table class="cc-table">';
	echo '<thead><tr><th>Vorgang</th><th>Firma</th><th>Phase</th><th>Nächster Schritt</th><th>Termin</th><th>Alter</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr onclick="location.href=\'' . esc_js( fge_cc_request_url( (int) $r['req'] ) ) . '\'">';
		echo '<td><a href="' . esc_url( fge_cc_request_url( (int) $r['req'] ) ) . '">' . esc_html( $r['ref'] ) . '</a></td>';
		echo '<td>' . esc_html( $r['company'] ?: '—' ) . '</td>';
		echo '<td>' . fge_cc_pill( $r['phase'], fge_cc_phase_tone( $r['phase'] ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td class="cc-td-next">' . esc_html( $r['next'] ?: '—' ) . '</td>';
		echo '<td>' . esc_html( $r['date'] > 0 ? wp_date( 'd.m.Y', (int) $r['date'] ) : '—' ) . '</td>';
		echo '<td class="cc-num">' . (int) $r['age'] . ' T</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}

// ── Einzelne Anfrage (Platzhalter bis Stufe 3) ───────────────────────────────

function fge_cc_page_request( int $req ): void {
	if ( 'firmengolf_request' !== get_post_type( $req ) ) {
		fge_cc_empty( 'Diese Anfrage gibt es nicht.' );
		return;
	}
	echo '<p class="cc-back"><a href="' . esc_url( fge_cc_url( 'anfragen' ) ) . '">Zurück zur Liste</a></p>';
	fge_cc_request_header( $req );
	echo '<div class="cc-cols">';
	echo '<div class="cc-col-main">';
	fge_cc_request_contacts( $req );
	echo '</div><div class="cc-col-side">';
	fge_cc_request_tasks( $req );
	fge_cc_request_mails( $req );
	echo '</div></div>';
}

function fge_cc_request_header( int $req ): void {
	[ , $phase ] = fge_cc_phase( $req );
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$sel  = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $req ) : [];
	$tot  = function_exists( 'fge_offer_totals' ) ? fge_offer_totals( $snap, $sel ) : [ 'net' => 0 ];

	echo '<header class="cc-head">';
	echo '<div><span class="cc-ref cc-ref--big">' . esc_html( fge_request_number( $req ) ) . '</span>';
	echo '<h2>' . esc_html( (string) get_post_meta( $req, '_fge_company_name', true ) ?: 'Ohne Firma' ) . '</h2></div>';
	echo '<div class="cc-head-meta">';
	echo fge_cc_pill( $phase, fge_cc_phase_tone( $phase ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	$date = fge_cc_event_date( $req );
	if ( $date > 0 ) {
		echo '<span class="cc-head-date">' . esc_html( fge_cc_date( $date ) ) . '</span>';
	}
	if ( (float) ( $tot['net'] ?? 0 ) > 0 ) {
		echo '<span class="cc-head-sum">' . esc_html( number_format_i18n( (float) $tot['net'], 2 ) ) . ' € netto</span>';
	}
	echo '<a class="cc-btn cc-btn--ghost" href="' . esc_url( get_edit_post_link( $req, 'raw' ) ) . '">Im WordPress öffnen</a>';
	echo '</div></header>';
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
	echo '<section class="cc-card"><h2>Offen</h2>';
	if ( ! $tasks ) {
		fge_cc_empty( 'Nichts offen.' );
		echo '</section>';
		return;
	}
	echo '<ul class="cc-tasks">';
	foreach ( $tasks as $t ) {
		echo '<li class="cc-task cc-task--' . esc_attr( $t['urgency'] ) . '">'
			. '<span class="cc-task-who">' . ( 'me' === $t['who'] ? 'ich' : 'andere' ) . '</span>'
			. esc_html( $t['text'] ) . '</li>';
	}
	echo '</ul></section>';
}

function fge_cc_request_mails( int $req ): void {
	$log = function_exists( 'fge_mail_log_for_request' ) ? fge_mail_log_for_request( $req, 20 ) : [];
	echo '<section class="cc-card"><h2>Postausgang</h2>';
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
