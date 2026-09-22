import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from '../shared/datatables';
import { buildSwalOptions } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let eventsTable;
let audienceFilter = '';

const AUDIENCE_LABELS = {
    youth: 'Youth',
    parents: 'Parents',
    community: 'Community',
};

export function initHopeEventsModule() {
    const tableEl = document.getElementById('hope-events-table');
    if (!tableEl) return;

    document.querySelector('[data-admin-action="create-hope-event"]')
        ?.addEventListener('click', () => showForm(null));

    document.getElementById('hope-events-audience-filter')
        ?.addEventListener('change', (event) => {
            audienceFilter = event.target.value || '';
            loadRows();
        });

    initializeTable(tableEl).then(() => {
        bindAdminActionTooltipSuppression(tableEl);
        loadRows();
    });
}

async function initializeTable(tableElement) {
    eventsTable = await createAdminDataTable(
        tableElement,
        getAdminDataTableOptions({
            columns: [
                { title: 'Order', width: '64px' },
                { title: 'Event' },
                { title: 'Audience' },
                { title: 'Dates' },
                { title: 'Slots' },
                { title: 'Status' },
                { title: 'Actions', orderable: false, searchable: false },
            ],
            searchPlaceholder: 'Search events...',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ events',
            pageLength: 15,
            drawCallback: () => bindRowActions(),
        }),
    );
}

function loadRows() {
    if (!eventsTable) return;

    axios.get('/admin/hope-events', {
        params: {
            per_page: 500,
            audience: audienceFilter || undefined,
        },
    })
        .then(({ data }) => {
            const rows = data.data?.data ?? [];
            eventsTable.clear();
            rows.forEach((row) => eventsTable.row.add(buildRow(row)));
            eventsTable.draw();
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Failed to load Hope Events.');
        });
}

function audienceBadge(audience) {
    const key = audience || 'all';
    const label = AUDIENCE_LABELS[key] ?? (audience ? String(audience) : 'All');
    const className = AUDIENCE_LABELS[key]
        ? `hope-audience-badge hope-audience-badge-${key}`
        : 'hope-audience-badge hope-audience-badge-all';
    return `<span class="${className}">${escapeHtml(label)}</span>`;
}

function statusBadge(row) {
    if (!row.is_active) {
        return '<span class="admin-status-badge rehab-status-badge rehab-status-badge-inactive">Inactive</span>';
    }
    const status = row.status || 'upcoming';
    const label = status.charAt(0).toUpperCase() + status.slice(1);
    return `<span class="hope-event-status-badge hope-event-status-badge-${escapeAttr(status)}">${escapeHtml(label)}</span>`;
}

function mediaThumb(url, icon) {
    if (url) {
        return `<img class="admin-table-media-thumb" src="${escapeAttr(url)}" alt="" loading="lazy">`;
    }
    return `<span class="admin-table-media-placeholder" aria-hidden="true"><i class="fas ${icon}"></i></span>`;
}

function buildRow(row) {
    const location = row.is_online
        ? (row.online_label || 'Online')
        : (row.venue || '—');
    const dates = [row.start_date, row.end_date].filter(Boolean).join(' → ') || '—';
    const actions = buildAdminActionButtons([
        {
            tooltip: 'Edit event',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
            attrs: `data-hope-event-edit="${row.id}"`,
        },
        {
            tooltip: 'Delete event',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `data-hope-event-delete="${row.id}"`,
        },
    ]);

    return [
        row.sort_order ?? 0,
        `<div class="admin-table-media-cell">
            ${mediaThumb(row.cover_url, 'fa-calendar-days')}
            <div class="admin-table-media-copy">
                <div class="font-semibold text-slate-800">${escapeHtml(row.title)}</div>
                <div class="text-xs text-slate-500">${escapeHtml(location)}</div>
            </div>
         </div>`,
        audienceBadge(row.audience),
        escapeHtml(dates),
        row.slots ?? '—',
        statusBadge(row),
        `<div class="admin-table-actions-nowrap">${actions}</div>`,
    ];
}

function bindRowActions() {
    document.querySelectorAll('[data-hope-event-edit]').forEach((button) => {
        if (button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-hope-event-edit');
            axios.get(`/admin/hope-events/${id}`)
                .then(({ data }) => showForm(data.data))
                .catch(() => showErrorToast('Failed to load event.'));
        });
    });

    document.querySelectorAll('[data-hope-event-delete]').forEach((button) => {
        if (button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-hope-event-delete');
            Swal.fire(buildSwalOptions({
                icon: 'warning',
                title: 'Delete this event?',
                html: '<p class="admin-swal-description">It will no longer appear in the mobile Hope Events list.</p>',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
            }, { danger: true, size: 'sm' })).then(({ isConfirmed }) => {
                if (!isConfirmed) return;
                axios.delete(`/admin/hope-events/${id}`)
                    .then(({ data }) => {
                        showSuccessToast(data.message ?? 'Event deleted.');
                        loadRows();
                    })
                    .catch(({ response }) => {
                        showErrorToast(response?.data?.message ?? 'Delete failed.');
                    });
            });
        });
    });
}

function buildFormHtml(row) {
    const forYou = Array.isArray(row?.for_you_items) ? row.for_you_items.join('\n') : '';
    const highlights = Array.isArray(row?.highlights)
        ? row.highlights.map((h) => (typeof h === 'string' ? h : h.label)).filter(Boolean).join('\n')
        : '';
    const coverPreview = row?.cover_url
        ? `<img class="admin-swal-media-preview" src="${escapeAttr(row.cover_url)}" alt="Current cover">`
        : '';

    return `
        <div class="admin-swal-form">
            <div class="admin-swal-section">
                <p class="admin-swal-section-title">Basics</p>
                <div class="admin-swal-fields">
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="he-title">Title *</label>
                        <input id="he-title" class="admin-swal-input" type="text" placeholder="Event title" value="${escapeAttr(row?.title ?? '')}">
                    </div>
                    <div class="admin-swal-fields-grid-2">
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="he-audience">Audience</label>
                            <select id="he-audience" class="admin-swal-select">
                                <option value="">All audiences</option>
                                <option value="youth" ${row?.audience === 'youth' ? 'selected' : ''}>Youth</option>
                                <option value="parents" ${row?.audience === 'parents' ? 'selected' : ''}>Parents</option>
                                <option value="community" ${row?.audience === 'community' ? 'selected' : ''}>Community</option>
                            </select>
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="he-status">Lifecycle status</label>
                            <select id="he-status" class="admin-swal-select">
                                <option value="upcoming" ${!row?.status || row?.status === 'upcoming' ? 'selected' : ''}>Upcoming</option>
                                <option value="ongoing" ${row?.status === 'ongoing' ? 'selected' : ''}>Ongoing</option>
                                <option value="ended" ${row?.status === 'ended' ? 'selected' : ''}>Ended</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-swal-fields-grid-2">
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="he-sort">Sort order</label>
                            <input id="he-sort" class="admin-swal-input" type="number" min="0" value="${row?.sort_order ?? 0}">
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="he-active">Visibility *</label>
                            <select id="he-active" class="admin-swal-select">
                                <option value="1" ${row?.is_active !== false ? 'selected' : ''}>Active in app</option>
                                <option value="0" ${row?.is_active === false ? 'selected' : ''}>Hidden</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-swal-section">
                <p class="admin-swal-section-title">Schedule & venue</p>
                <div class="admin-swal-fields">
                    <div class="admin-swal-fields-grid-2">
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="he-start">Start date</label>
                            <input id="he-start" class="admin-swal-input" type="date" value="${escapeAttr(row?.start_date ?? '')}">
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="he-end">End date</label>
                            <input id="he-end" class="admin-swal-input" type="date" value="${escapeAttr(row?.end_date ?? '')}">
                        </div>
                    </div>
                    <div class="admin-swal-fields-grid-2">
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="he-venue">Venue</label>
                            <input id="he-venue" class="admin-swal-input" type="text" placeholder="Venue or city" value="${escapeAttr(row?.venue ?? '')}">
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="he-slots">Slots</label>
                            <input id="he-slots" class="admin-swal-input" type="number" min="0" placeholder="Optional capacity" value="${row?.slots ?? ''}">
                        </div>
                    </div>
                    <div class="admin-swal-fields-grid-2">
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="he-online">Event format</label>
                            <select id="he-online" class="admin-swal-select">
                                <option value="0" ${!row?.is_online ? 'selected' : ''}>In person</option>
                                <option value="1" ${row?.is_online ? 'selected' : ''}>Online / hybrid</option>
                            </select>
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="he-online-label">Online label</label>
                            <input id="he-online-label" class="admin-swal-input" type="text" placeholder="Zoom / Facebook LIVE" value="${escapeAttr(row?.online_label ?? '')}">
                        </div>
                    </div>
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="he-reg">Registration URL</label>
                        <input id="he-reg" class="admin-swal-input" type="url" placeholder="https://" value="${escapeAttr(row?.registration_url ?? '')}">
                    </div>
                </div>
            </div>

            <div class="admin-swal-section">
                <p class="admin-swal-section-title">Content</p>
                <div class="admin-swal-fields">
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="he-about">About</label>
                        <textarea id="he-about" class="admin-swal-textarea" rows="3" placeholder="Short event overview">${escapeHtml(row?.about_text ?? '')}</textarea>
                    </div>
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="he-for-you">For you if</label>
                        <p class="admin-swal-hint">One bullet per line.</p>
                        <textarea id="he-for-you" class="admin-swal-textarea" rows="3" placeholder="You want to join a youth convention">${escapeHtml(forYou)}</textarea>
                    </div>
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="he-who">Who can join</label>
                        <textarea id="he-who" class="admin-swal-textarea" rows="2" placeholder="Eligibility details">${escapeHtml(row?.who_can_join ?? '')}</textarea>
                    </div>
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="he-highlights">Highlights</label>
                        <p class="admin-swal-hint">One highlight per line.</p>
                        <textarea id="he-highlights" class="admin-swal-textarea" rows="3" placeholder="Keynote speakers">${escapeHtml(highlights)}</textarea>
                    </div>
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="he-details">Details tab</label>
                        <textarea id="he-details" class="admin-swal-textarea" rows="2" placeholder="Additional schedule or logistics">${escapeHtml(row?.details_text ?? '')}</textarea>
                    </div>
                </div>
            </div>

            <div class="admin-swal-section">
                <p class="admin-swal-section-title">Speakers</p>
                <p class="admin-swal-hint mb-2">Shown on the Speakers tab in the mobile event detail screen.</p>
                <div id="he-speakers-list" class="admin-swal-repeatable"></div>
                <button type="button" class="admin-swal-repeatable-add mt-3 w-full" data-he-add-speaker>
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Add speaker
                </button>
            </div>

            <div class="admin-swal-section">
                <p class="admin-swal-section-title">FAQs</p>
                <p class="admin-swal-hint mb-2">Shown on the FAQs tab in the mobile event detail screen.</p>
                <div id="he-faqs-list" class="admin-swal-repeatable"></div>
                <button type="button" class="admin-swal-repeatable-add mt-3 w-full" data-he-add-faq>
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Add FAQ
                </button>
            </div>

            <div class="admin-swal-section">
                <p class="admin-swal-section-title">Cover image</p>
                <div class="admin-swal-field">
                    <div class="admin-swal-media-row">
                        ${coverPreview}
                        <div class="min-w-0 flex-1">
                            <label class="admin-swal-label" for="he-cover">Upload cover</label>
                            <input id="he-cover" class="admin-swal-input" type="file" accept="image/*">
                            <p class="admin-swal-hint mt-1">Recommended landscape image, JPG or PNG.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function speakerRowHtml(speaker = {}) {
    return `
        <div class="admin-swal-repeatable-row" data-he-speaker-row>
            <div class="admin-swal-repeatable-row-grid admin-swal-repeatable-row-grid-speaker">
                <div class="admin-swal-field">
                    <label class="admin-swal-label">Name *</label>
                    <input class="admin-swal-input" data-he-speaker-name type="text" placeholder="Speaker name" value="${escapeAttr(speaker.name ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label">Role</label>
                    <input class="admin-swal-input" data-he-speaker-role type="text" placeholder="Keynote, panelist..." value="${escapeAttr(speaker.role ?? '')}">
                </div>
                <div class="admin-swal-repeatable-actions">
                    <button type="button" class="admin-swal-repeatable-remove" data-he-remove-row aria-label="Remove speaker">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
}

function faqRowHtml(faq = {}) {
    return `
        <div class="admin-swal-repeatable-row" data-he-faq-row>
            <div class="admin-swal-repeatable-row-grid admin-swal-repeatable-row-grid-faq">
                <div class="admin-swal-fields">
                    <div class="admin-swal-field">
                        <label class="admin-swal-label">Question *</label>
                        <input class="admin-swal-input" data-he-faq-question type="text" placeholder="Is registration free?" value="${escapeAttr(faq.question ?? '')}">
                    </div>
                    <div class="admin-swal-field">
                        <label class="admin-swal-label">Answer *</label>
                        <textarea class="admin-swal-textarea" data-he-faq-answer rows="2" placeholder="Yes, selected participants attend free of charge.">${escapeHtml(faq.answer ?? '')}</textarea>
                    </div>
                </div>
                <div class="admin-swal-repeatable-actions">
                    <button type="button" class="admin-swal-repeatable-remove" data-he-remove-row aria-label="Remove FAQ">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
}

function mountRepeatableLists(row) {
    const speakersList = document.getElementById('he-speakers-list');
    const faqsList = document.getElementById('he-faqs-list');
    if (!speakersList || !faqsList) return;

    const speakers = Array.isArray(row?.speakers) && row.speakers.length
        ? row.speakers
        : [{ name: '', role: '' }];
    const faqs = Array.isArray(row?.faqs) && row.faqs.length
        ? row.faqs
        : [{ question: '', answer: '' }];

    speakersList.innerHTML = speakers.map((speaker) => speakerRowHtml(speaker)).join('');
    faqsList.innerHTML = faqs.map((faq) => faqRowHtml(faq)).join('');

    document.querySelector('[data-he-add-speaker]')?.addEventListener('click', () => {
        speakersList.insertAdjacentHTML('beforeend', speakerRowHtml());
    });

    document.querySelector('[data-he-add-faq]')?.addEventListener('click', () => {
        faqsList.insertAdjacentHTML('beforeend', faqRowHtml());
    });

    const popup = speakersList.closest('.swal2-popup') || document;
    popup.addEventListener('click', (event) => {
        const removeBtn = event.target.closest('[data-he-remove-row]');
        if (!removeBtn) return;
        const rowEl = removeBtn.closest('[data-he-speaker-row], [data-he-faq-row]');
        if (!rowEl) return;

        const list = rowEl.parentElement;
        rowEl.remove();
        if (list && list.children.length === 0) {
            if (list.id === 'he-speakers-list') {
                list.insertAdjacentHTML('beforeend', speakerRowHtml());
            } else if (list.id === 'he-faqs-list') {
                list.insertAdjacentHTML('beforeend', faqRowHtml());
            }
        }
    });
}

function collectSpeakers() {
    return [...document.querySelectorAll('[data-he-speaker-row]')]
        .map((rowEl) => ({
            name: rowEl.querySelector('[data-he-speaker-name]')?.value.trim() || '',
            role: rowEl.querySelector('[data-he-speaker-role]')?.value.trim() || '',
        }))
        .filter((speaker) => speaker.name);
}

function collectFaqs() {
    return [...document.querySelectorAll('[data-he-faq-row]')]
        .map((rowEl) => ({
            question: rowEl.querySelector('[data-he-faq-question]')?.value.trim() || '',
            answer: rowEl.querySelector('[data-he-faq-answer]')?.value.trim() || '',
        }))
        .filter((faq) => faq.question && faq.answer);
}

function showForm(row) {
    const isEdit = Boolean(row);

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit Event' : 'Add Event',
        subtitle: 'Configure schedule, speakers, FAQs, and content for the DAPE Hope Events screen.',
        html: buildFormHtml(row),
        confirmButtonText: isEdit ? 'Save Changes' : 'Create Event',
        cancelButtonText: 'Cancel',
        width: '46rem',
        didOpen: () => mountRepeatableLists(row),
        preConfirm: () => {
            const title = document.getElementById('he-title')?.value.trim();
            if (!title) {
                Swal.showValidationMessage('Title is required.');
                return false;
            }

            const startDate = document.getElementById('he-start')?.value || '';
            const endDate = document.getElementById('he-end')?.value || '';
            if (startDate && endDate && endDate < startDate) {
                Swal.showValidationMessage('End date must be on or after start date.');
                return false;
            }

            const speakers = collectSpeakers();
            const incompleteSpeaker = [...document.querySelectorAll('[data-he-speaker-row]')]
                .some((rowEl) => {
                    const name = rowEl.querySelector('[data-he-speaker-name]')?.value.trim() || '';
                    const role = rowEl.querySelector('[data-he-speaker-role]')?.value.trim() || '';
                    return !name && role;
                });
            if (incompleteSpeaker) {
                Swal.showValidationMessage('Speaker name is required when a role is set.');
                return false;
            }

            const incompleteFaq = [...document.querySelectorAll('[data-he-faq-row]')]
                .some((rowEl) => {
                    const question = rowEl.querySelector('[data-he-faq-question]')?.value.trim() || '';
                    const answer = rowEl.querySelector('[data-he-faq-answer]')?.value.trim() || '';
                    return (question && !answer) || (!question && answer);
                });
            if (incompleteFaq) {
                Swal.showValidationMessage('Each FAQ needs both a question and an answer.');
                return false;
            }

            const forYouItems = splitLines(document.getElementById('he-for-you')?.value);
            const highlightItems = splitLines(document.getElementById('he-highlights')?.value)
                .map((label) => ({ label }));

            return {
                title,
                audience: document.getElementById('he-audience')?.value || '',
                status: document.getElementById('he-status')?.value || 'upcoming',
                start_date: startDate,
                end_date: endDate,
                venue: document.getElementById('he-venue')?.value.trim() || '',
                is_online: document.getElementById('he-online')?.value === '1' ? '1' : '0',
                online_label: document.getElementById('he-online-label')?.value.trim() || '',
                slots: document.getElementById('he-slots')?.value || '',
                sort_order: document.getElementById('he-sort')?.value || 0,
                registration_url: document.getElementById('he-reg')?.value.trim() || '',
                about_text: document.getElementById('he-about')?.value.trim() || '',
                for_you_items: JSON.stringify(forYouItems),
                who_can_join: document.getElementById('he-who')?.value.trim() || '',
                highlights: JSON.stringify(highlightItems),
                details_text: document.getElementById('he-details')?.value.trim() || '',
                speakers: JSON.stringify(speakers),
                faqs: JSON.stringify(collectFaqs()),
                is_active: document.getElementById('he-active')?.value === '1' ? '1' : '0',
                cover: document.getElementById('he-cover')?.files?.[0] || null,
            };
        },
    })).then(({ isConfirmed, value }) => {
        if (!isConfirmed || !value) return;

        const form = new FormData();
        Object.entries(value).forEach(([key, val]) => {
            if (key === 'cover') {
                if (val) form.append('cover', val);
                return;
            }
            if (val === '' && (key === 'registration_url' || key === 'audience' || key === 'slots')) {
                return;
            }
            form.append(key, val);
        });

        const request = isEdit
            ? axios.post(`/admin/hope-events/${row.id}`, form)
            : axios.post('/admin/hope-events', form);

        request
            .then(({ data }) => {
                showSuccessToast(data.message ?? (isEdit ? 'Updated.' : 'Created.'));
                loadRows();
            })
            .catch(({ response }) => {
                const msg = response?.data?.message
                    ?? Object.values(response?.data?.errors ?? {})?.[0]?.[0]
                    ?? 'Save failed.';
                showErrorToast(msg);
            });
    });
}

function splitLines(value) {
    return String(value ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function escapeAttr(value) {
    return escapeHtml(value).replaceAll('`', '&#96;');
}
