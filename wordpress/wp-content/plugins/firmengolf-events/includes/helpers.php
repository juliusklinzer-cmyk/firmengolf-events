<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculates the net sale price based on purchase prices and the Firmengolf markup.
 *
 * @param array $meta Associative array of field values (keys without _fge_ prefix).
 * @return float Rounded net sale price.
 */
function fge_calculate_sale_price_net( array $meta ): float {
	$mode   = $meta['pricing_mode'] ?? 'package';
	$markup = isset( $meta['firmengolf_markup_percent'] ) && $meta['firmengolf_markup_percent'] !== ''
		? (float) $meta['firmengolf_markup_percent']
		: 20.0;

	if ( $mode === 'package' ) {
		$basis = (float) ( $meta['purchase_price_package_net'] ?? 0 );
	} else {
		$individual_keys = [
			'purchase_price_meeting_room_hour_net',
			'purchase_price_range_net',
			'purchase_price_trainer_hour_net',
			'purchase_price_breakfast_net',
			'purchase_price_lunch_net',
			'purchase_price_dinner_net',
			'purchase_price_shuttle_net',
			'purchase_price_other_net',
		];
		$basis = 0.0;
		foreach ( $individual_keys as $key ) {
			$basis += (float) ( $meta[ $key ] ?? 0 );
		}
	}

	return round( $basis * ( 1 + $markup / 100 ), 2 );
}

/**
 * Returns an array of post options for a given post type, keyed by post ID.
 *
 * @param string $post_type The post type to query.
 * @return array<int, string> [ post_ID => 'Post Titel (#post_ID)' ]
 */
function fge_get_posts_select_options( string $post_type ): array {
	$posts = get_posts( [
		'post_type'   => $post_type,
		'post_status' => [ 'publish', 'draft', 'pending' ],
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
	] );

	$options = [];
	foreach ( $posts as $p ) {
		$options[ $p->ID ] = $p->post_title . ' (#' . $p->ID . ')';
	}
	return $options;
}
