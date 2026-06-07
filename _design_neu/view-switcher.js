/* =====================================================================
   Firmengolf — View Switcher (Demo-Navigationshilfe)
   A floating capsule (bottom-left) to jump between the three product
   perspectives: Öffentliche Seite · Partner-Portal · Admin.
   Self-contained: injects its own styles, auto-detects path depth.
   Drop into any page with:  <script src="[../]view-switcher.js"></script>
   ===================================================================== */
(function () {
  if (window.__fgSwitcher) return;
  window.__fgSwitcher = true;

  // ---- path prefix: root pages = "", partner-portal pages = "../" ----
  var inPortal = /\/partner-portal\//.test(location.pathname);
  var P = inPortal ? "../" : "";
  var here = location.pathname.split("/").pop() || "index.html";

  // ---- which persona are we currently in? ----
  var view = inPortal ? "partner"
    : /admin\.html/.test(here) ? "admin"
    : /partner-login|partner-onboarding/.test(here) ? "partner"
    : "public";

  // ---- the three perspectives + their quick links ----
  var GROUPS = [
    {
      id: "public", label: "Öffentliche Seite", who: "Firma · Besucher",
      icon: '<path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/>',
      home: P + "index.html#/",
      links: [
        ["Startseite", P + "index.html#/"],
        ["Firmenevents", P + "index.html#/events"],
        ["Golfplätze (Karte)", P + "index.html#/golfplaetze"],
        ["Individuelle Events", P + "index.html#/individuell"],
        ["SEO-Stadtseite: München", P + "index.html#/golf-events/muenchen"],
        ["SEO-Stadtseite: Hamburg", P + "index.html#/golf-events/hamburg"],
        ["SEO-Stadtseite: Köln", P + "index.html#/golf-events/koeln"],
        ["Anfrage-Login (Partner)", P + "partner-login.html"],
      ],
    },
    {
      id: "partner", label: "Partner-Portal", who: "Golfplatz · GC München West",
      icon: '<path d="M6 21V4l9 2.5L6 9"/><circle cx="18" cy="17" r="3"/>',
      home: P + "partner-portal/Profil.html",
      links: [
        ["Übersicht", P + "partner-portal/Profil.html"],
        ["Anfragen", P + "partner-portal/Anfragen.html"],
        ["Platz", P + "partner-portal/Platz.html"],
        ["Ansprechpartner", P + "partner-portal/Team.html"],
        ["Angebot bearbeiten", P + "partner-portal/Angebot.html"],
        ["Foto-Leitfaden", P + "partner-portal/Fotoleitfaden.html"],
        ["Neuanmeldung (Onboarding)", P + "partner-onboarding.html"],
      ],
    },
    {
      id: "admin", label: "Admin", who: "Firmengolf-Team",
      icon: '<path d="M3 13h8V3H3zM13 21h8V11h-8zM13 3v6h8V3zM3 21h8v-6H3z"/>',
      home: P + "admin.html#/dashboard",
      links: [
        ["Übersicht", P + "admin.html#/dashboard"],
        ["Anfragen", P + "admin.html#/requests"],
        ["Firmenevents", P + "admin.html#/events"],
        ["Partnerplätze", P + "admin.html#/partners"],
        ["Benutzer", P + "admin.html#/users"],
        ["Magazin", P + "admin.html#/blog"],
      ],
    },
  ];

  // ---- styles (token-based, on-brand) ----
  var css = `
  #fg-switch { position: fixed; left: 18px; bottom: 18px; z-index: 9000;
    font-family: var(--font-body, system-ui, sans-serif); }
  #fg-switch * { box-sizing: border-box; }
  .fgs-pill { display: inline-flex; align-items: center; gap: 9px; white-space: nowrap;
    background: var(--ink-900, #0E1310); color: var(--paper-100, #FBFAF6);
    border: 0; cursor: pointer; padding: 10px 15px; border-radius: 999px;
    font-size: 13px; font-weight: 600; letter-spacing: -0.01em;
    box-shadow: 0 6px 24px rgba(14,19,16,.28); transition: transform .12s ease, box-shadow .2s ease; }
  .fgs-pill:hover { box-shadow: 0 8px 30px rgba(14,19,16,.34); }
  .fgs-pill:active { transform: translateY(1px); }
  .fgs-pill svg { width: 16px; height: 16px; }
  .fgs-dot { width: 7px; height: 7px; border-radius: 999px; background: var(--fairway-400, #6E9A5E); }
  .fgs-panel { position: absolute; left: 0; bottom: calc(100% + 10px);
    width: 290px; background: var(--paper-50, #fff); color: var(--ink-900, #0E1310);
    border-radius: 18px; box-shadow: 0 18px 50px rgba(14,19,16,.26);
    padding: 8px; opacity: 0; transform: translateY(8px) scale(.98); pointer-events: none;
    transition: opacity .18s ease, transform .18s cubic-bezier(.22,1,.36,1);
    border: 1px solid var(--ink-100, #EFEFEA); }
  #fg-switch.open .fgs-panel { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
  .fgs-head { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase;
    color: var(--ink-500, #6C736E); padding: 10px 12px 8px; }
  .fgs-group { border-radius: 12px; overflow: hidden; }
  .fgs-group + .fgs-group { margin-top: 2px; }
  .fgs-row { display: flex; align-items: center; gap: 11px; width: 100%;
    padding: 10px 12px; border: 0; background: none; cursor: pointer; text-align: left;
    text-decoration: none; color: inherit; border-radius: 10px; transition: background .12s ease; }
  .fgs-row:hover { background: var(--paper-200, #F4F1E9); }
  .fgs-ic { width: 32px; height: 32px; flex: none; border-radius: 9px;
    display: grid; place-items: center; background: var(--paper-200, #F4F1E9); color: var(--ink-700, #2B312D); }
  .fgs-ic svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.7;
    stroke-linecap: round; stroke-linejoin: round; }
  .fgs-group.cur .fgs-ic { background: var(--fairway-700, #2C5036); color: var(--paper-100, #FBFAF6); }
  .fgs-tl { display: block; font-size: 14px; font-weight: 600; letter-spacing: -0.01em; line-height: 1.15; }
  .fgs-who { display: block; font-size: 11.5px; color: var(--ink-500, #6C736E); margin-top: 1px; }
  .fgs-cur-tag { margin-left: auto; font-size: 10px; font-weight: 700; letter-spacing: .04em;
    text-transform: uppercase; color: var(--fairway-700, #2C5036);
    background: var(--fairway-100, #E6EEE2); padding: 3px 7px; border-radius: 999px; }
  .fgs-sub { display: none; padding: 2px 8px 8px 55px; flex-wrap: wrap; gap: 6px; }
  .fgs-group.cur .fgs-sub { display: flex; }
  .fgs-sublink { font-size: 12px; white-space: nowrap; color: var(--ink-600, #555B56); text-decoration: none;
    background: var(--paper-200, #F4F1E9); padding: 5px 10px; border-radius: 999px;
    transition: background .12s ease, color .12s ease; }
  .fgs-sublink:hover { background: var(--fairway-700, #2C5036); color: var(--paper-100, #FBFAF6); }
  .fgs-foot { display: flex; align-items: center; justify-content: space-between;
    padding: 9px 12px 6px; font-size: 11px; color: var(--ink-400, #9A9F9B); }
  .fgs-kbd { font-family: var(--font-mono, monospace); font-size: 10px;
    background: var(--paper-200, #F4F1E9); border-radius: 5px; padding: 2px 6px; color: var(--ink-600,#555B56); }
  @media print { #fg-switch { display: none; } }
  `;
  var st = document.createElement("style");
  st.textContent = css;

  // ---- markup ----
  var curGroup = GROUPS.filter(function (g) { return g.id === view; })[0] || GROUPS[0];
  var root = document.createElement("div");
  root.id = "fg-switch";

  var groupsHTML = GROUPS.map(function (g) {
    var cur = g.id === view;
    var subs = g.links.map(function (l) {
      return '<a class="fgs-sublink" href="' + l[1] + '">' + l[0] + "</a>";
    }).join("");
    return (
      '<div class="fgs-group' + (cur ? " cur" : "") + '">' +
        '<a class="fgs-row" href="' + g.home + '">' +
          '<span class="fgs-ic"><svg viewBox="0 0 24 24">' + g.icon + "</svg></span>" +
          "<span><span class='fgs-tl'>" + g.label + "</span>" +
          "<span class='fgs-who'>" + g.who + "</span></span>" +
          (cur ? '<span class="fgs-cur-tag">Hier</span>' : "") +
        "</a>" +
        '<div class="fgs-sub">' + subs + "</div>" +
      "</div>"
    );
  }).join("");

  root.innerHTML =
    '<div class="fgs-panel" role="menu" aria-label="Ansicht wechseln">' +
      '<div class="fgs-head">Ansicht wechseln</div>' +
      groupsHTML +
      '<div class="fgs-foot"><span>Demo-Navigation</span><span class="fgs-kbd">V</span></div>' +
    "</div>" +
    '<button class="fgs-pill" aria-label="Ansicht wechseln" aria-expanded="false">' +
      '<span class="fgs-dot"></span>' +
      "<span>" + curGroup.label + "</span>" +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 8l5-5 5 5M7 16l5 5 5-5"/></svg>' +
    "</button>";

  function mount() {
    document.head.appendChild(st);
    document.body.appendChild(root);
    var pill = root.querySelector(".fgs-pill");
    var open = false;
    function setOpen(v) {
      open = v;
      root.classList.toggle("open", v);
      pill.setAttribute("aria-expanded", v ? "true" : "false");
    }
    pill.addEventListener("click", function (e) { e.stopPropagation(); setOpen(!open); });
    document.addEventListener("click", function (e) { if (!root.contains(e.target)) setOpen(false); });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") setOpen(false);
      var tag = (e.target.tagName || "").toLowerCase();
      if ((e.key === "v" || e.key === "V") && tag !== "input" && tag !== "textarea" && !e.metaKey && !e.ctrlKey) {
        setOpen(!open);
      }
    });

    // Don't cover a sticky/fixed bottom bar (e.g. the onboarding footer nav,
    // whose "Zurück" button lives bottom-left where the capsule sits).
    function avoidBottomBar() {
      var bars = document.querySelectorAll(".ob-footer, .rw-foot, [data-bottom-bar]");
      var maxOverlap = 0;
      bars.forEach(function (b) {
        var cs = getComputedStyle(b);
        if (cs.position !== "fixed" && cs.position !== "sticky") return;
        var r = b.getBoundingClientRect();
        // only bars actually pinned near the viewport bottom
        if (r.height && r.bottom >= window.innerHeight - 4 && r.top < window.innerHeight) {
          maxOverlap = Math.max(maxOverlap, window.innerHeight - r.top);
        }
      });
      root.style.bottom = (maxOverlap > 0 ? maxOverlap + 16 : 18) + "px";
    }
    avoidBottomBar();
    window.addEventListener("resize", avoidBottomBar);
    window.addEventListener("scroll", avoidBottomBar, { passive: true });
    // Footers often render after this script (React apps mount async),
    // so re-check shortly after load and whenever the DOM changes.
    [100, 300, 700, 1500].forEach(function (t) { setTimeout(avoidBottomBar, t); });
    if (window.MutationObserver) {
      var mo = new MutationObserver(function () { avoidBottomBar(); });
      mo.observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.body) mount();
  else document.addEventListener("DOMContentLoaded", mount);
})();
