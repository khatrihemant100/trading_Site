<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__.'/config/openai.php';

// Load Gemini config if available
if (file_exists(__DIR__.'/config/gemini.php')) {
    require_once __DIR__.'/config/gemini.php';
}

$user_message = isset($_POST['message']) ? trim($_POST['message']) : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'get_response') {
    if (empty($user_message)) {
        echo json_encode(['response' => 'Please enter a message.', 'status' => 'error']);
        exit;
    }
    
    // Try OpenAI first
    $response = getOpenAIResponse($user_message);
    $is_fallback = isFallbackResponse($response, $user_message);
    
    // If OpenAI fails and returns fallback, try Gemini
    if ($is_fallback && defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
        $gemini_response = getGeminiResponse($user_message);
        if ($gemini_response !== false && !isFallbackResponse($gemini_response, $user_message)) {
            $response = $gemini_response;
        }
    }
    
    echo json_encode(['response' => $response, 'status' => 'success']);
    exit;
}

function getOpenAIResponse($message) {
    // Check if cURL is available
    if (!function_exists('curl_init')) {
        error_log("cURL is not enabled in PHP");
        return generateFallbackResponse($message);
    }
    
    // System prompt for trading assistant
    $system_prompt = "You are a helpful Trading AI Assistant specializing in trading education. You help users with:
- Trading basics and strategies
- NEPSE (Nepal Stock Exchange) trading
- Forex trading
- Risk management
- Market analysis
- Technical and fundamental analysis

Always respond in a friendly, professional manner. If asked about something outside trading, politely redirect to trading topics. Keep responses concise and practical. You can respond in English or Nepali (Devanagari script) based on the user's language preference.";

    $api_key = OPENAI_API_KEY;
    $api_url = OPENAI_API_URL;
    
    // Validate API key
    if (empty($api_key) || $api_key === 'your-api-key-here') {
        error_log("OpenAI API key is not set");
        return generateFallbackResponse($message);
    }
    
    // Prepare the request data
    $data = [
        'model' => OPENAI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => $message
            ]
        ],
        'max_tokens' => OPENAI_MAX_TOKENS,
        'temperature' => OPENAI_TEMPERATURE
    ];
    
    // Initialize cURL
    $ch = curl_init($api_url);
    
    if ($ch === false) {
        error_log("Failed to initialize cURL");
        return generateFallbackResponse($message);
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    // For local development, you might need to disable SSL verification
    // In production, keep this as true
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    
    // Handle cURL errors
    if ($curl_error || $curl_errno) {
        error_log("OpenAI API cURL Error #{$curl_errno}: {$curl_error}");
        // Return fallback response instead of error message
        return generateFallbackResponse($message);
    }
    
    // Handle HTTP errors
    if ($http_code !== 200) {
        $error_response = json_decode($response, true);
        $error_msg = isset($error_response['error']['message']) 
            ? $error_response['error']['message'] 
            : "HTTP Error {$http_code}";
        
        error_log("OpenAI API HTTP Error {$http_code}: {$error_msg}");
        error_log("Full response: " . $response);
        
        // Return fallback response if API fails
        return generateFallbackResponse($message);
    }
    
    // Parse response
    $response_data = json_decode($response, true);
    
    if (isset($response_data['choices'][0]['message']['content'])) {
        return trim($response_data['choices'][0]['message']['content']);
    } else {
        error_log("OpenAI API Response Error - Invalid response structure: " . $response);
        return generateFallbackResponse($message);
    }
}

// Check if response is a fallback response
function isFallbackResponse($response, $original_message) {
    // Check if response matches common fallback patterns
    $fallback_patterns = [
        "That's an interesting question! I'm here to help",
        "I'm here to help with trading-related topics",
        "Could you be more specific"
    ];
    
    foreach ($fallback_patterns as $pattern) {
        if (stripos($response, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

// Google Gemini API function
function getGeminiResponse($message) {
    if (!function_exists('curl_init')) {
        return false;
    }
    
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
        return false;
    }
    
    $api_key = GEMINI_API_KEY;
    $api_url = GEMINI_API_URL . '?key=' . urlencode($api_key);
    
    // System prompt for trading assistant
    $system_prompt = "You are a helpful Trading AI Assistant specializing in trading education. You help users with trading basics, NEPSE (Nepal Stock Exchange), Forex trading, risk management, market analysis, and technical/fundamental analysis. Always respond in a friendly, professional manner. Keep responses concise and practical. You can respond in English or Nepali based on the user's language preference.";
    
    // Prepare the request data for Gemini
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $system_prompt . "\n\nUser question: " . $message
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => GEMINI_TEMPERATURE,
            'maxOutputTokens' => GEMINI_MAX_TOKENS,
        ]
    ];
    
    // Initialize cURL
    $ch = curl_init($api_url);
    
    if ($ch === false) {
        return false;
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    
    // Handle errors
    if ($curl_error || $curl_errno) {
        error_log("Gemini API cURL Error #{$curl_errno}: {$curl_error}");
        return false;
    }
    
    if ($http_code !== 200) {
        error_log("Gemini API HTTP Error {$http_code}: " . $response);
        return false;
    }
    
    // Parse response
    $response_data = json_decode($response, true);
    
    if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        return trim($response_data['candidates'][0]['content']['parts'][0]['text']);
    } else {
        error_log("Gemini API Response Error - Invalid response structure: " . $response);
        return false;
    }
}

// Fallback function if OpenAI API fails
function generateFallbackResponse($message) {
    $message_lower = strtolower($message);
    
    if (preg_match('/\b(hi|hello|namaste|hey|good morning|good afternoon)\b/i', $message)) {
        return "Namaste! 👋 I'm your Trading AI Assistant. How can I help you with trading today?";
    } elseif (preg_match('/\b(what is|what\'s|explain|tell me about).*trading\b/i', $message)) {
        return "Trading is the act of buying and selling financial instruments like stocks, currencies, or commodities to make a profit. There are different types:\n\n📈 **Stock Trading**: Buying and selling shares of companies (like on NEPSE)\n💱 **Forex Trading**: Trading currency pairs (EUR/USD, GBP/USD, etc.)\n📊 **Other Markets**: Commodities, cryptocurrencies, indices\n\nKey concepts:\n- **Entry & Exit**: When to buy and sell\n- **Risk Management**: Protecting your capital\n- **Analysis**: Technical and fundamental analysis\n- **Strategy**: Having a plan before trading\n\nWould you like to know more about any specific type of trading?";
    } elseif (preg_match('/\b(basic|beginner|start|learn|how to start)\b/i', $message)) {
        return "Trading basics include understanding market trends, risk management, and having a solid trading plan. Start with education, practice with demo accounts, develop a trading plan, and always manage your risk. What specific topic would you like to know more about?";
    } elseif (preg_match('/\b(nepse|nepal stock|nepali stock|nepal share)\b/i', $message)) {
        return "NEPSE (Nepal Stock Exchange) is the primary stock exchange in Nepal. To trade, you need a demat account and trading account with a licensed broker. Key factors include company performance, market sentiment, and economic conditions. What would you like to know?";
    } elseif (preg_match('/\b(forex|currency|fx|eur|usd|gbp)\b/i', $message)) {
        return "Forex trading involves exchanging currencies. Major pairs include EUR/USD, GBP/USD, and USD/JPY. Markets are open 24/5. Key concepts include pips, lots, leverage, and margin. Risk management is crucial. What specific question do you have?";
    } elseif (preg_match('/\b(risk|stop loss|position size|risk management)\b/i', $message)) {
        return "Risk management is crucial! Never risk more than 1-2% of your account on a single trade. Always use stop-loss orders, diversify your portfolio, and keep emotions in check. Always calculate position size based on your risk tolerance.";
    } else {
        return "That's an interesting question! I'm here to help with trading-related topics. I can help with NEPSE, Forex, trading strategies, risk management, and market analysis. Could you be more specific?";
    }
}

?>

