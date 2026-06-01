<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// INLINE CSS — Status-Badges in der Admin-Listenansicht
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'admin_head', function() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'edit-firmengolf_event' ) {
		return;
	}
	?>
	<style>
	.fge-badge { display:inline-block; font-size:11px; font-weight:600; padding:2px 9px; border-radius:100px; white-space:nowrap; line-height:1.6; }
	.fge-badge--green  { background:#E6F4EA; color:#1A6B38; }
	.fge-badge--orange { background:#FFF3E0; color:#9A4E00; }
	.fge-badge--red    { background:#FDECEA; color:#9B1C1C; }
	.fge-badge--gray   { background:#F1F1F0; color:#555; }
	</style>
	<?php
} );

// ══════════════════════════════════════════════════════════════════════════════
// FILTER-DROPDOWNS
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'restrict_manage_posts', 'fge_event_list_filters' );

function fge_event_list_filters( string $post_type ): void {
	if ( $post_type !== 'firmengolf_event' ) {
		return;
	}

	$cur_status   = sanitize_key( $_GET['fge_filter_status']   ?? '' );
	$cur_provider = sanitize_key( $_GET['fge_filter_provider'] ?? '' );
	$cur_partner  = absint( $_GET['fge_filter_partner']        ?? 0 );
	$cur_type     = sanitize_key( $_GET['fge_filter_type']     ?? '' );

	// Status
	$statuses = [
		''                      => 'Alle Status',
		'entwurf'               => 'Entwurf',
		'zur_pruefung'          => 'Zur Prüfung',
		'freigegeben'           => 'Freigegeben',
		'aenderung_in_pruefung' => 'Änderung in Prüfung',
		'pausiert'              => 'Pausiert',
		'abgelehnt'             => 'Abgelehnt',
	];
	echo '<select name="fge_filter_status">';
	foreach ( $statuses as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $cur_status, $val, false ), esc_html( $label ) );
	}
	echo '</select> ';

	// Anbieter Typ
	$providers = [
		''                  => 'Alle Anbieter',
		'firmengolf'        => 'Firmengolf',
		'golfplatz_partner' => 'Golfplatz Partner',
	];
	echo '<select name="fge_filter_provider">';
	foreach ( $providers as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $cur_provider, $val, false ), esc_html( $label ) );
	}
	echo '</select> ';

	// Golfplatz Partner
	$partners = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
	] );
	echo '<select name="fge_filter_partner">';
	echo '<option value="0">Alle Partner</option>';
	foreach ( $partners as $p ) {
		printf( '<option value="%d"%s>%s</option>', $p->ID, selected( $cur_partner, $p->ID, false ), esc_html( $p->post_title ) );
	}
	echo '</select> ';

	// Eventart
	$types = [ '' => 'Alle Eventarten' ] + fge_get_event_formats()['standard'];
	echo '<select name="fge_filter_type">';
	foreach ( $types as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $cur_type, $val, false ), esc_html( $label ) );
	}
	echo '</select>';
}

add_action( 'pre_get_posts', 'fge_apply_event_list_filters' );

function fge_apply_event_list_filters( WP_Query $q ): void {
	if ( ! is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( ( $q->get( 'post_type' ) ?: '' ) !== 'firmengolf_event' ) {
		return;
	}

	$meta_query = [];

	$status = sanitize_key( $_GET['fge_filter_status'] ?? '' );
	if ( $status !== '' ) {
		$meta_query[] = [ 'key' => '_fge_event_status', 'value' => $status ];
	}

	$provider = sanitize_key( $_GET['fge_filter_provider'] ?? '' );
	if ( $provider !== '' ) {
		$meta_query[] = [ 'key' => '_fge_provider_type', 'value' => $provider ];
	}

	$partner = absint( $_GET['fge_filter_partner'] ?? 0 );
	if ( $partner > 0 ) {
		$meta_query[] = [ 'key' => '_fge_assigned_partner_id', 'value' => $partner, 'type' => 'NUMERIC' ];
	}

	$type = sanitize_key( $_GET['fge_filter_type'] ?? '' );
	if ( $type !== '' ) {
		$meta_query[] = [ 'key' => '_fge_event_type', 'value' => $type ];
	}

	if ( ! empty( $meta_query ) ) {
		$meta_query['relation'] = 'AND';
		$q->set( 'meta_query', $meta_query );
	}
}

// ══════════════════════════════════════════════════════════════════════════════
// ROW ACTIONS
// ══════════════════════════════════════════════════════════════════════════════

add_filter( 'post_row_actions', 'fge_event_row_actions', 10, 2 );

function fge_event_row_actions( array $actions, WP_Post $post ): array {
	if ( $post->post_type !== 'firmengolf_event' ) {
		return $actions;
	}
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	$current = (string) get_post_meta( $post->ID, '_fge_event_status', true );

	$status_actions = [
		'freigeben'    => [ 'label' => 'Freigeben',   'status' => 'freigegeben',  'admin_only' => true  ],
		'zur_pruefung' => [ 'label' => 'Zur Prüfung', 'status' => 'zur_pruefung', 'admin_only' => false ],
		'pausieren'    => [ 'label' => 'Pausieren',   'status' => 'pausiert',     'admin_only' => false ],
		'ablehnen'     => [ 'label' => 'Ablehnen',    'status' => 'abgelehnt',    'admin_only' => false ],
	];

	foreach ( $status_actions as $key => $cfg ) {
		if ( $current === $cfg['status'] ) {
			continue;
		}
		if ( $cfg['admin_only'] && ! current_user_can( 'manage_options' ) ) {
			continue;
		}
		$url = wp_nonce_url(
			add_query_arg(
				[
					'action'         => 'fge_set_event_status',
					'post_id'        => $post->ID,
					'fge_new_status' => $cfg['status'],
				],
				admin_url( 'admin.php' )
			),
			'fge_set_status_' . $post->ID
		);
		$actions[ 'fge_' . $key ] = '<a href="' . esc_url( $url ) . '">' . esc_html( $cfg['label'] ) . '</a>';
	}

	return $actions;
}

// ══════════════════════════════════════════════════════════════════════════════
// STATUS-AKTION HANDLER
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'admin_action_fge_set_event_status', 'fge_handle_set_event_status' );

function fge_handle_set_event_status(): void {
	$post_id = absint( $_GET['post_id'] ?? 0 );
	if ( $post_id <= 0 ) {
		wp_die( 'Ungültige Event-ID.', '', [ 'response' => 400 ] );
	}

	check_admin_referer( 'fge_set_status_' . $post_id );

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	if ( get_post_type( $post_id ) !== 'firmengolf_event' ) {
		wp_die( 'Ungültiger Post-Typ.', '', [ 'response' => 400 ] );
	}

	$allowed    = [ 'freigegeben', 'zur_pruefung', 'pausiert', 'abgelehnt' ];
	$new_status = sanitize_key( $_GET['fge_new_status'] ?? '' );

	if ( ! in_array( $new_status, $allowed, true ) ) {
		wp_die( 'Ungültiger Status.', '', [ 'response' => 400 ] );
	}
	if ( $new_status === 'freigegeben' && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Keine Berechtigung zum Freigeben.', '', [ 'response' => 403 ] );
	}

	update_post_meta( $post_id, '_fge_event_status', $new_status );

	wp_redirect( esc_url_raw( add_query_arg(
		[ 'post_type' => 'firmengolf_event', 'fge_notice' => $new_status ],
		admin_url( 'edit.php' )
	) ), 303 );
	exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// ADMIN NOTICES
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'admin_notices', 'fge_event_status_notice' );

function fge_event_status_notice(): void {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'edit-firmengolf_event' ) {
		return;
	}

	$key = sanitize_key( $_GET['fge_notice'] ?? '' );
	$messages = [
		'freigegeben'  => 'Event wurde freigegeben.',
		'zur_pruefung' => 'Event wurde zur Prüfung gesetzt.',
		'pausiert'     => 'Event wurde pausiert.',
		'abgelehnt'    => 'Event wurde abgelehnt.',
	];

	if ( ! isset( $messages[ $key ] ) ) {
		return;
	}

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( $messages[ $key ] )
	);
}

// ══════════════════════════════════════════════════════════════════════════════
// PARTNER — FILTER, ROW ACTIONS, STATUS HANDLER
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'admin_head', function() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'edit-firmengolf_partner' ) {
		return;
	}
	echo '<style>
	.fge-badge { display:inline-block; font-size:11px; font-weight:600; padding:2px 9px; border-radius:100px; white-space:nowrap; line-height:1.6; }
	.fge-badge--green  { background:#E6F4EA; color:#1A6B38; }
	.fge-badge--orange { background:#FFF3E0; color:#9A4E00; }
	.fge-badge--red    { background:#FDECEA; color:#9B1C1C; }
	.fge-badge--gray   { background:#F1F1F0; color:#555; }
	</style>';
} );

add_action( 'restrict_manage_posts', 'fge_partner_list_filters' );

function fge_partner_list_filters( string $post_type ): void {
	if ( $post_type !== 'firmengolf_partner' ) {
		return;
	}
	$cur = sanitize_key( $_GET['fge_filter_partner_status'] ?? '' );
	$statuses = [
		''            => 'Alle Status',
		'in_pruefung' => 'In Prüfung',
		'aktiv'       => 'Aktiv',
		'pausiert'    => 'Pausiert',
		'abgelehnt'   => 'Abgelehnt',
	];
	echo '<select name="fge_filter_partner_status">';
	foreach ( $statuses as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $cur, $val, false ), esc_html( $label ) );
	}
	echo '</select>';
}

add_action( 'pre_get_posts', 'fge_apply_partner_list_filters' );

function fge_apply_partner_list_filters( WP_Query $q ): void {
	if ( ! is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( ( $q->get( 'post_type' ) ?: '' ) !== 'firmengolf_partner' ) {
		return;
	}
	$status = sanitize_key( $_GET['fge_filter_partner_status'] ?? '' );
	if ( $status !== '' ) {
		$q->set( 'meta_query', [ [ 'key' => '_fge_partner_status', 'value' => $status ] ] );
	}
}

add_filter( 'post_row_actions', 'fge_partner_row_actions', 10, 2 );

function fge_partner_row_actions( array $actions, WP_Post $post ): array {
	if ( $post->post_type !== 'firmengolf_partner' ) {
		return $actions;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return $actions;
	}

	$current = (string) get_post_meta( $post->ID, '_fge_partner_status', true );

	$status_actions = [
		'freigeben'  => [ 'label' => 'Freigeben',  'status' => 'aktiv'       ],
		'pausieren'  => [ 'label' => 'Pausieren',  'status' => 'pausiert'    ],
		'ablehnen'   => [ 'label' => 'Ablehnen',   'status' => 'abgelehnt'   ],
		'in_pruefung' => [ 'label' => 'Zur Prüfung', 'status' => 'in_pruefung' ],
	];

	foreach ( $status_actions as $key => $cfg ) {
		if ( $current === $cfg['status'] ) {
			continue;
		}
		$url = wp_nonce_url(
			add_query_arg(
				[
					'action'              => 'fge_set_partner_status',
					'post_id'             => $post->ID,
					'fge_new_partner_status' => $cfg['status'],
				],
				admin_url( 'admin.php' )
			),
			'fge_set_partner_status_' . $post->ID
		);
		$actions[ 'fge_partner_' . $key ] = '<a href="' . esc_url( $url ) . '">' . esc_html( $cfg['label'] ) . '</a>';
	}

	return $actions;
}

add_action( 'admin_action_fge_set_partner_status', 'fge_handle_set_partner_status' );

function fge_handle_set_partner_status(): void {
	$post_id = absint( $_GET['post_id'] ?? 0 );
	if ( $post_id <= 0 ) {
		wp_die( 'Ungültige Partner-ID.', '', [ 'response' => 400 ] );
	}

	check_admin_referer( 'fge_set_partner_status_' . $post_id );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	if ( get_post_type( $post_id ) !== 'firmengolf_partner' ) {
		wp_die( 'Ungültiger Post-Typ.', '', [ 'response' => 400 ] );
	}

	$allowed    = [ 'aktiv', 'in_pruefung', 'pausiert', 'abgelehnt' ];
	$new_status = sanitize_key( $_GET['fge_new_partner_status'] ?? '' );

	if ( ! in_array( $new_status, $allowed, true ) ) {
		wp_die( 'Ungültiger Status.', '', [ 'response' => 400 ] );
	}

	update_post_meta( $post_id, '_fge_partner_status', $new_status );

	// When activating a partner, ensure portal is enabled and standard permissions are set.
	if ( $new_status === 'aktiv' ) {
		update_post_meta( $post_id, '_fge_partner_portal_enabled', 1 );
		if ( get_post_meta( $post_id, '_fge_can_create_events', true ) === '' ) {
			update_post_meta( $post_id, '_fge_can_create_events', 1 );
			update_post_meta( $post_id, '_fge_can_edit_events', 1 );
			update_post_meta( $post_id, '_fge_can_view_requests', 1 );
			update_post_meta( $post_id, '_fge_can_view_statistics', 1 );
		}
	}

	wp_redirect( esc_url_raw( add_query_arg(
		[ 'post_type' => 'firmengolf_partner', 'fge_partner_notice' => $new_status ],
		admin_url( 'edit.php' )
	) ), 303 );
	exit;
}

add_action( 'admin_notices', 'fge_partner_status_notice' );

function fge_partner_status_notice(): void {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'edit-firmengolf_partner' ) {
		return;
	}

	$key = sanitize_key( $_GET['fge_partner_notice'] ?? '' );
	$messages = [
		'aktiv'       => 'Partner wurde freigegeben.',
		'in_pruefung' => 'Partner wurde zur Prüfung gesetzt.',
		'pausiert'    => 'Partner wurde pausiert.',
		'abgelehnt'   => 'Partner wurde abgelehnt.',
	];

	if ( ! isset( $messages[ $key ] ) ) {
		return;
	}

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( $messages[ $key ] )
	);
}
