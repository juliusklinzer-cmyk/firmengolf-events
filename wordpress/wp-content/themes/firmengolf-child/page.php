<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

	<div class="fg-content-page">
		<?php while ( have_posts() ) : the_post(); ?>
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
		<?php endwhile; ?>
	</div>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
