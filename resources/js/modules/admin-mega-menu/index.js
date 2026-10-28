import axios from 'axios';
import { buildAdminFocusHref } from '../shared/page-focus';
import { ADMIN_FUNCTION_CATALOG } from './catalog';

const GROUP_ORDER = [
    'Overview',
    'Content',
    'Services',
    'Care',
    'Hope',
    'Reach',
    'Administration',
    'Account',
    'Records',
];

let allowedSections = [];
let isOpen = false;
let activeIndex = -1;
let recordResults = [];
let searchTimer = 0;
let latestQuery = '';

export function initAdminMegaMenu(sections = []) {
    allowedSections = Array.isArray(sections) ? sections : [];

    const root = document.querySelector('[data-admin-mega]');

    if (!root || root.dataset.bound === '1') {
        return;
    }

    root.dataset.bound = '1';

    const panel = root.querySelector('[data-admin-mega-panel]');
    const backdrop = document.querySelector('[data-admin-mega-backdrop]');
    const desktopInput = root.querySelector('[data-admin-mega-input="desktop"]');
    const panelInput = root.querySelector('[data-admin-mega-input="panel"]');
    const results = root.querySelector('[data-admin-mega-results]');
    const openButtons = document.querySelectorAll('[data-admin-mega-open]');

    const inputs = [desktopInput, panelInput].filter(Boolean);
    let ignoreDocumentClick = false;

    const setOpen = (nextOpen) => {
        isOpen = nextOpen;
        panel?.classList.toggle('hidden', !nextOpen);
        backdrop?.classList.toggle('hidden', !nextOpen);
        document.body.classList.toggle('admin-mega-open', nextOpen);
        openButtons.forEach((button) => {
            button.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
        });
        desktopInput?.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');

        if (nextOpen) {
            document.getElementById('admin-account-menu')?.classList.add('hidden');
            document.getElementById('admin-notifications-panel')?.classList.add('hidden');
            if (document.getElementById('admin-app')?.classList.contains('sidebar-open')) {
                document.getElementById('sidebar-toggle')?.click();
            }
            ignoreDocumentClick = true;
            window.setTimeout(() => {
                ignoreDocumentClick = false;
            }, 0);
            syncQuery(desktopInput?.value || panelInput?.value || '');
            render();
            window.setTimeout(() => {
                const target = window.matchMedia('(max-width: 1023px)').matches
                    ? panelInput
                    : (desktopInput || panelInput);
                target?.focus();
                target?.select();
            }, 20);
        } else {
            activeIndex = -1;
        }
    };

    const syncQuery = (value) => {
        latestQuery = String(value ?? '');
        inputs.forEach((input) => {
            if (input.value !== latestQuery) {
                input.value = latestQuery;
            }
        });
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            setOpen(!isOpen);
        });
    });

    desktopInput?.addEventListener('focus', () => {
        if (!isOpen) {
            setOpen(true);
        }
    });

    inputs.forEach((input) => {
        input.addEventListener('input', () => {
            syncQuery(input.value);
            activeIndex = 0;
            if (latestQuery.trim().length < 2) {
                recordResults = [];
            }
            render();
            scheduleRecordSearch(latestQuery);
        });

        input.addEventListener('keydown', (event) => {
            handleKeydown(event, setOpen);
        });
    });

    backdrop?.addEventListener('click', () => setOpen(false));

    document.addEventListener('click', (event) => {
        if (!isOpen || ignoreDocumentClick) {
            return;
        }

        if (root.contains(event.target) || event.target.closest('[data-admin-mega-open]')) {
            return;
        }

        setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        const key = String(event.key || '').toLowerCase();
        const isPalette = (event.metaKey || event.ctrlKey) && key === 'k';

        if (isPalette) {
            event.preventDefault();
            setOpen(true);
            return;
        }

        if (event.key === 'Escape' && isOpen) {
            event.preventDefault();
            setOpen(false);
        }
    });

    const shortcut = root.querySelector('.admin-mega-kbd');

    if (shortcut && !/Mac|iPhone|iPad/i.test(navigator.platform || navigator.userAgent)) {
        shortcut.textContent = 'Ctrl K';
    }

    results?.addEventListener('mousemove', (event) => {
        const item = event.target.closest('[data-admin-mega-index]');

        if (!item) {
            return;
        }

        activeIndex = Number(item.getAttribute('data-admin-mega-index'));
        paintActive(results);
    });

    window.AdminMegaMenu = {
        open: () => setOpen(true),
        close: () => setOpen(false),
    };

    render();
}

function visibleFunctions() {
    return ADMIN_FUNCTION_CATALOG.filter((item) => (
        !item.section || allowedSections.includes(item.section)
    ));
}

function matchingFunctions(query) {
    const list = visibleFunctions();
    const needle = query.trim().toLowerCase();

    if (!needle) {
        return list;
    }

    return list.filter((item) => {
        const haystack = [
            item.title,
            item.description,
            item.group,
            item.href,
            ...(item.keywords || []),
        ].join(' ').toLowerCase();

        return haystack.includes(needle);
    });
}

function allItems(query) {
    const functions = matchingFunctions(query).map((item) => ({
        ...item,
        kind: 'function',
    }));

    if (!query.trim()) {
        return functions.filter((item) => item.pin !== false);
    }

    const records = recordResults.map((item) => ({
        ...item,
        kind: 'record',
        group: item.group || 'Records',
        description: item.subtitle || 'Record',
        icon: item.icon || 'fas fa-magnifying-glass',
    }));

    return [...functions, ...records];
}

function render() {
    const results = document.querySelector('[data-admin-mega-results]');
    const hint = document.querySelector('[data-admin-mega-hint]');

    if (!results) {
        return;
    }

    const query = latestQuery.trim();
    const items = allItems(query);

    if (hint) {
        hint.textContent = query
            ? `${items.length} result${items.length === 1 ? '' : 's'}`
            : 'Browse every admin function, or type to search pages, actions, and records.';
    }

    if (!items.length) {
        results.innerHTML = `
            <div class="admin-mega-empty">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <p>No matches for “${escapeHtml(query)}”.</p>
            </div>
        `;
        return;
    }

    if (query) {
        results.innerHTML = `
            <div class="admin-mega-list" role="listbox">
                ${items.map((item, index) => renderItem(item, index, query)).join('')}
            </div>
        `;
        paintActive(results);
        return;
    }

    const grouped = groupItems(items);
    results.innerHTML = `
        <div class="admin-mega-grid">
            ${grouped.map((group) => `
                <section class="admin-mega-column">
                    <p class="admin-mega-column-title">${escapeHtml(group.name)}</p>
                    <div class="admin-mega-column-list">
                        ${group.items.map((item, index) => renderItem(item, items.indexOf(item), '')).join('')}
                    </div>
                </section>
            `).join('')}
        </div>
    `;
}

function groupItems(items) {
    const buckets = new Map();

    items.forEach((item) => {
        const name = item.group || 'Other';
        if (!buckets.has(name)) {
            buckets.set(name, []);
        }
        buckets.get(name).push(item);
    });

    return GROUP_ORDER
        .filter((name) => buckets.has(name))
        .map((name) => ({ name, items: buckets.get(name) }));
}

function resolveHref(item, query) {
    if (item.kind === 'record') {
        return item.href;
    }

    return buildAdminFocusHref(item.href, { q: query.trim() });
}

function renderItem(item, index, query) {
    const kindLabel = item.kind === 'record' ? item.group : item.description;

    return `
        <a
            href="${escapeHtml(resolveHref(item, query))}"
            class="admin-mega-item"
            role="option"
            data-admin-mega-index="${index}"
            aria-selected="false"
        >
            <span class="admin-mega-item-icon">
                <i class="${escapeHtml(item.icon)}" aria-hidden="true"></i>
            </span>
            <span class="admin-mega-item-copy">
                <span class="admin-mega-item-title">${highlight(item.title, query)}</span>
                <span class="admin-mega-item-desc">${highlight(kindLabel || '', query)}</span>
            </span>
            <span class="admin-mega-item-meta">${escapeHtml(item.group || '')}</span>
        </a>
    `;
}

function handleKeydown(event, setOpen) {
    const items = allItems(latestQuery);

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!isOpen) {
            setOpen(true);
        }
        activeIndex = Math.min(items.length - 1, activeIndex + 1);
        paintActive(document.querySelector('[data-admin-mega-results]'));
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex = Math.max(0, activeIndex - 1);
        paintActive(document.querySelector('[data-admin-mega-results]'));
        return;
    }

    if (event.key === 'Enter') {
        const current = items[activeIndex] || items[0];

        if (current?.href) {
            event.preventDefault();
            window.location.href = resolveHref(current, latestQuery);
        }
    }
}

function paintActive(container) {
    if (!container) {
        return;
    }

    container.querySelectorAll('[data-admin-mega-index]').forEach((node) => {
        const isActive = Number(node.getAttribute('data-admin-mega-index')) === activeIndex;
        node.classList.toggle('is-active', isActive);
        node.setAttribute('aria-selected', isActive ? 'true' : 'false');

        if (isActive) {
            node.scrollIntoView({ block: 'nearest' });
        }
    });
}

function scheduleRecordSearch(query) {
    window.clearTimeout(searchTimer);
    const needle = query.trim();

    if (needle.length < 2) {
        recordResults = [];
        return;
    }

    searchTimer = window.setTimeout(() => {
        axios.get('/admin/search', { params: { q: needle } })
            .then(({ data }) => {
                if (latestQuery.trim() !== needle) {
                    return;
                }

                recordResults = Array.isArray(data.data) ? data.data : [];
                render();
            })
            .catch(() => {
                if (latestQuery.trim() !== needle) {
                    return;
                }

                recordResults = [];
                render();
            });
    }, 220);
}

function highlight(value, query) {
    const safe = escapeHtml(value || '');
    const needle = query.trim();

    if (!needle) {
        return safe;
    }

    const pattern = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return safe.replace(new RegExp(`(${pattern})`, 'ig'), '<mark class="admin-mega-mark">$1</mark>');
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}
