<?php
/**
 * Pillar-Seite /teamevent-alternative/ — gerendert über includes/pillar-alternative.php.
 * Aufbau bewusst zitierfähig: Definition zuerst, Vergleichstabelle mit Zahlen,
 * Ablauf, FAQ (FAQPage-Schema), CTA. Optik wie die Format-Landingpages.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page = function_exists( 'fge_alternative_page_data' ) ? fge_alternative_page_data() : null;
if ( ! $page ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

$canonical  = function_exists( 'fge_alternative_url' ) ? fge_alternative_url() : home_url( '/teamevent-alternative/' );
$seo_title  = $page['title'];
$seo_desc   = $page['desc'];
$faqs       = $page['faqs'];
$events_url = (string) get_post_type_archive_link( 'firmengolf_event' );
$ind_url    = ( $p = get_page_by_path( 'individuelle-events' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/individuelle-events/' );
$anfrage    = add_query_arg( [ 'anfrage' => 'quick', 'anlass' => function_exists( 'fge_format_occasion' ) ? fge_format_occasion( 'teamevent' ) : 'teamevent' ], $ind_url );
$team_url   = home_url( '/firmenevent/teamevent/' );
$formats    = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];

if ( ! function_exists( 'fge_alt_ico' ) ) {
	function fge_alt_ico( string $name ): string {
		$p = [
			'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
			'handshake' => '<path d="m11 17 2 2a1 1 0 0 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 0 0 3-3l-3.9-3.9a2 2 0 0 0-2.8 0l-1.6 1.6a1 1 0 0 1-2.8-2.8l2.9-2.9"/><path d="m21 3-3 3M3 13l4 4"/>',
			'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
			'gift'      => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9"/><path d="M12 8S10.5 3 8 4.5 9.5 8 12 8zM12 8s1.5-5 4-3.5S14.5 8 12 8z"/>',
		];
		$d = $p[ $name ] ?? $p['users'];
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
	}
}

// ── SEO head ─────────────────────────────────────────────────────────────────
add_filter( 'pre_get_document_title', static fn() => $seo_title );
add_action( 'wp_head', static function () use ( $seo_title, $seo_desc, $canonical, $faqs, $page ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	echo '<meta property="og:type" content="article">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $seo_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
	$og_img = function_exists( 'fge_get_placeholder_image_url' ) ? fge_get_placeholder_image_url( $page['hero_img'] ) : '';
	if ( $og_img ) { echo '<meta property="og:image" content="' . esc_url( $og_img ) . '">' . "\n"; }

	$graph = [
		[
			'@context'      => 'https://schema.org',
			'@type'         => 'Article',
			'headline'      => $page['h1'],
			'description'   => $seo_desc,
			'url'           => $canonical,
			'inLanguage'    => 'de',
			'dateModified'  => '2026-09-15',
			'datePublished' => '2026-09-15',
			'about'         => [ 'Teamevent', 'Firmenevent', 'Golf-Teamevent', 'Escape Room Alternative' ],
			'author'        => [ '@type' => 'Person', 'name' => 'Julius Klinzer', 'jobTitle' => 'Gründer Firmengolf Events' ],
			'publisher'     => [ '@type' => 'Organization', 'name' => 'Firmengolf Events', 'url' => home_url( '/' ) ],
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
				[ '@type' => 'ListItem', 'position' => 1, 'name' => 'Firmengolf Events', 'item' => home_url( '/' ) ],
				[ '@type' => 'ListItem', 'position' => 2, 'name' => 'Teamevent-Alternative' ],
			],
		],
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $graph ) . '</script>' . "\n";
} );

get_header();
?>
<div class="fge-page fmt-lp alt-lp" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

<section class="ev-hero" aria-label="<?php echo esc_attr( $page['h1'] ); ?>">
	<div class="ev-hero-photo alt-hero-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( $page['hero_img'] ) ); ?>')">
		<div class="ev-hero-scrim" aria-hidden="true"></div>
		<div class="ev-hero-content">
			<div class="ev-hero-eyebrow"><?php echo esc_html( $page['eyebrow'] ); ?></div>
			<h1 class="ev-hero-title"><?php echo esc_html( $page['h1'] ); ?></h1>
			<p class="ev-hero-sub"><?php echo esc_html( $page['lead'] ); ?><?php if ( ! empty( $page['lead_more'] ) ) : ?> <span class="alt-lead-more"><?php echo esc_html( $page['lead_more'] ); ?></span><?php endif; ?></p>
			<div class="ev-hero-ctas">
				<a class="fg-btn-brand" href="#vergleich">Zum Vergleich</a>
				<a class="fg-btn-ghost-light" href="<?php echo esc_url( $anfrage ); ?>">Unverbindlich anfragen</a>
			</div>
		</div>
	</div>
</section>

<?php /* Definition: die ersten Sätze sind das, was Suchmaschinen und KI-Antworten zitieren */ ?>
<section class="mk-section alt-def" aria-label="Was ein Golf-Teamevent ist">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Kurz erklärt</div>
		<h2 class="mk-h2">Was ein Golf-Teamevent ist, und warum es als <em class="mk-italic">Alternative</em> funktioniert.</h2>
		<p class="mk-sub alt-def-text"><?php echo esc_html( $page['definition'] ); ?></p>
	</div>
</section>

<?php /* Vergleichstabelle */ ?>
<section class="mk-section alt-compare" id="vergleich" aria-label="Vergleich der Teamevent-Formate">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Der Vergleich</div>
		<h2 class="mk-h2">Golf gegen Escape Room, Kochkurs, Floßbau und Bowling.</h2>
		<p class="mk-sub">Dauer, Gruppengröße, Wetter und Preis pro Person im direkten Vergleich, damit ihr die Entscheidung mit Zahlen treffen könnt.</p>
	</div>
	<div class="fg-compare-wrap">
		<table class="fg-compare">
			<thead>
				<tr>
					<?php foreach ( $page['compare']['cols'] as $col ) : ?>
					<th scope="col"><?php echo esc_html( $col ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $page['compare']['rows'] as $row ) :
					$highlight = ! empty( $row[7] ); ?>
				<tr<?php echo $highlight ? ' class="is-golf"' : ''; ?>>
					<th scope="row"><?php echo esc_html( $row[0] ); ?></th>
					<?php for ( $i = 1; $i <= 6; $i++ ) : ?>
					<td data-label="<?php echo esc_attr( $page['compare']['cols'][ $i ] ); ?>"><?php echo esc_html( $row[ $i ] ); ?></td>
					<?php endfor; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="fg-compare-note"><?php echo esc_html( $page['compare']['note'] ); ?></p>
</section>

<?php /* Wann Golf die richtige Wahl ist */ ?>
<section class="mk-section fmt-keyfacts" aria-label="Wann Golf die richtige Wahl ist">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Wann Golf passt</div>
		<h2 class="mk-h2">Vier Situationen, in denen der Golfplatz die <em class="mk-italic">bessere</em> Wahl ist.</h2>
	</div>
	<div class="city-reasons">
		<?php foreach ( $page['when'] as $w ) : ?>
		<div class="city-reason">
			<span class="city-reason-ic" aria-hidden="true"><?php echo fge_alt_ico( $w['ic'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
			<h3 class="city-reason-t"><?php echo esc_html( $w['t'] ); ?></h3>
			<p class="city-reason-b"><?php echo esc_html( $w['b'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* Musterplanung mit Zahlen, als Beispiel gekennzeichnet */ ?>
<?php if ( ! empty( $page['example'] ) ) : ?>
<section class="mk-section alt-example" aria-label="Beispiel für ein Golf-Teamevent">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Beispiel</div>
		<h2 class="mk-h2"><?php echo esc_html( $page['example']['title'] ); ?></h2>
		<p class="mk-sub" style="max-width:var(--width-prose);"><?php echo esc_html( $page['example']['intro'] ); ?></p>
	</div>
	<div class="fg-compare-wrap">
		<table class="fg-compare fg-compare--keyval">
			<tbody>
				<?php foreach ( $page['example']['rows'] as $row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $row[0] ); ?></th>
					<td><?php echo esc_html( $row[1] ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
<?php endif; ?>

<?php /* Ablauf (aus der Teamevent-Konfiguration, damit beide Seiten dasselbe erzählen) */ ?>
<?php if ( ! empty( $page['flow'] ) ) : ?>
<section class="mk-section mk-band fmt-flow5" aria-label="So läuft ein Golf-Teamevent">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Der Ablauf</div>
		<h2 class="mk-h2">So läuft ein Golf-Teamevent, Schritt für Schritt.</h2>
	</div>
	<div class="fmt-flow5-row">
		<?php foreach ( $page['flow'] as $i => $step ) : ?>
		<div class="fmt-fstep" style="--fstep-delay:<?php echo esc_attr( (string) ( $i * 0.18 ) ); ?>s">
			<div class="fmt-fstep-n"><?php echo esc_html( (string) ( $i + 1 ) ); ?></div>
			<h3 class="fmt-fstep-t"><?php echo esc_html( $step['t'] ); ?></h3>
			<p class="fmt-fstep-b"><?php echo esc_html( $step['b'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
	<p class="alt-flow-link"><a class="faq-a-link" href="<?php echo esc_url( $team_url ); ?>">Alle Golf-Teamevents mit Preis ansehen &rarr;</a></p>
</section>
<?php endif; ?>

<?php /* FAQ */ ?>
<section class="mk-section faq-section" aria-label="FAQ">
	<div class="faq-shell">
		<div class="faq-aside">
			<div class="mk-eyebrow">Häufige Fragen</div>
			<h2 class="mk-h2" style="margin-top:8px;">Teamevent-Alternative, kurz beantwortet.</h2>
		</div>
		<?php get_template_part( 'template-parts/fge-faq', null, [
			'items' => array_map( static function ( $f ) {
				$html = esc_html( $f['a'] );
				if ( ! empty( $f['link']['url'] ) ) {
					$html .= ' <a class="faq-a-link" href="' . esc_url( $f['link']['url'] ) . '">' . esc_html( $f['link']['label'] ) . ' &rarr;</a>';
				}
				return [ 'q' => $f['q'], 'a_html' => $html ];
			}, $faqs ),
		] ); ?>
	</div>
</section>

<section class="mk-cta" aria-label="Anfrage">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Bereit für etwas anderes als Escape Room?</div>
		<h2 class="mk-cta-h">Lasst uns euer Teamevent <em class="mk-italic">planen</em>.</h2>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $anfrage ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">Event anfragen</a>
			<?php foreach ( $formats as $fslug => $f ) : ?>
				<a class="mk-cta-mail" href="<?php echo esc_url( home_url( '/firmenevent/' . $fslug . '/' ) ); ?>"><?php echo esc_html( $f['name'] ); ?> →</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
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
</script>

<?php get_footer();
