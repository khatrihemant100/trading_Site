<!-- AI Chat Widget -->
<div id="aiChatWidget" class="ai-chat-widget">
    <!-- Chat Button -->
    <button id="chatToggleBtn" class="chat-toggle-btn" onclick="toggleAIChat()">
        <i class="fas fa-robot"></i>
        <span class="chat-badge">AI</span>
    </button>
    
    <!-- Chat Window -->
    <div id="chatWindow" class="chat-window">
        <div class="chat-header">
            <div class="d-flex align-items-center">
                <div class="ai-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="ms-2">
                    <h6 class="mb-0">Trading AI Assistant</h6>
                    <small class="text-muted">Online</small>
                </div>
            </div>
            <button class="btn-close btn-close-white" onclick="toggleAIChat()"></button>
        </div>
        
        <div class="chat-body" id="chatBody">
            <div class="welcome-message">
                <div class="ai-message">
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <p>Namaste! 👋 I'm your Trading AI Assistant. I can help you with:</p>
                        <ul class="quick-topics">
                            <li>📈 Trading basics & strategies</li>
                            <li>🇳🇵 NEPSE trading</li>
                            <li>💱 Forex trading</li>
                            <li>⚖️ Risk management</li>
                            <li>📊 Market analysis</li>
                        </ul>
                        <p class="mb-0">Ask me anything about trading!</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Action Buttons -->
            <div class="quick-actions">
                <button class="quick-action-btn" onclick="sendQuickMessage('What are trading basics?')">
                    <i class="fas fa-book"></i> Trading Basics
                </button>
                <button class="quick-action-btn" onclick="sendQuickMessage('Tell me about NEPSE')">
                    <i class="fas fa-chart-line"></i> About NEPSE
                </button>
                <button class="quick-action-btn" onclick="sendQuickMessage('What is risk management?')">
                    <i class="fas fa-shield-alt"></i> Risk Management
                </button>
                <button class="quick-action-btn" onclick="sendQuickMessage('Explain forex trading')">
                    <i class="fas fa-exchange-alt"></i> Forex Trading
                </button>
            </div>
        </div>
        
        <div class="chat-footer">
            <div class="typing-indicator" id="typingIndicator" style="display: none;">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <form id="chatForm" onsubmit="sendMessage(event)">
                <input type="text" id="chatInput" class="chat-input" placeholder="Ask me about trading..." autocomplete="off">
                <button type="submit" class="chat-send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
/* AI Chat Widget Styles */
.ai-chat-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.chat-toggle-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.chat-toggle-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 30px rgba(16, 185, 129, 0.6);
}

.chat-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: bold;
}

.chat-window {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 380px;
    height: 600px;
    background: var(--dark-card);
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    display: none;
    flex-direction: column;
    border: 1px solid var(--border-color);
    overflow: hidden;
    animation: slideUp 0.3s ease-out;
}

.chat-window.active {
    display: flex;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.chat-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
}

.ai-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.chat-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: var(--dark-bg);
}

.chat-body::-webkit-scrollbar {
    width: 6px;
}

.chat-body::-webkit-scrollbar-track {
    background: var(--dark-card);
}

.chat-body::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 3px;
}

.welcome-message {
    margin-bottom: 15px;
}

.ai-message {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.message-content {
    background: var(--dark-card);
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    flex: 1;
}

.message-content p {
    margin: 0 0 8px 0;
    color: var(--text-primary);
    line-height: 1.5;
}

.message-content ul {
    margin: 8px 0;
    padding-left: 20px;
    color: var(--text-secondary);
}

.message-content li {
    margin: 4px 0;
}

.user-message {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-direction: row-reverse;
    animation: fadeIn 0.3s ease-in;
}

.user-message .message-content {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    color: white;
}

.user-message .message-avatar {
    background: var(--dark-hover);
}

.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 15px;
}

.quick-action-btn {
    background: var(--dark-card);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.quick-action-btn:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: translateY(-2px);
}

.chat-footer {
    padding: 15px;
    background: var(--dark-card);
    border-top: 1px solid var(--border-color);
}

.typing-indicator {
    display: flex;
    gap: 4px;
    padding: 10px;
    margin-bottom: 10px;
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--primary);
    animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% {
        transform: translateY(0);
        opacity: 0.7;
    }
    30% {
        transform: translateY(-10px);
        opacity: 1;
    }
}

.chat-form {
    display: flex;
    gap: 10px;
}

.chat-input {
    flex: 1;
    background: var(--dark-bg);
    border: 1px solid var(--border-color);
    border-radius: 25px;
    padding: 10px 20px;
    color: var(--text-primary);
    font-size: 0.9rem;
    outline: none;
    transition: all 0.3s;
}

.chat-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.chat-send-btn {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.chat-send-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

@media (max-width: 768px) {
    .chat-window {
        width: calc(100vw - 40px);
        height: calc(100vh - 100px);
        bottom: 80px;
        right: 20px;
        left: 20px;
    }
    
    .chat-toggle-btn {
        width: 55px;
        height: 55px;
        font-size: 1.3rem;
    }
}
</style>

<script>
let chatOpen = false;

function toggleAIChat() {
    const chatWindow = document.getElementById('chatWindow');
    chatOpen = !chatOpen;
    
    if (chatOpen) {
        chatWindow.classList.add('active');
        document.getElementById('chatInput').focus();
    } else {
        chatWindow.classList.remove('active');
    }
}

function sendQuickMessage(message) {
    document.getElementById('chatInput').value = message;
    sendMessage(new Event('submit'));
}

// Store conversation history
let conversationHistory = [];

function sendMessage(event) {
    event.preventDefault();
    
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message to chat
    addMessage(message, 'user');
    conversationHistory.push({role: 'user', content: message});
    input.value = '';
    
    // Show typing indicator
    showTypingIndicator();
    
    // Determine the correct path to ai-chat.php based on current location
    const currentPath = window.location.pathname;
    let chatApiPath = 'ai-chat.php';
    
    // If we're in dashboard folder, go up one level
    if (currentPath.includes('/dashboard/')) {
        chatApiPath = '../ai-chat.php';
    }
    
    // Send to AI
    fetch(chatApiPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_response&message=${encodeURIComponent(message)}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        hideTypingIndicator();
        if (data.status === 'success') {
            addMessage(data.response, 'ai');
            conversationHistory.push({role: 'assistant', content: data.response});
        } else {
            const errorMsg = data.response || 'Sorry, I encountered an error. Please try again.';
            addMessage(errorMsg, 'ai');
        }
    })
    .catch(error => {
        hideTypingIndicator();
        console.error('Error:', error);
        addMessage('Sorry, I\'m having trouble connecting. Please check your internet connection and try again later.', 'ai');
    });
}

function addMessage(text, type) {
    const chatBody = document.getElementById('chatBody');
    const messageDiv = document.createElement('div');
    messageDiv.className = type === 'user' ? 'user-message' : 'ai-message';
    
    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.innerHTML = type === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';
    
    const content = document.createElement('div');
    content.className = 'message-content';
    // Escape HTML and preserve line breaks
    const escapedText = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/\n/g, '<br>');
    content.innerHTML = `<p>${escapedText}</p>`;
    
    messageDiv.appendChild(avatar);
    messageDiv.appendChild(content);
    chatBody.appendChild(messageDiv);
    
    // Remove welcome message and quick actions after first user message
    const welcomeMsg = chatBody.querySelector('.welcome-message');
    const quickActions = chatBody.querySelector('.quick-actions');
    if (welcomeMsg && type === 'user') {
        welcomeMsg.style.display = 'none';
        if (quickActions) quickActions.style.display = 'none';
    }
    
    // Scroll to bottom
    setTimeout(() => {
        chatBody.scrollTop = chatBody.scrollHeight;
    }, 100);
}

function showTypingIndicator() {
    document.getElementById('typingIndicator').style.display = 'flex';
    const chatBody = document.getElementById('chatBody');
    chatBody.scrollTop = chatBody.scrollHeight;
}

function hideTypingIndicator() {
    document.getElementById('typingIndicator').style.display = 'none';
}

// Close chat when clicking outside (optional)
document.addEventListener('click', function(event) {
    const widget = document.getElementById('aiChatWidget');
    const toggleBtn = document.getElementById('chatToggleBtn');
    const chatWindow = document.getElementById('chatWindow');
    
    if (chatOpen && !widget.contains(event.target)) {
        // Keep chat open on mobile, close on desktop
        if (window.innerWidth > 768) {
            toggleAIChat();
        }
    }
});
</script>

