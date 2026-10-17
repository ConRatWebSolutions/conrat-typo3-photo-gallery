<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Controller\Backend;

use Conrat\Crgalerie\Configuration\ExtensionKonfiguration;
use Conrat\Crgalerie\Service\GalerieBearbeitung;
use Conrat\Crgalerie\Service\GalerieBearbeitungFehler;
use Conrat\Crgalerie\Service\LoeschErgebnis;
use Conrat\Crgalerie\Service\ModulAbfragen;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Backend-Modul „Galerie“: rendert Übersicht, Galerie und Upload-Seite.
 * Schreibvorgänge laufen über GalerieBearbeitung (DataHandler).
 */
class AdminController extends ActionController
{
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly ExtensionKonfiguration $extensionKonfiguration,
        protected readonly ComponentFactory $componentFactory,
        protected readonly ModulAbfragen $abfragen,
        protected readonly GalerieBearbeitung $bearbeitung,
        protected readonly IconFactory $iconFactory,
        protected readonly PageRenderer $pageRenderer,
        protected readonly BackendUriBuilder $backendUriBuilder,
        protected readonly SiteFinder $siteFinder,
    ) {}

    public function indexAction(): ResponseInterface
    {
        $storagePid = $this->extensionKonfiguration->getStoragePid();
        if ($storagePid === 0) {
            $this->addFlashMessage($this->text('message.noStoragePid'), $this->text('message.title.error'), ContextualFeedbackSeverity::ERROR);
        }
        $galId = $this->intArgument('galerie');
        $this->pageRenderer->addCssFile('EXT:crgalerie/Resources/Public/Css/module.css');
        // Als ES-Modul mit Zeitstempel laden (nicht über die Importmap): deren ?bust= hängt an der
        // Extension-Version und bliebe nach einem Deploy gleich.
        $this->pageRenderer->addJsFooterFile('EXT:crgalerie/Resources/Public/JavaScript/module.js', 'module', false, false, '', true);

        $moduleTemplate = $this->modulVorlage();
        $returnUrl = (string)$this->uriBuilder->reset()->uriFor('index', $galId > 0 ? ['galerie' => $galId] : []);

        if ($galId > 0) {
            $galerie = $this->abfragen->galerie($galId);
            if ($galerie === null) {
                return $this->redirect('index');
            }
            $moduleTemplate->setTitle($this->text('module.gallery.eyebrow'), $galerie['titel']);
            $this->zurueckButton($moduleTemplate, (string)$this->uriBuilder->reset()->uriFor('index'), $this->text('module.back.allGalleries'));
            $moduleTemplate->assignMultiple([
                'galerie' => $galerie,
                'vorschauUri' => $this->vorschauUri($galId),
                'bilder' => $this->abfragen->bilder($galId),
                'returnUrl' => $returnUrl,
                'storagePid' => $storagePid,
            ]);
            return $moduleTemplate->renderResponse('Admin/Gallery');
        }

        $galerien = $this->abfragen->galerienUebersicht();
        foreach ($galerien as $index => $galerie) {
            $galerien[$index]['vorschau'] = $this->vorschauUri($galerie['uid']);
        }
        $moduleTemplate->setTitle($this->text('module.overview.eyebrow'));
        $moduleTemplate->assignMultiple([
            'galerien' => $galerien,
            'anzahlSichtbar' => count(array_filter($galerien, static fn(array $g): bool => $g['hidden'] === 0)),
            'returnUrl' => $returnUrl,
            'storagePid' => $storagePid,
        ]);
        return $moduleTemplate->renderResponse('Admin/Index');
    }

    public function uploadAction(): ResponseInterface
    {
        $galId = $this->intArgument('gal');
        $galerie = $this->abfragen->galerie($galId);
        if ($galerie === null) {
            return $this->redirect('index');
        }
        $galerieUri = (string)$this->uriBuilder->reset()->uriFor('index', ['galerie' => $galId]);
        $this->pageRenderer->addCssFile('EXT:crgalerie/Resources/Public/Css/module.css');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:crgalerie/Resources/Private/Language/locallang.xlf', 'upload.');
        $this->pageRenderer->addJsFooterFile('EXT:crgalerie/Resources/Public/JavaScript/upload.js', 'module', false, false, '', true);

        $moduleTemplate = $this->modulVorlage();
        $moduleTemplate->setTitle($this->text('module.uploadImages'));
        $this->zurueckButton($moduleTemplate, $galerieUri, $this->text('module.back.gallery'));
        $moduleTemplate->assignMultiple([
            'gal' => $galId,
            'galerie' => $galerie,
            'bildAnzahl' => count($this->abfragen->bilder($galId)),
            'uploadUri' => (string)$this->backendUriBuilder->buildUriFromRoute('ajax_crgalerie_upload'),
            'maxMb' => (int)floor(GeneralUtility::getMaxUploadFileSize() / 1024),
            'galleryUri' => $galerieUri,
        ]);
        return $moduleTemplate->renderResponse('Admin/Upload');
    }

    public function sortGalleriesAction(): ResponseInterface
    {
        $ids = $this->uidListe('sorting');
        if ($ids !== [] && $this->istPost()) {
            $this->ausfuehren(
                fn() => $this->bearbeitung->galerienSortieren($ids),
                'message.galleriesSorted'
            );
        }
        return $this->redirect('index');
    }

    public function sortImagesAction(): ResponseInterface
    {
        $galId = $this->intArgument('gal');
        $ids = $this->uidListe('sorting');
        if ($galId > 0 && $ids !== [] && $this->istPost()) {
            $this->ausfuehren(
                fn() => $this->bearbeitung->bilderSortieren($galId, $ids),
                'message.imagesSorted'
            );
        }
        return $this->redirect('index', null, null, ['galerie' => $galId]);
    }

    public function toggleGalleryHiddenAction(): ResponseInterface
    {
        $galId = $this->intArgument('gal');
        $ausblenden = $this->intArgument('hidden') === 1;
        if ($galId > 0 && $this->istPost()) {
            $this->ausfuehren(
                fn() => $this->bearbeitung->galerieSichtbarkeit($galId, !$ausblenden),
                $ausblenden ? 'message.galleryHidden' : 'message.galleryVisible'
            );
        }
        $zurueck = $this->request->hasArgument('back') && (string)$this->request->getArgument('back') === 'galerie';
        return $this->redirect('index', null, null, $zurueck ? ['galerie' => $galId] : []);
    }

    public function toggleImageHiddenAction(): ResponseInterface
    {
        $galId = $this->intArgument('gal');
        $bildUid = $this->intArgument('img');
        $aktion = $this->request->hasArgument('do') ? (string)$this->request->getArgument('do') : '';
        if ($bildUid > 0 && ($aktion === 'hide' || $aktion === 'show') && $this->istPost()) {
            $this->ausfuehren(
                fn() => $this->bearbeitung->bildSichtbarkeit($bildUid, $aktion === 'show'),
                $aktion === 'show' ? 'message.imageVisible' : 'message.imageHidden'
            );
        }
        return $this->redirect('index', null, null, ['galerie' => $galId]);
    }

    public function deleteImageAction(): ResponseInterface
    {
        $galId = $this->intArgument('gal');
        $bildUid = $this->intArgument('image');
        if ($bildUid > 0 && $this->istPost()) {
            try {
                $ergebnis = $this->bearbeitung->bildLoeschen($bildUid);
                [$schluessel, $schwere] = match ($ergebnis) {
                    LoeschErgebnis::DateiGeloescht => ['message.deleted.withFile', ContextualFeedbackSeverity::OK],
                    LoeschErgebnis::KeineDatei => ['message.deleted.noFile', ContextualFeedbackSeverity::OK],
                    LoeschErgebnis::DateiWirdVerwendet => ['message.deleted.fileInUse', ContextualFeedbackSeverity::OK],
                    LoeschErgebnis::DateiNichtGeloescht => ['message.deleted.fileNotDeleted', ContextualFeedbackSeverity::WARNING],
                };
                $this->addFlashMessage($this->text($schluessel), $this->text('message.title.deleted'), $schwere);
            } catch (GalerieBearbeitungFehler $fehler) {
                $this->addFlashMessage($fehler->getMessage(), $this->text('message.title.notDeleted'), ContextualFeedbackSeverity::ERROR);
            }
        }
        return $this->redirect('index', null, null, ['galerie' => $galId]);
    }

    /**
     * @param string $erfolg Sprachschlüssel der Erfolgsmeldung
     */
    private function ausfuehren(callable $aenderung, string $erfolg): void
    {
        try {
            $aenderung();
            $this->addFlashMessage($this->text($erfolg), $this->text('message.title.saved'), ContextualFeedbackSeverity::OK);
        } catch (GalerieBearbeitungFehler $fehler) {
            // Die Meldung stammt vom DataHandler (Core-Text in der Sprache des Benutzers)
            $this->addFlashMessage($fehler->getMessage(), $this->text('message.title.notSaved'), ContextualFeedbackSeverity::ERROR);
        }
    }

    /**
     * Text aus Resources/Private/Language/locallang.xlf in der Sprache des Backend-Benutzers
     */
    private function text(string $schluessel, int|string ...$werte): string
    {
        return LocalizationUtility::translate($schluessel, 'Crgalerie', $werte !== [] ? $werte : null, null, $this->request) ?? $schluessel;
    }

    private function modulVorlage(): ModuleTemplate
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
        return $moduleTemplate;
    }

    private function zurueckButton(ModuleTemplate $moduleTemplate, string $ziel, string $titel): void
    {
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $buttonBar->addButton(
            $this->componentFactory->createLinkButton()
                ->setHref($ziel)
                ->setTitle($titel)
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-view-go-back', IconSize::SMALL))
        );
    }

    /**
     * Änderungen nur per POST (Formulare im Modul). Ein aufgerufener Link – etwa aus dem Browserverlauf oder per
     * Prefetch – ändert nichts, sondern führt nur zur Ansicht zurück.
     */
    private function istPost(): bool
    {
        return $this->request->getMethod() === 'POST';
    }

    private function intArgument(string $name): int
    {
        return $this->request->hasArgument($name) ? (int)$this->request->getArgument($name) : 0;
    }

    /**
     * @return list<int>
     */
    private function uidListe(string $name): array
    {
        $wert = $this->request->hasArgument($name) ? (string)$this->request->getArgument($name) : '';
        return array_values(array_filter(array_map('intval', explode(',', $wert)), static fn(int $uid): bool => $uid > 0));
    }

    /**
     * Frontend-Link auf eine Galerie (inkl. cHash) über die konfigurierte Vorschau-Seite
     */
    private function vorschauUri(int $galId): string
    {
        $previewPid = $this->extensionKonfiguration->getPreviewPid();
        if ($previewPid === 0) {
            return '';
        }
        try {
            return (string)$this->siteFinder->getSiteByPageId($previewPid)->getRouter()->generateUri($previewPid, [
                'tx_crgalerie_uebersicht' => ['action' => 'show', 'controller' => 'Uebersicht', 'galerie' => $galId],
            ]);
        } catch (\Throwable) {
            return '';
        }
    }
}
