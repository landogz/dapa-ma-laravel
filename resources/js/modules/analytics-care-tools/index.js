import { writeAnimatedMetric } from '../analytics-motion';

function formatCount(value) {
    return Number(value || 0).toLocaleString();
}

function setKpi(key, value) {
    document.querySelectorAll(`[data-analytics-summary="${key}"]`).forEach((el) => {
        writeAnimatedMetric(el, value == null ? '0' : formatCount(value));
    });
}

export function renderCareTools(careTools = {}) {
    const totals = careTools.totals ?? {};

    setKpi('journal-creates', totals.journal_creates);
    setKpi('care-checks', totals.care_checks);
    setKpi('mood-checkins', totals.mood_checkins);
    setKpi('calm-sessions', totals.calm_sessions);
    setKpi('color-fun-opens', totals.color_fun_opens);
    setKpi('color-fun-completes', totals.color_fun_completes);
    setKpi('notification-opens', totals.notification_opens);
    setKpi('contest-submits', totals.contest_submits);
}
