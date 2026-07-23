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

// ── Mehrere ASP pro Platz (2026-07-21) ────────────────────────────────────────
// Jeder Ansprechpartner bekommt eine eigene Einladung mit eigenem Token/Link.
// Speicher: Post-Meta _fge_invitees (Array, Key = Token). Für die Token→Platz-
// Auflösung wird jeder Token zusätzlich als eigene _fge_invite_token-Meta-Zeile
// (add_post_meta) hinterlegt, damit fge_invite_partner_by_token weiter greift.

/** @return array<string,array{email:string,name:string,sent_at:int,reminders:int,accepted_at:int,user_id:int,status:string}> */
function fge_invitees( int $partner_id ): array {
	$list = get_post_meta( $partner_id, '_fge_invitees', true );
	return is_array( $list ) ? $list : [];
}

function fge_invitees_save( int $partner_id, array $list ): void {
	update_post_meta( $partner_id, '_fge_invitees', $list );
}

/** Neue Einladung für einen ASP anlegen; gibt den frischen Token zurück. */
function fge_invite_create( int $partner_id, string $email, string $name = '' ): string {
	$token          = bin2hex( random_bytes( 20 ) );
	$list           = fge_invitees( $partner_id );
	$list[ $token ] = [
		'email'       => $email,
		'name'        => $name,
		'sent_at'     => 0,
		'reminders'   => 0,
		'accepted_at' => 0,
		'user_id'     => 0,
		'status'      => 'sent',
	];
	fge_invitees_save( $partner_id, $list );
	add_post_meta( $partner_id, '_fge_invite_token', $token );
	return $token;
}

/**
 * Offene Einladung derselben Adresse an diesem Platz (Dedupe, Audit-Punkt 3):
 * erneutes Senden nutzt den bestehenden Link wieder, statt eine zweite
 * Nachfass-Serie an dieselbe Person zu starten. Gibt den Token oder '' zurück.
 */
function fge_invite_find_open( int $partner_id, string $email ): string {
	foreach ( fge_invitees( $partner_id ) as $tok => $inv ) {
		if ( 0 === (int) ( $inv['accepted_at'] ?? 0 )
			&& strtolower( trim( (string) ( $inv['email'] ?? '' ) ) ) === strtolower( trim( $email ) ) ) {
			return (string) $tok;
		}
	}
	return '';
}

/**
 * Hat der Platz irgendein Event, egal in welchem Status? (Audit-Punkt 2: der
 * Aktivierungs-Nachfass darf nicht feuern, wenn ein Event bereits eingereicht
 * ist und nur auf Freigabe wartet.)
 */
function fge_partner_has_any_event( int $partner_id ): bool {
	$ids = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => [ 'publish', 'draft', 'pending', 'future', 'private' ],
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_query'  => [ [ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id, 'type' => 'NUMERIC' ] ],
	] );
	return ! empty( $ids );
}

/** Ein Feld im Invitee-Record setzen und speichern. */
function fge_invite_update( int $partner_id, string $token, array $patch ): void {
	$list = fge_invitees( $partner_id );
	if ( ! isset( $list[ $token ] ) ) {
		return;
	}
	$list[ $token ] = array_merge( $list[ $token ], $patch );
	fge_invitees_save( $partner_id, $list );
}

function fge_invite_url_for( int $partner_id, string $token ): string {
	return home_url( '/einladung/' . get_post_field( 'post_name', $partner_id ) . '/' . $token . '/' );
}

/** Hat mindestens ein ASP dieses Platzes die Einladung angenommen? */
function fge_partner_is_claimed( int $partner_id ): bool {
	if ( (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true ) > 0 ) {
		return true;
	}
	foreach ( fge_invitees( $partner_id ) as $inv ) {
		if ( (int) ( $inv['accepted_at'] ?? 0 ) > 0 ) {
			return true;
		}
	}
	return false;
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
	$token     = sanitize_text_field( wp_unslash( $_POST['fge_invite_token'] ?? '' ) );
	$token_url = preg_match( '/^[a-f0-9]{40}$/', $token ) ? home_url( '/einladung/' . $token . '/' ) : home_url( '/' );
	// Abgelaufene Nonce (Seite lag lange offen im Tab): freundlich zurück statt
	// weißer 403-Seite; einmal neu absenden genügt (Audit 2026-07-23, Punkt 9).
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_invite_nonce'] ?? '' ) ), 'fge_invite_' . $token ) ) {
		wp_safe_redirect( add_query_arg( 'fehler', 'sitzung', $token_url ) );
		exit;
	}
	$partner_id = fge_invite_partner_by_token( $token );
	// WICHTIG: zurück auf die URL DIESES Tokens. Die alte fge_invite_url() nahm die
	// erste Token-Zeile des Platzes; bei mehreren ASP landete der Nutzer nach dem
	// Code-Versand auf einem fremden Token, sein Passwort-Transient und Code-Kontext
	// waren dort unauffindbar → Dauerschleife "Eingabe hat zu lange gedauert"
	// (Live-Vorfall GC Schloss Igling, 2026-07-23).
	$back       = $partner_id > 0 ? fge_invite_url_for( $partner_id, $token ) : $token_url;
	// Ungültiger/verbrauchter/verknüpfter Link: auf die Landing leiten, die zeigt
	// den passenden freundlichen Zustand ("Schon erledigt" bzw. "nicht mehr gültig").
	if ( $partner_id <= 0 ) {
		wp_safe_redirect( $token_url );
		exit;
	}
	$invitees = fge_invitees( $partner_id );
	$has_rec  = isset( $invitees[ $token ] );
	if ( $has_rec && (int) ( $invitees[ $token ]['accepted_at'] ?? 0 ) > 0 ) {
		wp_safe_redirect( $back );
		exit;
	}
	// Alt-Einladung ohne Invitee-Record: wie bisher genau ein Konto pro Platz.
	if ( ! $has_rec && (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true ) > 0 ) {
		wp_safe_redirect( $back );
		exit;
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

	// Reverse-Link am User: mehrere ASP können denselben Platz verwalten.
	update_user_meta( $user_id, '_fge_managed_partner_id', $partner_id );
	// Primärer User nur, wenn der Platz noch keinen hat (erster Accepter).
	if ( (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true ) <= 0 ) {
		update_post_meta( $partner_id, '_fge_assigned_wp_user_id', $user_id );
	}
	// Zeitpunkt der ersten Annahme (Basis für den Aktivierungs-Nachfass, Strecke B).
	if ( (int) get_post_meta( $partner_id, '_fge_first_accept_at', true ) <= 0 ) {
		update_post_meta( $partner_id, '_fge_first_accept_at', time() );
	}
	update_post_meta( $partner_id, '_fge_partner_portal_enabled', 1 );
	// Recherchierte Kontaktdaten nur ergänzen, nicht überschreiben.
	if ( '' === (string) get_post_meta( $partner_id, '_fge_main_contact_name', true ) ) {
		update_post_meta( $partner_id, '_fge_main_contact_name', trim( $first . ' ' . $last ) );
	}
	if ( '' === (string) get_post_meta( $partner_id, '_fge_main_contact_email', true ) ) {
		update_post_meta( $partner_id, '_fge_main_contact_email', $email );
	}

	// Entwerten: beim Invitee-Record bleibt die Token-Zeile stehen, damit ein
	// erneuter Klick aus der Mail die freundliche "Schon erledigt"-Seite zeigt
	// (der Record mit accepted_at blockt jede Wiederverwendung, s. oben).
	// Nur Alt-Einladungen ohne Record entwerten weiter über das Token-Löschen.
	if ( $has_rec ) {
		fge_invite_update( $partner_id, $token, [ 'accepted_at' => time(), 'user_id' => $user_id, 'status' => 'accepted' ] );
	} else {
		delete_post_meta( $partner_id, '_fge_invite_token', $token );
	}
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

	$back     = admin_url( 'edit.php?post_type=firmengolf_partner' );
	$prefill  = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true );
	$invitees = fge_invitees( $partner_id );
	$primary  = (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true );
	$notice   = sanitize_key( $_GET['fge_sent'] ?? '' );
	?><!doctype html><html lang="de"><head><meta charset="utf-8"><title>Partner-Einladung</title>
	<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f0f0f1;margin:0;padding:60px 20px;}
	.card{max-width:680px;margin:0 auto;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:28px 32px;}
	h1{font-size:20px;margin:0 0 6px;}h2{font-size:14px;margin:26px 0 4px;}p{color:#50575e;font-size:14px;line-height:1.5;}
	input{width:100%;font:14px/1.4 inherit;padding:10px 12px;border:1px solid #c3c4c7;border-radius:4px;box-sizing:border-box;margin:12px 0;}
	button,a.btn{display:inline-block;background:#2271b1;color:#fff;border:0;border-radius:4px;padding:9px 16px;font-size:13px;cursor:pointer;text-decoration:none;}
	a.back{color:#2271b1;font-size:13px;}
	table{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px;}
	th{text-align:left;padding:6px 8px;border-bottom:2px solid #ddd;color:#50575e;}
	td{padding:8px;border-bottom:1px solid #eee;color:#1d2327;}
	.ok{background:#edfaef;border:1px solid #00a32a;border-radius:4px;padding:10px 14px;font-size:13px;color:#1d2327;margin-bottom:14px;}
	.err{background:#fcf0f1;border:1px solid #d63638;border-radius:4px;padding:10px 14px;font-size:13px;color:#1d2327;margin-bottom:14px;}
	.status{background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:10px 14px;font-size:13px;color:#50575e;margin:14px 0;}</style></head><body>
	<div class="card">
		<h1>Partner-Einladung: <?php echo esc_html( get_the_title( $partner_id ) ); ?></h1>
		<?php if ( '1' === $notice ) : ?><div class="ok">✓ Einladung versendet. Bis zu drei automatische Nachfässe (Tag 5, 10, 15), falls sie liegen bleibt.</div><?php endif; ?>
		<?php if ( '0' === $notice ) : ?><div class="err">Versand fehlgeschlagen, E-Mail-Adresse prüfen.</div><?php endif; ?>

		<h2>Ansprechpartner einladen</h2>
		<p>Jeder ASP bekommt einen eigenen persönlichen Link. Mehrere Personen können denselben Platz verwalten. Für den Platz ist Firmengolf kostenlos, die Provision zahlt der Kunde.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fge_partner_invite_send">
			<input type="hidden" name="post_id" value="<?php echo (int) $partner_id; ?>">
			<?php wp_nonce_field( 'fge_partner_invite_send_' . $partner_id ); ?>
			<input type="email" name="fge_invite_to" value="<?php echo esc_attr( 0 === count( $invitees ) ? $prefill : '' ); ?>" placeholder="ansprechpartner@golfclub.de" required>
			<input type="text" name="fge_invite_name" value="" placeholder="Vorname für die Anrede (optional, z. B. Manuel)">
			<button type="submit">Einladung senden</button>
			<p style="font-size:12px;color:#787c82;margin:8px 0 0;">Nochmal an dieselbe Adresse senden ist ok: der bestehende Link wird wiederverwendet und die Nachfass-Serie startet neu.</p>
		</form>

		<?php if ( $invitees ) : ?>
			<h2>Bereits eingeladen</h2>
			<table>
				<tr><th>E-Mail</th><th>Status</th><th></th></tr>
				<?php foreach ( $invitees as $tok => $inv ) :
					$acc = (int) ( $inv['accepted_at'] ?? 0 );
					if ( $acc > 0 ) {
						$st = '✓ angenommen';
					} elseif ( 'no_response' === ( $inv['status'] ?? '' ) ) {
						$st = 'keine Rückmeldung';
					} else {
						$rem = (int) ( $inv['reminders'] ?? 0 );
						$st  = 'versendet' . ( $rem > 0 ? ', ' . $rem . 'x nachgefasst' : '' );
					}
					$link = fge_invite_url_for( $partner_id, (string) $tok );
					?>
					<tr>
						<td><?php echo esc_html( trim( (string) ( $inv['name'] ?? '' ) . ' ' ) ); ?><?php echo esc_html( (string) ( $inv['email'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( $st ); ?></td>
						<td style="text-align:right;">
							<?php if ( 0 === $acc ) : ?>
								<a class="btn" href="#" onclick="navigator.clipboard.writeText('<?php echo esc_js( $link ); ?>');this.textContent='Kopiert &#10003;';return false;">Link kopieren</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php endif; ?>

		<?php if ( $primary > 0 ) : ?>
			<div class="status">Dieser Platz hat bereits einen aktiven Zugang (User #<?php echo (int) $primary; ?>). Weitere ASP können trotzdem eingeladen werden und teilen sich denselben Golfplatz-Account.</div>
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
	// Mehrere ASP möglich: kein Block mehr, wenn der Platz schon einen Zugang hat.
	$to   = sanitize_email( wp_unslash( $_POST['fge_invite_to'] ?? '' ) );
	$who  = sanitize_text_field( wp_unslash( $_POST['fge_invite_name'] ?? '' ) );
	$sent = function_exists( 'fge_send_partner_invite_email' ) && fge_send_partner_invite_email( $partner_id, $to, $who );
	// Zurück ins Modal (braucht frische Nonce der Anzeige-Aktion).
	$modal = wp_nonce_url( admin_url( 'admin-post.php?action=fge_partner_invite&post_id=' . $partner_id ), 'fge_partner_invite_' . $partner_id );
	wp_safe_redirect( add_query_arg( 'fge_sent', $sent ? '1' : '0', $modal ) );
	exit;
} );

// ── Nachfass + interne Übersicht (täglicher Followup-Cron, request-followups.php) ──

/** Partner mit angelegten Einladungen (mind. ein Invitee-Record). */
function fge_partners_with_invitees(): array {
	return array_map( 'intval', get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_fge_invitees', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	] ) );
}

/** Partner, die eine Einladung angenommen haben (Basis für Strecke B und C). */
function fge_partners_accepted(): array {
	return array_map( 'intval', get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_fge_first_accept_at', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	] ) );
}

/** Frühestes Veröffentlichungsdatum der Events eines Platzes (Unix-Timestamp). */
function fge_partner_first_event_ts( int $partner_id, array $event_ids = [] ): int {
	if ( ! $event_ids ) {
		$event_ids = function_exists( 'fge_partner_public_event_ids' ) ? fge_partner_public_event_ids( $partner_id ) : [];
	}
	$min = 0;
	foreach ( $event_ids as $eid ) {
		$ts = (int) get_post_time( 'U', true, (int) $eid );
		if ( $ts > 0 && ( 0 === $min || $ts < $min ) ) {
			$min = $ts;
		}
	}
	return $min;
}

add_action( 'fge_request_followups_cron', 'fge_invite_run_followups' );
/**
 * Drei Nachfass-Strecken (2026-07-21):
 * A) nicht angenommen: je ASP Nachfass an Tag 5/10/15, danach Status „keine Rückmeldung";
 *    Stopp für ALLE ASP eines Platzes, sobald einer annimmt.
 * B) angenommen, kein Event: Tag 3 Aktivierungs-Nudge an den Partner, Tag 8 interne Alarm-Mail.
 * C) angenommen, mind. ein Event: ~Tag 12 nach dem ersten Event einmalig Embed-Promo.
 */
function fge_invite_run_followups(): array {
	$stats       = [ 'reminded' => 0, 'no_response' => 0, 'activation' => 0, 'alert' => 0, 'embed' => 0, 'digest' => 0 ];
	$stages_days = [ 5, 10, 15 ];

	// Einmalige Übernahme von Alt-Einladungen (vor dem Multi-ASP-Umbau versendet):
	// sie hatten nur _fge_invite_sent_at/_to + eine Token-Zeile und wären sonst aus
	// allen Nachfässen und dem Digest gefallen (Audit 2026-07-23, Punkt 6).
	if ( ! get_option( 'fge_invites_migrated_v2' ) ) {
		$legacy = get_posts( [
			'post_type'   => 'firmengolf_partner',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => '_fge_invite_sent_at', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		] );
		foreach ( $legacy as $pid ) {
			$pid = (int) $pid;
			if ( fge_invitees( $pid ) || fge_partner_is_claimed( $pid ) ) {
				continue;
			}
			$tok  = (string) get_post_meta( $pid, '_fge_invite_token', true );
			$mail = (string) get_post_meta( $pid, '_fge_invite_sent_to', true );
			if ( '' === $tok || ! is_email( $mail ) ) {
				continue;
			}
			fge_invitees_save( $pid, [ $tok => [
				'email'       => $mail,
				'name'        => '',
				'sent_at'     => (int) get_post_meta( $pid, '_fge_invite_sent_at', true ),
				'reminders'   => '1' === (string) get_post_meta( $pid, '_fge_invite_reminded', true ) ? 1 : 0,
				'accepted_at' => 0,
				'user_id'     => 0,
				'status'      => 'sent',
			] ] );
		}
		update_option( 'fge_invites_migrated_v2', 1, false );
	}

	// Einmalige Übernahme v3 (Julius, 2026-07-23): Altfälle "Zugang angelegt, aber
	// nie ein Event" aus der Zeit VOR dem Umbau (z. B. GC Chieming) in die
	// Aktivierungs-Strecke holen. Zeitstempel = jetzt, damit die Kette normal
	// startet (Nudge Tag 3, interne Alarm-Mail Tag 8, Stopp beim ersten Event).
	// WICHTIG: Wer schon einen Zeitstempel hat (alle seit 21.07. Angenommenen,
	// "die Neuen"), wird NIE überschrieben; Partner mit Events bleiben unberührt.
	if ( ! get_option( 'fge_invites_migrated_v3' ) ) {
		$linked = get_posts( [
			'post_type'   => 'firmengolf_partner',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => '_fge_assigned_wp_user_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		] );
		foreach ( $linked as $pid ) {
			$pid = (int) $pid;
			if ( function_exists( 'fge_is_demo_partner' ) && fge_is_demo_partner( $pid ) ) {
				continue;
			}
			if ( (int) get_post_meta( $pid, '_fge_assigned_wp_user_id', true ) <= 0 ) {
				continue;
			}
			if ( (int) get_post_meta( $pid, '_fge_first_accept_at', true ) > 0 ) {
				continue; // Neue seit dem Umbau: Zeitstempel bleibt unangetastet.
			}
			if ( fge_partner_has_any_event( $pid ) ) {
				continue; // Wer schon Events hat, braucht keinen Aktivierungs-Nachfass.
			}
			update_post_meta( $pid, '_fge_first_accept_at', time() );
		}
		update_option( 'fge_invites_migrated_v3', 1, false );
	}

	// ── Strecke A ──
	foreach ( fge_partners_with_invitees() as $pid ) {
		if ( fge_partner_is_claimed( $pid ) ) {
			continue; // Sobald ein ASP angenommen hat, keine weiteren Nachfässe.
		}
		$list    = fge_invitees( $pid );
		$changed = false;
		foreach ( $list as $token => $inv ) {
			$sent_at = (int) ( $inv['sent_at'] ?? 0 );
			if ( (int) ( $inv['accepted_at'] ?? 0 ) > 0 || 'no_response' === ( $inv['status'] ?? '' ) || $sent_at <= 0 ) {
				continue;
			}
			$done = (int) ( $inv['reminders'] ?? 0 );
			if ( $done < 3 ) {
				if ( time() >= $sent_at + $stages_days[ $done ] * DAY_IN_SECONDS
					&& fge_send_partner_invite_reminder_email( $pid, (string) $token, $done + 1 ) ) {
					$list[ $token ]['reminders'] = $done + 1;
					$changed = true;
					$stats['reminded']++;
				}
			} elseif ( time() >= $sent_at + 20 * DAY_IN_SECONDS ) {
				// Erst Tag 20 (nicht direkt nach Nachfass 3 an Tag 15): die dritte
				// Mail soll noch wirken können, bevor der Fall als kalt markiert wird.
				$list[ $token ]['status'] = 'no_response';
				$changed = true;
				$stats['no_response']++;
			}
		}
		if ( $changed ) {
			fge_invitees_save( $pid, $list );
		}
	}

	// ── Strecke B + C ──
	foreach ( fge_partners_accepted() as $pid ) {
		if ( function_exists( 'fge_is_demo_partner' ) && fge_is_demo_partner( $pid ) ) {
			continue;
		}
		$accept_at = (int) get_post_meta( $pid, '_fge_first_accept_at', true );
		$events    = function_exists( 'fge_partner_public_event_ids' ) ? fge_partner_public_event_ids( $pid ) : [];

		// "Kein Event" heißt: wirklich KEINS, auch kein eingereichtes in Prüfung.
		// Wer eingereicht hat und auf Freigabe wartet, darf keinen "leg dein erstes
		// Event an"-Nachfass bekommen (Audit 2026-07-23, Punkt 2).
		if ( empty( $events ) && ! fge_partner_has_any_event( $pid ) ) {
			// B: angemeldet, aber noch kein Event.
			if ( '1' !== (string) get_post_meta( $pid, '_fge_activation_nudged', true )
				&& $accept_at > 0 && time() >= $accept_at + 3 * DAY_IN_SECONDS
				&& fge_send_partner_activation_nudge_email( $pid ) ) {
				update_post_meta( $pid, '_fge_activation_nudged', 1 );
				$stats['activation']++;
			}
			if ( '1' !== (string) get_post_meta( $pid, '_fge_activation_alerted', true )
				&& $accept_at > 0 && time() >= $accept_at + 8 * DAY_IN_SECONDS ) {
				$to      = apply_filters( 'fge_internal_email', fge_company_internal_email() );
				$subject = 'Partner ohne erstes Event: ' . get_the_title( $pid );
				$content = '<p style="margin:0 0 16px;"><strong>' . esc_html( get_the_title( $pid ) ) . '</strong> hat sich vor 8 Tagen angemeldet, aber noch kein Event angelegt. Zeit für einen Anruf.</p>'
					. '<p>' . ( function_exists( 'fge_email_button' ) ? fge_email_button( admin_url( 'post.php?post=' . $pid . '&action=edit' ), 'Partner öffnen' ) : '' ) . '</p>';
				if ( function_exists( 'fge_email_wrap' ) && wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] ) ) {
					update_post_meta( $pid, '_fge_activation_alerted', 1 );
					$stats['alert']++;
				}
			}
		} elseif ( '1' !== (string) get_post_meta( $pid, '_fge_embed_promoed', true ) ) {
			// C: hat mind. ein Event, ~Tag 12 nach dem ersten Event einmal Embed-Promo.
			$first = fge_partner_first_event_ts( $pid, $events );
			if ( $first > 0 && time() >= $first + 12 * DAY_IN_SECONDS
				&& fge_send_partner_embed_promo_email( $pid ) ) {
				update_post_meta( $pid, '_fge_embed_promoed', 1 );
				$stats['embed']++;
			}
		}
	}

	// ── Interne Wochenübersicht offener Einladungen ──
	$open_rows = '';
	$open_n    = 0;
	foreach ( fge_partners_with_invitees() as $pid ) {
		if ( fge_partner_is_claimed( $pid ) ) {
			continue;
		}
		foreach ( fge_invitees( $pid ) as $inv ) {
			// Angenommene raus; "keine Rückmeldung" ebenfalls, sonst steht derselbe
			// kalte Fall für immer jede Woche im Digest (Audit-Punkt 10). Die Liste
			// dieser Fälle bleibt in der Admin-Einladungsbox sichtbar.
			if ( (int) ( $inv['accepted_at'] ?? 0 ) > 0 || 'no_response' === ( $inv['status'] ?? '' ) ) {
				continue;
			}
			$sent_at = (int) ( $inv['sent_at'] ?? 0 );
			$age     = $sent_at > 0 ? max( 0, (int) floor( ( time() - $sent_at ) / DAY_IN_SECONDS ) ) : 0;
			$status  = (int) ( $inv['reminders'] ?? 0 ) . ' Nachfass gesendet';
			$open_rows .= '<tr>'
				. '<td style="padding:6px 10px;border-bottom:1px solid #eee;"><strong>' . esc_html( get_the_title( $pid ) ) . '</strong></td>'
				. '<td style="padding:6px 10px;border-bottom:1px solid #eee;">' . esc_html( (string) ( $inv['email'] ?? '' ) ) . '</td>'
				. '<td style="padding:6px 10px;border-bottom:1px solid #eee;">vor ' . $age . ' Tag' . ( 1 === $age ? '' : 'en' ) . '</td>'
				. '<td style="padding:6px 10px;border-bottom:1px solid #eee;">' . esc_html( $status ) . '</td>'
				. '</tr>';
			$open_n++;
		}
	}
	if ( $open_n > 0 && time() >= (int) get_option( 'fge_invite_digest_last', 0 ) + 7 * DAY_IN_SECONDS ) {
		$to      = apply_filters( 'fge_internal_email', fge_company_internal_email() );
		$subject = 'Offene Partner-Einladungen: ' . $open_n;
		$content = '<p style="margin:0 0 16px;">Diese Einladungen sind noch nicht eingelöst, Kandidaten für einen persönlichen Anruf:</p>'
			. '<table style="border-collapse:collapse;width:100%;font-size:13px;"><tr>'
			. '<th style="padding:6px 10px;text-align:left;border-bottom:2px solid #ddd;">Club</th>'
			. '<th style="padding:6px 10px;text-align:left;border-bottom:2px solid #ddd;">Empfänger</th>'
			. '<th style="padding:6px 10px;text-align:left;border-bottom:2px solid #ddd;">Versendet</th>'
			. '<th style="padding:6px 10px;text-align:left;border-bottom:2px solid #ddd;">Status</th></tr>' . $open_rows . '</table>'
			. '<p style="margin:16px 0 0;">' . ( function_exists( 'fge_email_button' ) ? fge_email_button( admin_url( 'edit.php?post_type=firmengolf_partner' ), 'Zur Partner-Liste' ) : '' ) . '</p>';
		if ( function_exists( 'fge_email_wrap' ) && wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] ) ) {
			update_option( 'fge_invite_digest_last', time(), false );
			$stats['digest'] = 1;
		}
	}

	return $stats;
}
