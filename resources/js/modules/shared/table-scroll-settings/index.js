const STORAGE_KEY = 'dape-admin-dt-scrollable';
export const ADMIN_TABLE_SCROLLABLE_EVENT = 'admin-dt-scrollable-change';

const MOBILE_QUERY = '(max-width: 1023px)';

export function isAdminTableScrollable() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

export function setAdminTableScrollable(enabled) {
    try {
        window.localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
    } catch {
        // Private mode or storage blocked — keep the in-page class only.
    }

    window.dispatchEvent(new CustomEvent(ADMIN_TABLE_SCROLLABLE_EVENT, {
        detail: { enabled: Boolean(enabled) },
    }));
}

export function getAdminTableMode() {
    if (isAdminTableScrollable()) {
        return 'desktop';
    }

    return window.matchMedia(MOBILE_QUERY).matches ? 'mobile' : 'desktop';
}

export function bindAdminTableModeChange(handler) {
    if (typeof handler !== 'function') {
        return () => {};
    }

    window.addEventListener(ADMIN_TABLE_SCROLLABLE_EVENT, handler);

    return () => window.removeEventListener(ADMIN_TABLE_SCROLLABLE_EVENT, handler);
}

export function bindAdminTableScrollSettings(table, onToggle) {
    const container = table?.table?.()?.container?.();
    if (!container || container.dataset.scrollSettingsBound === '1') {
        return;
    }

    const search = container.querySelector('.dt-search');
    if (!search) {
        return;
    }

    container.dataset.scrollSettingsBound = '1';
    search.classList.add('admin-dt-search-with-settings');

    const wrap = document.createElement('label');
    wrap.className = 'admin-dt-table-settings';
    wrap.setAttribute(
        'title',
        'Keep all columns visible and scroll sideways. Actions stay pinned on the right.',
    );
    wrap.innerHTML = `
        <input type="checkbox" class="admin-dt-table-settings-checkbox" data-admin-dt-scrollable>
        <span class="admin-dt-table-settings-ui">
            <span class="admin-dt-table-settings-box" aria-hidden="true">
                <i class="fas fa-check"></i>
            </span>
            <span class="admin-dt-table-settings-label">Scrollable</span>
        </span>
    `;

    search.appendChild(wrap);
    syncAdminTableScrollableUi(table);

    const checkbox = wrap.querySelector('[data-admin-dt-scrollable]');
    checkbox?.addEventListener('change', () => {
        setAdminTableScrollable(Boolean(checkbox.checked));
        applyAdminTableScrollableState(table);
        onToggle?.(Boolean(checkbox.checked));
    });

    table.on('destroy', () => {
        delete container.dataset.scrollSettingsBound;
        wrap.remove();
    });
}

export function applyAdminTableScrollableState(table) {
    try {
        const container = table?.table?.()?.container?.();
        if (!container) {
            return false;
        }

        const enabled = isAdminTableScrollable();
        container.classList.toggle('admin-dt-scrollable', enabled);
        syncAdminTableScrollableUi(table);

        return enabled;
    } catch {
        return false;
    }
}

function syncAdminTableScrollableUi(table) {
    const container = table?.table?.()?.container?.();
    const wrap = container?.querySelector('.admin-dt-table-settings');
    if (!wrap) {
        return;
    }

    const enabled = isAdminTableScrollable();
    const checkbox = wrap.querySelector('[data-admin-dt-scrollable]');

    if (checkbox) {
        checkbox.checked = enabled;
        checkbox.setAttribute(
            'aria-label',
            enabled
                ? 'Scrollable table is on. All columns stay visible and the table scrolls sideways.'
                : 'Turn on scrollable table. All columns stay visible and the table scrolls sideways.',
        );
    }

    wrap.classList.toggle('is-active', enabled);
}
