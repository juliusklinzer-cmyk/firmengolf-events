/* eslint-disable */
/* GC München West — Foto-Leitfaden (Fotoleitfaden.html) */

const FL_SECTIONS = [
  {
    n: "01", t: "Fotografier bei Tageslicht",
    p: "Die schönsten Platzfotos entstehen am frühen Morgen oder in den zwei Stunden vor Sonnenuntergang — das warme, tiefe Licht lässt das Grün leuchten. Meide die harte Mittagssonne und bewölkte, graue Tage.",
    tips: ["Goldene Stunde nutzen (kurz nach Sonnenaufgang / vor Sonnenuntergang)", "Sonne im Rücken oder seitlich, nicht frontal", "Keine künstlichen Filter — natürlich wirkt am stärksten"],
  },
  {
    n: "02", t: "Zeig Menschen in Bewegung",
    p: "Bilder mit echten Menschen werden deutlich häufiger angefragt als leere Plätze. Am besten: Gäste mitten im Schwung, beim Putten, beim Anstoßen auf der Terrasse — keine gestellten Gruppenfotos in die Kamera.",
    tips: ["Action statt Pose: Abschlag, Putt, Lachen am Loch", "Teams gemeinsam unterwegs auf dem Fairway", "Auf Einverständnis der abgebildeten Personen achten"],
  },
  {
    n: "03", t: "Quer, scharf & hochauflösend",
    p: "Für Event-Karten und Titelbilder brauchen wir Querformat (16:9 oder 3:2) in hoher Auflösung. Hochkant-Handyfotos lassen sich schlecht zuschneiden. Mindestens 1920 px breit, gestochen scharf.",
    tips: ["Querformat für Titelbild & Event-Karten", "Mind. 1920 px Breite, besser mehr", "Linse kurz sauber wischen — Smartphone reicht völlig"],
  },
  {
    n: "04", t: "Erzähl deinen Platz",
    p: "Zeig den Kontext, der euch ausmacht: weite Fairways, alter Baumbestand, das Clubhaus, die Terrasse, der Blick übers Grün. Großzügige Ausschnitte mit Gras, Bäumen und Himmel wirken einladender als enge Detailaufnahmen von Equipment.",
    tips: ["Weite statt enger Crops", "Eure Besonderheit einfangen (Wasser, Bergblick, Clubhaus)", "Eine ruhige Bildsprache — nicht überladen"],
  },
  {
    n: "05", t: "Echt & natürlich halten",
    p: "Keine schweren Filter, kein Schwarz-Weiß, kein Kunstlicht-Look. Ein leicht warmer Ton ist gut, der Rest soll wirken wie ein schöner Tag auf dem Platz — ehrlich und einladend.",
    tips: ["Leicht warmer Look, keine künstlichen Effekte", "Keine Logos oder Text ins Bild stempeln", "Lieber wenige starke Bilder als viele mittelmäßige"],
  },
];

const FL_AVOID = [
  "Stockfotos von fremden Plätzen",
  "Dunkle oder graue Aufnahmen",
  "Hochkant-Schnappschüsse fürs Titelbild",
  "Enge Crops von Schlägern & Bällen",
  "Schwarz-Weiß oder starke Filter",
  "Unscharfe oder verpixelte Bilder",
];

const FotoleitfadenApp = () => (
  <div>
    <TopNav activeTab="" />
    <div className="page-wide">
      <div className="page-head">
        <div className="eyebrow">Hilfe · Bilder</div>
        <h1>Der <em>Foto-Leitfaden</em></h1>
        <p>Angebote mit eigenen Platzfotos erhalten rund <strong>3× mehr Anfragen</strong> als solche mit Stock-Bildern. Mit diesen fünf Punkten holst du das Beste aus deinem Platz heraus — Smartphone genügt.</p>
      </div>

      <div className="fl-list">
        {FL_SECTIONS.map(s => (
          <section className="fl-item" key={s.n}>
            <div className="fl-num">{s.n}</div>
            <div className="fl-body">
              <h2 className="fl-t">{s.t}</h2>
              <p className="fl-p">{s.p}</p>
              <ul className="fl-tips">
                {s.tips.map((t, i) => (
                  <li key={i}><span className="fl-check"><Icon name="check" size={12} sw={2.6} /></span>{t}</li>
                ))}
              </ul>
            </div>
          </section>
        ))}
      </div>

      <section className="fl-avoid">
        <h2 className="fl-avoid-h">Lieber vermeiden</h2>
        <div className="fl-avoid-grid">
          {FL_AVOID.map((a, i) => (
            <div className="fl-avoid-item" key={i}><span className="fl-x"><Icon name="x" size={13} sw={2.4} /></span>{a}</div>
          ))}
        </div>
      </section>

      <div className="fl-cta">
        <div>
          <div className="fl-cta-h">Bereit, Bilder hochzuladen?</div>
          <p>Du kannst Titelbild und Galerie jederzeit in deinem Platzprofil ändern.</p>
        </div>
        <a className="btn btn-brand" href="Platz.html#galerie"><Icon name="image" size={15} /> Zur Galerie</a>
      </div>

      <Footer />
    </div>
  </div>
);

const flRoot = ReactDOM.createRoot(document.getElementById("app"));
flRoot.render(<FotoleitfadenApp />);
