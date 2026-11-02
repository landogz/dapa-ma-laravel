const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const metricFrames = new WeakMap();

export function initAnalyticsMotion() {
    const shell = document.querySelector('[data-analytics-shell]');
    if (!shell) {
        return;
    }

    requestAnimationFrame(() => {
        shell.classList.add('is-motion-on');
    });
}

export function writeAnimatedMetric(el, nextText) {
    if (!el) {
        return;
    }

    const next = String(nextText ?? '');
    cancelMetricFrame(el);
    if (reducedMotion()) {
        el.textContent = next;
        return;
    }

    const parsedNext = parseMetric(next);
    const parsedFrom = parseMetric(el.textContent);
    if (!parsedNext) {
        el.textContent = next;
        return;
    }

    const from = parsedFrom?.value ?? 0;
    const to = parsedNext.value;
    if (from === to) {
        el.textContent = next;
        return;
    }

    const started = performance.now();
    const duration = 720;
    const tick = (now) => {
        const progress = Math.min(1, (now - started) / duration);
        const eased = 1 - ((1 - progress) ** 3);
        el.textContent = parsedNext.format(from + ((to - from) * eased));
        if (progress < 1) {
            metricFrames.set(el, window.requestAnimationFrame(tick));
            return;
        }
        metricFrames.delete(el);
        el.textContent = next;
    };

    metricFrames.set(el, window.requestAnimationFrame(tick));
}

function cancelMetricFrame(el) {
    const frame = metricFrames.get(el);
    if (frame) {
        window.cancelAnimationFrame(frame);
        metricFrames.delete(el);
    }
}

function parseMetric(text) {
    const raw = String(text ?? '').trim();
    if (!raw || raw === '—' || raw === '--' || raw === '-') {
        return null;
    }

    const percent = raw.endsWith('%');
    const seconds = /^-?\d[\d,]*s$/.test(raw);
    const numeric = raw.replace(/[^0-9.-]/g, '');
    if (numeric === '' || numeric === '-' || Number.isNaN(Number(numeric))) {
        return null;
    }

    return {
        value: Number(numeric),
        format(value) {
            if (percent) {
                return `${Number(value).toLocaleString(undefined, {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1,
                })}%`;
            }

            const rounded = Math.round(value);
            const formatted = rounded.toLocaleString();
            return seconds ? `${formatted}s` : formatted;
        },
    };
}
