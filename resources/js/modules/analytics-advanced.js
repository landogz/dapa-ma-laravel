import axios from 'axios';
import Swal from 'sweetalert2';
import { showErrorToast, showSuccessToast } from './shared/toast';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

export function getAnalyticsQueryParams() {
    const rangeSelect = document.getElementById('analytics-range-select');
    const range = rangeSelect?.value || document.querySelector('[data-analytics-range].is-active')?.getAttribute('data-analytics-range') || '7';
    const params = { range };
    if (range === 'custom') {
        params.from = document.getElementById('analytics-range-from')?.value || '';
        params.to = document.getElementById('analytics-range-to')?.value || '';
    }
    return params;
}

export function bindAnalyticsTabs() {
    const tabs = document.querySelectorAll('[data-analytics-tab]');
    if (!tabs.length) return;

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const key = tab.getAttribute('data-analytics-tab');
            tabs.forEach((t) => t.classList.toggle('is-active', t === tab));
            document.querySelectorAll('[data-analytics-panel]').forEach((panel) => {
                panel.classList.toggle('hidden', panel.getAttribute('data-analytics-panel') !== key);
            });
            loadAnalyticsTab(key);
        });
    });
}

export function bindAdvancedRangeControls(onChange) {
    const select = document.getElementById('analytics-range-select');
    const customWrap = document.getElementById('analytics-custom-range');
    const applyBtn = document.getElementById('analytics-apply-range');

    const syncCustom = () => {
        if (!customWrap || !select) return;
        customWrap.classList.toggle('hidden', select.value !== 'custom');
    };

    const syncPresets = () => {
        const value = select?.value || '7';
        document.querySelectorAll('[data-analytics-range]').forEach((el) => {
            el.classList.toggle('is-active', el.getAttribute('data-analytics-range') === value);
        });
    };

    select?.addEventListener('change', () => {
        syncCustom();
        syncPresets();
        if (select.value !== 'custom') onChange?.();
    });
    applyBtn?.addEventListener('click', () => onChange?.());
    syncCustom();
    syncPresets();
}

export async function loadAnalyticsTab(key) {
    const params = getAnalyticsQueryParams();
    try {
        if (key === 'users') await loadUsersTab(params);
        if (key === 'mobile') await loadMobileTab(params);
        if (key === 'content') await loadContentTab(params);
        if (key === 'retention') await loadRetentionTab(params);
        if (key === 'journeys') await loadJourneysTab(params);
        if (key === 'leaderboard') await loadLeaderboardTab(params);
        if (key === 'geography') await loadGeographyTab(params);
        if (key === 'alerts') await loadAlertsTab(params);
        if (key === 'reports') await loadReportsTab(params);
    } catch (error) {
        showErrorToast(error.response?.data?.message ?? 'Unable to load analytics tab.', 'Analytics');
    }
}

export function renderExtendedKpis(kpis = {}, tooltips = {}) {
    const map = {
        'registered-users': kpis.registered_users,
        'new-users': kpis.new_users,
        dau: kpis.dau,
        wau: kpis.wau,
        mau: kpis.mau,
        'app-opens': kpis.app_opens,
        'avg-session': kpis.avg_session_duration_seconds,
        'rel-engagements': kpis.relational_engagements,
    };
    Object.entries(map).forEach(([key, value]) => {
        document.querySelectorAll(`[data-analytics-summary="${key}"]`).forEach((el) => {
            if (key === 'avg-session') {
                const secs = Number(value) || 0;
                el.textContent = secs > 0 ? `${Math.round(secs)}s` : '—';
                return;
            }
            el.textContent = value == null ? '—' : Number(value).toLocaleString();
        });
    });

    const since = document.querySelector('[data-analytics-tracking-since]');
    if (since) {
        since.textContent = kpis.sessions_tracking_since
            ? `Session metrics collecting since ${String(kpis.sessions_tracking_since).slice(0, 10)}`
            : 'Session and daily active user metrics appear after mobile app opens are recorded.';
    }

    Object.entries(tooltips).forEach(([key, tip]) => {
        document.querySelectorAll(`[data-analytics-tip="${key}"]`).forEach((el) => {
            el.setAttribute('title', tip);
        });
    });
}

async function loadUsersTab(params) {
    const root = document.getElementById('analytics-users-results');
    if (!root) return;
    const q = document.getElementById('analytics-users-q')?.value?.trim() || '';
    const ageMin = document.getElementById('analytics-users-age-min')?.value || '';
    const ageMax = document.getElementById('analytics-users-age-max')?.value || '';
    const inactive = document.getElementById('analytics-users-inactive')?.value || '';
    const platform = document.getElementById('analytics-users-platform')?.value || '';
    const activity = document.getElementById('analytics-users-activity')?.value || '';

    const { data } = await axios.get('/admin/analytics/users', {
        params: {
            ...params,
            q: q || undefined,
            age_min: ageMin || undefined,
            age_max: ageMax || undefined,
            inactive_days: inactive || undefined,
            platform: platform || undefined,
            activity: activity || undefined,
            per_page: 25,
        },
    });
    const payload = data.data ?? {};
    const rows = Array.isArray(payload.data) ? payload.data : [];
    const total = payload.meta?.total ?? rows.length;
    document.querySelector('[data-analytics-users-count]')?.replaceChildren(
        document.createTextNode(`${Number(total).toLocaleString()} matching users`),
    );

    if (!rows.length) {
        root.innerHTML = '<p class="text-sm text-slate-400">No users match these filters in the selected range.</p>';
        return;
    }

    root.innerHTML = `
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-3">User</th>
                        <th class="py-2 pr-3">Age</th>
                        <th class="py-2 pr-3">Location</th>
                        <th class="py-2 pr-3">Events</th>
                        <th class="py-2 pr-3">Last active</th>
                        <th class="py-2"> </th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map((row) => `
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-3">
                                <div class="font-semibold text-slate-800">${escapeHtml(row.name)}</div>
                                <div class="text-xs text-slate-500">#${row.id} · ${escapeHtml(row.email || '')}</div>
                            </td>
                            <td class="py-2 pr-3">${row.age ?? '—'}</td>
                            <td class="py-2 pr-3">${escapeHtml([row.city, row.province, row.region].filter(Boolean).join(', ') || '—')}</td>
                            <td class="py-2 pr-3">${Number(row.events_in_range || 0).toLocaleString()}</td>
                            <td class="py-2 pr-3 text-xs">${escapeHtml((row.last_active_at || '—').toString().slice(0, 16).replace('T', ' '))}</td>
                            <td class="py-2">
                                <button type="button" class="admin-secondary-button min-h-11 px-3 text-xs" data-analytics-user-open="${row.id}">View</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    root.querySelectorAll('[data-analytics-user-open]').forEach((btn) => {
        btn.addEventListener('click', () => openUserDetail(Number(btn.getAttribute('data-analytics-user-open')), params));
    });
}

async function openUserDetail(userId, params) {
    const { data } = await axios.get(`/admin/analytics/users/${userId}`, { params });
    const payload = data.data ?? {};
    const user = payload.user ?? {};
    const summary = payload.summary ?? {};
    const timeline = Array.isArray(payload.timeline) ? payload.timeline : [];

    await Swal.fire({
        title: escapeHtml(user.name || 'User'),
        width: 720,
        html: `
            <div class="space-y-3 text-left text-sm">
                <p class="text-slate-500">#${user.id} · Age ${user.age ?? '—'} · ${escapeHtml([user.city, user.province, user.region].filter(Boolean).join(', ') || 'Location unknown')}</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] uppercase text-slate-500">Events</p><p class="font-bold">${summary.events ?? 0}</p></div>
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] uppercase text-slate-500">Sessions</p><p class="font-bold">${summary.sessions ?? 0}</p></div>
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] uppercase text-slate-500">App opens</p><p class="font-bold">${summary.app_opens ?? 0}</p></div>
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] uppercase text-slate-500">Post views</p><p class="font-bold">${summary.post_views ?? 0}</p></div>
                </div>
                <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-100 p-3">
                    ${timeline.length ? timeline.map((e) => `
                        <div class="border-b border-slate-50 py-2 last:border-0">
                            <p class="font-medium text-slate-800">${escapeHtml(e.event_type)} ${e.post_title ? `· ${escapeHtml(e.post_title)}` : ''}</p>
                            <p class="text-xs text-slate-500">${escapeHtml((e.occurred_at || '').replace('T', ' ').slice(0, 19))} · ${escapeHtml(e.platform || '—')} · ${escapeHtml(e.screen_name || '')}</p>
                        </div>
                    `).join('') : '<p class="text-slate-400">No timeline events in this range.</p>'}
                </div>
            </div>
        `,
        confirmButtonText: 'Close',
    });
}

async function loadMobileTab(params) {
    const root = document.getElementById('analytics-mobile-panel');
    if (!root) return;
    const { data } = await axios.get('/admin/analytics', { params });
    const payload = data.data ?? {};
    const kpis = payload.kpis ?? {};
    const screens = Array.isArray(payload.screen_breakdown) ? payload.screen_breakdown : [];
    const devices = payload.devices ?? {};

    root.innerHTML = `
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="analytics-mini-kpi"><p class="analytics-mini-kpi-label">App opens</p><p class="analytics-mini-kpi-value">${Number(kpis.app_opens || 0).toLocaleString()}</p></article>
            <article class="analytics-mini-kpi"><p class="analytics-mini-kpi-label">Session rows</p><p class="analytics-mini-kpi-value">${Number(kpis.session_rows || 0).toLocaleString()}</p></article>
            <article class="analytics-mini-kpi"><p class="analytics-mini-kpi-label">Avg session</p><p class="analytics-mini-kpi-value">${kpis.avg_session_duration_seconds ? `${Math.round(kpis.avg_session_duration_seconds)}s` : '—'}</p></article>
            <article class="analytics-mini-kpi"><p class="analytics-mini-kpi-label">Android / iOS</p><p class="analytics-mini-kpi-value">${Number(devices.android || 0).toLocaleString()} / ${Number(devices.ios || 0).toLocaleString()}</p></article>
        </div>
        <div class="mt-4">
            <h4 class="text-sm font-semibold text-slate-800">Top screens</h4>
            ${screens.length ? screens.map((s) => `
                <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm">
                    <span>${escapeHtml(s.screen_name)}</span>
                    <span class="text-slate-500">${Number(s.total || 0).toLocaleString()} · ${Number(s.unique_users || 0).toLocaleString()} users</span>
                </div>
            `).join('') : '<p class="mt-2 text-sm text-slate-400">No screen_view events yet. They appear after the updated mobile app ships.</p>'}
        </div>
    `;
}

async function loadContentTab(params) {
    const detail = document.getElementById('analytics-content-detail');
    const engagers = document.getElementById('analytics-content-engagers');
    const postId = Number(document.getElementById('analytics-content-post-id')?.value || 0);
    if (!detail) return;
    if (!postId) {
        detail.innerHTML = '<p class="text-sm text-slate-400">Enter a post ID to inspect engagement.</p>';
        if (engagers) engagers.innerHTML = '';
        return;
    }
    const [{ data: detailRes }, { data: engagersRes }] = await Promise.all([
        axios.get(`/admin/analytics/posts/${postId}`, { params }),
        axios.get(`/admin/analytics/posts/${postId}/engagers`, {
            params: { ...params, interaction: document.getElementById('analytics-content-interaction')?.value || 'any' },
        }),
    ]);
    const d = detailRes.data ?? {};
    const m = d.metrics ?? {};
    const post = d.post ?? {};
    detail.innerHTML = `
        <div class="space-y-2">
            <h4 class="text-base font-semibold text-slate-900">${escapeHtml(post.title || 'Post')}</h4>
            <p class="text-xs text-slate-500">${escapeHtml(post.author || '—')} · ${escapeHtml(post.category || '—')}</p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-xl bg-slate-50 p-3 text-sm"><p class="text-[11px] uppercase text-slate-500">Views</p><p class="font-bold">${m.views ?? 0}</p></div>
                <div class="rounded-xl bg-slate-50 p-3 text-sm"><p class="text-[11px] uppercase text-slate-500">Unique</p><p class="font-bold">${m.unique_viewers ?? 0}</p></div>
                <div class="rounded-xl bg-slate-50 p-3 text-sm"><p class="text-[11px] uppercase text-slate-500">Engagements</p><p class="font-bold">${m.engagements ?? 0}</p></div>
                <div class="rounded-xl bg-slate-50 p-3 text-sm"><p class="text-[11px] uppercase text-slate-500">Rate</p><p class="font-bold">${m.engagement_rate ?? 0}%</p></div>
            </div>
            <p class="text-xs text-slate-500">Likes ${m.likes ?? 0} · Comments ${m.comments ?? 0} · Bookmarks ${m.bookmarks ?? 0} · Shares ${m.shares ?? 0}</p>
        </div>
    `;
    const rows = Array.isArray(engagersRes.data?.data) ? engagersRes.data.data : [];
    if (engagers) {
        engagers.innerHTML = rows.length ? `
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">User</th><th>Views</th><th>Liked</th><th>Commented</th><th>Shared</th></tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr class="border-t border-slate-100">
                            <td class="py-2">${escapeHtml(r.name)} <span class="text-xs text-slate-400">#${r.id}</span></td>
                            <td>${r.views ?? 0}</td>
                            <td>${r.liked ? 'Yes' : '—'}</td>
                            <td>${r.commented ? 'Yes' : '—'}</td>
                            <td>${r.shared ? 'Yes' : '—'}</td>
                        </tr>
                    `).join('')}</tbody>
                </table>
            </div>
        ` : '<p class="text-sm text-slate-400">No engagers for this filter.</p>';
    }
}

async function loadRetentionTab(params) {
    const root = document.getElementById('analytics-retention-heatmap');
    if (!root) return;
    const grain = document.getElementById('analytics-retention-grain')?.value || 'week';
    const { data } = await axios.get('/admin/analytics/retention', { params: { ...params, grain } });
    const payload = data.data ?? {};
    const offsets = payload.offsets ?? [1, 7, 14, 30];
    const rows = payload.rows ?? [];
    if (!rows.length) {
        root.innerHTML = '<p class="text-sm text-slate-400">No registration cohorts in this range yet.</p>';
        return;
    }
    root.innerHTML = `
        <p class="mb-2 text-xs text-slate-500">${escapeHtml(payload.note || '')}</p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-xs sm:text-sm">
                <thead><tr><th class="py-2 pr-2">Cohort</th><th class="pr-2">Size</th>${offsets.map((d) => `<th class="pr-2">D${d}</th>`).join('')}</tr></thead>
                <tbody>
                    ${rows.map((row) => `
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-2 font-medium">${escapeHtml(row.cohort)}</td>
                            <td class="pr-2">${row.size}</td>
                            ${offsets.map((d) => {
                                const val = row.retention?.[String(d)];
                                if (val == null) return '<td class="pr-2 text-slate-300">—</td>';
                                const tone = val >= 50 ? 'bg-emerald-100 text-emerald-800' : val >= 25 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700';
                                return `<td class="pr-2"><span class="inline-flex min-w-12 justify-center rounded-md px-2 py-1 ${tone}">${val}%</span></td>`;
                            }).join('')}
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function loadJourneysTab(params) {
    const root = document.getElementById('analytics-journeys-funnel');
    if (!root) return;
    const { data } = await axios.get('/admin/analytics/journeys', { params });
    const report = data.data?.report ?? {};
    const steps = report.steps ?? [];
    root.innerHTML = `
        <h4 class="text-sm font-semibold text-slate-800">${escapeHtml(report.name || 'Journey')}</h4>
        <div class="mt-3 space-y-3">
            ${steps.map((step) => `
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span>${escapeHtml(step.name)}</span>
                        <span class="text-slate-500">${Number(step.users || 0).toLocaleString()} · ${step.conversion_from_previous ?? 0}%</span>
                    </div>
                    <div class="analytics-track"><div class="analytics-track-fill" style="width:${Math.max(8, Number(step.conversion_from_previous || 0))}%"></div></div>
                    <p class="mt-1 text-[11px] text-slate-400">Drop-off: ${Number(step.drop_off || 0).toLocaleString()}</p>
                </div>
            `).join('') || '<p class="text-sm text-slate-400">No funnel data yet.</p>'}
        </div>
    `;
}

let leaderboardRows = [];
let leaderboardMetricLabel = 'Engagement';
let leaderboardSelectedId = null;

async function loadLeaderboardTab(params) {
    const root = document.getElementById('analytics-leaderboard-table');
    if (!root) return;
    const metricSelect = document.getElementById('analytics-leaderboard-metric');
    const metric = metricSelect?.value || 'engagements';
    const limit = document.getElementById('analytics-leaderboard-limit')?.value || '25';
    const { data } = await axios.get('/admin/analytics/leaderboard', { params: { ...params, metric, limit } });
    const payload = data.data ?? {};
    leaderboardRows = Array.isArray(payload.rows) ? payload.rows : [];
    leaderboardMetricLabel = payload.metric_label || metricLabelFromKey(metric);
    renderLeaderboardCriteria(payload.metrics ?? [], metric);
    if (!leaderboardRows.some((row) => Number(row.user_id) === Number(leaderboardSelectedId))) {
        leaderboardSelectedId = leaderboardRows[0]?.user_id ?? null;
    }
    renderLeaderboardBoard();
}

function metricLabelFromKey(metric) {
    const labels = {
        visits: 'Visits',
        app_usage: 'App usage',
        content_views: 'Content views',
        views: 'Content views',
        engagements: 'Engagement',
        engagement: 'Engagement',
        score: 'Engagement score',
        sessions: 'Sessions',
        app_opens: 'App opens',
        likes: 'Likes',
        comments: 'Comments',
        shares: 'Shares',
        duration: 'Session duration',
    };
    return labels[metric] || String(metric || 'Metric').replaceAll('_', ' ');
}

function renderLeaderboardCriteria(metrics, activeMetric) {
    const root = document.getElementById('analytics-leaderboard-criteria');
    if (!root) return;
    const primary = (Array.isArray(metrics) ? metrics : [])
        .filter((item) => item.group === 'primary')
        .map((item) => item.key);
    const keys = primary.length
        ? primary
        : ['visits', 'app_usage', 'content_views', 'engagements'];
    const labels = {
        visits: 'Visits',
        app_usage: 'App usage',
        content_views: 'Content views',
        engagements: 'Engagement',
    };

    root.innerHTML = keys.map((key) => `
        <button
            type="button"
            class="admin-range-toggle-button min-h-11 rounded-full px-4 text-xs ${key === activeMetric ? 'is-active' : ''}"
            data-leaderboard-metric="${escapeHtml(key)}"
        >${escapeHtml(labels[key] || metricLabelFromKey(key))}</button>
    `).join('');

    root.querySelectorAll('[data-leaderboard-metric]').forEach((button) => {
        button.addEventListener('click', () => {
            const next = button.getAttribute('data-leaderboard-metric');
            const select = document.getElementById('analytics-leaderboard-metric');
            if (select && next) {
                select.value = next;
            }
            loadLeaderboardTab(getAnalyticsQueryParams());
        });
    });
}

function renderLeaderboardBoard() {
    const root = document.getElementById('analytics-leaderboard-table');
    if (!root) return;

    if (!leaderboardRows.length) {
        root.innerHTML = '<p class="text-sm text-slate-400">No leaderboard rows for this range.</p>';
        return;
    }

    const podium = leaderboardRows.slice(0, 3);
    const rest = leaderboardRows.slice(3);
    const selected = leaderboardRows.find((row) => Number(row.user_id) === Number(leaderboardSelectedId))
        || leaderboardRows[0];

    root.innerHTML = `
        <div class="analytics-leaderboard-layout">
            <div class="min-w-0 space-y-4">
                <div class="analytics-leaderboard-podium">
                    ${podium.map((row) => renderLeaderboardPodiumCard(row)).join('')}
                </div>
                <div class="analytics-leaderboard-list">
                    ${rest.length ? rest.map((row) => renderLeaderboardListRow(row)).join('') : `
                        <p class="text-sm text-slate-400">${leaderboardRows.length <= 3 ? 'Only the top ranks are available for this range.' : ''}</p>
                    `}
                </div>
            </div>
            <aside class="analytics-leaderboard-spotlight" aria-label="Selected user stats">
                ${renderLeaderboardSpotlight(selected)}
            </aside>
        </div>
    `;

    root.querySelectorAll('[data-leaderboard-user]').forEach((el) => {
        el.addEventListener('click', () => {
            leaderboardSelectedId = Number(el.getAttribute('data-leaderboard-user'));
            renderLeaderboardBoard();
        });
    });
}

function renderLeaderboardPodiumCard(row) {
    const rank = Number(row.rank) || 0;
    const selected = Number(row.user_id) === Number(leaderboardSelectedId);
    return `
        <button
            type="button"
            class="analytics-leaderboard-podium-card rank-${rank} ${selected ? 'is-selected' : ''}"
            data-leaderboard-user="${row.user_id}"
        >
            <span class="analytics-leaderboard-rank-badge" aria-label="Rank ${rank}">${rank}</span>
            ${leaderboardAvatar(row, rank === 1 ? 'lg' : 'md')}
            <p class="analytics-leaderboard-name">${escapeHtml(row.name)}</p>
            <p class="analytics-leaderboard-subtitle">${escapeHtml(row.subtitle || 'App user')}</p>
            <div class="analytics-leaderboard-metric-pill">
                <span>${escapeHtml(leaderboardMetricLabel)}</span>
                <strong>${Number(row.metric_value || 0).toLocaleString()}</strong>
            </div>
            <div class="analytics-leaderboard-mini-stats">
                <span title="Visits"><i class="fas fa-shoe-prints" aria-hidden="true"></i> ${Number(row.visits || 0).toLocaleString()}</span>
                <span title="Views"><i class="fas fa-eye" aria-hidden="true"></i> ${Number(row.views || 0).toLocaleString()}</span>
                <span title="Engagement"><i class="fas fa-bolt" aria-hidden="true"></i> ${Number(row.engagements || 0).toLocaleString()}</span>
            </div>
        </button>
    `;
}

function renderLeaderboardListRow(row) {
    const selected = Number(row.user_id) === Number(leaderboardSelectedId);
    return `
        <button
            type="button"
            class="analytics-leaderboard-row ${selected ? 'is-selected' : ''}"
            data-leaderboard-user="${row.user_id}"
        >
            <span class="analytics-leaderboard-row-rank">#${row.rank}</span>
            ${leaderboardAvatar(row, 'sm')}
            <span class="min-w-0 flex-1 text-left">
                <span class="block truncate font-semibold text-slate-900">${escapeHtml(row.name)}</span>
                <span class="block truncate text-[11px] text-slate-500">${escapeHtml(row.subtitle || 'App user')}</span>
            </span>
            <span class="analytics-leaderboard-row-metric">
                <span class="text-[10px] uppercase tracking-wide text-slate-400">${escapeHtml(leaderboardMetricLabel)}</span>
                <strong>${Number(row.metric_value || 0).toLocaleString()}</strong>
            </span>
            <span class="hidden text-xs text-slate-500 sm:inline-flex sm:items-center sm:gap-3">
                <span title="App usage"><i class="fas fa-mobile-screen" aria-hidden="true"></i> ${Number(row.app_usage || 0).toLocaleString()}</span>
                <span title="Views"><i class="fas fa-eye" aria-hidden="true"></i> ${Number(row.views || 0).toLocaleString()}</span>
            </span>
        </button>
    `;
}

function renderLeaderboardSpotlight(row) {
    if (!row) {
        return '<p class="text-sm text-slate-400">Select a user to inspect their stats.</p>';
    }

    const durationMins = Math.round((Number(row.duration_seconds) || 0) / 60);
    const tiles = [
        { label: 'Rank', value: `#${row.rank}` },
        { label: leaderboardMetricLabel, value: Number(row.metric_value || 0).toLocaleString() },
        { label: 'Engagement score', value: Number(row.engagement_score || 0).toLocaleString() },
    ];

    return `
        <div class="analytics-leaderboard-spotlight-card">
            <div class="analytics-leaderboard-spotlight-head">
                ${leaderboardAvatar(row, 'lg')}
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#FBD116]">Rank #${row.rank}</p>
                    <h4 class="truncate text-lg font-bold text-white">${escapeHtml(row.name)}</h4>
                    <p class="truncate text-sm text-white/80">${escapeHtml(row.subtitle || 'App user')}</p>
                </div>
            </div>
            <div class="analytics-leaderboard-spotlight-tiles">
                ${tiles.map((tile) => `
                    <article>
                        <p>${escapeHtml(tile.label)}</p>
                        <strong>${escapeHtml(String(tile.value))}</strong>
                    </article>
                `).join('')}
            </div>
            <div class="analytics-leaderboard-spotlight-breakdown">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Breakdown</p>
                ${[
                    ['Visits', row.visits],
                    ['App usage', row.app_usage],
                    ['Content views', row.views],
                    ['Engagement actions', row.engagements],
                    ['App opens', row.app_opens],
                    ['Sessions', row.sessions],
                    ['Session minutes', durationMins],
                    ['Likes', row.likes],
                    ['Comments', row.comments],
                    ['Shares', row.shares],
                    ['Bookmarks', row.bookmarks],
                ].map(([label, value]) => `
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0">
                        <span class="text-slate-600">${escapeHtml(label)}</span>
                        <strong class="text-slate-900">${Number(value || 0).toLocaleString()}</strong>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function leaderboardAvatar(row, size = 'md') {
    const name = String(row?.name || 'User');
    const initials = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('') || '?';
    const sizeClass = size === 'lg' ? 'is-lg' : size === 'sm' ? 'is-sm' : 'is-md';
    if (row?.avatar_url) {
        return `<span class="analytics-leaderboard-avatar ${sizeClass}"><img src="${escapeHtml(row.avatar_url)}" alt="" loading="lazy"></span>`;
    }
    return `<span class="analytics-leaderboard-avatar ${sizeClass} is-fallback" aria-hidden="true">${escapeHtml(initials)}</span>`;
}

let geographyOverviewCache = null;

async function loadGeographyTab(params) {
    const root = document.getElementById('analytics-geography-panel');
    if (!root) return;
    const { data } = await axios.get('/admin/analytics/geography', {
        params: { ...params, level: 'overview', limit: 8 },
    });
    geographyOverviewCache = data.data ?? {};
    renderGeographyOverview();
}

function formatCompactNumber(value) {
    const n = Number(value) || 0;
    if (n >= 1000000) {
        const m = n / 1000000;
        return `${m >= 10 ? Math.round(m) : m.toFixed(1).replace(/\.0$/, '')}M`;
    }
    if (n >= 1000) {
        const k = n / 1000;
        return `${k >= 10 ? Math.round(k) : k.toFixed(1).replace(/\.0$/, '')}K`;
    }
    return n.toLocaleString();
}

function geographyMetricKey() {
    return document.getElementById('analytics-geo-metric')?.value === 'active' ? 'active' : 'registered';
}

function geographyCardStatusIcons() {
    return `
        <span class="analytics-demo-card-status" aria-hidden="true">
            <i class="fas fa-triangle-exclamation text-amber-500"></i>
            <i class="fas fa-circle-check text-emerald-500"></i>
        </span>
    `;
}

function classifyPhilippineIsland(location) {
    const name = String(location || '').toLowerCase();
    if (!name) return 'unknown';

    const mindanao = [
        'mindanao', 'zamboanga', 'region ix', 'region 9', 'region x', 'region 10',
        'region xi', 'region 11', 'region xii', 'region 12', 'region xiii', 'region 13',
        'northern mindanao', 'davao', 'soccsksargen', 'caraga', 'barmm', 'bangsamoro',
        'armm', 'cotabato', 'maguindanao', 'sulu', 'tawi', 'basilan', 'bukidnon',
        'misamis', 'lanao', 'surigao', 'agusan', 'compostela', 'sarangani', 'sultan kudarat',
    ];
    const visayas = [
        'visayas', 'region vi', 'region 6', 'region vii', 'region 7', 'region viii', 'region 8',
        'western visayas', 'central visayas', 'eastern visayas', 'negros', 'cebu', 'bohol',
        'leyte', 'samar', 'iloilo', 'capiz', 'antique', 'guimaras', 'siquijor', 'biliran',
        'aklan',
    ];

    if (mindanao.some((token) => name.includes(token))) return 'mindanao';
    if (visayas.some((token) => name.includes(token))) return 'visayas';
    return 'luzon';
}

const PH_ISLAND_COLORS = {
    luzon: '#055498',
    visayas: '#FBD116',
    mindanao: '#CE2028',
};

function aggregateIslandHeat(rows, valueKey) {
    const totals = { luzon: 0, visayas: 0, mindanao: 0 };
    rows.forEach((row) => {
        const island = classifyPhilippineIsland(row.location);
        if (island === 'unknown') return;
        totals[island] += Number(row[valueKey]) || 0;
    });
    const total = Math.max(totals.luzon + totals.visayas + totals.mindanao, 0);
    const pct = (value) => (total > 0 ? Math.round((value / total) * 1000) / 10 : 0);
    return {
        luzon: totals.luzon,
        visayas: totals.visayas,
        mindanao: totals.mindanao,
        total,
        pct: {
            luzon: pct(totals.luzon),
            visayas: pct(totals.visayas),
            mindanao: pct(totals.mindanao),
        },
    };
}

function renderIslandStatPin(island, label, count, percent, x, y) {
    return `
        <div class="analytics-demo-map-pin analytics-demo-map-pin--${escapeHtml(island)}"
            style="left:${x}%; top:${y}%;"
            title="${escapeHtml(label)}: ${formatCompactNumber(count)} (${percent}%)">
            <span class="analytics-demo-map-pin-count">${formatCompactNumber(count)}</span>
            <span class="analytics-demo-map-pin-pct">${percent}%</span>
            <span class="analytics-demo-map-pin-name">${escapeHtml(label)}</span>
        </div>
    `;
}

function renderPhilippinesMap(rows, valueKey) {
    const heat = aggregateIslandHeat(rows, valueKey);

    return `
        <div class="analytics-demo-map analytics-demo-map--infographic" role="img"
            aria-label="Philippines users by island group: Luzon ${formatCompactNumber(heat.luzon)}, Visayas ${formatCompactNumber(heat.visayas)}, Mindanao ${formatCompactNumber(heat.mindanao)}">
            <div class="analytics-demo-map-canvas">
                <svg viewBox="0 0 220 320" class="analytics-demo-map-svg" aria-hidden="true" preserveAspectRatio="xMidYMid meet">
                    <defs>
                        <radialGradient id="analytics-demo-map-glow" cx="50%" cy="48%" r="58%">
                            <stop offset="0%" stop-color="#ffffff" stop-opacity="0.95"/>
                            <stop offset="55%" stop-color="#eef2f7" stop-opacity="0.9"/>
                            <stop offset="100%" stop-color="#e5e7eb" stop-opacity="0.75"/>
                        </radialGradient>
                        <filter id="analytics-demo-map-soft" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="2" stdDeviation="2.2" flood-color="#0f172a" flood-opacity="0.12"/>
                        </filter>
                    </defs>
                    <rect width="220" height="320" rx="18" fill="url(#analytics-demo-map-glow)"/>
                    <circle cx="110" cy="150" r="118" fill="none" stroke="#d1d5db" stroke-width="1" opacity="0.45"/>
                    <circle cx="110" cy="150" r="88" fill="none" stroke="#d1d5db" stroke-width="1" opacity="0.35"/>
                    <circle cx="110" cy="150" r="58" fill="none" stroke="#d1d5db" stroke-width="1" opacity="0.25"/>

                    <g filter="url(#analytics-demo-map-soft)" stroke="#ffffff" stroke-width="2.2" stroke-linejoin="round">
                        <!-- Luzon -->
                        <path fill="${PH_ISLAND_COLORS.luzon}"
                            d="M108 18c8-1 18 2 24 11 5 8 5 18 2 27-2 7 1 13 5 18 5 6 4 14-1 19-6 6-15 6-22 2-4-2-9-2-12 1-5 4-13 3-16-2-3-6 0-13 4-18 3-4 3-10 1-15-2-8 3-16 15-25z"/>
                        <path fill="${PH_ISLAND_COLORS.luzon}"
                            d="M92 78c5 1 9 5 10 10 1 4-1 8-5 9-4 1-8-2-9-6-1-5 0-9 4-13z"/>
                        <!-- Visayas cluster -->
                        <path fill="${PH_ISLAND_COLORS.visayas}"
                            d="M70 148c7-4 16-2 23 3 5 3 12 2 17-1 7-4 16-2 21 4 4 5 10 7 11 13 1 6-5 11-12 11-5 0-9 4-9 8 0 4-6 8-12 6-7-2-11-7-11-13 0-4-4-6-8-6-7 0-12-6-8-13 2-4 1-7-6-9-4-1-7-2-6-3z"/>
                        <path fill="${PH_ISLAND_COLORS.visayas}"
                            d="M128 172c5-2 11 1 13 6 2 5-1 10-6 11-5 2-10-1-12-6-2-5 1-9 5-11z"/>
                        <path fill="${PH_ISLAND_COLORS.visayas}"
                            d="M152 158c4-1 8 2 9 6 1 4-2 8-6 8-4 1-8-2-9-6 0-4 2-7 6-8z"/>
                        <!-- Mindanao -->
                        <path fill="${PH_ISLAND_COLORS.mindanao}"
                            d="M84 214c14-8 34-6 45 5 10 10 15 25 10 38-5 13-20 21-36 21-15 0-30-8-35-20-5-13 0-28 10-36 2-2 3-5 6-8z"/>
                        <path fill="${PH_ISLAND_COLORS.mindanao}"
                            d="M78 268c5 1 9 5 9 10 0 4-4 7-8 7-5 0-9-4-9-8 0-5 3-9 8-9z"/>
                    </g>
                </svg>

                ${renderIslandStatPin('luzon', 'Luzon', heat.luzon, heat.pct.luzon, 58, 14)}
                ${renderIslandStatPin('visayas', 'Visayas', heat.visayas, heat.pct.visayas, 12, 46)}
                ${renderIslandStatPin('mindanao', 'Mindanao', heat.mindanao, heat.pct.mindanao, 54, 72)}
            </div>
            <div class="analytics-demo-map-legend">
                <span><i style="background:${PH_ISLAND_COLORS.luzon}"></i>Luzon</span>
                <span><i style="background:${PH_ISLAND_COLORS.visayas}"></i>Visayas</span>
                <span><i style="background:${PH_ISLAND_COLORS.mindanao}"></i>Mindanao</span>
            </div>
        </div>
    `;
}

function renderGeographyRankList(rows, valueKey, emptyMessage, options = {}) {
    const limit = options.limit ?? 8;
    const showBars = options.showBars !== false;
    const compact = Boolean(options.compact);
    const list = rows.slice(0, limit);
    const max = Math.max(...list.map((row) => Number(row[valueKey]) || 0), 1);

    if (!list.length) {
        return `<p class="analytics-demo-empty">${escapeHtml(emptyMessage)}</p>`;
    }

    return `
        <ul class="analytics-geo-rank-list ${compact ? 'is-compact' : ''}">
            ${list.map((row) => {
                const value = Number(row[valueKey]) || 0;
                const width = Math.max(6, Math.round((value / max) * 100));
                return `
                    <li>
                        <div class="analytics-geo-rank-meta">
                            <span class="analytics-geo-rank-name" title="${escapeHtml(row.location)}">${escapeHtml(row.location)}</span>
                            <span class="analytics-geo-rank-value">${formatCompactNumber(value)}</span>
                        </div>
                        ${showBars ? `
                            <div class="analytics-geo-rank-track" aria-hidden="true">
                                <div class="analytics-geo-rank-fill" style="width:${width}%"></div>
                            </div>
                        ` : ''}
                    </li>
                `;
            }).join('')}
        </ul>
    `;
}

function renderRealtimeBars(rows, valueKey) {
    const list = rows.slice(0, 12);
    const max = Math.max(...list.map((row) => Number(row[valueKey]) || 0), 1);
    if (!list.length) {
        return `<div class="analytics-demo-realtime-bars is-empty" aria-hidden="true"></div>`;
    }
    return `
        <div class="analytics-demo-realtime-bars" aria-hidden="true" title="Users by region">
            ${list.map((row) => {
                const value = Number(row[valueKey]) || 0;
                const height = Math.max(8, Math.round((value / max) * 100));
                return `<span style="height:${height}%"></span>`;
            }).join('')}
        </div>
        <p class="analytics-demo-realtime-caption">Users by region</p>
    `;
}

function renderCoverageDoughnut(summary) {
    const withLocation = Number(summary.with_location || 0);
    const missing = Number(summary.missing_location || 0);
    const total = Math.max(withLocation + missing, 1);
    const withPct = Math.round((withLocation / total) * 1000) / 10;
    const missingPct = Math.round((missing / total) * 1000) / 10;
    const circumference = 2 * Math.PI * 42;
    const withLen = (withLocation / total) * circumference;
    const missingLen = circumference - withLen;

    return `
        <div class="analytics-demo-donut-wrap">
            <svg class="analytics-demo-donut" viewBox="0 0 120 120" aria-hidden="true">
                <circle cx="60" cy="60" r="42" fill="none" stroke="#93c5fd" stroke-width="16"/>
                <circle cx="60" cy="60" r="42" fill="none" stroke="#055498" stroke-width="16"
                    stroke-dasharray="${withLen} ${missingLen}"
                    stroke-linecap="butt" transform="rotate(-90 60 60)"/>
            </svg>
            <div class="analytics-demo-donut-center">
                <strong>${Number(summary.location_coverage_pct || withPct).toLocaleString()}%</strong>
                <span>covered</span>
            </div>
        </div>
        <ul class="analytics-demo-donut-legend">
            <li><i class="is-with"></i><span>WITH LOCATION</span><strong>${withPct}%</strong></li>
            <li><i class="is-missing"></i><span>MISSING</span><strong>${missingPct}%</strong></li>
        </ul>
    `;
}

function renderGeographyOverview() {
    const root = document.getElementById('analytics-geography-panel');
    if (!root) return;

    const payload = geographyOverviewCache ?? {};
    const valueKey = geographyMetricKey();
    const summary = payload.summary ?? {};
    const regionRows = Array.isArray(payload.region?.rows) ? payload.region.rows : [];
    const provinceRows = Array.isArray(payload.province?.rows) ? payload.province.rows : [];
    const cityRows = Array.isArray(payload.city?.rows) ? payload.city.rows : [];
    const activeTotal = Number(summary.active_in_range || 0);

    if (!payload.has_location_data) {
        root.innerHTML = `
            <div class="analytics-demo-empty-state">
                <p class="text-sm font-semibold text-slate-700">No profile locations yet</p>
                <p class="mt-1 text-sm text-slate-500">Geographic analytics will populate once app users set Region, Province, and City/Municipality on their authorized profiles.</p>
            </div>
        `;
        return;
    }

    root.innerHTML = `
        <div class="analytics-demo-overview">
            <div class="analytics-demo-top">
                <article class="analytics-demo-card analytics-demo-card--hero">
                    <header class="analytics-demo-card-header">
                        <h4>Users by Region</h4>
                        ${geographyCardStatusIcons()}
                    </header>
                    <div class="analytics-demo-hero-body">
                        ${renderPhilippinesMap(regionRows, valueKey)}
                        <div class="analytics-demo-hero-list">
                            ${renderGeographyRankList(regionRows, valueKey, 'No region values on profiles yet.')}
                        </div>
                    </div>
                    <footer class="analytics-demo-card-footer">
                        <span class="analytics-demo-link">View regions →</span>
                    </footer>
                </article>

                <article class="analytics-demo-card analytics-demo-card--realtime">
                    <header class="analytics-demo-card-header">
                        <h4>Active in selected range</h4>
                        ${geographyCardStatusIcons()}
                    </header>
                    <div class="analytics-demo-realtime-body">
                        <p class="analytics-demo-realtime-value">${formatCompactNumber(activeTotal)}</p>
                        ${renderRealtimeBars(regionRows, valueKey)}
                        <div class="analytics-demo-realtime-top">
                            <p class="analytics-demo-realtime-top-label">Top Regions</p>
                            ${renderGeographyRankList(regionRows, valueKey, 'No active regions yet.', {
                                limit: 5,
                                showBars: false,
                                compact: true,
                            })}
                        </div>
                    </div>
                    <footer class="analytics-demo-card-footer">
                        <span class="analytics-demo-link">View activity →</span>
                    </footer>
                </article>
            </div>

            <div class="analytics-demo-bottom">
                <article class="analytics-demo-card">
                    <header class="analytics-demo-card-header">
                        <h4>Users by City</h4>
                        ${geographyCardStatusIcons()}
                    </header>
                    <div class="analytics-demo-card-body">
                        ${renderGeographyRankList(cityRows, valueKey, 'No city/municipality values on profiles yet.')}
                    </div>
                    <footer class="analytics-demo-card-footer">
                        <span class="analytics-demo-link">View cities →</span>
                    </footer>
                </article>

                <article class="analytics-demo-card analytics-demo-card--donut">
                    <header class="analytics-demo-card-header">
                        <h4>Location coverage</h4>
                        ${geographyCardStatusIcons()}
                    </header>
                    <div class="analytics-demo-card-body analytics-demo-card-body--donut">
                        ${renderCoverageDoughnut(summary)}
                    </div>
                    <footer class="analytics-demo-card-footer">
                        <span class="analytics-demo-link">View coverage →</span>
                    </footer>
                </article>

                <article class="analytics-demo-card">
                    <header class="analytics-demo-card-header">
                        <h4>Users by Province</h4>
                        ${geographyCardStatusIcons()}
                    </header>
                    <div class="analytics-demo-card-body">
                        ${renderGeographyRankList(provinceRows, valueKey, 'No province values on profiles yet.')}
                    </div>
                    <footer class="analytics-demo-card-footer">
                        <span class="analytics-demo-link">View provinces →</span>
                    </footer>
                </article>
            </div>

            <p class="analytics-demo-source">${escapeHtml(payload.source || 'Authorized user profile location.')}</p>
        </div>
    `;
}

async function loadAlertsTab() {
    const root = document.getElementById('analytics-alerts-list');
    if (!root) return;
    const status = document.getElementById('analytics-alerts-status')?.value || '';
    const { data } = await axios.get('/admin/analytics/alerts', { params: { status: status || undefined, per_page: 30 } });
    const rows = data.data?.data ?? data.data ?? [];
    const list = Array.isArray(rows) ? rows : [];
    root.innerHTML = list.length ? list.map((alert) => `
        <article class="rounded-xl border border-slate-200 p-3">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">${escapeHtml(alert.severity)} · ${escapeHtml(alert.status)}</p>
                    <h4 class="font-semibold text-slate-900">${escapeHtml(alert.title)}</h4>
                    <p class="mt-1 text-sm text-slate-600">${escapeHtml(alert.description || '')}</p>
                    <p class="mt-1 text-xs text-slate-400">${escapeHtml(String(alert.detected_at || '').replace('T', ' ').slice(0, 19))}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="admin-secondary-button min-h-11 px-3 text-xs" data-alert-status="${alert.id}" data-status="acknowledged">Acknowledge</button>
                    <button type="button" class="admin-secondary-button min-h-11 px-3 text-xs" data-alert-status="${alert.id}" data-status="resolved">Resolve</button>
                    <button type="button" class="admin-secondary-button min-h-11 px-3 text-xs" data-alert-status="${alert.id}" data-status="dismissed">Dismiss</button>
                </div>
            </div>
        </article>
    `).join('') : '<p class="text-sm text-slate-400">No alerts yet. Hourly detection will populate this list when anomalies appear.</p>';

    root.querySelectorAll('[data-alert-status]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                await axios.put(`/admin/analytics/alerts/${btn.getAttribute('data-alert-status')}`, {
                    status: btn.getAttribute('data-status'),
                });
                showSuccessToast('Alert updated.');
                loadAlertsTab();
            } catch (error) {
                showErrorToast(error.response?.data?.message ?? 'Unable to update alert.', 'Analytics');
            }
        });
    });
}

async function loadReportsTab() {
    const history = document.getElementById('analytics-exports-history');
    if (!history) return;
    const { data } = await axios.get('/admin/analytics/exports');
    const rows = data.data?.data ?? [];
    history.innerHTML = rows.length ? rows.map((row) => `
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 py-2 text-sm">
            <div>
                <p class="font-medium">${escapeHtml(row.report_type)} · ${escapeHtml(row.format)}</p>
                <p class="text-xs text-slate-500">${escapeHtml(row.status)} · ${escapeHtml(String(row.created_at || '').replace('T', ' ').slice(0, 19))}</p>
            </div>
            ${row.status === 'ready' ? `<button type="button" class="admin-secondary-button min-h-11 px-3 text-xs" data-analytics-export-download="${row.id}">Download</button>` : ''}
        </div>
    `).join('') : '<p class="text-sm text-slate-400">No export history yet.</p>';

    history.querySelectorAll('[data-analytics-export-download]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                const id = btn.getAttribute('data-analytics-export-download');
                const response = await axios.get(`/admin/analytics/exports/${id}/download`, { responseType: 'blob' });
                const url = window.URL.createObjectURL(response.data);
                const link = document.createElement('a');
                link.href = url;
                link.download = `analytics-export-${id}`;
                link.click();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                showErrorToast(error.response?.data?.message ?? 'Download failed.', 'Analytics');
            }
        });
    });
}

export function bindAdvancedAnalyticsActions(reloadOverview) {
    document.getElementById('analytics-users-apply')?.addEventListener('click', () => loadUsersTab(getAnalyticsQueryParams()));
    document.getElementById('analytics-content-apply')?.addEventListener('click', () => loadContentTab(getAnalyticsQueryParams()));
    document.getElementById('analytics-retention-apply')?.addEventListener('click', () => loadRetentionTab(getAnalyticsQueryParams()));
    document.getElementById('analytics-leaderboard-apply')?.addEventListener('click', () => loadLeaderboardTab(getAnalyticsQueryParams()));
    document.getElementById('analytics-leaderboard-metric')?.addEventListener('change', () => loadLeaderboardTab(getAnalyticsQueryParams()));
    document.getElementById('analytics-leaderboard-limit')?.addEventListener('change', () => loadLeaderboardTab(getAnalyticsQueryParams()));
    document.getElementById('analytics-geo-apply')?.addEventListener('click', () => loadGeographyTab(getAnalyticsQueryParams()));
    document.getElementById('analytics-geo-metric')?.addEventListener('change', () => {
        if (geographyOverviewCache) {
            renderGeographyOverview();
            return;
        }
        loadGeographyTab(getAnalyticsQueryParams());
    });
    document.getElementById('analytics-alerts-apply')?.addEventListener('click', () => loadAlertsTab());
    document.getElementById('analytics-export-queue')?.addEventListener('click', async () => {
        try {
            const params = getAnalyticsQueryParams();
            await axios.post('/admin/analytics/exports', {
                report_type: document.getElementById('analytics-export-type')?.value || 'summary',
                format: document.getElementById('analytics-export-format')?.value || 'csv',
                range: params.range,
                from: params.from,
                to: params.to,
            });
            showSuccessToast('Export queued.');
            loadReportsTab();
        } catch (error) {
            showErrorToast(error.response?.data?.message ?? 'Unable to queue export.', 'Analytics');
        }
    });
    bindAdvancedRangeControls(reloadOverview);
}
