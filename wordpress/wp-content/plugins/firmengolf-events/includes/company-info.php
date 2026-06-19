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
	return [
		// Telefon / WhatsApp
		'phone_display'    => '+49 (0) 89 1225 1010',
		'phone_tel'        => '+498912251010',
		'whatsapp_display' => '+49 (0) 152 3434 8249',
		'whatsapp_number'  => '4915234348249',
		'whatsapp_url'     => 'https://wa.me/4915234348249',

		// E-Mail (alle @visionpunch.de)
		'email_general'    => 'hallo@visionpunch.de',
		'email_events'     => 'events@visionpunch.de',
		'email_partner'    => 'partner@visionpunch.de',
		'email_press'      => 'presse@visionpunch.de',
		'email_jobs'       => 'jobs@visionpunch.de',

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

/** Empfänger interner Admin-Benachrichtigungen (Hauptadresse). */
function fge_company_internal_email(): string {
	return fge_company()['email_events'];
}
