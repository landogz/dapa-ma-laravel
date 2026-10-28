const SKIP_SELECTOR = [
    '.dt-col-actions',
    '.admin-table-actions',
    '.admin-table-action',
    'input',
    'textarea',
    'select',
    'script',
    'style',
    'mark',
].join(', ');

export function readAdminPageFocus() {
    const params = new URLSearchParams(window.location.search);

    return {
        q: String(params.get('q') || '').trim(),
        id: String(params.get('id') || '').trim(),
        group: String(params.get('group') || '').trim(),
    };
}

export function buildAdminFocusHref(path, { q, id, group } = {}) {
    const [pathname, existing = ''] = String(path || '').split('?');
    const params = new URLSearchParams(existing);

    if (q) {
        params.set('q', q);
    }

    if (id !== undefined && id !== null && String(id) !== '') {
        params.set('id', String(id));
    }

    if (group) {
        params.set('group', group);
    }

    const query = params.toString();

    return query ? `${pathname}?${query}` : pathname;
}

export function highlightAdminPageMatches(root, query) {
    const needle = String(query || '').trim();

    if (!root) {
        return [];
    }

    root.querySelectorAll('mark.admin-mega-mark').forEach((mark) => {
        mark.replaceWith(document.createTextNode(mark.textContent || ''));
    });
    root.querySelectorAll('.admin-dt-focus-row').forEach((row) => {
        row.classList.remove('admin-dt-focus-row');
    });

    if (!needle) {
        return [];
    }

    const pattern = new RegExp(`(${escapeRegExp(needle)})`, 'ig');
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const parent = node.parentElement;

            if (!parent || parent.closest(SKIP_SELECTOR) || !node.nodeValue) {
                return NodeFilter.FILTER_REJECT;
            }

            pattern.lastIndex = 0;

            return pattern.test(node.nodeValue)
                ? NodeFilter.FILTER_ACCEPT
                : NodeFilter.FILTER_REJECT;
        },
    });

    const nodes = [];

    while (walker.nextNode()) {
        nodes.push(walker.currentNode);
    }

    const rows = [];

    nodes.forEach((node) => {
        pattern.lastIndex = 0;
        const value = node.nodeValue || '';
        const fragment = document.createDocumentFragment();
        let cursor = 0;

        value.replace(pattern, (match, _group, offset) => {
            if (offset > cursor) {
                fragment.appendChild(document.createTextNode(value.slice(cursor, offset)));
            }

            const mark = document.createElement('mark');
            mark.className = 'admin-mega-mark';
            mark.textContent = match;
            fragment.appendChild(mark);
            cursor = offset + match.length;

            return match;
        });

        if (cursor === 0) {
            return;
        }

        if (cursor < value.length) {
            fragment.appendChild(document.createTextNode(value.slice(cursor)));
        }

        const row = node.parentElement?.closest('tr, .admin-settings-tile, [data-legal-slug]');
        node.parentNode?.replaceChild(fragment, node);

        if (row) {
            row.classList.add('admin-dt-focus-row');
            if (!rows.includes(row)) {
                rows.push(row);
            }
        }
    });

    return rows;
}

export function bindAdminPageFocus(table) {
    const { q, id } = readAdminPageFocus();

    if ((!q && !id) || !table) {
        return;
    }

    if (q) {
        table.search(q);
        const input = table.table().container()?.querySelector('.dt-search input');

        if (input && input.value !== q) {
            input.value = q;
        }
    }

    let didScroll = false;

    table.on('draw', () => {
        const api = table.table();
        const body = (typeof api.body === 'function' ? api.body() : null)
            || api.container()?.querySelector('tbody');
        const marked = q ? highlightAdminPageMatches(body, q) : [];
        marked.forEach((row) => row.classList.remove('admin-dt-focus-row'));
        const byId = findRowByRecordId(body, id);
        const first = byId || marked[0];

        if (first) {
            first.classList.add('admin-dt-focus-row');
        }

        if (!didScroll && first) {
            didScroll = true;
            first.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    });
}

function findRowByRecordId(body, id) {
    if (!body || !id) {
        return null;
    }

    const pattern = new RegExp(`\\(${escapeRegExp(id)}\\)`);

    return [...body.querySelectorAll('tr')].find((row) => {
        const actions = row.querySelector('.dt-col-actions') || row;

        return pattern.test(actions.innerHTML);
    }) || null;
}

function escapeRegExp(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
