<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

the_post();
$post_id    = get_the_ID();
$title      = get_the_title();
$content    = get_the_content();
$excerpt    = get_the_excerpt();
$date       = get_the_date( 'd. F Y' );
$byline     = function_exists( 'fge_blog_author' ) ? fge_blog_author( $post_id ) : null;
$author     = $byline ? $byline['name'] : get_the_author_meta( 'display_name' );
$author_bio = $byline ? $byline['role'] : '';
$author_img = $byline ? $byline['img'] : get_avatar_url( (int) get_the_author_meta( 'ID' ), [ 'size' => 88 ] );
$cats       = get_the_category( $post_id );
$cat        = $cats[0] ?? null;
$thumb      = has_post_thumbnail( $post_id )
	? get_the_post_thumbnail_url( $post_id, 'full' )
	: fge_get_placeholder_image_url( 'golfplatz-drohnenaufnahme.jpg' );

$word_count   = str_word_count( wp_strip_all_tags( $content ) );
$read_minutes = max( 1, (int) ceil( $word_count / 200 ) );

// Related posts: same category, excluding current
$related_args = [
	'post_type'      => 'post',
	'posts_per_page' => 3,
	'post_status'    => 'publish',
	'post__not_in'   => [ $post_id ],
	'orderby'        => 'date',
	'order'          => 'DESC',
];
if ( $cat ) {
	$related_args['cat'] = $cat->term_id;
}
$related_posts = get_posts( $related_args );

// Fill up to 3 from all posts if category didn't yield enough
if ( count( $related_posts ) < 3 ) {
	$extra_args = [
		'post_type'      => 'post',
		'posts_per_page' => 3 - count( $related_posts ),
		'post_status'    => 'publish',
		'post__not_in'   => array_merge( [ $post_id ], array_column( $related_posts, 'ID' ) ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	];
	$related_posts = array_merge( $related_posts, get_posts( $extra_args ) );
}

// ── SEO: Blogpost-Title, Description, OpenGraph (Article) + BlogPosting-Schema ──
$post_seo_title = $title . ' | Firmengolf';
$post_seo_desc  = function_exists( 'fge_generate_description' ) ? fge_generate_description( $post_id ) : '';
add_filter( 'pre_get_document_title', function () use ( $post_seo_title ) {
	return $post_seo_title;
} );
add_action( 'wp_head', function () use ( $post_seo_title, $post_seo_desc, $post_id, $title, $thumb, $author ) {
	if ( function_exists( 'fge_render_seo_meta' ) ) {
		fge_render_seo_meta( [
			'title'   => $post_seo_title,
			'desc'    => $post_seo_desc,
			'url'     => get_permalink( $post_id ),
			'image'   => $thumb,
			'og_type' => 'article',
		] );
	}

	$schema = [
		'@context'         => 'https://schema.org',
		'@type'            => 'BlogPosting',
		'headline'         => $title,
		'datePublished'    => get_the_date( 'c', $post_id ),
		'dateModified'     => get_the_modified_date( 'c', $post_id ),
		'author'           => [ '@type' => 'Person', 'name' => $author ],
		'publisher'        => [ '@type' => 'Organization', 'name' => 'Firmengolf Events', 'url' => home_url( '/' ) ],
		'mainEntityOfPage' => [ '@type' => 'WebPage', '@id' => get_permalink( $post_id ) ],
	];
	if ( $post_seo_desc !== '' ) {
		$schema['description'] = $post_seo_desc;
	}
	if ( $thumb ) {
		$schema['image'] = $thumb;
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";

	$crumbs = [
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => [
			[ '@type' => 'ListItem', 'position' => 1, 'name' => 'Firmengolf Events', 'item' => home_url( '/' ) ],
			[ '@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => home_url( '/blog/' ) ],
			[ '@type' => 'ListItem', 'position' => 3, 'name' => $title ],
		],
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $crumbs ) . '</script>' . "\n";
} );

get_header();
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'blog' ] ); ?>

	<?php /* ── Lesefortschritt (Redesign 2026-08-21) ── */ ?>
	<div class="bg2-progress" aria-hidden="true"><span id="fge-read-progress"></span></div>

	<article class="blog-article">

		<?php /* ── Back link ── */ ?>
		<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="ev-back">← Alle Artikel</a>

		<?php /* ── Article Header ── */ ?>
		<header class="blog-article-head">
			<div class="bg2-meta">
				<?php if ( $cat ) : ?>
					<span class="bg2-pill"><?php echo esc_html( $cat->name ); ?></span>
				<?php endif; ?>
				<span><?php echo esc_html( $date ); ?></span>
				<span aria-hidden="true">·</span>
				<span><?php echo esc_html( (string) $read_minutes ); ?> Min. Lesezeit</span>
			</div>

			<h1 class="blog-article-h"><?php echo esc_html( $title ); ?></h1>

			<?php if ( $excerpt ) : ?>
				<p class="blog-article-lead"><?php echo esc_html( wp_trim_words( $excerpt, 40 ) ); ?></p>
			<?php endif; ?>

			<div class="blog-article-byline">
				<img src="<?php echo esc_url( $author_img ); ?>" alt="<?php echo esc_attr( $author ); ?>" width="44" height="44">
				<div>
					<div class="blog-author-n"><?php echo esc_html( $author ); ?></div>
					<div class="blog-author-r"><?php echo esc_html( $author_bio ?: 'Autor' ); ?></div>
				</div>
			</div>
		</header>

		<?php /* ── Hero Photo ── */ ?>
		<div class="blog-article-photo" style="background-image:url('<?php echo esc_url( $thumb ); ?>')"></div>

		<?php /* ── Article Body ── */ ?>
		<div class="blog-article-body" id="fge-article-body">
			<?php the_content(); ?>
		</div>

		<?php /* ── Teilen ── */ ?>
		<div class="bg2-share" aria-label="Artikel teilen">
			<span class="bg2-share-l">Artikel teilen</span>
			<button type="button" class="bg2-share-btn" id="fge-share-copy" data-url="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
				<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
				<span data-label>Link kopieren</span>
			</button>
			<a class="bg2-share-btn" href="<?php echo esc_url( 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( get_permalink( $post_id ) ) ); ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a>
			<a class="bg2-share-btn" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $title . ' ' . get_permalink( $post_id ) ) ); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
		</div>

	</article>

	<?php /* ── Schlanker CTA vor dem Weiterlesen ── */ ?>
	<section class="bg2-shell" aria-label="Event anfragen">
		<div class="fmt-promo bg2-article-cta">
			<div>
				<div class="mk-eyebrow">Vom Lesen ins Machen</div>
				<h2 class="mk-h2" style="font-size:28px;">Golf-Event für euer Team planen?</h2>
				<p class="mk-sub">Schickt uns eure Eckdaten in 30 Sekunden, ihr bekommt innerhalb eines Werktags konkrete Vorschläge.</p>
			</div>
			<a class="fg-btn-brand" href="<?php echo esc_url( home_url( '/individuelle-events/?anfrage=quick' ) ); ?>">In 30 Sekunden anfragen →</a>
		</div>
	</section>

	<?php /* ── Weiterlesen: kompakte Zeilenliste (Stil der Blog-Übersicht) ── */ ?>
	<?php if ( $related_posts ) : ?>
	<section class="bg2-shell bg2-listwrap" aria-label="Weiterlesen">
		<div class="blog-related-head">
			<h2 class="bg2-list-h" style="border-bottom:0;padding-bottom:0;">Weiterlesen</h2>
			<a class="fg-btn-ghost" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">
				Alle Artikel <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
		</div>
		<div class="bg2-list" style="border-top:1px solid rgba(14,19,16,.12);">
			<?php foreach ( $related_posts as $rp ) :
				$rid    = $rp->ID;
				$r_img  = has_post_thumbnail( $rid )
					? get_the_post_thumbnail_url( $rid, 'medium_large' )
					: fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' );
				$r_cats = get_the_category( $rid );
				$r_cat  = $r_cats[0] ?? null;
				$r_wc   = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $rid ) ) );
				$r_read = max( 1, (int) ceil( $r_wc / 200 ) );
				$r_date = get_the_date( 'd. M Y', $rid );
			?>
			<a class="bg2-row" href="<?php echo esc_url( get_permalink( $rid ) ); ?>">
				<span class="bg2-row-thumb"><img src="<?php echo esc_url( $r_img ); ?>" alt="" loading="lazy"></span>
				<span class="bg2-row-main">
					<span class="bg2-row-t"><?php echo esc_html( get_the_title( $rid ) ); ?></span>
					<span class="bg2-meta bg2-row-meta">
						<?php if ( $r_cat ) : ?><span class="bg2-pill"><?php echo esc_html( $r_cat->name ); ?></span><?php endif; ?>
						<span><?php echo esc_html( $r_date ); ?></span>
						<span aria-hidden="true">·</span>
						<span><?php echo esc_html( (string) $r_read ); ?> Min.</span>
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

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<script>
(function () {
	/* Lesefortschritt: Balken folgt der Scrollposition im Artikeltext. */
	var bar  = document.getElementById('fge-read-progress');
	var body = document.getElementById('fge-article-body');
	if (bar && body) {
		var update = function () {
			var r    = body.getBoundingClientRect();
			var total = r.height - window.innerHeight;
			var done  = Math.min(Math.max(-r.top, 0), Math.max(total, 1));
			bar.style.transform = 'scaleX(' + (total > 0 ? done / total : 1) + ')';
		};
		window.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update);
		update();
	}
	/* Link kopieren */
	var copy = document.getElementById('fge-share-copy');
	if (copy) copy.addEventListener('click', function () {
		var url = copy.getAttribute('data-url') || window.location.href;
		var done = function () {
			var l = copy.querySelector('[data-label]');
			if (!l) { return; }
			var orig = l.textContent;
			l.textContent = 'Kopiert!';
			setTimeout(function () { l.textContent = orig; }, 1400);
		};
		if (navigator.clipboard) {
			navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Link kopieren:', url); });
		} else { window.prompt('Link kopieren:', url); }
	});
})();
</script>
<?php
wp_reset_postdata();
get_footer();
