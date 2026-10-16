<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $card->title }} · DAPE-MA</title>
    <meta name="description" content="{{ $card->description }}">
    <link rel="canonical" href="{{ $card->canonicalUrl }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('ddb.svg') }}">

    <meta property="og:type" content="article">
    <meta property="og:site_name" content="DAPE-MA">
    <meta property="og:title" content="{{ $card->title }}">
    <meta property="og:description" content="{{ $card->description }}">
    <meta property="og:url" content="{{ $card->canonicalUrl }}">
    <meta property="og:image" content="{{ $card->imageUrl }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $card->title }}">
    <meta name="twitter:description" content="{{ $card->description }}">
    <meta name="twitter:image" content="{{ $card->imageUrl }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    data-page="public-share"
    data-app-path="{{ $card->appPath }}"
    data-app-package="com.example.dape_ma_mobile"
    class="min-h-dvh bg-[#123a60] text-white"
>
    <div class="relative isolate flex min-h-dvh flex-col bg-gradient-to-br from-[#055498] via-[#123a60] to-[#055498]">
        <div class="pointer-events-none absolute inset-0 opacity-10">
            <img src="{{ asset('ddb.svg') }}" alt="" class="absolute -right-10 top-16 h-64 w-64 object-contain sm:h-80 sm:w-80">
        </div>

        <header class="relative z-10 flex items-center gap-3 px-5 pt-6 sm:px-6">
            <img src="{{ asset('ddb.svg') }}" alt="DDB" class="h-10 w-10 rounded-xl bg-white object-contain p-1 shadow-lg">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#FBD116]">DAPE-MA</p>
                <p class="text-sm font-semibold text-white/90">Dangerous Drugs Board</p>
            </div>
        </header>

        <main class="relative z-10 mx-auto flex w-full max-w-[420px] flex-1 flex-col px-4 pb-[17.5rem] pt-6 sm:max-w-[480px] sm:px-5 sm:pb-56">
            <article class="overflow-hidden rounded-[24px] bg-white shadow-2xl shadow-black/30">
                <div class="relative aspect-video bg-gradient-to-br from-[#055498] to-[#123a60]">
                    <img
                        src="{{ $card->imageUrl }}"
                        alt=""
                        class="h-full w-full object-cover"
                    >
                    <span class="absolute left-3 top-3 rounded-full bg-[#FBD116] px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-[#123a60]">
                        {{ $card->typeLabel }}
                    </span>
                </div>
                <div class="px-5 py-5 sm:px-6">
                    <h1 class="text-[22px] font-bold leading-tight text-[#0A0A0A] sm:text-2xl">{{ $card->title }}</h1>
                    @if ($card->meta)
                        <p class="mt-2 text-xs font-semibold text-[#055498]">{{ $card->meta }}</p>
                    @endif
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $card->description }}</p>
                    <p class="mt-4 text-sm font-semibold text-[#123a60]">Open in the DAPE-MA app to read the full {{ strtolower($card->typeLabel) }}.</p>
                </div>
            </article>
        </main>

        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-white/10 bg-[#123a60]/95 px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur-md sm:px-6">
            <div class="mx-auto flex w-full max-w-[480px] flex-col gap-2.5">
                <button
                    id="share-open-app"
                    type="button"
                    class="flex min-h-11 w-full items-center justify-center rounded-xl bg-gradient-to-r from-[#055498] to-[#123a60] px-4 text-sm font-bold text-white shadow-lg ring-1 ring-white/20"
                >
                    Open in DAPE-MA
                </button>
                <a
                    href="{{ url('/download/dape-ma.apk') }}"
                    class="flex min-h-11 w-full items-center justify-center rounded-xl bg-[#FBD116] px-4 text-sm font-bold text-[#123a60]"
                >
                    Get the app (Android)
                </a>
                <div class="grid grid-cols-2 gap-2">
                    <span
                        class="flex min-h-11 cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-black/40 px-2 text-xs font-semibold text-white/70 opacity-70"
                        aria-disabled="true"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.365 1.43c0 1.14-.42 2.2-1.2 3.02-.86.9-2.28 1.6-3.5 1.5-.14-1.1.4-2.26 1.2-3.1.86-.92 2.34-1.6 3.5-1.42zM20.5 17.2c-.72 1.66-1.6 3.2-2.86 3.22-1.24.04-1.64-.74-3.06-.74s-1.86.72-3.04.78c-1.22.06-2.16-1.48-2.9-3.12-1.5-3.3-.4-8.18 2.1-8.28 1.16-.04 2.02.78 2.72.78.68 0 1.74-.96 2.94-.82.5.02 1.9.2 2.8 1.52-2.4 1.32-2.02 4.76.3 5.66z"/></svg>
                        App Store · Soon
                    </span>
                    <span
                        class="flex min-h-11 cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-black/40 px-2 text-xs font-semibold text-white/70 opacity-70"
                        aria-disabled="true"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.6 2.4 13.2 12 3.6 21.6A2.4 2.4 0 0 1 3 19.8V4.2c0-.7.22-1.34.6-1.8zm11.1 11.1 2.18 2.18-8.4 4.86 6.22-7.04zm2.9-2.9 2.54 1.46c.86.5.86 1.74 0 2.24l-2.54 1.46L14.4 12l3.2-1.4zM8.48 3.46l8.4 4.86-2.18 2.18L8.48 3.46z"/></svg>
                        Google Play · Soon
                    </span>
                </div>
                <p class="pb-1 text-center text-[11px] text-white/60">DAPE-MA · Dangerous Drugs Board</p>
            </div>
        </div>
    </div>
</body>
</html>
