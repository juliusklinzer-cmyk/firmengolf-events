# KI-Sichtbarkeit Firmengolf Events: Befund, Roadmap, Stand

Stand 15.09.2026. Befund von Julius aus einem separaten Chat, hier gegengeprüft und als Arbeitsstand geführt.

## Befund (geprüft am 15.09.)

- **Trainings-Crawler gesperrt:** GPTBot, ClaudeBot und meta-externalagent bekommen auf firmengolf-events.de und firmengolf.app ein 403 vom Hetzner-Proxy (openresty-Seite, trifft auch die Sitemap). Die Live-.htaccess hat keine User-Agent-Regel, die Sperre liegt also bei Hetzner. Suche-Crawler (OAI-SearchBot, Claude-SearchBot, PerplexityBot, Googlebot, bingbot, Applebot-Extended) kommen durch.
- **Modellwissen leer:** Ohne Websuche nennt kein Modell Firmengolf, bei Golf-Suchbegriffen taucht die Seite über die Livesuche auf (Platz 1 bis 5), bei „Alternative zum Teamevent" gar nicht.
- **Marke:** „Firmengolf" wird von mehreren Clubs als Programmname genutzt, firmengolf.de gehört einem Firmenlauf. Eigenname daher konsequent „Firmengolf Events" und „Firmengolf Benefits".
- **On-Page:** technisch solide (318 Event-Seiten, 238 Landingpages, Schema auf Hubs und Blog), aber ohne llms.txt, ohne „Alternative"-Inhalt, ohne Preise auf Startseite und Hubs, ohne Bewertungen, Organization-Schema ohne Adresse und Gründer.
- **Off-Page:** keine Drittquellen (Presse, Listicles, Bewertungsprofile, Wikidata, YouTube).

## Einschätzung

Die Hetzner-Sperre ist wichtig, aber nicht der Haupthebel: Modellwissen entsteht erst beim nächsten Training und Nischenseiten landen dort selten. Der Hebel ist die Livesuche, und die entscheidet sich an Bing- und Google-Rankings für die Nicht-Golf-Fragen plus Erwähnungen auf Drittseiten. llms.txt ist Hygiene ohne belegte Wirkung. Bing Webmaster Tools und Google-Unternehmensprofil gehören nach vorn, Reddit gestrichen.

## Erledigt

| Datum | Maßnahme | Version |
|---|---|---|
| 15.09. | Pillar-Seite `/teamevent-alternative/`: Definition mit Zahlen, Vergleichstabelle Golf gegen Escape Room, Kochkurs, Floßbau, Bowling, Klettergarten, Musterplanung 10 Personen, Ablauf, 9 FAQ mit FAQPage- und Article-Schema, Live-Preise aus den Events. Verlinkt von Startseite (Absatz mit „Alternative") und allen Format-Hubs, in der Sitemap. Inhalte nach Julius' Antworten (Regen, Gruppengröße 6 bis 12, Vorlauf 5 Tage, Elitär-Einwand, gemischte Gruppen, Verpflegung). | 1.9.253 bis 1.9.255 |
| 15.09. | robots.txt mit expliziten Allow-Blöcken für 15 KI-Crawler und Hinweis auf llms.txt (`includes/ai-visibility.php`). | 1.9.256 |
| 15.09. | `/llms.txt`: Was Firmengolf Events ist, Abgrenzung des Eigennamens, Ablauf, Formate mit Links, Live-Preisspannen, Städte, Anbieter-Seiten, Blog, Kontakt. | 1.9.256 |
| 15.09. | Organization-Schema: Name „Firmengolf Events", alternateName, legalName, Adresse, Telefon, E-Mail, Gründer, Gründungsjahr, areaServed, knowsAbout, contactPoint, sameAs inkl. firmengolf.app. WebSite-Schema mit publisher. | 1.9.256 |

| 15.09. | Schema-Eigenname „Firmengolf Events" in Publisher, Provider und Breadcrumbs aller Blog-, Event-, Partner-, Stadt- und Format-Templates. | 1.9.259 |
| 15.09. | Blogartikel 1 von 4 veröffentlicht: „Teamevent-Ideen für 40 Personen, die nicht jeder schon gemacht hat (mit Preisen)", `/teamevent-ideen-40-personen/`, Kategorie Inspiration, Autor Julius, Bild Golf-Coach mit Gruppe. Entwurf in `docs/blog-entwuerfe/`. Hetzner-Ticket von Julius eingereicht. | Post-ID 1137 |

## Offen, nur Julius

1. **Hetzner-Ticket** (Text unten) für GPTBot, ClaudeBot, meta-externalagent, beide Domains. Erfolg prüfen: `curl -s -o /dev/null -w "%{http_code}" -A "Mozilla/5.0 (compatible; GPTBot/1.0)" https://firmengolf-events.de/` muss 200 liefern.
2. Bing Webmaster Tools: erledigt 15.09., beide Domains aus der Search Console importiert, Aktivierung dauert bis 48 Stunden. Danach prüfen, ob beide Sitemaps unter „Sitemaps" stehen.
3. Google-Unternehmensprofil: bestand schon, am 15.09. aktualisiert. Entscheidung: EIN Profil mit Namen „Firmengolf" (Dachmarke, Richtlinien erlauben keine Zusätze), Website bis zum Benefits-Start firmengolf-events.de, Kategorie Eventagentur, Beschreibung mit beiden Geschäften. Offen: Bewertungslink in die Abschlussmail nach jedem Event.

## Offen, nächste Schritte

- Phase 1: drei bis vier Vergleichsartikel im Blog („Teamevent-Ideen für 40 Personen", „Firmenevent im Sommer draußen: 7 Ideen mit Preisen", „Escape Room Alternative für Firmen", „Teambuilding für gemischte Teams"). Preisblock auf der Startseite und Preisspanne auf jedem Hub.
- Phase 2: Listicle-Portale anschreiben (lebegeil.de, hirschfeld.de, meyer-events.de, bernstein-agentur.de, gokonfetti.com), Wikidata-Eintrag, LinkedIn-Artikel, Fachmedien.
- Phase 3: drei kurze YouTube-Videos, Case Studies mit Zahlen, Partnerplatz-Links.
- firmengolf.app: Artikel „Golf als Mitarbeiter-Benefit: Was steuerlich geht (Sachbezug 50 Euro)", vorher Steuerberater.
- Monitoring: 15 Prompts alle vier Wochen in ChatGPT, Claude, Perplexity, Google AI Mode (Liste im Befund), GA4-Referrer chatgpt.com, perplexity.ai, claude.ai, copilot.microsoft.com.

## Hetzner-Ticket (Entwurf)

Betreff: Freigabe von KI-Crawlern (403 durch Proxy) für firmengolf-events.de und firmengolf.app

Guten Tag,

auf unseren beiden Webhosting-Domains firmengolf-events.de und firmengolf.app antwortet der vorgeschaltete Proxy für einzelne User-Agents mit 403 Forbidden (openresty-Fehlerseite, auch für statische Dateien wie /wp-sitemap.xml). Betroffen sind GPTBot, ClaudeBot und meta-externalagent. In unserer .htaccess und in WordPress gibt es keine entsprechende Regel, die robots.txt erlaubt diese Crawler ausdrücklich.

Bitte heben Sie die Sperre für diese User-Agents auf beiden Domains auf, beziehungsweise nennen Sie mir die Einstellung in konsoleH, mit der ich das selbst steuern kann. Test: curl mit User-Agent „Mozilla/5.0 (compatible; GPTBot/1.0)" auf https://firmengolf-events.de/ liefert aktuell 403, erwartet ist 200.

Vielen Dank und viele Grüße
Julius Klinzer, Visionpunch UG (haftungsbeschränkt)
