<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Upgrades;

use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Stellt Inhaltselemente des alten Plugins „crgalerie_crgalerie“ (CType list) auf die CTypes
 * crgalerie_galerie bzw. crgalerie_uebersicht um – je nachdem, ob in der FlexForm eine Galerie gewählt ist.
 */
#[UpgradeWizard('crgalerieCTypeMigration')]
final class PluginZuCTypeUpgrade implements UpgradeWizardInterface, ChattyInterface
{
    private const ALTER_LIST_TYPE = 'crgalerie_crgalerie';
    private const CTYPE_GALERIE = 'crgalerie_galerie';
    private const CTYPE_UEBERSICHT = 'crgalerie_uebersicht';
    /** TYPO3 14 hat list_type nicht mehr im Schema; nach „Entfernen“ in der DB-Analyse heißt sie so: */
    private const SPALTEN = ['list_type', 'zzz_deleted_list_type'];

    private ?OutputInterface $output = null;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly FlexFormTools $flexFormTools,
    ) {}

    public function getTitle(): string
    {
        return 'crgalerie: Plugin auf Inhaltselemente „Galerie“ / „Galerie-Übersicht“ umstellen';
    }

    public function getDescription(): string
    {
        return 'Elemente des alten Plugins „ConRat Galerie“ werden zu „Galerie“ (Galerie gewählt) bzw. '
            . '„Galerie-Übersicht“ (keine Galerie gewählt). Backend-Gruppenrechte werden angepasst.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function updateNecessary(): bool
    {
        return $this->spalteMitTreffern() !== null || $this->gruppenMitAltemRecht() !== [];
    }

    public function executeUpdate(): bool
    {
        $anzahl = [self::CTYPE_GALERIE => 0, self::CTYPE_UEBERSICHT => 0];
        $spalte = $this->spalteMitTreffern();
        if ($spalte !== null) {
            $verbindung = $this->connectionPool->getConnectionForTable('tt_content');
            foreach ($this->alteElemente($spalte) as $element) {
                [$ctype, $flexForm] = $this->wandle((string)($element['pi_flexform'] ?? ''));
                $verbindung->update(
                    'tt_content',
                    ['CType' => $ctype, 'pi_flexform' => $flexForm, $spalte => ''],
                    ['uid' => (int)$element['uid']]
                );
                $anzahl[$ctype]++;
            }
        }
        $gruppen = $this->aktualisiereGruppen();

        $this->output?->writeln(sprintf(
            '%d Element(e) → Galerie, %d Element(e) → Galerie-Übersicht, %d Backend-Gruppe(n) angepasst.',
            $anzahl[self::CTYPE_GALERIE],
            $anzahl[self::CTYPE_UEBERSICHT],
            $gruppen
        ));
        return true;
    }

    /**
     * @return array{0: string, 1: string} [CType, bereinigte FlexForm]
     */
    private function wandle(string $xml): array
    {
        $daten = $xml !== '' ? GeneralUtility::xml2array($xml) : [];
        if (!is_array($daten)) {
            $daten = [];
        }
        $grund = $daten['data']['Grundeinstellungen']['lDEF'] ?? [];
        $galerie = (int)($grund['settings.galerie']['vDEF'] ?? 0);
        $ctype = $galerie > 0 ? self::CTYPE_GALERIE : self::CTYPE_UEBERSICHT;

        if (!isset($daten['data']['Grundeinstellungen']['lDEF'])) {
            return [$ctype, $xml];
        }
        unset($daten['data']['Grundeinstellungen']['lDEF']['settings.folderpid']);
        if ($ctype === self::CTYPE_GALERIE) {
            unset($daten['data']['Grundeinstellungen']['lDEF']['settings.category']);
        } else {
            unset($daten['data']['Grundeinstellungen']['lDEF']['settings.galerie']);
        }
        return [$ctype, $this->flexFormTools->flexArray2Xml($daten)];
    }

    private function spalteMitTreffern(): ?string
    {
        $vorhanden = array_keys(
            $this->connectionPool->getConnectionForTable('tt_content')->createSchemaManager()->listTableColumns('tt_content')
        );
        $vorhanden = array_map('strtolower', $vorhanden);
        foreach (self::SPALTEN as $spalte) {
            if (in_array($spalte, $vorhanden, true) && $this->alteElemente($spalte) !== []) {
                return $spalte;
            }
        }
        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alteElemente(string $spalte): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder
            ->select('uid', 'pi_flexform')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list')),
                $queryBuilder->expr()->eq($spalte, $queryBuilder->createNamedParameter(self::ALTER_LIST_TYPE)),
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function gruppenMitAltemRecht(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder
            ->select('uid', 'explicit_allowdeny')
            ->from('be_groups')
            ->where($queryBuilder->expr()->like(
                'explicit_allowdeny',
                $queryBuilder->createNamedParameter(
                    '%' . $queryBuilder->escapeLikeWildcards('tt_content:list_type:' . self::ALTER_LIST_TYPE) . '%'
                )
            ))
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function aktualisiereGruppen(): int
    {
        $verbindung = $this->connectionPool->getConnectionForTable('be_groups');
        $gruppen = $this->gruppenMitAltemRecht();
        foreach ($gruppen as $gruppe) {
            $rechte = GeneralUtility::trimExplode(',', (string)$gruppe['explicit_allowdeny'], true);
            $neu = [];
            foreach ($rechte as $recht) {
                if ($recht === 'tt_content:list_type:' . self::ALTER_LIST_TYPE) {
                    $neu[] = 'tt_content:CType:' . self::CTYPE_GALERIE;
                    $neu[] = 'tt_content:CType:' . self::CTYPE_UEBERSICHT;
                } else {
                    $neu[] = $recht;
                }
            }
            $verbindung->update(
                'be_groups',
                ['explicit_allowdeny' => implode(',', array_values(array_unique($neu)))],
                ['uid' => (int)$gruppe['uid']]
            );
        }
        return count($gruppen);
    }
}
