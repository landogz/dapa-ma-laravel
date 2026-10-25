import axios from 'axios';

const catalogCache = new Map();

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function parseChoices(root, fallback) {
    const raw = root?.getAttribute('data-icon-choices');
    if (!raw) {
        return fallback;
    }
    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : fallback;
    } catch {
        return fallback;
    }
}

function prettyIconName(name) {
    return String(name ?? '')
        .replace(/^solid\//, '')
        .replaceAll(/[-_]+/g, ' ')
        .trim();
}

function choiceLabel(choices, name) {
    const match = choices.find((choice) => String(choice.value ?? '') === String(name ?? ''));
    return match?.label || prettyIconName(name);
}

function sanitizeSvg(markup) {
    const html = String(markup ?? '');
    if (!html.includes('<svg')) {
        return '';
    }
    return html
        .replaceAll(/<script[\s\S]*?<\/script>/gi, '')
        .replaceAll(/\son\w+="[^"]*"/gi, '')
        .replaceAll(/\son\w+='[^']*'/gi, '');
}

function loadIcons(uri) {
    if (!catalogCache.has(uri)) {
        catalogCache.set(
            uri,
            axios.get(`/admin/icon-picker/iconsets/${encodeURIComponent(uri)}`)
                .then(({ data }) => (Array.isArray(data?.data) ? data.data : [])),
        );
    }
    return catalogCache.get(uri);
}

function loadIconsets() {
    if (!catalogCache.has('__sets')) {
        catalogCache.set(
            '__sets',
            axios.get('/admin/icon-picker/iconsets')
                .then(({ data }) => (Array.isArray(data?.data) ? data.data : [])),
        );
    }
    return catalogCache.get('__sets');
}

function findIcon(icons, name) {
    return icons.find((icon) => String(icon.name ?? '') === String(name ?? '')) || null;
}

function renderTrigger(previewEl, icon, label, emptyCopy) {
    if (!previewEl) {
        return;
    }
    if (!icon?.name) {
        previewEl.innerHTML = `
            <span class="admin-svg-icon-picker-preview-empty" aria-hidden="true">
                <i class="fas fa-ban"></i>
            </span>
            <span class="admin-svg-icon-picker-preview-copy">
                <strong>${escapeHtml(emptyCopy.title)}</strong>
                <span>${escapeHtml(emptyCopy.hint)}</span>
            </span>
        `;
        return;
    }
    previewEl.innerHTML = `
        <span class="admin-nova-icon-svg admin-svg-icon-picker-preview-icon" aria-hidden="true">${sanitizeSvg(icon.contents)}</span>
        <span class="admin-svg-icon-picker-preview-copy">
            <strong>${escapeHtml(label || icon.name)}</strong>
            <span>${escapeHtml(icon.name)}</span>
        </span>
    `;
}

function renderGrid(gridEl, icons, selected, query) {
    const filtered = icons.filter((icon) => String(icon.name ?? '').toLowerCase().includes(query));
    if (filtered.length === 0) {
        gridEl.innerHTML = '<p class="admin-svg-icon-picker-empty">No icons found.</p>';
        return;
    }
    gridEl.innerHTML = filtered.map((icon) => {
        const name = String(icon.name ?? '');
        const isSelected = name === selected;
        return `<button type="button" class="admin-svg-icon-picker-tile${isSelected ? ' is-selected' : ''}" data-icon-value="${escapeHtml(name)}" title="${escapeHtml(name)}" aria-pressed="${isSelected ? 'true' : 'false'}" aria-label="${escapeHtml(name)}">
            <span class="admin-nova-icon-svg">${sanitizeSvg(icon.contents)}</span>
        </button>`;
    }).join('');
}

export function iconFaClass(value, choices = []) {
    const match = choices.find((choice) => String(choice.value ?? '') === String(value ?? ''));
    return match?.fa || 'fas fa-icons';
}

export function bindSvgIconPicker(root, {
    choices = [],
    selected = '',
    noneLabel = 'None (use default art)',
} = {}) {
    if (!root || root.dataset.bound === '1') {
        return;
    }
    root.dataset.bound = '1';

    const resolvedChoices = parseChoices(root, choices);
    const hidden = root.querySelector('input[type="hidden"]');
    const current = String(selected || hidden?.value || '');
    if (hidden && current && !hidden.value) {
        hidden.value = current;
    }
    const noneAttr = root.getAttribute('data-none-label');
    const resolvedNone = noneAttr != null ? noneAttr : noneLabel;
    const defaultUri = root.getAttribute('data-iconset') || 'outline';
    const resettable = root.getAttribute('data-resettable') !== '0';
    const fieldId = hidden?.id || 'tr-icon';

    root.innerHTML = `
        ${hidden ? hidden.outerHTML : '<input type="hidden" id="tr-icon" name="icon" value="">'}
        <div class="admin-svg-icon-picker-field">
            <div class="admin-svg-icon-picker-preview" data-icon-preview></div>
            <div class="admin-svg-icon-picker-actions">
                <button type="button" class="admin-svg-icon-picker-select" data-icon-open>
                    Select icon
                </button>
                ${resettable ? `<button type="button" class="admin-svg-icon-picker-reset" data-icon-reset aria-label="Reset icon" title="Reset">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>` : ''}
            </div>
        </div>
        ${resolvedNone ? `<p class="mt-1 text-xs text-slate-500">${escapeHtml(resolvedNone)}</p>` : ''}
        <div class="admin-nova-icon-modal" data-icon-modal hidden>
            <div class="admin-nova-icon-modal-backdrop" data-icon-close></div>
            <div class="admin-nova-icon-modal-panel" role="dialog" aria-modal="true" aria-labelledby="${escapeHtml(fieldId)}-modal-title">
                <div class="admin-nova-icon-modal-header">
                    <h3 id="${escapeHtml(fieldId)}-modal-title">Select icon</h3>
                    <button type="button" class="admin-nova-icon-modal-x" data-icon-close aria-label="Close">
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="admin-nova-icon-modal-toolbar">
                    <label class="sr-only" for="${escapeHtml(fieldId)}-search">Search icons</label>
                    <input type="search" class="admin-swal-input admin-svg-icon-picker-search" id="${escapeHtml(fieldId)}-search" placeholder="Search icons…" autocomplete="off">
                    <select class="admin-swal-input admin-svg-icon-picker-iconset" data-iconset-select hidden></select>
                </div>
                <div class="admin-svg-icon-picker-grid admin-nova-icon-modal-grid" data-icon-grid role="listbox" aria-label="Icon set"></div>
                <div class="admin-nova-icon-modal-footer">
                    <button type="button" class="admin-nova-icon-modal-cancel" data-icon-close>Cancel</button>
                </div>
            </div>
        </div>
    `;

    const input = root.querySelector('input[type="hidden"]');
    const previewEl = root.querySelector('[data-icon-preview]');
    const searchEl = root.querySelector('.admin-svg-icon-picker-search');
    const gridEl = root.querySelector('[data-icon-grid]');
    const modalEl = root.querySelector('[data-icon-modal]');
    const iconsetSelect = root.querySelector('[data-iconset-select]');
    const resetBtn = root.querySelector('[data-icon-reset]');
    document.body.appendChild(modalEl);
    let icons = [];
    let currentUri = defaultUri;

    const emptyCopy = {
        title: 'No icon',
        hint: resolvedNone || 'Default art will be used',
    };

    const paintTrigger = () => {
        const value = String(input?.value ?? '');
        const icon = findIcon(icons, value);
        renderTrigger(previewEl, icon || (value ? { name: value, contents: '' } : null), choiceLabel(resolvedChoices, value), emptyCopy);
        if (resetBtn) {
            resetBtn.hidden = !value;
        }
    };

    const paintGrid = () => {
        renderGrid(gridEl, icons, String(input?.value ?? ''), String(searchEl?.value ?? '').trim().toLowerCase());
    };

    const setValue = (value) => {
        if (input) {
            input.value = value;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        paintTrigger();
        paintGrid();
    };

    const closeModal = () => {
        modalEl.hidden = true;
        document.body.classList.remove('admin-nova-icon-modal-open');
    };

    const openModal = async () => {
        modalEl.hidden = false;
        document.body.classList.add('admin-nova-icon-modal-open');
        searchEl.value = '';
        searchEl.focus();
        await ensureIcons(currentUri);
        paintGrid();
    };

    const ensureIcons = async (uri) => {
        icons = await loadIcons(uri);
        paintTrigger();
    };

    const resolveSetForValue = async (value, sets) => {
        if (value) {
            const preferred = value.startsWith('solid/')
                ? 'solid'
                : sets.map((set) => set.uri);
            const order = [
                ...(typeof preferred === 'string' ? [preferred] : []),
                ...sets.map((set) => set.uri),
            ].filter((uri, index, all) => uri && all.indexOf(uri) === index);
            for (const uri of order) {
                const list = await loadIcons(uri);
                if (findIcon(list, value)) {
                    return { uri, icons: list };
                }
            }
        }
        const fallback = sets.some((set) => set.uri === defaultUri) ? defaultUri : (sets[0]?.uri || 'outline');
        return { uri: fallback, icons: await loadIcons(fallback) };
    };

    const fillIconsets = async () => {
        const sets = await loadIconsets();
        const resolved = await resolveSetForValue(String(input?.value ?? ''), sets);
        currentUri = resolved.uri;
        icons = resolved.icons;
        paintTrigger();
        if (sets.length < 2) {
            return;
        }
        iconsetSelect.hidden = false;
        iconsetSelect.innerHTML = sets.map((set) => {
            const uri = set.uri || set.name;
            const selected = uri === currentUri ? 'selected' : '';
            return `<option value="${escapeHtml(uri)}" ${selected}>${escapeHtml(set.name || uri)}</option>`;
        }).join('');
    };

    root.querySelector('[data-icon-open]')?.addEventListener('click', (event) => {
        event.preventDefault();
        openModal();
    });

    resetBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        setValue('');
    });

    modalEl.querySelectorAll('[data-icon-close]').forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            closeModal();
        });
    });

    gridEl.addEventListener('click', (event) => {
        const tile = event.target.closest('[data-icon-value]');
        if (!tile) {
            return;
        }
        event.preventDefault();
        setValue(tile.getAttribute('data-icon-value') ?? '');
        closeModal();
    });

    searchEl.addEventListener('input', paintGrid);

    iconsetSelect.addEventListener('change', async () => {
        currentUri = iconsetSelect.value || defaultUri;
        catalogCache.delete(currentUri);
        await ensureIcons(currentUri);
        paintGrid();
    });

    fillIconsets();
}

export function bindSvgIconPickers(root = document) {
    root.querySelectorAll('[data-svg-icon-picker]').forEach((el) => {
        bindSvgIconPicker(el);
    });
}
