@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-handshake-angle"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="admin-shell-title text-base sm:text-lg">Get Support Resources</h2>
                    <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">
                        Manage Hotlines, Counseling, and Crisis Support content shown in DAPE Care.
                    </p>
                </div>
            </div>
            <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:w-auto lg:self-center">
                <label class="sr-only" for="care-support-category-filter">Filter by category</label>
                <select id="care-support-category-filter" class="admin-filter-select admin-choice-select-page w-full sm:w-52" aria-label="Filter by category">
                    <option value="">All categories</option>
                    <option value="hotline">24/7 Hotlines</option>
                    <option value="counseling">Counseling</option>
                    <option value="crisis_emergency">Crisis emergency</option>
                    <option value="crisis_resource">Crisis resources</option>
                </select>
                <button type="button" class="admin-primary-button w-full sm:w-auto" data-admin-action="create-care-support-resource">
                    Add Resource
                </button>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 px-4 py-3 text-sm text-slate-600">
            <i class="fas fa-mobile-screen-button mr-2 text-[#055498]"></i>
            These entries power the mobile <strong>Get Support</strong> pages (Hotlines, Counseling, Crisis). Use body paragraphs for crisis info modals.
        </div>

        <div class="admin-table-shell mt-6">
            <table id="care-support-resources-table" class="min-w-full text-left text-sm text-slate-700"></table>
        </div>
    </section>
@endsection
