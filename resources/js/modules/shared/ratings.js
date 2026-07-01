export function getPostRatingMetrics(post = {}) {
    const average = Number(post.average_rating ?? post.reviews_avg_rating ?? 0);
    const count = Number(post.reviews_count ?? 0);

    return { average, count };
}

export function renderStars(rating) {
    const value = Math.max(0, Math.min(5, Math.round(Number(rating) || 0)));

    return Array.from({ length: 5 }, (_, index) => (
        index < value ? '★' : '☆'
    )).join('');
}

export function renderRatingBadge(post = {}, { compact = false } = {}) {
    const { average, count } = getPostRatingMetrics(post);

    if (count === 0) {
        return '<span class="text-xs text-slate-400">No ratings</span>';
    }

    const stars = renderStars(Math.round(average));
    const label = `${average.toFixed(1)} · ${count}`;

    if (compact) {
        return `
            <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-600" title="${label}">
                <span aria-hidden="true">${stars}</span>
                <span class="text-slate-600">${average.toFixed(1)}</span>
            </span>
        `;
    }

    return `
        <div class="flex flex-col gap-0.5">
            <span class="text-sm text-amber-500 leading-none" aria-hidden="true">${stars}</span>
            <span class="text-xs text-slate-500">${average.toFixed(1)} · ${count} ${count === 1 ? 'rating' : 'ratings'}</span>
        </div>
    `;
}
