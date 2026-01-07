<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
error_reporting(0);

// Market Prices API Proxy - Server-side to avoid CORS
$response = [
    'success' => false,
    'gold' => null,
    'bitcoin' => null,
    'timestamp' => time()
];

// Function to fetch data with cURL
function fetchWithCurl($url, $headers = []) {
    if (!function_exists('curl_init')) {
        return null;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && $result && !$error) {
        return json_decode($result, true);
    }
    
    return null;
}

// ========== GOLD PRICE FETCHING ==========
// Try multiple reliable sources for Gold

// Method 1: metals.live API (Free, no key required)
$goldData1 = fetchWithCurl('https://api.metals.live/v1/spot/gold');
if ($goldData1) {
    if (is_array($goldData1) && isset($goldData1[0]) && is_numeric($goldData1[0])) {
        $goldPrice = floatval($goldData1[0]);
        if ($goldPrice > 1000 && $goldPrice < 5000) {
            $response['gold'] = [
                'price' => $goldPrice,
                'change_24h' => 0
            ];
        }
    }
}

// Method 2: exchangerate-api.com (Free, reliable)
if (!isset($response['gold']['price']) || $response['gold']['price'] <= 0) {
    $xauData = fetchWithCurl('https://api.exchangerate-api.com/v4/latest/XAU');
    if ($xauData && isset($xauData['rates']['USD']) && is_numeric($xauData['rates']['USD'])) {
        $usdPrice = floatval($xauData['rates']['USD']);
        if ($usdPrice > 1000 && $usdPrice < 5000) {
            $response['gold'] = [
                'price' => $usdPrice,
                'change_24h' => 0
            ];
        }
    }
}

// Method 3: Try using file_get_contents as alternative
if (!isset($response['gold']['price']) || $response['gold']['price'] <= 0) {
    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0',
                'ignore_errors' => true
            ]
        ]);
        $goldSimple = @file_get_contents('https://api.metals.live/v1/spot/gold', false, $context);
        if ($goldSimple) {
            $goldJson = json_decode($goldSimple, true);
            if (is_array($goldJson) && isset($goldJson[0]) && is_numeric($goldJson[0])) {
                $goldPrice = floatval($goldJson[0]);
                if ($goldPrice > 1000 && $goldPrice < 5000) {
                    $response['gold'] = [
                        'price' => $goldPrice,
                        'change_24h' => 0
                    ];
                }
            }
        }
    }
}

// ========== BITCOIN PRICE FETCHING ==========
// Method 1: CoinGecko (Most reliable, free, no key)
$btcData = fetchWithCurl('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true');

if ($btcData && isset($btcData['bitcoin']['usd'])) {
    $btcPrice = floatval($btcData['bitcoin']['usd']);
    if ($btcPrice > 1000) {
        $response['bitcoin'] = [
            'price' => $btcPrice,
            'change_24h' => isset($btcData['bitcoin']['usd_24h_change']) ? floatval($btcData['bitcoin']['usd_24h_change']) : 0
        ];
    }
}

// Bitcoin fallback 1: CoinDesk
if (!isset($response['bitcoin']['price']) || $response['bitcoin']['price'] <= 0) {
    $btcAlt = fetchWithCurl('https://api.coindesk.com/v1/bpi/currentprice.json');
    if ($btcAlt && isset($btcAlt['bpi']['USD']['rate'])) {
        $price = floatval(str_replace(',', '', $btcAlt['bpi']['USD']['rate']));
        if ($price > 1000) {
            $response['bitcoin'] = [
                'price' => $price,
                'change_24h' => 0
            ];
        }
    }
}

// Bitcoin fallback 2: Binance API (public, no key needed)
if (!isset($response['bitcoin']['price']) || $response['bitcoin']['price'] <= 0) {
    $binanceData = fetchWithCurl('https://api.binance.com/api/v3/ticker/price?symbol=BTCUSDT');
    if ($binanceData && isset($binanceData['price']) && is_numeric($binanceData['price'])) {
        $btcPrice = floatval($binanceData['price']);
        if ($btcPrice > 1000) {
            $response['bitcoin'] = [
                'price' => $btcPrice,
                'change_24h' => 0
            ];
        }
    }
}

// Final validation: Only return real API data, NO fallback
// For Gold - remove if invalid
if (!isset($response['gold']['price']) || $response['gold']['price'] <= 0 || $response['gold']['price'] > 5000) {
    $response['gold'] = null;
}

// For Bitcoin - remove if invalid
if (!isset($response['bitcoin']['price']) || $response['bitcoin']['price'] <= 0) {
    $response['bitcoin'] = null;
}

// Set success only if we have REAL live data from APIs
if (($response['gold'] && isset($response['gold']['price']) && $response['gold']['price'] > 0) || 
    ($response['bitcoin'] && isset($response['bitcoin']['price']) && $response['bitcoin']['price'] > 0)) {
    $response['success'] = true;
} else {
    $response['success'] = false;
    $response['message'] = 'Live data unavailable - APIs not responding';
}

echo json_encode($response);
?>
