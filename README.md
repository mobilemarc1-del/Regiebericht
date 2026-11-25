# Regiebericht Generator

Web-App zur Erstellung von Regieberichten mit PDF-Export.

## Features

- 📱 Optimiert für iPad / iPhone / Desktop
- 🏢 Firmen-Stammdaten speichern
- 👷 Mitarbeiter-Stammdaten speichern
- ⏱️ Automatische Stundenberechnung
- 📊 Zuschlagsberechnung (Samstag/Sonntag/Überstunden)
- 🔢 Automatische Berichtsnummerierung
- ✍️ Unterschriftenfelder (Touch & Apple Pencil)
- 📄 PDF-Export
- 📧 PDF per E-Mail senden

## Nutzung

Öffne die App unter: `https://DEIN-USERNAME.github.io/regiebericht/`

Alle Daten werden lokal im Browser gespeichert (localStorage).

## 🎤 Spracheingabe (Albanisch → Deutsch)

Der Handwerker tippt auf **🎤 Fol shqip**, spricht auf Albanisch (z. B. *„Sot kam punuar te Müller nga 7 deri 4, pushim gjysmë ore, kam vendosur pllaka në banjë“*) und tippt nochmal. Die App zeigt eine albanische Kontrolle plus deutsche Übersetzung; mit **✓ Po / Übernehmen** werden Datum, Auftraggeber, Bauvorhaben, Arbeitszeiten, Tätigkeiten und Material auf Deutsch ausgefüllt.

### Einrichtung Server (PHP 7.4+ mit cURL, HTTPS)

1. Ordner `server/` auf einen PHP-Webspace hochladen (GitHub Pages kann kein PHP).
2. `config.sample.php` → `config.php` kopieren, OpenAI-API-Key und ein eigenes `app_token` eintragen,
   unter `allowed_origins` die Adresse der App (z. B. `https://mobilemarc1-del.github.io`).
3. In der App: **⚙️ Stammdaten → 🎤 Sprache** → Adresse von `sprache.php`, Token und Name des Sprechers eintragen.

Kosten: pro Meldung ca. 1 Cent (Spracherkennung + Übersetzung).
