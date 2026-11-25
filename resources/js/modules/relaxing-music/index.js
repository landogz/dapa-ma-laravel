import axios from 'axios';
import Swal from 'sweetalert2';
import {
    buildSwalForm,
    buildSwalOptions,
    confirmWithSwal,
} from '../shared/swal-forms';
import { buildAdminActionButtons } from '../shared/table-actions';
import { bindAdminFilterTabs, enhanceAdminFilterTabs } from '../shared/filter-tabs';
import {
    attachWorkflowWindowHandlers,
    buildWorkflowTableActions,
    canCreateWorkflowDraft,
    ensureWorkflowStatusFilter,
    filterByWorkflowStatus,
    applyWorkflowStatusCounts,
    applyQueueAwareFilterCounts,
    guardWorkflowCreateAction,
    isPublisherReviewOnly,
    isPublisherRole,
    itemForEditorForm,
    openItemFromQuery,
    publisherReviewSwalButtons,
    renderReviewNotesBanner,
    resolvePublisherReviewSwalResult,
    syncWorkflowCreateButton,
    workflowStatusBadge,
} from '../shared/content-workflow';
import { showErrorToast, showSuccessToast } from '../shared/toast';

const BASE_PATH = '/admin/relaxing-music-tracks';

let tracks = [];
let activeStatus = '';
let workflowStatusFilter = '';
let searchQuery = '';
let bound = false;
let previewAudio = null;
let previewTrackId = null;
let previewPlaying = false;

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function readField(id) {
    const el = document.getElementById(id);
    if (!el) {
        return '';
    }
    if (el.type === 'file') {
        return el.files?.[0] ?? null;
    }
    return String(el.value ?? '').trim();
}

function readAudioFiles(id) {
    const el = document.getElementById(id);
    if (!el || el.type !== 'file') {
        return [];
    }

    return Array.from(el.files || []).filter((file) => {
        const type = String(file.type || '').toLowerCase();
        const name = String(file.name || '').toLowerCase();
        return type.startsWith('audio/')
            || /\.(mp3|m4a|aac|wav|ogg)$/i.test(name);
    });
}

function formatDuration(seconds) {
    const total = Number(seconds);
    if (!Number.isFinite(total) || total <= 0) {
        return '—';
    }
    const mins = Math.floor(total / 60);
    const secs = Math.floor(total % 60);
    return `${mins}:${String(secs).padStart(2, '0')}`;
}

function stopPreview() {
    if (previewAudio) {
        previewAudio.pause();
        previewAudio.src = '';
        previewAudio = null;
    }
    previewTrackId = null;
    previewPlaying = false;
    syncPlayButtons();
}

function syncPlayButtons() {
    document.querySelectorAll('[data-track-play]').forEach((button) => {
        const id = Number(button.getAttribute('data-track-play'));
        const isCurrent = previewTrackId !== null && id === previewTrackId;
        const isPlaying = isCurrent && previewPlaying;
        const icon = button.querySelector('i');
        if (icon) {
            icon.className = isPlaying ? 'fas fa-pause' : 'fas fa-play';
        }
        const title = tracks.find((item) => Number(item.id) === id)?.title || 'track';
        button.setAttribute(
            'aria-label',
            isPlaying ? `Pause ${title}` : `Preview ${title}`,
        );
        button.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
        button.classList.toggle('is-playing', isPlaying);
        button.classList.toggle('is-current', isCurrent);
        button.classList.toggle('ring-2', isCurrent);
        button.classList.toggle('ring-[#FBD116]', isCurrent);

        const card = button.closest('[data-relaxing-music-card]');
        const nowPlaying = card?.querySelector?.('[data-now-playing]');
        if (nowPlaying) {
            nowPlaying.hidden = !isPlaying;
        }
    });

    const formPlay = document.getElementById('relaxing-music-form-play');
    if (formPlay) {
        const isPlaying = previewTrackId === -1 && previewPlaying;
        const icon = formPlay.querySelector('i');
        if (icon) {
            icon.className = isPlaying ? 'fas fa-pause' : 'fas fa-play';
        }
        formPlay.setAttribute('aria-label', isPlaying ? 'Pause track' : 'Play track for review');
        formPlay.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
        formPlay.classList.toggle('is-playing', isPlaying);
        formPlay.classList.toggle('ring-2', isPlaying);
        formPlay.classList.toggle('ring-[#FBD116]', isPlaying);
    }
}

function statusBadge(track) {
    const active = track.is_active !== false;
    return `<span class="admin-status-badge rehab-status-badge ${active ? 'rehab-status-badge-active' : 'rehab-status-badge-inactive'}">${active ? 'Active' : 'Inactive'}</span>`;
}

export function initRelaxingMusicModule() {
    const root = document.querySelector('.relaxing-music-page');
    if (!root) {
        return;
    }

    if (!bound) {
        bound = true;
        root.querySelector('[data-relaxing-music-action="add-track"]')
            ?.addEventListener('click', () => guardWorkflowCreateAction(() => showTrackForm(null)));
        root.querySelector('#relaxing-music-search')
            ?.addEventListener('input', (event) => {
                searchQuery = String(event.target.value || '').trim().toLowerCase();
                renderGallery();
            });
        root.addEventListener('click', onGalleryClick);
        window.addEventListener('pagehide', stopPreview);

        const statusTabs = document.getElementById('relaxing-music-status-tabs');
        if (statusTabs) {
            enhanceAdminFilterTabs(statusTabs);
            bindAdminFilterTabs(statusTabs, (value) => {
                activeStatus = value || '';
                renderGallery();
            });
        }

        ensureWorkflowStatusFilter({
            tabsRoot: root.querySelector('[data-workflow-status-tabs]'),
            selectMount: root.querySelector('.admin-filter-main') || root,
            onChange: (value) => {
                workflowStatusFilter = value;
                renderGallery();
                updateStatusTabCounts();
            },
        });

        syncWorkflowCreateButton([
            '[data-relaxing-music-action="add-track"]',
            '[data-relaxing-music-action="add-track-tile"]',
        ]);

        attachWorkflowWindowHandlers('RelaxingMusic', BASE_PATH, () => loadTracks());
        window.RelaxingMusic = {
            ...(window.RelaxingMusic || {}),
            edit: editTrack,
            reload: loadTracks,
        };

        openItemFromQuery('item', (id) => editTrack(id));
    }

    loadTracks();
}

async function loadTracks() {
    const gallery = document.getElementById('relaxing-music-gallery');
    if (gallery) {
        gallery.setAttribute('aria-busy', 'true');
        gallery.innerHTML = `
            <div class="sticker-library-empty" role="status">
                <i class="fas fa-spinner fa-spin text-2xl text-[#055498]" aria-hidden="true"></i>
                <p>Loading relaxing music…</p>
            </div>
        `;
    }

    try {
        const { data } = await axios.get(BASE_PATH);
        tracks = Array.isArray(data?.data) ? data.data : [];
        applyWorkflowStatusCounts(null, tracks);
        updateStatusTabCounts();
        renderGallery();
        if (gallery) {
            gallery.setAttribute('aria-busy', 'false');
        }
        syncWorkflowCreateButton([
            '[data-relaxing-music-action="add-track"]',
            '[data-relaxing-music-action="add-track-tile"]',
        ]);
    } catch (error) {
        showErrorToast(error?.response?.data?.message || 'Failed to load relaxing music.');
        if (gallery) {
            gallery.setAttribute('aria-busy', 'false');
            gallery.innerHTML = `
                <div class="sticker-library-empty" role="alert">
                    <i class="fas fa-triangle-exclamation text-2xl text-[#CE2028]" aria-hidden="true"></i>
                    <p>Could not load relaxing music.</p>
                </div>
            `;
        }
    }
}

function updateStatusTabCounts() {
    const queue = filterByWorkflowStatus(tracks, workflowStatusFilter);
    applyQueueAwareFilterCounts(
        document.getElementById('relaxing-music-status-tabs'),
        queue,
        (row) => (row.is_active === false ? 'inactive' : 'active'),
    );
}

function filteredTracks() {
    return filterByWorkflowStatus(tracks, workflowStatusFilter).filter((track) => {
        if (activeStatus === 'active' && track.is_active === false) {
            return false;
        }
        if (activeStatus === 'inactive' && track.is_active !== false) {
            return false;
        }
        if (!searchQuery) {
            return true;
        }
        const title = String(track.title || '').toLowerCase();
        const titleTl = String(track.title_tl || '').toLowerCase();
        return title.includes(searchQuery) || titleTl.includes(searchQuery);
    });
}

function trackCardActions(track) {
    const actions = buildWorkflowTableActions(track, 'RelaxingMusic', { basePath: BASE_PATH });
    return buildAdminActionButtons(actions, { isMobile: true, nowrap: false });
}

function renderGallery() {
    const gallery = document.getElementById('relaxing-music-gallery');
    const meta = document.getElementById('relaxing-music-meta');
    if (!gallery) {
        return;
    }

    const rows = filteredTracks();
    if (meta) {
        meta.textContent = `${rows.length} track${rows.length === 1 ? '' : 's'}`;
    }

    const canAdd = canCreateWorkflowDraft();
    const emptyCopy = isPublisherRole()
        ? 'No tracks are waiting for your review.'
        : 'No tracks yet. Upload MP3 or M4A files for Calm Corner.';

    if (rows.length === 0) {
        gallery.innerHTML = `
            <div class="sticker-library-empty">
                <i class="fas fa-music text-2xl text-[#055498]" aria-hidden="true"></i>
                <p>${escapeHtml(emptyCopy)}</p>
                ${canAdd ? `
                    <button type="button" class="admin-primary-button min-h-11 mt-3" data-relaxing-music-action="add-track-tile">
                        <i class="fas fa-plus mr-2" aria-hidden="true"></i>
                        Add tracks
                    </button>
                ` : ''}
            </div>
        `;
        return;
    }

    gallery.innerHTML = `
        <div class="relaxing-music-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 items-stretch">
            ${rows.map((track) => {
                const id = Number(track.id);
                const isCurrent = previewTrackId !== null && id === previewTrackId;
                const isPlaying = isCurrent && previewPlaying;
                const hasAudio = Boolean(track.audio_url);
                const rawTitle = String(track.title || 'Untitled').trim();
                const rawTitleTl = String(track.title_tl || '').trim();
                const title = escapeHtml(rawTitle);
                const showTitleTl = rawTitleTl !== ''
                    && rawTitleTl.toLowerCase() !== rawTitle.toLowerCase();
                const titleTl = showTitleTl ? escapeHtml(rawTitleTl) : '';
                return `
                <article
                    class="relaxing-music-card h-full rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col gap-3 min-w-0"
                    data-relaxing-music-card="${id}"
                >
                    <div class="relaxing-music-card-body flex items-start gap-3 flex-1 min-w-0">
                        <button
                            type="button"
                            class="relaxing-music-play shrink-0 size-12 rounded-full bg-[#055498] text-white flex items-center justify-center min-h-11 min-w-11 cursor-pointer transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FBD116] disabled:cursor-not-allowed disabled:opacity-50${isCurrent ? ' ring-2 ring-[#FBD116] is-current' : ''}${isPlaying ? ' is-playing' : ''}"
                            data-track-play="${id}"
                            aria-label="${isPlaying ? 'Pause' : 'Preview'} ${title}"
                            aria-pressed="${isPlaying ? 'true' : 'false'}"
                            ${hasAudio ? '' : 'disabled'}
                            title="${hasAudio ? (isPlaying ? 'Pause preview' : 'Play preview') : 'No audio file'}"
                        >
                            <i class="fas ${isPlaying ? 'fa-pause' : 'fa-play'}" aria-hidden="true"></i>
                        </button>
                        <div class="min-w-0 flex-1 flex flex-col gap-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-[#123a60] break-words [overflow-wrap:anywhere] leading-snug">${title}</h3>
                                ${workflowStatusBadge(track.workflow_status, track)}
                                ${statusBadge(track)}
                            </div>
                            <p class="relaxing-music-card-subtitle text-sm text-slate-500 break-words [overflow-wrap:anywhere] ${showTitleTl ? '' : 'invisible'}" aria-hidden="${showTitleTl ? 'false' : 'true'}">${showTitleTl ? titleTl : '&nbsp;'}</p>
                            <p class="text-xs text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-1 mt-auto">
                                <span>${formatDuration(track.duration_seconds)}</span>
                                <span aria-hidden="true">·</span>
                                <span>Order ${Number(track.sort_order ?? 0)}</span>
                                <span
                                    class="relaxing-music-now-playing inline-flex items-center gap-1 font-semibold text-[#123a60]"
                                    data-now-playing
                                    ${isPlaying ? '' : 'hidden'}
                                >
                                    <i class="fas fa-waveform-lines text-[#055498]" aria-hidden="true"></i>
                                    Playing
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="admin-table-mobile-actions relaxing-music-card-actions mt-auto flex flex-wrap gap-2">
                        ${trackCardActions(track)}
                    </div>
                </article>
                `;
            }).join('')}
            ${canAdd ? `
                <button
                    type="button"
                    class="relaxing-music-add-tile h-full min-h-[11rem] rounded-2xl border-2 border-dashed border-slate-300 text-[#055498] font-semibold cursor-pointer transition-colors duration-150 hover:bg-[#055498]/5 hover:border-[#055498]/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#055498] flex items-center justify-center"
                    data-relaxing-music-action="add-track-tile"
                >
                    <i class="fas fa-plus mr-2" aria-hidden="true"></i>
                    Add tracks
                </button>
            ` : ''}
        </div>
    `;
    syncPlayButtons();
}

function onGalleryClick(event) {
    const target = event.target.closest('[data-track-play], [data-relaxing-music-action="add-track-tile"]');
    if (!target) {
        return;
    }

    if (target.matches('[data-track-play]')) {
        playTrack(Number(target.getAttribute('data-track-play')));
        return;
    }

    if (target.matches('[data-relaxing-music-action="add-track-tile"]')) {
        guardWorkflowCreateAction(() => showTrackForm(null));
    }
}

function editTrack(id) {
    const track = tracks.find((item) => Number(item.id) === Number(id));
    if (track) {
        showTrackForm(track);
        return;
    }

    axios.get(`${BASE_PATH}/${id}`)
        .then(({ data }) => showTrackForm(data.data))
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Unable to load this track.');
        });
}

function resolveFormAudioUrl(item) {
    if (!item) {
        return null;
    }
    const pendingUrl = item.pending_revision?.audio_url;
    if (pendingUrl) {
        return String(pendingUrl);
    }
    const pendingPath = item.pending_revision?.audio_path;
    if (pendingPath) {
        const path = String(pendingPath).replace(/^\//, '');
        return path.startsWith('storage/') ? `/${path}` : `/storage/${path}`;
    }
    return item.audio_url ? String(item.audio_url) : null;
}

function renderFormAudioPreview(url, { proposed = false } = {}) {
    if (!url) {
        return `
            <div class="mb-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-600">
                No audio file is available to preview for this review.
            </div>
        `;
    }

    return `
        <div class="mb-3 flex flex-wrap items-center gap-3 rounded-xl border border-[#055498]/25 bg-[#055498]/5 px-3 py-3">
            <button
                type="button"
                id="relaxing-music-form-play"
                class="shrink-0 w-12 h-12 rounded-full bg-[#055498] text-white flex items-center justify-center min-h-11 min-w-11"
                data-form-audio-url="${escapeHtml(url)}"
                aria-label="Play track for review"
            >
                <i class="fas fa-play" aria-hidden="true"></i>
            </button>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-[#123a60]">${proposed ? 'Listen to proposed audio' : 'Listen to track'}</p>
                <p class="text-xs text-slate-500 mt-0.5">Play / pause before you approve or send back for revision.</p>
            </div>
        </div>
    `;
}

function bindFormAudioPreview(popup) {
    const button = popup?.querySelector?.('#relaxing-music-form-play');
    if (!button) {
        return;
    }

    button.addEventListener('click', () => {
        const url = button.getAttribute('data-form-audio-url');
        if (!url) {
            showErrorToast('This track has no audio file.');
            return;
        }

        const formTrackId = -1;
        const existing = tracks.find((item) => Number(item.id) === formTrackId);
        if (!existing) {
            tracks.push({ id: formTrackId, title: 'Review preview', audio_url: url });
        } else {
            existing.audio_url = url;
        }

        playTrack(formTrackId);
    });
}

function playTrack(id) {
    const trackId = Number(id);
    const track = tracks.find((item) => Number(item.id) === trackId);
    if (!track?.audio_url) {
        showErrorToast('This track has no audio file.');
        return;
    }

    if (previewAudio && previewTrackId === trackId) {
        if (previewPlaying) {
            previewAudio.pause();
            previewPlaying = false;
            syncPlayButtons();
            return;
        }

        previewAudio.play().then(() => {
            previewPlaying = true;
            syncPlayButtons();
        }).catch(() => {
            showErrorToast('Could not play this track in the browser.');
            stopPreview();
        });
        return;
    }

    if (previewAudio) {
        previewAudio.pause();
        previewAudio.src = '';
        previewAudio = null;
    }

    previewTrackId = trackId;
    previewPlaying = false;
    previewAudio = new Audio(track.audio_url);
    previewAudio.addEventListener('ended', () => {
        previewPlaying = false;
        previewTrackId = null;
        previewAudio = null;
        syncPlayButtons();
    });
    previewAudio.addEventListener('pause', () => {
        if (previewTrackId === trackId) {
            previewPlaying = false;
            syncPlayButtons();
        }
    });
    previewAudio.addEventListener('play', () => {
        if (previewTrackId === trackId) {
            previewPlaying = true;
            syncPlayButtons();
        }
    });

    syncPlayButtons();
    previewAudio.play().catch(() => {
        showErrorToast('Could not play this track in the browser.');
        stopPreview();
    });
}

function showTrackForm(existingRaw) {
    const existing = existingRaw ? itemForEditorForm(existingRaw) : null;
    const isEdit = Boolean(existing?.id ?? existingRaw?.id);
    const reviewItem = existingRaw?.pending_revision ? existingRaw : null;
    const publisherReviewOnly = isPublisherReviewOnly(existingRaw);
    const isReviewUi = publisherReviewOnly || Boolean(reviewItem);
    const formAudioUrl = resolveFormAudioUrl(existingRaw);
    const proposedAudio = Boolean(existingRaw?.pending_revision?.audio_path
        && existingRaw.pending_revision.audio_path !== existingRaw.audio_path);

    const fields = [
        {
            id: 'relaxing-music-title',
            name: 'title',
            label: isEdit ? 'Title *' : 'Title (single upload only)',
            type: 'text',
            value: existing?.title ?? '',
            required: isEdit,
            attribute: 'title',
            hint: isEdit
                ? undefined
                : 'Optional when uploading one file. Multi-upload uses each file name.',
        },
        {
            id: 'relaxing-music-title-tl',
            name: 'title_tl',
            label: 'Title (Filipino)',
            type: 'text',
            value: existing?.title_tl ?? '',
            attribute: 'title_tl',
        },
    ];

    if (!publisherReviewOnly) {
        fields.push({
            id: 'relaxing-music-audio',
            name: isEdit ? 'audio' : 'audios',
            label: isEdit ? 'Audio file' : 'Audio files *',
            type: 'file',
            accept: 'audio/mpeg,audio/mp4,audio/x-m4a,audio/aac,audio/wav,audio/ogg,.mp3,.m4a,.aac,.wav,.ogg',
            multiple: !isEdit,
            span: 2,
            required: !isEdit,
            hint: isEdit
                ? 'Leave empty to keep the current file. Max 20 MB.'
                : 'Up to 20 files, 20 MB each (MP3, M4A, AAC, WAV, OGG).',
        });
    }

    fields.push(
        {
            id: 'relaxing-music-sort',
            name: 'sort_order',
            label: 'Sort order',
            type: 'number',
            value: String(existing?.sort_order ?? 0),
            attribute: 'sort_order',
        },
        {
            id: 'relaxing-music-active',
            name: 'is_active',
            label: 'Status *',
            type: 'select',
            value: existing?.is_active === false ? '0' : '1',
            options: [
                { value: '1', label: 'Active' },
                { value: '0', label: 'Inactive' },
            ],
            attribute: 'is_active',
        },
    );

    Swal.fire(buildSwalOptions({
        title: isEdit
            ? (isReviewUi ? 'Review track changes' : 'Edit track')
            : 'Add tracks',
        html: `${renderReviewNotesBanner(existingRaw)}${
            isReviewUi || isEdit ? renderFormAudioPreview(formAudioUrl, { proposed: proposedAudio || isReviewUi }) : ''
        }${buildSwalForm({
            description: publisherReviewOnly
                ? 'Review the staged changes, then publish or return for revision.'
                : (isEdit
                    ? 'Update the title or replace the audio file used in Calm Corner.'
                    : 'Select one or more MP3 / M4A files. Multi-upload uses each file name as the title.'),
            reviewItem,
            fields,
        })}`,
        ...(publisherReviewOnly
            ? publisherReviewSwalButtons(existingRaw)
            : {
                confirmButtonText: isEdit ? 'Save changes' : 'Add tracks',
                showCancelButton: true,
                cancelButtonText: 'Cancel',
            }),
        didOpen: (popup) => {
            bindFormAudioPreview(popup);
        },
        willClose: () => {
            if (previewTrackId === -1) {
                stopPreview();
            }
            tracks = tracks.filter((item) => Number(item.id) !== -1);
        },
        preConfirm: () => {
            if (publisherReviewOnly) {
                return { reviewedOnly: true };
            }

            const values = {
                title: readField('relaxing-music-title'),
                title_tl: readField('relaxing-music-title-tl'),
                audio: isEdit ? readField('relaxing-music-audio') : null,
                audios: isEdit ? [] : readAudioFiles('relaxing-music-audio'),
                sort_order: readField('relaxing-music-sort'),
                is_active: readField('relaxing-music-active'),
            };
            if (isEdit && !values.title) {
                Swal.showValidationMessage('Title is required.');
                return false;
            }
            if (!isEdit && values.audios.length === 0) {
                Swal.showValidationMessage('Select at least one audio file.');
                return false;
            }
            if (!isEdit && values.audios.length > 20) {
                Swal.showValidationMessage('You can upload up to 20 files at once.');
                return false;
            }
            return values;
        },
    }, { size: 'lg' })).then(async (result) => {
        if (publisherReviewOnly) {
            await resolvePublisherReviewSwalResult(result, {
                basePath: BASE_PATH,
                id: existingRaw.id,
                item: existingRaw,
                onSuccess: () => loadTracks(),
            });
            return;
        }

        if (!result.isConfirmed || !result.value) {
            return;
        }

        const values = result.value;
        const formData = new FormData();
        formData.append('title', values.title || '');
        formData.append('title_tl', values.title_tl || '');
        formData.append('sort_order', String(values.sort_order || 0));
        formData.append('is_active', values.is_active === '1' ? '1' : '0');

        if (isEdit) {
            if (values.audio instanceof File) {
                formData.append('audio', values.audio);
            }
        } else {
            values.audios.forEach((file) => {
                formData.append('audios[]', file);
            });
        }

        Swal.fire({
            title: isEdit ? 'Saving track…' : 'Uploading tracks…',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading(),
        });

        try {
            const request = isEdit
                ? axios.post(`${BASE_PATH}/${existingRaw.id}`, (() => {
                    formData.append('_method', 'PUT');
                    return formData;
                })())
                : axios.post(BASE_PATH, formData);

            const { data } = await request;
            Swal.close();
            showSuccessToast(
                data.message,
                isEdit
                    ? 'Updated'
                    : (Array.isArray(values.audios) && values.audios.length > 1 ? 'Tracks added' : 'Created'),
            );
            await loadTracks();
        } catch (error) {
            Swal.close();
            const errors = error?.response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;
            showErrorToast(firstError || error?.response?.data?.message || 'Save failed.');
        }
    });
}
