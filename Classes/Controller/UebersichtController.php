<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Controller;

use Conrat\Crgalerie\Domain\Model\Galerie;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Frontend\Controller\ErrorController;

/**
 * CType crgalerie_uebersicht: Kacheln aller Galerien (optional einer Kategorie), Einzelansicht per URL.
 */
class UebersichtController extends AbstractGalerieController
{
    public function listAction(): ResponseInterface
    {
        $this->markiereCache();
        $this->view->assignMultiple([
            'galerien' => $this->galerieRepository->findSichtbare($this->kategorie()),
            'kachelstil' => in_array($this->settings['kachelstil'] ?? '', ['unten', 'ueber'], true)
                ? (string)$this->settings['kachelstil']
                : 'unten',
            'cssEinbinden' => $this->cssEinbinden(),
        ]);
        return $this->htmlResponse();
    }

    /**
     * $galerie kommt als String, damit manipulierte Werte („abc“, „1 OR 1=1“) sauber 404 ergeben
     * statt eines Extbase-Mapping-Fehlers.
     */
    public function showAction(string $galerie = ''): ResponseInterface
    {
        $this->markiereCache();
        $uid = ctype_digit($galerie) ? (int)$galerie : 0;
        $gefunden = $this->galerieRepository->findEineSichtbare($uid);
        if ($gefunden === null || !$this->passtZurKategorie($gefunden)) {
            throw new PropagateResponseException(
                $this->getErrorController()->pageNotFoundAction($this->request, 'Gallery not found'),
                1760530001
            );
        }
        $this->weiseGalerieZu($gefunden);
        $this->view->assign('zurueckLink', true);
        return $this->htmlResponse();
    }

    private function kategorie(): ?int
    {
        $kategorie = (int)($this->settings['category'] ?? 0);
        return $kategorie > 0 ? $kategorie : null;
    }

    private function passtZurKategorie(Galerie $galerie): bool
    {
        $kategorie = $this->kategorie();
        return $kategorie === null || $galerie->getKategorie()?->getUid() === $kategorie;
    }

    private function getErrorController(): ErrorController
    {
        return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(ErrorController::class);
    }
}
