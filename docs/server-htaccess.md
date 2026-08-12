# Live .htaccess (public_html) — Stand 2026-07-10
# Achtung: liegt NICHT in git. Nach einem Fresh-Deploy (Duplicator) diesen Stand wiederherstellen.

## Offen: www-Variante braucht 2 Redirect-Hops (Audit 2026-08-12)

`http://www.firmengolf-events.de` läuft heute über zwei 301er
(`https://www.…` → `https://firmengolf-events.de`). Alle anderen Varianten lösen
in einem Hop auf. Der folgende Block direkt unter `RewriteEngine On` im
visionpunch-Abschnitt macht daraus einen Hop:

```apache
# www -> Apex in einem Hop (statt Umweg über https://www.…)
RewriteCond %{HTTP_HOST} ^www\.firmengolf-events\.de$ [NC]
RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/ [NC]
RewriteRule ^ https://firmengolf-events.de%{REQUEST_URI} [R=301,L]
```

Bewusst NICHT automatisch ausgerollt: die Datei liegt nur auf dem Live-Server
und ein Syntaxfehler darin nimmt die komplette Seite mit einem 500er offline.
Nach dem Einspielen prüfen mit
`curl -sIL http://www.firmengolf-events.de/ -o /dev/null -w '%{num_redirects}\n'`
(Ziel: 1).
```apache
# This file was updated by Duplicator on 2026-07-06 08:04:40.
# See the original_files_ folder for the original source_site_htaccess file.
# BEGIN visionpunch-Weiterleitung (301 -> firmengolf-events.de, Julius 2026-07-07)
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTP_HOST} ^(www\.)?visionpunch\.de$ [NC]
RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/ [NC]
RewriteRule ^ https://firmengolf-events.de%{REQUEST_URI} [R=301,L]
</IfModule>
# END visionpunch-Weiterleitung

# BEGIN WordPress
# Die Direktiven (Zeilen) zwischen „BEGIN WordPress“ und „END WordPress“ sind
# dynamisch generiert und sollten nur über WordPress-Filter geändert werden.
# Alle Änderungen an den Direktiven zwischen diesen Markierungen werden überschrieben.
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>

# END WordPress
# BEGIN Firmengolf Performance (Caching + Kompression, Julius 2026-07-10)
<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript application/json application/xml image/svg+xml font/ttf
</IfModule>
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType image/jpeg "access plus 6 months"
ExpiresByType image/png "access plus 6 months"
ExpiresByType image/webp "access plus 6 months"
ExpiresByType image/gif "access plus 6 months"
ExpiresByType image/svg+xml "access plus 6 months"
ExpiresByType image/x-icon "access plus 6 months"
ExpiresByType font/woff2 "access plus 1 year"
ExpiresByType font/woff "access plus 1 year"
# CSS/JS tragen ?ver=-Cache-Buster (FGE_VERSION), lange TTL ist sicher
ExpiresByType text/css "access plus 1 year"
ExpiresByType application/javascript "access plus 1 year"
ExpiresByType text/javascript "access plus 1 year"
</IfModule>
# END Firmengolf Performance

# BEGIN Firmengolf WebP (automatisch .webp ausliefern, wenn Browser + Zwillingsdatei vorhanden)
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTP_ACCEPT} image/webp
RewriteCond %{REQUEST_FILENAME}.webp -f
RewriteRule ^(.+)\.(jpe?g|png)$ $1.$2.webp [T=image/webp,L]
</IfModule>
<IfModule mod_headers.c>
<FilesMatch "\.(jpe?g|png)$">
Header append Vary Accept
</FilesMatch>
</IfModule>
# END Firmengolf WebP
```
