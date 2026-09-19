<?php
/**
 * Kunden-Seite: Angebot ansehen, annehmen oder ablehnen, plus read-only Status.
 * Aufruf: /angebot/<customer-token>/  (siehe includes/offers.php).
 * Seit 17.09.2026 als klassisches Angebotsdokument (includes/offer-document.php):
 * gleiche Vorlage wie PDF und Angebotsmail, Mail-Optik statt Website-Look.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$token = (string) get_query_var( 'fge_angebot' );
$req   = function_exists( 'fge_request_by_customer_token' ) ? fge_request_by_customer_token( $token ) : 0;
$done     = isset( $_GET['done'] );
$done_val = sanitize_key( $_GET['done'] ?? '' );

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<meta name="referrer" content="no-referrer">
	<?php wp_head(); ?>
	<style>
	<?php echo function_exists( 'fge_offer_document_css' ) ? fge_offer_document_css() : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	body.tl-page.od-page { background: #f4f4f2; }
	.od-wrap { max-width: 780px; margin: 0 auto; padding: 28px 16px 60px; }
	.od-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 0 0 14px; font-size: 13px; color: #6C736E; }
	.od-top strong { color: #1a1a1a; }
	.od-pdf { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; font-size: 13px; font-weight: 600; color: #20294D; background: #fff; border: 1px solid #d8d8d2; border-radius: 999px; padding: 8px 16px; }
	.od-pdf:hover { border-color: #20294D; }
	.od-sheet { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 8px 24px rgba(32,41,77,.08); }
	.od-sheet .od-head td { padding: 20px 32px; }
	.od-sheet .od-body { padding: 30px 32px 24px; }
	.od-actions { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-top: 18px; padding: 22px 32px; }
	.od-actions h2 { font-size: 16px; margin: 0 0 12px; }
	.od-deadline { font-size: 14px; line-height: 1.5; color: #7A5A12; background: #FBF3E0; border: 1px solid #E2C36B; border-radius: 6px; padding: 10px 14px; margin: 0 0 16px; }
	.od-agb { display: flex; gap: 9px; align-items: flex-start; margin: 0 0 14px; font-size: 13px; line-height: 1.45; }
	.od-agb a { color: #4279D1; text-decoration: underline; }
	.od-btns { display: flex; gap: 10px; flex-wrap: wrap; }
	.od-btn { font: inherit; font-size: 14px; font-weight: 600; padding: 12px 24px; border-radius: 8px; border: 1px solid transparent; cursor: pointer; }
	.od-btn.yes { background: #4279D1; color: #fff; border-bottom: 3px solid #2C55A0; }
	.od-btn.yes:disabled { opacity: .5; cursor: default; }
	.od-btn.no { background: #fff; color: #333; border-color: #d8d8d2; }
	.od-btn.q { background: #ECECE6; color: #333; }
	.od-q { display: none; margin-top: 12px; }
	.od-q textarea { width: 100%; padding: 10px 12px; border: 1px solid #d8d8d2; border-radius: 8px; font: inherit; box-sizing: border-box; }
	.od-hint { font-size: 13px; color: #6C736E; margin: 12px 0 0; line-height: 1.5; }
	.od-err { font-size: 13px; color: #B4332B; margin: 0 0 10px; }
	@media (max-width: 640px) {
		.od-wrap { padding: 16px 10px 40px; }
		.od-sheet .od-head td { padding: 16px 18px; }
		.od-sheet .od-body, .od-actions { padding: 20px 18px; }
		.od-meta td { display: block; width: auto !important; padding-right: 0 !important; }
		.od-facts { margin-top: 14px; }
		/* Positionstabelle gestapelt: Leistung volle Breite, Beträge als Zeilen mit Label. */
		.od-pos thead { display: none; }
		.od-pos, .od-pos tbody, .od-pos tfoot { display: block; width: 100%; }
		.od-pos tbody tr { display: block; padding: 12px 0; border-bottom: 1px solid #e4e4e0; }
		.od-pos tbody td { display: block; border: 0; padding: 0; width: auto !important; }
		.od-pos tbody td.c-pos { display: none; }
		.od-pos tbody td.r { display: flex; justify-content: space-between; gap: 12px; text-align: right; white-space: normal; padding: 3px 0 0; }
		.od-pos tbody td.r::before { content: attr(data-label); color: #6C736E; text-align: left; }
		.od-pos tbody td.c-qty { margin-top: 8px; }
		.od-pos tfoot tr { display: flex; justify-content: space-between; gap: 12px; }
		.od-pos tfoot td { display: block; width: auto !important; }
		/* Rabattzeile: langer Text (Code + Inhaber) darf umbrechen, Betrag bleibt einzeilig. */
		.od-pos tfoot tr.od-disc td:first-child { flex: 1 1 auto; min-width: 0; white-space: normal; text-align: left; }
		.od-pos tfoot tr.od-disc td:last-child { flex: 0 0 auto; white-space: nowrap; }
		.od-pos tfoot tr.od-total td { border-top: 2px solid #20294D; }
	}
	</style>
</head>
<body class="tl-page od-page">
	<div class="od-wrap">
	<?php if ( $req <= 0 ) : ?>
		<div class="tl-eyebrow">Angebot</div>
		<h1 class="tl-h">Dieser Link ist <em>ungültig</em> oder abgelaufen.</h1>
		<p class="tl-lead">Bitte wendet euch an euren Ansprechpartner bei Firmengolf.</p>
	<?php else :
		$ref          = function_exists( 'fge_request_number' ) ? fge_request_number( $req ) : 'FG-' . $req;
		$first        = (string) get_post_meta( $req, '_fge_contact_first_name', true );
		$status       = (string) get_post_meta( $req, '_fge_request_status', true );
		$offer_status = (string) get_post_meta( $req, '_fge_offer_status', true );
		$snap         = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
		$nonce        = wp_create_nonce( 'fge_offer_' . $token );
		$deadline     = (int) get_post_meta( $req, '_fge_offer_deadline', true );
		$has_pdf      = function_exists( 'fge_pdf_available' ) && fge_pdf_available() && ! empty( $snap );
		$pdf_link     = $has_pdf && function_exists( 'fge_offer_pdf_link' ) ? fge_offer_pdf_link( $req ) : '';
		$document     = function_exists( 'fge_offer_document_html' ) ? fge_offer_document_html( $req, 'web' ) : '';

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
				case 'bestaetigt':
					return 'Ein Termin steht. Euer Angebot ist unterwegs.';
				case 'in_uebernahme':
					return 'Wir koordinieren euer Event gerade persönlich und melden uns in Kürze.';
				case 'angebot_rueckfrage':
					return 'Eure Rückfrage liegt uns vor. Wir melden uns mit einer Antwort oder einem angepassten Angebot.';
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

		$pdf_button = '' !== $pdf_link
			? '<a class="od-pdf" href="' . esc_url( $pdf_link ) . '"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>Angebot als PDF</a>'
			: '';

		// 1) Frisch bestätigt/abgelehnt/Rückfrage → Bestätigung.
		if ( 'expired' === $done_val ) : ?>
			<div class="tl-done">
				<div class="tl-done-ic">
					<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				</div>
				<h2>Danke, <?php echo esc_html( $first ?: '' ); ?>!</h2>
				<p>Eure Zusage ist bei uns, die Reservierungsfrist war allerdings schon abgelaufen. Wir prüfen sofort, ob der Termin noch frei ist, und melden uns umgehend bei euch.</p>
			</div>

		<?php elseif ( 'query' === $done_val ) : ?>
			<div class="tl-done">
				<div class="tl-done-ic">
					<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				</div>
				<h2>Danke, <?php echo esc_html( $first ?: '' ); ?>!</h2>
				<p>Eure Rückfrage ist bei uns. Wir melden uns kurzfristig mit einer Antwort oder einem angepassten Angebot. Euer Wunschtermin bleibt so lange für euch reserviert.</p>
			</div>

		<?php elseif ( $done && in_array( $offer_status, [ 'accepted', 'declined' ], true ) ) : ?>
			<div class="tl-done" style="padding-bottom:30px;">
				<div class="tl-done-ic">
					<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				</div>
				<?php if ( 'accepted' === $offer_status ) : ?>
					<h2>Gebucht, <?php echo esc_html( $first ?: 'super' ); ?>!</h2>
					<p>Euer Event <strong><?php echo esc_html( (string) ( $snap['event_title'] ?? '' ) ); ?></strong> am <strong><?php echo esc_html( (string) ( $snap['date'] ?? '' ) ); ?></strong> ist verbindlich gebucht. Wir kümmern uns um die letzten Details und melden uns.</p>
				<?php else : ?>
					<h2>Schade, <?php echo esc_html( $first ?: '' ); ?>.</h2>
					<p>Ihr habt das Angebot abgelehnt. Wenn ihr mögt, finden wir gern eine Alternative, antwortet einfach auf die Angebots-Mail oder schreibt uns, wir passen es gern an.</p>
				<?php endif; ?>
			</div>
			<?php if ( 'accepted' === $offer_status && '' !== $document ) : ?>
			<div class="od-top"><span>Eure Buchung im Überblick</span><?php echo $pdf_button; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<div class="od-sheet"><?php echo $document; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<?php endif; ?>

		<?php // 2) Offenes Angebot → annehmen/ablehnen.
		elseif ( 'pending' === $offer_status && ! empty( $snap ) ) : ?>
			<div class="od-top">
				<span>Angebot <strong><?php echo esc_html( $ref ); ?></strong><?php echo '' !== $first ? ' für ' . esc_html( $first ) : ''; ?></span>
				<?php echo $pdf_button; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
			<form method="post" action="<?php echo esc_url( fge_offer_link( $req ) ); ?>" id="tl-offer-form">
				<input type="hidden" name="fge_offer_token" value="<?php echo esc_attr( $token ); ?>">
				<input type="hidden" name="fge_offer_nonce" value="<?php echo esc_attr( $nonce ); ?>">

				<div class="od-sheet"><?php echo $document; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>

				<div class="od-actions">
					<h2>Angebot annehmen</h2>
					<?php if ( $deadline > time() ) : ?>
					<div class="od-deadline">Wir halten den Termin bis <strong><?php echo esc_html( wp_date( 'D, d.m.Y', $deadline ) ); ?></strong> für euch. Sagt ihr bis dahin zu, ist er verbindlich gebucht.</div>
					<?php elseif ( $deadline > 0 ) : ?>
					<div class="od-deadline">Die Reservierungsfrist ist abgelaufen, der Termin ist nicht mehr garantiert. Ihr könnt trotzdem zusagen: wir prüfen dann sofort, ob er noch frei ist, und melden uns umgehend.</div>
					<?php endif; ?>
					<?php if ( isset( $_GET['agb'] ) ) : ?>
					<p class="od-err">Bitte bestätigt die AGB, um verbindlich zu buchen.</p>
					<?php endif; ?>
					<?php if ( isset( $_GET['session'] ) ) : ?>
					<p class="od-err">Die Sitzung war abgelaufen. Bitte bestätigt eure Auswahl noch einmal.</p>
					<?php endif; ?>
					<?php if ( isset( $_GET['err'] ) && 'empty' === sanitize_key( $_GET['err'] ) ) : ?>
					<p class="od-err">Bitte schreibt uns kurz, worum es geht, dann können wir antworten.</p>
					<?php endif; ?>
					<label class="od-agb">
						<input type="checkbox" id="tl-agb" name="fge_offer_agb" value="1" style="margin-top:3px;flex:0 0 auto;">
						<span>Ich akzeptiere die <a href="<?php echo esc_url( home_url( '/agb/' ) ); ?>" target="_blank" rel="noopener">AGB</a> und buche mit „Angebot annehmen" verbindlich.</span>
					</label>
					<div class="od-btns">
						<button type="submit" name="fge_offer_action" value="accept" id="tl-accept" class="od-btn yes" disabled>Angebot annehmen</button>
						<button type="button" id="tl-toggle-q" class="od-btn q">Rückfrage / Änderung</button>
						<button type="submit" name="fge_offer_action" value="decline" class="od-btn no" onclick="return confirm('Angebot wirklich absagen? Der reservierte Termin wird dann freigegeben.');">Leider absagen</button>
					</div>
					<div id="tl-q-wrap" class="od-q">
						<textarea name="fge_offer_message" rows="3" placeholder="Was möchtet ihr ändern oder wissen?"></textarea>
						<button type="submit" name="fge_offer_action" value="request" class="od-btn yes" style="margin-top:8px;">Rückfrage senden</button>
					</div>
					<p class="od-hint">Mit „Angebot annehmen" bucht ihr verbindlich, damit kommt der Vertrag zustande. Ihr bekommt die Bestätigung mit dem Angebot als PDF per E-Mail; wir speichern den Vertragstext, und ihr könnt ihn jederzeit über diesen Link abrufen. Eure Auswahl könnt ihr bis zum Klick über die Häkchen ändern. Vertragssprache ist Deutsch. Lieber erst etwas klären? Nutzt „Rückfrage / Änderung", euer Termin bleibt reserviert.</p>
				</div>
			</form>
			<?php
			$tl_pos  = function_exists( 'fge_offer_positions' ) ? fge_offer_positions( $snap ) : [ 'rows' => [], 'vat_percent' => 19 ];
			$tl_base = 0.0;
			foreach ( (array) $tl_pos['rows'] as $tl_row ) {
				if ( 'event' === $tl_row['kind'] ) {
					$tl_base = (float) $tl_row['total'];
				}
			}
			?>
			<script>
			(function(){
				var agb = document.getElementById('tl-agb'), acc = document.getElementById('tl-accept');
				if (agb && acc) { agb.addEventListener('change', function(){ acc.disabled = !agb.checked; }); }
				var tq = document.getElementById('tl-toggle-q'), qw = document.getElementById('tl-q-wrap');
				if (tq && qw) { tq.addEventListener('click', function(){ qw.style.display = (qw.style.display === 'block' ? 'none' : 'block'); }); }

				// Zusatzleistungen abwählbar: Zwischensumme, USt. und Gesamtbetrag exakt nachrechnen.
				var base = <?php echo wp_json_encode( round( $tl_base, 2 ) ); ?>,
				    vat = <?php echo (int) $tl_pos['vat_percent']; ?>,
				    discPct = <?php echo wp_json_encode( (float) ( $snap['discount']['percent'] ?? 0 ) ); ?>,
				    picks = document.querySelectorAll('.tl-x-pick');
				function fmt(n){ return n.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €'; }
				function recalc(){
					var sub = base;
					picks.forEach(function(p){
						var tr = p.closest('tr');
						if (tr) { tr.classList.toggle('od-off', !p.checked); }
						if (p.checked && tr) { sub += parseFloat(tr.dataset.total || '0') || 0; }
					});
					sub = Math.round(sub * 100) / 100;
					// Partnercode-Rabatt auf die gewählte Zwischensumme, USt. auf den Rest (wie fge_offer_positions).
					var disc = discPct > 0 ? Math.round(sub * discPct) / 100 : 0;
					var net = Math.round((sub - disc) * 100) / 100;
					var v = Math.round(net * vat) / 100;
					var elN = document.getElementById('od-net'), elD = document.getElementById('od-disc'), elV = document.getElementById('od-vat'), elT = document.getElementById('od-total');
					if (elN) elN.textContent = fmt(sub);
					if (elD) elD.textContent = fmt(disc);
					if (elV) elV.textContent = fmt(v);
					if (elT) elT.textContent = fmt(Math.round((net + v) * 100) / 100);
				}
				picks.forEach(function(p){ p.addEventListener('change', recalc); });
			})();
			</script>

		<?php // 3) Read-only Status (noch kein Angebot oder schon abgeschlossen).
		else : ?>
			<div class="tl-eyebrow">Anfrage <?php echo esc_html( $ref ); ?></div>
			<h1 class="tl-h">Hallo <?php echo esc_html( $first ?: '' ); ?>, hier ist <em>euer aktueller Stand</em>.</h1>
			<div class="tl-status-card"><?php echo esc_html( $status_msg( $status ) ); ?></div>
			<?php if ( in_array( $offer_status, [ 'accepted', 'declined' ], true ) && '' !== $document ) : ?>
			<div class="od-top" style="margin-top:22px;"><span><?php echo 'accepted' === $offer_status ? 'Eure Buchung im Überblick' : 'Das Angebot'; ?></span><?php echo $pdf_button; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<div class="od-sheet"><?php echo $document; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
	</div>
	<?php /* Pflichtangaben: Standalone-Template ohne Site-Footer, hier wird verbindlich
		gebucht (Audit 2026-08-12: nur AGB verlinkt, kein Impressum, keine Datenschutzerklärung). */ ?>
	<footer class="tl-legal">
		<a href="<?php echo esc_url( home_url( '/impressum/' ) ); ?>" target="_blank" rel="noopener">Impressum</a>
		<a href="<?php echo esc_url( home_url( '/datenschutz/' ) ); ?>" target="_blank" rel="noopener">Datenschutz</a>
		<a href="<?php echo esc_url( home_url( '/agb/' ) ); ?>" target="_blank" rel="noopener">AGB</a>
	</footer>
	<style>
	.tl-legal { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; margin: 12px auto 40px; font-size: 12.5px; }
	.tl-legal a { color: #5C6660; text-decoration: none; padding: 12px 8px; }
	.tl-legal a:hover, .tl-legal a:focus-visible { color: #4279D1; text-decoration: underline; text-underline-offset: 2px; }
	</style>
	<?php wp_footer(); ?>
</body>
</html>
