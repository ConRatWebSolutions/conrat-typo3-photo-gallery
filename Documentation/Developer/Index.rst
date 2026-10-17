..  _developer:

=========
Developer
=========

..  _developer-templates:

Overriding templates
====================

..  code-block:: typoscript

    plugin.tx_crgalerie.view {
        templateRootPaths.20 = EXT:my_sitepackage/Resources/Private/Crgalerie/Templates/
        partialRootPaths.20 = EXT:my_sitepackage/Resources/Private/Crgalerie/Partials/
    }

The template of a gallery is :file:`Partials/Galerie/Bilder.html`, of the
overview :file:`Templates/Uebersicht/List.html`.

Variables of a gallery: ``galerie`` (gallery object), ``bilder`` (list with
``datei``, ``titel``, ``beschreibung``, ``credit``), ``showDescription``
(0–3), ``darstellung``, ``anzahlStart``, ``sizes``, ``sizesTitel``,
``cssEinbinden``, ``elementUid``, ``titelEbene`` (2–6). In the overview:
``galerien`` (with ``titel``, ``erstesBild``, ``anzahlBilder``) and
``kachelstil``.

..  _developer-viewhelpers:

ViewHelpers
===========

Namespace ``Conrat\Crgalerie\ViewHelpers``.

``<crg:bildQuellen datei="{…}" as="quellen">``
    Provides ``src``, ``srcset``, ``breite``, ``hoehe``, ``grossUrl``,
    ``grossBreite``, ``grossHoehe`` and ``verhaeltnis``. Arguments:
    ``breiten`` (thumbnail widths, default ``{0: 400, 1: 800, 2: 1200}``;
    ``src`` is the middle one) and ``gross`` (longest side of the lightbox
    variant, default 2560; ``0`` = no own variant).

``{crg:zeilenSizes(verhaeltnis: …, zeilenhoehe: 260, zeilenhoeheMobil: 120)}``
    ``sizes`` for one image in the layout "Mosaic in rows" from its aspect
    ratio and the row heights (pixel values).

..  _developer-markup:

Markup contract for own gallery templates
=========================================

:file:`galerie.js` (lightbox and "Show all") and the CSS rely on these
hooks – keep them when overriding :file:`Bilder.html`:

*   ``<f:render partial="Galerie/Assets" arguments="{cssEinbinden: cssEinbinden}" />``
    includes CSS, PhotoSwipe and :file:`galerie.js` (``lightbox: 0`` omits
    lightbox CSS and script, as in the overview).
*   ``data-crg-galerie`` on the container of a gallery (one lightbox per
    container).
*   ``data-crg-text-dialog``, ``-close``, ``-zoom``, ``-previous``,
    ``-next``, ``-error`` on the container: translated lightbox texts.
*   ``.crg__liste`` with ``data-crg-anzahl-start="{anzahlStart}"``,
    containing one ``.crg__bild`` per image.
*   ``.crg__titel`` in the container: its text names the lightbox dialog.
*   ``a.crg__link`` with ``href="{quellen.grossUrl}"``,
    ``data-pswp-width`` and ``data-pswp-height``; ``data-cropped="true"``
    if the thumbnail is cropped.
*   ``[data-crg-lightboxtext]`` (with ``hidden``) in the same
    ``.crg__figur`` – shown as caption in the lightbox.
*   ``[data-crg-mehr]`` (with ``hidden``) as the "Show all X photos" button.
*   Images after ``anzahlStart`` delivered with ``hidden``, plus the
    ``<noscript>`` stylesheet :file:`galerie-noscript.css`. Do not use a
    ``<style>`` element: TYPO3's Content Security Policy blocks it.

..  _developer-tests:

Development and tests
=====================

..  code-block:: bash

    composer install
    .Build/bin/phpstan analyse -c Build/phpstan.neon
    typo3DatabaseDriver=pdo_sqlite .Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml

The functional tests need ImageMagick or GraphicsMagick. Contributions are
welcome, see :file:`CONTRIBUTING.md` in the repository.
