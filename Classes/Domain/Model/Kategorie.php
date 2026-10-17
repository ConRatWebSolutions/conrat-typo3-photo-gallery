<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Kategorie extends AbstractEntity
{
    protected string $titel = '';

    public function getTitel(): string
    {
        return $this->titel;
    }
}
