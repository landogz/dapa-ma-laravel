@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6 relaxing-music-page">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Workflow</p>
                <div
                    class="admin-filter-tabs"
                    data-workflow-status-tabs="1"
                    role="tablist"
                    aria-label="Filter by workflow status"
                ></div>

                <p class="admin-filter-rail-title mt-5">Status</p>
                <div
                    id="relaxing-music-status-tabs"
                    class="admin-filter-tabs"
                    role="tablist"
                    aria-label="Filter by active status"
                >
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">All</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="active" data-icon="fas fa-circle-check">Active</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="inactive" data-icon="fas fa-circle-minus">Inactive</button>
                </div>
            </aside>

            <div class="admin-filter-main min-w-0">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-music"></i>
                        </span>
                        <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-3 min-w-0">
                            <h2 class="admin-shell-title text-base sm:text-lg">Relaxing Music</h2>
                            <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">
                                Upload and manage tracks for Care → Calm Corner → Relaxing Audio.
                            </p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered flex flex-wrap gap-2 xl:self-center">
                        <button type="button" class="admin-primary-button w-full sm:w-auto min-h-11" data-relaxing-music-action="add-track">
                            <i class="fas fa-plus mr-2" aria-hidden="true"></i>
                            Add tracks
                        </button>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div id="relaxing-music-meta" class="text-sm text-slate-500"></div>
                    <label class="relative block w-full sm:max-w-xs sm:ml-auto">
                        <span class="sr-only">Search tracks</span>
                        <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input
                            id="relaxing-music-search"
                            type="search"
                            class="admin-swal-input w-full pl-9 min-h-11"
                            placeholder="Search tracks…"
                            autocomplete="off"
                        >
                    </label>
                </div>

                <div id="relaxing-music-gallery" class="sticker-library-gallery mt-4" aria-live="polite"></div>
            </div>
        </div>
    </section>
@endsection
