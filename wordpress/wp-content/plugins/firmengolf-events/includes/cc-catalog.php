<?php
/**
 * Aus einer Anfrage ein Katalog-Event machen.
 *
 * Lief eine Anfrage über ein Platzhalter-Event, war die ganze Arbeit einmalig:
 * telefonisch verhandelt, einmal durchgeführt, danach wieder bei null. Diese
 * Datei fragt den Platz nach dem Event, ob er genau dieses Paket dauerhaft auf
 * firmengolf.app anbieten möchte, und legt bei Zustimmung ein Event im Status
 * „zur Prüfung" an, vorbefüllt aus dem Angebots-Snapshot.
 *
 * Der Platz antwortet direkt aus der Mail über einen signierten Link, nach dem
 * Muster der Terminabstimmung. Kein Login, kein Formularmarathon.
 *
 * Damit wird aus Handarbeit ein Katalogprodukt: schlanker für uns, wiederholbar
 * für den Platz, und die nächste Anfrage auf dieses Event läuft automatisch.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Token für den Antwortlink, beim ersten Mal erzeugt. */
function fge_catalog_token( int $req ): string {
	$t = (string) get_post_meta( $req, '_fge_catalog_token', true );
	if ( '' === $t ) {
		$t = bin2hex( random_bytes( 20 ) );
		update_post_meta( $req, '_fge_catalog_token', $t );
	}
	return $t;
}

/** Anfrage zu einem Token, 0 wenn unbekannt. */
function fge_catalog_request_by_token( string $token ): int {
	if ( '' === trim( $token ) ) {
		return 0;
	}
	$found = get_posts( [
		'post_type'        => 'firmengolf_request',
		'post_status'      => 'any',
		'numberposts'      => 1,
		'fields'           => 'ids',
		'meta_key'         => '_fge_catalog_token',
		'meta_value'       => $token,
		'suppress_filters' => true,
	] );
	return $found ? (int) $found[0] : 0;
}

function fge_catalog_link( int $req ): string {
	return home_url( '/event-vorschlag/' . fge_catalog_token( $req ) . '/' );
}

/** Antwort des Platzes: '', 'ja' oder 'nein'. */
function fge_catalog_answer( int $req ): string {
	return (string) get_post_meta( $req, '_fge_catalog_answer', true );
}

/**
 * Lohnt der Vorschlag für diese Anfrage, und wenn nein, warum nicht.
 * Rückgabe leer heißt: kann vorgeschlagen werden.
 */
function fge_catalog_blocker( int $req ): string {
	if ( 'accepted' !== (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
		return 'Erst wenn das Event gebucht ist.';
	}
	$partner_id = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
	if ( $partner_id <= 0 ) {
		return 'Der Anfrage ist kein Platz zugeordnet.';
	}
	if ( '' === fge_cc_partner_email( $partner_id ) ) {
		return 'Der Platz hat keine Kontaktmail.';
	}
	$answer = fge_catalog_answer( $req );
	if ( 'ja' === $answer ) {
		return 'Der Platz hat bereits zugestimmt, das Event liegt zur Prüfung.';
	}
	if ( 'nein' === $answer ) {
		return 'Der Platz möchte dieses Event nicht dauerhaft anbieten.';
	}
	return '';
}

/** Der Vorschlag in Stichpunkten, aus dem Angebots-Snapshot. */
function fge_catalog_proposal( int $req ): array {
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$pid  = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );

	$includes = array_values( array_filter( array_map( 'strval', (array) ( $snap['includes'] ?? [] ) ) ) );
	$pax      = (int) ( $snap['participants'] ?? 0 );

	return [
		'title'     => (string) ( $snap['event_title'] ?? 'Firmengolf-Event' ),
		'location'  => (string) ( $snap['location'] ?? '' ),
		'schedule'  => (string) ( $snap['schedule'] ?? '' ),
		'includes'  => $includes,
		'pax_min'   => $pax > 0 ? max( 4, (int) floor( $pax * 0.6 ) ) : 0,
		'pax_max'   => $pax > 0 ? (int) ceil( $pax * 2 ) : 0,
		'pax_was'   => $pax,
		'partner'   => $pid,
		'cost_text' => function_exists( 'fge_partner_cost_text' ) ? fge_partner_cost_text( $req, $pax ) : '',
	];
}

// ── Mail an den Platz ────────────────────────────────────────────────────────

function fge_catalog_send_proposal( int $req ): string {
	$blocker = fge_catalog_blocker( $req );
	if ( '' !== $blocker ) {
		return $blocker;
	}
	$p   = fge_catalog_proposal( $req );
	$pid = (int) $p['partner'];
	$to  = fge_cc_partner_email( $pid );
	$co  = function_exists( 'fge_company' ) ? fge_company() : [];

	$list = '';
	foreach ( $p['includes'] as $i ) {
		$list .= '<li style="margin-bottom:3px;">' . esc_html( $i ) . '</li>';
	}

	$row = static function ( string $k, string $v ): string {
		return '' === trim( wp_strip_all_tags( $v ) ) ? '' : '<tr><td style="padding:5px 16px 5px 0;color:#555;white-space:nowrap;vertical-align:top;"><strong>' . esc_html( $k ) . '</strong></td><td style="padding:5px 0;color:#1a1a1a;">' . $v . '</td></tr>';
	};
	$rows = $row( 'Titel', esc_html( $p['title'] ) )
		. $row( 'Ort', esc_html( $p['location'] ) )
		. $row( 'Ablauf', '' !== $p['schedule'] ? nl2br( esc_html( $p['schedule'] ) ) : '' )
		. $row( 'Gruppengröße', $p['pax_min'] > 0 ? $p['pax_min'] . ' bis ' . $p['pax_max'] . ' Personen' : '' )
		. $row( 'Euer Preis', esc_html( $p['cost_text'] ) );

	$subject = 'Wollt ihr das dauerhaft anbieten? ' . $p['title'];
	$content = '
		<p style="margin:0 0 16px;">' . esc_html( fge_venue_greeting( $pid ) ) . '</p>
		<p style="margin:0 0 16px;">das Event bei euch hat gut funktioniert. Wir würden es gern dauerhaft auf firmengolf.app anbieten, damit Firmen es direkt bei euch buchen können, ohne dass wir jedes Mal neu telefonieren.</p>
		<p style="margin:0 0 16px;">So hätten wir es aufgeschrieben:</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows . '</table>
		' . ( '' !== $list ? '<p style="margin:0 0 4px;font-weight:600;">Inklusive</p><ul style="margin:0 0 16px;padding-left:20px;">' . $list . '</ul>' : '' ) . '
		<p style="margin:0 0 16px;">Ein Klick genügt. Bei Ja legen wir das Event als Entwurf in eurem Partnerportal an, ihr könnt Bilder, Preise und Text noch anpassen, bevor es live geht.</p>
		<p style="margin:0 0 18px;">' . fge_email_button( fge_catalog_link( $req ), 'Antworten' ) . '</p>
		<p style="margin:0;color:#6C736E;font-size:13px;">Kein Login nötig. Der Link gehört zu eurem Platz und zu diesem Vorschlag.</p>
	';

	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
	if ( ! empty( $co['email_owner'] ) && is_email( $co['email_owner'] ) ) {
		$headers[] = 'Bcc: ' . $co['email_owner'];
	}

	fge_mail_log_context( $req, 'catalog_proposal' );
	$ok = (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), $headers );
	fge_mail_log_context_clear();

	if ( ! $ok ) {
		return 'Die Mail konnte nicht verschickt werden.';
	}
	update_post_meta( $req, '_fge_catalog_asked_at', current_time( 'mysql' ) );
	fge_activity_add( $req, 'venue', sprintf( 'Katalog-Vorschlag an %s gesendet', get_the_title( $pid ) ) );
	return '';
}

// ── Antwortseite ─────────────────────────────────────────────────────────────

add_action( 'init', static function (): void {
	add_rewrite_rule( '^event-vorschlag/([^/]+)/?$', 'index.php?fge_catalog=$matches[1]', 'top' );
}, 11 );

add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_catalog';
	return $vars;
} );

add_action( 'template_redirect', static function (): void {
	$token = (string) get_query_var( 'fge_catalog' );
	if ( '' === $token ) {
		return;
	}
	$req = fge_catalog_request_by_token( sanitize_text_field( $token ) );
	if ( $req <= 0 ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}

	// Antwort entgegennehmen.
	$done = '';
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'fge_catalog_' . $token ) ) {
		$answer = 'ja' === sanitize_key( wp_unslash( $_POST['answer'] ?? '' ) ) ? 'ja' : 'nein';
		$note   = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		$done   = fge_catalog_handle_answer( $req, $answer, $note );
	}

	fge_catalog_render_page( $req, $token, $done );
	exit;
}, 1 );

/** Antwort verarbeiten, gibt 'ja' oder 'nein' zurück. */
function fge_catalog_handle_answer( int $req, string $answer, string $note ): string {
	if ( '' !== fge_catalog_answer( $req ) ) {
		return fge_catalog_answer( $req ); // Zweiter Klick ändert nichts mehr.
	}
	update_post_meta( $req, '_fge_catalog_answer', $answer );
	update_post_meta( $req, '_fge_catalog_answered_at', current_time( 'mysql' ) );
	if ( '' !== $note ) {
		update_post_meta( $req, '_fge_catalog_note', $note );
	}

	$pid = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
	if ( 'ja' === $answer ) {
		$event_id = fge_catalog_create_event( $req );
		if ( $event_id > 0 ) {
			update_post_meta( $req, '_fge_catalog_event_id', $event_id );
			do_action( 'fge_event_submitted', $event_id, $pid, true );
		}
		fge_activity_add( $req, 'venue', sprintf( '%s möchte das Event dauerhaft anbieten, Entwurf angelegt', get_the_title( $pid ) ) );
	} else {
		fge_activity_add( $req, 'venue', sprintf( '%s möchte das Event nicht dauerhaft anbieten%s', get_the_title( $pid ), '' !== $note ? ': ' . $note : '' ) );
	}
	return $answer;
}

/**
 * Event als Entwurf anlegen, vorbefüllt aus dem Angebot.
 *
 * Preise bleiben bewusst leer: die setzt der Platz selbst im Portal, und dort
 * gilt seine Kalkulation, nicht der einmal telefonisch verhandelte Sonderpreis.
 */
function fge_catalog_create_event( int $req ): int {
	$p = fge_catalog_proposal( $req );
	return fge_catalog_create_event_from( $p, (int) $p['partner'], $req );
}

/**
 * Der eigentliche Entwurf aus einem Vorschlag (Schlüssel wie fge_catalog_proposal).
 * Wird auch von der Platz-Pipeline genutzt (cc-venue-catalog.php), dort kommt
 * der Vorschlag nicht aus dem Angebots-Snapshot, sondern aus der Pipeline-Zeile.
 */
function fge_catalog_create_event_from( array $p, int $pid, int $req ): int {
	if ( $pid <= 0 ) {
		return 0;
	}
	$p += [ 'title' => 'Firmengolf-Event', 'location' => '', 'schedule' => '', 'includes' => [], 'pax_min' => 0, 'pax_max' => 0 ];

	$event_id = (int) wp_insert_post( [
		'post_type'    => 'firmengolf_event',
		'post_status'  => 'publish',
		'post_title'   => $p['title'],
		'post_content' => '' !== $p['schedule'] ? $p['schedule'] : '',
	] );
	if ( $event_id <= 0 ) {
		return 0;
	}

	update_post_meta( $event_id, '_fge_event_status', 'zur_pruefung' );
	update_post_meta( $event_id, '_fge_provider_type', 'golfplatz_partner' );
	update_post_meta( $event_id, '_fge_assigned_partner_id', $pid );
	update_post_meta( $event_id, '_fge_event_location', $p['location'] );
	update_post_meta( $event_id, '_fge_event_dayflow', $p['schedule'] );
	update_post_meta( $event_id, '_fge_event_includes', $p['includes'] );
	if ( $p['pax_min'] > 0 ) {
		update_post_meta( $event_id, '_fge_participants_min', $p['pax_min'] );
		update_post_meta( $event_id, '_fge_participants_max', $p['pax_max'] );
	}
	foreach ( [ '_fge_city', '_fge_region', '_fge_public_golfclub_name' ] as $key ) {
		$val = (string) get_post_meta( $pid, $key, true );
		if ( '' !== $val ) {
			update_post_meta( $event_id, $key, $val );
		}
	}
	update_post_meta( $event_id, '_fge_catalog_from_request', $req );

	return $event_id;
}

/** Die Seite, die der Platz aus der Mail heraus sieht. */
function fge_catalog_render_page( int $req, string $token, string $done ): void {
	$p      = fge_catalog_proposal( $req );
	$answer = '' !== $done ? $done : fge_catalog_answer( $req );

	nocache_headers();
	?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<title>Event dauerhaft anbieten?</title>
	<link rel="stylesheet" href="<?php echo esc_url( plugins_url( 'assets/css/fge-cc.css', FGE_DIR . 'firmengolf-events.php' ) . '?v=' . FGE_VERSION ); ?>">
</head>
<body class="cc cc--plain">
<main class="cc-plain-wrap">
	<h1>Firmengolf</h1>
	<?php if ( 'ja' === $answer ) : ?>
		<section class="cc-card">
			<h2>Danke, wir legen es an</h2>
			<p>Das Event liegt jetzt als Entwurf in eurem Partnerportal. Ihr könnt Bilder, Preise und Text anpassen, danach geben wir es frei und es ist buchbar.</p>
			<p class="cc-muted">Wir melden uns, sobald es live ist.</p>
		</section>
	<?php elseif ( 'nein' === $answer ) : ?>
		<section class="cc-card">
			<h2>Alles klar</h2>
			<p>Wir bieten das Event nicht dauerhaft an. Für einzelne Anfragen kommen wir gern wieder auf euch zu.</p>
		</section>
	<?php else : ?>
		<section class="cc-card">
			<h2>Wollt ihr das dauerhaft anbieten?</h2>
			<p>Das Event, das gerade bei euch stattgefunden hat, könnten Firmen künftig direkt buchen. So hätten wir es aufgeschrieben:</p>
			<dl class="cc-facts">
				<?php
				fge_cc_fact( 'Titel', $p['title'] );
				fge_cc_fact( 'Ort', $p['location'] );
				fge_cc_fact( 'Ablauf', $p['schedule'] );
				fge_cc_fact( 'Gruppengröße', $p['pax_min'] > 0 ? $p['pax_min'] . ' bis ' . $p['pax_max'] . ' Personen' : '' );
				fge_cc_fact( 'Inklusive', implode( ', ', $p['includes'] ) );
				?>
			</dl>
			<p class="cc-muted">Bei Ja legen wir einen Entwurf in eurem Partnerportal an. Preise und Bilder bestimmt ihr dort selbst, nichts geht ohne eure Freigabe live.</p>

			<form method="post" class="cc-form">
				<?php wp_nonce_field( 'fge_catalog_' . $token ); ?>
				<label class="cc-field cc-field--wide"><span>Anmerkung (freiwillig)</span>
					<textarea name="note" rows="2" placeholder="Was wir dabei beachten sollten"></textarea></label>
				<p class="cc-venue-actions">
					<button type="submit" name="answer" value="ja" class="cc-btn cc-btn--primary">Ja, gern anbieten</button>
					<button type="submit" name="answer" value="nein" class="cc-btn">Lieber nicht</button>
				</p>
			</form>
		</section>
	<?php endif; ?>
	<p class="cc-muted cc-plain-foot">Firmengolf · Visionpunch UG (haftungsbeschränkt)</p>
</main>
</body>
</html>
	<?php
}
