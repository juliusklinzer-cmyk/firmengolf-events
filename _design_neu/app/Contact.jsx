/* eslint-disable */
// =============================================================
// Contact page — low-barrier, multi-channel, accessible.
// Goal: meet every customer where they are. Some want to call,
// some WhatsApp, some write, some are phone-shy and want a callback,
// some want a fixed slot. All options are first-class.
//
// Accessibility: every control has a <label for>, hints are wired with
// aria-describedby, required fields are marked, focus rings are visible,
// tap targets ≥ 44px, motion respects prefers-reduced-motion (CSS).
// =============================================================
// (asset helper provided as window.A)
var { useState } = React;

// ---------- inline channel icons (Lucide-style, 1.5 stroke) ----------
function CIcon({ name, size = 22 }) {
  const p = {
    phone:   <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>,
    whatsapp: <><path d="M3 21l1.65-4.5A8.5 8.5 0 1 1 7.5 19.3z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5"/><path d="M9 9.5c0-.6.4-1 1-1l1 .2.5 1.5-.8.8M14.5 15c.6 0 1-.4 1-1l-.2-1-1.5-.5-.8.8"/></>,
    mail:    <><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 6.5l9 6 9-6"/></>,
    chat:    <path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3 8.38 8.38 0 0 1 21 11.5z"/>,
    calendar:<><rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></>,
    pin:     <><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></>,
    clock:   <><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></>,
    check:   <path d="M20 6L9 17l-5-5"/>,
    accessibility: <><circle cx="12" cy="4.5" r="1.6"/><path d="M5 8.5c2 .9 4.4 1.4 7 1.4s5-.5 7-1.4M12 9.9v5.1M9 21l3-6 3 6"/></>,
  };
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
         strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {p[name]}
    </svg>
  );
}

// =============================================================
// Quick channels — the lowest-barrier ways to reach us
// =============================================================
function QuickChannels() {
  return (
    <section className="ct-channels" aria-label="Direkte Kontaktwege">
      <a className="ct-channel" href="tel:+498912345678">
        <span className="ct-channel-ic"><CIcon name="phone" /></span>
        <span className="ct-channel-body">
          <span className="ct-channel-l">Anrufen</span>
          <span className="ct-channel-v">+49 89 123 456 78</span>
          <span className="ct-channel-m">Mo–Fr · 9–18 Uhr</span>
        </span>
        <span className="ct-channel-go" aria-hidden="true"><ArrowGlyph size={14} /></span>
      </a>

      <a className="ct-channel ct-channel-wa" href="https://wa.me/498912345678" target="_blank" rel="noopener noreferrer">
        <span className="ct-channel-ic"><CIcon name="chat" /></span>
        <span className="ct-channel-body">
          <span className="ct-channel-l">WhatsApp</span>
          <span className="ct-channel-v">Kurz schreiben</span>
          <span className="ct-channel-m">Antwort meist in Minuten</span>
        </span>
        <span className="ct-channel-go" aria-hidden="true"><ArrowGlyph size={14} /></span>
      </a>

      <a className="ct-channel" href="mailto:hallo@firmengolf.de">
        <span className="ct-channel-ic"><CIcon name="mail" /></span>
        <span className="ct-channel-body">
          <span className="ct-channel-l">E-Mail</span>
          <span className="ct-channel-v">hallo@firmengolf.de</span>
          <span className="ct-channel-m">Antwort in einem Werktag</span>
        </span>
        <span className="ct-channel-go" aria-hidden="true"><ArrowGlyph size={14} /></span>
      </a>

      <a className="ct-channel" href="#callback"
         onClick={(e) => { e.preventDefault(); document.getElementById('callback')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); document.getElementById('cb-phone')?.focus(); }}>
        <span className="ct-channel-ic"><CIcon name="phone" /></span>
        <span className="ct-channel-body">
          <span className="ct-channel-l">Rückruf anfordern</span>
          <span className="ct-channel-v">Wir rufen dich an</span>
          <span className="ct-channel-m">Du nennst Zeit & Nummer</span>
        </span>
        <span className="ct-channel-go" aria-hidden="true"><ArrowGlyph size={14} /></span>
      </a>
    </section>
  );
}

// =============================================================
// Callback widget — lowest barrier for phone-shy people
// =============================================================
function CallbackCard() {
  const [done, setDone] = useState(false);
  const [phone, setPhone] = useState('');
  const [when, setWhen] = useState('Egal');
  return (
    <div className="ct-callback" id="callback">
      {!done ? (
        <form onSubmit={(e) => { e.preventDefault(); if (phone.trim()) setDone(true); }}>
          <div className="ct-callback-head">
            <span className="ct-callback-ic"><CIcon name="phone" size={20} /></span>
            <div>
              <h3 className="ct-callback-h">Lass dich zurückrufen.</h3>
              <p className="ct-callback-p">Keine Lust zu tippen oder in der Warteschleife zu hängen? Nummer rein, wir melden uns.</p>
            </div>
          </div>
          <div className="ct-callback-row">
            <div className="fg-field" style={{ flex: 2 }}>
              <label className="fg-field-label" htmlFor="cb-phone">Deine Nummer</label>
              <input id="cb-phone" className="fg-input" type="tel" inputMode="tel"
                     value={phone} onChange={(e) => setPhone(e.target.value)}
                     placeholder="+49 …" required aria-required="true" />
            </div>
            <div className="fg-field" style={{ flex: 1 }}>
              <label className="fg-field-label" htmlFor="cb-when">Wann passt's?</label>
              <select id="cb-when" className="fg-input" value={when} onChange={(e) => setWhen(e.target.value)}>
                <option>Egal</option>
                <option>Vormittags</option>
                <option>Nachmittags</option>
                <option>Früher Abend</option>
              </select>
            </div>
            <button className="fg-btn-brand ct-callback-btn" type="submit">
              Rückruf anfordern
            </button>
          </div>
        </form>
      ) : (
        <div className="ct-callback-done" role="status">
          <span className="ct-callback-ic done"><CIcon name="check" size={20} /></span>
          <div>
            <h3 className="ct-callback-h">Alles klar — wir rufen dich an.</h3>
            <p className="ct-callback-p">{when === 'Egal' ? 'Wir melden uns im Laufe des nächsten Werktags.' : when + ' melden wir uns.'} an <strong>{phone}</strong>.</p>
          </div>
        </div>
      )}
    </div>
  );
}

// =============================================================
// Main form — accessible, intent-aware
// =============================================================
const TOPICS = [
  { id: 'event',   label: 'Event anfragen' },
  { id: 'individ', label: 'Individuelles Event' },
  { id: 'partner', label: 'Partnerplatz werden' },
  { id: 'benefit', label: 'Benefit-Programm' },
  { id: 'press',   label: 'Presse' },
  { id: 'other',   label: 'Etwas anderes' },
];

function ContactForm() {
  const [sent, setSent] = useState(false);
  const [topic, setTopic] = useState('event');
  const [pref, setPref] = useState('Egal');
  const ref = 'FG-' + Math.floor(100000 + Math.random() * 900000);

  if (sent) {
    return (
      <div className="contact-success" role="status" aria-live="polite">
        <div className="fg-success-mark"><CheckGlyph size={22} /></div>
        <h2 className="contact-form-h">Danke — wir haben dich.</h2>
        <p className="muted" style={{ marginTop: 12, maxWidth: 420 }}>
          Deine Nachricht ist angekommen. Wir melden uns innerhalb eines Werktags — bei dir bevorzugt per {pref === 'Egal' ? 'Mail oder Telefon' : pref}.
        </p>
        <div className="ct-success-ref">
          <span>Vorgangs-Nr.</span>
          <span className="mono">{ref}</span>
        </div>
        <button className="fg-btn-ghost" onClick={() => setSent(false)} style={{ marginTop: 24 }}>
          Neue Nachricht
        </button>
      </div>
    );
  }

  return (
    <>
      <div className="mk-eyebrow">Schreib uns</div>
      <h2 className="contact-form-h">Sag uns kurz, worum es geht.</h2>
      <p className="muted" style={{ marginTop: 8 }}>
        Ein, zwei Sätze reichen. Pflichtfelder sind mit <span className="ct-req">*</span> markiert.
      </p>

      <form onSubmit={(e) => { e.preventDefault(); setSent(true); }} noValidate>
        {/* Topic — segmented chips, keyboard accessible */}
        <fieldset className="ct-fieldset">
          <legend className="fg-field-label">Worum geht's?</legend>
          <div className="ct-topic-row" role="radiogroup" aria-label="Thema">
            {TOPICS.map(t => (
              <button key={t.id} type="button" role="radio" aria-checked={topic === t.id}
                      className={'ct-topic ' + (topic === t.id ? 'on' : '')}
                      onClick={() => setTopic(t.id)}>
                {t.label}
              </button>
            ))}
          </div>
        </fieldset>

        <div className="contact-form-grid">
          <div className="fg-field">
            <label className="fg-field-label" htmlFor="ct-name">Name <span className="ct-req">*</span></label>
            <input id="ct-name" className="fg-input" placeholder="Vor- und Nachname" required aria-required="true" autoComplete="name" />
          </div>
          <div className="fg-field">
            <label className="fg-field-label" htmlFor="ct-company">Firma</label>
            <input id="ct-company" className="fg-input" placeholder="Firmenname" autoComplete="organization" />
          </div>
          <div className="fg-field">
            <label className="fg-field-label" htmlFor="ct-email">E-Mail <span className="ct-req">*</span></label>
            <input id="ct-email" className="fg-input" type="email" placeholder="du@firma.de"
                   required aria-required="true" autoComplete="email" aria-describedby="ct-email-hint" />
            <span className="fg-field-help" id="ct-email-hint">Nur für unsere Antwort — kein Newsletter.</span>
          </div>
          <div className="fg-field">
            <label className="fg-field-label" htmlFor="ct-phone">Telefon</label>
            <input id="ct-phone" className="fg-input" type="tel" inputMode="tel" placeholder="+49 …" autoComplete="tel" />
          </div>
          <div className="fg-field fg-field-full">
            <label className="fg-field-label" htmlFor="ct-msg">Deine Nachricht <span className="ct-req">*</span></label>
            <textarea id="ct-msg" className="fg-input" rows={5} required aria-required="true"
                      placeholder={topic === 'partner'
                        ? 'Name & Lage des Platzes, was ihr anbieten könnt …'
                        : 'Anlass, Gruppengröße, Zeitraum — oder einfach deine Frage.'} />
          </div>

          {/* Preferred contact method — barrier-lowering choice */}
          <fieldset className="ct-fieldset fg-field-full">
            <legend className="fg-field-label">Wie sollen wir antworten?</legend>
            <div className="ct-pref-row" role="radiogroup" aria-label="Bevorzugter Kontaktweg">
              {['Egal', 'E-Mail', 'Telefon', 'WhatsApp'].map(o => (
                <button key={o} type="button" role="radio" aria-checked={pref === o}
                        className={'ct-pref ' + (pref === o ? 'on' : '')}
                        onClick={() => setPref(o)}>
                  {o}
                </button>
              ))}
            </div>
          </fieldset>

          <label className="contact-consent fg-field-full" htmlFor="ct-consent">
            <input id="ct-consent" type="checkbox" required aria-required="true" />
            <span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß <a href="#/datenschutz" onClick={(e) => go('#/datenschutz', e)}>Datenschutzerklärung</a> zu.</span>
          </label>
        </div>

        <div className="contact-form-foot">
          <button type="submit" className="fg-btn-brand">
            Nachricht senden
            <span className="fg-arrow"><ArrowGlyph /></span>
          </button>
          <span className="fg-rail-note">Antwort innerhalb eines Werktags. Kein Vertriebs-Druck.</span>
        </div>
      </form>
    </>
  );
}

// =============================================================
// Page
// =============================================================
function ContactPage() {
  return (
    <div data-screen-label="Contact">

      {/* Hero */}
      <section className="ct-hero">
        <div className="mk-eyebrow">Kontakt</div>
        <h1 className="ct-hero-h">
          Frag uns alles — du landest bei einem <span className="mk-italic">echten Menschen</span>.
        </h1>
        <p className="ct-hero-sub">
          Kein Chatbot, kein Ticketsystem, keine Warteschleife ins Nichts. Wähl den Weg, der dir
          am liebsten ist — wir antworten innerhalb eines Werktags, oft schneller.
        </p>
      </section>

      <QuickChannels />

      {/* Form + sidebar */}
      <section className="contact-shell" id="kontaktformular">
        <aside className="contact-form-card">
          <ContactForm />
        </aside>

        <div className="contact-left">
          {/* Founder reassurance */}
          <div className="ct-person">
            <image-slot
              id="contact-person"
              class="ct-person-photo"
              shape="circle"
              src={window.A('assets/imagery/avatar-4.jpg')}
              placeholder="Foto">
            </image-slot>
            <div>
              <div className="ct-person-q">„Anfragen landen direkt bei mir. Ich antworte persönlich — versprochen."</div>
              <div className="ct-person-id">
                <span className="ct-person-name" contentEditable suppressContentEditableWarning>Dein Name</span>
                <span className="ct-person-role">Gründer · Firmengolf</span>
              </div>
            </div>
          </div>

          {/* Response promise */}
          <div className="ct-promise">
            <div className="ct-promise-row">
              <span className="ct-promise-ic"><CIcon name="clock" size={18} /></span>
              <div>
                <div className="ct-promise-t">Antwort in einem Werktag</div>
                <div className="ct-promise-m">Freitagnachmittag–Sonntag: Montag früh</div>
              </div>
            </div>
            <div className="ct-promise-row">
              <span className="ct-promise-ic"><CIcon name="check" size={18} /></span>
              <div>
                <div className="ct-promise-t">Unverbindlich &amp; kostenlos</div>
                <div className="ct-promise-m">Erst beraten, dann entscheiden</div>
              </div>
            </div>
            <div className="ct-promise-row">
              <span className="ct-promise-ic"><CIcon name="accessibility" size={18} /></span>
              <div>
                <div className="ct-promise-t">So, wie's dir passt</div>
                <div className="ct-promise-m">Mail, Telefon, WhatsApp — oder einfache Sprache auf Wunsch</div>
              </div>
            </div>
          </div>

          {/* Direct addresses by topic */}
          <div className="ct-directory">
            <div className="ct-dir-h">Direkt an die richtige Stelle</div>
            <a className="ct-dir-row" href="mailto:events@firmengolf.de">
              <span>Event-Anfragen</span>
              <span className="ct-dir-v">events@firmengolf.de</span>
            </a>
            <a className="ct-dir-row" href="mailto:partner@firmengolf.de">
              <span>Partnerplätze</span>
              <span className="ct-dir-v">partner@firmengolf.de</span>
            </a>
            <a className="ct-dir-row" href="mailto:presse@firmengolf.de">
              <span>Presse &amp; Medien</span>
              <span className="ct-dir-v">presse@firmengolf.de</span>
            </a>
          </div>
        </div>
      </section>

      {/* Callback band */}
      <section className="ct-callback-section">
        <CallbackCard />
      </section>

      {/* Prefer a slot / visit us */}
      <section className="ct-extra">
        <div className="ct-extra-grid">
          <div className="ct-extra-card">
            <span className="ct-extra-ic"><CIcon name="calendar" size={24} /></span>
            <h3 className="ct-extra-h">Lieber ein fester Termin?</h3>
            <p className="ct-extra-p">
              Buch dir 15 Minuten für ein lockeres Kennenlern-Gespräch — Video oder Telefon, ganz wie du magst.
            </p>
            <a className="fg-btn-brand" href="#" onClick={(e) => e.preventDefault()}>
              Termin buchen <span className="fg-arrow"><ArrowGlyph /></span>
            </a>
          </div>
          <div className="ct-extra-card ct-extra-card-visit">
            <div className="ct-extra-map" aria-hidden="true">
              <div className="ct-extra-map-grid" />
              <div className="ct-extra-map-pin" />
            </div>
            <div className="ct-extra-map-body">
              <span className="ct-extra-ic"><CIcon name="pin" size={24} /></span>
              <h3 className="ct-extra-h">Komm vorbei.</h3>
              <p className="ct-extra-p">
                Visionpunch UG · München. Auf einen Kaffee — kurz vorher anrufen, dann ist jemand da.
              </p>
              <a className="fg-btn-ghost" href="#" onClick={(e) => e.preventDefault()}>
                Route anzeigen <ArrowGlyph size={12} />
              </a>
            </div>
          </div>
        </div>
      </section>

      <FAQ
        title="Antworten, bevor du fragst."
        intro="Vieles klärt sich in einem Satz. Was nicht hier steht — frag einfach, auf dem Weg, der dir passt."
        items={[
          { q: 'Wie schnell antwortet ihr?', a: 'Werktags innerhalb eines Arbeitstags, per WhatsApp meist in Minuten. Anfragen von Freitagnachmittag bis Sonntag beantworten wir Montag früh.' },
          { q: 'Ich rede lieber, als zu tippen — geht das?', a: 'Klar. Ruf direkt an, schreib uns auf WhatsApp, oder fordere oben einen Rückruf an — dann melden wir uns zur gewünschten Zeit bei dir.' },
          { q: 'Ich bin Golfplatz und will Partner werden — wohin?', a: 'Schreib an partner@firmengolf.de oder starte direkt das Partner-Onboarding über „Partnerportal" oben. Bei größeren Anlagen kommen wir auch persönlich vorbei.' },
          { q: 'Ich habe eine Frage zum Benefit-Programm.', a: 'Das läuft über firmen.golf — schreib uns trotzdem hier, wir leiten weiter und sorgen, dass du eine Antwort bekommst.' },
          { q: 'Brauche ich ein Konto, um euch zu kontaktieren?', a: 'Nein. Kein Login, keine Registrierung — Formular, Mail, Anruf oder WhatsApp genügen.' },
          { q: 'Geht das auch auf Englisch?', a: 'Yes — wir antworten gerne auf Deutsch oder Englisch. Schreib einfach in deiner Sprache.' },
        ]}
      />
    </div>
  );
}
window.ContactPage = ContactPage;
