@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge admin-icon-badge-lg" aria-hidden="true">
                    <i class="fas fa-trophy"></i>
                </span>
                <div class="min-w-0 flex flex-col gap-1">
                    <h2 class="admin-shell-title text-lg sm:text-xl">Contests</h2>
                    <p class="admin-shell-subtitle max-w-2xl text-[11px] leading-relaxed sm:text-xs">
                        Manage Song, Poster, and Video contests from one place — choose a category when creating.
                    </p>
                </div>
            </div>
            <div class="admin-page-actions admin-page-actions-centered lg:self-center">
                <button type="button" class="admin-primary-button lg:w-auto" data-admin-action="create-contest">
                    Add Contest
                </button>
            </div>
        </div>

        <div class="admin-table-shell mt-6">
            <table id="contests-table" class="min-w-full text-left text-sm text-slate-700"></table>
        </div>
    </section>
@endsection
