<?php
/**
 * Control Center: Event bearbeiten mit der Maske aus dem Partnerportal
 * (Julius, 20.10.2026: „genau gleich wie wenn ein Partner die erstellt“, auch
 * für Platzhalter). Die Maske läuft als Website-Seite (?fge_cc_event_edit=ID)
 * im Rahmen der CC-Seite events/?bearbeiten=ID, wie die Prüfansicht.
 *
 * Unterschiede zum Portal: gespeichert wird im Namen des Event-Partners
 * (Platzhalter: ohne Partner, Preis als Einkauf netto), der Status bleibt,
 * und es geht keine Mail an den Partner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Website-Seite mit der Maske (wird im CC eingebettet). */
function fge_cc_event_editor_frame_url( int $eid, array $args = [] ): string {
	return add_query_arg( [ 'fge_cc_event_edit' => $eid, 'fge_cc_preview' => 1 ] + $args, home_url( '/' ) );
}

/** CC-Seite mit der eingebetteten Maske. */
function fge_cc_event_edit_url( int $eid ): string {
	return fge_cc_url( 'events', [ 'bearbeiten' => $eid ] );
}

/** Kontext für die Portal-Maske setzen: Partner des Events, 0 bei Platzhaltern. */
function fge_cc_event_editor_ctx( int $eid, array $extra = [] ): void {
	$GLOBALS['fge_cc_event_ctx'] = [
		'event'    => $eid,
		'partner'  => max( 0, (int) get_post_meta( $eid, '_fge_assigned_partner_id', true ) ),
		'back_url' => fge_cc_url( 'events' ),
	] + $extra;
}

add_action( 'template_redirect', static function (): void {
	if ( ! isset( $_GET['fge_cc_event_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$eid = absint( $_GET['fge_cc_event_edit'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! fge_cc_can() ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( fge_cc_event_edit_url( $eid ) ) );
			exit;
		}
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	if ( 'firmengolf_event' !== get_post_type( $eid ) ) {
		wp_die( 'Unbekanntes Event.', '', [ 'response' => 404 ] );
	}
	nocache_headers();
	fge_cc_event_editor_ctx( $eid, [ 'saved' => ! empty( $_GET['gespeichert'] ) ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$pid = (int) $GLOBALS['fge_cc_event_ctx']['partner'];

	add_action( 'wp_enqueue_scripts', static function () use ( $pid ): void {
		wp_enqueue_style( 'fge-portal', plugins_url( 'assets/css/fge-portal.css', FGE_DIR . 'firmengolf-events.php' ), [ 'fge-frontend' ], FGE_VERSION );
		if ( $pid > 0 && function_exists( 'fge_event_picker_enqueue' ) ) {
			fge_event_picker_enqueue( $pid );
		}
	}, 20 );
	add_filter( 'body_class', static fn( array $c ): array => array_merge( $c, [ 'fge-cc-event-edit' ] ) );

	[ 'errors' => $errors, 'data' => $err_data ] = fge_load_form_state( 'cc_err' );
	$saved = $errors ? $err_data : fge_portal_event_form_values( $eid );

	get_header();
	echo '<div class="fge-page fge-page--portal"><div class="fgpp"><div class="fp-page-wrap">';
	fge_portal_render_event_form( $pid, $saved, $errors, $eid );
	echo '</div></div></div>';
	get_footer();
	exit;
}, 0 );

// Speichern: Portal-Logik im Namen des Event-Partners, Status und Mails bleiben unberührt.
add_action( 'admin_post_fge_cc_event_full_save', static function (): void {
	$eid = absint( $_POST['fge_event_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! fge_cc_can() ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_cc_nonce'] ?? '' ) ), 'fge_cc_event_full_save_' . $eid ) ) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}
	if ( 'firmengolf_event' !== get_post_type( $eid ) ) {
		wp_die( 'Unbekanntes Event.', '', [ 'response' => 400 ] );
	}
	fge_cc_event_editor_ctx( $eid );
	$pid = (int) $GLOBALS['fge_cc_event_ctx']['partner'];

	$errors = fge_portal_validate_event_fields();
	if ( $errors ) {
		$token = wp_generate_uuid4();
		set_transient( 'fge_form_err_' . $token, [ 'errors' => $errors, 'data' => wp_unslash( $_POST ) ], 300 );
		wp_safe_redirect( fge_cc_event_editor_frame_url( $eid, [ 'cc_err' => $token ] ) );
		exit;
	}

	wp_update_post( [ 'ID' => $eid, 'post_title' => sanitize_text_field( wp_unslash( $_POST['fge_post_title'] ?? '' ) ) ] );
	fge_portal_save_event_meta( $eid );
	if ( isset( $_POST['fge_event_addons'] ) ) {
		update_post_meta( $eid, '_fge_event_addons', sanitize_textarea_field( wp_unslash( $_POST['fge_event_addons'] ) ) );
	}
	if ( $pid > 0 && function_exists( 'fge_event_save_images' ) ) {
		fge_event_save_images( $eid, $pid, wp_unslash( $_POST ) );
	}
	wp_safe_redirect( fge_cc_event_editor_frame_url( $eid, [ 'gespeichert' => 1 ] ) );
	exit;
} );

/** CC-Seite: Maske eingebettet, Zurück und „Ansehen“ oben wie in der Prüfansicht. */
function fge_cc_event_edit_page( int $eid ): void {
	$pid  = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
	echo '<div class="cc-backrow"><p class="cc-back"><a href="' . esc_url( fge_cc_url( 'events' ) ) . '">Zurück zu Events</a></p>';
	echo '<span class="cc-review-note">' . fge_cc_pill( $pid > 0 ? fge_cc_partner_name( $pid ) : 'Platzhalter', $pid > 0 ? 'neutral' : 'warn' ) // phpcs:ignore WordPress.Security.EscapeOutput
		. ' Gleiche Maske wie im Partnerportal. <a href="' . esc_url( fge_cc_public_link( $eid ) ) . '" target="_blank" rel="noopener">Eventseite ansehen</a></span></div>';
	echo '<div class="cc-editframe"><iframe src="' . esc_url( fge_cc_event_editor_frame_url( $eid ) ) . '" title="' . esc_attr( 'Event bearbeiten: ' . get_the_title( $eid ) ) . '"></iframe></div>';
}
