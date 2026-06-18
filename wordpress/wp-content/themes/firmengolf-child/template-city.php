<?php
/**
 * SEO city landing — rendered for /golf-events/<stadt>/ via city-landing.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slug   = (string) get_query_var( 'fge_city' );
$cities = function_exists( 'fge_get_cities' ) ? fge_get_cities() : [];
$city   = $cities[ $slug ] ?? null;
if ( ! $city ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

$city_name   = $city['name'];
$city_region = $city['region'];
$canonical   = home_url( '/golf-events/' . $slug . '/' );
$seo_title   = 'Firmen-Golfevents in ' . $city_name . ' — Teamevents & Turniere | Firmengolf';
$seo_desc    = 'Firmenevents auf Golfplätzen in ' . $city_name . ': Teamevents, Firmenturniere, Schnupperkurse und individuelle Events. Eine Anfrage, ein Ansprechpartner, eine Rechnung.';
$events_url  = (string) get_post_type_archive_link( 'firmengolf_event' );
$ind_url     = ( $p = get_page_by_path( 'individuelle-events' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/individuelle-events/' );

// City FAQ: aus der Stadt-Config (eigener Inhalt), sonst generischer Fallback.
$faqs = ! empty( $city['faqs'] ) ? $city['faqs'] : [
	[ 'q' => 'Welche Golfplätze gibt es für Firmenevents in ' . $city_name . '?', 'a' => 'Wir arbeiten mit ausgewählten Partnerplätzen in der Region ' . $city_region . ' zusammen, von der Übungsanlage für Einsteigende bis zur 18-Loch-Anlage für Firmenturniere.' ],
	[ 'q' => 'Müssen unsere Mitarbeitenden Golf spielen können?', 'a' => 'Nein. Unsere Schnupperkurse und Teamevents sind für Einsteigende konzipiert, PGA-Coach vor Ort, Schläger werden gestellt.' ],
	[ 'q' => 'Wie schnell bekommen wir eine Antwort?', 'a' => 'Innerhalb eines Werktags meldet sich ein persönlicher Ansprechpartner mit passenden Optionen für ' . $city_name . '.' ],
	[ 'q' => 'Wie wird abgerechnet?', 'a' => 'Eine Sammelrechnung von Firmengolf mit allen Posten, einfach für HR und Buchhaltung.' ],
];

// Events der lokalen Partnerplätze (Stadt → Platz-Standort), Fallback verhindert leere Seite.
$city_events = function_exists( 'fge_city_events' ) ? fge_city_events( $city, 6 ) : [];
if ( empty( $city_events ) ) {
	$city_events = fge_get_featured_events( 6 );
}

// Icon-Set für die „Gründe"-Kacheln.
if ( ! function_exists( 'fge_city_ico' ) ) {
	function fge_city_ico( string $name ): string {
		$p = [
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
			'mountain' => '<path d="M3 20l6.5-11 4 6 2-3L21 20z"/>',
			'flag'     => '<path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/>',
			'castle'   => '<path d="M4 21V8l2 1V5l2 1V4l2 1V4l2-1v2l2-1v2l2-1v4l2-1v13z"/><path d="M10 21v-4h4v4"/>',
			'gift'     => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9"/><path d="M12 8S10.5 3 8 4.5 9.5 8 12 8zM12 8s1.5-5 4-3.5S14.5 8 12 8z"/>',
			'leaf'     => '<path d="M11 20A7 7 0 0 1 4 13C4 8 9 4 20 4c0 9-4 16-9 16z"/><path d="M4 20c4-4 7-6 11-7"/>',
		];
		$d = $p[ $name ] ?? $p['flag'];
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
	}
}

// ── SEO head injection ────────────────────────────────────────────────────────
add_filter( 'pre_get_document_title', static fn() => $seo_title );
add_action( 'wp_head', static function () use ( $seo_title, $seo_desc, $canonical, $city_name, $faqs ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $seo_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
	$city_og_img = function_exists( 'fge_default_og_image_url' ) ? fge_default_og_image_url() : '';
	if ( $city_og_img ) { echo '<meta property="og:image" content="' . esc_url( $city_og_img ) . '">' . "\n"; }

	$graph = [
		[
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'serviceType' => 'Firmen-Golfevents',
			'name'        => 'Firmengolf ' . $city_name,
			'areaServed'  => [ '@type' => 'City', 'name' => $city_name ],
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
				[ '@type' => 'ListItem', 'position' => 2, 'name' => 'Firmenevents ' . $city_name ],
			],
		],
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $graph ) . '</script>' . "\n";
} );

get_header();
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<?php /* Hero */ ?>
<section class="ev-hero" aria-label="Golf-Events in <?php echo esc_attr( $city_name ); ?>">
	<div class="ev-hero-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( 'golfplatz-panorama.jpg' ) ); ?>')">
		<div class="ev-hero-scrim" aria-hidden="true"></div>
		<div class="ev-hero-content">
			<div class="ev-hero-eyebrow">Golf-Events · <?php echo esc_html( $city_name ); ?></div>
			<h1 class="ev-hero-title">Firmen-Golfevents in <em class="mk-italic"><?php echo esc_html( $city_name ); ?></em>.</h1>
			<p class="ev-hero-sub">
				Teamevents, Firmenturniere, Schnupperkurse und individuelle Events auf Partnerplätzen rund um
				<?php echo esc_html( $city_name ); ?>. Eine Anfrage, ein Ansprechpartner, eine Rechnung.
			</p>
		</div>
	</div>
</section>

<?php /* Stats */ ?>
<div class="trust-strip" aria-label="Auf einen Blick">
	<div class="trust-inner">
		<div class="trust-cell"><div class="trust-t"><?php echo esc_html( $city_region ); ?></div><div class="trust-b">Region in unserem Netz</div></div>
		<div class="trust-cell"><div class="trust-t">&lt; 24 h</div><div class="trust-b">Antwort auf jede Anfrage</div></div>
		<div class="trust-cell"><div class="trust-t">Ein Kontakt</div><div class="trust-b">Vom Erstkontakt bis nach dem Event</div></div>
		<div class="trust-cell"><div class="trust-t">Eine Rechnung</div><div class="trust-b">Sauber abgerechnet</div></div>
	</div>
</div>

<?php /* Intro */ ?>
<section class="mk-section" aria-label="Über Golf-Events in <?php echo esc_attr( $city_name ); ?>">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Golf für Unternehmen in <?php echo esc_html( $city_name ); ?></div>
		<h2 class="mk-h2">Raus aus dem Büro, rein ins Grüne — in <?php echo esc_html( $city_name ); ?>.</h2>
		<p class="mk-sub" style="max-width:var(--width-prose);"><?php echo esc_html( $city['intro'] ); ?></p>
	</div>
</section>

<?php /* Gründe für Golf-Events in der Stadt */ ?>
<?php if ( ! empty( $city['reasons'] ) ) : ?>
<section class="mk-section city-reasons-section" aria-label="Gründe für Golf-Events in <?php echo esc_attr( $city_name ); ?>">
	<div class="city-reasons">
		<?php foreach ( $city['reasons'] as $r ) : ?>
		<div class="city-reason">
			<span class="city-reason-ic" aria-hidden="true"><?php echo fge_city_ico( $r['ic'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
			<h3 class="city-reason-t"><?php echo esc_html( $r['t'] ); ?></h3>
			<p class="city-reason-b"><?php echo esc_html( $r['b'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Events in region */ ?>
<?php if ( ! empty( $city_events ) ) : ?>
<section class="fg-grid-section" aria-label="Formate in <?php echo esc_attr( $city_name ); ?>">
	<div class="fg-grid-head">
		<h2 class="fg-grid-title">Beliebte Formate rund um <?php echo esc_html( $city_name ); ?></h2>
		<a class="fg-chip" href="<?php echo esc_url( $events_url ); ?>">Alle ansehen</a>
	</div>
	<div class="fg-grid">
		<?php foreach ( $city_events as $ev ) :
			$eid    = $ev->ID;
			$elabel = fge_format_event_type( fge_get_event_meta( $eid, 'event_type' ) );
			$dur    = fge_get_event_meta( $eid, 'duration' );
			$pmax   = (int) fge_get_event_meta( $eid, 'participants_max' );
			$price  = fge_get_event_price_display( $eid );
			$thumb  = function_exists( 'fge_event_cover_url' ) ? fge_event_cover_url( $eid, 'large' ) : ( has_post_thumbnail( $eid ) ? get_the_post_thumbnail_url( $eid, 'large' ) : fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' ) );
		?>
		<article class="fg-event">
			<a href="<?php echo esc_url( get_permalink( $eid ) ); ?>" style="display:contents">
				<div class="fg-event-photo" style="background-image:url('<?php echo esc_url( $thumb ); ?>')">
					<?php if ( $elabel ) : ?><div class="fg-event-chips"><span class="fg-photo-chip"><?php echo esc_html( $elabel ); ?></span></div><?php endif; ?>
				</div>
				<div class="fg-event-body">
					<h3 class="fg-event-title"><?php echo esc_html( $ev->post_title ); ?></h3>
					<div class="fg-event-meta">
						<?php if ( $dur ) : ?><span><?php echo esc_html( $dur ); ?></span><?php endif; ?>
						<?php if ( $pmax ) : ?><span class="dot">·</span><span>bis <?php echo esc_html( (string) $pmax ); ?> Gäste</span><?php endif; ?>
					</div>
					<div class="fg-event-foot">
						<span class="fg-event-price"><?php echo $price ? esc_html( $price ) : 'Auf Anfrage'; ?></span>
					</div>
				</div>
			</a>
		</article>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* FAQ */ ?>
<section class="mk-section faq-section" aria-label="FAQ">
	<div class="faq-shell">
		<div class="faq-aside">
			<div class="mk-eyebrow">Häufige Fragen</div>
			<h2 class="mk-h2" style="margin-top:8px;">Golf-Events in <?php echo esc_html( $city_name ); ?> — kurz erklärt.</h2>
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

<?php /* Cross-links to other cities + CTA */ ?>
<section class="mk-cta" aria-label="Anfrage">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Bereit für <?php echo esc_html( $city_name ); ?>?</div>
		<h2 class="mk-cta-h">Lasst uns euer Event in <?php echo esc_html( $city_name ); ?> <em class="mk-italic">planen</em>.</h2>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $ind_url ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">Event anfragen</a>
			<?php foreach ( $cities as $cslug => $c ) : if ( $cslug === $slug ) continue; ?>
				<a class="mk-cta-mail" href="<?php echo esc_url( home_url( '/golf-events/' . $cslug . '/' ) ); ?>"><?php echo esc_html( $c['name'] ); ?> →</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
document.querySelectorAll('.fge-page .faq-q[aria-expanded]').forEach(function (btn) {
	btn.addEventListener('click', function () {
		var item = btn.closest('.faq-item');
		var open = item.classList.toggle('open');
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		var t = btn.querySelector('.faq-toggle'); if (t) t.textContent = open ? '–' : '+';
	});
});
</script>

<?php get_footer();
