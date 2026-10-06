<?php
/**
 * Teilnehmerzahl-Erinnerung: zurückgenommen (Julius, 06.10.2026).
 *
 * Der Stichtag 14 Tage vorher steht im Angebot und in § 4 Abs. 2 AGB, eine eigene
 * Erinnerungsmail soll es nicht geben. Diese Datei räumt nur noch den Cron aus 1.9.294 ab.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', static function (): void {
	$ts = wp_next_scheduled( 'fge_pax_reminder_cron' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'fge_pax_reminder_cron' );
	}
	wp_clear_scheduled_hook( 'fge_pax_reminder_cron' );
} );
