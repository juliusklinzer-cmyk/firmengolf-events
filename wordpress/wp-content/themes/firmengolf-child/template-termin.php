<?php
/**
 * Termin-Landing — persönliche Abstimmungsseite einer vote-Person.
 * Aufruf: /termin/<contact-token>/?req=<request-id>  (siehe request-responses.php).
 * Standalone-Seite im .tl-*-Design (kein Theme-Chrome).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$token    = (string) get_query_var( 'fge_termin' );
$req      = isset( $_GET['req'] ) ? absint( $_GET['req'] ) : 0;
$resolved = function_exists( 'fge_rr_resolve_landing' ) ? fge_rr_resolve_landing( $token, $req ) : null;
$done     = isset( $_GET['done'] );

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body class="tl-page">
	<header class="tl-bar">
		<span style="font-family:var(--font-display);font-weight:600;font-size:18px;letter-spacing:-0.02em;color:var(--ink-900);">Firmengolf</span>
		<span class="ctx">Terminabstimmung</span>
	</header>

	<div class="tl-wrap">
	<?php if ( ! $resolved ) : ?>
		<div class="tl-eyebrow">Terminabstimmung</div>
		<h1 class="tl-h">Dieser Link ist <em>ungültig</em> oder abgelaufen.</h1>
		<p class="tl-lead">Bitte wende dich an deinen Firmengolf-Ansprechpartner für einen neuen Link.</p>
	<?php else :
		$contact  = $resolved['contact'];
		$first    = trim( explode( ' ', (string) $contact['name'] )[0] );
		$company  = (string) get_post_meta( $req, '_fge_company_name', true );
		$event_id = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
		$event_t  = $event_id ? get_the_title( $event_id ) : ( (string) get_post_meta( $req, '_fge_event_type', true ) ?: 'Firmen-Event' );
		$pax      = (int) get_post_meta( $req, '_fge_expected_participants', true );
		$budget   = (string) get_post_meta( $req, '_fge_budget_range', true );
		$city     = (string) get_post_meta( $req, '_fge_company_city', true );
		$ref      = fge_request_number( $req );
		$wish     = fge_rr_wish_dates( $req );
		$rows     = fge_rr_get( $req );
		$nonce    = wp_create_nonce( 'fge_termin_' . $token );

		// Live-Stand der Abstimmung: wer hat zu welchem Termin schon zu- oder abgesagt.
		$matrix      = function_exists( 'fge_rr_matrix' ) ? fge_rr_matrix( $req ) : [ 'dates' => [], 'responders' => [] ];
		$m_resp      = $matrix['responders'];
		$m_total     = count( $m_resp );
		$final_idx   = function_exists( 'fge_rr_final_index' ) ? fge_rr_final_index( $req ) : 0;
		$deadline_ts = function_exists( 'fge_request_response_deadline' ) ? fge_request_response_deadline( $req ) : 0;
		$lead_idx = null; $lead_cnt = 0;
		foreach ( $matrix['dates'] as $li => $ld ) {
			if ( (int) $ld['confirmed'] > $lead_cnt ) { $lead_cnt = (int) $ld['confirmed']; $lead_idx = $li; }
		}
		$tl_dots = static function ( array $d ) use ( $m_resp ): string {
			$out = '';
			foreach ( $m_resp as $c ) {
				$st  = $d['responders'][ (int) $c['id'] ]['response'] ?? 'pending';
				$ini = function_exists( 'fge_portal_initials' ) ? fge_portal_initials( (string) ( $c['name'] ?: $c['email'] ) ) : '·';
				$out .= '<span class="tl-vdot ' . esc_attr( $st ) . '" title="' . esc_attr( (string) $c['name'] ) . '">' . esc_html( $ini ) . '</span>';
			}
			return $out;
		};

		if ( $done ) : ?>
		<div class="tl-done">
			<div class="tl-done-ic">
				<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
			</div>
			<h2>Danke, <?php echo esc_html( $first ?: 'dir' ); ?> — gespeichert.</h2>
			<p>Deine Rückmeldung ist da. Sobald alle Beteiligten reagiert haben und ein Termin bestätigt ist, kümmert sich Firmengolf um Angebot und Buchung.</p>
			<?php if ( $m_total > 0 && null !== $lead_idx ) : $ld = $matrix['dates'][ $lead_idx ]; ?>
			<div class="tl-done-stand">
				<?php if ( $final_idx ) : ?>
					Bestätigter Termin: <strong><?php echo esc_html( $matrix['dates'][ $final_idx ]['label'] ?? '' ); ?></strong>.
				<?php else : ?>
					Aktueller Stand: <strong><?php echo esc_html( $ld['label'] ); ?></strong> führt mit <?php echo (int) $ld['confirmed']; ?> von <?php echo (int) $m_total; ?> Zusagen.
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<p style="margin-top:18px;"><a class="tl-btn" href="<?php echo esc_url( fge_termin_contact_link( $req, $contact ) ); ?>">Antwort ändern</a></p>
		</div>
		<?php else : ?>
		<div class="tl-eyebrow">Anfrage <?php echo esc_html( $ref ); ?></div>
		<h1 class="tl-h">Hallo <?php echo esc_html( $first ?: '' ); ?>, passt <em>einer dieser Termine</em>?</h1>
		<p class="tl-lead">Eine Firmenanfrage wartet auf eure Rückmeldung. Sag einfach zu jedem Wunschtermin kurz zu oder ab — dauert keine Minute. Ihr seht direkt, wie die anderen schon abgestimmt haben.</p>
		<?php if ( $deadline_ts && ! $final_idx ) : ?>
		<div class="tl-deadline">Bitte möglichst <strong>bis <?php echo esc_html( wp_date( 'D, d.m.Y', $deadline_ts ) ); ?></strong> zurückmelden, damit der Termin rechtzeitig steht.</div>
		<?php endif; ?>

		<div class="tl-summary">
			<div class="tl-sum-co"><?php echo esc_html( $company ?: 'Ein Unternehmen' ); ?></div>
			<div class="tl-sum-grid">
				<div class="tl-sum-item"><div class="k">Veranstaltung</div><div class="v"><?php echo esc_html( $event_t ); ?></div></div>
				<?php if ( $pax > 0 ) : ?><div class="tl-sum-item"><div class="k">Teilnehmer</div><div class="v">ca. <?php echo esc_html( (string) $pax ); ?> Personen</div></div><?php endif; ?>
				<?php if ( '' !== $city ) : ?><div class="tl-sum-item"><div class="k">Region</div><div class="v"><?php echo esc_html( $city ); ?></div></div><?php endif; ?>
				<?php if ( '' !== $budget ) : ?><div class="tl-sum-item"><div class="k">Budget</div><div class="v"><?php echo esc_html( $budget ); ?></div></div><?php endif; ?>
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( fge_termin_contact_link( $req, $contact ) ); ?>">
			<input type="hidden" name="fge_termin_action" value="respond_dates">
			<input type="hidden" name="fge_termin_token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="fge_req" value="<?php echo (int) $req; ?>">
			<input type="hidden" name="fge_termin_nonce" value="<?php echo esc_attr( $nonce ); ?>">

			<div class="tl-section-label">Wunschtermine — bitte zu jedem zu- oder absagen</div>
			<?php foreach ( $wish as $idx => $label ) :
				$cur = $rows[ $idx . ':' . $contact['id'] ]['response'] ?? 'pending'; ?>
			<div class="tl-date">
				<div class="tl-date-top">
					<div>
						<div class="tl-date-d"><span class="idx"><?php echo (int) $idx; ?></span><?php echo esc_html( $label ); ?></div>
					</div>
					<div class="tl-date-btns">
						<label class="tl-btn yes">
							<input type="radio" name="vote[<?php echo (int) $idx; ?>]" value="confirmed" <?php checked( $cur, 'confirmed' ); ?>>
							Passt
						</label>
						<label class="tl-btn no">
							<input type="radio" name="vote[<?php echo (int) $idx; ?>]" value="declined" <?php checked( $cur, 'declined' ); ?>>
							Geht nicht
						</label>
					</div>
				</div>
				<?php $dd = $matrix['dates'][ $idx ] ?? null; if ( $dd && $m_total > 0 ) : ?>
				<div class="tl-tally">
					<div class="tl-tally-dots"><?php echo $tl_dots( $dd ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<div class="tl-tally-txt"><b><?php echo (int) $dd['confirmed']; ?></b> von <?php echo (int) $m_total; ?> zugesagt<?php if ( (int) $dd['declined'] > 0 ) { echo ', ' . (int) $dd['declined'] . ' dagegen'; } ?></div>
					<?php if ( (int) $final_idx === (int) $idx ) : ?><span class="tl-lead-badge final">Bestätigter Termin</span>
					<?php elseif ( $idx === $lead_idx && $lead_cnt > 0 ) : ?><span class="tl-lead-badge">Wahrscheinlichster Termin</span><?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>

			<div class="tl-alt">
				<div class="tl-section-label">Keiner passt? Schlag einen Alternativtermin vor (optional)</div>
				<input type="text" name="fge_alt_date" value="<?php echo esc_attr( fge_rr_contact_alt( $rows, (int) $contact['id'] ) ); ?>" placeholder="z. B. Fr, 21. Juni 2026 oder KW 30">
				<textarea name="fge_note" rows="3" placeholder="Anmerkung an Firmengolf (optional)"><?php echo esc_textarea( fge_rr_contact_note( $rows, (int) $contact['id'] ) ); ?></textarea>
			</div>

			<div class="tl-submit">
				<button type="submit" class="tl-btn yes" style="padding:12px 24px;">Rückmeldung speichern</button>
			</div>
			<p class="tl-note">Deine Angaben werden ausschließlich zur Bearbeitung dieser Firmenanfrage verwendet (Art. 6 Abs. 1 lit. b/f DSGVO).</p>
		</form>
		<?php endif; ?>
	<?php endif; ?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
