<?php
/**
 * Plugin Name: Firmengolf Events
 * Description: Custom event marketplace and partner portal for Firmengolf.
 * Version: 0.1.0
 * Author: Firmengolf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FGE_VERSION', '0.1.0' );
define( 'FGE_DIR', plugin_dir_path( __FILE__ ) );

require_once FGE_DIR . 'includes/post-types.php';
require_once FGE_DIR . 'includes/roles.php';
require_once FGE_DIR . 'includes/statuses.php';

function fge_activate() {
	fge_register_post_types();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'fge_activate' );

function fge_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'fge_deactivate' );
