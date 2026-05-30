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

$featured_post    = $posts_arr[0] ?? null;
$remaining_posts  = array_slice( $posts_arr, 1 );

// All categories (with at least one published post)
$categories = get_categories( [ 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' ] );

get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'blog' ] ); ?>

	<?php /* ── Page Hero ── */ ?>
	<div class="page-hero blog-hero">
		<div class="page-hero-inner">
			<p class="fg-section-label">Magazin</p>
			<h1 class="page-hero-title">
				Wissen für dein<br>
				<em class="mk-italic">Firmenevent</em>
			</h1>
			<p class="page-hero-sub">Ratgeber, Eventideen und Praxistipps rund um Golf als Firmen- und Teamevent.</p>
		</div>
	</div>

	<?php /* ── Featured Article ── */ ?>
	<?php if ( $featured_post ) :
		$featured_id    = $featured_post->ID;
		$featured_url   = get_permalink( $featured_id );
		$featured_thumb = has_post_thumbnail( $featured_id )
			? get_the_post_thumbnail_url( $featured_id, 'large' )
			: fge_get_placeholder_image_url( 'event-team.jpg' );
		$featured_cats  = get_the_category( $featured_id );
		$featured_cat   = $featured_cats[0] ?? null;
		$featured_date  = get_the_date( 'd. F Y', $featured_id );
		$featured_exc   = wp_trim_words( get_the_excerpt( $featured_id ), 28 );
		$featured_author_id = (int) $featured_post->post_author;
		$featured_author    = get_the_author_meta( 'display_name', $featured_author_id );
	?>
	<section class="mk-section blog-featured-sec" style="max-width:1280px;margin:0 auto;padding-left:24px;padding-right:24px;">
		<a href="<?php echo esc_url( $featured_url ); ?>" class="blog-featured">
			<div class="blog-featured-photo" style="background-image:url('<?php echo esc_url( $featured_thumb ); ?>')">
				<?php if ( $featured_cat ) : ?>
					<span class="blog-top-tag"><?php echo esc_html( $featured_cat->name ); ?></span>
				<?php endif; ?>
			</div>
			<div class="blog-featured-body">
				<div class="blog-meta-row">
					<?php if ( $featured_cat ) : ?>
						<span class="blog-tag"><?php echo esc_html( $featured_cat->name ); ?></span>
						<span>·</span>
					<?php endif; ?>
					<span><?php echo esc_html( $featured_date ); ?></span>
				</div>
				<h2 class="blog-featured-h"><?php echo esc_html( get_the_title( $featured_id ) ); ?></h2>
				<p class="blog-featured-x"><?php echo esc_html( $featured_exc ); ?></p>
				<?php if ( $featured_author ) : ?>
					<div class="blog-author" style="margin-top:auto;">
						<span class="blog-author-n"><?php echo esc_html( $featured_author ); ?></span>
						<span class="blog-author-r">Autor</span>
					</div>
				<?php endif; ?>
			</div>
		</a>
	</section>
	<?php endif; ?>

	<?php /* ── Category Filter Chips ── */ ?>
	<?php if ( $categories ) : ?>
	<div class="blog-cats">
		<div class="blog-cats-inner">
			<a class="fg-chip<?php echo ! $active_cat_slug ? ' fg-chip active' : ''; ?>"
			   href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Alle</a>
			<?php foreach ( $categories as $cat ) : ?>
				<a class="fg-chip<?php echo $active_cat_slug === $cat->slug ? ' fg-chip active' : ''; ?>"
				   href="<?php echo esc_url( add_query_arg( 'category_name', $cat->slug, home_url( '/blog/' ) ) ); ?>">
					<?php echo esc_html( $cat->name ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php /* ── Blog Grid ── */ ?>
	<section class="mk-section" style="max-width:1280px;margin:0 auto;padding:40px 24px 64px;">
		<?php if ( $remaining_posts ) : ?>
			<div class="blog-grid">
				<?php foreach ( $remaining_posts as $p ) :
					$pid    = $p->ID;
					$p_url  = get_permalink( $pid );
					$p_img  = has_post_thumbnail( $pid )
						? get_the_post_thumbnail_url( $pid, 'medium_large' )
						: fge_get_placeholder_image_url( 'event-team.jpg' );
					$p_cats = get_the_category( $pid );
					$p_cat  = $p_cats[0] ?? null;
					$p_date = get_the_date( 'd. M Y', $pid );
					$p_exc  = wp_trim_words( get_the_excerpt( $pid ), 18 );
					$p_auth = get_the_author_meta( 'display_name', (int) $p->post_author );
				?>
				<article class="blog-card">
					<a href="<?php echo esc_url( $p_url ); ?>" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;flex:1;">
						<div class="blog-card-photo" style="background-image:url('<?php echo esc_url( $p_img ); ?>')"></div>
						<div class="blog-card-body">
							<div class="blog-meta-row">
								<?php if ( $p_cat ) : ?>
									<span class="blog-tag"><?php echo esc_html( $p_cat->name ); ?></span>
									<span>·</span>
								<?php endif; ?>
								<span><?php echo esc_html( $p_date ); ?></span>
							</div>
							<h3 class="blog-card-h"><?php echo esc_html( get_the_title( $pid ) ); ?></h3>
							<p class="blog-card-x"><?php echo esc_html( $p_exc ); ?></p>
							<div class="blog-card-foot">
								<span><?php echo esc_html( $p_auth ); ?></span>
								<span style="color:var(--fairway-700);font-weight:500;">Weiterlesen →</span>
							</div>
						</div>
					</a>
				</article>
				<?php endforeach; ?>
			</div>
		<?php elseif ( ! $featured_post ) : ?>
			<div class="fg-empty" style="text-align:center;padding:80px 0;">
				<h2 style="font-family:var(--font-display);font-size:28px;margin-bottom:12px;">Beiträge erscheinen demnächst.</h2>
				<p style="color:var(--ink-500);">Hier entstehen Ratgeber, Tipps und Eventideen rund um Golf als Firmenevent.</p>
			</div>
		<?php endif; ?>
	</section>

	<?php /* ── Newsletter ── */ ?>
	<div class="blog-newsletter">
		<div class="blog-newsletter-inner">
			<div>
				<p class="fg-section-label">Newsletter</p>
				<h2 class="blog-newsletter-h">Neue Beiträge direkt<br>in dein Postfach</h2>
			</div>
			<form class="blog-newsletter-form" method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php wp_nonce_field( 'fge_newsletter', 'fge_newsletter_nonce' ); ?>
				<input type="email" name="fge_nl_email" class="fg-input" placeholder="deine@email.de" required>
				<button type="submit" class="fg-btn-cta">Anmelden</button>
			</form>
		</div>
	</div>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php
wp_reset_postdata();
get_footer();
