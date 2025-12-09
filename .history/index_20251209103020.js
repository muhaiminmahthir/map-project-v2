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


// ============================================================
// Initialize Map
// ============================================================
const map = L.map('map', {
    center: [4.2105, 101.9758], // Malaysia
    zoom: 6,
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

    Object.values(wmsLayers).forEach(layer => {
        if (!layer || !layer.setParams) return;

        if (filter) {
            layer.setParams({ CQL_FILTER: filter });
        } else {
            layer.setParams({ CQL_FILTER: null });
        }
    });
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
    roads: L.tileLayer.wms(wmsUrl, {
        layers: `${CONFIG.workspace}:roads`,
        format: 'image/png',
        transparent: true,
        styles: '',
        version: '1.1.0',
        maxZoom: 21
    }),
    areas: L.tileLayer.wms(wmsUrl, {
        layers: `${CONFIG.workspace}:areas`,
        format: 'image/png',
        transparent: true,
        styles: '',
        version: '1.1.0',
        maxZoom: 21
    }),
    buildings: L.tileLayer.wms(wmsUrl, {
        layers: `${CONFIG.workspace}:buildings`,
        format: 'image/png',
        transparent: true,
        styles: '',
        version: '1.1.0',
        maxZoom: 21
    })
};

// Add default layers
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