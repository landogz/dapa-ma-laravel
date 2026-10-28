@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Edit by group</p>
                <div id="app-translations-group-tabs" class="admin-filter-tab-groups" role="tablist" aria-label="Translation groups"></div>
            </aside>
            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-language"></i>
                        </span>
                        <div class="flex min-w-0 flex-col sm:flex-row sm:items-baseline sm:gap-3">
                            <h2 id="app-translations-group-title" class="admin-shell-title text-base sm:text-lg">App Translations</h2>
                            <p id="app-translations-group-subtitle" class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">Choose a group, then edit its English and Tagalog labels.</p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered xl:self-center">
                        <button type="button" class="admin-primary-button xl:w-auto" data-admin-action="create-app-translation">
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
