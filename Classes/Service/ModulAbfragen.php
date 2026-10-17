<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Lesezugriffe des Backend-Moduls. Liefert auch ausgeblendete Datensätze (nur gelöschte nicht).
 */
final class ModulAbfragen
{
    private const GALERIE = 'tx_crgalerie_domain_model_galerie';
    private const BILDER = 'tx_crgalerie_domain_model_bilder';

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return list<array{uid:int, pid:int, titel:string, hidden:int, sortierung:int, crdate:int, kategorie:string, anzahl:int, ausgeblendet:int, titelbild:int}>
     */
    public function galerienUebersicht(): array
    {
        $qb = $this->queryBuilder(self::GALERIE);
        $zeilen = $qb
            ->select('g.uid', 'g.pid', 'g.titel', 'g.hidden', 'g.sortierung', 'g.crdate', 'k.titel AS kategorie')
            ->from(self::GALERIE, 'g')
            ->leftJoin('g', 'tx_crgalerie_domain_model_kategorie', 'k', $qb->expr()->eq('k.uid', $qb->quoteIdentifier('g.kategorie')))
            ->orderBy('g.crdate', 'DESC')
            ->addOrderBy('g.uid', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        $statistik = $this->bildStatistik();
        $ergebnis = [];
        foreach ($zeilen as $zeile) {
            $uid = (int)$zeile['uid'];
            $ergebnis[] = [
                'uid' => $uid,
                'pid' => (int)$zeile['pid'],
                'titel' => (string)$zeile['titel'],
                'hidden' => (int)$zeile['hidden'],
                'sortierung' => (int)$zeile['sortierung'],
                'crdate' => (int)$zeile['crdate'],
                'kategorie' => (string)($zeile['kategorie'] ?? ''),
                'anzahl' => $statistik[$uid]['anzahl'] ?? 0,
                'ausgeblendet' => $statistik[$uid]['ausgeblendet'] ?? 0,
                'titelbild' => $statistik[$uid]['titelbild'] ?? 0,
            ];
        }
        return $ergebnis;
    }

    /**
     * @return array{uid:int, pid:int, titel:string, fotograf:string, hidden:int, status:string}|null
     */
    public function galerie(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $qb = $this->queryBuilder(self::GALERIE);
        $zeile = $qb
            ->select('uid', 'pid', 'titel', 'fotograf', 'hidden')
            ->from(self::GALERIE)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        if ($zeile === false) {
            return null;
        }
        return [
            'uid' => (int)$zeile['uid'],
            'pid' => (int)$zeile['pid'],
            'titel' => (string)$zeile['titel'],
            'fotograf' => (string)$zeile['fotograf'],
            'hidden' => (int)$zeile['hidden'],
            'status' => (int)$zeile['hidden'] === 1 ? 'fhidden' : 'fvisible',
        ];
    }

    /**
     * @return list<array{uid:int, pid:int, titel:string, hidden:int, status:string, sortierung:int, datei:int, metadaten:int}>
     */
    public function bilder(int $galerieUid): array
    {
        $ergebnis = [];
        foreach ($this->bildZeilen($galerieUid) as $zeile) {
            $ergebnis[] = [
                'uid' => (int)$zeile['uid'],
                'pid' => (int)$zeile['pid'],
                'titel' => (string)$zeile['titel'],
                'hidden' => (int)$zeile['hidden'],
                'status' => (int)$zeile['hidden'] === 1 ? 'fhidden' : 'fvisible',
                'sortierung' => (int)$zeile['sortierung'],
                'datei' => (int)($zeile['datei'] ?? 0),
                'metadaten' => (int)($zeile['metadaten'] ?? 0),
            ];
        }
        return $ergebnis;
    }

    public function naechsteSortierung(int $galerieUid): int
    {
        $qb = $this->queryBuilder(self::BILDER);
        $max = $qb
            ->selectLiteral('MAX(' . $qb->quoteIdentifier('sortierung') . ')')
            ->from(self::BILDER)
            ->where($qb->expr()->eq('galerie', $qb->createNamedParameter($galerieUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        return (int)$max + 1;
    }

    /**
     * Bilder einer (oder aller) Galerien mit aktiver Referenz und vorhandener Datei.
     *
     * @return list<array<string, mixed>>
     */
    private function bildZeilen(?int $galerieUid): array
    {
        $qb = $this->queryBuilder(self::BILDER);
        $qb->select('b.uid', 'b.pid', 'b.titel', 'b.hidden', 'b.sortierung', 'b.galerie', 'f.uid AS datei', 'm.uid AS metadaten')
            ->from(self::BILDER, 'b')
            ->leftJoin('b', 'sys_file_reference', 'r', (string)$qb->expr()->and(
                $qb->expr()->eq('r.uid_foreign', $qb->quoteIdentifier('b.uid')),
                $qb->expr()->eq('r.tablenames', $qb->createNamedParameter(self::BILDER)),
                $qb->expr()->eq('r.fieldname', $qb->createNamedParameter('datei')),
                $qb->expr()->eq('r.deleted', 0),
            ))
            ->leftJoin('r', 'sys_file', 'f', (string)$qb->expr()->and(
                $qb->expr()->eq('f.uid', $qb->quoteIdentifier('r.uid_local')),
                $qb->expr()->eq('f.missing', 0),
            ))
            ->leftJoin('f', 'sys_file_metadata', 'm', (string)$qb->expr()->and(
                $qb->expr()->eq('m.file', $qb->quoteIdentifier('f.uid')),
                $qb->expr()->eq('m.sys_language_uid', 0),
            ))
            ->orderBy('b.sortierung')
            ->addOrderBy('b.uid');
        if ($galerieUid !== null) {
            $qb->where($qb->expr()->eq('b.galerie', $qb->createNamedParameter($galerieUid, Connection::PARAM_INT)));
        }
        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return array<int, array{anzahl:int, ausgeblendet:int, titelbild:int}>
     */
    private function bildStatistik(): array
    {
        $statistik = [];
        foreach ($this->bildZeilen(null) as $zeile) {
            $galerie = (int)$zeile['galerie'];
            $statistik[$galerie] ??= ['anzahl' => 0, 'ausgeblendet' => 0, 'titelbild' => 0];
            $statistik[$galerie]['anzahl']++;
            if ((int)$zeile['hidden'] === 1) {
                $statistik[$galerie]['ausgeblendet']++;
            } elseif ($statistik[$galerie]['titelbild'] === 0 && (int)($zeile['datei'] ?? 0) > 0) {
                $statistik[$galerie]['titelbild'] = (int)$zeile['datei'];
            }
        }
        return $statistik;
    }

    private function queryBuilder(string $tabelle): QueryBuilder
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($tabelle);
        $qb->getRestrictions()->removeAll()->add(new DeletedRestriction());
        return $qb;
    }
}
