<div class="flex flex-col gap-2">
    <div class="grid grid-cols-1 gap-2 min-[380px]:grid-cols-2">
        <button
            type="button"
            data-share-open-app
            class="flex min-h-11 w-full items-center justify-center rounded-xl bg-gradient-to-r from-[#055498] to-[#123a60] px-3 text-sm font-bold text-white shadow-lg ring-1 ring-white/20 sm:min-h-12"
        >
            Open in DAPE-MA
        </button>
        <a
            href="{{ url('/download/dape-ma.apk') }}"
            class="flex min-h-11 w-full items-center justify-center rounded-xl bg-[#FBD116] px-3 text-sm font-bold text-[#123a60] sm:min-h-12"
        >
            Get the app (Android)
        </a>
    </div>
    <div class="grid grid-cols-2 gap-2" data-share-extra>
        <span
            class="flex min-h-11 cursor-not-allowed items-center justify-center gap-1.5 rounded-xl px-2 text-center text-[11px] font-semibold opacity-70 sm:min-h-12 sm:text-xs {{ !empty($onLight) ? 'bg-slate-100 text-slate-500' : 'bg-black/40 text-white/70' }}"
            aria-disabled="true"
        >
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.365 1.43c0 1.14-.42 2.2-1.2 3.02-.86.9-2.28 1.6-3.5 1.5-.14-1.1.4-2.26 1.2-3.1.86-.92 2.34-1.6 3.5-1.42zM20.5 17.2c-.72 1.66-1.6 3.2-2.86 3.22-1.24.04-1.64-.74-3.06-.74s-1.86.72-3.04.78c-1.22.06-2.16-1.48-2.9-3.12-1.5-3.3-.4-8.18 2.1-8.28 1.16-.04 2.02.78 2.72.78.68 0 1.74-.96 2.94-.82.5.02 1.9.2 2.8 1.52-2.4 1.32-2.02 4.76.3 5.66z"/></svg>
            App Store · Soon
        </span>
        <span
            class="flex min-h-11 cursor-not-allowed items-center justify-center gap-1.5 rounded-xl px-2 text-center text-[11px] font-semibold opacity-70 sm:min-h-12 sm:text-xs {{ !empty($onLight) ? 'bg-slate-100 text-slate-500' : 'bg-black/40 text-white/70' }}"
            aria-disabled="true"
        >
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.6 2.4 13.2 12 3.6 21.6A2.4 2.4 0 0 1 3 19.8V4.2c0-.7.22-1.34.6-1.8zm11.1 11.1 2.18 2.18-8.4 4.86 6.22-7.04zm2.9-2.9 2.54 1.46c.86.5.86 1.74 0 2.24l-2.54 1.46L14.4 12l3.2-1.4zM8.48 3.46l8.4 4.86-2.18 2.18L8.48 3.46z"/></svg>
            Google Play · Soon
        </span>
    </div>
    @if (!empty($showCredit))
        <p class="pb-0.5 text-center text-[11px] {{ !empty($onLight) ? 'text-slate-400' : 'text-white/60' }}" data-share-extra>DAPE-MA · Dangerous Drugs Board</p>
    @endif
</div>
