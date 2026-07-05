<?php
/**
 * Einladungs-Landing (/einladung/<club-slug>/<token>/): Club legt Zugangsdaten
 * für sein vorbereitetes Partner-Profil fest. Standalone-Seite mit eigenem,
 * gekapseltem Styling (unabhängig vom Theme, kein Admin-Bar).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
show_admin_bar( false );

$token      = (string) get_query_var( 'fge_einladung' );
$partner_id = function_exists( 'fge_invite_partner_by_token' ) ? fge_invite_partner_by_token( $token ) : 0;
$linked     = $partner_id > 0 && (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true ) > 0;
$club       = $partner_id > 0 ? ( (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id ) ) : '';
$city       = $partner_id > 0 ? (string) get_post_meta( $partner_id, '_fge_city', true ) : '';
$hero_id    = $partner_id > 0 ? (int) get_post_meta( $partner_id, '_fge_hero_image_attachment_id', true ) : 0;
$hero_img   = $hero_id > 0 ? (string) wp_get_attachment_image_url( $hero_id, 'large' ) : ( function_exists( 'fge_get_placeholder_image_url' ) ? fge_get_placeholder_image_url( 'hero-fairway-wide.jpg', max( 1, $partner_id ) ) : '' );
$logo_url   = function_exists( 'fge_get_logo_url' ) ? fge_get_logo_url() : '';
$phone      = function_exists( 'fge_company' ) ? ( fge_company()['phone_display'] ?? '' ) : '';
$post_url   = function_exists( 'fge_invite_url' ) && $partner_id > 0 ? fge_invite_url( $partner_id ) : home_url( '/einladung/' . rawurlencode( $token ) . '/' );
$fehler     = sanitize_key( wp_unslash( $_GET['fehler'] ?? '' ) );
$fehler_txt = [
	'felder'         => 'Bitte fülle Name und eine gültige E-Mail-Adresse aus.',
	'passwort'       => 'Das Passwort braucht mindestens 8 Zeichen.',
	'email_vergeben' => 'Für diese E-Mail existiert schon ein Konto. Melde dich damit im Portal an oder ruf uns kurz an — wir verknüpfen es für dich.',
	'rate'           => 'Zu viele Versuche — bitte warte ein paar Minuten.',
][ $fehler ] ?? '';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<meta name="referrer" content="no-referrer">
	<title><?php echo $club ? esc_html( $club ) . ' · ' : ''; ?>Einladung · Firmengolf</title>
	<?php wp_head(); ?>
	<style>
		.inv-page, .inv-page * { box-sizing: border-box; }
		.inv-page { min-height: 100vh; background: var(--paper-100, #FBFAF6); font-family: var(--font-body, sans-serif); color: var(--ink-900, #14231A); display: flex; flex-direction: column; }
		.inv-bar { padding: 20px 32px; }
		.inv-bar img { height: 24px; display: block; }
		.inv-wrap { flex: 1; display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); gap: 0; max-width: 1160px; width: 100%; margin: 0 auto; padding: 12px 32px 64px; align-items: stretch; }
		.inv-left { position: relative; border-radius: 20px 0 0 20px; overflow: hidden; background: #14231A center/cover no-repeat; min-height: 560px; }
		.inv-left::after { content: ""; position: absolute; inset: 0; background: linear-gradient(200deg, rgba(14,19,16,0.10) 0%, rgba(14,19,16,0.72) 82%); }
		.inv-left-inner { position: absolute; inset: auto 0 0 0; padding: 34px 36px; z-index: 2; color: #FBFAF6; }
		.inv-eyebrow { font-size: 11px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(251,250,246,0.75); margin-bottom: 10px; }
		.inv-club { font-family: var(--font-display, inherit); font-weight: 500; font-size: clamp(28px, 3.4vw, 40px); letter-spacing: -0.02em; line-height: 1.08; margin: 0 0 6px; }
		.inv-city { font-size: 14px; color: rgba(251,250,246,0.8); margin-bottom: 20px; }
		.inv-checks { display: flex; flex-direction: column; gap: 9px; }
		.inv-check { display: flex; gap: 10px; align-items: flex-start; font-size: 14.5px; line-height: 1.45; color: rgba(251,250,246,0.92); }
		.inv-check b { font-weight: 600; color: #fff; }
		.inv-check .ic { width: 20px; height: 20px; border-radius: 999px; background: rgba(255,255,255,0.16); backdrop-filter: blur(6px); display: inline-flex; align-items: center; justify-content: center; font-size: 11px; flex: none; margin-top: 1px; }
		.inv-right { background: var(--paper-50, #fff); border: 1px solid var(--ink-200, #E4E2DA); border-left: 0; border-radius: 0 20px 20px 0; padding: 44px 48px; display: flex; flex-direction: column; justify-content: center; }
		.inv-title { font-family: var(--font-display, inherit); font-weight: 500; font-size: 26px; letter-spacing: -0.02em; margin: 0 0 8px; }
		.inv-sub { font-size: 14.5px; color: var(--ink-600, #5C6660); line-height: 1.55; margin: 0 0 24px; }
		.inv-error { background: #F9EDEA; border: 1px solid #E5B8B0; color: #8C3B2F; border-radius: 10px; padding: 11px 14px; font-size: 14px; margin-bottom: 18px; line-height: 1.5; }
		.inv-form { display: flex; flex-direction: column; gap: 14px; }
		.inv-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
		.inv-field label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink-700, #3A443E); margin-bottom: 6px; }
		.inv-field input { width: 100%; font: inherit; font-size: 15px; color: var(--ink-900, #14231A); background: var(--paper-100, #FBFAF6); border: 1px solid var(--ink-200, #E4E2DA); border-radius: 10px; padding: 12px 14px; }
		.inv-field input:focus { outline: 2px solid var(--fairway-700, #4279D1); outline-offset: 1px; border-color: transparent; }
		/* button./a.-Präfix: schlägt den globalen .fge-page-button/-a-Reset (0-1-1) */
		.inv-cta, button.inv-cta, a.inv-cta { margin-top: 6px; width: 100%; font: inherit; font-size: 15.5px; font-weight: 600; color: #fff; background: var(--fairway-700, #4279D1); border: 0; border-radius: 999px; padding: 14px 26px; cursor: pointer; transition: background 0.15s ease; text-align: center; }
		.inv-cta:hover, button.inv-cta:hover, a.inv-cta:hover { background: var(--fairway-600, #3568BC); color: #fff; }
		.inv-legal { font-size: 12px; color: var(--ink-500, #7C857F); line-height: 1.55; margin: 14px 0 0; }
		.inv-legal a { color: var(--fairway-700, #4279D1); text-decoration: none; }
		.inv-help { margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--ink-200, #E4E2DA); font-size: 13.5px; color: var(--ink-600, #5C6660); }
		.inv-help a { color: var(--fairway-700, #4279D1); font-weight: 600; text-decoration: none; }
		.inv-single { max-width: 560px; margin: 40px auto; background: var(--paper-50, #fff); border: 1px solid var(--ink-200, #E4E2DA); border-radius: 20px; padding: 44px 48px; text-align: center; }
		.inv-single .inv-cta { display: inline-block; width: auto; text-decoration: none; margin-top: 18px; }
		@media (max-width: 880px) {
			.inv-wrap { grid-template-columns: 1fr; padding: 0 16px 48px; }
			.inv-left { border-radius: 20px 20px 0 0; min-height: 300px; }
			.inv-right { border-radius: 0 0 20px 20px; border-left: 1px solid var(--ink-200, #E4E2DA); border-top: 0; padding: 30px 24px; }
		}
	</style>
</head>
<body class="fge-page inv-page">
	<div class="inv-bar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( $logo_url ); ?>" alt="Firmengolf"></a>
	</div>

	<?php if ( $partner_id <= 0 || $linked ) : ?>
		<div class="inv-single">
			<h1 class="inv-title"><?php echo $linked ? 'Schon erledigt!' : 'Dieser Link ist nicht mehr gültig'; ?></h1>
			<p class="inv-sub" style="margin-bottom:0;">
				<?php echo $linked
					? 'Dieses Partnerprofil ist bereits mit einem Konto verknüpft — melde dich einfach im Portal an.'
					: 'Der Einladungslink wurde bereits verwendet oder ist abgelaufen. Ruf uns an, wir schicken dir sofort einen neuen.'; ?>
			</p>
			<a class="inv-cta" href="<?php echo esc_url( home_url( '/partnerportal/' ) ); ?>">Zum Partner-Portal →</a>
			<?php if ( '' !== $phone ) : ?><p class="inv-help" style="border:0;">Oder ruf an: <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p><?php endif; ?>
		</div>
	<?php else : ?>
		<div class="inv-wrap">
			<div class="inv-left" style="background-image:url('<?php echo esc_url( $hero_img ); ?>')">
				<div class="inv-left-inner">
					<div class="inv-eyebrow">Persönliche Einladung</div>
					<h1 class="inv-club"><?php echo esc_html( $club ); ?></h1>
					<?php if ( '' !== $city ) : ?><div class="inv-city"><?php echo esc_html( $city ); ?></div><?php endif; ?>
					<div class="inv-checks">
						<div class="inv-check"><span class="ic">✓</span><span><b>Euer Profil ist fertig eingerichtet</b> — Beschreibung, Fotos, Ausstattung haben wir übernommen.</span></div>
						<div class="inv-check"><span class="ic">✓</span><span><b>Firmen aus eurer Region</b> suchen bei uns nach Plätzen für Teamevents, Turniere &amp; After-Work.</span></div>
						<div class="inv-check"><span class="ic">✓</span><span><b>Kostenlos für euren Platz</b> — ihr passt nur noch Feinheiten an und schaltet euer erstes Angebot frei.</span></div>
					</div>
				</div>
			</div>
			<div class="inv-right">
				<h2 class="inv-title">Leg deine Zugangsdaten fest</h2>
				<p class="inv-sub">Eine Minute, dann bist du drin: Du siehst euer vorbereitetes Profil, kannst alles anpassen und euer erstes Event-Angebot anlegen.</p>

				<?php if ( '' !== $fehler_txt ) : ?><div class="inv-error"><?php echo esc_html( $fehler_txt ); ?></div><?php endif; ?>

				<form class="inv-form" method="post" action="<?php echo esc_url( $post_url ); ?>">
					<input type="hidden" name="fge_action" value="invite_accept">
					<input type="hidden" name="fge_invite_token" value="<?php echo esc_attr( $token ); ?>">
					<?php wp_nonce_field( 'fge_invite_' . $token, 'fge_invite_nonce' ); ?>
					<div class="inv-row2">
						<div class="inv-field"><label for="fge_first">Vorname</label><input type="text" id="fge_first" name="fge_first" required autocomplete="given-name"></div>
						<div class="inv-field"><label for="fge_last">Nachname</label><input type="text" id="fge_last" name="fge_last" autocomplete="family-name"></div>
					</div>
					<div class="inv-field"><label for="fge_email">E-Mail-Adresse (dein Login)</label><input type="email" id="fge_email" name="fge_email" required autocomplete="email"></div>
					<div class="inv-field"><label for="fge_pass">Passwort (mindestens 8 Zeichen)</label><input type="password" id="fge_pass" name="fge_pass" required minlength="8" autocomplete="new-password"></div>
					<button type="submit" class="inv-cta">Zugang anlegen &amp; Profil ansehen</button>
				</form>

				<p class="inv-legal">Mit dem Anlegen akzeptierst du unsere <a href="<?php echo esc_url( home_url( '/agb/' ) ); ?>" target="_blank" rel="noopener">Partner-Bedingungen</a>. Deine Daten nutzen wir nur für den Portal-Betrieb (<a href="<?php echo esc_url( home_url( '/datenschutz/' ) ); ?>" target="_blank" rel="noopener">Datenschutz</a>).</p>
				<?php if ( '' !== $phone ) : ?>
					<div class="inv-help">Fragen? Ruf uns einfach an: <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php wp_footer(); ?>
</body>
</html>
