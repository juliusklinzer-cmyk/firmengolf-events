<?php
/**
 * Template: Über uns
 * Ported from React About.jsx — generic design-system classes + real copy.
 * Bespoke about-* layout = fidelity follow-up (see _design/DELTA-phase2.md).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$img         = static fn( string $n ): string => fge_get_placeholder_image_url( $n );
$url_kontakt = ( $p = get_page_by_path( 'kontakt' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/kontakt/' );
$arrow       = '<span class="fg-arrow"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>';
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'ueber-uns' ] ); ?>

<?php /* Hero */ ?>
<section class="mk-section" aria-label="Über uns" style="padding-top:64px;">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Über uns</div>
		<h1 class="mk-h2" style="font-size:var(--fs-display-md);max-width:760px;">Golf ist für jeden, der mal <em class="mk-italic">raus</em> will.</h1>
		<p class="mk-sub" style="max-width:680px;">
			Ich habe Firmengolf gegründet, weil Golf einfach guttut: raus an die frische Luft, rein in Bewegung,
			mitten ins Gespräch. Ein paar Stunden, die ein Team enger zusammenbringen als zehn Meetings — und
			dabei richtig Spaß machen.
		</p>
	</div>
</section>

<?php /* Founder letter */ ?>
<section class="mk-section mk-testimonial" aria-label="Vom Gründer">
	<div class="mk-testimonial-grid">
		<div class="mk-testimonial-photo" style="background-image:url('<?php echo esc_url( $img( 'gruender-putting-green.jpg' ) ); ?>')"></div>
		<blockquote class="mk-testimonial-body">
			<div class="mk-eyebrow">In meinen Worten</div>
			<p>Warum Golf für <em class="mk-italic">jeden</em> etwas Gutes hat: Es passiert draußen, nimmt jeden mit
			und verbindet. Ein Schläger in der Hand verändert mehr, als man denkt.</p>
			<footer>
				<div>
					<div class="mk-tm-name">Julius Klinzer</div>
					<div class="mk-tm-role">Gründer · Firmengolf</div>
				</div>
			</footer>
		</blockquote>
	</div>
</section>

<?php /* Philosophy */ ?>
<section class="mk-section" aria-label="Unsere Überzeugung">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Woran wir glauben</div>
		<h2 class="mk-h2">Bewegung. Begegnung. Ruhe.</h2>
	</div>
	<div class="mk-steps-grid">
		<?php
		$philo = [
			[ 'Bewegung',  'Eine Runde sind vier, fünf Kilometer an der frischen Luft — ganz ohne das Gefühl, Sport zu machen.' ],
			[ 'Begegnung', 'Vier Stunden ohne Handy, Seite an Seite. Kaum ein Format lässt ein Team so unangestrengt zusammenwachsen.' ],
			[ 'Ruhe',      'Golf zwingt zur Konzentration auf den Moment. Genau das, was im Büroalltag am meisten fehlt — und am meisten heilt.' ],
		];
		foreach ( $philo as $p ) : ?>
			<div class="mk-step">
				<h3 class="mk-step-t"><?php echo esc_html( $p[0] ); ?></h3>
				<p class="mk-step-b"><?php echo esc_html( $p[1] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* Values */ ?>
<section class="mk-section" aria-label="Unsere Werte">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Wie wir sind</div>
		<h2 class="mk-h2">Unbeschwert. Inspirierend. Mitfühlend.</h2>
	</div>
	<div class="mk-steps-grid">
		<?php
		$values = [
			[ 'Unbeschwert',  'Wir haben Spaß und feiern ihn nicht. Kein VIP, kein „world-class", kein Druck — einfach ein guter Tag draußen.' ],
			[ 'Inspirierend', 'Wir verkaufen kein Produkt, wir verkaufen ein Gefühl: Bewegung, Natur, Konzentration und gemeinsame Zeit.' ],
			[ 'Mitfühlend',   'Direkt, persönlich, niemals belehrend. Du bekommst immer einen echten Menschen ans Telefon — kein Ticketsystem.' ],
		];
		foreach ( $values as $v ) : ?>
			<div class="mk-step">
				<h3 class="mk-step-t"><?php echo esc_html( $v[0] ); ?></h3>
				<p class="mk-step-b"><?php echo esc_html( $v[1] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* Closing CTA */ ?>
<section class="mk-cta" aria-label="Kontakt">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Lass uns kennenlernen</div>
		<h2 class="mk-cta-h">Schreib uns — du landest bei einem <em class="mk-italic">echten Menschen</em>.</h2>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $url_kontakt ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">
				Kontakt aufnehmen <?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
			<a class="mk-cta-mail" href="mailto:hallo@firmengolf.de">hallo@firmengolf.de</a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
