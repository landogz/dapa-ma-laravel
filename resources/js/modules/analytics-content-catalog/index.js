function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function formatCount(value) {
    return Number(value || 0).toLocaleString();
}

export function renderContentCatalog(catalog = {}) {
    const totals = catalog.totals ?? {};
    const groups = Array.isArray(catalog.groups) ? catalog.groups : [];

    setCatalogKpi('downloads', totals.downloads);
    setCatalogKpi('iec-views', totals.iec_views);
    setCatalogKpi('iec-shares', totals.iec_shares);
    setCatalogKpi('iec-downloads', totals.iec_downloads);
    setCatalogKpi('event-views', totals.event_views);
    setCatalogKpi('event-shares', totals.event_shares);
    setCatalogKpi('contest-views', totals.contest_views);
    setCatalogKpi('contest-shares', totals.contest_shares);
    setCatalogKpi('post-shares', totals.post_shares);
    setCatalogKpi('rehab-views', totals.rehab_views);
    setCatalogKpi('rehab-calls', totals.rehab_calls);
    setCatalogKpi('rehab-directions', totals.rehab_directions);
    setCatalogKpi('hope-org-views', totals.hope_org_views);

    document.querySelectorAll('[data-analytics-content-catalog]').forEach((root) => {
        root.innerHTML = renderCatalogGroups(groups);
    });
}

function setCatalogKpi(key, value) {
    document.querySelectorAll(`[data-analytics-summary="${key}"]`).forEach((el) => {
        el.textContent = value == null ? '0' : formatCount(value);
    });
}

function renderCatalogGroups(groups) {
    if (!groups.length) {
        return '<p class="text-sm text-slate-400">Shares and downloads will appear here as people use IEC materials, events, and contests in the app.</p>';
    }

    return `
        <div class="grid gap-4 xl:grid-cols-2 2xl:grid-cols-3">
            ${groups.map((group) => renderGroupCard(group)).join('')}
        </div>
    `;
}

function renderGroupCard(group) {
    const items = Array.isArray(group.items) ? group.items : [];
    const includeDownloads = Boolean(group.include_downloads);
    const href = escapeHtml(group.href || '#');
    const label = escapeHtml(group.label || 'Content');
    const viewLabel = group.view_label || 'Views';
    const shareLabel = group.share_label || 'Shares';
    const downloadLabel = group.download_label || 'Downloads';

    return `
        <article class="min-w-0 rounded-2xl border border-slate-200 bg-slate-50/70 p-3 sm:p-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#055498]">Catalog</p>
                    <h4 class="mt-0.5 text-sm font-bold text-slate-900">${label}</h4>
                </div>
                <a href="${href}" class="inline-flex min-h-11 items-center rounded-full px-3 text-xs font-semibold text-[#055498]">View all</a>
            </div>
            <div class="mt-3 grid grid-cols-2 ${includeDownloads ? 'sm:grid-cols-3' : ''} gap-2">
                ${metricChip(viewLabel, group.views)}
                ${metricChip(shareLabel, group.shares)}
                ${includeDownloads ? metricChip(downloadLabel, group.downloads) : ''}
            </div>
            <div class="mt-3 space-y-2">
                ${items.length ? items.map((item, index) => renderCatalogItem(item, index, includeDownloads, viewLabel, shareLabel, downloadLabel)).join('') : `<p class="text-xs text-slate-400">No ${label.toLowerCase()} activity in this range yet.</p>`}
            </div>
        </article>
    `;
}

function unitLabel(label, value) {
    const word = String(label || 'items').toLowerCase();
    return Number(value) === 1 && word.endsWith('s') ? word.slice(0, -1) : word;
}

function metricChip(label, value) {
    return `
        <div class="rounded-xl bg-white px-3 py-2">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">${escapeHtml(label)}</p>
            <p class="mt-0.5 text-base font-bold text-slate-900">${formatCount(value)}</p>
        </div>
    `;
}

function renderCatalogItem(item, index, includeDownloads, viewLabel = 'Views', shareLabel = 'Shares', downloadLabel = 'Downloads') {
    const title = escapeHtml(item.title || 'Untitled');
    const href = item.href ? escapeHtml(item.href) : '';
    const titleMarkup = href
        ? `<a href="${href}" class="admin-table-link-chip block max-w-full truncate text-left" title="${title}">${title}</a>`
        : `<span class="block max-w-full truncate text-sm font-semibold text-slate-800">${title}</span>`;
    const stats = [
        `${formatCount(item.views)} ${unitLabel(viewLabel, item.views)}`,
        `${formatCount(item.shares)} ${unitLabel(shareLabel, item.shares)}`,
    ];
    if (includeDownloads) {
        stats.push(`${formatCount(item.downloads)} ${unitLabel(downloadLabel, item.downloads)}`);
    }

    return `
        <div class="flex min-h-11 items-start gap-2 border-t border-slate-200/80 pt-2">
            <span class="mt-0.5 w-5 shrink-0 text-xs font-bold text-slate-400">${index + 1}</span>
            <div class="min-w-0 flex-1">
                ${titleMarkup}
                <p class="mt-0.5 text-[11px] text-slate-500">${stats.join(' · ')}</p>
            </div>
        </div>
    `;
}
