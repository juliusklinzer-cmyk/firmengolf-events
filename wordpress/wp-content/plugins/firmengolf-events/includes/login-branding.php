<?php
/**
 * Firmengolf-Branding für wp-login.php.
 * Greift auf allen Login-Screens: Anmeldung, „Passwort vergessen" und
 * „Neues Passwort setzen" (der Link aus der Willkommens-Mail).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'login_enqueue_scripts', 'fge_login_branding_styles' );
function fge_login_branding_styles(): void {
	wp_enqueue_style(
		'fge-login',
		plugins_url( 'assets/css/fge-login.css', FGE_DIR . 'firmengolf-events.php' ),
		[],
		FGE_VERSION
	);
	$logo = function_exists( 'fge_get_logo_url' ) ? fge_get_logo_url() : '';
	if ( '' !== $logo ) {
		wp_add_inline_style( 'fge-login', '.login h1 a { background-image: url(' . esc_url( $logo ) . '); }' );
	}
}

/** Logo verlinkt auf die Startseite statt auf wordpress.org. */
add_filter( 'login_headerurl', static function (): string {
	return home_url( '/' );
} );

add_filter( 'login_headertext', static function (): string {
	return 'Firmengolf';
} );
