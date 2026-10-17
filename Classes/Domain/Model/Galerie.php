<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Domain\Model;

use TYPO3\CMS\Extbase\Attribute\ORM\Lazy;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Galerie extends AbstractEntity
{
    protected string $titel = '';
    protected string $beschreibung = '';
    protected string $fotograf = '';
    protected ?\DateTime $aufnahmezeitpunkt = null;
    protected int $sortierung = 0;
    protected ?Kategorie $kategorie = null;

    /**
     * @var ObjectStorage<Bilder>
     */
    #[Lazy]
    protected ObjectStorage $bilder;

    /**
     * Zwischenspeicher für verfuegbareBilder(); ohne TCA-Spalte, Extbase mappt und speichert ihn nicht.
     *
     * @var list<Bilder>|null
     */
    private ?array $verfuegbareBilder = null;

    public function __construct()
    {
        $this->bilder = new ObjectStorage();
    }

    public function initializeObject(): void
    {
        // Extbase rekonstruiert Objekte ohne Konstruktor: Property ist dann evtl. noch nicht initialisiert.
        if (!(new \ReflectionProperty($this, 'bilder'))->isInitialized($this)) {
            $this->bilder = new ObjectStorage();
        }
    }

    public function getTitel(): string
    {
        return $this->titel;
    }

    public function getBeschreibung(): string
    {
        return $this->beschreibung;
    }

    public function getFotograf(): string
    {
        return $this->fotograf;
    }

    public function getAufnahmezeitpunkt(): ?\DateTime
    {
        return $this->aufnahmezeitpunkt;
    }

    public function getSortierung(): int
    {
        return $this->sortierung;
    }

    public function getKategorie(): ?Kategorie
    {
        return $this->kategorie;
    }

    /**
     * @return ObjectStorage<Bilder>
     */
    public function getBilder(): ObjectStorage
    {
        return $this->bilder;
    }

    /**
     * Erstes sichtbares Bild mit verfügbarer Datei (Reihenfolge wie im Modul), z. B. als Kachelbild.
     */
    public function getErstesBild(): ?Bilder
    {
        return $this->verfuegbareBilder()[0] ?? null;
    }

    /**
     * Anzahl sichtbarer Bilder mit verfügbarer Datei – so viele zeigt die Galerie auch an.
     */
    public function getAnzahlBilder(): int
    {
        return count($this->verfuegbareBilder());
    }

    /**
     * Die Übersicht fragt Anzahl und erstes Bild je Galerie mehrfach ab (Repository-Filter und Template); geprüft wird
     * jede Datei (auch im Dateisystem) aber nur einmal pro Anfrage.
     *
     * @return list<Bilder>
     */
    private function verfuegbareBilder(): array
    {
        if ($this->verfuegbareBilder === null) {
            $this->verfuegbareBilder = [];
            foreach ($this->getBilder() as $bild) {
                if ($bild->hatVerfuegbareDatei()) {
                    $this->verfuegbareBilder[] = $bild;
                }
            }
        }
        return $this->verfuegbareBilder;
    }
}
