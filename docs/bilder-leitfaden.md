# Bilder-Leitfaden: Ordnerstruktur zum Befüllen (Stand 03.09.2026)

Alles liegt unter `wordpress/wp-content/plugins/firmengolf-events/assets/imagery/`.
Du legst NUR die `.jpg` ab, ich erzeuge die `.webp`-Zwillinge und binde alles ein.

## 1. Event-Platzhalter je Typ → `imagery/pool/`

Der Dateiname STEUERT die Zuordnung: `<typ-präfix>-<motiv>.jpg`. Jedes Bild mit
passendem Präfix landet automatisch in der Bildwelt des Event-Typs (Karten,
Detailseiten, Seeds). Mehrere Bilder pro Typ sind gut, der Dedup-Verteiler
sorgt dafür, dass sich auf einer Seite nichts wiederholt.

| Präfix | Event-Typ | Beispiel-Dateiname |
| --- | --- | --- |
| `teamevent-` | Teamevent | `teamevent-putt-challenge.jpg` |
| `platzreife-` | Platzreife | `platzreife-pruefung-gruen.jpg` |
| `turnier-` | Firmen-Golfturnier | `turnier-siegerehrung.jpg` |
| `kundenevent-` | Kundenevent | `kundenevent-terrasse.jpg` |
| `afterwork-` | After-Work Golf | `afterwork-range-sonne.jpg` |
| `workshop-` | Workshop | `workshop-clubhaus-meeting.jpg` |
| `incentive-` | Incentive | `incentive-resort.jpg` |
| `nachtevent-` | Nacht-Event | `nachtevent-flutlicht.jpg` |
| `indoor-` | **Indoor Golf** | `indoor-simulator-gruppe.jpg` |
| `weihnachtsfeier-` | **Weihnachtsfeier** | `weihnachtsfeier-gluehwein-boxen.jpg` |
| `golfplatz-` | **Beimisch-Topf: echte Platz-Motive** | `golfplatz-fairway-morgen.jpg` |

**Der golfplatz-Topf ist besonders:** Diese Bilder gehören keinem Event-Typ.
Jedes Outdoor-Event bekommt in seiner Bildergalerie genau EIN Bild aus diesem
Topf (das dritte), Cover und zweites Bild bleiben typspezifisch. So taucht in
jedem Format ein echter Platz auf, ohne die Typ-Bildwelt zu verwässern. Indoor
und Weihnachtsfeier bleiben komplett drinnen, dort wird nichts beigemischt.
Zusätzlich dienen die golfplatz-Bilder weiter als Motive für Stadt- und
Platz-Cover.

WICHTIG für dich gerade: **indoor-** und **weihnachtsfeier-** sind neu und leer.
Solange `weihnachtsfeier-` leer ist, fallen Weihnachts-Events automatisch auf die
Indoor-Bildwelt zurück (nicht mehr auf Outdoor). 3 bis 6 Bilder pro Typ sind ideal.

## 2. Feste Kachel-Slots → `imagery/tiles/`

Diese Dateinamen sind FEST verdrahtet (Format-Kacheln auf der Weihnachtsfeier-
Seite, Homepage-Look). Fehlt eine Datei, zeigt die Kachel das Lounge-Bild.
Sobald du die Datei ablegst, erscheint sie automatisch. Hochformat-tauglich
beschnitten (Kachel ist 3:4), Motiv unten hell genug für weiße Schrift meiden
bzw. der dunkle Verlauf unten regelt das.

- `tiles/indoor-weihnachtsfeier.jpg` — Feier-Stimmung an den Boxen (Glühwein, Deko)
- `tiles/indoor-turnier.jpg` — Simulator mit Leaderboard/Turnier-Szene
- `tiles/indoor-teamevent.jpg` — Gruppe jubelt/spielt an einer Box
- `tiles/indoor-afterwork.jpg` — lockere Abend-Szene, Drinks an der Bar
- `tiles/indoor-grundlagen.jpg` — Golflehrer erklärt am Simulator
- `tiles/indoor-workshop.jpg` — Meeting-/Lounge-Bereich einer Indoor-Anlage
- `tiles/indoor-kundenevent.jpg` — gehobene Gastgeber-Szene indoor
- `tiles/indoor-exklusiv.jpg` — leere/edle Gesamtansicht der Location

Optional zusätzlich: `tiles/home-indoor.jpg` als Ersatz für das Lounge-Bild der
Indoor-Kachel auf der Startseite (sag Bescheid, dann verdrahte ich es).

## 3. Seiten-/Hero-Bilder → `imagery/` (direkt)

Sprechende Namen, kein Präfix-Zwang (z. B. `hero-…`, `onboarding-…`). Diese
Bilder verdrahte ich einzeln, schreib mir einfach dazu, wofür sie gedacht sind.

## Ein Bild in mehreren Typen verwenden

Einfach dieselbe Datei mehrfach ablegen, je einmal pro Präfix, z. B.
`teamevent-range-abend.jpg` UND `afterwork-range-abend.jpg` (identischer
Inhalt, nur anderer Name). Der Verteiler erkennt Dubletten am Bild-INHALT,
nicht am Namen: Auf einer Seite erscheint das Motiv nie zweimal, auch wenn es
unter fünf Namen im Pool liegt. In `docs/bildnachweise-pool.md` reicht dann
eine Zeile mit allen Dateinamen.

## Regeln

1. **Format:** JPG, Querformat, mindestens 1600 px breit (Kachel-Slots dürfen
   auch Hochformat sein). Keine Umlaute/Leerzeichen im Dateinamen, alles klein,
   Bindestriche.
2. **WebP:** machst du NICHT selbst, übernehme ich (Performance-Regel).
3. **Bildrechte:** Zu jedem Stock-Bild bitte eine Zeile in
   `docs/bildnachweise-pool.md` (Dateiname, Quelle/Lizenz, ggf. Fotograf).
   Datei lege ich an, du ergänzt pro Bild eine Zeile.
4. Nach dem Befüllen kurz Bescheid geben, dann: WebP-Zwillinge erzeugen,
   deployen, Sichtprüfung.
