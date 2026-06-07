# Icons

Firmengolf uses **Lucide** as its primary icon set (https://lucide.dev),
loaded from CDN. Stroke 1.5 px, 24 px grid, rounded line-cap and line-join.
Lucide matches the calm geometric tone of the editorial sans display.

## Loading

```html
<!-- One-time, in the document head -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
```

```js
// After any DOM update
lucide.createIcons({ attrs: { 'stroke-width': 1.5 } });
```

```html
<!-- Use anywhere -->
<i data-lucide="map-pin"></i>
<i data-lucide="calendar"></i>
```

## Curated list (~30 — keep tight)

**Marketplace** — `search`, `map-pin`, `calendar`, `users`, `clock`,
`heart`, `bookmark`, `arrow-right`, `arrow-up-right`, `filter`, `sliders-horizontal`

**Detail page** — `check`, `info`, `chevron-down`, `chevron-right`,
`star`, `share-2`, `play`

**Navigation** — `menu`, `x`, `home`, `inbox`, `user-round`, `bell`

**Comms** — `mail`, `phone`, `message-circle`, `external-link`

**Status** — `circle-check`, `circle-alert`, `loader`

## Custom marks (`./*.svg`)

Lucide doesn't ship golf-domain glyphs, so we drew six in the same style
(1.5 px stroke, rounded caps). Use them inline as `<img src="...">` or
inline SVG with `currentColor`:

| File              | Use |
|-------------------|-----|
| `golf-flag.svg`   | Course / hole markers; events with on-course play |
| `golf-tee.svg`    | Tee time; range sessions |
| `golf-cart.svg`   | Logistics; transfers; venue amenities |
| `range-bucket.svg`| Driving range; practice packages |

The Firmengolf wordmark itself (`../logo/firmengolf-wordmark.png`) is a
chunky slab serif with a stylised mark (golf ball + angled club shaft).
The mark alone lives in `../logo/firmengolf-mark.png` (+`-light.png`).
For tiny placements where the full wordmark is illegible (favicon, app
icon, in-product menu bar), use the mark only.

## Don't

- Mix sets — no FontAwesome, no Heroicons, no Material Symbols.
- Fill Lucide icons. They are stroke-only by design.
- Use emoji as icons. (See README.md § 4.4.)
- Use unicode dingbats — there's a Lucide glyph for every common case.
