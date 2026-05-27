<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fge_register_roles() {
	if ( get_role( 'firmengolf_partner' ) ) {
		return;
	}

	add_role(
		'firmengolf_partner',
		__( 'Golfplatz Partner', 'firmengolf-events' ),
		[ 'read' => true ]
	);
}

add_action( 'init', 'fge_register_roles' );
