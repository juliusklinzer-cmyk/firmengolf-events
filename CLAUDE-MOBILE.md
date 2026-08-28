# CLAUDE-MOBILE.md — Arbeitsreihenfolge für Responsive-Fixes

Feste Reihenfolge für alle Responsive-Arbeiten. Erst Befund (Skill `design-review`), dann Fixes in genau dieser Ordnung, damit Grundlagen nicht auf kaputtem Fundament poliert werden. Kein Fix ohne Go von Julius (siehe Workflow-Regeln).

## Reihenfolge

1. **Viewport-Meta-Tag**: `<meta name="viewport" content="width=device-width, initial-scale=1">` in `header.php` des Child-Themes muss vorhanden und unverändert sein. Kein `maximum-scale`, kein `user-scalable=no` (Accessibility). `viewport-fit=cover` nur ergänzen, wenn bewusst mit `env(safe-area-inset-*)` gearbeitet wird.
2. **Input-Schriftgrößen**: alle `input`, `select`, `textarea` auf Mobile mindestens `16px`, sonst zoomt iOS Safari beim Fokus in die Seite. Zentral über die Formular-Klassen in `fge-frontend.css` lösen, nicht pro Feld.
3. **Horizontaler Overflow**: Seite darf nie seitlich scrollen. Übliche Täter: feste Breiten, `100vw` (nimmt die Scrollbar nicht aus), zu breite Bilder/Tabellen, negative Margins. Gewollte Wisch-Reihen brauchen einen eigenen Container mit `overflow-x: auto`; die Wurzel behält `overflow-x: clip` (nicht `hidden`, das würde `position: sticky` in Kindern brechen).
4. **Touch-Targets**: interaktive Elemente auf Mobile mindestens 44 × 44 CSS-px (Padding erhöhen, nicht die Schrift aufblasen). Abstand zwischen benachbarten Zielen mindestens 8px.
5. **Sichtbarer Viewport (iOS)**: Auf iOS ist der Layout-Viewport größer als der sichtbare Bereich. Alles was per `position: fixed`, `inset: 0`, `bottom: 0` oder `100vh` daran hängt, liegt teilweise hinter der Safari-Leiste. `100dvh` allein reicht nicht, Safari zieht den Wert bei fixierten Overlays nicht zuverlässig nach. Verbindlich: `assets/js/fge-viewport.js` (global im Head) schreibt die echten Maße der visualViewport-API auf `<html>`:
   - `--fg-vvh` sichtbare Höhe, `--fg-vvt` Abstand nach oben, `--fg-vvb` Abstand nach unten.
   - Vollbild-Flächen: `top: var(--fg-vvt, 0px); height: var(--fg-vvh, 100dvh);` (mit `100vh`/`100dvh` als Fallback-Kette davor).
   - Fixierte oder sticky Füße: `bottom: var(--fg-vvb, 0px);`
   - `min-height: 100vh` auf Seiten mit sticky-Fuß zusätzlich als `min-height: var(--fg-vvh, 100dvh)` setzen, sonst wächst die Seite über den sichtbaren Bereich hinaus.
   - Fuß-Paddings nehmen `env(safe-area-inset-bottom, 0px)` mit.
   - Bei offener TASTATUR friert `fge-viewport.js` die Werte ein (solange ein Eingabefeld den Fokus hat): Vollbild-Wizards dürfen NICHT auf den Bereich über der Tastatur schrumpfen, die Tastatur legt sich wie in einer nativen App über den Wizard (Julius, 28.08.2026 abends). Nach dem Blur misst der nächste resize frisch.
   - Neue Vollbild-Flächen nie mit nacktem `inset: 0` bauen (Julius, 28.08.2026).
   - Vollbild-Flächen mit `height` UND Padding brauchen `box-sizing: border-box`, es gibt keinen globalen Reset dafür. Ohne ihn ragt die Box um das Padding unter den Viewport, der Fuß liegt hinter der Safari-Leiste (fg-modal-Fall, 28.08.2026). Zweite Falle desselben Falls: spätere Mobile-Blöcke (z. B. 720px) können das Vollbild-Padding des 768px-Blocks überschreiben, bei Änderungen an Modal-Paddings beide Blöcke prüfen.
   - Aktions-Füße in scrollenden Vollbild-Dialogen `position: sticky; bottom: 0` mit eigenem Grund + safe-area-Padding geben, dann sind die Buttons in jedem Leisten-Zustand ohne Scrollen sichtbar.
6. **Scroll-Verhalten**: 
   - Die Topnav bleibt statisch und scrollt weg. Fixierte Füße sind erlaubt, aber nur mit der Mechanik aus Punkt 5. Das ersetzt die ältere Regel "nie fixed Chrome unten" vom 27.08.2026, die den Fuß nur verschob statt die Ursache zu beheben.
   - Scroll-Schwellen-Effekte immer mit Hysterese bauen (Flacker-Gefahr durch Android-Scroll-Anchoring, siehe Memory `mobile-nav`).
   - Dialoge sperren den Hintergrund (`html.fg-drawer-lock` bzw. `body overflow hidden`) und geben ihn beim Schließen wieder frei.
   - Formular-Dialoge sind auf Mobile vollflächig (100dvh, kein zentriertes Popup ≤768px), Schließen-Button bleibt fixiert erreichbar.
7. **Animationen**: nur `transform` und `opacity` animieren (compositor-freundlich). Einblendende Flächen mit Ausstieg auf demselben Weg. Jede Animation braucht einen `prefers-reduced-motion: reduce`-Zweig (Crossfade statt Slide). Keine Animation auf dem Eingabepfad verzögern (Feedback bei pointer-down, nicht erst bei Release).

## Wohin gehören Fixes

- **CSS**: in `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-frontend.css` (dort lebt das gesamte Frontend-CSS) oder, falls themenspezifisch, in `wordpress/wp-content/themes/firmengolf-child/style.css`.
- **Markup/Logik**: in die Template-Dateien des Child-Themes (`wordpress/wp-content/themes/firmengolf-child/…`) oder die Plugin-Includes.
- **NIE ins Parent-Theme** (`twentytwentyfive`), das wird bei Updates überschrieben.
- Nach CSS-Änderungen `FGE_VERSION` in `firmengolf-events.php` bumpen (Cache-Bust), sonst laden Browser das alte Stylesheet.
- Mobile-Breakpoint des Projekts ist `max-width: 768px` (Feinstufen 480px und 420px existieren); neue Regeln an die bestehenden Blöcke anhängen statt neue Breakpoints zu erfinden.

## Verifikation

Jeden Fix headless gegenprüfen (Viewports und Technik siehe `.claude/skills/design-review/SKILL.md`): Screenshot vorher/nachher, Overflow- und Touch-Target-Messung erneut laufen lassen. Was sich nur "anfühlen" lässt (Animationstiming, Scroll-Gefühl), testet Julius auf dem echten Gerät vor dem Deploy.
