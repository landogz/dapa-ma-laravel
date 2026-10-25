@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Users</p>
                <div id="diary-user-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by user">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-users">All users</button>
                </div>
                <p class="admin-filter-rail-title">Skies</p>
                <div id="diary-sky-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by sky">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">All skies</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="clear_skies" data-icon="fas fa-sun">Clear skies</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="passing_mist" data-icon="fas fa-smog">Passing mist</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="overcast" data-icon="fas fa-cloud">Overcast</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="stormy" data-icon="fas fa-cloud-bolt">Stormy</button>
                </div>
            </aside>
            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-book-open"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="admin-shell-title text-base sm:text-lg">Journal</h2>
                            <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">
                                Review journal entries from all mobile app users.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 px-4 py-3 text-sm text-slate-600">
                    <i class="fas fa-mobile-screen-button mr-2 text-[#055498]"></i>
                    Users create private journal notes in the <strong>Journal</strong> section of the DAPE-MA mobile app. More than one entry can be saved on the same day.
                    Use the filters to review entries across all users for moderation.
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="diary-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>

        <div id="diary-context-menu" class="admin-context-menu" hidden>
            <button type="button" class="admin-context-menu-button" data-diary-context="view">
                <i class="fas fa-eye text-[#055498]"></i>
                <span>View</span>
            </button>
            <button type="button" class="admin-context-menu-button text-[#CE2028]" data-diary-context="delete">
                <i class="fas fa-trash"></i>
                <span>Delete</span>
            </button>
        </div>
    </section>
@endsection
