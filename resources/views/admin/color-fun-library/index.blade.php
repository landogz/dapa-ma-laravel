@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6 color-fun-library-page">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-palette"></i>
                </span>
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-3 min-w-0">
                    <h2 class="admin-shell-title text-base sm:text-lg">Color Fun Library</h2>
                    <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">
                        Manage coloring worksheets shown in the mobile Color Fun gallery.
                    </p>
                </div>
            </div>
            <div class="admin-page-actions admin-page-actions-centered flex flex-wrap gap-2 xl:self-center">
                <button type="button" class="admin-primary-button w-full sm:w-auto min-h-11" data-color-fun-action="add-worksheet">
                    <i class="fas fa-plus mr-2" aria-hidden="true"></i>
                    Add worksheets
                </button>
            </div>
        </div>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div
                id="color-fun-category-chips"
                class="sticker-pack-chips flex flex-wrap gap-2"
                role="tablist"
                aria-label="Worksheet categories"
            ></div>
            <label class="relative block w-full sm:max-w-xs">
                <span class="sr-only">Search worksheets</span>
                <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                <input
                    id="color-fun-library-search"
                    type="search"
                    class="admin-swal-input w-full pl-9 min-h-11"
                    placeholder="Search worksheets…"
                    autocomplete="off"
                >
            </label>
        </div>

        <div id="color-fun-library-meta" class="mt-3 text-sm text-slate-500"></div>

        <div id="color-fun-library-gallery" class="sticker-library-gallery mt-4" aria-live="polite"></div>
    </section>
@endsection
