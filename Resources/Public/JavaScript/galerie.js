/**
 * crgalerie – Großansicht (PhotoSwipe) und „Alle X Fotos anzeigen“.
 * Ohne JavaScript – oder wenn PhotoSwipe nicht geladen werden kann – bleiben die Bilder nutzbar:
 * Links öffnen das große Bild.
 */
/**
 * Texte kommen übersetzt aus dem Template (data-crg-text-* an der Galerie); englische Werte nur als Rückfall für
 * überschriebene Templates ohne diese Attribute.
 */
function texte(galerie) {
    const d = galerie.dataset;
    return {
        dialog: d.crgTextDialog || 'Image viewer',
        photoswipe: {
            closeTitle: d.crgTextClose || 'Close (Esc)',
            zoomTitle: d.crgTextZoom || 'Zoom',
            arrowPrevTitle: d.crgTextPrevious || 'Previous image',
            arrowNextTitle: d.crgTextNext || 'Next image',
            errorMsg: d.crgTextError || 'The image could not be loaded.',
            indexIndicatorSep: ' / ',
        },
    };
}

function alleAnzeigen(galerie) {
    const knopf = galerie.querySelector('[data-crg-mehr]');
    const liste = galerie.querySelector('.crg__liste');
    if (!knopf || !liste) {
        return;
    }
    // Der Server rendert die Einträge ab „anzahlStart“ mit hidden (kein Vorabladen der Vorschaubilder).
    const versteckte = () => Array.from(liste.querySelectorAll(':scope > .crg__bild[hidden]'));
    if (versteckte().length === 0) {
        return;
    }
    knopf.hidden = false;
    knopf.addEventListener('click', () => {
        const eintraege = versteckte();
        eintraege.forEach((eintrag) => { eintrag.hidden = false; });
        knopf.hidden = true;
        eintraege[0]?.querySelector('a')?.focus();
    });
}

function lightbox(PhotoSwipeLightbox, galerie) {
    const liste = galerie.querySelector('.crg__liste');
    if (!liste) {
        return;
    }
    const text = texte(galerie);
    const box = new PhotoSwipeLightbox({
        gallery: liste,
        children: 'a.crg__link',
        pswpModule: () => import('../Vendor/photoswipe/photoswipe.esm.min.js'),
        bgOpacity: 1,
        showHideAnimationType: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'none' : 'zoom',
        ...text.photoswipe,
    });
    // Barrierearm: Dialog benennen und als modal kennzeichnen (PhotoSwipe setzt nur role="dialog"). Öffnet man per
    // Maus oder Tippen, setzt PhotoSwipe den Fokus nicht in den Dialog und gibt ihn beim Schließen auch nicht
    // zurück – beides übernimmt galerie.js. Rückgabe an den Link, mit dem geöffnet wurde (Safari fokussiert Links
    // beim Klicken nicht, deshalb wird er beim Klick gemerkt statt document.activeElement).
    const titel = galerie.querySelector('.crg__titel')?.textContent.trim();
    let oeffner = null;
    let dialog = null;
    liste.addEventListener('click', (ereignis) => {
        oeffner = ereignis.target.closest?.('a.crg__link') ?? null;
    }, true);
    box.on('bindEvents', () => {
        dialog = box.pswp.element;
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-label', titel ? `${text.dialog}: ${titel}` : text.dialog);
        if (!dialog.contains(document.activeElement)) {
            dialog.focus({ preventScroll: true });
        }
    });
    box.on('destroy', () => {
        const aktiv = document.activeElement;
        const fokusVerloren = !aktiv || aktiv === document.body || (dialog && dialog.contains(aktiv));
        if (oeffner && oeffner.isConnected && fokusVerloren) {
            oeffner.focus({ preventScroll: true });
        }
        oeffner = null;
        dialog = null;
    });
    // Versteckte Vorschaubilder (z. B. vor „Alle anzeigen“) haben keine Fläche: dann ausblenden statt zur Ecke zoomen
    box.addFilter('thumbBounds', (bounds) => (bounds && bounds.w ? bounds : undefined));
    box.on('uiRegister', () => {
        box.pswp.ui.registerElement({
            name: 'crg-bildtext',
            className: 'crg-pswp-bildtext',
            appendTo: 'root',
            order: 9,
            isButton: false,
            onInit: (element, pswp) => {
                element.setAttribute('aria-live', 'polite');
                pswp.on('change', () => {
                    element.replaceChildren();
                    const quelle = pswp.currSlide?.data?.element?.closest('.crg__figur')?.querySelector('[data-crg-lightboxtext]');
                    if (quelle && quelle.children.length) {
                        const kopie = quelle.cloneNode(true);
                        kopie.hidden = false;
                        kopie.removeAttribute('data-crg-lightboxtext');
                        element.append(...kopie.childNodes);
                    }
                });
            },
        });
    });
    box.init();
}

const galerien = Array.from(document.querySelectorAll('[data-crg-galerie]'));
// „Alle anzeigen“ zuerst, damit es auch funktioniert, wenn die Lightbox nicht geladen werden kann
galerien.forEach(alleAnzeigen);
if (galerien.length) {
    try {
        const { default: PhotoSwipeLightbox } = await import('../Vendor/photoswipe/photoswipe-lightbox.esm.min.js');
        galerien.forEach((galerie) => lightbox(PhotoSwipeLightbox, galerie));
    } catch (fehler) {
        console.warn('crgalerie: Lightbox konnte nicht geladen werden, Links öffnen das große Bild.', fehler);
    }
}
