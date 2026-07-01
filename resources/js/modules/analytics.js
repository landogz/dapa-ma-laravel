import axios from 'axios';
import Swal from 'sweetalert2';
import { getPostRatingMetrics, renderRatingBadge, renderStars } from './shared/ratings';
import { showErrorToast, showSuccessToast } from './shared/toast';

const METRIC_COLORS = {
    views: '#055498',
    bookmarks: '#10B981',
    searches: '#7C3AED',
    shares: '#F97316',
};

const DEVICE_COLORS = {
    android: '#16A34A',
    ios: '#055498',
    web: '#F97316',
    other: '#64748B',
};

let cachedDailyCounts = [];
let cachedTopPosts = [];
let topPostsSearchBound = false;

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
            const topRatedPosts = data.data?.top_rated_posts ?? [];
            const ratings = data.data?.ratings ?? null;
            const dailyCounts = data.data?.daily_counts ?? [];
            const dailyByEventType = data.data?.daily_by_event_type ?? [];
            const periodComparison = data.data?.period_comparison ?? null;
            const recentEvents = data.data?.recent_events ?? [];
            const devices = data.data?.devices ?? null;
            const generatedAt = data.data?.generated_at ?? null;

            cachedDailyCounts = Array.isArray(dailyCounts) ? dailyCounts : [];

            renderAnalyticsSummary(periodComparison, generatedAt, eventTypes);
            renderRatingsSummary(ratings, periodComparison?.label ?? 'previous period');
            renderMetricSparklines(dailyByEventType, dailyCounts);
            renderEventTypeSummary(eventTypes);
            renderTopPosts(topPosts);
            renderTopRatedPosts(topRatedPosts);
            renderDailyCounts(dailyCounts);
            initializeOverviewActivity(dailyCounts);
            renderOverviewDistribution(eventTypes);
            renderOverviewTopPosts(topPosts);
            renderOverviewRecentActivity(recentEvents);
            renderDeviceBreakdown(devices);
        })
        .catch((error) => {
            showErrorToast(
                error.response?.data?.message ?? 'Failed to load analytics data.',
                'Analytics',
            );
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

function renderAnalyticsSummary(periodComparison, generatedAt, eventTypes = []) {
    const current = periodComparison?.current ?? summarizeEventTypes(eventTypes);
    const previous = periodComparison?.previous ?? {};
    const periodLabel = periodComparison?.label ?? 'previous period';

    const setValue = (key, value) => {
        const el = document.querySelector(`[data-analytics-summary="${key}"]`);

        if (el) {
            el.textContent = Number(value).toLocaleString();
        }
    };

    setValue('views', current.views ?? 0);
    setValue('bookmarks', current.bookmarks ?? 0);
    setValue('searches', current.searches ?? 0);
    setValue('shares', current.shares ?? 0);

    updatePeriodTrend('views', current.views ?? 0, previous.views ?? 0, periodLabel);
    updatePeriodTrend('bookmarks', current.bookmarks ?? 0, previous.bookmarks ?? 0, periodLabel);
    updatePeriodTrend('searches', current.searches ?? 0, previous.searches ?? 0, periodLabel);
    updatePeriodTrend('shares', current.shares ?? 0, previous.shares ?? 0, periodLabel);

    const updatedEl = document.querySelector('[data-analytics-updated-at]');

    if (updatedEl && generatedAt) {
        updatedEl.textContent = `Last updated ${formatRelativeTime(generatedAt)}`;
    }
}

function renderRatingsSummary(ratings, periodLabel = 'previous period') {
    const total = Number(ratings?.total_reviews ?? 0);
    const average = Number(ratings?.average_rating ?? 0);
    const previousTotal = Number(ratings?.previous_total ?? 0);

    const totalEl = document.querySelector('[data-analytics-summary="ratings-total"]');
    const averageEl = document.querySelector('[data-analytics-summary="ratings-average"]');

    if (totalEl) {
        totalEl.textContent = total.toLocaleString();
    }

    if (averageEl) {
        averageEl.innerHTML = average > 0
            ? `<span class="text-amber-500">${renderStars(Math.round(average))}</span> <span class="text-slate-900">${average.toFixed(1)}</span>`
            : '—';
    }

    updatePeriodTrend('ratings-total', total, previousTotal, periodLabel);
}

function renderMetricSparklines(dailyByEventType, filledDays) {
    const sparklines = buildMetricSparklineSeries(dailyByEventType, filledDays);

    Object.entries(sparklines).forEach(([metric, values]) => {
        const container = document.querySelector(`[data-analytics-sparkline="${metric}"]`);

        if (!container) {
            return;
        }

        container.innerHTML = buildSparklineSvg(values, METRIC_COLORS[metric] ?? '#055498');
    });
}

function buildMetricSparklineSeries(dailyByEventType, filledDays) {
    const metrics = ['views', 'bookmarks', 'searches', 'shares'];
    const series = Object.fromEntries(metrics.map((metric) => [metric, []]));

    filledDays.forEach((day) => {
        const date = String(day.date);
        const dayRows = dailyByEventType.filter((row) => String(row.date) === date);

        metrics.forEach((metric) => {
            let total = 0;

            dayRows.forEach((row) => {
                if (categorizeEventType(row.event_type) === metric) {
                    total += Number(row.total) || 0;
                }
            });

            series[metric].push(total);
        });
    });

    return series;
}

function buildSparklineSvg(values, color) {
    if (!values.length) {
        return '';
    }

    const width = 200;
    const height = 64;
    const max = Math.max(...values, 1);
    const points = values.map((value, index) => {
        const x = values.length === 1 ? width / 2 : (index / (values.length - 1)) * width;
        const y = height - ((Number(value) / max) * (height - 8)) - 4;

        return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');

    return `
        <svg viewBox="0 0 ${width} ${height}" class="analytics-sparkline" aria-hidden="true">
            <polyline points="${points}" fill="none" stroke="${color}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
        </svg>
    `;
}

function categorizeEventType(eventType) {
    const type = String(eventType || '').toLowerCase();

    if (type.includes('view')) {
        return 'views';
    }

    if (type.includes('bookmark')) {
        return 'bookmarks';
    }

    if (type.includes('search')) {
        return 'searches';
    }

    if (type.includes('share')) {
        return 'shares';
    }

    return '';
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
    }).then(async (result) => {
        if (!result.isConfirmed) {
            return;
        }

        const range = result.value || '30';

        try {
            const response = await axios.get('/admin/analytics/export', {
                params: { range },
                responseType: 'blob',
            });

            const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
            const downloadUrl = window.URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = downloadUrl;
            anchor.download = `dape-ma-analytics-${new Date().toISOString().slice(0, 10)}.csv`;
            anchor.click();
            window.URL.revokeObjectURL(downloadUrl);
            showSuccessToast('Analytics CSV downloaded.', 'Export ready');
        } catch (error) {
            showErrorToast(
                error.response?.data?.message ?? 'Failed to export analytics data.',
                'Export failed',
            );
        }
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
        { key: 'views', label: 'Views', color: METRIC_COLORS.views },
        { key: 'bookmarks', label: 'Bookmarks', color: METRIC_COLORS.bookmarks },
        { key: 'searches', label: 'Searches', color: METRIC_COLORS.searches },
        { key: 'shares', label: 'Shares', color: METRIC_COLORS.shares },
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
    cachedTopPosts = Array.isArray(posts) ? posts : [];
    bindTopPostsSearch();

    const query = document.getElementById('analytics-top-posts-search')?.value?.trim().toLowerCase() ?? '';
    const filtered = query
        ? cachedTopPosts.filter((post) => String(post.post?.title ?? '').toLowerCase().includes(query))
        : cachedTopPosts;

    renderTopPostsList(filtered);
}

function renderTopPostsList(posts) {
    const container = document.getElementById('analytics-top-posts');
    const searchInput = document.getElementById('analytics-top-posts-search');

    if (!container) {
        return;
    }

    if (!cachedTopPosts.length) {
        if (searchInput) {
            searchInput.classList.add('hidden');
        }

        container.innerHTML = '<p class="text-sm text-slate-400">No viewed content yet. Top posts will appear here as users read more articles.</p>';

        return;
    }

    if (searchInput) {
        searchInput.classList.remove('hidden');
    }

    if (!posts.length) {
        container.innerHTML = '<p class="text-sm text-slate-400">No posts match your search.</p>';

        return;
    }

    const rows = posts.map((post, index) => {
        const title = escapeHtml(post.post?.title ?? 'Untitled post');
        const postId = post.post?.id ?? post.post_id;
        const { average, count } = getPostRatingMetrics(post.post ?? {});
        const titleMarkup = postId
            ? `<a href="/admin/posts?post=${encodeURIComponent(postId)}" class="admin-table-link-chip block truncate text-left" title="${title}">${title}</a>`
            : `<span class="block truncate text-left">${title}</span>`;
        const ratingMarkup = count > 0
            ? `<span class="shrink-0 text-[11px] font-medium text-amber-600" title="${average.toFixed(1)} · ${count}">${renderStars(Math.round(average))} ${average.toFixed(1)}</span>`
            : '<span class="shrink-0 text-[11px] text-slate-400">—</span>';

        return `
            <div class="flex items-center gap-3 border-b border-slate-100 py-2 last:border-0" data-analytics-post-row>
                <span class="w-5 text-xs font-bold text-slate-400">${index + 1}</span>
                <div class="min-w-0 flex-1">${titleMarkup}</div>
                <div class="flex shrink-0 flex-col items-end gap-0.5">
                    <span class="text-xs font-semibold text-[#055498]">${formatViewCount(post.views)} views</span>
                    ${ratingMarkup}
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = `
        <div>${rows}</div>
        <div class="mt-3 flex justify-end">
            <a href="/admin/posts" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#055498] hover:text-[#123a60]">
                View all posts
                <i class="fas fa-arrow-right text-[10px]" aria-hidden="true"></i>
            </a>
        </div>
    `;
}

function renderTopRatedPosts(posts) {
    const container = document.getElementById('analytics-top-rated-posts');

    if (!container) {
        return;
    }

    if (!Array.isArray(posts) || posts.length === 0) {
        container.innerHTML = '<p class="text-sm text-slate-400">No rated posts yet. Ratings will appear here once users review content.</p>';

        return;
    }

    container.innerHTML = posts.map((post, index) => {
        const title = escapeHtml(post.title ?? 'Untitled post');
        const postId = post.id;
        const titleMarkup = postId
            ? `<a href="/admin/posts?post=${encodeURIComponent(postId)}" class="admin-table-link-chip block truncate text-left" title="${title}">${title}</a>`
            : `<span class="block truncate text-left">${title}</span>`;

        return `
            <div class="flex items-center gap-3 border-b border-slate-100 py-2 last:border-0">
                <span class="w-5 text-xs font-bold text-slate-400">${index + 1}</span>
                <div class="min-w-0 flex-1">${titleMarkup}</div>
                <div class="shrink-0">${renderRatingBadge(post, { compact: true })}</div>
            </div>
        `;
    }).join('');
}

function bindTopPostsSearch() {
    if (topPostsSearchBound) {
        return;
    }

    const searchInput = document.getElementById('analytics-top-posts-search');

    if (!searchInput) {
        return;
    }

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();
        const filtered = query
            ? cachedTopPosts.filter((post) => String(post.post?.title ?? '').toLowerCase().includes(query))
            : cachedTopPosts;

        renderTopPostsList(filtered);
    });

    topPostsSearchBound = true;
}

function renderDailyCounts(days) {
    const container = document.getElementById('analytics-daily-chart');
    if (!container) return;

    container.innerHTML = buildActivityChartHtml(days, {
        emptyTitle: 'Daily trend will appear here',
        emptyMessage: 'Once more analytics events are collected, this chart will show how activity changes over time.',
        singleTitle: 'Not enough data to show a trend',
        singleMessage: 'At least two days of activity are required before a meaningful daily trend can be displayed.',
    });
}

function buildActivityChartHtml(days, messages) {
    if (!days.length) {
        return buildAnalyticsEmptyState(messages.emptyTitle, messages.emptyMessage, 'fa-chart-line');
    }

    const hasActivity = days.some((day) => Number(day.total) > 0);

    if (!hasActivity) {
        return buildAnalyticsEmptyState(messages.emptyTitle, messages.emptyMessage, 'fa-chart-line');
    }

    const max = Math.max(...days.map((day) => Number(day.total)), 1);
    const showAllLabels = days.length <= 14;

    const topLabel = max;
    const midLabel = Math.round(max / 2);

    return `
        <div class="analytics-chart-shell flex gap-3 sm:gap-4">
            <div class="analytics-chart-y-axis flex shrink-0 gap-2">
                <span class="analytics-chart-y-label">Events</span>
                <div class="flex h-44 flex-col justify-between text-[11px] text-slate-400">
                    <span>${topLabel.toLocaleString()}</span>
                    <span>${midLabel.toLocaleString()}</span>
                    <span>0</span>
                </div>
            </div>
            <div class="analytics-chart-scroll-wrap min-w-0 flex-1">
                <div class="analytics-chart-scroll overflow-x-auto pb-2">
                    <div class="dashboard-activity-bars analytics-activity-bars" style="--bars:${days.length}">
                    ${days.map((day, index) => {
                        const total = Number(day.total) || 0;
                        const height = total === 0 ? 4 : Math.max(8, Math.round((total / max) * 176));
                        const label = showAllLabels || index % 2 === 0 || index === days.length - 1
                            ? formatChartDateLabel(day.date)
                            : '';
                        const shortDate = formatChartDateLabel(day.date);
                        const fullDate = formatChartDateLabel(day.date, true);
                        const countLabel = `${total.toLocaleString()} event${total === 1 ? '' : 's'}`;
                        const tooltipLabel = `${shortDate} · ${countLabel}`;
                        const ariaLabel = `${fullDate}: ${countLabel}`;

                        return `
                            <div
                                class="dashboard-activity-bar analytics-activity-bar analytics-chart-column"
                                data-chart-bar
                                data-chart-date="${escapeHtml(shortDate)}"
                                data-chart-count="${escapeHtml(countLabel)}"
                                title="${escapeHtml(tooltipLabel)}"
                                tabindex="0"
                                role="button"
                                aria-label="${escapeHtml(ariaLabel)}"
                            >
                                <div class="analytics-chart-hitbox">
                                    <div class="analytics-column-tooltip" role="tooltip">${escapeHtml(tooltipLabel)}</div>
                                    <div
                                        class="dashboard-activity-bar-fill analytics-bar-brand analytics-chart-bar${total === 0 ? ' analytics-bar-zero' : ''}"
                                        style="height:${height}px"
                                    ></div>
                                </div>
                                <span class="analytics-bar-label">${label}</span>
                            </div>
                        `;
                    }).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function buildAnalyticsEmptyState(title, message, iconClass) {
    return `
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-8 text-center">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <i class="fas ${iconClass}"></i>
            </span>
            <p class="text-sm font-medium text-slate-800">${title}</p>
            <p class="text-xs text-slate-500">${message}</p>
        </div>
    `;
}

function formatChartDateLabel(dateValue, full = false) {
    const date = new Date(`${String(dateValue).slice(0, 10)}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return String(dateValue).slice(5);
    }

    if (full) {
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${month}-${day}`;
}

function renderOverviewActivity(days) {
    const container = document.getElementById('dashboard-activity-chart');

    if (!container) {
        return;
    }

    container.innerHTML = buildActivityChartHtml(days.slice(-30), {
        emptyTitle: 'No activity data available',
        emptyMessage: 'Activity will appear here once analytics events are recorded.',
        singleTitle: 'No activity data available',
        singleMessage: 'Activity will appear here once analytics events are recorded.',
    });
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

    container.innerHTML = posts.slice(0, 5).map((post, index) => {
        const { average, count } = getPostRatingMetrics(post.post ?? {});

        return `
        <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
            <div class="flex items-center gap-4">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#055498]/10 text-sm font-bold text-[#055498]">${index + 1}</span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">${post.post?.title ?? 'Untitled Post'}</p>
                    <p class="text-xs text-slate-500">Viewed content${count > 0 ? ` · ${average.toFixed(1)} ★` : ''}</p>
                </div>
            </div>
            <span class="text-sm font-semibold text-[#055498]">${formatViewCount(post.views)}</span>
        </div>
    `;
    }).join('');
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

function updatePeriodTrend(key, current, previousValue, periodLabel) {
    const elements = document.querySelectorAll(`[data-analytics-trend="${key}"]`);

    if (!elements.length) {
        return;
    }

    const previous = Number(previousValue) || 0;
    const currentValue = Number(current) || 0;
    const delta = currentValue - previous;

    elements.forEach((element) => {
        element.classList.remove('admin-trend-up', 'admin-trend-down', 'admin-trend-neutral');

        if (delta > 0) {
            element.classList.add('admin-trend', 'admin-trend-up');
            element.textContent = `+${delta.toLocaleString()} vs ${periodLabel}`;
        } else if (delta < 0) {
            element.classList.add('admin-trend', 'admin-trend-down');
            element.textContent = `−${Math.abs(delta).toLocaleString()} vs ${periodLabel}`;
        } else if (currentValue === 0 && previous === 0) {
            element.classList.add('admin-trend', 'admin-trend-neutral');
            element.textContent = 'No events in this range';
        } else {
            element.classList.add('admin-trend', 'admin-trend-neutral');
            element.textContent = `Same as ${periodLabel}`;
        }
    });
}

function renderDeviceBreakdown(devices) {
    const chartContainer = document.getElementById('analytics-device-chart');
    const legendContainer = document.getElementById('analytics-device-legend');
    const emptyEl = document.querySelector('[data-analytics-device-empty]');

    if (!chartContainer || !legendContainer) {
        return;
    }

    if (!devices || !Number(devices.total)) {
        chartContainer.innerHTML = '';
        legendContainer.innerHTML = '';
        emptyEl?.classList.remove('hidden');

        return;
    }

    emptyEl?.classList.add('hidden');

    const segments = [
        { key: 'android', label: 'Android', value: Number(devices.android) || 0 },
        { key: 'ios', label: 'iOS', value: Number(devices.ios) || 0 },
        { key: 'web', label: 'Web', value: Number(devices.web) || 0 },
        { key: 'other', label: 'Other', value: Number(devices.other) || 0 },
    ].filter((segment) => segment.value > 0);

    const total = segments.reduce((sum, segment) => sum + segment.value, 0);
    let start = 0;

    const gradientSegments = segments.map((segment) => {
        const percent = total ? (segment.value / total) * 100 : 0;
        const color = DEVICE_COLORS[segment.key];
        const slice = `${color} ${start}% ${start + percent}%`;
        start += percent;

        return { ...segment, percent, color, slice };
    });

    chartContainer.innerHTML = `
        <div class="analytics-device-chart-inner">
            <div class="dashboard-donut-chart analytics-device-donut" style="background: conic-gradient(${gradientSegments.map((segment) => segment.slice).join(', ')});"></div>
            <div class="analytics-device-donut-center">
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">Events</p>
                <p class="text-lg font-bold leading-tight text-slate-900">${total.toLocaleString()}</p>
                <p class="mt-0.5 text-[10px] font-medium text-slate-400">by platform</p>
            </div>
        </div>
    `;

    legendContainer.innerHTML = `
        <p class="text-[11px] text-slate-500">Share of tracked events with a known platform in the selected range.</p>
        ${gradientSegments.map((segment) => `
        <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
            <span class="flex items-center gap-2">
                <span class="inline-flex h-2.5 w-2.5 rounded-full" style="background:${segment.color}"></span>
                ${segment.label}
            </span>
            <span class="font-semibold text-slate-800">${segment.value.toLocaleString()} (${Math.round(segment.percent)}%)</span>
        </div>
    `).join('')}`;
}

function formatViewCount(value) {
    const count = Number(value) || 0;

    return `${count.toLocaleString()} view${count === 1 ? '' : 's'}`;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

window.Analytics = { load: loadAnalytics, exportCsv: exportAnalyticsCsvWithRange };
