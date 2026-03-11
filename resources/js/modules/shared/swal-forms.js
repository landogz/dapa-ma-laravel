function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function renderOptions(options = [], selectedValue = '') {
    return options
        .map((option) => {
            const value = escapeHtml(option.value);
            const label = escapeHtml(option.label);
            const selected = String(option.value) === String(selectedValue) ? ' selected' : '';

            return `<option value="${value}"${selected}>${label}</option>`;
        })
        .join('');
}

function renderField(field) {
    const label = `<label class="admin-swal-label" for="${field.id}">${escapeHtml(field.label)}</label>`;
    const placeholder = field.placeholder ? ` placeholder="${escapeHtml(field.placeholder)}"` : '';
    const value = field.value ? ` value="${escapeHtml(field.value)}"` : '';
    const min = field.min ? ` min="${escapeHtml(field.min)}"` : '';
    const hint = field.hint ? `<p class="admin-swal-hint">${escapeHtml(field.hint)}</p>` : '';

    if (field.type === 'textarea') {
        return `
            <div class="admin-swal-field">
                ${label}
                <textarea id="${field.id}" class="admin-swal-textarea"${placeholder}>${escapeHtml(field.value ?? '')}</textarea>
                ${hint}
            </div>
        `;
    }

    if (field.type === 'select') {
        return `
            <div class="admin-swal-field">
                ${label}
                <select id="${field.id}" class="admin-swal-select">
                    ${renderOptions(field.options, field.value)}
                </select>
                ${hint}
            </div>
        `;
    }

    if (field.type === 'file') {
        return `
            <div class="admin-swal-field">
                ${label}
                <input id="${field.id}" class="admin-swal-input" type="file"${field.accept ? ` accept="${escapeHtml(field.accept)}"` : ''}>
                ${hint}
            </div>
        `;
    }

    return `
        <div class="admin-swal-field">
            ${label}
            <input id="${field.id}" class="admin-swal-input" type="${field.type ?? 'text'}"${placeholder}${value}${min}>
            ${hint}
        </div>
    `;
}

export function buildSwalForm({ description = '', fields = [] }) {
    return `
        <div class="admin-swal-form">
            ${description ? `<p class="admin-swal-description">${escapeHtml(description)}</p>` : ''}
            <div class="admin-swal-fields">
                ${fields.map(renderField).join('')}
            </div>
        </div>
    `;
}

export function buildSwalOptions(options, { danger = false } = {}) {
    return {
        ...options,
        buttonsStyling: false,
        customClass: {
            popup: 'admin-swal-popup',
            title: 'admin-swal-title',
            htmlContainer: 'admin-swal-html',
            actions: 'admin-swal-actions',
            confirmButton: danger ? 'admin-primary-button admin-swal-button admin-swal-button-danger' : 'admin-primary-button admin-swal-button',
            cancelButton: 'admin-secondary-button admin-swal-button',
            validationMessage: 'admin-swal-validation',
            ...options.customClass,
        },
    };
}
