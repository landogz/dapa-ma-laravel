/**
 * Lightweight public web analytics (page_view only).
 * Used on public post pages and the install guide — not admin UI.
 */
import axios from 'axios';

const SESSION_KEY = 'dape_ma_analytics_session';
const SESSION_TOUCH_KEY = 'dape_ma_analytics_session_touched';
const SESSION_TTL_MS = 30 * 60 * 1000;

function sessionId() {
    try {
        const now = Date.now();
        const touched = Number(localStorage.getItem(SESSION_TOUCH_KEY) || 0);
        let id = localStorage.getItem(SESSION_KEY);
        if (!id || now - touched > SESSION_TTL_MS) {
            id = crypto.randomUUID?.() || `web_${now}_${Math.random().toString(16).slice(2)}`;
            localStorage.setItem(SESSION_KEY, id);
        }
        localStorage.setItem(SESSION_TOUCH_KEY, String(now));
        return id;
    } catch {
        return null;
    }
}

export async function trackPublicPageView({
    pagePath = window.location.pathname,
    postId = null,
    screenName = 'public_page',
} = {}) {
    const sid = sessionId();
    try {
        await axios.post('/api/v1/analytics/events', {
            event_type: 'page_view',
            platform: 'web',
            session_id: sid,
            page_path: String(pagePath || '').slice(0, 500),
            screen_name: screenName,
            post_id: postId || undefined,
            device_category: window.matchMedia('(max-width: 767px)').matches ? 'mobile' : 'desktop',
            browser: navigator.userAgent?.slice(0, 80) || undefined,
            occurred_at: new Date().toISOString(),
        }, {
            headers: sid ? { 'X-Session-Id': sid } : {},
        });
    } catch {
        // Fire-and-forget.
    }
}

export function initPublicAnalytics() {
    const postRoot = document.getElementById('public-post-app');
    if (postRoot) {
        const postId = Number(postRoot.getAttribute('data-post-id') || 0) || null;
        trackPublicPageView({
            screenName: 'PublicPost',
            postId,
        });
        return;
    }

    if (document.body?.dataset?.page === 'install-guide' || /install-guide/i.test(window.location.pathname)) {
        trackPublicPageView({ screenName: 'InstallGuide' });
    }
}
