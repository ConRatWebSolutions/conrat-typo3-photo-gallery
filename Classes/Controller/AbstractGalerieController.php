<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Controller;

use Conrat\Crgalerie\Domain\Model\Galerie;
use Conrat\Crgalerie\Domain\Repository\BilderRepository;
use Conrat\Crgalerie\Domain\Repository\GalerieRepository;
use Conrat\Crgalerie\Domain\Sortierung;
use Conrat\Crgalerie\PageTitle\GalerieTitleProvider;
use Conrat\Crgalerie\Service\BildAnzeigeService;
use Conrat\Crgalerie\ViewHelpers\ZeilenSizesViewHelper;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Gemeinsames Rendern einer Galerie für beide Plugins.
 */
abstract class AbstractGalerieController extends ActionController
{
    private const DARSTELLUNGEN = ['zeilen', 'raster', 'spalten', 'titelbild'];
    /*
     * sizes je Darstellung. Auf Handys (unter 600 px) sollen Geräte mit DPR 2 scharfe Vorschaubilder bekommen;
     * Geräte mit DPR 3 zielen auf doppelte statt dreifache Pixeldichte (bei Vorschaubildern kaum sichtbar, aber
     * etwa ein Drittel der Daten).
     * - Zeilen: ZeilenSizesViewHelper berechnet sizes je Bild aus Seitenverhältnis und Zeilenhöhe, weil die Breite
     *   der Zeilenhöhe folgt, nicht der Viewportbreite. Die Variable „sizes“ enthält bei Zeilen nur den Wert für ein
     *   Querformat 3:2 – als Rückfall für überschriebene Templates ohne den ViewHelper.
     * - Raster/Spalten: Desktop vier Spalten; Inhaltsbereiche sind meist höchstens ~1400 px breit, eine Spalte also
     *   höchstens ~340 px → min(25vw, 360px), damit breite Bildschirme bei DPR 1 nicht die 800er laden. Tablet drei
     *   Spalten ≈ 33vw. Handy zwei Spalten ≈ 45vw: 34vw liefert bei 390 px und DPR 3 die 400er-Fassung (≈ 2,2-fach
     *   dicht), bei DPR 2 bis etwa 440 px scharf; zwischen ~450 und 599 px (kleine Tablets, keine Handys im
     *   Hochformat) reicht die 400er bei DPR 2 nicht ganz. Mit --crg-spalten-mobil: 1 ist 34vw zu klein → sizes im
     *   Template überschreiben.
     * - Titelbild: Desktop zwei von vier Spalten → min(50vw, 720px); Tablet zwei von drei Spalten ≈ 67vw; Handy
     *   volle Breite, 67vw × 3 ≈ 800w statt 1200w, DPR 2 bleibt bei 800w.
     */
    private const SIZES_RASTER = '(min-width: 1024px) min(25vw, 360px), (min-width: 600px) 33vw, 34vw';
    private const SIZES_TITEL = '(min-width: 1024px) min(50vw, 720px), 67vw';

    public function __construct(
        protected readonly GalerieRepository $galerieRepository,
        protected readonly BilderRepository $bilderRepository,
        protected readonly BildAnzeigeService $bildAnzeigeService,
        protected readonly GalerieTitleProvider $titleProvider,
        protected readonly MetaTagManagerRegistry $metaTagManagerRegistry,
    ) {}

    protected function weiseGalerieZu(Galerie $galerie): void
    {
        $bilder = $this->bilderRepository->findByGalerie($galerie, Sortierung::ausSetting($this->settings['order'] ?? null));
        $darstellung = $this->darstellung();
        $element = $this->inhaltselement();
        $this->view->assignMultiple([
            'galerie' => $galerie,
            'bilder' => $this->bildAnzeigeService->fuerBilder(
                $bilder,
                $this->siteSetting('crgalerie.metadata.creditField'),
                $this->siteSetting('crgalerie.metadata.licenseField'),
                $galerie->getFotograf(),
            ),
            'showDescription' => (int)($this->settings['showDescription'] ?? 1),
            'darstellung' => $darstellung,
            'anzahlStart' => $this->anzahlStart(),
            'sizes' => $darstellung === 'zeilen' ? ZeilenSizesViewHelper::sizes(1.5) : self::SIZES_RASTER,
            'sizesTitel' => self::SIZES_TITEL,
            'cssEinbinden' => $this->cssEinbinden(),
            // eindeutige ids, auch wenn dieselbe Galerie mehrfach auf einer Seite steht
            'elementUid' => (int)($element['uid'] ?? 0) ?: (int)$galerie->getUid(),
            'titelEbene' => $this->titelEbene($element),
        ]);
        $this->setzeSeitentitel($galerie);
    }

    /**
     * @return array<string, mixed>
     */
    private function inhaltselement(): array
    {
        $daten = $this->request->getAttribute('currentContentObject')?->data;
        return is_array($daten) ? $daten : [];
    }

    /**
     * Hat das Inhaltselement eine sichtbare eigene Überschrift, ist der Galerietitel eine Ebene tiefer
     * (header_layout 0 = Standard h2 → h3, 1 → h2, 3 → h4 …, höchstens h6), sonst h2.
     *
     * @param array<string, mixed> $element
     */
    private function titelEbene(array $element): int
    {
        $layout = (int)($element['header_layout'] ?? 0);
        if (trim((string)($element['header'] ?? '')) === '' || $layout === 100) {
            return 2;
        }
        $elementEbene = $layout >= 1 && $layout <= 6 ? $layout : 2;
        return min(6, $elementEbene + 1);
    }

    protected function darstellung(): string
    {
        $wert = (string)($this->settings['darstellung'] ?? '');
        return in_array($wert, self::DARSTELLUNGEN, true) ? $wert : 'zeilen';
    }

    protected function anzahlStart(): int
    {
        $wert = $this->settings['anzahlStart'] ?? '';
        return is_numeric($wert) && (int)$wert >= 0 ? (int)$wert : 12;
    }

    protected function cssEinbinden(): bool
    {
        $site = $this->request->getAttribute('site');
        if (!$site instanceof Site) {
            return true;
        }
        return (bool)$site->getSettings()->get('crgalerie.frontend.css', true);
    }

    protected function markiereCache(): void
    {
        // 'crgalerie' leert das Backend-Modul nach Änderungen. Die Tabellen-Tags leert der DataHandler,
        // wenn Galerien, Bilder oder Bild-Metadaten über die Bearbeitungsmaske (FormEngine) geändert werden.
        $this->request->getAttribute('frontend.cache.collector')?->addCacheTags(
            new CacheTag('crgalerie'),
            new CacheTag('tx_crgalerie_domain_model_galerie'),
            new CacheTag('tx_crgalerie_domain_model_bilder'),
            new CacheTag('sys_file_metadata'),
        );
    }

    private function setzeSeitentitel(Galerie $galerie): void
    {
        $muster = $this->siteSetting('crgalerie.pageTitle');
        if ($muster === '') {
            return;
        }
        $titel = str_replace('{galerie}', $galerie->getTitel(), $muster);
        $this->titleProvider->setTitle($titel);
        $this->metaTagManagerRegistry->getManagerForProperty('og:title')->addProperty('og:title', $titel);
    }

    private function siteSetting(string $schluessel): string
    {
        $site = $this->request->getAttribute('site');
        if (!$site instanceof Site) {
            return '';
        }
        $wert = $site->getSettings()->get($schluessel, '');
        return is_string($wert) ? trim($wert) : '';
    }
}
