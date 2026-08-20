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
$formats = array_merge( [ 'all' => 'Alle Typen' ], fge_get_event_formats_flat( false ) );

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
	'numberposts'    => 3,
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
$mbar_action = '<a class="ev-msearch" href="' . esc_url( get_post_type_archive_link( 'firmengolf_event' ) ) . '"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg><span class="ev-msearch-t muted">Events suchen</span></a>';
get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '', 'mbar_action' => $mbar_action ] );
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
				<a class="fg-btn-cta fg-btn-lg" href="<?php echo esc_url( add_query_arg( 'anfrage', 'quick', $url_ind ) ); ?>">
					Event anfragen
					<span class="fg-arrow"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
				<a class="fg-btn-ghost-light" href="<?php echo esc_url( $url_events ); ?>">
					Events entdecken →
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

		<?php /* Format dropdown */ ?>
		<div class="fg-search-cell fg-format-cell" id="qs-format-cell"
		     tabindex="0" role="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Format wählen">
			<div class="fg-cell-label">Veranstaltungstyp</div>
			<div class="fg-cell-value" id="qs-format-display">
				<span id="qs-format-text">Alle Typen</span>
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

		<?php /* Wo?, Ort/PLZ-Autocomplete + Standort + Umkreis (identisch zur Events-Seite) */ ?>
		<div class="fg-search-cell fg-loc-cell" id="qs-loc-cell"
		     tabindex="0" role="button" aria-haspopup="dialog" aria-expanded="false" aria-label="Ort oder PLZ wählen">
			<div class="fg-cell-label">Wo?</div>
			<div class="fg-cell-value fg-muted" id="qs-loc-display">
				<span id="qs-loc-text">Ort oder PLZ</span>
			</div>
			<input type="hidden" name="lat"    id="qs-lat"     value="">
			<input type="hidden" name="lng"    id="qs-lng"     value="">
			<input type="hidden" name="radius" id="qs-radius"  value="50">
			<input type="hidden" name="loc"    id="qs-loc-val" value="">
			<div class="fg-search-panel fg-loc-panel" id="qs-loc-panel" role="dialog" aria-label="Ort und Umkreis">
				<div class="fg-loc-search">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
					<input type="text" id="qs-loc-input" placeholder="Ort oder PLZ" autocomplete="off" value="">
				</div>
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

<?php /* ══════════════════ 2b. WARUM GOLFPLATZ (interaktiver Showcase) ══════════════════ */
/* Rote Linie 2026-08-20: Die These der Marke, bildgefuehrt. Vier Argumente als
   Liste, das grosse Bild wechselt beim Antippen mit Crossfade und ruhigem Zoom. */
$why_items = [
	[ 'ic' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
	  'k' => 'Draußen in Bewegung', 't' => 'Ein ganzer Tag an der frischen Luft: Bewegung, ohne dass es nach Sport aussieht.',
	  'img' => 'pool/afterwork-happy-im-cart.jpg', 'alt' => 'Team unterwegs im Golfcart' ],
	[ 'ic' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
	  'k' => 'Für alle machbar', 't' => 'Flache Wege, Carts, ein Pro für die ersten Schläge. Kein Fitnesslevel, kein Handicap nötig.',
	  'img' => 'golf-coaching-gruppe.jpg', 'alt' => 'Golflehrer führt eine Gruppe Einsteiger an' ],
	[ 'ic' => '<path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 8h.01M15 8h.01M9 12h.01M15 12h.01M10 21v-4h4v4"/>',
	  'k' => 'Clubhaus & Meetingräume', 't' => 'Moderne Räume für Empfang, Präsentation oder Workshop, direkt am Grün.',
	  'img' => 'pool/workshop-clubhaus-aussen.jpg', 'alt' => 'Clubhaus mit Tagungsräumen am Grün' ],
	[ 'ic' => '<path d="M3 2v7c0 1.1.9 2 2 2s2-.9 2-2V2"/><path d="M5 11v11"/><path d="M18 2c-1.7 0-3 2.7-3 6 0 2.6 1.1 4 3 4v10"/>',
	  'k' => 'Küche & Terrasse', 't' => 'Vom Business-Lunch bis zum Grillabend, die Gastronomie vor Ort trägt jeden Anlass.',
	  'img' => 'pool/afterwork-anstossen.jpg', 'alt' => 'Team stößt auf der Clubterrasse an' ],
];
?>
<section class="mk-section fgw cty-reveal" aria-label="Warum der Golfplatz die perfekte Event-Location ist">
	<div class="mk-section-head">
		<h2 class="mk-h2">Warum sich der Golfplatz perfekt für euer <span class="mk-italic">Firmenevent</span> eignet.</h2>
		<p class="mk-sub">Bewegung an der frischen Luft, moderne Clubhäuser mit Meetingräumen und eine Gastronomie, die jeden Anlass trägt, offen für alle, ganz ohne Golf-Vorkenntnisse.</p>
	</div>
	<div class="fgw-grid">
		<div class="fgw-list">
			<?php foreach ( $why_items as $wi => $w ) : ?>
			<button type="button" class="fgw-item<?php echo 0 === $wi ? ' is-active' : ''; ?>" data-fgw="<?php echo (int) $wi; ?>" aria-pressed="<?php echo 0 === $wi ? 'true' : 'false'; ?>">
				<span class="fgw-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?php echo $w['ic']; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></svg></span>
				<span class="fgw-body"><b><?php echo esc_html( $w['k'] ); ?></b><span><?php echo esc_html( $w['t'] ); ?></span></span>
			</button>
			<?php endforeach; ?>
		</div>
		<div class="fgw-media" aria-hidden="true">
			<?php foreach ( $why_items as $wi => $w ) : ?>
			<img src="<?php echo esc_url( $img( $w['img'] ) ); ?>" alt="<?php echo esc_attr( $w['alt'] ); ?>"
			     class="<?php echo 0 === $wi ? 'is-active' : ''; ?>" data-fgw-img="<?php echo (int) $wi; ?>" loading="lazy">
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php /* ══════════════════ 2c. ZWEI WEGE ══════════════════ */ ?>
<section class="mk-section cty-reveal" aria-label="Zwei Wege zu eurem Event">
	<div class="mk-section-head">
		<h2 class="mk-h2">Zwei Wege zu eurem Event.</h2>
		<p class="mk-sub"><?php echo esc_html( (string) $fge_live_count ); ?> Events sind sofort anfragbar, alles andere bauen wir nach Maß.</p>
	</div>
	<div class="home-loc-paths">
		<?php
		$loc_paths = [
			[
				'img'   => 'firmenevent-afterwork-golf.jpg',
				'alt'   => 'Team stößt nach dem Firmenevent auf dem Golfplatz an',
				'tag'   => 'Sofort buchbar',
				'k'     => 'Vorgeplante Partner-Events',
				't'     => 'Fertige Formate auf unseren Partnerplätzen, mit Datum, Preis und Ablauf. Aussuchen, anfragen, fertig.',
				'cta'   => 'Events entdecken',
				'href'  => $url_events,
			],
			[
				'img'   => 'clubhaus-aussenansicht.jpg',
				'alt'   => 'Modernes Golf-Clubhaus als Event-Location',
				'tag'   => 'Nach Maß',
				'k'     => 'Individuell geplant',
				't'     => 'Eigene Vorstellung? Schickt uns Anlass, Gruppe und Wunschregion, wir kuratieren passende Plätze für euer Unternehmen.',
				'cta'   => 'Individuell anfragen',
				'href'  => $url_ind,
			],
		];
		foreach ( $loc_paths as $path ) : ?>
			<a class="home-loc-path" href="<?php echo esc_url( $path['href'] ); ?>">
				<div class="home-loc-path-photo">
					<span class="home-loc-path-img" style="background-image:url('<?php echo esc_url( $img( $path['img'] ) ); ?>')" role="img" aria-label="<?php echo esc_attr( $path['alt'] ); ?>"></span>
					<span class="home-loc-path-tag"><?php echo esc_html( $path['tag'] ); ?></span>
				</div>
				<div class="home-loc-path-body">
					<h4 class="home-loc-path-k"><?php echo esc_html( $path['k'] ); ?></h4>
					<p class="home-loc-path-t"><?php echo esc_html( $path['t'] ); ?></p>
					<span class="home-loc-path-cta"><?php echo esc_html( $path['cta'] ); ?> <?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 3. HOW IT WORKS ══════════════════ */ ?>
<section class="mk-section mk-steps mk-band cty-reveal" aria-label="So funktioniert es">
	<div class="mk-section-head">
		<h2 class="mk-h2">Drei Schritte. Ein Ansprechpartner.</h2>
		<p class="mk-sub">Wir kümmern uns um Platzwahl, Koordination und Abrechnung. Ihr kümmert euch ums Team.</p>
	</div>
	<div class="mk-steps-grid">
		<?php
		$steps = [
			[ '01', 'Ihr sagt uns, was ihr plant.',      'Anlass, Gruppe, Zeitraum. Eine Anfrage, mehr brauchen wir nicht.' ],
			[ '02', 'Wir kuratieren passende Plätze.',  'Innerhalb eines Werktags bekommt ihr zwei bis drei Optionen mit Format, Preis und Verfügbarkeit.' ],
			[ '03', 'Ihr wählt, wir koordinieren.',     'Ein Ansprechpartner, eine Rechnung. Der Platz organisiert vor Ort, ihr seid nur Gastgeber.' ],
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

<?php /* ══════════════════ 4. FORMATE-BENTO (ersetzt Formate + Anlass-Kacheln) ══════════════════ */
/* Ein Produkt-Explorer statt zwei redundanter Karten-Sektionen (rote Linie 2026-08-20):
   Foto-Kacheln wie auf den Landingpages, Titel = Format, Text = Anlass-Nutzen,
   Links auf die deutschlandweiten Format-Seiten. */
$fmt_pages = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];
$home_fmts = [
	'teamevent'       => 'Einen gemeinsamen Tag draußen verbringen, ganz ohne Vorkenntnisse.',
	'golfturnier'     => 'Wettbewerb, der verbindet: Flights, faire Formate, Siegerehrung.',
	'platzreife'      => 'Über mehrere Tage gemeinsam lernen und bestehen.',
	'workshop'        => 'Arbeiten, wo der Kopf frei ist, mit Golf zum Ausklang.',
	'kundenevent'     => 'Gespräche, die im Konferenzraum nie entstehen, mit Hospitality und Dinner.',
	'incentive'       => 'Besondere Kulissen und bleibende Erinnerungen für eure Top-Leute.',
	'after-work-golf' => 'Nach Feierabend auf Range und Kurzplatz, locker angeleitet.',
];
?>
<section class="mk-section cty-reveal" aria-label="Das passende Format">
	<div class="mk-section-head between">
		<div>
			<h2 class="mk-h2">Das passende Format für euer Team.</h2>
			<p class="mk-sub">Sieben erprobte Formate, jedes mit eigener Seite: was drinsteckt, für wen es passt und was es kostet.</p>
		</div>
		<a class="fg-btn-ghost" href="<?php echo esc_url( $url_events ); ?>">
			Alle <?php echo $fge_live_count > 4 ? esc_html( (string) $fge_live_count ) . ' ' : ''; ?>Events ansehen <?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>
	</div>
	<div class="cty-fmt-bento">
		<?php $hf_i = 0; foreach ( $home_fmts as $fslug => $benefit ) :
			$fm = $fmt_pages[ $fslug ] ?? null;
			if ( ! $fm ) { continue; }
		?>
			<a class="cty-fmt-tile<?php echo 0 === $hf_i ? ' is-wide' : ''; ?>" href="<?php echo esc_url( home_url( '/firmenevent/' . $fslug . '/' ) ); ?>">
				<img src="<?php echo esc_url( $img( $fm['hero_img'] ?? 'golfplatz-panorama.jpg' ) ); ?>" alt="" loading="lazy">
				<span class="cty-fmt-tile-scrim" aria-hidden="true"></span>
				<span class="cty-fmt-tile-txt">
					<span class="cty-fmt-tile-h"><?php echo esc_html( $fm['name'] ?? $fslug ); ?></span>
					<span class="cty-fmt-tile-p"><?php echo esc_html( $benefit ); ?></span>
					<span class="cty-fmt-tile-go"><?php echo esc_html( $fm['name'] ?? '' ); ?> ansehen
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
					</span>
				</span>
			</a>
		<?php $hf_i++; endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 6. INDIVIDUAL TEASER ══════════════════ */ ?>
<section class="mk-section home-individual cty-reveal" aria-label="Individuelle Events">
	<div class="home-individual-grid">
		<div class="home-ind-photo" style="background-image:url('<?php echo esc_url( $img( 'golfplatz-luftaufnahme-2.jpg' ) ); ?>')"></div>
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
				$promises = [
					[ 'ic' => '<path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
					  't' => 'Ein echter Mensch am Telefon', 'b' => 'Kein Ticketsystem. Ihr sprecht direkt mit dem, der euer Event plant.' ],
					[ 'ic' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
					  't' => 'Eine Anfrage, eine Rechnung', 'b' => 'Platz, Pro, Catering, Shuttle, alles über einen Ansprechpartner, sauber für HR und Buchhaltung.' ],
					[ 'ic' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
					  't' => 'Auch ohne Golferfahrung', 'b' => 'Golflehrer führen Einsteiger an, Schläger werden gestellt. Niemand muss spielen können.' ],
					[ 'ic' => '<path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/>',
					  't' => 'Deutschlandweit organisierbar', 'b' => 'Über 500 Locations kommen für euer Event in Frage, vom Golfplatz bis zum Simulator. Passt eine nicht, nehmen wir die nächste.' ],
				];
				foreach ( $promises as $p ) : ?>
				<li class="cty-story-point">
					<span class="cty-story-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?php echo $p['ic']; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></svg></span>
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

<?php /* ══════════════════ 10. BLOG TEASER ══════════════════ */ ?>
<section class="mk-section cty-reveal" aria-label="Aus dem Magazin">
	<div class="mk-section-head between">
		<div>
			<h2 class="mk-h2">Was wir grad denken &amp; schreiben.</h2>
		</div>
		<a class="fg-btn-ghost" href="<?php echo esc_url( $url_blog ); ?>">
			Alle Artikel <?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>
	</div>
	<div class="home-blog-grid">
		<?php if ( ! empty( $blog_posts ) ) : ?>
			<?php foreach ( $blog_posts as $post ) :
				$thumb = has_post_thumbnail( $post->ID ) ? get_the_post_thumbnail_url( $post->ID, 'large' ) : $img( 'golf-coaching-gruppe.jpg' );
				$tags  = wp_get_post_tags( $post->ID, [ 'fields' => 'names' ] );
				$tag   = ! empty( $tags ) ? $tags[0] : '';
				$words = str_word_count( wp_strip_all_tags( $post->post_content ) );
				$read  = max( 1, (int) round( $words / 200 ) ) . ' Min.';
			?>
				<article class="home-blog-card">
					<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" style="display:contents">
						<div class="home-blog-photo" style="background-image:url('<?php echo esc_url( $thumb ); ?>')"></div>
						<div class="home-blog-body">
							<div class="home-blog-meta">
								<?php if ( $tag ) : ?>
									<span class="home-blog-tag"><?php echo esc_html( $tag ); ?></span>
									<span>·</span>
								<?php endif; ?>
								<span><?php echo esc_html( $read ); ?></span>
							</div>
							<h3 class="home-blog-t"><?php echo esc_html( $post->post_title ); ?></h3>
							<p class="home-blog-x"><?php echo esc_html( wp_trim_words( $post->post_excerpt ?: wp_strip_all_tags( $post->post_content ), 20 ) ); ?></p>
							<div class="home-blog-author">
								<?php /* Redaktions-Byline statt WP-Loginname (Audit 2026-08-12: 3x „julius") */ ?>
								<span class="home-blog-author-name"><?php echo esc_html( function_exists( 'fge_blog_author' ) ? fge_blog_author( (int) $post->ID )['name'] : get_the_author_meta( 'display_name', (int) $post->post_author ) ); ?></span>
								<span class="home-blog-author-date"><?php echo esc_html( get_the_date( 'j. F Y', $post ) ); ?></span>
							</div>
						</div>
					</a>
				</article>
			<?php endforeach; ?>
		<?php else : ?>
			<?php
			$static_posts = [
				[ 'Benefits', 'Warum Golf zum Corporate Benefit passt', 'golf-coaching-gruppe.jpg', '6 Min.', 'Julius Klinzer', '14. Mai 2026', '50 € steuerfreier Sachbezug und fittere Mitarbeitende. Wir erklären die wichtigsten Punkte.' ],
				[ 'Praxis', 'Die 12-Punkte-Checkliste für dein erstes Firmen-Golfevent', 'firmenevent-afterwork-golf.jpg', '4 Min.', 'Julius Klinzer', '2. Mai 2026', 'Vom richtigen Zeitfenster bis zum Wetter-Backup. Das solltest du im Blick haben.' ],
				[ 'Einsteiger', '"Aber wir können doch alle nicht Golf spielen"', 'golfplatz-rasen-qualitaet.jpg', '5 Min.', 'Julius Klinzer', '18. April 2026', 'Genau das ist der Punkt. Wie ein Schnupperkurs für absolute Einsteigende funktioniert.' ],
			];
			foreach ( $static_posts as $p ) : ?>
				<article class="home-blog-card">
					<div class="home-blog-photo" style="background-image:url('<?php echo esc_url( $img( $p[2] ) ); ?>')"></div>
					<div class="home-blog-body">
						<div class="home-blog-meta">
							<span class="home-blog-tag"><?php echo esc_html( $p[0] ); ?></span>
							<span>·</span>
							<span><?php echo esc_html( $p[3] ); ?></span>
						</div>
						<h3 class="home-blog-t"><?php echo esc_html( $p[1] ); ?></h3>
						<p class="home-blog-x"><?php echo esc_html( $p[6] ); ?></p>
						<div class="home-blog-author">
							<span class="home-blog-author-name"><?php echo esc_html( $p[4] ); ?></span>
							<span class="home-blog-author-date"><?php echo esc_html( $p[5] ); ?></span>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>

<?php /* ══════════════════ 11. CLOSING CTA (Putt-Moment, gemeinsames Part) ══════════════════ */
$home_cta_links = '<a href="mailto:' . esc_attr( fge_company()['email_events'] ) . '">' . esc_html( fge_company()['email_events'] ) . '</a>';
if ( function_exists( 'fge_get_cities' ) ) {
	foreach ( fge_get_cities() as $cslug => $c ) {
		$home_cta_links .= '<a href="' . esc_url( home_url( '/golf-events/' . $cslug . '/' ) ) . '">' . esc_html( $c['name'] ) . '</a>';
	}
}
get_template_part( 'template-parts/fge-putt-cta', null, [
	'headline_html' => 'Lasst uns euer nächstes Event <em class="mk-italic">zusammen</em> planen.',
	'sub'           => 'Schickt uns eure Eckdaten in 30 Sekunden. Innerhalb eines Werktags habt ihr konkrete Vorschläge, kostenlos und unverbindlich.',
	'anfrage_url'   => add_query_arg( 'anfrage', 'quick', $url_ind ),
	'links_html'    => $home_cta_links,
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
    var textEl = document.getElementById('qs-loc-text');
    var display = document.getElementById('qs-loc-display');
    var form = cell.closest('form');
    var ajax = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    function open() { panel.classList.add('is-open'); cell.setAttribute('aria-expanded', 'true'); setTimeout(function () { input && input.focus(); }, 30); }
    function close() { panel.classList.remove('is-open'); cell.setAttribute('aria-expanded', 'false'); }

    cell.addEventListener('click', function (e) {
      if (panel.contains(e.target)) return;
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
      if (textEl) textEl.textContent = label;
      if (display) display.classList.remove('fg-muted');
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

  /* „Warum der Golfplatz": Antippen (oder Zeigen mit der Maus) wechselt das
     grosse Bild per Crossfade; das aktive Bild zoomt ganz langsam weiter. */
  (function () {
    var items = document.querySelectorAll('.fgw-item');
    var imgs = document.querySelectorAll('[data-fgw-img]');
    if (!items.length || !imgs.length) return;
    var hoverable = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    function activate(i) {
      items.forEach(function (it) {
        var on = it.getAttribute('data-fgw') === String(i);
        it.classList.toggle('is-active', on);
        it.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      imgs.forEach(function (im) {
        im.classList.toggle('is-active', im.getAttribute('data-fgw-img') === String(i));
      });
    }
    items.forEach(function (it) {
      it.addEventListener('click', function () { activate(it.getAttribute('data-fgw')); });
      if (hoverable) { it.addEventListener('pointerenter', function () { activate(it.getAttribute('data-fgw')); }); }
    });
  })();

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
