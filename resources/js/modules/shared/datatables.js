let dataTablesLibraryPromise;

export function getAdminDataTableOptions({
    columns,
    columnDefs = [],
    searchLabel = 'Search',
    searchPlaceholder,
    infoLabel,
    pageLength = 10,
    responsive = false,
    scrollX = true,
    scrollCollapse = true,
    deferRender = false,
    drawCallback,
    layout,
    language = {},
    hidePagingWhenSinglePage = true,
}) {
    const placeholder = searchPlaceholder || 'Search…';

    return {
        searching: true,
        paging: true,
        ordering: true,
        pageLength,
        responsive,
        autoWidth: false,
        scrollX,
        scrollCollapse,
        processing: true,
        deferRender,
        stripeClasses: [],
        hidePagingWhenSinglePage,
        layout: layout ?? {
            topStart: null,
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: ['pageLength', 'paging'],
        },
        language: {
            lengthMenu: '_MENU_ per page',
            info: infoLabel,
            infoEmpty: 'No records available',
            zeroRecords: 'No matching records found. Try another search or clear filters.',
            emptyTable: 'No records yet. Use Add to create the first one.',
            paginate: {
                previous: 'Prev',
                next: 'Next',
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
        ...tableOptions
    } = options;
    const table = new DataTable(tableElement, tableOptions);
    bindAdminTableSearchField(table, ariaSearchLabel
        || tableOptions.language?.searchPlaceholder
        || 'Search');

    if (hidePagingWhenSinglePage) {
        const syncPagingVisibility = () => {
            const pages = table.page.info().pages;
            table.table().container()
                ?.querySelector('.dt-paging')
                ?.classList.toggle('is-single-page', pages <= 1);
        };

        table.on('draw', syncPagingVisibility);
        syncPagingVisibility();
    }

    return table;
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
