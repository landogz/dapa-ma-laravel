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

    select?.addEventListener('change', () => {
        syncCustom();
        if (select.value !== 'custom') onChange?.();
    });
    applyBtn?.addEventListener('click', () => onChange?.());
    syncCustom();
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
            : 'Session/DAU metrics appear after mobile app opens are recorded.';
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

async function loadLeaderboardTab(params) {
    const root = document.getElementById('analytics-leaderboard-table');
    if (!root) return;
    const metric = document.getElementById('analytics-leaderboard-metric')?.value || 'engagements';
    const limit = document.getElementById('analytics-leaderboard-limit')?.value || '25';
    const { data } = await axios.get('/admin/analytics/leaderboard', { params: { ...params, metric, limit } });
    const rows = data.data?.rows ?? [];
    root.innerHTML = rows.length ? `
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500"><tr><th>Rank</th><th>User</th><th>Score</th><th>Views</th><th>Likes</th><th>Comments</th><th>Metric</th></tr></thead>
                <tbody>${rows.map((r) => `
                    <tr class="border-t border-slate-100">
                        <td class="py-2">#${r.rank}</td>
                        <td>${escapeHtml(r.name)} <span class="text-xs text-slate-400">#${r.user_id}</span></td>
                        <td>${r.engagement_score}</td>
                        <td>${r.views}</td>
                        <td>${r.likes}</td>
                        <td>${r.comments}</td>
                        <td class="font-semibold">${Number(r.metric_value || 0).toLocaleString()}</td>
                    </tr>
                `).join('')}</tbody>
            </table>
        </div>
    ` : '<p class="text-sm text-slate-400">No leaderboard rows for this range.</p>';
}

async function loadGeographyTab(params) {
    const root = document.getElementById('analytics-geography-panel');
    if (!root) return;
    const level = document.getElementById('analytics-geo-level')?.value || 'province';
    const { data } = await axios.get('/admin/analytics/geography', { params: { ...params, level } });
    const payload = data.data ?? {};
    if (!payload.has_location_data) {
        root.innerHTML = '<p class="text-sm text-slate-400">No user location profile data yet. Geographic analytics will populate once users set region/province/city.</p>';
        return;
    }
    const rows = payload.rows ?? [];
    root.innerHTML = `
        <p class="mb-2 text-xs text-slate-500">Cells with fewer than ${payload.k_anonymity ?? 5} users are folded into Unknown (${Number(payload.unknown || 0).toLocaleString()}).</p>
        <div class="space-y-2">
            ${rows.map((r) => `
                <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm">
                    <span class="font-medium">${escapeHtml(r.location)}</span>
                    <span class="text-slate-500">${Number(r.registered || 0).toLocaleString()} registered · ${Number(r.active || 0).toLocaleString()} active</span>
                </div>
            `).join('') || '<p class="text-sm text-slate-400">No geographic rows.</p>'}
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
    document.getElementById('analytics-geo-apply')?.addEventListener('click', () => loadGeographyTab(getAnalyticsQueryParams()));
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
