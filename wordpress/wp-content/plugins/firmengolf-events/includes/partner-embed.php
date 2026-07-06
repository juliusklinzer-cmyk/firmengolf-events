<?php
/**
 * Embed-Widget: Golfplätze zeigen ihre Firmengolf-Events auf der EIGENEN Website.
 *
 *   <iframe src="https://…/embed/platz/<slug>/" style="width:100%;border:0" loading="lazy"></iframe>
 *   <script src="https://…/embed/resize.js" async></script>
 *
 * Bewusst cookielos und ohne Drittdienste (keine Maps, kein Tracking, Fonts
 * system-stack) → Clubs können es ohne Consent-Banner einbinden. Klicks führen
 * auf die Event-Seiten von firmengolf-events.de — Anfragen laufen durch unseren Flow.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', static function () {
	add_rewrite_rule( '^embed/platz/([^/]+)/?$', 'index.php?fge_embed_platz=$matches[1]', 'top' );
	add_rewrite_rule( '^embed/resize\.js$', 'index.php?fge_embed_resize=1', 'top' );
} );
add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_embed_platz';
	$vars[] = 'fge_embed_resize';
	return $vars;
} );
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^embed/platz/([^/]+)/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );

/** Snippet fürs Portal (und die Start-Mail). */
function fge_embed_snippet( int $partner_id ): string {
	$slug = get_post_field( 'post_name', $partner_id );
	return '<iframe src="' . esc_url( home_url( '/embed/platz/' . $slug . '/' ) ) . '"' . "\n"
		. '        style="width:100%;border:0" loading="lazy" title="Firmenevents auf unserem Platz"></iframe>' . "\n"
		. '<script src="' . esc_url( home_url( '/embed/resize.js' ) ) . '" async></script>';
}

add_action( 'template_redirect', 'fge_embed_render', 1 );
function fge_embed_render(): void {
	// resize.js — das Mini-Script für die Auto-Höhe des iframes.
	if ( get_query_var( 'fge_embed_resize' ) ) {
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: public, max-age=86400' );
		echo "(function(){window.addEventListener('message',function(e){if(!e.data||e.data.fgeEmbed!==true||typeof e.data.height!=='number')return;var f=document.querySelectorAll('iframe');for(var i=0;i<f.length;i++){if(f[i].contentWindow===e.source){f[i].style.height=Math.max(120,e.data.height)+'px';break;}}});})();";
		exit;
	}

	$slug = sanitize_title( (string) get_query_var( 'fge_embed_platz' ) );
	if ( '' === $slug ) {
		return;
	}

	$partner = get_page_by_path( $slug, OBJECT, 'firmengolf_partner' );
	$pid     = $partner ? (int) $partner->ID : 0;
	if ( $pid <= 0 || ! function_exists( 'fge_partner_is_public' ) || ! fge_partner_is_public( $pid ) ) {
		status_header( 404 );
		exit;
	}

	$event_ids = function_exists( 'fge_partner_public_event_ids' ) ? fge_partner_public_event_ids( $pid ) : [];
	$name      = (string) get_post_meta( $pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $pid );

	// Framing ausdrücklich erlauben (nur für diese Route) + Cache.
	header_remove( 'X-Frame-Options' );
	header( 'Content-Security-Policy: frame-ancestors *' );
	header( 'Cache-Control: public, max-age=600' );
	header( 'Content-Type: text/html; charset=utf-8' );

	$cards = [];
	foreach ( $event_ids as $eid ) {
		$pmin    = (int) get_post_meta( $eid, '_fge_participants_min', true );
		$pmax    = (int) get_post_meta( $eid, '_fge_participants_max', true );
		$group   = ( $pmin > 0 && $pmax > 0 ) ? "{$pmin}–{$pmax} Pers." : ( $pmax > 0 ? "bis {$pmax} Pers." : '' );
		$cards[] = [
			'url'      => get_permalink( $eid ),
			'img'      => function_exists( 'fge_event_cover_url' ) ? fge_event_cover_url( $eid, 'large' ) : '',
			'title'    => get_the_title( $eid ),
			'type'     => function_exists( 'fge_format_event_type' ) ? fge_format_event_type( (string) get_post_meta( $eid, '_fge_event_type', true ) ) : '',
			'duration' => (string) get_post_meta( $eid, '_fge_duration', true ),
			'group'    => $group,
			'price'    => function_exists( 'fge_event_price_label' ) ? (string) fge_event_price_label( $eid ) : '',
		];
	}
	?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Firmenevents · <?php echo esc_html( $name ); ?></title>
<style>
	:root{--ink:#14231A;--ink2:#5C6660;--line:#E4E2DA;--paper:#FFFFFF;--bg:#FBFAF6;--brand:#4279D1;--green:#2F6E45;}
	*{box-sizing:border-box;margin:0;padding:0}
	body{font-family:-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--ink);padding:4px 2px}
	.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
	.card{background:var(--paper);border:1px solid var(--line);border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;display:flex;flex-direction:column;transition:transform .12s ease,box-shadow .12s ease}
	.card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(20,35,26,.08)}
	.ph{aspect-ratio:16/10;background-size:cover;background-position:center;position:relative}
	.chip{position:absolute;top:10px;left:10px;background:rgba(255,255,255,.94);border-radius:999px;padding:4px 10px;font-size:11.5px;font-weight:600}
	.bd{padding:14px 16px 15px;display:flex;flex-direction:column;gap:6px;flex:1}
	.t{font-size:15.5px;font-weight:700;line-height:1.3}
	.m{font-size:12.5px;color:var(--ink2)}
	.foot{margin-top:auto;display:flex;align-items:baseline;justify-content:space-between;gap:8px;padding-top:8px;border-top:1px solid var(--line)}
	.p{font-size:15px;font-weight:700}
	.p small{font-size:11px;color:var(--ink2);font-weight:500;margin-right:3px}
	.cta{font-size:12.5px;font-weight:600;color:var(--brand)}
	.brand{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;padding:2px 2px 0}
	.brand span{font-size:11.5px;color:var(--ink2)}
	.brand a{font-size:11.5px;font-weight:700;color:var(--ink);text-decoration:none}
	.empty{background:var(--paper);border:1px dashed var(--line);border-radius:14px;padding:26px;text-align:center;color:var(--ink2);font-size:14px}
</style>
</head>
<body>
	<?php if ( empty( $cards ) ) : ?>
		<div class="empty">Aktuell sind keine Event-Angebote online — schau bald wieder vorbei.</div>
	<?php else : ?>
	<div class="grid">
		<?php foreach ( $cards as $c ) : ?>
		<a class="card" href="<?php echo esc_url( $c['url'] ); ?>" target="_blank" rel="noopener">
			<div class="ph" style="background-image:url('<?php echo esc_url( $c['img'] ); ?>')">
				<?php if ( '' !== $c['type'] ) : ?><span class="chip"><?php echo esc_html( $c['type'] ); ?></span><?php endif; ?>
			</div>
			<div class="bd">
				<div class="t"><?php echo esc_html( $c['title'] ); ?></div>
				<div class="m"><?php echo esc_html( trim( $c['duration'] . ( $c['duration'] && $c['group'] ? ' · ' : '' ) . $c['group'] ) ); ?></div>
				<div class="foot">
					<span class="p"><?php echo '' !== $c['price'] ? esc_html( $c['price'] ) . ( false === stripos( $c['price'], 'netto' ) && false === stripos( $c['price'], 'Anfrage' ) ? ' <small>netto</small>' : '' ) : '<small>Preis</small>auf Anfrage'; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="cta">Ansehen &amp; anfragen →</span>
				</div>
			</div>
		</a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<div class="brand">
		<span>Firmenevents bei <?php echo esc_html( $name ); ?></span>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">Angebote &amp; Buchung über Firmengolf ↗</a>
	</div>
	<script>
	(function(){
		function send(){try{parent.postMessage({fgeEmbed:true,height:document.documentElement.scrollHeight},'*')}catch(e){}}
		window.addEventListener('load',send);
		window.addEventListener('resize',send);
		if(window.ResizeObserver){new ResizeObserver(send).observe(document.body)}
		setTimeout(send,300);
	})();
	</script>
</body>
</html><?php
	exit;
}
