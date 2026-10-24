@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Label groups</p>
                <div id="app-translations-group-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by group">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-mobile-screen">All labels</button>
                </div>
            </aside>
            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-language"></i>
                        </span>
                        <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-3">
                            <h2 class="admin-shell-title text-base sm:text-lg">App Translations</h2>
                            <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">Change every English and Tagalog label shown in the mobile app.</p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered lg:self-center">
                        <button type="button" class="admin-primary-button lg:w-auto" data-admin-action="create-app-translation">
                            Add Translation
                        </button>
                    </div>
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="app-translations-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
