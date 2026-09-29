@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
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
            <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:w-auto lg:self-center">
                <label class="sr-only" for="diary-user-filter">Filter by user</label>
                <select id="diary-user-filter" class="admin-filter-select admin-choice-select-page w-full sm:w-56" aria-label="Filter by user">
                    <option value="">All users</option>
                </select>
                <label class="sr-only" for="diary-sky-filter">Filter by sky</label>
                <select id="diary-sky-filter" class="admin-filter-select admin-choice-select-page w-full sm:w-52" aria-label="Filter by sky">
                    <option value="">All skies</option>
                    <option value="clear_skies">Clear skies</option>
                    <option value="passing_mist">Passing mist</option>
                    <option value="overcast">Overcast</option>
                    <option value="stormy">Stormy</option>
                </select>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 px-4 py-3 text-sm text-slate-600">
            <i class="fas fa-mobile-screen-button mr-2 text-[#055498]"></i>
            Users create private journal notes in the <strong>Journal</strong> section of the DAPE-MA mobile app (one entry per day).
            Use the filters to review entries across all users for moderation.
        </div>

        <div class="admin-table-shell mt-6">
            <table id="diary-table" class="min-w-full text-left text-sm text-slate-700"></table>
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
