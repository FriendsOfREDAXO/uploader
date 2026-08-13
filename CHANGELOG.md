# Uploader Changelog

## 3.0.6 - 14.08.2026

### UI

* Optische Anpassung zurück an die REDAXO-Standards: Panel mit Überschrift wie im Medienpool-Upload, Dropzone mit sichtbarer Rückmeldung beim Ziehen einer Datei, Farben und Button-Optik aus be_style statt eigener Palette

## 3.0.5 - 08.08.2026

### Bugfixes

* Regression behoben: Bei aktivierter Option „Medienpool-Upload ersetzen" wird auf `mediapool/upload` wieder zuverlässig die Uploader-Oberfläche geladen (statt nur der Standard-Einzelupload) / fixes #116

## 3.0.3 - 14.07.2026

### Bugfixes

* Dateinamens-Kollisionen beim Mehrfach-Upload werden wieder korrekt über den Medienpool aufgelöst: Jahreszahlen im Dateinamen werden nicht mehr als Zähler interpretiert (z. B. `bericht_2025.pdf` bleibt Basisname, statt fälschlich auf `bericht_2026.pdf` zu springen) / fixes #115
* Regression behoben: `_1` wird nicht mehr pauschal angehängt, wenn noch keine echte Kollision existiert
* Uploader-Initialisierung für wiederholte Seitenaufrufe (inkl. PJAX) robuster gemacht, sodass die Upload-UI beim zweiten Aufruf nicht mehr verschwindet
* Upload-Einträge verschwinden nach erfolgreichem Upload nicht mehr kurzzeitig aus der Liste (Fade-Verhalten in den Templates entfernt)
* Erneuter Upload derselben Datei ist nach Abschluss wieder möglich: Duplikat-Sperre gilt jetzt nur für aktuell laufende Queue-/Upload-Einträge
* Dateiname in der Ergebnisliste verlinkt nach Upload auf die Medienpool-Detailseite statt auf den Direkt-Download

### UI

* Upload-Oberfläche modernisiert (Dropzone, Queue, Buttonbar) mit konsistenten CSS-Variablen
* Theme-Handling strikt nach REDAXO-Muster umgesetzt: Light-Default + `body.rex-theme-dark` + Auto-Theme über `@media (prefers-color-scheme: dark)` mit identischen Dark-Werten
* Verläufe und Rundungen im neuen Design entfernt (flacher, klarer Look)

## 3.0.2 - 08.05.2026

### Bugfixes

* Bilder innerhalb der konfigurierten Maximalmaße werden beim Bildaustausch nicht mehr re-enkodiert – bereits optimierte Bilder (z. B. via TinyPNG) landen jetzt 1:1 im Medienpool ohne Qualitätsverlust oder Größenzunahme

## 3.0.1 - 07.05.2026

### Bugfixes

* Fatal error „Class `FriendsOfRedaxo\Uploader\BulkRework` not found" beim Bildaustausch mit aktivierter Resize-Checkbox behoben / fixes #113
* Tote `use`-Statements (`BulkRework`, `BulkReworkList`) und toter `bulk_rework`-Seitenblock aus `boot.php` entfernt
* Robusteres Blob-Handling im Browser-Resize (`toBlob()`-Nullfall), damit kein `TypeError` bei `blob.name` mehr auftritt
* Bootstrap-3-konformes Dateiauswahl-UI in der Medienpool-Detailansicht (Button + Dateiname im Input-Group-Stil)
* Sichtbarer Größenstatus direkt bei der Vorschau (`Übergroß` / `innerhalb der Limits`) inkl. konfigurierter Maximalwerte
* Live-Vorschau und Dimensionsanzeige beim Dateiaustausch robuster gemacht (auch bei dynamischen/duplizierten `file_new`-Feldern)
* Dateityp-Erkennung für Vorschau auf Fallback per Dateiendung erweitert (falls Browser-MIME leer liefert)
* AVIF-Dateien werden beim Bildaustausch im Browser korrekt erkannt – bei fehlendem Canvas-AVIF-Encode-Support wird das Original direkt zum Server gesendet und dort skaliert
* AVIF-Unterstützung in `ImageResizer` ergänzt: GD nutzt `imageavif()` (PHP 8.1+ mit libavif) wenn verfügbar; fehlt die Unterstützung, bleibt das Original unverändert erhalten (kein Absturz)
* **Bilder innerhalb der Maximalmaße werden nicht mehr re-enkodiert**: War „Übergroßes Bild verkleinern" aktiv, wurden bisher alle Bilder durch den Browser via Canvas neu enkodiert – auch bereits optimierte Bilder, was zu größeren Dateien führen konnte. Jetzt werden nur tatsächlich verkleinerte Bilder als neue Blob-Datei übergeben; Bilder innerhalb der Limits landen 1:1 im Medienpool.

### Features

* Neue Option „Resize erzwingen" in den AddOn-Einstellungen – sperrt die Resize-Checkbox im Medienpool dauerhaft ein, sodass Redakteure sie nicht deaktivieren können

### Intern

* Neue Klasse `FriendsOfRedaxo\Uploader\ImageResizer` übernimmt das serverseitige Resize beim Bildaustausch im Medienpool
  * Nutzt ImageMagick (Imagick PHP-Extension) wenn verfügbar, fällt sonst auf GD zurück
  * Unterstützt JPEG, PNG, GIF, WebP, AVIF; GIF-Animationen werden via Imagick korrekt behandelt

## 3.0.0 - 07.05.2026

### Breaking Changes

* **Bulk-Verarbeitung entfernt** – die Stapelverarbeitung zum nachträglichen Verkleinern von Medienpool-Bildern wurde in das separate AddOn [`mediapool_tools`](https://github.com/FriendsOfREDAXO/mediapool_tools) ausgelagert
  * Klassen `BulkRework`, `BulkReworkList`, `ApiBulkProcess` entfernt
  * Berechtigung `uploader[bulk_rework]` entfernt
  * Unterseite `bulk_rework` entfernt
  * Assets `uploader_bulk_rework.js`, `uploader_bulk_rework_simple.js` entfernt

### Features

* Bildvorschau-Modal in der Medienpool-Detailansicht hinzugefügt
* „Datei übernehmen"-Button wird bei fehlgeschlagener Dateivalidierung ausgeblendet / fixes #111

## 2.6.0 - 13.06.2025

### Features

* The scaling function is now also available when re-uploading on the details page in the media pool
 (selectively via checkbox)
  * First, a browser-side reduction is attempted
  * ... if this fails, the conversion takes place on the server side (MediaManager, gd)
* New subpage in the add-on enables batch processing of files in the media pool whose dimensions are still above the 
 maximum values set in the uploader settings
  * e.g. for subsequent installation of the add-on in projects with media pools that are already filled or uploads via FTP/SSH
  and subsequent use of MP synchronisation
  * For better handling on this page, pjax is enabled in package.yml

_Idee + Hauptteil der Umsetzung von @bitshiftersgmbh_

### New Contributors

* @ischfr : Conceptual input/discussion + testing
* @ynamite : Browser-side reduction during re-upload + code optimisation (especially in JS)

## 2.5.1 - 05.05.2025

### Bugfixes

* Server-side verification of the target category ID; it is now ensured that the category exists and that the user has
the necessary rights / fixed #90 by @skerbis 
* Placeholders (`jfucounterNjfucounter`) are converted to _N before saving / fixed #91 by @skerbis 
* Additional safeguard in `generate_response()` so that `rex_media::get()` is only checked for `isImage()` if an object
is actually returned / by @skerbis 
* Preventing a file from being selected multiple times for upload / by @skerbis 
## 2.4.3 - 03.04.2025

* Switch to `rex_media_service` by @skerbis 

## 2.4.2 - 11.11.2023

### What's Changed

* `fetchRequestValues`: empty fields should be ignored by @akrys in #78
* Delete jquery.js, fix Dependabot alerts by @eaCe in #79

### New Contributors

* @akrys made their first contribution in #78
* @eaCe made their first contribution in #79

## 2.4.1 - 18.03.2023

### Bugfixes

* Fix deprecation warning in PHP 8.1 by @IngoWinter

## 2.4.0 - 14.03.2023

### What's Changed

* adding feature 'filename as title' to upload form (refs #74) by @bitshiftersgmbh in #75

### New Contributors
* @bitshiftersgmbh made their first contribution in #75

## 2.3.0 – 18.10.2021

### Features

* Dressed up for new dark mode (REDAXO 5.13) 🦇


## 2.2.2 – 14.03.2021

### Bugfixes

* GD als default für Bildberechnungen


## 2.2.1 – 22.02.2021

### Bugfixes

* Fehler beim Aufruf der Extension behoben


## 2.2.0 – 10.09.2020

### Features

* Alle JS Ressourcen auf den neuesten Stand gebracht


## 2.1.0 – 14.08.2020

### Features

* Vendor Update
