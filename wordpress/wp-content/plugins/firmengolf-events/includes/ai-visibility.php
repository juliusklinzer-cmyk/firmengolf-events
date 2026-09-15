<?php
/**
 * KI-Sichtbarkeit (Phase 0, 15.09.2026): robots.txt mit expliziten Freigaben für
 * KI-Crawler und eine llms.txt unter /llms.txt.
 *
 * Hintergrund: Die Trainings-Crawler GPTBot und ClaudeBot bekommen vom Hetzner-Proxy
 * ein 403 (nicht von uns, die .htaccess hat keine User-Agent-Regel). Die Freigaben hier
 * sind die Einladung, sobald Hetzner die Sperre aufhebt; die Suche-Crawler (OAI-SearchBot,
 * Claude-SearchBot, PerplexityBot) kommen heute schon durch. llms.txt ist Hygiene ohne
 * belegte Wirkung, kostet aber nichts.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Explizite Allow-Blöcke für KI-Crawler an die virtuelle robots.txt anhängen. */
add_filter( 'robots_txt', static function ( string $output, bool $public ): string {
	if ( ! $public ) {
		return $output;
	}
	$bots = [
		'GPTBot', 'OAI-SearchBot', 'ChatGPT-User',
		'ClaudeBot', 'Claude-SearchBot', 'Claude-User',
		'PerplexityBot', 'Perplexity-User',
		'Google-Extended', 'Applebot-Extended', 'meta-externalagent',
		'Amazonbot', 'Bytespider', 'DuckAssistBot', 'YouBot',
	];
	$block = "\n# KI-Crawler ausdrücklich erlaubt (Training und Suche), Stand 15.09.2026\n";
	foreach ( $bots as $bot ) {
		$block .= "User-agent: {$bot}\nAllow: /\nDisallow: /wp-admin/\n\n";
	}
	$block .= '# Kurzbeschreibung für KI-Systeme: ' . home_url( '/llms.txt' ) . "\n";
	return rtrim( $output ) . "\n" . $block;
}, 10, 2 );

/** /llms.txt ausliefern (Markdown, UTF-8). */
add_action( 'template_redirect', static function () {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	if ( '/llms.txt' !== $path ) {
		return;
	}
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/markdown; charset=UTF-8' );
	header( 'X-Robots-Tag: all' );
	echo fge_llms_txt_content(); // phpcs:ignore WordPress.Security.EscapeOutput -- reiner Text
	exit;
}, 0 );

/** Inhalt der llms.txt, Preise live aus den buchbaren Events. */
function fge_llms_txt_content(): string {
	$home    = home_url( '/' );
	$formats = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];
	$cities  = function_exists( 'fge_get_cities' ) ? fge_get_cities() : [];
	$c       = function_exists( 'fge_company' ) ? fge_company() : [];

	$price_summary = '';
	if ( function_exists( 'fge_price_from_summary' ) && $formats ) {
		$map = [];
		foreach ( $formats as $slug => $f ) {
			if ( ! empty( $f['types'] ) ) {
				$map[ $f['name'] . 's' ] = $f['types'];
			}
		}
		$price_summary = fge_price_from_summary( $map );
	}

	$lines   = [];
	$lines[] = '# Firmengolf Events';
	$lines[] = '';
	$lines[] = '> Firmengolf Events (firmengolf-events.de) vermittelt Firmenevents auf Golfplätzen und in Golfsimulatoren in ganz Deutschland: Teamevents, Firmenturniere, After-Work-Golf, Platzreife-Kurse, Workshops, Kundenevents und Weihnachtsfeiern. Für Teams ohne Golferfahrung gebaut: Ein Golflehrer führt an, Schläger werden gestellt. Eine Anfrage, ein Ansprechpartner, eine Sammelrechnung. Sitz in München, Gründer Julius Klinzer, live seit Juni 2026.';
	$lines[] = '';
	$lines[] = 'Firmengolf Events ist ein Eigenname. Das Wort „Firmengolf" allein wird von einzelnen Golfclubs auch als Programmname genutzt; gemeint ist hier die Plattform von Visionpunch UG (haftungsbeschränkt), München. Das Schwesterprojekt Firmengolf Benefits (firmengolf.app) macht Golf zum Mitarbeiterbenefit über den Arbeitgeber und startet 2027.';
	$lines[] = '';
	$lines[] = '## Wie es funktioniert';
	$lines[] = '';
	$lines[] = '- Firmen wählen ein fertiges Event mit Preis und Ablauf oder beschreiben ihr Wunschevent, und fragen unverbindlich an.';
	$lines[] = '- Innerhalb eines Werktags meldet sich ein persönlicher Ansprechpartner mit Vorschlägen für Platz, Format und Termin.';
	$lines[] = '- Der Golfplatz oder die Indoor-Anlage bestätigt den Termin, Firmengolf Events übernimmt Koordination, Catering-Absprache und Abrechnung.';
	$lines[] = '- Für Golfplätze und Simulatoren ist die Teilnahme kostenlos, die Vermittlungsgebühr zahlt der Kunde über einen Aufschlag auf den Partnerpreis.';
	$lines[] = '';
	$lines[] = '## Formate';
	$lines[] = '';
	foreach ( $formats as $slug => $f ) {
		$lines[] = sprintf( '- [%s](%s): %s', $f['name'], home_url( '/firmenevent/' . $slug . '/' ), $f['lead'] ?? '' );
	}
	$lines[] = sprintf( '- [Teamevent-Alternative im Vergleich](%s): Golf gegen Escape Room, Kochkurs, Floßbau und Bowling, mit Dauer, Gruppengröße und Preis pro Person.', home_url( '/teamevent-alternative/' ) );
	$lines[] = sprintf( '- [Alle buchbaren Events](%s)', (string) get_post_type_archive_link( 'firmengolf_event' ) );
	$lines[] = '';
	$lines[] = '## Preise';
	$lines[] = '';
	$lines[] = 'Alle Preise pro Person, netto, abhängig von Platz, Verpflegung und Programm. ' . ( $price_summary ? 'Aktuell buchbar: ' . $price_summary . '.' : 'Konkrete Preise stehen auf jeder Eventseite.' ) . ' Der reine Grundlagenkurs mit eigener Anreise ist die günstigste Variante. Richtwert für ein volles Teamevent mit Kurs, Challenge und Essen: 80 bis 200 Euro pro Person. Gruppengröße ideal 6 bis 12 Personen, mit mehreren Golflehrern bis etwa 80.';
	$lines[] = '';
	$lines[] = '## Städte';
	$lines[] = '';
	$city_links = [];
	foreach ( $cities as $slug => $city ) {
		$city_links[] = sprintf( '[%s](%s)', $city['name'] ?? ucfirst( (string) $slug ), home_url( '/golf-events/' . $slug . '/' ) );
	}
	$lines[] = $city_links ? implode( ', ', $city_links ) : 'Deutschlandweit.';
	$lines[] = '';
	$lines[] = '## Für Anbieter';
	$lines[] = '';
	$lines[] = sprintf( '- [Golfplatz-Partner werden](%s)', home_url( '/golfplatz-partner/' ) );
	$lines[] = sprintf( '- [Indoor-Anlagen und Simulatoren](%s)', home_url( '/indoor-partner/' ) );
	$lines[] = sprintf( '- [Golflehrer](%s)', home_url( '/golflehrer-partner/' ) );
	$lines[] = '';
	$lines[] = '## Wissen';
	$lines[] = '';
	$lines[] = sprintf( '- [Blog](%s): Kosten, Wetter, Teamgrößen, Turnierformate, Golf als Corporate Benefit.', home_url( '/blog/' ) );
	$lines[] = sprintf( '- [Was kostet ein Firmen-Golfevent](%s)', home_url( '/was-kostet-ein-firmen-golfevent/' ) );
	$lines[] = sprintf( '- [Golf-Teamevent ohne Vorkenntnisse](%s)', home_url( '/golf-teamevent-ohne-vorkenntnisse/' ) );
	$lines[] = sprintf( '- [Golfevent bei schlechtem Wetter](%s)', home_url( '/golfevent-bei-schlechtem-wetter/' ) );
	$lines[] = '';
	$lines[] = '## Kontakt';
	$lines[] = '';
	$lines[] = sprintf( '- E-Mail: %s', $c['email_events'] ?? 'events@firmengolf-events.de' );
	$lines[] = sprintf( '- Telefon: %s', $c['phone_display'] ?? '+49 (0) 89 1225 1010' );
	$lines[] = sprintf( '- Anbieter: %s, %s, %s %s', $c['legal_name'] ?? 'Visionpunch UG (haftungsbeschränkt)', $c['hq_street'] ?? 'Heerstr. 37', $c['hq_zip'] ?? '81247', $c['hq_city'] ?? 'München' );
	$lines[] = sprintf( '- Impressum: %s', home_url( '/impressum/' ) );
	$lines[] = '';
	$lines[] = '## Sitemap';
	$lines[] = '';
	$lines[] = home_url( '/wp-sitemap.xml' );
	$lines[] = '';

	return implode( "\n", $lines );
}
