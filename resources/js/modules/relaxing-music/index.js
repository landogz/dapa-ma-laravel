import axios from 'axios';
import Swal from 'sweetalert2';
import {
    buildSwalForm,
    buildSwalOptions,
    confirmWithSwal,
} from '../shared/swal-forms';
import { showErrorToast, showSuccessToast } from '../shared/toast';

const STATUS_FILTERS = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

let tracks = [];
let activeStatus = 'all';
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
        button.classList.toggle('ring-2', isCurrent);
        button.classList.toggle('ring-[#FBD116]', isCurrent);
    });
}

export function initRelaxingMusicModule() {
    const root = document.querySelector('.relaxing-music-page');
    if (!root) {
        return;
    }

    if (!bound) {
        bound = true;
        root.querySelector('[data-relaxing-music-action="add-track"]')
            ?.addEventListener('click', () => showTrackForm(null));
        root.querySelector('#relaxing-music-search')
            ?.addEventListener('input', (event) => {
                searchQuery = String(event.target.value || '').trim().toLowerCase();
                renderGallery();
            });
        root.addEventListener('click', onGalleryClick);
        window.addEventListener('pagehide', stopPreview);
    }

    loadTracks();
}

async function loadTracks() {
    const gallery = document.getElementById('relaxing-music-gallery');
    if (gallery) {
        gallery.innerHTML = `
            <div class="sticker-library-empty">
                <i class="fas fa-spinner fa-spin text-2xl text-[#055498]" aria-hidden="true"></i>
                <p>Loading relaxing music…</p>
            </div>
        `;
    }

    try {
        const { data } = await axios.get('/admin/relaxing-music-tracks');
        tracks = Array.isArray(data?.data) ? data.data : [];
        renderStatusChips();
        renderGallery();
    } catch (error) {
        showErrorToast(error?.response?.data?.message || 'Failed to load relaxing music.');
        if (gallery) {
            gallery.innerHTML = `
                <div class="sticker-library-empty">
                    <i class="fas fa-triangle-exclamation text-2xl text-[#CE2028]" aria-hidden="true"></i>
                    <p>Could not load relaxing music.</p>
                </div>
            `;
        }
    }
}

function renderStatusChips() {
    const mount = document.getElementById('relaxing-music-status-chips');
    if (!mount) {
        return;
    }

    mount.innerHTML = STATUS_FILTERS.map((filter) => {
        const active = filter.value === activeStatus;
        const count = filter.value === 'all'
            ? tracks.length
            : tracks.filter((item) => (filter.value === 'active' ? item.is_active !== false : item.is_active === false)).length;

        return `
            <button
                type="button"
                class="sticker-pack-chip${active ? ' is-active' : ''}"
                role="tab"
                aria-selected="${active ? 'true' : 'false'}"
                data-relaxing-music-status="${escapeHtml(filter.value)}"
            >
                <span class="sticker-pack-chip-icon"><i class="fas fa-music" aria-hidden="true"></i></span>
                <span class="sticker-pack-chip-label">${escapeHtml(filter.label)}</span>
                <span class="sticker-pack-chip-count">${count}</span>
            </button>
        `;
    }).join('');

    mount.querySelectorAll('[data-relaxing-music-status]').forEach((chip) => {
        chip.addEventListener('click', () => {
            activeStatus = chip.getAttribute('data-relaxing-music-status') || 'all';
            renderStatusChips();
            renderGallery();
        });
    });
}

function filteredTracks() {
    return tracks.filter((track) => {
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

    if (rows.length === 0) {
        gallery.innerHTML = `
            <div class="sticker-library-empty">
                <i class="fas fa-music text-2xl text-[#055498]" aria-hidden="true"></i>
                <p>No tracks yet. Upload MP3 or M4A files for Calm Corner.</p>
                <button type="button" class="admin-primary-button min-h-11 mt-3" data-relaxing-music-action="add-track-tile">
                    <i class="fas fa-plus mr-2" aria-hidden="true"></i>
                    Add tracks
                </button>
            </div>
        `;
        return;
    }

    gallery.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
            ${rows.map((track) => `
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col gap-3 min-w-0">
                    <div class="flex items-start gap-3">
                        <button
                            type="button"
                            class="shrink-0 w-12 h-12 rounded-full bg-[#055498] text-white flex items-center justify-center min-h-11 min-w-11${previewTrackId === Number(track.id) ? ' ring-2 ring-[#FBD116]' : ''}"
                            data-track-play="${Number(track.id)}"
                            aria-label="${previewTrackId === Number(track.id) && previewPlaying ? 'Pause' : 'Preview'} ${escapeHtml(track.title || 'track')}"
                        >
                            <i class="fas ${previewTrackId === Number(track.id) && previewPlaying ? 'fa-pause' : 'fa-play'}" aria-hidden="true"></i>
                        </button>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold text-[#123a60] truncate">${escapeHtml(track.title || 'Untitled')}</h3>
                            <p class="text-sm text-slate-500 truncate">${escapeHtml(track.title_tl || track.title || '')}</p>
                            <p class="text-xs text-slate-400 mt-1">
                                ${formatDuration(track.duration_seconds)}
                                · ${track.is_active === false ? 'Inactive' : 'Active'}
                                · Order ${Number(track.sort_order ?? 0)}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="admin-secondary-button min-h-11 flex-1" data-track-edit="${Number(track.id)}">
                            <i class="fas fa-pen-to-square mr-2" aria-hidden="true"></i>Edit
                        </button>
                        <button type="button" class="admin-secondary-button min-h-11 flex-1 text-[#CE2028]" data-track-delete="${Number(track.id)}">
                            <i class="fas fa-trash mr-2" aria-hidden="true"></i>Delete
                        </button>
                    </div>
                </article>
            `).join('')}
            <button
                type="button"
                class="rounded-2xl border-2 border-dashed border-slate-300 min-h-[140px] text-[#055498] font-semibold hover:bg-slate-50"
                data-relaxing-music-action="add-track-tile"
            >
                <i class="fas fa-plus mr-2" aria-hidden="true"></i>
                Add tracks
            </button>
        </div>
    `;
}

function onGalleryClick(event) {
    const target = event.target.closest('[data-track-edit], [data-track-delete], [data-track-play], [data-relaxing-music-action="add-track-tile"]');
    if (!target) {
        return;
    }

    if (target.matches('[data-track-play]')) {
        playTrack(Number(target.getAttribute('data-track-play')));
        return;
    }

    if (target.matches('[data-track-edit]')) {
        const track = tracks.find((item) => Number(item.id) === Number(target.getAttribute('data-track-edit')));
        showTrackForm(track || null);
        return;
    }

    if (target.matches('[data-track-delete]')) {
        deleteTrack(Number(target.getAttribute('data-track-delete')));
        return;
    }

    if (target.matches('[data-relaxing-music-action="add-track-tile"]')) {
        showTrackForm(null);
    }
}

function playTrack(id) {
    const trackId = Number(id);
    const track = tracks.find((item) => Number(item.id) === trackId);
    if (!track?.audio_url) {
        showErrorToast('This track has no audio file.');
        return;
    }

    // Same track: toggle pause / resume.
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

function showTrackForm(existing) {
    const isEdit = Boolean(existing?.id);

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit track' : 'Add tracks',
        html: buildSwalForm({
            description: isEdit
                ? 'Update the title or replace the audio file used in Calm Corner.'
                : 'Select one or more MP3 / M4A files. Multi-upload uses each file name as the title.',
            fields: [
                {
                    id: 'relaxing-music-title',
                    name: 'title',
                    label: isEdit ? 'Title *' : 'Title (single upload only)',
                    type: 'text',
                    value: existing?.title ?? '',
                    required: isEdit,
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
                },
                {
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
                },
                {
                    id: 'relaxing-music-sort',
                    name: 'sort_order',
                    label: 'Sort order',
                    type: 'number',
                    value: String(existing?.sort_order ?? 0),
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
                },
            ],
        }),
        confirmButtonText: isEdit ? 'Save changes' : 'Add tracks',
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        preConfirm: () => {
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

        try {
            const request = isEdit
                ? axios.post(`/admin/relaxing-music-tracks/${existing.id}`, (() => {
                    formData.append('_method', 'PUT');
                    return formData;
                })())
                : axios.post('/admin/relaxing-music-tracks', formData);

            const { data } = await request;
            showSuccessToast(
                data.message,
                isEdit
                    ? 'Updated'
                    : (Array.isArray(values.audios) && values.audios.length > 1 ? 'Tracks added' : 'Created'),
            );
            await loadTracks();
        } catch (error) {
            const errors = error?.response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;
            showErrorToast(firstError || error?.response?.data?.message || 'Save failed.');
        }
    });
}

async function deleteTrack(id) {
    const track = tracks.find((item) => Number(item.id) === Number(id));
    const result = await confirmWithSwal({
        title: 'Delete track?',
        text: `Delete “${track?.title || `track #${id}`}”? This cannot be undone.`,
        icon: 'warning',
        confirmButtonText: 'Delete',
        danger: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const { data } = await axios.delete(`/admin/relaxing-music-tracks/${id}`);
        showSuccessToast(data.message || 'Track deleted.', 'Deleted');
        stopPreview();
        await loadTracks();
    } catch (error) {
        showErrorToast(error?.response?.data?.message || 'Delete failed.');
    }
}
