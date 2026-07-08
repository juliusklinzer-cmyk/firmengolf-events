<?php
/**
 * E-Mail-Verifizierung per 6-stelligem Code.
 *
 * Hintergrund (Julius, 2026-07-06): Ein Partner hat sich beim Onboarding in der
 * E-Mail vertippt (julius@ statt jklinzer@) — danach liefen alle Mails ins Leere.
 * Bevor eine selbst eingegebene Adresse „scharf" wird (Account-Anlage im
 * Onboarding/Einladung, Kontaktadressen-Wechsel im Portal), muss sie per Code
 * bestätigt werden. NICHT bei Kunden-Anfragen (Conversion).
 *
 * Code + Status liegen in Transients, kein DB-Schema nötig. Der Kontext-String
 * trennt die Anwendungsfälle (z. B. 'onboarding_123', 'invite_<token>',
 * 'portalmail_123'), sodass parallele Flows sich nicht in die Quere kommen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Transient-Key für den aktiven Code. */
function fge_ev_code_key( string $email, string $context ): string {
	return 'fge_evc_' . md5( $context . '|' . strtolower( trim( $email ) ) );
}

/** Transient-Key für „diese Adresse ist in diesem Kontext bestätigt". */
function fge_ev_verified_key( string $email, string $context ): string {
	return 'fge_evv_' . md5( $context . '|' . strtolower( trim( $email ) ) );
}

/**
 * Erzeugt einen Code, speichert ihn (gehasht) und mailt ihn.
 *
 * @return true|WP_Error  'cooldown' | 'toomany' | 'nomail' | true
 */
function fge_ev_send_code( string $email, string $context ) {
	$email = trim( $email );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'nomail', 'Bitte gib zuerst eine gültige E-Mail-Adresse ein.' );
	}

	// 60-Sekunden-Cooldown pro Kontext+Adresse.
	$cool_key = 'fge_evcd_' . md5( $context . '|' . strtolower( $email ) );
	if ( get_transient( $cool_key ) ) {
		return new WP_Error( 'cooldown', 'Bitte warte kurz, wir haben dir gerade erst einen Code geschickt.' );
	}

	// Max. 8 Sends pro Stunde pro Adresse (kontextübergreifend), Missbrauchsschutz.
	$rate_key = 'fge_evrl_' . md5( strtolower( $email ) );
	$sent     = (int) get_transient( $rate_key );
	if ( $sent >= 8 ) {
		return new WP_Error( 'toomany', 'Zu viele Code-Anfragen. Bitte versuche es in einer Stunde erneut oder melde dich bei uns.' );
	}

	$code = (string) wp_rand( 100000, 999999 );
	set_transient(
		fge_ev_code_key( $email, $context ),
		[ 'hash' => wp_hash( $code . '|' . strtolower( $email ) ), 'tries' => 0 ],
		15 * MINUTE_IN_SECONDS
	);

	$subject = 'Dein Firmengolf-Bestätigungscode';
	$content = '
		<p style="margin:0 0 16px;">Dein Bestätigungscode:</p>
		<p style="margin:0 0 20px;font-size:30px;font-weight:700;letter-spacing:0.28em;color:#20294D;">' . esc_html( $code ) . '</p>
		<p style="margin:0 0 8px;color:#6C736E;font-size:13px;">Der Code ist 15 Minuten gültig. Gib ihn einfach im offenen Formular ein.</p>
		<p style="margin:0;color:#6C736E;font-size:13px;">Wenn du das nicht warst, kannst du diese Mail ignorieren.</p>
	';
	$ok = wp_mail(
		$email,
		$subject,
		function_exists( 'fge_email_wrap' ) ? fge_email_wrap( $subject, $content ) : $content,
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);
	if ( ! $ok ) {
		// Fehlversand kostet keinen Cooldown/Rate-Slot (Kern-Audit N7) — sofortiger
		// zweiter Versuch bleibt möglich, der ungenutzte Code wird entwertet.
		delete_transient( fge_ev_code_key( $email, $context ) );
		return new WP_Error( 'nomail', 'Der Code konnte nicht verschickt werden. Bitte prüfe die Adresse oder versuch es gleich erneut.' );
	}
	// Cooldown + Stunden-Zähler erst NACH erfolgreichem Versand setzen (Kern-Audit N7).
	set_transient( $cool_key, 1, MINUTE_IN_SECONDS );
	set_transient( $rate_key, $sent + 1, HOUR_IN_SECONDS );
	return true;
}

/**
 * Prüft den eingegebenen Code. Bei Erfolg wird die Adresse für 2h als bestätigt
 * markiert und der Code entwertet.
 *
 * @return true|WP_Error  'expired' | 'wrong' | 'toomany' | true
 */
function fge_ev_check_code( string $email, string $context, string $code ) {
	$email = trim( $email );
	$code  = trim( $code );
	$key   = fge_ev_code_key( $email, $context );
	$rec   = get_transient( $key );
	if ( ! is_array( $rec ) || empty( $rec['hash'] ) ) {
		return new WP_Error( 'expired', 'Der Code ist abgelaufen, fordere einfach einen neuen an.' );
	}
	$tries = (int) ( $rec['tries'] ?? 0 ) + 1;
	if ( $tries > 5 ) {
		delete_transient( $key );
		return new WP_Error( 'toomany', 'Zu viele Fehlversuche. Bitte fordere einen neuen Code an.' );
	}
	if ( ! hash_equals( (string) $rec['hash'], wp_hash( $code . '|' . strtolower( $email ) ) ) ) {
		$rec['tries'] = $tries;
		set_transient( $key, $rec, 15 * MINUTE_IN_SECONDS );
		return new WP_Error( 'wrong', 'Der Code stimmt nicht, schau nochmal in die Mail.' );
	}
	delete_transient( $key );
	set_transient( fge_ev_verified_key( $email, $context ), 1, 2 * HOUR_IN_SECONDS );
	return true;
}

/** Ist die Adresse in diesem Kontext aktuell bestätigt? */
function fge_ev_is_verified( string $email, string $context ): bool {
	return (bool) get_transient( fge_ev_verified_key( $email, $context ) );
}

/** Räumt Code- und Verified-Status nach erfolgreichem Abschluss ab. */
function fge_ev_forget( string $email, string $context ): void {
	delete_transient( fge_ev_code_key( $email, $context ) );
	delete_transient( fge_ev_verified_key( $email, $context ) );
}
