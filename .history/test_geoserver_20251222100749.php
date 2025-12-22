<?php
/**
 * GeoServer Connection Test Script
 * Upload this to your web server and visit it in browser to diagnose issues
 */

header('Content-Type: text/html; charset=utf-8');

$geoserver_base = 'http://geoserversafe.duckdns.org:65437/geoserver';
$test_url = $geoserver_base . '/web/';

echo "<h1>🔍 GeoServer Connection Test</h1>";
echo "<hr>";

// Test 1: Check PHP cURL
echo "<h2>1. PHP cURL Extension</h2>";
if (function_exists('curl_init')) {
    echo "✅ cURL is installed and enabled<br>";
    echo "Version: " . curl_version()['version'] . "<br>";
} else {
    echo "❌ cURL is NOT installed!<br>";
    echo "<b>Fix:</b> <code>sudo yum install php-curl && sudo systemctl restart httpd</code><br>";
    exit;
}

// Test 2: Check allow_url_fopen
echo "<h2>2. PHP allow_url_fopen</h2>";
if (ini_get('allow_url_fopen')) {
    echo "✅ allow_url_fopen is enabled<br>";
} else {
    echo "⚠️ allow_url_fopen is disabled (not critical if cURL works)<br>";
}

// Test 3: Try to connect to GeoServer
echo "<h2>3. Connection to GeoServer</h2>";
echo "Target: <code>$geoserver_base</code><br><br>";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $test_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => true,  // HEAD request only
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$errno = curl_errno($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($errno) {
    echo "❌ <b>Connection FAILED</b><br>";
    echo "Error: $error<br>";
    echo "Error code: $errno<br><br>";
    
    echo "<b>Possible causes:</b><br>";
    echo "<ul>";
    echo "<li>GeoServer is not running on http://geoserversafe.duckdns.org:65437/geoserver</li>";
    echo "<li>Firewall is blocking port 65437</li>";
    echo "<li>The IP address is incorrect</li>";
    echo "<li>Network routing issue between servers</li>";
    echo "</ul>";
    
    echo "<b>Debug commands to run on THIS server (naktengokmap):</b><br>";
    echo "<pre>";
    echo "# Test if port is reachable:\n";
    echo "curl -I http://geoserversafe.duckdns.org:65437/geoserver/web/\n\n";
    echo "# Test with telnet:\n";
    echo "telnet 34.158.42.102 65437\n\n";
    echo "# Check if it's a DNS issue:\n";
    echo "ping 34.158.42.102\n";
    echo "</pre>";
} else {
    echo "✅ <b>Connection SUCCESSFUL</b><br>";
    echo "HTTP Status: " . $info['http_code'] . "<br>";
    echo "Response time: " . round($info['total_time'], 3) . "s<br>";
    echo "IP connected: " . $info['primary_ip'] . "<br>";
}

// Test 4: Try WMS GetCapabilities
echo "<h2>4. WMS GetCapabilities Test</h2>";
$wms_url = $geoserver_base . '/geodb/wms?service=WMS&request=GetCapabilities';
echo "Testing: <code>$wms_url</code><br><br>";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $wms_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($error) {
    echo "❌ WMS request failed: $error<br>";
} else {
    echo "HTTP Status: $http_code<br>";
    echo "Content-Type: $content_type<br>";
    
    if ($http_code == 200 && strpos($response, 'WMS_Capabilities') !== false) {
        echo "✅ <b>WMS is working!</b><br>";
        
        // Check if workspace exists
        if (strpos($response, 'geodb:roads') !== false) {
            echo "✅ Found layer: geodb:roads<br>";
        }
        if (strpos($response, 'geodb:areas') !== false) {
            echo "✅ Found layer: geodb:areas<br>";
        }
        if (strpos($response, 'geodb:buildings') !== false) {
            echo "✅ Found layer: geodb:buildings<br>";
        }
    } else {
        echo "⚠️ Got response but may not be valid WMS<br>";
        echo "First 500 chars: <pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
    }
}

echo "<hr>";
echo "<p><small>Test completed at " . date('Y-m-d H:i:s') . "</small></p>";