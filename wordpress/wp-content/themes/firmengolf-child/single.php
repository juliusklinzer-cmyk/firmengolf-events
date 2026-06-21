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
$author_id  = (int) get_the_author_meta( 'ID' );
$author     = get_the_author_meta( 'display_name', $author_id );
$author_bio = get_the_author_meta( 'description', $author_id );
$author_img = get_avatar_url( $author_id, [ 'size' => 88 ] );
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
		'publisher'        => [ '@type' => 'Organization', 'name' => 'Firmengolf', 'url' => home_url( '/' ) ],
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
			[ '@type' => 'ListItem', 'position' => 1, 'name' => 'Firmengolf', 'item' => home_url( '/' ) ],
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

	<article class="blog-article">

		<?php /* ── Back link ── */ ?>
		<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="ev-back">← Alle Artikel</a>

		<?php /* ── Article Header ── */ ?>
		<header class="blog-article-head">
			<div class="blog-meta-row">
				<?php if ( $cat ) : ?>
					<span class="blog-tag"><?php echo esc_html( $cat->name ); ?></span>
					<span>·</span>
				<?php endif; ?>
				<span><?php echo esc_html( $date ); ?></span>
				<span>·</span>
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
		<div class="blog-article-body">
			<?php the_content(); ?>
		</div>

	</article>

	<?php /* ── Related Posts ── */ ?>
	<?php if ( $related_posts ) : ?>
	<section class="blog-related">
		<div class="blog-related-head">
			<h2 class="blog-related-h">Weiterlesen</h2>
			<a class="fg-btn-ghost" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">
				Alle Artikel <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
		</div>
		<div class="blog-grid">
			<?php foreach ( $related_posts as $rp ) :
				$rid    = $rp->ID;
				$r_url  = get_permalink( $rid );
				$r_img  = has_post_thumbnail( $rid )
					? get_the_post_thumbnail_url( $rid, 'medium_large' )
					: fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' );
				$r_cats = get_the_category( $rid );
				$r_cat  = $r_cats[0] ?? null;
				$r_wc   = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $rid ) ) );
				$r_read = max( 1, (int) ceil( $r_wc / 200 ) );
				$r_date = get_the_date( 'd. M Y', $rid );
				$r_auth = get_the_author_meta( 'display_name', (int) $rp->post_author );
			?>
			<article class="blog-card">
				<a href="<?php echo esc_url( $r_url ); ?>" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;flex:1;">
					<div class="blog-card-photo" style="background-image:url('<?php echo esc_url( $r_img ); ?>')"></div>
					<div class="blog-card-body">
						<div class="blog-meta-row">
							<?php if ( $r_cat ) : ?>
								<span class="blog-tag"><?php echo esc_html( $r_cat->name ); ?></span>
								<span>·</span>
							<?php endif; ?>
							<span><?php echo esc_html( (string) $r_read ); ?> Min.</span>
						</div>
						<h3 class="blog-card-h"><?php echo esc_html( get_the_title( $rid ) ); ?></h3>
						<p class="blog-card-x"><?php echo esc_html( wp_trim_words( get_the_excerpt( $rid ), 18 ) ); ?></p>
						<div class="blog-card-foot">
							<span class="blog-author-n"><?php echo esc_html( $r_auth ); ?></span>
							<span class="blog-author-r"><?php echo esc_html( $r_date ); ?></span>
						</div>
					</div>
				</a>
			</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php
wp_reset_postdata();
get_footer();
