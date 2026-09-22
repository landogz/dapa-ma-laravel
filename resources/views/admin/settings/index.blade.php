@extends('admin.layout')

@section('content')
    <section id="admin-settings-page" class="space-y-6">
        <div class="admin-shell-card p-4 sm:p-6">
            <div class="admin-section-header admin-section-header-accent items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-gear"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="admin-shell-title text-base sm:text-lg">Settings</h2>
                    <p class="mt-0.5 text-xs sm:text-sm admin-shell-subtitle">
                        Manage mobile legal pages and Kid Listo motivational quotes.
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-[#055498]">Legal pages</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-1 lg:grid-cols-2" data-settings-legal-list>
                    <div class="admin-settings-tile-skeleton" aria-hidden="true"></div>
                    <div class="admin-settings-tile-skeleton" aria-hidden="true"></div>
                </div>
            </div>
        </div>

        <div class="admin-shell-card p-4 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                    <span class="admin-icon-badge">
                        <i class="fas fa-quote-left"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="admin-shell-title text-base sm:text-lg">Kid Listo Quotes</h2>
                        <p class="mt-0.5 text-xs sm:text-sm admin-shell-subtitle">
                            Motivational quotes shown randomly in the mobile Kid Listo screen.
                        </p>
                    </div>
                </div>
                <div class="admin-page-actions admin-page-actions-centered lg:self-center">
                    <button type="button" class="admin-primary-button w-full sm:w-auto" data-admin-action="create-kid-listo-quote">
                        Add Quote
                    </button>
                </div>
            </div>

            <div class="admin-table-shell mt-6">
                <table id="kid-listo-quotes-table" class="min-w-full text-left text-sm text-slate-700"></table>
            </div>
        </div>
    </section>
@endsection
