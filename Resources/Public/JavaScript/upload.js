/**
 * crgalerie – Bilder-Upload im Backend-Modul
 * Dateien werden nacheinander (in Auswahl-Reihenfolge) hochgeladen,
 * damit die Sortierung in der Galerie der Upload-Reihenfolge entspricht.
 * Texte kommen übersetzt aus locallang.xlf (upload.*) über TYPO3.lang.
 */
const root = document.querySelector('[data-crg-upload]');

/**
 * Übersetzung mit sprintf-Platzhaltern wie in der XLIFF-Datei (%s, %d, %1$s, %2$d).
 */
function text(schluessel, ...werte) {
    const vorlage = window.TYPO3?.lang?.[schluessel] ?? schluessel;
    let naechster = 0;
    return vorlage.replace(/%(?:(\d+)\$)?[sd]/g, (_, position) => {
        const index = position ? Number(position) - 1 : naechster++;
        return String(werte[index] ?? '');
    });
}

function formatSize(bytes) {
    if (bytes > 1024 * 1024) {
        return (bytes / 1024 / 1024).toLocaleString(document.documentElement.lang || undefined, { maximumFractionDigits: 1 }) + ' MB';
    }
    return Math.round(bytes / 1024) + ' KB';
}

if (root) {
    const uploadUri = root.dataset.uploadUri;
    const gal = root.dataset.gal;
    const drop = root.querySelector('[data-crg-drop]');
    const input = root.querySelector('[data-crg-input]');
    const queueEl = root.querySelector('[data-crg-queue]');
    const list = root.querySelector('[data-crg-list]');
    const summary = root.querySelector('[data-crg-summary]');
    const totalBar = root.querySelector('[data-crg-total-bar]');
    const actions = root.querySelector('[data-crg-actions]');
    const countEl = root.querySelector('[data-crg-count]');
    const tpl = root.querySelector('[data-crg-item-template]');

    const MAX_MB = parseInt(root.dataset.maxMb, 10) || 50;
    const MAX_BYTES = MAX_MB * 1024 * 1024;
    const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
    const PREVIEWABLE = ['image/jpeg', 'image/png', 'image/webp'];

    // HEIC-Dateien haben in manchen Browsern keinen MIME-Typ – dann zählt die Endung
    const isAllowed = (file) => ALLOWED.includes(file.type) || /\.(heic|heif)$/i.test(file.name);

    const queue = [];
    let running = false;
    const stats = { total: 0, done: 0, failed: 0 };

    const setState = (item, state, statusText, meta) => {
        item.el.className = 'crg-item is-' + state;
        item.el.querySelector('.crg-item__status').textContent = statusText;
        if (meta !== undefined) {
            item.el.querySelector('.crg-item__meta').textContent = meta;
        }
    };

    const setProgress = (item, pct) => {
        item.el.querySelector('.crg-progress span').style.width = pct + '%';
    };

    const updateSummary = () => {
        const finished = stats.done + stats.failed;
        let zusammenfassung = text('upload.summary', stats.done, stats.total);
        if (stats.failed) {
            zusammenfassung += ' · ' + text('upload.failedCount', stats.failed);
        }
        summary.textContent = zusammenfassung;
        totalBar.style.width = (stats.total ? Math.round(finished / stats.total * 100) : 0) + '%';
        actions.hidden = !(stats.total && finished === stats.total);
        root.classList.toggle('is-busy', running);
    };

    // Kleine Vorschau als data:-URL (blob:-URLs blockiert die Content Security Policy des Backends)
    const renderPreview = async (file, img) => {
        if (!PREVIEWABLE.includes(file.type) || !window.createImageBitmap) {
            return;
        }
        try {
            const bitmap = await createImageBitmap(file);
            const size = 112;
            const scale = Math.max(size / bitmap.width, size / bitmap.height);
            const canvas = document.createElement('canvas');
            canvas.width = size;
            canvas.height = size;
            const w = bitmap.width * scale;
            const h = bitmap.height * scale;
            canvas.getContext('2d').drawImage(bitmap, (size - w) / 2, (size - h) / 2, w, h);
            img.src = canvas.toDataURL('image/jpeg', 0.8);
            bitmap.close?.();
        } catch {
            // keine Vorschau möglich
        }
    };

    // XMLHttpRequest statt fetch: nur so gibt es einen Upload-Fortschritt
    const upload = (item) => new Promise((resolve) => {
        setState(item, 'uploading', text('upload.uploading'));
        const data = new FormData();
        data.append('galerie', gal);
        data.append('file', item.file, item.file.name);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUri, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                // Die letzten Prozent reserviert für die Verarbeitung auf dem Server
                setProgress(item, Math.round(e.loaded / e.total * 90));
            }
        });
        xhr.upload.addEventListener('load', () => {
            setState(item, 'uploading', text('upload.processing'));
        });
        xhr.addEventListener('load', () => {
            let res = null;
            try {
                res = JSON.parse(xhr.responseText);
            } catch {
                // keine JSON-Antwort
            }
            if (xhr.status >= 200 && xhr.status < 300 && res?.success) {
                stats.done++;
                setProgress(item, 100);
                setState(item, 'done', text('upload.done'), formatSize(item.file.size));
                if (res.vorschauUrl) {
                    item.el.querySelector('img').src = res.vorschauUrl;
                }
                if (countEl) {
                    countEl.textContent = String(parseInt(countEl.textContent, 10) + 1);
                }
            } else {
                stats.failed++;
                setState(item, 'error', text('upload.error'), res?.message || text('upload.failedStatus', xhr.status));
            }
            resolve();
        });
        xhr.addEventListener('error', () => {
            stats.failed++;
            setState(item, 'error', text('upload.error'), text('upload.noConnection'));
            resolve();
        });
        xhr.send(data);
    });

    const next = async () => {
        if (running) {
            return;
        }
        running = true;
        let item;
        while ((item = queue.shift())) {
            updateSummary();
            await upload(item);
        }
        running = false;
        updateSummary();
    };

    const addFiles = (fileList) => {
        const files = Array.from(fileList);
        if (!files.length) {
            return;
        }
        queueEl.hidden = false;

        files.forEach((file) => {
            const el = tpl.content.firstElementChild.cloneNode(true);
            el.querySelector('.crg-item__name').textContent = file.name;
            el.querySelector('.crg-item__meta').textContent = formatSize(file.size);
            renderPreview(file, el.querySelector('img'));
            list.appendChild(el);

            const item = { file, el };
            stats.total++;

            if (!isAllowed(file)) {
                stats.failed++;
                setState(item, 'error', text('upload.notPossible'), text('upload.allowedTypes'));
                return;
            }
            if (file.size > MAX_BYTES) {
                stats.failed++;
                setState(item, 'error', text('upload.tooLarge'), text('upload.tooLargeDetail', formatSize(file.size), MAX_MB));
                return;
            }
            queue.push(item);
        });

        updateSummary();
        next();
    };

    input.addEventListener('change', () => {
        addFiles(input.files);
        input.value = '';
    });

    ['dragenter', 'dragover'].forEach((type) => {
        drop.addEventListener(type, (e) => {
            e.preventDefault();
            drop.classList.add('is-over');
        });
    });
    ['dragleave', 'drop'].forEach((type) => {
        drop.addEventListener(type, (e) => {
            e.preventDefault();
            if (type === 'dragleave' && drop.contains(e.relatedTarget)) {
                return;
            }
            drop.classList.remove('is-over');
        });
    });
    drop.addEventListener('drop', (e) => {
        if (e.dataTransfer?.files) {
            addFiles(e.dataTransfer.files);
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (running || queue.length) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
}
