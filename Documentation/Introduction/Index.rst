..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
================

The ConRat Photo Gallery keeps all image galleries of a website in one place.
Editors create galleries in the backend module :guilabel:`Content > Galleries`,
upload images by drag and drop, put them in order and hide single images or
whole galleries. Galleries are not tied to pages: content elements show a
single gallery or an overview of all galleries on any page.

..  figure:: /Images/backend-galerien.jpg
    :alt: Backend module "Galleries" with four galleries as cards
    :class: with-border with-shadow

    The backend module lists all galleries with cover image, number of images and visibility.

..  _features:

Features
========

Backend module
    Create, edit, hide and delete galleries; upload JPG, PNG, WebP and HEIC
    by drag and drop with progress display; sort images by dragging; hide or
    delete single images; preview link to the website.

Safe upload
    Files are stored through the TYPO3 File Abstraction Layer (FAL). The
    format is checked by file content, images are rotated according to EXIF,
    EXIF and GPS data are removed, images are reduced to a configurable
    maximum size, HEIC is converted to JPG, images above 100 megapixels are
    rejected.

Two content elements
    :guilabel:`Gallery` shows one gallery, :guilabel:`Gallery overview` shows
    all galleries (or those of one category) as tiles and opens a gallery on
    the same page.

Four layouts
    Mosaic in rows, grid, mosaic in columns and large cover image with grid,
    see :ref:`layouts`.

Responsive images
    ``srcset``/``sizes`` per layout, lazy loading, width and height on every
    image (no layout shift). Image variants are rendered by TYPO3 itself and
    prepared during upload. Images behind :guilabel:`Show all X photos` are
    not loaded until the button is used.

Accessible lightbox
    PhotoSwipe 5: swipe, arrow keys, ESC, zoom, counter, title, caption and
    credit. Named modal dialog, focus moves into the dialog and back to the
    opened image.

Credits
    Author and license from the file metadata (configurable fields), from a
    ``(c) Name`` in title or caption, or the photographer of the gallery.

No dependencies
    No jQuery, no external services, no build step. Works with TYPO3's
    Content Security Policy and without JavaScript. English and German
    included.

..  _screenshots:

Screenshots
===========

..  figure:: /Images/demo-seite.jpg
    :alt: Demo page with the layout "Mosaic in rows"
    :class: with-border with-shadow

    A gallery in the layout "Mosaic in rows" (live demo with its own site package).

..  figure:: /Images/grossansicht.jpg
    :alt: Lightbox with counter, title and credit
    :class: with-border with-shadow

    The lightbox with counter, title and credit.

..  figure:: /Images/handy-mosaik-spalten.jpg
    :alt: Gallery on a phone
    :class: with-border with-shadow
    :width: 300px

    On a phone: two columns, image sizes chosen for the screen.

..  _limitations:

Limitations
===========

*   **One gallery collection per installation.** Galleries and categories
    are not tied to pages and the storage folder is global. In installations
    with several sites, module and content elements show the galleries of all
    sites; separate them with categories.
*   **No translation of galleries.** Image titles and captions come from the
    file metadata (``sys_file_metadata``) or from the image record in the
    default language.
*   **Workspaces:** the module always shows the live state.
*   A gallery deleted outside the module leaves its files in the upload folder.

..  _credits:

Credits
=======

Developed by `ConRat WebSolutions GmbH <https://somesolutions.de/typo3-bildergalerie/>`__.
Bundles `PhotoSwipe <https://photoswipe.com>`__ 5.4.4 by Dmytro Semenov
(MIT license). The images of the demo were generated with ConRat AI.
