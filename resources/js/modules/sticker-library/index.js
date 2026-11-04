import axios from 'axios';
import Swal from 'sweetalert2';
import {
    bindZoomableImages,
    buildSwalForm,
    buildSwalOptions,
    confirmWithSwal,
} from '../shared/swal-forms';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let packs = [];
let activePackId = null;
let searchQuery = '';
let bound = false;

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

function readImageFiles(id) {
    const el = document.getElementById(id);
    if (!el || el.type !== 'file') {
        return [];
    }

    return Array.from(el.files || []).filter((file) => file.type.startsWith('image/'));
}

export function initStickerLibraryModule() {
    const root = document.querySelector('.sticker-library-page');
    if (!root) {
        return;
    }

    if (!bound) {
        bound = true;
        root.querySelector('[data-sticker-action="create-pack"]')
            ?.addEventListener('click', () => showPackForm(null));
        root.querySelector('[data-sticker-action="add-sticker"]')
            ?.addEventListener('click', () => showStickerForm(null));
        root.querySelector('#sticker-library-search')
            ?.addEventListener('input', (event) => {
                searchQuery = String(event.target.value || '').trim().toLowerCase();
                renderGallery();
            });
        root.addEventListener('click', onGalleryClick);
    }

    loadPacks();
}

async function loadPacks({ preferPackId = null } = {}) {
    const gallery = document.getElementById('sticker-library-gallery');
    if (gallery) {
        gallery.innerHTML = `
            <div class="sticker-library-empty">
                <i class="fas fa-spinner fa-spin text-2xl text-[#055498]" aria-hidden="true"></i>
                <p>Loading sticker library…</p>
            </div>
        `;
    }

    try {
        const { data } = await axios.get('/admin/sticker-packs');
        packs = Array.isArray(data?.data) ? data.data : [];
        const preferred = preferPackId ?? activePackId;
        if (preferred && packs.some((pack) => Number(pack.id) === Number(preferred))) {
            activePackId = Number(preferred);
        } else {
            activePackId = packs[0]?.id ?? null;
        }
        renderPackChips();
        renderGallery();
    } catch (error) {
        showErrorToast(error?.response?.data?.message || 'Failed to load sticker packs.');
        if (gallery) {
            gallery.innerHTML = `
                <div class="sticker-library-empty">
                    <i class="fas fa-triangle-exclamation text-2xl text-[#CE2028]" aria-hidden="true"></i>
                    <p>Could not load the sticker library.</p>
                </div>
            `;
        }
    }
}

function activePack() {
    return packs.find((pack) => Number(pack.id) === Number(activePackId)) || null;
}

function renderPackChips() {
    const mount = document.getElementById('sticker-pack-chips');
    if (!mount) {
        return;
    }

    if (!packs.length) {
        mount.innerHTML = `
            <span class="sticker-pack-chip is-muted">No packs yet</span>
        `;
        return;
    }

    mount.innerHTML = packs.map((pack) => {
        const active = Number(pack.id) === Number(activePackId);
        const count = Number(pack.stickers_count ?? pack.stickers?.length ?? 0);
        return `
            <button
                type="button"
                class="sticker-pack-chip${active ? ' is-active' : ''}${pack.is_active ? '' : ' is-inactive'}"
                role="tab"
                aria-selected="${active ? 'true' : 'false'}"
                data-pack-id="${pack.id}"
            >
                ${pack.cover_url
                    ? `<img src="${escapeHtml(pack.cover_url)}" alt="" class="sticker-pack-chip-thumb">`
                    : `<span class="sticker-pack-chip-icon"><i class="fas fa-face-smile" aria-hidden="true"></i></span>`}
                <span class="sticker-pack-chip-label">${escapeHtml(pack.name)}</span>
                <span class="sticker-pack-chip-count">${count}</span>
            </button>
        `;
    }).join('');

    mount.querySelectorAll('[data-pack-id]').forEach((chip) => {
        chip.addEventListener('click', () => {
            activePackId = Number(chip.getAttribute('data-pack-id'));
            renderPackChips();
            renderGallery();
        });
    });
}

function filteredStickers(pack) {
    const stickers = Array.isArray(pack?.stickers) ? pack.stickers : [];
    if (!searchQuery) {
        return stickers;
    }

    return stickers.filter((sticker) => {
        const name = String(sticker.name || '').toLowerCase();
        return name.includes(searchQuery) || String(sticker.id).includes(searchQuery);
    });
}

function renderGallery() {
    const gallery = document.getElementById('sticker-library-gallery');
    const meta = document.getElementById('sticker-library-meta');
    if (!gallery) {
        return;
    }

    const pack = activePack();
    if (!pack) {
        if (meta) meta.textContent = '';
        gallery.innerHTML = `
            <div class="sticker-library-empty">
                <i class="fas fa-folder-open text-3xl text-[#055498]" aria-hidden="true"></i>
                <p>Create a sticker pack to get started.</p>
                <button type="button" class="admin-primary-button min-h-11" data-sticker-action="create-pack-empty">
                    New pack
                </button>
            </div>
        `;
        gallery.querySelector('[data-sticker-action="create-pack-empty"]')
            ?.addEventListener('click', () => showPackForm(null));
        return;
    }

    const stickers = filteredStickers(pack);
    if (meta) {
        const status = pack.is_active ? 'Active' : 'Inactive';
        meta.innerHTML = `
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <p>
                    <strong class="text-slate-700">${escapeHtml(pack.name)}</strong>
                    <span class="mx-2 text-slate-300">•</span>
                    ${stickers.length} sticker${stickers.length === 1 ? '' : 's'}
                    <span class="mx-2 text-slate-300">•</span>
                    <span class="${pack.is_active ? 'text-emerald-600' : 'text-slate-400'}">${status}</span>
                </p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="admin-secondary-button min-h-11 px-3" data-pack-edit="${pack.id}">
                        <i class="fas fa-pen-to-square mr-2" aria-hidden="true"></i>Edit pack
                    </button>
                    <button type="button" class="admin-secondary-button min-h-11 px-3 text-[#CE2028]" data-pack-delete="${pack.id}">
                        <i class="fas fa-trash mr-2" aria-hidden="true"></i>Delete pack
                    </button>
                </div>
            </div>
        `;
    }

    if (!stickers.length) {
        gallery.innerHTML = `
            <div class="sticker-library-empty">
                <i class="fas fa-images text-3xl text-[#055498]" aria-hidden="true"></i>
                <p>${searchQuery ? 'No stickers match your search.' : 'This pack has no stickers yet.'}</p>
                ${searchQuery ? '' : `
                    <button type="button" class="admin-primary-button min-h-11" data-sticker-action="add-sticker-empty">
                        Add stickers
                    </button>
                `}
            </div>
        `;
        gallery.querySelector('[data-sticker-action="add-sticker-empty"]')
            ?.addEventListener('click', () => showStickerForm(null));
        return;
    }

    gallery.innerHTML = `
        <div class="sticker-mosaic">
            ${stickers.map((sticker) => `
                <article class="sticker-tile${sticker.is_active ? '' : ' is-inactive'}" data-sticker-id="${sticker.id}">
                    <div class="sticker-tile-media">
                        <img src="${escapeHtml(sticker.image_url || '')}" alt="${escapeHtml(sticker.name || 'Sticker')}" loading="lazy" data-image-zoom>
                    </div>
                    <div class="sticker-tile-meta">
                        <p class="sticker-tile-name">${escapeHtml(sticker.name || `Sticker #${sticker.id}`)}</p>
                        <p class="sticker-tile-status">${sticker.is_active ? 'Active' : 'Inactive'}</p>
                    </div>
                    <div class="sticker-tile-actions">
                        <button type="button" class="sticker-tile-btn" data-sticker-edit="${sticker.id}" aria-label="Edit sticker" title="Edit">
                            <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="sticker-tile-btn is-danger" data-sticker-delete="${sticker.id}" aria-label="Delete sticker" title="Delete">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </article>
            `).join('')}
            <button type="button" class="sticker-tile sticker-tile-add" data-sticker-action="add-sticker-tile" aria-label="Add stickers">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span>Add stickers</span>
            </button>
        </div>
    `;

    bindZoomableImages(gallery);
}

function onGalleryClick(event) {
    const target = event.target.closest('[data-pack-edit], [data-pack-delete], [data-sticker-edit], [data-sticker-delete], [data-sticker-action="add-sticker-tile"]');
    if (!target) {
        return;
    }

    if (target.matches('[data-pack-edit]')) {
        const pack = packs.find((item) => Number(item.id) === Number(target.getAttribute('data-pack-edit')));
        showPackForm(pack || null);
        return;
    }

    if (target.matches('[data-pack-delete]')) {
        deletePack(Number(target.getAttribute('data-pack-delete')));
        return;
    }

    if (target.matches('[data-sticker-edit]')) {
        const stickerId = Number(target.getAttribute('data-sticker-edit'));
        const sticker = packs.flatMap((pack) => pack.stickers || []).find((item) => Number(item.id) === stickerId);
        showStickerForm(sticker || null);
        return;
    }

    if (target.matches('[data-sticker-delete]')) {
        deleteSticker(Number(target.getAttribute('data-sticker-delete')));
        return;
    }

    if (target.matches('[data-sticker-action="add-sticker-tile"]')) {
        showStickerForm(null);
    }
}

function showPackForm(existing) {
    const isEdit = Boolean(existing?.id);
    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit sticker pack' : 'New sticker pack',
        html: buildSwalForm({
            description: 'Packs group stickers shown in the mobile comment Stickers tab.',
            fields: [
                {
                    id: 'pack-name',
                    name: 'name',
                    label: 'Name *',
                    type: 'text',
                    value: existing?.name ?? '',
                    required: true,
                },
                {
                    id: 'pack-slug',
                    name: 'slug',
                    label: 'Slug',
                    type: 'text',
                    value: existing?.slug ?? '',
                    hint: 'Optional. Auto-generated from the name when blank.',
                },
                {
                    id: 'pack-description',
                    name: 'description',
                    label: 'Description',
                    type: 'textarea',
                    value: existing?.description ?? '',
                    span: 2,
                },
                {
                    id: 'pack-cover',
                    name: 'cover',
                    label: 'Cover image',
                    type: 'file',
                    accept: 'image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp',
                    showPreview: true,
                    previewUrl: existing?.cover_url || '',
                    span: 2,
                },
                {
                    id: 'pack-sort',
                    name: 'sort_order',
                    label: 'Sort order',
                    type: 'number',
                    value: String(existing?.sort_order ?? 0),
                },
                {
                    id: 'pack-active',
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
        confirmButtonText: isEdit ? 'Save changes' : 'Create pack',
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const values = {
                name: readField('pack-name'),
                slug: readField('pack-slug'),
                description: readField('pack-description'),
                cover: readField('pack-cover'),
                sort_order: readField('pack-sort'),
                is_active: readField('pack-active'),
            };
            if (!values.name) {
                Swal.showValidationMessage('Name is required.');
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
        formData.append('name', values.name);
        if (values.slug) formData.append('slug', values.slug);
        formData.append('description', values.description || '');
        formData.append('sort_order', String(values.sort_order || 0));
        formData.append('is_active', values.is_active === '1' ? '1' : '0');
        if (values.cover instanceof File) {
            formData.append('cover', values.cover);
        }

        try {
            const request = isEdit
                ? axios.post(`/admin/sticker-packs/${existing.id}`, (() => {
                    formData.append('_method', 'PUT');
                    return formData;
                })())
                : axios.post('/admin/sticker-packs', formData);

            const { data } = await request;
            showSuccessToast(data.message, isEdit ? 'Updated' : 'Created');
            await loadPacks({ preferPackId: data?.data?.id ?? existing?.id });
        } catch (error) {
            const errors = error?.response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;
            showErrorToast(firstError || error?.response?.data?.message || 'Save failed.');
        }
    });
}

function showStickerForm(existing) {
    const isEdit = Boolean(existing?.id);
    const packOptions = packs.map((pack) => ({
        value: String(pack.id),
        label: pack.name,
    }));

    if (!packOptions.length) {
        showErrorToast('Create a sticker pack first.');
        return;
    }

    const defaultPackId = existing?.sticker_pack_id
        || activePackId
        || packOptions[0].value;

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit sticker' : 'Add stickers',
        html: buildSwalForm({
            description: isEdit
                ? 'Upload a PNG, JPEG, or WebP image for the mobile Stickers tab.'
                : 'Select one or more PNG, JPEG, or WebP images. Multiple files are added in one go; names default to each file name.',
            fields: [
                {
                    id: 'sticker-pack',
                    name: 'sticker_pack_id',
                    label: 'Pack *',
                    type: 'select',
                    value: String(defaultPackId),
                    options: packOptions,
                    required: true,
                },
                {
                    id: 'sticker-name',
                    name: 'name',
                    label: isEdit ? 'Name' : 'Name (single upload only)',
                    type: 'text',
                    value: existing?.name ?? '',
                    hint: isEdit
                        ? undefined
                        : 'Optional when uploading one image. Multi-upload uses each file name.',
                },
                {
                    id: 'sticker-image',
                    name: isEdit ? 'image' : 'images',
                    label: isEdit ? 'Image' : 'Images *',
                    type: 'file',
                    accept: 'image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp',
                    showPreview: true,
                    previewUrl: existing?.image_url || '',
                    multiple: !isEdit,
                    span: 2,
                    required: !isEdit,
                    hint: isEdit
                        ? undefined
                        : 'You can select up to 40 images at once.',
                },
                {
                    id: 'sticker-sort',
                    name: 'sort_order',
                    label: 'Sort order',
                    type: 'number',
                    value: String(existing?.sort_order ?? 0),
                    hint: isEdit
                        ? undefined
                        : 'Starting order for the first image; additional files continue after it.',
                },
                {
                    id: 'sticker-active',
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
        confirmButtonText: isEdit ? 'Save changes' : 'Add stickers',
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const values = {
                sticker_pack_id: readField('sticker-pack'),
                name: readField('sticker-name'),
                image: isEdit ? readField('sticker-image') : null,
                images: isEdit ? [] : readImageFiles('sticker-image'),
                sort_order: readField('sticker-sort'),
                is_active: readField('sticker-active'),
            };
            if (!values.sticker_pack_id) {
                Swal.showValidationMessage('Pack is required.');
                return false;
            }
            if (!isEdit && values.images.length === 0) {
                Swal.showValidationMessage('Select at least one image.');
                return false;
            }
            if (!isEdit && values.images.length > 40) {
                Swal.showValidationMessage('You can upload up to 40 images at once.');
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
        formData.append('sticker_pack_id', values.sticker_pack_id);
        formData.append('name', values.name || '');
        formData.append('sort_order', String(values.sort_order || 0));
        formData.append('is_active', values.is_active === '1' ? '1' : '0');

        if (isEdit) {
            if (values.image instanceof File) {
                formData.append('image', values.image);
            }
        } else {
            values.images.forEach((file) => {
                formData.append('images[]', file);
            });
        }

        try {
            const packId = Number(values.sticker_pack_id);
            const request = isEdit
                ? axios.post(`/admin/stickers/${existing.id}`, (() => {
                    formData.append('_method', 'PUT');
                    return formData;
                })())
                : axios.post('/admin/stickers', formData);

            const { data } = await request;
            showSuccessToast(
                data.message,
                isEdit
                    ? 'Updated'
                    : (Array.isArray(values.images) && values.images.length > 1 ? 'Stickers added' : 'Created'),
            );
            await loadPacks({ preferPackId: packId });
        } catch (error) {
            const errors = error?.response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;
            showErrorToast(firstError || error?.response?.data?.message || 'Save failed.');
        }
    });
}

async function deletePack(id) {
    const pack = packs.find((item) => Number(item.id) === Number(id));
    const result = await confirmWithSwal({
        title: 'Delete sticker pack?',
        text: `Delete “${pack?.name || 'this pack'}” and all of its stickers? This cannot be undone.`,
        icon: 'warning',
        confirmButtonText: 'Delete pack',
        danger: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const { data } = await axios.delete(`/admin/sticker-packs/${id}`);
        showSuccessToast(data.message || 'Sticker pack deleted.', 'Deleted');
        if (Number(activePackId) === Number(id)) {
            activePackId = null;
        }
        await loadPacks();
    } catch (error) {
        showErrorToast(error?.response?.data?.message || 'Delete failed.');
    }
}

async function deleteSticker(id) {
    const sticker = packs.flatMap((pack) => pack.stickers || []).find((item) => Number(item.id) === Number(id));
    const result = await confirmWithSwal({
        title: 'Delete sticker?',
        text: `Delete “${sticker?.name || `sticker #${id}`}”? This cannot be undone.`,
        icon: 'warning',
        confirmButtonText: 'Delete',
        danger: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const { data } = await axios.delete(`/admin/stickers/${id}`);
        showSuccessToast(data.message || 'Sticker deleted.', 'Deleted');
        await loadPacks({ preferPackId: activePackId });
    } catch (error) {
        showErrorToast(error?.response?.data?.message || 'Delete failed.');
    }
}
