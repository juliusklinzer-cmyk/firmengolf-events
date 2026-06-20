<?php
/**
 * Verbindlicher Mail-Absender für alle Plugin-Mails — VERSIONIERT.
 *
 * Wichtig: Das lokale MailHog-Routing + die From-Adresse liegen in der
 * git-ignorierten mu-plugins/fge-local-mail.php und existieren produktiv NICHT.
 * Damit produktiv nicht `wordpress@<domain>` als Absender erscheint, setzen wir
 * From/From-Name/Reply-To hier zentral (aus fge_company()).
 *
 * PRODUKTIV ZUSÄTZLICH NÖTIG (siehe Go-Live-Plan): echter SMTP-Versand über
 * visionpunch.de (One.com) + SPF/DKIM/DMARC — sonst landen Mails im Spam.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_mail_from', static function ( $from ) {
	$email = function_exists( 'fge_company' ) ? ( fge_company()['email_events'] ?? '' ) : '';
	return ( $email && is_email( $email ) ) ? $email : $from;
}, 20 );

add_filter( 'wp_mail_from_name', static function ( $name ) {
	$brand = function_exists( 'fge_company' ) ? ( fge_company()['brand'] ?? '' ) : '';
	return $brand !== '' ? $brand : $name;
}, 20 );

/**
 * Standard-Reply-To, falls eine Mail keinen eigenen setzt → Antworten („antworte
 * einfach auf diese E-Mail") landen im überwachten Postfach statt im Leeren.
 */
add_action( 'phpmailer_init', static function ( $phpmailer ) {
	if ( ! method_exists( $phpmailer, 'getReplyToAddresses' ) || ! empty( $phpmailer->getReplyToAddresses() ) ) {
		return;
	}
	$email = function_exists( 'fge_company' ) ? ( fge_company()['email_events'] ?? '' ) : '';
	$brand = function_exists( 'fge_company' ) ? ( fge_company()['brand'] ?? 'Firmengolf' ) : 'Firmengolf';
	if ( $email && is_email( $email ) ) {
		$phpmailer->addReplyTo( $email, $brand );
	}
} );
