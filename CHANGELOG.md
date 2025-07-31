# Uploader Changelog

## x.x.x - xx.xx.20xx

* Namespace changed from `uploader` to `FriendsOfRedaxo\Uploader`
  - class `uploader\lib\uploader_bulk_rework` renamed to `FriendsOfRedaxo\Uploader\BulkRework`
  - class `uploader\lib\uploader_bulk_rework_list` renamed to `FriendsOfRedaxo\Uploader\BulkReworkList`

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
