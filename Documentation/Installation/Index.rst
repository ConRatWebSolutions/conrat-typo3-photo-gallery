..  _installation:

============
Installation
============

..  _requirements:

Requirements
============

*   TYPO3 14.3
*   PHP 8.2 – 8.5
*   ImageMagick or GraphicsMagick enabled in the Install Tool
    (:guilabel:`Admin Tools > Settings > Configure Installation-Wide Options > GFX`).
    HEIC upload needs a build with HEIC support.

..  _install-composer:

Install with Composer
=====================

..  code-block:: bash

    composer require conratwebsolutions/crgalerie
    vendor/bin/typo3 extension:setup

``extension:setup`` creates the database tables. In a non-Composer
installation, install the extension from the TYPO3 Extension Repository
in :guilabel:`Admin Tools > Extensions`.

..  _install-site-set:

Add the site set
================

Add the site set :guilabel:`ConRat Photo Gallery` (``conrat/crgalerie``) to
your site, either in :guilabel:`Site Management > Sites` or in
:file:`config/sites/<site>/config.yaml`:

..  code-block:: yaml
    :caption: config/sites/main/config.yaml

    dependencies:
      - conrat/crgalerie

The set provides the TypoScript of both content elements. No static template
is needed.

..  _install-configuration:

Extension configuration
=======================

In :guilabel:`Admin Tools > Settings > Extension Configuration > crgalerie`:

``storagePid``
    Folder (sys folder) in which the module stores new galleries and images.
    Create a sys folder, e.g. "Galleries", and enter its page ID.

``previewPid``
    Page with a :guilabel:`Gallery overview` element. Used for the preview
    links in the module.

``uploadFolder``
    FAL folder for uploaded images, default ``1:/galerien/``. One subfolder
    ``<uid>-<title>`` is created per gallery.

``maxBildgroesse``
    Longest side in pixels to which images are reduced on upload, default
    2560.

Clear all caches afterwards:

..  code-block:: bash

    vendor/bin/typo3 cache:flush

..  _install-first-gallery:

First gallery
=============

1.  Open :guilabel:`Content > Galleries`, create a gallery and upload images,
    see :ref:`editors`.
2.  On a page, add the content element :guilabel:`Gallery` (tab
    :guilabel:`Plugins`) and choose the gallery.
3.  Open the page in the frontend.

..  _install-upgrade:

Upgrading from 2.x
==================

Version 2 was only used in projects of ConRat. To upgrade:

1.  Run the upgrade wizard that converts the old plugin into the new content
    elements, then clear the caches:

    ..  code-block:: bash

        vendor/bin/typo3 upgrade:run crgalerieCTypeMigration
        vendor/bin/typo3 cache:flush

2.  Set ``storagePid`` and ``previewPid``. Galleries on the root level
    (pid 0) can only be edited by admins; move them into the sys folder.
3.  Add the site set and set site settings if needed (see
    :ref:`configuration-site-settings`).
4.  Adapt own template overrides; templates and variables are new.
5.  The old folders ``fileadmin/crgalerie/<uid>/raw/`` and ``/thumbnails/``
    are no longer used and can be removed after a backup.
6.  Editors now need table, database and file permissions, see
    :ref:`configuration-permissions`.
