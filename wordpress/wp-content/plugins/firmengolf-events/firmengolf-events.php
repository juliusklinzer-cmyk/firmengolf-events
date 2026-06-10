<?php
/**
 * Plugin Name: Firmengolf Events
 * Description: Custom event marketplace and partner portal for Firmengolf.
 * Version: 1.1.5
 * Author: Firmengolf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FGE_VERSION', '1.2.1' );
define( 'FGE_DIR', plugin_dir_path( __FILE__ ) );

require_once FGE_DIR . 'includes/post-types.php';
require_once FGE_DIR . 'includes/roles.php';
require_once FGE_DIR . 'includes/statuses.php';
require_once FGE_DIR . 'includes/helpers.php';
require_once FGE_DIR . 'includes/company-info.php';
require_once FGE_DIR . 'includes/catalogs.php';
require_once FGE_DIR . 'includes/partner-media.php';
require_once FGE_DIR . 'includes/partner-contacts.php';
require_once FGE_DIR . 'includes/request-responses.php';
require_once FGE_DIR . 'includes/event-pricing.php';
require_once FGE_DIR . 'includes/event-formats.php';
require_once FGE_DIR . 'includes/event-fields.php';
require_once FGE_DIR . 'includes/partner-fields.php';
require_once FGE_DIR . 'includes/request-fields.php';
require_once FGE_DIR . 'includes/admin-columns.php';
require_once FGE_DIR . 'includes/admin-approval.php';
require_once FGE_DIR . 'includes/frontend.php';
require_once FGE_DIR . 'includes/city-landing.php';
require_once FGE_DIR . 'includes/scheduling.php';
require_once FGE_DIR . 'includes/geo.php';
require_once FGE_DIR . 'includes/budget-calc.php';
require_once FGE_DIR . 'includes/form-handler.php';
require_once FGE_DIR . 'includes/emails.php';
require_once FGE_DIR . 'includes/request-followups.php';
require_once FGE_DIR . 'includes/contact-handler.php';
require_once FGE_DIR . 'includes/partner-portal.php';
require_once FGE_DIR . 'includes/onboarding.php';
require_once FGE_DIR . 'includes/rest-media.php';
require_once FGE_DIR . 'includes/media-widget.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once FGE_DIR . 'includes/cli-migrations.php';
}

function fge_activate() {
	fge_register_post_types();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'fge_activate' );

function fge_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'fge_deactivate' );
