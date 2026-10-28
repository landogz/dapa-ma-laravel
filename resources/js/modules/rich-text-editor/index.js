import ClassicEditor from '@ckeditor/ckeditor5-build-classic';

const DEFAULT_TOOLBAR = [
    'heading',
    '|',
    'bold',
    'italic',
    'link',
    'bulletedList',
    'numberedList',
    '|',
    'undo',
    'redo',
];

const DEFAULT_PLACEHOLDER = 'Write the main content';

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

async function mountEditor(textarea) {
    const id = textarea?.id;

    if (!id || editors.has(id) || textarea.dataset.richTextBound === '1') {
        return;
    }

    textarea.dataset.richTextBound = '1';
    textarea.value = toEditorHtml(textarea.value);

    const placeholder = textarea.getAttribute('placeholder') || DEFAULT_PLACEHOLDER;

    try {
        const editor = await ClassicEditor.create(textarea, {
            toolbar: DEFAULT_TOOLBAR,
            placeholder,
        });
        editors.set(id, editor);
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
        const stillInRoot = Boolean(
            (source && root.contains(source))
            || root.querySelector(`#${CSS.escape(id)}`),
        );

        if (!stillInRoot) {
            continue;
        }

        editor.destroy().catch(() => {});
        editors.delete(id);

        if (source) {
            source.dataset.richTextBound = '0';
        }
    }
}
