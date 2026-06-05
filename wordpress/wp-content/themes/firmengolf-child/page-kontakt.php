<?php
/**
 * Template: Kontakt
 * Ported from React Contact.jsx — generic classes + real copy + echte Kontaktwege.
 * Interaktives Formular = Feinschliff-Follow-up (Channels sind der Kern).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$url_ind = ( $p = get_page_by_path( 'individuelle-events' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/individuelle-events/' );
$arrow   = '<span class="fg-arrow"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>';
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'kontakt' ] ); ?>

<section class="mk-section" aria-label="Kontakt" style="padding-top:64px;">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Kontakt</div>
		<h1 class="mk-h2" style="font-size:var(--fs-display-md);max-width:760px;">Frag uns alles — du landest bei einem <em class="mk-italic">echten Menschen</em>.</h1>
		<p class="mk-sub" style="max-width:680px;">
			Kein Chatbot, kein Ticketsystem, keine Warteschleife ins Nichts. Wähl den Weg, der dir am liebsten ist —
			wir antworten innerhalb eines Werktags, oft schneller.
		</p>
	</div>

	<div class="home-occasions" style="margin-top:32px;">
		<?php
		$channels = [
			[ 'Anrufen',           '+49 89 123 456 78',     'Mo–Fr · 9–18 Uhr',           'tel:+498912345678' ],
			[ 'WhatsApp',          'Kurz schreiben',        'Antwort meist in Minuten',    'https://wa.me/498912345678' ],
			[ 'E-Mail',            'hallo@firmengolf.de',   'Antwort in einem Werktag',    'mailto:hallo@firmengolf.de' ],
			[ 'Rückruf anfordern', 'Wir rufen dich an',     'Du nennst Zeit & Nummer',     'mailto:hallo@firmengolf.de?subject=Rückruf' ],
		];
		foreach ( $channels as $c ) : ?>
			<a class="home-occ" href="<?php echo esc_url( $c[3] ); ?>"<?php echo str_starts_with( $c[3], 'http' ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
				<div class="home-occ-body">
					<div class="mk-eyebrow" style="color:var(--fairway-700)"><?php echo esc_html( $c[0] ); ?></div>
					<h3 class="home-occ-t"><?php echo esc_html( $c[1] ); ?></h3>
					<p class="home-occ-b"><?php echo esc_html( $c[2] ); ?></p>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<?php /* Directory + promises */ ?>
<section class="mk-section" aria-label="Wer ist zuständig">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Direkter Draht</div>
		<h2 class="mk-h2">Wer ist zuständig?</h2>
	</div>
	<div class="mk-steps-grid">
		<?php
		$dir = [
			[ 'Event-Anfragen', 'events@firmengolf.de' ],
			[ 'Partnerplätze',  'partner@firmengolf.de' ],
			[ 'Presse & Medien','presse@firmengolf.de' ],
		];
		foreach ( $dir as $d ) : ?>
			<div class="mk-step">
				<h3 class="mk-step-t"><?php echo esc_html( $d[0] ); ?></h3>
				<p class="mk-step-b"><a href="mailto:<?php echo esc_attr( $d[1] ); ?>"><?php echo esc_html( $d[1] ); ?></a></p>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="trust-strip" aria-label="Versprechen" style="margin-top:8px;">
		<div class="trust-inner">
			<?php
			$promises = [
				[ 'Antwort in einem Werktag', 'Freitagnachmittag–Sonntag: Montag früh.' ],
				[ 'Unverbindlich & kostenlos', 'Erst beraten, dann entscheiden.' ],
				[ 'So, wie\'s dir passt', 'Mail, Telefon, WhatsApp — oder einfache Sprache auf Wunsch.' ],
				[ 'Komm vorbei', 'Visionpunch UG · München. Auf einen Kaffee — kurz vorher anrufen.' ],
			];
			foreach ( $promises as $t ) : ?>
				<div class="trust-cell">
					<div class="trust-t"><?php echo esc_html( $t[0] ); ?></div>
					<div class="trust-b"><?php echo esc_html( $t[1] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="mk-cta" aria-label="Event anfragen">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Konkret etwas vor?</div>
		<h2 class="mk-cta-h">Erzähl uns von eurem <em class="mk-italic">Event</em>.</h2>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $url_ind ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">
				Event anfragen <?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
			<a class="mk-cta-mail" href="mailto:hallo@firmengolf.de">hallo@firmengolf.de</a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
