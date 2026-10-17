<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Domain\Repository;

use Conrat\Crgalerie\Domain\Model\Bilder;
use Conrat\Crgalerie\Domain\Model\Galerie;
use Conrat\Crgalerie\Domain\Sortierung;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Bilder>
 */
class BilderRepository extends Repository
{
    public function initializeObject(): void
    {
        $querySettings = $this->createQuery()->getQuerySettings();
        if ($querySettings instanceof Typo3QuerySettings) {
            $querySettings->setRespectStoragePage(false);
        }
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Sichtbare Bilder einer Galerie mit Datei-Referenz.
     * Die uid ist zweites Kriterium, damit gleich sortierte Bilder in Upload-Reihenfolge erscheinen.
     *
     * @return list<Bilder>
     */
    public function findByGalerie(Galerie $galerie, Sortierung $sortierung): array
    {
        $query = $this->createQuery();
        $query->matching($query->equals('galerie', (int)$galerie->getUid()));
        $richtung = match ($sortierung) {
            Sortierung::ManuellAbsteigend, Sortierung::TitelAbsteigend => QueryInterface::ORDER_DESCENDING,
            default => QueryInterface::ORDER_ASCENDING,
        };
        $feld = match ($sortierung) {
            Sortierung::TitelAufsteigend, Sortierung::TitelAbsteigend => 'titel',
            default => 'sortierung',
        };
        $query->setOrderings([$feld => $richtung, 'uid' => $richtung]);
        /** @var list<Bilder> $bilder */
        $bilder = $query->execute()->toArray();
        return array_values(array_filter(
            $bilder,
            static fn(Bilder $bild): bool => $bild->getDatei() !== null
        ));
    }
}
