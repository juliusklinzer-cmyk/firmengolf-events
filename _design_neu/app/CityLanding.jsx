/* eslint-disable */
// =============================================================
// CityLanding — SEO landing template for major German cities.
// Route: #/golf-events/<slug>  (e.g. #/golf-events/muenchen)
// Pulls partner courses + event formats from window.SITE_DATA by
// venue, injects real on-page SEO (title, meta, canonical, JSON-LD
// Service + FAQPage), and shows a visible SEO annotation for the
// code hand-off. Exposes window.CityPage.
// =============================================================
var { useState: useCityState, useEffect: useCityEffect, useMemo: useCityMemo } = React;

// ---- per-city content (the only thing a CMS would feed in code) ----
const CITIES = {
  muenchen: {
    slug: 'muenchen', name: 'München', inCity: 'in München', region: 'Süd',
    venues: ['München-Riem', 'GC München West'],
    hero: 'assets/imagery/hero-fairway-wide.jpg',
    metaTitle: 'Firmenevents auf dem Golfplatz in München | Firmengolf',
    metaDesc: 'Firmen-Golfevents in München & Umgebung — Teamevent, Firmenturnier, Schnupperkurs oder Sommerfest auf Partnerplätzen wie Eichenried und Riem. Eine Anfrage, ein Ansprechpartner.',
    sub: 'Vom Schnupperkurs bis zum Firmenturnier — wir richten euer Firmenevent auf den schönsten Golfplätzen rund um München aus. Eine Anfrage, ein Ansprechpartner, eine Rechnung.',
    intro: [
      'München ist Firmenstandort und Naherholung in einem — und kaum eine Stadt hat so viele Championship-Plätze direkt vor der Tür. Innerhalb von 30 Minuten seid ihr von der Innenstadt im Grünen, zwischen Eichen, Isar-Auen und Alpenpanorama.',
      'Ob **Teamevent**, **Firmenturnier**, **Kundenevent** oder **Sommerfest**: Wir kennen die passenden Plätze im Münchner Umland, kümmern uns um Coaching, Catering und Ablauf — und ihr seid am Ende einfach nur Gastgeber.',
    ],
    reasons: [
      { ic: 'clock', t: '30 Min. ins Grüne', b: 'Die besten Plätze liegen stadtnah — Eichenried, Riem & Co. sind schnell erreichbar, auch mit der S-Bahn.' },
      { ic: 'users', t: 'Für jedes Team', b: 'Von kompletten Einsteigenden bis zu Stammspielern — wir stellen jedes Format passend zusammen.' },
      { ic: 'mountain', t: 'Bergpanorama inklusive', b: 'An klaren Tagen spielt ihr mit Blick auf die Alpen — ein Erlebnis, das in Erinnerung bleibt.' },
      { ic: 'flag', t: 'Lokale Partnerplätze', b: 'Wir arbeiten direkt mit den Clubs im Münchner Raum — kurze Wege, verlässliche Termine.' },
    ],
    faqs: [
      { q: 'Welche Golfplätze in München kann ich für ein Firmenevent buchen?', a: 'Rund um München arbeiten wir mit mehreren Partnerplätzen, u. a. in Eichenried und Riem. Je nach Gruppengröße, Anlass und Wunschtermin schlagen wir euch die passenden Plätze vor — alle innerhalb von rund 30 Minuten Fahrzeit vom Stadtkern.' },
      { q: 'Können auch Anfänger ohne Golferfahrung mitmachen?', a: 'Ja — die meisten unserer Münchner Formate sind genau für Teams gedacht, in denen kaum jemand schon gespielt hat. Schläger werden gestellt, ein PGA-Pro führt euch an, und es geht um den gemeinsamen Nachmittag, nicht um Handicaps.' },
      { q: 'Wie groß darf die Gruppe sein?', a: 'Vom Coaching für zwei Personen bis zum Firmenturnier mit 80 Teilnehmenden ist alles möglich. Sag uns einfach eure Gruppengröße in der Anfrage, dann wählen wir Platz und Format passend aus.' },
      { q: 'Wie schnell bekomme ich eine Rückmeldung?', a: 'Nach eurer Anfrage melden wir uns innerhalb von 24 Stunden mit konkreten Vorschlägen für Platz, Format und Termin in München.' },
    ],
  },
  hamburg: {
    slug: 'hamburg', name: 'Hamburg', inCity: 'in Hamburg', region: 'Nord',
    venues: ['Hamburg-Wendlohe', 'Schloss Lüdersburg'],
    hero: 'assets/imagery/hero-meadow.jpg',
    metaTitle: 'Firmenevents auf dem Golfplatz in Hamburg | Firmengolf',
    metaDesc: 'Firmen-Golfevents in Hamburg & Umgebung — Teamevent, Schnupperkurs, Offsite oder Firmenturnier auf Partnerplätzen wie Wendlohe und Schloss Lüdersburg. Eine Anfrage, ein Ansprechpartner.',
    sub: 'Raus aus dem Büro, rein ins Grüne — wir richten euer Firmenevent auf den schönsten Golfplätzen rund um Hamburg aus. Eine Anfrage, ein Ansprechpartner, eine Rechnung.',
    intro: [
      'Hamburg lebt vom Wasser und vom Wind — und genau das macht Golf hier besonders. Die Plätze im Hamburger Umland liegen zwischen Knicks, Wiesen und alten Alleen, viele davon nur eine kurze Fahrt vom Zentrum entfernt.',
      'Ob **Teamevent** nach Feierabend, **Schnupperkurs** für die ganze Abteilung oder mehrtägiges **Offsite** mit Übernachtung im Schlosshotel: Wir kennen die passenden Plätze im Norden und organisieren euer Event von A bis Z.',
    ],
    reasons: [
      { ic: 'clock', t: 'Stadtnah & erreichbar', b: 'Wendlohe liegt im Norden Hamburgs — schnell erreichbar, ideal für ein Event nach Feierabend.' },
      { ic: 'users', t: 'Für jedes Team', b: 'Von Einsteigenden bis Fortgeschrittenen — wir stellen jedes Format passend zusammen.' },
      { ic: 'castle', t: 'Offsite im Schloss', b: 'Schloss Lüdersburg verbindet Tagung, Golf und Übernachtung — perfekt für Strategie-Tage.' },
      { ic: 'flag', t: 'Lokale Partnerplätze', b: 'Wir arbeiten direkt mit den Clubs im Hamburger Raum — kurze Wege, verlässliche Termine.' },
    ],
    faqs: [
      { q: 'Welche Golfplätze in Hamburg kann ich für ein Firmenevent buchen?', a: 'Im Hamburger Raum arbeiten wir u. a. mit dem GC Hamburg-Wendlohe und dem Resort Schloss Lüdersburg bei Lüneburg. Je nach Anlass, Gruppe und Termin schlagen wir euch den passenden Platz vor.' },
      { q: 'Können auch Anfänger ohne Golferfahrung mitmachen?', a: 'Ja — unsere Hamburger Formate sind genau für Teams ohne Vorerfahrung gedacht. Schläger werden gestellt, ein PGA-Pro führt euch an, und der gemeinsame Nachmittag steht im Vordergrund.' },
      { q: 'Geht auch ein mehrtägiges Offsite mit Übernachtung?', a: 'Ja. Schloss Lüdersburg verbindet Tagungsräume, Golf und Hotel an einem Ort — ideal für ein Strategie-Offsite mit Übernachtung. Wir planen Ablauf, Verpflegung und Golfprogramm gemeinsam mit euch.' },
      { q: 'Wie schnell bekomme ich eine Rückmeldung?', a: 'Nach eurer Anfrage melden wir uns innerhalb von 24 Stunden mit konkreten Vorschlägen für Platz, Format und Termin in Hamburg.' },
    ],
  },
  koeln: {
    slug: 'koeln', name: 'Köln', inCity: 'in Köln', region: 'West',
    venues: ['Köln-Hahnwald', 'Köln-Refrath'],
    hero: 'assets/imagery/event-corporate.jpg',
    metaTitle: 'Firmenevents auf dem Golfplatz in Köln | Firmengolf',
    metaDesc: 'Firmen-Golfevents in Köln & Umgebung — Firmenturnier, Kundenevent, Teamevent oder Charity-Cup auf Partnerplätzen wie Hahnwald und Refrath. Eine Anfrage, ein Ansprechpartner.',
    sub: 'Vom Kundenevent bis zum Firmenturnier — wir richten euer Firmenevent auf den schönsten Golfplätzen rund um Köln aus. Eine Anfrage, ein Ansprechpartner, eine Rechnung.',
    intro: [
      'Köln ist Messe- und Medienstadt — und ein perfekter Ort, um Kunden und Teams einmal ganz anders zusammenzubringen. Die Plätze im Kölner Süden und im Bergischen liegen nah an der Stadt und doch mitten im Grünen.',
      'Ob **Firmenturnier** mit Siegerehrung, **Kundenevent** mit Dinner oder **Charity-Cup** für den guten Zweck: Wir kennen die passenden Plätze im Rheinland und organisieren euer Event von der ersten Idee bis zur Rechnung.',
    ],
    reasons: [
      { ic: 'clock', t: 'Nah an der Stadt', b: 'Hahnwald und Refrath liegen stadtnah — ideal für Events mit Kunden aus der Region.' },
      { ic: 'users', t: 'Für jedes Team', b: 'Von Einsteigenden bis Fortgeschrittenen — wir stellen jedes Format passend zusammen.' },
      { ic: 'gift', t: 'Kunden & Charity', b: 'Vom Hospitality-Tag bis zum Charity-Cup mit Spendentopf — wir setzen euer Anliegen in Szene.' },
      { ic: 'flag', t: 'Lokale Partnerplätze', b: 'Wir arbeiten direkt mit den Clubs im Rheinland — kurze Wege, verlässliche Termine.' },
    ],
    faqs: [
      { q: 'Welche Golfplätze in Köln kann ich für ein Firmenevent buchen?', a: 'Im Kölner Raum arbeiten wir u. a. mit dem GC Köln-Hahnwald und dem GC Köln-Refrath. Abhängig von Anlass, Gruppengröße und Termin schlagen wir euch den passenden Platz vor.' },
      { q: 'Eignet sich Golf für ein Kundenevent?', a: 'Sehr gut sogar — ein paar entspannte Stunden auf dem Platz schaffen Gespräche, die im Konferenzraum nie entstehen. Wir kombinieren das gern mit Catering, Dinner oder einem kleinen Wettbewerb.' },
      { q: 'Können auch Anfänger ohne Golferfahrung mitmachen?', a: 'Ja — Schläger werden gestellt, ein PGA-Pro führt an, und niemand muss vorher gespielt haben. Es geht um das gemeinsame Erlebnis, nicht um Handicaps.' },
      { q: 'Wie schnell bekomme ich eine Rückmeldung?', a: 'Nach eurer Anfrage melden wir uns innerhalb von 24 Stunden mit konkreten Vorschlägen für Platz, Format und Termin in Köln.' },
    ],
  },
};
const CITY_ORDER = ['muenchen', 'hamburg', 'koeln'];

// ---- small icon set for the "reasons" row ----
function CityIco({ name }) {
  const p = {
    clock:    <><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></>,
    users:    <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></>,
    mountain: <><path d="M3 20l6.5-11 4 6 2-3L21 20z"/></>,
    flag:     <><path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/></>,
    castle:   <><path d="M4 21V8l2 1V5l2 1V4l2 1V4l2-1v2l2-1v2l2-1v4l2-1v13z"/><path d="M10 21v-4h4v4"/></>,
    gift:     <><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9"/><path d="M12 8S10.5 3 8 4.5 9.5 8 12 8zM12 8s1.5-5 4-3.5S14.5 8 12 8z"/></>,
  };
  return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{p[name]}</svg>;
}

// ---- SEO head injection ----
function useCitySEO(city, events, faqs, courses) {
  useCityEffect(() => {
    const prevTitle = document.title;
    document.title = city.metaTitle;
    const setMeta = (sel, attr, val) => {
      let el = document.head.querySelector(sel);
      if (!el) { el = document.createElement('meta'); el.setAttribute(sel.includes('property') ? 'property' : 'name', attr); document.head.appendChild(el); }
      el.setAttribute('content', val);
      return el;
    };
    const desc = setMeta('meta[name="description"]', 'description', city.metaDesc);
    const ogt = setMeta('meta[property="og:title"]', 'og:title', city.metaTitle);
    const ogd = setMeta('meta[property="og:description"]', 'og:description', city.metaDesc);
    let canon = document.head.querySelector('link[rel="canonical"]');
    if (!canon) { canon = document.createElement('link'); canon.setAttribute('rel', 'canonical'); document.head.appendChild(canon); }
    canon.setAttribute('href', 'https://firmengolf.de/golf-events/' + city.slug);

    const ld = document.createElement('script');
    ld.type = 'application/ld+json';
    ld.id = '__city-jsonld';
    ld.textContent = JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'Service', serviceType: 'Firmen-Golfevents',
          name: 'Firmenevents auf dem Golfplatz ' + city.inCity,
          areaServed: { '@type': 'City', name: city.name },
          provider: { '@type': 'Organization', name: 'Firmengolf' },
          description: city.metaDesc,
        },
        {
          '@type': 'FAQPage',
          mainEntity: faqs.map(f => ({ '@type': 'Question', name: f.q, acceptedAnswer: { '@type': 'Answer', text: f.a } })),
        },
      ],
    });
    document.head.appendChild(ld);

    return () => {
      document.title = prevTitle;
      ld.remove();
    };
  }, [city.slug]);
}

function CityPage({ citySlug }) {
  const city = CITIES[citySlug];
  const [wizard, setWizard] = useCityState(null);
  const [openFaq, setOpenFaq] = useCityState(0);
  const [seoOpen, setSeoOpen] = useCityState(false);
  const RequestWizard = window.RequestWizard;
  const D = window.SITE_DATA;

  // derive events + courses for this city (must be before any early return for hooks order)
  const events = useCityMemo(() => city ? (D.events || []).filter(e => city.venues.includes(e.venue)) : [], [citySlug]);
  const courses = useCityMemo(() => {
    if (!city) return [];
    return city.venues.map(v => {
      const evs = (D.events || []).filter(e => e.venue === v);
      if (!evs.length) return null;
      const avg = (evs.reduce((s, e) => s + (e.rating || 0), 0) / evs.length).toFixed(1);
      return { venue: v, region: evs[0].region, img: evs[0].heroImage, count: evs.length, rating: avg };
    }).filter(Boolean);
  }, [citySlug]);

  useCitySEO(city || CITIES.muenchen, events, (city || CITIES.muenchen).faqs, courses);

  if (!city) {
    return (
      <div className="ev-notfound">
        <div className="mk-eyebrow">404</div>
        <h1 className="display-md">Diesen Standort gibt's (noch) nicht.</h1>
        <div className="city-links" style={{ justifyContent: 'center', marginTop: 20 }}>
          {CITY_ORDER.map(s => <a key={s} className="city-link-pill" href={'#/golf-events/' + s} onClick={(e) => go('#/golf-events/' + s, e)}>Golf-Events in {CITIES[s].name}</a>)}
        </div>
      </div>
    );
  }

  const open = (preset) => setWizard({ preset: preset || null });
  const others = CITY_ORDER.filter(s => s !== citySlug);

  return (
    <div className="city-wrap" data-screen-label={'Standort · ' + city.name}>
      {/* HERO */}
      <section className="city-hero">
        <div className="city-hero-photo" style={{ backgroundImage: `url('${window.A(city.hero)}')` }}>
          <div className="city-hero-scrim" />
          <div className="city-hero-inner">
            <div className="city-hero-eyebrow"><CityIco name="flag" /> Firmengolf {city.inCity}</div>
            <h1 className="city-h1">Firmenevents auf dem <em>Golfplatz</em> {city.inCity}.</h1>
            <p className="city-hero-sub">{city.sub}</p>
            <div className="city-hero-ctas">
              <button className="fg-btn-ink lg" onClick={() => open({ city: city.name })}>
                Anfrage starten <span className="fg-arrow"><ArrowGlyph /></span>
              </button>
              <a className="fg-btn-ghost-light" href="#formate" onClick={(e) => { e.preventDefault(); document.getElementById('city-formate')?.scrollIntoView({ behavior: 'smooth' }); }}>
                Formate ansehen →
              </a>
            </div>
            <div className="city-hero-stats">
              <div><div className="city-stat-v">{courses.length}</div><div className="city-stat-l">Partnerplätze {city.inCity}</div></div>
              <div><div className="city-stat-v">{events.length}</div><div className="city-stat-l">Buchbare Formate</div></div>
              <div><div className="city-stat-v">&lt; 24 h</div><div className="city-stat-l">Antwort auf jede Anfrage</div></div>
            </div>
          </div>
        </div>
      </section>

      {/* INTRO */}
      <section className="city-section">
        <div className="city-prose">
          <div className="city-eyebrow">Golf für Unternehmen {city.inCity}</div>
          <h2 className="city-h2">Euer Firmenevent — raus aus dem Büro, rein in die <em>Natur</em>.</h2>
          {city.intro.map((p, i) => <p key={i} dangerouslySetInnerHTML={{ __html: p.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>') }} />)}
        </div>
      </section>

      {/* COURSES */}
      <section className="city-section tint">
        <div className="city-inner">
          <div className="city-head">
            <div className="city-eyebrow">Partnerplätze</div>
            <h2 className="city-h2">Golfplätze in und um {city.name}</h2>
            <p className="city-lead">Sorgfältig ausgewählte Clubs in eurer Region — wir schlagen euch je nach Anlass und Gruppe den passenden Platz vor.</p>
          </div>
          <div className="city-courses">
            {courses.map(c => (
              <button key={c.venue} className="city-course" onClick={() => go('#/events?region=' + encodeURIComponent(city.region))}>
                <span className="city-course-photo" style={{ backgroundImage: `url('${window.A(c.img)}')` }} />
                <span className="city-course-body">
                  <span className="city-course-name">{c.venue}</span>
                  <span className="city-course-meta">Region {c.region} · {c.count} {c.count === 1 ? 'Format' : 'Formate'}</span>
                  <span className="city-course-foot">
                    <span className="city-course-rating"><StarGlyph size={13} /> {c.rating}</span>
                    <span className="city-course-link">Events ansehen <ArrowGlyph size={12} /></span>
                  </span>
                </span>
              </button>
            ))}
          </div>
        </div>
      </section>

      {/* FORMATS */}
      <section className="city-section" id="city-formate">
        <div className="city-head">
          <div className="city-eyebrow">Beliebte Formate</div>
          <h2 className="city-h2">Event-Formate {city.inCity}</h2>
          <p className="city-lead">Direkt buchbar oder als Startpunkt für euer individuelles Event — alles findet auf dem Golfplatz statt.</p>
        </div>
        <div className="city-formats-grid">
          {events.slice(0, 6).map(e => (
            <EventCard key={e.id} event={e} onClick={() => go('#/events/' + e.slug)} />
          ))}
        </div>
      </section>

      {/* REASONS */}
      <section className="city-section tint">
        <div className="city-inner">
          <div className="city-head">
            <div className="city-eyebrow">Warum {city.name}</div>
            <h2 className="city-h2">Was ein Golfevent {city.inCity} besonders macht</h2>
          </div>
          <div className="city-reasons">
            {city.reasons.map((r, i) => (
              <div className="city-reason" key={i}>
                <div className="city-reason-ic"><CityIco name={r.ic} /></div>
                <div className="city-reason-t">{r.t}</div>
                <div className="city-reason-b">{r.b}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section className="city-section">
        <div className="city-head">
          <div className="city-eyebrow">Häufige Fragen</div>
          <h2 className="city-h2">Golfevents {city.inCity} — <em>kurz erklärt</em></h2>
        </div>
        <div className="city-faq">
          {city.faqs.map((f, i) => (
            <div className={'city-faq-item ' + (openFaq === i ? 'open' : '')} key={i}>
              <button className="city-faq-q" onClick={() => setOpenFaq(openFaq === i ? -1 : i)}>
                {f.q}
                <svg className="chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <div className="city-faq-a"><p>{f.a}</p></div>
            </div>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="city-cta">
        <div className="city-cta-card" style={{ backgroundImage: `url('${window.A('assets/imagery/hero-forest.jpg')}')` }}>
          <div className="city-cta-scrim" />
          <div className="city-cta-inner">
            <h2 className="city-cta-h">Plant ihr ein Firmenevent {city.inCity}?</h2>
            <p className="city-cta-p">Sag uns kurz, was ihr vorhabt — wir melden uns innerhalb von 24 Stunden mit passenden Plätzen und einem Richtpreis. Unverbindlich.</p>
            <button className="fg-btn-ink lg" onClick={() => open({ city: city.name })} style={{ background: 'var(--paper-100)', color: 'var(--fairway-900)' }}>
              Anfrage starten <span className="fg-arrow" style={{ background: 'var(--fairway-200)' }}><ArrowGlyph /></span>
            </button>
            <div className="city-links">
              {others.map(s => (
                <a key={s} className="city-link-pill" href={'#/golf-events/' + s} onClick={(e) => go('#/golf-events/' + s, e)}>
                  <CityIco name="flag" /> Golf-Events in {CITIES[s].name}
                </a>
              ))}
              <a className="city-link-pill" href="#/events" onClick={(e) => go('#/events', e)}>Alle Events ansehen →</a>
            </div>
          </div>
        </div>
      </section>

      {/* SEO annotation (visible for code hand-off) */}
      <section className="city-seo">
        <div className="city-seo-card">
          <button className="city-seo-toggle" onClick={() => setSeoOpen(!seoOpen)}>
            <span className="badge">SEO</span>
            On-Page-Struktur dieser Seite {seoOpen ? '▲' : '▼'}
          </button>
          {seoOpen && (
            <div className="city-seo-body">
              <div className="city-seo-row"><span className="k">&lt;title&gt;</span><span className="v">{city.metaTitle}</span></div>
              <div className="city-seo-row"><span className="k">URL / canonical</span><span className="v">/golf-events/{city.slug}</span></div>
              <div className="city-seo-row"><span className="k">meta description</span><span className="v">{city.metaDesc}</span></div>
              <div className="city-seo-row"><span className="k">H1</span><span className="v">Firmenevents auf dem Golfplatz {city.inCity}.</span></div>
              <div className="city-seo-row"><span className="k">Strukturierte Daten (JSON-LD)</span><span className="v">Service · areaServed: {city.name} + FAQPage ({city.faqs.length} Fragen)</span></div>
              <div className="city-seo-row"><span className="k">Interne Links</span><span className="v">{others.map(s => '/golf-events/' + s).join(' · ')} · /events</span></div>
              <div className="city-seo-note">Im Code wird diese Vorlage je Stadt aus echten Platzdaten generiert (eigene URL, Sitemap-Eintrag, lokale Inhalte). Diese Box ist nur eine Annotation für den Hand-off — im Live-Build entfällt sie.</div>
            </div>
          )}
        </div>
      </section>

      {wizard && RequestWizard && (
        <RequestWizard mode="full" preset={wizard.preset} onClose={() => setWizard(null)} />
      )}
    </div>
  );
}

window.CityPage = CityPage;
