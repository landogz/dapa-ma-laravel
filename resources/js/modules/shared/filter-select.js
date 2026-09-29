/**
 * Turns native <select class="admin-filter-select"> into branded interactive dropdowns.
 * Keeps the original select in sync so existing change listeners still work.
 */

let documentListenersBound = false;
let mutationObserver = null;

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function closeAllChoiceSelects(exceptWrap = null) {
    document.querySelectorAll('.admin-choice-select.is-open').forEach((wrap) => {
        if (exceptWrap && wrap === exceptWrap) {
            return;
        }

        wrap.classList.remove('is-open');
        wrap.querySelector('.admin-choice-select-trigger')?.setAttribute('aria-expanded', 'false');
        wrap.querySelector('.admin-choice-select-menu')?.setAttribute('hidden', 'hidden');
    });
}

function bindDocumentListenersOnce() {
    if (documentListenersBound) {
        return;
    }

    documentListenersBound = true;

    document.addEventListener('click', (event) => {
        const wrap = event.target.closest?.('.admin-choice-select');
        if (!wrap) {
            closeAllChoiceSelects();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllChoiceSelects();
        }
    });
}

function selectedLabel(select) {
    const option = select.selectedOptions?.[0] ?? select.options?.[select.selectedIndex];
    return option?.textContent?.trim() || 'Select';
}

function rebuildMenu(wrap, select) {
    const menu = wrap.querySelector('.admin-choice-select-menu');
    if (!menu) {
        return;
    }

    menu.innerHTML = Array.from(select.options)
        .map((option, index) => {
            const isSelected = option.selected || String(option.value) === String(select.value);
            return `
                <button
                    type="button"
                    class="admin-choice-select-option${isSelected ? ' is-selected' : ''}"
                    role="option"
                    data-index="${index}"
                    data-value="${escapeHtml(option.value)}"
                    aria-selected="${isSelected ? 'true' : 'false'}"
                >
                    <span class="admin-choice-select-option-label">${escapeHtml(option.textContent.trim())}</span>
                    <i class="fas fa-check admin-choice-select-check" aria-hidden="true"></i>
                </button>
            `;
        })
        .join('');
}

function syncTriggerLabel(wrap, select) {
    const label = wrap.querySelector('.admin-choice-select-label');
    if (label) {
        label.textContent = selectedLabel(select);
    }
}

function setOpen(wrap, isOpen) {
    const trigger = wrap.querySelector('.admin-choice-select-trigger');
    const menu = wrap.querySelector('.admin-choice-select-menu');

    wrap.classList.toggle('is-open', isOpen);
    trigger?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

    if (!menu) {
        return;
    }

    if (isOpen) {
        menu.removeAttribute('hidden');
    } else {
        menu.setAttribute('hidden', 'hidden');
    }
}

/**
 * Enhance a single filter <select>. Safe to call repeatedly.
 */
export function enhanceAdminFilterSelect(select) {
    if (!(select instanceof HTMLSelectElement)) {
        return null;
    }

    if (!select.classList.contains('admin-filter-select')) {
        return null;
    }

    if (select.dataset.adminChoiceEnhanced === '1') {
        const existingWrap = select.closest('.admin-choice-select');
        if (existingWrap) {
            rebuildMenu(existingWrap, select);
            syncTriggerLabel(existingWrap, select);
            return existingWrap;
        }
    }

    select.dataset.adminChoiceEnhanced = '1';
    bindDocumentListenersOnce();

    const wrap = document.createElement('div');
    wrap.className = 'admin-choice-select';
    if (select.classList.contains('admin-filter-select-inline')) {
        wrap.classList.add('admin-choice-select-inline');
    }
    if (select.classList.contains('w-full') || select.className.includes('sm:w-')) {
        wrap.classList.add('admin-choice-select-page');
    }

    const ariaLabel = select.getAttribute('aria-label')
        || select.previousElementSibling?.textContent?.trim()
        || 'Filter';

    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('admin-choice-select-native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'admin-choice-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-label', ariaLabel);
    trigger.innerHTML = `
        <span class="admin-choice-select-label">${escapeHtml(selectedLabel(select))}</span>
        <i class="fas fa-chevron-down admin-choice-select-caret" aria-hidden="true"></i>
    `;

    const menu = document.createElement('div');
    menu.className = 'admin-choice-select-menu';
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('aria-label', ariaLabel);
    menu.setAttribute('hidden', 'hidden');

    wrap.appendChild(trigger);
    wrap.appendChild(menu);
    rebuildMenu(wrap, select);

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const willOpen = !wrap.classList.contains('is-open');
        closeAllChoiceSelects(wrap);
        if (willOpen) {
            rebuildMenu(wrap, select);
            syncTriggerLabel(wrap, select);
        }
        setOpen(wrap, willOpen);
    });

    menu.addEventListener('click', (event) => {
        const optionBtn = event.target.closest('.admin-choice-select-option');
        if (!optionBtn) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const nextValue = optionBtn.getAttribute('data-value') ?? '';
        if (String(select.value) !== String(nextValue)) {
            select.value = nextValue;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        rebuildMenu(wrap, select);
        syncTriggerLabel(wrap, select);
        setOpen(wrap, false);
    });

    select.addEventListener('change', () => {
        rebuildMenu(wrap, select);
        syncTriggerLabel(wrap, select);
    });

    return wrap;
}

/**
 * Enhance all filter selects under a root.
 */
export function enhanceAdminFilterSelects(root = document) {
    root.querySelectorAll('select.admin-filter-select').forEach((select) => {
        enhanceAdminFilterSelect(select);
    });
}

/**
 * Watch the admin shell so dynamically injected toolbar filters also get enhanced.
 */
export function startAdminFilterSelectWatcher(root = document.getElementById('admin-app') || document.body) {
    enhanceAdminFilterSelects(root);

    if (mutationObserver || !root) {
        return;
    }

    mutationObserver = new MutationObserver((mutations) => {
        const shouldScan = mutations.some((mutation) => {
            if (mutation.type !== 'childList' || mutation.addedNodes.length === 0) {
                return false;
            }

            return Array.from(mutation.addedNodes).some((node) => {
                if (!(node instanceof Element)) {
                    return false;
                }

                return node.matches?.('select.admin-filter-select')
                    || node.querySelector?.('select.admin-filter-select');
            });
        });

        if (shouldScan) {
            enhanceAdminFilterSelects(root);
        }
    });

    mutationObserver.observe(root, { childList: true, subtree: true });
}
