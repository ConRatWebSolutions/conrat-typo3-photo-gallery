<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\ViewHelpers;

use Conrat\Crgalerie\Service\BildQuellenService;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Stellt Vorschau-srcset und Lightbox-Fassung eines Bildes als Variable bereit:
 * <crg:bildQuellen datei="{bild.datei}" as="quellen">{quellen.src}</crg:bildQuellen>
 */
final class BildQuellenViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(private readonly BildQuellenService $service) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('datei', 'object', 'FileReference oder Datei', true);
        $this->registerArgument('breiten', 'array', 'Vorschaubreiten in Pixeln', false, [400, 800, 1200]);
        $this->registerArgument('gross', 'int', 'Längste Seite der Lightbox-Fassung; 0 = keine eigene Fassung (grossUrl = src)', false, 2560);
        $this->registerArgument('as', 'string', 'Name der Variablen', false, 'quellen');
    }

    public function render(): string
    {
        $datei = $this->arguments['datei'];
        if (!$datei instanceof FileInterface && !$datei instanceof FileReference) {
            return '';
        }
        /** @var list<int> $breiten */
        $breiten = array_values(array_map('intval', (array)$this->arguments['breiten']));
        $quellen = $this->service->fuer($datei, $breiten, (int)$this->arguments['gross']);
        if ($quellen === null) {
            return '';
        }
        $variablen = $this->renderingContext->getVariableProvider();
        $name = (string)$this->arguments['as'];
        $vorhanden = $variablen->exists($name);
        $vorher = $vorhanden ? $variablen->get($name) : null;
        if ($vorhanden) {
            $variablen->remove($name);
        }
        $variablen->add($name, $quellen);
        try {
            $ausgabe = (string)$this->renderChildren();
        } finally {
            $variablen->remove($name);
            if ($vorhanden) {
                $variablen->add($name, $vorher);
            }
        }
        return $ausgabe;
    }
}
