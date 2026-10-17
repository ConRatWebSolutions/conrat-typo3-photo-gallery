<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * CType crgalerie_galerie: genau eine, im Element ausgewählte Galerie.
 */
class GalerieController extends AbstractGalerieController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function showAction(): ResponseInterface
    {
        $this->markiereCache();
        $uid = (int)($this->settings['galerie'] ?? 0);
        $galerie = $this->galerieRepository->findEineSichtbare($uid);
        if ($galerie === null) {
            // Ausgeblendete Galerie soll nicht die ganze Seite unerreichbar machen
            $this->logger?->info(sprintf('crgalerie: Galerie %d ist nicht sichtbar oder existiert nicht.', $uid));
            return $this->htmlResponse('');
        }
        $this->weiseGalerieZu($galerie);
        return $this->htmlResponse();
    }
}
