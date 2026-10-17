<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Upgrades;

use Conrat\Crgalerie\Upgrades\PluginZuCTypeUpgrade;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class PluginZuCTypeUpgradeTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['conrat/crgalerie'];

    private function legeSpalteAn(string $spalte): void
    {
        $verbindung = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $spalten = array_keys($verbindung->createSchemaManager()->listTableColumns('tt_content'));
        if (!in_array($spalte, $spalten, true)) {
            $verbindung->executeStatement(sprintf("ALTER TABLE tt_content ADD COLUMN %s varchar(255) DEFAULT '' NOT NULL", $spalte));
        }
    }

    private function wizard(): PluginZuCTypeUpgrade
    {
        $wizard = $this->get(PluginZuCTypeUpgrade::class);
        $wizard->setOutput(new BufferedOutput());
        return $wizard;
    }

    /**
     * @return array<string, mixed>
     */
    private function flexForm(int $uid): array
    {
        $xml = (string)$this->getConnectionPool()->getConnectionForTable('tt_content')
            ->select(['pi_flexform'], 'tt_content', ['uid' => $uid])->fetchOne();
        return $this->get(FlexFormTools::class)->convertFlexFormContentToArray($xml);
    }

    #[Test]
    public function migriertNachFlexFormUndPasstGruppenAn(): void
    {
        $this->legeSpalteAn('list_type');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/AltePlugins.csv');

        $wizard = $this->wizard();
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/../Fixtures/AltePluginsErwartet.csv');
        self::assertFalse($this->wizard()->updateNecessary());
    }

    #[Test]
    public function bereinigtFlexForm(): void
    {
        $this->legeSpalteAn('list_type');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/AltePlugins.csv');
        $this->wizard()->executeUpdate();

        $galerie = $this->flexForm(1);
        self::assertSame(['galerie' => '5', 'showDescription' => '3', 'order' => '2'], $galerie['settings']);

        $uebersicht = $this->flexForm(2);
        self::assertSame(['category' => '4'], $uebersicht['settings']);

        $ohneAnzeige = $this->flexForm(4);
        self::assertSame(['galerie' => '8'], $ohneAnzeige['settings']);
    }

    #[Test]
    public function funktioniertMitUmbenannterSpalte(): void
    {
        // Beide Spalten anlegen: list_type wird für den CSV-Import gebraucht
        $this->legeSpalteAn('list_type');
        $this->legeSpalteAn('zzz_deleted_list_type');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/AltePlugins.csv');
        // Fixture schreibt in list_type; für diesen Test die Werte in die umbenannte Spalte kopieren
        $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->executeStatement('UPDATE tt_content SET zzz_deleted_list_type = list_type, list_type = \'\'');

        self::assertTrue($this->wizard()->updateNecessary());
        $this->wizard()->executeUpdate();
        $ctype = $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->select(['CType'], 'tt_content', ['uid' => 1])->fetchOne();
        self::assertSame('crgalerie_galerie', $ctype);
    }

    #[Test]
    public function ohneAlteSpalteNichtsZuTun(): void
    {
        self::assertFalse($this->wizard()->updateNecessary());
    }
}
