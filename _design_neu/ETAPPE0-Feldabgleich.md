# Etappe 0 — Foundation & Feld-Abgleich (rev. 2 ↔ Bestand)

Stand 2026-06-08. Legende: ✅ vorhanden · ⚠️ teilweise/anzupassen · ❌ neu.

## Foundation
- **Tokens:** ✅ alle 126 Design-Tokens (`colors_and_type.css`) sind bereits in `fge-frontend.css` (128). Keine Arbeit nötig.
- **Fonts:** ✅ Bricolage Grotesque als `@font-face` in `fge-frontend.css` (aus `../fonts/`). ⚠️ Instrument Sans / Instrument Serif Italic / JetBrains Mono noch verifizieren (wahrscheinlich vorhanden).
- **Rollen:** ⚠️ vorhanden ist **eine** Rolle `firmengolf_partner` + Bindung Partner↔User via `_fge_assigned_wp_user_id` (umgekehrte Richtung). rev.2 will `fg_partner_manager` + `org`-User-Meta = partner_id. → Angleichung/Umbenennung, kein Neubau. `fg_admin` nur falls Admin-App dazukommt.

## Partner (rev.2 §1 / §1.1–1.4)
Kern (§1): name/city/region/contact/role/email/phone/address/status/rating/joined → **alle ✅** (über `public_golfclub_name`, `federal_state`/`free_region`, `main_contact_*`, `street/house_number/postal_code/city`, `partner_status`, `rating`, `partner_since`).

| rev.2-Feld | Status | Heutiges Pendant / Hinweis |
|---|---|---|
| `golf_type` (Platztyp, Single-Select) | ❌ neu | kein „Golfplatz-Typ"-Klassifikator vorhanden |
| `infra` — **Infrastruktur-Katalog** (5 Gruppen, ~50 Items) | ❌ **groß neu** | heute nur verstreute Kapazitäten + Event-`has_*`; kein strukturierter Katalog |
| `cap` — bedingte Kapazitäten | ⚠️ teils | vorhanden: range/putting/short_game/meeting/gastro(+outdoor)/golf_teacher/parking, min/max. **Neu:** Indoor-Sim, Seminar/Konferenz/Workshop/Event-Raum, Terrasse/Außen/Lounge, Schnupper-/Platzreife-/Firmenkurs + „nur wenn infra gewählt"-Logik |
| Anfahrt `arrival.car/parking/train` | ✅ | `_fge_poi_car/parking/train` (+ `directions_text`) |
| `arrival.shuttle` | ❌ neu | (haben `poi_hotel`, aber kein shuttle) |
| `arrival.eStation` (E-Ladestation, bool) | ❌ neu | |
| `formats` (anbietbare Typen) | ✅ | `_fge_event_formats` |
| `avail` (weekdays/leadTime/season) | ✅ | `preferred_event_days`, `min_lead_time_days`, `season` |
| `finalNote` | ❌ neu | (oder `internal_note` zweckentfremden — besser eigenes Feld) |
| **Preis/USt/Bank im Onboarding** | ⚠️ entfernen | rev.2: **raus aus Onboarding** (nur Infotext). Heute erfasst: `default_markup_percent`, `billing_method_internal`, `bank_details_available`, `vat_required`, `tax_number_or_vat_id` → aus Onboarding-Flow nehmen |
| **Ansprechpartner** (mehrere, 30 Rollen, is_owner) §1.2 | ❌ **neu (Etappe 3)** | heute nur 1 `main_contact_*`; rev.2 will n Kontakte (Tabelle/Repeater) + 30-Rollen-Enum |

## Event/Angebot (rev.2 §2 / §2.3 / §2.4)
Kern: title/slug/format/venue/region/duration/groupMin-Max/rating/reviews/gallery/tags/featured/views/bookings → **✅** (`event_type`, `assigned_partner_id`, `region`, `participants_min/max`, `reviews_count`, `event_gallery_ids`, `event_tags`, `featured`, `*_total`).

| rev.2-Feld | Status | Hinweis |
|---|---|---|
| `owner` (partner\|firmengolf) | ⚠️ | evtl. aus `provider_type` ableitbar — prüfen/angleichen |
| `includes` (Repeater Leistungen) | ❌ neu | heute `has_*`-Booleans; rev.2 will freie Liste, vorbelegt aus `infra` |
| `dayflow` („So läuft der Tag ab") | ❌ neu | |
| `status` Lifecycle `entwurf→in-pruefung→published→paused` | ⚠️ angleichen | `event_status` existiert, **andere Werte/Übergänge** |
| `release_mode` (`us`\|`approve`) + Freigabe-Personen | ❌ neu | speist Termin-Koordination (§3.3) |
| Preis-Modell: `price_mode`, `gesamt_amount`+`gesamt_basis`, `line_items`, +20% konstant | ⚠️ umbauen | heute `pricing_mode`/`sale_price_net`/`*_price_possible`/variabler `markup_percent` → auf amount+basis + line_items + fixe 20% |
| `spotsLeft` | ❌ neu | „nur noch X Plätze" |
| `eyebrow` / `summary` / `heroImage` | ⚠️ | aus `card_description` / Beitragsbild ableitbar |

## Anfragen (rev.2 §3) → Etappe 5
Entscheidung: **eigene DB-Tabellen** `fg_requests` / `fg_request_dates` / `fg_request_responses` + Migration der heutigen `firmengolf_request`-CPT-Daten. Heutige Meta deckt viele Spalten ab (company/contact/email/phone/city/group_size/budget/services/note/status/created). **Neu:** `kind`, Event-Snapshots, `deadline`/`overdue`/`taken_over`, **Wunschtermine** + **Antworten je Person** (komplett neu).

## Fazit
- **Foundation + ~70 % der Felder sind vorhanden** → wir erweitern die bestehenden CPTs (`firmengolf_partner/event`) mit dem vorhandenen Meta-Box-Muster (kein ACF, keine Umbenennung).
- **Echter Neu-Aufwand:** (a) Infrastruktur-Katalog + bedingte Kapazitäten, (b) Ansprechpartner/30 Rollen, (c) Event-Editor-Felder (includes/dayflow/Preis-Modell/Lifecycle/release_mode), (d) Anfrage-Engine (Tabellen + Termin-Koordination).
- **Vereinfachungen:** Preis/USt/Bank raus aus Onboarding.
