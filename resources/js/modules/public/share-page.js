export function initPublicSharePage() {
    const openButton = document.getElementById('share-open-app');
    if (!openButton) {
        return;
    }

    openButton.addEventListener('click', () => {
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
    });
}
