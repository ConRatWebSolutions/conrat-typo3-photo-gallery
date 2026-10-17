..  _troubleshooting:

===============
Troubleshooting
===============

..  _troubleshooting-not-shown:

The gallery is not shown
========================

*   The site set ``conrat/crgalerie`` is missing in the site configuration –
    the content elements then fail with an error that the template cannot be
    found. Add the set, then clear the caches.
*   The gallery or all its images are hidden in the module.
*   The overview is limited to a category that the gallery does not have.
*   A gallery opened from an overview whose ID does not exist or is hidden
    returns "page not found" (404) on purpose.

..  _troubleshooting-images:

Images are missing or broken
============================

*   Image processing is not configured: check
    :guilabel:`Admin Tools > Environment > Image Processing`. Without
    ImageMagick or GraphicsMagick no thumbnails can be rendered.
*   Files were deleted or moved outside TYPO3: images whose file is missing
    are skipped and do not count; run the file indexing scheduler task or
    re-upload them.
*   The first page view after many uploads is slow: pre-generate the image
    variants with ``vendor/bin/typo3 crgalerie:vorwaermen``.
*   Thumbnails look blurry after changing columns or row heights: adapt the
    ``sizes`` (see :ref:`configuration-css`).

..  _troubleshooting-upload:

Upload fails
============

*   "The file did not arrive completely or is too large": the file exceeds ``upload_max_filesize`` or
    ``post_max_size`` of PHP. "The image is too large (more than 100
    megapixels)": export a smaller version.
*   "This file format is not supported": only JPG, PNG, WebP and HEIC are
    accepted. "This server cannot convert HEIC images": the image processor
    has no HEIC support.
*   "Image processing is disabled in TYPO3": enable ImageMagick or
    GraphicsMagick in the Install Tool.
*   "No permission for this gallery" or "No write access to the gallery
    folder": check the editor permissions (:ref:`configuration-permissions`),
    especially the DB mount and the file mount on the upload folder.
*   "The upload folder is not available": ``uploadFolder`` in the extension
    configuration points to a storage or folder that does not exist.

..  _troubleshooting-js:

The lightbox or "Show all" does not work
========================================

*   Check the browser console. The gallery loads :file:`galerie.js` as an ES
    module via the asset collector; the page template must render the
    collected JavaScript (standard in TYPO3).
*   An own :file:`Bilder.html` misses one of the hooks of the
    :ref:`markup contract <developer-markup>`.
*   The lightbox is hidden behind a cookie banner or sticky header: set
    ``--crg-lightbox-z`` higher than that layer.

..  _troubleshooting-layout:

The layout looks wrong
======================

*   Project CSS overrides the gallery: global rules with two or more classes
    or IDs can win against the gallery CSS. Inspect the element and use a
    more specific selector for your rule, or set the CSS variables instead.
*   ``crgalerie.frontend.css`` is ``false`` but no own CSS is loaded.
*   Caches: after changing templates or settings, clear all caches.
