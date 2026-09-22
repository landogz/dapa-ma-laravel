@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-heart-pulse"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="admin-shell-title text-base sm:text-lg">Care Toolkit Questions</h2>
                    <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">
                        Manage Stress, Anxiety, and Sleep check questions shown in the DAPE Care mobile toolkit.
                    </p>
                </div>
            </div>
            <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:w-auto lg:self-center">
                <label class="sr-only" for="care-toolkit-type-filter">Filter by toolkit</label>
                <select id="care-toolkit-type-filter" class="admin-input w-full sm:w-48">
                    <option value="">All toolkits</option>
                    <option value="stress">Stress Check</option>
                    <option value="anxiety">Anxiety Check</option>
                    <option value="sleep">Sleep Quality</option>
                </select>
                <button type="button" class="admin-primary-button w-full sm:w-auto" data-admin-action="create-care-toolkit-question">
                    Add Question
                </button>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-[#055498]/15 bg-[#055498]/5 px-4 py-3 text-sm text-slate-600">
            <i class="fas fa-mobile-screen-button mr-2 text-[#055498]"></i>
            Questions appear in the mobile <strong>Self-care Toolkit</strong> (swipeable Care Bits cards). Leave custom options blank to use the default scale for each answer type.
        </div>

        <div class="admin-table-shell mt-6">
            <table id="care-toolkit-questions-table" class="min-w-full text-left text-sm text-slate-700"></table>
        </div>
    </section>
@endsection
