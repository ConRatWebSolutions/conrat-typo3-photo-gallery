..  _layouts:

=======
Layouts
=======

Every :guilabel:`Gallery` and :guilabel:`Gallery overview` element has its
own layout (tab :guilabel:`Display` > :guilabel:`Layout`). All layouts use
responsive image sizes, lazy loading and the same lightbox. The column count
and row height can be changed with CSS variables, see
:ref:`configuration-css`. All four layouts can be tried in the
`live demo <https://demotypo14.conrat-projects.de/fotogalerie>`__.

..  _layout-rows:

Mosaic in rows
==============

..  figure:: /Images/layout-mosaik-zeilen.jpg
    :alt: Layout "Mosaic in rows"
    :class: with-border with-shadow

Images stand in rows of equal height and fill the full width. Every image
stays complete – portrait and landscape formats are not cropped. This is the
default and fits almost any selection of images.

Settings
    Row height ``--crg-zeilenhoehe`` (from 600 px, default 220px) and
    ``--crg-zeilenhoehe-mobil`` (default 100px).

Limitations
    The last row is not stretched and may end before the right edge.

..  _layout-grid:

Grid
====

..  figure:: /Images/layout-raster.jpg
    :alt: Layout "Grid"
    :class: with-border with-shadow

All thumbnails have the same format and stand in a calm, even grid. The
thumbnail is cropped for this; the lightbox shows the whole image. Good for
series and pages with a uniform look.

Settings
    Columns ``--crg-spalten`` (desktop, default 4), ``--crg-spalten-tablet``
    (default 3), ``--crg-spalten-mobil`` (default 2).

Limitations
    Thumbnails are cropped at the center.

..  _layout-columns:

Mosaic in columns
=================

..  figure:: /Images/layout-mosaik-spalten.jpg
    :alt: Layout "Mosaic in columns"
    :class: with-border with-shadow

Images flow in columns, each in its own aspect ratio. Portrait images look
especially good here.

Settings
    Columns as for the grid.

Limitations
    The order runs column by column from top to bottom. Lightbox, keyboard
    and screen readers follow the order of the HTML, which differs from the
    visual arrangement.

..  _layout-cover:

Large cover image and grid
==========================

..  figure:: /Images/layout-titelbild.jpg
    :alt: Layout "Large cover image and grid"
    :class: with-border with-shadow

The first image is shown large as cover image, the others follow in a grid.
This gives a gallery a clear start, e.g. for an event or a project.

Settings
    Columns as for the grid. The cover image is the first image in the order
    of the module – drag the desired image to position 1.

Limitations
    The cover image and the grid tiles are cropped.

..  _layout-overview:

Gallery overview
================

..  figure:: /Images/uebersicht.jpg
    :alt: Gallery overview with tiles
    :class: with-border with-shadow

The :guilabel:`Gallery overview` shows one tile per gallery with its first
image, title and number of photos. The overview does not load the lightbox.
A click opens the gallery on the same page in the layout chosen in the
element.
