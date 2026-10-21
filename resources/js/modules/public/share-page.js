import Swal from 'sweetalert2';

function userAgent() {
    return navigator.userAgent || '';
}

function isAndroid() {
    return /android/i.test(userAgent());
}

function isApplePhoneOrTablet() {
    const ua = userAgent();
    if (/iPhone|iPod|iPad/i.test(ua)) {
        return true;
    }

    const looksLikeIpadOs = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    if (!looksLikeIpadOs) {
        return false;
    }

    return window.matchMedia('(hover: none) and (pointer: coarse)').matches;
}

function apkUrl() {
    const link = document.querySelector('a[href*="/download/dape-ma.apk"]');
    if (link?.href) {
        return link.href;
    }

    return new URL('/download/dape-ma.apk', window.location.origin).href;
}

async function showOpenInAppHelp() {
    const result = await Swal.fire({
        icon: 'info',
        title: 'Open this in the DAPE-MA app',
        text: 'This computer cannot open the DAPE-MA app. Use a phone with the app installed, or download the Android app below. You can keep reading this page in the browser.',
        confirmButtonText: 'Get the Android app',
        showCancelButton: true,
        cancelButtonText: 'Stay on this page',
        confirmButtonColor: '#055498',
        cancelButtonColor: '#6B7280',
        width: 'min(28rem, calc(100vw - 1.5rem))',
    });

    if (result.isConfirmed) {
        window.location.href = apkUrl();
    }
}

export function initPublicSharePage() {
    const openButtons = document.querySelectorAll('[data-share-open-app]');
    if (!openButtons.length) {
        return;
    }

    const openApp = (event) => {
        event.preventDefault();
        event.stopPropagation();

        const path = document.body.dataset.appPath;
        const pkg = document.body.dataset.appPackage || 'com.example.dape_ma_mobile';
        if (!path) {
            showOpenInAppHelp();
            return;
        }

        if (isAndroid()) {
            const fallback = window.location.href;
            window.location.href = `intent://${path}#Intent;scheme=dapema;package=${pkg};S.browser_fallback_url=${encodeURIComponent(fallback)};end`;
            return;
        }

        if (isApplePhoneOrTablet()) {
            window.location.href = `dapema://${path}`;
            window.setTimeout(() => {
                if (document.visibilityState === 'visible') {
                    showOpenInAppHelp();
                }
            }, 1200);
            return;
        }

        showOpenInAppHelp();
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', openApp);
    });
}
