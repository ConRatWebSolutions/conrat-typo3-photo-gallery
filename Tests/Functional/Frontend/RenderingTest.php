<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class RenderingTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content', 'filemetadata'];
    protected array $testExtensionsToLoad = [
        'conrat/crgalerie',
        'typo3conf/ext/crgalerie/Tests/Functional/Fixtures/Extensions/crgalerie_test',
    ];
    protected array $pathsToProvideInTestInstance = [
        'typo3conf/ext/crgalerie/Tests/Functional/Fixtures/Files/' => 'fileadmin/galerie/',
    ];
    protected array $configurationToUseInTestInstance = [
        'GFX' => ['processor_enabled' => false],
        // Seiten-Cache in der Datenbank statt NullBackend, damit Cache-Tags prüfbar sind
        'SYS' => ['caching' => ['cacheConfigurations' => ['pages' => ['backend' => Typo3DatabaseBackend::class]]]],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Galerien.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Seiten.csv');
        $this->get(SiteWriter::class)->write('main', [
            'rootPageId' => 1,
            'base' => 'https://website.local/',
            'languages' => [[
                'languageId' => 0,
                'title' => 'Deutsch',
                'locale' => 'de_DE.UTF-8',
                'base' => '/',
            ]],
            'dependencies' => ['conrat/crgalerie-test-seite'],
        ]);
    }

    private function seite(string $pfad): string
    {
        $antwort = $this->executeFrontendSubRequest(new InternalRequest('https://website.local' . $pfad));
        self::assertSame(200, $antwort->getStatusCode(), (string)$antwort->getBody());
        return (string)$antwort->getBody();
    }

    private function statusMitGueltigemCHash(int $seite, string $galerie): int
    {
        $parameter = [
            'id' => $seite,
            'tx_crgalerie_uebersicht' => ['action' => 'show', 'controller' => 'Uebersicht', 'galerie' => $galerie],
        ];
        $parameter['cHash'] = $this->get(CacheHashCalculator::class)
            ->generateForParameters(HttpUtility::buildQueryString($parameter));
        // withQueryParameters() kann keine verschachtelten Plugin-Parameter, daher fertige Query
        $request = new InternalRequest('https://website.local/?' . HttpUtility::buildQueryString($parameter));
        return $this->executeFrontendSubRequest($request)->getStatusCode();
    }

    #[Test]
    public function galerieZeigtSichtbareBilderMitTextenInReihenfolge(): void
    {
        $html = $this->seite('/galerie');
        self::assertSame(3, substr_count($html, 'class="crg__bild"'));
        self::assertMatchesRegularExpression('/Burgtor.*Beta.*Zelt/s', $html);
        self::assertStringContainsString('Aufbau am Morgen', $html);
        self::assertStringContainsString('© Max Muster', $html);
        self::assertStringNotContainsString('Versteckt</', $html);
    }

    #[Test]
    public function versteckteGalerieAlsFesteAuswahlRendertLeerOhneFehler(): void
    {
        $html = $this->seite('/versteckt');
        self::assertStringNotContainsString('crg__bild', $html);
    }

    #[Test]
    public function uebersichtVerlinktGalerieUndEinzelansichtFunktioniert(): void
    {
        $html = $this->seite('/uebersicht');
        self::assertStringContainsString('Werratal', $html);
        self::assertStringContainsString('Sommerfest', $html);
        self::assertStringNotContainsString('Leer', $html);
        // Fehlende Dateien zählen nicht; kaputter Verweis vor dem ersten Bild bricht nichts ab
        self::assertStringNotContainsString('Nur fehlende Dateien', $html);
        self::assertMatchesRegularExpression('#Werratal</span><span class="crg-uebersicht__trenner">, </span>\s*<span class="crg-uebersicht__meta">\s*<span class="crg-uebersicht__anzahl">1 Foto</span>#', $html);
        self::assertMatchesRegularExpression('#Sommerfest</span><span class="crg-uebersicht__trenner">, </span>\s*<span class="crg-uebersicht__meta">\s*<span class="crg-uebersicht__anzahl">3 Fotos</span>#', $html);
        self::assertSame(1, preg_match('/<a [^>]*href="([^"]+galerie%5D=1[^"]*)"/', $html, $treffer));

        $einzel = $this->seite(html_entity_decode($treffer[1]));
        self::assertSame(3, substr_count($einzel, 'class="crg__bild"'));
        self::assertStringContainsString('<span aria-hidden="true">←</span> Alle Galerien', $einzel);
        // Die Einzelansicht braucht die Lightbox, die Kachelliste nicht
        self::assertStringContainsString('JavaScript/galerie.js', $einzel);
        self::assertStringNotContainsString('JavaScript/galerie.js', $html);
    }

    #[Test]
    public function einzelansichtLiefert404FuerUngueltigeVersteckteOderFremdeGalerie(): void
    {
        self::assertSame(200, $this->statusMitGueltigemCHash(3, '1'));
        self::assertSame(404, $this->statusMitGueltigemCHash(3, '999'));
        self::assertSame(404, $this->statusMitGueltigemCHash(3, '3'));
        self::assertSame(404, $this->statusMitGueltigemCHash(3, 'abc'));
        self::assertSame(404, $this->statusMitGueltigemCHash(3, '1 OR 1=1'));
        // Übersicht „Landschaft“ (Kategorie 2) darf Galerie 1 (Kategorie 1) nicht zeigen
        self::assertSame(404, $this->statusMitGueltigemCHash(4, '1'));
    }

    #[Test]
    public function seitenCacheTraegtTagsFuerModulUndBearbeitungsmaske(): void
    {
        $this->seite('/galerie');
        $backend = $this->get(CacheManager::class)->getCache('pages')->getBackend();
        self::assertInstanceOf(TaggableBackendInterface::class, $backend);
        foreach (['crgalerie', 'tx_crgalerie_domain_model_galerie', 'tx_crgalerie_domain_model_bilder', 'sys_file_metadata'] as $tag) {
            self::assertNotEmpty($backend->findIdentifiersByTag($tag), $tag);
        }
    }

    #[Test]
    public function seitentitelNurMitSetting(): void
    {
        self::assertStringNotContainsString('Bildergalerie', $this->seite('/galerie'));

        $this->get(SiteWriter::class)->writeSettings('main', [
            'crgalerie' => ['pageTitle' => '{galerie} – Bildergalerie'],
        ]);
        // Erste Antwort liegt sonst im Seiten-Cache
        $this->get(CacheManager::class)->flushCaches();
        self::assertMatchesRegularExpression('#<title>[^<]*Sommerfest – Bildergalerie#', $this->seite('/galerie'));
    }
}
