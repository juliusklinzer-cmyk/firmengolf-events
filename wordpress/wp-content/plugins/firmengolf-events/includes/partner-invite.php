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

/**
 * Passwort aus Schritt 1 verschlüsselt zwischenparken (Transient, 15 min), damit
 * es im Code-Schritt NICHT erneut eingetippt werden muss (Funnel-Audit 2026-07-12:
 * Doppel-Eingabe wirkte wie ein Bug und kostete Conversion an der heißesten Stelle).
 * Verschlüsselung mit Schlüssel aus wp_salt(): ein DB-Leak allein reicht nicht.
 */
function fge_invite_seal( string $plain ): string {
	if ( '' === $plain ) {
		return '';
	}
	$key   = substr( hash( 'sha256', wp_salt( 'auth' ), true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	return base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) );
}

function fge_invite_unseal( string $sealed ): string {
	$raw = base64_decode( $sealed, true );
	if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
		return '';
	}
	$key   = substr( hash( 'sha256', wp_salt( 'auth' ), true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
	return false === $plain ? '' : $plain;
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
		wp_die( 'Dieses Partnerprofil ist bereits mit einem Konto verknüpft. Melde dich einfach im Portal an oder ruf uns an, wenn etwas nicht stimmt.', '', [ 'response' => 409 ] );
	}
	// Limit 15 statt 5 (Kern-Audit H2): der Verifizierungs-Flow braucht mehrere POSTs
	// (Code senden, ggf. erneut senden, Tippfehler) — die Code-Prüfung hat eigene
	// Limits (5 Versuche, 60s-Cooldown, 8 Sends/h), das IP-Gate ist nur der Notanker.
	if ( function_exists( 'fge_form_rate_limited' ) && empty( $_POST['fge_resend'] ) && fge_form_rate_limited( 15, 600, 'einladung' ) ) {
		wp_safe_redirect( add_query_arg( 'fehler', 'rate', $back ) );
		exit;
	}

	$first = sanitize_text_field( wp_unslash( $_POST['fge_first'] ?? '' ) );
	$last  = sanitize_text_field( wp_unslash( $_POST['fge_last'] ?? '' ) );
	$email = sanitize_email( wp_unslash( $_POST['fge_email'] ?? '' ) );
	$pass  = (string) wp_unslash( $_POST['fge_pass'] ?? '' );
	$ev_ctx = 'invite_' . $token;

	// Geparktes Passwort aus Schritt 1 wiederverwenden — der Code-Schritt hat kein
	// Passwortfeld mehr. Frische Eingabe (Schritt 1) hat immer Vorrang.
	$pending_prev = (array) get_transient( 'fge_invite_pending_' . $token );
	if ( '' === $pass ) {
		$pass = fge_invite_unseal( (string) ( $pending_prev['pass'] ?? '' ) );
	}

	// „Code erneut senden": ohne Passwort-Zwang, nur E-Mail nötig (Julius, 2026-07-06).
	if ( ! empty( $_POST['fge_resend'] ) ) {
		if ( is_email( $email ) && ! fge_ev_is_verified( $email, $ev_ctx ) ) {
			set_transient( 'fge_invite_pending_' . $token, [ 'first' => $first, 'last' => $last, 'email' => $email, 'pass' => fge_invite_seal( $pass ) ], 15 * MINUTE_IN_SECONDS );
			$send = fge_ev_send_code( $email, $ev_ctx );
			wp_safe_redirect( add_query_arg( is_wp_error( $send ) ? [ 'verify' => 1, 'evfehler' => $send->get_error_code() ] : [ 'verify' => 1 ], $back ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'verify', 1, $back ) );
		exit;
	}

	if ( '' === $first || ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'fehler', 'felder', $back ) );
		exit;
	}
	if ( strlen( $pass ) < 8 ) {
		// Im Code-Schritt heißt ein fehlendes Passwort: das Transient (15 min) ist
		// abgelaufen → zurück zu Schritt 1 mit eigener Meldung, nicht „zu kurz".
		$code = isset( $_POST['fge_code'] ) ? 'abgelaufen' : 'passwort';
		wp_safe_redirect( add_query_arg( 'fehler', $code, $back ) );
		exit;
	}
	// SICHERHEIT (wie Onboarding): bestehende Konten werden NIE automatisch
	// verknüpft — sonst wäre Konto-Übernahme per fremder E-Mail möglich.
	if ( get_user_by( 'email', $email ) ) {
		wp_safe_redirect( add_query_arg( 'fehler', 'email_vergeben', $back ) );
		exit;
	}

	// E-Mail-Verifizierung (Julius, 2026-07-06): die selbst getippte Login-Adresse
	// muss per 6-stelligem Code bestätigt sein, BEVOR das Konto entsteht —
	// ein Tippfehler würde den Partner sonst dauerhaft von allen Mails abschneiden.
	if ( ! fge_ev_is_verified( $email, $ev_ctx ) ) {
		set_transient( 'fge_invite_pending_' . $token, [ 'first' => $first, 'last' => $last, 'email' => $email, 'pass' => fge_invite_seal( $pass ) ], 15 * MINUTE_IN_SECONDS );
		$code = sanitize_text_field( wp_unslash( $_POST['fge_code'] ?? '' ) );
		if ( '' === $code ) {
			$send = fge_ev_send_code( $email, $ev_ctx );
			wp_safe_redirect( add_query_arg( is_wp_error( $send ) ? [ 'verify' => 1, 'evfehler' => $send->get_error_code() ] : [ 'verify' => 1 ], $back ) );
			exit;
		}
		$chk = fge_ev_check_code( $email, $ev_ctx, $code );
		if ( is_wp_error( $chk ) ) {
			wp_safe_redirect( add_query_arg( [ 'verify' => 1, 'evfehler' => $chk->get_error_code() ], $back ) );
			exit;
		}
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
	// Verifizierungs-Status + zwischengeparkte Formulardaten abräumen.
	fge_ev_forget( $email, $ev_ctx );
	delete_transient( 'fge_invite_pending_' . $token );

	// Intern Bescheid geben — Julius sieht, wer die Übergabe angenommen hat.
	$to      = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$subject = 'Partner-Zugang aktiviert: ' . get_the_title( $partner_id );
	$content = '<p style="margin:0 0 16px;"><strong>' . esc_html( trim( $first . ' ' . $last ) ) . '</strong> (' . esc_html( $email ) . ') hat den Einladungslink für <strong>' . esc_html( get_the_title( $partner_id ) ) . '</strong> eingelöst und ist jetzt im Portal.</p>';
	if ( function_exists( 'fge_email_wrap' ) ) {
		wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	}
	// Schriftliche Spur für den Club: „dein Zugang existiert, so kommst du wieder
	// rein" — bisher bekam nur Firmengolf intern Bescheid (Funnel-Audit 2026-07-12).
	if ( function_exists( 'fge_send_invite_access_email' ) ) {
		fge_send_invite_access_email( $user_id, $partner_id );
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
	// Kein Token für bereits verknüpfte Partner erzeugen/persistieren (Kern-Audit N6).
	$url     = $linked > 0 ? '' : fge_invite_url( $partner_id );
	$back    = admin_url( 'edit.php?post_type=firmengolf_partner' );
	$to      = (string) get_post_meta( $partner_id, '_fge_invite_sent_to', true )
		?: (string) get_post_meta( $partner_id, '_fge_main_contact_email', true );
	$sent_at = (int) get_post_meta( $partner_id, '_fge_invite_sent_at', true );
	$remind  = '1' === (string) get_post_meta( $partner_id, '_fge_invite_reminded', true );
	$days    = (int) apply_filters( 'fge_invite_reminder_days', 5 );
	$notice  = sanitize_key( $_GET['fge_sent'] ?? '' );
	?><!doctype html><html lang="de"><head><meta charset="utf-8"><title>Partner-Einladung</title>
	<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f0f0f1;margin:0;padding:60px 20px;}
	.card{max-width:640px;margin:0 auto;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:28px 32px;}
	h1{font-size:20px;margin:0 0 6px;}h2{font-size:14px;margin:26px 0 4px;}p{color:#50575e;font-size:14px;line-height:1.5;}
	input{width:100%;font:13px/1.4 monospace;padding:10px 12px;border:1px solid #c3c4c7;border-radius:4px;box-sizing:border-box;margin:12px 0;}
	input.mail{font-family:inherit;font-size:14px;}
	button,a.btn{display:inline-block;background:#2271b1;color:#fff;border:0;border-radius:4px;padding:9px 16px;font-size:13px;cursor:pointer;text-decoration:none;margin-right:8px;}
	a.back{color:#2271b1;font-size:13px;}
	.warn{background:#fcf9e8;border:1px solid #dba617;border-radius:4px;padding:10px 14px;font-size:13px;color:#646970;}
	.ok{background:#edfaef;border:1px solid #00a32a;border-radius:4px;padding:10px 14px;font-size:13px;color:#1d2327;margin-bottom:14px;}
	.err{background:#fcf0f1;border:1px solid #d63638;border-radius:4px;padding:10px 14px;font-size:13px;color:#1d2327;margin-bottom:14px;}
	.status{background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:10px 14px;font-size:13px;color:#50575e;margin:14px 0;}</style></head><body>
	<div class="card">
		<h1>Partner-Einladung: <?php echo esc_html( get_the_title( $partner_id ) ); ?></h1>
		<?php if ( '1' === $notice ) : ?><div class="ok">✓ Einladung versendet. Automatischer Nachfass nach <?php echo (int) $days; ?> Tagen, falls sie liegen bleibt.</div><?php endif; ?>
		<?php if ( '0' === $notice ) : ?><div class="err">Versand fehlgeschlagen — E-Mail-Adresse prüfen.</div><?php endif; ?>
		<?php if ( $linked > 0 ) : ?>
			<div class="warn">⚠ Dieser Partner ist bereits mit einem Konto verknüpft (User #<?php echo (int) $linked; ?>). Der Link würde beim Einlösen fehlschlagen, nur nötig, wenn du die Verknüpfung vorher löst.</div>
		<?php else : ?>
			<h2>Einladung per E-Mail senden (empfohlen)</h2>
			<p>Versendet die versionierte Start-Mail: Nutzen, „kostenlos, provisionsbasiert", persönlicher Link, deine Telefonnummer. Liegt sie <?php echo (int) $days; ?> Tage, geht automatisch genau ein Nachfass raus.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fge_partner_invite_send">
				<input type="hidden" name="post_id" value="<?php echo (int) $partner_id; ?>">
				<?php wp_nonce_field( 'fge_partner_invite_send_' . $partner_id ); ?>
				<input class="mail" type="email" name="fge_invite_to" value="<?php echo esc_attr( $to ); ?>" placeholder="empfaenger@golfclub.de" required>
				<button type="submit"><?php echo $sent_at > 0 ? 'Einladung erneut senden' : 'Einladung jetzt senden'; ?></button>
			</form>
			<?php if ( $sent_at > 0 ) : ?>
			<div class="status">Versendet am <?php echo esc_html( wp_date( 'd.m.Y H:i', $sent_at ) ); ?> an <?php echo esc_html( (string) get_post_meta( $partner_id, '_fge_invite_sent_to', true ) ); ?> · noch nicht eingelöst · Nachfass: <?php echo $remind ? 'bereits verschickt' : 'geplant nach ' . (int) $days . ' Tagen'; ?></div>
			<?php endif; ?>
			<h2>Oder: Link kopieren</h2>
			<p>Für den Versand aus deinem eigenen Postfach. Der Link ist einmalig gültig. Achtung: Der automatische Nachfass greift nur nach Versand über den Button oben.</p>
		<?php endif; ?>
		<?php if ( '' !== $url ) : ?>
		<input type="text" value="<?php echo esc_attr( $url ); ?>" id="fge-inv" readonly onclick="this.select()">
		<button onclick="document.getElementById('fge-inv').select();document.execCommand('copy');this.textContent='Kopiert ✓';">Link kopieren</button>
		<?php endif; ?>
		<a class="back" href="<?php echo esc_url( $back ); ?>">← Zurück zur Partner-Liste</a>
	</div></body></html><?php
	exit;
} );

// Versand der System-Einladung aus dem Modal.
add_action( 'admin_post_fge_partner_invite_send', static function (): void {
	$partner_id = absint( $_POST['post_id'] ?? 0 );
	if ( $partner_id <= 0 || ! current_user_can( 'manage_options' ) || get_post_type( $partner_id ) !== 'firmengolf_partner' ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_partner_invite_send_' . $partner_id );
	if ( (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true ) > 0 ) {
		wp_die( 'Dieser Partner ist bereits verknüpft, eine Einladung würde fehlschlagen.', '', [ 'response' => 409 ] );
	}
	$to   = sanitize_email( wp_unslash( $_POST['fge_invite_to'] ?? '' ) );
	$sent = function_exists( 'fge_send_partner_invite_email' ) && fge_send_partner_invite_email( $partner_id, $to );
	// Zurück ins Modal (braucht frische Nonce der Anzeige-Aktion).
	$modal = wp_nonce_url( admin_url( 'admin-post.php?action=fge_partner_invite&post_id=' . $partner_id ), 'fge_partner_invite_' . $partner_id );
	wp_safe_redirect( add_query_arg( 'fge_sent', $sent ? '1' : '0', $modal ) );
	exit;
} );

// ── Nachfass + interne Übersicht (täglicher Followup-Cron, request-followups.php) ──

/** Offene Einladungen: versendet, aber noch nicht eingelöst. */
function fge_invite_open_invites(): array {
	$ids = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_fge_invite_sent_at', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	] );
	return array_values( array_filter( array_map( 'intval', $ids ), static function ( int $pid ): bool {
		return (int) get_post_meta( $pid, '_fge_assigned_wp_user_id', true ) <= 0
			&& (int) get_post_meta( $pid, '_fge_invite_sent_at', true ) > 0;
	} ) );
}

add_action( 'fge_request_followups_cron', 'fge_invite_run_followups' );
function fge_invite_run_followups(): array {
	$stats = [ 'invite_reminded' => 0, 'digest' => 0 ];
	$days  = (int) apply_filters( 'fge_invite_reminder_days', 5 );
	$open  = fge_invite_open_invites();

	// Genau EIN automatischer Nachfass pro Einladung — mehr nervt, weniger versickert.
	foreach ( $open as $pid ) {
		if ( '1' === (string) get_post_meta( $pid, '_fge_invite_reminded', true ) ) {
			continue;
		}
		if ( time() < (int) get_post_meta( $pid, '_fge_invite_sent_at', true ) + $days * DAY_IN_SECONDS ) {
			continue;
		}
		if ( function_exists( 'fge_send_partner_invite_reminder_email' ) && fge_send_partner_invite_reminder_email( $pid ) ) {
			update_post_meta( $pid, '_fge_invite_reminded', 1 );
			$stats['invite_reminded']++;
		}
	}

	// Interne Wochenübersicht: was liegt offen und wie lange schon? Nur senden,
	// wenn es offene Einladungen gibt — sonst kein Rauschen im Postfach.
	if ( $open && time() >= (int) get_option( 'fge_invite_digest_last', 0 ) + 7 * DAY_IN_SECONDS ) {
		$rows = '';
		foreach ( $open as $pid ) {
			$sent_at = (int) get_post_meta( $pid, '_fge_invite_sent_at', true );
			$age     = max( 0, (int) floor( ( time() - $sent_at ) / DAY_IN_SECONDS ) );
			$rows   .= '<tr>'
				. '<td style="padding:6px 10px;border-bottom:1px solid #eee;"><strong>' . esc_html( get_the_title( $pid ) ) . '</strong></td>'
				. '<td style="padding:6px 10px;border-bottom:1px solid #eee;">' . esc_html( (string) get_post_meta( $pid, '_fge_invite_sent_to', true ) ) . '</td>'
				. '<td style="padding:6px 10px;border-bottom:1px solid #eee;">vor ' . $age . ' Tag' . ( 1 === $age ? '' : 'en' ) . '</td>'
				. '<td style="padding:6px 10px;border-bottom:1px solid #eee;">' . ( '1' === (string) get_post_meta( $pid, '_fge_invite_reminded', true ) ? 'Nachfass raus' : 'wartet' ) . '</td>'
				. '</tr>';
		}
		$to      = apply_filters( 'fge_internal_email', fge_company_internal_email() );
		$subject = 'Offene Partner-Einladungen: ' . count( $open );
		$content = '<p style="margin:0 0 16px;">Diese versendeten Einladungen sind noch nicht eingelöst — Kandidaten für einen persönlichen Anruf:</p>'
			. '<table style="border-collapse:collapse;width:100%;font-size:13px;"><tr>'
			. '<th style="padding:6px 10px;text-align:left;border-bottom:2px solid #ddd;">Club</th>'
			. '<th style="padding:6px 10px;text-align:left;border-bottom:2px solid #ddd;">Empfänger</th>'
			. '<th style="padding:6px 10px;text-align:left;border-bottom:2px solid #ddd;">Versendet</th>'
			. '<th style="padding:6px 10px;text-align:left;border-bottom:2px solid #ddd;">Status</th></tr>' . $rows . '</table>'
			. '<p style="margin:16px 0 0;">' . ( function_exists( 'fge_email_button' ) ? fge_email_button( admin_url( 'edit.php?post_type=firmengolf_partner' ), 'Zur Partner-Liste' ) : '' ) . '</p>';
		if ( function_exists( 'fge_email_wrap' ) && wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] ) ) {
			update_option( 'fge_invite_digest_last', time(), false );
			$stats['digest'] = 1;
		}
	}

	return $stats;
}
