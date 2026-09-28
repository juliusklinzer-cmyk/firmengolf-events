<?php
/**
 * Erzeugt docs/mail-inventar.md aus includes/mail-registry.php.
 * Lauf (lokal): bash tests/mail-inventar.sh
 */
$reg     = fge_mail_registry();
$parties = [
	'kunde'         => 'An Firmenkunden',
	'platz'         => 'An Golfplätze und Simulatoren',
	'dienstleister' => 'An Dienstleister',
	'partner'       => 'An Partner (Portal und Einladung)',
	'intern'        => 'Intern an Firmengolf',
];
$by = [];
foreach ( $reg as $key => $m ) {
	$by[ (string) ( $m['party'] ?? 'sonstige' ) ][ $key ] = $m;
}
$out  = "# Mail-Inventar Firmengolf\n\n";
$out .= 'Erzeugt aus `includes/mail-registry.php` (eine Quelle für Folgen-Vorschau, Postausgang, Phasenleiste und dieses Dokument). Stand: ' . wp_date( 'd.m.Y' ) . ', Version ' . FGE_VERSION . ', ' . count( $reg ) . " Mails. Neu erzeugen nach jeder Änderung an der Registry: `bash tests/mail-inventar.sh`.\n\n";
$out .= "Gemeinsamer Stil: Rahmen `fge_email_wrap()`, Absender events@firmengolf-events.de (mail-config.php, auch Reply-To), Buttons `fge_email_button()`, Versand über WP Mail SMTP und Brevo, Protokoll je Versand in `wp_fge_mail_log` (mail-log.php, Zuordnung über die Vorgangsnummer im Betreff). Keine Gedankenstriche, Preise als „XX € p.P.“ oder „XX € netto“.\n\n";
$out .= "Spalte „Auslöser“: automatisch heißt Hook oder Cron ohne Klick, sonst löst jemand die Mail am Knopf aus.\n\n";
foreach ( $parties + [ 'sonstige' => 'Sonstige' ] as $p => $title ) {
	if ( empty( $by[ $p ] ) ) {
		continue;
	}
	$out .= '## ' . $title . ' (' . count( $by[ $p ] ) . ")\n\n| Schlüssel | Mail | Betreff | Auslöser | Inhalt |\n|---|---|---|---|---|\n";
	foreach ( $by[ $p ] as $key => $m ) {
		$trigger = (string) ( $m['trigger'] ?? '' ) . ( ! empty( $m['auto'] ) ? ' (automatisch)' : ' (Knopf)' );
		$out    .= '| `' . $key . '` | ' . ( $m['label'] ?? '' ) . ' | ' . ( $m['subject'] ?? '' ) . ' | ' . $trigger . ' | ' . implode( ', ', (array) ( $m['content'] ?? [] ) ) . " |\n";
	}
	$out .= "\n";
}
echo $out;
