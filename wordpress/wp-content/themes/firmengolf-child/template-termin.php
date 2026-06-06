<?php
/**
 * Termin-Landing — persönliche Abstimmungsseite einer Partei (Platz/Pro/Gastro).
 * Aufgerufen über /termin/<token>/ via scheduling.php. Token = Authentifizierung.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$token    = (string) get_query_var( 'fge_termin' );
$resolved = function_exists( 'fge_sched_resolve_token' ) ? fge_sched_resolve_token( $token ) : null;

get_header();
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<section class="mk-section" style="padding-top:56px;max-width:720px;margin:0 auto;">
<?php if ( ! $resolved ) : ?>

	<div class="mk-section-head">
		<div class="mk-eyebrow">Terminabstimmung</div>
		<h1 class="mk-h2">Dieser Link ist ungültig oder abgelaufen.</h1>
		<p class="mk-sub">Bitte wende dich an deinen Firmengolf-Ansprechpartner für einen neuen Link.</p>
	</div>

<?php else :
	$request_id = $resolved['request_id'];
	$party      = $resolved['party'];
	$labels     = fge_sched_parties();
	$party_name = $labels[ $party ] ?? $party;
	$company    = (string) get_post_meta( $request_id, '_fge_company_name', true );
	$event_id   = (int) get_post_meta( $request_id, '_fge_assigned_event_id', true );
	$event_t    = $event_id ? get_the_title( $event_id ) : 'Firmen-Event';
	$pax        = (int) get_post_meta( $request_id, '_fge_expected_participants', true );
	$dates      = array_filter( [
		get_post_meta( $request_id, '_fge_preferred_date_1', true ),
		get_post_meta( $request_id, '_fge_preferred_date_2', true ),
		get_post_meta( $request_id, '_fge_preferred_date_3', true ),
	] );
	$alt_period = (string) get_post_meta( $request_id, '_fge_alternative_period', true );
	$current    = (string) get_post_meta( $request_id, '_fge_sched_' . $party . '_status', true ) ?: 'pending';
	$done       = isset( $_GET['done'] );
?>

	<div class="mk-section-head">
		<div class="mk-eyebrow">Terminabstimmung · <?php echo esc_html( $party_name ); ?></div>
		<h1 class="mk-h2">Passt einer dieser Termine?</h1>
		<p class="mk-sub">
			Anfrage von <strong><?php echo esc_html( $company ?: 'einem Unternehmen' ); ?></strong> für
			<strong><?php echo esc_html( $event_t ); ?></strong><?php echo $pax ? ' · ca. ' . esc_html( (string) $pax ) . ' Personen' : ''; ?>.
			Bitte gib als <strong><?php echo esc_html( $party_name ); ?></strong> kurz Rückmeldung.
		</p>
	</div>

	<?php if ( $done ) : ?>
		<div class="trust-strip" style="margin-top:8px;"><div class="trust-inner"><div class="trust-cell">
			<div class="trust-t">Danke — gespeichert ✓</div>
			<div class="trust-b">Deine Rückmeldung ist bei Firmengolf eingegangen. Du kannst sie unten jederzeit ändern.</div>
		</div></div></div>
	<?php endif; ?>

	<div class="fg-rail-card" style="margin-top:24px;max-width:none;">
		<div>
			<div class="fg-section-eyebrow">Wunschtermine</div>
			<ul style="margin:8px 0 0;padding-left:18px;">
				<?php if ( $dates ) : foreach ( $dates as $d ) : ?>
					<li style="margin:4px 0;"><?php echo esc_html( $d ); ?></li>
				<?php endforeach; else : ?>
					<li><?php echo esc_html( $alt_period ?: 'Zeitraum nach Absprache' ); ?></li>
				<?php endif; ?>
			</ul>
		</div>

		<form method="post" action="<?php echo esc_url( fge_sched_link( $token ) ); ?>" style="margin-top:20px;display:flex;flex-direction:column;gap:14px;">
			<input type="hidden" name="fge_termin_action" value="respond">
			<input type="hidden" name="fge_termin_token" value="<?php echo esc_attr( $token ); ?>">
			<?php wp_nonce_field( 'fge_termin_' . $token, 'fge_termin_nonce' ); ?>

			<div class="fg-field">
				<label class="fg-field-label">Deine Rückmeldung</label>
				<div class="ind-chip-group" role="radiogroup">
					<?php
					$opts = [ 'zugesagt' => 'Passt — zugesagt', 'alternative' => 'Alternative vorschlagen', 'abgesagt' => 'Leider nicht möglich' ];
					foreach ( $opts as $val => $lab ) : ?>
						<label class="ind-pchip<?php echo $current === $val ? ' on' : ''; ?>" style="cursor:pointer;">
							<input type="radio" name="fge_termin_status" value="<?php echo esc_attr( $val ); ?>" <?php checked( $current, $val ); ?> style="margin-right:8px;">
							<?php echo esc_html( $lab ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="fg-field">
				<label class="fg-field-label" for="fg-termin-alt">Alternativer Termin (optional)</label>
				<input class="fg-input" id="fg-termin-alt" name="fge_termin_alt" value="<?php echo esc_attr( get_post_meta( $request_id, '_fge_sched_' . $party . '_alt', true ) ); ?>" placeholder="z.B. KW 30 · vormittags">
			</div>
			<div class="fg-field">
				<label class="fg-field-label" for="fg-termin-note">Anmerkung (optional)</label>
				<textarea class="fg-input" id="fg-termin-note" name="fge_termin_note" rows="3" placeholder="z.B. nur nachmittags, Greenkeeper-Pflege am Vormittag …"><?php echo esc_textarea( get_post_meta( $request_id, '_fge_sched_' . $party . '_note', true ) ); ?></textarea>
			</div>

			<button type="submit" class="fg-btn-brand block">Rückmeldung speichern</button>
		</form>
	</div>

	<p class="mk-sub" style="margin-top:16px;font-size:13px;">Dieser Link ist nur für dich bestimmt. Du kannst deine Rückmeldung jederzeit über denselben Link anpassen.</p>

<?php endif; ?>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
