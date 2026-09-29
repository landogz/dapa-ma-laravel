@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-bell"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="admin-shell-title text-base sm:text-lg">In-app Notifications</h2>
                    <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">
                        Send inbox alerts to mobile app users and review campaign history.
                    </p>
                </div>
            </div>
            <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:w-auto lg:self-center">
                <label class="sr-only" for="notifications-topic-filter">Filter by audience</label>
                <select id="notifications-topic-filter" class="admin-filter-select admin-choice-select-page w-full sm:w-48" aria-label="Filter by audience">
                    <option value="">All audiences</option>
                    <option value="all">All users</option>
                    <option value="android">Android</option>
                    <option value="ios">iOS</option>
                </select>
                <button type="button" class="admin-primary-button w-full sm:w-auto" data-admin-action="send-notification">
                    <i class="fas fa-paper-plane mr-2" aria-hidden="true"></i>
                    Send Notification
                </button>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 px-4 py-3 text-sm text-slate-600">
            <i class="fas fa-mobile-screen-button mr-2 text-[#055498]"></i>
            Messages appear in the mobile app <strong>Notifications</strong> inbox for matching users.
            Device push can be enabled later when FCM is configured.
        </div>

        <div class="admin-table-shell mt-6">
            <table id="notifications-table" class="min-w-full text-left text-sm text-slate-700"></table>
        </div>

        <div id="notifications-context-menu" class="admin-context-menu" hidden>
            <button type="button" class="admin-context-menu-button" data-notif-context="view">
                <i class="fas fa-eye text-[#055498]"></i>
                <span>View</span>
            </button>
            <button type="button" class="admin-context-menu-button text-[#CE2028]" data-notif-context="delete">
                <i class="fas fa-trash"></i>
                <span>Delete</span>
            </button>
        </div>
    </section>
@endsection
