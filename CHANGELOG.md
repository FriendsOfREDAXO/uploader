# Uploader Changelog

## 2.6.0 - 12.06.2025

### Features

* Anwendung der Skalierungsfunktion nun auch beim Re-Upload auf der Detailseite im Medienpool verfügbar
 (selektiv via Checkbox)
  * es wird zunächst eine browserseitige Verkleinerung versucht
  * ... falls diese fehlschlägt, passiert die Umsetzung serverseitig (MediaManager, gd) 
* Neue Subpage im AddOn ermöglicht Stapelbearbeitung von Dateien im Medienpool, deren Abmaße noch oberhalb der in den 
 Uploader-Settings eingestellten Maximalwerte liegen
  * z.B. für nachträgliche Installationen des AddOns bei Projekten mit schon befüllten Medienpools oder Uploads über FTP/SSH
  und anschließender Nutzung der MP-Synchronisierung
  * für besseres Handling auf dieser Page pjax eingeschaltet in der package.yml

_Credits an @bitshiftersgmbh (Idee + Umsetzung) | @ynamite (browserseitige Verkleinerung bei Re-Upload + Code-Optimierung)
| @ischfr (Konzeptioneller Input + Testing)_

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
