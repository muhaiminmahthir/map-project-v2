<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Map Viewer - Roads & Areas</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .header h1 {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .header-controls {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            position: relative;
        }
        
        #map {
            flex: 1;
            z-index: 1;
        }
        
        /* Sidebar */
        .sidebar {
            width: 320px;
            background: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            z-index: 100;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .sidebar-header {
            padding: 16px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .sidebar-header h2 {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
        }
        
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }
        
        /* Layer Controls */
        .layer-section {
            margin-bottom: 20px;
        }
        
        .layer-section h3 {
            font-size: 0.85rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .layer-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .layer-item:hover {
            background: #e9ecef;
        }
        
        .layer-item.active {
            background: #e3f2fd;
            border: 1px solid #2196f3;
        }
        
        .layer-checkbox {
            margin-right: 10px;
        }
        
        .layer-name {
            flex: 1;
            font-size: 0.9rem;
        }
        
        .layer-icon {
            width: 20px;
            height: 3px;
            border-radius: 2px;
            margin-right: 10px;
        }
        
        .layer-icon.roads { background: #ff9800; }
        .layer-icon.areas { background: #4caf50; }
        .layer-icon.buildings { background: #9c27b0; }
        
        /* Base Map Selector */
        .basemap-selector {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .basemap-btn {
            flex: 1;
            min-width: 80px;
            padding: 10px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        
        .basemap-btn:hover {
            border-color: #2196f3;
        }
        
        .basemap-btn.active {
            border-color: #2196f3;
            background: #e3f2fd;
        }
        
        .basemap-btn img {
            width: 40px;
            height: 40px;
            margin-bottom: 4px;
            border-radius: 4px;
        }
        
        .basemap-btn span {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Connection Status */
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .status-dot.connected { background: #4caf50; }
        .status-dot.disconnected { background: #f44336; }
        .status-dot.loading { background: #ff9800; animation: pulse 1s infinite; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
        /* Legend */
        .legend {
            position: absolute;
            bottom: 30px;
            right: 340px;
            background: white;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        
        .legend h4 {
            font-size: 0.8rem;
            margin-bottom: 8px;
            color: #666;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
            font-size: 0.85rem;
        }
        
        .legend-color {
            width: 20px;
            height: 4px;
            border-radius: 2px;
            margin-right: 8px;
        }
        
        /* Feature Info Popup */
        .feature-info {
            max-width: 300px;
        }
        
        .feature-info h4 {
            margin-bottom: 8px;
            color: #333;
        }
        
        .feature-info table {
            width: 100%;
            font-size: 0.85rem;
        }
        
        .feature-info td {
            padding: 4px 0;
        }
        
        .feature-info td:first-child {
            font-weight: 500;
            color: #666;
            width: 40%;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: absolute;
                right: -320px;
                top: 0;
                bottom: 0;
                transition: right 0.3s;
            }
            
            .sidebar.open {
                right: 0;
            }
            
            .legend {
                right: 20px;
            }
        }
    </style>
</head>
<body>
    <?php
    // Configuration - UPDATE THESE VALUES
    $config = [
        'geoserver_url' => 'http://35.187.255.127:8080/geoserver',
        'workspace' => 'geodb',
        //'google_maps_api_key' => 'YOUR_GOOGLE_MAPS_API_KEY', // Optional
    ];
    ?>
    
    <!-- Header -->
    <header class="header">
        <h1>🗺️ Spatial Data Viewer</h1>
        <div class="header-controls">
            <div class="status-indicator">
                <span class="status-dot loading" id="statusDot"></span>
                <span id="statusText">Connecting to GeoServer...</span>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="main-content">
        <div id="map"></div>
        
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Layer Controls</h2>
            </div>
            
            <div class="sidebar-content">
                <!-- Base Map Selection -->
                <div class="layer-section">
                    <h3>Base Map</h3>
                    <div class="basemap-selector">
                        <button class="basemap-btn active" data-basemap="osm">
                            <div style="width:40px;height:40px;background:#c8e6c9;border-radius:4px;margin:0 auto 4px;"></div>
                            <span>OpenStreetMap</span>
                        </button>
                        <button class="basemap-btn" data-basemap="satellite">
                            <div style="width:40px;height:40px;background:#1a237e;border-radius:4px;margin:0 auto 4px;"></div>
                            <span>Satellite</span>
                        </button>
                        <button class="basemap-btn" data-basemap="google">
                            <div style="width:40px;height:40px;background:#fff3e0;border-radius:4px;margin:0 auto 4px;"></div>
                            <span>Google Maps</span>
                        </button>
                        <button class="basemap-btn" data-basemap="google-sat">
                            <div style="width:40px;height:40px;background:#33691e;border-radius:4px;margin:0 auto 4px;"></div>
                            <span>Google Sat</span>
                        </button>
                        <button class="basemap-btn" data-basemap="carto">
                            <div style="width:40px;height:40px;background:#f5f5f5;border-radius:4px;margin:0 auto 4px;"></div>
                            <span>Carto Light</span>
                        </button>
                    </div>
                </div>
                
                <!-- GeoServer Layers -->
                <div class="layer-section">
                    <h3>Data Layers</h3>
                    <div id="layerList">
                        <div class="layer-item" data-layer="roads">
                            <input type="checkbox" class="layer-checkbox" id="layer-roads" checked>
                            <div class="layer-icon roads"></div>
                            <label class="layer-name" for="layer-roads">Roads</label>
                        </div>
                        <div class="layer-item" data-layer="areas">
                            <input type="checkbox" class="layer-checkbox" id="layer-areas" checked>
                            <div class="layer-icon areas"></div>
                            <label class="layer-name" for="layer-areas">Survey Areas</label>
                        </div>
                        <div class="layer-item" data-layer="buildings">
                            <input type="checkbox" class="layer-checkbox" id="layer-buildings">
                            <div class="layer-icon buildings"></div>
                            <label class="layer-name" for="layer-buildings">Buildings</label>
                        </div>
                    </div>
                </div>
                
                <!-- WMS/WFS URLs for external tools -->
                <div class="layer-section">
                    <h3>External Access</h3>
                    <p style="font-size:0.8rem; color:#666; margin-bottom:8px;">
                        Connect QGIS or other GIS tools:
                    </p>
                    <div style="background:#f5f5f5; padding:8px; border-radius:4px; font-family:monospace; font-size:0.7rem; word-break:break-all;">
                        <strong>WMS:</strong><br>
                        <?= htmlspecialchars($config['geoserver_url']) ?>/<?= $config['workspace'] ?>/wms
                        <br><br>
                        <strong>WFS:</strong><br>
                        <?= htmlspecialchars($config['geoserver_url']) ?>/<?= $config['workspace'] ?>/wfs
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Legend -->
        <div class="legend">
            <h4>Legend</h4>
            <div class="legend-item">
                <div class="legend-color" style="background:#ff9800;"></div>
                <span>Roads</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background:#4caf50; height:12px;"></div>
                <span>Survey Areas</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background:#9c27b0; height:12px;"></div>
                <span>Buildings</span>
            </div>
        </div>
    </div>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Google Maps API (optional - for Google layers) -->
    <?php if (!empty($config['google_maps_api_key']) && $config['google_maps_api_key'] !== 'YOUR_GOOGLE_MAPS_API_KEY'): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($config['google_maps_api_key']) ?>"></script>
    <script src="https://unpkg.com/leaflet.gridlayer.googlemutant@latest/dist/Leaflet.GoogleMutant.js"></script>
    <?php endif; ?>
    
    <script>
        // Configuration from PHP
        const CONFIG = {
            geoserverUrl: '<?= $config['geoserver_url'] ?>',
            workspace: '<?= $config['workspace'] ?>',
            hasGoogleApi: <?= (!empty($config['google_maps_api_key']) && $config['google_maps_api_key'] !== 'YOUR_GOOGLE_MAPS_API_KEY') ? 'true' : 'false' ?>
        };
        
        // ============================================================
        // Base Map Layers
        // ============================================================

        // basemaps from xx
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

        // Hook them into the existing basemap keys used by your buttons:
        //  - data-basemap="osm"
        //  - data-basemap="satellite"
        //  - data-basemap="google"
        //  - data-basemap="google-sat"
        const basemaps = {
            // OpenStreetMap button
            osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }),

            // "Satellite" button  → pure imagery
            satellite: googleSat,

            // "Google Maps" button → hybrid (satellite + labels)
            google: googleHybrid,

            // "Google Sat" button → also satellite (can be changed to terrain or roadmap if desired)
            'google-sat': googleSat,

            // Extra: Carto style (not wired to a button yet)
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
        // GeoServer WMS Layers
        // ============================================================
        const wmsUrl = `${CONFIG.geoserverUrl}/${CONFIG.workspace}/wms`;
        
        const wmsLayers = {
            roads: L.tileLayer.wms(wmsUrl, {
                layers: `${CONFIG.workspace}:roads`,
                format: 'image/png',
                transparent: true,
                styles: '',
                version: '1.1.0'
            }),
            areas: L.tileLayer.wms(wmsUrl, {
                layers: `${CONFIG.workspace}:areas`,
                format: 'image/png',
                transparent: true,
                styles: '',
                version: '1.1.0'
            }),
            buildings: L.tileLayer.wms(wmsUrl, {
                layers: `${CONFIG.workspace}:buildings`,
                format: 'image/png',
                transparent: true,
                styles: '',
                version: '1.1.0'
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
                // Try to get WMS capabilities
                const response = await fetch(`${wmsUrl}?service=WMS&request=GetCapabilities`, {
                    mode: 'cors'
                });
                
                if (response.ok) {
                    statusDot.className = 'status-dot connected';
                    statusText.textContent = 'Connected to GeoServer';
                } else {
                    throw new Error('Bad response');
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
                
                // Remove current basemap
                map.removeLayer(basemaps[currentBasemap]);
                
                // Add new basemap
                basemaps[basemapKey].addTo(map);
                basemaps[basemapKey].bringToBack();
                currentBasemap = basemapKey;
                
                // Update UI
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
            
            // Set initial active state
            if (checkbox.checked) {
                item.classList.add('active');
            }
        });
        
        // ============================================================
        // Feature Info on Click (WMS GetFeatureInfo)
        // ============================================================
        map.on('click', async (e) => {
            // Build GetFeatureInfo URL
            const size = map.getSize();
            const bounds = map.getBounds();
            const sw = bounds.getSouthWest();
            const ne = bounds.getNorthEast();
            
            // Determine which layers are visible
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
                const response = await fetch(`${wmsUrl}?${params}`);
                const data = await response.json();
                
                if (data.features && data.features.length > 0) {
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
                }
            } catch (err) {
                console.error('GetFeatureInfo failed:', err);
            }
        });
        
        // ============================================================
        // Fit to Layer Bounds
        // ============================================================
        async function fitToLayerBounds(layerName) {
            const url = `${CONFIG.geoserverUrl}/${CONFIG.workspace}/wfs?` + new URLSearchParams({
                service: 'WFS',
                version: '1.1.0',
                request: 'GetFeature',
                typeName: `${CONFIG.workspace}:${layerName}`,
                outputFormat: 'application/json',
                maxFeatures: 1
            });
            
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
    </script>
</body>
</html>
