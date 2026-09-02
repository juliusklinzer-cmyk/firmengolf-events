# Mini-Audit der drei Onboarding-Wizards (01.09.2026, Stand 1.9.194)
Je ein Prüfer pro Wizard. Leitfrage je Slide: Info wichtig? Wo verwendet? Später (Portal) möglich? Plus Copy/Flow/tote Daten. 28 Funde.
Kategorien: `info-unnoetig` (kann raus), `spaeter-moeglich` (ins Portal), `tote-daten` (erhoben, nirgends genutzt), `copy`, `flow`, `pflicht`, `aufraeumen`.

---

## COACH  (11 Funde)

### HIGH

**Format-Slide mit 26 Karten und inhaltlicher Doppelung zweier Kataloge**  
`coach-formats` · info-unnoetig

- Befund: Die Slide rendert ZWEI Kachelgruppen: fge_catalog_coach_formats() (14 Einträge) plus fge_catalog_partner_formats() als „Größere Eventmodule" (12 Einträge) = 26 Checkbox-Karten auf einer wide-Slide (onboarding.php:3450-3468). Die Kataloge überschneiden sich: coach_formats hat 'schnupper-team'=Grundlagenkurs für Teams, 'platzreife-kompakt', 'platzreife-serie', partner_formats hat 'schnupperkurs'=Schnupperkurs, 'platzreife'=Platzreife, 'teamevent' (catalogs.php:178-193 / 383-401). Beide Auswahlen werden auf der öffentlichen Karte zu EINER Liste gemergt (fge-partner-coach.php:61-74) und im Review zusammengeworfen (onboarding.php:4201-4215) — Platzreife/Schnupperkurs erscheinen dadurch potenziell doppelt. Massiver Overload, widerspricht Julius' Prinzip „kein Scrollen, kleine Schritte".
- Empfehlung: Die zweite Gruppe „Größere Eventmodule" (event_formats) aus dem Wizard nehmen und später pro Angebot im Portal erfassen (dort werden Angebote ohnehin einzeln angelegt). coach_formats-Katalog auf die wirklich unterscheidenden 6 bis 8 Formate kürzen und Doppelungen zu partner_formats auflösen.

### MEDIUM

**Gesundheits-Zertifizierung wird aufwendig erklärt, aber nirgends ausgespielt**  
`coach-quali` · tote-daten

- Befund: coach_health_cert bekommt eine eigene Karte plus ein aufklappbares Info-Accordion mit §20 SGB V / §3 Nr. 34 EStG-Erklärung (onboarding.php:3312-3329). Gespeichert wird das Flag (onboarding.php:746). Verwendet wird es NUR im Review (onboarding.php:4180) — grep über Theme+Plugin ohne onboarding.php findet KEINE weitere Nutzung (nicht auf der öffentlichen Coach-Karte fge-partner-coach.php, nicht im Portal, nicht im Matching request-responses.php). Für die Firma, die es überzeugen soll (steuerfreier Zuschuss), ist es unsichtbar = totes Ausgabedatum bei hohem Erklär-Aufwand im Wizard.
- Empfehlung: Entweder auf der öffentlichen Coach-Karte als Vertrauens-/Verkaufsargument ausspielen (dann lohnt die Abfrage), oder aus dem Onboarding raus und später im Portal abfragen. So oder so das lange Info-Accordion kürzen, es erhöht die kognitive Last auf der Quali-Slide.

**Ganze Verfügbarkeits-Slide beim Coach in den Wizard überflüssig**  
`avail` · spaeter-moeglich

- Befund: avail erfasst preferred_event_days, evening_events_possible, min_lead_time_days, season_from/to (onboarding.php:3587-3693). Diese Metas fließen ausschließlich in das weiche Scoring des internen Matching-Tools (request-responses.php:351-393, nur +1/-2/-3 Punkte, kein hartes Gate) und sind allesamt im Portal editierbar (partner-fields.php). Im Coach-Review (fge_onboarding_review_blocks_coach, onboarding.php:4149-4230) taucht avail NICHT auf, auf der öffentlichen Karte auch nicht. Der Coach füllt hier also Felder, die er nie wiedersieht und die nur ein Admin-Scoring minimal beeinflussen.
- Empfehlung: avail für den Coach aus dem Onboarding nehmen und ins Portal verlegen (Grunddaten reichen erst nach Anmeldung). Falls etwas bleiben soll, höchstens die Saison, alles andere später.

**Pricing-Slide ist eine reine Textseite ohne ein einziges Eingabefeld**  
`pricing` · aufraeumen

- Befund: Die pricing-Slide (render_step_10, onboarding.php:3695-3735) zeigt nur die Hero-Karte „Dein Netto-Preis" plus drei nummerierte Erklärschritte, kein Formularfeld. Der Save-Case bestätigt es: case 'pricing' persistiert bewusst nichts (onboarding.php:629-632, „Nothing to persist"). Es ist also ein voller zusätzlicher Klick/Slide nur zum Lesen, mitten im Flow zwischen avail und media.
- Empfehlung: Die Info in die Chapter-Intro (intro-3 fehlt beim Coach ohnehin) oder als kompakte Info-Box auf die Review-Seite ziehen und die eigenständige pricing-Slide streichen. Spart einen Schritt ohne Datenverlust.

**Feld „Golfschule oder Team" wird gespeichert, aber nirgends angezeigt**  
`coach-profile` · tote-daten

- Befund: coach_school wird im Profil optional erfasst (onboarding.php:3230) und gespeichert (onboarding.php:737). grep über Theme+Plugin (ohne onboarding.php) findet KEINE Nutzung von coach_school: nicht auf der öffentlichen Karte, nicht im Portal, nicht im Matching. Nur im Review sichtbar (onboarding.php:4172). Der Code-Kommentar nennt es „später die Brücke zur verknüpften Golfschule" — die Funktion existiert aber noch nicht, aktuell also totes Datum.
- Empfehlung: Aus dem Onboarding nehmen, bis die Golfschul-Verknüpfung gebaut ist, oder erst im Portal abfragen. Hält den Profil-Schritt schlanker.

**Verwaiste Coach-Slides samt Render/Save/Validate als toter Code**  
`coach-formats` · aufraeumen

- Befund: render_coach_venue (onboarding.php:3359 mit Rolle/venue_use/groups/fees/mobiles Angebot), render_coach_authority (3414), render_coach_includes und render_step_8 sind über fge_onboarding_render_slide_form weiter verdrahtet (1474-1478) und haben Save- (755-778) und Validate-Cases (1294-1310), stehen aber in KEINEM Manifest (coach nutzt 'location' statt 'coach-venue', kein 'coach-authority'/'coach-includes', Zeilen 97-116). Sie sind damit unerreichbar. render_coach_venue enthält zudem sichtbare „Trainer"-Copy entgegen der Vorgabe „Lehrer beim Coach" ('Freier Trainer mit Vereinbarung' 3367, Platzhalter 'freier Trainer' 3409).
- Empfehlung: render_coach_venue, render_coach_authority, render_coach_includes, render_step_8 samt zugehöriger Save-/Validate-Cases und der Dispatcher-Zeilen löschen. Reduziert Wartungslast und verhindert versehentliche Reaktivierung mit „Trainer"-Copy.

**Öffentliche Coach-Karte spricht durchgehend von „Trainer" statt „Lehrer"**  
`review` · copy

- Befund: Die zum Coach gehörende öffentliche Copy verstößt gegen „Lehrer, nicht Trainer": fge-partner-coach.php:172 „Wer dein Team trainiert", :37 Jahres-Label 'u3'=>'Trainererfahrung', :231 „keine Einzelabrechnungen mit Trainer oder Anlage". (Offizielle DOSB-Labels wie „A-Trainer Golf" in catalogs.php sind Eigennamen und bleiben.) Betrifft zwar das Theme-Template, ist aber der Coach-Kontext, den der Wizard befüllt.
- Empfehlung: In fge-partner-coach.php „trainiert"→„unterrichtet", „Trainererfahrung"→„Erfahrung als Golflehrer", „Trainer oder Anlage"→„Golflehrer oder Anlage" ändern.

### LOW

**Gastro-Einbindung im Coach-Flow gehört ins Portal**  
`avail` · spaeter-moeglich

- Befund: Auf der avail-Slide hängt beim Coach zusätzlich die Frage fge_coach_gastro_involve (onboarding.php:3673-3683, Save 623-627). Sie ist nur für größere Eventmodule relevant und im Portal editierbar (partner-portal.php:3517-3521). Für die Erst-Anmeldung eines einzelnen Golflehrers ist das eine Detailfrage, die den Flow verlängert.
- Empfehlung: gastro_involve aus dem Onboarding nehmen und im Portal (dort schon vorhanden) belassen.

**Saison-Default April bis Oktober ist platz-, nicht coach-typisch**  
`avail` · flow

- Befund: avail belegt die Saison mit season_from=4 / season_to=10 vor (onboarding.php:3610-3611). Golflehrer arbeiten häufig ganzjährig (Indoor/Winter-Coaching); der Default schränkt im Matching-Scoring (request-responses.php:351-368) unnötig ein, wenn der Coach ihn nicht anpasst.
- Empfehlung: Falls avail beim Coach bleibt: neutraler bzw. ganzjähriger Default oder gar keine Vorbelegung, damit das Scoring nicht fälschlich außerhalb der Saison abwertet.

**Intro-Checkliste unterschlägt den Portal-Zugang-Schritt**  
`intro-1` · copy

- Befund: Die coach:intro-1-Liste kündigt an: „Dein Profil und deine Qualifikation / Wo du unterrichtest / Deine Formate und Gruppengrößen / Preis, Fotos & Einreichung" (onboarding.php:2175). Der Account-/Login-Schritt (main, jetzt Slide 12 von 14, onboarding.php:113) und die Ansprechpartner (contacts) kommen darin nicht vor, obwohl beim Coach genau dort das Konto entsteht.
- Empfehlung: Listenpunkt ergänzen, z. B. „Dein Zugang zum Partnerportal", damit die Erwartung zum Flow passt.

**coach-kind steuert derzeit nur optionale Kapazitätsfelder**  
`coach-kind` · info-unnoetig

- Befund: coach_kind (solo/school) wird ganz am Anfang abgefragt (onboarding.php:101, 3192-3213), gespeichert (727) und außerhalb des Wizards nirgends genutzt (grep: nur catalogs.php-Definition). Einziger aktueller Effekt: Ein-/Ausblenden der „Anzahl Lehrer"-Felder auf der ohnehin optionalen, überspringbaren coach-capacity-Slide (3496-3511) und eine Zeile im Review (4223). Die angekündigte „Team-Verwaltung für Schulen" existiert noch nicht.
- Empfehlung: Beibehalten ist vertretbar (steuert Kapazität), aber solange keine Team-Funktion existiert, könnte die Solo/Schule-Wahl auch in die Kapazitäts-Slide integriert werden, statt eine eigene erste Slide zu belegen.

---

## COURSE  (8 Funde)

### HIGH

**Account + Willkommensmail entstehen mitten im Wizard (course), anders als coach**  
`main` · flow

- Befund: Im course-Manifest liegt 'main' in Kapitel 1 (Schritt ~6 von 17, nach arrival). fge_onboarding_handle_step ruft dort fge_onboarding_create_or_assign_user() auf, das bei neuen Adressen den WP-User anlegt UND fge_send_partner_welcome_email() verschickt (onboarding.php:1028 + 859-860), plus wp_set_auth_cookie. Wer nach Schritt 6 abbricht, hat bereits einen echten Partner-Account und eine Willkommensmail bekommen, obwohl ~11 Slides fehlen. Der coach-Wizard wurde am 01.09. genau deshalb umgestellt: 'main' + 'contacts' ans Ende, Kommentar 'erst alles ausfuellen, dann Login anlegen, dann einreichen' (Manifest 111-114). course wurde nicht mitgezogen.
- Empfehlung: 'main' (und 'contacts') im course-Manifest analog zum coach ans Ende (Kapitel 3, vor review) verschieben. Bis dahin der gesamte Wizard tokenbasiert wie beim coach. Vermeidet Waisen-Accounts und verfruehte Willkommensmails und stellt die Konsistenz zwischen den Wizards her.

### MEDIUM

**Anfahrt-Slide komplett optional und im Portal doppelt vorhanden**  
`arrival` · spaeter-moeglich

- Befund: arrival speichert _fge_poi_car/parking/train/shuttle + _fge_arrival_estation, alle optional (keine Validierung, nicht in fge_onboarding_is_submittable). Verwendung: oeffentliche Partnerseite via fge_partner_arrival_pois() (single-firmengolf_partner.php:281). NICHT im Matching (request-responses.php nutzt nur cap/formats/preferred_days/lead, Zeilen 328-381). Exakt dieselben Felder sind bereits voll im Portal editierbar (Tab 'Standort & Anfahrt', partner-portal.php:3613-3625) inkl. Vollstaendigkeits-Checkliste (Zeile 1405). Der Wizard-Schritt ist damit reine Doppelerfassung.
- Empfehlung: arrival aus dem Onboarding nehmen und ausschliesslich im Portal pflegen lassen (Profil-Vervollstaendigung nach Freigabe). Spart einen 5-Felder-Schritt in Kapitel 1, ohne Datenverlust.

**Preis-Slide ist eine reine Info-Seite ohne jede Eingabe**  
`pricing` · aufraeumen

- Befund: case 'pricing' in fge_onboarding_save_slide ist leer ('Info-only ... Nothing to persist', onboarding.php:629-632). fge_onboarding_render_step_10 rendert nur Hero-Karte + 3 nummerierte Erklaerschritte, kein einziges Feld. Es ist ein Pflicht-Durchklick-Schritt (17. von 17 Slides insgesamt), der nichts erfasst und nur das Provisions-/Rechnungsmodell erklaert, das auch in intro-3 ('Preis-Prinzip') und im Portal steht.
- Empfehlung: Preis-Slide als eigenstaendigen Schritt streichen und den Inhalt als Info-Box in intro-3 oder in die review-Seite integrieren. Ein Schritt weniger, kein Informationsverlust.

### LOW

**Einzelnes optionales Textarea als eigener Schritt**  
`beschreibung` · flow

- Befund: beschreibung besitzt genau ein Feld (_fge_public_short_description, optional, keine Validierung). Wurde am 01.09. bewusst aus basics ausgelagert ('kleine Schritte'). Verwendung ist real (single-firmengolf_partner.php:36 via $m('public_short_description')). Trotzdem erzeugt ein einzelnes optionales Feld einen zusaetzlichen Klick-Schritt.
- Empfehlung: Pruefen, ob die Kurzbeschreibung nicht doch zu basics (unter Name/Website) oder ans Ende passt. Wenn die bewusste 01.09.-Trennung bleiben soll: so lassen. Nur als Kandidat fuer Verschlankung notiert.

**gastro und infra sind zwei aufeinanderfolgende Auswahl-Slides derselben Katalogstruktur**  
`gastro` · flow

- Befund: infra rendert alle fge_catalog_infra_groups() ausser 'Gastronomie', gastro rendert nur die Gruppe 'Gastronomie' (beide 'wide', beide _fge_infra). Es sind zwei konsekutive Kachel-Auswahl-Schritte in Kapitel 2, die technisch dasselbe Meta befuellen (Split nur wegen sauberer Merge-Logik in save, onboarding.php:577-592).
- Empfehlung: Erwaegen, Gastronomie als weitere Kategorie-Gruppe wieder in die infra-Slide zu ziehen (eine Slide mit Kategorie-Ueberschriften). Spart einen Schritt; die bestehende gruppenweise Merge-Logik bleibt nutzbar.

**Ueberschrift 'Wie heisst euer Golfplatz?' unterschlaegt Platztyp + Website**  
`basics` · copy

- Befund: Seit dem 01.09.-Bundling erfasst basics drei Dinge: Name (Pflicht), Platztyp-Dropdown (Pflicht, course) und Website (optional) (fge_onboarding_render_basics 2261-2269). Die Ueberschrift fragt nur nach dem Namen; der Untertitel deckt den Rest nur indirekt ab.
- Empfehlung: Ueberschrift leicht weiten, z. B. 'Grunddaten eures Golfplatzes' oder Untertitel explizit auf Name, Platztyp und Website beziehen, damit die Slide nicht 'nur Name' verspricht.

**Platztyp-Dropdown bietet 'Indoor-Simulator' und 'Mini-Golf' an**  
`basics` · flow

- Befund: fge_catalog_golf_types() (catalogs.php:32) enthaelt u. a. 'indoor-sim' => 'Indoor-Simulator' und 'mini-golf' => 'Mini-Golf'. Da Indoor inzwischen ein eigener Partnertyp mit eigenem Wizard ist, ueberschneidet sich 'Indoor-Simulator' als course-Platztyp mit dem indoor-Flow (Fehlleitungs-/Verwechslungsrisiko), und 'Mini-Golf' passt kaum zu Firmen-Golfevents.
- Empfehlung: Platztyp-Liste fuer course auf echte Golfplatz-Typen eingrenzen (Indoor-Simulator raus, da eigener Partnertyp; Mini-Golf pruefen). Reduziert Fehleinordnungen im richtigen Wizard.

**'_fge_individual_availability_check' wird nur noch fest auf 1 gesetzt**  
`avail` · info-unnoetig

- Befund: In case 'avail' wird update_post_meta(..., '_fge_individual_availability_check', 1) unbedingt gesetzt, Kommentar 'Keine Frage mehr' (onboarding.php:621-622). Das Feld hat keine UI mehr und ist damit ein konstanter Wert; es taucht ausser in onboarding nur in partner-fields.php auf.
- Empfehlung: Kein Wizard-Handlungsbedarf, aber als toter Konstant-Flag notiert: bei Gelegenheit pruefen, ob die Meta noch von irgendetwas gelesen wird, sonst entfernen.

---

## INDOOR  (9 Funde)

### HIGH

**Fast alle Indoor-Zusatzdaten haben keinen Abnehmer außerhalb des Wizards**  
`indoor-spaces, indoor-formats, indoor-hours (+ location entrance_note, indoor-kind)` · tote-daten

- Befund: Beleg per grep über plugins/ + themes/: Die öffentliche Partnerseite themes/firmengolf-child/single-firmengolf_partner.php enthält 0 (null) Vorkommen von 'indoor'. Das Portal liest/bearbeitet ausschließlich _fge_indoor_sim (fge_portal_section_indoor, partner-portal.php:2230 — Boxen, Systeme, Features, box_comfort, box_max, lefthand, max_persons). Nirgends (außer der wizard-internen Review + dem Speichern) gelesen werden: _fge_indoor_spaces, _fge_indoor_spaces_custom, _fge_indoor_area, _fge_indoor_formats, _fge_indoor_hours, _fge_indoor_open_note, _fge_indoor_year_round, _fge_indoor_open_from, _fge_indoor_open_to, _fge_indoor_after_hours, _fge_indoor_entrance_note, _fge_indoor_kind (grep get_post_meta: nur _fge_indoor_sim in portal + onboarding). Deckt sich mit MEMORY (pending-deploy: 'Indoor-Daten ohne Portal-Abnehmer', offene 'öffentliche Indoor-Seite'). Der Nutzer füllt 4 komplette Slides (Räume, Formate, Öffnungszeiten, Fläche) aus, die aktuell keinem Firmenkunden und keinem Matching etwas bringen.
- Empfehlung: Entweder VOR dem Onboarding-Ausbau die öffentliche Indoor-Profilseite + Portal-Anzeige bauen, die genau diese Felder konsumiert, ODER die Slides indoor-spaces, indoor-formats und die Detail-Öffnungszeiten aus dem Wizard herausnehmen und erst im Portal nach Anmeldung erfassen (kleiner Wizard). Solange es keinen Abnehmer gibt, ist jede dieser Slides reiner Drop-off-Risikofaktor.

### MEDIUM

**Konto-Anlage + E-Mail-Verifizierung liegt mitten im Indoor-Flow (Chapter 1)**  
`main + contacts` · flow

- Befund: Im Indoor-Manifest (onboarding.php:66-85) steht 'main' (Konto/Login + Verifizierungscode, fge_onboarding_render_step_4) an Position ~7 von 18, gefolgt von 'contacts', beide in Chapter 1. Beim coach-Wizard hat Julius genau das am 01.09. ANS ENDE verschoben (onboarding.php:111-114: 'erst alles ausfüllen, dann Login anlegen, dann einreichen'). Die E-Mail-Code-Abfrage mitten im Flow ist eine harte Abbruchstelle (Nutzer verlässt die Seite, um die Mail zu holen), bevor er die Substanz gesehen hat.
- Empfehlung: Indoor an die coach-Entscheidung angleichen: main + contacts nach Chapter 3 (vor review) verschieben. Gleiche Begründung, gleicher Nutzen; hält den Einstieg niederschwellig.

**Räume/Aktivitäten/Fläche gehören ins Portal, nicht in den Erst-Wizard**  
`indoor-spaces` · spaeter-moeglich

- Befund: indoor-spaces (fge_onboarding_render_indoor_spaces, onboarding.php:2956) erfasst 4 Gruppen mit ~23 Kacheln (Spielmöglichkeiten, Ausstattung, Tagen/Arbeiten, Weitere Aktivitäten), freie Sonstiges-Kacheln und Gesamtfläche m². Optional (keine Validierung) und aktuell nirgends konsumiert (siehe tote-daten-Fund). Das ist ein langer, scrollintensiver Schritt gegen Julius' Ziel 'kein Overload, im Optimalfall kein Scrollen'.
- Empfehlung: Aus dem Onboarding entfernen und im Partnerportal (nach Anmeldung) pflegbar machen, sobald es dort eine Indoor-Anzeige gibt. Für die reine Anfragbarkeit reichen Standort, Boxen/Technik (indoor-detail) und Öffnungszeiten.

**'club'-Option widerspricht dem Slide-Hinweis und ist nachgelagert wirkungslos**  
`indoor-kind` · flow

- Befund: indoor-kind (onboarding.php:2931) bietet 'lounge' und 'club' = 'Simulator auf einem Golfplatz' (catalogs.php:242). Gleichzeitig sagt der Hinweis der Slide (onboarding.php:2950): Indoor-Bereich eines bestehenden Golfclub-Partners braucht KEIN neues Profil, sondern läuft über das Club-Portal. Wer 'club' wählt, wird also teils direkt wieder weggeschickt. Zudem verzweigt das Manifest NICHT nach indoor_kind (statisch), und _fge_indoor_kind wird nach dem Onboarding nirgends gelesen (grep: 0 Treffer außerhalb onboarding, nur als Pflicht-Gate in fge_onboarding_is_submittable, onboarding.php:399).
- Empfehlung: Entscheiden: Wenn 'club' nur zum Wegleiten dient, die Slide auf eine reine Weiche/Info reduzieren oder ganz streichen und lounge als Default annehmen. Wenn indoor_kind fachlich gebraucht wird, dann tatsächlich einen Abnehmer schaffen (interne Segmentierung/Anzeige), sonst ist es tote Pflichtabfrage.

**Überschrift 'Indoor Golf Anlage' bricht die eigene 'kein Anlage'-Regel**  
`indoor-detail` · copy

- Befund: indoor-detail-Header (onboarding.php:2848): 'Welche Ausstattung hat eure Indoor Golf Anlage?' und der Review-Block heißt 'Anlage' (onboarding.php:3845). Direkt in basics steht dagegen der bewusste Kommentar (onboarding.php:2252): '„Anlage" trifft den Ton der Branche nicht … Indoor-Betriebe sprechen von ihrem „Indoor Golf"'. Die beiden Stellen widersprechen dieser Festlegung.
- Empfehlung: 'Anlage' in Header und Review-Block durch 'Indoor Golf' bzw. 'euer Indoor-Bereich' ersetzen, konsistent zur basics-Entscheidung.

### LOW

**indoor-detail bündelt sehr viele Pflichtfelder auf einer Slide (Scroll)**  
`indoor-detail` · pflicht

- Befund: Eine Slide (onboarding.php:2845) verlangt Pflicht: Boxen, Personen/Box komfortabel, Personen/Box maximal, mindestens 1 System (Multi), Linkshänder-Select, max. Personen gleichzeitig, dazu optionale Features-Kacheln und 'Anderes System'. Das ist die EINZIGE Indoor-Slide, die real konsumiert wird (Portal), also inhaltlich berechtigt, aber die Fülle sprengt Julius' Ziel 'kein Scrollen auf normalem Display'.
- Empfehlung: Beibehalten (wird gebraucht), aber Personen-pro-Box komfortabel/maximal ggf. zu einem Feld vereinfachen oder Features optional unter ein Accordion legen, um die Höhe zu reduzieren.

**Toter 'fun'-Icon-Eintrag in indoor-kind**  
`indoor-kind` · aufraeumen

- Befund: $kind_icons in fge_onboarding_render_indoor_kind (onboarding.php:2936-2940) enthält Icons für 'lounge', 'club' UND 'fun'. fge_catalog_indoor_kinds() liefert aber nur noch 'lounge' und 'club' (catalogs.php:242-245, seit Julius 01.09. auf zwei Pole reduziert). Der 'fun'-Zweig ist unerreichbar.
- Empfehlung: Den 'fun'-Eintrag aus dem $kind_icons-Array entfernen (reines Aufräumen, kein Verhalten).

**Preis-Slide erfasst keinerlei Daten (reiner Info-Screen)**  
`pricing` · aufraeumen

- Befund: fge_onboarding_render_step_10 (onboarding.php:3695) rendert nur eine Hero-Karte + 3 nummerierte Erklärschritte, 0 Eingabefelder (verifiziert: kein <input>/fge_onboarding_input im Slide-Body). Für Indoor gibt es zusätzlich KEINE billing-Slide im Manifest, d. h. der ganze 'pricing'-Schritt sammelt nichts. Inhaltlich ok (Preise entstehen pro Angebot im Portal), aber es ist ein zusätzlicher Klick ohne Dateninput.
- Empfehlung: Erwägen, das Preis-Prinzip in die intro-3-Kapitel-Einleitung zu integrieren und die eigenständige pricing-Slide zu sparen, oder klar als reinen Info-Schritt belassen. Keine Datenlücke, nur Straffungspotenzial.

**Ganzjährig/Saison + 'außerhalb der Öffnungszeiten' erzeugen Daten ohne Abnehmer**  
`indoor-hours` · info-unnoetig

- Befund: indoor-hours (onboarding.php:3035) erfasst neben dem Wochentags-Raster zusätzlich Ganzjährig ja/nein mit Monats-Von/Bis (_fge_indoor_year_round/open_from/open_to) und 'Events außerhalb der Öffnungszeiten' (_fge_indoor_after_hours). Das Raster erzeugt zwar eine lesbare Zusammenfassung (_fge_indoor_open_note via fge_indoor_hours_summary), aber auch diese wird nach dem Onboarding nirgends angezeigt (grep: 0 Treffer außerhalb onboarding). Der Review-Block trägt außerdem noch ein Legacy-Label 'aufpreis' => 'Ja, möglich' (onboarding.php:4138) mit, obwohl das Feld nur noch ja/nein kennt.
- Empfehlung: Kern-Öffnungszeiten (Raster) behalten, aber Saison-Monate und After-Hours erst dann abfragen, wenn ein Abnehmer (öffentliche Seite/Matching) existiert; ansonsten in den Wizard-Endausbau/Portal verschieben. Legacy-'aufpreis'-Label beim Aufräumen entfernen.
