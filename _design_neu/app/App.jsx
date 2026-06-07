/* eslint-disable */
// =============================================================
// App router — hash-based routing.
// Routes:
//   #/             home
//   #/events       events overview (optional ?format=)
//   #/events/<slug> event detail
//   #/individuell  individuelles event
//   #/blog         blog list
//   #/blog/<slug>  blog article
//   #/ueber-uns    about
//   #/kontakt      contact
//   #/impressum    impressum
//   #/datenschutz  datenschutz
//   #/agb          agb
// =============================================================
var { useState, useEffect } = React;

function parseHash() {
  const h = window.location.hash || '#/';
  const [path, query] = h.replace(/^#/, '').split('?');
  const params = {};
  if (query) {
    query.split('&').forEach(p => {
      const [k, v] = p.split('=');
      params[k] = decodeURIComponent(v || '');
    });
  }
  return { path: path || '/', params };
}

function App() {
  const [{ path, params }, setRoute] = useState(parseHash());
  const [isMobile, setIsMobile] = useState(() => window.matchMedia('(max-width: 720px)').matches);

  useEffect(() => {
    const onHash = () => setRoute(parseHash());
    window.addEventListener('hashchange', onHash);
    if (!window.location.hash) window.location.hash = '#/';
    return () => window.removeEventListener('hashchange', onHash);
  }, []);

  useEffect(() => {
    const mq = window.matchMedia('(max-width: 720px)');
    const on = () => setIsMobile(mq.matches);
    mq.addEventListener ? mq.addEventListener('change', on) : mq.addListener(on);
    return () => { mq.removeEventListener ? mq.removeEventListener('change', on) : mq.removeListener(on); };
  }, []);

  // ---- route resolution ----
  const d = window.SITE_DATA;
  let page;

  if (path === '/' || path === '') {
    // On mobile we skip the marketing homepage and lead straight with Events.
    page = isMobile ? <EventsPage initialFormat="all" /> : <HomePage />;
  } else if (path === '/events') {
    page = <EventsPage initialFormat={params.format || 'all'} initialQuery={params} />;
  } else if (path.startsWith('/events/')) {
    const slug = path.replace('/events/', '');
    const ev = d.events.find(e => e.slug === slug);
    page = <EventDetailWithExtras event={ev} />;
  } else if (path === '/individuell') {
    page = <IndividualPage />;
  } else if (path === '/golfplaetze') {
    page = <VenuesPage />;
  } else if (path.startsWith('/golfplatz/')) {
    const vslug = path.replace('/golfplatz/', '');
    page = <VenuePage slug={vslug} />;
  } else if (path.startsWith('/golf-events/')) {
    const city = path.replace('/golf-events/', '');
    page = <CityPage citySlug={city} />;
  } else if (path === '/blog') {
    page = <BlogListPage />;
  } else if (path.startsWith('/blog/')) {
    const slug = path.replace('/blog/', '');
    const post = d.posts.find(p => p.slug === slug);
    page = <BlogArticlePage post={post} />;
  } else if (path === '/ueber-uns') {
    page = <AboutPage />;
  } else if (path === '/kontakt') {
    page = <ContactPage />;
  } else if (path === '/presse') {
    page = <PressPage />;
  } else if (path === '/karriere') {
    page = <CareerPage />;
  } else if (path === '/partner-faq') {
    page = <PartnerFaqPage />;
  } else if (path === '/impressum') {
    page = <ImpressumPage />;
  } else if (path === '/datenschutz') {
    page = <DatenschutzPage />;
  } else if (path === '/agb') {
    page = <AGBPage />;
  } else {
    page = (
      <div className="ev-notfound">
        <div className="mk-eyebrow">404</div>
        <h1 className="display-md">Diese Seite gibt's nicht.</h1>
        <a className="fg-btn-brand" href="#/" onClick={(e) => go('#/', e)} style={{ marginTop: 24 }}>
          Zur Startseite
        </a>
      </div>
    );
  }

  // Pages that render their OWN mobile bar (with an action) — others get tabs-only.
  const ownBar = path === '/' || path === '' || path === '/events' || path === '/individuell' || path === '/blog';
  // On a single event's detail page the mobile menu is hidden — the logo is enough.
  const noBar = path.startsWith('/events/');
  const tabFor = path.startsWith('/events') ? 'events'
    : path === '/individuell' ? 'anfrage'
    : (path === '/golfplaetze' || path.startsWith('/golfplatz/')) ? 'golfplaetze'
    : path.startsWith('/blog') ? 'blog' : '';

  return (
    <div className="site">
      <TopNav current={'#' + path} />
      {!ownBar && !noBar && <MobileBar active={tabFor} />}
      <main key={path}>{page}</main>
      <SiteFooter />
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('app')).render(<App />);
