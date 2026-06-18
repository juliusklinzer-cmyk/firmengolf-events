# SEO-Keyword-Map — Firmengolf

> Strategische Keyword-Zuordnung pro Seite. Grundlage: Geschäftsmodell + Such-Intent + reale Seitenstruktur.
> **Ohne Live-Tool keine echten Suchvolumina** — vor größeren Content-Investitionen mit Search Console / Ahrefs / Sistrix gegenprüfen.
> Stand: 2026-06-18.

## 1. Keyword-Universum nach Such-Intent

### Transaktional / kommerziell (Geld-Keywords — höchste Priorität)
- `Firmenevent Golf`, `Firmengolf`, `Golf Firmenevent`, `Corporate Golf Event`
- `Golf Teamevent`, `Teamevent Golfplatz`
- `Firmen-Golfturnier`, `Golfturnier Firma`
- `Golf Schnupperkurs Firma`, `Schnupperkurs Team`
- `Kundenevent Golf`, `Golf Kundenveranstaltung`
- `Golf Incentive`, `Incentive Golfreise`
- `After-Work Golf`

### Lokal (Stadt + Format — sehr kaufstark)
- `Firmenevent Golf <Stadt>`, `Golf Teamevent <Stadt>`, `Firmengolf <Stadt>` …

### Informational (Blog / Funnel-Einstieg)
- `Golf als Corporate Benefit`, `Firmenevent Ideen`, `Teambuilding Ideen Sommer`,
  `Incentive Ideen Mitarbeiter`, `Golf-Teamevent für Nicht-Golfer`, `Was kostet ein Firmen-Golfevent`

### Brand
- `Firmengolf`, `Firmengolf Events`

## 2. Keyword → Seite (eine Seite besitzt ein Haupt-Keyword)

| Seite | URL | Haupt-Keyword | Neben-Keywords |
|---|---|---|---|
| Startseite | `/` | `Firmenevent Golf` | Firmengolf, Corporate Golf Event |
| Event-Archiv | `/firmenevents/` | `Firmenevent Formate` | Golf Teamevent, Firmenturnier |
| **Format-Hub Teamevent** | `/firmenevent/teamevent/` | `Golf-Teamevent` | Teamevent Golfplatz, Teambuilding Golf |
| **Format-Hub Turnier** | `/firmenevent/golfturnier/` | `Firmen-Golfturnier` | Golfturnier Firma, Firmencup |
| **Format-Hub Schnupperkurs** | `/firmenevent/schnupperkurs/` | `Golf-Schnupperkurs Firma` | Schnupperkurs Team, Golf für Anfänger Firma |
| **Format-Hub Kundenevent** | `/firmenevent/kundenevent/` | `Kundenevent Golf` | Hospitality Golf, Kundenbindung Golf |
| **Format-Hub Incentive** | `/firmenevent/incentive/` | `Golf-Incentive` | Incentive Golfreise, Mitarbeiter-Incentive |
| **Format-Hub After-Work** | `/firmenevent/after-work-golf/` | `After-Work Golf` | Golf nach Feierabend, Teamabend Golf |
| City-Landingpage | `/golf-events/<stadt>/` | `Firmenevent Golf <Stadt>` | Golf Teamevent <Stadt> |
| Event-Single | `/firmenevents/<slug>/` | **Format + Stadt (long-tail)** | konkretes Format/Anlass |
| Individuelle Events | `/individuelle-events/` | `individuelles Firmen-Golfevent` | Golfevent planen, Budget |
| Golfplatz-Seite | `/golfplatz/<slug>/` | `Firmenevent <Platzname>` | Golfturniere <Stadt> |
| Blog-Post | `/<slug>/` | je 1 Informational-Keyword | thematisches Cluster |

## 3. Kannibalisierung — Status

**Behoben (2026-06-18):** City-Landingpage und Event-Singles konkurrierten um `Firmenevent Golf <Stadt>`.
→ City-Page besitzt jetzt das Head-Keyword; Event-Singles sind **Format-first** betitelt
(`Team-Golftag mit Coaching in München | Firmengolf` statt `Firmenevent Golf München: …`).

**Neu zu beobachten:** Format-Hubs (`/firmenevent/teamevent/`) vs. Event-Singles desselben Formats.
Abgrenzung: Hub = generisches Format-Keyword ohne Ort; Single = Format **+ Stadt + konkretes Angebot**.
City-Page = Format **+ Stadt** als Übersicht. Drei verschiedene Intents — kein Konflikt, solange die Titel-Konvention (unten) eingehalten wird.

## 4. Offene Chancen / Lücken

- **Weitere Städte**: aktuell 8 City-Pages — skalierbar (Nürnberg, Leipzig, Hannover, Dresden, Bremen …).
- **Format × Stadt-Matrix** (später): `/golf-events/<stadt>/` deckt schon Stadt ab, Format-Hubs die Formate.
  Echte Kombi-Seiten (`Golf-Teamevent München`) nur bauen, wenn Suchvolumen + eigener Inhalt das tragen — sonst Thin Content.
- **Informational-Cluster** rund um die Geld-Keywords, jeweils intern auf den passenden Hub/City verlinkt:
  - „Was kostet ein Firmen-Golfevent?" → /individuelle-events/ + /firmenevent/…/
  - „Golf-Teamevent für Nicht-Golfer" → /firmenevent/teamevent/ + /firmenevent/schnupperkurs/
  - „Die besten Golfplätze für Firmenevents in <Stadt>" → /golf-events/<stadt>/
- **Interne Verlinkung**: Blogposts und City-Pages mit beschreibendem Anchor auf die Format-Hubs verlinken
  (z. B. „Golf-Teamevent" → /firmenevent/teamevent/).

## 5. On-Page-Konventionen (damit es konsistent bleibt)

- **Title**: Haupt-Keyword vorn, Marke hinten — `<Keyword> | Firmengolf` (≤ 60 Zeichen).
- **Event-Single-Title**: Format-first, nicht „Firmenevent Golf <Stadt>" (das gehört der City-Page).
- **H1**: genau eine, enthält das Haupt-Keyword.
- **Description**: Haupt-Keyword + Nutzen + CTA (≤ 160 Zeichen).
- **Keyword in den ersten 100 Wörtern** des Fließtexts.
- **Eine Seite, ein Haupt-Keyword** — bei Überschneidung Intent schärfen statt zweite Seite bauen.

## 6. Umgesetzte technische SEO-Basis (Kontext)

Siehe Auto-Memory `seo-launch`. Kurz: eigene Titel/Descriptions/OG/Schema auf allen Seitentypen,
City- + Format-Landingpages (programmatisch), XML-Sitemap inkl. dieser Seiten, 410-Lifecycle für
entfernte Events/Plätze, gestylte 404/Suche. **Launch-Pflicht:** `blog_public=1` + Domain-search-replace.
