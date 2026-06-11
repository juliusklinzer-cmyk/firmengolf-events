<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fge_get_statuses( string $type ): array {
	$statuses = [
		'event'   => [
			'entwurf',
			'zur_pruefung',
			'freigegeben',
			'aenderung_in_pruefung',
			'pausiert',
			'abgelehnt',
		],
		'partner' => [
			'in_pruefung',
			'rueckfragen',
			'aktiv',
			'pausiert',
			'abgelehnt',
		],
		'request' => [
			'neu',
			'eingangsbestaetigung_gesendet',
			'verfuegbarkeit_wird_geprueft',
			'partner_angefragt',
			'teilweise_verfuegbar',
			'vollstaendig_verfuegbar',
			'nicht_verfuegbar',
			'telefonat_offen',
			'telefonat_erledigt',
			'angebot_in_lexoffice_erstellt',
			'angebot_versendet',
			'angebot_angenommen',
			'angebot_abgelehnt',
			'event_durchgefuehrt',
			'rechnung_in_lexoffice_erstellt',
			'abgeschlossen',
			'verloren',
		],
	];

	return $statuses[ $type ] ?? [];
}
