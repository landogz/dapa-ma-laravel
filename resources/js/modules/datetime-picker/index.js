const DATETIME_SELECTOR = [
    'input[type="datetime-local"]',
    'input[type="date"]',
    'input[type="time"]',
    '[data-datetime-picker]',
].join(', ');

function isDatetimePickerInput(element) {
    if (!(element instanceof HTMLInputElement)) {
        return false;
    }

    return element.type === 'datetime-local'
        || element.type === 'date'
        || element.type === 'time'
        || element.hasAttribute('data-datetime-picker');
}

function collectDatetimePickerInputs(root = document) {
    if (!root) {
        return [];
    }

    const found = Array.from(root.querySelectorAll(DATETIME_SELECTOR))
        .filter((element) => isDatetimePickerInput(element));

    if (isDatetimePickerInput(root)) {
        found.unshift(root);
    }

    return [...new Set(found)];
}

function iconClassForInput(input) {
    return input.type === 'time' ? 'fas fa-clock' : 'fas fa-calendar-days';
}

function ensureDatetimePickerWrap(input) {
    const existing = input.closest('.admin-datetime-picker');
    if (existing) {
        return existing;
    }

    const wrap = document.createElement('div');
    wrap.className = 'admin-datetime-picker';
    input.parentNode?.insertBefore(wrap, input);
    wrap.appendChild(input);

    const icon = document.createElement('span');
    icon.className = 'admin-datetime-picker-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = `<i class="${iconClassForInput(input)}"></i>`;
    wrap.appendChild(icon);

    return wrap;
}

function openNativePicker(input) {
    if (!(input instanceof HTMLInputElement) || input.disabled || input.readOnly) {
        return;
    }

    input.focus({ preventScroll: true });

    if (typeof input.showPicker !== 'function') {
        return;
    }

    try {
        input.showPicker();
    } catch {
        // Browser may reject showPicker outside a user gesture or if already open.
    }
}

export function bindDatetimePicker(input) {
    if (!isDatetimePickerInput(input) || input.dataset.datetimeBound === '1') {
        return input?._datetimePicker ?? null;
    }

    input.dataset.datetimeBound = '1';
    input.setAttribute('data-datetime-picker', '');
    input.setAttribute('autocomplete', 'off');

    const wrap = ensureDatetimePickerWrap(input);

    const onPointerDown = (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        openNativePicker(input);
    };

    const onKeyDown = (event) => {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
            event.preventDefault();
            openNativePicker(input);
        }
    };

    wrap.addEventListener('pointerdown', onPointerDown);
    input.addEventListener('keydown', onKeyDown);

    const api = {
        destroy() {
            wrap.removeEventListener('pointerdown', onPointerDown);
            input.removeEventListener('keydown', onKeyDown);
            delete input.dataset.datetimeBound;
            delete input._datetimePicker;
        },
        open() {
            openNativePicker(input);
        },
    };

    input._datetimePicker = api;
    return api;
}

export function bindDatetimePickerFields(root = document) {
    return collectDatetimePickerInputs(root).map((input) => bindDatetimePicker(input));
}

export function destroyDatetimePickerFields(root = document) {
    collectDatetimePickerInputs(root).forEach((input) => {
        input._datetimePicker?.destroy();
    });
}
