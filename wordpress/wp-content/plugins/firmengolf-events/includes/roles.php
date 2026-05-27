<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fge_register_roles() {
	$caps = [
		'read'         => true,
		'upload_files' => true,
	];

	if ( get_role( 'firmengolf_partner' ) ) {
		// Ensure existing role has all required capabilities.
		$role = get_role( 'firmengolf_partner' );
		foreach ( $caps as $cap => $grant ) {
			if ( ! isset( $role->capabilities[ $cap ] ) ) {
				$role->add_cap( $cap, $grant );
			}
		}
		return;
	}

	add_role( 'firmengolf_partner', __( 'Golfplatz Partner', 'firmengolf-events' ), $caps );
}

add_action( 'init', 'fge_register_roles' );
