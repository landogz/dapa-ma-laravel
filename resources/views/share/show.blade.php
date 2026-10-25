<!DOCTYPE html>
<html lang="en" class="overflow-x-hidden">
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
    <meta property="og:image:secure_url" content="{{ $card->imageUrl }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $card->title }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $card->title }}">
    <meta name="twitter:description" content="{{ $card->description }}">
    <meta name="twitter:image" content="{{ $card->imageUrl }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .share-fallback-logo {
            width: 6.5rem;
            height: 6.5rem;
        }
        @media (min-width: 640px) {
            .share-fallback-logo { width: 8rem; height: 8rem; }
        }
        @media (min-width: 1024px) {
            .share-fallback-logo { width: 10rem; height: 10rem; }
        }
    </style>
</head>
<body
    data-page="public-share"
    data-app-path="{{ $card->appPath }}"
    data-app-package="com.example.dape_ma_mobile"
    class="min-h-dvh overflow-x-hidden bg-[#123a60] font-sans text-white antialiased"
>
    <div class="relative isolate flex min-h-dvh flex-col bg-gradient-to-br from-[#055498] via-[#123a60] to-[#055498]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden opacity-10">
            <img src="{{ asset('ddb.svg') }}" alt="" class="absolute -right-10 top-16 h-40 w-40 object-contain sm:h-64 sm:w-64 lg:h-80 lg:w-80">
        </div>

        <header class="relative z-10 shrink-0">
            <div class="mx-auto flex w-full max-w-6xl items-center gap-3 px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-3 sm:px-6 sm:pt-6 lg:px-8">
                <img src="{{ asset('ddb.svg') }}" alt="DDB" class="h-9 w-9 shrink-0 rounded-xl bg-white object-contain p-1 shadow-lg sm:h-10 sm:w-10">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#FBD116]">DAPE-MA</p>
                    <p class="truncate text-sm font-semibold text-white/90">Dangerous Drugs Board</p>
                </div>
            </div>
        </header>

        <main class="relative z-10 mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center px-4 py-3 sm:px-6 sm:py-6 lg:px-8 lg:py-10 max-lg:pb-[12.5rem] [@media(max-height:500px)]:max-lg:pb-[7.25rem]">
            <article class="overflow-hidden rounded-2xl bg-white shadow-2xl shadow-black/30 sm:rounded-3xl lg:grid lg:grid-cols-2 lg:items-stretch [&>*]:min-w-0">
                <div class="relative aspect-[2/1] min-h-[9.5rem] overflow-hidden bg-gradient-to-br from-[#055498] to-[#123a60] sm:aspect-[16/7] sm:min-h-[12rem] lg:aspect-auto lg:min-h-[22rem]">
                    @if ($card->imageIsLogo)
                        <div class="flex h-full w-full items-center justify-center p-6 sm:p-8">
                            <img
                                src="{{ $card->imageSrc }}"
                                alt="Dangerous Drugs Board"
                                class="share-fallback-logo rounded-full bg-white object-contain p-1 shadow-xl"
                            >
                        </div>
                    @else
                        <img
                            src="{{ $card->imageSrc }}"
                            alt="{{ $card->title }}"
                            class="h-full w-full object-cover object-center"
                        >
                    @endif
                    <span class="absolute left-3 top-3 rounded-full bg-[#FBD116] px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-[#123a60]">
                        {{ $card->typeLabel }}
                    </span>
                </div>
                <div class="flex min-w-0 flex-col justify-center px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-10">
                    <h1 class="text-xl font-bold leading-tight break-words text-[#0A0A0A] sm:text-2xl lg:text-[28px] lg:leading-snug">
                        {{ $card->title }}
                    </h1>
                    @if ($card->meta)
                        <p class="mt-2 text-xs font-semibold leading-5 break-words text-[#055498] sm:text-sm">{{ $card->meta }}</p>
                    @endif
                    <p class="mt-3 text-sm leading-relaxed break-words text-slate-600 sm:text-[15px]">{{ $card->description }}</p>
                    <p class="mt-4 text-sm font-semibold text-[#123a60]">
                        Open in the DAPE-MA app to read the full {{ strtolower($card->typeLabel) }}.
                    </p>
                    <div class="mt-6 hidden lg:block">
                        @include('share.actions', ['onLight' => true])
                    </div>
                </div>
            </article>
        </main>

        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-white/10 bg-[#123a60]/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur-md sm:px-6 lg:hidden [@media(max-height:500px)]:[&_[data-share-extra]]:hidden">
            <div class="mx-auto w-full max-w-3xl">
                @include('share.actions', ['showCredit' => true])
            </div>
        </div>
    </div>
</body>
</html>
