@extends('admin.layout')

@section('content')
    <section id="dashboard-overview" class="space-y-6">
        <div class="admin-shell-card p-4 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Dashboard</h2>
                    <p class="mt-1 text-sm text-slate-500">Overview of admin operations.</p>
                </div>
            </div>
        </div>

        <div class="admin-shell-card p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Quick Actions</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="admin-quick-action admin-quick-action-blue" data-admin-action="create-post" title="Create a new post draft">
                        <i class="fas fa-pen-to-square mr-2"></i>
                        New Post
                    </button>
                    <button type="button" class="admin-quick-action admin-quick-action-green" data-admin-action="create-rehab-center" title="Add a new rehab center listing">
                        <i class="fas fa-house-medical mr-2"></i>
                        Add Rehab Center
                    </button>
                    <button type="button" class="admin-quick-action admin-quick-action-orange" data-admin-action="send-notification" title="Send a push notification campaign">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Send Notification
                    </button>
                </div>
            </div>
        </div>

        <div id="function-cards" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <a href="/admin/posts" class="admin-stat-card admin-function-card admin-dashboard-summary text-left" data-card-section="posts">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-[#FBD116]">Content</p>
                        <p class="mt-3 text-base font-semibold">Posts</p>
                        <p class="mt-2 text-3xl font-bold" data-overview-count="posts">--</p>
                        <p class="admin-trend" data-overview-trend="posts">–</p>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10">
                        <i class="fas fa-file-lines text-[#FBD116]"></i>
                    </span>
                </div>
            </a>

            <a href="/admin/rehab-centers" class="admin-stat-card admin-function-card admin-dashboard-summary text-left" data-card-section="rehab-centers">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-[#055498]">Directory</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Rehab Centers</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900" data-overview-count="rehab-centers">--</p>
                        <p class="admin-trend" data-overview-trend="rehab-centers">–</p>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-hospital-user"></i>
                    </span>
                </div>
            </a>

            <a href="/admin/notifications" class="admin-stat-card admin-function-card admin-dashboard-summary text-left" data-card-section="notifications">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-[#CE2028]">Campaigns</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Notifications</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900" data-overview-count="notifications">--</p>
                        <p class="admin-trend" data-overview-trend="notifications">–</p>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-bell"></i>
                    </span>
                </div>
            </a>

            <a href="/admin/users" class="admin-stat-card admin-function-card admin-dashboard-summary text-left" data-card-section="users">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-[#123a60]">Access</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Users</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900" data-overview-count="users">--</p>
                        <p class="admin-trend" data-overview-trend="users">–</p>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/50 text-[#123a60]">
                        <i class="fas fa-users"></i>
                    </span>
                </div>
            </a>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="admin-shell-card p-4 sm:p-6" data-overview-analytics>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="admin-icon-badge">
                            <i class="fas fa-chart-line"></i>
                        </span>
                        <div>
                            <p class="text-base font-semibold text-slate-900">Activity Over Time</p>
                            <p class="text-xs text-slate-500">Events in the last 30 days</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <span>Range:</span>
                        <div class="inline-flex overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-[11px]">
                            <button type="button" class="px-3 py-1 text-slate-600 is-active" data-activity-range="7">7 days</button>
                            <button type="button" class="px-3 py-1 text-slate-500" data-activity-range="30">30 days</button>
                        </div>
                    </div>
                </div>
                <div id="dashboard-activity-chart" class="mt-6"></div>
            </div>

            <div class="admin-shell-card p-4 sm:p-6" data-overview-analytics>
                <div class="flex items-center gap-3">
                    <span class="admin-icon-badge">
                        <i class="fas fa-clock-rotate-left"></i>
                    </span>
                    <div>
                        <p class="text-base font-semibold text-slate-900">Recent Activity</p>
                        <p class="text-xs text-slate-500">Latest app and content events</p>
                    </div>
                </div>
                <div id="dashboard-recent-activity" class="mt-6 space-y-3"></div>
            </div>
        </div>

        <div class="admin-shell-card p-4 sm:p-6" data-overview-analytics>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="admin-icon-badge">
                        <i class="fas fa-ranking-star"></i>
                    </span>
                    <div>
                        <p class="text-base font-semibold text-slate-900">Top Content</p>
                        <p class="text-xs text-slate-500">Most viewed posts</p>
                    </div>
                </div>
            </div>
            <div id="dashboard-top-posts" class="mt-6 grid gap-3"></div>
        </div>
    </section>
@endsection
