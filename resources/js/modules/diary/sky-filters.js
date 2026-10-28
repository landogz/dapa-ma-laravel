export const SKY_FILTER_ITEMS = [
    { value: '', label: 'All skies', hint: 'Every color', spectrum: true },
    { value: 'sky_red', label: 'Red', hint: 'Love · Anger', swatch: 'sky_red' },
    { value: 'sky_pink', label: 'Pink', hint: 'Happy · Optimistic', swatch: 'sky_pink' },
    { value: 'sky_blue', label: 'Blue', hint: 'Calm · Serene', swatch: 'sky_blue' },
    { value: 'sky_white', label: 'White', hint: 'Imaginative', swatch: 'sky_white' },
    { value: 'sky_violet', label: 'Violet', hint: 'Reflective', swatch: 'sky_violet' },
    { value: 'sky_gray', label: 'Gray', hint: 'Neutral · Balance', swatch: 'sky_gray' },
    { value: 'sky_purple', label: 'Purple', hint: 'Afraid', swatch: 'sky_purple' },
    { value: 'sky_green', label: 'Green', hint: 'Growth · Hope', swatch: 'sky_green' },
    { value: 'sky_orange', label: 'Orange', hint: 'Confidence', swatch: 'sky_orange' },
    { value: 'sky_yellow', label: 'Yellow', hint: 'Happiness', swatch: 'sky_yellow' },
    { value: 'sky_black', label: 'Black', hint: 'Power · Weight', swatch: 'sky_black' },
];

export function userInitials(name) {
    const parts = String(name || 'User').trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return 'U';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0] || ''}${parts[parts.length - 1][0] || ''}`.toUpperCase();
}
