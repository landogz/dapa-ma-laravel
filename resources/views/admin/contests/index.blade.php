@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Types</p>
                <div id="contests-category-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by contest type">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">All types</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="song" data-icon="fas fa-music">Song Contest</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="poster" data-icon="fas fa-image">Poster Contest</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="video" data-icon="fas fa-video">Video Contest</button>
                </div>
                <p class="admin-filter-rail-title">Statuses</p>
                <div id="contests-status-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by status">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">All statuses</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="draft" data-icon="fas fa-pen">Draft</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="open" data-icon="fas fa-folder-open">Open for submissions</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="closed" data-icon="fas fa-folder">Closed (reviewing)</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="completed" data-icon="fas fa-trophy">Completed</button>
                </div>
            </aside>
            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge admin-icon-badge-lg" aria-hidden="true">
                            <i class="fas fa-trophy"></i>
                        </span>
                        <div class="min-w-0 flex flex-col gap-1">
                            <h2 class="admin-shell-title text-lg sm:text-xl">Contests</h2>
                            <p class="admin-shell-subtitle max-w-2xl text-[11px] leading-relaxed sm:text-xs">
                                Manage Song, Poster, and Video contests from one place — choose a category when creating.
                            </p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered xl:self-center">
                        <button type="button" class="admin-primary-button xl:w-auto" data-admin-action="create-contest">
                            Add Contest
                        </button>
                    </div>
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="contests-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
