@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Status</p>
                <div
                    id="posts-status-tabs"
                    class="admin-filter-tabs"
                    role="tablist"
                    aria-label="Filter by status"
                ></div>
            </aside>

            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-file-lines"></i>
                        </span>
                        <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-3">
                            <h2 class="admin-shell-title text-base sm:text-lg">Content lifecycle management</h2>
                            <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">Create, review, schedule, archive.</p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered">
                        <button
                            type="button"
                            class="admin-primary-button xl:w-auto"
                            data-admin-action="create-post"
                            title="Create a new draft post"
                        >
                            Create Draft
                        </button>
                        <a
                            href="/admin/analytics"
                            class="admin-secondary-button xl:w-auto"
                            title="Open analytics dashboard"
                        >
                            Review Insights
                        </a>
                    </div>
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="posts-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
