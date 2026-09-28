<?php
/**
 * Eventtag-Details vom Platz selbst, ohne Login.
 *
 * Nach der Buchung fehlen genau die Angaben, die nur der Platz kennt:
 * Ansprechpartner vor Ort mit Telefon, Golflehrer, Treffpunkt, Startzeit und
 * was die Gruppe mitbringen soll. Bisher hat Julius das telefonisch erfragt
 * und in die Box „Schritt 4: Event-Tag" getippt. Jetzt trägt der Platz es über
 * einen signierten Link aus der Auftragsbestätigung selbst ein, nach dem
 * Muster von cc-venue-catalog.php.
 *
 * Die Werte landen in denselben Metas wie die Cockpit-Box (fge_day_fields),
 * der Zeitpunkt in der Pipeline-Zeile (details_at). Sobald Startzeit und
 * Treffpunkt stehen, geht die Ablauf-Info einmalig an den Kunden.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Token und Link ───────────────────────────────────────────────────────────

/** Token für den Detail-Link, beim ersten Mal erzeugt und gespeichert. */
function fge_venue_detail_token( int $venue_id ): string {
	$row = fge_venue_get( $venue_id );
	if ( ! $row ) {
		return '';
	}
	$t = (string) ( $row['detail_token'] ?? '' );
	if ( '' === $t ) {
		$t = bin2hex( random_bytes( 20 ) );
		fge_venue_update( $venue_id, [ 'detail_token' => $t ] );
	}
	return $t;
}

/** Pipeline-Zeile zu einem Detail-Token, null wenn unbekannt. */
function fge_venue_detail_by_token( string $token ): ?array {
	global $wpdb;
	$token = trim( $token );
	if ( '' === $token || ! preg_match( '/^[a-f0-9]{40}$/', $token ) ) {
		return null;
	}
	$t = fge_venues_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE detail_token = %s", $token ), ARRAY_A );
	return $row ?: null;
}

function fge_venue_detail_link( int $venue_id ): string {
	$token = fge_venue_detail_token( $venue_id );
	return '' !== $token ? home_url( '/platz-details/' . $token . '/' ) : home_url( '/' );
}

// ── Pipeline-Zeile des gebuchten Platzes ─────────────────────────────────────

/**
 * Pipeline-Zeile des zugeordneten Platzes, null wenn keine da ist.
 *
 * Gewählte Zeile zuerst, sonst irgendeine zu diesem Platz. Mit $create wird bei
 * Partner-Events ohne Pipeline eine Zeile angelegt und als gewählt markiert,
 * damit der Link ein Zuhause hat (fge_venue_add ist INSERT IGNORE, deshalb
 * erst suchen, dann anlegen).
 */
function fge_venue_detail_row_for( int $req, int $partner_id, bool $create = false ): ?array {
	global $wpdb;
	if ( $req <= 0 || $partner_id <= 0 || ! function_exists( 'fge_venues_table' ) ) {
		return null;
	}
	$t = fge_venues_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$t} WHERE request_id = %d AND partner_id = %d ORDER BY (status = 'gewaehlt') DESC, id ASC LIMIT 1",
		$req,
		$partner_id
	), ARRAY_A );
	if ( $row ) {
		return $row;
	}
	if ( ! $create || ! function_exists( 'fge_venue_add' ) ) {
		return null;
	}
	$id = fge_venue_add( $req, $partner_id );
	if ( $id <= 0 ) {
		return null;
	}
	fge_venue_update( $id, [ 'status' => 'gewaehlt' ] );
	return fge_venue_get( $id );
}

/** Bis wann der Platz eintragen soll: fünf Tage vor dem Termin, leer ohne lesbaren oder schon nahen Termin. */
function fge_venue_detail_deadline( int $req ): string {
	$ts = function_exists( 'fge_cc_event_date' ) ? fge_cc_event_date( $req ) : 0;
	if ( $ts <= 0 ) {
		return '';
	}
	$due = $ts - 5 * DAY_IN_SECONDS;
	return $due > time() ? wp_date( 'd.m.Y', $due ) : '';
}

/** Änderungen bis zum Vortag: solange der Termin noch nicht heute ist. */
function fge_venue_detail_editable( int $req ): bool {
	$ts = function_exists( 'fge_cc_event_date' ) ? fge_cc_event_date( $req ) : 0;
	return $ts <= 0 || ! function_exists( 'fge_cc_today' ) || $ts > fge_cc_today();
}

// ── Seite ────────────────────────────────────────────────────────────────────

add_action( 'init', static function (): void {
	add_rewrite_rule( '^platz-details/([^/]+)/?$', 'index.php?fge_venue_details=$matches[1]', 'top' );
}, 11 );

add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_venue_details';
	return $vars;
} );

add_action( 'template_redirect', static function (): void {
	$token = (string) get_query_var( 'fge_venue_details' );
	if ( '' === $token ) {
		return;
	}
	$token = sanitize_text_field( $token );
	$venue = fge_venue_detail_by_token( $token );
	$req   = $venue ? (int) $venue['request_id'] : 0;
	$pid   = $venue ? (int) $venue['partner_id'] : 0;
	// Nur der gebuchte Platz einer angenommenen Anfrage darf hier rein.
	if ( ! $venue || $req <= 0 || $pid <= 0
		|| 'firmengolf_request' !== get_post_type( $req )
		|| 'accepted' !== (string) get_post_meta( $req, '_fge_offer_status', true )
		|| $pid !== (int) get_post_meta( $req, '_fge_assigned_partner_id', true ) ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}

	$saved = false;
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'fge_venue_details_' . $token )
		&& fge_venue_detail_editable( $req ) ) {
		$saved = fge_venue_detail_save( $req, $venue );
		$venue = fge_venue_get( (int) $venue['id'] ) ?: $venue;
	}

	fge_venue_detail_render_page( $req, $venue, $token, $saved );
	exit;
}, 1 );

/**
 * Formular speichern: Metas wie in der Cockpit-Box, Zeitpunkt in der Zeile,
 * einmalig die Ablauf-Info an den Kunden, dann die interne Mail.
 */
function fge_venue_detail_save( int $req, array $venue ): bool {
	$venue_id = (int) ( $venue['id'] ?? 0 );
	$pid      = (int) ( $venue['partner_id'] ?? 0 );
	if ( $venue_id <= 0 || $pid <= 0 ) {
		return false;
	}
	$update = '' !== (string) ( $venue['details_at'] ?? '' );

	foreach ( fge_day_fields() as $k => [ , $type ] ) {
		$raw = wp_unslash( $_POST[ 'fge_' . $k ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$val = trim( 'textarea' === $type ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw ) );
		if ( '' !== $val ) {
			update_post_meta( $req, '_fge_' . $k, $val );
		} else {
			delete_post_meta( $req, '_fge_' . $k );
		}
	}
	fge_venue_update( $venue_id, [ 'details_at' => current_time( 'mysql' ) ] );

	$name = (string) get_the_title( $pid );
	fge_activity_add( $req, 'venue', $update
		? sprintf( 'Platz hat Eventtag-Details aktualisiert (%s)', $name )
		: sprintf( 'Platz hat Eventtag-Details eingetragen (%s)', $name ) );

	if ( function_exists( 'fge_is_demo_request' ) && fge_is_demo_request( $req ) ) {
		return true; // Musterumgebung: speichern ja, Mails nie.
	}

	// Das Gate liegt beim Aufrufer, fge_send_day_plan() prüft es nicht selbst.
	if ( '' === (string) get_post_meta( $req, '_fge_day_plan_sent', true ) && fge_day_plan_ready( $req ) ) {
		fge_send_day_plan( $req );
	}
	fge_venue_detail_notify_internal( $req, $pid, $update );
	return true;
}

/** Interne Mail mit den eingetragenen Werten. */
function fge_venue_detail_notify_internal( int $req, int $pid, bool $update ): bool {
	$to   = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	$name = (string) get_the_title( $pid );
	$ref  = fge_request_number( $req );

	$rows = '';
	foreach ( fge_day_fields() as $k => [ $label ] ) {
		$val   = trim( (string) get_post_meta( $req, '_fge_' . $k, true ) );
		$rows .= '<tr><td style="padding:5px 16px 5px 0;color:#555;white-space:nowrap;vertical-align:top;"><strong>' . esc_html( $label ) . '</strong></td><td style="padding:5px 0;color:#1a1a1a;">'
			. ( '' !== $val ? nl2br( esc_html( $val ) ) : '<span style="color:#B3261E;">fehlt</span>' ) . '</td></tr>';
	}

	if ( '' !== (string) get_post_meta( $req, '_fge_day_plan_sent', true ) ) {
		$plan = 'Die Ablauf-Info an den Kunden ist raus.';
	} elseif ( fge_day_plan_ready( $req ) ) {
		$plan = 'Die Ablauf-Info an den Kunden konnte nicht gesendet werden, bitte im Cockpit nachsehen.';
	} else {
		$plan = 'Startzeit oder Treffpunkt fehlen noch, die Ablauf-Info an den Kunden wartet.';
	}

	$admin   = function_exists( 'fge_format_request_admin_link' ) ? fge_format_request_admin_link( $req ) : admin_url();
	$subject = 'Platzdetails eingetragen: ' . $name . ' (' . $ref . ')';
	$content = '
		<p style="margin:0 0 16px;"><strong>' . esc_html( $name ) . '</strong> hat die Eventtag-Details zu ' . esc_html( $ref ) . ( $update ? ' aktualisiert' : ' eingetragen' ) . ':</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows . '</table>
		<p style="margin:0 0 16px;">' . esc_html( $plan ) . '</p>
		<p style="margin:0;">' . fge_email_button( $admin, 'Anfrage öffnen' ) . '</p>
	';

	fge_mail_log_context( $req, 'venue_details_internal' );
	$ok = (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	fge_mail_log_context_clear();
	return $ok;
}

/** Die Seite, die der Platz aus der Auftragsbestätigung heraus sieht. */
function fge_venue_detail_render_page( int $req, array $venue, string $token, bool $saved ): void {
	$data     = fge_get_request_email_data( $req );
	$snap     = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$v        = fge_day_values( $req );
	$ref      = fge_request_number( $req );
	$date     = (string) ( $snap['date'] ?? '' );
	$cust     = trim( $data['first_name'] . ' ' . $data['last_name'] );
	$cust_c   = trim( $cust . ( '' !== $data['phone'] ? ', ' . $data['phone'] : '' ), ', ' );
	$due      = fge_venue_detail_deadline( $req );
	$editable = fge_venue_detail_editable( $req );
	$done_at  = (string) ( $venue['details_at'] ?? '' );
	$co       = function_exists( 'fge_company' ) ? fge_company() : [];

	nocache_headers();
	?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<title>Details für den Eventtag</title>
	<link rel="stylesheet" href="<?php echo esc_url( plugins_url( 'assets/css/fge-cc.css', FGE_DIR . 'firmengolf-events.php' ) . '?v=' . FGE_VERSION ); ?>">
</head>
<body class="cc cc--plain">
<main class="cc-plain-wrap">
	<h1>Firmengolf</h1>
	<?php if ( $saved ) : ?>
		<section class="cc-card">
			<h2>Danke, gespeichert</h2>
			<p>Die Gruppe bekommt eure Angaben von uns vorab. Änderungen bis zum Vortag jederzeit über diesen Link.</p>
		</section>
	<?php endif; ?>
	<section class="cc-card">
		<h2>Details für den Eventtag</h2>
		<dl class="cc-facts">
			<?php
			fge_cc_fact( 'Vorgang', $ref );
			fge_cc_fact( 'Termin', $date );
			fge_cc_fact( 'Gruppe', fge_request_group_label( $req, true ) );
			fge_cc_fact( 'Ansprechpartner der Gruppe', $cust_c );
			fge_cc_fact( 'Paket', (string) ( $snap['event_title'] ?? $data['event_title'] ) );
			?>
		</dl>
		<?php if ( ! $editable ) : ?>
			<p class="cc-hint cc-hint--warn">Der Eventtag ist da oder vorbei, Änderungen bitte direkt an uns per Mail oder Telefon.</p>
			<dl class="cc-facts">
				<?php
				foreach ( fge_day_fields() as $k => [ $label ] ) {
					fge_cc_fact( $label, $v[ $k ] );
				}
				?>
			</dl>
		<?php else : ?>
			<?php if ( ! $saved ) : ?>
				<p>Damit die Gruppe alles Wichtige vorab bekommt, tragt bitte<?php echo '' !== $due ? ' bis <strong>' . esc_html( $due ) . '</strong>' : ''; ?> ein, wer sie vor Ort empfängt, wo und wann es losgeht und was sie mitbringen soll.<?php echo '' !== $done_at ? ' Zuletzt gespeichert am ' . esc_html( wp_date( 'd.m.Y H:i', (int) strtotime( $done_at ) ) ) . ' Uhr, Änderungen bis zum Vortag jederzeit hier.' : ''; ?></p>
			<?php endif; ?>
			<form method="post" class="cc-form">
				<?php wp_nonce_field( 'fge_venue_details_' . $token ); ?>
				<div class="cc-fields">
					<?php foreach ( fge_day_fields() as $k => [ $label, $type, $ph ] ) : ?>
						<?php if ( 'textarea' === $type ) : ?>
							<label class="cc-field cc-field--wide"><span><?php echo esc_html( $label ); ?></span>
								<textarea name="fge_<?php echo esc_attr( $k ); ?>" rows="3" placeholder="<?php echo esc_attr( $ph ); ?>"><?php echo esc_textarea( $v[ $k ] ); ?></textarea></label>
						<?php else : ?>
							<label class="cc-field<?php echo 'day_meeting_point' === $k ? ' cc-field--wide' : ''; ?>"><span><?php echo esc_html( $label ); ?></span>
								<input type="text" name="fge_<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v[ $k ] ); ?>" placeholder="<?php echo esc_attr( $ph ); ?>"></label>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<p class="cc-venue-actions">
					<button type="submit" name="save" value="1" class="cc-btn cc-btn--primary">Details speichern</button>
				</p>
			</form>
			<p class="cc-muted">Startzeit und Treffpunkt gehen direkt an die Gruppe, den Rest bekommt sie am Vortag. Bei Fragen einfach auf unsere Mail antworten.</p>
		<?php endif; ?>
	</section>
	<p class="cc-muted cc-plain-foot">Firmengolf · <?php echo esc_html( (string) ( $co['legal_name'] ?? 'Visionpunch UG (haftungsbeschränkt)' ) ); ?></p>
</main>
</body>
</html>
	<?php
}

// ── Erinnerung (Cron in request-followups.php) ───────────────────────────────

/** Einmalige Erinnerung an den Platz, wenn nach der Buchung nichts eingetragen wurde. */
function fge_send_venue_details_reminder( int $req, array $venue ): bool {
	$pid = (int) ( $venue['partner_id'] ?? 0 );
	$to  = [];
	foreach ( fge_mail_recipients( 'venue_details_reminder', $req ) as $r ) {
		if ( empty( $r['missing'] ) && is_email( $r['email'] ) ) {
			$to[ strtolower( $r['email'] ) ] = $r['email'];
		}
	}
	if ( ! $to || $pid <= 0 ) {
		return false;
	}
	$snap  = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$date  = (string) ( $snap['date'] ?? '' );
	$ref   = fge_request_number( $req );
	$due   = fge_venue_detail_deadline( $req );
	$group = fge_request_group_label( $req, true );

	$subject = 'Kurze Bitte: Details für den Eventtag (' . $ref . ')';
	$content = '
		<p style="margin:0 0 16px;">' . esc_html( fge_venue_greeting( $pid ) ) . '</p>
		<p style="margin:0 0 16px;">für das Event' . ( '' !== $date ? ' am ' . esc_html( $date ) : '' ) . ' (' . esc_html( $group ) . ') fehlen uns noch die Angaben von eurer Seite: Ansprechpartner vor Ort mit Telefon, Golflehrer, Treffpunkt, Startzeit und was die Gruppe mitbringen soll. Das dauert zwei Minuten, die Gruppe bekommt dann alles vorab von uns.</p>
		<p style="margin:0 0 16px;">' . fge_email_button( fge_venue_detail_link( (int) $venue['id'] ), 'Details für den Eventtag eintragen' ) . '</p>
		' . ( '' !== $due ? '<p style="margin:0 0 16px;">Bitte bis ' . esc_html( $due ) . '.</p>' : '' ) . '
		<p style="margin:0;">Falls ihr lieber telefoniert: einfach auf diese Mail antworten, dann rufen wir an.</p>
	';

	fge_mail_log_context( $req, 'venue_details_reminder' );
	$ok = (bool) wp_mail( array_values( $to ), $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	fge_mail_log_context_clear();
	if ( $ok ) {
		fge_activity_add( $req, 'system', 'Erinnerung an den Platz: Eventtag-Details fehlen noch' );
	}
	return $ok;
}
