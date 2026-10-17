# Changelog

## 3.0.0 (17.10.2026)

Erste öffentliche Version.

- Produktname „ConRat Photo Gallery“ (Titel, Site Set, composer.json); Extension-Key `crgalerie` und Paket
  `conrat/crgalerie` bleiben.
- LICENSE (GPL-2.0), SECURITY.md, CONTRIBUTING.md; README mit Support- und Lizenzabschnitt.
- Öffentliches Repository: `ConRatWebSolutions/conrat-typo3-photo-gallery`.
- CI testet PHP 8.2, 8.3, 8.4 und 8.5.
- Dokumentation für docs.typo3.org in `Documentation/` (englisch, mit Screenshots aller Layouts); README mit
  Screenshots, Link zur Live-Demo und zur Produktseite.

## 3.0.0-beta.4

- **Vorbereitung TER/Packagist:** `ext_emconf.php` (nur für TER/Tailor; TYPO3 14.3 liest die Metadaten aus
  `composer.json`), Extension-Icon
  `Resources/Public/Icons/Extension.svg`, `composer.json` mit `homepage`, `support` und `keywords`,
  `.gitattributes` hält Tests, Build-Dateien und interne Dokumentation aus dem Paket.
- **Entfernt:** das wirkungslose Argument `basis` von `crg:zeilenSizes` (angekündigt in beta.3). Überschriebene
  Templates, die es noch übergeben, brechen mit einem Fluid-Fehler ab – Argument dort streichen.
- Aufgeräumt: ungenutzte Icons und Sprachdateien (u. a. `ext_icon.gif`, `locallang_csh_*.xlf`), leeres Partial.
- README: Abschnitt „Grenzen“ (eine Galerie-Sammlung je Installation, keine Übersetzung, Workspaces).
- **Mehrsprachig (Englisch und Deutsch):** Alle Texte in Frontend, Backend-Modul, Upload, FlexForms, TCA,
  Extension-Konfiguration und Site Settings kommen aus `locallang*.xlf` (Englisch) bzw. `de.locallang*.xlf`. Das
  Frontend folgt der Sprache der Site, das Modul der Sprache des Redakteurs. **Geändert:** Auf Sites mit englischer
  Sprache erscheinen die Galerietexte jetzt englisch („3 photos“, „Image viewer“); Texte lassen sich per
  `plugin.tx_crgalerie._LOCAL_LANG` oder `resourceOverrides` anpassen (README, „Texte und Übersetzungen“).
  Die Großansicht bekommt ihre Texte über `data-crg-text-*` an der Galerie – eigene `Bilder.html` übernehmen sie
  aus dem Original, sonst zeigt die Großansicht englische Texte. Konsolenausgabe von `crgalerie:vorwaermen` englisch.
- **Modul:** Ausblenden, Einblenden und Löschen laufen als Formular (POST) statt als Link; ein aufgerufener Link
  ändert nichts mehr. „1 Bild“ statt „1 Bilder“.
- **Content Security Policy:** Der Ohne-JavaScript-Fallback ist ein Stylesheet (`galerie-noscript.css`) statt eines
  `<style>`-Elements, das die CSP von TYPO3 blockiert. Mit aktivierter Frontend-CSP geprüft (keine Verstöße).
- Übersicht: Anzahl und erstes Bild je Galerie werden einmal ermittelt statt bei jedem Zugriff (bis zu fünfmal) alle
  Bilder samt Dateisystem-Prüfung zu durchlaufen.
- `upload.js` als ES-Modul modernisiert.
- README auf Englisch, deutsche Fassung in `README.de.md`.
- Release-Workflow (`.github/workflows/release.yml`): prüft bei Tags, dass Tag, `composer.json` und
  `ext_emconf.php` dieselbe Version nennen; lädt finale Versionen per Tailor ins TER, sobald das Secret
  `TYPO3_API_TOKEN` gesetzt ist.

## 3.0.0-beta.3

Änderungen gegenüber 3.0.0-beta.2:

- **`sizes` im Mosaik in Zeilen auf allen Breiten je Bild**: Tablet und Desktop nutzen nicht mehr pauschal
  `33vw`/`50vw`, sondern Seitenverhältnis × Zeilenhöhe 220 px × Reserve 1,4 (`(min-width: 600px) min(100vw, r × 308 px)`),
  das Handy wie bisher r × 133 px bzw. r × 88 px ab DPR 2,5. Kleine Kacheln laden auf dem Desktop die 400er statt der
  800er/1200er-Fassung (Testseite, erste 12 Bilder, 1280 px: DPR 1 2,9 MB → 2,2 MB, DPR 2 2,9 MB → 2,6 MB bei
  geringerer Hochskalierung: höchstens 1,17 statt 1,39). `crg:zeilenSizes` hat neue Argumente `zeilenhoehe` und
  `zeilenhoeheMobil`; **geändert:** das Argument `basis` hat keine Wirkung mehr, die Variable `sizes` enthält bei
  Zeilen nur noch einen Rückfallwert für Querformat 3:2.
- `sizes` Raster/Spalten auf dem Desktop `min(25vw, 360px)` statt `25vw` (breite Bildschirme laden bei DPR 1 nicht mehr
  die 800er), Titelbild `(min-width: 1024px) min(50vw, 720px), 67vw` statt `(min-width: 600px) 50vw, 67vw` (auf
  Tablets belegt das Titelbild zwei von drei Spalten, 50vw war zu klein). Übersicht geprüft, unverändert.
- **Großansicht barrierearm**: Beim Öffnen per Maus oder Tippen springt der Fokus jetzt in den Dialog (bisher nur per
  Tastatur) und beim Schließen zurück auf das geöffnete Bild, auch in Safari, das Links beim Klicken nicht
  fokussiert. Der Dialog heißt „Großansicht: <Galerietitel>“ und trägt `aria-modal="true"`.
- **Großansicht über Cookie-Bannern und Overlays**: `z-index` 2147483000 statt PhotoSwipes 100000, einstellbar über
  `--crg-lightbox-z`.
- **Übersicht und Galerie robust gegen Projekt-CSS**: Listenpunkte, Einrückung (`padding-left`, `margin`),
  `::before`-Aufzählungszeichen, Unterstreichung, Linkfarbe und -stärke aus globalen Regeln (z. B.
  `.frame-default ul li`, `.section-wrapper a`) wirken nicht mehr auf Kacheln, Bildliste, Knopf „Alle anzeigen“ und
  Zurück-Link (doppelte Klasse statt `!important`). Der Kacheltitel wird bei Hover und Tastaturfokus unterstrichen,
  jetzt auch im Kachelstil „über dem Bild“.
  **Geändert:** Die doppelten Klassen erhöhen die Spezifität. Projekt-CSS, das diese Eigenschaften (z. B. `margin`,
  `padding`, `list-style`, `text-decoration`, Linkfarbe der Kacheln) mit nur einer Klasse überschreibt, braucht jetzt
  zwei Klassen, z. B. `.crg-uebersicht .crg-uebersicht__kachel` – siehe README, Abschnitt „Robust gegen Projekt-CSS“.

## 3.0.0-beta.2

Änderungen gegenüber 3.0.0-beta.1:

- Backend-Modul hängt unter „Content“ (TYPO3 14 hat „Web“ umbenannt, `parent` jetzt `content`) mit
  `after => records`; weil EXT:viewpage sich ebenfalls hinter „Records“ einhängt, steht es hinter „Preview“. Bezeichner `web_crgalerie` und Pfad `/module/web/crgalerie` bleiben – Lesezeichen und
  Gruppenrechte gelten weiter.
- Modul heißt „Galerien“ statt „Galerie Administration“.
- Eigenes Modul-Icon im Stil der TYPO3-14-Modulicons statt grauem TYPO3-Logo; die Inhaltselemente „Galerie“
  (`crgalerie-plugin`) und „Galerie-Übersicht“ haben Galerie-Symbole. **Geändert:** Das Icon der Übersicht heißt
  jetzt `crgalerie-uebersicht` statt `crgalerie-plugin` (wer den Bezeichner in eigenem TSconfig/TCA nutzt, anpassen).
- Titel, die nur aus dem Dateinamen bestehen (beim Upload gesetzt, z. B. „IMG 1234“), gelten im Frontend als leer:
  kein Dateiname mehr als Bildtitel, Alt-Text oder in der Lightbox. Gespeicherte Daten bleiben unverändert.
- Handy: Standard-Zeilenhöhe im Mosaik in Zeilen 100px statt 140px (`--crg-zeilenhoehe-mobil`). `sizes` auf
  Handys knapper: Zeilen je Bild aus dem Seitenverhältnis (neuer ViewHelper `crg:zeilenSizes`, Variable `sizes`
  enthält bei Zeilen nur noch Tablet/Desktop), Raster/Spalten `34vw`, Titelbild `67vw`. Geräte mit DPR 3 laden
  meist dieselbe 400er-Fassung wie DPR 2 (Testseite: 2,9 MB → 0,9 MB für die ersten 12 Bilder), Panoramen
  bekommen bei DPR 2 die 800er.

## 3.0.0 (in Arbeit)

### Breaking Changes

- Frontend-Markup und CSS-Klassen neu (`crg`, `crg__*`, `crg-uebersicht*`); die alten Dateien
  `Resources/Public/galerie.css`, `galerie.js`, `masonry.js` entfallen.
- Nur noch TYPO3 14.3+ (PHP 8.2+).
- Plugin „ConRat Galerie“ (`list_type` `crgalerie_crgalerie`) ersetzt durch die Inhaltselemente
  `crgalerie_galerie` und `crgalerie_uebersicht`.
- URL-Parameter `tx_crgalerie_crgalerie[…]` → `tx_crgalerie_uebersicht[galerie]`.
- Templates, Partials und Template-Variablen neu; Overrides aus 2.x funktionieren nicht mehr.
- Statisches TypoScript entfällt → Site Set `conrat/crgalerie`.
- Storage-PID aus `module.tx_crgalerie…` → Extension-Konfiguration `storagePid`; Vorschau-Seite (bisher fest 105)
  → `previewPid`.
- Seitentitel und Metadatenfelder für Urheber/Lizenz (bisher fest im Code) nur noch per Site Settings.
- Upload im Modul neu: Bilder werden über FAL in `<uploadFolder>/<uid>-<slug>/` gespeichert und auf
  `maxBildgroesse` verkleinert; es gibt keine `raw/`/`thumbnails/`-Fassungen und keinen externen Resizer mehr.
- TypoScript-Einstellungen `plugin.tx_crgalerie_crgalerie.settings.mdWidth/thWidth/thHeight/diffVertical/cclicense/urheber`
  entfallen.
- Alle Änderungen im Modul laufen über den DataHandler und die FAL-API: Redakteure brauchen Tabellen-, Feld-,
  Datenbank- und Dateirechte – vollständige Liste in der README, Abschnitt „Rechte für Redakteure“.
  Galerien auf Ebene 0 können nur Admins bearbeiten.
- Löschen eines Bildes im Modul entfernt auch die Datei, wenn sie nirgends sonst verwendet wird (weder als
  Dateireferenz noch laut Referenzindex, z. B. als `t3://file`-Link in einem Text).

### Neu

- Frontend neu: vier Darstellungen (Mosaik in Zeilen, Raster, Mosaik in Spalten, Titelbild), „Alle X Fotos
  anzeigen“ (Bilder ab „anzahlStart“ kommen serverseitig mit `hidden` und werden nicht vorab geladen; ohne
  JavaScript zeigt ein noscript-Fallback alle), Bildgrößen per srcset und Lazy Loading, Lightbox PhotoSwipe 5
  (ohne jQuery; `Css/lightbox.css` wird immer geladen), Übersicht mit zwei Kachelstilen, Anpassung über
  CSS-Variablen (`--crg-*`, auch an `:root`; Spalten und Zeilenhöhe je Breakpoint mit `-tablet`/`-mobil`).
  Die Übersicht lädt keine Lightbox (kein PhotoSwipe, kein `galerie.js`) und keine große Bildfassung.
  Überschriftenebene des Galerietitels folgt der Elementüberschrift (`header_layout` + 1, höchstens h6).
- Bildfassungen für das Frontend (srcset und Lightbox) entstehen schon beim Upload im Modul; für Altbestände
  erzeugt `vendor/bin/typo3 crgalerie:vorwaermen [--galerie=<uid>]` sie vorab.
- Upload von JPG, PNG, WebP und HEIC (HEIC wird in JPG umgewandelt, wenn der Bildprozessor es kann); EXIF-Drehung;
  EXIF-/GPS-Daten werden entfernt; das Format wird am Dateianfang geprüft, bevor ImageMagick die Datei sieht;
  Bilder über 100 Megapixel werden abgelehnt;
  Frontend zeigt den Fotografen der Galerie, wenn ein Bild keinen eigenen Urheber hat.

### Upgrade von 2.x

1. Vor dem Entfernen alter Datenbankspalten den Upgrade-Wizard „crgalerie: Plugin auf Inhaltselemente … umstellen“
   ausführen (`vendor/bin/typo3 upgrade:run crgalerieCTypeMigration`).
   Danach den Cache leeren (`vendor/bin/typo3 cache:flush`) – der Wizard leert den Seiten-Cache nicht.
2. Extension-Konfiguration `storagePid` und `previewPid` setzen. „Neue Galerie“ legt im Sysordner `storagePid` an.
   Bestandsgalerien auf Ebene 0 (pid 0) können nur Admins bearbeiten – für Redakteure in den Sysordner verschieben.
3. Site Set `conrat/crgalerie` einbinden, ggf. Site Settings setzen
   (z. B. `crgalerie.metadata.creditField: urheber`, `licenseField: cclicense`,
   `pageTitle: 'Unsere Region in Bildern - {galerie}'`).
4. Eigene Template-Overrides an die neuen Variablen anpassen.
5. Extension-Konfiguration `uploadFolder` und `maxBildgroesse` prüfen. Die alten Ordner
   `fileadmin/crgalerie/<uid>/raw/` und `/thumbnails/` werden nicht mehr verwendet und können nach einer Sicherung
   gelöscht werden.
6. Redakteure brauchen jetzt Tabellen-, Datenbank- und Dateirechte, siehe README, Abschnitt
   „Rechte für Redakteure“.

### Behoben

- SQL-Injection über die URL-Parameter `galerie`/`category` im Frontend.
- Galerie-Übersicht: Bilder mit fehlender Datei (oder Verweis auf einen nicht vorhandenen `sys_file`-Datensatz)
  zählen nicht mehr mit und brechen die Übersicht nicht mehr ab; Galerien nur mit solchen Bildern erscheinen nicht.
- Seiten mit Galerien werden auch nach Änderungen über die Bearbeitungsmaske (Galerie, Bild, Bild-Metadaten) neu
  aufgebaut.

### Bekannte Einschränkungen

- Wird eine Galerie außerhalb des Moduls gelöscht, bleiben ihre Dateien im Upload-Ordner liegen.
