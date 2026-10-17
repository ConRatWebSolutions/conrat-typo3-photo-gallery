<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Command;

use Conrat\Crgalerie\Service\BildQuellenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Erzeugt die Frontend-Fassungen (srcset und Lightbox) aller sichtbaren Galeriebilder vorab, z. B. nach einer
 * Migration oder für Altbestände, damit der erste Seitenaufruf sie nicht alle synchron rechnen muss.
 */
#[AsCommand('crgalerie:vorwaermen', 'Pre-generates the image variants (srcset, lightbox) for all visible gallery images.')]
final class FassungenVorwaermenCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ResourceFactory $resourceFactory,
        private readonly BildQuellenService $bildQuellen,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('galerie', null, InputOption::VALUE_REQUIRED, 'Only the images of this gallery (uid)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $galerie = $input->getOption('galerie');
        if ($galerie !== null && (!is_string($galerie) || !ctype_digit($galerie) || (int)$galerie === 0)) {
            $output->writeln('<error>--galerie expects the uid of a gallery.</error>');
            return Command::INVALID;
        }
        $vorbereitet = 0;
        $uebersprungen = 0;
        foreach ($this->dateiUids($galerie === null ? null : (int)$galerie) as $dateiUid) {
            try {
                $datei = $this->resourceFactory->getFileObject($dateiUid);
                $ok = $this->bildQuellen->fuer($datei) !== null;
            } catch (\Throwable) {
                $ok = false;
            }
            $ok ? $vorbereitet++ : $uebersprungen++;
            if ($output->isVerbose()) {
                $output->writeln(sprintf('File %d: %s', $dateiUid, $ok ? 'prepared' : 'skipped'));
            }
        }
        $output->writeln(sprintf('%d %s prepared.', $vorbereitet, $vorbereitet === 1 ? 'image' : 'images'));
        if ($uebersprungen > 0) {
            $output->writeln(sprintf(
                '%d %s skipped (file missing or not a processable image, see the TYPO3 log for details).',
                $uebersprungen,
                $uebersprungen === 1 ? 'image' : 'images'
            ));
        }
        return Command::SUCCESS;
    }

    /**
     * Dateien sichtbarer Bilder in sichtbaren Galerien (Standard-Einschränkungen: gelöscht, ausgeblendet, Zeitraum).
     *
     * @return list<int>
     */
    private function dateiUids(?int $galerie): array
    {
        $abfrage = $this->connectionPool->getQueryBuilderForTable('tx_crgalerie_domain_model_bilder');
        $abfrage->selectLiteral('DISTINCT ' . $abfrage->quoteIdentifier('r.uid_local'))
            ->from('tx_crgalerie_domain_model_bilder', 'b')
            ->join('b', 'tx_crgalerie_domain_model_galerie', 'g', $abfrage->expr()->eq('g.uid', $abfrage->quoteIdentifier('b.galerie')))
            ->join('b', 'sys_file_reference', 'r', (string)$abfrage->expr()->and(
                $abfrage->expr()->eq('r.uid_foreign', $abfrage->quoteIdentifier('b.uid')),
                $abfrage->expr()->eq('r.tablenames', $abfrage->createNamedParameter('tx_crgalerie_domain_model_bilder')),
                $abfrage->expr()->eq('r.fieldname', $abfrage->createNamedParameter('datei')),
            ))
            ->orderBy('r.uid_local');
        if ($galerie !== null) {
            $abfrage->where($abfrage->expr()->eq('b.galerie', $abfrage->createNamedParameter($galerie, Connection::PARAM_INT)));
        }
        return array_map('intval', $abfrage->executeQuery()->fetchFirstColumn());
    }
}
