/**
 * crgalerie – Galerie-Verwaltung im Backend-Modul
 * - Übersicht: Suche, Filter (sichtbar/ausgeblendet) und Sortierung
 * - Galerie: Bilder per Maus sortieren, erst mit "Reihenfolge speichern" übernehmen
 * Wird als ES-Modul geladen (@conrat/crgalerie/module.js).
 */
import Sortable from 'sortablejs';

(function () {
    'use strict';

    // Sicherheitsabfrage (z. B. Bild löschen)
    document.querySelectorAll('[data-crg-confirm]').forEach(function (el) {
        if (!el.getAttribute('data-crg-confirm')) { return; }
        el.addEventListener('click', function (e) {
            if (!window.confirm(el.getAttribute('data-crg-confirm'))) { e.preventDefault(); }
        });
    });

    // Übersicht: Suche + Filter
    var overview = document.querySelector('[data-crg-overview]');
    if (overview) {
        var search = overview.querySelector('[data-crg-search]');
        var cards = Array.prototype.slice.call(overview.querySelectorAll('[data-crg-card]'));
        var empty = overview.querySelector('[data-crg-empty]');
        var filter = 'all';

        var apply = function () {
            var term = (search.value || '').trim().toLowerCase();
            var shown = 0;
            cards.forEach(function (card) {
                var match = (!term || card.dataset.title.indexOf(term) !== -1)
                    && (filter === 'all' || card.dataset.state === filter);
                card.hidden = !match;
                if (match) { shown++; }
            });
            empty.hidden = shown !== 0;
        };

        var cardGrid = overview.querySelector('[data-crg-grid]');

        // Galerien per Maus sortieren – nur sinnvoll in der Website-Reihenfolge
        var galSavebar = overview.querySelector('[data-crg-savebar]');
        var galSortingField = document.getElementById('crg-galsorting');
        var dragHint = overview.querySelector('[data-crg-draghint]');
        var galInitial = cards.map(function (c) { return c.dataset.uid; });
        var galDirty = false;

        var galRefresh = function () {
            var order = Array.prototype.slice.call(cardGrid.querySelectorAll('[data-crg-card]'))
                .map(function (c) { return c.dataset.uid; });
            galDirty = order.join(',') !== galInitial.join(',');
            galSortingField.value = order.join(',');
            galSavebar.hidden = !galDirty;
            sortSelect.disabled = galDirty;
        };

        var galSortable = Sortable.create(cardGrid, {
            draggable: '[data-crg-card]',
            filter: '.btn',
            preventOnFilter: false,
            forceFallback: true,
            fallbackOnBody: false,
            fallbackTolerance: 4,
            animation: 160,
            disabled: true,
            ghostClass: 'is-ghost',
            dragClass: 'is-drag-clone',
            onStart: function () { cardGrid.classList.add('is-sorting'); },
            onEnd: function () {
                cardGrid.classList.remove('is-sorting');
                galRefresh();
            }
        });

        var updateDragMode = function (mode) {
            var on = mode === 'web';
            galSortable.option('disabled', !on);
            cardGrid.classList.toggle('is-draggable', on);
            dragHint.hidden = !on;
        };

        overview.querySelector('[data-crg-reset]').addEventListener('click', function () {
            var byUid = {};
            cards.forEach(function (c) { byUid[c.dataset.uid] = c; });
            galInitial.forEach(function (uid) { cardGrid.appendChild(byUid[uid]); });
            galRefresh();
        });
        document.getElementById('crg-galsortform').addEventListener('submit', function () { galDirty = false; });
        window.addEventListener('beforeunload', function (e) {
            if (galDirty) { e.preventDefault(); e.returnValue = ''; }
        });

        // Sortierung (Auswahl wird pro Browser gemerkt)
        var sortSelect = overview.querySelector('[data-crg-sort]');
        var sorters = {
            // Reihenfolge auf der Website: Feld "Sortierung" der Galerie
            'web': function (a, b) {
                return a.dataset.sortierung - b.dataset.sortierung
                    || a.dataset.title.localeCompare(b.dataset.title, 'de');
            },
            'new': function (a, b) { return b.dataset.crdate - a.dataset.crdate || b.dataset.uid - a.dataset.uid; },
            'old': function (a, b) { return a.dataset.crdate - b.dataset.crdate || a.dataset.uid - b.dataset.uid; },
            'az': function (a, b) { return a.dataset.title.localeCompare(b.dataset.title, 'de'); },
            'za': function (a, b) { return b.dataset.title.localeCompare(a.dataset.title, 'de'); }
        };
        var sortCards = function (mode) {
            if (!sorters[mode]) { mode = 'new'; }
            sortSelect.value = mode;
            cards.slice().sort(sorters[mode]).forEach(function (card) { cardGrid.appendChild(card); });
            try { window.localStorage.setItem('crgalerie.sort', mode); } catch (e) { /* ohne Speicher */ }
            updateDragMode(mode);
            galInitial = cards.slice().sort(sorters[mode]).map(function (c) { return c.dataset.uid; });
            galRefresh();
        };
        var savedSort = 'new';
        try { savedSort = window.localStorage.getItem('crgalerie.sort') || 'new'; } catch (e) { /* ohne Speicher */ }
        sortCards(savedSort);
        sortSelect.addEventListener('change', function () { sortCards(sortSelect.value); });

        search.addEventListener('input', apply);
        overview.querySelectorAll('[data-crg-filter]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                overview.querySelectorAll('[data-crg-filter]').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                filter = btn.dataset.crgFilter;
                apply();
            });
        });
    }

    // Galerie: Sortieren per Maus (SortableJS im Fallback-Modus: keine nativen
    // Browser-Zugbilder, dadurch keine mitgezogenen Texte oder Markierungen)
    var grid = document.querySelector('[data-crg-sortable]');
    if (grid) {
        var savebar = document.querySelector('[data-crg-savebar]');
        var sortingField = document.getElementById('crg-sorting');
        var items = function () { return Array.prototype.slice.call(grid.querySelectorAll('[data-uid]')); };
        var initial = items().map(function (el) { return el.dataset.uid; });
        var dirty = false;

        var refresh = function () {
            var order = items().map(function (el, i) {
                var pos = el.querySelector('[data-crg-pos]');
                if (pos) { pos.textContent = i + 1; }
                return el.dataset.uid;
            });
            dirty = order.join(',') !== initial.join(',');
            sortingField.value = order.join(',');
            savebar.hidden = !dirty;
        };

        Sortable.create(grid, {
            draggable: '[data-uid]',
            filter: '.btn',
            preventOnFilter: false,
            forceFallback: true,
            fallbackOnBody: false,
            fallbackTolerance: 4,
            animation: 160,
            ghostClass: 'is-ghost',
            chosenClass: 'is-chosen',
            dragClass: 'is-drag-clone',
            onStart: function () { grid.classList.add('is-sorting'); },
            onEnd: function () {
                grid.classList.remove('is-sorting');
                refresh();
            }
        });

        document.querySelector('[data-crg-reset]').addEventListener('click', function () {
            var byUid = {};
            items().forEach(function (el) { byUid[el.dataset.uid] = el; });
            initial.forEach(function (uid) { grid.appendChild(byUid[uid]); });
            refresh();
        });

        document.getElementById('crg-sortform').addEventListener('submit', function () { dirty = false; });
        window.addEventListener('beforeunload', function (e) {
            if (dirty) { e.preventDefault(); e.returnValue = ''; }
        });
    }
})();
