# Uploader für REDAXO 5

Moderner Datei-Upload für den REDAXO 5 Medienpool mit Drag & Drop Unterstützung.

## Features

- Drag & Drop Upload direkt in den Medienpool
- Clientseitige Bildverkleinerung vor dem Upload
- Chunk-Upload für große Dateien
- Mehrsprachige Benutzeroberfläche (de, en, es, sv)
- Integration in den REDAXO-Medienpool
- Automatische Vorschaubilder
- Responsive Design
- Konfigurierbare maximale Bildgrößen und Dateigröße
- Option zum automatischen Setzen des Dateinamens als Titel

## Systemvoraussetzungen

- PHP 8.1 oder höher
- REDAXO 5.18.0 oder höher
- Moderne Browser mit HTML5 File API Unterstützung

## Installation

1. Im REDAXO-Installer das Addon `uploader` herunterladen
2. Addon installieren und aktivieren
3. Berechtigungen für Benutzer unter System/Benutzer anpassen:
   - `uploader[]` - Grundlegende Uploader-Rechte
   - `uploader[page]` - Zugriff auf die Uploader-Seite

## Konfiguration

Die Konfiguration erfolgt unter "Uploader > Einstellungen":

- Maximale Bildbreite (0 = keine Begrenzung)
- Maximale Bildhöhe (0 = keine Begrenzung)
- Maximale Dateigröße in MB
- Automatische Bildverkleinerung aktivieren/deaktivieren
- Dateiname als Standardtitel aktivieren/deaktivieren

## Benutzung

1. Im Medienpool auf den Tab "Uploader" wechseln
2. Dateien per Drag & Drop in die markierte Zone ziehen oder per Klick auswählen
3. Optional: Kategorie auswählen
4. Upload startet automatisch
5. Hochgeladene Dateien erscheinen direkt im Medienpool

## Entwicklung

### Technologien

- PHP >= 8.1
- JavaScript (ES6+)
- Webpack als Bundler
- [Dropzone.js](https://www.dropzonejs.com/) für Drag & Drop Funktionalität

### Setup Entwicklungsumgebung

1. Repository klonen:
   ```bash
   git clone https://github.com/FriendsOfREDAXO/uploader.git
   ```

2. In das build-Verzeichnis wechseln:
   ```bash
   cd build
   ```

3. NPM-Dependencies installieren:
   ```bash
   npm install
   ```

### Build-Prozess

Assets erstellen (einmalig):
```bash
npm run build
```

Entwicklungsmodus mit automatischem Rebuild:
```bash
npm run watch
```

Die kompilierten Dateien werden automatisch im `assets`-Verzeichnis abgelegt:
- `assets/uploader.min.js`
- `assets/uploader.min.css`

### Übersetzungen

Sprachdateien befinden sich im `lang`-Verzeichnis:
- `de_de.lang` - Deutsch
- `en_gb.lang` - Englisch
- `es_es.lang` - Spanisch
- `sv_se.lang` - Schwedisch

### Projektstruktur

```
uploader/
├── assets/           # Kompilierte Assets
├── build/           # Build-System und Source-Dateien
├── inc/            # PHP-Include-Dateien
├── lang/           # Sprachdateien
├── lib/            # PHP-Klassen
└── pages/          # REDAXO-Seiten
```

## Bekannte Probleme

### Empty file upload result

Dies deutet auf serverseitige Upload-Limits hin. Überprüfen Sie folgende PHP-Einstellungen:
- upload_max_filesize
- post_max_size
- max_execution_time
- max_input_time
- memory_limit

### SyntaxError: JSON.parse: unexpected character

Mögliche Ursachen:
- XOutputfilter mit zusätzlichen Ausgaben
- Leerzeichen nach öffnendem PHP-Tag
- AddOns mit Ausgaben vor dem Content

## Support

- [GitHub Issues](https://github.com/FriendsOfREDAXO/uploader/issues)
- [REDAXO Forum](https://www.redaxo.org/forum/)

## Lizenz

MIT Lizenz, siehe [LICENSE](LICENSE.md)

## Autor

**Friends Of REDAXO**
- https://github.com/FriendsOfREDAXO
- https://github.com/FriendsOfREDAXO/uploader
