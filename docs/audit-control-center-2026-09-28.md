# Audit Control Center, kompletter Prozess (28.09.2026, Stand 1.9.278 → Fixes 1.9.279)

Durchlauf über die echte Oberfläche (localhost:8080, eingeloggter Admin, MailHog fängt alle Mails), nicht per Skript-Aufruf der Funktionen. Zwei Testplätze (Nord, Süd) mit Hauptkontakt, ein Portal-Zugang für Nord, ein Partner-Event für Nord. Alle Testdaten danach gelöscht.

## Was gespielt wurde

| Szenario | Weg | Ergebnis |
|---|---|---|
| 1 Platzhalter-Anfrage, zwei Plätze | Eventseite → Preisanfrage an Nord + Süd per Mail → beide antworten (Nord: Kurs 33 € brutto, Abendessen 25 €, Getränkepauschale und Fotograf nicht) → Nord gewählt → Positionen (Abendessen Platz, Fotograf extern, Meetingraum extern ungefragt, Shuttle Platz ungefragt, Getränke nach Verbrauch) → Termin bestätigt → Angebot mit PDF → Rückfrage des Kunden → Gravur ergänzt, Fassung 2 → Annahme ohne Meetingraum → Eventtag-Daten, Ablauf-Info, Vortags-Info → Absage an Süd → Event durchgeführt (Bewertungsbitte) → Katalog-Frage an Nord → abgeschlossen | läuft durch, alle 20 Mails inhaltlich geprüft |
| 2 Abbrechen und neu aufsetzen | Anfrage 2 → Nord sagt ab, Süd sagt zu → Angebot → Kunde lehnt ab → neu aufgelegt (Fassung 2) → Annahme | läuft durch, zwei Funde (unten) |
| 3 Partner-Event, Terminabstimmung, Portal | Anfrage auf ein Event mit Platz → Termin-Link-Mail an Nina → Termin-Seite (anonym) → Portal als Nina (anonym) → Abstimmung → „Alle Rückmeldungen da" → Bestätigung im Portal → Angebot zurückgehalten (Meetingraum ohne Preis) → bepreist, gesendet → Annahme → Portal und Termin-Seite zeigen jetzt den Kunden | läuft durch |

Angebotsseite als Kunde in Desktop- und Handybreite gesichtet: Positionen, Beschreibungen, Organisation, Verbrauchs-Position, Hinweise und Summen sauber.

## Funde und Status

| # | Schwere | Fund | Status |
|---|---|---|---|
| 1 | hoch | Nach Kundenabsage stand die Anfrage weiter als „Angebot läuft" (Phase 4), auch „verloren" und „nicht verfügbar" hatten kein eigenes Label | behoben 1.9.279: Phasen „Abgelehnt", „Verloren", „Nicht verfügbar" (rote Pille, Filter in der Liste) |
| 2 | hoch | Nach Kundenabsage erfuhr der gewählte Platz nichts, der Termin blieb bei ihm blockiert | behoben: Panel „Kunde hat abgelehnt" mit „Platz informieren: Termin wird frei" (neue Mail `venue_release`), „neu auflegen", „als verloren ablegen" |
| 3 | hoch | Platzhalter-Events tragen eine generische Leistungsliste („Verpflegung im Clubhaus"). Ohne Eintrag in Schritt 2 ging sie ins Angebot und in die Auftragsbestätigung des Platzes | behoben: bei Platzhaltern kommt die Event-Liste nie ins Angebot, das Cockpit warnt, solange Leistungen oder Ablauf fehlen |
| 4 | mittel | Vortags-Info an den Platz listete externe Positionen (Fotograf, Gravur) unter „Gebucht und bestätigt", als müsste er sie liefern | behoben: Platz sieht nur seine Positionen, externe als Hinweis „zusätzlich über Firmengolf organisiert" |
| 5 | mittel | Sagt der Platz zu einem „über Firmengolf gewünschten" Posten (Fotograf) Nein, wurde er als „am Platz nicht möglich" markiert und so dem Kunden gezeigt | behoben: solche Posten werden externe Positionen ohne Preis, Julius bepreist |
| 6 | mittel | Angebotsmail ging ohne PDF raus, wenn der Cache-Ordner nicht beschreibbar ist, ohne jede Warnung (lokal durch einen root-Testlauf ausgelöst, live ist der Ordner in Ordnung) | behoben: interne Warnmail „Angebots-PDF fehlt" |
| 7 | niedrig | Fassung-2-Mail las sich wie das erste Angebot („der Termin steht") | behoben: „hier ist die neue Fassung, sie ersetzt die vorherige" |
| 8 | niedrig | Nach der Buchung boten die übrigen zugesagten Plätze weiter „Diesen Platz nehmen" an | behoben: Knopf nur vor der Buchung |
| 9 | niedrig | Buchungsbestätigung an den Platz und „Alle Rückmeldungen da" begannen mit „Hallo," ohne Namen | behoben: Vorname des Platz-Kontakts wie in den Pipeline-Mails |
| 10 | offen, mittel | Zwei Plätze mit unterschiedlichen Angeboten: das System zwingt zur Platzwahl vor dem Angebot, der Kunde kann nicht zwischen Varianten wählen | Vorschlag unten |
| 11 | offen, niedrig | Cockpit bei Partner-Events zeigt „Angefragte Plätze: noch kein Platz", obwohl der Platz feststeht und die Terminabstimmung läuft. Die Abstimmungsmatrix fehlt im Cockpit | Cockpit sollte bei Partner-Events die Terminabstimmung (wer hat geantwortet) statt der leeren Pipeline zeigen |
| 12 | offen, niedrig | Platz-Auswahlliste der Pipeline listet alle Partner-Posts, auch Golflehrer, Doppelte und „in Prüfung" | Filter auf Typ Platz/Indoor und Status aktiv |
| 13 | offen, niedrig | Termin-Seite des Platzes zeigt nach der Buchung weiter die Abstimmung („passt einer dieser Termine?") | Nach Buchung Stand „gebucht am …" zeigen |
| 14 | offen, niedrig | Vortags-Info an den Platz siezt („Guten Tag"), alle anderen Platz-Mails duzen | Anrede vereinheitlichen |
| 15 | offen, niedrig | Rückfrage des Kunden: keine Bestätigungsmail an den Kunden, Antwort läuft außerhalb des Systems | Bestätigung „Rückfrage ist da" plus Antwortfeld im Cockpit |
| 16 | offen, niedrig | Partner-Event: Einkaufspreis des Platzes muss von Hand in Schritt 2, die Pipeline greift dort nicht | Preis aus dem Partnerprofil oder dem Preisgedächtnis vorbelegen |

Geprüft und in Ordnung: Anonymität vor der Buchung (Preisanfrage, Termin-Link-Mail, Termin-Seite, Portal-Liste, Portal-Detail, Portal-Widget, ICS), Kundendaten nach der Buchung im Portal und in der Buchungsbestätigung, Einmal-Guards (Doppelklick beim Annehmen, Dienstleister-Mails nach Fassung 2), Dienstleister-Auftrag und -Absage bei Abwahl, Margenübersicht intern, Zahlen (Einzel- und Gesamtpreise, USt., brutto-nach-netto), alle zehn Control-Center-Seiten ohne PHP-Fehler.

## Vorschlag zu Fund 10: Angebot mit Varianten

Wenn zwei Plätze zugesagt haben und beide passen, soll der Kunde entscheiden. Bauvorschlag (nicht umgesetzt):

1. In der Pipeline zwei Plätze als „Variante A" und „Variante B" markieren (statt einen wählen). Jede Variante bekommt eigene Positionen aus ihren Pipeline-Preisen und einen eigenen Basispreis.
2. Der Snapshot enthält beide Varianten. Angebotsseite, PDF und Mail zeigen zwei Blöcke nebeneinander (Ort, Ablauf, Leistungen, Positionen, Summe je Variante) mit einer Auswahl „Wir nehmen Variante A" vor dem Annehmen.
3. Bei Annahme wird die gewählte Variante zum normalen Angebot (Platz zuordnen, Buchungsbestätigung an diesen Platz, Freigabe-Mail an den anderen). Die Termin-Frist gilt für beide Plätze, beide bekommen bis dahin die Reservierungsbitte.
4. Aufwand: Snapshot und Dokument zweispurig, Positionen je Variante, Annahme-Handler, Pipeline-UI. Etwa ein Arbeitstag.
