@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Workflow</p>
                <div
                    class="admin-filter-tabs"
                    data-workflow-status-tabs="1"
                    role="tablist"
                    aria-label="Filter by workflow status"
                ></div>
            </aside>
            <div class="admin-filter-main min-w-0">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-photo-film"></i>
                        </span>
                        <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-3">
                            <h2 class="admin-shell-title text-base sm:text-lg">IEC Materials</h2>
                            <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">Curate animated IEC GIFs, images, and YouTube clips for the mobile gallery.</p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered xl:self-center">
                        <button type="button" class="admin-primary-button xl:w-auto" data-admin-action="create-iec-material">
                            Add IEC Material
                        </button>
                    </div>
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="iec-materials-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
