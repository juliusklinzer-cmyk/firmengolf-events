<?php
/**
 * Angebot zurückziehen und neu auflegen.
 *
 * Bis 1.9.269 war ein versendetes Angebot endgültig: eine Änderung ging nur
 * über eine komplett neue Anfrage, mit neuer Vorgangsnummer und ohne Historie.
 * Bei zwei Anfragen im Monat ist das lästig, bei zwanzig am Tag untragbar.
 *
 * Mechanik: Der bisherige Snapshot wandert samt Datum und Versionsnummer ins
 * Archiv, die Zähler und Sperren werden zurückgesetzt, danach baut derselbe
 * Weg wie beim ersten Mal ein frisches Angebot aus den aktuellen Positionen.
 * Angebotsseite, PDF und Mail lesen unverändert den aktiven Snapshot, sie
 * müssen von Versionen nichts wissen.
 *
 * Ein bereits angenommenes Angebot wird nie neu aufgelegt. Es ist ein Vertrag.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Laufende Version des Angebots, 1 wenn nie neu aufgelegt wurde. */
function fge_offer_version( int $req ): int {
	return max( 1, (int) get_post_meta( $req, '_fge_offer_version', true ) );
}

/**
 * Frühere Fassungen, neueste zuerst.
 *
 * Ein leeres Metafeld liefert einen leeren String, und (array) '' ergibt ein
 * Array mit einem leeren Element. Deshalb wird hier gefiltert statt gecastet.
 */
function fge_offer_archive( int $req ): array {
	$raw  = get_post_meta( $req, '_fge_offer_archive', true );
	$rows = is_array( $raw ) ? array_values( array_filter( $raw, 'is_array' ) ) : [];
	usort( $rows, static fn( $a, $b ) => (int) ( $b['version'] ?? 0 ) <=> (int) ( $a['version'] ?? 0 ) );
	return $rows;
}

/** Darf dieses Angebot neu aufgelegt werden, und wenn nein, warum nicht. */
function fge_offer_relaunch_blocker( int $req ): string {
	if ( '1' !== (string) get_post_meta( $req, '_fge_offer_sent', true ) ) {
		return 'Es ist noch kein Angebot draußen.';
	}
	if ( 'accepted' === (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
		return 'Dieses Angebot wurde angenommen. Ein angenommenes Angebot ist ein Vertrag und wird nicht neu aufgelegt.';
	}
	if ( '1' === (string) get_post_meta( $req, '_fge_offer_options_mode', true ) ) {
		// Optionen-Angebot: der Termin steht erst mit der Wahl des Kunden.
		return function_exists( 'fge_offer_options_blocker' ) ? fge_offer_options_blocker( $req ) : '';
	}
	if ( function_exists( 'fge_rr_final_index' ) && fge_rr_final_index( $req ) < 1 ) {
		return 'Für diese Anfrage ist kein Termin bestätigt.';
	}
	if ( function_exists( 'fge_offer_is_priced' ) && ! fge_offer_is_priced( $req ) ) {
		return 'Ohne Preis kein Angebot. Erst die Positionen bepreisen.';
	}
	return '';
}

/**
 * Zurückziehen und neu senden.
 *
 * @return string Leer bei Erfolg, sonst der Grund.
 */
function fge_offer_relaunch( int $req ): string {
	$blocker = fge_offer_relaunch_blocker( $req );
	if ( '' !== $blocker ) {
		return $blocker;
	}

	$version = fge_offer_version( $req );
	$archive = fge_offer_archive( $req );
	$archive[] = [
		'version'  => $version,
		'snapshot' => (array) get_post_meta( $req, '_fge_offer_snapshot', true ),
		'status'   => (string) get_post_meta( $req, '_fge_offer_status', true ),
		'sent_at'  => (int) get_post_meta( $req, '_fge_offer_sent_at', true ),
		'deadline' => (int) get_post_meta( $req, '_fge_offer_deadline', true ),
		'retired'  => current_time( 'mysql' ),
	];
	update_post_meta( $req, '_fge_offer_archive', $archive );
	update_post_meta( $req, '_fge_offer_version', $version + 1 );

	// Sperren und Spuren der alten Fassung räumen, sonst hält der Einmal-Guard
	// den neuen Versand auf oder der Kunde sieht seine alte Auswahl.
	foreach ( [
		'_fge_offer_sent',
		'_fge_offer_decided',
		'_fge_offer_status',
		'_fge_offer_deadline',
		'_fge_offer_sent_at',
		'_fge_offer_query',
		'_fge_offer_expired_query',
		'_fge_offer_extras_selected',
		'_fge_offer_hold',
		'_fge_offer_review_done',
		// Sonst blieben die Dienstleister-Aufträge nach „abgelehnt, neu aufgelegt, angenommen" stumm.
		'_fge_xs_providers_notified',
	] as $key ) {
		delete_post_meta( $req, $key );
	}

	$idx = fge_rr_final_index( $req );
	update_post_meta( $req, '_fge_offer_review_done', 1 );

	// Derselbe Weg wie beim ersten Mal; die Kennung fürs Protokoll setzt
	// fge_send_offer_email() selbst.
	if ( '1' === (string) get_post_meta( $req, '_fge_offer_options_mode', true ) && function_exists( 'fge_offer_send_options' ) ) {
		fge_offer_send_options( $req );
	} else {
		fge_offer_on_date_confirmed( $req, $idx );
	}

	if ( '1' !== (string) get_post_meta( $req, '_fge_offer_sent', true ) ) {
		return 'Das neue Angebot konnte nicht erstellt werden. Bitte im WordPress-Backend nachsehen.';
	}

	if ( function_exists( 'fge_activity_add' ) ) {
		fge_activity_add( $req, 'offer', sprintf(
			'Angebot neu aufgelegt, Version %d ersetzt Version %d',
			$version + 1,
			$version
		) );
	}
	return '';
}

// ── Version sichtbar machen ──────────────────────────────────────────────────

/**
 * Versionszusatz für Betreff und Dokument, leer bei der ersten Fassung.
 * So merkt der Kunde, dass er ein ersetztes Angebot in der Hand hat.
 */
function fge_offer_version_suffix( int $req ): string {
	$v = fge_offer_version( $req );
	return $v > 1 ? ' (Fassung ' . $v . ')' : '';
}
