<?php
/**
 * SEO format landing — rendered for /firmenevent/<format>/ via format-landing.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slug    = (string) get_query_var( 'fge_format' );
$formats = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];
$format  = $formats[ $slug ] ?? null;
if ( ! $format ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

$f_name    = $format['name'];
$canonical = home_url( '/firmenevent/' . $slug . '/' );
$seo_title = $format['h1'] . ' | Firmengolf';
$seo_desc  = $format['lead'];
$events_url = (string) get_post_type_archive_link( 'firmengolf_event' );
$ind_url    = ( $p = get_page_by_path( 'individuelle-events' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/individuelle-events/' );
// CTAs springen direkt in den Anfrage-Wizard (Deep-Link, s. fge-individual.js):
// full = ausführliches Formular, quick = 30-Sekunden-Schnellanfrage.
$anfrage_full  = add_query_arg( 'anfrage', 'full', $ind_url );
$anfrage_quick = add_query_arg( 'anfrage', 'quick', $ind_url );
$faqs       = $format['faqs'] ?? [];

// Passende Events: 2 volle 4er-Reihen (Grid wie auf der Eventliste), Fallback verhindert leere Seite.
$format_events = function_exists( 'fge_format_events' ) ? fge_format_events( $format, 8 ) : [];
if ( empty( $format_events ) && function_exists( 'fge_get_featured_events' ) ) {
	$format_events = fge_get_featured_events( 8 );
}

// Icon-Set für die „Gründe"-Kacheln.
if ( ! function_exists( 'fge_format_ico' ) ) {
	function fge_format_ico( string $name ): string {
		$p = [
			'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
			'mountain'  => '<path d="M3 20l6.5-11 4 6 2-3L21 20z"/>',
			'flag'      => '<path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/>',
			'castle'    => '<path d="M4 21V8l2 1V5l2 1V4l2 1V4l2-1v2l2-1v2l2-1v4l2-1v13z"/><path d="M10 21v-4h4v4"/>',
			'gift'      => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9"/><path d="M12 8S10.5 3 8 4.5 9.5 8 12 8zM12 8s1.5-5 4-3.5S14.5 8 12 8z"/>',
			'leaf'      => '<path d="M11 20A7 7 0 0 1 4 13C4 8 9 4 20 4c0 9-4 16-9 16z"/><path d="M4 20c4-4 7-6 11-7"/>',
			'trophy'    => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3"/>',
			'handshake' => '<path d="m11 17 2 2a1 1 0 0 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 0 0 3-3l-3.9-3.9a2 2 0 0 0-2.8 0l-1.6 1.6a1 1 0 0 1-2.8-2.8l2.9-2.9"/><path d="m21 3-3 3M3 13l4 4"/>',
			'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		];
		$d = $p[ $name ] ?? $p['flag'];
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
	}
}

// ── SEO head injection ────────────────────────────────────────────────────────
add_filter( 'pre_get_document_title', static fn() => $seo_title );
add_action( 'wp_head', static function () use ( $seo_title, $seo_desc, $canonical, $f_name, $faqs ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $seo_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
	$og_img = function_exists( 'fge_default_og_image_url' ) ? fge_default_og_image_url() : '';
	if ( $og_img ) { echo '<meta property="og:image" content="' . esc_url( $og_img ) . '">' . "\n"; }

	$graph = [
		[
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'serviceType' => $f_name,
			'name'        => 'Firmengolf, ' . $f_name,
			'areaServed'  => [ '@type' => 'Country', 'name' => 'Deutschland' ],
			'provider'    => [ '@type' => 'Organization', 'name' => 'Firmengolf', 'url' => home_url( '/' ) ],
			'url'         => $canonical,
		],
		[
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array_map( static function ( $f ) {
				return [
					'@type'          => 'Question',
					'name'           => $f['q'],
					'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $f['a'] ],
				];
			}, $faqs ),
		],
		[
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => [
				[ '@type' => 'ListItem', 'position' => 1, 'name' => 'Firmengolf', 'item' => home_url( '/' ) ],
				[ '@type' => 'ListItem', 'position' => 2, 'name' => 'Firmenevents', 'item' => get_post_type_archive_link( 'firmengolf_event' ) ],
				[ '@type' => 'ListItem', 'position' => 3, 'name' => $f_name ],
			],
		],
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $graph ) . '</script>' . "\n";
} );

get_header();
?>
<div class="fge-page fmt-lp" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

<?php /* Hero mit direkten CTAs: Ads-Traffic soll ohne Umweg zu den Events bzw. zur Anfrage */ ?>
<section class="ev-hero" aria-label="<?php echo esc_attr( $format['h1'] ); ?>">
	<div class="ev-hero-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( $format['hero_img'] ?? 'golfplatz-panorama.jpg' ) ); ?>')">
		<div class="ev-hero-scrim" aria-hidden="true"></div>
		<div class="ev-hero-content">
			<div class="ev-hero-eyebrow"><?php echo esc_html( $format['eyebrow'] ); ?></div>
			<h1 class="ev-hero-title"><?php echo esc_html( $format['h1'] ); ?></h1>
			<p class="ev-hero-sub"><?php echo esc_html( $format['lead'] ); ?></p>
			<div class="ev-hero-ctas">
				<a class="fg-btn-brand" href="#angebote">Passende Events ansehen</a>
				<a class="fg-btn-ghost-light" href="<?php echo esc_url( $anfrage_quick ); ?>">Unverbindlich anfragen</a>
			</div>
		</div>
	</div>
</section>

<?php /* Icon-Facts direkt unter dem Hero: die Antworten, die Ads-Besucher zuerst suchen
	(Kachel-Design, volle Container-Breite, vereint Facts + frühere Gründe-Sektion) */ ?>
<?php if ( ! empty( $format['facts'] ) ) : ?>
<section class="mk-section fmt-keyfacts" aria-label="<?php echo esc_attr( $f_name ); ?> auf einen Blick">
	<div class="city-reasons">
		<?php foreach ( $format['facts'] as $fact ) : ?>
		<div class="city-reason">
			<span class="city-reason-ic" aria-hidden="true"><?php echo fge_format_ico( $fact['ic'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
			<h2 class="city-reason-t"><?php echo esc_html( $fact['t'] ); ?></h2>
			<p class="city-reason-b"><?php echo esc_html( $fact['b'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Passende Events zuerst: das schnellste Ergebnis für die Suchanfrage */ ?>
<?php if ( ! empty( $format_events ) ) : ?>
<section class="fg-grid-section" id="angebote" aria-label="<?php echo esc_attr( $f_name ); ?>-Angebote">
	<div class="fg-grid-head">
		<h2 class="fg-grid-title">Beliebte <?php echo esc_html( $f_name ); ?>-Angebote</h2>
		<div class="fmt-city-chips">
			<?php if ( function_exists( 'fge_citformat_is_valid' ) && function_exists( 'fge_get_cities' ) ) :
				$fmt_cities = function_exists( 'fge_citformat_enabled_cities' ) ? fge_citformat_enabled_cities() : [];
				$all_cities = fge_get_cities();
				foreach ( $fmt_cities as $cslug ) : if ( ! fge_citformat_is_valid( $cslug, $slug ) ) continue; ?>
				<a class="fg-chip" href="<?php echo esc_url( home_url( '/golf-events/' . $cslug . '/' . $slug . '/' ) ); ?>"><?php echo esc_html( $all_cities[ $cslug ]['name'] ?? ucfirst( $cslug ) ); ?></a>
			<?php endforeach; endif; ?>
			<a class="fg-chip" href="<?php echo esc_url( $events_url ); ?>">Alle ansehen</a>
		</div>
	</div>
	<div class="fg-grid ev-grid4">
		<?php foreach ( $format_events as $ev ) : ?>
			<?php get_template_part( 'template-parts/fge-event-card', null, [ 'id' => (int) $ev->ID ] ); ?>
			<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Intro */ ?>
<section class="mk-section" aria-label="Über <?php echo esc_attr( $f_name ); ?>">
	<div class="mk-section-head">
		<div class="mk-eyebrow"><?php echo esc_html( $f_name ); ?> für Unternehmen</div>
		<h2 class="mk-h2"><?php echo esc_html( $f_name ); ?>, gemeinsam erleben.</h2>
		<p class="mk-sub" style="max-width:var(--width-prose);"><?php echo esc_html( $format['intro'] ); ?></p>
	</div>
</section>

<?php /* So könnte dein Tag ablaufen: eine Reihe, Punkte blenden gestaffelt von oben ein */ ?>
<?php if ( ! empty( $format['flow'] ) ) : ?>
<section class="mk-section mk-band fmt-flow5" aria-label="So läuft euer <?php echo esc_attr( $f_name ); ?>">
	<div class="mk-section-head">
		<div class="mk-eyebrow">So könnte dein Tag ablaufen</div>
		<h2 class="mk-h2">Vom Welcome bis zum Ausklang.</h2>
	</div>
	<div class="fmt-flow5-row">
		<?php foreach ( $format['flow'] as $i => $step ) : ?>
		<div class="fmt-fstep" style="--fstep-delay:<?php echo esc_attr( (string) ( $i * 0.18 ) ); ?>s">
			<div class="fmt-fstep-n"><?php echo esc_html( (string) ( $i + 1 ) ); ?></div>
			<h3 class="fmt-fstep-t"><?php echo esc_html( $step['t'] ); ?></h3>
			<p class="fmt-fstep-b"><?php echo esc_html( $step['b'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Golf-Erfahrung: für jedes Level das Passende (identisch zu Individuelle Events) */ ?>
<?php if ( ! empty( $format['levels'] ) ) :
	$fmt_levels = [
		[ 'level' => 1, 'badge' => 'Einsteiger', 't' => 'Erste Erfahrungen', 'img' => 'erfahrung-korb.jpg',
			'b' => 'Noch nie einen Schläger gehalten? Genau richtig. Golflehrer, Leih-Ausrüstung und die ersten Schwünge auf der Range, locker, ohne Druck.',
			'meta' => [ 'Golflehrer', 'Schläger gestellt', 'Range & Putting' ] ],
		[ 'level' => 2, 'badge' => 'Auffrischer', 't' => 'Schon mal gespielt', 'img' => 'erfahrung-sand.jpg',
			'b' => 'Ein paar Runden Erfahrung? Wir frischen den Schwung auf, gehen ins Kurzspiel und spielen danach gemeinsam entspannte 9 Loch.',
			'meta' => [ 'Kurzspiel-Training', '9 Loch', 'Gemischte Flights' ] ],
		[ 'level' => 3, 'badge' => 'Fortgeschritten', 't' => 'Fortgeschrittene Golfer', 'img' => 'erfahrung-inselgruen.jpg',
			'b' => 'Platzreife in der Tasche? Volle 18 Loch im Turnierformat mit Flights, Live-Scoring und Siegerehrung bei Sonnenuntergang.',
			'meta' => [ '18 Loch', 'Live-Scoring', 'Siegerehrung' ] ],
	];
?>
<section class="iv-section">
	<div class="iv-head">
		<div class="mk-eyebrow">Golf-Erfahrung</div>
		<h2 class="mk-h2">Für jedes Level das <span class="mk-italic">Passende</span></h2>
		<p class="mk-sub">In jedem Team spielt jemand zum ersten Mal, und jemand seit Jahren. Wir stellen jedes Event so zusammen, dass alle Spaß haben, egal auf welchem Level.</p>
	</div>
	<div class="iv-exp-grid">
		<?php foreach ( $fmt_levels as $x ) : ?>
			<article class="iv-exp">
				<div class="iv-exp-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( $x['img'] ) ); ?>')">
					<span class="iv-exp-badge"><?php echo esc_html( $x['badge'] ); ?></span>
				</div>
				<div class="iv-exp-body">
					<div class="iv-exp-dots" aria-hidden="true">
						<?php for ( $n = 1; $n <= 3; $n++ ) : ?>
							<span class="iv-exp-dot<?php echo $n <= $x['level'] ? ' on' : ''; ?>"></span>
						<?php endfor; ?>
					</div>
					<h3 class="iv-exp-t"><?php echo esc_html( $x['t'] ); ?></h3>
					<p class="iv-exp-b"><?php echo esc_html( $x['b'] ); ?></p>
					<div class="iv-exp-meta">
						<?php foreach ( $x['meta'] as $m ) : ?>
							<span class="iv-exp-tag"><?php echo esc_html( $m ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Gründe */ ?>
<?php if ( ! empty( $format['reasons'] ) ) : ?>
<section class="mk-section city-reasons-section" aria-label="Gründe für <?php echo esc_attr( $f_name ); ?>">
	<div class="city-reasons">
		<?php foreach ( $format['reasons'] as $r ) : ?>
		<div class="city-reason">
			<span class="city-reason-ic" aria-hidden="true"><?php echo fge_format_ico( $r['ic'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
			<h3 class="city-reason-t"><?php echo esc_html( $r['t'] ); ?></h3>
			<p class="city-reason-b"><?php echo esc_html( $r['b'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Optionale Cross-Promo (z. B. Platzreife → Firmengolf-Benefit) */ ?>
<?php if ( ! empty( $format['promo'] ) ) : $fmt_promo = $format['promo']; ?>
<section class="mk-section" aria-label="<?php echo esc_attr( $fmt_promo['title'] ); ?>">
	<div class="fmt-promo">
		<div>
			<div class="mk-eyebrow"><?php echo esc_html( $fmt_promo['eyebrow'] ); ?></div>
			<h2 class="mk-h2" style="font-size:30px;"><?php echo esc_html( $fmt_promo['title'] ); ?></h2>
			<p class="mk-sub"><?php echo esc_html( $fmt_promo['text'] ); ?></p>
		</div>
		<a class="fg-btn-brand" href="<?php echo esc_url( $fmt_promo['url'] ); ?>"<?php echo ! empty( $fmt_promo['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
			<?php echo esc_html( $fmt_promo['cta'] ); ?>
		</a>
	</div>
</section>
<?php endif; ?>

<?php /* FAQ */ ?>
<?php if ( ! empty( $faqs ) ) : ?>
<section class="mk-section faq-section" aria-label="FAQ">
	<div class="faq-shell">
		<div class="faq-aside">
			<div class="mk-eyebrow">Häufige Fragen</div>
			<h2 class="mk-h2" style="margin-top:8px;"><?php echo esc_html( $f_name ); ?>, kurz erklärt.</h2>
		</div>
		<ul class="faq-list">
			<?php foreach ( $faqs as $faq ) : ?>
				<li class="faq-item">
					<button class="faq-q" type="button" aria-expanded="false">
						<span><?php echo esc_html( $faq['q'] ); ?></span>
						<span class="faq-toggle" aria-hidden="true">+</span>
					</button>
					<div class="faq-a"><?php echo esc_html( $faq['a'] ); ?></div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
<?php endif; ?>

<?php /* Cross-links zu anderen Formaten + CTA */ ?>
<section class="mk-cta" aria-label="Anfrage">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Bereit für euer <?php echo esc_html( $f_name ); ?>?</div>
		<h2 class="mk-cta-h">Lasst uns euer Event <em class="mk-italic">planen</em>.</h2>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $anfrage_full ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">Event anfragen</a>
			<?php foreach ( $formats as $fslug => $f ) : if ( $fslug === $slug ) continue; ?>
				<a class="mk-cta-mail" href="<?php echo esc_url( home_url( '/firmenevent/' . $fslug . '/' ) ); ?>"><?php echo esc_html( $f['name'] ); ?> →</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
// Ablauf-Punkte blenden gestaffelt ein, sobald die Sektion sichtbar wird.
(function () {
	var row = document.querySelector('.fmt-flow5-row');
	if (!row) return;
	if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		row.classList.add('is-in');
		return;
	}
	var io = new IntersectionObserver(function (entries) {
		entries.forEach(function (e) {
			if (e.isIntersecting) { row.classList.add('is-in'); io.disconnect(); }
		});
	}, { threshold: 0.25 });
	io.observe(row);
})();
document.querySelectorAll('.fge-page .faq-q[aria-expanded]').forEach(function (btn) {
	btn.addEventListener('click', function () {
		var item = btn.closest('.faq-item');
		var open = item.classList.toggle('open');
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		var t = btn.querySelector('.faq-toggle'); if (t) t.textContent = open ? '−' : '+';
	});
});
</script>

<?php get_footer();
