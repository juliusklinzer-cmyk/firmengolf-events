/* eslint-disable */
// =============================================================
// HomePage — Startseite for Firmenevents.
// Structure: Hero · How it works · Featured formats · Numbers
//          · Individual events teaser · Benefit CTA · Testimonial
//          · Blog teaser · Partners strip · Closing CTA
// =============================================================

// (asset helper provided as window.A)

// Rotating final word in the hero headline. Fades + lifts gently (brand motion:
// calm ease-out, no bounce).
function RotatingWord() {
  const words = ['Bewegung', 'neue Energie', 'den Austausch', 'frische Luft'];
  const [i, setI] = React.useState(0);
  const [show, setShow] = React.useState(true);

  React.useEffect(() => {
    let outTimer;
    const cycle = setInterval(() => {
      setShow(false);
      outTimer = setTimeout(() => {
        setI(prev => (prev + 1) % words.length);
        setShow(true);
      }, 380);
    }, 2600);
    return () => { clearInterval(cycle); clearTimeout(outTimer); };
  }, []);

  return (
    <span className="rot-wrap">
      <span className={'rot-word mk-italic ' + (show ? 'in' : 'out')} key={i}>
        {words[i]}
      </span>
      <span className="rot-dot">.</span>
    </span>
  );
}

function HomeHero() {
  return (
    <section className="mk-hero" data-screen-label="Home Hero">
      <div className="mk-hero-photo" style={{ backgroundImage: `url('${window.A('assets/imagery/hero-fairway-wide.jpg')}')` }}>
        <div className="mk-hero-scrim" />
        <div className="mk-hero-content">
          <div className="mk-hero-eyebrow">Firmenevents · Golf für Unternehmen</div>
          <h1 className="mk-hero-title">
            Bringt euer Team raus aus dem Büro und rein in <RotatingWord />
          </h1>
          <p className="mk-hero-sub">
            Vom Schnupperkurs bis zum Firmenturnier — kuratierte Veranstaltungstypen auf Partnerplätzen
            in ganz Deutschland. Eine Anfrage, eine Rechnung, ein Ansprechpartner.
          </p>
          <div className="mk-hero-ctas">
            <a className="fg-btn-ink lg" href="#/events" onClick={(e) => go('#/events', e)}>
              Events entdecken
              <span className="fg-arrow"><ArrowGlyph /></span>
            </a>
            <a className="fg-btn-ghost-light" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
              Individuelles Event planen →
            </a>
          </div>
          <div className="mk-hero-trust">
            <span>Keine Mitgliedschaft nötig</span>
            <span className="mk-hero-trust-dot" />
            <span>Schläger werden gestellt</span>
            <span className="mk-hero-trust-dot" />
            <span>Antwort in 24 Stunden</span>
          </div>
        </div>
      </div>

      {/* Floating chip in top-right */}
      <div className="mk-hero-floating">
        <div className="mk-floating-thumb" style={{ backgroundImage: `url('${window.A('assets/imagery/event-team.jpg')}')` }} />
        <div>
          <div className="mk-floating-chip">12 Sommer-Slots im Juni</div>
          <div className="mk-floating-meta">Hamburg · München · Berlin · Köln</div>
        </div>
      </div>

      {/* Shared search bar — identical to the Events page; navigates with params */}
      <HomeSearch />
    </section>
  );
}

function HomeSearch() {
  const [format, setFormat] = React.useState('all');
  const [origin, setOrigin] = React.useState(null);
  const [radius, setRadius] = React.useState(50);
  const [groupBand, setGroupBand] = React.useState('any');

  const search = () => {
    const p = [];
    if (format !== 'all') p.push('format=' + encodeURIComponent(format));
    if (origin && origin.lat != null) {
      p.push('ort=' + encodeURIComponent(origin.label));
      p.push('radius=' + radius);
    }
    if (groupBand !== 'any') p.push('group=' + groupBand);
    window.location.hash = '#/events' + (p.length ? '?' + p.join('&') : '');
    window.scrollTo({ top: 0, behavior: 'instant' });
  };

  const Bar = window.EventSearchBar;
  return (
    <div className="home-searchwrap">
      {Bar && (
        <Bar
          format={format} setFormat={setFormat}
          origin={origin} setOrigin={setOrigin}
          radius={radius} setRadius={setRadius}
          groupBand={groupBand} setGroupBand={setGroupBand}
          onSearch={search}
        />
      )}
    </div>
  );
}

function PartnersStrip() {
  const d = window.SITE_DATA;
  return (
    <section className="mk-partners">
      <div className="mk-eyebrow">Schon mit uns draußen gewesen</div>
      <div className="mk-partners-row">
        {d.partners.map(p => <span key={p} className="mk-partner-logo">{p}</span>)}
      </div>
    </section>
  );
}

// The emotional core — sell the feeling, not the product (brand voice).
function ExperienceSection() {
  const points = [
    { k: 'Bewegung',     t: 'Vier, fünf Kilometer an der frischen Luft — ohne dass es sich nach Sport anfühlt.', img: 'assets/imagery/golf-range.jpg' },
    { k: 'Natur',        t: 'Grün, Weite, Himmel — die perfekte Ergänzung zu einem Tag voller Gespräche.',               img: 'assets/imagery/golf-green-flag.jpg' },
    { k: 'Konzentration', t: 'Ein Spiel, das ganz im Moment verlangt — und genau dadurch den Kopf frei macht.',  img: 'assets/imagery/golf-bunker.jpg' },
    { k: 'Zusammenhalt', t: 'Vier Stunden Seite an Seite, ohne Bildschirm. Teams wachsen hier unangestrengt zusammen.', img: 'assets/imagery/golf-team.jpg' },
  ];
  return (
    <section className="home-exp">
      <div className="home-exp-inner">
        <div className="home-exp-head">
          <div className="mk-eyebrow">Warum Golf</div>
          <h2 className="home-exp-h">
            Es geht nicht ums Golf. Es geht um das, was <span className="mk-italic">dabei</span> passiert.
          </h2>
          <p className="home-exp-lead">
            Niemand muss spielen können. Verbindet euer Meeting mit ein paar Stunden draußen —
            und merkt, wie viel leichter Gespräche laufen, wenn zwischendurch Bewegung dazukommt.
          </p>
        </div>
        <div className="home-exp-cards">
          {points.map(p => (
            <article className="home-exp-card" key={p.k}>
              <div className="home-exp-card-photo" style={{ backgroundImage: `url('${window.A(p.img)}')` }} />
              <div className="home-exp-card-body">
                <div className="home-exp-k">{p.k}</div>
                <p>{p.t}</p>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

function HowItWorks() {
  const d = window.SITE_DATA;
  return (
    <section className="mk-section mk-steps">
      <div className="mk-section-head">
        <div className="mk-eyebrow">So funktioniert's</div>
        <h2 className="mk-h2">Drei Schritte. Ein Ansprechpartner.</h2>
        <p className="mk-sub">Wir kümmern uns um Platzwahl, Koordination und Abrechnung. Du kümmerst dich ums Team.</p>
      </div>
      <div className="mk-steps-grid">
        {d.steps.map(s => (
          <div className="mk-step" key={s.n}>
            <div className="mk-step-n">{s.n}</div>
            <h3 className="mk-step-t">{s.title}</h3>
            <p className="mk-step-b">{s.body}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

function FeaturedFormats() {
  const d = window.SITE_DATA;
  // Pick 4 distinct format types for variety
  const featured = [
    d.events.find(e => e.id === 'e1'),  // schnupperkurs
    d.events.find(e => e.id === 'e2'),  // firmenturnier (dark)
    d.events.find(e => e.id === 'e8'),  // offsite
    d.events.find(e => e.id === 'e5'),  // incentive (dark)
  ].filter(Boolean);
  return (
    <section className="mk-section">
      <div className="mk-section-head between">
        <div>
          <div className="mk-eyebrow">Kuratierte Veranstaltungstypen</div>
          <h2 className="mk-h2">Vom Schnupperkurs bis zum Firmenturnier.</h2>
        </div>
        <a className="fg-btn-ghost" href="#/events" onClick={(e) => go('#/events', e)}>
          Alle Events ansehen <ArrowGlyph size={12} />
        </a>
      </div>
      <div className="home-formats-grid">
        {featured.map((f, i) => (
          <article key={f.id}
                   className={'mk-format ' + (f.accent === 'dark' ? 'is-dark' : '')}
                   onClick={() => go('#/events/' + f.slug)}>
            <div className="mk-format-photo" style={{ backgroundImage: `url('${window.A(f.heroImage)}')` }}>
              <span className="mk-format-tag">{f.formatLabel}</span>
              <span className="mk-format-eyebrow">{f.duration} · bis {f.groupMax} Gäste</span>
            </div>
            <div className="mk-format-body">
              <h3 className="mk-format-t">{f.title}</h3>
              <p className="mk-format-desc">{f.summary.split('. ')[0]}.</p>
              <div className="mk-format-foot">
                <span className="mk-format-price">
                  ab {f.pricePerPerson ? '€' + f.pricePerPerson + ' p.P.' : '€' + f.pricePerGroup + ' / Gruppe'}
                </span>
                <span className="mk-format-arrow" aria-hidden><ArrowGlyph size={14} /></span>
              </div>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

function Numbers() {
  const d = window.SITE_DATA;
  return (
    <section className="home-numbers">
      <div className="home-numbers-inner">
        <div className="home-numbers-aside">
          <div className="mk-eyebrow" style={{ color: 'var(--fairway-300)' }}>In Zahlen</div>
          <h2 className="home-numbers-h">
            Was wir bisher <span className="mk-italic">gebaut</span> haben.
          </h2>
          <p className="home-numbers-p">
            Drei Jahre, ein einziges Versprechen — Firmenevents auf Golfplätzen, die niemand vergisst.
            Stand: Mai 2026.
          </p>
        </div>
        <div className="home-numbers-grid">
          {d.numbers.map((n, i) => (
            <div key={i} className="home-num">
              <div className="home-num-v">{n.v}</div>
              <div className="home-num-l">{n.l}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function IndividualTeaser() {
  return (
    <section className="mk-section home-individual">
      <div className="home-individual-grid">
        <div className="home-ind-photo" style={{ backgroundImage: `url('${window.A('assets/imagery/golf-approach.jpg')}')` }} />
        <div className="home-ind-text">
          <div className="mk-eyebrow">Individuelle Events</div>
          <h2 className="mk-h2">
            Nichts dabei? <span className="mk-italic">Wir planen</span> dein Event nach deinen Ansprüchen.
          </h2>
          <p className="mk-sub">
            Sonderwünsche, eigene Location, mehrtägiges Programm, internationale Gruppe — beschreib uns kurz,
            was du vorhast. Wir bauen euren Veranstaltungstyp und schlagen die passenden Plätze vor.
          </p>
          <div className="home-ind-points">
            <div><CheckGlyph /><span>Persönliche Beratung in 24 h</span></div>
            <div><CheckGlyph /><span>Maßgeschneidertes Programm</span></div>
            <div><CheckGlyph /><span>Ein Ansprechpartner, eine Rechnung</span></div>
          </div>
          <div className="home-ind-ctas">
            <a className="fg-btn-brand" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
              Event anfragen
              <span className="fg-arrow"><ArrowGlyph /></span>
            </a>
            <a className="fg-btn-ghost" href="#/kontakt" onClick={(e) => go('#/kontakt', e)}>
              Mit uns sprechen
            </a>
          </div>
        </div>
      </div>
    </section>
  );
}

function BenefitTeaser() {
  return (
    <section className="home-benefit">
      <div className="home-benefit-inner">
        <div className="home-benefit-eyebrow">Corporate Benefit · firmen.golf</div>
        <h2 className="home-benefit-h">
          Golf als Benefit, den deine Mitarbeitenden <span className="mk-italic">spüren</span>.
        </h2>
        <p className="home-benefit-sub">
          50 € steuerfreier Sachbezug pro Monat, Zugang zu Partnerplätzen, Coaching-Stunden zum Mitarbeiterpreis.
          Bewegung statt Obstkorb — und die HR-Abrechnung läuft sauber.
        </p>
        <div className="home-benefit-ctas">
          <a className="fg-btn-ink lg" href="https://firmen.golf" target="_blank" rel="noopener noreferrer"
             style={{ background: 'var(--paper-100)', color: 'var(--fairway-900)' }}>
            Zum Benefit-Programm
            <span className="fg-arrow" style={{ background: 'var(--fairway-200)' }}>
              <ArrowGlyph />
            </span>
          </a>
          <span className="home-benefit-tag">firmen.golf ↗</span>
        </div>
      </div>
    </section>
  );
}

function Testimonials() {
  const d = window.SITE_DATA;
  return (
    <section className="mk-section home-quotes">
      <div className="mk-section-head">
        <div className="mk-eyebrow">Was Teams sagen</div>
        <h2 className="mk-h2">Der beste Beweis ist der Montag danach.</h2>
      </div>
      <div className="home-quotes-grid">
        {d.testimonials.map((t, i) => (
          <figure className={'home-quote ' + (i === 0 ? 'is-lead' : '')} key={i}>
            <div className="home-quote-stars" aria-label="5 von 5">
              {[0,1,2,3,4].map(s => <StarGlyph key={s} size={15} />)}
            </div>
            <blockquote>{t.quote}</blockquote>
            <figcaption>
              <img src={window.A(t.photo)} alt="" />
              <div>
                <div className="home-quote-name">{t.name}</div>
                <div className="home-quote-role">{t.role}</div>
              </div>
            </figcaption>
          </figure>
        ))}
      </div>
    </section>
  );
}

function BlogTeaser() {
  const d = window.SITE_DATA;
  const posts = d.posts.slice(0, 3);
  return (
    <section className="mk-section">
      <div className="mk-section-head between">
        <div>
          <div className="mk-eyebrow">Aus dem Magazin</div>
          <h2 className="mk-h2">Was wir grad denken & schreiben.</h2>
        </div>
        <a className="fg-btn-ghost" href="#/blog" onClick={(e) => go('#/blog', e)}>
          Alle Artikel <ArrowGlyph size={12} />
        </a>
      </div>
      <div className="home-blog-grid">
        {posts.map(p => (
          <article key={p.slug} className="home-blog-card"
                   onClick={() => go('#/blog/' + p.slug)}>
            <div className="home-blog-photo" style={{ backgroundImage: `url('${window.A(p.image)}')` }} />
            <div className="home-blog-body">
              <div className="home-blog-meta"><span className="home-blog-tag">{p.tag}</span><span>·</span><span>{p.readTime}</span></div>
              <h3 className="home-blog-t">{p.title}</h3>
              <p className="home-blog-x">{p.excerpt}</p>
              <div className="home-blog-author">
                <span className="home-blog-author-name">{p.author}</span>
                <span className="home-blog-author-date">{p.date}</span>
              </div>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

function ClosingCTA() {
  return (
    <section className="mk-cta">
      <div className="mk-cta-inner">
        <div className="mk-eyebrow" style={{ color: 'rgba(251,250,246,0.65)' }}>Bereit?</div>
        <h2 className="mk-cta-h">
          Lasst uns euer nächstes Event <span className="mk-italic">zusammen</span> planen.
        </h2>
        <p className="mk-cta-sub">
          Antwort innerhalb eines Werktags. Kein Vertriebs-Druck, kein Telefon-Marathon.
          Du beschreibst kurz, was du vorhast — wir kümmern uns um den Rest.
        </p>
        <div className="mk-cta-ctas">
          <a className="fg-btn-ink lg" href="#/individuell" onClick={(e) => go('#/individuell', e)}
             style={{ background: 'var(--paper-100)', color: 'var(--fairway-900)' }}>
            Event anfragen
            <span className="fg-arrow" style={{ background: 'var(--fairway-200)' }}>
              <ArrowGlyph />
            </span>
          </a>
          <a className="mk-cta-mail" href="mailto:events@firmengolf.de">events@firmengolf.de</a>
        </div>
      </div>
    </section>
  );
}

function OccasionsGrid() {
  const items = [
    { eyebrow: 'Onboarding', t: 'Neue Mitarbeitende willkommen heißen.',     b: 'Ein Halbtag Schnupperkurs, der Eis bricht.',         href: '#/events?format=schnupperkurs', img: 'assets/imagery/event-summer.jpg' },
    { eyebrow: 'Vertrieb',   t: 'Kunden und Partner zusammenbringen.',        b: 'Ganztägiges Firmenturnier oder Networking-Runde.',   href: '#/events?format=firmenturnier',  img: 'assets/imagery/event-corporate.jpg' },
    { eyebrow: 'Strategie',  t: 'Raus aus dem Konferenzraum, rein ins Gespräch.', b: 'Mehrtägiges Offsite mit Workshop-Räumen.',     href: '#/events?format=offsite',        img: 'assets/imagery/venue-clubhouse.jpg' },
    { eyebrow: 'HR & BGM',   t: 'Bewegung in den Arbeitsalltag bringen.',     b: 'Gesundheitstag, BGM-konform abrechenbar.',           href: '#/events?format=gesundheitstag', img: 'assets/imagery/hero-forest.jpg' },
    { eyebrow: 'Top-Performer', t: 'Eure besten Leute besonders behandeln.',   b: 'Incentive-Reise mit Übernachtung und privatem Dinner.', href: '#/events?format=incentive',   img: 'assets/imagery/hero-mountains.jpg' },
    { eyebrow: 'Team-Tag',   t: 'Ein gemeinsamer Nachmittag draußen.',         b: 'Teamevent mit gemischten Zweier-Teams.',         href: '#/events?format=teamevent',  img: 'assets/imagery/event-team.jpg' },
  ];
  return (
    <section className="mk-section home-occasions-section">
      <div className="mk-section-head">
        <div className="mk-eyebrow">Für welchen Anlass?</div>
        <h2 className="mk-h2">Sag uns, was ihr vorhabt — wir kennen den passenden Veranstaltungstyp.</h2>
        <p className="mk-sub">Suche nach dem, was ihr erreichen wollt, nicht nach dem Veranstaltungstyp.</p>
      </div>
      <div className="home-occasions">
        {items.map((it, i) => (
          <a key={i} className="home-occ" href={it.href} onClick={(e) => go(it.href, e)}>
            <div className="home-occ-photo" style={{ backgroundImage: `url('${window.A(it.img)}')` }} />
            <div className="home-occ-body">
              <div className="mk-eyebrow" style={{ color: 'var(--fairway-700)' }}>{it.eyebrow}</div>
              <h3 className="home-occ-t">{it.t}</h3>
              <p className="home-occ-b">{it.b}</p>
              <div className="home-occ-foot">
                Passende Events ansehen <ArrowGlyph size={12} />
              </div>
            </div>
          </a>
        ))}
      </div>
    </section>
  );
}

function HomePage() {
  return (
    <div data-screen-label="Home">
      <HomeHero />
      <PartnersStrip />
      <ExperienceSection />
      <HowItWorks />
      <FeaturedFormats />
      <OccasionsGrid />
      <IndividualTeaser />
      <BenefitTeaser />
      <Testimonials />
      <BlogTeaser />
      <ClosingCTA />
    </div>
  );
}
window.HomePage = HomePage;
