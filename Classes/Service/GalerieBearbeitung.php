<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\Exception as ResourceException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Alle Schreibvorgänge des Galerie-Moduls laufen über den DataHandler:
 * Rechte, Verlauf, Workspaces und Cache-Leerung kommen so vom Core.
 *
 * Nicht als final deklariert, damit Tests die Klasse mocken können.
 *
 * @final
 */
class GalerieBearbeitung
{
    private const GALERIE = 'tx_crgalerie_domain_model_galerie';
    private const BILDER = 'tx_crgalerie_domain_model_bilder';

    public function __construct(
        private readonly ModulAbfragen $abfragen,
        private readonly GalerieDateiablage $dateiablage,
        private readonly ConnectionPool $connectionPool,
        private readonly LoggerInterface $logger,
    ) {}

    public function bildAnlegen(int $galerieUid, int $pid, int $dateiUid, string $titel): int
    {
        // NEW-Platzhalter ohne Unterstrich: der DataHandler liest „NEW_x“ in Relationsfeldern als „Tabelle_UID“.
        // sys_language_uid muss mitgeschickt werden: Für Nicht-Admins verlangt checkRecordEditAccess() das Sprachfeld,
        // und der DataHandler ergänzt es nur, wenn zur Seite eine Site existiert (addDefaultPermittedLanguageIfNotSet).
        $datenHandler = $this->ausfuehren([
            'sys_file_reference' => ['NEWreferenz' => [
                'pid' => $pid,
                'sys_language_uid' => 0,
                'uid_local' => $dateiUid,
            ]],
            self::BILDER => ['NEWbild' => [
                'pid' => $pid,
                'sys_language_uid' => 0,
                'galerie' => $galerieUid,
                'titel' => $titel,
                'sortierung' => $this->abfragen->naechsteSortierung($galerieUid),
                'datei' => 'NEWreferenz',
            ]],
        ], []);
        $uid = (int)($datenHandler->substNEWwithIDs['NEWbild'] ?? 0);
        if ($uid === 0) {
            throw new GalerieBearbeitungFehler('The image record could not be created.', 1760600021);
        }
        return $uid;
    }

    /**
     * @param list<int> $bildUids gewünschte Reihenfolge; UIDs anderer Galerien werden ignoriert
     */
    public function bilderSortieren(int $galerieUid, array $bildUids): void
    {
        $erlaubt = array_column($this->abfragen->bilder($galerieUid), 'uid');
        $daten = [];
        $position = 1;
        foreach ($bildUids as $uid) {
            if (in_array($uid, $erlaubt, true)) {
                $daten[$uid] = ['sortierung' => $position++];
            }
        }
        if ($daten !== []) {
            $this->ausfuehren([self::BILDER => $daten], []);
        }
    }

    /**
     * @param list<int> $galerieUids gewünschte Reihenfolge; Zehnerschritte lassen Platz zum Einschieben
     */
    public function galerienSortieren(array $galerieUids): void
    {
        $daten = [];
        foreach ($galerieUids as $index => $uid) {
            if ($uid > 0) {
                $daten[$uid] = ['sortierung' => ($index + 1) * 10];
            }
        }
        if ($daten !== []) {
            $this->ausfuehren([self::GALERIE => $daten], []);
        }
    }

    public function bildSichtbarkeit(int $bildUid, bool $sichtbar): void
    {
        $this->ausfuehren([self::BILDER => [$bildUid => ['hidden' => $sichtbar ? 0 : 1]]], []);
    }

    public function galerieSichtbarkeit(int $galerieUid, bool $sichtbar): void
    {
        $this->ausfuehren([self::GALERIE => [$galerieUid => ['hidden' => $sichtbar ? 0 : 1]]], []);
    }

    /**
     * Löscht den Bild-Datensatz und danach die Datei, sofern sie nirgends sonst verwendet wird.
     */
    public function bildLoeschen(int $bildUid): LoeschErgebnis
    {
        $dateiUid = $this->dateiDesBildes($bildUid);
        $this->ausfuehren([], [self::BILDER => [$bildUid => ['delete' => 1]]]);
        if ($dateiUid === 0) {
            return LoeschErgebnis::KeineDatei;
        }
        if ($this->dateiablage->wirdVerwendet($dateiUid)) {
            return LoeschErgebnis::DateiWirdVerwendet;
        }
        try {
            if ($this->dateiablage->loescheWennUnbenutzt($dateiUid)) {
                return LoeschErgebnis::DateiGeloescht;
            }
            $grund = 'deletion refused or file not found';
        } catch (ResourceException $fehler) {
            // Der Datensatz ist gelöscht; die Datei bleibt liegen (z. B. fehlende Löschrechte im Dateispeicher).
            $grund = $fehler->getMessage();
        }
        $this->logger->warning(sprintf(
            'crgalerie: Datei %d von Bild %d konnte nicht gelöscht werden (%s).',
            $dateiUid,
            $bildUid,
            $grund,
        ));
        return LoeschErgebnis::DateiNichtGeloescht;
    }

    private function dateiDesBildes(int $bildUid): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $qb->getRestrictions()->removeAll();
        return (int)$qb->select('uid_local')
            ->from('sys_file_reference')
            ->where(
                $qb->expr()->eq('uid_foreign', $qb->createNamedParameter($bildUid, Connection::PARAM_INT)),
                $qb->expr()->eq('tablenames', $qb->createNamedParameter(self::BILDER)),
                $qb->expr()->eq('fieldname', $qb->createNamedParameter('datei')),
                $qb->expr()->eq('deleted', 0),
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $daten
     * @param array<string, array<int, array<string, mixed>>> $befehle
     */
    private function ausfuehren(array $daten, array $befehle): DataHandler
    {
        $datenHandler = GeneralUtility::makeInstance(DataHandler::class);
        $datenHandler->start($daten, $befehle);
        if ($daten !== []) {
            $datenHandler->process_datamap();
        }
        if ($befehle !== []) {
            $datenHandler->process_cmdmap();
        }
        if ($datenHandler->errorLog !== []) {
            throw new GalerieBearbeitungFehler(implode(' ', $datenHandler->errorLog), 1760600022);
        }
        return $datenHandler;
    }
}
