# CLAUDE-MOBILE.md — Arbeitsreihenfolge für Responsive-Fixes

Feste Reihenfolge für alle Responsive-Arbeiten. Erst Befund (Skill `design-review`), dann Fixes in genau dieser Ordnung, damit Grundlagen nicht auf kaputtem Fundament poliert werden. Kein Fix ohne Go von Julius (siehe Workflow-Regeln).

## Reihenfolge

1. **Viewport-Meta-Tag**: `<meta name="viewport" content="width=device-width, initial-scale=1">` in `header.php` des Child-Themes muss vorhanden und unverändert sein. Kein `maximum-scale`, kein `user-scalable=no` (Accessibility). `viewport-fit=cover` nur ergänzen, wenn bewusst mit `env(safe-area-inset-*)` gearbeitet wird.
2. **Input-Schriftgrößen**: alle `input`, `select`, `textarea` auf Mobile mindestens `16px`, sonst zoomt iOS Safari beim Fokus in die Seite. Zentral über die Formular-Klassen in `fge-frontend.css` lösen, nicht pro Feld.
3. **Horizontaler Overflow**: Seite darf nie seitlich scrollen. Übliche Täter: feste Breiten, `100vw` (nimmt die Scrollbar nicht aus), zu breite Bilder/Tabellen, negative Margins. Gewollte Wisch-Reihen brauchen einen eigenen Container mit `overflow-x: auto`; die Wurzel behält `overflow-x: clip` (nicht `hidden`, das würde `position: sticky` in Kindern brechen).
4. **Touch-Targets**: interaktive Elemente auf Mobile mindestens 44 × 44 CSS-px (Padding erhöhen, nicht die Schrift aufblasen). Abstand zwischen benachbarten Zielen mindestens 8px.
5. **Scroll-Verhalten**: 
   - Auf Mobile NIE fixed/sticky Chrome am unteren Viewport-Rand (kollidiert mit den ein-/ausblendenden Browser-Leisten; Entscheidung Julius 27.08.2026). Die Topnav bleibt statisch und scrollt weg.
   - Scroll-Schwellen-Effekte immer mit Hysterese bauen (Flacker-Gefahr durch Android-Scroll-Anchoring, siehe Memory `mobile-nav`).
   - Dialoge sperren den Hintergrund (`html.fg-drawer-lock` bzw. `body overflow hidden`) und geben ihn beim Schließen wieder frei.
   - Formular-Dialoge sind auf Mobile vollflächig (100dvh, kein zentriertes Popup ≤768px), Schließen-Button bleibt fixiert erreichbar.
6. **Animationen**: nur `transform` und `opacity` animieren (compositor-freundlich). Einblendende Flächen mit Ausstieg auf demselben Weg. Jede Animation braucht einen `prefers-reduced-motion: reduce`-Zweig (Crossfade statt Slide). Keine Animation auf dem Eingabepfad verzögern (Feedback bei pointer-down, nicht erst bei Release).

## Wohin gehören Fixes

- **CSS**: in `wordpress/wp-content/plugins/firmengolf-events/assets/css/fge-frontend.css` (dort lebt das gesamte Frontend-CSS) oder, falls themenspezifisch, in `wordpress/wp-content/themes/firmengolf-child/style.css`.
- **Markup/Logik**: in die Template-Dateien des Child-Themes (`wordpress/wp-content/themes/firmengolf-child/…`) oder die Plugin-Includes.
- **NIE ins Parent-Theme** (`twentytwentyfive`), das wird bei Updates überschrieben.
- Nach CSS-Änderungen `FGE_VERSION` in `firmengolf-events.php` bumpen (Cache-Bust), sonst laden Browser das alte Stylesheet.
- Mobile-Breakpoint des Projekts ist `max-width: 768px` (Feinstufen 480px und 420px existieren); neue Regeln an die bestehenden Blöcke anhängen statt neue Breakpoints zu erfinden.

## Verifikation

Jeden Fix headless gegenprüfen (Viewports und Technik siehe `.claude/skills/design-review/SKILL.md`): Screenshot vorher/nachher, Overflow- und Touch-Target-Messung erneut laufen lassen. Was sich nur "anfühlen" lässt (Animationstiming, Scroll-Gefühl), testet Julius auf dem echten Gerät vor dem Deploy.
