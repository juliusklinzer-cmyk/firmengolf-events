# Analyse: Naboo Budget-Rechner (de.ext.naboo.app/tools)

Stand 07.09.2026. Quelle: Live-Seite headless durchgespielt (Gate mit julius@visionpunch.de freigeschaltet, ein Lead ist dabei bei Naboo gelandet) und der ausgelieferte JavaScript-Chunk `assets/Tools-ChMCA3lw.js`, in dem die komplette Preislogik im Klartext steht. Alles unten ist damit belegt, nichts geschätzt.

## 1. Die Tools-Seite

Drei Werkzeuge auf einer Seite, alle als Lead-Magnet gebaut:

| Tool | Zweck | Gate |
|---|---|---|
| Budget-Rechner | Richtwert in 4 Posten | geschäftliche E-Mail |
| Guide 2026 | PDF-Download (Trends, Locations, Teambuilding) | Formular |
| CO₂-Rechner | Emissionen je Transportmittel, Mahlzeiten, Unterkunft | keins, aber Ergebnis ebenfalls geblurrt bis „Berechnen" |

Dazu HubSpot-Chat („Caroline"), Mixpanel, PostHog, Bing, LinkedIn, Meta, Google Ads. Consent über Axeptio.

## 2. Aufbau des Budget-Rechners

Eine einzige Eingabezeile als Leiste, darunter die Ergebniskarte.

**Eingaben (Leiste, links nach rechts)**

1. Teilnehmende: Zahlfeld mit Minus/Plus in 5er-Schritten, Minimum 5, kein Maximum (Anzeige kappt bei „300+").
2. Tage: Zahlfeld 1 bis 30.
3. Veranstaltungstyp: Select mit 7 Einträgen (Tagesveranstaltung, Firmenevent, Tagung mit Übernachtung, Firmenretreat/Offsite, Teambuilding, Konferenz/Tagung, Workshop/Schulung).
4. Preisspanne: Segment € / €€ / €€€ (intern budget, standard, premium).
5. Gewünschte Services: drei Toggles Catering, Aktivitäten, Transport, alle vorausgewählt.
6. Button „Berechnen", grün, rechts angedockt.

**Ergebniskarte**

- Links „Kostenaufschlüsselung": maximal 4 Zeilen (Veranstaltungsort, Catering, Transport, Aktivitäten), Farbpunkt + Betrag, Zeilen mit 0 € verschwinden.
- Mitte Donut (Recharts, innerRadius 50, outerRadius 80, Animation 800 ms, Tooltip je Segment).
- Rechts „Gesamtbudget" groß, darunter „Für 100 Personen / 2 Tage", Button „Jetzt kostenfrei anfragen".

**Wichtig:** Die Ergebniskarte hat die Klassen `hidden md:block`. Unter 768 px Breite existiert sie nicht. Auf dem Handy tut „Berechnen" nichts Sichtbares. Das ist ein echter Fehler bei Naboo, kein Feature.

## 3. Gate-Mechanik (das, was Julius will)

Ablauf im Code (Zustände `c` = Berechnen gedrückt, `p` = freigeschaltet):

1. Beim Laden ist die Ergebniskarte bereits mit echten Zahlen gefüllt, aber mit `blur(6px)` belegt. Die Zahlen ändern sich live bei jeder Eingabe, auch hinter dem Blur.
2. „Berechnen" rechnet nichts. Es legt nur ein Overlay über die Karte: weiß 70 % plus Backdrop-Blur, darin eine Karte mit Mail-Icon, Satz „Geben Sie Ihre geschäftliche E-Mail-Adresse ein, um Ihr personalisiertes Budget zu sehen", ein Mail-Feld, Button „Budget anzeigen".
3. Prüfung: Regex auf Mail-Form plus Sperrliste mit rund 100 Freemail-Domains (gmail, gmx, web.de, t-online, outlook, icloud, posteo, ionos …). Freemail ergibt Toast „Bitte verwenden Sie eine geschäftliche E-Mail-Adresse".
4. Bei gültiger Mail geht eine Slack-Nachricht über einen Vercel-Proxy (`simulateurtransport.vercel.app/api/sendSlackMessage`) mit Mail, Markt, Teilnehmer, Tage, Typ, Qualität, Services, Budget, Aufschlüsselung, UTM-Parametern und Landing-URL. HubSpot Collected Forms greift die Mail zusätzlich automatisch ab. Es gibt keine Bestätigungsmail an den Nutzer und keinen eigenen Server für die Preise.
5. Freischaltung: Overlay verschwindet, Blur geht mit `transition 500 ms` und 500 ms Verzögerung weg, Toast „Budget erfolgreich berechnet!". Danach rechnet jede Eingabe live weiter, ohne erneutes Gate. Die Freischaltung wird nicht gespeichert, nach Reload ist das Gate wieder da.
6. „Jetzt kostenfrei anfragen": vor der Freischaltung öffnet es nur das Gate, danach das Lead-Formular (Eventtyp mit 11 Optionen, Datum, Teilnehmer, Budget, Mail, Telefon, „Rückruf heute", danach KI-Vorschläge in 20 bis 40 Sekunden und Kalenderbuchung). Events an Mixpanel: `form_trigger_clicked`, `cta_budget_request_clicked`.

Das Gate ist rein kosmetisch: die Beträge stehen auch geblurrt im DOM.

## 4. Preislogik, exakt aus dem Code

Alle Beträge in Euro pro Person. Qualität = Preisspanne.

**Veranstaltungsort (Posten 1)**

| Typ | budget | standard | premium | Formel |
|---|---|---|---|---|
| Tagesveranstaltung | 75 | 95 | 125 | × Personen × Tage |
| Firmenevent | 130 | 160 | 200 | × Personen × Tage |
| Tagung mit Übernachtung | 150 | 185 | 230 | × Personen × max(Tage − 1, 1) |
| Firmenretreat/Offsite | 150 | 185 | 230 | × Personen × Tage |
| Teambuilding, Konferenz/Tagung, Workshop/Schulung | 1 Tag: 75 / 95 / 125, mehrtägig: 150 / 185 / 230 | | | 1 Tag: × Personen, sonst × Personen × (Tage − 1) |

**Services**

| Service | budget | standard | premium | Formel |
|---|---|---|---|---|
| Catering | 35 | 55 | 85 | × Personen × Tage |
| Aktivitäten | 30 | 50 | 80 | × Personen × Tage |
| Transport | 25 | 35 | 50 | × Personen, einmalig |

Rundung nur auf ganze Euro. Keine Fixkosten, keine Mengenstaffel, kein Standort, keine Saison.

Kontrollrechnung Startzustand (100 Personen, 2 Tage, Tagesveranstaltung, standard, alle Services): Ort 95 × 100 × 2 = 19.000, Catering 55 × 100 × 2 = 11.000, Transport 35 × 100 = 3.500, Aktivitäten 50 × 100 × 2 = 10.000, Summe 43.500. Stimmt mit der Seite überein.

**Was daran auffällt**

- Rein linear pro Person. 5 Personen kosten pro Kopf dasselbe wie 500. Das ist als Lead-Magnet bewusst simpel, als Schätzung für kleine Gruppen unrealistisch.
- Die Preisspannen-Faktoren sind je Posten verschieden: Ort etwa 0,79 / 1,0 / 1,32, Catering 0,64 / 1,0 / 1,55, Aktivitäten 0,6 / 1,0 / 1,6. Premium schlägt bei Services stärker durch als beim Ort.
- „Tagung mit Übernachtung" rechnet Nächte (Tage − 1), Retreat rechnet Tage. Bei 1 Tag kostet die Tagung trotzdem eine Nacht.
- Pro Kopf und Tag landet ein Standard-Tagesevent mit allem bei 217,50 € (2 Tage) bzw. 235 € (1 Tag). Das ist die Erwartung, die Naboo bei Firmenkunden setzt.

## 5. Vergleich mit unserem Rechner (individuelle-events, `budget-calc.php` + `fge-individual.js`)

| Merkmal | Naboo | Firmengolf heute |
|---|---|---|
| Eingaben | Personen, Tage, Typ (7), Spanne (3), 3 Services | Personen 6 bis 250 in 2er-Schritten, Typ (9), Niveau (3), typabhängige Service-Chips aus 27 Leistungen |
| Preismodell | 4 Posten, linear pro Person | je Leistung €/Person oder Pauschale, Faktoren 0,82 / 1 / 1,45, Rundung 50 |
| Tage | ja | nein |
| Berechnung | live, aber geblurrt bis Mail | live, sofort sichtbar |
| Gate | E-Mail geschäftlich, Slack + HubSpot | keins |
| Aufschlüsselung | 4 Sammelposten | eine Zeile je Leistung, eigene Farbe |
| Donut | Recharts, animiert 800 ms | eigenes SVG, ohne Animation |
| Animation bei Änderung | Donut-Segmente animieren, Zahlen springen | nichts animiert |
| Mobil | Ergebnis nicht vorhanden | Ergebnis vorhanden, Donut oben |
| CTA | Lead-Formular mit KI-Vorschlägen | Wizard vorbefüllt (Anlass, Größe, Leistungen, Pro-Kopf-Budget, Notiz) |
| Nettohinweis | keiner | „netto, zzgl. 19 % MwSt." |

Preisniveau im Vergleich: Für ein Teamevent mit 30 Personen zeigt unser Rechner im Standard nur den Schnupperkurs mit 99 € pro Kopf, weil sonst nichts vorausgewählt ist. Naboo zeigt für eine Tagesveranstaltung sofort 235 € pro Kopf. Wer beide Tools nacheinander benutzt, hält uns für die halbe Leistung. Vorschlag: Catering oder Getränke beim Teamevent vorauswählen, dann liegt der Richtwert bei rund 190 € pro Kopf und wirkt komplett.

## 6. Was wir übernehmen sollten und wie

**A. Gate mit Blur, das bei E-Mail aufgeht**

- Ergebnisbereich (Aufschlüsselung, Donut, Gesamtbudget) von Anfang an geblurrt, Eingaben bleiben frei bedienbar, Zahlen laufen dahinter live mit. Genau wie Naboo, das erzeugt den Reiz.
- Statt Overlay nach Klick: ein kleines Mail-Feld direkt in der Gesamtbudget-Spalte, „Richtwert freischalten", Button „Anzeigen". Weniger Schritte als bei Naboo, kein Extra-Button „Berechnen" (unser Rechner rechnet ohnehin sofort).
- Bei gültiger Mail: Blur weg mit 500 ms Ease-out, Zahl zählt hoch, Freischaltung 30 Tage im localStorage, damit Wiederkehrer nicht erneut gefragt werden.
- Freemail nicht sperren. Unsere Zielkunden sind auch Mittelständler mit gmx-Adressen. Sperre nur als Hinweis „geschäftliche Adresse hilft uns beim Angebot".
- Lead speichern: eigene Zeile in der Anfragen-Tabelle mit Quelle `budget_gate` (Mail, Typ, Personen, Niveau, Services, Richtwert), interne Mail an uns wie bei den Kurz-Anfragen, Bot-Fallen `fge_form_trap_fields()` pflicht. Keine Bestätigungsmail an den Nutzer, aber die Mail im Wizard vorbefüllen, wenn er danach anfragt.
- Datenschutz: ein Satz unter dem Feld mit Link auf die Datenschutzerklärung, kein Newsletter-Opt-in, Zweck ist Kontakt zur Anfrage.

**B. Animationen bei Anpassungen**

- Gesamtbudget zählt von alt nach neu (Tween 400 ms, Ease-out), kein Sprung.
- Donut-Segmente: CSS-Transition auf `stroke-dasharray` und `stroke-dashoffset`, 500 ms Ease-out. Unsere Segmente haben je Typ eine feste Reihenfolge, damit funktioniert die Transition ohne Flackern.
- Neue Zeile in der Aufschlüsselung gleitet ein (Opacity plus 6 px nach oben, 220 ms), entfernte Zeile blendet aus. Betrag einer geänderten Zeile blitzt kurz grün hinterlegt auf (300 ms).
- Chip beim Antippen `scale(0.97)`, 120 ms. Gilt für alle Chips im Design-System (DESIGN.md), nicht nur hier.
- Nichts davon läuft auf `reduce-motion`.

**C. Tage-Dimension**

Nur für Offsite, Platzreife (mehrtägig) und Turnier-Serie sinnvoll. Vorschlag: Feld „Tage" nur bei diesen Typen einblenden, Übernachtung und Verpflegung multiplizieren mit Tagen, Golf-Leistungen nicht. Kann in Schritt 2, nicht nötig für A und B.

**D. Nicht übernehmen**

- 4 Sammelposten. Unsere Zeile je Leistung ist transparenter und passt zu „ein Angebot, alle Posten".
- Slack als Lead-Kanal. Wir haben Anfragen-Tabelle, Portal und Mail.
- Ergebnis nur auf Desktop.

## 7. Aufwand, wenn das Go kommt

| Paket | Inhalt | Aufwand |
|---|---|---|
| A Gate | Blur, Mail-Feld, Freischaltung, localStorage, Lead-Speicherung, interne Mail, Bot-Fallen, Wizard-Vorbefüllung | halber Tag |
| B Animationen | Zahl-Tween, Donut-Transition, Zeilen-Ein/Ausblenden, Chip-Press | 2 bis 3 Stunden |
| C Tage | Feld, Multiplikation, Admin-Flag je Leistung „pro Tag" | 2 bis 3 Stunden |
| Vorauswahl-Politur | default_on je Typ ergänzen (Catering oder Getränke), damit der Richtwert komplett wirkt | 30 Minuten plus Julius' Preisentscheidung |

Offene Entscheidung für Julius: Blur von Anfang an (Naboo-Stil, erzeugt Neugier) oder Zahlen erst nach Klick auf „Berechnen" blurren? Empfehlung: von Anfang an, aber ohne Extra-Button.
