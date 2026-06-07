/* eslint-disable */
// =============================================================
// Event detail — single event page.
// "Dieses Event anfragen" opens the shared window.RequestWizard
// with a removable event-context preset. URL: #/events/<slug>
// =============================================================
// (asset helper provided as window.A)
var { useState } = React;

// Deterministic "live since" date per event (stable, no real backend).
function liveSince(event) {
  const months = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  let h = 0; for (const ch of (event.id + event.slug)) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  const day = (h % 27) + 1;
  const mon = h % 5; // Jan–Mai 2026
  return `${day}. ${months[mon]} 2026`;
}

function EventDetailPage({ event }) {
  const [showRequest, setShowRequest] = useState(false);
  if (!event) {
    return (
      <div className="ev-notfound">
        <div className="mk-eyebrow">404</div>
        <h1 className="display-md">Dieses Event gibt's nicht (mehr).</h1>
        <p className="muted" style={{ marginTop: 12, marginBottom: 24 }}>Vielleicht findest du in der Übersicht etwas Ähnliches.</p>
        <a className="fg-btn-brand" href="#/events" onClick={(e) => go('#/events', e)}>
          Zur Event-Übersicht
        </a>
      </div>
    );
  }
  const [main, suffix] = priceLine(event);
  const shareEvent = () => {
    const url = window.location.href;
    if (navigator.share) {
      navigator.share({ title: event.title, text: 'Schau dir dieses Firmen-Event an: ' + event.title, url }).catch(() => {});
    } else if (navigator.clipboard) {
      navigator.clipboard.writeText(url);
      const el = document.getElementById('share-hint');
      if (el) { el.classList.add('show'); setTimeout(() => el.classList.remove('show'), 1800); }
    }
  };

  return (
    <article className="fg-detail" data-screen-label="Event Detail">
      <a className="ev-back" href="#/events" onClick={(e) => go('#/events', e)}>
        ← Alle Events
      </a>

      <header className="fg-detail-header">
        <div className="fg-detail-eyebrow">{event.formatLabel} · {event.venue}</div>
        <h1 className="fg-detail-title">{event.title}</h1>
        <div className="fg-detail-meta">
          <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>{event.venue} · {event.region}</span>
          <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>{event.duration}</span>
          <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>{event.groupMin}–{event.groupMax} Gäste</span>
          <span className="fg-detail-rating">
            <StarGlyph />
            {event.rating} <span className="muted">({event.reviews} Bewertungen)</span>
          </span>
        </div>
      </header>

      <div className="fg-detail-gallery">
        <div className="fg-gallery-main" style={{ backgroundImage: `url('${window.A(event.heroImage)}')` }}>
          <div className="fg-gallery-floating">
            <div className="fg-floating-card sm">
              <div className="fg-floating-thumb" style={{ backgroundImage: `url('${window.A(event.gallery[0] || event.heroImage)}')` }} />
              <div className="fg-floating-body">
                <div className="fg-floating-chip">Nächster freier Termin</div>
                <div className="fg-floating-meta">Donnerstag · 12. Juni · 09:30</div>
              </div>
            </div>
          </div>
        </div>
        <div className="fg-gallery-side">
          {(event.gallery.length >= 2 ? event.gallery.slice(0, 2) : [event.gallery[0] || event.heroImage, event.heroImage])
            .map((g, i) => (
              <div key={i} className="fg-gallery-tile" style={{ backgroundImage: `url('${window.A(g)}')` }} />
            ))}
          <button className="fg-gallery-more">+ alle Fotos</button>
        </div>
      </div>

      <div className="fg-detail-body">
        <div className="fg-detail-main">
          <section>
            <div className="fg-section-eyebrow">So läuft der Tag</div>
            <p className="fg-detail-summary">{event.summary}</p>
          </section>

          <section>
            <div className="fg-section-eyebrow">Im Preis enthalten</div>
            <ul className="fg-includes">
              {event.includes.map((it, i) => (
                <li key={i}><CheckGlyph /><span>{it}</span></li>
              ))}
            </ul>
          </section>

          <section>
            <div className="fg-section-eyebrow">Gut zu wissen</div>
            <div className="fg-good-grid">
              {event.tags.map((t, i) => <div key={i} className="fg-good">{t}</div>)}
            </div>
          </section>

          <section className="fg-quote">
            <div className="fg-quote-mark">"</div>
            <p>Wir haben mit Firmengolf zum dritten Mal in Folge unseren Team-Tag gebucht. Buchung im Self-Service, Ansprechpartner immer erreichbar, Rechnung sauber.</p>
            <div className="fg-quote-attr">— Sandra Klein, HR-Direktorin · Werkstatt 4</div>
          </section>
        </div>

        <aside className="fg-detail-rail">
          <div className="fg-rail-card">
            <div className="fg-rail-live">
              <span className="fg-live-dot" />
              <span>Angebot live seit {liveSince(event)}</span>
            </div>
            <div className="fg-rail-price">ab {main}<span>{suffix}</span></div>
            <div className="fg-rail-fields">
              <div className="fg-rail-field">
                <div className="fg-cell-label">Gruppe</div>
                <div className="fg-cell-value">ab {event.groupMin}, max. {event.groupMax} Personen</div>
              </div>
              <div className="fg-rail-field">
                <div className="fg-cell-label">Buchung</div>
                <div className="fg-cell-value">Als Paket — alles inklusive</div>
              </div>
            </div>
            <button className="fg-btn-brand block" onClick={() => setShowRequest(true)}>
              Dieses Event anfragen
            </button>
            <button className="fg-btn-ghost block" onClick={shareEvent}>
              <span className="fg-share-row">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M12 15V3"/><path d="M8 7l4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
                Event teilen
              </span>
            </button>
            <span id="share-hint" className="fg-share-hint">Link kopiert ✓</span>
            <div className="fg-rail-note">
              Anfrage ist kostenlos. Sie geht direkt an den Golfplatz zur Terminfreigabe und an uns —
              du bekommst eine Antwort innerhalb von 48 Stunden.
            </div>
          </div>
          <div className="fg-rail-host">
            <img src={window.A("assets/imagery/avatar-1.jpg")} alt="" className="fg-rail-host-photo" />
            <div>
              <div className="fg-rail-host-name">Gebucht über Firmengolf</div>
              <div className="fg-rail-host-meta">Ein Ansprechpartner · eine Rechnung</div>
            </div>
          </div>
        </aside>
      </div>

      {showRequest && window.RequestWizard && (
        <window.RequestWizard
          preset={{ eventRef: {
            id: event.id, slug: event.slug, title: event.title, venue: event.venue,
            formatLabel: event.formatLabel, region: event.region,
            groupMin: event.groupMin, groupMax: event.groupMax,
            duration: event.duration,
            includes: event.includes || [], tags: event.tags || [],
            heroImage: event.heroImage,
            priceLabel: event.pricePerPerson ? ('ab €' + event.pricePerPerson + ' p.P.')
              : (event.pricePerGroup ? ('€' + event.pricePerGroup.toLocaleString('de-DE') + ' / Gruppe') : ''),
          } }}
          onClose={() => setShowRequest(false)} />
      )}
    </article>
  );
}
window.EventDetailPage = EventDetailPage;

function EventDetailWithExtras({ event }) {
  if (!event) return <EventDetailPage event={event} />;
  const d = window.SITE_DATA;
  const related = d.events
    .filter(e => e.id !== event.id && (e.format === event.format || e.region === event.region))
    .slice(0, 3);
  return (
    <>
      <EventDetailPage event={event} />
      <EventDetailLocation event={event} />
      <EventDetailFAQ event={event} />
      <EventDetailRelated events={related} />
    </>
  );
}
window.EventDetailWithExtras = EventDetailWithExtras;

function EventDetailLocation({ event }) {
  return (
    <section className="evd-location">
      <div className="evd-location-inner">
        <div className="evd-location-info">
          <div className="mk-eyebrow">Anfahrt & Location</div>
          <h2 className="mk-h2">{event.venue}</h2>
          <p className="evd-location-p">
            Großzügige Anlage, Parkplätze direkt am Clubhaus, barrierearmer Zugang.
            Genaue Adresse und Anfahrtsbeschreibung schicken wir mit der Bestätigung.
          </p>
          <div className="evd-poi-grid">
            <div className="evd-poi"><div className="evd-poi-l">Auto</div><div className="evd-poi-v">15 Min. ab Stadtzentrum</div></div>
            <div className="evd-poi"><div className="evd-poi-l">Bahn</div><div className="evd-poi-v">Shuttle ab Hauptbahnhof</div></div>
            <div className="evd-poi"><div className="evd-poi-l">Parken</div><div className="evd-poi-v">Kostenfrei vor Ort</div></div>
            <div className="evd-poi"><div className="evd-poi-l">Hotel</div><div className="evd-poi-v">3 Partnerhotels in 10 Min.</div></div>
          </div>
        </div>
        <div className="evd-map">
          <iframe
            title={'Karte — ' + event.venue}
            className="evd-map-frame"
            loading="lazy"
            referrerPolicy="no-referrer-when-downgrade"
            src={'https://maps.google.com/maps?q=' + encodeURIComponent('Golfplatz ' + event.venue + ', ' + (event.region || 'Deutschland')) + '&z=12&output=embed'}>
          </iframe>
          <a className="evd-map-open"
             href={'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent('Golfplatz ' + event.venue + ', ' + (event.region || 'Deutschland'))}
             target="_blank" rel="noopener noreferrer">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M7 17L17 7M9 7h8v8"/></svg>
            In Karten öffnen
          </a>
        </div>
      </div>
    </section>
  );
}

function EventDetailFAQ({ event }) {
  return (
    <FAQ
      title={'Häufige Fragen zu diesem ' + event.formatLabel + '.'}
      items={[
        { q: 'Was ist im Preis enthalten?', a: 'Alle Punkte aus der Liste oben — Coaching, Ausrüstung, Green-Fee, Catering wo angegeben. Was nicht enthalten ist: persönliche Getränke an der Bar, optionale Add-ons (Fotograf, Trophäen), eventuelle Übernachtungen.' },
        { q: 'Können Begleitpersonen mitkommen?', a: 'Ja — Partner und Familien sind auf den meisten Plätzen willkommen. Sag uns Bescheid, wir sprechen mit dem Platz und melden uns mit Optionen (Restaurant-Plätze, kleines Kinder-Programm).' },
        { q: 'Wie viel Vorlauf brauchen wir?', a: 'Für dieses Format empfehlen wir 4–6 Wochen Vorlauf. Kurzfristiger ist oft möglich — frag einfach an, wir prüfen Verfügbarkeit.' },
        { q: 'Was passiert bei Regen?', a: 'Wir kommunizieren am Vortag, ob das Programm angepasst wird (Indoor-Backup, gekürzte Runde, Verschiebung). Bei kompletter Absage durch den Platz: voller Storno bis 24 h vor Termin.' },
        { q: 'Gibt es einen Dresscode?', a: 'Smart-Casual. Wir empfehlen Sportschuhe oder Golf-Spikes; Schläger und alles weitere werden gestellt. Keine Krawatten-Pflicht, kein Polo-Zwang.' },
      ]}
    />
  );
}

function EventDetailRelated({ events }) {
  if (!events.length) return null;
  return (
    <section className="mk-section evd-related">
      <div className="mk-section-head between">
        <div>
          <div className="mk-eyebrow">Auch interessant</div>
          <h2 className="mk-h2" style={{ fontSize: 32 }}>Ähnliche Events</h2>
        </div>
        <a className="fg-btn-ghost" href="#/events" onClick={(e) => go('#/events', e)}>
          Alle Events <ArrowGlyph size={12} />
        </a>
      </div>
      <div className="fg-grid">
        {events.map(e => (
          <EventCard key={e.id} event={e} onClick={() => go('#/events/' + e.slug)} />
        ))}
      </div>
    </section>
  );
}
