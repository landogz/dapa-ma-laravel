import { dismissAdminTablePopovers, escapeActionHtml } from '../table-actions';

const MENU_ID = 'admin-dt-context-menu';

let contextMenuEl = null;
let contextMenuDocumentBound = false;
let contextTargetRow = null;

function isOverflowToggle(button) {
    return Boolean(button?.matches?.('[data-admin-overflow-toggle]'));
}

function isDangerButton(button) {
    const className = String(button?.className ?? '');

    return className.includes('danger')
        || className.includes('CE2028')
        || className.includes('ce2028');
}

function buttonLabel(button) {
    return (
        button.getAttribute('aria-label')
        || button.getAttribute('title')
        || button.textContent
        || 'Action'
    ).replace(/\s+/g, ' ').trim();
}

function buttonIconClass(button) {
    const icon = button.querySelector('i');
    const className = String(icon?.className ?? '').trim();

    return className || 'fas fa-circle';
}

function collectOverflowMenu(wrap) {
    const ownerId = wrap.dataset.overflowId;

    return wrap._overflowMenu
        || wrap.querySelector('.admin-action-overflow-menu')
        || (ownerId
            ? document.querySelector(`.admin-action-overflow-menu[data-overflow-owner="${CSS.escape(ownerId)}"]`)
            : null);
}

function collectRowActions(row) {
    const actions = [];
    const seen = new Set();

    const addButton = (button) => {
        if (!button || seen.has(button) || isOverflowToggle(button)) {
            return;
        }

        seen.add(button);
        actions.push({
            label: buttonLabel(button),
            icon: buttonIconClass(button),
            danger: isDangerButton(button),
            button,
        });
    };

    row.querySelectorAll('.admin-table-actions .admin-table-action, .admin-table-actions-mobile .admin-table-action').forEach(addButton);
    row.querySelectorAll('.admin-action-overflow').forEach((wrap) => {
        collectOverflowMenu(wrap)?.querySelectorAll('.admin-action-overflow-item').forEach(addButton);
    });

    if (actions.length === 0) {
        row.querySelectorAll('.admin-table-action, .admin-action-overflow-item').forEach(addButton);
    }

    return actions;
}

function isSkippableRow(row) {
    if (!row || row.classList.contains('child') || row.classList.contains('dt-empty')) {
        return true;
    }

    return Boolean(row.querySelector('td.dataTables_empty'));
}

function ensureContextMenu() {
    if (contextMenuEl?.isConnected) {
        return contextMenuEl;
    }

    contextMenuEl = document.getElementById(MENU_ID);
    if (!contextMenuEl) {
        contextMenuEl = document.createElement('div');
        contextMenuEl.id = MENU_ID;
        contextMenuEl.className = 'admin-context-menu';
        contextMenuEl.setAttribute('role', 'menu');
        contextMenuEl.setAttribute('hidden', 'hidden');
        contextMenuEl.setAttribute('aria-hidden', 'true');
        document.body.appendChild(contextMenuEl);
    }

    return contextMenuEl;
}

function clearContextTarget() {
    contextTargetRow?.classList.remove('is-context-target');
    contextTargetRow = null;
}

export function hideAdminTableContextMenu() {
    const menu = contextMenuEl ?? document.getElementById(MENU_ID);
    if (menu) {
        menu.classList.remove('is-open');
        menu.setAttribute('hidden', 'hidden');
        menu.setAttribute('aria-hidden', 'true');
        menu.innerHTML = '';
    }

    clearContextTarget();
}

function positionContextMenu(clientX, clientY) {
    const menu = ensureContextMenu();
    const pad = 8;
    const menuRect = menu.getBoundingClientRect();
    const left = Math.min(
        Math.max(pad, clientX),
        Math.max(pad, window.innerWidth - menuRect.width - pad),
    );
    const top = Math.min(
        Math.max(pad, clientY),
        Math.max(pad, window.innerHeight - menuRect.height - pad),
    );

    menu.style.left = `${left}px`;
    menu.style.top = `${top}px`;
}

function showContextMenu(actions, clientX, clientY, row) {
    const menu = ensureContextMenu();
    hideAdminTableContextMenu();
    dismissAdminTablePopovers();

    contextTargetRow = row;
    row.classList.add('is-context-target');

    menu.innerHTML = actions.map((action, index) => {
        const itemClass = [
            'admin-context-menu-button',
            action.danger ? 'admin-context-menu-button-danger' : '',
        ].filter(Boolean).join(' ');

        return `
            <button type="button" class="${itemClass}" role="menuitem" data-context-index="${index}">
                <i class="${escapeActionHtml(action.icon)}" aria-hidden="true"></i>
                <span>${escapeActionHtml(action.label)}</span>
            </button>
        `;
    }).join('');

    menu.querySelectorAll('[data-context-index]').forEach((item) => {
        item.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const index = Number(item.dataset.contextIndex);
            const source = actions[index]?.button;
            hideAdminTableContextMenu();
            source?.click();
        });
    });

    menu.classList.add('is-open');
    menu.removeAttribute('hidden');
    menu.setAttribute('aria-hidden', 'false');
    positionContextMenu(clientX, clientY);
    requestAnimationFrame(() => positionContextMenu(clientX, clientY));
}

function bindContextMenuDocumentListeners() {
    if (contextMenuDocumentBound) {
        return;
    }

    contextMenuDocumentBound = true;

    document.addEventListener('click', (event) => {
        if (event.target.closest(`#${MENU_ID}`)) {
            return;
        }

        hideAdminTableContextMenu();
    });

    document.addEventListener('contextmenu', (event) => {
        if (event.target.closest(`#${MENU_ID}`)) {
            event.preventDefault();
            return;
        }

        if (event.defaultPrevented) {
            return;
        }

        hideAdminTableContextMenu();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideAdminTableContextMenu();
        }
    });

    window.addEventListener('scroll', (event) => {
        if (event.target?.closest?.(`#${MENU_ID}`)) {
            return;
        }

        hideAdminTableContextMenu();
    }, true);
    window.addEventListener('resize', hideAdminTableContextMenu);
}

/**
 * Right-click a DataTable row to open the same actions as the Actions column.
 * Bound once per table by createAdminDataTable — do not add per-page menus.
 */
export function bindAdminTableContextMenu(tableEl) {
    if (!tableEl || tableEl.dataset.contextMenuBound === '1') {
        return;
    }

    tableEl.dataset.contextMenuBound = '1';
    ensureContextMenu();
    bindContextMenuDocumentListeners();

    tableEl.addEventListener('contextmenu', (event) => {
        if (event.target.closest('input, textarea, select, option')) {
            return;
        }

        const row = event.target.closest('tbody tr');
        if (!row || !tableEl.contains(row) || isSkippableRow(row)) {
            return;
        }

        const actions = collectRowActions(row);
        if (actions.length === 0) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        showContextMenu(actions, event.clientX, event.clientY, row);
    });
}
