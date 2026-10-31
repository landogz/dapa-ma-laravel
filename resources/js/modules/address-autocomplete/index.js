import axios from 'axios';

const MIN_QUERY_LENGTH = 3;
const DEBOUNCE_MS = 320;
const ADDRESS_LABEL = /^address(\s*\*)?$/i;

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function normalizePlace(item = {}) {
    const latitude = Number(item.latitude ?? item.lat);
    const longitude = Number(item.longitude ?? item.lng);
    const address = String(item.address ?? item.label ?? '').trim();

    return {
        label: String(item.label ?? address),
        address,
        latitude: Number.isFinite(latitude) ? latitude : null,
        longitude: Number.isFinite(longitude) ? longitude : null,
        lat: Number.isFinite(latitude) ? latitude : null,
        lng: Number.isFinite(longitude) ? longitude : null,
        region: String(item.region ?? '').trim(),
        province: String(item.province ?? '').trim(),
        city: String(item.city ?? '').trim(),
    };
}

export async function searchAddresses(query) {
    const trimmed = String(query ?? '').trim();
    if (trimmed.length < MIN_QUERY_LENGTH) {
        return [];
    }

    const { data } = await axios.get('/geocode/search', { params: { q: trimmed } });
    return (data.data?.results ?? []).map(normalizePlace);
}

export async function reverseGeocodeAddress(lat, lng) {
    const { data } = await axios.get('/geocode/reverse', { params: { lat, lng } });
    return data.data ? normalizePlace(data.data) : null;
}

function ensureWrap(input) {
    const parent = input.parentElement;
    if (parent?.classList.contains('address-autocomplete')) {
        return parent;
    }

    const wrap = document.createElement('div');
    wrap.className = 'address-autocomplete';
    parent?.insertBefore(wrap, input);
    wrap.appendChild(input);
    return wrap;
}

function ensureMenu(wrap) {
    let menu = wrap.querySelector('.address-autocomplete-menu');
    if (menu) {
        return menu;
    }

    menu = document.createElement('div');
    menu.className = 'address-autocomplete-menu';
    menu.hidden = true;
    menu.setAttribute('role', 'listbox');
    wrap.appendChild(menu);
    return menu;
}

function hideMenu(input, menu) {
    menu.hidden = true;
    menu.innerHTML = '';
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
}

function optionId(input, index) {
    return `${input.id || 'address'}-suggestion-${index}`;
}

function renderMenu(input, menu, results, { activeIndex = 0, emptyText = '' } = {}) {
    if (emptyText) {
        menu.innerHTML = `<p class="address-autocomplete-empty">${escapeHtml(emptyText)}</p>`;
        menu.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        return;
    }

    if (!results.length) {
        hideMenu(input, menu);
        return;
    }

    menu.innerHTML = results
        .map((result, index) => `
            <button
                type="button"
                class="address-autocomplete-option${index === activeIndex ? ' is-active' : ''}"
                id="${escapeHtml(optionId(input, index))}"
                role="option"
                data-index="${index}"
                aria-selected="${index === activeIndex ? 'true' : 'false'}"
            >
                <span class="address-autocomplete-option-icon" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                <span class="address-autocomplete-option-copy">${escapeHtml(result.label)}</span>
            </button>
        `)
        .join('');
    menu.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    input.setAttribute('aria-activedescendant', optionId(input, activeIndex));
    menu.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' });
}

function collectAddressFields(root) {
    const fields = new Set();

    root.querySelectorAll('[data-address-autocomplete]').forEach((field) => {
        if (field.matches('input, textarea')) {
            fields.add(field);
        }
    });

    root.querySelectorAll('label').forEach((label) => {
        const text = (label.textContent ?? '').replace(/\s+/g, ' ').trim();
        if (!ADDRESS_LABEL.test(text)) {
            return;
        }

        const forId = label.getAttribute('for');
        const input = forId
            ? root.querySelector(`#${CSS.escape(forId)}`)
            : label.querySelector('input, textarea');

        if (input?.matches('input, textarea')) {
            fields.add(input);
        }
    });

    return [...fields];
}

export function bindAddressAutocomplete(input, options = {}) {
    if (!input || input.dataset.addressBound === '1') {
        return input?._addressAutocomplete ?? null;
    }

    input.dataset.addressBound = '1';
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('autocorrect', 'off');
    input.setAttribute('autocapitalize', 'off');
    input.setAttribute('spellcheck', 'false');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    const wrap = ensureWrap(input);
    const menu = ensureMenu(wrap);
    const listId = `${input.id || 'address'}-suggestions`;
    menu.id = listId;
    input.setAttribute('aria-controls', listId);

    const controller = new AbortController();
    const { signal } = controller;
    let debounceId = 0;
    let requestToken = 0;
    let results = [];
    let activeIndex = 0;
    let selectedAddress = '';
    let skipSearch = false;

    const currentValue = () => String(input.value ?? '').trim();

    const isCommittedSelection = () => selectedAddress !== '' && currentValue() === selectedAddress;

    const closeSuggestions = () => {
        window.clearTimeout(debounceId);
        requestToken += 1;
        results = [];
        activeIndex = 0;
        hideMenu(input, menu);
    };

    const applySelection = (item) => {
        if (!item) {
            return;
        }

        const nextValue = item.address || item.label;
        selectedAddress = String(nextValue ?? '').trim();
        skipSearch = true;
        input.value = nextValue;
        closeSuggestions();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        skipSearch = false;

        if (typeof options.onSelect === 'function') {
            options.onSelect(item);
        }

        input.dispatchEvent(new CustomEvent('address-autocomplete:select', {
            bubbles: true,
            detail: item,
        }));
    };

    const runSearch = async (query) => {
        const trimmed = String(query ?? '').trim();
        const token = ++requestToken;

        if (trimmed.length < MIN_QUERY_LENGTH || (selectedAddress !== '' && trimmed === selectedAddress)) {
            results = [];
            hideMenu(input, menu);
            return;
        }

        renderMenu(input, menu, [], { emptyText: 'Searching addresses…' });

        try {
            const nextResults = await searchAddresses(trimmed);
            if (token !== requestToken || currentValue() !== trimmed || isCommittedSelection()) {
                return;
            }

            results = nextResults;
            activeIndex = 0;
            if (!results.length) {
                renderMenu(input, menu, [], { emptyText: 'No matching Philippine addresses.' });
                return;
            }

            renderMenu(input, menu, results, { activeIndex });
        } catch {
            if (token !== requestToken || isCommittedSelection()) {
                return;
            }
            results = [];
            renderMenu(input, menu, [], { emptyText: 'Address search failed. Try again.' });
        }
    };

    const scheduleSearch = () => {
        if (skipSearch) {
            return;
        }

        window.clearTimeout(debounceId);

        if (isCommittedSelection()) {
            closeSuggestions();
            return;
        }

        selectedAddress = '';
        debounceId = window.setTimeout(() => {
            if (isCommittedSelection()) {
                closeSuggestions();
                return;
            }
            runSearch(input.value);
        }, options.debounceMs ?? DEBOUNCE_MS);
    };

    input.addEventListener('input', scheduleSearch, { signal });
    input.addEventListener('focus', () => {
        if (isCommittedSelection()) {
            closeSuggestions();
            return;
        }
        if (results.length) {
            renderMenu(input, menu, results, { activeIndex });
            return;
        }
        if (currentValue().length >= MIN_QUERY_LENGTH) {
            scheduleSearch();
        }
    }, { signal });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideMenu(input, menu);
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (menu.hidden || !results.length) {
                return;
            }
            event.preventDefault();
            const delta = event.key === 'ArrowDown' ? 1 : -1;
            activeIndex = (activeIndex + delta + results.length) % results.length;
            renderMenu(input, menu, results, { activeIndex });
            return;
        }

        if (event.key === 'Enter' && !menu.hidden && results[activeIndex]) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            applySelection(results[activeIndex]);
        }
    }, { signal });

    menu.addEventListener('mousedown', (event) => {
        const button = event.target.closest('[data-index]');
        if (!button) {
            return;
        }
        event.preventDefault();
        applySelection(results[Number(button.dataset.index)]);
    }, { signal });

    document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) {
            hideMenu(input, menu);
        }
    }, { signal });

    const api = {
        destroy() {
            window.clearTimeout(debounceId);
            controller.abort();
            hideMenu(input, menu);
            delete input.dataset.addressBound;
            delete input._addressAutocomplete;
        },
        searchNow() {
            window.clearTimeout(debounceId);
            return runSearch(input.value);
        },
    };

    input._addressAutocomplete = api;
    return api;
}

export function bindAddressFields(root = document, options = {}) {
    return collectAddressFields(root).map((field) => bindAddressAutocomplete(field, options));
}

export function destroyAddressAutocompletes(root = document) {
    root.querySelectorAll('[data-address-bound="1"], [data-address-autocomplete]').forEach((field) => {
        field._addressAutocomplete?.destroy();
    });
}
