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
	: fge_get_placeholder_image_url( 'event-team.jpg' );

// Estimated reading time
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

get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'blog' ] ); ?>

	<article class="blog-article">

		<?php /* ── Back link ── */ ?>
		<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="ev-back">← Zurück zum Magazin</a>

		<?php /* ── Article Header ── */ ?>
		<header class="blog-article-head">
			<div class="blog-meta-row">
				<?php if ( $cat ) : ?>
					<span class="blog-tag"><?php echo esc_html( $cat->name ); ?></span>
					<span>·</span>
				<?php endif; ?>
				<span><?php echo esc_html( $date ); ?></span>
				<span>·</span>
				<span><?php echo esc_html( $read_minutes ); ?> Min. Lesezeit</span>
			</div>

			<h1 class="blog-article-h"><?php echo esc_html( $title ); ?></h1>

			<?php if ( $excerpt ) : ?>
				<p class="blog-article-lead"><?php echo esc_html( wp_trim_words( $excerpt, 40 ) ); ?></p>
			<?php endif; ?>

			<div class="blog-article-byline">
				<img src="<?php echo esc_url( $author_img ); ?>" alt="<?php echo esc_attr( $author ); ?>" width="44" height="44">
				<div class="blog-author">
					<span class="blog-author-n"><?php echo esc_html( $author ); ?></span>
					<?php if ( $author_bio ) : ?>
						<span class="blog-author-r"><?php echo esc_html( $author_bio ); ?></span>
					<?php else : ?>
						<span class="blog-author-r">Autor</span>
					<?php endif; ?>
				</div>
			</div>
		</header>

		<?php /* ── Hero Photo ── */ ?>
		<div class="blog-article-photo" style="background-image:url('<?php echo esc_url( $thumb ); ?>')"></div>

		<?php /* ── Article Body ── */ ?>
		<div class="blog-article-body">
			<?php the_content(); ?>
		</div>

		<?php /* ── Related Posts ── */ ?>
		<?php if ( $related_posts ) : ?>
		<section class="blog-related" style="margin-top:80px;border-top:1px solid var(--ink-200);padding-top:48px;">
			<h2 style="font-family:var(--font-display);font-size:28px;letter-spacing:-.02em;font-weight:500;margin-bottom:28px;">
				Weitere Beiträge
			</h2>
			<div class="blog-grid">
				<?php foreach ( $related_posts as $rp ) :
					$rid   = $rp->ID;
					$r_url = get_permalink( $rid );
					$r_img = has_post_thumbnail( $rid )
						? get_the_post_thumbnail_url( $rid, 'medium_large' )
						: fge_get_placeholder_image_url( 'event-team.jpg' );
					$r_cats = get_the_category( $rid );
					$r_cat  = $r_cats[0] ?? null;
					$r_date = get_the_date( 'd. M Y', $rid );
					$r_exc  = wp_trim_words( get_the_excerpt( $rid ), 16 );
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
								<span><?php echo esc_html( $r_date ); ?></span>
							</div>
							<h3 class="blog-card-h"><?php echo esc_html( get_the_title( $rid ) ); ?></h3>
							<p class="blog-card-x"><?php echo esc_html( $r_exc ); ?></p>
							<div class="blog-card-foot">
								<span><?php echo esc_html( $r_auth ); ?></span>
								<span style="color:var(--fairway-700);font-weight:500;">Weiterlesen →</span>
							</div>
						</div>
					</a>
				</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

	</article>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php
wp_reset_postdata();
get_footer();
