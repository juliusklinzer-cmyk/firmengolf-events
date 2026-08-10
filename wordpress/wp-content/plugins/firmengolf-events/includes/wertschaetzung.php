<?php
/**
 * Wertschätzungspaket: Landingpage /wertschaetzung/ + Bestellformular.
 *
 * Firmen zeichnen Mitarbeitende mit einem Golf-Erlebnis aus (Julius, 2026-08-10):
 * - Paket „Anerkennung" (59 € p. P.): Golf-Grundlagenkurs (Schnupperkurs) mit
 *   persönlichem Empfang, Leih-Equipment, Rabatt auf den Platzreifekurs.
 * - Paket „Platzreife Excellence" (450 € netto p. P.): namentliche Einladung zum
 *   mehrtägigen Platzreifekurs als exklusives Netzwerk-Event (Turnier + Theorie-
 *   prüfung, Platzreife bleibt, danach vergünstigte Jahresmitgliedschaft).
 *
 * Bewusst OHNE Backend-Speicherung: Bestellung geht strukturiert per Mail an
 * Firmengolf, Besteller bekommt eine Bestätigung (Rechnung folgt, Zustellung an
 * den Mitarbeiter innerhalb von 7 Werktagen). Preise sind Startpreise.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Paket-Katalog (zentral, Landingpage + Handler nutzen dieselben Daten). */
function fge_wz_packages(): array {
	return [
		'anerkennung' => [
			'name'  => 'Anerkennung',
			'price' => '59 € pro Person',
			'sub'   => 'Der Golf-Grundlagenkurs als persönliches Dankeschön.',
			'items' => [
				'Golf-Grundlagenkurs (Schnupperkurs) in kleiner Gruppe',
				'Persönlicher Empfang im Golfclub',
				'Leih-Equipment inklusive',
				'Exklusiver Rabatt auf den Platzreifekurs',
			],
		],
		'excellence' => [
			'name'  => 'Platzreife Excellence',
			'price' => '450 € netto pro Person',
			'sub'   => 'Die Platzreife als exklusives Netzwerk-Event mit den Besten der Besten.',
			'items' => [
				'Persönliche, namentliche Einladung im Auftrag eures Unternehmens',
				'Mehrtägiger Platzreifekurs, exklusiv mit anderen ausgezeichneten Talenten',
				'Platzreife-Turnier und Theorieprüfung, die Platzreife bleibt für immer',
				'Danach: vergünstigte Jahresmitgliedschaft im austragenden Club',
				'Golfplatz-Auswahl passend zum Wohnort, Termin frei wählbar',
			],
		],
	];
}

/* ── Routing: /wertschaetzung/ → template-wertschaetzung.php ─────────────────── */

add_action( 'init', static function () {
	add_rewrite_rule( '^wertschaetzung/?$', 'index.php?fge_wz=1', 'top' );
} );

add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_wz';
	return $vars;
} );

add_filter( 'template_include', static function ( $template ) {
	if ( ! get_query_var( 'fge_wz' ) ) {
		return $template;
	}
	$wz_template = locate_template( 'template-wertschaetzung.php' );
	return $wz_template ?: $template;
} );

add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^wertschaetzung/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );

/* ── Bestellung: AJAX-Handler (keine DB-Speicherung, alles per Mail) ─────────── */

add_action( 'wp_ajax_fge_wz_order', 'fge_ajax_wz_order' );
add_action( 'wp_ajax_nopriv_fge_wz_order', 'fge_ajax_wz_order' );
function fge_ajax_wz_order(): void {
	check_ajax_referer( 'fge_wz_order', 'nonce' );
	if ( function_exists( 'fge_form_spam_gate' ) ) {
		fge_form_spam_gate();
	}

	$packages = fge_wz_packages();
	$paket    = sanitize_key( (string) ( $_POST['paket'] ?? '' ) );
	if ( ! isset( $packages[ $paket ] ) ) {
		wp_send_json_error( [ 'message' => 'Bitte wähle ein Paket aus.' ], 400 );
	}
	$p = $packages[ $paket ];

	$f = static function ( string $key ): string {
		return sanitize_text_field( wp_unslash( (string) ( $_POST[ $key ] ?? '' ) ) );
	};
	$company   = $f( 'company' );
	$contact   = $f( 'contact' );
	$email     = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );
	$phone     = $f( 'phone' );
	$bill_addr = $f( 'bill_address' );
	$vat       = $f( 'vat' );
	$recipient = $f( 'recipient' );
	$rec_addr  = $f( 'rec_address' );
	$qty       = max( 1, min( 500, (int) ( $_POST['qty'] ?? 1 ) ) );
	$message   = sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) );
	$consent   = '1' === (string) ( $_POST['consent'] ?? '' );

	if ( '' === $company || '' === $contact || ! is_email( $email ) || '' === $bill_addr || '' === $recipient || '' === $rec_addr ) {
		wp_send_json_error( [ 'message' => 'Bitte fülle alle Pflichtfelder aus.' ], 400 );
	}
	if ( ! $consent ) {
		wp_send_json_error( [ 'message' => 'Bitte bestätige die Datenschutzerklärung.' ], 400 );
	}

	$ref = 'WZ-' . strtoupper( wp_generate_password( 6, false, false ) );

	// Interne Bestell-Mail (strukturiert, keine DB-Ablage gewollt).
	$rows = [
		'Referenz'            => $ref,
		'Paket'               => $p['name'] . ' (' . $p['price'] . ')',
		'Anzahl'              => (string) $qty,
		'Unternehmen'         => $company,
		'Ansprechpartner'     => $contact,
		'E-Mail'              => $email,
		'Telefon'             => $phone !== '' ? $phone : 'k. A.',
		'Rechnungsadresse'    => $bill_addr,
		'USt-ID'              => $vat !== '' ? $vat : 'k. A.',
		'Beschenkte Person'   => $recipient,
		'Versandadresse'      => $rec_addr,
		'Persönliche Nachricht' => $message !== '' ? $message : 'k. A.',
	];
	$internal = '<h2 style="margin:0 0 12px;">Neue Wertschätzungspaket-Bestellung</h2><table cellpadding="6" style="border-collapse:collapse;">';
	foreach ( $rows as $k => $v ) {
		$internal .= '<tr><td style="border:1px solid #ddd;font-weight:bold;vertical-align:top;">' . esc_html( $k ) . '</td><td style="border:1px solid #ddd;">' . nl2br( esc_html( $v ) ) . '</td></tr>';
	}
	$internal .= '</table>';
	$subject_i = 'Wertschätzungspaket-Bestellung ' . $ref . ': ' . $p['name'] . ' × ' . $qty . ' (' . $company . ')';
	$wrap      = static function ( string $subject, string $html ): string {
		return function_exists( 'fge_email_wrap' ) ? fge_email_wrap( $subject, $html ) : $html;
	};
	$to_internal = function_exists( 'fge_company_internal_email' ) ? fge_company_internal_email() : (string) get_option( 'admin_email' );
	wp_mail( $to_internal, $subject_i, $wrap( $subject_i, $internal ), [ 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $email ] );

	// Bestätigung an den Besteller.
	$subject_c = 'Deine Bestellung ist eingegangen (' . $ref . '), Firmengolf Wertschätzungspaket';
	$confirm   = '<p>Hallo ' . esc_html( $contact ) . ',</p>'
		. '<p>danke für eure Bestellung! Sie ist bei uns eingegangen und wird jetzt vorbereitet:</p>'
		. '<ul>'
		. '<li><strong>Paket:</strong> ' . esc_html( $p['name'] ) . ' (' . esc_html( $p['price'] ) . '), Anzahl: ' . (int) $qty . '</li>'
		. '<li><strong>Referenz:</strong> ' . esc_html( $ref ) . '</li>'
		. '</ul>'
		. '<p>So geht es weiter:</p>'
		. '<ol>'
		. '<li>Ihr erhaltet in Kürze die Rechnung an die angegebene Rechnungsadresse.</li>'
		. '<li>' . esc_html( $recipient ) . ' erhält innerhalb von 7 Werktagen das Wertschätzungspaket, persönlich und im Namen von ' . esc_html( $company ) . '.</li>'
		. '<li>Auf der Geschenkkarte stehen die zum Wohnort passenden Golfplätze als Optionen, den Termin wählt die beschenkte Person selbst.</li>'
		. '</ol>'
		. '<p>Fragen? Antworte einfach auf diese Mail.</p>'
		. '<p>Sportliche Grüße<br>dein Firmengolf-Team</p>';
	wp_mail( $email, $subject_c, $wrap( $subject_c, $confirm ), [ 'Content-Type: text/html; charset=UTF-8' ] );

	wp_send_json_success( [ 'ref' => $ref, 'email' => $email ] );
}
