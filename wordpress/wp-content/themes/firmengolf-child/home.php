<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Category filter ──────────────────────────────────────────────────────────
$active_cat_slug = sanitize_key( $_GET['category_name'] ?? '' );  // phpcs:ignore WordPress.Security.NonceVerification

// Editoriale Hierarchie (Redesign 2026-08-21): 1 Lead-Story gross, 2 Feature-
// Karten, der Rest als kompakte Zeilenliste. Ein Format pro Rolle statt
// neun identischer Karten.
$query_args = [
	'post_type'      => 'post',
	'posts_per_page' => 24,
	'post_status'    => 'publish',
	'orderby'        => 'date',
	'order'          => 'DESC',
];
if ( $active_cat_slug ) {
	$query_args['category_name'] = $active_cat_slug;
}
$all_posts = new WP_Query( $query_args );
$posts_arr = $all_posts->posts;

$featured_post = $posts_arr[0] ?? null;
$duo_posts     = array_slice( $posts_arr, 1, 2 );
$list_posts    = array_slice( $posts_arr, 3 );

// Meta-Zeile pro Beitrag einmal zentral berechnet.
$blog_meta = static function ( int $pid ): array {
	$cats = get_the_category( $pid );
	$wc   = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $pid ) ) );
	return [
		'cat'  => $cats[0] ?? null,
		'read' => max( 1, (int) ceil( $wc / 200 ) ),
		'date' => get_the_date( 'd. M Y', $pid ),
		'img'  => has_post_thumbnail( $pid )
			? (string) get_the_post_thumbnail_url( $pid, 'large' )
			: fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' ),
		'by'   => function_exists( 'fge_blog_author' ) ? fge_blog_author( $pid ) : null,
	];
};

$categories = get_categories( [ 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' ] );

get_header();
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

	<?php
	$blog_url    = home_url( '/blog/' );
	$active_cat  = $active_cat_slug ? array_filter( $categories, static fn( $c ) => $c->slug === $active_cat_slug ) : [];
	$active_cat  = $active_cat ? reset( $active_cat ) : null;
	$pill_label  = $active_cat ? $active_cat->name : 'Beiträge durchsuchen';
	$mbar_action = '<button class="ev-msearch" type="button" id="fge-blog-pill" aria-label="Blog durchsuchen">'
		. '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>'
		. '<span class="ev-msearch-t ' . ( $active_cat ? '' : 'muted' ) . '">' . esc_html( $pill_label ) . '</span></button>';
	get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'blog', 'mbar_action' => $mbar_action ] );
	?>

	<?php /* ── Mobiles Blog-Such-Sheet (≤768px) ── */ ?>
	<div class="ev-sheet-scrim" id="fge-blog-sheet">
		<div class="ev-sheet" role="dialog" aria-modal="true" aria-label="Im Magazin suchen">
			<div class="ev-sheet-top">
				<button class="ev-sheet-close" type="button" aria-label="Schließen">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
				</button>
				<span class="ev-sheet-title">Im Magazin suchen</span>
			</div>
			<div class="ev-sheet-body">
				<section class="ev-sheet-card">
					<div class="ev-sheet-q">Kategorie</div>
					<div class="ev-sheet-chips">
						<a class="ev-sheet-chip <?php echo $active_cat ? '' : 'on'; ?>" href="<?php echo esc_url( $blog_url ); ?>">Alle</a>
						<?php foreach ( $categories as $c ) : ?>
							<a class="ev-sheet-chip <?php echo $active_cat_slug === $c->slug ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'category_name', $c->slug, $blog_url ) ); ?>"><?php echo esc_html( $c->name ); ?></a>
						<?php endforeach; ?>
					</div>
				</section>
				<section class="ev-sheet-card">
					<div class="ev-sheet-q">Stichwort</div>
					<form id="fge-blog-search-form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search">
						<div class="ev-loc-input">
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
							<input type="search" name="s" placeholder="Beiträge durchsuchen" autocomplete="off">
						</div>
					</form>
				</section>
			</div>
			<div class="ev-sheet-foot">
				<a class="ev-sheet-clear" href="<?php echo esc_url( $blog_url ); ?>">Zurücksetzen</a>
				<button class="ev-sheet-go" type="submit" form="fge-blog-search-form">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
					Suchen
				</button>
			</div>
		</div>
	</div>
	<script>
	(function () {
		var pill  = document.getElementById('fge-blog-pill');
		var sheet = document.getElementById('fge-blog-sheet');
		if (!sheet) { return; }
		var lastFocus = null;
		var openS  = function () {
			lastFocus = document.activeElement;
			sheet.classList.add('is-open'); document.body.style.overflow = 'hidden';
			var first = sheet.querySelector('.ev-sheet-close, button, input, a[href]');
			if (first) { first.focus(); }
		};
		var closeS = function () {
			sheet.classList.remove('is-open'); document.body.style.overflow = '';
			if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
		};
		if (pill) { pill.addEventListener('click', openS); }
		sheet.addEventListener('click', function (e) { if (e.target === sheet) { closeS(); } });
		sheet.querySelector('.ev-sheet-close').addEventListener('click', closeS);
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sheet.classList.contains('is-open')) { closeS(); } });
		/* Fokus im aria-modal-Sheet halten */
		sheet.addEventListener('keydown', function (e) {
			if (e.key !== 'Tab' || !sheet.classList.contains('is-open')) { return; }
			var els = sheet.querySelectorAll('button, input, select, textarea, a[href], [tabindex]:not([tabindex="-1"])');
			if (!els.length) { return; }
			var first = els[0], last = els[els.length - 1];
			if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
		});
	})();
	</script>

	<?php /* ── Masthead: zentrierter Magazin-Kopf mit Themenzeile (Redesign 2026-08-21) ── */ ?>
	<header class="bg2-masthead">
		<div class="mk-eyebrow">Magazin</div>
		<h1 class="bg2-masthead-h">Aus dem <em class="mk-italic">Fairway</em>.</h1>
		<p class="bg2-masthead-sub">
			Praxisleitfäden, Inspiration für eure nächste Veranstaltung und Gespräche mit
			Menschen, die täglich auf den Plätzen unterwegs sind.
		</p>
		<?php if ( $categories ) : ?>
		<nav class="bg2-cats" aria-label="Themen">
			<a class="bg2-cat<?php echo ! $active_cat_slug ? ' on' : ''; ?>" href="<?php echo esc_url( $blog_url ); ?>">Alle Themen</a>
			<?php foreach ( $categories as $cat ) : ?>
				<a class="bg2-cat<?php echo $active_cat_slug === $cat->slug ? ' on' : ''; ?>"
				   href="<?php echo esc_url( add_query_arg( 'category_name', $cat->slug, $blog_url ) ); ?>">
					<?php echo esc_html( $cat->name ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
	</header>

	<?php if ( $featured_post ) : $fm = $blog_meta( (int) $featured_post->ID ); ?>
	<?php /* ── Lead-Story: grosser editorialer Split ── */ ?>
	<section class="bg2-shell" aria-label="Top-Story">
		<a class="bg2-lead" href="<?php echo esc_url( get_permalink( $featured_post->ID ) ); ?>">
			<div class="bg2-lead-media">
				<img src="<?php echo esc_url( $fm['img'] ); ?>" alt="" loading="eager">
			</div>
			<div class="bg2-lead-body">
				<div class="bg2-meta">
					<?php if ( $fm['cat'] ) : ?><span class="bg2-pill"><?php echo esc_html( $fm['cat']->name ); ?></span><?php endif; ?>
					<span><?php echo esc_html( $fm['date'] ); ?></span>
					<span aria-hidden="true">·</span>
					<span><?php echo esc_html( (string) $fm['read'] ); ?> Min. Lesezeit</span>
				</div>
				<h2 class="bg2-lead-h"><?php echo esc_html( get_the_title( $featured_post->ID ) ); ?></h2>
				<p class="bg2-lead-x"><?php echo esc_html( wp_trim_words( get_the_excerpt( $featured_post->ID ), 32 ) ); ?></p>
				<div class="bg2-lead-foot">
					<?php if ( $fm['by'] ) : ?>
					<span class="blog-card-author">
						<img class="blog-card-avatar" src="<?php echo esc_url( $fm['by']['img'] ); ?>" alt="<?php echo esc_attr( $fm['by']['name'] ); ?>" width="24" height="24" loading="lazy">
						<span class="blog-author-n"><?php echo esc_html( $fm['by']['name'] ); ?></span>
					</span>
					<?php endif; ?>
					<span class="bg2-read">Artikel lesen
						<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
					</span>
				</div>
			</div>
		</a>
	</section>
	<?php endif; ?>

	<?php if ( $duo_posts ) : ?>
	<?php /* ── Zwei Feature-Karten ── */ ?>
	<section class="bg2-shell" aria-label="Aktuelle Beiträge">
		<div class="bg2-duo">
			<?php foreach ( $duo_posts as $dp ) : $dm = $blog_meta( (int) $dp->ID ); ?>
			<a class="bg2-card" href="<?php echo esc_url( get_permalink( $dp->ID ) ); ?>">
				<div class="bg2-card-media">
					<img src="<?php echo esc_url( $dm['img'] ); ?>" alt="" loading="lazy">
					<?php if ( $dm['cat'] ) : ?><span class="bg2-pill bg2-pill--onimg"><?php echo esc_html( $dm['cat']->name ); ?></span><?php endif; ?>
				</div>
				<div class="bg2-card-body">
					<h3 class="bg2-card-h"><?php echo esc_html( get_the_title( $dp->ID ) ); ?></h3>
					<p class="bg2-card-x"><?php echo esc_html( wp_trim_words( get_the_excerpt( $dp->ID ), 20 ) ); ?></p>
					<div class="bg2-meta">
						<?php if ( $dm['by'] ) : ?><span><?php echo esc_html( strtok( $dm['by']['name'], ' ' ) ); ?></span><span aria-hidden="true">·</span><?php endif; ?>
						<span><?php echo esc_html( $dm['date'] ); ?></span>
						<span aria-hidden="true">·</span>
						<span><?php echo esc_html( (string) $dm['read'] ); ?> Min.</span>
					</div>
				</div>
			</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $list_posts ) : ?>
	<?php /* ── Alle weiteren Beitraege als kompakte Zeilenliste ── */ ?>
	<section class="bg2-shell bg2-listwrap" aria-label="Alle Beiträge">
		<h2 class="bg2-list-h">Alle Beiträge</h2>
		<div class="bg2-list">
			<?php foreach ( $list_posts as $lp ) : $lm = $blog_meta( (int) $lp->ID ); ?>
			<a class="bg2-row" href="<?php echo esc_url( get_permalink( $lp->ID ) ); ?>">
				<span class="bg2-row-thumb"><img src="<?php echo esc_url( $lm['img'] ); ?>" alt="" loading="lazy"></span>
				<span class="bg2-row-main">
					<span class="bg2-row-t"><?php echo esc_html( get_the_title( $lp->ID ) ); ?></span>
					<span class="bg2-meta bg2-row-meta">
						<?php if ( $lm['cat'] ) : ?><span class="bg2-pill"><?php echo esc_html( $lm['cat']->name ); ?></span><?php endif; ?>
						<span><?php echo esc_html( $lm['date'] ); ?></span>
						<span aria-hidden="true">·</span>
						<span><?php echo esc_html( (string) $lm['read'] ); ?> Min.</span>
					</span>
				</span>
				<span class="bg2-row-go" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
				</span>
			</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( ! $featured_post ) : ?>
	<section class="bg2-shell" aria-label="Demnächst">
		<div style="text-align:center;padding:80px 0;">
			<div class="mk-eyebrow" style="margin-bottom:12px;">Demnächst</div>
			<h2 class="mk-h2" style="font-size:32px;">Beiträge erscheinen bald.</h2>
			<p class="muted" style="margin-top:12px;">Hier entstehen Ratgeber, Tipps und Eventideen rund um Golf als Firmenevent.</p>
		</div>
	</section>
	<?php endif; ?>

	<?php /* ── Seitenabschluss: Kontakt-Panel + Wertschätzungs-CTA (Julius, 2026-08-10)
		Das frühere Newsletter-Formular ist raus: es hatte keinen Handler, Mails
		wurden still verworfen (Audit 2026-08-12). Statt eines toten Funnels hier
		der direkte Weg zum Ansprechpartner. ── */ ?>
	<section class="mk-section blog-endcaps" aria-label="Kontakt und Wertschätzungspaket">
		<div class="blog-nl-panel">
			<div class="blog-nl-copy">
				<div class="mk-eyebrow">Noch Fragen?</div>
				<h2 class="blog-newsletter-h">Lieber kurz sprechen statt lange lesen.</h2>
				<p class="muted">Erzähl uns von eurem Anlass, wir melden uns innerhalb eines Werktags mit konkreten Vorschlägen.</p>
				<div class="blog-nl-cta">
					<a class="fg-btn-brand" href="<?php echo esc_url( home_url( '/individuelle-events/?anfrage=quick' ) ); ?>">In 30 Sekunden anfragen</a>
					<a class="fg-btn-ghost" href="<?php echo esc_url( home_url( '/kontakt/' ) ); ?>">Zum Kontakt</a>
				</div>
			</div>
			<div class="blog-nl-img" role="img" aria-label="Golfplatz im Abendlicht" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( 'golfplatz-huegel-abendlicht.jpg' ) ); ?>')"></div>
		</div>

		<div class="fmt-promo blog-wz-promo">
			<div>
				<div class="mk-eyebrow">Mitarbeiter auszeichnen</div>
				<h2 class="mk-h2" style="font-size:30px;">Wertschätzung, die bleibt.</h2>
				<p class="mk-sub">Belohne besondere Leistungen mit einem persönlichen Golf-Erlebnis: vom Grundlagenkurs mit persönlichem Empfang bis zur Platzreife als exklusiver Networking-Kurs.</p>
			</div>
			<a class="fg-btn-brand" href="<?php echo esc_url( home_url( '/wertschaetzung/' ) ); ?>">Wertschätzungspaket entdecken →</a>
		</div>
	</section>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php
wp_reset_postdata();
get_footer();
