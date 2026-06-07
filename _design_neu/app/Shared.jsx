/* eslint-disable */
// =============================================================
// Shared components — TopNav, Footer, page chrome, common bits.
// All link clicks update window.location.hash; App routes off of it.
// =============================================================

// (asset helper provided as window.A)

function go(hash, e) {
  if (e) e.preventDefault();
  window.location.hash = hash;
  window.scrollTo({ top: 0, behavior: 'instant' });
}
window.go = go;

// Lightweight global toast (used by share buttons etc.)
window.__fgToast = function (msg) {
  let el = document.getElementById('fg-toast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'fg-toast';
    el.className = 'fg-toast';
    document.body.appendChild(el);
  }
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(window.__fgToastT);
  window.__fgToastT = setTimeout(() => el.classList.remove('show'), 2000);
};

// ---------- Top Nav ----------
function TopNav({ current }) {
  const [open, setOpen] = React.useState(false);
  const items = [
    { href: '#/events',     label: 'Events' },
    { href: '#/golfplaetze', label: 'Golfplätze' },
    { href: '#/individuell', label: 'Individuelle Events' },
    { href: '#/blog',       label: 'Blog' },
    { href: '#/ueber-uns',  label: 'Über uns' },
    { href: '#/kontakt',    label: 'Kontakt' },
  ];

  // lock scroll while the mobile drawer is open
  React.useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : '';
    return () => { document.body.style.overflow = ''; };
  }, [open]);

  const nav = (href, e) => { setOpen(false); go(href, e); };

  return (
    <nav className="fg-topnav">
      <div className="fg-topnav-inner">
        <a className="fg-brand" href="#/" onClick={(e) => nav('#/', e)}>
          <img src={window.A('assets/logo/firmengolf-wordmark.png')} alt="Firmengolf" />
        </a>
        <div className="fg-nav-items">
          {items.map(it => (
            <a key={it.href}
               href={it.href}
               className={current && current.startsWith(it.href) ? 'active' : ''}
               onClick={(e) => go(it.href, e)}>
              {it.label}
            </a>
          ))}
        </div>
        <div className="fg-nav-end">
          <a className="fg-nav-link" href="partner-login.html">
            Partnerportal
          </a>
          <a className="fg-nav-cta" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
            Jetzt anfragen
            <span className="fg-arrow">
              <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </span>
          </a>
        </div>

        {/* Mobile hamburger */}
        <button className="fg-nav-burger" aria-label="Menü öffnen" aria-expanded={open} onClick={() => setOpen(true)}>
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
      </div>

      {/* Mobile drawer */}
      {open && (
        <div className="fg-drawer-scrim" onClick={() => setOpen(false)}>
          <div className="fg-drawer" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Navigation">
            <div className="fg-drawer-top">
              <a className="fg-brand" href="#/" onClick={(e) => nav('#/', e)}>
                <img src={window.A('assets/logo/firmengolf-wordmark.png')} alt="Firmengolf" />
              </a>
              <button className="fg-drawer-close" aria-label="Menü schließen" onClick={() => setOpen(false)}>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
            <div className="fg-drawer-items">
              {items.map(it => (
                <a key={it.href} href={it.href}
                   className={'fg-drawer-link ' + (current && current.startsWith(it.href) ? 'active' : '')}
                   onClick={(e) => nav(it.href, e)}>
                  {it.label}
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </a>
              ))}
            </div>
            <div className="fg-drawer-foot">
              <a className="fg-nav-cta fg-drawer-cta" href="#/individuell" onClick={(e) => nav('#/individuell', e)}>
                Jetzt anfragen
                <span className="fg-arrow">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </span>
              </a>
              <a className="fg-drawer-partner" href="partner-login.html">
                Partnerportal für Golfplätze →
              </a>
            </div>
          </div>
        </div>
      )}
    </nav>
  );
}
window.TopNav = TopNav;

// ---------- Mobile menu bar (shared across all pages) ----------
// The three icon tabs (Events / Anfrage / Blog) are the mobile menu.
// They collapse away on scroll; the page's `children` action (search pill,
// CTA button, blog search) stays sticky on top. CSS shows this ≤720px only.
function MobileTabs({ active }) {
  return (
    <div className="ev-mtabs">
      <a href="#/events" className={'ev-mtab ' + (active === 'events' ? 'active' : '')} onClick={(e) => go('#/events', e)}>
        <span className="ev-mtab-ic">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        </span>
        <span className="ev-mtab-l">Events</span>
      </a>
      <a href="#/individuell" className={'ev-mtab ' + (active === 'anfrage' ? 'active' : '')} onClick={(e) => go('#/individuell', e)}>
        <span className="ev-mtab-ic">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
        </span>
        <span className="ev-mtab-l">Anfrage</span>
      </a>
      <a href="#/blog" className={'ev-mtab ' + (active === 'blog' ? 'active' : '')} onClick={(e) => go('#/blog', e)}>
        <span className="ev-mtab-ic">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M4 5a2 2 0 0 1 2-2h8l6 6v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M14 3v6h6"/><path d="M8 13h8M8 17h5"/></svg>
        </span>
        <span className="ev-mtab-l">Blog</span>
      </a>
    </div>
  );
}
window.MobileTabs = MobileTabs;

function MobileBar({ active, children }) {
  const [stuck, setStuck] = React.useState(false);
  React.useEffect(() => {
    const onScroll = () => setStuck(window.scrollY > 56);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    return () => window.removeEventListener('scroll', onScroll);
  }, []);
  return (
    <div className={'ev-msearch-bar ' + (stuck ? 'is-stuck ' : '') + (children ? '' : 'tabs-only')}>
      <MobileTabs active={active} />
      {children}
    </div>
  );
}
window.MobileBar = MobileBar;

// ---------- Footer ----------
function SiteFooter() {
  return (
    <footer className="fg-footer" data-screen-label="Footer">
      <div className="fg-footer-top">
        <div className="fg-footer-brand">
          <img src={window.A('assets/logo/firmengolf-wordmark.png')} alt="Firmengolf" style={{ height: 32 }} />
          <p className="fg-footer-line">
            Wir machen Golf zugänglich. Als Veranstaltungstyp, als Ausgleich und als
            Erlebnis, das Teams verbindet.
          </p>
          <p className="fg-footer-tag">
            Bringt euer Team raus aus dem Büro und rein ins <span className="mk-italic">Grüne</span>.
          </p>
          <div className="fg-footer-socials">
            <a href="https://www.instagram.com/firmengolf/" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><SocialIcon name="instagram" /></a>
            <a href="https://www.facebook.com/Firmengolf" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><SocialIcon name="facebook" /></a>
            <a href="https://www.linkedin.com/company/firmengolf/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><SocialIcon name="linkedin" /></a>
          </div>
        </div>
        <div className="fg-footer-cols">
          <div>
            <div className="fg-footer-head">Events</div>
            <a href="#/events" onClick={(e) => go('#/events', e)}>Alle Veranstaltungstypen</a>
            <a href="#/golfplaetze" onClick={(e) => go('#/golfplaetze', e)}>Golfplätze</a>
            <a href="#/events?format=schnupperkurs" onClick={(e) => go('#/events?format=schnupperkurs', e)}>Schnupperkurs</a>
            <a href="#/events?format=firmenturnier" onClick={(e) => go('#/events?format=firmenturnier', e)}>Firmenturnier</a>
            <a href="#/events?format=teamevent" onClick={(e) => go('#/events?format=teamevent', e)}>Teamevent</a>
            <a href="#/events?format=offsite" onClick={(e) => go('#/events?format=offsite', e)}>Offsite</a>
            <a href="#/individuell" onClick={(e) => go('#/individuell', e)}>Individuelles Event</a>
          </div>
          <div>
            <div className="fg-footer-head">Firmengolf</div>
            <a href="#/ueber-uns" onClick={(e) => go('#/ueber-uns', e)}>Über uns</a>
            <a href="#/blog" onClick={(e) => go('#/blog', e)}>Blog</a>
            <a href="#/kontakt" onClick={(e) => go('#/kontakt', e)}>Kontakt</a>
            <a href="https://firmen.golf" target="_blank" rel="noopener noreferrer">Corporate Benefit ↗</a>
          </div>
          <div>
            <div className="fg-footer-head">Für Plätze</div>
            <a href="https://partner.firmengolf.de" target="_blank" rel="noopener noreferrer">Partnerportal ↗</a>
            <a href="partner-onboarding.html">Platz anbieten</a>
            <a href="#/partner-faq" onClick={(e) => go('#/partner-faq', e)}>Partner-FAQ</a>
          </div>
          <div>
            <div className="fg-footer-head">Rechtliches</div>
            <a href="#/impressum"  onClick={(e) => go('#/impressum', e)}>Impressum</a>
            <a href="#/datenschutz" onClick={(e) => go('#/datenschutz', e)}>Datenschutz</a>
            <a href="#/agb"        onClick={(e) => go('#/agb', e)}>AGB</a>
          </div>
        </div>
      </div>
      <div className="fg-footer-base">
        <span>© 2026 Visionpunch UG (haftungsbeschränkt), München</span>
        <div className="fg-footer-links">
          <a>DE · EN</a>
          <span>·</span>
          <a href="#/presse" onClick={(e) => go('#/presse', e)}>Presse</a>
          <span>·</span>
          <a href="#/karriere" onClick={(e) => go('#/karriere', e)}>Karriere</a>
        </div>
      </div>
    </footer>
  );
}
window.SiteFooter = SiteFooter;

// ---------- Social brand icons (official glyph paths) ----------
function SocialIcon({ name, size = 17 }) {
  const paths = {
    instagram: <><rect x="2" y="2" width="20" height="20" rx="5.5" fill="none" stroke="currentColor" strokeWidth="1.7"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" strokeWidth="1.7"/><circle cx="17.4" cy="6.6" r="1.2" fill="currentColor"/></>,
    facebook: <path fill="currentColor" d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z"/>,
    tiktok: <path fill="currentColor" d="M16.5 2h-2.9v12.3a2.6 2.6 0 1 1-2.2-2.56V8.8a5.6 5.6 0 1 0 5 5.57V8.9a6.9 6.9 0 0 0 4 1.28V7.2a4 4 0 0 1-3.9-4z"/>,
    youtube: <path fill="currentColor" d="M22.5 7.2a2.7 2.7 0 0 0-1.9-1.9C18.9 4.8 12 4.8 12 4.8s-6.9 0-8.6.5A2.7 2.7 0 0 0 1.5 7.2 28.4 28.4 0 0 0 1 12a28.4 28.4 0 0 0 .5 4.8 2.7 2.7 0 0 0 1.9 1.9c1.7.5 8.6.5 8.6.5s6.9 0 8.6-.5a2.7 2.7 0 0 0 1.9-1.9A28.4 28.4 0 0 0 23 12a28.4 28.4 0 0 0-.5-4.8zM9.8 15.3V8.7l5.7 3.3z"/>,
    linkedin: <path fill="currentColor" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.95v5.66H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.55V9h3.57z"/>,
  };
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} aria-hidden>
      {paths[name]}
    </svg>
  );
}
window.SocialIcon = SocialIcon;

// ---------- small bits ----------
function ArrowGlyph({ size = 12 }) {
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M7 17L17 7M9 7h8v8"/>
    </svg>
  );
}
window.ArrowGlyph = ArrowGlyph;

function CheckGlyph({ size = 14 }) {
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M20 6L9 17l-5-5"/>
    </svg>
  );
}
window.CheckGlyph = CheckGlyph;

function StarGlyph({ size = 14, color = '#C9B488' }) {
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} fill={color} style={{ color }}>
      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>
    </svg>
  );
}
window.StarGlyph = StarGlyph;

function priceLine(e) {
  if (e.pricePerPerson) return ['€' + e.pricePerPerson, ' p.P.'];
  if (e.pricePerGroup)  return ['€' + (e.pricePerGroup >= 1000 ? (e.pricePerGroup/1000).toFixed(1)+'k' : e.pricePerGroup), ' / Gruppe'];
  return ['Auf Anfrage', ''];
}
window.priceLine = priceLine;

// ---------- FAQ ----------
function FAQ({ eyebrow = 'Häufige Fragen', title, items, intro }) {
  const [open, setOpen] = React.useState(0);
  return (
    <section className="mk-section faq-section">
      <div className="faq-shell">
        <div className="faq-aside">
          <div className="mk-eyebrow">{eyebrow}</div>
          <h2 className="mk-h2" style={{ marginTop: 8 }}>{title}</h2>
          {intro && <p className="mk-sub" style={{ marginTop: 16 }}>{intro}</p>}
          <div className="faq-cta">
            <a className="fg-btn-ghost" href="#/kontakt" onClick={(e) => go('#/kontakt', e)}>
              Etwas anderes fragen <ArrowGlyph size={12} />
            </a>
          </div>
        </div>
        <ul className="faq-list">
          {items.map((it, i) => (
            <li key={i} className={'faq-item ' + (open === i ? 'open' : '')}>
              <button className="faq-q" onClick={() => setOpen(open === i ? -1 : i)}>
                <span>{it.q}</span>
                <span className="faq-toggle" aria-hidden>{open === i ? '–' : '+'}</span>
              </button>
              {open === i && <div className="faq-a">{it.a}</div>}
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
window.FAQ = FAQ;

// ---------- Trust strip ----------
function TrustStrip() {
  const items = [
    { t: '180+ Partnerplätze',     b: 'Vom Stadtkurs bis zur Berg-Anlage.' },
    { t: 'Ein Ansprechpartner',     b: 'Vom Erstkontakt bis nach dem Event.' },
    { t: 'Eine Rechnung',           b: 'Sauber abgerechnet, BGM-konform wenn nötig.' },
    { t: 'Antwort < 24 h',          b: 'Werktags innerhalb eines Arbeitstags.' },
  ];
  return (
    <section className="trust-strip">
      <div className="trust-inner">
        {items.map((it, i) => (
          <div key={i} className="trust-cell">
            <div className="trust-t">{it.t}</div>
            <div className="trust-b">{it.b}</div>
          </div>
        ))}
      </div>
    </section>
  );
}
window.TrustStrip = TrustStrip;
