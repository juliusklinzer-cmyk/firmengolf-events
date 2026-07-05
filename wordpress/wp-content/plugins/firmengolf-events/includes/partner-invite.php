<?php
/**
 * Partner-Einladung: Übergabe vorbereiteter Partner-Profile per Magic-Link.
 *
 * Julius erzeugt im Admin (Partner-Liste → „Einladungslink") einen persönlichen
 * Link (/einladung/<token>/). Der Club legt dort nur Zugangsdaten an, wird mit
 * seinem vorbefüllten Profil verknüpft und landet eingeloggt im Portal — das
 * Willkommens-Panel (partner-portal.php) führt durch die ersten Schritte.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Token + URL ───────────────────────────────────────────────────────────────

/** Einladungs-Token des Partners (lazy erzeugt, entwertet beim Einlösen). */
function fge_invite_token( int $partner_id ): string {
	$t = (string) get_post_meta( $partner_id, '_fge_invite_token', true );
	if ( '' === $t ) {
		$t = bin2hex( random_bytes( 20 ) );
		update_post_meta( $partner_id, '_fge_invite_token', $t );
	}
	return $t;
}

function fge_invite_url( int $partner_id ): string {
	// Club-Slug in der URL: der Empfänger sieht seinen eigenen Namen im Link (Vertrauen);
	// maßgeblich fürs Auflösen ist allein der Token.
	return home_url( '/einladung/' . get_post_field( 'post_name', $partner_id ) . '/' . fge_invite_token( $partner_id ) . '/' );
}

function fge_invite_partner_by_token( string $token ): int {
	if ( ! preg_match( '/^[a-f0-9]{40}$/', $token ) ) {
		return 0;
	}
	$q = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => '_fge_invite_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] );
	return $q ? (int) $q[0] : 0;
}

// ── Routing: /einladung/<token>/ ──────────────────────────────────────────────

add_action( 'init', static function () {
	add_rewrite_rule( '^einladung/[^/]+/([a-f0-9]{40})/?$', 'index.php?fge_einladung=$matches[1]', 'top' );
	add_rewrite_rule( '^einladung/([a-f0-9]{40})/?$', 'index.php?fge_einladung=$matches[1]', 'top' );
} );
add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_einladung';
	return $vars;
} );
add_filter( 'template_include', static function ( $template ) {
	if ( get_query_var( 'fge_einladung' ) ) {
		$t = locate_template( 'template-einladung.php' );
		return $t ?: $template;
	}
	return $template;
} );
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^einladung/[^/]+/([a-f0-9]{40})/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );

// ── Annahme: Konto anlegen + verknüpfen + einloggen ───────────────────────────

add_action( 'init', 'fge_invite_handle_accept', 8 );
function fge_invite_handle_accept(): void {
	if ( ( $_POST['fge_action'] ?? '' ) !== 'invite_accept' ) {
		return;
	}
	$token = sanitize_text_field( wp_unslash( $_POST['fge_invite_token'] ?? '' ) );
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_invite_nonce'] ?? '' ) ), 'fge_invite_' . $token ) ) {
		wp_die( 'Ungültige Sicherheitsüberprüfung.', '', [ 'response' => 403 ] );
	}
	$partner_id = fge_invite_partner_by_token( $token );
	$back       = $partner_id > 0 ? fge_invite_url( $partner_id ) : home_url( '/einladung/' . rawurlencode( $token ) . '/' );
	if ( $partner_id <= 0 ) {
		wp_die( 'Dieser Einladungslink ist ungültig oder wurde bereits verwendet.', '', [ 'response' => 404 ] );
	}
	if ( (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true ) > 0 ) {
		wp_die( 'Dieses Partnerprofil ist bereits mit einem Konto verknüpft. Melde dich einfach im Portal an — oder ruf uns an, wenn etwas nicht stimmt.', '', [ 'response' => 409 ] );
	}
	if ( function_exists( 'fge_form_rate_limited' ) && fge_form_rate_limited( 5, 600, 'einladung' ) ) {
		wp_safe_redirect( add_query_arg( 'fehler', 'rate', $back ) );
		exit;
	}

	$first = sanitize_text_field( wp_unslash( $_POST['fge_first'] ?? '' ) );
	$last  = sanitize_text_field( wp_unslash( $_POST['fge_last'] ?? '' ) );
	$email = sanitize_email( wp_unslash( $_POST['fge_email'] ?? '' ) );
	$pass  = (string) wp_unslash( $_POST['fge_pass'] ?? '' );

	if ( '' === $first || ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'fehler', 'felder', $back ) );
		exit;
	}
	if ( strlen( $pass ) < 8 ) {
		wp_safe_redirect( add_query_arg( 'fehler', 'passwort', $back ) );
		exit;
	}
	// SICHERHEIT (wie Onboarding): bestehende Konten werden NIE automatisch
	// verknüpft — sonst wäre Konto-Übernahme per fremder E-Mail möglich.
	if ( get_user_by( 'email', $email ) ) {
		wp_safe_redirect( add_query_arg( 'fehler', 'email_vergeben', $back ) );
		exit;
	}

	$user_id = wp_create_user( $email, $pass, $email );
	if ( is_wp_error( $user_id ) ) {
		wp_safe_redirect( add_query_arg( 'fehler', 'felder', $back ) );
		exit;
	}
	wp_update_user( [
		'ID'           => $user_id,
		'first_name'   => $first,
		'last_name'    => $last,
		'display_name' => trim( $first . ' ' . $last ),
		'role'         => 'firmengolf_partner',
	] );

	update_post_meta( $partner_id, '_fge_assigned_wp_user_id', $user_id );
	update_post_meta( $partner_id, '_fge_partner_portal_enabled', 1 );
	// Recherchierte Kontaktdaten nur ergänzen, nicht überschreiben.
	if ( '' === (string) get_post_meta( $partner_id, '_fge_main_contact_name', true ) ) {
		update_post_meta( $partner_id, '_fge_main_contact_name', trim( $first . ' ' . $last ) );
	}
	if ( '' === (string) get_post_meta( $partner_id, '_fge_main_contact_email', true ) ) {
		update_post_meta( $partner_id, '_fge_main_contact_email', $email );
	}

	// Token entwerten, Willkommens-Panel fürs erste Login vormerken.
	delete_post_meta( $partner_id, '_fge_invite_token' );
	update_user_meta( $user_id, 'fge_welcome_pending', 1 );

	// Intern Bescheid geben — Julius sieht, wer die Übergabe angenommen hat.
	$to      = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$subject = 'Partner-Zugang aktiviert: ' . get_the_title( $partner_id );
	$content = '<p style="margin:0 0 16px;"><strong>' . esc_html( trim( $first . ' ' . $last ) ) . '</strong> (' . esc_html( $email ) . ') hat den Einladungslink für <strong>' . esc_html( get_the_title( $partner_id ) ) . '</strong> eingelöst und ist jetzt im Portal.</p>';
	if ( function_exists( 'fge_email_wrap' ) ) {
		wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	}

	wp_set_auth_cookie( $user_id, true );
	wp_safe_redirect( function_exists( 'fge_portal_page_url' ) ? fge_portal_page_url() : home_url( '/partnerportal/' ) );
	exit;
}

// ── Admin: „Einladungslink"-Aktion in der Partner-Liste ───────────────────────

add_filter( 'post_row_actions', static function ( array $actions, WP_Post $post ): array {
	if ( 'firmengolf_partner' === $post->post_type && current_user_can( 'manage_options' ) ) {
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=fge_partner_invite&post_id=' . $post->ID ),
			'fge_partner_invite_' . $post->ID
		);
		$actions['fge_invite'] = '<a href="' . esc_url( $url ) . '">Einladungslink</a>';
	}
	return $actions;
}, 10, 2 );

add_action( 'admin_post_fge_partner_invite', static function (): void {
	$partner_id = absint( $_GET['post_id'] ?? 0 );
	if ( $partner_id <= 0 || ! current_user_can( 'manage_options' ) || get_post_type( $partner_id ) !== 'firmengolf_partner' ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_partner_invite_' . $partner_id );

	$linked = (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true );
	$url    = fge_invite_url( $partner_id );
	$back   = admin_url( 'edit.php?post_type=firmengolf_partner' );
	?><!doctype html><html lang="de"><head><meta charset="utf-8"><title>Einladungslink</title>
	<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f0f0f1;margin:0;padding:60px 20px;}
	.card{max-width:640px;margin:0 auto;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:28px 32px;}
	h1{font-size:20px;margin:0 0 6px;}p{color:#50575e;font-size:14px;line-height:1.5;}
	input{width:100%;font:13px/1.4 monospace;padding:10px 12px;border:1px solid #c3c4c7;border-radius:4px;box-sizing:border-box;margin:12px 0;}
	button,a.btn{display:inline-block;background:#2271b1;color:#fff;border:0;border-radius:4px;padding:9px 16px;font-size:13px;cursor:pointer;text-decoration:none;margin-right:8px;}
	a.back{color:#2271b1;font-size:13px;}
	.warn{background:#fcf9e8;border:1px solid #dba617;border-radius:4px;padding:10px 14px;font-size:13px;color:#646970;}</style></head><body>
	<div class="card">
		<h1>Einladungslink: <?php echo esc_html( get_the_title( $partner_id ) ); ?></h1>
		<?php if ( $linked > 0 ) : ?>
			<div class="warn">⚠ Dieser Partner ist bereits mit einem Konto verknüpft (User #<?php echo (int) $linked; ?>). Der Link würde beim Einlösen fehlschlagen — nur nötig, wenn du die Verknüpfung vorher löst.</div>
		<?php else : ?>
			<p>Diesen persönlichen Link in deine Start-Mail an den Club kopieren. Der Club legt damit nur noch Zugangsdaten fest und landet direkt in seinem vorbereiteten Portal. Der Link ist einmalig gültig.</p>
		<?php endif; ?>
		<input type="text" value="<?php echo esc_attr( $url ); ?>" id="fge-inv" readonly onclick="this.select()">
		<button onclick="document.getElementById('fge-inv').select();document.execCommand('copy');this.textContent='Kopiert ✓';">Link kopieren</button>
		<a class="back" href="<?php echo esc_url( $back ); ?>">← Zurück zur Partner-Liste</a>
	</div></body></html><?php
	exit;
} );
