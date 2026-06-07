/* eslint-disable */
// =============================================================
// About page — founder-led, trust-building.
// Centerpiece: a founder video the user uploads (drag/drop or click).
// Portrait uses the persistent <image-slot>. Copy is first-person,
// in brand voice (du, unbeschwert/inspirierend/mitfühlend).
// =============================================================
// (asset helper provided as window.A)

const { useState: useAboutState, useRef: useAboutRef } = React;

// ---------- Founder video slot ----------
// Drag/drop or click to load a local video for preview. Plays via object URL.
// (Preview only — not persisted; in production the src points to a hosted file.)
function FounderVideo() {
  const [src, setSrc] = useAboutState(null);
  const [drag, setDrag] = useAboutState(false);
  const [playing, setPlaying] = useAboutState(false);
  const inputRef = useAboutRef();
  const videoRef = useAboutRef();

  const loadFile = (file) => {
    if (file && file.type.startsWith('video/')) {
      setSrc(URL.createObjectURL(file));
      setPlaying(false);
    }
  };
  const onDrop = (e) => {
    e.preventDefault(); setDrag(false);
    loadFile(e.dataTransfer.files && e.dataTransfer.files[0]);
  };
  const play = () => {
    if (videoRef.current) { videoRef.current.play(); setPlaying(true); }
  };

  return (
    <div className="about-video-wrap">
      <div
        className={'about-video ' + (drag ? 'is-drag ' : '') + (src ? 'is-filled ' : '')}
        onDragOver={(e) => { e.preventDefault(); setDrag(true); }}
        onDragLeave={() => setDrag(false)}
        onDrop={onDrop}>

        {src ? (
          <video
            ref={videoRef}
            src={src}
            controls
            playsInline
            className="about-video-el"
            poster={window.A('assets/imagery/hero-forest.jpg')}
          />
        ) : (
          <button type="button" className="about-video-ph" onClick={() => inputRef.current?.click()}>
            <div className="about-video-poster" style={{ backgroundImage: `url('${window.A('assets/imagery/hero-forest.jpg')}')` }} />
            <div className="about-video-scrim" />
            <div className="about-video-center">
              <span className="about-play">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden>
                  <path d="M8 5v14l11-7z" />
                </svg>
              </span>
              <div className="about-video-label">Eine Minute mit dem Gründer</div>
              <div className="about-video-hint">
                {drag ? 'Video hier ablegen' : 'Klicken zum Hochladen — oder Video hierher ziehen'}
              </div>
            </div>
            <span className="about-video-badge">Video</span>
          </button>
        )}

        <input
          ref={inputRef}
          type="file"
          accept="video/*"
          hidden
          onChange={(e) => loadFile(e.target.files && e.target.files[0])}
        />
      </div>

      <div className="about-video-caption">
        <span className="about-video-cap-dot" />
        <span>
          {src
            ? 'Dein Video ist geladen — so sieht es auf der Seite aus.'
            : 'Platzhalter — hier kommt dein persönliches Video. Unkomprimiert hochladen, wir kümmern uns ums Hosting.'}
        </span>
      </div>
    </div>
  );
}

function AboutPage() {
  return (
    <div data-screen-label="About">

      {/* ===== Hero — vision statement ===== */}
      <section className="about-hero">
        <div className="about-hero-inner">
          <div className="mk-eyebrow">Über uns</div>
          <h1 className="about-hero-h">
            Golf ist für jeden, der mal <span className="mk-italic">raus</span> will.
          </h1>
          <p className="about-hero-sub">
            Ich habe Firmengolf gegründet, weil Golf einfach guttut: raus an die frische Luft,
            rein in Bewegung, mitten ins Gespräch. Ein paar Stunden, die ein Team enger
            zusammenbringen als zehn Meetings — und dabei richtig Spaß machen.
          </p>
        </div>
      </section>

      {/* ===== Founder video — centerpiece ===== */}
      <section className="about-video-section">
        <div className="about-video-head">
          <div className="mk-eyebrow">In meinen Worten</div>
          <h2 className="about-video-title">
            Warum Golf für <span className="mk-italic">jeden</span> etwas Gutes hat.
          </h2>
          <p className="about-video-lead">
            Zwei Minuten, in denen ich dir erzähle, was Firmengolf ist, wovon wir träumen —
            und warum ein Schläger in der Hand mehr verändern kann, als man denkt.
          </p>
        </div>
        <FounderVideo />
      </section>

      {/* ===== Founder letter ===== */}
      <section className="about-letter">
        <div className="about-letter-grid">
          <div className="about-letter-aside">
            <image-slot
              id="founder-portrait"
              class="about-portrait"
              shape="rounded"
              radius="20"
              src={window.A('assets/imagery/avatar-4.jpg')}
              placeholder="Dein Foto hierher ziehen">
            </image-slot>
            <div className="about-letter-id">
              <div className="about-letter-name">Julius Klinzer</div>
              <div className="about-letter-role">Gründer · Firmengolf</div>
              <a className="about-linkedin" href="https://www.linkedin.com/in/julius-klinzer-a724b6133/" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                  <path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.35V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.55V9h3.57v11.45zM22.22 0H1.77C.8 0 0 .78 0 1.74v20.52C0 23.22.8 24 1.77 24h20.45c.98 0 1.78-.78 1.78-1.74V1.74C24 .78 23.2 0 22.22 0z"/>
                </svg>
                LinkedIn
              </a>
            </div>
          </div>

          <div className="about-letter-body">
            <div className="mk-eyebrow">Persönlich</div>
            <h2 className="about-letter-h">
              Lange dachte ich: Golf spiele ich, wenn ich mal <span className="mk-italic">alt</span> bin.
            </h2>
            <p className="about-p">
              Dann machten mein Vater und mein Bruder ihre Platzreife — und sprachen von
              nichts anderem mehr. Ich saß daneben und dachte: Wie kann man sich von so einem
              Spiel derart anstecken lassen? Wenn ich das mal spiele, zeige ich euch, wie's geht.
            </p>
            <p className="about-p">
              Tja. Als ich den Schläger dann in meiner eigenen Platzreife in der Hand hielt,
              war alles klar — jetzt hatte es mich auch erwischt. Golf holt dich raus aus dem
              Alltag. Ein guter Schlag macht zehn schlechte wett. Und sich aufzuregen bringt
              nichts: Der nächste Schlag wird davon kein Stück besser.
            </p>
            <p className="about-p">
              Man lernt so viel nebenbei, das sich aufs echte Leben übertragen lässt. Gleichzeitig
              bist du draußen, kannst den Kopf abschalten und triffst großartige, interessante
              Menschen — vom Bodenleger bis zum Vorstand ist alles dabei.
            </p>
            <p className="about-p">
              Genau das will ich mit Firmengolf zugänglich machen. Nicht als Erlebnis für wenige,
              sondern für alle. Denn Golf ist für jeden da.
            </p>
            <div className="about-signature">
              Julius Klinzer
            </div>
          </div>
        </div>
      </section>

      {/* ===== Philosophy — what golf is ===== */}
      <section className="about-philo">
        <div className="about-philo-inner">
          <div className="about-philo-head">
            <div className="mk-eyebrow" style={{ color: 'var(--fairway-300)' }}>Unsere Überzeugung</div>
            <h2 className="about-philo-h">
              Was Golf für uns <span className="mk-italic">wirklich</span> ist.
            </h2>
          </div>
          <div className="about-philo-grid">
            <div className="about-philo-card">
              <div className="about-philo-n">Bewegung</div>
              <p>Eine Runde sind vier, fünf Kilometer an der frischen Luft — ganz ohne das Gefühl, Sport zu machen. Der Körper dankt es, der Kopf auch.</p>
            </div>
            <div className="about-philo-card">
              <div className="about-philo-n">Begegnung</div>
              <p>Vier Stunden ohne Handy, Seite an Seite. Es gibt kaum ein Format, bei dem ein Team so unangestrengt zusammenwächst.</p>
            </div>
            <div className="about-philo-card">
              <div className="about-philo-n">Ruhe</div>
              <p>Golf zwingt zur Konzentration auf den Moment. Genau das, was im Büroalltag am meisten fehlt — und am meisten heilt.</p>
            </div>
          </div>
        </div>
      </section>

      {/* ===== Values — three voice pillars ===== */}
      <section className="mk-section">
        <div className="mk-section-head">
          <div className="mk-eyebrow">Wie wir sind</div>
          <h2 className="mk-h2">Drei Dinge, auf die du dich bei uns verlassen kannst.</h2>
        </div>
        <div className="about-values-grid">
          <div className="about-value-row">
            <div className="about-value-k">Unbeschwert</div>
            <p>Wir haben Spaß und feiern ihn nicht. Kein VIP, kein „world-class", kein Druck — einfach ein guter Tag draußen.</p>
          </div>
          <div className="about-value-row">
            <div className="about-value-k">Inspirierend</div>
            <p>Wir verkaufen kein Produkt, wir verkaufen ein Gefühl: Bewegung, Natur, Konzentration und gemeinsame Zeit.</p>
          </div>
          <div className="about-value-row">
            <div className="about-value-k">Mitfühlend</div>
            <p>Direkt, persönlich, niemals belehrend. Du bekommst immer einen echten Menschen ans Telefon — kein Ticketsystem.</p>
          </div>
        </div>
      </section>

      {/* ===== Closing CTA ===== */}
      <section className="mk-cta">
        <div className="mk-cta-inner">
          <div className="mk-eyebrow" style={{ color: 'rgba(251,250,246,0.65)' }}>Lust auf eine Runde mit uns?</div>
          <h2 className="mk-cta-h">
            Lass uns <span className="mk-italic">kennenlernen</span>.
          </h2>
          <p className="mk-cta-sub">
            Ob Event-Anfrage, Idee oder einfach eine Frage — schreib mir. Ich antworte persönlich.
          </p>
          <div className="mk-cta-ctas">
            <a className="fg-btn-ink lg" href="#/kontakt" onClick={(e) => go('#/kontakt', e)}
               style={{ background: 'var(--paper-100)', color: 'var(--fairway-900)' }}>
              Kontakt aufnehmen
              <span className="fg-arrow" style={{ background: 'var(--fairway-200)' }}><ArrowGlyph /></span>
            </a>
            <a className="mk-cta-mail" href="mailto:hallo@firmengolf.de">hallo@firmengolf.de</a>
          </div>
        </div>
      </section>
    </div>
  );
}
window.AboutPage = AboutPage;
