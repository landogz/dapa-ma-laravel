<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DAPE-MA Post</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="min-h-screen bg-[#F9FAFB] text-[#0A0A0A]"
    data-page="public-post-show"
>
    <div id="public-post-app" data-post-id="{{ $postId }}" class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
        <header class="mb-6 flex items-center justify-between gap-4">
            <a href="/" class="text-sm font-semibold text-[#055498]">← DAPE-MA</a>
            <button
                id="public-rate-button"
                type="button"
                class="inline-flex items-center gap-2 rounded-full bg-[#055498] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#123a60]"
            >
                <span aria-hidden="true">★</span>
                Rate & Review
            </button>
        </header>

        <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <p id="public-post-author" class="text-sm font-semibold text-[#055498]">DAPE-MA</p>
                <span id="public-post-category" class="mt-2 inline-flex rounded-full bg-[#055498]/10 px-3 py-1 text-xs font-semibold text-[#055498] hidden"></span>
                <h1 id="public-post-title" class="mt-3 text-2xl font-bold text-slate-900">Loading post...</h1>
            </div>

            <div class="space-y-5 px-5 py-5 sm:px-6">
                <div id="public-post-body" class="prose prose-slate max-w-none text-slate-700"></div>
                <div id="public-post-media"></div>

                <section class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Ratings</h2>
                    <div id="public-rating-summary" class="mt-3"></div>
                </section>

                <section>
                    <h2 class="mb-3 text-lg font-semibold text-slate-900">Community reviews</h2>
                    <div id="public-reviews-list" class="space-y-3">
                        <p class="text-sm text-slate-500">Loading ratings...</p>
                    </div>
                </section>
            </div>
        </article>
    </div>
</body>
</html>
