@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-book-open"></i>
                </span>
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-3">
                    <h2 class="admin-shell-title text-base sm:text-lg">My Diary (Talaarawan)</h2>
                    <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">
                        Private journal entries submitted from the mobile app.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 px-4 py-3 text-sm text-slate-600">
            <i class="fas fa-mobile-screen-button mr-2 text-[#055498]"></i>
            Users create diary notes in the <strong>Diary</strong> tab of the DAPE-MA mobile app (one entry per day, rich text).
            This page is for super admin review and moderation only.
        </div>

        <div class="admin-table-shell mt-6">
            <table id="diary-table" class="min-w-full text-left text-sm text-slate-700"></table>
        </div>
    </section>
@endsection
