import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import axios from 'axios';
import { showErrorToast, showSuccessToast } from '../shared/toast';

const DEFAULT_TOOLBAR = [
    'heading',
    '|',
    'bold',
    'italic',
    'link',
    '|',
    'bulletedList',
    'numberedList',
    '|',
    'undo',
    'redo',
];

const DEFAULT_PLACEHOLDER = 'Write the main content';
const EDITOR_MEDIA_UPLOAD_URL = '/admin/editor-media';

/** @type {Map<string, import('@ckeditor/ckeditor5-build-classic').default>} */
const editors = new Map();

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function authToken() {
    return sessionStorage.getItem('dape_ma_admin_token') || '';
}

function uploadHeaders() {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = authToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    return headers;
}

function editorUploadUrl() {
    const base = String(axios.defaults.baseURL || '/api/v1').replace(/\/$/, '');
    return `${base}${EDITOR_MEDIA_UPLOAD_URL}`;
}

export function looksLikeHtml(value) {
    return /<[a-z][\s\S]*>/i.test(String(value ?? ''));
}

/**
 * Convert stored plain text (newlines) into paragraph HTML for CKEditor.
 */
export function toEditorHtml(value) {
    const raw = String(value ?? '');
    const trimmed = raw.trim();

    if (!trimmed) {
        return '';
    }

    if (looksLikeHtml(trimmed)) {
        return raw;
    }

    return trimmed
        .split(/\n\s*\n/)
        .map((block) => block.trim())
        .filter(Boolean)
        .map((block) => `<p>${escapeHtml(block).replaceAll('\n', '<br>')}</p>`)
        .join('');
}

export function isRichTextEmpty(html) {
    return String(html ?? '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim() === '';
}

/**
 * Body rich-text fields: explicit type, or a textarea whose label starts with "Body".
 * "Message body", "Modal body", and similar labels stay plain textareas.
 */
export function isBodyRichTextField(field = {}) {
    const type = String(field.type ?? '').toLowerCase();

    if (type === 'richtext' || type === 'body' || type === 'ckeditor') {
        return true;
    }

    if (type && type !== 'textarea') {
        return false;
    }

    return /^body(\b|[\s/(])/i.test(String(field.label ?? '').trim());
}

export function getRichTextValue(id) {
    const editor = editors.get(String(id));

    if (editor) {
        return String(editor.getData() ?? '').trim();
    }

    return String(document.getElementById(id)?.value ?? '').trim();
}

function detectMediaKind(file, mimeFromServer = '') {
    const mime = String(mimeFromServer || file?.type || '').toLowerCase();
    if (mime.startsWith('video/')) {
        return 'video';
    }
    if (mime.startsWith('image/')) {
        return 'image';
    }
    const name = String(file?.name || '').toLowerCase();
    if (/\.(mp4|webm|ogg|mov|m4v)$/i.test(name)) {
        return 'video';
    }

    return 'image';
}

async function uploadEditorMedia(file) {
    const formData = new FormData();
    formData.append('upload', file);
    formData.append('file', file);

    const { data } = await axios.post(EDITOR_MEDIA_UPLOAD_URL, formData, {
        headers: {
            ...uploadHeaders(),
            'Content-Type': 'multipart/form-data',
        },
    });

    const url = data?.url || data?.data?.url;
    if (!url) {
        throw new Error(data?.message || 'Upload failed.');
    }

    return {
        url: String(url),
        kind: data?.data?.kind || detectMediaKind(file, data?.data?.mime),
    };
}

function insertMediaIntoEditor(editor, { url, kind }) {
    editor.model.change((writer) => {
        const selection = editor.model.document.selection;
        if (kind === 'video') {
            const mediaElement = writer.createElement('media', { url });
            editor.model.insertContent(mediaElement, selection);
            return;
        }

        const schema = editor.model.schema;
        const imageName = schema.isRegistered('imageBlock')
            ? 'imageBlock'
            : (schema.isRegistered('image') ? 'image' : null);

        if (!imageName) {
            throw new Error('Image insert is not available in this editor.');
        }

        const imageElement = writer.createElement(imageName, { src: url });
        editor.model.insertContent(imageElement, selection);
    });
    editor.editing.view.focus();
}

function attachAddMediaBar(editor, textarea) {
    const shell = textarea.closest('.admin-rich-text') || textarea.parentElement;
    if (!shell || shell.querySelector('[data-rich-text-add-media]')) {
        return;
    }

    const bar = document.createElement('div');
    bar.className = 'admin-rich-text-media-bar';
    bar.setAttribute('data-rich-text-add-media', '1');
    bar.innerHTML = `
        <button type="button" class="admin-rich-text-add-media" aria-label="Add media to body content">
            <i class="fas fa-photo-film" aria-hidden="true"></i>
            <span>Add Media</span>
        </button>
        <span class="admin-rich-text-media-hint">Insert image or video into the body.</span>
        <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.mov" data-rich-text-media-input>
    `;

    const editorRoot = shell.querySelector('.ck.ck-editor');
    if (editorRoot) {
        shell.insertBefore(bar, editorRoot);
    } else {
        shell.prepend(bar);
    }

    const button = bar.querySelector('.admin-rich-text-add-media');
    const input = bar.querySelector('[data-rich-text-media-input]');
    if (!button || !input) {
        return;
    }

    button.addEventListener('click', () => {
        input.value = '';
        input.click();
    });

    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) {
            return;
        }

        button.disabled = true;
        button.classList.add('is-busy');
        try {
            const media = await uploadEditorMedia(file);
            insertMediaIntoEditor(editor, media);
            showSuccessToast(
                media.kind === 'video' ? 'Video inserted into the body.' : 'Image inserted into the body.',
                'Add Media',
            );
        } catch (error) {
            const message = error?.response?.data?.message
                || error?.response?.data?.errors?.upload?.[0]
                || error?.message
                || 'Unable to upload media.';
            showErrorToast(message, 'Add Media');
        } finally {
            button.disabled = false;
            button.classList.remove('is-busy');
            input.value = '';
        }
    });
}

function buildEditorConfig(placeholder) {
    return {
        toolbar: DEFAULT_TOOLBAR,
        placeholder,
        simpleUpload: {
            uploadUrl: editorUploadUrl(),
            headers: uploadHeaders(),
        },
        mediaEmbed: {
            previewsInData: true,
            extraProviders: [
                {
                    name: 'dapeLocalVideo',
                    url: [
                        /^(https?:)?\/\/.+\.(mp4|webm|ogg|ogv|mov|m4v)(\?.*)?$/i,
                        /\/storage\/.+\.(mp4|webm|ogg|ogv|mov|m4v)(\?.*)?$/i,
                    ],
                    html: (match) => {
                        const src = escapeHtml(match.input || match[0] || '');
                        return `
                            <div class="dape-editor-video">
                                <video controls playsinline preload="metadata" src="${src}"></video>
                            </div>
                        `;
                    },
                },
            ],
        },
    };
}

async function disposeEditor(id, editor) {
    const source = editor?.sourceElement;
    const shell = source?.closest?.('.admin-rich-text')
        || document.getElementById(id)?.closest?.('.admin-rich-text');
    shell?.querySelector?.('[data-rich-text-add-media]')?.remove();

    try {
        await editor.destroy();
    } catch {
        // Editor DOM may already be gone with the Swal popup.
    }

    editors.delete(id);

    if (source?.isConnected) {
        source.dataset.richTextBound = '0';
    }
}

async function mountEditor(textarea) {
    const id = textarea?.id;

    if (!id) {
        return;
    }

    const existing = editors.get(id);
    if (existing) {
        // Same live instance — skip. Otherwise clear a stale Swal remount.
        if (existing.sourceElement === textarea && textarea.isConnected) {
            return;
        }
        await disposeEditor(id, existing);
    }

    if (textarea.dataset.richTextBound === '1' && textarea.closest('.ck') ) {
        return;
    }

    textarea.dataset.richTextBound = '1';
    textarea.value = toEditorHtml(textarea.value);

    const placeholder = textarea.getAttribute('placeholder') || DEFAULT_PLACEHOLDER;
    textarea.closest('.admin-rich-text')?.classList.add('admin-rich-text-with-media');

    try {
        let editor;
        try {
            editor = await ClassicEditor.create(textarea, buildEditorConfig(placeholder));
        } catch (primaryError) {
            // Fallback without media toolbar items if this Classic build rejects them.
            console.warn('Rich text media toolbar failed; retrying basic editor.', primaryError);
            editor = await ClassicEditor.create(textarea, {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo'],
                placeholder,
            });
        }

        editors.set(id, editor);
        attachAddMediaBar(editor, textarea);
    } catch (error) {
        textarea.dataset.richTextBound = '0';
        console.error('Unable to start the rich text editor.', error);
    }
}

export async function bindRichTextEditors(root = document) {
    const nodes = [...root.querySelectorAll('textarea[data-rich-text]')];
    await Promise.all(nodes.map((textarea) => mountEditor(textarea)));
}

export function destroyRichTextEditors(root = document) {
    for (const [id, editor] of [...editors.entries()]) {
        const source = editor.sourceElement;
        const orphaned = !source || !source.isConnected;
        const inRoot = Boolean(
            (source && root.contains(source))
            || root.querySelector?.(`#${CSS.escape(id)}`),
        );

        // Always tear down orphaned instances left behind when Swal removes DOM
        // before/during willClose; otherwise the next open skips remount.
        if (root !== document && !inRoot && !orphaned) {
            continue;
        }

        disposeEditor(id, editor);
    }
}
