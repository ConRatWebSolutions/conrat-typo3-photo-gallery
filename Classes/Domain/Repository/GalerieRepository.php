<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Domain\Repository;

use Conrat\Crgalerie\Domain\Model\Galerie;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Galerie>
 */
class GalerieRepository extends Repository
{
    public function initializeObject(): void
    {
        // Galerien hängen nicht an Seiten (Bestandsdaten liegen auf Ebene 0)
        $querySettings = $this->createQuery()->getQuerySettings();
        if ($querySettings instanceof Typo3QuerySettings) {
            $querySettings->setRespectStoragePage(false);
        }
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Sichtbare Galerien mit mindestens einem Bild, nach Sortierung.
     *
     * @return list<Galerie>
     */
    public function findSichtbare(?int $kategorie = null): array
    {
        $query = $this->createQuery();
        if ($kategorie !== null && $kategorie > 0) {
            $query->matching($query->equals('kategorie', $kategorie));
        }
        $query->setOrderings([
            'sortierung' => QueryInterface::ORDER_ASCENDING,
            'uid' => QueryInterface::ORDER_ASCENDING,
        ]);
        /** @var list<Galerie> $galerien */
        $galerien = $query->execute()->toArray();
        return array_values(array_filter(
            $galerien,
            static fn(Galerie $galerie): bool => $galerie->getAnzahlBilder() > 0
        ));
    }

    public function findEineSichtbare(int $uid): ?Galerie
    {
        if ($uid <= 0) {
            return null;
        }
        $query = $this->createQuery();
        $query->matching($query->equals('uid', $uid));
        $galerie = $query->execute()->getFirst();
        return $galerie instanceof Galerie ? $galerie : null;
    }
}
