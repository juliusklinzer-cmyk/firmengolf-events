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

const FGE_CC_REWRITE_VERSION = '1.2.0';

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
		fge_cc_topbar( $title, $page );
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
			case 'plaetze':
				fge_cc_page_partners();
				break;
			case 'kunden':
				fge_cc_page_customers();
				break;
			case 'kalender':
				fge_cc_page_calendar();
				break;
			case 'angebote':
				fge_cc_page_offers();
				break;
			case 'aufgaben':
				fge_cc_page_tasks();
				break;
			case 'geld':
				fge_cc_page_money();
				break;
			case 'postausgang':
				fge_cc_page_outbox();
				break;
			case 'dienstleister':
				fge_cc_page_providers();
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

function fge_cc_topbar( string $title, string $page = 'anfragen' ): void {
	// Die Suche bleibt auf der Seite, auf der man steht. Nur Seiten ohne eigene
	// Suche schicken nach „Anfragen", weil das die häufigste Suche ist.
	$targets = [
		'anfragen' => [ 'anfragen', 'Firma oder FG-Nummer' ],
		'plaetze'  => [ 'plaetze', 'Platz oder Ort' ],
		'kunden'   => [ 'kunden', 'Firma oder Mailadresse' ],
	];
	[ $target, $placeholder ] = $targets[ $page ] ?? $targets['anfragen'];

	echo '<header class="cc-top">';
	echo '<h1>' . esc_html( $title ) . '</h1>';
	echo '<form class="cc-search" method="get" action="' . esc_url( fge_cc_url( $target ) ) . '" role="search">';
	if ( ! get_option( 'permalink_structure' ) ) {
		echo '<input type="hidden" name="fge_cc" value="' . esc_attr( $target ) . '">';
	}
	echo '<input type="search" name="s" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( wp_unslash( $_GET['s'] ?? '' ) ) . '" aria-label="Suche">'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

	_prime_post_caches( $ids, false, true );

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
	fge_cc_message( $req );
	fge_cc_request_header( $req );
	fge_cc_request_steps( $req );
	echo '<div class="cc-cols">';
	echo '<div class="cc-col-main">';
	fge_cc_request_contacts( $req );
	// Die Platz-Pipeline steht vor der Phasenkarte, solange noch kein Angebot
	// raus ist: dort wird in dieser Zeit tatsächlich gearbeitet. Danach bleibt
	// sie sichtbar, solange Plätze in der Liste stehen.
	if ( function_exists( 'fge_cc_venues_panel' )
		&& ( '1' !== (string) get_post_meta( $req, '_fge_offer_sent', true ) || fge_venues_get( $req ) ) ) {
		fge_cc_venues_panel( $req );
	}
	fge_cc_request_phase_panel( $req );
	fge_cc_request_timeline( $req );
	echo '</div><div class="cc-col-side">';
	fge_cc_request_tasks( $req );
	fge_cc_request_mails( $req );
	fge_cc_request_snooze( $req );
	echo '</div></div>';
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

	echo '<section class="cc-card cc-phase"><h2>Phase ' . (int) $num . ': ' . esc_html( $label ) . '</h2>';

	if ( 'accepted' === $offer ) {
		fge_cc_phase_booked( $req );
	} elseif ( $sent ) {
		fge_cc_phase_offer_running( $req );
	} elseif ( function_exists( 'fge_rr_final_index' ) && fge_rr_final_index( $req ) > 0 ) {
		fge_cc_phase_offer_ready( $req );
	} else {
		fge_cc_phase_early( $req );
	}
	echo '</section>';
}

/** Eingang und Termin: was fehlt, um ein Angebot senden zu können. */
function fge_cc_phase_early( int $req ): void {
	$partner_id = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
	$wishes     = function_exists( 'fge_rr_wish_dates' ) ? fge_rr_wish_dates( $req ) : [];

	echo '<dl class="cc-facts">';
	fge_cc_fact( 'Wunschtermine', $wishes ? implode( ' · ', array_map( 'strval', $wishes ) ) : 'keine angegeben' );
	fge_cc_fact( 'Teilnehmer', (string) get_post_meta( $req, '_fge_expected_participants', true ) );
	fge_cc_fact( 'Budget', (string) get_post_meta( $req, '_fge_budget_range', true ) );
	fge_cc_fact( 'Nachricht', (string) get_post_meta( $req, '_fge_message', true ) );
	echo '</dl>';

	if ( $partner_id <= 0 ) {
		echo '<p class="cc-hint">Es ist noch kein Platz zugeordnet. Nimm oben Plätze in die Liste auf, frag sie an und wähle einen.</p>';
	}

	fge_cc_confirm_date_form( $req, $wishes );
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
		echo '<p class="cc-hint cc-hint--warn">Ohne Preis kein Angebot. Erst in Schritt 2 im WordPress-Backend bepreisen, sonst ginge „Auf Anfrage" verbindlich buchbar raus.</p>';
		echo '<p><a class="cc-btn" href="' . esc_url( get_edit_post_link( $req, 'raw' ) ) . '">Positionen bepreisen</a></p>';
		return;
	}
	fge_cc_button( 'fge_cc_offer_send', $req, 'Angebot jetzt senden', [
		'class'   => 'cc-btn cc-btn--primary',
		'confirm' => 'Angebot mit PDF an den Kunden senden?',
	] );
	fge_cc_action_preview( 'offer_send', $req );
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
	echo '<section class="cc-card"><h2>Zeitleiste</h2>';

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
	foreach ( $items as $a ) {
		echo '<li class="cc-tl cc-tl--' . esc_attr( (string) $a['type'] ) . '">';
		echo '<span class="cc-tl-type">' . esc_html( $labels[ $a['type'] ] ?? (string) $a['type'] ) . '</span>';
		echo '<span class="cc-tl-text">' . nl2br( esc_html( (string) $a['text'] ) ) . '</span>';
		echo '<span class="cc-tl-time">' . esc_html( fge_cc_ago( (string) $a['created_at'] ) ) . '</span>';
		echo '</li>';
	}
	echo '</ul></section>';
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
