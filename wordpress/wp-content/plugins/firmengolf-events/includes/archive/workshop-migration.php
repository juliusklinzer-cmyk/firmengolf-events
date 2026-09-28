<?php
/**
 * Einmalige Migration (2026-08): Offsite/Incentive raus aus dem Standard-Angebot,
 * Workshop rein. Läuft selbstständig nach dem Deploy (Option-gegated), weil das
 * Live-System nur per FTPS erreichbar ist, kein WP-CLI.
 *
 * 1. Die 8 Incentive-Seed-Events („Golf-Incentive in <Stadt> & Umland") werden zu
 *    Workshop-Events mit neuem Inhalt (Ablauf laut Julius, 190 € p. P.).
 *    Slug-Wechsel erzeugt via wp_update_post automatisch _wp_old_slug → 301.
 * 2. Blog-Artikel „Warum Strategie-Offsites …" wird zum Workshop-Artikel.
 *
 * Idempotent: identifiziert Posts über die alten Slugs; nichts gefunden → still.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', static function () {
	if ( get_option( 'fge_workshop_migration' ) ) {
		return;
	}
	update_option( 'fge_workshop_migration', '1', true );

	$cities = [
		'muenchen'   => 'München',
		'hamburg'    => 'Hamburg',
		'koeln'      => 'Köln',
		'stuttgart'  => 'Stuttgart',
		'berlin'     => 'Berlin',
		'frankfurt'  => 'Frankfurt',
		'duesseldorf'=> 'Düsseldorf',
		'tegernsee'  => 'Tegernsee',
	];

	$dayflow = "Ankunft im Golfclub\nDer Club begrüßt euch persönlich und führt euch in eure Eventlocation.\n\n"
		. "Begrüßung mit Kaffee & Kuchen\nAnkommen am Buffet mit Selbstbedienung, Zeit für ein erstes lockeres Gespräch.\n\n"
		. "Workshop Teil 1\nDer offizielle Teil im Konferenzraum, konzentriert und ungestört.\n\n"
		. "Mittagessen auf der Clubterrasse\nBuffet inklusive Getränke, mit Blick ins Grüne.\n\n"
		. "Workshop Teil 2\nWeiter geht es im Konferenzraum, mit frischem Kopf.\n\n"
		. "Ausklang im Team\nZum Abschluss ein Golf-Grundlagenkurs mit einem unserer Golflehrer, ganz ohne Vorkenntnisse.";
	$includes = "Eventlocation & Konferenzraum\nBegrüßung mit Kaffee & Kuchen\nMittagsbuffet inkl. Getränke auf der Clubterrasse\nGolf-Grundlagenkurs mit Golflehrer\nLeihschläger & Range-Bälle\nOrganisation & ein Ansprechpartner";
	$addons   = "Beamer & Moderationstechnik\nAbendessen im Clubrestaurant\nFotograf\nAbholservice";
	$card     = 'Konferenzraum im Clubhaus, Mittagsbuffet auf der Clubterrasse und zum Ausklang ein Golf-Grundlagenkurs mit Golflehrer, der Workshop-Tag im Grünen.';

	foreach ( $cities as $slug => $name ) {
		$post = get_page_by_path( 'golf-incentive-in-' . $slug . '-umland', OBJECT, 'firmengolf_event' );
		if ( ! $post ) {
			continue;
		}
		wp_update_post( [
			'ID'         => $post->ID,
			'post_title' => 'Workshop auf dem Golfplatz in ' . $name,
			'post_name'  => 'workshop-auf-dem-golfplatz-in-' . $slug,
		] );
		update_post_meta( $post->ID, '_fge_event_type', 'workshop' );
		update_post_meta( $post->ID, '_fge_card_description', $card );
		update_post_meta( $post->ID, '_fge_event_dayflow', $dayflow );
		update_post_meta( $post->ID, '_fge_event_includes', $includes );
		update_post_meta( $post->ID, '_fge_event_addons', $addons );
		update_post_meta( $post->ID, '_fge_duration', 'Ganztag · ca. 8 Std.' );
		update_post_meta( $post->ID, '_fge_price_amount', '190' );
		update_post_meta( $post->ID, '_fge_price_basis', 'person' );
		update_post_meta( $post->ID, '_fge_price_mode', 'gesamt' );
		update_post_meta( $post->ID, '_fge_participants_min', '8' );
		update_post_meta( $post->ID, '_fge_participants_max', '30' );
	}

	$blog = get_page_by_path( 'wieso-offsite-im-gruenen', OBJECT, 'post' );
	if ( $blog ) {
		$content = (string) $blog->post_content;
		$content = str_replace(
			[
				'Strategie-Offsites auf einem Golfplatz laufen anders',
				'Wie ein typisches 2-Tages-Offsite mit uns aussieht',
				'<li><strong>Tag 1:</strong> Vormittags Workshop, Lunch auf der Terrasse, Nachmittags 9 Loch in gemischten Vierern.</li>',
				'<li><strong>Abend:</strong> Privates Dinner, optional Sommelier-Tasting.</li>',
				'<li><strong>Tag 2:</strong> Vormittags Synthese-Session, Lunch, Verabschiedung.</li>',
			],
			[
				'Workshops auf einem Golfplatz laufen anders',
				'Wie ein typischer Workshop-Tag mit uns aussieht',
				'<li><strong>Vormittag:</strong> Ankunft im Golfclub, Begrüßung mit Kaffee und Kuchen am Buffet, dann Workshop Teil 1 im Konferenzraum.</li>',
				'<li><strong>Mittag:</strong> Buffet auf der Clubterrasse, inklusive Getränke.</li>',
				'<li><strong>Nachmittag:</strong> Workshop Teil 2 im Konferenzraum, zum Ausklang ein Golf-Grundlagenkurs mit einem unserer Golflehrer. Der Standard-Workshoptag liegt bei 190 € pro Person, mehrtägige Offsites mit Übernachtung planen wir als individuelles Event.</li>',
			],
			$content
		);
		wp_update_post( [
			'ID'           => $blog->ID,
			'post_title'   => 'Warum Workshops auf dem Golfplatz besser laufen',
			'post_name'    => 'wieso-workshops-im-gruenen',
			'post_content' => $content,
		] );
	}

	if ( function_exists( 'fge_flush_formats_in_use' ) ) {
		fge_flush_formats_in_use();
	}
}, 20 );
