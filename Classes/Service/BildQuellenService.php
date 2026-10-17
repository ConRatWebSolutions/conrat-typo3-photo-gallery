<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Service;

use Conrat\Crgalerie\Domain\Dto\BildQuellen;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Service\ImageService;

/**
 * Berechnet über die TYPO3-Bildverarbeitung die Vorschau-Fassungen (srcset) und die große Lightbox-Fassung.
 * Bilder werden nie vergrößert.
 */
final class BildQuellenService
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param list<int> $breiten
     * @param int $gross Längste Seite der Lightbox-Fassung; 0 = keine eigene Fassung (Lightbox nutzt die mittlere Vorschau)
     */
    public function fuer(FileInterface|FileReference $datei, array $breiten = [400, 800, 1200], int $gross = 2560): ?BildQuellen
    {
        $breiten = array_values(array_unique(array_filter($breiten, static fn(int $b): bool => $b > 0)));
        sort($breiten);
        if ($breiten === []) {
            $breiten = [400, 800, 1200];
        }
        $kennung = '';
        try {
            $original = $datei instanceof FileReference ? $datei->getOriginalResource() : $datei;
            $basis = $original instanceof CoreFileReference ? $original->getOriginalFile() : $original;
            $kennung = ($basis instanceof File ? $basis->getUid() . ' ' : '') . $basis->getIdentifier();
            if ($basis instanceof File && ($basis->isMissing() || !$basis->exists())) {
                $this->logger->debug('Bilddatei fehlt: ' . $kennung);
                return null;
            }
            $originalBreite = (int)$basis->getProperty('width');
            $originalHoehe = (int)$basis->getProperty('height');

            $eintraege = [];
            $proBreite = [];
            foreach ($breiten as $breite) {
                $fassung = $this->verarbeite($original, ['maxWidth' => $breite]);
                $proBreite[$breite] = $fassung;
                $eintraege[(int)$fassung->getProperty('width') ?: $breite] = $fassung;
            }
            ksort($eintraege);
            $srcset = [];
            foreach ($eintraege as $breite => $fassung) {
                $srcset[] = $this->imageService->getImageUri($fassung) . ' ' . $breite . 'w';
            }
            $mitte = $proBreite[$breiten[intdiv(count($breiten), 2)]];
            $grossFassung = $gross > 0
                ? $this->verarbeite($original, ['maxWidth' => $gross, 'maxHeight' => $gross])
                : $mitte;

            return new BildQuellen(
                $this->imageService->getImageUri($mitte),
                implode(', ', $srcset),
                max(1, (int)$mitte->getProperty('width')),
                max(1, (int)$mitte->getProperty('height')),
                $this->imageService->getImageUri($grossFassung),
                max(1, (int)$grossFassung->getProperty('width')),
                max(1, (int)$grossFassung->getProperty('height')),
                $originalBreite > 0 && $originalHoehe > 0
                    ? number_format($originalBreite / $originalHoehe, 4, '.', '')
                    : '1.5000',
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Bildquellen konnten nicht berechnet werden (' . $kennung . '): ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * @param array<string, int> $anweisungen
     */
    private function verarbeite(FileInterface $datei, array $anweisungen): ProcessedFile
    {
        return $this->imageService->applyProcessingInstructions($datei, $anweisungen);
    }
}
