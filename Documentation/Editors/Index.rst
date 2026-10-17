..  _editors:

===========
For editors
===========

..  _editors-module:

The module "Galleries"
======================

All galleries are maintained in :guilabel:`Content > Galleries`. The start
view shows every gallery as a card with cover image, position, number of
images, category and visibility. Search, the filters :guilabel:`All`,
:guilabel:`Visible`, :guilabel:`Hidden` and the sort order help with many
galleries.

..  figure:: /Images/backend-galerien.jpg
    :alt: Start view of the module with gallery cards
    :class: with-border with-shadow

..  _editors-create:

Create a gallery
================

1.  Click :guilabel:`New gallery`.
2.  Fill in :guilabel:`Title`; optionally :guilabel:`Description`,
    :guilabel:`Photographer` (used as credit for images without their own
    author), :guilabel:`Date taken` and :guilabel:`Category`.
3.  Save and close. The gallery appears in the module.

..  _editors-upload:

Upload and sort images
======================

1.  Open the gallery and click :guilabel:`Upload images`.
2.  Drag images into the upload area or choose them from your computer. A
    progress bar is shown per file; files that cannot be processed are
    listed with the reason (e.g. unsupported format or too large).
3.  Back in the gallery, drag images to change their order – the website
    shows them in the same order.
4.  Click an image to edit its title and caption. A text like
    ``(c) Jane Doe`` in the title or caption is shown as credit.

..  figure:: /Images/backend-galerie-bilder.jpg
    :alt: Gallery view in the module with numbered image tiles
    :class: with-border with-shadow

    Images of a gallery: drag to sort, switch to hide, bin to delete.

Supported formats: JPG, PNG, WebP and HEIC (converted to JPG if the image
processor supports it). Images are reduced to the configured maximum size
on upload; location data (GPS) and other EXIF data are removed.

..  _editors-hide-delete:

Hide and delete
===============

*   The switch on an image or gallery hides it on the website without
    deleting it.
*   The bin deletes an image. Its file is removed as well if it is not
    used anywhere else.
*   :guilabel:`Preview` opens the gallery on the website (page set in
    ``previewPid``).

..  _editors-content-elements:

Show galleries on a page
========================

In the page module, add a content element from the tab
:guilabel:`Plugins`:

:guilabel:`Gallery`
    Shows one gallery. Choose it in the tab :guilabel:`Gallery`.

:guilabel:`Gallery overview`
    Shows all galleries – or only those of one category – as tiles. A click
    on a tile opens the gallery on the same page, with a link back to the
    overview.

Options in the tab :guilabel:`Display` (both elements):

:guilabel:`Layout`
    Mosaic in rows, grid, mosaic in columns or large cover image and grid,
    see :ref:`layouts`.

:guilabel:`Images shown at first (0 = all)`
    Default 12. Further images appear after a click on
    :guilabel:`Show all X photos`.

:guilabel:`Text below the images`
    None, caption, image title, or image title and caption.

:guilabel:`Order of the images`
    As in the module (ascending or reversed) or by title.

:guilabel:`Overview tiles` (only overview)
    Title below the image or on the image.

The header of the content element is shown above the gallery; the gallery
title gets the next heading level.
