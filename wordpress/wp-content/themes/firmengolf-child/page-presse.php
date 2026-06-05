<?php
/**
 * Template: Presse & Newsroom
 * Ported from React Press.jsx — generic classes + real copy.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$img = static fn( string $n ): string => fge_get_placeholder_image_url( $n );
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<section class="mk-section" aria-label="Presse" style="padding-top:64px;">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Newsroom · Presse</div>
		<h1 class="mk-h2" style="font-size:var(--fs-display-md);">Golf, neu <em class="mk-italic">erzählt</em>.</h1>
		<p class="mk-sub" style="max-width:680px;">
			Material, Zahlen und O-Töne für eure Berichterstattung über Firmengolf — Logos, Fakten,
			Pressemitteilungen und ein direkter Draht zu uns.
		</p>
	</div>
	<div class="trust-strip" style="margin-top:24px;">
		<div class="trust-inner">
			<?php
			$facts = [
				[ '180+',   'Partnerplätze in Deutschland' ],
				[ '2.400',  'Mitarbeitende im Benefit-Programm' ],
				[ '2024',   'gegründet in München' ],
				[ '4,9 ★',  'Ø Bewertung über alle Events' ],
			];
			foreach ( $facts as $f ) : ?>
				<div class="trust-cell"><div class="trust-t"><?php echo esc_html( $f[0] ); ?></div><div class="trust-b"><?php echo esc_html( $f[1] ); ?></div></div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php /* Boilerplate */ ?>
<section class="mk-section" aria-label="Unternehmen in Kürze">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Das Unternehmen in Kürze</div>
		<h2 class="mk-h2">Boilerplate</h2>
	</div>
	<p class="mk-sub" style="max-width:var(--width-prose);">
		Firmengolf macht Golf für Unternehmen zugänglich — als Firmenevent, als Offsite-Location und als
		wiederkehrenden Mitarbeiter-Benefit. Über eine kuratierte Plattform buchen Firmen Teamevents, Turniere,
		Schnupperkurse und individuelle Veranstaltungen auf über 180 Partnerplätzen in ganz Deutschland: eine
		Anfrage, ein Ansprechpartner, eine Rechnung. Gegründet 2024 in München, verfolgt Firmengolf ein klares
		Ziel — Golf nicht als exklusives Statussymbol, sondern als offenen, gesunden Ausgleich, der Teams aus dem
		Büro und in Bewegung bringt.
	</p>
</section>

<?php /* Press releases */ ?>
<section class="mk-section" aria-label="Pressemitteilungen">
	<div class="mk-section-head"><div class="mk-eyebrow">Pressemitteilungen</div><h2 class="mk-h2">Aktuelles</h2></div>
	<ul class="faq-list">
		<?php
		$releases = [
			[ '22. Mai 2026',     'Wachstum', 'Firmengolf überschreitet 180 Partnerplätze in Deutschland', 'Mit 24 neuen Clubs allein in diesem Frühjahr wächst das Netz buchbarer Golfplätze weiter.' ],
			[ '8. April 2026',    'Benefit',  '2.400 Mitarbeitende nutzen Golf inzwischen als Corporate Benefit', 'Jahresbilanz: Immer mehr Unternehmen integrieren den steuerfreien Sachbezug.' ],
			[ '3. März 2026',     'Studie',   'Neue Auswertung: Wie ein Tag im Grünen Teams nachhaltig verbindet', 'Gemeinsam mit einer Sporthochschule hat Firmengolf untersucht, was bleibt.' ],
			[ '14. Januar 2026',  'Produkt',  'Neuer Budget-Rechner macht Eventplanung in Minuten transparent', 'Unternehmen sehen ab sofort in Echtzeit einen realistischen Richtwert.' ],
		];
		foreach ( $releases as $r ) : ?>
			<li class="faq-item">
				<div class="faq-q" style="cursor:default;">
					<span><span class="mk-eyebrow" style="color:var(--fairway-700)"><?php echo esc_html( $r[0] . ' · ' . $r[1] ); ?></span><br><?php echo esc_html( $r[2] ); ?></span>
				</div>
				<div class="faq-a" style="max-height:none;padding:0 0 18px;"><?php echo esc_html( $r[3] ); ?></div>
			</li>
		<?php endforeach; ?>
	</ul>
</section>

<?php /* Coverage */ ?>
<section class="mk-section" aria-label="Pressestimmen">
	<div class="mk-section-head"><div class="mk-eyebrow">Pressestimmen</div><h2 class="mk-h2">Was über uns geschrieben wird</h2></div>
	<div class="mk-steps-grid">
		<?php
		$cov = [
			[ 'HR Today', 'Firmengolf nimmt dem Golfsport das Elitäre — und macht ihn zum überraschend nahbaren Team-Benefit.' ],
			[ 'Eventbranche.de', 'Eine Anfrage, ein Ansprechpartner, eine Rechnung: Die Plattform räumt mit der Komplexität von Firmenevents auf.' ],
			[ 'Gründerszene', 'Aus einer simplen Idee — Golf für Firmen zugänglich machen — ist ein bundesweites Partnernetz geworden.' ],
		];
		foreach ( $cov as $c ) : ?>
			<div class="mk-step">
				<p class="mk-step-b" style="font-family:var(--font-serif);font-style:italic;font-size:18px;color:var(--ink-800);">„<?php echo esc_html( $c[1] ); ?>"</p>
				<div class="mk-tm-role" style="margin-top:10px;"><?php echo esc_html( $c[0] ); ?> · 2026</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<section class="mk-cta" aria-label="Pressekontakt">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Pressekontakt</div>
		<h2 class="mk-cta-h">Marie Albers · Presse & Kommunikation</h2>
		<p class="mk-cta-sub">Firmengolf · Visionpunch UG, München</p>
		<div class="mk-cta-ctas">
			<a class="mk-cta-mail" href="mailto:presse@firmengolf.de">presse@firmengolf.de</a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
