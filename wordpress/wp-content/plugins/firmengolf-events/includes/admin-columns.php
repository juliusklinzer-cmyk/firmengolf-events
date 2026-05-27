<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Firmenevent Spalten ───────────────────────────────────────────────────────

function fge_event_columns( array $columns ): array {
	unset( $columns['date'] );
	return array_merge( $columns, [
		'fge_event_type'         => 'Eventart',
		'fge_event_status'       => 'Status',
		'fge_provider_type'      => 'Anbieter Typ',
		'fge_assigned_partner'   => 'Golfplatz',
		'fge_sale_price_net'     => 'Verkaufspreis netto',
		'fge_views_count'        => 'Aufrufe',
		'fge_requests_count'     => 'Anfragen',
		'fge_bookings_count'     => 'Buchungen',
		'date'                   => 'Datum',
	] );
}
add_filter( 'manage_firmengolf_event_posts_columns', 'fge_event_columns' );

function fge_event_column_content( string $column, int $post_id ) {
	switch ( $column ) {
		case 'fge_event_type':
			echo esc_html( get_post_meta( $post_id, '_fge_event_type', true ) ?: '—' );
			break;
		case 'fge_event_status':
			echo esc_html( get_post_meta( $post_id, '_fge_event_status', true ) ?: '—' );
			break;
		case 'fge_provider_type':
			echo esc_html( get_post_meta( $post_id, '_fge_provider_type', true ) ?: '—' );
			break;
		case 'fge_assigned_partner':
			$pid = (int) get_post_meta( $post_id, '_fge_assigned_partner_id', true );
			echo $pid > 0 ? esc_html( get_the_title( $pid ) ) : '—';
			break;
		case 'fge_sale_price_net':
			$price = get_post_meta( $post_id, '_fge_sale_price_net', true );
			echo $price !== '' ? esc_html( number_format( (float) $price, 2, ',', '.' ) ) . ' €' : '—';
			break;
		case 'fge_views_count':
			echo esc_html( get_post_meta( $post_id, '_fge_views_count', true ) ?: '0' );
			break;
		case 'fge_requests_count':
			echo esc_html( get_post_meta( $post_id, '_fge_requests_count', true ) ?: '0' );
			break;
		case 'fge_bookings_count':
			echo esc_html( get_post_meta( $post_id, '_fge_bookings_count', true ) ?: '0' );
			break;
	}
}
add_action( 'manage_firmengolf_event_posts_custom_column', 'fge_event_column_content', 10, 2 );

// ── Golfplatz Partner Spalten ─────────────────────────────────────────────────

function fge_partner_columns( array $columns ): array {
	unset( $columns['date'] );
	return array_merge( $columns, [
		'fge_partner_status'          => 'Status',
		'fge_city'                    => 'Ort',
		'fge_federal_state'           => 'Bundesland',
		'fge_main_contact'            => 'Hauptansprechpartner',
		'fge_event_contact'           => 'Event Ansprechpartner',
		'fge_portal_active'           => 'Portal',
		'fge_published_events_count'  => 'Events',
		'fge_requests_total'          => 'Anfragen',
		'fge_bookings_total'          => 'Buchungen',
		'date'                        => 'Datum',
	] );
}
add_filter( 'manage_firmengolf_partner_posts_columns', 'fge_partner_columns' );

function fge_partner_column_content( string $column, int $post_id ) {
	switch ( $column ) {
		case 'fge_partner_status':
			echo esc_html( get_post_meta( $post_id, '_fge_partner_status', true ) ?: '—' );
			break;
		case 'fge_city':
			echo esc_html( get_post_meta( $post_id, '_fge_city', true ) ?: '—' );
			break;
		case 'fge_federal_state':
			echo esc_html( get_post_meta( $post_id, '_fge_federal_state', true ) ?: '—' );
			break;
		case 'fge_main_contact':
			$name = get_post_meta( $post_id, '_fge_main_contact_name', true );
			echo $name ? esc_html( $name ) : '—';
			break;
		case 'fge_event_contact':
			$name = get_post_meta( $post_id, '_fge_event_contact_name', true );
			echo $name ? esc_html( $name ) : '—';
			break;
		case 'fge_portal_active':
			echo get_post_meta( $post_id, '_fge_partner_portal_enabled', true ) == '1' ? '✓' : '—';
			break;
		case 'fge_published_events_count':
			echo esc_html( get_post_meta( $post_id, '_fge_published_events_count', true ) ?: '0' );
			break;
		case 'fge_requests_total':
			echo esc_html( get_post_meta( $post_id, '_fge_requests_total', true ) ?: '0' );
			break;
		case 'fge_bookings_total':
			echo esc_html( get_post_meta( $post_id, '_fge_bookings_total', true ) ?: '0' );
			break;
	}
}
add_action( 'manage_firmengolf_partner_posts_custom_column', 'fge_partner_column_content', 10, 2 );

// ── Event Anfragen Spalten ────────────────────────────────────────────────────

function fge_request_columns( array $columns ): array {
	unset( $columns['date'] );
	return array_merge( $columns, [
		'fge_request_status'      => 'Status',
		'fge_company_name'        => 'Unternehmen',
		'fge_contact_name'        => 'Kontakt',
		'fge_assigned_event'      => 'Event',
		'fge_assigned_partner'    => 'Golfplatz',
		'fge_participants'        => 'Teilnehmer',
		'fge_preferred_date_1'    => 'Wunschtermin 1',
		'fge_request_source'      => 'Quelle',
		'fge_request_date'        => 'Anfrage Datum',
	] );
}
add_filter( 'manage_firmengolf_request_posts_columns', 'fge_request_columns' );

function fge_request_column_content( string $column, int $post_id ) {
	switch ( $column ) {
		case 'fge_request_status':
			echo esc_html( get_post_meta( $post_id, '_fge_request_status', true ) ?: '—' );
			break;
		case 'fge_company_name':
			echo esc_html( get_post_meta( $post_id, '_fge_company_name', true ) ?: '—' );
			break;
		case 'fge_contact_name':
			$first = get_post_meta( $post_id, '_fge_contact_first_name', true );
			$last  = get_post_meta( $post_id, '_fge_contact_last_name', true );
			$name  = trim( $first . ' ' . $last );
			echo $name ? esc_html( $name ) : '—';
			break;
		case 'fge_assigned_event':
			$eid = (int) get_post_meta( $post_id, '_fge_assigned_event_id', true );
			echo $eid > 0 ? esc_html( get_the_title( $eid ) ) : '—';
			break;
		case 'fge_assigned_partner':
			$pid = (int) get_post_meta( $post_id, '_fge_assigned_partner_id', true );
			echo $pid > 0 ? esc_html( get_the_title( $pid ) ) : '—';
			break;
		case 'fge_participants':
			echo esc_html( get_post_meta( $post_id, '_fge_expected_participants', true ) ?: '—' );
			break;
		case 'fge_preferred_date_1':
			echo esc_html( get_post_meta( $post_id, '_fge_preferred_date_1', true ) ?: '—' );
			break;
		case 'fge_request_source':
			echo esc_html( get_post_meta( $post_id, '_fge_request_source', true ) ?: '—' );
			break;
		case 'fge_request_date':
			echo esc_html( get_post_meta( $post_id, '_fge_request_date', true ) ?: '—' );
			break;
	}
}
add_action( 'manage_firmengolf_request_posts_custom_column', 'fge_request_column_content', 10, 2 );
