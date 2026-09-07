<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// SHARED HELPERS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Loads PRG error-state from a transient.
 * Reads $_GET[$err_key], fetches the transient, deletes it, returns its contents.
 * Returns ['errors' => [], 'data' => []] if no token present or transient expired.
 */
function fge_load_form_state( string $err_key ): array {
	$token = sanitize_text_field( wp_unslash( $_GET[ $err_key ] ?? '' ) );
	if ( $token === '' ) {
		return [ 'errors' => [], 'data' => [] ];
	}
	$transient = get_transient( 'fge_form_err_' . $token );
	if ( ! is_array( $transient ) ) {
		return [ 'errors' => [], 'data' => [] ];
	}
	delete_transient( 'fge_form_err_' . $token );
	return [
		'errors' => $transient['errors'] ?? [],
		'data'   => $transient['data']   ?? [],
	];
}


/**
 * Spam-Schutz für öffentliche Anfrage-Endpunkte.
 * Honeypot: das versteckte Feld `fge_hp` muss leer bleiben (Bots füllen es aus).
 */
function fge_form_honeypot_tripped(): bool {
	return '' !== trim( (string) wp_unslash( $_POST['fge_hp'] ?? '' ) );
}

/**
 * Einfaches IP-Rate-Limit via Transient. true = Limit überschritten.
 * Default: max. 8 Anfragen pro 10 Minuten je IP — großzügig für Menschen, bremst Bots.
 */
function fge_form_rate_limited( int $max = 8, int $window = 600, string $bucket = '' ): bool {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( $ip === '' ) {
		return false;
	}
	$key   = 'fge_rl_' . md5( $bucket . '|' . $ip );
	$count = (int) get_transient( $key );
	if ( $count >= $max ) {
		return true;
	}
	set_transient( $key, $count + 1, $window );
	return false;
}

/**
 * Signierter Zeitstempel für die Bot-Fallen (Spam-Welle contact_page 2026-08-07:
 * der Bot lädt die Seite, übernimmt den Nonce, lässt den Honeypot leer und
 * postet sofort — Nonce+Honeypot+IP-Limit reichen dagegen nicht).
 */
function fge_form_trap_token(): string {
	$ts = (string) time();
	return $ts . '.' . substr( hash_hmac( 'sha256', $ts, wp_salt( 'nonce' ) ), 0, 20 );
}

/**
 * Versteckte Fallen-Felder für klassische POST-Formulare: signierte Zeitfalle
 * (fge_ft) + JS-Pflichtfeld (fge_js, wird per Inline-Script aus fge_ft gefüllt).
 * Mehrfach pro Seite einbindbar, das Script füllt je Formular das eigene Feld.
 */
function fge_form_trap_fields(): string {
	return '<input type="hidden" name="fge_ft" value="' . esc_attr( fge_form_trap_token() ) . '">'
		. '<input type="hidden" name="fge_js" value="">'
		. '<script>(function(){var l=document.getElementsByName("fge_js");for(var i=0;i<l.length;i++){var f=l[i].form&&l[i].form.querySelector("input[name=fge_ft]");if(f){l[i].value=f.value.split("").reverse().join("");}}})();</script>';
}

/**
 * true = Bot. Greift, wenn (a) das JS-Pflichtfeld nicht clientseitig gefüllt
 * wurde (Bots ohne JS-Ausführung), (b) die Signatur des Zeitstempels nicht
 * stimmt oder (c) das Formular unter $min_seconds abgeschickt wurde. Obergrenze
 * 24 h entspricht der Nonce-Lebensdauer, gecachte Seiten bleiben also gültig.
 */
function fge_form_bot_detected( int $min_seconds = 4 ): bool {
	$ft = sanitize_text_field( wp_unslash( $_POST['fge_ft'] ?? '' ) );
	$js = sanitize_text_field( wp_unslash( $_POST['fge_js'] ?? '' ) );
	if ( '' === $ft || $js !== strrev( $ft ) ) {
		return true;
	}
	$parts = explode( '.', $ft, 2 );
	if ( 2 !== count( $parts ) ) {
		return true;
	}
	$expected = substr( hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) ), 0, 20 );
	if ( ! hash_equals( $expected, $parts[1] ) ) {
		return true;
	}
	$age = time() - (int) $parts[0];
	return $age < $min_seconds || $age > DAY_IN_SECONDS;
}

// ── Plausibilitätsprüfung der Eckdaten ───────────────────────────────────────
//
// Stepper-Grenzen und das date-min-Attribut sind rein clientseitig und per JS
// trivial zu umgehen. Vorher landeten so „-999 Gäste" und Termine aus 1990 in
// der Datenbank und standen anschließend im Beleg für den Kunden
// (Audit 2026-08-12). Beide AJAX-Handler prüfen das jetzt serverseitig.

/** Kleinste und größte Gruppengröße, die wir als Anfrage akzeptieren. */
const FGE_GROUP_MIN = 1;
const FGE_GROUP_MAX = 500;

/**
 * Teilnehmerzahl aus einer Freitexteingabe. Gibt 0 zurück, wenn keine Zahl
 * enthalten ist, und null, wenn die Zahl außerhalb des zulässigen Bereichs liegt.
 */
function fge_parse_group_size( $raw ): ?int {
	$str = (string) wp_unslash( $raw ?? '' );
	if ( ! preg_match( '/-?\d+/', $str, $m ) ) {
		return 0; // keine Angabe, bleibt optional
	}
	$n = (int) $m[0];
	if ( $n < FGE_GROUP_MIN || $n > FGE_GROUP_MAX ) {
		return null;
	}
	return $n;
}

/**
 * Wunschtermin: akzeptiert nur ein echtes ISO-Datum, das nicht in der
 * Vergangenheit und nicht weiter als drei Jahre in der Zukunft liegt.
 * Gibt '' für „keine Angabe" und null für einen unzulässigen Wert zurück.
 */
function fge_validate_wish_date( $raw ): ?string {
	$str = trim( sanitize_text_field( wp_unslash( $raw ?? '' ) ) );
	if ( '' === $str ) {
		return '';
	}
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $str, wp_timezone() );
	if ( ! $d || $d->format( 'Y-m-d' ) !== $str ) {
		return null;
	}
	$today = current_datetime()->setTime( 0, 0 );
	if ( $d < $today || $d > $today->modify( '+3 years' ) ) {
		return null;
	}
	return $str;
}

/**
 * Prüft Gruppengröße und bis zu drei Wunschtermine gemeinsam und bricht mit
 * einer verständlichen Meldung ab, wenn etwas nicht passt.
 *
 * @return array{size:int,dates:array<int,string>}
 */
function fge_validate_event_basics( $size_raw, array $date_raws ): array {
	$size = fge_parse_group_size( $size_raw );
	if ( null === $size ) {
		wp_send_json_error( [
			'message' => sprintf( 'Bitte gib eine Gruppengröße zwischen %d und %d Personen an.', FGE_GROUP_MIN, FGE_GROUP_MAX ),
		], 422 );
	}
	$dates = [];
	foreach ( $date_raws as $raw ) {
		$d = fge_validate_wish_date( $raw );
		if ( null === $d ) {
			wp_send_json_error( [ 'message' => 'Bitte wähle Wunschtermine, die in der Zukunft liegen.' ], 422 );
		}
		$dates[] = $d;
	}
	return [ 'size' => $size, 'dates' => $dates ];
}

/**
 * Meta-Pixel event_id: serverseitig erzeugt, eindeutig je Anfrage, am Request
 * gespeichert. Der Browser-Lead schickt sie als eventID mit; die Conversions API
 * sendet später dieselbe ID an dieselbe Datenquelle, Meta dedupliziert darüber
 * (ohne gemeinsame ID zählt jede Anfrage doppelt). Hängt am fge_request_created-
 * Hook, damit jede Anfrage unabhängig vom Eingangskanal eine ID bekommt.
 */
function fge_meta_event_id( int $request_id ): string {
	$id = (string) get_post_meta( $request_id, '_fge_meta_event_id', true );
	if ( '' === $id ) {
		$id = wp_generate_uuid4();
		update_post_meta( $request_id, '_fge_meta_event_id', $id );
	}
	return $id;
}
add_action( 'fge_request_created', 'fge_meta_event_id', 1 );

/** Gemeinsamer Spam-Gate für AJAX-Anfragen: bricht mit JSON-Antwort ab, wenn verdächtig. */
function fge_form_spam_gate(): void {
	if ( fge_form_honeypot_tripped() || fge_form_bot_detected() ) {
		// Verständlich statt kryptisch: die Zeitfalle trifft gelegentlich auch
		// sehr schnelle echte Nutzer, die dann nur „Ungültige Anfrage." sahen.
		wp_send_json_error( [ 'message' => 'Das ging uns zu schnell. Bitte warte einen Moment und schick die Anfrage noch einmal ab.' ], 400 );
	}
	if ( fge_form_rate_limited() ) {
		wp_send_json_error( [ 'message' => 'Zu viele Anfragen in kurzer Zeit. Bitte versuche es in ein paar Minuten erneut oder schreib uns direkt.' ], 429 );
	}
}


// ══════════════════════════════════════════════════════════════════════════════
// MODAL ANFRAGE — AJAX handler (logged-out + logged-in)
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_fge_modal_anfrage',        'fge_ajax_modal_anfrage' );
add_action( 'wp_ajax_nopriv_fge_modal_anfrage', 'fge_ajax_modal_anfrage' );

function fge_ajax_modal_anfrage(): void {
	check_ajax_referer( 'fge_modal_anfrage', 'nonce' );
	fge_form_spam_gate();

	$event_id  = absint( $_POST['event_id'] ?? 0 );
	$group     = sanitize_text_field( wp_unslash( $_POST['group_size'] ?? '' ) );
	$notes     = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
	$first     = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
	$last      = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$company   = sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) );
	$phone      = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$city       = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
	$experience = sanitize_text_field( wp_unslash( $_POST['experience'] ?? '' ) );
	$starttime  = sanitize_text_field( wp_unslash( $_POST['starttime'] ?? '' ) );
	$diet       = sanitize_text_field( wp_unslash( $_POST['diet'] ?? '' ) );
	$pref       = sanitize_text_field( wp_unslash( $_POST['contact_pref'] ?? '' ) );

	if ( ! $email || ! is_email( $email ) || ! $first || ! $last || ! $company ) {
		wp_send_json_error( [ 'message' => 'Pflichtfelder fehlen.' ], 422 );
	}
	if ( '1' !== (string) ( $_POST['consent'] ?? '' ) ) {
		wp_send_json_error( [ 'message' => 'Bitte stimme der Datenverarbeitung zu, um die Anfrage zu senden.' ], 422 );
	}
	if ( 'firmengolf_event' !== get_post_type( $event_id ) ) {
		wp_send_json_error( [ 'message' => 'Ungültiges Event.' ], 422 );
	}
	// Eckdaten prüfen, bevor ein Datensatz entsteht.
	$basics = fge_validate_event_basics( $group, [ $_POST['date1'] ?? '', $_POST['date2'] ?? '', $_POST['date3'] ?? '' ] );

	$partner_id  = (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true );
	$event_title = $event_id > 0 ? get_the_title( $event_id ) : 'k. A.';
	$ref = fge_generate_request_ref();

	$request_id = wp_insert_post( [
		'post_type'   => 'firmengolf_request',
		'post_status' => 'publish',
		'post_title'  => $ref . ' · ' . $first . ' ' . $last,
	] );
	if ( is_wp_error( $request_id ) || ! $request_id ) {
		wp_send_json_error( [ 'message' => 'Anfrage konnte nicht gespeichert werden.' ], 500 );
	}

	// Core / routing (canonical request fields — same as the PRG handler)
	update_post_meta( $request_id, '_fge_request_type',        'specific_event' );
	update_post_meta( $request_id, '_fge_request_status',      'neu' );
	update_post_meta( $request_id, '_fge_assigned_event_id',   $event_id );
	update_post_meta( $request_id, '_fge_assigned_partner_id', $partner_id );
	update_post_meta( $request_id, '_fge_source',              'event_page' );
	update_post_meta( $request_id, '_fge_ref',                 $ref );
	add_post_meta( $request_id, '_fge_request_date',      current_datetime()->format( 'Y-m-d H:i:s' ), true );
	add_post_meta( $request_id, '_fge_consent_timestamp', current_datetime()->format( 'Y-m-d H:i:s' ), true );

	// Company + contact
	update_post_meta( $request_id, '_fge_company_name',       $company );
	update_post_meta( $request_id, '_fge_company_city',       $city );
	update_post_meta( $request_id, '_fge_contact_first_name', $first );
	update_post_meta( $request_id, '_fge_contact_last_name',  $last );
	update_post_meta( $request_id, '_fge_contact_email',      $email );
	update_post_meta( $request_id, '_fge_contact_phone',      $phone );
	$pref_method = [ 'E-Mail' => 'email', 'Telefon' => 'phone', 'Egal' => 'any' ][ $pref ] ?? 'any';
	update_post_meta( $request_id, '_fge_preferred_contact_method', $pref_method );

	// Event framework
	update_post_meta( $request_id, '_fge_expected_participants', $basics['size'] );
	update_post_meta( $request_id, '_fge_group_experience', $experience );
	update_post_meta( $request_id, '_fge_start_time',       $starttime );
	update_post_meta( $request_id, '_fge_catering_notes',   $diet );
	// Wunschtermine (1–3): Kalender liefert ISO, wird zu lesbarem Label („Do, 18.06.2026")
	// formatiert; speist die Termin-Abstimmung (fge_request_responses / scheduling).
	$fmt = static function ( $raw ) {
		return ( '' !== $raw && function_exists( 'fge_format_wish_date' ) ) ? fge_format_wish_date( $raw ) : $raw;
	};
	update_post_meta( $request_id, '_fge_preferred_date_1', $fmt( $basics['dates'][0] ?? '' ) );
	update_post_meta( $request_id, '_fge_preferred_date_2', $fmt( $basics['dates'][1] ?? '' ) );
	update_post_meta( $request_id, '_fge_preferred_date_3', $fmt( $basics['dates'][2] ?? '' ) );
	$msg_parts = [];
	if ( '' !== $starttime )     { $msg_parts[] = 'Gewünschter Startzeitpunkt: ' . $starttime; }
	if ( '' !== $experience )    { $msg_parts[] = 'Golf-Erfahrung: ' . $experience; }
	if ( '' !== $diet )          { $msg_parts[] = 'Verpflegung/Diät: ' . $diet; }
	if ( '' !== trim( $notes ) ) { $msg_parts[] = trim( $notes ); }
	update_post_meta( $request_id, '_fge_message', implode( "\n", $msg_parts ) );

	// Wunsch-Leistungen (Step 2) → getrennt nach Quelle: Platz vs. Firmengolf.
	$wishes_raw = json_decode( (string) wp_unslash( $_POST['wishes'] ?? '[]' ), true );
	$wish_platz = [];
	$wish_fg    = [];
	if ( is_array( $wishes_raw ) ) {
		foreach ( $wishes_raw as $w ) {
			$label = isset( $w['label'] ) ? sanitize_text_field( (string) $w['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			if ( isset( $w['source'] ) && 'firmengolf' === $w['source'] ) {
				$wish_fg[] = $label;
			} else {
				$wish_platz[] = $label;
			}
		}
	}
	update_post_meta( $request_id, '_fge_wishes_platz',      array_values( array_unique( $wish_platz ) ) );
	update_post_meta( $request_id, '_fge_wishes_firmengolf', array_values( array_unique( $wish_fg ) ) );

	// Tracking + downstream (emails, status) via the shared hook
	$current_count = (int) get_post_meta( $event_id, '_fge_requests_count', true );
	update_post_meta( $event_id, '_fge_requests_count', $current_count + 1 );

	do_action( 'fge_request_created', $request_id );

	wp_send_json_success( [
		'ref'         => $ref,
		'event_title' => $event_title,
		'date_1'      => (string) get_post_meta( $request_id, '_fge_preferred_date_1', true ),
		'group_size'  => $group,
		'fb_event_id' => fge_meta_event_id( $request_id ),
	] );
}

// ── RequestWizard (Individuelle Events): allgemeine Anfrage per AJAX ─────────
add_action( 'wp_ajax_fge_general_request',        'fge_ajax_general_request' );
add_action( 'wp_ajax_nopriv_fge_general_request', 'fge_ajax_general_request' );

function fge_ajax_general_request(): void {
	check_ajax_referer( 'fge_general_request', 'nonce' );
	fge_form_spam_gate();

	$t = static fn( $k ) => sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );

	$occasion = $t( 'occasion' );
	$first    = $t( 'first_name' );
	$last     = $t( 'last_name' );
	$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$company  = $t( 'company' );

	// Quick-Mode kommt mit „Vor- und Nachname" in einem Feld → splitten.
	if ( $last === '' && strpos( $first, ' ' ) !== false ) {
		$parts = preg_split( '/\s+/', $first, 2 );
		$first = $parts[0];
		$last  = $parts[1] ?? '';
	}

	if ( ! $email || ! is_email( $email ) || $first === '' || $occasion === '' ) {
		wp_send_json_error( [ 'message' => 'Bitte Anlass, Name und gültige E-Mail angeben.' ], 422 );
	}

	// DSGVO: Einwilligung ist Pflicht.
	if ( '1' !== (string) ( $_POST['consent'] ?? '' ) ) {
		wp_send_json_error( [ 'message' => 'Bitte stimme der Datenverarbeitung zu, um die Anfrage zu senden.' ], 422 );
	}

	// Eckdaten prüfen, bevor ein Datensatz entsteht.
	$basics    = fge_validate_event_basics( $_POST['size'] ?? '', [ $_POST['date1'] ?? '', $_POST['date2'] ?? '', $_POST['date3'] ?? '' ] );
	$goal      = $t( 'goal' );
	$size      = $basics['size'];
	$region    = $t( 'region' );
	$place     = $t( 'place' );
	$budget    = $t( 'budget' );
	$when      = $t( 'when' );
	$flex      = $t( 'flex' );
	$duration  = $t( 'duration' );
	$experience = $t( 'experience' );
	$startzeit  = $t( 'startzeit' );
	$diet       = $t( 'diet' );
	$phone     = $t( 'phone' );
	$city      = $t( 'city' );
	$pref      = $t( 'contact_pref' );
	$notes     = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

	// Services kommen als kommaseparierte Wizard-Labels.
	$services = array_filter( array_map( 'trim', explode( '||', (string) wp_unslash( $_POST['services'] ?? '' ) ) ) );
	$services = array_map( 'sanitize_text_field', $services );

	$ref = fge_generate_request_ref();

	$request_id = wp_insert_post( [
		'post_type'   => 'firmengolf_request',
		'post_status' => 'publish',
		'post_title'  => $ref . ' · ' . trim( $first . ' ' . $last ),
	] );
	if ( is_wp_error( $request_id ) || ! $request_id ) {
		wp_send_json_error( [ 'message' => 'Anfrage konnte nicht gespeichert werden.' ], 500 );
	}

	// Core / routing
	update_post_meta( $request_id, '_fge_request_type',   'general_event_request' );
	update_post_meta( $request_id, '_fge_request_status', 'neu' );
	$allowed_sources = [ 'general_landingpage', 'general_anfrage_page', 'weihnachtsfeier_section', 'sommerfest_section' ]; // weihnachtsfeier_section: Kurz-Anfrage auf /individuelle-events/ (07.09.)
	$req_source      = $t( 'source' );
	update_post_meta( $request_id, '_fge_source', in_array( $req_source, $allowed_sources, true ) ? $req_source : 'general_landingpage' );
	update_post_meta( $request_id, '_fge_ref',            $ref );
	add_post_meta( $request_id, '_fge_request_date',      current_datetime()->format( 'Y-m-d H:i:s' ), true );
	add_post_meta( $request_id, '_fge_consent_timestamp', current_datetime()->format( 'Y-m-d H:i:s' ), true );

	// Company + contact
	update_post_meta( $request_id, '_fge_company_name',       $company );
	update_post_meta( $request_id, '_fge_company_city',       $city );
	update_post_meta( $request_id, '_fge_contact_first_name', $first );
	update_post_meta( $request_id, '_fge_contact_last_name',  $last );
	update_post_meta( $request_id, '_fge_contact_email',      $email );
	update_post_meta( $request_id, '_fge_contact_phone',      $phone );
	$pref_method = [ 'E-Mail' => 'email', 'Telefon' => 'phone', 'Egal' => 'any' ][ $pref ] ?? 'any';
	update_post_meta( $request_id, '_fge_preferred_contact_method', $pref_method );

	// Event framework
	update_post_meta( $request_id, '_fge_expected_participants', $size );
	update_post_meta( $request_id, '_fge_group_experience',      $experience );
	update_post_meta( $request_id, '_fge_start_time',            $startzeit );
	update_post_meta( $request_id, '_fge_catering_notes',        $diet );
	update_post_meta( $request_id, '_fge_desired_region',        $region );
	update_post_meta( $request_id, '_fge_place_wish',            $place );
	update_post_meta( $request_id, '_fge_budget_range',          $budget );
	$period = trim( $when . ( $flex !== '' ? ' · ' . $flex : '' ), ' ·' );
	update_post_meta( $request_id, '_fge_alternative_period', $period );
	// Wunschtermine zu lesbaren Labels formatieren (konsistent zum Modal-Handler).
	$fmt_wish = static function ( $raw ) {
		return ( '' !== $raw && function_exists( 'fge_format_wish_date' ) ) ? fge_format_wish_date( $raw ) : $raw;
	};
	update_post_meta( $request_id, '_fge_preferred_date_1', $fmt_wish( $basics['dates'][0] ?? '' ) );
	update_post_meta( $request_id, '_fge_preferred_date_2', $fmt_wish( $basics['dates'][1] ?? '' ) );
	update_post_meta( $request_id, '_fge_preferred_date_3', $fmt_wish( $basics['dates'][2] ?? '' ) );

	// Services → kanonische wants_* Flags; unbekannte fließen in individual_customization.
	$svc_map = [
		'Golflehrer / Coaching'    => 'golf_teacher',
		'Schnupperkurs'            => 'golf_teacher',
		'Platzreife'               => 'golf_teacher',
		'Firmenturnier'            => 'tournament_mode',
		'Putting-Challenge'        => 'tournament_mode',
		'Frühstück'                => 'breakfast',
		'Mittagessen'              => 'lunch', // war „Lunch" (Wizard-Wording 2026-07-06)
		'Grill'                    => 'dinner',
		'Abendessen'               => 'dinner',
		'Bar & Drinks'             => 'dinner',
		'Meetingraum'              => 'meeting_room',
		'Shuttle / Transport'      => 'shuttle',
		'Branding & Banner'        => 'branding',
		'Schlechtwetter-Alternative' => 'bad_weather_alternative',
	];
	$all_wants = [ 'golf_teacher', 'meeting_room', 'breakfast', 'lunch', 'dinner', 'shuttle', 'branding', 'tournament_mode', 'bad_weather_alternative', 'individual_customization' ];
	$set_wants = [];
	$has_other = false;
	foreach ( $services as $label ) {
		if ( isset( $svc_map[ $label ] ) ) {
			$set_wants[ $svc_map[ $label ] ] = true;
		} else {
			$has_other = true;
		}
	}
	if ( $has_other ) {
		$set_wants['individual_customization'] = true;
	}
	foreach ( $all_wants as $w ) {
		update_post_meta( $request_id, '_fge_wants_' . $w, isset( $set_wants[ $w ] ) ? 1 : 0 );
	}

	update_post_meta( $request_id, '_fge_additional_wishes', $services ? implode( ', ', $services ) : '' );

	$message = 'Anlass: ' . $occasion
		. ( $goal !== ''     ? "\nZiel: " . $goal : '' )
		. ( $duration !== '' ? "\nDauer: " . $duration : '' )
		. ( $startzeit !== '' ? "\nGewünschter Startzeitpunkt: " . $startzeit : '' )
		. ( $experience !== '' ? "\nGolf-Erfahrung: " . $experience : '' )
		. ( $region !== ''   ? "\nWunsch-Ort: " . $region : '' )
		. ( $place !== ''    ? "\nKonkreter Platz: " . $place : '' )
		. ( $diet !== ''     ? "\nVerpflegung/Diät: " . $diet : '' )
		. ( $pref !== ''     ? "\nKontakt bevorzugt: " . $pref : '' )
		. ( $services        ? "\nGewünschte Leistungen: " . implode( ', ', $services ) : '' )
		. ( $notes !== ''    ? "\n\n" . $notes : '' );
	update_post_meta( $request_id, '_fge_message', trim( $message ) );

	// Downstream: Mails + Status über den gemeinsamen Hook.
	do_action( 'fge_request_created', $request_id );

	wp_send_json_success( [
		'ref'         => $ref,
		'occasion'    => $occasion,
		'size'        => $size,
		'company'     => $company,
		'email'       => $email,
		'fb_event_id' => fge_meta_event_id( $request_id ),
	] );
}
