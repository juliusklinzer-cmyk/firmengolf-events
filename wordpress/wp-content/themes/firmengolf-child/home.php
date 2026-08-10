<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Category filter ──────────────────────────────────────────────────────────
$active_cat_slug = sanitize_key( $_GET['category_name'] ?? '' );  // phpcs:ignore WordPress.Security.NonceVerification

$query_args = [
	'post_type'      => 'post',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
	'orderby'        => 'date',
	'order'          => 'DESC',
];
if ( $active_cat_slug ) {
	$query_args['category_name'] = $active_cat_slug;
}
$all_posts = new WP_Query( $query_args );
$posts_arr = $all_posts->posts;

$featured_post   = $posts_arr[0] ?? null;
$remaining_posts = array_slice( $posts_arr, 1 );

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

	<?php /* ── Page Hero: Text links, kompakte Top-Story rechts (Julius, 2026-08-10) ── */ ?>
	<div class="page-hero blog-hero-sec">
		<div class="page-hero-inner blog-hero-grid">
			<div class="blog-hero-copy">
				<div class="mk-eyebrow">Magazin</div>
				<h1 class="page-hero-title blog-hero">
					Aus dem <em class="mk-italic">Fairway</em>, unser Magazin.
				</h1>
				<p class="page-hero-sub">
					Praxisleitfäden, Inspiration für eure nächste Veranstaltung und Gespräche mit
					Menschen, die täglich auf den Plätzen unterwegs sind.
				</p>
			</div>
			<div class="blog-hero-photo" role="img" aria-label="Golferinnen in der Münchner U-Bahn" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( 'golferinnen-ubahn-muenchen.jpg' ) ); ?>')"></div>
		</div>
	</div>

	<?php /* ── Category Filter Chips ── */ ?>
	<?php if ( $categories ) : ?>
	<div class="blog-cats">
		<div class="blog-cats-inner">
			<a class="fg-chip<?php echo ! $active_cat_slug ? ' active' : ''; ?>"
			   href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Alle Themen</a>
			<?php foreach ( $categories as $cat ) : ?>
				<a class="fg-chip<?php echo $active_cat_slug === $cat->slug ? ' active' : ''; ?>"
				   href="<?php echo esc_url( add_query_arg( 'category_name', $cat->slug, home_url( '/blog/' ) ) ); ?>">
					<?php echo esc_html( $cat->name ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php /* ── Blog Grid ── */ ?>
	<section class="mk-section" style="padding-top:48px;padding-bottom:80px;">
		<?php if ( $remaining_posts ) : ?>
			<div class="blog-grid blog-grid-4">
				<?php
				// Mosaik: Top-Story als große Kachel (2 Spalten) vorneweg, Rest normal.
				$grid_posts = $featured_post ? array_merge( [ $featured_post ], $remaining_posts ) : $remaining_posts;
				foreach ( $grid_posts as $gi => $p ) :
					$pid   = $p->ID;
					$is_lg = 0 === $gi && $featured_post;
					$p_url = get_permalink( $pid );
					$p_img = has_post_thumbnail( $pid )
						? get_the_post_thumbnail_url( $pid, 'medium_large' )
						: fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' );
					$p_cats = get_the_category( $pid );
					$p_cat  = $p_cats[0] ?? null;
					$p_date = get_the_date( 'd. M Y', $pid );
					$p_wc   = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $pid ) ) );
					$p_read = max( 1, (int) ceil( $p_wc / 200 ) );
					$p_exc  = wp_trim_words( get_the_excerpt( $pid ), 20 );
					$p_by   = function_exists( 'fge_blog_author' ) ? fge_blog_author( $pid ) : null;
				?>
				<article class="blog-card<?php echo $is_lg ? ' blog-card-lg' : ''; ?>">
					<a href="<?php echo esc_url( $p_url ); ?>" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;flex:1;">
						<div class="blog-card-photo" style="background-image:url('<?php echo esc_url( $p_img ); ?>')"><?php if ( $is_lg ) : ?><span class="blog-top-tag">Top-Story</span><?php endif; ?></div>
						<div class="blog-card-body">
							<div class="blog-meta-row">
								<?php if ( $p_cat ) : ?>
									<span class="blog-tag"><?php echo esc_html( $p_cat->name ); ?></span>
									<span>·</span>
								<?php endif; ?>
								<span><?php echo esc_html( (string) $p_read ); ?> Min.</span>
							</div>
							<h3 class="blog-card-h"><?php echo esc_html( get_the_title( $pid ) ); ?></h3>
							<p class="blog-card-x"><?php echo esc_html( $p_exc ); ?></p>
							<div class="blog-card-foot">
								<?php if ( $p_by ) : ?>
								<span class="blog-card-author">
									<img class="blog-card-avatar" src="<?php echo esc_url( $p_by['img'] ); ?>" alt="<?php echo esc_attr( $p_by['name'] ); ?>" width="24" height="24" loading="lazy">
									<span class="blog-author-n"><?php echo esc_html( strtok( $p_by['name'], ' ' ) ); ?></span>
								</span>
								<?php endif; ?>
								<span class="blog-author-r"><?php echo esc_html( $p_date ); ?></span>
							</div>
						</div>
					</a>
				</article>
				<?php endforeach; ?>
			</div>
			<?php
			// Mobile (≤768px via CSS): Beiträge nach Kategorie in wischbaren Reihen.
			$blog_by_cat = [];
			foreach ( $remaining_posts as $bp ) {
				$bc  = get_the_category( $bp->ID );
				$bc0 = $bc[0] ?? null;
				$bk  = $bc0 ? (int) $bc0->term_id : 0;
				if ( ! isset( $blog_by_cat[ $bk ] ) ) { $blog_by_cat[ $bk ] = [ 'name' => $bc0 ? $bc0->name : 'Weitere', 'ids' => [] ]; }
				$blog_by_cat[ $bk ]['ids'][] = (int) $bp->ID;
			}
			?>
			<div class="blog-catbrowse">
				<?php foreach ( $blog_by_cat as $grp ) : ?>
					<section class="ev-catsec">
						<div class="ev-catsec-head">
							<h3 class="ev-catsec-h"><?php echo esc_html( $grp['name'] ); ?> <span class="ev-catsec-c"><?php echo (int) count( $grp['ids'] ); ?></span></h3>
						</div>
						<div class="ev-catrow blog-catrow">
							<?php foreach ( $grp['ids'] as $bid ) { get_template_part( 'template-parts/fge-blog-card', null, [ 'id' => $bid ] ); } ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		<?php elseif ( ! $featured_post ) : ?>
			<div style="text-align:center;padding:80px 0;">
				<div class="mk-eyebrow" style="margin-bottom:12px;">Demnächst</div>
				<h2 class="mk-h2" style="font-size:32px;">Beiträge erscheinen bald.</h2>
				<p class="muted" style="margin-top:12px;">Hier entstehen Ratgeber, Tipps und Eventideen rund um Golf als Firmenevent.</p>
			</div>
		<?php endif; ?>
	</section>

	<?php /* ── Seitenabschluss: Newsletter-Panel + Wertschätzungs-CTA (Julius, 2026-08-10) ── */ ?>
	<section class="mk-section blog-endcaps" aria-label="Newsletter und Wertschätzungspaket">
		<div class="blog-nl-panel">
			<div class="blog-nl-copy">
				<div class="mk-eyebrow">Newsletter</div>
				<h2 class="blog-newsletter-h">Einmal im Monat, kurze Mail, gute Stories.</h2>
				<p class="muted">Lesetipps, neue Formate und Termine. Kein Spam, kein Vertrieb.</p>
				<form class="blog-newsletter-form" method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php wp_nonce_field( 'fge_newsletter', 'fge_newsletter_nonce' ); ?>
					<input type="email" name="fge_nl_email" class="fg-input" placeholder="deine@firma.de" required>
					<button type="submit" class="fg-btn-brand">Abonnieren</button>
				</form>
			</div>
			<div class="blog-nl-picks">
				<div class="blog-nl-picks-h">Meistgelesen zuletzt</div>
				<?php
				$nl_posts = get_posts( [ 'post_type' => 'post', 'posts_per_page' => 2, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC' ] );
				foreach ( $nl_posts as $nlp ) :
					$nl_cats = get_the_category( $nlp->ID );
					$nl_cat  = $nl_cats[0] ?? null;
				?>
				<a class="blog-nl-pick" href="<?php echo esc_url( get_permalink( $nlp->ID ) ); ?>">
					<span class="blog-nl-pick-img" style="background-image:url('<?php echo esc_url( has_post_thumbnail( $nlp->ID ) ? get_the_post_thumbnail_url( $nlp->ID, 'thumbnail' ) : fge_get_placeholder_image_url( 'golfplatz-rasen-qualitaet.jpg' ) ); ?>')"></span>
					<span class="blog-nl-pick-txt">
						<?php if ( $nl_cat ) : ?><span class="blog-tag"><?php echo esc_html( $nl_cat->name ); ?></span><?php endif; ?>
						<span class="blog-nl-pick-t"><?php echo esc_html( get_the_title( $nlp->ID ) ); ?></span>
					</span>
				</a>
				<?php endforeach; ?>
			</div>
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
