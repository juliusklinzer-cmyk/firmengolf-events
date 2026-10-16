<?php
/**
 * Zentrale Firmen-/Kontaktdaten — Single Source of Truth.
 *
 * Alle öffentlichen Kontakt-, Adress- und Rechtsdaten an einer Stelle.
 * Änderungen hier wirken app-weit (Templates, E-Mails, Impressum).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string,string> Firmen- & Kontaktdaten.
 */
function fge_company(): array {
	static $merged = null;
	if ( null !== $merged ) {
		return $merged;
	}
	$base = fge_company_base();
	// Firmendaten aus dem Control Center (Einstellungen, Freigabe Julius
	// 16.10.2026) überschreiben fge_company_base(), nur die freigegebenen Schlüssel.
	$over = function_exists( 'get_option' ) ? (array) get_option( 'fge_company_overrides', [] ) : [];
	foreach ( array_intersect_key( $over, array_flip( fge_company_editable_keys() ) ) as $k => $v ) {
		if ( is_string( $v ) && '' !== trim( $v ) ) {
			$base[ $k ] = trim( $v );
		}
	}
	// Abgeleitete Werte passend zur Anzeige halten.
	if ( ! empty( $over['phone_display'] ) ) {
		$base['phone_tel'] = preg_replace( '/[^0-9+]/', '', str_replace( '(0)', '', $base['phone_display'] ) );
	}
	if ( ! empty( $over['whatsapp_display'] ) ) {
		$num                     = ltrim( preg_replace( '/[^0-9+]/', '', str_replace( '(0)', '', $base['whatsapp_display'] ) ), '+' );
		$base['whatsapp_number'] = $num;
		$base['whatsapp_url']    = 'https://wa.me/' . $num;
	}
	return $merged = $base;
}

/** Werte im Plugin, ohne Änderungen aus dem Control Center. */
function fge_company_base(): array {
	return [
		// Telefon / WhatsApp
		'phone_display'    => '+49 (0) 89 1225 1010',
		'phone_tel'        => '+498912251010',
		'whatsapp_display' => '+49 (0) 152 3434 8249',
		'whatsapp_number'  => '4915234348249',
		'whatsapp_url'     => 'https://wa.me/4915234348249',

		// E-Mail (alle @firmengolf-events.de)
		'email_general'    => 'hallo@firmengolf-events.de',
		'email_events'     => 'events@firmengolf-events.de',
		'email_partner'    => 'partner@firmengolf-events.de',
		'email_press'      => 'presse@firmengolf-events.de',
		'email_jobs'       => 'jobs@firmengolf-events.de',
		'email_privacy'    => 'datenschutz@firmengolf-events.de',
		// Julius persönlich: Kopie (BCC) der Angebotsmail an den Kunden (Julius, 18.09.2026).
		'email_owner'      => 'julius@firmengolf-events.de',
		// Google-Unternehmensprofil „Firmengolf" (Julius, 15.09.2026): Link für Bewertungsbitten.
		'google_review_url' => 'https://g.page/r/CVIA4CTsS9mtEBk/review',

		// Firma
		'legal_name'       => 'Visionpunch UG (haftungsbeschränkt)',
		'brand'            => 'Firmengolf',
		'managing_director' => 'Julius Klinzer',

		// Firmensitz (Impressum)
		'hq_street'        => 'Heerstr. 37',
		'hq_zip'           => '81247',
		'hq_city'          => 'München',

		// Büro (Besuchsadresse)
		'office_name'      => 'ELZE Laim',
		'office_street'    => 'Elsenheimerstr. 7-13',
		'office_zip'       => '80687',
		'office_city'      => 'München',
		'office_floor'     => '2. Stock',

		// Register & Steuer
		'register_court'   => 'Amtsgericht München',
		'register_no'      => 'HRB 305945',
		'ust_id'           => 'DE457632560',
		'tax_no'           => '143/190/50613',
		'finanzamt'        => 'Finanzamt München',
		'finanzamt_phone'  => '089/1252-1583',
		'vbg_no'           => '3408 0142 6665 001',
	];
}

/** Schlüssel, die im Control Center unter Firmendaten änderbar sind, mit Label. */
function fge_company_editable_fields(): array {
	return [
		'legal_name'        => 'Firma',
		'brand'             => 'Marke',
		'managing_director' => 'Geschäftsführer',
		'hq_street'         => 'Sitz, Straße',
		'hq_zip'            => 'Sitz, PLZ',
		'hq_city'           => 'Sitz, Ort',
		'office_name'       => 'Büro',
		'office_street'     => 'Büro, Straße',
		'office_zip'        => 'Büro, PLZ',
		'office_city'       => 'Büro, Ort',
		'office_floor'      => 'Büro, Stockwerk',
		'register_court'    => 'Registergericht',
		'register_no'       => 'Handelsregister',
		'ust_id'            => 'USt-IdNr.',
		'tax_no'            => 'Steuernummer',
		'phone_display'     => 'Telefon',
		'whatsapp_display'  => 'WhatsApp',
		'email_events'      => 'Absender-Mail und interne Mails',
		'email_general'     => 'Allgemeine Mail',
		'email_partner'     => 'Partner-Mail',
		'email_owner'       => 'Kopie der Angebote an',
	];
}

function fge_company_editable_keys(): array {
	return array_keys( fge_company_editable_fields() );
}

/** Empfänger interner Admin-Benachrichtigungen (Hauptadresse). */
function fge_company_internal_email(): string {
	return fge_company()['email_events'];
}
