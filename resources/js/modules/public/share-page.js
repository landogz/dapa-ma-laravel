export function initPublicSharePage() {
    const openButtons = document.querySelectorAll('[data-share-open-app]');
    if (!openButtons.length) {
        return;
    }

    const openApp = () => {
        const path = document.body.dataset.appPath;
        const pkg = document.body.dataset.appPackage || 'com.example.dape_ma_mobile';
        if (!path) {
            return;
        }

        const fallback = window.location.href;
        const isAndroid = /android/i.test(navigator.userAgent || '');

        if (isAndroid) {
            window.location.href = `intent://${path}#Intent;scheme=dapema;package=${pkg};S.browser_fallback_url=${encodeURIComponent(fallback)};end`;
            return;
        }

        window.location.href = `dapema://${path}`;
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', openApp);
    });
}
