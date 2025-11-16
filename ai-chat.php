<?php
session_start();
header('Content-Type: application/json');

// Simple AI response handler
// Note: For production, integrate with OpenAI API or similar

$user_message = isset($_POST['message']) ? trim($_POST['message']) : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'get_response') {
    $response = generateAIResponse($user_message);
    echo json_encode(['response' => $response, 'status' => 'success']);
    exit;
}

function generateAIResponse($message) {
    $message_lower = strtolower($message);
    
    // Trading-related responses
    $responses = [
        'greeting' => [
            "Namaste! 👋 I'm your Trading AI Assistant. How can I help you with trading today?",
            "Hello! I'm here to help you with trading questions, market analysis, and trading strategies. What would you like to know?",
            "Hi there! Ready to learn about trading? Ask me anything about stocks, forex, or trading strategies!"
        ],
        'trading_basics' => [
            "Trading basics include understanding market trends, risk management, and having a solid trading plan. Would you like to know more about any specific topic?",
            "The fundamentals of trading involve technical analysis, fundamental analysis, and proper risk management. What aspect interests you most?",
            "Key trading basics: 1) Start with education 2) Practice with demo accounts 3) Develop a trading plan 4) Manage your risk. Need details on any of these?"
        ],
        'nepse' => [
            "NEPSE (Nepal Stock Exchange) is the primary stock exchange in Nepal. You can trade stocks of listed companies. Want to know about specific stocks or trading strategies?",
            "NEPSE trading involves buying and selling shares of Nepalese companies. Key factors include company performance, market sentiment, and economic conditions. What would you like to know?",
            "To trade on NEPSE, you need a demat account and a trading account with a licensed broker. I can help you understand the process better!"
        ],
        'forex' => [
            "Forex trading involves exchanging currencies. Major pairs include EUR/USD, GBP/USD, and USD/JPY. Risk management is crucial in forex trading. What specific question do you have?",
            "Forex markets are open 24/5. Key concepts include pips, lots, leverage, and margin. Would you like to learn about any of these?",
            "Forex trading requires understanding currency pairs, market analysis, and risk management. I can help you get started!"
        ],
        'risk_management' => [
            "Risk management is crucial! Never risk more than 1-2% of your account on a single trade. Always use stop-loss orders and never trade with money you can't afford to lose.",
            "Key risk management rules: 1) Use stop-loss orders 2) Don't risk more than 1-2% per trade 3) Diversify your portfolio 4) Keep emotions in check. Want more details?",
            "Proper risk management protects your capital. Always calculate your position size based on your risk tolerance and account size."
        ],
        'strategy' => [
            "Trading strategies vary: day trading, swing trading, position trading. Choose based on your time availability and risk tolerance. What type interests you?",
            "Popular strategies include trend following, breakout trading, and mean reversion. Each has pros and cons. Which one would you like to explore?",
            "A good trading strategy should have clear entry/exit rules, risk management, and be backtested. Need help developing one?"
        ],
        'default' => [
            "That's an interesting question! I'm here to help with trading-related topics. Could you be more specific? I can help with NEPSE, Forex, trading strategies, risk management, and more!",
            "I specialize in trading topics. Ask me about stocks, forex, trading strategies, risk management, or market analysis!",
            "I'm your trading assistant! I can help with trading basics, market analysis, strategies, and risk management. What would you like to know?"
        ]
    ];
    
    // Detect intent
    if (preg_match('/\b(hi|hello|namaste|hey|good morning|good afternoon)\b/i', $message)) {
        return $responses['greeting'][array_rand($responses['greeting'])];
    } elseif (preg_match('/\b(basic|beginner|start|learn|how to start)\b/i', $message)) {
        return $responses['trading_basics'][array_rand($responses['trading_basics'])];
    } elseif (preg_match('/\b(nepse|nepal stock|nepali stock|nepal share)\b/i', $message)) {
        return $responses['nepse'][array_rand($responses['nepse'])];
    } elseif (preg_match('/\b(forex|currency|fx|eur|usd|gbp)\b/i', $message)) {
        return $responses['forex'][array_rand($responses['forex'])];
    } elseif (preg_match('/\b(risk|stop loss|position size|risk management)\b/i', $message)) {
        return $responses['risk_management'][array_rand($responses['risk_management'])];
    } elseif (preg_match('/\b(strategy|strategies|method|approach|system)\b/i', $message)) {
        return $responses['strategy'][array_rand($responses['strategy'])];
    } else {
        return $responses['default'][array_rand($responses['default'])];
    }
}

?>

