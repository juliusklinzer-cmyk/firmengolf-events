<?php
/**
 * Landingpage Wertschätzungspaket — /wertschaetzung/ (Route: includes/wertschaetzung.php).
 * Bestellformular als 3-Stufen-Popup mit Blur-Backdrop im Onboarding-Stil
 * (Feedback Julius 2026-08-10), Abschluss mit Konfetti-Erfolgsbox.
 * Keine Backend-Speicherung: Versand per Mail (AJAX fge_wz_order).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$packages  = function_exists( 'fge_wz_packages' ) ? fge_wz_packages() : [];
$canonical = home_url( '/wertschaetzung/' );
$seo_title = 'Wertschätzungspaket: Mitarbeiter mit Golf auszeichnen | Firmengolf';
$seo_desc  = 'Belohne besondere Leistungen mit einem Erlebnis, das bleibt: Golf-Grundlagenkurs mit persönlichem Empfang oder die Platzreife als exklusiver Networking-Kurs. Bestellt in 2 Minuten.';

add_filter( 'pre_get_document_title', static fn() => $seo_title );
add_action( 'wp_head', static function () use ( $seo_title, $seo_desc, $canonical ) {
	if ( function_exists( 'fge_render_seo_meta' ) ) {
		fge_render_seo_meta( [ 'title' => $seo_title, 'desc' => $seo_desc, 'url' => $canonical ] );
	}
	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
} );

get_header();
?>
<div class="fge-page fmt-lp" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<?php /* Hero */ ?>
<section class="ev-hero" aria-label="Wertschätzungspaket">
	<div class="ev-hero-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( 'golfer-putt-abendlicht.jpg' ) ); ?>')">
		<div class="ev-hero-scrim" aria-hidden="true"></div>
		<div class="ev-hero-content">
			<div class="ev-hero-eyebrow">Für Unternehmen · Mitarbeiter auszeichnen</div>
			<h1 class="ev-hero-title">Wertschätzung, die bleibt.</h1>
			<p class="ev-hero-sub">Ein Bonus verpufft. Ein Erlebnis bleibt. Zeichne besondere Leistungen mit einem persönlichen Golf-Erlebnis aus, vom ersten Schwung bis zur Platzreife fürs Leben.</p>
			<div class="ev-hero-ctas">
				<a class="fg-btn-brand" href="#pakete">Pakete ansehen</a>
				<button type="button" class="fg-btn-ghost-light" data-wz-open>Jetzt bestellen</button>
			</div>
		</div>
	</div>
</section>

<?php /* Warum */ ?>
<section class="mk-section" aria-label="Warum ein Wertschätzungspaket">
	<div class="mk-section-head">
		<div class="mk-eyebrow">So funktioniert Anerkennung heute</div>
		<h2 class="mk-h2">Ein Geschenk, das etwas <span class="mk-italic">bedeutet</span>.</h2>
		<p class="mk-sub" style="max-width:var(--width-prose);">Die beschenkte Person erhält eine persönliche, namentliche Einladung von Firmengolf, im Auftrag eures Unternehmens und innerhalb von 7 Werktagen. Auf der Geschenkkarte stehen die zum Wohnort passenden Golfplätze, den Termin wählt sie selbst. Kein Gutschein-Code im Intranet, sondern echte Wertschätzung zum Anfassen.</p>
	</div>
</section>

<?php /* Pakete */ ?>
<section class="mk-section" id="pakete" aria-label="Die Pakete">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Zwei Pakete</div>
		<h2 class="mk-h2">Vom Dankeschön bis zur Auszeichnung.</h2>
	</div>
	<div class="wz-packages">
		<?php foreach ( $packages as $key => $p ) : ?>
		<article class="wz-pack<?php echo 'excellence' === $key ? ' wz-pack-hero' : ''; ?>">
			<?php if ( ! empty( $p['flag'] ) ) : ?><span class="wz-pack-flag"><?php echo esc_html( $p['flag'] ); ?></span><?php endif; ?>
			<h3 class="wz-pack-t"><?php echo esc_html( $p['name'] ); ?></h3>
			<p class="wz-pack-sub"><?php echo esc_html( $p['sub'] ); ?></p>
			<div class="wz-pack-price"><?php echo esc_html( $p['price'] ); ?></div>
			<ul class="wz-pack-list">
				<?php foreach ( $p['items'] as $it ) : ?>
					<li><?php echo esc_html( $it ); ?></li>
				<?php endforeach; ?>
			</ul>
			<button type="button" class="fg-btn-brand wz-pack-cta" data-wz-open="<?php echo esc_attr( $key ); ?>">Dieses Paket bestellen</button>
		</article>
		<?php endforeach; ?>
	</div>
	<p class="wz-pack-note">Alle Preise zzgl. MwSt. Bezahlung bequem auf Rechnung.</p>
</section>

<?php /* Ablauf */ ?>
<section class="mk-section mk-band fmt-flow5" aria-label="So läuft die Bestellung">
	<div class="mk-section-head">
		<div class="mk-eyebrow">So einfach geht es</div>
		<h2 class="mk-h2">Von der Bestellung bis zum Abschlag.</h2>
	</div>
	<div class="fmt-flow5-row wz-flow4">
		<?php
		$wz_steps = [
			[ 't' => 'Ihr bestellt das Paket', 'b' => 'Zwei Minuten im Formular, danach kommt die Bestätigung und die Rechnung.' ],
			[ 't' => 'Persönliche Einladung', 'b' => 'Innerhalb von 7 Werktagen erhält die beschenkte Person ihr Paket, im Namen eures Unternehmens.' ],
			[ 't' => 'Platz & Termin wählen', 'b' => 'Auf der Geschenkkarte stehen die zum Wohnort passenden Golfplätze, der Termin ist frei wählbar.' ],
			[ 't' => 'Erleben & behalten', 'b' => 'Kurs, persönlicher Empfang und beim großen Paket die Platzreife, die für immer bleibt.' ],
		];
		foreach ( $wz_steps as $i => $step ) : ?>
		<div class="fmt-fstep" style="--fstep-delay:<?php echo esc_attr( (string) ( $i * 0.18 ) ); ?>s">
			<div class="fmt-fstep-n"><?php echo esc_html( (string) ( $i + 1 ) ); ?></div>
			<h3 class="fmt-fstep-t"><?php echo esc_html( $step['t'] ); ?></h3>
			<p class="fmt-fstep-b"><?php echo esc_html( $step['b'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* FAQ */ ?>
<section class="mk-section faq-section" aria-label="FAQ">
	<div class="faq-shell">
		<div class="faq-aside">
			<div class="mk-eyebrow">Häufige Fragen</div>
			<h2 class="mk-h2" style="margin-top:8px;">Wertschätzungspaket, kurz erklärt.</h2>
		</div>
		<ul class="faq-list">
			<?php
			$wz_faqs = [
				[ 'q' => 'Braucht die beschenkte Person Golf-Vorkenntnisse?', 'a' => 'Nein. Beide Pakete starten bei null: Golflehrer, Leih-Equipment und ein persönlicher Empfang gehören immer dazu.' ],
				[ 'q' => 'Wie schnell kommt das Paket an?', 'a' => 'Innerhalb von 7 Werktagen nach der Bestellung erhält die beschenkte Person ihre persönliche Einladung, im Namen eures Unternehmens und auf Wunsch mit eurer persönlichen Nachricht.' ],
				[ 'q' => 'Wo kann das Paket eingelöst werden?', 'a' => 'Auf der Geschenkkarte stehen die zum Wohnort passenden Partner-Golfplätze als Optionen. Den Kurs und den Termin wählt die beschenkte Person selbst, alternativ beraten wir persönlich.' ],
				[ 'q' => 'Wie läuft die Bezahlung?', 'a' => 'Klassisch auf Rechnung: Ihr bestellt, wir schicken die Rechnung an die angegebene Rechnungsadresse. Kein Kreditkarten-Formular, keine Vorkasse.' ],
				[ 'q' => 'Ist das steuerlich ein geldwerter Vorteil?', 'a' => 'Sachzuwendungen an Mitarbeitende können steuerpflichtig sein, oft lässt sich das pauschal versteuern. Am besten kurz mit eurer Lohnbuchhaltung oder Steuerberatung abstimmen.' ],
			];
			foreach ( $wz_faqs as $faq ) : ?>
				<li class="faq-item">
					<button class="faq-q" type="button" aria-expanded="false">
						<span><?php echo esc_html( $faq['q'] ); ?></span>
						<span class="faq-toggle" aria-hidden="true">+</span>
					</button>
					<div class="faq-a"><?php echo esc_html( $faq['a'] ); ?></div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<?php /* CTA */ ?>
<section class="mk-cta" aria-label="Jetzt auszeichnen">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Bereit, Danke zu sagen?</div>
		<h2 class="mk-cta-h">Zeichne dein Team <em class="mk-italic">aus</em>.</h2>
		<div class="mk-cta-ctas">
			<button type="button" class="fg-btn-ink fg-btn-lg" style="background:var(--paper-100);color:var(--fairway-900)" data-wz-open>Jetzt bestellen</button>
			<a class="mk-cta-mail" href="<?php echo esc_url( ( ( $kp = get_page_by_path( 'kontakt' ) ) ? get_permalink( $kp->ID ) : home_url( '/kontakt/' ) ) ); ?>">Lieber erst beraten lassen →</a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

<?php /* ── Bestell-Popup: 3 Stufen im Onboarding-Stil, Blur-Backdrop ── */ ?>
<div class="wz-modal" id="wz-modal" hidden>
	<div class="wz-m-backdrop" data-wz-close aria-hidden="true"></div>
	<div class="wz-m-card" role="dialog" aria-modal="true" aria-label="Wertschätzungspaket bestellen">
		<header class="wz-m-top">
			<span class="wz-m-title">Wertschätzungspaket bestellen</span>
			<button type="button" class="rw-close" data-wz-close aria-label="Schließen"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
		</header>

		<form class="wz-m-body" id="wz-form" novalidate>
			<?php echo function_exists( 'fge_form_trap_fields' ) ? fge_form_trap_fields() : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<input type="hidden" name="action" value="fge_wz_order">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'fge_wz_order' ) ); ?>">

			<div class="wz-mstep" data-wzstep="0">
				<div class="rw-eyebrow">Schritt 1 · Paket</div>
				<h2 class="wz-m-h">Welches Paket darf es sein?</h2>
				<div class="wz-choose">
					<?php foreach ( $packages as $key => $p ) : ?>
					<label class="wz-choice">
						<input type="radio" name="paket" value="<?php echo esc_attr( $key ); ?>" <?php checked( 'anerkennung', $key ); ?>>
						<span class="wz-choice-card">
							<span class="wz-choice-t"><?php echo esc_html( $p['name'] ); ?></span>
							<span class="wz-choice-p"><?php echo esc_html( $p['price'] ); ?></span>
							<span class="wz-choice-s"><?php echo esc_html( $p['sub'] ); ?></span>
						</span>
					</label>
					<?php endforeach; ?>
				</div>
				<div class="wz-field" style="max-width:220px;">
					<label class="wz-label" for="wz-qty">Anzahl Personen *</label>
					<input class="wz-input" id="wz-qty" name="qty" type="number" min="1" max="500" value="1" required>
				</div>
			</div>

			<div class="wz-mstep" data-wzstep="1" hidden>
				<div class="rw-eyebrow">Schritt 2 · Beschenkte Person</div>
				<h2 class="wz-m-h">Wen möchtet ihr auszeichnen?</h2>
				<div class="wz-field">
					<label class="wz-label" for="wz-recipient">Name der beschenkten Person *</label>
					<input class="wz-input" id="wz-recipient" name="recipient" type="text" required>
				</div>
				<div class="wz-field">
					<label class="wz-label" for="wz-rec-addr">Versandadresse *</label>
					<textarea class="wz-input" id="wz-rec-addr" name="rec_address" rows="2" placeholder="Straße, PLZ Ort (privat oder Büro)" required></textarea>
				</div>
				<div class="wz-field">
					<label class="wz-label" for="wz-message">Eure persönliche Nachricht (optional)</label>
					<textarea class="wz-input" id="wz-message" name="message" rows="3" placeholder="Diese Nachricht drucken wir auf die persönliche Einladung."></textarea>
				</div>
			</div>

			<div class="wz-mstep" data-wzstep="2" hidden>
				<div class="rw-eyebrow">Schritt 3 · Unternehmen &amp; Rechnung</div>
				<h2 class="wz-m-h">Wer bestellt, und wohin geht die Rechnung?</h2>
				<div class="wz-mrow">
					<div class="wz-field">
						<label class="wz-label" for="wz-company">Unternehmen *</label>
						<input class="wz-input" id="wz-company" name="company" type="text" autocomplete="organization" required>
					</div>
					<div class="wz-field">
						<label class="wz-label" for="wz-contact">Ansprechpartner *</label>
						<input class="wz-input" id="wz-contact" name="contact" type="text" autocomplete="name" required>
					</div>
				</div>
				<div class="wz-mrow">
					<div class="wz-field">
						<label class="wz-label" for="wz-email">E-Mail für Bestätigung &amp; Rechnung *</label>
						<input class="wz-input" id="wz-email" name="email" type="email" autocomplete="email" required>
					</div>
					<div class="wz-field">
						<label class="wz-label" for="wz-phone">Telefon (optional)</label>
						<input class="wz-input" id="wz-phone" name="phone" type="tel" autocomplete="tel">
					</div>
				</div>
				<div class="wz-mrow">
					<div class="wz-field">
						<label class="wz-label" for="wz-bill">Rechnungsadresse *</label>
						<textarea class="wz-input" id="wz-bill" name="bill_address" rows="2" placeholder="Firma, Straße, PLZ Ort" required></textarea>
					</div>
					<div class="wz-field">
						<label class="wz-label" for="wz-vat">USt-ID (optional)</label>
						<input class="wz-input" id="wz-vat" name="vat" type="text">
					</div>
				</div>
				<label class="wz-consent">
					<input type="checkbox" name="consent" value="1" required>
					<span>Ich habe die <a href="<?php echo esc_url( home_url( '/datenschutz/' ) ); ?>" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen. Die Daten der beschenkten Person gebe ich berechtigt weiter; sie werden nur zur Zustellung und Einlösung des Pakets verwendet. *</span>
				</label>
			</div>
		</form>

		<div class="wz-m-success" id="wz-success" hidden>
			<div class="wz-confetti" id="wz-confetti" aria-hidden="true"></div>
			<div class="fg-success-mark"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg></div>
			<h3 class="wz-success-h">Toll, wie du deine Mitarbeiter wertschätzt!</h3>
			<p class="wz-success-p"><strong id="wz-success-name"></strong> erhält in den nächsten Tagen die persönliche Einladung zu dieser Wertschätzung, im Namen eures Unternehmens. Die Bestätigung ist unterwegs an <strong id="wz-success-mail"></strong>, die Rechnung folgt.</p>
			<button type="button" class="rw-btn-primary" data-wz-close>Schließen</button>
		</div>

		<footer class="wz-m-foot" id="wz-foot">
			<div class="wz-prog" aria-hidden="true">
				<div class="rw-seg" data-wzseg="0"><div class="rw-seg-bar"><div class="rw-seg-fill"></div></div><span class="rw-seg-label">Paket</span></div>
				<div class="rw-seg" data-wzseg="1"><div class="rw-seg-bar"><div class="rw-seg-fill"></div></div><span class="rw-seg-label">Beschenkte Person</span></div>
				<div class="rw-seg" data-wzseg="2"><div class="rw-seg-bar"><div class="rw-seg-fill"></div></div><span class="rw-seg-label">Unternehmen &amp; Rechnung</span></div>
			</div>
			<div class="wz-m-nav">
				<button type="button" class="rw-btn-text" id="wz-back">Abbrechen</button>
				<button type="button" class="rw-btn-primary" id="wz-next">Weiter <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg></button>
			</div>
		</footer>
	</div>
</div>

</div><?php /* .fge-page */ ?>

<script>
(function () {
	// Ablauf-Animation (wie Format-Landingpages).
	var row = document.querySelector('.fmt-flow5-row');
	if (row) {
		if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			row.classList.add('is-in');
		} else {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) { if (e.isIntersecting) { row.classList.add('is-in'); io.disconnect(); } });
			}, { threshold: 0.25 });
			io.observe(row);
		}
	}

	// FAQ-Toggle.
	document.querySelectorAll('.fge-page .faq-q[aria-expanded]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var item = btn.closest('.faq-item');
			var open = item.classList.toggle('open');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			var t = btn.querySelector('.faq-toggle'); if (t) t.textContent = open ? '−' : '+';
		});
	});

	// ── Bestell-Popup: 3 Stufen ──
	var modal = document.getElementById('wz-modal');
	var form = document.getElementById('wz-form');
	if (!modal || !form) return;
	var foot = document.getElementById('wz-foot');
	var success = document.getElementById('wz-success');
	var backBtn = document.getElementById('wz-back');
	var nextBtn = document.getElementById('wz-next');
	var NEXT_HTML = nextBtn.innerHTML;
	var step = 0;

	function renderStep() {
		modal.querySelectorAll('.wz-mstep').forEach(function (s) {
			s.hidden = parseInt(s.getAttribute('data-wzstep'), 10) !== step;
		});
		modal.querySelectorAll('.wz-prog .rw-seg').forEach(function (seg) {
			var i = parseInt(seg.getAttribute('data-wzseg'), 10);
			seg.classList.toggle('done', i < step);
			seg.classList.toggle('on', i === step);
			seg.querySelector('.rw-seg-fill').style.width = i <= step ? '100%' : '0';
		});
		backBtn.textContent = step === 0 ? 'Abbrechen' : 'Zurück';
		nextBtn.innerHTML = step === 2 ? 'Kostenpflichtig bestellen' : NEXT_HTML;
		modal.querySelector('.wz-m-body').scrollTop = 0;
	}

	function openModal(paket) {
		if (paket) {
			var r = form.querySelector('input[name="paket"][value="' + paket + '"]');
			if (r) r.checked = true;
		}
		step = 0;
		form.hidden = false;
		foot.hidden = false;
		success.hidden = true;
		renderStep();
		modal.hidden = false;
		document.documentElement.style.overflow = 'hidden';
	}
	function closeModal() {
		modal.hidden = true;
		document.documentElement.style.overflow = '';
	}

	document.querySelectorAll('[data-wz-open]').forEach(function (b) {
		b.addEventListener('click', function () { openModal(b.getAttribute('data-wz-open') || ''); });
	});
	modal.querySelectorAll('[data-wz-close]').forEach(function (b) {
		b.addEventListener('click', closeModal);
	});
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeModal(); });

	function stepValid() {
		var cur = modal.querySelector('.wz-mstep[data-wzstep="' + step + '"]');
		var ok = true;
		cur.querySelectorAll('input, textarea').forEach(function (f) {
			if (!f.checkValidity()) { if (ok) f.reportValidity(); ok = false; }
		});
		return ok;
	}

	function confetti() {
		var host = document.getElementById('wz-confetti');
		if (!host) return;
		var colors = ['#4279D1', '#C9B488', '#009E78', '#D2693E', '#6E9BDD', '#D8B26A'];
		for (var i = 0; i < 60; i++) {
			var c = document.createElement('span');
			c.className = 'wz-conf';
			c.style.left = (Math.random() * 100) + '%';
			c.style.background = colors[i % colors.length];
			c.style.animationDelay = (Math.random() * 0.9) + 's';
			c.style.animationDuration = (1.6 + Math.random() * 1.4) + 's';
			c.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
			host.appendChild(c);
		}
	}

	backBtn.addEventListener('click', function () {
		if (step === 0) { closeModal(); return; }
		step--; renderStep();
	});

	nextBtn.addEventListener('click', function () {
		if (!stepValid()) return;
		if (step < 2) { step++; renderStep(); return; }
		nextBtn.disabled = true;
		nextBtn.textContent = 'Wird gesendet…';
		fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams(new FormData(form)).toString()
		})
		.then(function (r) { return r.json(); })
		.then(function (j) {
			if (j && j.success) {
				window.dataLayer = window.dataLayer || [];
				window.dataLayer.push({ event: 'wz_bestellung' });
				document.getElementById('wz-success-mail').textContent = (j.data && j.data.email) || '';
				document.getElementById('wz-success-name').textContent = form.querySelector('[name="recipient"]').value || 'Die beschenkte Person';
				form.hidden = true;
				foot.hidden = true;
				success.hidden = false;
				confetti();
			} else {
				alert((j && j.data && j.data.message) || 'Es ist ein Fehler aufgetreten. Bitte versuche es erneut.');
			}
			nextBtn.disabled = false;
			nextBtn.innerHTML = 'Kostenpflichtig bestellen';
		})
		.catch(function () {
			alert('Verbindungsfehler. Bitte versuche es erneut.');
			nextBtn.disabled = false;
			nextBtn.innerHTML = 'Kostenpflichtig bestellen';
		});
	});
})();
</script>

<?php get_footer();
