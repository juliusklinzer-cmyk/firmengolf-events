<?php
/**
 * Wiederverwendbare Blog-Karte (Grid + mobile Wisch-Reihen).
 * Args: [ 'id' => int (required) ].
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pid = (int) ( $args['id'] ?? 0 );
if ( $pid <= 0 || get_post_type( $pid ) !== 'post' ) {
	return;
}
$p_url   = get_permalink( $pid );
$p_img   = has_post_thumbnail( $pid )
	? get_the_post_thumbnail_url( $pid, 'medium_large' )
	: fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' );
$p_cats  = get_the_category( $pid );
$p_cat   = $p_cats[0] ?? null;
$p_date  = get_the_date( 'd. M Y', $pid );
$p_wc    = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $pid ) ) );
$p_read  = max( 1, (int) ceil( $p_wc / 200 ) );
$p_exc   = wp_trim_words( get_the_excerpt( $pid ), 20 );
$auth_id = (int) get_post_field( 'post_author', $pid );
$p_auth  = get_the_author_meta( 'display_name', $auth_id );
$avatar  = get_avatar_url( $auth_id, [ 'size' => 48 ] );
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
				<span class="blog-card-author">
					<?php if ( $avatar ) : ?>
						<img class="blog-card-avatar" src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $p_auth ); ?>" width="24" height="24" loading="lazy">
					<?php endif; ?>
					<span class="blog-author-n"><?php echo esc_html( $p_auth ); ?></span>
				</span>
				<span class="blog-author-r"><?php echo esc_html( $p_date ); ?></span>
			</div>
		</div>
	</a>
</article>
