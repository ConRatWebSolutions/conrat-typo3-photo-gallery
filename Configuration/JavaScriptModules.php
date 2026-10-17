<?php

/**
 * Stellt sicher, dass die Importmap des Backends (u. a. "sortablejs") verfügbar ist.
 * module.js selbst wird NICHT über diese Importmap geladen, sondern im Controller
 * per addJsFooterFile als type="module" mit Zeitstempel – die Importmap hängt ihr
 * ?bust= an die Extension-Version und bliebe nach einem Deploy unverändert.
 */
return [
    'dependencies' => ['backend', 'core'],
];
