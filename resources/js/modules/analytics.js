import axios from 'axios';
import Swal from 'sweetalert2';
import { getPostRatingMetrics, renderRatingBadge, renderStars } from './shared/ratings';
import { showErrorToast, showSuccessToast } from './shared/toast';

const METRIC_COLORS = {
    total: '#055498',
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

const EVENT_TYPE_META = {
    post_view: { label: 'Post views', icon: 'fas fa-eye' },
    view: { label: 'Views', icon: 'fas fa-eye' },
    bookmark: { label: 'Bookmarks', icon: 'fas fa-bookmark' },
    search: { label: 'Searches', icon: 'fas fa-magnifying-glass' },
    share: { label: 'Shares', icon: 'fas fa-share-nodes' },
};

let cachedDailyCounts = [];
let cachedTopPosts = [];
let cachedRecentEvents = [];
let topPostsSearchBound = false;
let recentSearchBound = false;

export function initAnalyticsModule() {
    initializeAnalyticsRange();
    bindAnalyticsExport();
    bindAnalyticsRefresh();
    bindRecentActivitySearch();
    loadAnalytics();
}

function bindAnalyticsExport() {
    const button = document.querySelector('[data-admin-action="export-analytics"]');
    if (!button || button.dataset.boundExport === '1') {
        return;
    }

    button.dataset.boundExport = '1';
    button.addEventListener('click', () => {
        exportAnalyticsCsvWithRange();
    });
}

function bindAnalyticsRefresh() {
    const button = document.querySelector('[data-admin-action="refresh-analytics"]');
    if (!button || button.dataset.boundRefresh === '1') {
        return;
    }

    button.dataset.boundRefresh = '1';
    button.addEventListener('click', () => {
        loadAnalytics();
    });
}

function setAnalyticsLoading(isLoading) {
    const overlay = document.querySelector('[data-analytics-loading]');
    overlay?.classList.toggle('hidden', !isLoading);
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
    setAnalyticsLoading(true);

    axios.get('/admin/analytics', { params: { range } })
        .then(({ data }) => {
            const payload = data.data ?? {};
            const eventTypes = payload.by_event_type ?? [];
            const topPosts = payload.top_posts ?? [];
            const topRatedPosts = payload.top_rated_posts ?? [];
            const topCategories = payload.top_categories ?? [];
            const ratings = payload.ratings ?? null;
            const ratingDistribution = payload.rating_distribution ?? [];
            const dailyCounts = payload.daily_counts ?? [];
            const dailyByEventType = payload.daily_by_event_type ?? [];
            const periodComparison = payload.period_comparison ?? null;
            const recentEvents = payload.recent_events ?? [];
            const devices = payload.devices ?? null;
            const generatedAt = payload.generated_at ?? null;
            const audience = payload.audience ?? {};
            const funnel = payload.funnel ?? {};
            const hourlyCounts = payload.hourly_counts ?? [];
            const weekdayCounts = payload.weekday_counts ?? [];
            const insights = payload.insights ?? [];

            cachedDailyCounts = Array.isArray(dailyCounts) ? dailyCounts : [];
            cachedRecentEvents = Array.isArray(recentEvents) ? recentEvents : [];

            renderAnalyticsSummary(periodComparison, generatedAt, eventTypes, audience, funnel);
            renderRatingsSummary(ratings, periodComparison?.label ?? 'previous period');
            renderMetricSparklines(dailyByEventType, dailyCounts);
            renderInsights(insights);
            renderEventTypeSummary(eventTypes);
            renderFunnel(funnel);
            renderTopPosts(topPosts);
            renderTopRatedPosts(topRatedPosts);
            renderTopCategories(topCategories);
            renderDailyCounts(dailyCounts, audience);
            renderHourlyHeatmap(hourlyCounts);
            renderWeekdayChart(weekdayCounts);
            renderRatingDistribution(ratingDistribution);
            renderAudienceSplit(audience);
            renderRecentActivity(cachedRecentEvents);
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
        })
        .finally(() => {
            setAnalyticsLoading(false);
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

function renderAnalyticsSummary(periodComparison, generatedAt, eventTypes = [], audience = {}, funnel = {}) {
    const summarized = summarizeEventTypes(eventTypes);
    const current = periodComparison?.current ?? summarized;
    const previous = periodComparison?.previous ?? {};
    const periodLabel = periodComparison?.label ?? 'previous period';
    const totalEvents = Number(current.total_events ?? audience.total_events ?? summarized.total) || 0;
    const uniqueUsers = Number(current.unique_users ?? audience.unique_users) || 0;
    const uniqueSessions = Number(current.unique_sessions ?? audience.unique_sessions) || 0;
    const views = Number(current.views ?? 0);
    const bookmarks = Number(current.bookmarks ?? 0);
    const shares = Number(current.shares ?? 0);
    const engagement = Number(funnel.engagement ?? (views > 0 ? ((bookmarks + shares) / views) * 100 : 0));
    const previousEngagement = Number(previous.views) > 0
        ? (((Number(previous.bookmarks) || 0) + (Number(previous.shares) || 0)) / Number(previous.views)) * 100
        : 0;

    const setValue = (key, value, formatted = null) => {
        const el = document.querySelector(`[data-analytics-summary="${key}"]`);

        if (el) {
            el.textContent = formatted ?? Number(value).toLocaleString();
        }
    };

    setValue('total-events', totalEvents);
    setValue('views', views);
    setValue('unique-users', uniqueUsers);
    setValue('unique-sessions', uniqueSessions);
    setValue('bookmarks', current.bookmarks ?? 0);
    setValue('searches', current.searches ?? 0);
    setValue('shares', current.shares ?? 0);
    setValue('engagement', engagement, `${engagement.toFixed(1)}%`);

    updatePeriodTrend('total-events', totalEvents, previous.total_events ?? 0, periodLabel);
    updatePeriodTrend('views', views, previous.views ?? 0, periodLabel);
    updatePeriodTrend('unique-users', uniqueUsers, previous.unique_users ?? 0, periodLabel);
    updatePeriodTrend('unique-sessions', uniqueSessions, previous.unique_sessions ?? 0, periodLabel);
    updatePeriodTrend('bookmarks', current.bookmarks ?? 0, previous.bookmarks ?? 0, periodLabel);
    updatePeriodTrend('searches', current.searches ?? 0, previous.searches ?? 0, periodLabel);
    updatePeriodTrend('shares', current.shares ?? 0, previous.shares ?? 0, periodLabel);
    updateChangeBadge('engagement', engagement, previousEngagement);

    const avgDayEl = document.querySelector('[data-analytics-avg-day]');

    if (avgDayEl) {
        const avg = Number(audience.avg_per_day ?? 0);
        const peakTotal = Number(audience.peak_day?.total ?? 0);
        avgDayEl.textContent = peakTotal > 0
            ? `Avg ${avg.toLocaleString()} / day · peak ${peakTotal.toLocaleString()}`
            : `Avg ${avg.toLocaleString()} events / day`;
    }

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
    sparklines.total = (filledDays ?? []).map((day) => Number(day.total) || 0);

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
        const exportButton = document.querySelector('[data-admin-action="export-analytics"]');
        exportButton?.setAttribute('disabled', 'true');

        try {
            const response = await axios.get('/admin/analytics/export', {
                params: { range },
                responseType: 'blob',
            });

            const payload = response.data;
            const contentType = String(response.headers?.['content-type'] ?? '');
            if (contentType.includes('application/json') || (payload instanceof Blob && payload.type.includes('application/json'))) {
                const text = payload instanceof Blob ? await payload.text() : String(payload);
                let message = 'Failed to export analytics data.';
                try {
                    message = JSON.parse(text)?.message ?? message;
                } catch (_) {
                    // Keep the default message when the body is not JSON.
                }
                showErrorToast(message, 'Export failed');
                return;
            }

            const blob = payload instanceof Blob
                ? payload
                : new Blob([payload], { type: 'text/csv;charset=utf-8;' });
            const downloadUrl = window.URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = downloadUrl;
            anchor.download = `dape-ma-analytics-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            window.URL.revokeObjectURL(downloadUrl);
            showSuccessToast('Analytics CSV downloaded.', 'Export ready');
        } catch (error) {
            showErrorToast(await messageFromExportError(error), 'Export failed');
        } finally {
            exportButton?.removeAttribute('disabled');
        }
    });
}

function renderEventTypeSummary(events) {
    const container = document.getElementById('analytics-event-summary');
    if (!container) {
        return;
    }

    if (!events.length) {
        container.innerHTML = buildAnalyticsEmptyState(
            'No events recorded yet',
            'Event mix will appear here once the app starts tracking activity.',
            'fa-chart-pie',
        );

        return;
    }

    const summary = summarizeEventTypes(events);
    const colors = [METRIC_COLORS.views, METRIC_COLORS.bookmarks, METRIC_COLORS.searches, METRIC_COLORS.shares, '#123a60', '#CE2028'];

    const rows = events.map((event, index) => {
        const value = Number(event.total) || 0;
        const percent = summary.total ? Math.round((value / summary.total) * 100) : 0;
        const typeKey = String(event.event_type || '').toLowerCase();
        const meta = EVENT_TYPE_META[typeKey] ?? { label: formatEventTypeLabel(event.event_type), icon: 'fas fa-bolt' };
        const color = colors[index % colors.length];

        return `
            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-3 text-xs font-medium text-slate-600">
                    <span class="inline-flex min-w-0 items-center gap-2">
                        <i class="${meta.icon} text-[11px]" style="color:${color}"></i>
                        <span class="truncate">${escapeHtml(meta.label)}</span>
                    </span>
                    <span class="shrink-0 text-slate-500">${value.toLocaleString()} · ${percent}%</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full" style="width:${percent}%;background:${color};"></div>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = `
        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
            ${summary.total.toLocaleString()} events across ${events.length} type${events.length === 1 ? '' : 's'}
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

    const maxViews = Math.max(...posts.map((post) => Number(post.views) || 0), 1);
    const totalViews = cachedTopPosts.reduce((sum, post) => sum + (Number(post.views) || 0), 0) || 1;

    const rows = posts.map((post, index) => {
        const title = escapeHtml(post.post?.title ?? 'Untitled post');
        const postId = post.post?.id ?? post.post_id;
        const views = Number(post.views) || 0;
        const share = Math.round((views / totalViews) * 100);
        const width = Math.max(8, Math.round((views / maxViews) * 100));
        const category = escapeHtml(post.post?.category?.name ?? '');
        const titleMarkup = postId
            ? `<a href="/admin/posts?post=${encodeURIComponent(postId)}" class="admin-table-link-chip block max-w-full truncate text-left" title="${title}">${title}</a>`
            : `<span class="block max-w-full truncate text-left text-sm font-semibold text-slate-800">${title}</span>`;
        const ratingMarkup = getPostRatingMetrics(post.post ?? {}).count > 0
            ? `<span class="shrink-0">${renderRatingBadge(post.post ?? {}, { compact: true })}</span>`
            : '<span class="shrink-0 text-[11px] text-slate-400">No ratings</span>';

        return `
            <div class="space-y-1.5 border-b border-slate-100 py-2.5 last:border-0" data-analytics-post-row>
                <div class="flex items-start gap-3">
                    <span class="mt-1 w-5 shrink-0 text-xs font-bold text-slate-400">${index + 1}</span>
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div class="flex min-w-0 flex-col gap-1.5 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                            <div class="min-w-0 flex-1">
                                ${titleMarkup}
                                ${category ? `<p class="mt-0.5 truncate text-[11px] text-slate-400">${category}</p>` : ''}
                            </div>
                            <div class="flex shrink-0 flex-row items-center gap-2 sm:flex-col sm:items-end sm:gap-1">
                                <span class="whitespace-nowrap text-xs font-semibold text-[#055498]">${formatViewCount(post.views)}</span>
                                ${ratingMarkup}
                            </div>
                        </div>
                        <div class="analytics-post-share">
                            <div class="analytics-track">
                                <div class="analytics-track-fill" style="width:${width}%"></div>
                            </div>
                            <span class="w-8 shrink-0 text-right text-[10px] font-semibold text-slate-400">${share}%</span>
                        </div>
                    </div>
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
            ? `<a href="/admin/posts?post=${encodeURIComponent(postId)}" class="admin-table-link-chip block max-w-full truncate text-left" title="${title}">${title}</a>`
            : `<span class="block max-w-full truncate text-left text-sm font-semibold text-slate-800">${title}</span>`;

        return `
            <div class="flex items-start gap-3 border-b border-slate-100 py-2.5 last:border-0">
                <span class="mt-1 w-5 shrink-0 text-xs font-bold text-slate-400">${index + 1}</span>
                <div class="flex min-w-0 flex-1 flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                    <div class="min-w-0 flex-1">${titleMarkup}</div>
                    <div class="shrink-0">${renderRatingBadge(post, { compact: true })}</div>
                </div>
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
    if (!container) {
        return;
    }

    container.innerHTML = buildActivityChartHtml(days, {
        emptyTitle: 'Daily trend will appear here',
        emptyMessage: 'Once more analytics events are collected, this chart will show how activity changes over time.',
        singleTitle: 'Not enough data to show a trend',
        singleMessage: 'At least two days of activity are required before a meaningful daily trend can be displayed.',
    });
}

function niceCeiling(value, steps = 4) {
    const max = Math.max(Number(value) || 0, 0);

    if (max === 0) {
        return steps;
    }

    const roughStep = max / steps;
    const exp = 10 ** Math.floor(Math.log10(roughStep));
    const fraction = roughStep / exp;
    let niceFraction = 10;

    if (fraction <= 1) niceFraction = 1;
    else if (fraction <= 2) niceFraction = 2;
    else if (fraction <= 2.5) niceFraction = 2.5;
    else if (fraction <= 5) niceFraction = 5;

    const step = niceFraction * exp;

    return step * steps;
}

function buildYTicks(ceiling, steps = 4) {
    const step = ceiling / steps;

    return Array.from({ length: steps + 1 }, (_, index) => {
        const value = step * index;
        return Number.isInteger(value) ? value : Number(value.toFixed(1));
    });
}

function buildActivityChartHtml(days, messages, options = {}) {
    if (!days.length) {
        return buildAnalyticsEmptyState(messages.emptyTitle, messages.emptyMessage, 'fa-chart-line');
    }

    const rawMax = Math.max(...days.map((day) => Number(day.total) || 0), 0);
    const ceiling = niceCeiling(rawMax, 4);
    const ticks = buildYTicks(ceiling, 4);
    const plotHeight = Number(options.plotHeight) || 160;
    const showAllLabels = days.length <= 7;

    return `
        <div class="analytics-chart-shell">
            <div class="analytics-chart-y-axis">
                <span class="analytics-chart-y-label">Events</span>
                <div class="analytics-chart-y-ticks" aria-hidden="true">
                    ${[...ticks].reverse().map((tick) => `<span class="analytics-chart-y-tick">${tick.toLocaleString()}</span>`).join('')}
                </div>
            </div>
            <div class="analytics-chart-plot-wrap">
                <div class="analytics-chart-plot">
                    <div class="analytics-chart-gridlines" aria-hidden="true">
                        ${ticks.map((tick) => {
                            const top = ceiling === 0 ? 100 : 100 - ((tick / ceiling) * 100);
                            return `<span class="analytics-chart-gridline" style="top:${top}%"></span>`;
                        }).join('')}
                    </div>
                    <div class="dashboard-activity-bars analytics-activity-bars" style="--bars:${days.length}">
                    ${days.map((day, index) => {
                        const total = Number(day.total) || 0;
                        const height = total === 0
                            ? 3
                            : Math.max(8, Math.round((total / ceiling) * plotHeight));
                        const label = showAllLabels || index % 2 === 0 || index === days.length - 1
                            ? formatChartDateLabel(day.date)
                            : '';
                        const fullDate = formatChartDateLabel(day.date, true);
                        const countLabel = `${total.toLocaleString()} event${total === 1 ? '' : 's'}`;
                        const tooltipLabel = `${fullDate}: ${countLabel}`;

                        return `
                            <div
                                class="dashboard-activity-bar analytics-activity-bar analytics-chart-column"
                                data-chart-bar
                                data-chart-date="${escapeHtml(fullDate)}"
                                data-chart-count="${escapeHtml(String(total))}"
                                tabindex="0"
                                role="img"
                                aria-label="${escapeHtml(tooltipLabel)}"
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

function renderOverviewActivity(days, rangeDays = 7) {
    const container = document.getElementById('dashboard-activity-chart');
    const subtitle = document.querySelector('[data-activity-subtitle]');
    const summary = document.getElementById('dashboard-activity-summary');

    if (!container) {
        return;
    }

    const daysCount = Number(rangeDays) || days.length || 7;
    if (subtitle) {
        subtitle.textContent = `Events in the last ${daysCount} day${daysCount === 1 ? '' : 's'}`;
    }

    container.innerHTML = buildActivityChartHtml(days, {
        emptyTitle: 'No activity data available',
        emptyMessage: 'Activity will appear here once analytics events are recorded.',
        singleTitle: 'No activity data available',
        singleMessage: 'Activity will appear here once analytics events are recorded.',
    }, { plotHeight: 280 });

    renderOverviewActivitySummary(days, summary);
}

function renderOverviewActivitySummary(days, summary) {
    if (!summary) {
        return;
    }

    if (!days.length) {
        summary.hidden = true;
        summary.innerHTML = '';
        return;
    }

    const totals = days.map((day) => Number(day.total) || 0);
    const totalEvents = totals.reduce((sum, value) => sum + value, 0);
    const peakValue = Math.max(...totals, 0);
    const peakIndex = totals.indexOf(peakValue);
    const peakDay = peakIndex >= 0 ? formatChartDateLabel(days[peakIndex]?.date, true) : '—';
    const dailyAverage = days.length > 0 ? Math.round((totalEvents / days.length) * 10) / 10 : 0;

    summary.hidden = false;
    summary.innerHTML = `
        <div class="admin-chart-summary-item">
            <span class="admin-chart-summary-label">Total events</span>
            <span class="admin-chart-summary-value">${totalEvents.toLocaleString()}</span>
        </div>
        <div class="admin-chart-summary-item">
            <span class="admin-chart-summary-label">Peak day</span>
            <span class="admin-chart-summary-value">${escapeHtml(peakDay)}${peakValue > 0 ? ` · ${peakValue.toLocaleString()}` : ''}</span>
        </div>
        <div class="admin-chart-summary-item">
            <span class="admin-chart-summary-label">Daily average</span>
            <span class="admin-chart-summary-value">${dailyAverage.toLocaleString()}</span>
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
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-[var(--color-border)] bg-slate-50 px-4 py-8 text-center">
                <span class="admin-stat-card-icon"><i class="fas fa-inbox"></i></span>
                <p class="text-sm font-medium text-[var(--color-text)]">No recent activity yet</p>
                <p class="text-xs text-[var(--color-text-muted)]">Events will show up here as people use the app.</p>
            </div>
        `;

        return;
    }

    const sorted = [...events].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    const viewGroups = new Map();
    const otherItems = [];

    sorted.forEach((event) => {
        const type = String(event.event_type || '').toLowerCase();
        const postId = event.post?.id ?? event.post_id ?? '';
        const createdAt = event.created_at ? new Date(event.created_at).getTime() : 0;
        const isView = type === 'post_view' || type === 'view';

        if (isView && postId !== '' && postId !== null) {
            const key = String(postId);
            const existing = viewGroups.get(key);

            if (existing) {
                existing.count += 1;
                if (createdAt > existing.latestAtMs) {
                    existing.latestAt = event.created_at;
                    existing.latestAtMs = createdAt;
                    existing.post = event.post || existing.post;
                    existing.user = event.user || existing.user;
                }
                return;
            }

            viewGroups.set(key, {
                eventType: type,
                postId,
                post: event.post,
                user: event.user,
                count: 1,
                latestAt: event.created_at,
                latestAtMs: createdAt,
            });
            return;
        }

        otherItems.push({
            eventType: type,
            postId,
            post: event.post,
            user: event.user,
            count: 1,
            latestAt: event.created_at,
            latestAtMs: createdAt,
        });
    });

    const groups = [...viewGroups.values(), ...otherItems]
        .sort((a, b) => b.latestAtMs - a.latestAtMs)
        .slice(0, 5);

    container.innerHTML = groups.map((group) => {
        const meta = describeGroupedEvent(group);
        const exact = group.latestAt ? new Date(group.latestAt).toLocaleString() : '';

        return `
            <div class="flex items-start justify-between gap-3 rounded-xl border border-[var(--color-border)] bg-slate-50 px-3 py-3">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="admin-activity-icon ${meta.tone}" aria-hidden="true">
                        <i class="${meta.icon}"></i>
                    </span>
                    <div class="min-w-0 space-y-0.5">
                        <p class="truncate text-sm font-semibold text-[var(--color-text)]">${escapeHtml(meta.title)}</p>
                        ${meta.details ? `<p class="text-xs text-[var(--color-text-muted)]">${escapeHtml(meta.details)}</p>` : ''}
                    </div>
                </div>
                <p class="shrink-0 text-xs font-medium text-[var(--color-text-muted)]" title="${escapeHtml(exact)}">${formatRelativeTime(group.latestAt)}</p>
            </div>
        `;
    }).join('');
}

function describeGroupedEvent(group) {
    const type = String(group.eventType || '').toLowerCase();
    const count = group.count ?? 1;
    const userName = group.user?.name || 'A user';
    const postTitle = group.post?.title || 'a post';

    if (type === 'post_view' || type === 'view') {
        return {
            icon: 'fas fa-eye',
            tone: 'is-view',
            title: count > 1 ? `${postTitle} viewed ${count} times` : `${postTitle} viewed`,
            details: count > 1 ? `${userName} and others viewed this post.` : `${userName} viewed this post.`,
        };
    }

    if (type === 'search') {
        return {
            icon: 'fas fa-magnifying-glass',
            tone: 'is-search',
            title: 'Search performed',
            details: `${userName} searched for content in the app.`,
        };
    }

    if (type === 'bookmark') {
        return {
            icon: 'fas fa-bookmark',
            tone: 'is-bookmark',
            title: count > 1 ? `${postTitle} bookmarked ${count} times` : 'Post bookmarked',
            details: `${userName} bookmarked this post.`,
        };
    }

    if (type === 'share') {
        return {
            icon: 'fas fa-share-nodes',
            tone: 'is-share',
            title: 'Post shared',
            details: `${userName} shared "${postTitle}".`,
        };
    }

    if (type === 'signup' || type === 'register' || type === 'user_registered') {
        return {
            icon: 'fas fa-user-plus',
            tone: 'is-signup',
            title: count > 1 ? `${count} new signups` : 'New signup',
            details: `${userName} joined the app.`,
        };
    }

    if (type.includes('notification') || type === 'admin_push') {
        return {
            icon: 'fas fa-bell',
            tone: 'is-notification',
            title: 'Notification sent',
            details: 'An in-app campaign reached users.',
        };
    }

    return {
        icon: 'fas fa-bolt',
        tone: 'is-default',
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
        container.innerHTML = `
            <div class="rounded-xl border border-dashed border-[var(--color-border)] bg-slate-50 px-4 py-8 text-center text-sm text-[var(--color-text-muted)]">
                No post engagement data available yet.
            </div>
        `;

        return;
    }

    const placeholderThumb = `
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)]" data-top-post-placeholder>
            <i class="fas fa-image" aria-hidden="true"></i>
        </span>
    `;

    container.innerHTML = posts.slice(0, 5).map((row) => {
        const post = row.post ?? {};
        const views = Number(row.views) || 0;
        const previous = Number(row.previous_views) || 0;
        const isNew = previous === 0 && views > 0;
        const deltaPct = previous > 0
            ? Math.round(((views - previous) / previous) * 100)
            : 0;
        let trendHtml = '';

        if (isNew) {
            trendHtml = ' <span class="admin-trend-new">New</span>';
        } else if (previous > 0 && deltaPct > 0) {
            trendHtml = ` <span class="admin-trend-up">· up ${deltaPct}%</span>`;
        } else if (previous > 0 && deltaPct < 0) {
            trendHtml = ` <span class="admin-trend-down">· down ${Math.abs(deltaPct)}%</span>`;
        }

        const thumb = post.media_url
            ? `<span class="relative inline-flex h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-[var(--color-primary-soft)]">
                    <img
                        src="${escapeHtml(post.media_url)}"
                        alt=""
                        class="h-10 w-10 rounded-lg object-cover"
                        data-top-post-thumb
                        onerror="this.classList.add('hidden'); const p=this.parentElement?.querySelector('[data-top-post-placeholder]'); if(p){p.classList.remove('hidden');}"
                    >
                    <span class="hidden absolute inset-0 inline-flex items-center justify-center text-[var(--color-primary)]" data-top-post-placeholder>
                        <i class="fas fa-image" aria-hidden="true"></i>
                    </span>
               </span>`
            : placeholderThumb;
        const href = post.id ? `/admin/posts?post=${encodeURIComponent(post.id)}` : '/admin/posts';

        return `
        <a href="${href}" class="flex items-center justify-between gap-4 rounded-xl border border-[var(--color-border)] bg-slate-50 px-3 py-3 transition hover:border-[var(--color-primary)]/30 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:ring-offset-2">
            <div class="flex min-w-0 items-center gap-3">
                ${thumb}
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-[var(--color-text)]">${escapeHtml(post.title ?? 'Untitled Post')}</p>
                    <p class="text-xs text-[var(--color-text-muted)]">
                        ${formatViewCount(views)}${trendHtml}
                    </p>
                </div>
            </div>
            <i class="fas fa-chevron-right text-xs text-[var(--color-text-muted)]" aria-hidden="true"></i>
        </a>
    `;
    }).join('');
}

function initializeOverviewActivity(days) {
    const container = document.getElementById('dashboard-activity-chart');

    if (!container) {
        return;
    }

    const rangeDays = Number(getSelectedAnalyticsRange()) || days.length || 7;
    renderOverviewActivity(days, rangeDays);
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
    const dashboardRange = document.querySelector('[data-dashboard-range]');
    if (dashboardRange?.value) {
        return dashboardRange.value;
    }

    const active = document.querySelector('[data-analytics-range].is-active');

    return active?.getAttribute('data-analytics-range') || '7';
}

function initializeAnalyticsRange() {
    const buttons = document.querySelectorAll('[data-analytics-range]');

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((other) => other.classList.remove('is-active'));
            button.classList.add('is-active');
            loadAnalytics();
        });
    });

    const dashboardRange = document.querySelector('[data-dashboard-range]');
    if (dashboardRange && dashboardRange.dataset.analyticsBound !== '1') {
        dashboardRange.dataset.analyticsBound = '1';
        dashboardRange.addEventListener('change', () => {
            if (document.getElementById('dashboard-activity-chart')) {
                loadAnalytics();
            }
        });
    }
}

function updatePeriodTrend(key, current, previousValue, periodLabel) {
    const elements = document.querySelectorAll(`[data-analytics-trend="${key}"]`);
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

    updateChangeBadge(key, currentValue, previous);
}

function percentChange(current, previous) {
    const currentValue = Number(current) || 0;
    const previousValue = Number(previous) || 0;

    if (previousValue === 0) {
        return currentValue === 0 ? 0 : 100;
    }

    return Math.round(((currentValue - previousValue) / previousValue) * 100);
}

function updateChangeBadge(key, current, previous) {
    const element = document.querySelector(`[data-analytics-change="${key}"]`);

    if (!element) {
        return;
    }

    const delta = percentChange(current, previous);
    element.classList.remove('is-up', 'is-down');

    if (delta > 0) {
        element.classList.add('is-up');
        element.textContent = `+${delta}%`;
        return;
    }

    if (delta < 0) {
        element.classList.add('is-down');
        element.textContent = `${delta}%`;
        return;
    }

    element.textContent = '0%';
}

function renderInsights(insights) {
    const container = document.querySelector('[data-analytics-insights]');

    if (!container) {
        return;
    }

    if (!Array.isArray(insights) || insights.length === 0) {
        container.classList.remove('is-visible');
        container.innerHTML = '';

        return;
    }

    container.classList.add('is-visible');
    container.innerHTML = insights.map((insight) => {
        const tone = ['up', 'down'].includes(insight.tone) ? insight.tone : 'info';
        const icon = escapeHtml(insight.icon || 'fa-lightbulb');

        return `
            <article class="analytics-insight-chip is-${tone}">
                <span class="analytics-insight-chip-icon">
                    <i class="fas ${icon}" aria-hidden="true"></i>
                </span>
                <p class="analytics-insight-chip-text">${escapeHtml(insight.text || '')}</p>
            </article>
        `;
    }).join('');
}

function renderFunnel(funnel) {
    const container = document.getElementById('analytics-funnel');

    if (!container) {
        return;
    }

    const views = Number(funnel.views) || 0;
    const bookmarks = Number(funnel.bookmarks) || 0;
    const shares = Number(funnel.shares) || 0;
    const max = Math.max(views, bookmarks, shares, 1);

    if (views + bookmarks + shares === 0) {
        container.innerHTML = buildAnalyticsEmptyState(
            'No funnel data yet',
            'Views, bookmarks, and shares will populate this conversion path.',
            'fa-filter',
        );

        return;
    }

    const steps = [
        { key: 'views', label: 'Views', value: views, className: '' },
        { key: 'bookmarks', label: 'Bookmarks', value: bookmarks, className: 'is-bookmarks' },
        { key: 'shares', label: 'Shares', value: shares, className: 'is-shares' },
    ];

    container.innerHTML = `
        <div class="space-y-3">
            ${steps.map((step) => {
                const width = Math.max(18, Math.round((step.value / max) * 100));
                const conversion = views > 0 ? Math.round((step.value / views) * 100) : 0;

                return `
                    <div class="analytics-funnel-step">
                        <div class="flex items-center justify-between text-xs font-semibold text-slate-600">
                            <span>${step.label}</span>
                            <span>${step.value.toLocaleString()} · ${conversion}%</span>
                        </div>
                        <div class="analytics-funnel-bar ${step.className}" style="width:${width}%"></div>
                    </div>
                `;
            }).join('')}
            <p class="text-[11px] text-slate-500">Conversion is relative to views in the selected range.</p>
        </div>
    `;
}

function renderTopCategories(categories) {
    const container = document.getElementById('analytics-top-categories');

    if (!container) {
        return;
    }

    if (!Array.isArray(categories) || categories.length === 0) {
        container.innerHTML = buildAnalyticsEmptyState(
            'No category traffic yet',
            'Viewed posts with a category will rank here.',
            'fa-layer-group',
        );

        return;
    }

    const max = Math.max(...categories.map((category) => Number(category.views) || 0), 1);

    container.innerHTML = categories.map((category, index) => {
        const views = Number(category.views) || 0;
        const width = Math.max(8, Math.round((views / max) * 100));
        const name = escapeHtml(category.name || 'Uncategorized');

        return `
            <div class="analytics-category-row border-b border-slate-100 py-2 last:border-0">
                <span class="w-5 shrink-0 text-xs font-bold text-slate-400">${index + 1}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-800">${name}</p>
                    <div class="analytics-track mt-1.5">
                        <div class="analytics-track-fill" style="width:${width}%"></div>
                    </div>
                </div>
                <span class="shrink-0 text-xs font-semibold text-[#055498]">${formatViewCount(views)}</span>
            </div>
        `;
    }).join('');
}

function renderHourlyHeatmap(hours) {
    const container = document.getElementById('analytics-hourly-heatmap');

    if (!container) {
        return;
    }

    if (!Array.isArray(hours) || hours.length === 0) {
        container.innerHTML = buildAnalyticsEmptyState(
            'No hourly pattern yet',
            'A 24-hour heatmap will appear once events are recorded.',
            'fa-clock',
        );

        return;
    }

    const max = Math.max(...hours.map((row) => Number(row.total) || 0), 0);

    if (max === 0) {
        container.innerHTML = buildAnalyticsEmptyState(
            'No hourly pattern yet',
            'A 24-hour heatmap will appear once events are recorded.',
            'fa-clock',
        );

        return;
    }

    const peak = hours.reduce((best, row) => (
        Number(row.total) > Number(best.total) ? row : best
    ), hours[0]);

    container.innerHTML = `
        <div class="overflow-x-auto pb-1">
            <div class="analytics-heat-grid">
                ${hours.map((row) => {
                    const total = Number(row.total) || 0;
                    const intensity = heatIntensity(total, max);
                    const hourLabel = formatHourCompact(row.hour);
                    const aria = `${formatHourFull(row.hour)}: ${total.toLocaleString()} events`;

                    return `
                        <div class="analytics-heat-cell analytics-heat-${intensity}" title="${escapeHtml(aria)}" aria-label="${escapeHtml(aria)}">
                            <span>${hourLabel}</span>
                            <span>${total}</span>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
        <p class="mt-3 text-[11px] text-slate-500">Peak hour ${formatHourFull(peak.hour)} · ${Number(peak.total).toLocaleString()} events</p>
    `;
}

function heatIntensity(value, max) {
    if (!value || !max) {
        return 0;
    }

    const ratio = value / max;

    if (ratio >= 0.8) {
        return 4;
    }

    if (ratio >= 0.55) {
        return 3;
    }

    if (ratio >= 0.3) {
        return 2;
    }

    return 1;
}

function formatHourCompact(hour) {
    const value = Number(hour) || 0;
    const suffix = value >= 12 ? 'p' : 'a';
    const twelve = value % 12 === 0 ? 12 : value % 12;

    return `${twelve}${suffix}`;
}

function formatHourFull(hour) {
    const value = Math.max(0, Math.min(23, Number(hour) || 0));
    const date = new Date();
    date.setHours(value, 0, 0, 0);

    return date.toLocaleTimeString(undefined, { hour: 'numeric' });
}

function renderWeekdayChart(weekdays) {
    const container = document.getElementById('analytics-weekday-chart');

    if (!container) {
        return;
    }

    if (!Array.isArray(weekdays) || weekdays.length === 0) {
        container.innerHTML = buildAnalyticsEmptyState(
            'No weekday pattern yet',
            'Monday through Sunday volume will appear here.',
            'fa-calendar-week',
        );

        return;
    }

    const max = Math.max(...weekdays.map((row) => Number(row.total) || 0), 1);
    const total = weekdays.reduce((sum, row) => sum + (Number(row.total) || 0), 0);

    container.innerHTML = `
        <div class="space-y-2.5">
            ${weekdays.map((row) => {
                const value = Number(row.total) || 0;
                const width = Math.max(value ? 8 : 0, Math.round((value / max) * 100));
                const share = total ? Math.round((value / total) * 100) : 0;

                return `
                    <div class="analytics-weekday-row">
                        <span class="w-9 shrink-0 text-xs font-semibold text-slate-500">${escapeHtml(row.label)}</span>
                        <div class="analytics-track">
                            <div class="analytics-track-fill" style="width:${width}%"></div>
                        </div>
                        <span class="w-16 shrink-0 text-right text-xs font-semibold text-slate-700">${value.toLocaleString()}</span>
                        <span class="w-10 shrink-0 text-right text-[11px] text-slate-400">${share}%</span>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderRatingDistribution(distribution) {
    const container = document.getElementById('analytics-rating-distribution');

    if (!container) {
        return;
    }

    if (!Array.isArray(distribution) || distribution.every((row) => !Number(row.total))) {
        container.innerHTML = buildAnalyticsEmptyState(
            'No ratings in this range',
            'Star totals will appear as users review posts.',
            'fa-star',
        );

        return;
    }

    const max = Math.max(...distribution.map((row) => Number(row.total) || 0), 1);
    const total = distribution.reduce((sum, row) => sum + (Number(row.total) || 0), 0);

    container.innerHTML = `
        <div class="space-y-2.5">
            ${distribution.map((row) => {
                const value = Number(row.total) || 0;
                const width = Math.max(value ? 8 : 0, Math.round((value / max) * 100));
                const share = total ? Math.round((value / total) * 100) : 0;
                const stars = '★'.repeat(Number(row.rating) || 0);

                return `
                    <div class="analytics-rating-row">
                        <span class="w-16 shrink-0 text-xs font-semibold text-amber-500">${stars}</span>
                        <div class="analytics-track">
                            <div class="analytics-track-fill" style="width:${width}%"></div>
                        </div>
                        <span class="w-12 shrink-0 text-right text-xs font-semibold text-slate-700">${value.toLocaleString()}</span>
                        <span class="w-10 shrink-0 text-right text-[11px] text-slate-400">${share}%</span>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderAudienceSplit(audience) {
    const container = document.getElementById('analytics-audience-split');

    if (!container) {
        return;
    }

    const signedIn = Number(audience.signed_in_events) || 0;
    const anonymous = Number(audience.anonymous_events) || 0;
    const total = signedIn + anonymous;

    if (!total) {
        container.innerHTML = '';

        return;
    }

    const signedShare = Math.round((signedIn / total) * 100);
    const anonymousShare = 100 - signedShare;

    container.innerHTML = `
        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Audience split</p>
        <div class="mt-2 flex h-2 overflow-hidden rounded-full bg-slate-100">
            <span class="h-full bg-[#055498]" style="width:${signedShare}%"></span>
            <span class="h-full bg-slate-300" style="width:${anonymousShare}%"></span>
        </div>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500">
            <span>Signed-in ${signedIn.toLocaleString()} (${signedShare}%)</span>
            <span>Anonymous ${anonymous.toLocaleString()} (${anonymousShare}%)</span>
        </div>
    `;
}

function formatEventTypeLabel(value) {
    return String(value || 'Event')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function bindRecentActivitySearch() {
    if (recentSearchBound) {
        return;
    }

    const input = document.getElementById('analytics-recent-search');

    if (!input) {
        return;
    }

    input.addEventListener('input', () => {
        renderRecentActivity(cachedRecentEvents);
    });

    recentSearchBound = true;
}

function renderRecentActivity(events) {
    const container = document.getElementById('analytics-recent-feed');
    const meta = document.querySelector('[data-analytics-recent-meta]');
    const searchInput = document.getElementById('analytics-recent-search');

    if (!container) {
        return;
    }

    const query = searchInput?.value?.trim().toLowerCase() ?? '';
    const list = Array.isArray(events) ? events : [];
    const filtered = query
        ? list.filter((event) => {
            const haystack = [
                event.event_type,
                event.user?.name,
                event.post?.title,
                event.platform,
            ].join(' ').toLowerCase();

            return haystack.includes(query);
        })
        : list;

    if (meta) {
        meta.textContent = filtered.length
            ? `Showing ${filtered.length} of ${list.length} latest events`
            : 'Latest tracked events in this range';
    }

    if (!list.length) {
        container.innerHTML = buildAnalyticsEmptyState(
            'No recent activity',
            'New events will appear here as users interact with the app.',
            'fa-clock',
        );

        return;
    }

    if (!filtered.length) {
        container.innerHTML = '<p class="text-sm text-slate-400">No events match this filter.</p>';

        return;
    }

    container.innerHTML = `
        <div class="grid gap-2 xl:grid-cols-2">
            ${filtered.map((event) => buildRecentActivityCard(event)).join('')}
        </div>
    `;
}

function buildRecentActivityCard(event) {
    const typeKey = String(event.event_type || '').toLowerCase();
    const meta = EVENT_TYPE_META[typeKey] ?? { label: formatEventTypeLabel(event.event_type), icon: 'fas fa-bolt' };
    const when = formatRelativeTime(event.created_at);
    const exactWhen = event.created_at ? new Date(event.created_at).toLocaleString() : '';
    const userName = escapeHtml(event.user?.name || 'Anonymous');
    const postTitle = event.post?.title ? escapeHtml(event.post.title) : 'App activity';
    const postId = event.post?.id ?? event.post_id;
    const platform = formatPlatformLabel(event.platform);
    const titleMarkup = postId
        ? `<a href="/admin/posts?post=${encodeURIComponent(postId)}" class="admin-table-link-chip block max-w-full truncate text-left" title="${postTitle}">${postTitle}</a>`
        : `<span class="block max-w-full truncate text-sm font-semibold text-slate-800">${postTitle}</span>`;

    return `
        <article class="flex min-h-11 items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-3">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#055498]/10 text-[#055498]">
                <i class="${meta.icon}" aria-hidden="true"></i>
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-slate-800">${escapeHtml(meta.label)}</span>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500">${escapeHtml(platform)}</span>
                </div>
                <div class="mt-1 min-w-0">${titleMarkup}</div>
                <p class="mt-1 text-[11px] text-slate-500">${userName} · <span title="${escapeHtml(exactWhen)}">${escapeHtml(when)}</span></p>
            </div>
        </article>
    `;
}

function formatPlatformLabel(platform) {
    const value = String(platform || '').toLowerCase();

    if (value === 'ios') {
        return 'iOS';
    }

    if (value === 'android') {
        return 'Android';
    }

    if (value === 'web') {
        return 'Web';
    }

    return value ? value : 'Unknown';
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

async function messageFromExportError(error) {
    const data = error.response?.data;
    if (data instanceof Blob) {
        try {
            const parsed = JSON.parse(await data.text());
            return parsed?.message ?? 'Failed to export analytics data.';
        } catch (_) {
            return 'Failed to export analytics data.';
        }
    }

    return data?.message ?? 'Failed to export analytics data.';
}

window.Analytics = {
    load: loadAnalytics,
    exportCsv: exportAnalyticsCsvWithRange,
    exportCsvWithRange: exportAnalyticsCsvWithRange,
};
