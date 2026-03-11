@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-paper-plane"></i>
                </span>
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-3">
                    <h2 class="admin-shell-title text-base sm:text-lg">Push notifications</h2>
                    <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">Send alerts and review history.</p>
                </div>
            </div>
            <div class="admin-page-actions admin-page-actions-centered">
                <button type="button" class="admin-primary-button lg:w-auto" data-admin-action="send-notification">
                    Send Push Notification
                </button>
            </div>
        </div>

        <div class="admin-table-shell mt-6">
            <table id="notifications-table" class="min-w-full text-left text-sm text-slate-700"></table>
        </div>
    </section>
@endsection
