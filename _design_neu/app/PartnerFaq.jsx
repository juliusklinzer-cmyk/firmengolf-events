/* eslint-disable */
// =============================================================
// PartnerFaqPage — detailed FAQ for golf-course partners.
// Route: #/partner-faq. Category rail + accordion. Mirrors the
// real partner flow (portal, multi-party date approval, billing).
// Exposes window.PartnerFaqPage.
// =============================================================
var { useState: usePfaqState } = React;

const PFAQ = [
  {
    id: 'einstieg', name: 'Einstieg & Aufnahme',
    desc: 'Wie ihr Partnerplatz werdet — und was es kostet.',
    items: [
      { q: 'Wie wird mein Golfplatz Partner bei Firmengolf?', a: [
        'Über unser Onboarding hinterlegt ihr in rund 20 Minuten eure Infrastruktur (Range, Greens, Clubhaus, Räume), Kapazitäten pro Bereich und die Veranstaltungstypen, die ihr abdecken könnt.',
        'Wir prüfen die Angaben, stimmen kurz mit euch ab und schalten euer Profil anschließend frei — danach erscheint ihr auf der Plattform und könnt Anfragen empfangen.',
      ] },
      { q: 'Was kostet die Partnerschaft?', a: [
        'Es gibt keine Grundgebühr und keine Setup-Kosten. Firmengolf verdient nur an erfolgreich vermittelten Buchungen über eine Provision — ihr zahlt also erst, wenn ihr verdient.',
      ], note: 'Konkreter Provisionssatz ist Platzhalter — bitte mit euren echten Konditionen ersetzen.' },
      { q: 'Welche Voraussetzungen muss mein Platz erfüllen?', a: [
        'Eine bespielbare Anlage (Driving Range, 9 oder 18 Loch), mindestens ein:e feste:r Ansprechpartner:in und die Bereitschaft, Firmengruppen zu empfangen. Clubhaus, Gastronomie oder Tagungsräume sind ein Plus, aber kein Muss.',
      ] },
      { q: 'Wie lange dauert die Freischaltung?', a: [
        'In der Regel wenige Werktage nach Eingang eurer Angaben. Sobald wir gemeinsam alles geprüft haben, geht euer Profil live.',
      ] },
    ],
  },
  {
    id: 'anfragen', name: 'Anfragen & Buchungen',
    desc: 'Wie Anfragen bei euch ankommen und wer sich kümmert.',
    items: [
      { q: 'Wie bekomme ich Anfragen?', a: [
        'Jede passende Firmen-Anfrage landet in eurem Partner-Portal — und zusätzlich erhalten alle hinterlegten Ansprechpartner:innen eine E-Mail mit einem Link zur Anfrage.',
      ] },
      { q: 'Muss ich jede Anfrage annehmen?', a: [
        'Nein. Ihr könnt eine Anfrage annehmen, ablehnen oder einen Alternativtermin vorschlagen. Ihr behaltet die volle Kontrolle über eure Belegung.',
      ] },
      { q: 'Wer betreut die anfragende Firma?', a: [
        'Firmengolf koordiniert die Kommunikation mit der Firma — Angebot, Rückfragen und Abstimmung laufen über uns. Ihr gebt nur eure Verfügbarkeit frei und richtet am Tag das Event aus.',
      ] },
    ],
  },
  {
    id: 'termine', name: 'Termine & Koordination',
    desc: 'Die Termin-Freigabe mit mehreren Beteiligten.',
    items: [
      { q: 'Wie funktioniert die Terminfreigabe?', a: [
        'Die Firma schlägt bis zu drei Wunschtermine vor. Im Portal kann jede beteiligte Person zu jedem Termin zusagen, absagen oder eine Alternative vorschlagen.',
        'Sobald alle reagiert haben, bestätigt ihr den passenden Termin — und Firmengolf übernimmt den Rest.',
      ] },
      { q: 'Können mehrere Personen vom Platz mitentscheiden?', a: [
        'Ja. Ihr könnt z. B. Inhaber:in, Golf-Pro und Sekretariat hinterlegen. Alle bekommen den Anfrage-Link per Mail und können ihre Verfügbarkeit eintragen — ihr seht jederzeit, wer schon reagiert hat.',
      ] },
      { q: 'Was passiert, wenn jemand nicht rechtzeitig reagiert?', a: [
        'Damit die Firma nicht warten muss, übernimmt Firmengolf in dem Fall die Koordination und legt gemeinsam mit euch einen Termin fest. So bleibt die Antwortzeit kurz.',
      ] },
      { q: 'Kann ich einen Termin in meinen Kalender übernehmen?', a: [
        'Ja. Sobald ein Termin bestätigt ist, lässt er sich direkt als Kalendereintrag (.ics) speichern und in Outlook, Google & Co. übernehmen.',
      ] },
    ],
  },
  {
    id: 'preise', name: 'Preise & Abrechnung',
    desc: 'Wie ihr Preise pflegt und ausgezahlt werdet.',
    items: [
      { q: 'Wie lege ich meine Preise fest?', a: [
        'Ihr pflegt eure Angebote selbst im Portal — pro Person oder als Gruppenpreis, inklusive Dauer, Gruppengröße und Leistungen. Ihr könnt Angebote jederzeit anpassen, pausieren oder neu anlegen.',
      ] },
      { q: 'Wie läuft die Abrechnung?', a: [
        'Die Firma erhält eine Rechnung von Firmengolf — sie muss sich nicht um mehrere Einzelposten kümmern. Eure Leistung rechnen wir transparent mit euch ab.',
      ], note: 'Genauer Abrechnungs- und Auszahlungsmodus ist Platzhalter — bitte an eure Konditionen anpassen.' },
      { q: 'Wann werde ich ausgezahlt?', a: [
        'Nach dem durchgeführten Event und innerhalb der vereinbarten Frist. Den aktuellen Stand seht ihr jederzeit in eurer Umsatzübersicht im Portal.',
      ] },
    ],
  },
  {
    id: 'portal', name: 'Portal & Profil',
    desc: 'Euer Auftritt und die Technik dahinter.',
    items: [
      { q: 'Brauche ich spezielle Software?', a: [
        'Nein. Das gesamte Partner-Portal läuft im Browser — am Laptop oder am Handy. Keine Installation, kein Wartungsaufwand.',
      ] },
      { q: 'Kann ich mein Profil und meine Fotos selbst pflegen?', a: [
        'Ja. Beschreibung, Eckdaten, Bildergalerie, Ausstattung und Angebote bearbeitet ihr selbst auf eurer Platz-Seite. Was ihr veröffentlicht, erscheint auf eurem öffentlichen Firmengolf-Profil.',
      ] },
      { q: 'Können mehrere Mitarbeitende Zugang haben?', a: [
        'Ja. Ihr könnt mehrere Personen mit eigenen Zugängen und Rollen hinterlegen — vom Sekretariat bis zum Pro.',
      ] },
    ],
  },
  {
    id: 'vertrag', name: 'Vertrag & Konditionen',
    desc: 'Bindung, Pause und Exklusivität.',
    items: [
      { q: 'Binde ich mich langfristig?', a: [
        'Nein. Die Partnerschaft ist flexibel — kein langjähriger Lock-in. Ihr entscheidet, wie viel ihr über Firmengolf laufen lasst.',
      ], note: 'Vertragslaufzeit/Kündigungsfrist ist Platzhalter — bitte mit euren echten Konditionen ersetzen.' },
      { q: 'Kann ich meinen Platz vorübergehend pausieren?', a: [
        'Ja. Mit einem Klick pausiert ihr euren Platz — alle Angebote gehen dann offline und sind für Firmen nicht buchbar, bis ihr wieder aktiviert.',
      ] },
      { q: 'Muss ich exklusiv mit Firmengolf arbeiten?', a: [
        'Nein. Ihr könnt euren Platz weiterhin frei vermarkten und anderweitig vergeben. Firmengolf ist ein zusätzlicher Kanal, kein Ersatz.',
      ] },
    ],
  },
];

function PartnerFaqPage() {
  const [active, setActive] = usePfaqState('einstieg');
  // open state keyed by "catId:index"
  const [open, setOpen] = usePfaqState({ 'einstieg:0': true });
  const toggle = (k) => setOpen(o => ({ ...o, [k]: !o[k] }));

  const goCat = (id) => {
    setActive(id);
    const el = document.getElementById('pfaq-' + id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  return (
    <div className="pfaq" data-screen-label="Partner-FAQ">
      {/* HERO */}
      <section className="pfaq-hero">
        <div className="pfaq-hero-inner">
          <div className="pfaq-hero-eyebrow">Für Golfplätze · Häufige Fragen</div>
          <h1>Alles, was ihr über eine Partnerschaft <em>wissen</em> müsst.</h1>
          <p className="pfaq-hero-sub">
            Von der Aufnahme über die Termin-Abstimmung bis zur Abrechnung — hier beantworten wir die
            Fragen, die Golfplätze uns am häufigsten stellen.
          </p>
          <div className="pfaq-hero-ctas">
            <a className="press-btn-light" href="#/partner-onboarding" onClick={(e) => { e.preventDefault(); window.location.href = 'partner-onboarding.html'; }}>
              Platz anbieten <ArrowGlyph />
            </a>
            <a className="press-btn-ghost" href="#/kontakt" onClick={(e) => go('#/kontakt', e)}>Direkt fragen</a>
          </div>
        </div>
      </section>

      {/* BODY */}
      <div className="pfaq-body">
        <nav className="pfaq-rail">
          <div className="pfaq-rail-h">Themen</div>
          {PFAQ.map(c => (
            <button key={c.id} className={'pfaq-cat ' + (active === c.id ? 'on' : '')} onClick={() => goCat(c.id)}>
              {c.name} <span className="n">{c.items.length}</span>
            </button>
          ))}
        </nav>

        <div className="pfaq-groups">
          {PFAQ.map(c => (
            <section className="pfaq-group" id={'pfaq-' + c.id} key={c.id}>
              <h2 className="pfaq-group-h">{c.name}</h2>
              <p className="pfaq-group-d">{c.desc}</p>
              {c.items.map((it, i) => {
                const k = c.id + ':' + i;
                return (
                  <div className={'pfaq-item ' + (open[k] ? 'open' : '')} key={k}>
                    <button className="pfaq-q" onClick={() => toggle(k)}>
                      {it.q}
                      <svg className="chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div className="pfaq-a">
                      <div className="pfaq-a-inner">
                        {it.a.map((p, j) => <p key={j}>{p}</p>)}
                        {it.note && <div className="note">{it.note}</div>}
                      </div>
                    </div>
                  </div>
                );
              })}
            </section>
          ))}
        </div>
      </div>

      {/* CTA */}
      <section className="pfaq-cta">
        <div className="pfaq-cta-card">
          <div>
            <div className="pfaq-cta-h">Noch eine Frage offen?</div>
            <div className="pfaq-cta-p">Schreibt uns — wir melden uns persönlich und helfen bei der Aufnahme eures Platzes.</div>
          </div>
          <div className="pfaq-cta-actions">
            <a className="fg-btn-brand" href="mailto:partner@firmengolf.de">An Partner-Team schreiben</a>
            <a className="fg-btn-ghost" href="#/partner-onboarding" onClick={(e) => { e.preventDefault(); window.location.href = 'partner-onboarding.html'; }}>Platz anbieten →</a>
          </div>
        </div>
      </section>
    </div>
  );
}

window.PartnerFaqPage = PartnerFaqPage;
