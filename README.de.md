# ConRat Photo Gallery für TYPO3 14 (crgalerie)

*English version: [README.md](README.md)*

Bildergalerien mit zentraler Pflege im Backend-Modul „Content > Galerien“: Galerien anlegen, Bilder per Drag-and-drop
hochladen und sortieren, Bilder und Galerien ausblenden. Galerien hängen nicht an Seiten.

**Live-Demo:** https://demotypo14.conrat-projects.de/fotogalerie ·
**Dokumentation (englisch):** https://docs.typo3.org/p/conratwebsolutions/crgalerie/main/en-us/ ·
**Produktseite:** https://somesolutions.de/typo3-bildergalerie/ ·
**TER:** https://extensions.typo3.org/extension/crgalerie ·
**Packagist:** https://packagist.org/packages/conratwebsolutions/crgalerie

| Mosaik in Zeilen | Raster |
|---|---|
| ![Mosaik in Zeilen](Documentation/Images/layout-mosaik-zeilen.jpg) | ![Raster](Documentation/Images/layout-raster.jpg) |
| **Mosaik in Spalten** | **Großes Titelbild mit Raster** |
| ![Mosaik in Spalten](Documentation/Images/layout-mosaik-spalten.jpg) | ![Titelbild](Documentation/Images/layout-titelbild.jpg) |

## Einbinden

```bash
composer require conratwebsolutions/crgalerie
vendor/bin/typo3 extension:setup
```

## Konfiguration

1. **Extension-Konfiguration** (Einstellungen > Extension-Konfiguration > crgalerie):
   - `storagePid` – Sysordner, in dem das Modul neue Galerien und Bilder speichert. Ältere Galerien liegen oft
     auf Ebene 0 (pid 0) – die können nur Admins bearbeiten; für Redakteure in den Sysordner verschieben.
   - `previewPid` – Seite mit einem Element „Galerie-Übersicht“ für die Vorschau-Links im Modul
   - `uploadFolder` – FAL-Ordner für hochgeladene Bilder (Standard `1:/galerien/`), je Galerie entsteht ein
     Unterordner `<uid>-<slug des Titels>` (z. B. `12-sommerfest-2026`)
   - `maxBildgroesse` – längste Seite in Pixeln, auf die Bilder beim Upload verkleinert werden (Standard 2560)
2. **Site Set** „ConRat Photo Gallery“ (`conrat/crgalerie`) in der Site-Konfiguration als Abhängigkeit eintragen.
3. **Site Settings** (optional):
   - `crgalerie.pageTitle` – Seitentitel bei Galerie-Anzeige, z. B. `{galerie} – Bildergalerie`
   - `crgalerie.metadata.creditField` – Urheber-Spalte in `sys_file_metadata`, z. B. `creator`
   - `crgalerie.metadata.licenseField` – Lizenz-Spalte, z. B. `copyright`

### Grenzen

- **Eine Galerie-Sammlung je Installation:** Galerien und Kategorien hängen an keiner Seite, `storagePid` gilt global.
  In einer Installation mit mehreren Sites sehen Modul und Inhaltselemente alle Galerien aller Sites; trennen lässt
  sich das nur über Kategorien.
- **Übersetzung:** Galerien und Bilder werden nicht übersetzt; Bildtitel und -beschreibungen kommen aus den
  Datei-Metadaten (`sys_file_metadata`) bzw. dem Bild-Datensatz der Standardsprache.
- **Workspaces:** Das Modul zeigt immer den Live-Stand.

## Inhaltselemente

- **Galerie** – zeigt eine ausgewählte Galerie.
- **Galerie-Übersicht** – Kacheln aller Galerien (oder einer Kategorie); ein Klick öffnet die Galerie auf
  derselben Seite.

## Darstellung im Frontend

- **Darstellung** (pro Element): Mosaik in Zeilen (Standard), Raster, Mosaik in Spalten, großes Titelbild und Raster.
- **Bilder zunächst zeigen**: Standard 12, 0 = alle; weitere Bilder über „Alle X Fotos anzeigen“. Die übrigen Bilder
  werden serverseitig mit `hidden` ausgeliefert (und deshalb nicht vorab geladen) und erst per Klick eingeblendet.
  Ohne JavaScript zeigt ein `<noscript>`-Fallback alle Bilder.
- **Kacheln der Übersicht**: Titel unter dem Bild (Standard) oder über dem Bild.
- Großansicht mit PhotoSwipe 5 (Wischen, Pfeiltasten, ESC, Zoom), Bildtitel, Bildunterschrift und Urheber. Die
  Großansicht ist ein benannter, modaler Dialog („Großansicht: <Galerietitel>“ bzw. „Image viewer: …“ auf englischen
  Sites, `aria-modal="true"`); der Fokus
  springt beim Öffnen – auch per Maus oder Tippen – in den Dialog und beim Schließen zurück auf das geöffnete Bild.
- Die Großansicht liegt über Cookie-Bannern und anderen Overlays (`z-index` 2147483000, siehe `--crg-lightbox-z`).
- Listen und Links der Galerie und Übersicht sind gegen typische Projektregeln abgesichert (Aufzählungspunkte,
  Einrückung, unterstrichene Links, `* { margin: 0 auto }`), siehe „Robust gegen Projekt-CSS“.
- Bilder werden in passenden Größen (`srcset`) und erst bei Bedarf geladen (`loading="lazy"`).
- Das Lightbox-CSS (`Css/lightbox.css`) wird immer geladen, auch wenn `crgalerie.frontend.css` ausgeschaltet ist.

### Aussehen anpassen

Das mitgelieferte CSS liest CSS-Variablen nur mit Standardwert (`var(--crg-…, Standard)`) und setzt sie nie selbst.
Eigene Werte gewinnen deshalb immer – an `:root` für die ganze Seite oder an `.crg` (Galerie) bzw. `.crg-uebersicht`
(Übersicht), unabhängig davon, ob das Projekt-CSS vor oder nach dem crgalerie-CSS geladen wird:

```css
:root {
    --crg-abstand: 12px;          /* Abstand zwischen Bildern, Standard 8px */
    --crg-radius: 0;              /* Eckenradius, Standard 4px */
    --crg-akzent: #b0002a;        /* Knopf und Fokusrahmen, Standard currentColor */
    --crg-text-gedaempft: #555;   /* Zusatztexte, Standard #666 */
    --crg-zeilenhoehe: 260px;     /* Mosaik in Zeilen ab 600 px, Standard 220px */
    --crg-zeilenhoehe-mobil: 120px; /* unter 600 px, Standard 100px */
}
.crg {
    --crg-spalten: 5;             /* ab 1024 px, Standard 4 (Übersicht 3) */
    --crg-spalten-tablet: 3;      /* 600–1023 px, Standard 3 (Übersicht 2) */
    --crg-spalten-mobil: 1;       /* unter 600 px, Standard 2 (Übersicht 1) */
}
.pswp { --crg-lightbox-hintergrund: #111; }
:root { --crg-lightbox-z: 10000; }  /* Ebene der Großansicht, Standard 2147483000 */
```

`--crg-lightbox-z` setzt PhotoSwipes `--pswp-root-z-index`. Der Standard 2147483000 liegt über üblichen
Cookie-Bannern und Overlays (die oft 99999 bis knapp 2147483647 nutzen) und lässt darüber noch etwas Platz. Soll ein
Element bewusst über der Großansicht liegen, die Variable kleiner setzen oder dem Element einen höheren Wert geben.

Die Spaltenzahl gilt für Raster, Titelbild und Mosaik in Spalten (Galerie) sowie für die Kacheln der Übersicht.
Für Breakpoints gibt es eigene Variablen, ein Wert für `--crg-spalten` wirkt also nur auf dem Desktop.

Die `sizes`-Angaben der Vorschaubilder sind auf die Standardwerte abgestimmt. Auf Handys bekommen Geräte mit DPR 2
scharfe Bilder; Geräte mit DPR 3 zielen auf doppelte statt dreifache Pixeldichte (meist dieselbe 400er-Fassung):

- Mosaik in Zeilen: je Bild aus Seitenverhältnis r und Zeilenhöhe, auf allen Breiten (ViewHelper `crg:zeilenSizes`):
  `(min-width: 600px) min(100vw, r × 308 px), (min-resolution: 2.5dppx) min(100vw, r × 88 px), min(100vw, r × 133 px)`.
  Breite = r × Zeilenhöhe × Reserve fürs Strecken der Zeile: ab 600 px 220 px × 1,4 (gemessen bis ~1,4), darunter
  100 px × 1,33 bzw. × 0,88 ab DPR 2,5. Ein 160 px breites Hochformat auf dem Desktop lädt so die 400er statt der
  800er/1200er-Fassung; Panoramen bekommen bei DPR 2 weiter die großen Fassungen. Chrome wählt bei DPR 2 nach dem
  geometrischen Mittel der Nachbarfassungen und nimmt deshalb gelegentlich die kleinere (bis ~1,17-fach
  hochskaliert); ein Ausgleich über eine höhere Reserve hätte eine Testseite mit 21 Bildern bei DPR 2 rund 70 % schwerer gemacht.
  Die Reserve 1,4 ist ein Erfahrungswert aus Messungen: Ein einzelnes breites Bild in einem schmalen Container kann
  stärker gestreckt werden und wirkt dann etwas weicher.
- Raster/Spalten: `(min-width: 1024px) min(25vw, 360px), (min-width: 600px) 33vw, 34vw`. Vier Spalten in einem
  Inhaltsbereich bis ~1400 px sind höchstens ~340 px breit; die Obergrenze verhindert auf breiten Bildschirmen bei
  DPR 1 die 800er-Fassung. Bei zwei Spalten (Handy) ist ein Bild etwa 45vw breit; 34vw reicht bei DPR 2 bis etwa
  440 px Breite. Zwischen etwa 450 und 599 px (kleine Tablets, Handys quer liegen meist schon darüber) wirkt die
  400er-Fassung bei DPR 2 leicht weich – bewusst in Kauf genommen.
- Titelbild, erstes Bild: `(min-width: 1024px) min(50vw, 720px), 67vw` (Desktop zwei von vier Spalten, Tablet zwei
  von drei Spalten, Handy volle Breite mit Ziel DPR 2).
- Übersicht: `(min-width: 1024px) 33vw, (min-width: 600px) 50vw, 100vw` mit Fassungen 400/800 (unverändert; die
  Kacheln sind auf dem Desktop 300–440 px breit und laden die 800er).

Wer Zeilenhöhen oder Spalten ändert, muss `sizes` in einem eigenen `Partials/Galerie/Bilder.html` mit anpassen, sonst
werden die Bilder unscharf oder unnötig groß: bei `--crg-spalten-mobil: 1` ist `34vw` viel zu klein (Bild ≈ 100vw);
bei eigenen Zeilenhöhen die Werte an den ViewHelper geben, z. B.
`{crg:zeilenSizes(verhaeltnis: quellen.verhaeltnis, zeilenhoehe: 260, zeilenhoeheMobil: 120)}`. Die Argumente nehmen nur
Pixelwerte als Zahl (ohne Einheit); Zeilenhöhen in `em`, `rem` oder `vh` lassen sich so nicht abbilden.

Eigenes CSS statt des mitgelieferten: Site Setting `crgalerie.frontend.css: false`. Das Lightbox-CSS wird trotzdem
geladen. Eigenes CSS muss dann mindestens `.crg__bild[hidden] { display: none; }` enthalten (sonst sind die Bilder
hinter „Alle X Fotos anzeigen“ sofort sichtbar).

### Robust gegen Projekt-CSS

Globale Regeln aus Sitepackages wie `.frame-default ul li { list-style: disc; padding: 4px 0 }`,
`.frame-default ul { padding-left: 25px }`, `a, a span { text-decoration: underline }` oder `* { margin: 0 auto }`
wirken nicht auf Galerie und Übersicht: Listen, Listeneinträge (auch `::before`/`::marker`), Kachel-Links und
Vorschau-Links werden mit doppelter Klasse (Spezifität 0,2,x, ohne `!important`) zurückgesetzt, ebenso der Knopf
„Alle anzeigen“ und der Zurück-Link. Als Hinweis bei Hover und Tastaturfokus wird der Kacheltitel unterstrichen.
Wer diese Eigenschaften bewusst ändern will, braucht ebenfalls mindestens zwei Klassen, z. B.
`.crg-uebersicht .crg-uebersicht__kachel { color: … }`. Regeln mit höherer Spezifität (z. B. drei Klassen) oder
`!important` gewinnen weiterhin.

### Bildfassungen vorab erzeugen

Pro Bild rechnet TYPO3 drei Vorschaugrößen (400/800/1200 px) und eine Lightbox-Fassung (längste Seite 2560 px).
Beim Upload im Modul geschieht das sofort. Für Altbestände (z. B. nach einer Migration oder einem Leeren von
`_processed_`) einmal ausführen, damit der erste Seitenaufruf nicht alle Fassungen auf einmal rechnen muss:

```bash
vendor/bin/typo3 crgalerie:vorwaermen            # alle sichtbaren Bilder
vendor/bin/typo3 crgalerie:vorwaermen --galerie=7 # nur eine Galerie
```

## Texte und Übersetzungen

Alle Texte stehen in `Resources/Private/Language/` – Englisch als Ausgangssprache (`locallang*.xlf`), Deutsch in
`de.locallang*.xlf`. Das Frontend folgt der Sprache der Site (`locale` der Site-Sprache), das Backend-Modul der
Sprache des Redakteurs. Weitere Sprachen: eine `<sprache>.locallang.xlf` mit denselben Schlüsseln anlegen.

Einzelne Texte im Frontend per TypoScript ändern:

```typoscript
plugin.tx_crgalerie._LOCAL_LANG.de {
    frontend.showAll = Alle %d Bilder zeigen
    frontend.photos.many = %d Bilder
}
```

Oder ganze Dateien überschreiben (Frontend und Backend), z. B. in `ext_localconf.php` des Sitepackages:

```php
$GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides']['de']['EXT:crgalerie/Resources/Private/Language/locallang.xlf'][]
    = 'EXT:mein_sitepackage/Resources/Private/Language/Overrides/de.crgalerie.xlf';
```

Die Konsolenausgabe von `crgalerie:vorwaermen` ist englisch.

## Rechte für Redakteure

Das Modul schreibt über den DataHandler und die FAL-API. Checkliste für die Redakteurs-Gruppe. Tabellen-, Feld-,
Datenbank- und Dateirechte entsprechen genau der Fixture `Tests/Functional/Fixtures/ModulRedakteurMitRechten.csv`
und sind in den Functional Tests (Upload, Sortieren, Ausblenden als Redakteur) nachgewiesen; Modulzugriff und
Bildverarbeitung prüfen die Tests nicht, weil sie Controller und Dienste direkt aufrufen. Zusätzliches
Benutzer-/Gruppen-TSconfig (`permissions.file.default.*`) ist nicht nötig.

- [ ] Modulzugriff „Content > Galerien“ (`web_crgalerie`)
- [ ] Tabellen anzeigen und ändern: Galerie (`tx_crgalerie_domain_model_galerie`), Bilder
      (`tx_crgalerie_domain_model_bilder`), Dateireferenz (`sys_file_reference`), Datei (`sys_file`),
      Datei-Metadaten (`sys_file_metadata`)
- [ ] Erlaubte Feldwerte (Feldrechte): Feld „Ausgeblendet“ (`hidden`) bei Galerie und Bilder
- [ ] Datenbank-Freigabe (Mount) auf den Sysordner der Galerien (`storagePid`) mit Recht „Inhalt bearbeiten“
- [ ] Dateifreigabe (File Mount) auf den Upload-Ordner (`uploadFolder`, z. B. `1:/galerien/`) mit den
      Dateirechten Ordner lesen, Ordner anlegen, Datei lesen, Datei hinzufügen, Datei schreiben, Datei löschen
- [ ] Beim Benutzer „Freigaben aus Gruppen übernehmen“ für Datenbank- und Dateifreigaben (Standard)
- [ ] Bildverarbeitung (ImageMagick/GraphicsMagick) im Install Tool aktiv

Galerien auf Ebene 0 (pid 0, Altbestand) können nur Admins bearbeiten; für Redakteure in den Sysordner verschieben.

## Templates überschreiben

```typoscript
plugin.tx_crgalerie.view {
    templateRootPaths.20 = EXT:mein_sitepackage/Resources/Private/Crgalerie/Templates/
    partialRootPaths.20 = EXT:mein_sitepackage/Resources/Private/Crgalerie/Partials/
}
```

Die Vorlage für eine Galerie ist `Partials/Galerie/Bilder.html`, für die Übersicht `Templates/Uebersicht/List.html`.

Variablen: `galerie` (Galerie-Objekt), `bilder` (Liste mit `datei`, `titel`, `beschreibung`, `credit`),
`showDescription` (0–3), `darstellung`, `anzahlStart`, `sizes` (bei Zeilen nur ein Rückfallwert für Querformat 3:2;
das Template nimmt je Bild `crg:zeilenSizes`), `sizesTitel`, `cssEinbinden`, `elementUid`,
`titelEbene` (2–6), in der Übersicht `galerien` (mit `titel`, `erstesBild`, `anzahlBilder`) und `kachelstil`.

Der ViewHelper `<crg:bildQuellen datei="{…}" as="quellen">` (Namespace `Conrat\Crgalerie\ViewHelpers`) liefert
`src`, `srcset`, `breite`, `hoehe`, `grossUrl`, `grossBreite`, `grossHoehe`, `verhaeltnis`. Argumente:
`breiten` (Vorschaubreiten, Standard `{0: 400, 1: 800, 2: 1200}`; `src` ist die mittlere) und `gross` (längste
Seite der Lightbox-Fassung, Standard 2560; `0` = keine eigene Fassung, `grossUrl` ist dann `src` – so in der
Übersicht, die keine Lightbox hat). Abweichende Werte erzeugen eigene Fassungen, die `crgalerie:vorwaermen` und der
Upload nicht vorab rechnen.

Der ViewHelper `{crg:zeilenSizes(verhaeltnis: quellen.verhaeltnis)}` liefert `sizes` für ein Bild im Mosaik in Zeilen
(alle Breakpoints). Optionale Argumente `zeilenhoehe` (Standard 220) und `zeilenhoeheMobil` (Standard 100), nur px als
Zahl – passend
zu `--crg-zeilenhoehe`/`--crg-zeilenhoehe-mobil` setzen, wenn das Projekt sie ändert.

### Markup-Vertrag für eigene Galerie-Templates

`galerie.js` (Lightbox und „Alle anzeigen“) und das CSS verlassen sich auf folgende Haken. Wer `Bilder.html`
überschreibt, sollte sie behalten:

- `<f:render partial="Galerie/Assets" arguments="{cssEinbinden: cssEinbinden}" />` – bindet CSS, PhotoSwipe und
  `galerie.js` ein (`lightbox: 0` lässt Lightbox-CSS und Skript weg, wie in der Übersicht).
- `data-crg-galerie` am Container einer Galerie (eine Lightbox pro Container).
- `.crg__liste` mit `data-crg-anzahl-start="{anzahlStart}"`, darin je Bild ein `.crg__bild`.
- `.crg__titel` im Container: sein Text benennt die Großansicht („Großansicht: …“).
- `data-crg-text-dialog`, `-close`, `-zoom`, `-previous`, `-next`, `-error` am Container: übersetzte Texte der
  Großansicht (aus `Bilder.html` übernehmen; fehlen sie, nimmt `galerie.js` englische Texte).
- `a.crg__link` mit `href="{quellen.grossUrl}"`, `data-pswp-width` und `data-pswp-height` (Maße der großen Fassung);
  `data-cropped="true"`, wenn das Vorschaubild beschnitten ist (Raster, Zeilen, Titelbild).
- `[data-crg-lightboxtext]` (mit `hidden`) in derselben `.crg__figur` – der Inhalt wird als Bildtext in der
  Lightbox angezeigt.
- `[data-crg-mehr]` (mit `hidden`) als Knopf „Alle X Fotos anzeigen“. Das Skript zeigt ihn, sobald es
  `.crg__bild[hidden]` gibt, und hebt beim Klick alle `hidden` auf.
- Bilder nach `anzahlStart` mit dem Attribut `hidden` ausliefern (dann lädt der Browser ihre Vorschaubilder nicht
  vorab) und das `<noscript>`-Stylesheet aus `Bilder.html` (`galerie-noscript.css`) übernehmen, damit sie ohne
  JavaScript sichtbar sind. Kein `<style>`-Element verwenden: Die Content Security Policy von TYPO3 blockiert es.
- Bei eigenem CSS `.crg__bild[hidden] { display: none; }` sicherstellen.

Eigenheit der Darstellung „Mosaik in Spalten“: Die Bilder laufen spaltenweise (erst die erste Spalte von oben nach
unten, dann die zweite …). Lightbox, Tastatur und Screenreader folgen der Reihenfolge im HTML, die von der
sichtbaren Anordnung abweicht.

## Entwicklung

Abhängigkeiten installieren und prüfen (benötigt ImageMagick oder GraphicsMagick):

```bash
composer install
.Build/bin/phpstan analyse -c Build/phpstan.neon
typo3DatabaseDriver=pdo_sqlite .Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml
```

## Lizenz

GPL-2.0-or-later, siehe [LICENSE](LICENSE). Enthält [PhotoSwipe](https://photoswipe.com) 5.4.4 (MIT-Lizenz, siehe
`Resources/Public/Vendor/photoswipe/LICENSE`).

Entwickelt von ConRat WebSolutions GmbH – Produktseite: [somesolutions.de/typo3-bildergalerie](https://somesolutions.de/typo3-bildergalerie/).
