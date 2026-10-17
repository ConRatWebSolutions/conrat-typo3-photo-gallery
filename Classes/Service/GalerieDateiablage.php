<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Service;

use Conrat\Crgalerie\Configuration\ExtensionKonfiguration;
use Conrat\Crgalerie\Domain\Dto\VerarbeitetesBild;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Ablage der Galeriebilder in FAL: ein Ordner pro Galerie unter dem konfigurierten Upload-Ordner.
 */
final class GalerieDateiablage
{
    public function __construct(
        private readonly ExtensionKonfiguration $konfiguration,
        private readonly ResourceFactory $resourceFactory,
        private readonly ConnectionPool $connectionPool,
        private readonly StorageRepository $storageRepository,
    ) {}

    /**
     * „12-sommerfest-2026“; die UID macht den Namen eindeutig, der Titel dient nur der Lesbarkeit.
     */
    public static function ordnerName(int $uid, string $titel): string
    {
        $text = GeneralUtility::makeInstance(CharsetConverter::class)->utf8_char_mapping($titel);
        $text = strtolower($text);
        $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text) ?? '', '-');
        $text = rtrim(substr($text, 0, 40), '-');
        return $uid . '-' . ($text !== '' ? $text : 'galerie');
    }

    public function ordnerFuer(int $galerieUid, string $galerieTitel): Folder
    {
        $basis = $this->basisOrdner();
        foreach ($basis->getSubfolders() as $unterordner) {
            if (str_starts_with($unterordner->getName(), $galerieUid . '-')) {
                return $unterordner;
            }
        }
        return $basis->createFolder(self::ordnerName($galerieUid, $galerieTitel));
    }

    public function ablegen(VerarbeitetesBild $bild, int $galerieUid, string $galerieTitel): File
    {
        return $this->ordnerFuer($galerieUid, $galerieTitel)
            ->addFile($bild->pfad, $bild->dateiname, DuplicationBehavior::RENAME);
    }

    /**
     * Löscht die Datei, wenn sie nirgends mehr verwendet wird: keine aktive Dateireferenz und kein Eintrag im
     * Referenzindex (z. B. „t3://file?uid=…“-Links in Texten oder Link-Feldern).
     *
     * @return bool true, wenn die Datei gelöscht wurde; false, wenn sie noch verwendet wird oder nicht existiert
     */
    public function loescheWennUnbenutzt(int $dateiUid): bool
    {
        if ($this->wirdVerwendet($dateiUid)) {
            return false;
        }
        try {
            $datei = $this->resourceFactory->getFileObject($dateiUid);
        } catch (\Throwable) {
            return false;
        }
        return $datei->delete();
    }

    /**
     * true, wenn eine aktive Dateireferenz oder ein Eintrag im Referenzindex (außer Referenzen/Metadaten) auf die Datei zeigt.
     */
    public function wirdVerwendet(int $dateiUid): bool
    {
        return $this->aktiveReferenzen($dateiUid) > 0 || $this->fremdeIndexEintraege($dateiUid) > 0;
    }

    private function aktiveReferenzen(int $dateiUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder
            ->count('uid')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($dateiUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Verwendungen außerhalb von Dateireferenzen und Metadaten; gelöschte Datensätze stehen nicht im Referenzindex.
     */
    private function fremdeIndexEintraege(int $dateiUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_refindex');
        return (int)$queryBuilder
            ->count('hash')
            ->from('sys_refindex')
            ->where(
                $queryBuilder->expr()->eq('ref_table', $queryBuilder->createNamedParameter('sys_file')),
                $queryBuilder->expr()->eq('ref_uid', $queryBuilder->createNamedParameter($dateiUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->notIn(
                    'tablename',
                    $queryBuilder->createNamedParameter(['sys_file_reference', 'sys_file_metadata'], Connection::PARAM_STR_ARRAY)
                ),
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function basisOrdner(): Folder
    {
        $kennung = $this->konfiguration->getUploadFolder();
        [$speicherUid, $pfad] = explode(':', $kennung, 2);
        $speicher = $this->storageRepository->findByUid((int)$speicherUid);
        if ($speicher === null || !$speicher->isOnline()) {
            throw new DateiablageFehler(
                'The upload folder is not available, check the extension configuration (uploadFolder).',
                1760600012
            );
        }
        if ($speicher->hasFolder($pfad)) {
            return $speicher->getFolder($pfad);
        }
        return $speicher->createFolder($pfad);
    }
}
