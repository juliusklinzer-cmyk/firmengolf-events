<?php
/**
 * Template: Über uns
 * Ported pixel-for-pixel from React About.jsx — bespoke about-* layout.
 * Sections: Hero · Founder video (poster placeholder) · Founder letter ·
 *           Philosophy (dark slab) · Values · Closing CTA.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$img         = static fn( string $n ): string => fge_get_placeholder_image_url( $n );
$url_kontakt = ( $p = get_page_by_path( 'kontakt' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/kontakt/' );
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'ueber-uns' ] ); ?>

<?php /* ===== Hero, vision statement ===== */ ?>
<section class="about-hero" aria-label="Über uns">
	<div class="about-hero-inner">
		<div class="mk-eyebrow">Über uns</div>
		<h1 class="about-hero-h">
			Golf ist für jeden, der mal <em class="mk-italic" style="color:var(--clay-500)">raus</em> will.
		</h1>
		<p class="about-hero-sub">
			Ich habe Firmengolf gegründet, weil Golf einfach guttut: raus an die frische Luft,
			rein in Bewegung, mitten ins Gespräch. Ein paar Stunden, die ein Team enger
			zusammenbringen als zehn Meetings. Und dabei macht es richtig Spaß.
		</p>
	</div>
</section>

<?php /* ===== Founder video, centerpiece (poster placeholder) ===== */ ?>
<section class="about-video-section" aria-label="In meinen Worten">
	<div class="about-video-head">
		<div class="mk-eyebrow" style="color:var(--fairway-700)">In meinen Worten</div>
	</div>
	<div class="about-video-wrap">
		<div class="about-video">
			<?php
			$fge_video_url    = plugins_url( 'assets/video/gruender.mp4', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' );
			$fge_video_poster = $img( 'gruender-video-poster.jpg' );
			?>
			<video class="about-video-el" preload="none" playsinline controls poster="<?php echo esc_url( $fge_video_poster ); ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;border:0;background:var(--ink-900);">
				<source src="<?php echo esc_url( $fge_video_url ); ?>" type="video/mp4">
			</video>
			<button type="button" class="about-video-ph" aria-label="Video abspielen: Eine Minute mit dem Gründer">
				<div class="about-video-poster" style="background-image:url('<?php echo esc_url( $fge_video_poster ); ?>')"></div>
				<div class="about-video-scrim"></div>
				<div class="about-video-center">
					<span class="about-play">
						<svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
					</span>
					<div class="about-video-label">Eine Minute mit dem Gründer</div>
				</div>
				<span class="about-video-badge">Video</span>
			</button>
			<script>
			(function(){
				var ph  = document.currentScript.previousElementSibling;
				var box = ph ? ph.parentNode : null;
				var vid = box ? box.querySelector('.about-video-el') : null;
				if ( ! ph || ! vid ) { return; }
				ph.addEventListener('click', function(){
					ph.style.display = 'none';
					vid.style.display = 'block';
					if ( vid.play ) { vid.play(); }
				});
			})();
			</script>
		</div>
		<div class="about-video-caption">
			<span class="about-video-cap-dot"></span>
			<span>Eine Minute mit Julius, dem Gründer von Firmengolf.</span>
		</div>
	</div>
</section>

<?php /* ===== Founder letter ===== */ ?>
<section class="about-letter" aria-label="Vom Gründer">
	<div class="about-letter-grid">
		<div class="about-letter-aside">
			<div class="about-portrait" role="img" aria-label="Julius Klinzer, Gründer von Firmengolf" style="background-image:url('<?php echo esc_url( $img( 'gruender-julius-klinzer.jpg' ) ); ?>')"></div>
			<div class="about-letter-id">
				<div class="about-letter-name">Julius Klinzer</div>
				<div class="about-letter-role">Gründer · Firmengolf</div>
				<a class="about-linkedin" href="https://www.linkedin.com/in/julius-klinzer-a724b6133/" target="_blank" rel="noopener noreferrer">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
						<path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.35V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.55V9h3.57v11.45zM22.22 0H1.77C.8 0 0 .78 0 1.74v20.52C0 23.22.8 24 1.77 24h20.45c.98 0 1.78-.78 1.78-1.74V1.74C24 .78 23.2 0 22.22 0z"/>
					</svg>
					LinkedIn
				</a>
			</div>
		</div>

		<div class="about-letter-body">
			<div class="mk-eyebrow">Persönlich</div>
			<h2 class="about-letter-h">
				Lange dachte ich: Golf spiele ich, wenn ich mal <em class="mk-italic">alt</em> bin.
			</h2>
			<p class="about-p">
				Dann machten mein Vater und mein Bruder ihre Platzreife und sprachen von
				nichts anderem mehr. Ich saß daneben und dachte: Wie kann man sich von so einem
				Spiel derart anstecken lassen? Wenn ich das mal spiele, zeige ich euch, wie's geht.
			</p>
			<p class="about-p">
				Tja. Als ich den Schläger dann in meiner eigenen Platzreife in der Hand hielt,
				war alles klar, jetzt hatte es mich auch erwischt. Golf holt dich raus aus dem
				Alltag. Ein guter Schlag macht zehn schlechte wett. Und sich aufzuregen bringt
				nichts: Der nächste Schlag wird davon kein Stück besser.
			</p>
			<p class="about-p">
				Man lernt so viel nebenbei, das sich aufs echte Leben übertragen lässt. Gleichzeitig
				bist du draußen, kannst den Kopf abschalten und triffst großartige, interessante
				Menschen. Vom Bodenleger bis zum Vorstand ist alles dabei.
			</p>
			<p class="about-p">
				Genau das will ich mit Firmengolf zugänglich machen. Nicht als Erlebnis für wenige,
				sondern für alle. Denn Golf ist für jeden da.
			</p>
			<div class="about-signature">Julius Klinzer</div>
		</div>
	</div>
</section>

<?php /* ===== Wie wir sind, ein dunkler 3er-Block (Philosophie + Werte zusammengeführt, Julius 2026-07-07) ===== */ ?>
<section class="about-philo" aria-label="Wie wir sind">
	<div class="about-philo-inner">
		<div class="about-philo-head">
			<div class="mk-eyebrow" style="color:var(--fairway-300)">Wie wir sind</div>
			<h2 class="about-philo-h">
				Drei Dinge, auf die du dich bei uns <em class="mk-italic">verlassen</em> kannst.
			</h2>
		</div>
		<div class="about-philo-grid">
			<?php
			$pillars = [
				[ 'Unbeschwert',  'Wir haben Spaß und feiern ihn nicht. Kein VIP, kein „world-class", kein Druck. Einfach ein guter Tag draußen.' ],
				[ 'Inspirierend', 'Wir verkaufen kein Produkt, wir verkaufen ein Gefühl: Bewegung, Natur, Konzentration und gemeinsame Zeit.' ],
				[ 'Mitfühlend',   'Direkt, persönlich, niemals belehrend. Du bekommst immer einen echten Menschen ans Telefon, kein Ticketsystem.' ],
			];
			foreach ( $pillars as $p ) : ?>
				<div class="about-philo-card">
					<div class="about-philo-n"><?php echo esc_html( $p[0] ); ?></div>
					<p><?php echo esc_html( $p[1] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php /* ===== Wir sind neu, euer Feedback (Julius: vor dem CTA, 2026-07-07) ===== */ ?>
<section class="about-new" aria-label="Wir sind neu, euer Feedback zählt">
	<div class="about-new-grid">
		<div class="about-new-photo" role="img" aria-label="Greenkeeper mäht das Puttinggrün" style="background-image:url('<?php echo esc_url( $img( 'pool/greenkeeping-maeher.jpg' ) ); ?>')"></div>
		<div class="about-new-text">
			<div class="mk-eyebrow">In eigener Sache</div>
			<h2 class="about-new-h">Wir sind neu und arbeiten jeden Tag am Platz.</h2>
			<p>
				Firmen für unseren Sport zu begeistern ist für uns Neuland. Genau deshalb stecken
				wir jeden Tag Arbeit in die neue Firmengolf-Events-Seite, so wie ein Greenkeeper
				jeden Morgen an seinem Grün feilt.
			</p>
			<p>
				Umso mehr zählt dein Blick von außen: Stimmt ein Text nicht, passt ein Angebot
				nicht zu dir, oder gefällt dir etwas richtig gut? Sag es uns. Kritik und Lob
				nehmen wir beide dankend an.
			</p>
			<div class="about-new-ctas">
				<a class="fg-btn-cta" href="mailto:<?php echo esc_attr( fge_company()['email_general'] ?? 'hallo@firmengolf-events.de' ); ?>?subject=Feedback%20zur%20Firmengolf-Seite">Feedback schicken</a>
				<a class="fg-btn-ghost" href="<?php echo esc_url( $url_kontakt ); ?>">Zum Kontakt</a>
			</div>
		</div>
	</div>
</section>

<?php /* ===== Closing CTA ===== */ ?>
<section class="mk-cta" aria-label="Kontakt">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Lust auf eine Runde mit uns?</div>
		<h2 class="mk-cta-h">Lass uns <em class="mk-italic">kennenlernen</em>.</h2>
		<p class="mk-cta-sub">
			Ob Event-Anfrage, Idee oder einfach eine Frage, schreib mir. Ich antworte persönlich.
		</p>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $url_kontakt ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">
				Kontakt aufnehmen <span class="fg-arrow" style="background:var(--fairway-200)"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</a>
			<a class="mk-cta-mail" href="mailto:<?php echo esc_attr( fge_company()['email_general'] ); ?>"><?php echo esc_html( fge_company()['email_general'] ); ?></a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
