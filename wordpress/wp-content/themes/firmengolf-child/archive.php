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

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'blog' ] ); ?>

	<?php /* ── Page Hero ── */ ?>
	<div class="page-hero">
		<div class="page-hero-inner">
			<div class="mk-eyebrow">Magazin</div>
			<h1 class="page-hero-title blog-hero">
				Aus dem <em class="mk-italic">Fairway</em> — unser Magazin.
			</h1>
			<p class="page-hero-sub">
				Praxisleitfäden, Inspiration für eure nächste Veranstaltung und Gespräche mit
				Menschen, die täglich auf den Plätzen unterwegs sind.
			</p>
		</div>
	</div>

	<?php /* ── Featured Article ── */ ?>
	<?php if ( $featured_post ) :
		$featured_id    = $featured_post->ID;
		$featured_url   = get_permalink( $featured_id );
		$featured_thumb = has_post_thumbnail( $featured_id )
			? get_the_post_thumbnail_url( $featured_id, 'large' )
			: fge_get_placeholder_image_url( 'golfplatz-drohnenaufnahme.jpg' );
		$featured_cats  = get_the_category( $featured_id );
		$featured_cat   = $featured_cats[0] ?? null;
		$featured_date  = get_the_date( 'd. F Y', $featured_id );
		$featured_exc   = wp_trim_words( get_the_excerpt( $featured_id ), 30 );
		$featured_wc    = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $featured_id ) ) );
		$featured_read  = max( 1, (int) ceil( $featured_wc / 200 ) );
		$featured_auth_id = (int) $featured_post->post_author;
		$featured_author  = get_the_author_meta( 'display_name', $featured_auth_id );
		$featured_bio     = get_the_author_meta( 'description', $featured_auth_id );
	?>
	<div class="blog-featured-sec">
		<a href="<?php echo esc_url( $featured_url ); ?>" class="blog-featured">
			<div class="blog-featured-photo" style="background-image:url('<?php echo esc_url( $featured_thumb ); ?>')">
				<span class="blog-top-tag">Top-Story</span>
			</div>
			<div class="blog-featured-body">
				<div class="blog-meta-row">
					<?php if ( $featured_cat ) : ?>
						<span class="blog-tag"><?php echo esc_html( $featured_cat->name ); ?></span>
						<span>·</span>
					<?php endif; ?>
					<span><?php echo esc_html( $featured_date ); ?></span>
					<span>·</span>
					<span><?php echo esc_html( (string) $featured_read ); ?> Min. Lesezeit</span>
				</div>
				<h2 class="blog-featured-h"><?php echo esc_html( get_the_title( $featured_id ) ); ?></h2>
				<p class="blog-featured-x"><?php echo esc_html( $featured_exc ); ?></p>
				<?php if ( $featured_author ) : ?>
					<div class="blog-author">
						<span class="blog-author-n"><?php echo esc_html( $featured_author ); ?></span>
						<span class="blog-author-r"><?php echo esc_html( $featured_bio ?: 'Autor' ); ?></span>
					</div>
				<?php endif; ?>
				<div class="blog-featured-cta">
					<span class="fg-btn-ghost">Artikel lesen <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</div>
			</div>
		</a>
	</div>
	<?php endif; ?>

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
			<div class="blog-grid">
				<?php foreach ( $remaining_posts as $p ) :
					$pid   = $p->ID;
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
								<span><?php echo esc_html( (string) $p_read ); ?> Min.</span>
							</div>
							<h3 class="blog-card-h"><?php echo esc_html( get_the_title( $pid ) ); ?></h3>
							<p class="blog-card-x"><?php echo esc_html( $p_exc ); ?></p>
							<div class="blog-card-foot">
								<span class="blog-author-n"><?php echo esc_html( $p_auth ); ?></span>
								<span class="blog-author-r"><?php echo esc_html( $p_date ); ?></span>
							</div>
						</div>
					</a>
				</article>
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

	<?php /* ── Newsletter ── */ ?>
	<div class="blog-newsletter">
		<div class="blog-newsletter-inner">
			<div>
				<div class="mk-eyebrow">Newsletter</div>
				<h2 class="blog-newsletter-h">Einmal im Monat — kurze Mail, gute Stories.</h2>
				<p class="muted">Lesetipps, neue Formate und Termine. Kein Spam, kein Vertrieb.</p>
				<form class="blog-newsletter-form" method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php wp_nonce_field( 'fge_newsletter', 'fge_newsletter_nonce' ); ?>
					<input type="email" name="fge_nl_email" class="fg-input" placeholder="deine@firma.de" required>
					<button type="submit" class="fg-btn-brand">Abonnieren</button>
				</form>
			</div>
			<div class="blog-newsletter-right" style="display:flex;flex-direction:column;gap:20px;">
				<?php
				$nl_posts = get_posts( [ 'post_type' => 'post', 'posts_per_page' => 2, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC' ] );
				foreach ( $nl_posts as $nlp ) :
					$nl_cats = get_the_category( $nlp->ID );
					$nl_cat  = $nl_cats[0] ?? null;
				?>
				<a href="<?php echo esc_url( get_permalink( $nlp->ID ) ); ?>" style="display:flex;gap:14px;text-decoration:none;align-items:flex-start;">
					<div style="width:56px;height:56px;border-radius:8px;background-size:cover;background-position:center;flex:none;background-image:url('<?php echo esc_url( has_post_thumbnail( $nlp->ID ) ? get_the_post_thumbnail_url( $nlp->ID, 'thumbnail' ) : fge_get_placeholder_image_url( 'golfplatz-rasen-qualitaet.jpg' ) ); ?>')"></div>
					<div>
						<?php if ( $nl_cat ) : ?>
							<span class="blog-tag" style="margin-bottom:4px;display:inline-block;"><?php echo esc_html( $nl_cat->name ); ?></span>
						<?php endif; ?>
						<div style="font-size:14px;font-weight:500;color:var(--ink-900);line-height:1.3;"><?php echo esc_html( get_the_title( $nlp->ID ) ); ?></div>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php
wp_reset_postdata();
get_footer();
