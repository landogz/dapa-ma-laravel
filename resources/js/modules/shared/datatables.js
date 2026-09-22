let dataTablesLibraryPromise;

export function getAdminDataTableOptions({
    columns,
    columnDefs = [],
    searchLabel = 'Search:',
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
            search: searchLabel,
            searchPlaceholder,
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
        },
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
    const { hidePagingWhenSinglePage = true, ...tableOptions } = options;
    const table = new DataTable(tableElement, tableOptions);

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
