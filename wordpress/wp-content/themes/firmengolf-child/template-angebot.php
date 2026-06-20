<?php
/**
 * Kunden-Seite: Angebot ansehen, annehmen oder ablehnen, plus read-only Status.
 * Aufruf: /angebot/<customer-token>/  (siehe includes/offers.php).
 * Standalone im .tl-*-Design (kein Theme-Chrome).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$token = (string) get_query_var( 'fge_angebot' );
$req   = function_exists( 'fge_request_by_customer_token' ) ? fge_request_by_customer_token( $token ) : 0;
$done  = isset( $_GET['done'] );

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<meta name="referrer" content="no-referrer">
	<?php wp_head(); ?>
</head>
<body class="tl-page">
	<header class="tl-bar">
		<span style="font-family:var(--font-display);font-weight:600;font-size:18px;letter-spacing:-0.02em;color:var(--ink-900);">Firmengolf</span>
		<span class="ctx">Euer Angebot</span>
	</header>

	<div class="tl-wrap">
	<?php if ( $req <= 0 ) : ?>
		<div class="tl-eyebrow">Angebot</div>
		<h1 class="tl-h">Dieser Link ist <em>ungültig</em> oder abgelaufen.</h1>
		<p class="tl-lead">Bitte wende dich an deinen Firmengolf-Ansprechpartner.</p>
	<?php else :
		$ref          = function_exists( 'fge_request_number' ) ? fge_request_number( $req ) : 'FG-' . $req;
		$first        = (string) get_post_meta( $req, '_fge_contact_first_name', true );
		$status       = (string) get_post_meta( $req, '_fge_request_status', true );
		$offer_status = (string) get_post_meta( $req, '_fge_offer_status', true );
		$snap         = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
		$nonce        = wp_create_nonce( 'fge_offer_' . $token );
		$deadline     = (int) get_post_meta( $req, '_fge_offer_deadline', true );

		// Read-only Statusmeldung für den Kunden (wenn noch kein offenes Angebot vorliegt).
		$status_msg = static function ( string $s ): string {
			switch ( $s ) {
				case 'neu':
				case 'eingangsbestaetigung_gesendet':
					return 'Eure Anfrage ist eingegangen. Wir prüfen gerade passende Optionen.';
				case 'verfuegbarkeit_wird_geprueft':
				case 'partner_angefragt':
				case 'teilweise_verfuegbar':
					return 'Wir stimmen die Termine mit dem Platz ab. Sobald ein Termin steht, bekommt ihr euer Angebot.';
				case 'vollstaendig_verfuegbar':
					return 'Ein Termin steht. Euer Angebot ist unterwegs.';
				case 'angebot_angenommen':
				case 'abgeschlossen':
					return 'Euer Event ist gebucht. Wir kümmern uns um die letzten Details.';
				case 'angebot_abgelehnt':
				case 'verloren':
					return 'Diese Anfrage ist abgeschlossen. Meldet euch gern jederzeit für ein neues Event.';
				case 'nicht_verfuegbar':
					return 'Leider hat keiner der Wunschtermine gepasst. Firmengolf meldet sich mit Alternativen.';
				default:
					return 'Eure Anfrage ist in Bearbeitung.';
			}
		};

		// 1) Frisch angenommen/abgelehnt → Bestätigung.
		if ( $done && in_array( $offer_status, [ 'accepted', 'declined' ], true ) ) : ?>
			<div class="tl-done">
				<div class="tl-done-ic">
					<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				</div>
				<?php if ( 'accepted' === $offer_status ) : ?>
					<h2>Gebucht, <?php echo esc_html( $first ?: 'super' ); ?>!</h2>
					<p>Euer Event <strong><?php echo esc_html( (string) ( $snap['event_title'] ?? '' ) ); ?></strong> am <strong><?php echo esc_html( (string) ( $snap['date'] ?? '' ) ); ?></strong> ist verbindlich gebucht. Wir kümmern uns um die letzten Details und melden uns.</p>
				<?php else : ?>
					<h2>Schade, <?php echo esc_html( $first ?: '' ); ?>.</h2>
					<p>Ihr habt das Angebot abgelehnt. Wenn ihr mögt, finden wir gern eine Alternative, antwortet einfach auf die Angebots-Mail.</p>
				<?php endif; ?>
			</div>

		<?php // 2) Offenes Angebot → annehmen/ablehnen.
		elseif ( 'pending' === $offer_status && ! empty( $snap ) ) : ?>
			<div class="tl-eyebrow">Angebot <?php echo esc_html( $ref ); ?></div>
			<h1 class="tl-h">Hallo <?php echo esc_html( $first ?: '' ); ?>, hier ist <em>euer Angebot</em>.</h1>
			<p class="tl-lead">Der Termin steht. Schaut es euch an und nehmt es mit einem Klick an.</p>
			<?php if ( $deadline > time() ) : ?>
			<div class="tl-deadline">Bitte bis <strong><?php echo esc_html( wp_date( 'D, d.m.Y', $deadline ) ); ?></strong> zusagen, damit wir den Termin fest für euch reservieren.</div>
			<?php endif; ?>

			<div class="tl-summary">
				<div class="tl-sum-co"><?php echo esc_html( (string) ( $snap['event_title'] ?? '' ) ); ?></div>
				<div class="tl-sum-grid">
					<div class="tl-sum-item"><div class="k">Termin</div><div class="v"><?php echo esc_html( (string) ( $snap['date'] ?? '' ) ); ?></div></div>
					<?php if ( (int) ( $snap['participants'] ?? 0 ) > 0 ) : ?><div class="tl-sum-item"><div class="k">Teilnehmer</div><div class="v">ca. <?php echo (int) $snap['participants']; ?> Personen</div></div><?php endif; ?>
					<div class="tl-sum-item"><div class="k">Preis</div><div class="v"><?php echo esc_html( function_exists( 'fge_offer_price_text' ) ? fge_offer_price_text( $snap ) : '' ); ?></div></div>
				</div>
			</div>

			<?php if ( ! empty( $snap['includes'] ) ) : ?>
			<div class="tl-section-label">Das ist dabei</div>
			<ul class="tl-list"><?php foreach ( (array) $snap['includes'] as $i ) : ?><li><?php echo esc_html( $i ); ?></li><?php endforeach; ?></ul>
			<?php endif; ?>

			<?php if ( ! empty( $snap['wishes_platz'] ) || ! empty( $snap['wishes_firmengolf'] ) ) : ?>
			<div class="tl-section-label">Eure Zusatzwünsche</div>
			<ul class="tl-list">
				<?php foreach ( (array) ( $snap['wishes_platz'] ?? [] ) as $i ) : ?><li><?php echo esc_html( $i ); ?> <span class="tl-src">am Platz</span></li><?php endforeach; ?>
				<?php foreach ( (array) ( $snap['wishes_firmengolf'] ?? [] ) as $i ) : ?><li><?php echo esc_html( $i ); ?> <span class="tl-src">durch Firmengolf</span></li><?php endforeach; ?>
			</ul>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( fge_offer_link( $req ) ); ?>" class="tl-offer-actions">
				<input type="hidden" name="fge_offer_token" value="<?php echo esc_attr( $token ); ?>">
				<input type="hidden" name="fge_offer_nonce" value="<?php echo esc_attr( $nonce ); ?>">
				<button type="submit" name="fge_offer_action" value="accept" class="tl-btn yes" style="padding:13px 26px;">Angebot annehmen</button>
				<button type="submit" name="fge_offer_action" value="decline" class="tl-btn no" style="padding:13px 26px;">Leider absagen</button>
			</form>
			<p class="tl-note">Mit „Angebot annehmen" bucht ihr verbindlich. Bei Fragen einfach auf die Angebots-Mail antworten.</p>

		<?php // 3) Read-only Status (noch kein Angebot oder schon abgeschlossen).
		else : ?>
			<div class="tl-eyebrow">Anfrage <?php echo esc_html( $ref ); ?></div>
			<h1 class="tl-h">Hallo <?php echo esc_html( $first ?: '' ); ?>, hier ist <em>euer aktueller Stand</em>.</h1>
			<div class="tl-status-card"><?php echo esc_html( $status_msg( $status ) ); ?></div>
			<?php if ( 'accepted' === $offer_status && ! empty( $snap ) ) : ?>
			<div class="tl-summary" style="margin-top:18px;">
				<div class="tl-sum-co"><?php echo esc_html( (string) ( $snap['event_title'] ?? '' ) ); ?></div>
				<div class="tl-sum-grid">
					<div class="tl-sum-item"><div class="k">Termin</div><div class="v"><?php echo esc_html( (string) ( $snap['date'] ?? '' ) ); ?></div></div>
					<div class="tl-sum-item"><div class="k">Status</div><div class="v">Gebucht</div></div>
				</div>
			</div>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
