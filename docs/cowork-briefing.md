# Firmengolf: Projekt-Briefing für Marketing und Kommunikation

Stand: 8. Juli 2026. Dieses Dokument ist die Arbeitsgrundlage für alle Text-, Mail- und
Marketing-Arbeit in Cowork. Die technische Umsetzung (Code, Templates, Website) läuft
separat in Claude Code auf dem Repo. Ergebnisse aus Cowork (Mailtexte, Kampagnenpläne,
Landingpage-Copy) werden dort eingebaut.

---

## 1. Was ist Firmengolf?

**Firmengolf** (firmengolf-events.de) ist ein Marktplatz, der Unternehmen und Golfplätze
zusammenbringt. Firmen buchen Golf-Events für Teams und Kunden (Teamevents, Turniere,
Platzreife-Kurse, After-Work-Golf, Kundenevents, Incentives). Golfplätze bekommen
darüber Firmenkunden, ein lukratives Segment, das sie allein schwer erreichen.

**Geschäftsmodell:** Der Golfplatz hinterlegt seine Netto-Preise, Firmengolf schlägt
fix 20 Prozent Vermittlung auf. Für Golfplätze ist die Teilnahme kostenlos. Ein
Ansprechpartner, eine Rechnung, ein Vertrag für die Firma.

**Gründer und Gesicht der Marke:** Julius Klinzer. Er tritt persönlich auf (Video auf
der Über-uns-Seite, Ansprechpartner in Anfrage-Wizard und Mails, LinkedIn).

**Wichtig:** firmen.golf ist ein ANDERES Projekt und darf nie als Mail-Domain oder
Referenz genutzt werden. visionpunch.de war die alte Domain und leitet nur noch weiter.

## 2. Wo steht das Projekt gerade?

- **Live seit 21.06.2026**, seit 06.07.2026 auf der finalen Domain firmengolf-events.de
  (Hetzner). Alte Domain visionpunch.de leitet per 301 weiter, Mails an @visionpunch.de
  kommen weiter an.
- **20 Vertragspartner-Golfplätze** (Bayern, Schleswig-Holstein, Baden-Württemberg,
  u. a. OPEN.9 Golf Eichenried, Gut Kaden, Golfclub Schloss Maxlrain). Stammdaten
  aller 20 sind gegen die Club-Websites verifiziert. Ziel: 750 Partnerplätze.
- **48 buchbare Events** live, 810 deutsche Golfplätze im Verzeichnis (DGV-Basis).
- **Der nächste große Schritt und Hauptauftrag für Cowork:** Die Ansprache aller
  deutschen Golfplätze, damit sie sich selbst onboarden. Die Technik dafür
  (Selbst-Onboarding mit E-Mail-Verifizierung, Portal, Einladungslinks) ist fertig
  und auditiert.
- Analytics (GA4, Property G-17GGY2WEEV) und Google Search Console laufen.

## 3. Zielgruppen

**Demand (Firmen):** HR, Office-Management, Assistenz, Team-Leads, Geschäftsführung
kleinerer Firmen. Planen Sommerfeste, Teamevents, Kundenevents. Meist ohne
Golf-Vorkenntnisse. Kernbotschaft: Golf ist für jeden, kein Elite-Gehabe, keine
Vorkenntnisse nötig, wir kümmern uns um alles.

**Supply (Golfplätze):** Clubmanager, Geschäftsführer, Sekretariate von Golfanlagen.
Schmerz: Firmenkunden sind attraktiv, aber Akquise und Organisation sind mühsam.
Angebot: kostenlose Plattform, vorbereitete Profile, Anfragen kommen fertig
qualifiziert rein, ein Portal für alles.

## 4. Die Produkt-Flows in Kurzform

- **Firma:** Event finden (Liste, Stadt- und Format-Seiten) oder individuell anfragen
  (Wizard, 30-Sekunden-Schnellanfrage) → Firmengolf klärt Termine mit dem Platz →
  Angebot per Mail (netto plus 19 % MwSt, Endpreis) → Ein-Klick-Annahme → gebucht.
- **Golfplatz:** Selbst-Onboarding unter /partner-onboarding/ (17 Schritte, mit
  6-stelliger E-Mail-Code-Verifizierung) ODER persönlicher Einladungslink für
  vorbereitete Profile → Partnerportal (Profil, Fotos, Events anlegen, Anfragen
  beantworten, Kennzahlen) → Events werden von Firmengolf geprüft und freigegeben.
- **Automatische Mails:** 31 Transaktionsmails begleiten beide Seiten. Komplettes
  Inventar mit Auslöser, Empfänger und Inhalt: docs/mail-inventar.md im Repo.

## 5. Marke, Tonalität, harte Schreibregeln

**Markenwerte (von der Über-uns-Seite):** Unbeschwert (Spaß ohne VIP-Gehabe),
Inspirierend (wir verkaufen ein Gefühl: Bewegung, Natur, gemeinsame Zeit),
Mitfühlend (direkt, persönlich, nie belehrend, echter Mensch am Telefon).

**Tonalität:** Du-Form gegenüber allen (Firmen wie Partnern). Persönlich, konkret,
ehrlich. Selbstbewusst zur eigenen Frühphase stehen (die Seite hat eine eigene
„Wir sind neu"-Sektion, die aktiv um Feedback bittet).

**Harte Regeln (unbedingt einhalten):**
1. **Keine Gedankenstriche** (weder — noch –) in irgendwelchen Texten. Julius empfindet
   sie als unecht wirkend. Stattdessen Komma, Doppelpunkt, Punkt oder zwei Sätze.
   Zahlenbereiche mit „bis" schreiben (12 bis 30 Personen, März bis Oktober).
2. **Preise immer netto kennzeichnen:** Format „96 € p.P. netto" bzw. „810 € Gesamt
   netto". In Angeboten zusätzlich „zzgl. 19 % MwSt." und Endpreis. Euro-Zeichen
   hinter der Zahl.
3. **Keine Fake-Daten:** keine erfundenen Bewertungen, Testimonials oder Zahlen.
   Nur echte, belegbare Aussagen.
4. **Absender-Adressen:** immer @firmengolf-events.de (events@, partner@, hallo@,
   julius@). Niemals firmen.golf.
5. CI: Primärblau #4279D1, Türkis-Akzent #00C896 nur an starken Momenten,
   Schrift Roboto.

## 6. Mail- und Versand-Infrastruktur

- **Transaktionsmails:** WordPress → WP Mail SMTP → Brevo (Domain authentifiziert,
  SPF/DKIM/DMARC grün). Absender events@firmengolf-events.de.
- **Postfächer:** Microsoft 365. Geteiltes Postfach „Events" mit Aliassen events@,
  partner@, hallo@. julius@ geht direkt an Julius. Antworten auf alle Systemmails
  landen im geteilten Postfach.
- **Brevo** steht auch für Marketing-Kampagnen und Newsletter bereit (gleiche
  authentifizierte Domain). Noch keine Kampagnen aufgesetzt.
- **Zustellbarkeit:** Die Domain ist jung. Erfahrungswert vom alten Setup: erste Mails
  an Outlook-Adressen landeten anfangs im Junk (reine Neu-Absender-Reputation, keine
  Technikfehler). Konsequenz für die Akquise: **in Wellen versenden** (Richtwert 50
  bis 100 pro Tag, langsam steigern), nicht 810 auf einmal.

## 7. Marketing-Bestand (worauf Cowork aufbauen kann)

- **SEO-Landingpages:** Stadt-Seiten (/golf-events/muenchen/ usw. für 8 Städte),
  Format-Seiten (/firmenevent/teamevent/ usw. für 6 Formate), Format-mal-Stadt-Seiten
  (München, Hamburg). Keyword-Map liegt im Repo (docs/seo-keyword-map.md).
- **Blog:** 6 Artikel live (Sprint 1, Zielgruppe Planer, Golfwissen als
  E-E-A-T-Signal). Content-Strategie existiert. Ein Entwurf für Golfplätze
  („Mehr Firmenanfragen für euren Golfplatz") wartet auf Freigabe.
- **Partner-Werkzeuge, die Marketing nutzen kann:** Embed-Widget (Partner binden ihre
  Firmengolf-Events auf der eigenen Website ein), Partner-Badge-Snippet mit UTM,
  persönliche Einladungslinks mit Club-Namen in der URL.
- **Über-uns-Seite** mit Gründervideo und persönlichem Brief als Vertrauensanker.
- **Tracking:** GA4 (einwilligungsbasiert via Klaro), Search Console mit Sitemap.

## 8. Arbeitsvorrat für Cowork (priorisiert)

### A. Golfplatz-Akquise (wichtigste Aufgabe)
1. **Outreach-Sequenz an die 810 Verzeichnis-Plätze texten:** Erstansprache plus
   1 bis 2 Follow-ups. Kern: kostenlos, vorqualifizierte Firmenanfragen, 5 Minuten
   Selbst-Onboarding. Absender Julius persönlich. Wellenplan (50 bis 100/Tag).
2. **Begleitmail für die 20 Bestandspartner:** persönliche Mail mit Einladungslink,
   Erklärung des Portals, Bitte um Profilvervollständigung und erstes Event.
3. **Segmentierung überlegen:** nach Region (Bayern und Norden zuerst, dort sind
   Referenzpartner), nach Anlagentyp, nach Nähe zu Großstädten.
4. **Einwände vorwegnehmen:** „Was kostet das?" (nichts), „Wie viel Arbeit?"
   (Profil in Minuten, Anfragen kommen fertig), „Was ist mit unseren Mitgliedern?"
   (Firmenkunden buchen Randzeiten, Events sind planbar).

### B. Demand-Marketing (Firmen)
5. Blog Sprint 2 planen und texten (Keyword-Map als Basis).
6. Social-Kanal-Strategie (LinkedIn liegt nahe: Julius als Person, B2B-Zielgruppe).
7. Newsletter-Konzept für Brevo (Interessenten aus Anfragen, die nicht gebucht haben).
8. Saisonale Anlässe bespielen (Sommerfest-Saison, Weihnachtsfeier-Planung ab Herbst).

### C. Vertrauen und Recht
9. Testimonials und Referenzen von den ersten echten Events einsammeln (Prozess
   dafür entwerfen: wann fragen wir, mit welchem Text).
10. Pressetext / Über-Firmengolf-Boilerplate für Anfragen und Verzeichnisse.
11. Offen aus der Launch-Phase (mit Anwalt/DSB): Datenschutzerklärung final
    (muss GA4, Klaro, Brevo abdecken), AGB-Review, AVVs mit Brevo, Google, Hetzner.

## 9. Arbeitsteilung und Übergabe-Prozess

**Cowork (dieses Briefing):** Alles, was Text, Konzept, Kampagne, Planung ist.
Mailsequenzen, Landingpage-Copy, Blogartikel, Social-Posts, Segmentierung,
Einwandbehandlung, Redaktionsplan.

**Claude Code (Repo firmengolf-events):** Alles, was die Website oder das System
anfasst. Neue Templates, Mail-Template-Änderungen, Landingpages technisch anlegen,
Brevo-Anbindung, Datenexporte (z. B. Liste der 810 Plätze mit Kontaktdaten aus
der Datenbank ziehen).

**So fließt Arbeit hin und her:**
1. Cowork produziert Texte/Pläne als Dokumente.
2. Julius bringt fertige Texte in die Code-Session („bau diese Mail ein",
   „erstelle diese Landingpage mit diesem Text").
3. Braucht Cowork Daten aus dem System (Platzlisten, Anfrage-Statistiken,
   Event-Daten), liefert die Code-Session einen Export, den Julius in Cowork hochlädt.
4. Dieses Briefing ist das lebende Übergabedokument. Bei größeren Änderungen am
   Produkt wird es in der Code-Session aktualisiert und neu nach Cowork übernommen.

**Referenzen im Repo** (bei Bedarf in der Code-Session abrufbar):
docs/mail-inventar.md (alle 31 Mails), docs/seo-keyword-map.md (Keywords),
docs/go-live-runbook.md und docs/domain-migration-runbook.md (Technik-Historie).

## 10. Schnellreferenz

| | |
|---|---|
| Website | https://firmengolf-events.de |
| Alte Domain | visionpunch.de (301-Weiterleitung, Mail läuft weiter) |
| Mail-Absender | events@firmengolf-events.de (System), julius@ (persönlich) |
| Transaktionsmails | Brevo, 31 Stück, siehe docs/mail-inventar.md |
| Analytics | GA4 G-17GGY2WEEV, einwilligungsbasiert |
| Partner aktuell | 20 Vertragsplätze, Ziel 750 |
| Events live | 48 |
| Verzeichnis | 810 deutsche Golfplätze (DGV-Basis) |
| Modell | Netto-Partnerpreis + 20 % Firmengolf-Aufschlag, für Plätze kostenlos |
| Onboarding | /partner-onboarding/ (selbst) oder persönlicher Einladungslink |
| Schreibregeln | Du-Form, keine Gedankenstriche, Preise netto, keine Fake-Daten |
