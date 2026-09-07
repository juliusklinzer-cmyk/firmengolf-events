# Bilder-Leitfaden: Ordnerstruktur zum Befüllen (Stand 04.09.2026)

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
| `golfplatz-` (oder `platz-`) | Echte Platz-Motive für Stadt- und Platz-Cover | `golfplatz-fairway-morgen.jpg` |
| `closeup-` | Nahaufnahmen (Bälle, Schläger, Schuhe), nur gezielt, nicht automatisch | `closeup-ball-am-loch.jpg` |
| `pool-` | **Reserve**, wird NIE automatisch vergeben | `pool-meetingraum.jpg` |
| `pool-hochformat-` | **Reserve Hochformat**, ebenfalls nie automatisch | `pool-hochformat-teamevent-jubel.jpg` |

**Platzhalter-Events zeigen nur ihren eigenen Typ** (Julius, 04.09., nach kurzem
Test mit Beimisch-Slots wieder verworfen): Cover und beide Galerie-Kacheln kommen
aus dem Typ-Topf, auf einer Seite nie dasselbe Motiv doppelt (Dedup über den
Bildinhalt). golfplatz-Bilder dienen als Stadt- und Platz-Cover, closeup-Bilder
liegen nur für gezielte Einsätze bereit und werden nicht automatisch vergeben.

**Cover sind eindeutig:** Jedes Platzhalter-Event bekommt nach seinem Rang im Typ
ein eigenes Titelbild. Erst wenn der Topf einmal durch ist (z. B. 90 Turniere
bei 32 Turnier-Bildern), wiederholen sich Cover, dann gleichmäßig verteilt und
nie mit fremden Typ-Motiven.

**weihnachtsfeier- ist in indoor- aufgegangen** (dieselben Motive, Julius 04.09.):
Weihnachts-Events ziehen die Indoor-Bildwelt. Der Präfix bleibt erlaubt, falls
später echte Weihnachts-Motive kommen (Glühwein, Deko); sobald dort etwas liegt,
greift wieder der eigene Topf.

**Hochformat:** Karten und Galerie-Kacheln sind Querformat und beschneiden.
Hochformat-Bilder landen beim Import daher automatisch als `pool-hochformat-…`
in der Reserve (Original-Präfix bleibt im Namen erhalten, z. B.
`pool-hochformat-teamevent-jubel.jpg`), damit man sie schnell findet, wenn die
Website mal ein Hochformat braucht.

## 1b. Import-Skript (`import-bilder.php` im Repo-Root)

Neue Bilder einfach mit Präfix in den OneDrive-Ordner `Desktop/Bilder/` legen
(Unterordner wie `Stadtbilder/`, `Videos/` werden ignoriert). Der Import läuft im
WordPress-Container (GD + EXIF):

```bash
docker compose cp "/mnt/c/Users/Julius/OneDrive - VisionPunch UG/Desktop/Bilder" wordpress:/tmp/bilder
docker compose exec -T wordpress sh -c 'cd /tmp/bilder && rm -rf Stadtbilder Videos *.MOV *.mov'
docker compose cp import-bilder.php wordpress:/tmp/import-bilder.php
docker compose exec -T -u 1000:1000 wordpress php -d memory_limit=-1 /tmp/import-bilder.php /tmp/bilder \
  /var/www/html/wp-content/plugins/firmengolf-events/assets/imagery dry    # erst Trockenlauf, dann write
```

Das Skript: dreht nach EXIF, flacht PNG auf Weiß, skaliert auf max. 1920 px,
schreibt `.jpg` + `.jpg.webp`, mappt `platz-`→`golfplatz-`,
`weihnachtsfeier-`→`indoor-`, `office-/ki-/bunkerschlag-`→`pool-`, Hochformat→
`pool-hochformat-`. Dubletten erkennt es am Bildinhalt: dasselbe Motiv unter
mehreren Präfixen bekommt byteidentische Kopien (so greift der Dedup), zweimal
dasselbe Motiv im selben Präfix wird nur einmal angelegt, und Motive, die im Pool
schon unter dem gleichen Präfix liegen, werden übersprungen. Alte kleine Pool-
Dateien desselben Motivs werden auf die neue Qualität gehoben.

## 2. Feste Kachel-Slots → `imagery/tiles/`

Diese Dateinamen sind FEST verdrahtet (Format-Kacheln auf der Weihnachtsfeier-
Seite und den Weihnachts-Stadt-Seiten, Homepage-Look). Fehlt eine Datei, zeigt
die Kachel das Lounge-Bild. Seit 04.09. sind alle 8 Slots aus dem Indoor-Pool
befüllt (auf 3:4 beschnitten, 900 × 1200 px); zum Tauschen einfach die Datei
ersetzen, Hochformat bevorzugt. Hochformat-tauglich
beschnitten (Kachel ist 3:4), Motiv unten hell genug für weiße Schrift meiden
bzw. der dunkle Verlauf unten regelt das.

- `tiles/indoor-weihnachtsfeier.jpg` — Feier-Stimmung an den Boxen (Glühwein, Deko) (aktuell: indoor-bier-und-simulator)
- `tiles/indoor-turnier.jpg` — Simulator mit Leaderboard/Turnier-Szene (aktuell: pool-hochformat-indoor-golfsimulator (Nachtschwung, Datenbildschirm))
- `tiles/indoor-teamevent.jpg` — Gruppe jubelt/spielt an einer Box (aktuell: indoor-golf-indoor-simulator-bar-event-im-team)
- `tiles/indoor-afterwork.jpg` — lockere Abend-Szene, Drinks an der Bar (aktuell: indoor-topgolf-oberhausen-drinks-and-drive)
- `tiles/indoor-grundlagen.jpg` — Golflehrer erklärt am Simulator (aktuell: pool-hochformat-indoor-golfsimulator-echt-mit-gruender (kniender Trainer))
- `tiles/indoor-workshop.jpg` — Meeting-/Lounge-Bereich einer Indoor-Anlage (aktuell: pool-hochformat-indoor-lounge)
- `tiles/indoor-kundenevent.jpg` — gehobene Gastgeber-Szene indoor (aktuell: indoor-golf-bar-und-fun-imi-team)
- `tiles/indoor-exklusiv.jpg` — leere/edle Gesamtansicht der Location (aktuell: indoor-eingang-golfsimulator)

Optional zusätzlich: `tiles/home-indoor.jpg` als Ersatz für das Lounge-Bild der
Indoor-Kachel auf der Startseite (sag Bescheid, dann verdrahte ich es).

## 2b. Spielformate auf der Weihnachtsfeier-Seite → `imagery/spielformate/`

Sechs Karten „Bewegung, Location und Spielformate". Dateiname = Slot, Querformat
4:3, mindestens 1200 px breit. Fehlt eine Datei, zeigt die Karte ein Indoor-Motiv
aus dem Pool. Sobald die Datei liegt, erscheint sie automatisch.

- `spielformate/nearest-to-the-pin.jpg`
- `spielformate/longest-drive.jpg`
- `spielformate/angry-birds.jpg`
- `spielformate/putt-bierpong.jpg`
- `spielformate/team-scramble.jpg`
- `spielformate/virtuelle-runde.jpg`

## 2c. Sommerfest-Formate auf der Sommerfest-Seite → `imagery/sommerfest/`

Sechs Kacheln „Unsere Sommerfest-Formate" (/firmenevent/sommerfest/). Dateiname =
Slot, Querformat 4:3, mindestens 1200 px breit; fehlt eine Datei, läuft ein Pool-Motiv.

- `sommerfest/firmenturnier.jpg`
- `sommerfest/kurzplatz-turnier.jpg`
- `sommerfest/schnupperrunde.jpg`
- `sommerfest/putting-challenge.jpg`
- `sommerfest/barbecue.jpg`
- `sommerfest/siegerehrung.jpg`

Hero der Sommerfest-Seite: `pool/afterwork-anstossen.jpg` (Desktop) und
`pool/pool-hochformat-afterwork-sundowner-golf.jpg` (Mobil), beides im Template.

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
