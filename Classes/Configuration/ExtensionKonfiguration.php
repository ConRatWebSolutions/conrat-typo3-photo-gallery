<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Typisierter Zugriff auf die globale Extension-Konfiguration (Einstellungen > Extension-Konfiguration).
 */
final readonly class ExtensionKonfiguration
{
    public function __construct(private ExtensionConfiguration $extensionConfiguration) {}

    public function getStoragePid(): int
    {
        return $this->positiveZahl('storagePid');
    }

    public function getPreviewPid(): int
    {
        return $this->positiveZahl('previewPid');
    }

    public function getUploadFolder(): string
    {
        $wert = $this->text('uploadFolder');
        if (preg_match('#^\d+:/[^\0]*$#', $wert) !== 1 || str_contains($wert, '..')) {
            return '1:/galerien/';
        }
        return rtrim($wert, '/') . '/';
    }

    public function getMaxBildgroesse(): int
    {
        $wert = $this->positiveZahl('maxBildgroesse');
        return $wert >= 800 && $wert <= 6000 ? $wert : 2560;
    }

    private function text(string $schluessel): string
    {
        try {
            $wert = $this->extensionConfiguration->get('crgalerie', $schluessel);
        } catch (\Throwable) {
            return '';
        }
        return is_scalar($wert) ? trim((string)$wert) : '';
    }

    private function positiveZahl(string $schluessel): int
    {
        try {
            $wert = $this->extensionConfiguration->get('crgalerie', $schluessel);
        } catch (\Throwable) {
            return 0;
        }
        if (!is_scalar($wert) || !ctype_digit((string)$wert)) {
            return 0;
        }
        return (int)$wert;
    }
}
