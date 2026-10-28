import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { reverseGeocodeAddress, searchAddresses } from '../address-autocomplete';

const PH_CENTER = { lng: 121.7740, lat: 12.8797 };
const PH_DEFAULT_ZOOM = 5.5;
const PICKER_ZOOM = 14;

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

export async function searchLocations(query) {
    return searchAddresses(query);
}

async function reverseGeocode(lat, lng) {
    return reverseGeocodeAddress(lat, lng);
}

export function buildRehabMapPickerHtml() {
    return `
        <div class="rehab-map-picker">
            <label class="admin-swal-label" for="rc-map-search">Search location</label>
            <div class="rehab-map-search-row">
                <div class="address-autocomplete">
                    <input
                        id="rc-map-search"
                        class="admin-swal-input rehab-map-search-input"
                        type="search"
                        data-address-autocomplete
                        placeholder="Search address or place in the Philippines"
                        autocomplete="off"
                    >
                </div>
                <button type="button" id="rc-map-search-btn" class="admin-secondary-button rehab-map-search-button">Search</button>
            </div>
            <p class="admin-swal-hint">Click the map or drag the pin to set the exact coordinates.</p>
            <div id="rc-map-canvas" class="rehab-map-canvas" role="application" aria-label="Location map"></div>
            <p id="rc-map-coords" class="rehab-map-coords">Coordinates: not set</p>
            <input type="hidden" id="rc-latitude" value="">
            <input type="hidden" id="rc-longitude" value="">
        </div>
    `;
}

export function initRehabMapPicker({ lat, lng, onLocationChange } = {}) {
    destroyRehabMapPicker();

    const canvas = document.getElementById('rc-map-canvas');
    const searchInput = document.getElementById('rc-map-search');
    const searchButton = document.getElementById('rc-map-search-btn');
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

    searchInput?.addEventListener('address-autocomplete:select', (event) => {
        const item = event.detail ?? {};
        if (!Number.isFinite(item.lat) || !Number.isFinite(item.lng)) {
            return;
        }
        applyLocation(item.lat, item.lng, {
            address: item.address,
            region: item.region,
            province: item.province,
        });
    });

    searchButton?.addEventListener('click', () => {
        searchInput?._addressAutocomplete?.searchNow();
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

    activePicker = { map, marker, applyLocation };
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
