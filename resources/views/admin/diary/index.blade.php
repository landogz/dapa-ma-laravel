@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <section class="admin-filter-rail-section">
                    <div class="admin-filter-rail-heading">
                        <p class="admin-filter-rail-title">Users</p>
                        <p class="admin-filter-rail-kicker">Filter entries by writer</p>
                    </div>
                    <label class="admin-filter-rail-search">
                        <span class="sr-only">Search writers</span>
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" data-diary-user-search placeholder="Search writers…" autocomplete="off">
                    </label>
                    <div id="diary-user-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by user"></div>
                    <p class="admin-filter-rail-empty hidden" data-diary-user-empty>No matching writers.</p>
                </section>
                <section class="admin-filter-rail-section">
                    <div class="admin-filter-rail-heading">
                        <p class="admin-filter-rail-title">Skies</p>
                        <p class="admin-filter-rail-kicker">Color mood of the day</p>
                    </div>
                    <div id="diary-sky-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by sky"></div>
                </section>
            </aside>
            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge shrink-0">
                            <i class="fas fa-book-open"></i>
                        </span>
                        <div class="min-w-0 flex-1">
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
    </section>
@endsection
