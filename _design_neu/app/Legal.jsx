/* eslint-disable */
// =============================================================
// Legal placeholder pages — Impressum, Datenschutz, AGB.
// Minimal real-looking content; not actual legal text.
// =============================================================

function LegalShell({ title, children, label }) {
  return (
    <article className="legal" data-screen-label={label}>
      <div className="legal-head">
        <div className="mk-eyebrow">Rechtliches</div>
        <h1 className="legal-h">{title}</h1>
      </div>
      <div className="legal-body">{children}</div>
    </article>
  );
}

function ImpressumPage() {
  return (
    <LegalShell title="Impressum" label="Impressum">
      <h2>Angaben gemäß § 5 TMG</h2>
      <p>
        Firmengolf GmbH<br />
        Hopfenstraße 17<br />
        20359 Hamburg<br />
        Deutschland
      </p>

      <h2>Vertreten durch</h2>
      <p>Lena Hoffmann (CEO), Jonas Bredow (Geschäftsführer)</p>

      <h2>Kontakt</h2>
      <p>
        Telefon: <a href="tel:+494012345678">+49 40 123 456 78</a><br />
        E-Mail: <a href="mailto:hallo@firmengolf.de">hallo@firmengolf.de</a>
      </p>

      <h2>Registereintrag</h2>
      <p>
        Eintragung im Handelsregister.<br />
        Registergericht: Amtsgericht Hamburg<br />
        Registernummer: HRB 1234567
      </p>

      <h2>Umsatzsteuer-ID</h2>
      <p>Umsatzsteuer-Identifikationsnummer gemäß §27 a Umsatzsteuergesetz: DE123456789</p>

      <h2>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h2>
      <p>Lena Hoffmann, Adresse wie oben</p>

      <h2>Haftungsausschluss</h2>
      <p>
        Trotz sorgfältiger inhaltlicher Kontrolle übernehmen wir keine Haftung für die Inhalte externer Links.
        Für den Inhalt der verlinkten Seiten sind ausschließlich deren Betreiber verantwortlich.
      </p>
      <p className="muted" style={{ marginTop: 32, fontSize: 13 }}>
        Hinweis: Dieser Text ist ein Platzhalter und nicht rechtsverbindlich.
      </p>
    </LegalShell>
  );
}

function DatenschutzPage() {
  return (
    <LegalShell title="Datenschutzerklärung" label="Datenschutz">
      <p className="legal-lead">
        Der Schutz deiner persönlichen Daten ist uns wichtig. Wir verarbeiten deine Daten ausschließlich
        auf Grundlage der gesetzlichen Bestimmungen (DSGVO, BDSG, TKG 2003).
      </p>

      <h2>1. Verantwortlicher</h2>
      <p>Firmengolf GmbH, Hopfenstraße 17, 20359 Hamburg. Kontakt siehe Impressum.</p>

      <h2>2. Welche Daten werden erhoben?</h2>
      <ul>
        <li>Server-Logfiles (IP, Zeitstempel, Browser, Betriebssystem)</li>
        <li>Kontaktdaten, die du uns im Anfrageformular übermittelst</li>
        <li>Nutzungsdaten für die Bereitstellung der Website</li>
      </ul>

      <h2>3. Zweck der Verarbeitung</h2>
      <ul>
        <li>Beantwortung von Anfragen</li>
        <li>Vertragsanbahnung und -abwicklung</li>
        <li>Statistische Auswertung des Nutzungsverhaltens (anonymisiert)</li>
      </ul>

      <h2>4. Cookies</h2>
      <p>
        Wir setzen technisch notwendige Cookies ein, um die Nutzung der Website zu ermöglichen.
        Tracking-Cookies setzen wir nur mit deiner ausdrücklichen Einwilligung über das Cookie-Banner.
      </p>

      <h2>5. Deine Rechte</h2>
      <ul>
        <li>Recht auf Auskunft (Art. 15 DSGVO)</li>
        <li>Recht auf Berichtigung (Art. 16 DSGVO)</li>
        <li>Recht auf Löschung (Art. 17 DSGVO)</li>
        <li>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
        <li>Recht auf Datenübertragbarkeit (Art. 20 DSGVO)</li>
        <li>Widerspruchsrecht (Art. 21 DSGVO)</li>
        <li>Beschwerderecht bei einer Aufsichtsbehörde</li>
      </ul>

      <h2>6. Kontakt zum Datenschutz</h2>
      <p>
        Fragen zur Verarbeitung deiner Daten richtest du an <a href="mailto:datenschutz@firmengolf.de">datenschutz@firmengolf.de</a>.
      </p>

      <p className="muted" style={{ marginTop: 32, fontSize: 13 }}>
        Hinweis: Dieser Text ist ein Platzhalter und nicht rechtsverbindlich. Die finale Datenschutzerklärung
        wird durch eine Anwaltskanzlei geprüft.
      </p>
    </LegalShell>
  );
}

function AGBPage() {
  return (
    <LegalShell title="Allgemeine Geschäftsbedingungen" label="AGB">
      <p className="legal-lead">
        Diese Allgemeinen Geschäftsbedingungen (&bdquo;AGB&ldquo;) regeln die Nutzung der Plattform
        firmengolf.de sowie die Vermittlung und Durchführung von Firmen-Golfevents durch die
        Firmengolf GmbH. Sie gelten gegenüber Unternehmen im Sinne des § 14 BGB.
      </p>
      <p className="legal-meta">Stand: Juni 2026 · Firmengolf GmbH, Hopfenstraße 17, 20359 Hamburg</p>

      <h2>§ 1 Geltungsbereich & Anbieter</h2>
      <p>
        (1) Anbieter der Plattform und Vertragspartner im Sinne dieser AGB ist die Firmengolf GmbH,
        Hopfenstraße 17, 20359 Hamburg (nachfolgend &bdquo;Firmengolf&ldquo;).
      </p>
      <p>
        (2) Diese AGB gelten für alle über firmengolf.de angebahnten Anfragen, Vermittlungen und
        Buchungen. Sie richten sich ausschließlich an Unternehmen, juristische Personen des
        öffentlichen Rechts und öffentlich-rechtliche Sondervermögen. Ein Verbrauchergeschäft im
        Sinne des § 13 BGB liegt nicht vor.
      </p>
      <p>
        (3) Abweichenden, entgegenstehenden oder ergänzenden Bedingungen des Kunden wird
        widersprochen; sie werden nur mit ausdrücklicher Zustimmung in Textform Vertragsbestandteil.
      </p>

      <h2>§ 2 Begriffe</h2>
      <ul>
        <li><strong>Kunde / Firma</strong> &ndash; das anfragende oder buchende Unternehmen.</li>
        <li><strong>Partnerplatz</strong> &ndash; der mit Firmengolf kooperierende Golfclub bzw. Platzbetreiber.</li>
        <li><strong>Partner-Event</strong> &ndash; ein vom Partnerplatz angebotenes, über Firmengolf vermitteltes Format.</li>
        <li><strong>Individuelles Event</strong> &ndash; ein von Firmengolf eigenständig geplantes und durchgeführtes Event.</li>
      </ul>

      <h2>§ 3 Rolle von Firmengolf (Vermittlung & Eigenleistung)</h2>
      <p>
        (1) Bei <strong>Partner-Events</strong> wird Firmengolf als Vermittler tätig. Der Vertrag über die
        Durchführung des Events kommt unmittelbar zwischen dem Kunden und dem jeweiligen Partnerplatz
        zustande. Firmengolf schuldet insoweit lediglich die Vermittlung und Koordination.
      </p>
      <p>
        (2) Bei <strong>individuellen Events</strong> wird Firmengolf selbst Vertragspartner des Kunden und
        schuldet die vereinbarte Eventleistung; einzelne Bestandteile kann Firmengolf durch Partnerplätze
        oder Dritte erbringen lassen.
      </p>
      <p>
        (3) Welche Rolle Firmengolf im Einzelfall einnimmt, ergibt sich aus dem Angebot bzw. der
        Auftragsbestätigung. Im Zweifel gilt Absatz 1.
      </p>

      <h2>§ 4 Anfrage, Terminabstimmung & Vertragsschluss</h2>
      <p>
        (1) Eine Anfrage über das Anfrageformular oder den Budgetrechner ist unverbindlich und stellt
        kein Angebot im Rechtssinne dar.
      </p>
      <p>
        (2) Auf Grundlage der Anfrage stimmt Firmengolf Wunschtermine mit dem Partnerplatz und den
        Beteiligten ab und unterbreitet dem Kunden ein Angebot. Ein verbindlicher Vertrag kommt erst mit
        der Auftragsbestätigung in Textform durch Firmengolf bzw. den Partnerplatz zustande.
      </p>
      <p>
        (3) Angaben zu Verfügbarkeit, Preisen und Leistungen vor Vertragsschluss sind freibleibend.
      </p>

      <h2>§ 5 Leistungen & Mitwirkung des Kunden</h2>
      <p>
        (1) Inhalt und Umfang der Leistung ergeben sich aus dem Angebot bzw. der Auftragsbestätigung.
        Nebenabreden bedürfen der Bestätigung in Textform.
      </p>
      <p>
        (2) Der Kunde teilt die verbindliche Teilnehmerzahl spätestens 7 Tage vor dem Event mit. Spätere
        Reduzierungen lassen den vereinbarten Mindestpreis unberührt. Mehrteilnehmer werden anteilig
        nachberechnet, soweit Kapazitäten verfügbar sind.
      </p>
      <p>
        (3) Der Kunde stellt sicher, dass die teilnehmenden Personen die Platz- und Sicherheitsregeln des
        Partnerplatzes beachten (siehe § 9).
      </p>

      <h2>§ 6 Preise & Zahlung</h2>
      <p>
        (1) Alle Preise verstehen sich netto zzgl. der jeweils gültigen gesetzlichen Umsatzsteuer.
      </p>
      <p>
        (2) Die Abrechnung erfolgt &ndash; auch bei vermittelten Partner-Events &ndash; durch Firmengolf in
        einer Rechnung an den Kunden, sofern nicht ausdrücklich etwas anderes vereinbart ist.
      </p>
      <p>
        (3) Sofern nicht anders vereinbart, ist bei Buchung eine Anzahlung von 30 % des Auftragswerts
        fällig; der Restbetrag ist spätestens 14 Tage vor dem Eventtermin zu zahlen. Rechnungen sind
        ohne Abzug innerhalb von 14 Tagen ab Zugang zahlbar.
      </p>
      <p>
        (4) Bei Zahlungsverzug gelten die gesetzlichen Regelungen. Die Geltendmachung weiterer Schäden
        bleibt vorbehalten.
      </p>

      <h2>§ 7 Umbuchung & Stornierung durch den Kunden</h2>
      <p>
        (1) Stornierungen und Umbuchungen bedürfen der Textform. Maßgeblich ist der Zugang bei
        Firmengolf. Es gelten folgende Stornostaffeln (in % des Auftragswerts):
      </p>
      <ul>
        <li>früher als 30 Tage vor dem Event: kostenfrei</li>
        <li>30 bis 15 Tage vor dem Event: 50 %</li>
        <li>weniger als 15 Tage vor dem Event: 100 %</li>
      </ul>
      <p>
        (2) Dem Kunden bleibt der Nachweis vorbehalten, dass kein oder ein wesentlich geringerer Schaden
        entstanden ist. Bereits an Dritte verauslagte, nicht erstattungsfähige Kosten (z. B. Catering,
        Technik) können zusätzlich in Rechnung gestellt werden.
      </p>

      <h2>§ 8 Absage durch Partnerplatz oder Firmengolf, höhere Gewalt</h2>
      <p>
        (1) Müssen ein Event oder Teile davon aus Gründen abgesagt werden, die Firmengolf bzw. der
        Partnerplatz nicht zu vertreten hat (insbesondere höhere Gewalt, behördliche Anordnungen,
        nicht bespielbarer Platz wegen Witterung), wird vorrangig ein Ersatztermin angeboten.
      </p>
      <p>
        (2) Kommt kein Ersatztermin zustande, werden bereits gezahlte Beträge für nicht erbrachte
        Leistungen erstattet. Weitergehende Ansprüche sind in diesen Fällen ausgeschlossen, soweit
        gesetzlich zulässig.
      </p>

      <h2>§ 9 Verhalten auf dem Golfplatz & Sicherheit</h2>
      <p>
        (1) Auf dem Gelände des Partnerplatzes gelten dessen Platz-, Haus- und Sicherheitsregeln. Den
        Anweisungen des Platzpersonals und der Trainer ist Folge zu leisten.
      </p>
      <p>
        (2) Golf ist mit platztypischen Risiken verbunden. Die Teilnahme erfolgt auf eigenes Risiko;
        für die Vor-Ort-Leistung und Verkehrssicherung ist bei Partner-Events der Partnerplatz
        verantwortlich.
      </p>

      <h2>§ 10 Benefit-Programm</h2>
      <p>
        Für ein etwaiges fortlaufendes Benefit-Programm gelten ergänzend die im jeweiligen Angebot
        genannten Laufzeit- und Kündigungsregelungen. Soweit dort nichts geregelt ist, ist das Programm
        mit einer Frist von 30 Tagen zum Monatsende kündbar.
      </p>

      <h2>§ 11 Haftung</h2>
      <p>
        (1) Firmengolf haftet unbeschränkt bei Vorsatz und grober Fahrlässigkeit sowie bei der Verletzung
        von Leben, Körper oder Gesundheit.
      </p>
      <p>
        (2) Bei einfacher Fahrlässigkeit haftet Firmengolf nur bei Verletzung einer wesentlichen
        Vertragspflicht (Kardinalpflicht) und der Höhe nach begrenzt auf den vertragstypischen,
        vorhersehbaren Schaden.
      </p>
      <p>
        (3) Im Rahmen der Vermittlung von Partner-Events haftet Firmengolf nicht für die Erbringung der
        Eventleistung durch den Partnerplatz; insoweit ist der Partnerplatz Anspruchsgegner. Eine Haftung
        nach dem Produkthaftungsgesetz bleibt unberührt.
      </p>

      <h2>§ 12 Schlussbestimmungen</h2>
      <p>
        (1) Es gilt das Recht der Bundesrepublik Deutschland unter Ausschluss des UN-Kaufrechts.
      </p>
      <p>
        (2) Ausschließlicher Gerichtsstand für alle Streitigkeiten ist &ndash; soweit der Kunde Kaufmann,
        juristische Person des öffentlichen Rechts oder öffentlich-rechtliches Sondervermögen ist &ndash;
        Hamburg. Erfüllungsort ist Hamburg.
      </p>
      <p>
        (3) Sollten einzelne Bestimmungen unwirksam sein, bleibt die Wirksamkeit der übrigen
        Bestimmungen unberührt. Änderungen und Ergänzungen bedürfen der Textform.
      </p>

      <p className="muted" style={{ marginTop: 32, fontSize: 13 }}>
        Wichtiger Hinweis: Dieser Entwurf dient als Arbeitsgrundlage und ist <strong>keine
        Rechtsberatung</strong>. AGB unterliegen in Deutschland einer strengen Inhaltskontrolle
        (§§ 305 ff. BGB); unwirksame Klauseln entfallen ersatzlos. Vor Veröffentlichung muss dieser
        Text durch einen Fachanwalt (IT-/Vertragsrecht) geprüft und an euer finales Geschäftsmodell
        angepasst werden.
      </p>
    </LegalShell>
  );
}

window.ImpressumPage = ImpressumPage;
window.DatenschutzPage = DatenschutzPage;
window.AGBPage = AGBPage;
