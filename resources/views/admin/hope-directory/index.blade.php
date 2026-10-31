@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Categories</p>
                <div id="hope-directory-category-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by category">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">All categories</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="government" data-icon="fas fa-landmark">Gov't</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="ngo" data-icon="fas fa-handshake">NGOs</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="school" data-icon="fas fa-school">Schools</button>
                </div>

                <p class="admin-filter-rail-title">Workflow</p>
                <div
                    class="admin-filter-tabs"
                    data-workflow-status-tabs="1"
                    role="tablist"
                    aria-label="Filter by workflow status"
                ></div>

            </aside>
            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge admin-icon-badge-lg" aria-hidden="true">
                            <i class="fas fa-address-book"></i>
                        </span>
                        <div class="min-w-0 flex flex-col gap-1">
                            <h2 class="admin-shell-title text-lg sm:text-xl">Hope Directory</h2>
                            <p class="admin-shell-subtitle max-w-2xl text-[11px] leading-relaxed sm:text-xs">
                                Curate government, NGO, and school partners shown in the DAPE Hope Official Directory.
                            </p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center xl:w-auto xl:self-center">
                        <button type="button" class="admin-primary-button w-full sm:w-auto" data-admin-action="create-hope-directory">
                            <i class="fas fa-plus mr-2" aria-hidden="true"></i>
                            Add Organization
                        </button>
                    </div>
                </div>

                <div class="admin-page-tip">
                    <i class="fas fa-mobile-screen-button admin-page-tip-icon" aria-hidden="true"></i>
                    <p class="m-0">
                        Organizations appear in the mobile Hope Directory with filters for Gov't, NGOs, and Schools. Use sort order to control listing priority.
                    </p>
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="hope-directory-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
