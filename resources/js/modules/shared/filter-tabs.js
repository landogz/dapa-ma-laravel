const DEFAULT_ICON = 'fas fa-table';

export function bindAdminFilterTabs(root, onSelect) {
    if (!root || root.dataset.tabsBound === '1') {
        return;
    }

    root.dataset.tabsBound = '1';
    enhanceAdminFilterTabs(root);
    root.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-filter-value]');
        if (!tab || !root.contains(tab)) {
            return;
        }

        const value = tab.dataset.filterValue ?? '';
        const current = root.querySelector('[aria-selected="true"]')?.dataset.filterValue ?? '';
        if (value === current) {
            return;
        }

        setAdminFilterTab(root, value);
        onSelect(value);
    });
}

export function setAdminFilterTab(root, value) {
    if (!root) {
        return;
    }

    const selected = value ?? '';
    root.querySelectorAll('[data-filter-value]').forEach((tab) => {
        const active = (tab.dataset.filterValue ?? '') === selected;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
}

export function renderAdminFilterTabs(root, items) {
    if (!root) {
        return;
    }

    root.classList.add('admin-filter-tabs');
    if (!root.getAttribute('role')) {
        root.setAttribute('role', 'tablist');
    }

    root.replaceChildren();
    items.forEach((item) => {
        root.appendChild(buildAdminFilterTab(item));
    });
}

export function enhanceAdminFilterTabs(root) {
    if (!root) {
        return;
    }

    root.querySelectorAll('[data-filter-value]').forEach((tab) => {
        if (tab.querySelector('.admin-filter-tab-label')) {
            return;
        }

        const label = tab.dataset.filterLabel || tab.textContent.trim();
        const icon = tab.dataset.icon || DEFAULT_ICON;
        const count = tab.dataset.count;
        tab.replaceChildren();
        tab.appendChild(buildAdminFilterTabInner({
            label,
            icon,
            hint: tab.dataset.hint || '',
            swatch: tab.dataset.swatch || '',
            initials: tab.dataset.initials || '',
            spectrum: tab.dataset.spectrum === '1',
            count: count === undefined || count === '' ? null : Number(count),
        }));
    });
}

export function applyAdminFilterTabCounts(root, counts = {}) {
    if (!root) {
        return;
    }

    root.querySelectorAll('[data-filter-value]').forEach((tab) => {
        const countEl = tab.querySelector('.admin-filter-tab-count');
        if (!countEl) {
            return;
        }

        const value = tab.dataset.filterValue ?? '';
        const count = counts[value];
        countEl.textContent = count == null || Number.isNaN(Number(count)) ? '' : String(count);
    });
}

export function countByKey(rows, keyFn) {
    const counts = { '': rows.length };
    rows.forEach((row) => {
        const key = String(keyFn(row) ?? '');
        if (key === '') {
            return;
        }
        counts[key] = (counts[key] ?? 0) + 1;
    });
    return counts;
}

function buildAdminFilterTab(item) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'admin-filter-tab';
    button.setAttribute('role', 'tab');
    button.dataset.filterValue = item.value ?? '';
    button.dataset.filterLabel = item.label ?? '';
    if (item.icon) {
        button.dataset.icon = item.icon;
    }
    if (item.swatch) {
        button.dataset.swatch = item.swatch;
    }
    if (item.hint) {
        button.dataset.hint = item.hint;
    }
    if (item.initials) {
        button.dataset.initials = item.initials;
    }
    if (item.spectrum) {
        button.dataset.spectrum = '1';
    }
    button.appendChild(buildAdminFilterTabInner(item));
    return button;
}

function buildAdminFilterTabInner(item) {
    const fragment = document.createDocumentFragment();
    fragment.append(buildAdminFilterTabLead(item), buildAdminFilterTabCopy(item), buildAdminFilterTabCount(item));
    return fragment;
}

function buildAdminFilterTabLead(item) {
    if (item.spectrum || item.swatch) {
        const swatch = document.createElement('span');
        swatch.className = 'admin-filter-tab-swatch';
        swatch.setAttribute('aria-hidden', 'true');
        if (item.spectrum) {
            swatch.classList.add('is-spectrum');
        }
        if (item.swatch) {
            swatch.dataset.sky = item.swatch;
            if (item.swatch === 'sky_white') {
                swatch.classList.add('is-light');
            }
        }
        return swatch;
    }

    if (item.initials) {
        const avatar = document.createElement('span');
        avatar.className = 'admin-filter-tab-avatar';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = item.initials;
        return avatar;
    }

    const iconWrap = document.createElement('span');
    iconWrap.className = 'admin-filter-tab-icon';
    iconWrap.setAttribute('aria-hidden', 'true');
    const icon = document.createElement('i');
    icon.className = item.icon || DEFAULT_ICON;
    iconWrap.appendChild(icon);
    return iconWrap;
}

function buildAdminFilterTabCopy(item) {
    const copy = document.createElement('span');
    copy.className = 'admin-filter-tab-copy';

    const label = document.createElement('span');
    label.className = 'admin-filter-tab-label';
    label.textContent = item.label ?? '';
    copy.appendChild(label);

    if (item.hint) {
        const hint = document.createElement('span');
        hint.className = 'admin-filter-tab-hint';
        hint.textContent = item.hint;
        copy.appendChild(hint);
    }

    return copy;
}

function buildAdminFilterTabCount(item) {
    const count = document.createElement('span');
    count.className = 'admin-filter-tab-count';
    if (item.count != null && item.count !== '') {
        count.textContent = String(item.count);
    }
    return count;
}
