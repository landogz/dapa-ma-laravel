/**
 * Shared DataTable action-button helpers.
 * Every desktop action button must include a custom hover tooltip.
 */

let overflowDocumentBound = false;

export function escapeActionHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

/**
 * @param {object} options
 * @param {string} options.tooltip
 * @param {string} options.icon Font Awesome class list, e.g. "fas fa-pen-to-square"
 * @param {string} [options.className]
 * @param {string} [options.attrs] Extra HTML attributes (onclick, data-*, etc.)
 * @param {boolean} [options.isMobile]
 * @param {string} [options.label] Visible label for mobile; defaults to tooltip
 */
export function buildAdminActionButton({
    tooltip,
    icon,
    className = '',
    attrs = '',
    isMobile = false,
    label = null,
} = {}) {
    const tip = escapeActionHtml(tooltip || label || 'Action');
    const visibleLabel = escapeActionHtml(label || tooltip || 'Action');
    const classes = [
        'admin-table-action',
        isMobile ? '' : 'admin-table-action-icon',
        className,
    ].filter(Boolean).join(' ');

    const button = `
        <button type="button" class="${classes}" title="${tip}" aria-label="${tip}" ${attrs}>
            <i class="${escapeActionHtml(icon)}"></i>
            <span class="${isMobile ? '' : 'sr-only'}">${visibleLabel}</span>
        </button>
    `;

    if (isMobile) {
        return button;
    }

    return `
        <span class="admin-action-tooltip-wrap">
            ${button}
            <span class="admin-action-tooltip" role="tooltip">${tip}</span>
        </span>
    `;
}

/**
 * @param {Array<object>} actions
 * @param {{ isMobile?: boolean, nowrap?: boolean, className?: string, overflowAfter?: number|null }} [options]
 */
export function buildAdminActionButtons(actions = [], {
    isMobile = false,
    nowrap = true,
    className = '',
    overflowAfter = 2,
} = {}) {
    const useOverflow = !isMobile
        && Number.isFinite(overflowAfter)
        && overflowAfter >= 1
        && actions.length > overflowAfter;
    const visible = useOverflow ? actions.slice(0, overflowAfter) : actions;
    const extra = useOverflow ? actions.slice(overflowAfter) : [];

    const rowClass = [
        'admin-table-actions',
        isMobile ? 'admin-table-actions-mobile' : '',
        nowrap ? 'admin-table-actions-nowrap' : '',
        extra.length ? 'admin-table-actions-tiered' : '',
        className,
    ].filter(Boolean).join(' ');

    return `
        <div class="${rowClass}">
            ${visible.map((action) => buildAdminActionButton({
                ...action,
                isMobile,
            })).join('')}
            ${extra.length ? buildAdminActionOverflow(extra) : ''}
        </div>
    `;
}

function isDangerAction(action = {}) {
    return String(action.className ?? '').includes('danger')
        || String(action.iconClass ?? '').includes('CE2028');
}

function buildAdminActionOverflow(actions = []) {
    const items = actions.map((action) => {
        const tip = escapeActionHtml(action.tooltip || action.label || 'Action');
        const label = escapeActionHtml(action.label || action.tooltip || 'Action');
        const icon = escapeActionHtml([action.icon, action.iconClass].filter(Boolean).join(' '));
        const itemClass = [
            'admin-action-overflow-item',
            isDangerAction(action) ? 'admin-action-overflow-item-danger' : '',
        ].filter(Boolean).join(' ');

        return `
            <button type="button" class="${itemClass}" role="menuitem" title="${tip}" aria-label="${tip}" ${action.attrs || ''}>
                <i class="${icon}" aria-hidden="true"></i>
                <span>${label}</span>
            </button>
        `;
    }).join('');

    return `
        <div class="admin-action-overflow">
            ${buildAdminActionButton({
                tooltip: 'More actions',
                icon: 'fas fa-ellipsis',
                attrs: 'data-admin-overflow-toggle="true" aria-haspopup="menu" aria-expanded="false"',
            })}
            <div class="admin-action-overflow-menu" role="menu" hidden>
                ${items}
            </div>
        </div>
    `;
}

function closeAllActionOverflows(exceptWrap = null) {
    document.querySelectorAll('.admin-action-overflow.is-open').forEach((wrap) => {
        if (exceptWrap && wrap === exceptWrap) {
            return;
        }

        wrap.classList.remove('is-open');
        wrap.querySelector('[data-admin-overflow-toggle]')?.setAttribute('aria-expanded', 'false');
        wrap.querySelector('.admin-action-overflow-menu')?.setAttribute('hidden', 'hidden');
    });
}

function positionActionOverflowMenu(wrap) {
    const toggle = wrap.querySelector('[data-admin-overflow-toggle]');
    const menu = wrap.querySelector('.admin-action-overflow-menu');
    if (!toggle || !menu) {
        return;
    }

    menu.removeAttribute('hidden');
    const toggleRect = toggle.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const openUp = toggleRect.bottom + 8 + menuRect.height > window.innerHeight
        && toggleRect.top > menuRect.height + 8;

    menu.style.position = 'fixed';
    menu.style.left = 'auto';
    menu.style.right = `${Math.max(8, window.innerWidth - toggleRect.right)}px`;

    if (openUp) {
        menu.style.top = 'auto';
        menu.style.bottom = `${Math.max(8, window.innerHeight - toggleRect.top + 6)}px`;
    } else {
        menu.style.top = `${toggleRect.bottom + 6}px`;
        menu.style.bottom = 'auto';
    }
}

function bindOverflowDocumentListeners() {
    if (overflowDocumentBound) {
        return;
    }

    overflowDocumentBound = true;

    document.addEventListener('click', (event) => {
        if (!event.target.closest?.('.admin-action-overflow')) {
            closeAllActionOverflows();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllActionOverflows();
        }
    });

    window.addEventListener('scroll', () => closeAllActionOverflows(), true);
    window.addEventListener('resize', () => closeAllActionOverflows());
}

/**
 * Suppress sticky hover tooltips after click (desktop).
 */
export function bindAdminActionTooltipSuppression(rootEl) {
    if (!rootEl || rootEl.dataset.actionTooltipsBound === 'true') {
        return;
    }

    rootEl.dataset.actionTooltipsBound = 'true';
    bindOverflowDocumentListeners();

    rootEl.addEventListener('click', (event) => {
        const overflowToggle = event.target.closest('[data-admin-overflow-toggle]');
        if (overflowToggle) {
            event.preventDefault();
            event.stopPropagation();
            const overflow = overflowToggle.closest('.admin-action-overflow');
            const willOpen = !overflow?.classList.contains('is-open');
            closeAllActionOverflows(overflow);
            if (!overflow) {
                return;
            }

            overflow.classList.toggle('is-open', willOpen);
            overflowToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) {
                positionActionOverflowMenu(overflow);
            } else {
                overflow.querySelector('.admin-action-overflow-menu')?.setAttribute('hidden', 'hidden');
            }
            return;
        }

        if (event.target.closest('.admin-action-overflow-item')) {
            closeAllActionOverflows();
        }

        const wrap = event.target.closest('.admin-action-tooltip-wrap');
        if (!wrap) return;
        wrap.classList.add('admin-action-tooltip-wrap--suppressed');
        wrap.querySelector('.admin-table-action')?.blur();
    });

    rootEl.addEventListener('mouseleave', (event) => {
        const wrap = event.target.closest?.('.admin-action-tooltip-wrap');
        if (!wrap) return;
        wrap.classList.remove('admin-action-tooltip-wrap--suppressed');
    }, true);

    rootEl.addEventListener('focusout', (event) => {
        const wrap = event.target.closest?.('.admin-action-tooltip-wrap');
        if (!wrap) return;
        wrap.classList.remove('admin-action-tooltip-wrap--suppressed');
    }, true);
}
