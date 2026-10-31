@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Catalogs</p>
                <div id="app-option-group-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by catalog">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">
                        All catalogs
                    </button>
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
                        <span class="admin-icon-badge">
                            <i class="fas fa-sliders"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="admin-shell-title text-base sm:text-lg">Lookups</h2>
                            <p class="mt-0.5 text-xs sm:text-sm admin-shell-subtitle">
                                Manage the option lists shown in registration and the journal — pronouns, persona, sky, feelings, and what shaped your day.
                            </p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center xl:w-auto xl:self-center">
                        <button type="button" class="admin-primary-button w-full sm:w-auto" data-admin-action="create-app-option">
                            Add option
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 px-4 py-3 text-sm text-slate-600">
                    <i class="fas fa-mobile-screen-button mr-2 text-[#055498]"></i>
                    Changes appear in the mobile app. <strong>Which best describes you</strong> allows multiple selections. Journal feelings still let users add their own chip.
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="app-options-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
