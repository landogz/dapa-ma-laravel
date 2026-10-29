@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-user-shield"></i>
                </span>
                <div class="flex min-w-0 flex-col sm:flex-row sm:items-baseline sm:gap-3">
                    <h2 class="admin-shell-title text-base sm:text-lg">User administration</h2>
                    <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">Manage staff access and mobile app accounts.</p>
                </div>
            </div>
            <div class="admin-page-actions admin-page-actions-centered">
                <button type="button" class="admin-primary-button xl:w-auto" data-admin-action="create-user">
                    Add User
                </button>
            </div>
        </div>

        <div class="admin-users-filters mt-5 space-y-3">
            <div
                id="users-audience-tabs"
                class="admin-users-audience"
                role="tablist"
                aria-label="Account type"
            ></div>
            <div
                id="users-role-tabs"
                class="admin-users-role-tabs"
                role="tablist"
                aria-label="Filter by role"
            ></div>
        </div>

        <div class="admin-table-shell mt-5">
            <table id="users-table" class="min-w-full text-left text-sm text-slate-700"></table>
        </div>
    </section>
@endsection
