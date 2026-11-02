import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions, getAdminTableMode, bindAdminTableModeChange } from '../shared/datatables';
import {
    bindExportFormatPills,
    buildExportDialogHtml,
    buildSwalFieldsHtml,
    buildSwalOptions,
    syncExportCustomRangePanel,
} from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { bindAdminFilterTabs } from '../shared/filter-tabs';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let checksTable;
let checksTableMode;
let hasBoundViewportListener = false;
let hasBoundRangeControls = false;
let currentTypeFilter = '';
let currentTierFilter = '';
let loadedChecks = [];
const resultCache = new Map();

const TYPE_LABELS = {
    stress: 'Stress Check',
    anxiety: 'Anxiety Check',
    sleep: 'Sleep Quality',
};

const BAND_LABELS = {
    low: 'Low',
    moderate: 'Moderate',
    high: 'High',
    minimal: 'Minimal',
    mild: 'Mild',
    severe: 'Severe',
    good: 'Good',
    fair: 'Fair',
    poor: 'Poor',
};

const TIER_LABELS = {
    low: 'Lower tier',
    high: 'Higher tier',
};

const RANGE_OPTIONS = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: '7', label: 'Last 7 days' },
    { value: '14', label: 'Last 14 days' },
    { value: '30', label: 'Last 30 days' },
    { value: '60', label: 'Last 60 days' },
    { value: '90', label: 'Last 90 days' },
    { value: 'this_week', label: 'This week' },
    { value: 'last_week', label: 'Last week' },
    { value: 'this_month', label: 'This month' },
    { value: 'last_month', label: 'Last month' },
    { value: 'this_quarter', label: 'This quarter' },
    { value: 'last_quarter', label: 'Last quarter' },
    { value: 'this_year', label: 'This year' },
    { value: 'last_year', label: 'Last year' },
    { value: 'all_time', label: 'All time' },
    { value: 'custom', label: 'Custom range' },
];

export function initCareChecksModule() {
    const tableEl = document.getElementById('care-checks-table');
    if (!tableEl) {
        return;
    }

    bindAdminFilterTabs(document.getElementById('care-checks-type-tabs'), (value) => {
        currentTypeFilter = value;
        drawLoadedChecks();
    });

    bindAdminFilterTabs(document.getElementById('care-checks-tier-tabs'), (value) => {
        currentTierFilter = value;
        drawLoadedChecks();
    });

    bindRangeControls();

    const exportButton = document.querySelector('[data-admin-action="export-care-checks"]');
    if (exportButton && exportButton.dataset.boundExport !== '1') {
        exportButton.dataset.boundExport = '1';
        exportButton.addEventListener('click', exportCareChecksWithRange);
    }

    initializeTable(tableEl).then(() => {
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        reloadCareChecksPage();
    });
}

function bindRangeControls() {
    if (hasBoundRangeControls) {
        return;
    }

    const select = document.getElementById('care-checks-range-select');
    const customWrap = document.getElementById('care-checks-custom-range');
    const applyBtn = document.getElementById('care-checks-apply-range');

    const syncCustom = () => {
        customWrap?.classList.toggle('hidden', select?.value !== 'custom');
    };

    const syncQuickToggles = (range) => {
        document.querySelectorAll('[data-care-checks-range]').forEach((el) => {
            el.classList.toggle('is-active', el.getAttribute('data-care-checks-range') === String(range));
        });
    };

    select?.addEventListener('change', () => {
        syncCustom();
        syncQuickToggles(select.value);
        if (select.value !== 'custom') {
            reloadCareChecksPage();
        }
    });

    applyBtn?.addEventListener('click', () => {
        const from = document.getElementById('care-checks-range-from')?.value || '';
        const to = document.getElementById('care-checks-range-to')?.value || '';
        if (!from || !to) {
            showErrorToast('Enter both start and end dates.', 'Date range');
            return;
        }
        if (from > to) {
            showErrorToast('Start date must be on or before the end date.', 'Date range');
            return;
        }
        reloadCareChecksPage();
    });

    document.querySelectorAll('[data-care-checks-range]').forEach((button) => {
        button.addEventListener('click', () => {
            const range = button.getAttribute('data-care-checks-range') || '7';
            if (select) {
                select.value = range;
            }
            syncCustom();
            syncQuickToggles(range);
            reloadCareChecksPage();
        });
    });

    syncCustom();
    syncQuickToggles(select?.value || '7');
    hasBoundRangeControls = true;
}

function getCareChecksQueryParams() {
    const select = document.getElementById('care-checks-range-select');
    const range = select?.value
        || document.querySelector('[data-care-checks-range].is-active')?.getAttribute('data-care-checks-range')
        || '7';
    const params = { range };
    if (range === 'custom') {
        params.from = document.getElementById('care-checks-range-from')?.value || '';
        params.to = document.getElementById('care-checks-range-to')?.value || '';
    }
    return params;
}

function reloadCareChecksPage() {
    loadCareChecks();
}

async function initializeTable(tableElement) {
    const nextMode = getTableMode();
    if (checksTable && checksTableMode === nextMode) {
        return;
    }
    if (checksTable) {
        checksTable.destroy();
        tableElement.innerHTML = '';
    }
    checksTableMode = nextMode;
    checksTable = await createAdminDataTable(
        tableElement,
        getAdminDataTableOptions({
            columns: buildColumns(nextMode),
            searchPlaceholder: 'Search by user...',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ checks',
            language: {
                emptyTable: 'No care checks in this period. Completions from the mobile Self-care Toolkit will appear here.',
            },
            pageLength: nextMode === 'mobile' ? 5 : 15,
            scrollX: nextMode !== 'mobile',
            scrollCollapse: nextMode !== 'mobile',
        }),
    );
}

function bindViewportListener(tableEl) {
    if (hasBoundViewportListener) {
        return;
    }
    const mobileQuery = window.matchMedia('(max-width: 1023px)');
    const handleViewportChange = async () => {
        await initializeTable(tableEl);
        drawLoadedChecks();
    };
    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', handleViewportChange);
    } else {
        mobileQuery.addListener(handleViewportChange);
    }

    bindAdminTableModeChange(handleViewportChange);
    hasBoundViewportListener = true;
}

function getTableMode() {
    return getAdminTableMode();
}

function buildColumns(mode) {
    if (mode === 'mobile') {
        return [{ title: 'Check', className: 'dt-col-mobile-summary', orderable: false }];
    }
    return [
        { title: 'Date', className: 'dt-col-nowrap' },
        { title: 'User', className: 'dt-col-primary dt-col-name', orderable: false },
        { title: 'Check', className: 'dt-col-nowrap', orderable: false },
        { title: 'Level', className: 'dt-col-nowrap', orderable: false },
        { title: 'Score', className: 'dt-col-nowrap', orderable: false },
        { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
    ];
}

export function loadCareChecks() {
    if (!checksTable) {
        return;
    }
    axios.get('/admin/care-checks', {
        params: {
            ...getCareChecksQueryParams(),
            per_page: 200,
        },
    }).then(({ data }) => {
        const rows = data.data?.data ?? data.data ?? [];
        loadedChecks = Array.isArray(rows) ? rows : [];
        drawLoadedChecks();
    }).catch(({ response }) => {
        showErrorToast(response?.data?.message ?? 'Unable to load care checks.');
    });
}

function drawLoadedChecks() {
    if (!checksTable) {
        return;
    }

    const list = loadedChecks.filter((row) => {
        if (currentTypeFilter && row.check_type !== currentTypeFilter) {
            return false;
        }
        if (currentTierFilter && row.display_tier !== currentTierFilter) {
            return false;
        }

        return true;
    });

    resultCache.clear();
    list.forEach((row) => resultCache.set(String(row.id), row));
    checksTable.clear();
    list.forEach((row) => checksTable.row.add(buildRowData(row)));
    checksTable.draw();
}

function buildRowData(row) {
    const userName = escapeHtml(row.user?.name ?? 'Unknown user');
    const userEmail = escapeHtml(row.user?.email ?? '');
    const typeChip = typeChipHtml(row.check_type);
    const bandChip = bandChipHtml(row.band);
    const score = `${row.raw_score}/${row.max_score} (${row.percent}%)`;
    const when = formatDateTime(row.created_at);
    const actions = [{
        tooltip: 'View check',
        icon: 'fas fa-eye',
        className: 'admin-table-action-primary',
        attrs: `onclick="window.CareChecks.view(${row.id})"`,
    }];
    const desktopActions = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActions = buildAdminActionButtons([{ ...actions[0], label: 'View' }], { isMobile: true, nowrap: true });

    if (checksTableMode === 'mobile') {
        return [`
            <div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">${escapeHtml(when)}</p>
                        <p class="admin-table-mobile-title">${userName}</p>
                    </div>
                    ${typeChip}
                </div>
                <div class="admin-table-mobile-details">
                    ${userEmail ? `<p><span>Email:</span> ${userEmail}</p>` : ''}
                    <p><span>Level:</span> ${bandChip}</p>
                    <p><span>Score:</span> ${escapeHtml(score)}</p>
                </div>
                <div class="admin-table-mobile-actions">${mobileActions}</div>
            </div>
        `];
    }

    return [
        escapeHtml(when),
        `<div class="min-w-[10rem]"><p class="font-semibold text-slate-800">${userName}</p>${
            userEmail ? `<p class="text-xs text-slate-500">${userEmail}</p>` : ''
        }</div>`,
        typeChip,
        bandChip,
        escapeHtml(score),
        desktopActions,
    ];
}

function typeChipHtml(type) {
    const label = escapeHtml(TYPE_LABELS[type] ?? type ?? 'Check');
    const tone = ['stress', 'anxiety', 'sleep'].includes(type) ? type : 'stress';
    return `<span class="care-check-type-chip is-${tone}">${label}</span>`;
}

function bandChipHtml(band) {
    const label = escapeHtml(BAND_LABELS[band] ?? band ?? '—');
    const tone = bandTone(band);
    return `<span class="care-check-band is-${tone}">${label}</span>`;
}

function bandTone(band) {
    if (['high', 'severe', 'poor'].includes(band)) {
        return 'alert';
    }
    if (['moderate', 'mild', 'fair'].includes(band)) {
        return 'watch';
    }
    return 'calm';
}

export function viewCareCheck(id) {
    axios.get(`/admin/care-checks/${id}`)
        .then(({ data }) => {
            const row = data.data ?? resultCache.get(String(id));
            if (!row) {
                showErrorToast('Check not found.');
                return;
            }
            Swal.fire(buildSwalOptions({
                confirmOnly: false,
                title: TYPE_LABELS[row.check_type] ?? 'Care check',
                subtitle: `${row.user?.name ?? 'Unknown user'} · ${formatDateTime(row.created_at)}`,
                html: buildResultModalHtml(row),
                confirmButtonText: 'Close',
                showCancelButton: false,
                didOpen: (popup) => animateCareCheckResult(popup, row),
            }, { size: 'lg' }));
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Unable to load this check.');
        });
}

function buildResultModalHtml(row) {
    const high = row.display_tier === 'high';
    const isSleep = row.check_type === 'sleep';
    const tierLabel = escapeHtml(row.tier_label || TIER_LABELS[row.display_tier] || row.display_tier);
    const levelLabel = escapeHtml(row.level_label || 'Result');
    const answers = Array.isArray(row.answers) ? row.answers : [];
    const percent = Math.max(0, Math.min(100, Number(row.percent) || 0));
    const answerHtml = answers.map((answer) => `
        <article class="care-check-answer">
            <p class="care-check-answer-q">${escapeHtml(answer.question || `Question ${answer.question_id}`)}</p>
            <p class="care-check-answer-a">${escapeHtml(answer.label || String(answer.value ?? '—'))}</p>
        </article>
    `).join('') || '<p class="text-sm text-slate-500">No answer details stored.</p>';

    const visual = isSleep
        ? `<div class="care-check-sleep-icon ${high ? 'is-high' : 'is-low'}" aria-hidden="true">
                <i class="fas fa-moon"></i>
            </div>`
        : `<div class="care-check-gauge" data-care-check-gauge data-percent="${percent}" aria-hidden="true">
                <svg viewBox="0 0 220 124" class="care-check-gauge-svg">
                    <path class="care-check-arc care-check-arc-low" d="M 22 112 A 88 88 0 0 1 78 32"></path>
                    <path class="care-check-arc care-check-arc-mid" d="M 78 32 A 88 88 0 0 1 142 32"></path>
                    <path class="care-check-arc care-check-arc-high" d="M 142 32 A 88 88 0 0 1 198 112"></path>
                    <g class="care-check-needle">
                        <line x1="110" y1="112" x2="110" y2="38"></line>
                        <circle cx="110" cy="112" r="7"></circle>
                    </g>
                </svg>
            </div>`;

    return `
        <div class="admin-swal-form care-check-result ${high ? 'is-high' : 'is-low'}">
            <p class="admin-swal-description" hidden>${escapeHtml(row.user?.name ?? '')} · ${escapeHtml(formatDateTime(row.created_at))}</p>
            <div class="care-check-hero">
                ${visual}
                <p class="care-check-level">${levelLabel}</p>
                <p class="care-check-tier">${tierLabel}</p>
                <p class="care-check-meta">${bandChipHtml(row.band)} ${typeChipHtml(row.check_type)}</p>
                <div class="care-check-score">
                    <div class="care-check-score-track" aria-hidden="true">
                        <span class="${high ? 'is-high' : ''}" style="width:${percent}%"></span>
                    </div>
                    <p>${row.raw_score}/${row.max_score} · ${percent}%</p>
                </div>
            </div>
            <div class="care-check-answers">${answerHtml}</div>
            <p class="care-check-disclaimer">
                <i class="fas fa-leaf" aria-hidden="true"></i>
                This test is for self-reflection only and does not replace professional evaluation.
            </p>
        </div>
    `;
}

function animateCareCheckResult(popup, row) {
    const gauge = popup.querySelector('[data-care-check-gauge]');
    if (!gauge) {
        return;
    }
    const percent = Math.max(0, Math.min(100, Number(row.percent) || (row.display_tier === 'high' ? 82 : 22)));
    const deg = -90 + ((percent / 100) * 180);
    requestAnimationFrame(() => {
        gauge.style.setProperty('--needle-deg', `${deg}deg`);
        gauge.classList.add('is-ready');
    });
}

export function exportCareChecksWithRange() {
    const pageParams = getCareChecksQueryParams();
    const initialRange = pageParams.range || '7';
    const showCustomRange = initialRange === 'custom';

    Swal.fire(buildSwalOptions({
        title: 'Export care checks',
        subtitle: 'Download Stress, Anxiety, and Sleep Quality completions.',
        confirmButtonText: 'Download',
        customClass: {
            popup: 'admin-swal-popup-export',
        },
        html: buildExportDialogHtml({
            formatId: 'care-checks-export-file-format',
            formatValue: 'csv',
            formatOptions: [
                { value: 'csv', label: 'CSV', icon: 'fas fa-file-csv' },
            ],
            fieldsHtml: buildSwalFieldsHtml([
                {
                    id: 'care-checks-export-check-type',
                    label: 'Check type',
                    type: 'select',
                    value: currentTypeFilter || '',
                    options: [
                        { value: '', label: 'All checks' },
                        { value: 'stress', label: 'Stress Check' },
                        { value: 'anxiety', label: 'Anxiety Check' },
                        { value: 'sleep', label: 'Sleep Quality' },
                    ],
                },
                {
                    id: 'care-checks-export-tier',
                    label: 'Result tier',
                    type: 'select',
                    value: currentTierFilter || '',
                    options: [
                        { value: '', label: 'All results' },
                        { value: 'low', label: 'Lower tier' },
                        { value: 'high', label: 'Higher tier' },
                    ],
                },
                {
                    id: 'care-checks-export-range',
                    label: 'Date range',
                    type: 'select',
                    value: initialRange,
                    span: 2,
                    options: RANGE_OPTIONS,
                },
            ], 'admin-export-fields-grid'),
            showCustomRange,
            customRangeHtml: buildSwalFieldsHtml([
                {
                    id: 'care-checks-export-from',
                    label: 'From',
                    type: 'date',
                    value: pageParams.from || '',
                },
                {
                    id: 'care-checks-export-to',
                    label: 'To',
                    type: 'date',
                    value: pageParams.to || '',
                },
            ], 'admin-export-custom-grid'),
        }),
        didOpen: (popup) => {
            bindExportFormatPills(popup, 'care-checks-export-file-format');
            syncExportCustomRangePanel(popup, 'care-checks-export-range');
            popup.querySelector('#care-checks-export-range')?.addEventListener('change', () => {
                syncExportCustomRangePanel(popup, 'care-checks-export-range');
            });
        },
        preConfirm: () => {
            const checkType = document.getElementById('care-checks-export-check-type')?.value || '';
            const displayTier = document.getElementById('care-checks-export-tier')?.value || '';
            const range = document.getElementById('care-checks-export-range')?.value || '7';
            const from = document.getElementById('care-checks-export-from')?.value || '';
            const to = document.getElementById('care-checks-export-to')?.value || '';

            if (!range) {
                Swal.showValidationMessage('Choose a date range.');
                return false;
            }

            if (range === 'custom') {
                if (!from || !to) {
                    Swal.showValidationMessage('Enter both start and end dates for a custom range.');
                    return false;
                }
                if (from > to) {
                    Swal.showValidationMessage('Start date must be on or before the end date.');
                    return false;
                }
            }

            return { checkType, displayTier, range, from, to };
        },
    }, { size: 'md' })).then(async (result) => {
        if (!result.isConfirmed || !result.value) {
            return;
        }

        const { checkType, displayTier, range, from, to } = result.value;
        const exportButton = document.querySelector('[data-admin-action="export-care-checks"]');
        exportButton?.setAttribute('disabled', 'true');

        try {
            const params = {
                range,
                format: 'csv',
                check_type: checkType || undefined,
                display_tier: displayTier || undefined,
            };
            if (range === 'custom') {
                params.from = from;
                params.to = to;
            }

            const response = await axios.get('/admin/care-checks/export', {
                params,
                responseType: 'blob',
            });

            const payload = response.data;
            const contentType = String(response.headers?.['content-type'] ?? '');
            if (contentType.includes('application/json') || (payload instanceof Blob && payload.type.includes('application/json'))) {
                const text = payload instanceof Blob ? await payload.text() : String(payload);
                let message = 'Unable to export care checks.';
                try {
                    message = JSON.parse(text)?.message ?? message;
                } catch (_) {
                    // Keep default message.
                }
                showErrorToast(message, 'Export failed');
                return;
            }

            const disposition = String(response.headers?.['content-disposition'] ?? '');
            const matchedName = disposition.match(/filename=\"?([^\";]+)\"?/i);
            const filename = matchedName?.[1]
                || `dape-ma-care-checks-${range}-${new Date().toISOString().slice(0, 10)}.csv`;
            const blob = payload instanceof Blob
                ? payload
                : new Blob([payload], { type: 'text/csv;charset=utf-8;' });
            const downloadUrl = window.URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = downloadUrl;
            anchor.download = filename;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            window.URL.revokeObjectURL(downloadUrl);
            showSuccessToast('CSV download started.', 'Export ready');
        } catch (error) {
            const response = error?.response;
            let message = response?.data?.message ?? 'Unable to export care checks.';
            if (response?.data instanceof Blob) {
                try {
                    message = JSON.parse(await response.data.text())?.message ?? message;
                } catch (_) {
                    // Keep default message.
                }
            }
            showErrorToast(message, 'Export failed');
        } finally {
            exportButton?.removeAttribute('disabled');
        }
    });
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }
    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

window.CareChecks = {
    view: viewCareCheck,
    reload: loadCareChecks,
    exportCsv: exportCareChecksWithRange,
    exportCsvWithRange: exportCareChecksWithRange,
};
