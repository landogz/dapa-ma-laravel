@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge admin-icon-badge-lg" aria-hidden="true">
                    <i class="fas fa-calendar-days"></i>
                </span>
                <div class="min-w-0 flex flex-col gap-1">
                    <h2 class="admin-shell-title text-lg sm:text-xl">Hope Events & Seminars</h2>
                    <p class="admin-shell-subtitle max-w-2xl text-[11px] leading-relaxed sm:text-xs">
                        Manage youth, parent, and community events shown in the DAPE Hope Events list and detail screens.
                    </p>
                </div>
            </div>
            <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:w-auto lg:self-center">
                <label class="sr-only" for="hope-events-audience-filter">Filter by audience</label>
                <select id="hope-events-audience-filter" class="admin-input w-full sm:w-52" aria-label="Filter by audience">
                    <option value="">All audiences</option>
                    <option value="youth">Youth</option>
                    <option value="parents">Parents</option>
                    <option value="community">Community</option>
                </select>
                <button type="button" class="admin-primary-button w-full sm:w-auto" data-admin-action="create-hope-event">
                    <i class="fas fa-plus mr-2" aria-hidden="true"></i>
                    Add Event
                </button>
            </div>
        </div>

        <div class="admin-page-tip">
            <i class="fas fa-mobile-screen-button admin-page-tip-icon" aria-hidden="true"></i>
            <p class="m-0">
                Featured events appear on the Hope hub. Keep cover images, dates, and registration links current so mobile users can register quickly.
            </p>
        </div>

        <div class="admin-table-shell mt-6">
            <table id="hope-events-table" class="min-w-full text-left text-sm text-slate-700"></table>
        </div>
    </section>
@endsection
