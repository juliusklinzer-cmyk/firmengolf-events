<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// ── URL / page setup ────────────────────────────────────────────────────────
$archive_url = (string) get_post_type_archive_link( 'firmengolf_event' );
$ind_page    = get_page_by_path( 'individuelle-events' );
$ind_url     = $ind_page ? (string) get_permalink( $ind_page->ID ) : home_url( '/individuelle-events/' );
$kontakt_url = ( $kp = get_page_by_path( 'kontakt' ) ) ? (string) get_permalink( $kp->ID ) : home_url( '/kontakt/' );

// ── Sanitize GET params ──────────────────────────────────────────────────────
$active_format = sanitize_key( $_GET['format'] ?? 'all' );        // phpcs:ignore WordPress.Security.NonceVerification
$active_region = sanitize_text_field( $_GET['region'] ?? '' );    // phpcs:ignore WordPress.Security.NonceVerification
$active_pax    = max( 0, (int) ( $_GET['pax'] ?? 10 ) );          // phpcs:ignore WordPress.Security.NonceVerification — default 10
$active_sort   = sanitize_key( $_GET['sort'] ?? 'curated' );      // phpcs:ignore WordPress.Security.NonceVerification

// ── Format list ─────────────────────────────────────────────────────────────
$formats = [
	'all'            => 'Alle Formate',
	'schnupperkurs'  => 'Schnupperkurs',
	'firmenturnier'  => 'Firmenturnier',
	'team-building'  => 'Team-Building',
	'networking'     => 'Networking-Runde',
	'incentive'      => 'Incentive',
	'coaching'       => 'Coaching / Trainerstunde',
	'gesundheitstag' => 'Gesundheitstag',
	'offsite'        => 'Offsite',
	'kundenevent'    => 'Kundenevent',
];

// ── Regions: only those with at least one published event ────────────────────
global $wpdb;
$available_regions = $wpdb->get_col( $wpdb->prepare(
	"SELECT DISTINCT pm.meta_value
	 FROM {$wpdb->postmeta} pm
	 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	 WHERE pm.meta_key = %s
	   AND p.post_type = %s
	   AND p.post_status = 'publish'
	   AND pm.meta_value != ''
	 ORDER BY pm.meta_value",
	'_fge_region',
	'firmengolf_event'
) );
if ( empty( $available_regions ) ) {
	$available_regions = [ 'Nord', 'Ost', 'Süd', 'West' ]; // fallback
}

// ── Build chip URL (preserves other active params) ───────────────────────────
$chip_url = static function( array $params ) use ( $archive_url, $active_format, $active_region, $active_pax, $active_sort ): string {
	$base = [
		'format' => $active_format,
		'region' => $active_region,
		'pax'    => $active_pax,
		'sort'   => $active_sort,
	];
	return add_query_arg( array_merge( $base, $params ), $archive_url );
};

// ── Custom WP_Query with filters ─────────────────────────────────────────────
$query_args = [
	'post_type'      => 'firmengolf_event',
	'post_status'    => 'publish',
	'posts_per_page' => 24,
	'paged'          => max( 1, (int) ( $_GET['paged'] ?? get_query_var( 'paged' ) ) ),  // phpcs:ignore WordPress.Security.NonceVerification
];

if ( $active_format !== 'all' ) {
	$query_args['meta_query'][] = [
		'key'     => '_fge_event_type',
		'value'   => $active_format,
		'compare' => '=',
	];
}
if ( $active_region ) {
	$query_args['meta_query'][] = [
		'key'     => '_fge_region',
		'value'   => $active_region,
		'compare' => '=',
	];
}
if ( $active_pax > 0 ) {
	$query_args['meta_query'][] = [
		'key'     => '_fge_participants_max',
		'value'   => $active_pax,
		'compare' => '>=',
		'type'    => 'NUMERIC',
	];
}
switch ( $active_sort ) {
	case 'price-asc':
		$query_args['meta_key'] = '_fge_price_per_person';
		$query_args['orderby']  = 'meta_value_num';
		$query_args['order']    = 'ASC';
		break;
	case 'rating':
		$query_args['meta_key'] = '_fge_rating';
		$query_args['orderby']  = 'meta_value_num';
		$query_args['order']    = 'DESC';
		break;
	case 'group':
		$query_args['meta_key'] = '_fge_participants_max';
		$query_args['orderby']  = 'meta_value_num';
		$query_args['order']    = 'DESC';
		break;
	default:
		$query_args['orderby'] = 'menu_order';
		$query_args['order']   = 'ASC';
}

$events_query = new WP_Query( $query_args );
$total        = (int) $events_query->found_posts;

// ── Heading ──────────────────────────────────────────────────────────────────
$heading_parts = [];
if ( $active_format !== 'all' ) {
	$heading_parts[] = $formats[ $active_format ] ?? 'Events';
}
if ( $active_region ) {
	$heading_parts[] = $active_region;
}
if ( $active_pax > 0 ) {
	$heading_parts[] = $active_pax . '+ Pers.';
}
$heading = $heading_parts ? implode( ' · ', $heading_parts ) : 'Alle Firmenevents';

// ── Arrow SVG ────────────────────────────────────────────────────────────────
$arrow = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>';
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

<?php /* ══════════════ HERO ══════════════ */ ?>
<section class="ev-hero" aria-label="Events">
	<div class="ev-hero-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( 'golfplatz-panorama.jpg' ) ); ?>')">
		<div class="ev-hero-scrim" aria-hidden="true"></div>
		<div class="ev-hero-content">
			<div class="ev-hero-eyebrow">Marketplace · Firmenevents</div>
			<h1 class="ev-hero-title">
				Finde dein nächstes <em class="mk-italic">Firmen-Event</em>.
			</h1>
			<p class="ev-hero-sub">
				Kuratierte Formate auf 180+ Partnerplätzen — Schnupperkurs, Firmenturnier, Offsite, Gesundheitstag und mehr.
			</p>
		</div>
	</div>

	<?php /* ── Search bar ── */ ?>
	<form method="get" action="<?php echo esc_url( $archive_url ); ?>" class="fg-search-bar" role="search">

		<?php /* Cell 1: Format dropdown */ ?>
		<div class="fg-search-cell fg-format-cell" id="fg-format-cell"
		     tabindex="0" role="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Format wählen">
			<div class="fg-cell-label">Format</div>
			<div class="fg-cell-value" id="fg-format-display">
				<span id="fg-format-text"><?php echo esc_html( $formats[ $active_format ] ?? 'Alle Formate' ); ?></span>
			</div>
			<input type="hidden" name="format" id="fg-format-val" value="<?php echo esc_attr( $active_format ); ?>">
			<div class="fg-search-panel" id="fg-format-panel" role="listbox">
				<?php foreach ( $formats as $slug => $label ) : ?>
					<button type="button"
					        class="fg-search-panel-opt<?php echo $active_format === $slug ? ' is-selected' : ''; ?>"
					        data-value="<?php echo esc_attr( $slug ); ?>"
					        data-label="<?php echo esc_attr( $label ); ?>"
					        role="option"
					        aria-selected="<?php echo $active_format === $slug ? 'true' : 'false'; ?>">
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="fg-cell-divider" aria-hidden="true"></div>

		<?php /* Cell 2: Region dropdown (only existing regions) */ ?>
		<div class="fg-search-cell fg-region-cell" id="fg-region-cell"
		     tabindex="0" role="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Region wählen">
			<div class="fg-cell-label">Region</div>
			<div class="fg-cell-value<?php echo ! $active_region ? ' fg-muted' : ''; ?>" id="fg-region-display">
				<span id="fg-region-text"><?php echo $active_region ? esc_html( $active_region ) : 'Ganz Deutschland'; ?></span>
			</div>
			<input type="hidden" name="region" id="fg-region-val" value="<?php echo esc_attr( $active_region ); ?>">
			<div class="fg-search-panel" id="fg-region-panel" role="listbox">
				<button type="button"
				        class="fg-search-panel-opt<?php echo ! $active_region ? ' is-selected' : ''; ?>"
				        data-value="" data-label="Ganz Deutschland" role="option">
					Ganz Deutschland
				</button>
				<?php foreach ( $available_regions as $region ) : ?>
					<button type="button"
					        class="fg-search-panel-opt<?php echo $active_region === $region ? ' is-selected' : ''; ?>"
					        data-value="<?php echo esc_attr( $region ); ?>"
					        data-label="<?php echo esc_attr( $region ); ?>"
					        role="option"
					        aria-selected="<?php echo $active_region === $region ? 'true' : 'false'; ?>">
						<?php echo esc_html( $region ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="fg-cell-divider" aria-hidden="true"></div>

		<?php /* Cell 3: Personenzahl with +/− (default 10) */ ?>
		<div class="fg-search-cell fg-pax-cell">
			<div class="fg-cell-label">Personen</div>
			<div class="fg-pax-ctrl">
				<button type="button" class="fg-pax-btn" data-fn="dec" aria-label="Weniger Personen"
				        <?php echo $active_pax <= 1 ? 'disabled' : ''; ?>>−</button>
				<span class="fg-pax-num" id="fg-pax-display">
					<?php echo esc_html( (string) $active_pax ); ?> Pers.
				</span>
				<button type="button" class="fg-pax-btn" data-fn="inc" aria-label="Mehr Personen">+</button>
			</div>
			<input type="hidden" name="pax" id="fg-pax-val" value="<?php echo esc_attr( (string) $active_pax ); ?>">
		</div>

		<input type="hidden" name="sort" value="<?php echo esc_attr( $active_sort ); ?>">

		<button type="submit" class="fg-search-btn" aria-label="Events suchen">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
		</button>
	</form>
</section>

<?php /* ══════════════ FORMAT CHIPS ══════════════ */ ?>
<div class="fg-filter-rail">
	<div class="fg-chip-row">
		<?php foreach ( $formats as $slug => $label ) : ?>
			<a href="<?php echo esc_url( $chip_url( [ 'format' => $slug ] ) ); ?>"
			   class="fg-chip<?php echo $active_format === $slug ? ' active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</div>
</div>

<?php /* ══════════════ GRID ══════════════ */ ?>
<section class="fg-grid-section" aria-label="Eventangebote">
	<div class="fg-grid-head">
		<h2 class="fg-grid-title"><?php echo esc_html( $heading ); ?></h2>
		<div class="ev-grid-controls">
			<span class="fg-grid-count">
				<?php echo esc_html( $total . ' ' . ( $total === 1 ? 'Format' : 'Formate' ) ); ?>
			</span>
			<form method="get" action="<?php echo esc_url( $archive_url ); ?>" class="ev-sort">
				<span class="fg-cell-label">Sortieren</span>
				<input type="hidden" name="format" value="<?php echo esc_attr( $active_format ); ?>">
				<input type="hidden" name="region" value="<?php echo esc_attr( $active_region ); ?>">
				<input type="hidden" name="pax"    value="<?php echo esc_attr( (string) $active_pax ); ?>">
				<select name="sort" class="ev-select" onchange="this.form.submit()">
					<option value="curated"   <?php selected( $active_sort, 'curated' ); ?>>Empfohlen</option>
					<option value="price-asc" <?php selected( $active_sort, 'price-asc' ); ?>>Preis aufsteigend</option>
					<option value="rating"    <?php selected( $active_sort, 'rating' ); ?>>Bewertung</option>
					<option value="group"     <?php selected( $active_sort, 'group' ); ?>>Gruppengröße</option>
				</select>
			</form>
		</div>
	</div>

	<?php if ( $events_query->have_posts() ) : ?>

		<div class="fg-grid">
			<?php while ( $events_query->have_posts() ) : $events_query->the_post();
				$pid      = get_the_ID();
				$etype    = fge_get_event_meta( $pid, 'event_type' );
				$elabel   = fge_format_event_type( $etype ) ?: ucfirst( $etype );
				$venue    = fge_get_event_meta( $pid, 'event_location' );
				$region_m = fge_get_event_meta( $pid, 'region' );
				$eyebrow  = trim( $elabel . ' · ' . fge_get_event_meta( $pid, 'duration' ), ' ·' );
				$p_max    = fge_get_event_meta( $pid, 'participants_max' );
				$duration = fge_get_event_meta( $pid, 'duration' );
				$price    = fge_get_event_price_display( $pid );
				$rating   = fge_get_event_meta( $pid, 'rating' );
				$reviews  = fge_get_event_meta( $pid, 'reviews_count' );
				$featured = fge_get_event_meta( $pid, 'featured' );
				$thumb    = has_post_thumbnail() ? get_the_post_thumbnail_url( $pid, 'large' ) : fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' );
				$indoor   = in_array( 'Indoor-Backup', fge_get_active_leistungen( $pid ), true );
			?>
			<article class="fg-event">
				<a href="<?php the_permalink(); ?>" style="display:contents">
					<div class="fg-event-photo" style="background-image:url('<?php echo esc_url( $thumb ); ?>')">
						<div class="fg-event-chips">
							<?php if ( $elabel ) : ?>
								<span class="fg-photo-chip"><?php echo esc_html( $elabel ); ?></span>
							<?php endif; ?>
							<?php if ( $indoor ) : ?>
								<span class="fg-photo-chip">Indoor-Backup</span>
							<?php endif; ?>
						</div>
						<?php if ( $featured ) : ?>
							<span class="fg-event-featured">Featured</span>
						<?php endif; ?>
					</div>
					<div class="fg-event-body">
						<?php if ( $eyebrow ) : ?>
							<div class="fg-event-eyebrow"><?php echo esc_html( $eyebrow ); ?></div>
						<?php endif; ?>
						<h3 class="fg-event-title"><?php the_title(); ?></h3>
						<div class="fg-event-meta">
							<?php if ( $venue || $region_m ) : ?>
								<span><?php echo esc_html( $venue ?: $region_m ); ?></span>
							<?php endif; ?>
							<?php if ( $p_max ) : ?>
								<span class="dot">·</span>
								<span>bis <?php echo esc_html( (string) $p_max ); ?> Gäste</span>
							<?php endif; ?>
							<?php if ( $duration ) : ?>
								<span class="dot">·</span>
								<span><?php echo esc_html( $duration ); ?></span>
							<?php endif; ?>
						</div>
						<div class="fg-event-foot">
							<div class="fg-event-price">
								<?php if ( $price ) : ?>
									<?php echo esc_html( $price ); ?>
								<?php else : ?>
									<span style="font-size:13px;color:var(--ink-500)">Auf Anfrage</span>
								<?php endif; ?>
							</div>
							<?php if ( $rating ) : ?>
								<div class="fg-event-rating">
									<svg viewBox="0 0 24 24" width="13" height="13" fill="#C9B488" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
									<span><?php echo esc_html( (string) $rating ); ?></span>
									<?php if ( $reviews ) : ?>
										<span class="fg-event-reviews">(<?php echo esc_html( (string) $reviews ); ?>)</span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</a>
			</article>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>

	<?php else : ?>

		<div class="fg-empty">
			Noch nichts Passendes dabei. Plane dein Format individuell — wir kümmern uns drum.
			<div style="margin-top:16px">
				<a class="fg-btn-cta" href="<?php echo esc_url( $ind_url ); ?>">
					Individuelles Event anfragen
					<span class="fg-arrow"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M9 7h8v8"/></svg></span>
				</a>
			</div>
		</div>

	<?php endif; ?>

	<?php /* Inline CTA */ ?>
	<div class="ev-inline-cta">
		<div>
			<div class="mk-eyebrow">Kein passendes Format dabei?</div>
			<h3 class="ev-inline-h">Wir planen dein Event nach deinen Ansprüchen.</h3>
		</div>
		<a class="fg-btn-cta" href="<?php echo esc_url( $ind_url ); ?>">
			Individuelles Event anfragen
			<span class="fg-arrow"><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		</a>
	</div>
</section>

<?php /* ══════════════ TRUST STRIP ══════════════ */ ?>
<div class="trust-strip" aria-label="Vertrauen">
	<div class="trust-inner">
		<?php
		$trust = [
			[ '180+ Partnerplätze',  'Vom Stadtkurs bis zur Berg-Anlage.' ],
			[ 'Ein Ansprechpartner', 'Vom Erstkontakt bis nach dem Event.' ],
			[ 'Eine Rechnung',       'Sauber abgerechnet, BGM-konform wenn nötig.' ],
			[ 'Antwort < 24 h',      'Werktags innerhalb eines Arbeitstags.' ],
		];
		foreach ( $trust as $t ) : ?>
			<div class="trust-cell">
				<div class="trust-t"><?php echo esc_html( $t[0] ); ?></div>
				<div class="trust-b"><?php echo esc_html( $t[1] ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<?php /* ══════════════ FAQ ══════════════ */ ?>
<section class="mk-section faq-section" aria-label="FAQ">
	<div class="faq-shell">
		<div class="faq-aside">
			<div class="mk-eyebrow">Häufige Fragen</div>
			<h2 class="mk-h2" style="margin-top:8px">Was Firmen vor der Buchung wissen wollen.</h2>
			<p class="mk-sub" style="margin-top:16px">Die häufigsten Fragen unserer Kunden — vor allem von HR und Office Management.</p>
			<div class="faq-cta">
				<a class="fg-btn-ghost" href="<?php echo esc_url( $kontakt_url ); ?>">
					Etwas anderes fragen
					<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
				</a>
			</div>
		</div>
		<ul class="faq-list">
			<?php
			$faqs = [
				[
					'q' => 'Müssen meine Mitarbeitenden Golf spielen können?',
					'a' => 'Nein. Unsere Schnupperkurse und Team-Building-Formate sind komplett für Einsteigende konzipiert. Es gibt einen PGA-Coach vor Ort, alle Schläger werden gestellt und niemand wird mit einem 18-Loch-Turnier konfrontiert, wenn er noch nie einen Schläger gehalten hat.',
				],
				[
					'q' => 'Wie kurzfristig können wir buchen?',
					'a' => 'Beliebte Termine im Mai–September gehen meist 4–6 Wochen im Voraus weg. Im Frühjahr und Herbst gibt es oft noch Slots innerhalb von 1–2 Wochen. Trag uns gern unverbindlich ein — wir prüfen, was kurzfristig möglich ist.',
				],
				[
					'q' => 'Was passiert bei schlechtem Wetter?',
					'a' => 'Wir buchen immer mit Indoor-Backup an Plätzen, die das anbieten — z. B. überdachte Range oder Indoor-Simulatoren. Für reine Outdoor-Formate gibt es großzügige Stornoregeln 24 Stunden vor Termin.',
				],
				[
					'q' => 'Gibt es eine Mindestgruppengröße?',
					'a' => 'Je nach Format ab 4 Personen (Coaching, Schnupperkurs) bis 24 Personen (Firmenturnier mit Shotgun-Start). Für kleinere oder größere Gruppen planen wir individuell.',
				],
				[
					'q' => 'Wie wird abgerechnet?',
					'a' => 'Eine Sammelrechnung von Firmengolf, mit allen Posten ausgewiesen — Greenfees, Coaching, Catering, Trophäen. Das macht es für HR und Buchhaltung einfach.',
				],
				[
					'q' => 'Können wir das Event als Gesundheitsmaßnahme abrechnen?',
					'a' => 'Ja — unsere Gesundheitstage und Coaching-Formate sind BGM-konform (§ 3 Nr. 34 EStG) abrechenbar. Wir stellen die nötigen Belege aus.',
				],
			];
			foreach ( $faqs as $i => $faq ) : ?>
				<li class="faq-item" id="faq-<?php echo (int) $i; ?>">
					<button class="faq-q" onclick="(function(el){el.closest('.faq-item').classList.toggle('open')})(this)" aria-expanded="false">
						<span><?php echo esc_html( $faq['q'] ); ?></span>
						<span class="faq-toggle" aria-hidden="true">+</span>
					</button>
					<div class="faq-a"><?php echo esc_html( $faq['a'] ); ?></div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
(function () {
  'use strict';

  /* ── Generic dropdown factory ── */
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
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); panel.hidden ? open() : close(); }
      if (e.key === 'Escape') close();
    });

    panel.querySelectorAll('.fg-search-panel-opt').forEach(function (opt) {
      opt.addEventListener('click', function (e) {
        e.stopPropagation();
        var val   = opt.dataset.value;
        var label = opt.dataset.label;
        if (input)   input.value = val;
        if (display) display.textContent = label;
        panel.querySelectorAll('.fg-search-panel-opt').forEach(function (o) {
          o.classList.toggle('is-selected', o.dataset.value === val);
          o.setAttribute('aria-selected', String(o.dataset.value === val));
        });
        close();
      });
    });

    document.addEventListener('click', function (e) {
      if (!cell.contains(e.target)) close();
    });
  }

  initDropdown('fg-format-cell', 'fg-format-panel', 'fg-format-val', 'fg-format-text');
  initDropdown('fg-region-cell', 'fg-region-panel', 'fg-region-val', 'fg-region-text');

  /* ── Pax +/− controls ── */
  var paxDisplay = document.getElementById('fg-pax-display');
  var paxVal     = document.getElementById('fg-pax-val');
  var decBtn     = document.querySelector('.fg-pax-btn[data-fn="dec"]');
  var incBtn     = document.querySelector('.fg-pax-btn[data-fn="inc"]');

  function updatePax(next) {
    next = Math.max(1, next);
    paxVal.value           = next;
    paxDisplay.textContent = next + ' Pers.';
    if (decBtn) decBtn.disabled = next <= 1;
  }

  if (incBtn) incBtn.addEventListener('click', function () { updatePax((parseInt(paxVal.value) || 10) + 1); });
  if (decBtn) decBtn.addEventListener('click', function () { updatePax((parseInt(paxVal.value) || 10) - 1); });
}());
</script>

<?php get_footer(); ?>
