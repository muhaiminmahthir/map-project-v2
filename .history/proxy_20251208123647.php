<?php
/**
 * GeoServer Proxy - Bypasses CORS for WMS/WFS requests
 * 
 * Usage: proxy.php/workspace/wms?params... OR proxy.php?path=workspace/wms&params...
 */

// Allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// GeoServer base URL (no trailing slash)
$geoserver_base = 'http://35.187.255.127:8080/geoserver';

// Get the path after proxy.php
$path_info = $_SERVER['PATH_INFO'] ?? '';

// If no PATH_INFO, try to get path from query string (fallback method)
if (empty($path_info) && isset($_GET['path'])) {
    $path_info = '/' . $_GET['path'];
    // Remove 'path' from the query string we'll forward
    $query_params = $_GET;
    unset($query_params['path']);
    $query_string = http_build_query($query_params);
} else {
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
}

// Build the full GeoServer URL
$geoserver_url = $geoserver_base . $path_info;
if (!empty($query_string)) {
    $geoserver_url .= '?' . $query_string;
}

// Debug logging (comment out in production)
error_log("GeoServer Proxy: " . $geoserver_url);

// Initialize cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $geoserver_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADER => true,
]);

// Execute request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

if (curl_errno($ch)) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Proxy error',
        'message' => curl_error($ch),
        'url' => $geoserver_url
    ]);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Separate headers and body
$header_text = substr($response, 0, $header_size);
$body = substr($response, $header_size);

// Parse headers from GeoServer response
$headers = [];
foreach (explode("\r\n", $header_text) as $line) {
    if (strpos($line, ':') !== false) {
        list($key, $value) = explode(':', $line, 2);
        $headers[strtolower(trim($key))] = trim($value);
    }
}

// Forward important headers (especially Content-Type for images!)
if (isset($headers['content-type'])) {
    header('Content-Type: ' . $headers['content-type']);
}
if (isset($headers['content-length'])) {
    header('Content-Length: ' . $headers['content-length']);
}

// Set HTTP response code
http_response_code($http_code);

// Output the response body
echo $body;