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
}
