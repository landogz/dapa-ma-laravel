@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Status</p>
                <div id="word-blocking-status-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by status">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">All words</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="1" data-icon="fas fa-circle-check">Active</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="0" data-icon="fas fa-circle-minus">Inactive</button>
                </div>
            </aside>
            <div class="admin-filter-main min-w-0">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-ban"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="admin-shell-title text-base sm:text-lg">Word Blocking</h2>
                            <p class="mt-0.5 text-xs sm:text-sm admin-shell-subtitle">
                                Words the mobile app replaces with **** in user-generated text.
                            </p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center xl:w-auto xl:self-center">
                        <button type="button" class="admin-primary-button w-full sm:w-auto" data-admin-action="create-blocked-word">
                            Add word
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 px-4 py-3 text-sm text-slate-600">
                    <i class="fas fa-mobile-screen-button mr-2 text-[#055498]"></i>
                    Matching is whole-word and case-insensitive. The app syncs this list automatically.
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="word-blocking-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
