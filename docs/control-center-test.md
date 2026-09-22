# Control Center: Testanleitung

Stand 22.09.2026, Plugin 1.9.274. Zum Durchklicken auf der lokalen Umgebung, bevor irgendetwas deployt wird.

**Wo:** `http://localhost:8080/control/` nach `docker compose up -d`. Angemeldet als Administrator. Wer kein Administrator ist, bekommt 404, die Seite existiert für fremde Konten nicht.

**Was noch nicht deployt ist:** alles. Live läuft weiterhin 1.9.266 ohne Control Center.

---

## 1 Der wichtigste Durchgang: eine Anfrage von vorn

Nimm eine Testanfrage ohne zugeordneten Platz (Dashboard, Eimer „Wartet auf mich", Zeile mit „Passende Plätze finden und anfragen").

1. **Plätze aufnehmen.** In der Anfrage unter „Angefragte Plätze" zwei oder drei Plätze aus dem Auswahlfeld aufnehmen. Beim Platz sollte, sofern schon einmal ein Preis erfasst wurde, der letzte Preis als Hinweis stehen.
2. **Anfragen.** Bei einem Platz „Per Mail anfragen", bei einem anderen „Telefonisch angefragt". Die Mail landet in MailHog (`http://localhost:8025`). Lies sie: Ton, Wunschtermine, Gruppe, und die Liste der Positionen, zu denen wir einen Preis brauchen.
3. **Antwort festhalten.** Beim angefragten Platz „Antwort festhalten" aufklappen: Zusage mit Preis, dazu je Position einen eigenen Preis oder das Häkchen wegnehmen, wenn der Platz das nicht anbietet. Beim anderen Platz eine Absage mit Grund.
4. **Platz nehmen.** „Diesen Platz nehmen". Danach steht der Platz in der Anfrage, und sein Preis ist als Einkauf hinterlegt.
5. **Übrigen absagen.** Der Knopf „Allen übrigen absagen" erscheint. Lies auch diese Mail.

Worauf achten: Stimmt der Ton der beiden Platz-Mails? Das ist der Text, den deine Plätze künftig dauerhaft von uns bekommen.

## 2 Angebot und Buchung

1. Termin im WordPress-Backend bestätigen (Schritt 1), oder eine Anfrage nehmen, bei der das schon passiert ist.
2. Im Cockpit „Angebot jetzt senden". **Vorher lesen, was unter dem Knopf steht**: dort stehen die echten Empfänger dieser Anfrage. Das ist die Zusage, dass dich nie wieder eine Mail überrascht.
3. Angebotsseite öffnen, als Kunde annehmen.
4. Zurück im Cockpit: im Postausgang stehen jetzt vier Mails mit Klarnamen und Zustellstatus.
5. Die Auftragsbestätigung an den Platz in MailHog lesen. Sie ersetzt deine handgetippte Mail an Sandy: Termin, Gruppe, Ansprechpartner, Paket, Betrag, Rechnungsadresse, Verwendungszweck.

## 3 Eventtag

1. In der Phase „Vorbereitung" Startzeit und Treffpunkt eintragen, speichern.
2. Beim Speichern geht die Ablauf-Info an den Kunden raus, einmalig. In MailHog lesen: das ist deine Mail an Elske.
3. „Vortags-Info senden" probeweise auslösen und die drei Mails vergleichen.

## 4 Kalender und Outlook

1. Kalender öffnen, Monate durchblättern.
2. Bei einem Tag mit zwei Events erscheint oben eine Warnung, die Zelle ist rot umrandet.
3. Den Abo-Link kopieren und in Outlook als Internetkalender hinzufügen. Danach sollten Events, Optionen, Fristen und fällige Aufgaben erscheinen.
4. Bedenke: Outlook aktualisiert solche Abos nur alle paar Stunden. Dafür gibt es die Tagesmail.
5. Tagesmail von Hand auslösen: `docker compose exec wordpress wp eval 'fge_cc_send_daily_digest();'`

## 5 Plätze, Kunden, Dienstleister

1. **Plätze:** ganz oben steht, wie viele Plätze keine Kontaktmail haben. Diese Plätze bekommen weder Auftragsbestätigung noch Vortags-Info. Der Filter zeigt genau sie.
2. **Kunden:** prüfe die Dublettenwarnung. In den Testdaten schlägt sie oft an, weil dieselbe Mailadresse mehrfach verwendet wurde. Auf echten Daten ist das der Hinweis, zwei Vorgänge zusammenzuführen.
3. **Dienstleister:** lege deine echten Pros, Shuttle-Anbieter und Caterer an. Danach schlägt Schritt 2 im WordPress-Backend ihre Mailadressen vor.

## 6 Geld

Die Marge rechnet nur über Buchungen mit hinterlegtem Einkaufspreis und sagt dazu, wie viele ohne sind. Prüfe die Zahl gegen einen Vorgang, den du kennst. Mit dem Platz verhandelst du brutto, angeboten wird netto, die Umrechnung steckt in der Rechnung.

## 7 Am Telefon

Ruf das Control Center auf dem Handy auf. Geprüft ist: kein seitliches Scrollen, keine Eingabefelder unter 16 Pixel (sonst zoomt iOS beim Tippen), keine Knöpfe unter 44 Pixel. Was sich nur anfühlen lässt, musst du selbst beurteilen.

---

## Was bewusst nicht gebaut ist

- **Microsoft Graph**: keine echten Outlook-Aufgaben, kein Zweiweg-Sync. Bewusst, weil App-Registrierung und Tokenpflege ein eigenes Projekt sind.
- **Preise im Katalog-Event**: wenn ein Platz zusagt, sein Event dauerhaft anzubieten, bleiben die Preise leer. Die setzt er selbst im Portal, dort gilt seine Kalkulation und nicht der telefonisch verhandelte Sonderpreis.
- **Das WordPress-Backend bleibt**, als Werkstatt für alles, was das Control Center noch nicht kann.

## Wenn etwas kaputt ist

Der Postausgang zeigt, ob eine Mail wirklich rausging. Die Zeitleiste einer Anfrage zeigt, was wann passiert ist. Beides gab es vorher nicht, beides ist die erste Anlaufstelle, bevor du im Code suchst.
