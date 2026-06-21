<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

	<?php get_template_part( 'template-parts/fge-nav', null, [
		'cta_label'  => 'Event anfragen',
		'cta_active' => true,
	] ); ?>

	<?php /* ── Page Intro ── */ ?>
	<div class="fg-detail">
		<a href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ); ?>" class="fg-detail-back">
			<?php echo fge_icon_arrow_left(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			Alle Firmenevents
		</a>

		<header class="fg-page-intro">
			<p class="fg-section-label">Allgemeine Anfrage</p>
			<h1 class="fg-page-intro-title">Firmenevent anfragen</h1>
			<p class="fg-page-intro-sub">Du weißt noch nicht genau, welches Golf Event passt? Sag uns, was ihr plant — wir suchen gemeinsam das passende Format.</p>
			<p class="fg-page-intro-body">Firmengolf prüft passende Eventformate, Golfplätze und Dienstleister. Danach melden wir uns persönlich zur Abstimmung.</p>
		</header>

		<section class="fg-anfrage" id="general-anfrage" aria-label="Allgemeine Eventanfrage">
			<button type="button" class="fg-btn-cta fg-btn-lg" data-rw-open="full" data-rw-intro data-rw-source="general_anfrage_page">
				Anfrage starten <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>
			<p class="fg-rail-note" style="margin-top:14px;">Geführte Anfrage in fünf kurzen Schritten — ca. zwei Minuten, unverbindlich. Antwort innerhalb eines Werktags.</p>
		</section>

	</div><?php /* .fg-detail */ ?>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
