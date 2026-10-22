..  _configuration:

=============
Configuration
=============

..  _configuration-extension:

Extension configuration
=======================

See :ref:`install-configuration` (``storagePid``, ``previewPid``,
``uploadFolder``, ``maxBildgroesse``).

..  _configuration-site-settings:

Site settings
=============

Set in :guilabel:`Site Management > Settings` or in
:file:`config/sites/<site>/settings.yaml`:

..  code-block:: yaml
    :caption: config/sites/main/settings.yaml

    crgalerie:
      pageTitle: '{galerie} – Photo gallery'
      metadata:
        creditField: creator
        licenseField: copyright
      frontend:
        css: true

``crgalerie.pageTitle``
    Page title when a gallery is opened from the overview. ``{galerie}`` is
    replaced by the gallery title. Empty: the page title stays unchanged.

``crgalerie.metadata.creditField``
    Column in ``sys_file_metadata`` that holds the author, e.g. ``creator``
    (requires EXT:filemetadata). Empty: only ``(c) …`` in title or caption,
    then the photographer of the gallery.

``crgalerie.metadata.licenseField``
    Column in ``sys_file_metadata`` that holds the license, e.g.
    ``copyright``. Empty: no license.

``crgalerie.frontend.photoCount``
    ``false`` hides the line “21 photos” above galleries by default (since
    3.1.0). Content elements can override it in the tab :guilabel:`Display`.

``crgalerie.frontend.css``
    ``false`` disables the bundled gallery CSS (the basic lightbox CSS stays).
    Your CSS must then contain at least
    ``.crg__bild[hidden] { display: none; }``.

..  _configuration-css:

CSS variables
=============

The bundled CSS only reads CSS variables with a default
(``var(--crg-…, default)``) and never sets them. Your values always win – on
``:root`` for the whole page or on ``.crg`` (gallery) or
``.crg-uebersicht`` (overview), regardless of the loading order:

..  code-block:: css

    :root {
        --crg-abstand: 12px;            /* gap between images, default 8px */
        --crg-radius: 0;                /* corner radius, default 4px */
        --crg-akzent: #b0002a;          /* button and focus outline, default currentColor */
        --crg-text-gedaempft: #555;     /* secondary texts, default #666 */
        --crg-zeilenhoehe: 260px;       /* mosaic in rows from 600 px, default 220px */
        --crg-zeilenhoehe-mobil: 120px; /* below 600 px, default 100px */
    }
    .crg {
        --crg-spalten: 5;               /* from 1024 px, default 4 (overview 3) */
        --crg-spalten-tablet: 3;        /* 600–1023 px, default 3 (overview 2) */
        --crg-spalten-mobil: 1;         /* below 600 px, default 2 (overview 1) */
    }
    .pswp { --crg-lightbox-hintergrund: #111; }
    :root { --crg-lightbox-z: 10000; }  /* lightbox layer, default 2147483000 */

The column count applies to grid, cover image and mosaic in columns and to
the overview tiles.

..  important::

    The ``sizes`` attributes of the thumbnails are tuned to the default
    columns and row heights. If you change them, adapt ``sizes`` in your own
    :file:`Partials/Galerie/Bilder.html` (see :ref:`developer-templates`),
    otherwise images become blurry or unnecessarily large.

Lists and links of gallery and overview are protected against typical
project rules (bullets, indentation, underlined links,
``* { margin: 0 auto }``) with a doubled class. To change these properties
deliberately, use at least two classes as well, e.g.
``.crg-uebersicht .crg-uebersicht__kachel { color: … }``.

..  _configuration-texts:

Texts and translations
======================

All texts are in :file:`Resources/Private/Language/` – English as source
language, German included. The frontend follows the site language, the
module the editor's language. For further languages add a
:file:`<language>.locallang.xlf` with the same keys.

Change single frontend texts with TypoScript:

..  code-block:: typoscript

    plugin.tx_crgalerie._LOCAL_LANG.en {
        frontend.showAll = View all %d pictures
    }

Or override whole files, e.g. in :file:`ext_localconf.php` of your site
package:

..  code-block:: php

    $GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides']['EXT:crgalerie/Resources/Private/Language/locallang.xlf'][]
        = 'EXT:my_sitepackage/Resources/Private/Language/Overrides/crgalerie.xlf';

..  _configuration-permissions:

Permissions for editors
=======================

The module writes through the DataHandler and the FAL API. The editor group
needs:

*   Module access :guilabel:`Content > Galleries` (``web_crgalerie``)
*   Tables (listing and modify): gallery
    (``tx_crgalerie_domain_model_galerie``), gallery image
    (``tx_crgalerie_domain_model_bilder``), file reference
    (``sys_file_reference``), file (``sys_file``), file metadata
    (``sys_file_metadata``)
*   Allowed excludefields: "Hidden" of gallery and gallery image
*   DB mount on the gallery folder (``storagePid``) with "Edit content"
    permission
*   File mount on the upload folder (``uploadFolder``) with the file
    permissions read folder, add folder, read file, add file, write file,
    delete file
*   Image processing enabled in the Install Tool

Galleries on the root level (pid 0) can only be edited by admins.

..  _configuration-prewarm:

Pre-generating image variants
=============================

TYPO3 renders three thumbnail sizes (400/800/1200 px) and one lightbox
variant (longest side 2560 px) per image. Uploads in the module do this
immediately. For existing images, e.g. after clearing ``_processed_``, run:

..  code-block:: bash

    vendor/bin/typo3 crgalerie:vorwaermen             # all visible images
    vendor/bin/typo3 crgalerie:vorwaermen --galerie=7 # one gallery only
