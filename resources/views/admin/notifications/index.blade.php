@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Audiences</p>
                <div id="notifications-topic-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by audience">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">All audiences</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="all" data-icon="fas fa-users">All users</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="android" data-icon="fab fa-android">Android</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="ios" data-icon="fab fa-apple">iOS</button>
                </div>
            </aside>
            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-bell"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="admin-shell-title text-base sm:text-lg">In-app Notifications</h2>
                            <p class="mt-0.5 text-xs sm:text-sm admin-shell-subtitle">
                                Send inbox alerts to mobile app users and review campaign history.
                            </p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center xl:w-auto xl:self-center">
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
            </div>
        </div>
    </section>
@endsection
