import axios from 'axios';

const MASK = '****';

let words = [];
let loadPromise = null;
let version = '';

function buildPattern(list) {
    if (!list.length) {
        return null;
    }

    const sorted = [...list].sort((a, b) => b.length - a.length);
    const escaped = sorted.map((word) => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    return new RegExp(`(?<![\\p{L}\\p{N}_])(?:${escaped.join('|')})(?![\\p{L}\\p{N}_])`, 'giu');
}

let pattern = null;

function syncPattern() {
    pattern = buildPattern(words);
}

/**
 * Prefetch the public blocked-word catalog for admin masking.
 */
export function ensureBlockedWordsLoaded({ force = false } = {}) {
    if (!force && loadPromise) {
        return loadPromise;
    }

    loadPromise = axios.get('/blocked-words')
        .then(({ data }) => {
            const nextVersion = String(data?.data?.version ?? '');
            const nextWords = Array.isArray(data?.data?.words)
                ? data.data.words.map((word) => String(word || '').trim().toLowerCase()).filter(Boolean)
                : [];

            if (force || nextVersion !== version || nextWords.join('\0') !== words.join('\0')) {
                version = nextVersion;
                words = nextWords;
                syncPattern();
            }

            return words;
        })
        .catch(() => {
            // Keep whatever we already have so UI still works offline.
            return words;
        });

    return loadPromise;
}

export function maskBlockedWords(text) {
    const source = text == null ? '' : String(text);
    if (!source || !pattern) {
        return source;
    }

    return source.replace(pattern, MASK);
}

/**
 * Mask whole words in HTML text nodes only (leave tags/attributes alone).
 */
export function maskBlockedWordsHtml(html) {
    const source = html == null ? '' : String(html);
    if (!source || !pattern) {
        return source;
    }

    return source.replace(/(^|>)([^<]*)/g, (_, prefix, text) => `${prefix}${maskBlockedWords(text)}`);
}

export function getBlockedWordCount() {
    return words.length;
}
