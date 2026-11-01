import axios from 'axios';
import Swal from 'sweetalert2';
import {
    buildSwalForm,
    buildSwalOptions,
    confirmWithSwal,
} from '../shared/swal-forms';
import { showErrorToast, showSuccessToast } from '../shared/toast';

const DEFAULT_CATEGORIES = [
    { value: 'all', label: 'All' },
    { value: 'nature', label: 'Nature' },
    { value: 'people', label: 'People' },
    { value: 'messages', label: 'Messages' },
];

let categories = [...DEFAULT_CATEGORIES];
let worksheets = [];
let activeCategory = 'all';
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

function categoryLabel(value) {
    return categories.find((item) => item.value === value)?.label || value;
}

function worksheetCategoryOptions() {
    const options = categories.filter((item) => item.value !== 'all');
    return options.length ? options : DEFAULT_CATEGORIES.filter((item) => item.value !== 'all');
}

async function loadCategories() {
    try {
        const { data } = await axios.get('/admin/app-options', {
            params: { group: 'color_fun', per_page: 100 },
        });
        const rows = (data.data?.data ?? []).filter((row) => row.is_active !== false);
        if (rows.length > 0) {
            categories = rows
                .slice()
                .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0))
                .map((row) => ({
                    value: String(row.key || '').toLowerCase(),
                    label: row.label_en || row.key,
                }))
                .filter((item) => item.value);
            if (!categories.some((item) => item.value === 'all')) {
                categories = [{ value: 'all', label: 'All' }, ...categories];
            }
        }
    } catch {
        categories = [...DEFAULT_CATEGORIES];
    }
}

export function initColorFunLibraryModule() {
    const root = document.querySelector('.color-fun-library-page');
    if (!root) {
        return;
    }

    if (!bound) {
        bound = true;
        root.querySelector('[data-color-fun-action="add-worksheet"]')
            ?.addEventListener('click', () => showWorksheetForm(null));
        root.querySelector('#color-fun-library-search')
            ?.addEventListener('input', (event) => {
                searchQuery = String(event.target.value || '').trim().toLowerCase();
                renderGallery();
            });
        root.addEventListener('click', onGalleryClick);
    }

    loadCategories().then(() => loadWorksheets());
}

async function loadWorksheets() {
    const gallery = document.getElementById('color-fun-library-gallery');
    if (gallery) {
        gallery.innerHTML = `
            <div class="sticker-library-empty">
                <i class="fas fa-spinner fa-spin text-2xl text-[#055498]" aria-hidden="true"></i>
                <p>Loading Color Fun library…</p>
            </div>
        `;
    }

    try {
        const { data } = await axios.get('/admin/color-fun-worksheets');
        worksheets = Array.isArray(data?.data) ? data.data : [];
        renderCategoryChips();
        renderGallery();
    } catch (error) {
        showErrorToast(error?.response?.data?.message || 'Failed to load Color Fun worksheets.');
        if (gallery) {
            gallery.innerHTML = `
                <div class="sticker-library-empty">
                    <i class="fas fa-triangle-exclamation text-2xl text-[#CE2028]" aria-hidden="true"></i>
                    <p>Could not load the Color Fun library.</p>
                </div>
            `;
        }
    }
}

function renderCategoryChips() {
    const mount = document.getElementById('color-fun-category-chips');
    if (!mount) {
        return;
    }

    mount.innerHTML = categories.map((category) => {
        const active = category.value === activeCategory;
        const count = category.value === 'all'
            ? worksheets.length
            : worksheets.filter((item) => item.category === category.value).length;

        return `
            <button
                type="button"
                class="sticker-pack-chip${active ? ' is-active' : ''}"
                role="tab"
                aria-selected="${active ? 'true' : 'false'}"
                data-color-fun-category="${escapeHtml(category.value)}"
            >
                <span class="sticker-pack-chip-icon"><i class="fas fa-palette" aria-hidden="true"></i></span>
                <span class="sticker-pack-chip-label">${escapeHtml(category.label)}</span>
                <span class="sticker-pack-chip-count">${count}</span>
            </button>
        `;
    }).join('');

    mount.querySelectorAll('[data-color-fun-category]').forEach((chip) => {
        chip.addEventListener('click', () => {
            activeCategory = chip.getAttribute('data-color-fun-category') || 'all';
            renderCategoryChips();
            renderGallery();
        });
    });
}

function filteredWorksheets() {
    return worksheets.filter((worksheet) => {
        if (activeCategory !== 'all' && worksheet.category !== activeCategory) {
            return false;
        }
        if (!searchQuery) {
            return true;
        }
        const title = String(worksheet.title || '').toLowerCase();
        const category = String(worksheet.category || '').toLowerCase();
        return title.includes(searchQuery)
            || category.includes(searchQuery)
            || String(worksheet.id).includes(searchQuery);
    });
}

function renderGallery() {
    const gallery = document.getElementById('color-fun-library-gallery');
    const meta = document.getElementById('color-fun-library-meta');
    if (!gallery) {
        return;
    }

    const items = filteredWorksheets();
    if (meta) {
        meta.innerHTML = `
            <p>
                <strong class="text-slate-700">${escapeHtml(categoryLabel(activeCategory))}</strong>
                <span class="mx-2 text-slate-300">•</span>
                ${items.length} worksheet${items.length === 1 ? '' : 's'}
            </p>
        `;
    }

    if (!items.length) {
        gallery.innerHTML = `
            <div class="sticker-library-empty">
                <i class="fas fa-images text-3xl text-[#055498]" aria-hidden="true"></i>
                <p>${searchQuery ? 'No worksheets match your search.' : 'No worksheets in this category yet.'}</p>
                ${searchQuery ? '' : `
                    <button type="button" class="admin-primary-button min-h-11" data-color-fun-action="add-worksheet-empty">
                        Add worksheets
                    </button>
                `}
            </div>
        `;
        gallery.querySelector('[data-color-fun-action="add-worksheet-empty"]')
            ?.addEventListener('click', () => showWorksheetForm(null));
        return;
    }

    gallery.innerHTML = `
        <div class="sticker-mosaic">
            ${items.map((worksheet) => `
                <article class="sticker-tile${worksheet.is_active ? '' : ' is-inactive'}" data-worksheet-id="${worksheet.id}">
                    <div class="sticker-tile-media">
                        <img src="${escapeHtml(worksheet.image_url || '')}" alt="${escapeHtml(worksheet.title || 'Worksheet')}" loading="lazy">
                    </div>
                    <div class="sticker-tile-meta">
                        <p class="sticker-tile-name">${escapeHtml(worksheet.title || `Worksheet #${worksheet.id}`)}</p>
                        <p class="sticker-tile-status">
                            ${escapeHtml(categoryLabel(worksheet.category))}
                            <span class="mx-1 text-slate-300">•</span>
                            ${worksheet.is_active ? 'Active' : 'Inactive'}
                        </p>
                    </div>
                    <div class="sticker-tile-actions">
                        <button type="button" class="sticker-tile-btn" data-worksheet-edit="${worksheet.id}" aria-label="Edit worksheet" title="Edit">
                            <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="sticker-tile-btn is-danger" data-worksheet-delete="${worksheet.id}" aria-label="Delete worksheet" title="Delete">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </article>
            `).join('')}
            <button type="button" class="sticker-tile sticker-tile-add" data-color-fun-action="add-worksheet-tile" aria-label="Add worksheets">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span>Add worksheets</span>
            </button>
        </div>
    `;
}

function onGalleryClick(event) {
    const target = event.target.closest('[data-worksheet-edit], [data-worksheet-delete], [data-color-fun-action="add-worksheet-tile"]');
    if (!target) {
        return;
    }

    if (target.matches('[data-worksheet-edit]')) {
        const worksheet = worksheets.find((item) => Number(item.id) === Number(target.getAttribute('data-worksheet-edit')));
        showWorksheetForm(worksheet || null);
        return;
    }

    if (target.matches('[data-worksheet-delete]')) {
        deleteWorksheet(Number(target.getAttribute('data-worksheet-delete')));
        return;
    }

    if (target.matches('[data-color-fun-action="add-worksheet-tile"]')) {
        showWorksheetForm(null);
    }
}

function showWorksheetForm(existing) {
    const isEdit = Boolean(existing?.id);
    const defaultCategory = existing?.category
        || (activeCategory !== 'all' ? activeCategory : 'nature');

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit worksheet' : 'Add worksheets',
        html: buildSwalForm({
            description: isEdit
                ? 'Update the coloring worksheet image used in the mobile Color Fun gallery.'
                : 'Select one or more PNG, JPEG, or WebP coloring pages. Multiple files are added in one go; titles default to each file name.',
            fields: [
                {
                    id: 'color-fun-title',
                    name: 'title',
                    label: isEdit ? 'Title *' : 'Title (single upload only)',
                    type: 'text',
                    value: existing?.title ?? '',
                    required: isEdit,
                    hint: isEdit
                        ? undefined
                        : 'Optional when uploading one image. Multi-upload uses each file name.',
                },
                {
                    id: 'color-fun-category',
                    name: 'category',
                    label: 'Category *',
                    type: 'select',
                    value: defaultCategory,
                    options: worksheetCategoryOptions(),
                    required: true,
                },
                {
                    id: 'color-fun-image',
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
                    id: 'color-fun-sort',
                    name: 'sort_order',
                    label: 'Sort order',
                    type: 'number',
                    value: String(existing?.sort_order ?? 0),
                    hint: isEdit
                        ? undefined
                        : 'Starting order for the first image; additional files continue after it.',
                },
                {
                    id: 'color-fun-active',
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
        confirmButtonText: isEdit ? 'Save changes' : 'Add worksheets',
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const values = {
                title: readField('color-fun-title'),
                category: readField('color-fun-category'),
                image: isEdit ? readField('color-fun-image') : null,
                images: isEdit ? [] : readImageFiles('color-fun-image'),
                sort_order: readField('color-fun-sort'),
                is_active: readField('color-fun-active'),
            };
            if (isEdit && !values.title) {
                Swal.showValidationMessage('Title is required.');
                return false;
            }
            if (!values.category) {
                Swal.showValidationMessage('Category is required.');
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
        formData.append('title', values.title || '');
        formData.append('category', values.category);
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
            const request = isEdit
                ? axios.post(`/admin/color-fun-worksheets/${existing.id}`, (() => {
                    formData.append('_method', 'PUT');
                    return formData;
                })())
                : axios.post('/admin/color-fun-worksheets', formData);

            const { data } = await request;
            showSuccessToast(
                data.message,
                isEdit
                    ? 'Updated'
                    : (Array.isArray(values.images) && values.images.length > 1 ? 'Worksheets added' : 'Created'),
            );
            activeCategory = values.category || activeCategory;
            await loadWorksheets();
        } catch (error) {
            const errors = error?.response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;
            showErrorToast(firstError || error?.response?.data?.message || 'Save failed.');
        }
    });
}

async function deleteWorksheet(id) {
    const worksheet = worksheets.find((item) => Number(item.id) === Number(id));
    const result = await confirmWithSwal({
        title: 'Delete worksheet?',
        text: `Delete “${worksheet?.title || `worksheet #${id}`}”? This cannot be undone.`,
        icon: 'warning',
        confirmButtonText: 'Delete',
        danger: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const { data } = await axios.delete(`/admin/color-fun-worksheets/${id}`);
        showSuccessToast(data.message || 'Worksheet deleted.', 'Deleted');
        await loadWorksheets();
    } catch (error) {
        showErrorToast(error?.response?.data?.message || 'Delete failed.');
    }
}
