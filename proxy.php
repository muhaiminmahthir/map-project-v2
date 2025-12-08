<?php
// proxy.php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$geoserver = 'http://35.187.255.127:8080/geoserver';
$path = $_GET['path'] ?? '';
$query = $_SERVER['QUERY_STRING'];

// Remove 'path' from query string
$query = preg_replace('/&?path=[^&]*/', '', $query);

$url = $geoserver . '/' . $path . ($query ? '?' . $query : '');

$response = file_get_contents($url);
echo $response;