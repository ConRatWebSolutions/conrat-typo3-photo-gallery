<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Domain\Dto;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;

/**
 * Für die Ausgabe aufbereitetes Bild.
 */
final readonly class BildAnzeige
{
    public function __construct(
        public FileReference $datei,
        public string $titel,
        public string $beschreibung,
        public string $credit,
    ) {}
}
