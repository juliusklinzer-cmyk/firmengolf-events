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
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

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
				[ '721',    'Golfplätze in Deutschland als Eventlocation' ],
				[ '1.500',  'Golflehrer führen euer Event an' ],
				[ '2024',   'gegründet in München' ],
				[ '< 24 h', 'Antwort auf jede Anfrage' ],
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
		Schnupperkurse und individuelle Veranstaltungen deutschlandweit auf Golfplätzen in ganz Deutschland:
		eine Anfrage, ein Ansprechpartner, eine Rechnung. Gegründet 2024 in München, verfolgt Firmengolf ein klares
		Ziel — Golf nicht als exklusives Statussymbol, sondern als offenen, gesunden Ausgleich, der Teams aus dem
		Büro und in Bewegung bringt.
	</p>
</section>

<?php /* Press releases */ ?>
<section class="mk-section" aria-label="Pressemitteilungen">
	<div class="mk-section-head"><div class="mk-eyebrow">Pressemitteilungen</div><h2 class="mk-h2">Aktuelles</h2></div>
	<ul class="faq-list">
		<?php
		$releases = [];
		if ( empty( $releases ) ) : ?>
			<li class="faq-item"><div class="faq-q" style="cursor:default;"><span>Aktuell sind keine Pressemitteilungen veröffentlicht. Für Presseanfragen, O-Töne und Bildmaterial meldet euch jederzeit über den Pressekontakt — wir antworten kurzfristig.</span></div></li>
		<?php else : foreach ( $releases as $r ) : ?>
			<li class="faq-item">
				<div class="faq-q" style="cursor:default;">
					<span><span class="mk-eyebrow" style="color:var(--fairway-700)"><?php echo esc_html( $r[0] . ' · ' . $r[1] ); ?></span><br><?php echo esc_html( $r[2] ); ?></span>
				</div>
				<div class="faq-a" style="max-height:none;padding:0 0 18px;"><?php echo esc_html( $r[3] ); ?></div>
			</li>
		<?php endforeach; endif; ?>
	</ul>
</section>

<section class="mk-cta" aria-label="Pressekontakt">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Pressekontakt</div>
		<h2 class="mk-cta-h">Julius Klinzer · Presse &amp; Kommunikation</h2>
		<p class="mk-cta-sub">Firmengolf · Visionpunch UG, München</p>
		<div class="mk-cta-ctas">
			<a class="mk-cta-mail" href="mailto:presse@visionpunch.de">presse@visionpunch.de</a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
