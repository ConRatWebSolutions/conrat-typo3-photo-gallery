<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Controller\Backend;

use Conrat\Crgalerie\Service\BildQuellenService;
use Conrat\Crgalerie\Service\BildVerarbeitung;
use Conrat\Crgalerie\Service\BildVerarbeitungFehler;
use Conrat\Crgalerie\Service\DateiablageFehler;
use Conrat\Crgalerie\Service\GalerieBearbeitung;
use Conrat\Crgalerie\Service\GalerieDateiablage;
use Conrat\Crgalerie\Service\ModulAbfragen;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Resource\Exception\IllegalFileExtensionException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientUserPermissionsException;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;

/**
 * Nimmt im Galerie-Modul hochgeladene Bilder entgegen (eine Datei pro Anfrage) und antwortet mit JSON.
 */
final class UploadAjaxController
{
    public function __construct(
        private readonly ModulAbfragen $abfragen,
        private readonly BildVerarbeitung $verarbeitung,
        private readonly GalerieDateiablage $dateiablage,
        private readonly GalerieBearbeitung $bearbeitung,
        private readonly ImageService $imageService,
        private readonly BildQuellenService $bildQuellen,
        private readonly LoggerInterface $logger,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function uploadAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $upload = $request->getUploadedFiles()['file'] ?? null;
        // Überschreitet die Anfrage post_max_size, verwirft PHP Formularfelder und Dateien gleichermaßen.
        if ($upload === null && ($body === null || $body === [])) {
            return $this->zuGross();
        }
        $galerie = $this->abfragen->galerie((int)(is_array($body) ? ($body['galerie'] ?? 0) : 0));
        if ($galerie === null) {
            return $this->fehler('error.galleryNotFound', 400);
        }
        if (!$this->darfBearbeiten($galerie['pid'])) {
            return $this->fehler('error.noPermission', 403);
        }
        if (!$upload instanceof UploadedFileInterface || $upload->getError() !== UPLOAD_ERR_OK) {
            return $this->zuGross();
        }

        $originalName = (string)$upload->getClientFilename();
        $temporaer = GeneralUtility::tempnam('crgalerie_upload_');
        $verarbeitet = null;
        try {
            $this->speichereTemporaer($upload, $temporaer);
            $verarbeitet = $this->verarbeitung->verarbeite($temporaer, $originalName);
            try {
                $datei = $this->dateiablage->ablegen($verarbeitet, $galerie['uid'], $galerie['titel']);
            } catch (
                InsufficientUserPermissionsException
                | InsufficientFolderAccessPermissionsException
                | InsufficientFileAccessPermissionsException
            ) {
                // Die Folder-/File-Ausnahmen decken auch ihre Read-/Write-Unterklassen ab.
                return $this->fehler('error.noWriteAccess', 403);
            } catch (IllegalFileExtensionException) {
                return $this->fehler('error.format', 415);
            }
            try {
                $uid = $this->bearbeitung->bildAnlegen(
                    $galerie['uid'],
                    $galerie['pid'],
                    $datei->getUid(),
                    BildVerarbeitung::titelAusDateiname($originalName)
                );
            } catch (\Throwable $fehler) {
                $this->logger->error('crgalerie: Bild-Datensatz konnte nicht angelegt werden', ['datei' => $originalName, 'fehler' => $fehler->getMessage()]);
                try {
                    $this->dateiablage->loescheWennUnbenutzt($datei->getUid());
                } catch (\Throwable $aufraeumFehler) {
                    $this->logger->error('crgalerie: Abgelegte Datei konnte nicht wieder entfernt werden', [
                        'datei' => $datei->getIdentifier(),
                        'fehler' => $aufraeumFehler->getMessage(),
                    ]);
                }
                return $this->fehler('error.saveImage', 500);
            }
            $this->fassungenVorbereiten($datei);
            return new JsonResponse([
                'success' => true,
                'uid' => $uid,
                'dateiname' => $datei->getName(),
                'vorschauUrl' => $this->vorschauUrl($datei),
            ]);
        } catch (BildVerarbeitungFehler $fehler) {
            return $this->fehler($fehler->sprachschluessel, $fehler->httpStatus);
        } catch (DateiablageFehler $fehler) {
            $this->logger->error('crgalerie: Upload-Ordner nicht verfügbar', ['fehler' => $fehler->getMessage()]);
            return $this->fehler('error.uploadFolder', 500);
        } catch (\Throwable $fehler) {
            $this->logger->error('crgalerie: Upload fehlgeschlagen', ['datei' => $originalName, 'fehler' => $fehler->getMessage()]);
            return $this->fehler('error.generic', 500);
        } finally {
            @unlink($temporaer);
            if ($verarbeitet !== null) {
                @unlink($verarbeitet->pfad);
            }
        }
    }

    private function darfBearbeiten(int $pid): bool
    {
        $benutzer = $GLOBALS['BE_USER'] ?? null;
        if (!$benutzer instanceof BackendUserAuthentication) {
            return false;
        }
        if (!$benutzer->check('tables_modify', 'tx_crgalerie_domain_model_bilder')
            || !$benutzer->check('tables_modify', 'sys_file_reference')
        ) {
            return false;
        }
        if ($pid === 0) {
            return $benutzer->isAdmin();
        }
        $seite = BackendUtility::getRecord('pages', $pid);
        return is_array($seite) && $benutzer->doesUserHaveAccess($seite, Permission::CONTENT_EDIT);
    }

    private function speichereTemporaer(UploadedFileInterface $upload, string $ziel): void
    {
        $stream = $upload->getStream();
        $stream->rewind();
        $handle = fopen($ziel, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Temporary file is not writable.', 1760600031);
        }
        while (!$stream->eof()) {
            if (fwrite($handle, $stream->read(65536)) === false) {
                fclose($handle);
                throw new \RuntimeException('Temporary file could not be written.', 1760600032);
            }
        }
        fclose($handle);
    }

    /**
     * Erzeugt die Frontend-Fassungen (srcset und Lightbox) schon beim Upload, damit der erste Seitenaufruf sie
     * nicht alle synchron rechnen muss. Ein Fehler hier lässt den Upload gelingen; die Fassungen entstehen dann
     * beim ersten Aufruf.
     */
    private function fassungenVorbereiten(\TYPO3\CMS\Core\Resource\File $datei): void
    {
        try {
            if ($this->bildQuellen->fuer($datei) === null) {
                $this->logger->warning('crgalerie: Fassungen konnten beim Upload nicht vorbereitet werden', ['datei' => $datei->getIdentifier()]);
            }
        } catch (\Throwable $fehler) {
            $this->logger->warning('crgalerie: Fassungen konnten beim Upload nicht vorbereitet werden', [
                'datei' => $datei->getIdentifier(),
                'fehler' => $fehler->getMessage(),
            ]);
        }
    }

    private function vorschauUrl(\TYPO3\CMS\Core\Resource\File $datei): string
    {
        try {
            $verarbeitet = $this->imageService->applyProcessingInstructions($datei, ['width' => '300c', 'height' => '300c']);
            return $this->imageService->getImageUri($verarbeitet);
        } catch (\Throwable) {
            return '';
        }
    }

    private function zuGross(): ResponseInterface
    {
        $maxMb = (int)floor(GeneralUtility::getMaxUploadFileSize() / 1024);
        return $this->fehler('error.tooLarge', 400, $maxMb);
    }

    /**
     * @param string $schluessel Sprachschlüssel (locallang.xlf); die Meldung geht in der Sprache des Benutzers raus
     */
    private function fehler(string $schluessel, int $status, int|string ...$werte): ResponseInterface
    {
        $benutzer = $GLOBALS['BE_USER'] ?? null;
        $sprache = $benutzer instanceof BackendUserAuthentication
            ? $this->languageServiceFactory->createFromUserPreferences($benutzer)
            : $this->languageServiceFactory->create('default');
        $meldung = $sprache->sL('LLL:EXT:crgalerie/Resources/Private/Language/locallang.xlf:' . $schluessel);
        if ($werte !== []) {
            $meldung = vsprintf($meldung, $werte);
        }
        return new JsonResponse(['success' => false, 'message' => $meldung], $status);
    }
}
