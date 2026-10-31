@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Toolkits</p>
                <div id="care-toolkit-type-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by toolkit">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">All toolkits</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="stress" data-icon="fas fa-bolt">Stress Check</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="anxiety" data-icon="fas fa-heart-pulse">Anxiety Check</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="sleep" data-icon="fas fa-moon">Sleep Quality</button>
                </div>

                <p class="admin-filter-rail-title">Workflow</p>
                <div
                    class="admin-filter-tabs"
                    data-workflow-status-tabs="1"
                    role="tablist"
                    aria-label="Filter by workflow status"
                ></div>

            </aside>
            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-heart-pulse"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="admin-shell-title text-base sm:text-lg">Care Toolkit Questions</h2>
                            <p class="mt-0.5 text-xs sm:text-sm admin-shell-subtitle">
                                Manage Stress, Anxiety, and Sleep check questions shown in the DAPE Care mobile toolkit.
                            </p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center xl:w-auto xl:self-center">
                        <button type="button" class="admin-primary-button w-full sm:w-auto" data-admin-action="create-care-toolkit-question">
                            Add Question
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 px-4 py-3 text-sm text-slate-600">
                    <i class="fas fa-mobile-screen-button mr-2 text-[#055498]"></i>
                    Questions appear in the mobile <strong>Self-care Toolkit</strong>. Turn each toolkit Active or Inactive under
                    <strong>Lookups → Care toolkits</strong>. Leave custom options blank to use the default scale for each answer type.
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="care-toolkit-questions-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
