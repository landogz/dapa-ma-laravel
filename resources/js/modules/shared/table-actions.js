/**
 * Shared DataTable action-button helpers.
 * Every desktop action button must include a custom hover tooltip.
 */

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
    showLabel = false,
    variant = '',
} = {}) {
    const tip = escapeActionHtml(tooltip || label || 'Action');
    const visibleLabel = escapeActionHtml(label || tooltip || 'Action');
    const isGhost = variant === 'ghost';
    const isLabeled = showLabel || isMobile;
    const classes = [
        'admin-table-action',
        isGhost ? 'admin-table-action-ghost' : '',
        isLabeled ? 'admin-table-action-label' : 'admin-table-action-icon',
        className,
    ].filter(Boolean).join(' ');

    const button = `
        <button type="button" class="${classes}" title="${tip}" aria-label="${tip}" ${attrs}>
            <i class="${escapeActionHtml(icon)}" aria-hidden="true"></i>
            <span class="${isLabeled ? '' : 'sr-only'}">${visibleLabel}</span>
        </button>
    `;

    if (isMobile || isLabeled) {
        return button;
    }

    return `
        <span class="admin-action-tooltip-wrap">
            ${button}
            <span class="admin-action-tooltip" role="tooltip">${tip}</span>
        </span>
    `;
}

export function buildAdminActionSlot() {
    return '<span class="admin-table-action-slot" aria-hidden="true"></span>';
}

export function buildAdminActionOverflow(items = [], { isMobile = false } = {}) {
    if (!items.length) {
        return buildAdminActionSlot();
    }

    const menuItems = items.map((item, index) => {
        const tip = escapeActionHtml(item.tooltip || item.label || 'Action');
        const label = escapeActionHtml(item.label || item.tooltip || 'Action');
        const icon = escapeActionHtml(item.icon || 'fa-regular fa-circle');
        const divider = item.danger && index > 0 && !items[index - 1]?.danger
            ? '<div class="admin-action-overflow-divider" role="separator"></div>'
            : '';
        const itemClass = [
            'admin-action-overflow-item',
            item.danger ? 'admin-action-overflow-item-danger' : '',
            item.className ?? '',
        ].filter(Boolean).join(' ');

        return `
            ${divider}
            <button type="button" class="${itemClass}" role="menuitem" title="${tip}" aria-label="${tip}" ${item.attrs ?? ''}>
                <i class="${icon}" aria-hidden="true"></i>
                <span>${label}</span>
            </button>
        `;
    }).join('');

    const toggle = buildAdminActionButton({
        tooltip: 'More actions',
        icon: 'fa-regular fa-ellipsis',
        variant: 'ghost',
        isMobile,
        attrs: 'data-admin-overflow-toggle aria-haspopup="menu" aria-expanded="false"',
    });

    return `
        <div class="admin-action-overflow">
            ${toggle}
            <div class="admin-action-overflow-menu" role="menu" hidden>
                ${menuItems}
            </div>
        </div>
    `;
}

export function closeAdminActionOverflows(except = null) {
    document.querySelectorAll('.admin-action-overflow.is-open').forEach((wrap) => {
        if (except && wrap === except) {
            return;
        }

        wrap.classList.remove('is-open');
        wrap.querySelector('[data-admin-overflow-toggle]')?.setAttribute('aria-expanded', 'false');
        wrap.querySelector('.admin-action-overflow-menu')?.setAttribute('hidden', 'hidden');
    });
}

/**
 * @param {Array<object>} actions
 * @param {{ isMobile?: boolean, nowrap?: boolean, className?: string }} [options]
 */
export function buildAdminActionButtons(actions = [], {
    isMobile = false,
    nowrap = true,
    className = '',
} = {}) {
    const rowClass = [
        'admin-table-actions',
        isMobile ? 'admin-table-actions-mobile' : '',
        nowrap ? 'admin-table-actions-nowrap' : '',
        className,
    ].filter(Boolean).join(' ');

    return `
        <div class="${rowClass}">
            ${actions.map((action) => buildAdminActionButton({
                ...action,
                isMobile,
            })).join('')}
        </div>
    `;
}

let documentOverflowListenersBound = false;

/**
 * Suppress sticky hover tooltips after click (desktop).
 */
export function bindAdminActionTooltipSuppression(rootEl) {
    if (!rootEl || rootEl.dataset.actionTooltipsBound === 'true') {
        return;
    }

    rootEl.dataset.actionTooltipsBound = 'true';

    rootEl.addEventListener('click', (event) => {
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

    bindAdminActionOverflow(rootEl);
}

export function bindAdminActionOverflow(rootEl) {
    if (!rootEl || rootEl.dataset.actionOverflowBound === 'true') {
        return;
    }

    rootEl.dataset.actionOverflowBound = 'true';

    rootEl.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-admin-overflow-toggle]');
        if (toggle) {
            event.preventDefault();
            event.stopPropagation();
            const wrap = toggle.closest('.admin-action-overflow');
            const willOpen = !wrap?.classList.contains('is-open');
            closeAdminActionOverflows(wrap);
            if (!wrap) {
                return;
            }

            wrap.classList.toggle('is-open', willOpen);
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            wrap.querySelector('.admin-action-overflow-menu')?.toggleAttribute('hidden', !willOpen);
            return;
        }

        if (!event.target.closest('.admin-action-overflow')) {
            closeAdminActionOverflows();
        }
    });

    if (documentOverflowListenersBound) {
        return;
    }

    documentOverflowListenersBound = true;

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAdminActionOverflows();
        }
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.admin-action-overflow')) {
            closeAdminActionOverflows();
        }
    });
}
