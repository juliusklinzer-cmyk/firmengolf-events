<?php
/**
 * Mehr-Parteien-Terminabstimmung (multi-party scheduling).
 *
 * Pro Anfrage stimmen bis zu drei Parteien (Platz · Pro · Gastro) über die
 * Wunschtermine (_fge_preferred_date_1..3) ab. Jede Partei bekommt einen
 * persönlichen Token-Link (/termin/<token>/) und sagt zu / ab / schlägt eine
 * Alternative vor. Sobald alle beteiligten Parteien reagiert haben, wird der
 * Anfrage-Status automatisch fortgeschrieben.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fge_sched_parties(): array {
	return [
		'platz'  => 'Golfplatz',
		'pro'    => 'Golf-Pro',
		'gastro' => 'Gastronomie',
	];
}

/** Which parties are involved for a request (from the *_requested flags; default all three). */
function fge_sched_involved_parties( int $request_id ): array {
	$map = [
		'platz'  => '_fge_golfplatz_requested',
		'pro'    => '_fge_golfpro_requested',
		'gastro' => '_fge_gastro_requested',
	];
	$involved = [];
	foreach ( $map as $party => $flag ) {
		if ( get_post_meta( $request_id, $flag, true ) == '1' ) {
			$involved[] = $party;
		}
	}
	return $involved ?: array_keys( fge_sched_parties() );
}

/** Generate per-party tokens for involved parties if missing. Returns party => token. */
function fge_sched_ensure_tokens( int $request_id ): array {
	$tokens = [];
	foreach ( fge_sched_involved_parties( $request_id ) as $party ) {
		$key = '_fge_sched_token_' . $party;
		$tok = (string) get_post_meta( $request_id, $key, true );
		if ( $tok === '' ) {
			$tok = substr( md5( $request_id . $party . wp_generate_uuid4() ), 0, 24 );
			update_post_meta( $request_id, $key, $tok );
			if ( (string) get_post_meta( $request_id, '_fge_sched_' . $party . '_status', true ) === '' ) {
				update_post_meta( $request_id, '_fge_sched_' . $party . '_status', 'pending' );
			}
		}
		$tokens[ $party ] = $tok;
	}
	return $tokens;
}

function fge_sched_link( string $token ): string {
	return home_url( '/termin/' . $token . '/' );
}

/** Resolve a token → [ 'request_id' => int, 'party' => string ] or null. */
function fge_sched_resolve_token( string $token ): ?array {
	if ( $token === '' ) {
		return null;
	}
	foreach ( array_keys( fge_sched_parties() ) as $party ) {
		$found = get_posts( [
			'post_type'      => 'firmengolf_request',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_fge_sched_token_' . $party,
			'meta_value'     => $token,
		] );
		if ( ! empty( $found ) ) {
			return [ 'request_id' => (int) $found[0], 'party' => $party ];
		}
	}
	return null;
}

/** Record a party's response and recompute the overall scheduling state. */
function fge_sched_record_response( int $request_id, string $party, string $status, string $alt = '', string $note = '' ): void {
	$allowed = [ 'zugesagt', 'abgesagt', 'alternative' ];
	if ( ! in_array( $status, $allowed, true ) ) {
		return;
	}
	update_post_meta( $request_id, '_fge_sched_' . $party . '_status', $status );
	update_post_meta( $request_id, '_fge_sched_' . $party . '_alt', sanitize_text_field( $alt ) );
	update_post_meta( $request_id, '_fge_sched_' . $party . '_note', sanitize_textarea_field( $note ) );
	update_post_meta( $request_id, '_fge_sched_' . $party . '_at', current_datetime()->format( 'Y-m-d H:i:s' ) );
	fge_sched_recompute_status( $request_id );
}

/** Compute the overall state from involved parties' responses. Returns the summary array. */
function fge_sched_state( int $request_id ): array {
	$involved = fge_sched_involved_parties( $request_id );
	$by_party = [];
	$counts   = [ 'pending' => 0, 'zugesagt' => 0, 'abgesagt' => 0, 'alternative' => 0 ];
	foreach ( $involved as $party ) {
		$st = (string) get_post_meta( $request_id, '_fge_sched_' . $party . '_status', true ) ?: 'pending';
		$by_party[ $party ] = $st;
		$counts[ $st ]      = ( $counts[ $st ] ?? 0 ) + 1;
	}
	$total = count( $involved );

	if ( $counts['pending'] > 0 ) {
		$overall = 'offen';
	} elseif ( $counts['zugesagt'] === $total ) {
		$overall = 'buchbar';
	} elseif ( $counts['abgesagt'] === $total ) {
		$overall = 'nicht_verfuegbar';
	} else {
		$overall = 'teilweise';
	}

	return [ 'involved' => $involved, 'by_party' => $by_party, 'counts' => $counts, 'overall' => $overall, 'all_responded' => $counts['pending'] === 0 ];
}

/** Map the overall scheduling state onto the canonical request status. */
function fge_sched_recompute_status( int $request_id ): void {
	$state = fge_sched_state( $request_id );
	$map   = [
		'buchbar'          => 'vollstaendig_verfuegbar',
		'nicht_verfuegbar' => 'nicht_verfuegbar',
		'teilweise'        => 'teilweise_verfuegbar',
		'offen'            => 'partner_angefragt',
	];
	if ( isset( $map[ $state['overall'] ] ) ) {
		update_post_meta( $request_id, '_fge_request_status', $map[ $state['overall'] ] );
	}
}

// ── Routing: /termin/<token>/ ────────────────────────────────────────────────
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

// ── Response handler (PRG from the termin landing form) ──────────────────────
add_action( 'init', static function () {
	if ( ( $_POST['fge_termin_action'] ?? '' ) !== 'respond' ) {
		return;
	}
	$token = sanitize_text_field( wp_unslash( $_POST['fge_termin_token'] ?? '' ) );
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_termin_nonce'] ?? '' ) ), 'fge_termin_' . $token ) ) {
		wp_die( 'Ungültige Sicherheitsprüfung.', '', [ 'response' => 403 ] );
	}
	$resolved = fge_sched_resolve_token( $token );
	if ( ! $resolved ) {
		wp_die( 'Link ungültig oder abgelaufen.', '', [ 'response' => 404 ] );
	}
	$status = sanitize_key( $_POST['fge_termin_status'] ?? '' );
	$alt    = sanitize_text_field( wp_unslash( $_POST['fge_termin_alt'] ?? '' ) );
	$note   = sanitize_textarea_field( wp_unslash( $_POST['fge_termin_note'] ?? '' ) );
	fge_sched_record_response( $resolved['request_id'], $resolved['party'], $status, $alt, $note );

	wp_safe_redirect( fge_sched_link( $token ) . '?done=1' );
	exit;
}, 5 );

// ── Admin meta box: scheduling matrix + party links ──────────────────────────
add_action( 'add_meta_boxes', static function () {
	add_meta_box( 'fge_mb_scheduling', 'Terminabstimmung (Platz · Pro · Gastro)', 'fge_render_mb_scheduling', 'firmengolf_request', 'normal', 'high' );
} );

function fge_render_mb_scheduling( WP_Post $post ): void {
	$tokens = fge_sched_ensure_tokens( $post->ID );
	$state  = fge_sched_state( $post->ID );
	$labels = fge_sched_parties();
	$badges = [
		'pending'     => [ 'Offen', '#C58A1D' ],
		'zugesagt'    => [ 'Zugesagt', '#2F6E45' ],
		'abgesagt'    => [ 'Abgesagt', '#B4332B' ],
		'alternative' => [ 'Alternative', '#3F6E8A' ],
	];
	$overall_label = [
		'offen'            => 'Wartet auf Rückmeldungen',
		'buchbar'          => '✓ Alle zugesagt — buchbar',
		'teilweise'        => 'Teilweise verfügbar',
		'nicht_verfuegbar' => 'Nicht verfügbar',
	];
	$dates = array_filter( [
		get_post_meta( $post->ID, '_fge_preferred_date_1', true ),
		get_post_meta( $post->ID, '_fge_preferred_date_2', true ),
		get_post_meta( $post->ID, '_fge_preferred_date_3', true ),
	] );
	?>
	<p style="margin:0 0 8px;"><strong>Wunschtermine:</strong> <?php echo $dates ? esc_html( implode( ' · ', $dates ) ) : '—'; ?></p>
	<p style="margin:0 0 12px;"><strong>Gesamtstatus:</strong> <?php echo esc_html( $overall_label[ $state['overall'] ] ?? $state['overall'] ); ?></p>
	<table class="widefat striped" style="margin-top:4px;">
		<thead><tr><th>Partei</th><th>Status</th><th>Alternative / Notiz</th><th>Persönlicher Link</th></tr></thead>
		<tbody>
		<?php foreach ( $state['involved'] as $party ) :
			$st  = $state['by_party'][ $party ];
			$b   = $badges[ $st ] ?? [ $st, '#666' ];
			$alt = get_post_meta( $post->ID, '_fge_sched_' . $party . '_alt', true );
			$nt  = get_post_meta( $post->ID, '_fge_sched_' . $party . '_note', true );
			$lnk = fge_sched_link( $tokens[ $party ] ?? '' );
		?>
			<tr>
				<td><strong><?php echo esc_html( $labels[ $party ] ); ?></strong></td>
				<td><span style="display:inline-block;padding:2px 8px;border-radius:10px;color:#fff;font-size:12px;background:<?php echo esc_attr( $b[1] ); ?>;"><?php echo esc_html( $b[0] ); ?></span></td>
				<td><?php echo esc_html( trim( $alt . ( $nt ? ' — ' . $nt : '' ) ) ?: '—' ); ?></td>
				<td><input type="text" readonly value="<?php echo esc_attr( $lnk ); ?>" style="width:100%;" onclick="this.select();"></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description" style="margin-top:8px;">Schick jeder Partei ihren Link. Sobald alle reagiert haben, springt der Anfrage-Status automatisch (z. B. auf „vollständig verfügbar").</p>
	<?php
}
