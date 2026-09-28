# Partner-Simulation + Daten-Verwendungs-Audit (02.09.2026, Stand 1.9.205)

Methode: Drei echte Personas haben den kompletten Prozess durchlaufen (Browser-Simulation, alle Slides ausgefüllt, eingereicht, ins Portal eingeloggt, erstes Angebot erstellt). Parallel haben drei Prüfläufe JEDES im Wizard erfasste Feld gegen seine tatsächlichen Lesestellen geprüft (öffentliche Seiten, Portal, Matching, Mails, Admin).

Personas (lokal, Passwort `DesignTest123!`):
- **Petra Sommer**, Golfclub Ammersee Höhe (course, Partner 1000, `petra.sommer-test@example.org`)
- **Jonas Berger**, Golflehrer am selben Platz (coach, Partner 1001, `jonas.berger-test@example.org`)
- **Sarah Klein**, Birdie Base München (indoor, Partner 999, `sarah.klein-test@example.org`, Angebot 1002)

## Was funktioniert (verifiziert)

- Alle drei Wizards laufen ohne Hänger durch, Validierungen greifen korrekt (fehlendes Pflichtfeld → sauberer Fehler-Redirect).
- E-Mail-Bestätigung, Konto-Anlage, Einreichung, „In Prüfung"-Status: sauber.
- **Coach↔Platz-Verknüpfung End-to-End bestätigt:** Jonas tippt seinen Heimatplatz, die Vorschlagskarte „Diesen Platz kennen wir schon: Golfclub Ammersee Höhe" erscheint im Portal mit Verknüpfen-Button. Clubmanagement-Einladung sichtbar.
- **Brutto-Preiskette korrekt:** Sarah gibt 89 € brutto ein → 74,79 € netto gespeichert → Karte zeigt „AB 94 €" Kundenpreis.
- **Weihnachtsfeier-Kachel** steht bei Petra an Position 2 nach Teamevent.
- Coach-Confirmation korrekt typisiert („Dein Profil ist bei uns").

## Befunde aus der Simulation (nach Schwere)

### S1 · Indoor-Erfolgsseite ist golfplatz-fest (KRITISCH, Wording)
Nach Sarahs Indoor-Einreichung: Titel „Geschafft! Dein **Golfplatz** ist bei uns", Text „geben deinen **Platz** frei", Empfehlung + CTA „Erstes **Teamevent** erstellen", Hintergrund = Outdoor-Landschaftsbild. Die Typ-Verzweigung der Confirmation greift nicht (vermutlich kennt `fge_onboarding_current_type()` nach dem Submit-Redirect den Typ nicht mehr → Fallback course).

### S2 · Indoor-Partner findet seine Kernkategorie nicht (KRITISCH, UX)
Im Angebote-Grid fehlt für Sarah die leere „Indoor Golf"-Kachel (`fge_portal_hidden_empty_types()` versteckt sie global — die Regel stammt aus der Golfplatz-Welt). Ein Indoor-Partner sieht Teamevent/Platzreife/Firmen-Golfturnier-Kacheln, aber nicht Indoor Golf. Zusätzlich Wording im Intro: „Mach deinen **Golfplatz** zur Event-Location", „Angebote der anderen **Plätze**", Embed-Box „Sobald dein **Platz** öffentlich ist".

### S3 · Coach-Profil-Bearbeitung heißt „Platz" (Wording)
Im Golflehrer-Edit: „← Zurück zum **Platz**" + Eyebrow „**PLATZ** BEARBEITEN". Muss beim Coach „Profil", beim Indoor „Anlage" heißen.

### S4 · Indoor-Angebotskarte mit Golfplatz-Platzhalterbild
Sarahs Angebot ohne eigenes Foto bekommt ein Luftbild-Grün als Fallback. Für Indoor ein Indoor-Platzhalter.

## Befunde aus dem Daten-Audit (was erfasst, aber nie verwendet wird)

### Strukturelle Lücken (größter Hebel)

| # | Lücke | Wirkung |
|---|---|---|
| D1 | **Keine öffentliche Indoor-Partnerseite.** `single-firmengolf_partner.php` forkt nur für coach; Indoor-Partner rendern als „Golfplatz auf Firmengolf" mit leeren Golfplatz-Facts. Technik, Räume, Gastro, Öffnungszeiten bleiben unsichtbar. | Alle Indoor-Spezialdaten enden im Portal |
| D2 | **Indoor ohne Kapazitäts-Matching.** Matching filtert hart über `_fge_cap[min/max]`; Indoor schreibt nur `_fge_indoor_sim[max_persons]`. Mapping beim Save fehlt. | Indoor-Partner fallen aus der Kapazitätslogik |
| D3 | **Indoor-Saison läuft leer.** Saison-Scoring + Offseason-Badge lesen `_fge_season_from/to`; der Indoor-Wizard schreibt stattdessen `_fge_indoor_open_from/to` (nie gelesen). | Ganzjahres-Vorteil von Indoor unsichtbar |
| D4 | **Coach: `_fge_event_formats`-Pfad gekappt.** Slide sendet das Feld seit 02.09. nicht mehr, Portal-Edit fehlt → Format-Matching-Bonus (+2) feuert für Coaches nie; `_fge_coach_formats` wird nicht gemappt. | Coaches im Matching benachteiligt |
| D5 | **Verfügbarkeit nach Onboarding unveränderbar** (alle Typen): `_fge_preferred_event_days`, `_fge_season_*`, `_fge_min_lead_time_days`, `_fge_evening_events_possible` haben kein Portal-Formular, steuern aber Matching hart. Coach kann zusätzlich Formate + Gruppengrößen nie ändern. | Partner können matching-relevante Daten nicht pflegen |
| D6 | **Kaputter Review-Edit-Link (course):** Block „Golfplatz" verlinkt auf die gestrichene Slide `golftype` → führt auf `?ob_step=0`. | Toter Link im Review |
| D7 | **Admin sieht Coach/Indoor-Daten nicht:** `partner-fields.php` hat 0 Coach- und 0 Indoor-Felder; Einreichungs-Mail transportiert keine typspezifischen Angaben. | Prüfung läuft blind übers Frontend |

### Tote Felder (erfasst, nirgends gelesen)

**Indoor:** komplette Räume-Slide (`_fge_indoor_spaces`, `_spaces_custom`, `_area`), Öffnungszeiten-Raster (`_fge_indoor_hours`, `_open_note`, `_year_round`, `_after_hours`) — einziger Payoff der Slide sind die abgeleiteten Event-Tage; `_fge_indoor_formats` (Kacheln erzeugen nichts); `_fge_indoor_kind` (Pflicht-Erstfrage, reine UI-Weiche); `_fge_indoor_sim[features]` + `[box_max]` (Pflichtfelder!, fehlen in der Technik-Zusammenfassung); `_fge_indoor_entrance_note`.

**Coach:** `_fge_coach_health_cert` (Gesundheits-Zertifizierung — extra gebaut, wird nie angezeigt!), `_fge_coach_school`, `_fge_coach_cap[trainers/per_trainer/rental_persons]` (versprochene Rechnung „Lehrer × Richtwert" existiert nicht), `_fge_coach_gastro_involve`; dazu Code-Leichen: `coach-authority`-Slide (5 Keys, unerreichbar), `coach-includes` (nie gespeichert).

**Course:** `_fge_google_place_id` (Schlüssel zu Google-Bewertungen — `_fge_rating` wird gelesen, aber nie befüllt!), `_fge_image_rights_note` (gehört in Freigabe-Ansicht/Mail), `_fge_individual_availability_check` (Konstante, streichen), `_fge_main_contact_role` (nur intern).

**Gegenlücke:** `_fge_poi_hotel` wird auf der Event-Seite gelesen, aber nirgends abgefragt.

## Empfohlene Pakete (Reihenfolge)

1. **Sofort (Wording+UX, klein):** S1 Confirmation-Typ-Fix, S2 Indoor-Golf-Kachel + Wording, S3 „Zurück zum Profil", D6 Review-Link. 
2. **Matching-Paket (mittel):** D2 cap-Mapping Indoor, D3 Saison-Mapping Indoor, D4 coach_formats→event_formats-Mapping.
3. **Anzeige-Paket (mittel):** Tote Pflichtfelder sichtbar machen (features/box_max/Räume/Öffnungszeiten in Technik-View; health_cert + Leihschläger-Fact auf Coach-Visitenkarte; indoor_kind als Anlagentyp).
4. **Portal-Pflege (größer):** Verfügbarkeits-Sektion für alle Typen, Coach-Formate/Gruppen editierbar (D5).
5. **Öffentliche Indoor-Seite (eigenes Projekt):** D1, zusammen mit Simulator-Landingpage-Strategie.
6. **Aufräumen:** Code-Leichen (coach-authority, coach-includes), Konstanten-Felder streichen.
