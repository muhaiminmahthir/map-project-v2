<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nak Tengok Map</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <?php
    // Configuration - UPDATE THESE VALUES
    $config = [
        'geoserver_url' => 'http://geoserversafe.duckdns.org:65437/geoserver',
        'workspace' => 'gis_project',
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
                <!-- Address Search -->
                <div class="layer-section">
                    <h3>Search Location</h3>
                    <div class="search-container">
                        <input type="text" id="addressSearch" class="search-input" placeholder="Search address or place...">
                        <button id="searchBtn" class="search-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="searchResults" class="search-results"></div>
                </div>

                <!-- Base Map Selection -->
                <div class="layer-section">
                    <h3>Base Map</h3>
                    <div class="basemap-selector">
                        <button class="basemap-btn active" data-basemap="osm">
                            <div style="width:40px;height:40px;background:#c8e6c9;border-radius:4px;margin:0 auto 4px;"></div>
                            <span>OSM</span>
                        </button>
                        <button class="basemap-btn" data-basemap="satellite">
                            <div style="width:40px;height:40px;background:#1a237e;border-radius:4px;margin:0 auto 4px;"></div>
                            <span>Satellite</span>
                        </button>
                        <button class="basemap-btn" data-basemap="google">
                            <div style="width:40px;height:40px;background:#fff3e0;border-radius:4px;margin:0 auto 4px;"></div>
                            <span>Google Maps</span>
                        </button>
                        <button class="basemap-btn" data-basemap="carto">
                            <div style="width:40px;height:40px;background:#f5f5f5;border-radius:4px;margin:0 auto 4px;"></div>
                            <span>Carto Light</span>
                        </button>
                    </div>
                </div>
                
                <!-- Views -->
                <div class="layer-section">
                    <h3>View</h3>
                    <select id="viewSelect" style="width:100%; padding:6px 8px; border-radius:4px; border:1px solid #ccc;">
                        <option value="">Loading views…</option>
                    </select>
                    <p style="font-size:0.75rem; color:#777; margin-top:4px;">
                        These match the views from the editor (VM1).
                    </p>
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
        // Use proxy to avoid CORS - proxy.php forwards to GeoServer
        const CONFIG = {
            geoserverUrl: 'proxy.php',  // Proxy handles the GeoServer connection
            workspace: '<?= $config['workspace'] ?>',
            hasGoogleApi: <?= (!empty($config['google_maps_api_key']) && $config['google_maps_api_key'] !== 'YOUR_GOOGLE_MAPS_API_KEY') ? 'true' : 'false' ?>
        };
    </script>

    <!-- Custom JS -->
    <script src="index.js"></script>
</body>
</html>