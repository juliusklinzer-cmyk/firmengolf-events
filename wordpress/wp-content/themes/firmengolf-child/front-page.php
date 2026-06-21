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

// ── Format list (canonical — single source: event-formats.php) ───────────────
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
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

<?php
$mbar_action = '<a class="ev-msearch" href="' . esc_url( get_post_type_archive_link( 'firmengolf_event' ) ) . '"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg><span class="ev-msearch-t muted">Events suchen</span></a>';
get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '', 'mbar_action' => $mbar_action ] );
?>

<?php /* ══════════════════ 1. HERO ══════════════════ */ ?>
<section class="mk-hero" aria-label="Hero">
	<div class="mk-hero-photo" style="background-image:url('<?php echo esc_url( $img( 'hero-gruen-3.jpg' ) ); ?>')">
		<div class="mk-hero-scrim" aria-hidden="true"></div>
		<div class="mk-hero-content">
			<div class="mk-hero-eyebrow">Firmenevents · Golf für Unternehmen</div>
			<h1 class="mk-hero-title">
				<span class="mk-hero-lead">Bringt euer Team raus aus dem Büro und rein in</span>
				<span class="rot-wrap"><span class="rot-word mk-italic in" id="fg-rot-word">Bewegung</span><span class="rot-dot">.</span></span>
			</h1>
			<p class="mk-hero-sub">
				Vom Schnupperkurs bis zum Firmenturnier — Golf-Formate auf Partnerplätzen
				in ganz Deutschland. Eine Anfrage, eine Rechnung, ein Ansprechpartner.
			</p>
			<div class="mk-hero-ctas">
				<a class="fg-btn-cta fg-btn-lg" href="<?php echo esc_url( $url_events ); ?>">
					Events entdecken
					<span class="fg-arrow"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
				<a class="fg-btn-ghost-light" href="<?php echo esc_url( $url_ind ); ?>">
					Individuelles Event planen →
				</a>
			</div>
			<div class="mk-hero-trust">
				<span>Keine Mitgliedschaft nötig</span>
				<span class="mk-hero-trust-dot" aria-hidden="true"></span>
				<span>Schläger werden gestellt</span>
				<span class="mk-hero-trust-dot" aria-hidden="true"></span>
				<span>Antwort in einem Werktag</span>
			</div>
		</div>
	</div>

	<div class="mk-hero-floating" aria-hidden="true">
		<div class="mk-floating-thumb" style="background-image:url('<?php echo esc_url( $img( 'golf-coaching-einzel.jpg' ) ); ?>')"></div>
		<div>
			<div class="mk-floating-chip">12 Sommer-Slots im Juni</div>
			<div class="mk-floating-meta">Hamburg · München · Berlin · Köln</div>
		</div>
	</div>

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

		<?php /* Wo? — Ort/PLZ-Autocomplete + Standort + Umkreis (identisch zur Events-Seite) */ ?>
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

<?php /* ══════════════════ 2. FAKTEN-BOXEN ══════════════════ */ ?>
<div class="home-facts" aria-label="Firmengolf in Zahlen">
	<?php
	$facts = [
		[ 'ic' => '<path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/>',
		  't' => '721', 'b' => 'Golfplätze in Deutschland' ],
		[ 'ic' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
		  't' => '1.500', 'b' => 'Golflehrer deutschlandweit' ],
		[ 'ic' => '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
		  't' => 'Aus einer Hand', 'b' => 'Platz, Pro, Catering, Rechnung' ],
		[ 'ic' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		  't' => '1 Werktag', 'b' => 'Antwort auf jede Anfrage' ],
	];
	foreach ( $facts as $f ) : ?>
		<div class="home-fact">
			<span class="home-fact-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?php echo $f['ic']; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></svg></span>
			<div class="home-fact-txt">
				<div class="home-fact-t"><?php echo $f['t']; // phpcs:ignore WordPress.Security.EscapeOutput -- nur statische, kontrollierte Strings (inkl. &lt;) ?></div>
				<div class="home-fact-b"><?php echo esc_html( $f['b'] ); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<?php /* ══════════════════ 2b. EXPERIENCE — Warum Golf ══════════════════ */ ?>
<section class="home-exp" aria-label="Warum Golf">
	<div class="home-exp-inner">
		<div class="home-exp-head">
			<div class="mk-eyebrow">Warum Golf</div>
			<h2 class="home-exp-h">Es geht nicht ums Golf. Es geht um das, was <span class="mk-italic">dabei</span> passiert.</h2>
			<p class="home-exp-lead">
				Niemand muss spielen können. Verbindet euer Meeting mit ein paar Stunden draußen —
				und merkt, wie viel leichter Gespräche laufen, wenn zwischendurch Bewegung dazukommt.
			</p>
		</div>
		<div class="home-exp-cards">
			<?php
			$exp_points = [
				[ 'k' => 'Bewegung',      't' => 'Vier, fünf Kilometer an der frischen Luft — ohne dass es sich nach Sport anfühlt.',       'img' => 'driving-range-uebung.jpg' ],
				[ 'k' => 'Natur',         't' => 'Grün, Weite, Himmel — die perfekte Ergänzung zu einem Tag voller Gespräche.',             'img' => 'golf-gruen-fahne.jpg' ],
				[ 'k' => 'Konzentration', 't' => 'Ein Spiel, das ganz im Moment verlangt — und genau dadurch den Kopf frei macht.',         'img' => 'golf-sandbunker.jpg' ],
				[ 'k' => 'Zusammenhalt',  't' => 'Vier Stunden Seite an Seite, ohne Bildschirm. Teams wachsen hier unangestrengt zusammen.', 'img' => 'golfer-gruppe-fairway.png' ],
			];
			foreach ( $exp_points as $p ) : ?>
				<article class="home-exp-card">
					<div class="home-exp-card-photo" style="background-image:url('<?php echo esc_url( $img( $p['img'] ) ); ?>')"></div>
					<div class="home-exp-card-body">
						<div class="home-exp-k"><?php echo esc_html( $p['k'] ); ?></div>
						<p><?php echo esc_html( $p['t'] ); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php /* ══════════════════ 3. HOW IT WORKS ══════════════════ */ ?>
<section class="mk-section mk-steps mk-band" aria-label="So funktioniert es">
	<div class="mk-section-head">
		<div class="mk-eyebrow">So funktioniert's</div>
		<h2 class="mk-h2">Drei Schritte. Ein Ansprechpartner.</h2>
		<p class="mk-sub">Wir kümmern uns um Platzwahl, Koordination und Abrechnung. Du kümmerst dich ums Team.</p>
	</div>
	<div class="mk-steps-grid">
		<?php
		$steps = [
			[ '01', 'Du sagst uns, was du planst.',     'Anlass, Gruppe, Zeitraum. Eine Anfrage — mehr brauchen wir nicht.' ],
			[ '02', 'Wir kuratieren passende Plätze.',  'Innerhalb eines Werktags bekommst du zwei bis drei Optionen mit Format, Preis und Verfügbarkeit.' ],
			[ '03', 'Du wählst, wir koordinieren.',     'Ein Ansprechpartner, eine Rechnung. Der Platz organisiert vor Ort — du bist nur Gastgeberin.' ],
		];
		foreach ( $steps as $step ) : ?>
			<div class="mk-step">
				<div class="mk-step-n"><?php echo esc_html( $step[0] ); ?></div>
				<h3 class="mk-step-t"><?php echo esc_html( $step[1] ); ?></h3>
				<p class="mk-step-b"><?php echo esc_html( $step[2] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 4. FEATURED FORMATS ══════════════════ */ ?>
<section class="mk-section" aria-label="Beliebte Formate">
	<div class="mk-section-head between">
		<div>
			<div class="mk-eyebrow">Beliebte Formate</div>
			<h2 class="mk-h2">Vom Schnupperkurs bis zum Firmenturnier.</h2>
		</div>
		<a class="fg-btn-ghost" href="<?php echo esc_url( $url_events ); ?>">
			Alle Events ansehen <?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>
	</div>
	<div class="home-formats-grid<?php echo ! empty( $featured_events ) ? ' home-formats-grid--desk' : ''; ?>">
		<?php if ( ! empty( $featured_events ) ) : ?>
			<?php foreach ( array_slice( $featured_events, 0, 4 ) as $i => $event ) :
				$eid         = $event->ID;
				$event_type  = fge_get_event_meta( $eid, 'event_type' );
				$format_label = fge_format_event_type( $event_type );
				$duration    = fge_get_event_meta( $eid, 'duration' );
				$p_max       = (int) fge_get_event_meta( $eid, 'participants_max' );
				$price       = fge_get_event_price_display( $eid );
				$excerpt     = fge_get_event_meta( $eid, 'card_description', $event->post_excerpt );
				$thumb       = function_exists( 'fge_event_cover_url' ) ? fge_event_cover_url( $eid, 'large' ) : ( has_post_thumbnail( $eid ) ? get_the_post_thumbnail_url( $eid, 'large' ) : $img( 'golf-coaching-gruppe.jpg' ) );
				$is_dark     = ( $i % 2 === 1 );
			?>
			<article class="mk-format<?php echo $is_dark ? ' is-dark' : ''; ?>">
				<a href="<?php echo esc_url( get_permalink( $eid ) ); ?>" style="display:contents">
					<div class="mk-format-photo" style="background-image:url('<?php echo esc_url( $thumb ); ?>')">
						<?php if ( $format_label ) : ?>
							<span class="mk-format-tag"><?php echo esc_html( $format_label ); ?></span>
						<?php endif; ?>
						<span class="mk-format-eyebrow">
							<?php echo esc_html( $duration ); ?><?php if ( $p_max ) { echo ' · bis ' . esc_html( (string) $p_max ) . ' Gäste'; } ?>
						</span>
					</div>
					<div class="mk-format-body">
						<h3 class="mk-format-t"><?php echo esc_html( $event->post_title ); ?></h3>
						<?php if ( $excerpt ) : ?>
							<p class="mk-format-desc"><?php echo esc_html( $excerpt ); ?></p>
						<?php endif; ?>
						<div class="mk-format-foot">
							<span class="mk-format-price"><?php echo $price ? esc_html( $price ) : 'Auf Anfrage'; ?></span>
							<span class="mk-format-arrow" aria-hidden="true"><?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
					</div>
				</a>
			</article>
			<?php endforeach; ?>
		<?php else : ?>
			<?php
			// Fallback: static cards when no published events yet
			$static_formats = [
				[ 'label' => 'Schnupperkurs',  'title' => 'Schnupperkurs an einem Nachmittag',    'desc' => 'Einsteigerfreundlich. Golflehrer, Schläger gestellt, Range-Bälle inklusive.',           'price' => 'ab €89 p.P.',       'img' => 'golf-coaching-einzel.jpg',        'dark' => false ],
				[ 'label' => 'Firmenturnier',  'title' => 'Das große Firmenturnier',               'desc' => 'Shotgun-Start, Fotograf, Siegerehrung — wir kümmern uns um alles.',                   'price' => 'ab €320 p.P.',      'img' => 'firmenevent-afterwork-golf.jpg',  'dark' => true  ],
				[ 'label' => 'Offsite',        'title' => 'Strategie-Offsite Schloss Lüdersburg',  'desc' => 'Workshops im Schloss, nachmittags 9 Loch. Übernachtung inklusive.',                   'price' => 'ab €540 p.P.',      'img' => 'clubhaus-aussenansicht.jpg',      'dark' => false ],
				[ 'label' => 'Incentive',      'title' => 'Incentive-Reise Südtirol',              'desc' => 'Drei Tage Dolomiten — Bergblick, private Dinings, Wellness.',                         'price' => 'ab €1.480 p.P.',    'img' => 'golfplatz-meerblick.jpg',         'dark' => true  ],
			];
			foreach ( $static_formats as $f ) : ?>
				<article class="mk-format<?php echo $f['dark'] ? ' is-dark' : ''; ?>">
					<div class="mk-format-photo" style="background-image:url('<?php echo esc_url( $img( $f['img'] ) ); ?>')">
						<span class="mk-format-tag"><?php echo esc_html( $f['label'] ); ?></span>
					</div>
					<div class="mk-format-body">
						<h3 class="mk-format-t"><?php echo esc_html( $f['title'] ); ?></h3>
						<p class="mk-format-desc"><?php echo esc_html( $f['desc'] ); ?></p>
						<div class="mk-format-foot">
							<span class="mk-format-price"><?php echo esc_html( $f['price'] ); ?></span>
							<span class="mk-format-arrow" aria-hidden="true"><?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php if ( ! empty( $featured_events ) ) : ?>
		<div class="home-formats-rail">
			<?php foreach ( $featured_events as $event ) {
				get_template_part( 'template-parts/fge-event-card', null, [ 'id' => (int) $event->ID, 'dist' => null ] );
			} ?>
		</div>
	<?php endif; ?>
</section>

<?php /* ══════════════════ 5. OCCASIONS GRID ══════════════════ */ ?>
<section class="mk-section home-occasions-section" aria-label="Für welchen Anlass?">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Für welchen Anlass?</div>
		<h2 class="mk-h2">Sag uns, was ihr vorhabt — wir kennen das passende Format.</h2>
		<p class="mk-sub">Suche nach dem, was ihr erreichen wollt, nicht nach dem Format-Namen.</p>
	</div>
	<div class="home-occasions">
		<?php
		$occasions = [
			[
				'eyebrow' => 'Onboarding',
				'title'   => 'Neue Mitarbeitende willkommen heißen.',
				'body'    => 'Ein entspannter Halbtag auf dem Platz, der das Eis bricht.',
				'url'     => add_query_arg( 'format', 'after_work_golf', $url_events ),
				'img'     => 'golfer-gruppe-fairway.png',
			],
			[
				'eyebrow' => 'Vertrieb',
				'title'   => 'Kunden und Partner zusammenbringen.',
				'body'    => 'Ganztägiges Firmenturnier oder Networking-Runde.',
				'url'     => add_query_arg( 'format', 'firmen_golfturnier', $url_events ),
				'img'     => 'firmenevent-afterwork-golf.jpg',
			],
			[
				'eyebrow' => 'Kundenbindung',
				'title'   => 'Kunden aufs Grün einladen.',
				'body'    => 'Entspannter Tag, der Geschäftsbeziehungen pflegt.',
				'url'     => add_query_arg( 'format', 'kundenevent', $url_events ),
				'img'     => 'clubhaus-aussenansicht.jpg',
			],
			[
				'eyebrow' => 'Einsteiger',
				'title'   => 'Auch ganz ohne Golf-Erfahrung.',
				'body'    => 'Halbtags-Schnupperkurs mit Trainer — jeder kann mit.',
				'url'     => add_query_arg( 'format', 'schnupperkurs', $url_events ),
				'img'     => 'work-life-balance-golf.jpg',
			],
			[
				'eyebrow' => 'Top-Performer',
				'title'   => 'Eure besten Leute besonders behandeln.',
				'body'    => 'Incentive-Reise mit Übernachtung und privatem Dinner.',
				'url'     => add_query_arg( 'format', 'incentive', $url_events ),
				'img'     => 'golfplatz-meerblick.jpg',
			],
			[
				'eyebrow' => 'Team-Tag',
				'title'   => 'Ein gemeinsamer Nachmittag draußen.',
				'body'    => 'Team-Building mit gemischten Zweier-Teams.',
				'url'     => add_query_arg( 'format', 'teamevent', $url_events ),
				'img'     => 'golfer-trio-spaziergang.png',
			],
		];
		foreach ( $occasions as $occ ) : ?>
			<a class="home-occ" href="<?php echo esc_url( $occ['url'] ); ?>">
				<div class="home-occ-photo" style="background-image:url('<?php echo esc_url( $img( $occ['img'] ) ); ?>')"></div>
				<div class="home-occ-body">
					<div class="mk-eyebrow" style="color:var(--fairway-700)"><?php echo esc_html( $occ['eyebrow'] ); ?></div>
					<h3 class="home-occ-t"><?php echo esc_html( $occ['title'] ); ?></h3>
					<p class="home-occ-b"><?php echo esc_html( $occ['body'] ); ?></p>
					<div class="home-occ-foot">
						Passende Events ansehen <?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 6. INDIVIDUAL TEASER ══════════════════ */ ?>
<section class="mk-section home-individual" aria-label="Individuelle Events">
	<div class="home-individual-grid">
		<div class="home-ind-photo" style="background-image:url('<?php echo esc_url( $img( 'buero-dachterrasse-panorama.jpg' ) ); ?>')"></div>
		<div class="home-ind-text">
			<div class="mk-eyebrow">Individuelle Events</div>
			<h2 class="mk-h2">
				Nichts dabei? <em class="mk-italic">Wir planen</em> dein Event nach deinen Ansprüchen.
			</h2>
			<p class="mk-sub">
				Sonderwünsche, eigene Location, mehrtägiges Programm, internationale Gruppe — beschreib uns kurz,
				was du vorhast. Wir bauen das Format für euch und schlagen die passenden Plätze vor.
			</p>
			<div class="home-ind-points">
				<div><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Persönliche Beratung in einem Werktag</span></div>
				<div><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Maßgeschneidertes Programm</span></div>
				<div><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Ein Ansprechpartner, eine Rechnung</span></div>
			</div>
			<div class="home-ind-ctas">
				<a class="fg-btn-cta" href="<?php echo esc_url( $url_ind ); ?>">
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
<section class="home-benefit" aria-label="Corporate Benefit">
	<div class="home-benefit-inner">
		<div class="home-benefit-eyebrow">Corporate Benefit · firmen.golf</div>
		<h2 class="home-benefit-h">
			Golf als Benefit, den deine Mitarbeitenden <em class="mk-italic">spüren</em>.
		</h2>
		<p class="home-benefit-sub">
			50 € steuerfreier Sachbezug pro Monat, Zugang zu Partnerplätzen, Coaching-Stunden zum Mitarbeiterpreis.
			Bewegung statt Obstkorb — und die HR-Abrechnung läuft sauber.
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

<?php /* ══════════════════ 8. WAS EUCH ERWARTET ══════════════════ */ ?>
<section class="mk-section" aria-label="Was euch erwartet">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Was euch erwartet</div>
		<h2 class="mk-h2">Worauf ihr euch <span class="mk-italic">verlassen</span> könnt.</h2>
	</div>
	<div class="city-reasons">
		<?php
		$promises = [
			[ 'ic' => '<path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
			  't' => 'Ein echter Mensch am Telefon', 'b' => 'Kein Ticketsystem. Du sprichst direkt mit dem, der dein Event plant.' ],
			[ 'ic' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
			  't' => 'Eine Anfrage, eine Rechnung', 'b' => 'Platz, Pro, Catering, Shuttle – alles über einen Ansprechpartner, sauber für HR und Buchhaltung.' ],
			[ 'ic' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
			  't' => 'Auch ohne Golferfahrung', 'b' => 'Golflehrer führen Einsteiger an, Schläger werden gestellt. Niemand muss spielen können.' ],
			[ 'ic' => '<path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/>',
			  't' => 'Deutschlandweit organisierbar', 'b' => 'Rund 721 Golfplätze in Deutschland kommen als Eventlocation in Frage – passt einer nicht, nehmen wir den nächsten.' ],
		];
		foreach ( $promises as $p ) : ?>
			<div class="city-reason">
				<span class="city-reason-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?php echo $p['ic']; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></svg></span>
				<h3 class="city-reason-t"><?php echo esc_html( $p['t'] ); ?></h3>
				<p class="city-reason-b"><?php echo esc_html( $p['b'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 10. BLOG TEASER ══════════════════ */ ?>
<section class="mk-section" aria-label="Aus dem Magazin">
	<div class="mk-section-head between">
		<div>
			<div class="mk-eyebrow">Aus dem Magazin</div>
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
								<span class="home-blog-author-name"><?php echo esc_html( get_the_author_meta( 'display_name', (int) $post->post_author ) ); ?></span>
								<span class="home-blog-author-date"><?php echo esc_html( get_the_date( 'j. F Y', $post ) ); ?></span>
							</div>
						</div>
					</a>
				</article>
			<?php endforeach; ?>
		<?php else : ?>
			<?php
			$static_posts = [
				[ 'Benefits', 'Warum Golf zum Corporate Benefit passt', 'golf-coaching-gruppe.jpg', '6 Min.', 'Julius Klinzer', '14. Mai 2026', '50 € steuerfreier Sachbezug, fittere Mitarbeitende — wir erklären die wichtigsten Punkte.' ],
				[ 'Praxis', 'Die 12-Punkte-Checkliste für dein erstes Firmen-Golfevent', 'firmenevent-afterwork-golf.jpg', '4 Min.', 'Julius Klinzer', '2. Mai 2026', 'Vom richtigen Zeitfenster bis zum Wetter-Backup — was du im Blick haben solltest.' ],
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

<?php /* ══════════════════ 11. CLOSING CTA ══════════════════ */ ?>
<section class="mk-cta" aria-label="Event anfragen">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Bereit?</div>
		<h2 class="mk-cta-h">
			Lasst uns euer nächstes Event <em class="mk-italic">zusammen</em> planen.
		</h2>
		<p class="mk-cta-sub">
			Antwort innerhalb eines Werktags. Kein Vertriebs-Druck, kein Telefon-Marathon.
			Du beschreibst kurz, was du vorhast — wir kümmern uns um den Rest.
		</p>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $url_ind ); ?>"
			   style="background:var(--paper-100);color:var(--fairway-900)">
				Event anfragen
				<span class="fg-arrow" style="background:var(--fairway-200)"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</a>
			<a class="mk-cta-mail" href="mailto:events@visionpunch.de">events@visionpunch.de</a>
		</div>
	</div>
</section>

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
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); panel.classList.contains('is-open') ? close() : open(); }
      if (e.key === 'Escape') close();
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
    cell.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    function setLocation(lat, lng, label) {
      latEl.value = lat; lngEl.value = lng; locEl.value = label;
      if (textEl) textEl.textContent = label;
      if (display) display.classList.remove('fg-muted');
      close(); // Startseite: KEIN Auto-Submit — Weiterleitung erst bei Klick auf "Suchen".
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

  /* ── Hero rotating word ── */
  var rotEl = document.getElementById('fg-rot-word');
  if (rotEl) {
    var rotWords = ['Bewegung', 'neue Energie', 'den Austausch', 'frische Luft'];
    var rotI = 0;
    setInterval(function () {
      rotEl.classList.remove('in');
      rotEl.classList.add('out');
      setTimeout(function () {
        rotI = (rotI + 1) % rotWords.length;
        rotEl.textContent = rotWords[rotI];
        rotEl.classList.remove('out');
        rotEl.classList.add('in');
      }, 380);
    }, 3000);
  }
}());
</script>

<?php get_footer(); ?>
