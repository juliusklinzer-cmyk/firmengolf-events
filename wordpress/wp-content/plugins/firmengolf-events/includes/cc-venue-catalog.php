<?php
/**
 * Aus einem nicht gewählten Platz ein Katalog-Angebot machen.
 *
 * Die Platz-Pipeline (cc-venues.php) fragt je Anfrage mehrere Plätze an. Wer
 * am Ende nicht gewählt wird, bekommt eine Absage (cc-venue-mails.php). Genau
 * dieser Platz hat aber gerade Preise für eine konkrete Gruppe genannt, das
 * ist der beste Moment, ihn dauerhaft in den Katalog zu holen.
 *
 * Deshalb trägt die Absage-Mail einen signierten Link nach dem Muster von
 * cc-catalog.php (dort für den GEWÄHLTEN Platz nach dem Event). Ein Klick auf
 * Ja legt einen Event-Entwurf im Status „zur Prüfung" an, vorbefüllt aus der
 * Pipeline-Zeile. Stammdaten-Plätze (kein Portalzugang) werden dabei zum
 * Partner in Prüfung und bekommen die Einladungsmail mit ihrem Zugang.
 *
 * Alle Antwortdaten liegen in der Pipeline-Zeile selbst (catalog_*-Spalten),
 * weil derselbe Platz bei der nächsten Anfrage wieder in einer Pipeline steht.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Token und Link ───────────────────────────────────────────────────────────

/** Token für den Antwortlink, beim ersten Mal erzeugt und gespeichert. */
function fge_venue_catalog_token( int $venue_id ): string {
	$row = fge_venue_get( $venue_id );
	if ( ! $row ) {
		return '';
	}
	$t = (string) ( $row['catalog_token'] ?? '' );
	if ( '' === $t ) {
		$t = bin2hex( random_bytes( 20 ) );
		fge_venue_update( $venue_id, [ 'catalog_token' => $t ] );
	}
	return $t;
}

/** Pipeline-Zeile zu einem Token, null wenn unbekannt. */
function fge_venue_catalog_by_token( string $token ): ?array {
	global $wpdb;
	$token = trim( $token );
	if ( '' === $token || ! preg_match( '/^[a-f0-9]{40}$/', $token ) ) {
		return null;
	}
	$t = fge_venues_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE catalog_token = %s", $token ), ARRAY_A );
	return $row ?: null;
}

function fge_venue_catalog_link( int $venue_id ): string {
	$token = fge_venue_catalog_token( $venue_id );
	return '' !== $token ? home_url( '/platz-vorschlag/' . $token . '/' ) : home_url( '/' );
}

// ── Stammdaten-Brücke ────────────────────────────────────────────────────────
// Die Funktionen aus stammdaten-import.php entstehen parallel; hier mit Guard
// und Fallback über das Status-Meta, damit nichts voneinander abhängt.

function fge_venue_catalog_is_stammdaten( int $pid ): bool {
	if ( function_exists( 'fge_partner_is_stammdaten' ) ) {
		return fge_partner_is_stammdaten( $pid );
	}
	return 'stammdaten' === (string) get_post_meta( $pid, '_fge_partner_status', true );
}

function fge_venue_catalog_promote( int $pid ): bool {
	if ( function_exists( 'fge_partner_promote_from_stammdaten' ) ) {
		return fge_partner_promote_from_stammdaten( $pid );
	}
	update_post_meta( $pid, '_fge_partner_status', 'in_pruefung' );
	return true;
}

// ── Vorschlag ────────────────────────────────────────────────────────────────

/** Preis in Worten, z. B. „33,00 € brutto p.P." oder „450,00 € netto pauschal". */
function fge_venue_catalog_price_text( float $price, string $basis, bool $gross ): string {
	if ( $price <= 0 ) {
		return '';
	}
	return number_format_i18n( $price, 2 ) . ' € ' . ( $gross ? 'brutto' : 'netto' ) . ( 'pauschal' === $basis ? ' pauschal' : ' p.P.' );
}

/**
 * Der Vorschlag in Stichpunkten, dieselben Schlüssel wie fge_catalog_proposal().
 * Quelle ist die Pipeline-Zeile, nicht der Angebots-Snapshot: der Snapshot
 * gehört zum gewählten Platz, dieser hier wurde nicht gewählt.
 */
function fge_venue_catalog_proposal( int $req, array $venue ): array {
	$pid  = (int) ( $venue['partner_id'] ?? 0 );
	$data = function_exists( 'fge_get_request_email_data' ) ? fge_get_request_email_data( $req ) : [];

	$club = trim( (string) get_post_meta( $pid, '_fge_public_golfclub_name', true ) );
	if ( '' === $club ) {
		$club = (string) get_the_title( $pid );
	}
	$city     = trim( (string) get_post_meta( $pid, '_fge_city', true ) );
	$location = $club . ( '' !== $city ? ', ' . $city : '' );

	$title = trim( (string) ( $data['event_title'] ?? '' ) );
	if ( '' === $title ) {
		$title = 'Firmengolf-Event bei ' . $club;
	}

	// Positionen: nur verfügbare, das Green Fee ist der Grundpreis, keine Leistung.
	$includes   = [];
	$item_costs = [];
	foreach ( fge_venue_items_get( (int) ( $venue['id'] ?? 0 ) ) as $it ) {
		$label = trim( (string) ( $it['label'] ?? '' ) );
		if ( 'green_fee' === (string) ( $it['wish_key'] ?? '' ) || '' === $label || (int) ( $it['available'] ?? 1 ) <= 0 ) {
			continue;
		}
		$includes[] = $label;
		$txt = fge_venue_catalog_price_text( (float) ( $it['price'] ?? 0 ), (string) ( $it['price_basis'] ?? 'person' ), (int) ( $it['price_gross'] ?? 1 ) > 0 );
		if ( '' !== $txt ) {
			$item_costs[] = $label . ' ' . $txt;
		}
	}

	$pax = (int) get_post_meta( $req, '_fge_expected_participants', true );
	if ( $pax <= 0 ) {
		$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
		$pax  = (int) ( $snap['participants'] ?? 0 );
	}

	$base = fge_venue_catalog_price_text( (float) ( $venue['price'] ?? 0 ), (string) ( $venue['price_basis'] ?? 'person' ), (int) ( $venue['price_gross'] ?? 1 ) > 0 );
	$cost = array_values( array_filter( array_merge( [ $base ], $item_costs ) ) );

	return [
		'title'     => $title,
		'location'  => $location,
		'schedule'  => '',
		'includes'  => $includes,
		'pax_min'   => $pax > 0 ? max( 4, (int) floor( $pax * 0.6 ) ) : 0,
		'pax_max'   => $pax > 0 ? (int) ceil( $pax * 2 ) : 0,
		'pax_was'   => $pax,
		'partner'   => $pid,
		'cost_text' => implode( ', ', $cost ),
	];
}

// ── Antwortseite ─────────────────────────────────────────────────────────────

add_action( 'init', static function (): void {
	add_rewrite_rule( '^platz-vorschlag/([^/]+)/?$', 'index.php?fge_venue_catalog=$matches[1]', 'top' );
}, 11 );

add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_venue_catalog';
	return $vars;
} );

add_action( 'template_redirect', static function (): void {
	$token = (string) get_query_var( 'fge_venue_catalog' );
	if ( '' === $token ) {
		return;
	}
	$token = sanitize_text_field( $token );
	$venue = fge_venue_catalog_by_token( $token );
	$req   = $venue ? (int) $venue['request_id'] : 0;
	if ( ! $venue || $req <= 0 || 'firmengolf_request' !== get_post_type( $req ) ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}

	// Antwort entgegennehmen.
	$done = '';
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'fge_venue_catalog_' . $token ) ) {
		$answer = 'ja' === sanitize_key( wp_unslash( $_POST['answer'] ?? '' ) ) ? 'ja' : 'nein';
		$note   = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		$done   = fge_venue_catalog_handle_answer( $req, $venue, $answer, $note );
		$venue  = fge_venue_get( (int) $venue['id'] ) ?: $venue;
	}

	fge_venue_catalog_render_page( $req, $venue, $token, $done );
	exit;
}, 1 );

/**
 * Antwort verarbeiten, gibt 'ja' oder 'nein' zurück, '' wenn schon beantwortet.
 *
 * Bei Ja:
 *   a) Stammdaten-Platz wird Partner in Prüfung (ohne Mail, siehe Import)
 *   b) Event-Entwurf „zur Prüfung", vorbefüllt aus der Pipeline-Zeile
 *   c) Stammdaten-Platz: interne Mail plus Einladungsmail mit Portalzugang
 *      (kein fge_event_submitted, der Platz hat noch keinen Zugang)
 *   d) aktiver Partner: fge_event_submitted wie beim Katalog nach dem Event
 */
function fge_venue_catalog_handle_answer( int $req, array $venue, string $answer, string $note ): string {
	if ( '' !== (string) ( $venue['catalog_answer'] ?? '' ) ) {
		return ''; // Zweiter Klick ändert nichts mehr.
	}
	$venue_id = (int) ( $venue['id'] ?? 0 );
	$pid      = (int) ( $venue['partner_id'] ?? 0 );
	if ( $venue_id <= 0 || $pid <= 0 ) {
		return '';
	}
	$answer = 'ja' === $answer ? 'ja' : 'nein';
	$name   = (string) get_the_title( $pid );

	$data = [
		'catalog_answer'      => $answer,
		'catalog_answered_at' => current_time( 'mysql' ),
	];
	if ( '' !== $note ) {
		$data['catalog_note'] = $note;
	}

	if ( 'nein' === $answer ) {
		fge_venue_update( $venue_id, $data );
		fge_activity_add( $req, 'venue', sprintf( '%s möchte das Angebot nicht dauerhaft anbieten%s', $name, '' !== $note ? ': ' . $note : '' ) );
		return 'nein';
	}

	// a) Stammdaten-Platz wird Partner in Prüfung.
	$was_stammdaten = fge_venue_catalog_is_stammdaten( $pid );
	if ( $was_stammdaten ) {
		fge_venue_catalog_promote( $pid );
	}

	// b) Entwurf anlegen.
	$event_id = 0;
	if ( function_exists( 'fge_catalog_create_event_from' ) ) {
		$event_id = fge_catalog_create_event_from( fge_venue_catalog_proposal( $req, $venue ), $pid, $req );
	}
	if ( $event_id > 0 ) {
		update_post_meta( $event_id, '_fge_catalog_from_venue', $venue_id );
		$data['catalog_event_id'] = $event_id;
	}
	fge_venue_update( $venue_id, $data );

	if ( $was_stammdaten ) {
		// c) intern Bescheid geben.
		fge_venue_catalog_notify_internal( $req, $pid, $event_id, true );
		// d) Zugang per Einladungsmail.
		if ( function_exists( 'fge_send_partner_invite_email' ) ) {
			fge_mail_log_context( $req, 'partner_invite' );
			fge_send_partner_invite_email( $pid, fge_cc_partner_email( $pid ) );
			fge_mail_log_context_clear();
		}
		fge_activity_add( $req, 'venue', sprintf( '%s möchte das Angebot dauerhaft anbieten, Entwurf angelegt, Zugang eingeladen', $name ) );
	} else {
		if ( $event_id > 0 ) {
			do_action( 'fge_event_submitted', $event_id, $pid, true );
		} else {
			fge_venue_catalog_notify_internal( $req, $pid, 0, false );
		}
		fge_activity_add( $req, 'venue', sprintf( '%s möchte das Angebot dauerhaft anbieten, Entwurf angelegt', $name ) );
	}
	return 'ja';
}

/** Interne Mail: ein Platz will in den Katalog. */
function fge_venue_catalog_notify_internal( int $req, int $pid, int $event_id, bool $invited ): bool {
	$to   = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$name = (string) get_the_title( $pid );
	$ref  = function_exists( 'fge_request_number' ) ? fge_request_number( $req ) : ( '#' . $req );

	$subject = 'Platz will dauerhaft anbieten: ' . $name . ' (' . $ref . ')';
	$content = '
		<p style="margin:0 0 16px;"><strong>' . esc_html( $name ) . '</strong> hat aus der Absage zu ' . esc_html( $ref ) . ' heraus zugesagt, das Angebot dauerhaft bei Firmengolf zu zeigen.</p>
		' . ( $event_id > 0
			? '<p style="margin:0 0 16px;">Der Entwurf #' . (int) $event_id . ' liegt zur Prüfung. Preise und Bilder trägt der Platz im Portal nach.</p><p style="margin:0 0 16px;">' . fge_email_button( admin_url( 'post.php?post=' . $event_id . '&action=edit' ), 'Entwurf öffnen' ) . '</p>'
			: '<p style="margin:0 0 16px;">Der Entwurf konnte nicht angelegt werden, bitte im Control Center nachsehen.</p>' ) . '
		' . ( $invited ? '<p style="margin:0;color:#6C736E;font-size:13px;">Der Platz war bis eben Stammdaten, er steht jetzt in Prüfung und hat die Einladungsmail mit dem Portalzugang bekommen.</p>' : '' ) . '
	';

	fge_mail_log_context( $req, 'venue_catalog_internal' );
	$ok = (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	fge_mail_log_context_clear();
	return $ok;
}

/** Die Seite, die der Platz aus der Absage-Mail heraus sieht. */
function fge_venue_catalog_render_page( int $req, array $venue, string $token, string $done ): void {
	$p      = fge_venue_catalog_proposal( $req, $venue );
	$answer = '' !== $done ? $done : (string) ( $venue['catalog_answer'] ?? '' );

	nocache_headers();
	?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<title>Angebot dauerhaft anbieten?</title>
	<link rel="stylesheet" href="<?php echo esc_url( plugins_url( 'assets/css/fge-cc.css', FGE_DIR . 'firmengolf-events.php' ) . '?v=' . FGE_VERSION ); ?>">
</head>
<body class="cc cc--plain">
<main class="cc-plain-wrap">
	<h1>Firmengolf</h1>
	<?php if ( 'ja' === $answer ) : ?>
		<section class="cc-card">
			<h2>Danke, wir legen es an</h2>
			<p>Das Angebot liegt jetzt als Entwurf bei uns zur Prüfung. Euren Zugang zum Partnerportal bekommt ihr per Mail, dort könnt ihr Bilder, Preise und Text anpassen, bevor es live geht.</p>
			<p class="cc-muted">Wir melden uns, sobald es sichtbar ist.</p>
		</section>
	<?php elseif ( 'nein' === $answer ) : ?>
		<section class="cc-card">
			<h2>Alles klar</h2>
			<p>Wir zeigen das Angebot nicht dauerhaft. Für einzelne Anfragen kommen wir gern wieder auf euch zu.</p>
		</section>
	<?php else : ?>
		<section class="cc-card">
			<h2>Dieses Angebot dauerhaft bei Firmengolf sichtbar machen?</h2>
			<p>Ihr habt uns für eine Firmengruppe Preise genannt. Genau so könnten Firmen künftig direkt bei euch anfragen, ohne dass wir jedes Mal neu telefonieren. So hätten wir es aufgeschrieben:</p>
			<dl class="cc-facts">
				<?php
				fge_cc_fact( 'Titel', $p['title'] );
				fge_cc_fact( 'Ort', $p['location'] );
				fge_cc_fact( 'Gruppengröße', $p['pax_min'] > 0 ? $p['pax_min'] . ' bis ' . $p['pax_max'] . ' Personen' : '' );
				fge_cc_fact( 'Inklusive', implode( ', ', $p['includes'] ) );
				fge_cc_fact( 'Euer Preis', $p['cost_text'] );
				?>
			</dl>
			<p class="cc-muted">Bei Ja bekommt ihr einen Zugang per Mail, Preise und Bilder bestimmt ihr selbst. Nichts geht ohne eure Freigabe live, und für euch ist das kostenlos.</p>

			<form method="post" class="cc-form">
				<?php wp_nonce_field( 'fge_venue_catalog_' . $token ); ?>
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

// ── Cockpit ──────────────────────────────────────────────────────────────────

/** Kurzstatus für die Pipeline-Karte, leer solange keine Antwort da ist. */
function fge_venue_catalog_status_html( array $venue ): string {
	$answer = (string) ( $venue['catalog_answer'] ?? '' );
	if ( 'ja' === $answer ) {
		$event_id = (int) ( $venue['catalog_event_id'] ?? 0 );
		if ( $event_id > 0 ) {
			$edit = admin_url( 'post.php?post=' . $event_id . '&action=edit' );
			return '<span class="cc-muted">Möchte das Angebot dauerhaft anbieten, <a href="' . esc_url( $edit ) . '">Entwurf #' . $event_id . '</a> liegt zur Prüfung</span>';
		}
		return '<span class="cc-muted">Möchte das Angebot dauerhaft anbieten, Entwurf fehlt noch</span>';
	}
	if ( 'nein' === $answer ) {
		$note = trim( (string) ( $venue['catalog_note'] ?? '' ) );
		return '<span class="cc-muted">Möchte nicht dauerhaft anbieten' . ( '' !== $note ? ': ' . esc_html( $note ) : '' ) . '</span>';
	}
	return '';
}
