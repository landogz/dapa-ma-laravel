const ALLOWED_TAGS = new Set([
    'P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'BLOCKQUOTE',
    'UL', 'OL', 'LI', 'H2', 'H3', 'H4', 'H5', 'A', 'IMG',
    'SPAN', 'DIV', 'FIGURE', 'FIGCAPTION',
]);

const ALLOWED_ATTRIBUTES = {
    A: new Set(['href', 'title', 'rel', 'target']),
    IMG: new Set(['src', 'alt', 'title', 'width', 'height']),
};

function isDangerousUrl(value) {
    const lower = String(value ?? '').trim().toLowerCase();

    return lower.startsWith('javascript:')
        || lower.startsWith('data:')
        || lower.startsWith('vbscript:');
}

function cleanElement(element) {
    [...element.childNodes].forEach((node) => {
        if (node.nodeType === Node.COMMENT_NODE) {
            node.remove();
            return;
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        if (!ALLOWED_TAGS.has(node.tagName)) {
            node.remove();
            return;
        }

        [...node.attributes].forEach((attribute) => {
            const name = attribute.name.toLowerCase();
            const allowed = ALLOWED_ATTRIBUTES[node.tagName] ?? new Set();

            if (name.startsWith('on') || !allowed.has(name)) {
                node.removeAttribute(attribute.name);
                return;
            }

            if ((name === 'href' || name === 'src') && isDangerousUrl(attribute.value)) {
                node.removeAttribute(attribute.name);
            }
        });

        if (node.tagName === 'A') {
            node.setAttribute('rel', 'noopener noreferrer');
        }

        cleanElement(node);
    });
}

export function sanitizeHtml(value) {
    const raw = String(value ?? '').trim();
    if (!raw) {
        return '';
    }

    const template = document.createElement('template');
    template.innerHTML = raw;
    cleanElement(template.content);

    return template.innerHTML;
}
