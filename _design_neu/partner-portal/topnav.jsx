/* eslint-disable */
/* Shared top navigation — used by every partner-portal page. */

// Lightweight toast for actions that are mocked (save, send, upload, …) so
// every control gives feedback instead of feeling dead.
window.fgToast = function (msg) {
  let host = document.getElementById("fg-toast-host");
  if (!host) {
    host = document.createElement("div");
    host.id = "fg-toast-host";
    document.body.appendChild(host);
  }
  const el = document.createElement("div");
  el.className = "fg-toast";
  el.innerHTML = '<span class="fg-toast-ic">✓</span>' + msg;
  host.appendChild(el);
  requestAnimationFrame(() => el.classList.add("in"));
  setTimeout(() => { el.classList.remove("in"); setTimeout(() => el.remove(), 300); }, 2600);
};

const NEW_REQUESTS = (typeof REQUESTS !== "undefined")
  ? REQUESTS.filter(r => r.status === "neu").length
  : 5;

const TopNav = ({ activeTab = "uebersicht" }) => (
  <nav className="nav">
    <div className="nav-inner">
      <a className="brand" href="Profil.html">
        <img src="design-system/logo/firmengolf-wordmark.png" alt="Firmengolf" />
        <span className="brand-divider"></span>
        <span className="brand-context">
          Partner-Portal
          <span className="pill">Live</span>
        </span>
      </a>

      <div className="nav-tabs">
        <a href="Profil.html" className={`nav-tab ${activeTab === "uebersicht" ? "active" : ""}`}>
          <Icon name="chart" size={15} /> Übersicht
        </a>
        <a href="Profil.html#angebote" className={`nav-tab ${activeTab === "angebote" ? "active" : ""}`}>
          <Icon name="flag" size={15} /> Angebote
        </a>
        <a href="Anfragen.html" className={`nav-tab ${activeTab === "anfragen" ? "active" : ""}`}>
          <Icon name="inbox" size={15} /> Anfragen
          {NEW_REQUESTS > 0 && <span className="badge">{NEW_REQUESTS}</span>}
        </a>
        <a href="Platz.html" className={`nav-tab ${activeTab === "platz" ? "active" : ""}`}>
          <Icon name="map" size={15} /> Platz
        </a>
        <a href="Team.html" className={`nav-tab ${activeTab === "team" ? "active" : ""}`}>
          <Icon name="users" size={15} /> Ansprechpartner
        </a>
      </div>

      <div className="nav-end">
        <a className="nav-link" href="../index.html#/events" target="_blank" rel="noopener">
          <Icon name="eye" size={15} /> Vorschau auf Firmengolf
        </a>
        <a className="nav-link" style={{ padding: 8 }} onClick={() => window.fgToast("Keine neuen Benachrichtigungen")}>
          <Icon name="bell" size={17} />
        </a>
        <a className="nav-avatar" href="Platz.html" title="GC München West">SR</a>
      </div>
    </div>
  </nav>
);

// Shared footer — used by every page.
const Footer = () => (
  <footer className="foot">
    <div>© 2026 Firmengolf · Partner-Portal v2.4</div>
    <div className="links">
      <a onClick={() => window.fgToast("Hilfe-Center wird geöffnet")}>Hilfe-Center</a>
      <a onClick={() => window.fgToast("Changelog wird geöffnet")}>Was ist neu?</a>
      <a href="mailto:partner@firmengolf.de">Support kontaktieren</a>
    </div>
  </footer>
);

Object.assign(window, { TopNav, Footer });
