import axios from 'axios';
import Swal from 'sweetalert2';

let cachedDailyCounts = [];

export function initAnalyticsModule() {
    initializeAnalyticsRange();
    loadAnalytics();
}

export function loadAnalytics() {
    const overviewTopPostsContainer = document.getElementById('dashboard-top-posts');
    const analyticsTopPostsContainer = document.getElementById('analytics-top-posts');

    if (overviewTopPostsContainer) {
        overviewTopPostsContainer.innerHTML = buildTopPostsSkeleton();
    }

    if (analyticsTopPostsContainer) {
        analyticsTopPostsContainer.innerHTML = buildTopPostsSkeleton();
    }

    const range = getSelectedAnalyticsRange();

    axios.get('/admin/analytics', { params: { range } })
        .then(({ data }) => {
            const eventTypes = data.data?.by_event_type ?? [];
            const topPosts = data.data?.top_posts ?? [];
            const dailyCounts = data.data?.daily_counts ?? [];
            const recentEvents = data.data?.recent_events ?? [];
            const devices = data.data?.devices ?? null;

            cachedDailyCounts = Array.isArray(dailyCounts) ? dailyCounts : [];

            renderAnalyticsSummary(eventTypes);
            renderEventTypeSummary(eventTypes);
            renderTopPosts(topPosts);
            renderDailyCounts(dailyCounts);
            initializeOverviewActivity(dailyCounts);
            renderOverviewDistribution(eventTypes);
            renderOverviewTopPosts(topPosts);
            renderOverviewRecentActivity(recentEvents);
            renderDeviceBreakdown(devices);
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Analytics', text: 'Failed to load analytics data.' });
        });
}

function summarizeEventTypes(events) {
    const summary = {
        total: 0,
        views: 0,
        bookmarks: 0,
        searches: 0,
        shares: 0,
    };

    events.forEach((event) => {
        const type = String(event.event_type || '').toLowerCase();
        const total = Number(event.total) || 0;

        summary.total += total;

        if (type.includes('view')) {
            summary.views += total;
        } else if (type.includes('bookmark')) {
            summary.bookmarks += total;
        } else if (type.includes('search')) {
            summary.searches += total;
        } else if (type.includes('share')) {
            summary.shares += total;
        }
    });

    return summary;
}

function renderAnalyticsSummary(events) {
    const summary = summarizeEventTypes(events);
    const storageKey = 'dape-analytics-summary';
    let previous = {};

    try {
        const raw = window.localStorage.getItem(storageKey);

        if (raw) {
            previous = JSON.parse(raw);
        }
    } catch {
        previous = {};
    }

    const setValue = (key, value) => {
        const el = document.querySelector(`[data-analytics-summary="${key}"]`);

        if (el) {
            el.textContent = Number(value).toLocaleString();
        }
    };

    setValue('views', summary.views);
    setValue('bookmarks', summary.bookmarks);
    setValue('searches', summary.searches);
    setValue('shares', summary.shares);

    updateAnalyticsTrend('views', summary.views, previous.views);
    updateAnalyticsTrend('bookmarks', summary.bookmarks, previous.bookmarks);
    updateAnalyticsTrend('searches', summary.searches, previous.searches);
    updateAnalyticsTrend('shares', summary.shares, previous.shares);

    try {
        window.localStorage.setItem(storageKey, JSON.stringify(summary));
    } catch {
        // ignore storage issues
    }
}

export function exportAnalyticsCsvWithRange() {
    Swal.fire({
        title: 'Export Analytics',
        input: 'select',
        inputLabel: 'Select date range',
        inputOptions: {
            today: 'Today',
            '7': 'Last 7 days',
            '30': 'Last 30 days',
        },
        inputValue: '30',
        showCancelButton: true,
        confirmButtonText: 'Download CSV',
        customClass: {
            popup: 'admin-swal-popup',
            title: 'admin-swal-title',
            htmlContainer: 'admin-swal-html',
            actions: 'admin-swal-actions',
            confirmButton: 'admin-swal-button admin-swal-button-primary',
            cancelButton: 'admin-swal-button',
            validationMessage: 'admin-swal-validation',
        },
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        const range = result.value || '30';
        const url = new URL(`${window.axios.defaults.baseURL}/admin/analytics/export`, window.location.origin);

        url.searchParams.set('range', range);

        const anchor = document.createElement('a');
        anchor.href = url.toString();
        anchor.download = `dape-ma-analytics-${new Date().toISOString().slice(0, 10)}.csv`;
        anchor.click();
    });
}

function renderEventTypeSummary(events) {
    const container = document.getElementById('analytics-event-summary');
    if (!container) return;

    if (!events.length) {
        container.innerHTML = '<p class="text-sm text-slate-400">No events recorded yet.</p>';

        return;
    }

    const summary = summarizeEventTypes(events);

    const types = [
        { key: 'views', label: 'Views', color: '#055498' },
        { key: 'bookmarks', label: 'Bookmarks', color: '#10B981' },
        { key: 'searches', label: 'Searches', color: '#0EA5E9' },
        { key: 'shares', label: 'Shares', color: '#F97316' },
    ];

    const rows = types.map((type) => {
        const value = summary[type.key] || 0;
        const percent = summary.total ? Math.round((value / summary.total) * 100) : 0;

        return `
            <div class="space-y-1.5">
                <div class="flex items-center justify-between text-xs font-medium text-slate-600">
                    <span>${type.label}</span>
                    <span class="text-slate-500">${Number(value).toLocaleString()} (${percent}%)</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full" style="width:${percent}%;background:${type.color};"></div>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = `
        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
            Total events: ${Number(summary.total).toLocaleString()}
        </p>
        <div class="space-y-3">
            ${rows}
        </div>
    `;
}

function renderTopPosts(posts) {
    const container = document.getElementById('analytics-top-posts');
    if (!container) return;

    if (!posts.length) {
        container.innerHTML = '<p class="text-sm text-slate-400">No viewed content yet. Top posts will appear here as users read more articles.</p>';

        return;
    }

    container.innerHTML = posts.map((p, i) => `
        <div class="flex items-center gap-3 py-2 border-b border-slate-100 last:border-0">
            <span class="text-xs font-bold text-slate-400 w-5">${i + 1}</span>
            <span class="flex-1 text-xs text-slate-700 truncate">${p.post?.title ?? '—'}</span>
            <span class="text-xs font-semibold text-[#055498]">${Number(p.views).toLocaleString()} views</span>
        </div>
    `).join('');
}

function renderDailyCounts(days) {
    const container = document.getElementById('analytics-daily-chart');
    if (!container) return;

    if (!days.length) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-8 text-center">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                    <i class="fas fa-chart-line"></i>
                </span>
                <p class="text-sm font-medium text-slate-800">Daily trend will appear here</p>
                <p class="text-xs text-slate-500">Once more analytics events are collected, this chart will show how activity changes over time.</p>
            </div>
        `;

        return;
    }

    if (days.length < 2) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-8 text-center">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                    <i class="fas fa-chart-column"></i>
                </span>
                <p class="text-sm font-medium text-slate-800">Not enough data to show a trend</p>
                <p class="text-xs text-slate-500">At least two days of activity are required before a meaningful daily trend can be displayed.</p>
            </div>
        `;

        return;
    }

    const max = Math.max(...days.map((d) => Number(d.total)), 1);
    const visibleDays = days.slice(-30);

    const topLabel = max;
    const midLabel = Math.round(max / 2);

    const labelPositions = new Set();
    const len = visibleDays.length;

    if (len <= 5) {
        for (let i = 0; i < len; i += 1) {
            labelPositions.add(i);
        }
    } else {
        const steps = 4;

        for (let i = 0; i <= steps; i += 1) {
            labelPositions.add(Math.round((i * (len - 1)) / steps));
        }
    }

    container.innerHTML = `
        <div class="flex gap-4">
            <div class="flex flex-col justify-between text-[11px] text-slate-400 h-40">
                <span>${topLabel}</span>
                <span>${midLabel}</span>
                <span>0</span>
            </div>
            <div class="flex-1">
                <div class="dashboard-activity-bars" style="--bars:${visibleDays.length}">
                    ${visibleDays.map((day, index) => `
                        <div class="dashboard-activity-bar">
                            <div class="dashboard-activity-bar-fill" style="height:${Math.max(8, Math.round((Number(day.total) / max) * 160))}px"></div>
                            <span class="text-[11px] text-slate-400">${labelPositions.has(index) ? String(day.date).slice(5) : ''}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
}

function renderOverviewActivity(days) {
    const container = document.getElementById('dashboard-activity-chart');

    if (!container) {
        return;
    }

    if (days.length === 0) {
        container.innerHTML = '<p class="text-sm text-slate-400">No activity data available.</p>';

        return;
    }

    const max = Math.max(...days.map((day) => Number(day.total)), 1);
    const visibleDays = days.slice(-30);
    const topLabel = max;
    const midLabel = Math.round(max / 2);

    const labelPositions = new Set();
    const len = visibleDays.length;

    if (len <= 5) {
        for (let i = 0; i < len; i += 1) {
            labelPositions.add(i);
        }
    } else {
        // roughly 5 labels spread across the range
        const steps = 4;

        for (let i = 0; i <= steps; i += 1) {
            labelPositions.add(Math.round((i * (len - 1)) / steps));
        }
    }

    container.innerHTML = `
        <div class="flex gap-4">
            <div class="flex flex-col justify-between text-[11px] text-slate-400 h-40">
                <span>${topLabel}</span>
                <span>${midLabel}</span>
                <span>0</span>
            </div>
            <div class="flex-1">
                <div class="dashboard-activity-bars" style="--bars:${visibleDays.length}">
                    ${visibleDays.map((day, index) => `
                        <div class="dashboard-activity-bar">
                            <div class="dashboard-activity-bar-fill" style="height:${Math.max(8, Math.round((Number(day.total) / max) * 160))}px"></div>
                            <span class="text-[11px] text-slate-400">${labelPositions.has(index) ? String(day.date).slice(5) : ''}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
}

function renderOverviewDistribution(events) {
    const chartContainer = document.getElementById('dashboard-event-distribution');
    const legendContainer = document.getElementById('dashboard-event-legend');

    if (!chartContainer || !legendContainer) {
        return;
    }

    if (events.length === 0) {
        chartContainer.innerHTML = '<p class="text-sm text-slate-400">No event data available.</p>';
        legendContainer.innerHTML = '';

        return;
    }

    const colors = ['#055498', '#FBD116', '#123a60', '#CE2028', '#7C3AED', '#10B981'];
    const total = events.reduce((sum, event) => sum + Number(event.total), 0);
    let start = 0;

    const segments = events.map((event, index) => {
        const value = Number(event.total);
        const percent = total === 0 ? 0 : (value / total) * 100;
        const color = colors[index % colors.length];
        const segment = `${color} ${start}% ${start + percent}%`;
        start += percent;

        return {
            color,
            event,
            percent,
            segment,
        };
    });

    chartContainer.innerHTML = `
        <div class="dashboard-donut-chart" style="background: conic-gradient(${segments.map((segment) => segment.segment).join(', ')});"></div>
        ${segments.length === 1 ? '<p class="mt-3 text-xs text-slate-500 text-center">Additional event types (bookmarks, shares, searches) will appear here as tracking expands.</p>' : ''}
    `;

    legendContainer.innerHTML = segments.map((segment) => `
        <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-3.5 w-3.5 rounded-full" style="background:${segment.color}"></span>
                <span class="text-sm font-medium text-slate-700">${segment.event.event_type}</span>
            </div>
            <span class="text-sm font-semibold text-slate-900">${Math.round(segment.percent)}%</span>
        </div>
    `).join('');
}

function renderOverviewRecentActivity(events) {
    const container = document.getElementById('dashboard-recent-activity');

    if (!container) {
        return;
    }

    if (!events.length) {
        container.innerHTML = '<p class="text-sm text-slate-400">Recent events will appear here as users and admins start interacting with the system.</p>';

        return;
    }

    const groupsMap = new Map();

    events.forEach((event) => {
        const type = String(event.event_type || '').toLowerCase();
        const postId = event.post?.id ?? event.post_id ?? '';
        const key = `${type}|${postId}`;
        const existing = groupsMap.get(key);

        if (!existing) {
            groupsMap.set(key, {
                eventType: type,
                post: event.post,
                user: event.user,
                count: 1,
                latestAt: event.created_at,
            });
        } else {
            existing.count += 1;

            if (event.created_at && new Date(event.created_at) > new Date(existing.latestAt)) {
                existing.latestAt = event.created_at;
                existing.post = event.post || existing.post;
                existing.user = event.user || existing.user;
            }
        }
    });

    const groups = Array.from(groupsMap.values())
        .sort((a, b) => new Date(b.latestAt) - new Date(a.latestAt))
        .slice(0, 5);

    container.innerHTML = groups.map((group) => {
        const meta = describeGroupedEvent(group);

        return `
            <div class="flex items-start justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                        <i class="${meta.icon}"></i>
                    </span>
                    <div class="space-y-0.5">
                        <p class="text-sm font-semibold text-slate-900">${meta.title}</p>
                        <p class="text-xs text-slate-500">${meta.details}</p>
                    </div>
                </div>
                <p class="shrink-0 text-xs font-medium text-slate-400">${formatRelativeTime(group.latestAt)}</p>
            </div>
        `;
    }).join('') + `
        <div class="mt-3 flex justify-end">
            <a href="/admin/analytics" class="text-xs font-semibold text-[#055498] hover:text-[#123a60]">
                View all activity
            </a>
        </div>
    `;
}

function describeGroupedEvent(group) {
    const type = String(group.eventType || '').toLowerCase();
    const count = group.count ?? 1;
    const userName = group.user?.name || 'A user';
    const postTitle = group.post?.title || 'a post';

    if (type === 'post_view' || type === 'view') {
        if (count > 1) {
            return {
                icon: 'fas fa-eye',
                title: 'Post viewed',
                details: `"${postTitle}" viewed ${count} times in recent activity.`,
            };
        }

        return {
            icon: 'fas fa-eye',
            title: 'Post viewed',
            details: `${userName} viewed "${postTitle}".`,
        };
    }

    if (type === 'search') {
        return {
            icon: 'fas fa-magnifying-glass',
            title: 'Search performed',
            details: `${userName} searched for content in the app.`,
        };
    }

    if (type === 'bookmark') {
        return {
            icon: 'fas fa-bookmark',
            title: 'Post bookmarked',
            details: `${userName} bookmarked "${postTitle}".`,
        };
    }

    if (type === 'share') {
        return {
            icon: 'fas fa-share-nodes',
            title: 'Post shared',
            details: `${userName} shared "${postTitle}".`,
        };
    }

    return {
        icon: 'fas fa-bolt',
        title: 'App activity',
        details: `${userName} triggered "${group.eventType ?? 'an event'}".`,
    };
}

function formatRelativeTime(isoString) {
    if (!isoString) {
        return '';
    }

    const eventDate = new Date(isoString);
    const now = new Date();
    const diffMs = now.getTime() - eventDate.getTime();
    const diffSeconds = Math.round(diffMs / 1000);
    const diffMinutes = Math.round(diffSeconds / 60);
    const diffHours = Math.round(diffMinutes / 60);
    const diffDays = Math.round(diffHours / 24);

    if (diffSeconds < 45) {
        return 'Just now';
    }

    if (diffMinutes < 60) {
        return `${diffMinutes} min${diffMinutes === 1 ? '' : 's'} ago`;
    }

    if (diffHours < 24) {
        return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
    }

    if (diffDays < 7) {
        return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
    }

    return eventDate.toLocaleDateString();
}

function renderOverviewTopPosts(posts) {
    const container = document.getElementById('dashboard-top-posts');

    if (!container) {
        return;
    }

    if (posts.length === 0) {
        container.innerHTML = '<p class="text-sm text-slate-400">No post engagement data available yet. Recent content performance will appear here once users start engaging.</p>';

        return;
    }

    container.innerHTML = posts.slice(0, 5).map((post, index) => `
        <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
            <div class="flex items-center gap-4">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#055498]/10 text-sm font-bold text-[#055498]">${index + 1}</span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">${post.post?.title ?? 'Untitled Post'}</p>
                    <p class="text-xs text-slate-500">Viewed content</p>
                </div>
            </div>
            <span class="text-sm font-semibold text-[#055498]">${Number(post.views).toLocaleString()} views</span>
        </div>
    `).join('');
}

function initializeOverviewActivity(days) {
    const container = document.getElementById('dashboard-activity-chart');

    if (!container) {
        return;
    }

    const controls = document.querySelectorAll('[data-activity-range]');

    if (!controls.length) {
        renderOverviewActivity(days);

        return;
    }

    const applyRange = (range) => {
        let subset = days;

        if (range === '1') {
            subset = days.slice(-1);
        } else if (range === '7') {
            subset = days.slice(-7);
        } else if (range === '30') {
            subset = days.slice(-30);
        }

        renderOverviewActivity(subset);
    };

    controls.forEach((button) => {
        button.addEventListener('click', () => {
            controls.forEach((other) => other.classList.remove('is-active'));
            button.classList.add('is-active');

            applyRange(button.getAttribute('data-activity-range') || '30');
        });
    });

    const initial = document.querySelector('[data-activity-range].is-active') || controls[0];

    if (initial) {
        applyRange(initial.getAttribute('data-activity-range') || '30');
    } else {
        renderOverviewActivity(days);
    }
}

function buildTopPostsSkeleton() {
    return `
        <div class="grid gap-3">
            <div class="h-12 rounded-xl bg-slate-100 animate-pulse"></div>
            <div class="h-12 rounded-xl bg-slate-100 animate-pulse"></div>
            <div class="h-12 rounded-xl bg-slate-100 animate-pulse"></div>
        </div>
    `;
}

function getSelectedAnalyticsRange() {
    const active = document.querySelector('[data-analytics-range].is-active');

    return active?.getAttribute('data-analytics-range') || '7';
}

function initializeAnalyticsRange() {
    const buttons = document.querySelectorAll('[data-analytics-range]');

    if (!buttons.length) {
        return;
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((other) => other.classList.remove('is-active'));
            button.classList.add('is-active');
            loadAnalytics();
        });
    });
}

function updateAnalyticsTrend(key, current, previousValue) {
    const elements = document.querySelectorAll(`[data-analytics-trend="${key}"]`);

    if (!elements.length) {
        return;
    }

    const prev = typeof previousValue === 'number' ? previousValue : current;
    const delta = Number(current) - Number(prev);

    elements.forEach((element) => {
        element.classList.remove('admin-trend-up', 'admin-trend-down', 'admin-trend-neutral');

        if (delta > 0) {
            element.classList.add('admin-trend', 'admin-trend-up');
            element.textContent = `↑ ${delta} since last check`;
        } else if (delta < 0) {
            element.classList.add('admin-trend', 'admin-trend-down');
            element.textContent = `↓ ${Math.abs(delta)} since last check`;
        } else {
            element.classList.add('admin-trend', 'admin-trend-neutral');
            element.textContent = 'No change since last check';
        }
    });
}

function renderDeviceBreakdown(devices) {
    const androidEl = document.querySelector('[data-analytics-device="android"]');
    const iosEl = document.querySelector('[data-analytics-device="ios"]');
    const emptyEl = document.querySelector('[data-analytics-device-empty]');

    if (!androidEl || !iosEl || !emptyEl) {
        return;
    }

    if (!devices || !Number(devices.total)) {
        androidEl.textContent = '--';
        iosEl.textContent = '--';
        emptyEl.classList.remove('hidden');

        return;
    }

    const total = Number(devices.total) || 0;
    const android = Number(devices.android) || 0;
    const ios = Number(devices.ios) || 0;

    const androidPercent = total ? Math.round((android / total) * 100) : 0;
    const iosPercent = total ? Math.round((ios / total) * 100) : 0;

    androidEl.textContent = `${android.toLocaleString()} (${androidPercent} %)`;
    iosEl.textContent = `${ios.toLocaleString()} (${iosPercent} %)`;

    emptyEl.classList.add('hidden');
}

window.Analytics = { load: loadAnalytics, exportCsv: exportAnalyticsCsvWithRange };
