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
        deferRender: true,
        stripeClasses: [],
        layout: {
            topStart: 'pageLength',
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging',
        },
        language: {
            search: searchLabel,
            searchPlaceholder,
            lengthMenu: 'Show _MENU_ entries',
            info: infoLabel,
            infoEmpty: 'No records available',
            zeroRecords: 'No matching records found',
            emptyTable: 'No data available',
            paginate: {
                previous: 'Prev',
                next: 'Next',
            },
        },
        columns,
        columnDefs,
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

    return new DataTable(tableElement, options);
}
