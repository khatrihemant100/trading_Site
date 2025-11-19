<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// NEPSE Index Scraper
// This file fetches NEPSE index from nepsealpha.com

function fetchNEPSEIndex() {
    $url = 'https://nepsealpha.com/live-market';
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
        return null;
    }
    
    // Parse HTML to extract NEPSE index
    // This is a basic parser - you may need to adjust based on actual HTML structure
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    // Try to find NEPSE index value
    // Common patterns: look for index value in various elements
    $patterns = [
        '//*[contains(@class, "index")]',
        '//*[contains(@class, "nepse")]',
        '//*[contains(@id, "index")]',
        '//*[contains(text(), "NEPSE")]'
    ];
    
    $index = null;
    $change = null;
    $changePercent = null;
    
    // Try to extract from common HTML structures
    if (preg_match('/NEPSE.*?(\d+\.?\d*)/i', $html, $matches)) {
        $index = $matches[1];
    }
    
    // Try to find change value
    if (preg_match('/([+-]?\d+\.?\d*)\s*\(([+-]?\d+\.?\d*)%\)/i', $html, $matches)) {
        $change = $matches[1];
        $changePercent = $matches[2];
    }
    
    if ($index) {
        return [
            'index' => floatval($index),
            'change' => $change ? floatval($change) : 0,
            'changePercent' => $changePercent ? floatval($changePercent) : 0
        ];
    }
    
    return null;
}

// Alternative: Use NEPSE official API if available
function fetchNEPSEFromAPI() {
    // NEPSE official API endpoint (if available)
    $apiUrl = 'https://www.nepalstock.com/api/nots/market-summary';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && isset($data['index'])) {
            return [
                'index' => floatval($data['index']),
                'change' => isset($data['change']) ? floatval($data['change']) : 0,
                'changePercent' => isset($data['changePercent']) ? floatval($data['changePercent']) : 0
            ];
        }
    }
    
    return null;
}

// Try to fetch NEPSE data
$nepseData = fetchNEPSEFromAPI();

if (!$nepseData) {
    $nepseData = fetchNEPSEIndex();
}

if ($nepseData) {
    echo json_encode([
        'success' => true,
        'index' => $nepseData['index'],
        'change' => $nepseData['change'],
        'changePercent' => $nepseData['changePercent'],
        'timestamp' => time()
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch NEPSE data. Please visit https://nepsealpha.com/live-market for live data.',
        'link' => 'https://nepsealpha.com/live-market'
    ]);
}
?>

