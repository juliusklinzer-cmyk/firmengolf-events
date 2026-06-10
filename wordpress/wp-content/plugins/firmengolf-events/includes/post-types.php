<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fge_register_post_types() {

	register_post_type( 'firmengolf_event', [
		'labels'       => [
			'name'          => __( 'Firmenevents', 'firmengolf-events' ),
			'singular_name' => __( 'Firmenevent', 'firmengolf-events' ),
		],
		'public'        => true,
		'has_archive'   => true,
		'rewrite'       => [ 'slug' => 'firmenevents' ],
		'supports'      => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
		'show_in_rest'  => true,
		'menu_icon'     => 'dashicons-calendar-alt',
		'menu_position' => 20,
	] );

	register_post_type( 'firmengolf_partner', [
		'labels'       => [
			'name'          => __( 'Golfplatz Partner', 'firmengolf-events' ),
			'singular_name' => __( 'Golfplatz Partner', 'firmengolf-events' ),
		],
		'public'             => false,
		'publicly_queryable' => true,                          // public single page (gated in fge_block_non_public_partners)
		'exclude_from_search' => true,
		'show_ui'            => true,
		'has_archive'        => false,
		'rewrite'            => [ 'slug' => 'golfplatz', 'with_front' => false ],
		'supports'           => [ 'title', 'editor', 'thumbnail' ],
		'show_in_rest'       => true,
		'menu_icon'          => 'dashicons-location-alt',
		'menu_position'      => 21,
	] );

	register_post_type( 'firmengolf_request', [
		'labels'       => [
			'name'          => __( 'Event Anfragen', 'firmengolf-events' ),
			'singular_name' => __( 'Event Anfrage', 'firmengolf-events' ),
		],
		'public'        => false,
		'show_ui'       => true,
		'has_archive'   => false,
		'supports'      => [ 'editor' ],
		'show_in_rest'  => true,
		'menu_icon'     => 'dashicons-email-alt',
		'menu_position' => 22,
	] );
}

add_action( 'init', 'fge_register_post_types' );
