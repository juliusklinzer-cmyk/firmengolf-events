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
			<div class="tl-done">
				<div class="tl-done-ic">
					<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				</div>
				<?php if ( 'accepted' === $offer_status ) : ?>
					<h2>Gebucht, <?php echo esc_html( $first ?: 'super' ); ?>!</h2>
					<p>Euer Event <strong><?php echo esc_html( (string) ( $snap['event_title'] ?? '' ) ); ?></strong> am <strong><?php echo esc_html( (string) ( $snap['date'] ?? '' ) ); ?></strong> ist verbindlich gebucht. Wir kümmern uns um die letzten Details und melden uns.</p>
					<?php
					// Mitgebuchte Zusatzleistungen bestätigen (nur die vom Kunden gewählten).
					$tl_sel    = array_map( 'intval', (array) get_post_meta( $req, '_fge_offer_extras_selected', true ) );
					$tl_booked = array_filter( (array) ( $snap['extras'] ?? [] ), static fn( $x ) => in_array( (int) ( $x['src'] ?? -1 ), $tl_sel, true ) );
					if ( ! empty( $tl_booked ) ) : ?>
					<p style="margin-top:10px;">Mitgebucht: <strong><?php echo esc_html( implode( ', ', array_map( static fn( $x ) => (string) $x['label'], $tl_booked ) ) ); ?></strong></p>
					<?php endif; ?>
				<?php else : ?>
					<h2>Schade, <?php echo esc_html( $first ?: '' ); ?>.</h2>
					<p>Ihr habt das Angebot abgelehnt. Wenn ihr mögt, finden wir gern eine Alternative, antwortet einfach auf die Angebots-Mail oder schreibt uns, wir passen es gern an.</p>
				<?php endif; ?>
			</div>

		<?php // 2) Offenes Angebot → annehmen/ablehnen.
		elseif ( 'pending' === $offer_status && ! empty( $snap ) ) : ?>
			<div class="tl-eyebrow">Angebot <?php echo esc_html( $ref ); ?></div>
			<h1 class="tl-h">Hallo <?php echo esc_html( $first ?: '' ); ?>, hier ist <em>euer Angebot</em>.</h1>
			<p class="tl-lead">Der Termin steht. Schaut es euch an und nehmt es mit einem Klick an.</p>
			<?php if ( $deadline > time() ) : ?>
			<div class="tl-deadline">Wir halten den Termin bis <strong><?php echo esc_html( wp_date( 'D, d.m.Y', $deadline ) ); ?></strong> für euch. Sagt ihr bis dahin zu, ist er verbindlich gebucht.</div>
			<?php elseif ( $deadline > 0 ) : ?>
			<div class="tl-deadline">Die Reservierungsfrist ist abgelaufen, der Termin ist nicht mehr garantiert. Ihr könnt trotzdem zusagen: wir prüfen dann sofort, ob er noch frei ist, und melden uns umgehend.</div>
			<?php endif; ?>

			<?php
			$tl_extras  = array_values( (array) ( $snap['extras'] ?? [] ) );
			$tl_vatp    = (int) ( $snap['vat_percent'] ?? 19 );
			$tl_pax     = (int) ( $snap['participants'] ?? 0 );
			$tl_is_pp   = 'pro Person' === (string) ( $snap['price_unit'] ?? '' );
			// Netto-Basis des Events (ohne Extras) — gleiche Logik wie fge_offer_totals.
			$tl_base    = $tl_is_pp ? ( $tl_pax > 0 ? (float) ( $snap['price_total'] ?? 0 ) : 0.0 ) : (float) ( $snap['price_gross'] ?? 0 );
			// Startzustand: alle Extras gewählt; JS rechnet bei Abwahl live nach.
			$tl_totals  = function_exists( 'fge_offer_totals' ) ? fge_offer_totals( $snap ) : [ 'net' => $tl_base, 'ca' => $tl_is_pp && $tl_base > 0 ];
			$tl_net     = (float) $tl_totals['net'];
			$tl_ca      = $tl_totals['ca'] ? 'ca. ' : ''; // p.P.-Summen hängen an der Teilnehmerzahl (Kern-Audit M6)
			?>
			<div class="tl-summary">
				<div class="tl-sum-co"><?php echo esc_html( (string) ( $snap['event_title'] ?? '' ) ); ?></div>
				<div class="tl-sum-grid">
					<div class="tl-sum-item"><div class="k">Termin</div><div class="v"><?php echo esc_html( (string) ( $snap['date'] ?? '' ) ); ?></div></div>
					<?php if ( '' !== (string) ( $snap['location'] ?? '' ) ) : ?><div class="tl-sum-item"><div class="k">Ort</div><div class="v"><?php echo esc_html( (string) $snap['location'] ); ?></div></div><?php endif; ?>
					<?php if ( $tl_pax > 0 ) : ?><div class="tl-sum-item"><div class="k">Teilnehmer</div><div class="v"><?php echo (int) $tl_pax; ?> Personen</div></div><?php endif; ?>
					<?php if ( (float) ( $snap['price_gross'] ?? 0 ) > 0 || empty( $tl_extras ) ) : ?>
					<div class="tl-sum-item"><div class="k"><?php echo empty( $tl_extras ) ? 'Preis (netto)' : 'Eventpreis (netto)'; ?></div><div class="v"><?php
						echo esc_html( function_exists( 'fge_offer_price_text' ) ? fge_offer_price_text( $snap ) : '' );
					?></div></div>
					<?php endif; ?>
					<?php if ( $tl_net > 0 ) : ?>
					<?php if ( ! empty( $tl_extras ) ) : ?>
					<div class="tl-sum-item"><div class="k">Summe (netto)</div><div class="v"><span id="tl-x-net"><?php echo esc_html( $tl_ca . number_format_i18n( round( $tl_net ), 0 ) ); ?> €</span></div></div>
					<?php endif; ?>
					<div class="tl-sum-item"><div class="k">zzgl. <?php echo (int) $tl_vatp; ?> % MwSt.</div><div class="v"><span id="tl-x-vat"><?php echo esc_html( $tl_ca . number_format_i18n( round( $tl_net * $tl_vatp / 100 ), 0 ) ); ?> €</span></div></div>
					<div class="tl-sum-item"><div class="k">Endpreis inkl. MwSt.</div><div class="v"><strong><span id="tl-x-total"><?php echo esc_html( $tl_ca . number_format_i18n( round( $tl_net * ( 1 + $tl_vatp / 100 ) ), 0 ) ); ?> €</span></strong></div></div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $snap['includes'] ) ) : ?>
			<div class="tl-section-label">Das ist dabei</div>
			<ul class="tl-list"><?php foreach ( (array) $snap['includes'] as $i ) : ?><li><?php echo esc_html( $i ); ?></li><?php endforeach; ?></ul>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( fge_offer_link( $req ) ); ?>" class="tl-offer-actions" id="tl-offer-form">
				<input type="hidden" name="fge_offer_token" value="<?php echo esc_attr( $token ); ?>">
				<input type="hidden" name="fge_offer_nonce" value="<?php echo esc_attr( $nonce ); ?>">

				<?php if ( ! empty( $tl_extras ) ) : ?>
				<div class="tl-section-label">Eure Zusatzleistungen</div>
				<p class="tl-note" style="margin:0 0 8px;">In der Summe oben enthalten. Was ihr nicht braucht, wählt ihr einfach ab, der Preis passt sich sofort an.</p>
				<ul class="tl-list" style="list-style:none;padding-left:0;">
					<?php foreach ( $tl_extras as $x ) :
						$x_pp  = 'person' === (string) ( $x['basis'] ?? '' );
						$x_add = $x_pp ? ( $tl_pax > 0 ? (float) $x['price'] * $tl_pax : 0.0 ) : (float) $x['price'];
						?>
					<li style="margin-bottom:8px;">
						<label style="display:flex;gap:9px;align-items:flex-start;cursor:pointer;">
							<input type="checkbox" class="tl-x-pick" name="fge_offer_extras[]" value="<?php echo (int) ( $x['src'] ?? 0 ); ?>" checked
							       data-add="<?php echo esc_attr( (string) $x_add ); ?>" data-pp="<?php echo $x_pp && $tl_pax > 0 ? '1' : '0'; ?>" style="margin-top:3px;flex:0 0 auto;">
							<span><?php echo esc_html( (string) ( $x['label'] ?? '' ) ); ?>
								<strong style="white-space:nowrap;"><?php echo esc_html( function_exists( 'fge_offer_extra_price_text' ) ? fge_offer_extra_price_text( $x ) : '' ); ?></strong>
								<?php if ( $x_pp && $tl_pax > 0 ) : ?><span class="tl-src">ca. <?php echo esc_html( number_format_i18n( round( $x_add ), 0 ) ); ?> € bei <?php echo (int) $tl_pax; ?> Personen</span><?php endif; ?>
							</span>
						</label>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>

				<?php if ( ! empty( $snap['wishes_platz'] ) || ! empty( $snap['wishes_firmengolf'] ) ) : ?>
				<div class="tl-section-label">Eure Zusatzwünsche</div>
				<p class="tl-note" style="margin:0 0 8px;">Auf Wunsch organisiert, wird separat ausgewiesen und ist noch nicht im oben genannten Preis enthalten.</p>
				<ul class="tl-list">
					<?php foreach ( (array) ( $snap['wishes_platz'] ?? [] ) as $i ) : ?><li><?php echo esc_html( $i ); ?> <span class="tl-src">am Platz</span></li><?php endforeach; ?>
					<?php foreach ( (array) ( $snap['wishes_firmengolf'] ?? [] ) as $i ) : ?><li><?php echo esc_html( $i ); ?> <span class="tl-src">durch Firmengolf</span></li><?php endforeach; ?>
				</ul>
				<?php endif; ?>

				<?php if ( isset( $_GET['agb'] ) ) : ?>
				<p class="tl-note" style="color:#B4332B;margin:0 0 10px;">Bitte bestätige die AGB, um verbindlich zu buchen.</p>
				<?php endif; ?>
				<?php if ( isset( $_GET['session'] ) ) : ?>
				<p class="tl-note" style="color:#B4332B;margin:0 0 10px;">Die Sitzung war abgelaufen. Bitte bestätige deine Auswahl noch einmal.</p>
				<?php endif; ?>
				<p class="tl-note" style="margin:0 0 14px;">Alle Preise verstehen sich zzgl. der gesetzlichen Umsatzsteuer. Es gelten unsere <a href="<?php echo esc_url( home_url( '/agb/' ) ); ?>" target="_blank" rel="noopener">AGB</a> inkl. der dort genannten Storno- und Zahlungsbedingungen.</p>
				<label style="display:flex;gap:9px;align-items:flex-start;margin:0 0 14px;font-size:13px;line-height:1.45;">
					<input type="checkbox" id="tl-agb" name="fge_offer_agb" value="1" style="margin-top:3px;flex:0 0 auto;">
					<span>Ich akzeptiere die <a href="<?php echo esc_url( home_url( '/agb/' ) ); ?>" target="_blank" rel="noopener">AGB</a> und buche mit „Angebot annehmen" verbindlich.</span>
				</label>
				<div style="display:flex;gap:10px;flex-wrap:wrap;">
					<button type="submit" name="fge_offer_action" value="accept" id="tl-accept" class="tl-btn yes" style="padding:13px 26px;opacity:.5;" disabled>Angebot annehmen</button>
					<button type="submit" name="fge_offer_action" value="decline" class="tl-btn no" style="padding:13px 26px;" onclick="return confirm('Angebot wirklich absagen? Der reservierte Termin wird dann freigegeben.');">Leider absagen</button>
					<button type="button" id="tl-toggle-q" class="tl-btn" style="padding:13px 26px;background:#ECECE6;color:#333;border:none;border-radius:999px;cursor:pointer;">Rückfrage / Änderung</button>
				</div>
				<div id="tl-q-wrap" style="display:none;margin-top:12px;">
					<textarea name="fge_offer_message" rows="3" placeholder="Was möchtet ihr ändern oder wissen?" style="width:100%;padding:10px 12px;border:1px solid #d8d8d2;border-radius:8px;font:inherit;box-sizing:border-box;"></textarea>
					<button type="submit" name="fge_offer_action" value="request" class="tl-btn" style="margin-top:8px;padding:11px 22px;background:#4279D1;color:#fff;border:none;border-radius:999px;cursor:pointer;">Rückfrage senden</button>
				</div>
			</form>
			<p class="tl-note">Mit „Angebot annehmen" bucht ihr verbindlich. Lieber erst etwas klären? Nutzt „Rückfrage / Änderung", euer Termin bleibt reserviert.</p>

			<?php if ( '' !== (string) ( $snap['contact_phone'] ?? '' ) || '' !== (string) ( $snap['contact_email'] ?? '' ) ) : ?>
			<div class="tl-contact" style="margin-top:22px;padding-top:16px;border-top:1px solid #ece9e2;font-size:13px;color:#555;">
				Euer Ansprechpartner: <strong><?php echo esc_html( (string) ( $snap['contact_name'] ?? 'Firmengolf' ) ); ?></strong><?php
				if ( '' !== (string) ( $snap['contact_phone'] ?? '' ) ) {
					echo ' · <a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', (string) $snap['contact_phone'] ) ) . '" style="color:#4279D1;">' . esc_html( (string) $snap['contact_phone'] ) . '</a>';
				}
				if ( '' !== (string) ( $snap['contact_email'] ?? '' ) ) {
					echo ' · <a href="mailto:' . esc_attr( (string) $snap['contact_email'] ) . '" style="color:#4279D1;">' . esc_html( (string) $snap['contact_email'] ) . '</a>';
				}
				?>
			</div>
			<?php endif; ?>
			<script>
			(function(){
				var agb = document.getElementById('tl-agb'), acc = document.getElementById('tl-accept');
				if (agb && acc) { agb.addEventListener('change', function(){ acc.disabled = !agb.checked; acc.style.opacity = agb.checked ? '1' : '.5'; }); }
				var tq = document.getElementById('tl-toggle-q'), qw = document.getElementById('tl-q-wrap');
				if (tq && qw) { tq.addEventListener('click', function(){ qw.style.display = (qw.style.display === 'none' ? 'block' : 'none'); }); }

				// Zusatzleistungen abwählbar: Summe, MwSt. und Endpreis live nachrechnen.
				var base = <?php echo wp_json_encode( round( $tl_base, 2 ) ); ?>,
				    baseCa = <?php echo $tl_is_pp && $tl_base > 0 ? 'true' : 'false'; ?>,
				    vat = <?php echo (int) $tl_vatp; ?>,
				    picks = document.querySelectorAll('.tl-x-pick');
				function fmt(n){ return Math.round(n).toLocaleString('de-DE') + ' €'; }
				function recalc(){
					var net = base, ca = baseCa;
					picks.forEach(function(p){
						if (!p.checked) return;
						net += parseFloat(p.dataset.add || '0') || 0;
						if (p.dataset.pp === '1') ca = true;
					});
					var pre = ca ? 'ca. ' : '';
					var elN = document.getElementById('tl-x-net'), elV = document.getElementById('tl-x-vat'), elT = document.getElementById('tl-x-total');
					if (elN) elN.textContent = pre + fmt(net);
					if (elV) elV.textContent = pre + fmt(net * vat / 100);
					if (elT) elT.textContent = pre + fmt(net * (1 + vat / 100));
				}
				picks.forEach(function(p){ p.addEventListener('change', recalc); });
			})();
			</script>

		<?php // 3) Read-only Status (noch kein Angebot oder schon abgeschlossen).
		else : ?>
			<div class="tl-eyebrow">Anfrage <?php echo esc_html( $ref ); ?></div>
			<h1 class="tl-h">Hallo <?php echo esc_html( $first ?: '' ); ?>, hier ist <em>euer aktueller Stand</em>.</h1>
			<div class="tl-status-card"><?php echo esc_html( $status_msg( $status ) ); ?></div>
			<?php if ( 'accepted' === $offer_status && ! empty( $snap ) ) :
				$tl_sel    = array_map( 'intval', (array) get_post_meta( $req, '_fge_offer_extras_selected', true ) );
				$tl_booked = array_filter( (array) ( $snap['extras'] ?? [] ), static fn( $x ) => in_array( (int) ( $x['src'] ?? -1 ), $tl_sel, true ) );
				?>
			<div class="tl-summary" style="margin-top:18px;">
				<div class="tl-sum-co"><?php echo esc_html( (string) ( $snap['event_title'] ?? '' ) ); ?></div>
				<div class="tl-sum-grid">
					<div class="tl-sum-item"><div class="k">Termin</div><div class="v"><?php echo esc_html( (string) ( $snap['date'] ?? '' ) ); ?></div></div>
					<div class="tl-sum-item"><div class="k">Status</div><div class="v">Gebucht</div></div>
					<?php if ( ! empty( $tl_booked ) ) : ?>
					<div class="tl-sum-item"><div class="k">Zusatzleistungen</div><div class="v"><?php echo esc_html( implode( ', ', array_map( static fn( $x ) => (string) $x['label'], $tl_booked ) ) ); ?></div></div>
					<?php endif; ?>
				</div>
			</div>
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
	.tl-legal { display: flex; flex-wrap: wrap; gap: 18px; justify-content: center; margin: 32px auto 40px; font-size: 12.5px; }
	.tl-legal a { color: #5C6660; text-decoration: none; }
	.tl-legal a:hover, .tl-legal a:focus-visible { color: #4279D1; text-decoration: underline; text-underline-offset: 2px; }
	</style>
	<?php wp_footer(); ?>
</body>
</html>
