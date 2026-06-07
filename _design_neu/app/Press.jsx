/* eslint-disable */
// =============================================================
// PressPage — Newsroom / press page. Route: #/presse
// Boilerplate (copy-ready), facts, press releases, coverage,
// media kit (real logo downloads), founder quote, press contact.
// Exposes window.PressPage.
// =============================================================
var { useState: usePressState } = React;

const PRESS_RELEASES = [
  {
    date: '22. Mai 2026', tag: 'Wachstum',
    title: 'Firmengolf überschreitet 180 Partnerplätze in Deutschland',
    excerpt: 'Mit 24 neuen Clubs allein in diesem Frühjahr wächst das Netz buchbarer Golfplätze für Firmenevents weiter — von der Küste bis zu den Alpen.',
  },
  {
    date: '8. April 2026', tag: 'Benefit',
    title: '2.400 Mitarbeitende nutzen Golf inzwischen als Corporate Benefit',
    excerpt: 'Jahresbilanz: Immer mehr Unternehmen integrieren den steuerfreien Sachbezug in ihr Benefit-Programm — und bringen ihre Teams regelmäßig in Bewegung.',
  },
  {
    date: '3. März 2026', tag: 'Studie',
    title: 'Neue Auswertung: Wie ein Tag im Grünen Teams nachhaltig verbindet',
    excerpt: 'Gemeinsam mit einer Sporthochschule hat Firmengolf untersucht, was ein Golf-Teamtag mit Zusammenhalt, Konzentration und Wohlbefinden macht.',
  },
  {
    date: '14. Januar 2026', tag: 'Produkt',
    title: 'Neuer Budget-Rechner macht Eventplanung in Minuten transparent',
    excerpt: 'Unternehmen sehen ab sofort in Echtzeit einen realistischen Richtwert für ihr Firmenevent — vom Schnupperkurs bis zum mehrtägigen Offsite.',
  },
];

const PRESS_COVERAGE = [
  { outlet: 'HR Today', q: '„Firmengolf nimmt dem Golfsport das Elitäre — und macht ihn zum überraschend nahbaren Team-Benefit."', meta: 'Über den Benefit-Ansatz · 2026' },
  { outlet: 'Eventbranche.de', q: '„Eine Anfrage, ein Ansprechpartner, eine Rechnung: Die Plattform räumt mit der Komplexität von Firmenevents auf."', meta: 'Über den Buchungsprozess · 2026' },
  { outlet: 'Gründerszene', q: '„Aus einer simplen Idee — Golf für Firmen zugänglich machen — ist ein bundesweites Partnernetz geworden."', meta: 'Über das Wachstum · 2025' },
];

const PRESS_FACTS = [
  { v: '180+', l: 'Partnerplätze in Deutschland' },
  { v: '2.400', l: 'Mitarbeitende im Benefit-Programm' },
  { v: '2024', l: 'gegründet in München' },
  { v: '4,9 ★', l: 'Ø Bewertung über alle Events' },
];

const BOILERPLATE = 'Firmengolf macht Golf für Unternehmen zugänglich — als Firmenevent, als Offsite-Location und als wiederkehrenden Mitarbeiter-Benefit. Über eine kuratierte Plattform buchen Firmen Teamevents, Turniere, Schnupperkurse und individuelle Veranstaltungen auf über 180 Partnerplätzen in ganz Deutschland: eine Anfrage, ein Ansprechpartner, eine Rechnung. Gegründet 2024 in München, verfolgt Firmengolf ein klares Ziel — Golf nicht als exklusives Statussymbol, sondern als offenen, gesunden Ausgleich, der Teams aus dem Büro und in Bewegung bringt.';

function PressPage() {
  const [copied, setCopied] = usePressState(false);
  const [toast, setToast] = usePressState(null);
  const A = window.A;

  const notify = (msg) => { setToast(msg); setTimeout(() => setToast(null), 2400); };
  const copyBoiler = () => {
    try { navigator.clipboard.writeText(BOILERPLATE); } catch (e) {}
    setCopied(true); setTimeout(() => setCopied(false), 2000);
  };
  const scrollTo = (id) => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });

  return (
    <div className="press" data-screen-label="Presse">
      {/* HERO */}
      <section className="press-hero">
        <div className="press-hero-glow" />
        <div className="press-hero-inner">
          <div className="press-hero-eyebrow">Presse & Newsroom</div>
          <h1>Golf, neu <em>erzählt</em>.</h1>
          <p className="press-hero-sub">
            Material, Zahlen und O-Töne für eure Berichterstattung über Firmengolf — Logos, Fakten,
            Pressemitteilungen und ein direkter Draht zu uns.
          </p>
          <div className="press-hero-ctas">
            <button className="press-btn-light" onClick={() => scrollTo('press-kit')}>
              Medienkit ansehen <ArrowGlyph />
            </button>
            <button className="press-btn-ghost" onClick={() => scrollTo('press-contact')}>Pressekontakt</button>
          </div>
          <div className="press-hero-meta">
            {PRESS_FACTS.map((f, i) => (
              <div key={i}><div className="v">{f.v}</div><div className="l">{f.l}</div></div>
            ))}
          </div>
        </div>
      </section>

      {/* BOILERPLATE */}
      <section className="press-section">
        <div className="press-shead">
          <div>
            <div className="press-eyebrow">Über Firmengolf</div>
            <h2 className="press-h2">Das Unternehmen <em>in Kürze</em></h2>
          </div>
        </div>
        <div className="press-boiler">
          <div className="press-boiler-text">
            <p><strong>Firmengolf macht Golf für Unternehmen zugänglich</strong> — als Firmenevent, als Offsite-Location und als wiederkehrenden Mitarbeiter-Benefit.</p>
            <p>Über eine kuratierte Plattform buchen Firmen Teamevents, Turniere, Schnupperkurse und individuelle Veranstaltungen auf über 180 Partnerplätzen in ganz Deutschland: eine Anfrage, ein Ansprechpartner, eine Rechnung.</p>
            <p>Gegründet 2024 in München, verfolgt Firmengolf ein klares Ziel — Golf nicht als exklusives Statussymbol, sondern als <strong>offenen, gesunden Ausgleich</strong>, der Teams aus dem Büro und in Bewegung bringt.</p>
          </div>
          <div className="press-copybox">
            <div className="press-copybox-h">Boilerplate · druckfertig</div>
            <p>{BOILERPLATE}</p>
            <button className={'press-copy-btn ' + (copied ? 'done' : '')} onClick={copyBoiler}>
              {copied ? '✓ In die Zwischenablage kopiert' : 'Text kopieren'}
            </button>
          </div>
        </div>
      </section>

      {/* FACTS */}
      <section className="press-section tint">
        <div className="press-inner">
          <div className="press-shead">
            <div>
              <div className="press-eyebrow">Zahlen & Fakten</div>
              <h2 className="press-h2">Firmengolf auf einen <em>Blick</em></h2>
            </div>
          </div>
          <div className="press-facts">
            {PRESS_FACTS.map((f, i) => (
              <div className="press-fact" key={i}><div className="v">{f.v}</div><div className="l">{f.l}</div></div>
            ))}
          </div>
        </div>
      </section>

      {/* PRESS RELEASES */}
      <section className="press-section">
        <div className="press-shead">
          <div>
            <div className="press-eyebrow">Pressemitteilungen</div>
            <h2 className="press-h2">Aktuelles aus dem <em>Newsroom</em></h2>
          </div>
        </div>
        <div className="press-releases">
          {PRESS_RELEASES.map((r, i) => (
            <button className="press-release" key={i} onClick={() => notify('Pressemitteilung wird geöffnet (PDF)')}>
              <div>
                <div className="press-release-date">{r.date}</div>
                <div className="press-release-tag">{r.tag}</div>
              </div>
              <div>
                <div className="press-release-t">{r.title}</div>
                <div className="press-release-x">{r.excerpt}</div>
              </div>
              <div className="press-release-link">Lesen <ArrowGlyph size={12} /></div>
            </button>
          ))}
        </div>
      </section>

      {/* COVERAGE */}
      <section className="press-section tint">
        <div className="press-inner">
          <div className="press-shead">
            <div>
              <div className="press-eyebrow">In den Medien</div>
              <h2 className="press-h2">Was über uns <em>geschrieben</em> wird</h2>
            </div>
          </div>
          <div className="press-coverage">
            {PRESS_COVERAGE.map((c, i) => (
              <div className="press-cov" key={i}>
                <div className="press-cov-outlet">{c.outlet}</div>
                <div className="press-cov-q">{c.q}</div>
                <div className="press-cov-meta">{c.meta}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* MEDIA KIT */}
      <section className="press-section" id="press-kit">
        <div className="press-shead">
          <div>
            <div className="press-eyebrow">Medienkit</div>
            <h2 className="press-h2">Logos & <em>Markenmaterial</em></h2>
          </div>
          <button className="fg-btn-brand" onClick={() => notify('Komplettes Medienkit (.zip) wird vorbereitet')}>
            Komplettes Kit (.zip)
          </button>
        </div>
        <div className="press-kit">
          <div className="press-kit-card">
            <div className="press-kit-preview dark"><img src={A('assets/logo/firmengolf-wordmark-light.png')} alt="Firmengolf Logo hell" /></div>
            <div className="press-kit-body">
              <div className="press-kit-t">Logo-Paket</div>
              <div className="press-kit-s">Wortmarke & Bildmarke, hell und dunkel — PNG & SVG.</div>
              <a className="press-kit-dl" href={A('assets/logo/firmengolf-wordmark.png')} download>↓ Logos herunterladen</a>
            </div>
          </div>
          <div className="press-kit-card">
            <div className="press-kit-preview cream">
              <div className="swatches">
                <span className="sw" style={{ background: 'var(--fairway-700)' }} />
                <span className="sw" style={{ background: 'var(--ink-900)' }} />
                <span className="sw" style={{ background: 'var(--paper-100)' }} />
                <span className="sw" style={{ background: 'var(--clay-600)' }} />
              </div>
            </div>
            <div className="press-kit-body">
              <div className="press-kit-t">Marken­richtlinien</div>
              <div className="press-kit-s">Farben, Typografie und korrekte Logo-Verwendung als PDF.</div>
              <a className="press-kit-dl" onClick={() => notify('Markenrichtlinien (PDF) werden geladen')}>↓ Guide (PDF)</a>
            </div>
          </div>
          <div className="press-kit-card">
            <div className="press-kit-preview" style={{ padding: 0 }}>
              <span style={{ width: '100%', height: '100%', backgroundImage: `url('${A('assets/imagery/event-corporate.jpg')}')`, backgroundSize: 'cover', backgroundPosition: 'center' }} />
            </div>
            <div className="press-kit-body">
              <div className="press-kit-t">Pressefotos</div>
              <div className="press-kit-s">Hochauflösende Event- und Platzfotos zur freien Nutzung.</div>
              <a className="press-kit-dl" onClick={() => notify('Pressefotos (.zip) werden vorbereitet')}>↓ Fotopaket (.zip)</a>
            </div>
          </div>
        </div>
      </section>

      {/* FOUNDER QUOTE */}
      <section className="press-section tint">
        <div className="press-inner">
          <div className="press-quote">
            <img className="press-quote-photo" src={A('assets/imagery/avatar-4.jpg')} alt="Julius Klinzer" />
            <div>
              <div className="press-quote-text">
                „Lange dachte ich, Golf spiele ich, wenn ich mal alt bin. Heute ist es das Erste, was ich jedem Team empfehle —
                weil es draußen passiert, jeden mitnimmt und verbindet. Golf ist für jeden da."
              </div>
              <div className="press-quote-by">Julius Klinzer <span>· Gründer, Firmengolf</span></div>
            </div>
          </div>
        </div>
      </section>

      {/* PRESS CONTACT */}
      <section className="press-section" id="press-contact">
        <div className="press-contact">
          <img src={A('assets/imagery/avatar-3.jpg')} alt="Pressekontakt" />
          <div>
            <div className="press-contact-name">Marie Albers</div>
            <div className="press-contact-role">Presse & Kommunikation · Firmengolf</div>
            <div className="press-contact-rows">
              <a href="mailto:presse@firmengolf.de">✉ presse@firmengolf.de</a>
              <a href="tel:+49891234560">☏ +49 89 1234 56-0</a>
            </div>
          </div>
          <a className="press-btn-light" href="mailto:presse@firmengolf.de">Anfrage senden <ArrowGlyph /></a>
        </div>
      </section>

      {toast && (
        <div style={{ position: 'fixed', bottom: 24, left: '50%', transform: 'translateX(-50%)', zIndex: 90, background: 'var(--ink-900)', color: 'var(--paper-100)', fontSize: 14, fontWeight: 500, padding: '12px 20px', borderRadius: 999, boxShadow: 'var(--shadow-lg)' }}>
          ✓ {toast}
        </div>
      )}
    </div>
  );
}

window.PressPage = PressPage;
