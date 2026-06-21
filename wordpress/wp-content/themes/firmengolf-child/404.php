<?php
/**
 * 404 – Seite nicht gefunden. Im Design des restlichen Frontends (Header/Nav/Footer
 * des Child-Themes), damit nicht das Eltern-Block-Theme einspringt.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

	<section class="fge-state">
		<p class="fge-state-code">404</p>
		<h1>Diese Seite gibt es leider nicht</h1>
		<p>Die aufgerufene Seite wurde verschoben oder existiert nicht mehr. Vielleicht findest du hier weiter:</p>
		<div class="fge-state-actions">
			<a class="fg-btn fg-btn-cta fg-btn-lg" href="<?php echo esc_url( home_url( '/' ) ); ?>">Zur Startseite</a>
			<a class="fg-btn fg-btn-ink fg-btn-lg" href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ); ?>">Alle Firmenevents</a>
		</div>
	</section>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
