<?php
/**
 * Template: Karriere
 * Ported from React Career.jsx — generic classes + real copy.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$arrow = '<span class="fg-arrow"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>';
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<section class="mk-section" aria-label="Karriere" style="padding-top:64px;">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Karriere bei Firmengolf</div>
		<h1 class="mk-h2" style="font-size:var(--fs-display-md);max-width:780px;">Komm ins Team, das andere <em class="mk-italic">rausbringt</em>.</h1>
		<p class="mk-sub" style="max-width:680px;">
			Wir machen Golf für Unternehmen zugänglich — und suchen Leute, die Lust haben, daraus das beste Stück
			Arbeitswoche zu machen. Für sich und für tausende Teams da draußen.
		</p>
	</div>
</section>

<?php /* Values */ ?>
<section class="mk-section" aria-label="Werte">
	<div class="mk-section-head"><div class="mk-eyebrow">Wie wir arbeiten</div><h2 class="mk-h2">Vier Dinge, die uns wichtig sind.</h2></div>
	<div class="mk-steps-grid">
		<?php
		$values = [
			[ 'Unbeschwert',        'Wir nehmen die Arbeit ernst, uns selbst nicht zu sehr. Gute Laune ist hier kein Nice-to-have.' ],
			[ 'Eigenverantwortung', 'Kurze Wege, echtes Vertrauen. Du gestaltest deinen Bereich — niemand schaut dir über die Schulter.' ],
			[ 'Nahbar & echt',      'Kein Konzern-Sprech, keine Politik. Wir sagen, was wir denken — freundlich und direkt.' ],
			[ 'Wirkung',            'Wir bringen tausende Menschen raus in Bewegung. Was du baust, spürt man am nächsten Wochenende.' ],
		];
		foreach ( $values as $v ) : ?>
			<div class="mk-step"><h3 class="mk-step-t"><?php echo esc_html( $v[0] ); ?></h3><p class="mk-step-b"><?php echo esc_html( $v[1] ); ?></p></div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* Perks */ ?>
<section class="mk-section" aria-label="Benefits">
	<div class="mk-section-head"><div class="mk-eyebrow">Was wir bieten</div><h2 class="mk-h2">Benefits, die wir selbst nutzen.</h2></div>
	<div class="home-occasions">
		<?php
		$perks = [
			[ 'Golf als Benefit',  'Schläger, Coaching und freie Runden auf unseren Partnerplätzen.' ],
			[ '30 Tage Urlaub',    'Plus den Tag nach dem Sommerfest frei — versprochen.' ],
			[ 'Hybrid & flexibel', 'München-Office mit Terrasse, oder von zuhause. Du entscheidest.' ],
			[ 'Lernbudget',        '1.500 € im Jahr für Kurse, Konferenzen, Bücher — oder die Platzreife.' ],
			[ 'Mental Health',     'Zugang zu Coaching & Beratung, weil draußen sein nicht alles heilt.' ],
			[ 'Team-Tage draußen', 'Wir testen, was wir verkaufen — regelmäßig gemeinsam auf dem Platz.' ],
		];
		foreach ( $perks as $p ) : ?>
			<div class="home-occ" style="cursor:default;">
				<div class="home-occ-body"><h3 class="home-occ-t" style="font-size:18px;"><?php echo esc_html( $p[0] ); ?></h3><p class="home-occ-b"><?php echo esc_html( $p[1] ); ?></p></div>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* Open positions */ ?>
<section class="mk-section" aria-label="Offene Stellen">
	<div class="mk-section-head"><div class="mk-eyebrow">Offene Stellen</div><h2 class="mk-h2">Wir suchen dich.</h2></div>
	<ul class="faq-list">
		<?php
		$jobs = [
			[ 'Senior Frontend Engineer (m/w/d)',         'Engineering · Vollzeit · München / Remote' ],
			[ 'Backend Engineer — Buchungssystem (m/w/d)', 'Engineering · Vollzeit · München / Remote' ],
			[ 'Partner Manager Golfplätze (m/w/d)',        'Partnerships · Vollzeit · Hybrid · Süddeutschland' ],
			[ 'Event Coordinator (m/w/d)',                 'Events · Vollzeit · München' ],
			[ 'Performance Marketing Manager (m/w/d)',     'Marketing · Vollzeit · München / Remote' ],
			[ 'Content & Social Lead (m/w/d)',             'Marketing · Teilzeit möglich · Remote' ],
			[ 'Customer Success Manager (m/w/d)',          'Operations · Vollzeit · München' ],
			[ 'Werkstudent:in People & Operations (m/w/d)','Operations · Werkstudent · München' ],
		];
		foreach ( $jobs as $j ) : ?>
			<li class="faq-item">
				<a class="faq-q" href="mailto:jobs@visionpunch.de?subject=<?php echo rawurlencode( $j[0] ); ?>" style="text-decoration:none;">
					<span><?php echo esc_html( $j[0] ); ?><br><span class="mk-tm-role"><?php echo esc_html( $j[1] ); ?></span></span>
					<span class="faq-toggle" aria-hidden="true">→</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>

<section class="mk-cta" aria-label="Initiativbewerbung">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Nichts Passendes dabei?</div>
		<h2 class="mk-cta-h">Erzähl uns trotzdem <em class="mk-italic">von dir</em>.</h2>
		<p class="mk-cta-sub">Wir wachsen schnell — und gute Leute finden bei uns fast immer einen Platz.</p>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="mailto:jobs@visionpunch.de" style="background:var(--paper-100);color:var(--fairway-900)">
				Initiativ bewerben <?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
