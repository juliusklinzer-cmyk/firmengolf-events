/* eslint-disable */
// =============================================================
// Blog list + Blog article pages.
// URLs: #/blog  and  #/blog/<slug>
// =============================================================
// (asset helper provided as window.A)

const BLOG_CATS = ['Alle Themen', 'Benefits', 'Praxis', 'Einsteiger', 'Inspiration', 'Nachhaltigkeit', 'Research'];

function BlogMobileSearch({ cat, setCat, query, setQuery, count }) {
  const [open, setOpen] = React.useState(false);
  const [c, setC] = React.useState(cat);
  const [q, setQ] = React.useState(query);

  React.useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : '';
    return () => { document.body.style.overflow = ''; };
  }, [open]);

  const openSheet = () => { setC(cat); setQ(query); setOpen(true); };
  const onSearch = () => { setCat(c); setQuery(q); setOpen(false); };
  const reset = () => { setC('Alle Themen'); setQ(''); };

  const pristine = cat === 'Alle Themen' && !query;
  const summary = pristine ? 'Beiträge durchsuchen'
    : [cat !== 'Alle Themen' && cat, query && `„${query}"`].filter(Boolean).join(' · ');

  return (
    <>
      <MobileBar active="blog">
        <button className="ev-msearch" onClick={openSheet} aria-label="Blog durchsuchen">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <span className={'ev-msearch-t ' + (pristine ? 'muted' : '')}>{summary}</span>
        </button>
      </MobileBar>

      {open && (
        <div className="ev-sheet-scrim" onClick={() => setOpen(false)}>
          <div className="ev-sheet" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Blog durchsuchen">
            <div className="ev-sheet-top">
              <button className="ev-sheet-close" onClick={() => setOpen(false)} aria-label="Schließen">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
              <span className="ev-sheet-title">Im Magazin suchen</span>
            </div>
            <div className="ev-sheet-body">
              <section className="ev-sheet-card">
                <div className="ev-sheet-q">Suchbegriff</div>
                <input className="fg-input" value={q} onChange={(e) => setQ(e.target.value)}
                       placeholder="z.B. Benefit, Sommerfest, Einsteiger …" autoFocus />
              </section>
              <section className="ev-sheet-card">
                <div className="ev-sheet-q">Kategorie</div>
                <div className="ev-sheet-chips">
                  {BLOG_CATS.map(x => (
                    <button key={x} className={'ev-sheet-chip ' + (c === x ? 'on' : '')} onClick={() => setC(x)}>{x}</button>
                  ))}
                </div>
              </section>
            </div>
            <div className="ev-sheet-foot">
              <button className="ev-sheet-clear" onClick={reset}>Alle löschen</button>
              <button className="ev-sheet-go" onClick={onSearch}>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                Anzeigen
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

function BlogListPage() {
  const d = window.SITE_DATA;
  const [cat, setCat] = React.useState('Alle Themen');
  const [query, setQuery] = React.useState('');

  const match = (p) => {
    if (cat !== 'Alle Themen' && p.tag !== cat) return false;
    if (query) {
      const q = query.toLowerCase();
      if (!(p.title + ' ' + p.excerpt + ' ' + p.tag).toLowerCase().includes(q)) return false;
    }
    return true;
  };

  const filtering = cat !== 'Alle Themen' || !!query;
  const featured = d.posts.find(p => p.featured) || d.posts[0];
  const list = d.posts.filter(match);
  const rest = filtering ? list : list.filter(p => p !== featured);

  return (
    <div data-screen-label="Blog">
      <BlogMobileSearch cat={cat} setCat={setCat} query={query} setQuery={setQuery} count={list.length} />

      <section className="page-hero blog-hero">
        <div className="page-hero-inner">
          <div className="mk-eyebrow">Magazin</div>
          <h1 className="page-hero-title">
            Aus dem <span className="mk-italic">Fairway</span> — unser Magazin.
          </h1>
          <p className="page-hero-sub">
            Praxisleitfäden, Inspiration für eure nächste Veranstaltung und Gespräche mit Menschen,
            die täglich auf den Plätzen unterwegs sind.
          </p>
        </div>
      </section>

      {/* Featured — only when not filtering */}
      {!filtering && (
        <section className="mk-section blog-featured-sec">
          <article className="blog-featured" onClick={() => go('#/blog/' + featured.slug)}>
            <div className="blog-featured-photo" style={{ backgroundImage: `url('${window.A(featured.image)}')` }}>
              <span className="mk-format-tag">Top-Story</span>
            </div>
            <div className="blog-featured-body">
              <div className="blog-meta-row">
                <span className="blog-tag">{featured.tag}</span>
                <span>·</span>
                <span>{featured.date}</span>
                <span>·</span>
                <span>{featured.readTime}</span>
              </div>
              <h2 className="blog-featured-h">{featured.title}</h2>
              <p className="blog-featured-x">{featured.excerpt}</p>
              <div className="blog-author">
                <span className="blog-author-n">{featured.author}</span>
                <span className="blog-author-r">{featured.role}</span>
              </div>
              <div style={{ marginTop: 20 }}>
                <span className="fg-btn-ghost">Artikel lesen <ArrowGlyph size={12} /></span>
              </div>
            </div>
          </article>
        </section>
      )}

      {/* Category filter — now functional */}
      <section className="blog-cats">
        <div className="blog-cats-inner">
          {BLOG_CATS.map(c => (
            <button key={c} className={'fg-chip ' + (cat === c ? 'active' : '')} onClick={() => setCat(c)}>{c}</button>
          ))}
        </div>
      </section>

      {/* Grid */}
      <section className="mk-section">
        {filtering && (
          <div className="blog-result-head">
            <span>{list.length} {list.length === 1 ? 'Beitrag' : 'Beiträge'}{cat !== 'Alle Themen' ? ' in ' + cat : ''}{query ? ' für „' + query + '"' : ''}</span>
            <button className="ev-clear" onClick={() => { setCat('Alle Themen'); setQuery(''); }}>Zurücksetzen</button>
          </div>
        )}
        {rest.length === 0 ? (
          <div className="fg-empty">
            <div className="ev-empty-h">Kein Beitrag gefunden.</div>
            <p>Versuch eine andere Kategorie oder einen anderen Suchbegriff.</p>
          </div>
        ) : (
          <div className="blog-grid">
            {rest.map(p => (
              <article key={p.slug} className="blog-card" onClick={() => go('#/blog/' + p.slug)}>
                <div className="blog-card-photo" style={{ backgroundImage: `url('${window.A(p.image)}')` }} />
                <div className="blog-card-body">
                  <div className="blog-meta-row">
                    <span className="blog-tag">{p.tag}</span>
                    <span>·</span>
                    <span>{p.readTime}</span>
                  </div>
                  <h3 className="blog-card-h">{p.title}</h3>
                  <p className="blog-card-x">{p.excerpt}</p>
                  <div className="blog-card-foot">
                    <span className="blog-author-n">{p.author}</span>
                    <span className="blog-author-r">{p.date}</span>
                  </div>
                </div>
              </article>
            ))}
          </div>
        )}
      </section>

      {/* Newsletter */}
      <section className="blog-newsletter">
        <div className="blog-newsletter-inner">
          <div>
            <div className="mk-eyebrow">Newsletter</div>
            <h3 className="blog-newsletter-h">
              Einmal im Monat — kurze Mail, gute Stories.
            </h3>
            <p className="muted" style={{ marginTop: 8 }}>
              Lesetipps, neue Formate und Termine. Kein Spam, kein Vertrieb.
            </p>
          </div>
          <form className="blog-newsletter-form" onSubmit={(e) => e.preventDefault()}>
            <input className="fg-input" type="email" placeholder="deine@firma.de" />
            <button className="fg-btn-brand" type="submit">Abonnieren</button>
          </form>
        </div>
      </section>
    </div>
  );
}
window.BlogListPage = BlogListPage;


function BlogArticlePage({ post }) {
  const d = window.SITE_DATA;
  if (!post) {
    return (
      <div className="ev-notfound">
        <div className="mk-eyebrow">404</div>
        <h1 className="display-md">Diesen Artikel finden wir grad nicht.</h1>
        <a className="fg-btn-brand" href="#/blog" onClick={(e) => go('#/blog', e)} style={{ marginTop: 24 }}>
          Zurück zum Magazin
        </a>
      </div>
    );
  }
  const related = d.posts.filter(p => p.slug !== post.slug).slice(0, 3);
  const body = ARTICLE_BODIES[post.slug] || ARTICLE_BODIES.__default;

  return (
    <article className="blog-article" data-screen-label="Blog Article">
      <a className="ev-back" href="#/blog" onClick={(e) => go('#/blog', e)}>← Alle Artikel</a>

      <header className="blog-article-head">
        <div className="blog-meta-row" style={{ justifyContent: 'center' }}>
          <span className="blog-tag">{post.tag}</span>
          <span>·</span>
          <span>{post.date}</span>
          <span>·</span>
          <span>{post.readTime}</span>
        </div>
        <h1 className="blog-article-h">{post.title}</h1>
        <p className="blog-article-lead">{post.excerpt}</p>
        <div className="blog-article-byline">
          <img src={window.A('assets/imagery/avatar-1.jpg')} alt={post.author} />
          <div>
            <div className="blog-author-n">{post.author}</div>
            <div className="blog-author-r">{post.role}</div>
          </div>
        </div>
      </header>

      <div className="blog-article-photo" style={{ backgroundImage: `url('${window.A(post.image)}')` }} />

      <div className="blog-article-body">
        {body}
      </div>

      {/* Related */}
      <section className="mk-section blog-related">
        <div className="mk-section-head between">
          <h2 className="mk-h2" style={{ fontSize: 32 }}>Weiterlesen</h2>
          <a className="fg-btn-ghost" href="#/blog" onClick={(e) => go('#/blog', e)}>
            Alle Artikel <ArrowGlyph size={12} />
          </a>
        </div>
        <div className="blog-grid">
          {related.map(p => (
            <article key={p.slug} className="blog-card" onClick={() => go('#/blog/' + p.slug)}>
              <div className="blog-card-photo" style={{ backgroundImage: `url('${window.A(p.image)}')` }} />
              <div className="blog-card-body">
                <div className="blog-meta-row">
                  <span className="blog-tag">{p.tag}</span>
                  <span>·</span>
                  <span>{p.readTime}</span>
                </div>
                <h3 className="blog-card-h">{p.title}</h3>
                <p className="blog-card-x">{p.excerpt}</p>
              </div>
            </article>
          ))}
        </div>
      </section>
    </article>
  );
}
window.BlogArticlePage = BlogArticlePage;

// ---------- Article bodies per slug ----------
const ARTICLE_BODIES = {
  'golf-als-corporate-benefit': (
    <>
      <p className="blog-p-lead">
        Wenn HR-Verantwortliche heute über Benefits sprechen, geht es selten nur ums Gehalt.
        Es geht um Wirkung — und um ein Signal an die Belegschaft, dass das Unternehmen
        die Gesundheit, das Wohlbefinden und die Zeit der Mitarbeitenden ernst nimmt.
      </p>
      <h2 className="blog-h2">Warum Golf — und warum jetzt?</h2>
      <p>
        Drei einfache Gründe, die in unseren Kunden-Gesprächen immer wieder auftauchen:
        Golf ist eine Bewegungsform, die ohne Höchstleistung funktioniert. Es bringt
        Mitarbeitende an die frische Luft — vier, fünf Kilometer Spaziergang sind ein
        typischer 9-Loch-Nachmittag. Und es verbindet Generationen, vom Auszubildenden
        bis zur Geschäftsführerin.
      </p>
      <blockquote className="blog-pullquote">
        <p>"Wir verkaufen kein Produkt. Wir verkaufen Bewegung, Konzentration und Zeit draußen — und Golf ist eines der wenigen Vehikel, das alle drei in einer einzigen Aktivität liefert."</p>
        <footer>— Lena Hoffmann, CEO Firmengolf</footer>
      </blockquote>
      <h2 className="blog-h2">Der steuerfreie Sachbezug: 50 € pro Monat</h2>
      <p>
        Seit 2022 können Unternehmen ihren Mitarbeitenden einen sogenannten <em>steuerfreien Sachbezug</em>
        in Höhe von <strong>50 € pro Monat</strong> gewähren — ohne dass dieser Lohnsteuer oder
        Sozialabgaben auslöst. Genutzt werden kann das für Sport-, Mobilitäts- oder Gesundheits-Angebote.
      </p>
      <p>
        Bei Firmengolf bedeutet das in der Praxis: Mitarbeitende bekommen Zugang zu Schnupperkursen,
        Trainerstunden und Range-Sessions auf Partnerplätzen — verrechnet über das Benefit-Konto,
        das die HR-Abteilung einmalig einrichtet.
      </p>
      <h2 className="blog-h2">Was einen guten Benefit ausmacht</h2>
      <ul className="blog-ul">
        <li><strong>Niedrigschwellig.</strong> Wer noch nie einen Schläger gehalten hat, muss trotzdem mitmachen können.</li>
        <li><strong>Sichtbar.</strong> Mitarbeitende erleben das Benefit aktiv — nicht nur in der Gehaltsabrechnung.</li>
        <li><strong>Belegschafts-übergreifend.</strong> Vom Onboarding bis zur Geschäftsführung, alle haben Zugriff.</li>
        <li><strong>Abrechenbar.</strong> Steuerlich sauber, einfach für HR.</li>
      </ul>
      <div className="blog-cta-card">
        <div>
          <div className="mk-eyebrow">Nächster Schritt</div>
          <h3 className="blog-cta-h">Benefit-Programm fürs eigene Team prüfen.</h3>
          <p className="muted">15 Minuten Demo, ein Vorschlag, keine Bindung.</p>
        </div>
        <a className="fg-btn-brand lg" href="https://firmen.golf" target="_blank" rel="noopener noreferrer">
          Zum Benefit-Programm ↗
        </a>
      </div>
    </>
  ),

  'event-checkliste': (
    <>
      <p className="blog-p-lead">
        Ein erstes Firmenevent zu planen ist kein Hexenwerk — wenn man die zwölf Punkte vorher kennt,
        die typischerweise unter den Tisch fallen. Hier ist unsere Checkliste, sortiert nach Aufwand
        und zeitlicher Priorität.
      </p>
      <h2 className="blog-h2">Acht Wochen vorher</h2>
      <ul className="blog-ul">
        <li><strong>Zielsetzung.</strong> Soll das Event ein Dankeschön sein, ein Teamevent, eine Kundenpflege? Die Antwort prägt das gesamte Format.</li>
        <li><strong>Datum und Backup-Datum.</strong> Donnerstag und Freitag sind beliebter als Montag/Dienstag, früher Nachmittag besser als später Vormittag.</li>
        <li><strong>Gruppengröße & Mix.</strong> Vorerfahrung im Team grob abschätzen — Schnupperformat oder Spielerunde?</li>
        <li><strong>Budget-Rahmen.</strong> Pro Person typisch zwischen €80 (Schnupperkurs) und €350 (Turnier mit Catering).</li>
      </ul>
      <h2 className="blog-h2">Vier Wochen vorher</h2>
      <ul className="blog-ul">
        <li><strong>Einladung verschicken.</strong> Inklusive Hinweis: Vorerfahrung nicht nötig, Ausrüstung wird gestellt.</li>
        <li><strong>Catering planen.</strong> Vegetarisch/vegan als Standard einplanen — nicht als Sonderwunsch.</li>
        <li><strong>Transport & Parken.</strong> Shuttle ab Bahnhof, Fahrgemeinschaften, eigener Stellplatz.</li>
        <li><strong>Wetter-Backup.</strong> Indoor-Simulator oder überdachte Range klären.</li>
      </ul>
      <blockquote className="blog-pullquote">
        <p>"Das einzige, was beim Event-Tag schiefgeht, sind die Sachen, die man vier Wochen vorher nicht entschieden hat."</p>
        <footer>— Jonas Bredow, Head of Events</footer>
      </blockquote>
      <h2 className="blog-h2">Eine Woche vorher</h2>
      <ul className="blog-ul">
        <li><strong>Teilnehmerliste finalisieren.</strong> Allergien, Schuhgrößen, Linkshänder/-fänger.</li>
        <li><strong>Briefing für die Belegschaft.</strong> Was anziehen, was mitbringen, wo treffen.</li>
        <li><strong>Fotograf?</strong> Wenn ja: zwei Sätze zum Stil, keine 20-Seiten-PDF.</li>
        <li><strong>Ein klarer Ansprechpartner vor Ort.</strong> Damit nicht alle die HR-Leitung anrufen, wenn der Bahnhof gesperrt ist.</li>
      </ul>
      <div className="blog-cta-card">
        <div>
          <div className="mk-eyebrow">Tipp</div>
          <h3 className="blog-cta-h">Wir übernehmen 11 der 12 Punkte.</h3>
          <p className="muted">Den Anlass solltest du selbst kennen — den Rest planen wir mit dir.</p>
        </div>
        <a className="fg-btn-brand lg" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
          Jetzt anfragen
        </a>
      </div>
    </>
  ),

  'einsteiger-mythos': (
    <>
      <p className="blog-p-lead">
        "Aber wir können doch alle nicht Golf spielen" ist mit Abstand der Satz, den wir am häufigsten hören.
        Und er ist gleichzeitig der größte Grund, warum Firmen sich überhaupt erst überlegen, ein
        Golf-Event zu buchen — sie wissen es nur noch nicht.
      </p>
      <h2 className="blog-h2">Drei Stunden, kein Vorwissen</h2>
      <p>
        Ein klassischer Schnupperkurs sieht so aus: 15 Minuten Begrüßung und Sicherheits-Briefing,
        90 Minuten Range mit PGA-Coach (Putten, Chippen, voller Schwung), eine kleine Schnitzeljagd
        übers Übungsgelände, dann ein Getränk auf der Terrasse. Alles in unter drei Stunden, alles
        ohne Vorwissen, alles inklusive Schläger und Bälle.
      </p>
      <h2 className="blog-h2">Was Einsteigende am meisten überrascht</h2>
      <ul className="blog-ul">
        <li><strong>Wie schnell der erste Erfolg kommt.</strong> Den ersten Ball solide treffen — das schafft fast jede:r in den ersten 15 Minuten.</li>
        <li><strong>Wie ruhig es ist.</strong> Kein Stadion-Lärm, kein Anfeuern. Golf ist eine konzentrierte, fast meditative Sportart.</li>
        <li><strong>Wie viel man läuft.</strong> Auch nur 9 Loch sind locker 4 Kilometer Spaziergang.</li>
        <li><strong>Wie wenig der Coach korrigiert.</strong> PGA-Pros sind darauf trainiert, Einsteigende positiv zu verstärken — nicht zu drillen.</li>
      </ul>
      <blockquote className="blog-pullquote">
        <p>"Ich war nervös. Ich war nach 20 Minuten begeistert. Drei Wochen später hab ich mir Schläger gekauft."</p>
        <footer>— Teilnehmer eines Schnupperkurses, Hamburg</footer>
      </blockquote>
      <h2 className="blog-h2">Was Veranstalter beachten sollten</h2>
      <p>
        Drei Dinge, die wir vorher mit HR und Office-Management klären: Erstens, die Kommunikation an die
        Belegschaft sollte explizit sagen "kein Vorwissen nötig" — sonst trauen sich Anfänger:innen nicht.
        Zweitens, die Gruppengröße pro Coach: max. 8 Personen. Drittens, ein lockerer Dresscode-Hinweis
        ("Sportschuhe oder Turnschuhe, sonst egal") reicht völlig.
      </p>
      <div className="blog-cta-card">
        <div>
          <div className="mk-eyebrow">Beliebt für Einsteiger:innen</div>
          <h3 className="blog-cta-h">Schnupperkurs an einem Nachmittag.</h3>
          <p className="muted">€89 pro Person · 6–24 Gäste · PGA-Coach inklusive.</p>
        </div>
        <a className="fg-btn-brand lg" href="#/events/schnupperkurs-hamburg-wendlohe"
           onClick={(e) => go('#/events/schnupperkurs-hamburg-wendlohe', e)}>
          Format ansehen
        </a>
      </div>
    </>
  ),

  'sommerfest-2026': (
    <>
      <p className="blog-p-lead">
        Sommerfeste haben einen schlechten Ruf — zu Recht. Zwei Stunden Reden, eine halbe Stunde Buffet,
        dann fragt sich jede:r diskret, wann man früh gehen kann. Wir haben dieses Jahr drei Formate
        gebaut, die das anders machen.
      </p>
      <h2 className="blog-h2">Format 1: Flutlicht-Putting</h2>
      <p>
        Ein Sommerfest, das erst gegen 18:00 Uhr startet und unter Flutlicht endet. Putten auf einem präparierten Übungsgrün,
        Food Trucks auf der Range, DJ auf der Terrasse. Keine Reden, keine Powerpoints — nur ein langer Abend mit Mitarbeitenden
        und ihren Partner:innen. Skaliert von 30 bis 80 Personen.
      </p>
      <h2 className="blog-h2">Format 2: Sommerturnier am Nachmittag</h2>
      <p>
        9-Loch-Scramble in Vierer-Teams, gemischt nach Erfahrung. Wer noch nie gespielt hat, wird einem erfahrenen
        Spieler zugeteilt — und alle haben am Ende einen Score. Anschließend Grillen auf der Terrasse, kleine Siegerehrung,
        gegen 21:00 ist Schluss.
      </p>
      <h2 className="blog-h2">Format 3: Open-Range Tag</h2>
      <p>
        Komplettes Übungsgelände wird für euch reserviert. Vier PGA-Coaches stehen bereit, jede:r kann kommen und gehen
        wann er/sie will. Food Trucks, Liegestühle, Eismaschine. Perfekt, wenn die Belegschaft groß ist und man kein
        striktes Programm möchte.
      </p>
      <blockquote className="blog-pullquote">
        <p>"Nach drei Jahren Sommerfest-im-Park-mit-Catering war Flutlicht-Putting das beliebteste, das wir je gemacht haben."</p>
        <footer>— Office Manager einer Hamburger Agentur, 110 Personen</footer>
      </blockquote>
      <div className="blog-cta-card">
        <div>
          <div className="mk-eyebrow">Sommer 2026</div>
          <h3 className="blog-cta-h">Slots zwischen Juni und August sind knapp.</h3>
          <p className="muted">Fang früh an — wir reservieren auch unverbindlich.</p>
        </div>
        <a className="fg-btn-brand lg" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
          Sommerfest planen
        </a>
      </div>
    </>
  ),

  'wieso-offsite-im-gruenen': (
    <>
      <p className="blog-p-lead">
        Wir lieben Klischees nicht. "Bewegung tut gut" ist eines, das man kaum noch ernst nehmen kann.
        Trotzdem berichten unsere Kunden zuverlässig: Strategie-Offsites auf einem Golfplatz laufen
        anders als im Hotel-Konferenzraum. Wir haben gefragt, woran das liegt.
      </p>
      <h2 className="blog-h2">Drei Dinge, die immer wieder genannt werden</h2>
      <ul className="blog-ul">
        <li><strong>Konzentration.</strong> Vier Stunden konzentriertes Arbeiten am Vormittag, dann eine Pause auf der Range — und nachmittags lässt es sich besser wieder einsteigen.</li>
        <li><strong>Bildschirm-Distanz.</strong> Auf dem Platz ist niemand am Laptop. Das wirkt klein, ist aber riesig.</li>
        <li><strong>Bewegung.</strong> Wer den Vormittag im Workshop sitzt, ist nachmittags glücklich, draußen zu sein. Das öffnet die Köpfe.</li>
      </ul>
      <blockquote className="blog-pullquote">
        <p>"Wir entscheiden nach dem Lunch und dem 9-Loch-Spaziergang Dinge schneller als nach dem Lunch und der Hotel-Lobby."</p>
        <footer>— VP Sales, B2B-SaaS, 70 Mitarbeitende</footer>
      </blockquote>
      <h2 className="blog-h2">Was die Forschung dazu sagt</h2>
      <p>
        Mehrere Studien aus dem Bereich Workplace-Psychology zeigen, dass Walking-Meetings und
        Outdoor-Sessions die divergente Kreativität signifikant erhöhen — die Fähigkeit also, neue Optionen
        zu generieren, nicht zwischen vorhandenen zu wählen. Genau das ist die Phase, in der Strategie-Offsites
        oft hängen bleiben.
      </p>
      <h2 className="blog-h2">Wie ein typisches 2-Tages-Offsite mit uns aussieht</h2>
      <ul className="blog-ul">
        <li><strong>Tag 1:</strong> Vormittags Workshop (Breakout-Raum), Lunch auf der Terrasse, Nachmittags 9 Loch (gemischte Vierer).</li>
        <li><strong>Abend:</strong> Privates Dinner, optional Sommelier-Tasting.</li>
        <li><strong>Tag 2:</strong> Vormittags Synthese-Session, Lunch, Verabschiedung. Wer will, spielt nachmittags noch eine Runde.</li>
      </ul>
      <div className="blog-cta-card">
        <div>
          <div className="mk-eyebrow">Format</div>
          <h3 className="blog-cta-h">Strategie-Offsite Schloss Lüdersburg.</h3>
          <p className="muted">2 Tage, 1 Nacht, Workshop-Räume und 9 Loch.</p>
        </div>
        <a className="fg-btn-brand lg" href="#/events/strategie-offsite-luedersburg"
           onClick={(e) => go('#/events/strategie-offsite-luedersburg', e)}>
          Format ansehen
        </a>
      </div>
    </>
  ),

  'nachhaltiger-platzbetrieb': (
    <>
      <p className="blog-p-lead">
        Golfplätze haben Wasser-Probleme. Stimmt das überhaupt noch? Wir haben drei Greenkeeper:innen
        gefragt, was sich in den letzten zehn Jahren verändert hat — und wo die Branche heute steht.
      </p>
      <h2 className="blog-h2">Wasser: Tropfschläuche statt Sprinkler</h2>
      <p>
        Moderne Anlagen bewässern punktgenau — Tropfschläuche auf den Fairways, präzise Greens-Bewässerung,
        Brauchwasser- und Brunnen-Recycling. Auf einer typischen 18-Loch-Anlage in Norddeutschland sind
        das heute 40 % weniger Wasser als 2015, bei gleicher Spielbarkeit.
      </p>
      <h2 className="blog-h2">Biodiversität: 60 % der Fläche ist nicht Spielfläche</h2>
      <p>
        Ein 18-Loch-Platz umfasst typisch 60 Hektar — davon sind nur 25 bis 30 Hektar gepflegte Spielfläche.
        Der Rest sind Hecken, Wäldchen, Teiche, Streuobstwiesen, ungemähte Säume. Genau diese Flächen sind
        in den letzten Jahren explizit als Biodiversitäts-Habitat gestaltet worden.
      </p>
      <blockquote className="blog-pullquote">
        <p>"Wir haben mehr Wildbienenarten als drei umliegende Felder zusammen. Das war 2010 noch undenkbar."</p>
        <footer>— Greenkeeper, GC München-Riem</footer>
      </blockquote>
      <h2 className="blog-h2">Düngung & Pflanzenschutz</h2>
      <p>
        Mineraldünger ist auf Greens und Tee-Flächen Standard, wird aber massiv reduziert eingesetzt
        (typisch 80–120 kg N/ha/Jahr — vergleichbar mit moderner Landwirtschaft). Pflanzenschutz wird
        nur sehr punktuell eingesetzt; die meisten Plätze sind heute IPS-zertifiziert.
      </p>
      <h2 className="blog-h2">Was wir bei der Platzwahl achten</h2>
      <ul className="blog-ul">
        <li><strong>GEO-Zertifikat</strong> oder vergleichbares Nachhaltigkeits-Audit.</li>
        <li><strong>Wassermanagement-Plan</strong> öffentlich einsehbar.</li>
        <li><strong>Biodiversitäts-Flächen-Anteil</strong> über 50 %.</li>
        <li><strong>Heimischer Greenkeeping-Beruf</strong> mit Lehrlingsausbildung.</li>
      </ul>
      <div className="blog-cta-card">
        <div>
          <div className="mk-eyebrow">Auf einem GEO-zertifizierten Platz</div>
          <h3 className="blog-cta-h">Bewegung statt PowerPoint.</h3>
          <p className="muted">Gesundheitstag in Frankfurt-Niederrad.</p>
        </div>
        <a className="fg-btn-brand lg" href="#/events/gesundheitstag-frankfurt"
           onClick={(e) => go('#/events/gesundheitstag-frankfurt', e)}>
          Format ansehen
        </a>
      </div>
    </>
  ),

  __default: (
    <>
      <p className="blog-p-lead">
        Dieser Artikel ist als Platzhalter formatiert — die finale Redaktionsfassung wird ergänzt.
      </p>
      <p>
        Wenn dich das Thema interessiert und du einen konkreten Aspekt diskutiert sehen willst,
        schreib uns gerne. Wir greifen Leser-Themen regelmäßig auf und antworten persönlich.
      </p>
    </>
  ),
};
