<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'individuelle-events' ] ); ?>

	<div class="fg-detail">

		<?php /* ── Seiten-Intro ── */ ?>
		<header class="fg-page-intro">
			<p class="fg-section-label">Individuelle Events</p>
			<h1 class="fg-page-intro-title">Wir planen dein Event für dich.</h1>
			<p class="fg-page-intro-sub">Es gibt nichts, was nicht möglich ist. Von der Idee bis zur Durchführung — Firmengolf entwickelt dein Individualevent von Grund auf.</p>
			<p class="fg-page-intro-body">Kein Standard-Paket, das irgendwie passt — sondern ein Format, das zu eurem Team, euren Zielen und eurem Budget maßgeschneidert ist.</p>
		</header>

		<?php /* ── Was wir leisten ── */ ?>
		<section class="fg-how" aria-label="Unsere Leistungen" style="margin-top:0;">
			<div class="fg-how-inner">
				<p class="fg-how-label">Was wir für euch tun</p>
				<h2 class="fg-how-title">Von der Idee bis zur Durchführung</h2>
				<div class="fg-steps">
					<?php
					$leistungen = [
						[ '→', 'Komplettplanung',         'Wir übernehmen die gesamte Planung — Location, Ablauf, Koordination mit dem Golfplatz.' ],
						[ '→', 'Jede Teamgröße',          'Ob 10 oder 200 Personen — wir finden das passende Format und den richtigen Rahmen.' ],
						[ '→', 'Einzigartige Formate',     'Kein Event von der Stange: Wir entwickeln Formate, die zu eurer Marke und euren Zielen passen.' ],
						[ '→', 'Persönliche Beratung',     'Ein direkter Ansprechpartner, der zuhört und mitdenkt — von der Anfrage bis nach dem Event.' ],
					];
					foreach ( $leistungen as $item ) : ?>
						<div>
							<p class="fg-step-num"><?php echo esc_html( $item[0] ); ?></p>
							<h3 class="fg-step-title"><?php echo esc_html( $item[1] ); ?></h3>
							<p class="fg-step-body"><?php echo esc_html( $item[2] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<?php /* ── Anfrageformular ── */ ?>
		<section class="fg-anfrage" id="anfrage" aria-label="Individuelle Eventanfrage">
			<h2 class="fg-anfrage-title" style="margin-bottom:8px;">Jetzt anfragen</h2>
			<p style="font-size:15px;color:var(--ink-500);margin-bottom:24px;">Beschreib uns kurz, was ihr plant — wir melden uns persönlich zurück.</p>
			<?php fge_render_general_anfrage_form(); ?>
		</section>

	</div>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
