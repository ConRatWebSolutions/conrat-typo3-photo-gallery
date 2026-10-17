<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Backend;

use Conrat\Crgalerie\Controller\Backend\UploadAjaxController;
use Conrat\Crgalerie\Service\GalerieBearbeitung;
use Conrat\Crgalerie\Service\GalerieBearbeitungFehler;
use Conrat\Crgalerie\Tests\Functional\BackendBenutzerTrait;
use Conrat\Crgalerie\Tests\Functional\TestbildTrait;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class UploadAjaxControllerTest extends FunctionalTestCase
{
    use BackendBenutzerTrait;
    use TestbildTrait;

    protected array $testExtensionsToLoad = ['conratwebsolutions/crgalerie'];

    protected array $configurationToUseInTestInstance = [
        'GFX' => [
            'processor' => 'ImageMagick',
            'processor_path' => '/usr/bin/',
            'processor_enabled' => true,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Modul.csv');
    }

    private function hochladen(string $quelle, string $name, int $galerie = 1): ResponseInterface
    {
        $datei = new UploadedFile(new Stream(fopen($quelle, 'rb')), (int)filesize($quelle), UPLOAD_ERR_OK, $name, 'image/jpeg');
        $request = (new ServerRequest('https://typo3-dev.ddev.site/typo3/ajax/crgalerie/upload', 'POST'))
            ->withParsedBody(['galerie' => (string)$galerie])
            ->withUploadedFiles(['file' => $datei]);
        return $this->get(UploadAjaxController::class)->uploadAction($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $antwort): array
    {
        return json_decode((string)$antwort->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function quelle(string $name, int $breite = 800, int $hoehe = 600): string
    {
        $verzeichnis = Environment::getVarPath() . '/transient/upload/';
        @mkdir($verzeichnis, 0777, true);
        return $this->erzeugeTestbild($verzeichnis . $name, $breite, $hoehe);
    }

    #[Test]
    public function erfolgreicherUploadLegtDateiUndBildAn(): void
    {
        $this->meldeAn(1);
        $antwort = $this->hochladen($this->quelle('quelle.jpg'), 'Burg_Tor.jpg');
        self::assertSame(200, $antwort->getStatusCode());
        $daten = $this->json($antwort);
        self::assertTrue($daten['success']);
        self::assertSame('Burg_Tor.jpg', $daten['dateiname']);
        self::assertNotSame('', $daten['vorschauUrl']);
        self::assertFileExists(Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/Burg_Tor.jpg');
        $titel = $this->getConnectionPool()->getConnectionForTable('tx_crgalerie_domain_model_bilder')
            ->select(['titel'], 'tx_crgalerie_domain_model_bilder', ['uid' => $daten['uid']])->fetchOne();
        self::assertSame('Burg Tor', $titel);
    }

    #[Test]
    public function unbekannteGalerieErgibt400(): void
    {
        $this->meldeAn(1);
        $antwort = $this->hochladen($this->quelle('a.jpg'), 'a.jpg', 99);
        self::assertSame(400, $antwort->getStatusCode());
        self::assertFalse($this->json($antwort)['success']);
    }

    #[Test]
    public function keinBildErgibt415UndKeineDatei(): void
    {
        $this->meldeAn(1);
        $quelle = Environment::getVarPath() . '/transient/kaputt.jpg';
        file_put_contents($quelle, '<html>kein Bild</html>');
        $antwort = $this->hochladen($quelle, 'kaputt.jpg');
        self::assertSame(415, $antwort->getStatusCode());
        self::assertSame('Dieses Dateiformat wird nicht unterstützt. Erlaubt sind JPG, PNG, WebP und HEIC.', $this->json($antwort)['message']);
        self::assertFileDoesNotExist(Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/kaputt.jpg');
    }

    #[Test]
    public function redakteurOhneRechteErgibt403(): void
    {
        $this->meldeAn(2);
        $antwort = $this->hochladen($this->quelle('b.jpg'), 'b.jpg');
        self::assertSame(403, $antwort->getStatusCode());
        self::assertSame('Keine Berechtigung für diese Galerie.', $this->json($antwort)['message']);
    }

    #[Test]
    public function meldungInDerSpracheDesBenutzers(): void
    {
        $this->meldeAn(2, 'default');
        $antwort = $this->hochladen($this->quelle('b.jpg'), 'b.jpg');
        self::assertSame('No permission for this gallery.', $this->json($antwort)['message']);
    }

    #[Test]
    public function abgelegteDateiWirdEntferntWennDatensatzScheitert(): void
    {
        $this->meldeAn(1);
        $scheitert = $this->createMock(GalerieBearbeitung::class);
        $scheitert->method('bildAnlegen')->willThrowException(new GalerieBearbeitungFehler('abgelehnt'));
        $container = $this->getContainer();
        self::assertInstanceOf(SymfonyContainerInterface::class, $container);
        $container->set(GalerieBearbeitung::class, $scheitert);

        $antwort = $this->hochladen($this->quelle('c.jpg'), 'verwaist.jpg');
        self::assertSame(500, $antwort->getStatusCode());
        self::assertFileDoesNotExist(Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/verwaist.jpg');
    }

    #[Test]
    public function fehlenderSpeicherErgibtKonfigurationsmeldung(): void
    {
        $this->meldeAn(1);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['crgalerie']['uploadFolder'] = '99:/galerien/';
        $antwort = $this->hochladen($this->quelle('d.jpg'), 'd.jpg');
        self::assertSame(500, $antwort->getStatusCode());
        self::assertSame(
            'Der Upload-Ordner ist nicht verfügbar – bitte die Extension-Konfiguration (uploadFolder) prüfen.',
            $this->json($antwort)['message']
        );
    }

    #[Test]
    public function zuGrosseAnfrageOhneFelderErgibtGroessenmeldung(): void
    {
        $this->meldeAn(1);
        // Überschreitet die Anfrage post_max_size, kommen weder Formularfelder noch Dateien an.
        $request = new ServerRequest('https://typo3-dev.ddev.site/typo3/ajax/crgalerie/upload', 'POST');
        $antwort = $this->get(UploadAjaxController::class)->uploadAction($request);
        self::assertSame(400, $antwort->getStatusCode());
        $daten = $this->json($antwort);
        self::assertFalse($daten['success']);
        self::assertMatchesRegularExpression(
            '/^Die Datei ist nicht vollständig angekommen oder zu groß \(max\. \d+ MB\)\.$/',
            $daten['message']
        );
    }

    #[Test]
    public function benutzerOhneDateirechteErgibt403(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ModulRedakteurOhneDateirechte.csv');
        // Ordner liegen bereit, damit die Prüfung des Hinzufügen-Rechts (addFile) greift.
        $ordner = Environment::getPublicPath() . '/fileadmin/galerien/3-redakteur-galerie/';
        @mkdir($ordner, 0777, true);
        // Dateirechte und Dateifreigaben setzt der Core nur im Backend-Kontext auf den Speicher (StoragePermissionsAspect).
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://typo3-dev.ddev.site/typo3/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->meldeAn(3);
        $antwort = $this->hochladen($this->quelle('d.jpg'), 'ohne_recht.jpg', 3);
        self::assertSame(403, $antwort->getStatusCode());
        self::assertSame('Kein Schreibrecht im Galerie-Ordner – bitte Dateifreigabe prüfen.', $this->json($antwort)['message']);
        self::assertFileDoesNotExist($ordner . 'ohne_recht.jpg');
    }
    #[Test]
    public function redakteurMitRechtenLaedtAufSeitenGalerieHoch(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ModulRedakteurMitRechten.csv');
        // Die Dateifreigabe 1:/galerien/ muss existieren; den Galerie-Unterordner legt der Upload selbst an.
        @mkdir(Environment::getPublicPath() . '/fileadmin/galerien/', 0777, true);
        // Dateirechte und Dateifreigaben setzt der Core nur im Backend-Kontext auf den Speicher (StoragePermissionsAspect).
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://typo3-dev.ddev.site/typo3/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->meldeAn(4);

        $antwort = $this->hochladen($this->quelle('e.jpg'), 'Kapelle_innen.jpg', 4);

        $daten = $this->json($antwort);
        self::assertSame(200, $antwort->getStatusCode(), (string)($daten['message'] ?? ''));
        self::assertTrue($daten['success']);
        self::assertFileExists(Environment::getPublicPath() . '/fileadmin/galerien/4-redaktionsgalerie/Kapelle_innen.jpg');
        $bild = $this->getConnectionPool()->getConnectionForTable('tx_crgalerie_domain_model_bilder')
            ->select(['pid', 'galerie', 'titel', 'datei'], 'tx_crgalerie_domain_model_bilder', ['uid' => $daten['uid']])
            ->fetchAssociative();
        self::assertSame(['pid' => 20, 'galerie' => 4, 'titel' => 'Kapelle innen', 'datei' => 1], $bild);
        $referenz = $this->getConnectionPool()->getConnectionForTable('sys_file_reference')
            ->select(['pid', 'tablenames', 'fieldname', 'uid_local'], 'sys_file_reference', ['uid_foreign' => $daten['uid'], 'tablenames' => 'tx_crgalerie_domain_model_bilder'])
            ->fetchAssociative();
        self::assertIsArray($referenz);
        self::assertSame(20, $referenz['pid']);
        self::assertSame('datei', $referenz['fieldname']);
        $dateiName = $this->getConnectionPool()->getConnectionForTable('sys_file')
            ->select(['name'], 'sys_file', ['uid' => $referenz['uid_local']])->fetchOne();
        self::assertSame('Kapelle_innen.jpg', $dateiName);
    }
    #[Test]
    public function uploadErzeugtDieFrontendFassungenVorab(): void
    {
        $this->meldeAn(1);
        $antwort = $this->hochladen($this->quelle('vorab.jpg', 3000, 2000), 'Vorab.jpg');
        self::assertSame(200, $antwort->getStatusCode());
        $datei = (int)$this->getConnectionPool()->getConnectionForTable('sys_file')
            ->select(['uid'], 'sys_file', ['name' => 'Vorab.jpg'])->fetchOne();
        self::assertGreaterThan(0, $datei);
        $anweisungen = [];
        foreach ($this->getConnectionPool()->getConnectionForTable('sys_file_processedfile')
            ->select(['configuration'], 'sys_file_processedfile', ['original' => $datei])->fetchFirstColumn() as $konfiguration) {
            $werte = unserialize((string)$konfiguration, ['allowed_classes' => false]);
            $anweisungen[] = (string)($werte['maxWidth'] ?? '') . 'x' . (string)($werte['maxHeight'] ?? '');
        }
        // Vorschau-srcset und Lightbox-Fassung wie im Frontend (BildQuellenService mit Standardwerten)
        foreach (['400x', '800x', '1200x', '2560x2560'] as $erwartet) {
            self::assertContains($erwartet, $anweisungen);
        }
    }
}
