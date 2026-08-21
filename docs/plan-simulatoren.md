# Plan: Golfsimulatoren für die Wintersaison anbinden

Stand 21.08.2026, ausgearbeitet zur gemeinsamen Umsetzung. Ziel: Julius kontaktiert alle Golfsimulator-Betreiber in Deutschland und gibt ihnen die Möglichkeit, sich wie Golfplätze anzumelden. Simulatoren tragen die Offseason (Oktober bis März) und Schlechtwettertage.

## 1. Kernentscheidung: kein neuer Datentyp, eine eigene Spur

Das Datenmodell kann Simulatoren heute schon fast vollständig abbilden:

- `firmengolf_partner` hat das Feld `_fge_golf_type`, und der Katalog (`fge_catalog_golf_types()` in `includes/catalogs.php`) kennt bereits **`indoor-sim` (Indoor-Simulator)**.
- Die Infrastruktur- und Kapazitätskataloge kennen bereits `indoor` („Indoor Simulator", „Kapazität Indoor Simulator").
- Events, Anfragen, Angebote, Mails, Portal, Freigabe-Workflow: alles hängt am Partner-CPT und funktioniert für Simulatoren unverändert mit.

**Vorschlag:** Simulator-Betreiber werden normale `firmengolf_partner` mit `_fge_golf_type = indoor-sim` plus einem Spur-Flag `_fge_partner_track = simulator`. Kein neuer Post-Type, keine parallele Infrastruktur. Alles, was „einfacher" sein soll, wird über dieses Flag gesteuert (kürzeres Onboarding, reduzierte Event-Typen, angepasste Labels).

Neue Simulator-spezifische Meta-Felder (wenige, optional):

| Feld | Inhalt |
|---|---|
| `_fge_sim_technik` | Simulator-System (Trackman, Golfzon, Foresight, aboutGolf, Sonstiges) |
| `_fge_sim_bays` | Anzahl Boxen/Abschlagplätze |
| `_fge_sim_gastro` | Gastro vor Ort: keine / Getränke / Bar / Küche (vereinfachtes Pendant zum Gastro-Kapitel) |

Kapazität (Personen gleichzeitig) existiert schon über das `indoor`-Kapazitätsfeld.

## 2. Eigenes Anmeldeformular: verkürzte Onboarding-Spur

Das Onboarding (`includes/onboarding.php`) ist manifest-basiert: `fge_onboarding_manifest()` liefert 17 Slides, Render/Save dispatchen über Slide-IDs. Das ist ideal für eine zweite Spur:

- **Einstieg:** eigener Link, z. B. `/partner-onboarding/?spur=simulator`. Beim Anlegen des Partner-Entwurfs wird `_fge_partner_track = simulator` und `_fge_golf_type = indoor-sim` gesetzt.
- **`fge_onboarding_manifest()` bekommt einen Parameter** (liest das Spur-Flag des Partners) und liefert für Simulatoren ein kurzes Manifest, ca. 9 statt 17 Slides:

| # | Slide | Inhalt |
|---|---|---|
| 1 | intro-1 | Begrüßung, angepasster Text (Offseason-Pitch) |
| 2 | basics | Name, Kurzbeschreibung, Website (wie bisher) |
| 3 | location | Adresse + Karte (wie bisher, wiederverwendet) |
| 4 | sim-details | NEU: Technik, Anzahl Bays, Personen-Kapazität, Gastro-Kurzauswahl |
| 5 | formats | Reduzierte Format-Liste (siehe Abschnitt 3) |
| 6 | avail | Verfügbarkeit (wie bisher, Texte angepasst: Abendslots, Winter) |
| 7 | pricing | Vereinfacht: Preis pro Bay und Stunde oder pro Person |
| 8 | media | Fotos (wie bisher) |
| 9 | review | Zusammenfassung (gefiltert auf die Simulator-Slides) |

- Entfallen: golftype (fix gesetzt), arrival, contacts (optional lassen oder in basics integrieren), infra, gastro (Großkapitel), capacity (steckt in sim-details).
- Die bestehenden Slides basics/location/avail/media werden **wiederverwendet**, nicht dupliziert. Neu gebaut wird nur `sim-details` und die Manifest-Weiche.
- Spam-Schutz: `fge_form_trap_fields()` ist im Onboarding-Flow bereits vorhanden bzw. wird mit übernommen.

## 3. Reduzierte Event-Typen für Simulatoren

Keine Platzreife, kein Greenfee-Denken. Sinnvolle Auswahl im formats-Slide und später bei der Event-Anlage im Portal:

- Teamevent (Indoor)
- After-Work Golf (der natürliche Simulator-Klassiker, 29 bis 49 Euro Muster existiert schon)
- Firmen-Golfturnier (Simulator-Turnier: Nearest-to-pin, Longest Drive, virtuelle Plätze)
- Kundenevent
- Workshop
- Weihnachtsfeier bzw. Nacht-Event (Simulatoren sind abends und im Dezember am stärksten)

**Keine neuen Event-Typ-Slugs nötig.** Die bestehenden `_fge_event_type`-Werte bleiben, die Auswahl wird bei `track = simulator` nur gefiltert (eine Whitelist im formats-Slide und im Portal-Event-Formular). Vorteil: Simulator-Events erscheinen automatisch in den bestehenden Format-Filtern der Eventliste.

## 4. Öffentliche Platzierung auf der Website

Wo Simulator-Events für Firmen sichtbar werden, in absteigender Priorität:

1. **Eigene Landingpage „Indoor-Golf und Simulator"**, z. B. `/firmenevent/indoor-golf/` über die bestehende Format-Landing-Infrastruktur (`includes/format-landing.php`). SEO-Ziel: „Firmenevent Golfsimulator", „Indoor Golf Teamevent", „Weihnachtsfeier Golfsimulator". Aufbau wie die anderen Format-Seiten (Hero, Events, Story „Golf bei jedem Wetter", FAQ, Putt-CTA), Rollout im Herbst.
2. **Eventliste:** Simulator-Events laufen normal mit (Geo-Filter greift über die Partner-Koordinaten). Neu: ein **„Indoor"-Badge** auf der Event-Karte v2 (analog zum Offseason-Chip, gespeist aus `_fge_golf_type` des zugewiesenen Partners). Optional ein Indoor-Filter-Chip, wenn genug Events da sind.
3. **Startseite, saisonal:** ab Oktober eine Winter-Kachel in der Formate-Sektion („Indoor und Simulator") plus ein Satz im Hero-Umfeld („auch im Winter"). Als kleiner saisonaler Schalter, nicht als Umbau.
4. **Stadt-Landingpages:** Simulator-Partner erscheinen in der Golfplätze-Karte (gpx) mit eigenem Pin-Stil und Badge „Indoor". Die Datenquelle ist hier der Partner-CPT, nicht `wp_fge_golfplaetze` (dort stehen nur DGV-Plätze, Simulatoren werden dort NICHT eingetragen).
5. **Schlechtwetter-Verknüpfung:** Die bestehende Wetter-FAQ („Was passiert bei schlechtem Wetter?") verlinkt auf die Indoor-Seite. Der Blogartikel „Schlechtwetter beim Golfevent" bekommt einen Absatz plus Link.
6. Die Zahl **„über 500 Eventlocations"** deckt Simulatoren begrifflich bereits ab, keine Textänderung nötig.

## 5. Betreiber-Ansicht im Portal (vereinfacht)

Das Partner-Portal bleibt dasselbe, bei `track = simulator` wird gefiltert statt geforkt:

- Event-Anlage: reduzierte Typ-Auswahl (Whitelist aus Abschnitt 3), Platzreife-spezifische Felder und Greenfee-Wording ausgeblendet.
- Profil: Simulator-Felder (Technik, Bays) statt Platz-Feldern (Löcher, Par).
- Wording-Weiche an wenigen Stellen: „euer Platz" wird „euer Standort" bzw. „euer Studio".
- Freigabe-Workflow, Anfragen, Angebote, Digest-Mails: unverändert.

## 6. Einladung und Akquise-Unterstützung

- `includes/partner-invite.php` (System-Einladungsmail plus Nachfass existiert seit 1.9.90): eine **Simulator-Variante** der Einladungsmail mit eigenem Betreff und Text (Offseason-Argument: „Firmenkunden im Winter, ohne Vertriebsaufwand, kostenlos") und dem Spur-Link aufs Onboarding.
- Die Landingpage für die Akquise: `/simulator-partner/` analog zu `/golfplatz-partner/` (nicht in der Navigation). Kernbotschaften: kostenlos, Kunde zahlt Provision, ihr zahlt nie (Geschäftsmodell unverändert), Auslastung genau dann, wenn draußen nichts geht.
- Julius kontaktiert die Betreiber manuell; das Formular plus Einladungslink reicht, kein CSV-Import nötig.

## 7. Umsetzungsreihenfolge (Pakete)

| Paket | Inhalt | Abhängigkeit |
|---|---|---|
| A | Spur-Flag + Manifest-Weiche + sim-details-Slide + reduzierte Formats-Liste (Onboarding komplett durchspielbar) | keine |
| B | Akquise-Landingpage `/simulator-partner/` mit CTA auf die Onboarding-Spur | A |
| C | Portal-Anpassungen (Typ-Whitelist bei Event-Anlage, Profil-Felder, Wording-Weiche) | A |
| D | Öffentlich: Indoor-Badge auf Event-Karten, Format-Landingpage `/firmenevent/indoor-golf/`, gpx-Pin-Stil auf Stadtseiten | C (braucht erste Events) |
| E | Saison-Schalter Startseite + Simulator-Einladungsmail + FAQ/Blog-Verlinkung | B, D |

A und B reichen, damit Julius mit der Kontaktaufnahme starten kann. C bis E können nachziehen, während sich die ersten Betreiber anmelden.

## 8. Offene Entscheidungen (vor Umsetzung klären)

1. **Begriff nach außen:** „Indoor-Golf", „Golfsimulator" oder beides? (Betrifft URL der Format-Seite und Badge-Text. SEO-Bauchgefühl: „Golfsimulator" hat das klarere Suchvolumen bei Firmen, „Indoor-Golf" ist das schönere Dach.)
2. **Slug der Onboarding-Spur:** eigener Einstieg `/simulator-onboarding/` oder Parameter `/partner-onboarding/?spur=simulator`? (Empfehlung: Parameter, weniger Infrastruktur.)
3. **Provision:** gleiches Modell wie Golfplätze (Kunde zahlt Provision, Betreiber nie)? Wenn ja, im Universum-Repo dokumentieren.
4. **Weihnachtsfeiern:** Simulator-Events aktiv mit der ganzjährig buchbaren Weihnachtsfeier verzahnen (1.9.125)?
5. **Wann live:** Winter-Aktivierung der Startseiten-Kachel ab Oktober oder sofort bei den ersten Simulator-Events?
6. **Stadtseiten:** sollen Simulatoren dort von Anfang an in der Karte auftauchen oder erst ab einer Mindestzahl Partner?

Bei der Umsetzung außerdem: Entscheidung und Partner-Segment im Universum-Repo dokumentieren (Geschäftsmodell, Partner-Status) und nach dem ersten kundenrelevanten Release einen Eintrag in kunden-updates.md machen.
