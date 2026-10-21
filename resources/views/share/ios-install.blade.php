<!DOCTYPE html>
<html lang="en" class="overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Install DAPE-MA on iPhone</title>
    <meta name="description" content="Sideload the DAPE-MA iPhone app with a computer, a USB cable, and a free Apple ID. Reinstall every 7 days until the App Store release.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('ddb.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh overflow-x-hidden bg-[#123a60] font-sans text-white antialiased">
    <div class="relative isolate min-h-dvh bg-gradient-to-br from-[#055498] via-[#123a60] to-[#055498]">
        <header class="relative z-10">
            <div class="mx-auto flex w-full max-w-3xl items-center gap-3 px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-3 sm:px-6 sm:pt-6">
                <img src="{{ asset('ddb.svg') }}" alt="DDB" class="h-9 w-9 shrink-0 rounded-xl bg-white object-contain p-1 shadow-lg sm:h-10 sm:w-10">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#FBD116]">DAPE-MA</p>
                    <p class="truncate text-sm font-semibold text-white/90">Dangerous Drugs Board</p>
                </div>
            </div>
        </header>

        <main class="relative z-10 mx-auto w-full max-w-3xl px-4 pb-12 pt-2 sm:px-6 sm:pb-16 sm:pt-4">
            <article class="rounded-2xl bg-white p-5 text-[#0A0A0A] shadow-2xl shadow-black/30 sm:rounded-3xl sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#055498]">iPhone</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Install on iPhone</h1>
                <p class="mt-3 text-sm leading-relaxed text-slate-600 sm:text-[15px]">
                    Requires a computer, a USB cable, and a free Apple ID. The app must be reinstalled every 7 days until it is on the App Store.
                </p>

                <div class="mt-5 grid grid-cols-1 gap-2 min-[420px]:grid-cols-2">
                    @if ($ipaReady)
                        <a
                            href="{{ $ipaUrl }}"
                            class="flex min-h-11 items-center justify-center rounded-xl bg-[#FBD116] px-3 text-sm font-bold text-[#123a60] sm:min-h-12"
                        >
                            Download DAPE-MA.ipa
                        </a>
                    @else
                        <span class="flex min-h-11 cursor-not-allowed items-center justify-center rounded-xl bg-slate-100 px-3 text-sm font-bold text-slate-500 sm:min-h-12">
                            IPA coming soon
                        </span>
                    @endif
                    <a
                        href="https://sideloadly.io/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex min-h-11 items-center justify-center rounded-xl bg-[#055498] px-3 text-sm font-bold text-white sm:min-h-12"
                    >
                        Download Sideloadly
                    </a>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-500">
                    Sideloadly is the current Impactor-style installer for Windows and Mac. Download the IPA on the computer you will use to install. If the file appears as a .zip, rename it to dape-ma.ipa — do not unzip it.
                </p>

                <ol class="mt-6 space-y-3">
                    <li class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#055498]">Step 1</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Download Sideloadly on your computer</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Open sideloadly.io on a Windows PC or Mac and install Sideloadly. Cydia Impactor no longer works with current Apple IDs.</p>
                        <p class="mt-3 text-sm font-semibold text-slate-900">If Mac says Apple could not verify Sideloadly</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">That warning is normal. Sideloadly is not notarized by Apple. Click Done, then open System Settings → Privacy &amp; Security. Scroll to Security and tap Open Anyway next to Sideloadly. Confirm Open Anyway. If that button is missing, Control-click Sideloadly.app and choose Open.</p>
                    </li>
                    <li class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#055498]">Step 2</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Plug in the iPhone</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Unlock the iPhone, tap Trust This Computer, and enter the passcode if asked.</p>
                    </li>
                    <li class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#055498]">Step 3</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Sign in with your Apple ID</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Open Sideloadly, enter a free Apple ID, and type the verification code shown on the iPhone or in Settings.</p>
                    </li>
                    <li class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#055498]">Step 4</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Install dape-ma.ipa</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Select the downloaded DAPE-MA IPA (not a .zip or unzipped folder), choose your iPhone, then click Start. Wait until Sideloadly says the install finished.</p>
                    </li>
                    <li class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#055498]">Step 5</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Turn on Developer Mode</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">On the iPhone, open Settings → Privacy &amp; Security → Developer Mode. Turn it on and restart when asked.</p>
                    </li>
                    <li class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#055498]">Step 6</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Trust the app</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Open Settings → General → VPN &amp; Device Management. Tap your Apple ID, then tap Trust. Open DAPE-MA from the Home Screen.</p>
                    </li>
                </ol>

                <p class="mt-6 text-sm leading-6 text-slate-600">
                    Free Apple IDs expire after 7 days. Repeat steps 2–4 to refresh the app. A paid Apple Developer account lasts 1 year. App Store listing is still coming.
                </p>
            </article>
        </main>
    </div>
</body>
</html>
