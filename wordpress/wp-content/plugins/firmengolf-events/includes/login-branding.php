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

// ── „Passwort vergessen"-Mail im Firmengolf-Look ──────────────────────────────

add_filter( 'retrieve_password_title', 'fge_retrieve_password_subject' );
function fge_retrieve_password_subject( $title ): string {
	return 'Firmengolf: Neues Passwort festlegen';
}

add_filter( 'retrieve_password_message', 'fge_retrieve_password_message', 10, 4 );
function fge_retrieve_password_message( $message, $key, $user_login, $user_data ) {
	if ( ! function_exists( 'fge_email_wrap' ) || ! $user_data instanceof WP_User ) {
		return $message; // Fallback: WordPress-Standardtext.
	}
	$reset_url = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user_login ), 'login' );
	$first     = (string) $user_data->first_name;
	$greeting  = $first !== '' ? 'Hallo ' . esc_html( $first ) . ',' : 'Hallo,';

	$content = '
		<p>' . $greeting . '</p>
		<p>für dein Firmengolf-Konto (<strong>' . esc_html( $user_data->user_email ) . '</strong>) wurde ein neues Passwort angefordert.</p>
		<p style="margin-top:28px;">
			<a href="' . esc_url( $reset_url ) . '" style="display:inline-block;background:#2a6e32;color:#ffffff;padding:10px 22px;text-decoration:none;border-radius:4px;font-size:14px;">Neues Passwort festlegen</a>
		</p>
		<p style="font-size:13px;color:#888;">Der Link ist aus Sicherheitsgründen 24 Stunden gültig. Wenn du das nicht warst, kannst du diese E-Mail einfach ignorieren, dein Passwort bleibt unverändert.</p>
	';

	// Nur diese eine Mail als HTML verschicken, danach wieder Standard.
	add_filter( 'wp_mail_content_type', 'fge_html_mail_content_type' );
	return fge_email_wrap( 'Neues Passwort festlegen', $content );
}

function fge_html_mail_content_type(): string {
	return 'text/html';
}

add_action( 'wp_mail_succeeded', 'fge_reset_html_mail_content_type' );
add_action( 'wp_mail_failed', 'fge_reset_html_mail_content_type' );
function fge_reset_html_mail_content_type(): void {
	remove_filter( 'wp_mail_content_type', 'fge_html_mail_content_type' );
}
