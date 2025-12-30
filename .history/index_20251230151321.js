// ============================================================
// Base Map Layers
// ============================================================

const googleSat = L.tileLayer(
    'http://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}&hl=en',
    {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
    }
);

const googleHybrid = L.tileLayer(
    'http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}&hl=en',
    {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
    }
);

const carto = L.tileLayer(
    'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
    {
        maxZoom: 20
    }
);

const basemaps = {
    osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }),
    satellite: googleSat,
    google: googleHybrid,
    'google-sat': googleSat,
    carto: carto
};

const basemapMaxZoom = {
    osm: 19,
    satellite: 20,
    google: 20,
    'google-sat': 20,
    carto: 20
};


// ============================================================
// Initialize Map
// ============================================================
const map = L.map('map', {
    center: [4.2105, 101.9758], // Malaysia
    zoom: 6,
    maxZoom: 19,
    layers: [basemaps.osm]
});

let currentBasemap = 'osm';

// ============================================================
// Proxy URL Helper
// ============================================================
// Build proxy URL for GeoServer requests
function proxyUrl(path) {
    // Use query parameter method for maximum compatibility
    return `${CONFIG.geoserverUrl}?path=${encodeURIComponent(path)}`;
}

// ============================================================
// VM1 "views" integration (View1 / View2 / etc.)
// ============================================================

const VM1_BASE_URL = 'http://34.124.247.53';
const SPATIAL_VIEWS_URL = `${VM1_BASE_URL}/api/spatial/views`;

let currentViewId = null;

// Update CQL filter on all WMS layers based on currentViewId
function applyViewFilterToWms() {
    const filter = currentViewId ? `view_id=${currentViewId}` : null;

    ['roads', 'areas', 'buildings'].forEach(k => {
        const layer = wmsLayers[k];
        if (!layer || !layer.setParams) return;

        layer.setParams({ CQL_FILTER: filter || null });
    });
    /*
    Object.values(wmsLayers).forEach(layer => {
        if (!layer || !layer.setParams) return;

        if (filter) {
            layer.setParams({ CQL_FILTER: filter });
        } else {
            layer.setParams({ CQL_FILTER: null });
        }
    });
    */
}

// Zoom map to the extent of the current view's areas
async function fitMapToCurrentView() {
    if (!currentViewId) return;

    const params = new URLSearchParams({
        service: 'WFS',
        version: '1.1.0',
        request: 'GetFeature',
        typeName: `${CONFIG.workspace}:areas`,
        outputFormat: 'application/json',
        CQL_FILTER: `view_id=${currentViewId}`
    });

    const url = proxyUrl(`${CONFIG.workspace}/wfs`) + '&' + params.toString();

    try {
        const res = await fetch(url);
        if (!res.ok) {
            console.error('Failed to fetch view extent from GeoServer', res.status);
            return;
        }

        const data = await res.json();
        if (!data.features || data.features.length === 0) {
            console.warn('No areas found for current view', currentViewId);
            return;
        }

        const tmpLayer = L.geoJSON(data);
        const bounds = tmpLayer.getBounds();

        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [50, 50] });
        }
    } catch (err) {
        console.error('Error fitting map to current view', err);
    }
}

// Load list of views from VM1
async function loadViewsFromVm1() {
    const select = document.getElementById('viewSelect');
    if (!select) return;

    try {
        const res = await fetch(SPATIAL_VIEWS_URL, {
            headers: { 'Accept': 'application/json' }
        });

        if (!res.ok) {
            console.error('Failed to load views (HTTP ' + res.status + ')');
            select.innerHTML = '<option value="">No views available</option>';
            currentViewId = null;
            applyViewFilterToWms();
            return;
        }

        const data = await res.json();
        const viewList = data.views || [];

        if (!viewList.length) {
            select.innerHTML = '<option value="">No views created yet</option>';
            currentViewId = null;
            applyViewFilterToWms();
            return;
        }

        // Build dropdown
        select.innerHTML = '';
        viewList.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.id;
            opt.textContent = v.view_name || `View ${v.id}`;
            select.appendChild(opt);
        });

        // Pick first view as default
        currentViewId = viewList[0].id;
        select.value = String(currentViewId);
        applyViewFilterToWms();
        fitMapToCurrentView();

        const status = document.getElementById('geoserver-status');
        if (status) {
            const name = viewList[0].view_name || viewList[0].view_key;
            status.textContent = `Connected to GeoServer · View: ${name}`;
        }

    } catch (err) {
        console.error('Error loading views from VM1', err);
        const select = document.getElementById('viewSelect');
        if (select) {
            select.innerHTML = '<option value="">Failed to load views</option>';
        }
        currentViewId = null;
        applyViewFilterToWms();
    }
}

// Handle when user changes the view
const viewSelectEl = document.getElementById('viewSelect');
if (viewSelectEl) {
    viewSelectEl.addEventListener('change', (e) => {
        const val = e.target.value;
        currentViewId = val ? parseInt(val, 10) : null;
        applyViewFilterToWms();
        fitMapToCurrentView();
    });
}

// Kick off load on page startup
loadViewsFromVm1();

// ============================================================
// GeoServer WMS Layers (via proxy)
// ============================================================
const wmsUrl = proxyUrl(`${CONFIG.workspace}/wms`);

const wmsLayers = {
        buildingPlan: L.tileLayer.wms(wmsUrl, {
        layers: `${CONFIG.workspace}:msb01-geotiff`,
        format: 'image/png',
        transparent: true,
        styles: '',
        version: '1.1.0',
        maxZoom: 20,
        opacity: 0.6
    }),
    roads: L.tileLayer.wms(wmsUrl, {
        layers: `${CONFIG.workspace}:roads`,
        format: 'image/png',
        transparent: true,
        styles: '',
        version: '1.1.0',
        maxZoom: 20
    }),
    areas: L.tileLayer.wms(wmsUrl, {
        layers: `${CONFIG.workspace}:areas`,
        format: 'image/png',
        transparent: true,
        styles: '',
        version: '1.1.0',
        maxZoom: 20
    }),
    buildings: L.tileLayer.wms(wmsUrl, {
        layers: `${CONFIG.workspace}:buildings`,
        format: 'image/png',
        transparent: true,
        styles: '',
        version: '1.1.0',
        maxZoom: 20
    })
};

// Add default layers
wmsLayers.buildingPlan.addTo(map);
wmsLayers.roads.addTo(map);
wmsLayers.areas.addTo(map);

// ============================================================
// Check GeoServer Connection
// ============================================================
async function checkGeoServerStatus() {
    const statusDot = document.getElementById('statusDot');
    const statusText = document.getElementById('statusText');
    
    try {
        const testUrl = proxyUrl(`${CONFIG.workspace}/wms`) + '&service=WMS&request=GetCapabilities';
        const response = await fetch(testUrl);
        
        if (response.ok) {
            statusDot.className = 'status-dot connected';
            statusText.textContent = 'Connected to GeoServer';
        } else {
            throw new Error('Bad response: ' + response.status);
        }
    } catch (err) {
        console.error('GeoServer connection failed:', err);
        statusDot.className = 'status-dot disconnected';
        statusText.textContent = 'GeoServer unavailable';
    }
}

// Check status on load
checkGeoServerStatus();

// ============================================================
// Base Map Switcher
// ============================================================
document.querySelectorAll('.basemap-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const basemapKey = btn.dataset.basemap;
        
        map.removeLayer(basemaps[currentBasemap]);
        basemaps[basemapKey].addTo(map);
        basemaps[basemapKey].bringToBack();
        currentBasemap = basemapKey;

        //  Update maxZoom based on basemap
        const newMaxZoom = basemapMaxZoom[basemapKey] || 19;
        map.setMaxZoom(newMaxZoom);
        
        // If currently zoomed beyond new limit, zoom back
        if (map.getZoom() > newMaxZoom) {
            map.setZoom(newMaxZoom);
        }
        
        document.querySelectorAll('.basemap-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    });
});

// ============================================================
// Layer Toggle
// ============================================================
document.querySelectorAll('.layer-item').forEach(item => {
    const checkbox = item.querySelector('.layer-checkbox');
    const layerKey = item.dataset.layer;
    
    checkbox.addEventListener('change', () => {
        if (checkbox.checked) {
            wmsLayers[layerKey].addTo(map);
            item.classList.add('active');
        } else {
            map.removeLayer(wmsLayers[layerKey]);
            item.classList.remove('active');
        }
    });
    
    if (checkbox.checked) {
        item.classList.add('active');
    }
});

// ============================================================
// Feature Info on Click (WMS GetFeatureInfo)
// ============================================================
const labelMarkers = L.layerGroup().addTo(map);

map.on('click', async (e) => {
     labelMarkers.clearLayers();  // this line to clear previous labels

    const size = map.getSize();
    const bounds = map.getBounds();
    const sw = bounds.getSouthWest();
    const ne = bounds.getNorthEast();
    
    const visibleLayers = [];
    document.querySelectorAll('.layer-checkbox:checked').forEach(cb => {
        const layerKey = cb.closest('.layer-item').dataset.layer;
        visibleLayers.push(`${CONFIG.workspace}:${layerKey}`);
    });
    
    if (visibleLayers.length === 0) return;
    
    const point = map.latLngToContainerPoint(e.latlng);
    
    const params = new URLSearchParams({
        service: 'WMS',
        version: '1.1.1',
        request: 'GetFeatureInfo',
        layers: visibleLayers.join(','),
        query_layers: visibleLayers.join(','),
        info_format: 'application/json',
        feature_count: 10,
        x: Math.round(point.x),
        y: Math.round(point.y),
        width: size.x,
        height: size.y,
        srs: 'EPSG:4326',
        bbox: `${sw.lng},${sw.lat},${ne.lng},${ne.lat}`
    });
    
    try {
        const url = proxyUrl(`${CONFIG.workspace}/wms`) + '&' + params.toString();
        const response = await fetch(url);
        const data = await response.json();
        
        console.log('GetFeatureInfo response:', data);

        if (data.features && data.features.length > 0) {
            console.log('Feature properties:', data.features[0].properties);

            let html = '<div class="feature-info">';
            
            data.features.forEach((feature, idx) => {
                const props = feature.properties;
                html += `<h4>${props.road_name || props.area_name || props.building_name || 'Feature ' + (idx + 1)}</h4>`;
                html += '<table>';
                
                Object.entries(props).forEach(([key, value]) => {
                    if (value !== null && key !== 'geom') {
                        html += `<tr><td>${key}</td><td>${value}</td></tr>`;
                    }
                });
                
                html += '</table>';
                if (idx < data.features.length - 1) html += '<hr style="margin:8px 0">';
            });
            
            html += '</div>';
            
            L.popup()
                .setLatLng(e.latlng)
                .setContent(html)
                .openOn(map);
                
            // Add label marker
            const feature = data.features[0];
            const props = feature.properties;
            const name = props.road_name || props.area_name || props.name || null;
            
            if (name) {
                const label = L.marker(e.latlng, {
                    icon: L.divIcon({
                        className: 'feature-label',
                        html: `<div>${name}</div>`,
                        iconAnchor: [0, 0]
                    })
                }).addTo(labelMarkers);
            }
        }
    } catch (err) {
        console.error('GetFeatureInfo failed:', err);
    }
});

// ============================================================
// Fit to Layer Bounds
// ============================================================
async function fitToLayerBounds(layerName) {
    const params = new URLSearchParams({
        service: 'WFS',
        version: '1.1.0',
        request: 'GetFeature',
        typeName: `${CONFIG.workspace}:${layerName}`,
        outputFormat: 'application/json',
        maxFeatures: 1
    });
    
    const url = proxyUrl(`${CONFIG.workspace}/wfs`) + '&' + params.toString();
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.features && data.features.length > 0) {
            const geojsonLayer = L.geoJSON(data);
            map.fitBounds(geojsonLayer.getBounds(), { padding: [50, 50] });
        }
    } catch (err) {
        console.error('Failed to get layer bounds:', err);
    }
}

// Double-click layer item to zoom to extent
document.querySelectorAll('.layer-item').forEach(item => {
    item.addEventListener('dblclick', () => {
        const layerKey = item.dataset.layer;
        fitToLayerBounds(layerKey);
    });
});

// ============================================================
// Address Search (using Nominatim / OpenStreetMap)
// ============================================================
const searchInput = document.getElementById('addressSearch');
const searchBtn = document.getElementById('searchBtn');
const searchResults = document.getElementById('searchResults');

let searchMarker = null;
let searchTimeout = null;

// Create a custom marker icon for search results
const searchMarkerIcon = L.divIcon({
    className: 'search-marker-icon',
    iconSize: [20, 20],
    iconAnchor: [10, 10]
});

// Search function using Nominatim API
async function searchAddress(query) {
    if (!query || query.trim().length < 3) {
        searchResults.innerHTML = '<div class="search-no-results">Type at least 3 characters</div>';
        return;
    }

    searchResults.innerHTML = '<div class="search-loading">Searching...</div>';

    try {
        // Use Nominatim with preference for Malaysia results
        const url = `https://nominatim.openstreetmap.org/search?` + new URLSearchParams({
            q: query,
            format: 'json',
            addressdetails: 1,
            limit: 5,
            countrycodes: 'my', // Prefer Malaysia
            'accept-language': 'en'
        });

        const response = await fetch(url, {
            headers: {
                'User-Agent': 'SpatialDataViewer/1.0'
            }
        });

        if (!response.ok) throw new Error('Search failed');

        const data = await response.json();

        if (data.length === 0) {
            // If no results in Malaysia, try worldwide search
            const worldUrl = `https://nominatim.openstreetmap.org/search?` + new URLSearchParams({
                q: query,
                format: 'json',
                addressdetails: 1,
                limit: 5,
                'accept-language': 'en'
            });

            const worldResponse = await fetch(worldUrl, {
                headers: {
                    'User-Agent': 'SpatialDataViewer/1.0'
                }
            });

            const worldData = await worldResponse.json();

            if (worldData.length === 0) {
                searchResults.innerHTML = '<div class="search-no-results">No results found</div>';
                return;
            }

            displaySearchResults(worldData);
            return;
        }

        displaySearchResults(data);

    } catch (err) {
        console.error('Search error:', err);
        searchResults.innerHTML = '<div class="search-no-results">Search failed. Try again.</div>';
    }
}

// Display search results
function displaySearchResults(results) {
    searchResults.innerHTML = '';

    results.forEach(result => {
        const item = document.createElement('div');
        item.className = 'search-result-item';

        // Extract a clean name
        const name = result.name || result.display_name.split(',')[0];
        const address = result.display_name;

        item.innerHTML = `
            <div class="search-result-name">${name}</div>
            <div class="search-result-address">${address}</div>
        `;

        item.addEventListener('click', () => {
            goToLocation(parseFloat(result.lat), parseFloat(result.lon), name);
            searchResults.innerHTML = '';
            searchInput.value = name;
        });

        searchResults.appendChild(item);
    });
}

// Go to location and add marker
function goToLocation(lat, lon, name) {
    // Remove previous search marker
    if (searchMarker) {
        map.removeLayer(searchMarker);
    }

    // Determine zoom level based on place type
    const zoomLevel = 16;

    // Fly to location
    map.flyTo([lat, lon], zoomLevel, {
        duration: 1.5
    });

    // Add marker
    searchMarker = L.marker([lat, lon], {
        icon: L.divIcon({
            className: 'search-marker',
            html: `<div style="
                background: #f44336;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                border: 3px solid white;
                box-shadow: 0 2px 8px rgba(0,0,0,0.4);
            "></div>`,
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        })
    }).addTo(map);

    // Add popup with name
    searchMarker.bindPopup(`<strong>${name}</strong>`).openPopup();
}

// Event listeners for search
searchBtn.addEventListener('click', () => {
    searchAddress(searchInput.value);
});

searchInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        searchAddress(searchInput.value);
    }
});

// Auto-search as user types (with debounce)
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (searchInput.value.length >= 3) {
            searchAddress(searchInput.value);
        } else {
            searchResults.innerHTML = '';
        }
    }, 500); // Wait 500ms after user stops typing
});

// Clear results when clicking elsewhere
document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-container') && !e.target.closest('.search-results')) {
        searchResults.innerHTML = '';
    }
});