# DESIGN.md — Verbindliche Design-Linie für firmengolf-events

Diese Datei ist die eine Wahrheit für das Aussehen aller Oberflächen (Website, Anfrage-Wizard, Event-Modal, Filter, Partner-Login, Onboarding). Wer UI baut oder ändert, liest sie vorher und hängt neue Elemente an die zentralen Komponenten an, statt eigene Stile zu erfinden. Die technischen Definitionen stehen gebündelt am ENDE von `assets/css/fge-frontend.css` unter „DESIGN-LINIE: zentrale Komponenten".

## Grundwerte (Tokens, definiert in :root von fge-frontend.css)

- Markenfarbe: `--fairway-700` (#4279D1), Hover heller: `--fairway-600`. Nie dunkler beim Hover, nie Hex hartkodieren.
- Auswahl-/Akzentflächen: `--fairway-100` Grund, `--fairway-200` Rahmen, `--fairway-800` Text.
- Text: `--ink-900` (fast schwarz) bis `--ink-400`; Flächen: `--paper-50` bis `--paper-300`.
- Radien: Pille (`--radius-pill`) für Buttons und Chips, 12px für Eingabefelder, 20 bis 24px für Karten und Dialoge.
- Schrift: Überschriften `--font-display`, alles andere `--font-body`.

## Komponenten (Identität ist fix, Größe darf im Kontext variieren)

1. **Primär-Button**: Marken-Pille. `fairway-700` auf `paper-100`, Radius Pille, Gewicht 600, Hover `fairway-600`, Druck `scale(0.97)`, Disabled `ink-300`. Gruppe im CSS: `.fg-btn-brand`, `.fg-nav-cta`, `.rw-btn-primary`, `.ev-sheet-go`, `.ev-geo-allow`, Login-Submit, `.ob-btn-primary`.
2. **Text-Button** (Zurück, Abbrechen, Zurücksetzen): unterstrichener Text, `ink-600`, Hover `ink-900`, kein Hintergrund.
3. **Sekundär-Outline**: weißer Grund, 1px `ink-200`-Rahmen, Pillenform, Hover Rahmen `ink-900`.
4. **Auswahl-Chip**: Aus = weißer Grund, 1px `ink-200`-Rahmen, `ink-700`-Text. An = `fairway-100`/`fairway-200`/`fairway-800`, Gewicht 600, kleiner Pop (`ind-card-pop`). Hover nur auf hover-fähigen Geräten (`@media (hover: hover)`).
5. **Eingabefeld**: `.fg-input`. Weißer Grund, 1px `ink-200`, Radius 12, Fokus = `fairway-700`-Rahmen + 3px `fairway-100`-Ring. Auf Mobile mindestens 16px Schrift (iOS-Zoom).
6. **Schließen**: schlichtes X ohne Kreis oder Rahmen, 44px Tippfläche (`.rw-close`-Muster).
7. **Fortschritt**: durchgehende Segmente volle Breite, 5px hoch, Füllung `fairway-700`, keine Beschriftung (Schritt-Info steht im Inhalt).
8. **Dialoge und Formulare**: Desktop zentriert mit Blur-Scrim, rundum gleicher Radius (`overflow: hidden` nicht vergessen). Auf Mobile sind Anfrage- und Bestellformulare IMMER vollflächig (100dvh), kleine Entscheidungs-Popups bleiben zentriert. Fußzeile: Zurück ganz links, Primäraktion ganz rechts.
9. **FAQ**: EINE Komponente für die ganze Site: `get_template_part( 'template-parts/fge-faq', null, [ 'items' => [...] ] )`. Karten-Optik (weiße Karte, 1px Rahmen, offen = Marken-Rahmen + Schatten, drehender Chevron, animierte Antwort). Nie eigene FAQ-Markups oder eigene Toggle-Scripts bauen; das Part bringt sein JS einmal pro Seite mit.
10. **Motion**: Nur `transform`/`opacity`. Einstieg nie aus scale(0), Dauer unter 300ms außer Seitenwechseln, `prefers-reduced-motion` bekommt Fades. Fixierte oder sticky Füße am unteren Mobile-Rand nur mit der Viewport-Mechanik aus CLAUDE-MOBILE.md Punkt 5 (`bottom: var(--fg-vvb, 0px)` + safe-area-Padding), sonst nie.

## Bekannte Fallen

- `.fge-page button`-Reset (Spezifität 0,1,1) entkernt nackte Ein-Klassen-Selektoren: Buttons auf Eventseiten IMMER mit Eltern-Klasse prefixen.
- `a.fg-nav-cta` überstimmt nackte Klassen: Sichtbarkeits-Overrides ebenfalls mit `a.`-Präfix schreiben.
- `.ob-shell a { color: inherit }` (0,1,1) entkernt Ein-Klassen-Buttonfarben im Onboarding (`.ob-done-btn-primary` stand schwarz auf blau): Link-Buttons dort immer zusätzlich als `.ob-shell a.klasse` selektieren.
- Nach CSS-Änderungen `FGE_VERSION` bumpen (Cache-Bust).
- Safari (iOS) färbt die Fläche hinter Status- und Adressleiste nach `theme-color` (header.php, #FBFAF6) und dem `body`-Hintergrund. Beide müssen dem Papierweiß `--paper-100` entsprechen, sonst liegt ein fremder Farbton über der Seite.

## Prozess

Jede visuelle Änderung wird entweder sofort auf ALLE Strecken angewendet (über die zentralen Gruppen) oder als offene Schuld mit Julius' Go dokumentiert. Konsistenz prüfen: `/impeccable audit`, Responsive: Skill `design-review`.
