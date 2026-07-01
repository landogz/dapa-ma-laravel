import axios from 'axios';
import Swal from 'sweetalert2';
import { ensurePublicAuth, hydratePublicAuth } from './auth';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let postId = null;
let currentPost = null;
let selectedRating = 5;

export function initPublicPostShow() {
    const root = document.getElementById('public-post-app');

    if (!root) {
        return;
    }

    postId = Number(root.dataset.postId);
    hydratePublicAuth();

    const rateButton = document.getElementById('public-rate-button');
    rateButton?.addEventListener('click', openRateModal);

    loadPost();
    loadReviews();
}

async function loadPost() {
    const titleEl = document.getElementById('public-post-title');
    const bodyEl = document.getElementById('public-post-body');
    const categoryEl = document.getElementById('public-post-category');
    const authorEl = document.getElementById('public-post-author');
    const mediaEl = document.getElementById('public-post-media');
    const summaryEl = document.getElementById('public-rating-summary');

    try {
        const response = await axios.get(`/posts/${postId}`);
        currentPost = response.data?.data ?? null;

        if (!currentPost) {
            throw new Error('Post not found');
        }

        if (titleEl) {
            titleEl.textContent = currentPost.title ?? 'Post';
        }

        if (bodyEl) {
            bodyEl.innerHTML = currentPost.body ?? '';
        }

        if (categoryEl) {
            categoryEl.textContent = currentPost.category?.name ?? '';
            categoryEl.classList.toggle('hidden', !currentPost.category?.name);
        }

        if (authorEl) {
            authorEl.textContent = currentPost.author?.name ?? 'DAPE-MA';
        }

        if (mediaEl) {
            mediaEl.innerHTML = '';

            if (currentPost.media_url) {
                mediaEl.innerHTML = `<img src="${escapeHtml(currentPost.media_url)}" alt="" class="w-full rounded-2xl object-cover">`;
            } else if (currentPost.youtube_url) {
                const videoId = extractYoutubeId(currentPost.youtube_url);
                if (videoId) {
                    mediaEl.innerHTML = `
                        <div class="aspect-video overflow-hidden rounded-2xl bg-slate-100">
                            <iframe class="h-full w-full" src="https://www.youtube.com/embed/${videoId}" title="YouTube video" allowfullscreen></iframe>
                        </div>`;
                }
            }
        }

        renderRatingSummary(summaryEl, currentPost);
    } catch (error) {
        if (titleEl) {
            titleEl.textContent = 'Post unavailable';
        }
        showErrorToast(error.response?.data?.message ?? 'Unable to load this post.', 'Error');
    }
}

async function loadReviews() {
    const listEl = document.getElementById('public-reviews-list');

    if (!listEl) {
        return;
    }

    try {
        const response = await axios.get(`/posts/${postId}/reviews`);
        const data = response.data?.data ?? {};
        const summary = data.summary ?? {};
        const reviews = Array.isArray(data.reviews) ? data.reviews : [];

        renderRatingSummary(document.getElementById('public-rating-summary'), {
            average_rating: summary.average_rating ?? 0,
            reviews_count: summary.reviews_count ?? 0,
            user_rating: summary.user_review?.rating ?? null,
        });

        if (reviews.length === 0) {
            listEl.innerHTML = '<p class="text-sm text-slate-500 italic">No ratings yet. Be the first to rate this post.</p>';
            return;
        }

        listEl.innerHTML = reviews.map((review) => `
            <article class="rounded-2xl bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-semibold text-slate-900">${escapeHtml(review.user?.name ?? 'User')}</p>
                    <div class="text-amber-500 text-sm">${renderStars(review.rating)}</div>
                </div>
                ${review.comment ? `<p class="mt-2 text-sm text-slate-700">${escapeHtml(review.comment)}</p>` : ''}
            </article>
        `).join('');
    } catch (error) {
        listEl.innerHTML = '<p class="text-sm text-red-600">Unable to load ratings.</p>';
    }
}

function renderRatingSummary(element, post) {
    if (!element) {
        return;
    }

    const average = Number(post.average_rating ?? 0);
    const count = Number(post.reviews_count ?? 0);
    const userRating = post.user_rating ?? null;

    element.innerHTML = `
        <div class="flex flex-wrap items-center gap-2">
            <div class="text-amber-500 text-lg">${renderStars(Math.round(average))}</div>
            <span class="text-sm text-slate-600">
                ${count > 0 ? `${average.toFixed(1)} · ${count} ${count === 1 ? 'rating' : 'ratings'}` : 'No ratings yet'}
            </span>
        </div>
        ${userRating ? `<p class="mt-2 text-sm font-semibold text-[#055498]">You rated this ${userRating} star${userRating === 1 ? '' : 's'}</p>` : ''}
    `;
}

async function openRateModal() {
    const authed = await ensurePublicAuth();

    if (!authed) {
        return;
    }

    selectedRating = currentPost?.user_rating ?? 5;

    const { value: submitted } = await Swal.fire({
        title: 'Rate this content',
        html: `
            <p class="mb-3 text-sm text-slate-600">Tap a star to choose your rating.</p>
            <div id="public-rate-stars" class="mb-4 flex justify-center gap-1 text-3xl text-amber-500">
                ${renderInteractiveStars(selectedRating)}
            </div>
            <textarea id="public-rate-comment" class="swal2-textarea" placeholder="Comment (optional)">${escapeHtml(currentPost?.user_review_comment ?? '')}</textarea>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Submit rating',
        confirmButtonColor: '#055498',
        didOpen: () => {
            document.querySelectorAll('[data-rate-star]').forEach((button) => {
                button.addEventListener('click', () => {
                    selectedRating = Number(button.getAttribute('data-rate-star'));
                    const container = document.getElementById('public-rate-stars');
                    if (container) {
                        container.innerHTML = renderInteractiveStars(selectedRating);
                        bindStarButtons();
                    }
                });
            });
        },
        preConfirm: () => {
            const comment = document.getElementById('public-rate-comment')?.value.trim() ?? '';

            if (selectedRating < 1 || selectedRating > 5) {
                Swal.showValidationMessage('Please choose a rating from 1 to 5 stars.');
                return false;
            }

            return { rating: selectedRating, comment };
        },
    });

    if (!submitted) {
        return;
    }

    try {
        const response = await axios.post('/reviews', {
            target_type: 'post',
            target_id: postId,
            rating: submitted.rating,
            comment: submitted.comment || null,
        });

        const summary = response.data?.data?.summary ?? {};
        currentPost = {
            ...currentPost,
            average_rating: summary.average_rating ?? 0,
            reviews_count: summary.reviews_count ?? 0,
            user_rating: summary.user_review?.rating ?? submitted.rating,
            user_review_comment: submitted.comment,
        };

        renderRatingSummary(document.getElementById('public-rating-summary'), currentPost);
        await loadReviews();
        showSuccessToast('Your rating was saved.', 'Thank you');
    } catch (error) {
        showErrorToast(error.response?.data?.message ?? 'Unable to submit rating.', 'Error');
    }
}

function bindStarButtons() {
    document.querySelectorAll('[data-rate-star]').forEach((button) => {
        button.addEventListener('click', () => {
            selectedRating = Number(button.getAttribute('data-rate-star'));
            const container = document.getElementById('public-rate-stars');
            if (container) {
                container.innerHTML = renderInteractiveStars(selectedRating);
                bindStarButtons();
            }
        });
    });
}

function renderStars(rating) {
    return Array.from({ length: 5 }, (_, index) => (
        index < rating ? '★' : '☆'
    )).join('');
}

function renderInteractiveStars(rating) {
    return Array.from({ length: 5 }, (_, index) => {
        const value = index + 1;
        const filled = value <= rating;
        return `<button type="button" data-rate-star="${value}" class="transition hover:scale-110">${filled ? '★' : '☆'}</button>`;
    }).join('');
}

function extractYoutubeId(url) {
    const match = String(url).match(/(?:youtu\.be\/|v=)([\w-]{11})/);
    return match ? match[1] : null;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}
