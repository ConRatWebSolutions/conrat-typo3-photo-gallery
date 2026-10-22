<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class DarstellungTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content', 'filemetadata'];
    protected array $testExtensionsToLoad = [
        'conratwebsolutions/crgalerie',
        'typo3conf/ext/crgalerie/Tests/Functional/Fixtures/Extensions/crgalerie_test',
    ];
    protected array $pathsToProvideInTestInstance = [
        'typo3conf/ext/crgalerie/Tests/Functional/Fixtures/Files/' => 'fileadmin/galerie/',
    ];
    protected array $configurationToUseInTestInstance = [
        'GFX' => ['processor_enabled' => false],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Galerien.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Seiten.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SeitenDarstellung.csv');
        $this->get(SiteWriter::class)->write('main', [
            'rootPageId' => 1,
            'base' => 'https://website.local/',
            'languages' => [['languageId' => 0, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF-8', 'base' => '/']],
            'dependencies' => ['conrat/crgalerie-test-seite'],
        ]);
        // settings.yaml überlebt zwischen Tests derselben Testinstanz (cssWirdEingebunden… schreibt sie),
        // die Site-Settings liegen zudem im Datei-Cache
        $this->get(SiteWriter::class)->writeSettings('main', []);
        $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
    }

    private function seite(string $pfad): string
    {
        $antwort = $this->executeFrontendSubRequest(new InternalRequest('https://website.local' . $pfad));
        self::assertSame(200, $antwort->getStatusCode(), (string)$antwort->getBody());
        return (string)$antwort->getBody();
    }

    #[Test]
    public function standardIstZeilenMitZwoelfUndZeilenSizes(): void
    {
        $html = $this->seite('/galerie');
        self::assertStringContainsString('class="crg crg--zeilen"', $html);
        self::assertStringContainsString('data-crg-anzahl-start="12"', $html);
        // alle Breakpoints je Bild aus dem Seitenverhältnis (Querformat 60×40 → 1,5), keine vw-Anteile mehr
        self::assertStringContainsString(
            'sizes="(min-width: 600px) min(100vw, 462px), (min-resolution: 2.5dppx) min(100vw, 132px), min(100vw, 200px)"',
            $html
        );
        self::assertStringNotContainsString('33vw', $html);
        self::assertStringNotContainsString('50vw', $html);
    }

    #[Test]
    public function rasterMitAnzahlZweiZeigtMehrButton(): void
    {
        $html = $this->seite('/raster');
        self::assertStringContainsString('class="crg crg--raster"', $html);
        self::assertStringContainsString('data-crg-anzahl-start="2"', $html);
        self::assertMatchesRegularExpression('/<button[^>]*data-crg-mehr[^>]*hidden[^>]*>\s*Alle 3 Fotos anzeigen/s', $html);
        // der Knopf verschwindet nach dem Klick, ein aria-expanded hätte nichts mehr zu melden
        self::assertStringNotContainsString('aria-expanded', $html);
        self::assertStringContainsString('sizes="(min-width: 1024px) min(25vw, 360px), (min-width: 600px) 33vw, 34vw"', $html);
    }

    #[Test]
    public function spaltenMitNullZeigtKeinenButton(): void
    {
        $html = $this->seite('/spalten');
        self::assertStringContainsString('class="crg crg--spalten"', $html);
        self::assertStringContainsString('data-crg-anzahl-start="0"', $html);
        self::assertStringNotContainsString('data-crg-mehr', $html);
        self::assertStringContainsString('sizes="(min-width: 1024px) min(25vw, 360px), (min-width: 600px) 33vw, 34vw"', $html);
    }

    #[Test]
    public function titelbildHatEigeneSizesFuerErstesBild(): void
    {
        $html = $this->seite('/titelbild');
        self::assertStringContainsString('class="crg crg--titelbild"', $html);
        self::assertSame(1, substr_count($html, 'sizes="(min-width: 1024px) min(50vw, 720px), 67vw"'));
        self::assertStringContainsString('sizes="(min-width: 1024px) min(25vw, 360px), (min-width: 600px) 33vw, 34vw"', $html);
    }

    #[Test]
    public function ungueltigeWerteErgebenStandard(): void
    {
        $html = $this->seite('/ungueltig');
        self::assertStringContainsString('class="crg crg--zeilen"', $html);
        self::assertStringContainsString('data-crg-anzahl-start="12"', $html);
    }

    #[Test]
    public function bildMarkupHatSrcsetLazyLightboxUndVerhaeltnis(): void
    {
        $html = $this->seite('/galerie');
        self::assertSame(3, substr_count($html, 'class="crg__bild"'));
        self::assertMatchesRegularExpression('/<img[^>]+srcset="[^"]+\d+w"[^>]+loading="lazy"/', $html);
        self::assertMatchesRegularExpression('/data-pswp-width="[1-9]\d*" data-pswp-height="[1-9]\d*"/', $html);
        self::assertMatchesRegularExpression('/--crg-verhaeltnis: \d\.\d{4}/', $html);
        self::assertStringContainsString('data-crg-lightboxtext', $html);
        self::assertStringContainsString('3 Fotos', $html);
    }

    #[Test]
    public function cssWirdEingebundenUndLaesstSichAbschalten(): void
    {
        self::assertStringContainsString('crgalerie/Resources/Public/Css/galerie.css', $this->seite('/galerie'));
        $this->get(SiteWriter::class)->writeSettings('main', ['crgalerie' => ['frontend' => ['css' => false]]]);
        $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
        self::assertStringNotContainsString('Public/Css/galerie.css', $this->seite('/galerie'));
    }

    #[Test]
    public function fotoanzahlLaesstSichImElementAusblenden(): void
    {
        $html = $this->seite('/ohne-anzahl');
        self::assertStringNotContainsString('3 Fotos', $html);
        // ohne Datum und Fotograf bleibt keine leere Meta-Zeile stehen
        self::assertStringNotContainsString('crg__meta', $html);
    }

    #[Test]
    public function fotoanzahlFolgtSiteEinstellungUndElementUeberschreibtSie(): void
    {
        $this->get(SiteWriter::class)->writeSettings('main', ['crgalerie' => ['frontend' => ['photoCount' => false]]]);
        $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
        self::assertStringNotContainsString('3 Fotos', $this->seite('/galerie'));
        self::assertStringContainsString('<span class="crg__anzahl">3 Fotos</span>', $this->seite('/mit-anzahl'));
    }

    #[Test]
    public function seiteOhneGalerieBindetNichtsEin(): void
    {
        self::assertStringNotContainsString('crgalerie/Resources/Public', $this->seite('/'));
    }

    #[Test]
    public function zweiGalerienAufEinerSeiteHabenEigeneBereiche(): void
    {
        $html = $this->seite('/zwei');
        self::assertSame(2, substr_count($html, 'data-crg-galerie'));
        self::assertSame(1, substr_count($html, 'Public/Css/galerie.css'));
        self::assertStringContainsString('1 Foto<', $html);
    }

    #[Test]
    public function idsSindProInhaltselementEindeutig(): void
    {
        $html = $this->seite('/doppelt');
        self::assertSame(1, substr_count($html, 'id="crg-titel-17"'));
        self::assertSame(1, substr_count($html, 'id="crg-titel-18"'));
        self::assertSame(1, substr_count($html, 'aria-labelledby="crg-titel-17"'));
        self::assertSame(1, substr_count($html, 'aria-labelledby="crg-titel-18"'));
    }

    #[Test]
    public function titelIstH3WennElementEigeneSichtbareUeberschriftHat(): void
    {
        $html = $this->seite('/doppelt');
        self::assertMatchesRegularExpression('#<h3 class="crg__titel" id="crg-titel-17">Sommerfest</h3>#', $html);
        // header_layout 100 = verborgen → Galerietitel bleibt h2
        self::assertMatchesRegularExpression('#<h2 class="crg__titel" id="crg-titel-18">Sommerfest</h2>#', $html);
    }

    #[Test]
    public function titelIstH2OhneElementUeberschrift(): void
    {
        $html = $this->seite('/zwei');
        self::assertMatchesRegularExpression('#<h2 class="crg__titel" id="crg-titel-15">Sommerfest</h2>#', $html);
        self::assertMatchesRegularExpression('#<h2 class="crg__titel" id="crg-titel-16">Werratal</h2>#', $html);
    }

    #[Test]
    public function uebersichtStandardKachelstilUnten(): void
    {
        $html = $this->seite('/uebersicht');
        self::assertStringContainsString('class="crg-uebersicht crg-uebersicht--unten"', $html);
        self::assertMatchesRegularExpression('/<img[^>]+class="crg-uebersicht__bild"[^>]+srcset="[^"]+"[^>]+loading="lazy"/', $html);
        self::assertStringContainsString('crgalerie/Resources/Public/Css/galerie.css', $html);
        self::assertStringContainsString('3 Fotos', $html);
        self::assertStringContainsString('1 Foto<', $html);
    }

    #[Test]
    public function uebersichtKachelstilUeber(): void
    {
        self::assertStringContainsString('class="crg-uebersicht crg-uebersicht--ueber"', $this->seite('/uebersicht-ueber'));
    }

    #[Test]
    public function lightboxSkriptUndLightboxCssSindImmerEingebunden(): void
    {
        $html = $this->seite('/galerie');
        self::assertMatchesRegularExpression('/<script[^>]+type="module"[^>]+galerie\.js|<script[^>]+galerie\.js[^>]+type="module"/', $html);
        self::assertStringContainsString('Vendor/photoswipe/photoswipe.css', $html);
        self::assertStringContainsString('Public/Css/lightbox.css', $html);
        $this->get(SiteWriter::class)->writeSettings('main', ['crgalerie' => ['frontend' => ['css' => false]]]);
        $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
        $html = $this->seite('/galerie');
        self::assertStringContainsString('Vendor/photoswipe/photoswipe.css', $html);
        self::assertStringContainsString('Public/Css/lightbox.css', $html);
        self::assertStringNotContainsString('Public/Css/galerie.css', $html);
    }

    #[Test]
    public function bilderNachAnzahlStartSindServerseitigVersteckt(): void
    {
        $html = $this->seite('/raster');
        preg_match_all('/<li class="crg__bild"[^>]*>/', $html, $treffer);
        self::assertCount(3, $treffer[0]);
        self::assertStringNotContainsString(' hidden', $treffer[0][0]);
        self::assertStringNotContainsString(' hidden', $treffer[0][1]);
        self::assertStringContainsString(' hidden', $treffer[0][2]);
        // ohne JavaScript bleiben alle Bilder sichtbar – per externem Stylesheet, weil die CSP <style> blockiert
        self::assertMatchesRegularExpression('#<noscript><link rel="stylesheet" href="[^"]*/Resources/Public/Css/galerie-noscript\.css[^"]*" /></noscript>#', $html);
        self::assertStringNotContainsString('<style', $html);
        $css = (string)file_get_contents(dirname(__DIR__, 3) . '/Resources/Public/Css/galerie-noscript.css');
        self::assertMatchesRegularExpression('#\.crg__bild\[hidden\]\s*\{\s*display:\s*list-item\s*!important#', $css);
    }

    #[Test]
    public function ohneAnzahlStartOderBeiWenigenBildernIstNichtsVersteckt(): void
    {
        foreach (['/spalten', '/galerie'] as $pfad) {
            $html = $this->seite($pfad);
            self::assertDoesNotMatchRegularExpression('/<li class="crg__bild"[^>]* hidden/', $html, $pfad);
            self::assertStringNotContainsString('<noscript>', $html, $pfad);
        }
    }

    #[Test]
    public function beschnitteneDarstellungenMeldenDasPhotoSwipe(): void
    {
        self::assertSame(3, substr_count($this->seite('/raster'), 'data-cropped="true"'));
        self::assertSame(3, substr_count($this->seite('/titelbild'), 'data-cropped="true"'));
        self::assertSame(3, substr_count($this->seite('/galerie'), 'data-cropped="true"'));
        self::assertStringNotContainsString('data-cropped', $this->seite('/spalten'));
    }
    #[Test]
    public function titelEbeneFolgtDerElementueberschrift(): void
    {
        $html = $this->seite('/ebenen');
        // header_layout 1 → Elementüberschrift h1 → Galerietitel h2; 3 → h4; 5 → h6
        self::assertMatchesRegularExpression('#<h2 class="crg__titel" id="crg-titel-20">Sommerfest</h2>#', $html);
        self::assertMatchesRegularExpression('#<h4 class="crg__titel" id="crg-titel-21">Sommerfest</h4>#', $html);
        self::assertMatchesRegularExpression('#<h6 class="crg__titel" id="crg-titel-22">Sommerfest</h6>#', $html);
    }

    private function aendereBild(int $uid, string $titel, string $unterschrift): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_crgalerie_domain_model_bilder')
            ->update('tx_crgalerie_domain_model_bilder', ['titel' => $titel, 'bildunterschrift' => $unterschrift], ['uid' => $uid]);
    }

    #[Test]
    public function altTextFaelltVonTitelAufUnterschriftUndPositionZurueck(): void
    {
        // Reihenfolge auf /galerie: Bild 2 (Metadatentitel „Burgtor“), Bild 3, Bild 1
        $this->aendereBild(3, '', 'Nur Unterschrift');
        $this->aendereBild(1, '', '');
        $html = $this->seite('/galerie');
        preg_match_all('/<img [^>]*alt="([^"]*)"/', $html, $treffer);
        self::assertSame(['Burgtor', 'Nur Unterschrift', 'Foto 3 von 3 – Sommerfest'], $treffer[1]);
    }

    #[Test]
    public function htmlInTitelUndUnterschriftWirdEscaped(): void
    {
        $this->aendereBild(3, '<b>Burg & Tor</b>', '<i>Unter & Schrift</i>');
        $html = $this->seite('/galerie');
        self::assertStringContainsString('alt="&lt;b&gt;Burg &amp; Tor&lt;/b&gt;"', $html);
        self::assertStringContainsString('<span class="crg__bildtitel">&lt;b&gt;Burg &amp; Tor&lt;/b&gt;</span>', $html);
        self::assertStringContainsString('<span class="crg__unterschrift">&lt;i&gt;Unter &amp; Schrift&lt;/i&gt;</span>', $html);
        self::assertStringNotContainsString('<b>Burg', $html);
        self::assertStringNotContainsString('<i>Unter', $html);
        self::assertStringNotContainsString('&amp;amp;', $html);
    }

    #[Test]
    public function englischeSiteZeigtEnglischeTexte(): void
    {
        $this->get(SiteWriter::class)->write('main', [
            'rootPageId' => 1,
            'base' => 'https://website.local/',
            'languages' => [['languageId' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8', 'base' => '/']],
            'dependencies' => ['conrat/crgalerie-test-seite'],
        ]);
        $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
        $this->aendereBild(1, '', '');
        $html = $this->seite('/galerie');
        self::assertStringContainsString('3 photos', $html);
        self::assertStringContainsString('alt="Photo 3 of 3 – Sommerfest"', $html);
        self::assertStringContainsString('data-crg-text-dialog="Image viewer"', $html);
        self::assertStringNotContainsString('Fotos', $html);
    }

    #[Test]
    public function galerietitelImUebersetztenAltTextWirdGenauEinmalEscaped(): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_crgalerie_domain_model_galerie')
            ->update('tx_crgalerie_domain_model_galerie', ['titel' => 'Hof & "Tor" <Fest>'], ['uid' => 1]);
        $this->aendereBild(1, '', '');
        $html = $this->seite('/galerie');
        self::assertStringContainsString('alt="Foto 3 von 3 – Hof &amp; &quot;Tor&quot; &lt;Fest&gt;"', $html);
        self::assertStringNotContainsString('&amp;amp;', $html);
        self::assertStringNotContainsString('<Fest>', $html);
    }

    #[Test]
    public function uebersichtLaedtKeineLightbox(): void
    {
        $html = $this->seite('/uebersicht');
        self::assertStringContainsString('Public/Css/galerie.css', $html);
        self::assertStringNotContainsString('photoswipe', $html);
        self::assertStringNotContainsString('Public/Css/lightbox.css', $html);
        self::assertStringNotContainsString('JavaScript/galerie.js', $html);
    }
}
