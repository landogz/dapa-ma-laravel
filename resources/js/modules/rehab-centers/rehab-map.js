import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const PH_CENTER = { lng: 121.7740, lat: 12.8797 };
const PH_DEFAULT_ZOOM = 5.5;
const PICKER_ZOOM = 14;
const NOMINATIM_BASE = 'https://nominatim.openstreetmap.org';
const NOMINATIM_HEADERS = {
    Accept: 'application/json',
    'Accept-Language': 'en',
};

const MAP_STYLE = {
    version: 8,
    sources: {
        osm: {
            type: 'raster',
            tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
            tileSize: 256,
            attribution: '© OpenStreetMap contributors',
        },
    },
    layers: [
        {
            id: 'osm',
            type: 'raster',
            source: 'osm',
        },
    ],
};

let activePicker = null;
let overviewMap = null;
let overviewMarkers = [];

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function nominatimFetch(path) {
    const response = await fetch(`${NOMINATIM_BASE}${path}`, { headers: NOMINATIM_HEADERS });
    if (!response.ok) {
        throw new Error('Location search is temporarily unavailable.');
    }
    return response.json();
}

export async function searchLocations(query) {
    const trimmed = query.trim();
    if (trimmed.length < 3) {
        return [];
    }

    const params = new URLSearchParams({
        format: 'json',
        q: trimmed,
        countrycodes: 'ph',
        addressdetails: '1',
        limit: '6',
    });

    const results = await nominatimFetch(`/search?${params.toString()}`);
    return (results ?? []).map((item) => ({
        label: item.display_name,
        lat: Number(item.lat),
        lng: Number(item.lon),
        address: item.display_name,
        region: item.address?.state ?? item.address?.region ?? '',
        province: item.address?.province ?? item.address?.county ?? item.address?.city ?? '',
    }));
}

async function reverseGeocode(lat, lng) {
    const params = new URLSearchParams({
        format: 'json',
        lat: String(lat),
        lon: String(lng),
        addressdetails: '1',
    });

    const result = await nominatimFetch(`/reverse?${params.toString()}`);
    if (!result) {
        return null;
    }

    return {
        address: result.display_name ?? '',
        region: result.address?.state ?? result.address?.region ?? '',
        province: result.address?.province ?? result.address?.county ?? result.address?.city ?? '',
    };
}

export function buildRehabMapPickerHtml() {
    return `
        <div class="rehab-map-picker">
            <label class="admin-swal-label" for="rc-map-search">Search location</label>
            <div class="rehab-map-search-row">
                <input
                    id="rc-map-search"
                    class="admin-swal-input rehab-map-search-input"
                    type="search"
                    placeholder="Search address or place in the Philippines"
                    autocomplete="off"
                >
                <button type="button" id="rc-map-search-btn" class="admin-secondary-button rehab-map-search-button">Search</button>
            </div>
            <div id="rc-map-search-results" class="rehab-map-search-results" hidden></div>
            <p class="admin-swal-hint">Click the map or drag the pin to set the exact coordinates.</p>
            <div id="rc-map-canvas" class="rehab-map-canvas" role="application" aria-label="Location map"></div>
            <p id="rc-map-coords" class="rehab-map-coords">Coordinates: not set</p>
            <input type="hidden" id="rc-latitude" value="">
            <input type="hidden" id="rc-longitude" value="">
        </div>
    `;
}

function renderSearchResults(container, results, onSelect) {
    if (!results.length) {
        container.innerHTML = '<p class="rehab-map-search-empty">No locations found. Try a more specific address.</p>';
        container.hidden = false;
        return;
    }

    container.innerHTML = results
        .map(
            (result, index) => `
                <button type="button" class="rehab-map-search-item" data-index="${index}">
                    ${escapeHtml(result.label)}
                </button>
            `,
        )
        .join('');
    container.hidden = false;

    container.querySelectorAll('.rehab-map-search-item').forEach((button) => {
        button.addEventListener('click', () => {
            const item = results[Number(button.dataset.index)];
            onSelect(item);
            container.hidden = true;
            container.innerHTML = '';
        });
    });
}

export function initRehabMapPicker({ lat, lng, onLocationChange } = {}) {
    destroyRehabMapPicker();

    const canvas = document.getElementById('rc-map-canvas');
    const searchInput = document.getElementById('rc-map-search');
    const searchButton = document.getElementById('rc-map-search-btn');
    const resultsEl = document.getElementById('rc-map-search-results');
    const coordsEl = document.getElementById('rc-map-coords');
    const latInput = document.getElementById('rc-latitude');
    const lngInput = document.getElementById('rc-longitude');

    if (!canvas) {
        return null;
    }

    const hasCoords = Number.isFinite(lat) && Number.isFinite(lng);
    const initialCenter = hasCoords ? { lat, lng } : PH_CENTER;
    const initialZoom = hasCoords ? PICKER_ZOOM : PH_DEFAULT_ZOOM;

    const map = new maplibregl.Map({
        container: canvas,
        style: MAP_STYLE,
        center: [initialCenter.lng, initialCenter.lat],
        zoom: initialZoom,
        attributionControl: true,
    });

    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    const marker = new maplibregl.Marker({ draggable: true, color: '#055498' });
    if (hasCoords) {
        marker.setLngLat([lng, lat]).addTo(map);
    }

    const updateCoordsDisplay = (nextLat, nextLng) => {
        coordsEl.textContent = `Coordinates: ${nextLat.toFixed(6)}, ${nextLng.toFixed(6)}`;
        latInput.value = String(nextLat);
        lngInput.value = String(nextLng);
    };

    const applyLocation = async (nextLat, nextLng, meta = {}) => {
        marker.setLngLat([nextLng, nextLat]).addTo(map);
        map.flyTo({ center: [nextLng, nextLat], zoom: PICKER_ZOOM, essential: true });
        updateCoordsDisplay(nextLat, nextLng);

        if (typeof onLocationChange === 'function') {
            onLocationChange({
                latitude: nextLat,
                longitude: nextLng,
                ...meta,
            });
        }
    };

    if (hasCoords) {
        updateCoordsDisplay(lat, lng);
    }

    const runSearch = async () => {
        const query = searchInput?.value ?? '';
        try {
            const results = await searchLocations(query);
            renderSearchResults(resultsEl, results, (item) => {
                applyLocation(item.lat, item.lng, {
                    address: item.address,
                    region: item.region,
                    province: item.province,
                });
            });
        } catch {
            resultsEl.innerHTML = '<p class="rehab-map-search-empty">Search failed. Please try again.</p>';
            resultsEl.hidden = false;
        }
    };

    searchButton?.addEventListener('click', runSearch);
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            runSearch();
        }
    });

    map.on('click', async (event) => {
        const nextLng = event.lngLat.lng;
        const nextLat = event.lngLat.lat;

        try {
            const reverse = await reverseGeocode(nextLat, nextLng);
            await applyLocation(nextLat, nextLng, reverse ?? {});
        } catch {
            await applyLocation(nextLat, nextLng);
        }
    });

    marker.on('dragend', async () => {
        const { lat: nextLat, lng: nextLng } = marker.getLngLat();
        try {
            const reverse = await reverseGeocode(nextLat, nextLng);
            await applyLocation(nextLat, nextLng, reverse ?? {});
        } catch {
            await applyLocation(nextLat, nextLng);
        }
    });

    activePicker = { map, marker };
    return activePicker;
}

export function destroyRehabMapPicker() {
    if (!activePicker) {
        return;
    }

    activePicker.marker.remove();
    activePicker.map.remove();
    activePicker = null;
}

export function getRehabMapPickerCoords() {
    const lat = Number(document.getElementById('rc-latitude')?.value);
    const lng = Number(document.getElementById('rc-longitude')?.value);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }

    return { latitude: lat, longitude: lng };
}

export function initRehabCentersOverviewMap(containerId, centers = []) {
    const container = document.getElementById(containerId);
    if (!container) {
        return;
    }

    destroyRehabCentersOverviewMap();

    const mapped = centers.filter((center) => Number.isFinite(center.latitude) && Number.isFinite(center.longitude));
    const map = new maplibregl.Map({
        container,
        style: MAP_STYLE,
        center: [PH_CENTER.lng, PH_CENTER.lat],
        zoom: PH_DEFAULT_ZOOM,
        attributionControl: true,
    });

    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    overviewMarkers = mapped.map((center) => {
        const popup = new maplibregl.Popup({ offset: 16, closeButton: false }).setHTML(
            `<div class="rehab-map-popup">
                <p class="rehab-map-popup-title">${escapeHtml(center.name)}</p>
                <p class="rehab-map-popup-meta">${escapeHtml(center.address)}</p>
            </div>`,
        );

        return new maplibregl.Marker({ color: center.is_active ? '#055498' : '#94a3b8' })
            .setLngLat([center.longitude, center.latitude])
            .setPopup(popup)
            .addTo(map);
    });

    if (mapped.length === 1) {
        map.setCenter([mapped[0].longitude, mapped[0].latitude]);
        map.setZoom(PICKER_ZOOM);
    } else if (mapped.length > 1) {
        const bounds = new maplibregl.LngLatBounds();
        mapped.forEach((center) => bounds.extend([center.longitude, center.latitude]));
        map.fitBounds(bounds, { padding: 48, maxZoom: 12 });
    }

    overviewMap = map;
}

export function destroyRehabCentersOverviewMap() {
    overviewMarkers.forEach((marker) => marker.remove());
    overviewMarkers = [];

    if (overviewMap) {
        overviewMap.remove();
        overviewMap = null;
    }
}
