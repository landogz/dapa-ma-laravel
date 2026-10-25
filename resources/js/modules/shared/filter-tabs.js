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

        const label = tab.textContent.trim();
        const icon = tab.dataset.icon || DEFAULT_ICON;
        const count = tab.dataset.count;
        tab.replaceChildren();
        tab.appendChild(buildAdminFilterTabInner({
            label,
            icon,
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
    if (item.icon) {
        button.dataset.icon = item.icon;
    }
    button.appendChild(buildAdminFilterTabInner(item));
    return button;
}

function buildAdminFilterTabInner(item) {
    const fragment = document.createDocumentFragment();

    const iconWrap = document.createElement('span');
    iconWrap.className = 'admin-filter-tab-icon';
    iconWrap.setAttribute('aria-hidden', 'true');
    const icon = document.createElement('i');
    icon.className = item.icon || DEFAULT_ICON;
    iconWrap.appendChild(icon);

    const label = document.createElement('span');
    label.className = 'admin-filter-tab-label';
    label.textContent = item.label ?? '';

    const count = document.createElement('span');
    count.className = 'admin-filter-tab-count';
    if (item.count != null && item.count !== '') {
        count.textContent = String(item.count);
    }

    fragment.append(iconWrap, label, count);
    return fragment;
}
