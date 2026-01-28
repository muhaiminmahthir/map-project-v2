<?php
/**
 * GeoServer Proxy - Bypasses CORS for WMS/WFS requests
 * With detailed error reporting for debugging
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

// =====================================================
// CONFIGURATION - Update this to your GeoServer
// =====================================================
$geoserver_base = 'http://10.164.14.203:8080/geoserver';

// =====================================================
// DEBUG MODE - Set to true to see detailed errors
// =====================================================
$debug = true;

// Get the path
$path_info = $_SERVER['PATH_INFO'] ?? '';

if (empty($path_info) && isset($_GET['path'])) {
    $path_info = '/' . $_GET['path'];
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

// Check if cURL is available
if (!function_exists('curl_init')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'cURL not available',
        'message' => 'PHP cURL extension is not installed or enabled',
        'solution' => 'Run: sudo yum install php-curl && sudo systemctl restart httpd'
    ]);
    exit;
}

// Initialize cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $geoserver_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HEADER => true,
    // Don't verify SSL (in case of self-signed certs)
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

// Execute request
$response = curl_exec($ch);
$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

// Check for cURL errors
if ($curl_errno) {
    http_response_code(502);
    header('Content-Type: application/json');
    
    $error_details = [
        'error' => 'Connection to GeoServer failed',
        'curl_error' => $curl_error,
        'curl_errno' => $curl_errno,
        'target_url' => $debug ? $geoserver_url : '[hidden]',
        'time_elapsed' => $total_time . 's'
    ];
    
    // Add helpful messages based on error type
    switch ($curl_errno) {
        case CURLE_COULDNT_CONNECT:
            $error_details['likely_cause'] = 'GeoServer is not running or port 8080 is blocked';
            $error_details['solution'] = 'Check if GeoServer is running: curl -I ' . $geoserver_base;
            break;
        case CURLE_OPERATION_TIMEDOUT:
            $error_details['likely_cause'] = 'Connection timed out - firewall may be blocking';
            $error_details['solution'] = 'Check firewall rules on GeoServer VM';
            break;
        case CURLE_COULDNT_RESOLVE_HOST:
            $error_details['likely_cause'] = 'Cannot resolve hostname';
            $error_details['solution'] = 'Check the GeoServer URL in proxy.php';
            break;
        default:
            $error_details['likely_cause'] = 'Network connectivity issue';
    }
    
    echo json_encode($error_details, JSON_PRETTY_PRINT);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Separate headers and body
$header_text = substr($response, 0, $header_size);
$body = substr($response, $header_size);

// Parse headers
$headers = [];
foreach (explode("\r\n", $header_text) as $line) {
    if (strpos($line, ':') !== false) {
        list($key, $value) = explode(':', $line, 2);
        $headers[strtolower(trim($key))] = trim($value);
    }
}

// Forward Content-Type (critical for images!)
if (isset($headers['content-type'])) {
    header('Content-Type: ' . $headers['content-type']);
} else {
    // Default to octet-stream if no content type
    header('Content-Type: application/octet-stream');
}

// Set HTTP response code
http_response_code($http_code);

// Output the response body
echo $body;