<?php
/**
 * Template Name: Partner Onboarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="fge-page fge-page--onboarding">
	<?php fge_onboarding_render(); ?>
</div>
<?php
get_footer();
