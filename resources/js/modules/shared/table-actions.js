/**
 * Shared DataTable action-button helpers.
 * Every desktop action button must include a custom hover tooltip.
 */

let overflowDocumentBound = false;
let tooltipDocumentBound = false;

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
 * @param {boolean} [options.hideTooltip]
 */
export function buildAdminActionButton({
    tooltip,
    icon,
    className = '',
    attrs = '',
    isMobile = false,
    label = null,
    hideTooltip = false,
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

    if (isMobile || hideTooltip) {
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
        && actions.length > overflowAfter + 1;
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
                hideTooltip: true,
                attrs: 'data-admin-overflow-toggle="true" aria-haspopup="menu" aria-expanded="false"',
            })}
            <div class="admin-action-overflow-menu" role="menu" hidden>
                ${items}
            </div>
        </div>
    `;
}

function getOverflowMenu(wrap) {
    return wrap._overflowMenu
        || wrap.querySelector('.admin-action-overflow-menu')
        || document.querySelector(`.admin-action-overflow-menu[data-overflow-owner="${wrap.dataset.overflowId}"]`);
}

function restoreOverflowMenu(wrap) {
    const menu = getOverflowMenu(wrap);
    if (!menu) {
        return;
    }

    menu.setAttribute('hidden', 'hidden');
    menu.style.top = '';
    menu.style.right = '';
    menu.style.bottom = '';
    menu.style.left = '';

    if (menu.parentElement !== wrap) {
        wrap.appendChild(menu);
    }

    wrap._overflowMenu = null;
}

function closeAllActionOverflows(exceptWrap = null) {
    document.querySelectorAll('.admin-action-overflow.is-open').forEach((wrap) => {
        if (exceptWrap && wrap === exceptWrap) {
            return;
        }

        wrap.classList.remove('is-open');
        wrap.querySelector('[data-admin-overflow-toggle]')?.setAttribute('aria-expanded', 'false');
        restoreOverflowMenu(wrap);
    });
}

function positionActionOverflowMenu(wrap) {
    const toggle = wrap.querySelector('[data-admin-overflow-toggle]');
    const menu = getOverflowMenu(wrap);
    if (!toggle || !menu) {
        return;
    }

    if (!wrap.dataset.overflowId) {
        wrap.dataset.overflowId = `overflow-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    menu.dataset.overflowOwner = wrap.dataset.overflowId;
    wrap._overflowMenu = menu;
    document.body.appendChild(menu);
    menu.removeAttribute('hidden');

    const toggleRect = toggle.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const openUp = toggleRect.bottom + 8 + menuRect.height > window.innerHeight
        && toggleRect.top > menuRect.height + 8;
    const left = Math.min(
        toggleRect.right - menuRect.width,
        window.innerWidth - menuRect.width - 8,
    );

    menu.style.position = 'fixed';
    menu.style.zIndex = '200';
    menu.style.left = `${Math.max(8, left)}px`;
    menu.style.right = 'auto';

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
        const item = event.target.closest?.('.admin-action-overflow-item');
        if (item) {
            closeAllActionOverflows();
            return;
        }

        if (!event.target.closest?.('.admin-action-overflow')
            && !event.target.closest?.('.admin-action-overflow-menu')) {
            closeAllActionOverflows();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllActionOverflows();
        }
    });

    window.addEventListener('scroll', (event) => {
        if (event.target?.closest?.('.admin-action-overflow-menu')) {
            return;
        }

        closeAllActionOverflows();
    }, true);
    window.addEventListener('resize', () => closeAllActionOverflows());
}

function getActionTooltip(wrap) {
    return wrap._tooltip
        || wrap.querySelector('.admin-action-tooltip')
        || document.querySelector(`.admin-action-tooltip[data-tooltip-owner="${wrap.dataset.tooltipId}"]`);
}

function restoreAdminActionTooltip(wrap) {
    const tooltip = getActionTooltip(wrap);
    if (!tooltip) {
        return;
    }

    tooltip.classList.remove('is-ported', 'is-below');
    tooltip.style.position = '';
    tooltip.style.top = '';
    tooltip.style.left = '';
    tooltip.style.right = '';
    tooltip.style.bottom = '';
    tooltip.style.zIndex = '';

    if (tooltip.parentElement !== wrap) {
        wrap.appendChild(tooltip);
    }

    wrap._tooltip = null;
}

function positionAdminActionTooltip(wrap) {
    const tooltip = getActionTooltip(wrap);
    const button = wrap.querySelector('.admin-table-action');
    if (!tooltip || !button || wrap.classList.contains('admin-action-tooltip-wrap--suppressed')) {
        return;
    }

    if (!wrap.dataset.tooltipId) {
        wrap.dataset.tooltipId = `tip-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    tooltip.dataset.tooltipOwner = wrap.dataset.tooltipId;
    wrap._tooltip = tooltip;
    document.body.appendChild(tooltip);
    tooltip.classList.add('is-ported');

    const buttonRect = button.getBoundingClientRect();
    const tipRect = tooltip.getBoundingClientRect();
    const gap = 8;
    const spaceAbove = buttonRect.top;
    const spaceBelow = window.innerHeight - buttonRect.bottom;
    const openBelow = spaceAbove < tipRect.height + gap && spaceBelow > spaceAbove;

    tooltip.classList.toggle('is-below', openBelow);

    const left = Math.min(
        Math.max(8, buttonRect.right - tipRect.width),
        window.innerWidth - tipRect.width - 8,
    );

    tooltip.style.position = 'fixed';
    tooltip.style.zIndex = '220';
    tooltip.style.left = `${Math.max(8, left)}px`;
    tooltip.style.right = 'auto';
    tooltip.style.bottom = 'auto';
    tooltip.style.top = openBelow
        ? `${buttonRect.bottom + gap}px`
        : `${Math.max(8, buttonRect.top - tipRect.height - gap)}px`;
}

function closeAllPortedActionTooltips() {
    document.querySelectorAll('.admin-action-tooltip-wrap').forEach((wrap) => {
        restoreAdminActionTooltip(wrap);
    });
}

function bindTooltipDocumentListeners() {
    if (tooltipDocumentBound) {
        return;
    }

    tooltipDocumentBound = true;
    window.addEventListener('scroll', closeAllPortedActionTooltips, true);
    window.addEventListener('resize', closeAllPortedActionTooltips);
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
    bindTooltipDocumentListeners();

    rootEl.addEventListener('pointerenter', (event) => {
        const wrap = event.target.closest?.('.admin-action-tooltip-wrap');
        if (!wrap || wrap.querySelector('[data-admin-overflow-toggle]')) {
            return;
        }

        positionAdminActionTooltip(wrap);
    }, true);

    rootEl.addEventListener('focusin', (event) => {
        const wrap = event.target.closest?.('.admin-action-tooltip-wrap');
        if (!wrap || wrap.querySelector('[data-admin-overflow-toggle]')) {
            return;
        }

        positionAdminActionTooltip(wrap);
    });

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
        restoreAdminActionTooltip(wrap);
        wrap.querySelector('.admin-table-action')?.blur();
    });

    rootEl.addEventListener('pointerleave', (event) => {
        const wrap = event.target.closest?.('.admin-action-tooltip-wrap');
        if (!wrap) return;
        wrap.classList.remove('admin-action-tooltip-wrap--suppressed');
        restoreAdminActionTooltip(wrap);
    }, true);

    rootEl.addEventListener('focusout', (event) => {
        const wrap = event.target.closest?.('.admin-action-tooltip-wrap');
        if (!wrap) return;
        if (wrap.contains(event.relatedTarget)) {
            return;
        }

        wrap.classList.remove('admin-action-tooltip-wrap--suppressed');
        restoreAdminActionTooltip(wrap);
    }, true);
}
