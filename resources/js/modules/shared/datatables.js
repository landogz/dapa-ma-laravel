import { bindAdminPageFocus } from './page-focus';
import { bindAdminTableContextMenu } from './table-context-menu';

let dataTablesLibraryPromise;

const PAGE_LENGTH_KEY = 'dape-admin-dt-page-length';
const PAGE_LENGTHS = [10, 25, 50, 100];

export function getAdminDataTableOptions({
    columns,
    columnDefs = [],
    searchLabel = 'Search',
    searchPlaceholder,
    infoLabel,
    pageLength = 10,
    deferRender = false,
    drawCallback,
    layout,
    language = {},
    hidePagingWhenSinglePage = true,
    order,
}) {
    const placeholder = searchPlaceholder || 'Search…';
    const storedLength = readStoredPageLength(pageLength);

    return {
        searching: true,
        paging: true,
        ordering: true,
        pageLength: storedLength,
        lengthMenu: PAGE_LENGTHS,
        responsive: false,
        autoWidth: false,
        scrollX: false,
        scrollCollapse: false,
        processing: true,
        deferRender,
        stripeClasses: [],
        hidePagingWhenSinglePage,
        order,
        layout: layout ?? {
            topStart: null,
            topEnd: 'search',
            bottomStart: {
                rowClass: 'admin-dt-footer',
                features: ['info'],
            },
            bottomEnd: [
                'pageLength',
                {
                    paging: {
                        buttons: 7,
                        firstLast: false,
                        previousNext: true,
                        numbers: true,
                        boundaryNumbers: true,
                    },
                },
            ],
        },
        language: {
            lengthMenu: 'Rows per page _MENU_',
            info: compactInfoLabel(infoLabel),
            infoEmpty: 'No records available',
            zeroRecords: 'No matching records found. Try another search or clear filters.',
            emptyTable: 'No records yet. Use Add to create the first one.',
            paginate: {
                previous: '‹',
                next: '›',
            },
            aria: {
                paginate: {
                    first: 'First page',
                    last: 'Last page',
                    previous: 'Previous page',
                    next: 'Next page',
                },
            },
            ...language,
            search: '',
            searchPlaceholder: placeholder,
        },
        ariaSearchLabel: searchLabel || placeholder,
        columns,
        columnDefs,
        drawCallback,
    };
}

export function loadAdminDataTableLibrary() {
    if (!dataTablesLibraryPromise) {
        dataTablesLibraryPromise = import('datatables.net-bs5')
            .then((module) => module.default);
    }

    return dataTablesLibraryPromise;
}

export async function createAdminDataTable(tableElement, options) {
    const DataTable = await loadAdminDataTableLibrary();
    const {
        hidePagingWhenSinglePage = true,
        ariaSearchLabel,
        ...rawOptions
    } = options;
    const tableOptions = applyAdminTableCollapse(rawOptions);
    const table = new DataTable(tableElement, tableOptions);
    bindAdminTableSearchField(table, ariaSearchLabel
        || tableOptions.language?.searchPlaceholder
        || 'Search');
    bindAdminTableCollapseControls(table, tableOptions);
    bindAdminDataTableFooter(table, hidePagingWhenSinglePage);
    bindAdminPageFocus(table);
    bindAdminTableContextMenu(tableElement);

    return table;
}

function applyAdminTableCollapse(options = {}) {
    const columns = options.columns ?? [];
    if (isMobileCardTable(columns)) {
        return {
            ...options,
            scrollX: false,
            scrollCollapse: false,
            responsive: false,
        };
    }

    const collapseColumns = [
        {
            title: '',
            data: null,
            defaultContent: '',
            orderable: false,
            searchable: false,
            className: 'dtr-control dt-col-toggle',
            width: '3rem',
        },
        ...columns.map((column, index) => ({
            ...column,
            data: column.data ?? index,
        })),
    ];

    return {
        ...options,
        columns: collapseColumns,
        scrollX: false,
        scrollCollapse: false,
        autoWidth: false,
        responsive: false,
        order: shiftOrderForControlColumn(options.order),
        columnDefs: [
            { orderable: false, searchable: false, targets: 0 },
            ...(options.columnDefs ?? []),
        ],
    };
}

function isMobileCardTable(columns = []) {
    if (columns.length === 0) {
        return false;
    }

    return columns.every((column) => {
        const className = String(column.className || '');
        return className.includes('dt-col-mobile-summary')
            || className.includes('dt-col-actions');
    });
}

function shiftOrderForControlColumn(order) {
    if (!Array.isArray(order) || order.length === 0) {
        return [[1, 'asc']];
    }

    return order.map(([index, direction]) => [Number(index) + 1, direction]);
}

function bindAdminTableSearchField(table, ariaLabel) {
    const container = table.table().container();
    if (!container) {
        return;
    }

    container.querySelectorAll('.dt-search > label').forEach((label) => {
        if (!label.querySelector('input')) {
            label.classList.add('sr-only');
        }
    });

    const input = container.querySelector('.dt-search input');
    if (input) {
        input.setAttribute('aria-label', ariaLabel);
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('enterkeyhint', 'search');
    }
}

function bindAdminTableCollapseControls(table, tableOptions) {
    const container = table.table().container();
    if (!container || container.dataset.collapseBound === '1') {
        return;
    }

    if (isMobileCardTable(tableOptions.columns ?? [])) {
        return;
    }

    container.dataset.collapseBound = '1';
    container.classList.add('admin-dt-collapse');
    table._adminCollapseColumns = tableOptions.columns ?? [];

    const scheduleSync = () => scheduleAdminTableCollapse(table);
    table.on('draw', scheduleSync);

    container.addEventListener('click', (event) => {
        const cell = event.target.closest('td.dtr-control');
        if (!cell || !container.contains(cell)) {
            return;
        }

        event.preventDefault();
        toggleAdminTableChildRow(table, cell.closest('tr'));
    });

    container.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const cell = event.target.closest('td.dtr-control');
        if (!cell || !container.contains(cell)) {
            return;
        }

        event.preventDefault();
        toggleAdminTableChildRow(table, cell.closest('tr'));
    });

    const onResize = () => scheduleSync();
    window.addEventListener('resize', onResize);

    const host = container.querySelector('.dt-layout-table') || container;
    if (typeof ResizeObserver === 'function' && host) {
        const observer = new ResizeObserver(() => scheduleSync());
        observer.observe(host);
        table._adminCollapseObserver = observer;
    }

    table.on('destroy', () => {
        window.removeEventListener('resize', onResize);
        table._adminCollapseObserver?.disconnect();
        cancelAnimationFrame(table._adminCollapseFrame || 0);
        window.clearTimeout(table._adminCollapseTimer);
        delete table._adminCollapseObserver;
        delete table._adminCollapseFrame;
        delete table._adminCollapseTimer;
        delete container.dataset.collapseBound;
        container.classList.remove('admin-dt-collapse');
        delete table._adminCollapseColumns;
        delete table._adminHiddenIndexes;
    });

    scheduleSync();
}

function scheduleAdminTableCollapse(table) {
    cancelAnimationFrame(table._adminCollapseFrame || 0);
    window.clearTimeout(table._adminCollapseTimer);

    const run = () => syncAdminTableCollapse(table);
    table._adminCollapseFrame = requestAnimationFrame(() => {
        run();
        table._adminCollapseTimer = window.setTimeout(run, 120);
    });
}

function syncAdminTableCollapse(table) {
    if (table._adminCollapsing) {
        return;
    }

    const container = table.table().container();
    const tableNode = table.table().node();
    if (!container || !tableNode) {
        return;
    }

    const columnCount = table.columns().count();
    if (columnCount < 3) {
        return;
    }

    const actionsIndex = columnCount - 1;
    const host = container.querySelector('.dt-layout-table') || tableNode.parentElement;
    const available = Math.floor(host?.clientWidth || container.clientWidth);
    if (available < 80) {
        return;
    }

    table._adminCollapsing = true;

    try {
        const candidates = [];
        for (let index = actionsIndex - 1; index >= 1; index -= 1) {
            const header = getAdminTableColumnHeader(table, index);
            const className = String(header?.className || '');
            const pinned = isPinnedAdminColumn(index, className, actionsIndex);
            candidates.push({ index, pinned });
        }

        const hideCandidates = candidates.filter((column) => !column.pinned);

        applyHiddenColumnClasses(tableNode, [], false);
        tableNode.classList.remove('collapsed');

        const hiddenIndexes = [];
        const tableOverflows = () => tableNode.scrollWidth > available + 4;

        if (tableOverflows()) {
            hideCandidates.forEach((column) => {
                if (!tableOverflows() || hiddenIndexes.includes(column.index)) {
                    return;
                }

                hiddenIndexes.push(column.index);
                applyHiddenColumnClasses(tableNode, hiddenIndexes, true);
                tableNode.classList.add('collapsed');
            });
        } else {
            applyHiddenColumnClasses(tableNode, [], false);
        }

        table._adminHiddenIndexes = hiddenIndexes;
        tableNode.classList.toggle('collapsed', hiddenIndexes.length > 0);
        container.classList.toggle(
            'admin-dt-has-x-overflow',
            tableNode.scrollWidth > available + 4,
        );

        table.rows().every(function hideStaleChild() {
            if (hiddenIndexes.length === 0 && this.child.isShown()) {
                this.child.hide();
                this.node()?.classList.remove('parent');
            }
        });

        syncAdminTableCollapseLabels(container);
    } finally {
        table._adminCollapsing = false;
    }
}

function isPinnedAdminColumn(index, className, actionsIndex) {
    if (index === 1 || index === actionsIndex) {
        return true;
    }

    return className.includes('dt-col-primary')
        || className.includes('dt-col-id')
        || className.includes('dt-col-actions');
}

function getAdminTableColumnHeader(table, index) {
    const header = table.column(index).header();
    return header?.closest?.('th') || header || null;
}

function applyHiddenColumnClasses(tableNode, hiddenIndexes, showToggle) {
    tableNode.querySelectorAll('tr').forEach((row) => {
        if (row.classList.contains('child')) {
            return;
        }

        [...row.children].forEach((cell, index) => {
            const columnIndex = Number(cell.getAttribute('data-dt-column') ?? index);
            const hide = columnIndex === 0 ? !showToggle : hiddenIndexes.includes(columnIndex);
            cell.classList.toggle('is-collapsed-col', hide);
        });
    });
}

function toggleAdminTableChildRow(table, rowNode) {
    if (!rowNode) {
        return;
    }

    const row = table.row(rowNode);
    if (!row || row.length === 0) {
        return;
    }

    if (row.child.isShown()) {
        row.child.hide();
        rowNode.classList.remove('parent');
        syncAdminTableCollapseLabels(table.table().container());
        return;
    }

    const html = renderAdminTableChildDetails(table, row);
    if (!html) {
        return;
    }

    row.child(html, 'child').show();
    rowNode.classList.add('parent');
    syncAdminTableCollapseLabels(table.table().container());
}

function renderAdminTableChildDetails(table, row) {
    const hiddenIndexes = table._adminHiddenIndexes ?? [];
    const columns = table._adminCollapseColumns ?? [];
    const data = row.data();
    if (hiddenIndexes.length === 0 || !Array.isArray(data)) {
        return '';
    }

    const items = hiddenIndexes
        .slice()
        .sort((left, right) => left - right)
        .map((index) => {
            const column = columns[index] || {};
            const title = column.title || 'Details';
            const sourceIndex = Number.isInteger(column.data) ? column.data : index - 1;
            const value = data[sourceIndex];
            const display = value === undefined || value === null || value === ''
                ? '—'
                : value;

            return `<div class="admin-dt-child-item">
                <span class="admin-dt-child-label">${title}</span>
                <div class="admin-dt-child-value">${display}</div>
            </div>`;
        })
        .join('');

    return `<div class="admin-dt-child">${items}</div>`;
}

function syncAdminTableCollapseLabels(container) {
    if (!container) {
        return;
    }

    container.querySelectorAll('td.dtr-control').forEach((cell) => {
        const expanded = cell.closest('tr')?.classList.contains('parent');
        cell.setAttribute('role', 'button');
        cell.setAttribute('tabindex', '0');
        cell.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        cell.setAttribute('aria-label', expanded ? 'Hide extra columns' : 'Show extra columns');
    });
}

function compactInfoLabel(infoLabel) {
    const fallback = 'Showing <b>_START_</b>-<b>_END_</b> of <b>_TOTAL_</b> entries';
    if (!infoLabel) {
        return fallback;
    }

    return String(infoLabel).replace(
        /Showing\s+_START_\s+to\s+_END_\s+of\s+_TOTAL_/i,
        'Showing <b>_START_</b>-<b>_END_</b> of <b>_TOTAL_</b>',
    );
}

function readStoredPageLength(fallback) {
    try {
        const stored = Number(window.localStorage.getItem(PAGE_LENGTH_KEY));
        if (PAGE_LENGTHS.includes(stored)) {
            return stored;
        }
    } catch {
        // Private mode or storage blocked — keep the table default.
    }

    return PAGE_LENGTHS.includes(fallback) ? fallback : PAGE_LENGTHS[0];
}

function persistPageLength(table) {
    if (!table || typeof table.page !== 'function') {
        return;
    }

    try {
        const length = Number(table.page.len());
        if (PAGE_LENGTHS.includes(length)) {
            window.localStorage.setItem(PAGE_LENGTH_KEY, String(length));
        }
    } catch {
        // Ignore storage write failures.
    }
}

function bindAdminDataTableFooter(table, hidePagingWhenSinglePage) {
    const container = table.table().container();
    if (!container) {
        return;
    }

    const syncFooter = () => {
        persistPageLength(table);
        enhanceAdminDataTableFooter(container, table);

        if (!hidePagingWhenSinglePage) {
            return;
        }

        const pages = table.page.info().pages;
        container.querySelector('.dt-paging')
            ?.classList.toggle('is-single-page', pages <= 1);
    };

    table.on('draw', syncFooter);
    table.on('length', syncFooter);
    syncFooter();
}

function enhanceAdminDataTableFooter(container, table) {
    const info = container.querySelector('.dt-info');
    const length = container.querySelector('.dt-length');
    const paging = container.querySelector('.dt-paging');
    const footer = container.querySelector('.admin-dt-footer')
        || info?.closest('.dt-layout-row')
        || length?.closest('.dt-layout-row');

    if (footer) {
        footer.classList.add('admin-dt-footer');

        let controls = footer.querySelector('.admin-dt-footer-controls');
        if (!controls) {
            controls = document.createElement('div');
            controls.className = 'admin-dt-footer-controls';
        }

        if (info && info.parentElement !== footer) {
            footer.appendChild(info);
        }

        if (controls.parentElement !== footer) {
            footer.appendChild(controls);
        }

        [length, paging].forEach((node) => {
            if (node && node.parentElement !== controls) {
                controls.appendChild(node);
            }
        });

        footer.querySelectorAll('.dt-layout-cell').forEach((cell) => {
            if (!cell.children.length) {
                cell.remove();
            }
        });
    }

    if (info) {
        info.setAttribute('aria-live', 'polite');
        info.classList.add('admin-dt-info');
        splitInfoForMobile(info);
    }

    if (length) {
        length.classList.add('admin-dt-length');
    }

    if (paging) {
        paging.classList.add('admin-dt-pager');
        markPagerEdges(paging);
        syncMobilePageStatus(paging, table);
    }
}

function markPagerEdges(paging) {
    paging.querySelectorAll('.page-item, .dt-paging-button').forEach((item) => {
        item.classList.toggle('previous', Boolean(item.querySelector('.previous') || item.classList.contains('previous')));
        item.classList.toggle('next', Boolean(item.querySelector('.next') || item.classList.contains('next')));
    });
}

function splitInfoForMobile(info) {
    if (info.querySelector('.admin-dt-info-full') || info.querySelector('.admin-dt-info-short')) {
        return;
    }

    const html = info.innerHTML.trim();
    const text = info.textContent.trim();
    if (!html || /^no /i.test(text)) {
        return;
    }

    const match = text.match(/(\d+)\s*-\s*(\d+)\s+of\s+(\d+)/i);
    info.innerHTML = `<span class="admin-dt-info-full">${html}</span>`;

    if (match) {
        const short = document.createElement('span');
        short.className = 'admin-dt-info-short';
        short.innerHTML = `<b>${match[1]}</b>-<b>${match[2]}</b> of <b>${match[3]}</b>`;
        info.appendChild(short);
    }
}

function syncMobilePageStatus(paging, table) {
    let status = paging.querySelector('.admin-dt-page-status');

    if (!status) {
        status = document.createElement('span');
        status.className = 'admin-dt-page-status';
        status.setAttribute('aria-hidden', 'true');
        paging.appendChild(status);
    }

    if (!table || typeof table.page !== 'function') {
        status.hidden = true;
        return;
    }

    const pageInfo = table.page.info();
    const pages = Number(pageInfo.pages) || 0;
    const current = pages > 0 ? Number(pageInfo.page) + 1 : 0;
    status.textContent = pages > 0 ? `Page ${current} of ${pages}` : '';
    status.hidden = pages <= 1;
}
