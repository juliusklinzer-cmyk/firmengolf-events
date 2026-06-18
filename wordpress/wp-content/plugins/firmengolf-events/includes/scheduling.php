<?php
/**
 * Termin-Routing + Admin-Sicht + Eskalation/Übernehmen.
 *
 * Das Datenmodell der Mehr-Parteien-Abstimmung liegt in request-responses.php
 * (Tabelle fge_request_responses, Responder = vote-Kontakte aus fge_partner_contacts).
 * Diese Datei hält nur noch: die /termin/<token>/-Route (genutzt vom Landing-Template),
 * die Admin-Matrix der Anfrage und die Firmengolf-„Übernehmen"-Funktion.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Routing: /termin/<contact-token>/?req=<id> ───────────────────────────────
add_action( 'init', static function () {
	add_rewrite_rule( '^termin/([^/]+)/?$', 'index.php?fge_termin=$matches[1]', 'top' );
} );
add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_termin';
	return $vars;
} );
add_filter( 'template_include', static function ( $template ) {
	if ( get_query_var( 'fge_termin' ) ) {
		$t = locate_template( 'template-termin.php' );
		return $t ?: $template;
	}
	return $template;
} );
// Self-heal rewrite rule.
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^termin/([^/]+)/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );

// ── Eskalation / Übernehmen ──────────────────────────────────────────────────

/** Response deadline (timestamp) — created + N days (default 5, filterable). */
function fge_request_response_deadline( int $request_id ): int {
	$created = strtotime( (string) get_post_field( 'post_date', $request_id ) ?: 'now' );
	$days    = (int) apply_filters( 'fge_request_response_days', 5 );
	return $created + $days * DAY_IN_SECONDS;
}

function fge_request_is_taken_over( int $request_id ): bool {
	return '1' === (string) get_post_meta( $request_id, '_fge_taken_over', true );
}

/** Overdue = responders exist, not all responded, not confirmed/taken-over, past deadline. */
function fge_request_is_overdue( int $request_id ): bool {
	if ( fge_request_is_taken_over( $request_id ) ) {
		return false;
	}
	if ( function_exists( 'fge_rr_final_index' ) && fge_rr_final_index( $request_id ) > 0 ) {
		return false;
	}
	if ( ! function_exists( 'fge_rr_matrix' ) ) {
		return false;
	}
	$m = fge_rr_matrix( $request_id );
	if ( empty( $m['responders'] ) || ! empty( $m['all_responded'] ) ) {
		return false;
	}
	return time() > fge_request_response_deadline( $request_id );
}

// Admin action: Firmengolf takes over (or releases) the coordination.
add_action( 'admin_post_fge_take_over', 'fge_handle_take_over' );
function fge_handle_take_over(): void {
	$req = absint( $_POST['request_id'] ?? 0 );
	if ( $req <= 0 || ! current_user_can( 'edit_post', $req ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_take_over_' . $req );
	$on = ( 'on' === ( $_POST['mode'] ?? '' ) );
	update_post_meta( $req, '_fge_taken_over', $on ? 1 : 0 );
	if ( $on ) {
		update_post_meta( $req, '_fge_request_status', 'in_uebernahme' );
	}
	wp_safe_redirect( get_edit_post_link( $req, 'raw' ) );
	exit;
}

// ── Admin meta box: scheduling matrix (vote-contacts × wish dates) ────────────
add_action( 'add_meta_boxes', static function () {
	add_meta_box( 'fge_mb_scheduling', 'Terminabstimmung', 'fge_render_mb_scheduling', 'firmengolf_request', 'normal', 'high' );
} );

function fge_render_mb_scheduling( WP_Post $post ): void {
	$req   = $post->ID;
	$wish  = function_exists( 'fge_rr_wish_dates' ) ? fge_rr_wish_dates( $req ) : [];
	$m     = function_exists( 'fge_rr_matrix' ) ? fge_rr_matrix( $req ) : [ 'dates' => [], 'responders' => [], 'all_responded' => false, 'final_index' => null ];
	$resp  = $m['responders'];
	$final = function_exists( 'fge_rr_final_index' ) ? fge_rr_final_index( $req ) : 0;
	$total = count( $resp );

	if ( empty( $resp ) ) {
		echo '<p style="margin:0;color:#646970;">Für die Terminabstimmung sind keine Ansprechpartner mit „Terminabstimmung"-Recht hinterlegt. Lege sie beim zugewiesenen Partner an (Tab „Ansprechpartner" im Portal oder Partner-Profil).</p>';
		return;
	}

	$overdue    = fge_request_is_overdue( $req );
	$taken_over = fge_request_is_taken_over( $req );
	$badges     = [ 'confirmed' => [ 'Zusage', '#2F6E45' ], 'declined' => [ 'Absage', '#B4332B' ], 'pending' => [ 'Offen', '#C58A1D' ] ];

	if ( $taken_over ) {
		echo '<p style="margin:0 0 10px;padding:8px 12px;background:#EAF2EC;border-radius:6px;"><strong>Von Firmengolf übernommen.</strong> Koordination läuft direkt mit der Firma.</p>';
	} elseif ( $overdue ) {
		echo '<p style="margin:0 0 10px;padding:8px 12px;background:#FBEFD6;border-radius:6px;"><strong>Überfällig</strong> — Reaktionsfrist überschritten, noch nicht alle haben reagiert.</p>';
	}
	?>
	<p style="margin:0 0 12px;"><strong>Status:</strong>
		<?php
		if ( $final > 0 ) {
			echo 'Termin bestätigt: <strong>' . esc_html( $wish[ $final ] ?? ('#' . $final) ) . '</strong>';
		} elseif ( $m['all_responded'] ) {
			echo 'Alle haben reagiert — Termin kann bestätigt werden.';
		} else {
			echo 'Wartet auf Rückmeldungen.';
		}
		?>
	</p>

	<?php if ( ! empty( $wish ) ) : ?>
	<table class="widefat striped" style="margin-bottom:14px;">
		<thead><tr><th>Wunschtermin</th><th>Verfügbar</th><th>Antworten</th></tr></thead>
		<tbody>
		<?php foreach ( $wish as $idx => $label ) :
			$d = $m['dates'][ $idx ] ?? [ 'confirmed' => 0, 'responders' => [] ]; ?>
			<tr>
				<td><strong><?php echo esc_html( $label ); ?></strong><?php echo (int) $final === (int) $idx ? ' ✓' : ''; ?></td>
				<td><?php echo (int) $d['confirmed']; ?>/<?php echo (int) $total; ?></td>
				<td>
					<?php foreach ( $resp as $c ) :
						$st = $d['responders'][ (int) $c['id'] ]['response'] ?? 'pending';
						$b  = $badges[ $st ]; ?>
						<span title="<?php echo esc_attr( $c['name'] ); ?>" style="display:inline-block;margin:1px 3px 1px 0;padding:1px 7px;border-radius:9px;color:#fff;font-size:11px;background:<?php echo esc_attr( $b[1] ); ?>;"><?php echo esc_html( ( $c['name'] ?: $c['email'] ) . ': ' . $b[0] ); ?></span>
					<?php endforeach; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<p style="margin:0 0 6px;"><strong>Persönliche Links</strong> (zum Nachsenden):</p>
	<table class="widefat striped" style="margin-bottom:14px;">
		<tbody>
		<?php foreach ( $resp as $c ) :
			$responded = fge_rr_contact_done( $req, (int) $c['id'] );
			$link      = fge_termin_contact_link( $req, $c ); ?>
			<tr>
				<td style="width:38%;"><strong><?php echo esc_html( $c['name'] ?: $c['email'] ); ?></strong><br><span style="color:#646970;font-size:12px;"><?php echo esc_html( $c['role'] ?: 'Ansprechpartner' ); ?> · <?php echo $responded ? 'hat reagiert' : 'ausstehend'; ?></span></td>
				<td><input type="text" readonly value="<?php echo esc_attr( $link ); ?>" style="width:100%;" onclick="this.select();"></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
		<?php wp_nonce_field( 'fge_take_over_' . $req ); ?>
		<input type="hidden" name="action" value="fge_take_over">
		<input type="hidden" name="request_id" value="<?php echo (int) $req; ?>">
		<?php if ( $taken_over ) : ?>
			<input type="hidden" name="mode" value="off">
			<button type="submit" class="button">Übernahme zurücknehmen</button>
		<?php else : ?>
			<input type="hidden" name="mode" value="on">
			<button type="submit" class="button button-primary">Koordination übernehmen</button>
			<span class="description" style="margin-left:8px;">Firmengolf koordiniert dann direkt mit der Firma.</span>
		<?php endif; ?>
	</form>
	<?php
}
