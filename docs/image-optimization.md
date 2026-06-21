# Bild-Optimierung (Launch-Schritt)

Der Ordner `wp-content/plugins/firmengolf-events/assets/imagery/` ist aktuell **~60 MB**
(Einzelbilder bis 4,8 MB; einige Foto-PNGs 1–2 MB). Hero-/Onboarding-Bilder werden teils
als CSS-`background-image` eingebunden, gehen also **nicht** durch WordPress' `srcset`.

Auf dieser Dev-Maschine ist kein Bildtool installiert. Vor dem Launch einmalig
**in-place** komprimieren (Dateinamen bleiben gleich → **keine** Code-Änderungen nötig):

```bash
# Tools (einmalig):  sudo apt-get install imagemagick pngquant   # oder: brew install imagemagick pngquant
cd wp-content/plugins/firmengolf-events/assets/imagery

# JPGs: auf max 1920px verkleinern (nur wenn größer) + Quality 80 + Metadaten strippen
mogrify -strip -resize '1920x1920>' -quality 80 *.jpg

# Foto-PNGs verlustbehaftet verkleinern (Dateiname bleibt, ~2 MB -> ~300-500 KB)
pngquant --quality=60-82 --skip-if-larger --ext .png --force *.png

# Kontrolle
du -sh .        # Ziel: < 8 MB gesamt
```

Erwartung: ~60 MB → unter 8 MB, ohne sichtbaren Qualitätsverlust. Danach `git add` der
geänderten Binärdateien + commit.

Optional (mehr Wirkung): zusätzlich WebP/AVIF ausliefern (`cwebp`) + ein Plugin/Server-Rewrite,
das moderne Formate bevorzugt. Nicht zwingend für Launch.

## CSS (bewusst zurückgestellt)
`assets/css/fge-frontend.css` (~242 KB, unminifiziert, sitewide) wird vom Server gzip/brotli
komprimiert (~35 KB auf der Leitung). Eine Minifizierung spart danach nur ~10–15 KB, bei
Wartungsaufwand (Regenerieren bei jeder Änderung) und Bruch-Risiko → erst sinnvoll mit einem
echten Build-Schritt im Deploy (z. B. `lightningcss`/`csso`), nicht hand-gerollt.
