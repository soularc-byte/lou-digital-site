# Vorschaubilder (og:image) erzeugen

Nur Werkzeuge, nicht öffentlich (`tools/` ist per `.htaccess` gesperrt).

- `screens.js`: Screenshots der Seiten (iPhone 1206×2622, MacBook 2560×1600) vom lokalen Server (Port 8765 im Repo).
- Geräte-Mockups kommen aus Shots.so (iPhone 17 Black, MacBook Air 13), zugeschnitten als `dev-*.png` (im Container unter `/home/claude/work/shots/`).
- `og5.js`: baut die sechs Vorschaubilder 1200×630 (@2x) aus Text, Mockups und 3D-Figuren. Danach auf 1200×630 verkleinern, JPG Qualität 88, nach `img/og-<seite>.jpg`, `?v=` im `og:image` der Seite erhöhen, LinkedIn Post Inspector erneut ausführen.
- `bell.html` + `render_bell.py`: 3D-Glocke (three.js, Blick von links oben) für die Automatik-Vorschau; `fig-bell.webp` ist das Ergebnis mit weichem Schatten.
- Regeln: Blickpunkt ist die Bildmitte (rechts sitzende Dinge zeigen ihre linke Seite, Figuren unterhalb der Augenhöhe); die Limetten-Markierung liegt hinter der ganzen Überschrift (eigene Ebene), damit Unterlängen der Zeile darüber sichtbar bleiben.
