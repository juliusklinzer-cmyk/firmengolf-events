# Wizard-Audit 28.08.2026 (Stand 1.9.177)
7 Dimensionen, je Fund adversarisch am Code verifiziert. 81 bestätigt, 2 verworfen.
Agenten: Flow/Navigation, Validierung, Datenfluss, Responsive/Mobile, Copy/Wording, A11y+Security, Vollständigkeits-Kritiker.
| Schwere | Anzahl |
|---|---|
| critical | 2 |
| high | 10 |
| medium | 33 |
| low | 36 |

---

## CRITICAL

### Indoor-Partner sieht seinen Indoor-Reiter im Portal nie
- **Dimension/Wizard:** dataflow · indoor / indoor-detail  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/helpers.php` :79
- **Problem:** fge_partner_has_indoor() prueft nur _fge_infra auf indoor/trackman/toptracer. Der Indoor-Wizard hat keine infra-Slide; nur die gastro-Slide schreibt Gastro-IDs nach _fge_infra (onboarding.php 571-577). Ein fertiger Indoor-Partner hat daher has_indoor=false: Portal-Tab Indoor wird entfernt (partner-portal.php 1145-1147), fge_portal_section_indoor blockt mit Fehlertext, der auf den fuer Indoor nicht existierenden Tab Platz/Ausstattung verweist (2223-2225), und fge_portal_handle_indoor_update stirbt mit 403 (593). Die im Wizard erhobenen _fge_indoor_sim-Daten sind im Portal weder sichtbar noch aenderbar.
- **Verifizierer-Notiz:** Am ECHTEN Datenbestand verifiziert: Partner 926 (Typ indoor, _fge_indoor_sim vollstaendig, _fge_infra = ['bar','lounge']) liefert fge_partner_has_indoor(926) === false. Statisch bestaetigt: kein Codepfad im Indoor-Wizard schreibt indoor/trackman/toptracer nach _fge_infra (grep _fge_infra in onboarding.php: nur Cases infra und gastro).
- **Fix:** fge_partner_has_indoor() auf den Partnertyp erweitern: return 'indoor' === fge_partner_type( $partner_id ) || ( is_array( $infra ) && [] !== array_intersect( [ 'indoor', 'trackman', 'toptracer' ], $infra ) ); Zusaetzlich den Fehlertext in fge_portal_section_indoor typabhaengig machen (bei Indoor-Typ nie erreichbar, bei course weiter auf Tab Platz/Ausstattung verweisen).

### Indoor-Partner (Formular B) hat im Portal keinen Indoor-Reiter und kann seine Simulator-Daten nie wieder bearbeiten
- **Dimension/Wizard:** gaps · indoor / indoor-detail  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/partner-portal.php` :1145
- **Problem:** Vollstaendig am Code und in der DB belegt. helpers.php 79-82: fge_partner_has_indoor() prueft ausschliesslich indoor/trackman/toptracer in _fge_infra. Der Indoor-Wizard schreibt _fge_infra nur ueber die geteilte gastro-Slide (onboarding.php 571-577, nur Gastro-ids); 'indoor' wird nirgends gespiegelt. DB-Check am Testpartner 926 (Typ indoor, in_pruefung, _fge_indoor_sim gefuellt mit 4 Boxen/trackman): _fge_infra = ['bar','lounge'], fge_partner_has_indoor(926) === false. Folgen im Code: Tab-Unset partner-portal.php 1145-1147, Save-Handler wp_die 593-594, Sektion-Guard 2223. Der Wizard verspricht den Reiter woertlich (onboarding.php 2892: 'Der Club pflegt Indoor direkt in seinem Partnerportal im Reiter Indoor-Golf'). Zusatzbefund ebenfalls bestaetigt: fge_portal_section_platz forkt nur fuer coach (3376-3378), der indoor-Typ bekommt im Tab 'Anlage' (Label-Umbenennung 1154-1156) die Golfplatz-Sektionen steckbrief (Platztyp-Select aus fge_catalog_golf_types, 3604-3609) und ausstattung (Platz-Infra-Katalog).
- **Verifizierer-Notiz:** Behavioral am realen Datenbestand (Partner 926) und an allen genannten Codezeilen verifiziert.
- **Fix:** fge_partner_has_indoor() um 'indoor' === fge_partner_type( $partner_id ) erweitern (ein Einzeiler in helpers.php 81, repariert Tab, Save-Handler und Sektion-Guard gleichzeitig), und in fge_portal_section_platz (3376-3378) eine eigene Sektionsliste fuer den indoor-Typ anlegen (steckbrief ohne Platztyp, statt Platz-Ausstattung die Indoor-Spaces), analog zum Coach-Fork.

## HIGH

### Zurück auf Slide 1 + Start legt neuen Partner an, Fortschritt weg
- **Dimension/Wizard:** flow · all / intro-1  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :890
- **Problem:** Selbst reproduziert: Indoor-Partner 938 (Token 6f56e88c…) bis Slide 3 gebracht, Zurück-Link auf Slide 2 zeigt auf ?ob_step=1&ob_token=… (Footer Zeile 1684, uses_token immer true). GET Slide 1 mit gültigem Token rendert intro-1 mit Start-Button (Type-Chooser-Guard Zeile 1307 greift nur ohne Partner). POST Start → Redirect ?ob_step=2&ob_token=75d17657… und neuer Draft-Partner 939; der alte Token ist aus der URL verschwunden, Partner 938 verwaist. Grep bestätigt: _fge_onboarding_step wird nur geschrieben (Zeilen 916/1013/1036 via max()), nie für Resume gelesen; GET nur mit ?ob_token ohne ob_step rendert ebenfalls intro-1 mit Start-Button (getestet).
- **Verifizierer-Notiz:** Der intro-1-Zweig (Zeile 890-903) ruft fge_onboarding_create_draft_partner ohne jeden Partner-Check auf. Severity high bestätigt: realer Ein-Klick-Pfad (Zurück-Link existiert auf Slide 2) mit vollständigem Fortschrittsverlust.
- **Fix:** Im intro-1-Zweig vor der Draft-Anlage fge_onboarding_get_current_partner_id() prüfen: bei existierendem Partner mit dessen Token auf max(2, min(fge_onboarding_get_progress()+1, total)) redirecten statt neu anzulegen. Zusätzlich im Renderer bei Slide 1 mit gültigem Token den Primärbutton als Weiter-Link auf den gespeicherten Fortschritt rendern.

### Alle Indoor-Wizard-Metas sind tote Daten (kein Reader ausserhalb des Wizards)
- **Dimension/Wizard:** dataflow · indoor / indoor-kind/indoor-spaces/indoor-formats/indoor-hours/location  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/partner-portal.php` :3372
- **Problem:** _fge_indoor_kind, _fge_indoor_spaces(_custom), _fge_indoor_area, _fge_indoor_formats, _fge_indoor_open_note, _fge_indoor_year_round, _fge_indoor_open_from/_to, _fge_indoor_after_hours und _fge_indoor_entrance_note werden nur in onboarding.php geschrieben und in der Onboarding-Review gelesen; danach existiert kein Reader mehr. fge_portal_section_platz (3372-3388) behandelt nur is_coach als Sonderfall, Indoor-Partner bekommen die course-Sektionen steckbrief/ausstattung/standort mit Platztyp-Select und Golfplatz-Ausstattungskatalog, die ihr Wizard nie befuellt hat (Partner 926: _fge_golf_type = ''). single-firmengolf_partner.php (Zeile 14) branch nur fuer coach, Indoor faellt ins Golfplatz-Layout ohne Indoor-Daten.
- **Verifizierer-Notiz:** Grep ueber Plugin und Theme: jede der genannten Meta-Keys hat exakt eine Fundstelle (der Write in onboarding.php); die Review liest ueber das $v-Array derselben Datei. Einziger Treffer ausserhalb ist page-indoor-partner.php 111, der nur den Katalog fge_catalog_indoor_formats() rendert, keine Partner-Meta. DB-Befund an Partner 926 bestaetigt _fge_golf_type = ''. Randnotiz: Speichert ein Indoor-Partner die falsche steckbrief-Sektion, ueberschreibt sie zudem _fge_cap min/max und _fge_event_formats.
- **Fix:** Eigene Anlage-Ansicht und Edit-Sektionen fuer den Indoor-Typ im Portal, mit Persistenz ueber die vorhandenen Slide-Cases (fge_onboarding_save_slide mit indoor-kind/indoor-spaces/indoor-formats/indoor-hours, analog zum coach-Muster in fge_portal_handle_profile_update Case profil/standorte); plus Indoor-Zweig in single-firmengolf_partner.php analog zum coach-Zweig in Zeile 14.

### rw-stepper im Onboarding komplett unstyled, +/- Buttons nur 9x25px
- **Dimension/Wizard:** mobile · coach / coach-capacity  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :3272
- **Problem:** Live nachgemessen (390x844, coach Step 9 'Deine Gruppengroessen'): 10x BUTTON .rw-step-btn mit w:9 h:25, padding 0, border 0, background transparent; 5x INPUT .rw-stepper-val 55x31 mit UA-Rahmen (border 2px, weisses bg). Code bestaetigt die Ursache doppelt: (a) in fge-frontend.css sind ALLE .rw-step-btn-Regeln (6708 bis 6711) und .rw-stepper-val-Regeln (6713 bis 6715) unter .rw-overlay gescoped, nur der Container .rw-stepper (6707) sowie -mid/-unit sind ungescoped, deshalb rendert die Pille, aber innen ist alles nackt; (b) der Reset .ob-shell button (fge-onboarding.css Zeile 15, 0-1-1) strippt zusaetzlich padding/border/background. fge-onboarding.css enthaelt null rw-stepper-Regeln (grep leer). Kein .rw-overlay im Onboarding-Markup (grep onboarding.php leer).
- **Verifizierer-Notiz:** Markup fge_onboarding_stepper() onboarding.php 3272 bis 3276; genutzt nur auf coach-capacity (3294 bis 3317).
- **Fix:** In fge-onboarding.css .ob-shell-gepraefixte Regeln fuer .rw-step-btn (min. 44px Kreis, border 1px var(--ink-200), background var(--paper-100), font-size 22px, inline-flex zentriert) und .rw-stepper-val (width 96px, border 0, background transparent, text-align center, font-size 24px, appearance textfield plus ::-webkit-inner-spin-button aus) ergaenzen, 1:1 nach dem Vorbild fge-frontend.css 6708 bis 6715. Wichtig: Selektor mind. .ob-shell .rw-step-btn (0-1-1) plus Quellreihenfolge nach dem Reset, oder .ob-shell button.rw-step-btn (0-1-2) fuer sicheren Gewinn gegen den Reset.

### .ob-add-contact vom .ob-shell button Reset gestrippt: 25px-Textzeilen statt Buttons
- **Dimension/Wizard:** mobile · all / contacts  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-onboarding.css` :629
- **Problem:** Live nachgemessen (Step 7 contacts): 4x BUTTON .ob-add-contact mit w:350 h:25, padding 0, border 0, background transparent. CSS bestaetigt: .ob-add-contact (Zeile 629, Spezifitaet 0-1-0, padding 14px 22px, 1.5px dashed border) verliert border/background/padding an den Reset .ob-shell button (Zeile 15, 0-1-1). Auch .ob-add-contact-row .ob-add-contact (Zeile 1409, 0-2-0) stellt nur width/justify, nicht die Optik wieder her. Markup onboarding.php 2549 bis 2556, Slide ist in allen drei Manifesten enthalten.
- **Fix:** Zeilen 629/630 auf .ob-shell .ob-add-contact und .ob-shell .ob-add-contact:hover anheben (0-2-0 schlaegt den Reset), wie es .ob-shell .ob-top-pill (Zeile 48, mit Kommentar zur Reset-Spezifitaet) bereits vormacht. Padding 14px 22px ergibt dann ca. 51px Hoehe, Touch-Ziel erledigt sich mit.

### Coach-Kapitel-Labels passen nicht zu den Kapiteln, Label 4 erscheint nie
- **Dimension/Wizard:** copy · coach / all (Footer-Fortschritt)  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1648
- **Problem:** Selbst verifiziert: $chapter_labels['coach'] hat 4 Einträge (Z. 1648), das Coach-Manifest (Z. 96 bis 110) kennt nur chapter 1 bis 3. Flow-Test GET ?ob_step=1&ob_type=coach liefert genau drei ob-prog-label: 'Über dich', 'Deine Anlage', 'Dein Angebot'; 'Rahmen & Preis' kommt im HTML nicht vor. Kapitel 2 (coach-formats, coach-capacity = Angebots-Inhalte) läuft unter 'Deine Anlage', Kapitel 3 (avail/pricing/media/review = Rahmen-Inhalte) unter 'Dein Angebot'; die location-Slide ('Wo unterrichtest du?') steckt in Kapitel 1 'Über dich'. Der Fortschrittsbalken zeigt damit im gesamten Coach-Wizard falsche Kapitelnamen.
- **Verifizierer-Notiz:** Beide Belege des Auditors halten der Prüfung stand (Code + Live-Flow).
- **Fix:** Kleinster Eingriff ohne Manifest-Umbau: Label-Array auf drei inhaltlich passende Kapitel kürzen, z. B. 1 'Über dich', 2 'Dein Angebot', 3 'Rahmen & Preis' (Kapitel 1 enthält dann zwar auch die location-Slide, 'Über dich' trägt das aber). Sauberer: Manifest auf 4 Kapitel umziehen (location→2, coach-formats/coach-capacity→3, avail/pricing/media/review→4), dann passt das bestehende Label-Array unverändert.

### Bestätigungsseite ist für alle drei Typen hart auf 'Golfplatz' getextet
- **Dimension/Wizard:** copy · all / Bestätigungsseite (ob_submitted)  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :4067
- **Problem:** Selbst verifiziert: fge_onboarding_render_confirmation() (Z. 4055 bis 4101) hat keinerlei Typ-Abzweig. Z. 4067 'Geschafft! Dein Golfplatz ist bei uns.', Z. 4079 Receipt-Label 'Platz', Z. 4088 'Wir schauen uns alles persönlich an und geben deinen Platz frei.', Z. 4093 '… gewinn dabei neue Besucher, Interessenten und Mitglieder.' (Golfclub-Logik). Golflehrer und Indoor-Betriebe lesen als letzten Eindruck, ihr 'Golfplatz' sei eingereicht. Der Hinweis des Auditors stimmt auch: Der Typ ist auf der Seite ermittelbar, Z. 4071 holt bereits fge_onboarding_get_current_partner_id(), fge_partner_type($done_pid) steht zur Verfügung.
- **Fix:** In fge_onboarding_render_confirmation() nach Z. 4071 $type = fge_partner_type($done_pid) ziehen (bzw. den pid-Fetch vor den Titel vorziehen) und Titel, Receipt-Label und Freigabe-Satz variieren: coach 'Geschafft! Dein Profil ist bei uns.' / Receipt 'Profil', indoor 'Geschafft! Euer Indoor Golf ist bei uns.' / Receipt 'Location'; den Mitglieder-Pitch (Z. 4093) und ggf. den Teamevent-CTA-Text nur für course zeigen, für coach/indoor neutral formulieren.

### Medien-Widget per Tastatur nicht bedienbar
- **Dimension/Wizard:** a11y-sec · all / media  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/assets/js/fge-media-gallery.js` :160
- **Problem:** Selbst verifiziert: Z.160/161 (Logo-Slot) und Z.185/186 (Cover-Slot) setzen role=button + tabIndex=0, danach nur slot.addEventListener('click', ...) (Z.173 bzw. Z.198). grep keydown/keypress/keyup über die ganze Datei: 0 Treffer, ein div mit role=button feuert bei Enter/Space nichts. Für das Logo gibt es damit KEINEN Tastatur-Pfad (der echte <button class=fge-gallery-add>, Z.215, füttert nur die Galerie). Kachel-Aktionen (Stern/©/Entfernen, Z.262-283) sind echte fokussierbare Buttons, aber fge-media-gallery.css Z.107-112 hält .fge-tile-actions auf opacity:0 und blendet nur bei .fge-gallery-item:hover ein (Z.113 zeigt sie bei hover:none-Geräten, ein :focus-within-Pendant fehlt), fokussierte Buttons bleiben also unsichtbar. Umsortierung existiert nur als dragstart/drop (Z.287-294). Portal-Wiederverwendung bestätigt: partner-portal.php Z.3744 ruft fge_media_gallery_render(). Severity high ist gerechtfertigt: WCAG 2.1.1-Totalausfall für den Logo-Upload.
- **Verifizierer-Notiz:** Alle Zeilenangaben stimmen exakt. Einzige Nuance: Galerie-Fotos lassen sich per Tastatur über den echten Add-Button hochladen, nur Logo-Slot, Cover-Direkt-Upload, sichtbarer Fokus auf Kachel-Aktionen und Reihenfolge sind tot.
- **Fix:** In renderLogoSlot/renderCoverSlot die Slots als <button type=button> erzeugen (el('button', ...) + type='button', CSS erbt weitgehend) statt div+role, alternativ einen gemeinsamen keydown-Handler (Enter/Space → click()) an beide Slots hängen. In fge-media-gallery.css neben Z.112 ergänzen: .fge-gallery-item:focus-within .fge-tile-actions { opacity: 1; }. Für die Reihenfolge zwei zusätzliche fge-tile-btn (nach vorn/hinten) rendern, die moveBefore() aufrufen.

### Portal-Indoor-Formular zeigt entfernte Felder, deren Werte der verschlankte Save verwirft und Bestandswerte loescht
- **Dimension/Wizard:** gaps · all / indoor-detail  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/partner-portal.php` :2349
- **Problem:** Am Code belegt. fge_portal_section_indoor rendert fge_indoor_rental_clubs (2349-2353, mit Pflicht-Stern), fge_indoor_support (2360-2367, Stern), fge_indoor_exclusive (2370-2377, Stern), fge_indoor_exclusive_from (2382), fge_indoor_offseason (2387-2392), fge_indoor_winter_hours (2396-2397). Der Save-Handler (598: Validierung, 605: Persistenz) laeuft ueber fge_onboarding_save_slide('indoor-detail'), dessen Case (onboarding.php 642-657, Kommentar 'Betreuung/Exklusiv/Offseason/Winterzeiten sind gestrichen') _fge_indoor_sim als frisches Array mit nur 8 Keys schreibt: boxes/systems/systems_other/features/box_comfort/box_max/lefthand/max_persons. Eingaben in den 6 Altfeldern verschwinden kommentarlos; bei Partnern mit Altdaten im Array werden support/exclusive/offseason/winter_hours/rental_clubs beim naechsten Save geloescht. Die Pflicht-Sterne luegen: fge_onboarding_validate_slide('indoor-detail') (1175-1200) prueft nur boxes/systems/box_comfort/box_max/lefthand/max_persons. Erreichbar heute nur fuer Course-Partner mit Indoor-Ausstattung (wegen Fund 1), was Altdaten-Verlust genau dort real macht.
- **Verifizierer-Notiz:** DB-Beleg: _fge_indoor_sim von Partner 926 enthaelt exakt die 8 neuen Keys, keine rental_clubs/support/exclusive.
- **Fix:** Die 6 gestrichenen Felder aus fge_portal_section_indoor entfernen (Leihschlaeger laut Umbau bei den Spielmoeglichkeiten), damit Formular, Validierung und Save wieder dasselbe Modell sprechen.

### Indoor-Wizard-Daten haben keinen Abnehmer: kein Portal-Edit, keine oeffentliche Anzeige, versprochene Beispiel-Events werden nie gebaut
- **Dimension/Wizard:** gaps · indoor / indoor-formats  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2963
- **Problem:** Per Grep und Code-Lektuere belegt. indoor_kind/indoor_spaces/indoor_area/indoor_formats/indoor_open_note/indoor_year_round/indoor_open_from/to/indoor_after_hours werden ausserhalb von onboarding.php nirgends gelesen (page-indoor-partner.php nutzt nur die Katalog-Labels von fge_catalog_indoor_formats, keine Partner-Metas; _fge_indoor_sim wird nur in der wegen Fund 1 unerreichbaren Portal-Indoor-Sektion gelesen). single-firmengolf_partner.php forkt nur fuer coach (Zeile 14), Indoor-Partner bekommen die Golfplatz-Seite. Die indoor-formats-Slide verspricht woertlich 'Aus deiner Auswahl bauen wir zwei bis drei Beispiel-Events als Startpunkt' (2963), aber fge_onboarding_submit() (837-858) setzt nur Status/Tracking/Nummer/Mail/Token, keinerlei Event-Erzeugung; der Validierungs-Kommentar 1208 ('erzeugt nur Platzhalter-Events') beschreibt nicht existierenden Code. Kleine Praezisierung: _fge_indoor_entrance_note ist KEIN toter Meta, er lebt in der arrival-Slide und im Review (507-508, 2364, 3654-3655), hat aber ebenfalls keinen Abnehmer ausserhalb des Onboardings.
- **Fix:** Kurzfristig die Beispiel-Event-Zusage aus der Slide-Copy (2963) und den irrefuehrenden Kommentar (1208) entfernen oder die Seed-Logik beim Submit implementieren; mittelfristig Indoor-Sektionen im Portal-Platz-Tab und auf der oeffentlichen Partnerseite nachziehen (haengt an Fund 1).

### Bestaetigungsseite und Einreichungs-Mail sind golfplatz-fest verdrahtet (falsche Copy und falscher CTA fuer Coach und Indoor)
- **Dimension/Wizard:** gaps · all / review  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :4067
- **Problem:** Am Code belegt, keine Typ-Weiche vorhanden. fge_onboarding_render_confirmation() (4055-4101): 'Geschafft! Dein Golfplatz ist bei uns.' (4067), Receipt-Label 'Platz' (4079), 'gibt deinen Platz frei' (4088), CTA 'Erstes Teamevent erstellen' mit preset_type=teamevent (4057, 4095), obwohl das Portal fuer Indoor preset_type=indoor-golf kennt (partner-portal.php 2259). emails.php: Betreff 'Dein Golfplatz wurde zur Pruefung eingereicht' (728) und 'Dein Golfplatz-Profil' (743) hart verdrahtet; fge_onboarding_submit() ruft genau diese Mail fuer alle drei Typen (853). Portal-Pending-Notice 'geht dein Platz oeffentlich online' (partner-portal.php 1193). Fuer Coach und Indoor ist das der letzte Eindruck des Onboardings und durchgehend falsch; der Teamevent-Deeplink fuehrt Indoor-Partner in den falschen Eventtyp.
- **Fix:** Typ-Weiche analog zu den Intro-Texten (fge_onboarding_current_type() ist auf der Confirmation via Partner-ID verfuegbar): Ueberschrift/Receipt-Label/Freigabe-Satz je Typ, CTA fuer indoor auf preset_type=indoor-golf, fuer coach auf ein Coach-Format; in fge_send_onboarding_submitted_email() Betreff und 'Golfplatz-Profil' anhand fge_partner_type() parametrisieren; Pending-Notice im Portal ('dein Platz') mitziehen.

## MEDIUM

### Bearbeiten aus Review: Indoor hinzufügen landet auf Medien statt Review
- **Dimension/Wizard:** flow · course / infra  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :47
- **Problem:** Selbst reproduziert: frischer Course-Partner (Token 6de76e1b…), POST ob_step=9 mit fge_ob_return=1 und fge_infra[]=indoor → Redirect ?ob_step=17, dort rendert 'Bilder, die euren Platz zeigen.' (media); Review liegt jetzt auf ?ob_step=18. Ursache exakt wie gemeldet: fge_onboarding_current_has_indoor() cacht statisch (Zeile 47-54), fge_onboarding_total_slides() baut das Manifest schon in Zeile 870 (vor dem Save), fge_onboarding_ordinal_of('review') in Zeile 1053 rechnet daher mit dem 17er-Manifest, obwohl das Manifest nach dem Save 18 Slides hat. Der Umkehrfall (Indoor abwählen) heilt sich zufällig über das Step-Clamping in Zeile 1316.
- **Verifizierer-Notiz:** Von high auf medium korrigiert: Fehlnavigation auf eine falsche, aber harmlose Slide; kein Datenverlust, Review ist mit einem Klick erreichbar. Tritt nur im Edit-aus-Review-Pfad beim HINZUFÜGEN von Indoor auf.
- **Fix:** Den statischen has_indoor-Cache nach fge_onboarding_save_slide('infra') invalidierbar machen (z. B. optionaler $reset-Parameter in fge_onboarding_current_has_indoor) und den ob_return-/next-Redirect erst nach dem Reset berechnen; alternativ im infra-Zweig den Review-Redirect explizit gegen ein frisch gebautes Manifest fge_onboarding_manifest(fge_partner_type($partner_id)) auflösen.

### Speichern & beenden funktioniert auf der Hauptkontakt-Slide nicht
- **Dimension/Wizard:** flow · all / main  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :941
- **Problem:** Selbst reproduziert: POST ob_step=6 (main, course) mit fge_ob_save_exit=1 und ausgefüllten Kontaktfeldern ohne Verifizierung → Redirect …&ob_step=6&ob_err=1 (Code wurde versendet, Nur-Code-Ansicht) statt ?ob_saved=1 mit Resume-Link. Der Button ist auf der main-Slide vorhanden (2 Vorkommen: Topbar Zeile 1600 + Exit-Dialog Zeile 1613, beide für kind=form). Der main-Zweig (Zeilen 941-1020) endet in jedem Pfad mit exit, bevor die fge_ob_save_exit-Prüfung in Zeile 1039 erreicht wird; Speichern & beenden löst also je nach Zustand Code-Versand, Code-Fehlermeldung oder Weiter-Navigation aus, nie die Gespeichert-Seite.
- **Fix:** Am Anfang des main-Zweigs (nach der Feld-Sanitization, vor dem Verifizierungs-Block) isset($_POST['fge_ob_save_exit']) prüfen: fge_onboarding_save_slide($partner_id, 'main', $_POST) aufrufen (ohne Code-Versand und ohne Account-Anlage) und auf die ob_saved-URL mit Token redirecten, wie es der generische Zweig in Zeile 1043-1046 tut.

### Ohne Auto-Login-Cookie blockiert die eigene E-Mail die main-Slide dauerhaft
- **Dimension/Wizard:** flow · all / main  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :951
- **Problem:** Selbst reproduziert an Partner 942: E-Mail verif-test-d@example.org via Verified-Transient bestätigt, POST main → User 47 angelegt und als _fge_assigned_wp_user_id=47 am Partner hinterlegt, Redirect auf Slide 7. Erneuter POST derselben Slide mit derselben E-Mail OHNE Auth-Cookie (curl ohne Cookie-Jar, entspricht iOS-Privatmodus) → ob_err=1 mit Transient-Fehler 'Diese E-Mail ist bereits registriert. Bitte logge dich zuerst in dein Partnerkonto ein…'. Der Check in Zeile 951-956 vergleicht nur get_current_user_id() (dann 0) mit der User-ID und ignoriert, dass der Token-Partner diesen User bereits als eigenen Hauptkontakt führt; der -1-Pfad aus create_or_assign (Zeilen 996-1002) wirkt gleichartig. Wer den Hauptkontakt aus der Review bearbeitet oder per Zurück auf main landet, kommt ohne Login nicht mehr vorbei.
- **Fix:** In beiden Existing-User-Checks eine Ausnahme ergänzen: Wenn (int) get_post_meta($partner_id, '_fge_assigned_wp_user_id', true) === (int) $existing_user->ID, dann Metas via fge_onboarding_save_slide('main') aktualisieren und normal weiterleiten (der Token belegt den Besitz des Onboardings); nur bei fremder Zuordnung weiterhin zum Login leiten.

### Ungültiger/entwerteter Token: leerer Golfplatz-Wizard statt Hinweis
- **Dimension/Wizard:** flow · all / all  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1314
- **Problem:** Selbst reproduziert: GET ?ob_step=5&ob_token=deadbeef… → HTTP 200, rendert 'Anfahrt & Parken' (course-Slide 5) mit leeren Feldern; kein Treffer für 'ungültig' oder 'abgelaufen' im Markup. Der Guard in Zeile 1307-1312 (Type-Chooser) greift nur bei ob_step=1; ab Zeile 1314 wird ohne Partner-Check gerendert, fge_onboarding_current_type() fällt ohne Partner auf 'course' zurück (Zeile 36). Nach Einreichung wird der Token gelöscht (Zeile 857), alte Resume-URLs aus History/Mails treffen also genau diesen Pfad: Indoor-/Coach-Partner sehen einen fremden, leeren Golfplatz-Wizard.
- **Fix:** Im Renderer vor Zeile 1314: wenn fge_onboarding_get_token() !== '' und fge_onboarding_get_partner_id_by_token() === 0 (bzw. bei ob_step > 1 gar kein Partner auflösbar), eine eigene Hinweis-Seite rendern (Link ungültig oder Onboarding bereits eingereicht, mit Portal-Login und Neustart-Option) statt der leeren Default-Slide.

### Ansprechpartner mit ungültiger E-Mail wird stillschweigend verworfen
- **Dimension/Wizard:** validation · all / contacts  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :550
- **Problem:** Save-Case 'contacts' überspringt jede Karte mit leerer oder ungültiger E-Mail per continue (Zeilen 548-552), und fge_onboarding_validate_slide (1068-1283) hat keinen 'contacts'-Case, der default-Zweig liefert []. Vorher werden alle bestehenden No-Account-Kontakte gelöscht (543-546), eine ungültige Zeile kann also sogar zuvor gespeicherte Kontakte ersatzlos entfernen.
- **Verifizierer-Notiz:** Flow-Test reproduziert: POST auf Schritt 7 (course, Partner 941) mit fge_contact_name[]=Gastro Person, fge_contact_email[]=kaputte-mail → Redirect auf ob_step=8 ohne ob_err; fge_contacts_get(941) danach leeres Array. Gilt für alle drei Wizards (contacts-Slide in allen Manifesten).
- **Fix:** In fge_onboarding_validate_slide einen 'contacts'-Case ergänzen: über fge_contact_name[]/fge_contact_email[] iterieren; wenn eine Zeile Namen oder Rolle trägt, aber is_email(sanitize_email(...)) fehlschlägt, Fehler 'Bitte gib für NAME eine gültige E-Mail-Adresse an.' zurückgeben. Das Refill-Skript stellt die []-Felder bereits wieder her.

### Kapazitäts-Eingaben gehen bei Validierungsfehler verloren, Defaults ersetzen sie
- **Dimension/Wizard:** validation · course / capacity  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1352
- **Problem:** Das Refill-Skript (1348-1366) behandelt nur Arrays (name[]) und flache Namen. fge_cap kommt im Refill-JSON als Objekt an (verifiziert: {"fge_cap":{"min":"50","max":"30"}}), Array.isArray ist false und getElementsByName('fge_cap') findet die als fge_cap[min]/fge_cap[max] benannten Stepper-Inputs (Zeile 2780) nicht. Die Slide rendert die Startwerte aus $cap_val (10/80, Zeilen 2707-2716). Besonders tückisch: wer nach dem Fehler einfach Weiter klickt, speichert die Defaults 10/80 statt seiner Werte. Gleiches Muster trifft fge_coach_cap[...] auf der coach-capacity-Slide (Stepper Zeilen 3294-3317).
- **Verifizierer-Notiz:** Flow-Test reproduziert: POST fge_cap[min]=50, fge_cap[max]=30 → ob_err=1 mit 'Maximum muss ≥ Minimum sein.', aber value="10" und value="80" im gerenderten Formular, während das Refill-JSON die getippten Werte enthält und wirkungslos bleibt.
- **Fix:** Im Refill-Skript nicht-Array-Objekte behandeln: für typeof v === 'object' über Object.keys(v) iterieren und Felder per document.getElementsByName(k + '[' + sub + ']') setzen. Deckt fge_cap und fge_coach_cap ab.

### Bestätigungsseite nach Einreichung ist für Indoor und Golflehrer Golfplatz-Copy
- **Dimension/Wizard:** validation · all / review  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :4067
- **Problem:** fge_onboarding_render_confirmation (4055-4101) ist komplett typblind: Titel 'Geschafft! Dein Golfplatz ist bei uns.' (4067), Receipt-Label 'Platz' (4079), Statuszeile 'geben deinen Platz frei' (4088) und der CTA-Block 'Erstes Teamevent erstellen' mit Golfplatz-Argumentation 'neue Besucher, Interessenten und Mitglieder' plus preset_type=teamevent (4057, 4093-4095) erscheinen identisch nach Indoor- und Coach-Einreichung.
- **Verifizierer-Notiz:** Die Behauptung des Auditors, der Typ sei nach der Einreichung nur schwer ermittelbar, stimmt nicht ganz: Zeile 4071 holt bereits erfolgreich die Partner-ID über fge_onboarding_get_current_partner_id() (der Nutzer ist nach dem main-Schritt eingeloggt), der Receipt-Block rendert ja auch Name und Vorgangs-Nr. Der Fix ist damit einfacher als vorgeschlagen.
- **Fix:** In fge_onboarding_render_confirmation die Partner-ID an den Funktionsanfang ziehen und über fge_partner_type($done_pid) Titel, Receipt-Label, Statuszeile und CTA-Block je Typ variieren (indoor: 'Euer Indoor Golf ist bei uns.' + passendes preset, coach: 'Dein Profil ist bei uns.'). Kein Query-Arg nötig.

### Coach-Fortschrittsbalken: Kapitel-Labels um eins verschoben, 'Rahmen & Preis' erscheint nie
- **Dimension/Wizard:** validation · coach / coach-formats  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1648
- **Problem:** Zeile 1648 definiert für den Coach 4 Kapitel-Labels (Über dich / Deine Anlage / Dein Angebot / Rahmen & Preis), das Coach-Manifest (96-110) hat aber nur 3 Kapitel: location liegt in Kapitel 1, coach-formats/coach-capacity in Kapitel 2, avail/pricing/media/review in Kapitel 3. Ergebnis: die Angebots-Slides tragen das Label 'Deine Anlage', die Rahmen-Slides 'Dein Angebot', und 'Rahmen & Preis' wird nie gerendert.
- **Verifizierer-Notiz:** Flow-Test reproduziert: GET coach-Wizard Schritt 8 (coach-formats, Titel 'Was kannst du anbieten?') rendert die drei ob-prog-Labels 'Über dich', 'Deine Anlage', 'Dein Angebot' und als aktives Kapitel (ob-prog-count) 'Deine Anlage'.
- **Fix:** Labels auf das 3-Kapitel-Manifest anpassen, z. B. [1 => 'Über dich', 2 => 'Dein Angebot', 3 => 'Rahmen & Preis'] (oder das Manifest auf 4 Kapitel umstellen, falls die Anlage als eigenes Kapitel gewollt ist, dann bekämen coach-formats/coach-capacity chapter 3 und avail/pricing/media/review chapter 4).

### 'Speichern & beenden' auf der Hauptkontakt-Slide beendet nicht, sondern startet den Codeversand
- **Dimension/Wizard:** validation · all / main  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :941
- **Problem:** Der 'Speichern & beenden'-Button ist auf jeder form-Slide ein Submit mit name=fge_ob_save_exit (Topbar Zeile 1600, Mobile-Exit-Dialog 1613), auch auf 'main'. Der main-Branch des POST-Handlers (941-1020) läuft aber komplett vor der fge_ob_save_exit-Prüfung (1039) und beendet jeden Pfad mit exit: bei unverifizierter Adresse wird ein Verifizierungscode verschickt und die Nur-Code-Ansicht gezeigt, bei verifizierter Adresse sogar der Account angelegt und weitergeleitet. Die ob_saved-Notice mit Resume-URL erscheint nie.
- **Verifizierer-Notiz:** Flow-Test reproduziert: POST auf Schritt 6 (course) mit fge_ob_save_exit=1 und gültigen Kontaktdaten → Redirect ...ob_step=6&ob_err=1 (Code-Ansicht, Mail ausgelöst); identischer POST auf Schritt 3 (basics) → korrekt ...ob_saved=1&ob_step=3.
- **Fix:** Am Anfang des main-Branches (nach der Validierung) fge_ob_save_exit prüfen: fge_onboarding_save_slide($partner_id, 'main', $_POST) aufrufen, Fortschritt setzen und zur ob_saved-URL (wie Zeile 1043-1046) redirecten, ohne Codeversand und ohne Account-Anlage.

### Portal-Standorte-Formular verdoppelt die Hausnummer in _fge_street
- **Dimension/Wizard:** dataflow · coach / portal standorte  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/partner-portal.php` :3534
- **Problem:** Das Feld fge_street wird mit trim( $m('street') . ' ' . $m('house_number') ) vorbefuellt (3534), der Save-Case standorte schreibt den kombinierten Wert aber nur nach _fge_street (476-480) und laesst _fge_house_number stehen. Nach einem Save ohne Aenderung: _fge_street = 'Golfweg 3', _fge_house_number = '3'. Alle Konkatenations-Anzeigen zeigen dann 'Golfweg 3 3': single-firmengolf_partner.php 106 und 267, partner-portal.php 3153, onboarding.php 3980. Jeder weitere Save haengt die Hausnummer erneut an.
- **Verifizierer-Notiz:** Statisch verifiziert: Formular-Value 3534, Save-Loop 476-480 (nur street/postal_code/city), Konkatenationen per grep bestaetigt. _fge_house_number wird vom geteilten location-Save (onboarding.php 487) befuellt, den der Coach-Wizard durchlaeuft, der Fall ist also real.
- **Fix:** Im Save-Case standorte vor dem Schreiben _fge_house_number leeren (delete_post_meta) und den kombinierten Wert in _fge_street belassen, oder sauberer: das Formular in getrennte Felder Strasse und Hausnummer aufteilen wie die course-Sektion standort (3658) und beide Keys speichern.

### Coach kann Bundesland und Karten-Pin im Portal nie korrigieren
- **Dimension/Wizard:** dataflow · coach / portal standorte  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/partner-portal.php` :476
- **Problem:** Der Save-Case standorte speichert nur street/postal_code/city plus die coach-venue-Felder; _fge_federal_state, _fge_latitude/_fge_longitude und _fge_google_place_id kommen weder im Formular (3525-3590, kein Bundesland-Select, keine Places-Suche) noch im Save vor. Wechselt der Coach die Anlage, bleiben Bundesland und Pin auf dem alten Standort. Der Formulartext verspricht selbst 'Die Adresse setzt Karten-Pin, Umkreissuche und Stadt-Zuordnung'. Die Felder existieren nur in der course-Sektion standort, die Coaches nicht erreichen (Sektionsliste 3376-3378: profil/standorte/medien/kontakt). Im Wizard ist federal_state Coach-Pflicht (onboarding.php 408 in fge_onboarding_is_submittable).
- **Verifizierer-Notiz:** Alle vier Codeanker gegengelesen und bestaetigt. Der irrefuehrende Hinweistext im Formular ('Die Adresse setzt Karten-Pin ...') verschaerft den Fund: der Save aendert den Pin gerade nicht.
- **Fix:** Bundesland-Select plus die Places-Suche/Lat-Lng-Hidden-Felder aus der location-Slide in die standorte-Sektion uebernehmen und im Save-Case federal_state, latitude, longitude, google_place_id mitschreiben. Mindestens: bei geaenderter Adresse latitude/longitude/place_id loeschen, damit kein falscher Pin stehen bleibt.

### Indoor-Partner ohne _fge_cap und _fge_event_formats im Anfrage-Matching
- **Dimension/Wizard:** dataflow · indoor / indoor-detail  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :642
- **Problem:** fge_match_partners_for_request (request-responses.php 305 ff.) laedt ALLE publizierten Partner und bewertet ueber _fge_cap (min/max, Zeilen 328-336), _fge_event_formats (345-348) und _fge_season_from/_to (353-370). Der Coach-Wizard spiegelt min/max bewusst nach _fge_cap (onboarding.php 770-774), der indoor-detail-Save (642-657) schreibt ausschliesslich das _fge_indoor_sim-Array; indoor-hours setzt keine _fge_season_from/_to, _fge_event_formats bleibt leer. Indoor-Partner bekommen daher nie Kapazitaets-, Format- oder Saison-Punkte und werden bei klar unpassender Teilnehmerzahl nicht ausgeschlossen ($fits bleibt null statt false).
- **Verifizierer-Notiz:** Am publizierten Indoor-Testpartner 926 verifiziert: _fge_cap = '', _fge_event_formats = '', _fge_season_from = '' bei vollstaendigem _fge_indoor_sim (max_persons 30). Coach-Spiegel per Direktausfuehrung bestaetigt (siehe naechster Fund).
- **Fix:** Im Case indoor-detail nach dem update_post_meta von _fge_indoor_sim spiegeln: $cap = get_post_meta(...,'_fge_cap',true); $cap = is_array($cap)?$cap:[]; $cap['min'] = 1; $cap['max'] = absint($post['fge_indoor_max_persons'] ?? 0); update_post_meta(...). Im Case indoor-hours bei year_round '0' zusaetzlich _fge_season_from/_to aus indoor_open_from/_to setzen (bei '1' beide loeschen). Optional _fge_indoor_formats auf Event-Format-Keys mappen.

### Sichtbarkeits-Checkliste verlangt von Coach/Indoor unerfuellbare Punkte
- **Dimension/Wizard:** dataflow · all / portal sichtbarkeit  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/partner-portal.php` :1405
- **Problem:** fge_portal_visibility_checklist (1382-1408) ist fuer alle Typen identisch. 'Ausstattung angehakt' verlangt mindestens 5 _fge_infra-IDs und verlinkt ?tab=platz&edit=ausstattung. Coaches erheben nirgends Infrastruktur, der Punkt bleibt dauerhaft offen; 'ausstattung' fehlt in ihrer Sektionsliste (3376-3378), der Link faellt in fge_portal_section_platz kommentarlos auf das Coach-Profil zurueck. Indoor-Partner landen im Golfplatz-Ausstattungskatalog, der nicht ihr Datenmodell ist. Auch 'Beschreibung deines Platzes' und 'Anfahrt & Parken' (edit=standort) passen fuer Coach nicht. fge_coach_formats/fge_coach_cap sind im Portal nur lesbar (einziger Treffer: Read in 3260), nirgends editierbar.
- **Verifizierer-Notiz:** Alle Anker verifiziert: Checkliste 1401-1408 ohne Typ-Branch, Coach-Sektionen ohne ausstattung/standort, sichtbarkeit-Tab fuer alle Typen erlaubt (747, Teaser 1504). Grep bestaetigt: keine Portal-Inputs fuer fge_coach_formats oder fge_coach_cap.
- **Fix:** fge_portal_visibility_checklist typabhaengig machen (fge_partner_type): Coach-Variante mit Profiltext, Fotos, Formaten, Gruppengroessen (plus Edit-Sektion dafuer im Portal ergaenzen); Indoor-Variante mit indoor_spaces statt _fge_infra und Link auf eine kuenftige Indoor-Edit-Sektion (haengt am Fund zu den toten Indoor-Metas).

### Galerie-Aktionsbuttons (Stern/Copyright/Loeschen) im Onboarding ohne weissen Kreis, 32px Touch-Ziel
- **Dimension/Wizard:** mobile · all / media  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-media-gallery.css` :114
- **Problem:** Live per Probe im echten .fge-media-Container auf der media-Slide (Step 12, .fge-media serverseitig vorhanden): button.fge-tile-btn hat computed background rgba(0,0,0,0) statt rgba(255,255,255,.95), 32x32px, nur der box-shadow bleibt. Ursache identisch: .fge-tile-btn (fge-media-gallery.css 114, 0-1-0) verliert background an .ob-shell button (0-1-1); width/height 32px ueberleben, liegen aber unter 44px. @media (hover:none) { .fge-tile-actions { opacity:1 } } (Zeile 113) bestaetigt, dass Touch-Geraete die Buttons dauerhaft in der kaputten Variante sehen.
- **Fix:** In fge-onboarding.css .ob-shell .fge-tile-btn { background: rgba(255,255,255,.95); width: 44px; height: 44px; } ergaenzen (plus :hover background #fff), oder in fge-media-gallery.css auf .fge-media .fge-tile-btn (0-2-0) praefixen. Beim Vergroessern die Icon-SVG-Groesse (16px) beibehalten.

### Nur-Code-Ansicht: Link E-Mail-Adresse anpassen sieht aus wie toter Text
- **Dimension/Wizard:** mobile · all / main  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2473
- **Problem:** Markup bestaetigt: onboarding.php 2473 rendert <a class="ob-linkbtn" href="...ob_editmail=1...">E-Mail-Adresse anpassen</a> direkt neben dem korrekt gestylten <button class="ob-linkbtn"> (2472). CSS 1389/1390 matcht ausschliesslich button.ob-linkbtn (.ob-shell button.ob-linkbtn, button.ob-linkbtn), keine a-Variante. Live-Probe eines a.ob-linkbtn im ob-shell: color rgb(14,19,16) (geerbtes ink-900 statt fairway-Blau), text-decoration none, keinerlei Link-Affordanz. Der Rueckweg zum vollen Kontaktformular ist damit optisch unsichtbar, direkt neben einem blauen unterstrichenen Zwilling.
- **Fix:** fge-onboarding.css 1389/1390 um die Anker-Variante erweitern: .ob-shell a.ob-linkbtn, .ob-shell button.ob-linkbtn, button.ob-linkbtn { ... } (und :hover analog). Beiden Elementen padding (z. B. 12px 4px) plus negatives margin geben, damit das Tap-Ziel Richtung 44px kommt.

### Bearbeiten-Links auf der Review-Slide nur 61x20px
- **Dimension/Wizard:** mobile · all / review  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-onboarding.css` :947
- **Problem:** Live nachgemessen (coach review, Step 13): 10+ A .ob-rev-edit je 61x20px, padding 0. CSS: .ob-rev-edit (947) font-size 13px ohne padding, a.ob-rev-edit (946) nur inline-flex. Auf der wichtigsten Korrektur-Oberflaeche vor der Einreichung liegen alle Rettungs-Links weit unter der 44px-Linie, die sich das Projekt mit Funnel-Audit P5 selbst gesetzt hat.
- **Fix:** .ob-rev-edit padding: 12px 0 12px 12px; margin: -12px 0 -12px -12px; geben (Optik unveraendert, Tap-Ziel 44px hoch) oder als kleine Pille mit min-height 44px ausfuehren.

### Hilfe-Drawer: Schliessen-Button gestrippt und Hintergrund-Seite scrollt weiter
- **Dimension/Wizard:** mobile · all / all (Hilfe-Drawer)  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-onboarding.css` :1087
- **Problem:** Beide Teile live bestaetigt. (1) .ob-help-close (1087, 0-1-0: border 1px ink-900, paper-50-bg, font-size 22px) verliert gegen .ob-shell button (Zeile 15, 0-1-1): computed border 0px, background transparent, font-size 16px (font:inherit-Shorthand resettet auch font-size), 40x40. Uebrig bleibt ein nacktes kleines x auf dem Vollbild-Panel. (2) Scroll-Lock fehlt: Help-Script onboarding.php 1776 bis 1784 toggelt nur h.hidden, kein body-overflow-Lock; .ob-help (1074 bis 1084) hat overflow-y:auto ohne overscroll-behavior. Live bei offenem Drawer: body overflow visible, overscrollBehavior auto, window.scrollTo scrollte die dahinterliegende Seite 0 auf 600.
- **Fix:** (1) Selektor auf .ob-shell .ob-help-close anheben (Rahmen, paper-50, 22px zurueck) und width/height auf 44px. (2) In open()/close() document.body.style.overflow = 'hidden' / '' setzen und overscroll-behavior: contain auf .ob-help; das gleiche open/close-Muster nutzt auch der Exit-Scrim.

### Entfernen-Button in Kontakt-Karten vom Reset gestrippt (25px-Ziel)
- **Dimension/Wizard:** mobile · all / contacts  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-onboarding.css` :605
- **Problem:** Live bestaetigt: nach Klick auf Weiteren Ansprechpartner hinzufuegen misst button.ob-contact-remove 68x25px mit padding 0 und background transparent (CSS-Soll 607: padding 6px 10px). Zusaetzlich verliert er sein color var(--ink-500) an color:inherit des Resets (beides 0-1-0 vs 0-1-1). Der Hover (610, 0-2-0) gewinnt zwar, aber das Grundziel bleibt 25px hoch, direkt neben den Eingabefeldern der Karte. Markup onboarding.php 2615.
- **Fix:** Zeilen 605 bis 610 auf .ob-shell .ob-contact-remove (und :hover) anheben und padding auf ca. 13px 12px erhoehen bzw. min-height 44px setzen.

### Indoor-Hauptkontakt-Slide spricht von 'Platz' und 'Rolle im Club'
- **Dimension/Wizard:** copy · indoor / main  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2486
- **Problem:** Selbst verifiziert per Flow-Test (Indoor-Wizard, Slide 6, frisch angelegter Draft-Partner): Die Seite zeigt 'Wer ist der Hauptkontakt?', 'mit dem Platz verknüpft und bekommt einen Login fürs Partnerportal', 'Rolle im Club' und Platzhalter 'z. B. Clubmanager, Eventleitung'. Code Z. 2448/2481 bis 2497 kennt nur $main_is_coach ja/nein, indoor fällt auf die course-Texte zurück.
- **Verifizierer-Notiz:** Severity von high auf medium korrigiert: gleiche Fehlerklasse wie der 'Golfplatz suchen'-Fund auf der Indoor-location-Slide (course-Copy ohne Indoor-Abzweig, je zwei falsche Strings auf einer Slide); für Konsistenz beide medium. Der Befund selbst ist voll bestätigt.
- **Fix:** In fge_onboarding_render_step_4() einen $main_is_indoor-Zweig ergänzen: Subtitle 'Diese Person wird mit eurer Location verknüpft und bekommt einen Login fürs Partnerportal. …' und das Rollen-Feld mit Label 'Rolle bei euch' (Platzhalter 'z. B. Inhaber, Studioleitung, Eventleitung').

### Google-Suchfeld auf der Indoor-Standort-Slide heißt 'Golfplatz suchen'
- **Dimension/Wizard:** copy · indoor / location  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2323
- **Problem:** Selbst verifiziert per Flow-Test (Indoor-Wizard, Slide 4): Im selben Formular stehen H1 'Wo findet man euch?' (euch-Ansprache), Label 'Golfplatz suchen', Hint 'Such deinen Golfplatz bei Google, Adresse, Bundesland und Kartenpin füllen sich automatisch.' (Du-Form) und Platzhalter 'z. B. Golfclub Hamburg-Wendlohe…'. Code: Block Z. 2319 bis 2328 ist nur mit '! $is_coach' gegated, kein Indoor-Abzweig.
- **Fix:** Im Block Z. 2319 ff. per $is_indoor variieren: Label 'Eure Location suchen', Hint 'Sucht euren Betrieb bei Google, Adresse, Bundesland und Kartenpin füllen sich automatisch.', Platzhalter mit fiktivem Indoor-Beispiel (z. B. 'Indoor Golf Musterstadt').

### Kontextspezifische Button-Labels gehen durch No-op fge_onboarding_next_btn verloren
- **Dimension/Wizard:** copy · all / main / media  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1802
- **Problem:** Selbst verifiziert: fge_onboarding_next_btn() ist ein No-op (Z. 1802), der Footer kennt nur 'Start'/'Weiter'/'Zur Prüfung einreichen' (Z. 1687). Flow-Test: Nach Codeversand auf der main-Slide (H1 'Bestätige deine E-Mail-Adresse') heißt der Primary-Button 'Weiter', obwohl der Klick den Code prüft; 'Code bestätigen' (Aufruf Z. 2476) kommt im HTML nicht vor. 'Weiter zur Zusammenfassung' (media, Z. 3587) verpufft genauso. Die contacts-Slide umgeht das per JS-Umbenennung des Footer-Buttons (Z. 2586 bis 2591), das Muster existiert also schon.
- **Fix:** fge_onboarding_render_footer() um ein optionales Label-Override erweitern (z. B. statisches Register, das die Slide-Renderer vor dem Footer-Render füllen, oder ein Filter) und mindestens die Code-Ansicht auf 'Code bestätigen' setzen; alternativ das vorhandene JS-Muster der contacts-Slide (querySelector auf button[form=ob-step-form]) für die Code-Ansicht und media wiederverwenden.

### 'Schnupperkurs' im Coach-Wizard trotz Richtlinie 'Grundlagenkurs für Teams'
- **Dimension/Wizard:** copy · coach / intro-1 / coach-formats  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2148
- **Problem:** Selbst verifiziert: (1) onboarding.php:2148 Coach-Intro-Lead 'Unternehmen suchen Golflehrer für Schnupperkurse, Platzreife und Teamevents.'. (2) Auf der coach-formats-Slide rendert der Block 'Größere Eventmodule' fge_catalog_partner_formats() (onboarding.php:3261) und damit die Kachel 'Schnupperkurs' (catalogs.php:179), direkt unter der Coach-Format-Liste mit 'Grundlagenkurs für Teams' (catalogs.php:387, Kommentar Z. 385 f. verweist ausdrücklich auf die globale Richtlinie). Beide Begriffe für dasselbe Konzept auf einer Slide.
- **Fix:** Intro-Lead auf 'Grundlagenkurse' umstellen; in fge_catalog_partner_formats() nur das LABEL des Keys 'schnupperkurs' auf 'Grundlagenkurs' ändern (Key stabil lassen, gespeicherte Auswahlen!). Achtung Reichweite: fge_catalog_partner_formats() wird laut Docblock auch von Portal, Admin-Metaboxen und Eignungsprüfung genutzt, die Label-Änderung wirkt überall; das entspricht aber gerade der Richtlinie.

### 'Anlage' als Indoor-Produktname an vielen sichtbaren Stellen (Branchenton 'Indoor Golf')
- **Dimension/Wizard:** copy · indoor / intro-1/intro-2, indoor-kind, indoor-detail, review, Footer, Typ-Wahl  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :3632
- **Problem:** Alle genannten Stellen selbst verifiziert: intro-1 Eyebrow 'Erzählt uns von eurer Anlage' (Z. 2122) und Lead 'erfassen wir eure Anlage' (Z. 2124); intro-2 Titel 'Was macht eure Anlage zum Firmenevent?' (Z. 2131); Footer-Kapitel 'Eure Anlage' (Z. 1647); Fehlertext 'Bitte wähle aus, was eure Anlage am besten beschreibt.' (Z. 1205); indoor-detail-Header 'Welche Ausstattung hat eure Indoor Golf Anlage?' (Z. 2790, Mischform); Review-Block 'Anlage' mit Zeile 'Anlagentyp' (Z. 3632 f.); Typ-Wahl-Karte 'Ihr betreibt eine Indoor-Anlage mit Simulatoren' (Z. 2083); Partner-Typ-Label 'Indoor-Anlage' (catalogs.php:24). Kommentar Z. 2224 f. dokumentiert die Entscheidung ('Anlage trifft den Ton der Branche nicht'), umgesetzt ist sie nur in basics. Die Ausnahmen des Auditors (catalogs.php:244 'Freizeit- und Entertainment-Anlage', Coach-Kontext) sind korrekt eingeordnet.
- **Fix:** Konsistent auf 'euer Indoor Golf' bzw. 'eure Location' umstellen: Eyebrow 'Erzählt uns von eurem Indoor Golf' (Z. 2122/2124), intro-2 'Was macht euer Indoor Golf zum Firmenevent?' (Z. 2131), Footer-Kapitel 'Euer Indoor Golf' (Z. 1647), Fehlertext 'was euch am besten beschreibt' (Z. 1205, passt auch zum Slide-Titel 'Was beschreibt euch am besten?'), indoor-detail 'Welche Ausstattung hat euer Indoor Golf?' (Z. 2790), Review-Block 'Indoor Golf' + Zeile 'Typ' (Z. 3632 f.), Typ-Karte 'Ihr betreibt Indoor Golf mit Simulatoren, ganzjährig buchbar.' (Z. 2083), Katalog-Label 'Indoor Golf' (catalogs.php:24; wirkt auch in Admin/Portal, gewollt).

### Tastaturfokus auf Karten, Tages-Kacheln und Ja/Nein-Radios unsichtbar
- **Dimension/Wizard:** a11y-sec · all / alle Kachel-Slides (golftype, infra, gastro, formats, indoor-*, coach-*, avail, arrival)  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-onboarding.css` :1258
- **Problem:** Selbst verifiziert: fge-onboarding.css Z.1258 (.ob-card-input { position:absolute; opacity:0; pointer-events:none; }) und Z.1349 (.ob-day input analog) verstecken die Inputs fokussierbar, korrekt. Aber grep über die gesamte Datei liefert Fokus-Stile NUR für .ob-input:focus (Z.167), .ob-stepper-input:focus-visible (Z.718), .ob-input--error:focus (Z.1368) und .ob-foot-legal a:focus-visible (Z.1396). Kein einziger :has(...:focus-visible)/:focus-within-Selektor für .ob-card, .ob-day oder die Ja/Nein-Radios (onboarding.php Z.2414/2418, 2757/2761, 2991/2995, 3039/3043, 3422/3426 mit style=position:absolute;opacity:0). WCAG 2.4.7 auf dem Großteil der Slides verletzt.
- **Verifizierer-Notiz:** Zeilenangaben und grep-Ergebnis exakt bestätigt.
- **Fix:** .ob-card:has(.ob-card-input:focus-visible), .ob-day:has(input:focus-visible), .ob-radio:has(input:focus-visible) { outline: 2px solid var(--fairway-600, #3768C0); outline-offset: 2px; } an die Fokus-Gruppe in fge-onboarding.css anhängen (gleiches Muster wie .ob-stepper-input:focus-visible in Z.718). Die Ja/Nein-Radios brauchen ggf. eine gemeinsame Wrapper-Klasse, aktuell tragen die Labels unterschiedliche Klassen je Slide.

### Kontakt-Karten: Labels ohne for/id-Verknüpfung
- **Dimension/Wizard:** a11y-sec · all / contacts  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2618
- **Problem:** Selbst verifiziert in fge_onboarding_contact_card() (Z.2606-2647): Z.2618 <label class="ob-field-label">Name</label> + Z.2619 <input name="fge_contact_name[]"> ohne id; Z.2623/2624 Rolle-Select, Z.2632/2633 E-Mail-Input, Z.2637/2639 Berechtigung-Select, alle vier ohne for/id und ohne Label-Wrapping. Kontrast bestätigt: fge_onboarding_input() (Z.1838) setzt korrekt for/id. Da die Karte auch als leeres Template geklont wird, ist das Label-Wrapping die klonsichere Lösung.
- **Verifizierer-Notiz:** Zeilen minimal verschoben (2618 statt 2617), inhaltlich exakt.
- **Fix:** In fge_onboarding_contact_card() jedes Feld implizit verknüpfen: <label class="ob-field-label">Name <input ...></label> bzw. das Label als Wrapper um Select. CSS-seitig .ob-field-label ggf. auf display:block prüfen, damit das Layout unverändert bleibt. Keine statischen ids verwenden (Template-Klonung).

### Karten-Gruppen ohne Gruppen-Semantik und ohne programmatisches Pflicht-Kennzeichen
- **Dimension/Wizard:** a11y-sec · all / golftype, indoor-kind, coach-kind, infra, gastro, indoor-detail, avail  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2660
- **Problem:** Selbst verifiziert: Z.2660-2667 (infra) rendert div.ob-cat > div.ob-cat-h + div.ob-cards, grep über die ganze Datei findet KEIN fieldset/legend und kein role=group/radiogroup. fge_onboarding_card() (Z.2002-2027) wrappt Input+Optionstext zwar korrekt im Label (der Optionsname selbst ist zugänglich), aber der Gruppenname (.ob-cat-h) ist nicht verknüpft. Die Karten-Inputs tragen kein required/aria-required (Z.2014), Pflicht wird nur serverseitig geprüft (fge_onboarding_validate_slide, golftype Z.1069-1072). Der Zähl-Badge wird in fge_onboarding_cards_script() per textContent gesetzt (Z.2049) ohne aria-live.
- **Verifizierer-Notiz:** Alle drei Teilbehauptungen (keine Gruppen-Semantik, kein aria-required, Badge ohne aria-live) am Code bestätigt.
- **Fix:** div.ob-cat → <fieldset class="ob-cat"><legend class="ob-cat-h">…</legend> (fieldset/legend brauchen einen CSS-Reset: border:0;padding:0;margin:0, legend float:left o. ä. wegen Grid). Bei Pflicht-Slides aria-required=true auf das fieldset bzw. bei Radiogruppen role=radiogroup. Dem in fge_onboarding_cards_script() erzeugten Badge beim Anlegen badge.setAttribute('aria-live','polite') geben.

### Fehlermeldungen ohne aria-live/Fokus-Führung; Selects und Gruppen ohne aria-Verankerung
- **Dimension/Wizard:** a11y-sec · all / alle Form-Slides  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1822
- **Problem:** Selbst verifiziert: fge_onboarding_error() (Z.1822-1830) gibt <p class="ob-field-error"> ohne role/aria-live aus, id nur bei übergebener desc_id. fge_onboarding_input() setzt bei Fehlern korrekt aria-invalid + aria-describedby (Z.1845/1848), fge_onboarding_select() dagegen nicht: Z.1869 rendert das Select ohne aria-Attribute, Z.1875 ruft fge_onboarding_error() ohne desc_id. Gruppen-Fehler stehen frei im DOM (z. B. fge_infra Z.2656, fge_golf_type Z.2218). Der Fehler-Redirect (Z.928-937) hängt nur ob_err=1 an, kein Fragment/Fokus-Ziel.
- **Verifizierer-Notiz:** Exakt wie gemeldet, inkl. der korrekten Würdigung, dass fge_onboarding_input() bereits sauber ist.
- **Fix:** In fge_onboarding_error() role="alert" ergänzen. fge_onboarding_select() auf das Muster von fge_onboarding_input() bringen: bei isset($errors[$name]) aria-invalid="true" aria-describedby="{$id}-error" setzen und fge_onboarding_error($errors,$name,$id.'-error') aufrufen. Zusätzlich ein kleines Inline-Script bei ob_err=1: document.querySelector('.ob-field--error .ob-input, .ob-field-error') fokussieren bzw. scrollIntoView. Gruppen-Fehler nach dem fieldset-Umbau (siehe Gruppen-Fund) per aria-describedby am fieldset verankern.

### Exit-Dialog und Hilfe-Drawer: aria-modal ohne Fokus-Management
- **Dimension/Wizard:** a11y-sec · all / alle (Topbar/Hilfe)  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1608
- **Problem:** Selbst verifiziert: Exit-Scrim Z.1608-1634: role=dialog aria-modal=true (Z.1609), Öffnen nur via scrim.hidden=false (Z.1629), kein focus()-Aufruf, kein Tab-Trap, keine Fokus-Rückgabe; Escape schließt (Z.1633). Hilfe-Drawer Z.1753-1784: identisches Muster, function open(){ h.hidden = false; } (Z.1779), Escape-Handler Z.1783 schließt sogar bedingungslos bei jedem Escape (auch wenn der Drawer zu ist, harmlos aber unsauber). Hintergrund bleibt tabbable, obwohl aria-modal das Gegenteil behauptet.
- **Verifizierer-Notiz:** Beide Codestellen exakt bestätigt.
- **Fix:** In den beiden Inline-Scripts: beim Öffnen den Auslöser merken und den ersten Button im Dialog fokussieren (scrim.querySelector('button, a').focus()), beim Schließen den gemerkten Auslöser refokussieren. Für den Trap am einfachsten inert auf dem Rest: document.querySelector('.ob-shell').inert = true beim Öffnen, false beim Schließen (inert ist in allen aktuellen Browsern verfügbar).

### Code-Versand ohne IP-Rate-Limit: Mail-Versand an beliebige Fremdadressen skalierbar
- **Dimension/Wizard:** a11y-sec · all / main  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/includes/email-verification.php` :42
- **Problem:** Selbst verifiziert: fge_ev_send_code() drosselt nur pro Zieladresse, $cool_key (Z.42, 60s je Kontext+Adresse) und $rate_key (Z.48, 8/Stunde je Adresse), beide md5 über die E-Mail, keine IP-Komponente. Im main-Handler (onboarding.php Z.974-978) löst jede seit dem letzten Versand geänderte Adresse sofort einen neuen Versand aus, ohne fge_form_rate_limited-Prüfung. Kontrast bestätigt: Draft-Anlage ist IP-gedrosselt (Z.892, 10/h), Resume-Link ebenfalls (Z.4146, 5/10min), nur der Code-Versand nicht. Ein Token-Halter kann also unbegrenzt viele verschiedene Fremdadressen mit Code-Mails beschicken. Belästigungs-/Mailserver-Reputationsrisiko, kein Datenleck, medium passt.
- **Verifizierer-Notiz:** Zeilenangabe präzisiert: die Rate-Keys liegen bei Z.42/48, nicht Z.35. Die Einordnung als Härtung (kein akuter Exploit) ist korrekt.
- **Fix:** Im main-Handler direkt vor dem fge_ev_send_code()-Aufruf (onboarding.php Z.978): if ( function_exists('fge_form_rate_limited') && fge_form_rate_limited( 10, 3600, 'ev_send' ) ) { Fehlermeldung wie beim Cooldown setzen und redirecten; }. Das deckt auch den Resend-Button mit ab; die bestehenden Adress-Limits bleiben unverändert.

### Coach-Fortschrittsbalken zeigt falsche Kapitel-Labels (4 Labels fuer 3 Kapitel, um eins verschoben)
- **Dimension/Wizard:** gaps · coach / coach-formats  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1648
- **Problem:** Live nachgetestet: Coach-Draft-Partner erzeugt (Typ coach), GET Schritt 8 (coach-formats, Titel 'Was kannst du anbieten?') rendert ob-prog-count 'Deine Anlage' und die Segmente 'Ueber dich / Deine Anlage / Dein Angebot'; 'Rahmen & Preis' (Eintrag 4) erscheint nie. Ursache exakt wie gemeldet: Coach-Manifest (96-110) hat 3 Kapitel (2 = coach-formats + coach-capacity, 3 = avail/pricing/media/review), die Label-Tabelle (1648) traegt noch 4 Eintraege der alten Sequenz. Severity von high auf medium korrigiert: durchgehend sichtbares, aber rein orientierendes Label-Problem, keine Blockade, kein Datenverlust, keine falsche Aktion.
- **Verifizierer-Notiz:** Flow-Test mit frischem Coach-Token durchgefuehrt, Testpartner danach geloescht. Achtung beim eigenen Nachtesten: der oeffentliche POST auf Schritt 1 ist rate-limited (10/h) und ob_type muss als GET-Parameter mitkommen, sonst entsteht ein course-Partner.
- **Fix:** In $chapter_labels (1645-1649) den coach-Eintrag auf [ 1 => 'Ueber dich', 2 => 'Dein Angebot', 3 => 'Rahmen & Preis' ] aendern und Eintrag 4 loeschen.

### Fehlender Rueckfluss: Coach-Formate, Gruppengroessen und Verfuegbarkeit im Portal nicht editierbar
- **Dimension/Wizard:** gaps · coach / coach-formats  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/partner-portal.php` :3335
- **Problem:** Am Code belegt. Coach-Sektionsliste = profil/standorte/medien/kontakt (3376-3378). Das Panel 'Deine Formate' (3335-3342) ist das einzige Profil-Panel ohne Bearbeiten-Link; _fge_coach_formats wird im Portal nur lesend genutzt (3258-3262). _fge_coach_cap wird ausserhalb des Wizards nur auf der oeffentlichen Visitenkarte gelesen (fge-partner-coach.php 56), im Portal weder angezeigt noch editierbar; die Wizard-Copy 'Anpassen geht spaeter jederzeit, pro Angebot im Partnerportal' (onboarding.php 3281) deckt zwar die Werte pro Angebot, aber nicht die hier erfassten Standardwerte ab. Auch die avail-Daten (Event-Tage, Vorlauf, Saison; Gastro-Einbindung immerhin in standorte editierbar, 3578-3584) haben keine Coach-Portal-Sektion.
- **Fix:** Coach-Sektion 'angebot' ergaenzen (Formate inkl. fge_event_formats, Gruppengroessen-Stepper, Verfuegbarkeit) und im Save-Handler (partner-portal.php um Zeile 473) auf fge_onboarding_save_slide('coach-formats'/'coach-capacity'/'avail') routen, wie es 'standorte' mit 'coach-venue' bereits vormacht; dem Formate-Panel (3336) den Bearbeiten-Link geben.

### coach_kind wird verpflichtend erhoben, aber nirgends verwendet (Rechnungssteller-Weiche fuer angestellte Coaches fehlt komplett)
- **Dimension/Wizard:** gaps · coach / coach-kind  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :698
- **Problem:** Per Grep belegt: _fge_coach_kind kommt nur in onboarding.php vor (Save 698-700, Validate 1216-1219 als Pflicht, Renderer 3055-3073, Loader 1539) plus Katalogdefinition. fge_onboarding_review_blocks_coach (3936-4016) zeigt es nicht, Portal-Coach-Profil (partner-portal.php 3240 ff.) nicht, Visitenkarte nicht. Der Katalog-Kommentar benennt den Zweck ausdruecklich als offen (catalogs.php 326-331: bei 'employed' ist der Rechnungssteller der Golfclub, 'Backend fragt den Rechnungssteller dann als Golfplatz ab — noch offen'); Platzhalter-Code dafuer existiert nicht. Erste Pflichtfrage des Coach-Wizards ohne jede Wirkung.
- **Fix:** coach_kind mindestens im Review-Profil-Block (3956-3961) und in den Admin-Partner-Feldern anzeigen, damit das Team die employed-Faelle sieht; die Rechnungssteller-Logik als dokumentiertes Arbeitspaket fuehren (deckt sich mit der offenen Restfrage in der Memory-Datei) oder die Frage streichen.

### Intro-Bullets versprechen noch Abrechnung und Preis-Aufschlag (Reste der entfernten Billing-Slide)
- **Dimension/Wizard:** gaps · coach / intro-1  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2149
- **Problem:** Am Code belegt. Coach intro-1 listet 'Abrechnung, Fotos & Einreichung' (2149) als vierten Punkt, obwohl das Coach-Manifest (96-110) keine billing-Slide enthaelt und die pricing-Slide reine Info ist (Save-Case 614-617 'Nothing to persist'). Course intro-3: Lead 'Verfuegbarkeit, Aufschlag und Bilder' (2174) und Bullet 'Preis-Aufschlag & Abrechnung' (2175), waehrend die Pricing-Slide (3482-3522) das Netto-Modell erklaert, in dem der Partner gar keinen Aufschlag eingibt ('Den Firmengolf-Aufschlag kalkulieren wir oben drauf'). Partner erwarten einen Schritt bzw. eine Eingabe, die nie kommt.
- **Fix:** Coach intro-1 Bullet 4 auf 'Fotos & Einreichung' kuerzen; Course intro-3 Lead auf 'Verfuegbarkeit, Preis-Prinzip und Bilder' und Bullet 2 auf 'Preis-Prinzip' umtexten (Copy-Regeln beachten: keine Gedankenstriche).

## LOW

### Coach-Wizard: Kapitel-Labels im Fortschritt um eins verschoben
- **Dimension/Wizard:** flow · coach / coach-formats  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1648
- **Problem:** Selbst reproduziert an Coach-Partner 946: Schritt 8 'Was kannst du anbieten?' → ob-prog-count 'Deine Anlage'; Schritt 9 'Deine Gruppengrößen' → 'Deine Anlage'; Schritt 10 (avail) und 11 (pricing) → 'Dein Angebot'; die Segment-Labels der drei Balken lauten 'Über dich / Deine Anlage / Dein Angebot', 'Rahmen & Preis' erscheint nie. Code: $chapter_labels['coach'] (Zeile 1648) hat 4 Einträge, das Coach-Manifest (Zeilen 96-110) nur die Kapitel 1-3; Kapitel 2 (coach-formats/coach-capacity) ist inhaltlich das Angebot, Kapitel 3 (avail/pricing/media/review) Rahmen & Preis.
- **Verifizierer-Notiz:** Von medium auf low korrigiert: rein kosmetisches Label im Fortschrittsbalken, keine funktionale Auswirkung auf Navigation oder Daten; sichtbar allerdings auf jeder Coach-Slide ab Schritt 8.
- **Fix:** Zeile 1648 auf drei Einträge kürzen: 'coach' => [ 1 => 'Über dich', 2 => 'Dein Angebot', 3 => 'Rahmen & Preis' ].

### enctype=multipart hart auf Schritt 11 verdrahtet (heute: capacity)
- **Dimension/Wizard:** flow · all / capacity  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1805
- **Problem:** Selbst reproduziert: Course-Partner, ?ob_step=11 ('Wie viele Gäste passen wo?', capacity) rendert <form method="post" enctype="multipart/form-data" id="ob-step-form">; die media-Slide (?ob_step=17, 'Bilder, die euren Platz zeigen.') rendert das Formular OHNE enctype. Code: Zeile 1805 $enc = ( $step === 11 ) ? … , ein Relikt der alten festen Schrittfolge. Aktuell folgenlos, da die Bilder asynchron über das Media-Widget laufen; im Coach-Wizard trifft Schritt 11 die pricing-Slide, im Indoor-Wizard gastro.
- **Fix:** In fge_onboarding_form_open statt des Ordinals die Slide-ID prüfen ((fge_onboarding_slide($step)['id'] ?? '') === 'media') oder das enctype ersatzlos streichen, da media asynchron hochlädt.

### Billing/IBAN-Regression geprüft: keine Slide, keine Pflicht; nur toter Code übrig
- **Dimension/Wizard:** flow · all / review  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :619
- **Problem:** Am Code verifiziert: Keines der drei Manifeste (Zeilen 66-130) enthält 'billing', 'coach-authority' oder 'coach-includes'; fge_onboarding_is_submittable (Zeilen 375-431) verlangt für keinen Typ Abrechnungsdaten (Kommentar Zeile 382-383 dokumentiert die Entfernung explizit). Die Regression aus dem Auftrag ist damit nicht vorhanden. Übrig ist unerreichbarer Code: save-Case 'billing' Zeile 619 ff. (inkl. _fge_billing_iban Zeile 634), save-Case 'coach-authority' Zeile 739 ff., Validierung 'billing' Zeile 1132 ff. und 'coach-authority' Zeile 1250, Render-Dispatch Zeilen 1425/1428/1432 sowie fge_onboarding_render_billing Zeile 3524. Diese Cases sind nur über Slide-IDs erreichbar, die in keinem Manifest mehr vorkommen.
- **Verifizierer-Notiz:** Die Flow-Behauptung 'alle drei Wizards bis zur Einreichung durchgeklickt' habe ich nicht komplett wiederholt; die Code-Belege (Manifest + is_submittable) tragen den Befund allein. Severity low passt: reines Pflegerisiko.
- **Fix:** Die toten Cases (billing, coach-authority, coach-includes) in save_slide, validate_slide und im Render-Dispatch samt fge_onboarding_render_billing/…_coach_authority/…_coach_includes entfernen; die weiter genutzten _fge_billing_*-Portal-Metas davon unberührt lassen.

### Zahlenfelder: negative Werte werden per absint() stillschweigend positiv, keine Obergrenzen serverseitig
- **Dimension/Wizard:** validation · indoor / indoor-detail  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1177
- **Problem:** absint() nimmt den Absolutbetrag und die Validierung (1175-1200) prüft nur Untergrenzen und box_max >= box_comfort, keine Obergrenzen. Gleiche absint-Muster ohne Obergrenze in den Save-Cases capacity (584) und indoor-spaces area (672) sowie coach-capacity.
- **Verifizierer-Notiz:** Flow-Test reproduziert (Partner 944): POST fge_indoor_boxes=-3, fge_indoor_box_comfort=-4, fge_indoor_box_max=999999999, fge_indoor_max_persons=999999999 → Redirect ohne ob_err; _fge_indoor_sim danach ['boxes'=>3,'box_comfort'=>4,'box_max'=>999999999,'max_persons'=>999999999]. Die Markup-Grenzen (max-Attribute) greifen serverseitig nicht.
- **Fix:** Serverseitig auf die Markup-Grenzen klemmen (z. B. min(99, absint(...)) für boxes/box_comfort, min(999, ...) für box_max/max_persons, min(99999, ...) für area) oder bei Überschreitung Validierungsfehler ausgeben; Rohwerte mit führendem Minus vor absint() als ungültig behandeln.

### Ungültige E-Mail im Hauptkontakt meldet 'Pflichtfeld' statt 'ungültig'
- **Dimension/Wizard:** validation · all / main  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1106
- **Problem:** sanitize_email('not-an-email') liefert '', dadurch greift in fge_onboarding_validate die Pflichtfeld-Meldung, obwohl der Nutzer etwas eingetippt hat. Die vorbereitete Meldung 'Bitte gib eine gültige E-Mail-Adresse an.' (1110-1112) ist praktisch unerreichbar, weil sanitize_email jede von is_email abgelehnte Eingabe vorher leert.
- **Verifizierer-Notiz:** Flow-Test reproduziert: POST fge_contact_email=not-an-email auf Schritt 6 → ob_err=1, gerenderter Fehler 'E-Mail ist ein Pflichtfeld.' (id fge_contact_email-error).
- **Fix:** Im 'main'-Case den Rohwert unterscheiden: trim(wp_unslash($post['fge_contact_email'])) leer → Pflichtfeld-Meldung; nicht leer, aber ! is_email(sanitize_email(...)) → 'Bitte gib eine gültige E-Mail-Adresse an.'

### Keine Längenbegrenzung für Freitexte, Coach-Versprechen 'maximal 400 Zeichen' ungeprüft
- **Dimension/Wizard:** validation · all / basics  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :480
- **Problem:** Die öffentliche Kurzbeschreibung wird nur mit sanitize_textarea_field gespeichert (Zeile 480 basics, 716 coach-story), ohne maxlength im Markup und ohne serverseitige Kürzung, obwohl der coach-story-Platzhalter (3143) 'maximal 400 Zeichen' verspricht. Auch die eigenen Raum-Kacheln (fge_indoor_spaces_custom, 668-671) haben kein Längenlimit pro Kachel. maxlength existiert im Wizard nur für PLZ und Bestätigungscode (grep-verifiziert).
- **Verifizierer-Notiz:** Flow-Test reproduziert: 6000-Zeichen-Beschreibung auf der basics-Slide gespeichert (Partner 941, strlen der Meta = 6000), Redirect ohne Fehler. Die XSS-Aussage des Auditors (Tags werden von den Sanitizern entfernt, Ausgabe escaped) ist plausibel und deckt sich mit dem Code, wurde von mir aber nicht separat getestet.
- **Fix:** maxlength am Textarea-Markup (z. B. 400 fürs Kurzprofil, 40 pro Custom-Kachel) plus serverseitiges mb_substr im jeweiligen Save-Case (basics Zeile 480, coach-story 716, indoor-spaces 669).

### Regression-Check Billing/IBAN: bestanden, aber der komplette tote Code bleibt im Repo
- **Dimension/Wizard:** validation · all / review  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :619
- **Problem:** Regression bestanden: kein Manifest (61-140) enthält eine billing-Slide und fge_onboarding_is_submittable (375-410) verlangt keinerlei Abrechnungsdaten mehr (Kommentar 381-383 bestätigt die Absicht; indoor-Formate sind dort ebenfalls explizit optional, 397-399, passend zum leeren validate-Case 1207-1209). ABER: Save-Case 'billing' (619-640), Validierung inkl. IBAN-Pflicht (1132-1173), Renderer fge_onboarding_render_billing, Dispatch-Case (1432) und billing_*-Schlüssel im Vals-Loader (1473 ff.) existieren weiter; helpers.php (~93) behauptet noch 'Genutzt vom billing-Slide im Onboarding'. Ebenso tot: coach-venue, coach-authority, coach-includes (Renderer/Validate-Cases mit eigenen Pflichtfeldern, in keinem Manifest).
- **Verifizierer-Notiz:** Von mir verifiziert: Manifeste ohne billing/coach-venue/coach-authority/coach-includes, is_submittable ohne Billing-Anforderung, alle genannten toten Code-Stellen vorhanden. Den kompletten Indoor-Einreichungs-Durchlauf des Auditors habe ich nicht wiederholt; die statische Lage (keine billing-Slide erreichbar, keine Submit-Anforderung) belegt die Regression aber ausreichend.
- **Fix:** Billing-Save/Validate/Render-Cases, den Dispatch-Case und die billing_*-Vals-Schlüssel entfernen (Metas unangetastet lassen), Kommentar zu fge_is_valid_iban in helpers.php aktualisieren; die toten Coach-Renderer/Validate-Cases (coach-venue/authority/includes) im selben Zug ausbauen.

### Hardcodiertes enctype auf Schritt 11 trifft die falsche Slide in allen drei Wizards
- **Dimension/Wizard:** validation · all / capacity  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1805
- **Problem:** fge_onboarding_form_open setzt enctype=multipart/form-data bei $step === 11. Per Manifest ist Schritt 11 beim Golfplatz capacity, beim Indoor gastro, beim Coach pricing; die media-Slide (course 16 bzw. 17 mit indoor-detail, indoor 16, coach 12) bekommt kein enctype. Funktional folgenlos, weil Uploads asynchron über REST laufen, aber der Code ist irreführend und bräche still bei einem künftigen synchronen Upload-Feld.
- **Verifizierer-Notiz:** Flow-Test reproduziert: course Schritt 11 (capacity) rendert <form method="post" enctype="multipart/form-data" id="ob-step-form">, Schritt 16 (media, 'Bilder, die euren Platz zeigen.') rendert <form method="post" id="ob-step-form"> ohne enctype.
- **Fix:** Die Ordinal-Prüfung ersetzen: enctype nur wenn (fge_onboarding_slide($step)['id'] ?? '') === 'media', oder das Attribut ganz entfernen, da die media-Slide asynchron über die REST-Routen hochlädt.

### Coach-Intro nutzt 'Schnupperkurse' entgegen der Grundlagenkurs-Sprachregel
- **Dimension/Wizard:** validation · coach / intro-1  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2148
- **Problem:** Der Lead der Coach-Intro-Slide wirbt mit 'Unternehmen suchen Golflehrer für Schnupperkurse, Platzreife und Teamevents.', während der Formate-Katalog desselben Wizards bewusst 'Grundlagenkurs für Teams' heißt (catalogs.php 386-387 mit Richtlinien-Kommentar). Das Intro widerspricht der eigenen Wortwahl zwei Slides später.
- **Verifizierer-Notiz:** Beide Stellen im Code verifiziert. Zusatzbeobachtung am selben Intro: der Listenpunkt Zeile 2149 nennt noch 'Abrechnung, Fotos & Einreichung' als Wizard-Inhalt, obwohl der Abrechnungs-Slide aus allen Wizards entfernt wurde; das sollte im selben Fix mit angepasst werden (z. B. 'Verfügbarkeit, Fotos & Einreichung').
- **Fix:** Lead umformulieren: 'Unternehmen suchen Golflehrer für Grundlagenkurse, Platzreife und Teamevents.' und im Listenpunkt Zeile 2149 'Abrechnung' durch den tatsächlichen Kapitelinhalt ersetzen.

### Mehr als 12 eigene Raum-Kacheln werden kommentarlos verworfen
- **Dimension/Wizard:** validation · indoor / indoor-spaces  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :671
- **Problem:** array_slice($custom_spaces, 0, 12) kappt die eigenen Kacheln hart bei 12, ohne Fehlermeldung; weder der Renderer (2915-2931) noch das Hinzufügen-JS (2932-2953) kommunizieren oder erzwingen ein Limit, und gleiche Namen werden weder im JS noch beim Speichern dedupliziert.
- **Verifizierer-Notiz:** Flow-Test reproduziert (Partner 944): 15 Kacheln (Kachel1..15) gesendet → _fge_indoor_spaces_custom enthält exakt Kachel1 bis Kachel12, Redirect ohne ob_err.
- **Fix:** Limit im UI kommunizieren (Hinweistext + JS-Sperre in add() ab 12 Einträgen in #fge-spaces-custom) oder Validierungsfehler statt stillem Kappen; im Save-Case zusätzlich array_unique vor dem array_slice.

### Coach-Fortschrittsbalken zeigt verschobene Kapitel-Labels
- **Dimension/Wizard:** dataflow · coach / footer/progress  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1649
- **Problem:** chapter_labels['coach'] hat 4 Eintraege (1 Ueber dich, 2 Deine Anlage, 3 Dein Angebot, 4 Rahmen & Preis), das Coach-Manifest (Zeilen 96-110) nutzt aber nur die Kapitel 1-3: location lebt in Kapitel 1, coach-formats/coach-capacity in Kapitel 2, avail/pricing/media/review in Kapitel 3. Da die Labels per Kapitelnummer gemappt werden ($labels[$ch]), zeigt Kapitel 2 (inhaltlich Dein Angebot) 'Deine Anlage' und Kapitel 3 (inhaltlich Rahmen & Preis) 'Dein Angebot'; 'Rahmen & Preis' erscheint nie.
- **Verifizierer-Notiz:** Statisch eindeutig: Label-Array (1647-1651) vs. Manifest-Kapitel (96-110), Mapping in Zeile 1659 ($labels[$ch]). Severity von medium auf low korrigiert: rein kosmetischer Orientierungsfehler, keine Daten- oder Funktionswirkung.
- **Fix:** 'coach' => [ 1 => 'Ueber dich', 2 => 'Dein Angebot', 3 => 'Rahmen & Preis' ] (Wortlaut mit korrekten Umlauten, ohne Bindestriche gemaess Copy-Regel).

### Unbeantwortete Ganzjaehrig-Frage speichert still 'Ganzjaehrig' als Saison
- **Dimension/Wizard:** dataflow · indoor / indoor-hours  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :692
- **Problem:** Die Validierung der Slide (1211-1213) prueft nur fge_indoor_open_note. Die Ganzjaehrig-Radios starten unchecked (checked nur bei '1'/'0', Zeilen 2991/2995), $san_select liefert bei fehlender Antwort '' (Zeilen 461-464). Der Save schreibt dann _fge_indoor_year_round = '' aber _fge_season = 'Ganzjaehrig', weil der Ternary in Zeile 692 nur '0' als saisonal behandelt. Die Review zeigt fuer year_round '' eine leere Saison-Zeile (3917-3924), waehrend alle _fge_season-Leser Ganzjahresbetrieb behaupten, den der Partner nie bestaetigt hat.
- **Verifizierer-Notiz:** Codepfad deterministisch bestaetigt ($san_select-Definition, Ternary, Validierung, Radio-Render). Kein eigener Flow-Test noetig; Randdetail zur Auditor-Angabe: die Review-Zeile ist bei '' leer, die Diskrepanz zwischen Review und _fge_season besteht wie beschrieben.
- **Fix:** Zeile 692 dreistufig machen: '' === $year_round ? '' : ( '0' === $year_round ? 'Saisonal, siehe Oeffnungszeiten' : 'Ganzjaehrig' ). Alternativ die Frage in fge_onboarding_validate_slide fuer indoor-hours als Pflicht validieren.

### Cap-Spiegel erzeugt Muell-Element 0 => '' in _fge_cap
- **Dimension/Wizard:** dataflow · coach / coach-capacity  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :770
- **Problem:** (array) get_post_meta( ..., '_fge_cap', true ) castet den ''-Default fehlender Metas zu [0 => '']; das leere Element wird mitgespeichert. Per Direktausfuehrung von fge_onboarding_save_slide('coach-capacity') auf einem frischen Draft reproduziert: _fge_cap = array( 0 => '', 'min' => 4, 'max' => 24 ). Aktuelle Leser greifen per Key zu (Matching, Portal, Review), daher heute unschaedlich; iterierender oder zaehlender Code wuerde stolpern. Gleiches Muster in partner-portal.php Case steckbrief (~491). Das Trainer-mal-Richtwert-Maximum ist nur clientseitiges JS (3341-3348) und wird serverseitig nicht erzwungen; das ist als manuell anpassbar dokumentiert und kein Fehler.
- **Verifizierer-Notiz:** Verifiziert durch direkte Ausfuehrung des Save-Cases im Container (Ergebnis exakt array(0 => '', 'min' => 4, 'max' => 24); Test-Meta danach entfernt).
- **Fix:** $cap_common = get_post_meta( $partner_id, '_fge_cap', true ); $cap_common = is_array( $cap_common ) ? $cap_common : []; statt des (array)-Casts; dasselbe Muster in partner-portal.php Case steckbrief ($cap_existing) korrigieren.

### Review zeigt Karten-Pin-Status nur beim Coach, nicht bei Platz/Indoor
- **Dimension/Wizard:** dataflow · all / review  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :3650
- **Problem:** Der gemeinsame Standort-Block fuer course/indoor (3649-3657) zeigt nur Adresse, Bundesland und bei Indoor den Eingangs-Hinweis; die Karten-Pin-Zeile ('Gesetzt' / 'Noch nicht gesetzt', aus latitude/longitude) existiert nur in der Coach-Review (~3982). Platz- und Indoor-Partner koennen vor dem Einreichen nicht pruefen, ob ihr Pin gesetzt ist, obwohl er die Kartenanzeige der oeffentlichen Seite steuert.
- **Verifizierer-Notiz:** Beide Codestellen gegengelesen und bestaetigt. Eher Konsistenz-Luecke als Fehler, low ist passend.
- **Fix:** In den $loc_rows des gemeinsamen Standort-Blocks dieselbe Zeile ergaenzen: [ 'Karten-Pin', ( '' !== (string) ( $v['latitude'] ?? '' ) && '' !== (string) ( $v['longitude'] ?? '' ) ) ? 'Gesetzt' : 'Noch nicht gesetzt' ].

### Cookie-Einstellungen-Button im Karten-Consent ebenfalls vom Reset gestrippt
- **Dimension/Wizard:** mobile · all / location  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-onboarding.css` :145
- **Problem:** Statisch eindeutig belegt: Markup onboarding.php 2349 rendert <button class="ob-map-consent-btn"> im Consent-Fallback innerhalb des ob-shell. .ob-map-consent-btn (145 bis 151, 0-1-0: padding 8px 14px, border 1px, paper-bg) verliert padding/border/background an .ob-shell button (0-1-1). Exakt derselbe Mechanismus, der bei .ob-add-contact und .fge-tile-btn live vermessen wurde (identische Spezifitaetspaarung); im Testlauf nicht direkt renderbar, weil Klaro-Consent Maps global erlaubt hatte. Der Fund betrifft genau Nutzer, die Maps abgelehnt haben.
- **Fix:** Zeilen 145 bis 152 auf .ob-shell .ob-map-consent-btn (und :hover) anheben, padding Richtung 12px 16px fuer ein brauchbares Touch-Ziel.

### Ja/Nein-Radio-Pillen 40px hoch, unter der eigenen 44px-Linie
- **Dimension/Wizard:** mobile · all / avail / indoor-hours / arrival  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-onboarding.css` :754
- **Problem:** Live nachgemessen (coach avail, Step 10): 2x LABEL .ob-radio mit h:40 (w:76/90), padding 8px 18px, font 14px. Als <label> ist die Pille nicht vom Button-Reset betroffen, sie rendert korrekt, liegt nur 4px unter der 44px-Linie, die andere Onboarding-Controls (ob-day, ob-stepper-btn) nach Funnel-Audit P5 einhalten. Reine Konsistenz-Sache, korrekt als low eingestuft.
- **Fix:** In Zeile 756 padding auf 10px 18px anheben oder min-height: 44px auf .ob-radio setzen.

### Aendern-Pille auf Logo/Titelbild-Slot auf Touch-Geraeten unsichtbar
- **Dimension/Wizard:** mobile · all / media  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-media-gallery.css` :66
- **Problem:** Code eindeutig: .fge-slot-swap (66 bis 71) ist opacity:0 und wird nur ueber .fge-slot:hover / :focus-visible (73) bzw. .is-dragover (74) eingeblendet. Die Touch-Ausnahme @media (hover:none) in Zeile 113 gilt nur fuer .fge-tile-actions. Auf Geraeten ohne Hover fehlt die Tausch-Affordanz auf gefuellten Logo/Titelbild-Slots komplett.
- **Fix:** In fge-media-gallery.css die bestehende hover:none-Query erweitern: @media (hover: none) { .fge-tile-actions, .fge-slot.is-filled .fge-slot-swap { opacity: 1; } } (Klassenname fuer den gefuellten Zustand am JS pruefen).

### Impressum/Datenschutz/AGB im Sticky-Footer nur 19px hoch
- **Dimension/Wizard:** mobile · all / all (Footer)  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-onboarding.css` :1394
- **Problem:** Live nachgemessen (390x844): A Impressum 60x19, Datenschutz 67x19, AGB 23x19, padding 0. CSS 1394/1395: .ob-foot-legal font-size 12px, Links ohne padding, direkt unter der Zurueck/Weiter-Zeile im Footer. Tertiaere Pflicht-Links, low ist angemessen.
- **Fix:** In Zeile 1395 .ob-foot-legal a { padding: 12px 6px; margin: -8px 0; } geben und gap in 1394 leicht reduzieren (18px minus Link-Padding), damit die optische Dichte bleibt.

### E-Mail-Platzhalter 'name@golfclub.de' auch bei Indoor und Golflehrer
- **Dimension/Wizard:** copy · all / main / contacts  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2500
- **Problem:** Selbst verifiziert: Flow-Test Indoor-Wizard Slide 6 zeigt placeholder name@golfclub.de; Code Z. 2500 (Login-E-Mail, main) und Z. 2633 (Kontakt-Karte, contacts) ohne Typ-Abzweig, gilt also auch für coach.
- **Verifizierer-Notiz:** Severity von medium auf low korrigiert: reiner Platzhalter (verschwindet beim Tippen), führt nicht zu Fehleingaben, nur leicht unpassende Ansprache.
- **Fix:** Neutralen Platzhalter setzen, z. B. 'name@firma.de' oder 'deine@email.de' an beiden Stellen; typspezifische Varianten sind möglich, aber für einen Platzhalter nicht nötig.

### 'Schnupperkurs' im Platz-Wizard (Golfschule-Kachel und Kapazitätszeile)
- **Dimension/Wizard:** copy · course / infra / capacity  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/catalogs.php` :90
- **Problem:** Selbst verifiziert: catalogs.php:90 'trial-course' => 'Schnupperkurs' (Gruppe Golfschule, gerendert über fge_catalog_infra_groups() auf der infra-Slide) und catalogs.php:158 'Max. Teilnehmer Schnupperkurs' (cap_rows, capacity-Slide). Die Einordnung des Auditors ist fair: Hier ist es eine Leistung der Golfschule (Branchenbegriff), nicht der abgeschaffte Event-Typ; Richtlinien-Entscheidung liegt bei Julius.
- **Fix:** Falls die Richtlinie auch hier gelten soll: Labels auf 'Grundlagenkurs' / 'Max. Teilnehmer Grundlagenkurs' ändern (ids 'trial-course'/'trial' stabil lassen); andernfalls als bewusste Ausnahme in der Copy-Style-Notiz dokumentieren.

### Billing-Regression bestanden, aber Intro-Bullets versprechen noch 'Abrechnung' und Billing-Code liegt tot im File
- **Dimension/Wizard:** copy · all / intro-1 (coach) / intro-3 (course) + toter Code  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2149
- **Problem:** Selbst verifiziert: Kein Manifest (Z. 66 bis 130) enthält eine billing-Slide; fge_onboarding_is_submittable() verlangt keine Abrechnungsdaten (Kommentar Z. 382 f.: 'der billing-Slide ist raus'). Copy-Reste: Coach-Bullet 'Abrechnung, Fotos & Einreichung' (Z. 2149) und course-intro-3-Bullet 'Preis-Aufschlag & Abrechnung' (Z. 2175) kündigen einen Schritt an, den es nicht gibt (pricing ist reine Info-Slide, Z. 3482 ff.). Toter Code bestätigt: save-Case 'billing' (Z. 619 bis 639), validate-Case mit 'IBAN ist ein Pflichtfeld.' (Z. 1132 bis 1161), Renderer fge_onboarding_render_billing (Z. 3524 bis 3566), Dispatcher-Case 'billing' (Z. 1433) sowie unerreichbare Renderer-Cases coach-venue/coach-authority/coach-includes (Z. 1424, 1425, 1428). Zusatzbefund: fge_onboarding_render_coach_includes() wird im Dispatcher aufgerufen, ist aber NIRGENDS definiert; ein versehentliches Reaktivieren der Slide würde fatalen.
- **Verifizierer-Notiz:** Wichtige Korrektur am suggested_fix des Auditors: Der coach-venue-SAVE-Case (Z. 722 ff.) ist NICHT tot, die location-Slide ruft ihn über fge_onboarding_save_slide($partner_id, 'coach-venue', $post) auf (Z. 513). Nur die Renderer-Dispatcher-Cases sind unerreichbar.
- **Fix:** Bullets umformulieren (coach Z. 2149: 'Preis-Prinzip, Fotos & Einreichung'; course Z. 2175: 'Preis-Aufschlag & Preis-Prinzip'). Toten Code entfernen: billing save-/validate-/Renderer-Blöcke + Dispatcher-Case, Renderer-Cases coach-authority/coach-includes (letzterer ruft eine nicht existierende Funktion) und Renderer coach-venue/coach-authority; den coach-venue-SAVE-Case behalten, er wird von der location-Slide genutzt.

### Du/Ihr-Bruch im Coach-Wizard: 'bei euch' auf avail und coach-capacity
- **Dimension/Wizard:** copy · coach / avail / coach-capacity  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :3375
- **Problem:** Selbst verifiziert: geteilte avail-Slide fragt 'Wann sind Events bei euch möglich?' (Z. 3375) mit Hint 'Grober Zeitraum für Events bei euch.' (Z. 3456) und Info-Box 'fragen wir einzeln bei euch an' (Z. 3474); coach-capacity fragt 'Wie viele Trainer sind bei euch beschäftigt?' (Z. 3303). Der Coach-Wizard duzt sonst konsequent; für den ersten coach_kind 'Einzelner oder selbstständiger Golflehrer' (catalogs.php:335) ist 'bei euch beschäftigt' schief. $avail_is_coach existiert bereits (Z. 3384).
- **Fix:** avail-Header und -Hints für coach per $avail_is_coach auf Du-Form variieren ('Wann sind Events bei dir möglich?', 'Grober Zeitraum für Events bei dir.'); coach-capacity-Label neutral formulieren, z. B. 'Wie viele Trainer stehen zur Verfügung?' (passt für solo, school und employed).

### Sprachlich schiefes Feld-Label 'Maximal Personen gleichzeitig bei euch'
- **Dimension/Wizard:** copy · indoor / indoor-detail  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2866
- **Problem:** Selbst verifiziert: Z. 2866 Label 'Maximal Personen gleichzeitig bei euch' (grammatikalisch unsauber), zugehöriger Fehlertext Z. 1198 'Maximale Personenzahl im Indoor-Bereich fehlt.' — Label und Fehlertext benennen das Feld unterschiedlich.
- **Fix:** Label auf 'Maximale Personenzahl gleichzeitig bei euch' ändern (dann passt auch der Fehlertext Z. 1198 wieder zum Label).

### Echte Fremd-Markennamen als Platzhalter (Ruff Golf, Eisen 7, GC Augusta National)
- **Dimension/Wizard:** copy · all / basics  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2233
- **Problem:** Selbst verifiziert: Z. 2233 Platzhalter indoor 'z. B. Ruff Golf München oder Eisen 7 Indoor Golf', course 'z. B. GC Augusta National'. Der Kommentar Z. 2224 f. nennt Ruff Golf und Eisen 7 nur als interne Tonalitäts-Referenz; als sichtbare Platzhalter nennen sie potenziellen Partnern real existierende Mitbewerber bzw. eine geschützte fremde Marke.
- **Fix:** Fiktive Beispielnamen einsetzen: indoor 'z. B. Indoor Golf Musterstadt', course 'z. B. GC Musterstadt'.

### Regressions-Checks bestanden: keine Gedankenstriche in sichtbaren Texten, kein Hochsaison/Hauptsaison, Portal-Gate 'Jetzt Partner werden'
- **Dimension/Wizard:** copy · all / alle  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1
- **Problem:** Selbst nachgeprüft: (1) Alle Vorkommen von — und – in onboarding.php und catalogs.php liegen auf Kommentar-/PHPDoc-Zeilen, keines in einem String/sichtbaren Text (grep mit Kommentar-Filter: 0 Treffer außerhalb). (2) 'Hochsaison' und 'Hauptsaison' kommen in beiden Dateien nicht vor. (3) partner-portal.php:733 sagt 'Jetzt Partner werden'. Kein Handlungsbedarf; reiner Prüf-Befund.
- **Verifizierer-Notiz:** Hinweis am Rande: Die Flow-Tests dieses Audits haben lokale Draft-Partner (post_status Entwurf) in der Dev-DB angelegt, u. a. Token 873f7f53…; bei Bedarf löschen.
- **Fix:** Keiner nötig.

### Namensfeld auf der basics-Slide ohne Label (nur Placeholder)
- **Dimension/Wizard:** a11y-sec · course / basics  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2233
- **Problem:** Selbst verifiziert: Z.2233 übergibt '' als drittes Argument (Label) an fge_onboarding_input(), und Z.1837 rendert das <label> nur bei $label !== ''. Beschriftung ist damit ausschließlich der Placeholder (course: 'z. B. GC Augusta National', indoor: 'z. B. Ruff Golf München oder Eisen 7 Indoor Golf'); die H1 (Z.2228, 'Wie heißt euer Golfplatz?' bzw. 'Wie heißt euer Indoor Golf?') ist nicht programmatisch verknüpft. Betrifft course UND indoor (beide teilen die basics-Slide), wizard-Feld daher eher 'all' als 'course'.
- **Verifizierer-Notiz:** Korrektur: betrifft course + indoor, nicht nur course.
- **Fix:** Am einfachsten aria-label übers $attrs-Argument mitgeben ('aria-label="Öffentlicher Anzeigename"') oder der Step-H1 in fge_onboarding_render_step_header() eine id geben und das Input per aria-labelledby anbinden; ein sichtbares Label 'Öffentlicher Anzeigename' wäre die sauberste Variante.

### Billing-Entfernung: Manifeste sauber, aber Coach-Intro verspricht noch Abrechnung (plus toter IBAN-Code)
- **Dimension/Wizard:** a11y-sec · coach / intro-1  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :2149
- **Problem:** Selbst verifiziert: Alle drei Manifeste (Z.66-130) enthalten keine billing-Slide, die Regression-Prüfung fällt positiv aus. Aber die coach:intro-1-Kapitelliste (Z.2149) nennt weiterhin 'Abrechnung, Fotos &amp; Einreichung', obwohl der Coach-Flow (Z.96-110) keinen Abrechnungs-Schritt hat, sichtbare Copy widerspricht dem Flow. (Der Satz im Lead 'wir kümmern uns um Anfragen und Abrechnung', Z.2148, ist dagegen inhaltlich korrekt, Firmengolf wickelt ab.) Toter Billing-Code bestätigt: save-Case Z.619-639 (inkl. _fge_billing_iban Z.634), Validierung Z.1132ff., Dispatcher Z.1432, Renderer fge_onboarding_render_billing Z.3524, alle über das Manifest unerreichbar.
- **Verifizierer-Notiz:** Alle Codestellen exakt bestätigt.
- **Fix:** Listenpunkt in Z.2149 auf 'Fotos &amp; Einreichung' kürzen. Die vier toten billing-Stellen (Z.619-639, Z.1132ff., Z.1432, Z.3524ff.) entfernen; die Portal-Seite nutzt eigene Billing-Felder, ein Grep auf fge_onboarding_render_billing vor dem Löschen genügt.

### Fortschrittsanzeige für Screenreader stumm
- **Dimension/Wizard:** a11y-sec · all / alle (Footer)  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1698
- **Problem:** Selbst verifiziert: Z.1698 <div class="ob-prog" aria-label="Fortschritt"> ohne role, Z.1701 .ob-prog-fill nur mit style=width:x%, keine aria-value*-Attribute. Die Kapitel-Labels (Z.1702) sind als Text zwar lesbar, aber der Füllstand und der aktive Zustand (Klassen done/on) sind rein visuell. aria-label auf einem generischen div wird von den meisten AT-Kombinationen ignoriert.
- **Verifizierer-Notiz:** Exakt bestätigt.
- **Fix:** Der .ob-prog role="progressbar" + aria-valuemin/max/now über den Gesamtfortschritt geben (die Ratio-Summe liegt in $segments bereits vor), oder simpler: einen visually-hidden <span> mit 'Kapitel X von Y: {label}' in .ob-prog-count rendern.

### Härtung: $attrs in fge_onboarding_input() ungeprüft ausgegeben
- **Dimension/Wizard:** a11y-sec · all / alle Form-Slides  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1846
- **Problem:** Selbst verifiziert: Z.1846 echo $attrs mit phpcs:ignore. Stichprobe der Aufrufer bestätigt ausschließlich statische Literale ('autocomplete="off"' Z.2261, 'maxlength="5"' Z.2335, 'inputmode="numeric" autocomplete="one-time-code" maxlength="6"' Z.2471), keine Variablen im $attrs-Argument gefunden. Kein aktuelles Risiko, reiner Footgun für künftige Aufrufer. Low korrekt.
- **Verifizierer-Notiz:** Verifiziert, dass aktuell kein Aufrufer dynamische Werte übergibt.
- **Fix:** Signatur auf array $attrs = [] umstellen und je Paar sprintf(' %s="%s"', esc_attr($k), esc_attr($v)) rendern; die rund 15 Aufrufstellen mechanisch mitziehen. Alternativ minimal-invasiv: am Funktionskopf ein assert/wp_kses-Check, der nur [a-z-]+="[^"<>]*"-Muster durchlässt.

### Token-Modell geprüft: kein IDOR gefunden; Restrisiko nur Token-in-URL (bewusste Designentscheidung)
- **Dimension/Wizard:** a11y-sec · all / main / Token-Flow  (bestätigt)
- **Ort:** `/home/julius/projects/firmengolf-events/wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :347
- **Problem:** Alle Teilaussagen selbst nachvollzogen: (1) Token = bin2hex(random_bytes(16)) (Z.347), Lookup über strikte Meta-Gleichheit (fge_onboarding_get_partner_id_by_token, Z.286-298), fremder/erfundener Token → partner_id 0 → Redirect ohne Schreibzugriff (Handler Z.906-910). (2) Nonce-Pflicht behaviorally re-getestet: POST step_submit ohne Nonce gegen den Container → HTTP 403 (wp_die Z.876-878). (3) Token-Entwertung beim Submit bestätigt (delete_post_meta '_fge_onboarding_token' mit erklärendem Kommentar zu History/Referrer/Logs). (4) Account-Übernahme-Guard bestätigt: registrierte E-Mail → return -1 bzw. Login-Redirect (Z.796-799, Z.951-956). (5) Code-Härte bestätigt in email-verification.php: wp_hash-Speicherung, hash_equals (Z.105), max. 5 Versuche (Z.101), 15-min-TTL, 60s-Cooldown. Restrisiko Token-in-URL korrekt als Designentscheidung eingeordnet, Positivbefund mit Doku-Charakter, low passt.
- **Verifizierer-Notiz:** Zeile präzisiert: Token-Erzeugung liegt bei Z.347, nicht Z.180.
- **Fix:** Optional: header('Referrer-Policy: no-referrer') im Onboarding-Template setzen und den Token nach der Account-Anlage auf der main-Slide rotieren (neuer random_bytes-Wert, Redirect auf die neue URL); sonst Status quo dokumentieren.

### Billing/IBAN: Regression bestanden (keine Slide mehr erreichbar), aber der komplette Slide-Korpus liegt als toter Code herum
- **Dimension/Wizard:** gaps · all / billing  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :619
- **Problem:** Beide Teile bestaetigt. Regression OK: keines der drei Manifeste (66-130) enthaelt 'billing', 'coach-authority', 'coach-venue' oder 'coach-includes'; im Flow-Test tauchte keine IBAN-Abfrage auf. Toter Code existiert wie gemeldet: Save-Case 'billing' (619-640), Validate-Case mit IBAN-Pflicht (1132-1173), Renderer fge_onboarding_render_billing (3524-3566), Dispatch (1432), billing_*-Loader-Keys (1476-1486), fge_is_valid_iban (helpers.php 95, einziger Aufrufer ist der tote Validate-Case; fge_normalize_iban haengt mit dran). Ebenso coach-authority: Save 739-745, Validate 1250-1261, Render 3211-3240, Dispatch 1425. Severity von medium auf low korrigiert: rein toter Code ohne jede Nutzerwirkung, die eigentliche Regressionspruefung ist bestanden.
- **Fix:** Cases, Renderer, Dispatch-Eintraege, Loader-Keys sowie fge_is_valid_iban/fge_normalize_iban (falls nicht anderweitig geplant) in einem Aufraeum-Commit entfernen; Bestands-Metas per WP-CLI-Migration abraeumen.

### Verwaiste Coach-Slides: coach-venue-Renderer mit gestrichenen Pflichtfeldern, coach-includes-Dispatch auf nicht existierende Funktion
- **Dimension/Wizard:** gaps · coach / coach-venue  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :3156
- **Problem:** Am Code belegt. fge_onboarding_render_coach_venue (3156-3209) ist unerreichbar (kein Manifest-Eintrag) und rendert coach_venue_role (3161, required), coach_venue_groups (3179, required), coach_venue_fees (3184, required) plus die mobile-Felder (3194-3205), waehrend der Save-Case (722-737, Kommentar 'Rolle, Fees, mobiles Angebot sind gestrichen') nur name/venue_use/more_venues/gastro_involve persistiert und der Validate-Case (1245-1248) nur den Namen prueft. Der Save-Case selbst ist LEBENDIG: location-Save (512-513) und Portal-Sektion standorte (partner-portal.php 473-474) rufen ihn. fge_onboarding_render_coach_includes existiert nirgends, nur der Dispatch-Aufruf (1428); heute unerreichbar, aber ein garantierter Fatal, falls die Slide je wieder ins Manifest kommt. Severity von medium auf low korrigiert: aktuell kein Nutzerpfad betroffen, reine Landmine plus toter Code.
- **Fix:** Renderer coach-venue loeschen oder auf die real persistierten Felder eindampfen (Save-Case behalten, er traegt location + Portal-standorte); Dispatch-Eintraege coach-venue/coach-authority/coach-includes/billing (1424/1425/1428/1432) entfernen; zugehoerige Loader-Keys mit ausmisten.

### Values-Loader schleppt rund 40 tote Meta-Keys aus entfernten Slides mit
- **Dimension/Wizard:** gaps · all / review  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1521
- **Problem:** Kern bestaetigt, zwei Korrekturen. Per Grep verifiziert tot (nur im Loader, kein Renderer/Save/Review): indoor_peak_from/to, indoor_closures, indoor_shortnotice, indoor_hold_slots, indoor_nearby, indoor_radius_km, indoor_start_earliest/latest, indoor_min_hours, indoor_floor, indoor_elevator (1521-1537), participants_min/max_general und die *_capacity/parking_count-Reihe (1487-1496), gastro/golf_school/billing_contact_* (1467-1475), vat_required/billing_method_internal/bank_details_available (1504-1506) sowie die billing_*- und Coach-Reste aus den Funden 7/8. Korrektur 1: event_contact_* ist NICHT tot, es lebt im Portal-Kontaktformular (partner-portal.php 526-528, 3729-3731) und in admin-columns.php; internal_billing_note lebt in partner-fields.php (wie vom Auditor selbst angemerkt). Korrektur 2: Die Performance-Begruendung ist uebertrieben; get_post_meta bedient sich nach dem ersten Zugriff aus dem Meta-Cache, es entstehen keine zusaetzlichen DB-Queries, nur Ballast und Unlesbarkeit. Deshalb low statt medium.
- **Fix:** fge_onboarding_get_saved_vals auf die von aktiven Slides/Reviews genutzten Keys reduzieren; event_contact_* und internal_billing_note dabei NICHT als tot behandeln (Nutzer ausserhalb des Onboardings).

### Portal-Hinweise der Coach-Standorte-Sektion beschreiben entfernte Features (mobiles Angebot, Partnerplatz-Auswahl)
- **Dimension/Wizard:** gaps · coach / location  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/partner-portal.php` :3410
- **Problem:** Am Code belegt. Intro der Sektion: 'Dein Hauptstandort und dein mobiles Angebot' (3410); Hilfe-Bullets: 'Waehlst du einen Firmengolf-Partnerplatz, uebernehmen wir Stadt und Karten-Pin automatisch' und 'Das mobile Angebot erscheint auf deiner Visitenkarte (Kommt auch zu euch)' (3420). Das tatsaechliche standorte-Formular (3525-3586) enthaelt weder ein Partnerplatz-Dropdown noch Mobil-Felder (nur Name, Adresse, venue_use, weitere Plaetze, Gastro-Einbindung), und der Save-Case coach-venue (onboarding.php 722-737) persistiert coach_mobile nicht mehr. Die Hints beschreiben Bedienelemente, die es auf der Seite nicht gibt.
- **Fix:** Intro (3410) auf 'Dein Hauptstandort, daraus entstehen Karte und Zuordnung deiner Events' kuerzen und die beiden Bullets (3420) durch Hinweise zum realen Umfang ersetzen (Adresse setzt den Karten-Pin; weitere Plaetze erscheinen auf der Visitenkarte).

### Unbeantwortete Ganzjaehrig-Frage setzt trotzdem oeffentlich sichtbares Saison-Label 'Ganzjaehrig'
- **Dimension/Wizard:** gaps · indoor / indoor-hours  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :692
- **Problem:** Am Code belegt. Die Radios fge_indoor_year_round haben keine Vorauswahl (2987-2998, checked nur bei '1'/'0'), die Frage ist nicht Pflicht (Validate 1211-1214 verlangt nur die Oeffnungszeiten-Notiz), und san_select liefert bei fehlender Antwort '' (461-464). Zeile 692: '0' === $year_round ? 'Saisonal, siehe Oeffnungszeiten' : 'Ganzjaehrig', d. h. auch '' ergibt 'Ganzjaehrig'. Das Review bleibt bei '' korrekt leer ('k. A.', 3917-3924), aber _fge_season wird u. a. auf der oeffentlichen Event-Seite angezeigt (single-firmengolf_event.php 141). Testpartner 926 traegt _fge_season = 'Ganzjaehrig'.
- **Fix:** In Zeile 692 dreiwertig speichern: bei '' das Label leer lassen ('' === $year_round ? '' : (...)), oder die Frage per Validate-Case zur Pflicht machen.

### Kleinkram-Leichen: hartkodiertes enctype fuer Step 11, nie gelesenes skippable-Flag, tote Helfer und Locals
- **Dimension/Wizard:** gaps · all / media  (bestätigt)
- **Ort:** `wordpress/wp-content/plugins/firmengolf-events/includes/onboarding.php` :1805
- **Problem:** Alle vier Punkte verifiziert. (1) fge_onboarding_form_open setzt multipart/form-data fuer $step === 11 (1805); die media-Slide liegt laut Manifesten auf Ordinal 16 (indoor), 12 (coach), 16 bzw. 17 (course mit indoor-detail), Step 11 ist im Course-Wizard die capacity-Slide; Uploads laufen ohnehin async per REST (Kommentar 776-778), also nur irrefuehrend, nicht schaedlich. (2) 'skippable' (73/103/119) wird per Grep nirgends ausgewertet. (3) fge_onboarding_account_ordinal (168) und fge_onboarding_summary_section (4042) haben in Plugin und Theme keine Aufrufer. (4) $allowed_seasons/$allowed_billing (452-453) werden in keinem Save-Case benutzt (einzige Treffer im File sind die Definitionen).
- **Fix:** enctype-Sonderfall in fge_onboarding_form_open entfernen, skippable-Flags aus den Manifesten streichen (oder endlich auswerten), die beiden Helfer und die zwei Locals loeschen.
