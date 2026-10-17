<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Domain\Model;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Bilder extends AbstractEntity
{
    protected string $titel = '';
    protected string $bildunterschrift = '';
    protected int $sortierung = 0;
    protected ?FileReference $datei = null;

    public function getTitel(): string
    {
        return $this->titel;
    }

    public function getBildunterschrift(): string
    {
        return $this->bildunterschrift;
    }

    public function getSortierung(): int
    {
        return $this->sortierung;
    }

    public function getDatei(): ?FileReference
    {
        return $this->datei;
    }

    /**
     * Originaldatei, wenn sie wirklich verfügbar ist: Referenz vorhanden, sys_file-Datensatz da,
     * nicht als fehlend markiert und im Speicher vorhanden. Sonst null (auch bei Fehlern im FAL).
     */
    public function verfuegbareDatei(): ?File
    {
        if ($this->datei === null) {
            return null;
        }
        try {
            $datei = $this->datei->getOriginalResource()->getOriginalFile();
            return !$datei->isMissing() && $datei->exists() ? $datei : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function hatVerfuegbareDatei(): bool
    {
        return $this->verfuegbareDatei() !== null;
    }
}
