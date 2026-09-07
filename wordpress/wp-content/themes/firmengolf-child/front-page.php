<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// ── URLs ────────────────────────────────────────────────────────────────────
$get_page_url = static function( string $slug, string $fallback = '#' ): string {
	$page = get_page_by_path( $slug );
	return $page ? (string) get_permalink( $page->ID ) : $fallback;
};

$url_events  = (string) get_post_type_archive_link( 'firmengolf_event' );
$url_ind     = $get_page_url( 'individuelle-events', home_url( '/individuelle-events/' ) );
$url_kontakt = $get_page_url( 'kontakt', home_url( '/kontakt/' ) );
$url_blog    = home_url( '/blog/' );

// ── Format list (canonical, single source: event-formats.php) ───────────────
$formats = array_merge( [ 'all' => 'Alle' ], fge_get_event_formats_flat( false ) );

// ── Regions from DB ──────────────────────────────────────────────────────────
global $wpdb;
$available_regions = $wpdb->get_col( $wpdb->prepare(
	"SELECT DISTINCT pm.meta_value
	 FROM {$wpdb->postmeta} pm
	 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	 WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' AND pm.meta_value != ''
	 ORDER BY pm.meta_value",
	'_fge_region', 'firmengolf_event'
) );
if ( empty( $available_regions ) ) {
	$available_regions = [ 'Nord', 'Ost', 'Süd', 'West' ];
}

// ── Featured events (up to 4 published) ────────────────────────────────────
$featured_events = fge_get_featured_events( 6 );

// ── Latest blog posts ───────────────────────────────────────────────────────
$blog_posts = get_posts( [
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'numberposts'    => 4,
	'orderby'        => 'date',
	'order'          => 'DESC',
] );

// ── Images ──────────────────────────────────────────────────────────────────
$img = static function( string $name ) use ( &$img ): string {
	return fge_get_placeholder_image_url( $name );
};

// Arrow SVG (inline, reused)
$arrow_svg = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>';
$arrow_right = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';
$check_svg  = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';
?>
<div class="fge-page cty-page" id="fge-main" role="main" tabindex="-1">

<?php
/* Keine Such-Pille auf der Startseite: Hero-CTAs reichen, oben bleibt frei (Julius, 2026-08-27). */
get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] );
?>

<?php /* ══════════════════ 1. HERO ══════════════════ */ ?>
<section class="mk-hero" aria-label="Hero">
	<div class="mk-hero-photo" style="background-image:url('<?php echo esc_url( $img( 'hero-golfer-alpen.jpg' ) ); ?>')">
		<div class="mk-hero-scrim" aria-hidden="true"></div>
		<div class="mk-hero-content">
			<h1 class="mk-hero-title">
				<span class="mk-hero-lead">Wir machen den Golfplatz</span>
				<span class="rot-wrap"><span class="rot-word in" id="fg-rot-word"><span class="rot-art">zur </span><span class="rot-key">Eventlocation</span></span><span class="rot-dot">.</span></span>
			</h1>
			<p class="mk-hero-sub">
				Von der Platzreife bis zum Firmenturnier: Golf-Formate auf Partnerplätzen
				in ganz Deutschland. Eine Anfrage, eine Rechnung, ein Ansprechpartner.
			</p>
			<div class="mk-hero-ctas">
				<?php /* Events entdecken als Primär-CTA (Julius, 28.08.): der einfachere
					Einstieg gewinnt, die Anfrage bleibt als zweiter Weg daneben. */ ?>
				<a class="fg-btn-cta fg-btn-lg" href="<?php echo esc_url( $url_events ); ?>">
					Events entdecken
					<span class="fg-arrow"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
				<a class="fg-btn-ghost-light" href="<?php echo esc_url( add_query_arg( 'anfrage', 'quick', $url_ind ) ); ?>">
					Event anfragen →
				</a>
			</div>
			<?php /* Gebuendelte Fakten direkt im Hero (Julius, 2026-08-20): 500+ Locations
			         (Golfplaetze, Simulatoren, kuenftig mehr) statt Golfplatz-Zaehlung,
			         dazu die wichtigste Einwand-Entkraeftung. Das separate Fakten-Band
			         darunter ist dafuer entfallen. */ ?>
			<div class="cty-hero-facts">
				<span class="cty-hero-fact"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><strong>500+</strong>&nbsp;Locations deutschlandweit</span>
				<span class="cty-hero-fact"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>Auch ohne Golferfahrung</span>
			</div>
		</div>
	</div>

	<?php
	// Floating-Karte: verlinkt auf die Eventliste, Zähler = aktuell öffentliche Events.
	$fge_live_event_ids = get_posts( [
		'post_type'     => 'firmengolf_event',
		'post_status'   => 'publish',
		'numberposts'   => -1,
		'fields'        => 'ids',
		'no_found_rows' => true,
	] );
	$fge_live_count = function_exists( 'fge_event_is_public' )
		? count( array_filter( $fge_live_event_ids, 'fge_event_is_public' ) )
		: count( $fge_live_event_ids );
	?>
	<?php if ( $fge_live_count > 0 ) : ?>
	<a class="mk-hero-floating" href="<?php echo esc_url( $url_events ); ?>">
		<div class="mk-floating-thumb" aria-hidden="true" style="background-image:url('<?php echo esc_url( $img( 'abschlag-driver-tee.jpg' ) ); ?>')"></div>
		<div>
			<div class="mk-floating-chip"><?php echo esc_html( (string) $fge_live_count ); ?> Events aktuell live</div>
			<div class="mk-floating-meta">Hamburg · München · Berlin · Köln</div>
		</div>
	</a>
	<?php endif; ?>

	<form method="get" action="<?php echo esc_url( $url_events ); ?>" class="home-quicksearch fg-search-bar" role="search" aria-label="Events filtern">

		<?php /* Wo?, Ort/PLZ-Autocomplete + Standort + Umkreis (identisch zur Events-Seite) */ ?>
		<div class="fg-search-cell fg-loc-cell" id="qs-loc-cell"
		     tabindex="0" role="button" aria-haspopup="dialog" aria-expanded="false" aria-label="Ort oder PLZ wählen">
			<div class="fg-cell-label">Wo?</div>
			<div class="fg-cell-value" id="qs-loc-display"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
				<input type="text" class="fg-cell-input" id="qs-loc-input" placeholder="Ort oder PLZ" autocomplete="off" value="">
			</div>
			<input type="hidden" name="lat"    id="qs-lat"     value="">
			<input type="hidden" name="lng"    id="qs-lng"     value="">
			<input type="hidden" name="radius" id="qs-radius"  value="50">
			<input type="hidden" name="loc"    id="qs-loc-val" value="">
			<div class="fg-search-panel fg-loc-panel" id="qs-loc-panel" role="dialog" aria-label="Ort und Umkreis">
				<button type="button" class="fg-loc-gps" id="qs-loc-gps">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>
					Meinen Standort
				</button>
				<div class="fg-loc-suggest" id="qs-loc-suggest" role="listbox"></div>
				<div class="fg-loc-radius">
					<div class="fg-loc-radius-label">Umkreis</div>
					<div class="fg-loc-radius-btns">
						<?php foreach ( [ 25, 50, 100, 200 ] as $r ) : ?>
							<button type="button" class="fg-loc-rb<?php echo 50 === $r ? ' active' : ''; ?>" data-r="<?php echo (int) $r; ?>"><?php echo (int) $r; ?> km</button>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>

		<div class="fg-cell-divider" aria-hidden="true"></div>

		<?php /* Format dropdown */ ?>
		<div class="fg-search-cell fg-format-cell" id="qs-format-cell"
		     tabindex="0" role="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Format wählen">
			<div class="fg-cell-label">Veranstaltungstyp</div>
			<div class="fg-cell-value" id="qs-format-display">
				<span id="qs-format-text">Alle</span>
			</div>
			<input type="hidden" name="format" id="qs-format-val" value="all">
			<div class="fg-search-panel" id="qs-format-panel" role="listbox">
				<?php foreach ( $formats as $slug => $label ) : ?>
					<button type="button"
					        class="fg-search-panel-opt<?php echo $slug === 'all' ? ' is-selected' : ''; ?>"
					        data-value="<?php echo esc_attr( $slug ); ?>"
					        data-label="<?php echo esc_attr( $label ); ?>"
					        role="option">
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="fg-cell-divider" aria-hidden="true"></div>

		<?php /* Personenzahl */ ?>
		<div class="fg-search-cell fg-pax-cell">
			<div class="fg-cell-label">Personen</div>
			<div class="fg-pax-ctrl">
				<button type="button" class="fg-pax-btn" data-fn="dec" aria-label="Weniger" disabled>−</button>
				<span class="fg-pax-num" id="qs-pax-display">10 Pers.</span>
				<button type="button" class="fg-pax-btn" data-fn="inc" aria-label="Mehr">+</button>
			</div>
			<input type="hidden" name="pax" id="qs-pax-val" value="10">
		</div>

		<button type="submit" class="fg-search-btn" aria-label="Events suchen">
			<span>Suchen</span>
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
		</button>
	</form>
</section>

<?php /* ══════════════════ 2a. INDOOR-BANNER (Saison-Werbung, Julius 07.09.) ══════════════════
	Großer Banner direkt unter dem Hero: Firmenevents im Simulator, Einstieg in die
	Weihnachtsfeier-Sektion auf /individuelle-events/. Bild: Golferin an der Box,
	Team an der Bar (pool/indoor-golf-bar-und-fun-imi-team.jpg). */ ?>
<section class="home-indoor cty-reveal" aria-label="Firmenevents im Indoor-Simulator">
	<div class="home-indoor-inner">
		<div class="home-indoor-photo" role="img" aria-label="Golferin schlägt im Indoor-Simulator ab, im Hintergrund das Team an der Bar" style="background-image:url('<?php echo esc_url( $img( 'pool/indoor-golf-bar-und-fun-imi-team.jpg' ) ); ?>')"></div>
		<div class="home-indoor-text">
			<span class="home-indoor-tag">Top aktuell</span>
			<h2 class="mk-h2">Eure Weihnachtsfeier im <em class="mk-italic">Indoor-Simulator</em>.</h2>
			<p class="mk-sub">Warm, wetterfest und mitten in der Stadt. Spannende Locations in ganz Deutschland, Golf-Challenge, Menü und Bar aus einer Hand.</p>
			<div class="home-indoor-ctas">
				<a class="fg-btn-cta" href="<?php echo esc_url( home_url( '/firmenevent/weihnachtsfeier/' ) ); ?>">
					Weihnachtsfeier planen
					<span class="fg-arrow"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
				<a class="fg-btn-ghost" href="<?php echo esc_url( home_url( '/firmenevent/weihnachtsfeier/#simulatoren' ) ); ?>">Simulatoren in ganz Deutschland</a>
			</div>
		</div>
	</div>
</section>

<?php /* ══════════════════ 2b. WARUM GOLFPLATZ (interaktiver Showcase) ══════════════════ */
/* Rote Linie 2026-08-20: Die These der Marke, bildgefuehrt. Vier Argumente als
   Liste, das grosse Bild wechselt beim Antippen mit Crossfade und ruhigem Zoom. */
$why_items = [
	[ 'ic' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
	  'k' => 'Draußen in Bewegung', 't' => 'Ein ganzer Tag an der frischen Luft: Bewegung, ohne dass es nach Sport aussieht.' ],
	[ 'ic' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
	  'k' => 'Für alle machbar', 't' => 'Flache Wege, Carts, ein Pro für die ersten Schläge. Kein Fitnesslevel, kein Handicap nötig.' ],
	[ 'ic' => '<path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 8h.01M15 8h.01M9 12h.01M15 12h.01M10 21v-4h4v4"/>',
	  'k' => 'Clubhaus & Meetingräume', 't' => 'Moderne Räume für Empfang, Präsentation oder Workshop, direkt am Grün.' ],
	[ 'ic' => '<path d="M3 2v7c0 1.1.9 2 2 2s2-.9 2-2V2"/><path d="M5 11v11"/><path d="M18 2c-1.7 0-3 2.7-3 6 0 2.6 1.1 4 3 4v10"/>',
	  'k' => 'Gastronomie & Terrasse', 't' => 'Vom Business-Lunch bis zum Grillabend, die Gastronomie vor Ort trägt jeden Anlass.' ],
];
?>
<section class="mk-section fgw cty-reveal" aria-label="Warum der Golfplatz die perfekte Event-Location ist">
	<div class="mk-section-head">
		<h2 class="mk-h2">Warum sich der Golfplatz perfekt für euer <span class="mk-italic">Firmenevent</span> eignet.</h2>
		<p class="mk-sub">Bewegung an der frischen Luft, moderne Clubhäuser mit Meetingräumen und eine Gastronomie, die jeden Anlass trägt, offen für alle, ganz ohne Golf-Vorkenntnisse.</p>
	</div>
	<div class="fgw-cols">
		<?php foreach ( $why_items as $w ) : ?>
		<div class="fgw-col">
			<span class="fgw-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?php echo $w['ic']; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></svg></span>
			<h3 class="fgw-col-t"><?php echo esc_html( $w['k'] ); ?></h3>
			<p class="fgw-col-b"><?php echo esc_html( $w['t'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 2c. ANGEBOT: BUCHEN ODER PLANEN ══════════════════ */ ?>
<section class="mk-section cty-reveal" aria-label="Unser Angebot">
	<div class="mk-section-head">
		<h2 class="mk-h2">Fertige Events buchen oder frei planen lassen.</h2>
		<p class="mk-sub"><?php echo esc_html( (string) $fge_live_count ); ?> Events mit Preis und Ablauf sind sofort anfragbar. Und wenn ihr eigene Vorstellungen habt, bauen wir euer Event nach Maß.</p>
	</div>
	<div class="fgp-grid">
		<a class="fgp-card" href="<?php echo esc_url( $url_events ); ?>">
			<img src="<?php echo esc_url( $img( 'kolleginnen-teambuilding.jpg' ) ); ?>" alt="" loading="lazy">
			<span class="fgp-scrim" aria-hidden="true"></span>
			<span class="fgp-body">
				<span class="fgp-tag">Sofort buchbar</span>
				<span class="fgp-h">Vorgeplante Partner-Events</span>
				<span class="fgp-p">Fertige Formate auf unseren Partnerplätzen, mit Preis, Ablauf und Ansprechpartner. Aussuchen, anfragen, fertig.</span>
				<span class="fgp-cta"><?php echo esc_html( (string) $fge_live_count ); ?> Events entdecken
					<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>
			</span>
		</a>
		<a class="fgp-card" href="<?php echo esc_url( $url_ind ); ?>">
			<img src="<?php echo esc_url( $img( 'erfahrung-inselgruen.jpg' ) ); ?>" alt="" loading="lazy">
			<span class="fgp-scrim" aria-hidden="true"></span>
			<span class="fgp-body">
				<span class="fgp-tag">Nach Maß</span>
				<span class="fgp-h">Individuell geplant</span>
				<span class="fgp-p">Eigene Vorstellung? Schickt uns Anlass, Gruppe und Wunschregion, wir kuratieren passende Plätze für euer Unternehmen.</span>
				<span class="fgp-cta">Individuell anfragen
					<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>
			</span>
		</a>
	</div>
</section>

<?php /* ══════════════════ 3. HOW IT WORKS ══════════════════
	(Scroll-Pin-Experiment am 27.08. wieder entfernt, Julius: fühlte sich nicht gut an.
	Mobil bleibt die normale Wischreihe.) */ ?>
<section class="mk-section mk-steps mk-band cty-reveal" aria-label="So funktioniert es">
	<div class="mk-section-head">
		<h2 class="mk-h2">Drei Schritte. Ein Ansprechpartner.</h2>
		<p class="mk-sub">Wir kümmern uns um Platzwahl, Koordination und Abrechnung. Ihr kümmert euch ums Team.</p>
	</div>
	<div class="mk-steps-grid">
		<?php
		/* Schritt 2 ohne Werktags-Versprechen, Schritt 3 endet beim Ergebnis (Julius, 27.08.). */
		$steps = [
			[ '01', 'Ihr sagt uns, was ihr plant.',              'Anlass, Gruppe, Zeitraum. Eine Anfrage, mehr brauchen wir nicht.' ],
			[ '02', 'Wir finden das passende Angebot für euch.', 'Golfplatz, Golflehrer, Gastronomie und Meetingraum: Wir stellen zusammen, was zu eurer Veranstaltung passt.' ],
			[ '03', 'Ihr habt einen tollen Teamtag.',            'Ein Ansprechpartner, eine Rechnung. Der Platz organisiert vor Ort, ihr seid nur Gastgeber.' ],
		];
		foreach ( $steps as $step ) : ?>
			<?php /* Ganze Karte klickbar zur 30-Sekunden-Anfrage (Julius, 2026-07-06) */ ?>
			<a class="mk-step" href="<?php echo esc_url( add_query_arg( 'anfrage', 'quick', $url_ind ) ); ?>">
				<div class="mk-step-n"><?php echo esc_html( $step[0] ); ?></div>
				<h3 class="mk-step-t"><?php echo esc_html( $step[1] ); ?></h3>
				<p class="mk-step-b"><?php echo esc_html( $step[2] ); ?></p>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 4. FORMATE: ALLE Eventarten als Slider ══════════════════ */
/* Alle Eventarten in einer wischbaren Reihe (Julius, 2026-08-27). Eventarten MIT
   fertigen Events verlinken auf die gefilterte Eventliste; die ohne (Incentive,
   Indoor Golf) springen mit Begrüßung + vorgewähltem Anlass in die Schnellanfrage. */
$home_fmt_tiles = [
	[ 't' => 'Teamevent',        'sub' => 'Spielerisch zusammenwachsen',     'img' => 'firmenevent-afterwork-golf.jpg',      'url' => add_query_arg( 'format', 'teamevent', $url_events ) ],
	[ 't' => 'After-Work Golf',  'sub' => 'Der Feierabend im Grünen',        'img' => 'pool/pool-hochformat-afterwork-laessiger-golfeinsteiger-macht-erste-schwuenge.jpg', 'url' => add_query_arg( 'format', 'after_work_golf', $url_events ) ],
	[ 't' => 'Workshop & Golf',  'sub' => 'Arbeiten, wo der Kopf frei ist',  'img' => 'pool/workshop-clubhaus-aussen.jpg',   'url' => add_query_arg( 'format', 'workshop', $url_events ) ],
	[ 't' => 'Firmenturnier',    'sub' => 'Flights, Pokale & Siegerehrung',  'img' => 'golf-gruen-fahne.jpg',                'url' => add_query_arg( 'format', 'firmen_golfturnier', $url_events ) ],
	[ 't' => 'Platzreife',       'sub' => 'Gemeinsam zur Platzreife',        'img' => 'golf-coaching-gruppe.jpg',            'url' => add_query_arg( 'format', 'platzreife', $url_events ) ],
	[ 't' => 'Kundenevent',      'sub' => 'Golf, Dinner & echte Gespräche',  'img' => 'pool/kundenevent-handshake.jpg',      'url' => add_query_arg( 'format', 'kundenevent', $url_events ) ],
	[ 't' => 'Incentive',        'sub' => 'Belohnung mit Erinnerungswert',   'img' => 'golfplatz-luftaufnahme-2.jpg',        'url' => add_query_arg( [ 'anfrage' => 'quick', 'anlass' => 'Incentive-Reise', 'intro' => '1' ], $url_ind ) ],
	[ 't' => 'Indoor Golf',      'sub' => 'Ganzjährig & wetterfest',         'img' => 'onboarding-indoor-lounge.jpg',        'url' => add_query_arg( 'format', 'indoor-golf', $url_events ) ],
];
?>
<section class="mk-section cty-reveal" aria-label="Das passende Format">
	<div class="mk-section-head">
		<h2 class="mk-h2">Das passende Format für euer <em class="mk-italic">Team</em>.</h2>
		<p class="mk-sub">Erprobte Formate für jeden Anlass: was drinsteckt, für wen es passt und was es kostet.</p>
	</div>
	<div class="iv-tiles home-fmt-slider">
		<?php foreach ( $home_fmt_tiles as $tile ) : ?>
			<a class="iv-tile" href="<?php echo esc_url( $tile['url'] ); ?>">
				<span class="iv-tile-img" style="background-image:url('<?php echo esc_url( $img( $tile['img'] ) ); ?>')"></span>
				<span class="iv-tile-scrim"></span>
				<span class="iv-tile-label">
					<span>
						<span class="iv-tile-t"><?php echo esc_html( $tile['t'] ); ?></span>
						<span class="iv-tile-sub" style="display:block;"><?php echo esc_html( $tile['sub'] ); ?></span>
					</span>
					<span class="iv-tile-arrow"><?php echo function_exists( 'fge_icon_arrow_right' ) ? fge_icon_arrow_right() : '→'; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 6. INDIVIDUAL TEASER ══════════════════ */ ?>
<section class="mk-section home-individual cty-reveal" aria-label="Individuelle Events">
	<div class="home-individual-grid">
		<?php /* Statt Foto (zu viele Bilder, Julius 28.08.): gezeichnete Baukasten-Komposition
			in der Designsprache der Standort-Illustration. Die Leistungs-Bausteine schweben
			um „Euer Event", gestaffeltes Einblenden über cty-reveal. */ ?>
		<div class="home-ind-visual" aria-hidden="true">
			<svg class="hiv-rings" viewBox="0 0 420 420" fill="none">
				<circle cx="210" cy="210" r="96"  stroke="var(--fairway-200)" stroke-width="1.5"/>
				<circle cx="210" cy="210" r="150" stroke="var(--ink-200)" stroke-width="1.2" stroke-dasharray="3 6"/>
				<circle cx="210" cy="210" r="198" stroke="var(--ink-100)" stroke-width="1" stroke-dasharray="2 7"/>
			</svg>
			<div class="hiv-core">
				<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 21V4l11 3.5L6 11"/><path d="M6 21h6"/></svg>
				<b>Euer Event</b>
				<i>nach Maß geplant</i>
			</div>
			<span class="hiv-chip hiv-c1"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>Golfplatz</span>
			<span class="hiv-chip hiv-c2"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/></svg>Golflehrer</span>
			<span class="hiv-chip hiv-c3"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4a8 8 0 0 1 8 8H4a8 8 0 0 1 8-8z"/><path d="M3 15h18"/><path d="M12 2v2"/></svg>Gastronomie</span>
			<span class="hiv-chip hiv-c4"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M12 16v4M8 20h8"/><path d="M7 8h6M7 11h4"/></svg>Meetingraum</span>
			<span class="hiv-chip hiv-c5"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="11" rx="2"/><path d="M3 11h18"/><circle cx="7.5" cy="18.5" r="1.6"/><circle cx="16.5" cy="18.5" r="1.6"/></svg>Shuttle</span>
			<span class="hiv-chip hiv-c6"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V6l10-2v11"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="15" r="2.5"/></svg>Musik &amp; DJ</span>
		</div>
		<div class="home-ind-text">
			<h2 class="mk-h2">
				Nichts dabei? <em class="mk-italic">Wir planen</em> euer Event nach euren Ansprüchen.
			</h2>
			<p class="mk-sub">
				Sonderwünsche, eigene Location, mehrtägiges Programm, internationale Gruppe? Beschreibt uns kurz,
				was ihr vorhabt. Wir bauen das Format für euch und schlagen die passenden Plätze vor.
			</p>
			<div class="home-ind-points">
				<div><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Persönliche Beratung in einem Werktag</span></div>
				<div><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Maßgeschneidertes Programm</span></div>
				<div><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Ein Ansprechpartner, eine Rechnung</span></div>
			</div>
			<div class="home-ind-ctas">
				<a class="fg-btn-cta" href="<?php echo esc_url( add_query_arg( 'anfrage', 'full', $url_ind ) ); ?>">
					Event anfragen
					<span class="fg-arrow"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
				<a class="fg-btn-ghost" href="<?php echo esc_url( $url_kontakt ); ?>">
					Mit uns sprechen
				</a>
			</div>
		</div>
	</div>
</section>

<?php /* ══════════════════ 7. BENEFIT TEASER ══════════════════ */ ?>
<section class="home-benefit cty-reveal" aria-label="Corporate Benefit">
	<div class="home-benefit-inner">
		<div class="home-benefit-eyebrow">Corporate Benefit · firmen.golf</div>
		<h2 class="home-benefit-h">
			Golf als Benefit, den eure Mitarbeitenden <em class="mk-italic">spüren</em>.
		</h2>
		<p class="home-benefit-sub">
			50 € steuerfreier Sachbezug pro Monat, Zugang zu Partnerplätzen, Coaching-Stunden zum Mitarbeiterpreis.
			Bewegung statt Obstkorb. Und die HR-Abrechnung läuft sauber.
		</p>
		<div class="home-benefit-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="https://firmen.golf" target="_blank" rel="noopener noreferrer"
			   style="background:var(--paper-100);color:var(--fairway-900)">
				Zum Benefit-Programm
				<span class="fg-arrow" style="background:var(--fairway-200)"><?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</a>
			<span class="home-benefit-tag">firmen.golf ↗</span>
		</div>
	</div>
</section>

<?php /* ══════════════════ 8. FOUNDER + VERSPRECHEN ══════════════════ */ ?>
<?php /* Echtes Gesicht statt anonymer Kaertchen-Reihe (2026-08-20): dieselben vier
	     Versprechen, aber als Liste neben dem Ansprechpartner, der sie einloest. */ ?>
<section class="cty-founder" aria-label="Euer Ansprechpartner">
	<div class="cty-founder-inner cty-reveal">
		<div class="cty-founder-photo">
			<img src="<?php echo esc_url( $img( 'gruender-julius-klinzer.jpg' ) ); ?>"
			     alt="Julius Klinzer, Gründer von Firmengolf" loading="lazy">
		</div>
		<div class="cty-founder-body">
			<h2 class="mk-h2">Worauf ihr euch <span class="mk-italic">verlassen</span> könnt.</h2>
			<p class="cty-founder-p">
				Ich bin Julius, Gründer von Firmengolf. Ich kenne unsere Partnerplätze persönlich
				und plane euer Event mit euch, vom ersten Vorschlag bis zur Rechnung.
			</p>
			<ul class="cty-story-points">
				<?php
				// Bewusst ohne Icons (Julius, 2026-08-20): die Versprechen tragen selbst.
				$promises = [
					[ 't' => 'Wir beraten euch persönlich', 'b' => 'Kein Ticketsystem. Ihr sprecht direkt mit dem, der euer Event plant.' ],
					[ 't' => 'Eine Anfrage, eine Rechnung', 'b' => 'Platz, Pro, Catering, Shuttle, alles über einen Ansprechpartner, sauber für HR und Buchhaltung.' ],
					[ 't' => 'Auch ohne Golferfahrung', 'b' => 'Golflehrer führen Einsteiger an, Schläger werden gestellt. Niemand muss spielen können.' ],
					[ 't' => 'Deutschlandweit organisierbar', 'b' => 'Über 500 Locations kommen für euer Event in Frage, vom Golfplatz bis zum Simulator. Passt eine nicht, nehmen wir die nächste.' ],
				];
				foreach ( $promises as $p ) : ?>
				<li class="cty-story-point">
					<div>
						<h3><?php echo esc_html( $p['t'] ); ?></h3>
						<p><?php echo esc_html( $p['b'] ); ?></p>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<div class="cty-founder-ctas">
				<a class="fg-btn-brand" href="<?php echo esc_url( add_query_arg( 'anfrage', 'quick', $url_ind ) ); ?>">Event anfragen</a>
				<a class="cty-founder-mail" href="mailto:julius@firmengolf-events.de">julius@firmengolf-events.de</a>
			</div>
		</div>
	</div>
</section>

<?php /* ══════════════════ 9. CLOSING CTA ══════════════════
	Ohne Städte-Linkliste (Julius, 2026-08-28): der Footer direkt darunter listet
	bereits alle Orte. Blog-Teaser-Sektion entfernt (Julius, 2026-08-28). */
get_template_part( 'template-parts/fge-putt-cta', null, [
	'headline_html' => 'Lasst uns euer nächstes Event <em class="mk-italic">zusammen</em> planen.',
	'sub'           => 'Schickt uns eure Eckdaten in 30 Sekunden. Innerhalb eines Werktags habt ihr konkrete Vorschläge, kostenlos und unverbindlich.',
	'anfrage_url'   => add_query_arg( 'anfrage', 'quick', $url_ind ),
	'links_html'    => '<a href="mailto:' . esc_attr( fge_company()['email_events'] ) . '">' . esc_html( fge_company()['email_events'] ) . '</a>',
] );
?>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
(function () {
  'use strict';

  function initDropdown(cellId, panelId, inputId, displayId) {
    var cell    = document.getElementById(cellId);
    var panel   = document.getElementById(panelId);
    var input   = document.getElementById(inputId);
    var display = document.getElementById(displayId);
    if (!cell || !panel) return;

    function open()  { panel.classList.add('is-open');    cell.setAttribute('aria-expanded', 'true'); }
    function close() { panel.classList.remove('is-open'); cell.setAttribute('aria-expanded', 'false'); }

    cell.addEventListener('click', function (e) {
      if (panel.contains(e.target)) return;
      panel.classList.contains('is-open') ? close() : open();
    });
    cell.addEventListener('keydown', function (e) {
      /* Nur auf der Zelle selbst, sonst blockiert preventDefault die Options-Auswahl per Enter */
      if ((e.key === 'Enter' || e.key === ' ') && e.target === cell) {
        e.preventDefault();
        panel.classList.contains('is-open') ? close() : open();
      }
      if (e.key === 'Escape') { close(); cell.focus(); }
    });
    panel.querySelectorAll('.fg-search-panel-opt').forEach(function (opt) {
      opt.addEventListener('click', function (e) {
        e.stopPropagation();
        if (input)   input.value = opt.dataset.value;
        if (display) display.textContent = opt.dataset.label;
        panel.querySelectorAll('.fg-search-panel-opt').forEach(function (o) {
          o.classList.toggle('is-selected', o.dataset.value === opt.dataset.value);
        });
        close();
      });
    });
    document.addEventListener('click', function (e) {
      if (!cell.contains(e.target)) close();
    });
  }

  initDropdown('qs-format-cell', 'qs-format-panel', 'qs-format-val', 'qs-format-text');

  /* ── Location picker (identisch zur Events-Seite) ── */
  function initLocationPicker() {
    var cell = document.getElementById('qs-loc-cell');
    var panel = document.getElementById('qs-loc-panel');
    if (!cell || !panel) return;
    var input = document.getElementById('qs-loc-input');
    var suggest = document.getElementById('qs-loc-suggest');
    var gps = document.getElementById('qs-loc-gps');
    var latEl = document.getElementById('qs-lat');
    var lngEl = document.getElementById('qs-lng');
    var radEl = document.getElementById('qs-radius');
    var locEl = document.getElementById('qs-loc-val');
    var form = cell.closest('form');
    var ajax = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    function open() { panel.classList.add('is-open'); cell.setAttribute('aria-expanded', 'true'); setTimeout(function () { input && input.focus(); }, 30); }
    function close() { panel.classList.remove('is-open'); cell.setAttribute('aria-expanded', 'false'); }

    /* Direkteingabe: das Eingabefeld sitzt IN der Zelle. Klick/Fokus oeffnet das
       Panel (Standort, Vorschlaege, Umkreis); Klicks ins Feld schliessen nichts. */
    if (input) { input.addEventListener('focus', open); }
    cell.addEventListener('click', function (e) {
      if (panel.contains(e.target)) return;
      if (input && (e.target === input)) { open(); return; }
      panel.classList.contains('is-open') ? close() : open();
    });
    document.addEventListener('click', function (e) { if (!cell.contains(e.target)) close(); });
    cell.addEventListener('keydown', function (e) {
      if ((e.key === 'Enter' || e.key === ' ') && e.target === cell) {
        e.preventDefault();
        panel.classList.contains('is-open') ? close() : open();
      }
      if (e.key === 'Escape') { close(); cell.focus(); }
    });

    function setLocation(lat, lng, label) {
      latEl.value = lat; lngEl.value = lng; locEl.value = label;
      if (input) input.value = label;
      close(); // Startseite: KEIN Auto-Submit, Weiterleitung erst bei Klick auf "Suchen".
    }

    var t = null;
    if (input) input.addEventListener('input', function () {
      var q = input.value.trim();
      clearTimeout(t);
      if (q.length < 2) { suggest.innerHTML = ''; return; }
      t = setTimeout(function () {
        fetch(ajax + '?action=fge_geo_suggest&q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (res) {
            suggest.innerHTML = '';
            if (!res || !res.success) return;
            res.data.forEach(function (s) {
              var b = document.createElement('button');
              b.type = 'button';
              b.className = 'fg-loc-opt';
              b.setAttribute('role', 'option');
              b.textContent = s.label;
              b.addEventListener('click', function () { suggest.innerHTML = ''; input.value = s.label; setLocation(s.lat, s.lng, s.label); });
              suggest.appendChild(b);
            });
          })
          .catch(function () { suggest.innerHTML = ''; });
      }, 220);
    });

    if (gps) gps.addEventListener('click', function () {
      if (!navigator.geolocation) { alert('Standort wird vom Browser nicht unterstützt.'); return; }
      gps.disabled = true; gps.classList.add('is-loading');
      navigator.geolocation.getCurrentPosition(function (pos) {
        gps.disabled = false; gps.classList.remove('is-loading');
        setLocation(pos.coords.latitude.toFixed(5), pos.coords.longitude.toFixed(5), 'Mein Standort');
      }, function () {
        gps.disabled = false; gps.classList.remove('is-loading');
        alert('Standort konnte nicht ermittelt werden. Bitte Freigabe erlauben oder Ort eingeben.');
      }, { enableHighAccuracy: false, timeout: 8000 });
    });

    panel.querySelectorAll('.fg-loc-rb').forEach(function (btn) {
      btn.addEventListener('click', function () {
        panel.querySelectorAll('.fg-loc-rb').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        radEl.value = btn.getAttribute('data-r');
      });
    });
  }
  initLocationPicker();

  var paxDisplay = document.getElementById('qs-pax-display');
  var paxVal     = document.getElementById('qs-pax-val');
  var decBtn     = document.querySelector('#qs-pax-display')?.closest('.fg-pax-cell')?.querySelector('[data-fn="dec"]');
  var incBtn     = document.querySelector('#qs-pax-display')?.closest('.fg-pax-cell')?.querySelector('[data-fn="inc"]');

  function updatePax(next) {
    next = Math.max(1, next);
    paxVal.value = next;
    paxDisplay.textContent = next + ' Pers.';
    if (decBtn) decBtn.disabled = next <= 1;
  }

  if (incBtn) incBtn.addEventListener('click', function () { updatePax((parseInt(paxVal.value) || 10) + 1); });
  if (decBtn) decBtn.addEventListener('click', function () { updatePax((parseInt(paxVal.value) || 10) - 1); });

  /* ── Hero: rotierendes Satzende (mit Artikel, damit die Grammatik stimmt) ── */
  var rotEl = document.getElementById('fg-rot-word');
  if (rotEl && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
    /* Artikel (weiß) + Schlüsselwort (mint) getrennt, damit nur das Keyword farbig ist */
    var rotWords = [['zur ', 'Eventlocation'], ['zum ', 'Afterwork-Spot'], ['zur ', 'Turnier-Bühne'], ['zum ', 'Team-Erlebnis'], ['zur ', 'Sommerfest-Location']];
    var artEl = rotEl.querySelector('.rot-art');
    var keyEl = rotEl.querySelector('.rot-key');
    var rotI = 0;
    setInterval(function () {
      rotEl.classList.remove('in');
      rotEl.classList.add('out');
      setTimeout(function () {
        rotI = (rotI + 1) % rotWords.length;
        if (artEl && keyEl) {
          artEl.textContent = rotWords[rotI][0];
          keyEl.textContent = rotWords[rotI][1];
        } else {
          rotEl.textContent = rotWords[rotI][0] + rotWords[rotI][1];
        }
        rotEl.classList.remove('out');
        rotEl.classList.add('in');
      }, 380);
    }, 3200);
  }

  /* Sanftes Einblenden der Sektionen beim Scrollen (wie die Landingpages):
     greift nur unter html.cty-io, ohne JS oder mit reduzierter Bewegung
     bleibt alles sofort sichtbar. */
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
    document.documentElement.classList.add('cty-io');
    var fgIo = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('is-in'); fgIo.unobserve(e.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px' });
    document.querySelectorAll('.cty-reveal').forEach(function (el) { fgIo.observe(el); });
  }
}());
</script>

<?php get_footer(); ?>
