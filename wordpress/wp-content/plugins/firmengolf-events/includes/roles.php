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

// ── Partner gehören ins Portal, nicht ins WP-Backend ─────────────────────────

/** Hat der Nutzer (nur) die Partner-Rolle, ohne Admin zu sein? */
function fge_user_is_portal_partner( $user ): bool {
	return $user instanceof WP_User
		&& in_array( 'firmengolf_partner', (array) $user->roles, true )
		&& ! user_can( $user, 'manage_options' );
}

/** Nach dem Login (auch nach dem Passwort-Setzen): Partner direkt ins Portal. */
add_filter( 'login_redirect', 'fge_partner_login_redirect', 10, 3 );
function fge_partner_login_redirect( $redirect_to, $requested, $user ) {
	if ( fge_user_is_portal_partner( $user ) ) {
		return trailingslashit( home_url( '/partnerportal/' ) );
	}
	return $redirect_to;
}

/** wp-admin für Partner sperren und ins Portal umleiten (AJAX bleibt erlaubt). */
add_action( 'admin_init', 'fge_partner_block_wp_admin' );
function fge_partner_block_wp_admin(): void {
	if ( wp_doing_ajax() || ! is_user_logged_in() ) {
		return;
	}
	if ( fge_user_is_portal_partner( wp_get_current_user() ) ) {
		wp_redirect( trailingslashit( home_url( '/partnerportal/' ) ) );
		exit;
	}
}

/** Keine WP-Adminleiste für Partner im Frontend. */
add_filter( 'show_admin_bar', 'fge_partner_hide_admin_bar' );
function fge_partner_hide_admin_bar( $show ) {
	if ( is_user_logged_in() && fge_user_is_portal_partner( wp_get_current_user() ) ) {
		return false;
	}
	return $show;
}
